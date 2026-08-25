<?php
/**
 * EducaTudo - Controller da Grade Horária de Aulas
 * Admin: CRUD + importação por imagem (IA)
 */

if (!class_exists('GradeHorariaController')) {
class GradeHorariaController extends BaseController
{
    private $auth;
    private $db;

    private static $DIAS_SEMANA = [
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /**
     * Normaliza texto para comparação: minúsculas, sem acentos, símbolos °ºª tratados, espaços colapsados
     */
    private static function normalizarParaBusca($s)
    {
        $s = (string) $s;
        $s = str_replace(['°', 'º', 'ª'], ' ', $s);
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/\s+/', ' ', $s);
        $map = ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c'];
        foreach ($map as $de => $para) {
            $s = str_replace($de, $para, $s);
        }
        return $s;
    }

    /**
     * Extrai array de itens da grade a partir da resposta da IA (JSON em vários formatos possíveis)
     */
    private static function extrairItensGradeDaResposta($resposta)
    {
        $resposta = trim((string) $resposta);
        if ($resposta === '') return null;

        // Remove marcadores de code block se existirem
        $resposta = preg_replace('/^```(?:json)?\s*/i', '', $resposta);
        $resposta = preg_replace('/\s*```\s*$/i', '', $resposta);
        $resposta = trim($resposta);

        // Tenta decodificar a resposta inteira (pode ser objeto com chave "aulas", "itens", "questoes", etc.)
        $decoded = json_decode($resposta, true);
        if (is_array($decoded)) {
            if (isset($decoded['aulas']) && is_array($decoded['aulas'])) return $decoded['aulas'];
            if (isset($decoded['itens']) && is_array($decoded['itens'])) return $decoded['itens'];
            if (isset($decoded['questoes']) && is_array($decoded['questoes'])) return $decoded['questoes'];
            if (isset($decoded[0]) && is_array($decoded[0])) return $decoded;
        }

        // Extrai o primeiro array JSON [...] por contagem de colchetes
        $start = strpos($resposta, '[');
        if ($start === false) return null;
        $depth = 0;
        $len = strlen($resposta);
        for ($i = $start; $i < $len; $i++) {
            $c = $resposta[$i];
            if ($c === '[') $depth++;
            if ($c === ']') {
                $depth--;
                if ($depth === 0) {
                    $jsonStr = substr($resposta, $start, $i - $start + 1);
                    $jsonStr = preg_replace('/,\s*]/', ']', $jsonStr);
                    $jsonStr = preg_replace('/,\s*}/', '}', $jsonStr);
                    $itens = json_decode($jsonStr, true);
                    return is_array($itens) ? $itens : null;
                }
            }
        }

        // Resposta truncada (sem ] de fechamento): tenta recuperar objetos completos
        if ($depth > 0) {
            $corte = substr($resposta, $start);
            // Última ocorrência de "},\n" ou "}, " (fim de um objeto completo)
            if (preg_match_all('/\}\s*,/s', $corte, $matches, PREG_OFFSET_CAPTURE)) {
                $last = end($matches[0]);
                $pos = $last[1] + strlen($last[0]); // após "},"
                $jsonStr = substr($corte, 0, $pos) . ']';
                $jsonStr = preg_replace('/,\s*]/', ']', $jsonStr);
                $itens = json_decode($jsonStr, true);
                if (is_array($itens) && !empty($itens)) {
                    return $itens;
                }
            }
        }

        // Fallback: regex para pegar conteúdo entre primeiro [ e último ]
        if (preg_match('/\[[\s\S]*\]/s', $resposta, $m)) {
            $jsonStr = preg_replace('/,\s*]/', ']', $m[0]);
            $jsonStr = preg_replace('/,\s*}/', '}', $jsonStr);
            $itens = json_decode($jsonStr, true);
            return is_array($itens) ? $itens : null;
        }

        return null;
    }

    /**
     * Encontra ID no mapa: primeiro por chave exata, depois por chave que contenha o valor ou valor que contenha a chave
     */
    private static function buscarMelhorMatch($valorNormalizado, array $map)
    {
        if ($valorNormalizado === '') return null;
        if (isset($map[$valorNormalizado])) return $map[$valorNormalizado];
        foreach ($map as $chave => $id) {
            if ($chave !== '' && (strpos($chave, $valorNormalizado) !== false || strpos($valorNormalizado, $chave) !== false)) {
                return $id;
            }
        }
        return null;
    }

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();

        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->redirectToCorrectDashboard($user['tipo'] ?? null);
        }
    }

    /**
     * Lista a grade horária
     */
    public function index()
    {
        $user = $this->auth->getUser();
        $filtros = $this->filtrosDaRequest();

        $turmas = $this->db->fetchAll(
            "SELECT id, nome, serie, tipo_ensino FROM turmas WHERE ativo = 1 ORDER BY nome"
        ) ?: [];
        $professores = $this->db->fetchAll("SELECT id, nome FROM professores WHERE ativo = 1 ORDER BY nome") ?: [];
        $materias = $this->db->fetchAll("SELECT id, nome FROM materias ORDER BY nome") ?: [];

        $tiposEnsino = [];
        $series = [];
        foreach ($turmas as $turma) {
            $tipo = trim((string) ($turma['tipo_ensino'] ?? ''));
            $serie = trim((string) ($turma['serie'] ?? ''));
            if ($tipo !== '') {
                $tiposEnsino[$tipo] = $tipo;
            }
            if ($serie !== '') {
                $series[$serie] = $serie;
            }
        }
        ksort($tiposEnsino, SORT_NATURAL | SORT_FLAG_CASE);
        ksort($series, SORT_NATURAL | SORT_FLAG_CASE);

        $turmasFiltro = $turmas;
        if ($filtros['tipo_ensino'] !== '') {
            $turmasFiltro = array_values(array_filter($turmasFiltro, static function ($turma) use ($filtros) {
                return strcasecmp((string) ($turma['tipo_ensino'] ?? ''), $filtros['tipo_ensino']) === 0;
            }));
            $series = [];
            foreach ($turmasFiltro as $turma) {
                $serie = trim((string) ($turma['serie'] ?? ''));
                if ($serie !== '') {
                    $series[$serie] = $serie;
                }
            }
            ksort($series, SORT_NATURAL | SORT_FLAG_CASE);
            if ($filtros['serie'] !== '' && !isset($series[$filtros['serie']])) {
                $filtros['serie'] = '';
            }
        }
        if ($filtros['serie'] !== '') {
            $turmasFiltro = array_values(array_filter($turmasFiltro, static function ($turma) use ($filtros) {
                return strcasecmp((string) ($turma['serie'] ?? ''), $filtros['serie']) === 0;
            }));
        }

        $data = [
            'title' => 'Grade Horária de Aulas - EducaTudo',
            'user' => $user,
            'current_page' => 'grade_horaria',
            'itens' => $this->listarItens($filtros),
            'dias_semana' => self::$DIAS_SEMANA,
            'turmas' => $turmas,
            'turmas_filtro' => $turmasFiltro,
            'tipos_ensino' => array_values($tiposEnsino),
            'series' => array_values($series),
            'professores' => $professores,
            'materias' => $materias,
            'filtros' => $filtros,
            'csrf_token' => $this->generateCsrfToken(),
        ];

        $this->viewWithLayout('admin', 'admin/grade-horaria/index', $data);
    }

    /**
     * PDF da grade na visão/filtros atuais (semana, dia ou lista).
     */
    public function pdf()
    {
        $filtros = $this->filtrosDaRequest();
        $turmas = $this->db->fetchAll(
            "SELECT id, nome, serie, tipo_ensino FROM turmas WHERE ativo = 1 ORDER BY nome"
        ) ?: [];
        $professores = $this->db->fetchAll("SELECT id, nome FROM professores WHERE ativo = 1 ORDER BY nome") ?: [];
        $materias = $this->db->fetchAll("SELECT id, nome FROM materias ORDER BY nome") ?: [];

        $html = $this->renderizarHtml('admin/grade-horaria/pdf', [
            'itens' => $this->listarItens($filtros),
            'filtros' => $filtros,
            'dias_semana' => self::$DIAS_SEMANA,
            'turmas' => $turmas,
            'turmas_filtro' => $turmas,
            'professores' => $professores,
            'materias' => $materias,
            'gerado_em' => date('d/m/Y H:i'),
        ]);

        $orientacao = ($filtros['visao'] === 'dia') ? 'portrait' : 'landscape';
        $this->outputPdf($html, 'grade-horaria.pdf', $orientacao);
    }

    /**
     * Dados de uma aula (JSON) para popular o offcanvas de edição
     */
    public function dados($id)
    {
        $item = $this->db->fetch("SELECT * FROM grade_horaria WHERE id = :id", ['id' => (int) $id]);
        if (!$item) {
            $this->json(['error' => 'Registro não encontrado.'], 404);
            return;
        }

        $this->json([
            'success' => true,
            'item' => [
                'id' => (int) $item['id'],
                'dia_semana' => (int) $item['dia_semana'],
                'periodo' => $item['periodo'],
                'horario_de' => substr((string) $item['horario_de'], 0, 5),
                'horario_ate' => substr((string) $item['horario_ate'], 0, 5),
                'turma_id' => (int) $item['turma_id'],
                'professor_id' => (int) $item['professor_id'],
                'materia_id' => (int) $item['materia_id'],
            ],
        ]);
    }

    /**
     * Salva nova aula na grade
     */
    public function store()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }

        $dia_semana = (int) ($_POST['dia_semana'] ?? 0);
        $horario_de = trim($_POST['horario_de'] ?? '');
        $horario_ate = trim($_POST['horario_ate'] ?? '');
        $turma_id = (int) ($_POST['turma_id'] ?? 0);
        $professor_id = (int) ($_POST['professor_id'] ?? 0);
        $materia_id = (int) ($_POST['materia_id'] ?? 0);
        $periodo = in_array($_POST['periodo'] ?? '', ['manha', 'tarde']) ? $_POST['periodo'] : 'manha';

        if ($dia_semana < 1 || $dia_semana > 7 || !$horario_de || !$horario_ate || !$turma_id || !$professor_id || !$materia_id) {
            $this->json(['error' => 'Preencha todos os campos obrigatórios.'], 400);
            return;
        }

        try {
            $this->db->insert(
                "INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
                 VALUES (:dia_semana, :horario_de, :horario_ate, :turma_id, :professor_id, :materia_id, :periodo)",
                [
                    'dia_semana' => $dia_semana,
                    'horario_de' => $horario_de,
                    'horario_ate' => $horario_ate,
                    'turma_id' => $turma_id,
                    'professor_id' => $professor_id,
                    'materia_id' => $materia_id,
                    'periodo' => $periodo,
                ]
            );
            $this->json(['success' => true, 'message' => 'Aula adicionada à grade com sucesso.']);
        } catch (Exception $e) {
            error_log("Erro ao salvar grade horária: " . $e->getMessage());
            $this->json(['error' => 'Erro ao salvar: ' . $e->getMessage()], 400);
        }
    }

    /**
     * Atualiza registro
     */
    public function update($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }

        $item = $this->db->fetch("SELECT id FROM grade_horaria WHERE id = :id", ['id' => (int) $id]);
        if (!$item) {
            $this->json(['error' => 'Registro não encontrado.'], 404);
            return;
        }

        $dia_semana = (int) ($_POST['dia_semana'] ?? 0);
        $horario_de = trim($_POST['horario_de'] ?? '');
        $horario_ate = trim($_POST['horario_ate'] ?? '');
        $turma_id = (int) ($_POST['turma_id'] ?? 0);
        $professor_id = (int) ($_POST['professor_id'] ?? 0);
        $materia_id = (int) ($_POST['materia_id'] ?? 0);
        $periodo = in_array($_POST['periodo'] ?? '', ['manha', 'tarde']) ? $_POST['periodo'] : 'manha';

        if ($dia_semana < 1 || $dia_semana > 7 || !$horario_de || !$horario_ate || !$turma_id || !$professor_id || !$materia_id) {
            $this->json(['error' => 'Preencha todos os campos obrigatórios.'], 400);
            return;
        }

        try {
            $this->db->query(
                "UPDATE grade_horaria SET dia_semana = :dia_semana, horario_de = :horario_de, horario_ate = :horario_ate,
                 turma_id = :turma_id, professor_id = :professor_id, materia_id = :materia_id, periodo = :periodo
                 WHERE id = :id",
                [
                    'id' => (int) $id,
                    'dia_semana' => $dia_semana,
                    'horario_de' => $horario_de,
                    'horario_ate' => $horario_ate,
                    'turma_id' => $turma_id,
                    'professor_id' => $professor_id,
                    'materia_id' => $materia_id,
                    'periodo' => $periodo,
                ]
            );
            $this->json(['success' => true, 'message' => 'Aula atualizada com sucesso.']);
        } catch (Exception $e) {
            error_log("Erro ao atualizar grade horária: " . $e->getMessage());
            $this->json(['error' => 'Erro ao atualizar: ' . $e->getMessage()], 400);
        }
    }

    /**
     * Remove registro
     */
    public function destroy($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? $_GET['_token'] ?? '')) {
            $_SESSION['error_message'] = 'Token inválido.';
            $this->redirect('/admin/grade-horaria');
            return;
        }

        try {
            $this->db->delete("DELETE FROM grade_horaria WHERE id = :id", ['id' => $id]);
            $_SESSION['success_message'] = 'Aula removida da grade.';
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao remover.';
        }
        $this->redirect('/admin/grade-horaria');
    }

    /**
     * Processa imagem da grade com IA: extrai dados e retorna preview (não salva).
     * O usuário confirma na tela e chama salvarImportacaoIA para gravar.
     */
    public function processarImagemIA()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token inválido.']);
            return;
        }

        if (empty($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Envie uma imagem (foto ou scan da grade horária).']);
            return;
        }

        $file = $_FILES['imagem'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, (string) $file['tmp_name']) : (string) ($file['type'] ?? '');
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!isset($allowed[$mime]) || (int) $file['size'] > 12 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Arquivo inválido ou muito grande. Use JPG, PNG ou WEBP (máx. 12MB).']);
            return;
        }

        try {
            $dir = realpath(__DIR__ . '/../../../storage') ?: (__DIR__ . '/../../../storage');
            $dir .= '/tmp/grade_horaria_ia';
            if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new Exception('Não foi possível preparar o envio da imagem.');
            }

            $nomeSeguro = preg_replace('/[^a-zA-Z0-9_.-]+/', '-', basename((string) ($file['name'] ?? 'grade')));
            $filename = 'grade-' . (int) ($this->auth->getUser()['id'] ?? 0) . '-' . bin2hex(random_bytes(8)) . '-' . $nomeSeguro;
            $path = $dir . '/' . $filename;
            if (!move_uploaded_file((string) $file['tmp_name'], $path)) {
                throw new Exception('Não foi possível salvar a imagem para processamento.');
            }
            @chmod($path, 0600);

            require_once __DIR__ . '/../../Services/AIJobService.php';
            $user = $this->auth->getUser();
            $jobId = \App\Services\AIJobService::enqueue('grade_horaria_importar_imagem', [
                'arquivo' => [
                    'path' => $path,
                    'nome' => substr((string) ($file['name'] ?? 'grade'), 0, 180),
                    'mime' => $mime,
                    'tamanho' => (int) ($file['size'] ?? 0),
                ],
            ], (int) ($user['id'] ?? 0), 'admin');

            echo json_encode([
                'success' => true,
                'job_id' => $jobId,
                'message' => 'Imagem enviada. A IA está lendo a grade em segundo plano.',
            ]);
        } catch (Exception $e) {
            error_log("Grade horária IA: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao processar imagem: ' . $e->getMessage()]);
        }
    }

    /**
     * Salva itens da importação por imagem após o usuário confirmar o preview
     */
    public function salvarImportacaoIA()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        if (!$this->verifyCsrfToken($input['_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token inválido.']);
            return;
        }

        $itens = $input['itens'] ?? [];
        if (!is_array($itens) || empty($itens)) {
            echo json_encode(['success' => false, 'error' => 'Nenhum item para salvar.']);
            return;
        }

        $inseridos = 0;
        foreach ($itens as $row) {
            $dia_semana = (int) ($row['dia_semana'] ?? 0);
            $horario_de = isset($row['horario_de']) ? preg_replace('/[^\d:]/', '', $row['horario_de']) : '';
            $horario_ate = isset($row['horario_ate']) ? preg_replace('/[^\d:]/', '', $row['horario_ate']) : '';
            $turma_id = (int) ($row['turma_id'] ?? 0);
            $professor_id = (int) ($row['professor_id'] ?? 0);
            $materia_id = (int) ($row['materia_id'] ?? 0);
            $periodo = isset($row['periodo']) && in_array($row['periodo'], ['manha', 'tarde']) ? $row['periodo'] : 'manha';

            if ($dia_semana < 1 || $dia_semana > 7 || strlen($horario_de) !== 5 || strlen($horario_ate) !== 5 || !$turma_id || !$professor_id || !$materia_id) {
                continue;
            }

            try {
                $this->db->insert(
                    "INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
                     VALUES (:dia_semana, :horario_de, :horario_ate, :turma_id, :professor_id, :materia_id, :periodo)",
                    [
                        'dia_semana' => $dia_semana,
                        'horario_de' => $horario_de,
                        'horario_ate' => $horario_ate,
                        'turma_id' => $turma_id,
                        'professor_id' => $professor_id,
                        'materia_id' => $materia_id,
                        'periodo' => $periodo,
                    ]
                );
                $inseridos++;
            } catch (Exception $e) {
                // ignora item duplicado ou erro
            }
        }

        echo json_encode([
            'success' => true,
            'inseridos' => $inseridos,
            'message' => $inseridos . ' aula(s) adicionada(s) à grade.',
        ]);
    }

    /**
     * @return array{tipo_ensino:string,serie:string,turma_id:int,periodo:string,professor_id:int,materia_id:int,visao:string,dia:int}
     */
    private function filtrosDaRequest(): array
    {
        $periodo = strtolower(trim((string) ($_GET['periodo'] ?? '')));
        $visao = strtolower(trim((string) ($_GET['visao'] ?? 'semana')));
        $dia = (int) ($_GET['dia'] ?? 0);

        return [
            'tipo_ensino' => mb_substr(trim((string) ($_GET['tipo_ensino'] ?? '')), 0, 80),
            'serie' => mb_substr(trim((string) ($_GET['serie'] ?? '')), 0, 80),
            'turma_id' => (int) ($_GET['turma_id'] ?? 0),
            'periodo' => in_array($periodo, ['manha', 'tarde'], true) ? $periodo : '',
            'professor_id' => (int) ($_GET['professor_id'] ?? 0),
            'materia_id' => (int) ($_GET['materia_id'] ?? 0),
            'visao' => in_array($visao, ['semana', 'dia', 'lista'], true) ? $visao : 'semana',
            'dia' => ($dia >= 1 && $dia <= 7) ? $dia : 0,
        ];
    }

    /**
     * @param array{tipo_ensino:string,serie:string,turma_id:int,periodo:string,professor_id:int,materia_id:int,visao:string,dia:int} $filtros
     * @return list<array<string,mixed>>
     */
    private function listarItens(array $filtros): array
    {
        $selectSala = ', NULL AS sala_nome';
        $joinSala = '';
        if ($this->colunaExiste('turmas', 'sala_padrao_id') && $this->tabelaExiste('school_locations')) {
            $selectSala = ', sl.nome AS sala_nome';
            $joinSala = ' LEFT JOIN school_locations sl ON sl.id = t.sala_padrao_id';
        }

        $where = [];
        $params = [];

        if ($filtros['turma_id'] > 0) {
            $where[] = 'g.turma_id = :turma_id';
            $params['turma_id'] = $filtros['turma_id'];
        }
        if ($filtros['periodo'] !== '') {
            $where[] = 'g.periodo = :periodo';
            $params['periodo'] = $filtros['periodo'];
        }
        if ($filtros['professor_id'] > 0) {
            $where[] = 'g.professor_id = :professor_id';
            $params['professor_id'] = $filtros['professor_id'];
        }
        if ($filtros['materia_id'] > 0) {
            $where[] = 'g.materia_id = :materia_id';
            $params['materia_id'] = $filtros['materia_id'];
        }
        if ($filtros['tipo_ensino'] !== '') {
            $where[] = 't.tipo_ensino = :tipo_ensino';
            $params['tipo_ensino'] = $filtros['tipo_ensino'];
        }
        if ($filtros['serie'] !== '') {
            $where[] = 't.serie = :serie';
            $params['serie'] = $filtros['serie'];
        }

        $sql = "SELECT g.*, t.nome AS turma_nome, t.serie AS turma_serie, t.tipo_ensino,
                       p.nome AS professor_nome, m.nome AS materia_nome
                       {$selectSala}
                FROM grade_horaria g
                JOIN turmas t ON g.turma_id = t.id
                JOIN professores p ON g.professor_id = p.id
                JOIN materias m ON g.materia_id = m.id
                {$joinSala}";
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY g.horario_de ASC, g.horario_ate ASC, g.dia_semana ASC, t.nome ASC';

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    private function colunaExiste(string $tabela, string $coluna): bool
    {
        if ($tabela !== 'turmas' || $coluna !== 'sala_padrao_id') {
            return false;
        }
        try {
            return $this->db->fetch('SHOW COLUMNS FROM turmas LIKE :c', ['c' => $coluna]) !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    private function tabelaExiste(string $tabela): bool
    {
        if ($tabela !== 'school_locations') {
            return false;
        }
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tabela LIMIT 1',
                ['tabela' => $tabela]
            );
            return !empty($row);
        } catch (Exception $e) {
            return false;
        }
    }

    private function renderizarHtml(string $view, array $viewData): string
    {
        $arquivo = $this->resolveViewPath($view);
        if ($arquivo === null) {
            throw new Exception('View não encontrada: ' . $view);
        }
        ob_start();
        extract($viewData, EXTR_SKIP);
        require $arquivo;
        return (string) ob_get_clean();
    }

    private function outputPdf(string $html, string $filename, string $orientation = 'landscape'): void
    {
        $orientation = $orientation === 'portrait' ? 'portrait' : 'landscape';
        $old = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', $orientation);
            $dompdf->render();
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            echo $dompdf->output();
            exit;
        } finally {
            ini_set('display_errors', (string) $old);
        }
    }
}
}
