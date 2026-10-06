<?php
/**
 * Mesma lista e o mesmo Demonstrativo de Notas da coordenação.
 * Só eventos de notas (exibir_em=notas). O boletim oficial não entra aqui.
 *
 * @var list<array<string,mixed>> $boletins_gerados_notas
 * @var array<string,mixed>|null $aluno
 * @var array<string,mixed>|null $filho
 */
$boletins_gerados_notas = is_array($boletins_gerados_notas ?? null) ? $boletins_gerados_notas : [];
$alunoIdPortal = (int) ($aluno_id_notas ?? 0);
if ($alunoIdPortal <= 0 && is_array($aluno ?? null)) {
    $alunoIdPortal = (int) ($aluno['id'] ?? 0);
}
if ($alunoIdPortal <= 0 && is_array($filho ?? null)) {
    $alunoIdPortal = (int) ($filho['id'] ?? 0);
}
$student = ['id' => $alunoIdPortal];
$aluno_id = $alunoIdPortal;
$notas_mensagem_vazia = 'Nenhuma nota disponível.';
$boletim_eventos_notas = [];
$vistosPortalNotas = [];
$idsListaPortal = static function ($raw): array {
    $decoded = [];
    if (is_array($raw)) {
        $decoded = $raw;
    } elseif (is_string($raw) && trim($raw) !== '') {
        $parsed = json_decode($raw, true);
        $decoded = is_array($parsed) ? $parsed : (preg_split('/[,\s;]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
    $ids = [];
    foreach ($decoded as $v) {
        if (is_array($v)) {
            $cand = (int) ($v['id'] ?? $v['serie_id'] ?? $v['value'] ?? 0);
        } else {
            $cand = (int) $v;
        }
        if ($cand > 0) {
            $ids[$cand] = $cand;
        }
    }

    return array_values($ids);
};
$montarEventoPortal = static function (array $ev, int $rid, string $nome, string $codigo, string $updated, string $ini, string $fim, $bim, $ano) use (&$boletim_eventos_notas, &$vistosPortalNotas): void {
    $chave = $rid . ':' . (int) $ano . ':' . (int) $bim;
    if ($rid <= 0 || isset($vistosPortalNotas[$chave])) {
        return;
    }
    $vistosPortalNotas[$chave] = true;
    $boletim_eventos_notas[] = [
        'id' => $rid,
        'nome' => $nome,
        'codigo' => $codigo,
        'updated_at' => $updated,
        'default_data_inicio' => $ini,
        'default_data_fim' => $fim,
        'bimestre' => $bim,
        'ano_letivo' => $ano,
    ];
};
if ($alunoIdPortal > 0) {
    try {
        if (!class_exists('Database', false)) {
            require_once dirname(__DIR__, 2) . '/Core/Database.php';
        }
        $dbPortal = Database::getInstance();
        $turmasPortal = [];
        $seriesPortal = [];
        $ordinaisPortal = [];
        $guardarEscopo = static function ($turmaId, $serieId, string $serieNome) use (&$turmasPortal, &$seriesPortal, &$ordinaisPortal): void {
            $turmaId = (int) $turmaId;
            $serieId = (int) $serieId;
            if ($turmaId > 0) {
                $turmasPortal[$turmaId] = $turmaId;
            }
            if ($serieId > 0) {
                $seriesPortal[$serieId] = $serieId;
            }
            if ($serieNome !== '' && preg_match('/\d+/', $serieNome, $mOrd)) {
                $ord = (int) ($mOrd[0] ?? 0);
                if ($ord > 0) {
                    $ordinaisPortal[$ord] = $ord;
                }
            }
        };
        $alunoEscopo = $dbPortal->fetch(
            'SELECT a.turma_id, t.serie_id, s.nome AS serie_nome
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             LEFT JOIN serie s ON s.id = t.serie_id
             WHERE a.id = :id',
            ['id' => $alunoIdPortal]
        );
        if (is_array($alunoEscopo)) {
            $guardarEscopo($alunoEscopo['turma_id'] ?? 0, $alunoEscopo['serie_id'] ?? 0, (string) ($alunoEscopo['serie_nome'] ?? ''));
        }
        try {
            $matriculas = $dbPortal->fetchAll(
                "SELECT DISTINCT t.id AS turma_id, t.serie_id, s.nome AS serie_nome
                 FROM matricula m
                 INNER JOIN turmas t ON t.id = m.turma_id
                 LEFT JOIN serie s ON s.id = t.serie_id
                 WHERE m.aluno_id = :id
                   AND m.status = 'ativa'
                   AND m.data_saida IS NULL",
                ['id' => $alunoIdPortal]
            ) ?: [];
            foreach ($matriculas as $mat) {
                if (is_array($mat)) {
                    $guardarEscopo($mat['turma_id'] ?? 0, $mat['serie_id'] ?? 0, (string) ($mat['serie_nome'] ?? ''));
                }
            }
        } catch (Throwable $eMat) {
            // Sem matrícula ativa, vale a turma principal.
        }
        $visCol = (($notas_perfil ?? 'aluno') === 'pais') ? 'vis_pais' : 'vis_aluno';
        $regrasPortal = $dbPortal->fetchAll(
            "SELECT id, nome, codigo, updated_at, default_data_inicio, default_data_fim,
                    bimestre, ano_letivo, series_ids, turmas_ids
             FROM boletim_regras
             WHERE ativo = 1
               AND exibir_em = 'notas'
               AND ({$visCol} = 1 OR {$visCol} IS NULL)
               AND (extras_json IS NULL OR extras_json = '' OR COALESCE(JSON_UNQUOTE(JSON_EXTRACT(extras_json, '$.oculto_lista_avaliacoes')), '0') NOT IN ('1', 'true'))
             ORDER BY ano_letivo DESC, bimestre ASC, id DESC
             LIMIT 200"
        ) ?: [];
        $anoFiltroPortal = (int) ($filtroAnoLetivo ?? $filtro_ano_letivo ?? 0);
        $bimFiltroPortal = (int) ($filtroBimestre ?? $filtro_bimestre ?? 0);
        foreach ($regrasPortal as $regraPortal) {
            if (!is_array($regraPortal)) {
                continue;
            }
            $anoRegra = (int) ($regraPortal['ano_letivo'] ?? 0);
            $bimRegra = (int) ($regraPortal['bimestre'] ?? 0);
            if ($anoFiltroPortal > 0 && $anoRegra !== $anoFiltroPortal) {
                continue;
            }
            if ($bimFiltroPortal > 0 && $bimRegra !== $bimFiltroPortal) {
                continue;
            }
            $turmasRegra = $idsListaPortal($regraPortal['turmas_ids'] ?? null);
            if ($turmasRegra !== []) {
                if ($turmasPortal === [] || count(array_intersect($turmasRegra, array_values($turmasPortal))) === 0) {
                    continue;
                }
            } else {
                $seriesRegra = $idsListaPortal($regraPortal['series_ids'] ?? null);
                if ($seriesRegra !== []) {
                    $bateId = $seriesPortal !== [] && count(array_intersect($seriesRegra, array_values($seriesPortal))) > 0;
                    $bateOrd = $ordinaisPortal !== [] && count(array_intersect($seriesRegra, array_values($ordinaisPortal))) > 0;
                    if (!$bateId && !$bateOrd) {
                        continue;
                    }
                }
            }
            $montarEventoPortal(
                $regraPortal,
                (int) ($regraPortal['id'] ?? 0),
                (string) ($regraPortal['nome'] ?? ''),
                (string) ($regraPortal['codigo'] ?? ''),
                (string) ($regraPortal['updated_at'] ?? ''),
                (string) ($regraPortal['default_data_inicio'] ?? ''),
                (string) ($regraPortal['default_data_fim'] ?? ''),
                $regraPortal['bimestre'] ?? null,
                $regraPortal['ano_letivo'] ?? null
            );
        }
    } catch (Throwable $ePortal) {
        error_log('Notas do portal aluno #' . $alunoIdPortal . ': ' . $ePortal->getMessage());
    }
}
foreach ($boletins_gerados_notas as $evPortal) {
    if (!is_array($evPortal)) {
        continue;
    }
    if (strtolower(trim((string) ($evPortal['exibir_em'] ?? 'notas'))) !== 'notas') {
        continue;
    }
    $montarEventoPortal(
        $evPortal,
        (int) ($evPortal['regra_id'] ?? 0),
        (string) ($evPortal['regra_nome'] ?? ''),
        (string) ($evPortal['regra_codigo'] ?? ''),
        (string) ($evPortal['updated_at'] ?? ''),
        (string) ($evPortal['data_inicio'] ?? ''),
        (string) ($evPortal['data_fim'] ?? ''),
        $evPortal['bimestre'] ?? null,
        $evPortal['ano_letivo'] ?? null
    );
}
$boletins_gerados_notas_por_regra = [];
require __DIR__ . '/../admin/students/_secao_notas_eventos.php';
