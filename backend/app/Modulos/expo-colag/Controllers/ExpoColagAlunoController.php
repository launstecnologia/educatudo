<?php
/**
 * Expo Colag — área do aluno (mural, detalhe e inscrição).
 */

require_once __DIR__ . '/../Services/ExpoColagService.php';
require_once __DIR__ . '/../Services/ExpoColagExecucaoService.php';

if (!class_exists('ExpoColagAlunoController')) {
class ExpoColagAlunoController extends BaseController
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

    private function requireAluno(): array
    {
        $user = $this->auth->getUser();
        if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
            $this->redirect('/login');
            exit;
        }
        return $user;
    }

    public function index(): void
    {
        $user = $this->requireAluno();
        $filtros = [
            'area' => trim((string) ($_GET['area'] ?? '')),
            'so_com_vagas' => !empty($_GET['so_com_vagas']),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];
        $projetos = $this->service->listarMuralAluno((int) $user['id'], $filtros);
        $secoes = $this->service->organizarMural($projetos);
        $edicaoResult = $this->service->obterOuCriarEdicaoAtiva();

        $areas = [];
        foreach ($projetos as $p) {
            $a = trim((string) ($p['area'] ?? ''));
            if ($a !== '') {
                $areas[$a] = $a;
            }
        }
        // Áreas de todos os visíveis (sem filtro area) para o select
        if ($filtros['area'] !== '') {
            $todos = $this->service->listarMuralAluno((int) $user['id'], array_merge($filtros, ['area' => '']));
            $areas = [];
            foreach ($todos as $p) {
                $a = trim((string) ($p['area'] ?? ''));
                if ($a !== '') {
                    $areas[$a] = $a;
                }
            }
        }

        $this->viewWithLayout('student', 'aluno/expo-colag/index', [
            'user' => $user,
            'current_page' => 'expo-colag',
            'page_title' => 'Expo Colag',
            'projetos' => $projetos,
            'secoes' => $secoes,
            'filtros' => $filtros,
            'areas' => array_values($areas),
            'edicao' => $edicaoResult['edicao'] ?? null,
            'minhas_inscricoes' => $this->service->listarInscricoesAluno((int) $user['id']),
        ]);
    }

    public function projeto($id): void
    {
        $user = $this->requireAluno();
        $projeto = $this->service->obterProjeto((int) $id);
        if (
            !$projeto
            || empty($projeto['ativo'])
            || in_array($projeto['status'] ?? '', ['Rascunho', 'Cancelado'], true)
        ) {
            $this->setFlashMessage('Projeto não encontrado.', 'error');
            $this->redirect('/expo-colag');
            return;
        }

        $ctx = $this->service->contextoVisibilidadeAluno((int) $user['id']);
        if (!$this->service->alunoPodeVerProjeto((int) $id, $ctx)) {
            $this->setFlashMessage('Projeto não encontrado.', 'error');
            $this->redirect('/expo-colag');
            return;
        }

        $completo = $this->service->carregarProjetoCompleto((int) $id);
        $statusInsc = $this->service->statusInscricaoParaAluno((int) $id, (int) $user['id']);

        $this->viewWithLayout('student', 'aluno/expo-colag/projeto', [
            'user' => $user,
            'current_page' => 'expo-colag',
            'page_title' => $projeto['titulo'] ?? 'Projeto',
            'projeto' => $projeto,
            'relacoes' => $completo['relacoes'] ?? [],
            'status_inscricao' => $statusInsc,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function inscrever($id): void
    {
        $user = $this->requireAluno();
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/expo-colag/projeto/' . (int) $id);
            return;
        }

        $result = $this->service->inscreverAluno((int) $id, (int) $user['id'], $_POST);
        if (!$result['success']) {
            if (!empty($result['requer_confirmacao'])) {
                $this->setFlashMessage($result['error'] . ' Marque a confirmação e envie de novo.', 'error');
            } else {
                $this->setFlashMessage($result['error'] ?? 'Não foi possível se inscrever.', 'error');
            }
            $this->redirect('/expo-colag/projeto/' . (int) $id);
            return;
        }

        $msgs = [
            'Aprovada' => 'Inscrição confirmada! Você faz parte do projeto.',
            'Aguardando' => 'Inscrição enviada. Aguarde a aprovação do professor.',
            'Lista_espera' => 'Projeto lotado. Você entrou na lista de espera.',
        ];
        $st = (string) ($result['status'] ?? 'Aprovada');
        $msg = $msgs[$st] ?? 'Inscrição registrada.';
        if (!empty($result['infos'])) {
            $msg .= ' ' . implode(' ', $result['infos']);
        }
        $this->setFlashMessage($msg, 'success');
        $this->redirect('/expo-colag/projeto/' . (int) $id);
    }

    public function cancelarInscricao($id): void
    {
        $user = $this->requireAluno();
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/expo-colag');
            return;
        }
        $inscricaoId = (int) ($_POST['inscricao_id'] ?? 0);
        $result = $this->service->cancelarInscricaoAluno($inscricaoId, (int) $user['id'], (int) $id);
        $this->setFlashMessage(
            $result['success'] ? 'Inscrição cancelada.' : ($result['error'] ?? 'Erro ao cancelar.'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect('/expo-colag/projeto/' . (int) $id);
    }

    public function painel($id): void
    {
        $user = $this->requireAluno();
        $painel = $this->execucao->painelAluno((int) $id, (int) $user['id']);
        if (!$painel['success']) {
            $this->setFlashMessage($painel['error'] ?? 'Acesso negado.', 'error');
            $this->redirect('/expo-colag/projeto/' . (int) $id);
            return;
        }
        $this->viewWithLayout('student', 'aluno/expo-colag/painel', [
            'user' => $user,
            'current_page' => 'expo-colag',
            'page_title' => 'Meu painel',
            'projeto' => $painel['projeto'],
            'inscricao' => $painel['inscricao'],
            'relacoes' => $painel['relacoes'],
            'tarefas' => $painel['tarefas'],
            'progresso' => $painel['progresso'],
            'materiais' => $painel['materiais'],
            'conteudos' => $painel['conteudos'] ?? [],
            'pedidos' => $painel['pedidos'] ?? [],
            'pode_solicitar_materiais' => !empty($painel['pode_solicitar_materiais']),
            'motivo_solicitacao' => $painel['motivo_solicitacao'] ?? '',
            'mensagens' => $painel['mensagens'] ?? [],
            'stand' => $painel['stand'],
            'url_qr' => $painel['url_qr'],
            'aba' => trim((string) ($_GET['aba'] ?? 'progresso')),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function solicitarMaterial($id): void
    {
        $user = $this->requireAluno();
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/expo-colag/projeto/' . (int) $id . '/painel?aba=materiais');
            return;
        }
        $result = $this->execucao->solicitarMaterialAluno((int) $id, (int) $user['id'], $_POST);
        $this->setFlashMessage(
            $result['success'] ? 'Pedido enviado ao professor.' : ($result['error'] ?? 'Erro ao solicitar.'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect('/expo-colag/projeto/' . (int) $id . '/painel?aba=materiais');
    }

    public function enviarMensagem($id): void
    {
        $user = $this->requireAluno();
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/expo-colag/projeto/' . (int) $id . '/painel?aba=grupo');
            return;
        }
        $result = $this->execucao->enviarMensagemAluno(
            (int) $id,
            (int) $user['id'],
            (string) ($_POST['mensagem'] ?? '')
        );
        $this->setFlashMessage(
            $result['success'] ? 'Mensagem enviada.' : ($result['error'] ?? 'Erro ao enviar.'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect('/expo-colag/projeto/' . (int) $id . '/painel?aba=grupo');
    }

    public function entregarTarefa($id): void
    {
        $user = $this->requireAluno();
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/expo-colag/projeto/' . (int) $id . '/painel?aba=tarefas');
            return;
        }
        $result = $this->execucao->entregarTarefaAluno(
            (int) ($_POST['atribuicao_id'] ?? 0),
            (int) $user['id'],
            $_POST,
            (int) $id
        );
        $this->setFlashMessage(
            $result['success'] ? 'Entrega enviada.' : ($result['error'] ?? 'Erro.'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect('/expo-colag/projeto/' . (int) $id . '/painel?aba=tarefas');
    }

    public function programacao(): void
    {
        $user = $this->requireAluno();
        $prog = $this->execucao->listarProgramacaoPublica();
        $this->viewWithLayout('student', 'aluno/expo-colag/programacao', [
            'user' => $user,
            'current_page' => 'expo-colag',
            'page_title' => 'Programação',
            'itens' => $prog['itens'] ?? [],
            'edicao' => $prog['edicao'] ?? null,
            'ainda_nao_publica' => !empty($prog['ainda_nao_publica']),
        ]);
    }
}
}
