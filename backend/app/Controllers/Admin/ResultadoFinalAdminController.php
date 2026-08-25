<?php
/**
 * EducaTudo - Resultados finais: fechamento, documentos oficiais e relatórios.
 */

require_once __DIR__ . '/AdminBaseController.php';
require_once __DIR__ . '/../../Models/Education/ResultadoAcademico.php';
require_once __DIR__ . '/../../Models/Education/ComponenteCurricular.php';
require_once __DIR__ . '/../../Services/ResultadoHomologacaoService.php';
require_once __DIR__ . '/../../Services/DocumentoOficialService.php';

if (!class_exists('ResultadoFinalAdminController')) {
class ResultadoFinalAdminController extends AdminBaseController
{
    private function homologacao(): ResultadoHomologacaoService
    {
        return new ResultadoHomologacaoService();
    }

    private function documentos(): DocumentoOficialService
    {
        return new DocumentoOficialService($this->homologacao());
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $svc = $this->homologacao();
        $anos = $svc->model()->anosLetivosTurmas();
        $anoLetivo = (int) ($_GET['ano_letivo'] ?? ($anos[0] ?? date('Y')));
        if (!in_array($anoLetivo, $anos, true)) {
            $anoLetivo = (int) ($anos[0] ?? date('Y'));
        }
        [$periodoTipo, $periodoNumero] = $this->periodoDaRequest();
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $turmas = $svc->model()->turmasAtivas($anoLetivo);
        $paineis = [];
        foreach ($turmas as $turma) {
            if ($turmaId > 0 && (int) $turma['id'] !== $turmaId) {
                continue;
            }
            $paineis[] = $svc->previewTurma((int) $turma['id'], $anoLetivo, $periodoTipo, $periodoNumero);
        }

        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/resultados-finais/index', [
            'title' => 'Resultados finais - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'resultados-finais',
            'anos' => $anos,
            'ano_letivo' => $anoLetivo,
            'periodo_tipo' => $periodoTipo,
            'periodo_numero' => $periodoNumero,
            'turma_id' => $turmaId,
            'turmas' => $turmas,
            'paineis' => $paineis,
            'config' => $svc->model()->getConfigFechamento(),
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
        $svc = $this->homologacao();
        $anos = $svc->model()->anosLetivosTurmas();
        $anoLetivo = (int) ($_GET['ano_letivo'] ?? ($anos[0] ?? date('Y')));
        [$periodoTipo, $periodoNumero] = $this->periodoDaRequest();
        $preview = $svc->previewTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        $especiais = $svc->model()->listarEspeciaisTurma($turmaId, $anoLetivo);

        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/resultados-finais/turma', [
            'title' => 'Fechamento da turma - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'resultados-finais',
            'anos' => $anos,
            'ano_letivo' => $anoLetivo,
            'periodo_tipo' => $periodoTipo,
            'periodo_numero' => $periodoNumero,
            'preview' => $preview,
            'especiais' => $especiais,
            'componentes' => $this->componentesCatalogo(),
            'alunos' => $preview['linhas'],
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
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
        $result = $this->homologacao()->homologarTurma(
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

    public function reabrir($resultadoId): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'excluir', false)) {
            return;
        }
        $resultadoId = (int) $resultadoId;
        $doc = $this->homologacao()->model()->findById($resultadoId);
        $voltar = $doc ? $this->urlTurma((int) $doc['turma_id'], (int) $doc['ano_letivo'], (string) $doc['periodo_tipo'], (int) $doc['periodo_numero']) : '/admin/resultados-finais';
        if (!$this->csrfOuRedirect($voltar)) {
            return;
        }
        $result = $this->homologacao()->reabrir(
            $resultadoId,
            (int) ($this->auth->getUser()['id'] ?? 0),
            (string) ($_POST['motivo'] ?? '')
        );
        $this->setFlashMessage(
            $result['success'] ? 'Resultado reaberto. A versão anterior foi preservada.' : ($result['error'] ?? 'Falha'),
            $result['success'] ? 'success' : 'error'
        );
        $this->redirect($voltar);
    }

    public function especial($turmaId): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'alterar', false)) {
            return;
        }
        $turmaId = (int) $turmaId;
        $voltar = $this->urlTurma($turmaId);
        if (!$this->csrfOuRedirect($voltar)) {
            return;
        }
        $input = $_POST;
        $input['turma_id'] = $turmaId;
        $input['ano_letivo'] = (int) ($_POST['ano_letivo'] ?? date('Y'));
        $result = $this->homologacao()->salvarEspecial($input, (int) ($this->auth->getUser()['id'] ?? 0));
        $this->setFlashMessage($result['success'] ? 'Situação especial registrada.' : ($result['error'] ?? 'Falha'), $result['success'] ? 'success' : 'error');
        $this->redirect($voltar);
    }

    public function excluirEspecial($turmaId, $id): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'excluir', false)) {
            return;
        }
        $voltar = $this->urlTurma((int) $turmaId);
        if (!$this->csrfOuRedirect($voltar)) {
            return;
        }
        $this->homologacao()->model()->excluirEspecial((int) $id, (int) $turmaId);
        $this->setFlashMessage('Situação especial removida.', 'success');
        $this->redirect($voltar);
    }

    public function ficha($alunoId): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $alunoId = (int) $alunoId;
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $anoLetivo = (int) ($_GET['ano_letivo'] ?? date('Y'));
        [$periodoTipo, $periodoNumero] = $this->periodoDaRequest();
        if ($turmaId <= 0) {
            $aluno = $this->db->fetch('SELECT turma_id FROM alunos WHERE id = :id LIMIT 1', ['id' => $alunoId]);
            $turmaId = (int) ($aluno['turma_id'] ?? 0);
        }
        $payload = $this->documentos()->montarFicha($alunoId, $turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        if (!$payload) {
            $this->setFlashMessage('Não foi possível montar a ficha deste aluno.', 'error');
            $this->redirect($turmaId > 0 ? $this->urlTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero) : '/admin/resultados-finais');
            return;
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/resultados-finais/ficha', [
            'title' => 'Ficha Individual - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'resultados-finais',
            'payload' => $payload,
            'aluno_id' => $alunoId,
            'turma_id' => $turmaId,
            'ano_letivo' => $anoLetivo,
            'periodo_tipo' => $periodoTipo,
            'periodo_numero' => $periodoNumero,
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function fichaPdf($alunoId): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $alunoId = (int) $alunoId;
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $anoLetivo = (int) ($_GET['ano_letivo'] ?? date('Y'));
        if ($turmaId <= 0) {
            $aluno = $this->db->fetch('SELECT turma_id FROM alunos WHERE id = :id LIMIT 1', ['id' => $alunoId]);
            $turmaId = (int) ($aluno['turma_id'] ?? 0);
        }
        try {
            $emitido = $this->documentos()->emitirFicha(
                $alunoId,
                $turmaId,
                $anoLetivo,
                (string) ($_GET['periodo_tipo'] ?? 'ano'),
                (int) ($_GET['periodo_numero'] ?? 0),
                (int) ($this->auth->getUser()['id'] ?? 0),
                $this->config ?? null
            );
            $this->outputPdf($emitido['html'], 'ficha_individual_' . (int) $alunoId . '.pdf', $emitido['orientacao'], $emitido['papel'] ?? 'A4');
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/admin/students/' . (int) $alunoId);
        }
    }

    public function ata($turmaId): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $turmaId = (int) $turmaId;
        $anoLetivo = (int) ($_GET['ano_letivo'] ?? date('Y'));
        [$periodoTipo, $periodoNumero] = $this->periodoDaRequest();
        $preview = $this->homologacao()->previewTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/resultados-finais/ata', [
            'title' => 'Ata de Resultados Finais - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'resultados-finais',
            'preview' => $preview,
            'ano_letivo' => $anoLetivo,
            'periodo_tipo' => $periodoTipo,
            'periodo_numero' => $periodoNumero,
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function ataPdf($turmaId): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        try {
            $emitido = $this->documentos()->emitirAta(
                (int) $turmaId,
                (int) ($_GET['ano_letivo'] ?? date('Y')),
                (string) ($_GET['periodo_tipo'] ?? 'ano'),
                (int) ($_GET['periodo_numero'] ?? 0),
                (int) ($this->auth->getUser()['id'] ?? 0),
                $this->config ?? null
            );
            $this->outputPdf($emitido['html'], 'ata_resultados_turma_' . (int) $turmaId . '.pdf', $emitido['orientacao'], $emitido['papel'] ?? 'A4');
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect($this->urlTurma((int) $turmaId));
        }
    }

    public function boletimPdf($alunoId): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $alunoId = (int) $alunoId;
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        if ($turmaId <= 0) {
            $aluno = $this->db->fetch('SELECT turma_id FROM alunos WHERE id = :id LIMIT 1', ['id' => $alunoId]);
            $turmaId = (int) ($aluno['turma_id'] ?? 0);
        }
        try {
            $emitido = $this->documentos()->emitirBoletim(
                $alunoId,
                $turmaId,
                (int) ($_GET['ano_letivo'] ?? date('Y')),
                (string) ($_GET['periodo_tipo'] ?? 'ano'),
                (int) ($_GET['periodo_numero'] ?? 0),
                (int) ($this->auth->getUser()['id'] ?? 0),
                $this->config ?? null
            );
            $this->outputPdf($emitido['html'], 'boletim_oficial_' . (int) $alunoId . '.pdf', $emitido['orientacao'], $emitido['papel'] ?? 'A4');
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/admin/students/' . (int) $alunoId);
        }
    }

    public function relatorios(): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $svc = $this->homologacao();
        $anos = $svc->model()->anosLetivosTurmas();
        $anoLetivo = (int) ($_GET['ano_letivo'] ?? ($anos[0] ?? date('Y')));
        [$periodoTipo, $periodoNumero] = $this->periodoDaRequest();
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $tipo = (string) ($_GET['tipo'] ?? 'relatorio_fechamento');
        if (!isset(ResultadoAcademico::DOCUMENTO_TIPOS[$tipo]) || !str_starts_with($tipo, 'relatorio_')) {
            $tipo = 'relatorio_fechamento';
        }
        $turmas = $svc->model()->turmasAtivas($anoLetivo);
        $linhas = [];
        $preview = null;
        if ($turmaId > 0) {
            $preview = $svc->previewTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
            $linhas = $this->documentos()->filtrarRelatorio($preview['linhas'], $tipo);
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/resultados-finais/relatorios', [
            'title' => 'Relatórios acadêmicos - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'resultados-finais',
            'anos' => $anos,
            'ano_letivo' => $anoLetivo,
            'periodo_tipo' => $periodoTipo,
            'periodo_numero' => $periodoNumero,
            'turma_id' => $turmaId,
            'tipo' => $tipo,
            'turmas' => $turmas,
            'preview' => $preview,
            'linhas' => $linhas,
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function relatorioPdf(): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $tipo = (string) ($_GET['tipo'] ?? 'relatorio_fechamento');
        if ($turmaId <= 0) {
            $this->setFlashMessage('Selecione uma turma.', 'error');
            $this->redirect('/admin/resultados-finais/relatorios');
            return;
        }
        try {
            $emitido = $this->documentos()->emitirRelatorio(
                $tipo,
                $turmaId,
                (int) ($_GET['ano_letivo'] ?? date('Y')),
                (string) ($_GET['periodo_tipo'] ?? 'ano'),
                (int) ($_GET['periodo_numero'] ?? 0),
                (int) ($this->auth->getUser()['id'] ?? 0),
                $this->config ?? null
            );
            $this->outputPdf($emitido['html'], $tipo . '_turma_' . $turmaId . '.pdf', $emitido['orientacao'], $emitido['papel'] ?? 'A4');
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/admin/resultados-finais/relatorios?turma_id=' . $turmaId);
        }
    }

    public function relatorioCsv(): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $tipo = (string) ($_GET['tipo'] ?? 'relatorio_fechamento');
        if (!isset(ResultadoAcademico::DOCUMENTO_TIPOS[$tipo]) || !str_starts_with($tipo, 'relatorio_')) {
            $tipo = 'relatorio_fechamento';
        }
        $anoLetivo = (int) ($_GET['ano_letivo'] ?? date('Y'));
        [$periodoTipo, $periodoNumero] = $this->periodoDaRequest();
        if ($turmaId <= 0) {
            $this->setFlashMessage('Selecione uma turma.', 'error');
            $this->redirect('/admin/resultados-finais/relatorios');
            return;
        }
        $preview = $this->homologacao()->previewTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        $linhas = $this->documentos()->filtrarRelatorio($preview['linhas'], $tipo);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $this->nomeArquivoPdf($tipo . '_turma_' . $turmaId . '.csv') . '"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Aluno', 'RA', 'Média', 'Frequência', 'Situação', 'Status', 'Pendências'], ';');
        foreach ($linhas as $linha) {
            $media = $linha['avaliado']['media_final'] ?? '';
            $freq = $linha['frequencia']['percentual'] ?? '';
            fputcsv($out, [
                $this->csvCelula((string) ($linha['aluno']['nome'] ?? '')),
                $this->csvCelula((string) ($linha['aluno']['ra'] ?? '')),
                is_numeric($media) ? number_format((float) $media, 2, ',', '') : '',
                is_numeric($freq) ? number_format((float) $freq, 1, ',', '') : '',
                $linha['rotulo'] ?? '',
                $linha['status'] ?? '',
                implode(' | ', $linha['pendencias'] ?? []),
            ], ';');
        }
        fclose($out);
        exit;
    }

    public function layouts(): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'visualizar', false)) {
            return;
        }
        $model = $this->homologacao()->model();
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/resultados-finais/layouts', [
            'title' => 'Layouts dos documentos - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'resultados-finais',
            'tipos' => ResultadoAcademico::DOCUMENTO_TIPOS,
            'escolhidos' => $model->listarLayoutsEscola(),
            'modelos' => $this->documentos()->modelosDisponiveis(),
            'config' => $model->getConfigFechamento(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function salvarLayouts(): void
    {
        if (!$this->enforceAdminPermissionKey('resultados_finais', 'alterar', false)) {
            return;
        }
        if (!$this->csrfOuRedirect('/admin/resultados-finais/layouts')) {
            return;
        }
        $model = $this->homologacao()->model();
        $layouts = is_array($_POST['layouts'] ?? null) ? $_POST['layouts'] : [];
        foreach ($layouts as $tipo => $codigo) {
            $model->salvarLayout((string) $tipo, (string) $codigo);
        }
        $model->salvarConfigFechamento([
            'exigir_conselho' => !empty($_POST['exigir_conselho']),
            'exigir_frequencia' => !empty($_POST['exigir_frequencia']),
            'exigir_notas' => !empty($_POST['exigir_notas']),
        ], (int) ($this->auth->getUser()['id'] ?? 0));
        $this->setFlashMessage('Layouts e regras de fechamento salvos.', 'success');
        $this->redirect('/admin/resultados-finais/layouts');
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

    private function urlTurma(int $turmaId, ?int $ano = null, ?string $tipo = null, ?int $numero = null): string
    {
        $qs = array_filter([
            'ano_letivo' => $ano ?? ($_GET['ano_letivo'] ?? $_POST['ano_letivo'] ?? null),
            'periodo_tipo' => $tipo ?? ($_GET['periodo_tipo'] ?? $_POST['periodo_tipo'] ?? 'ano'),
            'periodo_numero' => $numero ?? ($_GET['periodo_numero'] ?? $_POST['periodo_numero'] ?? 0),
        ], static fn ($v) => $v !== null && $v !== '');
        return '/admin/resultados-finais/turma/' . $turmaId . ($qs ? ('?' . http_build_query($qs)) : '');
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

    private function nomeArquivoPdf(string $filename): string
    {
        $limpo = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        return (is_string($limpo) && $limpo !== '') ? $limpo : 'documento.pdf';
    }

    private function csvCelula(string $valor): string
    {
        if ($valor !== '' && preg_match('/^[=+\-@\t\r]/', $valor)) {
            return "'" . $valor;
        }
        return $valor;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function componentesCatalogo(): array
    {
        try {
            return (new ComponenteCurricular())->getAll(false) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function outputPdf(string $html, string $filename, string $orientation = 'portrait', string $paper = 'A4'): void
    {
        $orientation = $orientation === 'landscape' ? 'landscape' : 'portrait';
        $paper = strtoupper($paper) === 'A5' ? 'A5' : 'A4';
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
            $chroot = defined('BASE_PATH') ? (BASE_PATH . '/storage') : null;
            if (is_string($chroot) && is_dir($chroot)) {
                $options->setChroot($chroot);
            }
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper($paper, $orientation);
            $dompdf->render();
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $this->nomeArquivoPdf($filename) . '"');
            echo $dompdf->output();
            exit;
        } finally {
            ini_set('display_errors', (string) $old);
        }
    }
}
}
