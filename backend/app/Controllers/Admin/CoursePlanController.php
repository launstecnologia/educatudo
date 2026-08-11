<?php

require_once __DIR__ . '/AdminBaseController.php';
require_once __DIR__ . '/../../Models/Education/CoursePlan.php';
require_once __DIR__ . '/../../Models/Bncc/BnccSkill.php';

/**
 * EducaTudo - CoursePlanController (Plano de Curso)
 */
if (!class_exists('CoursePlanController')) {
class CoursePlanController extends AdminBaseController
{
    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('bncc', 'visualizar', false)) {
            return;
        }
        $model = new CoursePlan();
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/plano-curso/index', [
            'title' => 'Plano de Curso - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'bncc',
            'planos' => $model->listar(),
            'schema_pronto' => $model->tableExists(),
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
        ]);
    }

    public function form(): void
    {
        if (!$this->enforceAdminPermissionKey('bncc', 'visualizar', false)) {
            return;
        }
        $id = (int) ($_GET['id'] ?? 0);
        $model = new CoursePlan();
        $plano = $id > 0 ? $model->find($id) : null;
        $skill = new BnccSkill();
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/plano-curso/form', [
            'title' => ($plano ? 'Editar' : 'Novo') . ' Plano de Curso - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'bncc',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'plano' => $plano,
            'habilidades_plano' => $plano ? $model->habilidadesDetalhadas($id) : [],
            'habilidade_ids' => $plano ? $model->habilidadeIds($id) : [],
            'materias' => $this->db->fetchAll("SELECT id, nome FROM materias ORDER BY nome") ?: [],
            'series' => $this->tabela('serie') ? ($this->db->fetchAll("SELECT id, nome FROM serie ORDER BY nome") ?: []) : [],
            'anos_letivos' => $this->tabela('ano_letivo') ? ($this->db->fetchAll("SELECT id, ano, ativo FROM ano_letivo ORDER BY ano DESC") ?: []) : [],
            'ano_letivo_ativo_id' => $this->tabela('ano_letivo') ? (function($db) { $r = $db->fetch("SELECT id FROM ano_letivo WHERE ativo = 1 ORDER BY ano DESC LIMIT 1"); return $r ? (int)$r['id'] : 0; })($this->db) : 0,
            'habilidades' => $skill->listar('', '', 1000),
            'componentes' => $skill->componentes(),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function salvar(): void
    {
        if (!$this->enforceAdminPermissionKey('bncc', 'cadastrar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/plano-curso');
            return;
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ((int) ($_POST['materia_id'] ?? 0) <= 0) {
            $this->setFlashMessage('Selecione a matéria.', 'error');
            $this->redirect('/admin/plano-curso/form' . ($id > 0 ? '?id=' . $id : ''));
            return;
        }
        $model = new CoursePlan();
        $user = $this->auth->getUser();
        try {
            $novoId = $model->salvar([
                'ano_letivo_id' => $_POST['ano_letivo_id'] ?? 0,
                'serie_id' => $_POST['serie_id'] ?? 0,
                'materia_id' => $_POST['materia_id'] ?? 0,
                'carga_horaria_prevista' => $_POST['carga_horaria_prevista'] ?? 0,
                'avaliacoes_previstas' => $_POST['avaliacoes_previstas'] ?? 0,
                'conteudo_previsto' => $_POST['conteudo_previsto'] ?? '',
                'objetivos' => $_POST['objetivos'] ?? '',
                'metodologia' => $_POST['metodologia'] ?? '',
                'status' => $_POST['status'] ?? 'rascunho',
                'created_by' => isset($user['id']) ? (int) $user['id'] : null,
            ], $id > 0 ? $id : null);
            $model->definirHabilidades($novoId, (array) ($_POST['habilidades'] ?? []));
            $this->setFlashMessage('Plano de curso salvo com sucesso.', 'success');
            $this->redirect('/admin/plano-curso/form?id=' . $novoId);
        } catch (Throwable $e) {
            $this->setFlashMessage('Erro ao salvar: já existe um plano para essa série/matéria/ano.', 'error');
            $this->redirect('/admin/plano-curso/form' . ($id > 0 ? '?id=' . $id : ''));
        }
    }

    public function excluir(): void
    {
        if (!$this->enforceAdminPermissionKey('bncc', 'excluir', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/plano-curso');
            return;
        }
        (new CoursePlan())->excluir((int) ($_POST['id'] ?? 0));
        $this->setFlashMessage('Plano de curso excluído.', 'success');
        $this->redirect('/admin/plano-curso');
    }

    public function marcarTrabalhada(): void
    {
        if (!$this->enforceAdminPermissionKey('bncc', 'alterar', true)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->json(['error' => 'Sessão expirada.'], 419);
            return;
        }
        $planoId = (int) ($_POST['plano_id'] ?? 0);
        $habId = (int) ($_POST['habilidade_id'] ?? 0);
        $trabalhada = (string) ($_POST['trabalhada'] ?? '0') === '1';
        (new CoursePlan())->marcarTrabalhada($planoId, $habId, $trabalhada);
        $this->json(['ok' => true, 'trabalhada' => $trabalhada]);
    }

    private function tabela(string $nome): bool
    {
        try {
            $row = $this->db->fetch(
                "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [$nome]
            );
            return $row !== false && !empty($row);
        } catch (Throwable $e) {
            return false;
        }
    }
}
}
