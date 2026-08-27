<?php
/**
 * EducaTudo - Vida Escolar (admin / secretaria / coordenação)
 */

require_once __DIR__ . '/../../../Controllers/Admin/AdminBaseController.php';
require_once __DIR__ . '/../Services/VidaEscolarService.php';
require_once __DIR__ . '/../Services/ProntuarioVidaEscolarService.php';
require_once __DIR__ . '/../Services/VidaEscolarPdfService.php';

use App\Modulos\VidaEscolar\Services\ProntuarioVidaEscolarService;
use App\Modulos\VidaEscolar\Services\VidaEscolarPdfService;
use App\Modulos\VidaEscolar\Services\VidaEscolarService;

if (!class_exists('VidaEscolarAdminController', false)) {
class VidaEscolarAdminController extends AdminBaseController
{
    private function service(): VidaEscolarService
    {
        return new VidaEscolarService();
    }

    private function prontuario(): ProntuarioVidaEscolarService
    {
        return new ProntuarioVidaEscolarService();
    }

    private function exigirPermissao(string $acao): bool
    {
        if (!class_exists('LayoutHelper', false)) {
            require_once dirname(__DIR__, 3) . '/Core/LayoutHelper.php';
        }
        if (!\LayoutHelper::isModuleEnabled('vida_escolar')) {
            $this->setFlashMessage('O módulo Vida Escolar está desativado nesta escola.', 'error');
            $this->redirect('/admin/dashboard');
            return false;
        }
        return $this->enforceAdminPermissionKey('vida_escolar', $acao, false);
    }

    public function index(): void
    {
        if (!$this->exigirPermissao('visualizar')) {
            return;
        }
        $svc = $this->service();
        $anos = $svc->model()->anosLetivosTurmas();
        $anoLetivo = (int) ($_GET['ano_letivo'] ?? ($anos[0] ?? date('Y')));
        if (!in_array($anoLetivo, $anos, true)) {
            $anoLetivo = (int) ($anos[0] ?? date('Y'));
        }
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $fichas = $turmaId > 0 ? $svc->model()->listarFichasTurma($turmaId, $anoLetivo) : [];
        $flash = $this->getFlashMessage();
        if (!$svc->model()->schemaPronto() && empty($flash['message'])) {
            $flash = [
                'message' => 'Execute a migration da Vida Escolar (painel Master) antes de usar o módulo.',
                'type' => 'error',
            ];
        }
        $this->viewWithLayout('admin', 'admin/vida-escolar/index', [
            'title' => 'Vida Escolar - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'vida_escolar',
            'anos' => $anos,
            'ano_letivo' => $anoLetivo,
            'turma_id' => $turmaId,
            'turmas' => $svc->model()->turmasAtivas($anoLetivo),
            'fichas' => $fichas,
            'schema_pronto' => $svc->model()->schemaPronto(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function aluno($id): void
    {
        if (!$this->exigirPermissao('visualizar')) {
            return;
        }
        $alunoId = (int) $id;
        $aluno = $this->service()->model()->alunoPorId($alunoId);
        if (!$aluno) {
            $this->setFlashMessage('Aluno não encontrado.', 'error');
            $this->redirect('/admin/students');
            return;
        }
        $this->redirectAluno(
            $alunoId,
            ProntuarioVidaEscolarService::abaValida($_GET['aba'] ?? 'boletim'),
            (int) ($_GET['ficha_id'] ?? 0),
            (int) ($_GET['ai_job'] ?? 0)
        );
    }

    public function garantir($id): void
    {
        if (!$this->exigirPermissao('cadastrar')) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirectAluno($id);
            return;
        }
        $svc = $this->service();
        $aluno = $svc->model()->alunoPorId((int) $id);
        $turmaId = (int) ($aluno['turma_id'] ?? 0);
        $ano = (int) ($aluno['turma_ano_letivo'] ?? date('Y'));
        $user = $this->auth->getUser();
        $res = $svc->garantirFicha((int) $id, $turmaId, $ano, (int) ($user['id'] ?? 0) ?: null);
        if (!empty($res['success'])) {
            try {
                $svc->sincronizarDeEventosGerados(
                    (int) $id,
                    is_array($user) ? $user : [],
                    null,
                    null,
                    (int) ($res['id'] ?? 0) ?: null,
                    false
                );
            } catch (\Throwable $e) {
                error_log('Vida escolar sync ao garantir ficha: ' . $e->getMessage());
            }
        }
        $this->setFlashMessage(
            $res['success'] ? (empty($res['criada']) ? 'Ficha já existia para este ano.' : 'Ficha de boletim criada. Eventos de notas existentes foram sincronizados.') : ($res['error'] ?? 'Não foi possível criar a ficha.'),
            $res['success'] ? 'success' : 'error'
        );
        $this->redirectAluno($id, 'boletim');
    }

    public function alimentar($id): void
    {
        if (!$this->exigirPermissao('alterar')) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirectAluno($id);
            return;
        }
        $fichaIdPost = (int) ($_POST['ficha_id'] ?? 0);
        if ($fichaIdPost > 0 && !$this->fichaPertenceAoAluno((int) $id, $fichaIdPost)) {
            $this->setFlashMessage('Ficha não encontrada para este aluno.', 'error');
            $this->redirectAluno($id);
            return;
        }
        $res = $this->service()->alimentarDoCalculo(
            (int) $id,
            trim((string) ($_POST['periodo_ref'] ?? '')) ?: null,
            $this->auth->getUser(),
            $fichaIdPost > 0 ? $fichaIdPost : null
        );
        $msg = $res['success']
            ? ('Eventos de notas sincronizados na ficha: ' . (int) ($res['atualizadas'] ?? 0) . ' célula(s). Células de outra escola não foram alteradas.')
            : ($res['error'] ?? 'Falha ao alimentar.');
        $this->setFlashMessage($msg, $res['success'] ? 'success' : 'error');
        $this->redirectAluno($id, 'boletim', $fichaIdPost);
    }

    public function salvarCelula($id, $celulaId): void
    {
        if (!$this->exigirPermissao('alterar')) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirectAluno($id);
            return;
        }
        $res = $this->service()->salvarCelula((int) $celulaId, $_POST, $this->auth->getUser(), (int) $id);
        $this->setFlashMessage($res['success'] ? 'Célula atualizada.' : ($res['error'] ?? 'Falha ao salvar.'), $res['success'] ? 'success' : 'error');
        $this->redirectAluno($id, 'boletim');
    }

    public function fecharBimestre($id): void
    {
        if (!$this->exigirPermissao('alterar')) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirectAluno($id);
            return;
        }
        $fichaId = (int) ($_POST['ficha_id'] ?? 0);
        if (!$this->fichaPertenceAoAluno((int) $id, $fichaId)) {
            $this->setFlashMessage('Ficha não encontrada para este aluno.', 'error');
            $this->redirectAluno($id);
            return;
        }
        $bim = (int) ($_POST['bimestre'] ?? 0);
        $res = $this->service()->fecharBimestre($fichaId, $bim, $this->auth->getUser());
        $this->setFlashMessage($res['success'] ? 'Bimestre fechado.' : ($res['error'] ?? 'Falha.'), $res['success'] ? 'success' : 'error');
        $this->redirectAluno($id, 'boletim', $fichaId);
    }

    public function homologar($id): void
    {
        if (!$this->exigirPermissao('alterar')) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirectAluno($id);
            return;
        }
        $fichaId = (int) ($_POST['ficha_id'] ?? 0);
        if (!$this->fichaPertenceAoAluno((int) $id, $fichaId)) {
            $this->setFlashMessage('Ficha não encontrada para este aluno.', 'error');
            $this->redirectAluno($id);
            return;
        }
        $res = $this->service()->homologarFicha($fichaId, $this->auth->getUser());
        $this->setFlashMessage($res['success'] ? 'Boletim homologado. O ano entra no histórico vivo desta escola.' : ($res['error'] ?? 'Falha.'), $res['success'] ? 'success' : 'error');
        $this->redirectAluno($id, 'boletim', $fichaId);
    }

    public function reabrir($id): void
    {
        if (!$this->exigirPermissao('alterar')) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirectAluno($id);
            return;
        }
        $fichaId = (int) ($_POST['ficha_id'] ?? 0);
        if (!$this->fichaPertenceAoAluno((int) $id, $fichaId)) {
            $this->setFlashMessage('Ficha não encontrada para este aluno.', 'error');
            $this->redirectAluno($id);
            return;
        }
        $res = $this->service()->reabrir(
            $fichaId,
            (int) ($_POST['bimestre'] ?? 0),
            (string) ($_POST['motivo'] ?? ''),
            $this->auth->getUser(),
            (int) ($_POST['celula_id'] ?? 0) ?: null
        );
        $this->setFlashMessage($res['success'] ? 'Período reaberto. A versão anterior permanece na auditoria.' : ($res['error'] ?? 'Falha.'), $res['success'] ? 'success' : 'error');
        $this->redirectAluno($id, 'boletim', $fichaId);
    }

    public function boletimPdf($id): void
    {
        $this->emitirPdfProntuario((int) $id, 'boletim');
    }

    public function pacoteTransferencia($id): void
    {
        $this->emitirPdfProntuario((int) $id, 'pacote');
    }

    public function dossie($id): void
    {
        $this->emitirPdfProntuario((int) $id, 'dossie');
    }

    public function sed($id): void
    {
        $this->emitirPdfProntuario((int) $id, 'sed');
    }

    public function arquivoDocumento($id, $documentoId): void
    {
        if (!$this->exigirPermissao('visualizar')) {
            return;
        }
        $doc = $this->service()->model()->findDocumento((int) $documentoId);
        if (!$doc || (int) ($doc['aluno_id'] ?? 0) !== (int) $id) {
            $this->setFlashMessage('Documento não encontrado.', 'error');
            $this->redirectAluno($id, 'documentos');
            return;
        }
        $key = (string) ($doc['arquivo_key'] ?? '');
        if ($key === '' || !defined('TENANT_SLUG') || trim((string) TENANT_SLUG) === '') {
            $this->setFlashMessage('Arquivo indisponível.', 'error');
            $this->redirectAluno($id, 'documentos');
            return;
        }
        $slug = preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_SLUG);
        $prefixo = $slug . '/vida-escolar/' . (int) $id . '/';
        if ($slug === ''
            || str_contains($key, '..')
            || str_contains($key, '\\')
            || !str_starts_with($key, $prefixo)
        ) {
            $this->setFlashMessage('Arquivo indisponível.', 'error');
            $this->redirectAluno($id, 'documentos');
            return;
        }
        $root = dirname(__DIR__, 4) . '/storage/uploads/' . $slug . '/vida-escolar/' . (int) $id;
        $path = dirname(__DIR__, 4) . '/storage/uploads/' . $key;
        $realRoot = realpath($root);
        $realPath = realpath($path);
        if ($realRoot === false || $realPath === false || !str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR) || !is_file($realPath)) {
            $this->setFlashMessage('Arquivo não encontrado no disco.', 'error');
            $this->redirectAluno($id, 'documentos');
            return;
        }
        $mimeOk = [
            'application/pdf' => true,
            'image/jpeg' => true,
            'image/png' => true,
            'image/webp' => true,
        ];
        $mime = (string) ($doc['arquivo_mime'] ?? '');
        if (!isset($mimeOk[$mime])) {
            $mime = 'application/octet-stream';
        }
        $nome = preg_replace('/[\r\n\t"\\\\]/', '', (string) ($doc['arquivo_nome'] ?? basename($realPath))) ?: 'documento';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . $nome . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($realPath);
        exit;
    }

    public function anoExterno($id): void
    {
        if (!$this->exigirPermissao('cadastrar')) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirectAluno($id);
            return;
        }
        $componentes = [];
        $nomes = $_POST['comp_nome'] ?? [];
        $notas = $_POST['comp_nota'] ?? [];
        $chs = $_POST['comp_ch'] ?? [];
        if (is_array($nomes)) {
            foreach ($nomes as $i => $nome) {
                $nome = trim((string) $nome);
                if ($nome === '') {
                    continue;
                }
                $componentes[] = [
                    'componente_original' => $nome,
                    'nota_original' => $notas[$i] ?? '',
                    'carga_horaria' => $chs[$i] ?? '',
                ];
            }
        }
        $input = $_POST;
        $input['componentes'] = $componentes;
        $input['documento_id'] = $this->documentoIdDoAluno((int) $id, $_POST['documento_id'] ?? 0);
        $res = $this->service()->adicionarAnoExterno((int) $id, $input, $this->auth->getUser());
        $this->setFlashMessage($res['success'] ? 'Ano de escolarização registrado.' : ($res['error'] ?? 'Falha.'), $res['success'] ? 'success' : 'error');
        $this->redirectAluno($id, 'trajetoria');
    }

    public function documento($id): void
    {
        if (!$this->exigirPermissao('cadastrar')) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirectAluno($id);
            return;
        }
        $file = $_FILES['arquivo'] ?? null;
        $arquivo = null;
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $arquivo = $this->gravarArquivo((int) $id, $file);
            } catch (\Throwable $e) {
                $this->setFlashMessage($e->getMessage(), 'error');
                $this->redirectAluno($id, 'documentos');
                return;
            }
        }
        $this->service()->model()->criarDocumento(array_merge([
            'aluno_id' => (int) $id,
            'tipo' => in_array($_POST['tipo'] ?? '', ['historico', 'ficha_individual', 'declaracao_transferencia', 'guia', 'outro'], true)
                ? $_POST['tipo'] : 'historico',
            'escola_emissora' => trim((string) ($_POST['escola_emissora'] ?? '')) ?: null,
            'data_emissao' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['data_emissao'] ?? '')) ? $_POST['data_emissao'] : null,
            'observacao' => trim((string) ($_POST['observacao'] ?? '')) ?: null,
            'enviado_por' => (int) ($this->auth->getUser()['id'] ?? 0) ?: null,
        ], $arquivo ?? []));
        $this->prontuario()->reconhecerEntregasExternas((int) $id, $this->service());
        $this->setFlashMessage('Documento anexado. O checklist da ficha foi atualizado.', 'success');
        $this->redirectAluno($id, 'documentos');
    }

    public function lerHistorico($id, $documentoId): void
    {
        if (!$this->exigirPermissao('cadastrar')) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirectAluno($id, 'documentos');
            return;
        }
        $alunoId = (int) $id;
        $docId = (int) $documentoId;
        $doc = $this->service()->model()->findDocumento($docId);
        if (!$doc || (int) ($doc['aluno_id'] ?? 0) !== $alunoId) {
            $this->setFlashMessage('Documento não encontrado para este aluno.', 'error');
            $this->redirectAluno($id, 'documentos');
            return;
        }
        if (!$this->podeLerHistoricoIa()) {
            $this->setFlashMessage('Leitura por IA indisponível (TudiCoins desligado ou sem saldo na carteira da escola).', 'error');
            $this->redirectAluno($id, 'documentos');
            return;
        }

        require_once dirname(__DIR__, 3) . '/Services/AIJobService.php';
        require_once dirname(__DIR__, 3) . '/Services/CreditosService.php';
        require_once dirname(__DIR__, 3) . '/Core/CreditosModuleRegistry.php';
        $user = $this->auth->getUser();
        $creditsModulo = 'vida_escolar_ler_historico';
        $creditsRef = 'vida_escolar_doc:' . $docId;
        $debitou = false;
        $jobId = 0;
        try {
            $creditos = new \App\Services\CreditosService();
            if (!$creditos->podeConsumir('escola', \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID, $creditsModulo)) {
                throw new \RuntimeException('TudiCoins insuficientes na carteira da escola.');
            }
            $creditos->consumirEscola($creditsModulo, $creditsRef);
            $debitou = true;
            $jobId = \App\Services\AIJobService::enqueue('vida_escolar_ler_historico', [
                'aluno_id' => $alunoId,
                'documento_id' => $docId,
                'user_id' => (int) ($user['id'] ?? 0),
                'user_nome' => (string) ($user['nome'] ?? ''),
                'user_tipo' => (string) ($user['tipo'] ?? 'admin'),
                'credits_ref' => $creditsRef,
                'credits_modulo' => $creditsModulo,
            ], (int) ($user['id'] ?? 0), 'admin');
        } catch (\Throwable $e) {
            if ($debitou) {
                try {
                    (new \App\Services\CreditosService())->estornarPorReferencia($creditsModulo, $creditsRef);
                } catch (\Throwable $eEstorno) {
                    error_log('Vida escolar estorno OCR: ' . $eEstorno->getMessage());
                }
            }
            $this->setFlashMessage('Não foi possível iniciar a leitura: ' . $e->getMessage(), 'error');
            $this->redirectAluno($id, 'documentos');
            return;
        }
        $this->setFlashMessage('Lendo o histórico. Em instantes o rascunho aparece na Trajetória para conferir.', 'success');
        $this->redirectAluno($id, 'documentos', 0, $jobId);
    }

    public function importar($id): void
    {
        if (!$this->exigirPermissao('cadastrar')) {
            return;
        }
        $this->aluno($id);
    }

    public function salvarImportacao($id): void
    {
        if (!$this->exigirPermissao('cadastrar')) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirectAluno($id);
            return;
        }
        $input = $this->montarPayloadImportacao($_POST);
        $input['documento_id'] = $this->documentoIdDoAluno((int) $id, $input['documento_id'] ?? 0);
        $res = $this->service()->salvarImportacao((int) $id, $input, $this->auth->getUser());
        $this->setFlashMessage($res['success'] ? 'Rascunho da importação salvo. Confira e valide.' : ($res['error'] ?? 'Falha.'), $res['success'] ? 'success' : 'error');
        $this->redirectAluno($id, 'trajetoria');
    }

    public function validarImportacao($id, $importacaoId): void
    {
        if (!$this->exigirPermissao('alterar')) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirectAluno($id);
            return;
        }
        $imp = $this->service()->model()->findImportacao((int) $importacaoId);
        if (!$imp || (int) ($imp['aluno_id'] ?? 0) !== (int) $id) {
            $this->setFlashMessage('Importação não encontrada para este aluno.', 'error');
            $this->redirectAluno($id);
            return;
        }
        $res = $this->service()->validarImportacao((int) $importacaoId, $this->auth->getUser());
        $msg = $res['success']
            ? ('Importação validada. Anos anteriores: ' . (int) ($res['resumo']['anos_anteriores'] ?? 0) . '. Células do ano atual: ' . (int) ($res['resumo']['celulas_externas'] ?? 0) . '.')
            : ($res['error'] ?? 'Falha ao validar.');
        $this->setFlashMessage($msg, $res['success'] ? 'success' : 'error');
        $this->redirectAluno($id, 'trajetoria');
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private function montarPayloadImportacao(array $post): array
    {
        $anos = [];
        $n = min(20, max(0, (int) ($post['anos_qtd'] ?? 0)));
        for ($i = 0; $i < $n; $i++) {
            $anoLetivo = trim((string) ($post['ano_letivo'][$i] ?? ''));
            $serie = trim((string) ($post['serie_ano'][$i] ?? ''));
            if ($anoLetivo === '' || $serie === '') {
                continue;
            }
            $comps = [];
            $nomes = $post['comp_nome'][$i] ?? [];
            $notas = $post['comp_nota'][$i] ?? [];
            if (is_array($nomes)) {
                foreach (array_slice($nomes, 0, 40, true) as $j => $nome) {
                    $nome = trim((string) $nome);
                    if ($nome === '') {
                        continue;
                    }
                    $comps[] = ['componente_original' => $nome, 'nota_original' => $notas[$j] ?? ''];
                }
            }
            $anos[] = [
                'ano_letivo' => $anoLetivo,
                'serie_ano' => $serie,
                'escola_nome' => trim((string) ($post['escola_origem'] ?? '')),
                'resultado' => trim((string) ($post['resultado'][$i] ?? '')),
                'componentes' => $comps,
            ];
        }
        $bims = [];
        $txt = static function ($v): string {
            return is_scalar($v) ? trim((string) $v) : '';
        };
        $blocos = min(80, max(0, (int) ($post['bim_bloco_qtd'] ?? 0)));
        if ($blocos > 0) {
            for ($i = 0; $i < $blocos; $i++) {
                $comp = $txt($post['bim_comp'][$i] ?? '');
                $materiaId = (int) ($post['bim_materia_id'][$i] ?? 0);
                if ($materiaId <= 0 && $comp === '') {
                    continue;
                }
                $notas = is_array($post['bim_nota'][$i] ?? null) ? $post['bim_nota'][$i] : [];
                $faltas = is_array($post['bim_faltas'][$i] ?? null) ? $post['bim_faltas'][$i] : [];
                for ($p = 1; $p <= 4; $p++) {
                    $nota = $txt($notas[$p] ?? '');
                    $falta = $txt($faltas[$p] ?? '');
                    if ($nota === '' && $falta === '') {
                        continue;
                    }
                    $bims[] = [
                        'componente' => $comp,
                        'materia_id' => $materiaId,
                        'periodo_numero' => $p,
                        'nota' => $nota,
                        'faltas' => $falta,
                    ];
                }
            }
        } else {
            $bn = min(40, max(0, (int) ($post['bims_qtd'] ?? 0)));
            for ($i = 0; $i < $bn; $i++) {
                $comp = $txt($post['bim_comp'][$i] ?? '');
                $periodo = (int) ($post['bim_periodo'][$i] ?? 0);
                $materiaId = (int) ($post['bim_materia_id'][$i] ?? 0);
                $notaRaw = $post['bim_nota'][$i] ?? '';
                $faltasRaw = $post['bim_faltas'][$i] ?? '';
                if (is_array($notaRaw) || is_array($faltasRaw)) {
                    continue;
                }
                $nota = $txt($notaRaw);
                $faltas = $txt($faltasRaw);
                if ($periodo < 1 || $periodo > 4) {
                    continue;
                }
                if ($materiaId <= 0 && $comp === '') {
                    continue;
                }
                if ($nota === '' && $faltas === '') {
                    continue;
                }
                $bims[] = [
                    'componente' => $comp,
                    'materia_id' => $materiaId,
                    'periodo_numero' => $periodo,
                    'nota' => $nota,
                    'faltas' => $faltas,
                ];
            }
        }
        return [
            'importacao_id' => (int) ($post['importacao_id'] ?? 0),
            'escola_origem' => $post['escola_origem'] ?? '',
            'escola_inep' => $post['escola_inep'] ?? '',
            'municipio' => $post['municipio'] ?? '',
            'uf' => $post['uf'] ?? '',
            'data_transferencia' => $post['data_transferencia'] ?? '',
            'data_entrada' => $post['data_entrada'] ?? '',
            'documento_id' => $post['documento_id'] ?? 0,
            'anos_anteriores' => $anos,
            'bimestres_atuais' => $bims,
        ];
    }

    private function redirectAluno($id, string $aba = '', int $fichaId = 0, int $aiJobId = 0): void
    {
        $qs = ['tab' => 'vida-escolar'];
        $aba = $aba !== '' ? $aba : 'boletim';
        if ($aba === 'identidade') {
            $aba = 'boletim';
        }
        $qs['ve_aba'] = $aba;
        if ($fichaId > 0) {
            $qs['ficha_id'] = $fichaId;
        }
        if ($aiJobId > 0) {
            $qs['ai_job'] = $aiJobId;
        }
        $this->redirect('/admin/students/' . (int) $id . '?' . http_build_query($qs));
    }

    private function emitirPdfProntuario(int $alunoId, string $tipo): void
    {
        if (!$this->exigirPermissao('visualizar')) {
            return;
        }
        $svc = $this->service();
        $aluno = $svc->model()->alunoPorId($alunoId);
        if (!$aluno) {
            $this->setFlashMessage('Aluno não encontrado.', 'error');
            $this->redirect('/admin/students');
            return;
        }
        $fichaId = (int) ($_GET['ficha_id'] ?? 0);
        if ($tipo === 'boletim') {
            if (!$this->fichaPertenceAoAluno($alunoId, $fichaId)) {
                $this->setFlashMessage('Ficha não encontrada.', 'error');
                $this->redirectAluno($alunoId);
                return;
            }
        }
        $dados = $this->prontuario()->montar($alunoId, $svc, $fichaId);
        $dados['planilha_sed'] = $this->prontuario()->planilhaSed($dados);
        $slug = preg_replace('/[^a-z0-9]+/i', '_', (string) ($aluno['nome'] ?? 'aluno')) ?: 'aluno';
        $pdf = new VidaEscolarPdfService();
        try {
            if ($tipo === 'boletim') {
                $pdf->emitirBoletim($dados, VidaEscolarService::PERIODOS, $this->config, 'boletim_' . $slug . '.pdf');
            } elseif ($tipo === 'pacote') {
                $pdf->emitirPacote($dados, VidaEscolarService::PERIODOS, $this->config, 'pacote_transferencia_' . $slug . '.pdf');
            } elseif ($tipo === 'sed') {
                $pdf->emitirSed($dados, VidaEscolarService::PERIODOS, $this->config, 'planilha_sed_' . $slug . '.pdf');
            } else {
                $pdf->emitirDossie($dados, VidaEscolarService::PERIODOS, $this->config, 'dossie_' . $slug . '.pdf');
            }
        } catch (\Throwable $e) {
            error_log('VidaEscolarAdminController PDF: ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível gerar o PDF. Confira o Layout de documentos (papel timbrado e modelos da Vida Escolar).', 'error');
            $this->redirectAluno($alunoId, $tipo === 'sed' ? 'conferencia' : ($tipo === 'boletim' ? 'boletim' : 'dossie'), $fichaId);
        }
    }

    private function fichaPertenceAoAluno(int $alunoId, int $fichaId): bool
    {
        if ($alunoId <= 0 || $fichaId <= 0) {
            return false;
        }
        $ficha = $this->service()->model()->findFicha($fichaId);
        return is_array($ficha) && (int) ($ficha['aluno_id'] ?? 0) === $alunoId;
    }

    private function documentoIdDoAluno(int $alunoId, $documentoId): ?int
    {
        $id = (int) $documentoId;
        if ($id <= 0 || $alunoId <= 0) {
            return null;
        }
        $doc = $this->service()->model()->findDocumento($id);
        if (!$doc || (int) ($doc['aluno_id'] ?? 0) !== $alunoId) {
            return null;
        }
        return $id;
    }

    private function podeLerHistoricoIa(): bool
    {
        require_once dirname(__DIR__, 3) . '/Core/CreditosModuleRegistry.php';
        require_once dirname(__DIR__, 3) . '/Services/CreditosService.php';
        if (!\CreditosModuleRegistry::acaoIaDisponivel('vida_escolar_ler_historico')) {
            return false;
        }
        try {
            return (new \App\Services\CreditosService())->podeConsumir(
                'escola',
                \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID,
                'vida_escolar_ler_historico'
            );
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $file
     * @return array<string,mixed>
     */
    private function gravarArquivo(int $alunoId, array $file): array
    {
        $ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new \Exception('Envie PDF ou imagem (JPG/PNG/WebP).');
        }
        if ((int) ($file['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new \Exception('Arquivo maior que 10MB.');
        }
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (is_string($detected)) {
                    $mime = $detected;
                }
            }
        }
        $okMime = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];
        $esperado = $okMime[$ext] ?? '';
        if ($esperado === '' || $mime !== $esperado) {
            throw new \Exception('Extensão e conteúdo do arquivo não coincidem.');
        }
        if (!defined('TENANT_SLUG') || trim((string) TENANT_SLUG) === '') {
            throw new \Exception('Não foi possível gravar o arquivo (escola não identificada).');
        }
        $slug = preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_SLUG);
        if ($slug === '') {
            throw new \Exception('Não foi possível gravar o arquivo (escola não identificada).');
        }
        $dir = dirname(__DIR__, 4) . '/storage/uploads/' . $slug . '/vida-escolar/' . $alunoId;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \Exception('Não foi possível gravar o arquivo.');
        }
        $nome = 'doc_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . '/' . $nome;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \Exception('Falha ao mover o arquivo.');
        }
        return [
            'arquivo_key' => $slug . '/vida-escolar/' . $alunoId . '/' . $nome,
            'arquivo_nome' => (string) ($file['name'] ?? $nome),
            'arquivo_mime' => $mime,
            'arquivo_tamanho' => (int) ($file['size'] ?? 0),
        ];
    }
}
}
