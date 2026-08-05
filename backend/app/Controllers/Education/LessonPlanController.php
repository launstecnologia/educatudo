<?php
/**
 * EducaTudo - Controller de Planos de Aula
 * Gerencia planos de aula para professores e administração
 */

require_once __DIR__ . '/../../Models/Education/LessonPlan.php';
require_once __DIR__ . '/../../Models/Education/Subject.php';
require_once __DIR__ . '/../../Models/Education/ClassRoom.php';
require_once __DIR__ . '/../../Models/User/Teacher.php';
require_once __DIR__ . '/../../Helpers/LessonPlanAfternoonHelper.php';

if (!class_exists('LessonPlanController')) {
class LessonPlanController extends BaseController
{
    private $auth;
    private $db;
    private $planoAulaModel;
    private $subjectModel;
    private $turmaModel;
    private $teacherModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $this->planoAulaModel = new LessonPlan();
        $this->subjectModel = new Subject();
        $this->turmaModel = new ClassRoom();
        $this->teacherModel = new Teacher();
        
        // Verifica se está logado
        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        
        $user = $this->auth->getUser();
        // Verifica se é professor ou admin
        if ($user && !in_array($user['tipo'], ['professor', 'admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
    }
    
    /**
     * ============================================
     * ROTAS PARA PROFESSORES
     * ============================================
     */
    
    /**
     * Lista planos de aula do professor
     */
    public function index()
    {
        $user = $this->auth->getUser();
        
        if ($user['tipo'] !== 'professor') {
            $this->redirect('/admin/planos-aula');
            return;
        }
        
        // Busca professor
        $professor = $this->teacherModel->findById($user['id']);
        if (!$professor) {
            $this->setFlashMessage('Professor não encontrado', 'error');
            $this->redirect('/teacher/dashboard');
            return;
        }
        
        // Busca planos do professor (com filtros da listagem)
        try {
            $filtroDescricao = trim((string) ($_GET['descricao'] ?? ''));
            $filtroTurmaId = (int) ($_GET['turma_id'] ?? 0);
            $filtroMateriaId = (int) ($_GET['materia_id'] ?? 0);
            $filtroDataInicio = trim((string) ($_GET['data_inicio'] ?? ''));
            $filtroDataFim = trim((string) ($_GET['data_fim'] ?? ''));

            $where = ["pa.professor_id = :professor_id", "pa.deleted_at IS NULL"];
            $params = ['professor_id' => (int) $professor['id']];

            if ($filtroDescricao !== '') {
                $where[] = "(pa.titulo LIKE :descricao OR pa.conteudo LIKE :descricao OR pa.objetivos LIKE :descricao)";
                $params['descricao'] = '%' . $filtroDescricao . '%';
            }
            if ($filtroTurmaId > 0) {
                $where[] = "pa.turma_id = :turma_id";
                $params['turma_id'] = $filtroTurmaId;
            }
            if ($filtroMateriaId > 0) {
                $where[] = "pa.materia_id = :materia_id";
                $params['materia_id'] = $filtroMateriaId;
            }
            $whereSql = implode(' AND ', $where);
            $planos = $this->db->fetchAll(
                "SELECT pa.*, m.nome as materia_nome, t.nome as turma_nome
                 FROM planos_aula pa
                 LEFT JOIN materias m ON pa.materia_id = m.id
                 LEFT JOIN turmas t ON pa.turma_id = t.id
                 WHERE {$whereSql}
                 ORDER BY pa.created_at DESC",
                $params
            );

            if (!is_array($planos)) {
                $planos = [];
            }

            // Filtro de datas em memória para suportar data_aula em JSON (múltiplas datas)
            if ($filtroDataInicio !== '' || $filtroDataFim !== '') {
                $tsInicio = $filtroDataInicio !== '' ? strtotime($filtroDataInicio . ' 00:00:00') : null;
                $tsFim = $filtroDataFim !== '' ? strtotime($filtroDataFim . ' 23:59:59') : null;
                $planos = array_values(array_filter($planos, function ($plano) use ($tsInicio, $tsFim) {
                    $datas = [];
                    $rawDataAula = (string) ($plano['data_aula'] ?? '');
                    if ($rawDataAula === '') {
                        return false;
                    }
                    $decoded = json_decode($rawDataAula, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $datas = $decoded;
                    } else {
                        $datas = [$rawDataAula];
                    }

                    foreach ($datas as $dataItem) {
                        $ts = strtotime((string) $dataItem);
                        if ($ts === false) {
                            continue;
                        }
                        if ($tsInicio !== null && $ts < $tsInicio) {
                            continue;
                        }
                        if ($tsFim !== null && $ts > $tsFim) {
                            continue;
                        }
                        return true;
                    }
                    return false;
                }));
            }

            $perPage = (int) ($_GET['per_page'] ?? 20);
            $page = (int) ($_GET['page'] ?? 1);
            $allowedPerPage = [10, 20, 50, 100];
            if (!in_array($perPage, $allowedPerPage, true)) {
                $perPage = 20;
            }
            if ($page < 1) {
                $page = 1;
            }

            $totalItems = count($planos);
            $totalPages = max(1, (int) ceil($totalItems / max(1, $perPage)));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
            $planos = array_slice($planos, $offset, $perPage);

            $turmas = $this->db->fetchAll(
                "SELECT DISTINCT t.id, t.nome
                 FROM turmas t
                 INNER JOIN planos_aula pa ON pa.turma_id = t.id
                 WHERE pa.professor_id = :professor_id AND pa.deleted_at IS NULL
                 ORDER BY t.nome ASC",
                ['professor_id' => (int) $professor['id']]
            );
            $materias = $this->db->fetchAll(
                "SELECT DISTINCT m.id, m.nome
                 FROM materias m
                 INNER JOIN planos_aula pa ON pa.materia_id = m.id
                 WHERE pa.professor_id = :professor_id AND pa.deleted_at IS NULL
                 ORDER BY m.nome ASC",
                ['professor_id' => (int) $professor['id']]
            );

            require_once __DIR__ . '/../../Core/LayoutHelper.php';
            $data = [
                'title' => 'Meus Planos de Aula - EducaTudo',
                'user' => $user,
                'planos' => $planos,
                'stats' => ['total' => $totalItems],
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $totalItems,
                    'per_page' => $perPage,
                    'has_prev' => $page > 1,
                    'has_next' => $page < $totalPages,
                    'prev_page' => max(1, $page - 1),
                    'next_page' => $page + 1,
                ],
                'turmas' => is_array($turmas) ? $turmas : [],
                'materias' => is_array($materias) ? $materias : [],
                'filtros' => [
                    'descricao' => $filtroDescricao,
                    'turma_id' => $filtroTurmaId > 0 ? $filtroTurmaId : '',
                    'materia_id' => $filtroMateriaId > 0 ? $filtroMateriaId : '',
                    'data_inicio' => $filtroDataInicio,
                    'data_fim' => $filtroDataFim,
                ],
                'primary_color' => LayoutHelper::get('primary_color', '#3b82f6'),
                'primary_text_color' => LayoutHelper::get('primary_text_color', '#ffffff'),
                'current_page' => 'planos-aula'
            ];

            $this->viewWithLayout('professor', 'teacher/planos-aula/index', $data);
        } catch (Exception $e) {
            error_log("Erro em PlanoAulaController::index: " . $e->getMessage());
            $this->setFlashMessage('Erro ao carregar planos de aula', 'error');
            $this->redirect('/teacher/dashboard');
        }
    }
    
    /**
     * Formulário de criação de plano de aula
     */
    public function criar()
    {
        $user = $this->auth->getUser();
        
        if ($user['tipo'] !== 'professor') {
            $this->redirect('/admin/planos-aula');
            return;
        }
        
        // Busca professor
        $professor = $this->teacherModel->findById($user['id']);
        if (!$professor) {
            $this->setFlashMessage('Professor não encontrado', 'error');
            $this->redirect('/teacher/dashboard');
            return;
        }
        
        // Busca matérias do professor (apenas as liberadas)
        $materias_professor = json_decode($professor['materias'], true) ?: [];
        
        if (empty($materias_professor)) {
            $materias = [];
        } else {
            $placeholders = str_repeat('?,', count($materias_professor) - 1) . '?';
            $materias = $this->db->fetchAll(
                "SELECT * FROM materias WHERE nome IN ($placeholders) ORDER BY nome ASC",
                $materias_professor
            );
        }
        
        // Busca turmas do professor (apenas as liberadas)
        $turmas_professor = json_decode($professor['turmas'], true) ?: [];
        
        if (empty($turmas_professor)) {
            $turmas = [];
        } else {
            $placeholders = str_repeat('?,', count($turmas_professor) - 1) . '?';
            $turmas = $this->db->fetchAll(
                "SELECT * FROM turmas WHERE id IN ($placeholders) ORDER BY nome",
                $turmas_professor
            );
        }
        
        $anos_letivos = [];
        $ano_letivo_ativo_id = 0;
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'ano_letivo'");
            if ($row) {
                $anos_letivos = $this->db->fetchAll("SELECT id, ano, ativo FROM ano_letivo ORDER BY ano DESC") ?: [];
                $ativo = array_filter($anos_letivos, fn($a) => !empty($a['ativo']));
                $ano_letivo_ativo_id = $ativo ? (int) reset($ativo)['id'] : ($anos_letivos ? (int) $anos_letivos[0]['id'] : 0);
            }
        } catch (Throwable $e) {}

        $data = [
            'title' => 'Criar Plano de Aula - EducaTudo',
            'user' => $user,
            'professor' => $professor,
            'materias' => $materias,
            'turmas' => $turmas,
            'plano' => null,
            'anos_letivos' => $anos_letivos,
            'ano_letivo_ativo_id' => $ano_letivo_ativo_id,
            'current_page' => 'planos-aula'
        ];

        $this->viewWithLayout('professor', 'teacher/planos-aula/criar', $data);
    }
    
    /**
     * Salva novo plano de aula
     */
    public function salvar()
    {
        $user = $this->auth->getUser();
        
        if ($user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        try {
            // Lê dados JSON do body
            $input = file_get_contents('php://input');
            $postData = json_decode($input, true);
            
            // Se não conseguir ler JSON, tenta $_POST
            if (!$postData) {
                $postData = $_POST;
            }
            
            // Validação
            $errors = [];
            
            if (empty($postData['materia_id'])) {
                $errors['materia_id'] = 'Matéria é obrigatória';
            }
            // Aceita turmas_id[] (array) ou turma_id (singular para compatibilidade)
            $turmasSelecionadas = [];
            if (!empty($postData['turmas_id']) && is_array($postData['turmas_id'])) {
                $turmasSelecionadas = $postData['turmas_id'];
            } elseif (!empty($postData['turma_id'])) {
                $turmasSelecionadas = [$postData['turma_id']];
            }
            
            if (empty($turmasSelecionadas)) {
                $errors['turmas_id'] = 'Pelo menos uma turma é obrigatória';
            }
            if (empty($postData['data_aula'])) {
                $errors['data_aula'] = 'Data da aula é obrigatória';
            } else {
                // Valida se é JSON válido ou data única
                $dataAula = $postData['data_aula'];
                $datasArray = json_decode($dataAula, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($datasArray)) {
                    if (empty($datasArray)) {
                        $errors['data_aula'] = 'Pelo menos uma data é obrigatória';
                    }
                } else {
                    // Se não for JSON, valida como data única
                    if (!strtotime($dataAula)) {
                        $errors['data_aula'] = 'Data inválida';
                    }
                }
            }
            if (empty($postData['titulo'])) {
                $errors['titulo'] = 'Título é obrigatório';
            }

            $aulasTarde = LessonPlanAfternoonHelper::validateAndBuild($postData);
            $errors = array_merge($errors, $aulasTarde['errors']);
            
            if (!empty($errors)) {
                $this->json(['error' => 'Dados inválidos', 'errors' => $errors], 400);
                return;
            }
            
            // Busca professor
            $professor = $this->teacherModel->findById($user['id']);
            if (!$professor) {
                throw new Exception('Professor não encontrado');
            }
            
            // Processa múltiplas datas
            $dataAula = $postData['data_aula'];
            // Se for JSON, decodifica e pega a primeira data (para compatibilidade com o banco)
            $datasArray = json_decode($dataAula, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($datasArray) && !empty($datasArray)) {
                // Armazena todas as datas como JSON no campo data_aula
                $dataAula = json_encode($datasArray);
            }
            
            // Busca informações das turmas e matéria para gerar ano_disciplina
            $materia = $this->db->fetch(
                "SELECT nome FROM materias WHERE id = :id",
                ['id' => $postData['materia_id']]
            );
            
            $turmasInfo = [];
            if (!empty($turmasSelecionadas)) {
                $placeholders = str_repeat('?,', count($turmasSelecionadas) - 1) . '?';
                $turmasInfo = $this->db->fetchAll(
                    "SELECT id, nome FROM turmas WHERE id IN ($placeholders)",
                    $turmasSelecionadas
                );
            }
            
            // Cria um plano de aula para cada turma selecionada
            $planosCriados = [];
            $erros = [];
            
            foreach ($turmasSelecionadas as $turmaId) {
                try {
                    // Busca o nome da turma para gerar ano_disciplina
                    $turmaNome = '';
                    foreach ($turmasInfo as $turma) {
                        if ($turma['id'] == $turmaId) {
                            $turmaNome = $turma['nome'];
                            break;
                        }
                    }
                    
                    // Gera ano_disciplina automaticamente se não foi fornecido
                    $anoDisciplina = $postData['ano_disciplina'] ?? null;
                    if (empty($anoDisciplina) && $turmaNome && $materia) {
                        $anoDisciplina = $turmaNome . ' / ' . $materia['nome'];
                    }
                    
                    // Processa objetivos para adicionar <br><br> entre elementos
                    $objetivos = $postData['objetivos'] ?? null;
                    if (!empty($objetivos)) {
                        // Substitui fechamento de parágrafo seguido de abertura por <br><br>
                        $objetivos = preg_replace('/<\/p>\s*<p[^>]*>/i', '<br><br>', $objetivos);
                        // Substitui fechamento de lista seguido de parágrafo ou outra lista por <br><br>
                        $objetivos = preg_replace('/<\/[uo]l>\s*<p[^>]*>/i', '<br><br>', $objetivos);
                        $objetivos = preg_replace('/<\/p>\s*<[uo]l[^>]*>/i', '<br><br>', $objetivos);
                        // Remove tags <p> restantes no início e fim
                        $objetivos = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $objetivos);
                        // Remove tags <p> vazias
                        $objetivos = preg_replace('/<p[^>]*><\/p>/i', '', $objetivos);
                    }
                    
                    // Prepara dados para esta turma
                    $data = [
                        'professor_id' => $professor['id'],
                        'materia_id' => $postData['materia_id'],
                        'turma_id' => $turmaId,
                        'data_aula' => $dataAula,
                        'titulo' => $postData['titulo'],
                        'ano_disciplina' => $anoDisciplina,
                        'modulo' => $postData['modulo'] ?? null,
                        'aula_num' => $postData['aula_num'] ?? null,
                        'paginas' => $postData['paginas'] ?? null,
                        'conteudo' => $postData['conteudo'] ?? null,
                        'conteudo_lista' => $postData['conteudo_lista'] ?? null,
                        'objetivos' => $objetivos,
                        'objetivos_lista' => $postData['objetivos_lista'] ?? null,
                        'metodologia' => $postData['metodologia'] ?? null,
                        'periodo_tarde_tema' => $postData['periodo_tarde_tema'] ?? null,
                        'periodo_tarde_exercicios' => $postData['periodo_tarde_exercicios'] ?? null,
                        'recursos' => $postData['recursos'] ?? null,
                        'recursos_lista' => isset($postData['recursos_lista']) ? $postData['recursos_lista'] : null,
                        'aulas_tarde_oficinas' => $aulasTarde['value'],
                        'avaliacao' => $postData['avaliacao'] ?? null,
                        'avaliacao_apostila' => $postData['avaliacao_apostila'] ?? null,
                        'avaliacao_conteudo' => $postData['avaliacao_conteudo'] ?? null,
                        'avaliacao_paginas' => $postData['avaliacao_paginas'] ?? null,
                        'observacoes' => $postData['observacoes'] ?? null,
                        'status' => $postData['status'] ?? 'rascunho'
                    ];
                    
                    $id = $this->planoAulaModel->create($data);
                    $planosCriados[] = [
                        'id' => $id,
                        'turma_id' => $turmaId,
                        'turma_nome' => $turmaNome
                    ];
                } catch (Exception $e) {
                    $erros[] = "Erro ao criar plano para turma {$turmaNome}: " . $e->getMessage();
                    error_log("Erro ao criar plano de aula para turma {$turmaId}: " . $e->getMessage());
                }
            }
            
            if (empty($planosCriados)) {
                throw new Exception('Nenhum plano de aula foi criado. ' . implode(' ', $erros));
            }
            
            $mensagem = count($planosCriados) > 1 
                ? count($planosCriados) . ' planos de aula criados com sucesso'
                : 'Plano de aula criado com sucesso';
            
            $this->json([
                'success' => true,
                'message' => $mensagem,
                'planos_criados' => $planosCriados,
                'erros' => $erros
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao salvar plano de aula: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Visualiza plano de aula
     */
    public function visualizar($id)
    {
        $user = $this->auth->getUser();
        
        $plano = $this->planoAulaModel->findById($id);
        if (!$plano) {
            $this->setFlashMessage('Plano de aula não encontrado', 'error');
            if ($user['tipo'] === 'professor') {
                $this->redirect('/teacher/planos-aula');
            } else {
                $this->redirect('/admin/planos-aula');
            }
            return;
        }
        
        // Verifica permissão
        if ($user['tipo'] === 'professor' && $plano['professor_id'] != $user['id']) {
            $this->setFlashMessage('Você não tem permissão para visualizar este plano', 'error');
            $this->redirect('/teacher/planos-aula');
            return;
        }
        
        // Decodifica recursos_lista se existir
        if (!empty($plano['recursos_lista'])) {
            $plano['recursos_lista'] = json_decode($plano['recursos_lista'], true);
        }
        
        $data = [
            'title' => 'Visualizar Plano de Aula - EducaTudo',
            'user' => $user,
            'plano' => $plano,
            'isAdmin' => in_array($user['tipo'], ['admin', 'admin_escola']),
            'current_page' => 'planos-aula'
        ];
        
        $layout = $user['tipo'] === 'professor' ? 'professor' : 'admin';
        $view = $user['tipo'] === 'professor' ? 'teacher/planos-aula/visualizar' : 'admin/planos-aula/visualizar';
        
        $this->viewWithLayout($layout, $view, $data);
    }
    
    /**
     * Formulário de edição de plano de aula
     */
    public function editar($id)
    {
        $user = $this->auth->getUser();
        
        if (!in_array($user['tipo'], ['professor', 'admin', 'admin_escola'])) {
            $this->redirect('/admin/planos-aula');
            return;
        }
        
        $plano = $this->planoAulaModel->findById($id);
        if (!$plano) {
            $this->setFlashMessage('Plano de aula não encontrado', 'error');
            $this->redirect('/teacher/planos-aula');
            return;
        }
        
        if ($user['tipo'] === 'professor') {
            // Verifica se pode editar (só se for o dono e estiver em rascunho)
            if (!$this->planoAulaModel->canEdit($id, $user['id'])) {
                $this->setFlashMessage('Você só pode editar planos em rascunho', 'error');
                $this->redirect('/teacher/planos-aula');
                return;
            }
            
            // Busca professor
            $professor = $this->teacherModel->findById($user['id']);
            if (!$professor) {
                $this->setFlashMessage('Professor não encontrado', 'error');
                $this->redirect('/teacher/dashboard');
                return;
            }
            
            // Busca matérias do professor (apenas as liberadas)
            $materias_professor = json_decode($professor['materias'], true) ?: [];
            
            if (empty($materias_professor)) {
                $materias = [];
            } else {
                $placeholders = str_repeat('?,', count($materias_professor) - 1) . '?';
                $materias = $this->db->fetchAll(
                    "SELECT * FROM materias WHERE nome IN ($placeholders) ORDER BY nome ASC",
                    $materias_professor
                );
            }
            
            // Busca turmas do professor (apenas as liberadas)
            $turmas_professor = json_decode($professor['turmas'], true) ?: [];
            
            if (empty($turmas_professor)) {
                $turmas = [];
            } else {
                $placeholders = str_repeat('?,', count($turmas_professor) - 1) . '?';
                $turmas = $this->db->fetchAll(
                    "SELECT * FROM turmas WHERE id IN ($placeholders) ORDER BY nome",
                    $turmas_professor
                );
            }
        } else {
            $materias = $this->subjectModel->getAll();
            $turmas = $this->turmaModel->getActive();
        }
        
        // Decodifica recursos_lista se existir
        if (!empty($plano['recursos_lista'])) {
            $plano['recursos_lista'] = json_decode($plano['recursos_lista'], true);
        }
        
        $data = [
            'title' => 'Editar Plano de Aula - EducaTudo',
            'user' => $user,
            'plano' => $plano,
            'materias' => $materias,
            'turmas' => $turmas,
            'current_page' => 'planos-aula',
            'back_url' => $user['tipo'] === 'professor' ? URL . '/professor/planos-aula' : URL . '/admin/planos-aula',
            'update_url' => $user['tipo'] === 'professor'
                ? URL . '/professor/planos-aula/atualizar/' . $plano['id']
                : URL . '/admin/planos-aula/atualizar/' . $plano['id'],
            'redirect_url' => $user['tipo'] === 'professor' ? URL . '/professor/planos-aula' : URL . '/admin/planos-aula'
        ];
        
        $layout = $user['tipo'] === 'professor' ? 'professor' : 'admin';
        $this->viewWithLayout($layout, 'teacher/planos-aula/editar', $data);
    }
    
    /**
     * Atualiza plano de aula
     */
    public function atualizar($id)
    {
        $user = $this->auth->getUser();
        
        if (!in_array($user['tipo'], ['professor', 'admin', 'admin_escola'])) {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        try {
            // Verifica se pode editar
            if ($user['tipo'] === 'professor' && !$this->planoAulaModel->canEdit($id, $user['id'])) {
                throw new Exception('Você só pode editar planos em rascunho');
            }
            
            // Lê dados JSON do body
            $input = file_get_contents('php://input');
            $postData = json_decode($input, true);
            
            // Se não conseguir ler JSON, tenta $_POST
            if (!$postData) {
                $postData = $_POST;
            }
            
            // Validação
            $errors = [];
            
            if (empty($postData['materia_id'])) {
                $errors['materia_id'] = 'Matéria é obrigatória';
            }
            if (empty($postData['turma_id'])) {
                $errors['turma_id'] = 'Turma é obrigatória';
            }
            if (empty($postData['data_aula'])) {
                $errors['data_aula'] = 'Data da aula é obrigatória';
            } else {
                // Valida se é JSON válido ou data única
                $dataAula = $postData['data_aula'];
                $datasArray = json_decode($dataAula, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($datasArray)) {
                    if (empty($datasArray)) {
                        $errors['data_aula'] = 'Pelo menos uma data é obrigatória';
                    }
                } else {
                    // Se não for JSON, valida como data única
                    if (!strtotime($dataAula)) {
                        $errors['data_aula'] = 'Data inválida';
                    }
                }
            }
            if (empty($postData['titulo'])) {
                $errors['titulo'] = 'Título é obrigatório';
            }

            $aulasTarde = LessonPlanAfternoonHelper::validateAndBuild($postData);
            $errors = array_merge($errors, $aulasTarde['errors']);
            
            if (!empty($errors)) {
                $this->json(['error' => 'Dados inválidos', 'errors' => $errors], 400);
                return;
            }
            
            // Processa múltiplas datas
            $dataAula = $postData['data_aula'];
            // Se for JSON, decodifica e pega a primeira data (para compatibilidade com o banco)
            $datasArray = json_decode($dataAula, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($datasArray) && !empty($datasArray)) {
                // Armazena todas as datas como JSON no campo data_aula
                $dataAula = json_encode($datasArray);
            }
            
            // Processa objetivos para adicionar <br><br> entre elementos
            $objetivos = $postData['objetivos'] ?? null;
            if (!empty($objetivos)) {
                // Substitui fechamento de parágrafo seguido de abertura por <br><br>
                $objetivos = preg_replace('/<\/p>\s*<p[^>]*>/i', '<br><br>', $objetivos);
                // Substitui fechamento de lista seguido de parágrafo ou outra lista por <br><br>
                $objetivos = preg_replace('/<\/[uo]l>\s*<p[^>]*>/i', '<br><br>', $objetivos);
                $objetivos = preg_replace('/<\/p>\s*<[uo]l[^>]*>/i', '<br><br>', $objetivos);
                // Remove tags <p> restantes no início e fim
                $objetivos = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $objetivos);
                // Remove tags <p> vazias
                $objetivos = preg_replace('/<p[^>]*><\/p>/i', '', $objetivos);
            }
            
            // Prepara dados
            $data = [
                'materia_id' => $postData['materia_id'],
                'turma_id' => $postData['turma_id'],
                'data_aula' => $dataAula,
                'titulo' => $postData['titulo'],
                'ano_disciplina' => $postData['ano_disciplina'] ?? null,
                'modulo' => $postData['modulo'] ?? null,
                'aula_num' => $postData['aula_num'] ?? null,
                'paginas' => $postData['paginas'] ?? null,
                'conteudo' => $postData['conteudo'] ?? null,
                'conteudo_lista' => $postData['conteudo_lista'] ?? null,
                'objetivos' => $objetivos,
                'objetivos_lista' => $postData['objetivos_lista'] ?? null,
                'metodologia' => $postData['metodologia'] ?? null,
                'periodo_tarde_tema' => $postData['periodo_tarde_tema'] ?? null,
                'periodo_tarde_exercicios' => $postData['periodo_tarde_exercicios'] ?? null,
                'recursos' => $postData['recursos'] ?? null,
                'recursos_lista' => isset($postData['recursos_lista']) ? $postData['recursos_lista'] : null,
                'aulas_tarde_oficinas' => $aulasTarde['value'],
                'avaliacao' => $postData['avaliacao'] ?? null,
                'avaliacao_apostila' => $postData['avaliacao_apostila'] ?? null,
                'avaliacao_conteudo' => $postData['avaliacao_conteudo'] ?? null,
                'avaliacao_paginas' => $postData['avaliacao_paginas'] ?? null,
                'observacoes' => $postData['observacoes'] ?? null,
                'status' => $postData['status'] ?? 'rascunho'
            ];
            
            $this->planoAulaModel->update($id, $data);
            
            $this->json([
                'success' => true,
                'message' => 'Plano de aula atualizado com sucesso'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao atualizar plano de aula: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Duplica um plano de aula do professor criando uma nova cópia em rascunho.
     */
    public function duplicar($id)
    {
        $user = $this->auth->getUser();

        if (($user['tipo'] ?? '') !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        try {
            $plano = $this->planoAulaModel->findById($id);
            if (!$plano) {
                throw new Exception('Plano de aula não encontrado.');
            }

            if ((int) ($plano['professor_id'] ?? 0) !== (int) ($user['id'] ?? 0)) {
                throw new Exception('Você não tem permissão para duplicar este plano.');
            }

            $tituloOriginal = trim((string) ($plano['titulo'] ?? 'Plano de Aula'));
            $novoTitulo = $tituloOriginal;
            if (mb_stripos($tituloOriginal, '(cópia)') === false) {
                $novoTitulo .= ' (Cópia)';
            }

            $novoPlanoId = $this->planoAulaModel->create([
                'professor_id' => (int) ($plano['professor_id'] ?? 0),
                'materia_id' => (int) ($plano['materia_id'] ?? 0),
                'turma_id' => (int) ($plano['turma_id'] ?? 0),
                'data_aula' => $plano['data_aula'] ?? null,
                'titulo' => $novoTitulo,
                'ano_disciplina' => $plano['ano_disciplina'] ?? null,
                'modulo' => $plano['modulo'] ?? null,
                'aula_num' => $plano['aula_num'] ?? null,
                'paginas' => $plano['paginas'] ?? null,
                'conteudo' => $plano['conteudo'] ?? null,
                'conteudo_lista' => $plano['conteudo_lista'] ?? null,
                'objetivos' => $plano['objetivos'] ?? null,
                'objetivos_lista' => $plano['objetivos_lista'] ?? null,
                'metodologia' => $plano['metodologia'] ?? null,
                'periodo_tarde_tema' => $plano['periodo_tarde_tema'] ?? null,
                'periodo_tarde_exercicios' => $plano['periodo_tarde_exercicios'] ?? null,
                'recursos' => $plano['recursos'] ?? null,
                'recursos_lista' => !empty($plano['recursos_lista']) ? json_decode((string) $plano['recursos_lista'], true) : null,
                'aulas_tarde_oficinas' => $plano['aulas_tarde_oficinas'] ?? null,
                'avaliacao' => $plano['avaliacao'] ?? null,
                'avaliacao_apostila' => $plano['avaliacao_apostila'] ?? null,
                'avaliacao_conteudo' => $plano['avaliacao_conteudo'] ?? null,
                'avaliacao_paginas' => $plano['avaliacao_paginas'] ?? null,
                'observacoes' => $plano['observacoes'] ?? null,
                'status' => 'rascunho',
            ]);

            $this->json([
                'success' => true,
                'message' => 'Plano de aula duplicado com sucesso.',
                'id' => $novoPlanoId,
                'redirect' => URL . '/professor/planos-aula/editar/' . $novoPlanoId,
            ]);
        } catch (Exception $e) {
            error_log("Erro ao duplicar plano de aula: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Exclui plano de aula (soft delete)
     */
    public function excluir($id)
    {
        $user = $this->auth->getUser();
        
        if ($user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        try {
            $plano = $this->planoAulaModel->findById($id);
            if (!$plano) {
                throw new Exception('Plano de aula não encontrado');
            }
            
            // Verifica se é o dono
            if ($plano['professor_id'] != $user['id']) {
                throw new Exception('Você não tem permissão para excluir este plano');
            }
            
            $this->planoAulaModel->delete($id);
            
            $this->json([
                'success' => true,
                'message' => 'Plano de aula excluído com sucesso'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao excluir plano de aula: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * ============================================
     * ROTAS PARA ADMINISTRAÇÃO
     * ============================================
     */
    
    /**
     * Lista todos os planos de aula (admin)
     */
    public function indexAdmin()
    {
        $user = $this->auth->getUser();
        
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirect('/teacher/planos-aula');
            return;
        }
        
        // Filtros e paginação
        $filtro_status = $_GET['status'] ?? '';
        $filtro_professor = $_GET['professor_id'] ?? '';
        $filtro_turma = $_GET['turma_id'] ?? '';
        $filtro_materia = $_GET['materia_id'] ?? '';
        $filtro_tipo_ensino = $_GET['tipo_ensino'] ?? '';
        $perPage = (int)($_GET['per_page'] ?? 10);
        $page = (int)($_GET['page'] ?? 1);

        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        if ($page < 1) {
            $page = 1;
        }

        $where = ["pa.deleted_at IS NULL"];
        $params = [];

        if (!empty($filtro_status)) {
            $where[] = "pa.status = :status";
            $params['status'] = $filtro_status;
        }
        if (!empty($filtro_professor)) {
            $where[] = "pa.professor_id = :professor_id";
            $params['professor_id'] = $filtro_professor;
        }
        if (!empty($filtro_turma)) {
            $where[] = "pa.turma_id = :turma_id";
            $params['turma_id'] = $filtro_turma;
        }
        if (!empty($filtro_materia)) {
            $where[] = "pa.materia_id = :materia_id";
            $params['materia_id'] = $filtro_materia;
        }
        if (!empty($filtro_tipo_ensino)) {
            $where[] = "t.tipo_ensino = :tipo_ensino";
            $params['tipo_ensino'] = $filtro_tipo_ensino;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $total = $this->db->fetch(
            "SELECT COUNT(*) as total
             FROM planos_aula pa
             LEFT JOIN turmas t ON pa.turma_id = t.id
             {$whereSql}",
            $params
        )['total'] ?? 0;

        $offset = ($page - 1) * $perPage;
        $planos = $this->db->fetchAll(
            "SELECT pa.*,
                    p.nome as professor_nome,
                    m.nome as materia_nome,
                    t.nome as turma_nome
             FROM planos_aula pa
             LEFT JOIN professores p ON pa.professor_id = p.id
             LEFT JOIN materias m ON pa.materia_id = m.id
             LEFT JOIN turmas t ON pa.turma_id = t.id
             {$whereSql}
             ORDER BY pa.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        
        // Estatísticas
        $stats = [
            'total' => $this->planoAulaModel->count(),
            'rascunho' => $this->planoAulaModel->countByStatus('rascunho'),
            'aprovado' => $this->planoAulaModel->countByStatus('aprovado'),
            'rejeitado' => $this->planoAulaModel->countByStatus('rejeitado')
        ];
        
        // Busca professores e turmas para filtros
        $professores = $this->teacherModel->getActive();
        $turmas = $this->turmaModel->getActive();
        $materias = $this->subjectModel->getAll();
        $tiposEnsino = $this->db->fetchAll(
            "SELECT DISTINCT tipo_ensino FROM turmas WHERE tipo_ensino IS NOT NULL AND tipo_ensino != '' ORDER BY tipo_ensino ASC"
        );
        
        $data = [
            'title' => 'Planos de Aula - Administração - EducaTudo',
            'user' => $user,
            'planos' => $planos,
            'stats' => $stats,
            'professores' => $professores,
            'turmas' => $turmas,
            'filtro_status' => $filtro_status,
            'filtro_professor' => $filtro_professor,
            'filtro_turma' => $filtro_turma,
            'filtro_materia' => $filtro_materia,
            'filtro_tipo_ensino' => $filtro_tipo_ensino,
            'materias' => $materias,
            'tipos_ensino' => $tiposEnsino,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => max(1, (int) ceil($total / max(1, $perPage))),
                'total_items' => (int) $total,
                'per_page' => $perPage,
                'has_prev' => $page > 1,
                'has_next' => $page < max(1, (int) ceil($total / max(1, $perPage))),
                'prev_page' => max(1, $page - 1),
                'next_page' => $page + 1
            ],
            'current_page' => 'planos-aula'
        ];
        
        $this->viewWithLayout('admin', 'admin/planos-aula/index', $data);
    }
    
    /**
     * Aprova ou rejeita plano de aula
     */
    public function aprovarRejeitar($id)
    {
        $user = $this->auth->getUser();
        
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        try {
            $acao = $_POST['acao'] ?? '';
            
            if (!in_array($acao, ['aprovar', 'rejeitar'])) {
                throw new Exception('Ação inválida');
            }
            
            $status = $acao === 'aprovar' ? 'aprovado' : 'rejeitado';
            
            $this->planoAulaModel->updateStatus($id, $status);
            
            $this->json([
                'success' => true,
                'message' => $acao === 'aprovar' ? 'Plano aprovado com sucesso' : 'Plano rejeitado'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao aprovar/rejeitar plano: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Exporta plano de aula para PDF
     */
    public function exportarPdf($id)
    {
        $user = $this->auth->getUser();
        
        $plano = $this->planoAulaModel->findById($id);
        if (!$plano) {
            $this->setFlashMessage('Plano de aula não encontrado', 'error');
            if (in_array($user['tipo'], ['admin', 'admin_escola'])) {
                $this->redirect('/admin/planos-aula');
            } else {
                $this->redirect('/teacher/planos-aula');
            }
            return;
        }
        
        // Decodifica recursos_lista se existir
        if (!empty($plano['recursos_lista'])) {
            $plano['recursos_lista'] = json_decode($plano['recursos_lista'], true);
        }
        
        // Limpar HTML dos campos de texto
        $camposTexto = ['conteudo', 'conteudo_lista', 'objetivos', 'objetivos_lista', 'recursos', 'avaliacao', 'observacoes'];
        foreach ($camposTexto as $campo) {
            if (!empty($plano[$campo])) {
                // Remove HTML e decodifica entidades HTML
                $plano[$campo] = html_entity_decode(strip_tags($plano[$campo]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // Remove múltiplos espaços e quebras de linha extras
                $plano[$campo] = preg_replace('/\s+/', ' ', $plano[$campo]);
                $plano[$campo] = trim($plano[$campo]);
            }
        }
        
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        
        $data = [
            'title' => 'Plano de Aula - ' . $plano['titulo'],
            'plano' => $plano,
            'logo_url' => LayoutHelper::getLogoUrl(),
            'logo_horizontal_url' => LayoutHelper::getLogoHorizontalUrl(),
            'system_title' => LayoutHelper::getSystemTitle()
        ];
        
        // Renderizar view HTML (não PDF real)
        $this->view('teacher/planos-aula/pdf', $data);
    }
    
    /**
     * Redireciona para o dashboard correto baseado no tipo
     */
    private function redirectToCorrectDashboard($tipo)
    {
        switch ($tipo) {
            case 'aluno':
                $this->redirect('/dashboard');
                break;
            case 'professor':
                $this->redirect('/teacher/dashboard');
                break;
            case 'admin_escola':
            case 'admin':
                $this->redirect('/admin/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/');
        }
    }
}
}
