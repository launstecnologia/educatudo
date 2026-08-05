<?php

require_once __DIR__ . '/../../Services/AvaCourseService.php';
require_once __DIR__ . '/../../Services/AvaActivityService.php';
require_once __DIR__ . '/../../Models/Ava/DisciplineEnrollment.php';

/**
 * EducaTudo - AVA: atividades/tarefas na área do aluno (ver, enviar, acompanhar nota).
 */
if (!class_exists('AvaActivityStudentController')) {
class AvaActivityStudentController extends BaseController
{
    private const EXTENSOES = ['pdf', 'doc', 'docx', 'odt', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip', 'mp3', 'mp4'];

    private $authManager;
    private AvaCourseService $service;
    private AvaActivityService $activities;
    private DisciplineEnrollment $enrollments;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            $this->redirect('/');
        }
        $this->service = new AvaCourseService();
        $this->activities = new AvaActivityService();
        $this->enrollments = new DisciplineEnrollment();
    }

    private function alunoId(): int
    {
        $user = $this->authManager->getUser();
        return (int) ($user['id'] ?? 0);
    }

    public function list(string $id): void
    {
        $disciplinaId = (int) $id;
        if (!$this->enrollments->isEnrolled($this->alunoId(), $disciplinaId)) {
            $this->setFlashMessage('Você não está matriculado nesta disciplina.', 'error');
            $this->redirect('/cursos');
            return;
        }
        $disc = $this->service->disciplinesModel()->find($disciplinaId);
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('student', 'student/ava/atividades', [
            'title' => 'Atividades - ' . htmlspecialchars((string) $disc['nome']),
            'current_page' => 'cursos',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'disciplina' => $disc,
            'atividades' => $this->activities->listForStudent($this->alunoId(), $disciplinaId),
        ]);
    }

    public function show(string $id): void
    {
        $atv = $this->activities->activitiesModel()->find((int) $id);
        if (!$atv || !$this->enrollments->isEnrolled($this->alunoId(), (int) $atv['disciplina_id'])) {
            $this->setFlashMessage('Atividade indisponível.', 'error');
            $this->redirect('/cursos');
            return;
        }
        if (($atv['status'] ?? '') !== 'publicada') {
            $this->setFlashMessage('Esta atividade ainda não está disponível.', 'info');
            $this->redirect('/cursos/disciplina/' . (int) $atv['disciplina_id'] . '/atividades');
            return;
        }
        $entrega = $this->activities->submissionsModel()->find((int) $id, $this->alunoId());
        $arquivos = $entrega ? $this->activities->submissionsModel()->files((int) $entrega['id']) : [];
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('student', 'student/ava/atividade', [
            'title' => htmlspecialchars((string) $atv['titulo']),
            'current_page' => 'cursos',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'atividade' => $atv,
            'entrega' => $entrega,
            'arquivos' => $arquivos,
            'pode_enviar' => $this->activities->canSubmit($atv),
            'rubrica_resultado' => $this->decodeRubrica($entrega['rubrica_resultado_json'] ?? null),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function submit(string $id): void
    {
        $atv = $this->activities->activitiesModel()->find((int) $id);
        $disciplinaId = (int) ($atv['disciplina_id'] ?? 0);
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? '')) || !$atv
            || !$this->enrollments->isEnrolled($this->alunoId(), $disciplinaId)) {
            $this->redirect('/cursos');
            return;
        }
        if (!$this->activities->canSubmit($atv)) {
            $this->setFlashMessage('O prazo de entrega desta atividade está encerrado.', 'error');
            $this->redirect('/cursos/atividade/' . (int) $id);
            return;
        }
        $entrega = $this->activities->submissionsModel()->find((int) $id, $this->alunoId());
        if ($entrega && ($entrega['status'] ?? '') === 'avaliada' && empty($atv['permite_reenvio'])) {
            $this->setFlashMessage('Esta atividade já foi avaliada e não permite reenvio.', 'info');
            $this->redirect('/cursos/atividade/' . (int) $id);
            return;
        }

        $texto = trim((string) ($_POST['texto'] ?? '')) ?: null;
        $link = trim((string) ($_POST['link'] ?? '')) ?: null;
        $atrasada = $this->activities->wouldBeLate($atv);
        $entregaId = $this->activities->submissionsModel()->submit((int) $id, $this->alunoId(), $texto, $link, $atrasada);

        $erros = $this->processUploads($entregaId, $atv);
        if ($erros !== '') {
            $this->setFlashMessage($erros, 'error');
        } else {
            $this->setFlashMessage('Atividade enviada com sucesso!', 'success');
        }
        $this->redirect('/cursos/atividade/' . (int) $id);
    }

    public function deleteFile(string $id): void
    {
        $arquivo = $this->activities->submissionsModel()->findFile((int) $id);
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? '')) || !$arquivo
            || (int) $arquivo['aluno_id'] !== $this->alunoId()) {
            $this->redirect('/cursos');
            return;
        }
        $entrega = $this->activities->submissionsModel()->find((int) $arquivo['atividade_id'], $this->alunoId());
        if ($entrega && ($entrega['status'] ?? '') === 'avaliada') {
            $this->setFlashMessage('Não é possível alterar uma entrega já avaliada.', 'info');
            $this->redirect('/cursos/atividade/' . (int) $arquivo['atividade_id']);
            return;
        }
        if (!empty($arquivo['arquivo_key'])) {
            try {
                require_once __DIR__ . '/../../Services/MediaStorageService.php';
                (new MediaStorageService($this->config))->delete('arquivos', (string) $arquivo['arquivo_key']);
            } catch (Throwable $e) {
                // best-effort
            }
        }
        $this->activities->submissionsModel()->deleteFile((int) $id);
        $this->setFlashMessage('Arquivo removido.', 'success');
        $this->redirect('/cursos/atividade/' . (int) $arquivo['atividade_id']);
    }

    public function file(string $id): void
    {
        $arquivo = $this->activities->submissionsModel()->findFile((int) $id);
        if (!$arquivo || (int) $arquivo['aluno_id'] !== $this->alunoId()) {
            http_response_code(403);
            echo 'Acesso negado';
            return;
        }
        if (empty($arquivo['arquivo_key'])) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            return;
        }
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $conteudo = (new MediaStorageService($this->config))->getContents('arquivos', (string) $arquivo['arquivo_key']);
        if ($conteudo === null) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            return;
        }
        if (!headers_sent()) {
            header('Content-Type: ' . (string) ($arquivo['mime'] ?? 'application/octet-stream'));
            header('Content-Disposition: inline; filename="' . str_replace('"', '', (string) ($arquivo['nome'] ?? 'arquivo')) . '"');
            header('Content-Length: ' . strlen($conteudo));
        }
        echo $conteudo;
    }

    // ---- Helpers ----------------------------------------------------------

    private function decodeRubrica($json): array
    {
        if (empty($json)) {
            return [];
        }
        $arr = is_array($json) ? $json : json_decode((string) $json, true);
        return is_array($arr) ? $arr : [];
    }

    /** Processa uploads múltiplos respeitando limites da atividade. Retorna string de erro (vazia = ok). */
    private function processUploads(int $entregaId, array $atv): string
    {
        if (empty($_FILES['arquivos']) || !is_array($_FILES['arquivos']['name'] ?? null)) {
            return '';
        }
        $maxArquivos = (int) ($atv['max_arquivos'] ?? 5);
        $maxBytes = ((int) ($atv['tamanho_max_mb'] ?? 20)) * 1024 * 1024;
        $jaTem = count($this->activities->submissionsModel()->files($entregaId));

        $names = $_FILES['arquivos']['name'];
        $total = count($names);
        $enviados = 0;
        for ($i = 0; $i < $total; $i++) {
            if (($_FILES['arquivos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            if ($jaTem + $enviados >= $maxArquivos) {
                return 'Limite de ' . $maxArquivos . ' arquivo(s) atingido. Alguns arquivos não foram enviados.';
            }
            $file = [
                'name' => $_FILES['arquivos']['name'][$i],
                'type' => $_FILES['arquivos']['type'][$i] ?? '',
                'tmp_name' => $_FILES['arquivos']['tmp_name'][$i],
                'error' => $_FILES['arquivos']['error'][$i],
                'size' => $_FILES['arquivos']['size'][$i],
            ];
            $ext = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, self::EXTENSOES, true)) {
                return 'Tipo de arquivo não permitido: ' . htmlspecialchars((string) $file['name']);
            }
            if ($file['size'] > $maxBytes) {
                return 'Arquivo acima do limite de ' . (int) ($atv['tamanho_max_mb'] ?? 20) . 'MB.';
            }
            try {
                $arq = $this->storeFile($file, $ext);
                $this->activities->submissionsModel()->addFile($entregaId, $arq);
                $enviados++;
            } catch (Throwable $e) {
                return $e->getMessage();
            }
        }
        return '';
    }

    /** @return array{arquivo_key:string,nome:string,mime:string,tamanho:int} */
    private function storeFile(array $file, string $ext): array
    {
        $mimeReal = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if ($detected) {
                    $mimeReal = $detected;
                }
            }
        }
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $slug = trim((string) ($this->config['tenant']['slug'] ?? $this->config['school']['code'] ?? '')) ?: 'default';
        $nomeUnico = 'entrega_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $key = $slug . '/ava/entregas/' . $nomeUnico;
        if (!$media->put('arquivos', $key, $file['tmp_name'], $mimeReal)) {
            throw new Exception('Falha ao armazenar o arquivo.');
        }
        return [
            'arquivo_key' => $key,
            'nome' => substr((string) $file['name'], 0, 255),
            'mime' => $mimeReal,
            'tamanho' => (int) $file['size'],
        ];
    }
}
}
