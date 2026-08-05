<?php
/**
 * Controller para Notificações Push (OneSignal) - Admin
 * Envio e relatórios.
 */
require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Core/Logger.php';
require_once __DIR__ . '/../../Models/PushNotifications/PushNotification.php';
require_once __DIR__ . '/../../Services/OneSignalService.php';
require_once __DIR__ . '/../../Services/FirebaseMessagingService.php';
require_once __DIR__ . '/../../Services/MobileDeviceService.php';

class PushNotificationController extends BaseController
{
    private $auth;
    private $db;
    private $pushModel;
    private $oneSignal;
    private $firebase;
    private $mobileDevices;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $this->pushModel = new PushNotification();
        $this->oneSignal = new OneSignalService();
        $this->firebase = new FirebaseMessagingService();
        $this->mobileDevices = new MobileDeviceService();

        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirect(URL . '/admin');
        }
    }

    /**
     * Lista notificações push com totais
     */
    public function index()
    {
        try {
            $notificacoes = $this->pushModel->getAll(100);
        } catch (Exception $e) {
            Logger::error('Push: falha ao listar notificações (getAll)', ['exception' => $e], 'push');
            $notificacoes = [];
        }
        $this->viewWithLayout('admin', 'admin/push-notifications/index', [
            'notificacoes' => $notificacoes,
            'title' => 'Notificações Push',
            'page_title' => 'Notificações Push',
            'user' => $this->auth->getUser(),
            'onesignal_configured' => $this->oneSignal->isConfigured(),
            'fcm_configured' => $this->firebase->isConfigured(),
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'notificacoes-push'
        ]);
    }

    /**
     * Formulário para criar e enviar
     */
    public function create()
    {
        $turmas = $this->db->fetchAll("SELECT id, nome, serie FROM turmas WHERE ativo = 1 ORDER BY serie, nome");
        $usuarios = $this->getUsuariosParaSelect();
        $responsaveis = $this->getResponsaveisParaSelect();
        $this->viewWithLayout('admin', 'admin/push-notifications/create', [
            'turmas' => $turmas,
            'usuarios' => $usuarios,
            'responsaveis' => $responsaveis,
            'title' => 'Enviar Notificação Push',
            'page_title' => 'Enviar Notificação Push',
            'user' => $this->auth->getUser(),
            'onesignal_configured' => $this->oneSignal->isConfigured(),
            'fcm_configured' => $this->firebase->isConfigured(),
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'notificacoes-push'
        ]);
    }

    /**
     * POST: enviar notificação
     */
    public function enviar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(URL . '/admin/notificacoes-push/criar');
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Atualize a página e tente novamente.', 'error');
            $this->redirect(URL . '/admin/notificacoes-push/criar');
            return;
        }

        $titulo = trim($_POST['titulo'] ?? '');
        $mensagem = trim($_POST['mensagem'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $tipoDestino = $_POST['tipo_destino'] ?? '';
        $destinoId = !empty($_POST['destino_id']) ? (int) $_POST['destino_id'] : null;
        $canais = array_values(array_intersect((array) ($_POST['canais'] ?? []), ['onesignal', 'fcm']));

        Logger::info('Push: início do envio', [
            'titulo' => $titulo,
            'tipo_destino' => $tipoDestino,
            'destino_id' => $destinoId,
            'url' => $url
        ], 'push');

        if ($titulo === '' || $mensagem === '') {
            Logger::warning('Push: título ou mensagem vazios', [], 'push');
            $this->setFlashMessage('Título e mensagem são obrigatórios.', 'error');
            $this->redirect(URL . '/admin/notificacoes-push/criar');
            return;
        }
        if (!in_array($tipoDestino, ['todos', 'pais', 'alunos', 'professores', 'turma', 'usuario', 'responsavel'], true)) {
            Logger::warning('Push: tipo de envio inválido', ['tipo_destino' => $tipoDestino], 'push');
            $this->setFlashMessage('Tipo de envio inválido.', 'error');
            $this->redirect(URL . '/admin/notificacoes-push/criar');
            return;
        }
        if (in_array($tipoDestino, ['turma', 'usuario', 'responsavel'], true) && !$destinoId) {
            Logger::warning('Push: turma/usuário não selecionado', [], 'push');
            $this->setFlashMessage('Selecione a turma ou o usuário.', 'error');
            $this->redirect(URL . '/admin/notificacoes-push/criar');
            return;
        }
        if ($canais === []) {
            $this->setFlashMessage('Selecione ao menos um canal de envio.', 'error');
            $this->redirect(URL . '/admin/notificacoes-push/criar');
            return;
        }
        if ($tipoDestino === 'responsavel' && !in_array('fcm', $canais, true)) {
            $this->setFlashMessage('O envio para responsável específico exige o canal Aplicativo Android (Firebase).', 'error');
            $this->redirect(URL . '/admin/notificacoes-push/criar');
            return;
        }
        if (in_array('fcm', $canais, true) && !$this->firebase->isConfigured()) {
            $this->setFlashMessage('Firebase/FCM ainda não está configurado no servidor.', 'error');
            $this->redirect(URL . '/admin/notificacoes-push/criar');
            return;
        }

        try {
            $user = $this->auth->getUser();
            $recipients = $this->resolveDestinatarios($tipoDestino, $destinoId);
            $mobileParentIds = $this->resolveMobileParentIds($tipoDestino, $destinoId);

            if (in_array('onesignal', $canais, true) && !in_array('fcm', $canais, true) && $recipients === []) {
                $this->setFlashMessage('Nenhum destinatário disponível para o canal Web/PWA.', 'error');
                $this->redirect(URL . '/admin/notificacoes-push/criar');
                return;
            }
            if (in_array('fcm', $canais, true) && !in_array('onesignal', $canais, true) && $mobileParentIds === []) {
                $this->setFlashMessage('Esse público não possui responsáveis disponíveis para o aplicativo Android.', 'error');
                $this->redirect(URL . '/admin/notificacoes-push/criar');
                return;
            }

            Logger::info('Push: destinatários resolvidos', [
                'total' => count($recipients),
                'tipo_destino' => $tipoDestino,
                'destino_id' => $destinoId
            ], 'push');

            if (empty($recipients) && empty($mobileParentIds)) {
                Logger::warning('Push: nenhum destinatário encontrado', ['tipo_destino' => $tipoDestino, 'destino_id' => $destinoId], 'push');
                $this->setFlashMessage('Nenhum destinatário encontrado para os critérios selecionados.', 'error');
                $this->redirect(URL . '/admin/notificacoes-push/criar');
                return;
            }

            $notificacaoId = $this->pushModel->create($titulo, $mensagem, $url ?: null, $tipoDestino, $destinoId, $user['id']);

            if (!$notificacaoId) {
                Logger::error('Push: falha ao registrar notificação no banco (create retornou 0)', [
                    'titulo' => $titulo,
                    'tipo_destino' => $tipoDestino
                ], 'push');
                $this->setFlashMessage('Erro ao registrar notificação. Verifique o log em storage/logs/push_*.log', 'error');
                $this->redirect(URL . '/admin/notificacoes-push/criar');
                return;
            }

            Logger::info('Push: notificação registrada', ['notificacao_id' => $notificacaoId], 'push');

            $baseUrl = rtrim(defined('URL') ? URL : '', '/');
            $fullUrl = $url ? (preg_match('#^https?://#', $url) ? $url : $baseUrl . '/' . ltrim($url, '/')) : null;

            $enviadosOk = 0;
            $enviadosFalha = 0;
            $semToken = 0;
            $trackingByRecipient = [];

            if (in_array('onesignal', $canais, true)) {
                foreach ($recipients as $r) {
                    $token = $this->pushModel->addEnvio($notificacaoId, $r['user_id'], $r['role']);
                    if (!$token) {
                        $semToken++;
                        continue;
                    }
                    $trackingByRecipient[$r['role'] . ':' . $r['user_id']] = $token;
                    $result = $this->oneSignal->sendToUser($titulo, $mensagem, $fullUrl, [
                        'tracking_token' => $token,
                        'notificacao_id' => (string) $notificacaoId
                    ], $r['user_id']);
                    if (!empty($result['success'])) {
                        $enviadosOk++;
                        $this->pushModel->marcarEntregueDestinatario($notificacaoId, $r['user_id'], $r['role']);
                    } else {
                        $enviadosFalha++;
                    }
                }
            }

            if (in_array('fcm', $canais, true)) {
                $devices = $this->mobileDevices->enabledTokensForParents($mobileParentIds);
                $parentsWithDevice = [];
                $trackingByParent = [];
                foreach ($devices as $device) {
                    $parentId = (int) $device['parent_id'];
                    $parentsWithDevice[$parentId] = true;
                    if (!isset($trackingByParent[$parentId])) {
                        $recipientKey = 'pai:' . $parentId;
                        $trackingByParent[$parentId] = $trackingByRecipient[$recipientKey]
                            ?? $this->pushModel->addEnvio($notificacaoId, $parentId, 'pai');
                    }
                    $result = $this->firebase->sendToToken((string) $device['fcm_token'], $titulo, $mensagem, [
                        'tracking_token' => $trackingByParent[$parentId],
                        'notificacao_id' => (string) $notificacaoId,
                        'type' => 'school_notification',
                        'route' => ($url !== '' && strpos($url, '/') === 0) ? $url : '/notifications',
                    ]);
                    if (!empty($result['success'])) {
                        $enviadosOk++;
                        $this->pushModel->marcarEntregueDestinatario($notificacaoId, $parentId, 'pai');
                    } else {
                        $enviadosFalha++;
                        if (!empty($result['invalid_token'])) {
                            $this->mobileDevices->disableToken((string) $device['fcm_token']);
                        }
                    }
                }
                $semToken += max(0, count($mobileParentIds) - count($parentsWithDevice));
            }

            Logger::info('Push: envio finalizado', [
                'notificacao_id' => $notificacaoId,
                'total_destinatarios_web' => count($recipients),
                'total_responsaveis_mobile' => count($mobileParentIds),
                'enviados_ok' => $enviadosOk,
                'enviados_falha' => $enviadosFalha,
                'sem_token' => $semToken
            ], 'push');

            $msg = 'Notificação registrada. ' . $enviadosOk . ' envio(s) aceito(s).';
            if ($enviadosFalha > 0) {
                $msg .= ' ' . $enviadosFalha . ' falha(s) (ver log push).';
            }
            if ($semToken > 0) {
                $msg .= ' ' . $semToken . ' sem registro de envio.';
            }
            $this->setFlashMessage($msg, $enviadosOk > 0 ? 'success' : 'error');
            $this->redirect(URL . '/admin/notificacoes-push');
        } catch (Exception $e) {
            Logger::error('Push: exceção ao enviar notificação', [
                'exception' => $e,
                'titulo' => $titulo,
                'tipo_destino' => $tipoDestino,
                'destino_id' => $destinoId
            ], 'push');
            $this->setFlashMessage('Erro ao enviar. Consulte storage/logs/push_' . date('Y-m-d') . '.log', 'error');
            $this->redirect(URL . '/admin/notificacoes-push/criar');
        }
    }

    /**
     * Detalhes e relatório por notificação
     */
    public function show($id)
    {
        $notificacao = $this->pushModel->getById($id);
        if (!$notificacao) {
            $this->setFlashMessage('Notificação não encontrada.', 'error');
            $this->redirect(URL . '/admin/notificacoes-push');
            return;
        }
        $envios = $this->pushModel->getEnviosByNotificacao($id);
        $notificacao['total_envios'] = count($envios);
        $notificacao['total_entregues'] = count(array_filter($envios, function ($e) { return !empty($e['entregue']); }));
        $notificacao['total_visualizados'] = count(array_filter($envios, function ($e) { return !empty($e['visualizado']); }));
        $notificacao['total_clicados'] = count(array_filter($envios, function ($e) { return !empty($e['clicado']); }));
        $this->viewWithLayout('admin', 'admin/push-notifications/show', [
            'notificacao' => $notificacao,
            'envios' => $envios,
            'title' => 'Detalhes da Notificação Push',
            'page_title' => 'Detalhes da Notificação Push',
            'user' => $this->auth->getUser(),
            'current_page' => 'notificacoes-push'
        ]);
    }

    /**
     * Resolve destinatários. user_id = id da tabela do perfil (AuthManager: aluno=alunos.id, pai=pais.id, professor=professores.id, admin=usuarios.id).
     */
    private function resolveDestinatarios($tipoDestino, $destinoId)
    {
        $recipients = [];
        switch ($tipoDestino) {
            case 'todos':
                $rows = $this->db->fetchAll("SELECT id AS user_id FROM usuarios WHERE tipo = 'admin_escola'");
                foreach ($rows as $r) {
                    $recipients[] = ['user_id' => (int) $r['user_id'], 'role' => 'admin_escola'];
                }
                $rows = $this->db->fetchAll("SELECT id AS user_id FROM alunos WHERE ativo = 1");
                foreach ($rows as $r) {
                    $recipients[] = ['user_id' => (int) $r['user_id'], 'role' => 'aluno'];
                }
                $rows = $this->db->fetchAll("SELECT id AS user_id FROM responsaveis WHERE ativo = 1");
                foreach ($rows as $r) {
                    $recipients[] = ['user_id' => (int) $r['user_id'], 'role' => 'pai'];
                }
                $rows = $this->db->fetchAll("SELECT id AS user_id FROM professores WHERE ativo = 1");
                foreach ($rows as $r) {
                    $recipients[] = ['user_id' => (int) $r['user_id'], 'role' => 'professor'];
                }
                break;
            case 'pais':
                $rows = $this->db->fetchAll("SELECT id AS user_id FROM responsaveis WHERE ativo = 1");
                foreach ($rows as $r) {
                    $recipients[] = ['user_id' => (int) $r['user_id'], 'role' => 'pai'];
                }
                break;
            case 'alunos':
                $rows = $this->db->fetchAll("SELECT id AS user_id FROM alunos WHERE ativo = 1");
                foreach ($rows as $r) {
                    $recipients[] = ['user_id' => (int) $r['user_id'], 'role' => 'aluno'];
                }
                break;
            case 'professores':
                $rows = $this->db->fetchAll("SELECT id AS user_id FROM professores WHERE ativo = 1");
                foreach ($rows as $r) {
                    $recipients[] = ['user_id' => (int) $r['user_id'], 'role' => 'professor'];
                }
                break;
            case 'turma':
                $rows = $this->db->fetchAll(
                    "SELECT id AS user_id FROM alunos WHERE ativo = 1 AND turma_id = :tid",
                    ['tid' => $destinoId]
                );
                foreach ($rows as $r) {
                    $recipients[] = ['user_id' => (int) $r['user_id'], 'role' => 'aluno'];
                }
                break;
            case 'usuario':
                $one = $this->db->fetch("SELECT id, tipo FROM usuarios WHERE id = :id", ['id' => $destinoId]);
                if ($one) {
                    $recipients[] = ['user_id' => (int) $one['id'], 'role' => $one['tipo'] === 'admin' ? 'admin_escola' : $one['tipo']];
                }
                break;
        }
        return $recipients;
    }

    /** Responsáveis que devem receber pelo aplicativo Android (FCM). */
    private function resolveMobileParentIds(string $tipoDestino, ?int $destinoId): array
    {
        if (in_array($tipoDestino, ['todos', 'pais'], true)) {
            $rows = $this->db->fetchAll('SELECT id FROM responsaveis WHERE ativo = 1 ORDER BY id');
            return array_map('intval', array_column($rows, 'id'));
        }

        if ($tipoDestino === 'responsavel' && $destinoId) {
            $row = $this->db->fetch('SELECT id FROM responsaveis WHERE id = :id AND ativo = 1', ['id' => $destinoId]);
            return $row ? [(int) $row['id']] : [];
        }

        if ($tipoDestino === 'usuario' && $destinoId) {
            $row = $this->db->fetch(
                'SELECT id FROM responsaveis WHERE usuario_id = :usuario_id AND ativo = 1 LIMIT 1',
                ['usuario_id' => $destinoId]
            );
            return $row ? [(int) $row['id']] : [];
        }

        if ($tipoDestino !== 'turma' || !$destinoId) {
            return [];
        }

        $rows = $this->db->fetchAll(
            "SELECT DISTINCT r.id
             FROM responsaveis r
             INNER JOIN (
                 SELECT a.responsavel_id AS responsavel_id
                 FROM alunos a
                 WHERE a.ativo = 1 AND a.turma_id = :turma_legacy AND a.responsavel_id IS NOT NULL
                 UNION
                 SELECT ar.responsavel_id
                 FROM alunos a
                 INNER JOIN alunos_responsaveis ar ON ar.aluno_id = a.id AND ar.ativo = 1
                 WHERE a.ativo = 1 AND a.turma_id = :turma_links
             ) vinculos ON vinculos.responsavel_id = r.id
             WHERE r.ativo = 1
             ORDER BY r.id",
            ['turma_legacy' => $destinoId, 'turma_links' => $destinoId]
        );
        return array_map('intval', array_column($rows, 'id'));
    }

    private function getResponsaveisParaSelect(): array
    {
        return $this->db->fetchAll(
            "SELECT id, nome, email
             FROM responsaveis
             WHERE ativo = 1
             ORDER BY nome"
        );
    }

    /**
     * Usuários para select (admin + lista combinada para "usuario")
     */
    private function getUsuariosParaSelect()
    {
        try {
            $sql = "SELECT u.id, u.nome, u.email, u.tipo
                    FROM usuarios u
                    LEFT JOIN alunos a ON a.usuario_id = u.id AND u.tipo = 'aluno'
                    LEFT JOIN professores p ON p.usuario_id = u.id AND u.tipo = 'professor'
                    LEFT JOIN responsaveis pa ON pa.usuario_id = u.id AND u.tipo = 'pai'
                    WHERE u.tipo IN ('aluno','professor','pai','admin_escola')
                    AND (a.id IS NOT NULL OR p.id IS NOT NULL OR pa.id IS NOT NULL OR u.tipo = 'admin_escola')
                    AND (COALESCE(a.ativo, p.ativo, pa.ativo, 1) = 1)
                    ORDER BY u.tipo, u.nome";
            return $this->db->fetchAll($sql);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'usuario_id') !== false || strpos($e->getMessage(), 'Column not found') !== false) {
                return $this->db->fetchAll(
                    "SELECT id, nome, email, tipo FROM usuarios WHERE tipo IN ('aluno','professor','pai','admin_escola') ORDER BY tipo, nome"
                );
            }
            throw $e;
        }
    }
}
