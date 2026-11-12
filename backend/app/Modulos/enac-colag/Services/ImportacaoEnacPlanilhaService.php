<?php

namespace App\Modulos\EnacColag\Services;

use App\Helpers\AlunoLancamentoNotaHelper;
use App\Modulos\Provas\Services\ImportacaoNotasPlanilha;

/**
 * Lê Excel/CSV ENAC (NOME, TURMA, TOTAL) e devolve linhas prontas para cruzar no evento.
 */
class ImportacaoEnacPlanilhaService
{
    private const MAX_BYTES = 2000000;
    private const MAX_XML_BYTES = 10000000;
    private const MAX_LINHAS = 8000;
    private const MAX_ARQUIVOS = 30;

    private ConversaoNotaEnacService $conversao;

    public function __construct(?ConversaoNotaEnacService $conversao = null)
    {
        $this->conversao = $conversao ?? new ConversaoNotaEnacService();
    }

    /**
     * Lê um ou vários Excels ENAC e junta as linhas.
     *
     * @param array<string,mixed> $filesCampo campo $_FILES['planilha']
     * @return array{
     *   linhas: list<array{linha:int,codigo:string,nome:string,disciplina:string,nota:string,arquivo?:string}>,
     *   abaixo_de_20:int,
     *   ignoradas:int,
     *   erros: list<string>,
     *   arquivos:int
     * }
     */
    public function lerArquivos(array $filesCampo): array
    {
        $lista = ImportacaoNotasPlanilha::separarUploads($filesCampo);
        if ($lista === []) {
            throw new \RuntimeException('Selecione ao menos um arquivo ENAC para importar.');
        }
        if (count($lista) > self::MAX_ARQUIVOS) {
            throw new \RuntimeException('Envie no máximo ' . self::MAX_ARQUIVOS . ' arquivos por vez.');
        }

        $linhas = [];
        $abaixo = 0;
        $ignoradas = 0;
        $erros = [];
        foreach ($lista as $arquivo) {
            $nomeArq = basename((string) ($arquivo['name'] ?? 'arquivo'));
            try {
                $lido = $this->lerArquivo($arquivo);
            } catch (\RuntimeException $e) {
                $erros[] = $nomeArq . ': ' . $e->getMessage();
                continue;
            }
            $abaixo += (int) ($lido['abaixo_de_20'] ?? 0);
            $ignoradas += (int) ($lido['ignoradas'] ?? 0);
            foreach (($lido['erros'] ?? []) as $erro) {
                $erros[] = $nomeArq . ' ' . (string) $erro;
            }
            foreach (($lido['linhas'] ?? []) as $linha) {
                $linha['arquivo'] = $nomeArq;
                $linhas[] = $linha;
            }
        }
        if ($linhas === [] && $erros === []) {
            throw new \RuntimeException('A planilha ENAC não tem linhas com pontuação para lançar.');
        }

        return [
            'linhas' => $linhas,
            'abaixo_de_20' => $abaixo,
            'ignoradas' => $ignoradas,
            'erros' => $erros,
            'arquivos' => count($lista),
        ];
    }

