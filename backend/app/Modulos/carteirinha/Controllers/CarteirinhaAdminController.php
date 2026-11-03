<?php

namespace App\Modulos\Carteirinha\Controllers;

use App\Core\AdminPermissionMatrix;
use App\Modulos\Carteirinha\Services\CarteirinhaService;

if (!class_exists(__NAMESPACE__ . '\\CarteirinhaAdminController', false)) {
class CarteirinhaAdminController extends \App\Painel\Admin\Controllers\AdminBaseController
{
    private CarteirinhaService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new CarteirinhaService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('carteirinha', 'visualizar', false)) {
            return;
        }
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $busca = trim((string) ($_GET['busca'] ?? ''));
        if (mb_strlen($busca) < 2) {
            $busca = '';
        }
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $filtrar = $turmaId > 0 || $busca !== '';
        $lista = ['rows' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
        $schema = $this->service->tabelasProntas();
        if ($schema && $filtrar && $this->service->habilitada()) {
            $lista = $this->service->listar($turmaId, $busca, $pagina);
        }
        $flash = $this->getFlashMessage();
        $user = $this->auth->getUser();
        $permissoes = AdminPermissionMatrix::effectivePermissionsForUser($this->db, $user ?? []);
        $this->viewWithLayout('admin', 'admin/carteirinha/index', [
            'title' => 'Carteirinha - EducaTudo',
            'user' => $user,
            'current_page' => 'carteirinha',
            'csrf_token' => $this->generateCsrfToken(),
            'flash_message' => $flash['message'] ?? '',
            'flash_type' => $flash['type'] ?? '',
            'schema_pronto' => $schema,
            'habilitada' => $schema && $this->service->habilitada(),
            'pode_alterar' => AdminPermissionMatrix::can($permissoes, 'carteirinha', 'alterar'),
            'turmas' => $schema ? $this->service->turmas() : [],
            'turma_id' => $turmaId,
            'busca' => trim((string) ($_GET['busca'] ?? '')),
            'filtrar' => $filtrar,
            'alunos' => $lista['rows'],
            'total' => $lista['total'],
            'pagina' => $lista['pagina'],
            'total_paginas' => $lista['total_paginas'],
        ]);
    }

    public function salvarUso(): void
    {
        if (!$this->enforceAdminPermissionKey('carteirinha', 'alterar', false)) {
            return;
        }
        if (!$this->csrfOk()) {
            return;
        }
        try {
            $valor = $_POST['habilitada'] ?? '0';
            if (is_array($valor)) {
                $ligada = in_array('1', $valor, true);
            } else {
                $ligada = (string) $valor === '1';
            }
            $this->service->definirHabilitada($ligada);
            $this->setFlashMessage(
                $ligada ? 'Carteirinha habilitada nesta escola.' : 'Carteirinha desabilitada nesta escola.',
                'success'
            );
        } catch (\Throwable $e) {
            $this->setFlashMessage($this->mensagemErro($e), 'error');
        }
        $this->redirect('/admin/carteirinha');
    }

    public function emitir(): void
    {
        $this->acao(function (): void {
            $alunoId = (int) ($_POST['aluno_id'] ?? 0);
            $this->service->emitir($alunoId, $this->usuarioId(), true);
            $this->setFlashMessage('Carteirinha emitida.', 'success');
        });
    }

    public function emitirTurma(): void
    {
        $this->acao(function (): void {
            $turmaId = (int) ($_POST['turma_id'] ?? 0);
            $resultado = $this->service->emitirTurma($turmaId, $this->usuarioId());
            $emitidas = (int) $resultado['emitidas'];
            $puladas = (int) $resultado['puladas'];
            if ($emitidas === 0) {
                $this->setFlashMessage('Nenhuma carteirinha nova. Quem já tinha uma válida foi mantido.', 'success');
                return;
            }
            $texto = $emitidas === 1 ? '1 carteirinha emitida.' : $emitidas . ' carteirinhas emitidas.';
            if ($puladas > 0) {
                $texto .= ' ' . $puladas . ' já tinham carteirinha válida.';
            }
            $this->setFlashMessage($texto, 'success');
        });
    }

    public function cancelar(): void
    {
        $this->acao(function (): void {
            $this->service->cancelar((int) ($_POST['id'] ?? 0));
            $this->setFlashMessage('Carteirinha cancelada. O QR antigo deixa de valer.', 'success');
        });
    }

    public function imprimir(): void
    {
        if (!$this->enforceAdminPermissionKey('carteirinha', 'visualizar', false)) {
            return;
        }
        try {
            $cartoes = $this->service->paraImpressao(
                (int) ($_GET['aluno_id'] ?? 0),
                (int) ($_GET['turma_id'] ?? 0)
            );
        } catch (\Throwable $e) {
            $this->setFlashMessage($this->mensagemErro($e), 'error');
            $this->redirect('/admin/carteirinha');
            return;
        }
        if ($cartoes === []) {
            $this->setFlashMessage('Não há carteirinha emitida para imprimir.', 'error');
            $this->redirect('/admin/carteirinha');
            return;
        }
        $this->renderImpressao($cartoes);
    }

    /**
     * @param list<array<string,mixed>> $cartoes
     */
    private function renderImpressao(array $cartoes): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $escola = (string) ($cartoes[0]['escola'] ?? 'EducaTudo');
        require dirname(__DIR__) . '/Views/impressao.php';
    }

    private function acao(callable $fn): void
    {
        if (!$this->enforceAdminPermissionKey('carteirinha', 'alterar', false)) {
            return;
        }
        if (!$this->csrfOk()) {
            return;
        }
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->setFlashMessage($this->mensagemErro($e), 'error');
        }
        $this->voltarLista();
    }

    private function csrfOk(): bool
    {
        if ($this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            return true;
        }
        $this->setFlashMessage('Token inválido. Atualize a página e tente de novo.', 'error');
        $this->voltarLista();
        return false;
    }

    private function voltarLista(): void
    {
        $q = [];
        $turmaId = (int) ($_POST['turma_id'] ?? $_GET['turma_id'] ?? 0);
        $pagina = (int) ($_POST['pagina'] ?? 1);
        $busca = trim((string) ($_POST['busca'] ?? ''));
        if ($turmaId > 0) {
            $q['turma_id'] = $turmaId;
        }
        if ($pagina > 1) {
            $q['pagina'] = $pagina;
        }
        if ($busca !== '') {
            $q['busca'] = $busca;
        }
        $this->redirect('/admin/carteirinha' . ($q !== [] ? '?' . http_build_query($q) : ''));
    }

    private function usuarioId(): int
    {
        $user = $this->auth->getUser();
        return (int) ($user['id'] ?? 0);
    }

    private function mensagemErro(\Throwable $e): string
    {
        if ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException) {
            return $e->getMessage();
        }
        error_log('Carteirinha: ' . $e->getMessage());
        return 'Não foi possível concluir. Tente de novo.';
    }
}
}
