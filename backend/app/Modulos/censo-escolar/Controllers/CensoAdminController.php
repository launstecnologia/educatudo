<?php
/**
 * EducaTudo - Censo Escolar (admin / secretaria)
 */

require_once __DIR__ . '/../../../Controllers/Admin/AdminBaseController.php';
require_once __DIR__ . '/../Services/CensoEdicaoService.php';
require_once __DIR__ . '/../Services/CensoNormalizador.php';
require_once __DIR__ . '/../Models/CensoEdicao.php';

use App\Modulos\CensoEscolar\Models\CensoEdicao;
use App\Modulos\CensoEscolar\Services\CensoEdicaoService;
use App\Modulos\CensoEscolar\Services\CensoNormalizador;

if (!class_exists('CensoAdminController', false)) {
class CensoAdminController extends AdminBaseController
{
    private function service(): CensoEdicaoService
    {
        return new CensoEdicaoService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'visualizar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->resolverEdicao($svc, false);
        $flash = $this->getFlashMessage();
        if (!$svc->model()->schemaPronto() && empty($flash['message'])) {
            $flash = [
                'message' => 'Execute a migration do Censo Escolar (painel Master) antes de usar o módulo.',
                'type' => 'error',
            ];
        }
        $painel = $edicao ? $svc->painel($edicao) : ['cards' => [], 'validacao' => [], 'layout' => [], 'pode_gerar' => ['ok' => false, 'motivo' => 'Crie ou selecione uma edição.']];
        $this->render('index', [
            'edicao' => $edicao,
            'painel' => $painel,
            'contexto' => $this->contexto($svc),
            'flash' => $flash,
        ]);
    }

    public function salvarEdicao(): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'cadastrar', false)) {
            return;
        }
        if (!$this->csrfOk('/admin/censo')) {
            return;
        }
        $user = $this->auth->getUser();
        $r = $this->service()->garantirEdicao($_POST, (int) ($user['id'] ?? 0));
        if (empty($r['success'])) {
            $this->setFlashMessage($r['error'] ?? 'Não foi possível criar a edição.', 'error');
            $this->redirect('/admin/censo');
            return;
        }
        $this->setFlashMessage('Edição pronta. Os cadastros existentes foram sincronizados e validados.', 'success');
        $this->redirect('/admin/censo?edicao_id=' . (int) $r['id']);
    }

    public function config($id = 0): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'visualizar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        $this->render('config', [
            'edicao' => $edicao,
            'contexto' => $this->contexto($svc),
            'layout' => $svc->layouts()->carregar((int) $edicao['ano'], (string) $edicao['etapa_coleta']),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function salvarConfig($id = 0): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'alterar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        if (!$this->csrfOk('/admin/censo/' . (int) $edicao['id'] . '/config')) {
            return;
        }
        $r = $svc->atualizarConfig($edicao, $_POST, (int) ($this->auth->getUser()['id'] ?? 0));
        $this->setFlashMessage($r['success'] ? 'Configuração salva.' : ($r['error'] ?? 'Erro'), $r['success'] ? 'success' : 'error');
        $this->redirect('/admin/censo/' . (int) $edicao['id'] . '/config');
    }

    public function sincronizar($id = 0): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'alterar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        if (!$this->csrfOk($this->voltar($edicao))) {
            return;
        }
        $r = $svc->sincronizar($edicao, (int) ($this->auth->getUser()['id'] ?? 0));
        $this->setFlashMessage(
            !empty($r['success']) ? 'Cadastros sincronizados e validados sem duplicar pessoas.' : ($r['error'] ?? 'Falha na sincronização'),
            !empty($r['success']) ? 'success' : 'error'
        );
        $this->redirect($this->voltar($edicao));
    }

    public function validar($id = 0): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'alterar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        if (!$this->csrfOk($this->voltar($edicao))) {
            return;
        }
        $r = $svc->validar($edicao, (int) ($this->auth->getUser()['id'] ?? 0));
        $resumo = $r['resumo'] ?? [];
        $msg = !empty($r['success'])
            ? sprintf('Validação concluída: %d erro(s), %d alerta(s), %d divergência(s).', $resumo['erros'] ?? 0, $resumo['alertas'] ?? 0, $resumo['divergencias'] ?? 0)
            : ($r['error'] ?? 'Falha na validação');
        $this->setFlashMessage($msg, !empty($r['success']) && empty($resumo['erros']) ? 'success' : 'error');
        $this->redirect('/admin/censo/' . (int) $edicao['id'] . '/pendencias');
    }

    public function listagem($id, $entidade): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'visualizar', false)) {
            return;
        }
        $entidade = $this->entidadeLista((string) $entidade);
        if ($entidade === '') {
            $this->redirect('/admin/censo');
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        $filtros = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? 'todos')),
        ];
        $linhas = $svc->model()->listarEntidade($entidade, (int) $edicao['id'], $filtros);
        foreach ($linhas as &$linha) {
            $linha['cpf_mascarado'] = $svc->mascararCpf($linha['cpf'] ?? null);
            if (in_array($entidade, ['profissionais', 'gestores'], true)) {
                $linha['nome'] = CensoNormalizador::nomeExibicao((string) ($linha['nome'] ?? ''));
            }
            if ($entidade === 'profissionais') {
                $dadosLinha = json_decode((string) ($linha['dados_json'] ?? ''), true);
                $dadosLinha = is_array($dadosLinha) ? $dadosLinha : [];
                $sit = CensoNormalizador::situacaoFuncional((string) ($dadosLinha['situacao_funcional'] ?? ''));
                $rotulosSit = [
                    '1' => 'Efetivo',
                    '2' => 'Temporário',
                    '3' => 'Terceirizado',
                    '4' => 'CLT',
                ];
                $turma = trim((string) ($linha['turma_nome'] ?? ''));
                $linha['turma_nome'] = trim(($rotulosSit[$sit] ?? 'CLT') . ($turma !== '' ? ' · ' . $turma : ''));
            }
        }
        unset($linha);
        $this->render('listagem', [
            'edicao' => $edicao,
            'contexto' => $this->contexto($svc),
            'entidade' => $entidade,
            'linhas' => $linhas,
            'filtros' => $filtros,
            'flash' => $this->getFlashMessage(),
            'editavel' => $svc->edicaoEditavel($edicao),
        ]);
    }

    public function formulario($id, $entidade, $rid): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'visualizar', false)) {
            return;
        }
        $entidade = $this->entidadeForm((string) $entidade);
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao || $entidade === '') {
            return;
        }
        $registro = $svc->model()->findComplemento($entidade, (int) $edicao['id'], (int) $rid);
        if (!$registro) {
            $this->setFlashMessage('Registro não encontrado nesta edição.', 'error');
            $this->redirect('/admin/censo/' . (int) $edicao['id'] . '/' . $this->listaDaForm($entidade));
            return;
        }
        $id = (int) $rid;
        $origem = $this->carregarOrigem($svc, $entidade, $registro);
        $dados = $this->decodificarJson($registro['dados_json'] ?? null);
        if ($entidade === 'profissional') {
            if (!empty($origem['nome'])) {
                $origem['nome'] = CensoNormalizador::nomeExibicao((string) $origem['nome']);
            }
            if (!empty($dados['escolaridade'])) {
                $dados['escolaridade'] = CensoNormalizador::escolaridadeInep((string) $dados['escolaridade'])
                    ?: $dados['escolaridade'];
            }
            if (isset($dados['situacao_funcional'])) {
                $dados['situacao_funcional'] = CensoNormalizador::situacaoFuncional((string) $dados['situacao_funcional']);
            }
        }
        $secoes = require dirname(__DIR__) . '/Config/formularios.php';
        $layout = $svc->layouts()->carregar((int) $edicao['ano'], (string) $edicao['etapa_coleta']);
        $vinculosProfissional = [];
        if ($entidade === 'profissional') {
            $vinculosProfissional = $svc->model()->vinculosDoProfessor(
                (int) $edicao['id'],
                (int) ($registro['professor_id'] ?? 0)
            );
        }
        $this->render('formulario', [
            'edicao' => $edicao,
            'contexto' => $this->contexto($svc),
            'entidade' => $entidade,
            'registro' => $registro,
            'origem' => $origem,
            'dados' => $dados,
            'secoes' => $secoes[$entidade] ?? ['conferencia' => 'Conferência'],
            'secao' => trim((string) ($_GET['secao'] ?? array_key_first($secoes[$entidade] ?? ['conferencia' => '']))),
            'editavel' => $svc->edicaoEditavel($edicao),
            'etapas_censo' => $layout['dominios']['etapa'] ?? [],
            'vinculos_profissional' => $vinculosProfissional,
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function salvarFormulario($id, $entidade, $rid): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'alterar', false)) {
            return;
        }
        $entidade = $this->entidadeForm((string) $entidade);
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao || $entidade === '') {
            return;
        }
        $url = '/admin/censo/' . (int) $edicao['id'] . '/' . $entidade . '/' . (int) $rid;
        if (!$this->csrfOk($url)) {
            return;
        }
        $r = $svc->salvarComplemento($edicao, $entidade, (int) $rid, $_POST, (int) ($this->auth->getUser()['id'] ?? 0));
        $this->setFlashMessage($r['success'] ? 'Dados salvos.' : ($r['error'] ?? 'Erro ao salvar'), $r['success'] ? 'success' : 'error');
        $secao = trim((string) ($_POST['proxima_secao'] ?? $_POST['secao'] ?? ''));
        $this->redirect($url . ($secao !== '' ? '?secao=' . rawurlencode($secao) : ''));
    }

    public function pendencias($id = 0): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'visualizar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        $this->render('pendencias', [
            'edicao' => $edicao,
            'contexto' => $this->contexto($svc),
            'pendencias' => $svc->model()->pendencias((int) $edicao['id'], [
                'severidade' => trim((string) ($_GET['severidade'] ?? '')),
                'status' => trim((string) ($_GET['status'] ?? '')),
            ]),
            'resumo' => $svc->model()->resumoValidacao((int) $edicao['id']),
            'flash' => $this->getFlashMessage(),
            'editavel' => $svc->edicaoEditavel($edicao),
        ]);
    }

    public function conferirPendencia($id, $pid): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'alterar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        if (!$this->csrfOk('/admin/censo/' . (int) $edicao['id'] . '/pendencias')) {
            return;
        }
        $just = trim((string) ($_POST['justificativa'] ?? ''));
        $r = $svc->marcarConferido($edicao, (int) $pid, (int) ($this->auth->getUser()['id'] ?? 0), $just);
        $this->setFlashMessage(
            !empty($r['success']) ? ($just !== '' ? 'Pendência justificada.' : 'Pendência marcada como conferida.') : ($r['error'] ?? 'Não foi possível conferir.'),
            !empty($r['success']) ? 'success' : 'error'
        );
        $this->redirect('/admin/censo/' . (int) $edicao['id'] . '/pendencias');
    }

    public function situacao($id = 0): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'visualizar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        $svc->garantirSituacoes($edicao);
        $this->render('situacao', [
            'edicao' => $edicao,
            'contexto' => $this->contexto($svc),
            'linhas' => $svc->model()->situacoes((int) $edicao['id']),
            'flash' => $this->getFlashMessage(),
            'editavel' => $svc->edicaoEditavel($edicao),
        ]);
    }

    public function salvarSituacao($id, $sid): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'alterar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        if (!$this->csrfOk('/admin/censo/' . (int) $edicao['id'] . '/situacao')) {
            return;
        }
        $r = $svc->confirmarSituacao(
            $edicao,
            (int) $sid,
            trim((string) ($_POST['situacao_codigo'] ?? '')),
            trim((string) ($_POST['justificativa'] ?? '')),
            (int) ($this->auth->getUser()['id'] ?? 0)
        );
        $this->setFlashMessage($r['success'] ? 'Situação confirmada.' : ($r['error'] ?? 'Erro'), $r['success'] ? 'success' : 'error');
        $this->redirect('/admin/censo/' . (int) $edicao['id'] . '/situacao');
    }

    public function previa($id = 0): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'visualizar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        $this->render('previa', [
            'edicao' => $edicao,
            'contexto' => $this->contexto($svc),
            'previa' => $svc->exportacao()->previa($edicao),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function exportacoes($id = 0): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'visualizar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        $this->render('exportacoes', [
            'edicao' => $edicao,
            'contexto' => $this->contexto($svc),
            'exportacoes' => $svc->model()->exportacoes((int) $edicao['id']),
            'pode_gerar' => (new \App\Modulos\CensoEscolar\Services\CensoValidacaoService($svc->model()))->podeGerarTxt($edicao),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function gerarTxt($id = 0): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'alterar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        if (!$this->csrfOk('/admin/censo/' . (int) $edicao['id'] . '/exportacoes')) {
            return;
        }
        $r = $svc->exportacao()->gerarTxt($edicao, (int) ($this->auth->getUser()['id'] ?? 0));
        $this->setFlashMessage($r['success'] ? 'Arquivo gerado. A versão anterior foi preservada.' : ($r['error'] ?? 'Falha'), $r['success'] ? 'success' : 'error');
        $this->redirect('/admin/censo/' . (int) $edicao['id'] . '/exportacoes');
    }

    public function download($id, $eid): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'visualizar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        $exp = $svc->model()->findExportacao((int) $eid, (int) $edicao['id']);
        if (!$exp) {
            $this->setFlashMessage('Exportação não encontrada.', 'error');
            $this->redirect('/admin/censo/' . (int) $edicao['id'] . '/exportacoes');
            return;
        }
        $abs = $svc->exportacao()->caminhoAbsoluto((string) $exp['arquivo']);
        if ($abs === null || !is_file($abs)) {
            $this->setFlashMessage('Arquivo ausente no armazenamento.', 'error');
            $this->redirect('/admin/censo/' . (int) $edicao['id'] . '/exportacoes');
            return;
        }
        $svc->model()->registrarAuditoria([
            'edicao_id' => (int) $edicao['id'],
            'usuario_id' => (int) ($this->auth->getUser()['id'] ?? 0),
            'acao' => 'download',
            'entidade_tipo' => 'exportacao',
            'entidade_id' => (int) $eid,
            'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
        header('Content-Type: text/plain; charset=ISO-8859-1');
        header('Content-Disposition: attachment; filename="' . basename((string) $exp['nome_original']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($abs);
        exit;
    }

    public function retornos($id = 0): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'visualizar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        $this->render('retornos', [
            'edicao' => $edicao,
            'contexto' => $this->contexto($svc),
            'retornos' => $svc->model()->retornos((int) $edicao['id']),
            'exportacoes' => $svc->model()->exportacoes((int) $edicao['id']),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function importarRetorno($id = 0): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'cadastrar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        if (!$this->csrfOk('/admin/censo/' . (int) $edicao['id'] . '/retornos')) {
            return;
        }
        $r = $svc->exportacao()->importarRetorno(
            $edicao,
            $_FILES['arquivo'] ?? [],
            (int) ($this->auth->getUser()['id'] ?? 0),
            (int) ($_POST['exportacao_id'] ?? 0)
        );
        $this->setFlashMessage($r['success'] ? 'Retorno importado. Nenhum cadastro foi alterado automaticamente.' : ($r['error'] ?? 'Falha'), $r['success'] ? 'success' : 'error');
        $this->redirect('/admin/censo/' . (int) $edicao['id'] . '/retornos');
    }

    public function fechar($id = 0): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'alterar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        if (!$this->csrfOk($this->voltar($edicao))) {
            return;
        }
        $r = $svc->fechar($edicao, (int) ($this->auth->getUser()['id'] ?? 0));
        $this->setFlashMessage($r['success'] ? 'Edição fechada. Os dados declarados ficam preservados.' : ($r['error'] ?? 'Erro'), $r['success'] ? 'success' : 'error');
        $this->redirect($this->voltar($edicao));
    }

    public function reabrir($id = 0): void
    {
        if (!$this->enforceAdminPermissionKey('censo_escolar', 'alterar', false)) {
            return;
        }
        $svc = $this->service();
        $edicao = $this->edicaoOuRedirect($svc, (int) $id);
        if (!$edicao) {
            return;
        }
        if (!$this->csrfOk($this->voltar($edicao))) {
            return;
        }
        $r = $svc->reabrir($edicao, (int) ($this->auth->getUser()['id'] ?? 0), (string) ($_POST['motivo_reabertura'] ?? ''));
        $this->setFlashMessage($r['success'] ? 'Edição reaberta. O histórico anterior foi mantido.' : ($r['error'] ?? 'Erro'), $r['success'] ? 'success' : 'error');
        $this->redirect($this->voltar($edicao));
    }

    private function render(string $view, array $data): void
    {
        $flash = $data['flash'] ?? $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/censo-escolar/' . $view, array_merge($data, [
            'title' => 'Censo Escolar - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'censo_escolar',
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => ($flash['type'] ?? '') === 'success' ? 'success' : (($flash['message'] ?? '') ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]));
    }

    private function contexto(CensoEdicaoService $svc): array
    {
        $unidades = $svc->model()->unidadesAtivas();
        $anos = $svc->anosDoSeletor();
        return [
            'unidades' => $unidades,
            'anos' => $anos,
            'etapas' => CensoEdicao::ETAPAS,
            'status_labels' => CensoEdicao::STATUS,
        ];
    }

    private function resolverEdicao(CensoEdicaoService $svc, bool $criar): ?array
    {
        if (!$svc->model()->schemaPronto()) {
            return null;
        }
        $id = (int) ($_GET['edicao_id'] ?? 0);
        if ($id > 0) {
            return $svc->model()->findById($id);
        }
        $lista = $svc->model()->listar(1);
        if ($lista !== []) {
            return $svc->model()->findById((int) $lista[0]['id']);
        }
        if ($criar) {
            $r = $svc->garantirEdicao([
                'ano' => (int) date('Y'),
                'unidade_id' => 0,
                'etapa_coleta' => 'matricula_inicial',
            ], (int) ($this->auth->getUser()['id'] ?? 0));
            return !empty($r['id']) ? $svc->model()->findById((int) $r['id']) : null;
        }
        return null;
    }

    private function edicaoOuRedirect(CensoEdicaoService $svc, int $id = 0): ?array
    {
        if ($id <= 0) {
            $id = (int) ($_GET['edicao_id'] ?? 0);
        }
        if ($id <= 0) {
            $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if (preg_match('#/admin/censo/(\d+)#', $uri, $m)) {
                $id = (int) $m[1];
            }
        }
        $edicao = $id > 0 ? $svc->model()->findById($id) : $this->resolverEdicao($svc, false);
        if (!$edicao) {
            $this->setFlashMessage('Selecione ou crie uma edição do Censo.', 'error');
            $this->redirect('/admin/censo');
            return null;
        }
        $layout = $svc->layouts()->carregar((int) $edicao['ano'], (string) $edicao['etapa_coleta']);
        $edicao['versao_layout'] = (string) ($layout['versao'] ?? $edicao['versao_layout'] ?? '');
        $edicao['layout_oficial'] = !empty($layout['oficial']);
        return $edicao;
    }

    private function csrfOk(string $fallback): bool
    {
        if ($this->verifyCsrfToken($_POST['_token'] ?? '')) {
            return true;
        }
        $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
        $this->redirect($fallback);
        return false;
    }

    private function voltar(array $edicao): string
    {
        return '/admin/censo?edicao_id=' . (int) $edicao['id'];
    }

    private function entidadeLista(string $entidade): string
    {
        $ok = ['escola', 'gestores', 'turmas', 'alunos', 'profissionais', 'matriculas', 'vinculos'];
        return in_array($entidade, $ok, true) ? $entidade : '';
    }

    private function entidadeForm(string $entidade): string
    {
        $ok = ['escola', 'gestor', 'turma', 'aluno', 'profissional', 'matricula'];
        return in_array($entidade, $ok, true) ? $entidade : '';
    }

    private function listaDaForm(string $entidade): string
    {
        return [
            'escola' => 'escola',
            'gestor' => 'gestores',
            'turma' => 'turmas',
            'aluno' => 'alunos',
            'profissional' => 'profissionais',
            'matricula' => 'matriculas',
        ][$entidade] ?? 'alunos';
    }

    private function carregarOrigem(CensoEdicaoService $svc, string $entidade, array $registro): array
    {
        if ($entidade === 'aluno' && !empty($registro['aluno_id'])) {
            return $svc->model()->alunoPorId((int) $registro['aluno_id']) ?? [];
        }
        if ($entidade === 'profissional' && !empty($registro['professor_id'])) {
            return $svc->model()->professorPorId((int) $registro['professor_id']) ?? [];
        }
        if ($entidade === 'turma' && !empty($registro['turma_id'])) {
            return $svc->model()->turmaPorId((int) $registro['turma_id']) ?? [];
        }
        if ($entidade === 'escola') {
            return $svc->model()->unidadePorId((int) ($registro['unidade_id'] ?? 0)) ?? [];
        }
        return [];
    }

    private function decodificarJson($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }
}
}
