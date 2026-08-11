<?php

require_once __DIR__ . '/AdminBaseController.php';

/**
 * EducaTudo - TeacherDocsController
 * Checklist de documentação dos professores (diploma, licenciatura, contrato...).
 */
if (!class_exists('TeacherDocsController')) {
class TeacherDocsController extends AdminBaseController
{
    public const CHECKLIST = [
        'diploma' => 'Diploma',
        'licenciatura' => 'Licenciatura / Habilitação',
        'especializacao' => 'Especialização / Pós',
        'identidade' => 'RG / CPF',
        'comprovante_residencia' => 'Comprovante de residência',
        'contrato' => 'Contrato de trabalho',
        'antecedentes' => 'Antecedentes criminais',
        'outros' => 'Outros documentos',
    ];

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('documentos_professor', 'visualizar', false)) {
            return;
        }
        $pronto = $this->tableExists();
        $professorId = (int) ($_GET['professor_id'] ?? 0);
        $flash = $this->getFlashMessage();
        if ($professorId > 0) {
            $this->viewWithLayout('admin', 'admin/teachers-documentos/checklist', [
                'title' => 'Documentos do Professor - EducaTudo',
                'user' => $this->auth->getUser(),
                'current_page' => 'documentos_professor',
                'flash_message' => $flash['message'],
                'flash_type' => $flash['type'],
                'professor' => $this->db->fetch("SELECT id, nome FROM professores WHERE id = :id", ['id' => $professorId]),
                'checklist' => self::CHECKLIST,
                'docs' => $pronto ? $this->docsMap($professorId) : [],
                'schema_pronto' => $pronto,
                'csrf_token' => $this->generateCsrfToken(),
            ]);
            return;
        }
        $this->viewWithLayout('admin', 'admin/teachers-documentos/index', [
            'title' => 'Documentos dos Professores - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'documentos_professor',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'professores' => $this->professoresComProgresso($pronto),
            'total_checklist' => count(self::CHECKLIST),
            'schema_pronto' => $pronto,
        ]);
    }

    public function salvar(): void
    {
        if (!$this->enforceAdminPermissionKey('documentos_professor', 'cadastrar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/teachers-documentos');
            return;
        }
        $professorId = (int) ($_POST['professor_id'] ?? 0);
        if ($professorId <= 0 || !$this->tableExists()) {
            $this->setFlashMessage('Professor inválido ou recurso indisponível.', 'error');
            $this->redirect('/admin/teachers-documentos');
            return;
        }
        $statuses = (array) ($_POST['status'] ?? []);
        $observacoes = (array) ($_POST['observacao'] ?? []);
        foreach (self::CHECKLIST as $tipo => $label) {
            $status = (string) ($statuses[$tipo] ?? 'pendente');
            if (!in_array($status, ['pendente', 'entregue', 'dispensado'], true)) {
                $status = 'pendente';
            }
            $obs = trim((string) ($observacoes[$tipo] ?? ''));
            $this->db->query(
                "INSERT INTO professores_documentos (professor_id, tipo, status, observacao, entregue_em)
                 VALUES (:p, :t, :s, :o, :e)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), observacao = VALUES(observacao),
                    entregue_em = VALUES(entregue_em), updated_at = CURRENT_TIMESTAMP",
                [
                    'p' => $professorId,
                    't' => $tipo,
                    's' => $status,
                    'o' => $obs !== '' ? mb_substr($obs, 0, 500) : null,
                    'e' => $status === 'entregue' ? date('Y-m-d H:i:s') : null,
                ]
            );
        }
        $this->setFlashMessage('Documentação do professor atualizada.', 'success');
        $this->redirect('/admin/teachers-documentos?professor_id=' . $professorId);
    }

    /** @return array<string,array<string,mixed>> */
    private function docsMap(int $professorId): array
    {
        $rows = $this->db->fetchAll("SELECT tipo, status, observacao FROM professores_documentos WHERE professor_id = :id", ['id' => $professorId]) ?: [];
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['tipo']] = $r;
        }
        return $map;
    }

    /** @return list<array<string,mixed>> */
    private function professoresComProgresso(bool $pronto): array
    {
        $professores = $this->db->fetchAll("SELECT id, nome FROM professores WHERE ativo = 1 ORDER BY nome") ?: [];
        if (!$pronto) {
            return $professores;
        }
        $counts = $this->db->fetchAll(
            "SELECT professor_id, SUM(CASE WHEN status = 'entregue' THEN 1 ELSE 0 END) AS entregues
             FROM professores_documentos GROUP BY professor_id"
        ) ?: [];
        $map = [];
        foreach ($counts as $c) {
            $map[(int) $c['professor_id']] = (int) $c['entregues'];
        }
        foreach ($professores as &$p) {
            $p['entregues'] = $map[(int) $p['id']] ?? 0;
        }
        unset($p);
        return $professores;
    }

    private function tableExists(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'professores_documentos'");
            $cache = $row !== false && !empty($row);
        } catch (Throwable $e) {
            $cache = false;
        }
        return $cache;
    }
}
}
