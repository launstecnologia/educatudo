<?php

namespace App\Modulos\Boletim26Colag\Services;

require_once __DIR__ . '/../Models/Boletim26ColagHomologacao.php';

use App\Core\PeriodoLetivo;
use App\Dominio\Escola\Models\ComponenteCurricular;
use App\Dominio\Pessoas\Services\AlunoTurmaResolver;
use App\Modulos\Boletim26Colag\Models\Boletim26ColagHomologacao;
use App\Modulos\Boletim26Colag\Models\Boletim26ColagLeitura;
use App\Modulos\Boletins\Models\JourneyBoletimLancamento;
use App\Modulos\GruposRegrasNotas\Services\GrupoRegrasNotasService;
use App\Modulos\Presenca\Services\FrequencyService;
use App\Modulos\Provas\Models\ExamBlock;
use App\Modulos\VidaEscolar\Models\SchoolAbsence;

/**
 * Demonstrativo fixo do Colag. A conta já vem pronta: a escola não monta fórmula.
 * 1ª e 2ª série: Bloco A nas semanas ímpares e Bloco B nas pares.
 * 3ª série: uma lista, sem semana, e o ENAC só substitui o resultado quando fica maior.
 */
class Boletim26ColagService
{
    private Boletim26ColagLeitura $leitura;

    /** @var array<int, list<array<string,mixed>>> */
    private array $linhasSerieCache = [];

    /** @var list<array<string,mixed>>|null */
    private ?array $listaEventosFaltas = null;

    /** @var array<int, array<string, array{faltas:float,observacao:string}>> */
    private array $mapaLancamentosFaltas = [];

    /** @var array<string, array<int, array<string, float>>> */
    private array $cacheResultadoOficial = [];

    /** @var array<int, list<array{materia_id:int,componente:string,periodo:int,nota:?float,faltas:?int,ano:int}>> */
    private array $cacheChegadaMeioAno = [];

    public function __construct()
    {
        $this->leitura = new Boletim26ColagLeitura();
    }

    public static function ativo(): bool
    {
        if (!defined('TENANT_SLUG')) {
            return false;
        }

        return strtolower(trim((string) TENANT_SLUG)) === 'colag';
    }

    /**
     * @return array<string,mixed>
     */
    public function montarPainel(int $turmaId, int $alunoId, int $ano, int $serieFiltro = 0, int $bimestreFiltro = 0, ?float $notaMinima = null, bool $abrir = false, string $notaCampo = 'resultado', bool $montarRelatorios = true, array $bimestresLista = []): array
    {
        $ano = ($ano >= 2000 && $ano <= 2100) ? $ano : (int) date('Y');
        $serieFiltro = in_array($serieFiltro, [1, 2, 3], true) ? $serieFiltro : 0;
        $bimestreFiltro = ($bimestreFiltro >= 1 && $bimestreFiltro <= 4) ? $bimestreFiltro : 0;
        $listaBimestres = [];
        foreach ($bimestresLista as $numeroBimestre) {
            $numeroBimestre = (int) $numeroBimestre;
            if ($numeroBimestre >= 1 && $numeroBimestre <= 4) {
                $listaBimestres[$numeroBimestre] = $numeroBimestre;
            }
        }
        ksort($listaBimestres);
        $listaBimestres = array_values($listaBimestres);
        $turmas = $this->turmasMedio();
        $turmasSerie = $serieFiltro > 0
            ? array_values(array_filter(
                $turmas,
                static fn (array $item): bool => (int) ($item['ano_serie'] ?? 0) === $serieFiltro
            ))
            : $turmas;
        $turma = null;
        foreach ($turmasSerie as $item) {
            if ((int) $item['id'] === $turmaId) {
                $turma = $item;
                break;
            }
        }
        if ($turma === null) {
            $turmaId = 0;
        }
        $alunos = $this->alunosDoEscopo($turma !== null ? [$turma] : ($serieFiltro > 0 ? $turmasSerie : []));
        $aluno = null;
        foreach ($alunos as $item) {
            if ((int) $item['id'] === $alunoId) {
                $aluno = $item;
                break;
            }
        }
        $notaCampo = $this->campoNotaMinima($notaCampo);
        $bimestres = $listaBimestres !== [] ? $listaBimestres : ($bimestreFiltro > 0 ? [$bimestreFiltro] : [1, 2, 3, 4]);
        $bimestreFiltro = count($bimestres) === 1 ? $bimestres[0] : 0;
        $relatorios = [];
        $carregarFundo = $abrir && !$montarRelatorios && ($turma !== null || $serieFiltro > 0);
        if ($abrir && $montarRelatorios && ($turma !== null || $serieFiltro > 0)) {
            foreach ($aluno !== null ? [$aluno] : $alunos as $pessoa) {
                $turmaPessoa = $turma ?? $this->turmaPorId($turmasSerie, (int) ($pessoa['turma_id'] ?? 0));
                if ($turmaPessoa === null) {
                    continue;
                }
                $demonstrativos = [];
                foreach ($bimestres as $bimestre) {
                    $demonstrativos[] = $this->montarBimestre($turmaPessoa, (int) $pessoa['id'], $bimestre, $ano);
                }
                $relatorios[] = [
                    'aluno' => $pessoa,
                    'turma' => $turmaPessoa,
                    'demonstrativos' => $demonstrativos,
                ];
            }
        } elseif ($turma === null && $serieFiltro === 0) {
            $relatorios[] = [
                'aluno' => null,
                'turma' => null,
                'demonstrativos' => $this->montarModelo(),
            ];
        }
        if ($abrir && $notaMinima !== null) {
            $relatorios = $this->filtrarRelatoriosAbaixo($relatorios, $notaMinima, $notaCampo);
        }
        $filtroHomologacao = [
            'ano' => $ano,
            'serie' => $serieFiltro,
            'turma_id' => $turmaId,
            'aluno_id' => $aluno !== null ? (int) $aluno['id'] : 0,
            'bimestre' => $bimestreFiltro,
            'nota_campo' => $notaCampo,
            'nota_minima' => $notaMinima,
        ];

        return [
            'ano' => $ano,
            'serie' => $serieFiltro,
            'bimestre' => $bimestreFiltro,
            'bimestres' => $bimestres,
            'nota_minima' => $notaMinima,
            'nota_campo' => $notaCampo,
            'abrir' => $abrir,
            'turma_id' => $turmaId,
            'aluno_id' => $aluno !== null ? (int) $aluno['id'] : 0,
            'turmas' => $turmasSerie,
            'alunos' => $alunos,
            'relatorios' => $relatorios,
            'carregar_fundo' => $carregarFundo,
            'homologacao' => $carregarFundo
                ? ['estado' => 'carregando', 'em' => '', 'usuario' => '']
                : ($abrir ? $this->situacaoHomologacao($filtroHomologacao, $relatorios) : ['estado' => 'fechado']),
            'linhas_planilha' => ($abrir && $montarRelatorios) ? $this->linhasDaPlanilha($relatorios) : [],
        ];
    }

    private function campoNotaMinima(string $campo): string
    {
        if ($campo === 'media_bim' || $campo === 'media_anual') {
            return $campo;
        }

        return 'resultado';
    }

    /**
     * @param list<array<string,mixed>> $relatorios
     * @return list<array<string,mixed>>
     */
    private function filtrarRelatoriosAbaixo(array $relatorios, float $notaMinima, string $notaCampo): array
    {
        if ($notaCampo === 'media_anual') {
            return $this->filtrarPelaMediaAnual($relatorios, $notaMinima);
        }
        $saida = [];
        foreach ($relatorios as $relatorio) {
            $demonstrativos = [];
            foreach (is_array($relatorio['demonstrativos'] ?? null) ? $relatorio['demonstrativos'] : [] as $demo) {
                $blocos = [];
                foreach (is_array($demo['blocos'] ?? null) ? $demo['blocos'] : [] as $bloco) {
                    $linhas = is_array($bloco['linhas'] ?? null) ? $bloco['linhas'] : [];
                    $pais = [];
                    foreach ($linhas as $linha) {
                        if ($this->frentePortuguesOculta($linha)) {
                            continue;
                        }
                        if (!empty($linha['filha']) && $this->notaAbaixo($linha, $notaMinima, $notaCampo)) {
                            $pais[(string) ($linha['pai_nome'] ?? '')] = true;
                        }
                    }
                    $linhas = array_values(array_filter(
                        $linhas,
                        function (array $linha) use ($notaMinima, $notaCampo, $pais): bool {
                            if ($this->frentePortuguesOculta($linha)) {
                                return false;
                            }
                            if ($this->notaAbaixo($linha, $notaMinima, $notaCampo)) {
                                return true;
                            }

                            return empty($linha['filha']) && isset($pais[(string) ($linha['nome'] ?? '')]);
                        }
                    ));
                    if ($linhas === []) {
                        continue;
                    }
                    $bloco['linhas'] = $linhas;
                    $blocos[] = $bloco;
                }
                if ($blocos === []) {
                    continue;
                }
                $demo['blocos'] = $blocos;
                $demonstrativos[] = $demo;
            }
            if ($demonstrativos === []) {
                continue;
            }
            $relatorio['demonstrativos'] = $demonstrativos;
            $saida[] = $relatorio;
        }

        return $saida;
    }

