<?php
/**
 * EducaTudo - Controller de Administracao (extraido de AdminController)
 */

require_once __DIR__ . '/../../Core/LayoutHelper.php';
require_once __DIR__ . '/AdminBaseController.php';

if (!class_exists('FinanceAdminController')) {
class FinanceAdminController extends AdminBaseController
{
    public function financeiroDashboard()
    {
        $user = $this->auth->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirect('/admin/dashboard');
            return;
        }

        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $financeConfig = $this->getFinanceiroConfig();

        $this->gerarCobrancaMensalSeNecessario($user['id'], $financeConfig);

        $pagamentos = $this->db->fetchAll(
            "SELECT *
             FROM financeiro_valores_mensais
             ORDER BY mes_referencia DESC"
        );

        if (empty($pagamentos)) {
            $totalAlunosPagantes = $this->db->fetch("SELECT COUNT(*) as count FROM alunos WHERE ativo = 1 AND pagante = 1")['count'] ?? 0;
            $totalProfessoresPagantes = $this->db->fetch("SELECT COUNT(*) as count FROM professores WHERE ativo = 1 AND pagante = 1")['count'] ?? 0;
            $totalPagantes = $totalAlunosPagantes + $totalProfessoresPagantes;
            $valorPorUsuario = (float) ($financeConfig['valor_por_usuario'] ?? 0);
            $valorTotal = $totalPagantes * $valorPorUsuario;
            $pagamentos = [[
                'total_usuarios_pagantes' => $totalPagantes,
                'valor_por_usuario' => number_format($valorPorUsuario, 2, '.', ''),
                'valor_total' => number_format($valorTotal, 2, '.', ''),
                'data_vencimento' => null,
                'status' => 'aberto'
            ]];
        }

        $ultimo = $pagamentos[0] ?? null;
        $kpis = [
            'total_usuarios' => (int)($ultimo['total_usuarios_pagantes'] ?? 0),
            'valor_unitario' => (float)($ultimo['valor_por_usuario'] ?? ($financeConfig['valor_por_usuario'] ?? 0)),
            'valor_total' => (float)($ultimo['valor_total'] ?? 0),
            'data_vencimento' => $ultimo['data_vencimento'] ?? null
        ];

        $data = [
            'title' => 'Financeiro - EducaTudo',
            'page_title' => 'Financeiro',
            'user' => $user,
            'current_page' => 'financeiro',
            'pagamentos' => $pagamentos,
            'finance_config' => $financeConfig,
            'kpis' => $kpis,
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('admin', 'admin/financeiro/dashboard', $data);
    }

    public function financeiroRelatorioPagantes()
    {
        $user = $this->auth->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirect('/admin/dashboard');
            return;
        }

        $alunosPagantes = $this->db->fetchAll(
            "SELECT a.id, a.nome, a.nickname, a.ra, t.nome as turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             WHERE a.ativo = 1 AND a.pagante = 1
             ORDER BY a.nome ASC"
        );
        $alunosNaoPagantes = $this->db->fetchAll(
            "SELECT a.id, a.nome, a.nickname, a.ra, t.nome as turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             WHERE a.ativo = 1 AND a.pagante = 0
             ORDER BY a.nome ASC"
        );
        $profPagantes = $this->db->fetchAll(
            "SELECT id, nome, email
             FROM professores
             WHERE ativo = 1 AND pagante = 1
             ORDER BY nome ASC"
        );
        $profNaoPagantes = $this->db->fetchAll(
            "SELECT id, nome, email
             FROM professores
             WHERE ativo = 1 AND pagante = 0
             ORDER BY nome ASC"
        );

        $data = [
            'title' => 'Relatório de Pagantes - EducaTudo',
            'page_title' => 'Relatório de Pagantes',
            'user' => $user,
            'current_page' => 'financeiro_relatorio',
            'alunos_pagantes' => $alunosPagantes,
            'alunos_nao_pagantes' => $alunosNaoPagantes,
            'prof_pagantes' => $profPagantes,
            'prof_nao_pagantes' => $profNaoPagantes
        ];

        $this->viewWithLayout('admin', 'admin/financeiro/relatorio-pagantes', $data);
    }

    public function financeiroPagar($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/financeiro');
        }

        $user = $this->auth->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola']) || ($user['perfil_admin'] ?? '') !== 'dev') {
            $this->redirect('/admin/dashboard');
            return;
        }

        $this->db->update(
            "UPDATE financeiro_valores_mensais
             SET status = 'pago', data_pagamento = CURDATE()
             WHERE id = :id",
            ['id' => (int) $id]
        );

        $this->setFlashMessage('Pagamento marcado como pago.', 'success');
        $this->redirect('/admin/financeiro');
    }

    public function financeiroReabrir($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/financeiro');
        }

        $user = $this->auth->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola']) || ($user['perfil_admin'] ?? '') !== 'dev') {
            $this->redirect('/admin/dashboard');
            return;
        }

        $this->db->update(
            "UPDATE financeiro_valores_mensais
             SET status = 'aberto', data_pagamento = NULL
             WHERE id = :id",
            ['id' => (int) $id]
        );

        $this->setFlashMessage('Pagamento reaberto.', 'success');
        $this->redirect('/admin/financeiro');
    }

    public function financeiroEditar($id)
    {
        $user = $this->auth->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola']) || ($user['perfil_admin'] ?? '') !== 'dev') {
            $this->redirect('/admin/financeiro');
            return;
        }

        $pagamento = $this->db->fetch(
            "SELECT * FROM financeiro_valores_mensais WHERE id = :id",
            ['id' => (int) $id]
        );

        if (!$pagamento) {
            $this->setFlashMessage('Pagamento não encontrado.', 'error');
            $this->redirect('/admin/financeiro');
            return;
        }

        $data = [
            'title' => 'Editar Pagamento - EducaTudo',
            'page_title' => 'Editar Pagamento',
            'user' => $user,
            'current_page' => 'financeiro',
            'pagamento' => $pagamento,
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('admin', 'admin/financeiro/editar', $data);
    }

    public function financeiroAtualizar($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/financeiro');
        }

        $user = $this->auth->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola']) || ($user['perfil_admin'] ?? '') !== 'dev') {
            $this->redirect('/admin/financeiro');
            return;
        }

        $status = $_POST['status'] ?? 'aberto';
        $valor_total = (float) str_replace(',', '.', $_POST['valor_total'] ?? '0');
        $data_vencimento = $_POST['data_vencimento'] ?? null;
        $data_pagamento = $_POST['data_pagamento'] ?? null;

        if (!in_array($status, ['aberto', 'pago'], true)) {
            $status = 'aberto';
        }

        $this->db->update(
            "UPDATE financeiro_valores_mensais
             SET valor_total = :valor_total,
                 status = :status,
                 data_vencimento = :data_vencimento,
                 data_pagamento = :data_pagamento
             WHERE id = :id",
            [
                'valor_total' => number_format($valor_total, 2, '.', ''),
                'status' => $status,
                'data_vencimento' => $data_vencimento ?: null,
                'data_pagamento' => $data_pagamento ?: null,
                'id' => (int) $id
            ]
        );

        $this->setFlashMessage('Pagamento atualizado.', 'success');
        $this->redirect('/admin/financeiro');
    }

    public function salvarFinanceiroConfig()
    {
        $user = $this->auth->getUser();
        if (!$user || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        try {
            $diaFechamento = (int)($_POST['financeiro_dia_fechamento'] ?? 1);
            $diaVencimento = (int)($_POST['financeiro_dia_vencimento'] ?? 5);
            $diaPagamento = (int)($_POST['financeiro_dia_pagamento'] ?? 5);

            $diaFechamento = max(1, min(31, $diaFechamento));
            $diaVencimento = max(1, min(31, $diaVencimento));
            $diaPagamento = max(1, min(31, $diaPagamento));

            $configs = [
                'financeiro_dia_fechamento' => (string) $diaFechamento,
                'financeiro_dia_vencimento' => (string) $diaVencimento,
                'financeiro_dia_pagamento' => (string) $diaPagamento
            ];

            foreach ($configs as $key => $value) {
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value)
                     VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    [$key, $value]
                );
            }

            $this->json(['success' => true, 'message' => 'Configurações financeiras salvas com sucesso']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function getFinanceiroConfig()
    {
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        return [
            'dia_fechamento' => (int) LayoutHelper::get('financeiro_dia_fechamento', '1'),
            'dia_vencimento' => (int) LayoutHelper::get('financeiro_dia_vencimento', '5'),
            'dia_pagamento' => (int) LayoutHelper::get('financeiro_dia_pagamento', '5'),
            'valor_por_usuario' => (float) LayoutHelper::get('valor_por_usuario', '0.00')
        ];
    }

    private function gerarCobrancaMensalSeNecessario($userId, array $financeConfig)
    {
        $diaFechamento = max(1, min(31, (int) ($financeConfig['dia_fechamento'] ?? 1)));
        $diaVencimento = max(1, min(31, (int) ($financeConfig['dia_vencimento'] ?? 5)));

        $today = new DateTime('today');
        if ((int) $today->format('j') < $diaFechamento) {
            return;
        }

        $mesReferencia = new DateTime('first day of previous month');
        $mesReferenciaStr = $mesReferencia->format('Y-m-01');

        $exists = $this->db->fetch(
            "SELECT id FROM financeiro_valores_mensais WHERE mes_referencia = :mes",
            ['mes' => $mesReferenciaStr]
        );

        if ($exists) {
            return;
        }

        $totalAlunosPagantes = $this->db->fetch("SELECT COUNT(*) as count FROM alunos WHERE ativo = 1 AND pagante = 1")['count'] ?? 0;
        $totalProfessoresPagantes = $this->db->fetch("SELECT COUNT(*) as count FROM professores WHERE ativo = 1 AND pagante = 1")['count'] ?? 0;
        $totalPagantes = $totalAlunosPagantes + $totalProfessoresPagantes;
        $valorPorUsuario = (float) ($financeConfig['valor_por_usuario'] ?? 0);
        $valorTotal = $totalPagantes * $valorPorUsuario;

        $dataVencimento = $this->calcularDataVencimento($mesReferencia, $diaVencimento);

        $this->db->insert(
            "INSERT INTO financeiro_valores_mensais
             (mes_referencia, total_alunos_pagantes, total_professores_pagantes, total_usuarios_pagantes,
              valor_por_usuario, valor_total, status, data_vencimento, registrado_por)
             VALUES (:mes_referencia, :total_alunos, :total_professores, :total_usuarios,
                     :valor_por_usuario, :valor_total, 'aberto', :data_vencimento, :registrado_por)",
            [
                'mes_referencia' => $mesReferenciaStr,
                'total_alunos' => $totalAlunosPagantes,
                'total_professores' => $totalProfessoresPagantes,
                'total_usuarios' => $totalPagantes,
                'valor_por_usuario' => number_format($valorPorUsuario, 2, '.', ''),
                'valor_total' => number_format($valorTotal, 2, '.', ''),
                'data_vencimento' => $dataVencimento,
                'registrado_por' => $userId
            ]
        );
    }

    private function calcularDataVencimento(DateTime $mesReferencia, int $diaVencimento)
    {
        $vencimento = (clone $mesReferencia)->modify('first day of next month');
        $ultimoDia = (int) $vencimento->format('t');
        $dia = min($diaVencimento, $ultimoDia);
        $vencimento->setDate((int) $vencimento->format('Y'), (int) $vencimento->format('m'), $dia);
        return $vencimento->format('Y-m-d');
    }
}
}
