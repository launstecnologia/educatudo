<?php

require_once __DIR__ . '/AdminBaseController.php';

if (!class_exists('PatrimonyAdminController')) {
class PatrimonyAdminController extends AdminBaseController
{
    private function schemaReady(): bool
    {
        return $this->db->tableExists('patrimony_assets') && $this->db->tableExists('school_locations');
    }

    private function userId(): ?int
    {
        $user = $this->auth->getUser();
        return isset($user['id']) ? (int) $user['id'] : null;
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('patrimonio', 'visualizar', false)) {
            return;
        }

        $data = [
            'title' => 'Patrimônio - EducaTudo',
            'user' => $this->auth->getUser(),
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'patrimonio',
            'schema_ready' => $this->schemaReady(),
            'flash' => $this->getFlashMessage(),
        ];

        if ($data['schema_ready']) {
            $data['locations'] = $this->db->fetchAll("SELECT * FROM school_locations ORDER BY ativo DESC, nome ASC");
            $data['suppliers'] = $this->db->tableExists('inventory_suppliers') ? $this->db->fetchAll("SELECT * FROM inventory_suppliers ORDER BY nome ASC") : [];
            $data['assets'] = $this->db->fetchAll(
                "SELECT a.*, l.nome AS location_nome,
                    CASE
                        WHEN a.data_aquisicao IS NULL OR a.valor_aquisicao <= 0 OR a.vida_util_meses <= 0 THEN a.valor_aquisicao
                        ELSE LEAST(a.valor_aquisicao, GREATEST(0, a.valor_aquisicao - (a.valor_aquisicao / a.vida_util_meses * TIMESTAMPDIFF(MONTH, a.data_aquisicao, CURDATE()))))
                    END AS valor_contabil
                 FROM patrimony_assets a
                 LEFT JOIN school_locations l ON l.id = a.location_id
                 ORDER BY FIELD(a.status, 'ativo', 'manutencao', 'emprestado', 'nao_localizado', 'baixado'), a.descricao ASC"
            );
            $data['movements'] = $this->db->fetchAll(
                "SELECT m.*, a.numero_patrimonio, a.descricao, lo.nome AS origem_nome, ld.nome AS destino_nome
                 FROM patrimony_movements m
                 JOIN patrimony_assets a ON a.id = m.asset_id
                 LEFT JOIN school_locations lo ON lo.id = m.location_origem_id
                 LEFT JOIN school_locations ld ON ld.id = m.location_destino_id
                 ORDER BY m.created_at DESC
                 LIMIT 80"
            );
            $data['checks'] = $this->db->fetchAll(
                "SELECT c.*, a.numero_patrimonio, a.descricao, l.nome AS location_nome
                 FROM patrimony_inventory_checks c
                 JOIN patrimony_assets a ON a.id = c.asset_id
                 LEFT JOIN school_locations l ON l.id = c.location_id
                 ORDER BY c.created_at DESC
                 LIMIT 50"
            );
        }

        $this->viewWithLayout('admin', 'admin/patrimonio/index', $data);
    }

    public function storeLocation(): void
    {
        if (!$this->canChange('cadastrar') || !$this->verifyPostCsrf()) {
            return;
        }
        $this->db->insert(
            "INSERT INTO school_locations (codigo, nome, tipo, bloco, andar, responsavel_nome, ativo)
             VALUES (:codigo, :nome, :tipo, :bloco, :andar, :responsavel, 1)
             ON DUPLICATE KEY UPDATE nome = VALUES(nome), tipo = VALUES(tipo), bloco = VALUES(bloco), andar = VALUES(andar), responsavel_nome = VALUES(responsavel_nome), ativo = 1, updated_at = NOW()",
            [
                'codigo' => trim($_POST['codigo'] ?? '') !== '' ? trim($_POST['codigo']) : null,
                'nome' => trim($_POST['nome'] ?? ''),
                'tipo' => $_POST['tipo'] ?? 'sala',
                'bloco' => trim($_POST['bloco'] ?? ''),
                'andar' => trim($_POST['andar'] ?? ''),
                'responsavel' => trim($_POST['responsavel_nome'] ?? ''),
            ]
        );
        $this->setFlashMessage('Ambiente físico salvo.', 'success');
        $this->redirect('/admin/patrimonio');
    }

    public function storeAsset(): void
    {
        if (!$this->canChange('cadastrar') || !$this->verifyPostCsrf()) {
            return;
        }
        $this->db->insert(
            "INSERT INTO patrimony_assets
             (numero_patrimonio, descricao, categoria, numero_serie, marca, modelo, data_aquisicao, valor_aquisicao, nota_fiscal, fornecedor_id, garantia_ate, vida_util_meses, location_id, responsavel_nome, origem, status, observacoes)
             VALUES (:numero, :descricao, :categoria, :serie, :marca, :modelo, :data, :valor, :nf, :fornecedor_id, :garantia, :vida, :location_id, :responsavel, :origem, :status, :obs)
             ON DUPLICATE KEY UPDATE descricao = VALUES(descricao), categoria = VALUES(categoria), numero_serie = VALUES(numero_serie), marca = VALUES(marca), modelo = VALUES(modelo),
                data_aquisicao = VALUES(data_aquisicao), valor_aquisicao = VALUES(valor_aquisicao), nota_fiscal = VALUES(nota_fiscal), fornecedor_id = VALUES(fornecedor_id),
                garantia_ate = VALUES(garantia_ate), vida_util_meses = VALUES(vida_util_meses), location_id = VALUES(location_id), responsavel_nome = VALUES(responsavel_nome),
                origem = VALUES(origem), status = VALUES(status), observacoes = VALUES(observacoes), updated_at = NOW()",
            $this->assetPayload()
        );
        $this->setFlashMessage('Bem patrimonial salvo.', 'success');
        $this->redirect('/admin/patrimonio');
    }

    public function moveAsset(): void
    {
        if (!$this->canChange('alterar') || !$this->verifyPostCsrf()) {
            return;
        }
        $assetId = (int) ($_POST['asset_id'] ?? 0);
        $asset = $this->db->fetch("SELECT * FROM patrimony_assets WHERE id = :id", ['id' => $assetId]);
        if (!$asset) {
            $this->setFlashMessage('Bem não encontrado.', 'error');
            $this->redirect('/admin/patrimonio');
        }
        $tipo = $_POST['tipo'] ?? 'transferencia';
        $dest = ($_POST['location_destino_id'] ?? '') === '' ? null : (int) $_POST['location_destino_id'];
        $status = $this->statusForMovement($tipo);
        try {
            $this->db->beginTransaction();
            $this->db->insert(
                "INSERT INTO patrimony_movements
                 (asset_id, tipo, location_origem_id, location_destino_id, responsavel_origem, responsavel_destino, motivo, documento, realizado_por)
                 VALUES (:asset_id, :tipo, :origem_id, :destino_id, :resp_origem, :resp_destino, :motivo, :documento, :user_id)",
                [
                    'asset_id' => $assetId,
                    'tipo' => $tipo,
                    'origem_id' => $asset['location_id'] ?? null,
                    'destino_id' => $dest,
                    'resp_origem' => $asset['responsavel_nome'] ?? '',
                    'resp_destino' => trim($_POST['responsavel_destino'] ?? ''),
                    'motivo' => trim($_POST['motivo'] ?? ''),
                    'documento' => trim($_POST['documento'] ?? ''),
                    'user_id' => $this->userId(),
                ]
            );
            $this->db->update(
                "UPDATE patrimony_assets SET location_id = COALESCE(:destino_id, location_id), responsavel_nome = COALESCE(NULLIF(:responsavel, ''), responsavel_nome), status = :status, updated_at = NOW() WHERE id = :id",
                ['destino_id' => $dest, 'responsavel' => trim($_POST['responsavel_destino'] ?? ''), 'status' => $status, 'id' => $assetId]
            );
            $this->db->commit();
            $this->setFlashMessage('Movimentação patrimonial registrada.', 'success');
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            $this->setFlashMessage($e->getMessage(), 'error');
        }
        $this->redirect('/admin/patrimonio');
    }

    public function checkAsset(): void
    {
        if (!$this->canChange('alterar') || !$this->verifyPostCsrf()) {
            return;
        }
        $assetId = (int) ($_POST['asset_id'] ?? 0);
        $locationId = ($_POST['location_id'] ?? '') === '' ? null : (int) $_POST['location_id'];
        $status = $_POST['status_conferencia'] ?? 'ok';
        $this->db->insert(
            "INSERT INTO patrimony_inventory_checks (asset_id, location_id, status_conferencia, observacoes, conferido_por)
             VALUES (:asset_id, :location_id, :status, :obs, :user_id)",
            ['asset_id' => $assetId, 'location_id' => $locationId, 'status' => $status, 'obs' => trim($_POST['observacoes'] ?? ''), 'user_id' => $this->userId()]
        );
        if ($status === 'nao_localizado') {
            $this->db->update("UPDATE patrimony_assets SET status = 'nao_localizado' WHERE id = :id", ['id' => $assetId]);
        } elseif ($locationId) {
            $this->db->update("UPDATE patrimony_assets SET location_id = :location_id, status = 'ativo' WHERE id = :id", ['location_id' => $locationId, 'id' => $assetId]);
        }
        $this->setFlashMessage('Conferência registrada.', 'success');
        $this->redirect('/admin/patrimonio');
    }

    private function assetPayload(): array
    {
        return [
            'numero' => trim($_POST['numero_patrimonio'] ?? ''),
            'descricao' => trim($_POST['descricao'] ?? ''),
            'categoria' => $_POST['categoria'] ?? 'outro',
            'serie' => trim($_POST['numero_serie'] ?? ''),
            'marca' => trim($_POST['marca'] ?? ''),
            'modelo' => trim($_POST['modelo'] ?? ''),
            'data' => ($_POST['data_aquisicao'] ?? '') ?: null,
            'valor' => (float) ($_POST['valor_aquisicao'] ?? 0),
            'nf' => trim($_POST['nota_fiscal'] ?? ''),
            'fornecedor_id' => ($_POST['fornecedor_id'] ?? '') === '' ? null : (int) $_POST['fornecedor_id'],
            'garantia' => ($_POST['garantia_ate'] ?? '') ?: null,
            'vida' => max(1, (int) ($_POST['vida_util_meses'] ?? 60)),
            'location_id' => ($_POST['location_id'] ?? '') === '' ? null : (int) $_POST['location_id'],
            'responsavel' => trim($_POST['responsavel_nome'] ?? ''),
            'origem' => $_POST['origem'] ?? 'proprio',
            'status' => $_POST['status'] ?? 'ativo',
            'obs' => trim($_POST['observacoes'] ?? ''),
        ];
    }

    private function statusForMovement(string $tipo): string
    {
        if ($tipo === 'manutencao_envio') {
            return 'manutencao';
        }
        if ($tipo === 'emprestimo') {
            return 'emprestado';
        }
        if ($tipo === 'baixa') {
            return 'baixado';
        }
        return 'ativo';
    }

    private function canChange(string $action): bool
    {
        return $this->enforceAdminPermissionKey('patrimonio', $action, false);
    }

    private function verifyPostCsrf(): bool
    {
        if ($this->verifyCsrfToken($_POST['_token'] ?? '')) {
            return true;
        }
        $this->setFlashMessage('Token inválido. Atualize a página e tente novamente.', 'error');
        $this->redirect('/admin/patrimonio');
        return false;
    }
}
}
