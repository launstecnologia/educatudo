<?php

namespace App\Modulos\Boletins\Services;

use App\Core\Database;
use App\Modulos\Fechamento\Services\ResultadoAcademicoService;
use App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig;
use Throwable;

/**
 * Resolve a coluna flagada como “Média do Ciclo” no boletim para a convocação de REC.
 */
class MediaCicloRecuperacaoService
{
    private $db;
    private ResultadoAcademicoService $resultado;

    public function __construct(?ResultadoAcademicoService $resultado = null)
    {
        $this->db = Database::getInstance();
        $this->resultado = $resultado ?? new ResultadoAcademicoService();
    }

    /**
     * @param list<int> $turmaIds
     * @return array{
     *   configurada:bool,
     *   pendente:bool,
     *   codigo:?string,
     *   expressao:?string,
     *   regra_id:int,
     *   notas:array<string,float>,
     *   motivo:string
     * }
     */
    public function resolverNotas(int $ano, int $codigoPeriodoRec, array $turmaIds): array
    {
        $vazio = [
            'configurada' => false,
            'pendente' => false,
            'codigo' => null,
            'expressao' => null,
            'regra_id' => 0,
            'notas' => [],
            'motivo' => 'sem_metrica',
        ];
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', $turmaIds))));
        if ($ano < 2000 || $codigoPeriodoRec < RecuperacaoConfig::CODIGO_CICLO_BASE || $turmaIds === []) {
            return $vazio;
        }

        $grupo = $this->layoutGroupDoPeriodoRec($codigoPeriodoRec);
        $regra = $this->resolverRegraBoletim($ano, $turmaIds);
        if ($regra === null) {
            $vazio['motivo'] = 'sem_boletim';
            return $vazio;
        }
        $comp = $this->componenteMediaCiclo($regra, $grupo);
        if ($comp === null) {
            $vazio['regra_id'] = (int) ($regra['id'] ?? 0);
            $vazio['motivo'] = 'sem_metrica';
            return $vazio;
        }

        $codigo = strtolower(trim((string) ($comp['codigo'] ?? '')));
        $cfg = $this->configDoComponente($comp);
        $expressao = strtolower(trim((string) ($cfg['expressao'] ?? '')));
        if ($codigo === '') {
            return [
                'configurada' => true,
                'pendente' => true,
                'codigo' => null,
                'expressao' => null,
                'regra_id' => (int) ($regra['id'] ?? 0),
                'notas' => [],
                'motivo' => 'sem_formula',
            ];
        }

        $periodosOrigem = $this->periodosOrigem($codigoPeriodoRec);
        // Snapshot primeiro (serve também para evento_boletim sem expressão própria).
        $notas = $this->notasDoSnapshot((int) $regra['id'], $ano, $periodosOrigem, $codigo, $turmaIds);
        if ($notas === [] && $expressao !== '') {
            $notas = $this->notasPelaFormula($regra, $expressao, $turmaIds, $periodosOrigem, $ano);
        }
        // evento_boletim: tenta o código da coluna no demonstrativo referenciado.
        if ($notas === [] && (string) ($comp['source_type'] ?? '') === 'evento_boletim') {
            $codDemo = strtolower(trim((string) ($cfg['componente_codigo'] ?? '')));
            if ($codDemo !== '' && $codDemo !== $codigo) {
                $notas = $this->notasDoSnapshot((int) $regra['id'], $ano, $periodosOrigem, $codDemo, $turmaIds);
            }
        }

        return [
            'configurada' => true,
            'pendente' => $notas === [],
            'codigo' => $codigo,
            'expressao' => $expressao !== '' ? $expressao : null,
            'regra_id' => (int) ($regra['id'] ?? 0),
            'notas' => $notas,
            'motivo' => $notas === []
                ? ($expressao === '' && (string) ($comp['source_type'] ?? '') !== 'evento_boletim' ? 'sem_formula' : 'aguardando_notas')
                : 'ok',
        ];
    }

