<?php

namespace App\Painel\Admin\Services;

/**
 * Gera o Excel e o PDF do relatório completo a partir das abas já montadas.
 */
class RelatorioCompletoArquivo
{
    private const PDF_LINHAS = 500;

    /**
     * @param array{titulo:string,escola:string,gerado_em:string,abas:list<array{nome:string,colunas:list<string>,linhas:list<list<string>>,aviso:string}>} $relatorio
     */
    public function excel(array $relatorio): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('O servidor não tem suporte para gerar Excel.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'relatorio');
        if ($tmp === false) {
            throw new \RuntimeException('Não foi possível preparar o arquivo Excel.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Não foi possível preparar o arquivo Excel.');
        }

        $abas = array_values($relatorio['abas'] ?? []);
        if ($abas === []) {
            $abas[] = ['nome' => 'Relatorio', 'colunas' => ['Aviso'], 'linhas' => [['Nenhum dado.']], 'aviso' => ''];
        }

        $sheets = '';
        $rels = '';
        $overrides = '';
        $nomes = [];
        foreach ($abas as $i => $aba) {
            $id = $i + 1;
            $zip->addFromString('xl/worksheets/sheet' . $id . '.xml', $this->planilha($aba));
            $nome = $this->nomeAba((string) ($aba['nome'] ?? ('Aba ' . $id)), $nomes);
            $sheets .= '<sheet name="' . $this->xml($nome) . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
            $rels .= '<Relationship Id="rId' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $id . '.xml"/>';
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $id . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $rels .= '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheets . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>');
        $zip->addFromString('xl/styles.xml', $this->estilos());
        $zip->close();

        $bin = file_get_contents($tmp);
        @unlink($tmp);
        if ($bin === false || $bin === '') {
            throw new \RuntimeException('Não foi possível finalizar o Excel.');
        }

        return $bin;
    }

