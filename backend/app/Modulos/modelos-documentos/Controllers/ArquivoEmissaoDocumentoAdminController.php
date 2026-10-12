<?php

namespace App\Modulos\ModelosDocumentos\Controllers;
/**
 * Painel de emissão versionada de declarações e autorizações.
 */


use App\Modulos\ModelosDocumentos\Services\ArquivoEmissaoDocumentoService;
use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;

if (!class_exists(__NAMESPACE__ . '\\ArquivoEmissaoDocumentoAdminController', false)) {
class ArquivoEmissaoDocumentoAdminController extends \App\Controllers\Admin\AdminBaseController
{
    private ArquivoEmissaoDocumentoService $arquivo;

    public function __construct()
    {
        parent::__construct();
        $this->arquivo = new ArquivoEmissaoDocumentoService($this->db);
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('declaracoes_aluno', 'visualizar', false)) {
            return;
        }
        $filtro = $this->filtroDaRequest();
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/modelos-documentos/arquivo-emissoes', [
            'title' => 'Documentos — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'paineis_documentos',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'flash_status' => ($flash['message'] ?? '') !== ''
                ? (($flash['type'] ?? '') === 'success' ? 'success' : 'error')
                : '',
            'csrf_token' => $this->generateCsrfToken(),
            'tabela_pronta' => $this->arquivo->tabelaPronta(),
            'catalogo' => $this->arquivo->catalogo(),
            'opcoes' => $this->arquivo->opcoesFiltro(),
            'alunos' => $this->arquivo->alunosDaTurma($filtro['turma_id']),
            'emissoes' => $this->arquivo->listar($filtro),
            'filtro' => $filtro,
            'categorias' => ModeloDocumentoService::CATEGORIAS,
        ]);
    }

    public function gerar(): void
    {
        if (!$this->enforceAdminPermissionKey('declaracoes_aluno', 'alterar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? $_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect($this->urlFiltro());
            return;
        }
        $valores = $_POST['c'] ?? [];
        if (!is_array($valores)) {
            $valores = [];
        }
        $escopo = [
            'ano' => (int) ($_POST['ano'] ?? 0),
            'serie_id' => (int) ($_POST['serie_id'] ?? 0),
            'turma_id' => (int) ($_POST['turma_id'] ?? 0),
            'aluno_id' => (int) ($_POST['aluno_id'] ?? 0),
        ];
        try {
            $id = $this->arquivo->gerar(
                (int) ($_POST['modelo_id'] ?? 0),
                $escopo,
                $valores,
                $this->config,
                $this->auth->getUser() ?: null
            );
            $this->setFlashMessage('Versão guardada. O PDF fica disponível para baixar de novo.', 'success');
            $this->redirect($this->urlFiltro() . (str_contains($this->urlFiltro(), '?') ? '&' : '?') . 'gerado=' . $id);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect($this->urlFiltro());
        } catch (\Throwable $e) {
            error_log('ArquivoEmissaoDocumento gerar: ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível gerar o documento.', 'error');
            $this->redirect($this->urlFiltro());
        }
    }

    public function pdf(int $id): void
    {
        if (!$this->enforceAdminPermissionKey('declaracoes_aluno', 'visualizar', false)) {
            return;
        }
        $arquivo = $this->arquivo->baixar($id, $this->config);
        if ($arquivo === null) {
            $this->setFlashMessage('Esta versão não está mais no arquivo.', 'error');
            $this->redirect('/admin/paineis/documentos');
            return;
        }
        $old = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $arquivo['nome'] . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            echo $arquivo['binario'];
            exit;
        } finally {
            ini_set('display_errors', (string) $old);
        }
    }

    /**
     * @return array{ano:int,serie_id:int,turma_id:int,aluno_id:int,modelo_id:int}
     */
    private function filtroDaRequest(): array
    {
        $fonte = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $ano = (int) ($fonte['ano'] ?? 0);
        if ($ano <= 0) {
            $ano = (int) date('Y');
        }
        return [
            'ano' => $ano,
            'serie_id' => (int) ($fonte['serie_id'] ?? 0),
            'turma_id' => (int) ($fonte['turma_id'] ?? 0),
            'aluno_id' => (int) ($fonte['aluno_id'] ?? 0),
            'modelo_id' => (int) ($fonte['modelo_id'] ?? 0),
        ];
    }

    private function urlFiltro(): string
    {
        $filtro = $this->filtroDaRequest();
        return '/admin/paineis/documentos?' . http_build_query([
            'ano' => $filtro['ano'],
            'serie_id' => $filtro['serie_id'],
            'turma_id' => $filtro['turma_id'],
            'aluno_id' => $filtro['aluno_id'],
            'modelo_id' => $filtro['modelo_id'],
        ]);
    }
}
}

if (!class_exists('ArquivoEmissaoDocumentoAdminController', false) && class_exists(__NAMESPACE__ . '\\ArquivoEmissaoDocumentoAdminController', false)) {
    class_alias(__NAMESPACE__ . '\\ArquivoEmissaoDocumentoAdminController', 'ArquivoEmissaoDocumentoAdminController');
}