    /**
     * Chave aluno|materia => nota da média do ciclo.
     *
     * @param array{notas?:array<string,float>} $resolvido
     */
    public function notaAlunoMateria(array $resolvido, int $alunoId, int $materiaId): ?float
    {
        if ($alunoId <= 0 || $materiaId <= 0) {
            return null;
        }
        $chave = $alunoId . '|' . $materiaId;
        $mapa = is_array($resolvido['notas'] ?? null) ? $resolvido['notas'] : [];
        if (!isset($mapa[$chave]) || !is_numeric($mapa[$chave])) {
            return null;
        }

        return round((float) $mapa[$chave], 2);
    }

    private function layoutGroupDoPeriodoRec(int $codigo): string
    {
        if ($codigo === RecuperacaoConfig::CODIGO_FINAL) {
            return 'final';
        }
        if ($codigo > RecuperacaoConfig::CODIGO_PAR_BASE && $codigo < RecuperacaoConfig::CODIGO_FINAL) {
            $par = $codigo - RecuperacaoConfig::CODIGO_PAR_BASE;

            return $par === 2 ? 's2' : 's1';
        }
        if ($codigo > RecuperacaoConfig::CODIGO_CICLO_BASE && $codigo < RecuperacaoConfig::CODIGO_PAR_BASE) {
            $n = $codigo - RecuperacaoConfig::CODIGO_CICLO_BASE;
            if ($n >= 1 && $n <= 4) {
                return 'b' . $n;
            }
        }

        return '';
    }

    /**
     * @return list<int>
     */
    private function periodosOrigem(int $codigo): array
    {
        if ($codigo === RecuperacaoConfig::CODIGO_FINAL) {
            return [1, 2, 3, 4];
        }
        if ($codigo > RecuperacaoConfig::CODIGO_PAR_BASE && $codigo < RecuperacaoConfig::CODIGO_FINAL) {
            $par = $codigo - RecuperacaoConfig::CODIGO_PAR_BASE;
            $inicio = (($par - 1) * 2) + 1;

            return [$inicio, $inicio + 1];
        }
        if ($codigo > RecuperacaoConfig::CODIGO_CICLO_BASE && $codigo < RecuperacaoConfig::CODIGO_PAR_BASE) {
            return [$codigo - RecuperacaoConfig::CODIGO_CICLO_BASE];
        }

        return [];
    }

