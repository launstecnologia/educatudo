<?php

require_once __DIR__ . '/AdminBaseController.php';

/**
 * FinanceReportController
 * Fluxo de Caixa, Contas a Pagar, DRE, Balanço, DFC, DMPL, DLPA
 */
class FinanceReportController extends AdminBaseController
{


    private function hasTable(string $t): bool
    {
        return (bool)$this->db->fetch(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$t]
        );
    }

    private function baseVars(string $title, string $page): array
    {
        return [
            'title'        => $title . ' — Financeiro',
            'page_title'   => $title,
            'user'         => $this->auth->getUser(),
            'current_page' => $page,
            'csrf_token'   => $this->generateCsrfToken(),
        ];
    }

    // ── Fluxo de Caixa ───────────────────────────────────────────────────────

    public function cashFlow(): void
    {
        $ano  = (int)($_GET['ano']  ?? date('Y'));
        $mes  = (int)($_GET['mes']  ?? date('n'));
        $modo = $_GET['modo'] ?? 'mensal'; // mensal | anual

        $dataInicio = $modo === 'anual' ? "$ano-01-01" : sprintf('%04d-%02d-01', $ano, $mes);
        $dataFim    = $modo === 'anual' ? "$ano-12-31" : date('Y-m-t', strtotime($dataInicio));

        // Entradas do ledger (créditos = pagamentos recebidos)
        $entradas = [];
        if ($this->hasTable('finance_ledger')) {
            $rows = $this->db->fetchAll(
                "SELECT DATE(data_lancamento) AS dia,
                        SUM(valor) AS total
                 FROM finance_ledger
                 WHERE tipo = 'credito'
                   AND referencia_tipo <> 'estorno'
                   AND data_lancamento BETWEEN ? AND ?
                 GROUP BY DATE(data_lancamento)
                 ORDER BY dia ASC",
                [$dataInicio, $dataFim]
            ) ?: [];
            foreach ($rows as $r) $entradas[$r['dia']] = (float)$r['total'];
        }

        // Saídas (contas a pagar pagas)
        $saidas = [];
        if ($this->hasTable('finance_bills')) {
            $rows = $this->db->fetchAll(
                "SELECT data_pagamento AS dia, SUM(valor_pago) AS total
                 FROM finance_bills
                 WHERE status = 'pago'
                   AND data_pagamento BETWEEN ? AND ?
                 GROUP BY data_pagamento
                 ORDER BY dia ASC",
                [$dataInicio, $dataFim]
            ) ?: [];
            foreach ($rows as $r) $saidas[$r['dia']] = (float)$r['total'];
        }

        // Montar linha do tempo
        $dias  = [];
        $saldoAcumulado = 0;
        $dt = new \DateTime($dataInicio);
        $fim = new \DateTime($dataFim);
        while ($dt <= $fim) {
            $d = $dt->format('Y-m-d');
            $entrada = $entradas[$d] ?? 0;
            $saida   = $saidas[$d]   ?? 0;
            if ($entrada > 0 || $saida > 0) {
                $saldoAcumulado += $entrada - $saida;
                $dias[] = [
                    'data'     => $d,
                    'entrada'  => $entrada,
                    'saida'    => $saida,
                    'liquido'  => $entrada - $saida,
                    'saldo'    => $saldoAcumulado,
                ];
            }
            $dt->modify('+1 day');
        }

        // Agrupar por mês se modo anual
        $porMes = [];
        if ($modo === 'anual') {
            foreach ($dias as $d) {
                $m = substr($d['data'], 0, 7);
                if (!isset($porMes[$m])) $porMes[$m] = ['entrada' => 0, 'saida' => 0, 'liquido' => 0];
                $porMes[$m]['entrada'] += $d['entrada'];
                $porMes[$m]['saida']   += $d['saida'];
                $porMes[$m]['liquido'] += $d['liquido'];
            }
        }

        $totEntrada = array_sum(array_column($dias, 'entrada'));
        $totSaida   = array_sum(array_column($dias, 'saida'));

        $this->viewWithLayout('admin', 'admin/finance/reports/cash_flow', array_merge($this->baseVars('Fluxo de Caixa', 'finance_cashflow'), [
            'dias'        => $dias,
            'porMes'      => $porMes,
            'totEntrada'  => $totEntrada,
            'totSaida'    => $totSaida,
            'totLiquido'  => $totEntrada - $totSaida,
            'ano'         => $ano,
            'mes'         => $mes,
            'modo'        => $modo,
            'dataInicio'  => $dataInicio,
            'dataFim'     => $dataFim,
        ]));
    }

    // ── Contas a Pagar ───────────────────────────────────────────────────────

    public function billsIndex(): void
    {
        if (!$this->hasTable('finance_bills')) {
            $this->setFlashMessage('Execute a migration 2026_07_03_finance_bills_plano_contas.sql primeiro.', 'error');
            $this->redirect('/admin/finance'); return;
        }

        $status = $_GET['status'] ?? '';
        $mes    = $_GET['mes']    ?? '';

        $where  = ['b.status <> ?'];
        $params = ['cancelado'];
        if ($status) { $where[] = 'b.status = ?';              $params[] = $status; }
        if ($mes)    { $where[] = 'DATE_FORMAT(b.data_vencimento,"%Y-%m") = ?'; $params[] = $mes; }

        $bills = $this->db->fetchAll(
            "SELECT b.*, ca.nome AS conta_nome, ca.grupo AS conta_grupo
             FROM finance_bills b
             LEFT JOIN finance_chart_accounts ca ON ca.id = b.account_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY b.data_vencimento ASC",
            $params
        ) ?: [];

        $accounts = $this->db->fetchAll(
            "SELECT * FROM finance_chart_accounts WHERE tipo = 'despesa' AND ativo = 1 ORDER BY ordem", []
        ) ?: [];

        $this->viewWithLayout('admin', 'admin/finance/reports/bills_index', array_merge($this->baseVars('Contas a Pagar', 'finance_bills'), [
            'bills'    => $bills,
            'accounts' => $accounts,
        ]));
    }

    public function billStore(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect('/admin/finance/bills'); return; }

        $user = $this->auth->getUser();
        $valor = (float)str_replace(',', '.', $_POST['valor'] ?? '0');

        $this->db->insert(
            "INSERT INTO finance_bills
             (account_id, descricao, fornecedor, valor, data_vencimento, data_competencia, recorrente, recorrencia_dia, observacoes, criado_por, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,'pendente')",
            [
                (int)($_POST['account_id'] ?? 0) ?: null,
                trim($_POST['descricao'] ?? ''),
                trim($_POST['fornecedor'] ?? '') ?: null,
                $valor,
                $_POST['data_vencimento'] ?? date('Y-m-d'),
                $_POST['data_competencia'] ?? null ?: null,
                !empty($_POST['recorrente']) ? 1 : 0,
                (int)($_POST['recorrencia_dia'] ?? 0) ?: null,
                trim($_POST['observacoes'] ?? '') ?: null,
                $user['id'] ?? null,
            ]
        );

        $this->setFlashMessage('Conta a pagar registrada.', 'success');
        $this->redirect('/admin/finance/bills');
    }

    public function billPay(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect('/admin/finance/bills'); return; }

        $valorPago = (float)str_replace(',', '.', $_POST['valor_pago'] ?? '0');
        $dataPag   = $_POST['data_pagamento'] ?? date('Y-m-d');

        $this->db->update(
            "UPDATE finance_bills SET status = 'pago', valor_pago = ?, data_pagamento = ? WHERE id = ?",
            [$valorPago, $dataPag, $id]
        );

        $this->setFlashMessage('Pagamento registrado.', 'success');
        $this->redirect('/admin/finance/bills');
    }

    public function billDelete(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect('/admin/finance/bills'); return; }
        $this->db->update("UPDATE finance_bills SET status = 'cancelado' WHERE id = ?", [$id]);
        $this->setFlashMessage('Conta cancelada.', 'success');
        $this->redirect('/admin/finance/bills');
    }

    // ── Relatórios Contábeis ─────────────────────────────────────────────────

    private function getPeriod(): array
    {
        $ano = (int)($_GET['ano'] ?? date('Y'));
        $mes = isset($_GET['mes']) && $_GET['mes'] !== '' ? (int)$_GET['mes'] : null;
        if ($mes) {
            $ini = sprintf('%04d-%02d-01', $ano, $mes);
            $fim = date('Y-m-t', strtotime($ini));
        } else {
            $ini = "$ano-01-01";
            $fim = "$ano-12-31";
        }
        return [$ano, $mes, $ini, $fim];
    }

    private function getReceitas(string $ini, string $fim): array
    {
        if (!$this->hasTable('finance_ledger')) return [];
        return $this->db->fetchAll(
            "SELECT categoria, SUM(valor) AS total
             FROM finance_ledger
             WHERE tipo = 'credito' AND referencia_tipo <> 'estorno'
               AND data_lancamento BETWEEN ? AND ?
             GROUP BY categoria ORDER BY total DESC",
            [$ini, $fim]
        ) ?: [];
    }

    private function getDespesas(string $ini, string $fim): array
    {
        if (!$this->hasTable('finance_bills')) return [];
        return $this->db->fetchAll(
            "SELECT ca.nome AS categoria, ca.grupo, SUM(b.valor_pago) AS total
             FROM finance_bills b
             LEFT JOIN finance_chart_accounts ca ON ca.id = b.account_id
             WHERE b.status = 'pago' AND b.data_pagamento BETWEEN ? AND ?
             GROUP BY b.account_id ORDER BY total DESC",
            [$ini, $fim]
        ) ?: [];
    }

    // DRE — Demonstração do Resultado do Exercício
    public function dre(): void
    {
        [$ano, $mes, $ini, $fim] = $this->getPeriod();

        $receitas  = $this->getReceitas($ini, $fim);
        $despesas  = $this->getDespesas($ini, $fim);

        $totReceita = array_sum(array_column($receitas, 'total'));
        $totDespesa = array_sum(array_column($despesas, 'total'));

        // A receber (receitas futuras previstas)
        $aReceber = 0;
        if ($this->hasTable('finance_installments')) {
            $r = $this->db->fetch(
                "SELECT SUM(valor_cobrado) AS total FROM finance_installments
                 WHERE status IN ('pendente','vencido') AND data_vencimento BETWEEN ? AND ?", [$ini, $fim]
            );
            $aReceber = (float)($r['total'] ?? 0);
        }

        $this->viewWithLayout('admin', 'admin/finance/reports/dre', array_merge($this->baseVars('DRE', 'finance_dre'), [
            'receitas'    => $receitas,
            'despesas'    => $despesas,
            'totReceita'  => $totReceita,
            'totDespesa'  => $totDespesa,
            'lucro'       => $totReceita - $totDespesa,
            'aReceber'    => $aReceber,
            'ano' => $ano, 'mes' => $mes, 'ini' => $ini, 'fim' => $fim,
        ]));
    }

    // DFC — Demonstração do Fluxo de Caixa
    public function dfc(): void
    {
        [$ano, $mes, $ini, $fim] = $this->getPeriod();

        // Atividades operacionais: recebimentos de clientes
        $recebimentos = 0;
        if ($this->hasTable('finance_ledger')) {
            $r = $this->db->fetch(
                "SELECT SUM(valor) AS total FROM finance_ledger
                 WHERE tipo = 'credito' AND referencia_tipo <> 'estorno' AND data_lancamento BETWEEN ? AND ?",
                [$ini, $fim]
            );
            $recebimentos = (float)($r['total'] ?? 0);
        }

        // Pagamentos a fornecedores
        $pagamentos = 0;
        $despPorGrupo = [];
        if ($this->hasTable('finance_bills')) {
            $r = $this->db->fetch(
                "SELECT SUM(valor_pago) AS total FROM finance_bills WHERE status='pago' AND data_pagamento BETWEEN ? AND ?",
                [$ini, $fim]
            );
            $pagamentos = (float)($r['total'] ?? 0);

            $despPorGrupo = $this->db->fetchAll(
                "SELECT COALESCE(ca.grupo,'Sem Categoria') AS grupo, SUM(b.valor_pago) AS total
                 FROM finance_bills b LEFT JOIN finance_chart_accounts ca ON ca.id = b.account_id
                 WHERE b.status='pago' AND b.data_pagamento BETWEEN ? AND ?
                 GROUP BY ca.grupo ORDER BY total DESC",
                [$ini, $fim]
            ) ?: [];
        }

        $fcOperacional = $recebimentos - $pagamentos;

        $this->viewWithLayout('admin', 'admin/finance/reports/dfc', array_merge($this->baseVars('DFC', 'finance_dfc'), [
            'recebimentos'  => $recebimentos,
            'pagamentos'    => $pagamentos,
            'despPorGrupo'  => $despPorGrupo,
            'fcOperacional' => $fcOperacional,
            'ano' => $ano, 'mes' => $mes, 'ini' => $ini, 'fim' => $fim,
        ]));
    }

    // Balanço Patrimonial (simplificado)
    public function balanco(): void
    {
        [$ano, $mes, $ini, $fim] = $this->getPeriod();

        // Ativo Circulante
        $caixa = 0;
        if ($this->hasTable('finance_ledger')) {
            $r = $this->db->fetch(
                "SELECT SUM(CASE WHEN tipo='credito' THEN valor ELSE -valor END) AS saldo
                 FROM finance_ledger WHERE referencia_tipo <> 'estorno' AND data_lancamento <= ?", [$fim]
            );
            $caixa = max(0, (float)($r['saldo'] ?? 0));
        }

        $aReceber = 0;
        if ($this->hasTable('finance_installments')) {
            $r = $this->db->fetch(
                "SELECT SUM(valor_cobrado) AS total FROM finance_installments WHERE status IN ('pendente','vencido') AND data_vencimento <= ?", [$fim]
            );
            $aReceber = (float)($r['total'] ?? 0);
        }

        $totalAtivo = $caixa + $aReceber;

        // Passivo Circulante
        $aPagar = 0;
        if ($this->hasTable('finance_bills')) {
            $r = $this->db->fetch(
                "SELECT SUM(valor) AS total FROM finance_bills WHERE status IN ('pendente','vencido') AND data_vencimento <= ?", [$fim]
            );
            $aPagar = (float)($r['total'] ?? 0);
        }

        $patrimonioLiquido = $totalAtivo - $aPagar;

        $this->viewWithLayout('admin', 'admin/finance/reports/balanco', array_merge($this->baseVars('Balanço Patrimonial', 'finance_balanco'), [
            'caixa'             => $caixa,
            'aReceber'          => $aReceber,
            'totalAtivo'        => $totalAtivo,
            'aPagar'            => $aPagar,
            'patrimonioLiquido' => $patrimonioLiquido,
            'ano' => $ano, 'mes' => $mes, 'ini' => $ini, 'fim' => $fim,
        ]));
    }

    // DMPL — Demonstração das Mutações do Patrimônio Líquido
    public function dmpl(): void
    {
        $ano = (int)($_GET['ano'] ?? date('Y'));

        // Calcular PL mês a mês
        $meses = [];
        for ($m = 1; $m <= 12; $m++) {
            $ini = sprintf('%04d-%02d-01', $ano, $m);
            $fim = date('Y-m-t', strtotime($ini));
            if ($ini > date('Y-m-d')) { $meses[] = ['mes' => $m, 'receita' => 0, 'despesa' => 0, 'resultado' => null]; continue; }

            $rec = 0;
            if ($this->hasTable('finance_ledger')) {
                $r = $this->db->fetch(
                    "SELECT SUM(valor) AS t FROM finance_ledger WHERE tipo='credito' AND referencia_tipo<>'estorno' AND data_lancamento BETWEEN ? AND ?",
                    [$ini, $fim]
                );
                $rec = (float)($r['t'] ?? 0);
            }
            $desp = 0;
            if ($this->hasTable('finance_bills')) {
                $r = $this->db->fetch(
                    "SELECT SUM(valor_pago) AS t FROM finance_bills WHERE status='pago' AND data_pagamento BETWEEN ? AND ?",
                    [$ini, $fim]
                );
                $desp = (float)($r['t'] ?? 0);
            }
            $meses[] = ['mes' => $m, 'receita' => $rec, 'despesa' => $desp, 'resultado' => $rec - $desp];
        }

        $this->viewWithLayout('admin', 'admin/finance/reports/dmpl', array_merge($this->baseVars('DMPL', 'finance_dmpl'), [
            'meses' => $meses,
            'ano'   => $ano,
        ]));
    }

    // DLPA — Demonstração dos Lucros ou Prejuízos Acumulados
    public function dlpa(): void
    {
        $ano = (int)($_GET['ano'] ?? date('Y'));

        // Lucro/Prejuízo do exercício
        $ini = "$ano-01-01"; $fim = "$ano-12-31";
        $receita = 0; $despesa = 0;

        if ($this->hasTable('finance_ledger')) {
            $r = $this->db->fetch("SELECT SUM(valor) AS t FROM finance_ledger WHERE tipo='credito' AND referencia_tipo<>'estorno' AND data_lancamento BETWEEN ? AND ?", [$ini, $fim]);
            $receita = (float)($r['t'] ?? 0);
        }
        if ($this->hasTable('finance_bills')) {
            $r = $this->db->fetch("SELECT SUM(valor_pago) AS t FROM finance_bills WHERE status='pago' AND data_pagamento BETWEEN ? AND ?", [$ini, $fim]);
            $despesa = (float)($r['t'] ?? 0);
        }

        $resultadoExercicio = $receita - $despesa;

        // Acumulado anos anteriores
        $acumuladoAnterior = 0;
        if ($this->hasTable('finance_ledger') && $this->hasTable('finance_bills')) {
            $rRec  = $this->db->fetch("SELECT SUM(valor) AS t FROM finance_ledger WHERE tipo='credito' AND referencia_tipo<>'estorno' AND data_lancamento < ?", ["$ano-01-01"]);
            $rDesp = $this->db->fetch("SELECT SUM(valor_pago) AS t FROM finance_bills WHERE status='pago' AND data_pagamento < ?", ["$ano-01-01"]);
            $acumuladoAnterior = (float)($rRec['t'] ?? 0) - (float)($rDesp['t'] ?? 0);
        }

        $this->viewWithLayout('admin', 'admin/finance/reports/dlpa', array_merge($this->baseVars('DLPA', 'finance_dlpa'), [
            'receita'            => $receita,
            'despesa'            => $despesa,
            'resultadoExercicio' => $resultadoExercicio,
            'acumuladoAnterior'  => $acumuladoAnterior,
            'acumuladoTotal'     => $acumuladoAnterior + $resultadoExercicio,
            'ano'                => $ano,
        ]));
    }
}
