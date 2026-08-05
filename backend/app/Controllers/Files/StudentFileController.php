<?php
/**
 * EducaTudo - Módulo de Arquivos (Aluno)
 * Aluno acessa arquivos disponibilizados pela turma, com opção de download dos anexos.
 */

if (!class_exists('StudentFileController')) {
class StudentFileController extends BaseController
{
    private $auth;
    private $db;

    /**
     * Faz stream de uma URL assinada mantendo a navegação na rota interna.
     */
    private function streamSignedUrl(string $signedUrl, string $contentType, string $filename, string $disposition = 'inline'): bool
    {
        if ($signedUrl === '') {
            return false;
        }

        if (!headers_sent()) {
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '\\"', $filename) . '"');
            header('Cache-Control: private, max-age=3600');
        }

        $fp = @fopen($signedUrl, 'rb');
        if ($fp !== false) {
            while (!feof($fp)) {
                echo fread($fp, 8192);
            }
            fclose($fp);
            return true;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($signedUrl);
            if ($ch !== false) {
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $chunk) {
                    echo $chunk;
                    return strlen($chunk);
                });
                $ok = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return $ok !== false && $httpCode >= 200 && $httpCode < 300;
            }
        }

        return false;
    }

    private function anexoPathToMediaKey(string $caminho): string
    {
        $path = ltrim(str_replace('\\', '/', $caminho), '/');
        if (strpos($path, 'public/uploads/arquivos/') === 0) {
            return substr($path, strlen('public/uploads/arquivos/'));
        }
        if (strpos($path, 'arquivos/') === 0) {
            return substr($path, strlen('arquivos/'));
        }
        return basename($path);
    }

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        $user = $this->auth->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
        }
    }

    private function getAluno()
    {
        $user = $this->auth->getUser();
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome FROM alunos a LEFT JOIN turmas t ON a.turma_id = t.id WHERE a.id = :id",
            ['id' => $user['id']]
        );
        if (!$aluno || empty($aluno['turma_id'])) {
            $this->setFlashMessage('Você não está vinculado a uma turma.', 'error');
            $this->redirect('/dashboard');
            exit;
        }
        return $aluno;
    }

    /**
     * Lista publicações de arquivos da turma do aluno (com nome do professor, filtros e paginação)
     */
    public function index()
    {
        $this->listarArquivos(false);
    }

    /**
     * Lista publicações marcadas como recuperação.
     */
    public function recuperacao()
    {
        $this->listarArquivos(true);
    }

    /**
     * @param bool $somenteRecuperacao true = menu Recuperação; false = menu Arquivos
     */
    private function listarArquivos(bool $somenteRecuperacao): void
    {
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        if (LayoutHelper::get('module_aluno_arquivos', '1') !== '1') {
            $this->setFlashMessage('O módulo Arquivos está desabilitado.', 'error');
            $this->redirect('/dashboard');
            return;
        }
        $aluno = $this->getAluno();
        $turmaId = (int)$aluno['turma_id'];
        $alunoId = (int)$aluno['id'];

        $filtroMateria = isset($_GET['materia_id']) ? (int)$_GET['materia_id'] : null;
        $filtroProfessor = isset($_GET['professor_id']) ? (int)$_GET['professor_id'] : null;
        $filtroTitulo = isset($_GET['titulo']) ? trim((string)$_GET['titulo']) : '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        $filtroPasta = isset($_GET['pasta_id']) ? (int)$_GET['pasta_id'] : null;

        $visibilityCond = "((ma.aluno_id IS NULL AND (ma.turma_id = :turma_id OR EXISTS (SELECT 1 FROM modulos_arquivos_turmas mat WHERE mat.modulo_arquivo_id = ma.id AND mat.turma_id = :turma_id2))) OR ma.aluno_id = :aluno_id)";
        $paramsList = ['turma_id' => $turmaId, 'turma_id2' => $turmaId, 'aluno_id' => $alunoId];

        $where = [$visibilityCond];
        $params = ['turma_id' => $turmaId, 'turma_id2' => $turmaId, 'aluno_id' => $alunoId];

        $hasRecuperacaoCol = (bool)$this->db->fetch(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'modulos_arquivos' AND COLUMN_NAME = 'recuperacao'"
        );
        if ($hasRecuperacaoCol) {
            if ($somenteRecuperacao) {
                $where[] = "ma.recuperacao = 1";
            } else {
                $where[] = "(ma.recuperacao = 0 OR ma.recuperacao IS NULL)";
            }
        } elseif ($somenteRecuperacao) {
            // Coluna ainda não migrada: lista vazia em Recuperação
            $where[] = "1 = 0";
        }

        // Filtro de pasta
        $pastaAtual = null;
        $hasPastaCol = (bool)$this->db->fetch("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'modulos_arquivos' AND COLUMN_NAME = 'pasta_id'");
        $hasPastaTable = $hasPastaCol && (bool)$this->db->fetch("SHOW TABLES LIKE 'modulos_arquivos_pastas'");

        if ($hasPastaCol) {
            if ($filtroPasta !== null && $filtroPasta > 0) {
                $where[] = "ma.pasta_id = :pasta_id";
                $params['pasta_id'] = $filtroPasta;
                if ($hasPastaTable) {
                    $pastaAtual = $this->db->fetch("SELECT id, nome, cor FROM modulos_arquivos_pastas WHERE id = :id", ['id' => $filtroPasta]);
                }
            } elseif ($filtroPasta === null && !$filtroMateria && !$filtroProfessor && $filtroTitulo === '') {
                // Raiz: mostrar apenas arquivos sem pasta
                $where[] = "ma.pasta_id IS NULL";
            }
        }

        if ($filtroMateria > 0) {
            $where[] = "ma.materia_id = :materia_id";
            $params['materia_id'] = $filtroMateria;
        }
        if ($filtroProfessor > 0) {
            $where[] = "ma.professor_id = :professor_id";
            $params['professor_id'] = $filtroProfessor;
        }
        if ($filtroTitulo !== '') {
            $where[] = "(ma.titulo LIKE :titulo OR ma.descricao LIKE :titulo2)";
            $params['titulo'] = '%' . $filtroTitulo . '%';
            $params['titulo2'] = '%' . $filtroTitulo . '%';
        }
        $whereSql = implode(' AND ', $where);

        // Pastas visíveis (apenas na raiz, sem filtros ativos)
        $pastas = [];
        if ($hasPastaTable && $filtroPasta === null && !$filtroMateria && !$filtroProfessor && $filtroTitulo === '') {
            $pastaWhere = $visibilityCond;
            $pastaParams = $paramsList;
            if ($hasRecuperacaoCol) {
                if ($somenteRecuperacao) {
                    $pastaWhere .= " AND ma.recuperacao = 1";
                } else {
                    $pastaWhere .= " AND (ma.recuperacao = 0 OR ma.recuperacao IS NULL)";
                }
            } elseif ($somenteRecuperacao) {
                $pastaWhere .= " AND 1 = 0";
            }
            $pastas = $this->db->fetchAll(
                "SELECT p.id, p.nome, p.cor,
                        COUNT(ma.id) as total_arquivos
                 FROM modulos_arquivos_pastas p
                 INNER JOIN modulos_arquivos ma ON ma.pasta_id = p.id
                 WHERE {$pastaWhere}
                 GROUP BY p.id, p.nome, p.cor
                 ORDER BY p.ordem ASC, p.nome ASC",
                $pastaParams
            );
        }

        $total = (int)$this->db->fetch(
            "SELECT COUNT(*) as c FROM modulos_arquivos ma WHERE {$whereSql}",
            $params
        )['c'];

        $lista = $this->db->fetchAll(
            "SELECT ma.id, ma.titulo, ma.descricao, ma.created_at, m.nome as materia_nome,
             p.nome as professor_nome,
             (SELECT COUNT(*) FROM modulos_arquivos_anexos WHERE modulo_arquivo_id = ma.id) as total_anexos
             FROM modulos_arquivos ma
             LEFT JOIN materias m ON ma.materia_id = m.id
             LEFT JOIN professores p ON ma.professor_id = p.id
             WHERE {$whereSql}
             ORDER BY ma.created_at DESC
             LIMIT " . (int)$perPage . " OFFSET " . (int)$offset,
            $params
        );

        $materiasWhere = $visibilityCond;
        $professoresWhere = $visibilityCond;
        $filtroParams = $paramsList;
        if ($hasRecuperacaoCol) {
            if ($somenteRecuperacao) {
                $materiasWhere .= " AND ma.recuperacao = 1";
                $professoresWhere .= " AND ma.recuperacao = 1";
            } else {
                $materiasWhere .= " AND (ma.recuperacao = 0 OR ma.recuperacao IS NULL)";
                $professoresWhere .= " AND (ma.recuperacao = 0 OR ma.recuperacao IS NULL)";
            }
        } elseif ($somenteRecuperacao) {
            $materiasWhere .= " AND 1 = 0";
            $professoresWhere .= " AND 1 = 0";
        }

        $materias = $this->db->fetchAll(
            "SELECT DISTINCT m.id, m.nome FROM materias m
             INNER JOIN modulos_arquivos ma ON ma.materia_id = m.id
             WHERE {$materiasWhere} ORDER BY m.nome",
            $filtroParams
        );
        $professores = $this->db->fetchAll(
            "SELECT DISTINCT p.id, p.nome FROM professores p
             INNER JOIN modulos_arquivos ma ON ma.professor_id = p.id
             WHERE {$professoresWhere} ORDER BY p.nome",
            $filtroParams
        );

        $basePath = $somenteRecuperacao ? '/aluno/recuperacao' : '/aluno/arquivos';
        $data = [
            'title' => ($somenteRecuperacao ? 'Recuperação' : 'Arquivos') . ' - EducaTudo',
            'user' => $this->auth->getUser(),
            'lista' => $lista,
            'pastas' => $pastas,
            'pasta_atual' => $pastaAtual,
            'filtro_pasta_id' => $filtroPasta,
            'current_page' => $somenteRecuperacao ? 'recuperacao' : 'arquivos',
            'modo_recuperacao' => $somenteRecuperacao,
            'base_path' => $basePath,
            'filtro_materia_id' => $filtroMateria,
            'filtro_professor_id' => $filtroProfessor,
            'filtro_titulo' => $filtroTitulo,
            'materias' => $materias,
            'professores' => $professores,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $total > 0 ? (int)ceil($total / $perPage) : 1
        ];
        $this->viewWithLayout('student', 'student/arquivos/index', $data);
    }

    /**
     * Ver publicação com lista de anexos (download habilitado para todos os tipos).
     */
    public function ver()
    {
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        if (LayoutHelper::get('module_aluno_arquivos', '1') !== '1') {
            $this->setFlashMessage('O módulo Arquivos está desabilitado.', 'error');
            $this->redirect('/dashboard');
            return;
        }
        $id = (int)($_GET['id'] ?? 0);
        if (preg_match('#/arquivos/ver/(\d+)#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $id = (int)$m[1];
        }
        if (!$id) {
            $this->setFlashMessage('Publicação não encontrada.', 'error');
            $this->redirect('/aluno/arquivos');
            return;
        }
        $aluno = $this->getAluno();
        $pub = $this->db->fetch(
            "SELECT ma.*, m.nome as materia_nome, p.nome as professor_nome FROM modulos_arquivos ma LEFT JOIN materias m ON ma.materia_id = m.id LEFT JOIN professores p ON ma.professor_id = p.id WHERE ma.id = :id AND ((ma.aluno_id IS NULL AND (ma.turma_id = :turma_id OR EXISTS (SELECT 1 FROM modulos_arquivos_turmas mat WHERE mat.modulo_arquivo_id = ma.id AND mat.turma_id = :turma_id2))) OR ma.aluno_id = :aluno_id)",
            ['id' => $id, 'turma_id' => $aluno['turma_id'], 'turma_id2' => $aluno['turma_id'], 'aluno_id' => $aluno['id']]
        );
        if (!$pub) {
            $this->setFlashMessage('Publicação não encontrada.', 'error');
            $this->redirect('/aluno/arquivos');
            return;
        }
        $anexos = $this->db->fetchAll("SELECT * FROM modulos_arquivos_anexos WHERE modulo_arquivo_id = :id ORDER BY ordem", ['id' => $id]);
        $videos = [];
        try {
            $videos = $this->db->fetchAll("SELECT * FROM modulos_arquivos_videos WHERE modulo_arquivo_id = :id ORDER BY ordem", ['id' => $id]);
            foreach ($videos as &$v) {
                $v['embed_url'] = self::urlParaEmbed((string)$v['url']);
            }
            unset($v);
        } catch (\Throwable $e) {
        }
        $modoRecuperacao = !empty($pub['recuperacao']);
        $data = [
            'title' => $pub['titulo'] . ' - ' . ($modoRecuperacao ? 'Recuperação' : 'Arquivos'),
            'user' => $this->auth->getUser(),
            'pub' => $pub,
            'anexos' => $anexos,
            'videos' => $videos,
            'modo_recuperacao' => $modoRecuperacao,
            'current_page' => $modoRecuperacao ? 'recuperacao' : 'arquivos'
        ];
        $this->viewWithLayout('student', 'student/arquivos/ver', $data);
    }

    /**
     * Converte URL de vídeo (YouTube, Vimeo, etc.) para URL de embed.
     */
    public static function urlParaEmbed(string $url): string
    {
        $url = trim($url);
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([a-zA-Z0-9_-]+)#', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return $url;
    }

    /**
     * Stream/Download do anexo.
     * ?download=1 força baixar no navegador.
     */
    public function visualizarAnexo()
    {
        $id = (int)($_GET['id'] ?? 0);
        if (preg_match('#/arquivos/visualizar/(\d+)#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $id = (int)$m[1];
        }
        if (!$id) {
            http_response_code(404);
            echo 'Anexo não encontrado';
            exit;
        }
        $anexo = $this->db->fetch(
            "SELECT aa.*, aa.modulo_arquivo_id FROM modulos_arquivos_anexos aa WHERE aa.id = :id",
            ['id' => $id]
        );
        if (!$anexo) {
            http_response_code(404);
            echo 'Anexo não encontrado';
            exit;
        }

        $embedTokenValid = false;
        $embed = $_GET['embed'] ?? '';
        $expires = isset($_GET['expires']) ? (int)$_GET['expires'] : 0;
        $token = (string)($_GET['token'] ?? '');
        if ($embed === '1' && $expires > time() && $token !== '') {
            $secret = $this->config['security']['entra_como_secret'] ?? hash('sha256', 'embed');
            $expected = hash_hmac('sha256', $id . '.' . $expires, $secret);
            if (hash_equals($expected, $token)) {
                $embedTokenValid = true;
            }
        }

        if (!$embedTokenValid) {
            $aluno = $this->auth->getUser();
            if (!$aluno || $aluno['tipo'] !== 'aluno') {
                http_response_code(403);
                exit;
            }
            $alunoRow = $this->db->fetch("SELECT id, turma_id FROM alunos WHERE id = :id", ['id' => $aluno['id']]);
            if (!$alunoRow || empty($alunoRow['turma_id'])) {
                http_response_code(403);
                exit;
            }
            $tid = (int)$alunoRow['turma_id'];
            $aid = (int)$alunoRow['id'];
            $podeVer = $this->db->fetch(
                "SELECT 1 FROM modulos_arquivos ma WHERE ma.id = :id AND ((ma.aluno_id IS NULL AND (ma.turma_id = :tid OR EXISTS (SELECT 1 FROM modulos_arquivos_turmas mat WHERE mat.modulo_arquivo_id = ma.id AND mat.turma_id = :tid2))) OR ma.aluno_id = :aid)",
                ['id' => $anexo['modulo_arquivo_id'], 'tid' => $tid, 'tid2' => $tid, 'aid' => $aid]
            );
            if (!$podeVer) {
                http_response_code(404);
                echo 'Anexo não encontrado';
                exit;
            }
        }

        $mimes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp',
            'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg',
            'mp3' => 'audio/mpeg', 'wav' => 'audio/wav',
            'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain', 'csv' => 'text/csv'
        ];
        $ext = strtolower((string)$anexo['extensao']);
        $contentType = $mimes[$ext] ?? 'application/octet-stream';
        $download = (!empty($_GET['download']) && $_GET['download'] === '1');
        $disposition = $download ? 'attachment' : 'inline';

        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $mediaKey = $this->anexoPathToMediaKey((string)$anexo['caminho']);
        if ($media->isS3() && $mediaKey !== '') {
            $signedUrl = $media->getViewUrl('arquivos', $mediaKey, (string)$anexo['nome_original'], 900);
            if (!empty($signedUrl) && $this->streamSignedUrl($signedUrl, $contentType, (string)$anexo['nome_original'], $disposition)) {
                exit;
            }
            if (!$download && $media->streamInline('arquivos', $mediaKey, (string)$anexo['nome_original'], $contentType)) {
                exit;
            }
        }

        $basePath = defined('ROOT_PATH') ? ROOT_PATH . '/' : (__DIR__ . '/../../../');
        $fullPath = $basePath . ltrim((string)$anexo['caminho'], '/');
        if ((!file_exists($fullPath) || !is_readable($fullPath)) && $mediaKey !== '') {
            // Fallback legado para caminhos já convertidos para "arquivos/nome.ext"
            $fullPath = $basePath . 'public/uploads/arquivos/' . $mediaKey;
        }
        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            exit;
        }
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '\\"', $anexo['nome_original']) . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: private, max-age=3600');
        readfile($fullPath);
        exit;
    }

    /**
     * Página que abre o anexo em um iframe/embed (visualização na plataforma)
     */
    public function abrir()
    {
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        if (LayoutHelper::get('module_aluno_arquivos', '1') !== '1') {
            $this->setFlashMessage('O módulo Arquivos está desabilitado.', 'error');
            $this->redirect('/dashboard');
            return;
        }
        $id = (int)($_GET['id'] ?? 0);
        if (preg_match('#/arquivos/abrir/(\d+)#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $id = (int)$m[1];
        }
        if (!$id) {
            $this->setFlashMessage('Anexo não encontrado.', 'error');
            $this->redirect('/aluno/arquivos');
            return;
        }
        $aluno = $this->getAluno();
        $anexo = $this->db->fetch(
            "SELECT aa.*, aa.modulo_arquivo_id FROM modulos_arquivos_anexos aa WHERE aa.id = :id",
            ['id' => $id]
        );
        if (!$anexo) {
            $this->setFlashMessage('Anexo não encontrado.', 'error');
            $this->redirect('/aluno/arquivos');
            return;
        }
        $tid = (int)$aluno['turma_id'];
        $aid = (int)$aluno['id'];
        $podeVer = $this->db->fetch(
            "SELECT 1 FROM modulos_arquivos ma WHERE ma.id = :id AND ((ma.aluno_id IS NULL AND (ma.turma_id = :tid OR EXISTS (SELECT 1 FROM modulos_arquivos_turmas mat WHERE mat.modulo_arquivo_id = ma.id AND mat.turma_id = :tid2))) OR ma.aluno_id = :aid)",
            ['id' => $anexo['modulo_arquivo_id'], 'tid' => $tid, 'tid2' => $tid, 'aid' => $aid]
        );
        if (!$podeVer) {
            $this->setFlashMessage('Anexo não encontrado.', 'error');
            $this->redirect('/aluno/arquivos');
            return;
        }
        $urlVisualizar = URL . '/aluno/arquivos/visualizar/' . $id;
        $urlDownload = $urlVisualizar . '?download=1';
        $ext = strtolower($anexo['extensao']);
        $ehVideo = in_array($ext, ['mp4', 'webm', 'ogg']);
        $podeEmbed = $ehVideo || in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
        $ehOffice = in_array($ext, ['ppt', 'pptx', 'doc', 'docx', 'xls', 'xlsx']);
        $urlEmbedPublic = '';
        if ($ehOffice) {
            $expires = time() + 600;
            $secret = $this->config['security']['entra_como_secret'] ?? hash('sha256', 'embed');
            $token = hash_hmac('sha256', $id . '.' . $expires, $secret);
            $urlEmbedPublic = URL . '/aluno/arquivos/visualizar/' . $id . '?embed=1&expires=' . $expires . '&token=' . urlencode($token);
        }
        $data = [
            'title' => $anexo['nome_original'] . ' - Visualizar',
            'user' => $this->auth->getUser(),
            'anexo' => $anexo,
            'url_visualizar' => $urlVisualizar,
            'url_download' => $urlDownload,
            'url_embed_public' => $urlEmbedPublic,
            'pode_embed' => $podeEmbed,
            'eh_video' => $ehVideo,
            'eh_office' => $ehOffice,
            'current_page' => 'arquivos'
        ];
        $this->viewWithLayout('student', 'student/arquivos/abrir', $data);
    }
}
}
