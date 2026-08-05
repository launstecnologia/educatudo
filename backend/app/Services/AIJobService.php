<?php

namespace App\Services;

/**
 * Fila de jobs assíncronos para chamadas de IA.
 *
 * Uso típico no controller:
 *   $jobId = AIJobService::enqueue('gerar_exercicio', $payload, $userId, 'professor');
 *   echo json_encode(['job_id' => $jobId]);
 *
 * O cron process_ai_jobs.php chama AIJobService::processNext() a cada minuto.
 * O frontend faz polling em GET /ai-job/{id}/status até status === 'done'.
 */
class AIJobService
{
    private const MAX_ATTEMPTS = 3;
    private const STALE_MINUTES = 10;

    /**
     * Enfileira um novo job de IA e retorna o id gerado em ai_jobs.
     */
    /**
     * @param bool $dispararImediatamente Se false, não tenta disparar um worker em
     *   background nem processar inline nesta request — o job só é pego pelo cron
     *   (até 1 min de latência). Usar false em job_type de alto volume/escala (muitos
     *   usuários enfileirando ao mesmo tempo): disparar imediatamente hoje spawna um
     *   processo CLI novo por enqueue() que itera TODAS as escolas ativas do sistema
     *   (ver CronMultiTenantHelper::run com forceIterateTenants=true em
     *   cron/process_ai_jobs.php), não só a escola do usuário que enfileirou — é
     *   trabalho desperdiçado proporcional ao número de escolas, não ao de jobs reais.
     */
    public static function enqueue(
        string $jobType,
        array  $payload,
        ?int   $userId   = null,
        ?string $userRole = null,
        bool   $dispararImediatamente = true
    ): int {
        $db = \Database::getInstance();
        if (!$db->tableExists('ai_jobs')) {
            throw new \RuntimeException('Fila de IA (ai_jobs) não configurada. Execute a migration 2026_06_15_ai_jobs.sql.');
        }

        $jobId = (int) $db->insert(
            "INSERT INTO ai_jobs (job_type, payload, user_id, user_role) VALUES (?, ?, ?, ?)",
            [$jobType, json_encode($payload, JSON_UNESCAPED_UNICODE), $userId, $userRole]
        );

        if ($dispararImediatamente) {
            self::tryProcessImmediately();
        }

        return $jobId;
    }

