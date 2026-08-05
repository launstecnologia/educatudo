<?php

require_once __DIR__ . '/AdminBaseController.php';

if (!class_exists('MeetingController')) {
class MeetingController extends AdminBaseController
{
    // ── Reunião com Pais (por aluno) ─────────────────────────────────────────

    /** Lista ATAs de reunião de um aluno */
    public function alunoIndex(): void
    {
        $alunoId = (int) ($_GET['aluno_id'] ?? 0);
        if ($alunoId <= 0) { $this->redirect('/admin/students'); return; }

        $aluno = $this->db->fetch("SELECT id, nome FROM alunos WHERE id = ?", [$alunoId]);
        if (!$aluno) { $this->redirect('/admin/students'); return; }

        $reunioes = $this->db->fetchAll(
            "SELECT r.*, GROUP_CONCAT(ra.nome ORDER BY ra.id SEPARATOR '|') AS anexo_nomes,
                    GROUP_CONCAT(ra.caminho ORDER BY ra.id SEPARATOR '|') AS anexo_caminhos
             FROM reunioes r
             LEFT JOIN reuniao_anexos ra ON ra.reuniao_id = r.id
             WHERE r.aluno_id = ? AND r.tipo = 'pais'
             GROUP BY r.id
             ORDER BY r.data_reuniao DESC",
            [$alunoId]
        ) ?: [];

        $this->viewWithLayout('admin', 'admin/reunioes/aluno', [
            'title'        => 'ATAs de Reunião — ' . ($aluno['nome'] ?? ''),
            'user'         => $this->auth->getUser(),
            'current_page' => 'reunioes',
            'aluno'        => $aluno,
            'reunioes'     => $reunioes,
            'csrf_token'   => $this->generateCsrfToken(),
            'flash_message'=> ($this->getFlashMessage())['message'],
            'flash_type'   => ($this->getFlashMessage())['type'],
        ]);
    }

    /** Salva nova ATA (reunião com pais) */
    public function alunoSalvar(): void
    {
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/students');
            return;
        }
        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        if ($alunoId <= 0) { $this->redirect('/admin/students'); return; }

        $user = $this->auth->getUser();
        $reuniaoId = (int) $this->db->insert(
            "INSERT INTO reunioes (tipo, titulo, data_reuniao, hora_inicio, hora_fim, local_reuniao, descricao, aluno_id, responsavel_nome, criado_por)
             VALUES ('pais', ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                mb_substr(trim($_POST['titulo'] ?? ''), 0, 255),
                $this->sanitizeDate($_POST['data_reuniao'] ?? ''),
                $this->sanitizeTime($_POST['hora_inicio'] ?? ''),
                $this->sanitizeTime($_POST['hora_fim'] ?? ''),
                mb_substr(trim($_POST['local_reuniao'] ?? ''), 0, 255) ?: null,
                trim($_POST['descricao'] ?? '') ?: null,
                $alunoId,
                mb_substr(trim($_POST['responsavel_nome'] ?? ''), 0, 255) ?: null,
                (int) ($user['id'] ?? 0),
            ]
        );

        $this->processarAnexos($reuniaoId, 'reunioes');

        $this->setFlashMessage('ATA registrada com sucesso.', 'success');
        $this->redirect('/admin/reunioes/aluno?aluno_id=' . $alunoId);
    }

    /** Exclui ATA */
    public function alunoExcluir(): void
    {
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->redirect('/admin/students'); return;
        }
        $id      = (int) ($_POST['id'] ?? 0);
        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        if ($id > 0) {
            $this->db->query("DELETE FROM reuniao_anexos WHERE reuniao_id = ?", [$id]);
            $this->db->query("DELETE FROM reunioes WHERE id = ? AND tipo = 'pais'", [$id]);
        }
        $this->setFlashMessage('ATA removida.', 'success');
        $this->redirect('/admin/reunioes/aluno?aluno_id=' . $alunoId);
    }

    // ── Reunião Geral ────────────────────────────────────────────────────────

    public function geralIndex(): void
    {
        $reunioes = $this->db->fetchAll(
            "SELECT r.*,
                    GROUP_CONCAT(DISTINCT t.nome ORDER BY t.nome SEPARATOR ', ') AS turmas_nomes,
                    GROUP_CONCAT(DISTINCT ra.nome ORDER BY ra.id SEPARATOR '|') AS anexo_nomes,
                    GROUP_CONCAT(DISTINCT ra.caminho ORDER BY ra.id SEPARATOR '|') AS anexo_caminhos
             FROM reunioes r
             LEFT JOIN reuniao_turmas rt ON rt.reuniao_id = r.id
             LEFT JOIN turmas t ON t.id = rt.turma_id
             LEFT JOIN reuniao_anexos ra ON ra.reuniao_id = r.id
             WHERE r.tipo = 'geral'
             GROUP BY r.id
             ORDER BY r.data_reuniao DESC"
        ) ?: [];

        $turmas = $this->db->fetchAll("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome ASC") ?: [];

        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/reunioes/geral', [
            'title'        => 'Reuniões Gerais',
            'user'         => $this->auth->getUser(),
            'current_page' => 'reunioes_geral',
            'reunioes'     => $reunioes,
            'turmas'       => $turmas,
            'csrf_token'   => $this->generateCsrfToken(),
            'flash_message'=> $flash['message'],
            'flash_type'   => $flash['type'],
        ]);
    }

    public function geralSalvar(): void
    {
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/reunioes/geral');
            return;
        }
        $user = $this->auth->getUser();
        $reuniaoId = (int) $this->db->insert(
            "INSERT INTO reunioes (tipo, titulo, data_reuniao, hora_inicio, hora_fim, local_reuniao, descricao, criado_por)
             VALUES ('geral', ?, ?, ?, ?, ?, ?, ?)",
            [
                mb_substr(trim($_POST['titulo'] ?? ''), 0, 255),
                $this->sanitizeDate($_POST['data_reuniao'] ?? ''),
                $this->sanitizeTime($_POST['hora_inicio'] ?? ''),
                $this->sanitizeTime($_POST['hora_fim'] ?? ''),
                mb_substr(trim($_POST['local_reuniao'] ?? ''), 0, 255) ?: null,
                trim($_POST['descricao'] ?? '') ?: null,
                (int) ($user['id'] ?? 0),
            ]
        );

        // Turmas selecionadas
        $turmaIds = array_filter(array_map('intval', (array) ($_POST['turma_ids'] ?? [])));
        foreach ($turmaIds as $tid) {
            $this->db->query("INSERT IGNORE INTO reuniao_turmas (reuniao_id, turma_id) VALUES (?, ?)", [$reuniaoId, $tid]);
        }

        $this->processarAnexos($reuniaoId, 'reunioes');

        $this->setFlashMessage('Reunião registrada com sucesso.', 'success');
        $this->redirect('/admin/reunioes/geral');
    }

    public function geralExcluir(): void
    {
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->redirect('/admin/reunioes/geral'); return;
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->db->query("DELETE FROM reuniao_turmas WHERE reuniao_id = ?", [$id]);
            $this->db->query("DELETE FROM reuniao_anexos WHERE reuniao_id = ?", [$id]);
            $this->db->query("DELETE FROM reunioes WHERE id = ? AND tipo = 'geral'", [$id]);
        }
        $this->setFlashMessage('Reunião removida.', 'success');
        $this->redirect('/admin/reunioes/geral');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function processarAnexos(int $reuniaoId, string $subdir): void
    {
        if (empty($_FILES['anexos']['name'][0])) return;

        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        $uploadDir = __DIR__ . '/../../storage/uploads/' . $subdir . '/' . $reuniaoId . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $count = count($_FILES['anexos']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['anexos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $tmp  = $_FILES['anexos']['tmp_name'][$i];
            $nome = basename($_FILES['anexos']['name'][$i]);

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $tmp);
            finfo_close($finfo);
            if (!in_array($mime, $allowedMimes, true)) continue;

            $slug = defined('TENANT_SLUG') ? TENANT_SLUG . '_' : '';
            $filename = $slug . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $nome);
            $dest = $uploadDir . $filename;
            if (!move_uploaded_file($tmp, $dest)) continue;

            $caminho = 'uploads/' . $subdir . '/' . $reuniaoId . '/' . $filename;
            $this->db->insert(
                "INSERT INTO reuniao_anexos (reuniao_id, nome, caminho, mime, tamanho) VALUES (?, ?, ?, ?, ?)",
                [$reuniaoId, $nome, $caminho, $mime, (int) $_FILES['anexos']['size'][$i]]
            );
        }
    }

    private function sanitizeDate(string $raw): string
    {
        $raw = trim($raw);
        $dt  = DateTime::createFromFormat('Y-m-d', $raw);
        return ($dt && $dt->format('Y-m-d') === $raw) ? $raw : date('Y-m-d');
    }

    private function sanitizeTime(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        $dt = DateTime::createFromFormat('H:i', $raw);
        return $dt ? $dt->format('H:i:s') : null;
    }
}
}
