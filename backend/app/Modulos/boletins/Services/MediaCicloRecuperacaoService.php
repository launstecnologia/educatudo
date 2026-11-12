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
     *   notas:array<string,float>
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
        ];
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', $turmaIds))));
        if ($ano < 2000 || $codigoPeriodoRec < RecuperacaoConfig::CODIGO_CICLO_BASE || $turmaIds === []) {
            return $vazio;
        }

        $grupo = $this->layoutGroupDoPeriodoRec($codigoPeriodoRec);
        $regra = $this->resolverRegraBoletim($ano, $turmaIds);
        if ($regra === null) {
            return $vazio;
        }
        $comp = $this->componenteMediaCiclo($regra, $grupo);
        if ($comp === null) {
            return $vazio;
        }

        $codigo = strtolower(trim((string) ($comp['codigo'] ?? '')));
        $cfg = $this->configDoComponente($comp);
        $expressao = strtolower(trim((string) ($cfg['expressao'] ?? '')));
        if ($codigo === '' || $expressao === '') {
            return [
                'configurada' => true,
                'pendente' => true,
                'codigo' => $codigo !== '' ? $codigo : null,
                'expressao' => null,
                'regra_id' => (int) ($regra['id'] ?? 0),
                'notas' => [],
            ];
        }

        $periodosOrigem = $this->periodosOrigem($codigoPeriodoRec);
        $notas = $this->notasDoSnapshot((int) $regra['id'], $ano, $periodosOrigem, $codigo, $turmaIds);
        if ($notas === []) {
            $notas = $this->notasPelaFormula($regra, $expressao, $turmaIds, $periodosOrigem, $ano);
        }

        return [
            'configurada' => true,
            'pendente' => $notas === [],
            'codigo' => $codigo,
            'expressao' => $expressao,
            'regra_id' => (int) ($regra['id'] ?? 0),
            'notas' => $notas,
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
        $melhor = null;
        $melhorScore = -1;
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
            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhor = $regra;
            }
        }
        if ($melhor === null) {
            return null;
        }
        $id = (int) ($melhor['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        try {
            $comps = $this->db->fetchAll(
                'SELECT * FROM boletim_componentes WHERE regra_id = :rid AND ativo = 1 ORDER BY ordem ASC, id ASC',
                ['rid' => $id]
            ) ?: [];
        } catch (Throwable $e) {
            $comps = [];
        }
        foreach ($comps as &$c) {
            if (!is_array($c)) {
                continue;
            }
            $c['config'] = $this->configDoComponente($c);
        }
        unset($c);
        $melhor['componentes'] = $comps;

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
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'calculado') {
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
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? $c['layout_group'] ?? '')));
            if ($g === $grupo) {
                return $c;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($grupo === 'final' && in_array($cod, ['media_final', 'mf', 'ma'], true)) {
                return $c;
            }
            if (preg_match('/^b([1-4])/', $grupo, $m) && str_starts_with($cod, 'b' . $m[1] . '_')) {
                return $c;
            }
        }

        // Quadro livre: bloco flagado sem grupo (ou quadro_comum).
        foreach ($candidatos as $c) {
            $cfg = $this->configDoComponente($c);
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? '')));
            if ($g === '' || $g === 'quadro_comum') {
                return $c;
            }
        }

        // Sem match do período: não usa bloco de outro ciclo (evita MIN → média errada).
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
        $reservados = [
            'max', 'min', 'chamada', 'substituta', 'composicao', 'media_ponderada',
            'media_lancado', 'se', 'if', 'verdadeiro', 'falso', 'true', 'false',
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

        $pecas = $this->carregarNotasPecas($turmaIds, $periodos, $ano, $eventoIds, $tipoIds);
        if ($pecas === []) {
            return [];
        }

        $pares = [];
        $codigosPeca = array_keys($codigos);
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
        if (is_array($componente['config'] ?? null)) {
            return $componente['config'];
        }
        $raw = trim((string) ($componente['config_json'] ?? ''));
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
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
