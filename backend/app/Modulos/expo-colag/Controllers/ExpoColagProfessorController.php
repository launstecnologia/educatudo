<?php
/**
 * Expo Colag — área do professor (wizard criar/editar + acompanhar).
 */

require_once __DIR__ . '/../Services/ExpoColagService.php';
require_once __DIR__ . '/../Services/ExpoColagExecucaoService.php';

if (!class_exists('ExpoColagProfessorController')) {
class ExpoColagProfessorController extends BaseController
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
    }

    private function requireProfessor(): array
    {
        $user = $this->auth->getUser();
        if (!$user || ($user['tipo'] ?? '') !== 'professor') {
            $this->redirect('/login');
            exit;
        }
        return $user;
    }

    private function jsonResponse(array $payload, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function index(): void
    {
        $user = $this->requireProfessor();
        $indicadores = $this->service->indicadoresProfessor((int) $user['id']);
        try {
            $indicadores = array_merge($indicadores, $this->execucao->indicadoresExtrasProfessor((int) $user['id']));
        } catch (Throwable $e) {
            $indicadores['tarefas_atrasadas'] = 0;
            $indicadores['entregas_avaliar'] = 0;
        }
        $projetos = $this->service->listarProjetosProfessor((int) $user['id']);
        $edicaoResult = $this->service->obterOuCriarEdicaoAtiva();
        $pendentes = $this->service->listarPendentesProfessor((int) $user['id']);

        $this->viewWithLayout('professor', 'professor/expo-colag/index', [
            'user' => $user,
            'current_page' => 'expo-colag',
            'page_title' => 'Expo Colag',
            'indicadores' => $indicadores,
            'projetos' => $projetos,
            'pendentes' => $pendentes,
            'edicao' => $edicaoResult['edicao'] ?? null,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function acompanhar($id): void
    {
        $user = $this->requireProfessor();
        $painel = $this->execucao->painelProfessor((int) $id, (int) $user['id']);
        if (!$painel['success']) {
            $this->setFlashMessage($painel['error'] ?? 'Projeto não encontrado.', 'error');
            $this->redirect('/professor/expo-colag');
            return;
        }

        $this->viewWithLayout('professor', 'professor/expo-colag/acompanhar', [
            'user' => $user,
            'current_page' => 'expo-colag',
            'page_title' => 'Acompanhar projeto',
            'projeto' => $painel['projeto'],
            'relacoes' => $painel['relacoes'],
            'inscricoes' => $painel['inscricoes'],
            'tarefas' => $painel['tarefas'],
            'atribuicoes' => $painel['atribuicoes'],
            'materiais' => $painel['materiais'],
            'stand' => $painel['stand'],
            'url_qr' => $painel['url_qr'],
            'setores' => $painel['setores'],
            'aba' => trim((string) ($_GET['aba'] ?? 'geral')),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function criarTarefa($id): void
    {
        $user = $this->requireProfessor();
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/professor/expo-colag/projetos/' . (int) $id . '/acompanhar?aba=tarefas');
            return;
        }
        $result = $this->execucao->criarTarefa((int) $id, (int) $user['id'], $_POST);
        $this->setFlashMessage(
            $result['success'] ? 'Tarefa criada.' : ($result['error'] ?? 'Erro.'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect('/professor/expo-colag/projetos/' . (int) $id . '/acompanhar?aba=tarefas');
    }

    public function excluirTarefa($id): void
    {
        $user = $this->requireProfessor();
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/professor/expo-colag/projetos/' . (int) $id . '/acompanhar?aba=tarefas');
            return;
        }
        $result = $this->execucao->excluirTarefa((int) ($_POST['tarefa_id'] ?? 0), (int) $user['id'], (int) $id);
        $this->setFlashMessage(
            $result['success'] ? 'Tarefa removida.' : ($result['error'] ?? 'Erro.'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect('/professor/expo-colag/projetos/' . (int) $id . '/acompanhar?aba=tarefas');
    }

    public function decidirAtribuicao($id): void
    {
        $user = $this->requireProfessor();
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/professor/expo-colag/projetos/' . (int) $id . '/acompanhar?aba=tarefas');
            return;
        }
        $result = $this->execucao->decidirAtribuicao(
            (int) ($_POST['atribuicao_id'] ?? 0),
            (int) $user['id'],
            trim((string) ($_POST['acao'] ?? '')),
            trim((string) ($_POST['comentario'] ?? '')) ?: null,
            (int) $id
        );
        $this->setFlashMessage(
            $result['success'] ? 'Atualizado.' : ($result['error'] ?? 'Erro.'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect('/professor/expo-colag/projetos/' . (int) $id . '/acompanhar?aba=tarefas');
    }

    public function adicionarMaterial($id): void
    {
        $user = $this->requireProfessor();
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/professor/expo-colag/projetos/' . (int) $id . '/acompanhar?aba=materiais');
            return;
        }
        $result = $this->execucao->adicionarMaterial((int) $id, (int) $user['id'], $_POST);
        $this->setFlashMessage(
            $result['success'] ? 'Material adicionado.' : ($result['error'] ?? 'Erro.'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect('/professor/expo-colag/projetos/' . (int) $id . '/acompanhar?aba=materiais');
    }

    public function removerMaterial($id): void
    {
        $user = $this->requireProfessor();
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/professor/expo-colag/projetos/' . (int) $id . '/acompanhar?aba=materiais');
            return;
        }
        $result = $this->execucao->removerMaterial((int) ($_POST['material_id'] ?? 0), (int) $user['id']);
        $this->setFlashMessage(
            $result['success'] ? 'Material removido.' : ($result['error'] ?? 'Erro.'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect('/professor/expo-colag/projetos/' . (int) $id . '/acompanhar?aba=materiais');
    }

    public function salvarStand($id): void
    {
        $user = $this->requireProfessor();
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/professor/expo-colag/projetos/' . (int) $id . '/acompanhar?aba=stand');
            return;
        }
        $input = $_POST;
        $input['atualizar'] = 1;
        $result = $this->execucao->garantirStand((int) $id, (int) $user['id'], $input);
        $this->setFlashMessage(
            $result['success'] ? 'Stand atualizado.' : ($result['error'] ?? 'Erro.'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect('/professor/expo-colag/projetos/' . (int) $id . '/acompanhar?aba=stand');
    }

    public function decidirInscricao($id): void
    {
        $user = $this->requireProfessor();
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/professor/expo-colag/projetos/' . (int) $id . '/acompanhar');
            return;
        }
        $inscricaoId = (int) ($_POST['inscricao_id'] ?? 0);
        $decisao = trim((string) ($_POST['decisao'] ?? ''));
        $motivo = trim((string) ($_POST['motivo_recusa'] ?? '')) ?: null;

        $result = $this->service->decidirInscricao(
            $inscricaoId,
            (int) $user['id'],
            $decisao,
            $motivo,
            (int) $id
        );
        $this->setFlashMessage(
            $result['success'] ? 'Decisão registrada.' : ($result['error'] ?? 'Erro.'),
            $result['success'] ? 'success' : 'error'
        );

        $voltar = trim((string) ($_POST['voltar'] ?? ''));
        if ($voltar === 'index') {
            $this->redirect('/professor/expo-colag');
            return;
        }
        $this->redirect('/professor/expo-colag/projetos/' . (int) $id . '/acompanhar');
    }

    public function projetos(): void
    {
        $this->index();
    }

    /** Wizard criar (novo) ou editar. */
    public function criar(): void
    {
        $this->wizard(0);
    }

    public function editar($id): void
    {
        $this->wizard((int) $id);
    }

    private function wizard(int $projetoId): void
    {
        $user = $this->requireProfessor();
        $catalogos = $this->service->catalogosWizard();
        $projeto = null;
        $relacoes = null;

        if ($projetoId > 0) {
            $completo = $this->service->carregarProjetoCompleto($projetoId, (int) $user['id']);
            if (!$completo['success']) {
                $this->setFlashMessage($completo['error'] ?? 'Projeto não encontrado.', 'error');
                $this->redirect('/professor/expo-colag');
                return;
            }
            $projeto = $completo['projeto'];
            $relacoes = $completo['relacoes'];
        }

        $this->viewWithLayout('professor', 'professor/expo-colag/wizard', [
            'user' => $user,
            'current_page' => 'expo-colag',
            'page_title' => $projeto ? 'Editar projeto' : 'Criar projeto',
            'projeto' => $projeto,
            'relacoes' => $relacoes,
            'catalogos' => $catalogos,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function salvar(): void
    {
        $user = $this->requireProfessor();
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->responderSalvar(false, 'Token de segurança inválido.', null, true);
            return;
        }

        $capa = $_FILES['capa'] ?? null;
        $result = $this->service->salvarProjetoCompleto((int) $user['id'], $_POST, is_array($capa) ? $capa : null);
        $wantsJson = $this->wantsJson();

        if (!$result['success']) {
            $this->responderSalvar(false, $result['error'] ?? 'Erro ao salvar.', null, $wantsJson);
            return;
        }

        $acao = trim((string) ($_POST['acao'] ?? 'rascunho'));
        $id = (int) $result['id'];

        if ($acao === 'publicar') {
            $pub = $this->service->publicarProjeto($id, (int) $user['id']);
            if (!$pub['success']) {
                $this->responderSalvar(false, $pub['error'] ?? 'Salvo, mas não publicado.', $id, $wantsJson);
                return;
            }
            $this->responderSalvar(true, 'Projeto publicado.', $id, $wantsJson, '/professor/expo-colag');
            return;
        }

        $this->responderSalvar(true, 'Rascunho salvo.', $id, $wantsJson, '/professor/expo-colag/projetos/' . $id . '/editar');
    }

    public function publicar($id): void
    {
        $user = $this->requireProfessor();
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/professor/expo-colag');
            return;
        }
        $result = $this->service->publicarProjeto((int) $id, (int) $user['id']);
        $this->setFlashMessage(
            $result['success'] ? 'Projeto publicado.' : ($result['error'] ?? 'Não foi possível publicar.'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect($result['success'] ? '/professor/expo-colag' : '/professor/expo-colag/projetos/' . (int) $id . '/editar');
    }

    public function preview($id): void
    {
        $user = $this->requireProfessor();
        $completo = $this->service->carregarProjetoCompleto((int) $id, (int) $user['id']);
        if (!$completo['success']) {
            $this->setFlashMessage($completo['error'] ?? 'Projeto não encontrado.', 'error');
            $this->redirect('/professor/expo-colag');
            return;
        }

        $this->viewWithLayout('professor', 'professor/expo-colag/preview', [
            'user' => $user,
            'current_page' => 'expo-colag',
            'page_title' => 'Pré-visualização',
            'projeto' => $completo['projeto'],
            'relacoes' => $completo['relacoes'],
        ]);
    }

    public function alunosTurma(): void
    {
        $this->requireProfessor();
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $this->jsonResponse(['success' => true, 'alunos' => $this->service->listarAlunosPorTurma($turmaId)]);
    }

    public function buscarBncc(): void
    {
        $this->requireProfessor();
        $q = trim((string) ($_GET['q'] ?? ''));
        $this->jsonResponse(['success' => true, 'habilidades' => $this->service->buscarHabilidadesBncc($q)]);
    }

    /** @deprecated S1 — redireciona ao wizard */
    public function salvarRascunho(): void
    {
        $user = $this->requireProfessor();
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido. Tente novamente.', 'error');
            $this->redirect('/professor/expo-colag');
            return;
        }

        $result = $this->service->salvarRascunhoProjeto((int) $user['id'], $_POST);
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível salvar o rascunho.', 'error');
            $this->redirect('/professor/expo-colag');
            return;
        }

        $this->setFlashMessage('Rascunho criado. Complete o wizard.', 'success');
        $this->redirect('/professor/expo-colag/projetos/' . (int) $result['id'] . '/editar');
    }

    private function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return strpos($accept, 'application/json') !== false
            || strtolower($xrw) === 'xmlhttprequest'
            || !empty($_POST['ajax']);
    }

    private function responderSalvar(bool $ok, string $msg, ?int $id, bool $json, ?string $redirect = null): void
    {
        if ($json) {
            $this->jsonResponse([
                'success' => $ok,
                'message' => $msg,
                'id' => $id,
                'redirect' => $redirect,
            ], $ok ? 200 : 422);
        }
        $this->setFlashMessage($msg, $ok ? 'success' : 'error');
        if ($ok && $redirect) {
            $this->redirect($redirect);
            return;
        }
        if ($id) {
            $this->redirect('/professor/expo-colag/projetos/' . $id . '/editar');
            return;
        }
        $this->redirect('/professor/expo-colag/criar');
    }
}
}
