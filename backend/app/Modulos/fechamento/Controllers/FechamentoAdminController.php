<?php
/**
 * EducaTudo - Painel de Fechamento, homologações e documentos do período.
 */

require_once __DIR__ . '/../../../Controllers/Admin/AdminBaseController.php';
require_once __DIR__ . '/../Services/FechamentoService.php';
require_once __DIR__ . '/../Services/ImpressaoLoteFechamentoService.php';
require_once __DIR__ . '/../Services/FechamentoMaquinaEstados.php';
require_once __DIR__ . '/../../../Models/Education/ResultadoAcademico.php';
require_once __DIR__ . '/../../../Core/AdminMenuDiagnostico.php';

if (!class_exists('FechamentoAdminController')) {
class FechamentoAdminController extends AdminBaseController
{
    private function service(): FechamentoService
    {
        return new FechamentoService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $svc = $this->service();
        $anos = $svc->homologacao()->model()->anosLetivosTurmas();
        $anoLetivo = $this->anoDaRequest($anos);
        [$periodoTipo, $periodoNumero] = $this->periodoDaRequest();
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $paineis = $svc->painel($anoLetivo, $periodoTipo, $periodoNumero, $turmaId);
        $flash = $this->getFlashMessage();
        $lote = new ImpressaoLoteFechamentoService();

        $this->viewWithLayout('admin', 'admin/fechamento/index', [
            'title' => 'Fechamento - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'fechamento',
            'anos' => $anos,
            'ano_letivo' => $anoLetivo,
            'periodo_tipo' => $periodoTipo,
            'periodo_numero' => $periodoNumero,
            'turma_id' => $turmaId,
            'turmas' => $svc->homologacao()->model()->turmasAtivas($anoLetivo),
            'paineis' => $paineis,
            'impressao_lote' => $lote->resumo($paineis),
            'schema_pronto' => $svc->model()->schemaPronto(),
            'simular_regra_id' => $this->simularRegraId(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function turma($turmaId): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $turmaId = (int) $turmaId;
        $svc = $this->service();
        $model = $svc->homologacao()->model();
        $anos = $model->anosLetivosTurmas();
        $anoTurma = $model->anoDaTurma($turmaId);
        $anoLetivo = isset($_GET['ano_letivo'])
            ? $this->anoDaRequest($anos)
            : ($anoTurma > 0 ? $anoTurma : $this->anoDaRequest($anos));
        [$periodoTipo, $periodoNumero] = $this->periodoDaRequest();
        $alinhado = $model->alinharTurmaAoAnoLetivo($turmaId, $anoLetivo);
        if ((int) $alinhado['turma_id'] !== $turmaId || (int) $alinhado['ano_letivo'] !== $anoLetivo) {
            $this->redirect($this->urlTurma(
                (int) $alinhado['turma_id'],
                (int) $alinhado['ano_letivo'],
                $periodoTipo,
                $periodoNumero
            ));
            return;
        }
        $preview = $svc->homologacao()->previewTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        $fechamento = $svc->model()->findVigente($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        $historico = $fechamento ? $svc->model()->listarHistorico((int) $fechamento['id']) : [];
        $flash = $this->getFlashMessage();

        $this->viewWithLayout('admin', 'admin/fechamento/turma', [
            'title' => 'Fechamento da turma - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'fechamento',
            'anos' => $anos,
            'ano_letivo' => $anoLetivo,
            'periodo_tipo' => $periodoTipo,
            'periodo_numero' => $periodoNumero,
            'preview' => $preview,
            'fechamento' => $fechamento,
            'historico' => $historico,
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function iniciar($turmaId): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'alterar', false)) {
            return;
        }
        $turmaId = (int) $turmaId;
        $voltar = $this->urlTurma($turmaId);
        if (!$this->csrfOuRedirect($voltar)) {
            return;
        }
        $anoLetivo = (int) ($_POST['ano_letivo'] ?? date('Y'));
        [$periodoTipo, $periodoNumero] = $this->periodoDoPost();
        $result = $this->service()->transitar(
            $turmaId,
            $anoLetivo,
            $periodoTipo,
            $periodoNumero,
            FechamentoMaquinaEstados::EM_FECHAMENTO,
            (int) ($this->auth->getUser()['id'] ?? 0)
        );
        $this->setFlashMessage(
            $result['success'] ? 'Fechamento iniciado.' : ($result['error'] ?? 'Falha'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect($voltar);
    }

    public function reabrir($turmaId): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'alterar', false)) {
            return;
        }
        $turmaId = (int) $turmaId;
        $voltar = $this->urlTurma($turmaId);
        if (!$this->csrfOuRedirect($voltar)) {
            return;
        }
        $anoLetivo = (int) ($_POST['ano_letivo'] ?? date('Y'));
        [$periodoTipo, $periodoNumero] = $this->periodoDoPost();
        $result = $this->service()->transitar(
            $turmaId,
            $anoLetivo,
            $periodoTipo,
            $periodoNumero,
            FechamentoMaquinaEstados::ABERTO,
            (int) ($this->auth->getUser()['id'] ?? 0),
            'Reabertura: conferência com pendências, sem iniciar o fechamento.'
        );
        $this->setFlashMessage(
            $result['success'] ? 'Período voltou para aberto. Conferir turma não inicia o fechamento.' : ($result['error'] ?? 'Falha'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect($voltar);
    }

    public function homologar($turmaId): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'alterar', false)) {
            return;
        }
        $turmaId = (int) $turmaId;
        $voltar = $this->urlTurma($turmaId);
        if (!$this->csrfOuRedirect($voltar)) {
            return;
        }
        $anoLetivo = (int) ($_POST['ano_letivo'] ?? date('Y'));
        [$periodoTipo, $periodoNumero] = $this->periodoDoPost();
        $ids = [];
        foreach ((array) ($_POST['aluno_ids'] ?? []) as $id) {
            $ids[] = (int) $id;
        }
        $todos = !empty($_POST['homologar_todos']);
        $result = $this->service()->homologacao()->homologarTurma(
            $turmaId,
            $anoLetivo,
            $periodoTipo,
            $periodoNumero,
            (int) ($this->auth->getUser()['id'] ?? 0),
            $todos ? [] : $ids,
            $todos
        );
        if (empty($result['success'])) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível homologar.', 'error');
        } else {
            $this->setFlashMessage(
                'Homologados: ' . (int) ($result['homologados'] ?? 0)
                . ' · ignorados: ' . (int) ($result['ignorados'] ?? 0) . '.',
                'success'
            );
        }
        $this->redirect($voltar);
    }

    public function retificar($turmaId): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'excluir', false)) {
            return;
        }
        $turmaId = (int) $turmaId;
        $voltar = $this->urlTurma($turmaId);
        if (!$this->csrfOuRedirect($voltar)) {
            return;
        }
        $anoLetivo = (int) ($_POST['ano_letivo'] ?? date('Y'));
        [$periodoTipo, $periodoNumero] = $this->periodoDoPost();
        $result = $this->service()->retificar(
            $turmaId,
            $anoLetivo,
            $periodoTipo,
            $periodoNumero,
            (int) ($this->auth->getUser()['id'] ?? 0),
            (string) ($_POST['justificativa'] ?? '')
        );
        $this->setFlashMessage(
            $result['success']
                ? 'Retificação registrada. O snapshot homologado foi preservado; o período vigente está aberto para correção oficial.'
                : ($result['error'] ?? 'Falha'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect($voltar);
    }

    public function homologacoes(): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $svc = $this->service();
        $anos = $svc->homologacao()->model()->anosLetivosTurmas();
        $anoLetivo = $this->anoDaRequest($anos);
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $registros = $svc->model()->listarLivro($anoLetivo, $turmaId);
        $historicos = [];
        foreach ($registros as $reg) {
            $fid = (int) ($reg['id'] ?? 0);
            if ($fid > 0) {
                $historicos[$fid] = $svc->model()->listarHistorico($fid);
            }
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/fechamento/homologacoes', [
            'title' => 'Homologações - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'homologacoes',
            'anos' => $anos,
            'ano_letivo' => $anoLetivo,
            'turma_id' => $turmaId,
            'turmas' => $svc->homologacao()->model()->turmasAtivas($anoLetivo),
            'registros' => $registros,
            'historicos' => $historicos,
            'schema_pronto' => $svc->model()->schemaPronto(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function impressaoLote(): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $svc = $this->service();
        $anos = $svc->homologacao()->model()->anosLetivosTurmas();
        $anoLetivo = $this->anoDaRequest($anos);
        [$periodoTipo, $periodoNumero] = $this->periodoDaRequest();
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $serie = trim((string) ($_GET['serie'] ?? ''));
        $paineis = $svc->painel($anoLetivo, $periodoTipo, $periodoNumero, $turmaId, $serie);
        $lote = new ImpressaoLoteFechamentoService();
        $resumo = $lote->resumo($paineis);
        $voltar = $this->urlImpressaoLote($anoLetivo, $periodoTipo, $periodoNumero, $turmaId, $serie);
        if ((string) ($_GET['documento'] ?? '') !== '') {
            $this->setFlashMessage('A geração agora roda em segundo plano. Escolha a turma ou a série e clique em Gerar.', 'success');
            $this->redirect($voltar);
            return;
        }

        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/fechamento/impressao-lote', [
            'title' => 'Impressão em lote - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'fechamento',
            'ano_letivo' => $anoLetivo,
            'periodo_tipo' => $periodoTipo,
            'periodo_numero' => $periodoNumero,
            'turma_id' => $turmaId,
            'serie' => $serie,
            'series' => $this->seriesDasTurmas($svc->homologacao()->model()->turmasAtivas($anoLetivo)),
            'turmas' => $svc->homologacao()->model()->turmasAtivas($anoLetivo),
            'escopo_ok' => $lote->escopoInformado($turmaId, $serie),
            'jobs' => $this->jobsDaQuery(),
            'impressao_lote' => $resumo,
            'documentos' => ImpressaoLoteFechamentoService::DOCUMENTOS,
            'pacote' => ImpressaoLoteFechamentoService::PACOTE,
            'pode_preparar_historico' => $this->podeAlterarResultados(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function prepararHistoricos(): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'alterar', false)) {
            return;
        }
        $svc = $this->service();
        $anos = $svc->homologacao()->model()->anosLetivosTurmas();
        $anoLetivo = (int) ($_POST['ano_letivo'] ?? 0);
        if (!in_array($anoLetivo, $anos, true)) {
            $anoLetivo = (int) ($anos[0] ?? date('Y'));
        }
        [$periodoTipo, $periodoNumero] = $this->periodoDoPost();
        $turmaId = (int) ($_POST['turma_id'] ?? 0);
        $serie = trim((string) ($_POST['serie'] ?? ''));
        $voltar = $this->urlImpressaoLote($anoLetivo, $periodoTipo, $periodoNumero, $turmaId, $serie);
        if (!$this->csrfOuRedirect($voltar)) {
            return;
        }
        $paineis = $svc->painel($anoLetivo, $periodoTipo, $periodoNumero, $turmaId, $serie);
        $lote = new ImpressaoLoteFechamentoService();
        if (!$lote->escopoInformado($turmaId, $serie)) {
            $this->setFlashMessage('Escolha uma turma ou uma série antes de preparar os históricos.', 'error');
            $this->redirect($voltar);
            return;
        }
        if (!$lote->resumo($paineis)['pode']) {
            $this->setFlashMessage('Prepare os históricos só depois de homologar todas as turmas deste filtro.', 'error');
            $this->redirect($voltar);
            return;
        }
        $resultado = $lote->prepararHistoricos(
            $paineis,
            $anoLetivo,
            $periodoTipo,
            $periodoNumero,
            (int) ($this->auth->getUser()['id'] ?? 0)
        );
        $msg = 'Históricos preparados: ' . (int) $resultado['criados'] . ' rascunho(s) novo(s), '
            . (int) $resultado['existentes'] . ' já existente(s).';
        if ($resultado['falhas'] !== []) {
            $msg .= ' Falhas: ' . implode(' ', $resultado['falhas']);
        }
        $this->setFlashMessage($msg, $resultado['falhas'] === [] ? 'success' : 'error');
        $this->redirect($voltar);
    }

    public function enfileirarImpressao(): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $svc = $this->service();
        $anos = $svc->homologacao()->model()->anosLetivosTurmas();
        $anoLetivo = (int) ($_POST['ano_letivo'] ?? 0);
        if (!in_array($anoLetivo, $anos, true)) {
            $anoLetivo = (int) ($anos[0] ?? date('Y'));
        }
        [$periodoTipo, $periodoNumero] = $this->periodoDoPost();
        $turmaId = (int) ($_POST['turma_id'] ?? 0);
        $serie = trim((string) ($_POST['serie'] ?? ''));
        $voltar = $this->urlImpressaoLote($anoLetivo, $periodoTipo, $periodoNumero, $turmaId, $serie);
        if (!$this->csrfOuRedirect($voltar)) {
            return;
        }
        $lote = new ImpressaoLoteFechamentoService();
        if (!$lote->escopoInformado($turmaId, $serie)) {
            $this->setFlashMessage('Escolha uma turma ou uma série. A escola inteira de uma vez deixa a geração pesada.', 'error');
            $this->redirect($voltar);
            return;
        }
        $pedido = (string) ($_POST['documento'] ?? '');
        $documentos = $pedido === 'pacote' ? ImpressaoLoteFechamentoService::PACOTE : [$pedido];
        foreach ($documentos as $documento) {
            if (!isset(ImpressaoLoteFechamentoService::DOCUMENTOS[$documento])) {
                $this->setFlashMessage('Documento de impressão desconhecido.', 'error');
                $this->redirect($voltar);
                return;
            }
        }
        $paineis = $svc->painel($anoLetivo, $periodoTipo, $periodoNumero, $turmaId, $serie);
        if (!$lote->resumo($paineis)['pode']) {
            $this->setFlashMessage('A impressão em lote só fica disponível quando todas as turmas deste filtro estão homologadas.', 'error');
            $this->redirect($voltar);
            return;
        }
        try {
            foreach ($documentos as $documento) {
                $lote->exigirEscopoLeve($paineis, $documento);
            }
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect($voltar);
            return;
        }

        $tenantSlug = defined('TENANT_SLUG') ? preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_SLUG) : '';
        $userId = (int) ($this->auth->getUser()['id'] ?? 0);
        require_once dirname(__DIR__, 3) . '/Services/AIJobService.php';
        $jobs = [];
        try {
            foreach ($documentos as $documento) {
                $jobs[] = \App\Services\AIJobService::enqueue(
                    ImpressaoLoteFechamentoService::TIPO_JOB,
                    [
                        'documento' => $documento,
                        'ano_letivo' => $anoLetivo,
                        'periodo_tipo' => $periodoTipo,
                        'periodo_numero' => $periodoNumero,
                        'turma_id' => $turmaId,
                        'serie' => $serie,
                        'user_id' => $userId,
                        'tenant_slug' => is_string($tenantSlug) ? $tenantSlug : '',
                    ],
                    $userId > 0 ? $userId : null,
                    'admin',
                    false
                );
            }
            \App\Services\AIJobService::tentarDispararWorker();
        } catch (Throwable $e) {
            $this->setFlashMessage('Não foi possível iniciar a geração. Tente de novo em instantes.', 'error');
            $this->redirect($voltar);
            return;
        }

        $this->setFlashMessage('Geração iniciada em segundo plano. Esta página avisa quando cada documento puder ser aberto.', 'success');
        $this->redirect($voltar . (str_contains($voltar, '?') ? '&' : '?') . 'jobs=' . implode(',', $jobs));
    }

    public function arquivoImpressao($id): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $jobId = (int) $id;
        require_once dirname(__DIR__, 3) . '/Services/AIJobService.php';
        $job = \App\Services\AIJobService::getJob($jobId);
        $voltar = '/admin/fechamento/impressao-lote';
        if (!$job || (string) ($job['job_type'] ?? '') !== ImpressaoLoteFechamentoService::TIPO_JOB) {
            $this->setFlashMessage('Impressão não encontrada.', 'error');
            $this->redirect($voltar);
            return;
        }
        $userId = (int) ($this->auth->getUser()['id'] ?? 0);
        if ($userId > 0 && (int) ($job['user_id'] ?? 0) !== $userId) {
            $this->setFlashMessage('Impressão não encontrada.', 'error');
            $this->redirect($voltar);
            return;
        }
        if ((string) ($job['status'] ?? '') !== 'done') {
            $this->setFlashMessage('A impressão ainda está sendo gerada.', 'success');
            $this->redirect($voltar . '?jobs=' . $jobId);
            return;
        }
        $resultado = json_decode((string) ($job['result'] ?? ''), true);
        $slug = is_array($resultado) ? (string) ($resultado['tenant_slug'] ?? '') : '';
        $path = ImpressaoLoteFechamentoService::caminhoArquivo($jobId, $slug);
        if ($path === null || !is_file($path)) {
            $this->setFlashMessage('O arquivo da impressão não está mais disponível.', 'error');
            $this->redirect($voltar);
            return;
        }
        $nome = is_array($resultado) ? (string) ($resultado['documento'] ?? 'impressao') : 'impressao';
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: inline; filename="' . $nome . '_' . $jobId . '.html"');
        readfile($path);
        exit;
    }

    public function documentos(): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $svc = $this->service();
        $anos = $svc->homologacao()->model()->anosLetivosTurmas();
        $anoLetivo = $this->anoDaRequest($anos);
        [$periodoTipo, $periodoNumero] = $this->periodoDaRequest();
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $tipo = (string) ($_GET['tipo'] ?? '');
        $emissoes = $svc->homologacao()->model()->listarEmissoes($anoLetivo, $turmaId, $tipo);
        $paineis = $turmaId > 0
            ? [$svc->homologacao()->previewTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero)]
            : [];
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/fechamento/documentos', [
            'title' => 'Documentos do período - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'documentos-periodo',
            'anos' => $anos,
            'ano_letivo' => $anoLetivo,
            'periodo_tipo' => $periodoTipo,
            'periodo_numero' => $periodoNumero,
            'turma_id' => $turmaId,
            'tipo' => $tipo,
            'turmas' => $svc->homologacao()->model()->turmasAtivas($anoLetivo),
            'emissoes' => $emissoes,
            'paineis' => $paineis,
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function dadosHomologacoes($turmaId): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', true)) {
            return;
        }
        $svc = $this->service();
        $anos = $svc->homologacao()->model()->anosLetivosTurmas();
        $anoLetivo = $this->anoDaRequest($anos);
        $payload = $svc->dadosHomologacoes($anoLetivo, (int) $turmaId);
        $payload['success'] = true;
        $payload['ano_letivo'] = $anoLetivo;
        $payload['turma_id'] = (int) $turmaId;
        $this->json($payload);
    }

    public function dadosDocumentos($turmaId): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', true)) {
            return;
        }
        $svc = $this->service();
        $anos = $svc->homologacao()->model()->anosLetivosTurmas();
        $anoLetivo = $this->anoDaRequest($anos);
        [$periodoTipo, $periodoNumero] = $this->periodoDaRequest();
        $payload = $svc->dadosDocumentos((int) $turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        $payload['success'] = true;
        $payload['ano_letivo'] = $anoLetivo;
        $payload['periodo_tipo'] = $periodoTipo;
        $payload['periodo_numero'] = $periodoNumero;
        $this->json($payload);
    }

    public function diagnosticoMenu(): void
    {
        $user = $this->auth->getUser();
        $perfil = (string) ($user['perfil_admin'] ?? '');
        if (!in_array($perfil, ['dev', 'diretor'], true)) {
            $this->setFlashMessage('Diagnóstico de menu disponível para direção e desenvolvimento.', 'error');
            $this->redirect('/admin/fechamento');
            return;
        }
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $matriz = AdminMenuDiagnostico::avaliar($user ?? []);
        $this->viewWithLayout('admin', 'admin/fechamento/diagnostico', [
            'title' => 'Diagnóstico do menu - EducaTudo',
            'user' => $user,
            'current_page' => 'diagnostico-menu',
            'matriz' => $matriz,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    /**
     * @param list<int> $anos
     */
    private function anoDaRequest(array $anos): int
    {
        $anoLetivo = (int) ($_GET['ano_letivo'] ?? ($anos[0] ?? date('Y')));
        if (!in_array($anoLetivo, $anos, true)) {
            $anoLetivo = (int) ($anos[0] ?? date('Y'));
        }
        return $anoLetivo;
    }

    /**
     * @return array{0:string,1:int}
     */
    private function periodoDaRequest(): array
    {
        $tipo = (string) ($_GET['periodo_tipo'] ?? 'ano');
        if (!isset(ResultadoAcademico::PERIODO_TIPOS[$tipo])) {
            $tipo = 'ano';
        }
        $numero = (int) ($_GET['periodo_numero'] ?? 0);
        if ($tipo === 'ano') {
            $numero = 0;
        }
        return [$tipo, $numero];
    }

    /**
     * @return array{0:string,1:int}
     */
    private function periodoDoPost(): array
    {
        $tipo = (string) ($_POST['periodo_tipo'] ?? 'ano');
        if (!isset(ResultadoAcademico::PERIODO_TIPOS[$tipo])) {
            $tipo = 'ano';
        }
        $numero = (int) ($_POST['periodo_numero'] ?? 0);
        if ($tipo === 'ano') {
            $numero = 0;
        }
        return [$tipo, $numero];
    }

    private function urlImpressaoLote(int $ano, string $tipo, int $numero, int $turmaId = 0, string $serie = ''): string
    {
        $qs = [
            'ano_letivo' => $ano,
            'periodo_tipo' => $tipo,
            'periodo_numero' => $numero,
        ];
        if ($turmaId > 0) {
            $qs['turma_id'] = $turmaId;
        }
        $serie = trim($serie);
        if ($serie !== '') {
            $qs['serie'] = $serie;
        }

        return '/admin/fechamento/impressao-lote?' . http_build_query($qs);
    }

    /**
     * @param list<array<string,mixed>> $turmas
     * @return list<string>
     */
    private function seriesDasTurmas(array $turmas): array
    {
        $series = [];
        foreach ($turmas as $turma) {
            $serie = trim((string) ($turma['serie'] ?? ''));
            if ($serie !== '') {
                $series[$serie] = $serie;
            }
        }
        $lista = array_values($series);
        sort($lista, SORT_NATURAL);

        return $lista;
    }

    /**
     * @return list<int>
     */
    private function jobsDaQuery(): array
    {
        $ids = [];
        foreach (explode(',', (string) ($_GET['jobs'] ?? '')) as $parte) {
            $id = (int) $parte;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function urlTurma(int $turmaId, ?int $ano = null, ?string $tipo = null, ?int $numero = null): string
    {
        $qs = array_filter([
            'ano_letivo' => $ano ?? ($_GET['ano_letivo'] ?? $_POST['ano_letivo'] ?? null),
            'periodo_tipo' => $tipo ?? ($_GET['periodo_tipo'] ?? $_POST['periodo_tipo'] ?? 'ano'),
            'periodo_numero' => $numero ?? ($_GET['periodo_numero'] ?? $_POST['periodo_numero'] ?? 0),
        ], static fn ($v) => $v !== null && $v !== '');
        return '/admin/fechamento/turma/' . $turmaId . ($qs ? ('?' . http_build_query($qs)) : '');
    }

    public function implantar(): void
    {
        if (!$this->podeImplantarAcademico()) {
            $_SESSION['error_message'] = 'Sem permissão para esta ação.';
            $this->redirect('/admin/academico');
            return;
        }
        require_once dirname(__DIR__, 3) . '/Services/ImplantacaoAcademicaService.php';
        $ano = (int) ($_GET['ano_letivo'] ?? date('Y'));
        $diag = (new ImplantacaoAcademicaService())->diagnosticar($ano);
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/fechamento/implantar', [
            'title' => 'Implantar acadêmico — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'implantar-academico',
            'diag' => $diag,
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    private function podeAlterarResultados(): bool
    {
        $user = $this->auth->getUser();
        if (!class_exists('AdminPermissionMatrix')) {
            require_once dirname(__DIR__, 3) . '/Core/AdminPermissionMatrix.php';
        }
        $permissions = AdminPermissionMatrix::effectivePermissionsForUser($this->db, $user ?? []);

        return AdminPermissionMatrix::can($permissions, 'resultados_finais', 'alterar');
    }

    private function podeImplantarAcademico(): bool
    {
        $user = $this->auth->getUser();
        if (!class_exists('AdminPermissionMatrix')) {
            require_once dirname(__DIR__, 3) . '/Core/AdminPermissionMatrix.php';
        }
        $permissions = AdminPermissionMatrix::effectivePermissionsForUser($this->db, $user ?? []);
        return AdminPermissionMatrix::can($permissions, 'configuracao_boletim', 'visualizar')
            || AdminPermissionMatrix::can($permissions, 'resultados_finais', 'visualizar');
    }

    private function simularRegraId(): int
    {
        try {
            require_once dirname(__DIR__, 3) . '/Models/System/BoletimConfig.php';
            $ultima = (new BoletimConfig())->getUltimaRegraNotas();
            return (int) ($ultima['id'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function csrfOuRedirect(string $voltar): bool
    {
        if ($this->verifyCsrfToken($_POST['_token'] ?? '')) {
            return true;
        }
        $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
        $this->redirect($voltar);
        return false;
    }
}
}
