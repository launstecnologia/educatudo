<?php
/**
 * Agrega fila ai_jobs dos tenants + histórico cron_execucoes no Master.
 */
namespace App\Services;

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/MasterTenantConnection.php';
require_once __DIR__ . '/CronExecucaoService.php';

class MasterFilaIaService
{
    public const STUCK_PROCESSING_MINUTES = 10;
    public const STUCK_PENDING_MINUTES = 5;

    /**
     * @return list<array{id:int,nome:string,slug:string}>
     */
    public static function listarEscolasAtivas(): array
    {
        $db = \Database::getInstance();
        return $db->query(
            "SELECT e.id, e.nome, e.slug
             FROM escolas e
             INNER JOIN config_escolas_banco b ON b.escola_id = e.id
             WHERE e.ativo = 1
             ORDER BY e.nome"
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array{pending:int,processing:int,failed:int,done:int,travados:int,escolas_com_fila:int,erros:list<string>}
     */
    public static function agregarKpis(int $escolaIdFiltro = 0): array
    {
        $kpis = [
            'pending' => 0,
            'processing' => 0,
            'failed' => 0,
            'done' => 0,
            'travados' => 0,
            'escolas_com_fila' => 0,
            'erros' => [],
        ];
        foreach (self::listarEscolasAtivas() as $escola) {
            $eid = (int) ($escola['id'] ?? 0);
            if ($escolaIdFiltro > 0 && $eid !== $escolaIdFiltro) {
                continue;
            }
            $conn = \MasterTenantConnection::getPdoAndEscola($eid);
            if ($conn === null || empty($conn['pdo'])) {
                $kpis['erros'][] = 'Sem conexão: ' . ($escola['nome'] ?? (string) $eid);
                continue;
            }
            /** @var \PDO $pdo */
            $pdo = $conn['pdo'];
            if (!self::tabelaExiste($pdo, 'ai_jobs')) {
                continue;
            }
            try {
                $counts = self::contarPorStatus($pdo);
                $travadosEscola = self::contarTravados($pdo);
                $kpis['pending'] += $counts['pending'];
                $kpis['processing'] += $counts['processing'];
                $kpis['failed'] += $counts['failed'];
                $kpis['done'] += $counts['done'];
                $kpis['travados'] += $travadosEscola;
                if (($counts['pending'] + $counts['processing'] + $counts['failed']) > 0 || $travadosEscola > 0) {
                    $kpis['escolas_com_fila']++;
                }
            } catch (\Throwable $e) {
                error_log('[MasterFilaIa] kpi escola=' . $eid . ' ' . $e->getMessage());
                $kpis['erros'][] = 'Falha ao ler fila: ' . ($escola['nome'] ?? (string) $eid);
            }
        }
        return $kpis;
    }

    /**
     * @param array{
     *   escola_id?: int,
     *   status?: string,
     *   job_type?: string,
     *   q?: string,
     *   so_travados?: bool,
     *   page?: int,
     *   per_page?: int
     * } $filtros
     * @return array{
     *   kpis: array{pending:int,processing:int,failed:int,done:int,travados:int,escolas_com_fila:int},
     *   jobs: list<array>,
     *   total: int,
     *   page: int,
     *   per_page: int,
     *   erros: list<string>
     * }
     */
    public static function listarJobs(array $filtros = []): array
    {
        $escolaIdFiltro = (int) ($filtros['escola_id'] ?? 0);
        $statusFiltro = trim((string) ($filtros['status'] ?? ''));
        $tipoFiltro = trim((string) ($filtros['job_type'] ?? ''));
        $q = trim((string) ($filtros['q'] ?? ''));
        $soTravados = !empty($filtros['so_travados']);
        $page = max(1, (int) ($filtros['page'] ?? 1));
        $perPage = max(5, min(50, (int) ($filtros['per_page'] ?? 20)));

        $statusValidos = ['pending', 'processing', 'done', 'failed'];
        if ($statusFiltro !== '' && !in_array($statusFiltro, $statusValidos, true)) {
            $statusFiltro = '';
        }

        $kpis = [
            'pending' => 0,
            'processing' => 0,
            'failed' => 0,
            'done' => 0,
            'travados' => 0,
            'escolas_com_fila' => 0,
        ];
        $erros = [];
        $todos = [];

        $escolas = self::listarEscolasAtivas();
        foreach ($escolas as $escola) {
            $eid = (int) ($escola['id'] ?? 0);
            if ($escolaIdFiltro > 0 && $eid !== $escolaIdFiltro) {
                continue;
            }
            $conn = \MasterTenantConnection::getPdoAndEscola($eid);
            if ($conn === null || empty($conn['pdo'])) {
                $erros[] = 'Sem conexão: ' . ($escola['nome'] ?? (string) $eid);
                continue;
            }
            /** @var \PDO $pdo */
            $pdo = $conn['pdo'];
            if (!self::tabelaExiste($pdo, 'ai_jobs')) {
                continue;
            }

            try {
                $counts = self::contarPorStatus($pdo);
                $travadosEscola = self::contarTravados($pdo);
                $kpis['pending'] += $counts['pending'];
                $kpis['processing'] += $counts['processing'];
                $kpis['failed'] += $counts['failed'];
                $kpis['done'] += $counts['done'];
                $kpis['travados'] += $travadosEscola;
                if (($counts['pending'] + $counts['processing'] + $counts['failed']) > 0 || $travadosEscola > 0) {
                    $kpis['escolas_com_fila']++;
                }

                $rows = self::buscarJobsEscola($pdo, $statusFiltro, $tipoFiltro, $soTravados, 80);
                $nomesCache = self::carregarNomesEmLote($pdo, $rows);
                foreach ($rows as $row) {
                    $job = self::enriquecerJob($pdo, $row, $escola, $nomesCache);
                    if ($q !== '' && !self::jobCasaBusca($job, $q)) {
                        continue;
                    }
                    $todos[] = $job;
                }
            } catch (\Throwable $e) {
                error_log('[MasterFilaIa] escola=' . $eid . ' ' . $e->getMessage());
                $erros[] = 'Falha ao ler fila: ' . ($escola['nome'] ?? (string) $eid);
            }
        }

        usort($todos, static function (array $a, array $b): int {
            $ta = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
            $tb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
            if ($ta === $tb) {
                return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
            }
            return $tb <=> $ta;
        });

        $total = count($todos);
        $offset = ($page - 1) * $perPage;
        $pageJobs = array_slice($todos, $offset, $perPage);

        return [
            'kpis' => $kpis,
            'jobs' => $pageJobs,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'erros' => $erros,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function detalheJob(int $escolaId, int $jobId): ?array
    {
        if ($escolaId < 1 || $jobId < 1) {
            return null;
        }
        $conn = \MasterTenantConnection::getPdoAndEscola($escolaId);
        if ($conn === null || empty($conn['pdo'])) {
            return null;
        }
        /** @var \PDO $pdo */
        $pdo = $conn['pdo'];
        if (!self::tabelaExiste($pdo, 'ai_jobs')) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM ai_jobs WHERE id = ? LIMIT 1');
        $stmt->execute([$jobId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $escola = [
            'id' => (int) ($conn['escola']['id'] ?? $escolaId),
            'nome' => (string) ($conn['escola']['nome'] ?? ''),
            'slug' => (string) ($conn['escola']['slug'] ?? ''),
        ];
        $nomesCache = self::carregarNomesEmLote($pdo, [$row]);
        $job = self::enriquecerJob($pdo, $row, $escola, $nomesCache);
        $job['payload_decoded'] = self::decodeJson($row['payload'] ?? null);
        $job['result_decoded'] = self::decodeJson($row['result'] ?? null);
        return $job;
    }

    /**
     * @return array{items:list<array>,total:int,page:int,per_page:int,tabela_ok:bool}
     */
    public static function listarCronExecucoes(string $script = '', int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        $pdo = CronExecucaoService::masterPdo();
        if (!$pdo || !CronExecucaoService::tabelasDisponiveis($pdo)) {
            return [
                'items' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'tabela_ok' => false,
            ];
        }

        $where = '1=1';
        $params = [];
        if ($script !== '') {
            $where .= ' AND script = :script';
            $params['script'] = substr($script, 0, 64);
        }

        $stCount = $pdo->prepare("SELECT COUNT(*) FROM cron_execucoes WHERE {$where}");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM cron_execucoes WHERE {$where} ORDER BY iniciado_em DESC, id DESC LIMIT {$perPage} OFFSET {$offset}";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $items = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'tabela_ok' => true,
        ];
    }

    /**
     * @return array{execucao:array,escolas:list<array>}|null
     */
    public static function detalheCronExecucao(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $pdo = CronExecucaoService::masterPdo();
        if (!$pdo || !CronExecucaoService::tabelasDisponiveis($pdo)) {
            return null;
        }
        $st = $pdo->prepare('SELECT * FROM cron_execucoes WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $exec = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$exec) {
            return null;
        }
        $stE = $pdo->prepare(
            'SELECT * FROM cron_execucoes_escolas WHERE cron_execucao_id = ? ORDER BY id ASC'
        );
        $stE->execute([$id]);
        $escolas = $stE->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return [
            'execucao' => $exec,
            'escolas' => $escolas,
        ];
    }

    /**
     * Última execução do script (para KPI no topo).
     * @return array<string,mixed>|null
     */
    public static function ultimaCronExecucao(string $script): ?array
    {
        $pdo = CronExecucaoService::masterPdo();
        if (!$pdo || !CronExecucaoService::tabelasDisponiveis($pdo)) {
            return null;
        }
        $st = $pdo->prepare(
            'SELECT * FROM cron_execucoes WHERE script = ? ORDER BY iniciado_em DESC, id DESC LIMIT 1'
        );
        $st->execute([substr($script, 0, 64)]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function tabelaExiste(\PDO $pdo, string $table): bool
    {
        try {
            $pdo->query('SELECT 1 FROM `' . str_replace('`', '', $table) . '` LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array{pending:int,processing:int,failed:int,done:int}
     */
    private static function contarPorStatus(\PDO $pdo): array
    {
        $out = ['pending' => 0, 'processing' => 0, 'failed' => 0, 'done' => 0];
        $st = $pdo->query(
            "SELECT status, COUNT(*) AS total FROM ai_jobs GROUP BY status"
        );
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $s = (string) ($row['status'] ?? '');
            if (isset($out[$s])) {
                $out[$s] = (int) $row['total'];
            }
        }
        return $out;
    }

    private static function contarTravados(\PDO $pdo): int
    {
        $proc = (int) self::STUCK_PROCESSING_MINUTES;
        $pend = (int) self::STUCK_PENDING_MINUTES;
        $st = $pdo->query(
            "SELECT COUNT(*) FROM ai_jobs
             WHERE (
                (status = 'processing' AND started_at IS NOT NULL AND started_at < (NOW() - INTERVAL {$proc} MINUTE))
                OR
                (status = 'pending' AND created_at < (NOW() - INTERVAL {$pend} MINUTE) AND attempts < 3)
             )"
        );
        return (int) $st->fetchColumn();
    }

    /**
     * @return list<array>
     */
    private static function buscarJobsEscola(
        \PDO $pdo,
        string $statusFiltro,
        string $tipoFiltro,
        bool $soTravados,
        int $limit
    ): array {
        $where = ['1=1'];
        $params = [];
        if ($statusFiltro !== '') {
            $where[] = 'status = ?';
            $params[] = $statusFiltro;
        }
        if ($tipoFiltro !== '') {
            $where[] = 'job_type = ?';
            $params[] = $tipoFiltro;
        }
        if ($soTravados) {
            $proc = (int) self::STUCK_PROCESSING_MINUTES;
            $pend = (int) self::STUCK_PENDING_MINUTES;
            $where[] = "(
                (status = 'processing' AND started_at IS NOT NULL AND started_at < (NOW() - INTERVAL {$proc} MINUTE))
                OR
                (status = 'pending' AND created_at < (NOW() - INTERVAL {$pend} MINUTE) AND attempts < 3)
            )";
        } elseif ($statusFiltro === '') {
            // Na listagem geral prioriza fila ativa; done só se filtrar explicitamente
            $where[] = "status IN ('pending','processing','failed')";
        }

        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM ai_jobs WHERE ' . implode(' AND ', $where)
            . " ORDER BY created_at DESC, id DESC LIMIT {$limit}";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{aluno: array<int,string>, professor: array<int,string>, usuario: array<int,string>}
     */
    private static function carregarNomesEmLote(\PDO $pdo, array $rows): array
    {
        $ids = [
            'aluno' => [],
            'professor' => [],
            'usuario' => [],
        ];
        foreach ($rows as $row) {
            $userId = isset($row['user_id']) ? (int) $row['user_id'] : 0;
            $userRole = strtolower(trim((string) ($row['user_role'] ?? '')));
            $payload = self::decodeJson($row['payload'] ?? null);
            $alunoIdPayload = (int) ($payload['aluno_id'] ?? 0);
            if ($alunoIdPayload > 0) {
                $ids['aluno'][$alunoIdPayload] = true;
            }
            if ($userId < 1) {
                continue;
            }
            if ($userRole === 'aluno') {
                $ids['aluno'][$userId] = true;
            } elseif ($userRole === 'professor') {
                $ids['professor'][$userId] = true;
            } else {
                $ids['usuario'][$userId] = true;
            }
        }

        return [
            'aluno' => self::buscarNomesPorIds($pdo, 'alunos', array_keys($ids['aluno'])),
            'professor' => self::buscarNomesPorIds($pdo, 'professores', array_keys($ids['professor'])),
            'usuario' => self::buscarNomesPorIds($pdo, 'usuarios', array_keys($ids['usuario'])),
        ];
    }

    /**
     * @param list<int> $ids
     * @return array<int, string>
     */
    private static function buscarNomesPorIds(\PDO $pdo, string $tabela, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $tabelasOk = ['alunos' => true, 'professores' => true, 'usuarios' => true];
        if (!isset($tabelasOk[$tabela])) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            $st = $pdo->prepare("SELECT id, nome FROM {$tabela} WHERE id IN ({$placeholders})");
            $st->execute($ids);
            $map = [];
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $map[(int) $row['id']] = (string) ($row['nome'] ?? '');
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string,mixed> $row
     * @param array{id?:int|string,nome?:string,slug?:string} $escola
     * @param array{aluno?:array<int,string>,professor?:array<int,string>,usuario?:array<int,string>}|null $nomesCache
     * @return array<string,mixed>
     */
    private static function enriquecerJob(\PDO $pdo, array $row, array $escola, ?array $nomesCache = null): array
    {
        $userId = isset($row['user_id']) ? (int) $row['user_id'] : 0;
        $userRole = strtolower(trim((string) ($row['user_role'] ?? '')));
        $payload = self::decodeJson($row['payload'] ?? null);
        $alunoIdPayload = (int) ($payload['aluno_id'] ?? 0);
        $nomesCache = $nomesCache ?? ['aluno' => [], 'professor' => [], 'usuario' => []];

        $usuarioNome = null;
        $usuarioTipo = $userRole !== '' ? $userRole : null;
        if ($userId > 0) {
            if ($userRole === 'aluno') {
                $usuarioNome = $nomesCache['aluno'][$userId] ?? self::buscarNomeUsuario($pdo, 'aluno', $userId);
            } elseif ($userRole === 'professor') {
                $usuarioNome = $nomesCache['professor'][$userId] ?? self::buscarNomeUsuario($pdo, 'professor', $userId);
            } else {
                $usuarioNome = $nomesCache['usuario'][$userId] ?? self::buscarNomeUsuario($pdo, $userRole, $userId);
            }
        }

        $alunoNome = null;
        $alunoId = $alunoIdPayload > 0 ? $alunoIdPayload : (($userRole === 'aluno' && $userId > 0) ? $userId : 0);
        if ($alunoId > 0) {
            $alunoNome = $nomesCache['aluno'][$alunoId] ?? self::buscarNomeUsuario($pdo, 'aluno', $alunoId);
        }

        $professorNome = null;
        $professorId = ($userRole === 'professor' && $userId > 0) ? $userId : 0;
        if ($professorId > 0) {
            $professorNome = $usuarioNome;
        }

        $status = (string) ($row['status'] ?? '');
        $travado = self::jobEstaTravado($row);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'escola_id' => (int) ($escola['id'] ?? 0),
            'escola_nome' => (string) ($escola['nome'] ?? ''),
            'escola_slug' => (string) ($escola['slug'] ?? ''),
            'job_type' => (string) ($row['job_type'] ?? ''),
            'status' => $status,
            'error_message' => $row['error_message'] ?? null,
            'user_id' => $userId > 0 ? $userId : null,
            'user_role' => $usuarioTipo,
            'usuario_nome' => $usuarioNome,
            'aluno_id' => $alunoId > 0 ? $alunoId : null,
            'aluno_nome' => $alunoNome,
            'professor_id' => $professorId > 0 ? $professorId : null,
            'professor_nome' => $professorNome,
            'attempts' => (int) ($row['attempts'] ?? 0),
            'created_at' => $row['created_at'] ?? null,
            'started_at' => $row['started_at'] ?? null,
            'completed_at' => $row['completed_at'] ?? null,
            'travado' => $travado,
            'payload_resumo' => self::resumoPayload($payload),
        ];
    }

    /**
     * @param array<string,mixed> $job
     */
    private static function jobCasaBusca(array $job, string $q): bool
    {
        $q = mb_strtolower($q);
        $hay = mb_strtolower(implode(' ', [
            (string) ($job['job_type'] ?? ''),
            (string) ($job['escola_nome'] ?? ''),
            (string) ($job['usuario_nome'] ?? ''),
            (string) ($job['aluno_nome'] ?? ''),
            (string) ($job['professor_nome'] ?? ''),
            (string) ($job['user_id'] ?? ''),
            (string) ($job['error_message'] ?? ''),
            (string) ($job['id'] ?? ''),
        ]));
        return str_contains($hay, $q);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function jobEstaTravado(array $row): bool
    {
        $status = (string) ($row['status'] ?? '');
        $now = time();
        if ($status === 'processing') {
            $started = strtotime((string) ($row['started_at'] ?? '')) ?: 0;
            return $started > 0 && ($now - $started) >= (self::STUCK_PROCESSING_MINUTES * 60);
        }
        if ($status === 'pending') {
            $created = strtotime((string) ($row['created_at'] ?? '')) ?: 0;
            $attempts = (int) ($row['attempts'] ?? 0);
            return $created > 0
                && $attempts < 3
                && ($now - $created) >= (self::STUCK_PENDING_MINUTES * 60);
        }
        return false;
    }

    private static function buscarNomeUsuario(\PDO $pdo, string $role, int $id): ?string
    {
        if ($id < 1) {
            return null;
        }
        try {
            if ($role === 'aluno') {
                $st = $pdo->prepare('SELECT nome FROM alunos WHERE id = ? LIMIT 1');
            } elseif ($role === 'professor') {
                $st = $pdo->prepare('SELECT nome FROM professores WHERE id = ? LIMIT 1');
            } else {
                $st = $pdo->prepare('SELECT nome FROM usuarios WHERE id = ? LIMIT 1');
            }
            $st->execute([$id]);
            $nome = $st->fetchColumn();
            return $nome !== false && $nome !== null && $nome !== '' ? (string) $nome : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function decodeJson($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function resumoPayload(array $payload): string
    {
        if ($payload === []) {
            return '';
        }
        $keys = ['aluno_id', 'prova_id', 'jornada_id', 'redacao_id', 'essay_id', 'turma_id', 'titulo', 'tema'];
        $parts = [];
        foreach ($keys as $k) {
            if (!array_key_exists($k, $payload) || $payload[$k] === null || $payload[$k] === '') {
                continue;
            }
            $v = $payload[$k];
            if (is_scalar($v)) {
                $parts[] = $k . '=' . (string) $v;
            }
            if (count($parts) >= 4) {
                break;
            }
        }
        return implode(' · ', $parts);
    }
}
