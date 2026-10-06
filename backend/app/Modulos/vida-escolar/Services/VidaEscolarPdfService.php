<?php

namespace App\Modulos\VidaEscolar\Services;

require_once __DIR__ . '/../../../Modulos/modelos-documentos/Services/ModeloDocumentoService.php';
require_once __DIR__ . '/../../../Modulos/modelos-documentos/Services/GeradorPdfFolhaService.php';

use App\Modulos\ModelosDocumentos\Services\GeradorPdfFolhaService;
use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;
use Database;

/**
 * Emite PDFs da Vida Escolar no papel timbrado (Layout de documentos).
 */
class VidaEscolarPdfService
{
    public const CODIGO_BOLETIM = 'vida_escolar_boletim';
    public const CODIGO_DOSSIE = 'vida_escolar_dossie';
    public const CODIGO_PACOTE = 'vida_escolar_pacote';
    public const CODIGO_SED = 'vida_escolar_sed';
    public const CODIGO_HISTORICO = 'vida_escolar_historico';
    public const CODIGO_OFICIO = 'vida_escolar_oficio';

    private ModeloDocumentoService $modelos;
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->modelos = new ModeloDocumentoService($this->db);
    }

    /**
     * @param array<string,mixed> $prontuario
     * @param array<string,mixed>|null $config
     */
    public function emitirBoletim(array $prontuario, array $periodos, ?array $config, string $filename): void
    {
        $this->emitir(self::CODIGO_BOLETIM, 'Boletim Escolar', $prontuario, $periodos, $config, $filename);
    }

    /**
     * Um PDF com um boletim da Vida Escolar por aluno (quebra de página).
     *
     * @param list<array<string,mixed>> $prontuarios
     * @param array<int,string> $periodos
     * @param array<string,mixed>|null $config
     */
    public function emitirBoletinsLote(array $prontuarios, array $periodos, ?array $config, string $filename): void
    {
        if ($prontuarios === []) {
            throw new \RuntimeException('Nenhum boletim da Vida Escolar para emitir.');
        }
        if (count($prontuarios) > 80) {
            throw new \RuntimeException('O lote tem mais de 80 boletins. Filtre por turma e tente de novo.');
        }
        @set_time_limit(180);
        $this->garantirModelos();
        $modelo = $this->modelos->findByCodigo(self::CODIGO_BOLETIM);
        if (!$modelo) {
            throw new \RuntimeException('Modelo vida_escolar_boletim indisponível. Cadastre-o em Layout de documentos.');
        }
        $css = '';
        $corpos = [];
        foreach ($prontuarios as $prontuario) {
            $out = $this->htmlProntuario(self::CODIGO_BOLETIM, 'Boletim Escolar', $prontuario, $periodos, $config);
            if ($css === '') {
                $css = $this->extrairCssHtml($out['html']);
            }
            $corpos[] = $this->extrairCorpoHtml($out['html']);
        }
        $ultimo = count($corpos) - 1;
        $partes = [];
        foreach ($corpos as $i => $corpo) {
            $quebra = $i < $ultimo ? 'page-break-after:always;break-after:page;' : '';
            $partes[] = '<div class="boletim-lote" style="' . $quebra . '">' . $corpo . '</div>';
        }
        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><style>'
            . $css
            . "\n.page-break{page-break-after:always;break-after:page;}"
            . '</style></head><body>'
            . implode('', $partes)
            . '</body></html>';
        $this->enviarPdf($html, $filename, $modelo);
    }

    private function extrairCssHtml(string $html): string
    {
        if (preg_match('/<style[^>]*>(.*?)<\/style>/is', $html, $m)) {
            return (string) $m[1];
        }
        return '';
    }

    private function extrairCorpoHtml(string $html): string
    {
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
            return (string) $m[1];
        }
        return $html;
    }

    /**
     * @param array<string,mixed> $prontuario
     * @param array<string,mixed>|null $config
     */
    public function emitirDossie(array $prontuario, array $periodos, ?array $config, string $filename): void
    {
        $this->emitir(self::CODIGO_DOSSIE, 'Dossiê do aluno', $prontuario, $periodos, $config, $filename);
    }

    /**
     * @param array<string,mixed> $prontuario
     * @param array<string,mixed>|null $config
     */
    public function emitirPacote(array $prontuario, array $periodos, ?array $config, string $filename): void
    {
        $this->emitir(self::CODIGO_PACOTE, 'Pacote de transferência', $prontuario, $periodos, $config, $filename);
    }

    /**
     * @param array<string,mixed> $prontuario
     * @param array<string,mixed>|null $config
     */
    public function emitirSed(array $prontuario, array $periodos, ?array $config, string $filename): void
    {
        $this->emitir(self::CODIGO_SED, 'Planilha SED', $prontuario, $periodos, $config, $filename);
    }

    /**
     * @param array<string,mixed> $oficio
     * @param array<string,mixed>|null $config
     */
    public function emitirOficio(array $oficio, ?array $config, string $filename): void
    {
        $this->garantirModelos();
        $modelo = $this->modelos->findByCodigo(self::CODIGO_OFICIO);
        if (!$modelo) {
            throw new \RuntimeException('Modelo vida_escolar_oficio indisponível. Cadastre-o em Layout de documentos.');
        }
        $html = $this->modelos->renderHtml($modelo, $this->varsDoOficio($oficio), 'auto', $config);
        $this->enviarPdf($html, $filename, $modelo, (int) ($oficio['aluno_id'] ?? 0), 'Ofício', $config);
    }

    /**
     * @param array<string,mixed> $dadosPdf
     * @param array<string,mixed>|null $config
     */
    public function emitirHistorico(array $dadosPdf, ?array $config, string $filename): void
    {
        $html = $this->htmlHistorico($dadosPdf, $config);
        $modelo = $this->modelos->findByCodigo(self::CODIGO_HISTORICO);
        if (!$modelo) {
            throw new \RuntimeException('Modelo vida_escolar_historico indisponível. Cadastre-o em Layout de documentos.');
        }
        $aluno = is_array($dadosPdf['aluno'] ?? null) ? $dadosPdf['aluno'] : [];
        $doc = is_array($dadosPdf['documento'] ?? null) ? $dadosPdf['documento'] : [];
        $titulo = (string) ($doc['finalidade'] ?? '') === 'Transferencia'
            ? 'Histórico para transferência'
            : 'Histórico escolar';
        $alunoId = (int) ($aluno['id'] ?? $doc['aluno_id'] ?? 0);
        $this->enviarPdf($html, $filename, $modelo, $alunoId, $titulo, $config);
    }

    /**
     * HTML do histórico, sem enviar o PDF. Usado na impressão em lote.
     *
     * @param array<string,mixed> $dadosPdf
     * @param array<string,mixed>|null $config
     */
    public function htmlHistorico(array $dadosPdf, ?array $config): string
    {
        $this->garantirModelos();
        $modelo = $this->modeloHistorico($dadosPdf);
        if (!$modelo) {
            throw new \RuntimeException('Nenhum layout de histórico disponível. Vincule um em Layout de documentos.');
        }
        $aluno = is_array($dadosPdf['aluno'] ?? null) ? $dadosPdf['aluno'] : [];
        $unidade = is_array($dadosPdf['unidade'] ?? null) ? $dadosPdf['unidade'] : [];
        $doc = is_array($dadosPdf['documento'] ?? null) ? $dadosPdf['documento'] : [];
        $finalidade = (string) ($doc['finalidade'] ?? '');
        $tituloDoc = $finalidade === 'Transferencia'
            ? 'Histórico Escolar para Transferência'
            : 'Histórico Escolar';
        $viewData = [
            'tipo' => 'historico',
            'titulo' => $tituloDoc,
            'dados' => [
                'aluno' => $aluno,
                'unidade' => $unidade,
                'matricula' => null,
            ],
            'numero' => (int) ($doc['versao'] ?? 1),
            'ano' => (int) date('Y'),
            'gerado_em' => date('d/m/Y'),
            'cidade_data' => $this->cidadeData($unidade),
        ];
        $vars = $this->modelos->varsFromDeclaracao($viewData);
        $filiacao = trim((string) ($aluno['nome_mae'] ?? '') . ' / ' . (string) ($aluno['nome_pai'] ?? ''), ' /');
        if ($filiacao !== '') {
            $vars['resp_nome'] = htmlspecialchars($filiacao, ENT_QUOTES, 'UTF-8');
        }
        $serieTurma = trim((string) ($aluno['turma_serie'] ?? ''));
        if ($serieTurma !== '') {
            $vars['serie'] = htmlspecialchars($serieTurma, ENT_QUOTES, 'UTF-8');
        }
        $vars['historico_html'] = $this->historicoOficialHtml($dadosPdf);
        $vars['titulo'] = htmlspecialchars($tituloDoc, ENT_QUOTES, 'UTF-8');
        $vars['observacoes'] = htmlspecialchars(
            (string) ($dadosPdf['observacoes_gerais'] ?? $doc['observacoes_gerais'] ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );

        $html = $this->modelos->renderHtml($modelo, $vars, ModeloDocumentoService::estiloDoModelo($modelo), $config);
        if ($finalidade === 'Transferencia') {
            $html = str_replace(
                '<h1 class="doc-title">Histórico Escolar</h1>',
                '<h1 class="doc-title">Histórico Escolar para Transferência</h1>',
                $html
            );
        }

        return $html;
    }

    /**
     * @param array<string,mixed> $modelo
     */
    public function gerarPdfBinario(string $html, array $modelo): string
    {
        $autoload = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4)) . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $chroot = defined('BASE_PATH') ? (BASE_PATH . '/storage') : null;
        if (is_string($chroot) && is_dir($chroot)) {
            $options->setChroot($chroot);
        }
        $pdfFolha = (new GeradorPdfFolhaService())->gerarSeFolhaOficial($html);
        if (is_string($pdfFolha)) {
            return $pdfFolha;
        }
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $this->modelos->aplicarPapelDompdf($dompdf, $modelo);
        $dompdf->render();
        return (string) $dompdf->output();
    }

    /**
     * @param array<string,mixed> $prontuario
     * @param array<int,string> $periodos
     * @param array<string,mixed>|null $config
     * @return array{html:string,modelo:array<string,mixed>}
     */
    public function htmlProntuario(
        string $codigo,
        string $titulo,
        array $prontuario,
        array $periodos,
        ?array $config
    ): array {
        $this->garantirModelos();
        $modelo = $this->modelos->findByCodigo($codigo);
        if (!$modelo) {
            throw new \RuntimeException('Modelo ' . $codigo . ' indisponível. Cadastre-o em Layout de documentos.');
        }
        return [
            'html' => $this->modelos->renderHtml(
                $modelo,
                $this->varsDoProntuario($prontuario, $periodos, $titulo),
                ModeloDocumentoService::estiloDoModelo($modelo),
                $config
            ),
            'modelo' => $modelo,
        ];
    }

    /**
     * @param array<string,mixed> $prontuario
     * @param array<int,string> $periodos
     * @param array<string,mixed>|null $config
     */
    private function emitir(
        string $codigo,
        string $titulo,
        array $prontuario,
        array $periodos,
        ?array $config,
        string $filename
    ): void {
        $out = $this->htmlProntuario($codigo, $titulo, $prontuario, $periodos, $config);
        $aluno = is_array($prontuario['aluno'] ?? null) ? $prontuario['aluno'] : [];
        $this->enviarPdf($out['html'], $filename, $out['modelo'], (int) ($aluno['id'] ?? 0), $titulo, $config);
    }

    /**
     * @param array<string,mixed> $prontuario
     * @param array<int,string> $periodos
     * @return array<string,string>
     */
    private function varsDoProntuario(array $prontuario, array $periodos, string $titulo): array
    {
        $aluno = is_array($prontuario['aluno'] ?? null) ? $prontuario['aluno'] : [];
        $unidade = is_array($prontuario['unidade'] ?? null) ? $prontuario['unidade'] : [];
        $matricula = is_array($prontuario['matricula'] ?? null) ? $prontuario['matricula'] : [];
        $capa = is_array($prontuario['capa'] ?? null) ? $prontuario['capa'] : [];
        $quadro = is_array($prontuario['quadro'] ?? null) ? $prontuario['quadro'] : [];
        $ficha = is_array($quadro['ficha'] ?? null) ? $quadro['ficha'] : [];
        $planilha = is_array($prontuario['planilha_sed'] ?? null) ? $prontuario['planilha_sed'] : [];
        $viewData = [
            'tipo' => 'transferencia',
            'titulo' => $titulo,
            'dados' => [
                'aluno' => $aluno,
                'unidade' => $unidade,
                'matricula' => $matricula,
            ],
            'numero' => 0,
            'ano' => (int) ($ficha['ano_letivo'] ?? date('Y')),
            'gerado_em' => date('d/m/Y'),
            'cidade_data' => $this->cidadeData($unidade),
        ];
        $vars = $this->modelos->varsFromDeclaracao($viewData);
        $vars['titulo'] = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
        $vars['doc_rotulo'] = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
        $vars['situacao_matricula'] = htmlspecialchars((string) ($capa['situacao'] ?? $vars['situacao_matricula'] ?? ''), ENT_QUOTES, 'UTF-8');
        $vars['situacao_final'] = $vars['situacao_matricula'];
        $freqRaw = $ficha['frequencia_percentual'] ?? $capa['frequencia_percentual'] ?? '';
        if ($freqRaw !== '' && $freqRaw !== null && is_numeric($freqRaw)) {
            $vars['frequencia_percentual'] = htmlspecialchars(
                number_format((float) $freqRaw, 1, ',', '.') . '%',
                ENT_QUOTES,
                'UTF-8'
            );
        } elseif (trim((string) ($vars['frequencia_percentual'] ?? '')) === '') {
            $vars['frequencia_percentual'] = '—';
        }
        $vars['identidade_html'] = $this->tabelaChaveValor($planilha);
        $vars['trajetoria_html'] = $this->trajetoriaHtml(is_array($prontuario['trajetoria']['anos'] ?? null) ? $prontuario['trajetoria']['anos'] : []);
        $vars['quadro_notas_html'] = $this->quadroHtml($quadro, $periodos);
        $vars['documentos_html'] = $this->checklistHtml(is_array($prontuario['docs_checklist']['itens'] ?? null) ? $prontuario['docs_checklist']['itens'] : []);
        $vars['sed_html'] = $this->sedHtml(
            is_array($prontuario['sed']['itens'] ?? null) ? $prontuario['sed']['itens'] : [],
            is_array($prontuario['inep'] ?? null) ? $prontuario['inep'] : []
        );
        $vars['tabela_html'] = $vars['identidade_html'];
        $vars['historico_html'] = $vars['trajetoria_html'];
        $obsCoord = trim((string) ($prontuario['boletim_observacao'] ?? $prontuario['observacao_boletim'] ?? ''));
        if ($obsCoord === '' && is_array($prontuario['boletim_observacao'] ?? null)) {
            $obsCoord = trim((string) ($prontuario['boletim_observacao']['conteudo'] ?? ''));
        }
        if ($obsCoord === '') {
            $alunoIdObs = (int) ($aluno['id'] ?? 0);
            if ($alunoIdObs > 0) {
                try {
                    if (!class_exists('BoletimConfig', false)) {
                        require_once dirname(__DIR__, 3) . '/Models/System/BoletimConfig.php';
                    }
                    $rowObs = (new \BoletimConfig())->getObservacaoCoordenacao($alunoIdObs);
                    $obsCoord = is_array($rowObs) ? trim((string) ($rowObs['conteudo'] ?? '')) : '';
                } catch (\Throwable $e) {
                    $obsCoord = '';
                }
            }
        }
        $vars['observacoes'] = $obsCoord !== ''
            ? htmlspecialchars($obsCoord, ENT_QUOTES, 'UTF-8')
            : htmlspecialchars(
                'Ficha do ano: ' . (string) ($capa['status_ficha_label'] ?? $ficha['status'] ?? 'sem ficha')
                . ' · Documentos: ' . (string) ($capa['docs_txt'] ?? '—')
                . ' · SED: ' . (string) ($capa['sed_txt'] ?? '—'),
                ENT_QUOTES,
                'UTF-8'
            );
        return $vars;
    }

    /**
     * @param list<array{campo?:string,valor?:string}> $linhas
     */
    private function tabelaChaveValor(array $linhas): string
    {
        if ($linhas === []) {
            return '<p>Sem dados de identidade.</p>';
        }
        $html = '<table class="dados">';
        foreach ($linhas as $linha) {
            $html .= '<tr><td class="label">' . $this->esc($linha['campo'] ?? '') . '</td><td>'
                . $this->esc($linha['valor'] ?? '—') . '</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * @param list<array<string,mixed>> $anos
     */
    private function trajetoriaHtml(array $anos): string
    {
        $html = '<table class="dados"><tr><td class="label">Ano</td><td class="label">Série</td>'
            . '<td class="label">Escola</td><td class="label">Origem</td><td class="label">Resultado</td></tr>';
        if ($anos === []) {
            return $html . '<tr><td colspan="5">Sem anos de escolarização registrados.</td></tr></table>';
        }
        foreach ($anos as $ano) {
            $html .= '<tr><td>' . $this->esc($ano['ano_letivo'] ?? '') . '</td>'
                . '<td>' . $this->esc($ano['serie_ano'] ?? '') . '</td>'
                . '<td>' . $this->esc($ano['escola_nome'] ?? '—') . '</td>'
                . '<td>' . $this->esc($ano['origem'] ?? '') . '</td>'
                . '<td>' . $this->esc($ano['resultado'] ?? '—') . '</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * @param array<string,mixed> $quadro
     * @param array<int,string> $periodos
     */
    private function quadroHtml(array $quadro, array $periodos): string
    {
        $grid = is_array($quadro['grid'] ?? null) ? $quadro['grid'] : [];
        $cols = [1, 2, 3, 4];
        $rotulos = [];
        foreach ($cols as $p) {
            $rotulos[] = (string) ($periodos[$p] ?? (string) $p);
        }
        $linhas = [];
        foreach ($grid as $row) {
            if (!is_array($row)) {
                continue;
            }
            $celulas = [];
            foreach ($cols as $p) {
                $c = is_array($row['celulas'][$p] ?? null) ? $row['celulas'][$p] : null;
                $celulas[] = [
                    'nota' => $this->fmtCelula($c),
                    'falta' => $this->fmtFalta($c),
                ];
            }
            $linhas[] = [
                'componente' => (string) ($row['linha']['componente_nome'] ?? ''),
                'celulas' => $celulas,
            ];
        }
        $html = ModeloDocumentoService::htmlQuadroNotas($rotulos, $linhas);
        if ($grid === []) {
            return $html;
        }
        return $html . '<p style="font-size:8pt;color:#4b5563;margin:4px 0 0;">¹ Resultado recebido da escola de origem.</p>';
    }

    /**
     * @param list<array<string,mixed>> $itens
     */
    private function checklistHtml(array $itens): string
    {
        $html = '<table class="dados"><tr><td class="label">Documento</td><td class="label">Status</td></tr>';
        if ($itens === []) {
            return $html . '<tr><td colspan="2">Nenhum item de checklist.</td></tr></table>';
        }
        foreach ($itens as $item) {
            $html .= '<tr><td>' . $this->esc($item['label'] ?? '') . '</td><td>'
                . $this->esc($item['status'] ?? '') . '</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * @param list<array<string,mixed>> $itens
     * @param array<string,mixed> $inep
     */
    private function sedHtml(array $itens, array $inep): string
    {
        $html = '<p>Educacenso: ' . $this->esc($inep['resumo'] ?? '—')
            . ' · INEP escola ' . $this->esc($inep['codigo_escola'] ?? '—')
            . ' · INEP aluno ' . $this->esc($inep['codigo_aluno'] ?? '—') . '</p>';
        $html .= '<table class="dados"><tr><td class="label">Campo</td><td class="label">Situação</td></tr>';
        if ($itens === []) {
            return $html . '<tr><td colspan="2">Sem conferência SED.</td></tr></table>';
        }
        foreach ($itens as $item) {
            $html .= '<tr><td>' . $this->esc($item['mensagem'] ?? '') . '</td><td>'
                . (!empty($item['ok']) ? 'Ok' : 'Falta') . '</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * Quadro no formato de histórico: componentes nas linhas, um ano/série por coluna,
     * e abaixo os estabelecimentos dos anos anteriores.
     *
     * @param array<string,mixed> $dados
     */
    private function historicoOficialHtml(array $dados): string
    {
        $itens = is_array($dados['itens'] ?? null) ? $dados['itens'] : [];
        $resultados = is_array($dados['resultados'] ?? null) ? $dados['resultados'] : [];
        $labels = is_array($dados['resultado_labels'] ?? null) ? $dados['resultado_labels'] : [];
        $unidade = is_array($dados['unidade'] ?? null) ? $dados['unidade'] : [];
        $escolaAtual = trim((string) ($unidade['razao_social'] ?? $unidade['nome'] ?? ''));
        $colunas = [];
        $componentes = [];
        $notas = [];
        $cargas = [];
        foreach ($itens as $it) {
            $ano = trim((string) ($it['ano_letivo'] ?? ''));
            $serie = $this->serieVisivel($dados, trim((string) ($it['serie_ano'] ?? '')));
            $chaveCol = $ano . '|' . $serie;
            if (!isset($colunas[$chaveCol])) {
                $colunas[$chaveCol] = ['ano' => $ano, 'serie' => $serie];
            }
            $comp = trim((string) ($it['componente'] ?? ''));
            if ($comp === '') {
                continue;
            }
            $chaveComp = function_exists('mb_strtolower') ? mb_strtolower($comp, 'UTF-8') : strtolower($comp);
            if (!isset($componentes[$chaveComp])) {
                $componentes[$chaveComp] = $comp;
            }
            $notas[$chaveComp][$chaveCol] = $this->notaHistorico($it['resultado_valor'] ?? '');
            $ch = $it['carga_horaria'] ?? null;
            if ($ch !== null && $ch !== '' && is_numeric($ch)) {
                $cargas[$chaveComp] = ($cargas[$chaveComp] ?? 0) + (int) $ch;
            }
        }
        uksort($colunas, static function (string $a, string $b): int {
            return strcmp($a, $b);
        });
        $resultMap = [];
        foreach ($resultados as $r) {
            $anoRes = trim((string) ($r['ano_letivo'] ?? ''));
            $serieRes = $this->serieVisivel($dados, trim((string) ($r['serie_ano'] ?? '')));
            $resultMap[$anoRes . '|' . $serieRes] = $r;
        }
        $temCarga = $cargas !== [];
        $html = '<style>'
            . '.hist-sec{font-size:10pt;font-weight:700;letter-spacing:.04em;color:#1e3a5f;margin:14px 0 6px;padding-bottom:2px;border-bottom:2px solid #1e3a5f;}'
            . '.hist-quadro{width:100%;border-collapse:collapse;margin:0 0 8px;font-size:9pt;}'
            . '.hist-quadro th{background:#1e3a5f;color:#fff;border:1px solid #1e3a5f;padding:6px 5px;text-align:center;font-size:8pt;font-weight:700;}'
            . '.hist-quadro td{border:1px solid #cbd5e1;padding:5px 6px;}'
            . '.hist-quadro td.comp{text-align:left;}'
            . '.hist-quadro td.num{text-align:center;}'
            . '.hist-quadro tr.resultado td{background:#f1f5f9;font-weight:700;}'
            . '</style>';
        if ($colunas !== [] && $componentes !== []) {
            $html .= '<p class="hist-sec">COMPONENTES CURRICULARES</p>';
            $html .= '<table class="hist-quadro"><tr><th class="comp">Componente</th>';
            foreach ($colunas as $col) {
                $html .= '<th>' . $this->esc($col['serie']) . '<br>' . $this->esc($col['ano']) . '</th>';
            }
            if ($temCarga) {
                $html .= '<th>Carga horária</th>';
            }
            $html .= '</tr>';
            foreach ($componentes as $chaveComp => $nome) {
                $html .= '<tr><td class="comp">' . $this->esc($nome) . '</td>';
                foreach (array_keys($colunas) as $chaveCol) {
                    $html .= '<td class="num">' . $this->esc($notas[$chaveComp][$chaveCol] ?? '—') . '</td>';
                }
                if ($temCarga) {
                    $chTotal = $cargas[$chaveComp] ?? null;
                    $html .= '<td class="num">' . $this->esc($chTotal !== null && $chTotal > 0 ? (string) $chTotal : '—') . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '<tr class="resultado"><td class="comp">Resultado</td>';
            foreach ($colunas as $chaveCol => $col) {
                $res = $resultMap[$chaveCol] ?? [];
                $resLabel = (string) ($labels[$res['resultado'] ?? ''] ?? ($res['resultado'] ?? '—'));
                $html .= '<td class="num">' . $this->esc($resLabel) . '</td>';
            }
            if ($temCarga) {
                $html .= '<td></td>';
            }
            $html .= '</tr></table>';
        } else {
            $html .= '<p>Sem componentes lançados neste histórico.</p>';
        }

        $estudos = is_array($dados['estudos'] ?? null) ? $dados['estudos'] : [];
        if ($estudos === []) {
            foreach ($colunas as $chaveCol => $col) {
                $escola = '';
                foreach ($itens as $it) {
                    if (((string) ($it['ano_letivo'] ?? '') . '|' . (string) ($it['serie_ano'] ?? '')) !== $chaveCol) {
                        continue;
                    }
                    $escola = trim((string) ($it['escola_origem'] ?? ''));
                    if ($escola !== '') {
                        break;
                    }
                }
                if ($escola === '') {
                    $escola = $escolaAtual;
                }
                $estudos[] = [
                    'ano_letivo' => $col['ano'],
                    'serie_ano' => $col['serie'],
                    'escola' => $escola,
                    'municipio' => '',
                    'uf' => '',
                ];
            }
        }
        $html .= '<p class="hist-sec">ESTUDOS REALIZADOS</p>';
        $html .= '<table class="hist-quadro"><tr>'
            . '<th>Série</th><th>Ano</th><th>Estabelecimento de ensino</th><th>Município</th><th>UF</th></tr>';
        if ($estudos === []) {
            $html .= '<tr><td class="comp" colspan="5">Nenhum ano de escolarização informado.</td></tr>';
        }
        foreach ($estudos as $estudo) {
            if (!is_array($estudo)) {
                continue;
            }
            $html .= '<tr><td class="comp">' . $this->esc($this->serieVisivel($dados, (string) ($estudo['serie_ano'] ?? ''))) . '</td>'
                . '<td class="num">' . $this->esc($estudo['ano_letivo'] ?? '') . '</td>'
                . '<td class="comp">' . $this->esc($estudo['escola'] ?? '') . '</td>'
                . '<td class="comp">' . $this->esc($estudo['municipio'] ?? '') . '</td>'
                . '<td class="num">' . $this->esc($estudo['uf'] ?? '') . '</td></tr>';
        }
        $html .= '</table>';

        $assinaturas = is_array($dados['assinaturas'] ?? null) ? $dados['assinaturas'] : [];
        if ($assinaturas !== []) {
            $html .= '<p style="font-size:9pt;margin-top:12px;">Assinaturas: ';
            $nomes = [];
            foreach ($assinaturas as $a) {
                $nomes[] = trim((string) ($a['cargo'] ?? '') . ' — ' . (string) ($a['usuario_nome'] ?? ''));
            }
            $html .= $this->esc(implode(' · ', array_filter($nomes))) . '</p>';
        }
        $url = trim((string) ($dados['validation_url'] ?? ''));
        if ($url !== '') {
            $html .= '<p style="font-size:8pt;color:#4b5563;">Validação: ' . $this->esc($url) . '</p>';
        }

        return $html;
    }

    /**
     * Usa o layout vinculado à emissão do histórico. Sem vínculo, o modelo oficial da escola.
     *
     * @param array<string,mixed> $dadosPdf
     * @return array<string,mixed>|null
     */
    private function modeloHistorico(array $dadosPdf): ?array
    {
        $aluno = is_array($dadosPdf['aluno'] ?? null) ? $dadosPdf['aluno'] : [];
        $cursoId = 0;
        $serieId = 0;
        $alunoId = (int) ($aluno['id'] ?? 0);
        if ($alunoId > 0) {
            try {
                $row = $this->db->fetch(
                    'SELECT t.curso_novo_id, t.serie_id
                     FROM alunos a
                     INNER JOIN turmas t ON t.id = a.turma_id
                     WHERE a.id = :id
                     LIMIT 1',
                    ['id' => $alunoId]
                );
                if (is_array($row)) {
                    $cursoId = (int) ($row['curso_novo_id'] ?? 0);
                    $serieId = (int) ($row['serie_id'] ?? 0);
                }
            } catch (\Throwable $e) {
                $cursoId = 0;
            }
        }
        $candidatos = [];
        $vinculado = $this->modelos->codigoParaEmissao('historico', $cursoId, $serieId);
        if (is_string($vinculado) && $vinculado !== '') {
            $candidatos[] = $vinculado;
        }
        try {
            require_once dirname(__DIR__, 3) . '/Models/Education/ResultadoAcademico.php';
            $candidatos[] = (new \ResultadoAcademico())->getLayoutCodigo('historico');
        } catch (\Throwable $e) {
            $candidatos[] = '';
        }
        $candidatos[] = self::CODIGO_HISTORICO;
        foreach ($candidatos as $codigo) {
            $codigo = trim((string) $codigo);
            if ($codigo === '') {
                continue;
            }
            $modelo = $this->modelos->findByCodigo($codigo);
            if (is_array($modelo) && $this->modeloTemQuadroHistorico($modelo)) {
                return $modelo;
            }
        }

        return $this->modelos->findByCodigo(self::CODIGO_HISTORICO);
    }

    /**
     * @param array<string,mixed> $modelo
     */
    private function modeloTemQuadroHistorico(array $modelo): bool
    {
        $bruto = (string) ($modelo['corpo_html'] ?? '') . (string) ($modelo['estrutura_json'] ?? '');

        return str_contains($bruto, 'historico_html') || str_contains($bruto, '"type":"historico"');
    }

    /**
     * @param array<string,mixed> $dados
     */
    private function serieVisivel(array $dados, string $serie): string
    {
        $serie = trim($serie);
        if ($serie !== '' && $serie !== 'Série não informada') {
            return $serie;
        }
        $aluno = is_array($dados['aluno'] ?? null) ? $dados['aluno'] : [];
        $daTurma = trim((string) ($aluno['turma_serie'] ?? ''));
        if ($daTurma === '') {
            $daTurma = $this->serieDaTurma((int) ($aluno['id'] ?? 0));
        }

        return $daTurma !== '' ? $daTurma : 'Série';
    }

    private function serieDaTurma(int $alunoId): string
    {
        if ($alunoId <= 0) {
            return '';
        }
        try {
            $row = $this->db->fetch(
                'SELECT t.serie
                 FROM alunos a
                 INNER JOIN turmas t ON t.id = a.turma_id
                 WHERE a.id = :id
                 LIMIT 1',
                ['id' => $alunoId]
            );
        } catch (\Throwable $e) {
            return '';
        }

        return is_array($row) ? trim((string) ($row['serie'] ?? '')) : '';
    }

    private function notaHistorico($valor): string
    {
        $s = trim((string) $valor);
        if ($s === '') {
            return '—';
        }
        $normal = str_replace(',', '.', $s);
        if (!is_numeric($normal)) {
            return $s;
        }
        $casas = 1;
        if (preg_match('/[.,](\d+)/', $s, $m)) {
            $casas = min(2, strlen($m[1]));
        }

        return number_format((float) $normal, $casas, ',', '');
    }

    /**
     * @param array<string,mixed>|null $c
     */
    private function fmtCelula(?array $c): string
    {
        if ($c === null) {
            return '—';
        }
        if (!empty($c['conceito'])) {
            return (string) $c['conceito'];
        }
        if ($c['nota'] === null || $c['nota'] === '') {
            return '—';
        }
        $nota = number_format((float) $c['nota'], 1, ',', '');
        if (($c['origem'] ?? '') === 'externa') {
            $nota .= '¹';
        }
        return $nota;
    }

    /**
     * @param array<string,mixed>|null $c
     */
    private function fmtFalta(?array $c): string
    {
        if ($c === null || !isset($c['faltas']) || $c['faltas'] === null || $c['faltas'] === '') {
            return '—';
        }
        return (string) (int) $c['faltas'];
    }

    /**
     * @param array<string,mixed>|null $unidade
     */
    private function cidadeData(?array $unidade): string
    {
        $cidade = trim((string) ($unidade['cidade'] ?? '')) ?: 'Local';
        $meses = [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
            5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
            9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
        ];
        $n = (int) date('n');
        return $cidade . ', ' . date('d') . ' de ' . ($meses[$n] ?? '') . ' de ' . date('Y');
    }

    private function esc($v): string
    {
        $s = trim((string) $v);
        return $s === '' ? '—' : htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param array<string,mixed> $oficio
     * @return array<string,string>
     */
    private function varsDoOficio(array $oficio): array
    {
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $aluno = [];
        $unidade = [];
        $alunoId = (int) ($oficio['aluno_id'] ?? 0);
        if ($alunoId > 0) {
            try {
                require_once dirname(__DIR__, 3) . '/Services/DeclarationService.php';
                $decl = new \App\Services\DeclarationService($this->db);
                $encontrado = $decl->getAluno($alunoId);
                $aluno = is_array($encontrado) ? $encontrado : [];
                $uni = $aluno !== [] ? $decl->getUnidadeForAluno($aluno) : null;
                $unidade = is_array($uni) ? $uni : [];
            } catch (\Throwable $e) {
                $aluno = ['nome' => (string) ($oficio['aluno_nome'] ?? '')];
            }
        }
        $numero = (int) ($oficio['numero'] ?? 0);
        $ano = (int) ($oficio['ano'] ?? date('Y'));
        $titulo = $numero > 0 ? ('Ofício nº ' . $numero . '/' . $ano) : 'Ofício (rascunho)';
        $vars = $this->modelos->varsFromDeclaracao([
            'tipo' => 'oficio',
            'titulo' => $titulo,
            'dados' => [
                'aluno' => $aluno,
                'unidade' => $unidade,
                'matricula' => [],
            ],
            'numero' => $numero,
            'ano' => $ano,
            'gerado_em' => date('d/m/Y'),
            'cidade_data' => $this->cidadeData($unidade),
        ]);
        $vars['titulo'] = $esc($titulo);
        $vars['doc_rotulo'] = $esc($titulo);
        $vars['numero'] = $numero > 0 ? (string) $numero : '—';
        $vars['ano'] = (string) $ano;
        $vars['destinatario'] = $esc($oficio['destinatario'] ?? '');
        $vars['cargo_destinatario'] = $esc($oficio['cargo_destinatario'] ?? '');
        $vars['instituicao'] = $esc($oficio['instituicao'] ?? '');
        $vars['assunto'] = $esc($oficio['assunto'] ?? '');
        $data = substr((string) ($oficio['data_oficio'] ?? ''), 0, 10);
        $dt = \DateTime::createFromFormat('Y-m-d', $data);
        $vars['data_oficio'] = $dt ? $esc($dt->format('d/m/Y')) : $esc($data);
        $vars['corpo_oficio_html'] = nl2br($esc(trim((string) ($oficio['corpo'] ?? ''))), false);
        require_once dirname(__DIR__, 3) . '/Helpers/StudentFormHelper.php';
        if (\StudentFormHelper::nomeExibicao($aluno) !== '') {
            $vars['aluno_nome'] = $esc(\StudentFormHelper::nomeOficialLinha($aluno));
        } elseif (!empty($oficio['aluno_nome'])) {
            $vars['aluno_nome'] = $esc($oficio['aluno_nome']);
        }
        if (!empty($oficio['turma_nome'])) {
            $vars['turma_nome'] = $esc($oficio['turma_nome']);
        }
        return $vars;
    }

    /**
     * @param array<string,mixed> $modelo
     * @param array<string,mixed>|null $config
     */
    private function enviarPdf(string $html, string $filename, array $modelo, int $alunoId = 0, string $titulo = '', ?array $config = null): void
    {
        $filename = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $filename) ?: 'documento.pdf';
        if (!str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }
        $autoload = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4)) . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
        $old = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $bin = $this->gerarPdfBinario($html, $modelo);
            $this->arquivarPdfAluno($alunoId, $titulo, $filename, $bin, $config);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($bin));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $bin;
            exit;
        } finally {
            ini_set('display_errors', (string) $old);
        }
    }

    /**
     * @param array<string,mixed>|null $config
     */
    private function arquivarPdfAluno(int $alunoId, string $titulo, string $filename, string $bin, ?array $config): void
    {
        if ($alunoId <= 0 || $bin === '') {
            return;
        }
        try {
            require_once __DIR__ . '/ArquivoPdfAlunoService.php';
            $tipo = preg_replace('/[^a-z0-9]+/i', '_', $titulo) ?: 'documento';
            (new ArquivoPdfAlunoService($this->db))->guardar(
                $alunoId,
                strtolower((string) $tipo),
                $titulo !== '' ? $titulo : 'Documento',
                $bin,
                $filename,
                null,
                '',
                $config
            );
        } catch (\Throwable $e) {
            error_log('Vida escolar arquivar PDF: ' . $e->getMessage());
        }
    }

    public function garantirModelos(): void
    {
        if (!$this->modelos->schemaReady()) {
            return;
        }
        $rodape = '<div class="fecho">{{cidade_data}}.</div><div class="assinaturas">'
            . '<div class="sig"><div class="line"></div><div class="nome">{{secretario_nome}}</div><div class="cargo">Secretaria</div></div>'
            . '<div class="sig"><div class="line"></div><div class="nome">{{diretor_nome}}</div><div class="cargo">Direção</div></div></div>';
        foreach ($this->catalogoModelos($rodape) as $row) {
            $existe = $this->db->fetch(
                'SELECT id FROM secretaria_modelos_documentos WHERE codigo = :c LIMIT 1',
                ['c' => $row['codigo']]
            );
            if ($existe) {
                continue;
            }
            try {
                $this->modelos->salvar($row, null, null);
            } catch (\Throwable $e) {
                error_log('VidaEscolarPdfService garantirModelos: ' . $e->getMessage());
            }
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function catalogoModelos(string $rodape): array
    {
        $cab = '';
        return [
            [
                'codigo' => self::CODIGO_BOLETIM,
                'nome' => 'Boletim (Vida Escolar)',
                'descricao' => 'Ficha oficial do ano. Placeholders: {{quadro_notas_html}}, {{aluno_nome}}, {{turma_nome}}.',
                'cabecalho_html' => $cab,
                'corpo_html' => '<div class="doc-num">{{doc_rotulo}}</div><h1 class="doc-title">{{titulo}}</h1>'
                    . '<p style="text-align:center;font-size:10pt;color:#4b5563;">{{turma_nome}} · {{serie}} · {{ano_letivo}}</p>'
                    . '<table class="dados"><tr><td class="label">Aluno(a)</td><td>{{aluno_nome}}</td></tr>'
                    . '<tr><td class="label">Matrícula / RA</td><td>{{aluno_codigo}}</td></tr>'
                    . '<tr><td class="label">CPF</td><td>{{aluno_cpf}}</td></tr>'
                    . '<tr><td class="label">Nascimento</td><td>{{aluno_data_nasc}}</td></tr>'
                    . '<tr><td class="label">Turma / série</td><td>{{turma_nome}} · {{serie}}</td></tr>'
                    . '<tr><td class="label">Situação</td><td>{{situacao_final}}</td></tr>'
                    . '<tr><td class="label">Frequência</td><td>{{frequencia_percentual}}</td></tr></table>'
                    . '{{quadro_notas_html}}<p>{{observacoes}}</p>',
                'rodape_html' => $rodape,
                'orientacao' => 'paisagem',
                'ativo' => 1,
                'usar_layout_padrao' => 1,
            ],
            [
                'codigo' => self::CODIGO_PACOTE,
                'nome' => 'Pacote de transferência',
                'descricao' => 'Identidade + trajetória + boletim. {{identidade_html}}, {{trajetoria_html}}, {{quadro_notas_html}}.',
                'cabecalho_html' => $cab,
                'corpo_html' => '<div class="doc-num">{{doc_rotulo}}</div><h1 class="doc-title">{{titulo}}</h1>'
                    . '<p style="text-align:center;font-size:10pt;color:#4b5563;">{{aluno_nome}} · {{ano_letivo}}</p>'
                    . '<h3>1. Identificação</h3>{{identidade_html}}'
                    . '<h3>2. Trajetória</h3>{{trajetoria_html}}'
                    . '<h3>3. Boletim do ano</h3>{{quadro_notas_html}}'
                    . '<p>Emita também o Histórico Escolar oficial. Débito financeiro não impede a expedição destes documentos acadêmicos.</p>',
                'rodape_html' => $rodape,
                'orientacao' => 'retrato',
                'ativo' => 1,
                'usar_layout_padrao' => 1,
            ],
            [
                'codigo' => self::CODIGO_DOSSIE,
                'nome' => 'Dossiê do aluno',
                'descricao' => 'Pacote completo. {{identidade_html}}, {{trajetoria_html}}, {{quadro_notas_html}}, {{documentos_html}}, {{sed_html}}.',
                'cabecalho_html' => $cab,
                'corpo_html' => '<div class="doc-num">{{doc_rotulo}}</div><h1 class="doc-title">{{titulo}}</h1>'
                    . '<p style="text-align:center;font-size:10pt;color:#4b5563;">{{aluno_nome}} · {{data_hoje}}</p>'
                    . '<h3>Identidade</h3>{{identidade_html}}'
                    . '<h3>Trajetória</h3>{{trajetoria_html}}'
                    . '<h3>Boletim</h3>{{quadro_notas_html}}'
                    . '<h3>Documentos de matrícula</h3>{{documentos_html}}'
                    . '<h3>SED / Educacenso</h3>{{sed_html}}',
                'rodape_html' => $rodape,
                'orientacao' => 'retrato',
                'ativo' => 1,
                'usar_layout_padrao' => 1,
            ],
            [
                'codigo' => self::CODIGO_SED,
                'nome' => 'Planilha SED',
                'descricao' => 'Campos para digitação na SED. {{identidade_html}} e {{sed_html}}.',
                'cabecalho_html' => $cab,
                'corpo_html' => '<div class="doc-num">{{doc_rotulo}}</div><h1 class="doc-title">{{titulo}}</h1>'
                    . '<p>Planilha de apoio à digitação no portal da SED. Não há API pública.</p>'
                    . '{{identidade_html}}<h3>Conferência</h3>{{sed_html}}',
                'rodape_html' => $rodape,
                'orientacao' => 'retrato',
                'ativo' => 1,
                'usar_layout_padrao' => 1,
            ],
            [
                'codigo' => self::CODIGO_HISTORICO,
                'nome' => 'Histórico escolar oficial',
                'descricao' => 'Documento emitido/assinado. Placeholders: {{historico_html}}, {{aluno_nome}}, {{observacoes}}.',
                'cabecalho_html' => $cab,
                'corpo_html' => '<div class="doc-num">Histórico nº {{numero}}/{{ano}}</div><h1 class="doc-title">Histórico Escolar</h1>'
                    . '<table class="dados"><tr><td class="label">Aluno(a)</td><td>{{aluno_nome}}</td></tr>'
                    . '<tr><td class="label">CPF</td><td>{{aluno_cpf}}</td></tr>'
                    . '<tr><td class="label">Nascimento</td><td>{{aluno_data_nasc}}</td></tr>'
                    . '<tr><td class="label">Filiação</td><td>{{resp_nome}}</td></tr>'
                    . '<tr><td class="label">Turma atual</td><td>{{turma_nome}} · {{serie}}</td></tr></table>'
                    . '{{historico_html}}<p>{{observacoes}}</p>',
                'rodape_html' => $rodape,
                'orientacao' => 'paisagem',
                'ativo' => 1,
                'usar_layout_padrao' => 1,
            ],
            [
                'codigo' => self::CODIGO_OFICIO,
                'nome' => 'Ofício da secretaria',
                'descricao' => 'Correspondência oficial numerada. Placeholders: {{destinatario}}, {{assunto}}, {{corpo_oficio_html}}.',
                'cabecalho_html' => $cab,
                'corpo_html' => '<div class="doc-num">Ofício nº {{numero}}/{{ano}}</div>'
                    . '<h1 class="doc-title">Ofício</h1>'
                    . '<p style="text-align:right;font-size:10pt;">{{cidade_data}}</p>'
                    . '<table class="dados">'
                    . '<tr><td class="label">Destinatário</td><td>{{destinatario}}</td></tr>'
                    . '<tr><td class="label">Cargo</td><td>{{cargo_destinatario}}</td></tr>'
                    . '<tr><td class="label">Instituição</td><td>{{instituicao}}</td></tr>'
                    . '<tr><td class="label">Assunto</td><td>{{assunto}}</td></tr>'
                    . '<tr><td class="label">Aluno(a)</td><td>{{aluno_nome}}</td></tr>'
                    . '<tr><td class="label">Turma</td><td>{{turma_nome}}</td></tr>'
                    . '</table>'
                    . '<p>{{corpo_oficio_html}}</p>',
                'rodape_html' => $rodape,
                'orientacao' => 'retrato',
                'ativo' => 1,
                'usar_layout_padrao' => 1,
            ],
        ];
    }
}
