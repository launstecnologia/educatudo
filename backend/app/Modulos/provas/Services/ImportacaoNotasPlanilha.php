<?php

namespace App\Modulos\Provas\Services;

/**
 * Lê uma planilha de notas (CSV ou Excel) com código do aluno, disciplina e nota
 * e cruza com os alunos já convocados no evento.
 */
class ImportacaoNotasPlanilha
{
    private const MAX_BYTES = 2000000;
    private const MAX_LINHAS = 8000;

    /**
     * @param array<string,mixed> $arquivo
     * @return list<array{linha:int,codigo:string,disciplina:string,nota:string}>
     */
    public function lerArquivo(array $arquivo): array
    {
        $erro = (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erro === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException('Selecione a planilha para importar.');
        }
        if ($erro !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Não foi possível receber a planilha. Tente de novo.');
        }
        $tmp = (string) ($arquivo['tmp_name'] ?? '');
        $nome = (string) ($arquivo['name'] ?? '');
        $tamanho = (int) ($arquivo['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('Arquivo de planilha inválido.');
        }
        if ($tamanho <= 0 || $tamanho > self::MAX_BYTES) {
            throw new \RuntimeException('A planilha precisa ter até 2 MB.');
        }
        $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'], true)) {
            throw new \RuntimeException('Use um arquivo .csv ou .xlsx. No Excel, salve como CSV ou como pasta de trabalho do Excel.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $mime = is_string($mime) ? $mime : '';
        $mimesCsv = ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel', 'application/octet-stream'];
        $mimesXlsx = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'];
        if ($ext === 'csv' && !in_array($mime, $mimesCsv, true)) {
            throw new \RuntimeException('O arquivo não parece um CSV.');
        }
        if ($ext === 'xlsx' && !in_array($mime, $mimesXlsx, true)) {
            throw new \RuntimeException('O arquivo não parece uma planilha do Excel.');
        }

        $tabela = $ext === 'xlsx' ? $this->lerXlsx($tmp) : $this->lerCsv($tmp);

        return $this->tabelaParaLinhas($tabela);
    }

    /**
     * @param list<array{linha:int,codigo:string,disciplina:string,nota:string}> $linhas
     * @param list<array<string,mixed>> $elegiveis
     * @return array{aplicar:list<array<string,mixed>>,erros:list<string>,ignoradas:int}
     */
    public function cruzar(array $linhas, array $elegiveis, bool $notaUnica): array
    {
        $porAluno = [];
        $porCodigo = [];
        $ambiguos = [];
        $materiasPorNorma = [];
        foreach ($elegiveis as $row) {
            if (!is_array($row)) {
                continue;
            }
            $aid = (int) ($row['aluno_id'] ?? 0);
            if ($aid <= 0) {
                continue;
            }
            $porAluno[$aid][] = $row;
            foreach (['codigo_aluno', 'ra'] as $campo) {
                $cod = $this->normalizarCodigo((string) ($row[$campo] ?? ''));
                if ($cod === '') {
                    continue;
                }
                if (isset($porCodigo[$cod]) && $porCodigo[$cod] !== $aid) {
                    $ambiguos[$cod] = true;
                } else {
                    $porCodigo[$cod] = $aid;
                }
            }
            $norma = $this->normalizarTexto((string) ($row['materia_nome'] ?? ''));
            if ($norma !== '') {
                $materiasPorNorma[$norma] = true;
            }
        }

        $erros = [];
        $ignoradas = 0;
        $vistos = [];
        foreach ($linhas as $linha) {
            $n = (int) ($linha['linha'] ?? 0);
            $cod = $this->normalizarCodigo((string) ($linha['codigo'] ?? ''));
            $notaBruta = trim(str_replace(',', '.', (string) ($linha['nota'] ?? '')));
            $disciplinaBruta = trim((string) ($linha['disciplina'] ?? ''));
            if ($cod === '' && $notaBruta === '' && $disciplinaBruta === '') {
                continue;
            }
            if ($cod === '') {
                $erros[] = 'Linha ' . $n . ': informe o código do aluno.';
                continue;
            }
            if ($notaBruta === '') {
                $ignoradas++;
                continue;
            }
            if (!is_numeric($notaBruta)) {
                $erros[] = 'Linha ' . $n . ': nota inválida.';
                continue;
            }
            $nota = round((float) $notaBruta, 2);
            if ($nota < 0 || $nota > 10) {
                $erros[] = 'Linha ' . $n . ': a nota deve estar entre 0 e 10.';
                continue;
            }
            if (!isset($porCodigo[$cod]) || isset($ambiguos[$cod])) {
                $erros[] = isset($ambiguos[$cod])
                    ? 'Linha ' . $n . ': o código ' . $this->recortar((string) ($linha['codigo'] ?? '')) . ' aparece em mais de um aluno.'
                    : 'Linha ' . $n . ': o código ' . $this->recortar((string) ($linha['codigo'] ?? '')) . ' não está neste evento.';
                continue;
            }
            $aid = $porCodigo[$cod];
            $candidatos = $porAluno[$aid] ?? [];
            if (!$notaUnica) {
                $disciplina = $this->normalizarTexto($disciplinaBruta);
                if ($disciplina === '') {
                    $erros[] = 'Linha ' . $n . ': informe a disciplina.';
                    continue;
                }
                if (!isset($materiasPorNorma[$disciplina])) {
                    $erros[] = 'Linha ' . $n . ': a disciplina não faz parte deste evento.';
                    continue;
                }
                $candidatos = array_values(array_filter(
                    $candidatos,
                    function (array $row) use ($disciplina): bool {
                        return $this->normalizarTexto((string) ($row['materia_nome'] ?? '')) === $disciplina;
                    }
                ));
            }
            $ativos = array_values(array_filter(
                $candidatos,
                static function (array $row): bool {
                    return empty($row['transferido']);
                }
            ));
            if ($ativos === []) {
                $erros[] = $candidatos === []
                    ? 'Linha ' . $n . ': este aluno não está nessa disciplina no evento.'
                    : 'Linha ' . $n . ': aluno transferido, a nota não foi lançada.';
                continue;
            }
            foreach ($ativos as $row) {
                $chave = (int) ($row['professor_id'] ?? 0) . '_'
                    . (int) ($row['materia_id'] ?? 0) . '_'
                    . (int) ($row['turma_id'] ?? 0) . '_'
                    . $aid;
                $vistos[$chave] = [
                    'professor_id' => (int) ($row['professor_id'] ?? 0),
                    'materia_id' => (int) ($row['materia_id'] ?? 0),
                    'turma_id' => (int) ($row['turma_id'] ?? 0),
                    'aluno_id' => $aid,
                    'nota' => $nota,
                    'linha' => $n,
                ];
            }
        }

        return [
            'aplicar' => array_values($vistos),
            'erros' => $erros,
            'ignoradas' => $ignoradas,
        ];
    }

    /**
     * @return list<list<string>>
     */
    private function lerCsv(string $caminho): array
    {
        $handle = fopen($caminho, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Não foi possível ler o CSV.');
        }
        $primeira = fgets($handle);
        if ($primeira === false) {
            fclose($handle);
            throw new \RuntimeException('A planilha está vazia.');
        }
        if (str_starts_with($primeira, "\xEF\xBB\xBF")) {
            $primeira = substr($primeira, 3);
        }
        $delim = substr_count($primeira, ';') >= substr_count($primeira, ',') ? ';' : ',';
        rewind($handle);
        $tabela = [];
        $n = 0;
        while (($row = fgetcsv($handle, 0, $delim)) !== false) {
            $n++;
            if ($n > self::MAX_LINHAS + 1) {
                fclose($handle);
                throw new \RuntimeException('A planilha passa de ' . self::MAX_LINHAS . ' linhas.');
            }
            if ($n === 1 && isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]) ?? (string) $row[0];
            }
            $tabela[] = array_map(static function ($celula): string {
                return trim((string) $celula);
            }, $row);
        }
        fclose($handle);

        return $tabela;
    }

