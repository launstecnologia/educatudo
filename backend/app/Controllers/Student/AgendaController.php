<?php

/**
 * EducaTudo - Agenda do aluno: reúne provas, jornadas, redação e eventos da
 * escola num único calendário, mais itens pessoais que o próprio aluno cria.
 */
if (!class_exists('AgendaController')) {
class AgendaController extends BaseController
{
    private $authManager;
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            $this->redirect('/');
        }
    }

    private function alunoId(): int
    {
        $user = $this->authManager->getUser();
        return (int) ($user['id'] ?? 0);
    }

    public function agenda(): void
    {
        $alunoId = $this->alunoId();
        $mes = (int) ($_GET['mes'] ?? date('n'));
        $ano = (int) ($_GET['ano'] ?? date('Y'));
        if ($mes < 1 || $mes > 12) {
            $mes = (int) date('n');
        }
        if ($ano < 2000 || $ano > 2100) {
            $ano = (int) date('Y');
        }

        $aluno = $this->db->fetch("SELECT turma_id FROM alunos WHERE id = :id", ['id' => $alunoId]);
        $turmaId = (int) ($aluno['turma_id'] ?? 0);

        require_once __DIR__ . '/../../Services/StudentAgendaService.php';
        $service = new StudentAgendaService($this->db);
        $eventos = $service->getMonthEvents($alunoId, $turmaId, $ano, $mes);

        $this->viewWithLayout('student', 'student/agenda', [
            'title' => 'Agenda - EducaTudo',
            'current_page' => 'agenda',
            'user' => $this->authManager->getUser(),
            'mes' => $mes,
            'ano' => $ano,
            'eventos' => $eventos,
        ]);
    }

    /**
     * Tipos que o aluno pode escolher pro item pessoal (só rótulo/cor — não
     * cria vínculo real com uma prova/jornada/redação de verdade).
     */
    public static function tiposItemPessoal(): array
    {
        return [
            'pessoal' => ['label' => 'Pessoal', 'cor' => '#eab308'],
            'prova' => ['label' => 'Prova', 'cor' => '#ef4444'],
            'jornada' => ['label' => 'Jornada', 'cor' => '#3b82f6'],
            'redacao' => ['label' => 'Redação', 'cor' => '#a855f7'],
            'estudo' => ['label' => 'Estudo', 'cor' => '#14b8a6'],
            'outro' => ['label' => 'Outro', 'cor' => '#6b7280'],
        ];
    }

    public function criarItemPessoal(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido', 'csrf_token' => $this->refreshCsrfToken()], 400);
            return;
        }

        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $data = trim((string) ($_POST['data'] ?? ''));
        $hora = trim((string) ($_POST['hora'] ?? ''));
        $tipo = trim((string) ($_POST['tipo'] ?? 'pessoal'));
        $banca = trim((string) ($_POST['banca'] ?? ''));
        $local = trim((string) ($_POST['local'] ?? ''));
        $link = trim((string) ($_POST['link'] ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));

        if ($titulo === '' || $data === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $this->json(['success' => false, 'error' => 'Título e data são obrigatórios'], 400);
            return;
        }
        if (!array_key_exists($tipo, self::tiposItemPessoal())) {
            $tipo = 'pessoal';
        }
        if ($hora !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora)) {
            $hora = '';
        }
        if ($link !== '' && !preg_match('#^https?://#i', $link)) {
            $link = '';
        }

        try {
            $id = $this->db->insert(
                "INSERT INTO aluno_agenda_itens (aluno_id, titulo, tipo, descricao, data, hora, banca, local, link, created_at)
                 VALUES (:aluno_id, :titulo, :tipo, :descricao, :data, :hora, :banca, :local, :link, NOW())",
                [
                    'aluno_id' => $this->alunoId(),
                    'titulo' => mb_substr($titulo, 0, 255),
                    'tipo' => $tipo,
                    'descricao' => $descricao !== '' ? mb_substr($descricao, 0, 2000) : null,
                    'data' => $data,
                    'hora' => $hora !== '' ? $hora : null,
                    'banca' => $banca !== '' ? mb_substr($banca, 0, 255) : null,
                    'local' => $local !== '' ? mb_substr($local, 0, 255) : null,
                    'link' => $link !== '' ? mb_substr($link, 0, 500) : null,
                ]
            );
            $this->json(['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            error_log("Agenda: falha ao criar item pessoal: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao salvar item'], 500);
        }
    }

    public function excluirItemPessoal(string $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido', 'csrf_token' => $this->refreshCsrfToken()], 400);
            return;
        }

        try {
            $this->db->delete(
                "DELETE FROM aluno_agenda_itens WHERE id = :id AND aluno_id = :aluno_id",
                ['id' => (int) $id, 'aluno_id' => $this->alunoId()]
            );
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            error_log("Agenda: falha ao excluir item pessoal: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao excluir item'], 500);
        }
    }
}
}
