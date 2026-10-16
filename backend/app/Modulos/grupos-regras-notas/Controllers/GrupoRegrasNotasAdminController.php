<?php

namespace App\Modulos\GruposRegrasNotas\Controllers;

use App\Modulos\GruposRegrasNotas\Models\GrupoRegrasNotas;
use App\Modulos\GruposRegrasNotas\Services\GrupoRegrasNotasService;
use Throwable;

/**
 * Admin — cadastro de Quadro de Notas.
 */


if (!class_exists(__NAMESPACE__ . '\\GrupoRegrasNotasAdminController', false)) {
class GrupoRegrasNotasAdminController extends \App\Controllers\Admin\AdminBaseController
{
    /** @var GrupoRegrasNotasService */
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new GrupoRegrasNotasService();
    }

    public function index(): void
    {
        if ($this->recusarAcessoQuadro('visualizar')) {
            return;
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/grupos-regras-notas/index', [
            'title' => 'Quadro de Notas — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'quadros-notas',
            'itens' => $this->service->model()->listar(),
            'schema_pronto' => $this->service->model()->tabelasProntas(),
            'pode_cadastrar' => $this->podeAcaoQuadro('cadastrar'),
            'retorno' => $this->retornoQuadro((string) ($_GET['retorno'] ?? '')),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function novo(): void
    {
        if ($this->recusarAcessoQuadro('cadastrar')) {
            return;
        }
        $this->renderFormulario(null);
    }

    public function editar($id): void
    {
        if ($this->recusarAcessoQuadro('alterar')) {
            return;
        }
        $item = $this->service->carregarCompleto((int) $id);
        if ($item === null) {
            $this->setFlashMessage('Quadro não encontrado.', 'error');
            $this->redirect('/admin/grupos-regras-notas');
            return;
        }
        $item['modo'] = $this->service->modoDoGrupo($item);
        $this->renderFormulario($item);
    }

    public function salvar(): void
    {
        if ($this->recusarAcessoQuadro('cadastrar')) {
            return;
        }
        $retorno = $this->retornoQuadro((string) ($_POST['retorno'] ?? ''));
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect($this->urlFormularioQuadro(0, $retorno));
            return;
        }
        $result = $this->service->salvar($_POST, null);
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível salvar.', 'error');
            $this->redirect($this->urlFormularioQuadro(0, $retorno));
            return;
        }
        $this->redirecionarAposSalvar(true, (int) ($result['id'] ?? 0), $retorno);
    }

    public function atualizar($id): void
    {
        if ($this->recusarAcessoQuadro('alterar')) {
            return;
        }
        $retorno = $this->retornoQuadro((string) ($_POST['retorno'] ?? ''));
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect($this->urlFormularioQuadro((int) $id, $retorno));
            return;
        }
        $result = $this->service->salvar($_POST, (int) $id);
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível atualizar.', 'error');
            $this->redirect($this->urlFormularioQuadro((int) $id, $retorno));
            return;
        }
        $this->redirecionarAposSalvar(false, (int) $id, $retorno);
    }

    public function excluir($id): void
    {
        if ($this->recusarAcessoQuadro('excluir')) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/grupos-regras-notas');
            return;
        }
        if ($this->service->model()->findById((int) $id) === null) {
            $this->setFlashMessage('Quadro não encontrado.', 'error');
            $this->redirect('/admin/grupos-regras-notas');
            return;
        }
        $result = $this->service->excluirGrupo((int) $id);
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível excluir.', 'error');
            $this->redirect('/admin/grupos-regras-notas');
            return;
        }
        $this->setFlashMessage('Quadro de notas excluído.', 'success');
        $this->redirect('/admin/grupos-regras-notas');
    }

    public function dadosJson($id): void
    {
        if (!$this->service->moduloAtivo()) {
            $this->json(['ok' => false, 'error' => 'O Quadro de Notas não está habilitado nesta escola.'], 403);
            return;
        }
        if (!$this->podeAcaoQuadro('visualizar')) {
            $this->json(['ok' => false, 'error' => 'Sem permissão para consultar o Quadro de Notas.'], 403);
            return;
        }
        $payload = $this->service->payloadPublico((int) $id);
        if ($payload === null) {
            $this->json(['ok' => false, 'error' => 'Grupo não encontrado.'], 404);
            return;
        }
        $this->json(['ok' => true, 'grupo' => $payload]);
    }

    public function proximaColunaJson($id): void
    {
        if (!$this->service->moduloAtivo()) {
            $this->json(['ok' => false, 'error' => 'O Quadro de Notas não está habilitado nesta escola.'], 403);
            return;
        }
        if (!$this->podeAcaoQuadro('visualizar')) {
            $this->json(['ok' => false, 'error' => 'Sem permissão para consultar o Quadro de Notas.'], 403);
            return;
        }
        $turmaIds = [];
        $rawTurmas = $_GET['turma_ids'] ?? [];
        if (!is_array($rawTurmas)) {
            $rawTurmas = preg_split('/[,\s]+/', (string) $rawTurmas) ?: [];
        }
        foreach ($rawTurmas as $tid) {
            $n = (int) $tid;
            if ($n > 0) {
                $turmaIds[$n] = true;
            }
        }
        $dataProva = (string) ($_GET['data_prova'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $dataProva)) {
            $dataProva = '';
        } else {
            $dataProva = substr($dataProva, 0, 10);
        }
        $prox = $this->service->proximaColunaDoBloco(
            (int) $id,
            (int) ($_GET['tipo_id'] ?? 0),
            (int) ($_GET['ano'] ?? 0),
            (int) ($_GET['bimestre'] ?? 0),
            (int) ($_GET['exceto_bloco_id'] ?? 0),
            array_map('intval', array_keys($turmaIds)),
            $dataProva
        );
        $this->json($prox, !empty($prox['ok']) ? 200 : 422);
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    private function listarTiposNota(): array
    {
        try {
            $rows = (new \App\Models\Exams\ExamEvaluationType())->getAllActive();
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = ['id' => $id, 'nome' => (string) ($r['nome'] ?? '')];
        }
        return $out;
    }

    /**
     * @param array<string,mixed>|null $item
     */
    private function renderFormulario(?array $item): void
    {
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/grupos-regras-notas/form', [
            'title' => ($item ? 'Editar' : 'Novo') . ' quadro de notas — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'quadros-notas',
            'item' => $item,
            'retorno' => $this->retornoQuadro((string) ($_GET['retorno'] ?? $_POST['retorno'] ?? '')),
            'materias' => $this->service->model()->listarMaterias(),
            'schema_pronto' => $this->service->model()->tabelasProntas(),
            'numero_max' => GrupoRegrasNotas::NUMERO_MAX,
            'tipos_nota' => $this->listarTiposNota(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    private function redirecionarAposSalvar(bool $ehNovo, int $quadroId, string $retorno): void
    {
        $mensagem = $ehNovo ? 'Quadro de notas cadastrado.' : 'Quadro de notas atualizado.';
        if ($retorno !== '' && $quadroId > 0) {
            $mensagem .= ' Ele já pode ser escolhido na avaliação. As notas dos alunos não foram geradas.';
            $_SESSION['boletim_flash'] = $mensagem;
            $_SESSION['boletim_flash_type'] = 'success';
            $this->redirect($this->urlRetornoComQuadro($retorno, $quadroId));
            return;
        }
        $this->setFlashMessage($mensagem, 'success');
        $this->redirect('/admin/quadros-notas');
    }

    private function recusarAcessoQuadro(string $acao): bool
    {
        if (!$this->service->moduloAtivo()) {
            $this->renderAcessoQuadro(
                'O Quadro de Notas não está habilitado nesta escola.',
                'Peça a quem administra os módulos da escola para ativá-lo. O Lançamento de Notas continua em outra tela: é nele que a nota é digitada.'
            );
            return true;
        }
        if ($this->podeAcaoQuadro($acao)) {
            return false;
        }
        $texto = [
            'cadastrar' => 'Você não tem permissão para criar um Quadro de Notas.',
            'alterar' => 'Você não tem permissão para alterar um Quadro de Notas.',
            'excluir' => 'Você não tem permissão para excluir um Quadro de Notas.',
        ];
        $this->renderAcessoQuadro(
            $texto[$acao] ?? 'Você não tem permissão para consultar o Quadro de Notas.',
            'O quadro é o molde das colunas. Peça essa permissão a um administrador da escola. Nada foi gravado nesta tentativa.'
        );
        return true;
    }

    private function podeAcaoQuadro(string $acao): bool
    {
        $permissoes = \App\Core\AdminPermissionMatrix::effectivePermissionsForUser($this->db, $this->auth->getUser() ?? []);
        return \App\Core\AdminPermissionMatrix::can($permissoes, 'grupos_regras_notas', $acao);
    }

    private function renderAcessoQuadro(string $titulo, string $detalhe): void
    {
        $this->viewWithLayout('admin', 'admin/grupos-regras-notas/index', [
            'title' => 'Quadro de Notas — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'quadros-notas',
            'acesso_bloqueado' => true,
            'acesso_titulo' => $titulo,
            'acesso_detalhe' => $detalhe,
            'itens' => [],
            'schema_pronto' => true,
            'pode_cadastrar' => false,
            'retorno' => '',
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => '',
            'flash_message' => '',
        ]);
    }

    private function retornoQuadro(string $bruto): string
    {
        $bruto = trim($bruto);
        if ($bruto === '' || preg_match('#^\s*(https?:)?//#i', $bruto) || str_contains($bruto, '\\') || str_contains($bruto, '..') || preg_match('/[\r\n]/', $bruto)) {
            return '';
        }
        $partes = parse_url($bruto);
        if (!is_array($partes)) {
            return '';
        }
        $path = rawurldecode((string) ($partes['path'] ?? ''));
        if (str_contains($path, '..') || !preg_match('#^/admin/(boletim-configuracao|boletins)(/|$)#', $path)) {
            return '';
        }
        $query = (string) ($partes['query'] ?? '');
        if ($query !== '' && preg_match('/[\r\n]/', $query)) {
            return '';
        }
        if (str_contains($path, '/assistente') && !str_contains($query, 'continuar=1')) {
            $query = $query === '' ? 'continuar=1' : $query . '&continuar=1';
        }

        return $path . ($query !== '' ? '?' . $query : '');
    }

    private function urlFormularioQuadro(int $id, string $retorno): string
    {
        $url = $id > 0 ? '/admin/quadros-notas/' . $id . '/editar' : '/admin/quadros-notas/novo';
        if ($retorno === '') {
            return $url;
        }

        return $url . '?retorno=' . rawurlencode($retorno);
    }

    private function urlRetornoComQuadro(string $retorno, int $quadroId): string
    {
        $sep = str_contains($retorno, '?') ? '&' : '?';
        $extra = 'quadro_id=' . $quadroId;
        if (str_contains($retorno, '/assistente') && !str_contains($retorno, 'continuar=1')) {
            $extra .= '&continuar=1';
        }

        return $retorno . $sep . $extra;
    }
}
}

if (!class_exists('GrupoRegrasNotasAdminController', false) && class_exists(__NAMESPACE__ . '\\GrupoRegrasNotasAdminController', false)) {
    class_alias(__NAMESPACE__ . '\\GrupoRegrasNotasAdminController', 'GrupoRegrasNotasAdminController');
}
