<?php

namespace App\Modulos\Provas\Services;

use App\Helpers\AlunoLancamentoNotaHelper;

/**
 * Lê planilha/documento de notas (CSV, Excel ou Word) e cruza com os alunos
 * já convocados no evento. Aceita código/RA ou nome do aluno.
 */
class ImportacaoNotasPlanilha
{
    private const MAX_BYTES = 2000000;
    private const MAX_XML_BYTES = 10000000;
    private const MAX_LINHAS = 8000;
    private const MAX_ARQUIVOS = 30;

    /**
     * Normaliza $_FILES (um arquivo ou planilha[]).
     *
     * @param array<string,mixed> $files
     * @return list<array<string,mixed>>
     */
    public static function separarUploads(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return [$files];
        }
        $saida = [];
        $total = count($files['name']);
        for ($i = 0; $i < $total; $i++) {
            $erro = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($erro === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $saida[] = [
                'name' => (string) ($files['name'][$i] ?? ''),
                'type' => (string) ($files['type'][$i] ?? ''),
                'tmp_name' => (string) ($files['tmp_name'][$i] ?? ''),
                'error' => $erro,
                'size' => (int) ($files['size'][$i] ?? 0),
            ];
        }

        return $saida;
    }

    /**
     * Lê um ou vários arquivos e devolve as linhas juntas.
     *
     * @param array<string,mixed> $filesCampo campo $_FILES['planilha']
     * @return list<array{linha:int,codigo:string,nome:string,disciplina:string,nota:string,arquivo?:string}>
     */
    public function lerArquivos(array $filesCampo): array
    {
        $lista = self::separarUploads($filesCampo);
        if ($lista === []) {
            throw new \RuntimeException('Selecione ao menos um arquivo para importar.');
        }
        if (count($lista) > self::MAX_ARQUIVOS) {
            throw new \RuntimeException('Envie no máximo ' . self::MAX_ARQUIVOS . ' arquivos por vez.');
        }
        $todas = [];
        foreach ($lista as $arquivo) {
            $nomeArq = basename((string) ($arquivo['name'] ?? 'arquivo'));
            try {
                $linhas = $this->lerArquivo($arquivo);
            } catch (\RuntimeException $e) {
                throw new \RuntimeException($nomeArq . ': ' . $e->getMessage(), 0, $e);
            }
            foreach ($linhas as $linha) {
                $linha['arquivo'] = $nomeArq;
                $todas[] = $linha;
            }
        }
        if ($todas === []) {
            throw new \RuntimeException('Nenhuma linha de nota encontrada nos arquivos.');
        }

        return $todas;
    }

