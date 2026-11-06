<?php

namespace App\Modulos\Comunicacao\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Core\Database;
use App\Core\LayoutHelper;
use App\Modulos\Comunicacao\Services\SchoolCommunicationService;

/**
 * Recado do professor para os pais, quando a coordenação libera.
 */
class TeacherComunicacaoController extends BaseController
{
    private $db;
    private AuthManager $auth;
    private SchoolCommunicationService $service;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->auth = new AuthManager();
        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
        }
        $user = $this->auth->getUser();
        if (($user['tipo'] ?? '') !== 'professor') {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
        }
        if (LayoutHelper::get('module_professor_comunicacao_pais', '0') !== '1') {
            $this->setFlashMessage('A coordenação ainda não liberou o envio de recados aos pais.', 'error');
            $this->redirect(URL . '/professor/dashboard');
        }
        $this->service = new SchoolCommunicationService($this->db);
    }

    public function index(): void
    {
        $user = $this->auth->getUser();
        $flash = $this->getFlashMessage();
        $itens = $this->db->fetchAll(
            "SELECT id, titulo, created_at, enviar_push,
                (SELECT COUNT(*) FROM school_communication_reads r WHERE r.communication_id = c.id) AS read_count,
                (SELECT COUNT(*) FROM school_communication_replies rp WHERE rp.communication_id = c.id) AS reply_count
             FROM school_communications c
             WHERE autor_tipo = 'professor' AND autor_id = :id
             ORDER BY created_at DESC
             LIMIT 30",
            ['id' => (int) $user['id']]
        );
        $this->viewWithLayout('professor', 'teacher/school-communication/index', [
            'title' => 'Recados aos pais',
            'page_title' => 'Recados aos pais',
            'user' => $user,
            'turmas' => $this->turmasDoProfessor((int) $user['id']),
            'itens' => $itens,
            'current_page' => 'comunicacao-pais',
            'csrf_token' => $this->generateCsrfToken(),
            'flash_message' => $flash['message'] ?? null,
            'flash_type' => $flash['type'] ?? null,
        ]);
    }

    public function store(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect(URL . '/professor/comunicacao-pais');
        }
        $user = $this->auth->getUser();
        $professorId = (int) $user['id'];
        $minhas = $this->turmasDoProfessor($professorId);
        $permitidas = array_map('intval', array_column($minhas, 'id'));
        $title = trim((string) ($_POST['titulo'] ?? ''));
        $content = trim((string) ($_POST['conteudo'] ?? ''));
        $video = $this->youtube($_POST['video_url'] ?? '');
        $escolhidas = array_values(array_intersect(
            array_map('intval', is_array($_POST['turmas'] ?? null) ? $_POST['turmas'] : []),
            $permitidas
        ));
        if ($title === '' || $content === '') {
            $this->setFlashMessage('Título e mensagem são obrigatórios.', 'error');
            $this->redirect(URL . '/professor/comunicacao-pais');
        }
        if ($video === false) {
            $this->setFlashMessage('Informe um link do YouTube válido ou deixe o campo vazio.', 'error');
            $this->redirect(URL . '/professor/comunicacao-pais');
        }
        if ($escolhidas === []) {
            $this->setFlashMessage('Selecione ao menos uma turma sua.', 'error');
            $this->redirect(URL . '/professor/comunicacao-pais');
        }
        $enviarPush = isset($_POST['enviar_push']) ? 1 : 0;
        $id = (int) $this->db->insert(
            "INSERT INTO school_communications (titulo, conteudo, video_url, prioridade, permite_resposta, enviar_push, publico, autor_tipo, autor_id, status, published_at)
             VALUES (:title, :content, :video, 'normal', 1, :push, 'turmas', 'professor', :author, 'publicado', NOW())",
            [
                'title' => $title,
                'content' => $content,
                'video' => $video,
                'push' => $enviarPush,
                'author' => $professorId,
            ]
        );
        foreach ($escolhidas as $turmaId) {
            $this->db->insert(
                'INSERT INTO school_communication_classes (communication_id, turma_id) VALUES (:id, :turma)',
                ['id' => $id, 'turma' => $turmaId]
            );
        }
        $this->service->saveAttachments($id, $_FILES['attachments'] ?? []);
        if ($enviarPush === 1) {
            $parents = $this->service->parentIds('turmas', $escolhidas);
            $resumo = trim(preg_replace('/\s+/u', ' ', strip_tags($content)) ?? '');
            if (mb_strlen($resumo) > 160) {
                $resumo = mb_substr($resumo, 0, 157) . '...';
            }
            $resultadoPush = $this->service->push($parents, $title, $resumo, '/school-communications/' . $id, [
                'type' => 'school_communication',
                'communication_id' => (string) $id,
            ], $professorId);
            $enviados = (int) ($resultadoPush['sent'] ?? 0);
            $this->setFlashMessage(
                $enviados > 0
                    ? 'Recado enviado. O push foi aceito para ' . $enviados . ' aparelho(s).'
                    : 'Recado enviado. Nenhum aparelho aceitou o push.',
                $enviados > 0 ? 'success' : 'error'
            );
            $this->redirect(URL . '/professor/comunicacao-pais');
            return;
        }
        $this->setFlashMessage('Recado enviado aos pais.', 'success');
        $this->redirect(URL . '/professor/comunicacao-pais');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function turmasDoProfessor(int $professorId): array
    {
        $professor = $this->db->fetch('SELECT turmas FROM professores WHERE id = :id', ['id' => $professorId]);
        $ids = json_decode((string) ($professor['turmas'] ?? '[]'), true);
        if (!is_array($ids) || $ids === []) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        return $this->db->fetchAll("SELECT id, nome FROM turmas WHERE id IN ({$marcas}) ORDER BY nome", $ids);
    }

    /** @return string|null|false */
    private function youtube($value)
    {
        $url = trim((string) $value);
        if ($url === '') {
            return null;
        }
        if (!preg_match('#^https?://(www\.)?(youtube\.com|youtu\.be)/#i', $url)) {
            return false;
        }
        return mb_substr($url, 0, 500);
    }
}
