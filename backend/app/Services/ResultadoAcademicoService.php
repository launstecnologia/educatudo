<?php
require_once __DIR__ . '/../Modulos/regras-academicas/Models/RegraAcademica.php';
require_once __DIR__ . '/FrequencyService.php';

use App\Modulos\RegrasAcademicas\Models\RegraAcademica;

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
        $serie = $matchOrNull($regra['serie_id'] ?? null, $contexto['serie_id'] ?? null);
        if ($serie === null) {
            return -1;
        }
        $matriz = $matchOrNull($regra['matriz_curricular_id'] ?? null, $contexto['matriz_curricular_id'] ?? null);
        if ($matriz === null) {
            return -1;
        }
        $materia = $matchOrNull($regra['materia_id'] ?? null, $contexto['materia_id'] ?? null);
        if ($materia === null) {
            return -1;
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
        $score += $materia === 10 ? 160 : $materia;
        $score += $numRegra > 0 ? 20 : 2;
        return $score;
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
            'decimal_places' => (int) ($eventoBoletim['decimal_places'] ?? 2) === 1 ? 1 : 2,
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

        $passouRendimento = $media >= $minima;
        if ($passouRendimento) {
            $usouRec = $rec !== null && $mediaAntes !== null && $mediaAntes < $minima;
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

    public function normalizeRoundMode(string $value): string
    {
        $v = strtolower(trim($value));
        return in_array($v, ['none', 'half'], true) ? $v : 'none';
    }

    public function applyRoundMode(?float $value, string $mode): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $v = (float) $value;
        if ($mode !== 'half') {
            return round($v, 2);
        }
        $base = floor($v);
        $dec = $v - $base;
        if ($dec < 0.25) {
            return round($base, 2);
        }
        if ($dec < 0.75) {
            return round($base + 0.5, 2);
        }
        return round($base + 1.0, 2);
    }

    /**
     * Avalia fórmula com códigos de bloco, max() e min(). Extraído do boletim
     * para ser a fonte única.
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
        $expr = preg_replace('/(?<=\d),(?=\d)/', '.', $formula) ?? $formula;

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

        $expr = preg_replace_callback('/\b[a-zA-Z_][a-zA-Z0-9_]*\b/', function ($m) use ($valoresPorCodigo, $valoresPorCodigoInsensitive, $valoresPorCodigoNormalized, $normKey) {
            $token = $m[0];
            $tl = strtolower($token);
            if ($tl === 'max' || $tl === 'min') {
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
        } catch (Throwable $e) {
            return ['ok' => false, 'erro' => 'Erro na fórmula final: ' . $e->getMessage()];
        }
    }

    private function avaliarExpressaoComMaxMin(string $expr): float
    {
        $expr = trim($expr);
        $guard = 0;
        while (preg_match('/\b(max|min)\s*\(/i', $expr)) {
            if (++$guard > 200) {
                throw new RuntimeException('Limite de aninhamento em max/min.');
            }
            $expr = $this->substituirPrimeiraFuncaoMaxMin($expr);
        }
        if ($expr === '' || !preg_match('/^[0-9\.\+\-\*\/\(\)]+$/', $expr)) {
            throw new RuntimeException('Fórmula contém caracteres inválidos após max/min.');
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
            throw new RuntimeException('Função max/min precisa de dois argumentos separados por vírgula.');
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
            throw new RuntimeException($message, $severity);
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
