<?php
/**
 * EducaTudo - Importação de Exercícios Personalizados (IA) para o schema rígido
 *
 * Antes, quem transformava o resultado canônico da IA (alternativas[]) no
 * schema fixo de questoes_personalizadas (colunas alternativa_a..e) era o
 * NAVEGADOR — só rodava se o aluno ficasse na tela até o AIJobPoller detectar
 * "pronto". Se o aluno saísse antes, a lista ficava presa em "gerando" pra
 * sempre, mesmo com a IA já tendo terminado nos bastidores (bug real visto em
 * produção — várias listas travadas).
 *
 * Esta classe move essa etapa pro servidor: processarPendencias() é chamada
 * pelo cron (process_ai_jobs.php) a cada minuto, em cada tenant, e resolve
 * toda lista com status='gerando' cujo job de IA já terminou (com sucesso ou
 * falha) ou que passou do tempo limite — sem depender do navegador estar
 * aberto. O endpoint HTTP (CustomExerciseController::importarQuestoesIA)
 * continua existindo só pra dar feedback imediato quando o aluno de fato
 * ficou esperando na tela.
 *
 * Concorrência: cron roda a cada minuto sem lock entre execuções, e o mesmo
 * job pode ser resolvido tanto pelo cron quanto pelo endpoint HTTP (se o
 * aluno ainda estiver na tela). Por isso toda transição de status usa UPDATE
 * condicionado (`WHERE status = 'gerando'`) como trava atômica — só quem
 * realmente vira a linha primeiro (rowCount()===1) segue adiante (insere
 * questões / estorna crédito); os demais desistem em silêncio.
 */
class CustomExerciseImportService
{
    /** Acima disso, uma lista "gerando" sem job resolvido é considerada travada. */
    private const TIMEOUT_MINUTOS = 10;

    /**
     * Varre as listas em 'gerando' do tenant atual e resolve as que já têm
     * job de IA concluído/falho, ou que estouraram o tempo limite.
     *
     * @return int quantidade de listas resolvidas nesta chamada
     */
    public static function processarPendencias(): int
    {
        $db = \Database::getInstance();
        if (!$db->tableExists('listas_personalizadas_exercicios') || !$db->tableExists('ai_jobs')) {
            return 0;
        }

        // updated_at, não created_at: "Tentar novamente" reseta a lista pra 'gerando' e
        // isso toca updated_at (ON UPDATE CURRENT_TIMESTAMP) — usar created_at faria o
        // timeout contar a partir da tentativa ORIGINAL, derrubando o retry sozinho no
        // próximo tick do cron sempre que a lista já estivesse velha o suficiente.
        $listas = $db->fetchAll(
            "SELECT id, updated_at FROM listas_personalizadas_exercicios WHERE status = 'gerando'"
        );
        if (empty($listas)) {
            return 0;
        }

        $resolvidas = 0;
        foreach ($listas as $lista) {
            $listaId = (int) $lista['id'];
            try {
                if (self::resolverLista($listaId, (string) $lista['updated_at'])) {
                    $resolvidas++;
                }
            } catch (\Throwable $e) {
                if (class_exists('\Logger')) {
                    \Logger::error('CustomExerciseImportService: falha ao resolver lista', [
                        'lista_id' => $listaId,
                        'exception' => $e,
                    ], 'exercicios_personalizados');
                }
            }
        }

        return $resolvidas;
    }

    private static function resolverLista(int $listaId, string $atualizadaEm): bool
    {
        $db = \Database::getInstance();

        // Job mais recente ligado a essa lista (payload.lista_id é gravado no enqueue).
        $job = $db->fetch(
            "SELECT id, status, result, error_message, payload
             FROM ai_jobs
             WHERE job_type = 'gerar_questao_ia'
               AND JSON_EXTRACT(payload, '$.lista_id') = :lista_id
             ORDER BY id DESC
             LIMIT 1",
            ['lista_id' => $listaId]
        );

        if ($job) {
            if ($job['status'] === 'done') {
                return self::importar($listaId, $job);
            }
            if ($job['status'] === 'failed') {
                // AIJobService::processNext() já estorna o crédito quando marca o job
                // 'failed' definitivamente (ver refundCredits) — não estornar de novo aqui.
                self::marcarComoErro($listaId, $job['error_message'] ?: 'Não foi possível gerar os exercícios.');
                return true;
            }
            // pending/processing: ainda rodando, só resolve por timeout abaixo.
        }

        $idadeMinutos = (strtotime('now') - strtotime($atualizadaEm)) / 60;
        if ($idadeMinutos > self::TIMEOUT_MINUTOS) {
            // Job nunca chegou a 'failed' (preso em pending/processing) ou nem foi
            // encontrado — ninguém estornou ainda. marcarComoErro() só devolve true se
            // ESTA chamada venceu a corrida (UPDATE condicionado), então só estorna
            // quando realmente foi quem resolveu — nunca em duplicidade.
            if (self::marcarComoErro($listaId, 'Tempo limite excedido ao gerar os exercícios.')) {
                self::estornarSePossivel($job['payload'] ?? null);
                return true;
            }
            return false;
        }

        return false;
    }

