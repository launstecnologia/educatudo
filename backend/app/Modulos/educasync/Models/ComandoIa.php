<?php

namespace App\Modulos\EducaSync\Models;

use App\Core\Database;

/**
 * EducaSync — auditoria do assistente (educasync_comandos_ia).
 * Uma linha por mensagem: prompt, proposta tipada (se houver) e se o usuário confirmou.
 */
class ComandoIa
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function criar(int $cenarioId, int $usuarioId, string $prompt): int
    {
        return (int) $this->db->insert(
            'INSERT INTO educasync_comandos_ia (cenario_id, usuario_id, prompt, confirmado)
             VALUES (:cenario_id, :usuario_id, :prompt, 0)',
            [
                'cenario_id' => (int) $cenarioId,
                'usuario_id' => $usuarioId > 0 ? (int) $usuarioId : null,
                'prompt' => mb_substr($prompt, 0, 4000),
            ]
        );
    }

    /**
     * Grava (ou troca) o rascunho da proposta. Só mexe em comando ainda não confirmado.
     *
     * @param array<string,mixed> $proposta
     */
    public function gravarProposta(int $id, int $cenarioId, array $proposta): bool
    {
        return (int) $this->db->update(
            'UPDATE educasync_comandos_ia SET proposta_json = :proposta_json
             WHERE id = :id AND cenario_id = :cenario_id AND confirmado = 0',
            [
                'proposta_json' => json_encode($proposta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'id' => (int) $id,
                'cenario_id' => (int) $cenarioId,
            ]
        ) > 0;
    }

    public function findDoCenario(int $id, int $cenarioId): ?array
    {
        $linha = $this->db->fetch(
            'SELECT id, cenario_id, usuario_id, prompt, proposta_json, confirmado, created_at
             FROM educasync_comandos_ia
             WHERE id = :id AND cenario_id = :cenario_id',
            ['id' => (int) $id, 'cenario_id' => (int) $cenarioId]
        );
        return $linha ?: null;
    }

    /**
     * Transição atômica confirmado 0 → 1. False se já estava confirmado (idempotência).
     */
    public function marcarConfirmado(int $id, int $cenarioId): bool
    {
        return (int) $this->db->update(
            'UPDATE educasync_comandos_ia SET confirmado = 1
             WHERE id = :id AND cenario_id = :cenario_id AND confirmado = 0 AND proposta_json IS NOT NULL',
            ['id' => (int) $id, 'cenario_id' => (int) $cenarioId]
        ) > 0;
    }
}
