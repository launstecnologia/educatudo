<?php

namespace App\Dominio\Escola\Services;

use App\Core\Database;

/**
 * Lê CSV ou Excel da grade e devolve o mesmo preview da importação por imagem.
 */
class ImportacaoGradeHorariaPlanilha
{
    private const MAX_BYTES = 2000000;
    private const MAX_LINHAS = 500;
    private const MAX_XML_BYTES = 5000000;
    private const MAX_COLUNA = 16;

    /**
     * @param array<string, mixed> $arquivo
     * @return array{success:bool,itens:list<array<string,mixed>>,dias_semana:array<int,string>,turmas:list<array<string,mixed>>,professores:list<array<string,mixed>>,materias:list<array<string,mixed>>}
     */
    public function lerArquivo(array $arquivo): array
    {
        $tabela = $this->lerTabela($arquivo);
        $linhas = $this->tabelaParaAulas($tabela);

        return $this->montarPreview($linhas);
    }

    /**
     * @param array<string, mixed> $arquivo
     * @return list<list<string>>
     */
    private function lerTabela(array $arquivo): array
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
            throw new \RuntimeException('Use um arquivo .xlsx ou .csv. No Excel, salve como pasta de trabalho ou como CSV.');
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

        return $ext === 'xlsx' ? $this->lerXlsx($tmp) : $this->lerCsv($tmp);
    }

    /**
     * @param list<list<string>> $tabela
     * @return list<array{dia_semana:int,horario_de:string,horario_ate:string,turma:string,professor:string,materia:string,periodo:string}>
     */
    private function tabelaParaAulas(array $tabela): array
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
        if ($mapa === null) {
            throw new \RuntimeException('A primeira linha precisa ter as colunas dia, horario_de, horario_ate, turma, professor e componente.');
        }

        $saida = [];
        for ($i = 1; $i < count($tabela); $i++) {
            if (count($saida) >= self::MAX_LINHAS) {
                throw new \RuntimeException('A planilha passa de ' . self::MAX_LINHAS . ' aulas.');
            }
            $row = $tabela[$i];
            $dia = $this->dia((string) ($row[$mapa['dia']] ?? ''));
            $de = $this->horario((string) ($row[$mapa['horario_de']] ?? ''));
            $ate = $this->horario((string) ($row[$mapa['horario_ate']] ?? ''));
            $turma = trim((string) ($row[$mapa['turma']] ?? ''));
            $professor = trim((string) ($row[$mapa['professor']] ?? ''));
            $materia = trim((string) ($row[$mapa['materia']] ?? ''));
            if ($dia < 1 || $de === '' || $ate === '' || $turma === '') {
                continue;
            }
            $periodoBruto = $mapa['periodo'] === null ? '' : (string) ($row[$mapa['periodo']] ?? '');
            $periodo = $this->periodo($periodoBruto, $de);
            $saida[] = [
                'dia_semana' => $dia,
                'horario_de' => $de,
                'horario_ate' => $ate,
                'turma' => $turma,
                'professor' => $professor,
                'materia' => $materia,
                'periodo' => $periodo,
            ];
        }
        if ($saida === []) {
            throw new \RuntimeException('Nenhuma aula encontrada. Confira dia, horários e turma em cada linha.');
        }

        return $saida;
    }

    /**
     * @param list<string> $cabecalho
     * @return array{dia:int,horario_de:int,horario_ate:int,turma:int,professor:int,materia:int,periodo:?int}|null
     */
    private function mapaColunas(array $cabecalho): ?array
    {
        $aliases = [
            'dia' => ['dia', 'dia da semana', 'dia_semana', 'weekday'],
            'horario_de' => ['horario de', 'horario_de', 'inicio', 'hora inicio', 'de', 'horario inicio'],
            'horario_ate' => ['horario ate', 'horario_ate', 'fim', 'hora fim', 'ate', 'horario fim'],
            'turma' => ['turma'],
            'professor' => ['professor', 'prof'],
            'materia' => ['materia', 'componente', 'disciplina', 'componente curricular'],
            'periodo' => ['periodo', 'turno'],
        ];
        $achou = [];
        foreach ($cabecalho as $i => $celula) {
            $norma = $this->normalizar((string) $celula);
            foreach ($aliases as $chave => $opcoes) {
                if (isset($achou[$chave])) {
                    continue;
                }
                if (in_array($norma, $opcoes, true)) {
                    $achou[$chave] = (int) $i;
                }
            }
        }
        foreach (['dia', 'horario_de', 'horario_ate', 'turma', 'professor', 'materia'] as $obrigatoria) {
            if (!isset($achou[$obrigatoria])) {
                return null;
            }
        }
        $achou['periodo'] = $achou['periodo'] ?? null;

        return $achou;
    }

    /**
     * @param list<array{dia_semana:int,horario_de:string,horario_ate:string,turma:string,professor:string,materia:string,periodo:string}> $linhas
     * @return array{success:bool,itens:list<array<string,mixed>>,dias_semana:array<int,string>,turmas:list<array<string,mixed>>,professores:list<array<string,mixed>>,materias:list<array<string,mixed>>}
     */
    private function montarPreview(array $linhas): array
    {
        $db = Database::getInstance();
        $turmasLista = $db->fetchAll('SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome') ?: [];
        $profLista = $db->fetchAll('SELECT id, nome FROM professores WHERE ativo = 1 ORDER BY nome') ?: [];
        $matLista = $db->fetchAll('SELECT id, nome FROM materias ORDER BY nome') ?: [];

        $turmasMap = $this->mapaNomes($turmasLista);
        $profMap = $this->mapaNomes($profLista);
        $matMap = $this->mapaNomes($matLista);

        $preview = [];
        foreach ($linhas as $row) {
            $turmaId = $this->buscarId($this->normalizar($row['turma']), $turmasMap);
            $professorId = $this->buscarId($this->normalizar($row['professor']), $profMap);
            $materiaId = $this->buscarId($this->normalizar($row['materia']), $matMap);
            $podeInserir = $turmaId && $professorId && $materiaId;
            $motivo = '';
            if (!$podeInserir) {
                $partes = [];
                if (!$turmaId) {
                    $partes[] = 'turma';
                }
                if (!$professorId) {
                    $partes[] = 'professor';
                }
                if (!$materiaId) {
                    $partes[] = 'matéria';
                }
                $motivo = implode(', ', $partes) . ' não encontrado(s)';
            }
            $preview[] = [
                'dia_semana' => $row['dia_semana'],
                'horario_de' => $row['horario_de'],
                'horario_ate' => $row['horario_ate'],
                'turma_nome' => $row['turma'],
                'professor_nome' => $row['professor'],
                'materia_nome' => $row['materia'],
                'periodo' => $row['periodo'],
                'turma_id' => $turmaId,
                'professor_id' => $professorId,
                'materia_id' => $materiaId,
                'pode_inserir' => $podeInserir,
                'motivo' => $motivo,
            ];
        }

        return [
            'success' => true,
            'itens' => $preview,
            'dias_semana' => [
                1 => 'Segunda-feira',
                2 => 'Terça-feira',
                3 => 'Quarta-feira',
                4 => 'Quinta-feira',
                5 => 'Sexta-feira',
                6 => 'Sábado',
                7 => 'Domingo',
            ],
            'turmas' => $turmasLista,
            'professores' => $profLista,
            'materias' => $matLista,
        ];
    }

    /**
     * @param list<array<string, mixed>> $lista
     * @return array<string, int>
     */
    private function mapaNomes(array $lista): array
    {
        $mapa = [];
        foreach ($lista as $item) {
            $nome = $this->normalizar((string) ($item['nome'] ?? ''));
            $id = (int) ($item['id'] ?? 0);
            if ($nome !== '' && $id > 0 && !isset($mapa[$nome])) {
                $mapa[$nome] = $id;
            }
        }

        return $mapa;
    }

    /**
     * @param array<string, int> $mapa
     */
    private function buscarId(string $valor, array $mapa): ?int
    {
        if ($valor === '') {
            return null;
        }
        if (isset($mapa[$valor])) {
            return (int) $mapa[$valor];
        }
        $achados = [];
        foreach ($mapa as $chave => $id) {
            if ($chave !== '' && (str_contains($chave, $valor) || str_contains($valor, $chave))) {
                $achados[(int) $id] = true;
            }
        }
        if (count($achados) === 1) {
            return (int) array_key_first($achados);
        }

        return null;
    }

    private function dia(string $valor): int
    {
        $norma = $this->normalizar($valor);
        $map = [
            '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7,
            'segunda' => 1, 'segunda feira' => 1, 'seg' => 1,
            'terca' => 2, 'terca feira' => 2, 'ter' => 2,
            'quarta' => 3, 'quarta feira' => 3, 'qua' => 3,
            'quinta' => 4, 'quinta feira' => 4, 'qui' => 4,
            'sexta' => 5, 'sexta feira' => 5, 'sex' => 5,
            'sabado' => 6, 'sab' => 6,
            'domingo' => 7, 'dom' => 7,
        ];

        return $map[$norma] ?? 0;
    }

    private function horario(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})/', $valor, $m) === 1) {
            $hora = (int) $m[1];
            $minuto = (int) $m[2];
            if ($hora > 23 || $minuto > 59) {
                return '';
            }

            return sprintf('%02d:%02d', $hora, $minuto);
        }
        if (preg_match('/^(\d{1,2})\s*h\s*(\d{2})?$/iu', $valor, $m) === 1) {
            $hora = (int) $m[1];
            $minuto = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
            if ($hora > 23 || $minuto > 59) {
                return '';
            }

            return sprintf('%02d:%02d', $hora, $minuto);
        }
        if (is_numeric($valor)) {
            $numero = (float) $valor;
            if ($numero > 1) {
                $numero -= floor($numero);
            }
            if ($numero >= 0 && $numero < 1) {
                $minutos = (int) round($numero * 24 * 60);
                $hora = intdiv($minutos, 60) % 24;
                $minuto = $minutos % 60;

                return sprintf('%02d:%02d', $hora, $minuto);
            }
        }

        return '';
    }

    private function periodo(string $valor, string $horarioDe): string
    {
        $norma = $this->normalizar($valor);
        if (in_array($norma, ['tarde', 'vespertino'], true)) {
            return 'tarde';
        }
        if (in_array($norma, ['manha', 'matutino'], true)) {
            return 'manha';
        }
        $hora = (int) substr($horarioDe, 0, 2);

        return $hora >= 12 ? 'tarde' : 'manha';
    }

    private function normalizar(string $valor): string
    {
        $valor = str_replace(['°', 'º', 'ª', '-', '_', '/'], ' ', $valor);
        $valor = mb_strtolower(trim($valor), 'UTF-8');
        $valor = preg_replace('/\s+/', ' ', $valor) ?? $valor;
        $mapa = ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c'];

        return strtr($valor, $mapa);
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
        while (($row = fgetcsv($handle, 0, $delim, '"', '\\')) !== false) {
            $n++;
            if ($n > self::MAX_LINHAS + 1) {
                fclose($handle);
                throw new \RuntimeException('A planilha passa de ' . self::MAX_LINHAS . ' aulas.');
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
        try {
            $compartilhadas = $this->textosCompartilhados($this->xmlDoZip($zip, 'xl/sharedStrings.xml'));
            $aba = $this->xmlDoZip($zip, 'xl/worksheets/sheet1.xml');
        } finally {
            $zip->close();
        }
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
                throw new \RuntimeException('A planilha passa de ' . self::MAX_LINHAS . ' aulas.');
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
                    $valor = $tipo === 's' ? ($compartilhadas[(int) $bruto] ?? '') : $bruto;
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

    private function xmlDoZip(\ZipArchive $zip, string $nome): string
    {
        $stat = $zip->statName($nome);
        if ($stat === false) {
            return '';
        }
        if ((int) ($stat['size'] ?? 0) > self::MAX_XML_BYTES) {
            throw new \RuntimeException('A planilha é grande demais para importar. Salve a primeira aba como CSV.');
        }
        $conteudo = $zip->getFromName($nome);

        return is_string($conteudo) ? $conteudo : '';
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
        if ($letras === '') {
            return 0;
        }
        if (strlen($letras) > 2) {
            throw new \RuntimeException('A planilha tem uma coluna fora do modelo. Use só as colunas dia, horário, turma, professor, componente e período.');
        }
        $n = 0;
        $len = strlen($letras);
        for ($i = 0; $i < $len; $i++) {
            $n = ($n * 26) + (ord($letras[$i]) - 64);
        }
        $indice = $n - 1;
        if ($indice < 0 || $indice > self::MAX_COLUNA) {
            throw new \RuntimeException('A planilha tem uma coluna fora do modelo. Use só as colunas dia, horário, turma, professor, componente e período.');
        }

        return $indice;
    }
}
