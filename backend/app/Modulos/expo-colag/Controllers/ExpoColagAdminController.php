<?php
/**
 * Expo Colag — painel da coordenação (configuração da edição).
 */

require_once __DIR__ . '/../Services/ExpoColagService.php';
require_once __DIR__ . '/../Services/ExpoColagExecucaoService.php';

if (!class_exists('ExpoColagAdminController')) {
class ExpoColagAdminController extends BaseController
{
    private $auth;
    private ExpoColagService $service;
    private ExpoColagExecucaoService $execucao;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->service = new ExpoColagService();
        $this->execucao = new ExpoColagExecucaoService();

        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
            return;
        }

        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            $this->redirect('/admin/dashboard');
            return;
        }
    }

    private function podeGerenciar(): bool
    {
        $user = $this->auth->getUser();
        $perfil = trim((string) ($user['perfil_admin'] ?? ''));
        if ($perfil === '') {
            return true;
        }
        return in_array($perfil, ['diretor', 'coordenador', 'dev'], true);
    }

    public function index(): void
    {
        $result = $this->service->obterOuCriarEdicaoAtiva();
        $authResumo = [];
        try {
            $authResumo = $this->service->autorizacaoImagemResumo()['contagens'] ?? [];
        } catch (Throwable $e) {
            $authResumo = [];
        }
        $this->viewWithLayout('admin', 'admin/expo-colag/index', [
            'user' => $this->auth->getUser(),
            'current_page' => 'expo-colag',
            'page_title' => 'Expo Colag',
            'edicao' => $result['edicao'] ?? null,
            'pode_gerenciar' => $this->podeGerenciar(),
            'autorizacao_contagens' => $authResumo,
        ]);
    }

    public function autorizacoes(): void
    {
        if (!$this->podeGerenciar()) {
            $this->setFlashMessage('Apenas diretor ou coordenador podem gerenciar autorizações de imagem.', 'error');
            $this->redirect('/admin/expo-colag');
            return;
        }

        $resumo = $this->service->autorizacaoImagemResumo();
        $this->viewWithLayout('admin', 'admin/expo-colag/autorizacoes', [
            'user' => $this->auth->getUser(),
            'current_page' => 'expo-colag',
            'page_title' => 'Autorização de imagem — Expo Colag',
            'contagens' => $resumo['contagens'] ?? [],
            'alunos' => $resumo['alunos'] ?? [],
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function salvarAutorizacao(): void
    {
        if (!$this->podeGerenciar()) {
            $this->setFlashMessage('Apenas diretor ou coordenador podem gerenciar autorizações de imagem.', 'error');
            $this->redirect('/admin/expo-colag');
            return;
        }
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/admin/expo-colag/autorizacoes');
            return;
        }

        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));
        $obs = trim((string) ($_POST['observacao'] ?? '')) ?: null;
        $user = $this->auth->getUser();

        $result = $this->service->registrarAutorizacaoImagem(
            $alunoId,
            $status,
            (int) ($user['id'] ?? 0) ?: null,
            $obs
        );

        $this->setFlashMessage(
            $result['success'] ? 'Autorização atualizada.' : ($result['error'] ?? 'Erro ao salvar.'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect('/admin/expo-colag/autorizacoes');
    }

    public function configuracao(): void
    {
        if (!$this->podeGerenciar()) {
            $this->setFlashMessage('Apenas diretor ou coordenador podem configurar a Expo Colag.', 'error');
            $this->redirect('/admin/expo-colag');
            return;
        }

        $result = $this->service->obterOuCriarEdicaoAtiva();
        $edicao = $result['edicao'] ?? null;
        $config = $edicao['config_decoded'] ?? ExpoColagService::configPadrao();

        $this->viewWithLayout('admin', 'admin/expo-colag/configuracao', [
            'user' => $this->auth->getUser(),
            'current_page' => 'expo-colag',
            'page_title' => 'Configuração da Expo Colag',
            'edicao' => $edicao,
            'config' => $config,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function salvarConfiguracao(): void
    {
        if (!$this->podeGerenciar()) {
            $this->setFlashMessage('Apenas diretor ou coordenador podem configurar a Expo Colag.', 'error');
            $this->redirect('/admin/expo-colag');
            return;
        }

        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido. Tente novamente.', 'error');
            $this->redirect('/admin/expo-colag/configuracao');
            return;
        }

        $edicaoId = (int) ($_POST['edicao_id'] ?? 0);
        if ($edicaoId <= 0) {
            $this->setFlashMessage('Edição inválida.', 'error');
            $this->redirect('/admin/expo-colag/configuracao');
            return;
        }

        $result = $this->service->salvarConfiguracaoEdicao($edicaoId, $_POST);
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Erro ao salvar configuração.', 'error');
            $this->redirect('/admin/expo-colag/configuracao');
            return;
        }

        $this->setFlashMessage('Configuração da edição salva.', 'success');
        $this->redirect('/admin/expo-colag/configuracao');
    }

    public function programacao(): void
    {
        if (!$this->podeGerenciar()) {
            $this->setFlashMessage('Apenas diretor ou coordenador podem gerenciar a programação.', 'error');
            $this->redirect('/admin/expo-colag');
            return;
        }
        $dados = $this->execucao->adminProgramacao();
        $this->viewWithLayout('admin', 'admin/expo-colag/programacao', [
            'user' => $this->auth->getUser(),
            'current_page' => 'expo-colag',
            'page_title' => 'Programação — Expo Colag',
            'edicao' => $dados['edicao'] ?? null,
            'itens' => $dados['itens'] ?? [],
            'setores' => $dados['setores'] ?? [],
            'stands' => $dados['stands'] ?? [],
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function salvarProgramacao(): void
    {
        if (!$this->podeGerenciar()) {
            $this->setFlashMessage('Sem permissão.', 'error');
            $this->redirect('/admin/expo-colag');
            return;
        }
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/admin/expo-colag/programacao');
            return;
        }
        $acao = trim((string) ($_POST['acao'] ?? 'item'));
        if ($acao === 'setor') {
            $result = $this->execucao->criarSetor((string) ($_POST['nome'] ?? ''));
        } elseif ($acao === 'excluir') {
            $result = $this->execucao->excluirItemProgramacao((int) ($_POST['item_id'] ?? 0));
        } else {
            $result = $this->execucao->salvarItemProgramacao($_POST);
        }
        $this->setFlashMessage(
            $result['success'] ? 'Salvo.' : ($result['error'] ?? 'Erro.'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect('/admin/expo-colag/programacao');
    }
}
}