    /**
     * Mantém a matéria cuja média dos bimestres fica abaixo do mínimo.
     *
     * @param list<array<string,mixed>> $relatorios
     * @return list<array<string,mixed>>
     */
    private function filtrarPelaMediaAnual(array $relatorios, float $notaMinima): array
    {
        $saida = [];
        foreach ($relatorios as $relatorio) {
            $somas = [];
            $quantidades = [];
            foreach (is_array($relatorio['demonstrativos'] ?? null) ? $relatorio['demonstrativos'] : [] as $demo) {
                foreach (is_array($demo['blocos'] ?? null) ? $demo['blocos'] : [] as $bloco) {
                    foreach (is_array($bloco['linhas'] ?? null) ? $bloco['linhas'] : [] as $linha) {
                        if ($this->frentePortuguesOculta($linha) || !empty($linha['filha'])) {
                            continue;
                        }
                        $nome = (string) ($linha['nome'] ?? '');
                        if ($nome === '' || !is_numeric($linha['resultado'] ?? null)) {
                            continue;
                        }
                        $somas[$nome] = ($somas[$nome] ?? 0.0) + (float) $linha['resultado'];
                        $quantidades[$nome] = ($quantidades[$nome] ?? 0) + 1;
                    }
                }
            }
            $abaixo = [];
            foreach ($somas as $nome => $soma) {
                $quantidade = (int) ($quantidades[$nome] ?? 0);
                if ($quantidade < 1) {
                    continue;
                }
                $media = round(($soma / $quantidade) * 2, 0, PHP_ROUND_HALF_UP) / 2;
                if ($media < $notaMinima) {
                    $abaixo[$nome] = $media;
                }
            }
            if ($abaixo === []) {
                continue;
            }
            $demonstrativos = [];
            foreach (is_array($relatorio['demonstrativos'] ?? null) ? $relatorio['demonstrativos'] : [] as $demo) {
                $blocos = [];
                foreach (is_array($demo['blocos'] ?? null) ? $demo['blocos'] : [] as $bloco) {
                    $linhas = [];
                    foreach (is_array($bloco['linhas'] ?? null) ? $bloco['linhas'] : [] as $linha) {
                        if ($this->frentePortuguesOculta($linha)) {
                            continue;
                        }
                        $nome = (string) ($linha['nome'] ?? '');
                        if ($nome === '' || !isset($abaixo[$nome])) {
                            continue;
                        }
                        $linha['media_anual'] = $abaixo[$nome];
                        $linhas[] = $linha;
                    }
                    if ($linhas === []) {
                        continue;
                    }
                    $bloco['linhas'] = $linhas;
                    $blocos[] = $bloco;
                }
                if ($blocos === []) {
                    continue;
                }
                $demo['blocos'] = $blocos;
                $demonstrativos[] = $demo;
            }
            if ($demonstrativos === []) {
                continue;
            }
            $relatorio['demonstrativos'] = $demonstrativos;
            $saida[] = $relatorio;
        }

        return $saida;
    }

    /**
     * @param array<string,mixed> $linha
     */
    private function notaAbaixo(array $linha, float $notaMinima, string $notaCampo): bool
    {
        return is_numeric($linha[$notaCampo] ?? null) && (float) $linha[$notaCampo] < $notaMinima;
    }

    /**
     * Gramática, Literatura e Leitura não aparecem. A nota delas não puxa a Língua Portuguesa.
     *
     * @param array<string,mixed> $linha
     */
    private function frentePortuguesOculta(array $linha): bool
    {
        if (empty($linha['filha'])) {
            return false;
        }
        $texto = $this->chave((string) ($linha['nome'] ?? '') . ' ' . (string) ($linha['pai_nome'] ?? ''));

        return str_contains($texto, 'gramatica')
            || str_contains($texto, 'literatura')
            || str_contains($texto, 'leitura')
            || str_contains($texto, 'lingua portuguesa')
            || str_contains($texto, 'portugues');
    }

    /**
     * @param list<array<string,mixed>> $relatorios
     * @return list<array<string,mixed>>
     */
    public function linhasDaPlanilha(array $relatorios): array
    {
        $linhas = [];
        foreach ($relatorios as $relatorio) {
            $aluno = is_array($relatorio['aluno'] ?? null) ? $relatorio['aluno'] : [];
            $turma = is_array($relatorio['turma'] ?? null) ? $relatorio['turma'] : [];
            foreach (is_array($relatorio['demonstrativos'] ?? null) ? $relatorio['demonstrativos'] : [] as $demo) {
                if (!is_array($aluno) || $aluno === [] || !empty($demo['titulo'])) {
                    continue;
                }
                foreach (is_array($demo['blocos'] ?? null) ? $demo['blocos'] : [] as $bloco) {
                    foreach (is_array($bloco['linhas'] ?? null) ? $bloco['linhas'] : [] as $linha) {
                        $linhas[] = [
                            'aluno_id' => (int) ($aluno['id'] ?? 0),
                            'aluno' => (string) ($aluno['nome'] ?? ''),
                            'turma' => (string) ($turma['nome'] ?? ''),
                            'bimestre' => (int) ($demo['bimestre'] ?? 0),
                            'bloco' => (string) ($bloco['titulo'] ?? ''),
                            'materia_id' => (int) ($linha['materia_id'] ?? 0),
                            'materia' => (string) ($linha['nome'] ?? ''),
                            'filha' => !empty($linha['filha']) ? 1 : 0,
                            'media_sem' => $this->notaTexto($linha['media_sem'] ?? null),
                            'prova_bim' => $this->notaTexto($linha['prova_bim'] ?? null),
                            'jornada' => $this->notaTexto($linha['jornada'] ?? null),
                            'media' => $this->notaTexto($linha['media'] ?? null),
                            'enac' => $this->notaTexto($linha['enac'] ?? null),
                            'media_bim' => $this->notaTexto($linha['media_bim'] ?? null),
                            'rec' => $this->notaTexto($linha['rec'] ?? null),
                            'resultado' => $this->notaTexto($linha['resultado'] ?? null),
                            'faltas' => is_numeric($linha['faltas'] ?? null) ? (int) $linha['faltas'] : null,
                        ];
                    }
                }
            }
        }

        return $linhas;
    }

    /**
     * @param array{ano:int,serie:int,turma_id:int,aluno_id:int,bimestre:int,nota_campo:string,nota_minima:?float} $filtro
     * @param list<array<string,mixed>> $relatorios
     * @return array{estado:string,em:string,usuario:string}
     */
    private function situacaoHomologacao(array $filtro, array $relatorios): array
    {
        $vazio = ['estado' => 'nenhuma', 'em' => '', 'usuario' => ''];
        try {
            $repo = new Boletim26ColagHomologacao();
            if (!$repo->tabelaPronta()) {
                return ['estado' => 'sem_tabela', 'em' => '', 'usuario' => ''];
            }
            $ultima = $repo->ultima($filtro);
        } catch (\Throwable $e) {
            return $vazio;
        }
        if ($ultima === null) {
            return $vazio;
        }
        return $this->compararComUltima($ultima, $this->linhasDaPlanilha($relatorios));
    }

    /**
     * @param array{ano:int,serie:int,turma_id:int,aluno_id:int,bimestre:int,nota_campo:string,nota_minima:?float} $filtro
     * @param list<array<string,mixed>> $linhas
     * @return array{estado:string,em:string,usuario:string}
     */
    public function situacaoDasLinhas(array $filtro, array $linhas): array
    {
        $vazio = ['estado' => 'nenhuma', 'em' => '', 'usuario' => ''];
        try {
            $repo = new Boletim26ColagHomologacao();
            if (!$repo->tabelaPronta()) {
                return ['estado' => 'sem_tabela', 'em' => '', 'usuario' => ''];
            }
            $ultima = $repo->ultima($filtro);
        } catch (\Throwable $e) {
            return $vazio;
        }
        if ($ultima === null) {
            return $vazio;
        }

        return $this->compararComUltima($ultima, $linhas);
    }

    /**
     * @param array<string,mixed> $ultima
     * @param list<array<string,mixed>> $linhas
     * @return array{estado:string,em:string,usuario:string}
     */
    private function compararComUltima(array $ultima, array $linhas): array
    {
        $salvo = json_decode((string) ($ultima['resumo_json'] ?? ''), true);
        $igual = is_array($salvo) && $this->resumoCanonico($salvo) === $this->resumoCanonico($linhas);

        return [
            'estado' => $igual ? 'igual' : 'alterada',
            'em' => (string) ($ultima['criado_em'] ?? ''),
            'usuario' => (string) ($ultima['usuario_nome'] ?? ''),
        ];
    }

    /**
     * @param list<array<string,mixed>> $linhas
     */
    private function resumoCanonico(array $linhas): string
    {
        $limpo = [];
        foreach ($linhas as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $limpo[] = [
                'aluno_id' => (int) ($linha['aluno_id'] ?? 0),
                'bimestre' => (int) ($linha['bimestre'] ?? 0),
                'materia_id' => (int) ($linha['materia_id'] ?? 0),
                'filha' => (int) ($linha['filha'] ?? 0),
                'media_sem' => $linha['media_sem'] ?? null,
                'prova_bim' => $linha['prova_bim'] ?? null,
                'jornada' => $linha['jornada'] ?? null,
                'media' => $linha['media'] ?? null,
                'enac' => $linha['enac'] ?? null,
                'media_bim' => $linha['media_bim'] ?? null,
                'rec' => $linha['rec'] ?? null,
                'resultado' => $linha['resultado'] ?? null,
                'faltas' => $linha['faltas'] ?? null,
            ];
        }

        return json_encode($limpo, JSON_UNESCAPED_UNICODE) ?: '';
    }

    private function notaTexto(mixed $valor): ?string
    {
        if (!is_numeric($valor)) {
            return null;
        }

        return number_format((float) $valor, 1, '.', '');
    }

    /**
     * @param array{ano:int,serie:int,turma_id:int,aluno_id:int,bimestre:int,nota_campo:string,nota_minima:?float} $filtro
     * @param list<array<string,mixed>> $relatorios
     */
    public function homologar(array $filtro, array $relatorios, int $usuarioId, string $usuarioNome): bool
    {
        $repo = new Boletim26ColagHomologacao();
        if (!$repo->tabelaPronta()) {
            return false;
        }
        $repo->registrar($filtro, $this->linhasDaPlanilha($relatorios), $usuarioId, $usuarioNome);

        return true;
    }

    /**
     * Resultado final do Colag por bimestre, para o boletim e as notas do detalhe do aluno.
     * Pais e aluno não usam este mapa.
     *
     * @return array<int, array<string, float>>
     */
    public function resultadosPorBimestre(int $alunoId, int $turmaId, int $ano): array
    {
        if (!self::ativo() || $alunoId <= 0 || $ano < 2000) {
            return [];
        }
        if ($turmaId <= 0) {
            $turmaId = (int) (new AlunoTurmaResolver())->getTurmaPrincipal($alunoId);
        }
        if ($turmaId <= 0) {
            return [];
        }
        $chave = $alunoId . ':' . $turmaId . ':' . $ano;
        if (isset($this->cacheResultadoOficial[$chave])) {
            return $this->cacheResultadoOficial[$chave];
        }
        $turma = $this->turmaPorId($this->turmasMedio(), $turmaId);
        if ($turma === null) {
            return $this->cacheResultadoOficial[$chave] = [];
        }
        $mapa = [];
        for ($bimestre = 1; $bimestre <= 4; $bimestre++) {
            $demo = $this->montarBimestre($turma, $alunoId, $bimestre, $ano);
            $porChave = [];
            foreach (is_array($demo['blocos'] ?? null) ? $demo['blocos'] : [] as $bloco) {
                foreach (is_array($bloco['linhas'] ?? null) ? $bloco['linhas'] : [] as $linha) {
                    if (!is_numeric($linha['resultado'] ?? null)) {
                        continue;
                    }
                    $valor = (float) $linha['resultado'];
                    $mid = (int) ($linha['materia_id'] ?? 0);
                    $nome = $this->chave((string) ($linha['nome'] ?? ''));
                    if ($mid > 0) {
                        $porChave['id:' . $mid] = $valor;
                    }
                    if ($nome !== '') {
                        $porChave['nome:' . $nome] = $valor;
                    }
                }
            }
            $mapa[$bimestre] = $porChave;
        }

        return $this->cacheResultadoOficial[$chave] = $mapa;
    }

