<?php
/**
 * EducaTudo - Redação Livre: professor sobe redação(ões), vincula aluno (nome/arquivo/sala), corrige.
 * Sem necessidade de criar proposta ou jornada. Módulo habilitável no Master.
 */

require_once __DIR__ . '/../../Models/RedacaoLivreEnvio.php';
require_once __DIR__ . '/../../Models/RedacaoLivreCorrecao.php';
require_once __DIR__ . '/../../Services/EssayAIService.php';
require_once __DIR__ . '/../../Services/MediaStorageService.php';
require_once __DIR__ . '/../../Core/Logger.php';
require_once __DIR__ . '/../../Core/LayoutHelper.php';

if (!class_exists('TeacherRedacaoLivreController')) {
class TeacherRedacaoLivreController extends BaseController
{
    private $authManager;
    private $db;
    private $envioModel;
    private $correcaoModel;

    /** Critérios padrão ENEM (quando não há proposta) */
    private static function defaultCriteria()
    {
        return [
            ['slug' => 'competencia_1', 'name' => 'Domínio da norma padrão', 'max_score' => 200, 'order_position' => 1],
            ['slug' => 'competencia_2', 'name' => 'Compreensão do tema', 'max_score' => 200, 'order_position' => 2],
            ['slug' => 'competencia_3', 'name' => 'Seleção de argumentos', 'max_score' => 200, 'order_position' => 3],
            ['slug' => 'competencia_4', 'name' => 'Conhecimento dos mecanismos linguísticos', 'max_score' => 200, 'order_position' => 4],
            ['slug' => 'competencia_5', 'name' => 'Proposta de intervenção', 'max_score' => 200, 'order_position' => 5],
        ];
    }

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        $this->envioModel = new RedacaoLivreEnvio();
        $this->correcaoModel = new RedacaoLivreCorrecao();

        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
        }
        $user = $this->authManager->getUser();
        if ($user && $user['tipo'] !== 'professor') {
            $this->redirect(URL . '/professor/dashboard');
        }
    }

    private function getTeacherId()
    {
        $user = $this->authManager->getUser();
        return $user ? (int) $user['id'] : 0;
    }

    private function ensureRedacaoLivreEnabled($json = false)
    {
        $enabled = LayoutHelper::get('module_professor_redacao_livre', '0') === '1';
        if (!$enabled) {
            if ($json) {
                $this->json(['error' => 'O módulo Redação Livre está desabilitado.'], 403);
                return;
            }
            $this->setFlashMessage('O módulo Redação Livre está desabilitado.', 'error');
            $this->redirect(URL . '/professor/dashboard');
        }
    }

    private function normalizeNameForMatch($s)
    {
        $s = mb_strtolower(trim((string) $s), 'UTF-8');
        $s = preg_replace('/\s+/', ' ', $s);
        $map = ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c'];
        foreach ($map as $from => $to) {
            $s = str_replace($from, $to, $s);
        }
        return $s;
    }

    private function nameFromFilename($filename)
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/[\_\-]+/', ' ', $name);
        return $this->normalizeNameForMatch($name);
    }

    /** Busca aluno por nome (no arquivo) nas turmas do professor */
    private function findStudentByNameHint($nameHint)
    {
        if (trim($nameHint) === '') return null;
        $teacherId = $this->getTeacherId();
        $prof = $this->db->fetch("SELECT turmas FROM professores WHERE id = :id", ['id' => $teacherId]);
        $turmaIds = json_decode($prof['turmas'] ?? '[]', true);
        if (empty($turmaIds)) return null;
        $placeholders = implode(',', array_map('intval', $turmaIds));
        $alunos = $this->db->fetchAll(
            "SELECT a.id, a.nome FROM alunos a WHERE a.turma_id IN ($placeholders) AND a.ativo = 1"
        );
        $hintNorm = $this->normalizeNameForMatch($nameHint);
        foreach ($alunos as $a) {
            $nomeNorm = $this->normalizeNameForMatch($a['nome']);
            if ($nomeNorm === $hintNorm) return (int) $a['id'];
            if (strpos($hintNorm, $nomeNorm) !== false || strpos($nomeNorm, $hintNorm) !== false) return (int) $a['id'];
            $words = array_filter(explode(' ', $nomeNorm));
            if (count($words) >= 2) {
                $surname = end($words);
                $firstNames = array_slice($words, 0, -1);
                $hasSurname = strpos($hintNorm, $surname) !== false;
                $hasFirst = false;
                foreach ($firstNames as $w) {
                    if (strlen($w) > 2 && strpos($hintNorm, $w) !== false) { $hasFirst = true; break; }
                }
                if ($hasSurname && $hasFirst) return (int) $a['id'];
            }
        }
        return null;
    }

    public function index()
    {
        $this->ensureRedacaoLivreEnabled();
        $user = $this->authManager->getUser();
        $teacherId = $this->getTeacherId();
        $envios = $this->envioModel->findByTeacher($teacherId);
        $turmas = [];
        $prof = $this->db->fetch("SELECT turmas FROM professores WHERE id = :id", ['id' => $teacherId]);
        $turmaIds = json_decode($prof['turmas'] ?? '[]', true);
        if (!empty($turmaIds)) {
            $placeholders = implode(',', array_map('intval', $turmaIds));
            $turmas = $this->db->fetchAll("SELECT id, nome FROM turmas WHERE id IN ($placeholders) ORDER BY nome");
        }
        $this->viewWithLayout('professor', 'teacher/redacao_livre/index', [
            'title' => 'Redação Livre',
            'user' => $user,
            'envios' => $envios,
            'turmas' => $turmas,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'redacao-livre',
        ]);
    }

    /** POST: upload uma ou várias redações (arquivo ou texto). student_name opcional; pode sugerir por nome do arquivo. */
    public function upload()
    {
        $this->ensureRedacaoLivreEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        $teacherId = $this->getTeacherId();
        $config = $this->config ?? [];
        $media = new MediaStorageService($config);
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        $results = [];

        // Upload por arquivo(s)
        $files = [];
        if (!empty($_FILES['arquivos']['name'])) {
            $names = is_array($_FILES['arquivos']['name']) ? $_FILES['arquivos']['name'] : [$_FILES['arquivos']['name']];
            $tmps = is_array($_FILES['arquivos']['tmp_name']) ? $_FILES['arquivos']['tmp_name'] : [$_FILES['arquivos']['tmp_name']];
            $errors = is_array($_FILES['arquivos']['error']) ? $_FILES['arquivos']['error'] : [$_FILES['arquivos']['error']];
            $types = is_array($_FILES['arquivos']['type']) ? $_FILES['arquivos']['type'] : [$_FILES['arquivos']['type']];
            for ($i = 0; $i < count($names); $i++) {
                if ($errors[$i] === UPLOAD_ERR_OK && $names[$i] !== '') {
                    $files[] = ['name' => $names[$i], 'tmp_name' => $tmps[$i], 'type' => $types[$i], 'size' => $_FILES['arquivos']['size'][$i] ?? 0];
                }
            }
        }
        if (!empty($_FILES['arquivo']['name']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
            $files[] = ['name' => $_FILES['arquivo']['name'], 'tmp_name' => $_FILES['arquivo']['tmp_name'], 'type' => $_FILES['arquivo']['type'], 'size' => $_FILES['arquivo']['size'] ?? 0];
        }

        foreach ($files as $file) {
            $mime = $file['type'];
            if (!in_array($mime, $allowedMimes)) {
                $results[] = ['file' => $file['name'], 'ok' => false, 'error' => 'Tipo não permitido'];
                continue;
            }
            if (($file['size'] ?? 0) > 15 * 1024 * 1024) {
                $results[] = ['file' => $file['name'], 'ok' => false, 'error' => 'Arquivo muito grande'];
                continue;
            }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $storageKey = 'livre/t' . $teacherId . '/' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
            if (!$media->put('essays_submissions', $storageKey, $file['tmp_name'], $mime)) {
                $results[] = ['file' => $file['name'], 'ok' => false, 'error' => 'Erro ao salvar'];
                continue;
            }
            $ocrText = '';
            $isImage = in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($isImage) {
                try {
                    require_once __DIR__ . '/../../Services/OpenAIService.php';
                    $imageData = base64_encode(file_get_contents($file['tmp_name']));
                    $openai = new \App\Services\OpenAIService();
                    $prompt = 'Transcreva todo o texto presente nesta imagem. Retorne apenas o texto transcrito, sem comentários adicionais.';
                    set_time_limit(300);
                    $ocrText = $openai->analyzeImage($imageData, $prompt);
                    $ocrText = is_string($ocrText) ? trim($ocrText) : '';
                } catch (\Exception $e) {
                    $ocrText = '';
                }
            }
            $studentName = trim($_POST['student_name'] ?? '');
            $studentId = isset($_POST['student_id']) ? (int) $_POST['student_id'] : null;
            $turmaId = isset($_POST['turma_id']) ? (int) $_POST['turma_id'] : null;
            if ($studentName === '' && $studentId === null) {
                $nameHint = $this->nameFromFilename($file['name']);
                if ($nameHint !== '') {
                    $matched = $this->findStudentByNameHint($nameHint);
                    if ($matched) {
                        $studentId = $matched;
                        $aluno = $this->db->fetch("SELECT nome FROM alunos WHERE id = ?", [$studentId]);
                        $studentName = $aluno['nome'] ?? '';
                    } else {
                        $studentName = preg_replace('/[\_\-]+/', ' ', pathinfo($file['name'], PATHINFO_FILENAME));
                    }
                }
            }
            $envioId = $this->envioModel->create([
                'teacher_id' => $teacherId,
                'student_name' => $studentName ?: null,
                'student_id' => $studentId,
                'turma_id' => $turmaId,
                'original_filename' => $file['name'],
                'content_image_path' => $storageKey,
                'content_text' => $ocrText ?: null,
                'ocr_text' => $ocrText ?: null,
            ]);
            $results[] = ['file' => $file['name'], 'ok' => true, 'envio_id' => $envioId];
        }

        // Texto puro (sem arquivo)
        $texto = trim($_POST['content_text'] ?? '');
        if ($texto !== '' && empty($files)) {
            $studentName = trim($_POST['student_name'] ?? '');
            $studentId = isset($_POST['student_id']) ? (int) $_POST['student_id'] : null;
            $turmaId = isset($_POST['turma_id']) ? (int) $_POST['turma_id'] : null;
            $envioId = $this->envioModel->create([
                'teacher_id' => $teacherId,
                'student_name' => $studentName ?: null,
                'student_id' => $studentId,
                'turma_id' => $turmaId,
                'original_filename' => null,
                'content_image_path' => null,
                'content_text' => $texto,
                'ocr_text' => null,
            ]);
            $results[] = ['file' => 'texto', 'ok' => true, 'envio_id' => $envioId];
        }

        if (empty($results)) {
            $this->json(['error' => 'Nenhum arquivo ou texto enviado.', 'results' => []], 400);
            return;
        }
        $this->json(['success' => true, 'message' => 'Envio(s) registrado(s).', 'results' => $results]);
    }

    /** GET: formulário de correção de um envio */
    public function correctForm($envioId)
    {
        $this->ensureRedacaoLivreEnabled();
        $user = $this->authManager->getUser();
        $teacherId = $this->getTeacherId();
        $envio = $this->envioModel->findByIdAndTeacher($envioId, $teacherId);
        if (!$envio) {
            $this->setFlashMessage('Envio não encontrado', 'error');
            $this->redirect(URL . '/professor/redacao-livre');
        }
        $correction = $this->correcaoModel->findByEnvio($envioId);
        $criteria = self::defaultCriteria();
        $displayName = $envio['student_name'] ?: ($envio['aluno_nome'] ?? 'Sem nome');
        $this->viewWithLayout('professor', 'teacher/redacao_livre/correct', [
            'title' => 'Corrigir redação - ' . $displayName,
            'user' => $user,
            'envio' => $envio,
            'correction' => $correction,
            'criteria' => $criteria,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'redacao-livre',
        ]);
    }

    /** GET: visualizar arquivo original do envio */
    public function viewOriginal($envioId)
    {
        $this->ensureRedacaoLivreEnabled();
        $teacherId = $this->getTeacherId();
        $envio = $this->envioModel->findByIdAndTeacher($envioId, $teacherId);
        if (!$envio || empty($envio['content_image_path'])) {
            http_response_code(404);
            exit('Arquivo não encontrado');
        }
        $pathOrKey = $envio['content_image_path'];
        $config = $this->config ?? [];
        $media = new MediaStorageService($config);
        if ($media->isS3()) {
            $url = $media->getViewUrl('essays_submissions', $pathOrKey, basename($pathOrKey));
            if ($url) { header('Location: ' . $url); exit; }
        }
        $localPath = $media->getLocalPath('essays_submissions', $pathOrKey);
        if (!$localPath || !is_file($localPath)) {
            http_response_code(404);
            exit('Arquivo não encontrado');
        }
        $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
        $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . addslashes(basename($pathOrKey)) . '"');
        readfile($localPath);
        exit;
    }

    /** POST: atualizar texto do envio */
    public function updateTexto($envioId)
    {
        $this->ensureRedacaoLivreEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        $teacherId = $this->getTeacherId();
        $envio = $this->envioModel->findByIdAndTeacher($envioId, $teacherId);
        if (!$envio) {
            $this->json(['error' => 'Envio não encontrado'], 404);
            return;
        }
        $text = trim($_POST['content_text'] ?? '');
        $this->envioModel->update($envioId, ['content_text' => $text]);
        $this->json(['success' => true]);
    }

    /** POST: enfileira correção por IA para uma redação livre (assíncrono) */
    public function runAICorrection($envioId)
    {
        $this->ensureRedacaoLivreEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        $teacherId = $this->getTeacherId();
        $envio = $this->envioModel->findByIdAndTeacher($envioId, $teacherId);
        if (!$envio) {
            $this->json(['error' => 'Envio não encontrado'], 404);
            return;
        }
        $text = !empty($envio['content_text']) ? $envio['content_text'] : ($envio['ocr_text'] ?? '');
        if (trim($text) === '') {
            $this->json(['error' => 'Não há texto para corrigir. Edite o texto da redação antes.'], 400);
            return;
        }

        require_once __DIR__ . '/../../Services/AIJobService.php';
        $jobId = \App\Services\AIJobService::enqueue(
            'corrigir_essay_livre',
            [
                'envio_id'    => (int) $envioId,
                'criteria'    => self::defaultCriteria(),
                'prompt_text' => 'Corrija esta redação segundo as competências do ENEM. Atribua nota de 0 a 200 para cada uma das 5 competências e forneça feedback e sugestões de melhoria.',
            ],
            $teacherId,
            'professor'
        );

        $this->json(['job_id' => $jobId]);
    }

    /** POST: salvar correção do professor */
    public function saveCorrection($envioId)
    {
        $this->ensureRedacaoLivreEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        $teacherId = $this->getTeacherId();
        $envio = $this->envioModel->findByIdAndTeacher($envioId, $teacherId);
        if (!$envio) {
            $this->json(['error' => 'Envio não encontrado'], 404);
            return;
        }
        $correction = $this->correcaoModel->findByEnvio($envioId);
        if (!$correction) {
            $this->correcaoModel->create([
                'envio_id' => (int) $envioId,
                'feedback_text' => '',
                'total_score' => 0,
            ]);
            $correction = $this->correcaoModel->findByEnvio($envioId);
        }
        $feedbackText = trim($_POST['feedback_text'] ?? '');
        $suggestionsText = trim($_POST['suggestions_text'] ?? '');
        $useAverage = isset($_POST['use_average']) && (string) $_POST['use_average'] === '1';
        $teacherGradesJson = [];
        foreach ($_POST as $k => $v) {
            if (strpos($k, 'grade_') === 0) {
                $slug = substr($k, 6);
                $score = is_numeric($v) ? (float) $v : null;
                $fb = trim($_POST['feedback_' . $slug] ?? '');
                $teacherGradesJson[$slug] = ['score' => $score, 'feedback' => $fb];
            }
        }
        $teacherTotal = null;
        if (!empty($teacherGradesJson)) {
            $teacherTotal = 0;
            foreach ($teacherGradesJson as $item) {
                if (isset($item['score']) && $item['score'] !== null) $teacherTotal += (float) $item['score'];
            }
        }
        $aiTotal = isset($correction['ai_total_score']) ? (float) $correction['ai_total_score'] : (float) ($correction['total_score'] ?? 0);
        if ($useAverage && $teacherTotal !== null) {
            $totalScore = ($aiTotal + $teacherTotal) / 2;
        } elseif ($teacherTotal !== null) {
            $totalScore = $teacherTotal;
        } else {
            $totalScore = $aiTotal;
        }
        $this->correcaoModel->updateTeacherAdjustment($correction['id'], $teacherId, [
            'teacher_grades_json' => $teacherGradesJson,
            'teacher_total_score' => $teacherTotal,
            'use_average' => $useAverage,
            'feedback_text' => $feedbackText,
            'suggestions_text' => $suggestionsText,
            'total_score' => $totalScore,
        ]);
        $this->json(['success' => true, 'message' => 'Correção salva.']);
    }

    /** POST: excluir envio (e correção associada); opcionalmente remove arquivo do storage */
    public function excluir($envioId)
    {
        $this->ensureRedacaoLivreEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        $teacherId = $this->getTeacherId();
        $envio = $this->envioModel->findByIdAndTeacher($envioId, $teacherId);
        if (!$envio) {
            $this->json(['error' => 'Envio não encontrado'], 404);
            return;
        }
        $this->correcaoModel->deleteByEnvio($envioId);
        if (!empty($envio['content_image_path'])) {
            $config = $this->config ?? [];
            $media = new MediaStorageService($config);
            $media->delete('essays_submissions', $envio['content_image_path']);
        }
        $this->envioModel->delete($envioId);
        $this->json(['success' => true, 'message' => 'Redação excluída.']);
    }

    /** POST: atualizar aluno/turma do envio */
    public function updateEnvioMeta($envioId)
    {
        $this->ensureRedacaoLivreEnabled(true);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        $teacherId = $this->getTeacherId();
        $envio = $this->envioModel->findByIdAndTeacher($envioId, $teacherId);
        if (!$envio) {
            $this->json(['error' => 'Envio não encontrado'], 404);
            return;
        }
        $this->envioModel->update($envioId, [
            'student_name' => trim($_POST['student_name'] ?? '') ?: null,
            'student_id' => isset($_POST['student_id']) && $_POST['student_id'] !== '' ? (int) $_POST['student_id'] : null,
            'turma_id' => isset($_POST['turma_id']) && $_POST['turma_id'] !== '' ? (int) $_POST['turma_id'] : null,
        ]);
        $this->json(['success' => true]);
    }
}
}
