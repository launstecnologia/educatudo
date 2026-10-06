<?php
/**
 * Planilha (xlsx) e texto de apoio à digitação.
 * Células saem como texto para o Excel não cortar zero à esquerda.
 */
class PlanilhaTextoDocumento
{
    public static function zipDisponivel(): bool
    {
        return class_exists('ZipArchive');
    }

    /**
     * @param list<string> $colunas
     * @param list<list<string>> $linhas
     * @param list<string> $orientacao
     */
    public static function gravarPlanilha(string $caminho, array $colunas, array $linhas, array $orientacao): string
    {
        $dir = dirname($caminho);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar a pasta da planilha.');
        }
        if (self::zipDisponivel()) {
            self::gravarXlsx($caminho, $colunas, $linhas, $orientacao);
            return $caminho;
        }
        $csv = preg_replace('/\.xlsx$/', '.csv', $caminho) ?? ($caminho . '.csv');
        self::gravarCsv($csv, $colunas, $linhas);
        return $csv;
    }

    /**
     * @param list<string> $linhas
     */
    public static function gravarTexto(string $caminho, array $linhas): void
    {
        $dir = dirname($caminho);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar a pasta do texto.');
        }
        $conteudo = implode("\n", $linhas) . "\n";
        if (file_put_contents($caminho, $conteudo) === false) {
            throw new RuntimeException('Não foi possível gravar o TXT.');
        }
    }

    /**
     * @param list<string> $colunas
     * @param list<list<string>> $linhas
     */
    private static function gravarCsv(string $caminho, array $colunas, array $linhas): void
    {
        $out = fopen($caminho, 'wb');
        if ($out === false) {
            throw new RuntimeException('Não foi possível gravar a planilha.');
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $colunas, ';');
        foreach ($linhas as $linha) {
            $celulas = [];
            foreach ($linha as $celula) {
                $celulas[] = self::csvTexto((string) $celula);
            }
            fputcsv($out, $celulas, ';');
        }
        fclose($out);
    }

    private static function csvTexto(string $valor): string
    {
        if ($valor !== '' && preg_match('/^\d+$/', $valor)) {
            return '="' . str_replace('"', '""', $valor) . '"';
        }
        return $valor;
    }

    /**
     * @param list<string> $colunas
     * @param list<list<string>> $linhas
     * @param list<string> $orientacao
     */
    private static function gravarXlsx(string $caminho, array $colunas, array $linhas, array $orientacao): void
    {
        $zip = new ZipArchive();
        if ($zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar a planilha.');
        }
        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::relsRaiz());
        $zip->addFromString('xl/workbook.xml', self::workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::relsWorkbook());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::planilha($colunas, $linhas, true));
        $orientacaoLinhas = [];
        foreach ($orientacao as $texto) {
            $orientacaoLinhas[] = [(string) $texto];
        }
        $zip->addFromString('xl/worksheets/sheet2.xml', self::planilha(['Orientação'], $orientacaoLinhas, false));
        $zip->close();
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
    }

    private static function relsRaiz(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="Digitação" sheetId="1" r:id="rId1"/>'
            . '<sheet name="Orientação" sheetId="2" r:id="rId2"/>'
            . '</sheets></workbook>';
    }

    private static function relsWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            . '</Relationships>';
    }

    /**
     * @param list<string> $colunas
     * @param list<list<string>> $linhas
     */
    private static function planilha(array $colunas, array $linhas, bool $congelarCabecalho): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if ($congelarCabecalho) {
            $xml .= '<sheetViews><sheetView workbookViewId="0">'
                . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
                . '</sheetView></sheetViews>';
        }
        $xml .= '<cols>';
        $larguras = self::larguras($colunas, $linhas);
        foreach ($larguras as $i => $largura) {
            $n = $i + 1;
            $xml .= '<col min="' . $n . '" max="' . $n . '" width="' . $largura . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';
        $xml .= self::linhaXml(1, $colunas);
        $numero = 2;
        foreach ($linhas as $linha) {
            $xml .= self::linhaXml($numero, $linha);
            $numero++;
        }
        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    /**
     * @param list<string> $celulas
     */
    private static function linhaXml(int $numero, array $celulas): string
    {
        $xml = '<row r="' . $numero . '">';
        foreach (array_values($celulas) as $i => $celula) {
            $ref = self::coluna($i) . $numero;
            $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                . self::xml((string) $celula)
                . '</t></is></c>';
        }
        return $xml . '</row>';
    }

    private static function coluna(int $indice): string
    {
        $indice++;
        $letras = '';
        while ($indice > 0) {
            $indice--;
            $letras = chr(65 + ($indice % 26)) . $letras;
            $indice = intdiv($indice, 26);
        }
        return $letras;
    }

    /**
     * @param list<string> $colunas
     * @param list<list<string>> $linhas
     * @return list<int>
     */
    private static function larguras(array $colunas, array $linhas): array
    {
        $larguras = [];
        foreach ($colunas as $i => $coluna) {
            $larguras[$i] = min(42, max(14, mb_strlen($coluna) + 2));
        }
        $amostra = 0;
        foreach ($linhas as $linha) {
            foreach ($linha as $i => $celula) {
                $tamanho = mb_strlen((string) $celula) + 2;
                if ($tamanho > ($larguras[$i] ?? 14)) {
                    $larguras[$i] = min(42, $tamanho);
                }
            }
            $amostra++;
            if ($amostra >= 40) {
                break;
            }
        }
        ksort($larguras);
        return array_values($larguras);
    }

    private static function xml(string $valor): string
    {
        $valor = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $valor) ?? '';
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
