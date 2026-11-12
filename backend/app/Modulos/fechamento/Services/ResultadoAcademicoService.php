<?php

namespace App\Modulos\Fechamento\Services;

use App\Modulos\RegrasAcademicas\Models\RegraAcademica;
use App\Modulos\Presenca\Services\FrequencyService;

/**
 * EducaTudo - Motor único de resultado acadêmico.
 *
 * Consome notas já compostas (evento de provas/notas/boletim), aplica a regra
 * vigente (média, recuperação, frequência) e devolve a situação. Boletim,
 * histórico e relatórios devem consultar daqui — não recalcular na view.
 */
class ResultadoAcademicoService
{
    public const SITUACOES = [
        'em_andamento' => 'Em andamento',
        'aprovado' => 'Aprovado',
        'aprovado_recuperacao' => 'Aprovado após recuperação',
        'aprovado_conselho' => 'Aprovado pelo Conselho',
        'reprovado_rendimento' => 'Reprovado por rendimento',
        'reprovado_frequencia' => 'Reprovado por frequência',
        'recuperacao' => 'Recuperação',
        'exame_final' => 'Exame final',
        'progressao_parcial' => 'Progressão parcial/Dependência',
        'dependencia' => 'Dependência',
        'dispensado' => 'Dispensado',
        'aproveitamento' => 'Aproveitamento de estudos',
        'transferido' => 'Transferido',
        'desistente' => 'Desistente/Cancelado',
        'nao_avaliado' => 'Não avaliado',
        'resultado_pendente' => 'Resultado pendente',
    ];

    private RegraAcademica $model;
    private FrequencyService $frequencia;
    /** @var array<string, ?float> */
    private array $freqCache = [];
    /** @var array<string, list<array<string,mixed>>> */
    private array $regrasCachePorAno = [];

    public function __construct(?RegraAcademica $model = null, ?FrequencyService $frequencia = null)
    {
        $this->model = $model ?? new RegraAcademica();
        $this->frequencia = $frequencia ?? new FrequencyService();
    }

