<?php

namespace App\Modulos\VidaEscolar\Services;

require_once __DIR__ . '/../../../Modulos/modelos-documentos/Services/ModeloDocumentoService.php';

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
     * @param array<string,mixed> $dadosPdf
     * @param array<string,mixed>|null $config
     */
    public function emitirHistorico(array $dadosPdf, ?array $config, string $filename): void
    {
        $this->garantirModelos();
        $modelo = $this->modelos->findByCodigo(self::CODIGO_HISTORICO);
        if (!$modelo) {
            throw new \RuntimeException('Modelo vida_escolar_historico indisponível. Cadastre-o em Layout de documentos.');
        }
        $aluno = is_array($dadosPdf['aluno'] ?? null) ? $dadosPdf['aluno'] : [];
        $unidade = is_array($dadosPdf['unidade'] ?? null) ? $dadosPdf['unidade'] : [];
        $doc = is_array($dadosPdf['documento'] ?? null) ? $dadosPdf['documento'] : [];
        $viewData = [
            'tipo' => 'historico',
            'titulo' => 'Histórico Escolar',
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
        $vars['historico_html'] = $this->historicoOficialHtml($dadosPdf);
        $vars['observacoes'] = htmlspecialchars(
            (string) ($dadosPdf['observacoes_gerais'] ?? $doc['observacoes_gerais'] ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
        $html = $this->modelos->renderHtml($modelo, $vars, ModeloDocumentoService::estiloDoModelo($modelo), $config);
        $this->enviarPdf($html, $filename, $modelo);
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
        $this->enviarPdf($out['html'], $filename, $out['modelo']);
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
        $vars['observacoes'] = htmlspecialchars(
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
        $cols = [1, 2, 3, 4, 0];
        $html = '<table class="dados"><tr><td class="label" rowspan="2">Componente</td>';
        foreach ($cols as $p) {
            $html .= '<td class="label" colspan="2" style="text-align:center">' . $this->esc($periodos[$p] ?? (string) $p) . '</td>';
        }
        $html .= '</tr><tr>';
        foreach ($cols as $_p) {
            $html .= '<td class="label" style="text-align:center">Nota</td><td class="label" style="text-align:center">Falta</td>';
        }
        $html .= '</tr>';
        if ($grid === []) {
            return $html . '<tr><td colspan="11">Sem ficha de boletim para este ano.</td></tr></table>';
        }
        foreach ($grid as $row) {
            $html .= '<tr><td>' . $this->esc($row['linha']['componente_nome'] ?? '') . '</td>';
            foreach ($cols as $p) {
                $c = is_array($row['celulas'][$p] ?? null) ? $row['celulas'][$p] : null;
                $html .= '<td style="text-align:center">' . $this->esc($this->fmtCelula($c)) . '</td>'
                    . '<td style="text-align:center">' . $this->esc($this->fmtFalta($c)) . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</table><p style="font-size:9pt;color:#4b5563;">¹ Resultado recebido da escola de origem.</p>';
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
     * @param array<string,mixed> $dados
     */
    private function historicoOficialHtml(array $dados): string
    {
        $itens = is_array($dados['itens'] ?? null) ? $dados['itens'] : [];
        $resultados = is_array($dados['resultados'] ?? null) ? $dados['resultados'] : [];
        $labels = is_array($dados['resultado_labels'] ?? null) ? $dados['resultado_labels'] : [];
        $porAno = [];
        foreach ($itens as $it) {
            $k = (string) ($it['ano_letivo'] ?? '') . '|' . (string) ($it['serie_ano'] ?? '');
            if (!isset($porAno[$k])) {
                $porAno[$k] = [
                    'ano' => (string) ($it['ano_letivo'] ?? ''),
                    'serie' => (string) ($it['serie_ano'] ?? ''),
                    'escola' => (string) ($it['escola_origem'] ?? ''),
                    'itens' => [],
                ];
            }
            $porAno[$k]['itens'][] = $it;
        }
        ksort($porAno);
        $resultMap = [];
        foreach ($resultados as $r) {
            $resultMap[(string) ($r['ano_letivo'] ?? '') . '|' . (string) ($r['serie_ano'] ?? '')] = $r;
        }
        if ($porAno === []) {
            return '<p>Sem componentes lançados neste histórico.</p>';
        }
        $html = '';
        foreach ($porAno as $k => $bloco) {
            $res = $resultMap[$k] ?? [];
            $resLabel = (string) ($labels[$res['resultado'] ?? ''] ?? ($res['resultado'] ?? '—'));
            $html .= '<h3>' . $this->esc($bloco['ano'] . ' · ' . $bloco['serie']) . '</h3>'
                . '<p style="font-size:9pt;color:#4b5563;">'
                . $this->esc($bloco['escola'] !== '' ? $bloco['escola'] : 'Esta instituição')
                . ' · Resultado: ' . $this->esc($resLabel) . '</p>'
                . '<table class="dados"><tr><td class="label">Componente</td><td class="label">Nota</td>'
                . '<td class="label">Carga horária</td><td class="label">Origem</td></tr>';
            foreach ($bloco['itens'] as $it) {
                $html .= '<tr><td>' . $this->esc($it['componente'] ?? '') . '</td>'
                    . '<td>' . $this->esc($it['resultado_valor'] ?? '—') . '</td>'
                    . '<td>' . $this->esc($it['carga_horaria'] ?? '—') . '</td>'
                    . '<td>' . $this->esc($it['origem'] ?? '') . '</td></tr>';
            }
            $html .= '</table>';
        }
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
     * @param array<string,mixed> $modelo
     */
    private function enviarPdf(string $html, string $filename, array $modelo): void
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
                    . '<tr><td class="label">CPF</td><td>{{aluno_cpf}}</td></tr>'
                    . '<tr><td class="label">Nascimento</td><td>{{aluno_data_nasc}}</td></tr>'
                    . '<tr><td class="label">Turma / série</td><td>{{turma_nome}} · {{serie}}</td></tr>'
                    . '<tr><td class="label">Situação</td><td>{{situacao_matricula}}</td></tr></table>'
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
        ];
    }
}
