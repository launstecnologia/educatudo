<?php

namespace App\Modulos\Mural\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Core\Database;
use App\Dominio\Pessoas\Services\DashboardAlunoService;
use App\Modulos\Mural\Services\MuralRecadoService;

/**
 * Mural de Recados — portal do aluno.
 * URLs preservadas: GET /mural-recados · POST /mural-recados/marcar-visto
 */
if (!class_exists('AlunoMuralController')) {
class AlunoMuralController extends BaseController
{
    private $authManager;
    private $db;
    private MuralRecadoService $servico;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        $this->servico = new MuralRecadoService($this->db);

        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        $user = $this->authManager->getUser();
        if (($user['tipo'] ?? '') !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
        }
    }

    private function redirectToCorrectDashboard($tipo): void
    {
        switch ($tipo) {
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'admin':
            case 'admin_escola':
                $this->redirect('/admin/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/dashboard');
        }
    }

    /**
     * GET /mural-recados
     */
    public function index(): void
    {
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch(
            'SELECT a.*, t.nome as turma_nome FROM alunos a LEFT JOIN turmas t ON a.turma_id = t.id WHERE a.id = :user_id',
            ['user_id' => $user['id']]
        );
        if (!$aluno) {
            $this->redirect('/logout');
            return;
        }

        $alunoTurmaId = isset($aluno['turma_id']) && $aluno['turma_id'] !== '' ? (int) $aluno['turma_id'] : 0;
        $filtroProfessor = isset($_GET['professor_id']) ? (int) $_GET['professor_id'] : '';
        $filtroMateria = isset($_GET['materia_id']) ? (int) $_GET['materia_id'] : '';
        $filtroDataDe = trim($_GET['data_de'] ?? '');
        $filtroDataAte = trim($_GET['data_ate'] ?? '');

        $recados = $this->servico->listarParaAluno((int) $aluno['id'], $alunoTurmaId, [
            'professor_id' => $filtroProfessor,
            'materia_id' => $filtroMateria,
            'data_de' => $filtroDataDe,
            'data_ate' => $filtroDataAte,
        ]);

        $recadosCompletos = [];
        foreach ($recados as $r) {
            $r['ja_visto'] = (bool) $this->db->fetch(
                'SELECT 1 FROM mural_recados_vistos WHERE mural_recado_id = :rid AND aluno_id = :aid',
                ['rid' => $r['id'], 'aid' => $aluno['id']]
            );
            $recadosCompletos[] = $r;
        }
        $recados = $recadosCompletos;

        $anexosPorRecado = $this->servico->listarAnexosDosRecados(
            array_map('intval', array_column($recados, 'id'))
        );
        foreach ($recados as $indice => $recado) {
            $recados[$indice]['anexos'] = $anexosPorRecado[(int) $recado['id']] ?? [];
        }

        $data = [
            'title' => 'Mural de Recados - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'recados' => $recados,
            'filtro_professor' => $filtroProfessor,
            'filtro_materia' => $filtroMateria,
            'filtro_data_de' => $filtroDataDe,
            'filtro_data_ate' => $filtroDataAte,
            'professores_opcoes' => $this->servico->listarProfessoresFiltroAluno(),
            'materias_opcoes' => $this->servico->listarMateriasFiltroAluno(),
            'current_page' => 'mural-recados',
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->viewWithLayout('student', 'student/mural-recados/index', $data);
    }

    /**
     * POST /mural-recados/marcar-visto — JSON
     * Só marca recados que o aluno pode ver (MuralRecadoService::marcarVistoAluno).
     */
    public function marcarVisto(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Sessão expirada.']);
            return;
        }
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch(
            'SELECT id, turma_id FROM alunos WHERE id = :user_id',
            ['user_id' => $user['id']]
        );
        if (!$aluno) {
            echo json_encode(['success' => false, 'error' => 'Aluno não encontrado']);
            return;
        }
        $ids = isset($_POST['ids']) && is_array($_POST['ids'])
            ? array_map('intval', array_filter($_POST['ids']))
            : [];
        if ($ids === []) {
            echo json_encode(['success' => true]);
            return;
        }
        $alunoId = (int) $aluno['id'];
        foreach ($ids as $rid) {
            $this->servico->marcarVistoAluno($rid, $alunoId);
        }
        (new DashboardAlunoService($this->db))->invalidarCacheAluno(
            $alunoId,
            (int) ($aluno['turma_id'] ?? 0)
        );
        echo json_encode(['success' => true]);
    }
}
}

if (!class_exists('AlunoMuralController', false) && class_exists(__NAMESPACE__ . '\\AlunoMuralController', false)) {
    class_alias(__NAMESPACE__ . '\\AlunoMuralController', 'AlunoMuralController');
}
