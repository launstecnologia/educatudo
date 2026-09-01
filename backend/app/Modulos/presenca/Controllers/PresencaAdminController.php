<?php

require_once __DIR__ . '/../../../Controllers/Admin/AdminBaseController.php';
require_once __DIR__ . '/../Models/PresencaConfig.php';
require_once __DIR__ . '/../Models/PresencaEvento.php';
require_once __DIR__ . '/../Models/PresencaIntegracao.php';
require_once __DIR__ . '/../Models/PresencaIdentificador.php';
require_once __DIR__ . '/../Services/PresencaEventoService.php';
require_once __DIR__ . '/../Services/PresencaAplicacaoService.php';

if (!class_exists('PresencaAdminController')) {
class PresencaAdminController extends AdminBaseController
{
    /** @var PresencaConfig */
    private $configModel;
    /** @var PresencaEvento */
    private $eventos;
    /** @var PresencaIntegracao */
    private $integracoes;
    /** @var PresencaIdentificador */
    private $identificadores;
    /** @var PresencaEventoService */
    private $service;
    /** @var PresencaAplicacaoService */
    private $aplicacao;

    public function __construct()
    {
        parent::__construct();
        $this->configModel = new PresencaConfig();
        $this->eventos = new PresencaEvento();
        $this->integracoes = new PresencaIntegracao();
        $this->identificadores = new PresencaIdentificador();
        $this->service = new PresencaEventoService();
        $this->aplicacao = new PresencaAplicacaoService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('presenca', 'visualizar', false)) {
            return;
        }
        $data = $this->dataValida((string) ($_GET['data'] ?? date('Y-m-d')));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $lista = $this->eventos->listar($data, $page, 10);
        $flash = $this->getFlashMessage();
        $total = (int) ($lista['total'] ?? 0);
        $this->viewWithLayout('admin', 'admin/presenca/index', [
            'title' => 'Gestão de Presença — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'presenca',
            'flash_message' => $flash['message'] ?? '',
            'flash_type' => $flash['type'] ?? '',
            'flash_status' => ($flash['type'] ?? '') === 'error' ? 'error' : (($flash['message'] ?? '') !== '' ? 'success' : ''),
            'schema_pronto' => $this->eventos->tabelasProntas(),
            'csrf_token' => $this->generateCsrfToken(),
            'data_filtro' => $data,
            'eventos' => $lista['rows'],
            'total' => $total,
            'page' => $page,
            'per_page' => 10,
            'total_pages' => max(1, (int) ceil($total / 10)),
            'token_gerado' => $_SESSION['presenca_token_gerado'] ?? null,
            'webhook_url' => rtrim((string) (defined('URL') ? URL : ''), '/') . '/api/webhooks/presenca',
        ]);
        unset($_SESSION['presenca_token_gerado']);
    }

    public function registrar(): void
    {
        if (!$this->enforceAdminPermissionKey('presenca', 'alterar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/presenca');
            return;
        }
        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $tipo = (string) ($_POST['tipo'] ?? '');
        $quando = trim((string) ($_POST['ocorrido_em'] ?? ''));
        $dataRedirect = $this->dataValida(substr($quando, 0, 10) ?: date('Y-m-d'));
        $ok = false;
        try {
            if ($alunoId <= 0) {
                throw new InvalidArgumentException('Selecione o aluno.');
            }
            if (!in_array($tipo, ['entrada', 'saida'], true)) {
                throw new InvalidArgumentException('Informe entrada ou saída.');
            }
            try {
                $dt = $quando !== '' ? new DateTimeImmutable($quando) : new DateTimeImmutable('now');
            } catch (Throwable $e) {
                throw new InvalidArgumentException('Data e horário inválidos.');
            }
            $user = $this->auth->getUser();
            $this->service->registrar([
                'aluno_id' => $alunoId,
                'tipo' => $tipo,
                'ocorrido_em' => $dt->format('Y-m-d H:i:s'),
                'origem' => 'manual_secretaria',
                'id_externo' => 'manual:' . bin2hex(random_bytes(8)),
                'registrado_por' => isset($user['id']) ? (int) $user['id'] : null,
            ]);
            $this->setFlashMessage('Registro de ' . ($tipo === 'entrada' ? 'entrada' : 'saída') . ' aplicado nas aulas do dia.', 'success');
            $ok = true;
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
        } catch (Throwable $e) {
            error_log('PresencaAdminController::registrar: ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível registrar a entrada/saída.', 'error');
        }
        $qs = 'data=' . urlencode($dataRedirect);
        if (!$ok) {
            $qs .= '&novo=1';
        }
        $this->redirect('/admin/presenca?' . $qs);
    }

    public function buscarAlunos(): void
    {
        if (!$this->enforceAdminPermissionKey('presenca', 'visualizar', true)) {
            return;
        }
        $q = (string) ($_GET['q'] ?? '');
        $this->json(['success' => true, 'alunos' => $this->service->buscarAlunos($q)]);
    }

    public function linhaDoTempo(): void
    {
        if (!$this->enforceAdminPermissionKey('presenca', 'visualizar', true)) {
            return;
        }
        $alunoId = (int) ($_GET['aluno_id'] ?? 0);
        $data = $this->dataValida((string) ($_GET['data'] ?? date('Y-m-d')));
        $eventos = $alunoId > 0 ? $this->eventos->doAlunoNoDia($alunoId, $data) : [];
        $aulas = $alunoId > 0 ? $this->aplicacao->aulasDoAlunoNoDia($alunoId, $data) : [];
        $this->json(['success' => true, 'eventos' => $eventos, 'aulas' => $aulas]);
    }

    public function config(): void
    {
        if (!$this->enforceAdminPermissionKey('presenca', 'visualizar', false)) {
            return;
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/presenca/config', [
            'title' => 'Configurar Presença — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'presenca',
            'flash_message' => $flash['message'] ?? '',
            'flash_type' => $flash['type'] ?? '',
            'schema_pronto' => $this->configModel->tabelasProntas(),
            'csrf_token' => $this->generateCsrfToken(),
            'config' => $this->configModel->obter(),
            'integracoes' => $this->integracoes->listar(),
            'identificadores' => $this->identificadores->listar(200),
            'token_gerado' => $_SESSION['presenca_token_gerado'] ?? null,
            'webhook_url' => rtrim((string) (defined('URL') ? URL : ''), '/') . '/api/webhooks/presenca',
        ]);
        unset($_SESSION['presenca_token_gerado']);
    }

    public function salvarConfig(): void
    {
        if (!$this->enforceAdminPermissionKey('presenca', 'alterar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/presenca/config');
            return;
        }
        try {
            $this->configModel->salvar($_POST);
            $this->setFlashMessage('Configuração salva.', 'success');
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
        } catch (Throwable $e) {
            error_log('PresencaAdminController::salvarConfig: ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível salvar a configuração.', 'error');
        }
        $this->redirect('/admin/presenca/config');
    }

    public function criarIntegracao(): void
    {
        if (!$this->enforceAdminPermissionKey('presenca', 'alterar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/presenca/config');
            return;
        }
        try {
            $criada = $this->integracoes->criar($_POST);
            $_SESSION['presenca_token_gerado'] = $criada['token'];
            $this->setFlashMessage('Integração criada. Copie o token agora — ele não será exibido de novo.', 'success');
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
        } catch (Throwable $e) {
            error_log('PresencaAdminController::criarIntegracao: ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível criar a integração.', 'error');
        }
        $this->redirect('/admin/presenca/config');
    }

    public function toggleIntegracao(): void
    {
        if (!$this->enforceAdminPermissionKey('presenca', 'alterar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/presenca/config');
            return;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $ativo = (string) ($_POST['ativo'] ?? '0') === '1';
        $this->integracoes->setAtivo($id, $ativo);
        $this->setFlashMessage($ativo ? 'Integração ativada.' : 'Integração desativada.', 'success');
        $this->redirect('/admin/presenca/config');
    }

    public function criarIdentificador(): void
    {
        if (!$this->enforceAdminPermissionKey('presenca', 'alterar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/presenca/config');
            return;
        }
        try {
            $this->identificadores->criar(
                (int) ($_POST['aluno_id'] ?? 0),
                (string) ($_POST['tipo'] ?? 'cartao'),
                (string) ($_POST['valor'] ?? '')
            );
            $this->setFlashMessage('Identificador vinculado ao aluno.', 'success');
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
        } catch (Throwable $e) {
            error_log('PresencaAdminController::criarIdentificador: ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível vincular o identificador.', 'error');
        }
        $this->redirect('/admin/presenca/config');
    }

    public function excluirIdentificador(): void
    {
        if (!$this->enforceAdminPermissionKey('presenca', 'excluir', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/presenca/config');
            return;
        }
        $this->identificadores->excluir((int) ($_POST['id'] ?? 0));
        $this->setFlashMessage('Identificador removido.', 'success');
        $this->redirect('/admin/presenca/config');
    }

    private function dataValida(string $data): string
    {
        $dt = DateTime::createFromFormat('Y-m-d', $data);
        return ($dt && $dt->format('Y-m-d') === $data) ? $data : date('Y-m-d');
    }
}
}
