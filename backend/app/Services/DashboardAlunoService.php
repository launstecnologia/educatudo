<?php
/**
 * Montagem assíncrona dos cards do dashboard do aluno.
 * Id do aluno sempre vem do caller (sessão) — nunca de input HTTP.
 */
class DashboardAlunoService
{
    private const CACHE_TTL = 30;

    /** @var mixed */
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @param array<string, mixed> $aluno
     * @return array<string, mixed>
     */
    public function montarCards(array $aluno, bool $acaoCards, bool $jornadasHabilitadas): array
    {
        $alunoId = (int) ($aluno['id'] ?? 0);
        $turmaId = (int) ($aluno['turma_id'] ?? 0);
        if ($alunoId <= 0) {
            return $this->cardsVazios($acaoCards);
        }

        $cacheKey = $this->cacheKey($alunoId, $turmaId, $acaoCards, $jornadasHabilitadas);
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $mural = $this->muralResumo($alunoId, $turmaId);

        $cards = [
            'jornadas_abertas' => ($jornadasHabilitadas && $turmaId > 0)
                ? $this->contarJornadasAbertas($alunoId, $turmaId)
                : 0,
            'mural_nao_lidos' => (int) ($mural['nao_lidos'] ?? 0),
            'provas_disponiveis' => $this->contarProvasDisponiveis($alunoId, $turmaId),
            'mural_total' => (int) ($mural['total'] ?? 0),
        ];

        if ($acaoCards) {
            $cards['jornada_redacao_pendentes'] = $this->contarJornadaRedacaoPendentes($alunoId);
        }

        $payload = [
            'cards' => $cards,
            'mural' => $mural,
        ];

        $this->cacheSet($cacheKey, $payload);

        return $payload;
    }

    /**
     * @return array{nao_lidos:int, total:int, exibir_popup:bool, qtd_popup:int}
     */
    public function muralResumo(int $alunoId, int $turmaId): array
    {
        if ($alunoId <= 0) {
            return ['nao_lidos' => 0, 'total' => 0, 'exibir_popup' => false, 'qtd_popup' => 0];
        }

        $naoLidos = $this->contarMural($alunoId, $turmaId, true);
        $total = $this->contarMural($alunoId, $turmaId, false);

        return [
            'nao_lidos' => $naoLidos,
            'total' => $total,
            'exibir_popup' => $naoLidos > 0,
            'qtd_popup' => min($naoLidos, 10),
        ];
    }

    public function invalidarCacheAluno(int $alunoId, int $turmaId = 0): void
    {
        if ($alunoId <= 0 || !$this->redisDisponivel()) {
            return;
        }
        $turmaId = $turmaId > 0 ? $turmaId : 0;
        foreach ([true, false] as $acao) {
            foreach ([true, false] as $jorn) {
                RedisCache::delete($this->cacheKey($alunoId, $turmaId, $acao, $jorn));
            }
        }
    }

    private function contarJornadasAbertas(int $alunoId, int $turmaId): int
    {
        $jornadas = $this->db->fetchAll(
            "SELECT j.id, j.turma_id, j.estrutura, j.created_at,
                    (SELECT COUNT(*) FROM jornadas_modulos jm WHERE jm.jornada_id = j.id) AS total_modulos,
                    (SELECT COUNT(*) FROM jornadas_progresso_alunos jpa
                     WHERE jpa.jornada_id = j.id AND jpa.aluno_id = :aluno_id
                       AND jpa.atividade_tipo = 'modulo' AND jpa.status = 'concluido') AS modulos_concluidos
             FROM jornadas j
             WHERE (j.turma_id = :turma_id OR (j.estrutura IS NOT NULL AND j.estrutura != ''))
               AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status IS NULL OR j.status = '')
               AND (j.status != 'pausada' OR j.status IS NULL)
               AND (j.ativo = 1 OR j.ativo IS NULL)
             ORDER BY j.created_at DESC
             LIMIT 50",
            ['turma_id' => $turmaId, 'aluno_id' => $alunoId]
        ) ?: [];

        $dataAtual = date('Y-m-d');
        $agoraTs = time();
        $count = 0;

        foreach ($jornadas as $jornada) {
            if (!$this->jornadaVisivelParaTurma($jornada, $turmaId)) {
                continue;
            }

            $estrutura = $this->decodificarEstrutura($jornada['estrutura'] ?? '');
            $dataInicio = !empty($estrutura['data_inicio'])
                ? date('Y-m-d', strtotime((string) $estrutura['data_inicio']))
                : null;
            if ($dataInicio && $dataAtual < $dataInicio) {
                continue;
            }

            $totalMod = (int) ($jornada['total_modulos'] ?? 0);
            $concluidos = (int) ($jornada['modulos_concluidos'] ?? 0);
            if ($totalMod > 0 && $concluidos >= $totalMod) {
                continue;
            }

            if (!empty($estrutura['data_fim'])) {
                $horaFim = trim((string) ($estrutura['hora_fim'] ?? '')) ?: '23:59:59';
                $tsFim = strtotime((string) $estrutura['data_fim'] . ' ' . $horaFim);
                if ($tsFim !== false && $agoraTs > $tsFim) {
                    continue;
                }
            }

            $count++;
        }

