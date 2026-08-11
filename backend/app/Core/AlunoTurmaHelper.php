<?php
/**
 * Turmas efetivas do aluno: principal (alunos.turma_id) + matrículas ativas.
 */
class AlunoTurmaHelper
{
    /** @var array<string, list<int>> */
    private static array $turmaIdsCache = [];

    /** @var array<string, bool> */
    private static array $matriculaTableExists = [];

    private static function tenantCacheKey(int $alunoId): string
    {
        if (defined('TENANT_SLUG') && (string) TENANT_SLUG !== '') {
            return (string) TENANT_SLUG . ':' . $alunoId;
        }
        if (defined('TENANT_ID') && (int) TENANT_ID > 0) {
            return 'id' . (int) TENANT_ID . ':' . $alunoId;
        }
        return 'default:' . $alunoId;
    }

    private static function tenantScopeKey(): string
    {
        if (defined('TENANT_SLUG') && (string) TENANT_SLUG !== '') {
            return (string) TENANT_SLUG;
        }
        if (defined('TENANT_ID') && (int) TENANT_ID > 0) {
            return 'id' . (int) TENANT_ID;
        }
        return 'default';
    }

    /**
     * @return list<int>
     */
    public static function getTurmaIds($db, int $alunoId, bool $forcarDb = false): array
    {
        if ($alunoId <= 0) {
            return [];
        }
        $cacheKey = self::tenantCacheKey($alunoId);
        if (!$forcarDb && isset(self::$turmaIdsCache[$cacheKey])) {
            return self::$turmaIdsCache[$cacheKey];
        }
        if ($forcarDb) {
            unset(self::$turmaIdsCache[$cacheKey]);
        }

        if (!$forcarDb) {
            if (!class_exists('ContextoAluno')) {
                require_once __DIR__ . '/ContextoAluno.php';
            }
            if (ContextoAluno::idDaSessao() === $alunoId) {
                $ctx = $_SESSION['aluno_ctx'] ?? null;
                if (is_array($ctx) && (int) ($ctx['id'] ?? 0) === $alunoId && !empty($ctx['turma_ids']) && is_array($ctx['turma_ids'])) {
                    $fromSession = array_values(array_map('intval', $ctx['turma_ids']));
                    sort($fromSession);
                    self::$turmaIdsCache[$cacheKey] = $fromSession;
                    return $fromSession;
                }
            }
        }

        $aluno = $db->fetch(
            'SELECT turma_id FROM alunos WHERE id = :id LIMIT 1',
            ['id' => $alunoId]
        );
        if (!$aluno) {
            self::$turmaIdsCache[$cacheKey] = [];
            return [];
        }

        $turmaIds = [];
        $principal = (int) ($aluno['turma_id'] ?? 0);
        if ($principal > 0) {
            $turmaIds[] = $principal;
        }

        try {
            if (self::matriculaTableExists($db)) {
                $mats = $db->fetchAll(
                    "SELECT DISTINCT turma_id FROM matricula
                     WHERE aluno_id = :aluno_id AND status = 'ativa' AND data_saida IS NULL",
                    ['aluno_id' => $alunoId]
                ) ?: [];
                foreach ($mats as $m) {
                    $tid = (int) ($m['turma_id'] ?? 0);
                    if ($tid > 0) {
                        $turmaIds[] = $tid;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Sem tabela de matrícula: usa apenas a turma principal.
        }

        $turmaIds = array_values(array_unique($turmaIds));
        sort($turmaIds);
        self::$turmaIdsCache[$cacheKey] = $turmaIds;

        return $turmaIds;
    }

    private static function matriculaTableExists($db): bool
    {
        $scope = self::tenantScopeKey();
        if (isset(self::$matriculaTableExists[$scope])) {
            return self::$matriculaTableExists[$scope];
        }
        try {
            self::$matriculaTableExists[$scope] = $db->fetch("SHOW TABLES LIKE 'matricula'") !== false;
        } catch (\Throwable $e) {
            self::$matriculaTableExists[$scope] = false;
        }
        return self::$matriculaTableExists[$scope];
    }

    /**
     * Chave estável para cache/listagem quando o aluno tem várias turmas.
     */
    public static function getTurmaIdsCacheToken(array $turmaIds): string
    {
        if ($turmaIds === []) {
            return '0';
        }
        return implode(',', $turmaIds);
    }
}
