<?php

namespace App\Modulos\Mural\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Core\Database;
use App\Modulos\Mural\Services\MuralRecadoService;


/**
 * EducaTudo - Mural de Recados (Professor)
 * Professor cria recados para turma(s) ou todos.
 */

if (!class_exists('TeacherMuralController')) {
class TeacherMuralController extends BaseController
{
    private $authManager;
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
        }
        $user = $this->authManager->getUser();
        if (($user['tipo'] ?? '') !== 'professor') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
    }

    private function getTurmasProfessor()
    {
        $user = $this->authManager->getUser();
        $professor = $this->db->fetch("SELECT * FROM professores WHERE id = :id", ['id' => $user['id']]);
        if (!$professor) return [];
        $ids = json_decode($professor['turmas'] ?? '[]', true);
        if (!is_array($ids) || empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return $this->db->fetchAll("SELECT id, nome FROM turmas WHERE id IN ($placeholders) ORDER BY nome", $ids);
    }

    /**
     * Lista recados do professor com filtros: Matéria, Entre datas.
     */
    public function index()
    {
        $user = $this->authManager->getUser();
        $professor = $this->db->fetch("SELECT id FROM professores WHERE id = :id", ['id' => $user['id']]);
        if (!$professor) {
            $this->setFlashMessage('Professor não encontrado.', 'error');
            $this->redirect(URL . '/professor/dashboard');
        }
        $filtro_materia = isset($_GET['materia_id']) ? (int)$_GET['materia_id'] : '';
        $filtro_data_de = trim($_GET['data_de'] ?? '');
        $filtro_data_ate = trim($_GET['data_ate'] ?? '');
        $where = ["r.autor_tipo = 'professor'", "r.autor_id = :autor_id"];
        $params = ['autor_id' => $professor['id']];
        if ($filtro_materia) {
            $where[] = "r.materia_id = :filtro_materia";
            $params['filtro_materia'] = $filtro_materia;
        }
        if ($filtro_data_de !== '') {
            $where[] = "DATE(r.data_publicacao) >= :data_de";
            $params['data_de'] = $filtro_data_de;
        }
        if ($filtro_data_ate !== '') {
            $where[] = "DATE(r.data_publicacao) <= :data_ate";
            $params['data_ate'] = $filtro_data_ate;
        }
        $turmasProfessor = $this->getTurmasProfessor();
        $recebidos = (new MuralRecadoService($this->db))->listarRecebidosProfessor(
            (int) $professor['id'],
            array_map('intval', array_column($turmasProfessor, 'id'))
        );
        $recados = $this->db->fetchAll(
            "SELECT r.*,
                    (SELECT GROUP_CONCAT(t.nome) FROM mural_recados_turmas rt JOIN turmas t ON rt.turma_id = t.id WHERE rt.mural_recado_id = r.id) as turmas_nomes,
                    (SELECT a.nome FROM alunos a WHERE a.id = r.aluno_id LIMIT 1) as aluno_nome
             FROM mural_recados r
             WHERE " . implode(' AND ', $where) . "
             ORDER BY r.data_publicacao DESC",
            $params
        );
        $turmas = $this->getTurmasProfessor();
        $materias_opcoes = $this->getMateriasOpcoes();
        $servico = new MuralRecadoService($this->db);
        $data = [
            'title' => 'Mural de Recados - Professor',
            'page_title' => 'Mural de Recados',
            'user' => $user,
            'recados' => $recados,
            'anexos_por_recado' => $servico->listarAnexosDosRecados(array_map('intval', array_column($recados, 'id'))),
            'recebidos' => $recebidos,
            'turmas_opcoes' => $turmas,
            'alunos_opcoes' => $this->getAlunosDasTurmas(array_map('intval', array_column($turmas, 'id'))),
            'materias_opcoes' => $materias_opcoes,
            'filtro_materia' => $filtro_materia,
            'filtro_data_de' => $filtro_data_de,
            'filtro_data_ate' => $filtro_data_ate,
            'current_page' => 'mural-recados',
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->viewWithLayout('professor', 'teacher/mural-recados/index', $data);
    }

    private function getMateriasOpcoes()
    {
        try {
            return $this->db->fetchAll("SELECT id, nome FROM materias ORDER BY nome");
        } catch (\Throwable $e) {
        }
        try {
            return $this->db->fetchAll("SELECT id, nome FROM jornadas_materias ORDER BY nome");
        } catch (\Throwable $e) {
        }
        return [];
    }

    /**
     * @param array<int, int> $turmaIds
     * @return array<int, array<string, mixed>>
     */
    private function getAlunosDasTurmas(array $turmaIds): array
    {
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', $turmaIds))));
        if ($turmaIds === []) {
            return [];
        }
        $marcas = [];
        $params = [];
        foreach ($turmaIds as $i => $tid) {
            $chave = 't' . $i;
            $marcas[] = ':' . $chave;
            $params[$chave] = $tid;
        }
        return $this->db->fetchAll(
            "SELECT a.id, a.nome, t.nome AS turma_nome
             FROM alunos a
             INNER JOIN turmas t ON t.id = a.turma_id
             WHERE a.ativo = 1 AND a.turma_id IN (" . implode(',', $marcas) . ")
             ORDER BY t.nome, a.nome",
            $params
        );
    }

    /**
     * @return array{erro: string, enviar_para_todos: int, aluno_id: ?int, turmas: array<int, int>}
     */
    private function lerDestinoProfessor(): array
    {
        $permitidas = array_values(array_unique(array_map('intval', array_column($this->getTurmasProfessor(), 'id'))));
        $alcance = (string) ($_POST['alcance'] ?? '');
        if (!in_array($alcance, ['todos', 'turmas', 'aluno'], true)) {
            $alcance = (isset($_POST['enviar_para_todos']) && (string) $_POST['enviar_para_todos'] === '0') ? 'turmas' : 'todos';
        }
        $turmasPost = isset($_POST['turmas']) && is_array($_POST['turmas'])
            ? array_values(array_unique(array_filter(array_map('intval', $_POST['turmas']))))
            : [];
        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $destino = [
            'erro' => '',
            'enviar_para_todos' => 0,
            'aluno_id' => null,
            'turmas' => [],
        ];
        if ($alcance === 'todos') {
            $destino['enviar_para_todos'] = 1;
            return $destino;
        }
        if ($alcance === 'turmas') {
            if ($turmasPost === []) {
                $destino['erro'] = 'Selecione ao menos uma turma.';
                return $destino;
            }
            foreach ($turmasPost as $tid) {
                if (!in_array($tid, $permitidas, true)) {
                    $destino['erro'] = 'Só é possível enviar para as suas turmas.';
                    return $destino;
                }
            }
            $destino['turmas'] = $turmasPost;
            return $destino;
        }
        if ($alunoId <= 0 || $permitidas === []) {
            $destino['erro'] = 'Selecione um aluno das suas turmas.';
            return $destino;
        }
        $marcas = [];
        $params = ['id' => $alunoId];
        foreach ($permitidas as $i => $tid) {
            $chave = 't' . $i;
            $marcas[] = ':' . $chave;
            $params[$chave] = $tid;
        }
        $existe = $this->db->fetch(
            'SELECT id FROM alunos WHERE id = :id AND ativo = 1 AND turma_id IN (' . implode(',', $marcas) . ')',
            $params
        );
        if (!$existe) {
            $destino['erro'] = 'Selecione um aluno das suas turmas.';
            return $destino;
        }
        $destino['aluno_id'] = $alunoId;
        return $destino;
    }

    public function criar()
    {
        $this->redirect(URL . '/professor/mural-recados?novo=1');
    }

    public function dados($id)
    {
        $id = (int) $id;
        $user = $this->authManager->getUser();
        $recado = $this->db->fetch(
            "SELECT * FROM mural_recados WHERE id = :id AND autor_tipo = 'professor' AND autor_id = :autor_id",
            ['id' => $id, 'autor_id' => $user['id']]
        );
        if (!$recado) {
            $this->json(['success' => false, 'error' => 'Recado não encontrado.'], 404);
            return;
        }
        $turmas = array_map('intval', array_column(
            $this->db->fetchAll('SELECT turma_id FROM mural_recados_turmas WHERE mural_recado_id = :id', ['id' => $id]),
            'turma_id'
        ));
        $alcance = 'turmas';
        if (!empty($recado['aluno_id'])) {
            $alcance = 'aluno';
        } elseif (!empty($recado['enviar_para_todos'])) {
            $alcance = 'todos';
        }
        $this->json([
            'success' => true,
            'item' => [
                'id' => (int) $recado['id'],
                'titulo' => (string) ($recado['titulo'] ?? ''),
                'conteudo' => (string) ($recado['conteudo'] ?? ''),
                'alcance' => $alcance,
                'aluno_id' => (int) ($recado['aluno_id'] ?? 0),
                'turmas' => $turmas,
                'anexos' => array_map(static function (array $anexo): array {
                    $mime = (string) ($anexo['tipo_arquivo'] ?? '');
                    return [
                        'id' => (int) $anexo['id'],
                        'nome' => (string) ($anexo['arquivo_nome'] ?? ''),
                        'imagem' => strncmp($mime, 'image/', 6) === 0,
                    ];
                }, (new MuralRecadoService($this->db))->listarAnexos($id)),
            ],
        ]);
    }

    private function gravarAnexosEAvisar(int $recadoId, string $mensagem): void
    {
        $servico = new MuralRecadoService($this->db);
        $remover = isset($_POST['remover_anexos']) && is_array($_POST['remover_anexos'])
            ? array_map('intval', $_POST['remover_anexos'])
            : [];
        $servico->removerAnexos($recadoId, $remover);
        $resultado = $servico->salvarAnexos($recadoId, $_FILES['anexos'] ?? []);
        $servico->publicarNoSino($recadoId);
        if ($resultado['erros'] !== []) {
            $this->setFlashMessage($mensagem . ' ' . implode(' ', $resultado['erros']), 'error');
            return;
        }
        $this->setFlashMessage($mensagem, 'success');
    }

    /**
     * Salvar novo recado (POST)
     */
    public function salvar()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect(URL . '/professor/mural-recados');
        }
        $user = $this->authManager->getUser();
        $professor = $this->db->fetch("SELECT id FROM professores WHERE id = :id", ['id' => $user['id']]);
        if (!$professor) {
            $this->setFlashMessage('Professor não encontrado.', 'error');
            $this->redirect(URL . '/professor/mural-recados');
        }
        $titulo = trim($_POST['titulo'] ?? '');
        $conteudo = trim($_POST['conteudo'] ?? '');
        $destino = $this->lerDestinoProfessor();
        if ($titulo === '') {
            $this->setFlashMessage('Título é obrigatório.', 'error');
            $this->redirect(URL . '/professor/mural-recados?novo=1');
        }
        if ($destino['erro'] !== '') {
            $this->setFlashMessage($destino['erro'], 'error');
            $this->redirect(URL . '/professor/mural-recados?novo=1');
        }
        $data_sai = date('Y-m-d', strtotime('+30 days'));
        $id = (int) $this->db->insert(
            "INSERT INTO mural_recados (titulo, conteudo, autor_tipo, autor_id, enviar_para_todos, publico, aluno_id, data_sai_mural)
             VALUES (:titulo, :conteudo, 'professor', :autor_id, :enviar_para_todos, 'alunos', :aluno_id, :data_sai_mural)",
            [
                'titulo' => $titulo,
                'conteudo' => $conteudo,
                'autor_id' => $professor['id'],
                'enviar_para_todos' => $destino['enviar_para_todos'],
                'aluno_id' => $destino['aluno_id'],
                'data_sai_mural' => $data_sai,
            ]
        );
        foreach ($destino['turmas'] as $tid) {
            $this->db->insert("INSERT INTO mural_recados_turmas (mural_recado_id, turma_id) VALUES (:rid, :tid)", ['rid' => $id, 'tid' => $tid]);
        }
        $this->gravarAnexosEAvisar($id, 'Recado publicado no mural e no sino.');
        $this->redirect(URL . '/professor/mural-recados');
    }

    public function editar()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $this->redirect(URL . '/professor/mural-recados' . ($id > 0 ? ('?recado=' . $id) : ''));
    }

    /**
     * Atualizar recado (POST)
     */
    public function atualizar()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect(URL . '/professor/mural-recados');
        }
        $id = (int)($_POST['id'] ?? 0);
        $user = $this->authManager->getUser();
        $recado = $this->db->fetch("SELECT id FROM mural_recados WHERE id = :id AND autor_tipo = 'professor' AND autor_id = :autor_id", ['id' => $id, 'autor_id' => $user['id']]);
        if (!$recado) {
            $this->setFlashMessage('Recado não encontrado.', 'error');
            $this->redirect(URL . '/professor/mural-recados');
        }
        $titulo = trim($_POST['titulo'] ?? '');
        $conteudo = trim($_POST['conteudo'] ?? '');
        $destino = $this->lerDestinoProfessor();
        if ($titulo === '') {
            $this->setFlashMessage('Título é obrigatório.', 'error');
            $this->redirect(URL . '/professor/mural-recados?recado=' . $id);
        }
        if ($destino['erro'] !== '') {
            $this->setFlashMessage($destino['erro'], 'error');
            $this->redirect(URL . '/professor/mural-recados?recado=' . $id);
        }
        $data_sai = date('Y-m-d', strtotime('+30 days'));
        $this->db->query(
            "UPDATE mural_recados
             SET titulo = :titulo, conteudo = :conteudo, enviar_para_todos = :enviar_para_todos,
                 publico = 'alunos', aluno_id = :aluno_id, professor_id = NULL, data_sai_mural = :data_sai_mural
             WHERE id = :id AND autor_tipo = 'professor' AND autor_id = :autor_id",
            [
                'titulo' => $titulo,
                'conteudo' => $conteudo,
                'enviar_para_todos' => $destino['enviar_para_todos'],
                'aluno_id' => $destino['aluno_id'],
                'data_sai_mural' => $data_sai,
                'id' => $id,
                'autor_id' => $user['id'],
            ]
        );
        $this->db->query("DELETE FROM mural_recados_turmas WHERE mural_recado_id = :id", ['id' => $id]);
        foreach ($destino['turmas'] as $tid) {
            $this->db->insert("INSERT INTO mural_recados_turmas (mural_recado_id, turma_id) VALUES (:rid, :tid)", ['rid' => $id, 'tid' => $tid]);
        }
        $this->gravarAnexosEAvisar($id, 'Recado atualizado.');
        $this->redirect(URL . '/professor/mural-recados');
    }

    /**
     * Excluir recado (POST)
     */
    public function excluir()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $id = (int)($_POST['id'] ?? 0);
        $user = $this->authManager->getUser();
        $recado = $this->db->fetch("SELECT id FROM mural_recados WHERE id = :id AND autor_tipo = 'professor' AND autor_id = :autor_id", ['id' => $id, 'autor_id' => $user['id']]);
        if (!$recado) {
            $this->json(['error' => 'Recado não encontrado'], 404);
        }
        (new MuralRecadoService($this->db))->apagarArquivosDoRecado($id);
        $this->db->query("DELETE FROM mural_recados WHERE id = :id", ['id' => $id]);
        $this->json(['success' => true]);
    }

    public function marcarVisto()
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Sessão expirada.']);
            return;
        }
        $user = $this->authManager->getUser();
        $professor = $this->db->fetch('SELECT id FROM professores WHERE id = :id', ['id' => $user['id']]);
        if (!$professor) {
            echo json_encode(['success' => false, 'error' => 'Professor não encontrado.']);
            return;
        }
        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', array_filter($_POST['ids'])) : [];
        $servico = new MuralRecadoService($this->db);
        foreach ($ids as $rid) {
            $servico->marcarVistoProfessor($rid, (int) $professor['id']);
        }
        echo json_encode(['success' => true]);
    }
}
}
