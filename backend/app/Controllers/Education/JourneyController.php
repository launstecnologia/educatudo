<?php
/**
 * EducaTudo - JornadaController
 * Gerencia o sistema completo de jornadas do aluno
 */

if (!class_exists('JourneyController')) {
class JourneyController extends BaseController
{
    private $authManager;
    private $db;

    /** @var array<string, bool> */
    private static $auditTableEnsured = [];
    
    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        
        // Verifica se está logado
        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
        }
    }

    /**
     * Retorna as colunas de JSON de estrutura que existem na tabela jornadas.
     * Pode ser: estrutura, descricao_estrutura ou descricao estrutura (com espaço).
     * Usa INFORMATION_SCHEMA para não disparar erro quando uma coluna não existir.
     */
    private function getEstruturaColumnNames()
    {
        static $cols = null;
        if ($cols !== null) {
            return $cols;
        }
        $cols = ['estrutura'];
        try {
            $rows = $this->db->fetchAll(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jornadas' 
                 AND (COLUMN_NAME = 'estrutura' OR COLUMN_NAME = 'descricao_estrutura' OR COLUMN_NAME = 'descricao estrutura')
                 ORDER BY COLUMN_NAME"
            );
            if (!empty($rows)) {
                $cols = array_column($rows, 'COLUMN_NAME');
            }
        } catch (\Throwable $e) {
        }
        return $cols;
    }

    /**
     * Nome da coluna de texto em jornadas_duvidas (duvida ou duvida_texto, conforme o tenant).
     */
    private function getJornadasDuvidaColumnName(): string
    {
        static $cache = [];
        $scope = defined('TENANT_SLUG') ? (string) TENANT_SLUG : 'default';
        if (isset($cache[$scope])) {
            return $cache[$scope];
        }

        $cache[$scope] = 'duvida';
        try {
            $rows = $this->db->fetchAll(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jornadas_duvidas'
                   AND COLUMN_NAME IN ('duvida', 'duvida_texto')
                 ORDER BY CASE COLUMN_NAME WHEN 'duvida' THEN 0 ELSE 1 END
                 LIMIT 1"
            );
            if (!empty($rows[0]['COLUMN_NAME'])) {
                $name = (string) $rows[0]['COLUMN_NAME'];
                if ($name === 'duvida' || $name === 'duvida_texto') {
                    $cache[$scope] = $name;
                }
            }
        } catch (\Throwable $e) {
        }

        return $cache[$scope];
    }

    /**
     * Expressão SQL da coluna de dúvida (sempre expõe alias duvida para a view).
     */
    private function sqlJornadasDuvidaColunaSelect(string $alias = ''): string
    {
        $col = $this->getJornadasDuvidaColumnName();
        $ref = $alias !== '' ? $alias . '.' . $col : $col;

        return $col === 'duvida_texto' ? "{$ref} AS duvida" : $ref;
    }

    /**
     * Referência SQL à coluna de estrutura (com backticks se tiver espaço no nome).
     */
    private function quoteEstruturaColumn($columnName)
    {
        return strpos($columnName, ' ') !== false
            ? 'j.`' . str_replace('`', '``', $columnName) . '`'
            : 'j.' . $columnName;
    }

    private function getJourneyListCacheKey(int $alunoId, string $turmaToken): string
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'tenant'));
        return 'journeys:index:v6:' . md5($host . '|' . $alunoId . '|' . $turmaToken);
    }

    private function ensureRedisCacheAvailable(): bool
    {
        if (class_exists('RedisCache')) {
            return true;
        }
        $cachePath = __DIR__ . '/../../Core/RedisCache.php';
        if (is_file($cachePath)) {
            require_once $cachePath;
        }
        return class_exists('RedisCache');
    }

    private function getCachedJourneyList(int $alunoId, string $turmaToken): ?array
    {
        if (!$this->ensureRedisCacheAvailable()) {
            return null;
        }
        $cached = RedisCache::get($this->getJourneyListCacheKey($alunoId, $turmaToken));
        if ($cached === null || $cached === '') {
            return null;
        }

        $decoded = json_decode($cached, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function setCachedJourneyList(int $alunoId, string $turmaToken, array $jornadas): void
    {
        if (!$this->ensureRedisCacheAvailable()) {
            return;
        }
        RedisCache::set(
            $this->getJourneyListCacheKey($alunoId, $turmaToken),
            json_encode($jornadas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            45
        );
    }

    /**
     * Lista em Redis pode ficar desatualizada após nova resposta (acertos/erros).
     */
    private function invalidateJourneyListCacheForAluno(int $alunoId): void
    {
        if (!$this->ensureRedisCacheAvailable()) {
            return;
        }
        if (!class_exists('AlunoTurmaHelper')) {
            require_once __DIR__ . '/../../Core/AlunoTurmaHelper.php';
        }
        $turmaIds = AlunoTurmaHelper::getTurmaIds($this->db, $alunoId);
        $turmaToken = AlunoTurmaHelper::getTurmaIdsCacheToken($turmaIds);
        RedisCache::delete($this->getJourneyListCacheKey($alunoId, $turmaToken));
    }

    /**
     * Mesma lógica de gabarito que responderExercicioModulo / tela da questão.
     *
     * @param mixed $questoesJson string JSON ou array já decodificado
     * @return bool|null true/false se deu para avaliar; null se não for múltipla escolha interpretável
     */
    private function avaliarAlternativasCorretoPorQuestoesJson($questoesJson, string $respostaJsonEncoded): ?bool
    {
        $questoes = is_string($questoesJson) ? json_decode($questoesJson, true) : $questoesJson;
        if (is_string($questoes)) {
            $questoes = json_decode($questoes, true);
        }
        if (!is_array($questoes)) {
            return null;
        }
        $opcoes = null;
        if (isset($questoes['opcoes']) && is_array($questoes['opcoes'])) {
            $opcoes = $questoes['opcoes'];
        } elseif (isset($questoes[0]) && is_array($questoes[0]) && isset($questoes[0]['letra'])) {
            $opcoes = $questoes;
        }
        if (!$opcoes) {
            return null;
        }
        $decodedResp = json_decode($respostaJsonEncoded, true);
        $resposta = '';
        if (is_array($decodedResp) && array_key_exists('resposta', $decodedResp)) {
            $resposta = (string) $decodedResp['resposta'];
        } elseif ($respostaJsonEncoded !== '') {
            $resposta = $respostaJsonEncoded;
        }
        $respostaNorm = strtoupper(trim($resposta));
        if ($respostaNorm === '') {
            return null;
        }
        foreach ($opcoes as $opcao) {
            $letraNorm = strtoupper(trim((string) ($opcao['letra'] ?? '')));
            $ehCorreta = !empty($opcao['correta']);
            if ($letraNorm !== '' && $letraNorm === $respostaNorm && $ehCorreta) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recalcula questões respondidas/acertos por jornada alinhando ao gabarito (não só pontuacao > 0),
     * para corrigir histórico em que acerto gravou peso 0 e o card mostrava 100% erro.
     *
     * @param list<array<string,mixed>> $jornadas
     * @return list<array<string,mixed>>
     */
    private function recalcularQuestoesRespondidasAcertosPorGabarito(int $alunoId, array $jornadas): array
    {
        if ($jornadas === []) {
            return $jornadas;
        }
        $jornadaIds = [];
        foreach ($jornadas as $j) {
            $jid = (int) ($j['id'] ?? 0);
            if ($jid > 0) {
                $jornadaIds[$jid] = true;
            }
        }
        if ($jornadaIds === []) {
            return $jornadas;
        }
        $placeholders = implode(',', array_fill(0, count($jornadaIds), '?'));
        $jornadaIdList = array_keys($jornadaIds);
        $paramsBase = array_merge([$alunoId], $jornadaIdList);

        $rowsModulo = $this->db->fetchAll(
            "SELECT jpa.id, jpa.jornada_id, jpa.atividade_tipo, jpa.exercicio_modulo_id, jpa.exercicio_id,
                    jpa.pontuacao, jpa.resposta,
                    me.tipo AS tipo_me, me.questoes_json AS qjson_me,
                    NULL AS qjson_legacy
             FROM jornadas_progresso_alunos jpa
             LEFT JOIN jornadas_modulos_exercicios me ON me.id = jpa.exercicio_modulo_id
             WHERE jpa.aluno_id = ?
               AND jpa.jornada_id IN ({$placeholders})
               AND jpa.atividade_tipo = 'exercicio_modulo'
               AND jpa.exercicio_modulo_id IS NOT NULL
               AND jpa.resposta IS NOT NULL
               AND TRIM(jpa.resposta) <> ''
             ORDER BY jpa.id DESC",
            $paramsBase
        ) ?: [];

        $rowsLegacy = $this->db->fetchAll(
            "SELECT jpa.id, jpa.jornada_id, jpa.atividade_tipo, jpa.exercicio_modulo_id, jpa.exercicio_id,
                    jpa.pontuacao, jpa.resposta,
                    NULL AS tipo_me, NULL AS qjson_me,
                    je.questoes_json AS qjson_legacy
             FROM jornadas_progresso_alunos jpa
             LEFT JOIN jornadas_exercicios je ON je.id = jpa.exercicio_id
             WHERE jpa.aluno_id = ?
               AND jpa.jornada_id IN ({$placeholders})
               AND jpa.atividade_tipo = 'exercicio'
               AND jpa.exercicio_id IS NOT NULL
               AND jpa.resposta IS NOT NULL
               AND TRIM(jpa.resposta) <> ''
             ORDER BY jpa.id DESC",
            $paramsBase
        ) ?: [];

        $rows = array_merge($rowsModulo, $rowsLegacy);
        usort($rows, static fn ($a, $b) => ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0)));

        $seen = [];
        $stats = [];
        foreach ($rows as $row) {
            $jid = (int) ($row['jornada_id'] ?? 0);
            if ($jid <= 0) {
                continue;
            }
            $tipoAt = (string) ($row['atividade_tipo'] ?? '');
            $ref = $tipoAt === 'exercicio_modulo'
                ? ('m' . (int) ($row['exercicio_modulo_id'] ?? 0))
                : ('e' . (int) ($row['exercicio_id'] ?? 0));
            $k = $jid . ':' . $ref;
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;

            $p = (float) ($row['pontuacao'] ?? 0);
            $respostaEnc = (string) ($row['resposta'] ?? '');
            $tipoMe = (string) ($row['tipo_me'] ?? '');
            $qjson = $row['qjson_me'] ?? null;
            if ($tipoAt === 'exercicio') {
                $qjson = $row['qjson_legacy'] ?? null;
            }

            $gabarito = null;
            if ($tipoAt === 'exercicio_modulo' && $tipoMe === 'alternativas') {
                $gabarito = $this->avaliarAlternativasCorretoPorQuestoesJson($qjson, $respostaEnc);
            } elseif ($tipoAt === 'exercicio' && $qjson) {
                $gabarito = $this->avaliarAlternativasCorretoPorQuestoesJson($qjson, $respostaEnc);
            }

            $acerto = $p > 0;
            if ($gabarito !== null) {
                $acerto = ($gabarito === true);
            }

            if (!isset($stats[$jid])) {
                $stats[$jid] = ['resp' => 0, 'acertos' => 0];
            }
            $stats[$jid]['resp']++;
            if ($acerto) {
                $stats[$jid]['acertos']++;
            }
        }

        foreach ($jornadas as &$j) {
            $jid = (int) ($j['id'] ?? 0);
            if ($jid > 0 && isset($stats[$jid])) {
                $j['questoes_respondidas'] = $stats[$jid]['resp'];
                $j['questoes_acertos'] = $stats[$jid]['acertos'];
            }
        }
        unset($j);

        return $jornadas;
    }

    private function normalizeJourneyEstrutura(array $jornadas): array
    {
        foreach ($jornadas as &$j) {
            if (trim((string) ($j['estrutura'] ?? '')) === '') {
                $j['estrutura'] = $j['descricao_estrutura'] ?? $j['descricao estrutura'] ?? '';
            }
        }
        unset($j);

        return $jornadas;
    }

    private function finalizeJourneyList(array $jornadas, array $aluno, ?array $turmaIds = null, bool $skipTurmaFilter = false): array
    {
        $alunoId = (int) ($aluno['id'] ?? 0);
        if (!$skipTurmaFilter) {
            if ($turmaIds === null) {
                $turmaIds = $this->getTurmaIdsAluno($alunoId);
            }
            $jornadas = array_values(array_filter($jornadas, function ($j) use ($alunoId, $turmaIds) {
                return $this->jornadaPermiteAluno($j, $alunoId, $turmaIds);
            }));
        }

        if (!class_exists('JornadaStatusHelper')) {
            require_once __DIR__ . '/../../Core/JornadaStatusHelper.php';
        }

        // Em listagem do aluno, não persistir status em massa para evitar write amplification no banco.
        foreach ($jornadas as &$j) {
            if (!empty($j['estrutura'])) {
                $j['status_jornada'] = JornadaStatusHelper::recalcularSemPersistir((string) $j['estrutura']);
            }
        }
        unset($j);

        $agoraTs = time();
        foreach ($jornadas as &$jornada) {
            $totalMod = (int) ($jornada['total_modulos'] ?? 0);
            $concluidosMod = (int) ($jornada['modulos_concluidos'] ?? 0);
            $jornada['jornada_concluida'] = ($totalMod > 0 && $concluidosMod >= $totalMod);

            $jornada['percentual_progresso'] = (int) ($jornada['total_aulas'] ?? 0) > 0
                ? round((((int) $jornada['aulas_concluidas']) / ((int) $jornada['total_aulas'])) * 100, 2)
                : 0;

            if (!empty($jornada['estrutura'])) {
                $estrutura = json_decode($jornada['estrutura'], true);
                if (is_array($estrutura)) {
                    if (isset($estrutura['data_inicio'])) {
                        $jornada['data_inicio'] = $estrutura['data_inicio'];
                    }
                    if (isset($estrutura['hora_inicio'])) {
                        $jornada['hora_inicio'] = $estrutura['hora_inicio'];
                    }
                    if (isset($estrutura['data_fim'])) {
                        $jornada['data_fim'] = $estrutura['data_fim'];
                    }
                    if (isset($estrutura['hora_fim'])) {
                        $jornada['hora_fim'] = $estrutura['hora_fim'];
                    }
                }
            }

            if (!empty($jornada['jornada_concluida'])) {
                $jornada['status_exibicao'] = 'concluido';
            } elseif (!empty($jornada['data_inicio']) && !empty($jornada['data_fim'])) {
                $horaInicio = trim((string) ($jornada['hora_inicio'] ?? '')) ?: '00:00';
                $horaFim = trim((string) ($jornada['hora_fim'] ?? '')) ?: '23:59:59';
                $tsInicio = strtotime($jornada['data_inicio'] . ' ' . $horaInicio);
                $tsFim = strtotime($jornada['data_fim'] . ' ' . $horaFim);
                if ($tsInicio !== false && $tsFim !== false) {
                    if ($agoraTs > $tsFim) {
                        $jornada['status_exibicao'] = 'expirado';
                    } elseif ($agoraTs < $tsInicio) {
                        $jornada['status_exibicao'] = 'aguardando';
                    } else {
                        $jornada['status_exibicao'] = 'em_andamento';
                    }
                } else {
                    $jornada['status_exibicao'] = 'em_andamento';
                }
            } else {
                $jornada['status_exibicao'] = 'em_andamento';
            }

            $qt = (int) ($jornada['questoes_total'] ?? 0);
            $resp = (int) ($jornada['questoes_respondidas'] ?? 0);
            $ac = (int) ($jornada['questoes_acertos'] ?? 0);
            $er = max(0, $resp - $ac);
            $jornada['questoes_erros'] = $er;
            $jornada['questoes_nao_respondidas'] = max(0, $qt - $resp);
            $jornada['questoes_pct_acertos_total'] = $qt > 0 ? round(($ac / $qt) * 100, 1) : null;
            $jornada['questoes_pct_erros_total'] = $qt > 0 ? round(($er / $qt) * 100, 1) : null;
            $jornada['questoes_pct_acertos_respondidas'] = $resp > 0 ? round(($ac / $resp) * 100, 1) : null;
            $jornada['questoes_pct_erros_respondidas'] = $resp > 0 ? round(($er / $resp) * 100, 1) : null;
        }
        unset($jornada);

        return $jornadas;
    }

    /**
     * Resumo para o dashboard do aluno em "Minhas Jornadas".
     *
     * @param list<array<string,mixed>> $jornadas
     * @return array{
     *   total:int,
     *   concluidas:int,
     *   nao_feitas_esperadas:int,
     *   jornadas_em_andamento:int,
     *   jornadas_expiradas:int,
     *   jornadas_pct_concluidas:float|null,
     *   jornadas_pct_em_andamento:float|null,
     *   jornadas_pct_expiradas:float|null,
     *   questoes_total:int,
     *   questoes_respondidas:int,
     *   questoes_acertos:int,
     *   questoes_erros:int,
     *   questoes_nao_respondidas:int,
     *   questoes_pct_respondidas:float|null,
     *   questoes_pct_acertos_respondidas:float|null,
     *   questoes_pct_acertos_total:float|null
     * }
     */
    private function buildJornadasAlunoDashboardStats(array $jornadas): array
    {
        $total = count($jornadas);
        $concluidas = 0;
        $naoFeitasEsperadas = 0;
        $jornadasEmAndamento = 0;
        $jornadasExpiradas = 0;
        $questoesTotal = 0;
        $questoesRespondidas = 0;
        $questoesAcertos = 0;
        foreach ($jornadas as $j) {
            $st = (string) ($j['status_exibicao'] ?? '');
            if (!empty($j['jornada_concluida']) || $st === 'concluido') {
                $concluidas++;
            } elseif ($st === 'em_andamento') {
                $jornadasEmAndamento++;
                $naoFeitasEsperadas++;
            } elseif ($st === 'expirado') {
                $jornadasExpiradas++;
                $naoFeitasEsperadas++;
            }
            $questoesTotal += (int) ($j['questoes_total'] ?? 0);
            $questoesRespondidas += (int) ($j['questoes_respondidas'] ?? 0);
            $questoesAcertos += (int) ($j['questoes_acertos'] ?? 0);
        }
        $questoesErros = max(0, $questoesRespondidas - $questoesAcertos);
        $questoesNaoRespondidas = max(0, $questoesTotal - $questoesRespondidas);
        $pctJornConcl = $total > 0 ? round(($concluidas / $total) * 100, 1) : null;
        $pctJornEm = $total > 0 ? round(($jornadasEmAndamento / $total) * 100, 1) : null;
        $pctJornExp = $total > 0 ? round(($jornadasExpiradas / $total) * 100, 1) : null;
        $pctQuestResp = $questoesTotal > 0
            ? round(($questoesRespondidas / $questoesTotal) * 100, 1)
            : null;
        $pctAcertosResp = $questoesRespondidas > 0
            ? round(($questoesAcertos / $questoesRespondidas) * 100, 1)
            : null;
        $pctAcertosTotal = $questoesTotal > 0
            ? round(($questoesAcertos / $questoesTotal) * 100, 1)
            : null;

        return [
            'total' => $total,
            'concluidas' => $concluidas,
            'nao_feitas_esperadas' => $naoFeitasEsperadas,
            'jornadas_em_andamento' => $jornadasEmAndamento,
            'jornadas_expiradas' => $jornadasExpiradas,
            'jornadas_pct_concluidas' => $pctJornConcl,
            'jornadas_pct_em_andamento' => $pctJornEm,
            'jornadas_pct_expiradas' => $pctJornExp,
            'questoes_total' => $questoesTotal,
            'questoes_respondidas' => $questoesRespondidas,
            'questoes_acertos' => $questoesAcertos,
            'questoes_erros' => $questoesErros,
            'questoes_nao_respondidas' => $questoesNaoRespondidas,
            'questoes_pct_respondidas' => $pctQuestResp,
            'questoes_pct_acertos_respondidas' => $pctAcertosResp,
            'questoes_pct_acertos_total' => $pctAcertosTotal,
        ];
    }

    /**
     * Aluno só vê gabarito dos exercícios do módulo quando concluiu todos os módulos da jornada.
     */
    private function alunoPodeVerGabaritoJornada(int $alunoId, int $jornadaId): bool
    {
        $totalModulos = $this->db->fetch(
            "SELECT COUNT(*) as total FROM jornadas_modulos WHERE jornada_id = :jornada_id",
            ['jornada_id' => $jornadaId]
        );
        $modulosConcluidos = $this->db->fetch(
            "SELECT COUNT(DISTINCT jpa.modulo_id) as total FROM jornadas_progresso_alunos jpa
             INNER JOIN jornadas_modulos m ON m.id = jpa.modulo_id AND m.jornada_id = jpa.jornada_id
             WHERE jpa.aluno_id = :aluno_id AND jpa.jornada_id = :jornada_id AND jpa.atividade_tipo = 'modulo' AND jpa.status = 'concluido'",
            ['aluno_id' => $alunoId, 'jornada_id' => $jornadaId]
        );
        $total = (int) ($totalModulos['total'] ?? 0);
        $concl = (int) ($modulosConcluidos['total'] ?? 0);

        return $total > 0 && $concl >= $total;
    }

    /**
     * Extrai texto das alternativas (questoes_json) para enviar à IA no modal "Tudinha explica".
     */
    private function montarTextoAlternativasExercicioJornada($questoesJson): string
    {
        $decoded = null;
        if (is_array($questoesJson)) {
            $decoded = $questoesJson;
        } elseif (is_string($questoesJson) && trim($questoesJson) !== '') {
            $decoded = json_decode($questoesJson, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return '';
            }
        } else {
            return '';
        }
        $opcoes = null;
        if (isset($decoded['opcoes']) && is_array($decoded['opcoes'])) {
            $opcoes = $decoded['opcoes'];
        } elseif (isset($decoded[0]['letra'])) {
            $opcoes = $decoded;
        }
        if (!is_array($opcoes) || $opcoes === []) {
            return '';
        }
        $lines = [];
        foreach ($opcoes as $op) {
            if (!is_array($op)) {
                continue;
            }
            $letra = trim((string) ($op['letra'] ?? ''));
            $texto = (string) ($op['texto'] ?? $op['text'] ?? '');
            $texto = trim(html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $texto = preg_replace('/\s+/u', ' ', $texto);
            if (strlen($texto) > 1200) {
                $texto = substr($texto, 0, 1200) . '…';
            }
            if ($letra === '' && $texto === '') {
                continue;
            }
            $lines[] = 'Alternativa ' . ($letra !== '' ? strtoupper($letra) : '?') . ': ' . $texto;
        }

        return $lines !== [] ? "Texto integral das alternativas (use na explicação, citando trechos):\n" . implode("\n", $lines) . "\n" : '';
    }

    private function getTurmaIdsAluno(int $alunoId): array
    {
        if (!class_exists('AlunoTurmaHelper')) {
            require_once __DIR__ . '/../../Core/AlunoTurmaHelper.php';
        }
        return AlunoTurmaHelper::getTurmaIds($this->db, $alunoId);
    }

    private function jornadaPermiteAluno($jornada, int $alunoId, ?array $turmaIds = null): bool
    {
        if ($alunoId <= 0) {
            return false;
        }
        $ids = $turmaIds ?? $this->getTurmaIdsAluno($alunoId);
        foreach ($ids as $turmaId) {
            if ($this->jornadaPermiteTurma($jornada, $turmaId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica se a jornada está disponível para a turma (turma principal ou em estrutura.turmas_selecionadas).
     * Usado para compatibilidade com MariaDB (sem JSON_CONTAINS no SQL).
     */
    private function jornadaPermiteTurma($jornada, $turma_id)
    {
        if (!$jornada || !is_array($jornada)) {
            return false;
        }
        $tid = $jornada['turma_id'] ?? null;
        if ($tid !== null && (int) $tid === (int) $turma_id) {
            return true;
        }
        $json = $jornada['estrutura'] ?? $jornada['descricao_estrutura'] ?? ($jornada['descricao estrutura'] ?? '');
        $e = json_decode($json, true);
        return is_array($e) && !empty($e['turmas_selecionadas']) && in_array((int) $turma_id, array_map('intval', $e['turmas_selecionadas']), true);
    }

    /**
     * @return array{0: string[], 1: array<string, int>}
     */
    private function buildInPlaceholders(array $ids, string $prefix): array
    {
        $parts = [];
        $params = [];
        foreach (array_values($ids) as $i => $id) {
            $intId = (int) $id;
            if ($intId <= 0) {
                continue;
            }
            $key = $prefix . $i;
            $parts[] = ':' . $key;
            $params[$key] = $intId;
        }

        return [$parts, $params];
    }

    private function groupRowsByColumn(array $rows, string $column): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $k = (int) ($row[$column] ?? 0);
            if ($k <= 0) {
                continue;
            }
            $grouped[$k][] = $row;
        }

        return $grouped;
    }

    /**
     * Carrega conteúdo de vários módulos em poucas queries (show do aluno).
     *
     * @return array{videos: array<int, array>, documentos: array<int, array>, textos: array<int, array>, exercicios: array<int, array>, resumos: array<int, array>, tempo_modulo: array<int, int>, tempo_exercicio: array<int, int>}
     */
    private function carregarConteudoModulosShowEmLote(array $moduloIds, int $alunoId): array
    {
        $empty = [
            'videos' => [],
            'documentos' => [],
            'textos' => [],
            'exercicios' => [],
            'resumos' => [],
            'tempo_modulo' => [],
            'tempo_exercicio' => [],
        ];
        if ($moduloIds === []) {
            return $empty;
        }

        [$inParts, $inParams] = $this->buildInPlaceholders($moduloIds, 'mid');
        if ($inParts === []) {
            return $empty;
        }
        $inSql = implode(', ', $inParts);

        $videos = $this->groupRowsByColumn(
            $this->db->fetchAll(
                "SELECT {$this->colunasJornadasModulosVideos()} FROM jornadas_modulos_videos WHERE modulo_id IN ({$inSql}) AND status = 'ativo' ORDER BY modulo_id, ordem ASC",
                $inParams
            ),
            'modulo_id'
        );

        $documentos = $this->groupRowsByColumn(
            $this->db->fetchAll(
                "SELECT {$this->colunasJornadasModulosDocumentos()} FROM jornadas_modulos_documentos WHERE modulo_id IN ({$inSql}) AND status = 'ativo' ORDER BY modulo_id, ordem ASC",
                $inParams
            ),
            'modulo_id'
        );

        $textos = $this->groupRowsByColumn(
            $this->db->fetchAll(
                "SELECT {$this->colunasJornadasModulosTextos()} FROM jornadas_modulos_textos WHERE modulo_id IN ({$inSql}) ORDER BY modulo_id, ordem ASC, created_at ASC",
                $inParams
            ),
            'modulo_id'
        );

        $exParams = array_merge($inParams, ['aluno_id' => $alunoId]);
        $exercicios = $this->groupRowsByColumn(
            $this->db->fetchAll(
                "SELECT {$this->colunasSqlJornadasModulosExerciciosShow('me')}, jpa.status as progresso_status, jpa.pontuacao, jpa.tempo_gasto
                 FROM jornadas_modulos_exercicios me
                 LEFT JOIN jornadas_progresso_alunos jpa
                   ON jpa.exercicio_modulo_id = me.id AND jpa.aluno_id = :aluno_id AND jpa.atividade_tipo = 'exercicio_modulo'
                 WHERE me.modulo_id IN ({$inSql}) AND me.status = 'publicado'
                 ORDER BY me.modulo_id, me.ordem ASC",
                $exParams
            ),
            'modulo_id'
        );

        $resumos = [];
        $tempoModulo = [];
        $tempoExercicio = [];
        if ($alunoId > 0) {
            foreach ($this->db->fetchAll(
                "SELECT {$this->colunasJornadasResumosAlunos()} FROM jornadas_resumos_alunos WHERE aluno_id = :aluno_id AND modulo_id IN ({$inSql})",
                $exParams
            ) as $row) {
                $resumos[(int) $row['modulo_id']] = $row;
            }

            foreach ($this->db->fetchAll(
                "SELECT modulo_id, exercicio_modulo_id, atividade_tipo, tempo_gasto
                 FROM jornadas_progresso_alunos
                 WHERE aluno_id = :aluno_id AND modulo_id IN ({$inSql})
                   AND atividade_tipo IN ('modulo', 'exercicio_modulo')",
                $exParams
            ) as $row) {
                $tg = (int) ($row['tempo_gasto'] ?? 0);
                if ($tg <= 0) {
                    continue;
                }
                if (($row['atividade_tipo'] ?? '') === 'modulo') {
                    $tempoModulo[(int) $row['modulo_id']] = $tg;
                } elseif (!empty($row['exercicio_modulo_id'])) {
                    $tempoExercicio[(int) $row['exercicio_modulo_id']] = $tg;
                }
            }
        }

        return [
            'videos' => $videos,
            'documentos' => $documentos,
            'textos' => $textos,
            'exercicios' => $exercicios,
            'resumos' => $resumos,
            'tempo_modulo' => $tempoModulo,
            'tempo_exercicio' => $tempoExercicio,
        ];
    }

    /**
     * Registra erro nas ações da jornada em arquivo (storage/logs/jornadas_*.log e app_*.log)
     * e envia notificação no grupo de log do WhatsApp (Evolution API), quando configurado.
     */
    private function logJornadaError($message, array $context = [])
    {
        if (!class_exists('Logger')) {
            require_once __DIR__ . '/../Core/Logger.php';
        }
        $context['request_uri'] = $_SERVER['REQUEST_URI'] ?? null;
        $context['request_method'] = $_SERVER['REQUEST_METHOD'] ?? null;
        $context['user_id'] = $_SESSION['user_id'] ?? null;
        $context['user_type'] = $_SESSION['user_type'] ?? null;
        $context['ip'] = $_SERVER['REMOTE_ADDR'] ?? null;
        Logger::error($message, $context, 'jornadas');
    }

    /**
     * Garante tabela de auditoria de exercícios da jornada.
     */
    private function ensureJornadaExerciseAuditTableExists()
    {
        $scope = defined('TENANT_SLUG') ? (string) TENANT_SLUG : 'default';
        if (!empty(self::$auditTableEnsured[$scope])) {
            return;
        }

        try {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS jornadas_exercicios_auditoria (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    aluno_id INT NOT NULL,
                    jornada_id INT NOT NULL,
                    modulo_id INT NOT NULL,
                    exercicio_id INT NOT NULL,
                    tipo_acao VARCHAR(50) NOT NULL,
                    de_valor TEXT NULL,
                    para_valor TEXT NULL,
                    resposta_final TEXT NULL,
                    correto TINYINT(1) NULL,
                    pontuacao DECIMAL(10,2) NULL,
                    ip VARCHAR(45) NULL,
                    user_agent VARCHAR(255) NULL,
                    detalhes_json TEXT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_jornadas_auditoria_aluno (aluno_id),
                    INDEX idx_jornadas_auditoria_jornada (jornada_id),
                    INDEX idx_jornadas_auditoria_modulo (modulo_id),
                    INDEX idx_jornadas_auditoria_exercicio (exercicio_id),
                    INDEX idx_jornadas_auditoria_acao (tipo_acao),
                    INDEX idx_jornadas_auditoria_data (created_at),
                    INDEX idx_jea_aluno_created (aluno_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            self::$auditTableEnsured[$scope] = true;
        } catch (\Throwable $e) {
            $this->logJornadaError('ensureJornadaExerciseAuditTableExists: falha ao criar tabela', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function getClientIpForAudit()
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
        if (strpos((string) $ip, ',') !== false) {
            $parts = explode(',', (string) $ip);
            $ip = trim((string) ($parts[0] ?? ''));
        }
        return substr((string) $ip, 0, 45);
    }

    private function registrarAuditoriaExercicio(array $data)
    {
        $this->ensureJornadaExerciseAuditTableExists();
        try {
            $this->db->insert(
                "INSERT INTO jornadas_exercicios_auditoria
                 (aluno_id, jornada_id, modulo_id, exercicio_id, tipo_acao, de_valor, para_valor, resposta_final, correto, pontuacao, ip, user_agent, detalhes_json)
                 VALUES
                 (:aluno_id, :jornada_id, :modulo_id, :exercicio_id, :tipo_acao, :de_valor, :para_valor, :resposta_final, :correto, :pontuacao, :ip, :user_agent, :detalhes_json)",
                [
                    'aluno_id' => (int) ($data['aluno_id'] ?? 0),
                    'jornada_id' => (int) ($data['jornada_id'] ?? 0),
                    'modulo_id' => (int) ($data['modulo_id'] ?? 0),
                    'exercicio_id' => (int) ($data['exercicio_id'] ?? 0),
                    'tipo_acao' => (string) ($data['tipo_acao'] ?? 'evento'),
                    'de_valor' => isset($data['de_valor']) ? (string) $data['de_valor'] : null,
                    'para_valor' => isset($data['para_valor']) ? (string) $data['para_valor'] : null,
                    'resposta_final' => isset($data['resposta_final']) ? (string) $data['resposta_final'] : null,
                    'correto' => array_key_exists('correto', $data) ? ($data['correto'] === null ? null : ((int) ((bool) $data['correto']))) : null,
                    'pontuacao' => array_key_exists('pontuacao', $data) ? ($data['pontuacao'] === null ? null : (float) $data['pontuacao']) : null,
                    'ip' => $this->getClientIpForAudit(),
                    'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    'detalhes_json' => isset($data['detalhes_json']) ? (is_string($data['detalhes_json']) ? $data['detalhes_json'] : json_encode($data['detalhes_json'])) : null,
                ]
            );
        } catch (\Throwable $e) {
            $this->logJornadaError('registrarAuditoriaExercicio: falha ao gravar evento', [
                'exception' => $e->getMessage(),
                'tipo_acao' => $data['tipo_acao'] ?? null,
                'aluno_id' => $data['aluno_id'] ?? null,
                'exercicio_id' => $data['exercicio_id'] ?? null,
            ]);
        }
    }

    /**
     * Colunas de jornadas usadas na listagem do aluno (cards + agregações).
     */
    private function getJornadaListagemColunasJornada(): string
    {
        return $this->colunasSqlJornadasListagem('j');
    }

    /**
     * @param list<string> $colunas
     */
    private function prefixColunas(string $alias, array $colunas): string
    {
        $parts = [];
        foreach ($colunas as $col) {
            if (strpos($col, ' ') !== false) {
                $parts[] = $alias . '.`' . str_replace('`', '``', $col) . '`';
            } else {
                $parts[] = $alias . '.' . $col;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Mescla colunas base com colunas de estrutura (JSON ou legado).
     *
     * @param list<string> $base
     * @return list<string>
     */
    private function colunasJornadasComEstrutura(array $base): array
    {
        $cols = $base;
        foreach ($this->getEstruturaColumnNames() as $coluna) {
            if (!in_array($coluna, $cols, true)) {
                $cols[] = $coluna;
            }
        }

        return $cols;
    }

    /**
     * @return list<string>
     */
    private function colunasJornadasListagem(): array
    {
        return $this->colunasJornadasComEstrutura([
            'id', 'titulo', 'descricao', 'turma_id', 'created_at',
        ]);
    }

    private function colunasSqlJornadasListagem(string $alias = 'j'): string
    {
        return $this->prefixColunas($alias, $this->colunasJornadasListagem());
    }

    /**
     * Checagem de turma / jornadaPermiteAluno.
     *
     * @return list<string>
     */
    private function colunasJornadasPermiteAluno(): array
    {
        return $this->colunasJornadasComEstrutura(['id', 'turma_id']);
    }

    private function colunasSqlJornadasPermiteAluno(string $alias = 'j'): string
    {
        return $this->prefixColunas($alias, $this->colunasJornadasPermiteAluno());
    }

    /** Preview do professor — contexto mínimo. */
    private function colunasSqlJornadasPreview(string $alias = 'j'): string
    {
        return $this->prefixColunas($alias, ['id', 'turma_id', 'professor_id']);
    }

    /** Tela show / cabeçalho de redações. */
    private function colunasSqlJornadasShow(string $alias = 'j'): string
    {
        return $this->prefixColunas($alias, $this->colunasJornadasComEstrutura([
            'id', 'titulo', 'descricao', 'turma_id', 'professor_id', 'materia_id', 'status', 'ativo', 'created_at',
        ]));
    }

    /** Envio de mensagem aluno → professor. */
    private function colunasSqlJornadasMensagem(string $alias = 'j'): string
    {
        return $this->prefixColunas($alias, array_merge($this->colunasJornadasPermiteAluno(), ['professor_id']));
    }

    private function colunasSqlJornadasAulasShow(string $alias = 'ja'): string
    {
        return $this->prefixColunas($alias, [
            'id', 'jornada_id', 'ordem', 'nome_aula', 'resumo_oficial', 'pontos_principais',
            'conteudos_adicionais', 'status',
        ]);
    }

    private function colunasSqlJornadasAulasDetalhe(string $alias = 'ja'): string
    {
        return $this->prefixColunas($alias, [
            'id', 'jornada_id', 'ordem', 'nome_aula', 'resumo_oficial', 'pontos_principais',
            'conteudos_adicionais', 'status',
        ]);
    }

    private function colunasSqlJornadasModulosShow(string $alias = 'm'): string
    {
        return $this->prefixColunas($alias, [
            'id', 'jornada_id', 'aula_id', 'tipo_modulo', 'titulo', 'descricao', 'ordem', 'obrigatorio', 'status',
        ]);
    }

    private function colunasSqlJornadasModulosAcesso(string $alias = 'm'): string
    {
        return $this->prefixColunas($alias, ['id', 'jornada_id', 'tipo_modulo', 'titulo']);
    }

    private function colunasSqlJornadasExerciciosShow(string $alias = 'je'): string
    {
        return $this->prefixColunas($alias, [
            'id', 'jornada_id', 'aula_id', 'tipo', 'titulo', 'descricao', 'questoes_json', 'status',
        ]);
    }

    private function colunasSqlJornadasModulosExerciciosShow(string $alias = 'me'): string
    {
        return $this->prefixColunas($alias, [
            'id', 'modulo_id', 'tipo', 'titulo', 'enunciado', 'questoes_json', 'resposta_correta',
            'gabarito', 'pontuacao', 'ordem', 'status', 'imagem_url', 'nivel_dificuldade', 'gerado_ia',
        ]);
    }

    private function colunasSqlJornadasModulosExerciciosResponder(string $alias = 'me'): string
    {
        return $this->prefixColunas($alias, [
            'id', 'modulo_id', 'tipo', 'enunciado', 'questoes_json', 'resposta_correta', 'gabarito', 'pontuacao',
        ]);
    }

    private function colunasSqlJornadasDuvidas(string $alias = 'jd'): string
    {
        return implode(', ', [
            "{$alias}.id",
            "{$alias}.aluno_id",
            "{$alias}.aula_id",
            $this->sqlJornadasDuvidaColunaSelect($alias),
            "{$alias}.resposta",
            "{$alias}.respondido_por",
            "{$alias}.respondido_em",
            "{$alias}.status",
            "{$alias}.created_at",
            "{$alias}.updated_at",
        ]);
    }

    private function colunasSqlJornadasMensagens(string $alias = 'jm'): string
    {
        return $this->prefixColunas($alias, [
            'id', 'jornada_id', 'aluno_id', 'professor_id', 'remetente_tipo', 'remetente_id',
            'mensagem', 'lida', 'lida_em', 'created_at', 'updated_at',
        ]);
    }

    private function colunasSqlJornadasRedacoes(string $alias = 'jr'): string
    {
        return $this->prefixColunas($alias, [
            'id', 'jornada_id', 'aula_id', 'professor_id', 'tema_sugerido', 'descricao_tema',
            'imagem_tema', 'documento_tema', 'status', 'data_limite', 'correcao_ia_automatica',
        ]);
    }

    private function colunasSqlJornadasRedacoesAlunos(string $alias = 'jra'): string
    {
        return $this->prefixColunas($alias, [
            'id', 'jornada_redacao_id', 'redacao_id', 'aluno_id', 'versao', 'status',
            'retornada_para_reescrever', 'correcao_ia_feita', 'correcao_professor_feita',
        ]);
    }

    private function colunasSqlRedacoesEscrever(string $alias = 'r'): string
    {
        return $this->prefixColunas($alias, [
            'id', 'aluno_id', 'jornada_id', 'titulo', 'conteudo', 'texto', 'tema', 'eh_rascunho', 'tempo_escrita',
        ]);
    }

    /**
     * Colunas usadas em leituras/updates de jornadas_progresso_alunos (sem SELECT *).
     */
    private function colunasJornadasProgressoAlunos(): string
    {
        return implode(', ', [
            'id',
            'aluno_id',
            'jornada_id',
            'aula_id',
            'modulo_id',
            'exercicio_id',
            'exercicio_modulo_id',
            'atividade_tipo',
            'tempo_gasto',
            'status',
            'pontuacao',
            'resposta',
            'data_inicio',
            'data_conclusao',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * Colunas usadas em jornadas_resumos_alunos (sem SELECT *).
     */
    private function colunasJornadasResumosAlunos(): string
    {
        return implode(', ', [
            'id',
            'aluno_id',
            'jornada_id',
            'aula_id',
            'modulo_id',
            'resumo_aluno',
            'analise_ia',
            'lacunas_identificadas',
            'explicacoes_complementares',
            'pontuacao',
            'status',
            'created_at',
            'updated_at',
            'observacoes_professor',
            'nota',
        ]);
    }

    /**
     * Colunas usadas em jornadas_duvidas (sem SELECT *).
     */
    private function colunasJornadasDuvidas(): string
    {
        return implode(', ', [
            'id',
            'aluno_id',
            'aula_id',
            $this->sqlJornadasDuvidaColunaSelect(),
            'resposta',
            'respondido_por',
            'respondido_em',
            'status',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * Colunas usadas em jornadas_mensagens_anexos (sem SELECT *).
     */
    private function colunasJornadasMensagensAnexos(): string
    {
        return implode(', ', [
            'id',
            'mensagem_id',
            'nome_arquivo',
            'caminho_arquivo',
            'tipo_arquivo',
            'tamanho_arquivo',
            'created_at',
        ]);
    }

    private function colunasJornadasModulosVideos(): string
    {
        return implode(', ', [
            'id',
            'modulo_id',
            'tipo',
            'titulo',
            'descricao',
            'url_youtube',
            'arquivo_video',
            'arquivo_nome',
            'arquivo_tamanho',
            'ordem',
            'status',
            'created_at',
            'updated_at',
        ]);
    }

    private function colunasJornadasModulosDocumentos(): string
    {
        return implode(', ', [
            'id',
            'modulo_id',
            'titulo',
            'descricao',
            'arquivo',
            'arquivo_nome',
            'arquivo_tamanho',
            'tipo_arquivo',
            'ordem',
            'status',
            'created_at',
            'updated_at',
        ]);
    }

    private function colunasJornadasModulosTextos(): string
    {
        return implode(', ', [
            'id',
            'modulo_id',
            'titulo',
            'conteudo',
            'ordem',
            'created_at',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchAlunoParaListagemJornadas(int $userId): ?array
    {
        if (!class_exists('ContextoAluno')) {
            require_once __DIR__ . '/../../Core/ContextoAluno.php';
        }
        if (ContextoAluno::idDaSessao() !== $userId) {
            return null;
        }
        $aluno = ContextoAluno::resolver($this->db);
        if (!$aluno) {
            return null;
        }
        return [
            'id' => (int) ($aluno['id'] ?? 0),
            'nome' => (string) ($aluno['nome'] ?? ''),
            'turma_id' => (int) ($aluno['turma_id'] ?? 0),
            'turma_nome' => (string) ($aluno['turma_nome'] ?? ''),
        ];
    }

    /**
     * Mescla listas de jornadas deduplicando por id (mantém a mais recente por created_at).
     *
     * @param list<array<string,mixed>> ...$listas
     * @return list<array<string,mixed>>
     */
    private function mesclarJornadasPorId(array ...$listas): array
    {
        $porId = [];
        foreach ($listas as $lista) {
            foreach ($lista as $jornada) {
                $id = (int) ($jornada['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                if (!isset($porId[$id])) {
                    $porId[$id] = $jornada;
                    continue;
                }
                $atual = (string) ($porId[$id]['created_at'] ?? '');
                $nova = (string) ($jornada['created_at'] ?? '');
                if ($nova > $atual) {
                    $porId[$id] = $jornada;
                }
            }
        }

        $merged = array_values($porId);
        usort($merged, static function ($a, $b) {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return $merged;
    }

    /**
     * SQL de agregações da listagem — WHERE externo sem OR (passado pelo caller).
     */
    private function montarSqlJornadasListagemComAgregados(string $whereSql): string
    {
        $cols = $this->getJornadaListagemColunasJornada();

        return "
            SELECT
                {$cols},
                p.nome AS professor_nome,
                m.nome AS materia_nome,
                COALESCE(ja.total_aulas, 0) AS total_aulas,
                COALESCE(je.total_exercicios, 0) AS total_exercicios,
                COALESCE(jme.total_modulo_exercicios, 0) + COALESCE(je.total_exercicios, 0) AS questoes_total,
                COALESCE(jqst_mod.questoes_respondidas, 0) + COALESCE(jqst_leg.questoes_respondidas, 0) AS questoes_respondidas,
                COALESCE(jqst_mod.questoes_acertos, 0) + COALESCE(jqst_leg.questoes_acertos, 0) AS questoes_acertos,
                COALESCE(jpa_aulas.aulas_concluidas, 0) AS aulas_concluidas,
                COALESCE(jm.total_modulos, 0) AS total_modulos,
                COALESCE(jpa_mod.modulos_concluidos, 0) AS modulos_concluidos
            FROM jornadas j
            INNER JOIN professores p ON p.id = j.professor_id
            LEFT JOIN materias m ON m.id = j.materia_id
            LEFT JOIN (
                SELECT jornada_id, COUNT(*) AS total_aulas
                FROM jornadas_aulas
                WHERE status = 'ativa'
                GROUP BY jornada_id
            ) ja ON ja.jornada_id = j.id
            LEFT JOIN (
                SELECT jornada_id, COUNT(*) AS total_exercicios
                FROM jornadas_exercicios
                WHERE status = 'publicado'
                GROUP BY jornada_id
            ) je ON je.jornada_id = j.id
            LEFT JOIN (
                SELECT jm2.jornada_id, COUNT(me.id) AS total_modulo_exercicios
                FROM jornadas_modulos jm2
                INNER JOIN jornadas_modulos_exercicios me ON me.modulo_id = jm2.id AND me.status = 'publicado'
                GROUP BY jm2.jornada_id
            ) jme ON jme.jornada_id = j.id
            LEFT JOIN (
                SELECT base.jornada_id,
                       COUNT(*) AS questoes_respondidas,
                       SUM(CASE WHEN base.pontuacao > 0 THEN 1 ELSE 0 END) AS questoes_acertos
                FROM (
                    SELECT jpa.jornada_id, MAX(COALESCE(jpa.pontuacao, 0)) AS pontuacao
                    FROM jornadas_progresso_alunos jpa
                    WHERE jpa.aluno_id = :aluno_id_quest_mod
                      AND jpa.atividade_tipo = 'exercicio_modulo'
                      AND jpa.resposta IS NOT NULL
                      AND jpa.exercicio_modulo_id IS NOT NULL
                    GROUP BY jpa.jornada_id, jpa.exercicio_modulo_id
                ) base
                GROUP BY base.jornada_id
            ) jqst_mod ON jqst_mod.jornada_id = j.id
            LEFT JOIN (
                SELECT base.jornada_id,
                       COUNT(*) AS questoes_respondidas,
                       SUM(CASE WHEN base.pontuacao > 0 THEN 1 ELSE 0 END) AS questoes_acertos
                FROM (
                    SELECT jpa.jornada_id, MAX(COALESCE(jpa.pontuacao, 0)) AS pontuacao
                    FROM jornadas_progresso_alunos jpa
                    WHERE jpa.aluno_id = :aluno_id_quest_leg
                      AND jpa.atividade_tipo = 'exercicio'
                      AND jpa.resposta IS NOT NULL
                      AND jpa.exercicio_id IS NOT NULL
                    GROUP BY jpa.jornada_id, jpa.exercicio_id
                ) base
                GROUP BY base.jornada_id
            ) jqst_leg ON jqst_leg.jornada_id = j.id
            LEFT JOIN (
                SELECT jornada_id, COUNT(*) AS aulas_concluidas
                FROM jornadas_progresso_alunos
                WHERE aluno_id = :aluno_id_aulas
                  AND status = 'concluido'
                GROUP BY jornada_id
            ) jpa_aulas ON jpa_aulas.jornada_id = j.id
            LEFT JOIN (
                SELECT jornada_id, COUNT(*) AS total_modulos
                FROM jornadas_modulos
                GROUP BY jornada_id
            ) jm ON jm.jornada_id = j.id
            LEFT JOIN (
                SELECT jornada_id, COUNT(*) AS modulos_concluidos
                FROM jornadas_progresso_alunos
                WHERE aluno_id = :aluno_id_modulo
                  AND atividade_tipo = 'modulo'
                  AND status = 'concluido'
                GROUP BY jornada_id
            ) jpa_mod ON jpa_mod.jornada_id = j.id
            WHERE {$whereSql}
              AND COALESCE(j.ativo, 1) = 1
            ORDER BY j.created_at DESC
        ";
    }

    /**
     * @return array<string, mixed>
     */
    private function paramsAgregacaoJornadasListagem(int $alunoId): array
    {
        return [
            'aluno_id_quest_mod' => $alunoId,
            'aluno_id_quest_leg' => $alunoId,
            'aluno_id_aulas' => $alunoId,
            'aluno_id_modulo' => $alunoId,
        ];
    }

    /**
     * Busca jornadas da turma do aluno (consulta separada — sem OR com estrutura).
     *
     * @param list<int> $turmaIds
     * @return list<array<string,mixed>>
     */
    private function fetchJornadasListagemPorTurma(int $alunoId, array $turmaIds): array
    {
        if ($turmaIds === []) {
            return [];
        }

        $turmaInParts = [];
        $params = $this->paramsAgregacaoJornadasListagem($alunoId);
        foreach ($turmaIds as $i => $tid) {
            $key = 'turma_in_' . $i;
            $turmaInParts[] = ':' . $key;
            $params[$key] = (int) $tid;
        }
        $turmaInSql = implode(', ', $turmaInParts);
        $whereSql = "j.turma_id IN ({$turmaInSql})";

        return $this->db->fetchAll($this->montarSqlJornadasListagemComAgregados($whereSql), $params) ?: [];
    }

    /**
     * Busca jornadas com JSON de estrutura preenchido (uma consulta por coluna de estrutura).
     *
     * @return list<array<string,mixed>>
     */
    private function fetchJornadasListagemComEstrutura(int $alunoId): array
    {
        $resultado = [];
        foreach ($this->getEstruturaColumnNames() as $coluna) {
            $ref = $this->quoteEstruturaColumn($coluna);
            $whereSql = "({$ref} IS NOT NULL AND TRIM({$ref}) <> '')";
            $params = $this->paramsAgregacaoJornadasListagem($alunoId);
            $lote = $this->db->fetchAll($this->montarSqlJornadasListagemComAgregados($whereSql), $params) ?: [];
            $resultado = $this->mesclarJornadasPorId($resultado, $lote);
        }

        return $resultado;
    }

    /**
     * Listagem completa: turma + estrutura, mesclada em PHP (sem OR no SQL).
     *
     * @param list<int> $turmaIds
     * @return list<array<string,mixed>>
     */
    private function fetchJornadasListagemAluno(int $alunoId, array $turmaIds): array
    {
        $porTurma = $this->fetchJornadasListagemPorTurma($alunoId, $turmaIds);
        $comEstrutura = $this->fetchJornadasListagemComEstrutura($alunoId);

        return $this->mesclarJornadasPorId($porTurma, $comEstrutura);
    }

    /**
     * @return array{aluno_id:int,aluno:array<string,mixed>,turma_ids:list<int>}|null
     */
    private function assertContextoApiListagemJornadas(): ?array
    {
        $user = $this->authManager->getUser();
        if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
            $this->json(['success' => false, 'error' => 'Não autorizado'], 401);
            return null;
        }

        $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
        if (!$aluno) {
            $this->json(['success' => false, 'error' => 'Aluno não encontrado'], 404);
            return null;
        }

        return [
            'aluno_id' => (int) $aluno['id'],
            'aluno' => $aluno,
            'turma_ids' => $this->getTurmaIdsAluno((int) $aluno['id']),
        ];
    }

    private function elapsedMs(float $start): float
    {
        return round((microtime(true) - $start) * 1000, 1);
    }

    /**
     * @param list<array<string,mixed>> $jornadas
     * @return array{materias:list<string>,professores:list<string>}
     */
    private function extrairFiltrosJornadasListagem(array $jornadas): array
    {
        $materias = array_values(array_filter(array_unique(array_map(
            static fn ($j) => trim((string) ($j['materia_nome'] ?? '')),
            $jornadas
        ))));
        $professores = array_values(array_filter(array_unique(array_map(
            static fn ($j) => trim((string) ($j['professor_nome'] ?? '')),
            $jornadas
        ))));
        sort($materias);
        sort($professores);

        return ['materias' => $materias, 'professores' => $professores];
    }

    /**
     * @param list<array<string,mixed>> $jornadas
     */
    private function renderJornadasCardsPartial(array $jornadas): string
    {
        ob_start();
        include __DIR__ . '/../../Views/student/journeys/_cards.php';
        return (string) ob_get_clean();
    }

    /**
     * Bloco 1 — jornadas da turma do aluno.
     */
    public function apiIndexBlocoTurma(): void
    {
        $ctx = $this->assertContextoApiListagemJornadas();
        if ($ctx === null) {
            return;
        }

        $start = microtime(true);
        try {
            $jornadas = $this->fetchJornadasListagemPorTurma($ctx['aluno_id'], $ctx['turma_ids']);
            $this->json([
                'success' => true,
                'bloco' => 'turma',
                'ms' => $this->elapsedMs($start),
                'count' => count($jornadas),
            ]);
        } catch (Throwable $e) {
            error_log('apiIndexBlocoTurma: ' . $e->getMessage());
            $this->json(['success' => false, 'bloco' => 'turma', 'error' => 'Erro ao carregar jornadas da turma'], 500);
        }
    }

    /**
     * Bloco 2 — jornadas com estrutura JSON (multi-turma etc.).
     */
    public function apiIndexBlocoEstrutura(): void
    {
        $ctx = $this->assertContextoApiListagemJornadas();
        if ($ctx === null) {
            return;
        }

        $start = microtime(true);
        try {
            $jornadas = $this->fetchJornadasListagemComEstrutura($ctx['aluno_id']);
            $jornadas = array_values(array_filter(
                $jornadas,
                fn ($j) => $this->jornadaPermiteAluno($j, $ctx['aluno_id'], $ctx['turma_ids'])
            ));
            $this->json([
                'success' => true,
                'bloco' => 'estrutura',
                'ms' => $this->elapsedMs($start),
                'count' => count($jornadas),
            ]);
        } catch (Throwable $e) {
            error_log('apiIndexBlocoEstrutura: ' . $e->getMessage());
            $this->json(['success' => false, 'bloco' => 'estrutura', 'error' => 'Erro ao carregar jornadas com estrutura'], 500);
        }
    }

    /**
     * Bloco 3 — busca no servidor, mescla, progresso, finaliza e renderiza cards + resumo.
     */
    public function apiIndexMontar(): void
    {
        $ctx = $this->assertContextoApiListagemJornadas();
        if ($ctx === null) {
            return;
        }

        $timings = [];
        try {
            $t0 = microtime(true);
            $jornadas = $this->fetchJornadasListagemAluno($ctx['aluno_id'], $ctx['turma_ids']);
            $jornadas = $this->normalizeJourneyEstrutura($jornadas);
            $timings['fetch_ms'] = $this->elapsedMs($t0);

            $t1 = microtime(true);
            $jornadas = $this->recalcularQuestoesRespondidasAcertosPorGabarito($ctx['aluno_id'], $jornadas);
            $timings['progresso_ms'] = $this->elapsedMs($t1);

            $t2 = microtime(true);
            $jornadas = $this->finalizeJourneyList($jornadas, $ctx['aluno'], $ctx['turma_ids'], false);
            $timings['finalizar_ms'] = $this->elapsedMs($t2);

            $turmaToken = AlunoTurmaHelper::getTurmaIdsCacheToken($ctx['turma_ids']);
            if ($this->ensureRedisCacheAvailable()) {
                $this->setCachedJourneyList($ctx['aluno_id'], $turmaToken, $jornadas);
            }

            $t3 = microtime(true);
            $dashboard = $this->buildJornadasAlunoDashboardStats($jornadas);
            $html = $this->renderJornadasCardsPartial($jornadas);
            $filtros = $this->extrairFiltrosJornadasListagem($jornadas);
            $timings['render_ms'] = $this->elapsedMs($t3);
            $timings['total_ms'] = round(array_sum($timings), 1);

            $this->json([
                'success' => true,
                'bloco' => 'montar',
                'timings' => $timings,
                'count' => count($jornadas),
                'dashboard' => $dashboard,
                'filtros' => $filtros,
                'html' => $html,
            ]);
        } catch (Throwable $e) {
            error_log('apiIndexMontar: ' . $e->getMessage());
            $this->json(['success' => false, 'bloco' => 'montar', 'error' => 'Erro ao montar listagem'], 500);
        }
    }

    /**
     * Lista jornadas do aluno
     */
    public function index()
    {
        $user = $this->authManager->getUser();
        
        if ($user['tipo'] !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
        
        $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
        
        if (!$aluno) {
            throw new Exception('Aluno não encontrado');
        }

        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $data = [
            'title' => 'Minhas Jornadas - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'jornadas' => [],
            'jornadas_dashboard' => null,
            'carregamento_blocos' => true,
            'current_page' => 'journeys',
            'chat_habilitado' => LayoutHelper::isModuleEnabled('chat'),
        ];
        
        $this->viewWithLayout('student', 'student/journeys/index', $data);
    }
    
    /**
     * Exibe detalhes de uma jornada específica
     */
    public function show($id)
    {
        $user = $this->authManager->getUser();
        $preview = false;

        if ($user['tipo'] === 'professor' && isset($_GET['preview']) && (int)$_GET['preview'] === 1) {
            $jornadaPreview = $this->db->fetch(
                "SELECT {$this->colunasSqlJornadasPreview('j')} FROM jornadas j WHERE j.id = :id AND j.professor_id = :prof_id",
                ['id' => $id, 'prof_id' => $user['id']]
            );
            if ($jornadaPreview) {
                $preview = true;
                $aluno = [
                    'id' => 0,
                    'turma_id' => $jornadaPreview['turma_id'] ?? 0,
                    'turma_nome' => 'Preview',
                    'nome' => 'Preview'
                ];
            }
        }

        if (!$preview && $user['tipo'] !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }

        $turmaIdsShow = null;
        if (!$preview) {
            // Busca dados do aluno
            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }
            $turmaIdsShow = $this->getTurmaIdsAluno((int) $aluno['id']);
        }

        // Busca dados da jornada por id (checagem de turma em PHP por compatibilidade MariaDB).
        // Em preview (professor) aceita qualquer status; para aluno só ativa/em_andamento/aguardando.
        if ($preview) {
            $jornada = $this->db->fetch(
                "SELECT {$this->colunasSqlJornadasShow('j')}, 
                        p.nome as professor_nome,
                        COALESCE(jm.nome, m.nome) as materia_nome,
                        jm.cor as materia_cor,
                        jm.icone as materia_icone
                 FROM jornadas j
                 JOIN professores p ON j.professor_id = p.id
                 LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id
                 LEFT JOIN materias m ON j.materia_id = m.id
                 WHERE j.id = :jornada_id AND j.professor_id = :prof_id AND (j.ativo = 1 OR j.ativo IS NULL)",
                ['jornada_id' => $id, 'prof_id' => $user['id']]
            );
        } else {
            $jornada = $this->db->fetch(
                "SELECT {$this->colunasSqlJornadasShow('j')}, 
                        p.nome as professor_nome,
                        COALESCE(jm.nome, m.nome) as materia_nome,
                        jm.cor as materia_cor,
                        jm.icone as materia_icone
                 FROM jornadas j
                 JOIN professores p ON j.professor_id = p.id
                 LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id
                 LEFT JOIN materias m ON j.materia_id = m.id
                 WHERE j.id = :jornada_id 
                     AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status = 'aguardando' OR j.status = 'finalizada' OR j.status IS NULL OR j.status = '')
                     AND j.status != 'pausada'
                     AND (j.ativo = 1 OR j.ativo IS NULL)",
                ['jornada_id' => $id]
            );
        }
        if ($jornada && !$preview && !$this->jornadaPermiteAluno($jornada, (int) ($aluno['id'] ?? 0), $turmaIdsShow)) {
            $jornada = null;
        }
        if (!$jornada) {
            $jornadaPausada = $this->db->fetch(
                "SELECT {$this->colunasSqlJornadasPermiteAluno('j')} FROM jornadas j WHERE j.id = :jornada_id AND j.status = 'pausada'",
                ['jornada_id' => $id]
            );
            if ($jornadaPausada && !$this->jornadaPermiteAluno($jornadaPausada, (int) ($aluno['id'] ?? 0), $turmaIdsShow)) {
                $jornadaPausada = null;
            }
            
            if ($jornadaPausada) {
                $_SESSION['error_message'] = 'Esta jornada está pausada pelo professor e não pode ser acessada no momento.';
                $this->redirect('/jornadas');
                return;
            }
            
            $_SESSION['error_message'] = 'Jornada não encontrada ou não está ativa';
            if ($preview) {
                $this->redirect('/professor/jornadas/' . (int)$id);
            } else {
                $this->redirect('/jornadas');
            }
            return;
        }
        
        // Extrai data/hora do JSON estrutura e valida se já pode ser acessada (usa data + hora)
        $dataInicio = null;
        $dataFim = null;
        $horaInicio = null;
        $horaFim = null;
        if (!empty($jornada['estrutura'])) {
            $estrutura = json_decode($jornada['estrutura'], true);
            if (is_array($estrutura)) {
                $dataInicio = $estrutura['data_inicio'] ?? null;
                $dataFim = $estrutura['data_fim'] ?? null;
                $horaInicio = trim((string)($estrutura['hora_inicio'] ?? '')) ?: '00:00';
                $horaFim = trim((string)($estrutura['hora_fim'] ?? '')) ?: '23:59:59';
            }
        }
        
        // Verifica se a jornada já pode ser iniciada (agora >= data_inicio + hora_inicio) — não em preview
        if (!$preview && $dataInicio) {
            $tsInicio = strtotime($dataInicio . ' ' . $horaInicio);
            if ($tsInicio !== false && time() < $tsInicio) {
                $_SESSION['error_message'] = 'Esta jornada ainda não está disponível. Ela estará disponível a partir de ' . date('d/m/Y H:i', $tsInicio) . '.';
                $this->redirect('/jornadas');
                return;
            }
        }
        
        // Registra visualização da jornada pelo aluno (se ainda não foi visualizada) — não em modo preview
        $progressoJornada = null;
        if (!$preview) {
            $progressoJornada = $this->db->fetch(
                "SELECT {$this->colunasJornadasProgressoAlunos()} FROM jornadas_progresso_alunos 
                 WHERE aluno_id = :aluno_id AND jornada_id = :jornada_id 
                 AND (atividade_tipo = 'visualizacao' OR atividade_tipo = 'jornada_concluida')
                 AND modulo_id IS NULL AND aula_id IS NULL AND exercicio_id IS NULL AND exercicio_modulo_id IS NULL",
                [
                    'aluno_id' => $aluno['id'],
                    'jornada_id' => $id
                ]
            );
            if (!$progressoJornada) {
                try {
                    $this->db->insert(
                        "INSERT INTO jornadas_progresso_alunos (aluno_id, jornada_id, atividade_tipo, status, created_at, updated_at) 
                         VALUES (:aluno_id, :jornada_id, 'visualizacao', 'visualizado', NOW(), NOW())",
                        [
                            'aluno_id' => $aluno['id'],
                            'jornada_id' => $id
                        ]
                    );
                } catch (Exception $e) {
                    $this->logJornadaError('show: erro ao inserir progresso de visualização da jornada', [
                        'exception' => $e->getMessage(),
                        'jornada_id' => $id,
                        'aluno_id' => $aluno['id'],
                    ]);
                }
            } else {
                if ($progressoJornada['status'] === 'nao_visualizado' || empty($progressoJornada['status'])) {
                    $this->db->update(
                        "UPDATE jornadas_progresso_alunos 
                         SET status = 'visualizado', updated_at = NOW() 
                         WHERE id = :progresso_id",
                        ['progresso_id' => $progressoJornada['id']]
                    );
                }
            }
        }
        
        // Busca aulas da jornada
        $aulas = $this->db->fetchAll(
            "SELECT {$this->colunasSqlJornadasAulasShow('ja')},
                    (SELECT COUNT(*) FROM jornadas_exercicios je WHERE je.aula_id = ja.id AND je.status = 'publicado') as total_exercicios,
                    (SELECT COUNT(*) FROM jornadas_duvidas jd WHERE jd.aula_id = ja.id AND jd.aluno_id = :aluno_id_1) as total_duvidas,
                    jpa.status as progresso_status,
                    jpa.tempo_gasto,
                    jpa.pontuacao,
                    jpa.data_conclusao
             FROM jornadas_aulas ja
             LEFT JOIN jornadas_progresso_alunos jpa ON jpa.aula_id = ja.id AND jpa.aluno_id = :aluno_id_2 AND jpa.atividade_tipo = 'aula'
             WHERE ja.jornada_id = :jornada_id AND ja.status = 'ativa'
             ORDER BY ja.ordem ASC",
            [
                'jornada_id' => $id,
                'aluno_id_1' => $aluno['id'],
                'aluno_id_2' => $aluno['id']
            ]
        );
        
        // Busca exercícios da jornada
        $exercicios = $this->db->fetchAll(
            "SELECT {$this->colunasSqlJornadasExerciciosShow('je')},
                    jpa.status as progresso_status,
                    jpa.tempo_gasto,
                    jpa.pontuacao,
                    jpa.data_conclusao
             FROM jornadas_exercicios je
             LEFT JOIN jornadas_progresso_alunos jpa ON jpa.exercicio_id = je.id AND jpa.aluno_id = :aluno_id AND jpa.atividade_tipo = 'exercicio'
             WHERE je.jornada_id = :jornada_id AND je.status = 'publicado'
             ORDER BY je.created_at ASC",
            [
                'jornada_id' => $id,
                'aluno_id' => $aluno['id']
            ]
        );
        
        // Busca dúvidas do aluno nesta jornada
        $duvidas = $this->db->fetchAll(
            "SELECT {$this->colunasSqlJornadasDuvidas('jd')}, ja.nome_aula as nome_aula
             FROM jornadas_duvidas jd
             JOIN jornadas_aulas ja ON jd.aula_id = ja.id
             WHERE jd.aluno_id = :aluno_id AND ja.jornada_id = :jornada_id
             ORDER BY jd.created_at DESC",
            [
                'aluno_id' => $aluno['id'],
                'jornada_id' => $id
            ]
        );
        
        
        // Busca módulos da jornada (etapas sequenciais)
        $modulos = $this->db->fetchAll(
            "SELECT {$this->colunasSqlJornadasModulosShow('m')}, 
                    jpa.status as progresso_status,
                    jpa.data_conclusao as data_conclusao_modulo
             FROM jornadas_modulos m
             LEFT JOIN jornadas_progresso_alunos jpa ON jpa.modulo_id = m.id AND jpa.aluno_id = :aluno_id AND jpa.atividade_tipo = 'modulo'
             WHERE m.jornada_id = :jornada_id AND m.status = 'ativo'
             ORDER BY m.ordem ASC, m.created_at ASC",
            [
                'jornada_id' => $id,
                'aluno_id' => $aluno['id']
            ]
        );
        
        // Remove módulos de redação da jornada do aluno (redação não é exibida dentro da jornada)
        $modulos = array_values(array_filter($modulos, function ($m) {
            return ($m['tipo_modulo'] ?? '') !== 'redacao';
        }));
        
        $moduloIds = array_map(fn($m) => (int) $m['id'], $modulos);
        $conteudoLote = $this->carregarConteudoModulosShowEmLote($moduloIds, (int) ($aluno['id'] ?? 0));

        foreach ($modulos as &$modulo) {
            $modId = (int) $modulo['id'];
            $modulo['conteudo'] = [];

            if (in_array($modulo['tipo_modulo'], ['video', 'conteudo', 'dica_professor'], true)) {
                $modulo['videos'] = $conteudoLote['videos'][$modId] ?? [];
                $modulo['documentos'] = $conteudoLote['documentos'][$modId] ?? [];
                $modulo['textos'] = $conteudoLote['textos'][$modId] ?? [];
            }

            if ($modulo['tipo_modulo'] === 'exercicios' || $modulo['tipo_modulo'] === 'exercicio') {
                $modulo['exercicios'] = $conteudoLote['exercicios'][$modId] ?? [];
            } else {
                $modulo['exercicios'] = [];
            }

            if ($modulo['tipo_modulo'] === 'resumo_aluno') {
                $modulo['resumo'] = $conteudoLote['resumos'][$modId] ?? null;
            }
        }
        unset($modulo);
        
        // Verifica se todas as atividades principais foram concluídas e marca a jornada como concluída
        $todasAtividadesConcluidas = true;
        if (!empty($modulos)) {
            foreach ($modulos as $mod) {
                if ($mod['progresso_status'] !== 'concluido') {
                    $todasAtividadesConcluidas = false;
                    break;
                }
            }
        } else {
            // Se não houver módulos, verifica aulas e exercícios
            $todasAulasConcluidas = true;
            foreach ($aulas as $aula) {
                if (($aula['progresso_status'] ?? '') !== 'concluido') {
                    $todasAulasConcluidas = false;
                    break;
                }
            }
            $todasAtividadesConcluidas = $todasAulasConcluidas;
        }
        
        // Se todas as atividades foram concluídas, atualiza o status da jornada
        if ($todasAtividadesConcluidas) {
            $progressoJornada = $this->db->fetch(
                "SELECT {$this->colunasJornadasProgressoAlunos()} FROM jornadas_progresso_alunos 
                 WHERE aluno_id = :aluno_id AND jornada_id = :jornada_id 
                 AND atividade_tipo IS NULL AND modulo_id IS NULL AND aula_id IS NULL AND exercicio_id IS NULL",
                [
                    'aluno_id' => $aluno['id'],
                    'jornada_id' => $id
                ]
            );
            
            if ($progressoJornada) {
                // Atualiza para concluído se ainda não estiver
                if ($progressoJornada['status'] !== 'concluido') {
                    $this->db->update(
                        "UPDATE jornadas_progresso_alunos 
                         SET status = 'concluido', data_conclusao = NOW(), updated_at = NOW() 
                         WHERE id = :progresso_id",
                        ['progresso_id' => $progressoJornada['id']]
                    );
                }
            } else {
                // Cria registro de conclusão da jornada
                $this->db->insert(
                    "INSERT INTO jornadas_progresso_alunos (aluno_id, jornada_id, atividade_tipo, status, data_conclusao, created_at, updated_at) 
                     VALUES (:aluno_id, :jornada_id, 'jornada_concluida', 'concluido', NOW(), NOW(), NOW())",
                    [
                        'aluno_id' => $aluno['id'],
                        'jornada_id' => $id
                    ]
                );
            }
            // Contabiliza tempo total da jornada (do primeiro ao último bloco) para o professor
            $inicio = $this->db->fetch(
                "SELECT MIN(created_at) as inicio FROM jornadas_progresso_alunos 
                 WHERE jornada_id = :jornada_id AND aluno_id = :aluno_id AND modulo_id IS NOT NULL",
                ['jornada_id' => $id, 'aluno_id' => $aluno['id']]
            );
            $fim = $this->db->fetch(
                "SELECT MAX(COALESCE(data_conclusao, created_at)) as fim FROM jornadas_progresso_alunos 
                 WHERE jornada_id = :jornada_id AND aluno_id = :aluno_id AND modulo_id IS NOT NULL",
                ['jornada_id' => $id, 'aluno_id' => $aluno['id']]
            );
            $dataInicio = $inicio['inicio'] ?? null;
            $dataFim = $fim['fim'] ?? date('Y-m-d H:i:s');
            if ($dataInicio) {
                $dataInicioTs = strtotime($dataInicio);
                $dataFimTs = strtotime($dataFim);
                $tempoTotalSegundos = max(0, (int)($dataFimTs - $dataInicioTs));
                $this->db->query(
                    "INSERT INTO jornadas_tempo_alunos (aluno_id, jornada_id, data_inicio, data_fim, tempo_total_segundos, updated_at) 
                     VALUES (:aluno_id, :jornada_id, :data_inicio, :data_fim, :tempo_total_segundos, NOW())
                     ON DUPLICATE KEY UPDATE data_fim = VALUES(data_fim), tempo_total_segundos = VALUES(tempo_total_segundos), updated_at = NOW()",
                    [
                        'aluno_id' => $aluno['id'],
                        'jornada_id' => $id,
                        'data_inicio' => $dataInicio,
                        'data_fim' => $dataFim,
                        'tempo_total_segundos' => $tempoTotalSegundos
                    ]
                );
            }
        }
        
        // Calcula tempo total de todas as atividades (aulas + módulos/etapas)
        $tempoTotalAulas = array_sum(array_column($aulas, 'tempo_gasto')) ?? 0;
        $tempoTotalModulos = 0;
        
        if (!empty($modulos)) {
            foreach ($modulos as $modulo) {
                $modId = (int) $modulo['id'];
                if (!empty($conteudoLote['tempo_modulo'][$modId])) {
                    $tempoTotalModulos += $conteudoLote['tempo_modulo'][$modId];
                }
                if (($modulo['tipo_modulo'] === 'exercicios' || $modulo['tipo_modulo'] === 'exercicio') && !empty($modulo['exercicios'])) {
                    foreach ($modulo['exercicios'] as $exercicio) {
                        $exId = (int) $exercicio['id'];
                        if (!empty($conteudoLote['tempo_exercicio'][$exId])) {
                            $tempoTotalModulos += $conteudoLote['tempo_exercicio'][$exId];
                        }
                    }
                }
            }
        }
        
        $tempoTotalSegundos = $tempoTotalAulas + $tempoTotalModulos;

        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $data = [
            'title' => $jornada['titulo'] . ' - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'jornada' => $jornada,
            'aulas' => $aulas,
            'exercicios' => $exercicios,
            'duvidas' => $duvidas,
            'modulos' => $modulos,
            'tempo_total_segundos' => $tempoTotalSegundos,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'journeys',
            'preview' => $preview,
            'jornada_concluida_aluno' => (bool) $todasAtividadesConcluidas,
            'chat_habilitado' => LayoutHelper::isModuleEnabled('chat'),
        ];
        
        $this->viewWithLayout('student', 'student/journeys/show', $data);
    }
    
    /**
     * Exibe uma aula específica
     */
    public function aula($jornada_id, $aula_id)
    {
        $user = $this->authManager->getUser();
        
        if ($user['tipo'] !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
        
        // Busca dados do aluno
        $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
        
        // Busca dados da aula (checagem de turma em PHP por compatibilidade MariaDB)
        $aula = $this->db->fetch(
            "SELECT {$this->colunasSqlJornadasAulasShow('ja')}, j.titulo as jornada_titulo, j.id as jornada_id, j.turma_id, j.estrutura
             FROM jornadas_aulas ja
             JOIN jornadas j ON ja.jornada_id = j.id
             WHERE ja.id = :aula_id 
                 AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status = 'finalizada' OR j.status IS NULL OR j.status = '')
                 AND j.status != 'pausada'",
            ['aula_id' => $aula_id]
        );
        if ($aula && !$this->jornadaPermiteAluno($aula, (int) ($aluno['id'] ?? 0))) {
            $aula = null;
        }
        if (!$aula) {
            $this->logJornadaError('aula: aula não encontrada ou jornada inativa', [
                'aula_id' => $aula_id,
                'aluno_id' => $aluno['id'] ?? null,
            ]);
            $_SESSION['error_message'] = 'Aula não encontrada ou jornada não está ativa';
            $this->redirect('/jornadas');
            return;
        }
        
        // Busca resumo do aluno para esta aula
        $resumo_aluno = $this->db->fetch(
            "SELECT {$this->colunasJornadasResumosAlunos()} FROM jornadas_resumos_alunos 
             WHERE aluno_id = :aluno_id AND aula_id = :aula_id",
            [
                'aluno_id' => $aluno['id'],
                'aula_id' => $aula_id
            ]
        );
        
        // Busca exercícios da aula
        $exercicios_aula = $this->db->fetchAll(
            "SELECT {$this->colunasSqlJornadasExerciciosShow('je')},
                    jpa.status as progresso_status,
                    jpa.pontuacao
             FROM jornadas_exercicios je
             LEFT JOIN jornadas_progresso_alunos jpa ON jpa.exercicio_id = je.id AND jpa.aluno_id = :aluno_id AND jpa.atividade_tipo = 'exercicio'
             WHERE je.aula_id = :aula_id AND je.status = 'publicado'
             ORDER BY je.created_at ASC",
            [
                'aula_id' => $aula_id,
                'aluno_id' => $aluno['id']
            ]
        );
        
        // Busca dúvidas do aluno para esta aula
        $duvidas_aula = $this->db->fetchAll(
            "SELECT {$this->colunasJornadasDuvidas()} FROM jornadas_duvidas 
             WHERE aluno_id = :aluno_id AND aula_id = :aula_id
             ORDER BY created_at DESC",
            [
                'aluno_id' => $aluno['id'],
                'aula_id' => $aula_id
            ]
        );
        
        $data = [
            'title' => $aula['titulo'] . ' - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'aula' => $aula,
            'resumo_aluno' => $resumo_aluno,
            'exercicios_aula' => $exercicios_aula,
            'duvidas_aula' => $duvidas_aula,
            'current_page' => 'journeys'
        ];
        
        $this->viewWithLayout('student', 'student/journeys/aula', $data);
    }
    
    /**
     * Salva resumo do aluno e analisa com IA
     */
    public function salvarResumo()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->logJornadaError('jornada_aluno salvarResumo: token CSRF inválido', [
                'aluno_id' => $_SESSION['user_id'] ?? null,
            ]);
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'aluno') {
                $this->logJornadaError('jornada_aluno salvarResumo: acesso negado', [
                    'user_id' => $user['id'] ?? null,
                    'user_tipo' => $user['tipo'] ?? null,
                ]);
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }
            
            $aulaId = $_POST['aula_id'] ?? null;
            $resumoTexto = trim($_POST['resumo'] ?? '');
            
            if (empty($aulaId) || empty($resumoTexto)) {
                $this->logJornadaError('jornada_aluno salvarResumo: aula ou resumo não enviados', [
                    'aluno_id' => $user['id'],
                    'tem_aula_id' => !empty($aulaId),
                    'resumo_length' => strlen($resumoTexto),
                ]);
                throw new Exception('Aula e resumo são obrigatórios');
            }
            
            // Busca dados do aluno
            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            
            // Busca dados da aula (checagem de turma em PHP por compatibilidade MariaDB)
            $aula = $this->db->fetch(
                "SELECT ja.id, ja.resumo_oficial, j.titulo as jornada_titulo, jm.nome as materia_nome, j.status as jornada_status, j.turma_id, j.estrutura
                 FROM jornadas_aulas ja
                 JOIN jornadas j ON ja.jornada_id = j.id
                 LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id
                 WHERE ja.id = :aula_id AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status = 'finalizada' OR j.status IS NULL OR j.status = '') AND j.status != 'pausada'",
                ['aula_id' => $aulaId]
            );
            if ($aula && !$this->jornadaPermiteAluno($aula, (int) ($aluno['id'] ?? 0))) {
                $aula = null;
            }
            if (!$aula) {
                $this->logJornadaError('jornada_aluno salvarResumo: aula não encontrada ou jornada inativa', [
                    'aluno_id' => $aluno['id'],
                    'aula_id' => $aulaId,
                ]);
                throw new Exception('Aula não encontrada ou jornada não está ativa');
            }
            
            $aulaId = (int) $aula['id'];
            
            // Busca resumo oficial do professor
            $resumoOficial = $aula['resumo_oficial'] ?? '';
            
            // Enfileira análise por IA (assíncrona) e atualiza progresso imediatamente
            require_once __DIR__ . '/../../Services/AIJobService.php';
            $jobId = \App\Services\AIJobService::enqueue(
                'comparar_resumo',
                [
                    'aluno_id'       => (int) $aluno['id'],
                    'aula_id'        => $aulaId,
                    'resumo_texto'   => $resumoTexto,
                    'resumo_oficial' => $resumoOficial,
                    'materia'        => $aula['materia_nome'] ?? '',
                ],
                (int) $aluno['id'],
                'aluno'
            );

            // Atualiza progresso do aluno sem bloquear na IA
            $this->atualizarProgressoAluno($aluno['id'], $aulaId, 'resumo');

            $this->json([
                'success' => true,
                'message' => 'Resumo salvo! A análise por IA será concluída em instantes.',
                'job_id'  => $jobId,
            ]);
            
        } catch (Exception $e) {
            $this->logJornadaError('jornada_aluno salvarResumo: exceção', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'aluno_id' => $user['id'] ?? null,
                'aula_id' => $aulaId ?? null,
            ]);
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Envia dúvida do aluno
     */
    public function enviarDuvida()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->logJornadaError('jornada_aluno enviarDuvida: token CSRF inválido', [
                'aluno_id' => $_SESSION['user_id'] ?? null,
            ]);
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'aluno') {
                $this->logJornadaError('jornada_aluno enviarDuvida: acesso negado', [
                    'user_id' => $user['id'] ?? null,
                    'user_tipo' => $user['tipo'] ?? null,
                ]);
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }
            
            $aulaId = $_POST['aula_id'] ?? null;
            $duvidaTexto = trim($_POST['duvida'] ?? '');
            
            if (empty($aulaId) || empty($duvidaTexto)) {
                $this->logJornadaError('jornada_aluno enviarDuvida: aula ou dúvida não enviados', [
                    'aluno_id' => $user['id'],
                    'tem_aula_id' => !empty($aulaId),
                    'duvida_length' => strlen($duvidaTexto),
                ]);
                throw new Exception('Aula e dúvida são obrigatórios');
            }
            
            // Busca dados do aluno
            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            
            // Busca dados da jornada (checagem de turma em PHP por compatibilidade MariaDB)
            $jornada = $this->db->fetch(
                "SELECT {$this->colunasSqlJornadasPermiteAluno('j')} FROM jornadas j
                 JOIN jornadas_aulas ja ON j.id = ja.jornada_id
                 WHERE ja.id = :aula_id AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status = 'finalizada' OR j.status IS NULL OR j.status = '') AND j.status != 'pausada'",
                ['aula_id' => $aulaId]
            );
            if ($jornada && !$this->jornadaPermiteAluno($jornada, (int) ($aluno['id'] ?? 0))) {
                $jornada = null;
            }
            if (!$jornada) {
                $this->logJornadaError('jornada_aluno enviarDuvida: aula não encontrada ou jornada inativa', [
                    'aluno_id' => $aluno['id'],
                    'aula_id' => $aulaId,
                ]);
                throw new Exception('Aula não encontrada ou jornada não está ativa');
            }
            
            // Insere dúvida
            $duvidaCol = $this->getJornadasDuvidaColumnName();
            $duvidaId = $this->db->insert(
                "INSERT INTO jornadas_duvidas (jornada_id, aula_id, aluno_id, {$duvidaCol}, status) 
                 VALUES (:jornada_id, :aula_id, :aluno_id, :duvida, 'pendente')",
                [
                    'jornada_id' => $jornada['id'],
                    'aula_id' => $aulaId,
                    'aluno_id' => $aluno['id'],
                    'duvida' => $duvidaTexto
                ]
            );
            
            $this->json(['success' => true, 'message' => 'Dúvida enviada com sucesso', 'duvida_id' => $duvidaId]);
            
        } catch (Exception $e) {
            $this->logJornadaError('jornada_aluno enviarDuvida: exceção', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'aluno_id' => $user['id'] ?? null,
                'aula_id' => $aulaId ?? null,
            ]);
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Envia mensagem do aluno para o professor
     */
    public function enviarMensagem()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->logJornadaError('jornada_aluno enviarMensagem: token CSRF inválido', [
                'aluno_id' => $_SESSION['user_id'] ?? null,
            ]);
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'aluno') {
                $this->logJornadaError('jornada_aluno enviarMensagem: acesso negado', [
                    'user_id' => $user['id'] ?? null,
                    'user_tipo' => $user['tipo'] ?? null,
                ]);
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }
            
            $jornadaId = $_POST['jornada_id'] ?? null;
            $mensagemTexto = $_POST['mensagem'] ?? '';
            // Remove todos os espaços extras: início, fim e múltiplos espaços no meio
            $mensagemTexto = preg_replace('/\s+/u', ' ', $mensagemTexto);
            $mensagemTexto = trim($mensagemTexto);
            // Remove espaços não quebráveis e outros caracteres invisíveis
            $mensagemTexto = str_replace(["\xC2\xA0", "\xE2\x80\x8B"], ' ', $mensagemTexto);
            $mensagemTexto = trim($mensagemTexto);
            
            if (empty($jornadaId) || empty($mensagemTexto)) {
                $this->logJornadaError('jornada_aluno enviarMensagem: jornada ou mensagem não enviados', [
                    'aluno_id' => $user['id'],
                    'tem_jornada_id' => !empty($jornadaId),
                    'mensagem_length' => strlen($mensagemTexto),
                ]);
                throw new Exception('Jornada e mensagem são obrigatórios');
            }
            
            // Busca dados do aluno
            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            
            // Busca dados da jornada (checagem de turma em PHP por compatibilidade MariaDB)
            $jornada = $this->db->fetch(
                "SELECT {$this->colunasSqlJornadasMensagem('j')} FROM jornadas j WHERE j.id = :jornada_id AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status = 'finalizada' OR j.status IS NULL OR j.status = '') AND j.status != 'pausada'",
                ['jornada_id' => $jornadaId]
            );
            if ($jornada && !$this->jornadaPermiteAluno($jornada, (int) ($aluno['id'] ?? 0))) {
                $jornada = null;
            }
            if (!$jornada) {
                $this->logJornadaError('jornada_aluno enviarMensagem: jornada não encontrada ou inativa', [
                    'aluno_id' => $aluno['id'],
                    'jornada_id' => $jornadaId,
                ]);
                throw new Exception('Jornada não encontrada ou não está ativa');
            }
            
            // Insere mensagem
            $mensagemId = $this->db->insert(
                "INSERT INTO jornadas_mensagens 
                 (jornada_id, aluno_id, professor_id, remetente_tipo, remetente_id, mensagem, lida) 
                 VALUES (:jornada_id, :aluno_id, :professor_id, 'aluno', :remetente_id, :mensagem, 0)",
                [
                    'jornada_id' => $jornadaId,
                    'aluno_id' => $aluno['id'],
                    'professor_id' => $jornada['professor_id'],
                    'remetente_id' => $aluno['id'],
                    'mensagem' => $mensagemTexto
                ]
            );
            
            // Processa anexos se houver
            $anexosIds = [];
            if (!empty($_FILES['anexos'])) {
                $anexosIds = $this->processarAnexos($mensagemId, $_FILES['anexos']);
            }
            
            // Busca a mensagem criada com todos os dados
            $mensagemCriada = $this->db->fetch(
                "SELECT {$this->colunasSqlJornadasMensagens('jm')}, 
                        a.nome as aluno_nome,
                        p.nome as professor_nome
                 FROM jornadas_mensagens jm
                 LEFT JOIN alunos a ON jm.aluno_id = a.id
                 LEFT JOIN professores p ON jm.professor_id = p.id
                 WHERE jm.id = :mensagem_id",
                ['mensagem_id' => $mensagemId]
            );
            
            // Busca anexos da mensagem
            if ($mensagemCriada) {
                $mensagemCriada['anexos'] = $this->db->fetchAll(
                    "SELECT {$this->colunasJornadasMensagensAnexos()} FROM jornadas_mensagens_anexos 
                     WHERE mensagem_id = :mensagem_id
                     ORDER BY created_at ASC",
                    ['mensagem_id' => $mensagemId]
                );
            }
            
            $this->json([
                'success' => true, 
                'message' => 'Mensagem enviada com sucesso', 
                'mensagem_id' => $mensagemId,
                'mensagem' => $mensagemCriada,
                'anexos' => $anexosIds
            ]);
            
        } catch (Exception $e) {
            $this->logJornadaError('jornada_aluno enviarMensagem: exceção', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'aluno_id' => $user['id'] ?? null,
                'jornada_id' => $jornadaId ?? null,
            ]);
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Processa anexos de uma mensagem
     */
    private function processarAnexos($mensagemId, $arquivos)
    {
        $anexosIds = [];
        $uploadDir = __DIR__ . '/../../public/uploads/mensagens/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Permite múltiplos arquivos
        $files = [];
        if (is_array($arquivos['name'])) {
            foreach ($arquivos['name'] as $key => $name) {
                if (!empty($name) && $arquivos['error'][$key] === UPLOAD_ERR_OK) {
                    $files[] = [
                        'name' => $name,
                        'type' => $arquivos['type'][$key],
                        'tmp_name' => $arquivos['tmp_name'][$key],
                        'size' => $arquivos['size'][$key],
                        'error' => $arquivos['error'][$key]
                    ];
                }
            }
        } else {
            if (!empty($arquivos['name']) && $arquivos['error'] === UPLOAD_ERR_OK) {
                $files[] = $arquivos;
            }
        }
        
        foreach ($files as $file) {
            // Validar tipo de arquivo
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 
                           'application/pdf', 'application/msword', 
                           'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if (!in_array($file['type'], $allowedTypes)) {
                continue; // Pula arquivos não permitidos
            }
            
            // Validar tamanho (max 10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                continue; // Pula arquivos muito grandes
            }
            
            // Gerar nome único
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'msg_' . $mensagemId . '_' . time() . '_' . uniqid() . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            // Mover arquivo
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Salvar anexo no banco
                $anexoId = $this->db->insert(
                    "INSERT INTO jornadas_mensagens_anexos 
                     (mensagem_id, nome_arquivo, caminho_arquivo, tipo_arquivo, tamanho_arquivo) 
                     VALUES (:mensagem_id, :nome_arquivo, :caminho_arquivo, :tipo_arquivo, :tamanho_arquivo)",
                    [
                        'mensagem_id' => $mensagemId,
                        'nome_arquivo' => $file['name'],
                        'caminho_arquivo' => '/public/uploads/mensagens/' . $filename,
                        'tipo_arquivo' => $file['type'],
                        'tamanho_arquivo' => $file['size']
                    ]
                );
                $anexosIds[] = $anexoId;
            }
        }
        
        return $anexosIds;
    }
    
    /**
     * Busca novas mensagens (para atualização em tempo real)
     */
    public function buscarMensagens()
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                $this->json(['error' => 'Acesso negado'], 403);
            }
            
            $jornadaId = $_GET['jornada_id'] ?? null;
            $ultimaMensagemId = $_GET['ultima_mensagem_id'] ?? 0;
            
            if (empty($jornadaId)) {
                throw new Exception('Jornada é obrigatória');
            }
            
            // Busca dados do aluno
            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            
            // Busca mensagens novas
            $mensagens = $this->db->fetchAll(
                "SELECT {$this->colunasSqlJornadasMensagens('jm')}, 
                        a.nome as aluno_nome,
                        p.nome as professor_nome
                 FROM jornadas_mensagens jm
                 LEFT JOIN alunos a ON jm.aluno_id = a.id
                 LEFT JOIN professores p ON jm.professor_id = p.id
                 WHERE jm.jornada_id = :jornada_id 
                   AND jm.aluno_id = :aluno_id
                   AND jm.id > :ultima_mensagem_id
                 ORDER BY jm.created_at ASC",
                [
                    'jornada_id' => $jornadaId,
                    'aluno_id' => $aluno['id'],
                    'ultima_mensagem_id' => $ultimaMensagemId
                ]
            );
            
            // Busca anexos das mensagens
            foreach ($mensagens as &$mensagem) {
                $mensagem['anexos'] = $this->db->fetchAll(
                    "SELECT {$this->colunasJornadasMensagensAnexos()} FROM jornadas_mensagens_anexos 
                     WHERE mensagem_id = :mensagem_id
                     ORDER BY created_at ASC",
                    ['mensagem_id' => $mensagem['id']]
                );
            }
            
            $this->json(['success' => true, 'mensagens' => $mensagens]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Conclui aula para o aluno
     */
    public function concluirAula()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->logJornadaError('jornada_aluno concluirAula: token CSRF inválido', [
                'aluno_id' => $_SESSION['user_id'] ?? null,
            ]);
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'aluno') {
                $this->logJornadaError('jornada_aluno concluirAula: acesso negado', [
                    'user_id' => $user['id'] ?? null,
                    'user_tipo' => $user['tipo'] ?? null,
                ]);
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }
            
            $aulaId = $_POST['aula_id'] ?? null;
            $tempoGasto = (int)($_POST['tempo_gasto'] ?? 0);
            
            if (empty($aulaId)) {
                $this->logJornadaError('jornada_aluno concluirAula: ID da aula não enviado', [
                    'aluno_id' => $user['id'],
                ]);
                throw new Exception('ID da aula é obrigatório');
            }
            
            // Busca dados do aluno
            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            
            // Verifica se a aula pertence a uma jornada ativa (checagem de turma em PHP por compatibilidade MariaDB)
            $aula = $this->db->fetch(
                "SELECT ja.id, j.status as jornada_status, j.turma_id, j.estrutura
                 FROM jornadas_aulas ja
                 JOIN jornadas j ON ja.jornada_id = j.id
                 WHERE ja.id = :aula_id AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status = 'finalizada' OR j.status IS NULL OR j.status = '') AND j.status != 'pausada'",
                ['aula_id' => $aulaId]
            );
            if ($aula && !$this->jornadaPermiteAluno($aula, (int) ($aluno['id'] ?? 0))) {
                $aula = null;
            }
            if (!$aula) {
                $this->logJornadaError('jornada_aluno concluirAula: aula não encontrada ou jornada inativa', [
                    'aluno_id' => $aluno['id'],
                    'aula_id' => $aulaId,
                ]);
                throw new Exception('Aula não encontrada ou jornada não está ativa');
            }
            
            // Atualiza progresso do aluno
            $this->atualizarProgressoAluno($aluno['id'], $aulaId, 'aula_concluida', $tempoGasto);
            
            $this->json(['success' => true, 'message' => 'Aula concluída com sucesso']);
            
        } catch (Exception $e) {
            $this->logJornadaError('jornada_aluno concluirAula: exceção', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'aluno_id' => $user['id'] ?? null,
                'aula_id' => $aulaId ?? null,
            ]);
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Atualiza progresso do aluno
     */
    private function atualizarProgressoAluno($alunoId, $aulaId, $tipoAtividade, $tempoGasto = 0)
    {
        // Verifica se já existe progresso para esta atividade
        $progressoExistente = $this->db->fetch(
            "SELECT {$this->colunasJornadasProgressoAlunos()} FROM jornadas_progresso_alunos 
             WHERE aluno_id = :aluno_id AND aula_id = :aula_id AND atividade_tipo = :tipo",
            [
                'aluno_id' => $alunoId,
                'aula_id' => $aulaId,
                'tipo' => $tipoAtividade
            ]
        );
        
        if ($progressoExistente) {
            // Atualiza progresso existente
            $this->db->update(
                "UPDATE jornadas_progresso_alunos 
                 SET status = 'concluido', tempo_gasto = :tempo, updated_at = NOW() 
                 WHERE id = :id",
                [
                    'tempo' => $tempoGasto,
                    'id' => $progressoExistente['id']
                ]
            );
        } else {
            // Insere novo progresso
            $this->db->insert(
                "INSERT INTO jornadas_progresso_alunos (aluno_id, aula_id, atividade_tipo, status, tempo_gasto) 
                 VALUES (:aluno_id, :aula_id, :tipo, 'concluido', :tempo)",
                [
                    'aluno_id' => $alunoId,
                    'aula_id' => $aulaId,
                    'tipo' => $tipoAtividade,
                    'tempo' => $tempoGasto
                ]
            );
        }
    }

    /**
     * Retoma a jornada do aluno - redireciona para a próxima aula não concluída
     */
    public function retomarJornada($jornada_id)
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                $this->redirectToCorrectDashboard($user['tipo']);
                return;
            }
            
            // Busca dados do aluno
            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            
            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }
            
            // Verifica se a jornada pertence à turma do aluno (checagem em PHP por compatibilidade MariaDB)
            $jornada = $this->db->fetch(
                "SELECT {$this->colunasSqlJornadasPermiteAluno('j')} FROM jornadas j WHERE j.id = :jornada_id AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status = 'finalizada' OR j.status IS NULL OR j.status = '') AND j.status != 'pausada'",
                ['jornada_id' => $jornada_id]
            );
            if ($jornada && !$this->jornadaPermiteAluno($jornada, (int) ($aluno['id'] ?? 0))) {
                $jornada = null;
            }
            if (!$jornada) {
                $_SESSION['error_message'] = 'Jornada não encontrada ou não está ativa';
                $this->redirect('/jornadas');
                return;
            }
            
            // Busca a próxima aula não concluída
            $proximaAula = $this->db->fetch(
                "SELECT ja.id
                 FROM jornadas_aulas ja
                 LEFT JOIN jornadas_progresso_alunos jpa ON jpa.aula_id = ja.id 
                     AND jpa.aluno_id = :aluno_id 
                     AND jpa.atividade_tipo = 'aula' 
                     AND jpa.status = 'concluido'
                 WHERE ja.jornada_id = :jornada_id 
                     AND ja.status = 'ativa'
                     AND jpa.id IS NULL
                 ORDER BY ja.ordem ASC
                 LIMIT 1",
                [
                    'jornada_id' => $jornada_id,
                    'aluno_id' => $aluno['id']
                ]
            );
            
            if ($proximaAula) {
                // Redireciona para a próxima aula não concluída
                $this->redirect('/jornadas/' . $jornada_id . '/aula/' . $proximaAula['id']);
            } else {
                // Todas as aulas foram concluídas, redireciona para a jornada
                $_SESSION['success_message'] = 'Parabéns! Você concluiu todas as aulas desta jornada!';
                $this->redirect('/jornadas/' . $jornada_id);
            }
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao retomar jornada: ' . $e->getMessage();
            $this->redirect('/jornadas');
        }
    }
    
    /**
     * Finaliza uma etapa (módulo) da jornada
     */
    public function finalizarEtapa()
    {
        $modulo_id = null;
        $tipo = '';
        try {
            $user = $this->authManager->getUser();
            $modulo_id = $_POST['modulo_id'] ?? null;
            $tipo = $_POST['tipo'] ?? '';
            $tempoGastoPost = isset($_POST['tempo_gasto']) ? (int)$_POST['tempo_gasto'] : null;
            $isPreview = isset($_POST['preview']) && (int)$_POST['preview'] === 1;

            // Modo preview: professor visualizando jornada como aluno — não persiste no banco
            if ($user && $user['tipo'] === 'professor' && $isPreview && $modulo_id) {
                $modulo = $this->db->fetch(
                    "SELECT m.id, j.professor_id FROM jornadas_modulos m
                     JOIN jornadas j ON m.jornada_id = j.id
                     WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                    ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
                );
                if ($modulo) {
                    $this->json(['success' => true, 'message' => 'Etapa finalizada com sucesso! (preview)']);
                    return;
                }
            }

            if (!$user || $user['tipo'] !== 'aluno') {
                $this->logJornadaError('jornada_aluno finalizarEtapa: acesso negado (não é aluno ou não logado)', [
                    'user_id' => $user['id'] ?? null,
                    'user_tipo' => $user['tipo'] ?? null,
                ]);
                throw new Exception('Acesso negado');
            }
            
            if (!$modulo_id) {
                $this->logJornadaError('jornada_aluno finalizarEtapa: ID do módulo não enviado', [
                    'aluno_id' => $user['id'],
                    'post_keys' => array_keys($_POST),
                ]);
                throw new Exception('ID do módulo é obrigatório');
            }
            
            // Verifica se o módulo existe, pertence a uma jornada ativa; checagem de turma em PHP (turmas_selecionadas)
            $modulo = $this->db->fetch(
                "SELECT {$this->colunasSqlJornadasModulosAcesso('m')}, j.turma_id, j.id as jornada_id, j.status as jornada_status, j.estrutura
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id 
                     AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status = 'finalizada' OR j.status IS NULL OR j.status = '')
                     AND j.status != 'pausada'",
                ['modulo_id' => $modulo_id]
            );
            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            if (!$modulo || !$aluno || !$this->jornadaPermiteAluno($modulo, (int) ($aluno['id'] ?? 0))) {
                $this->logJornadaError('jornada_aluno finalizarEtapa: módulo não encontrado ou não autorizado', [
                    'user_id' => $user['id'],
                    'aluno_id' => $aluno['id'] ?? null,
                    'modulo_id' => $modulo_id,
                    'tem_modulo' => (bool)$modulo,
                    'tem_aluno' => (bool)$aluno,
                ]);
                throw new Exception('Módulo não encontrado, não autorizado ou jornada não está ativa');
            }
            $alunoId = (int) $aluno['id'];
            
            // Tempo gasto: usa valor enviado pelo front (segundos) ou calcula pelo início da etapa
            $tempoGasto = 0;
            if ($tempoGastoPost !== null && $tempoGastoPost >= 0) {
                $tempoGasto = $tempoGastoPost;
            } else {
                $inicioEtapa = $this->db->fetch(
                    "SELECT MIN(created_at) as inicio FROM jornadas_progresso_alunos 
                     WHERE modulo_id = :modulo_id AND aluno_id = :aluno_id",
                    [
                        'modulo_id' => $modulo_id,
                        'aluno_id' => $alunoId
                    ]
                );
                if ($inicioEtapa && $inicioEtapa['inicio']) {
                    $inicioTimestamp = strtotime($inicioEtapa['inicio']);
                    $tempoGasto = max(0, time() - $inicioTimestamp);
                }
            }
            
            // Verifica se já existe progresso
            $progresso = $this->db->fetch(
                "SELECT {$this->colunasJornadasProgressoAlunos()} FROM jornadas_progresso_alunos 
                 WHERE modulo_id = :modulo_id AND aluno_id = :aluno_id AND atividade_tipo = 'modulo'",
                [
                    'modulo_id' => $modulo_id,
                    'aluno_id' => $alunoId
                ]
            );
            
            if ($progresso) {
                // Atualiza progresso existente com tempo gasto
                $tempoAnterior = (int)($progresso['tempo_gasto'] ?? 0);
                $tempoTotal = $tempoAnterior + $tempoGasto;
                
                $this->db->update(
                    "UPDATE jornadas_progresso_alunos 
                     SET status = 'concluido', tempo_gasto = :tempo, data_conclusao = NOW(), updated_at = NOW()
                     WHERE id = :progresso_id",
                    [
                        'tempo' => $tempoTotal,
                        'progresso_id' => $progresso['id']
                    ]
                );
            } else {
                // Cria novo progresso com tempo gasto
                $this->db->insert(
                    "INSERT INTO jornadas_progresso_alunos 
                     (jornada_id, modulo_id, aluno_id, atividade_tipo, status, tempo_gasto, data_conclusao, created_at) 
                     VALUES (:jornada_id, :modulo_id, :aluno_id, 'modulo', 'concluido', :tempo, NOW(), NOW())",
                    [
                        'jornada_id' => $modulo['jornada_id'],
                        'modulo_id' => $modulo_id,
                        'aluno_id' => $alunoId,
                        'tempo' => $tempoGasto
                    ]
                );
            }
            
            $this->json(['success' => true, 'message' => 'Etapa finalizada com sucesso!']);
            
        } catch (\Throwable $e) {
            $this->logJornadaError('jornada_aluno finalizarEtapa: erro ao finalizar etapa', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'modulo_id' => $modulo_id ?? null,
                'tipo' => $tipo ?? null,
                'user_id' => $user['id'] ?? null,
                'aluno_id' => $alunoId ?? null,
            ]);
            $status = $this->isTransientConnectionError($e) ? 503 : 400;
            $message = $this->isTransientConnectionError($e)
                ? 'Instabilidade temporária de conexão. Tente novamente em alguns segundos.'
                : $e->getMessage();
            $this->json(['success' => false, 'error' => $message], $status);
        }
    }
    
    /**
     * Envia resumo do aluno para um módulo
     */
    public function enviarResumo()
    {
        // Garante que sempre retorna JSON
        header('Content-Type: application/json');
        
        if (!class_exists('Logger')) {
            require_once __DIR__ . '/../Core/Logger.php';
        }
        
        try {
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'aluno') {
                $this->logJornadaError('jornada_aluno enviarResumo: acesso negado', [
                    'user_id' => $user['id'] ?? null,
                    'user_tipo' => $user['tipo'] ?? null,
                ]);
                $this->json(['success' => false, 'error' => 'Acesso negado'], 403);
                return;
            }
            
            $modulo_id = $_POST['modulo_id'] ?? null;
            $jornada_id = $_POST['jornada_id'] ?? null;
            $resumo = $_POST['resumo'] ?? '';
            
            if (!$modulo_id || $resumo === '') {
                $this->logJornadaError('jornada_aluno enviarResumo: módulo ou resumo não enviados', [
                    'aluno_id' => $user['id'],
                    'tem_modulo_id' => !empty($modulo_id),
                    'resumo_length' => strlen($resumo),
                ]);
                throw new Exception('Módulo e resumo são obrigatórios');
            }
            
            // Mesma lógica de finalizarEtapa/salvarTempoEtapa: busca módulo sem filtrar por turma no SQL,
            // depois usa jornadaPermiteTurma() (turma principal OU estrutura.turmas_selecionadas)
            $modulo = $this->db->fetch(
                "SELECT {$this->colunasSqlJornadasModulosAcesso('m')}, j.turma_id, j.id as jornada_id, j.status as jornada_status, j.estrutura
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id
                     AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status = 'finalizada' OR j.status IS NULL OR j.status = '')
                     AND j.status != 'pausada'",
                ['modulo_id' => $modulo_id]
            );
            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            if (!$aluno) {
                $this->logJornadaError('jornada_aluno enviarResumo: aluno não encontrado no BD', [
                    'user_id' => $user['id'],
                    'modulo_id' => $modulo_id,
                ]);
                throw new Exception('Aluno não encontrado. Faça login novamente.');
            }
            $alunoId = (int) $aluno['id'];
            if (!$modulo || !$this->jornadaPermiteAluno($modulo, (int) ($aluno['id'] ?? 0))) {
                if (!$modulo) {
                    $this->logJornadaError('jornada_aluno enviarResumo: módulo não encontrado', [
                        'user_id' => $user['id'],
                        'aluno_id' => $alunoId,
                        'modulo_id' => $modulo_id,
                    ]);
                    throw new Exception('Módulo não encontrado');
                }
                if (($modulo['jornada_status'] ?? '') === 'pausada') {
                    $this->logJornadaError('jornada_aluno enviarResumo: jornada pausada', [
                        'user_id' => $user['id'],
                        'aluno_id' => $alunoId,
                        'modulo_id' => $modulo_id,
                        'jornada_id' => $modulo['jornada_id'] ?? null,
                    ]);
                    throw new Exception('Esta jornada está pausada. O envio de resumos está temporariamente indisponível.');
                }
                $this->logJornadaError('jornada_aluno enviarResumo: turma não autorizada', [
                    'user_id' => $user['id'],
                    'aluno_id' => $alunoId,
                    'turma_id' => $aluno['turma_id'] ?? null,
                    'modulo_id' => $modulo_id,
                    'jornada_id' => $modulo['jornada_id'] ?? null,
                ]);
                throw new Exception('Você não tem acesso a esta jornada (verifique se está na turma correta).');
            }
            
            $jornadaIdParaInsert = !empty($jornada_id) ? $jornada_id : ($modulo['jornada_id'] ?? null);
            if (empty($jornadaIdParaInsert)) {
                $this->logJornadaError('jornada_aluno enviarResumo: jornada_id ausente (POST e módulo)', [
                    'user_id' => $user['id'],
                    'aluno_id' => $alunoId,
                    'modulo_id' => $modulo_id,
                ]);
                throw new Exception('Dados da jornada incompletos. Recarregue a página e tente novamente.');
            }
            
            // Verifica se já existe resumo
            $resumoExistente = $this->db->fetch(
                "SELECT {$this->colunasJornadasResumosAlunos()} FROM jornadas_resumos_alunos 
                 WHERE aluno_id = :aluno_id AND modulo_id = :modulo_id",
                [
                    'aluno_id' => $alunoId,
                    'modulo_id' => $modulo_id
                ]
            );
            
            if ($resumoExistente) {
                // Atualiza resumo existente
                $this->db->update(
                    "UPDATE jornadas_resumos_alunos 
                     SET resumo_aluno = :resumo, updated_at = NOW()
                     WHERE id = :resumo_id",
                    [
                        'resumo' => $resumo,
                        'resumo_id' => $resumoExistente['id']
                    ]
                );
            } else {
                // Resumo por módulo (não por aula) — usa jornada_id do módulo se POST vier vazio
                $this->db->insert(
                    "INSERT INTO jornadas_resumos_alunos 
                     (jornada_id, modulo_id, aluno_id, resumo_aluno, created_at) 
                     VALUES (:jornada_id, :modulo_id, :aluno_id, :resumo, NOW())",
                    [
                        'jornada_id' => $jornadaIdParaInsert,
                        'modulo_id' => $modulo_id,
                        'aluno_id' => $alunoId,
                        'resumo' => $resumo
                    ]
                );
            }
            
            // Calcula tempo gasto na etapa (tempo desde que começou até agora)
            $inicioEtapa = $this->db->fetch(
                "SELECT MIN(created_at) as inicio FROM jornadas_progresso_alunos 
                 WHERE modulo_id = :modulo_id AND aluno_id = :aluno_id",
                [
                    'modulo_id' => $modulo_id,
                    'aluno_id' => $alunoId
                ]
            );
            
            $tempoGasto = 0;
            if ($inicioEtapa && $inicioEtapa['inicio']) {
                $inicioTimestamp = strtotime($inicioEtapa['inicio']);
                $agoraTimestamp = time();
                $tempoGasto = max(0, $agoraTimestamp - $inicioTimestamp);
            }
            
            // Marca o módulo como concluído
            $progresso = $this->db->fetch(
                "SELECT {$this->colunasJornadasProgressoAlunos()} FROM jornadas_progresso_alunos 
                 WHERE modulo_id = :modulo_id AND aluno_id = :aluno_id AND atividade_tipo = 'modulo'",
                [
                    'modulo_id' => $modulo_id,
                    'aluno_id' => $alunoId
                ]
            );
            
            if (!$progresso) {
                $this->db->insert(
                    "INSERT INTO jornadas_progresso_alunos 
                     (jornada_id, modulo_id, aluno_id, atividade_tipo, status, tempo_gasto, data_conclusao, created_at) 
                     VALUES (:jornada_id, :modulo_id, :aluno_id, 'modulo', 'concluido', :tempo, NOW(), NOW())",
                    [
                        'jornada_id' => $jornadaIdParaInsert,
                        'modulo_id' => $modulo_id,
                        'aluno_id' => $alunoId,
                        'tempo' => $tempoGasto
                    ]
                );
            } else {
                // Atualiza tempo se já existe progresso
                $tempoAnterior = (int)($progresso['tempo_gasto'] ?? 0);
                $tempoTotal = $tempoAnterior + $tempoGasto;
                
                $this->db->update(
                    "UPDATE jornadas_progresso_alunos 
                     SET status = 'concluido', tempo_gasto = :tempo, data_conclusao = NOW(), updated_at = NOW()
                     WHERE id = :progresso_id",
                    [
                        'tempo' => $tempoTotal,
                        'progresso_id' => $progresso['id']
                    ]
                );
            }
            
            $this->json(['success' => true, 'message' => 'Resumo enviado com sucesso!']);
            
        } catch (Exception $e) {
            $user = $this->authManager->getUser();
            $this->logJornadaError('jornada_aluno enviarResumo: exceção ao enviar resumo', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user['id'] ?? null,
                'aluno_id' => $alunoId ?? null,
                'modulo_id' => $modulo_id ?? null,
                'jornada_id' => $jornada_id ?? null,
            ]);
            $errorResponse = [
                'success' => false,
                'error' => $e->getMessage()
            ];
            if (defined('DEBUG') && DEBUG) {
                $errorResponse['debug'] = [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'post_data' => $_POST,
                    'user' => $user ? ['id' => $user['id'], 'tipo' => $user['tipo']] : null
                ];
            }
            $this->json($errorResponse, 400);
        } catch (Throwable $e) {
            $user = $this->authManager->getUser();
            $this->logJornadaError('jornada_aluno enviarResumo: erro fatal', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user['id'] ?? null,
                'aluno_id' => $alunoId ?? null,
            ]);
            $errorResponse = [
                'success' => false,
                'error' => 'Erro fatal: ' . $e->getMessage()
            ];
            if (defined('DEBUG') && DEBUG) {
                $errorResponse['debug'] = [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ];
            }
            $this->json($errorResponse, 500);
        }
    }
    
    /**
     * Salva o tempo gasto em uma etapa (módulo)
     */
    public function salvarTempoEtapa()
    {
        // Garante que sempre retorna JSON
        header('Content-Type: application/json');
        
        try {
            $user = $this->authManager->getUser();
            $modulo_id = $_POST['modulo_id'] ?? null;
            $acao = $_POST['acao'] ?? 'salvar';
            $tempo_gasto = (int)($_POST['tempo_gasto'] ?? 0);
            $isPreview = isset($_POST['preview']) && (int)$_POST['preview'] === 1;

            // Modo preview: professor visualizando jornada como aluno — não persiste no banco
            if ($user && $user['tipo'] === 'professor' && $isPreview && $modulo_id) {
                $modulo = $this->db->fetch(
                    "SELECT m.id, m.jornada_id, j.professor_id FROM jornadas_modulos m
                     JOIN jornadas j ON m.jornada_id = j.id
                     WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                    ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
                );
                if ($modulo) {
                    $this->json([
                        'success' => true,
                        'tempo_inicio' => date('Y-m-d H:i:s'),
                        'message' => $acao !== 'iniciar' ? 'Tempo salvo (preview)' : null,
                    ]);
                    return;
                }
            }

            if (!$user || $user['tipo'] !== 'aluno') {
                $this->logJornadaError('jornada_aluno salvarTempoEtapa: acesso negado', [
                    'user_id' => $user['id'] ?? null,
                    'user_tipo' => $user['tipo'] ?? null,
                ]);
                $this->json(['success' => false, 'error' => 'Acesso negado'], 403);
                return;
            }
            
            if (!$modulo_id) {
                throw new Exception('ID do módulo é obrigatório');
            }
            
            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }
            
            // Mesma lógica de finalizarEtapa: busca módulo por id e jornada ativa; turma permitida em PHP (turmas_selecionadas)
            $modulo = $this->db->fetch(
                "SELECT {$this->colunasSqlJornadasModulosAcesso('m')}, j.turma_id, j.id as jornada_id, j.status as jornada_status, j.estrutura
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id 
                     AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status = 'finalizada' OR j.status IS NULL OR j.status = '')
                     AND j.status != 'pausada'",
                ['modulo_id' => $modulo_id]
            );
            if (!$modulo || !$this->jornadaPermiteAluno($modulo, (int) ($aluno['id'] ?? 0))) {
                throw new Exception('Módulo não encontrado ou não autorizado');
            }
            $alunoId = (int) $aluno['id'];
            
            if ($acao === 'iniciar') {
                // Verifica se já existe progresso (início da etapa)
                $progresso = $this->db->fetch(
                    "SELECT {$this->colunasJornadasProgressoAlunos()} FROM jornadas_progresso_alunos 
                     WHERE modulo_id = :modulo_id AND aluno_id = :aluno_id AND atividade_tipo = 'modulo'",
                    [
                        'modulo_id' => $modulo_id,
                        'aluno_id' => $alunoId
                    ]
                );
                
                if (!$progresso) {
                    // Cria registro de início da etapa
                    $this->db->insert(
                        "INSERT INTO jornadas_progresso_alunos 
                         (jornada_id, modulo_id, aluno_id, atividade_tipo, status, created_at, updated_at) 
                         VALUES (:jornada_id, :modulo_id, :aluno_id, 'modulo', 'em_andamento', NOW(), NOW())",
                        [
                            'jornada_id' => $modulo['jornada_id'],
                            'modulo_id' => $modulo_id,
                            'aluno_id' => $alunoId
                        ]
                    );
                }
                
                // Retorna o tempo de início
                $progressoAtual = $this->db->fetch(
                    "SELECT created_at FROM jornadas_progresso_alunos 
                     WHERE modulo_id = :modulo_id AND aluno_id = :aluno_id AND atividade_tipo = 'modulo'
                     ORDER BY created_at ASC LIMIT 1",
                    [
                        'modulo_id' => $modulo_id,
                        'aluno_id' => $alunoId
                    ]
                );
                
                $this->json([
                    'success' => true,
                    'tempo_inicio' => $progressoAtual ? $progressoAtual['created_at'] : date('Y-m-d H:i:s')
                ]);
                
            } else {
                // Salva o tempo gasto
                $progresso = $this->db->fetch(
                    "SELECT {$this->colunasJornadasProgressoAlunos()} FROM jornadas_progresso_alunos 
                     WHERE modulo_id = :modulo_id AND aluno_id = :aluno_id AND atividade_tipo = 'modulo'",
                    [
                        'modulo_id' => $modulo_id,
                        'aluno_id' => $alunoId
                    ]
                );
                
                if ($progresso) {
                    // Atualiza tempo gasto (acumula se já existir)
                    $tempoAnterior = (int)($progresso['tempo_gasto'] ?? 0);
                    $tempoTotal = $tempoAnterior + $tempo_gasto;
                    
                    $this->db->update(
                        "UPDATE jornadas_progresso_alunos 
                         SET tempo_gasto = :tempo, updated_at = NOW()
                         WHERE id = :progresso_id",
                        [
                            'tempo' => $tempoTotal,
                            'progresso_id' => $progresso['id']
                        ]
                    );
                } else {
                    // Cria novo registro com tempo
                    $this->db->insert(
                        "INSERT INTO jornadas_progresso_alunos 
                         (jornada_id, modulo_id, aluno_id, atividade_tipo, status, tempo_gasto, created_at, updated_at) 
                         VALUES (:jornada_id, :modulo_id, :aluno_id, 'modulo', 'em_andamento', :tempo, NOW(), NOW())",
                        [
                            'jornada_id' => $modulo['jornada_id'],
                            'modulo_id' => $modulo_id,
                            'aluno_id' => $alunoId,
                            'tempo' => $tempo_gasto
                        ]
                    );
                }
                
                $this->json(['success' => true, 'message' => 'Tempo salvo com sucesso']);
            }
            
        } catch (Exception $e) {
            $this->logJornadaError('jornada_aluno salvarTempoEtapa: erro', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'modulo_id' => $modulo_id ?? null,
                'user_id' => $user['id'] ?? null,
                'aluno_id' => $alunoId ?? null,
            ]);
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Exibe exercícios do módulo um por vez
     */
    public function executarExerciciosModulo($jornada_id, $modulo_id, $exercicio_index = null)
    {
        try {
            $user = $this->authManager->getUser();
            $preview = false;

            if ($user['tipo'] === 'professor' && isset($_GET['preview']) && (int)$_GET['preview'] === 1) {
                $jornadaPreview = $this->db->fetch(
                    "SELECT {$this->colunasSqlJornadasPreview('j')} FROM jornadas j WHERE j.id = :id AND j.professor_id = :prof_id",
                    ['id' => $jornada_id, 'prof_id' => $user['id']]
                );
                if ($jornadaPreview) {
                    $preview = true;
                    $aluno = [
                        'id' => 0,
                        'turma_id' => $jornadaPreview['turma_id'] ?? 0,
                        'turma_nome' => 'Preview',
                        'nome' => 'Preview'
                    ];
                }
            }

            if (!$preview) {
                if ($user['tipo'] !== 'aluno') {
                    throw new Exception('Acesso negado');
                }
                $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
                if (!$aluno) {
                    throw new Exception('Aluno não encontrado');
                }
            }

            // Busca o módulo (em preview: qualquer status da jornada do professor; senão: ativa/em_andamento)
            if ($preview) {
                $modulo = $this->db->fetch(
                    "SELECT {$this->colunasSqlJornadasModulosAcesso('m')}, j.titulo as jornada_titulo, j.turma_id, j.estrutura
                     FROM jornadas_modulos m
                     JOIN jornadas j ON m.jornada_id = j.id
                     WHERE m.id = :modulo_id AND m.jornada_id = :jornada_id AND j.professor_id = :prof_id AND (j.ativo = 1 OR j.ativo IS NULL)",
                    ['modulo_id' => $modulo_id, 'jornada_id' => $jornada_id, 'prof_id' => $user['id']]
                );
            } else {
                $modulo = $this->db->fetch(
                    "SELECT {$this->colunasSqlJornadasModulosAcesso('m')}, j.titulo as jornada_titulo, j.turma_id, j.estrutura, j.status as jornada_status
                     FROM jornadas_modulos m
                     JOIN jornadas j ON m.jornada_id = j.id
                     WHERE m.id = :modulo_id 
                         AND m.jornada_id = :jornada_id 
                         AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status = 'finalizada' OR j.status IS NULL OR j.status = '')
                         AND j.status != 'pausada'",
                    ['modulo_id' => $modulo_id, 'jornada_id' => $jornada_id]
                );
                if ($modulo && !$this->jornadaPermiteAluno($modulo, (int) ($aluno['id'] ?? 0))) {
                    $modulo = null;
                }
            }
            if (!$modulo) {
                $this->logJornadaError('executarExerciciosModulo: módulo não encontrado ou jornada inativa', [
                    'jornada_id' => $jornada_id,
                    'modulo_id' => $modulo_id,
                    'aluno_id' => $aluno ? $aluno['id'] : null,
                ]);
                $_SESSION['error_message'] = 'Módulo não encontrado ou jornada não está ativa';
                $this->redirect($preview ? '/professor/jornadas/' . (int)$jornada_id : '/jornadas');
                return;
            }
            
            // Busca todos os exercícios do módulo (com resposta salva para pré-seleção e checagem de não respondidas)
            $exercicios = $this->db->fetchAll(
                "SELECT {$this->colunasSqlJornadasModulosExerciciosShow('me')}, 
                        jpa.status as progresso_status,
                        jpa.pontuacao,
                        jpa.resposta as resposta_json
                 FROM jornadas_modulos_exercicios me
                 LEFT JOIN jornadas_progresso_alunos jpa ON jpa.exercicio_modulo_id = me.id AND jpa.aluno_id = :aluno_id AND jpa.atividade_tipo = 'exercicio_modulo'
                 WHERE me.modulo_id = :modulo_id AND me.status = 'publicado'
                 ORDER BY me.ordem ASC",
                [
                    'modulo_id' => $modulo_id,
                    'aluno_id' => $aluno['id']
                ]
            );
            foreach ($exercicios as &$ex) {
                if (!empty($ex['enunciado']) && is_string($ex['enunciado']) && stripos($ex['enunciado'], 'data:image/') !== false) {
                    $enunciadoMigrado = $this->migrarBase64EnunciadoParaMedia((int)$ex['id'], (string)$ex['enunciado'], (int)$aluno['id']);
                    if ($enunciadoMigrado !== null) {
                        $ex['enunciado'] = $enunciadoMigrado;
                    }
                }
                $ex['resposta_salva'] = '';
                if (!empty($ex['resposta_json'])) {
                    $dec = json_decode($ex['resposta_json'], true);
                    if (isset($dec['resposta'])) {
                        $ex['resposta_salva'] = (string) $dec['resposta'];
                    }
                }
                unset($ex['resposta_json']);
            }
            unset($ex);

            if (empty($exercicios)) {
                $this->logJornadaError('executarExerciciosModulo: nenhum exercício publicado no módulo', [
                    'jornada_id' => $jornada_id,
                    'modulo_id' => $modulo_id,
                    'aluno_id' => $aluno['id'],
                ]);
                $_SESSION['error_message'] = 'Nenhum exercício disponível';
                $this->redirect($preview ? '/professor/jornadas/' . (int)$jornada_id : '/jornadas/' . $jornada_id);
                return;
            }
            
            // Determina qual exercício exibir
            $exercicio_atual_index = 0;
            if ($exercicio_index !== null && is_numeric($exercicio_index)) {
                $exercicio_atual_index = (int)$exercicio_index;
                if ($exercicio_atual_index < 0 || $exercicio_atual_index >= count($exercicios)) {
                    $exercicio_atual_index = 0;
                }
            } else {
                // Busca o primeiro não concluído
                foreach ($exercicios as $index => $ex) {
                    if ($ex['progresso_status'] !== 'concluido') {
                        $exercicio_atual_index = $index;
                        break;
                    }
                }
            }
            
            $exercicio_atual = $exercicios[$exercicio_atual_index];
            
            // Decodifica questões JSON
            $questoes = null;
            if (!empty($exercicio_atual['questoes_json'])) {
                // Se já for um array, usa diretamente
                if (is_array($exercicio_atual['questoes_json'])) {
                    $questoes = $exercicio_atual['questoes_json'];
                } else {
                    // Tenta decodificar o JSON (evitar stripslashes no fluxo principal para não quebrar \u e \)
                    $jsonString = trim($exercicio_atual['questoes_json']);
                    
                    if (function_exists('mb_check_encoding') && function_exists('mb_convert_encoding') && !mb_check_encoding($jsonString, 'UTF-8')) {
                        $jsonString = mb_convert_encoding($jsonString, 'UTF-8', 'auto');
                    }
                    
                    // 1ª tentativa: decode direto (JSON vindo do banco costuma estar válido)
                    $questoes = json_decode($jsonString, true, 512, JSON_UNESCAPED_UNICODE | JSON_BIGINT_AS_STRING);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $questoes = null;
                        // 2ª tentativa: JSON escapado como string (ex.: começa e termina com aspas)
                        if (preg_match('/^"(.*)"$/s', $jsonString, $matches)) {
                            $inner = $matches[1];
                            $inner = str_replace('\"', '"', $inner);
                            $inner = str_replace('\\\\', '\\', $inner);
                            $questoes = json_decode($inner, true, 512, JSON_UNESCAPED_UNICODE);
                        }
                        if (($questoes === null || json_last_error() !== JSON_ERROR_NONE) && !empty($jsonString)) {
                            error_log("Erro ao decodificar JSON do exercício {$exercicio_atual['id']}: " . json_last_error_msg());
                        }
                    }
                }
                
                // Se conseguiu decodificar, normaliza o formato
                if ($questoes && is_array($questoes)) {
                    // Normaliza o formato das opções (aceita tanto 'texto' quanto 'text')
                    if (isset($questoes['opcoes']) && is_array($questoes['opcoes'])) {
                        foreach ($questoes['opcoes'] as &$opcao) {
                            // Se tiver 'text' mas não 'texto', copia para 'texto'
                            if (isset($opcao['text']) && !isset($opcao['texto'])) {
                                $opcao['texto'] = $opcao['text'];
                            }
                            // Se tiver 'texto' mas não 'text', copia para 'text'
                            if (isset($opcao['texto']) && !isset($opcao['text'])) {
                                $opcao['text'] = $opcao['texto'];
                            }
                            // Garante que 'correta' seja boolean
                            if (isset($opcao['correta'])) {
                                $opcao['correta'] = (bool)$opcao['correta'];
                            }
                            // Garante que 'letra' seja string
                            if (isset($opcao['letra'])) {
                                $opcao['letra'] = (string)$opcao['letra'];
                            }
                        }
                        unset($opcao); // Remove referência
                    } elseif (isset($questoes[0]) && isset($questoes[0]['letra'])) {
                        // Formato alternativo: array direto de opções (sem chave 'opcoes')
                        $questoes = ['opcoes' => $questoes];
                        // Normaliza as opções
                        foreach ($questoes['opcoes'] as &$opcao) {
                            if (isset($opcao['text']) && !isset($opcao['texto'])) {
                                $opcao['texto'] = $opcao['text'];
                            }
                            if (isset($opcao['texto']) && !isset($opcao['text'])) {
                                $opcao['text'] = $opcao['texto'];
                            }
                            if (isset($opcao['correta'])) {
                                $opcao['correta'] = (bool)$opcao['correta'];
                            }
                            if (isset($opcao['letra'])) {
                                $opcao['letra'] = (string)$opcao['letra'];
                            }
                        }
                        unset($opcao);
                    }
                } else {
                    // Se não conseguiu decodificar ou não é array, define como null
                    error_log("Questões não é array após decodificação. Tipo: " . gettype($questoes) . ", Valor: " . print_r($questoes, true));
                    $questoes = null;
                }
            }
            
            // GARANTE que $questoes seja sempre array ou null antes de passar para a view
            if ($questoes !== null && !is_array($questoes)) {
                if (is_string($questoes)) {
                    // Remove espaços e quebras de linha
                    $questoes = trim($questoes);
                    // Remove possíveis escapes duplos
                    $questoes = stripslashes($questoes);
                    $questoes = json_decode($questoes, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log("Erro ao decodificar questoes no controller: " . json_last_error_msg());
                        error_log("JSON string: " . $questoes);
                        $questoes = null;
                    }
                } else {
                    error_log("Questoes não é array nem string. Tipo: " . gettype($questoes));
                    $questoes = null;
                }
            }
            
            // Última tentativa: decode sem manipulação agressiva (sem stripslashes)
            if (empty($questoes) && !empty($exercicio_atual['questoes_json'])) {
                $jsonString = trim($exercicio_atual['questoes_json']);
                if (preg_match('/^"(.*)"$/s', $jsonString, $matches)) {
                    $jsonString = $matches[1];
                    $jsonString = str_replace('\"', '"', $jsonString);
                    $jsonString = str_replace('\\\\', '\\', $jsonString);
                }
                $questoes = json_decode($jsonString, true, 512, JSON_UNESCAPED_UNICODE);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $questoes = null;
                }
            }
            
            // Libera gabarito SOMENTE se TODOS os módulos da jornada foram concluídos pelo aluno
            $liberar_gabarito = $this->alunoPodeVerGabaritoJornada((int) $aluno['id'], (int) $jornada_id);
            
            // Se a jornada não foi concluída, não enviar resposta_correta/gabarito ao cliente (segurança)
            if (!$liberar_gabarito) {
                foreach ($exercicios as &$ex) {
                    unset($ex['resposta_correta'], $ex['gabarito']);
                    // Também limpa o campo 'correta' das opções dentro do questoes_json
                    if (!empty($ex['questoes_json'])) {
                        $questoesTemp = json_decode($ex['questoes_json'], true);
                        if (is_array($questoesTemp)) {
                            $this->stripCorretaFromQuestoesJsonForCliente($questoesTemp);
                            $ex['questoes_json'] = json_encode($questoesTemp);
                        }
                    }
                }
                unset($ex);
                $exercicio_atual = $exercicios[$exercicio_atual_index];
                
                // Limpa também o campo 'correta' da variável $questoes que vai para a view
                if (is_array($questoes)) {
                    $this->stripCorretaFromQuestoesJsonForCliente($questoes);
                }
            }
            
            require_once __DIR__ . '/../../Core/LayoutHelper.php';
            $chatHabilitado = LayoutHelper::isModuleEnabled('chat');
            $tudinhaExplicaModal = !$preview && !empty($liberar_gabarito) && $chatHabilitado;

            $data = [
                'title' => 'Exercícios - ' . $modulo['titulo'] . ' - EducaTudo',
                'user' => $user,
                'aluno' => $aluno,
                'jornada' => [
                    'id' => $jornada_id,
                    'titulo' => $modulo['jornada_titulo']
                ],
                'modulo' => $modulo,
                'exercicios' => $exercicios,
                'exercicio_atual' => $exercicio_atual,
                'exercicio_atual_index' => $exercicio_atual_index,
                'questoes' => $questoes, // Agora garantidamente array ou null
                'total_exercicios' => count($exercicios),
                'current_page' => 'journeys',
                'liberar_gabarito' => $liberar_gabarito,
                'preview' => $preview,
                'chat_habilitado' => $chatHabilitado,
                'ia_nome_tudinha' => LayoutHelper::getIaName(),
                'tudinha_explica_exercicio_modal' => $tudinhaExplicaModal,
                'csrf_token' => $this->generateCsrfToken(),
            ];

            if ($preview) {
                $_SESSION['journey_preview'] = 1;
            }

            $this->viewWithLayout('student', 'student/journeys/executar-exercicios', $data);

        } catch (Exception $e) {
            $this->logJornadaError('executarExerciciosModulo: exceção ao carregar exercícios', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'jornada_id' => $jornada_id ?? null,
                'modulo_id' => $modulo_id ?? null,
            ]);
            $_SESSION['error_message'] = 'Erro ao carregar exercícios: ' . $e->getMessage();
            $redirectUrl = (isset($preview) && $preview && !empty($jornada_id)) ? '/professor/jornadas/' . (int)$jornada_id : '/jornadas';
            $this->redirect($redirectUrl);
        }
    }

    /**
     * Migra imagens base64 do enunciado para media/serve e persiste no exercício.
     * Retorna enunciado atualizado quando houve migração, ou null quando não houve alteração.
     */
    private function migrarBase64EnunciadoParaMedia(int $exercicioId, string $enunciadoHtml, int $alunoId): ?string
    {
        if ($exercicioId <= 0 || trim($enunciadoHtml) === '' || stripos($enunciadoHtml, 'data:image/') === false) {
            return null;
        }

        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new \MediaStorageService($this->config);

        $alterou = false;
        $htmlNovo = preg_replace_callback('/<img\b([^>]*)\bsrc=["\']([^"\']+)["\']([^>]*)>/i', function ($m) use ($media, $alunoId, &$alterou) {
            $before = $m[1] ?? '';
            $srcRaw = html_entity_decode((string)($m[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $after = $m[3] ?? '';
            $src = trim($srcRaw);

            if (stripos($src, 'data:image/') !== 0) {
                return $m[0];
            }
            if (!preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,(.+)$/is', $src, $mm)) {
                return $m[0];
            }

            $ext = strtolower($mm[1]);
            if ($ext === 'jpeg') $ext = 'jpg';
            $payload = preg_replace('/\s+/', '', (string)$mm[2]);
            $bin = base64_decode($payload, true);
            if ($bin === false || $bin === '' || strlen($bin) > 10 * 1024 * 1024) {
                return $m[0];
            }

            $filename = 'exercicio_migrado_' . $alunoId . '_' . time() . '_' . uniqid('', true) . '.' . $ext;
            $key = \MediaStorageService::userKey('student', $alunoId, $filename);
            $tmpPath = sys_get_temp_dir() . '/' . $filename;
            $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);

            $ok = false;
            if (@file_put_contents($tmpPath, $bin) !== false) {
                $ok = $media->put('jornadas_exercicios', $key, $tmpPath, $mime);
            }
            @unlink($tmpPath);
            if (!$ok) {
                return $m[0];
            }

            $url = $this->buildStableJourneyExerciseMediaUrl($key);
            if ($url === '') {
                return $m[0];
            }
            $alterou = true;
            return '<img' . $before . 'src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $after . '>';
        }, $enunciadoHtml);

        if (!$alterou || !is_string($htmlNovo) || trim($htmlNovo) === '') {
            return null;
        }

        try {
            $this->db->update(
                "UPDATE jornadas_modulos_exercicios SET enunciado = :enunciado, updated_at = NOW() WHERE id = :id",
                ['enunciado' => $htmlNovo, 'id' => $exercicioId]
            );
        } catch (\Throwable $e) {
            $this->logJornadaError('migrarBase64EnunciadoParaMedia: falha ao persistir enunciado migrado', [
                'exercicio_id' => $exercicioId,
                'exception' => $e->getMessage(),
            ]);
        }

        return $htmlNovo;
    }

    private function buildStableJourneyExerciseMediaUrl(string $key): string
    {
        $baseUrl = rtrim((string) (defined('URL') ? URL : ''), '/');
        $slug = trim((string) ($this->config['tenant']['slug'] ?? ''));
        if ($slug === '') {
            $slug = trim((string) ($this->config['school']['code'] ?? ''));
        }

        $url = $baseUrl . '/media/serve?type=jornadas_exercicios&key=' . rawurlencode(trim($key, '/'));
        if ($slug !== '' && $slug !== 'default') {
            $url .= '&tenant=' . rawurlencode($slug);
        }

        return $url;
    }

    /**
     * Remove flags de gabarito (correta) de questoes_json antes de enviar ao cliente.
     * Aceita formato { opcoes: [...] }, lista de questões ou misturas; ignora escalares no meio do array.
     */
    private function stripCorretaFromQuestoesJsonForCliente(array &$questoesPayload): void
    {
        unset($questoesPayload['correta']);
        if (isset($questoesPayload['opcoes']) && is_array($questoesPayload['opcoes'])) {
            foreach ($questoesPayload['opcoes'] as &$opc) {
                if (is_array($opc)) {
                    unset($opc['correta']);
                }
            }
            unset($opc);
        }
        foreach ($questoesPayload as $k => &$item) {
            if ($k === 'opcoes' || !is_array($item)) {
                continue;
            }
            unset($item['correta']);
            if (isset($item['opcoes']) && is_array($item['opcoes'])) {
                foreach ($item['opcoes'] as &$opc) {
                    if (is_array($opc)) {
                        unset($opc['correta']);
                    }
                }
                unset($opc);
            }
        }
        unset($item);
    }

    /**
     * Gera explicação didática (Tudinha) para o exercício do módulo — só após gabarito liberado (jornada concluída).
     */
    public function explicarExercicioTudinha()
    {
        try {
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->json(['success' => false, 'error' => 'Sessão expirada. Atualize a página e tente novamente.'], 400);
                return;
            }

            $user = $this->authManager->getUser();
            if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
                $this->json(['success' => false, 'error' => 'Acesso negado'], 403);
                return;
            }

            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            if (!$aluno) {
                $this->json(['success' => false, 'error' => 'Aluno não encontrado'], 404);
                return;
            }

            $exercicioId = (int) ($_POST['exercicio_id'] ?? 0);
            if ($exercicioId <= 0) {
                $this->json(['success' => false, 'error' => 'Exercício inválido'], 400);
                return;
            }

            $row = $this->db->fetch(
                "SELECT me.id, me.titulo, me.tipo, me.enunciado, me.gabarito, me.resposta_correta, me.questoes_json,
                        m.id AS modulo_id, m.jornada_id, j.turma_id, j.estrutura
                 FROM jornadas_modulos_exercicios me
                 INNER JOIN jornadas_modulos m ON m.id = me.modulo_id
                 INNER JOIN jornadas j ON j.id = m.jornada_id
                 WHERE me.id = :eid AND me.status = 'publicado'",
                ['eid' => $exercicioId]
            );

            if (!$row || !$this->jornadaPermiteAluno($row, (int) ($aluno['id'] ?? 0))) {
                $this->json(['success' => false, 'error' => 'Exercício não encontrado'], 404);
                return;
            }

            $jornadaId = (int) $row['jornada_id'];
            if (!$this->alunoPodeVerGabaritoJornada((int) $aluno['id'], $jornadaId)) {
                $this->json(['success' => false, 'error' => 'A explicação fica disponível após você concluir todos os módulos da jornada.'], 403);
                return;
            }

            require_once __DIR__ . '/../../Core/LayoutHelper.php';
            if (!LayoutHelper::isModuleEnabled('chat')) {
                $this->json(['success' => false, 'error' => 'O chat não está disponível na sua escola.'], 403);
                return;
            }

            $prog = $this->db->fetch(
                "SELECT resposta FROM jornadas_progresso_alunos
                 WHERE aluno_id = :aid AND exercicio_modulo_id = :eid AND atividade_tipo = 'exercicio_modulo'
                 ORDER BY id DESC LIMIT 1",
                ['aid' => $aluno['id'], 'eid' => $exercicioId]
            );
            $respAluno = '';
            if ($prog && !empty($prog['resposta'])) {
                $dj = json_decode($prog['resposta'], true);
                $respAluno = isset($dj['resposta']) ? (string) $dj['resposta'] : (string) $prog['resposta'];
            }

            $enunciadoRaw = (string) ($row['enunciado'] ?? '');
            $enunciadoTxt = trim(html_entity_decode(strip_tags($enunciadoRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $enunciadoTxt = preg_replace('/\s+/u', ' ', $enunciadoTxt);
            if ($enunciadoTxt === '' && $enunciadoRaw !== '') {
                $enunciadoTxt = trim(preg_replace('/\s+/u', ' ', html_entity_decode($enunciadoRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            }
            if (strlen($enunciadoTxt) > 6000) {
                $enunciadoTxt = substr($enunciadoTxt, 0, 6000) . '…';
            }
            $gabaritoTxt = trim((string) ($row['gabarito'] ?? ''));
            if (strlen($gabaritoTxt) > 4000) {
                $gabaritoTxt = substr($gabaritoTxt, 0, 4000) . '…';
            }

            $alternativasTxt = $this->montarTextoAlternativasExercicioJornada($row['questoes_json'] ?? null);

            $fonteHash = $this->hashFonteExplicacaoTudinhaExercicioJornada(
                $exercicioId,
                $row,
                $enunciadoTxt,
                $gabaritoTxt,
                $alternativasTxt,
                $respAluno
            );

            $cachedRow = null;
            try {
                $cachedRow = $this->db->fetch(
                    'SELECT explicacao_html, fonte_hash FROM jornadas_tudinha_explicacao_exercicio
                     WHERE aluno_id = :aid AND exercicio_modulo_id = :eid LIMIT 1',
                    ['aid' => $aluno['id'], 'eid' => $exercicioId]
                );
            } catch (\Throwable $e) {
                $cachedRow = null;
            }
            if (
                is_array($cachedRow)
                && !empty($cachedRow['explicacao_html'])
                && isset($cachedRow['fonte_hash'])
                && hash_equals((string) $cachedRow['fonte_hash'], $fonteHash)
            ) {
                $this->json(['success' => true, 'html' => $cachedRow['explicacao_html'], 'cached' => true]);
                return;
            }

            $prompt = "Contexto: questão de jornada escolar (EducaTudo). O aluno já concluiu a jornada e está revisando o gabarito.\n\n";
            $prompt .= 'Título interno do exercício: ' . ($row['titulo'] ?? '') . "\n";
            $prompt .= 'Tipo: ' . ($row['tipo'] ?? '') . "\n\n";
            $prompt .= "ENUNCIADO (extraído em texto):\n" . ($enunciadoTxt !== '' ? $enunciadoTxt : '(vazio — infira só o que as alternativas permitirem)') . "\n\n";
            if ($alternativasTxt !== '') {
                $prompt .= $alternativasTxt . "\n";
            }
            if ($respAluno !== '') {
                $prompt .= 'Letra/opção que o ALUNO marcou: ' . $respAluno . "\n";
            }
            if (!empty($row['resposta_correta'])) {
                $prompt .= 'Letra/opção CORRETA (oficial): ' . $row['resposta_correta'] . "\n";
            }
            if ($gabaritoTxt !== '') {
                $prompt .= "Comentário/gabarito do professor (texto):\n" . $gabaritoTxt . "\n";
            }
            $prompt .= "\nSua tarefa: escrever a explicação DIDÁTICA desta questão em português do Brasil.\n";
            $prompt .= "- Cite o conteúdo das alternativas (trechos), comparando a que o aluno marcou com a correta.\n";
            $prompt .= "- Explique o raciocínio no tema do enunciado; não use parágrafos só com conselhos genéricos de estudo.\n";
            $prompt .= "- Se alguma informação essencial faltar no texto acima, mencione isso em uma frase — não invente fatos externos.\n";
            $prompt .= "- Resposta final: SOMENTE HTML válido (<h2>, <p>, <strong>, <ul>, <li>), sem Markdown.\n";

            $systemExercicio = "Você é um tutor brasileiro explicando UMA questão objetiva já respondida.\n\n"
                . "OBRIGATÓRIO:\n"
                . "- Baseie-se no enunciado, nas alternativas (texto) e no gabarito/resposta oficial fornecidos na mensagem do usuário.\n"
                . "- Nomeie alternativas pela letra E pelo conteúdo (ex.: cite trechos do texto de cada opção relevante).\n"
                . "- Se o aluno errou, explique por que a opção dele não fecha com o enunciado e por que a correta fecha.\n"
                . "- Proibido preencher a resposta principalmente com dicas genéricas (\"leia com atenção\", \"faça resumos\") sem ligar ao conteúdo desta questão.\n"
                . "- Saída: HTML válido, sem Markdown.\n";

            require_once __DIR__ . '/../../Services/TudinhaService.php';
            $tudinhaService = new \App\Services\TudinhaService();
            $html = $tudinhaService->gerarExplicacaoSemHistorico(
                $prompt,
                $systemExercicio,
                'gpt-4o',
                0.0,
                2800
            );

            try {
                $this->db->query(
                    'INSERT INTO jornadas_tudinha_explicacao_exercicio
                     (aluno_id, exercicio_modulo_id, fonte_hash, explicacao_html)
                     VALUES (:aid, :eid, :fh, :exhtml)
                     ON DUPLICATE KEY UPDATE fonte_hash = VALUES(fonte_hash), explicacao_html = VALUES(explicacao_html), updated_at = CURRENT_TIMESTAMP',
                    [
                        'aid' => (int) $aluno['id'],
                        'eid' => $exercicioId,
                        'fh' => $fonteHash,
                        'exhtml' => $html,
                    ]
                );
            } catch (\Throwable $e) {
                $this->logJornadaError('explicarExercicioTudinha: cache não gravado', [
                    'exception' => $e->getMessage(),
                ]);
            }

            $this->json(['success' => true, 'html' => $html, 'cached' => false]);
        } catch (\Throwable $e) {
            $this->logJornadaError('explicarExercicioTudinha: falha', [
                'exception' => $e->getMessage(),
            ]);
            $this->json(['success' => false, 'error' => 'Não foi possível gerar a explicação agora. Tente novamente em instantes.'], 500);
        }
    }

    /**
     * Hash estável do enunciado, alternativas, gabarito e resposta do aluno — muda só quando a fonte muda.
     */
    private function hashFonteExplicacaoTudinhaExercicioJornada(
        int $exercicioModuloId,
        array $row,
        string $enunciadoTxt,
        string $gabaritoTxt,
        string $alternativasTxt,
        string $respAluno
    ): string {
        $parts = [
            (string) $exercicioModuloId,
            (string) ($row['titulo'] ?? ''),
            (string) ($row['tipo'] ?? ''),
            (string) ($row['resposta_correta'] ?? ''),
            (string) ($row['questoes_json'] ?? ''),
            $enunciadoTxt,
            $gabaritoTxt,
            $alternativasTxt,
            $respAluno,
        ];

        return hash('sha256', implode("\x1e", $parts));
    }
    
    /**
     * Responde exercício de um módulo
     */
    public function responderExercicioModulo()
    {
        $user = null;
        $exercicio_id = null;
        $modulo_id = null;
        try {
            $user = $this->authManager->getUser();

            if (!$user) {
                $this->logJornadaError('jornada_aluno responderExercicioModulo: usuário não autenticado', []);
                throw new Exception('Usuário não autenticado');
            }

            $exercicio_id = $_POST['exercicio_id'] ?? null;
            $modulo_id = $_POST['modulo_id'] ?? null;

            // Modo preview: professor visualizando jornada — retorna sucesso sem gravar no banco
            if ($user['tipo'] === 'professor' && !empty($_SESSION['journey_preview'])) {
                if (!$exercicio_id) {
                    $this->json(['success' => false, 'error' => 'ID do exercício é obrigatório']);
                    return;
                }
                $exercicioPreview = $this->db->fetch(
                    "SELECT me.id FROM jornadas_modulos_exercicios me
                     JOIN jornadas_modulos m ON me.modulo_id = m.id
                     JOIN jornadas j ON m.jornada_id = j.id
                     WHERE me.id = :exercicio_id AND j.professor_id = :prof_id",
                    ['exercicio_id' => $exercicio_id, 'prof_id' => $user['id']]
                );
                if ($exercicioPreview) {
                    $this->json([
                        'success' => true,
                        'message' => 'Preview: resposta não salva.',
                        'correto' => false,
                        'modulo_concluido' => false
                    ]);
                    return;
                }
            }

            if ($user['tipo'] !== 'aluno') {
                $this->logJornadaError('jornada_aluno responderExercicioModulo: acesso negado', [
                    'user_id' => $user['id'],
                    'user_tipo' => $user['tipo'],
                ]);
                throw new Exception('Acesso negado');
            }

            $resposta = $_POST['resposta'] ?? '';
            
            if (!$exercicio_id) {
                $this->logJornadaError('jornada_aluno responderExercicioModulo: ID do exercício não enviado', [
                    'user_id' => $user['id'],
                    'post_keys' => array_keys($_POST),
                ]);
                throw new Exception('ID do exercício é obrigatório');
            }

            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            if (!$aluno) {
                $this->logJornadaError('jornada_aluno responderExercicioModulo: aluno não encontrado no BD', [
                    'user_id' => $user['id'],
                    'exercicio_id' => $exercicio_id,
                ]);
                throw new Exception('Aluno não encontrado. Faça login novamente.');
            }
            $alunoId = (int) $aluno['id'];
            
            // Converte resposta para string
            $resposta = $resposta === null ? '' : (string)$resposta;
            
            // Busca o exercício e o módulo (necessário para saber o tipo e validar resposta vazia)
            $exercicio = $this->db->fetch(
                "SELECT {$this->colunasSqlJornadasModulosExerciciosResponder('me')},
                        m.jornada_id,
                        m.id as modulo_id,
                        j.turma_id,
                        j.status as jornada_status,
                        j.estrutura
                 FROM jornadas_modulos_exercicios me
                 JOIN jornadas_modulos m ON me.modulo_id = m.id
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE me.id = :exercicio_id",
                ['exercicio_id' => $exercicio_id]
            );
            
            if (!$exercicio) {
                $this->logJornadaError('jornada_aluno responderExercicioModulo: exercício não encontrado', [
                    'user_id' => $user['id'],
                    'aluno_id' => $alunoId,
                    'exercicio_id' => $exercicio_id,
                ]);
                throw new Exception('Exercício não encontrado');
            }

            if (!$this->jornadaPermiteAluno($exercicio, $alunoId)) {
                $this->logJornadaError('jornada_aluno responderExercicioModulo: jornada não autorizada para aluno', [
                    'user_id' => $user['id'],
                    'aluno_id' => $alunoId,
                    'turma_id' => $aluno['turma_id'] ?? null,
                    'exercicio_id' => $exercicio_id,
                    'modulo_id' => $exercicio['modulo_id'] ?? null,
                    'jornada_id' => $exercicio['jornada_id'] ?? null,
                ]);
                throw new Exception('Você não tem acesso a esta jornada (verifique se está na turma correta).');
            }
            
            $moduloIdExercicio = (int) $exercicio['modulo_id'];
            if ($modulo_id && (int) $modulo_id !== $moduloIdExercicio) {
                $this->logJornadaError('jornada_aluno responderExercicioModulo: módulo divergente do exercício', [
                    'user_id' => $user['id'],
                    'aluno_id' => $alunoId,
                    'exercicio_id' => $exercicio_id,
                    'modulo_id_post' => $modulo_id,
                    'modulo_id_exercicio' => $moduloIdExercicio,
                ]);
                throw new Exception('Dados do exercício inconsistentes. Recarregue a página e tente novamente.');
            }
            $modulo_id = $moduloIdExercicio;
            
            // Verifica se a jornada está pausada (única restrição real)
            if ($exercicio['jornada_status'] === 'pausada') {
                $this->logJornadaError('jornada_aluno responderExercicioModulo: jornada pausada', [
                    'user_id' => $user['id'],
                    'aluno_id' => $alunoId,
                    'exercicio_id' => $exercicio_id,
                    'modulo_id' => $modulo_id,
                ]);
                throw new Exception('A jornada está pausada. Não é possível responder exercícios no momento.');
            }
            
            // Resposta vazia só é permitida para dissertativa (rascunho em branco)
            if (($resposta === '' || trim($resposta) === '') && ($exercicio['tipo'] ?? '') !== 'dissertativa') {
                $this->logJornadaError('jornada_aluno responderExercicioModulo: resposta obrigatória não enviada', [
                    'user_id' => $user['id'],
                    'aluno_id' => $alunoId,
                    'exercicio_id' => $exercicio_id,
                    'tipo_exercicio' => $exercicio['tipo'] ?? null,
                ]);
                throw new Exception('Resposta é obrigatória');
            }
            
            // Verifica resposta correta
            $correto = false;
            if ($exercicio['tipo'] === 'alternativas' && !empty($exercicio['questoes_json'])) {
                $questoes = is_string($exercicio['questoes_json'])
                    ? json_decode($exercicio['questoes_json'], true)
                    : $exercicio['questoes_json'];
                if (is_string($questoes)) {
                    $questoes = json_decode($questoes, true);
                }
                $opcoes = null;
                if (is_array($questoes)) {
                    if (isset($questoes['opcoes']) && is_array($questoes['opcoes'])) {
                        $opcoes = $questoes['opcoes'];
                    } elseif (isset($questoes[0]) && is_array($questoes[0]) && isset($questoes[0]['letra'])) {
                        $opcoes = $questoes;
                    }
                }
                if ($opcoes) {
                    $respostaNorm = strtoupper(trim((string) $resposta));
                    foreach ($opcoes as $idxOpcao => $opcao) {
                        $letraOpcao = trim((string) ($opcao['letra'] ?? ''));
                        if ($letraOpcao === '') {
                            $letraOpcao = chr(65 + ((int)$idxOpcao % 26));
                        }
                        $letraNorm = strtoupper($letraOpcao);
                        $ehCorreta = !empty($opcao['correta']);
                        if ($letraNorm !== '' && $letraNorm === $respostaNorm && $ehCorreta) {
                            $correto = true;
                            break;
                        }
                    }
                }
            }
            if ($exercicio['tipo'] === 'preencher_lacuna') {
                $respostasAluno = array_values(array_filter(array_map(function($v){
                    return mb_strtolower(trim((string)$v), 'UTF-8');
                }, preg_split('/[|,]/', (string)$resposta)), function($v){ return $v !== ''; }));
                $respostasCorretas = array_values(array_filter(array_map(function($v){
                    return mb_strtolower(trim((string)$v), 'UTF-8');
                }, preg_split('/[|,]/', (string)($exercicio['resposta_correta'] ?? ''))), function($v){ return $v !== ''; }));
                if (!empty($respostasAluno) && !empty($respostasCorretas) && count($respostasAluno) === count($respostasCorretas)) {
                    $correto = true;
                    foreach ($respostasCorretas as $idx => $respCerta) {
                        if (($respostasAluno[$idx] ?? '') !== $respCerta) {
                            $correto = false;
                            break;
                        }
                    }
                } elseif (!empty($respostasAluno) && !empty($respostasCorretas) && count($respostasCorretas) === 1) {
                    // Compatibilidade: 1 lacuna
                    $correto = (($respostasAluno[0] ?? '') === $respostasCorretas[0]);
                } elseif (count($respostasAluno) === 1 && count($respostasCorretas) > 1) {
                    // Usuário não preencheu todas as lacunas
                    $correto = false;
                } else {
                    $correto = false;
                }
            }
            
            // Salva progresso do exercício
            $progresso = $this->db->fetch(
                "SELECT {$this->colunasJornadasProgressoAlunos()} FROM jornadas_progresso_alunos 
                 WHERE exercicio_modulo_id = :exercicio_id AND aluno_id = :aluno_id AND atividade_tipo = 'exercicio_modulo'",
                [
                    'exercicio_id' => $exercicio_id,
                    'aluno_id' => $alunoId
                ]
            );
            
            $pontuacaoBruta = (float) ($exercicio['pontuacao'] ?? 0);
            // Listagem e boletim usam (pontuacao > 0) como proxy de acerto. Exercício com peso 0 ou NULL
            // gravava 0 mesmo quando correto — relatório mostrava 100% erro com UI "Correta".
            $pontuacao = $correto ? ($pontuacaoBruta > 0 ? $pontuacaoBruta : 1.0) : 0.0;

            // Salva a resposta como JSON
            $respostaJson = json_encode(['resposta' => $resposta]);
            
            $respostaAnterior = null;
            if ($progresso && !empty($progresso['resposta'])) {
                $respostaAnteriorJson = json_decode($progresso['resposta'], true);
                if (is_array($respostaAnteriorJson) && isset($respostaAnteriorJson['resposta'])) {
                    $respostaAnterior = (string) $respostaAnteriorJson['resposta'];
                }
            }

            if ($progresso) {
                $this->db->update(
                    "UPDATE jornadas_progresso_alunos 
                     SET status = 'concluido', pontuacao = :pontuacao, resposta = :resposta, data_conclusao = NOW(), updated_at = NOW()
                     WHERE id = :progresso_id",
                    [
                        'pontuacao' => $pontuacao,
                        'resposta' => $respostaJson,
                        'progresso_id' => $progresso['id']
                    ]
                );
            } else {
                $this->db->insert(
                    "INSERT INTO jornadas_progresso_alunos 
                     (jornada_id, modulo_id, exercicio_modulo_id, aluno_id, atividade_tipo, status, pontuacao, resposta, data_conclusao, created_at) 
                     VALUES (:jornada_id, :modulo_id, :exercicio_id, :aluno_id, 'exercicio_modulo', 'concluido', :pontuacao, :resposta, NOW(), NOW())",
                    [
                        'jornada_id' => $exercicio['jornada_id'],
                        'modulo_id' => $modulo_id,
                        'exercicio_id' => $exercicio_id,
                        'aluno_id' => $alunoId,
                        'pontuacao' => $pontuacao,
                        'resposta' => $respostaJson
                    ]
                );
            }
            
            // Verifica se todos os exercícios do módulo foram respondidos para auto-finalizar
            $moduloAutoConcluido = false;
            $contagemModulo = $this->db->fetch(
                "SELECT
                    (SELECT COUNT(*) FROM jornadas_modulos_exercicios WHERE modulo_id = :modulo_id AND status = 'publicado') AS total_pub,
                    (SELECT COUNT(*) FROM jornadas_progresso_alunos
                     WHERE modulo_id = :modulo_id2 AND aluno_id = :aluno_id AND atividade_tipo = 'exercicio_modulo' AND status = 'concluido') AS total_resp",
                [
                    'modulo_id' => $modulo_id,
                    'modulo_id2' => $modulo_id,
                    'aluno_id' => $alunoId,
                ]
            );
            if ((int) ($contagemModulo['total_pub'] ?? 0) > 0
                && (int) ($contagemModulo['total_resp'] ?? 0) >= (int) ($contagemModulo['total_pub'] ?? 0)) {
                $progressoModulo = $this->db->fetch(
                    "SELECT {$this->colunasJornadasProgressoAlunos()} FROM jornadas_progresso_alunos 
                     WHERE modulo_id = :modulo_id AND aluno_id = :aluno_id AND atividade_tipo = 'modulo'",
                    ['modulo_id' => $modulo_id, 'aluno_id' => $alunoId]
                );
                if ($progressoModulo) {
                    if ($progressoModulo['status'] !== 'concluido') {
                        $this->db->update(
                            "UPDATE jornadas_progresso_alunos SET status = 'concluido', data_conclusao = NOW(), updated_at = NOW() WHERE id = :id",
                            ['id' => $progressoModulo['id']]
                        );
                        $moduloAutoConcluido = true;
                    }
                } else {
                    $this->db->insert(
                        "INSERT INTO jornadas_progresso_alunos (jornada_id, modulo_id, aluno_id, atividade_tipo, status, data_conclusao, created_at) 
                         VALUES (:jornada_id, :modulo_id, :aluno_id, 'modulo', 'concluido', NOW(), NOW())",
                        [
                            'jornada_id' => $exercicio['jornada_id'],
                            'modulo_id' => $modulo_id,
                            'aluno_id' => $alunoId
                        ]
                    );
                    $moduloAutoConcluido = true;
                }
            }

            // Rastro final do envio da resposta (inclui acerto/erro e pontuação).
            $this->registrarAuditoriaExercicio([
                'aluno_id' => $alunoId,
                'jornada_id' => (int) $exercicio['jornada_id'],
                'modulo_id' => (int) $modulo_id,
                'exercicio_id' => (int) $exercicio_id,
                'tipo_acao' => 'resposta_enviada',
                'de_valor' => $respostaAnterior,
                'para_valor' => $resposta,
                'resposta_final' => $resposta,
                'correto' => $correto ? 1 : 0,
                'pontuacao' => $pontuacao,
                'detalhes_json' => [
                    'tipo_exercicio' => $exercicio['tipo'] ?? null,
                    'modulo_auto_concluido' => $moduloAutoConcluido ? 1 : 0,
                ],
            ]);

            if ($moduloAutoConcluido) {
                $this->invalidateJourneyListCacheForAluno($alunoId);
            }

            $this->json([
                'success' => true, 
                'message' => $correto ? 'Resposta correta! Parabéns!' : 'Resposta incorreta. Tente novamente.',
                'correto' => $correto,
                'modulo_concluido' => $moduloAutoConcluido
            ]);
            
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            $this->logJornadaError('jornada_aluno responderExercicioModulo: erro ao responder exercício', [
                'exception' => $errorMessage,
                'trace' => $e->getTraceAsString(),
                'exercicio_id' => $exercicio_id ?? null,
                'modulo_id' => $modulo_id ?? null,
                'user_id' => $user['id'] ?? null,
                'aluno_id' => $alunoId ?? null,
            ]);
            header('Content-Type: application/json');
            $status = $this->isTransientConnectionError($e) ? 503 : 400;
            $message = $this->isTransientConnectionError($e)
                ? 'Instabilidade temporária de conexão. Tente novamente em alguns segundos.'
                : $errorMessage;
            http_response_code($status);
            echo json_encode(['error' => $message, 'success' => false]);
            exit;
        }
    }

    private function isTransientConnectionError(\Throwable $e): bool
    {
        $msg = strtolower((string) $e->getMessage());
        return strpos($msg, 'sqlstate[hy000] [2002]') !== false
            || strpos($msg, 'connection timed out') !== false
            || strpos($msg, 'connection refused') !== false
            || strpos($msg, 'server has gone away') !== false
            || strpos($msg, 'lost connection') !== false;
    }

    /**
     * Registra eventos de auditoria de interação no exercício (ex.: troca de alternativa).
     */
    public function auditoriaExercicioEvento()
    {
        try {
            $user = $this->authManager->getUser();
            if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
                $this->json(['success' => false, 'error' => 'Acesso negado'], 403);
            }

            $exercicioId = (int) ($_POST['exercicio_id'] ?? 0);
            $moduloId = (int) ($_POST['modulo_id'] ?? 0);
            $tipoAcao = trim((string) ($_POST['tipo_acao'] ?? 'alternativa_trocada'));
            $deValor = isset($_POST['de_valor']) ? (string) $_POST['de_valor'] : null;
            $paraValor = isset($_POST['para_valor']) ? (string) $_POST['para_valor'] : null;

            if ($exercicioId <= 0 || $moduloId <= 0) {
                $this->json(['success' => false, 'error' => 'Dados inválidos'], 400);
            }

            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            if (!$aluno) {
                $this->json(['success' => false, 'error' => 'Aluno não encontrado'], 403);
            }
            $alunoId = (int) $aluno['id'];

            $exercicio = $this->db->fetch(
                "SELECT me.id, me.modulo_id, m.jornada_id, j.turma_id, j.status as jornada_status, j.estrutura
                 FROM jornadas_modulos_exercicios me
                 JOIN jornadas_modulos m ON me.modulo_id = m.id
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE me.id = :exercicio_id AND m.id = :modulo_id
                 LIMIT 1",
                [
                    'exercicio_id' => $exercicioId,
                    'modulo_id' => $moduloId,
                ]
            );

            if (!$exercicio) {
                $this->json(['success' => false, 'error' => 'Exercício não encontrado'], 404);
            }
            if (!$this->jornadaPermiteAluno($exercicio, $alunoId)) {
                $this->json(['success' => false, 'error' => 'Você não tem acesso a esta jornada'], 403);
            }
            if (($exercicio['jornada_status'] ?? '') === 'pausada') {
                $this->json(['success' => false, 'error' => 'Jornada pausada'], 400);
            }

            $this->registrarAuditoriaExercicio([
                'aluno_id' => $alunoId,
                'jornada_id' => (int) $exercicio['jornada_id'],
                'modulo_id' => (int) $moduloId,
                'exercicio_id' => (int) $exercicioId,
                'tipo_acao' => $tipoAcao !== '' ? $tipoAcao : 'alternativa_trocada',
                'de_valor' => $deValor,
                'para_valor' => $paraValor,
                'detalhes_json' => [
                    'origem' => 'frontend_jornada',
                ],
            ]);

            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->logJornadaError('auditoriaExercicioEvento: erro ao registrar evento', [
                'exception' => $e->getMessage(),
                'post_keys' => array_keys($_POST),
            ]);
            $this->json(['success' => false, 'error' => 'Falha ao registrar auditoria'], 500);
        }
    }

    /**
     * Registra eventos de auditoria em lote.
     * Espera payload JSON: { "eventos": [ { exercicio_id, modulo_id, tipo_acao, de_valor, para_valor }, ... ] }
     */
    public function auditoriaExercicioEventoLote()
    {
        try {
            $user = $this->authManager->getUser();
            if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
                $this->json(['success' => false, 'error' => 'Acesso negado'], 403);
            }

            $rawBody = file_get_contents('php://input');
            $payload = json_decode((string) $rawBody, true);
            if (!is_array($payload)) {
                $this->json(['success' => false, 'error' => 'Payload inválido'], 400);
            }

            $eventos = $payload['eventos'] ?? null;
            if (!is_array($eventos) || empty($eventos)) {
                $this->json(['success' => false, 'error' => 'Nenhum evento enviado'], 400);
            }

            if (count($eventos) > 100) {
                $this->json(['success' => false, 'error' => 'Lote excede o limite de 100 eventos'], 400);
            }

            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            if (!$aluno) {
                $this->json(['success' => false, 'error' => 'Aluno não encontrado'], 403);
            }
            $alunoId = (int) $aluno['id'];

            $pares = [];
            foreach ($eventos as $evento) {
                if (!is_array($evento)) {
                    continue;
                }
                $exercicioId = (int) ($evento['exercicio_id'] ?? 0);
                $moduloId = (int) ($evento['modulo_id'] ?? 0);
                if ($exercicioId > 0 && $moduloId > 0) {
                    $pares[$exercicioId . ':' . $moduloId] = [
                        'exercicio_id' => $exercicioId,
                        'modulo_id' => $moduloId,
                    ];
                }
            }

            if (empty($pares)) {
                $this->json(['success' => false, 'error' => 'Eventos sem dados válidos'], 400);
            }

            $whereParts = [];
            $params = [];
            $i = 0;
            foreach ($pares as $par) {
                $whereParts[] = "(me.id = :eid{$i} AND m.id = :mid{$i})";
                $params["eid{$i}"] = (int) $par['exercicio_id'];
                $params["mid{$i}"] = (int) $par['modulo_id'];
                $i++;
            }

            $exerciciosValidos = $this->db->fetchAll(
                "SELECT me.id, me.modulo_id, m.jornada_id, j.turma_id, j.status as jornada_status, j.estrutura
                 FROM jornadas_modulos_exercicios me
                 JOIN jornadas_modulos m ON me.modulo_id = m.id
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE " . implode(' OR ', $whereParts),
                $params
            );

            $mapExercicios = [];
            foreach ($exerciciosValidos as $row) {
                if (!$this->jornadaPermiteAluno($row, $alunoId)) {
                    continue;
                }
                $mapExercicios[(int) $row['id'] . ':' . (int) $row['modulo_id']] = $row;
            }

            $registrados = 0;
            $ignorados = 0;
            foreach ($eventos as $evento) {
                if (!is_array($evento)) {
                    $ignorados++;
                    continue;
                }

                $exercicioId = (int) ($evento['exercicio_id'] ?? 0);
                $moduloId = (int) ($evento['modulo_id'] ?? 0);
                $tipoAcao = trim((string) ($evento['tipo_acao'] ?? 'alternativa_trocada'));
                $deValor = isset($evento['de_valor']) ? (string) $evento['de_valor'] : null;
                $paraValor = isset($evento['para_valor']) ? (string) $evento['para_valor'] : null;

                if ($exercicioId <= 0 || $moduloId <= 0) {
                    $ignorados++;
                    continue;
                }

                $chave = $exercicioId . ':' . $moduloId;
                if (!isset($mapExercicios[$chave])) {
                    $ignorados++;
                    continue;
                }

                if (($mapExercicios[$chave]['jornada_status'] ?? '') === 'pausada') {
                    $ignorados++;
                    continue;
                }

                $this->registrarAuditoriaExercicio([
                    'aluno_id' => $alunoId,
                    'jornada_id' => (int) $mapExercicios[$chave]['jornada_id'],
                    'modulo_id' => (int) $moduloId,
                    'exercicio_id' => (int) $exercicioId,
                    'tipo_acao' => $tipoAcao !== '' ? $tipoAcao : 'alternativa_trocada',
                    'de_valor' => $deValor,
                    'para_valor' => $paraValor,
                    'detalhes_json' => [
                        'origem' => 'frontend_jornada_lote',
                    ],
                ]);
                $registrados++;
            }

            $this->json([
                'success' => true,
                'registrados' => $registrados,
                'ignorados' => $ignorados,
                'total_recebido' => count($eventos),
            ]);
        } catch (\Throwable $e) {
            $this->logJornadaError('auditoriaExercicioEventoLote: erro ao registrar lote', [
                'exception' => $e->getMessage(),
            ]);
            $this->json(['success' => false, 'error' => 'Falha ao registrar auditoria em lote'], 500);
        }
    }
    
    private function redirectToCorrectDashboard($tipo)
    {
        switch ($tipo) {
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'admin_escola':
                $this->redirect('/admin/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/dashboard');
        }
    }
    
    /**
     * ============================================
     * SISTEMA DE REDAÇÕES NA JORNADA (ALUNO)
     * ============================================
     */
    
    /**
     * Ver redações disponíveis na jornada
     */
    public function redacoesJornada($jornadaId)
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                $this->redirectToCorrectDashboard($user['tipo']);
                return;
            }
            
            // Buscar dados do aluno
            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            
            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }
            
            // Verificar se a jornada pertence à turma do aluno (checagem em PHP por compatibilidade MariaDB)
            $jornada = $this->db->fetch(
                "SELECT {$this->colunasSqlJornadasShow('j')}, p.nome as professor_nome
                 FROM jornadas j
                 JOIN professores p ON j.professor_id = p.id
                 WHERE j.id = :id AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status = 'finalizada' OR j.status IS NULL OR j.status = '') AND j.status != 'pausada'",
                ['id' => $jornadaId]
            );
            if ($jornada && !$this->jornadaPermiteAluno($jornada, (int) ($aluno['id'] ?? 0))) {
                $jornada = null;
            }
            if (!$jornada) {
                $_SESSION['error_message'] = 'Jornada não encontrada';
                $this->redirect('/jornadas');
                return;
            }
            
            // Buscar redações da jornada disponíveis para o aluno
            $redacoesJornada = $this->db->fetchAll(
                "SELECT {$this->colunasSqlJornadasRedacoes('jr')}, ja.nome_aula,
                        (SELECT COUNT(*) FROM jornadas_redacoes_alunos jra 
                         WHERE jra.jornada_redacao_id = jr.id AND jra.aluno_id = :aluno_id) as tem_redacao
                 FROM jornadas_redacoes jr
                 LEFT JOIN jornadas_aulas ja ON jr.aula_id = ja.id
                 WHERE jr.jornada_id = :jornada_id
                 ORDER BY jr.created_at DESC",
                ['jornada_id' => $jornadaId, 'aluno_id' => $aluno['id']]
            );
            
            // Para cada redação da jornada, buscar redação do aluno se existir
            foreach ($redacoesJornada as &$redacaoJornada) {
                $redacaoAluno = $this->db->fetch(
                    "SELECT {$this->colunasSqlJornadasRedacoesAlunos('jra')}, {$this->colunasSqlRedacoesEscrever('r')}, r.permitir_refazer
                     FROM jornadas_redacoes_alunos jra
                     INNER JOIN redacoes r ON jra.redacao_id = r.id
                     WHERE jra.jornada_redacao_id = :jornada_redacao_id 
                       AND jra.aluno_id = :aluno_id
                     ORDER BY jra.versao DESC
                     LIMIT 1",
                    ['jornada_redacao_id' => $redacaoJornada['id'], 'aluno_id' => $aluno['id']]
                );
                
                $redacaoJornada['minha_redacao'] = $redacaoAluno;
            }
            
            $data = [
                'title' => 'Redações da Jornada - EducaTudo',
                'jornada' => $jornada,
                'redacoes_jornada' => $redacoesJornada,
                'aluno' => $aluno,
                'user' => $user,
                'csrf_token' => $this->generateCsrfToken(),
                'current_page' => 'journeys'
            ];
            
            $this->viewWithLayout('student', 'student/journeys/redacoes', $data);
            
        } catch (Exception $e) {
            $this->logJornadaError('redacoesJornada: erro ao carregar redações', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'jornada_id' => $jornadaId ?? null,
            ]);
            $_SESSION['error_message'] = 'Erro ao carregar redações: ' . $e->getMessage();
            $this->redirect('/jornadas');
        }
    }
    
    /**
     * Escrever redação da jornada
     */
    public function escreverRedacaoJornada($jornadaRedacaoId)
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                $this->redirectToCorrectDashboard($user['tipo']);
                return;
            }
            
            // Buscar dados do aluno
            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            
            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }
            
            // Buscar redação da jornada (checagem de turma em PHP por compatibilidade MariaDB)
            $redacaoJornada = $this->db->fetch(
                "SELECT {$this->colunasSqlJornadasRedacoes('jr')}, j.id as jornada_id, j.titulo as jornada_titulo, ja.nome_aula, j.turma_id, j.estrutura
                 FROM jornadas_redacoes jr
                 INNER JOIN jornadas j ON jr.jornada_id = j.id
                 LEFT JOIN jornadas_aulas ja ON jr.aula_id = ja.id
                 WHERE jr.id = :id AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status = 'finalizada' OR j.status IS NULL OR j.status = '') AND j.status != 'pausada'",
                ['id' => $jornadaRedacaoId]
            );
            if ($redacaoJornada && !$this->jornadaPermiteAluno($redacaoJornada, (int) ($aluno['id'] ?? 0))) {
                $redacaoJornada = null;
            }
            
            if (!$redacaoJornada) {
                $_SESSION['error_message'] = 'Redação não encontrada ou jornada não está ativa';
                $this->redirect('/jornadas');
                return;
            }
            
            // Verificar se já existe redação do aluno (buscar a mais recente)
            // IMPORTANTE: Buscar TODAS as redações, não apenas rascunhos, para verificar se já foi finalizada
            $redacaoExistente = $this->db->fetch(
                "SELECT {$this->colunasSqlJornadasRedacoesAlunos('jra')}, {$this->colunasSqlRedacoesEscrever('r')}, r.id as redacao_id, r.eh_rascunho, jra.status as jra_status
                 FROM jornadas_redacoes_alunos jra
                 INNER JOIN redacoes r ON jra.redacao_id = r.id
                 WHERE jra.jornada_redacao_id = :jornada_redacao_id 
                   AND jra.aluno_id = :aluno_id
                 ORDER BY jra.versao DESC, jra.created_at DESC
                 LIMIT 1",
                ['jornada_redacao_id' => $jornadaRedacaoId, 'aluno_id' => $aluno['id']]
            );
            
            $versao = 1;
            if ($redacaoExistente) {
                // Se foi retornada para reescrever, incrementar versão
                if ($redacaoExistente['retornada_para_reescrever'] == 1 || $redacaoExistente['status'] === 'retornada') {
                    $versao = $redacaoExistente['versao'] + 1;
                } else {
                    // Se a redação já foi finalizada (não é rascunho), redirecionar para ver correção
                    $statusRedacao = $redacaoExistente['jra_status'] ?? $redacaoExistente['status'] ?? 'rascunho';
                    $ehRascunho = isset($redacaoExistente['eh_rascunho']) ? (int)$redacaoExistente['eh_rascunho'] : 1;
                    
                    // Considera finalizada se status não for 'rascunho' OU se eh_rascunho = 0
                    if ($statusRedacao !== 'rascunho' || $ehRascunho == 0) {
                        // Redirecionar para ver a redação finalizada/corrigida
                        $this->redirect('/jornadas/' . $redacaoJornada['jornada_id'] . '/redacao/' . $redacaoExistente['redacao_id']);
                        return;
                    }
                    // Se ainda é rascunho, permite continuar editando
                    $versao = $redacaoExistente['versao'];
                }
            }
            
            $data = [
                'title' => 'Escrever Redação - EducaTudo',
                'redacao_jornada' => $redacaoJornada,
                'redacao_existente' => $redacaoExistente,
                'versao' => $versao,
                'aluno' => $aluno,
                'user' => $user,
                'csrf_token' => $this->generateCsrfToken(),
                'current_page' => 'journeys'
            ];
            
            $this->viewWithLayout('student', 'student/journeys/escrever-redacao', $data);
            
        } catch (Exception $e) {
            $this->logJornadaError('escreverRedacaoJornada: erro ao carregar página', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'jornada_redacao_id' => $jornadaRedacaoId ?? null,
            ]);
            $_SESSION['error_message'] = 'Erro ao carregar página: ' . $e->getMessage();
            $this->redirect('/jornadas');
        }
    }
    
    /**
     * Ver correção da redação da jornada
     */
    public function verCorrecaoRedacaoJornada($jornadaId, $redacaoId)
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                $this->redirectToCorrectDashboard($user['tipo']);
                return;
            }
            
            // Buscar dados do aluno
            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            
            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }
            
            // Verificar se a jornada está ativa (checagem em PHP por compatibilidade MariaDB)
            $jornada = $this->db->fetch(
                "SELECT {$this->colunasSqlJornadasPermiteAluno('j')} FROM jornadas j WHERE j.id = :jornada_id AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status = 'finalizada' OR j.status IS NULL OR j.status = '') AND j.status != 'pausada'",
                ['jornada_id' => $jornadaId]
            );
            if ($jornada && !$this->jornadaPermiteAluno($jornada, (int) ($aluno['id'] ?? 0))) {
                $jornada = null;
            }
            if (!$jornada) {
                $_SESSION['error_message'] = 'Jornada não encontrada ou não está ativa';
                $this->redirect('/jornadas');
                return;
            }
            
            // Buscar redação - garantir que busca todos os campos necessários para média
            // IMPORTANTE: Usar aliases explícitos para evitar conflito de nomes entre r.* e jra.*
            $redacao = $this->db->fetch(
                "SELECT r.id as redacao_id,
                        r.aluno_id, r.jornada_id, r.titulo, r.conteudo, r.texto, r.tema,
                        r.competencia_1, r.competencia_2, r.competencia_3, r.competencia_4, r.competencia_5,
                        r.competencia_1_explicacao_professor, r.competencia_2_explicacao_professor,
                        r.competencia_3_explicacao_professor, r.competencia_4_explicacao_professor,
                        r.competencia_5_explicacao_professor,
                        r.nota_final, r.nota_final_professor, r.nota_final_utilizada,
                        r.usar_media_notas, r.nota_media,
                        r.comentarios_gerais_professor, r.sugestoes_melhoria_professor,
                        r.mostrar_competencia_1_aluno, r.mostrar_competencia_2_aluno,
                        r.mostrar_competencia_3_aluno, r.mostrar_competencia_4_aluno,
                        r.mostrar_competencia_5_aluno, r.mostrar_correcao_ia_aluno,
                        r.permitir_refazer, r.feedback_ia, r.corrigida_em, r.created_at, r.updated_at,
                        jra.id as jra_id, jra.jornada_redacao_id, jra.versao, jra.status as jra_status,
                        jra.created_at as jra_created_at, jra.updated_at as jra_updated_at,
                        jr.tema_sugerido, jr.descricao_tema
                 FROM redacoes r
                 INNER JOIN jornadas_redacoes_alunos jra ON r.id = jra.redacao_id
                 INNER JOIN jornadas_redacoes jr ON jra.jornada_redacao_id = jr.id
                 WHERE r.id = :redacao_id AND jra.aluno_id = :aluno_id AND jr.jornada_id = :jornada_id",
                ['redacao_id' => $redacaoId, 'aluno_id' => $aluno['id'], 'jornada_id' => $jornadaId]
            );
            
            if (!$redacao) {
                $_SESSION['error_message'] = 'Redação não encontrada';
                $this->redirect('/jornadas/' . $jornadaId . '/redacoes');
                return;
            }
            
            // Decodificar feedback da IA se existir
            $feedbackIA = null;
            if (!empty($redacao['feedback_ia'])) {
                $feedbackDecoded = json_decode($redacao['feedback_ia'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $feedbackIA = $feedbackDecoded;
                } else {
                    error_log("Erro ao decodificar feedback_ia para redação {$redacaoId}: " . json_last_error_msg());
                    error_log("Conteúdo do feedback_ia: " . substr($redacao['feedback_ia'], 0, 500));
                }
            }
            
            // Debug: Log dos dados da redação
            error_log("Redação ID {$redacaoId} - nota_final: " . ($redacao['nota_final'] ?? 'null') . 
                     ", nota_final_professor: " . ($redacao['nota_final_professor'] ?? 'null') .
                     ", nota_final_utilizada: " . ($redacao['nota_final_utilizada'] ?? 'null') .
                     ", usar_media_notas: " . ($redacao['usar_media_notas'] ?? 'null') .
                     ", nota_media: " . ($redacao['nota_media'] ?? 'null') .
                     ", competencia_1: " . ($redacao['competencia_1'] ?? 'null') . 
                     ", corrigida_em: " . ($redacao['corrigida_em'] ?? 'null') . 
                     ", feedback_ia existe: " . (!empty($redacao['feedback_ia']) ? 'sim' : 'não'));
            
            // Determinar qual correção mostrar
            $usarCorrecaoProfessor = isset($redacao['usar_correcao_professor']) && $redacao['usar_correcao_professor'] == 1;
            
            // Verificar se existe uma versão posterior desta redação
            $versaoAtual = $redacao['versao'] ?? 1;
            $temVersaoPosterior = false;
            if ($versaoAtual == 1) {
                // Verificar se existe versão 2 ou maior para o mesmo aluno e jornada_redacao_id
                $versaoPosterior = $this->db->fetch(
                    "SELECT COUNT(*) as total
                     FROM redacoes r
                     INNER JOIN jornadas_redacoes_alunos jra ON r.id = jra.redacao_id
                     WHERE jra.aluno_id = :aluno_id 
                     AND jra.jornada_redacao_id = :jornada_redacao_id 
                     AND jra.versao > :versao_atual",
                    [
                        'aluno_id' => $aluno['id'],
                        'jornada_redacao_id' => $redacao['jornada_redacao_id'],
                        'versao_atual' => $versaoAtual
                    ]
                );
                $temVersaoPosterior = ($versaoPosterior['total'] ?? 0) > 0;
            }
            
            $data = [
                'title' => 'Correção da Redação - EducaTudo',
                'redacao' => $redacao,
                'feedback_ia' => $feedbackIA,
                'usar_correcao_professor' => $usarCorrecaoProfessor,
                'jornada_id' => $jornadaId,
                'aluno' => $aluno,
                'user' => $user,
                'current_page' => 'journeys',
                'temVersaoPosterior' => $temVersaoPosterior,
                'versao_atual' => $versaoAtual
            ];
            
            // Adicionar CSRF token
            $data['csrf_token'] = $this->generateCsrfToken();
            
            $this->viewWithLayout('student', 'student/journeys/ver-correcao-redacao', $data);
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao carregar correção: ' . $e->getMessage();
            $this->redirect('/jornadas');
        }
    }
    
    /**
     * Refazer redação - permite ao aluno criar uma nova versão após ver a correção
     */
    public function refazerRedacao()
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                $this->redirectToCorrectDashboard($user['tipo']);
                return;
            }
            
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $_SESSION['error_message'] = 'Token inválido';
                $this->redirect('/jornadas');
                return;
            }
            
            $jornadaId = $_POST['jornada_id'] ?? null;
            $redacaoId = $_POST['redacao_id'] ?? null;
            
            if (!$jornadaId || !$redacaoId) {
                $_SESSION['error_message'] = 'Dados inválidos';
                $this->redirect('/jornadas');
                return;
            }
            
            // Buscar dados do aluno
            $aluno = $this->fetchAlunoParaListagemJornadas((int) $user['id']);
            
            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }
            
            // Buscar redação atual e verificar se pertence ao aluno
            $redacaoAtual = $this->db->fetch(
                "SELECT r.id, r.nota_final, r.nota_final_professor, r.competencia_1, r.competencia_2, r.competencia_3,
                        r.competencia_4, r.competencia_5, jra.jornada_redacao_id, jra.versao, jr.tema_sugerido, jr.descricao_tema
                 FROM redacoes r
                 INNER JOIN jornadas_redacoes_alunos jra ON r.id = jra.redacao_id
                 INNER JOIN jornadas_redacoes jr ON jra.jornada_redacao_id = jr.id
                 WHERE r.id = :redacao_id AND jra.aluno_id = :aluno_id AND jr.jornada_id = :jornada_id",
                ['redacao_id' => $redacaoId, 'aluno_id' => $aluno['id'], 'jornada_id' => $jornadaId]
            );
            
            if (!$redacaoAtual) {
                $_SESSION['error_message'] = 'Redação não encontrada';
                $this->redirect('/jornadas/' . $jornadaId);
                return;
            }
            
            // Verificar se a redação foi corrigida
            $temCorrecao = ($redacaoAtual['nota_final_professor'] ?? $redacaoAtual['nota_final'] ?? 0) > 0 || 
                          ($redacaoAtual['competencia_1'] ?? 0) > 0 || 
                          ($redacaoAtual['competencia_2'] ?? 0) > 0 ||
                          ($redacaoAtual['competencia_3'] ?? 0) > 0 ||
                          ($redacaoAtual['competencia_4'] ?? 0) > 0 ||
                          ($redacaoAtual['competencia_5'] ?? 0) > 0;
            
            if (!$temCorrecao) {
                $_SESSION['error_message'] = 'A redação ainda não foi corrigida. Aguarde a correção antes de refazer.';
                $this->redirect('/jornadas/' . $jornadaId . '/redacao/' . $redacaoId);
                return;
            }
            
            // Calcular próxima versão
            $versaoAtual = $redacaoAtual['versao'] ?? 1;
            $novaVersao = $versaoAtual + 1;
            
            // Criar nova redação vazia (rascunho)
            $novaRedacaoId = $this->db->insert(
                "INSERT INTO redacoes 
                 (aluno_id, jornada_id, titulo, conteudo, texto, tema, tipo, eh_rascunho, created_at)
                 VALUES (:aluno_id, :jornada_id, :titulo, '', '', :tema, 'jornada', 1, NOW())",
                [
                    'aluno_id' => $aluno['id'],
                    'jornada_id' => $jornadaId,
                    'titulo' => $redacaoAtual['tema_sugerido'] ?? 'Redação da Jornada',
                    'tema' => $redacaoAtual['tema_sugerido'] ?? 'Redação da Jornada'
                ]
            );
            
            // Criar vínculo na jornada com nova versão
            $this->db->insert(
                "INSERT INTO jornadas_redacoes_alunos 
                 (jornada_redacao_id, redacao_id, aluno_id, versao, status, created_at)
                 VALUES (:jornada_redacao_id, :redacao_id, :aluno_id, :versao, 'rascunho', NOW())",
                [
                    'jornada_redacao_id' => $redacaoAtual['jornada_redacao_id'],
                    'redacao_id' => $novaRedacaoId,
                    'aluno_id' => $aluno['id'],
                    'versao' => $novaVersao
                ]
            );
            
            $_SESSION['success_message'] = 'Nova versão da redação criada! Você pode começar a escrever agora.';
            
            // Redirecionar para a tela de escrever redação
            $this->redirect('/jornadas/redacao/' . $redacaoAtual['jornada_redacao_id'] . '/escrever');
            
        } catch (Exception $e) {
            error_log("Erro ao refazer redação: " . $e->getMessage());
            $_SESSION['error_message'] = 'Erro ao criar nova versão: ' . $e->getMessage();
            $this->redirect('/jornadas');
        }
    }
}
}
