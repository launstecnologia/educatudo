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
        $perPage = 10;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = (int) ($this->db->fetch(
            "SELECT COUNT(*) AS c FROM reunioes WHERE tipo = 'geral'"
        )['c'] ?? 0);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

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
             ORDER BY r.data_reuniao DESC, r.id DESC
             LIMIT " . (int) $perPage . " OFFSET " . (int) $offset
        ) ?: [];

        $turmas = $this->db->fetchAll("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome ASC") ?: [];
        $user = $this->auth->getUser();
        $flash = $this->getFlashMessage();

        $this->viewWithLayout('admin', 'admin/reunioes/geral', [
            'title'         => 'Reuniões Gerais',
            'page_title'    => 'Reuniões',
            'user'          => $user,
            'current_page'  => 'reunioes_geral',
            'reunioes'      => $reunioes,
            'turmas'        => $turmas,
            'relator_padrao'=> (string) ($user['nome'] ?? ''),
            'salvo_id'      => (int) ($_GET['salvo'] ?? 0),
            'csrf_token'    => $this->generateCsrfToken(),
            'flash_message' => $flash['message'],
            'flash_type'    => $flash['type'],
            'pagination'    => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    public function geralDados($id): void
    {
        $id = (int) $id;
        $reuniao = $this->db->fetch(
            "SELECT * FROM reunioes WHERE id = :id AND tipo = 'geral'",
            ['id' => $id]
        );
        if (!$reuniao) {
            $this->json(['success' => false, 'error' => 'Reunião não encontrada.'], 404);
            return;
        }
        $turmas = array_map('intval', array_column(
            $this->db->fetchAll("SELECT turma_id FROM reuniao_turmas WHERE reuniao_id = :id", ['id' => $id]),
            'turma_id'
        ));
        $anexos = $this->db->fetchAll(
            "SELECT id, nome, caminho FROM reuniao_anexos WHERE reuniao_id = :id ORDER BY id",
            ['id' => $id]
        ) ?: [];
        $this->json([
            'success' => true,
            'item' => [
                'id' => (int) $reuniao['id'],
                'titulo' => (string) ($reuniao['titulo'] ?? ''),
                'data_reuniao' => (string) ($reuniao['data_reuniao'] ?? ''),
                'hora_inicio' => $this->horaParaInput($reuniao['hora_inicio'] ?? null),
                'hora_fim' => $this->horaParaInput($reuniao['hora_fim'] ?? null),
                'local_reuniao' => (string) ($reuniao['local_reuniao'] ?? ''),
                'link_reuniao' => (string) ($reuniao['link_reuniao'] ?? ''),
                'descricao' => (string) ($reuniao['descricao'] ?? ''),
                'participantes' => (string) ($reuniao['participantes'] ?? ''),
                'encaminhamentos' => (string) ($reuniao['encaminhamentos'] ?? ''),
                'relator_nome' => (string) ($reuniao['relator_nome'] ?? ''),
                'turmas' => $turmas,
                'anexos' => array_map(static function ($a) {
                    return [
                        'id' => (int) ($a['id'] ?? 0),
                        'nome' => (string) ($a['nome'] ?? ''),
                    ];
                }, $anexos),
            ],
        ]);
    }

    public function geralSalvar(): void
    {
        if (!$this->csrfReuniaoOk()) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/reunioes/geral?novo=1');
            return;
        }
        $dados = $this->dadosFormularioReuniao();
        if ($dados['titulo'] === '') {
            $this->setFlashMessage('Título é obrigatório.', 'error');
            $this->redirect('/admin/reunioes/geral?novo=1');
            return;
        }
        $user = $this->auth->getUser();
        $reuniaoId = (int) $this->db->insert(
            "INSERT INTO reunioes (tipo, titulo, data_reuniao, hora_inicio, hora_fim, local_reuniao, link_reuniao, descricao, participantes, encaminhamentos, relator_nome, criado_por)
             VALUES ('geral', :titulo, :data_reuniao, :hora_inicio, :hora_fim, :local_reuniao, :link_reuniao, :descricao, :participantes, :encaminhamentos, :relator_nome, :criado_por)",
            array_merge($dados, ['criado_por' => (int) ($user['id'] ?? 0)])
        );

        $this->salvarTurmasReuniao($reuniaoId);
        $this->processarAnexos($reuniaoId, 'reunioes');

        $this->setFlashMessage('Reunião registrada. Baixe a ata em PDF se quiser arquivar.', 'success');
        $this->redirect('/admin/reunioes/geral?salvo=' . $reuniaoId);
    }

    public function geralAtualizar(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$this->csrfReuniaoOk()) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/reunioes/geral' . ($id > 0 ? ('?reuniao=' . $id) : ''));
            return;
        }
        $existe = $this->db->fetch("SELECT id FROM reunioes WHERE id = :id AND tipo = 'geral'", ['id' => $id]);
        if (!$existe) {
            $this->setFlashMessage('Reunião não encontrada.', 'error');
            $this->redirect('/admin/reunioes/geral');
            return;
        }
        $dados = $this->dadosFormularioReuniao();
        if ($dados['titulo'] === '') {
            $this->setFlashMessage('Título é obrigatório.', 'error');
            $this->redirect('/admin/reunioes/geral?reuniao=' . $id);
            return;
        }
        $this->db->query(
            "UPDATE reunioes SET
                titulo = :titulo, data_reuniao = :data_reuniao, hora_inicio = :hora_inicio, hora_fim = :hora_fim,
                local_reuniao = :local_reuniao, link_reuniao = :link_reuniao, descricao = :descricao,
                participantes = :participantes, encaminhamentos = :encaminhamentos, relator_nome = :relator_nome
             WHERE id = :id AND tipo = 'geral'",
            array_merge($dados, ['id' => $id])
        );
        $this->db->query("DELETE FROM reuniao_turmas WHERE reuniao_id = :id", ['id' => $id]);
        $this->salvarTurmasReuniao($id);
        $this->processarAnexos($id, 'reunioes');

        $this->setFlashMessage('Reunião atualizada.', 'success');
        $this->redirect('/admin/reunioes/geral?salvo=' . $id);
    }

    public function geralExcluir(): void
    {
        if (!$this->csrfReuniaoOk()) {
            $this->redirect('/admin/reunioes/geral');
            return;
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->db->query("DELETE FROM reuniao_turmas WHERE reuniao_id = :id", ['id' => $id]);
            $this->db->query("DELETE FROM reuniao_anexos WHERE reuniao_id = :id", ['id' => $id]);
            $this->db->query("DELETE FROM reunioes WHERE id = :id AND tipo = 'geral'", ['id' => $id]);
        }
        $this->setFlashMessage('Reunião removida.', 'success');
        $this->redirect('/admin/reunioes/geral');
    }

    public function geralPdf($id): void
    {
        $id = (int) $id;
        $reuniao = $this->db->fetch(
            "SELECT r.*, GROUP_CONCAT(DISTINCT t.nome ORDER BY t.nome SEPARATOR ', ') AS turmas_nomes
             FROM reunioes r
             LEFT JOIN reuniao_turmas rt ON rt.reuniao_id = r.id
             LEFT JOIN turmas t ON t.id = rt.turma_id
             WHERE r.id = :id AND r.tipo = 'geral'
             GROUP BY r.id",
            ['id' => $id]
        );
        if (!$reuniao) {
            $this->setFlashMessage('Reunião não encontrada.', 'error');
            $this->redirect('/admin/reunioes/geral');
            return;
        }
        $anexos = $this->db->fetchAll(
            "SELECT nome FROM reuniao_anexos WHERE reuniao_id = :id ORDER BY id",
            ['id' => $id]
        ) ?: [];
        $escola = class_exists('LayoutHelper') ? (string) LayoutHelper::getSystemTitle() : 'Escola';

        ob_start();
        extract([
            'reuniao' => $reuniao,
            'anexos' => $anexos,
            'escola' => $escola,
        ], EXTR_SKIP);
        require __DIR__ . '/../../Views/admin/reunioes/ata_pdf.php';
        $html = (string) ob_get_clean();

        if (!class_exists('\Dompdf\Dompdf')) {
            require_once __DIR__ . '/../../../vendor/autoload.php';
        }

        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $filename = 'ata-reuniao-' . $id . '-' . date('Ymd_His') . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $dompdf->output();
            exit;
        } finally {
            if ($oldDisplayErrors !== false) {
                ini_set('display_errors', (string) $oldDisplayErrors);
            }
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function sanitizarDescricaoAta(string $html): ?string
    {
        $html = trim($html);
        if ($html === '' || $html === '<p><br></p>') {
            return null;
        }
        if (function_exists('rich_text_render')) {
            $html = rich_text_render($html);
        }
        return $html !== '' ? $html : null;
    }

    private function csrfReuniaoOk(): bool
    {
        $token = (string) ($_POST['_token'] ?? $_POST['csrf_token'] ?? '');
        return $this->validateCsrf($token);
    }

    private function dadosFormularioReuniao(): array
    {
        $user = $this->auth->getUser();
        $relator = mb_substr(trim((string) ($_POST['relator_nome'] ?? '')), 0, 255);
        if ($relator === '') {
            $relator = mb_substr(trim((string) ($user['nome'] ?? '')), 0, 255);
        }
        $link = mb_substr(trim((string) ($_POST['link_reuniao'] ?? '')), 0, 500);

        return [
            'titulo' => mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 255),
            'data_reuniao' => $this->sanitizeDate((string) ($_POST['data_reuniao'] ?? '')),
            'hora_inicio' => $this->sanitizeTime((string) ($_POST['hora_inicio'] ?? '')),
            'hora_fim' => $this->sanitizeTime((string) ($_POST['hora_fim'] ?? '')),
            'local_reuniao' => mb_substr(trim((string) ($_POST['local_reuniao'] ?? '')), 0, 255) ?: null,
            'link_reuniao' => $link !== '' ? $link : null,
            'descricao' => $this->sanitizarDescricaoAta((string) ($_POST['descricao'] ?? '')),
            'participantes' => trim((string) ($_POST['participantes'] ?? '')) ?: null,
            'encaminhamentos' => trim((string) ($_POST['encaminhamentos'] ?? '')) ?: null,
            'relator_nome' => $relator !== '' ? $relator : null,
        ];
    }

    private function salvarTurmasReuniao(int $reuniaoId): void
    {
        if ($reuniaoId <= 0) {
            return;
        }
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['turma_ids'] ?? [])))));
        foreach ($turmaIds as $tid) {
            if ($tid <= 0) {
                continue;
            }
            $this->db->query(
                "INSERT IGNORE INTO reuniao_turmas (reuniao_id, turma_id) VALUES (:rid, :tid)",
                ['rid' => $reuniaoId, 'tid' => $tid]
            );
        }
    }

    private function horaParaInput($valor): string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }
        return substr($valor, 0, 5);
    }

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
