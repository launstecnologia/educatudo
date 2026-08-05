<?php
/**
 * EducaTudo - Controller de Catálogo Acadêmico
 * Gerencia cadastro de tipos de curso e cursos.
 */

if (!class_exists('CourseCatalogController')) {
class CourseCatalogController extends BaseController
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

    public function index()
    {
        $user = $this->auth->getUser();
        $schemaReady = $this->isSchemaReady();

        $tiposCurso = [];
        $cursos = [];

        if ($schemaReady) {
            $tiposCurso = $this->db->fetchAll(
                "SELECT tc.*,
                        (SELECT COUNT(*) FROM cursos c WHERE c.tipo_curso_id = tc.id) AS total_cursos
                 FROM tipos_curso tc
                 ORDER BY tc.ordem ASC, tc.nome ASC"
            );

            $cursos = $this->db->fetchAll(
                "SELECT c.*, tc.nome AS tipo_nome
                 FROM cursos c
                 INNER JOIN tipos_curso tc ON tc.id = c.tipo_curso_id
                 ORDER BY tc.ordem ASC, tc.nome ASC, c.ordem ASC, c.nome ASC"
            );
        }

        $data = [
            'title' => 'Tipos de Curso e Cursos - EducaTudo',
            'user' => $user,
            'current_page' => 'cursos_catalogo',
            'csrf_token' => $this->generateCsrfToken(),
            'schema_ready' => $schemaReady,
            'tipos_curso' => $tiposCurso,
            'cursos' => $cursos,
            'status' => $_GET['status'] ?? '',
            'message' => $_GET['message'] ?? ''
        ];

        $this->viewWithLayout('admin', 'admin/cursos/index', $data);
    }

    public function storeTipoCurso()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMessage('error', 'Token inválido.');
        }

        if (!$this->isSchemaReady()) {
            $this->redirectWithMessage('error', 'Execute a migration 006 para habilitar tipos de curso.');
        }

        try {
            $nome = trim((string)($_POST['nome'] ?? ''));
            $ordem = (int)($_POST['ordem'] ?? 0);
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if ($nome === '') {
                throw new Exception('Nome do tipo de curso é obrigatório.');
            }

            $exists = $this->db->fetch(
                "SELECT id FROM tipos_curso WHERE nome = :nome LIMIT 1",
                ['nome' => $nome]
            );

            if ($exists) {
                throw new Exception('Já existe um tipo de curso com esse nome.');
            }

            $slug = $this->slugify($nome);
            if ($slug === '') {
                $slug = 'tipo-curso-' . time();
            }
            $slug = $this->ensureUniqueSlug('tipos_curso', $slug);

            $this->db->insert(
                "INSERT INTO tipos_curso (nome, slug, ativo, ordem, created_at, updated_at)
                 VALUES (:nome, :slug, :ativo, :ordem, NOW(), NOW())",
                [
                    'nome' => $nome,
                    'slug' => $slug,
                    'ativo' => $ativo,
                    'ordem' => $ordem
                ]
            );

            $this->redirectWithMessage('success', 'Tipo de curso cadastrado com sucesso.');
        } catch (Exception $e) {
            $this->redirectWithMessage('error', $e->getMessage());
        }
    }

    public function storeCurso()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMessage('error', 'Token inválido.');
        }

        if (!$this->isSchemaReady()) {
            $this->redirectWithMessage('error', 'Execute a migration 006 para habilitar cursos.');
        }

        try {
            $nome = trim((string)($_POST['nome'] ?? ''));
            $tipoCursoId = (int)($_POST['tipo_curso_id'] ?? 0);
            $ordem = (int)($_POST['ordem'] ?? 0);
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if ($nome === '') {
                throw new Exception('Nome do curso é obrigatório.');
            }
            if ($tipoCursoId <= 0) {
                throw new Exception('Selecione um tipo de curso.');
            }

            $tipo = $this->db->fetch(
                "SELECT id FROM tipos_curso WHERE id = :id LIMIT 1",
                ['id' => $tipoCursoId]
            );
            if (!$tipo) {
                throw new Exception('Tipo de curso inválido.');
            }

            $exists = $this->db->fetch(
                "SELECT id FROM cursos WHERE tipo_curso_id = :tipo_curso_id AND nome = :nome LIMIT 1",
                ['tipo_curso_id' => $tipoCursoId, 'nome' => $nome]
            );

            if ($exists) {
                throw new Exception('Já existe esse curso para o tipo selecionado.');
            }

            $slugBase = $this->slugify($nome);
            if ($slugBase === '') {
                $slugBase = 'curso-' . time();
            }
            $slug = $this->ensureUniqueSlug('cursos', $slugBase);

            $this->db->insert(
                "INSERT INTO cursos (tipo_curso_id, nome, slug, ativo, ordem, created_at, updated_at)
                 VALUES (:tipo_curso_id, :nome, :slug, :ativo, :ordem, NOW(), NOW())",
                [
                    'tipo_curso_id' => $tipoCursoId,
                    'nome' => $nome,
                    'slug' => $slug,
                    'ativo' => $ativo,
                    'ordem' => $ordem
                ]
            );

            $this->redirectWithMessage('success', 'Curso cadastrado com sucesso.');
        } catch (Exception $e) {
            $this->redirectWithMessage('error', $e->getMessage());
        }
    }

    private function isSchemaReady()
    {
        try {
            $hasTiposCurso = $this->db->fetch("SHOW TABLES LIKE 'tipos_curso'");
            $hasCursos = $this->db->fetch("SHOW TABLES LIKE 'cursos'");
            return ($hasTiposCurso !== false) && ($hasCursos !== false);
        } catch (Exception $e) {
            return false;
        }
    }

    private function slugify($value)
    {
        $value = mb_strtolower(trim((string)$value), 'UTF-8');
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c'
        ]);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim((string)$value, '-');
    }

    private function ensureUniqueSlug($table, $baseSlug)
    {
        $slug = $baseSlug;
        $counter = 2;

        while (true) {
            $existing = $this->db->fetch(
                "SELECT id FROM {$table} WHERE slug = :slug LIMIT 1",
                ['slug' => $slug]
            );
            if (!$existing) {
                return $slug;
            }
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
    }

    private function redirectWithMessage($status, $message)
    {
        $statusSafe = $status === 'success' ? 'success' : 'error';
        $url = '/admin/cursos?status=' . rawurlencode($statusSafe) . '&message=' . rawurlencode((string)$message);
        $this->redirect($url);
    }
}
}
