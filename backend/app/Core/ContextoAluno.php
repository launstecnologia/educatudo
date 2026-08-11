<?php
/**
 * Contexto do aluno logado — sessão + cache por request.
 *
 * Segurança:
 * - O id do aluno vem SOMENTE de $_SESSION['user_id'] (nunca de GET/POST).
 * - Bloco aluno_ctx é validado contra user_id a cada resolução.
 * - Limpo no logout (AuthManager).
 */
class ContextoAluno
{
    private const SESSION_KEY = 'aluno_ctx';
    private const VERSION = 1;

    /** @var array<string, array<string, mixed>|null> */
    private static array $requestCache = [];

    /**
     * Popula/atualiza a sessão após login ou refresh explícito.
     */
    public static function gravarSessaoNoLogin($db, int $alunoId): void
    {
        if ($alunoId <= 0 || php_sapi_name() === 'cli') {
            return;
        }

        $row = $db->fetch(
            'SELECT a.id, a.nome, a.nickname, a.email, a.ra, a.turma_id, a.serie, t.nome AS turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = :id AND a.ativo = 1
             LIMIT 1',
            ['id' => $alunoId]
        );
        if (!$row) {
            self::limparSessao();
            return;
        }

        $avatarUrl = null;
        try {
            $av = $db->fetch(
                'SELECT avatar_url FROM avatares_alunos WHERE aluno_id = :aluno_id LIMIT 1',
                ['aluno_id' => $alunoId]
            );
            $avatarUrl = is_array($av) ? ($av['avatar_url'] ?? null) : null;
        } catch (\Throwable $e) {
        }

        if (!class_exists('AlunoTurmaHelper')) {
            require_once __DIR__ . '/AlunoTurmaHelper.php';
        }
        $turmaIds = AlunoTurmaHelper::getTurmaIds($db, $alunoId, true);

        $onboardingCompletado = false;
        try {
            $ob = $db->fetch(
                'SELECT completado FROM alunos_onboarding WHERE aluno_id = :aluno_id LIMIT 1',
                ['aluno_id' => $alunoId]
            );
            $onboardingCompletado = is_array($ob) && (int) ($ob['completado'] ?? 0) === 1;
        } catch (\Throwable $e) {
        }

        $turmasSelect = self::montarOpcoesTurmasCursos($db, $alunoId, $row);

        $_SESSION[self::SESSION_KEY] = [
            'v' => self::VERSION,
            'id' => (int) $row['id'],
            'nome' => (string) ($row['nome'] ?? ''),
            'nickname' => (string) ($row['nickname'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'ra' => (string) ($row['ra'] ?? ''),
            'turma_id' => (int) ($row['turma_id'] ?? 0),
            'turma_nome' => (string) ($row['turma_nome'] ?? ''),
            'serie' => (string) ($row['serie'] ?? ''),
            'avatar_url' => is_string($avatarUrl) ? self::normalizarUrlAvatar(trim($avatarUrl)) : null,
            'turma_ids' => $turmaIds,
            'onboarding_completado' => $onboardingCompletado,
            'turmas_cursos_select' => $turmasSelect,
        ];

        self::sincronizarChavesLegadasSessao();
        self::invalidarRequestCache();
    }

    /**
     * Remove dados do aluno da sessão (logout).
     */
    public static function limparSessao(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        self::invalidarRequestCache();
    }

    /**
     * Id do aluno autenticado — única fonte confiável para ownership.
     */
    public static function idDaSessao(): int
    {
        if (($_SESSION['user_type'] ?? '') !== 'aluno') {
            return 0;
        }
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    /**
     * Resolve contexto do aluno logado (sessão; 1 SELECT de fallback se sessão incompleta).
     *
     * @return array<string, mixed>|null Formato compatível com controllers/views (inclui turma_nome, turma_ids).
     */
    public static function resolver($db): ?array
    {
        $alunoId = self::idDaSessao();
        if ($alunoId <= 0) {
            return null;
        }

        $cacheKey = self::requestCacheKey($alunoId);
        if (array_key_exists($cacheKey, self::$requestCache)) {
            return self::$requestCache[$cacheKey];
        }

        $ctx = $_SESSION[self::SESSION_KEY] ?? null;
        if (!self::sessaoValida($ctx, $alunoId)) {
            self::gravarSessaoNoLogin($db, $alunoId);
            $ctx = $_SESSION[self::SESSION_KEY] ?? null;
        } elseif (self::sessaoSemTurmaMasDbTem($ctx, $db, $alunoId)) {
            self::gravarSessaoNoLogin($db, $alunoId);
            $ctx = $_SESSION[self::SESSION_KEY] ?? null;
        }

        if (!self::sessaoValida($ctx, $alunoId)) {
            self::$requestCache[$cacheKey] = null;
            return null;
        }

        $aluno = self::ctxParaArrayAluno($ctx);
        self::$requestCache[$cacheKey] = $aluno;

        return $aluno;
    }

    /**
     * @return list<int>
     */
    public static function getTurmaIds($db): array
    {
        $aluno = self::resolver($db);
        if (!$aluno) {
            return [];
        }
        $ids = $aluno['turma_ids'] ?? [];
        if (!is_array($ids)) {
            return [];
        }
        return array_values(array_map('intval', $ids));
    }

    public static function getAvatarUrl($db): ?string
    {
        $aluno = self::resolver($db);
        if (!$aluno) {
            return null;
        }
        $url = $aluno['avatar_url'] ?? null;
        if (!is_string($url) || trim($url) === '') {
            return null;
        }
        return self::normalizarUrlAvatar($url);
    }

    /**
     * Docroot já é public/: URLs legadas /public/assets|uploads quebram na VPS.
     */
    public static function normalizarUrlAvatar(string $url): string
    {
        $url = str_replace('/public/assets/', '/assets/', $url);
        return str_replace('/public/uploads/', '/uploads/', $url);
    }

    /**
     * @return array<int, array{turma_id:int, label:string}>
     */
    public static function getTurmasCursosSelect($db): array
    {
        $aluno = self::resolver($db);
        if (!$aluno) {
            return [];
        }
        $opts = $aluno['turmas_cursos_select'] ?? [];
        return is_array($opts) ? $opts : [];
    }

    /**
     * Após trocar turma ativa — atualiza banco já foi feito pelo caller.
     */
    public static function atualizarTurmaAtiva($db, int $turmaId): void
    {
        $alunoId = self::idDaSessao();
        if ($alunoId <= 0 || $turmaId <= 0) {
            return;
        }

        $turma = $db->fetch(
            'SELECT nome FROM turmas WHERE id = :id LIMIT 1',
            ['id' => $turmaId]
        );
        $turmaNome = is_array($turma) ? (string) ($turma['nome'] ?? '') : '';

        $_SESSION['turma_id'] = $turmaId;
        $_SESSION['turma_nome'] = $turmaNome;

        if (isset($_SESSION[self::SESSION_KEY]) && is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY]['turma_id'] = $turmaId;
            $_SESSION[self::SESSION_KEY]['turma_nome'] = $turmaNome;
        }

        self::gravarSessaoNoLogin($db, $alunoId);
    }

    /**
     * @param mixed $ctx
     */
    private static function sessaoValida($ctx, int $alunoId): bool
    {
        return is_array($ctx)
            && (int) ($ctx['id'] ?? 0) === $alunoId
            && (int) ($ctx['v'] ?? 0) === self::VERSION
            && isset($ctx['nome']);
    }

    /**
     * Sessão antiga (login antes de matrícula/troca de turma no admin ou seed local).
     *
     * @param mixed $ctx
     */
    private static function sessaoSemTurmaMasDbTem($ctx, $db, int $alunoId): bool
    {
        if (!is_array($ctx) || (int) ($ctx['turma_id'] ?? 0) > 0) {
            return false;
        }
        if (!empty($ctx['turma_ids']) && is_array($ctx['turma_ids'])) {
            return false;
        }

        $row = $db->fetch(
            'SELECT turma_id FROM alunos WHERE id = :id AND ativo = 1 LIMIT 1',
            ['id' => $alunoId]
        );
        return is_array($row) && (int) ($row['turma_id'] ?? 0) > 0;
    }

    /**
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    private static function ctxParaArrayAluno(array $ctx): array
    {
        return [
            'id' => (int) ($ctx['id'] ?? 0),
            'nome' => (string) ($ctx['nome'] ?? ''),
            'nickname' => (string) ($ctx['nickname'] ?? ''),
            'email' => (string) ($ctx['email'] ?? ''),
            'ra' => (string) ($ctx['ra'] ?? ''),
            'turma_id' => (int) ($ctx['turma_id'] ?? 0),
            'turma_nome' => (string) ($ctx['turma_nome'] ?? ''),
            'serie' => (string) ($ctx['serie'] ?? ''),
            'avatar_url' => $ctx['avatar_url'] ?? null,
            'turma_ids' => is_array($ctx['turma_ids'] ?? null) ? $ctx['turma_ids'] : [],
            'onboarding_completado' => !empty($ctx['onboarding_completado']),
            'turmas_cursos_select' => is_array($ctx['turmas_cursos_select'] ?? null) ? $ctx['turmas_cursos_select'] : [],
        ];
    }

    private static function sincronizarChavesLegadasSessao(): void
    {
        $ctx = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($ctx)) {
            return;
        }
        $_SESSION['user_id'] = (int) ($ctx['id'] ?? $_SESSION['user_id'] ?? 0);
        $_SESSION['user_name'] = (string) ($ctx['nome'] ?? $_SESSION['user_name'] ?? '');
        $_SESSION['ra'] = (string) ($ctx['ra'] ?? '');
        $_SESSION['turma_id'] = (int) ($ctx['turma_id'] ?? 0);
        $_SESSION['turma_nome'] = (string) ($ctx['turma_nome'] ?? '');
        $_SESSION['serie'] = (string) ($ctx['serie'] ?? '');
        if (!empty($ctx['avatar_url']) && is_string($ctx['avatar_url'])) {
            $_SESSION['avatar_url'] = $ctx['avatar_url'];
        }
    }

    /**
     * @param array<string, mixed> $alunoRow
     * @return array<int, array{turma_id:int, label:string}>
     */
    private static function montarOpcoesTurmasCursos($db, int $alunoId, array $alunoRow): array
    {
        $out = [];
        try {
            $hasMatricula = $db->fetch("SHOW TABLES LIKE 'matricula'");
            if ($hasMatricula) {
                $rows = $db->fetchAll(
                    "SELECT m.turma_id, t.nome AS turma_nome, MAX(al.ano) AS ano_letivo_ano
                     FROM matricula m
                     INNER JOIN turmas t ON t.id = m.turma_id AND (t.ativo = 1 OR t.ativo IS NULL)
                     INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id
                     WHERE m.aluno_id = :aluno_id AND m.status = 'ativa'
                     GROUP BY m.turma_id, t.nome
                     ORDER BY t.nome ASC",
                    ['aluno_id' => $alunoId]
                ) ?: [];
                foreach ($rows as $r) {
                    $tid = (int) ($r['turma_id'] ?? 0);
                    if ($tid <= 0) {
                        continue;
                    }
                    $nome = trim((string) ($r['turma_nome'] ?? ''));
                    $ano = isset($r['ano_letivo_ano']) ? (int) $r['ano_letivo_ano'] : 0;
                    $label = $nome !== '' ? $nome : ('Turma #' . $tid);
                    if ($ano > 0) {
                        $label .= ' (' . $ano . ')';
                    }
                    $out[] = ['turma_id' => $tid, 'label' => $label];
                }
            }
        } catch (\Throwable $e) {
            $out = [];
        }

        $ids = array_column($out, 'turma_id');
        $curTid = (int) ($alunoRow['turma_id'] ?? 0);
        if ($curTid > 0 && !in_array($curTid, $ids, true)) {
            $nome = trim((string) ($alunoRow['turma_nome'] ?? ''));
            $out[] = [
                'turma_id' => $curTid,
                'label' => $nome !== '' ? $nome : ('Turma #' . $curTid),
            ];
        }

        return $out;
    }

    private static function requestCacheKey(int $alunoId): string
    {
        $scope = defined('TENANT_SLUG') ? (string) TENANT_SLUG : 'default';
        return $scope . ':' . $alunoId;
    }

    private static function invalidarRequestCache(): void
    {
        self::$requestCache = [];
    }
}
