<?php

require_once __DIR__ . '/AdminBaseController.php';

if (!class_exists('InventoryAdminController')) {
class InventoryAdminController extends AdminBaseController
{
    private function schemaReady(): bool
    {
        return $this->db->tableExists('inventory_items') && $this->db->tableExists('inventory_movements');
    }

    private function userId(): ?int
    {
        $user = $this->auth->getUser();
        return isset($user['id']) ? (int) $user['id'] : null;
    }

    private function currentStockSql(): string
    {
        return "COALESCE((SELECT SUM(l.quantidade_atual) FROM inventory_lots l WHERE l.item_id = i.id), 0)";
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('almoxarifado', 'visualizar', false)) {
            return;
        }

        $data = [
            'title' => 'Almoxarifado - EducaTudo',
            'user' => $this->auth->getUser(),
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'almoxarifado',
            'schema_ready' => $this->schemaReady(),
            'flash' => $this->getFlashMessage(),
        ];

        if ($data['schema_ready']) {
            $stockSql = $this->currentStockSql();
            $data['items'] = $this->db->fetchAll(
                "SELECT i.*, {$stockSql} AS estoque_atual
                 FROM inventory_items i
                 ORDER BY i.ativo DESC, i.descricao ASC"
            );
            $data['warehouses'] = $this->db->fetchAll("SELECT * FROM inventory_warehouses ORDER BY ativo DESC, nome ASC");
            $data['suppliers'] = $this->db->fetchAll("SELECT * FROM inventory_suppliers ORDER BY ativo DESC, nome ASC");
            $data['lots'] = $this->db->fetchAll(
                "SELECT l.*, i.descricao AS item_descricao, i.unidade_medida, w.nome AS deposito_nome
                 FROM inventory_lots l
                 JOIN inventory_items i ON i.id = l.item_id
                 JOIN inventory_warehouses w ON w.id = l.warehouse_id
                 WHERE l.quantidade_atual > 0
                 ORDER BY CASE WHEN l.validade IS NULL THEN 1 ELSE 0 END, l.validade ASC, l.entrada_em ASC
                 LIMIT 80"
            );
            $data['movements'] = $this->db->fetchAll(
                "SELECT m.*, i.descricao AS item_descricao, i.unidade_medida, w.nome AS deposito_nome, wd.nome AS deposito_destino_nome
                 FROM inventory_movements m
                 JOIN inventory_items i ON i.id = m.item_id
                 JOIN inventory_warehouses w ON w.id = m.warehouse_id
                 LEFT JOIN inventory_warehouses wd ON wd.id = m.warehouse_destino_id
                 ORDER BY m.created_at DESC
                 LIMIT 60"
            );
            $data['requisitions'] = $this->db->fetchAll(
                "SELECT r.*, i.descricao AS item_descricao, i.unidade_medida, w.nome AS deposito_nome
                 FROM inventory_requisitions r
                 JOIN inventory_items i ON i.id = r.item_id
                 JOIN inventory_warehouses w ON w.id = r.warehouse_id
                 ORDER BY FIELD(r.status, 'pendente', 'aprovada', 'atendida', 'rejeitada', 'cancelada'), r.created_at DESC
                 LIMIT 80"
            );
            $data['alerts'] = [
                'low_stock' => $this->db->fetchAll(
                    "SELECT i.*, {$stockSql} AS estoque_atual
                     FROM inventory_items i
                     WHERE i.ativo = 1 AND {$stockSql} <= GREATEST(i.estoque_minimo, i.ponto_reposicao)
                     ORDER BY i.descricao ASC
                     LIMIT 20"
                ),
                'expiring' => $this->db->fetchAll(
                    "SELECT l.*, i.descricao AS item_descricao, w.nome AS deposito_nome
                     FROM inventory_lots l
                     JOIN inventory_items i ON i.id = l.item_id
                     JOIN inventory_warehouses w ON w.id = l.warehouse_id
                     WHERE l.quantidade_atual > 0 AND l.validade IS NOT NULL AND l.validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                     ORDER BY l.validade ASC
                     LIMIT 20"
                ),
            ];
        }

        $this->viewWithLayout('admin', 'admin/almoxarifado/index', $data);
    }

    public function storeItem(): void
    {
        if (!$this->enforceAdminPermissionKey('almoxarifado', 'cadastrar', false) || !$this->verifyPostCsrf()) {
            return;
        }
        $this->db->insert(
            "INSERT INTO inventory_items (codigo, descricao, unidade_medida, categoria, estoque_minimo, estoque_maximo, ponto_reposicao, custo_medio, ativo)
             VALUES (:codigo, :descricao, :unidade, :categoria, :minimo, :maximo, :reposicao, :custo, :ativo)
             ON DUPLICATE KEY UPDATE descricao = VALUES(descricao), unidade_medida = VALUES(unidade_medida), categoria = VALUES(categoria),
                estoque_minimo = VALUES(estoque_minimo), estoque_maximo = VALUES(estoque_maximo), ponto_reposicao = VALUES(ponto_reposicao),
                custo_medio = VALUES(custo_medio), ativo = VALUES(ativo), updated_at = NOW()",
            [
                'codigo' => trim($_POST['codigo'] ?? ''),
                'descricao' => trim($_POST['descricao'] ?? ''),
                'unidade' => trim($_POST['unidade_medida'] ?? 'un'),
                'categoria' => $_POST['categoria'] ?? 'outro',
                'minimo' => (float) ($_POST['estoque_minimo'] ?? 0),
                'maximo' => ($_POST['estoque_maximo'] ?? '') === '' ? null : (float) $_POST['estoque_maximo'],
                'reposicao' => (float) ($_POST['ponto_reposicao'] ?? 0),
                'custo' => (float) ($_POST['custo_medio'] ?? 0),
                'ativo' => !empty($_POST['ativo']) ? 1 : 0,
            ]
        );
        $this->setFlashMessage('Item salvo no catálogo.', 'success');
        $this->redirect('/admin/almoxarifado');
    }

    public function storeWarehouse(): void
    {
        if (!$this->enforceAdminPermissionKey('almoxarifado', 'cadastrar', false) || !$this->verifyPostCsrf()) {
            return;
        }
        $this->db->insert(
            "INSERT INTO inventory_warehouses (nome, tipo, responsavel_nome, ativo) VALUES (:nome, :tipo, :responsavel, 1)",
            ['nome' => trim($_POST['nome'] ?? ''), 'tipo' => $_POST['tipo'] ?? 'central', 'responsavel' => trim($_POST['responsavel_nome'] ?? '')]
        );
        $this->setFlashMessage('Depósito cadastrado.', 'success');
        $this->redirect('/admin/almoxarifado');
    }

    public function storeSupplier(): void
    {
        if (!$this->enforceAdminPermissionKey('almoxarifado', 'cadastrar', false) || !$this->verifyPostCsrf()) {
            return;
        }
        $this->db->insert(
            "INSERT INTO inventory_suppliers (nome, cnpj, contato, telefone, email, observacoes, ativo)
             VALUES (:nome, :cnpj, :contato, :telefone, :email, :obs, 1)",
            [
                'nome' => trim($_POST['nome'] ?? ''),
                'cnpj' => trim($_POST['cnpj'] ?? ''),
                'contato' => trim($_POST['contato'] ?? ''),
                'telefone' => trim($_POST['telefone'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'obs' => trim($_POST['observacoes'] ?? ''),
            ]
        );
        $this->setFlashMessage('Fornecedor cadastrado.', 'success');
        $this->redirect('/admin/almoxarifado');
    }

    public function storeMovement(): void
    {
        if (!$this->enforceAdminPermissionKey('almoxarifado', 'alterar', false) || !$this->verifyPostCsrf()) {
            return;
        }

        try {
            $tipo = $_POST['tipo'] ?? 'entrada';
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $warehouseId = (int) ($_POST['warehouse_id'] ?? 0);
            $qty = max(0, (float) ($_POST['quantidade'] ?? 0));
            if ($itemId <= 0 || $warehouseId <= 0 || $qty <= 0) {
                throw new RuntimeException('Informe item, depósito e quantidade.');
            }
            $this->db->beginTransaction();
            if ($tipo === 'entrada') {
                $cost = (float) ($_POST['custo_unitario'] ?? 0);
                $lotId = $this->db->insert(
                    "INSERT INTO inventory_lots (item_id, warehouse_id, lote, validade, quantidade_atual, custo_unitario, entrada_em)
                     VALUES (:item_id, :warehouse_id, :lote, :validade, :quantidade, :custo, NOW())",
                    [
                        'item_id' => $itemId,
                        'warehouse_id' => $warehouseId,
                        'lote' => trim($_POST['lote'] ?? ''),
                        'validade' => ($_POST['validade'] ?? '') ?: null,
                        'quantidade' => $qty,
                        'custo' => $cost,
                    ]
                );
                $this->recalculateAverageCost($itemId);
                $this->insertMovement($itemId, $warehouseId, $tipo, $qty, $lotId, $cost);
            } elseif ($tipo === 'transferencia') {
                $dest = (int) ($_POST['warehouse_destino_id'] ?? 0);
                if ($dest <= 0 || $dest === $warehouseId) {
                    throw new RuntimeException('Informe um depósito de destino diferente.');
                }
                $this->consumeLots($itemId, $warehouseId, $qty, 'transferencia');
                $destLot = $this->db->insert(
                    "INSERT INTO inventory_lots (item_id, warehouse_id, lote, validade, quantidade_atual, custo_unitario, entrada_em)
                     VALUES (:item_id, :warehouse_id, :lote, :validade, :quantidade, :custo, NOW())",
                    [
                        'item_id' => $itemId,
                        'warehouse_id' => $dest,
                        'lote' => 'TRANSF-' . date('YmdHis'),
                        'validade' => null,
                        'quantidade' => $qty,
                        'custo' => (float) ($_POST['custo_unitario'] ?? 0),
                    ]
                );
                $this->insertMovement($itemId, $warehouseId, $tipo, $qty, $destLot, null, $dest);
            } else {
                $this->consumeLots($itemId, $warehouseId, $qty, $tipo);
                $this->insertMovement($itemId, $warehouseId, $tipo, $qty);
            }
            $this->db->commit();
            $this->setFlashMessage('Movimentação registrada.', 'success');
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            $this->setFlashMessage($e->getMessage(), 'error');
        }
        $this->redirect('/admin/almoxarifado');
    }

    public function storeRequisition(): void
    {
        if (!$this->enforceAdminPermissionKey('almoxarifado', 'cadastrar', false) || !$this->verifyPostCsrf()) {
            return;
        }
        $this->db->insert(
            "INSERT INTO inventory_requisitions (item_id, warehouse_id, quantidade, setor, turma_id, professor_id, solicitante_nome, justificativa, solicitado_por)
             VALUES (:item_id, :warehouse_id, :quantidade, :setor, :turma_id, :professor_id, :solicitante, :justificativa, :user_id)",
            [
                'item_id' => (int) ($_POST['item_id'] ?? 0),
                'warehouse_id' => (int) ($_POST['warehouse_id'] ?? 0),
                'quantidade' => (float) ($_POST['quantidade'] ?? 0),
                'setor' => trim($_POST['setor'] ?? ''),
                'turma_id' => ($_POST['turma_id'] ?? '') === '' ? null : (int) $_POST['turma_id'],
                'professor_id' => ($_POST['professor_id'] ?? '') === '' ? null : (int) $_POST['professor_id'],
                'solicitante' => trim($_POST['solicitante_nome'] ?? ''),
                'justificativa' => trim($_POST['justificativa'] ?? ''),
                'user_id' => $this->userId(),
            ]
        );
        $this->setFlashMessage('Requisição criada.', 'success');
        $this->redirect('/admin/almoxarifado');
    }

    public function approve($id): void
    {
        $this->changeRequisitionStatus((int) $id, 'aprovada');
    }

    public function reject($id): void
    {
        $this->changeRequisitionStatus((int) $id, 'rejeitada');
    }

    public function fulfill($id): void
    {
        if (!$this->enforceAdminPermissionKey('almoxarifado', 'alterar', false) || !$this->verifyPostCsrf()) {
            return;
        }
        try {
            $req = $this->db->fetch("SELECT * FROM inventory_requisitions WHERE id = :id", ['id' => (int) $id]);
            if (!$req || !in_array($req['status'], ['aprovada', 'pendente'], true)) {
                throw new RuntimeException('Requisição não pode ser atendida.');
            }
            $this->db->beginTransaction();
            $this->consumeLots((int) $req['item_id'], (int) $req['warehouse_id'], (float) $req['quantidade'], 'saida');
            $this->insertMovement((int) $req['item_id'], (int) $req['warehouse_id'], 'saida', (float) $req['quantidade'], null, null, null, (int) $id, $req['setor'] ?? null, $req['turma_id'] ?? null, $req['professor_id'] ?? null, 'Atendimento da requisição #' . (int) $id);
            $this->db->update(
                "UPDATE inventory_requisitions SET status = 'atendida', atendido_por = :user_id, atendido_em = NOW(), updated_at = NOW() WHERE id = :id",
                ['user_id' => $this->userId(), 'id' => (int) $id]
            );
            $this->db->commit();
            $this->setFlashMessage('Requisição atendida e estoque baixado por PEPS.', 'success');
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            $this->setFlashMessage($e->getMessage(), 'error');
        }
        $this->redirect('/admin/almoxarifado');
    }

    private function verifyPostCsrf(): bool
    {
        if ($this->verifyCsrfToken($_POST['_token'] ?? '')) {
            return true;
        }
        $this->setFlashMessage('Token inválido. Atualize a página e tente novamente.', 'error');
        $this->redirect('/admin/almoxarifado');
        return false;
    }

    private function changeRequisitionStatus(int $id, string $status): void
    {
        if (!$this->enforceAdminPermissionKey('almoxarifado', 'alterar', false) || !$this->verifyPostCsrf()) {
            return;
        }
        $this->db->update(
            "UPDATE inventory_requisitions
             SET status = :status, aprovado_por = :user_id, aprovado_em = NOW(), updated_at = NOW()
             WHERE id = :id AND status = 'pendente'",
            ['status' => $status, 'user_id' => $this->userId(), 'id' => $id]
        );
        $this->setFlashMessage($status === 'aprovada' ? 'Requisição aprovada.' : 'Requisição rejeitada.', 'success');
        $this->redirect('/admin/almoxarifado');
    }

    private function consumeLots(int $itemId, int $warehouseId, float $qty, string $tipo): void
    {
        $lots = $this->db->fetchAll(
            "SELECT * FROM inventory_lots
             WHERE item_id = :item_id AND warehouse_id = :warehouse_id AND quantidade_atual > 0
             ORDER BY CASE WHEN validade IS NULL THEN 1 ELSE 0 END, validade ASC, entrada_em ASC, id ASC",
            ['item_id' => $itemId, 'warehouse_id' => $warehouseId]
        );
        $remaining = $qty;
        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (float) $lot['quantidade_atual']);
            $this->db->update(
                "UPDATE inventory_lots SET quantidade_atual = quantidade_atual - :take WHERE id = :id",
                ['take' => $take, 'id' => (int) $lot['id']]
            );
            $remaining -= $take;
        }
        if ($remaining > 0.0001) {
            throw new RuntimeException('Estoque insuficiente para concluir a ' . $tipo . '.');
        }
    }

    private function insertMovement(int $itemId, int $warehouseId, string $tipo, float $qty, ?int $lotId = null, ?float $cost = null, ?int $dest = null, ?int $req = null, ?string $setor = null, $turmaId = null, $professorId = null, ?string $motivo = null): void
    {
        $this->db->insert(
            "INSERT INTO inventory_movements
             (item_id, warehouse_id, warehouse_destino_id, lot_id, tipo, quantidade, custo_unitario, documento, fornecedor_id, requisicao_id, setor, turma_id, professor_id, motivo, realizado_por)
             VALUES (:item_id, :warehouse_id, :dest, :lot_id, :tipo, :quantidade, :custo, :documento, :fornecedor_id, :req, :setor, :turma_id, :professor_id, :motivo, :user_id)",
            [
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'dest' => $dest,
                'lot_id' => $lotId,
                'tipo' => $tipo,
                'quantidade' => $qty,
                'custo' => $cost,
                'documento' => trim($_POST['documento'] ?? ''),
                'fornecedor_id' => ($_POST['fornecedor_id'] ?? '') === '' ? null : (int) $_POST['fornecedor_id'],
                'req' => $req,
                'setor' => $setor ?? trim($_POST['setor'] ?? ''),
                'turma_id' => $turmaId === '' ? null : $turmaId,
                'professor_id' => $professorId === '' ? null : $professorId,
                'motivo' => $motivo ?? trim($_POST['motivo'] ?? ucfirst($tipo)),
                'user_id' => $this->userId(),
            ]
        );
    }

    private function recalculateAverageCost(int $itemId): void
    {
        $row = $this->db->fetch(
            "SELECT SUM(quantidade_atual * custo_unitario) AS total, SUM(quantidade_atual) AS qtd
             FROM inventory_lots WHERE item_id = :item_id AND quantidade_atual > 0",
            ['item_id' => $itemId]
        );
        $avg = ((float) ($row['qtd'] ?? 0)) > 0 ? (float) $row['total'] / (float) $row['qtd'] : 0;
        $this->db->update("UPDATE inventory_items SET custo_medio = :avg WHERE id = :id", ['avg' => $avg, 'id' => $itemId]);
    }
}
}