    /**
     * Dispara o processamento da fila sem bloquear a requisição HTTP.
     *
     * Estratégia:
     *  1. Tenta disparar um worker em background (processo CLI separado), de forma
     *     que a chamada à IA (lenta) não estoure o timeout do PHP-FPM nem deixe o
     *     job preso em 'processing'.
     *  2. Se não for possível (exec desabilitado, binário não encontrado), faz o
     *     processamento inline protegido contra timeout/abort. O cron continua
     *     sendo a garantia final.
     */
    public static function tryProcessImmediately(): void
    {
        if (PHP_SAPI === 'cli') {
            // Em contexto CLI (o próprio cron) processa direto; o loop do cron cuida do resto.
            return;
        }

        if (self::spawnBackgroundWorker()) {
            return;
        }

        // Fallback inline: protege contra limites de tempo e desconexão do cliente.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        try {
            self::processNext();
        } catch (\Throwable $e) {
            if (class_exists('\Logger')) {
                \Logger::warning('AIJobService: processamento imediato falhou', [
                    'error' => $e->getMessage(),
                ], 'ai_jobs');
            }
        }
    }

    /**
     * Dispara o worker da fila em um processo CLI separado (não bloqueante).
     * Retorna true se conseguiu disparar.
     */
    private static function spawnBackgroundWorker(): bool
    {
        try {
            if (!function_exists('exec')) {
                return false;
            }

            $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
            if (in_array('exec', $disabled, true)) {
                return false;
            }

            if (stripos(PHP_OS, 'WIN') === 0) {
                return false; // estratégia abaixo é específica para *nix
            }

            $script = realpath(__DIR__ . '/../../cron/process_ai_jobs.php');
            if ($script === false) {
                return false;
            }

            $php = self::findPhpCliBinary();
            if ($php === null) {
                return false;
            }

            $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' > /dev/null 2>&1 &';
            exec($cmd);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Localiza o binário do PHP CLI. Em PHP-FPM, PHP_BINARY aponta para o fpm,
     * então é preciso procurar alternativas comuns.
     */
    private static function findPhpCliBinary(): ?string
    {
        $candidates = [];

        if (defined('PHP_BINARY') && PHP_BINARY !== '') {
            if (stripos(PHP_BINARY, 'fpm') === false) {
                $candidates[] = PHP_BINARY;
            } else {
                // Em PHP-FPM, PHP_BINARY aponta para o php-fpm. Deriva o CLI do MESMO
                // build/versao (mesmas extensoes habilitadas, ex.: mbstring) — evita cair
                // num /usr/bin/php generico do sistema que pode nao ter mbstring.
                $guess = str_replace(
                    ['/sbin/php-fpm', '/bin/php-fpm', 'php-fpm'],
                    ['/bin/php', '/bin/php', 'php'],
                    PHP_BINARY
                );
                if ($guess !== PHP_BINARY) {
                    $candidates[] = $guess;
                }
            }
        }
        if (defined('PHP_BINDIR') && PHP_BINDIR !== '') {
            $candidates[] = PHP_BINDIR . '/php';
        }
        $candidates[] = '/usr/bin/php';
        $candidates[] = '/usr/local/bin/php';

        foreach ($candidates as $candidate) {
            if ($candidate && @is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Retorna um job pelo id, ou null se não encontrado. */
    public static function getJob(int $id): ?array
    {
        $db = \Database::getInstance();
        return $db->fetch("SELECT * FROM ai_jobs WHERE id = ?", [$id]) ?: null;
    }

    /**
     * Processa o próximo job pendente da fila.
     * Retorna true se processou algum job, false se a fila estava vazia.
     */
    public static function processNext(): bool
    {
        $db = \Database::getInstance();

        // Pula silenciosamente se a migration ainda não foi executada neste tenant
        if (!$db->tableExists('ai_jobs')) {
            return false;
        }

        self::releaseStaleJobs($db);

        // SELECT ... FOR UPDATE SKIP LOCKED só retém o lock de linha enquanto durar a
        // transação — fora de uma transação explícita, dois workers concorrentes podem
        // pegar o mesmo job pending entre este SELECT e o UPDATE de status seguinte.
        // Sob volume baixo isso quase nunca aparece; em escala (muitos usuários
        // enfileirando ao mesmo tempo) a corrida vira real, daí o begin/commit abaixo.
        $db->beginTransaction();
        try {
            $job = $db->fetch(
                "SELECT * FROM ai_jobs
                 WHERE status = 'pending' AND attempts < ?
                 ORDER BY created_at ASC
                 LIMIT 1
                 FOR UPDATE SKIP LOCKED",
                [self::MAX_ATTEMPTS]
            );

            if (!$job) {
                $db->commit();
                return false;
            }

            $db->query(
                "UPDATE ai_jobs SET status = 'processing', started_at = NOW(), attempts = attempts + 1 WHERE id = ?",
                [$job['id']]
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        try {
            $payload = json_decode($job['payload'], true);
            $result  = self::dispatch($job['job_type'], $payload);

            $db->query(
                "UPDATE ai_jobs SET status = 'done', result = ?, completed_at = NOW() WHERE id = ?",
                [json_encode($result, JSON_UNESCAPED_UNICODE), $job['id']]
            );
        } catch (\Throwable $e) {
            // attempts já foi incrementado pelo UPDATE acima; usa +1 para refletir a tentativa atual
            $isFinal = (int) $job['attempts'] + 1 >= self::MAX_ATTEMPTS;
            $db->query(
                "UPDATE ai_jobs
                 SET status = ?, error_message = ?, completed_at = IF(?, NOW(), NULL)
                 WHERE id = ?",
                [
                    $isFinal ? 'failed' : 'pending',
                    $e->getMessage(),
                    $isFinal ? 1 : 0,
                    $job['id'],
                ]
            );

            if (class_exists('\Logger')) {
                \Logger::error('AIJobService: job falhou', [
                    'job_id'   => $job['id'],
                    'job_type' => $job['job_type'],
                    'attempt'  => $job['attempts'],
                    'error'    => $e->getMessage(),
                ], 'ai_jobs');
            }

            // Estorna créditos automaticamente quando o job é definitivamente descartado
            if ($isFinal) {
                $payload = $payload ?? json_decode($job['payload'], true);
                self::refundCredits($payload);
            }
        }

        return true;
    }

    /**
     * Remove jobs concluídos/falhos com mais de 7 dias.
     */
    public static function cleanup(): int
    {
        $db = \Database::getInstance();
        if (!$db->tableExists('ai_jobs')) {
            return 0;
        }
        $db->query(
            "DELETE FROM ai_jobs WHERE status IN ('done','failed') AND completed_at < NOW() - INTERVAL 7 DAY"
        );
        return 0;
    }

    /**
     * Processa imediatamente a reescrita EducaInclui de uma versão adaptada.
     * Usado por ações administrativas em que o humano quer ver o diff na hora.
     */
    public static function rewriteAssessmentVersionNow(array $payload): array
    {
        require_once __DIR__ . '/OpenAIService.php';
        return self::dispatchReescreverVersao($payload);
    }

    /**
     * Reprocessa imediatamente apenas uma questão da versão adaptada.
     */
    public static function rewriteAssessmentVersionQuestionNow(array $payload): array
    {
        require_once __DIR__ . '/OpenAIService.php';
        return self::dispatchReescreverQuestaoVersao($payload);
    }

    // -------------------------------------------------------------------------

    /**
     * Estorna créditos se o payload contiver credits_ref e credits_modulo.
     * Falha silenciosa — log de aviso, mas não interrompe o fluxo.
     */
    private static function refundCredits(array $payload): void
    {
        $ref    = $payload['credits_ref']    ?? null;
        $modulo = $payload['credits_modulo'] ?? null;

        if (!$ref || !$modulo) {
            return;
        }

        try {
            require_once __DIR__ . '/CreditosService.php';
            (new \App\Services\CreditosService())->estornarPorReferencia($modulo, $ref);

            if (class_exists('\Logger')) {
                \Logger::info('AIJobService: créditos estornados por job falho', [
                    'credits_ref'    => $ref,
                    'credits_modulo' => $modulo,
                ], 'ai_jobs');
            }
        } catch (\Throwable $ex) {
            if (class_exists('\Logger')) {
                \Logger::warning('AIJobService: falha ao estornar créditos', [
                    'credits_ref' => $ref,
                    'error'       => $ex->getMessage(),
                ], 'ai_jobs');
            }
        }
    }

    private static function releaseStaleJobs(\Database $db): void
    {
        $db->query(
            "UPDATE ai_jobs
             SET status = 'pending'
             WHERE status = 'processing'
               AND started_at < NOW() - INTERVAL ? MINUTE
               AND attempts < ?",
            [self::STALE_MINUTES, self::MAX_ATTEMPTS]
        );
    }

    private static function dispatch(string $jobType, array $payload): array
    {
        require_once __DIR__ . '/OpenAIService.php';
        require_once __DIR__ . '/EssayAIService.php';
        require_once __DIR__ . '/FlashcardService.php';

        switch ($jobType) {
            case 'gerar_exercicio':
                $svc = new OpenAIService();
                return $svc->gerarExerciciosJornadaIA(
                    $payload['contexto'],
                    $payload['tipo']       ?? 'múltipla_escolha',
                    $payload['quantidade'] ?? 5
                );

            case 'gerar_prova':
                $svc = new OpenAIService();
                return $svc->gerarProvaIA(
                    $payload['tema'],
                    $payload['materia'],
                    $payload['quantidade'],
                    $payload['nivel'],
                    $payload['contexto_adicional'] ?? '',
                    $payload['tipo']               ?? 'alternativas',
                    $payload['serie']              ?? '',
                    $payload['com_imagens']        ?? false
                );

            case 'corrigir_redacao':
                $svc = new \EssayAIService();
                return $svc->corrigir(
                    $payload['texto'],
                    $payload['tema'],
                    $payload['board_config'] ?? []
                );

            case 'gerar_flashcards':
                $svc = new \FlashcardService();
                return $svc->gerarFlashcardsIA(
                    $payload['conteudo'],
                    $payload['quantidade'] ?? 10
                );

            // Correção de redação configurável (banca+critérios) — salva resultado no banco
            case 'corrigir_essay':
                return self::dispatchCorrigirEssay($payload);

            // Correção de redação livre (ENEM) — salva resultado no banco
            case 'corrigir_essay_livre':
                return self::dispatchCorrigirEssayLivre($payload);

            // Geração de exercícios para módulo de jornada — salva no banco
            case 'gerar_exercicios_jornada':
                return self::dispatchGerarExerciciosJornada($payload);

            // Geração de flashcards — cria deck no banco e retorna deck_id
            case 'criar_flashcards':
                return self::dispatchCriarFlashcards($payload);

            // Comparação de resumo do aluno com resumo oficial — salva análise no banco
            case 'comparar_resumo':
                return self::dispatchCompararResumo($payload);

            // Correção de redação via jornada (tabela redacoes) — salva campos de competência no banco
            case 'corrigir_redacao_jornada':
                return self::dispatchCorrigirRedacaoJornada($payload);

            // Correção automática de redação do aluno (ENEM, prompt configurável via config_layout)
            case 'corrigir_redacao_aluno_auto':
                return self::dispatchCorrigirRedacaoAlunoAuto($payload);

            // Análise completa de desempenho do aluno pela IA Tudinha (admin) — salva análise no banco
            case 'gerar_analise_tudinha':
                return self::dispatchGerarAnaliseTudinha($payload);

            // EducaInclui — OCR do laudo + sugestão de máscara (humano no loop)
            case 'inclusao_analisar_laudo':
                return self::dispatchAnalisarLaudo($payload);

            // EducaInclui — reescrita de enunciados da versão adaptada (simplificar/literal)
            case 'inclusao_reescrever_versao':
                return self::dispatchReescreverVersao($payload);

            // Motor único de geração de questão por IA (Prova/Jornada/Exercício IA) —
            // pipeline de agentes em app/AI/Agentes/Questoes/. Não persiste na tabela
            // final do consumidor (só o consumidor sabe pra qual schema transformar);
            // devolve o formato canônico em ai_jobs.result.
            case 'gerar_questao_ia':
                return self::dispatchGerarQuestaoIA($payload);

            default:
                throw new \InvalidArgumentException("Tipo de job desconhecido: {$jobType}");
        }
    }

    // -------------------------------------------------------------------------
    // Handlers específicos que fazem AI + persistência no banco

    private static function dispatchCorrigirEssay(array $payload): array
    {
        $submissionId = (int) $payload['submission_id'];
        $db = \Database::getInstance();

        $submission = $db->fetch("SELECT * FROM redacoes_orientadas_entregas WHERE id = ?", [$submissionId]);
        if (!$submission) {
            throw new \RuntimeException("Submission {$submissionId} não encontrada.");
        }

        require_once __DIR__ . '/../Helpers/EssayTextStructureHelper.php';
        $text = $submission['content_text'] ?? $submission['ocr_text'] ?? '';
        $textStructure = \EssayTextStructureHelper::decode((string) ($submission['ocr_text_structure_json'] ?? ''));
        if (empty($textStructure)) {
            $textStructure = \EssayTextStructureHelper::buildFromPlainText((string) $text);
        }

        $proposal = $db->fetch("SELECT * FROM redacoes_orientadas_propostas WHERE id = ?", [$submission['proposal_id']]);
        if (!$proposal) {
            throw new \RuntimeException("Proposta não encontrada para submission {$submissionId}.");
        }

        $promptRow = $db->fetch(
            "SELECT * FROM redacoes_orientadas_prompts
             WHERE board_id = ? AND text_type_id = ? AND is_active = 1
             ORDER BY updated_at DESC LIMIT 1",
            [$proposal['board_id'], $proposal['text_type_id']]
        );
        if (!$promptRow) {
            throw new \RuntimeException('Nenhum prompt de correção ativo para esta banca e tipo textual.');
        }

        $criteria = $db->fetchAll(
            "SELECT * FROM redacoes_orientadas_criterios WHERE text_type_id = ? ORDER BY order_position ASC, id ASC",
            [$proposal['text_type_id']]
        );

        $result = (new \EssayAIService())->correctStructuredEssay(
            $textStructure,
            $criteria,
            (string) ($proposal['title'] ?? ''),
            (string) ($promptRow['prompt_text'] ?? '')
        );

        $gradesJson  = json_encode($result['grades_json'] ?? $result['grades'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $rawJson     = json_encode($result['raw_response'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $aiTotal     = $result['total_score'];
        $suggestions = $result['suggestions_text'] ?? '';

        $existing = $db->fetch("SELECT id FROM redacoes_orientadas_correcoes WHERE submission_id = ?", [$submissionId]);
        if ($existing) {
            $db->query(
                "UPDATE redacoes_orientadas_correcoes
                 SET prompt_id = ?, raw_response_json = ?, grades_json = ?, feedback_text = ?, suggestions_text = ?,
                     total_score = ?, ai_total_score = ?, corrected_by_teacher_id = NULL, teacher_adjusted_at = NULL, updated_at = NOW()
                 WHERE submission_id = ?",
                [$promptRow['id'], $rawJson, $gradesJson, $result['feedback_text'], $suggestions, $aiTotal, $aiTotal, $submissionId]
            );
            $correctionId = $existing['id'];
        } else {
            $correctionId = $db->insert(
                "INSERT INTO redacoes_orientadas_correcoes
                 (submission_id, prompt_id, raw_response_json, grades_json, feedback_text, suggestions_text, total_score, ai_total_score)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$submissionId, $promptRow['id'], $rawJson, $gradesJson, $result['feedback_text'], $suggestions, $aiTotal, $aiTotal]
            );
        }

        $db->query("UPDATE redacoes_orientadas_entregas SET status = 'corrected' WHERE id = ?", [$submissionId]);

        return [
            'correction_id'    => (int) $correctionId,
            'submission_id'    => $submissionId,
            'total_score'      => $aiTotal,
            'feedback_text'    => $result['feedback_text'],
            'suggestions_text' => $suggestions,
            'grades'           => $result['grades'],
        ];
    }

    private static function dispatchCorrigirEssayLivre(array $payload): array
    {
        $envioId = (int) $payload['envio_id'];
        $db = \Database::getInstance();

        $envio = $db->fetch("SELECT * FROM redacoes_livres_envios WHERE id = ?", [$envioId]);
        if (!$envio) {
            throw new \RuntimeException("Envio {$envioId} não encontrado.");
        }

        $text = !empty($envio['content_text']) ? $envio['content_text'] : ($envio['ocr_text'] ?? '');
        if (trim($text) === '') {
            throw new \RuntimeException('Não há texto para corrigir.');
        }

        $criteria   = $payload['criteria'] ?? [];
        $promptText = $payload['prompt_text'] ?? 'Corrija esta redação segundo as competências do ENEM. Atribua nota de 0 a 200 para cada uma das 5 competências e forneça feedback e sugestões de melhoria.';

        $result = (new \EssayAIService())->correctEssay($text, $promptText, $criteria);

        $db->query(
            "UPDATE redacoes_livres_correcoes
             SET grades_json = ?, feedback_text = ?, suggestions_text = ?, total_score = ?, updated_at = NOW()
             WHERE envio_id = ?",
            [
                json_encode($result['grades_json'] ?? $result['grades'], JSON_UNESCAPED_UNICODE),
                $result['feedback_text'] ?? '',
                $result['suggestions_text'] ?? '',
                $result['total_score'] ?? 0,
                $envioId,
            ]
        );

        return [
            'envio_id'         => $envioId,
            'total_score'      => $result['total_score'] ?? 0,
            'feedback_text'    => $result['feedback_text'] ?? '',
            'suggestions_text' => $result['suggestions_text'] ?? '',
            'grades'           => $result['grades'] ?? [],
        ];
    }

    private static function dispatchCriarFlashcards(array $payload): array
    {
        $svc = new \FlashcardService();

        if (($payload['mode'] ?? 'topic') === 'content') {
            $result = $svc->generateFromContent(
                (int) $payload['user_id'],
                $payload['content'],
                $payload['grade']    ?? '',
                (int) ($payload['quantity'] ?? 5)
            );
        } else {
            $result = $svc->generate(
                (int) $payload['user_id'],
                $payload['topic']    ?? '',
                $payload['grade']    ?? '',
                (int) ($payload['quantity'] ?? 5)
            );
        }

        if (empty($result['success'])) {
            throw new \RuntimeException($result['message'] ?? 'Falha ao gerar flashcards.');
        }

        return ['deck_id' => (int) $result['deck_id']];
    }

    /**
     * Motor único de geração de questão por IA. Roda o pipeline de agentes
     * (Planejador → Gerador → Validador → RecursoVisual condicional) e
     * devolve o formato canônico — quem chamou (Prova/Jornada/Exercício IA)
     * é quem persiste na tabela final, cada schema é diferente demais pra
     * unificar a persistência aqui.
     */
    private static function dispatchGerarQuestaoIA(array $payload): array
    {
        require_once __DIR__ . '/../AI/AgenteIAInterface.php';
        require_once __DIR__ . '/../AI/ContextoExecucao.php';
        require_once __DIR__ . '/../AI/ExecutorPipeline.php';
        require_once __DIR__ . '/../AI/Agentes/Questoes/PlanejadorQuestaoAgent.php';
        require_once __DIR__ . '/../AI/Agentes/Questoes/BuscadorReferenciaAgent.php';
        require_once __DIR__ . '/../AI/Agentes/Questoes/GeradorQuestaoAgent.php';
        require_once __DIR__ . '/../AI/Agentes/Questoes/ValidadorQuestaoAgent.php';
        require_once __DIR__ . '/../AI/Agentes/Questoes/RevisorQuestaoAgent.php';
        require_once __DIR__ . '/../AI/Agentes/Questoes/RevisorCriticoAgent.php';
        require_once __DIR__ . '/../AI/Agentes/Questoes/GeradorRecursoVisualAgent.php';

        $contexto = new \App\AI\ContextoExecucao();
        foreach ($payload as $chave => $valor) {
            $contexto->set($chave, $valor);
        }

        $contexto = \App\AI\ExecutorPipeline::executar([
            new \App\AI\Agentes\Questoes\PlanejadorQuestaoAgent(),
            new \App\AI\Agentes\Questoes\BuscadorReferenciaAgent(),
            new \App\AI\Agentes\Questoes\GeradorQuestaoAgent(),
            new \App\AI\Agentes\Questoes\ValidadorQuestaoAgent(),
            new \App\AI\Agentes\Questoes\RevisorQuestaoAgent(),
            new \App\AI\Agentes\Questoes\RevisorCriticoAgent(),
            new \App\AI\Agentes\Questoes\GeradorRecursoVisualAgent(),
        ], $contexto);

        return [
            'questoes' => $contexto->get('questoes_validadas', []),
            'questoes_descartadas' => $contexto->get('questoes_descartadas', 0),
        ];
    }

    private static function dispatchCompararResumo(array $payload): array
    {
        $db = \Database::getInstance();

        $analiseIA = (new OpenAIService())->compararResumos(
            $payload['resumo_oficial'] ?? '',
            $payload['resumo_texto'],
            $payload['materia'] ?? ''
        );

        $analiseJson = json_encode($analiseIA, JSON_UNESCAPED_UNICODE);
        $alunoId     = (int) $payload['aluno_id'];
        $aulaId      = (int) $payload['aula_id'];

        $existing = $db->fetch(
            "SELECT id FROM jornadas_resumos_alunos WHERE aluno_id = ? AND aula_id = ?",
            [$alunoId, $aulaId]
        );

        if ($existing) {
            $db->query(
                "UPDATE jornadas_resumos_alunos
                 SET resumo_texto = ?, analise_ia = ?, updated_at = NOW()
                 WHERE id = ?",
                [$payload['resumo_texto'], $analiseJson, $existing['id']]
            );
            $resumoId = $existing['id'];
        } else {
            $resumoId = $db->insert(
                "INSERT INTO jornadas_resumos_alunos (aluno_id, aula_id, resumo_texto, analise_ia, status)
                 VALUES (?, ?, ?, ?, 'concluido')",
                [$alunoId, $aulaId, $payload['resumo_texto'], $analiseJson]
            );
        }

        return ['resumo_id' => (int) $resumoId, 'analise' => $analiseIA];
    }

    private static function dispatchCorrigirRedacaoJornada(array $payload): array
    {
        $redacaoId = (int) $payload['redacao_id'];
        $db = \Database::getInstance();

        $redacao = $db->fetch("SELECT * FROM redacoes WHERE id = ?", [$redacaoId]);
        if (!$redacao) {
            throw new \RuntimeException("Redação {$redacaoId} não encontrada.");
        }

        $activePrompt = $db->fetch(
            "SELECT id, text_type_id, prompt_text FROM redacoes_orientadas_prompts
             WHERE is_active = 1 ORDER BY updated_at DESC, id DESC LIMIT 1"
        );
        if (!$activePrompt || empty(trim((string) ($activePrompt['prompt_text'] ?? '')))) {
            throw new \RuntimeException('Nenhum prompt ativo em Admin > Redação Configurável.');
        }

        $textTypeId = (int) ($activePrompt['text_type_id'] ?? 0);
        $criteria   = $textTypeId > 0
            ? $db->fetchAll("SELECT name, slug, max_score, description FROM redacoes_orientadas_criterios WHERE text_type_id = ? ORDER BY order_position ASC, id ASC", [$textTypeId])
            : [];

        $essayText = trim((string) ($redacao['conteudo'] ?? $redacao['texto'] ?? ''));
        if ($essayText === '') {
            throw new \RuntimeException('Redação sem conteúdo para correção.');
        }

        $executionId = 'jornada-redacao-' . $redacaoId . '-' . date('YmdHis');
        $promptForRun = trim((string) $activePrompt['prompt_text'])
            . "\n\n[INSTRUCAO INTERNA]\nEsta e uma NOVA geracao (execucao: {$executionId}). Reavalie do zero.";

        $result = (new \EssayAIService())->correctEssay($essayText, $promptForRun, $criteria);

        $gradesJson = $result['grades_json'] ?? [];
        if (!is_array($gradesJson) || empty($gradesJson)) {
            throw new \RuntimeException('A IA não retornou notas por competência.');
        }

        $gradesByPos = [];
        $i = 1;
        foreach ($gradesJson as $item) {
            if (!is_array($item)) { continue; }
            $gradesByPos[$i] = ['nota' => (int) ($item['score'] ?? 0), 'exp' => trim((string) ($item['feedback'] ?? ''))];
            if (++$i > 5) { break; }
        }
        for ($k = 1; $k <= 5; $k++) {
            if (!isset($gradesByPos[$k])) { $gradesByPos[$k] = ['nota' => 0, 'exp' => '']; }
        }

        $notaFinal      = isset($result['total_score']) ? (int) $result['total_score']
            : array_sum(array_column($gradesByPos, 'nota'));
        $comentarios    = trim((string) ($result['feedback_text'] ?? ''));
        $sugestoes      = trim((string) ($result['suggestions_text'] ?? ''))
            ?: 'Revise as competências com menor nota, reescrevendo parágrafos com foco em clareza.';

        $db->query(
            "UPDATE redacoes SET
                competencia_1 = ?, competencia_2 = ?, competencia_3 = ?, competencia_4 = ?, competencia_5 = ?,
                nota_final = ?,
                competencia_1_explicacao_professor = ?, competencia_2_explicacao_professor = ?,
                competencia_3_explicacao_professor = ?, competencia_4_explicacao_professor = ?,
                competencia_5_explicacao_professor = ?,
                comentarios_gerais_professor = ?, sugestoes_melhoria_professor = ?,
                feedback_ia = ?, corrigida_em = NOW()
             WHERE id = ?",
            [
                $gradesByPos[1]['nota'], $gradesByPos[2]['nota'], $gradesByPos[3]['nota'],
                $gradesByPos[4]['nota'], $gradesByPos[5]['nota'], $notaFinal,
                $gradesByPos[1]['exp'], $gradesByPos[2]['exp'], $gradesByPos[3]['exp'],
                $gradesByPos[4]['exp'], $gradesByPos[5]['exp'],
                $comentarios, $sugestoes,
                json_encode([
                    'prompt_id' => (int) $activePrompt['id'], 'text_type_id' => $textTypeId,
                    'execution_id' => $executionId, 'grades_json' => $gradesJson,
                    'total_score' => $result['total_score'] ?? null,
                    'feedback_text' => $comentarios, 'suggestions_text' => $sugestoes,
                    'raw_response' => $result['raw_response'] ?? null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $redacaoId,
            ]
        );

        return ['redacao_id' => $redacaoId, 'nota_final' => $notaFinal];
    }

    /**
     * Correção automática de redação do aluno com critérios ENEM (5 competências hardcoded).
     * Lógica relocada de StudentController::corrigirRedacaoAutomaticamente() sem alterações de negócio.
     */
    private static function dispatchCorrigirRedacaoAlunoAuto(array $payload): array
    {
        $redacaoId = (int) $payload['redacao_id'];
        $db = \Database::getInstance();

        // Buscar redação
        $redacao = $db->fetch(
            "SELECT r.* FROM redacoes r WHERE r.id = :redacao_id",
            ['redacao_id' => $redacaoId]
        );

        if (!$redacao) {
            throw new \RuntimeException('Redação não encontrada');
        }

        // Usar OpenAI para corrigir com critérios ENEM
        $openaiService = new OpenAIService();

        // Buscar prompt personalizado do banco de dados
        $promptConfig = $db->fetch(
            "SELECT config_value FROM config_layout WHERE config_key = ?",
            ['prompt_corrigir_redacao']
        );

        // Se o prompt não existir, usar o padrão
        if (!$promptConfig || empty($promptConfig['config_value'])) {
            $promptBase = "Você é um corretor especializado em redações do ENEM.

            Corrija a redação abaixo nos critérios e parâmetros oficiais do ENEM:

            COMPETÊNCIA I – Domínio da norma padrão da Língua Portuguesa
            COMPETÊNCIA II – Compreensão da proposta e desenvolvimento do tema
            COMPETÊNCIA III – Seleção e organização de argumentos
            COMPETÊNCIA IV – Coesão e coerência
            COMPETÊNCIA V – Proposta de intervenção

            REDAÇÃO:
            Título: {$redacao['titulo']}
            Conteúdo: {$redacao['conteudo']}

            Responda em formato JSON com as seguintes chaves:
            - competencia_1: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - competencia_2: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - competencia_3: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - competencia_4: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - competencia_5: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - nota_final: soma das 5 competências
            - comentarios_gerais: \"comentários gerais sobre a redação\"
            - sugestoes_melhoria: \"sugestões específicas para melhoria\"

            Seja detalhado e construtivo nas explicações.";
        } else {
            // Usar prompt personalizado e substituir variáveis
            $promptBase = $promptConfig['config_value'];
            $promptBase = str_replace('{titulo}', $redacao['titulo'], $promptBase);
            $promptBase = str_replace('{conteudo}', $redacao['conteudo'], $promptBase);
        }

        // Gerar correção
        $response = $openaiService->generateText($promptBase, [
            'max_tokens' => 4000,
            'temperature' => 0.4
        ]);

        // Tentar decodificar JSON
        $correcaoData = json_decode($response, true);

        if (!$correcaoData) {
            // Tentar extrair JSON da resposta se vier com texto adicional
            if (preg_match('/\{.*\}/s', $response, $matches)) {
                $correcaoData = json_decode($matches[0], true);
            }

            if (!$correcaoData) {
                error_log("Resposta da IA não é JSON válido. Resposta: " . substr($response, 0, 500));
                throw new \RuntimeException('Erro ao processar correção da IA: resposta não é JSON válido');
            }
        }

        // Validar estrutura mínima
        if (!isset($correcaoData['competencia_1']) || !isset($correcaoData['nota_final'])) {
            error_log("Estrutura de correção inválida. Dados: " . json_encode($correcaoData));
            throw new \RuntimeException('Estrutura de correção inválida da IA');
        }

        // Salvar correção no banco
        $comp1Nota = isset($correcaoData['competencia_1']['nota']) ? (int)$correcaoData['competencia_1']['nota'] : 0;
        $comp2Nota = isset($correcaoData['competencia_2']['nota']) ? (int)$correcaoData['competencia_2']['nota'] : 0;
        $comp3Nota = isset($correcaoData['competencia_3']['nota']) ? (int)$correcaoData['competencia_3']['nota'] : 0;
        $comp4Nota = isset($correcaoData['competencia_4']['nota']) ? (int)$correcaoData['competencia_4']['nota'] : 0;
        $comp5Nota = isset($correcaoData['competencia_5']['nota']) ? (int)$correcaoData['competencia_5']['nota'] : 0;
        $notaFinal = isset($correcaoData['nota_final']) ? (int)$correcaoData['nota_final'] : ($comp1Nota + $comp2Nota + $comp3Nota + $comp4Nota + $comp5Nota);

        $db->update(
            "UPDATE redacoes SET
                competencia_1 = :comp1_nota,
                competencia_2 = :comp2_nota,
                competencia_3 = :comp3_nota,
                competencia_4 = :comp4_nota,
                competencia_5 = :comp5_nota,
                nota_final = :nota_final,
                feedback_ia = :feedback,
                corrigida_em = NOW()
             WHERE id = :redacao_id",
            [
                'comp1_nota' => $comp1Nota,
                'comp2_nota' => $comp2Nota,
                'comp3_nota' => $comp3Nota,
                'comp4_nota' => $comp4Nota,
                'comp5_nota' => $comp5Nota,
                'nota_final' => $notaFinal,
                'feedback' => json_encode($correcaoData),
                'redacao_id' => $redacaoId
            ]
        );

        error_log("Correção da IA salva com sucesso para redação {$redacaoId}. Nota final: {$notaFinal}");

        return ['redacao_id' => $redacaoId, 'nota_final' => $notaFinal, 'correcao' => $correcaoData];
    }

    /**
     * Gera análise completa de desempenho do aluno pela IA Tudinha (uso administrativo).
     * Lógica relocada de AdminController::gerarAnaliseTudinha() sem alterações de negócio.
     */
    private static function dispatchGerarAnaliseTudinha(array $payload): array
    {
        $db = \Database::getInstance();

        $id      = (int) $payload['aluno_id'];
        $dataAte = $payload['data_ate'] ?? date('Y-m-d');
        $userId  = $payload['user_id'] ?? null;

        // Buscar aluno
        $aluno = $db->fetch(
            "SELECT a.*, t.nome as turma_nome FROM alunos a LEFT JOIN turmas t ON a.turma_id = t.id WHERE a.id = :id",
            ['id' => $id]
        );

        if (!$aluno) {
            throw new \RuntimeException('Aluno não encontrado');
        }

        // ========== BUSCAR TODOS OS DADOS DO PERÍODO ==========

        // Conversas
        $conversas = $db->fetchAll(
            "SELECT cc.*,
                    (SELECT COUNT(*) FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id) as total_mensagens
             FROM tudinha_conversas cc
             WHERE cc.aluno_id = :id AND cc.excluida = 0 AND DATE(cc.created_at) <= :data_ate
             ORDER BY cc.created_at DESC",
            ['id' => $id, 'data_ate' => $dataAte]
        );

        $mensagensChat = [];
        foreach ($conversas as $conv) {
            $msgs = $db->fetchAll(
                "SELECT mc.* FROM tudinha_mensagens mc WHERE mc.conversa_id = :conv_id ORDER BY mc.created_at ASC LIMIT 10",
                ['conv_id' => $conv['id']]
            );
            $mensagensChat = array_merge($mensagensChat, $msgs);
        }

        // Exercícios Banco de Dados
        $exerciciosBD = $db->fetchAll(
            "SELECT h.*, le.titulo, le.materia, h.percentual_acerto, h.questoes_corretas, h.questoes_total
             FROM exercicios_historico h
             INNER JOIN listas_exercicios le ON h.lista_id = le.id
             WHERE h.aluno_id = :id AND DATE(h.created_at) <= :data_ate
             ORDER BY h.created_at DESC",
            ['id' => $id, 'data_ate' => $dataAte]
        );

        // Exercícios IA
        $exerciciosIA = $db->fetchAll(
            "SELECT sep.*, lep.titulo as lista_titulo, lep.materia,
                    (SELECT COUNT(*) FROM listas_personalizadas_respostas rep WHERE rep.sessao_id = sep.id) as total_respostas,
                    (SELECT SUM(CASE WHEN rep.is_correct = 1 THEN 1 ELSE 0 END) FROM listas_personalizadas_respostas rep WHERE rep.sessao_id = sep.id) as acertos
             FROM listas_personalizadas_sessoes sep
             LEFT JOIN listas_personalizadas_exercicios lep ON sep.lista_id = lep.id
             WHERE sep.aluno_id = :id AND DATE(sep.started_at) <= :data_ate
             ORDER BY sep.started_at DESC",
            ['id' => $id, 'data_ate' => $dataAte]
        );

        // Redações
        $redacoes = $db->fetchAll(
            "SELECT r.*, r.nota, r.nota_final, r.correcao, r.feedback_ia, r.corrigida_em,
                    r.competencia_1, r.competencia_2, r.competencia_3, r.competencia_4, r.competencia_5,
                    r.comentarios_gerais, r.sugestoes_melhoria
             FROM redacoes r
             WHERE r.aluno_id = :id AND DATE(r.created_at) <= :data_ate
             ORDER BY r.created_at DESC",
            ['id' => $id, 'data_ate' => $dataAte]
        );

        // ========== PREPARAR DADOS PARA ANÁLISE ==========

        $dadosAnalise = [
            'aluno' => [
                'nome' => $aluno['nome'],
                'turma' => $aluno['turma_nome'] ?? 'Sem turma',
                'email' => $aluno['email'] ?? ''
            ],
            'periodo' => $dataAte,
            'conversas' => [
                'total' => count($conversas),
                'mensagens' => count($mensagensChat),
                'temas' => array_unique(array_filter(array_column($conversas, 'materia')))
            ],
            'exercicios_bd' => [
                'total' => count($exerciciosBD),
                'materias' => array_count_values(array_filter(array_column($exerciciosBD, 'materia'))),
                'media_geral' => count($exerciciosBD) > 0 ? array_sum(array_column($exerciciosBD, 'percentual_acerto')) / count($exerciciosBD) : 0,
                'detalhes' => array_map(function($ex) {
                    return [
                        'materia' => $ex['materia'] ?? 'N/A',
                        'percentual' => $ex['percentual_acerto'] ?? 0,
                        'questoes' => $ex['questoes_total'] ?? 0,
                        'acertos' => $ex['questoes_corretas'] ?? 0
                    ];
                }, $exerciciosBD)
            ],
            'exercicios_ia' => [
                'total' => count($exerciciosIA),
                'finalizados' => count(array_filter($exerciciosIA, fn($ex) => ($ex['status'] ?? '') === 'finalizado')),
                'media_acertos' => 0,
                'detalhes' => []
            ],
            'redacoes' => [
                'total' => count($redacoes),
                'corrigidas' => count(array_filter($redacoes, function($r) {
                    return $r['corrigida_em'] !== null ||
                           $r['correcao'] !== null ||
                           $r['feedback_ia'] !== null ||
                           $r['nota'] !== null ||
                           $r['nota_final'] !== null;
                })),
                'media_notas' => 0,
                'media_competencia_1' => 0,
                'media_competencia_2' => 0,
                'media_competencia_3' => 0,
                'media_competencia_4' => 0,
                'media_competencia_5' => 0,
                'tem_correcoes' => count(array_filter($redacoes, fn($r) => !empty($r['correcao']) || !empty($r['feedback_ia']))),
                'detalhes' => array_map(function($r) {
                    return [
                        'tema' => $r['tema'] ?? 'Sem tema',
                        'nota' => $r['nota'] ?? $r['nota_final'] ?? null,
                        'competencias' => [
                            'comp1' => $r['competencia_1'] ?? null,
                            'comp2' => $r['competencia_2'] ?? null,
                            'comp3' => $r['competencia_3'] ?? null,
                            'comp4' => $r['competencia_4'] ?? null,
                            'comp5' => $r['competencia_5'] ?? null
                        ],
                        'tem_correcao' => !empty($r['correcao']),
                        'tem_feedback' => !empty($r['feedback_ia'])
                    ];
                }, $redacoes)
            ]
        ];

        // Calcular média exercícios IA
        $acertosIA = 0;
        $totalIA = 0;
        foreach ($exerciciosIA as $ex) {
            $totalResp = $ex['total_respostas'] ?? 0;
            if ($totalResp > 0) {
                $acertosIA += $ex['acertos'] ?? 0;
                $totalIA += $totalResp;
            }
        }
        $dadosAnalise['exercicios_ia']['media_acertos'] = $totalIA > 0 ? ($acertosIA / $totalIA) * 100 : 0;

        // Calcular média redações
        $redacoesComNota = array_filter($redacoes, function($r) {
            return ($r['nota'] !== null) || ($r['nota_final'] !== null);
        });
        $notas = [];
        foreach ($redacoesComNota as $r) {
            if ($r['nota'] !== null) {
                $notas[] = $r['nota'];
            } elseif ($r['nota_final'] !== null) {
                $notas[] = $r['nota_final'];
            }
        }
        $dadosAnalise['redacoes']['media_notas'] = count($notas) > 0 ? (array_sum($notas) / count($notas)) : 0;

        // Calcular médias das competências
        $comp1 = array_filter(array_column($redacoes, 'competencia_1'), fn($v) => $v !== null);
        $comp2 = array_filter(array_column($redacoes, 'competencia_2'), fn($v) => $v !== null);
        $comp3 = array_filter(array_column($redacoes, 'competencia_3'), fn($v) => $v !== null);
        $comp4 = array_filter(array_column($redacoes, 'competencia_4'), fn($v) => $v !== null);
        $comp5 = array_filter(array_column($redacoes, 'competencia_5'), fn($v) => $v !== null);

        $dadosAnalise['redacoes']['media_competencia_1'] = count($comp1) > 0 ? (array_sum($comp1) / count($comp1)) : 0;
        $dadosAnalise['redacoes']['media_competencia_2'] = count($comp2) > 0 ? (array_sum($comp2) / count($comp2)) : 0;
        $dadosAnalise['redacoes']['media_competencia_3'] = count($comp3) > 0 ? (array_sum($comp3) / count($comp3)) : 0;
        $dadosAnalise['redacoes']['media_competencia_4'] = count($comp4) > 0 ? (array_sum($comp4) / count($comp4)) : 0;
        $dadosAnalise['redacoes']['media_competencia_5'] = count($comp5) > 0 ? (array_sum($comp5) / count($comp5)) : 0;

        // ========== CRIAR PROMPT PARA IA ==========

        $prompt = self::criarPromptAnaliseTudinha($dadosAnalise);

        // ========== CHAMAR OPENAI ==========

        $openaiService = new OpenAIService();
        $analiseRaw = $openaiService->generateText($prompt);

        // Tentar decodificar JSON
        $analiseData = json_decode($analiseRaw, true);
        if (!$analiseData || json_last_error() !== JSON_ERROR_NONE) {
            // Se não for JSON, usar como texto
            $analiseData = ['analise_completa' => $analiseRaw];
        }

        // Normalizar campos - garantir que sejam strings
        $dificuldades = null;
        $facilidades = null;
        $observacoes = null;
        $recomendacoes = null;

        if (is_array($analiseData)) {
            // Buscar dificuldades
            $dificuldades = $analiseData['dificuldades'] ?? $analiseData['Dificuldades'] ?? $analiseData['dificuldades_identificadas'] ?? null;
            if (is_array($dificuldades)) {
                $dificuldades = json_encode($dificuldades, JSON_UNESCAPED_UNICODE);
            } elseif ($dificuldades !== null) {
                $dificuldades = (string)$dificuldades;
            }

            // Buscar facilidades
            $facilidades = $analiseData['facilidades'] ?? $analiseData['Facilidades'] ?? $analiseData['facilidades_identificadas'] ?? null;
            if (is_array($facilidades)) {
                $facilidades = json_encode($facilidades, JSON_UNESCAPED_UNICODE);
            } elseif ($facilidades !== null) {
                $facilidades = (string)$facilidades;
            }

            // Buscar observações
            $observacoes = $analiseData['observacoes'] ?? $analiseData['Observacoes'] ?? $analiseData['observacoes_gerais'] ?? null;
            if (is_array($observacoes)) {
                $observacoes = json_encode($observacoes, JSON_UNESCAPED_UNICODE);
            } elseif ($observacoes !== null) {
                $observacoes = (string)$observacoes;
            }

            // Buscar recomendações
            $recomendacoes = $analiseData['recomendacoes'] ?? $analiseData['Recomendacoes'] ?? null;
            if (is_array($recomendacoes)) {
                $recomendacoes = json_encode($recomendacoes, JSON_UNESCAPED_UNICODE);
            } elseif ($recomendacoes !== null) {
                $recomendacoes = (string)$recomendacoes;
            }
        }

        // ========== SALVAR NO BANCO ==========

        $analiseId = $db->insert(
            "INSERT INTO tudinha_analises
             (aluno_id, data_ate, analise_completa, dificuldades, facilidades, observacoes, recomendacoes, created_by)
             VALUES (:aluno_id, :data_ate, :analise_completa, :dificuldades, :facilidades, :observacoes, :recomendacoes, :created_by)",
            [
                'aluno_id' => $id,
                'data_ate' => $dataAte,
                'analise_completa' => is_array($analiseData) ? json_encode($analiseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $analiseRaw,
                'dificuldades' => $dificuldades,
                'facilidades' => $facilidades,
                'observacoes' => $observacoes,
                'recomendacoes' => $recomendacoes,
                'created_by' => $userId
            ]
        );

        // Retornar análise normalizada para o frontend
        $analiseRetorno = [];
        if ($dificuldades) $analiseRetorno['dificuldades'] = $dificuldades;
        if ($facilidades) $analiseRetorno['facilidades'] = $facilidades;
        if ($observacoes) $analiseRetorno['observacoes'] = $observacoes;
        if ($recomendacoes) $analiseRetorno['recomendacoes'] = $recomendacoes;

        return [
            'analise' => !empty($analiseRetorno) ? $analiseRetorno : $analiseData,
            'analise_id' => (int) $analiseId,
        ];
    }

    /**
     * Cria prompt para análise do aluno pela IA Tudinha.
     */
    private static function criarPromptAnaliseTudinha(array $dados): string
    {
        $aluno = $dados['aluno'];
        $temas = !empty($dados['conversas']['temas']) ? implode(', ', $dados['conversas']['temas']) : 'Nenhuma matéria específica';

        $prompt = "Você é a Tudinha, uma assistente educacional especializada em análise de desempenho estudantil.
Analise os dados abaixo do aluno {$aluno['nome']} da turma {$aluno['turma']} até {$dados['periodo']}.

DADOS DO ALUNO:
- Nome: {$aluno['nome']}
- Turma: {$aluno['turma']}
- Email: {$aluno['email']}

ATIVIDADES REALIZADAS:

1. CONVERSAS COM TUDINHA:
   - Total de conversas: {$dados['conversas']['total']}
   - Total de mensagens: {$dados['conversas']['mensagens']}
   - Matérias abordadas: {$temas}

2. EXERCÍCIOS DO BANCO DE DADOS:
   - Total realizado: {$dados['exercicios_bd']['total']}
   - Média geral de acertos: " . number_format($dados['exercicios_bd']['media_geral'], 1) . "%
   - Distribuição por matéria: " . json_encode($dados['exercicios_bd']['materias'], JSON_UNESCAPED_UNICODE) . "

3. EXERCÍCIOS GERADOS POR IA:
   - Total de sessões: {$dados['exercicios_ia']['total']}
   - Finalizados: {$dados['exercicios_ia']['finalizados']}
   - Média de acertos: " . number_format($dados['exercicios_ia']['media_acertos'], 1) . "%

4. REDAÇÕES:
   - Total de redações: {$dados['redacoes']['total']}
   - Redações corrigidas: {$dados['redacoes']['corrigidas']}
   - Redações com correção/feedback: {$dados['redacoes']['tem_correcoes']}
   - Média de notas: " . number_format($dados['redacoes']['media_notas'], 1) . "
   - Média Competência 1 (Domínio da norma padrão): " . number_format($dados['redacoes']['media_competencia_1'], 1) . "/200
   - Média Competência 2 (Compreensão da proposta): " . number_format($dados['redacoes']['media_competencia_2'], 1) . "/200
   - Média Competência 3 (Seleção de argumentos): " . number_format($dados['redacoes']['media_competencia_3'], 1) . "/200
   - Média Competência 4 (Coesão e coerência): " . number_format($dados['redacoes']['media_competencia_4'], 1) . "/200
   - Média Competência 5 (Proposta de intervenção): " . number_format($dados['redacoes']['media_competencia_5'], 1) . "/200

REQUISITOS DA ANÁLISE:
Você deve criar uma análise completa e profissional que será compartilhada com coordenadores pedagógicos e pais/responsáveis.
A análise deve ser em formato JSON com as seguintes chaves:

{
  \"dificuldades\": \"Texto em parágrafo descrevendo as principais dificuldades identificadas, por matéria e tipo de atividade. Seja específico e construtivo. Use texto corrido, não lista ou objetos JSON.\",
  \"facilidades\": \"Texto em parágrafo descrevendo as áreas em que o aluno demonstra facilidade e bom desempenho. Destaque pontos fortes. Use texto corrido, não lista ou objetos JSON.\",
  \"observacoes\": \"Texto em parágrafo com observações gerais sobre o desempenho, engajamento, progressão e padrões identificados. Use texto corrido.\",
  \"recomendacoes\": \"Texto em parágrafo com recomendações específicas e acionáveis para professores, coordenadores e pais para apoiar o desenvolvimento do aluno. Use texto corrido, não lista ou objetos JSON.\"
}

IMPORTANTE:
- Os valores de cada campo devem ser TEXTO SIMPLES (strings), NÃO objetos JSON
- Não use chaves {}, colchetes [] ou aspas dentro dos valores
- Escreva parágrafos descritivos e profissionais
- Use linguagem adequada para coordenadores e pais
- Destaque progressos, identifique áreas de melhoria e forneça orientações práticas

Retorne APENAS o JSON válido, sem texto adicional ou markdown.";

        return $prompt;
    }

    /**
     * EducaInclui — extrai texto do laudo (OCR) e sugere uma Máscara de Acessibilidade.
     * A sugestão é apenas uma recomendação: a coordenação/AEE revisa, ajusta e aprova
     * (humano no loop). Não ativa nada automaticamente.
     */
    private static function dispatchAnalisarLaudo(array $payload): array
    {
        $mascaraId = (int) ($payload['mascara_id'] ?? 0);
        $documentId = (int) ($payload['document_id'] ?? 0);
        if ($mascaraId <= 0) {
            throw new \RuntimeException('mascara_id ausente.');
        }

        require_once __DIR__ . '/EducaIncluiService.php';
        require_once __DIR__ . '/../Models/EducaInclui/LaudoAluno.php';
        require_once __DIR__ . '/../Helpers/CatalogoRegraMascara.php';
        require_once __DIR__ . '/../Models/EducaInclui/VersaoAdaptadaLog.php';

        $docs = (new \LaudoAluno())->listarPorMascara($mascaraId);
        if (empty($docs)) {
            throw new \RuntimeException('Nenhum laudo enviado para analisar.');
        }

        // Escolhe o documento alvo (id informado) ou o mais recente (maior id).
        $doc = null;
        foreach ($docs as $d) {
            if ($documentId > 0 && (int) $d['id'] === $documentId) {
                $doc = $d;
                break;
            }
            if ($documentId <= 0 && ($doc === null || (int) $d['id'] > (int) $doc['id'])) {
                $doc = $d;
            }
        }
        if (!$doc) {
            throw new \RuntimeException('Laudo não encontrado.');
        }

        $mime = (string) ($doc['tipo_mime'] ?? '');
        $bytes = (new \EducaIncluiService())->decryptDocument($doc);
        if ($bytes === null || $bytes === '') {
            throw new \RuntimeException('Não foi possível ler o laudo (descriptografia).');
        }

        $openai = new OpenAIService();
        $ocrPrompt = 'Extraia todo o texto visível neste documento (laudo/relatório).';
        $ocr = '';

        if (strpos($mime, 'image/') === 0) {
            $ocr = trim((string) $openai->analyzeImage(base64_encode($bytes), $ocrPrompt));
        } elseif ($mime === 'application/pdf') {
            // 1) Tenta extrair o texto embutido (PDFs digitais com camada de texto).
            $ocr = trim(self::extractPdfTextNaive($bytes));
            // 2) Se veio pouco texto, tenta rasterizar via Imagick e fazer OCR por imagem (PDF escaneado).
            if (mb_strlen($ocr) < 40) {
                $imgOcr = self::ocrPdfViaImagick($bytes, $openai, $ocrPrompt);
                if (mb_strlen($imgOcr) > mb_strlen($ocr)) {
                    $ocr = $imgOcr;
                }
            }
            if (mb_strlen($ocr) < 40) {
                throw new \RuntimeException('Não foi possível extrair texto deste PDF automaticamente (provável documento escaneado sem OCR/Ghostscript no servidor). Envie uma imagem (foto/scan) do laudo.');
            }
        } else {
            throw new \RuntimeException('Formato não suportado para análise. Envie PDF, PNG ou JPG.');
        }

        if ($ocr === '') {
            throw new \RuntimeException('Não foi possível extrair texto do laudo.');
        }

        $prompt = self::buildLaudoSuggestionPrompt($ocr);
        $raw = $openai->generateText($prompt, ['max_tokens' => 1200, 'temperature' => 0.2]);

        $parsed = json_decode((string) $raw, true);
        if (!is_array($parsed) && preg_match('/\{.*\}/s', (string) $raw, $m)) {
            $parsed = json_decode($m[0], true);
        }
        if (!is_array($parsed)) {
            throw new \RuntimeException('A IA não retornou uma sugestão válida.');
        }

        $validKeys = \CatalogoRegraMascara::validKeys();
        $suggested = [];
        $rawRules = $parsed['suggested_rules'] ?? $parsed['regras'] ?? [];
        if (is_array($rawRules)) {
            foreach ($rawRules as $k => $v) {
                if (!in_array($k, $validKeys, true)) {
                    continue;
                }
                if (is_bool($v)) {
                    $suggested[$k] = $v ? '1' : '';
                } else {
                    $suggested[$k] = (string) $v;
                }
                if ($suggested[$k] === '' ) {
                    unset($suggested[$k]);
                }
            }
        }

        $justification = (string) ($parsed['justification'] ?? $parsed['justificativa'] ?? '');
        $confidence = (string) ($parsed['confidence'] ?? $parsed['confianca'] ?? '');

        (new \VersaoAdaptadaLog())->record('laudo_analisado', [
            'mascara_id' => $mascaraId,
            'aluno_id' => (int) ($payload['aluno_id'] ?? 0),
            'user_id' => (int) ($payload['user_id'] ?? 0),
            'details' => [
                'suggested_rules' => $suggested,
                'justification' => mb_substr($justification, 0, 2000),
                'confidence' => $confidence,
                'ocr_chars' => mb_strlen($ocr),
                'document_id' => (int) $doc['id'],
            ],
        ]);

        return [
            'mascara_id' => $mascaraId,
            'suggested_rules' => $suggested,
            'justification' => $justification,
            'confidence' => $confidence,
        ];
    }

    /**
     * EducaInclui — reescreve os enunciados da prova clonada (versão adaptada) com
     * linguagem simplificada e/ou literal, preservando HTML seguro, LaTeX e imagens.
     * A versão permanece 'pendente' até aprovação humana na fila.
     */
    private static function dispatchReescreverVersao(array $payload): array
    {
        $versionId = (int) ($payload['version_id'] ?? 0);
        $adaptedProvaId = (int) ($payload['adapted_prova_id'] ?? 0);
        if ($versionId <= 0 || $adaptedProvaId <= 0) {
            throw new \RuntimeException('version_id/adapted_prova_id ausente.');
        }

        require_once __DIR__ . '/../Models/Exams/Exam.php';
        require_once __DIR__ . '/../Models/EducaInclui/VersaoAdaptadaLog.php';

        $modes = is_array($payload['modes'] ?? null) ? $payload['modes'] : [];
        $simplify = !empty($modes['simplified_instructions']);
        $shorten = !empty($modes['shorten_statement']);
        $literal = !empty($modes['literal_language']);
        $keepOptions = (int) ($payload['reduce_options_keep'] ?? 0);
        if (!$simplify && !$shorten && !$literal) {
            $simplify = true;
        }

        $exam = new \Exam();
        $questoes = $exam->getQuestoes($adaptedProvaId);
        if (empty($questoes)) {
            throw new \RuntimeException('Versão sem questões para reescrever.');
        }

        $openai = new OpenAIService();
        $mapeamento = [];
        $reescritas = 0;

        foreach ($questoes as $q) {
            $original = (string) ($q['enunciado'] ?? '');
            $alternativas = null;
            if ($keepOptions >= 2 && ($q['tipo'] ?? '') === 'multipla_escolha') {
                $atuais = $exam->getAlternativas((int) ($q['id'] ?? 0));
                if (count($atuais) > $keepOptions) {
                    $alternativas = self::reduceAlternativas($atuais, $keepOptions, (int) ($payload['aluno_id'] ?? 0));
                }
            }

            $novo = '';
            if (trim(strip_tags($original)) !== '') {
                try {
                    $novo = self::generateRewrittenEnunciado($openai, $original, $simplify, $shorten, $literal);
                } catch (\Throwable $e) {
                    error_log('reescrever_versao questao ' . ($q['id'] ?? '?') . ': ' . $e->getMessage());
                }
            }
            $textoMudou = ($novo !== '' && $novo !== $original);

            if (!$textoMudou && $alternativas === null) {
                continue;
            }

            $dadosQuestao = [
                'enunciado' => $textoMudou ? $novo : $original,
                'imagem_url' => $q['imagem_url'] ?? null,
                'tipo' => $q['tipo'] ?? 'multipla_escolha',
                'valor' => $q['valor'] ?? 1.00,
                'nivel_dificuldade' => $q['nivel_dificuldade'] ?? null,
                'ordem' => $q['ordem'] ?? 0,
            ];
            if ($alternativas !== null) {
                $dadosQuestao['alternativas'] = $alternativas;
            }
            $exam->updateQuestao((int) $q['id'], $dadosQuestao);
            if ($textoMudou) {
                $reescritas++;
                $mapeamento[] = [
                    'clone_questao_id' => (int) $q['id'],
                    'clone_ordem' => (int) ($q['ordem'] ?? 0),
                    'antes' => $original,
                    'depois' => $novo,
                ];
            }
        }

        (new \VersaoAdaptadaLog())->record('versao_reescrita_ia', [
            'versao_adaptada_id' => $versionId,
            'mascara_id' => (int) ($payload['mascara_id'] ?? 0),
            'aluno_id' => (int) ($payload['aluno_id'] ?? 0),
            'prova_id' => (int) ($payload['prova_id'] ?? 0),
            'user_id' => (int) ($payload['aluno_id'] ?? 0),
            'details' => [
                'reescritas' => $reescritas,
                'modes' => ['simplified_instructions' => $simplify, 'shorten_statement' => $shorten, 'literal_language' => $literal],
                'reduce_options_keep' => $keepOptions,
                'mapa' => $mapeamento,
            ],
        ]);

        return ['version_id' => $versionId, 'reescritas' => $reescritas];
    }

    private static function dispatchReescreverQuestaoVersao(array $payload): array
    {
        $versionId = (int) ($payload['version_id'] ?? 0);
        $adaptedProvaId = (int) ($payload['adapted_prova_id'] ?? 0);
        $cloneQuestaoId = (int) ($payload['clone_questao_id'] ?? 0);
        if ($versionId <= 0 || $adaptedProvaId <= 0 || $cloneQuestaoId <= 0) {
            throw new \RuntimeException('version_id/adapted_prova_id/clone_questao_id ausente.');
        }

        require_once __DIR__ . '/../Models/Exams/Exam.php';
        require_once __DIR__ . '/../Models/EducaInclui/VersaoAdaptadaLog.php';

        $exam = new \Exam();
        $questao = $exam->getQuestaoById($cloneQuestaoId);
        if (!$questao || (int) ($questao['prova_id'] ?? 0) !== $adaptedProvaId) {
            throw new \RuntimeException('Questão não pertence à versão adaptada.');
        }

        $modes = is_array($payload['modes'] ?? null) ? $payload['modes'] : [];
        $simplify = !empty($modes['simplified_instructions']);
        $shorten = !empty($modes['shorten_statement']);
        $literal = !empty($modes['literal_language']);
        $keepOptions = (int) ($payload['reduce_options_keep'] ?? 0);
        if (!$simplify && !$shorten && !$literal) {
            $simplify = true;
        }

        $original = trim((string) ($payload['original_enunciado'] ?? ''));
        if ($original === '') {
            $original = (string) ($questao['enunciado'] ?? '');
        }
        if (trim(strip_tags($original)) === '') {
            throw new \RuntimeException('Questão sem enunciado para reescrever.');
        }

        $alternativasReduzidas = null;
        if ($keepOptions >= 2 && ($questao['tipo'] ?? '') === 'multipla_escolha') {
            $alternativasAtuais = $exam->getAlternativas($cloneQuestaoId);
            if (count($alternativasAtuais) > $keepOptions) {
                $alternativasReduzidas = self::reduceAlternativas(
                    $alternativasAtuais,
                    $keepOptions,
                    (int) ($payload['aluno_id'] ?? 0)
                );
            }
        }

        $novo = '';
        $erroIa = null;
        try {
            $novo = self::generateRewrittenEnunciado(new OpenAIService(), $original, $simplify, $shorten, $literal);
        } catch (\Throwable $e) {
            $erroIa = $e->getMessage();
        }
        if ($novo === '' && $alternativasReduzidas === null) {
            throw new \RuntimeException($erroIa ?: 'A IA não retornou um enunciado válido.');
        }

        $dadosQuestao = [
            'enunciado' => $novo !== '' ? $novo : (string) ($questao['enunciado'] ?? $original),
            'imagem_url' => $questao['imagem_url'] ?? null,
            'tipo' => $questao['tipo'] ?? 'multipla_escolha',
            'valor' => $questao['valor'] ?? 1.00,
            'nivel_dificuldade' => $questao['nivel_dificuldade'] ?? null,
            'ordem' => $questao['ordem'] ?? 0,
        ];
        if ($alternativasReduzidas !== null) {
            $dadosQuestao['alternativas'] = $alternativasReduzidas;
        }
        $exam->updateQuestao($cloneQuestaoId, $dadosQuestao);

        (new \VersaoAdaptadaLog())->record('versao_reescrita_ia_questao', [
            'versao_adaptada_id' => $versionId,
            'mascara_id' => (int) ($payload['mascara_id'] ?? 0),
            'aluno_id' => (int) ($payload['aluno_id'] ?? 0),
            'prova_id' => (int) ($payload['prova_id'] ?? 0),
            'user_id' => (int) ($payload['user_id'] ?? 0),
            'details' => [
                'clone_questao_id' => $cloneQuestaoId,
                'clone_ordem' => (int) ($questao['ordem'] ?? 0),
                'antes' => $original,
                'depois' => $novo !== '' ? $novo : (string) ($questao['enunciado'] ?? $original),
                'modes' => ['simplified_instructions' => $simplify, 'shorten_statement' => $shorten, 'literal_language' => $literal],
                'reduce_options_keep' => $keepOptions,
                'alternativas_reduzidas' => $alternativasReduzidas !== null ? count($alternativasReduzidas) : null,
                'erro_ia' => $erroIa,
            ],
        ]);

        return ['version_id' => $versionId, 'clone_questao_id' => $cloneQuestaoId];
    }

    private static function extractGeneratedEnunciado(string $text): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return '';
        }
        $json = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json) && isset($json['enunciado'])) {
            return trim((string) $json['enunciado']);
        }
        if (preg_match('/"enunciado"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/us', $trimmed, $matches)) {
            $decoded = json_decode('"' . $matches[1] . '"');
            return trim((string) ($decoded ?? $matches[1]));
        }
        // JSON malformado (quebras de linha/aspas internas não escapadas): captura tudo
        // entre "enunciado":" e o fechamento "} para não armazenar o invólucro JSON.
        if (preg_match('/"enunciado"\s*:\s*"([\s\S]*)"\s*\}\s*$/u', $trimmed, $matches)
            || preg_match('/"enunciado"\s*:\s*"([\s\S]*)$/u', $trimmed, $matches)) {
            $candidate = preg_replace('/"\s*\}?\s*$/u', '', $matches[1]);
            $candidate = strtr($candidate, ['\\"' => '"', '\\n' => "\n", '\\t' => "\t", '\\/' => '/', '\\\\' => '\\']);
            return trim($candidate);
        }
        return $trimmed;
    }

    private static function generateRewrittenEnunciado(OpenAIService $openai, string $original, bool $simplify, bool $shorten, bool $literal): string
    {
        $prompt = self::buildRewritePrompt($original, $simplify, $shorten, $literal);
        $novo = trim((string) $openai->generateText($prompt, ['max_tokens' => 1200, 'temperature' => 0.2]));
        $novo = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $novo);
        $novo = self::extractGeneratedEnunciado(trim((string) $novo));

        if ($shorten && $novo !== '' && self::plainLength($novo) >= (self::plainLength($original) * 0.9)) {
            $retryPrompt = self::buildRewritePrompt($original, $simplify, true, $literal)
                . "\n\nATENÇÃO: a versão anterior ficou longa demais. Reescreva novamente com no máximo "
                . max(180, (int) floor(self::plainLength($original) * 0.65))
                . " caracteres, mantendo somente o comando e os dados indispensáveis.";
            $retry = trim((string) $openai->generateText($retryPrompt, ['max_tokens' => 700, 'temperature' => 0.1]));
            $retry = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $retry);
            $retry = self::extractGeneratedEnunciado(trim((string) $retry));
            if ($retry !== '' && self::plainLength($retry) < self::plainLength($novo)) {
                $novo = $retry;
            }
        }

        return $novo;
    }

    private static function plainLength(string $text): int
    {
        return mb_strlen(trim(preg_replace('/\s+/', ' ', strip_tags($text))));
    }

    /**
     * @param list<array<string,mixed>> $alternativas
     * @return list<array<string,mixed>>
     */
    private static function reduceAlternativas(array $alternativas, int $keep, int $seed): array
    {
        $corretas = [];
        $distratores = [];
        foreach ($alternativas as $alt) {
            if (!empty($alt['correta'])) {
                $corretas[] = $alt;
            } else {
                $distratores[] = $alt;
            }
        }
        if (empty($corretas)) {
            return $alternativas;
        }

        $ordenarPor = static function (string $prefixo) use ($seed) {
            return static function ($a, $b) use ($prefixo, $seed) {
                return strcmp(
                    md5($prefixo . '-' . $seed . '-' . ($a['id'] ?? '')),
                    md5($prefixo . '-' . $seed . '-' . ($b['id'] ?? ''))
                );
            };
        };

        usort($distratores, $ordenarPor('pick'));
        $faltam = max(0, $keep - count($corretas));
        $selecionadas = array_merge($corretas, array_slice($distratores, 0, $faltam));
        usort($selecionadas, $ordenarPor('ord'));

        $ordem = 0;
        return array_map(static function ($alt) use (&$ordem) {
            return [
                'texto' => $alt['texto'] ?? '',
                'correta' => (int) ($alt['correta'] ?? 0),
                'ordem' => $ordem++,
            ];
        }, array_values($selecionadas));
    }

    /**
     * Prompt de reescrita de enunciado, preservando HTML/LaTeX/imagens e o sentido.
     */
    private static function buildRewritePrompt(string $enunciado, bool $simplify, bool $shorten, bool $literal): string
    {
        $objetivos = [];
        if ($simplify) {
            $objetivos[] = '- Use linguagem mais simples e direta, frases curtas, vocabulário acessível.';
        }
        if ($shorten) {
            $objetivos[] = '- Encurte o enunciado de forma perceptível: mire uma redução de 30% a 50% quando houver texto redundante, contextualização excessiva ou repetição, mantendo todos os dados necessários para resolver a questão.';
        }
        if ($literal) {
            $objetivos[] = '- Use linguagem literal: remova metáforas, ironia, ambiguidade e figuras de linguagem (apoio a TEA).';
        }
        $objetivosTxt = implode("\n", $objetivos);

        return <<<PROMPT
Você é um especialista em acessibilidade educacional (AEE). Reescreva o ENUNCIADO de uma questão de prova para um aluno com adaptação significativa.

OBJETIVOS:
{$objetivosTxt}

REGRAS OBRIGATÓRIAS:
- NÃO altere o sentido, os dados, números, fórmulas ou a resposta esperada da questão.
- Se encurtar, preserve informações, restrições, comandos e dados indispensáveis para chegar à mesma alternativa correta.
- Se a regra de encurtar estiver ativa, a nova versão deve ficar menor que a original sempre que isso for possível sem prejudicar a resolução.
- PRESERVE exatamente as tags HTML existentes (<p>, <br>, <strong>, <img>, etc.) e os blocos de LaTeX (\\(...\\), \$\$...\$\$). Não remova imagens.
- NÃO adicione explicações, comentários ou a resposta. Responda APENAS com o novo enunciado.
- Mantenha o mesmo idioma (português).

ENUNCIADO ORIGINAL:
{$enunciado}

NOVO ENUNCIADO:
PROMPT;
    }

    /**
     * Extração nativa (sem dependências) de texto de PDFs digitais.
     * Descomprime streams FlateDecode e captura operadores de texto (Tj/TJ).
     * Não funciona em PDFs escaneados (imagem) — nesse caso retorna string vazia/curta.
     */
    private static function extractPdfTextNaive(string $bytes): string
    {
        if (!function_exists('gzuncompress')) {
            return '';
        }
        $texto = '';
        // Captura todos os streams do PDF.
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $bytes, $streams)) {
            foreach ($streams[1] as $stream) {
                $data = @gzuncompress($stream);
                if ($data === false) {
                    // Stream pode não estar comprimido com Flate.
                    $data = $stream;
                }
                // Operadores de texto: (texto) Tj   e   [ (a) -250 (b) ] TJ
                if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)\s*Tj/', $data, $mTj)) {
                    foreach ($mTj[0] as $frag) {
                        $texto .= self::pdfUnescape($frag) . ' ';
                    }
                }
                if (preg_match_all('/\[(.*?)\]\s*TJ/s', $data, $mTJ)) {
                    foreach ($mTJ[1] as $arr) {
                        if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)/', $arr, $parts)) {
                            foreach ($parts[0] as $p) {
                                $texto .= self::pdfUnescape($p);
                            }
                            $texto .= ' ';
                        }
                    }
                }
            }
        }
        $texto = preg_replace('/[ \t]+/', ' ', $texto);
        return trim((string) $texto);
    }

    /** Remove parênteses e desfaz escapes básicos de uma string literal PDF. */
    private static function pdfUnescape(string $frag): string
    {
        $s = preg_replace('/\s*(Tj)$/', '', $frag);
        $s = trim($s);
        if ($s !== '' && $s[0] === '(') {
            $s = substr($s, 1);
        }
        if ($s !== '' && substr($s, -1) === ')') {
            $s = substr($s, 0, -1);
        }
        $map = ['\\n' => "\n", '\\r' => "\r", '\\t' => "\t", '\\(' => '(', '\\)' => ')', '\\\\' => '\\'];
        return strtr($s, $map);
    }

    /**
     * Rasteriza o PDF (1ª/2ª páginas) com Imagick + Ghostscript e roda OCR por imagem.
     * Retorna '' se Imagick/Ghostscript não estiverem disponíveis.
     */
    private static function ocrPdfViaImagick(string $bytes, OpenAIService $openai, string $prompt): string
    {
        if (!class_exists('Imagick')) {
            return '';
        }
        $tmp = tempnam(sys_get_temp_dir(), 'laudo_') . '.pdf';
        if ($tmp === false) {
            return '';
        }
        $ocr = '';
        try {
            file_put_contents($tmp, $bytes);
            $im = new \Imagick();
            $im->setResolution(200, 200);
            // Limita a 2 páginas para custo/tempo.
            $im->readImage($tmp . '[0-1]');
            foreach ($im as $page) {
                $page->setImageFormat('png');
                $page->setImageBackgroundColor('white');
                $png = $page->getImageBlob();
                $parcial = trim((string) $openai->analyzeImage(base64_encode($png), $prompt));
                if ($parcial !== '') {
                    $ocr .= $parcial . "\n";
                }
            }
            $im->clear();
            $im->destroy();
        } catch (\Throwable $e) {
            error_log('ocrPdfViaImagick: ' . $e->getMessage());
        } finally {
            @unlink($tmp);
        }
        return trim($ocr);
    }

    /**
     * Monta o prompt que mapeia o texto do laudo para as regras do catálogo.
     */
    private static function buildLaudoSuggestionPrompt(string $ocrText): string
    {
        $linhas = [];
        foreach (\CatalogoRegraMascara::rules() as $key => $def) {
            $tipo = $def['input'] ?? 'toggle';
            $valor = $tipo === 'toggle' ? 'true/false'
                : ($tipo === 'number' ? 'número inteiro'
                : ($tipo === 'text' ? 'texto curto'
                : ('uma de: ' . implode(', ', array_keys($def['options'] ?? [])))));
            $linhas[] = "- {$key} ({$valor}): " . ($def['label'] ?? '') . ' — ' . ($def['help'] ?? '');
        }
        $catalogo = implode("\n", $linhas);
        $ocrText = mb_substr($ocrText, 0, 6000);

        return <<<PROMPT
Você é um especialista em educação inclusiva (AEE). A partir do texto de um LAUDO/relatório
de um estudante, sugira uma "Máscara de Acessibilidade" para provas online, escolhendo
APENAS entre as regras disponíveis abaixo. Não invente regras. Em caso de dúvida, seja conservador.

REGRAS DISPONÍVEIS (chave (tipo de valor): descrição):
{$catalogo}

TEXTO DO LAUDO (pode conter ruído de OCR):
\"\"\"
{$ocrText}
\"\"\"

Responda APENAS com um JSON válido, sem markdown, no formato:
{
  "suggested_rules": { "chave_da_regra": valor, ... },
  "justification": "explicação curta, objetiva, ligando cada regra ao que o laudo indica (sem citar CID/diagnóstico)",
  "confidence": "alta|media|baixa"
}
Inclua em suggested_rules apenas as regras realmente indicadas pelo laudo.
PROMPT;
    }

    /**
     * Roda o motor único de geração de questão (mesmo usado por Prova Online/
     * Exercício IA — app/AI/Agentes/Questoes/) por baixo dos panos, mas
     * TRADUZ o resultado canônico de volta pro formato legado que
     * jornadas_exercicios.questoes_json sempre teve (titulo, enunciado,
     * alternativas{a..e} minúsculas, resposta_correta letra minúscula,
     * explicacao, dificuldade) — zero mudança de contrato pra quem já lê essa
     * tabela hoje (aprovação em TeacherJourneyController, exibição em
     * JourneyController). Ganho real: prompt único/validado em vez do
     * fallback hardcoded duplicado que existia antes.
     */
    private static function dispatchGerarExerciciosJornada(array $payload): array
    {
        $db = \Database::getInstance();

        require_once __DIR__ . '/../AI/AgenteIAInterface.php';
        require_once __DIR__ . '/../AI/ContextoExecucao.php';
        require_once __DIR__ . '/../AI/ExecutorPipeline.php';
        require_once __DIR__ . '/../AI/Agentes/Questoes/PlanejadorQuestaoAgent.php';
        require_once __DIR__ . '/../AI/Agentes/Questoes/GeradorQuestaoAgent.php';
        require_once __DIR__ . '/../AI/Agentes/Questoes/ValidadorQuestaoAgent.php';

        $tipoBruto = mb_strtolower((string) ($payload['tipo'] ?? ''), 'UTF-8');
        $tipoQuestao = strpos($tipoBruto, 'dissert') !== false
            ? 'dissertativa'
            : (strpos($tipoBruto, 'verdadeiro') !== false ? 'verdadeiro_falso' : 'multipla_escolha');

        $contexto = new \App\AI\ContextoExecucao();
        $contexto
            ->set('disciplina', $payload['materia'] ?? 'Geral')
            ->set('assunto', mb_substr((string) ($payload['contexto'] ?? ''), 0, 200, 'UTF-8'))
            ->set('dificuldade', 'medio')
            ->set('tipo_questao', $tipoQuestao)
            ->set('quantidade', $payload['quantidade'] ?? 5)
            ->set('quantidade_alternativas', 5)
            ->set('com_recurso_visual', false)
            ->set('contexto_adicional', $payload['contexto'] ?? '');

        $contexto = \App\AI\ExecutorPipeline::executar([
            new \App\AI\Agentes\Questoes\PlanejadorQuestaoAgent(),
            new \App\AI\Agentes\Questoes\GeradorQuestaoAgent(),
            new \App\AI\Agentes\Questoes\ValidadorQuestaoAgent(),
        ], $contexto);

        $letras = ['a', 'b', 'c', 'd', 'e'];
        $lista = [];
        foreach ($contexto->get('questoes_validadas', []) as $questao) {
            $alternativasLegado = [];
            $respostaCorreta = 'a';
            foreach (array_slice($questao['alternativas'] ?? [], 0, 5) as $i => $alt) {
                $letra = $letras[$i];
                $alternativasLegado[$letra] = $alt['texto'] ?? '';
                if (!empty($alt['correta'])) {
                    $respostaCorreta = $letra;
                }
            }
            $dificuldadeLegado = ['facil' => 'fácil', 'medio' => 'médio', 'dificil' => 'difícil', 'desafio' => 'difícil'][$questao['nivel_dificuldade'] ?? 'medio'] ?? 'médio';

            $lista[] = [
                'titulo' => mb_substr((string) ($questao['enunciado'] ?? ''), 0, 60, 'UTF-8'),
                'enunciado' => $questao['enunciado'] ?? '',
                'alternativas' => $alternativasLegado,
                'resposta_correta' => $respostaCorreta,
                'explicacao' => $questao['explicacao'] ?? '',
                'dificuldade' => $dificuldadeLegado,
            ];
        }

        $exercicioId = $db->insert(
            "INSERT INTO jornadas_exercicios
             (jornada_id, aula_id, titulo, descricao, tipo, questoes_json, status, aprovado_por)
             VALUES (?, ?, ?, ?, 'ia_gerado', ?, 'rascunho', NULL)",
            [
                (int) $payload['jornada_id'],
                isset($payload['aula_id']) ? (int) $payload['aula_id'] : null,
                $payload['titulo']   ?? ('Exercícios IA - ' . ($payload['materia'] ?? 'Geral')),
                $payload['descricao'] ?? ('Exercícios gerados automaticamente pela IA para ' . ($payload['materia'] ?? 'Geral')),
                json_encode($lista, JSON_UNESCAPED_UNICODE),
            ]
        );

        return [
            'exercicio_id' => (int) $exercicioId,
            'exercicios'   => $lista,
        ];
    }
}
