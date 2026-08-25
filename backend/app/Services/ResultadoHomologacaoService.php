<?php
require_once __DIR__ . '/../Models/Education/ResultadoAcademico.php';
require_once __DIR__ . '/../Models/Education/ClassDiary.php';
require_once __DIR__ . '/FrequencyService.php';
require_once __DIR__ . '/ResultadoAcademicoService.php';

/**
 * EducaTudo - Fechamento e homologação do resultado acadêmico.
 *
 * Consome boletim gerado, frequência do diário, regras acadêmicas, conselho
 * e situações especiais. Não recalcula fórmula de nota.
 */
class ResultadoHomologacaoService
{
    private ResultadoAcademico $model;
    private ResultadoAcademicoService $motor;
    private FrequencyService $frequencia;
    private ClassDiary $diario;
    private $db;

    public function __construct(
        ?ResultadoAcademico $model = null,
        ?ResultadoAcademicoService $motor = null,
        ?FrequencyService $frequencia = null,
        ?ClassDiary $diario = null
    ) {
        $this->model = $model ?? new ResultadoAcademico();
        $this->motor = $motor ?? new ResultadoAcademicoService();
        $this->frequencia = $frequencia ?? new FrequencyService();
        $this->diario = $diario ?? new ClassDiary();
        $this->db = Database::getInstance();
    }

    public function model(): ResultadoAcademico
    {
        return $this->model;
    }

    public function motor(): ResultadoAcademicoService
    {
        return $this->motor;
    }

    /**
     * @return array{
     *   turma:array<string,mixed>,
     *   periodo:array{tipo:string,numero:int,label:string,inicio:string,fim:string},
     *   config:array{exigir_conselho:bool,exigir_frequencia:bool,exigir_notas:bool},
     *   linhas:list<array<string,mixed>>,
     *   resumo:array<string,int>,
     *   pode_homologar:bool
     * }
     */
    public function previewTurma(int $turmaId, int $anoLetivo, string $periodoTipo = 'ano', int $periodoNumero = 0): array
    {
        $turma = $this->turma($turmaId);
        $periodo = $this->resolverPeriodo($anoLetivo, $periodoTipo, $periodoNumero);
        $config = $this->model->getConfigFechamento();
        $alunos = $this->model->alunosDaTurma($turmaId, $anoLetivo);
        $notas = $this->notasDoBoletim($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        $freqs = $this->frequenciasPorAluno($turmaId, $periodo['inicio'], $periodo['fim']);
        $conselho = $this->conselhoDaTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        $homologados = $this->indexarPorAluno(
            $this->model->listarDaTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero)
        );
        $especiaisTurma = $this->indexarEspeciais(
            $this->model->listarEspeciaisTurma($turmaId, $anoLetivo)
        );

        $linhas = [];
        $resumo = [
            'total' => 0,
            'homologados' => 0,
            'pendencias' => 0,
            'aprovados' => 0,
            'reprovados' => 0,
            'recuperacao' => 0,
            'outros' => 0,
        ];

        foreach ($alunos as $aluno) {
            $linha = $this->montarLinhaPreview(
                $aluno,
                $turma,
                $anoLetivo,
                $periodo,
                $notas,
                $freqs,
                $conselho,
                $homologados,
                $especiaisTurma[(int) $aluno['id']] ?? [],
                $config
            );
            $linhas[] = $linha;
            $resumo['total']++;
            if (($linha['status'] ?? '') === 'homologado') {
                $resumo['homologados']++;
            }
            if (!empty($linha['pendencias_criticas'])) {
                $resumo['pendencias']++;
            }
            $sit = (string) ($linha['situacao'] ?? '');
            if (in_array($sit, ['aprovado', 'aprovado_recuperacao', 'aprovado_conselho', 'aproveitamento'], true)) {
                $resumo['aprovados']++;
            } elseif (in_array($sit, ['reprovado_rendimento', 'reprovado_frequencia'], true)) {
                $resumo['reprovados']++;
            } elseif (in_array($sit, ['recuperacao', 'exame_final'], true)) {
                $resumo['recuperacao']++;
            } else {
                $resumo['outros']++;
            }
        }

        $pode = $resumo['pendencias'] === 0 && $resumo['total'] > 0;

        return [
            'turma' => $turma,
            'periodo' => $periodo,
            'config' => $config,
            'linhas' => $linhas,
            'resumo' => $resumo,
            'pode_homologar' => $pode,
        ];
    }

