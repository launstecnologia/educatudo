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

        $itens = $this->db->fetchAll(
            "SELECT g.*, t.nome as turma_nome, p.nome as professor_nome, m.nome as materia_nome
             FROM grade_horaria g
             JOIN turmas t ON g.turma_id = t.id
             JOIN professores p ON g.professor_id = p.id
             JOIN materias m ON g.materia_id = m.id
             ORDER BY g.dia_semana ASC, g.horario_de ASC"
        );

        $data = [
            'title' => 'Grade Horária de Aulas - EducaTudo',
            'user' => $user,
            'current_page' => 'grade_horaria',
            'itens' => $itens,
            'dias_semana' => self::$DIAS_SEMANA,
            'csrf_token' => $this->generateCsrfToken(),
        ];

        $this->viewWithLayout('admin', 'admin/grade-horaria/index', $data);
    }

    /**
     * Formulário de nova aula
     */
    public function create()
    {
        $user = $this->auth->getUser();
        $turmas = $this->db->fetchAll("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome");
        $professores = $this->db->fetchAll("SELECT id, nome FROM professores WHERE ativo = 1 ORDER BY nome");
        $materias = $this->db->fetchAll("SELECT id, nome FROM materias ORDER BY nome");

        $data = [
            'title' => 'Nova Aula na Grade - EducaTudo',
            'user' => $user,
            'current_page' => 'grade_horaria',
            'dias_semana' => self::$DIAS_SEMANA,
            'turmas' => $turmas,
            'professores' => $professores,
            'materias' => $materias,
            'csrf_token' => $this->generateCsrfToken(),
        ];

        $this->viewWithLayout('admin', 'admin/grade-horaria/create', $data);
    }

    /**
     * Salva nova aula na grade
     */
    public function store()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $_SESSION['error_message'] = 'Token inválido.';
            $this->redirect('/admin/grade-horaria/create');
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
            $_SESSION['error_message'] = 'Preencha todos os campos obrigatórios.';
            $this->redirect('/admin/grade-horaria/create');
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
            $_SESSION['success_message'] = 'Aula adicionada à grade com sucesso.';
            $this->redirect('/admin/grade-horaria');
        } catch (Exception $e) {
            error_log("Erro ao salvar grade horária: " . $e->getMessage());
            $_SESSION['error_message'] = 'Erro ao salvar: ' . $e->getMessage();
            $this->redirect('/admin/grade-horaria/create');
        }
    }

    /**
     * Formulário de edição
     */
    public function edit($id)
    {
        $user = $this->auth->getUser();
        $item = $this->db->fetch(
            "SELECT * FROM grade_horaria WHERE id = :id",
            ['id' => $id]
        );

        if (!$item) {
            $_SESSION['error_message'] = 'Registro não encontrado.';
            $this->redirect('/admin/grade-horaria');
            return;
        }

        $turmas = $this->db->fetchAll("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome");
        $professores = $this->db->fetchAll("SELECT id, nome FROM professores WHERE ativo = 1 ORDER BY nome");
        $materias = $this->db->fetchAll("SELECT id, nome FROM materias ORDER BY nome");

        $data = [
            'title' => 'Editar Aula na Grade - EducaTudo',
            'user' => $user,
            'current_page' => 'grade_horaria',
            'item' => $item,
            'dias_semana' => self::$DIAS_SEMANA,
            'turmas' => $turmas,
            'professores' => $professores,
            'materias' => $materias,
            'csrf_token' => $this->generateCsrfToken(),
        ];

        $this->viewWithLayout('admin', 'admin/grade-horaria/edit', $data);
    }

    /**
     * Atualiza registro
     */
    public function update($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $_SESSION['error_message'] = 'Token inválido.';
            $this->redirect('/admin/grade-horaria/' . $id . '/edit');
            return;
        }

        $item = $this->db->fetch("SELECT id FROM grade_horaria WHERE id = :id", ['id' => $id]);
        if (!$item) {
            $_SESSION['error_message'] = 'Registro não encontrado.';
            $this->redirect('/admin/grade-horaria');
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
            $_SESSION['error_message'] = 'Preencha todos os campos obrigatórios.';
            $this->redirect('/admin/grade-horaria/' . $id . '/edit');
            return;
        }

        try {
            $this->db->query(
                "UPDATE grade_horaria SET dia_semana = :dia_semana, horario_de = :horario_de, horario_ate = :horario_ate,
                 turma_id = :turma_id, professor_id = :professor_id, materia_id = :materia_id, periodo = :periodo
                 WHERE id = :id",
                [
                    'id' => $id,
                    'dia_semana' => $dia_semana,
                    'horario_de' => $horario_de,
                    'horario_ate' => $horario_ate,
                    'turma_id' => $turma_id,
                    'professor_id' => $professor_id,
                    'materia_id' => $materia_id,
                    'periodo' => $periodo,
                ]
            );
            $_SESSION['success_message'] = 'Aula atualizada com sucesso.';
            $this->redirect('/admin/grade-horaria');
        } catch (Exception $e) {
            error_log("Erro ao atualizar grade horária: " . $e->getMessage());
            $_SESSION['error_message'] = 'Erro ao atualizar: ' . $e->getMessage();
            $this->redirect('/admin/grade-horaria/' . $id . '/edit');
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
}
}
