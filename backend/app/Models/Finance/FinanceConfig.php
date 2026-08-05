<?php

namespace App\Models\Finance;

use Database;

class FinanceConfig
{
    private $db;
    private static ?array $cache = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function get(): array
    {
        if (self::$cache !== null) return self::$cache;
        $row = $this->db->fetch("SELECT * FROM finance_config WHERE id = 1");
        self::$cache = $row ?: [
            'juros_mensal'                => 1.00,
            'multa_atraso'                => 2.00,
            'dias_carencia'               => 0,
            'dia_vencimento_padrao'       => 10,
            'gerar_debito_auto'           => 1,
            'email_remetente'             => null,
            'nome_escola_boleto'          => null,
            'desconto_pontualidade_pct'   => 0.00,
            'desconto_pontualidade_dia'   => 5,
        ];
        return self::$cache;
    }

    public function save(array $data): void
    {
        self::$cache = null;
        $this->db->insert(
            "INSERT INTO finance_config
               (id, juros_mensal, multa_atraso, dias_carencia, dia_vencimento_padrao,
                gerar_debito_auto, email_remetente, nome_escola_boleto,
                desconto_pontualidade_pct, desconto_pontualidade_dia)
             VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               juros_mensal = VALUES(juros_mensal),
               multa_atraso = VALUES(multa_atraso),
               dias_carencia = VALUES(dias_carencia),
               dia_vencimento_padrao = VALUES(dia_vencimento_padrao),
               gerar_debito_auto = VALUES(gerar_debito_auto),
               email_remetente = VALUES(email_remetente),
               nome_escola_boleto = VALUES(nome_escola_boleto),
               desconto_pontualidade_pct = VALUES(desconto_pontualidade_pct),
               desconto_pontualidade_dia = VALUES(desconto_pontualidade_dia)",
            [
                (float)($data['juros_mensal'] ?? 1.00),
                (float)($data['multa_atraso'] ?? 2.00),
                (int)($data['dias_carencia'] ?? 0),
                (int)($data['dia_vencimento_padrao'] ?? 10),
                !empty($data['gerar_debito_auto']) ? 1 : 0,
                trim($data['email_remetente'] ?? '') ?: null,
                trim($data['nome_escola_boleto'] ?? '') ?: null,
                (float)($data['desconto_pontualidade_pct'] ?? 0.00),
                (int)($data['desconto_pontualidade_dia'] ?? 5),
            ]
        );
    }

    public function schemaReady(): bool
    {
        try {
            $r = $this->db->fetch(
                "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                ['finance_config']
            );
            return !empty($r);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
