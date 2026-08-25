<?php
/**
 * EducaTudo - Controller de Turmas
 * Gerencia operações CRUD para turmas
 */

require_once __DIR__ . '/../../Models/Education/ClassRoom.php';
require_once __DIR__ . '/../../Models/Education/MatrizCurricular.php';
require_once __DIR__ . '/../../Models/Education/Sala.php';
require_once __DIR__ . '/../../Helpers/AdminPasswordHelper.php';

if (!class_exists('ClassController')) {
class ClassController extends BaseController
{
    private $auth;
    private $db;
    private $turmaModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $this->turmaModel = new ClassRoom();
        
        // Verifica se é admin (o middleware Auth já verificou se está logado)
        $user = $this->auth->getUser();
        if ($user && $user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
    }
    
    /**
     * Lista todas as turmas
     */
    public function index()
    {
        $user = $this->auth->getUser();

        $perPage = 10;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $totalTurmasGeral = $this->turmaModel->countAll();
        $turmas = $this->turmaModel->getAll($perPage, $offset);

        // Adicionar contagem de alunos para cada turma
        $vagaSvc = null;
        $vagaPath = dirname(__DIR__, 2) . '/Modulos/matricula/Services/MatriculaVagaService.php';
        if (is_file($vagaPath)) {
            require_once $vagaPath;
            $vagaSvc = new \App\Modulos\Matricula\Services\MatriculaVagaService($this->db);
        }
        foreach ($turmas as &$turma) {
            $turma['total_alunos'] = $this->turmaModel->countStudents($turma['id']);
            $turma['vagas_resumo'] = $vagaSvc ? $vagaSvc->resumo((int) $turma['id']) : null;
        }

        // Estatísticas do total geral (não só da página atual) — soma via o
        // mesmo resolver usado por turma, evita assumir o schema de matrícula.
        $statsRow = $this->db->fetch(
            "SELECT COUNT(*) AS total_turmas, COUNT(CASE WHEN ativo = 1 THEN 1 END) AS turmas_ativas FROM turmas"
        );
        $todasTurmasIds = $this->db->fetchAll("SELECT id FROM turmas");
        $totalAlunosGeral = 0;
        foreach ($todasTurmasIds as $t) {
            $totalAlunosGeral += $this->turmaModel->countStudents((int)$t['id']);
        }
        $statsTurmas = [
            'total_turmas' => (int)($statsRow['total_turmas'] ?? 0),
            'turmas_ativas' => (int)($statsRow['turmas_ativas'] ?? 0),
            'total_alunos' => $totalAlunosGeral,
            'media_alunos' => $totalTurmasGeral > 0 ? round($totalAlunosGeral / $totalTurmasGeral, 1) : 0,
        ];

        $pagination = [
            'total' => $totalTurmasGeral,
            'per_page' => $perPage,
            'page' => $page,
            'total_pages' => $perPage > 0 ? (int)ceil($totalTurmasGeral / $perPage) : 1,
        ];

        $cursosNovo = $this->turmaModel->supportsCursoNovo() ? $this->turmaModel->getCursosNovo() : [];
        $seriesNovo = $this->turmaModel->getSeriesNovo();
        $seriesPorCurso = [];
        foreach ($seriesNovo as $s) {
            $cid = (int)$s['curso_id'];
            if (!isset($seriesPorCurso[$cid])) {
                $seriesPorCurso[$cid] = [];
            }
            $seriesPorCurso[$cid][] = $s;
        }
        $anoAtual = (int)date('Y');
        $anoLetivoId = $this->turmaModel->getAnoLetivoIdByAno($anoAtual);
        $matrizesEmUso = [];
        if ($this->db->fetch("SHOW COLUMNS FROM turmas LIKE 'matriz_curricular_id'") !== false) {
            $matrizesEmUso = array_column(
                $this->db->fetchAll("SELECT DISTINCT matriz_curricular_id FROM turmas WHERE matriz_curricular_id IS NOT NULL"),
                'matriz_curricular_id'
            );
        }

        $data = [
            'title' => 'Gerenciar Turmas - EducaTudo',
            'user' => $user,
            'current_page' => 'turmas',
            'turmas' => $turmas,
            'pagination' => $pagination,
            'stats_turmas' => $statsTurmas,
            'csrf_token' => $this->generateCsrfToken(),
            'series' => $this->getSeries(),
            'cursos' => $this->getCursos(),
            'cursosNovo' => $cursosNovo,
            'seriesPorCurso' => $seriesPorCurso,
            'matrizesPorSerie' => $this->matrizesPorSerie($matrizesEmUso),
            'anosLetivo' => $this->turmaModel->getAnosLetivoNovo(),
            'salas' => (new Sala())->getAll(true),
            'ano_letivo_id' => $anoLetivoId,
        ];

        $this->viewWithLayout('admin', 'admin/turmas/index', $data);
    }

    /**
     * Dados de uma turma (JSON) para popular o offcanvas de edição
     */
    public function dados($id)
    {
        $turma = $this->turmaModel->findById($id);
        if (!$turma) {
            $this->json(['error' => 'Turma não encontrada'], 404);
            return;
        }

        $this->json([
            'success' => true,
            'item' => [
                'id' => (int) $turma['id'],
                'nome' => $turma['nome'],
                'curso_id' => $turma['curso_id'] ?? null,
                'serie' => $turma['serie'] ?? '',
                'curso_novo_id' => $turma['curso_novo_id'] ?? null,
                'serie_id' => $turma['serie_id'] ?? null,
                'matriz_curricular_id' => $turma['matriz_curricular_id'] ?? null,
                'ano_letivo_id' => $turma['ano_letivo_id'] ?? null,
                'turno' => $turma['turno'] ?? '',
                'sala_padrao_id' => $turma['sala_padrao_id'] ?? null,
                'observacoes' => $turma['observacoes'] ?? '',
                'vagas' => $turma['vagas'] ?? null,
                'ativo' => (int) ($turma['ativo'] ?? 0),
            ],
        ]);
    }

    /**
     * Salva nova turma
     */
    public function store()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        try {
            $nome = trim($_POST['nome'] ?? '');
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $cursoNovoIdRaw = $_POST['curso_novo_id'] ?? null;
            $cursoNovoId = ($cursoNovoIdRaw !== null && $cursoNovoIdRaw !== '') ? (int)$cursoNovoIdRaw : null;
            $serieIdRaw = $_POST['serie_id'] ?? null;
            $serieId = ($serieIdRaw !== null && $serieIdRaw !== '') ? (int)$serieIdRaw : null;
            [$anoLetivo, $anoLetivoId] = $this->resolverAnoLetivo($_POST['ano_letivo_id'] ?? null);
            $matrizCurricularIdRaw = $_POST['matriz_curricular_id'] ?? null;
            $matrizCurricularId = ($matrizCurricularIdRaw !== null && $matrizCurricularIdRaw !== '') ? (int)$matrizCurricularIdRaw : null;
            $turno = trim($_POST['turno'] ?? '');
            $salaPadraoId = $this->validarSalaPadrao($_POST['sala_padrao_id'] ?? null);
            $observacoes = trim($_POST['observacoes'] ?? '');

            // Fluxo nova estrutura (tabela curso): curso_novo_id preenchido
            if ($cursoNovoId > 0 && $this->turmaModel->supportsCursoNovo()) {
                $cursosNovo = $this->turmaModel->getCursosNovo();
                $cursoEncontrado = null;
                foreach ($cursosNovo as $c) {
                    if ((int)$c['id'] === $cursoNovoId) {
                        $cursoEncontrado = $c;
                        break;
                    }
                }
                if (!$cursoEncontrado) {
                    throw new Exception('Curso inválido');
                }
                if (empty($nome)) {
                    throw new Exception('Nome da turma é obrigatório');
                }
                if ($this->turmaModel->nameExists($nome)) {
                    throw new Exception('Nome da turma já cadastrado');
                }
                $this->validarMatrizDaSerie($matrizCurricularId, $serieId);
                $this->turmaModel->create([
                    'nome' => $nome,
                    'ano_letivo' => $anoLetivo,
                    'serie' => '',
                    'ativo' => $ativo,
                    'curso_novo_id' => $cursoNovoId,
                    'serie_id' => $serieId,
                    'ano_letivo_id' => $anoLetivoId,
                    'matriz_curricular_id' => $matrizCurricularId,
                    'turno' => $turno,
                    'sala_padrao_id' => $salaPadraoId,
                    'observacoes' => $observacoes,
                    'vagas' => $this->vagasDoPost(),
                ]);
                $this->json(['success' => true, 'message' => 'Turma cadastrada com sucesso']);
                return;
            }

            // Fluxo legado: curso_id (cursos/tipos_curso)
            $cursoIdRaw = $_POST['curso_id'] ?? null;
            $cursoId = ($cursoIdRaw !== null && $cursoIdRaw !== '') ? (int)$cursoIdRaw : null;
            $serie = trim($_POST['serie'] ?? '');

            if ($cursoId !== null) {
                $curso = $this->turmaModel->findCursoById($cursoId);
                if (!$curso) {
                    throw new Exception('Curso inválido');
                }
                $serie = (string)($curso['nome'] ?? '');
            }

            if (empty($nome)) {
                throw new Exception('Nome da turma é obrigatório');
            }
            if (empty($serie)) {
                throw new Exception('Curso/Série é obrigatório');
            }
            if ($this->turmaModel->nameExists($nome)) {
                throw new Exception('Nome da turma já cadastrado');
            }

            $this->turmaModel->create([
                'nome' => $nome,
                'ano_letivo' => $anoLetivo,
                'serie' => $serie,
                'curso_id' => $cursoId,
                'ativo' => $ativo,
                'turno' => $turno,
                'sala_padrao_id' => $salaPadraoId,
                'observacoes' => $observacoes,
                'vagas' => $this->vagasDoPost(),
            ]);

            $this->json(['success' => true, 'message' => 'Turma cadastrada com sucesso']);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Atualiza turma
     */
    public function update($id)
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $nome = trim($_POST['nome'] ?? '');
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $cursoNovoIdRaw = $_POST['curso_novo_id'] ?? null;
            $cursoNovoId = ($cursoNovoIdRaw !== null && $cursoNovoIdRaw !== '') ? (int)$cursoNovoIdRaw : null;
            $serieIdRaw = $_POST['serie_id'] ?? null;
            $serieId = ($serieIdRaw !== null && $serieIdRaw !== '') ? (int)$serieIdRaw : null;
            [$anoLetivo, $anoLetivoId] = $this->resolverAnoLetivo($_POST['ano_letivo_id'] ?? null);
            $matrizCurricularIdRaw = $_POST['matriz_curricular_id'] ?? null;
            $matrizCurricularId = ($matrizCurricularIdRaw !== null && $matrizCurricularIdRaw !== '') ? (int)$matrizCurricularIdRaw : null;
            $turno = trim($_POST['turno'] ?? '');
            $salaPadraoId = $this->validarSalaPadrao($_POST['sala_padrao_id'] ?? null);
            $observacoes = trim($_POST['observacoes'] ?? '');

            if (!$this->turmaModel->exists($id)) {
                throw new Exception('Turma não encontrada');
            }
            if ($this->turmaModel->nameExists($nome, $id)) {
                throw new Exception('Nome da turma já cadastrado');
            }

            // Fluxo nova estrutura (tabela curso)
            if ($cursoNovoId > 0 && $this->turmaModel->supportsCursoNovo()) {
                $cursosNovo = $this->turmaModel->getCursosNovo();
                $cursoEncontrado = null;
                foreach ($cursosNovo as $c) {
                    if ((int)$c['id'] === $cursoNovoId) {
                        $cursoEncontrado = $c;
                        break;
                    }
                }
                if (!$cursoEncontrado) {
                    throw new Exception('Curso inválido');
                }
                if (empty($nome)) {
                    throw new Exception('Nome da turma é obrigatório');
                }
                $this->validarMatrizDaSerie($matrizCurricularId, $serieId);
                $this->turmaModel->update($id, [
                    'nome' => $nome,
                    'ano_letivo' => $anoLetivo,
                    'serie' => '',
                    'ativo' => $ativo,
                    'curso_novo_id' => $cursoNovoId,
                    'serie_id' => $serieId,
                    'ano_letivo_id' => $anoLetivoId,
                    'matriz_curricular_id' => $matrizCurricularId,
                    'turno' => $turno,
                    'sala_padrao_id' => $salaPadraoId,
                    'observacoes' => $observacoes,
                    'vagas' => $this->vagasDoPost(),
                ]);
                $this->json(['success' => true, 'message' => 'Turma atualizada com sucesso']);
                return;
            }

            // Fluxo legado
            $cursoIdRaw = $_POST['curso_id'] ?? null;
            $cursoId = ($cursoIdRaw !== null && $cursoIdRaw !== '') ? (int)$cursoIdRaw : null;
            $serie = trim($_POST['serie'] ?? '');

            if ($cursoId !== null) {
                $curso = $this->turmaModel->findCursoById($cursoId);
                if (!$curso) {
                    throw new Exception('Curso inválido');
                }
                $serie = (string)($curso['nome'] ?? '');
            }
            if (empty($nome)) {
                throw new Exception('Nome da turma é obrigatório');
            }
            if (empty($serie)) {
                throw new Exception('Curso/Série é obrigatório');
            }

            $this->turmaModel->update($id, [
                'nome' => $nome,
                'ano_letivo' => $anoLetivo,
                'serie' => $serie,
                'curso_id' => $cursoId,
                'ativo' => $ativo,
                'turno' => $turno,
                'sala_padrao_id' => $salaPadraoId,
                'observacoes' => $observacoes,
                'vagas' => $this->vagasDoPost(),
            ]);

            $this->json(['success' => true, 'message' => 'Turma atualizada com sucesso']);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Retorna o token CSRF da requisição (POST ou body em DELETE/PUT).
     */
    private function getRequestCsrfToken()
    {
        $params = $this->getRequestBodyParams();
        return $params['_token'] ?? '';
    }

    private function vagasDoPost(): ?int
    {
        $raw = trim((string) ($_POST['vagas'] ?? ''));
        if ($raw === '') {
            return null;
        }
        $n = (int) $raw;
        return $n > 0 ? $n : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestBodyParams(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }
        if (in_array($_SERVER['REQUEST_METHOD'] ?? '', ['DELETE', 'PUT', 'PATCH'], true)) {
            $input = file_get_contents('php://input');
            if ($input !== false && $input !== '') {
                parse_str($input, $params);
                return is_array($params) ? $params : [];
            }
        }
        return [];
    }

    private function verifyDeletePassword(): ?array
    {
        $user = $this->auth->getUser();
        $params = $this->getRequestBodyParams();
        $senha = trim((string) ($params['senha'] ?? ''));
        if ($senha === '') {
            return ['error' => 'Digite sua senha para confirmar.', 'status' => 400];
        }
        if (!AdminPasswordHelper::verifyAdminPassword($this->db, $user, $senha)) {
            return ['error' => 'Senha incorreta.', 'status' => 400];
        }
        return null;
    }

    /**
     * @return array{deleted: bool, error: string}
     */
    private function deleteTurmaById(int $id): array
    {
        if (!$this->turmaModel->exists($id)) {
            return ['deleted' => false, 'error' => 'Turma #' . $id . ' não encontrada.'];
        }

        $turma = $this->turmaModel->findById($id);
        $nome = (string) ($turma['nome'] ?? ('#' . $id));

        if ($this->turmaModel->hasStudents($id)) {
            return ['deleted' => false, 'error' => '"' . $nome . '": possui alunos vinculados.'];
        }

        $this->turmaModel->delete($id);

        return ['deleted' => true, 'error' => ''];
    }

    /**
     * Exclui turma
     */
    public function destroy($id)
    {
        if (!$this->verifyCsrfToken($this->getRequestCsrfToken())) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        $passwordError = $this->verifyDeletePassword();
        if ($passwordError !== null) {
            $this->json(['error' => $passwordError['error']], $passwordError['status']);
            return;
        }

        try {
            $result = $this->deleteTurmaById((int) $id);
            if (!$result['deleted']) {
                throw new Exception($result['error']);
            }

            $this->json(['success' => true, 'message' => 'Turma excluída com sucesso']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function bulkDestroy()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        $passwordError = $this->verifyDeletePassword();
        if ($passwordError !== null) {
            $this->json(['error' => $passwordError['error']], $passwordError['status']);
            return;
        }

        $ids = AdminPasswordHelper::parseIds($_POST['turma_ids'] ?? []);
        if ($ids === []) {
            $this->json(['error' => 'Selecione ao menos uma turma.'], 400);
            return;
        }

        $deleted = 0;
        $errors = [];
        foreach ($ids as $id) {
            $result = $this->deleteTurmaById($id);
            if ($result['deleted']) {
                $deleted++;
            } else {
                $errors[] = $result['error'];
            }
        }

        if ($deleted === 0) {
            $this->json(['error' => implode(' ', $errors)], 400);
            return;
        }

        $message = $deleted . ' turma(s) excluída(s).';
        if ($errors !== []) {
            $message .= ' ' . count($errors) . ' não excluída(s): ' . implode(' ', array_slice($errors, 0, 3));
        }

        $this->json(['success' => true, 'message' => $message, 'deleted' => $deleted, 'errors' => $errors]);
    }
    
    /**
     * Alterna status ativo/inativo
     */
    public function toggleStatus($id)
    {
        if (!$this->verifyCsrfToken($this->getRequestCsrfToken())) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            // Verifica se turma existe
            if (!$this->turmaModel->exists($id)) {
                throw new Exception('Turma não encontrada');
            }
            
            // Alterna status
            $this->turmaModel->toggleStatus($id);
            
            $turma = $this->turmaModel->findById($id);
            $status = $turma['ativo'] ? 'ativada' : 'desativada';
            
            $this->json(['success' => true, 'message' => "Turma {$status} com sucesso"]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Exibe detalhes da turma
     */
    public function show($id)
    {
        $user = $this->auth->getUser();
        $turmaId = (int) $id;
        
        $turma = $this->turmaModel->findById($turmaId);
        
        if (!$turma) {
            $this->redirect('/admin/turmas');
        }

        $cursoInfo = null;
        try {
            $hasCursoNovo = $this->db->fetch("SHOW COLUMNS FROM turmas LIKE 'curso_novo_id'");
            if ($hasCursoNovo !== false && !empty($turma['curso_novo_id'])) {
                $cursoInfo = $this->db->fetch(
                    'SELECT id, nome, tipo FROM curso WHERE id = :id',
                    ['id' => (int) $turma['curso_novo_id']]
                );
            }
        } catch (Exception $e) {
            $cursoInfo = null;
        }
        if ($cursoInfo) {
            $turma['curso_nome'] = $cursoInfo['nome'] ?? '';
            $turma['curso_tipo'] = $cursoInfo['tipo'] ?? 'regular';
            $turma['curso_id'] = (int) ($cursoInfo['id'] ?? 0);
        }

        $hasMatricula = $this->turmaModel->supportsMatricula();
        require_once __DIR__ . '/../../Services/AlunoTurmaResolver.php';
        $turmaResolver = new \App\Services\AlunoTurmaResolver();
        $alunos = $turmaResolver->listarAlunosPorTurma($turmaId);
        
        $turma['total_alunos'] = $turmaResolver->contarAlunosPorTurma($turmaId);

        $vagasResumo = null;
        $filaEspera = [];
        try {
            $vagaPath = dirname(__DIR__, 2) . '/Modulos/matricula/Services/MatriculaVagaService.php';
            if (is_file($vagaPath)) {
                require_once $vagaPath;
                $vagaSvc = new \App\Modulos\Matricula\Services\MatriculaVagaService($this->db);
                $vagasResumo = $vagaSvc->resumo($turmaId);
                $filaEspera = $vagaSvc->listarFila($turmaId);
            }
        } catch (Exception $e) {
            $vagasResumo = null;
        }

        $anos_letivo_para_vinculo = [];
        try {
            $hasAnoLetivo = $this->db->fetch("SHOW TABLES LIKE 'ano_letivo'");
            if ($hasAnoLetivo !== false) {
                $anos_letivo_para_vinculo = $this->db->fetchAll(
                    'SELECT id, ano FROM ano_letivo WHERE ativo = 1 ORDER BY ano DESC'
                ) ?: [];
            }
        } catch (Exception $e) {
            $anos_letivo_para_vinculo = [];
        }
        
        $data = [
            'title' => 'Detalhes da Turma - EducaTudo',
            'user' => $user,
            'current_page' => 'turmas',
            'turma' => $turma,
            'alunos' => $alunos,
            'matriculas_schema_ready' => $hasMatricula,
            'anos_letivo_para_vinculo' => $anos_letivo_para_vinculo,
            'vagas_resumo' => $vagasResumo,
            'fila_espera' => $filaEspera,
            'csrf_token' => $this->generateCsrfToken()
        ];
        
        $this->viewWithLayout('admin', 'admin/turmas/show', $data);
    }

    /**
     * Busca alunos por nome, RA, login ou e-mail (AJAX) para vincular à turma.
     */
    public function buscarAlunosParaVincular($id)
    {
        try {
            $turmaId = (int) $id;
            $q = trim((string) ($_GET['q'] ?? ''));
            if ($turmaId <= 0) {
                $this->json(['success' => false, 'error' => 'Turma inválida'], 400);
                return;
            }
            if (!$this->turmaModel->exists($turmaId)) {
                $this->json(['success' => false, 'error' => 'Turma não encontrada'], 404);
                return;
            }
            if (mb_strlen($q) < 1) {
                $this->json(['success' => true, 'alunos' => []]);
                return;
            }

            $alunos = $this->searchAlunosParaVinculo($q, $turmaId);
            $this->json(['success' => true, 'alunos' => $alunos]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => 'Erro ao buscar alunos: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchAlunosParaVinculo(string $q, int $turmaId, int $limit = 30): array
    {
        $searchFields = $this->getAlunoSearchableColumns();
        $terms = array_values(array_filter(
            preg_split('/\s+/u', $q) ?: [],
            static fn (string $term): bool => mb_strlen(trim($term)) >= 1
        ));
        if ($terms === []) {
            return [];
        }

        $params = [
            'turma_id_mat' => $turmaId,
            'turma_id_principal' => $turmaId,
        ];
        $termGroups = [];
        foreach ($terms as $i => $term) {
            $term = trim($term);
            $orParts = [];
            foreach ($searchFields as $field) {
                $paramKey = 'term_' . $i . '_' . $field;
                $orParts[] = 'a.' . $field . ' LIKE :' . $paramKey;
                $params[$paramKey] = '%' . $term . '%';
            }
            if ($orParts === []) {
                continue;
            }
            $termGroups[] = '(' . implode(' OR ', $orParts) . ')';
        }
        if ($termGroups === []) {
            return [];
        }

        $selectExtras = [];
        foreach (['ra', 'codigo_aluno', 'nickname', 'email'] as $field) {
            if (in_array($field, $searchFields, true)) {
                $selectExtras[] = 'a.' . $field;
            }
        }
        $selectList = 'a.id, a.nome, a.ativo, t.nome AS turma_nome';
        if ($selectExtras !== []) {
            $selectList .= ', ' . implode(', ', $selectExtras);
        }

        $statusFilter = '';
        try {
            $hasStatus = $this->db->fetch("SHOW COLUMNS FROM alunos LIKE 'status'");
            if ($hasStatus !== false) {
                $statusFilter = " AND (a.status IS NULL OR a.status IN ('ACTIVE', 'PENDING'))";
            }
        } catch (Exception $e) {
            $statusFilter = '';
        }

        $jaVinculadoSql = $this->turmaModel->supportsMatricula()
            ? "(EXISTS (
                    SELECT 1 FROM matricula m
                    WHERE m.aluno_id = a.id
                      AND m.turma_id = :turma_id_mat
                      AND m.status = 'ativa'
                      AND m.data_saida IS NULL
                ) OR a.turma_id = :turma_id_principal)"
            : '(a.turma_id = :turma_id_principal)';

        $sql = "SELECT {$selectList},
                       {$jaVinculadoSql} AS ja_vinculado
                FROM alunos a
                LEFT JOIN turmas t ON t.id = a.turma_id
                WHERE " . implode(' AND ', $termGroups) . $statusFilter . "
                ORDER BY a.ativo DESC, a.nome ASC
                LIMIT " . max(1, min(50, $limit));

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    /**
     * @return list<string>
     */
    private function getAlunoSearchableColumns(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $available = ['nome' => true];
        try {
            $cols = $this->db->fetchAll('SHOW COLUMNS FROM alunos') ?: [];
            foreach ($cols as $col) {
                $field = (string) ($col['Field'] ?? '');
                if ($field !== '') {
                    $available[$field] = true;
                }
            }
        } catch (Exception $e) {
            $available = array_fill_keys(['nome', 'ra', 'codigo_aluno', 'nickname', 'email', 'cpf', 'status'], true);
        }

        $searchFields = ['nome'];
        foreach (['nickname', 'email', 'ra', 'codigo_aluno', 'cpf'] as $field) {
            if (!empty($available[$field])) {
                $searchFields[] = $field;
            }
        }

        $cache = $searchFields;
        return $searchFields;
    }

    /**
     * Vincula aluno existente à turma (matrícula; turma principal opcional).
     */
    public function vincularAluno($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        try {
            $turmaId = (int) $id;
            $alunoId = (int) ($_POST['aluno_id'] ?? 0);
            $anoLetivoId = (int) ($_POST['ano_letivo_id'] ?? 0);
            $dataEntrada = trim($_POST['data_entrada'] ?? '') ?: date('Y-m-d');
            $definirPrincipal = isset($_POST['definir_turma_principal']) && $_POST['definir_turma_principal'] !== '0';

            if ($turmaId <= 0 || $alunoId <= 0 || $anoLetivoId <= 0) {
                throw new Exception('Selecione aluno, ano letivo e confirme a turma.');
            }

            require_once __DIR__ . '/../../Services/AlunoMovimentacaoService.php';
            $movimentacao = new \App\Services\AlunoMovimentacaoService();
            $movimentacao->vincularAlunoTurma($alunoId, $turmaId, $anoLetivoId, $definirPrincipal, $dataEntrada);

            $this->json(['success' => true, 'message' => 'Aluno vinculado à turma com sucesso.']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Busca turmas por ano letivo (AJAX)
     */
    public function getByAnoLetivo()
    {
        $anoLetivo = (int)($_GET['ano_letivo'] ?? 0);
        
        if ($anoLetivo < 2020 || $anoLetivo > 2030) {
            $this->json(['error' => 'Ano letivo inválido'], 400);
        }
        
        $turmas = $this->turmaModel->getByAnoLetivo($anoLetivo);
        
        $this->json(['success' => true, 'turmas' => $turmas]);
    }
    
    /**
     * Busca turmas por série (AJAX)
     */
    public function getBySerie()
    {
        $serie = trim($_GET['serie'] ?? '');
        
        if (empty($serie)) {
            $this->json(['error' => 'Série inválida'], 400);
        }
        
        $turmas = $this->turmaModel->getBySerie($serie);
        
        $this->json(['success' => true, 'turmas' => $turmas]);
    }
    
    /**
     * Obtém anos letivos disponíveis
     */
    private function getAnosLetivos()
    {
        $anos = $this->turmaModel->getAnosLetivos();
        $anosLetivos = [];
        
        foreach ($anos as $ano) {
            $anosLetivos[] = $ano['ano_letivo'];
        }
        
        // Adicionar anos futuros se não existirem
        $anoAtual = date('Y');
        for ($i = $anoAtual; $i <= $anoAtual + 2; $i++) {
            if (!in_array($i, $anosLetivos)) {
                $anosLetivos[] = $i;
            }
        }
        
        sort($anosLetivos);
        return $anosLetivos;
    }
    
    /**
     * Matrizes Curriculares ativas, agrupadas por serie_id — usado pelo form de
     * turma (fluxo "nova estrutura") para popular o dropdown conforme a série
     * selecionada.
     *
     * @param int[]|null $incluirMesmoInativas Ids de matriz atualmente vinculados
     *   a alguma turma — incluídos na lista mesmo se estiverem inativos, senão o
     *   dropdown "perde" a opção selecionada e salvar o form (a turma continua com
     *   o mesmo valor pré-marcado no offcanvas) desvincula a matriz silenciosamente
     *   (matriz inativada depois do vínculo). Desde que o form de turma virou um
     *   único offcanvas reaproveitado por todas as turmas (não mais uma página por
     *   turma), a lista de exceções cobre todas as turmas da página, não uma só.
     */
    private function matrizesPorSerie(?array $incluirMesmoInativas = null): array
    {
        $model = new MatrizCurricular();
        $matrizes = $model->getAll(['ativo' => 1]);

        $idsAtuais = array_map('intval', array_column($matrizes, 'id'));
        foreach ($incluirMesmoInativas ?? [] as $id) {
            $id = (int) $id;
            if ($id > 0 && !in_array($id, $idsAtuais, true)) {
                $atual = $model->findById($id);
                if ($atual) {
                    $atual['inativa'] = true;
                    $matrizes[] = $atual;
                    $idsAtuais[] = $id;
                }
            }
        }

        $porSerie = [];
        foreach ($matrizes as $m) {
            $porSerie[(int) $m['serie_id']][] = $m;
        }
        return $porSerie;
    }

    /**
     * Garante que a Matriz Curricular enviada no form de turma pertence à
     * série selecionada — o dropdown já filtra isso no cliente, mas o
     * servidor precisa validar (POST forjado ou bug de JS não devem gravar
     * uma turma com a matriz de outra série).
     */
    private function validarMatrizDaSerie(?int $matrizCurricularId, ?int $serieId): void
    {
        if (!$matrizCurricularId) {
            return;
        }
        $matriz = (new MatrizCurricular())->findById($matrizCurricularId);
        if (!$matriz) {
            throw new Exception('Matriz Curricular inválida');
        }
        if ((int) $matriz['serie_id'] !== (int) $serieId) {
            throw new Exception('A Matriz Curricular selecionada não pertence à série da turma');
        }
    }

    /**
     * Resolve o ano letivo (int, coluna legada `ano_letivo`) e o
     * `ano_letivo_id` a persistir na turma. Se o form enviou um
     * `ano_letivo_id` (dropdown do fluxo "nova estrutura"), usa o ano civil
     * daquele registro — permite cadastrar turma pra um ano diferente do
     * calendário atual (ex.: preparar turmas do ano seguinte). Sem valor
     * enviado, mantém o comportamento anterior (ano corrente).
     * @return array{0:int,1:?int} [ano_letivo, ano_letivo_id]
     */
    private function resolverAnoLetivo($anoLetivoIdRaw): array
    {
        $anoLetivoIdPost = ($anoLetivoIdRaw !== null && $anoLetivoIdRaw !== '') ? (int) $anoLetivoIdRaw : null;
        if ($anoLetivoIdPost) {
            $ano = $this->turmaModel->getAnoById($anoLetivoIdPost);
            if ($ano) {
                return [$ano, $anoLetivoIdPost];
            }
        }
        $anoAtual = (int) date('Y');
        return [$anoAtual, $this->turmaModel->getAnoLetivoIdByAno($anoAtual)];
    }

    /**
     * Valida que a Sala Padrão enviada existe — retorna o id (ou null se
     * não informada).
     */
    private function validarSalaPadrao($salaPadraoIdRaw): ?int
    {
        $salaPadraoId = ($salaPadraoIdRaw !== null && $salaPadraoIdRaw !== '') ? (int) $salaPadraoIdRaw : null;
        if (!$salaPadraoId) {
            return null;
        }
        if (!(new Sala())->exists($salaPadraoId)) {
            throw new Exception('Sala/Ambiente inválida');
        }
        return $salaPadraoId;
    }

    /**
     * Obtém séries disponíveis
     */
    private function getSeries()
    {
        $series = $this->turmaModel->getSeries();
        $seriesList = [];
        
        foreach ($series as $serie) {
            $seriesList[] = $serie['serie'];
        }
        
        // Adicionar séries padrão se não existirem
        $seriesPadrao = ['1º Ano', '2º Ano', '3º Ano', '4º Ano', '5º Ano', '6º Ano', '7º Ano', '8º Ano', '9º Ano', 'Pré-vestibular'];
        foreach ($seriesPadrao as $serie) {
            if (!in_array($serie, $seriesList)) {
                $seriesList[] = $serie;
            }
        }

        // Se houver estrutura de cursos, adicionar nomes de cursos também.
        $cursos = $this->getCursos();
        foreach ($cursos as $curso) {
            $nomeCurso = trim((string)($curso['nome'] ?? ''));
            if ($nomeCurso !== '' && !in_array($nomeCurso, $seriesList, true)) {
                $seriesList[] = $nomeCurso;
            }
        }
        
        sort($seriesList);
        return $seriesList;
    }

    /**
     * Exporta alunos da turma (nome, RA, vínculo) em CSV.
     */
    public function exportarAlunosCsv($id)
    {
        $turmaId = (int) $id;
        $turma = $this->turmaModel->findById($turmaId);
        if (!$turma) {
            $this->redirect('/admin/turmas');
            return;
        }

        require_once __DIR__ . '/../../Services/AlunoTurmaResolver.php';
        $resolver = new \App\Services\AlunoTurmaResolver();
        $alunos = $resolver->listarAlunosPorTurma($turmaId);

        $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) ($turma['nome'] ?? 'turma'));
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="turma_' . $slug . '_alunos.csv"');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            return;
        }
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['nome', 'ra', 'vinculo_tipo', 'ativo'], ';');
        foreach ($alunos as $a) {
            $vinculo = ($a['vinculo_tipo'] ?? 'principal') === 'principal' ? 'Principal' : 'Matriculado';
            fputcsv($out, [
                (string) ($a['nome'] ?? ''),
                (string) ($a['ra'] ?? ''),
                $vinculo,
                !empty($a['ativo']) ? 'Sim' : 'Não',
            ], ';');
        }
        fclose($out);
        exit;
    }

    /**
     * Obtém cursos disponíveis para o cadastro/edição.
     */
    private function getCursos()
    {
        return $this->turmaModel->getCursos();
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
                $this->redirect('/professor/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/admin');
        }
    }
}
}
