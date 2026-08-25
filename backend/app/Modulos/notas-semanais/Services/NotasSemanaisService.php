<?php
/**
 * Monta o quadro de notas (S1–S8, média semanal, prova bim, ENAC, etc.).
 */

require_once __DIR__ . '/../Models/NotasSemanaisConfig.php';
require_once dirname(__DIR__, 3) . '/Services/ResultadoAcademicoService.php';

class NotasSemanaisService
{
    /** @var Database */
    private $db;

    /** @var NotasSemanaisConfig */
    private $configModel;
    private ?ResultadoAcademicoService $resultadoAcademicoSvc = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->configModel = new NotasSemanaisConfig();
    }

    public function moduloAtivo(): bool
    {
        if (!class_exists('LayoutHelper', false)) {
            require_once dirname(__DIR__, 3) . '/Core/LayoutHelper.php';
        }
        return LayoutHelper::isModuleEnabled('notas_semanais');
    }

    /**
     * @return array<string, mixed>
     */
    public function montarQuadroAluno(int $alunoId, ?int $bimestre = null, ?int $anoLetivo = null): array
    {
        $vazio = [
            'modulo_ativo' => $this->moduloAtivo(),
            'tem_dados' => false,
            'schema_pronto' => $this->configModel->tabelasProntas(),
            'ano_letivo' => $anoLetivo ?? (int) date('Y'),
            'bimestre' => $bimestre ?? 0,
            'bimestres_disponiveis' => [],
            'config' => $this->configModel->obter(),
            'tabelas' => [],
        ];
        if ($alunoId <= 0 || !$vazio['modulo_ativo']) {
            return $vazio;
        }

        $config = $vazio['config'];
        $lancamentos = $this->coletarLancamentos($alunoId);
        if ($lancamentos === []) {
            return $vazio;
        }

        $bimestres = [];
        $anos = [];
        foreach ($lancamentos as $l) {
            $b = (int) ($l['bimestre'] ?? 0);
            $a = (int) ($l['ano_letivo'] ?? 0);
            if ($b >= 1 && $b <= 4) {
                $bimestres[$b] = $b;
            }
            if ($a > 0) {
                $anos[$a] = $a;
            }
        }
        ksort($bimestres);
        krsort($anos);

        $anoSel = $anoLetivo !== null && $anoLetivo > 0
            ? $anoLetivo
            : ((int) (array_key_first($anos) ?: date('Y')));
        if ($bimestre !== null && $bimestre >= 1 && $bimestre <= 4) {
            $bimSel = $bimestre;
        } else {
            $keysBim = array_keys($bimestres);
            $bimSel = $keysBim === [] ? 1 : (int) end($keysBim);
        }

        $filtrados = array_values(array_filter($lancamentos, function ($l) use ($anoSel, $bimSel) {
            $a = (int) ($l['ano_letivo'] ?? 0);
            $b = (int) ($l['bimestre'] ?? 0);
            return $a === $anoSel && $b === $bimSel;
        }));

        $gruposMapa = $this->configModel->mapaGruposMaterias();
        $pesosInfo = $this->resolverPesosMediaBim($config, $bimSel, $anoSel);
        $ctxAluno = $this->contextoAcademicoDoAluno($alunoId);
        $porMateria = [];
        foreach ($filtrados as $l) {
            $mid = (int) ($l['materia_id'] ?? 0);
            $nome = trim((string) ($l['materia_nome'] ?? ''));
            $chave = $mid > 0 ? ('id:' . $mid) : ('n:' . mb_strtolower($nome !== '' ? $nome : 'sem', 'UTF-8'));
            if (!isset($porMateria[$chave])) {
                $porMateria[$chave] = [
                    'materia_id' => $mid,
                    'materia' => $nome !== '' ? $nome : '—',
                    'semanas' => [],
                    'prova_bim' => null,
                    'enac' => null,
                    'participacao' => null,
                    'trabalho' => null,
                    'recuperacao' => null,
                    'semana_sinal' => [],
                ];
            }
            $this->aplicarLancamento($porMateria[$chave], $l);
        }

        foreach ($porMateria as &$linha) {
            $linha['grupo'] = $this->resolverGrupo($linha, $gruposMapa, $config);
            $semanasGrupo = $linha['grupo'] === 'B'
                ? ($config['semanas_grupo_b'] ?? [2, 4, 6, 8])
                : ($config['semanas_grupo_a'] ?? [1, 3, 5, 7]);
            $this->fecharLinha($linha, $config, $semanasGrupo, $pesosInfo['pesos'], $ctxAluno);
        }
        unset($linha);

        $tabelas = [];
        foreach (['A' => $config['semanas_grupo_a'], 'B' => $config['semanas_grupo_b']] as $grupo => $semanas) {
            $materias = [];
            foreach ($porMateria as $linha) {
                if (($linha['grupo'] ?? '') !== $grupo) {
                    continue;
                }
                $materias[] = $linha;
            }
            usort($materias, function ($x, $y) {
                return strcasecmp((string) $x['materia'], (string) $y['materia']);
            });
            $tabelas[] = [
                'grupo' => $grupo,
                'titulo' => $grupo === 'A' ? 'Matérias Bloco A' : 'Matérias Bloco B',
                'subtitulo' => $grupo === 'B' ? 'Prova semanal' : '',
                'semanas' => $semanas,
                'materias' => $materias,
            ];
        }

        $tem = false;
        foreach ($tabelas as $t) {
            if (!empty($t['materias'])) {
                $tem = true;
                break;
            }
        }

        return [
            'modulo_ativo' => true,
            'tem_dados' => $tem,
            'schema_pronto' => true,
            'ano_letivo' => $anoSel,
            'bimestre' => $bimSel,
            'bimestres_disponiveis' => array_values($bimestres),
            'config' => $config,
            'tabelas' => $tabelas,
            'origem_media_bim' => $pesosInfo['origem'],
            'regra_boletim' => $pesosInfo['regra_nome'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function coletarLancamentos(int $alunoId): array
    {
        $hasSemana = $this->colunaExiste('provas_blocos', 'semana');
        $hasChave = $this->colunaExiste('provas_tipos_avaliacao', 'chave_quadro');
        $hasBim = $this->colunaExiste('provas_blocos', 'bimestre');
        $hasAno = $this->colunaExiste('provas_blocos', 'ano_letivo');
        $hasTipo = $this->colunaExiste('provas_blocos', 'tipo_avaliacao_id');

        $selSemana = $hasSemana ? 'pb.semana' : 'NULL';
        $selChave = ($hasTipo && $hasChave) ? 'pta.chave_quadro' : 'NULL';
        $selTipoNome = $hasTipo ? 'pta.nome' : 'NULL';
        $selBim = $hasBim ? 'pb.bimestre' : 'NULL';
        $selAno = $hasAno ? 'pb.ano_letivo' : 'NULL';
        $joinTipo = $hasTipo
            ? 'LEFT JOIN provas_tipos_avaliacao pta ON pta.id = pb.tipo_avaliacao_id AND pta.deleted_at IS NULL'
            : '';
        $filtroVisivel = $this->colunaExiste('provas_blocos', 'visivel_no_portal_aluno')
            ? ' AND pb.visivel_no_portal_aluno = 1'
            : '';

        $online = [];
        try {
            $online = $this->db->fetchAll(
                "SELECT pb.id AS bloco_id, pb.titulo AS bloco_titulo, pb.data_prova,
                        {$selSemana} AS semana, {$selBim} AS bimestre, {$selAno} AS ano_letivo,
                        {$selChave} AS chave_quadro, {$selTipoNome} AS tipo_nome,
                        p.materia_id, m.nome AS materia_nome,
                        pr.nota, p.valor_total, pr.finalizado_em,
                        (SELECT COUNT(*) FROM provas_respostas r
                          WHERE r.prova_id = p.id AND r.aluno_id = pr.aluno_id) AS qtd_questoes,
                        (SELECT COUNT(*) FROM provas_respostas r
                          WHERE r.prova_id = p.id AND r.aluno_id = pr.aluno_id AND r.correta = 1) AS qtd_acertos
                 FROM provas_realizacoes pr
                 INNER JOIN provas p ON p.id = pr.prova_id AND p.deleted_at IS NULL
                 INNER JOIN provas_blocos_vinculo pbv ON pbv.prova_id = p.id
                 INNER JOIN provas_blocos pb ON pb.id = pbv.bloco_id AND pb.deleted_at IS NULL
                 {$joinTipo}
                 LEFT JOIN materias m ON m.id = p.materia_id
                 WHERE pr.aluno_id = :aluno_id
                   AND pr.status = 'finalizado'
                   {$filtroVisivel}
                 ORDER BY pb.data_prova ASC, pb.id ASC",
                ['aluno_id' => $alunoId]
            ) ?: [];
        } catch (Throwable $e) {
            $online = [];
        }

        $lancadas = [];
        try {
            if ($this->db->fetch("SHOW TABLES LIKE 'provas_blocos_notas_lancadas'") !== false) {
                $lancadas = $this->db->fetchAll(
                    "SELECT pb.id AS bloco_id, pb.titulo AS bloco_titulo, pb.data_prova,
                            {$selSemana} AS semana, {$selBim} AS bimestre, {$selAno} AS ano_letivo,
                            {$selChave} AS chave_quadro, {$selTipoNome} AS tipo_nome,
                            n.materia_id, m.nome AS materia_nome,
                            n.nota, 10 AS valor_total, n.updated_at AS finalizado_em,
                            NULL AS qtd_questoes,
                            NULL AS qtd_acertos
                     FROM provas_blocos_notas_lancadas n
                     INNER JOIN provas_blocos pb ON pb.id = n.bloco_id AND pb.deleted_at IS NULL
                     {$joinTipo}
                     LEFT JOIN materias m ON m.id = n.materia_id
                     WHERE n.aluno_id = :aluno_id
                       AND n.nota IS NOT NULL
                       {$filtroVisivel}
                     ORDER BY pb.data_prova ASC, pb.id ASC",
                    ['aluno_id' => $alunoId]
                ) ?: [];
            }
        } catch (Throwable $e) {
            $lancadas = [];
        }

        return array_merge($online, $lancadas);
    }

    /**
     * @param array<string, mixed> $linha
     * @param array<string, mixed> $l
     */
    private function aplicarLancamento(array &$linha, array $l): void
    {
        $nota10 = $this->notaEmDez($l['nota'] ?? null, $l['valor_total'] ?? 10);
        if ($nota10 === null) {
            return;
        }
        $semana = isset($l['semana']) && $l['semana'] !== null && $l['semana'] !== ''
            ? (int) $l['semana']
            : 0;
        $chave = $this->normalizarChave((string) ($l['chave_quadro'] ?? ''), (string) ($l['tipo_nome'] ?? ''));
        $qtd = isset($l['qtd_questoes']) && $l['qtd_questoes'] !== null && $l['qtd_questoes'] !== ''
            ? (int) $l['qtd_questoes']
            : null;
        $acertos = isset($l['qtd_acertos']) && $l['qtd_acertos'] !== null && $l['qtd_acertos'] !== ''
            ? (int) $l['qtd_acertos']
            : null;
        if ($qtd !== null && $qtd <= 0) {
            $qtd = null;
            $acertos = null;
        }
        if ($acertos === null && $qtd !== null && $qtd > 0 && $nota10 !== null) {
            $acertos = (int) round(($nota10 / 10) * $qtd);
        }

        $quando = strtotime((string) ($l['finalizado_em'] ?? '')) ?: 0;
        $colunas = ['prova_bim', 'enac', 'participacao', 'trabalho', 'recuperacao'];
        if (in_array($chave, $colunas, true)) {
            $atualQuando = (int) ($linha[$chave . '_quando'] ?? 0);
            if ($quando >= $atualQuando) {
                $linha[$chave] = $nota10;
                $linha[$chave . '_quando'] = $quando;
            }
            return;
        }

        if ($semana >= 1 && $semana <= 8) {
            $atual = $linha['semanas'][$semana] ?? null;
            $atualQuando = is_array($atual) ? (int) ($atual['quando'] ?? 0) : 0;
            if (!is_array($atual) || $quando >= $atualQuando) {
                $linha['semanas'][$semana] = [
                    'n' => $acertos,
                    'q' => $qtd,
                    'nota' => $nota10,
                    'quando' => $quando,
                ];
            }
            $linha['semana_sinal'][] = $semana;
        }
    }

    /**
     * @param array<string, mixed> $linha
     * @param array<int, string> $gruposMapa
     * @param array<string, mixed> $config
     */
    private function resolverGrupo(array $linha, array $gruposMapa, array $config): string
    {
        $mid = (int) ($linha['materia_id'] ?? 0);
        if ($mid > 0 && isset($gruposMapa[$mid])) {
            return $gruposMapa[$mid];
        }
        $sinais = $linha['semana_sinal'] ?? [];
        $odd = 0;
        $even = 0;
        foreach ($sinais as $s) {
            if (((int) $s) % 2 === 1) {
                $odd++;
            } else {
                $even++;
            }
        }
        if ($odd > $even) {
            return 'A';
        }
        if ($even > $odd) {
            return 'B';
        }
        $a = $config['semanas_grupo_a'] ?? [1, 3, 5, 7];
        foreach ($sinais as $s) {
            if (in_array((int) $s, $a, true)) {
                return 'A';
            }
        }
        return 'B';
    }

    /**
     * @param array<string, mixed> $linha
     * @param array<string, mixed> $config
     * @param list<int> $semanasGrupo
     * @param array<string, float> $pesosCfg
     */
    private function fecharLinha(array &$linha, array $config, array $semanasGrupo, array $pesosCfg, array $ctxAluno = []): void
    {
        $nTotal = 0;
        $qTotal = 0;
        $notasSemana = [];
        $temLancadaSemQ = false;
        foreach ($semanasGrupo as $s) {
            $cel = $linha['semanas'][(int) $s] ?? null;
            if (!is_array($cel)) {
                continue;
            }
            $q = (int) ($cel['q'] ?? 0);
            $n = $cel['n'] ?? null;
            if ($q > 0 && $n !== null && $n !== '') {
                $acertos = min((int) $n, $q);
                $nTotal += $acertos;
                $qTotal += $q;
                $notasSemana[] = ($acertos / $q) * 10;
                continue;
            }
            if (isset($cel['nota']) && $cel['nota'] !== null && $cel['nota'] !== '') {
                $temLancadaSemQ = true;
                $notasSemana[] = (float) $cel['nota'];
            }
        }
        $mediaSem = null;
        if ($notasSemana !== []) {
            if ($qTotal > 0 && !$temLancadaSemQ) {
                $mediaSem = round(($nTotal / $qTotal) * 10, 1);
            } else {
                $mediaSem = round(array_sum($notasSemana) / count($notasSemana), 1);
            }
            $mediaSem = max(0, min(10, $mediaSem));
        }
        $linha['media_sem'] = $mediaSem;
        $linha['total'] = [
            'n' => $qTotal > 0 ? $nTotal : null,
            'q' => $qTotal > 0 ? $qTotal : null,
        ];

        $pesos = [
            'media_sem' => [(float) ($pesosCfg['media_sem'] ?? 0), $mediaSem],
            'prova_bim' => [(float) ($pesosCfg['prova_bim'] ?? 0), $linha['prova_bim']],
            'enac' => [(float) ($pesosCfg['enac'] ?? 0), $linha['enac']],
            'participacao' => [(float) ($pesosCfg['participacao'] ?? 0), $linha['participacao']],
            'trabalho' => [(float) ($pesosCfg['trabalho'] ?? 0), $linha['trabalho']],
        ];
        $somaPeso = 0.0;
        $somaNota = 0.0;
        foreach ($pesos as $item) {
            if ($item[1] === null || $item[0] <= 0) {
                continue;
            }
            $somaPeso += $item[0];
            $somaNota += $item[0] * (float) $item[1];
        }
        $mediaBim = $somaPeso > 0 ? round($somaNota / $somaPeso, 1) : null;
        $linha['media_bim'] = $mediaBim;

        $rec = $linha['recuperacao'];
        $final = $mediaBim;
        $motor = $this->resultadoAcademico();
        $regraAcad = $motor ? $motor->resolverRegra([
            'ano_letivo' => (int) ($config['ano_letivo'] ?? 0),
            'periodo_tipo' => 'bimestre',
            'periodo_numero' => (int) ($config['bimestre'] ?? 0),
            'materia_id' => (int) ($linha['materia_id'] ?? 0) ?: null,
            'serie_id' => $ctxAluno['serie_id'] ?? null,
            'curso_id' => $ctxAluno['curso_id'] ?? null,
        ]) : null;
        if ($regraAcad && $motor) {
            $aplicado = $motor->aplicarRecuperacao(
                $mediaBim,
                $rec !== null ? (float) $rec : null,
                $regraAcad
            );
            $final = $aplicado['media_final'];
        } elseif ($rec !== null && $mediaBim !== null) {
            $regra = (string) ($config['regra_recuperacao'] ?? 'maior');
            $minima = (float) ($config['media_minima'] ?? 6);
            if ($regra === 'substitui_se_abaixo') {
                $final = $mediaBim < $minima ? max($mediaBim, (float) $rec) : $mediaBim;
            } else {
                $final = max($mediaBim, (float) $rec);
            }
        } elseif ($rec !== null) {
            $final = (float) $rec;
        }
        $linha['media_bim_final'] = $final !== null ? round((float) $final, 1) : null;
        unset($linha['semana_sinal'], $linha['prova_bim_quando'], $linha['enac_quando'], $linha['participacao_quando'], $linha['trabalho_quando'], $linha['recuperacao_quando']);
        if (is_array($linha['semanas'] ?? null)) {
            foreach ($linha['semanas'] as &$cel) {
                if (is_array($cel)) {
                    unset($cel['quando'], $cel['nota']);
                }
            }
            unset($cel);
        }
    }

    private function notaEmDez($nota, $valorTotal): ?float
    {
        if ($nota === null || $nota === '') {
            return null;
        }
        $n = (float) $nota;
        $v = (float) $valorTotal;
        if ($v > 0 && $v !== 10.0) {
            $n = ($n / $v) * 10;
        }
        return round($n, 1);
    }

    private function normalizarChave(string $chave, string $nomeTipo): string
    {
        $c = strtolower(trim($chave));
        $validas = ['semanal', 'prova_bim', 'enac', 'participacao', 'trabalho', 'recuperacao'];
        if (in_array($c, $validas, true)) {
            return $c;
        }
        $n = mb_strtolower($nomeTipo, 'UTF-8');
        if (str_contains($n, 'semanal')) {
            return 'semanal';
        }
        if (str_contains($n, 'bimestral')) {
            return 'prova_bim';
        }
        if ($n === 'enac' || str_contains($n, 'enac')) {
            return 'enac';
        }
        if (str_contains($n, 'particip')) {
            return 'participacao';
        }
        if (str_contains($n, 'trabalho')) {
            return 'trabalho';
        }
        if (str_contains($n, 'recupera')) {
            return 'recuperacao';
        }
        return '';
    }

    /**
     * Pesos da média bimestral: regra do boletim (quando existir).
     * Sem regra, usa os valores gravados no quadro só como fallback silencioso.
     *
     * @param array<string, mixed> $config
     * @return array{pesos: array<string, float>, origem: string, regra_nome: ?string}
     */
    private function resolverPesosMediaBim(array $config, int $bimestre, int $ano): array
    {
        $fallback = [
            'media_sem' => (float) ($config['peso_media_sem'] ?? 4),
            'prova_bim' => (float) ($config['peso_prova_bim'] ?? 4),
            'enac' => (float) ($config['peso_enac'] ?? 1),
            'participacao' => (float) ($config['peso_participacao'] ?? 0.5),
            'trabalho' => (float) ($config['peso_trabalho'] ?? 0.5),
        ];
        $vazio = ['pesos' => $fallback, 'origem' => 'quadro', 'regra_nome' => null];
        try {
            if ($this->db->fetch("SHOW TABLES LIKE 'boletim_regras'") === false) {
                return $vazio;
            }
            $regras = $this->db->fetchAll(
                "SELECT id, nome, bimestre, ano_letivo
                 FROM boletim_regras
                 WHERE ativo = 1
                 ORDER BY id DESC
                 LIMIT 30"
            ) ?: [];
            $candidatas = [];
            foreach ($regras as $regra) {
                $rb = $regra['bimestre'] !== null && $regra['bimestre'] !== '' ? (int) $regra['bimestre'] : 0;
                $ra = $regra['ano_letivo'] !== null && $regra['ano_letivo'] !== '' ? (int) $regra['ano_letivo'] : 0;
                if ($rb > 0 && $rb !== $bimestre) {
                    continue;
                }
                if ($ra > 0 && $ra !== $ano) {
                    continue;
                }
                $score = 3;
                if ($ra === $ano && $rb === $bimestre) {
                    $score = 0;
                } elseif ($rb === $bimestre && $ra === 0) {
                    $score = 1;
                } elseif ($rb === $bimestre) {
                    $score = 2;
                } elseif ($rb === 0 && $ra === 0) {
                    $score = 3;
                } else {
                    $score = 4;
                }
                $regra['_score'] = $score;
                $candidatas[] = $regra;
            }
            usort($candidatas, static function ($a, $b) {
                return ((int) $a['_score']) <=> ((int) $b['_score']);
            });

            $zeros = [
                'media_sem' => 0.0,
                'prova_bim' => 0.0,
                'enac' => 0.0,
                'participacao' => 0.0,
                'trabalho' => 0.0,
            ];
            foreach ($candidatas as $regra) {
                $comps = $this->db->fetchAll(
                    'SELECT codigo, nome, peso FROM boletim_componentes
                     WHERE regra_id = :id AND ativo = 1
                     ORDER BY ordem ASC, id ASC',
                    ['id' => (int) ($regra['id'] ?? 0)]
                ) ?: [];
                $mapeados = [];
                foreach ($comps as $c) {
                    $chave = $this->mapearComponenteBoletim((string) ($c['codigo'] ?? ''), (string) ($c['nome'] ?? ''));
                    if ($chave === null || $chave === 'recuperacao') {
                        continue;
                    }
                    $mapeados[$chave] = max(0, (float) ($c['peso'] ?? 0));
                }
                if (count($mapeados) < 2) {
                    continue;
                }
                return [
                    'pesos' => array_merge($zeros, $mapeados),
                    'origem' => 'boletim',
                    'regra_nome' => (string) ($regra['nome'] ?? 'Boletim'),
                ];
            }
        } catch (Throwable $e) {
            return $vazio;
        }
        return $vazio;
    }

    private function mapearComponenteBoletim(string $codigo, string $nome): ?string
    {
        $cod = mb_strtolower(trim($codigo), 'UTF-8');
        $porCodigo = [
            'media_sem' => 'media_sem',
            'prova_bim' => 'prova_bim',
            'bimestral' => 'prova_bim',
            'enac' => 'enac',
            'part' => 'participacao',
            'participacao' => 'participacao',
            'participação' => 'participacao',
            'trab' => 'trabalho',
            'trabalho' => 'trabalho',
            'rec' => 'recuperacao',
            'recuperacao' => 'recuperacao',
            'recuperação' => 'recuperacao',
        ];
        if (isset($porCodigo[$cod])) {
            return $porCodigo[$cod];
        }
        $t = mb_strtolower(trim($codigo . ' ' . $nome), 'UTF-8');
        if ($t === '') {
            return null;
        }
        if (str_contains($t, 'semanal') || str_contains($t, 'media_sem') || str_contains($t, 'média sem')) {
            return 'media_sem';
        }
        if (str_contains($t, 'bimestral') || str_contains($t, 'prova_bim') || str_contains($t, 'prova bim')) {
            return 'prova_bim';
        }
        if (str_contains($t, 'enac')) {
            return 'enac';
        }
        if (str_contains($t, 'particip')) {
            return 'participacao';
        }
        if (str_contains($t, 'trabalho') || preg_match('/\btrab\b/u', $t)) {
            return 'trabalho';
        }
        if (str_contains($t, 'recupera')) {
            return 'recuperacao';
        }
        return null;
    }

    private function colunaExiste(string $tabela, string $coluna): bool
    {
        static $cache = [];
        $k = $tabela . '.' . $coluna;
        if (array_key_exists($k, $cache)) {
            return $cache[$k];
        }
        try {
            $row = $this->db->fetch(
                'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c',
                ['t' => $tabela, 'c' => $coluna]
            );
            $cache[$k] = ((int) ($row['c'] ?? 0)) > 0;
        } catch (Throwable $e) {
            $cache[$k] = false;
        }
        return $cache[$k];
    }

    private function resultadoAcademico(): ?ResultadoAcademicoService
    {
        if ($this->resultadoAcademicoSvc !== null) {
            return $this->resultadoAcademicoSvc;
        }
        if (!class_exists('ResultadoAcademicoService', false)) {
            $path = dirname(__DIR__, 3) . '/Services/ResultadoAcademicoService.php';
            if (!is_file($path)) {
                return null;
            }
            require_once $path;
        }
        $this->resultadoAcademicoSvc = new ResultadoAcademicoService();
        return $this->resultadoAcademicoSvc;
    }

    /**
     * @return array{serie_id:?int,curso_id:?int}
     */
    private function contextoAcademicoDoAluno(int $alunoId): array
    {
        $vazio = ['serie_id' => null, 'curso_id' => null];
        if ($alunoId <= 0) {
            return $vazio;
        }
        try {
            $row = $this->db->fetch(
                "SELECT t.serie_id, t.curso_novo_id
                 FROM alunos a
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 WHERE a.id = :id
                 LIMIT 1",
                ['id' => $alunoId]
            );
            if (!$row) {
                return $vazio;
            }
            $serie = (int) ($row['serie_id'] ?? 0);
            $curso = (int) ($row['curso_novo_id'] ?? 0);
            return [
                'serie_id' => $serie > 0 ? $serie : null,
                'curso_id' => $curso > 0 ? $curso : null,
            ];
        } catch (Throwable $e) {
            return $vazio;
        }
    }
}