    /**
     * @param array<int, array<string, float>> $porBimestre
     */
    public function buscarResultado(array $porBimestre, int $bimestre, int $materiaId, string $nome): ?float
    {
        $mapa = $porBimestre[$bimestre] ?? [];
        if ($mapa === []) {
            return null;
        }
        if ($materiaId > 0 && isset($mapa['id:' . $materiaId])) {
            return $mapa['id:' . $materiaId];
        }
        $chave = $this->chave($nome);
        if ($chave !== '' && isset($mapa['nome:' . $chave])) {
            return $mapa['nome:' . $chave];
        }

        return null;
    }

    /**
     * Troca a coluna de resultado final da simulação oficial pelo resultado do Colag.
     *
     * @param array<string,mixed> $simulacao
     * @return array<string,mixed>
     */
    public function aplicarResultadoNaSimulacao(array $simulacao, int $alunoId, int $turmaId, int $bimestre, int $ano): array
    {
        if (!self::ativo() || $bimestre < 1 || $bimestre > 4) {
            return $simulacao;
        }
        $mapa = $this->resultadosPorBimestre($alunoId, $turmaId, $ano)[$bimestre] ?? [];
        if ($mapa === []) {
            return $simulacao;
        }
        foreach (['matriz_materias', 'matriz_materias_boletim'] as $chaveMatriz) {
            if (!is_array($simulacao[$chaveMatriz] ?? null)) {
                continue;
            }
            $simulacao[$chaveMatriz] = $this->aplicarResultadoNaMatriz($simulacao[$chaveMatriz], $mapa);
        }

        return $simulacao;
    }

    /**
     * @param array<string,mixed> $matriz
     * @param array<string, float> $mapa
     * @return array<string,mixed>
     */
    private function aplicarResultadoNaMatriz(array $matriz, array $mapa): array
    {
        $codigos = [];
        foreach (is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [] as $coluna) {
            if (!is_array($coluna)) {
                continue;
            }
            if (!\App\Helpers\BoletimQuadroLayoutHelper::colunaEhResultadoResumo($coluna)
                && !\App\Helpers\BoletimQuadroLayoutHelper::colunaEhResultadoFinalOficial($coluna)) {
                continue;
            }
            $codigo = trim((string) ($coluna['codigo'] ?? ''));
            if ($codigo !== '') {
                $codigos[$codigo] = true;
            }
        }
        if ($codigos === []) {
            return $matriz;
        }
        $linhas = [];
        foreach (is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [] as $linha) {
            if (!is_array($linha)) {
                $linhas[] = $linha;
                continue;
            }
            $mid = (int) ($linha['materia_id'] ?? 0);
            $nome = $this->chave((string) ($linha['materia_nome'] ?? ''));
            $valor = $mid > 0 ? ($mapa['id:' . $mid] ?? null) : null;
            if ($valor === null && $nome !== '') {
                $valor = $mapa['nome:' . $nome] ?? null;
            }
            if ($valor !== null) {
                $notas = is_array($linha['notas'] ?? null) ? $linha['notas'] : [];
                foreach (array_keys($codigos) as $codigo) {
                    $notas[$codigo] = $valor;
                }
                $linha['notas'] = $notas;
            }
            $linhas[] = $linha;
        }
        $matriz['linhas'] = $linhas;

        return $matriz;
    }

    /**
     * @param list<array<string,mixed>> $turmas
     * @return list<array{id:int,nome:string,ra:string,turma_id:int,turma_nome:string}>
     */
    private function alunosDoEscopo(array $turmas): array
    {
        $alunos = [];
        $vistos = [];
        foreach ($turmas as $turma) {
            $turmaId = (int) ($turma['id'] ?? 0);
            foreach ($this->alunosDaTurma($turmaId) as $aluno) {
                $id = (int) $aluno['id'];
                if (isset($vistos[$id])) {
                    continue;
                }
                $vistos[$id] = true;
                $aluno['turma_id'] = $turmaId;
                $aluno['turma_nome'] = (string) ($turma['nome'] ?? '');
                $alunos[] = $aluno;
            }
        }
        usort($alunos, static fn (array $a, array $b): int => strcasecmp((string) $a['nome'], (string) $b['nome']));

        return $alunos;
    }

