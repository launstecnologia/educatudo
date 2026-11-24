<?php

namespace App\Modulos\Provas\Services;

use App\Core\Database;

/** Copia questões da prova para o banco do professor e grava as criadas direto no banco. */
class BancoQuestaoProfessorService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function sincronizarProvasDoProfessor(int $professorId): void
    {
        if ($professorId <= 0 || !$this->bancoDisponivel()) {
            return;
        }
        try {
            $questoes = $this->db->fetchAll(
                "SELECT q.id
                   FROM provas_questoes q
                   INNER JOIN provas p ON p.id = q.prova_id AND p.deleted_at IS NULL
                  WHERE p.professor_id = :professor_id
                  ORDER BY q.id ASC",
                ['professor_id' => $professorId]
            ) ?: [];
        } catch (\Throwable $e) {
            return;
        }
        foreach ($questoes as $questao) {
            $this->salvarQuestaoDaProva((int) ($questao['id'] ?? 0));
        }
    }

    public function salvarQuestaoDaProva(int $questaoId): void
    {
        if ($questaoId <= 0 || !$this->bancoDisponivel()) {
            return;
        }
        try {
            $questao = $this->db->fetch(
                "SELECT q.id, q.enunciado, q.tipo, q.valor, q.nivel_dificuldade, q.explicacao, q.prova_id,
                        p.professor_id, p.titulo AS prova_titulo, m.nome AS materia_nome
                   FROM provas_questoes q
                   INNER JOIN provas p ON p.id = q.prova_id AND p.deleted_at IS NULL
                   LEFT JOIN materias m ON m.id = p.materia_id
                  WHERE q.id = :id
                  LIMIT 1",
                ['id' => $questaoId]
            );
            if (!is_array($questao) || (int) ($questao['professor_id'] ?? 0) <= 0) {
                return;
            }
            $alternativasRows = $this->db->fetchAll(
                "SELECT texto, correta, ordem
                   FROM provas_alternativas
                  WHERE questao_id = :id
                  ORDER BY ordem ASC, id ASC",
                ['id' => $questaoId]
            ) ?: [];
            [$alternativas, $gabarito] = $this->montarAlternativas($alternativasRows);
            $this->gravar([
                'professor_id' => (int) $questao['professor_id'],
                'external_id' => 'prova_questao:' . $questaoId,
                'titulo' => $this->tituloDe((string) ($questao['enunciado'] ?? '')),
                'materia' => (string) ($questao['materia_nome'] ?? ''),
                'assunto' => (string) ($questao['prova_titulo'] ?? ''),
                'tipo' => (string) ($questao['tipo'] ?? 'multipla_escolha'),
                'nivel' => $this->nivel((string) ($questao['nivel_dificuldade'] ?? '')),
                'origem' => 'prova_manual',
                'origem_referencia' => 'prova:' . (int) ($questao['prova_id'] ?? 0) . ';questao:' . $questaoId,
                'enunciado' => (string) ($questao['enunciado'] ?? ''),
                'alternativas' => $alternativas,
                'gabarito' => $gabarito,
                'resolucao' => (string) ($questao['explicacao'] ?? ''),
                'payload' => [
                    'origem' => 'prova',
                    'prova_id' => (int) ($questao['prova_id'] ?? 0),
                    'questao_id' => $questaoId,
                    'valor' => (float) ($questao['valor'] ?? 1),
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('Banco do professor, questão da prova #' . $questaoId . ': ' . $e->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $dados
     * @return array{ok:bool,erro?:string}
     */
    public function criarManual(int $professorId, array $dados): array
    {
        if ($professorId <= 0 || !$this->bancoDisponivel()) {
            return ['ok' => false, 'erro' => 'O banco de questões ainda não está disponível nesta escola.'];
        }
        $enunciado = trim((string) ($dados['enunciado'] ?? ''));
        if (trim(strip_tags($enunciado)) === '') {
            return ['ok' => false, 'erro' => 'O enunciado é obrigatório.'];
        }
        $tipo = (string) ($dados['tipo'] ?? 'multipla_escolha');
        if (!in_array($tipo, ['multipla_escolha', 'verdadeiro_falso', 'dissertativa'], true)) {
            $tipo = 'multipla_escolha';
        }
        $alternativas = [];
        $gabarito = '';
        if ($tipo === 'multipla_escolha') {
            $brutas = is_array($dados['alternativas'] ?? null) ? $dados['alternativas'] : [];
            [$alternativas, $gabarito] = $this->montarAlternativas($brutas);
            if (count($alternativas) < 2) {
                return ['ok' => false, 'erro' => 'Adicione pelo menos 2 alternativas.'];
            }
            if ($gabarito === '') {
                return ['ok' => false, 'erro' => 'Marque o gabarito.'];
            }
        }
        try {
            $this->gravar([
                'professor_id' => $professorId,
                'external_id' => 'banco_manual:' . $professorId . ':' . bin2hex(random_bytes(8)),
                'titulo' => $this->tituloDe($enunciado),
                'materia' => (string) ($dados['materia'] ?? ''),
                'assunto' => '',
                'tipo' => $tipo,
                'nivel' => $this->nivel((string) ($dados['nivel'] ?? 'facil')),
                'origem' => 'banco_manual',
                'origem_referencia' => 'banco',
                'enunciado' => $enunciado,
                'alternativas' => $alternativas,
                'gabarito' => $gabarito,
                'resolucao' => '',
                'payload' => [
                    'origem' => 'banco',
                    'valor' => (float) ($dados['valor'] ?? 1),
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('Banco do professor, criar questão: ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível salvar a questão.'];
        }

        return ['ok' => true];
    }

    /**
     * @param list<array<string,mixed>> $linhas
     * @return array{0:array<string,string>,1:string}
     */
    private function montarAlternativas(array $linhas): array
    {
        $letras = ['A', 'B', 'C', 'D', 'E'];
        $alternativas = [];
        $gabarito = '';
        $i = 0;
        foreach ($linhas as $linha) {
            if ($i >= 5) {
                break;
            }
            $texto = trim((string) ($linha['texto'] ?? ''));
            if ($texto === '' || trim(strip_tags($texto)) === '') {
                continue;
            }
            $letra = $letras[$i];
            $alternativas[$letra] = $texto;
            if (!empty($linha['correta']) && (int) $linha['correta'] === 1) {
                $gabarito = $letra;
            }
            $i++;
        }

        return [$alternativas, $gabarito];
    }

    /**
     * @param array<string,mixed> $dados
     */
    private function gravar(array $dados): void
    {
        $alternativas = is_array($dados['alternativas'] ?? null) ? $dados['alternativas'] : [];
        $this->db->update(
            "INSERT INTO professor_questoes_api
                (professor_id, external_id, titulo, materia, assunto, tipo, nivel_dificuldade, origem, origem_referencia,
                 enunciado_html, alternativas_json, gabarito, resolucao_html, bncc, tags, topicos, source_payload, created_at, updated_at)
             VALUES
                (:professor_id, :external_id, :titulo, :materia, :assunto, :tipo, :nivel_dificuldade, :origem, :origem_referencia,
                 :enunciado_html, :alternativas_json, :gabarito, :resolucao_html, '', '', '', :source_payload, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                professor_id = VALUES(professor_id),
                titulo = VALUES(titulo),
                materia = VALUES(materia),
                assunto = VALUES(assunto),
                tipo = VALUES(tipo),
                nivel_dificuldade = VALUES(nivel_dificuldade),
                origem = VALUES(origem),
                origem_referencia = VALUES(origem_referencia),
                enunciado_html = VALUES(enunciado_html),
                alternativas_json = VALUES(alternativas_json),
                gabarito = VALUES(gabarito),
                resolucao_html = VALUES(resolucao_html),
                source_payload = VALUES(source_payload),
                updated_at = NOW()",
            [
                'professor_id' => (int) $dados['professor_id'],
                'external_id' => mb_substr((string) $dados['external_id'], 0, 80),
                'titulo' => mb_substr((string) $dados['titulo'], 0, 180),
                'materia' => mb_substr((string) $dados['materia'], 0, 120),
                'assunto' => mb_substr((string) $dados['assunto'], 0, 180),
                'tipo' => mb_substr((string) $dados['tipo'], 0, 120),
                'nivel_dificuldade' => $dados['nivel'],
                'origem' => mb_substr((string) $dados['origem'], 0, 40),
                'origem_referencia' => mb_substr((string) $dados['origem_referencia'], 0, 120),
                'enunciado_html' => (string) $dados['enunciado'],
                'alternativas_json' => $alternativas !== [] ? json_encode($alternativas, JSON_UNESCAPED_UNICODE) : null,
                'gabarito' => mb_substr((string) $dados['gabarito'], 0, 20),
                'resolucao_html' => (string) ($dados['resolucao'] ?? ''),
                'source_payload' => json_encode($dados['payload'] ?? [], JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    private function tituloDe(string $enunciado): string
    {
        $titulo = trim((string) preg_replace('/\s+/u', ' ', strip_tags($enunciado)));
        if ($titulo === '') {
            return 'Questão';
        }

        return mb_substr($titulo, 0, 100);
    }

    private function nivel(string $valor): ?string
    {
        $valor = strtolower(trim($valor));
        $valor = strtr($valor, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ã' => 'a', 'ê' => 'e']);

        return ['facil' => 'facil', 'medio' => 'medio', 'dificil' => 'dificil', 'desafio' => 'dificil'][$valor] ?? null;
    }

    private function bancoDisponivel(): bool
    {
        try {
            $row = $this->db->fetch(
                "SELECT COUNT(*) AS total
                   FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'professor_questoes_api'
                    AND COLUMN_NAME = 'professor_id'"
            );

            return (int) ($row['total'] ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
