<?php

namespace App\Services;

use Database;
use App\Models\Finance\FinanceContract;
use App\Models\Finance\FinanceInstallment;

class FinanceContractService
{
    private $db;
    private FinanceContract $contractModel;
    private FinanceInstallment $installmentModel;

    public function __construct(?Database $db = null)
    {
        $this->db               = $db ?? Database::getInstance();
        $this->contractModel    = new FinanceContract($this->db);
        $this->installmentModel = new FinanceInstallment($this->db);
    }

    // ── Prefill a partir do aluno ────────────────────────────────────────────

    public function prefillFromAluno(int $alunoId): array
    {
        $aluno = $this->db->fetch("SELECT * FROM alunos WHERE id = ?", [$alunoId]);
        if (!$aluno) return [];

        $resp = $this->db->fetch(
            "SELECT r.* FROM responsaveis r
             JOIN alunos_responsaveis ar ON ar.responsavel_id = r.id
             WHERE ar.aluno_id = ? AND ar.is_financeiro = 1
             ORDER BY ar.id LIMIT 1",
            [$alunoId]
        );

        $matricula = $this->db->fetch(
            "SELECT m.*, t.nome AS turma_nome, t.serie
             FROM matricula m
             LEFT JOIN turmas t ON t.id = m.turma_id
             WHERE m.aluno_id = ?
             ORDER BY m.id DESC LIMIT 1",
            [$alunoId]
        );

        $anoLetivo = null;
        if ($matricula) {
            $anoLetivo = $this->db->fetch(
                "SELECT * FROM ano_letivo WHERE id = ?",
                [$matricula['ano_letivo_id'] ?? 0]
            );
        }

        return [
            'aluno'      => $aluno,
            'responsavel'=> $resp,
            'matricula'  => $matricula,
            'ano_letivo' => $anoLetivo,
        ];
    }

    // ── Detecção de irmãos ───────────────────────────────────────────────────

    public function detectarIrmaos(int $alunoId, int $anoLetivoId): array
    {
        // Busca responsável financeiro do aluno atual
        $resp = $this->db->fetch(
            "SELECT ar.responsavel_id FROM alunos_responsaveis ar WHERE ar.aluno_id = ? AND ar.is_financeiro = 1 LIMIT 1",
            [$alunoId]
        );
        if (!$resp) return [];

        $respId = (int)$resp['responsavel_id'];

        // Busca outros alunos com mesmo responsável financeiro e contrato ativo no mesmo ano
        return $this->db->fetchAll(
            "SELECT a.id, a.nome, a.ra, fc.id AS contract_id, fc.status AS contract_status
             FROM alunos_responsaveis ar
             JOIN alunos a ON a.id = ar.aluno_id
             LEFT JOIN finance_contracts fc ON fc.aluno_id = a.id AND fc.ano_letivo_id = ? AND fc.status = 'ativo'
             WHERE ar.responsavel_id = ? AND ar.aluno_id != ? AND a.ativo = 1",
            [$anoLetivoId, $respId, $alunoId]
        ) ?: [];
    }

    // ── Cálculo de desconto de irmãos ────────────────────────────────────────

    public function calcularDescontoIrmaos(int $posicao, float $valorBase): array
    {
        // 2º irmão = 10%, 3º+ = 15%
        if ($posicao < 2) return ['percentual' => 0, 'valor' => 0.0];
        $pct = $posicao === 2 ? 10 : 15;
        return [
            'percentual'  => $pct,
            'valor'       => round($valorBase * ($pct / 100), 2),
            'descricao'   => "Desconto irmãos ({$pct}%) — {$posicao}º irmão na escola",
        ];
    }

    // ── Calcular totais com descontos ────────────────────────────────────────

    public function calcularTotais(float $valorBruto, array $descontos): array
    {
        $totalDesconto = 0.0;
        foreach ($descontos as $d) {
            if (($d['status'] ?? 'aprovado') !== 'aprovado') continue;
            if ($d['calculo'] === 'percentual') {
                $totalDesconto += round($valorBruto * ($d['valor'] / 100), 2);
            } else {
                $totalDesconto += (float)$d['valor'];
            }
        }
        $valorLiquido = max(0, $valorBruto - $totalDesconto);
        return [
            'valor_bruto'    => round($valorBruto, 2),
            'valor_desconto' => round($totalDesconto, 2),
            'valor_liquido'  => round($valorLiquido, 2),
        ];
    }

    // ── Recalcular totais do contrato após mudança de desconto ───────────────