    /**
     * @param list<array<string,mixed>> $turmas
     * @return array<string,mixed>|null
     */
    private function turmaPorId(array $turmas, int $turmaId): ?array
    {
        foreach ($turmas as $turma) {
            if ((int) ($turma['id'] ?? 0) === $turmaId) {
                return $turma;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $turma
     * @return array<string,mixed>
     */
    private function montarBimestre(array $turma, int $alunoId, int $bimestre, int $ano): array
    {
        $serie = (int) ($turma['ano_serie'] ?? 0);
        $terceira = $serie === 3;
        $linhasBase = $this->linhasDaSerie($serie);
        $blocoDaMateria = [];
        foreach ($linhasBase as $linhaBase) {
            $blocoDaMateria[(int) $linhaBase['materia_id']] = (string) ($linhaBase['bloco'] ?? 'a');
        }
        $provas = $this->juntarRecuperacaoDoSemestre(
            $this->leitura->provasDoBimestre((int) $turma['id'], $bimestre, $ano),
            (int) $turma['id'],
            $bimestre,
            $ano
        );
        $blocoIds = [];
        foreach ($provas as $prova) {
            $id = (int) ($prova['id'] ?? 0);
            if ($id > 0) {
                $blocoIds[] = $id;
            }
        }
        $materiasBloco = $this->leitura->materiasPorBloco($blocoIds);
        $rotulos = $this->rotulosQuadro($blocoIds);
        $notas = $this->indexarNotas($this->leitura->notasLancadas($alunoId, $blocoIds));
        $acertos = $this->leitura->acertosPorBloco($alunoId, $blocoIds);
        $jornada = $this->aplicarJornadaNasMaterias(
            $this->jornadaDoBimestre($alunoId, (int) $turma['id'], $bimestre, $ano),
            $linhasBase
        );
        $faltas = $this->faltasPorMateria($alunoId, (int) $turma['id'], $ano, $bimestre);

        $celulas = [];
        foreach ($provas as $prova) {
            $blocoId = (int) ($prova['id'] ?? 0);
            $peca = $this->pecaDaProva($prova);
            if ($peca === '') {
                continue;
            }
            $semana = $this->semanaDaProva($prova, $rotulos[$blocoId] ?? []);
            $materias = $materiasBloco[$blocoId] ?? [];
            if ($peca === 'semanal' && !$terceira) {
                $this->guardarSemana($celulas, $blocoId, $semana, $materias, $notas, $acertos, $blocoDaMateria);
                continue;
            }
            if ($peca === 'semanal') {
                continue;
            }
            $this->guardarNota($celulas, $peca, $blocoId, $materias, $notas, $acertos);
        }
        $this->completarEnacDaArte($celulas, $linhasBase);

        $semanasBimestre = $terceira ? [] : $this->semanasDoBimestre($bimestre);
        $linhas = [];
        foreach ($linhasBase as $linha) {
            $linhas[] = $this->fecharLinha($linha, $celulas, $jornada, $faltas, $terceira, $semanasBimestre);
        }
        $linhas = $this->fecharGrupos($linhas, $terceira, $semanasBimestre);
        $linhas = array_values(array_filter(
            $linhas,
            fn (array $linha): bool => !$this->ehRedacao((string) ($linha['nome'] ?? ''))
        ));
        $linhas = $this->aplicarSomaDosProfessores($linhas, $alunoId, (int) ($turma['id'] ?? 0), $bimestre, $ano, $terceira);
        $linhas = $this->aplicarNotaChegadaMeioAno($linhas, $alunoId, $bimestre, $ano);
        $blocos = $this->partirBlocos($linhas, $terceira, $bimestre);

        return [
            'bimestre' => $bimestre,
            'serie' => $serie,
            'terceira' => $terceira,
            'blocos' => $blocos,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function turmasMedio(): array
    {
        $out = [];
        foreach ($this->leitura->listarTurmas() as $turma) {
            $ano = $this->anoSerie(
                (string) ($turma['serie_nome'] ?? ''),
                trim((string) ($turma['curso_nome'] ?? '') . ' ' . (string) ($turma['tipo_ensino'] ?? '')),
                (string) ($turma['nome'] ?? '')
            );
            if ($ano < 1) {
                continue;
            }
            $turma['ano_serie'] = $ano;
            $out[] = $turma;
        }
        usort($out, static function (array $a, array $b): int {
            $cmp = ((int) $a['ano_serie']) <=> ((int) $b['ano_serie']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcasecmp((string) $a['nome'], (string) $b['nome']);
        });

        return $out;
    }

    /**
     * @return list<array{id:int,nome:string,ra:string}>
     */
    private function alunosDaTurma(int $turmaId): array
    {
        try {
            $rows = (new AlunoTurmaResolver())->listarAlunosPorTurma($turmaId);
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nome' => trim((string) ($row['nome'] ?? '')),
                'ra' => trim((string) ($row['ra'] ?? $row['matricula'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{materia_id:int,nome:string,bloco:string,filha:bool,pai_nome:string}>
     */
    private function linhasDaSerie(int $serie): array
    {
        if (isset($this->linhasSerieCache[$serie])) {
            return $this->linhasSerieCache[$serie];
        }
        $catalogo = $this->catalogoMaterias();
        $mapas = $this->mapasQuadro();
        $ordemA = ['lingua portuguesa', 'educacao fisica', 'arte', 'ingles', 'fisica', 'biologia', 'sociologia'];
        $ordemB = ['matematica', 'historia', 'geografia', 'quimica', 'filosofia'];
        $linhas = [];
        $vistos = [];
        foreach ($catalogo as $materia) {
            $id = (int) ($materia['id'] ?? 0);
            $nome = trim((string) ($materia['nome'] ?? ''));
            $pai = (int) ($materia['pai_id'] ?? 0);
            if ($id <= 0 || $nome === '' || $pai > 0) {
                continue;
            }
            $bloco = isset($mapas['B'][$id]) && !isset($mapas['A'][$id]) ? 'b' : 'a';
            $fixa = $this->ehPortugues($nome) || $this->ehEducacaoFisica($nome) || $this->ehArte($nome);
            if ($mapas['tem'] && !isset($mapas['A'][$id]) && !isset($mapas['B'][$id]) && !$fixa) {
                continue;
            }
            if (!$mapas['tem']) {
                $chave = $this->chaveOrdem($nome);
                if (in_array($chave, $ordemB, true)) {
                    $bloco = 'b';
                } elseif (!in_array($chave, $ordemA, true) && !$this->ehPortugues($nome)) {
                    continue;
                }
            }
            $linhas[] = [
                'materia_id' => $id,
                'nome' => $nome,
                'bloco' => $serie === 3 ? 'u' : $bloco,
                'filha' => false,
                'pai_nome' => '',
            ];
            $vistos[$id] = true;
            foreach ($catalogo as $filha) {
                $fid = (int) ($filha['id'] ?? 0);
                $fpai = (int) ($filha['pai_id'] ?? 0);
                if ($fid <= 0 || $fpai !== $id || isset($vistos[$fid])) {
                    continue;
                }
                $linhas[] = [
                    'materia_id' => $fid,
                    'nome' => trim((string) ($filha['nome'] ?? '')),
                    'bloco' => $serie === 3 ? 'u' : $bloco,
                    'filha' => true,
                    'pai_nome' => $nome,
                ];
                $vistos[$fid] = true;
            }
        }
        if ($serie === 3) {
            return $this->linhasSerieCache[$serie] = $linhas;
        }
        $peso = [];
        foreach ($ordemA as $i => $nome) {
            $peso['a|' . $nome] = $i;
        }
        foreach ($ordemB as $i => $nome) {
            $peso['b|' . $nome] = $i;
        }
        usort($linhas, function (array $a, array $b) use ($peso): int {
            $ba = (string) $a['bloco'];
            $bb = (string) $b['bloco'];
            if ($ba !== $bb) {
                return $ba <=> $bb;
            }
            $pa = $peso[$ba . '|' . $this->chaveOrdem((string) ($a['filha'] ? $a['pai_nome'] : $a['nome']))] ?? 50;
            $pb = $peso[$bb . '|' . $this->chaveOrdem((string) ($b['filha'] ? $b['pai_nome'] : $b['nome']))] ?? 50;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            $fa = !empty($a['filha']) ? 1 : 0;
            $fb = !empty($b['filha']) ? 1 : 0;
            if ($fa !== $fb) {
                return $fa <=> $fb;
            }

            return strcasecmp((string) $a['nome'], (string) $b['nome']);
        });

        return $this->linhasSerieCache[$serie] = $linhas;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function catalogoMaterias(): array
    {
        try {
            return (new ComponenteCurricular())->getAll(true) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array{tem:bool,A:array<int,true>,B:array<int,true>}
     */
    private function mapasQuadro(): array
    {
        $out = ['tem' => false, 'A' => [], 'B' => []];
        try {
            $bruto = (new GrupoRegrasNotasService())->materiasQuadroPadrao(null);
        } catch (\Throwable $e) {
            return $out;
        }
        foreach (['A', 'B'] as $letra) {
            foreach (is_array($bruto[$letra] ?? null) ? $bruto[$letra] : [] as $materia) {
                $id = (int) ($materia['id'] ?? 0);
                if ($id > 0) {
                    $out[$letra][$id] = true;
                    $out['tem'] = true;
                }
            }
        }

        return $out;
    }

    /**
     * @param list<int> $blocoIds
     * @return array<int, list<array{bloco:string,semana:string}>>
     */
    private function rotulosQuadro(array $blocoIds): array
    {
        if ($blocoIds === []) {
            return [];
        }
        try {
            $rotulos = (new ExamBlock())->rotulosQuadroPorBlocoIds($blocoIds);
        } catch (\Throwable $e) {
            return [];
        }

        return is_array($rotulos) ? $rotulos : [];
    }

    /**
     * @param list<array{bloco_id:int,materia_id:int,nota:float}> $notas
     * @return array<int, array<int, list<float>>>
     */
    private function indexarNotas(array $notas): array
    {
        $out = [];
        foreach ($notas as $nota) {
            $bid = (int) $nota['bloco_id'];
            $mid = (int) $nota['materia_id'];
            if ($bid > 0 && $mid > 0 && is_numeric($nota['nota'] ?? null)) {
                $out[$bid][$mid][] = (float) $nota['nota'];
            }
        }

        return $out;
    }

    /**
     * A recuperação semestral do 1º semestre entra no 1º e no 2º bimestre.
     * A do 2º semestre entra no 3º e no 4º.
     *
     * @param list<array<string,mixed>> $provas
     * @return list<array<string,mixed>>
     */
    private function juntarRecuperacaoDoSemestre(array $provas, int $turmaId, int $bimestre, int $ano): array
    {
        $par = [1 => 2, 2 => 1, 3 => 4, 4 => 3][$bimestre] ?? 0;
        if ($par < 1) {
            return $provas;
        }
        $vistos = [];
        foreach ($provas as $prova) {
            $vistos[(int) ($prova['id'] ?? 0)] = true;
        }
        foreach ($this->leitura->provasDoBimestre($turmaId, $par, $ano) as $prova) {
            $id = (int) ($prova['id'] ?? 0);
            if ($id <= 0 || isset($vistos[$id]) || $this->pecaDaProva($prova) !== 'rec') {
                continue;
            }
            $provas[] = $prova;
            $vistos[$id] = true;
        }

        return $provas;
    }

    /**
     * @param array<string,mixed> $prova
     */
    private function pecaDaProva(array $prova): string
    {
        $blob = $this->chave((string) ($prova['titulo'] ?? '') . ' ' . (string) ($prova['tipo_nome'] ?? ''));
        if (str_contains($blob, 'itinerar')) {
            return '';
        }
        if (str_contains($blob, 'semanal')) {
            return 'semanal';
        }
        if (str_contains($blob, 'enac')) {
            return 'enac';
        }
        if (str_contains($blob, 'recup')) {
            return 'rec';
        }
        if (str_contains($blob, 'bimest') || str_contains($blob, 'prova bim')) {
            return 'prova_bim';
        }
        if ($this->ehLancamentoDeNota($prova) && !$this->semanaPreenchida($prova)) {
            return 'prova_bim';
        }
        if ($this->semanaPreenchida($prova)) {
            return 'semanal';
        }

        return '';
    }

    /**
     * @param array<string,mixed> $prova
     */
    private function ehLancamentoDeNota(array $prova): bool
    {
        return strtolower(trim((string) ($prova['formato_evento'] ?? ''))) === 'lancamento_nota';
    }

    /**
     * @param array<string,mixed> $prova
     */
    private function semanaPreenchida(array $prova): bool
    {
        $semana = (int) ($prova['semana'] ?? 0);

        return $semana >= 1 && $semana <= 6;
    }

    /**
     * @param list<array{bloco:string,semana:string}> $rotulos
     */
    private function semanaDaProva(array $prova, array $rotulos): int
    {
        $semana = (int) ($prova['semana'] ?? 0);
        $letra = '';
        foreach ($rotulos as $rotulo) {
            if (!is_array($rotulo)) {
                continue;
            }
            $nome = $this->chave((string) ($rotulo['bloco'] ?? ''));
            if (str_contains($nome, 'bloco b') || preg_match('/\bb\b/', $nome) === 1) {
                $letra = 'b';
            } elseif (str_contains($nome, 'bloco a') || preg_match('/\ba\b/', $nome) === 1) {
                $letra = 'a';
            }
            if (preg_match('/^s(\d+)$/', strtolower(trim((string) ($rotulo['semana'] ?? ''))), $m) === 1) {
                $semana = (int) $m[1];
            }
        }
        $impares = [1, 3, 5];
        $pares = [2, 4, 6];
        if ($letra === 'b' && in_array($semana, $impares, true)) {
            $idx = array_search($semana, $impares, true);
            return $pares[$idx] ?? 2;
        }
        if ($letra === 'a' && in_array($semana, $pares, true)) {
            $idx = array_search($semana, $pares, true);
            return $impares[$idx] ?? 1;
        }
        if ($semana >= 1 && $semana <= 6) {
            return $semana;
        }

        return 0;
    }

    /**
     * @param array<int, array<string,mixed>> $celulas
     * @param list<int> $materias
     * @param array<int, array<int, list<float>>> $notas
     * @param list<array{bloco_id:int,materia_id:int,n:int,q:int,nota:?float}> $acertos
     * @param array<int, string> $blocoDaMateria
     */
    private function guardarSemana(array &$celulas, int $blocoId, int $semana, array $materias, array $notas, array $acertos, array $blocoDaMateria): void
    {
        $porMateria = [];
        foreach ($acertos as $acerto) {
            if ((int) $acerto['bloco_id'] !== $blocoId) {
                continue;
            }
            $mid = (int) $acerto['materia_id'];
            if ($mid <= 0) {
                continue;
            }
            if (!isset($porMateria[$mid])) {
                $porMateria[$mid] = ['n' => 0, 'q' => 0, 'nota' => null];
            }
            $porMateria[$mid]['n'] += (int) $acerto['n'];
            $porMateria[$mid]['q'] += (int) $acerto['q'];
            if ($acerto['nota'] !== null) {
                $porMateria[$mid]['nota'] = (float) $acerto['nota'];
            }
        }
        $destinos = $materias !== [] ? $materias : array_keys($porMateria);
        foreach ($notas[$blocoId] ?? [] as $mid => $nota) {
            if (!in_array((int) $mid, $destinos, true)) {
                $destinos[] = (int) $mid;
            }
        }
        foreach ($destinos as $mid) {
            $mid = (int) $mid;
            if ($mid <= 0) {
                continue;
            }
            $sem = $this->semanaNoBloco($semana, (string) ($blocoDaMateria[$mid] ?? 'a'));
            $nq = $porMateria[$mid] ?? ['n' => 0, 'q' => 0, 'nota' => null];
            if (!isset($celulas[$mid]['semanas'][$sem])) {
                $celulas[$mid]['semanas'][$sem] = ['n' => 0, 'q' => 0, 'notas' => []];
            }
            $celulas[$mid]['semanas'][$sem]['n'] += (int) $nq['n'];
            $celulas[$mid]['semanas'][$sem]['q'] += (int) $nq['q'];
            $lista = $notas[$blocoId][$mid] ?? [];
            if (is_array($lista) && $lista !== []) {
                foreach ($lista as $nota) {
                    if (is_numeric($nota)) {
                        $celulas[$mid]['semanas'][$sem]['notas'][] = (float) $nota;
                    }
                }
            } elseif ($nq['nota'] !== null && (int) $nq['q'] <= 0) {
                $celulas[$mid]['semanas'][$sem]['notas'][] = (float) $nq['nota'];
            }
        }
    }

    /**
     * @param array<int, array<string,mixed>> $celulas
     * @param list<int> $materias
     * @param array<int, array<int, list<float>>> $notas
     * @param list<array{bloco_id:int,materia_id:int,n:int,q:int,nota:?float}> $acertos
     */
    private function guardarNota(array &$celulas, string $peca, int $blocoId, array $materias, array $notas, array $acertos): void
    {
        $valores = [];
        foreach ($notas[$blocoId] ?? [] as $mid => $lista) {
            foreach (is_array($lista) ? $lista : [] as $nota) {
                if (is_numeric($nota)) {
                    $valores[(int) $mid][] = (float) $nota;
                }
            }
        }
        $comLancamento = $valores;
        foreach ($acertos as $acerto) {
            if ((int) $acerto['bloco_id'] !== $blocoId || $acerto['nota'] === null) {
                continue;
            }
            $mid = (int) $acerto['materia_id'];
            if ($mid > 0 && !isset($comLancamento[$mid])) {
                $valores[$mid][] = (float) $acerto['nota'];
            }
        }
        $destinos = $materias !== [] ? $materias : array_keys($valores);
        foreach (array_keys($valores) as $midValor) {
            $midValor = (int) $midValor;
            if ($midValor > 0 && !in_array($midValor, $destinos, true)) {
                $destinos[] = $midValor;
            }
        }
        foreach ($destinos as $mid) {
            $mid = (int) $mid;
            if ($mid <= 0 || empty($valores[$mid])) {
                continue;
            }
            $total = array_sum($valores[$mid]);
            $celulas[$mid][$peca][] = $peca === 'prova_bim'
                ? $total
                : $total / count($valores[$mid]);
        }
    }

    /**
     * Uma nota só, pela conclusão das jornadas do bimestre na tabela de faixas.
     */
    private function jornadaDoBimestre(int $alunoId, int $turmaId, int $bimestre, int $ano): ?float
    {
        try {
            $jornada = new JourneyBoletimLancamento();
            $ids = $jornada->listarIdsPorBimestre([$bimestre], $ano);
            $notas = $jornada->notasPorMateriaAluno(
                $alunoId,
                $turmaId,
                $ids,
                null,
                null,
                10.0,
                false,
                'media',
                $this->faixasJornadaColag(),
                true,
                null,
                true
            );
        } catch (\Throwable $e) {
            return null;
        }
        $nota = $notas['nota_unica_valor_padrao'] ?? null;

        return is_numeric($nota) ? (float) $nota : null;
    }

    /**
     * A mesma jornada entra na média de cada matéria. Educação Física fica de fora.
     *
     * @param list<array<string,mixed>> $linhas
     * @return array<int, float>
     */
    private function aplicarJornadaNasMaterias(?float $nota, array $linhas): array
    {
        if ($nota === null) {
            return [];
        }
        $nota = $this->meio($nota);
        $out = [];
        foreach ($linhas as $linha) {
            if ($this->ehEducacaoFisica((string) ($linha['nome'] ?? ''))) {
                continue;
            }
            $mid = (int) ($linha['materia_id'] ?? 0);
            if ($mid > 0) {
                $out[$mid] = $nota;
            }
        }

        return $out;
    }

    /**
     * @return list<array{percentual_min:int,nota:float}>
     */
    private function faixasJornadaColag(): array
    {
        return [
            ['percentual_min' => 100, 'nota' => 10.0],
            ['percentual_min' => 90, 'nota' => 9.5],
            ['percentual_min' => 80, 'nota' => 9.0],
            ['percentual_min' => 70, 'nota' => 8.5],
            ['percentual_min' => 60, 'nota' => 8.0],
            ['percentual_min' => 50, 'nota' => 7.5],
            ['percentual_min' => 40, 'nota' => 7.0],
            ['percentual_min' => 30, 'nota' => 6.5],
            ['percentual_min' => 20, 'nota' => 6.0],
            ['percentual_min' => 10, 'nota' => 5.5],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function faltasPorMateria(int $alunoId, int $turmaId, int $ano, int $bimestre): array
    {
        $evento = $this->faltasDoEvento($alunoId, $turmaId, $ano, $bimestre);
        $diario = $this->faltasDoDiario($alunoId, $turmaId, $ano, $bimestre);
        if ($evento === []) {
            return $diario;
        }
        foreach ($diario as $mid => $qtd) {
            if (!isset($evento[$mid])) {
                $evento[$mid] = $qtd;
            }
        }

        return $evento;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function listaEventosFaltas(): array
    {
        if ($this->listaEventosFaltas !== null) {
            return $this->listaEventosFaltas;
        }
        try {
            $this->listaEventosFaltas = (new SchoolAbsence())->listEventos(300);
        } catch (\Throwable $e) {
            $this->listaEventosFaltas = [];
        }

        return $this->listaEventosFaltas;
    }

    /**
     * @return array<string, array{faltas:float,observacao:string}>
     */
    private function mapaLancamentosDoEvento(int $eventoId): array
    {
        if ($eventoId <= 0) {
            return [];
        }
        if (!isset($this->mapaLancamentosFaltas[$eventoId])) {
            try {
                $this->mapaLancamentosFaltas[$eventoId] = (new SchoolAbsence())->getLancamentosMapByEvento($eventoId);
            } catch (\Throwable $e) {
                $this->mapaLancamentosFaltas[$eventoId] = [];
            }
        }

        return $this->mapaLancamentosFaltas[$eventoId];
    }

    /**
     * Lançamento de faltas do boletim, o mesmo do demonstrativo oficial.
     *
     * @return array<int, int>
     */
    private function faltasDoEvento(int $alunoId, int $turmaId, int $ano, int $bimestre): array
    {
        try {
            $daTurma = 0;
            $geral = 0;
            foreach ($this->listaEventosFaltas() as $evento) {
                if ((int) ($evento['ano_letivo'] ?? 0) !== $ano) {
                    continue;
                }
                if (!preg_match('/([1-4])/', (string) ($evento['bimestre'] ?? ''), $marca) || (int) $marca[1] !== $bimestre) {
                    continue;
                }
                $id = (int) ($evento['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $turmas = array_map('intval', (array) ($evento['turmas_ids'] ?? []));
                if ($turmas === []) {
                    $geral = $geral > 0 ? $geral : $id;
                    continue;
                }
                if (in_array($turmaId, $turmas, true)) {
                    $daTurma = $id;
                    break;
                }
            }
            $eventoId = $daTurma > 0 ? $daTurma : $geral;
            if ($eventoId <= 0) {
                return [];
            }
            $out = [];
            foreach ($this->mapaLancamentosDoEvento($eventoId) as $chave => $item) {
                $partes = explode('_', (string) $chave, 2);
                if ((int) ($partes[0] ?? 0) !== $alunoId) {
                    continue;
                }
                $mid = (int) ($partes[1] ?? 0);
                if ($mid <= 0 || !is_numeric($item['faltas'] ?? null)) {
                    continue;
                }
                $out[$mid] = (int) round((float) $item['faltas']);
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int, int>
     */
    private function faltasDoDiario(int $alunoId, int $turmaId, int $ano, int $bimestre): array
    {
        try {
            $intervalo = PeriodoLetivo::intervalo($ano, $bimestre);
            $inicio = (string) ($intervalo['inicio'] ?? '');
            $fim = (string) ($intervalo['fim'] ?? '');
            if ($inicio === '' || $fim === '') {
                return [];
            }
            $mapa = (new FrequencyService())->alunoPorComponente($alunoId, $turmaId, $inicio, $fim);
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($mapa as $mid => $info) {
            $out[(int) $mid] = (int) ($info['faltas'] ?? 0);
        }

        return $out;
    }

    /**
     * @param array{materia_id:int,nome:string,bloco:string,filha:bool,pai_nome:string} $linha
     * @param array<int, array<string,mixed>> $celulas
     * @param array<int, float> $jornada
     * @param array<int, int> $faltas
     * @return array<string,mixed>
     */
    private function fecharLinha(array $linha, array $celulas, array $jornada, array $faltas, bool $terceira, array $semanasContam = [1, 2, 3, 4, 5, 6]): array
    {
        $mid = (int) $linha['materia_id'];
        $celula = $celulas[$mid] ?? [];
        $semanas = [];
        $totalN = 0;
        $totalQ = 0;
        $notasSemana = [];
        foreach ($semanasContam as $numero) {
            $semana = is_array($celula['semanas'][$numero] ?? null) ? $celula['semanas'][$numero] : null;
            $n = $semana !== null ? (int) $semana['n'] : null;
            $q = $semana !== null ? (int) $semana['q'] : null;
            if ($q !== null && $q > 0) {
                $totalN += (int) $n;
                $totalQ += $q;
            }
            foreach (is_array($semana['notas'] ?? null) ? $semana['notas'] : [] as $nota) {
                if (is_numeric($nota)) {
                    $notasSemana[] = (float) $nota;
                }
            }
            $semanas[$numero] = [
                'n' => ($q !== null && $q > 0) ? $n : null,
                'q' => ($q !== null && $q > 0) ? $q : null,
            ];
        }
        $mediaSem = null;
        if ($totalQ > 0) {
            $mediaSem = $this->meio(10 * $totalN / $totalQ);
        } elseif ($notasSemana !== []) {
            $mediaSem = $this->meio(array_sum($notasSemana) / count($notasSemana));
        }
        $prova = $this->somarLista($celula['prova_bim'] ?? []);
        $enac = $this->mediaLista($celula['enac'] ?? []);
        $rec = $this->mediaLista($celula['rec'] ?? []);
        $notaJornada = isset($jornada[$mid]) ? $this->meio((float) $jornada[$mid]) : null;
        $falta = $faltas[$mid] ?? null;
        if ($this->ehEducacaoFisica((string) ($linha['nome'] ?? ''))) {
            return $this->linhaSoProvaBim($linha, $semanas, $totalN, $totalQ, $mediaSem, $prova, $rec, $falta, $terceira);
        }
        if ($terceira) {
            $fechamento = $this->fecharMediaTerceira($prova, $notaJornada, $enac, $rec);

            return $linha + [
                'semanas' => [],
                'total_n' => null,
                'total_q' => null,
                'media_sem' => null,
                'prova_bim' => $prova,
                'jornada' => $notaJornada,
                'media' => $fechamento['media'],
                'enac' => $enac,
                'media_bim' => $fechamento['media_bim'],
                'rec' => $rec,
                'resultado' => $fechamento['resultado'],
                'faltas' => $falta,
            ];
        }
        $media = $this->mediaPresentes([$mediaSem, $prova, $notaJornada]);
        $mediaBim = $this->mediaBimComEnac($media, $enac);
        $resultado = $mediaBim;
        if ($rec !== null && ($resultado === null || $rec > $resultado)) {
            $resultado = $rec;
        }

        return $linha + [
            'semanas' => $semanas,
            'total_n' => $totalQ > 0 ? $totalN : null,
            'total_q' => $totalQ > 0 ? $totalQ : null,
            'media_sem' => $mediaSem,
            'prova_bim' => $prova,
            'jornada' => $notaJornada,
            'media' => $media,
            'enac' => $enac,
            'media_bim' => $mediaBim,
            'rec' => $rec,
            'resultado' => $resultado,
            'faltas' => $falta,
        ];
    }

    /**
     * A mãe soma a prova bimestral das frentes. N, Q e faltas também somam.
     * Jornada, ENAC e recuperação continuam na média das frentes.
     *
     * @param list<array<string,mixed>> $linhas
     * @return list<array<string,mixed>>
     */
    private function fecharGrupos(array $linhas, bool $terceira, array $semanasContam = [1, 2, 3, 4, 5, 6]): array
    {
        $grupos = [];
        foreach ($linhas as $i => $linha) {
            if (empty($linha['filha'])) {
                continue;
            }
            $pai = (string) ($linha['pai_nome'] ?? '');
            if ($pai === '') {
                continue;
            }
            $grupos[$pai][] = $i;
        }
        foreach ($linhas as $i => $linha) {
            if (!empty($linha['filha'])) {
                continue;
            }
            $idxs = $grupos[(string) $linha['nome']] ?? [];
            if ($idxs === []) {
                continue;
            }
            $filhas = [];
            foreach ($idxs as $idx) {
                $filhas[] = $linhas[$idx];
            }
            $linhas[$i] = $this->juntarFilhas($linha, $filhas, $terceira, $semanasContam);
        }

        return $linhas;
    }

    /**
     * @param array<string,mixed> $pai
     * @param list<array<string,mixed>> $filhas
     * @return array<string,mixed>
     */
    private function juntarFilhas(array $pai, array $filhas, bool $terceira, array $semanasContam = [1, 2, 3, 4, 5, 6]): array
    {
        $provaFilhas = [];
        foreach ($filhas as $filha) {
            if (is_numeric($filha['prova_bim'] ?? null)) {
                $provaFilhas[] = (float) $filha['prova_bim'];
            }
        }
        if ($provaFilhas !== []) {
            $pai['prova_bim'] = $this->meio(array_sum($provaFilhas));
        }
        foreach (['jornada', 'enac', 'rec'] as $campo) {
            $vals = [];
            foreach ($filhas as $filha) {
                if (is_numeric($filha[$campo] ?? null)) {
                    $vals[] = (float) $filha[$campo];
                }
            }
            if ($vals !== []) {
                $pai[$campo] = $this->meio(array_sum($vals) / count($vals));
            }
        }
        $faltas = 0;
        $temFalta = false;
        foreach ($filhas as $filha) {
            if ($filha['faltas'] !== null) {
                $faltas += (int) $filha['faltas'];
                $temFalta = true;
            }
        }
        if ($temFalta) {
            $pai['faltas'] = $faltas;
        }
        if ($terceira) {
            $fechamento = $this->fecharMediaTerceira(
                is_numeric($pai['prova_bim'] ?? null) ? (float) $pai['prova_bim'] : null,
                is_numeric($pai['jornada'] ?? null) ? (float) $pai['jornada'] : null,
                is_numeric($pai['enac'] ?? null) ? (float) $pai['enac'] : null,
                is_numeric($pai['rec'] ?? null) ? (float) $pai['rec'] : null
            );
            $pai['media'] = $fechamento['media'];
            $pai['media_bim'] = $fechamento['media_bim'];
            $pai['resultado'] = $fechamento['resultado'];

            return $this->aplicarSoProvaSeEducacaoFisica($pai, true);
        }
        $totalN = 0;
        $totalQ = 0;
        $semanas = [];
        foreach ($semanasContam as $numero) {
            $n = 0;
            $q = 0;
            foreach ($filhas as $filha) {
                $cel = $filha['semanas'][$numero] ?? null;
                if (!is_array($cel) || !is_numeric($cel['q'] ?? null) || (int) $cel['q'] <= 0) {
                    continue;
                }
                $n += (int) ($cel['n'] ?? 0);
                $q += (int) $cel['q'];
            }
            $semanas[$numero] = ['n' => $q > 0 ? $n : null, 'q' => $q > 0 ? $q : null];
            $totalN += $n;
            $totalQ += $q;
        }
        if ($totalQ > 0) {
            $pai['semanas'] = $semanas;
            $pai['total_n'] = $totalN;
            $pai['total_q'] = $totalQ;
            $pai['media_sem'] = $this->meio(10 * $totalN / $totalQ);
        } else {
            $mediaFilhas = $this->mediaCampo($filhas, 'media_sem');
            if ($mediaFilhas !== null) {
                $pai['media_sem'] = $mediaFilhas;
            }
        }
        $pai['media'] = $this->mediaPresentes([$pai['media_sem'], $pai['prova_bim'], $pai['jornada']]);
        $pai['media_bim'] = $this->mediaBimComEnac(
            is_numeric($pai['media'] ?? null) ? (float) $pai['media'] : null,
            is_numeric($pai['enac'] ?? null) ? (float) $pai['enac'] : null
        );
        $pai['resultado'] = $pai['media_bim'];
        if (is_numeric($pai['rec']) && ($pai['resultado'] === null || (float) $pai['rec'] > (float) $pai['resultado'])) {
            $pai['resultado'] = $pai['rec'];
        }

        return $this->aplicarSoProvaSeEducacaoFisica($pai, false);
    }

    /**
     * Na mesma matéria, soma a nota que cada professor da turma lançou.
     * No lançamento da coordenação, a tela repete a nota gravada em quem ainda não tem a própria.
     * Fora isso, professor sem nota não entra. Itinerário e nota de outra turma ficam de fora.
     *
     * @param list<array<string,mixed>> $linhas
     * @return list<array<string,mixed>>
     */
    private function aplicarSomaDosProfessores(array $linhas, int $alunoId, int $turmaId, int $bimestre, int $ano, bool $terceira): array
    {
        $notas = $this->leitura->notasPorProfessorNoBimestre($alunoId, $bimestre, $ano);
        if ($notas === []) {
            return $linhas;
        }
        $porBloco = [];
        $blocos = [];
        $copiaNota = [];
        foreach ($notas as $nota) {
            if ($this->pecaDaProva($nota) !== 'prova_bim') {
                continue;
            }
            $bid = (int) $nota['bloco_id'];
            $mid = (int) $nota['materia_id'];
            $pid = (int) $nota['professor_id'];
            $tid = (int) ($nota['turma_id'] ?? 0);
            if ($bid <= 0 || $mid <= 0 || !is_numeric($nota['nota'] ?? null)) {
                continue;
            }
            if ($turmaId > 0 && $tid > 0 && $tid !== $turmaId) {
                continue;
            }
            $blocos[$bid] = $bid;
            if ((string) ($nota['configuracao_nota'] ?? '') === 'coordenacao_calcula') {
                $copiaNota[$bid] = true;
            }
            $valor = (float) $nota['nota'];
            $atual = $porBloco[$bid][$mid][$pid] ?? null;
            if ($atual === null || $valor > $atual) {
                $porBloco[$bid][$mid][$pid] = $valor;
            }
        }
        if ($porBloco === []) {
            return $linhas;
        }
        $ligados = [];
        foreach ($this->leitura->professoresDaMateriaNaTurma(array_values($blocos), $turmaId) as $prof) {
            $ligados[$prof['bloco_id']][$prof['materia_id']][$prof['professor_id']] = (int) $prof['professor_id'];
        }
        $notaPorProfessor = [];
        foreach ($porBloco as $bid => $materias) {
            foreach ($materias as $mid => $porProfessor) {
                $gravadas = [];
                foreach ($porProfessor as $pid => $valor) {
                    if ((int) $pid > 0) {
                        $gravadas[] = (float) $valor;
                    }
                }
                if ($gravadas === []) {
                    continue;
                }
                $referencia = max($gravadas);
                $professores = array_values($ligados[$bid][$mid] ?? []);
                if ($professores === []) {
                    $professores = array_map('intval', array_keys($porProfessor));
                }
                $repeteNota = !empty($copiaNota[$bid]);
                foreach ($professores as $pid) {
                    $pid = (int) $pid;
                    if ($pid <= 0) {
                        continue;
                    }
                    if (!isset($porProfessor[$pid]) && !$repeteNota) {
                        continue;
                    }
                    $valor = isset($porProfessor[$pid]) ? (float) $porProfessor[$pid] : $referencia;
                    $anterior = $notaPorProfessor[$mid][$pid] ?? null;
                    if ($anterior === null || $valor > $anterior) {
                        $notaPorProfessor[$mid][$pid] = $valor;
                    }
                }
            }
        }
        foreach ($linhas as $i => $linha) {
            if (!empty($linha['filha'])) {
                continue;
            }
            $id = (int) ($linha['materia_id'] ?? 0);
            $notasMateria = $notaPorProfessor[$id] ?? [];
            if ($notasMateria === []) {
                continue;
            }
            $linhas[$i] = $this->trocarProvaBim($linha, $this->meio(array_sum($notasMateria)), $terceira);
        }

        return $linhas;
    }

    /**
     * @param array<string,mixed> $linha
     * @return array<string,mixed>
     */
    private function trocarProvaBim(array $linha, float $prova, bool $terceira): array
    {
        $linha['prova_bim'] = $prova;
        if ($this->ehEducacaoFisica((string) ($linha['nome'] ?? ''))) {
            $rec = is_numeric($linha['rec'] ?? null) ? (float) $linha['rec'] : null;
            $resultado = $prova;
            if ($rec !== null && $rec > $resultado) {
                $resultado = $rec;
            }
            $linha['jornada'] = null;
            $linha['enac'] = null;
            $linha['media'] = $prova;
            $linha['media_bim'] = $prova;
            $linha['resultado'] = $resultado;

            return $linha;
        }
        $jornada = is_numeric($linha['jornada'] ?? null) ? (float) $linha['jornada'] : null;
        $enac = is_numeric($linha['enac'] ?? null) ? (float) $linha['enac'] : null;
        $rec = is_numeric($linha['rec'] ?? null) ? (float) $linha['rec'] : null;
        if ($terceira) {
            $fechamento = $this->fecharMediaTerceira($prova, $jornada, $enac, $rec);
            $linha['media'] = $fechamento['media'];
            $linha['media_bim'] = $fechamento['media_bim'];
            $linha['resultado'] = $fechamento['resultado'];

            return $linha;
        }
        $mediaSem = is_numeric($linha['media_sem'] ?? null) ? (float) $linha['media_sem'] : null;
        $media = $this->mediaPresentes([$mediaSem, $prova, $jornada]);
        $mediaBim = $this->mediaBimComEnac($media, $enac);
        $resultado = $mediaBim;
        if ($rec !== null && ($resultado === null || $rec > $resultado)) {
            $resultado = $rec;
        }
        $linha['media'] = $media;
        $linha['media_bim'] = $mediaBim;
        $linha['resultado'] = $resultado;

        return $linha;
    }

    /**
     * Educação Física não entra jornada nem ENAC. O resultado é a prova bimestral.
     *
     * @param array<string,mixed> $pai
     * @return array<string,mixed>
     */
    private function aplicarSoProvaSeEducacaoFisica(array $pai, bool $terceira): array
    {
        if (!$this->ehEducacaoFisica((string) ($pai['nome'] ?? ''))) {
            return $pai;
        }
        $prova = is_numeric($pai['prova_bim'] ?? null) ? (float) $pai['prova_bim'] : null;
        $rec = is_numeric($pai['rec'] ?? null) ? (float) $pai['rec'] : null;
        $resultado = $prova;
        if ($rec !== null && ($resultado === null || $rec > $resultado)) {
            $resultado = $rec;
        }
        $pai['jornada'] = null;
        $pai['enac'] = null;
        $pai['media'] = $prova;
        $pai['media_bim'] = $prova;
        $pai['resultado'] = $resultado;

        return $pai;
    }

    /**
     * @param array<string,mixed> $linha
     * @param array<int, array{n:?int,q:?int}> $semanas
     * @return array<string,mixed>
     */
    private function linhaSoProvaBim(array $linha, array $semanas, int $totalN, int $totalQ, ?float $mediaSem, ?float $prova, ?float $rec, ?int $falta, bool $terceira): array
    {
        $resultado = $prova;
        if ($rec !== null && ($resultado === null || $rec > $resultado)) {
            $resultado = $rec;
        }

        return $linha + [
            'semanas' => $terceira ? [] : $semanas,
            'total_n' => (!$terceira && $totalQ > 0) ? $totalN : null,
            'total_q' => (!$terceira && $totalQ > 0) ? $totalQ : null,
            'media_sem' => $terceira ? null : $mediaSem,
            'prova_bim' => $prova,
            'jornada' => null,
            'media' => $prova,
            'enac' => null,
            'media_bim' => $prova,
            'rec' => $rec,
            'resultado' => $resultado,
            'faltas' => $falta,
        ];
    }

    /**
     * @param list<array<string,mixed>> $linhas
     * @return list<array{titulo:string,semanas:list<int>,linhas:list<array<string,mixed>>}>
     */
    private function partirBlocos(array $linhas, bool $terceira, ?int $bimestre = null): array
    {
        if ($terceira) {
            return [[
                'titulo' => 'Matérias',
                'semanas' => [],
                'linhas' => $linhas,
            ]];
        }
        $a = [];
        $b = [];
        foreach ($linhas as $linha) {
            if (($linha['bloco'] ?? '') === 'b') {
                $b[] = $linha;
            } else {
                $a[] = $linha;
            }
        }
        $semanas = $this->semanasDoBimestre($bimestre ?? 1);

        return [
            ['titulo' => 'Matérias Bloco A', 'semanas' => $this->semanasDoBloco($semanas, 'a'), 'linhas' => $a],
            ['titulo' => 'Matérias Bloco B', 'semanas' => $this->semanasDoBloco($semanas, 'b'), 'linhas' => $b],
        ];
    }

    /**
     * 1ª e 2ª série: o 1º bimestre tem as seis semanas. Os outros param no S4.
     *
     * @return list<int>
     */
    private function semanasDoBimestre(?int $bimestre): array
    {
        if ($bimestre === 1 || $bimestre === null) {
            return [1, 2, 3, 4, 5, 6];
        }

        return [1, 2, 3, 4];
    }

    /**
     * @param list<int> $semanas
     * @return list<int>
     */
    private function semanasDoBloco(array $semanas, string $bloco): array
    {
        $out = [];
        foreach ($semanas as $semana) {
            $par = $semana % 2 === 0;
            if (($bloco === 'b' && $par) || ($bloco !== 'b' && !$par)) {
                $out[] = $semana;
            }
        }

        return $out;
    }

    /**
     * @param list<float> $valores
     */
    private function mediaLista(array $valores): ?float
    {
        $nums = [];
        foreach ($valores as $valor) {
            if (is_numeric($valor)) {
                $nums[] = (float) $valor;
            }
        }
        if ($nums === []) {
            return null;
        }

        return $this->meio(array_sum($nums) / count($nums));
    }

    /**
     * @param list<float|null> $valores
     */
    private function mediaPresentes(array $valores): ?float
    {
        return $this->mediaLista($valores);
    }

    /**
     * @param list<array<string,mixed>> $linhas
     */
    private function mediaCampo(array $linhas, string $campo): ?float
    {
        $vals = [];
        foreach ($linhas as $linha) {
            if (is_numeric($linha[$campo] ?? null)) {
                $vals[] = (float) $linha[$campo];
            }
        }

        return $this->mediaLista($vals);
    }

    private function semanaNoBloco(int $semana, string $bloco): int
    {
        $impares = [1 => 2, 3 => 4, 5 => 6];
        $pares = [2 => 1, 4 => 3, 6 => 5];
        if ($semana < 1 || $semana > 6) {
            return $bloco === 'b' ? 2 : 1;
        }
        if ($bloco === 'b' && isset($impares[$semana])) {
            return $impares[$semana];
        }
        if ($bloco === 'a' && isset($pares[$semana])) {
            return $pares[$semana];
        }

        return $semana;
    }

    /**
     * 3ª série: média da avaliação bimestral com a jornada.
     * A média final recebe o ENAC só quando supera essa média.
     * A recuperação substitui o resultado final só quando é maior.
     *
     * @return array{media:?float,media_bim:?float,resultado:?float}
     */
    private function fecharMediaTerceira(?float $prova, ?float $jornada, ?float $enac, ?float $rec): array
    {
        $media = $this->mediaPresentes([$prova, $jornada]);
        $mediaFinal = $this->mediaBimComEnac($media, $enac);
        $resultado = $mediaFinal;
        if ($rec !== null && ($resultado === null || $rec > $resultado)) {
            $resultado = $rec;
        }

        return [
            'media' => $media,
            'media_bim' => $mediaFinal,
            'resultado' => $resultado,
        ];
    }

    /**
     * Média bimestral fica com (MÉDIA + ENAC) / 2 só quando isso supera a MÉDIA.
     */
    private function mediaBimComEnac(?float $media, ?float $enac): ?float
    {
        if ($media === null) {
            return null;
        }
        if ($enac === null) {
            return $media;
        }
        $comEnac = $this->meio(($media + $enac) / 2);

        return $comEnac > $media ? $comEnac : $media;
    }

    private function meio(float $valor): float
    {
        return round($valor * 2, 0, PHP_ROUND_HALF_UP) / 2;
    }

    /**
     * Quadro vazio, no formato oficial, para a escola ver o modelo antes de escolher o aluno.
     *
     * @return list<array<string,mixed>>
     */
    private function montarModelo(): array
    {
        return [
            $this->modeloDaSerie(1, 'Modelo — 1ª e 2ª série — Bloco A e Bloco B'),
            $this->modeloDaSerie(3, 'Modelo — 3ª série — uma lista, sem prova semanal'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function modeloDaSerie(int $serie, string $titulo): array
    {
        $terceira = $serie === 3;
        $linhas = [];
        foreach ($this->linhasDoModelo($serie) as $linha) {
            $linhas[] = $linha + [
                'semanas' => [],
                'total_n' => null,
                'total_q' => null,
                'media_sem' => null,
                'prova_bim' => null,
                'jornada' => null,
                'media' => null,
                'enac' => null,
                'media_bim' => null,
                'rec' => null,
                'resultado' => null,
                'faltas' => null,
            ];
        }

        return [
            'bimestre' => 0,
            'serie' => $serie,
            'terceira' => $terceira,
            'titulo' => $titulo,
            'blocos' => $this->partirBlocos($linhas, $terceira),
        ];
    }

    /**
     * @return list<array{materia_id:int,nome:string,bloco:string,filha:bool,pai_nome:string}>
     */
    private function linhasDoModelo(int $serie): array
    {
        $linhas = $this->linhasDaSerie($serie);
        if ($linhas !== []) {
            return $linhas;
        }
        $filhas = ['Gramática', 'Literatura', 'Leitura e Interpretação'];
        $blocoA = ['Língua Portuguesa', 'Gramática', 'Literatura', 'Leitura e Interpretação', 'Educação Física', 'Arte', 'Inglês', 'Física', 'Biologia', 'Sociologia'];
        $blocoB = ['Matemática', 'História', 'Geografia', 'Química', 'Filosofia'];
        $out = [];
        $id = 1;
        $nomes = $serie === 3 ? array_merge($blocoA, $blocoB) : $blocoA;
        foreach ($nomes as $nome) {
            $out[] = [
                'materia_id' => $id++,
                'nome' => $nome,
                'bloco' => $serie === 3 ? 'u' : 'a',
                'filha' => in_array($nome, $filhas, true),
                'pai_nome' => '',
            ];
        }
        if ($serie !== 3) {
            foreach ($blocoB as $nome) {
                $out[] = [
                    'materia_id' => $id++,
                    'nome' => $nome,
                    'bloco' => 'b',
                    'filha' => false,
                    'pai_nome' => '',
                ];
            }
        }

        return $out;
    }

    private function anoSerie(string $serie, string $curso, string $turma): int
    {
        $junto = $this->chave($curso . ' ' . $serie . ' ' . $turma);
        $fundamental = str_contains($junto, 'fundamental') || str_contains($junto, 'infantil');
        $medio = str_contains($junto, 'medio') || preg_match('/\bem\b/', $junto) === 1;
        if ($fundamental && !$medio) {
            return 0;
        }
        $numero = $this->numeroSerie($this->chave($serie . ' ' . $turma));

        return $numero;
    }

    private function numeroSerie(string $texto): int
    {
        if (preg_match('/\bterceir[ao]\b|(?:^|\s)3(?:\s|[ao]|$)/', $texto) === 1) {
            return 3;
        }
        if (preg_match('/\bsegund[ao]\b|(?:^|\s)2(?:\s|[ao]|$)/', $texto) === 1) {
            return 2;
        }
        if (preg_match('/\bprimeir[ao]\b|(?:^|\s)1(?:\s|[ao]|$)/', $texto) === 1) {
            return 1;
        }

        return 0;
    }

    /**
     * Dois professores da mesma matéria lançam em cadastros diferentes.
     * A prova bimestral da linha visível passa a ser a soma desses cadastros.
     *
     * @param array<int, array<string,mixed>> $celulas
     * @param list<array<string,mixed>> $linhasBase
     */
    private function somarProvaDeProfessoresDaMesmaMateria(array &$celulas, array &$linhasBase): void
    {
        $oficial = [];
        $filha = [];
        foreach ($linhasBase as $linha) {
            $id = (int) ($linha['materia_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if (!empty($linha['filha'])) {
                $filha[$id] = true;
                continue;
            }
            $nome = trim((string) ($linha['nome'] ?? ''));
            if ($nome !== '') {
                $oficial[$id] = $nome;
            }
        }
        $nomePorId = $oficial;
        foreach ($this->catalogoMaterias() as $materia) {
            $id = (int) ($materia['id'] ?? 0);
            $nome = trim((string) ($materia['nome'] ?? ''));
            if ($id > 0 && $nome !== '' && !isset($nomePorId[$id])) {
                $nomePorId[$id] = $nome;
            }
        }
        $remover = [];
        $jaUsado = [];
        foreach ($oficial as $id => $nome) {
            $chave = $this->chaveOrdem($nome);
            if ($chave === '' || isset($jaUsado[$chave])) {
                if ($chave !== '' && isset($jaUsado[$chave])) {
                    $this->anexarProva($celulas, (int) $jaUsado[$chave], $celulas[$id]['prova_bim'] ?? []);
                    $remover[$id] = true;
                }
                continue;
            }
            $jaUsado[$chave] = $id;
        }
        foreach ($nomePorId as $id => $nome) {
            $id = (int) $id;
            if ($id <= 0 || isset($oficial[$id]) || !empty($filha[$id])) {
                continue;
            }
            $destino = 0;
            foreach ($oficial as $oficialId => $oficialNome) {
                if (isset($remover[$oficialId])) {
                    continue;
                }
                if ($this->mesmoNomeDeMateria($oficialNome, $nome)) {
                    $destino = (int) $oficialId;
                    break;
                }
            }
            if ($destino <= 0) {
                continue;
            }
            $this->anexarProva($celulas, $destino, $celulas[$id]['prova_bim'] ?? []);
        }
        if ($remover === []) {
            return;
        }
        $linhasBase = array_values(array_filter(
            $linhasBase,
            static fn (array $linha): bool => !isset($remover[(int) ($linha['materia_id'] ?? 0)])
        ));
    }

    /**
     * @param array<int, array<string,mixed>> $celulas
     * @param list<float|int|string|null> $notas
     */
    private function anexarProva(array &$celulas, int $destino, array $notas): void
    {
        foreach ($notas as $nota) {
            if (is_numeric($nota)) {
                $celulas[$destino]['prova_bim'][] = (float) $nota;
            }
        }
    }

    private function mesmoNomeDeMateria(string $oficial, string $outra): bool
    {
        $a = $this->chaveOrdem($oficial);
        $b = $this->chaveOrdem($outra);
        if ($a === '' || $b === '') {
            return false;
        }

        return $a === $b || str_starts_with($b, $a . ' ');
    }

    /**
     * @param list<float|int|string|null> $valores
     */
    private function somarLista(array $valores): ?float
    {
        $nums = [];
        foreach ($valores as $valor) {
            if (is_numeric($valor)) {
                $nums[] = (float) $valor;
            }
        }
        if ($nums === []) {
            return null;
        }

        return $this->meio(array_sum($nums));
    }

    private function ehPortugues(string $nome): bool
    {
        $chave = $this->chave($nome);

        return str_contains($chave, 'lingua portuguesa') || $chave === 'portugues';
    }

    private function ehRedacao(string $nome): bool
    {
        return str_contains($this->chave($nome), 'redacao');
    }

    private function ehEducacaoFisica(string $nome): bool
    {
        $chave = $this->chave($nome);

        return str_contains($chave, 'educacao fisica') || preg_match('/\bed fisica\b/', $chave) === 1;
    }

    private function ehArte(string $nome): bool
    {
        $chave = $this->chave($nome);

        return $chave === 'arte' || $chave === 'artes' || str_starts_with($chave, 'artes ');
    }

    /**
     * Quem chegou no meio do ano tem a nota da outra escola no lugar do cálculo.
     *
     * @param list<array<string,mixed>> $linhas
     * @return list<array<string,mixed>>
     */
    private function aplicarNotaChegadaMeioAno(array $linhas, int $alunoId, int $bimestre, int $ano): array
    {
        $notas = $this->notasChegadaDoAluno($alunoId);
        if ($notas === []) {
            return $linhas;
        }
        foreach ($linhas as $i => $linha) {
            $achada = $this->buscarNotaChegada($notas, $linha, $bimestre, $ano);
            if ($achada === null) {
                continue;
            }
            if ($achada['nota'] !== null) {
                $valor = $this->meio($achada['nota']);
                $linhas[$i]['resultado'] = $valor;
                $linhas[$i]['media_bim'] = $valor;
            }
            if ($achada['faltas'] !== null) {
                $linhas[$i]['faltas'] = $achada['faltas'];
            }
        }

        return $linhas;
    }

    /**
     * @return list<array{materia_id:int,componente:string,periodo:int,nota:?float,faltas:?int,ano:int}>
     */
    private function notasChegadaDoAluno(int $alunoId): array
    {
        if ($alunoId <= 0) {
            return [];
        }
        if (!isset($this->cacheChegadaMeioAno[$alunoId])) {
            try {
                $this->cacheChegadaMeioAno[$alunoId] = $this->leitura->notasChegadaMeioAno($alunoId);
            } catch (\Throwable $e) {
                $this->cacheChegadaMeioAno[$alunoId] = [];
            }
        }

        return $this->cacheChegadaMeioAno[$alunoId];
    }

    /**
     * @param list<array{materia_id:int,componente:string,periodo:int,nota:?float,faltas:?int,ano:int}> $notas
     * @param array<string,mixed> $linha
     * @return array{materia_id:int,componente:string,periodo:int,nota:?float,faltas:?int,ano:int}|null
     */
    private function buscarNotaChegada(array $notas, array $linha, int $bimestre, int $ano): ?array
    {
        $mid = (int) ($linha['materia_id'] ?? 0);
        $nome = $this->chaveOrdem((string) ($linha['nome'] ?? ''));
        $porNome = null;
        foreach ($notas as $nota) {
            if ((int) $nota['periodo'] !== $bimestre) {
                continue;
            }
            $anoNota = (int) $nota['ano'];
            if ($anoNota >= 2000 && $ano >= 2000 && $anoNota !== $ano) {
                continue;
            }
            if ($mid > 0 && (int) $nota['materia_id'] === $mid) {
                return $nota;
            }
            if ($porNome === null && $nome !== '' && $this->chaveOrdem((string) $nota['componente']) === $nome) {
                $porNome = $nota;
            }
        }

        return $porNome;
    }

    /**
     * A nota do ENAC pode estar em outro cadastro de Arte/Artes.
     * A linha do boletim usa um id só; copia a nota lançada para essa linha.
     *
     * @param array<int, array<string,mixed>> $celulas
     * @param list<array<string,mixed>> $linhasBase
     */
    private function completarEnacDaArte(array &$celulas, array $linhasBase): void
    {
        $idsLinha = [];
        foreach ($linhasBase as $linha) {
            if ($this->ehArte((string) ($linha['nome'] ?? ''))) {
                $id = (int) ($linha['materia_id'] ?? 0);
                if ($id > 0) {
                    $idsLinha[$id] = true;
                }
            }
        }
        if ($idsLinha === []) {
            return;
        }
        $notas = [];
        foreach ($this->catalogoMaterias() as $materia) {
            $id = (int) ($materia['id'] ?? 0);
            if ($id <= 0 || !$this->ehArte((string) ($materia['nome'] ?? ''))) {
                continue;
            }
            foreach (is_array($celulas[$id]['enac'] ?? null) ? $celulas[$id]['enac'] : [] as $nota) {
                if (is_numeric($nota)) {
                    $notas[] = (float) $nota;
                }
            }
        }
        if ($notas === []) {
            $unico = $this->enacUnicoDasOutrasMaterias($celulas, $linhasBase);
            if ($unico === null) {
                return;
            }
            $notas = [$unico];
        }
        foreach (array_keys($idsLinha) as $id) {
            foreach (is_array($celulas[$id]['enac'] ?? null) ? $celulas[$id]['enac'] : [] as $nota) {
                if (is_numeric($nota)) {
                    continue 2;
                }
            }
            $celulas[$id]['enac'] = $notas;
        }
    }

    /**
     * Na 2ª e na 3ª série o ENAC da Arte não veio no cadastro da matéria,
     * mas as outras disciplinas do bimestre receberam a mesma nota.
     *
     * @param array<int, array<string,mixed>> $celulas
     * @param list<array<string,mixed>> $linhasBase
     */
    private function enacUnicoDasOutrasMaterias(array $celulas, array $linhasBase): ?float
    {
        $vistos = [];
        foreach ($linhasBase as $linha) {
            $nome = (string) ($linha['nome'] ?? '');
            if ($this->ehArte($nome) || $this->ehEducacaoFisica($nome) || !empty($linha['filha'])) {
                continue;
            }
            $id = (int) ($linha['materia_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            foreach (is_array($celulas[$id]['enac'] ?? null) ? $celulas[$id]['enac'] : [] as $nota) {
                if (!is_numeric($nota)) {
                    continue;
                }
                $vistos[number_format($this->meio((float) $nota), 1, '.', '')] = $this->meio((float) $nota);
            }
        }
        if (count($vistos) !== 1) {
            return null;
        }

        return (float) reset($vistos);
    }

    private function chaveOrdem(string $nome): string
    {
        $chave = $this->chave($nome);

        return $chave === 'artes' ? 'arte' : $chave;
    }

    private function chave(string $nome): string
    {
        $nome = mb_strtolower(trim($nome));
        $nome = strtr($nome, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
            'ª' => 'a', 'º' => 'o',
        ]);
        $nome = preg_replace('/[^a-z0-9]+/', ' ', $nome) ?? $nome;

        return trim((string) $nome);
    }
}
