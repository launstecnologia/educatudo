<?php
/**
 * Consulta de jornadas dos alunos para MCP / Assistente (somente leitura).
 */

require_once __DIR__ . '/ProvasAlunoConsultaService.php';
require_once __DIR__ . '/../Models/Education/JourneyBoletimLancamento.php';
require_once __DIR__ . '/../Core/JornadaStatusHelper.php';

class JornadasAlunoConsultaService
{
    private $db;
    private ProvasAlunoConsultaService $alunos;
    private JourneyBoletimLancamento $boletim;

    public function __construct(
        ?ProvasAlunoConsultaService $alunos = null,
        ?JourneyBoletimLancamento $boletim = null
    ) {
        $this->db = Database::getInstance();
        $this->alunos = $alunos ?? new ProvasAlunoConsultaService();
        $this->boletim = $boletim ?? new JourneyBoletimLancamento();
    }

    /** @return list<array{id:int,nome:string,ra:?string,turma_id:?int,turma_nome:?string}> */
    public function buscarAlunos(string $termo, int $limit = 20, ?string $turma = null): array
    {
        return $this->alunos->buscarAlunos($termo, $limit, $turma);
    }

    /** @return array{id:int,nome:string,ra:?string,turma_id:?int,turma_nome:?string}|null */
    public function obterAluno(int $alunoId): ?array
    {
        return $this->alunos->obterAluno($alunoId);
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    public function listarMaterias(int $limit = 200): array
    {
        $limit = max(1, min($limit, 500));
        $out = [];
        $vistos = [];

        try {
            if ($this->temTabela('jornadas_materias')) {
                $rows = $this->db->fetchAll(
                    "SELECT id, nome FROM jornadas_materias ORDER BY nome ASC LIMIT {$limit}"
                ) ?: [];
                foreach ($rows as $r) {
                    $id = (int) ($r['id'] ?? 0);
                    $nome = trim((string) ($r['nome'] ?? ''));
                    if ($id <= 0 || $nome === '') {
                        continue;
                    }
                    $out[] = ['id' => $id, 'nome' => $nome];
                    $vistos[mb_strtolower($nome)] = true;
                }
            }
        } catch (Throwable $e) {
            // segue com materias gerais
        }

        foreach ($this->alunos->listarMaterias($limit) as $m) {
            $key = mb_strtolower($m['nome']);
            if (isset($vistos[$key])) {
                continue;
            }
            $out[] = $m;
            $vistos[$key] = true;
        }
        return array_slice($out, 0, $limit);
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,aluno?:array,candidatos?:list,jornadas?:list,total?:int,filtros_aplicados?:array,aviso?:string,error?:string}
     */
    public function listarJornadasAluno(array $filtros): array
    {
        $resolvido = $this->resolverAluno($filtros);
        if (isset($resolvido['error']) || isset($resolvido['candidatos'])) {
            return $resolvido;
        }
        /** @var array{id:int,nome:string,turma_id:?int,turma_nome:?string,ra:?string} $aluno */
        $aluno = $resolvido['aluno'];
        $alunoId = (int) $aluno['id'];
        $turmaId = (int) ($aluno['turma_id'] ?? 0);
        if ($turmaId <= 0) {
            return ['ok' => false, 'error' => 'Aluno sem turma vinculada.'];
        }

        $dataInicio = $this->normalizarData($filtros['data_inicio'] ?? null);
        $dataFim = $this->normalizarData($filtros['data_fim'] ?? null);
        $bimestre = (int) ($filtros['bimestre'] ?? 0);
        if ($bimestre < 1 || $bimestre > 4) {
            $bimestre = 0;
        }
        $materiaNome = trim((string) ($filtros['materia_nome'] ?? ''));
        $materiaId = (int) ($filtros['materia_id'] ?? 0);
        $statusAluno = strtolower(trim((string) ($filtros['status_aluno'] ?? '')));
        $limite = max(1, min((int) ($filtros['limite'] ?? 80), 200));

        $candidatas = $this->boletim->listarJornadasCandidatas([$turmaId], $dataInicio, $dataFim);
        $jornadasFiltradas = [];
        foreach ($candidatas as $j) {
            if (!$this->alunoElegivel($j, $alunoId, $turmaId)) {
                continue;
            }
            if ($bimestre > 0 && (int) ($j['bimestre'] ?? 0) !== $bimestre) {
                continue;
            }
            if ($materiaId > 0 && (int) ($j['materia_id'] ?? 0) !== $materiaId) {
                continue;
            }
            if ($materiaNome !== '' && !$this->materiaCasa($j, $materiaNome)) {
                continue;
            }
            $jornadasFiltradas[] = $j;
            if (count($jornadasFiltradas) >= $limite) {
                break;
            }
        }

        $ids = array_values(array_filter(array_map(static function ($j) {
            return (int) ($j['id'] ?? 0);
        }, $jornadasFiltradas)));

        $mapaMaterias = $this->mapaNomesMaterias($jornadasFiltradas);
        $totaisModulos = $this->totaisModulos($ids);
        $concluidas = $this->jornadasConcluidasExplicitas($alunoId, $ids);
        $modulosOk = $this->modulosConcluidos($alunoId, $ids);
        $statsEx = $this->statsExercicios($alunoId, $ids);
        $ultimaAtiv = $this->ultimaAtividade($alunoId, $ids);

        $lista = [];
        foreach ($jornadasFiltradas as $j) {
            $jid = (int) ($j['id'] ?? 0);
            if ($jid <= 0) {
                continue;
            }
            $estrutura = json_decode((string) ($j['estrutura'] ?? ''), true) ?: [];
            $statusCal = JornadaStatusHelper::calcularStatus($estrutura);
            $pct = $this->percentual($alunoId, $jid, $totaisModulos, $concluidas, $modulosOk);
            $stAluno = $this->statusAluno($pct, $statsEx[$jid] ?? null, $ultimaAtiv[$jid] ?? null);
            if ($statusAluno !== '' && $statusAluno !== 'todos'
                && !$this->statusAlunoCasa($stAluno, $statusAluno)) {
                continue;
            }

            $ex = $statsEx[$jid] ?? ['acertos' => 0, 'erros' => 0, 'total_respostas' => 0];
            $lista[] = [
                'jornada_id' => $jid,
                'titulo' => trim((string) ($j['titulo'] ?? '')),
                'materia' => [
                    'id' => isset($j['materia_id']) ? (int) $j['materia_id'] : null,
                    'nome' => $mapaMaterias[(int) ($j['materia_id'] ?? 0)] ?? null,
                ],
                'turma' => [
                    'id' => $turmaId,
                    'nome' => $aluno['turma_nome'] ?? null,
                ],
                'bimestre' => isset($j['bimestre']) ? (int) $j['bimestre'] : null,
                'ano_letivo' => isset($j['ano_letivo']) ? (int) $j['ano_letivo'] : null,
                'periodo' => [
                    'data_inicio' => $this->formatarData($estrutura['data_inicio'] ?? null),
                    'data_fim' => $this->formatarData($estrutura['data_fim'] ?? null),
                ],
                'status_calendario' => $statusCal,
                'aluno' => [
                    'status' => $stAluno,
                    'percentual_conclusao' => $pct,
                    'concluiu' => $pct >= 100.0 || !empty($concluidas[$alunoId . ':' . $jid]),
                    'ultima_atividade' => $ultimaAtiv[$jid] ?? null,
                    'acertos' => (int) ($ex['acertos'] ?? 0),
                    'erros' => (int) ($ex['erros'] ?? 0),
                    'pendentes' => (int) ($ex['pendentes'] ?? 0),
                    'total_respostas' => (int) ($ex['total_respostas'] ?? 0),
                ],
            ];
        }

        return [
            'ok' => true,
            'aluno' => $aluno,
            'jornadas' => $lista,
            'total' => count($lista),
            'filtros_aplicados' => [
                'aluno_id' => $alunoId,
                'turma_id' => $turmaId,
                'materia_id' => $materiaId > 0 ? $materiaId : null,
                'materia_nome' => $materiaNome !== '' ? $materiaNome : null,
                'bimestre' => $bimestre > 0 ? $bimestre : null,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'status_aluno' => $statusAluno !== '' ? $statusAluno : null,
                'limite' => $limite,
            ],
        ];
    }

    /**
     * @return array{ok:bool,aluno?:array,detalhe?:array,error?:string}
     */
    /**
     * @param array{somente_erros?:bool|int|string} $opcoes
     * @return array{ok:bool,aluno?:array,detalhe?:array,error?:string}
     */
    public function detalharJornadaAluno(int $alunoId, int $jornadaId, array $opcoes = []): array
    {
        $aluno = $this->obterAluno($alunoId);
        if ($aluno === null) {
            return ['ok' => false, 'error' => 'Aluno não encontrado.'];
        }
        if ($jornadaId <= 0) {
            return ['ok' => false, 'error' => 'jornada_id inválido.'];
        }

        $lista = $this->listarJornadasAluno([
            'aluno_id' => $alunoId,
            'limite' => 200,
        ]);
        $base = null;
        foreach ($lista['jornadas'] ?? [] as $j) {
            if ((int) ($j['jornada_id'] ?? 0) === $jornadaId) {
                $base = $j;
                break;
            }
        }
        if ($base === null) {
            // tenta mesmo assim se a jornada existe e cobre a turma
            $row = $this->db->fetch(
                'SELECT j.id, j.titulo, j.turma_id, j.estrutura, j.created_at, j.materia_id, j.ano_letivo, j.bimestre
                 FROM jornadas j WHERE j.id = :id AND (j.ativo = 1 OR j.ativo IS NULL) LIMIT 1',
                ['id' => $jornadaId]
            );
            if (!$row || !$this->alunoElegivel($row, $alunoId, (int) ($aluno['turma_id'] ?? 0))) {
                return ['ok' => false, 'error' => 'Jornada não encontrada para este aluno.'];
            }
            $base = ['jornada_id' => $jornadaId, 'titulo' => $row['titulo'] ?? ''];
        }

        $somenteErros = $this->flagVerdadeira($opcoes['somente_erros'] ?? false);
        $exercicios = $this->montarExerciciosDetalhe($jornadaId, $alunoId, $somenteErros);

        $base['exercicios'] = $exercicios;
        $base['total_exercicios'] = count($exercicios);
        $base['exercicios_respondidos'] = count(array_filter($exercicios, static function ($e) {
            return !empty($e['respondeu']);
        }));
        $base['somente_erros'] = $somenteErros;

        return ['ok' => true, 'aluno' => $aluno, 'detalhe' => $base];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function montarExerciciosDetalhe(int $jornadaId, int $alunoId, bool $somenteErros): array
    {
        $exercicios = [];
        try {
            $rows = $this->db->fetchAll(
                "SELECT me.id, me.titulo, me.tipo, me.enunciado, me.questoes_json,
                        me.resposta_correta, me.gabarito, me.pontuacao AS pontuacao_max,
                        m.titulo AS modulo_titulo, m.ordem AS modulo_ordem,
                        jpa.pontuacao AS pontuacao_aluno, jpa.status AS status_aluno,
                        jpa.data_conclusao, jpa.resposta AS resposta_aluno
                 FROM jornadas_modulos m
                 JOIN jornadas_modulos_exercicios me ON me.modulo_id = m.id
                 LEFT JOIN jornadas_progresso_alunos jpa
                   ON jpa.exercicio_modulo_id = me.id
                  AND jpa.aluno_id = :aluno_id
                  AND jpa.jornada_id = m.jornada_id
                  AND jpa.atividade_tipo = 'exercicio_modulo'
                 WHERE m.jornada_id = :jornada_id
                   AND (m.tipo_modulo = 'exercicios' OR m.tipo_modulo = 'exercicio')
                 ORDER BY m.ordem ASC, me.ordem ASC
                 LIMIT 300",
                ['aluno_id' => $alunoId, 'jornada_id' => $jornadaId]
            ) ?: [];

            if ($rows === []) {
                $rows = $this->db->fetchAll(
                    "SELECT je.id, je.titulo, je.tipo, je.descricao AS enunciado, je.questoes_json,
                            NULL AS resposta_correta, NULL AS gabarito, 1.0 AS pontuacao_max,
                            'Exercícios da Jornada' AS modulo_titulo, 0 AS modulo_ordem,
                            jpa.pontuacao AS pontuacao_aluno, jpa.status AS status_aluno,
                            jpa.data_conclusao, jpa.resposta AS resposta_aluno
                     FROM jornadas_exercicios je
                     LEFT JOIN jornadas_progresso_alunos jpa
                       ON jpa.exercicio_id = je.id
                      AND jpa.aluno_id = :aluno_id
                      AND jpa.jornada_id = je.jornada_id
                      AND jpa.atividade_tipo IN ('exercicio', 'exercicio_modulo')
                     WHERE je.jornada_id = :jornada_id
                     ORDER BY je.id ASC
                     LIMIT 300",
                    ['aluno_id' => $alunoId, 'jornada_id' => $jornadaId]
                ) ?: [];
            }

            $numero = 0;
            foreach ($rows as $r) {
                $numero++;
                $pont = $r['pontuacao_aluno'];
                $rawResp = $r['resposta_aluno'] ?? null;
                $respondeu = isset($rawResp) && $rawResp !== null && $rawResp !== '';
                $resultado = JornadaExercicioAvaliacao::classificar(
                    $r['tipo'] ?? '',
                    $pont,
                    is_string($rawResp) ? $rawResp : (string) ($rawResp ?? ''),
                    $respondeu
                );
                $acertou = $resultado === JornadaExercicioAvaliacao::STATUS_ACERTO;
                $aguardandoCorrecao = $resultado === JornadaExercicioAvaliacao::STATUS_PENDENTE;

                $gabaritoAlt = $this->extrairAlternativasJornada(
                    $r['questoes_json'] ?? null,
                    is_string($rawResp) ? $rawResp : (string) ($rawResp ?? '')
                );
                if (!$aguardandoCorrecao && $gabaritoAlt['acertou_gabarito'] !== null) {
                    $acertou = $respondeu && $gabaritoAlt['acertou_gabarito'];
                }

                $resultadoFinal = $aguardandoCorrecao
                    ? JornadaExercicioAvaliacao::STATUS_PENDENTE
                    : ($acertou ? JornadaExercicioAvaliacao::STATUS_ACERTO : JornadaExercicioAvaliacao::STATUS_ERRO);
                if ($somenteErros && $resultadoFinal !== JornadaExercicioAvaliacao::STATUS_ERRO) {
                    continue;
                }

                $exercicios[] = [
                    'numero' => $numero,
                    'exercicio_id' => (int) ($r['id'] ?? 0),
                    'titulo' => trim((string) ($r['titulo'] ?? '')),
                    'tipo' => trim((string) ($r['tipo'] ?? '')),
                    'modulo' => trim((string) ($r['modulo_titulo'] ?? '')),
                    'enunciado' => $this->textoCurto((string) ($r['enunciado'] ?? ''), 500),
                    'respondeu' => $respondeu,
                    'acertou' => $aguardandoCorrecao ? null : $acertou,
                    'aguardando_correcao' => $aguardandoCorrecao,
                    'pontuacao' => $pont !== null && $pont !== '' ? (float) $pont : null,
                    'status' => isset($r['status_aluno']) ? (string) $r['status_aluno'] : null,
                    'data_conclusao' => isset($r['data_conclusao']) ? (string) $r['data_conclusao'] : null,
                    'alternativa_marcada' => $gabaritoAlt['marcada'],
                    'alternativa_correta' => $gabaritoAlt['correta'],
                    'resposta_aluno' => $gabaritoAlt['resposta_texto']
                        ?? ($respondeu ? $this->textoCurto((string) $rawResp, 200) : null),
                    'resposta_correta' => $this->textoCurto((string) ($r['resposta_correta'] ?? $r['gabarito'] ?? ''), 200) ?: null,
                ];
            }
        } catch (Throwable $e) {
            error_log('JornadasAlunoConsultaService detalhar: ' . $e->getMessage());
        }

        return $exercicios;
    }

    /**
     * @return array{marcada:?array,correta:?array,resposta_texto:?string,acertou_gabarito:?bool}
     */
    private function extrairAlternativasJornada($questoesJson, string $respostaEncoded): array
    {
        $vazio = [
            'marcada' => null,
            'correta' => null,
            'resposta_texto' => null,
            'acertou_gabarito' => null,
        ];
        $questoes = is_string($questoesJson) ? json_decode($questoesJson, true) : $questoesJson;
        if (is_string($questoes)) {
            $questoes = json_decode($questoes, true);
        }
        if (!is_array($questoes)) {
            $decoded = json_decode($respostaEncoded, true);
            if (is_array($decoded) && array_key_exists('resposta', $decoded)) {
                $vazio['resposta_texto'] = $this->textoCurto((string) $decoded['resposta'], 200);
            } elseif ($respostaEncoded !== '') {
                $vazio['resposta_texto'] = $this->textoCurto($respostaEncoded, 200);
            }
            return $vazio;
        }

        $opcoes = null;
        if (isset($questoes['opcoes']) && is_array($questoes['opcoes'])) {
            $opcoes = $questoes['opcoes'];
        } elseif (isset($questoes[0]) && is_array($questoes[0])) {
            $opcoes = $questoes;
        }
        if (!$opcoes) {
            return $vazio;
        }

        $decodedResp = json_decode($respostaEncoded, true);
        $resposta = '';
        if (is_array($decodedResp) && array_key_exists('resposta', $decodedResp)) {
            $resposta = (string) $decodedResp['resposta'];
        } elseif ($respostaEncoded !== '') {
            $resposta = $respostaEncoded;
        }
        $respostaNorm = strtoupper(trim($resposta));
        $vazio['resposta_texto'] = $respostaNorm !== '' ? $respostaNorm : null;

        $idx = 0;
        $acertou = null;
        foreach ($opcoes as $opcao) {
            if (!is_array($opcao)) {
                continue;
            }
            $letra = strtoupper(trim((string) ($opcao['letra'] ?? '')));
            if ($letra === '') {
                $letra = chr(65 + $idx);
            }
            $idx++;
            $item = [
                'letra' => $letra,
                'texto' => $this->textoCurto((string) ($opcao['texto'] ?? $opcao['enunciado'] ?? ''), 200),
                'eh_correta' => !empty($opcao['correta']),
            ];
            if ($item['eh_correta']) {
                $vazio['correta'] = $item;
            }
            if ($respostaNorm !== '' && $letra === $respostaNorm) {
                $vazio['marcada'] = $item;
                $acertou = $item['eh_correta'];
            }
        }
        if ($respostaNorm !== '' && $acertou === null) {
            $acertou = false;
        }
        $vazio['acertou_gabarito'] = $acertou;

        return $vazio;
    }

    private function flagVerdadeira($valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }
        if (is_int($valor) || is_float($valor)) {
            return (int) $valor === 1;
        }
        $s = strtolower(trim((string) $valor));
        return in_array($s, ['1', 'true', 'sim', 'yes', 'on'], true);
    }

    private function textoCurto(string $htmlOuTexto, int $max = 400): string
    {
        $t = html_entity_decode(strip_tags($htmlOuTexto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('/\s+/u', ' ', trim($t)) ?? trim($t);
        if ($t === '') {
            return '';
        }
        if (mb_strlen($t) <= $max) {
            return $t;
        }
        return mb_substr($t, 0, $max - 1) . '…';
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,aluno?:array,total_jornadas?:int,concluidas?:int,em_andamento?:int,nao_iniciadas?:int,percentual_medio?:float,por_materia?:list,por_bimestre?:list,error?:string}
     */
    public function resumoJornadasAluno(array $filtros): array
    {
        $filtros['limite'] = min(200, (int) ($filtros['limite'] ?? 200));
        $lista = $this->listarJornadasAluno($filtros);
        if (empty($lista['ok'])) {
            return $lista;
        }
        if (!empty($lista['candidatos'])) {
            return $lista;
        }

        $concluidas = 0;
        $emAndamento = 0;
        $naoIniciadas = 0;
        $somaPct = 0.0;
        $porMateria = [];
        $porBimestre = [];

        foreach ($lista['jornadas'] as $j) {
            $st = (string) ($j['aluno']['status'] ?? '');
            $pct = (float) ($j['aluno']['percentual_conclusao'] ?? 0);
            $somaPct += $pct;
            if ($st === 'concluida') {
                $concluidas++;
            } elseif ($st === 'em andamento') {
                $emAndamento++;
            } else {
                $naoIniciadas++;
            }

            $mat = (string) ($j['materia']['nome'] ?? 'Sem matéria');
            if (!isset($porMateria[$mat])) {
                $porMateria[$mat] = ['materia' => $mat, 'quantidade' => 0, 'concluidas' => 0, 'media_pct' => 0.0, 'soma_pct' => 0.0];
            }
            $porMateria[$mat]['quantidade']++;
            $porMateria[$mat]['soma_pct'] += $pct;
            if ($st === 'concluida') {
                $porMateria[$mat]['concluidas']++;
            }

            $bim = $j['bimestre'] ?? null;
            $bimKey = ($bim !== null && (int) $bim > 0) ? ((int) $bim) . 'º bimestre' : 'Sem bimestre';
            if (!isset($porBimestre[$bimKey])) {
                $porBimestre[$bimKey] = ['bimestre' => $bimKey, 'quantidade' => 0, 'concluidas' => 0, 'media_pct' => 0.0, 'soma_pct' => 0.0];
            }
            $porBimestre[$bimKey]['quantidade']++;
            $porBimestre[$bimKey]['soma_pct'] += $pct;
            if ($st === 'concluida') {
                $porBimestre[$bimKey]['concluidas']++;
            }
        }

        $total = count($lista['jornadas']);
        foreach ($porMateria as &$m) {
            $m['media_pct'] = $m['quantidade'] > 0 ? round($m['soma_pct'] / $m['quantidade'], 1) : 0.0;
            unset($m['soma_pct']);
        }
        unset($m);
        foreach ($porBimestre as &$b) {
            $b['media_pct'] = $b['quantidade'] > 0 ? round($b['soma_pct'] / $b['quantidade'], 1) : 0.0;
            unset($b['soma_pct']);
        }
        unset($b);

        return [
            'ok' => true,
            'aluno' => $lista['aluno'],
            'total_jornadas' => $total,
            'concluidas' => $concluidas,
            'em_andamento' => $emAndamento,
            'nao_iniciadas' => $naoIniciadas,
            'percentual_medio' => $total > 0 ? round($somaPct / $total, 1) : 0.0,
            'por_materia' => array_values($porMateria),
            'por_bimestre' => array_values($porBimestre),
            'filtros_aplicados' => $lista['filtros_aplicados'],
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,aluno?:array,candidatos?:list,aviso?:string,error?:string}
     */
    private function resolverAluno(array $filtros): array
    {
        $alunoId = (int) ($filtros['aluno_id'] ?? 0);
        $alunoNome = trim((string) ($filtros['aluno_nome'] ?? $filtros['nome'] ?? ''));
        $turmaFiltro = trim((string) ($filtros['turma_nome'] ?? $filtros['turma'] ?? ''));

        if ($alunoId <= 0 && $alunoNome !== '') {
            $cands = $this->buscarAlunos($alunoNome, 15, $turmaFiltro !== '' ? $turmaFiltro : null);
            if ($cands === []) {
                if ($turmaFiltro !== '') {
                    $semTurma = $this->buscarAlunos($alunoNome, 10, null);
                    if ($semTurma !== []) {
                        return [
                            'ok' => true,
                            'aviso' => 'Nenhum aluno com esse nome na turma informada. Candidatos em outras turmas:',
                            'candidatos' => $semTurma,
                        ];
                    }
                }
                return ['ok' => false, 'error' => 'Nenhum aluno encontrado com esse nome.'];
            }
            if (count($cands) > 1) {
                $exatos = array_values(array_filter($cands, static function ($c) use ($alunoNome) {
                    return mb_strtolower($c['nome']) === mb_strtolower($alunoNome);
                }));
                if (count($exatos) === 1) {
                    $alunoId = (int) $exatos[0]['id'];
                } else {
                    return [
                        'ok' => true,
                        'aviso' => 'Há mais de um aluno. Informe aluno_id.',
                        'candidatos' => $cands,
                    ];
                }
            } else {
                $alunoId = (int) $cands[0]['id'];
            }
        }

        $aluno = $this->obterAluno($alunoId);
        if ($aluno === null) {
            return ['ok' => false, 'error' => 'Aluno não encontrado.'];
        }
        return ['ok' => true, 'aluno' => $aluno];
    }

    /** @param array<string,mixed> $j */
    private function alunoElegivel(array $j, int $alunoId, int $turmaId): bool
    {
        if ($turmaId <= 0 || !JourneyBoletimLancamento::jornadaCobreTurma($j, $turmaId)) {
            return false;
        }
        $e = json_decode((string) ($j['estrutura'] ?? ''), true) ?: [];
        $tipo = strtolower(trim((string) ($e['tipo_selecao_alunos'] ?? 'todos')));
        if ($tipo === 'selecionados') {
            $ids = array_map('intval', (array) ($e['alunos_selecionados'] ?? []));
            return in_array($alunoId, $ids, true);
        }
        return true;
    }

    /** @param array<string,mixed> $j */
    private function materiaCasa(array $j, string $materiaNome): bool
    {
        $mid = (int) ($j['materia_id'] ?? 0);
        $mapa = $this->mapaNomesMaterias([$j]);
        $nome = mb_strtolower(trim((string) ($mapa[$mid] ?? '')));
        $needle = mb_strtolower(trim($materiaNome));
        if ($nome === '' || $needle === '') {
            return false;
        }
        return $nome === $needle || str_contains($nome, $needle) || str_contains($needle, $nome);
    }

    /**
     * @param list<array<string,mixed>> $jornadas
     * @return array<int,string>
     */
    private function mapaNomesMaterias(array $jornadas): array
    {
        $ids = [];
        foreach ($jornadas as $j) {
            $id = (int) ($j['materia_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return [];
        }
        $idList = array_keys($ids);
        $ph = implode(',', array_fill(0, count($idList), '?'));
        $mapa = [];
        try {
            if ($this->temTabela('jornadas_materias')) {
                $rows = $this->db->fetchAll(
                    "SELECT id, nome FROM jornadas_materias WHERE id IN ($ph)",
                    $idList
                ) ?: [];
                foreach ($rows as $r) {
                    $mapa[(int) $r['id']] = trim((string) ($r['nome'] ?? ''));
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
        $faltando = array_values(array_filter($idList, static function ($id) use ($mapa) {
            return empty($mapa[$id]);
        }));
        if ($faltando !== [] && $this->temTabela('materias')) {
            $ph2 = implode(',', array_fill(0, count($faltando), '?'));
            $rows = $this->db->fetchAll(
                "SELECT id, nome FROM materias WHERE id IN ($ph2)",
                $faltando
            ) ?: [];
            foreach ($rows as $r) {
                $mapa[(int) $r['id']] = trim((string) ($r['nome'] ?? ''));
            }
        }
        return $mapa;
    }

    /** @param list<int> $jornadaIds @return array<int,int> */
    private function totaisModulos(array $jornadaIds): array
    {
        if ($jornadaIds === [] || !$this->temTabela('jornadas_modulos')) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($jornadaIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT jornada_id, COUNT(*) AS total
             FROM jornadas_modulos
             WHERE jornada_id IN ($ph) AND (status = 'ativo' OR status IS NULL)
             GROUP BY jornada_id",
            $jornadaIds
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['jornada_id']] = (int) ($r['total'] ?? 0);
        }
        return $out;
    }

    /** @param list<int> $jornadaIds @return array<string,bool> */
    private function jornadasConcluidasExplicitas(int $alunoId, array $jornadaIds): array
    {
        if ($jornadaIds === [] || !$this->temTabela('jornadas_progresso_alunos')) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($jornadaIds), '?'));
        $params = array_merge([$alunoId], $jornadaIds);
        $rows = $this->db->fetchAll(
            "SELECT jornada_id
             FROM jornadas_progresso_alunos
             WHERE aluno_id = ?
               AND jornada_id IN ($ph)
               AND status = 'concluido'
               AND (atividade_tipo IS NULL OR atividade_tipo = 'jornada_concluida')
               AND modulo_id IS NULL AND aula_id IS NULL
               AND exercicio_id IS NULL AND exercicio_modulo_id IS NULL",
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[$alunoId . ':' . (int) $r['jornada_id']] = true;
        }
        return $out;
    }

    /** @param list<int> $jornadaIds @return array<string,int> */
    private function modulosConcluidos(int $alunoId, array $jornadaIds): array
    {
        if ($jornadaIds === [] || !$this->temTabela('jornadas_progresso_alunos')) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($jornadaIds), '?'));
        $params = array_merge([$alunoId], $jornadaIds);
        $rows = $this->db->fetchAll(
            "SELECT jornada_id, COUNT(DISTINCT modulo_id) AS total
             FROM jornadas_progresso_alunos
             WHERE aluno_id = ?
               AND jornada_id IN ($ph)
               AND atividade_tipo = 'modulo'
               AND status = 'concluido'
               AND modulo_id IS NOT NULL
             GROUP BY jornada_id",
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[$alunoId . ':' . (int) $r['jornada_id']] = (int) ($r['total'] ?? 0);
        }
        return $out;
    }

    /**
     * @param list<int> $jornadaIds
     * @return array<int,array{acertos:int,erros:int,pendentes:int,total_respostas:int}>
     */
    private function statsExercicios(int $alunoId, array $jornadaIds): array
    {
        if ($jornadaIds === [] || !$this->temTabela('jornadas_progresso_alunos')) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($jornadaIds), '?'));
        $params = array_merge([$alunoId], $jornadaIds);
        $sqlPendente = JornadaExercicioAvaliacao::sqlPendente('COALESCE(me.tipo, je.tipo)', 'jpa.pontuacao', 'jpa.resposta');
        $sqlAcerto = JornadaExercicioAvaliacao::sqlCaseAcerto('COALESCE(me.tipo, je.tipo)', 'jpa.pontuacao', 'jpa.resposta');
        $sqlErro = JornadaExercicioAvaliacao::sqlCaseErro('COALESCE(me.tipo, je.tipo)', 'jpa.pontuacao', 'jpa.resposta');
        $rows = $this->db->fetchAll(
            "SELECT jpa.jornada_id,
                    SUM({$sqlAcerto}) AS acertos,
                    SUM({$sqlErro}) AS erros,
                    SUM(CASE WHEN {$sqlPendente} THEN 1 ELSE 0 END) AS pendentes,
                    COUNT(*) AS total_respostas
             FROM jornadas_progresso_alunos jpa
             LEFT JOIN jornadas_modulos_exercicios me ON me.id = jpa.exercicio_modulo_id
             LEFT JOIN jornadas_exercicios je ON je.id = jpa.exercicio_id
             WHERE jpa.aluno_id = ?
               AND jpa.jornada_id IN ($ph)
               AND jpa.atividade_tipo IN ('exercicio_modulo', 'exercicio')
               AND jpa.resposta IS NOT NULL
             GROUP BY jpa.jornada_id",
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['jornada_id']] = [
                'acertos' => (int) ($r['acertos'] ?? 0),
                'erros' => (int) ($r['erros'] ?? 0),
                'pendentes' => (int) ($r['pendentes'] ?? 0),
                'total_respostas' => (int) ($r['total_respostas'] ?? 0),
            ];
        }
        return $out;
    }

    /** @param list<int> $jornadaIds @return array<int,string> */
    private function ultimaAtividade(int $alunoId, array $jornadaIds): array
    {
        if ($jornadaIds === [] || !$this->temTabela('jornadas_progresso_alunos')) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($jornadaIds), '?'));
        $params = array_merge([$alunoId], $jornadaIds);
        $rows = $this->db->fetchAll(
            "SELECT jornada_id, MAX(COALESCE(data_conclusao, data_inicio, updated_at, created_at)) AS ultima
             FROM jornadas_progresso_alunos
             WHERE aluno_id = ? AND jornada_id IN ($ph)
             GROUP BY jornada_id",
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['jornada_id']] = isset($r['ultima']) ? (string) $r['ultima'] : null;
        }
        return $out;
    }

    /**
     * @param array<int,int> $totaisModulos
     * @param array<string,bool> $concluidas
     * @param array<string,int> $modulosOk
     */
    private function percentual(
        int $alunoId,
        int $jornadaId,
        array $totaisModulos,
        array $concluidas,
        array $modulosOk
    ): float {
        return $this->boletim->percentualConclusaoAlunoJornada(
            $alunoId,
            $jornadaId,
            $totaisModulos,
            $concluidas,
            $modulosOk
        );
    }

    /** @param array{acertos?:int,erros?:int,total_respostas?:int}|null $ex */
    private function statusAluno(float $pct, ?array $ex, ?string $ultima): string
    {
        if ($pct >= 100.0) {
            return 'concluida';
        }
        $respostas = (int) ($ex['total_respostas'] ?? 0);
        if ($respostas > 0 || ($ultima !== null && $ultima !== '') || $pct > 0) {
            return 'em andamento';
        }
        return 'nao iniciada';
    }

    private function statusAlunoCasa(string $atual, string $filtro): bool
    {
        $a = preg_replace('/[\s_]+/', '', mb_strtolower($atual)) ?? '';
        $f = preg_replace('/[\s_]+/', '', mb_strtolower($filtro)) ?? '';
        if ($a === $f) {
            return true;
        }
        $mapa = [
            'concluido' => 'concluida',
            'emandamento' => 'emandamento',
            'naoiniciado' => 'naoiniciada',
            'pendente' => 'naoiniciada',
        ];
        $a2 = $mapa[$a] ?? $a;
        $f2 = $mapa[$f] ?? $f;
        return $a2 === $f2;
    }

    private function normalizarData($raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
            return substr($s, 0, 10);
        }
        return null;
    }

    private function formatarData($raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = substr((string) $raw, 0, 10);
        if ($s === '0000-00-00' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return null;
        }
        return $s;
    }

    private function temTabela(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        try {
            if (method_exists($this->db, 'tableExists')) {
                $cache[$table] = (bool) $this->db->tableExists($table);
            } else {
                $row = $this->db->fetch(
                    'SELECT 1 AS ok FROM information_schema.tables
                     WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1',
                    ['t' => $table]
                );
                $cache[$table] = !empty($row);
            }
        } catch (Throwable $e) {
            $cache[$table] = false;
        }
        return $cache[$table];
    }
}
