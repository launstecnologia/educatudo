<?php

require_once __DIR__ . '/../../Models/Education/OnlineClass.php';
require_once __DIR__ . '/../../Services/PandaVideoLiveService.php';
require_once __DIR__ . '/../../Services/JaasMeetService.php';
require_once __DIR__ . '/../../Services/AulaOnlineGravacaoService.php';

if (!class_exists('OnlineClassController')) {
class OnlineClassController extends BaseController
{
    private $auth;
    private $onlineClass;
    private $pandaLive;
    private $jaasMeet;
    private $gravacaoService;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->onlineClass = new OnlineClass();
        $this->pandaLive = new PandaVideoLiveService();
        $this->jaasMeet = new JaasMeetService();
        $this->gravacaoService = new AulaOnlineGravacaoService($this->onlineClass, $this->jaasMeet);

        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
        }

        $user = $this->auth->getUser();
        $tipo = $user['tipo'] ?? '';
        if ($tipo !== 'admin' && $tipo !== 'admin_escola') {
            $this->redirectToCorrectDashboard($tipo);
        }

        $perfil = (string) ($user['perfil_admin'] ?? '');
        if ($perfil === 'financeiro') {
            $this->setFlashMessage('Perfil sem acesso ao módulo Aulas Online.', 'error');
            $this->redirect('/admin/dashboard');
        }
    }

    public function index(): void
    {
        $user = $this->auth->getUser();
        $aulas = $this->onlineClass->listForAdmin();

        $this->viewWithLayout('admin', 'admin/aulas-online/index', [
            'title' => 'Aulas Online',
            'page_title' => 'Aulas Online',
            'current_page' => 'aulas_online',
            'user' => $user,
            'aulas' => $aulas,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function criar(): void
    {
        $user = $this->auth->getUser();
        $turmas = $this->onlineClass->listTurmas();

        $this->viewWithLayout('admin', 'admin/aulas-online/form', [
            'title' => 'Nova Aula Online',
            'page_title' => 'Nova Aula Online',
            'current_page' => 'aulas_online',
            'user' => $user,
            'turmas' => $turmas,
            'aula' => null,
            'aula_arquivos' => [],
            'csrf_token' => $this->generateCsrfToken(),
            'panda_configured' => $this->pandaLive->isConfigured(),
            'jaas_configured' => $this->jaasMeet->isConfigured(),
        ]);
    }

    public function editar(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $aula = $id > 0 ? $this->onlineClass->getById($id) : null;

        if (!$aula) {
            $this->setFlashMessage('Aula não encontrada.', 'error');
            $this->redirect('/admin/aulas-online');
        }

        $user = $this->auth->getUser();
        $turmas = $this->onlineClass->listTurmas();
        $aulaArquivos = $this->onlineClass->listArquivos($id);

        $this->viewWithLayout('admin', 'admin/aulas-online/form', [
            'title' => 'Editar Aula Online',
            'page_title' => 'Editar Aula Online',
            'current_page' => 'aulas_online',
            'user' => $user,
            'turmas' => $turmas,
            'aula' => $aula,
            'aula_arquivos' => $aulaArquivos,
            'csrf_token' => $this->generateCsrfToken(),
            'panda_configured' => $this->pandaLive->isConfigured(),
            'jaas_configured' => $this->jaasMeet->isConfigured(),
        ]);
    }

    public function salvar(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/aulas-online');
        }

        $payload = $this->parsePayload($_POST);
        if ($payload['errors'] !== []) {
            $this->setFlashMessage(implode(' ', $payload['errors']), 'error');
            $this->redirect('/admin/aulas-online');
        }

        $user = $this->auth->getUser();
        $aulaId = $this->onlineClass->create($payload['data'], (int) ($user['id'] ?? 0));
        $createdBy = (int) ($user['id'] ?? 0);

        $integrationMessage = $this->tryIntegratePandaLive($aulaId, $payload['data'], false);
        $this->ensureJitsiMeetingLink($aulaId, $payload['data']);
        $recurrenceIds = $this->onlineClass->createWeeklyRecurrences(
            $aulaId,
            $payload['data'],
            $createdBy,
            (int) ($payload['data']['recorrencia_semanas'] ?? 1)
        );
        if (
            !empty($recurrenceIds) &&
            $integrationMessage === '' &&
            (int) ($payload['data']['gerar_panda'] ?? 0) === 1
        ) {
            foreach ($recurrenceIds as $rid) {
                $this->onlineClass->copyPandaDataFromClass($aulaId, (int) $rid);
            }
        }
        if (!empty($recurrenceIds) && $this->isJitsiData($payload['data'])) {
            foreach ($recurrenceIds as $rid) {
                $recurrence = $this->onlineClass->getById((int) $rid);
                if ($recurrence) {
                    $recurrenceData = array_merge($payload['data'], [
                        'titulo' => (string) ($recurrence['titulo'] ?? $payload['data']['titulo']),
                        'link_aula' => '',
                    ]);
                    $this->ensureJitsiMeetingLink((int) $rid, $recurrenceData);
                }
            }
        }

        if ($integrationMessage !== '') {
            $this->setFlashMessage('Aula criada. ' . $integrationMessage, 'warning');
            $this->redirect('/admin/aulas-online/editar?id=' . $aulaId);
        }

        $msg = 'Aula online criada com sucesso.';
        if (!empty($recurrenceIds)) {
            $msg .= ' Recorrência criada (' . count($recurrenceIds) . ' aulas no total).';
        }
        $this->setFlashMessage($msg, 'success');
        $this->redirect('/admin/aulas-online/editar?id=' . $aulaId);
    }

    public function atualizar(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/aulas-online');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->setFlashMessage('Aula inválida.', 'error');
            $this->redirect('/admin/aulas-online');
        }

        $payload = $this->parsePayload($_POST);
        if ($payload['errors'] !== []) {
            $this->setFlashMessage(implode(' ', $payload['errors']), 'error');
            $this->redirect('/admin/aulas-online/editar?id=' . $id);
        }

        $this->onlineClass->update($id, $payload['data']);
        $this->ensureJitsiMeetingLink($id, $payload['data']);

        $forceIntegration = (int) ($payload['data']['gerar_panda'] ?? 0) === 1
            && trim((string) ($payload['data']['link_aula'] ?? '')) === '';
        $integrationMessage = $this->tryIntegratePandaLive($id, $payload['data'], $forceIntegration);
        if ($integrationMessage !== '') {
            $this->setFlashMessage('Aula atualizada. ' . $integrationMessage, 'warning');
            $this->redirect('/admin/aulas-online/editar?id=' . $id);
        }

        $this->setFlashMessage('Aula online atualizada com sucesso.', 'success');
        $this->redirect('/admin/aulas-online/editar?id=' . $id);
    }

    public function retryIntegracao(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/aulas-online');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->setFlashMessage('Aula inválida.', 'error');
            $this->redirect('/admin/aulas-online');
        }

        $aula = $this->onlineClass->getById($id);
        if (!$aula) {
            $this->setFlashMessage('Aula não encontrada.', 'error');
            $this->redirect('/admin/aulas-online');
        }

        $data = [
            'titulo' => (string) ($aula['titulo'] ?? ''),
            'descricao' => (string) ($aula['descricao'] ?? ''),
            'inicio_em' => (string) ($aula['inicio_em'] ?? ''),
            'fim_em' => (string) ($aula['fim_em'] ?? ''),
            'gerar_panda' => (int) ($aula['gerar_panda'] ?? 0),
            'link_aula' => (string) ($aula['link_aula'] ?? ''),
        ];

        $integrationMessage = $this->tryIntegratePandaLive($id, $data, true);
        if ($integrationMessage !== '') {
            $this->setFlashMessage($integrationMessage, 'warning');
            $this->redirect('/admin/aulas-online');
        }

        $this->setFlashMessage('Integração Panda concluída com sucesso.', 'success');
        $this->redirect('/admin/aulas-online');
    }

    public function sincronizarGravacoes(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/aulas-online');
        }

        $aulaId = (int) ($_POST['id'] ?? 0);
        $voltarEdicao = $aulaId > 0;
        $resultado = $this->gravacaoService->sincronizar($voltarEdicao ? $aulaId : null);

        $destino = $voltarEdicao
            ? '/admin/aulas-online/editar?id=' . $aulaId
            : '/admin/aulas-online';

        if (!empty($resultado['erro'])) {
            $this->setFlashMessage('Não foi possível atualizar as gravações. Tente novamente.', 'error');
            $this->redirect($destino);
        }

        $atualizadas = (int) ($resultado['atualizadas'] ?? 0);
        $jaTinham = (int) ($resultado['ja_tinham'] ?? 0);
        $naoEncontradas = (int) ($resultado['nao_encontradas'] ?? 0);

        if ($voltarEdicao) {
            if ($atualizadas > 0) {
                $this->setFlashMessage('Gravação encontrada e vinculada a esta aula.', 'success');
            } elseif ($jaTinham > 0) {
                $this->setFlashMessage('Esta aula já possui URL de gravação.', 'success');
            } else {
                $this->setFlashMessage('Nenhuma gravação encontrada para esta aula na API.', 'error');
            }
            $this->redirect($destino);
        }

        if ($atualizadas > 0) {
            $msg = $atualizadas === 1
                ? '1 gravação vinculada à aula correspondente.'
                : $atualizadas . ' gravações vinculadas às aulas correspondentes.';
            if ($naoEncontradas > 0) {
                $msg .= ' ' . $naoEncontradas . ' aula(s) ainda sem correspondência na API.';
            }
            $this->setFlashMessage($msg, 'success');
        } elseif ($jaTinham > 0 && $naoEncontradas === 0) {
            $this->setFlashMessage('Todas as aulas com gravação na API já estavam atualizadas.', 'success');
        } else {
            $this->setFlashMessage('Nenhuma gravação nova encontrada para as aulas desta escola.', 'error');
        }
        $this->redirect($destino);
    }

    public function excluir(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/aulas-online');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->setFlashMessage('Aula inválida.', 'error');
            $this->redirect('/admin/aulas-online');
        }

        $this->onlineClass->softDelete($id);
        $this->setFlashMessage('Aula online removida.', 'success');
        $this->redirect('/admin/aulas-online');
    }

    public function chat(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->setFlashMessage('Aula inválida.', 'error');
            $this->redirect('/admin/aulas-online');
        }

        $aula = $this->onlineClass->getById($id);
        if (!$aula) {
            $this->setFlashMessage('Aula não encontrada.', 'error');
            $this->redirect('/admin/aulas-online');
        }

        $this->viewWithLayout('admin', 'admin/aulas-online/chat', [
            'title' => 'Chat da Aula',
            'page_title' => 'Chat da Aula',
            'current_page' => 'aulas_online',
            'user' => $this->auth->getUser(),
            'aula' => $aula,
            'jaas_meeting_url' => $this->buildAdminJaasMeetingUrl($aula),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function chatMensagens(): void
    {
        $aulaId = (int) ($_GET['aula_id'] ?? 0);
        $afterId = (int) ($_GET['after_id'] ?? 0);
        if ($aulaId <= 0) {
            $this->json(['success' => false, 'error' => 'Aula inválida'], 400);
            return;
        }
        $aula = $this->onlineClass->getById($aulaId);
        if (!$aula) {
            $this->json(['success' => false, 'error' => 'Aula não encontrada'], 404);
            return;
        }

        $rows = $this->onlineClass->listChatMessages($aulaId, $afterId, 150);
        $this->json(['success' => true, 'messages' => $rows]);
    }

    public function chatEnviar(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 400);
            return;
        }

        $aulaId = (int) ($_POST['aula_id'] ?? 0);
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        if ($aulaId <= 0 || $mensagem === '') {
            $this->json(['success' => false, 'error' => 'Dados inválidos'], 400);
            return;
        }
        $aula = $this->onlineClass->getById($aulaId);
        if (!$aula) {
            $this->json(['success' => false, 'error' => 'Aula não encontrada'], 404);
            return;
        }

        $user = $this->auth->getUser();
        $userId = (int) ($user['id'] ?? 0);
        $perfil = (string) ($user['perfil_admin'] ?? '');
        $nome = (string) ($user['nome'] ?? 'Coordenação');
        $tipo = $perfil !== '' ? 'coordenacao' : 'admin';

        $id = $this->onlineClass->addChatMessage($aulaId, $userId, $tipo, $nome, $mensagem);
        $this->json(['success' => true, 'id' => $id]);
    }

    private function parsePayload(array $input): array
    {
        $titulo = trim((string) ($input['titulo'] ?? ''));
        $descricao = trim((string) ($input['descricao'] ?? ''));
        $plataforma = trim((string) ($input['plataforma'] ?? ''));
        $linkAula = trim((string) ($input['link_aula'] ?? ''));
        $linkGravacao = trim((string) ($input['link_gravacao'] ?? ''));
        $inicio = trim((string) ($input['inicio_em'] ?? ''));
        $fim = trim((string) ($input['fim_em'] ?? ''));

        $enviarParaTodos = isset($input['enviar_para_todos']) && (string) $input['enviar_para_todos'] === '1' ? 1 : 0;
        $publicado = isset($input['publicado']) && (string) $input['publicado'] === '1' ? 1 : 0;
        $gerarPanda = isset($input['gerar_panda']) && (string) $input['gerar_panda'] === '1' ? 1 : 0;
        $reusarConfigPanda = isset($input['reusar_config_panda']) && (string) $input['reusar_config_panda'] === '1' ? 1 : 0;
        $recorrenteSemanal = isset($input['recorrente_semanal']) && (string) $input['recorrente_semanal'] === '1' ? 1 : 0;
        $recorrenciaSemanas = max(1, min(52, (int) ($input['recorrencia_semanas'] ?? 1)));
        $turmasIds = isset($input['turmas']) && is_array($input['turmas'])
            ? array_values(array_unique(array_filter(array_map('intval', $input['turmas']), static function ($v) {
                return $v > 0;
            })))
            : [];

        $errors = [];
        $platformLower = mb_strtolower($plataforma, 'UTF-8');
        $isJitsi = $platformLower === 'jitsi meet';
        if ($titulo === '') {
            $errors[] = 'Informe o título da aula.';
        }
        if ($gerarPanda === 0 && !$isJitsi && ($linkAula === '' || !filter_var($linkAula, FILTER_VALIDATE_URL))) {
            $errors[] = 'Informe um link válido para a aula online.';
        }
        if ($gerarPanda === 1 && $platformLower !== 'panda video live') {
            $errors[] = 'Para gerar automático no Panda, selecione a plataforma \"Panda Video Live\".';
        }
        if ($gerarPanda === 1 && !$this->pandaLive->isConfigured()) {
            $errors[] = 'Panda Video não configurado. Defina as credenciais no .env.';
        }
        if ($inicio === '') {
            $errors[] = 'Informe a data/hora de início.';
        }
        if ($fim !== '' && strtotime($fim) !== false && strtotime($inicio) !== false && strtotime($fim) < strtotime($inicio)) {
            $errors[] = 'A data/hora de término não pode ser antes do início.';
        }
        if ($enviarParaTodos === 0 && $turmasIds === []) {
            $errors[] = 'Selecione ao menos uma turma ou marque "Disponível para todas as turmas".';
        }
        if ($recorrenteSemanal === 1 && $recorrenciaSemanas < 2) {
            $errors[] = 'Para recorrência semanal, informe ao menos 2 semanas.';
        }

        $inicioDb = $this->toDbDateTime($inicio);
        $fimDb = $fim !== '' ? $this->toDbDateTime($fim) : null;
        if ($inicioDb === null) {
            $errors[] = 'Data/hora de início inválida.';
        }
        if ($fim !== '' && $fimDb === null) {
            $errors[] = 'Data/hora de término inválida.';
        }

        return [
            'errors' => $errors,
            'data' => [
                'titulo' => $titulo,
                'descricao' => $descricao,
                'plataforma' => $plataforma,
                'link_aula' => $linkAula,
                'link_gravacao' => $linkGravacao !== '' ? $linkGravacao : null,
                'inicio_em' => $inicioDb,
                'fim_em' => $fimDb,
                'enviar_para_todos' => $enviarParaTodos,
                'publicado' => $publicado,
                'gerar_panda' => $gerarPanda,
                'reusar_config_panda' => $reusarConfigPanda,
                'recorrente_semanal' => $recorrenteSemanal,
                'recorrencia_semanas' => $recorrenteSemanal === 1 ? $recorrenciaSemanas : 1,
                'turmas_ids' => $turmasIds,
            ],
        ];
    }

    private function tryIntegratePandaLive(int $aulaId, array &$data, bool $force): string
    {
        if ((int) ($data['gerar_panda'] ?? 0) !== 1) {
            return '';
        }

        $linkAtual = trim((string) ($data['link_aula'] ?? ''));
        if (!$force && $linkAtual !== '') {
            return '';
        }

        $this->onlineClass->markPandaIntegrationPending($aulaId);

        try {
            $isoInicio = !empty($data['inicio_em']) ? date('c', strtotime((string) $data['inicio_em'])) : '';
            $isoFim = !empty($data['fim_em']) ? date('c', strtotime((string) $data['fim_em'])) : '';

            $result = $this->pandaLive->createLive([
                'titulo' => (string) ($data['titulo'] ?? ''),
                'descricao' => (string) ($data['descricao'] ?? ''),
                'inicio_em_iso' => $isoInicio,
                'fim_em_iso' => $isoFim,
            ]);

            $data['link_aula'] = (string) ($result['url'] ?? '');
            $data['descricao'] = trim((string) ($data['descricao'] ?? ''));

            $this->onlineClass->updatePandaIntegrationSuccess(
                $aulaId,
                (string) ($result['url'] ?? ''),
                (string) ($result['id_externo'] ?? ''),
                (array) ($result['raw'] ?? []),
                (string) ($data['descricao'] ?? '')
            );
            return '';
        } catch (Throwable $e) {
            $this->onlineClass->updatePandaIntegrationError($aulaId, $e->getMessage());
            return 'A integração Panda falhou, mas a aula foi salva. Você pode tentar novamente na lista.';
        }
    }

    private function ensureJitsiMeetingLink(int $aulaId, array &$data): void
    {
        if (!$this->isJitsiData($data)) {
            return;
        }
        if (trim((string) ($data['link_aula'] ?? '')) !== '') {
            return;
        }

        $link = $this->jaasMeet->baseMeetingUrl($aulaId, (string) ($data['titulo'] ?? ''));
        if ($link === '') {
            return;
        }

        $data['link_aula'] = $link;
        $this->onlineClass->updateMeetingLink($aulaId, $link);
    }

    private function isJitsiData(array $data): bool
    {
        return mb_strtolower((string) ($data['plataforma'] ?? ''), 'UTF-8') === 'jitsi meet';
    }

    private function buildAdminJaasMeetingUrl(array $aula): string
    {
        if (mb_strtolower((string) ($aula['plataforma'] ?? ''), 'UTF-8') !== 'jitsi meet') {
            return '';
        }

        try {
            return $this->jaasMeet->meetingUrl(
                (int) ($aula['id'] ?? 0),
                (string) ($aula['titulo'] ?? ''),
                $this->auth->getUser() ?: [],
                true
            );
        } catch (Throwable $e) {
            return '';
        }
    }

    public function uploadArquivo(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 403);
            return;
        }

        $aulaId = (int) ($_POST['aula_id'] ?? 0);
        if ($aulaId <= 0 || !$this->onlineClass->getById($aulaId)) {
            $this->json(['success' => false, 'error' => 'Aula não encontrada'], 404);
            return;
        }

        $file = $_FILES['arquivo'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'error' => 'Nenhum arquivo enviado'], 400);
            return;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = [
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'text/plain', 'application/zip', 'application/x-zip-compressed',
        ];
        if (!in_array($mime, $allowedMimes, true)) {
            $this->json(['success' => false, 'error' => 'Tipo de arquivo não permitido'], 400);
            return;
        }

        $slug = defined('TENANT_SLUG') ? TENANT_SLUG : 'default';
        $dir = __DIR__ . '/../../../storage/aulas_online/' . $slug . '/' . $aulaId . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext = pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION);
        $filename = uniqid('doc_', true) . ($ext !== '' ? '.' . $ext : '');
        $dest = $dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->json(['success' => false, 'error' => 'Erro ao salvar arquivo'], 500);
            return;
        }

        $user = $this->auth->getUser();
        $id = $this->onlineClass->addArquivo($aulaId, [
            'nome_original' => (string) ($file['name'] ?? $filename),
            'caminho'       => 'aulas_online/' . $slug . '/' . $aulaId . '/' . $filename,
            'mime_type'     => $mime,
            'tamanho'       => (int) ($file['size'] ?? 0),
            'criado_por'    => (int) ($user['id'] ?? 0),
        ]);

        $this->json(['success' => true, 'id' => $id, 'nome' => $file['name']]);
    }

    public function excluirArquivo(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 403);
            return;
        }

        $id = (int) ($_POST['arquivo_id'] ?? 0);
        $arq = $this->onlineClass->getArquivo($id);
        if (!$arq) {
            $this->json(['success' => false, 'error' => 'Arquivo não encontrado'], 404);
            return;
        }

        $path = __DIR__ . '/../../../storage/' . $arq['caminho'];
        if (is_file($path)) {
            unlink($path);
        }

        $this->onlineClass->deleteArquivo($id);
        $this->json(['success' => true]);
    }

    public function downloadArquivo(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $arq = $this->onlineClass->getArquivo($id);
        if (!$arq) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            return;
        }

        $path = __DIR__ . '/../../../storage/' . $arq['caminho'];
        if (!is_file($path)) {
            http_response_code(404);
            echo 'Arquivo não encontrado no disco';
            return;
        }

        header('Content-Type: ' . ($arq['mime_type'] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . addslashes((string) $arq['nome_original']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    private function toDbDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }
}
}
