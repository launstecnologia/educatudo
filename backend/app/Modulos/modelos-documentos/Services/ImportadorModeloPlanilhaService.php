<?php

namespace App\Modulos\ModelosDocumentos\Services;

require_once __DIR__ . '/GradeSoltaService.php';
require_once __DIR__ . '/ConversorFolhaExcelHtml.php';

/**
 * Lê planilha oficial (xlsx/xlsm) e monta a estrutura do editor.
 * Modelos SEED conhecidos (1127, 1127-A, 1127-B, 1128) entram prontos.
 * Folha desconhecida vira sugestão de rótulo → placeholder para conferência.
 */
class ImportadorModeloPlanilhaService
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function catalogo(): array
    {
        return [
            '1127' => [
                'codigo' => 'seed_fi_1127',
                'nome' => 'Ficha individual — Médio NEM trimestral (1127/2026)',
                'codigo_emissao' => 'resultado_ficha_individual',
                'orientacao' => 'retrato',
                'tipo' => 'ficha',
                'seed' => 'SEED/DNE/CDE - 1127/2026',
                'periodos' => ['1º Trimestre', '2º Trimestre', '3º Trimestre'],
                'subtitulo' => 'ENSINO MÉDIO',
                'arquivo' => 'ficha-1127-trimestral.xlsm',
            ],
            '1127a' => [
                'codigo' => 'seed_fi_1127a',
                'nome' => 'Ficha individual — Médio NEM bimestral (1127-A/2026)',
                'codigo_emissao' => 'resultado_ficha_individual',
                'orientacao' => 'retrato',
                'tipo' => 'ficha',
                'seed' => 'SEED/DNE/CDE - 1127-A/2026',
                'periodos' => ['1º Bimestre', '2º Bimestre', '3º Bimestre', '4º Bimestre'],
                'subtitulo' => 'ENSINO MÉDIO',
                'arquivo' => 'ficha-1127-bimestral-semestral.xlsm',
            ],
            '1127b' => [
                'codigo' => 'seed_fi_1127b',
                'nome' => 'Ficha individual — Médio NEM semestral (1127-B/2026)',
                'codigo_emissao' => 'resultado_ficha_individual',
                'orientacao' => 'retrato',
                'tipo' => 'ficha',
                'seed' => 'SEED/DNE/CDE - 1127-B/2026',
                'periodos' => ['1º Semestre', '2º Semestre'],
                'subtitulo' => 'ENSINO MÉDIO',
                'arquivo' => 'ficha-1127-bimestral-semestral.xlsm',
            ],
            '1128' => [
                'codigo' => 'seed_rf_1128',
                'nome' => 'Relatório final — Médio NEM (1128/2026)',
                'codigo_emissao' => 'resultado_relatorio_padrao',
                'orientacao' => 'paisagem',
                'tipo' => 'relatorio',
                'seed' => 'SEED/DNE/CDE - 1128/2026',
                'periodos' => [],
                'subtitulo' => 'ENSINO MÉDIO',
                'arquivo' => 'relatorio-1128.xlsx',
            ],
        ];
    }

    /** @return array<string,string> rótulo normalizado => placeholder */
    public static function dicionarioRotulos(): array
    {
        return [
            'aluno(a)' => 'aluno_nome',
            'aluno a' => 'aluno_nome',
            'aluno' => 'aluno_nome',
            'sexo' => 'aluno_sexo',
            'gen' => 'aluno_sexo',
            'codigo' => 'aluno_codigo',
            'rg/uf' => 'aluno_rg',
            'rg' => 'aluno_rg',
            'pais' => 'aluno_pais',
            'data de nascimento' => 'aluno_data_nasc',
            'municipio/uf' => 'aluno_naturalidade',
            'filiacao' => 'aluno_filiacao',
            'organizacao' => 'organizacao',
            'turno' => 'turno',
            'turma' => 'turma_nome',
            'n' => 'numero_chamada',
            'no' => 'numero_chamada',
            'dias letivos' => 'dias_letivos',
            'total de horas anuais' => 'carga_horaria_total',
            'total de horas' => 'carga_horaria_total',
            'ano' => 'ano_letivo',
            'ano/epoca' => 'ano_letivo',
            'estabelecimento' => 'escola_nome',
            'endereco' => 'escola_endereco',
            'telefone' => 'escola_telefone',
            'municipio' => 'escola_municipio',
            'nre' => 'escola_nre',
            'entidade mantenedora' => 'entidade_mantenedora',
            'resultado final' => 'situacao_final',
            'resultado' => 'situacao_final',
            '% de frequencia no ano' => 'frequencia_percentual',
            'media anual' => 'quadro_notas_html',
            'media final' => 'quadro_notas_html',
            'observacoes' => 'observacoes',
            'local/data' => 'cidade_data',
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function analisarArquivo(string $caminho): array
    {
        $folhas = $this->lerFolhas($caminho);
        if ($folhas === []) {
            throw new \InvalidArgumentException('A planilha não tem folhas legíveis.');
        }
        $saida = [];
        foreach ($folhas as $folha) {
            $chave = $this->identificar($folha);
            if ($chave !== null) {
                $meta = self::catalogo()[$chave];
                $meta['chave'] = $chave;
                $saida[] = [
                    'reconhecido' => true,
                    'chave' => $chave,
                    'folha' => $folha['nome'],
                    'meta' => $meta,
                    'estrutura' => $this->estruturaDaFolha($chave, $folha),
                ];
                continue;
            }
            $saida[] = [
                'reconhecido' => false,
                'chave' => '',
                'folha' => $folha['nome'],
                'campos' => $this->sugerirCampos($folha),
                'titulo' => $this->tituloDaFolha($folha),
            ];
        }
        return $saida;
    }

    /**
     * @return array<string,mixed>
     */
    public function estruturaDoCatalogo(string $chave): array
    {
        $meta = self::catalogo()[$chave] ?? null;
        if ($meta === null) {
            throw new \InvalidArgumentException('Modelo oficial desconhecido.');
        }
        $meta['chave'] = $chave;
        $arquivo = $this->arquivoDoCatalogo($chave);
        if (is_string($arquivo) && is_file($arquivo)) {
            foreach ($this->analisarArquivo($arquivo) as $folha) {
                if (($folha['chave'] ?? '') === $chave && is_array($folha['estrutura'] ?? null)) {
                    return $folha['estrutura'];
                }
            }
        }
        if (($meta['tipo'] ?? '') === 'relatorio') {
            return $this->estruturaRelatorio($meta);
        }

        return $this->estruturaFicha($meta);
    }

    public function arquivoDoCatalogo(string $chave): ?string
    {
        $meta = self::catalogo()[$chave] ?? null;
        $nome = is_array($meta) ? (string) ($meta['arquivo'] ?? '') : '';
        if ($nome === '' || str_contains($nome, '..') || str_contains($nome, '/')) {
            return null;
        }
        $caminho = dirname(__DIR__) . '/Recursos/' . $nome;

        return is_file($caminho) ? $caminho : null;
    }

    /**
     * @param array<string,mixed> $folha
     * @return array<string,mixed>
     */
    public function estruturaDaFolha(string $chave, array $folha): array
    {
        $meta = self::catalogo()[$chave] ?? null;
        if ($meta === null) {
            throw new \InvalidArgumentException('Modelo oficial desconhecido.');
        }
        $meta['chave'] = $chave;
        $grade = is_array($folha['grade'] ?? null) ? $folha['grade'] : [];
        $partes = ConversorFolhaExcelHtml::converterPartes($grade, $chave);
        if (($partes['frente'] ?? '') === '') {
            return ($meta['tipo'] ?? '') === 'relatorio'
                ? $this->estruturaRelatorio($meta)
                : $this->estruturaFicha($meta);
        }
        $orientacao = ($meta['tipo'] ?? '') === 'relatorio' ? 'paisagem' : 'retrato';

        return $this->embrulhar(
            $partes['frente'],
            $orientacao,
            2,
            GradeSoltaService::mapa($chave),
            (string) ($partes['verso'] ?? '')
        );
    }

    /**
     * Estrutura a partir da conferência (folha não reconhecida).
     *
     * @param list<array{rotulo:string,placeholder:string}> $campos
     * @return array<string,mixed>
     */
    public function estruturaConferida(string $nome, string $orientacao, array $campos): array
    {
        $linhas = '';
        foreach ($campos as $campo) {
            $rotulo = trim((string) ($campo['rotulo'] ?? ''));
            $ph = trim((string) ($campo['placeholder'] ?? ''));
            if ($rotulo === '' || $ph === '' || $ph === '_ignorar') {
                continue;
            }
            if (!isset(ModeloDocumentoService::PLACEHOLDERS[$ph])) {
                continue;
            }
            $linhas .= '<tr><td class="label">' . htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8')
                . '</td><td>{{' . $ph . '}}</td></tr>';
        }
        if ($linhas === '') {
            throw new \InvalidArgumentException('Marque ao menos um campo para gravar no modelo.');
        }
        $html = '<h1 style="text-align:center;font-size:14px;margin:0 0 8px;">'
            . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<table class="dados" style="width:100%;border-collapse:collapse;">' . $linhas . '</table>'
            . '<p style="margin-top:12px;">{{cidade_data}}</p>';
        return $this->embrulhar($html, $orientacao === 'paisagem' ? 'paisagem' : 'retrato', 12);
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private function estruturaFicha(array $meta): array
    {
        $chave = (string) ($meta['chave'] ?? '1127');
        $seed = htmlspecialchars((string) ($meta['seed'] ?? ''), ENT_QUOTES, 'UTF-8');
        $html = '<div style="page-break-inside:avoid;">'
            . '<table class="dados seed-compacta" style="width:100%;border-collapse:collapse;font-size:9px;">'
            . '<tr><td colspan="4" style="text-align:center;font-weight:bold;">ESTADO DO PARANÁ<br>SECRETARIA DE ESTADO DA EDUCAÇÃO</td>'
            . '<td style="text-align:right;">{{logo_html}}</td></tr>'
            . '<tr><td colspan="5" style="text-align:center;font-weight:bold;">FICHA INDIVIDUAL - LEI Nº 9394/96 E SUAS ALTERAÇÕES<br>'
            . htmlspecialchars((string) ($meta['subtitulo'] ?? 'ENSINO MÉDIO'), ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td class="label">ESTABELECIMENTO</td><td colspan="4">{{escola_nome}}</td></tr>'
            . '<tr><td class="label">ENDEREÇO</td><td colspan="4">{{escola_endereco}}</td></tr>'
            . '<tr><td class="label">TELEFONE</td><td>{{escola_telefone}}</td><td class="label">MUNICÍPIO</td><td>{{escola_municipio}}</td><td class="label">NRE: {{escola_nre}}</td></tr>'
            . '<tr><td class="label">ENTIDADE MANTENEDORA</td><td colspan="4">{{entidade_mantenedora}}</td></tr>'
            . '<tr><td class="label">ALUNO(A)</td><td colspan="3">{{aluno_nome}}</td><td class="label">SEXO: {{aluno_sexo}}</td></tr>'
            . '<tr><td class="label">CÓDIGO</td><td>{{aluno_codigo}}</td><td class="label">RG/UF</td><td>{{aluno_rg}}</td><td class="label">PAÍS: {{aluno_pais}}</td></tr>'
            . '<tr><td class="label">DATA DE NASCIMENTO</td><td>{{aluno_data_nasc}}</td><td class="label">MUNICÍPIO/UF</td><td colspan="2">{{aluno_naturalidade}}</td></tr>'
            . '<tr><td class="label">FILIAÇÃO</td><td colspan="4">{{aluno_filiacao}}</td></tr>'
            . '<tr><td class="label">Organização</td><td>{{organizacao}}</td><td class="label">Turno</td><td>{{turno}}</td><td>Turma: {{turma_nome}} &nbsp; Nº: {{numero_chamada}}</td></tr>'
            . '<tr><td class="label">Dias Letivos</td><td>{{dias_letivos}}</td><td class="label">Total de Horas Anuais</td><td>{{carga_horaria_total}}</td><td>Ano: {{ano_letivo}}</td></tr>'
            . '</table>'
            . '<p style="font-size:9px;font-weight:bold;margin:8px 0 4px;">COMPONENTES CURRICULARES — Periodicidade / Aproveitamento / Assiduidade</p>'
            . GradeSoltaService::htmlFicha(GradeSoltaService::periodos($chave))
            . '<table class="dados seed-compacta" style="width:100%;border-collapse:collapse;font-size:9px;margin-top:8px;">'
            . '<tr><td class="label">Faltas</td><td>{{observacoes}}</td><td class="label">% de Frequência no Ano</td><td>{{frequencia_percentual}}</td></tr>'
            . '<tr><td class="label">RESULTADO FINAL</td><td colspan="3">{{situacao_final}}</td></tr>'
            . '<tr><td class="label">Local/Data</td><td colspan="3">{{cidade_data}}</td></tr>'
            . '</table>'
            . '<table style="width:100%;margin-top:16px;font-size:9px;"><tr>'
            . '<td style="width:50%;text-align:center;">________________________<br><strong>{{secretario_nome}}</strong><br>Secretário(a) — Ato (nº/ano)</td>'
            . '<td style="width:50%;text-align:center;">________________________<br><strong>{{diretor_nome}}</strong><br>Diretor(a) — Ato (nº/ano)</td>'
            . '</tr></table>'
            . '<p style="font-size:8px;">O presente documento não contém emendas nem rasuras. ' . $seed . '</p>'
            . '<p style="font-size:9px;font-weight:bold;">Guia de Transferência</p>'
            . '<p style="font-size:9px;">A presente transferência é expedida ao(à) aluno(a) {{aluno_nome}}, matriculado(a) em {{turma_nome}}, no ano {{ano_letivo}}, nos termos da Lei nº 9394/96.</p>'
            . '</div>';

        return $this->embrulhar($html, 'retrato', 8, GradeSoltaService::mapa($chave));
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private function estruturaRelatorio(array $meta): array
    {
        $seed = htmlspecialchars((string) ($meta['seed'] ?? ''), ENT_QUOTES, 'UTF-8');
        $html = '<div style="page-break-inside:avoid;">'
            . '<table class="dados seed-compacta" style="width:100%;border-collapse:collapse;font-size:9px;">'
            . '<tr><td style="font-weight:bold;">ESTADO DO PARANÁ<br>SECRETARIA DE ESTADO DA EDUCAÇÃO</td>'
            . '<td style="text-align:right;font-weight:bold;">RELATÓRIO FINAL - LEI Nº 9394/96<br>'
            . htmlspecialchars((string) ($meta['subtitulo'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td class="label">Estabelecimento</td><td>{{escola_nome}}</td></tr>'
            . '<tr><td class="label">Município / NRE</td><td>{{escola_municipio}} — {{escola_nre}}</td></tr>'
            . '<tr><td class="label">Entidade Mantenedora</td><td>{{entidade_mantenedora}}</td></tr>'
            . '<tr><td class="label">Organização / Turma / Turno</td><td>{{organizacao}} — {{turma_nome}} — {{turno}}</td></tr>'
            . '<tr><td class="label">Total de Horas / Dias Letivos / Ano</td><td>{{carga_horaria_total}} — {{dias_letivos}} — {{ano_letivo}}</td></tr>'
            . '</table>'
            . '<p style="font-size:9px;font-weight:bold;margin:8px 0 4px;">NOTAS OU MENÇÕES — Formação Geral Básica, Itinerário e Parte Diversificada</p>'
            . GradeSoltaService::htmlRelatorio()
            . '<p style="font-size:9px;margin-top:8px;"><strong>Observações:</strong> {{observacoes}}</p>'
            . '<p style="font-size:9px;">{{cidade_data}}</p>'
            . '<table style="width:100%;margin-top:12px;font-size:9px;"><tr>'
            . '<td style="width:50%;text-align:center;">________________________<br>{{secretario_nome}}<br>Secretário(a)</td>'
            . '<td style="width:50%;text-align:center;">________________________<br>{{diretor_nome}}<br>Diretor(a)</td>'
            . '</tr></table>'
            . '<p style="font-size:8px;">O presente documento não contém emendas nem rasuras. ' . $seed . '</p>'
            . '</div>';

        return $this->embrulhar($html, 'paisagem', 8, GradeSoltaService::mapa('1128'));
    }

    /**
     * @param array<string,mixed>|null $grade
     * @return array<string,mixed>
     */
    private function embrulhar(string $html, string $orientacao, int $margem, ?array $grade = null, string $htmlVerso = ''): array
    {
        $est = ModeloDocumentoService::estruturaVazia('a4', $orientacao, $margem);
        $secao = ModeloDocumentoService::secaoPadrao([100], 'body');
        $secao['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura('html', ['html' => $html]);
        $est['body']['sections'] = [$secao];
        if ($htmlVerso !== '') {
            $verso = ModeloDocumentoService::secaoPadrao([100], 'body');
            $verso['pageBreakBefore'] = true;
            $verso['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura('html', ['html' => $htmlVerso]);
            $est['body']['sections'][] = $verso;
        }
        $est['header']['sections'] = [];
        $est['footer']['sections'] = [];
        if ($grade !== null) {
            $est['grade'] = $grade;
        }
        return $est;
    }

    /**
     * @param array{nome:string,textos:list<string>} $folha
     */
    private function identificar(array $folha): ?string
    {
        $nome = self::normalizar((string) ($folha['nome'] ?? ''));
        $blob = $nome . ' ' . self::normalizar(implode(' ', $folha['textos'] ?? []));
        if (str_contains($blob, '1128') || str_contains($blob, 'relatorio final')) {
            return '1128';
        }
        $ficha = str_contains($blob, 'ficha individual') || str_contains($blob, '1127');
        if (!$ficha) {
            return null;
        }
        if (str_contains($blob, '1127 a') || str_contains($blob, '1127a') || str_contains($nome, 'bimestral') || str_contains($blob, '1o bimestre') || str_contains($blob, '1 bimestre')) {
            return '1127a';
        }
        if (str_contains($blob, '1127 b') || str_contains($blob, '1127b') || str_contains($nome, 'semestral') || str_contains($blob, '1o semestre') || str_contains($blob, '1 semestre')) {
            return '1127b';
        }
        if (str_contains($blob, '1127') || str_contains($nome, 'trimestral') || str_contains($blob, 'trimestre')) {
            return '1127';
        }
        return null;
    }

    /**
     * @param array{nome:string,textos:list<string>} $folha
     * @return list<array{rotulo:string,placeholder:string,confianca:string}>
     */
    private function sugerirCampos(array $folha): array
    {
        $dic = self::dicionarioRotulos();
        $vistos = [];
        $campos = [];
        foreach ($folha['textos'] ?? [] as $texto) {
            $bruto = trim((string) $texto);
            if ($bruto === '' || mb_strlen($bruto) > 80) {
                continue;
            }
            $chave = self::normalizar(rtrim($bruto, " :\t"));
            if ($chave === '' || isset($vistos[$chave])) {
                continue;
            }
            if (!isset($dic[$chave]) && !str_ends_with(trim($bruto), ':') && mb_strlen($chave) > 28) {
                continue;
            }
            $vistos[$chave] = true;
            if (isset($dic[$chave])) {
                $campos[] = [
                    'rotulo' => $bruto,
                    'placeholder' => $dic[$chave],
                    'confianca' => 'dicionario',
                ];
                continue;
            }
            $sugestao = $this->sugerirPlaceholder($chave);
            $campos[] = [
                'rotulo' => $bruto,
                'placeholder' => $sugestao,
                'confianca' => $sugestao !== '' ? 'sugestao' : 'manual',
            ];
            if (count($campos) >= 40) {
                break;
            }
        }
        return $campos;
    }

    private function sugerirPlaceholder(string $rotulo): string
    {
        $melhor = '';
        $pontos = 0.0;
        foreach (ModeloDocumentoService::PLACEHOLDERS as $chave => $label) {
            $alvo = self::normalizar((string) $label . ' ' . str_replace('_', ' ', $chave));
            similar_text($rotulo, $alvo, $pct);
            if ($pct > $pontos) {
                $pontos = $pct;
                $melhor = $chave;
            }
        }
        return $pontos >= 55.0 ? $melhor : '';
    }

    /**
     * @param array{nome:string,textos:list<string>} $folha
     */
    private function tituloDaFolha(array $folha): string
    {
        foreach ($folha['textos'] ?? [] as $texto) {
            $t = trim((string) $texto);
            if (mb_strlen($t) >= 8 && mb_strlen($t) <= 80) {
                return $t;
            }
        }
        return 'Modelo importado — ' . (string) ($folha['nome'] ?? 'folha');
    }

    /**
     * @return list<array{nome:string,textos:list<string>,grade:array<string,mixed>}>
     */
    private function lerFolhas(string $caminho): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($caminho) !== true) {
            throw new \InvalidArgumentException('Não foi possível abrir a planilha.');
        }
        $shared = $this->textosCompartilhados($zip);
        $alvos = $this->caminhosFolhas($zip);
        $folhas = [];
        foreach ($alvos as $item) {
            $xml = $zip->getFromName($item['path']);
            if (!is_string($xml) || $xml === '') {
                continue;
            }
            $lida = $this->interpretarFolha($xml, $shared);
            $folhas[] = [
                'nome' => $item['nome'],
                'textos' => $lida['textos'],
                'grade' => $lida['grade'],
            ];
        }
        $zip->close();
        return $folhas;
    }

    /**
     * @return list<string>
     */
    private function textosCompartilhados(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (!is_string($xml) || $xml === '') {
            return [];
        }
        $sx = @simplexml_load_string($xml);
        if ($sx === false) {
            return [];
        }
        $sx->registerXPathNamespace('m', self::NS);
        $out = [];
        foreach ($sx->xpath('//m:si') ?: [] as $si) {
            $si->registerXPathNamespace('m', self::NS);
            $partes = [];
            foreach ($si->xpath('.//m:t') ?: [] as $t) {
                $partes[] = (string) $t;
            }
            $out[] = implode('', $partes);
        }
        return $out;
    }

    /**
     * @return list<array{nome:string,path:string}>
     */
    private function caminhosFolhas(\ZipArchive $zip): array
    {
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (!is_string($wb) || !is_string($rels)) {
            return [];
        }
        $book = @simplexml_load_string($wb);
        $rel = @simplexml_load_string($rels);
        if ($book === false || $rel === false) {
            return [];
        }
        $mapa = [];
        foreach ($rel->Relationship as $r) {
            $id = (string) $r['Id'];
            $target = (string) $r['Target'];
            if ($id !== '' && $target !== '') {
                $mapa[$id] = 'xl/' . ltrim($target, '/');
            }
        }
        $book->registerXPathNamespace('m', self::NS);
        $book->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $folhas = [];
        foreach ($book->xpath('//m:sheet') ?: [] as $sheet) {
            $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $rid = $attrs ? (string) $attrs['id'] : '';
            $path = $mapa[$rid] ?? '';
            if ($path === '' || !str_contains($path, 'worksheets/')) {
                continue;
            }
            $folhas[] = ['nome' => (string) $sheet['name'], 'path' => $path];
        }
        return $folhas;
    }

    /**
     * @param list<string> $shared
     * @return array{textos:list<string>,grade:array{celulas:array<int,array<int,string>>,mesclas:list<array{0:int,1:int,2:int,3:int}>,maxLinha:int,maxColuna:int,larguras:array<int,float>}}
     */
    private function interpretarFolha(string $xml, array $shared): array
    {
        $vazia = [
            'textos' => [],
            'grade' => [
                'celulas' => [],
                'mesclas' => [],
                'maxLinha' => 0,
                'maxColuna' => 0,
                'larguras' => [],
                'alturas' => [],
            ],
        ];
        $sx = @simplexml_load_string($xml);
        if ($sx === false) {
            return $vazia;
        }
        $sx->registerXPathNamespace('m', self::NS);
        $celulas = [];
        $textos = [];
        $maxLinha = 0;
        $maxColuna = 0;
        foreach ($sx->xpath('//m:sheetData/m:row/m:c') ?: [] as $celula) {
            $coord = self::coordenada((string) $celula['r']);
            if ($coord === null) {
                continue;
            }
            [$linha, $coluna] = $coord;
            $valor = $this->valorCelula($celula, $shared);
            if ($valor === '') {
                continue;
            }
            $celulas[$linha][$coluna] = $valor;
            $textos[] = $valor;
            $maxLinha = max($maxLinha, $linha);
            $maxColuna = max($maxColuna, $coluna);
        }
        $mesclas = [];
        foreach ($sx->xpath('//m:mergeCell') ?: [] as $mescla) {
            $faixa = self::faixa((string) $mescla['ref']);
            if ($faixa === null) {
                continue;
            }
            $mesclas[] = $faixa;
            $maxLinha = max($maxLinha, $faixa[2]);
            $maxColuna = max($maxColuna, $faixa[3]);
        }
        $dim = $sx->xpath('//m:dimension') ?: [];
        if (isset($dim[0])) {
            $faixa = self::faixa((string) $dim[0]['ref']);
            if ($faixa !== null) {
                $maxLinha = max($maxLinha, $faixa[2]);
            }
        }

        return [
            'textos' => $textos,
            'grade' => [
                'celulas' => $celulas,
                'mesclas' => $mesclas,
                'maxLinha' => $maxLinha,
                'maxColuna' => $maxColuna,
                'larguras' => $this->largurasColunas($sx, $maxColuna),
                'alturas' => $this->alturasLinhas($sx, $maxLinha),
            ],
        ];
    }

    /**
     * @param list<string> $shared
     */
    private function valorCelula(\SimpleXMLElement $celula, array $shared): string
    {
        $tipo = (string) $celula['t'];
        $celula->registerXPathNamespace('m', self::NS);
        if ($tipo === 's') {
            $nos = $celula->xpath('./m:v') ?: [];
            $idx = isset($nos[0]) ? (int) $nos[0] : -1;

            return $idx >= 0 ? trim((string) ($shared[$idx] ?? '')) : '';
        }
        if ($tipo === 'inlineStr') {
            $partes = [];
            foreach ($celula->xpath('.//m:t') ?: [] as $t) {
                $partes[] = (string) $t;
            }

            return trim(implode('', $partes));
        }
        $nos = $celula->xpath('./m:v') ?: [];
        if (!isset($nos[0])) {
            return '';
        }
        if ($tipo === 'b') {
            return ((string) $nos[0] === '1') ? '1' : '';
        }

        return trim((string) $nos[0]);
    }

    /**
     * @return array<int,float>
     */
    private function largurasColunas(\SimpleXMLElement $sx, int $maxColuna): array
    {
        $larguras = [];
        $teto = max(1, min($maxColuna, 40));
        foreach ($sx->xpath('//m:cols/m:col') ?: [] as $col) {
            $min = (int) $col['min'];
            $max = min((int) $col['max'], $teto);
            $w = (float) $col['width'];
            if ($min < 1 || $max < $min || $w <= 0) {
                continue;
            }
            for ($i = $min; $i <= $max; $i++) {
                $larguras[$i] = $w;
            }
        }

        return $larguras;
    }

    /**
     * @return array<int,float> altura em pontos por linha (1-indexed)
     */
    private function alturasLinhas(\SimpleXMLElement $sx, int $maxLinha): array
    {
        $padrao = 13.2;
        foreach ($sx->xpath('//m:sheetFormatPr') ?: [] as $fmt) {
            if (isset($fmt['defaultRowHeight'])) {
                $padrao = (float) $fmt['defaultRowHeight'];
            }
        }
        $alturas = [];
        foreach ($sx->xpath('//m:sheetData/m:row') ?: [] as $row) {
            $n = (int) $row['r'];
            if ($n < 1) {
                continue;
            }
            $alturas[$n] = isset($row['ht']) ? (float) $row['ht'] : $padrao;
            $maxLinha = max($maxLinha, $n);
        }
        for ($i = 1; $i <= $maxLinha; $i++) {
            if (!isset($alturas[$i])) {
                $alturas[$i] = $padrao;
            }
        }

        return $alturas;
    }

    /** @return array{0:int,1:int}|null */
    private static function coordenada(string $ref): ?array
    {
        if (!preg_match('/^([A-Z]+)(\d+)$/', strtoupper(trim($ref)), $m)) {
            return null;
        }
        $coluna = 0;
        $letras = $m[1];
        $tam = strlen($letras);
        for ($i = 0; $i < $tam; $i++) {
            $coluna = $coluna * 26 + (ord($letras[$i]) - 64);
        }

        return [(int) $m[2], $coluna];
    }

    /** @return array{0:int,1:int,2:int,3:int}|null */
    private static function faixa(string $ref): ?array
    {
        $partes = explode(':', $ref);
        $ini = self::coordenada($partes[0] ?? '');
        if ($ini === null) {
            return null;
        }
        $fim = isset($partes[1]) ? self::coordenada($partes[1]) : $ini;
        if ($fim === null) {
            return null;
        }

        return [$ini[0], $ini[1], $fim[0], $fim[1]];
    }

    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $mapa = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'õ' => 'o', 'ô' => 'o',
            'ú' => 'u',
            'ç' => 'c',
            'º' => 'o', 'ª' => 'a',
        ];
        $texto = strtr($texto, $mapa);
        $texto = preg_replace('/[^a-z0-9%\/ ]+/', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;
        return trim($texto);
    }
}