    public function recalcularContrato(int $contractId): void
    {
        $contract  = $this->contractModel->findById($contractId);
        $discounts = $this->contractModel->getDiscounts($contractId);
        if (!$contract) return;

        $totais = $this->calcularTotais((float)$contract['valor_bruto'], $discounts);
        $this->contractModel->update($contractId, [
            'valor_desconto' => $totais['valor_desconto'],
            'valor_liquido'  => $totais['valor_liquido'],
        ]);

        // Regenerar parcelas
        $this->gerarParcelas($contractId);
    }

    // ── Gerar parcelas do contrato ───────────────────────────────────────────

    public function gerarParcelas(int $contractId): void
    {
        $contract = $this->contractModel->findById($contractId);
        if (!$contract) return;

        // Remove parcelas não pagas
        $this->db->update(
            "DELETE FROM finance_installments WHERE contract_id = ? AND status NOT IN ('pago','renegociado')",
            [$contractId]
        );

        $mesInicio     = (int)$contract['mes_inicio'];
        $mesFim        = (int)$contract['mes_fim'];
        $diaVencimento = (int)$contract['dia_vencimento'];
        $valorLiquido  = (float)$contract['valor_liquido'];
        $anoLetivoAno  = (int)($contract['ano_letivo_nome'] ?? date('Y'));

        $meses = $mesFim >= $mesInicio
            ? ($mesFim - $mesInicio + 1)
            : (12 - $mesInicio + $mesFim + 1);

        if ($meses <= 0) $meses = 1;

        $valorParcela = round($valorLiquido / $meses, 2);
        $diff         = round($valorLiquido - ($valorParcela * $meses), 2);

        $num   = 0;
        $mes   = $mesInicio;
        $ano   = $anoLetivoAno;

        for ($i = 0; $i < $meses; $i++) {
            $num++;
            $ultimoDia     = (int)date('t', mktime(0, 0, 0, $mes, 1, $ano));
            $dia           = min($diaVencimento, $ultimoDia);
            $vencimento    = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
            $valorEsta     = $valorParcela + ($i === $meses - 1 ? $diff : 0);

            $this->installmentModel->insert([
                'contract_id'    => $contractId,
                'num_parcela'    => $num,
                'categoria'      => 'mensalidade',
                'descricao'      => sprintf('Mensalidade %02d/%04d', $mes, $ano),
                'valor_nominal'  => $valorLiquido / $meses,
                'valor_desconto' => 0,
                'valor_cobrado'  => $valorEsta,
                'data_vencimento'=> $vencimento,
            ]);

            $mes++;
            if ($mes > 12) { $mes = 1; $ano++; }
        }
    }

    // ── Calcular encargos de atraso ──────────────────────────────────────────

    public function calcularEncargos(array $installment, float $pctMulta = 2.0, float $pctJurosMes = 1.0): array
    {
        if ($installment['status'] === 'pago') return ['juros' => 0, 'multa' => 0, 'total' => (float)$installment['valor_cobrado']];

        $venc = new \DateTime($installment['data_vencimento']);
        $hoje = new \DateTime('today');
        if ($hoje <= $venc) return ['juros' => 0, 'multa' => 0, 'total' => (float)$installment['valor_cobrado']];

        $dias         = (int)$hoje->diff($venc)->days;
        $valorBase    = (float)$installment['valor_cobrado'];
        $multa        = round($valorBase * ($pctMulta / 100), 2);
        $jurosDiario  = $pctJurosMes / 100 / 30;
        $juros        = round($valorBase * $jurosDiario * $dias, 2);

        return [
            'juros'  => $juros,
            'multa'  => $multa,
            'dias'   => $dias,
            'total'  => round($valorBase + $multa + $juros, 2),
        ];
    }

    // ── Auditoria ────────────────────────────────────────────────────────────

    public function audit(string $entidade, int $entidadeId, string $acao, ?array $antes, ?array $depois, ?array $user): void
    {
        try {
            $this->db->insert(
                "INSERT INTO finance_audit (entidade, entidade_id, acao, dados_antes, dados_depois, usuario_id, usuario_nome, ip)
                 VALUES (?,?,?,?,?,?,?,?)",
                [
                    $entidade, $entidadeId, $acao,
                    $antes  ? json_encode($antes)  : null,
                    $depois ? json_encode($depois) : null,
                    $user['id']   ?? null,
                    $user['nome'] ?? null,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]
            );
        } catch (\Throwable $e) {
            // auditoria nunca deve quebrar a operação principal
        }
    }
}
