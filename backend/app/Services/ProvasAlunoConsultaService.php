<?php
/**
 * Consulta de provas dos alunos para MCP / API admin (somente leitura).
 */

require_once __DIR__ . '/../Models/System/BoletimConfig.php';

class ProvasAlunoConsultaService
{
    private $db;
    private BoletimConfig $boletimConfig;

    public function __construct(?BoletimConfig $boletimConfig = null)
    {
        $this->db = Database::getInstance();
        $this->boletimConfig = $boletimConfig ?? new BoletimConfig();
    }

    /**
     * @return list<array{id:int,nome:string,ra:?string,turma_id:?int,turma_nome:?string}>
     */
    public function buscarAlunos(string $termo, int $limit = 20, ?string $turma = null): array
    {
        $termo = trim($termo);
        if ($termo === '' || mb_strlen($termo) < 2) {
            return [];
        }
        $limit = max(1, min($limit, 50));
        $tokensNome = $this->tokensBuscaNome($termo);
        if ($tokensNome === []) {
            // Fallback: termo inteiro (RA / código curto)
            $tokensNome = [mb_strtolower($termo)];
        }

        $where = ['a.ativo = 1'];
        $params = [];
        $i = 0;
        foreach ($tokensNome as $tok) {
            $i++;
            $where[] = "(a.nome LIKE :n{$i} OR a.ra LIKE :r{$i} OR a.codigo_aluno LIKE :c{$i})";
            $like = '%' . $tok . '%';
            $params['n' . $i] = $like;
            $params['r' . $i] = $like;
            $params['c' . $i] = $like;
        }
        $whereSql = implode(' AND ', $where);

        // Folga para filtrar turma em PHP (nomes variam: 2ªB, 2ª B do Ensino Médio…).
        $temTurma = $turma !== null && trim($turma) !== '';
        $limiteSql = $temTurma ? min(120, max(40, $limit * 6)) : min(80, max($limit, count($tokensNome) * 25));

        $rows = $this->db->fetchAll(
            "SELECT a.id, a.nome, a.ra, a.turma_id, t.nome AS turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE {$whereSql}
             ORDER BY a.nome ASC
             LIMIT {$limiteSql}",
            $params
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $item = [
                'id' => $id,
                'nome' => trim((string) ($r['nome'] ?? '')),
                'ra' => isset($r['ra']) && $r['ra'] !== '' ? trim((string) $r['ra']) : null,
                'turma_id' => isset($r['turma_id']) ? (int) $r['turma_id'] : null,
                'turma_nome' => isset($r['turma_nome']) ? trim((string) $r['turma_nome']) : null,
            ];
            if ($temTurma && !$this->turmaCasa($item['turma_nome'], (string) $turma)) {
                continue;
            }
            $out[] = $item;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * @return array{id:int,nome:string,ra:?string,turma_id:?int,turma_nome:?string}|null
     */
    public function obterAluno(int $alunoId): ?array
    {
        if ($alunoId <= 0) {
            return null;
        }
        $r = $this->db->fetch(
            "SELECT a.id, a.nome, a.ra, a.turma_id, t.nome AS turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = :id
             LIMIT 1",
            ['id' => $alunoId]
        );
        if (!$r) {
            return null;
        }
        return [
            'id' => (int) $r['id'],
            'nome' => trim((string) ($r['nome'] ?? '')),
            'ra' => isset($r['ra']) && $r['ra'] !== '' ? trim((string) $r['ra']) : null,
            'turma_id' => isset($r['turma_id']) ? (int) $r['turma_id'] : null,
            'turma_nome' => isset($r['turma_nome']) ? trim((string) $r['turma_nome']) : null,
        ];
    }

    /** Exposto para outros services (turma "2 Ano B" ≈ "2ª B do Ensino Médio"). */
    public function turmaCorresponde(?string $turmaNome, string $filtro): bool
    {
        return $this->turmaCasa($turmaNome, $filtro);
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    public function buscarTurmasPorNome(string $filtro, int $limit = 20): array
    {
        $filtro = trim($filtro);
        if ($filtro === '') {
            return [];
        }
        $limit = max(1, min(40, $limit));
        $rows = $this->db->fetchAll(
            'SELECT id, nome FROM turmas ORDER BY nome ASC LIMIT 400'
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $nome = trim((string) ($r['nome'] ?? ''));
            if ($nome === '' || !$this->turmaCasa($nome, $filtro)) {
                continue;
            }
            $out[] = ['id' => (int) $r['id'], 'nome' => $nome];
            if (count($out) >= $limit) {
                break;
            }
        }
        // Fallback LIKE se normalização não pegou nada
        if ($out === []) {
            $like = '%' . $filtro . '%';
            $rows = $this->db->fetchAll(
                'SELECT id, nome FROM turmas WHERE nome LIKE :q ORDER BY nome ASC LIMIT ' . $limit,
                ['q' => $like]
            ) ?: [];
            foreach ($rows as $r) {
                $out[] = ['id' => (int) $r['id'], 'nome' => trim((string) ($r['nome'] ?? ''))];
            }
        }
        return $out;
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    public function listarMaterias(int $limit = 200): array
    {
        $out = [];
        foreach ($this->boletimConfig->getAvailableSubjects($limit) as $m) {
            $id = (int) ($m['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nome' => trim((string) ($m['nome'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * @return list<array{id:int,nome:string,descricao:?string}>
     */
    public function listarTiposAvaliacao(): array
    {
        if (!class_exists('ExamEvaluationType', false)) {
            require_once __DIR__ . '/../Models/Exams/ExamEvaluationType.php';
        }
        $rows = (new ExamEvaluationType())->getAllActive();
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nome' => trim((string) ($row['nome'] ?? '')),
                'descricao' => isset($row['descricao']) ? trim((string) $row['descricao']) : null,
            ];
        }
        return $out;
    }

    /**
     * Resolve aluno por id ou nome (se vários, retorna candidatos sem listar provas).
     *
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,aluno?:array,candidatos?:list,provas?:list,total?:int,filtros_aplicados?:array,aviso?:string,error?:string}
     */
    public function listarProvasAluno(array $filtros): array
    {
        $alunoId = (int) ($filtros['aluno_id'] ?? 0);
        $alunoNome = trim((string) ($filtros['aluno_nome'] ?? $filtros['nome'] ?? ''));

        if ($alunoId <= 0 && $alunoNome !== '') {
            $turmaFiltro = trim((string) ($filtros['turma_nome'] ?? $filtros['turma'] ?? ''));
            $cands = $this->buscarAlunos($alunoNome, 15, $turmaFiltro !== '' ? $turmaFiltro : null);
            if ($cands === []) {
                // Sem match com turma: tenta só pelo nome para avisar.
                if ($turmaFiltro !== '') {
                    $semTurma = $this->buscarAlunos($alunoNome, 10, null);
                    if ($semTurma !== []) {
                        return [
                            'ok' => true,
                            'aviso' => 'Nenhum aluno com esse nome na turma informada. Candidatos em outras turmas:',
                            'candidatos' => $semTurma,
                            'provas' => [],
                            'total' => 0,
                        ];
                    }
                }
                return ['ok' => false, 'error' => 'Nenhum aluno encontrado com esse nome.'];
            }
            if (count($cands) > 1) {
                // Match exato preferencial
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
                        'provas' => [],
                        'total' => 0,
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

        $materiaId = (int) ($filtros['materia_id'] ?? 0);
        $materiaNome = trim((string) ($filtros['materia_nome'] ?? ''));
        if ($materiaId <= 0 && $materiaNome !== '') {
            $materiaId = $this->resolverMateriaIdPorNome($materiaNome);
            if ($materiaId <= 0) {
                return ['ok' => false, 'error' => 'Matéria não encontrada: ' . $materiaNome];
            }
        }

        $tipoId = (int) ($filtros['tipo_avaliacao_id'] ?? 0);
        $tipoNome = trim((string) ($filtros['tipo_avaliacao_nome'] ?? $filtros['tipo'] ?? ''));
        if ($tipoId <= 0 && $tipoNome !== '') {
            $tipo = $this->resolverTipoAvaliacao($tipoNome);
            $tipoId = $tipo ? (int) $tipo['id'] : 0;
            if ($tipoId <= 0) {
                return ['ok' => false, 'error' => 'Tipo de avaliação não encontrado: ' . $tipoNome];
            }
        }

        $dataInicio = $this->normalizarData($filtros['data_inicio'] ?? null);
        $dataFim = $this->normalizarData($filtros['data_fim'] ?? null);
        $bimestre = (int) ($filtros['bimestre'] ?? 0);
        if ($bimestre < 1 || $bimestre > 4) {
            $bimestre = 0;
        }
        $status = strtolower(trim((string) ($filtros['status'] ?? 'finalizado')));
        if (!in_array($status, ['finalizado', 'todos', 'em_andamento', 'cancelada'], true)) {
            $status = 'finalizado';
        }
        $limite = (int) ($filtros['limite'] ?? 50);
        $limite = max(1, min($limite, 200));

        $provas = $this->consultarProvas(
            $alunoId,
            $materiaId > 0 ? $materiaId : null,
            $tipoId > 0 ? $tipoId : null,
            $dataInicio,
            $dataFim,
            $status,
            $limite,
            null,
            $bimestre > 0 ? $bimestre : null
        );

        return [
            'ok' => true,
            'aluno' => $aluno,
            'provas' => $provas,
            'total' => count($provas),
            'filtros_aplicados' => [
                'aluno_id' => $alunoId,
                'materia_id' => $materiaId > 0 ? $materiaId : null,
                'tipo_avaliacao_id' => $tipoId > 0 ? $tipoId : null,
                'bimestre' => $bimestre > 0 ? $bimestre : null,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'status' => $status,
                'limite' => $limite,
            ],
        ];
    }

    /**
     * Detalhe de uma realização/prova do aluno (metadados + questões item a item).
     *
     * @param array{
     *   somente_erros?:bool|int|string,
     *   materia_nome?:string,
     *   titulo?:string,
     *   titulo_prova?:string
     * } $opcoes
     * @return array{ok:bool,aluno?:array,detalhe?:array,candidatos_provas?:list,error?:string}
     */
    public function detalharProvaAluno(int $alunoId, int $provaId, array $opcoes = []): array
    {
        $aluno = $this->obterAluno($alunoId);
        if ($aluno === null) {
            return ['ok' => false, 'error' => 'Aluno não encontrado.'];
        }

        if ($provaId <= 0) {
            $resolvido = $this->resolverProvaIdParaDetalhe($alunoId, $opcoes);
            if (!empty($resolvido['error'])) {
                return ['ok' => false, 'error' => $resolvido['error']];
            }
            if (!empty($resolvido['candidatos_provas'])) {
                return [
                    'ok' => false,
                    'error' => 'Há mais de uma prova. Informe prova_id.',
                    'aluno' => $aluno,
                    'candidatos_provas' => $resolvido['candidatos_provas'],
                ];
            }
            $provaId = (int) ($resolvido['prova_id'] ?? 0);
        }

        if ($provaId <= 0) {
            return ['ok' => false, 'error' => 'prova_id inválido. Liste as provas e informe o prova_id.'];
        }

        $lista = $this->consultarProvas($alunoId, null, null, null, null, 'todos', 5, $provaId);
        if ($lista === []) {
            return ['ok' => false, 'error' => 'Prova/realização não encontrada para este aluno.'];
        }
        $base = $lista[0];

        if (!class_exists('Exam', false)) {
            require_once __DIR__ . '/../Models/Exams/Exam.php';
        }
        $exam = new Exam();
        $provaConteudoId = $this->resolverProvaConteudoId($provaId, $alunoId);
        $versaoAdaptada = $provaConteudoId !== $provaId;

        // Contagem item a item vive no clone (EducaInclui); nota/tempo ficam na realização original.
        $contagem = $exam->getContagemAcertosErros($provaConteudoId, $alunoId);
        $realizacao = $exam->getRealizacao($provaId, $alunoId) ?: [];
        if ($versaoAdaptada && ((int) ($contagem['acertos'] ?? 0) + (int) ($contagem['erros'] ?? 0)) === 0) {
            $contagem = $exam->getContagemAcertosErros($provaId, $alunoId);
        }

        $base['realizacao']['acertos'] = (int) ($contagem['acertos'] ?? $base['realizacao']['acertos']);
        $base['realizacao']['erros'] = (int) ($contagem['erros'] ?? $base['realizacao']['erros']);
        $base['realizacao']['total_questoes'] = $base['realizacao']['acertos'] + $base['realizacao']['erros'];
        if ($base['realizacao']['total_questoes'] > 0) {
            $base['realizacao']['percentual_acerto'] = round(
                ($base['realizacao']['acertos'] / $base['realizacao']['total_questoes']) * 100,
                1
            );
        }
        if (!empty($realizacao['iniciado_em'])) {
            $base['realizacao']['iniciado_em'] = (string) $realizacao['iniciado_em'];
        }
        if (!empty($realizacao['tempo_gasto'])) {
            $base['realizacao']['tempo_gasto'] = $realizacao['tempo_gasto'];
        }

        $somenteErros = $this->flagVerdadeira($opcoes['somente_erros'] ?? false);
        $questoes = $this->montarQuestoesDetalhe($exam, $provaConteudoId, $alunoId, $somenteErros);
        $base['questoes'] = $questoes;
        $base['total_questoes_detalhe'] = count($questoes);
        $base['somente_erros'] = $somenteErros;
        $base['versao_adaptada'] = $versaoAdaptada;

        return [
            'ok' => true,
            'aluno' => $aluno,
            'detalhe' => $base,
        ];
    }

    /**
     * Resolve prova_id por matéria/título quando o LLM não repassa o id.
     *
     * @param array<string,mixed> $opcoes
     * @return array{prova_id?:int,candidatos_provas?:list,error?:string}
     */
    private function resolverProvaIdParaDetalhe(int $alunoId, array $opcoes): array
    {
        $materiaNome = trim((string) ($opcoes['materia_nome'] ?? $opcoes['materia'] ?? ''));
        $titulo = trim((string) ($opcoes['titulo'] ?? $opcoes['titulo_prova'] ?? ''));

        $filtros = [
            'aluno_id' => $alunoId,
            'limite' => 30,
            'status' => 'finalizado',
        ];
        if ($materiaNome !== '') {
            $filtros['materia_nome'] = $materiaNome;
        }
        $lista = $this->listarProvasAluno($filtros);
        if (empty($lista['ok']) || empty($lista['provas']) || !is_array($lista['provas'])) {
            return ['error' => 'Não encontrei provas deste aluno para detalhar. Liste as provas antes.'];
        }

        $provas = $lista['provas'];
        if ($titulo !== '') {
            $tituloNorm = mb_strtolower($titulo);
            $filtradas = [];
            foreach ($provas as $p) {
                $t = mb_strtolower(trim((string) ($p['titulo'] ?? '')));
                if ($t !== '' && (str_contains($t, $tituloNorm) || str_contains($tituloNorm, $t))) {
                    $filtradas[] = $p;
                }
            }
            if ($filtradas !== []) {
                $provas = $filtradas;
            }
        }

        // Preferir prova com erros > 0 quando o pedido é "qual errou".
        if ($this->flagVerdadeira($opcoes['somente_erros'] ?? false) && count($provas) > 1) {
            $comErro = [];
            foreach ($provas as $p) {
                if ((int) ($p['realizacao']['erros'] ?? 0) > 0) {
                    $comErro[] = $p;
                }
            }
            if (count($comErro) === 1) {
                return ['prova_id' => (int) $comErro[0]['prova_id']];
            }
            if ($comErro !== []) {
                $provas = $comErro;
            }
        }

        if (count($provas) === 1) {
            return ['prova_id' => (int) $provas[0]['prova_id']];
        }

        $candidatos = [];
        foreach (array_slice($provas, 0, 10) as $p) {
            $candidatos[] = [
                'prova_id' => (int) ($p['prova_id'] ?? 0),
                'titulo' => (string) ($p['titulo'] ?? ''),
                'materia' => (string) ($p['materia']['nome'] ?? ''),
                'erros' => (int) ($p['realizacao']['erros'] ?? 0),
                'acertos' => (int) ($p['realizacao']['acertos'] ?? 0),
            ];
        }
        return ['candidatos_provas' => $candidatos];
    }

    private function resolverProvaConteudoId(int $provaId, int $alunoId): int
    {
        try {
            $adaptRow = $this->db->fetch(
                "SELECT adapted_prova_id FROM versoes_adaptadas
                 WHERE prova_id = :pid AND aluno_id = :aid
                   AND status_aprovacao = 'aprovada' AND adapted_prova_id IS NOT NULL
                 ORDER BY id DESC LIMIT 1",
                ['pid' => $provaId, 'aid' => $alunoId]
            );
            if ($adaptRow && !empty($adaptRow['adapted_prova_id'])) {
                return (int) $adaptRow['adapted_prova_id'];
            }
        } catch (Throwable $e) {
            // módulo ausente
        }
        return $provaId;
    }

    /**
     * Questões com enunciado, marcada × correta e acerto/erro.
     * $provaConteudoId já deve ser o clone EducaInclui quando houver.
     *
     * @return list<array<string,mixed>>
     */
    private function montarQuestoesDetalhe($exam, int $provaConteudoId, int $alunoId, bool $somenteErros): array
    {
        $questoesRaw = $exam->getQuestoes($provaConteudoId) ?: [];
        $respostas = $exam->getRespostas($provaConteudoId, $alunoId) ?: [];
        $mapa = [];
        foreach ($respostas as $r) {
            $mapa[(int) ($r['questao_id'] ?? 0)] = $r;
        }

        $out = [];
        $numero = 0;
        foreach ($questoesRaw as $q) {
            $numero++;
            $qid = (int) ($q['id'] ?? 0);
            $tipo = (string) ($q['tipo'] ?? '');
            $resp = $mapa[$qid] ?? null;
            $respondeu = $resp !== null;
            $corrigida = $respondeu && array_key_exists('correta', $resp) && $resp['correta'] !== null && $resp['correta'] !== '';
            $acertou = $corrigida && !empty($resp['correta']);

            if ($somenteErros) {
                // Só erros confirmados (não inclui dissertativa sem correção).
                if (!$respondeu || !$corrigida || $acertou) {
                    continue;
                }
            }

            $alternativas = [];
            if (in_array($tipo, ['multipla_escolha', 'verdadeiro_falso', ''], true)) {
                $alternativas = $exam->getAlternativas($qid) ?: [];
            }

            $marcada = null;
            $correta = null;
            $letraIdx = 0;
            foreach ($alternativas as $alt) {
                $letra = chr(65 + $letraIdx);
                $letraIdx++;
                $item = [
                    'id' => (int) ($alt['id'] ?? 0),
                    'letra' => $letra,
                    'texto' => $this->textoCurto((string) ($alt['texto'] ?? ''), 200),
                    'eh_correta' => !empty($alt['correta']),
                ];
                if (!empty($alt['correta'])) {
                    $correta = $item;
                }
                if ($resp && !empty($resp['alternativa_id']) && (int) $resp['alternativa_id'] === $item['id']) {
                    $marcada = $item;
                }
            }

            $respostaTexto = null;
            if ($resp && ($tipo === 'dissertativa' || empty($resp['alternativa_id']))) {
                $respostaTexto = $this->textoCurto((string) ($resp['resposta_texto'] ?? ''), 400);
                if ($respostaTexto === '') {
                    $respostaTexto = null;
                }
            }

            $resultado = 'sem_resposta';
            if ($respondeu) {
                if (!$corrigida) {
                    $resultado = 'pendente';
                } else {
                    $resultado = $acertou ? 'acerto' : 'erro';
                }
            }

            $out[] = [
                'numero' => $numero,
                'questao_id' => $qid,
                'tipo' => $tipo,
                'valor' => isset($q['valor']) ? (float) $q['valor'] : null,
                'enunciado' => $this->textoCurto((string) ($q['enunciado'] ?? ''), 500),
                'respondeu' => $respondeu,
                'acertou' => $acertou,
                'resultado' => $resultado,
                'pontuacao' => $resp && isset($resp['pontuacao']) ? (float) $resp['pontuacao'] : null,
                'alternativa_marcada' => $marcada,
                'alternativa_correta' => $correta,
                'resposta_texto' => $respostaTexto,
            ];
        }

        return $out;
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
        if (mb_strlen($t) <= $max) {
            return $t;
        }
        return mb_substr($t, 0, $max - 1) . '…';
    }

    /**
     * Resumo agregado (por tipo, bimestre e matéria) com totais de acertos/erros.
     *
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,aluno?:array,total_provas?:int,total_acertos?:int,total_erros?:int,por_tipo?:list,por_materia?:list,error?:string}
     */
    public function resumoProvasAluno(array $filtros): array
    {
        $filtros['limite'] = min(200, (int) ($filtros['limite'] ?? 200));
        $lista = $this->listarProvasAluno($filtros);
        if (empty($lista['ok'])) {
            return $lista;
        }
        if (!empty($lista['candidatos'])) {
            return $lista;
        }

        $porTipo = [];
        $porMateria = [];
        $porBimestre = [];
        $totalAcertos = 0;
        $totalErros = 0;

        $novoGrupo = static function (): array {
            return [
                'quantidade' => 0,
                'acertos' => 0,
                'erros' => 0,
                'media_nota' => null,
                'soma_nota' => 0.0,
                'com_nota' => 0,
            ];
        };

        // Deduplica por prova_id: a mesma prova em vários blocos repetiria acertos/erros.
        $provasUnicas = [];
        foreach ($lista['provas'] as $p) {
            $pid = (int) ($p['prova_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            if (!isset($provasUnicas[$pid])) {
                $provasUnicas[$pid] = $p;
            }
        }

        foreach ($provasUnicas as $p) {
            $tipoNome = (string) ($p['tipo_avaliacao']['nome'] ?? 'Sem tipo');
            $matNome = (string) ($p['materia']['nome'] ?? 'Sem matéria');
            $bim = $p['evento']['bimestre'] ?? null;
            $bimKey = ($bim !== null && (int) $bim > 0) ? (string) ((int) $bim) . 'º bimestre' : 'Sem bimestre';
            if (!isset($porTipo[$tipoNome])) {
                $porTipo[$tipoNome] = ['tipo' => $tipoNome] + $novoGrupo();
            }
            if (!isset($porMateria[$matNome])) {
                $porMateria[$matNome] = ['materia' => $matNome] + $novoGrupo();
            }
            if (!isset($porBimestre[$bimKey])) {
                $porBimestre[$bimKey] = ['bimestre' => $bimKey] + $novoGrupo();
            }

            $acertos = (int) ($p['realizacao']['acertos'] ?? 0);
            $erros = (int) ($p['realizacao']['erros'] ?? 0);
            $totalAcertos += $acertos;
            $totalErros += $erros;

            $porTipo[$tipoNome]['quantidade']++;
            $porMateria[$matNome]['quantidade']++;
            $porBimestre[$bimKey]['quantidade']++;
            $porTipo[$tipoNome]['acertos'] += $acertos;
            $porTipo[$tipoNome]['erros'] += $erros;
            $porMateria[$matNome]['acertos'] += $acertos;
            $porMateria[$matNome]['erros'] += $erros;
            $porBimestre[$bimKey]['acertos'] += $acertos;
            $porBimestre[$bimKey]['erros'] += $erros;

            $nota = $p['realizacao']['nota'] ?? null;
            if ($nota !== null && $nota !== '') {
                $porTipo[$tipoNome]['soma_nota'] += (float) $nota;
                $porTipo[$tipoNome]['com_nota']++;
                $porMateria[$matNome]['soma_nota'] += (float) $nota;
                $porMateria[$matNome]['com_nota']++;
                $porBimestre[$bimKey]['soma_nota'] += (float) $nota;
                $porBimestre[$bimKey]['com_nota']++;
            }
        }
        foreach ($porTipo as &$t) {
            $t['media_nota'] = $t['com_nota'] > 0 ? round($t['soma_nota'] / $t['com_nota'], 2) : null;
            unset($t['soma_nota'], $t['com_nota']);
        }
        unset($t);
        foreach ($porMateria as &$m) {
            $m['media_nota'] = $m['com_nota'] > 0 ? round($m['soma_nota'] / $m['com_nota'], 2) : null;
            unset($m['soma_nota'], $m['com_nota']);
        }
        unset($m);
        foreach ($porBimestre as &$b) {
            $b['media_nota'] = $b['com_nota'] > 0 ? round($b['soma_nota'] / $b['com_nota'], 2) : null;
            unset($b['soma_nota'], $b['com_nota']);
        }
        unset($b);

        $totalQuestoes = $totalAcertos + $totalErros;

        return [
            'ok' => true,
            'aluno' => $lista['aluno'],
            'total_provas' => count($provasUnicas),
            'total_acertos' => $totalAcertos,
            'total_erros' => $totalErros,
            'total_questoes' => $totalQuestoes,
            'percentual_acerto' => $totalQuestoes > 0
                ? round(($totalAcertos / $totalQuestoes) * 100, 1)
                : null,
            'por_tipo' => array_values($porTipo),
            'por_bimestre' => array_values($porBimestre),
            'por_materia' => array_values($porMateria),
            'filtros_aplicados' => $lista['filtros_aplicados'],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function consultarProvas(
        int $alunoId,
        ?int $materiaId,
        ?int $tipoId,
        ?string $dataInicio,
        ?string $dataFim,
        string $status,
        int $limite,
        ?int $provaIdUnica = null,
        ?int $bimestre = null
    ): array {
        $temTipo = $this->temColuna('provas_blocos', 'tipo_avaliacao_id')
            && $this->temTabela('provas_tipos_avaliacao');

        $selectTipoId = $temTipo ? 'pta.id AS tipo_avaliacao_id' : 'NULL AS tipo_avaliacao_id';
        $selectTipoNome = $temTipo ? 'pta.nome AS tipo_avaliacao_nome' : 'NULL AS tipo_avaliacao_nome';
        $joinTipo = $temTipo
            ? 'LEFT JOIN provas_tipos_avaliacao pta ON pta.id = pb.tipo_avaliacao_id AND pta.deleted_at IS NULL'
            : '';

        $sql = "SELECT
                    pr.id AS realizacao_id,
                    pr.prova_id,
                    pr.aluno_id,
                    pr.nota,
                    pr.status,
                    pr.iniciado_em,
                    pr.finalizado_em,
                    p.titulo AS prova_titulo,
                    p.materia_id,
                    m.nome AS materia_nome,
                    p.valor_total,
                    pb.id AS bloco_id,
                    pb.titulo AS bloco_titulo,
                    pb.data_prova,
                    pb.bimestre,
                    pb.ano_letivo,
                    {$selectTipoId},
                    {$selectTipoNome},
                    (SELECT COUNT(*) FROM provas_respostas rr
                      WHERE rr.prova_id = pr.prova_id AND rr.aluno_id = pr.aluno_id) AS total_questoes,
                    (SELECT COUNT(*) FROM provas_respostas rr
                      WHERE rr.prova_id = pr.prova_id AND rr.aluno_id = pr.aluno_id AND rr.correta = 1) AS acertos,
                    (SELECT COUNT(*) FROM provas_respostas rr
                      WHERE rr.prova_id = pr.prova_id AND rr.aluno_id = pr.aluno_id AND rr.correta = 0) AS erros
                FROM provas_realizacoes pr
                INNER JOIN provas p ON p.id = pr.prova_id
                LEFT JOIN materias m ON m.id = p.materia_id
                LEFT JOIN provas_blocos_vinculo pbv ON pbv.prova_id = pr.prova_id
                LEFT JOIN provas_blocos pb ON pb.id = pbv.bloco_id AND pb.deleted_at IS NULL
                {$joinTipo}
                WHERE pr.aluno_id = :aluno_id";

        $params = ['aluno_id' => $alunoId];

        if ($provaIdUnica !== null && $provaIdUnica > 0) {
            $sql .= ' AND pr.prova_id = :prova_id';
            $params['prova_id'] = $provaIdUnica;
        }

        if ($status !== 'todos') {
            $sql .= ' AND pr.status = :status';
            $params['status'] = $status;
        }

        if ($materiaId !== null && $materiaId > 0) {
            $sql .= ' AND p.materia_id = :materia_id';
            $params['materia_id'] = $materiaId;
        }

        if ($temTipo && $tipoId !== null && $tipoId > 0) {
            $sql .= ' AND pb.tipo_avaliacao_id = :tipo_id';
            $params['tipo_id'] = $tipoId;
        }

        if ($bimestre !== null && $bimestre > 0) {
            $sql .= ' AND pb.bimestre = :bimestre';
            $params['bimestre'] = $bimestre;
        }

        if ($dataInicio !== null && $dataFim !== null) {
            $sql .= ' AND (
                (pr.finalizado_em IS NOT NULL AND DATE(pr.finalizado_em) BETWEEN :di1 AND :df1)
                OR (pb.data_prova IS NOT NULL AND CAST(pb.data_prova AS CHAR) <> \'0000-00-00\'
                    AND pb.data_prova BETWEEN :di2 AND :df2)
            )';
            $params['di1'] = $dataInicio;
            $params['df1'] = $dataFim;
            $params['di2'] = $dataInicio;
            $params['df2'] = $dataFim;
        } elseif ($dataInicio !== null) {
            $sql .= ' AND (
                (pr.finalizado_em IS NOT NULL AND DATE(pr.finalizado_em) >= :di1)
                OR (pb.data_prova IS NOT NULL AND pb.data_prova >= :di2)
            )';
            $params['di1'] = $dataInicio;
            $params['di2'] = $dataInicio;
        } elseif ($dataFim !== null) {
            $sql .= ' AND (
                (pr.finalizado_em IS NOT NULL AND DATE(pr.finalizado_em) <= :df1)
                OR (pb.data_prova IS NOT NULL AND pb.data_prova <= :df2)
            )';
            $params['df1'] = $dataFim;
            $params['df2'] = $dataFim;
        }

        // JOIN com blocos pode multiplicar linhas; busca folga e corta após dedupe.
        $limiteSql = min(500, max($limite * 3, $limite));
        $sql .= " ORDER BY COALESCE(pr.finalizado_em, pb.data_prova) DESC, pr.id DESC LIMIT {$limiteSql}";

        $rows = $this->db->fetchAll($sql, $params) ?: [];
        $out = [];
        $vistos = [];
        foreach ($rows as $r) {
            $provaId = (int) ($r['prova_id'] ?? 0);
            $blocoId = (int) ($r['bloco_id'] ?? 0);
            $key = $provaId . ':' . $blocoId;
            if (isset($vistos[$key])) {
                continue;
            }
            $vistos[$key] = true;

            $acertos = (int) ($r['acertos'] ?? 0);
            $erros = (int) ($r['erros'] ?? 0);
            $total = (int) ($r['total_questoes'] ?? ($acertos + $erros));
            $nota = isset($r['nota']) && $r['nota'] !== null && $r['nota'] !== '' ? (float) $r['nota'] : null;
            $perc = $total > 0 ? round(($acertos / $total) * 100, 1) : null;

            $out[] = [
                'prova_id' => $provaId,
                'titulo' => trim((string) ($r['prova_titulo'] ?? '')),
                'materia' => [
                    'id' => isset($r['materia_id']) ? (int) $r['materia_id'] : null,
                    'nome' => isset($r['materia_nome']) ? trim((string) $r['materia_nome']) : null,
                ],
                'tipo_avaliacao' => [
                    'id' => isset($r['tipo_avaliacao_id']) ? (int) $r['tipo_avaliacao_id'] : null,
                    'nome' => isset($r['tipo_avaliacao_nome']) ? trim((string) $r['tipo_avaliacao_nome']) : null,
                ],
                'evento' => [
                    'id' => $blocoId > 0 ? $blocoId : null,
                    'titulo' => isset($r['bloco_titulo']) ? trim((string) $r['bloco_titulo']) : null,
                    'data_prova' => $this->formatarData($r['data_prova'] ?? null),
                    'bimestre' => isset($r['bimestre']) ? (int) $r['bimestre'] : null,
                    'ano_letivo' => isset($r['ano_letivo']) ? (int) $r['ano_letivo'] : null,
                ],
                'realizacao' => [
                    'id' => isset($r['realizacao_id']) ? (int) $r['realizacao_id'] : null,
                    'status' => (string) ($r['status'] ?? ''),
                    'iniciado_em' => isset($r['iniciado_em']) ? (string) $r['iniciado_em'] : null,
                    'finalizado_em' => isset($r['finalizado_em']) ? (string) $r['finalizado_em'] : null,
                    'dia_realizacao' => $this->formatarData($r['finalizado_em'] ?? null),
                    'nota' => $nota,
                    'valor_total' => isset($r['valor_total']) ? (float) $r['valor_total'] : null,
                    'acertos' => $acertos,
                    'erros' => $erros,
                    'total_questoes' => $total,
                    'percentual_acerto' => $perc,
                ],
            ];

            if (count($out) >= $limite) {
                break;
            }
        }
        return $out;
    }

    private function resolverMateriaIdPorNome(string $nome): int
    {
        $needle = mb_strtolower(trim($nome));
        if ($needle === '') {
            return 0;
        }
        $parcial = 0;
        foreach ($this->listarMaterias(300) as $m) {
            $n = mb_strtolower($m['nome']);
            if ($n === $needle) {
                return (int) $m['id'];
            }
            if ($parcial === 0 && (str_contains($n, $needle) || str_contains($needle, $n))) {
                $parcial = (int) $m['id'];
            }
        }
        return $parcial;
    }

    /** @return array{id:int,nome:string}|null */
    private function resolverTipoAvaliacao(string $nome): ?array
    {
        $needle = mb_strtolower(trim($nome));
        if ($needle === '') {
            return null;
        }
        $parcial = null;
        foreach ($this->listarTiposAvaliacao() as $t) {
            $n = mb_strtolower($t['nome']);
            if ($n === $needle) {
                return ['id' => (int) $t['id'], 'nome' => $t['nome']];
            }
            if ($parcial === null && (str_contains($n, $needle) || str_contains($needle, $n))) {
                $parcial = ['id' => (int) $t['id'], 'nome' => $t['nome']];
            }
        }
        return $parcial;
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

    private function temColuna(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            if (method_exists($this->db, 'columnExists')) {
                $cache[$key] = (bool) $this->db->columnExists($table, $column);
            } else {
                $row = $this->db->fetch(
                    'SELECT 1 AS ok FROM information_schema.columns
                     WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1',
                    ['t' => $table, 'c' => $column]
                );
                $cache[$key] = !empty($row);
            }
        } catch (Throwable $e) {
            $cache[$key] = false;
        }
        return $cache[$key];
    }

    /** Compara nomes de turma de forma flexível (ex.: "2 Ano B" ≈ "2ª B do Ensino Médio"). */
    private function turmaCasa(?string $turmaNome, string $filtro): bool
    {
        $n = $this->normalizarTextoTurma((string) $turmaNome);
        $f = $this->normalizarTextoTurma($filtro);
        if ($f === '') {
            return true;
        }
        if ($n === '') {
            return false;
        }
        if ($n === $f || str_contains($n, $f) || str_contains($f, $n)) {
            return true;
        }
        // Tokens significativos: números e letras (ex.: 2 + b).
        if (!preg_match_all('/[0-9]+|[a-z]/', $f, $m) || $m[0] === []) {
            return false;
        }
        foreach ($m[0] as $token) {
            if ($token === '') {
                continue;
            }
            if (!str_contains($n, $token)) {
                return false;
            }
        }
        // Série + letra devem aparecer "juntas" o bastante (evita 1B casar com 2B só pela letra).
        if (preg_match('/([0-9]+)([a-z])/', $f, $mf) || (preg_match('/([0-9]+)/', $f, $dn) && preg_match('/([a-z])/', $f, $lt))) {
            $dig = $mf[1] ?? ($dn[1] ?? '');
            $let = $mf[2] ?? ($lt[1] ?? '');
            if ($dig !== '' && $let !== '') {
                // Aceita 2b, 2ab (ordinal transliterado), 2xb…
                if (!preg_match('/' . preg_quote($dig, '/') . '[a-z]{0,2}' . preg_quote($let, '/') . '/', $n)) {
                    return false;
                }
            }
        }
        return true;
    }

    private function normalizarTextoTurma(string $texto): string
    {
        $s = mb_strtolower(trim($texto));
        if ($s === '') {
            return '';
        }
        // Ordinals antes do translit: 2ª → 2, 1º → 1 (evita "2a" + "b" = "2ab" que quebra contains "2b").
        $s = preg_replace('/(\d+)\s*[ªº°aao]\b/u', '$1', $s) ?? $s;
        $s = str_replace(['ª', 'º', '°'], '', $s);
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if (is_string($t) && $t !== '') {
                $s = $t;
            }
        }
        $s = preg_replace(
            '/\b(ano|serie|série|turma|ensino|medio|médio|fundamental|infantil|tecnico|técnico|em|do|da|de|dos|das|e)\b/u',
            ' ',
            $s
        ) ?? $s;
        // Remove ordinal residual grudado no dígito (2a → 2).
        $s = preg_replace('/(\d)[ao](?=[a-z]|\b)/', '$1', $s) ?? $s;
        $s = preg_replace('/[^a-z0-9]+/', '', $s) ?? $s;
        return $s;
    }

    /**
     * Tokens de nome para busca (ignora stopwords e pedaços de turma no termo).
     *
     * @return list<string>
     */
    private function tokensBuscaNome(string $termo): array
    {
        $stop = [
            'do', 'da', 'de', 'dos', 'das', 'o', 'a', 'e', 'ou',
            'ano', 'serie', 'série', 'turma', 'aluno', 'aluna',
            'ensino', 'medio', 'médio', 'fundamental', 'em',
        ];
        if (!preg_match_all('/[\p{L}]{2,}/u', mb_strtolower($termo), $m)) {
            return [];
        }
        $out = [];
        foreach ($m[0] as $w) {
            if (in_array($w, $stop, true)) {
                continue;
            }
            // Evita letras soltas de turma ("B") como token de nome.
            if (mb_strlen($w) < 3) {
                continue;
            }
            $out[] = $w;
        }
        return array_values(array_unique($out));
    }
}
