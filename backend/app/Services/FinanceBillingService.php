<?php

namespace App\Services;

use Database;
use App\Models\Finance\FinanceInstallment;

class FinanceBillingService
{
    private $db;
    private FinanceInstallment $installmentModel;

    public function __construct(?Database $db = null)
    {
        $this->db               = $db ?? Database::getInstance();
        $this->installmentModel = new FinanceInstallment($this->db);
    }

    // Marca parcelas vencidas (roda via cron ou on-demand)
    public function marcarVencidas(): int
    {
        $affected = $this->db->update(
            "UPDATE finance_installments SET status = 'vencido', updated_at = NOW()
             WHERE status = 'pendente' AND data_vencimento < CURDATE()",
            []
        );
        return (int)$affected;
    }

    // Dispara régua de cobrança para parcelas elegíveis
    public function dispararRegua(): array
    {
        $regras = $this->db->fetchAll(
            "SELECT * FROM billing_rule_config WHERE ativo = 1 ORDER BY dias_relativo ASC",
            []
        ) ?: [];

        $enviados = [];

        foreach ($regras as $regra) {
            $dias       = (int)$regra['dias_relativo'];
            $targetDate = date('Y-m-d', strtotime("{$dias} days"));

            $parcelas = $this->db->fetchAll(
                "SELECT fi.*, fc.aluno_id, fc.responsavel_id, fc.responsavel_email,
                        fc.responsavel_telefone, fc.responsavel_nome,
                        a.nome AS aluno_nome
                 FROM finance_installments fi
                 JOIN finance_contracts fc ON fc.id = fi.contract_id AND fc.status = 'ativo'
                 LEFT JOIN alunos a ON a.id = fc.aluno_id
                 WHERE fi.data_vencimento = ?
                   AND fi.status IN ('pendente','vencido')
                   AND NOT EXISTS (
                       SELECT 1 FROM billing_message_log bml
                       WHERE bml.installment_id = fi.id
                         AND bml.canal = ?
                         AND bml.template_usado = ?
                         AND DATE(bml.created_at) = CURDATE()
                   )",
                [$targetDate, $regra['canal'], $regra['nome']]
            ) ?: [];

            foreach ($parcelas as $p) {
                $vars = [
                    '{aluno_nome}'       => $p['aluno_nome'] ?? '',
                    '{responsavel_nome}' => $p['responsavel_nome'] ?? '',
                    '{descricao}'        => $p['descricao'] ?? '',
                    '{valor}'            => number_format((float)$p['valor_cobrado'], 2, ',', '.'),
                    '{data_vencimento}'  => date('d/m/Y', strtotime($p['data_vencimento'])),
                    '{escola_nome}'      => defined('TENANT_SLUG') ? TENANT_SLUG : 'Escola',
                ];

                $corpo = strtr($regra['template_corpo'], $vars);
                $dest  = $this->destinatario($regra['canal'], $p);

                $status = $this->enviar($regra['canal'], $dest, $regra['template_titulo'], $corpo, $p);
                $log    = [
                    'installment_id' => $p['id'],
                    'aluno_id'       => $p['aluno_id'],
                    'responsavel_id' => $p['responsavel_id'],
                    'canal'          => $regra['canal'],
                    'template_usado' => $regra['nome'],
                    'destinatario'   => $dest,
                    'status'         => $status,
                ];
                $this->db->insert(
                    "INSERT INTO billing_message_log (installment_id, aluno_id, responsavel_id, canal, template_usado, destinatario, status) VALUES (?,?,?,?,?,?,?)",
                    array_values($log)
                );
                $enviados[] = $log;
            }
        }

        return $enviados;
    }

    private function destinatario(string $canal, array $parcela): string
    {
        return match ($canal) {
            'email'    => $parcela['responsavel_email'] ?? '',
            'whatsapp' => $this->normalizarPhone($parcela['responsavel_telefone'] ?? ''),
            default    => (string)($parcela['aluno_id'] ?? ''),
        };
    }

    private function normalizarPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 11) return '55' . $digits;
        if (strlen($digits) === 10) return '55' . $digits;
        return $digits;
    }

    private function enviar(string $canal, string $dest, string $titulo, string $corpo, array $parcela): string
    {
        if ($canal === 'app') {
            return $this->enviarApp((int)$parcela['aluno_id'], $titulo, $corpo);
        }
        if ($canal === 'email') {
            return $this->enviarEmail($dest, $titulo, $corpo);
        }
        if ($canal === 'whatsapp') {
            return $this->simularWhatsapp($dest, $corpo);
        }
        return 'simulado';
    }

    private function enviarApp(int $alunoId, string $titulo, string $corpo): string
    {
        try {
            $this->db->insert(
                "INSERT INTO notificacoes (usuario_id, tipo_usuario, titulo, mensagem, tipo, lida) VALUES (?,?,?,?,?,?)",
                [$alunoId, 'aluno', $titulo, $corpo, 'financeiro', 0]
            );
            return 'enviado';
        } catch (\Throwable $e) {
            return 'falha';
        }
    }

    private function enviarEmail(string $dest, string $titulo, string $corpo): string
    {
        if (empty($dest) || !filter_var($dest, FILTER_VALIDATE_EMAIL)) return 'falha';
        try {
            $headers = "From: no-reply@educatudo.com\r\nContent-Type: text/plain; charset=UTF-8";
            @mail($dest, $titulo, $corpo, $headers);
            return 'enviado';
        } catch (\Throwable $e) {
            return 'falha';
        }
    }

    private function simularWhatsapp(string $phone, string $corpo): string
    {
        // Simulado: sem gateway real configurado
        // Em produção: chamar Evolution API aqui
        return 'simulado';
    }
}