    /**
     * Escolhe a regra mais específica para o contexto (ano, curso, série, componente, período).
     *
     * @param array{
     *   ano_letivo?:int|null,
     *   curso_id?:int|null,
     *   serie_id?:int|null,
     *   matriz_curricular_id?:int|null,
     *   materia_id?:int|null,
     *   agrupamento_id?:int|null,
     *   periodo_tipo?:string|null,
     *   periodo_numero?:int|null
     * } $contexto
     * @return array<string,mixed>|null
     */
    public function resolverRegra(array $contexto): ?array
    {
        if (!$this->model->schemaPronto()) {
            return null;
        }
        $ano = isset($contexto['ano_letivo']) ? (int) $contexto['ano_letivo'] : 0;
        $cacheKey = $ano > 0 ? (string) $ano : 'all';
        if (!isset($this->regrasCachePorAno[$cacheKey])) {
            $this->regrasCachePorAno[$cacheKey] = $this->model->listarAtivas($ano > 0 ? $ano : null);
        }

        $melhor = null;
        $melhorScore = -1;
        foreach ($this->regrasCachePorAno[$cacheKey] as $regra) {
            $score = $this->pontuarRegra($regra, $contexto);
            if ($score < 0) {
                continue;
            }
            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhor = $regra;
            }
        }
        return $melhor;
    }

    /**
     * @param array<string,mixed> $regra
     * @param array<string,mixed> $contexto
     */
    private function pontuarRegra(array $regra, array $contexto): int
    {
        $anoCtx = (int) ($contexto['ano_letivo'] ?? 0);
        $anoRegra = isset($regra['ano_letivo']) && $regra['ano_letivo'] !== null && $regra['ano_letivo'] !== ''
            ? (int) $regra['ano_letivo']
            : 0;
        if ($anoRegra > 0 && $anoCtx > 0 && $anoRegra !== $anoCtx) {
            return -1;
        }

        $matchOrNull = static function ($regraVal, $ctxVal): ?int {
            $r = ($regraVal === null || $regraVal === '' || (int) $regraVal === 0) ? 0 : (int) $regraVal;
            $c = ($ctxVal === null || $ctxVal === '' || (int) $ctxVal === 0) ? 0 : (int) $ctxVal;
            if ($r === 0) {
                return 1;
            }
            if ($c === 0) {
                return null;
            }
            return $r === $c ? 10 : null;
        };

        $curso = $matchOrNull($regra['curso_id'] ?? null, $contexto['curso_id'] ?? null);
        if ($curso === null) {
            return -1;
        }
        $serie = $this->pontuarSeries($regra, $contexto['serie_id'] ?? null);
        if ($serie === null) {
            return -1;
        }
        $matriz = $matchOrNull($regra['matriz_curricular_id'] ?? null, $contexto['matriz_curricular_id'] ?? null);
        if ($matriz === null) {
            return -1;
        }
        $agrupamentoRegra = (int) ($regra['agrupamento_id'] ?? 0);
        $agrupamentoCtx = (int) ($contexto['agrupamento_id'] ?? 0);
        if ($agrupamentoRegra > 0) {
            if ($agrupamentoCtx !== $agrupamentoRegra) {
                return -1;
            }
            $agrupamento = 10;
            $materia = 1;
        } else {
            $agrupamento = 1;
            $materia = $matchOrNull($regra['materia_id'] ?? null, $contexto['materia_id'] ?? null);
            if ($materia === null) {
                return -1;
            }
        }

        $periodoTipoCtx = strtolower(trim((string) ($contexto['periodo_tipo'] ?? 'bimestre')));
        $periodoTipoRegra = strtolower(trim((string) ($regra['periodo_tipo'] ?? 'bimestre')));
        $tiposIguais = $periodoTipoRegra === $periodoTipoCtx
            || $periodoTipoRegra === 'etapa_unica'
            || $periodoTipoRegra === ''
            || $periodoTipoCtx === ''
            || $periodoTipoRegra === 'ano'
            || $periodoTipoCtx === 'ano';
        if (!$tiposIguais) {
            return -1;
        }
        $numRegra = isset($regra['periodo_numero']) && $regra['periodo_numero'] !== null && $regra['periodo_numero'] !== ''
            ? (int) $regra['periodo_numero']
            : 0;
        $numCtx = isset($contexto['periodo_numero']) ? (int) $contexto['periodo_numero'] : 0;
        if ($numRegra > 0 && ($numCtx <= 0 || $numRegra !== $numCtx)) {
            return -1;
        }

        $score = 0;
        $score += $anoRegra > 0 ? 50 : 5;
        $score += $curso === 10 ? 40 : $curso;
        $score += $serie === 10 ? 80 : $serie;
        $score += $matriz === 10 ? 30 : $matriz;
        $score += $agrupamento === 10 ? 160 : $agrupamento;
        $score += $materia === 10 ? 160 : $materia;
        $score += $numRegra > 0 ? 20 : 2;
        return $score;
    }

    /**
     * Série vazia vale para todas. Com uma ou mais séries, só vale para as marcadas.
     *
     * @param array<string,mixed> $regra
     */
    private function pontuarSeries(array $regra, mixed $serieContexto): ?int
    {
        $series = [];
        foreach ((array) ($regra['series_ids'] ?? []) as $serieId) {
            $serieId = (int) $serieId;
            if ($serieId > 0) {
                $series[$serieId] = true;
            }
        }
        if ($series === [] && (int) ($regra['serie_id'] ?? 0) > 0) {
            $series[(int) $regra['serie_id']] = true;
        }
        $contexto = (int) $serieContexto;
        if ($series === []) {
            return 1;
        }
        if ($contexto <= 0) {
            return null;
        }

        return isset($series[$contexto]) ? 10 : null;
    }

    /**
     * Monta uma regra sintética a partir do evento de boletim (fallback).
     *
     * @param array<string,mixed> $eventoBoletim
     * @return array<string,mixed>
     */
    public function regraFallbackDoBoletim(array $eventoBoletim): array
    {
        $minima = isset($eventoBoletim['nota_minima_aprovacao']) && is_numeric($eventoBoletim['nota_minima_aprovacao'])
            ? (float) $eventoBoletim['nota_minima_aprovacao']
            : 6.0;
        $round = $this->normalizeRoundMode((string) ($eventoBoletim['round_mode'] ?? 'none'));
        return [
            'id' => 0,
            'nome' => (string) ($eventoBoletim['nome'] ?? 'Evento de boletim'),
            'versao' => 0,
            'media_minima' => $minima,
            'frequencia_minima' => FrequencyService::MINIMO_LEGAL,
            'usar_frequencia' => 0,
            'round_mode' => $round,
            'decimal_places' => \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais(
                $eventoBoletim['decimal_places'] ?? 2
            ),
            'formula_final' => $eventoBoletim['formula_final'] ?? null,
            'recuperacao_tipo' => 'nenhuma',
            'recuperacao_composicao' => 'maior_nota',
            'componentes_sem_nota' => 0,
            'aprovacao_so_frequencia' => 0,
            'situacoes_json' => json_encode([
                'aprovado' => 'Aprovado',
                'reprovado_rendimento' => 'Reprovado',
            ], JSON_UNESCAPED_UNICODE),
            '_fallback_boletim' => 1,
        ];
    }

    /**
     * Classifica a situação acadêmica de um componente / aluno.
     *
     * @param array{
     *   media?:float|null,
     *   media_antes_rec?:float|null,
     *   recuperacao?:float|null,
     *   frequencia_percentual?:float|null,
     *   tem_nota?:bool,
     *   homologado?:bool,
     *   conselho?:bool,
     *   situacao_matricula?:string|null,
     *   turma_id?:int,
     *   aluno_id?:int,
     *   materia_id?:int,
     *   data_inicio?:string|null,
     *   data_fim?:string|null
     * } $entrada
     * @param array<string,mixed>|null $regra
     * @return array{
     *   situacao:string,
     *   rotulo:string,
     *   media_final:?float,
     *   frequencia_percentual:?float,
     *   regra_id:int,
     *   regra_versao:int,
     *   criterios:array{rendimento:?bool,frequencia:?bool,recuperacao:bool}
     * }
     */
    public function avaliar(array $entrada, ?array $regra = null): array
    {
        $regra = $regra ?? [];
        $matricula = strtolower(trim((string) ($entrada['situacao_matricula'] ?? '')));
        if (in_array($matricula, ['transferido', 'transferencia'], true)) {
            return $this->montarRetorno('transferido', $entrada, $regra, null, null);
        }
        if (in_array($matricula, ['desistente', 'cancelado', 'evadido'], true)) {
            return $this->montarRetorno('desistente', $entrada, $regra, null, null);
        }

        $especial = strtolower(trim((string) ($entrada['situacao_especial'] ?? '')));
        if ($especial === 'dispensado') {
            return $this->montarRetorno('dispensado', $entrada, $regra, null, null);
        }
        if ($especial === 'aproveitamento') {
            return $this->montarRetorno('aproveitamento', $entrada, $regra, null, null);
        }
        $mediaEsp = isset($entrada['media']) && is_numeric($entrada['media']) ? (float) $entrada['media'] : null;
        if ($especial === 'progressao_parcial') {
            return $this->montarRetorno('progressao_parcial', $entrada, $regra, $mediaEsp, null);
        }
        if ($especial === 'dependencia') {
            return $this->montarRetorno('dependencia', $entrada, $regra, $mediaEsp, null);
        }
        if ($especial === 'transferencia') {
            return $this->montarRetorno('transferido', $entrada, $regra, null, null);
        }
        if ($especial === 'classificacao') {
            return $this->montarRetorno('aproveitamento', $entrada, $regra, $mediaEsp, null);
        }

        $media = isset($entrada['media']) && is_numeric($entrada['media']) ? (float) $entrada['media'] : null;
        $mediaAntes = isset($entrada['media_antes_rec']) && is_numeric($entrada['media_antes_rec'])
            ? (float) $entrada['media_antes_rec']
            : null;
        $rec = isset($entrada['recuperacao']) && is_numeric($entrada['recuperacao'])
            ? (float) $entrada['recuperacao']
            : null;
        $temNota = array_key_exists('tem_nota', $entrada)
            ? (bool) $entrada['tem_nota']
            : ($media !== null);

        $freq = isset($entrada['frequencia_percentual']) && is_numeric($entrada['frequencia_percentual'])
            ? (float) $entrada['frequencia_percentual']
            : null;
        if ($freq === null && !empty($regra['usar_frequencia'])) {
            $freq = $this->obterFrequencia($entrada);
        }

        $soFreq = !empty($regra['aprovacao_so_frequencia']);
        $semNotaOk = !empty($regra['componentes_sem_nota']);
        $minima = isset($regra['media_minima']) && is_numeric($regra['media_minima'])
            ? (float) $regra['media_minima']
            : 6.0;
        $freqMin = isset($regra['frequencia_minima']) && is_numeric($regra['frequencia_minima'])
            ? (float) $regra['frequencia_minima']
            : FrequencyService::MINIMO_LEGAL;
        $usarFreq = !empty($regra['usar_frequencia']);
        $recTipo = (string) ($regra['recuperacao_tipo'] ?? 'periodo');

        if (!empty($entrada['conselho'])) {
            return $this->montarRetorno('aprovado_conselho', $entrada, $regra, $media, $freq);
        }

        if (!$temNota) {
            if ($soFreq && $usarFreq && $freq !== null) {
                $sit = $freq >= $freqMin ? 'aprovado' : 'reprovado_frequencia';
                return $this->montarRetorno($sit, $entrada, $regra, null, $freq);
            }
            if ($semNotaOk) {
                return $this->montarRetorno('nao_avaliado', $entrada, $regra, null, $freq);
            }
            return $this->montarRetorno('nao_avaliado', $entrada, $regra, null, $freq);
        }

        if ($usarFreq && $freq !== null && $freq < $freqMin) {
            return $this->montarRetorno('reprovado_frequencia', $entrada, $regra, $media, $freq);
        }

        if ($media === null) {
            return $this->montarRetorno('resultado_pendente', $entrada, $regra, null, $freq);
        }

        $mediaOrig = $mediaAntes ?? $media;
        if ($rec !== null) {
            $aplic = $this->aplicarRecuperacao($mediaOrig, $rec, $regra);
            if ($aplic['media_final'] !== null) {
                $media = (float) $aplic['media_final'];
            }
        }

        $passouRendimento = $media >= $minima;
        if ($passouRendimento) {
            $usouRec = $rec !== null && $mediaOrig < $minima;
            $sit = $usouRec ? 'aprovado_recuperacao' : 'aprovado';
            return $this->montarRetorno($sit, $entrada, $regra, $media, $freq);
        }

        if ($rec === null && $recTipo !== 'nenhuma') {
            $sit = $recTipo === 'final' ? 'exame_final' : 'recuperacao';
            return $this->montarRetorno($sit, $entrada, $regra, $media, $freq);
        }

        return $this->montarRetorno('reprovado_rendimento', $entrada, $regra, $media, $freq);
    }

    /**
     * @param array<string,mixed> $entrada
     * @param array<string,mixed> $regra
     * @return array<string,mixed>
     */
    private function montarRetorno(string $situacao, array $entrada, array $regra, ?float $media, ?float $freq): array
    {
        $minima = isset($regra['media_minima']) && is_numeric($regra['media_minima'])
            ? (float) $regra['media_minima']
            : 6.0;
        $freqMin = isset($regra['frequencia_minima']) && is_numeric($regra['frequencia_minima'])
            ? (float) $regra['frequencia_minima']
            : FrequencyService::MINIMO_LEGAL;

        return [
            'situacao' => $situacao,
            'rotulo' => $this->rotuloSituacao($situacao, $regra),
            'media_final' => $media,
            'frequencia_percentual' => $freq,
            'regra_id' => (int) ($regra['id'] ?? 0),
            'regra_versao' => (int) ($regra['versao'] ?? 0),
            'criterios' => [
                'rendimento' => $media === null ? null : $media >= $minima,
                'frequencia' => $freq === null ? null : $freq >= $freqMin,
                'recuperacao' => in_array($situacao, ['aprovado_recuperacao', 'recuperacao', 'exame_final'], true),
            ],
        ];
    }

    /**
     * @param array<string,mixed>|null $regra
     */
    public function rotuloSituacao(string $codigo, ?array $regra = null): string
    {
        $custom = [];
        $raw = $regra['situacoes_json'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $custom = $decoded;
            }
        } elseif (is_array($raw)) {
            $custom = $raw;
        }
        if (isset($custom[$codigo]) && is_string($custom[$codigo]) && trim($custom[$codigo]) !== '') {
            return trim($custom[$codigo]);
        }
        return self::SITUACOES[$codigo] ?? $codigo;
    }

    /**
     * Aplica a composição de recuperação sobre a média (para quem ainda não
     * fechou a nota no evento). O boletim já costuma ter feito isso na fórmula.
     *
     * @param array<string,mixed> $regra
     * @return array{media_final:?float, usou_recuperacao:bool}
     */
    public function aplicarRecuperacao(?float $media, ?float $recuperacao, array $regra): array
    {
        if ($media === null && $recuperacao === null) {
            return ['media_final' => null, 'usou_recuperacao' => false];
        }
        if ($recuperacao === null) {
            return ['media_final' => $media, 'usou_recuperacao' => false];
        }
        if ($media === null) {
            return ['media_final' => $recuperacao, 'usou_recuperacao' => true];
        }

        $comp = (string) ($regra['recuperacao_composicao'] ?? 'maior_nota');
        $minima = isset($regra['media_minima']) && is_numeric($regra['media_minima'])
            ? (float) $regra['media_minima']
            : 6.0;
        $round = $this->normalizeRoundMode((string) ($regra['round_mode'] ?? 'none'));
        $this->definirCasasArredondamento(
            (int) ($regra['decimal_places'] ?? $this->casasArredondamento)
        );

        $final = $media;
        $usou = false;
        if ($comp === 'substitui') {
            $final = $recuperacao;
            $usou = true;
        } elseif ($comp === 'composicao') {
            $final = ($media + $recuperacao) / 2;
            $usou = true;
        } elseif ($comp === 'formula') {
            $formula = trim((string) ($regra['formula_final'] ?? ''));
            if ($formula !== '') {
                $r = $this->avaliarFormula($formula, ['media' => $media, 'rec' => $recuperacao, 'recuperacao' => $recuperacao]);
                if (!empty($r['ok'])) {
                    $final = (float) $r['valor'];
                    $usou = true;
                }
            } else {
                $final = max($media, $recuperacao);
                $usou = $recuperacao > $media;
            }
        } else {
            // maior_nota: só substitui se a rec for maior; opcionalmente só se estava abaixo.
            $final = max($media, $recuperacao);
            $usou = $recuperacao > $media;
            if ($media >= $minima && $recuperacao < $media) {
                $final = $media;
                $usou = false;
            }
        }

        return [
            'media_final' => $this->applyRoundMode($final, $round),
            'usou_recuperacao' => $usou,
        ];
    }

    /**
     * @param array<string,mixed> $entrada
     */
    private function obterFrequencia(array $entrada): ?float
    {
        $alunoId = (int) ($entrada['aluno_id'] ?? 0);
        $turmaId = (int) ($entrada['turma_id'] ?? 0);
        $inicio = substr((string) ($entrada['data_inicio'] ?? ''), 0, 10);
        $fim = substr((string) ($entrada['data_fim'] ?? ''), 0, 10);
        if ($alunoId <= 0 || $turmaId <= 0 || $inicio === '' || $fim === '') {
            return null;
        }
        $materiaId = (int) ($entrada['materia_id'] ?? 0);
        $cacheKey = $turmaId . ':' . $alunoId . ':' . $materiaId . ':' . $inicio . ':' . $fim;
        if (array_key_exists($cacheKey, $this->freqCache)) {
            return $this->freqCache[$cacheKey];
        }
        $this->freqCache[$cacheKey] = $this->frequencia->alunoPercentual(
            $alunoId,
            $turmaId,
            $inicio,
            $fim,
            $materiaId > 0 ? $materiaId : null
        );
        return $this->freqCache[$cacheKey];
    }

    /** Casas usadas quando round_mode = places (ex.: 7,95 → 8,0 com 1 casa). */
    private int $casasArredondamento = 2;

    public function definirCasasArredondamento(int $casas): void
    {
        $this->casasArredondamento = \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais($casas);
    }

    public function normalizeRoundMode(string $value): string
    {
        $v = strtolower(trim($value));
        return in_array($v, ['none', 'half', 'places'], true) ? $v : 'none';
    }

    public function applyRoundMode(?float $value, string $mode): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $v = (float) $value;
        $mode = $this->normalizeRoundMode($mode);
        $casas = \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais($this->casasArredondamento);
        if ($mode === 'places') {
            // Ex.: (8,0 + 7,9)/2 = 7,95 com 1 casa → 8,0
            return round($v, $casas);
        }
        if ($mode !== 'half') {
            // Convencional: se a regra pede 0/1 casa, respeita; senão mantém 2.
            return round($v, $casas === 2 ? 2 : $casas);
        }
        $base = floor($v);
        $dec = $v - $base;
        if ($dec < 0.25) {
            $v = (float) $base;
        } elseif ($dec < 0.75) {
            $v = $base + 0.5;
        } else {
            $v = $base + 1.0;
        }
        // “Nenhuma” casa: faixa .50 vira inteiro (ex.: 57,5 → 58).
        if ($casas === 0) {
            return (float) round($v);
        }

        return round($v, min(1, $casas));
    }

    /**
     * Avalia fórmula com códigos de bloco, max(), min(), chamada()/substituta(),
     * composicao(), media_lancado() e se()/if() (estilo Excel).
     * Extraído do boletim para ser a fonte única.
     *
     * chamada(base1, base2, …, substituta) = média das bases, preenchendo
     * só a primeira base ausente com a substituta (ex.: CH no lugar de PP ou PU).
     * composicao(média, REC) = média se REC ausente; (média+REC)/2 se houver REC.
     * media_lancado(provas | substituta | opcionais) = MÉDIA do lançado.
     * Grupos separados por | (posição define o papel — o nome do evento não importa):
     *   1) provas — substituta cobre a 1ª que faltar
     *   2) substituta (ex.: CH, 2ª chamada, qualquer nome)
     *   3) opcionais (ex.: Projeto) — só se lançado; substituta nunca cobre
     * PE fora (+ PE). Sem | = média simples do que tiver nota.
     * media_ponderada(base, peso, base, peso… | substituta) = média ponderada;
     *   a substituta cobre só a 1ª base ausente (herda o peso dela).
     *   Base obrigatória ausente sem substituta → pendente (não calcula).
     *   Zero lançado participa. Uma substituta não cobre duas bases.
     * se()/if() aceita condição em 3 formas:
     *   - se(PJ, A, B) → existência da nota em PJ
     *   - se(PJ = verdadeiro|true|sim|1, A, B) e falso|false|nao|não|0
     *   - se(PP > 7, A, B), se(PP >= PU, A, B), com operadores >= <= > < = == != <>
     * Em comparações entre colunas, se alguma estiver sem nota a condição é falsa.
     *
     * @param array<string, float|int|string|null> $valoresPorCodigo
     * @return array{ok:bool,valor?:float,expressao?:string,erro?:string}
     */
    public function avaliarFormula(string $formula, array $valoresPorCodigo): array
    {
        $valoresPorCodigoInsensitive = [];
        $valoresPorCodigoNormalized = [];
        $normKey = static function (string $s): string {
            $s = strtolower(trim($s));
            $s = str_replace('-', '_', $s);
            $s = preg_replace('/_+/', '_', $s) ?? $s;
            return trim($s, '_');
        };
        foreach ($valoresPorCodigo as $k => $v) {
            $ks = (string) $k;
            $valoresPorCodigoInsensitive[strtolower($ks)] = $v;
            $valoresPorCodigoNormalized[$normKey($ks)] = $v;
        }
        $temNota = static function (string $cod) use ($valoresPorCodigo, $valoresPorCodigoInsensitive, $valoresPorCodigoNormalized, $normKey): bool {
            $cod = trim($cod);
            if ($cod === '') {
                return false;
            }
            if (array_key_exists($cod, $valoresPorCodigo) && is_numeric($valoresPorCodigo[$cod])) {
                return true;
            }
            $tl = strtolower($cod);
            if (array_key_exists($tl, $valoresPorCodigoInsensitive) && is_numeric($valoresPorCodigoInsensitive[$tl])) {
                return true;
            }
            $tn = $normKey($cod);

            return $tn !== ''
                && array_key_exists($tn, $valoresPorCodigoNormalized)
                && is_numeric($valoresPorCodigoNormalized[$tn]);
        };
        $valorNota = static function (string $cod) use ($valoresPorCodigo, $valoresPorCodigoInsensitive, $valoresPorCodigoNormalized, $normKey): ?float {
            $cod = trim($cod);
            if ($cod === '') {
                return null;
            }
            if (array_key_exists($cod, $valoresPorCodigo) && is_numeric($valoresPorCodigo[$cod])) {
                return (float) $valoresPorCodigo[$cod];
            }
            $tl = strtolower($cod);
            if (array_key_exists($tl, $valoresPorCodigoInsensitive) && is_numeric($valoresPorCodigoInsensitive[$tl])) {
                return (float) $valoresPorCodigoInsensitive[$tl];
            }
            $tn = $normKey($cod);
            if ($tn !== '' && array_key_exists($tn, $valoresPorCodigoNormalized) && is_numeric($valoresPorCodigoNormalized[$tn])) {
                return (float) $valoresPorCodigoNormalized[$tn];
            }

            return null;
        };

        $expr = preg_replace('/(?<=\d),(?=\d)/', '.', $formula) ?? $formula;
        // Operadores tipográficos do editor → ASCII do avaliador.
        $expr = str_replace(['×', '÷', '−', '–', '—'], ['*', '/', '-', '-', '-'], $expr);
        // Alias legível no editor → nome canônico da função.
        $expr = preg_replace('/\bmenor\s*\(/iu', 'min(', $expr) ?? $expr;
        $expr = preg_replace('/\bmaior\s*\(/iu', 'max(', $expr) ?? $expr;
        $expr = preg_replace('/\bmedia_ponderada_substituta\s*\(/i', 'media_ponderada(', $expr) ?? $expr;
        $expr = preg_replace('/\bm[eé]dia\s+ponderada(?:\s+substituta)?\s*\(/iu', 'media_ponderada(', $expr) ?? $expr;
        $expr = preg_replace('/\bsubstituta\s*\(/i', 'chamada(', $expr) ?? $expr;
        // Alias antigo da função (renomeada para media_lancado).
        $expr = preg_replace('/\bmedia_presentes\s*\(/i', 'media_lancado(', $expr) ?? $expr;
        // Excel IF → se (português).
        $expr = preg_replace('/\bif\s*\(/i', 'se(', $expr) ?? $expr;

        $codeKeys = array_keys($valoresPorCodigo);
        usort($codeKeys, static function ($a, $b) {
            return strlen((string) $b) <=> strlen((string) $a);
        });
        $aliasValues = [];
        $aliasSeq = 0;
        foreach ($codeKeys as $codeKey) {
            $code = (string) $codeKey;
            if ($code === '') {
                continue;
            }
            $isIdentificadorValido = (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/u', $code);
            if ($isIdentificadorValido) {
                continue;
            }
            $alias = '__codealias_' . $aliasSeq++;
            $pattern = '/(?<![A-Za-z0-9_])' . preg_quote($code, '/') . '(?![A-Za-z0-9_])/u';
            $exprNovo = preg_replace($pattern, $alias, $expr);
            if ($exprNovo !== null && $exprNovo !== $expr) {
                $expr = $exprNovo;
                if (array_key_exists($code, $valoresPorCodigo)) {
                    $aliasValues[$alias] = $valoresPorCodigo[$code];
                }
            }
        }
        if (!empty($aliasValues)) {
            foreach ($aliasValues as $ak => $av) {
                $valoresPorCodigo[$ak] = $av;
                $valoresPorCodigoInsensitive[strtolower((string) $ak)] = $av;
            }
        }

        try {
            // se() primeiro: descarta o ramo não usado (evita erro em média sem notas).
            $expr = $this->expandirFuncoesSe((string) $expr, $temNota, $valorNota);
            $expr = $this->expandirFuncoesMediaPonderada((string) $expr, $temNota);
            $expr = $this->expandirFuncoesMediaLancado((string) $expr, $temNota);
            $expr = $this->expandirFuncoesChamada((string) $expr, $temNota);
            $expr = $this->expandirFuncoesComposicao((string) $expr, $temNota);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_starts_with($msg, 'PENDENTE:')) {
                return ['ok' => false, 'erro' => trim(substr($msg, strlen('PENDENTE:'))), 'pendente' => true];
            }

            return ['ok' => false, 'erro' => 'Erro na fórmula final: ' . $msg];
        }

        $expr = preg_replace_callback('/\b[a-zA-Z_][a-zA-Z0-9_]*\b/', function ($m) use ($valoresPorCodigo, $valoresPorCodigoInsensitive, $valoresPorCodigoNormalized, $normKey) {
            $token = $m[0];
            $tl = strtolower($token);
            if ($tl === 'max' || $tl === 'min' || $tl === 'chamada' || $tl === 'substituta' || $tl === 'composicao' || $tl === 'media_lancado' || $tl === 'media_ponderada' || $tl === 'se' || $tl === 'if') {
                return $token;
            }
            if (array_key_exists($token, $valoresPorCodigo) && is_numeric($valoresPorCodigo[$token])) {
                return (string) ((float) $valoresPorCodigo[$token]);
            }
            if (array_key_exists($tl, $valoresPorCodigoInsensitive) && is_numeric($valoresPorCodigoInsensitive[$tl])) {
                return (string) ((float) $valoresPorCodigoInsensitive[$tl]);
            }
            $tn = $normKey($token);
            if ($tn !== '' && array_key_exists($tn, $valoresPorCodigoNormalized) && is_numeric($valoresPorCodigoNormalized[$tn])) {
                return (string) ((float) $valoresPorCodigoNormalized[$tn]);
            }

            return '0';
        }, $expr);

        try {
            $expr = preg_replace('/\s+/', '', $expr);
            $resultado = $this->avaliarExpressaoComMaxMin((string) $expr);
            return ['ok' => true, 'valor' => round((float) $resultado, 2), 'expressao' => $expr];
        } catch (\Throwable $e) {
            return ['ok' => false, 'erro' => 'Erro na fórmula final: ' . $e->getMessage()];
        }
    }

    /**
     * Expõe media_ponderada para prévia do assistente de boletim.
     *
     * @param callable(string):bool $temNota
     */
    public function expandirMediaPonderadaParaPreview(string $expr, callable $temNota): string
    {
        $expr = preg_replace('/\bmedia_ponderada_substituta\s*\(/i', 'media_ponderada(', $expr) ?? $expr;
        $expr = preg_replace('/\bm[eé]dia\s+ponderada(?:\s+substituta)?\s*\(/iu', 'media_ponderada(', $expr) ?? $expr;

        return $this->expandirFuncoesMediaPonderada($expr, $temNota);
    }

    /**
     * media_ponderada(base, peso, base, peso… | substituta)
     * ou media_ponderada(base, peso, base, peso…, substituta):
     * média ponderada; substituta cobre só a 1ª base ausente (herda o peso).
     * Base obrigatória ausente sem cobertura → pendente.
     *
     * @param callable(string):bool $temNota
     */
    private function expandirFuncoesMediaPonderada(string $expr, callable $temNota): string
    {
        $guard = 0;
        while (preg_match('/\bmedia_ponderada\s*\(/i', $expr)) {
            if (++$guard > 80) {
                throw new \RuntimeException('Limite de aninhamento em media_ponderada.');
            }
            if (!preg_match('/\bmedia_ponderada\s*\(/i', $expr, $m, PREG_OFFSET_CAPTURE)) {
                break;
            }
            $startFn = (int) $m[0][1];
            $openParen = $startFn + strlen($m[0][0]) - 1;
            $closePos = $this->indiceFechaParen($expr, $openParen);
            $conteudo = substr($expr, $openParen + 1, $closePos - $openParen - 1);
            [$bases, $substituta] = $this->parseArgsMediaPonderada($conteudo);
            if ($bases === []) {
                throw new \RuntimeException('média ponderada: informe pares coluna, peso (ex.: PP, 3, PU, 5 | CH).');
            }

            $usouSubst = false;
            $termos = [];
            $somaPesos = 0.0;
            foreach ($bases as [$cod, $peso]) {
                $pesoFmt = abs($peso - round($peso)) < 0.0001
                    ? (string) (int) round($peso)
                    : rtrim(rtrim(number_format($peso, 4, '.', ''), '0'), '.');
                if ($temNota($cod)) {
                    $termos[] = '((' . $cod . ')*(' . $pesoFmt . '))';
                    $somaPesos += $peso;
                    continue;
                }
                if (!$usouSubst && $substituta !== null && $substituta !== '' && $temNota($substituta)) {
                    $termos[] = '((' . $substituta . ')*(' . $pesoFmt . '))';
                    $somaPesos += $peso;
                    $usouSubst = true;
                    continue;
                }
                throw new \RuntimeException(
                    'PENDENTE: média ponderada — falta nota obrigatória em ' . $cod
                    . ($substituta ? ' (sem substituta cobrindo)' : '')
                );
            }
            if ($termos === [] || $somaPesos <= 0) {
                throw new \RuntimeException('PENDENTE: média ponderada — nenhuma base com nota.');
            }
            $somaFmt = abs($somaPesos - round($somaPesos)) < 0.0001
                ? (string) (int) round($somaPesos)
                : rtrim(rtrim(number_format($somaPesos, 4, '.', ''), '0'), '.');
            $media = '((' . implode('+', $termos) . ')/(' . $somaFmt . '))';
            $expr = substr($expr, 0, $startFn) . $media . substr($expr, $closePos + 1);
        }

        return $expr;
    }

    /**
     * @return array{0: list<array{0:string,1:float}>, 1: ?string}
     */
    private function parseArgsMediaPonderada(string $conteudo): array
    {
        $conteudo = trim($conteudo);
        $substituta = null;
        $paresRaw = $conteudo;
        $depth = 0;
        $pipePos = null;
        $n = strlen($conteudo);
        for ($i = 0; $i < $n; $i++) {
            $c = $conteudo[$i];
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            } elseif ($c === '|' && $depth === 0) {
                $pipePos = $i;
                break;
            }
        }
        if ($pipePos !== null) {
            $paresRaw = trim(substr($conteudo, 0, $pipePos));
            $substRaw = trim(substr($conteudo, $pipePos + 1));
            if ($substRaw !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $substRaw)) {
                $substituta = $substRaw;
            } elseif ($substRaw !== '') {
                throw new \RuntimeException('média ponderada: a substituta após | deve ser uma coluna.');
            }
        }

        $tokens = [];
        $depth = 0;
        $inicio = 0;
        $len = strlen($paresRaw);
        for ($i = 0; $i < $len; $i++) {
            $c = $paresRaw[$i];
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            } elseif ($c === ',' && $depth === 0) {
                $tokens[] = trim(substr($paresRaw, $inicio, $i - $inicio));
                $inicio = $i + 1;
            }
        }
        $tokens[] = trim(substr($paresRaw, $inicio));
        $tokens = array_values(array_filter($tokens, static fn ($t) => $t !== ''));

        $bases = [];
        $i = 0;
        $nt = count($tokens);
        while ($i < $nt) {
            $cod = $tokens[$i];
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $cod)) {
                throw new \RuntimeException('média ponderada: coluna inválida "' . $cod . '".');
            }
            if ($i + 1 < $nt && preg_match('/^-?\d+(?:[.,]\d+)?$/', $tokens[$i + 1])) {
                $peso = (float) str_replace(',', '.', $tokens[$i + 1]);
                if ($peso <= 0) {
                    throw new \RuntimeException('média ponderada: peso de ' . $cod . ' deve ser > 0.');
                }
                $bases[] = [$cod, $peso];
                $i += 2;
                continue;
            }
            // Último identificador sem peso = substituta (forma sem |).
            if ($substituta === null && $i === $nt - 1) {
                $substituta = $cod;
                break;
            }
            throw new \RuntimeException('média ponderada: falta o peso após ' . $cod . '.');
        }

        return [$bases, $substituta];
    }

    private function indiceFechaParen(string $expr, int $openParenIdx): int
    {
        $n = strlen($expr);
        $depth = 1;
        for ($i = $openParenIdx + 1; $i < $n; $i++) {
            $c = $expr[$i];
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        throw new \RuntimeException('Função com parênteses incompletos.');
    }

    /**
     * Expande chamada(base1, base2, …, substituta) para a média das bases,
     * trocando só a primeira base sem nota pela substituta.
     *
     * @param callable(string):bool $temNota
     */
    private function expandirFuncoesChamada(string $expr, callable $temNota): string
    {
        $guard = 0;
        while (preg_match('/\b(chamada|substituta)\s*\(/i', $expr)) {
            if (++$guard > 80) {
                throw new \RuntimeException('Limite de aninhamento em chamada/substituta.');
            }
            if (!preg_match('/\b(chamada|substituta)\s*\(/i', $expr, $m, PREG_OFFSET_CAPTURE)) {
                break;
            }
            $startFn = (int) $m[0][1];
            $openParen = $startFn + strlen($m[0][0]) - 1;
            [$args, $closePos] = $this->extrairArgumentosFuncao($expr, $openParen);
            if (count($args) < 2) {
                throw new \RuntimeException('chamada/substituta precisa de ao menos 2 argumentos (bases…, substituta).');
            }
            $substituta = array_pop($args);
            $bases = $args;
            $preencheu = false;
            $termos = [];
            foreach ($bases as $base) {
                $baseTrim = trim($base);
                $ehIdent = (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $baseTrim);
                if ($ehIdent && !$temNota($baseTrim) && !$preencheu) {
                    $termos[] = '(' . $substituta . ')';
                    $preencheu = true;
                } else {
                    $termos[] = '(' . $baseTrim . ')';
                }
            }
            $n = count($termos);
            $media = $n === 1
                ? $termos[0]
                : '((' . implode('+', $termos) . ')/' . $n . ')';
            $expr = substr($expr, 0, $startFn) . $media . substr($expr, $closePos + 1);
            // substituta já é média — remove ÷ N redundante (senão a nota cai na metade).
            if ($n > 1) {
                $cauda = substr($expr, $startFn + strlen($media));
                if (preg_match('/^\s*\/\s*(\d+(?:[.,]\d+)?)/', $cauda, $divM)) {
                    $divisor = (float) str_replace(',', '.', (string) ($divM[1] ?? ''));
                    if (abs($divisor - $n) < 0.0001) {
                        $expr = substr($expr, 0, $startFn + strlen($media)) . substr($cauda, strlen($divM[0]));
                    }
                }
            }
        }

        return $expr;
    }

    /**
     * Expõe a expansão de media_lancado para prévia do assistente de boletim.
     *
     * @param callable(string):bool $temNota
     */
    public function expandirMediaLancadoParaPreview(string $expr, callable $temNota): string
    {
        return $this->expandirFuncoesMediaLancado($expr, $temNota);
    }

    /**
     * Expõe se()/if() para prévia do assistente de boletim.
     *
     * @param callable(string):bool $temNota
     * @param (callable(string):?float)|null $valorNota Quando null, comparações numéricas/entre colunas resultam false.
     */
    public function expandirSeParaPreview(string $expr, callable $temNota, ?callable $valorNota = null): string
    {
        $expr = preg_replace('/\bif\s*\(/i', 'se(', $expr) ?? $expr;
        $valorNota = $valorNota ?? static fn (string $cod): ?float => null;

        return $this->expandirFuncoesSe($expr, $temNota, $valorNota);
    }

    /**
     * se(condicao, se_verdadeiro, se_falso) — estilo Excel SE/IF.
     * Condições aceitas:
     *  - IDENT (existência de nota)
     *  - IDENT [=|==|!=|<>] (true/verdadeiro/sim/1/false/falso/nao/não/0)
     *  - IDENT [>=|<=|>|<|=|==|!=|<>] número
     *  - IDENT [>=|<=|>|<|=|==|!=|<>] IDENT
     * Se qualquer coluna da comparação estiver sem nota numérica, condição = false.
     *
     * @param callable(string):bool $temNota
     * @param callable(string):?float $valorNota
     */
    private function expandirFuncoesSe(string $expr, callable $temNota, callable $valorNota): string
    {
        $guard = 0;
        while (preg_match('/\b(?:se|if)\s*\(/i', $expr)) {
            if (++$guard > 80) {
                throw new \RuntimeException('Limite de aninhamento em se/if.');
            }
            if (!preg_match('/\b(?:se|if)\s*\(/i', $expr, $m, PREG_OFFSET_CAPTURE)) {
                break;
            }
            $startFn = (int) $m[0][1];
            $openParen = $startFn + strlen($m[0][0]) - 1;
            [$args, $closePos] = $this->extrairArgumentosFuncao($expr, $openParen);
            if (count($args) !== 3) {
                throw new \RuntimeException('se() precisa de 3 argumentos: se(condição, se_verdadeiro, se_falso).');
            }
            $cond = trim((string) $args[0]);
            $condicaoVerdadeira = $this->avaliarCondicaoSe($cond, $temNota, $valorNota);
            $escolhido = $condicaoVerdadeira ? trim((string) $args[1]) : trim((string) $args[2]);
            if ($escolhido === '') {
                throw new \RuntimeException('se(): ramo escolhido está vazio.');
            }
            $expr = substr($expr, 0, $startFn) . '(' . $escolhido . ')' . substr($expr, $closePos + 1);
        }

        return $expr;
    }

    /**
     * @param callable(string):bool $temNota
     * @param callable(string):?float $valorNota
     */
    private function avaliarCondicaoSe(string $cond, callable $temNota, callable $valorNota): bool
    {
        $cond = trim($cond);
        $cond = preg_replace('/\s+/', ' ', $cond) ?? $cond;
        if ($cond === '') {
            throw new \RuntimeException('se(): condição vazia no 1º argumento.');
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $cond)) {
            return $temNota($cond);
        }

        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*(>=|<=|!=|<>|==|=|>|<)\s*(.+)$/u', $cond, $m)) {
            throw new \RuntimeException('se(): condição inválida. Use IDENT, IDENT = verdadeiro/falso, número ou outra coluna.');
        }

        $colunaEsquerda = trim((string) $m[1]);
        $operador = trim((string) $m[2]);
        $rhs = trim((string) $m[3]);
        if ($rhs === '') {
            throw new \RuntimeException('se(): condição inválida. Lado direito da comparação está vazio.');
        }

        $rhsBooleano = $this->normalizarLiteralBooleanoSe($rhs);
        if ($rhsBooleano !== null) {
            if (!in_array($operador, ['=', '==', '!=', '<>'], true)) {
                throw new \RuntimeException('se(): comparação com verdadeiro/falso aceita apenas =, ==, != ou <>.');
            }
            $tem = $temNota($colunaEsquerda);
            if ($operador === '=' || $operador === '==') {
                return $tem === $rhsBooleano;
            }

            return $tem !== $rhsBooleano;
        }

        if (preg_match('/^-?\d+(?:[.,]\d+)?$/', $rhs)) {
            $valorEsquerda = $valorNota($colunaEsquerda);
            if ($valorEsquerda === null) {
                return false;
            }
            $valorDireita = (float) str_replace(',', '.', $rhs);

            return $this->compararNumerosSe($valorEsquerda, $operador, $valorDireita);
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $rhs)) {
            $valorEsquerda = $valorNota($colunaEsquerda);
            $valorDireita = $valorNota($rhs);
            if ($valorEsquerda === null || $valorDireita === null) {
                return false;
            }

            return $this->compararNumerosSe($valorEsquerda, $operador, $valorDireita);
        }

        throw new \RuntimeException('se(): valor de comparação inválido. Use verdadeiro/falso, número ou código de coluna.');
    }

    private function normalizarLiteralBooleanoSe(string $valor): ?bool
    {
        $v = strtolower(trim($valor));
        if (in_array($v, ['true', 'verdadeiro', 'sim', '1'], true)) {
            return true;
        }
        if (in_array($v, ['false', 'falso', 'nao', 'não', '0'], true)) {
            return false;
        }

        return null;
    }

    private function compararNumerosSe(float $esquerda, string $operador, float $direita): bool
    {
        $eps = 0.000001;
        if ($operador === '=' || $operador === '==') {
            return abs($esquerda - $direita) <= $eps;
        }
        if ($operador === '!=' || $operador === '<>') {
            return abs($esquerda - $direita) > $eps;
        }
        if ($operador === '>') {
            return $esquerda > $direita;
        }
        if ($operador === '<') {
            return $esquerda < $direita;
        }
        if ($operador === '>=') {
            return $esquerda > $direita || abs($esquerda - $direita) <= $eps;
        }
        if ($operador === '<=') {
            return $esquerda < $direita || abs($esquerda - $direita) <= $eps;
        }

        throw new \RuntimeException('se(): operador inválido na condição.');
    }

    /**
     * media_lancado(provas | substituta | opcionais):
     * Papel vem da posição após | — não do nome/código do evento.
     * 1) provas: substituta cobre a 1ª que faltar
     * 2) substituta: 0 ou 1 coluna
     * 3) opcionais: só se lançado; substituta nunca cobre
     * Sem | = média simples das colunas com nota.
     *
     * @param callable(string):bool $temNota
     */
    private function expandirFuncoesMediaLancado(string $expr, callable $temNota): string
    {
        $guard = 0;
        while (preg_match('/\bmedia_lancado\s*\(/i', $expr)) {
            if (++$guard > 80) {
                throw new \RuntimeException('Limite de aninhamento em media_lancado.');
            }
            if (!preg_match('/\bmedia_lancado\s*\(/i', $expr, $m, PREG_OFFSET_CAPTURE)) {
                break;
            }
            $startFn = (int) $m[0][1];
            $openParen = $startFn + strlen($m[0][0]) - 1;
            [$conteudo, $closePos] = $this->extrairConteudoFuncao($expr, $openParen);
            [$provas, $subst, $extras] = $this->gruposMediaLancado($conteudo);

            if ($provas === [] && $extras === []) {
                throw new \RuntimeException('media_lancado precisa de ao menos 1 coluna de prova ou opcional.');
            }

            $termos = [];
            $usouSubst = false;
            foreach ($provas as $prova) {
                if ($temNota($prova)) {
                    $termos[] = '(' . $prova . ')';
                } elseif ($subst !== null && !$usouSubst && $temNota($subst)) {
                    $termos[] = '(' . $subst . ')';
                    $usouSubst = true;
                }
            }
            foreach ($extras as $extra) {
                if ($temNota($extra)) {
                    $termos[] = '(' . $extra . ')';
                }
            }

            if ($termos === []) {
                throw new \RuntimeException('média só do lançado sem notas lançadas.');
            }
            $n = count($termos);
            $media = $n === 1
                ? $termos[0]
                : '((' . implode('+', $termos) . ')/' . $n . ')';
            $expr = substr($expr, 0, $startFn) . $media . substr($expr, $closePos + 1);
        }

        return $expr;
    }

    /**
     * @return array{0: string, 1: int} conteúdo entre parênteses e índice do ')'
     */
    private function extrairConteudoFuncao(string $expr, int $openParenIdx): array
    {
        $n = strlen($expr);
        $i = $openParenIdx + 1;
        $depth = 1;
        for (; $i < $n; $i++) {
            $c = $expr[$i];
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    return [substr($expr, $openParenIdx + 1, $i - $openParenIdx - 1), $i];
                }
            }
        }

        throw new \RuntimeException('Função com parênteses incompletos.');
    }

    /**
     * Parte media_lancado em [provas, substituta|null, opcionais] pela posição do |.
     *
     * @return array{0: list<string>, 1: ?string, 2: list<string>}
     */
    private function gruposMediaLancado(string $conteudo): array
    {
        $gruposBrutos = [];
        $n = strlen($conteudo);
        $depth = 0;
        $inicio = 0;
        for ($i = 0; $i < $n; $i++) {
            $c = $conteudo[$i];
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            } elseif ($c === '|' && $depth === 0) {
                $gruposBrutos[] = substr($conteudo, $inicio, $i - $inicio);
                $inicio = $i + 1;
            }
        }
        $gruposBrutos[] = substr($conteudo, $inicio);

        $codigosDoGrupo = function (string $grupo): array {
            $out = [];
            foreach (explode(',', $grupo) as $argBruto) {
                $arg = trim($argBruto);
                if ($arg === '') {
                    continue;
                }
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $arg)) {
                    throw new \RuntimeException('media_lancado aceita só códigos de coluna (ex.: PP, PU | CH | PJ).');
                }
                $out[] = $arg;
            }

            return $out;
        };

        $provas = $codigosDoGrupo($gruposBrutos[0] ?? '');
        $subst = null;
        $extras = [];
        if (count($gruposBrutos) >= 2) {
            $doMeio = $codigosDoGrupo($gruposBrutos[1]);
            if (count($doMeio) > 1) {
                throw new \RuntimeException('media_lancado: o 2º grupo (substituta) aceita só 1 coluna.');
            }
            $subst = $doMeio[0] ?? null;
        }
        if (count($gruposBrutos) >= 3) {
            for ($g = 2; $g < count($gruposBrutos); $g++) {
                foreach ($codigosDoGrupo($gruposBrutos[$g]) as $cod) {
                    $extras[] = $cod;
                }
            }
        }

        return [$provas, $subst, $extras];
    }

    /**
     * composicao(média, REC) → se REC tem nota: (média+REC)/2; senão: só a média.
     * Evita tratar REC ausente como 0 (que cortaria a média pela metade).
     *
     * @param callable(string):bool $temNota
     */
    private function expandirFuncoesComposicao(string $expr, callable $temNota): string
    {
        $guard = 0;
        while (preg_match('/\bcomposicao\s*\(/i', $expr)) {
            if (++$guard > 80) {
                throw new \RuntimeException('Limite de aninhamento em composicao.');
            }
            if (!preg_match('/\bcomposicao\s*\(/i', $expr, $m, PREG_OFFSET_CAPTURE)) {
                break;
            }
            $startFn = (int) $m[0][1];
            $openParen = $startFn + strlen($m[0][0]) - 1;
            [$args, $closePos] = $this->extrairArgumentosFuncao($expr, $openParen);
            if (count($args) !== 2) {
                throw new \RuntimeException('composicao precisa de 2 argumentos: composicao(média, REC).');
            }
            $media = trim((string) $args[0]);
            $rec = trim((string) $args[1]);
            $ehIdent = (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $rec);
            if ($ehIdent && !$temNota($rec)) {
                $substituicao = '(' . $media . ')';
            } else {
                $substituicao = '((' . $media . ')+(' . $rec . '))/2';
            }
            $expr = substr($expr, 0, $startFn) . $substituicao . substr($expr, $closePos + 1);
        }

        return $expr;
    }

    /**
     * @return array{0: list<string>, 1: int}
     */
    private function extrairArgumentosFuncao(string $expr, int $openParenIdx): array
    {
        $n = strlen($expr);
        $i = $openParenIdx + 1;
        $depth = 1;
        $args = [];
        $inicio = $i;
        for (; $i < $n; $i++) {
            $c = $expr[$i];
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    $args[] = substr($expr, $inicio, $i - $inicio);

                    return [$args, $i];
                }
            } elseif ($c === ',' && $depth === 1) {
                $args[] = substr($expr, $inicio, $i - $inicio);
                $inicio = $i + 1;
            }
        }

        throw new \RuntimeException('Função com parênteses incompletos.');
    }

    private function avaliarExpressaoComMaxMin(string $expr): float
    {
        $expr = trim($expr);
        $guard = 0;
        while (preg_match('/\b(max|min)\s*\(/i', $expr)) {
            if (++$guard > 200) {
                throw new \RuntimeException('Limite de aninhamento em max/min.');
            }
            $expr = $this->substituirPrimeiraFuncaoMaxMin($expr);
        }
        if ($expr === '' || !preg_match('/^[0-9\.\+\-\*\/\(\)]+$/', $expr)) {
            throw new \RuntimeException('Fórmula contém caracteres inválidos após max/min.');
        }

        return $this->safeEval($expr);
    }

    private function substituirPrimeiraFuncaoMaxMin(string $expr): string
    {
        if (!preg_match('/\b(max|min)\s*\(/i', $expr, $m, PREG_OFFSET_CAPTURE)) {
            return $expr;
        }
        $fn = strtolower($m[1][0]);
        $startFn = (int) $m[0][1];
        $openParen = $startFn + strlen($m[0][0]) - 1;
        [$commaPos, $closePos] = $this->localizarArgumentosMaxMin($expr, $openParen);
        if ($commaPos === null) {
            throw new \RuntimeException('Função max/min precisa de dois argumentos separados por vírgula.');
        }
        $left = substr($expr, $openParen + 1, $commaPos - $openParen - 1);
        $right = substr($expr, $commaPos + 1, $closePos - $commaPos - 1);
        $lv = $this->avaliarExpressaoComMaxMin($left);
        $rv = $this->avaliarExpressaoComMaxMin($right);
        $val = $fn === 'max' ? max($lv, $rv) : min($lv, $rv);

        return substr($expr, 0, $startFn) . (string) $val . substr($expr, $closePos + 1);
    }

    /**
     * @return array{0: ?int, 1: int}
     */
    private function localizarArgumentosMaxMin(string $expr, int $openParenIdx): array
    {
        $n = strlen($expr);
        $i = $openParenIdx + 1;
        $depth = 1;
        $commaPos = null;
        for (; $i < $n; $i++) {
            $c = $expr[$i];
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    return [$commaPos, $i];
                }
            } elseif ($c === ',' && $depth === 1) {
                $commaPos = $i;
            }
        }

        return [null, $n - 1];
    }

    private function safeEval(string $expr): float
    {
        set_error_handler(function ($severity, $message) {
            throw new \RuntimeException($message, $severity);
        });

        try {
            $result = 0.0;
            eval('$result = ' . $expr . ';');
            return (float) $result;
        } finally {
            restore_error_handler();
        }
    }
}

if (!class_exists('ResultadoAcademicoService', false) && class_exists(__NAMESPACE__ . '\\ResultadoAcademicoService', false)) {
    class_alias(__NAMESPACE__ . '\\ResultadoAcademicoService', 'ResultadoAcademicoService');
}
