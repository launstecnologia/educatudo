<?php

namespace App\Modulos\Redacoes\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Core\Database;
use App\Modulos\Redacoes\Services\RedacaoAlunoIaService;
use App\Modulos\Redacoes\Services\RedacaoAlunoLegadoService;
use App\Infra\Services\MediaStorageService;

/**
 * Fluxo legado /redacoes (páginas + ownership + IA/créditos).
 * URLs preservadas em Modulos/redacoes/routes.php.
 */
if (!class_exists('AlunoRedacaoLegadoController')) {
class AlunoRedacaoLegadoController extends BaseController
{
    private $authManager;
    private $db;
    private RedacaoAlunoLegadoService $servico;
    private RedacaoAlunoIaService $servicoIa;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        $this->servico = new RedacaoAlunoLegadoService($this->db);
        $this->servicoIa = new RedacaoAlunoIaService($this->db);

        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        $user = $this->authManager->getUser();
        if (($user['tipo'] ?? '') !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
        }
    }

    private function redirectToCorrectDashboard(string $tipo): void
    {
        switch ($tipo) {
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'admin':
            case 'admin_escola':
                $this->redirect('/admin/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/dashboard');
        }
    }

    /** GET /redacoes — hub legado (temas, redações, rascunhos) sem IA. */
    public function hub(): void
    {
        try {
            $user = $this->authManager->getUser();
            if (!$user) {
                $this->redirect('/logout');
                return;
            }
            $aluno = $this->servico->buscarAlunoComTurma((int) ($user['id'] ?? 0));
            if (!$aluno) {
                $this->redirect('/logout');
                return;
            }

            $this->generateCsrfToken();
            $this->viewWithLayout('student', 'student/essays', [
                'title' => 'Redação - EducaTudo',
                'user' => $user,
                'aluno' => $aluno,
                'temas_disponiveis' => $this->servico->listarTemasDisponiveis(),
                'redacoes_aluno' => $this->servico->listarRedacoesHub((int) $aluno['id']),
                'rascunhos' => $this->servico->listarRascunhosHub((int) $aluno['id']),
                'current_page' => 'essays',
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
        } catch (\Throwable $e) {
            error_log('AlunoRedacaoLegadoController::hub: ' . $e->getMessage());
            if (defined('DEBUG') && DEBUG) {
                throw $e;
            }
            http_response_code(500);
            echo '<h1>Erro Interno</h1><p>Ocorreu um erro inesperado. Tente novamente mais tarde.</p>';
        }
    }

    /** POST /redacoes/salvar-rascunho — sem IA/créditos. */
    public function salvarRascunho(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        try {
            $user = $this->authManager->getUser();
            if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }
            $resultado = $this->servico->salvarRascunho((int) $user['id'], [
                'rascunho_id' => $_POST['rascunho_id'] ?? null,
                'titulo' => $_POST['titulo'] ?? 'Rascunho sem título',
                'conteudo' => $_POST['conteudo'] ?? '',
                'tema_texto' => $_POST['tema_texto'] ?? null,
                'tempo_escrita' => isset($_POST['tempo_escrita']) ? (int) $_POST['tempo_escrita'] : 0,
            ]);
            $this->json($resultado);
        } catch (\Throwable $e) {
            error_log('Erro ao salvar rascunho: ' . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /** GET /redacoes/historico */
    public function historico(): void
    {
        $user = $this->authManager->getUser();
        if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
            $this->redirect(URL . '/');
            return;
        }

        $redacoes = $this->servico->listarHistorico((int) $user['id']);

        $this->viewWithLayout('student', 'student/historico-redacoes', [
            'title' => 'Histórico de Redações - EducaTudo',
            'user' => $user,
            'redacoes' => $redacoes,
        ]);
    }

    /** GET /redacoes/{id} */
    public function ver($id): void
    {
        $user = $this->authManager->getUser();
        if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
            $this->redirect(URL . '/');
            return;
        }

        $redacao = $this->servico->buscarDoAluno((int) $id, (int) $user['id']);
        if (!$redacao) {
            $_SESSION['error_message'] = 'Redação não encontrada';
            $this->redirect('/redacoes/historico');
            return;
        }

        $feedback = null;
        if (!empty($redacao['feedback_ia'])) {
            $feedback = json_decode((string) $redacao['feedback_ia'], true);
        }

        $this->viewWithLayout('student', 'student/ver-redacao', [
            'title' => 'Redação - EducaTudo',
            'user' => $user,
            'redacao' => $redacao,
            'feedback' => $feedback,
        ]);
    }

    /** GET /redacoes/escrever */
    public function escrever(): void
    {
        $user = $this->authManager->getUser();
        $tema = $_SESSION['tema_gerado'] ?? null;
        if ($tema) {
            unset($_SESSION['tema_gerado']);
        }

        $this->generateCsrfToken();
        $this->viewWithLayout('student', 'student/escrever-redacao', [
            'title' => 'Escrever Redação - EducaTudo',
            'user' => $user,
            'tema' => $tema,
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }

    /** GET /redacoes/escrever-livre */
    public function escreverLivre(): void
    {
        $user = $this->authManager->getUser();
        $rascunhoId = isset($_GET['rascunho_id']) ? (int) $_GET['rascunho_id'] : 0;
        $rascunho = null;
        if ($rascunhoId > 0 && $user) {
            $rascunho = $this->servico->buscarRascunhoDoAluno($rascunhoId, (int) $user['id']);
        }

        $this->generateCsrfToken();
        $this->viewWithLayout('student', 'student/escrever-redacao-livre', [
            'title' => 'Redação Livre - EducaTudo',
            'user' => $user,
            'rascunho' => $rascunho,
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }

    /** GET /redacoes/transcrever */
    public function transcreverPagina(): void
    {
        $user = $this->authManager->getUser();
        $this->generateCsrfToken();
        $this->viewWithLayout('student', 'student/transcrever-imagem', [
            'title' => 'Transcrição de Imagem - EducaTudo',
            'user' => $user,
        ]);
    }

    /** GET /redacoes/rascunho/{id} */
    public function continuarRascunho($id): void
    {
        try {
            $user = $this->authManager->getUser();
            if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
                $this->redirect('/redacoes');
                return;
            }

            $rascunho = $this->servico->buscarRascunhoDoAluno((int) $id, (int) $user['id']);
            if (!$rascunho) {
                $_SESSION['error_message'] = 'Rascunho não encontrado';
                $this->redirect('/redacoes');
                return;
            }

            $tema = null;
            $isRedacaoLivre = false;
            if (!empty($rascunho['tema_texto'])) {
                $temaDecodificado = json_decode((string) $rascunho['tema_texto'], true);
                if ($temaDecodificado && is_array($temaDecodificado)) {
                    $tema = $temaDecodificado;
                } else {
                    $isRedacaoLivre = true;
                    $tema = ['titulo' => $rascunho['tema_texto']];
                }
            } elseif (empty($rascunho['tema_id'])) {
                $isRedacaoLivre = true;
            }

            if ($isRedacaoLivre) {
                $this->redirect('/redacoes/escrever-livre?rascunho_id=' . (int) $id);
                return;
            }

            $this->generateCsrfToken();
            $this->viewWithLayout('student', 'student/escrever-redacao', [
                'title' => 'Continuar Redação - EducaTudo',
                'user' => $user,
                'tema' => $tema,
                'rascunho' => $rascunho,
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
        } catch (\Throwable $e) {
            error_log('Erro ao continuar rascunho: ' . $e->getMessage());
            $_SESSION['error_message'] = 'Erro ao carregar rascunho';
            $this->redirect('/redacoes');
        }
    }

    /** POST /redacoes/rascunho/{id}/excluir */
    public function excluirRascunho($id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        try {
            $user = $this->authManager->getUser();
            if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }

            $alunoId = (int) $user['id'];
            $rid = (int) $id;
            $exige = $this->servico->colunaEhRascunhoExiste();
            $rascunho = $this->servico->buscarRascunhoParaExcluir($rid, $alunoId, $exige);
            if (!$rascunho) {
                $this->json(['error' => 'Rascunho não encontrado'], 404);
                return;
            }

            $this->servico->excluirRascunho($rid, $alunoId, $exige);
            $this->json(['success' => true, 'message' => 'Rascunho excluído com sucesso']);
        } catch (\Throwable $e) {
            error_log('Erro ao excluir rascunho: ' . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /redacoes/{id}/ocultar
     * CSRF obrigatório no controller (proteção efetiva mesmo com CSRF_MODE=audit).
     */
    public function ocultar($id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        try {
            $user = $this->authManager->getUser();
            if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }

            $ok = $this->servico->ocultarDoAluno((int) $id, (int) $user['id']);
            if (!$ok) {
                $this->json(['error' => 'Redação não encontrada'], 404);
                return;
            }

            $this->json(['success' => true, 'message' => 'Redação oculta com sucesso']);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /** POST /redacoes/criar */
    public function criar(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        try {
            $user = $this->authManager->getUser();
            if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }
            $out = $this->servicoIa->criarRedacao((int) $user['id'], [
                'tema_id' => $_POST['tema_id'] ?? null,
                'titulo' => $_POST['titulo'] ?? '',
                'conteudo' => $_POST['conteudo'] ?? '',
                'tipo' => $_POST['tipo'] ?? 'padrao',
                'tema_gerado' => $_POST['tema_gerado'] ?? null,
            ]);
            $this->json($out);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /** POST /redacoes/gerar-tema — TudiCoins + limite diário (comportamento legado). */
    public function gerarTema(): void
    {
        try {
            $user = $this->authManager->getUser();
            if (!$user) {
                $_SESSION['error_message'] = 'Usuário não está logado';
                $this->redirectHtml('/redacoes');
                return;
            }
            if (($user['tipo'] ?? '') !== 'aluno') {
                $_SESSION['error_message'] = 'Acesso negado: apenas alunos podem gerar temas';
                $this->redirectHtml('/redacoes');
                return;
            }
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $_SESSION['error_message'] = 'Token inválido';
                $this->redirectHtml('/redacoes');
                return;
            }
            $resultado = $this->servicoIa->gerarTema((int) $user['id'], (string) ($_POST['theme_request'] ?? ''));
            if (empty($resultado['ok'])) {
                $_SESSION['error_message'] = (string) ($resultado['erro'] ?? 'Erro ao gerar tema');
                $this->redirectHtml('/redacoes');
                return;
            }
            $_SESSION['tema_gerado'] = $resultado['tema'];
            $this->redirectHtml('/redacoes/escrever');
        } catch (\Throwable $e) {
            $_SESSION['error_message'] = 'Erro ao gerar tema: ' . $e->getMessage();
            $this->redirectHtml('/redacoes');
        }
    }

    /** POST /redacoes/transcrever-imagem — sem TudiCoins (legado). */
    public function transcreverImagem(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (ob_get_level()) {
            ob_clean();
        }
        if (!isset($_POST['_token']) || $_POST['_token'] === '') {
            $this->json([
                'error' => 'Token CSRF não enviado. Recarregue a página e tente novamente.',
                'csrf_token' => $this->refreshCsrfToken(),
            ], 400);
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'])) {
            $this->json([
                'error' => 'Token CSRF inválido. Recarregue a página e tente novamente.',
                'csrf_token' => $this->refreshCsrfToken(),
            ], 400);
            return;
        }
        try {
            $user = $this->authManager->getUser();
            if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }
            if (!isset($_FILES['imagem'])) {
                throw new \RuntimeException('Nenhuma imagem foi enviada');
            }
            $media = new MediaStorageService($this->config);
            $out = $this->servicoIa->transcreverImagem((int) $user['id'], $_FILES['imagem'], $media);
            if (!empty($out['ok'])) {
                $payload = ['success' => true, 'texto_transcrito' => $out['texto_transcrito']];
                if (!empty($out['warning'])) {
                    $payload['warning'] = $out['warning'];
                }
                $this->json($payload);
                return;
            }
            $this->json([
                'success' => false,
                'error' => $out['erro'] ?? 'Erro na transcrição',
                'tipo_erro' => $out['tipo_erro'] ?? 'geral',
                'is_quota_error' => !empty($out['is_quota_error']),
            ], 400);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /** POST /redacoes/corrigir — limite diário; sem TudiCoins no legado atual. */
    public function corrigir(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $_SESSION['error_message'] = 'Token inválido';
            $this->redirectHtml('/redacoes');
            return;
        }
        try {
            $user = $this->authManager->getUser();
            if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
                $_SESSION['error_message'] = 'Acesso negado';
                $this->redirectHtml('/redacoes');
                return;
            }
            $resultado = $this->servicoIa->corrigir((int) $user['id'], [
                'redacao_id' => $_POST['redacao_id'] ?? null,
                'conteudo' => $_POST['conteudo'] ?? '',
                'titulo' => $_POST['titulo'] ?? 'Redação',
                'tema' => $_POST['tema'] ?? 'Redação',
            ]);
            if (empty($resultado['ok'])) {
                $_SESSION['error_message'] = (string) ($resultado['erro'] ?? 'Erro ao corrigir redação');
                $this->redirectHtml('/redacoes');
                return;
            }
            $_SESSION['success_message'] = 'Redação corrigida com sucesso!';
            $this->redirect(URL . '/redacoes/' . (int) $resultado['redacao_id']);
        } catch (\Throwable $e) {
            $_SESSION['error_message'] = 'Erro ao corrigir redação: ' . $e->getMessage();
            $this->redirectHtml('/redacoes');
        }
    }

    private function redirectHtml(string $path): void
    {
        $url = rtrim((string) (defined('URL') ? URL : ''), '/') . $path;
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecionando...</title></head><body>'
            . '<script>window.location.href = ' . json_encode($url) . ';</script>'
            . '<p>Redirecionando...</p></body></html>';
        exit;
    }
}
}

if (!class_exists('AlunoRedacaoLegadoController', false) && class_exists(__NAMESPACE__ . '\\AlunoRedacaoLegadoController', false)) {
    class_alias(__NAMESPACE__ . '\\AlunoRedacaoLegadoController', 'AlunoRedacaoLegadoController');
}
