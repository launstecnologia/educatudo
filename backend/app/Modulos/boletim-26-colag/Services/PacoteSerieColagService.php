<?php

namespace App\Modulos\Boletim26Colag\Services;

/**
 * Pacote Excel completo da série (modelo 3ª SÉRIE.xlsx) para o fechamento Colag.
 * SpreadsheetML — abre no Excel sem PhpSpreadsheet.
 */
class PacoteSerieColagService
{
    private Boletim26ColagService $colag;

    /** Meta de aprovação da recuperação anual no modelo da escola. */
    private const META_RECUP = 7.0;

    /** @var array<string,true> */
    private array $abasUsadas = [];

    public function __construct(?Boletim26ColagService $colag = null)
    {
        $this->colag = $colag ?? new Boletim26ColagService();
    }

    /**
     * @param list<int> $turmaIds turmas homologadas do filtro
     * @return array{xml:string,nome_arquivo:string}
     */
    public function gerar(array $turmaIds, int $ano, string $rotuloSerie, float $limiar = 6.5): array
    {
        if (!Boletim26ColagService::ativo()) {
            throw new \RuntimeException('Pacote Excel disponível apenas no tenant Colag.');
        }
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', $turmaIds), static fn (int $id): bool => $id > 0)));
        if ($turmaIds === []) {
            throw new \RuntimeException('Nenhuma turma homologada no filtro para gerar o pacote.');
        }
        $ano = ($ano >= 2000 && $ano <= 2100) ? $ano : (int) date('Y');
        $limiar = max(0.0, min(10.0, $limiar));
        $relatorios = [];
        foreach ($turmaIds as $turmaId) {
            // serieFiltro=0: só a turma pedida (evita expandir a série se a turma não estiver no EM Colag)
            $painel = $this->colag->montarPainel(
                $turmaId,
                0,
                $ano,
                0,
                0,
                null,
                true,
                'resultado',
                true,
                [1, 2, 3, 4],
                null
            );
            if ((int) ($painel['turma_id'] ?? 0) !== $turmaId) {
                continue;
            }
            foreach (is_array($painel['relatorios'] ?? null) ? $painel['relatorios'] : [] as $relatorio) {
                $relatorios[] = $relatorio;
            }
        }
        if ($relatorios === []) {
            throw new \RuntimeException('Sem alunos/notas Colag para o filtro atual.');
        }

        $dados = $this->montarDados($relatorios, $limiar);
        $xml = $this->montarWorkbook($dados, $rotuloSerie !== '' ? $rotuloSerie : 'Série', $ano, $limiar);
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $rotuloSerie) ?: $rotuloSerie) ?: 'serie';
        $slug = trim((string) $slug, '-_') ?: 'serie';

        return [
            'xml' => $xml,
            'nome_arquivo' => $slug . '-medias-fechamento-' . $ano . '.xls',
        ];
    }

    /**
     * @param list<array<string,mixed>> $relatorios
     * @return array{
     *   alunos:list<array{nome:string,status:string}>,
     *   materias:list<string>,
     *   siglas:list<string>,
     *   matriz:array<string, array<string, array<int, float>>>,
     *   anuais:array<string, array<string, array{bims:array<int,float>,media:float,arred:float,aprovado:bool,precisa:string}>>
     * }
     */
    private function montarDados(array $relatorios, float $limiar): array
    {
        $blocos = $this->colag->planilhaMediasBimestrais($relatorios, $limiar);
        $alunosMap = [];
        $materiasMap = [];
        $matriz = [];
        $anuais = [];

        foreach ($relatorios as $relatorio) {
            $nomeAluno = trim((string) (($relatorio['aluno']['nome'] ?? '') ?: ''));
            if ($nomeAluno === '') {
                continue;
            }
            $status = 'MAT';
            $alunoRow = is_array($relatorio['aluno'] ?? null) ? $relatorio['aluno'] : [];
            $st = strtoupper((string) ($alunoRow['status'] ?? ''));
            if (str_contains($st, 'TRANSFER') || str_contains($st, 'TR')) {
                $status = 'TR';
            }
            $alunosMap[$nomeAluno] = ['nome' => $nomeAluno, 'status' => $status];

            foreach (is_array($relatorio['demonstrativos'] ?? null) ? $relatorio['demonstrativos'] : [] as $demo) {
                if (!empty($demo['titulo'])) {
                    continue;
                }
                $bimestre = (int) ($demo['bimestre'] ?? 0);
                if ($bimestre < 1 || $bimestre > 4) {
                    continue;
                }
                foreach (is_array($demo['blocos'] ?? null) ? $demo['blocos'] : [] as $bloco) {
                    foreach (is_array($bloco['linhas'] ?? null) ? $bloco['linhas'] : [] as $linha) {
                        if (!empty($linha['filha']) || !is_numeric($linha['resultado'] ?? null)) {
                            continue;
                        }
                        $materia = trim((string) ($linha['nome'] ?? ''));
                        if ($materia === '') {
                            continue;
                        }
                        $materiasMap[$materia] = $materia;
                        $matriz[$nomeAluno][$materia][$bimestre] = (float) $linha['resultado'];
                    }
                }
            }
        }

        foreach ($blocos as $bloco) {
            $materia = (string) ($bloco['materia'] ?? '');
            if ($materia === '') {
                continue;
            }
            $materiasMap[$materia] = $materia;
            foreach (is_array($bloco['alunos'] ?? null) ? $bloco['alunos'] : [] as $aluno) {
                $nome = (string) ($aluno['nome'] ?? '');
                if ($nome === '') {
                    continue;
                }
                $bims = is_array($aluno['bimestres'] ?? null) ? $aluno['bimestres'] : [];
                $arred = is_numeric(str_replace(',', '.', (string) ($aluno['media_arred_texto'] ?? '')))
                    ? (float) str_replace(',', '.', (string) $aluno['media_arred_texto'])
                    : null;
                if ($arred === null) {
                    $soma = 0.0;
                    $q = 0;
                    foreach ([1, 2, 3, 4] as $b) {
                        if (isset($bims[$b]) && is_numeric($bims[$b])) {
                            $soma += (float) $bims[$b];
                            $q++;
                        }
                    }
                    if ($q < 1) {
                        continue;
                    }
                    $media = $soma / $q;
                    $arred = round($media * 2, 0, PHP_ROUND_HALF_UP) / 2;
                } else {
                    $soma = 0.0;
                    $q = 0;
                    foreach ([1, 2, 3, 4] as $b) {
                        if (isset($bims[$b]) && is_numeric($bims[$b])) {
                            $soma += (float) $bims[$b];
                            $q++;
                        }
                    }
                    $media = $q > 0 ? $soma / $q : $arred;
                }
                $aprovado = !empty($aluno['aprovado']) || $arred >= $limiar;
                $precisa = $aprovado ? 'APRO' : $this->notaPrecisaRecuperacao($arred);
                $anuais[$materia][$nome] = [
                    'bims' => $bims,
                    'media' => $media,
                    'arred' => $arred,
                    'aprovado' => $aprovado,
                    'precisa' => $precisa,
                ];
                $alunosMap[$nome] = $alunosMap[$nome] ?? ['nome' => $nome, 'status' => 'MAT'];
            }
        }

        $alunos = array_values($alunosMap);
        usort($alunos, static fn (array $a, array $b): int => strcasecmp((string) $a['nome'], (string) $b['nome']));
        $materias = $this->ordenarMaterias(array_values($materiasMap));
        $siglas = [];
        foreach ($materias as $materia) {
            $siglas[] = $this->sigla($materia);
        }

        return [
            'alunos' => $alunos,
            'materias' => $materias,
            'siglas' => $siglas,
            'matriz' => $matriz,
            'anuais' => $anuais,
        ];
    }

    /**
     * @param array<string,mixed> $dados
     */
    private function montarWorkbook(array $dados, string $rotuloSerie, int $ano, float $limiar): string
    {
        $this->abasUsadas = [
            'LISTA' => true,
            '1OBIM' => true,
            '2OBIM' => true,
            '3OBIM' => true,
            '4OBIM' => true,
            'IT' => true,
            'REC' => true,
            'F-RECUP' => true,
            'F-MENÇÃO' => true,
            'F-FINAL' => true,
            'FINAL - IT' => true,
            'FICHA' => true,
        ];
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= $this->estilos();
        $xml .= $this->abaLista($dados, $rotuloSerie, $ano);
        for ($b = 1; $b <= 4; $b++) {
            $xml .= $this->abaBimestre($dados, $rotuloSerie, $b);
        }
        foreach ($dados['materias'] as $materia) {
            $xml .= $this->abaMateria($dados, $rotuloSerie, $materia);
        }
        $xml .= $this->abaIt($dados, $rotuloSerie);
        $xml .= $this->abaRec($dados, $rotuloSerie);
        $xml .= $this->abaFormulario($dados, $rotuloSerie, $ano, 'F-RECUP', 'RECUPERAÇÃO', true);
        $xml .= $this->abaFormulario($dados, $rotuloSerie, $ano, 'F-MENÇÃO', 'M. FINAL', false);
        $xml .= $this->abaFinal($dados, $rotuloSerie, $ano);
        $xml .= $this->abaFinalIt($dados, $rotuloSerie, $ano);
        $xml .= $this->abaFichas($dados, $rotuloSerie, $limiar);
        $xml .= '</Workbook>';

        return $xml;
    }

    private function estilos(): string
    {
        $borda = '<Borders>'
            . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#6B7280"/>'
            . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#6B7280"/>'
            . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#6B7280"/>'
            . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#6B7280"/>'
            . '</Borders>';

        return '<Styles>'
            . '<Style ss:ID="Default"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="11"/></Style>'
            . '<Style ss:ID="Titulo"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="13" ss:Bold="1"/>' . $borda . '</Style>'
            . '<Style ss:ID="TituloAzul"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="12" ss:Bold="1"/><Interior ss:Color="#93C5FD" ss:Pattern="Solid"/>' . $borda . '</Style>'
            . '<Style ss:ID="Cabeca"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1"/><Interior ss:Color="#E5E7EB" ss:Pattern="Solid"/>' . $borda . '</Style>'
            . '<Style ss:ID="CabecaBege"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1"/><Interior ss:Color="#E7D3A5" ss:Pattern="Solid"/>' . $borda . '</Style>'
            . '<Style ss:ID="Nome"><Alignment ss:Horizontal="Left" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="10"/>' . $borda . '</Style>'
            . '<Style ss:ID="Cel"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="10"/>' . $borda . '</Style>'
            . '<Style ss:ID="Bege"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="10"/><Interior ss:Color="#F5E6C8" ss:Pattern="Solid"/>' . $borda . '</Style>'
            . '<Style ss:ID="BegeBold"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1"/><Interior ss:Color="#F5E6C8" ss:Pattern="Solid"/>' . $borda . '</Style>'
            . '<Style ss:ID="NotaBaixa"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#DC2626"/><Interior ss:Color="#F5E6C8" ss:Pattern="Solid"/>' . $borda . '</Style>'
            . '<Style ss:ID="Num"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="10"/><Interior ss:Color="#FED7AA" ss:Pattern="Solid"/>' . $borda . '</Style>'
            . '</Styles>';
    }

    /**
     * @param array<string,mixed> $dados
     */
    private function abaLista(array $dados, string $rotuloSerie, int $ano): string
    {
        $xml = '<Worksheet ss:Name="LISTA"><Table>';
        $xml .= '<Column ss:Width="280"/><Column ss:Width="120"/><Column ss:Width="80"/><Column ss:Width="80"/>';
        $xml .= $this->row(['NOME DO ALUNO', 'MATRICULADO (MAT) OU TRANSFERIDO (TR)', 'ANO LETIVO', 'BIMESTRE', $rotuloSerie], 'Cabeca');
        foreach ($dados['alunos'] as $aluno) {
            $xml .= $this->row([
                (string) $aluno['nome'],
                (string) $aluno['status'],
                (string) $ano,
                'FINAL',
                '',
            ], 'Cel', 0);
        }
        $xml .= '</Table></Worksheet>';

        return $xml;
    }

    /**
     * @param array<string,mixed> $dados
     */
    private function abaBimestre(array $dados, string $rotuloSerie, int $bimestre): string
    {
        $nomeAba = $bimestre . 'oBIM';
        $xml = '<Worksheet ss:Name="' . $this->attr($nomeAba) . '"><Table>';
        $xml .= '<Column ss:Width="280"/>';
        foreach ($dados['siglas'] as $_) {
            $xml .= '<Column ss:Width="48"/>';
        }
        $xml .= $this->row([$rotuloSerie], 'Titulo');
        $xml .= $this->row(['MEDIA BIMESTRAL - PARA DIGITAR NO BOLETIM'], 'TituloAzul');
        $xml .= $this->row([$bimestre . 'º BIM'], 'Cabeca');
        $cabeca = array_merge(['NOME DO ALUNO'], $dados['siglas']);
        $xml .= $this->row($cabeca, 'Cabeca');
        foreach ($dados['alunos'] as $aluno) {
            $nome = (string) $aluno['nome'];
            $celulas = [$nome];
            foreach ($dados['materias'] as $materia) {
                $nota = $dados['matriz'][$nome][$materia][$bimestre] ?? null;
                $celulas[] = is_numeric($nota) ? $this->fmt((float) $nota) : '';
            }
            $xml .= $this->row($celulas, 'Cel', 0);
        }
        $xml .= '</Table></Worksheet>';

        return $xml;
    }

    /**
     * @param array<string,mixed> $dados
     */
    private function abaMateria(array $dados, string $rotuloSerie, string $materia): string
    {
        $sigla = $this->sigla($materia);
        $aba = $this->nomeAbaUnico('B-' . substr($sigla, 0, 5), $materia);
        $xml = '<Worksheet ss:Name="' . $this->attr($aba) . '"><Table>';
        $xml .= '<Column ss:Width="28"/><Column ss:Width="260"/>';
        for ($i = 0; $i < 10; $i++) {
            $xml .= '<Column ss:Width="52"/>';
        }
        $xml .= $this->row(['RELATÓRIO GERAL DAS MÉDIAS BIMESTRAIS', '', '', '', '', '', '', '', $rotuloSerie], 'Titulo');
        $xml .= $this->row([
            mb_strtoupper($materia, 'UTF-8'),
            '1º BIM', '2º BIM', '3º BIM', '4º BIM',
            'MÉDIA', 'MÉDIA ARRED', 'PRECISA TIRAR REC.', 'NOTA REC.', 'MÉDIA FINAL', '',
        ], 'CabecaBege', 0);
        $n = 1;
        foreach ($dados['alunos'] as $aluno) {
            $nome = (string) $aluno['nome'];
            $info = $dados['anuais'][$materia][$nome] ?? null;
            if ($info === null) {
                continue;
            }
            $bims = $info['bims'];
            $aprovado = !empty($info['aprovado']);
            $arredTxt = $this->fmt((float) $info['arred']);
            $status = $aprovado ? 'APRO' : $arredTxt;
            $xml .= $this->row([
                (string) $n,
                $nome,
                isset($bims[1]) ? $this->fmt((float) $bims[1]) : '',
                isset($bims[2]) ? $this->fmt((float) $bims[2]) : '',
                isset($bims[3]) ? $this->fmt((float) $bims[3]) : '',
                isset($bims[4]) ? $this->fmt((float) $bims[4]) : '',
                $this->fmt((float) $info['media']),
                $arredTxt,
                (string) $info['precisa'],
                '',
                $arredTxt,
                $status,
            ], $aprovado ? 'Bege' : 'NotaBaixa', 0);
            $n++;
        }
        $xml .= '</Table></Worksheet>';

        return $xml;
    }

    /**
     * @param array<string,mixed> $dados
     */
    private function abaIt(array $dados, string $rotuloSerie): string
    {
        $xml = '<Worksheet ss:Name="IT"><Table>';
        $xml .= '<Column ss:Width="280"/>';
        for ($i = 0; $i < 7; $i++) {
            $xml .= '<Column ss:Width="52"/>';
        }
        $xml .= $this->row(['RELATÓRIO GERAL DAS MÉDIAS BIMESTRAIS — ' . $rotuloSerie], 'Titulo');
        $xml .= $this->row(['REDAÇÃO - IT', '1º BIM', '2º BIM', '3º BIM', '4º BIM', 'MÉDIA', 'REC', 'MÉDIA FINAL'], 'Cabeca');
        foreach ($dados['alunos'] as $aluno) {
            $xml .= $this->row([(string) $aluno['nome'], '—', '—', '—', '—', '—', '', '—'], 'Cel', 0);
        }
        $xml .= '</Table></Worksheet>';

        return $xml;
    }

    /**
     * @param array<string,mixed> $dados
     */
    private function abaRec(array $dados, string $rotuloSerie): string
    {
        $xml = '<Worksheet ss:Name="REC"><Table>';
        $xml .= '<Column ss:Width="280"/>';
        foreach ($dados['siglas'] as $_) {
            $xml .= '<Column ss:Width="48"/>';
        }
        $xml .= $this->row([$rotuloSerie], 'Titulo');
        $xml .= $this->row(['QUANTO PRECISA NA PROVA FINAL OU APROVADO'], 'TituloAzul');
        $xml .= $this->row(['PROVA FINAL'], 'Cabeca');
        $xml .= $this->row(array_merge(['NOME DO ALUNO'], $dados['siglas']), 'Cabeca');
        foreach ($dados['alunos'] as $aluno) {
            $nome = (string) $aluno['nome'];
            $celulas = [$nome];
            foreach ($dados['materias'] as $materia) {
                $info = $dados['anuais'][$materia][$nome] ?? null;
                if ($info === null) {
                    $celulas[] = '';
                    continue;
                }
                $celulas[] = !empty($info['aprovado']) ? 'APRO' : (string) $info['precisa'];
            }
            $xml .= $this->row($celulas, 'Cel', 0);
        }
        $xml .= '</Table></Worksheet>';

        return $xml;
    }

    /**
     * @param array<string,mixed> $dados
     */
    private function abaFormulario(array $dados, string $rotuloSerie, int $ano, string $aba, string $tituloBloco, bool $recuperacao): string
    {
        $xml = '<Worksheet ss:Name="' . $this->attr($aba) . '"><Table>';
        $xml .= '<Column ss:Width="280"/><Column ss:Width="40"/><Column ss:Width="48"/>';
        $xml .= $this->row(['COLÉGIO — FECHAMENTO COLAG'], 'Titulo');
        $xml .= $this->row([$tituloBloco . ' · ' . $ano . ' · ' . $rotuloSerie], 'TituloAzul');
        $cabeca = ['NOME DO ALUNO', 'Nº'];
        foreach ($dados['siglas'] as $sigla) {
            $cabeca[] = $sigla;
        }
        $xml .= $this->row($cabeca, 'Cabeca');
        $n = 1;
        foreach ($dados['alunos'] as $aluno) {
            $nome = (string) $aluno['nome'];
            $celulas = [$nome, (string) $n];
            foreach ($dados['materias'] as $materia) {
                $info = $dados['anuais'][$materia][$nome] ?? null;
                if ($info === null) {
                    $celulas[] = '';
                    continue;
                }
                if ($recuperacao) {
                    $celulas[] = !empty($info['aprovado']) ? '' : (string) $info['precisa'];
                } else {
                    $celulas[] = $this->fmt((float) $info['arred']);
                }
            }
            $xml .= $this->row($celulas, 'Cel', 0);
            $n++;
        }
        $xml .= '</Table></Worksheet>';

        return $xml;
    }

    /**
     * @param array<string,mixed> $dados
     */
    private function abaFinal(array $dados, string $rotuloSerie, int $ano): string
    {
        $xml = '<Worksheet ss:Name="F-FINAL"><Table>';
        $xml .= '<Column ss:Width="280"/><Column ss:Width="40"/>';
        foreach ($dados['siglas'] as $_) {
            $xml .= '<Column ss:Width="48"/>';
        }
        $xml .= '<Column ss:Width="60"/>';
        $xml .= $this->row(['ATA CONSELHO FINAL - RENDIMENTO ESCOLAR'], 'Titulo');
        $xml .= $this->row([(string) $ano, $rotuloSerie], 'TituloAzul');
        $cabeca = array_merge(['NOME DO ALUNO', 'Nº'], $dados['siglas'], ['RESULTADO FINAL']);
        $xml .= $this->row($cabeca, 'Cabeca');
        $n = 1;
        foreach ($dados['alunos'] as $aluno) {
            $nome = (string) $aluno['nome'];
            $celulas = [$nome, (string) $n];
            $todasApro = true;
            foreach ($dados['materias'] as $materia) {
                $info = $dados['anuais'][$materia][$nome] ?? null;
                if ($info === null) {
                    $celulas[] = '';
                    $todasApro = false;
                    continue;
                }
                $celulas[] = $this->fmt((float) $info['arred']);
                if (empty($info['aprovado'])) {
                    $todasApro = false;
                }
            }
            $celulas[] = $todasApro ? 'P' : 'R';
            $xml .= $this->row($celulas, 'Cel', 0);
            $n++;
        }
        $xml .= '</Table></Worksheet>';

        return $xml;
    }

    /**
     * @param array<string,mixed> $dados
     */
    private function abaFinalIt(array $dados, string $rotuloSerie, int $ano): string
    {
        $xml = '<Worksheet ss:Name="FINAL - IT"><Table>';
        $xml .= '<Column ss:Width="280"/><Column ss:Width="40"/>';
        for ($i = 0; $i < 8; $i++) {
            $xml .= '<Column ss:Width="70"/>';
        }
        $xml .= $this->row(['ATA CONSELHO FINAL - ITINERÁRIO'], 'Titulo');
        $xml .= $this->row([(string) $ano, $rotuloSerie], 'TituloAzul');
        $xml .= $this->row([
            'NOME DO ALUNO', 'Nº',
            'REDAÇÃO - IT', 'LINGUAGENS - IT', 'INGLÊS - IT', 'MATEMÁTICA - IT',
            'PROJETO DE VIDA - IT', 'GEOPOLÍTICA - IT', 'CIÊNCIAS - IT', 'HUMANIDADES - IT',
        ], 'Cabeca');
        $n = 1;
        foreach ($dados['alunos'] as $aluno) {
            $celulas = [(string) $aluno['nome'], (string) $n];
            for ($i = 0; $i < 8; $i++) {
                $celulas[] = '—';
            }
            $xml .= $this->row($celulas, 'Cel', 0);
            $n++;
        }
        $xml .= '</Table></Worksheet>';

        return $xml;
    }

    /**
     * Fichas dos alunos que precisam de recuperação em alguma matéria.
     *
     * @param array<string,mixed> $dados
     */
    private function abaFichas(array $dados, string $rotuloSerie, float $limiar): string
    {
        $xml = '<Worksheet ss:Name="FICHA"><Table>';
        $xml .= '<Column ss:Width="140"/>';
        for ($i = 0; $i < 10; $i++) {
            $xml .= '<Column ss:Width="52"/>';
        }
        $xml .= $this->row(['FICHA DE ATENDIMENTO - ENSINO MÉDIO'], 'Titulo');
        $gerou = false;
        foreach ($dados['alunos'] as $aluno) {
            $nome = (string) $aluno['nome'];
            $linhasRec = [];
            foreach ($dados['materias'] as $materia) {
                $info = $dados['anuais'][$materia][$nome] ?? null;
                if ($info === null || !empty($info['aprovado'])) {
                    continue;
                }
                $linhasRec[] = [$materia, $info];
            }
            if ($linhasRec === []) {
                continue;
            }
            $gerou = true;
            $xml .= $this->row(['ALUNO(A)', $nome, '', 'SÉRIE', $rotuloSerie, '', 'BIMESTRE', 'FINAL'], 'Cabeca');
            $xml .= $this->row([
                '', '1º BIM', '2º BIM', '3º BIM', '4º BIM', 'MÉDIA', 'MEDIA ARRED',
                'PRECISA TIRAR RECUP.', 'NOTA REC', 'MÉDIA FINAL',
            ], 'CabecaBege');
            foreach ($linhasRec as [$materia, $info]) {
                $bims = $info['bims'];
                $xml .= $this->row([
                    (string) $materia,
                    isset($bims[1]) ? $this->fmt((float) $bims[1]) : '',
                    isset($bims[2]) ? $this->fmt((float) $bims[2]) : '',
                    isset($bims[3]) ? $this->fmt((float) $bims[3]) : '',
                    isset($bims[4]) ? $this->fmt((float) $bims[4]) : '',
                    $this->fmt((float) $info['media']),
                    $this->fmt((float) $info['arred']),
                    (string) $info['precisa'],
                    '',
                    $this->fmt((float) $info['arred']),
                ], 'NotaBaixa', 0);
            }
            $xml .= $this->row([''], 'Cel');
        }
        if (!$gerou) {
            $xml .= $this->row(['Nenhum aluno abaixo da média (' . $this->fmt($limiar) . ') neste filtro.'], 'Cel');
        }
        $xml .= '</Table></Worksheet>';

        return $xml;
    }

    /**
     * @param list<string> $valores
     */
    private function row(array $valores, string $estilo, int $nomeIndex = -1): string
    {
        $xml = '<Row ss:Height="18">';
        foreach ($valores as $i => $valor) {
            $estiloCel = ($nomeIndex >= 0 && $i === $nomeIndex) ? 'Nome' : $estilo;
            if ($estilo === 'Cabeca' || $estilo === 'CabecaBege' || $estilo === 'Titulo' || $estilo === 'TituloAzul') {
                $estiloCel = $estilo;
            }
            if ($i === 0 && $estilo === 'Cel') {
                $estiloCel = 'Nome';
            }
            if ($i === 0 && ($estilo === 'Bege' || $estilo === 'NotaBaixa' || $estilo === 'BegeBold')) {
                $estiloCel = 'Num';
            }
            if ($i === 1 && ($estilo === 'Bege' || $estilo === 'NotaBaixa' || $estilo === 'BegeBold')) {
                $estiloCel = 'Nome';
            }
            $xml .= '<Cell ss:StyleID="' . $estiloCel . '"><Data ss:Type="String">' . $this->txt((string) $valor) . '</Data></Cell>';
        }
        $xml .= '</Row>';

        return $xml;
    }

    private function notaPrecisaRecuperacao(float $mediaArred): string
    {
        // (média + prova) / 2 >= META → prova >= 2*META - média
        $precisa = (self::META_RECUP * 2) - $mediaArred;
        $precisa = max(0.0, min(10.0, round($precisa * 2, 0, PHP_ROUND_HALF_UP) / 2));

        return $this->fmt($precisa);
    }

    private function nomeAbaUnico(string $base, string $materia): string
    {
        $base = trim($base) !== '' ? trim($base) : 'B-MAT';
        $candidato = $base;
        $n = 2;
        while (isset($this->abasUsadas[mb_strtoupper($candidato, 'UTF-8')])) {
            $sufixo = substr(preg_replace('/[^A-Za-z0-9]/', '', $this->chave($materia)) ?: (string) $n, 0, 4);
            $candidato = substr($base, 0, 26) . '-' . ($sufixo !== '' ? $sufixo : (string) $n);
            $n++;
        }
        $this->abasUsadas[mb_strtoupper($candidato, 'UTF-8')] = true;

        return $candidato;
    }

    /**
     * @param list<string> $nomes
     * @return list<string>
     */
    private function ordenarMaterias(array $nomes): array
    {
        $ordem = [
            'biologia' => 1, 'matematica' => 2, 'geografia' => 3, 'fisica' => 4,
            'filosofia' => 5, 'historia' => 6, 'quimica' => 7,
            'lingua portuguesa' => 8, 'portugues' => 8,
            'ingles' => 9, 'lingua inglesa' => 9,
            'sociologia' => 10, 'educacao fisica' => 11, 'arte' => 12, 'artes' => 12,
        ];
        usort($nomes, function (string $a, string $b) use ($ordem): int {
            $ka = $this->chave($a);
            $kb = $this->chave($b);
            $oa = $ordem[$ka] ?? 100;
            $ob = $ordem[$kb] ?? 100;
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            return strcasecmp($a, $b);
        });

        return array_values($nomes);
    }

    private function sigla(string $nome): string
    {
        $mapa = [
            'arte' => 'ART', 'artes' => 'ART', 'biologia' => 'BIO',
            'educacao fisica' => 'ED.FIS', 'filosofia' => 'FIL', 'fisica' => 'FIS',
            'geografia' => 'GEO', 'historia' => 'HIS', 'ingles' => 'ING',
            'lingua inglesa' => 'ING', 'lingua portuguesa' => 'LIN', 'portugues' => 'LIN',
            'matematica' => 'MAT', 'quimica' => 'QUI', 'sociologia' => 'SOC',
        ];
        $chave = $this->chave($nome);

        return $mapa[$chave] ?? strtoupper(substr(preg_replace('/[^a-z]/', '', $chave) ?: 'MAT', 0, 3));
    }

    private function chave(string $nome): string
    {
        $nome = mb_strtolower(trim($nome));
        $nome = strtr($nome, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
        ]);
        $nome = preg_replace('/[^a-z0-9]+/', ' ', $nome) ?? $nome;

        return trim((string) $nome);
    }

    private function fmt(float $valor): string
    {
        return number_format($valor, 1, ',', '');
    }

    private function txt(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function attr(string $valor): string
    {
        $valor = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $valor) ?? $valor;
        $valor = trim(preg_replace('/\s+/', ' ', $valor) ?? $valor);
        if (function_exists('mb_substr')) {
            $valor = mb_substr($valor, 0, 31, 'UTF-8');
        } else {
            $valor = substr($valor, 0, 31);
        }

        return htmlspecialchars($valor !== '' ? $valor : 'Aba', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