    /**
     * @param list<int> $turmaIds
     * @return array<string,mixed>|null
     */
    private function resolverRegraBoletim(int $ano, array $turmaIds): ?array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT * FROM boletim_regras
                  WHERE ativo = 1 AND exibir_em = 'boletim'
                    AND (ano_letivo IS NULL OR ano_letivo = 0 OR ano_letivo = :ano)
                  ORDER BY updated_at DESC, id DESC
                  LIMIT 40",
                ['ano' => $ano]
            ) ?: [];
        } catch (Throwable $e) {
            return null;
        }
        if ($rows === []) {
            return null;
        }

        $seriesTurmas = $this->seriesDasTurmas($turmaIds);
        $candidatos = [];
        foreach ($rows as $regra) {
            if (!is_array($regra)) {
                continue;
            }
            $score = 0;
            $turmasRegra = $this->idsJson($regra['turmas_ids'] ?? null);
            $seriesRegra = $this->idsJson($regra['series_ids'] ?? null);
            if ($turmasRegra !== []) {
                $inter = array_intersect($turmaIds, $turmasRegra);
                if ($inter === []) {
                    continue;
                }
                $score += 20 + count($inter);
            } elseif ($seriesRegra !== []) {
                $inter = array_intersect($seriesTurmas, $seriesRegra);
                if ($inter === []) {
                    continue;
                }
                $score += 10 + count($inter);
            } else {
                $score += 1;
            }
            if ((int) ($regra['ano_letivo'] ?? 0) === $ano) {
                $score += 5;
            }
            $candidatos[] = ['regra' => $regra, 'score' => $score];
        }
        if ($candidatos === []) {
            return null;
        }

        $melhor = null;
        $melhorScore = -1;
        foreach ($candidatos as $item) {
            $regra = $item['regra'];
            $score = (int) $item['score'];
            $id = (int) ($regra['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            try {
                $comps = $this->db->fetchAll(
                    'SELECT * FROM boletim_componentes WHERE regra_id = :rid AND ativo = 1 ORDER BY ordem ASC, id ASC',
                    ['rid' => $id]
                ) ?: [];
            } catch (Throwable $e) {
                $comps = [];
            }
            $nComps = 0;
            $temMediaCiclo = false;
            foreach ($comps as &$c) {
                if (!is_array($c)) {
                    continue;
                }
                $nComps++;
                $c['config'] = $this->configDoComponente($c);
                $cfg = $c['config'];
                $src = (string) ($c['source_type'] ?? '');
                if (
                    !empty($cfg['media_ciclo'])
                    && ($src === 'calculado' || $src === 'evento_boletim')
                ) {
                    $temMediaCiclo = true;
                }
            }
            unset($c);
            // Layout “print” vazio (0 colunas) não pode ganhar do boletim real só por updated_at.
            if ($nComps === 0) {
                $score -= 80;
            } else {
                $score += min(40, $nComps);
            }
            if ($temMediaCiclo) {
                $score += 120;
            }
            $regra['componentes'] = $comps;
            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhor = $regra;
            }
        }

        return $melhor;
    }

    /**
     * @param array<string,mixed> $regra
     * @return array<string,mixed>|null
     */
    private function componenteMediaCiclo(array $regra, string $grupo): ?array
    {
        $candidatos = [];
        foreach ((array) ($regra['componentes'] ?? []) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $src = (string) ($c['source_type'] ?? '');
            // Calculado (fórmula) ou coluna do demonstrativo no boletim (evento_boletim).
            if ($src !== 'calculado' && $src !== 'evento_boletim') {
                continue;
            }
            $cfg = $this->configDoComponente($c);
            if (empty($cfg['media_ciclo'])) {
                continue;
            }
            $candidatos[] = $c;
        }
        if ($candidatos === []) {
            return null;
        }
        if ($grupo === '') {
            return $candidatos[0];
        }
        foreach ($candidatos as $c) {
            $cfg = $this->configDoComponente($c);
            $g = $this->layoutGroupDoConfig($cfg, $c);
            if ($g === $grupo) {
                return $c;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($grupo === 'final' && (
                in_array($cod, ['media_final', 'mf', 'ma', 'nota_final', 'resultado_final'], true)
                || str_starts_with($cod, 'final_')
                || str_contains($cod, 'media_final')
            )) {
                return $c;
            }
            if (($grupo === 's1' || $grupo === 's2') && str_starts_with($cod, $grupo . '_')) {
                return $c;
            }
            if (preg_match('/^b([1-4])/', $grupo, $m) && str_starts_with($cod, 'b' . $m[1] . '_')) {
                return $c;
            }
        }

        // Quadro livre: bloco flagado sem grupo (ou quadro_comum) — só se for o único sem destino.
        $semGrupo = [];
        foreach ($candidatos as $c) {
            $cfg = $this->configDoComponente($c);
            $g = $this->layoutGroupDoConfig($cfg, $c);
            if ($g === '' || $g === 'quadro_comum') {
                $semGrupo[] = $c;
            }
        }
        if (count($semGrupo) === 1) {
            return $semGrupo[0];
        }

        // Sem match do período: não usa bloco de outro ciclo (evita REC 2 pegar média do 3º).
        return null;
    }

    /**
     * @param list<int> $turmaIds
     * @param list<int> $periodos
     * @return array<string,float>
     */
    private function notasDoSnapshot(int $regraId, int $ano, array $periodos, string $codigo, array $turmaIds): array
    {
        if ($regraId <= 0 || $codigo === '' || $turmaIds === []) {
            return [];
        }
        $refs = [];
        foreach ($periodos as $p) {
            $p = (int) $p;
            if ($p >= 1 && $p <= 4) {
                $refs[] = $ano . '-B' . $p;
                $refs[] = $ano . '-T' . $p;
            }
        }
        if ($refs === []) {
            $refs[] = (string) $ano;
        }
        $params = ['rid' => $regraId, 'cod' => $codigo];
        $phTurma = [];
        foreach ($turmaIds as $i => $tid) {
            $k = 't' . $i;
            $phTurma[] = ':' . $k;
            $params[$k] = $tid;
        }
        $phRef = [];
        foreach ($refs as $i => $ref) {
            $k = 'r' . $i;
            $phRef[] = ':' . $k;
            $params[$k] = $ref;
        }
        try {
            $temVigente = $this->colunaExiste('boletim_resultados_gerados', 'vigente');
            $sql = 'SELECT g.aluno_id, g.materia_id, g.notas_json
                      FROM boletim_resultados_gerados g
                      INNER JOIN alunos a ON a.id = g.aluno_id
                     WHERE g.regra_id = :rid
                       AND g.preview = 0
                       AND g.periodo_ref IN (' . implode(', ', $phRef) . ')
                       AND a.turma_id IN (' . implode(', ', $phTurma) . ')';
            if ($temVigente) {
                $sql .= ' AND g.vigente = 1';
            }
            $temUpdated = $this->colunaExiste('boletim_resultados_gerados', 'updated_at');
            $sql .= $temUpdated
                ? ' ORDER BY g.updated_at DESC, g.id DESC'
                : ' ORDER BY g.id DESC';
            $rows = $this->db->fetchAll($sql, $params) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $alunoId = (int) ($row['aluno_id'] ?? 0);
            $materiaId = (int) ($row['materia_id'] ?? 0);
            if ($alunoId <= 0 || $materiaId <= 0) {
                continue;
            }
            $chave = $alunoId . '|' . $materiaId;
            // Já preenchido: row mais recente (ORDER BY) ganha.
            if (isset($out[$chave])) {
                continue;
            }
            $notas = json_decode((string) ($row['notas_json'] ?? ''), true);
            if (!is_array($notas)) {
                continue;
            }
            $valor = $notas[$codigo] ?? $notas[strtoupper($codigo)] ?? null;
            if (!is_numeric($valor)) {
                continue;
            }
            $out[$chave] = round((float) $valor, 2);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $regra
     * @param list<int> $turmaIds
     * @param list<int> $periodos
     * @return array<string,float>
     */
    private function notasPelaFormula(array $regra, string $expressao, array $turmaIds, array $periodos, int $ano): array
    {
        if (!preg_match_all('/\b[a-z_][a-z0-9_]*\b/', $expressao, $m)) {
            return [];
        }
        // Funções do editor / avaliador — não são colunas de nota.
        $reservados = [
            'max', 'min', 'maior', 'menor', 'chamada', 'substituta', 'composicao',
            'media_ponderada', 'media_lancado', 'media_presentes', 'media',
            'se', 'if', 'verdadeiro', 'falso', 'true', 'false', 'sim', 'nao',
            'and', 'or', 'not', 'div', 'mod',
        ];
        $codigos = [];
        foreach ($m[0] as $tok) {
            if (in_array($tok, $reservados, true) || is_numeric($tok)) {
                continue;
            }
            $codigos[$tok] = true;
        }
        if ($codigos === []) {
            return [];
        }

        $compsPorCodigo = [];
        foreach ((array) ($regra['componentes'] ?? []) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod !== '') {
                $compsPorCodigo[$cod] = $c;
            }
        }

        $eventoIds = [];
        $tipoIds = [];
        foreach (array_keys($codigos) as $cod) {
            $c = $compsPorCodigo[$cod] ?? null;
            if (!is_array($c)) {
                continue;
            }
            $cfg = $this->configDoComponente($c);
            $ev = (int) ($cfg['evento_avaliativo_id'] ?? $c['evento_avaliativo_id'] ?? 0);
            $tipo = (int) ($cfg['tipo_avaliacao_id'] ?? $c['tipo_avaliacao_id'] ?? 0);
            if ($ev > 0) {
                $eventoIds[$cod] = $ev;
            }
            if ($tipo > 0) {
                $tipoIds[$cod] = $tipo;
            }
        }

        // Só peças com origem de nota (evento/tipo). Tokens de função/coluna calculada não bloqueiam.
        $codigosPeca = array_values(array_unique(array_merge(array_keys($eventoIds), array_keys($tipoIds))));
        if ($codigosPeca === []) {
            return [];
        }

        $pecas = $this->carregarNotasPecas($turmaIds, $periodos, $ano, $eventoIds, $tipoIds);
        if ($pecas === []) {
            return [];
        }

        $pares = [];
        foreach ($pecas as $chaveAlunoMat => $porCodigo) {
            [$alunoId, $materiaId] = array_map('intval', explode('|', $chaveAlunoMat, 2));
            if ($alunoId <= 0 || $materiaId <= 0) {
                continue;
            }
            $valores = [];
            $completo = true;
            foreach ($codigosPeca as $cod) {
                if (!isset($porCodigo[$cod]) || !is_numeric($porCodigo[$cod])) {
                    $completo = false;
                    break;
                }
                $valores[$cod] = (float) $porCodigo[$cod];
            }
            // Peça faltante não vira 0: evita média artificialmente baixa e convocação indevida.
            if (!$completo) {
                continue;
            }
            $r = $this->resultado->avaliarFormula($expressao, $valores);
            if (empty($r['ok']) || !isset($r['valor']) || !is_numeric($r['valor'])) {
                continue;
            }
            $pares[$chaveAlunoMat] = round((float) $r['valor'], 2);
        }

        return $pares;
    }

    /**
     * @param list<int> $turmaIds
     * @param list<int> $periodos
     * @param array<string,int> $eventoIds codigo => evento_id
     * @param array<string,int> $tipoIds codigo => tipo_id
     * @return array<string,array<string,float>> aluno|materia => [codigo => nota]
     */
    private function carregarNotasPecas(
        array $turmaIds,
        array $periodos,
        int $ano,
        array $eventoIds,
        array $tipoIds
    ): array {
        $out = [];
        $paramsBase = ['ano' => $ano];
        $phTurma = [];
        foreach ($turmaIds as $i => $tid) {
            $k = 't' . $i;
            $phTurma[] = ':' . $k;
            $paramsBase[$k] = $tid;
        }
        $inTurmas = implode(', ', $phTurma);
        $phPeriodo = [];
        foreach ($periodos as $i => $p) {
            $k = 'p' . $i;
            $phPeriodo[] = ':' . $k;
            $paramsBase[$k] = (int) $p;
        }
        $filtroPeriodoBloco = $phPeriodo !== [] ? (' AND pb.bimestre IN (' . implode(', ', $phPeriodo) . ')') : '';
        $filtroPeriodoFinal = $phPeriodo !== [] ? (' AND n.periodo IN (' . implode(', ', $phPeriodo) . ')') : '';

        foreach ($eventoIds as $cod => $eventoId) {
            $params = $paramsBase;
            $params['ev'] = $eventoId;
            try {
                $rows = $this->db->fetchAll(
                    'SELECT n.aluno_id, n.materia_id, MAX(n.nota) AS nota
                       FROM provas_blocos_notas_lancadas n
                       INNER JOIN provas_blocos pb ON pb.id = n.bloco_id AND pb.deleted_at IS NULL
                      WHERE pb.ano_letivo = :ano
                        AND pb.evento_avaliativo_id = :ev
                        AND n.turma_id IN (' . $inTurmas . ')
                        AND n.nota IS NOT NULL' . $filtroPeriodoBloco . '
                      GROUP BY n.aluno_id, n.materia_id',
                    $params
                ) ?: [];
            } catch (Throwable $e) {
                $rows = [];
            }
            foreach ($rows as $row) {
                $alunoId = (int) ($row['aluno_id'] ?? 0);
                $materiaId = (int) ($row['materia_id'] ?? 0);
                if ($alunoId <= 0 || $materiaId <= 0 || !is_numeric($row['nota'] ?? null)) {
                    continue;
                }
                $chave = $alunoId . '|' . $materiaId;
                $out[$chave][$cod] = (float) $row['nota'];
            }
        }

        foreach ($tipoIds as $cod => $tipoId) {
            if (isset($eventoIds[$cod])) {
                continue;
            }
            $params = $paramsBase;
            $params['tipo'] = $tipoId;
            try {
                $rows = $this->db->fetchAll(
                    'SELECT n.aluno_id, n.materia_id, MAX(n.nota_final) AS nota
                       FROM notas_tipo_finais n
                      WHERE n.ano_letivo = :ano
                        AND n.tipo_avaliacao_id = :tipo
                        AND n.turma_id IN (' . $inTurmas . ')' . $filtroPeriodoFinal . '
                      GROUP BY n.aluno_id, n.materia_id',
                    $params
                ) ?: [];
            } catch (Throwable $e) {
                $rows = [];
            }
            foreach ($rows as $row) {
                $alunoId = (int) ($row['aluno_id'] ?? 0);
                $materiaId = (int) ($row['materia_id'] ?? 0);
                if ($alunoId <= 0 || $materiaId <= 0 || !is_numeric($row['nota'] ?? null)) {
                    continue;
                }
                $chave = $alunoId . '|' . $materiaId;
                if (isset($out[$chave][$cod])) {
                    continue;
                }
                $out[$chave][$cod] = (float) $row['nota'];
            }
        }

        return $out;
    }

    /**
     * @param list<int> $turmaIds
     * @return list<int>
     */
    private function seriesDasTurmas(array $turmaIds): array
    {
        if ($turmaIds === []) {
            return [];
        }
        $params = [];
        $ph = [];
        foreach ($turmaIds as $i => $id) {
            $k = 't' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        try {
            $rows = $this->db->fetchAll(
                'SELECT DISTINCT serie_id FROM turmas WHERE id IN (' . implode(', ', $ph) . ') AND serie_id IS NOT NULL',
                $params
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $sid = (int) ($r['serie_id'] ?? 0);
            if ($sid > 0) {
                $out[] = $sid;
            }
        }

        return $out;
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function idsJson($raw): array
    {
        if (is_array($raw)) {
            $arr = $raw;
        } else {
            $arr = json_decode((string) $raw, true);
            if (!is_array($arr)) {
                return [];
            }
        }
        $out = [];
        foreach ($arr as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param array<string,mixed> $componente
     * @return array<string,mixed>
     */
    private function configDoComponente(array $componente): array
    {
        $cfg = is_array($componente['config'] ?? null) ? $componente['config'] : [];
        $raw = trim((string) ($componente['config_json'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $cfg = $cfg === [] ? $decoded : array_replace($decoded, $cfg);
            }
        }

        return $cfg;
    }

    /**
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $componente
     */
    private function layoutGroupDoConfig(array $cfg, array $componente): string
    {
        $layout = is_array($cfg['layout'] ?? null) ? $cfg['layout'] : [];
        // Destino explícito da UI (“para qual REC?”) manda sobre o título do bloco.
        $g = strtolower(trim((string) (
            $cfg['media_ciclo_destino']
            ?? $cfg['layout_group']
            ?? $layout['group']
            ?? $componente['layout_group']
            ?? ''
        )));
        if (!in_array($g, ['b1', 'b2', 'b3', 'b4', 's1', 's2', 'final', 'quadro_comum'], true)
            && !preg_match('/^grupo_[a-z0-9_]+$/', $g)
        ) {
            return '';
        }

        return $g;
    }

    private function colunaExiste(string $tabela, string $coluna): bool
    {
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1',
                ['t' => $tabela, 'c' => $coluna]
            );

            return !empty($row['ok']);
        } catch (Throwable $e) {
            return false;
        }
    }
}
