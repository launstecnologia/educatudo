<?php

namespace App\Painel\Admin\Models;

use App\Core\Database;

/**
 * Rascunhos de escrita do Assistente Admin (confirmação humana obrigatória).
 */
class AssistenteProposta
{
    public const TIPOS = [
        'criar_ocorrencia',
        'entrada_saida',
        'chamada_aula',
    ];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tabelasProntas(): bool
    {
        try {
            if (method_exists($this->db, 'tableExists')) {
                return (bool) $this->db->tableExists('assistente_propostas');
            }
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1',
                ['t' => 'assistente_propostas']
            );
            return !empty($row);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function criar(int $usuarioId, string $tipo, array $payload, int $conversaId = 0, int $mensagemId = 0): ?int
    {
        if ($usuarioId <= 0 || !in_array($tipo, self::TIPOS, true) || !$this->tabelasProntas()) {
            return null;
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return null;
        }
        $id = (int) $this->db->insert(
            'INSERT INTO assistente_propostas
                (conversa_id, mensagem_id, usuario_id, tipo, payload_json, confirmado, created_at)
             VALUES
                (:conversa_id, :mensagem_id, :usuario_id, :tipo, :payload_json, 0, NOW())',
            [
                'conversa_id' => $conversaId > 0 ? $conversaId : null,
                'mensagem_id' => $mensagemId > 0 ? $mensagemId : null,
                'usuario_id' => $usuarioId,
                'tipo' => $tipo,
                'payload_json' => $json,
            ]
        );
        return $id > 0 ? $id : null;
    }

    public function findDoUsuario(int $id, int $usuarioId): ?array
    {
        if ($id <= 0 || $usuarioId <= 0 || !$this->tabelasProntas()) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT * FROM assistente_propostas
             WHERE id = :id AND usuario_id = :usuario_id
             LIMIT 1',
            ['id' => $id, 'usuario_id' => $usuarioId]
        );
        return $row ?: null;
    }

    /**
     * Reivindica a proposta de forma atômica (confirmado 0 → 2 = em processamento).
     * Só um request consegue o claim.
     */
    public function reivindicar(int $id, int $usuarioId): bool
    {
        if ($id <= 0 || $usuarioId <= 0 || !$this->tabelasProntas()) {
            return false;
        }
        $afetados = $this->db->update(
            'UPDATE assistente_propostas
             SET confirmado = 2,
                 confirmado_em = NOW(),
                 resultado_json = :resultado_json
             WHERE id = :id
               AND usuario_id = :usuario_id
               AND confirmado = 0',
            [
                'resultado_json' => json_encode(['status' => 'processando'], JSON_UNESCAPED_UNICODE),
                'id' => $id,
                'usuario_id' => $usuarioId,
            ]
        );
        return $afetados > 0;
    }

    /**
     * @param array<string,mixed> $resultado
     */
    public function finalizarConfirmada(int $id, int $usuarioId, array $resultado): bool
    {
        if ($id <= 0 || $usuarioId <= 0 || !$this->tabelasProntas()) {
            return false;
        }
        $json = json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $afetados = $this->db->update(
            'UPDATE assistente_propostas
             SET confirmado = 1,
                 confirmado_em = NOW(),
                 resultado_json = :resultado_json
             WHERE id = :id
               AND usuario_id = :usuario_id
               AND confirmado = 2',
            [
                'resultado_json' => $json !== false ? $json : null,
                'id' => $id,
                'usuario_id' => $usuarioId,
            ]
        );
        return $afetados > 0;
    }

    /** Libera o claim se a aplicação falhar (volta a rascunho). */
    public function liberarClaim(int $id, int $usuarioId, ?string $erro = null): bool
    {
        if ($id <= 0 || $usuarioId <= 0 || !$this->tabelasProntas()) {
            return false;
        }
        $resultado = $erro !== null ? ['status' => 'erro', 'error' => $erro] : null;
        $json = $resultado !== null
            ? json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
        $afetados = $this->db->update(
            'UPDATE assistente_propostas
             SET confirmado = 0,
                 confirmado_em = NULL,
                 resultado_json = :resultado_json
             WHERE id = :id
               AND usuario_id = :usuario_id
               AND confirmado = 2',
            [
                'resultado_json' => $json,
                'id' => $id,
                'usuario_id' => $usuarioId,
            ]
        );
        return $afetados > 0;
    }
}
