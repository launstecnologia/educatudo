<?php

namespace App\Modulos\EducaSync\Models;

use App\Core\Database;

/**
 * EducaSync — versão (resultado de uma execução do solver) de um cenário.
 */
class Versao
{
    public const STATUS = [
        'gerada' => 'Gerada',
        'selecionada' => 'Selecionada',
        'publicada' => 'Publicada',
        'descartada' => 'Descartada',
    ];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function criar(int $cenarioId, string $solverStatus, array $metricas): int
    {
        return (int) $this->db->insert(
            "INSERT INTO educasync_versoes (cenario_id, status, metricas_json, solver_status)
             VALUES (:cenario_id, 'gerada', :metricas_json, :solver_status)",
            [
                'cenario_id' => (int) $cenarioId,
                'metricas_json' => json_encode($metricas, JSON_UNESCAPED_UNICODE),
                'solver_status' => substr($solverStatus, 0, 20),
            ]
        );
    }

    public function findById(int $id): ?array
    {
        $linha = $this->db->fetch(
            'SELECT * FROM educasync_versoes WHERE id = :id',
            ['id' => (int) $id]
        );
        return $linha ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarPorCenario(int $cenarioId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM educasync_versoes WHERE cenario_id = :cenario_id ORDER BY id DESC',
            ['cenario_id' => (int) $cenarioId]
        ) ?: [];
    }

    public function atualizarStatus(int $id, string $status): void
    {
        $this->db->update(
            'UPDATE educasync_versoes SET status = :status WHERE id = :id',
            ['status' => $status, 'id' => (int) $id]
        );
    }

    /**
     * Devolve a versão publicada anterior do cenário ao estado "gerada" (só uma fica publicada).
     */
    public function despublicarAnteriores(int $cenarioId, int $exceptoVersaoId): void
    {
        $this->db->update(
            "UPDATE educasync_versoes SET status = 'gerada'
             WHERE cenario_id = :cenario_id AND status = 'publicada' AND id <> :versao_id",
            ['cenario_id' => (int) $cenarioId, 'versao_id' => (int) $exceptoVersaoId]
        );
    }

    public function registrarPublicacao(int $versaoId, int $usuarioId, string $modo, array $diff): int
    {
        return (int) $this->db->insert(
            'INSERT INTO educasync_publicacoes (versao_id, usuario_id, modo, diff_json)
             VALUES (:versao_id, :usuario_id, :modo, :diff_json)',
            [
                'versao_id' => (int) $versaoId,
                'usuario_id' => $usuarioId > 0 ? (int) $usuarioId : null,
                'modo' => $modo,
                'diff_json' => json_encode($diff, JSON_UNESCAPED_UNICODE),
            ]
        );
    }
}
