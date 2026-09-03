<?php
/**
 * Expo Colag — painel da coordenação (configuração da edição).
 */

require_once __DIR__ . '/../Services/ExpoColagService.php';
require_once __DIR__ . '/../Services/ExpoColagExecucaoService.php';
require_once __DIR__ . '/../../../Core/FeatureGate.php';

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
            exit;
        }

        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            $this->redirect('/admin/dashboard');
            exit;
        }
        if (!FeatureGate::isModuleEnabled('expo_colag')) {
            $this->redirect('/admin/dashboard');
            exit;
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
        $filtros = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'professor_id' => (int) ($_GET['professor_id'] ?? 0),
        ];
        $result = $this->service->obterOuCriarEdicaoAtiva();
        $authResumo = [];
        try {
            $authResumo = $this->service->autorizacaoImagemResumo()['contagens'] ?? [];
        } catch (Throwable $e) {
            $authResumo = [];
        }
        $catalogos = $this->service->catalogosWizard();
        $indicadores = $this->service->indicadoresAdmin();
        try {
            $indicadores = array_merge($indicadores, $this->execucao->indicadoresExtrasAdmin());
        } catch (Throwable $e) {
            $indicadores['tarefas_atrasadas'] = 0;
            $indicadores['entregas_avaliar'] = 0;
        }

        $this->viewWithLayout('admin', 'professor/expo-colag/index', [
            'user' => $this->auth->getUser(),
            'current_page' => 'expo-colag',
            'page_title' => 'Expo Colag',
            'edicao' => $result['edicao'] ?? null,
            'pode_gerenciar' => $this->podeGerenciar(),
            'autorizacao_contagens' => $authResumo,
            'indicadores' => $indicadores,
            'projetos' => $this->service->listarProjetosAdmin($filtros),
            'pendentes' => $this->service->listarPendentesAdmin(),
            'professores' => $catalogos['professores'] ?? [],
            'filtros' => $filtros,
            'modo_admin' => true,
            'base_url_expo' => URL . '/admin/expo-colag',
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function projetos(): void
    {
        $this->index();
    }

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
        $catalogos = $this->service->catalogosWizard();
        $projeto = null;
        $relacoes = null;

        if ($projetoId > 0) {
            $completo = $this->service->carregarProjetoCompleto($projetoId, null, true);
            if (!$completo['success']) {
                $this->setFlashMessage($completo['error'] ?? 'Projeto não encontrado.', 'error');
                $this->redirect('/admin/expo-colag');
                return;
            }
            $projeto = $completo['projeto'];
            $relacoes = $completo['relacoes'];
        }

        $this->viewWithLayout('admin', 'professor/expo-colag/wizard', [
            'user' => $this->auth->getUser(),
            'current_page' => 'expo-colag',
            'page_title' => $projeto ? 'Editar projeto' : 'Criar projeto',
            'projeto' => $projeto,
            'relacoes' => $relacoes,
            'catalogos' => $catalogos,
            'modo_admin' => true,
            'base_url_expo' => URL . '/admin/expo-colag',
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function acompanhar($id): void
    {
        $painel = $this->execucao->painelAdmin((int) $id);
        if (!$painel['success']) {
            $this->setFlashMessage($painel['error'] ?? 'Projeto não encontrado.', 'error');
            $this->redirect('/admin/expo-colag');
            return;
        }

        $this->viewWithLayout('admin', 'professor/expo-colag/acompanhar', [
            'user' => $this->auth->getUser(),
            'current_page' => 'expo-colag',
            'page_title' => 'Painel do projeto',
            'projeto' => $painel['projeto'],
            'relacoes' => $painel['relacoes'],
            'inscricoes' => $painel['inscricoes'],
            'tarefas' => $painel['tarefas'],
            'atribuicoes' => $painel['atribuicoes'],
            'materiais' => $painel['materiais'],
            'pedidos_materiais' => $painel['pedidos_materiais'] ?? [],
            'mensagens' => $painel['mensagens'] ?? [],
            'stand' => $painel['stand'],
            'url_qr' => $painel['url_qr'],
            'setores' => $painel['setores'],
            'aba' => trim((string) ($_GET['aba'] ?? 'geral')),
            'modo_admin' => true,
            'base_url_expo' => URL . '/admin/expo-colag',
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function salvar(): void
    {
        $wantsJson = $this->wantsJson();

        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $contentLength > 0 && empty($_POST) && empty($_FILES)) {
            $this->responderSalvar(false, 'A capa é grande demais para o servidor. Use JPG/PNG até 10 MB.', null, $wantsJson);
            return;
        }
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->responderSalvar(false, 'Token de segurança inválido.', null, $wantsJson);
            return;
        }

        $user = $this->auth->getUser();
        $capa = $_FILES['capa'] ?? null;
        $result = $this->service->salvarProjetoCompleto((int) ($user['id'] ?? 0), $_POST, is_array($capa) ? $capa : null, true);
        if (!$result['success']) {
            $this->responderSalvar(false, $result['error'] ?? 'Erro ao salvar.', null, $wantsJson);
            return;
        }

        $acao = trim((string) ($_POST['acao'] ?? 'rascunho'));
        $id = (int) $result['id'];
        if ($acao === 'publicar') {
            $pub = $this->service->publicarProjetoAdmin($id);
            if (!$pub['success']) {
                $this->responderSalvar(false, $pub['error'] ?? 'Salvo, mas não publicado.', $id, $wantsJson, null, $result);
                return;
            }
            $this->responderSalvar(true, 'Projeto publicado.', $id, $wantsJson, '/admin/expo-colag', $result);
            return;
        }

        $this->responderSalvar(true, 'Rascunho salvo.', $id, $wantsJson, '/admin/expo-colag/projetos/' . $id . '/editar', $result);
    }

    public function publicar($id): void
    {
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/admin/expo-colag');
            return;
        }
        $result = $this->service->publicarProjetoAdmin((int) $id);
        $this->setFlashMessage(
            $result['success'] ? 'Projeto publicado.' : ($result['error'] ?? 'Não foi possível publicar.'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect($result['success'] ? '/admin/expo-colag' : '/admin/expo-colag/projetos/' . (int) $id . '/editar');
    }

    public function excluir($id): void
    {
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/admin/expo-colag');
            return;
        }
        $result = $this->service->excluirProjeto((int) $id, 0, true);
        $this->setFlashMessage(
            $result['success'] ? 'Projeto excluído.' : ($result['error'] ?? 'Não foi possível excluir.'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect('/admin/expo-colag');
    }

    public function preview($id): void
    {
        $completo = $this->service->carregarProjetoCompleto((int) $id, null, true);
        if (!$completo['success']) {
            $this->setFlashMessage($completo['error'] ?? 'Projeto não encontrado.', 'error');
            $this->redirect('/admin/expo-colag');
            return;
        }

        $this->viewWithLayout('admin', 'professor/expo-colag/preview', [
            'user' => $this->auth->getUser(),
            'current_page' => 'expo-colag',
            'page_title' => 'Pré-visualização',
            'projeto' => $completo['projeto'],
            'relacoes' => $completo['relacoes'],
            'modo_admin' => true,
            'base_url_expo' => URL . '/admin/expo-colag',
        ]);
    }

    public function materiaisPdf($id): void
    {
        $completo = $this->service->carregarProjetoCompleto((int) $id, null, true);
        if (!$completo['success']) {
            $this->setFlashMessage($completo['error'] ?? 'Projeto não encontrado.', 'error');
            $this->redirect('/admin/expo-colag');
            return;
        }

        $projeto = $completo['projeto'];
        $itens = $this->execucao->itensPdfAlmoxarifado((int) $id);
        $escola = class_exists('LayoutHelper') ? (string) LayoutHelper::getSystemTitle() : 'Escola';
        $viewFile = $this->resolveViewPath('professor/expo-colag/materiais_pdf');
        if ($viewFile === null) {
            $this->setFlashMessage('Modelo do PDF não encontrado.', 'error');
            $this->redirect('/admin/expo-colag/projetos/' . (int) $id . '/editar');
            return;
        }

        ob_start();
        $geradoEm = date('d/m/Y H:i');
        include $viewFile;
        $html = (string) ob_get_clean();
        $slug = preg_replace('/[^a-z0-9]+/i', '-', (string) ($projeto['titulo'] ?? 'projeto')) ?: 'projeto';
        $this->enviarPdf($html, 'materiais-' . $slug . '-' . (int) $id . '.pdf');
    }

    public function decidirInscricao($id): void
    {
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/admin/expo-colag/projetos/' . (int) $id . '/acompanhar');
            return;
        }
        $projeto = $this->service->obterProjeto((int) $id);
        $result = $projeto
            ? $this->service->decidirInscricao((int) ($_POST['inscricao_id'] ?? 0), (int) $projeto['professor_id'], trim((string) ($_POST['decisao'] ?? '')), trim((string) ($_POST['motivo_recusa'] ?? '')) ?: null, (int) $id)
            : ['success' => false, 'error' => 'Projeto não encontrado.'];
        $this->setFlashMessage($result['success'] ? 'Decisão registrada.' : ($result['error'] ?? 'Erro.'), $result['success'] ? 'success' : 'error');
        $this->redirect(trim((string) ($_POST['voltar'] ?? '')) === 'index' ? '/admin/expo-colag' : '/admin/expo-colag/projetos/' . (int) $id . '/acompanhar');
    }

    public function criarTarefa($id): void
    {
        $this->executarAcaoProjeto((int) $id, 'tarefas', function (array $projeto) use ($id): array {
            return $this->execucao->criarTarefa((int) $id, (int) $projeto['professor_id'], $_POST);
        }, 'Tarefa criada.');
    }

    public function excluirTarefa($id): void
    {
        $this->executarAcaoProjeto((int) $id, 'tarefas', function (array $projeto) use ($id): array {
            return $this->execucao->excluirTarefa((int) ($_POST['tarefa_id'] ?? 0), (int) $projeto['professor_id'], (int) $id);
        }, 'Tarefa removida.');
    }

    public function decidirAtribuicao($id): void
    {
        $this->executarAcaoProjeto((int) $id, 'tarefas', function (array $projeto) use ($id): array {
            return $this->execucao->decidirAtribuicao(
                (int) ($_POST['atribuicao_id'] ?? 0),
                (int) $projeto['professor_id'],
                trim((string) ($_POST['acao'] ?? '')),
                trim((string) ($_POST['comentario'] ?? '')) ?: null,
                (int) $id
            );
        }, 'Atualizado.');
    }

    public function adicionarMaterial($id): void
    {
        $this->executarAcaoProjeto((int) $id, 'materiais', function (array $projeto) use ($id): array {
            return $this->execucao->adicionarMaterial((int) $id, (int) $projeto['professor_id'], $_POST);
        }, 'Material adicionado.');
    }

    public function removerMaterial($id): void
    {
        $this->executarAcaoProjeto((int) $id, 'materiais', function (array $projeto): array {
            return $this->execucao->removerMaterial((int) ($_POST['material_id'] ?? 0), (int) $projeto['professor_id']);
        }, 'Material removido.');
    }

    public function decidirPedidoMaterial($id): void
    {
        $this->executarAcaoProjeto((int) $id, 'materiais', function (array $projeto) use ($id): array {
            return $this->execucao->decidirPedidoMaterial(
                (int) ($_POST['pedido_id'] ?? 0),
                (int) $projeto['professor_id'],
                trim((string) ($_POST['acao'] ?? '')),
                trim((string) ($_POST['resposta'] ?? '')) ?: null,
                (int) $id
            );
        }, 'Pedido atualizado.');
    }

    public function enviarMensagem($id): void
    {
        $this->executarAcaoProjeto((int) $id, 'grupo', function (array $projeto) use ($id): array {
            return $this->execucao->enviarMensagemProfessor((int) $id, (int) $projeto['professor_id'], (string) ($_POST['mensagem'] ?? ''));
        }, 'Mensagem enviada.', 'Erro ao enviar.');
    }

    public function salvarStand($id): void
    {
        $this->executarAcaoProjeto((int) $id, 'stand', function (array $projeto) use ($id): array {
            $input = $_POST;
            $input['atualizar'] = 1;
            return $this->execucao->garantirStand((int) $id, (int) $projeto['professor_id'], $input);
        }, 'Stand atualizado.');
    }

    public function alunosTurma(): void
    {
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $this->jsonResponse(['success' => true, 'alunos' => $this->service->listarAlunosPorTurma($turmaId)]);
    }

    public function buscarBncc(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $this->jsonResponse(['success' => true, 'habilidades' => $this->service->buscarHabilidadesBncc($q)]);
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

    private function jsonResponse(array $payload, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return strpos($accept, 'application/json') !== false
            || strtolower($xrw) === 'xmlhttprequest'
            || !empty($_POST['ajax']);
    }

    private function responderSalvar(bool $ok, string $msg, ?int $id, bool $json, ?string $redirect = null, ?array $extra = null): void
    {
        if ($json) {
            $this->jsonResponse([
                'success' => $ok,
                'message' => $msg,
                'id' => $id,
                'redirect' => $redirect,
                'capa_url' => $extra['capa_url'] ?? null,
                'capa_src' => $extra['capa_src'] ?? null,
            ], $ok ? 200 : 422);
        }
        $this->setFlashMessage($msg, $ok ? 'success' : 'error');
        if ($ok && $redirect) {
            $this->redirect($redirect);
            return;
        }
        if ($id) {
            $this->redirect('/admin/expo-colag/projetos/' . $id . '/editar');
            return;
        }
        $this->redirect('/admin/expo-colag/criar');
    }

    private function executarAcaoProjeto(int $projetoId, string $aba, callable $callback, string $mensagemSucesso, string $mensagemErro = 'Erro.'): void
    {
        if (!$this->validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token de segurança inválido.', 'error');
            $this->redirect('/admin/expo-colag/projetos/' . $projetoId . '/acompanhar?aba=' . rawurlencode($aba));
            return;
        }

        $projeto = $this->service->obterProjeto($projetoId);
        if (!$projeto) {
            $this->setFlashMessage('Projeto não encontrado.', 'error');
            $this->redirect('/admin/expo-colag');
            return;
        }

        $result = $callback($projeto);
        $this->setFlashMessage(
            !empty($result['success']) ? $mensagemSucesso : ($result['error'] ?? $mensagemErro),
            !empty($result['success']) ? 'success' : 'error'
        );
        $this->redirect('/admin/expo-colag/projetos/' . $projetoId . '/acompanhar?aba=' . rawurlencode($aba));
    }

    private function enviarPdf(string $html, string $filename): void
    {
        $old = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            echo $dompdf->output();
            exit;
        } finally {
            ini_set('display_errors', (string) $old);
        }
    }
}
}