    /**
     * Homologa alunos da turma (ou um subconjunto). Não homologa quem tem pendência crítica.
     *
     * @param list<int> $alunoIds IDs selecionados. Vazio só é aceito com $todosElegiveis = true.
     * @return array{success:bool,error?:string,homologados?:int,ignorados?:int}
     */
    public function homologarTurma(
        int $turmaId,
        int $anoLetivo,
        string $periodoTipo,
        int $periodoNumero,
        int $usuarioId,
        array $alunoIds = [],
        bool $todosElegiveis = false
    ): array {
        $filtro = [];
        foreach ($alunoIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $filtro[$id] = true;
            }
        }
        if ($filtro === [] && !$todosElegiveis) {
            return ['success' => false, 'error' => 'Selecione ao menos um aluno, ou use Homologar todos os elegíveis.'];
        }

        $preview = $this->previewTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);

        $homologados = 0;
        $ignorados = 0;
        foreach ($preview['linhas'] as $linha) {
            $alunoId = (int) ($linha['aluno']['id'] ?? 0);
            if ($alunoId <= 0) {
                continue;
            }
            if ($filtro !== [] && !isset($filtro[$alunoId])) {
                continue;
            }
            if (($linha['status'] ?? '') === 'homologado') {
                $ignorados++;
                continue;
            }
            if (!empty($linha['pendencias_criticas'])) {
                $ignorados++;
                continue;
            }
            $this->gravarHomologacao($linha, $usuarioId);
            $homologados++;
        }

        if ($homologados === 0 && $ignorados === 0) {
            return ['success' => false, 'error' => 'Nenhum aluno elegível para homologar.'];
        }
        return ['success' => true, 'homologados' => $homologados, 'ignorados' => $ignorados];
    }

    /**
     * @return array{success:bool,error?:string}
     */
    public function reabrir(int $resultadoId, int $usuarioId, string $motivo): array
    {
        $doc = $this->model->findById($resultadoId);
        if (!$doc) {
            return ['success' => false, 'error' => 'Resultado não encontrado.'];
        }
        if ((string) $doc['status'] !== 'homologado') {
            return ['success' => false, 'error' => 'Só é possível reabrir resultado homologado.'];
        }
        $motivo = trim($motivo);
        if ($motivo === '') {
            return ['success' => false, 'error' => 'Informe o motivo da reabertura.'];
        }

        $this->model->registrarHistorico($resultadoId, [
            'versao' => (int) ($doc['versao'] ?? 1),
            'status' => (string) $doc['status'],
            'situacao' => (string) $doc['situacao'],
            'rotulo' => (string) $doc['rotulo'],
            'snapshot_json' => $doc['snapshot_json'] ?? null,
            'motivo' => $motivo,
            'usuario_id' => $usuarioId,
        ]);
        $this->model->atualizar($resultadoId, [
            'status' => 'reaberto',
            'reaberto_em' => date('Y-m-d H:i:s'),
            'reaberto_por' => $usuarioId,
            'reaberto_motivo' => $motivo,
        ]);
        return ['success' => true];
    }

    /**
     * Payload oficial para documentos. Usa snapshot se homologado.
     *
     * @return array<string,mixed>|null
     */
    public function payloadAluno(int $alunoId, int $turmaId, int $anoLetivo, string $periodoTipo = 'ano', int $periodoNumero = 0): ?array
    {
        $vigente = $this->model->findVigente($alunoId, $turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        if ($vigente && (string) $vigente['status'] === 'homologado' && !empty($vigente['snapshot_json'])) {
            $snap = json_decode((string) $vigente['snapshot_json'], true);
            if (is_array($snap)) {
                $snap['_homologado'] = true;
                $snap['_resultado_id'] = (int) $vigente['id'];
                $snap['_versao'] = (int) ($vigente['versao'] ?? 1);
                $snap['status'] = 'homologado';
                if (empty($snap['turma']) || !is_array($snap['turma'])) {
                    $snap['turma'] = $this->turma($turmaId) ?: [];
                    $snap['turma_id'] = $turmaId;
                }
                return $snap;
            }
        }

        $preview = $this->previewTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        foreach ($preview['linhas'] as $linha) {
            if ((int) ($linha['aluno']['id'] ?? 0) === $alunoId) {
                $linha['_homologado'] = false;
                $linha['_resultado_id'] = isset($vigente['id']) ? (int) $vigente['id'] : 0;
                $linha['_versao'] = isset($vigente['versao']) ? (int) $vigente['versao'] : 0;
                $linha['turma'] = $preview['turma'];
                $linha['periodo'] = $preview['periodo'];
                return $linha;
            }
        }
        return null;
    }

    /**
     * @return array{success:bool,error?:string,id?:int}
     */
    public function salvarEspecial(array $input, int $usuarioId): array
    {
        $alunoId = (int) ($input['aluno_id'] ?? 0);
        $ano = (int) ($input['ano_letivo'] ?? 0);
        $tipo = (string) ($input['tipo'] ?? '');
        if ($alunoId <= 0 || $ano <= 0 || !isset(ResultadoAcademico::ESPECIAIS[$tipo])) {
            return ['success' => false, 'error' => 'Informe aluno, ano letivo e tipo válido.'];
        }
        $turmaId = (int) ($input['turma_id'] ?? 0);
        $naTurma = false;
        foreach ($this->model->alunosDaTurma($turmaId, $ano) as $a) {
            if ((int) ($a['id'] ?? 0) === $alunoId) {
                $naTurma = true;
                break;
            }
        }
        if (!$naTurma) {
            return ['success' => false, 'error' => 'O aluno não pertence a esta turma.'];
        }
        $materiaId = (int) ($input['materia_id'] ?? 0);
        $id = $this->model->criarEspecial([
            'aluno_id' => $alunoId,
            'turma_id' => (int) ($input['turma_id'] ?? 0) ?: null,
            'ano_letivo' => $ano,
            'materia_id' => $materiaId > 0 ? $materiaId : null,
            'tipo' => $tipo,
            'observacao' => trim((string) ($input['observacao'] ?? '')) ?: null,
            'criado_por' => $usuarioId,
        ]);
        return ['success' => true, 'id' => $id];
    }

    public function periodoLabel(string $tipo, int $numero): string
    {
        if ($tipo === 'ano' || $numero <= 0) {
            return 'Ano letivo';
        }
        $mapa = [
            'bimestre' => 'º Bimestre',
            'trimestre' => 'º Trimestre',
            'semestre' => 'º Semestre',
        ];
        $suf = $mapa[$tipo] ?? 'º período';
        return $numero . $suf;
    }

    /**
     * @param array<string,mixed> $linha
     */
    private function gravarHomologacao(array $linha, int $usuarioId): void
    {
        $aluno = $linha['aluno'] ?? [];
        $periodo = $linha['periodo'] ?? [];
        $avaliado = $linha['avaliado'] ?? [];
        $snapshot = json_encode($linha, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $id = $this->model->upsertVigente([
            'aluno_id' => (int) $aluno['id'],
            'turma_id' => (int) ($linha['turma_id'] ?? 0),
            'ano_letivo' => (int) ($periodo['ano_letivo'] ?? 0),
            'periodo_tipo' => (string) ($periodo['tipo'] ?? 'ano'),
            'periodo_numero' => (int) ($periodo['numero'] ?? 0),
            'versao' => (int) ($linha['versao_proxima'] ?? 1),
            'status' => 'homologado',
            'situacao' => (string) ($avaliado['situacao'] ?? 'em_andamento'),
            'rotulo' => (string) ($avaliado['rotulo'] ?? 'Em andamento'),
            'media_final' => $avaliado['media_final'] ?? null,
            'frequencia_percentual' => $linha['frequencia']['percentual'] ?? null,
            'faltas' => $linha['frequencia']['faltas'] ?? null,
            'regra_id' => $avaliado['regra_id'] ?? null,
            'regra_versao' => $avaliado['regra_versao'] ?? null,
            'conselho_sessao_id' => $linha['conselho']['sessao_id'] ?? null,
            'conselho_resultado' => $linha['conselho']['resultado'] ?? null,
            'snapshot_json' => $snapshot,
            'homologado_em' => date('Y-m-d H:i:s'),
            'homologado_por' => $usuarioId,
            'reaberto_em' => null,
            'reaberto_por' => null,
            'reaberto_motivo' => null,
        ]);

        $this->model->substituirItens($id, $linha['componentes'] ?? []);
        $this->model->registrarHistorico($id, [
            'versao' => (int) ($linha['versao_proxima'] ?? 1),
            'status' => 'homologado',
            'situacao' => (string) ($avaliado['situacao'] ?? ''),
            'rotulo' => (string) ($avaliado['rotulo'] ?? ''),
            'snapshot_json' => $snapshot,
            'motivo' => 'Homologação',
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * @param array<string,mixed> $aluno
     * @param array<string,mixed> $turma
     * @param array{tipo:string,numero:int,label:string,inicio:string,fim:string,ano_letivo:int} $periodo
     * @param array{componentes:list,por_aluno:array,nota_minima:float} $notas
     * @param array<int,array<string,mixed>> $freqs
     * @param array{sessao:?array,por_aluno:array<int,string>} $conselho
     * @param array<int,array<string,mixed>> $homologados
     * @param list<array<string,mixed>> $especiais
     * @param array{exigir_conselho:bool,exigir_frequencia:bool,exigir_notas:bool} $config
     * @return array<string,mixed>
     */
    private function montarLinhaPreview(
        array $aluno,
        array $turma,
        int $anoLetivo,
        array $periodo,
        array $notas,
        array $freqs,
        array $conselho,
        array $homologados,
        array $especiais,
        array $config
    ): array {
        $alunoId = (int) $aluno['id'];
        $transferido = !empty($aluno['transferido']);
        $homolog = $homologados[$alunoId] ?? null;
        if (is_array($homolog) && (string) ($homolog['status'] ?? '') === 'homologado' && !empty($homolog['snapshot_json'])) {
            $snap = json_decode((string) $homolog['snapshot_json'], true);
            if (is_array($snap)) {
                $snap['status'] = 'homologado';
                $snap['resultado_id'] = (int) ($homolog['id'] ?? 0);
                $snap['turma'] = is_array($snap['turma'] ?? null) ? $snap['turma'] : $turma;
                $snap['turma_id'] = (int) ($snap['turma_id'] ?? $turma['id'] ?? 0);
                $snap['periodo'] = is_array($snap['periodo'] ?? null) ? $snap['periodo'] : $periodo;
                $snap['pendencias'] = [];
                $snap['pendencias_criticas'] = [];
                $snap['_homologado'] = true;
                $snap['_resultado_id'] = (int) ($homolog['id'] ?? 0);
                if (!isset($snap['notas_completas'])) {
                    $snap['notas_completas'] = true;
                }
                return $snap;
            }
        }
        $compsAluno = $notas['por_aluno'][$alunoId] ?? [];
        $freq = $freqs[$alunoId] ?? ['percentual' => null, 'faltas' => 0, 'total_aulas' => 0];
        $homolog = $homologados[$alunoId] ?? null;
        $conselhoResultado = $conselho['por_aluno'][$alunoId] ?? null;
        $especialGeral = $this->especialGeral($especiais);
        $especialPorMateria = $this->especialPorMateria($especiais);

        $contexto = [
            'ano_letivo' => $anoLetivo,
            'curso_id' => (int) ($turma['curso_novo_id'] ?? $turma['curso_id'] ?? 0) ?: null,
            'serie_id' => (int) ($turma['serie_id'] ?? 0) ?: null,
            'matriz_curricular_id' => (int) ($turma['matriz_curricular_id'] ?? 0) ?: null,
            'periodo_tipo' => $periodo['tipo'] === 'ano' ? 'bimestre' : $periodo['tipo'],
            'periodo_numero' => $periodo['numero'] > 0 ? $periodo['numero'] : null,
            'aluno_id' => $alunoId,
            'turma_id' => (int) $turma['id'],
        ];
        $regra = $this->motor->resolverRegra($contexto);
        if ($regra === null) {
            $regra = $this->motor->regraFallbackDoBoletim(['nota_minima_aprovacao' => $notas['nota_minima'] ?? 6]);
        }

        $componentes = [];
        $medias = [];
        $temNota = false;
        $ordem = 0;
        foreach ($notas['componentes'] as $comp) {
            $chave = mb_strtolower((string) ($comp['nome'] ?? ''));
            $cel = $compsAluno[$chave] ?? null;
            $mid = (int) ($comp['id'] ?? ($cel['materia_id'] ?? 0));
            $esp = $especialPorMateria[$mid] ?? $especialGeral;
            $media = is_array($cel) && is_numeric($cel['media'] ?? null) ? (float) $cel['media'] : null;
            if ($media !== null) {
                $temNota = true;
                $medias[] = $media;
            }
            $entradaItem = [
                'media' => $media,
                'tem_nota' => $media !== null,
                'frequencia_percentual' => $freq['percentual'],
                'situacao_matricula' => $transferido ? 'transferido' : null,
                'situacao_especial' => $esp,
                'conselho' => $conselhoResultado === 'aprovado_conselho',
                'aluno_id' => $alunoId,
                'turma_id' => (int) $turma['id'],
                'materia_id' => $mid > 0 ? $mid : null,
                'data_inicio' => $periodo['inicio'],
                'data_fim' => $periodo['fim'],
            ];
            $avaliadoItem = $this->motor->avaliar($entradaItem, $regra);
            $ordem++;
            $componentes[] = [
                'materia_id' => $mid > 0 ? $mid : null,
                'materia_nome' => (string) ($comp['nome'] ?? 'Componente'),
                'carga_horaria' => $this->cargaHoraria($mid),
                'media' => $media,
                'recuperacao' => null,
                'media_final' => $avaliadoItem['media_final'],
                'faltas' => $freq['faltas'] ?? null,
                'frequencia_percentual' => $freq['percentual'],
                'situacao' => $avaliadoItem['situacao'],
                'rotulo' => $avaliadoItem['rotulo'],
                'situacao_especial' => $esp,
                'observacao' => null,
                'ordem' => $ordem,
            ];
        }

        $mediaGeral = $medias !== [] ? round(array_sum($medias) / count($medias), 2) : null;
        $avaliado = $this->motor->avaliar([
            'media' => $mediaGeral,
            'tem_nota' => $temNota,
            'frequencia_percentual' => $freq['percentual'],
            'situacao_matricula' => $transferido ? 'transferido' : null,
            'situacao_especial' => $especialGeral,
            'conselho' => $conselhoResultado === 'aprovado_conselho',
            'aluno_id' => $alunoId,
            'turma_id' => (int) $turma['id'],
            'data_inicio' => $periodo['inicio'],
            'data_fim' => $periodo['fim'],
        ], $regra);

        if ($conselhoResultado && $conselhoResultado !== 'manter' && $conselhoResultado !== 'aprovado_conselho') {
            $map = [
                'aprovado' => 'aprovado',
                'recuperacao' => 'recuperacao',
                'retido' => 'reprovado_rendimento',
                'transferido' => 'transferido',
            ];
            if (isset($map[$conselhoResultado])) {
                $avaliado['situacao'] = $map[$conselhoResultado];
                $avaliado['rotulo'] = $this->motor->rotuloSituacao($avaliado['situacao'], $regra);
            }
        }

        $pendencias = [];
        $pendenciasCriticas = [];
        if ($config['exigir_notas'] && !$temNota && !$transferido && $especialGeral !== 'dispensado') {
            $pendencias[] = 'Notas incompletas';
            $pendenciasCriticas[] = 'notas';
        }
        if ($config['exigir_frequencia'] && ($freq['percentual'] ?? null) === null && !$transferido) {
            $pendencias[] = 'Frequência pendente';
            $pendenciasCriticas[] = 'frequencia';
        }
        if ($config['exigir_conselho'] && empty($conselho['sessao_finalizada']) && !$transferido) {
            $pendencias[] = 'Conselho pendente';
            $pendenciasCriticas[] = 'conselho';
        }

        $status = 'em_andamento';
        $versaoProxima = 1;
        if (is_array($homolog)) {
            $status = (string) ($homolog['status'] ?? 'em_andamento');
            $versaoProxima = (int) ($homolog['versao'] ?? 1);
            if ($status === 'reaberto') {
                $versaoProxima++;
            }
            if ($status === 'homologado') {
                $avaliado['situacao'] = (string) $homolog['situacao'];
                $avaliado['rotulo'] = (string) $homolog['rotulo'];
                $avaliado['media_final'] = $homolog['media_final'];
            }
        }

        return [
            'aluno' => $aluno,
            'turma' => $turma,
            'turma_id' => (int) $turma['id'],
            'periodo' => $periodo,
            'status' => $status,
            'resultado_id' => is_array($homolog) ? (int) $homolog['id'] : 0,
            'versao_proxima' => $versaoProxima,
            'homologado_em' => is_array($homolog) ? ($homolog['homologado_em'] ?? null) : null,
            'notas_completas' => $temNota,
            'componentes' => $componentes,
            'frequencia' => $freq,
            'conselho' => [
                'sessao_id' => $conselho['sessao']['id'] ?? null,
                'resultado' => $conselhoResultado,
                'finalizado' => !empty($conselho['sessao_finalizada']),
            ],
            'avaliado' => $avaliado,
            'situacao' => $avaliado['situacao'],
            'rotulo' => $avaliado['rotulo'],
            'pendencias' => $pendencias,
            'pendencias_criticas' => $pendenciasCriticas,
        ];
    }

    /**
     * @return array{tipo:string,numero:int,label:string,inicio:string,fim:string,ano_letivo:int}
     */
    public function resolverPeriodo(int $anoLetivo, string $periodoTipo, int $periodoNumero): array
    {
        if (!isset(ResultadoAcademico::PERIODO_TIPOS[$periodoTipo])) {
            $periodoTipo = 'ano';
        }
        if ($periodoTipo === 'ano') {
            $periodoNumero = 0;
            $inicio = sprintf('%04d-01-01', $anoLetivo);
            $fim = sprintf('%04d-12-31', $anoLetivo);
        } elseif ($periodoTipo === 'bimestre') {
            $periodoNumero = max(1, min(4, $periodoNumero));
            $p = $this->diario->periodoDoBimestre($anoLetivo, $periodoNumero);
            $inicio = $p['inicio'];
            $fim = $p['fim'];
        } elseif ($periodoTipo === 'trimestre') {
            $periodoNumero = max(1, min(3, $periodoNumero));
            $mesInicio = ($periodoNumero - 1) * 4 + 1;
            $inicio = sprintf('%04d-%02d-01', $anoLetivo, $mesInicio);
            $fim = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anoLetivo, $mesInicio + 3)));
        } else {
            $periodoNumero = max(1, min(2, $periodoNumero));
            $mesInicio = $periodoNumero === 1 ? 1 : 7;
            $inicio = sprintf('%04d-%02d-01', $anoLetivo, $mesInicio);
            $fim = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anoLetivo, $mesInicio + 5)));
        }

        return [
            'tipo' => $periodoTipo,
            'numero' => $periodoNumero,
            'label' => $this->periodoLabel($periodoTipo, $periodoNumero),
            'inicio' => $inicio,
            'fim' => $fim,
            'ano_letivo' => $anoLetivo,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function turma(int $turmaId): array
    {
        $row = $this->db->fetch(
            'SELECT * FROM turmas WHERE id = :id LIMIT 1',
            ['id' => $turmaId]
        );
        return $row ?: ['id' => $turmaId, 'nome' => 'Turma'];
    }

    /**
     * @return array{componentes:list<array{id:?int,nome:string}>,por_aluno:array<int,array<string,array<string,mixed>>>,nota_minima:float}
     */
    private function notasDoBoletim(int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero): array
    {
        $vazio = ['componentes' => [], 'por_aluno' => [], 'nota_minima' => 6.0];
        if (!$this->model->tabelaExiste('boletim_resultados_gerados') || !$this->model->tabelaExiste('boletim_regras')) {
            return $vazio;
        }

        $params = ['turma_id' => $turmaId, 'ano_regra' => $anoLetivo, 'ano_matricula' => $anoLetivo];
        $whereBim = '';
        if ($periodoTipo === 'bimestre' && $periodoNumero >= 1 && $periodoNumero <= 4) {
            $whereBim = ' AND r.bimestre = :bimestre';
            $params['bimestre'] = $periodoNumero;
        }

        $rows = $this->db->fetchAll(
            "SELECT g.aluno_id, g.materia_id, g.materia_nome, g.media_final, g.notas_json,
                    r.nota_minima_aprovacao, r.bimestre
             FROM boletim_resultados_gerados g
             INNER JOIN boletim_regras r ON r.id = g.regra_id
             INNER JOIN alunos a ON a.id = g.aluno_id
             WHERE g.preview = 0 AND r.ano_letivo = :ano_regra {$whereBim}
               AND g.aluno_id IN (
                    SELECT m.aluno_id
                    FROM matricula m
                    INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id
                    WHERE m.turma_id = :turma_id AND al.ano = :ano_matricula
                      AND m.status IN ('ativa', 'concluido', 'transferido')
               )
             ORDER BY g.updated_at DESC, g.ordem_linha ASC, g.id ASC",
            $params
        ) ?: [];

        $componentes = [];
        $vistosComp = [];
        $porAluno = [];
        $notaMinima = 6.0;
        $vistoCel = [];
        foreach ($rows as $row) {
            $nome = trim((string) ($row['materia_nome'] ?? ''));
            if ($nome === '') {
                $nome = 'Sem componente';
            }
            $chave = mb_strtolower($nome);
            if (!isset($vistosComp[$chave])) {
                $vistosComp[$chave] = true;
                $componentes[] = [
                    'id' => isset($row['materia_id']) && $row['materia_id'] !== null ? (int) $row['materia_id'] : null,
                    'nome' => $nome,
                ];
            }
            if (isset($row['nota_minima_aprovacao']) && is_numeric($row['nota_minima_aprovacao'])) {
                $notaMinima = (float) $row['nota_minima_aprovacao'];
            }
            $alunoId = (int) $row['aluno_id'];
            $celKey = $alunoId . '|' . $chave;
            if (isset($vistoCel[$celKey])) {
                continue;
            }
            $vistoCel[$celKey] = true;
            $media = $row['media_final'];
            if ($media === null || $media === '') {
                $notasJson = json_decode((string) ($row['notas_json'] ?? ''), true);
                if (is_array($notasJson)) {
                    foreach (['media_final', 'media_bim', 'media'] as $codigo) {
                        if (isset($notasJson[$codigo]) && is_numeric($notasJson[$codigo])) {
                            $media = $notasJson[$codigo];
                            break;
                        }
                    }
                }
            }
            $porAluno[$alunoId][$chave] = [
                'nome' => $nome,
                'materia_id' => isset($row['materia_id']) && $row['materia_id'] !== null ? (int) $row['materia_id'] : null,
                'media' => is_numeric($media) ? (float) $media : null,
            ];
        }

        return ['componentes' => $componentes, 'por_aluno' => $porAluno, 'nota_minima' => $notaMinima];
    }

    /**
     * @return array<int,array{percentual:?float,faltas:int,total_aulas:int}>
     */
    private function frequenciasPorAluno(int $turmaId, string $inicio, string $fim): array
    {
        $lista = $this->frequencia->alunosPercentual($turmaId, $inicio, $fim);
        $out = [];
        foreach ($lista as $item) {
            $out[(int) $item['aluno_id']] = [
                'percentual' => $item['percentual'],
                'faltas' => (int) ($item['faltas'] ?? 0),
                'total_aulas' => (int) ($item['total_aulas'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * @return array{sessao:?array,sessao_finalizada:bool,por_aluno:array<int,string>}
     */
    private function conselhoDaTurma(int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero): array
    {
        $vazio = ['sessao' => null, 'sessao_finalizada' => false, 'por_aluno' => []];
        if (!$this->model->tabelaExiste('conselho_sessoes') || !$this->model->tabelaExiste('conselho_deliberacoes')) {
            return $vazio;
        }
        $params = ['turma' => $turmaId, 'ano' => $anoLetivo];
        $sql = 'SELECT * FROM conselho_sessoes WHERE turma_id = :turma AND ano_letivo = :ano';
        if ($periodoTipo === 'bimestre' && $periodoNumero >= 1 && $periodoNumero <= 4) {
            $sql .= ' AND bimestre = :bim';
            $params['bim'] = $periodoNumero;
        }
        $sql .= ' ORDER BY bimestre DESC, id DESC';
        $sessao = $this->db->fetch($sql, $params);
        if (!$sessao) {
            return $vazio;
        }
        $rows = $this->db->fetchAll(
            "SELECT d.aluno_id, d.resultado_decisao
             FROM conselho_deliberacoes d
             INNER JOIN (
                SELECT aluno_id, MAX(id) AS max_id
                FROM conselho_deliberacoes
                WHERE sessao_id = :sid AND materia_id IS NULL
                GROUP BY aluno_id
             ) x ON x.max_id = d.id",
            ['sid' => (int) $sessao['id']]
        ) ?: [];
        $porAluno = [];
        foreach ($rows as $row) {
            $porAluno[(int) $row['aluno_id']] = (string) $row['resultado_decisao'];
        }
        return [
            'sessao' => $sessao,
            'sessao_finalizada' => (string) ($sessao['status'] ?? '') === 'finalizado',
            'por_aluno' => $porAluno,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function indexarPorAluno(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['aluno_id']] = $row;
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,list<array<string,mixed>>>
     */
    private function indexarEspeciais(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['aluno_id']][] = $row;
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $especiais
     */
    private function especialGeral(array $especiais): ?string
    {
        foreach ($especiais as $e) {
            if (($e['materia_id'] ?? null) === null || (int) $e['materia_id'] === 0) {
                return (string) $e['tipo'];
            }
        }
        return null;
    }

    /**
     * @param list<array<string,mixed>> $especiais
     * @return array<int,string>
     */
    private function especialPorMateria(array $especiais): array
    {
        $out = [];
        foreach ($especiais as $e) {
            $mid = (int) ($e['materia_id'] ?? 0);
            if ($mid > 0) {
                $out[$mid] = (string) $e['tipo'];
            }
        }
        return $out;
    }

    private function cargaHoraria(int $materiaId): ?int
    {
        if ($materiaId <= 0 || !$this->model->tabelaExiste('plano_curso')) {
            return null;
        }
        try {
            $row = $this->db->fetch(
                'SELECT carga_horaria_prevista FROM plano_curso WHERE materia_id = :id LIMIT 1',
                ['id' => $materiaId]
            );
            return isset($row['carga_horaria_prevista']) && is_numeric($row['carga_horaria_prevista'])
                ? (int) $row['carga_horaria_prevista']
                : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
