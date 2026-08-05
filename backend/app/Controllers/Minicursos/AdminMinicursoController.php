<?php
/**
 * EducaTudo - Admin Minicursos
 * CRUD de minicursos, módulos e aulas (Vídeo, Slides, PDF, Link, texto)
 */

if (!class_exists('AdminMinicursoController')) {
class AdminMinicursoController extends BaseController
{
    private $auth;
    private $db;

    private static $UPLOAD_CAPA_DIR = 'public/uploads/minicursos/capa/';
    private static $UPLOAD_ARQUIVOS_DIR = 'public/uploads/minicursos/arquivos/';
    private static $UPLOAD_AULAS_DIR = 'public/uploads/minicursos/aulas/';
    private static $EXTENSOES_CAPA = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private static $EXTENSOES_ARQUIVO = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'jpg', 'jpeg', 'png', 'gif'];
    private static $EXTENSOES_AULA = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'mp4', 'webm', 'mp3', 'wav', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
        }
    }

    public function index()
    {
        $lista = $this->db->fetchAll("SELECT * FROM minicursos ORDER BY ordem ASC, id DESC");
        $data = [
            'title' => 'Minicursos - Admin',
            'user' => $this->auth->getUser(),
            'current_page' => 'minicursos',
            'lista' => $lista,
            'csrf_token' => $this->generateCsrfToken()
        ];
        $this->viewWithLayout('admin', 'admin/minicursos/index', $data);
    }

    public function create()
    {
        $data = [
            'title' => 'Novo Minicurso',
            'user' => $this->auth->getUser(),
            'current_page' => 'minicursos',
            'csrf_token' => $this->generateCsrfToken()
        ];
        $this->viewWithLayout('admin', 'admin/minicursos/create', $data);
    }

    public function store()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido', 'error');
            $this->redirect('/admin/minicursos');
            return;
        }
        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $imagem_url = trim($_POST['imagem_url'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $ordem = (int)($_POST['ordem'] ?? 0);
        if ($titulo === '') {
            $this->setFlashMessage('Título é obrigatório.', 'error');
            $this->redirect('/admin/minicursos/criar');
            return;
        }
        $imagem_caminho = $this->processarUploadCapa(null);
        if ($imagem_caminho !== null) {
            $imagem_url = '';
        }
        try {
            $id = $this->db->insert(
                "INSERT INTO minicursos (titulo, descricao, imagem_url, imagem_caminho, ativo, ordem) VALUES (:titulo, :descricao, :imagem_url, :imagem_caminho, :ativo, :ordem)",
                ['titulo' => $titulo, 'descricao' => $descricao, 'imagem_url' => $imagem_url, 'imagem_caminho' => $imagem_caminho, 'ativo' => $ativo, 'ordem' => $ordem]
            );
        } catch (\Exception $e) {
            $id = $this->db->insert(
                "INSERT INTO minicursos (titulo, descricao, imagem_url, ativo, ordem) VALUES (:titulo, :descricao, :imagem_url, :ativo, :ordem)",
                ['titulo' => $titulo, 'descricao' => $descricao, 'imagem_url' => $imagem_url, 'ativo' => $ativo, 'ordem' => $ordem]
            );
        }
        try {
            $this->processarArquivosNovos($id);
        } catch (\Exception $e) {
        }
        $this->setFlashMessage('Minicurso criado.', 'success');
        $this->redirect('/admin/minicursos');
    }

    private function processarUploadCapa($minicursoId)
    {
        if (empty($_FILES['imagem_capa']['name']) || $_FILES['imagem_capa']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $ext = strtolower(pathinfo($_FILES['imagem_capa']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::$EXTENSOES_CAPA)) {
            return null;
        }
        $baseDir = defined('ROOT_PATH') ? (ROOT_PATH . '/' . self::$UPLOAD_CAPA_DIR) : (__DIR__ . '/../../../' . self::$UPLOAD_CAPA_DIR);
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }
        $nome = 'capa_' . ($minicursoId ?? time()) . '_' . time() . '.' . $ext;
        $caminhoRel = self::$UPLOAD_CAPA_DIR . $nome;
        $caminhoAbs = $baseDir . $nome;
        if (move_uploaded_file($_FILES['imagem_capa']['tmp_name'], $caminhoAbs)) {
            return $caminhoRel;
        }
        return null;
    }

    private function processarArquivosNovos($minicursoId)
    {
        $labels = $_POST['arquivos_label'] ?? [];
        $tipos = $_POST['arquivos_tipo'] ?? [];
        $urls = $_POST['arquivos_url'] ?? [];
        if (!is_array($labels)) {
            return;
        }
        $baseDir = defined('ROOT_PATH') ? (ROOT_PATH . '/' . self::$UPLOAD_ARQUIVOS_DIR) : (__DIR__ . '/../../../' . self::$UPLOAD_ARQUIVOS_DIR);
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }
        $ordem = 0;
        foreach ($labels as $i => $label) {
            $label = trim($label);
            if ($label === '') {
                continue;
            }
            $tipo = isset($tipos[$i]) && $tipos[$i] === 'upload' ? 'upload' : 'link';
            $url = isset($urls[$i]) ? trim($urls[$i]) : '';
            if ($tipo === 'link') {
                if ($url === '') {
                    continue;
                }
                $this->db->insert(
                    "INSERT INTO minicursos_arquivos (minicurso_id, tipo, url, label, ordem) VALUES (:mid, 'link', :url, :label, :ordem)",
                    ['mid' => $minicursoId, 'url' => $url, 'label' => $label, 'ordem' => $ordem]
                );
                $ordem++;
            } else {
                $key = 'arquivos_file_' . $i;
                if (empty($_FILES[$key]['name']) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
                    continue;
                }
                $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, self::$EXTENSOES_ARQUIVO)) {
                    continue;
                }
                $nome = 'arq_' . $minicursoId . '_' . time() . '_' . $i . '.' . $ext;
                $caminhoRel = self::$UPLOAD_ARQUIVOS_DIR . $nome;
                $caminhoAbs = $baseDir . $nome;
                if (move_uploaded_file($_FILES[$key]['tmp_name'], $caminhoAbs)) {
                    $this->db->insert(
                        "INSERT INTO minicursos_arquivos (minicurso_id, tipo, caminho, label, ordem) VALUES (:mid, 'upload', :caminho, :label, :ordem)",
                        ['mid' => $minicursoId, 'caminho' => $caminhoRel, 'label' => $label, 'ordem' => $ordem]
                    );
                    $ordem++;
                }
            }
        }
    }

    private function processarArquivosManterExcluir($minicursoId)
    {
        $manter = array_map('intval', array_filter($_POST['arquivos_manter'] ?? [], 'strlen'));
        $atuais = $this->db->fetchAll("SELECT id, caminho FROM minicursos_arquivos WHERE minicurso_id = :id", ['id' => $minicursoId]);
        $basePath = defined('ROOT_PATH') ? ROOT_PATH . '/' : (__DIR__ . '/../../../');
        foreach ($atuais as $a) {
            if (!in_array((int)$a['id'], $manter, true) && !empty($a['caminho'])) {
                $full = $basePath . $a['caminho'];
                if (file_exists($full)) {
                    @unlink($full);
                }
            }
            if (!in_array((int)$a['id'], $manter, true)) {
                $this->db->query("DELETE FROM minicursos_arquivos WHERE id = :aid", ['aid' => $a['id']]);
            }
        }
    }

    public function show($id)
    {
        $id = (int)$id;
        $minicurso = $this->db->fetch("SELECT * FROM minicursos WHERE id = :id", ['id' => $id]);
        if (!$minicurso) {
            $this->setFlashMessage('Minicurso não encontrado.', 'error');
            $this->redirect('/admin/minicursos');
            return;
        }
        $modulos = $this->db->fetchAll("SELECT * FROM minicursos_modulos WHERE minicurso_id = :id ORDER BY ordem, id", ['id' => $id]);
        foreach ($modulos as &$mod) {
            $mod['aulas'] = $this->db->fetchAll("SELECT * FROM minicursos_aulas WHERE modulo_id = :mid ORDER BY ordem, id", ['mid' => $mod['id']]);
        }
        unset($mod);
        $data = [
            'title' => $minicurso['titulo'] . ' - Módulos e Aulas',
            'user' => $this->auth->getUser(),
            'current_page' => 'minicursos',
            'minicurso' => $minicurso,
            'modulos' => $modulos,
            'csrf_token' => $this->generateCsrfToken()
        ];
        $this->viewWithLayout('admin', 'admin/minicursos/show', $data);
    }

    public function edit($id)
    {
        $id = (int)$id;
        $minicurso = $this->db->fetch("SELECT * FROM minicursos WHERE id = :id", ['id' => $id]);
        if (!$minicurso) {
            $this->setFlashMessage('Minicurso não encontrado.', 'error');
            $this->redirect('/admin/minicursos');
            return;
        }
        $arquivos = [];
        try {
            $arquivos = $this->db->fetchAll("SELECT * FROM minicursos_arquivos WHERE minicurso_id = :id ORDER BY ordem, id", ['id' => $id]);
        } catch (\Exception $e) {
            // Tabela pode não existir antes da migration
        }
        $data = [
            'title' => 'Editar Minicurso',
            'user' => $this->auth->getUser(),
            'current_page' => 'minicursos',
            'minicurso' => $minicurso,
            'arquivos' => $arquivos,
            'csrf_token' => $this->generateCsrfToken()
        ];
        $this->viewWithLayout('admin', 'admin/minicursos/edit', $data);
    }

    public function update($id)
    {
        $id = (int)$id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido', 'error');
            $this->redirect('/admin/minicursos');
            return;
        }
        $minicurso = $this->db->fetch("SELECT * FROM minicursos WHERE id = :id", ['id' => $id]);
        if (!$minicurso) {
            $this->redirect('/admin/minicursos');
            return;
        }
        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $imagem_url = trim($_POST['imagem_url'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $ordem = (int)($_POST['ordem'] ?? 0);
        if ($titulo === '') {
            $this->setFlashMessage('Título é obrigatório.', 'error');
            $this->redirect('/admin/minicursos/editar/' . $id);
            return;
        }
        $imagem_caminho = $minicurso['imagem_caminho'] ?? null;
        $novoUpload = $this->processarUploadCapa($id);
        if ($novoUpload !== null) {
            $basePath = defined('ROOT_PATH') ? ROOT_PATH . '/' : (__DIR__ . '/../../../');
            if ($imagem_caminho && file_exists($basePath . $imagem_caminho)) {
                @unlink($basePath . $imagem_caminho);
            }
            $imagem_caminho = $novoUpload;
            $imagem_url = '';
        }
        try {
            $this->db->query(
                "UPDATE minicursos SET titulo = :titulo, descricao = :descricao, imagem_url = :imagem_url, imagem_caminho = :imagem_caminho, ativo = :ativo, ordem = :ordem WHERE id = :id",
                ['titulo' => $titulo, 'descricao' => $descricao, 'imagem_url' => $imagem_url, 'imagem_caminho' => $imagem_caminho, 'ativo' => $ativo, 'ordem' => $ordem, 'id' => $id]
            );
        } catch (\Exception $e) {
            $this->db->query(
                "UPDATE minicursos SET titulo = :titulo, descricao = :descricao, imagem_url = :imagem_url, ativo = :ativo, ordem = :ordem WHERE id = :id",
                ['titulo' => $titulo, 'descricao' => $descricao, 'imagem_url' => $imagem_url, 'ativo' => $ativo, 'ordem' => $ordem, 'id' => $id]
            );
        }
        try {
            $this->processarArquivosManterExcluir($id);
            $this->processarArquivosNovos($id);
        } catch (\Exception $e) {
        }
        $this->setFlashMessage('Minicurso atualizado.', 'success');
        $this->redirect('/admin/minicursos/' . $id);
    }

    public function delete($id)
    {
        $id = (int)$id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        $this->db->query("DELETE FROM minicursos WHERE id = :id", ['id' => $id]);
        $this->setFlashMessage('Minicurso excluído.', 'success');
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->json(['success' => true]);
            return;
        }
        $this->redirect('/admin/minicursos');
    }

    // --- Módulos ---
    public function moduloStore()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        $minicurso_id = (int)($_POST['minicurso_id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $ordem = (int)($_POST['ordem'] ?? 0);
        if (!$minicurso_id || $titulo === '') {
            $this->json(['error' => 'Minicurso e título obrigatórios'], 400);
            return;
        }
        $this->db->insert(
            "INSERT INTO minicursos_modulos (minicurso_id, titulo, descricao, ordem) VALUES (:mid, :titulo, :descricao, :ordem)",
            ['mid' => $minicurso_id, 'titulo' => $titulo, 'descricao' => $descricao, 'ordem' => $ordem]
        );
        $this->setFlashMessage('Módulo adicionado.', 'success');
        $this->redirect('/admin/minicursos/' . $minicurso_id);
    }

    public function moduloUpdate()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $minicurso_id = (int)($_POST['minicurso_id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $ordem = (int)($_POST['ordem'] ?? 0);
        if (!$id || $titulo === '') {
            $this->redirect('/admin/minicursos');
            return;
        }
        $this->db->query(
            "UPDATE minicursos_modulos SET titulo = :titulo, descricao = :descricao, ordem = :ordem WHERE id = :id",
            ['titulo' => $titulo, 'descricao' => $descricao, 'ordem' => $ordem, 'id' => $id]
        );
        $this->setFlashMessage('Módulo atualizado.', 'success');
        $this->redirect('/admin/minicursos/' . ($minicurso_id ?: $this->db->fetch("SELECT minicurso_id FROM minicursos_modulos WHERE id = :id", ['id' => $id])['minicurso_id']));
    }

    public function moduloDelete()
    {
        $id = (int)($_POST['id'] ?? 0);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        $mod = $this->db->fetch("SELECT minicurso_id FROM minicursos_modulos WHERE id = :id", ['id' => $id]);
        if ($mod) {
            $this->db->query("DELETE FROM minicursos_modulos WHERE id = :id", ['id' => $id]);
            $this->setFlashMessage('Módulo excluído.', 'success');
            $this->redirect('/admin/minicursos/' . $mod['minicurso_id']);
            return;
        }
        $this->redirect('/admin/minicursos');
    }

    // --- Aulas ---
    public function aulaStore()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        $modulo_id = (int)($_POST['modulo_id'] ?? 0);
        $minicurso_id = (int)($_POST['minicurso_id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $tipo = in_array($_POST['tipo'] ?? '', ['video', 'slides', 'pdf', 'link', 'texto']) ? $_POST['tipo'] : 'link';
        $url_ou_caminho = trim($_POST['url_ou_caminho'] ?? '');
        $conteudo_html = $_POST['conteudo_html'] ?? '';
        $link_nome = trim($_POST['link_nome'] ?? '') ?: null;
        $duracao_minutos = !empty($_POST['duracao_minutos']) ? (int)$_POST['duracao_minutos'] : null;
        $ordem = (int)($_POST['ordem'] ?? 0);
        if (!$modulo_id || $titulo === '') {
            $this->setFlashMessage('Módulo e título são obrigatórios.', 'error');
            $this->redirect('/admin/minicursos/' . $minicurso_id);
            return;
        }
        if ($tipo === 'texto') {
            $url_ou_caminho = '';
        } else {
            $uploadPath = $this->processarUploadAula(null);
            if ($uploadPath !== null) {
                $url_ou_caminho = $uploadPath;
            } elseif ($url_ou_caminho === '') {
                $this->setFlashMessage('Informe a URL/caminho ou envie um arquivo para a aula.', 'error');
                $this->redirect('/admin/minicursos/' . $minicurso_id);
                return;
            }
        }
        try {
            $this->db->insert(
                "INSERT INTO minicursos_aulas (modulo_id, titulo, tipo, url_ou_caminho, link_nome, conteudo_html, duracao_minutos, ordem) VALUES (:mid, :titulo, :tipo, :url, :link_nome, :conteudo_html, :duracao, :ordem)",
                ['mid' => $modulo_id, 'titulo' => $titulo, 'tipo' => $tipo, 'url' => $url_ou_caminho, 'link_nome' => $link_nome, 'conteudo_html' => $conteudo_html, 'duracao' => $duracao_minutos, 'ordem' => $ordem]
            );
        } catch (\Exception $e) {
            $this->db->insert(
                "INSERT INTO minicursos_aulas (modulo_id, titulo, tipo, url_ou_caminho, link_nome, duracao_minutos, ordem) VALUES (:mid, :titulo, :tipo, :url, :link_nome, :duracao, :ordem)",
                ['mid' => $modulo_id, 'titulo' => $titulo, 'tipo' => $tipo, 'url' => $url_ou_caminho, 'link_nome' => $link_nome, 'duracao' => $duracao_minutos, 'ordem' => $ordem]
            );
        }
        $this->setFlashMessage('Aula adicionada.', 'success');
        $this->redirect('/admin/minicursos/' . $minicurso_id);
    }

    private function processarUploadAula($aulaId)
    {
        if (empty($_FILES['arquivo_aula']['name']) || $_FILES['arquivo_aula']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $ext = strtolower(pathinfo($_FILES['arquivo_aula']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::$EXTENSOES_AULA)) {
            return null;
        }
        $baseDir = defined('ROOT_PATH') ? (ROOT_PATH . '/' . self::$UPLOAD_AULAS_DIR) : (__DIR__ . '/../../../' . self::$UPLOAD_AULAS_DIR);
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }
        $nome = 'aula_' . ($aulaId ?? time()) . '_' . time() . '.' . $ext;
        $caminhoRel = self::$UPLOAD_AULAS_DIR . $nome;
        $caminhoAbs = $baseDir . $nome;
        if (move_uploaded_file($_FILES['arquivo_aula']['tmp_name'], $caminhoAbs)) {
            return $caminhoRel;
        }
        return null;
    }

    public function aulaUpdate()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect('/admin/minicursos');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $minicurso_id = (int)($_POST['minicurso_id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $tipo = in_array($_POST['tipo'] ?? '', ['video', 'slides', 'pdf', 'link', 'texto']) ? $_POST['tipo'] : 'link';
        $url_ou_caminho = trim($_POST['url_ou_caminho'] ?? '');
        $conteudo_html = $_POST['conteudo_html'] ?? '';
        $link_nome = trim($_POST['link_nome'] ?? '') ?: null;
        $duracao_minutos = !empty($_POST['duracao_minutos']) ? (int)$_POST['duracao_minutos'] : null;
        $ordem = (int)($_POST['ordem'] ?? 0);
        if (!$id || $titulo === '') {
            $this->redirect('/admin/minicursos/' . $minicurso_id);
            return;
        }
        if ($tipo === 'texto') {
            $url_ou_caminho = '';
        } else {
            $uploadPath = $this->processarUploadAula($id);
            if ($uploadPath !== null) {
                $aulaAtual = $this->db->fetch("SELECT url_ou_caminho FROM minicursos_aulas WHERE id = :id", ['id' => $id]);
                if ($aulaAtual && !empty($aulaAtual['url_ou_caminho']) && !preg_match('#^https?://#i', $aulaAtual['url_ou_caminho'])) {
                    $basePath = defined('ROOT_PATH') ? ROOT_PATH . '/' : (__DIR__ . '/../../../');
                    $oldFull = $basePath . $aulaAtual['url_ou_caminho'];
                    if (file_exists($oldFull)) {
                        @unlink($oldFull);
                    }
                }
                $url_ou_caminho = $uploadPath;
            }
        }
        try {
            $this->db->query(
                "UPDATE minicursos_aulas SET titulo = :titulo, tipo = :tipo, url_ou_caminho = :url, link_nome = :link_nome, conteudo_html = :conteudo_html, duracao_minutos = :duracao, ordem = :ordem WHERE id = :id",
                ['titulo' => $titulo, 'tipo' => $tipo, 'url' => $url_ou_caminho, 'link_nome' => $link_nome, 'conteudo_html' => $conteudo_html, 'duracao' => $duracao_minutos, 'ordem' => $ordem, 'id' => $id]
            );
        } catch (\Exception $e) {
            $this->db->query(
                "UPDATE minicursos_aulas SET titulo = :titulo, tipo = :tipo, url_ou_caminho = :url, link_nome = :link_nome, duracao_minutos = :duracao, ordem = :ordem WHERE id = :id",
                ['titulo' => $titulo, 'tipo' => $tipo, 'url' => $url_ou_caminho, 'link_nome' => $link_nome, 'duracao' => $duracao_minutos, 'ordem' => $ordem, 'id' => $id]
            );
        }
        $this->setFlashMessage('Aula atualizada.', 'success');
        $this->redirect('/admin/minicursos/' . $minicurso_id);
    }

    public function aulaDelete()
    {
        $id = (int)($_POST['id'] ?? 0);
        $minicurso_id = (int)($_POST['minicurso_id'] ?? 0);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        $aula = $this->db->fetch("SELECT ma.id, mm.minicurso_id FROM minicursos_aulas ma JOIN minicursos_modulos mm ON ma.modulo_id = mm.id WHERE ma.id = :id", ['id' => $id]);
        if ($aula) {
            $this->db->query("DELETE FROM minicursos_aulas WHERE id = :id", ['id' => $id]);
            $this->setFlashMessage('Aula excluída.', 'success');
            $this->redirect('/admin/minicursos/' . $aula['minicurso_id']);
            return;
        }
        $this->redirect('/admin/minicursos/' . $minicurso_id);
    }
}
}
