<?php
/**
 * EducaTudo - CRUD Curso (tabela curso, estrutura normalizada - migration 023)
 */

require_once __DIR__ . '/../../Helpers/AdminPasswordHelper.php';

if (!class_exists('CursoController')) {
class CursoController extends BaseController
{
    private $auth;
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $user = $this->auth->getUser();
        if ($user && $user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola') {
            $this->redirect('/admin');
        }
    }

    private function tableExists()
    {
        try {
            return $this->db->fetch("SHOW TABLES LIKE 'curso'") !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /** Verifica se a tabela curso tem as colunas tipo e possui_serie (migration 030). */
    private function hasTipoPossuiSerie()
    {
        try {
            $c = $this->db->fetch(
                "SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'curso' AND COLUMN_NAME = 'tipo'"
            );
            return ($c['n'] ?? 0) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    public function index()
    {
        $user = $this->auth->getUser();
        if (!$this->tableExists()) {
            $data = [
                'title' => 'Cursos - EducaTudo',
                'user' => $user,
                'current_page' => 'curso',
                'schema_ready' => false,
                'list' => []
            ];
            $this->viewWithLayout('admin', 'admin/curso/index', $data);
            return;
        }

        $perPage = 10;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $totalGeral = (int)($this->db->fetch("SELECT COUNT(*) AS total FROM curso")['total'] ?? 0);
        $list = $this->db->fetchAll(
            "SELECT c.*, (SELECT COUNT(*) FROM serie s WHERE s.curso_id = c.id) AS total_series
             FROM curso c ORDER BY c.ordem ASC, c.nome ASC
             LIMIT $perPage OFFSET $offset"
        );

        $pagination = [
            'total' => $totalGeral,
            'per_page' => $perPage,
            'page' => $page,
            'total_pages' => $perPage > 0 ? (int)ceil($totalGeral / $perPage) : 1,
        ];

        $data = [
            'title' => 'Cursos - EducaTudo',
            'user' => $user,
            'current_page' => 'curso',
            'schema_ready' => true,
            'list' => $list,
            'pagination' => $pagination,
            'csrf_token' => $this->generateCsrfToken(),
            'status' => $_GET['status'] ?? '',
            'message' => $_GET['message'] ?? '',
            'has_tipo_possui_serie' => $this->hasTipoPossuiSerie(),
        ];
        $this->viewWithLayout('admin', 'admin/curso/index', $data);
    }

    /**
     * Dados de um curso (JSON) para popular o offcanvas de edição
     */
    public function dados($id)
    {
        if (!$this->tableExists()) {
            $this->json(['error' => 'Tabela não disponível.'], 400);
            return;
        }
        $item = $this->db->fetch("SELECT * FROM curso WHERE id = :id", ['id' => $id]);
        if (!$item) {
            $this->json(['error' => 'Curso não encontrado.'], 404);
            return;
        }
        $this->json(['success' => true, 'item' => $item]);
    }

    public function store()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }
        if (!$this->tableExists()) {
            $this->json(['error' => 'Tabela não disponível.'], 400);
            return;
        }
        try {
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '') ?: null;
            $ordem = (int)($_POST['ordem'] ?? 0);
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $tipo = trim($_POST['tipo'] ?? '') === 'extra' ? 'extra' : 'regular';
            $possuiSerie = ($tipo === 'extra') ? 0 : (isset($_POST['possui_serie']) ? 1 : 0);
            if ($nome === '') {
                throw new Exception('Nome é obrigatório.');
            }
            $existe = $this->db->fetch("SELECT id FROM curso WHERE nome = :nome", ['nome' => $nome]);
            if ($existe) {
                throw new Exception('Já existe um curso com este nome.');
            }
            if ($this->hasTipoPossuiSerie()) {
                $this->db->insert(
                    "INSERT INTO curso (nome, descricao, ativo, ordem, tipo, possui_serie) VALUES (:nome, :descricao, :ativo, :ordem, :tipo, :possui_serie)",
                    ['nome' => $nome, 'descricao' => $descricao, 'ativo' => $ativo, 'ordem' => $ordem, 'tipo' => $tipo, 'possui_serie' => $possuiSerie]
                );
            } else {
                $this->db->insert(
                    "INSERT INTO curso (nome, descricao, ativo, ordem) VALUES (:nome, :descricao, :ativo, :ordem)",
                    ['nome' => $nome, 'descricao' => $descricao, 'ativo' => $ativo, 'ordem' => $ordem]
                );
            }
            $this->json(['success' => true, 'message' => 'Curso cadastrado com sucesso.']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }
        if (!$this->tableExists()) {
            $this->json(['error' => 'Tabela não disponível.'], 400);
            return;
        }
        try {
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '') ?: null;
            $ordem = (int)($_POST['ordem'] ?? 0);
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            if ($nome === '') {
                throw new Exception('Nome é obrigatório.');
            }
            $existe = $this->db->fetch("SELECT id FROM curso WHERE nome = :nome AND id != :id", ['nome' => $nome, 'id' => $id]);
            if ($existe) {
                throw new Exception('Já existe outro curso com este nome.');
            }
            $tipo = trim($_POST['tipo'] ?? '') === 'extra' ? 'extra' : 'regular';
            $possuiSerie = ($tipo === 'extra') ? 0 : (isset($_POST['possui_serie']) ? 1 : 0);
            if ($this->hasTipoPossuiSerie()) {
                $this->db->update(
                    "UPDATE curso SET nome = :nome, descricao = :descricao, ativo = :ativo, ordem = :ordem, tipo = :tipo, possui_serie = :possui_serie WHERE id = :id",
                    ['nome' => $nome, 'descricao' => $descricao, 'ativo' => $ativo, 'ordem' => $ordem, 'tipo' => $tipo, 'possui_serie' => $possuiSerie, 'id' => $id]
                );
            } else {
                $this->db->update(
                    "UPDATE curso SET nome = :nome, descricao = :descricao, ativo = :ativo, ordem = :ordem WHERE id = :id",
                    ['nome' => $nome, 'descricao' => $descricao, 'ativo' => $ativo, 'ordem' => $ordem, 'id' => $id]
                );
            }
            $this->json(['success' => true, 'message' => 'Curso atualizado com sucesso.']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function destroy($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }

        $user = $this->auth->getUser();
        $senha = trim((string) ($_POST['senha'] ?? ''));
        if ($senha === '') {
            $this->json(['error' => 'Digite sua senha para confirmar.'], 400);
            return;
        }
        if (!AdminPasswordHelper::verifyAdminPassword($this->db, $user, $senha)) {
            $this->json(['error' => 'Senha incorreta.'], 400);
            return;
        }

        $result = $this->deleteCursoById((int) $id);
        if ($result['deleted']) {
            $this->json(['success' => true, 'message' => 'Curso excluído.']);
            return;
        }

        $this->json(['error' => $result['error']], 400);
    }

    public function bulkDestroy()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }

        $user = $this->auth->getUser();
        $senha = trim((string) ($_POST['senha'] ?? ''));
        if ($senha === '') {
            $this->json(['error' => 'Digite sua senha para confirmar.'], 400);
            return;
        }
        if (!AdminPasswordHelper::verifyAdminPassword($this->db, $user, $senha)) {
            $this->json(['error' => 'Senha incorreta.'], 400);
            return;
        }

        $ids = AdminPasswordHelper::parseIds($_POST['curso_ids'] ?? []);
        if ($ids === []) {
            $this->json(['error' => 'Selecione ao menos um curso.'], 400);
            return;
        }

        $deleted = 0;
        $errors = [];
        foreach ($ids as $id) {
            $result = $this->deleteCursoById($id);
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

        $message = $deleted . ' curso(s) excluído(s).';
        if ($errors !== []) {
            $message .= ' ' . count($errors) . ' não excluído(s): ' . implode(' ', array_slice($errors, 0, 3));
        }

        $this->json(['success' => true, 'message' => $message, 'deleted' => $deleted, 'errors' => $errors]);
    }

    /**
     * @return array{deleted: bool, error: string}
     */
    private function deleteCursoById(int $id): array
    {
        if (!$this->tableExists()) {
            return ['deleted' => false, 'error' => 'Estrutura não disponível.'];
        }

        $item = $this->db->fetch("SELECT id, nome FROM curso WHERE id = :id", ['id' => $id]);
        if (!$item) {
            return ['deleted' => false, 'error' => 'Curso #' . $id . ' não encontrado.'];
        }

        $temSerie = $this->db->fetch("SELECT id FROM serie WHERE curso_id = :id LIMIT 1", ['id' => $id]);
        if ($temSerie) {
            return ['deleted' => false, 'error' => '"' . $item['nome'] . '": possui séries vinculadas.'];
        }

        $this->db->delete("DELETE FROM curso WHERE id = :id", ['id' => $id]);

        return ['deleted' => true, 'error' => ''];
    }

    /**
     * Formulário de importação de alunos em curso extra (CSV).
     */
    public function importarAlunos($id)
    {
        $user = $this->auth->getUser();
        if (!$this->tableExists()) {
            $this->redirect('/admin/curso');
            return;
        }
        $curso = $this->db->fetch("SELECT * FROM curso WHERE id = :id", ['id' => $id]);
        if (!$curso) {
            $this->redirect('/admin/curso?status=error&message=' . rawurlencode('Curso não encontrado.'));
            return;
        }
        $turmas = $this->listarTurmasDoCurso((int) $id);
        $turmaPreSelecionada = (int) ($_GET['turma_id'] ?? 0);
        $data = [
            'title' => 'Importar alunos - ' . $curso['nome'],
            'user' => $user,
            'current_page' => 'curso',
            'curso' => $curso,
            'turmas' => $turmas,
            'turma_pre_selecionada' => $turmaPreSelecionada,
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->viewWithLayout('admin', 'admin/curso/importar-alunos', $data);
    }

    /**
     * Processa upload de CSV: identifica por nome; cadastra se não existir; matricula na turma.
     */
    public function processarImportacaoAlunos($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect('/admin/curso/' . $id . '/importar-alunos?status=error&message=' . rawurlencode('Token inválido.'));
            return;
        }
        if (!$this->tableExists()) {
            $this->redirect('/admin/curso');
            return;
        }
        $curso = $this->db->fetch("SELECT * FROM curso WHERE id = :id", ['id' => $id]);
        if (!$curso) {
            $this->redirect('/admin/curso?status=error&message=' . rawurlencode('Curso não encontrado.'));
            return;
        }

        $cursoExtra = (isset($curso['tipo']) && $curso['tipo'] === 'extra');
        $turma_id = (int)($_POST['turma_id'] ?? 0);
        if ($turma_id <= 0) {
            $this->redirect('/admin/curso/' . $id . '/importar-alunos?status=error&message=' . rawurlencode('Selecione a turma.'));
            return;
        }
        $turma = $this->db->fetch("SELECT id, nome, serie FROM turmas WHERE id = :id AND ativo = 1", ['id' => $turma_id]);
        if (!$turma) {
            $this->redirect('/admin/curso/' . $id . '/importar-alunos?status=error&message=' . rawurlencode('Turma inválida.'));
            return;
        }

        $file = $_FILES['arquivo'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            $this->redirect('/admin/curso/' . $id . '/importar-alunos?status=error&message=' . rawurlencode('Envie um arquivo CSV.'));
            return;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $this->redirect('/admin/curso/' . $id . '/importar-alunos?status=error&message=' . rawurlencode('Aceito apenas arquivo .csv (exporte do Excel como CSV).'));
            return;
        }

        $anoLetivo = (string) date('Y');
        $ano_letivo_id = null;
        $hasMatricula = $this->db->fetch("SHOW TABLES LIKE 'matricula'");
        if ($hasMatricula) {
            $al = $this->db->fetch("SELECT id FROM ano_letivo WHERE ativo = 1 ORDER BY ano DESC LIMIT 1");
            if ($al) {
                $ano_letivo_id = (int) $al['id'];
            }
        }
        if (!$ano_letivo_id) {
            $this->redirect('/admin/curso/' . $id . '/importar-alunos?status=error&message=' . rawurlencode('Nenhum ano letivo ativo encontrado.'));
            return;
        }

        require_once __DIR__ . '/../../Models/User/Student.php';
        require_once __DIR__ . '/../../Services/AlunoMovimentacaoService.php';
        $studentModel = new Student();
        $movimentacao = new \App\Services\AlunoMovimentacaoService();

        $cadastrados = 0;
        $matriculados = 0;
        $erros = [];

        $raw = file_get_contents($file['tmp_name']);
        if ($raw === false) {
            $this->redirect('/admin/curso/' . $id . '/importar-alunos?status=error&message=' . rawurlencode('Não foi possível ler o arquivo.'));
            return;
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $lines = array_map('trim', preg_split('/\r\n|\r|\n/', $raw));
        $lines = array_values(array_filter($lines, function ($l) { return $l !== ''; }));
        $serieTurma = $turma['serie'] ?? 'Não informada';

        $delimiter = ',';
        if (!empty($lines)) {
            $first = $lines[0];
            if (strpos($first, ';') !== false && (strpos($first, ',') === false || substr_count($first, ';') >= substr_count($first, ','))) {
                $delimiter = ';';
            }
        }

        foreach ($lines as $num => $line) {
            $rowNum = $num + 1;
            $cols = str_getcsv($line, $delimiter, '"', '');
            $cols = array_map('trim', $cols);
            $nome = $cols[0] ?? '';
            $isHeader = (strtolower($nome) === 'nome' || strtolower($nome) === 'nome do aluno');
            if ($rowNum === 1 && $isHeader) {
                continue;
            }
            if ($nome === '') {
                $erros[] = "Linha {$rowNum}: nome vazio.";
                continue;
            }

            $email = isset($cols[1]) ? trim($cols[1]) : null;
            $data_nasc = null;
            if (isset($cols[2]) && trim($cols[2]) !== '') {
                $dn = trim($cols[2]);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dn)) {
                    $data_nasc = $dn;
                } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dn, $m)) {
                    $data_nasc = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
                }
            }
            $serie = isset($cols[3]) && trim($cols[3]) !== '' ? trim($cols[3]) : $serieTurma;

            $alunos = $studentModel->findAllByNome($nome);

            if (count($alunos) === 0) {
                $nickname = $this->gerarNicknameUnico($nome, $studentModel);
                try {
                    $alunoId = $studentModel->create([
                        'nome' => $nome,
                        'email' => $email,
                        'nickname' => $nickname,
                        'turma_id' => $turma_id,
                        'serie' => $serie,
                        'data_nasc' => $data_nasc,
                        'ativo' => 1,
                        'pagante' => 1,
                    ]);
                    $cadastrados++;
                    $movimentacao->vincularAlunoTurma($alunoId, $turma_id, $ano_letivo_id, true);
                } catch (Exception $e) {
                    $erros[] = "Linha {$rowNum} ({$nome}): " . $e->getMessage();
                }
                continue;
            }

            if (count($alunos) > 1) {
                $erros[] = "Linha {$rowNum} ({$nome}): mais de um aluno com este nome. Corrija ou cadastre manualmente.";
                continue;
            }

            $alunoId = (int) $alunos[0]['id'];
            try {
                $movimentacao->vincularAlunoTurma($alunoId, $turma_id, $ano_letivo_id, false);
                $matriculados++;
            } catch (Exception $e) {
                $erros[] = "Linha {$rowNum} ({$nome}): " . $e->getMessage();
            }
        }

        $msg = "Importação concluída: {$cadastrados} cadastrados, {$matriculados} matriculados.";
        if (!empty($erros)) {
            $msg .= ' Erros: ' . count($erros) . ' — ' . implode(' ', array_slice($erros, 0, 3));
            if (count($erros) > 3) {
                $msg .= '...';
            }
        } elseif ($cadastrados === 0 && $matriculados === 0) {
            $msg .= ' Nenhum dado importado: confira se o arquivo tem mais de uma linha (cabeçalho "nome" e depois os nomes dos alunos). Se exportou do Excel, salve como "CSV UTF-8" ou "CSV".';
        }
        $this->redirect('/admin/curso/' . $id . '/importar-alunos?status=success&message=' . rawurlencode($msg) . '&cadastrados=' . $cadastrados . '&matriculados=' . $matriculados . '&erros=' . count($erros));
    }

    private function listarTurmasDoCurso(int $cursoId): array
    {
        try {
            $hasCol = $this->db->fetch("SHOW COLUMNS FROM turmas LIKE 'curso_novo_id'");
            if ($hasCol !== false) {
                $turmas = $this->db->fetchAll(
                    'SELECT id, nome FROM turmas WHERE ativo = 1 AND curso_novo_id = :curso_id ORDER BY nome ASC',
                    ['curso_id' => $cursoId]
                );
                if (!empty($turmas)) {
                    return $turmas;
                }
            }
        } catch (Exception $e) {
            // fallback abaixo
        }

        return $this->db->fetchAll('SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome ASC') ?: [];
    }

    private function gerarNicknameUnico($nome, Student $studentModel)
    {
        $base = preg_replace('/[^a-zA-Z0-9]/', '', mb_strtolower($nome));
        $base = substr($base, 0, 20) ?: 'aluno';
        $nick = $base;
        $n = 0;
        while ($studentModel->nicknameExists($nick)) {
            $n++;
            $nick = $base . $n;
            if (strlen($nick) > 50) {
                $nick = $base . substr(uniqid(), -4);
            }
        }
        return $nick;
    }

    /**
     * Download do modelo CSV para importação.
     */
    public function modeloCsv($id)
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="modelo_importacao_alunos.csv"');
        echo "\xEF\xBB\xBF";
        echo "nome,email,data_nasc,serie\n";
        echo "João Silva,joao@email.com,2010-05-15,6º ano\n";
        echo "Maria Santos,,,7º ano\n";
        exit;
    }
}
}