    /**
     * @param array<string,mixed> $arquivo
     * @return list<array{linha:int,codigo:string,nome:string,disciplina:string,nota:string}>
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
        if (!in_array($ext, ['csv', 'xlsx', 'docx'], true)) {
            throw new \RuntimeException('Use um arquivo .csv, .xlsx ou .docx (Word).');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $mime = is_string($mime) ? $mime : '';
        $mimesCsv = ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel', 'application/octet-stream'];
        $mimesXlsx = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'];
        $mimesDocx = [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ];
        if ($ext === 'csv' && !in_array($mime, $mimesCsv, true)) {
            throw new \RuntimeException('O arquivo não parece um CSV.');
        }
        if ($ext === 'xlsx' && !in_array($mime, $mimesXlsx, true)) {
            throw new \RuntimeException('O arquivo não parece uma planilha do Excel.');
        }
        if ($ext === 'docx' && !in_array($mime, $mimesDocx, true)) {
            throw new \RuntimeException('O arquivo não parece um documento do Word.');
        }

        if ($ext === 'xlsx') {
            $tabela = $this->lerXlsx($tmp);
        } elseif ($ext === 'docx') {
            $tabela = $this->lerDocx($tmp);
        } else {
            $tabela = $this->lerCsv($tmp);
        }

        return $this->tabelaParaLinhas($tabela);
    }

    /**
     * @param list<array{linha:int,codigo:string,nome:string,disciplina:string,nota:string,arquivo?:string}> $linhas
     * @param list<array<string,mixed>> $elegiveis
     * @return array{
     *   aplicar:list<array<string,mixed>>,
     *   erros:list<string>,
     *   ignoradas:int,
     *   nao_encontrados:int,
     *   arquivos_ok:array<string,int>,
     *   arquivos_erro:array<string,int>
     * }
     */
    public function cruzar(array $linhas, array $elegiveis, bool $notaUnica, float $escalaMax = 10.0): array
    {
        $max = $escalaMax > 0 ? min(100.0, $escalaMax) : 10.0;
        $maxRotulo = (string) (int) $max;
        $porAluno = [];
        $porCodigo = [];
        $ambiguosCodigo = [];
        $porNome = [];
        $ambiguosNome = [];
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
                    $ambiguosCodigo[$cod] = true;
                } else {
                    $porCodigo[$cod] = $aid;
                }
            }
            $nomeNorma = $this->normalizarNomeAluno((string) ($row['aluno_nome'] ?? ''));
            if ($nomeNorma !== '') {
                if (isset($porNome[$nomeNorma]) && $porNome[$nomeNorma] !== $aid) {
                    $ambiguosNome[$nomeNorma] = true;
                } else {
                    $porNome[$nomeNorma] = $aid;
                }
            }
            $norma = $this->normalizarTexto((string) ($row['materia_nome'] ?? ''));
            if ($norma !== '') {
                $materiasPorNorma[$norma] = true;
            }
        }

        $erros = [];
        $ignoradas = 0;
        $naoEncontrados = 0;
        $arquivosOk = [];
        $arquivosErro = [];
        $vistos = [];
        foreach ($linhas as $linha) {
            $n = (int) ($linha['linha'] ?? 0);
            $rotulo = $this->rotuloLinha($linha);
            $arquivoChave = trim((string) ($linha['arquivo'] ?? ''));
            if ($arquivoChave === '') {
                $arquivoChave = '(sem arquivo)';
            }
            $cod = $this->normalizarCodigo((string) ($linha['codigo'] ?? ''));
            $nomeLinha = $this->normalizarNomeAluno((string) ($linha['nome'] ?? ''));
            $notaBruta = trim(str_replace(',', '.', (string) ($linha['nota'] ?? '')));
            $disciplinaBruta = trim((string) ($linha['disciplina'] ?? ''));
            if ($cod === '' && $nomeLinha === '' && $notaBruta === '' && $disciplinaBruta === '') {
                continue;
            }
            if ($cod === '' && $nomeLinha === '') {
                $erros[] = $rotulo . ': informe o código ou o nome do aluno.';
                $arquivosErro[$arquivoChave] = ($arquivosErro[$arquivoChave] ?? 0) + 1;
                continue;
            }
            if ($notaBruta === '' || $this->notaVaziaOuMarcador($notaBruta)) {
                $ignoradas++;
                continue;
            }
            if (!is_numeric($notaBruta)) {
                $erros[] = $rotulo . ': nota inválida.';
                $arquivosErro[$arquivoChave] = ($arquivosErro[$arquivoChave] ?? 0) + 1;
                continue;
            }
            $nota = round((float) $notaBruta, 2);
            if ($nota < 0 || $nota > $max) {
                $erros[] = $rotulo . ': a nota deve estar entre 0 e ' . $maxRotulo . '.';
                $arquivosErro[$arquivoChave] = ($arquivosErro[$arquivoChave] ?? 0) + 1;
                continue;
            }

            $aid = 0;
            if ($cod !== '') {
                if (!isset($porCodigo[$cod]) || isset($ambiguosCodigo[$cod])) {
                    $erros[] = isset($ambiguosCodigo[$cod])
                        ? $rotulo . ': o código ' . $this->recortar((string) ($linha['codigo'] ?? '')) . ' aparece em mais de um aluno.'
                        : $rotulo . ': o código ' . $this->recortar((string) ($linha['codigo'] ?? '')) . ' não está neste evento.';
                    if (!isset($ambiguosCodigo[$cod])) {
                        $naoEncontrados++;
                    }
                    $arquivosErro[$arquivoChave] = ($arquivosErro[$arquivoChave] ?? 0) + 1;
                    continue;
                }
                $aid = $porCodigo[$cod];
            } else {
                if (!isset($porNome[$nomeLinha]) || isset($ambiguosNome[$nomeLinha])) {
                    $erros[] = isset($ambiguosNome[$nomeLinha])
                        ? $rotulo . ': o nome ' . $this->recortar((string) ($linha['nome'] ?? '')) . ' aparece em mais de um aluno neste evento.'
                        : $rotulo . ': o nome ' . $this->recortar((string) ($linha['nome'] ?? '')) . ' não está neste evento.';
                    if (!isset($ambiguosNome[$nomeLinha])) {
                        $naoEncontrados++;
                    }
                    $arquivosErro[$arquivoChave] = ($arquivosErro[$arquivoChave] ?? 0) + 1;
                    continue;
                }
                $aid = $porNome[$nomeLinha];
            }

            $candidatos = $porAluno[$aid] ?? [];
            if (!$notaUnica) {
                $disciplina = $this->normalizarTexto($disciplinaBruta);
                if ($disciplina === '' && count($materiasPorNorma) === 1) {
                    $disciplina = (string) array_key_first($materiasPorNorma);
                }
                if ($disciplina === '') {
                    $erros[] = $rotulo . ': informe a disciplina.';
                    $arquivosErro[$arquivoChave] = ($arquivosErro[$arquivoChave] ?? 0) + 1;
                    continue;
                }
                if (!isset($materiasPorNorma[$disciplina])) {
                    $erros[] = $rotulo . ': a disciplina não faz parte deste evento.';
                    $arquivosErro[$arquivoChave] = ($arquivosErro[$arquivoChave] ?? 0) + 1;
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
                    ? $rotulo . ': este aluno não está nessa disciplina no evento.'
                    : $rotulo . ': aluno transferido, a nota não foi lançada.';
                $arquivosErro[$arquivoChave] = ($arquivosErro[$arquivoChave] ?? 0) + 1;
                continue;
            }
            if (!$notaUnica) {
                $profsAtivos = [];
                foreach ($ativos as $rowAtivo) {
                    $pidAtivo = (int) ($rowAtivo['professor_id'] ?? 0);
                    if ($pidAtivo > 0) {
                        $profsAtivos[$pidAtivo] = true;
                    }
                }
                if (count($profsAtivos) > 1) {
                    $erros[] = $rotulo . ': há mais de um professor nesta disciplina. Selecione o professor na tela antes de importar.';
                    $arquivosErro[$arquivoChave] = ($arquivosErro[$arquivoChave] ?? 0) + 1;
                    continue;
                }
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
            $arquivosOk[$arquivoChave] = ($arquivosOk[$arquivoChave] ?? 0) + 1;
        }

        return [
            'aplicar' => array_values($vistos),
            'erros' => $erros,
            'ignoradas' => $ignoradas,
            'nao_encontrados' => $naoEncontrados,
            'arquivos_ok' => $arquivosOk,
            'arquivos_erro' => $arquivosErro,
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
        $this->exigirEntradaZipSegura($zip, 'xl/worksheets/sheet1.xml');
        $compartilhadas = $this->textosCompartilhados($this->lerEntradaZip($zip, 'xl/sharedStrings.xml') ?: '');
        $aba = $this->lerEntradaZip($zip, 'xl/worksheets/sheet1.xml');
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
     * Lê a(s) tabela(s) do Word (.docx) — formato OOXML.
     *
     * @return list<list<string>>
     */
    private function lerDocx(string $caminho): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Este servidor não abre Word. Salve a tabela como CSV ou Excel.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($caminho) !== true) {
            throw new \RuntimeException('Não foi possível abrir o documento Word.');
        }
        $this->exigirEntradaZipSegura($zip, 'word/document.xml');
        $xml = $this->lerEntradaZip($zip, 'word/document.xml');
        $zip->close();
        if (!is_string($xml) || $xml === '') {
            throw new \RuntimeException('O Word não tem conteúdo legível.');
        }

        $dom = new \DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $carregou = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        if ($carregou !== true) {
            throw new \RuntimeException('Não foi possível ler o documento Word.');
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        // Só tabelas do corpo (evita tabela aninhada / cabeçalho).
        $tabelasXml = $xp->query('/w:document/w:body/w:tbl');
        if ($tabelasXml === false || $tabelasXml->length === 0) {
            $tabelasXml = $xp->query('//w:body/w:tbl');
        }
        if ($tabelasXml === false || $tabelasXml->length === 0) {
            throw new \RuntimeException('O Word precisa ter uma tabela com as notas (código ou nome, e nota).');
        }

        $tabela = [];
        $cabecalhoNorma = null;
        foreach ($tabelasXml as $tbl) {
            if (!$tbl instanceof \DOMElement) {
                continue;
            }
            $linhasXml = $xp->query('./w:tr', $tbl);
            if ($linhasXml === false) {
                continue;
            }
            $primeiraDaTabela = true;
            foreach ($linhasXml as $tr) {
                if (!$tr instanceof \DOMElement) {
                    continue;
                }
                if (count($tabela) >= self::MAX_LINHAS + 1) {
                    throw new \RuntimeException('A planilha passa de ' . self::MAX_LINHAS . ' linhas.');
                }
                $row = [];
                foreach ($xp->query('./w:tc', $tr) ?: [] as $tc) {
                    if (!$tc instanceof \DOMElement) {
                        continue;
                    }
                    $paragrafos = [];
                    foreach ($xp->query('./w:p', $tc) ?: [] as $p) {
                        if (!$p instanceof \DOMElement) {
                            continue;
                        }
                        $textos = [];
                        foreach ($xp->query('.//w:t', $p) ?: [] as $t) {
                            $textos[] = (string) ($t->textContent ?? '');
                        }
                        $paragrafo = trim(implode('', $textos));
                        if ($paragrafo !== '') {
                            $paragrafos[] = $paragrafo;
                        }
                    }
                    $row[] = trim(preg_replace('/\s+/u', ' ', implode(' ', $paragrafos)) ?? '');
                }
                if ($primeiraDaTabela) {
                    $primeiraDaTabela = false;
                    $normaLinha = $this->normalizarTexto(implode('|', $row));
                    if ($cabecalhoNorma === null) {
                        $cabecalhoNorma = $normaLinha;
                    } elseif ($normaLinha === $cabecalhoNorma) {
                        // Cabeçalho repetido em outra tabela do mesmo arquivo.
                        continue;
                    }
                }
                $tabela[] = $row;
            }
        }

        if ($tabela === []) {
            throw new \RuntimeException('A tabela do Word está vazia.');
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
     * @return list<array{linha:int,codigo:string,nome:string,disciplina:string,nota:string}>
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
            $mapa = ['codigo' => 0, 'nome' => -1, 'disciplina' => 1, 'nota' => 2];
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
                'nome' => trim((string) ($row[$mapa['nome']] ?? '')),
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
     * @return array{codigo:int,nome:int,disciplina:int,nota:int}|null
     */
    private function mapaColunas(array $cabecalho): ?array
    {
        $aliases = [
            'codigo' => ['codigo', 'codigo do aluno', 'codigo_aluno', 'cod', 'ra', 'matricula'],
            'nome' => ['nome', 'aluno', 'nome do aluno', 'nome aluno', 'estudante', 'nome completo'],
            'disciplina' => ['disciplina', 'materia', 'componente', 'componente curricular'],
            'nota' => ['nota', 'nota final', 'valor', 'nota da prova bimestral', 'nota bimestral', 'nota da prova'],
        ];
        $achou = [];
        $candidatasNota = [];
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
            if (str_starts_with($norma, 'nota')) {
                $candidatasNota[] = (int) $i;
            }
        }
        if (!isset($achou['nota'])) {
            $candidatasNota = array_values(array_unique($candidatasNota));
            if (count($candidatasNota) === 1) {
                $achou['nota'] = $candidatasNota[0];
            } else {
                return null;
            }
        }
        if (!isset($achou['codigo']) && !isset($achou['nome'])) {
            return null;
        }
        if (!isset($achou['codigo'])) {
            $achou['codigo'] = -1;
        }
        if (!isset($achou['nome'])) {
            $achou['nome'] = -1;
        }
        if (!isset($achou['disciplina'])) {
            $achou['disciplina'] = -1;
        }

        return [
            'codigo' => $achou['codigo'],
            'nome' => $achou['nome'],
            'disciplina' => $achou['disciplina'],
            'nota' => $achou['nota'],
        ];
    }

    private function notaVaziaOuMarcador(string $nota): bool
    {
        $norma = $this->normalizarTexto($nota);
        return in_array($norma, ['tr', 'transferido', 'transferida', '-', 'n/a', 'na', 's/n', 'sn'], true);
    }

    private function exigirEntradaZipSegura(\ZipArchive $zip, string $caminho): void
    {
        $stat = $zip->statName($caminho);
        if (!is_array($stat)) {
            return;
        }
        $tamanho = (int) ($stat['size'] ?? 0);
        if ($tamanho > self::MAX_XML_BYTES) {
            throw new \RuntimeException('O arquivo interno da planilha é grande demais para importar.');
        }
    }

    private function lerEntradaZip(\ZipArchive $zip, string $caminho): string
    {
        $stat = $zip->statName($caminho);
        if (is_array($stat)) {
            $tamanho = (int) ($stat['size'] ?? 0);
            if ($tamanho > self::MAX_XML_BYTES) {
                throw new \RuntimeException('O arquivo interno da planilha é grande demais para importar.');
            }
        }
        $conteudo = $zip->getFromName($caminho);
        if (!is_string($conteudo)) {
            return '';
        }
        if (strlen($conteudo) > self::MAX_XML_BYTES) {
            throw new \RuntimeException('O arquivo interno da planilha é grande demais para importar.');
        }

        return $conteudo;
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

    private function normalizarNomeAluno(string $nome): string
    {
        $nome = AlunoLancamentoNotaHelper::normalizarNome($nome);
        // Remove prefixo "TR " que a tela usa para transferido.
        $nome = preg_replace('/^TR\s+/u', '', $nome) ?? $nome;

        return trim($nome);
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

    /**
     * @param array<string,mixed> $linha
     */
    private function rotuloLinha(array $linha): string
    {
        $n = (int) ($linha['linha'] ?? 0);
        $arquivo = trim((string) ($linha['arquivo'] ?? ''));
        if ($arquivo !== '') {
            return $this->recortar($arquivo) . ' linha ' . $n;
        }

        return 'Linha ' . $n;
    }
}
