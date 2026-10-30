<?php

namespace App\Modulos\EventosAvaliativos\Controllers;

use App\Core\PeriodoLetivo;
use App\Modulos\EventosAvaliativos\Models\BoletimLayout;
use App\Modulos\EventosAvaliativos\Models\EventoAvaliativo;
use App\Modulos\EventosAvaliativos\Services\EventoAvaliativoService;
use App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig;
use App\Modulos\RegrasAcademicas\Models\RegraAcademica;
use App\Modulos\RegrasAcademicas\Services\TipoNotaRegraService;
use App\Painel\Admin\Controllers\AdminBaseController;

if (!class_exists(__NAMESPACE__ . '\\EventoAvaliativoAdminController', false)) {
class EventoAvaliativoAdminController extends AdminBaseController
{
    private EventoAvaliativoService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new EventoAvaliativoService();
    }

    public function index(): void
    {
        if (!$this->permitido('visualizar')) {
            return;
        }
        $flash = $this->getFlashMessage();
        $ano = $this->anoNoCadastro((int) ($_GET['ano'] ?? 0));
        $periodoNum = (int) ($_GET['periodo'] ?? 0);
        $pronto = $this->service->model()->tabelasProntas();
        $flashStatus = ($flash['type'] ?? '') === 'error' ? 'error' : (($flash['message'] ?? '') !== '' ? 'success' : '');
        $flashMessage = (string) ($flash['message'] ?? '');

        $pedidoAno = (int) ($_GET['ano'] ?? 0);
        if ($pedidoAno <= 0 && $periodoNum <= 0) {
            $this->listaRegras($flashStatus, $flashMessage);
            return;
        }

        $regraId = (int) ($_GET['regra'] ?? 0);
        $regraNome = '';
        if ($regraId > 0) {
            $regraModel = new RegraAcademica();
            $regra = $regraModel->schemaPronto() ? $regraModel->findById($regraId) : null;
            if ($regra) {
                $regraNome = trim((string) ($regra['nome'] ?? ''));
                $anoRegra = (int) ($regra['ano_letivo'] ?? 0);
                if ($anoRegra >= 2000 && $anoRegra <= 2100) {
                    $ano = $anoRegra;
                }
            } else {
                $regraId = 0;
            }
        }
        $tituloRegra = $regraNome !== '' ? 'Eventos Avaliativos - ' . $regraNome : 'Eventos Avaliativos';

        $recuperacao = new RecuperacaoConfig();
        $periodoEspecial = $recuperacao->codigoAceito($ano, $periodoNum, $regraId);
        if (!$periodoEspecial && ($periodoNum < 1 || !PeriodoLetivo::numeroValido($ano, $periodoNum))) {
            $info = PeriodoLetivo::doAno($ano);
            $contagens = $pronto ? $this->service->model()->contarPorAno($ano) : [];
            $temRecNoPeriodo = false;
            if ($recuperacao->tabelaPronta()) {
                $linhasRec = $regraId > 0 ? $recuperacao->listarDaRegra($regraId) : $recuperacao->listar($ano);
                foreach ($linhasRec as $linhaRec) {
                    $quandoRec = (string) ($linhaRec['quando'] ?? '');
                    $aplicaRec = (string) ($linhaRec['aplica_em'] ?? '');
                    if ($quandoRec === 'bimestre' || ($quandoRec === 'par' && $aplicaRec === 'cada')) {
                        $temRecNoPeriodo = true;
                        break;
                    }
                }
            }
            $this->viewWithLayout('admin', 'admin/eventos-avaliativos/periodos', $this->dados([
                'title' => $tituloRegra . ' - EducaTudo',
                'regra_id' => $regraId,
                'regra_nome' => $regraNome,
                'ano' => $ano,
                'periodo' => $info,
                'contagens' => $contagens,
                'rascunhos' => $this->service->model()->contarRascunhosPorAno($ano),
                'slots' => $recuperacao->slotsParaTela($ano, $contagens, $regraId),
                'tem_rec_no_periodo' => $temRecNoPeriodo,
                'schema_pronto' => $pronto,
                'flash_status' => $flashStatus,
                'flash_message' => $flashMessage,
            ]));
            return;
        }

        $info = PeriodoLetivo::doAno($ano);
        $rotulo = $periodoEspecial
            ? (string) ($recuperacao->rotuloDoCodigo($ano, $periodoNum, $regraId) ?? 'Recuperação')
            : (string) ($info['rotulos'][$periodoNum] ?? ($periodoNum . 'º'));
        $opcoesPeriodo = [];
        foreach ((array) ($info['rotulos'] ?? []) as $numeroOpcao => $rotuloOpcao) {
            $opcoesPeriodo[] = [
                'numero' => (int) $numeroOpcao,
                'rotulo' => (string) $rotuloOpcao,
            ];
        }
        if ($periodoEspecial) {
            $jaTemPeriodo = false;
            foreach ($opcoesPeriodo as $opcaoPeriodo) {
                if ((int) $opcaoPeriodo['numero'] === $periodoNum) {
                    $jaTemPeriodo = true;
                    break;
                }
            }
            if (!$jaTemPeriodo) {
                $opcoesPeriodo[] = ['numero' => $periodoNum, 'rotulo' => $rotulo];
            }
        }
        $this->viewWithLayout('admin', 'admin/eventos-avaliativos/index', $this->dados([
            'title' => $tituloRegra . ' - EducaTudo',
            'regra_id' => $regraId,
            'itens' => $pronto ? $this->service->model()->listar($ano, $periodoNum) : [],
            'schema_pronto' => $pronto,
            'pode_cadastrar' => $this->permitido('cadastrar', true),
            'pode_alterar' => $this->permitido('alterar', true),
            'pode_excluir' => $this->permitido('excluir', true),
            'ano' => $ano,
            'periodo_num' => $periodoNum,
            'periodo_rotulo' => $rotulo,
            'periodos_opcoes' => $opcoesPeriodo,
            'tipo_rotulo' => $periodoEspecial ? 'Recuperação' : 'Avaliação',
            'pode_duplicar' => PeriodoLetivo::numeroValido($ano, $periodoNum) && $periodoNum >= 2 && PeriodoLetivo::numeroValido($ano, $periodoNum - 1),
            'rotulo_anterior' => PeriodoLetivo::numeroValido($ano, $periodoNum - 1) ? PeriodoLetivo::rotulo($ano, $periodoNum - 1) : '',
            'flash_status' => $flashStatus,
            'flash_message' => $flashMessage,
        ]));
    }

    public function duplicar(): void
    {
        if (!$this->permitido('cadastrar') || !$this->exigirSchema()) {
            return;
        }
        $ano = (int) ($_POST['ano'] ?? 0);
        $periodo = (int) ($_POST['periodo'] ?? 0);
        $regra = (int) ($_POST['regra'] ?? 0);
        $volta = '/admin/eventos-avaliativos?ano=' . $ano . '&periodo=' . $periodo . ($regra > 0 ? '&regra=' . $regra : '');
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect($volta);
            return;
        }
        $user = $this->auth->getUser() ?? [];
        $res = $this->service->duplicarDoPeriodoAnterior($ano, $periodo, (int) ($user['id'] ?? 0));
        if (empty($res['success'])) {
            $this->setFlashMessage((string) ($res['error'] ?? 'Não foi possível duplicar os eventos.'), 'error');
            $this->redirect($volta);
            return;
        }
        $criados = (int) ($res['criados'] ?? 0);
        $this->setFlashMessage($criados === 1 ? '1 evento entrou como rascunho.' : $criados . ' eventos entraram como rascunho.', 'success');
        $this->redirect($volta);
    }

    public function publicar($id): void
    {
        if (!$this->permitido('alterar') || !$this->exigirSchema()) {
            return;
        }
        $evento = $this->service->model()->findById((int) $id);
        $ano = (int) ($evento['ano_letivo'] ?? 0);
        $periodo = (int) ($evento['bimestre'] ?? 0);
        $regra = (int) ($_POST['regra'] ?? 0);
        $volta = '/admin/eventos-avaliativos?ano=' . $ano . '&periodo=' . $periodo . ($regra > 0 ? '&regra=' . $regra : '');
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect($volta);
            return;
        }
        if ($evento === null) {
            $this->setFlashMessage('Evento não encontrado.', 'error');
            $this->redirect('/admin/eventos-avaliativos');
            return;
        }
        $this->service->model()->publicar((int) $id);
        $this->setFlashMessage('Evento publicado.', 'success');
        $this->redirect($volta);
    }

    public function criar(): void
    {
        if (!$this->permitido('cadastrar') || !$this->exigirSchema()) {
            return;
        }
        $ano = $this->anoNoCadastro((int) ($_GET['ano'] ?? 0));
        $periodoNum = (int) ($_GET['periodo'] ?? 0);
        $regraId = (int) ($_GET['regra'] ?? 0);
        $recuperacao = new RecuperacaoConfig();
        if (!$recuperacao->codigoAceito($ano, $periodoNum, $regraId) && !PeriodoLetivo::numeroValido($ano, $periodoNum)) {
            $periodoNum = 1;
        }
        $catalogo = $this->service->catalogoFormulario($ano);
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/eventos-avaliativos/form', $this->dados([
            'flash_status' => ($flash['type'] ?? '') === 'error' ? 'error' : (($flash['message'] ?? '') !== '' ? 'success' : ''),
            'flash_message' => (string) ($flash['message'] ?? ''),
            'title' => 'Novo evento avaliativo - EducaTudo',
            'professores' => $catalogo['professores'],
            'materias' => $catalogo['materias'],
            'turmas' => $catalogo['turmas'],
            'periodo' => $catalogo['periodo'],
            'ano_letivo' => $ano,
            'anos' => $this->anosDoCadastro(),
            'bimestre_selecionado' => $periodoNum,
            'regra_id' => $regraId,
            'periodos_extra' => $recuperacao->opcoesFormulario($ano, $periodoNum, $regraId),
            'criterios' => TipoNotaRegraService::CRITERIOS,
            'registros' => TipoNotaRegraService::REGISTROS,
            'criterios_professores' => TipoNotaRegraService::CRITERIOS_PROFESSORES,
            'canais' => TipoNotaRegraService::CANAIS_ORIGEM,
            'modos_offline' => TipoNotaRegraService::MODOS_OFFLINE,
        ]));
    }

    public function alunosRecuperacao(): void
    {
        if (!$this->permitido('cadastrar', true) && !$this->permitido('alterar', true)) {
            $this->json(['ok' => false, 'erro' => 'Sem permissão.'], 403);
            return;
        }
        $ano = (int) ($_GET['ano'] ?? 0);
        $periodo = (int) ($_GET['periodo'] ?? 0);
        $regraId = (int) ($_GET['regra'] ?? 0);
        $turmas = [];
        foreach (explode(',', (string) ($_GET['turmas'] ?? '')) as $parte) {
            $id = (int) $parte;
            if ($id > 0) {
                $turmas[$id] = $id;
            }
        }
        $res = $this->service->candidatosRecuperacao($ano, $periodo, array_values($turmas), $regraId);
        if (isset($res['error'])) {
            $this->json(['ok' => false, 'erro' => $res['error']], 422);
            return;
        }
        $this->json([
            'ok' => true,
            'media' => $res['media'],
            'tabela' => $this->service->model()->colunaMateriaPronta(),
            'alunos' => $res['alunos'],
        ]);
    }

    public function salvar(): void
    {
        if (!$this->permitido('cadastrar') || !$this->exigirSchema()) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect('/admin/eventos-avaliativos/criar?ano=' . (int) ($_POST['ano_letivo'] ?? 0) . '&periodo=' . (int) ($_POST['bimestre'] ?? 0));
            return;
        }
        $user = $this->auth->getUser() ?? [];
        $res = $this->service->criar($_POST, (int) ($user['id'] ?? 0));
        if (empty($res['success'])) {
            $this->setFlashMessage((string) ($res['error'] ?? 'Não foi possível criar o evento.'), 'error');
            $this->redirect('/admin/eventos-avaliativos/criar?ano=' . (int) ($_POST['ano_letivo'] ?? 0) . '&periodo=' . (int) ($_POST['bimestre'] ?? 0));
            return;
        }
        $this->setFlashMessage('Evento avaliativo criado.', 'success');
        $this->redirect($this->urlListaEvento(
            (int) ($_POST['ano_letivo'] ?? 0),
            (int) ($_POST['bimestre'] ?? 0),
            (int) ($_POST['regra'] ?? 0)
        ));
    }

    public function editar($id): void
    {
        if (!$this->permitido('alterar') || !$this->exigirSchema()) {
            return;
        }
        $edicao = $this->service->dadosParaEdicao((int) $id);
        if ($edicao === null) {
            $this->setFlashMessage('Evento não encontrado.', 'error');
            $this->redirect('/admin/eventos-avaliativos');
            return;
        }
        $ano = (int) ($edicao['ano_letivo'] ?? date('Y'));
        $regraId = (int) ($_GET['regra'] ?? 0);
        $catalogo = $this->service->catalogoFormulario($ano);
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/eventos-avaliativos/form', $this->dados([
            'flash_status' => ($flash['type'] ?? '') === 'error' ? 'error' : (($flash['message'] ?? '') !== '' ? 'success' : ''),
            'flash_message' => (string) ($flash['message'] ?? ''),
            'title' => 'Editar evento avaliativo - EducaTudo',
            'edicao' => $edicao,
            'professores' => $catalogo['professores'],
            'materias' => $catalogo['materias'],
            'turmas' => $catalogo['turmas'],
            'periodo' => $catalogo['periodo'],
            'ano_letivo' => $ano,
            'anos' => $this->anosDoCadastro($ano, true),
            'bimestre_selecionado' => (int) ($edicao['bimestre'] ?? 0),
            'regra_id' => $regraId,
            'periodos_extra' => (new RecuperacaoConfig())->opcoesFormulario($ano, (int) ($edicao['bimestre'] ?? 0), $regraId),
            'criterios' => TipoNotaRegraService::CRITERIOS,
            'registros' => TipoNotaRegraService::REGISTROS,
            'criterios_professores' => TipoNotaRegraService::CRITERIOS_PROFESSORES,
            'canais' => TipoNotaRegraService::CANAIS_ORIGEM,
            'modos_offline' => TipoNotaRegraService::MODOS_OFFLINE,
        ]));
    }

    public function atualizar($id): void
    {
        if (!$this->permitido('alterar') || !$this->exigirSchema()) {
            return;
        }
        $id = (int) $id;
        $evento = $this->service->model()->findById($id);
        $voltar = $evento === null
            ? '/admin/eventos-avaliativos'
            : '/admin/eventos-avaliativos/' . $id . '/editar';
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect($voltar);
            return;
        }
        if ($evento === null) {
            $this->setFlashMessage('Evento não encontrado.', 'error');
            $this->redirect('/admin/eventos-avaliativos');
            return;
        }
        $user = $this->auth->getUser() ?? [];
        $res = $this->service->atualizar($id, $_POST, (int) ($user['id'] ?? 0));
        if (empty($res['success'])) {
            $this->setFlashMessage((string) ($res['error'] ?? 'Não foi possível salvar o evento.'), 'error');
            $this->redirect($voltar);
            return;
        }
        $this->setFlashMessage('Evento atualizado.', 'success');
        $this->redirect($this->urlListaEvento(
            (int) ($evento['ano_letivo'] ?? 0),
            (int) ($_POST['bimestre'] ?? ($evento['bimestre'] ?? 0)),
            (int) ($_POST['regra'] ?? 0)
        ));
    }

    public function detalhe($id): void
    {
        if (!$this->permitido('visualizar') || !$this->exigirSchema()) {
            return;
        }
        $evento = $this->service->model()->findById((int) $id);
        if ($evento === null) {
            $this->setFlashMessage('Evento não encontrado.', 'error');
            $this->redirect('/admin/eventos-avaliativos');
            return;
        }
        $this->redirect($this->urlListaEvento(
            (int) ($evento['ano_letivo'] ?? 0),
            (int) ($evento['bimestre'] ?? 0),
            (int) ($_GET['regra'] ?? 0)
        ));
    }

    public function excluir($id): void
    {
        if (!$this->permitido('excluir') || !$this->exigirSchema()) {
            return;
        }
        $id = (int) $id;
        $evento = $this->service->model()->findById($id);
        $voltar = $evento === null
            ? '/admin/eventos-avaliativos'
            : '/admin/eventos-avaliativos?ano=' . (int) ($evento['ano_letivo'] ?? 0) . '&periodo=' . (int) ($evento['bimestre'] ?? 0);
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect($voltar);
            return;
        }
        $user = $this->auth->getUser() ?? [];
        $res = $this->service->excluir($id, (int) ($user['id'] ?? 0));
        if (empty($res['success'])) {
            $this->setFlashMessage((string) ($res['error'] ?? 'Não foi possível excluir.'), 'error');
            $this->redirect($voltar);
            return;
        }
        $this->setFlashMessage('Evento excluído.', 'success');
        $this->redirect($voltar);
    }

    public function boletim(): void
    {
        if (!$this->boletimPermitido('visualizar')) {
            return;
        }
        $ano = $this->anoNoCadastro((int) ($_REQUEST['ano'] ?? 0));
        $layout = new BoletimLayout();
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/eventos-avaliativos/boletim_lista', $this->dados([
            'title' => 'Boletim - EducaTudo',
            'current_page' => 'boletim-layout',
            'ano' => $ano,
            'anos' => $this->anosDoCadastro(),
            'itens' => $layout->listar($ano),
            'schema_pronto' => $layout->identidadePronta(),
            'pode_alterar' => $this->boletimPermitido('alterar', true),
            'pode_excluir' => $this->boletimPermitido('excluir', true),
            'flash_status' => ($flash['type'] ?? '') === 'error' ? 'error' : (($flash['message'] ?? '') !== '' ? 'success' : ''),
            'flash_message' => (string) ($flash['message'] ?? ''),
        ]));
    }

    public function novoBoletim(): void
    {
        if (!$this->boletimPermitido('alterar')) {
            return;
        }
        $this->renderBoletimForm(null);
    }

    public function criarBoletim(): void
    {
        if (!$this->boletimPermitido('alterar')) {
            return;
        }
        $ano = $this->anoNoCadastro((int) ($_REQUEST['ano'] ?? 0));
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect('/admin/eventos-avaliativos/boletim/novo?ano=' . $ano);
            return;
        }
        $res = (new BoletimLayout())->criar($_POST);
        if (!$res['ok']) {
            $this->setFlashMessage($res['erro'], 'error');
            $this->redirect('/admin/eventos-avaliativos/boletim/novo?ano=' . $ano);
            return;
        }
        $this->vincularVidaEscolarDoBoletim((int) $res['id']);
        $this->setFlashMessage('Boletim criado. Monte as linhas do layout.', 'success');
        $this->redirect('/admin/eventos-avaliativos/boletim/' . (int) $res['id']);
    }

    public function dadosBoletim($id): void
    {
        if (!$this->boletimPermitido('alterar')) {
            return;
        }
        $item = (new BoletimLayout())->find((int) $id);
        if ($item === null) {
            $this->setFlashMessage('Boletim não encontrado.', 'error');
            $this->redirect('/admin/eventos-avaliativos/boletim');
            return;
        }
        $this->renderBoletimForm($item);
    }

    public function atualizarBoletim($id): void
    {
        if (!$this->boletimPermitido('alterar')) {
            return;
        }
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect('/admin/eventos-avaliativos/boletim/' . $id . '/dados');
            return;
        }
        $res = (new BoletimLayout())->atualizarCadastro($id, $_POST);
        if (!$res['ok']) {
            $this->setFlashMessage($res['erro'], 'error');
            $this->redirect('/admin/eventos-avaliativos/boletim/' . $id . '/dados');
            return;
        }
        $this->vincularVidaEscolarDoBoletim($id);
        $this->setFlashMessage('Dados do boletim salvos.', 'success');
        $this->redirect('/admin/eventos-avaliativos/boletim/' . $id . '/dados');
    }

    public function excluirBoletim($id): void
    {
        if (!$this->boletimPermitido('excluir')) {
            return;
        }
        $id = (int) $id;
        $layout = new BoletimLayout();
        if (!$layout->identidadePronta()) {
            $this->setFlashMessage('Rode a migration 2026_10_28_boletim_layouts_varios.sql no painel Master antes de excluir.', 'error');
            $this->redirect('/admin/eventos-avaliativos/boletim?ano=' . $this->anoNoCadastro((int) ($_REQUEST['ano'] ?? 0)));
            return;
        }
        $item = $layout->find($id);
        $ano = $item === null ? $this->anoNoCadastro((int) ($_REQUEST['ano'] ?? 0)) : (int) $item['ano_letivo'];
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect('/admin/eventos-avaliativos/boletim?ano=' . $ano);
            return;
        }
        if ($item === null) {
            $this->setFlashMessage('Boletim não encontrado.', 'error');
            $this->redirect('/admin/eventos-avaliativos/boletim?ano=' . $ano);
            return;
        }
        $layout->excluir($id);
        $this->setFlashMessage('Boletim excluído.', 'success');
        $this->redirect('/admin/eventos-avaliativos/boletim?ano=' . $ano);
    }

    public function layoutBoletim($id): void
    {
        if (!$this->boletimPermitido('visualizar')) {
            return;
        }
        $layout = new BoletimLayout();
        $item = $layout->find((int) $id);
        if ($item === null) {
            $this->setFlashMessage('Boletim não encontrado.', 'error');
            $this->redirect('/admin/eventos-avaliativos/boletim');
            return;
        }
        $ano = (int) $item['ano_letivo'];
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/eventos-avaliativos/boletim', $this->dados([
            'title' => 'Layout do boletim - EducaTudo',
            'current_page' => 'boletim-layout',
            'boletim_id' => (int) $item['id'],
            'boletim_nome' => (string) $item['nome'],
            'boletim_finalidade' => (string) $item['finalidade'],
            'ano' => $ano,
            'catalogo' => $layout->catalogo($ano),
            'linhas' => $item['linhas'],
            'formula_final_media' => (string) $item['formula_final_media'],
            'variaveis_media' => $layout->variaveisMedia($ano),
            'schema_pronto' => true,
            'identidade_pronta' => $layout->identidadePronta(),
            'flash_status' => ($flash['type'] ?? '') === 'error' ? 'error' : (($flash['message'] ?? '') !== '' ? 'success' : ''),
            'flash_message' => (string) ($flash['message'] ?? ''),
        ]));
    }

    public function salvarBoletim($id): void
    {
        if (!$this->boletimPermitido('alterar')) {
            return;
        }
        $id = (int) $id;
        $layout = new BoletimLayout();
        $item = $layout->find($id);
        if ($item === null) {
            $this->setFlashMessage('Boletim não encontrado.', 'error');
            $this->redirect('/admin/eventos-avaliativos/boletim');
            return;
        }
        $ano = (int) $item['ano_letivo'];
        $voltar = '/admin/eventos-avaliativos/boletim/' . $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect($voltar);
            return;
        }
        $linhas = is_array($_POST['linhas'] ?? null) ? $_POST['linhas'] : [];
        if ($layout->filtrar($ano, $linhas) === []) {
            $this->setFlashMessage('Dê um nome à linha e marque ao menos uma coluna.', 'error');
            $this->redirect($voltar);
            return;
        }
        $formula = $layout->normalizarFormulaMedia($ano, (string) ($_POST['formula_final_media'] ?? ''));
        if (!$formula['ok']) {
            $this->setFlashMessage($formula['erro'], 'error');
            $this->redirect($voltar);
            return;
        }
        if (!$layout->salvarDoBoletim($id, $linhas, $formula['formula'])) {
            $this->setFlashMessage('Não foi possível salvar o layout.', 'error');
            $this->redirect($voltar);
            return;
        }
        $this->setFlashMessage('Layout do boletim salvo.', 'success');
        $this->redirect($voltar);
    }

    /**
     * @param array<string,mixed>|null $item
     */
    private function renderBoletimForm(?array $item): void
    {
        $layout = new BoletimLayout();
        if (!$layout->identidadePronta()) {
            $this->setFlashMessage('Rode a migration 2026_10_28_boletim_layouts_varios.sql no painel Master antes de criar outro boletim.', 'error');
            $this->redirect('/admin/eventos-avaliativos/boletim');
            return;
        }
        $ano = (int) ($item['ano_letivo'] ?? $this->anoNoCadastro(0));
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/eventos-avaliativos/boletim_form', $this->dados([
            'title' => ($item ? 'Editar boletim' : 'Novo boletim') . ' - EducaTudo',
            'current_page' => 'boletim-layout',
            'item' => $item,
            'ano' => $ano,
            'anos' => $this->anosDoCadastro($ano, $item !== null),
            'materias' => $this->materiasDoBoletim(),
            'series' => $this->seriesDoBoletim(),
            'turmas' => $this->turmasDoBoletim(),
            'regras_academicas' => $this->regrasDoBoletim((int) ($item['regra_academica_id'] ?? 0)),
            'so_regra' => (new \App\Modulos\RegrasAcademicas\Models\RegraAcademica())->boletimPronto(),
            'flash_status' => ($flash['type'] ?? '') === 'error' ? 'error' : (($flash['message'] ?? '') !== '' ? 'success' : ''),
            'flash_message' => (string) ($flash['message'] ?? ''),
        ]));
    }

    private function anoNoCadastro(int $pedido): int
    {
        $anos = PeriodoLetivo::anosCadastrados();
        if ($anos === []) {
            return ($pedido >= 2000 && $pedido <= 2100) ? $pedido : (int) date('Y');
        }
        if (in_array($pedido, $anos, true)) {
            return $pedido;
        }
        return $anos[0];
    }

    /**
     * @return list<int>
     */
    private function anosDoCadastro(int $anoAtual = 0, bool $incluirAtual = false): array
    {
        $anos = PeriodoLetivo::anosCadastrados();
        if ($incluirAtual && $anoAtual > 0 && !in_array($anoAtual, $anos, true)) {
            $anos[] = $anoAtual;
            rsort($anos);
        }
        if ($anos === []) {
            $fallback = ($anoAtual >= 2000 && $anoAtual <= 2100) ? $anoAtual : (int) date('Y');
            return [$fallback];
        }
        return $anos;
    }

    private function vincularVidaEscolarDoBoletim(int $boletimId): void
    {
        if ($boletimId <= 0) {
            return;
        }
        try {
            $item = (new BoletimLayout())->find($boletimId);
            $ano = (int) ($item['ano_letivo'] ?? 0);
            if ($item === null || $ano <= 0 || (string) ($item['finalidade'] ?? '') !== 'oficial' || (int) ($item['ativo'] ?? 0) !== 1) {
                return;
            }
            $usuario = $this->auth->getUser();
            (new \App\Modulos\VidaEscolar\Services\VidaEscolarService())->garantirFichasDoLayoutOficial(
                $ano,
                [],
                is_array($usuario) ? $usuario : []
            );
        } catch (\Throwable $e) {
            error_log('Vida escolar ao salvar boletim #' . $boletimId . ': ' . $e->getMessage());
        }
    }

    private function boletimPermitido(string $acao, bool $apenasConsulta = false): bool
    {
        if ($apenasConsulta) {
            $user = $this->auth->getUser();
            $permissions = \App\Core\AdminPermissionMatrix::effectivePermissionsForUser($this->db, $user ?? []);
            return \App\Core\AdminPermissionMatrix::can($permissions, 'configuracao_boletim', $acao);
        }
        return $this->enforceAdminPermissionKey('configuracao_boletim', $acao, false);
    }

    /**
     * @return list<array{id:int,nome:string,eh_rotulo:bool}>
     */
    private function materiasDoBoletim(): array
    {
        $saida = [];
        try {
            foreach ((new \App\Dominio\Escola\Models\ComponenteCurricular())->getOficiaisParaMatriz(true) as $componente) {
                $id = (int) ($componente['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $saida[] = [
                    'id' => $id,
                    'nome' => (string) ($componente['nome'] ?? ''),
                    'eh_rotulo' => !empty($componente['eh_rotulo']),
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }
        usort($saida, static fn (array $a, array $b): int => strcmp($a['nome'], $b['nome']));
        return $saida;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function seriesDoBoletim(): array
    {
        try {
            return \App\Core\Database::getInstance()->fetchAll(
                'SELECT s.id, s.nome, c.nome AS curso_nome
                 FROM serie s
                 LEFT JOIN curso c ON c.id = s.curso_id
                 WHERE s.ativo = 1
                 ORDER BY c.nome ASC, s.nome ASC
                 LIMIT 300'
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function turmasDoBoletim(): array
    {
        try {
            return \App\Core\Database::getInstance()->fetchAll(
                'SELECT t.id, t.nome, t.ano_letivo, s.nome AS serie_nome
                 FROM turmas t
                 LEFT JOIN serie s ON s.id = t.serie_id
                 WHERE t.ativo = 1 OR t.ativo IS NULL
                 ORDER BY t.ano_letivo DESC, t.nome ASC
                 LIMIT 800'
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    /**
     * @return list<array<string,mixed>>
     */
    private function regrasDoBoletim(int $incluirId = 0): array
    {
        try {
            $model = new \App\Modulos\RegrasAcademicas\Models\RegraAcademica();
            $lista = $model->getAll(['ativo' => 1]);
            if ($incluirId <= 0) {
                return $lista;
            }
            foreach ($lista as $regra) {
                if ((int) ($regra['id'] ?? 0) === $incluirId) {
                    return $lista;
                }
            }
            $extra = $model->findById($incluirId);
            if ($extra !== null) {
                $lista[] = $extra;
            }
            return $lista;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function listaRegras(string $flashStatus, string $flashMessage): void
    {
        $anoAtual = (int) date('Y');
        $pedido = (int) ($_GET['ano_lista'] ?? 0);
        $filtroExplicito = ($pedido >= 2000 && $pedido <= 2100);
        $anoFiltro = $filtroExplicito ? $pedido : $anoAtual;
        $anos = $this->anosDoCadastro($anoFiltro, true);
        if (!in_array($anoAtual, $anos, true)) {
            $anos[] = $anoAtual;
            rsort($anos);
        }
        $cursos = (new \App\Dominio\Escola\Models\ClassRoom())->getCursosNovo() ?: [];
        $pedidoCurso = (int) ($_GET['curso_id'] ?? 0);
        $cursoFiltro = 0;
        $cursoNome = '';
        foreach ($cursos as $curso) {
            $cursoId = (int) ($curso['id'] ?? 0);
            if ($pedidoCurso > 0 && $cursoId === $pedidoCurso) {
                $cursoFiltro = $cursoId;
                $cursoNome = (string) ($curso['nome'] ?? '');
                break;
            }
        }
        $regras = new RegraAcademica();
        $itens = [];
        if ($regras->schemaPronto()) {
            $consulta = ['ano_letivo' => $anoFiltro];
            if ($cursoFiltro > 0) {
                $consulta['curso_id'] = $cursoFiltro;
            }
            $itens = $regras->getAll($consulta);
        }
        $filtrosAtivos = 0;
        if ($filtroExplicito && $anoFiltro !== $anoAtual) {
            $filtrosAtivos++;
        }
        if ($cursoFiltro > 0) {
            $filtrosAtivos++;
        }
        $this->viewWithLayout('admin', 'admin/eventos-avaliativos/lista', $this->dados([
            'title' => 'Eventos Avaliativos - EducaTudo',
            'itens' => $itens,
            'schema_pronto' => $regras->schemaPronto(),
            'ano_padrao' => $anoFiltro,
            'ano_filtro' => $anoFiltro,
            'ano_atual' => $anoAtual,
            'filtros_ativos' => $filtrosAtivos,
            'anos' => $anos,
            'cursos' => $cursos,
            'curso_filtro' => $cursoFiltro,
            'curso_nome' => $cursoNome,
            'flash_status' => $flashStatus,
            'flash_message' => $flashMessage,
        ]));
    }

    /**
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function dados(array $extra): array
    {
        return array_merge([
            'user' => $this->auth->getUser(),
            'current_page' => 'eventos-avaliativos',
            'csrf_token' => $this->generateCsrfToken(),
        ], $extra);
    }

    private function urlListaEvento(int $ano, int $periodo, int $regra = 0): string
    {
        $url = '/admin/eventos-avaliativos?ano=' . $ano . '&periodo=' . $periodo;
        if ($regra > 0) {
            $url .= '&regra=' . $regra;
        }

        return $url;
    }

    private function exigirSchema(): bool
    {
        if ($this->service->model()->tabelasProntas()) {
            return true;
        }
        $this->setFlashMessage('Rode a migration 2026_10_26_eventos_avaliativos.sql no painel Master.', 'error');
        $this->redirect('/admin/eventos-avaliativos');
        return false;
    }

    private function permitido(string $acao, bool $apenasConsulta = false): bool
    {
        if ($apenasConsulta) {
            $user = $this->auth->getUser();
            $permissions = \App\Core\AdminPermissionMatrix::effectivePermissionsForUser($this->db, $user ?? []);
            return \App\Core\AdminPermissionMatrix::can($permissions, 'provas_online', $acao);
        }
        return $this->enforceAdminPermissionKey('provas_online', $acao, false);
    }
}
}
