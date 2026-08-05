<?php

if (!class_exists('VersaoAdaptadaLog')) {
/**
 * Auditoria append-only do EducaInclui. Nunca atualizar/excluir registros.
 */
class VersaoAdaptadaLog
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function record(string $acao, array $context = []): void
    {
        $acao = trim($acao);
        if ($acao === '') {
            return;
        }
        $details = $context['details'] ?? [];
        try {
            $this->db->insert(
                "INSERT INTO versoes_adaptadas_logs
                 (versao_adaptada_id, mascara_id, aluno_id, prova_id, acao, user_id, ip, detalhes_json, created_at)
                 VALUES (:versao_adaptada_id, :mascara_id, :aluno_id, :prova_id, :acao, :user_id, :ip, :detalhes_json, NOW())",
                [
                    'versao_adaptada_id' => $context['versao_adaptada_id'] ?? null,
                    'mascara_id' => $context['mascara_id'] ?? null,
                    'aluno_id' => $context['aluno_id'] ?? null,
                    'prova_id' => $context['prova_id'] ?? null,
                    'acao' => $acao,
                    'user_id' => $context['user_id'] ?? null,
                    'ip' => $context['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
                    'detalhes_json' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                ]
            );
        } catch (Throwable $e) {
            error_log('EducaInclui audit log falhou: ' . $e->getMessage());
        }
    }

    public function countDistinctApprovers(int $mascaraId, string $acao): int
    {
        if ($mascaraId <= 0) {
            return 0;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(DISTINCT user_id) AS c FROM versoes_adaptadas_logs
             WHERE mascara_id = :id AND acao = :a AND user_id IS NOT NULL",
            ['id' => $mascaraId, 'a' => $acao]
        );
        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listApprovers(int $mascaraId, string $acao): array
    {
        if ($mascaraId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT l.user_id, MAX(l.created_at) AS aprovado_em, u.nome
             FROM versoes_adaptadas_logs l
             LEFT JOIN usuarios u ON u.id = l.user_id
             WHERE l.mascara_id = :id AND l.acao = :a AND l.user_id IS NOT NULL
             GROUP BY l.user_id, u.nome
             ORDER BY aprovado_em ASC",
            ['id' => $mascaraId, 'a' => $acao]
        );
    }

    /** Retorna o detalhes_json decodificado do log mais recente de uma ação. */
    public function latestDetailsByAction(int $mascaraId, string $acao): ?array
    {
        if ($mascaraId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT detalhes_json, created_at FROM versoes_adaptadas_logs
             WHERE mascara_id = :id AND acao = :a
             ORDER BY id DESC LIMIT 1",
            ['id' => $mascaraId, 'a' => $acao]
        );
        if (!$row) {
            return null;
        }
        $details = json_decode((string) ($row['detalhes_json'] ?? ''), true);
        if (!is_array($details)) {
            $details = [];
        }
        $details['_created_at'] = $row['created_at'] ?? null;
        return $details;
    }

    public function latestDetailsByVersionAction(int $versionId, string $acao): ?array
    {
        if ($versionId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT detalhes_json, created_at FROM versoes_adaptadas_logs
             WHERE versao_adaptada_id = :id AND acao = :a
             ORDER BY id DESC LIMIT 1",
            ['id' => $versionId, 'a' => $acao]
        );
        if (!$row) {
            return null;
        }
        $details = json_decode((string) ($row['detalhes_json'] ?? ''), true);
        if (!is_array($details)) {
            $details = [];
        }
        $details['_created_at'] = $row['created_at'] ?? null;
        return $details;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listDetailsByVersionAction(int $versionId, string $acao, int $limit = 50): array
    {
        if ($versionId <= 0) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $rows = $this->db->fetchAll(
            "SELECT detalhes_json, created_at FROM versoes_adaptadas_logs
             WHERE versao_adaptada_id = :id AND acao = :a
             ORDER BY id DESC
             LIMIT " . (int) $limit,
            ['id' => $versionId, 'a' => $acao]
        );
        $items = [];
        foreach ($rows as $row) {
            $details = json_decode((string) ($row['detalhes_json'] ?? ''), true);
            if (!is_array($details)) {
                $details = [];
            }
            $details['_created_at'] = $row['created_at'] ?? null;
            $items[] = $details;
        }
        return $items;
    }

    public function userHasApproved(int $mascaraId, string $acao, int $userId): bool
    {
        if ($mascaraId <= 0 || $userId <= 0) {
            return false;
        }
        $row = $this->db->fetch(
            "SELECT 1 AS ok FROM versoes_adaptadas_logs
             WHERE mascara_id = :id AND acao = :a AND user_id = :u LIMIT 1",
            ['id' => $mascaraId, 'a' => $acao, 'u' => $userId]
        );
        return !empty($row['ok']);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarPorMascara(int $mascaraId, int $limit = 50): array
    {
        if ($mascaraId <= 0) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        return $this->db->fetchAll(
            "SELECT * FROM versoes_adaptadas_logs
             WHERE mascara_id = :id
             ORDER BY id DESC
             LIMIT " . (int) $limit,
            ['id' => $mascaraId]
        );
    }
}
}
