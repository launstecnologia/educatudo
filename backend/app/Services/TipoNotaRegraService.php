<?php
/**
 * Regras de cálculo do Tipo de Nota e fechamento da nota_final.
 * O boletim consome só o consolidado — não recalcula prova a prova.
 */

require_once __DIR__ . '/../Models/Exams/ExamEvaluationType.php';
require_once __DIR__ . '/../Models/Exams/NotaTipoFinal.php';

class TipoNotaRegraService
{
    public const ORIGENS = [
        'prova_online' => 'Avaliação online (ocorre dentro da plataforma)',
        'lancamento_direto' => 'Avaliações offline · evento único (informa uma vez)',
        'eventos' => 'Avaliações offline · eventos múltiplos (informa várias vezes)',
    ];

    public const CANAIS_ORIGEM = [
        'online' => [
            'label' => 'Avaliação online',
            'hint' => 'Ocorre dentro da plataforma — o aluno faz a prova no sistema.',
            'origem' => 'prova_online',
        ],
        'offline' => [
            'label' => 'Avaliações offline',
            'hint' => 'Ocorre fora da plataforma — a escola informa a nota.',
        ],
    ];

    public const MODOS_OFFLINE = [
        'lancamento_direto' => [
            'label' => 'Evento único',
            'hint' => 'Informa uma vez no período (ex.: prova bimestral, trabalho).',
        ],
        'eventos' => [
            'label' => 'Eventos múltiplos',
            'hint' => 'Informa várias vezes no período (ex.: lista, recitais, registros).',
        ],
    ];

    public const REGISTROS = [
        'nota' => 'Nota 0–10 (ou escala)',
        'acertos_questoes' => 'Acertos e total de questões',
    ];

    public const CRITERIOS = [
        'ultima' => 'Última nota lançada',
        'maior' => 'Maior nota',
        'media' => 'Média das notas',
        'soma' => 'Soma (depois limita na escala)',
        'aproveitamento_nq' => 'Aproveitamento: (soma acertos ÷ soma questões) × escala',
    ];

    public const CRITERIOS_PROFESSORES = [
        'nenhum' => 'Não junta — cada lançamento entra no critério acima',
        'media' => 'Média das notas dos professores (4 e 6 → 5)',
        'soma' => 'Soma das notas dos professores (4 e 6 → 10)',
    ];

    private ExamEvaluationType $tipos;
    private NotaTipoFinal $finais;
    private $db;

    public function __construct()
    {
        $this->tipos = new ExamEvaluationType();
        $this->finais = new NotaTipoFinal();
        $this->db = Database::getInstance();
    }

    public function tipos(): ExamEvaluationType
    {
        return $this->tipos;
    }

