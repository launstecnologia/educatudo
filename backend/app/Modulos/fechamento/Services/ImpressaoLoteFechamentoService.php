<?php
require_once __DIR__ . '/FechamentoMaquinaEstados.php';
require_once __DIR__ . '/../../../Services/DocumentoOficialService.php';
require_once __DIR__ . '/../../../Services/HistoricoEscolarService.php';
require_once __DIR__ . '/../../vida-escolar/Services/VidaEscolarPdfService.php';

use App\Modulos\VidaEscolar\Services\VidaEscolarPdfService;
use App\Services\HistoricoEscolarService;

/**
 * Impressão em lote do fechamento homologado:
 * boletim, ficha individual, histórico e resultado final (ata) da turma.
 */
class ImpressaoLoteFechamentoService
{
    public const DOCUMENTOS = [
        'boletim' => 'Boletim',
        'ficha' => 'Ficha individual',
        'historico' => 'Histórico escolar',
        'resultado' => 'Resultado final por turma',
        'relatorio' => 'Relatório de fechamento por turma',
    ];

    /** Os quatro documentos do pacote, na ordem de impressão. */
    public const PACOTE = ['boletim', 'ficha', 'historico', 'resultado'];

    public const TIPO_JOB = 'fechamento_impressao_lote';

    public const TIPO_ARQUIVO = 'fechamento_impressao';

    /** Acima disso a geração pede uma turma, para não montar a escola inteira. */
    public const MAX_ALUNOS = 80;

    private string $rotuloJob = '';

    private DocumentoOficialService $documentos;
    private HistoricoEscolarService $historicos;
    private VidaEscolarPdfService $pdfHistorico;

    public function __construct(
        ?DocumentoOficialService $documentos = null,
        ?HistoricoEscolarService $historicos = null,
        ?VidaEscolarPdfService $pdfHistorico = null
    ) {
        $this->documentos = $documentos ?? new DocumentoOficialService();
        $this->historicos = $historicos ?? new HistoricoEscolarService();
        $this->pdfHistorico = $pdfHistorico ?? new VidaEscolarPdfService();
    }

    /**
     * @param list<array<string,mixed>> $paineis
     * @return array{pode:bool,total_turmas:int,homologadas:int,pendentes:list<string>,total_alunos:int}
     */
    public function resumo(array $paineis): array
    {
        $homologadas = 0;
        $pendentes = [];
        $alunos = 0;
        foreach ($paineis as $painel) {
            $nome = trim((string) ($painel['turma']['nome'] ?? 'Turma'));
            if (($painel['status'] ?? '') === FechamentoMaquinaEstados::HOMOLOGADO) {
                $homologadas++;
                $alunos += (int) ($painel['resumo']['total'] ?? 0);
                continue;
            }
            $pendentes[] = $nome !== '' ? $nome : 'Turma';
        }
        $total = count($paineis);

        return [
            'pode' => $total > 0 && $homologadas === $total,
            'total_turmas' => $total,
            'homologadas' => $homologadas,
            'pendentes' => $pendentes,
            'total_alunos' => $alunos,
        ];
    }

    public function escopoInformado(int $turmaId, string $serie): bool
    {
        return $turmaId > 0 || trim($serie) !== '';
    }