    /**
     * @param array{titulo:string,escola:string,gerado_em:string,abas:list<array{nome:string,colunas:list<string>,linhas:list<list<string>>,aviso:string}>} $relatorio
     */
    public function pdf(array $relatorio): string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new \RuntimeException('O servidor não tem suporte para gerar PDF.');
        }

        $titulo = $this->html((string) ($relatorio['titulo'] ?? 'Relatório completo'));
        $escola = $this->html((string) ($relatorio['escola'] ?? ''));
        $quando = $this->html((string) ($relatorio['gerado_em'] ?? ''));
        $corpo = '';
        foreach (array_values($relatorio['abas'] ?? []) as $aba) {
            $colunas = array_values($aba['colunas'] ?? []);
            $linhas = array_values($aba['linhas'] ?? []);
            $aviso = trim((string) ($aba['aviso'] ?? ''));
            $cortadas = 0;
            if (count($linhas) > self::PDF_LINHAS) {
                $cortadas = count($linhas) - self::PDF_LINHAS;
                $linhas = array_slice($linhas, 0, self::PDF_LINHAS);
            }
            $corpo .= '<h2>' . $this->html((string) ($aba['nome'] ?? 'Banco')) . '</h2>';
            if ($aviso !== '') {
                $corpo .= '<p class="aviso">' . $this->html($aviso) . '</p>';
            }
            if ($cortadas > 0) {
                $corpo .= '<p class="aviso">O PDF mostra as primeiras ' . self::PDF_LINHAS . ' linhas. O Excel traz as outras ' . $cortadas . '.</p>';
            }
            if ($colunas === []) {
                $corpo .= '<p>Nenhum dado.</p>';
                continue;
            }
            $corpo .= '<table><thead><tr>';
            foreach ($colunas as $coluna) {
                $corpo .= '<th>' . $this->html((string) $coluna) . '</th>';
            }
            $corpo .= '</tr></thead><tbody>';
            if ($linhas === []) {
                $corpo .= '<tr><td colspan="' . count($colunas) . '">Nenhum registro neste recorte.</td></tr>';
            }
            foreach ($linhas as $linha) {
                $corpo .= '<tr>';
                foreach ($colunas as $i => $_coluna) {
                    $corpo .= '<td>' . $this->html((string) ($linha[$i] ?? '')) . '</td>';
                }
                $corpo .= '</tr>';
            }
            $corpo .= '</tbody></table>';
        }

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            . 'body{font-family:DejaVu Sans,sans-serif;font-size:8px;color:#111;}'
            . 'h1{font-size:14px;margin:0 0 2px;}h2{font-size:11px;margin:14px 0 4px;}'
            . '.meta{color:#444;margin:0 0 8px;}.aviso{color:#92400e;margin:0 0 4px;}'
            . 'table{width:100%;border-collapse:collapse;margin:0 0 6px;page-break-inside:auto;}'
            . 'th,td{border:1px solid #d1d5db;padding:2px 3px;vertical-align:top;word-wrap:break-word;}'
            . 'th{background:#f3f4f6;font-weight:bold;}'
            . 'tr{page-break-inside:avoid;}'
            . '</style></head><body>'
            . '<h1>' . $titulo . '</h1>'
            . '<p class="meta">' . $escola . ($escola !== '' ? ' · ' : '') . 'Gerado em ' . $quando . '</p>'
            . $corpo
            . '</body></html>';

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $bin = $dompdf->output();
        if (!is_string($bin) || $bin === '') {
            throw new \RuntimeException('Não foi possível finalizar o PDF.');
        }

        return $bin;
    }

    /**
     * @param array{nome?:string,colunas?:list<string>,linhas?:list<list<string>>,aviso?:string} $aba
     */
    private function planilha(array $aba): string
    {
        $colunas = array_values($aba['colunas'] ?? []);
        if ($colunas === []) {
            $colunas = ['Aviso'];
        }
        $linhas = array_values($aba['linhas'] ?? []);
        $aviso = trim((string) ($aba['aviso'] ?? ''));
        if ($aviso !== '') {
            array_unshift($linhas, array_merge([$aviso], array_fill(0, max(0, count($colunas) - 1), '')));
        }
        if ($linhas === []) {
            $linhas[] = array_merge(['Nenhum registro neste recorte.'], array_fill(0, max(0, count($colunas) - 1), ''));
        }

        $totalColunas = count($colunas);
        $ultima = $this->letra($totalColunas - 1);
        $ultimaLinha = count($linhas) + 1;
        $xmlLinhas = [];
        $cabecalho = '';
        foreach ($colunas as $c => $titulo) {
            $cabecalho .= $this->celula($this->letra($c) . '1', (string) $titulo, 1);
        }
        $xmlLinhas[] = '<row r="1">' . $cabecalho . '</row>';
        foreach ($linhas as $i => $linha) {
            $n = $i + 2;
            $cells = '';
            foreach ($colunas as $c => $_titulo) {
                $cells .= $this->celula($this->letra($c) . $n, (string) ($linha[$c] ?? ''), 0);
            }
            $xmlLinhas[] = '<row r="' . $n . '">' . $cells . '</row>';
        }

        $larguras = '';
        foreach ($colunas as $c => $titulo) {
            $largura = max(12, min(42, mb_strlen((string) $titulo) + 4));
            $min = $c + 1;
            $larguras .= '<col min="' . $min . '" max="' . $min . '" width="' . $largura . '" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $ultima . $ultimaLinha . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $larguras . '</cols>'
            . '<sheetData>' . implode('', $xmlLinhas) . '</sheetData>'
            . '</worksheet>';
    }

    private function celula(string $ref, string $valor, int $estilo): string
    {
        $valor = str_replace(["\r\n", "\r"], "\n", $valor);
        if (mb_strlen($valor) > 4000) {
            $valor = mb_substr($valor, 0, 4000);
        }
        if (preg_match('/^[=+\-@]/', $valor) === 1) {
            $valor = "'" . $valor;
        }
        if ($estilo === 0 && preg_match('/^-?\d+(\.\d+)?$/', $valor) === 1) {
            return '<c r="' . $ref . '" s="0"><v>' . $valor . '</v></c>';
        }
        $preserva = $valor !== trim($valor) || strpos($valor, "\n") !== false;
        return '<c r="' . $ref . '" t="inlineStr" s="' . $estilo . '"><is><t'
            . ($preserva ? ' xml:space="preserve"' : '') . '>' . $this->xml($valor) . '</t></is></c>';
    }

    private function estilos(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            . '</cellXfs></styleSheet>';
    }

    private function letra(int $index): string
    {
        $nome = '';
        $index++;
        while ($index > 0) {
            $index--;
            $nome = chr(65 + ($index % 26)) . $nome;
            $index = intdiv($index, 26);
        }

        return $nome;
    }

    /**
     * @param array<string,bool> $usados
     */
    private function nomeAba(string $nome, array &$usados): string
    {
        $nome = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $nome);
        $nome = trim(preg_replace('/\s+/', ' ', $nome) ?? $nome);
        if ($nome === '') {
            $nome = 'Aba';
        }
        $base = mb_substr($nome, 0, 31);
        $candidato = $base;
        $n = 2;
        while (isset($usados[$candidato])) {
            $sufixo = ' ' . $n;
            $candidato = mb_substr($base, 0, 31 - mb_strlen($sufixo)) . $sufixo;
            $n++;
        }
        $usados[$candidato] = true;

        return $candidato;
    }

    private function xml(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function html(string $valor): string
    {
        return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
    }
}