    public function finais(): NotaTipoFinal
    {
        return $this->finais;
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function normalizar(array $post): array
    {
        $nome = trim((string) ($post['nome'] ?? ''));
        $descricao = trim((string) ($post['descricao'] ?? ''));
        $origemRaw = $post['origem'] ?? 'lancamento_direto';
        $canal = strtolower(trim((string) ($post['canal_origem'] ?? '')));
        if ($canal === 'online') {
            $origemRaw = 'prova_online';
        } elseif ($canal === 'offline') {
            $modo = strtolower(trim((string) ($post['modo_offline'] ?? '')));
            $origemRaw = $modo === 'eventos' ? 'eventos' : 'lancamento_direto';
        }
        $origem = $this->normalizarOrigem($origemRaw);
        $registro = $this->normalizarRegistro($post['registro_evento'] ?? 'nota');
        $criterio = $this->normalizarCriterio($post['criterio_fechamento'] ?? 'ultima');
        $escala = isset($post['escala_max']) ? (float) $post['escala_max'] : 10.0;
        if ($escala <= 0) {
            $escala = 10.0;
        }
        if ($escala > 100) {
            $escala = 100.0;
        }

        $qtd = isset($post['quantidade_eventos_esperada']) ? (int) $post['quantidade_eventos_esperada'] : 0;
        if ($qtd <= 0) {
            $qtd = 0;
        }
        if ($qtd > 40) {
            $qtd = 40;
        }

        if ($origem === 'lancamento_direto') {
            $registro = 'nota';
            if ($criterio === 'aproveitamento_nq') {
                $criterio = 'ultima';
            }
            $qtd = 0;
        }
        if ($origem === 'prova_online') {
            $registro = 'acertos_questoes';
            if ($criterio === 'ultima') {
                $criterio = 'aproveitamento_nq';
            }
        }
        if ($registro === 'acertos_questoes' && $criterio === 'ultima' && $origem !== 'lancamento_direto') {
            $criterio = 'aproveitamento_nq';
        }

        $criterioProf = $this->normalizarCriterioProfessores(
            $post['criterio_professores_mesmo_componente'] ?? ($post['media_professores_mesmo_componente'] ?? 'nenhum')
        );

        return [
            'nome' => $nome,
            'descricao' => $descricao !== '' ? $descricao : null,
            'ativo' => isset($post['ativo']) ? 1 : 0,
            'origem' => $origem,
            'registro_evento' => $registro,
            'criterio_fechamento' => $criterio,
            'escala_max' => round($escala, 2),
            'quantidade_eventos_esperada' => $qtd > 0 ? $qtd : null,
            'criterio_professores_mesmo_componente' => $criterioProf,
            'media_professores_mesmo_componente' => in_array($criterioProf, ['media', 'soma'], true) ? 1 : 0,
        ];
    }

    public function normalizarOrigem($valor): string
    {
        $v = strtolower(trim((string) $valor));
        return array_key_exists($v, self::ORIGENS) ? $v : 'lancamento_direto';
    }

    public static function canalDaOrigem(string $origem): string
    {
        return $origem === 'prova_online' ? 'online' : 'offline';
    }

    public function normalizarRegistro($valor): string
    {
        $v = strtolower(trim((string) $valor));
        return array_key_exists($v, self::REGISTROS) ? $v : 'nota';
    }

    public function normalizarCriterio($valor): string
    {
        $v = strtolower(trim((string) $valor));
        return array_key_exists($v, self::CRITERIOS) ? $v : 'ultima';
    }

    public function normalizarCriterioProfessores($valor): string
    {
        if ($valor === 1 || $valor === '1' || $valor === true) {
            return 'media';
        }
        $v = strtolower(trim((string) $valor));
        return array_key_exists($v, self::CRITERIOS_PROFESSORES) ? $v : 'nenhum';
    }

    /**
     * @param array<string,mixed> $tipo
     */
    public function criterioProfessoresDoTipo(array $tipo): string
    {
        if (isset($tipo['criterio_professores_mesmo_componente']) && (string) $tipo['criterio_professores_mesmo_componente'] !== '') {
            return $this->normalizarCriterioProfessores($tipo['criterio_professores_mesmo_componente']);
        }
        return !empty($tipo['media_professores_mesmo_componente']) ? 'media' : 'nenhum';
    }

    public function rotuloCalculo(array $tipo): string
    {
        if (!$this->tipos->temColunasRegras()) {
            return '—';
        }
        $origem = $this->normalizarOrigem($tipo['origem'] ?? 'lancamento_direto');
        $criterio = $this->normalizarCriterio($tipo['criterio_fechamento'] ?? 'ultima');
        if ($origem === 'lancamento_direto') {
            return 'Offline · evento único';
        }
        $prefixo = $origem === 'prova_online' ? 'Online' : 'Offline · eventos múltiplos';
        $base = $prefixo . ' · ' . (self::CRITERIOS[$criterio] ?? $criterio);
        $prof = $this->criterioProfessoresDoTipo($tipo);
        if ($prof === 'media') {
            $base .= ' · média entre professores';
        } elseif ($prof === 'soma') {
            $base .= ' · soma entre professores';
        }
        return $base;
    }

    /**
     * Tipo fecha uma nota_final (boletim não precisa varrer eventos).
     */
    public function tipoFechaNotaFinal(?array $tipo): bool
    {
        if ($tipo === null || !$this->tipos->temColunasRegras()) {
            return false;
        }
        $origem = $this->normalizarOrigem($tipo['origem'] ?? 'lancamento_direto');
        return in_array($origem, ['lancamento_direto', 'eventos', 'prova_online'], true);
    }

    /**
     * Recalcula e grava a nota_final de um aluno/componente/período.
     *
     * @return array<int, array<string,mixed>> mapa materia_id => linha persistida
     */
    public function fecharAluno(int $tipoId, int $alunoId, int $turmaId, int $anoLetivo, int $periodo): array
    {
        $tipo = $this->tipos->findById($tipoId);
        if ($tipo === null || !$this->tipoFechaNotaFinal($tipo) || $alunoId <= 0) {
            return [];
        }

        $eventos = $this->coletarEventos($tipoId, $alunoId, $turmaId, $anoLetivo, $periodo);
        $porMateria = $this->agruparPorMateria($eventos, $tipo);
        $out = [];
        foreach ($porMateria as $materiaId => $fechado) {
            $linha = [
                'tipo_avaliacao_id' => $tipoId,
                'aluno_id' => $alunoId,
                'materia_id' => (int) $materiaId,
                'turma_id' => $turmaId,
                'ano_letivo' => $anoLetivo,
                'periodo' => $periodo,
                'nota_final' => $fechado['nota_final'],
                'acertos_soma' => $fechado['acertos_soma'],
                'questoes_soma' => $fechado['questoes_soma'],
                'eventos_qtd' => $fechado['eventos_qtd'],
            ];
            $this->finais->upsert($linha);
            $out[(int) $materiaId] = $linha;
        }
        return $out;
    }

    /**
     * Lê o consolidado; se vazio, fecha na hora.
     *
     * @return array<int, float> materia_id => nota_final
     */
    public function notasFinaisDoAluno(int $tipoId, int $alunoId, int $turmaId, int $anoLetivo, int $periodo): array
    {
        if ($tipoId <= 0 || $alunoId <= 0) {
            return [];
        }
        $tipo = $this->tipos->findById($tipoId);
        if (!$this->tipoFechaNotaFinal($tipo)) {
            return [];
        }

        $linhas = $this->finais->listarDoAluno($tipoId, $alunoId, $turmaId, $anoLetivo, $periodo);
        if ($linhas === []) {
            $linhas = array_values($this->fecharAluno($tipoId, $alunoId, $turmaId, $anoLetivo, $periodo));
        }
        $out = [];
        foreach ($linhas as $linha) {
            $mid = (int) ($linha['materia_id'] ?? 0);
            if (!isset($linha['nota_final']) || !is_numeric($linha['nota_final'])) {
                continue;
            }
            $out[$mid] = round((float) $linha['nota_final'], 2);
        }
        return $out;
    }

    /**
     * Recalcula após lançamento na pauta (não quebra o save se falhar).
     */
    public function atualizarAposLancamento(int $blocoId, int $alunoId, int $turmaId, int $materiaId): void
    {
        if ($blocoId <= 0 || $alunoId <= 0) {
            return;
        }
        try {
            $bloco = $this->db->fetch(
                "SELECT tipo_avaliacao_id, ano_letivo, bimestre
                 FROM provas_blocos
                 WHERE id = :id AND deleted_at IS NULL
                 LIMIT 1",
                ['id' => $blocoId]
            );
        } catch (Exception $e) {
            return;
        }
        $tipoId = (int) ($bloco['tipo_avaliacao_id'] ?? 0);
        if ($tipoId <= 0) {
            return;
        }
        $ano = (int) ($bloco['ano_letivo'] ?? date('Y'));
        $periodo = (int) ($bloco['bimestre'] ?? 0);
        $this->fecharAluno($tipoId, $alunoId, $turmaId, $ano, $periodo);
        unset($materiaId);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function coletarEventos(int $tipoId, int $alunoId, int $turmaId, int $anoLetivo, int $periodo): array
    {
        $blocos = $this->idsBlocosDoTipo($tipoId, $turmaId, $anoLetivo, $periodo);
        if ($blocos === []) {
            return [];
        }
        $ph = [];
        $params = ['aluno' => $alunoId];
        foreach ($blocos as $i => $bid) {
            $k = 'b' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $bid;
        }
        $in = implode(',', $ph);
        $out = [];

        try {
            $manuais = $this->db->fetchAll(
                "SELECT n.bloco_id, n.professor_id, n.materia_id, n.nota,
                        COALESCE(n.updated_at, n.created_at) AS quando
                 FROM provas_blocos_notas_lancadas n
                 WHERE n.aluno_id = :aluno
                   AND n.bloco_id IN ($in)
                   AND n.nota IS NOT NULL",
                $params
            ) ?: [];
            foreach ($manuais as $row) {
                $out[] = [
                    'bloco_id' => (int) ($row['bloco_id'] ?? 0),
                    'professor_id' => (int) ($row['professor_id'] ?? 0),
                    'materia_id' => (int) ($row['materia_id'] ?? 0),
                    'nota' => (float) ($row['nota'] ?? 0),
                    'acertos' => 0,
                    'questoes' => 0,
                    'quando' => (string) ($row['quando'] ?? ''),
                ];
            }
        } catch (Exception $e) {
            // tabela pode não existir em tenant antigo
        }

        try {
            $online = $this->db->fetchAll(
                "SELECT pb.id AS bloco_id,
                        COALESCE(p.professor_id, 0) AS professor_id,
                        COALESCE(p.materia_id, 0) AS materia_id,
                        pr.nota,
                        pr.finalizado_em AS quando,
                        pr.prova_id,
                        pr.aluno_id
                 FROM provas_realizacoes pr
                 INNER JOIN provas p ON p.id = pr.prova_id
                 INNER JOIN provas_blocos_vinculo pbv ON pbv.prova_id = pr.prova_id
                 INNER JOIN provas_blocos pb ON pb.id = pbv.bloco_id AND pb.deleted_at IS NULL
                 WHERE pr.aluno_id = :aluno
                   AND pb.id IN ($in)
                   AND pr.status = 'finalizado'",
                $params
            ) ?: [];
            $stats = $this->estatisticasQuestoes($alunoId, $online);
            foreach ($online as $row) {
                $provaId = (int) ($row['prova_id'] ?? 0);
                $st = $stats[$provaId] ?? ['acertos' => 0, 'questoes' => 0];
                $out[] = [
                    'bloco_id' => (int) ($row['bloco_id'] ?? 0),
                    'professor_id' => (int) ($row['professor_id'] ?? 0),
                    'materia_id' => (int) ($row['materia_id'] ?? 0),
                    'nota' => isset($row['nota']) && $row['nota'] !== null ? (float) $row['nota'] : null,
                    'acertos' => (int) $st['acertos'],
                    'questoes' => (int) $st['questoes'],
                    'quando' => (string) ($row['quando'] ?? ''),
                ];
            }
        } catch (Exception $e) {
            // sem provas online neste tenant
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $online
     * @return array<int, array{acertos:int,questoes:int}>
     */
    private function estatisticasQuestoes(int $alunoId, array $online): array
    {
        $provaIds = [];
        foreach ($online as $row) {
            $pid = (int) ($row['prova_id'] ?? 0);
            if ($pid > 0) {
                $provaIds[$pid] = true;
            }
        }
        $ids = array_keys($provaIds);
        if ($ids === []) {
            return [];
        }
        $ph = [];
        $params = ['aluno' => $alunoId];
        foreach ($ids as $i => $pid) {
            $k = 'p' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $pid;
        }
        try {
            $rows = $this->db->fetchAll(
                "SELECT prova_id,
                        COUNT(*) AS questoes,
                        SUM(CASE WHEN correta = 1 THEN 1 ELSE 0 END) AS acertos
                 FROM provas_respostas
                 WHERE aluno_id = :aluno AND prova_id IN (" . implode(',', $ph) . ")
                 GROUP BY prova_id",
                $params
            ) ?: [];
        } catch (Exception $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['prova_id']] = [
                'acertos' => (int) ($row['acertos'] ?? 0),
                'questoes' => (int) ($row['questoes'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * @return list<int>
     */
    private function idsBlocosDoTipo(int $tipoId, int $turmaId, int $anoLetivo, int $periodo): array
    {
        $sql = "SELECT DISTINCT pb.id
                FROM provas_blocos pb
                WHERE pb.deleted_at IS NULL
                  AND pb.tipo_avaliacao_id = :tipo";
        $params = ['tipo' => $tipoId];
        if ($anoLetivo > 0) {
            $sql .= " AND (pb.ano_letivo = :ano OR pb.ano_letivo IS NULL)";
            $params['ano'] = $anoLetivo;
        }
        if ($periodo > 0) {
            $sql .= " AND (pb.bimestre = :periodo OR pb.bimestre IS NULL OR pb.bimestre = 0)";
            $params['periodo'] = $periodo;
        }
        if ($turmaId > 0) {
            $sql .= " AND (
                pb.turma_id = :turma
                OR EXISTS (SELECT 1 FROM provas_blocos_turmas pbt WHERE pbt.bloco_id = pb.id AND pbt.turma_id = :turma2)
                OR EXISTS (
                    SELECT 1 FROM provas_blocos_professores pbp
                    INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp.id
                    WHERE pbp.bloco_id = pb.id AND pbpt.turma_id = :turma3
                )
            )";
            $params['turma'] = $turmaId;
            $params['turma2'] = $turmaId;
            $params['turma3'] = $turmaId;
        }
        try {
            $rows = $this->db->fetchAll($sql, $params) ?: [];
        } catch (Exception $e) {
            return [];
        }
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * @param list<array<string,mixed>> $eventos
     * @param array<string,mixed> $tipo
     * @return array<int, array{nota_final:float,acertos_soma:int,questoes_soma:int,eventos_qtd:int}>
     */
    private function agruparPorMateria(array $eventos, array $tipo): array
    {
        $porMateria = [];
        foreach ($eventos as $ev) {
            $mid = (int) ($ev['materia_id'] ?? 0);
            if (!isset($porMateria[$mid])) {
                $porMateria[$mid] = [];
            }
            $porMateria[$mid][] = $ev;
        }

        $criterioProf = $this->criterioProfessoresDoTipo($tipo);
        $escala = (float) ($tipo['escala_max'] ?? 10);
        if ($escala <= 0) {
            $escala = 10.0;
        }
        $out = [];
        foreach ($porMateria as $mid => $lista) {
            if ($criterioProf === 'media' || $criterioProf === 'soma') {
                $porProf = [];
                foreach ($lista as $ev) {
                    $pid = (int) ($ev['professor_id'] ?? 0);
                    if (!isset($porProf[$pid])) {
                        $porProf[$pid] = [];
                    }
                    $porProf[$pid][] = $ev;
                }
                $notasProf = [];
                $acertos = 0;
                $questoes = 0;
                $qtd = 0;
                foreach ($porProf as $listaProf) {
                    $f = $this->fecharLista($listaProf, $tipo);
                    if ($f === null) {
                        continue;
                    }
                    $notasProf[] = $f['nota_final'];
                    $acertos += $f['acertos_soma'];
                    $questoes += $f['questoes_soma'];
                    $qtd += $f['eventos_qtd'];
                }
                if ($notasProf === []) {
                    continue;
                }
                $nota = $criterioProf === 'soma'
                    ? array_sum($notasProf)
                    : (array_sum($notasProf) / count($notasProf));
                $out[(int) $mid] = [
                    'nota_final' => round(max(0.0, min($escala, (float) $nota)), 2),
                    'acertos_soma' => $acertos,
                    'questoes_soma' => $questoes,
                    'eventos_qtd' => $qtd,
                ];
                continue;
            }
            $f = $this->fecharLista($lista, $tipo);
            if ($f !== null) {
                $out[(int) $mid] = $f;
            }
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $lista
     * @param array<string,mixed> $tipo
     * @return array{nota_final:float,acertos_soma:int,questoes_soma:int,eventos_qtd:int}|null
     */
    private function fecharLista(array $lista, array $tipo): ?array
    {
        if ($lista === []) {
            return null;
        }
        usort($lista, static function (array $a, array $b): int {
            return strcmp((string) ($b['quando'] ?? ''), (string) ($a['quando'] ?? ''));
        });

        $escala = (float) ($tipo['escala_max'] ?? 10);
        if ($escala <= 0) {
            $escala = 10.0;
        }
        $criterio = $this->normalizarCriterio($tipo['criterio_fechamento'] ?? 'ultima');
        $acertos = 0;
        $questoes = 0;
        $notas = [];
        foreach ($lista as $ev) {
            $acertos += max(0, (int) ($ev['acertos'] ?? 0));
            $questoes += max(0, (int) ($ev['questoes'] ?? 0));
            if (isset($ev['nota']) && is_numeric($ev['nota'])) {
                $notas[] = (float) $ev['nota'];
            }
        }

        $valor = null;
        if ($criterio === 'aproveitamento_nq') {
            if ($questoes > 0) {
                $valor = ($acertos / $questoes) * $escala;
            } elseif ($notas !== []) {
                $valor = array_sum($notas) / count($notas);
            }
        } elseif ($criterio === 'soma') {
            $valor = $notas !== [] ? array_sum($notas) : null;
        } elseif ($criterio === 'maior') {
            $valor = $notas !== [] ? max($notas) : null;
        } elseif ($criterio === 'media') {
            $valor = $notas !== [] ? (array_sum($notas) / count($notas)) : null;
        } else {
            $valor = $notas !== [] ? $notas[0] : null;
        }

        if ($valor === null) {
            return null;
        }
        $valor = round(max(0.0, min($escala, (float) $valor)), 2);

        return [
            'nota_final' => $valor,
            'acertos_soma' => $acertos,
            'questoes_soma' => $questoes,
            'eventos_qtd' => count($lista),
        ];
    }
}