    /**
     * @param array<string,mixed> $arquivo
     * @return array{
     *   linhas: list<array{linha:int,codigo:string,nome:string,disciplina:string,nota:string}>,
     *   abaixo_de_20:int,
     *   ignoradas:int,
     *   erros: list<string>
     * }
     */
    public function lerArquivo(array $arquivo): array
    {
        $erro = (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erro === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException('Selecione a planilha ENAC para importar.');
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
            throw new \RuntimeException('Use um arquivo .csv ou .xlsx do ENAC.');
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
     * @param list<array{linha:int,codigo:string,nome:string,disciplina:string,nota:string}> $linhas
     * @param list<array<string,mixed>> $elegiveis
     * @return array{aplicar:list<array<string,mixed>>,erros:list<string>,ignoradas:int}
     */
    public function cruzar(array $linhas, array $elegiveis, bool $notaUnica, float $escalaMax = 10.0): array
    {
        return (new ImportacaoNotasPlanilha())->cruzar($linhas, $elegiveis, $notaUnica, $escalaMax);
    }

    /**
     * @param list<list<string>> $tabela
     * @return array{
     *   linhas: list<array{linha:int,codigo:string,nome:string,disciplina:string,nota:string}>,
     *   abaixo_de_20:int,
     *   ignoradas:int,
     *   erros: list<string>
     * }
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
            throw new \RuntimeException('A planilha ENAC está vazia.');
        }

        $mapa = $this->mapaColunas($tabela[0]);
        $inicio = 0;
        if ($mapa !== null) {
            $inicio = 1;
        } else {
            // NOME | TURMA | TOTAL [| NOTA]
            $mapa = ['nome' => 0, 'codigo' => -1, 'total' => 2];
        }

        $linhas = [];
        $abaixo = 0;
        $ignoradas = 0;
        $erros = [];
        for ($i = $inicio; $i < count($tabela); $i++) {
            if (count($linhas) + $ignoradas + count($erros) >= self::MAX_LINHAS) {
                throw new \RuntimeException('A planilha passa de ' . self::MAX_LINHAS . ' linhas.');
            }
            $row = $tabela[$i];
            $n = $i + 1;
            $nome = trim((string) ($row[$mapa['nome']] ?? ''));
            $codigo = trim((string) ($row[$mapa['codigo']] ?? ''));
            $totalBruto = trim((string) ($row[$mapa['total']] ?? ''));
            if ($nome === '' && $codigo === '' && $totalBruto === '') {
                continue;
            }
            if ($this->marcadorIgnorar($totalBruto)) {
                $ignoradas++;
                continue;
            }
            $totalNorma = str_replace(',', '.', $totalBruto);
            if (!is_numeric($totalNorma)) {
                $erros[] = 'Linha ' . $n . ': TOTAL inválido (' . $this->recortar($totalBruto) . ').';
                continue;
            }
            try {
                $convertido = $this->conversao->converter((float) $totalNorma);
            } catch (\InvalidArgumentException $e) {
                $erros[] = 'Linha ' . $n . ': ' . $e->getMessage();
                continue;
            }
            if ($convertido['abaixo_de_20']) {
                $abaixo++;
            }
            $linhas[] = [
                'linha' => $n,
                'codigo' => $codigo,
                'nome' => $nome,
                'disciplina' => '',
                'nota' => (string) $convertido['nota'],
            ];
        }
        if ($linhas === [] && $erros === []) {
            throw new \RuntimeException('A planilha ENAC não tem linhas com pontuação para lançar.');
        }

        return [
            'linhas' => $linhas,
            'abaixo_de_20' => $abaixo,
            'ignoradas' => $ignoradas,
            'erros' => $erros,
        ];
    }

    /**
     * @param list<string> $cabecalho
     * @return array{nome:int,codigo:int,total:int}|null
     */
    private function mapaColunas(array $cabecalho): ?array
    {
        $aliases = [
            'nome' => ['nome', 'aluno', 'nome do aluno', 'nome aluno', 'estudante', 'nome completo'],
            'codigo' => ['codigo', 'codigo do aluno', 'codigo_aluno', 'cod', 'ra', 'matricula'],
            'total' => ['total', 'total geral', 'pontuacao', 'pontuacao geral', 'acertos', 'pontos', 'score'],
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
        if (!isset($achou['total'])) {
            return null;
        }
        if (!isset($achou['nome']) && !isset($achou['codigo'])) {
            return null;
        }
        if (!isset($achou['nome'])) {
            $achou['nome'] = -1;
        }
        if (!isset($achou['codigo'])) {
            $achou['codigo'] = -1;
        }

        return [
            'nome' => $achou['nome'],
            'codigo' => $achou['codigo'],
            'total' => $achou['total'],
        ];
    }

    private function marcadorIgnorar(string $valor): bool
    {
        $norma = $this->normalizarTexto($valor);
        return $norma === ''
            || in_array($norma, ['tr', 'transferido', 'transferida', 'faltou', 'falta', '-', 'n/a', 'na', 's/n', 'sn'], true);
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
            throw new \RuntimeException('A planilha ENAC está vazia.');
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
        $compartilhadas = $this->textosCompartilhados($this->lerEntradaZip($zip, 'xl/sharedStrings.xml'));
        $aba = $this->lerEntradaZip($zip, 'xl/worksheets/sheet1.xml');
        $zip->close();
        if ($aba === '') {
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
            throw new \RuntimeException('A planilha ENAC está vazia.');
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

    private function exigirEntradaZipSegura(\ZipArchive $zip, string $caminho): void
    {
        $stat = $zip->statName($caminho);
        if (!is_array($stat)) {
            return;
        }
        if ((int) ($stat['size'] ?? 0) > self::MAX_XML_BYTES) {
            throw new \RuntimeException('O arquivo interno da planilha é grande demais para importar.');
        }
    }

    private function lerEntradaZip(\ZipArchive $zip, string $caminho): string
    {
        $this->exigirEntradaZipSegura($zip, $caminho);
        $conteudo = $zip->getFromName($caminho);
        if (!is_string($conteudo)) {
            return '';
        }
        if (strlen($conteudo) > self::MAX_XML_BYTES) {
            throw new \RuntimeException('O arquivo interno da planilha é grande demais para importar.');
        }

        return $conteudo;
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = AlunoLancamentoNotaHelper::normalizarNome($texto);
        $texto = mb_strtolower($texto, 'UTF-8');

        return trim($texto);
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