    /**
     * @param list<array<string,mixed>> $paineis
     */
    public function exigirEscopoLeve(array $paineis, string $documento): void
    {
        $resumo = $this->resumo($paineis);
        if ($resumo['total_turmas'] > 12) {
            throw new RuntimeException('Escolha uma turma ou uma série menor. Este recorte ainda tem ' . $resumo['total_turmas'] . ' turmas.');
        }
        if (in_array($documento, ['boletim', 'ficha', 'historico'], true) && $resumo['total_alunos'] > self::MAX_ALUNOS) {
            $nome = self::DOCUMENTOS[$documento] ?? 'documento';
            throw new RuntimeException(
                'Este recorte tem ' . $resumo['total_alunos'] . ' alunos. Escolha uma turma com até '
                . self::MAX_ALUNOS . ' alunos para gerar ' . $nome . '.'
            );
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{arquivo:string,documento:string,andamento:string}
     */
    public function executarJob(array $payload): array
    {
        require_once __DIR__ . '/FechamentoService.php';
        $jobId = (int) ($payload['_job_id'] ?? 0);
        $documento = (string) ($payload['documento'] ?? '');
        $anoLetivo = (int) ($payload['ano_letivo'] ?? 0);
        $periodoTipo = (string) ($payload['periodo_tipo'] ?? 'ano');
        $periodoNumero = (int) ($payload['periodo_numero'] ?? 0);
        $turmaId = (int) ($payload['turma_id'] ?? 0);
        $serie = trim((string) ($payload['serie'] ?? ''));
        $usuarioId = (int) ($payload['user_id'] ?? 0);
        $this->rotuloJob = trim((string) ($payload['turma_nome'] ?? ''));
        $slug = $this->slugTenant((string) ($payload['tenant_slug'] ?? ''));
        if ($jobId <= 0 || $anoLetivo <= 0 || !isset(self::DOCUMENTOS[$documento])) {
            throw new RuntimeException('Lote de impressão incompleto.');
        }
        if (!$this->escopoInformado($turmaId, $serie)) {
            throw new RuntimeException('Escolha uma turma ou uma série antes de gerar.');
        }
        $this->gravarAndamento($jobId, $documento, 'Lendo a turma…');

        $paineis = (new FechamentoService())->painel($anoLetivo, $periodoTipo, $periodoNumero, $turmaId, $serie);
        $resumo = $this->resumo($paineis);
        if (!$resumo['pode']) {
            throw new RuntimeException('A impressão em lote só fica disponível quando todas as turmas deste filtro estão homologadas.');
        }
        $chave = self::chavePdf($anoLetivo, $periodoTipo, $periodoNumero, $turmaId, $serie, $documento);
        $this->gravarAndamento($jobId, $documento, 'Montando documentos…');
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }
        $html = $this->htmlParaImpressao(
            $documento,
            $paineis,
            $anoLetivo,
            $periodoTipo,
            $periodoNumero,
            $usuarioId,
            null,
            '',
            $jobId
        );
        $this->gravarAndamento($jobId, $documento, 'Gerando o PDF…');
        $orientacao = in_array($documento, ['relatorio', 'resultado', 'boletim'], true) ? 'landscape' : 'portrait';
        $pdf = $this->pdfDeHtml($html, $orientacao);
        unset($html);
        if ($pdf === '') {
            throw new RuntimeException('O PDF saiu vazio.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'fechpdf');
        if ($tmp === false || file_put_contents($tmp, $pdf) === false) {
            throw new RuntimeException('Não foi possível gravar o PDF temporário.');
        }
        try {
            $media = $this->media($slug);
            if (!$media->put(self::TIPO_ARQUIVO, $chave, $tmp, 'application/pdf')) {
                throw new RuntimeException('Não foi possível salvar o PDF no armazenamento da escola.');
            }
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }

        return [
            'arquivo' => $chave,
            'arquivo_key' => $chave,
            'documento' => $documento,
            'andamento' => 'PDF salvo',
            'rotulo' => $this->rotuloJob,
            'tenant_slug' => $slug,
        ];
    }

    public static function chavePdf(
        int $ano,
        string $periodoTipo,
        int $periodoNumero,
        int $turmaId,
        string $serie,
        string $documento
    ): string {
        $periodoTipo = strtolower(preg_replace('/[^a-z]/i', '', $periodoTipo) ?: 'ano');
        $documento = strtolower(preg_replace('/[^a-z_]/i', '', $documento) ?: 'doc');
        if ($turmaId > 0) {
            $escopo = 'turma-' . $turmaId;
        } else {
            $slugSerie = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $serie));
            $slugSerie = trim($slugSerie, '-');
            $escopo = 'serie-' . ($slugSerie !== '' ? $slugSerie : 'x') . '-' . substr(md5($serie), 0, 8);
        }

        return $ano . '/' . $periodoTipo . '-' . $periodoNumero . '/' . $escopo . '/' . $documento . '.pdf';
    }

    public function pdfSalvo(string $chave, string $slug): bool
    {
        if (!$this->chaveValida($chave)) {
            return false;
        }

        return $this->media($slug)->exists(self::TIPO_ARQUIVO, $chave);
    }

    public function lerPdf(string $chave, string $slug): ?string
    {
        if (!$this->chaveValida($chave)) {
            return null;
        }

        return $this->media($slug)->getContents(self::TIPO_ARQUIVO, $chave);
    }

    public function chaveValida(string $chave): bool
    {
        return preg_match('#^\d{4}/[a-z]+-\d+/(turma-\d+|serie-[a-z0-9-]+)/[a-z_]+\.pdf$#', $chave) === 1;
    }

    public static function caminhoArquivo(int $jobId, string $slug): ?string
    {
        if ($jobId <= 0) {
            return null;
        }
        $dir = self::diretorio($slug);
        $path = $dir . '/lote_' . $jobId . '.html';
        $realDir = realpath($dir);
        $realFile = realpath($path);
        if ($realDir === false || $realFile === false || !str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realFile;
    }

    public static function diretorio(string $slug): string
    {
        $limpo = preg_replace('/[^a-z0-9_-]/i', '', $slug);
        if (!is_string($limpo) || $limpo === '') {
            throw new RuntimeException('Slug da escola ausente; não é possível gravar a impressão.');
        }
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__, 4);
        return rtrim($base, '/\\') . '/storage/exports/' . $limpo . '/fechamento';
    }

    /**
     * @param list<array<string,mixed>> $paineis
     * @param array<string,mixed>|null $configApp
     */
    public function htmlParaImpressao(
        string $documento,
        array $paineis,
        int $anoLetivo,
        string $periodoTipo,
        int $periodoNumero,
        int $usuarioId,
        ?array $configApp,
        string $voltarUrl,
        int $jobId = 0
    ): string {
        if (!isset(self::DOCUMENTOS[$documento])) {
            throw new InvalidArgumentException('Documento de impressão desconhecido.');
        }
        $resumo = $this->resumo($paineis);
        if (!$resumo['pode']) {
            throw new RuntimeException('A impressão em lote só fica disponível quando todas as turmas deste filtro estão homologadas.');
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        $this->exigirEscopoLeve($paineis, $documento);
        $avisos = [];
        $htmls = [];
        $feitos = 0;
        $totalFolhas = $this->estimarFolhas($paineis, $documento);
        $orientacao = in_array($documento, ['relatorio', 'resultado', 'boletim'], true) ? 'landscape' : 'portrait';

        foreach ($paineis as $painel) {
            $turmaId = (int) ($painel['turma']['id'] ?? 0);
            $turmaNome = trim((string) ($painel['turma']['nome'] ?? 'Turma'));
            if ($turmaId <= 0) {
                continue;
            }
            $this->pulso($jobId, $feitos, $totalFolhas, $documento);
            if ($documento === 'relatorio' || $documento === 'resultado') {
                $emitido = $documento === 'resultado'
                    ? $this->documentos->emitirAta(
                        $turmaId,
                        $anoLetivo,
                        $periodoTipo,
                        $periodoNumero,
                        $usuarioId > 0 ? $usuarioId : null,
                        $configApp,
                        false
                    )
                    : $this->documentos->emitirRelatorio(
                        'relatorio_fechamento',
                        $turmaId,
                        $anoLetivo,
                        $periodoTipo,
                        $periodoNumero,
                        $usuarioId > 0 ? $usuarioId : null,
                        $configApp,
                        [],
                        false
                    );
                $htmls[] = (string) ($emitido['html'] ?? '');
                $feitos++;
                if (($emitido['orientacao'] ?? '') === 'landscape') {
                    $orientacao = 'landscape';
                }
                continue;
            }

            $preview = $this->documentos->homologacao()->previewTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
            foreach ($preview['linhas'] ?? [] as $linha) {
                $alunoId = (int) ($linha['aluno']['id'] ?? 0);
                $alunoNome = trim((string) ($linha['aluno']['nome'] ?? 'Aluno'));
                if ($alunoId <= 0) {
                    continue;
                }
                if ($documento === 'ficha' || $documento === 'boletim') {
                    try {
                        $emitido = $documento === 'boletim'
                            ? $this->documentos->emitirBoletim(
                                $alunoId,
                                $turmaId,
                                $anoLetivo,
                                $periodoTipo,
                                $periodoNumero,
                                $usuarioId > 0 ? $usuarioId : null,
                                $configApp,
                                false
                            )
                            : $this->documentos->emitirFicha(
                                $alunoId,
                                $turmaId,
                                $anoLetivo,
                                $periodoTipo,
                                $periodoNumero,
                                $usuarioId > 0 ? $usuarioId : null,
                                $configApp,
                                false
                            );
                        $htmls[] = (string) ($emitido['html'] ?? '');
                        $feitos++;
                        $this->pulso($jobId, $feitos, $totalFolhas, $documento);
                        if (($emitido['orientacao'] ?? '') === 'landscape') {
                            $orientacao = 'landscape';
                        }
                    } catch (Throwable $e) {
                        $avisos[] = $turmaNome . ' — ' . $alunoNome . ': ' . $e->getMessage();
                    }
                    continue;
                }
                $historico = $this->htmlHistoricoAluno($alunoId, $alunoNome, $turmaNome, $configApp, $avisos);
                if ($historico !== null) {
                    $htmls[] = $historico;
                    $feitos++;
                    $this->pulso($jobId, $feitos, $totalFolhas, $documento);
                }
            }
        }

        $htmls = array_values(array_filter($htmls, static fn ($html) => trim($html) !== ''));
        if ($htmls === []) {
            $detalhe = $avisos !== [] ? ' ' . implode(' ', $avisos) : '';
            throw new RuntimeException('Nenhum documento para imprimir neste filtro.' . $detalhe);
        }

        return $this->juntarParaImpressao(
            self::DOCUMENTOS[$documento],
            $htmls,
            $orientacao,
            $avisos,
            $voltarUrl
        );
    }

    /**
     * Cria rascunho de conclusão só para aluno que ainda não tem histórico.
     *
     * @param list<array<string,mixed>> $paineis
     * @return array{criados:int,existentes:int,falhas:list<string>}
     */
    public function prepararHistoricos(array $paineis, int $anoLetivo, string $periodoTipo, int $periodoNumero, int $usuarioId): array
    {
        $criados = 0;
        $existentes = 0;
        $falhas = [];
        foreach ($paineis as $painel) {
            if (($painel['status'] ?? '') !== FechamentoMaquinaEstados::HOMOLOGADO) {
                continue;
            }
            $turmaId = (int) ($painel['turma']['id'] ?? 0);
            $turmaNome = trim((string) ($painel['turma']['nome'] ?? 'Turma'));
            if ($turmaId <= 0) {
                continue;
            }
            $preview = $this->documentos->homologacao()->previewTurma(
                $turmaId,
                $anoLetivo,
                $periodoTipo,
                $periodoNumero
            );
            foreach ($preview['linhas'] ?? [] as $linha) {
                $alunoId = (int) ($linha['aluno']['id'] ?? 0);
                $alunoNome = trim((string) ($linha['aluno']['nome'] ?? 'Aluno'));
                if ($alunoId <= 0) {
                    continue;
                }
                $docs = $this->historicos->listarPorAluno($alunoId);
                if ($this->historicoOficial($docs) !== null || $this->historicoRascunho($docs) !== null) {
                    $existentes++;
                    continue;
                }
                $gerado = $this->historicos->gerarRascunho($alunoId, 'Conclusao', $usuarioId > 0 ? $usuarioId : null);
                if (!empty($gerado['success'])) {
                    $criados++;
                    continue;
                }
                $falhas[] = $turmaNome . ' — ' . $alunoNome . ': ' . (string) ($gerado['error'] ?? 'falha ao gerar rascunho');
            }
        }

        return [
            'criados' => $criados,
            'existentes' => $existentes,
            'falhas' => $falhas,
        ];
    }

    /**
     * @param list<string> $avisos
     * @param array<string,mixed>|null $configApp
     */
    private function htmlHistoricoAluno(
        int $alunoId,
        string $alunoNome,
        string $turmaNome,
        ?array $configApp,
        array &$avisos
    ): ?string {
        $docs = $this->historicos->listarPorAluno($alunoId);
        $doc = $this->historicoOficial($docs);
        $rascunho = false;
        if ($doc === null) {
            $doc = $this->historicoRascunho($docs);
            $rascunho = $doc !== null;
        }
        if ($doc === null) {
            $avisos[] = $turmaNome . ' — ' . $alunoNome . ': ainda não há histórico. Prepare os rascunhos nesta tela antes de imprimir.';
            return null;
        }
        $dados = $this->historicos->dadosParaPdf((int) ($doc['id'] ?? 0));
        if (!$dados) {
            $avisos[] = $turmaNome . ' — ' . $alunoNome . ': não foi possível montar o histórico.';
            return null;
        }
        $dados['resultado_labels'] = HistoricoEscolarService::RESULTADO_LABELS;
        try {
            $html = $this->pdfHistorico->htmlHistorico($dados, $configApp);
        } catch (Throwable $e) {
            $avisos[] = $turmaNome . ' — ' . $alunoNome . ': ' . $e->getMessage();
            return null;
        }
        if ($rascunho) {
            $avisos[] = $turmaNome . ' — ' . $alunoNome . ': histórico ainda não emitido; saiu como rascunho.';
            $html = $this->marcarRascunho($html);
        }

        return $html;
    }

    /**
     * @param list<array<string,mixed>> $docs
     * @return array<string,mixed>|null
     */
    private function historicoOficial(array $docs): ?array
    {
        foreach ($docs as $doc) {
            if (in_array((string) ($doc['status'] ?? ''), ['Assinado', 'Emitido', 'Entregue'], true)) {
                return $doc;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $docs
     * @return array<string,mixed>|null
     */
    private function historicoRascunho(array $docs): ?array
    {
        foreach ($docs as $doc) {
            if (in_array((string) ($doc['status'] ?? ''), ['Conferido', 'Rascunho'], true)) {
                return $doc;
            }
        }

        return null;
    }

    private function marcarRascunho(string $html): string
    {
        $faixa = '<div style="border:2px dashed #b45309;background:#fffbeb;color:#92400e;padding:8px 12px;margin-bottom:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;text-align:center">Rascunho — histórico ainda não emitido</div>';
        if (preg_match('/<body[^>]*>/i', $html)) {
            return (string) preg_replace('/(<body[^>]*>)/i', '$1' . $faixa, $html, 1);
        }

        return $faixa . $html;
    }

    /**
     * @param list<string> $htmls
     * @param list<string> $avisos
     */
    private function juntarParaImpressao(string $titulo, array $htmls, string $orientacao, array $avisos, string $voltarUrl): string
    {
        $css = '';
        $corpos = [];
        foreach ($htmls as $html) {
            if ($css === '' && preg_match('/<style[^>]*>(.*?)<\/style>/is', $html, $estilo)) {
                $css = (string) $estilo[1];
            }
            if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $corpo)) {
                $corpos[] = (string) $corpo[1];
            } else {
                $corpos[] = $html;
            }
        }
        $ultimo = count($corpos) - 1;
        $partes = [];
        foreach ($corpos as $i => $corpo) {
            $quebra = $i < $ultimo ? 'page-break-after:always;break-after:page;' : '';
            $partes[] = '<section class="lote-folha" style="' . $quebra . '">' . $corpo . '</section>';
        }
        $size = $orientacao === 'landscape' ? 'A4 landscape' : 'A4 portrait';
        $avisosHtml = '';
        if ($avisos !== []) {
            $itens = '';
            foreach ($avisos as $aviso) {
                $itens .= '<li>' . htmlspecialchars($aviso, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $avisosHtml = '<div class="lote-avisos"><strong>Antes de imprimir</strong><ul>' . $itens . '</ul></div>';
        }
        $tituloEsc = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
        $voltarEsc = htmlspecialchars($voltarUrl, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>' . $tituloEsc . '</title><style>'
            . $css
            . '@page { size: ' . $size . '; }'
            . '.lote-barra{position:sticky;top:0;z-index:5;display:flex;justify-content:space-between;gap:12px;align-items:center;padding:12px 16px;background:#0f172a;color:#fff;font-family:sans-serif}'
            . '.lote-barra a,.lote-barra button{font:inherit;border:0;border-radius:8px;padding:8px 14px;cursor:pointer;text-decoration:none}'
            . '.lote-barra a{background:transparent;color:#fff}'
            . '.lote-barra button{background:#fff;color:#0f172a;font-weight:700}'
            . '.lote-avisos{margin:16px;padding:12px 16px;border:1px solid #fcd34d;background:#fffbeb;color:#92400e;font-family:sans-serif;font-size:14px}'
            . '.lote-avisos ul{margin:8px 0 0;padding-left:18px}'
            . '@media print{.lote-barra,.lote-avisos{display:none!important}}'
            . '</style></head><body>'
            . '<div class="lote-barra"><a href="' . $voltarEsc . '">Voltar ao fechamento</a>'
            . '<strong>' . $tituloEsc . '</strong>'
            . '<button type="button" onclick="window.print()">Imprimir</button></div>'
            . $avisosHtml
            . implode('', $partes)
            . '</body></html>';
    }

    /**
     * @param list<array<string,mixed>> $paineis
     */
    private function estimarFolhas(array $paineis, string $documento): int
    {
        if ($documento === 'resultado' || $documento === 'relatorio') {
            return count($paineis);
        }
        $total = 0;
        foreach ($paineis as $painel) {
            $total += (int) ($painel['resumo']['total'] ?? 0);
        }

        return $total;
    }

    private function pulso(int $jobId, int $feitos, int $total, string $documento): void
    {
        if ($jobId <= 0) {
            return;
        }
        require_once __DIR__ . '/../../../Services/AIJobService.php';
        \App\Services\AIJobService::renovarHeartbeat($jobId);
        $nome = self::DOCUMENTOS[$documento] ?? 'Documento';
        $this->gravarAndamento($jobId, $documento, $nome . ': ' . $feitos . ' de ' . max($total, $feitos));
    }

    private function gravarAndamento(int $jobId, string $documento, string $andamento): void
    {
        if ($jobId <= 0) {
            return;
        }
        $db = Database::getInstance();
        if (!$db->tableExists('ai_jobs')) {
            return;
        }
        try {
            $db->query(
                'UPDATE ai_jobs SET result = :result WHERE id = :id AND status = :status',
                [
                    'result' => json_encode([
                        'documento' => $documento,
                        'rotulo' => $this->rotuloJob,
                        'andamento' => $andamento,
                        'iniciado_em' => date('Y-m-d H:i:s'),
                    ], JSON_UNESCAPED_UNICODE),
                    'id' => $jobId,
                    'status' => 'processing',
                ]
            );
        } catch (Throwable $e) {
            error_log('ImpressaoLoteFechamento andamento job=' . $jobId . ': ' . $e->getMessage());
        }
    }

    private function pdfDeHtml(string $html, string $orientacao): string
    {
        $autoload = dirname(__DIR__, 4) . '/vendor/autoload.php';
        if (!class_exists(\Dompdf\Dompdf::class) && is_file($autoload)) {
            require_once $autoload;
        }
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientacao === 'landscape' ? 'landscape' : 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function media(string $slug): \MediaStorageService
    {
        require_once __DIR__ . '/../../../Services/MediaStorageService.php';
        $config = $this->configApp();
        $config['tenant']['slug'] = $slug;

        return new \MediaStorageService($config);
    }

    /**
     * @return array<string,mixed>
     */
    private function configApp(): array
    {
        static $carregado = null;
        if (is_array($carregado)) {
            return $carregado;
        }
        $path = dirname(__DIR__, 4) . '/config/app.php';
        if (!is_file($path)) {
            return $carregado = [];
        }
        $loaded = require $path;
        if (!is_array($loaded) && function_exists('env')) {
            $loaded = [
                'media' => [
                    'storage' => env('MEDIA_STORAGE', 'local'),
                    'local_base' => dirname($path),
                    'files_base' => 'storage/files',
                    'tenant_prefix' => env('MEDIA_TENANT_PREFIX', '') === 'true',
                ],
                'aws' => [
                    'bucket' => env('AWS_BUCKET', ''),
                    'key' => env('AWS_ACCESS_KEY', ''),
                    'secret' => env('AWS_SECRET_KEY', ''),
                    'region' => env('AWS_REGION', 'us-east-1'),
                ],
                'tenant' => [
                    'slug' => defined('TENANT_SLUG') ? (string) TENANT_SLUG : '',
                ],
            ];
        }

        return $carregado = is_array($loaded) ? $loaded : [];
    }

    private function slugTenant(string $slug): string
    {
        $limpo = preg_replace('/[^a-z0-9_-]/i', '', $slug);
        if (is_string($limpo) && $limpo !== '') {
            return $limpo;
        }
        if (defined('TENANT_SLUG')) {
            $constante = preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_SLUG);
            if (is_string($constante) && $constante !== '') {
                return $constante;
            }
        }
        throw new RuntimeException('Slug da escola ausente; não é possível gravar a impressão.');
    }
}