    /**
     * @param array<string,mixed> $job linha de ai_jobs com status='done' (precisa ter
     *                                 'result' e, se possível, 'payload')
     */
    public static function importar(int $listaId, array $job): bool
    {
        $db = \Database::getInstance();

        $result = json_decode((string) ($job['result'] ?? ''), true) ?: [];
        $questoes = $result['questoes'] ?? [];

        if (empty($questoes)) {
            if (self::marcarComoErro($listaId, 'Nenhuma questão gerada.')) {
                self::estornarSePossivel($job['payload'] ?? null);
                return true;
            }
            return false;
        }

        // Reivindica a lista e insere as questões na MESMA transação: se o INSERT de
        // alguma questão falhar no meio do loop (deadlock, conexão caiu, disco cheio),
        // o rollback desfaz também a mudança de status — a lista volta pra 'gerando' e
        // o timeout do cron cuida dela depois (marca erro, estorna, libera retry) em vez
        // de ficar "concluída" pra sempre com metade das questões e ninguém percebendo.
        // O lock de linha do UPDATE condicionado abaixo continua servindo de trava contra
        // execução concorrente (cron sobrepondo, ou cron + endpoint HTTP), só que agora
        // seguro até o commit — janela de corrida menor que antes, não maior.
        $db->beginTransaction();
        try {
            $ganhou = $db->update(
                "UPDATE listas_personalizadas_exercicios SET status = 'concluido' WHERE id = :id AND status = 'gerando'",
                ['id' => $listaId]
            );
            if ($ganhou !== 1) {
                $db->rollback();
                return false;
            }

            $letras = ['A', 'B', 'C', 'D', 'E'];
            foreach ($questoes as $index => $questao) {
                $alternativas = $questao['alternativas'] ?? [];
                $porLetra = [];
                $letraCorreta = 'A';
                foreach (array_slice($alternativas, 0, 5) as $i => $alt) {
                    $letra = $letras[$i];
                    $porLetra[$letra] = $alt['texto'] ?? '';
                    if (!empty($alt['correta'])) {
                        $letraCorreta = $letra;
                    }
                }

                $db->insert(
                    "INSERT INTO questoes_personalizadas
                     (lista_id, pergunta, alternativa_a, alternativa_b, alternativa_c, alternativa_d, alternativa_e, resposta_correta, explicacao, nivel_dificuldade, ordem)
                     VALUES (:lista_id, :pergunta, :a, :b, :c, :d, :e, :correta, :explicacao, :nivel, :ordem)",
                    [
                        'lista_id' => $listaId,
                        'pergunta' => $questao['enunciado'] ?? 'Pergunta não fornecida',
                        'a' => $porLetra['A'] ?? '',
                        'b' => $porLetra['B'] ?? '',
                        'c' => $porLetra['C'] ?? '',
                        'd' => $porLetra['D'] ?? '',
                        'e' => $porLetra['E'] ?? '',
                        'correta' => $letraCorreta,
                        'explicacao' => $questao['explicacao'] ?? '',
                        'nivel' => ['facil' => 'Fácil', 'medio' => 'Médio', 'dificil' => 'Difícil', 'desafio' => 'Difícil'][$questao['nivel_dificuldade'] ?? 'medio'] ?? 'Médio',
                        'ordem' => $index + 1,
                    ]
                );
            }

            $db->commit();
            return true;
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * UPDATE condicionado a status='gerando' — atômico, serve de trava contra corrida.
     * @return bool true só quando ESTA chamada de fato mudou o status (venceu a corrida).
     */
    public static function marcarComoErro(int $listaId, string $mensagem): bool
    {
        $db = \Database::getInstance();
        $afetadas = $db->update(
            "UPDATE listas_personalizadas_exercicios SET status = 'erro', mensagem_erro = :erro WHERE id = :id AND status = 'gerando'",
            ['id' => $listaId, 'erro' => mb_substr($mensagem, 0, 500)]
        );
        return $afetadas === 1;
    }

    /**
     * Estorna o crédito do job (se o payload trouxer credits_ref/credits_modulo) — nunca
     * lança exceção pra fora, log de aviso em caso de falha.
     */
    private static function estornarSePossivel(?string $payloadJson): void
    {
        if (!$payloadJson) {
            return;
        }
        $payload = json_decode($payloadJson, true) ?: [];
        $ref = $payload['credits_ref'] ?? null;
        $modulo = $payload['credits_modulo'] ?? null;
        if (!$ref || !$modulo) {
            return;
        }
        try {
            require_once __DIR__ . '/CreditosService.php';
            (new \App\Services\CreditosService())->estornarPorReferencia((string) $modulo, (string) $ref);
        } catch (\Throwable $e) {
            if (class_exists('\Logger')) {
                \Logger::warning('CustomExerciseImportService: falha ao estornar crédito', [
                    'exception' => $e,
                ], 'exercicios_personalizados');
            }
        }
    }
}