    /**
     * @return list<list<string>>
     */
    private function lerXlsx(string $caminho): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Este servidor não abre Excel. Salve a planilha como CSV.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($caminho) !== true) {
            throw new \RuntimeException('Não foi possível abrir a planilha.');
        }
        $compartilhadas = $this->textosCompartilhados($zip->getFromName('xl/sharedStrings.xml') ?: '');
        $aba = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (!is_string($aba) || $aba === '') {
            throw new \RuntimeException('A planilha não tem uma primeira aba.');
        }

        $dom = new \DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $carregou = $dom->loadXML($aba, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        if ($carregou !== true) {
            throw new \RuntimeException('Não foi possível ler a primeira aba da planilha.');
        }
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $linhasXml = $xp->query('//m:sheetData/m:row');
        if ($linhasXml === false || $linhasXml->length === 0) {
            $linhasXml = $xp->query('//sheetData/row');
        }
        if ($linhasXml === false) {
            throw new \RuntimeException('A planilha está vazia.');
        }

        $tabela = [];
        foreach ($linhasXml as $linhaXml) {
            if (!$linhaXml instanceof \DOMElement) {
                continue;
            }
            if (count($tabela) >= self::MAX_LINHAS + 1) {
                throw new \RuntimeException('A planilha passa de ' . self::MAX_LINHAS . ' linhas.');
            }
            $celulas = [];
            foreach ($linhaXml->getElementsByTagName('c') as $celula) {
                if (!$celula instanceof \DOMElement) {
                    continue;
                }
                $ref = (string) $celula->getAttribute('r');
                $col = $this->indiceColuna($ref);
                $tipo = (string) $celula->getAttribute('t');
                $valor = '';
                if ($tipo === 'inlineStr') {
                    $valor = $celula->textContent ?? '';
                } else {
                    $nos = $celula->getElementsByTagName('v');
                    $bruto = $nos->length > 0 ? (string) $nos->item(0)?->textContent : '';
                    if ($tipo === 's') {
                        $valor = $compartilhadas[(int) $bruto] ?? '';
                    } else {
                        $valor = $bruto;
                    }
                }
                $celulas[$col] = trim($valor);
            }
            if ($celulas === []) {
                $tabela[] = [];
                continue;
            }
            $max = max(array_keys($celulas));
            $row = [];
            for ($i = 0; $i <= $max; $i++) {
                $row[] = $celulas[$i] ?? '';
            }
            $tabela[] = $row;
        }

        return $tabela;
    }

    /**
     * @return list<string>
     */
    private function textosCompartilhados(string $xml): array
    {
        if ($xml === '') {
            return [];
        }
        $dom = new \DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $carregou = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        if ($carregou !== true) {
            return [];
        }
        $saida = [];
        foreach ($dom->getElementsByTagName('si') as $si) {
            $texto = '';
            foreach ($si->getElementsByTagName('t') as $t) {
                $texto .= $t->textContent;
            }
            $saida[] = $texto;
        }

        return $saida;
    }

    private function indiceColuna(string $ref): int
    {
        $letras = preg_replace('/[^A-Z]/', '', strtoupper($ref)) ?? '';
        $n = 0;
        $len = strlen($letras);
        for ($i = 0; $i < $len; $i++) {
            $n = ($n * 26) + (ord($letras[$i]) - 64);
        }

        return max(0, $n - 1);
    }

    /**
     * @param list<list<string>> $tabela
     * @return list<array{linha:int,codigo:string,disciplina:string,nota:string}>
     */
    private function tabelaParaLinhas(array $tabela): array
    {
        $tabela = array_values(array_filter($tabela, static function (array $row): bool {
            foreach ($row as $celula) {
                if (trim((string) $celula) !== '') {
                    return true;
                }
            }

            return false;
        }));
        if ($tabela === []) {
            throw new \RuntimeException('A planilha está vazia.');
        }
        $mapa = $this->mapaColunas($tabela[0]);
        $inicio = 0;
        if ($mapa !== null) {
            $inicio = 1;
        } else {
            $mapa = ['codigo' => 0, 'disciplina' => 1, 'nota' => 2];
        }
        $saida = [];
        for ($i = $inicio; $i < count($tabela); $i++) {
            if (count($saida) >= self::MAX_LINHAS) {
                throw new \RuntimeException('A planilha passa de ' . self::MAX_LINHAS . ' linhas.');
            }
            $row = $tabela[$i];
            $saida[] = [
                'linha' => $i + 1,
                'codigo' => trim((string) ($row[$mapa['codigo']] ?? '')),
                'disciplina' => trim((string) ($row[$mapa['disciplina']] ?? '')),
                'nota' => trim((string) ($row[$mapa['nota']] ?? '')),
            ];
        }
        if ($saida === []) {
            throw new \RuntimeException('A planilha não tem linhas de nota.');
        }

        return $saida;
    }

    /**
     * @param list<string> $cabecalho
     * @return array{codigo:int,disciplina:int,nota:int}|null
     */
    private function mapaColunas(array $cabecalho): ?array
    {
        $aliases = [
            'codigo' => ['codigo', 'codigo do aluno', 'codigo_aluno', 'cod', 'ra', 'matricula'],
            'disciplina' => ['disciplina', 'materia', 'componente', 'componente curricular'],
            'nota' => ['nota', 'nota final', 'valor'],
        ];
        $achou = [];
        foreach ($cabecalho as $i => $celula) {
            $norma = $this->normalizarTexto((string) $celula);
            foreach ($aliases as $chave => $opcoes) {
                if (isset($achou[$chave])) {
                    continue;
                }
                if (in_array($norma, $opcoes, true)) {
                    $achou[$chave] = (int) $i;
                }
            }
        }
        if (!isset($achou['codigo'], $achou['nota'])) {
            return null;
        }
        if (!isset($achou['disciplina'])) {
            $achou['disciplina'] = -1;
        }

        return [
            'codigo' => $achou['codigo'],
            'disciplina' => $achou['disciplina'],
            'nota' => $achou['nota'],
        ];
    }

    private function normalizarCodigo(string $codigo): string
    {
        $codigo = trim($codigo);
        if (preg_match('/^(\d+)[.,]0+$/', $codigo, $m) === 1) {
            $codigo = $m[1];
        }
        $codigo = mb_strtoupper($codigo, 'UTF-8');
        $codigo = preg_replace('/\s+/', '', $codigo) ?? $codigo;

        return $codigo;
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = trim(mb_strtolower($texto, 'UTF-8'));
        $de = ['á', 'à', 'â', 'ã', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'õ', 'ö', 'ú', 'ù', 'û', 'ü', 'ç', 'ñ'];
        $para = ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c', 'n'];
        $texto = str_replace($de, $para, $texto);
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return $texto;
    }

    private function recortar(string $texto): string
    {
        $texto = trim($texto);
        if (mb_strlen($texto) > 40) {
            return mb_substr($texto, 0, 40) . '…';
        }

        return $texto;
    }
}