        return $count;
    }

    private function contarProvasDisponiveis(int $alunoId, int $turmaId): int
    {
        try {
            require_once __DIR__ . '/../Models/Exams/Exam.php';
            return (new Exam())->countAvailableForStudent($alunoId, $turmaId);
        } catch (Throwable $e) {
            error_log('DashboardAlunoService::contarProvasDisponiveis: ' . $e->getMessage());
            return 0;
        }
    }

    private function contarJornadaRedacaoPendentes(int $alunoId): int
    {
        if ($alunoId <= 0) {
            return 0;
        }
        require_once __DIR__ . '/../Core/LayoutHelper.php';
        if (LayoutHelper::get('module_redacao_configuravel', '1') !== '1') {
            return 0;
        }
        if (LayoutHelper::get('module_aluno_redacao_configuravel', '1') !== '1') {
            return 0;
        }
        require_once __DIR__ . '/../Models/Essays/EssayProposal.php';
        return (new EssayProposal())->countPendingForStudent($alunoId);
    }

    private function contarMural(int $alunoId, int $turmaId, bool $apenasNaoVistos): int
    {
        $sql = $this->sqlMuralBase($turmaId, $apenasNaoVistos);
        $params = $this->paramsMuralBase($alunoId, $turmaId, $apenasNaoVistos);
        $row = $this->db->fetch("SELECT COUNT(*) AS total FROM mural_recados r WHERE {$sql}", $params);
        return (int) ($row['total'] ?? 0);
    }

    private function sqlMuralBase(int $turmaId, bool $apenasNaoVistos): string
    {
        $turmaFiltro = $turmaId > 0
            ? " OR EXISTS (SELECT 1 FROM mural_recados_turmas rt WHERE rt.mural_recado_id = r.id AND rt.turma_id = :aluno_turma_id)"
            : '';
        $sql = "(r.enviar_para_todos = 1{$turmaFiltro}) AND (CURDATE() <= r.data_sai_mural)";
        if ($apenasNaoVistos) {
            $sql .= " AND NOT EXISTS (
                SELECT 1 FROM mural_recados_vistos v
                WHERE v.mural_recado_id = r.id AND v.aluno_id = :aluno_id
            )";
        }
        return $sql;
    }

    /**
     * @return array<string, int|string>
     */
    private function paramsMuralBase(int $alunoId, int $turmaId, bool $apenasNaoVistos): array
    {
        $params = [];
        if ($turmaId > 0) {
            $params['aluno_turma_id'] = $turmaId;
        }
        if ($apenasNaoVistos) {
            $params['aluno_id'] = $alunoId;
        }
        return $params;
    }

    /**
     * @param array<string, mixed> $jornada
     */
    private function jornadaVisivelParaTurma(array $jornada, int $turmaId): bool
    {
        if ((int) ($jornada['turma_id'] ?? 0) === $turmaId) {
            return true;
        }
        $estrutura = $this->decodificarEstrutura($jornada['estrutura'] ?? '');
        if (empty($estrutura['turmas_selecionadas']) || !is_array($estrutura['turmas_selecionadas'])) {
            return false;
        }
        return in_array($turmaId, array_map('intval', $estrutura['turmas_selecionadas']), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodificarEstrutura($raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function cardsVazios(bool $acaoCards): array
    {
        $cards = [
            'jornadas_abertas' => 0,
            'mural_nao_lidos' => 0,
            'provas_disponiveis' => 0,
            'mural_total' => 0,
        ];
        if ($acaoCards) {
            $cards['jornada_redacao_pendentes'] = 0;
        }
        return [
            'cards' => $cards,
            'mural' => ['nao_lidos' => 0, 'total' => 0, 'exibir_popup' => false, 'qtd_popup' => 0],
        ];
    }

    private function cacheKey(int $alunoId, int $turmaId, bool $acaoCards, bool $jornadasHabilitadas): string
    {
        $tenant = defined('TENANT_SLUG') ? (string) TENANT_SLUG : 'default';
        return sprintf(
            'dashboard:cards:v1:%s:%d:%d:%d:%d',
            $tenant,
            $alunoId,
            $turmaId,
            $acaoCards ? 1 : 0,
            $jornadasHabilitadas ? 1 : 0
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cacheGet(string $key): ?array
    {
        if (!$this->redisDisponivel()) {
            return null;
        }
        $raw = RedisCache::get($key);
        if ($raw === null || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function cacheSet(string $key, array $payload): void
    {
        if (!$this->redisDisponivel()) {
            return;
        }
        RedisCache::set($key, json_encode($payload, JSON_UNESCAPED_UNICODE), self::CACHE_TTL);
    }

    private function redisDisponivel(): bool
    {
        if (class_exists('RedisCache')) {
            return true;
        }
        $path = __DIR__ . '/RedisCache.php';
        if (is_file($path)) {
            require_once $path;
        }
        return class_exists('RedisCache');
    }
}
