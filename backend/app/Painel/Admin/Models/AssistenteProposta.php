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
        $this->ensureSchema();
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

    /** Idempotente — espelha 2026_11_12_assistente_propostas.sql. */
    public function ensureSchema(): void
    {
        static $feito = false;
        if ($feito) {
            return;
        }
        $feito = true;
        try {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS `assistente_propostas` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `conversa_id` INT UNSIGNED NULL,
                    `mensagem_id` BIGINT UNSIGNED NULL,
                    `usuario_id` INT NOT NULL COMMENT 'usuarios.id do admin autor',
                    `tipo` VARCHAR(40) NOT NULL COMMENT 'criar_ocorrencia|entrada_saida|chamada_aula',
                    `payload_json` JSON NOT NULL,
                    `confirmado` TINYINT(1) NOT NULL DEFAULT 0,
                    `confirmado_em` DATETIME NULL,
                    `resultado_json` JSON NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_assistente_prop_usuario` (`usuario_id`, `confirmado`, `created_at`),
                    KEY `idx_assistente_prop_conversa` (`conversa_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable $e) {
            error_log('AssistenteProposta::ensureSchema: ' . $e->getMessage());
        }
    }

    /**
     * Última proposta em rascunho do usuário (opcionalmente da conversa).
     *
     * @return array<string,mixed>|null
     */
    public function encontrarPendente(int $usuarioId, int $conversaId = 0): ?array
    {
        if ($usuarioId <= 0 || !$this->tabelasProntas()) {
            return null;
        }
        $params = ['usuario_id' => $usuarioId];
        $sql = 'SELECT * FROM assistente_propostas
                WHERE usuario_id = :usuario_id AND confirmado = 0';
        if ($conversaId > 0) {
            $sql .= ' AND conversa_id = :conversa_id';
            $params['conversa_id'] = $conversaId;
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $row = $this->db->fetch($sql, $params);
        if ($row) {
            return $row;
        }
        // Sem conversa amarrada: pega a mais recente do usuário (últimos 30 min).
        if ($conversaId > 0) {
            $row = $this->db->fetch(
                'SELECT * FROM assistente_propostas
                 WHERE usuario_id = :usuario_id AND confirmado = 0
                   AND created_at >= (NOW() - INTERVAL 30 MINUTE)
                 ORDER BY id DESC LIMIT 1',
                ['usuario_id' => $usuarioId]
            );
            return $row ?: null;
        }
        return null;
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
