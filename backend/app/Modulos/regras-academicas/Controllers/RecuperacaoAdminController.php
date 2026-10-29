<?php

namespace App\Modulos\RegrasAcademicas\Controllers;

use App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig;
use App\Modulos\RegrasAcademicas\Services\RecuperacaoConfigService;

if (!class_exists(__NAMESPACE__ . '\\RecuperacaoAdminController', false)) {
class RecuperacaoAdminController extends \App\Controllers\Admin\AdminBaseController
{
    private RecuperacaoConfigService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new RecuperacaoConfigService();
    }

    public function index(): void
    {
        if (!$this->permitido('visualizar')) {
            return;
        }
        $ano = $this->anoPedido();
        $model = $this->service->model();
        if ($model->colunaRegraPronta()) {
            $this->setFlashMessage('As recuperações ficam dentro de cada regra de aprovação.', 'success');
            $this->redirect('/admin/regras-academicas');
            return;
        }
        $flash = $this->getFlashMessage();
        $textos = RecuperacaoConfig::textosDoAno($ano);
        $this->viewWithLayout('admin', 'admin/regras-academicas/recuperacao', [
            'title' => 'Recuperação - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'recuperacao',
            'ano' => $ano,
            'anos' => $this->anos($ano),
            'itens' => $model->tabelaPronta() ? $model->listar($ano) : [],
            'schema_pronto' => $model->tabelaPronta(),
            'quandos' => $textos['quandos'],
            'rotulos_quando' => $textos['rotulos'],
            'onde_lanca' => $textos['onde'],
            'aplica' => $textos['aplica'],
            'aplica_ajuda' => $textos['aplica_ajuda'],
            'ajuda_recuperacao' => $textos['ajuda'],
            'efeitos' => RecuperacaoConfig::EFEITOS,
            'pode_cadastrar' => $this->permitido('cadastrar', true),
            'pode_alterar' => $this->permitido('alterar', true),
            'pode_excluir' => $this->permitido('excluir', true),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => ($flash['type'] ?? '') === 'error' ? 'error' : (($flash['message'] ?? '') !== '' ? 'success' : ''),
            'flash_message' => (string) ($flash['message'] ?? ''),
        ]);
    }

    public function lista(): void
    {
        if (!$this->enforceAdminPermissionKey('regras_academicas', 'visualizar', true)) {
            return;
        }
        $ano = (int) ($_GET['ano'] ?? 0);
        $regraId = (int) ($_GET['regra'] ?? 0);
        $model = $this->service->model();
        $pronto = $model->tabelaPronta();
        $textos = RecuperacaoConfig::textosDoAno($ano);
        if (!$pronto || $regraId <= 0) {
            $itens = [];
        } else {
            $itens = $model->listarDaRegra($regraId);
        }
        $saida = [];
        foreach ($itens as $item) {
            $quando = (string) ($item['quando'] ?? '');
            $efeito = (string) ($item['efeito'] ?? '');
            $aplica = (string) ($item['aplica_em'] ?? '');
            $saida[] = [
                'id' => (int) ($item['id'] ?? 0),
                'nome' => (string) ($item['nome'] ?? ''),
                'quando' => $quando,
                'quando_rotulo' => (string) ($textos['rotulos'][$quando] ?? $quando),
                'efeito_rotulo' => (string) (RecuperacaoConfig::EFEITOS[$efeito] ?? $efeito),
                'aplica_rotulo' => (string) ($textos['aplica'][$aplica] ?? ''),
                'permite_aumentar' => (int) ($item['permite_aumentar'] ?? 0),
                'nota_minima' => $item['nota_minima'] !== null ? number_format((float) $item['nota_minima'], 2, ',', '.') : '—',
                'nota_maxima' => $item['nota_maxima'] !== null ? number_format((float) $item['nota_maxima'], 2, ',', '.') : '—',
            ];
        }
        $this->json([
            'schema_pronto' => $pronto,
            'isolado' => $model->colunaRegraPronta(),
            'bloqueado' => !$model->colunaRegraPronta() && $model->regrasNoAno($ano) > 1,
            'itens' => $saida,
            'textos' => [
                'quandos' => $textos['quandos'],
                'onde' => $textos['onde'],
                'aplica' => $textos['aplica'],
                'aplica_ajuda' => $textos['aplica_ajuda'],
                'ajuda' => $textos['ajuda'],
            ],
        ]);
    }

    public function dados($id): void
    {
        if (!$this->permitido('visualizar')) {
            return;
        }
        $item = $this->service->model()->findById((int) $id);
        if ($item === null) {
            $this->json(['error' => 'Recuperação não encontrada.'], 404);
            return;
        }
        $regraPedida = (int) ($_GET['regra'] ?? 0);
        $regraItem = (int) ($item['regra_id'] ?? 0);
        if ($regraPedida > 0 && $regraItem > 0 && $regraPedida !== $regraItem) {
            $this->json(['error' => 'Esta recuperação pertence a outra regra.'], 404);
            return;
        }
        $this->json([
            'id' => (int) $item['id'],
            'ano_letivo' => (int) $item['ano_letivo'],
            'nome' => (string) $item['nome'],
            'quando' => (string) $item['quando'],
            'nota_minima' => $item['nota_minima'] !== null ? number_format((float) $item['nota_minima'], 2, '.', '') : '',
            'nota_maxima' => $item['nota_maxima'] !== null ? number_format((float) $item['nota_maxima'], 2, '.', '') : '',
            'efeito' => (string) $item['efeito'],
            'aplica_em' => (string) ($item['aplica_em'] ?? ''),
            'formula' => (string) ($item['formula'] ?? ''),
            'permite_aumentar' => (int) ($item['permite_aumentar'] ?? 0),
        ]);
    }

    public function salvar(): void
    {
        if (!$this->permitido('cadastrar')) {
            return;
        }
        $this->gravar(0);
    }

    public function atualizar($id): void
    {
        if (!$this->permitido('alterar')) {
            return;
        }
        $this->gravar((int) $id);
    }

    public function excluir($id): void
    {
        if (!$this->permitido('excluir')) {
            return;
        }
        $id = (int) $id;
        $item = $this->service->model()->findById($id);
        $ano = (int) ($item['ano_letivo'] ?? $this->anoPedido());
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->responder(false, 'Token inválido. Tente novamente.', '/admin/regras-academicas/recuperacao?ano=' . $ano, 403);
            return;
        }
        if ($item === null) {
            $this->responder(false, 'Recuperação não encontrada.', '/admin/regras-academicas/recuperacao?ano=' . $ano, 404);
            return;
        }
        $regraItem = (int) ($item['regra_id'] ?? 0);
        $regraPost = (int) ($_POST['regra_id'] ?? 0);
        if ($regraItem > 0 && $regraPost !== $regraItem) {
            $this->responder(false, 'Esta recuperação pertence a outra regra.', '/admin/regras-academicas/recuperacao?ano=' . $ano, 403);
            return;
        }
        $this->service->model()->excluir($id, $regraItem);
        $this->responder(true, 'Recuperação excluída.', '/admin/regras-academicas/recuperacao?ano=' . $ano);
    }

    private function gravar(int $id): void
    {
        $ano = (int) ($_POST['ano_letivo'] ?? $this->anoPedido());
        $voltar = '/admin/regras-academicas/recuperacao?ano=' . $ano;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->responder(false, 'Token inválido. Tente novamente.', $voltar, 403);
            return;
        }
        $res = $this->service->salvar($_POST, $id);
        if (empty($res['success'])) {
            $this->responder(false, (string) ($res['error'] ?? 'Não foi possível salvar.'), $voltar, 422);
            return;
        }
        $this->responder(true, $id > 0 ? 'Recuperação atualizada.' : 'Recuperação cadastrada.', $voltar);
    }

    private function querJson(): bool
    {
        if ((string) ($_POST['_ajax'] ?? '') === '1') {
            return true;
        }
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        return str_contains($accept, 'application/json');
    }

    private function responder(bool $ok, string $mensagem, string $voltar, int $status = 200): void
    {
        if ($this->querJson()) {
            $this->json([
                'success' => $ok,
                'message' => $ok ? $mensagem : '',
                'error' => $ok ? '' : $mensagem,
            ], $ok ? 200 : $status);
        }
        $this->setFlashMessage($mensagem, $ok ? 'success' : 'error');
        $this->redirect($voltar);
    }

    private function anoPedido(): int
    {
        if (!isset($_GET['ano']) || (int) $_GET['ano'] <= 0) {
            return $this->anoPadrao();
        }
        $ano = (int) $_GET['ano'];
        if ($ano < 2000 || $ano > 2100) {
            return $this->anoPadrao();
        }
        return $ano;
    }

    private function anoPadrao(): int
    {
        $hoje = (int) date('Y');
        $escolhido = 0;
        foreach ($this->anos(0) as $ano) {
            if ($ano <= $hoje && $ano > $escolhido) {
                $escolhido = $ano;
            }
        }
        return $escolhido > 0 ? $escolhido : $hoje;
    }

    /**
     * @return list<int>
     */
    private function anos(int $anoAtual): array
    {
        $anos = [];
        try {
            $rows = $this->db->fetchAll(
                'SELECT ano FROM ano_letivo WHERE ano IS NOT NULL ORDER BY ano DESC'
            ) ?: [];
            foreach ($rows as $row) {
                $ano = (int) ($row['ano'] ?? 0);
                if ($ano >= 2000 && $ano <= 2100) {
                    $anos[$ano] = $ano;
                }
            }
        } catch (\Throwable $e) {
            $anos = [];
        }
        if ($anoAtual >= 2000 && $anoAtual <= 2100 && !isset($anos[$anoAtual])) {
            $anos[$anoAtual] = $anoAtual;
        }
        krsort($anos);
        return array_values($anos);
    }

    private function permitido(string $acao, bool $apenasConsulta = false): bool
    {
        if ($apenasConsulta) {
            $user = $this->auth->getUser();
            $permissions = \App\Core\AdminPermissionMatrix::effectivePermissionsForUser($this->db, $user ?? []);
            return \App\Core\AdminPermissionMatrix::can($permissions, 'regras_academicas', $acao);
        }
        return $this->enforceAdminPermissionKey('regras_academicas', $acao, $this->querJson());
    }
}
}

if (!class_exists('RecuperacaoAdminController', false) && class_exists(__NAMESPACE__ . '\\RecuperacaoAdminController', false)) {
    class_alias(__NAMESPACE__ . '\\RecuperacaoAdminController', 'RecuperacaoAdminController');
}
