<?php
/**
 * EducaTudo - Controller dos Pais
 * Gerencia painel dos parents/responsáveis
 */

if (!class_exists('ParentController')) {
class ParentController extends BaseController
{
    private $auth;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        
        // Verifica se é pai (o middleware Auth já verificou se está logado)
        $user = $this->auth->getUser();
        if ($user && $user['tipo'] !== 'pai') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
    }

    private function getPaiId(): int
    {
        $user = $this->auth->getUser();
        return (int)($user['id'] ?? 0);
    }

    private function getFilhoById(int $filhoId): ?array
    {
        $paiId = $this->getPaiId();
        if ($paiId <= 0 || $filhoId <= 0) {
            return null;
        }
        return $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome, t.serie
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             WHERE a.id = :filho_id
               AND a.ativo = 1
               AND (
                    a.responsavel_id = :pai_id_legacy
                    OR EXISTS (
                        SELECT 1
                        FROM alunos_responsaveis ar
                        WHERE ar.aluno_id = a.id
                          AND ar.responsavel_id = :pai_id_rel
                          AND ar.ativo = 1
                    )
               )",
            [
                'filho_id' => $filhoId,
                'pai_id_legacy' => $paiId,
                'pai_id_rel' => $paiId
            ]
        ) ?: null;
    }

    private function canAccessFinanceiroFilho(int $filhoId): bool
    {
        $paiId = $this->getPaiId();
        if ($paiId <= 0 || $filhoId <= 0) {
            return false;
        }
        $row = $this->db->fetch(
            "SELECT ar.id
             FROM alunos_responsaveis ar
             WHERE ar.aluno_id = :filho_id
               AND ar.responsavel_id = :pai_id
               AND ar.ativo = 1
               AND ar.is_financeiro = 1
             LIMIT 1",
            ['filho_id' => $filhoId, 'pai_id' => $paiId]
        );
        return $row !== false && !empty($row);
    }

    /**
     * Retorna a lista de filhos do pai logado
     */
    private function getFilhos(): array
    {
        $user = $this->auth->getUser();
        $pai = $this->db->fetch(
            "SELECT p.* FROM responsaveis p WHERE p.id = :user_id",
            ['user_id' => $user['id']]
        );
        if (!$pai) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT a.*, t.nome as turma_nome, t.serie
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             WHERE a.ativo = 1
               AND (
                    a.responsavel_id = :pai_id_legacy
                    OR EXISTS (
                        SELECT 1
                        FROM alunos_responsaveis ar
                        WHERE ar.aluno_id = a.id
                          AND ar.responsavel_id = :pai_id_rel
                          AND ar.ativo = 1
                    )
               )
             ORDER BY a.nome ASC",
            [
                'pai_id_legacy' => $pai['id'],
                'pai_id_rel' => $pai['id']
            ]
        );
    }

    /**
     * Retorna o filho atualmente selecionado (sessão) ou o primeiro da lista
     */
    private function getFilhoSelecionado(array $filhos): ?array
    {
        $id = isset($_SESSION['pais_filho_id']) ? (int)$_SESSION['pais_filho_id'] : null;
        if ($id && $filhos) {
            foreach ($filhos as $f) {
                if ((int)$f['id'] === $id) {
                    return $f;
                }
            }
        }
        return !empty($filhos) ? $filhos[0] : null;
    }

    private function normalizarTextoBoletim(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        if ($texto === '') {
            return '';
        }

        $map = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ç' => 'c', 'ª' => 'a', 'º' => 'o',
        ];

        return strtr($texto, $map);
    }

    private function inferGrupoTabelaBlocoLetra(array $col): array
    {
        $textos = array_filter([
            trim((string) ($col['bloco_modelo_nome'] ?? '')),
            trim((string) ($col['bloco_titulo'] ?? '')),
            trim((string) ($col['sample_prova_titulo'] ?? '')),
        ]);

        foreach ($textos as $textoOriginal) {
            $texto = $this->normalizarTextoBoletim($textoOriginal);
            if ($texto === '') {
                continue;
            }

            if (strpos($texto, 'avaliacao diagnostica') !== false) {
                return ['key' => 'avaliacao_diagnostica', 'label' => 'Avaliação Diagnóstica', 'ord_primary' => 10, 'ord_secondary' => 0];
            }

            if (preg_match('/\bbloco\s*[:\-]?\s*b(?:\s*(?:1|1a|1o|primeira|2|2a|2o|segunda))?\b/u', $texto)) {
                return ['key' => 'bloco_b', 'label' => 'Bloco B', 'ord_primary' => 66, 'ord_secondary' => 0];
            }

            if (preg_match('/\bbloco\s*[:\-]?\s*([a-z])\b/u', $texto, $m)) {
                $letra = strtoupper($m[1]);
                return ['key' => 'letra_' . $letra, 'label' => 'Bloco ' . $letra, 'ord_primary' => ord($letra), 'ord_secondary' => 0];
            }
        }

        $bid = (int) ($col['bloco_id'] ?? 0);
        if ($bid > 0) {
            $label = trim((string) ($col['bloco_modelo_nome'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($col['bloco_titulo'] ?? ''));
            }
            if ($label === '') {
                $label = 'Bloco #' . $bid;
            }
            return ['key' => 'bloco_' . $bid, 'label' => $label, 'ord_primary' => 200000, 'ord_secondary' => $bid];
        }

        return ['key' => 'sem_bloco', 'label' => 'Sem bloco', 'ord_primary' => 500000, 'ord_secondary' => 0];
    }

    private function buildProvasMatrizPorBlocoAplicado(array $rawRows): array
    {
        $vazio = ['tabelas' => [], 'tem_dados' => false];
        if ($rawRows === []) {
            return $vazio;
        }

        $colCandidates = [];
        $grid = [];
        $materiaRotulo = [];

        foreach ($rawRows as $row) {
            $blocoId = isset($row['bloco_id']) ? (int) $row['bloco_id'] : 0;
            $materiaExpr = trim((string) ($row['prova_materia'] ?? ''));
            $mk = $materiaExpr !== '' ? mb_strtolower($materiaExpr, 'UTF-8') : '_sem_materia';
            if (!isset($materiaRotulo[$mk])) {
                $materiaRotulo[$mk] = $materiaExpr !== '' ? $materiaExpr : '—';
            }

            $totalQ = (int) ($row['prova_total_questoes'] ?? 0);
            $acertos = (int) ($row['prova_acertos'] ?? 0);
            $dataProvaBanco = $row['bloco_data_prova'] ?? null;
            $iniciado = $row['iniciado_em'] ?? null;
            $tsColuna = 0;
            if (!empty($dataProvaBanco)) {
                $tsColuna = (int) strtotime((string) $dataProvaBanco);
            }
            if ($tsColuna <= 0 && !empty($iniciado)) {
                $tsColuna = (int) strtotime((string) $iniciado);
            }

            $colKey = $blocoId > 0 ? 'b:' . $blocoId : 'd:' . ($tsColuna > 0 ? date('Y-m-d', $tsColuna) : 'sem_data');

            if (!isset($colCandidates[$colKey])) {
                $colCandidates[$colKey] = [
                    'key' => $colKey,
                    'bloco_id' => $blocoId,
                    'sort_ts' => $tsColuna > 0 ? $tsColuna : PHP_INT_MAX,
                    'data_label' => $tsColuna > 0 ? date('d/m/Y', $tsColuna) : '—',
                    'bloco_titulo' => trim((string) ($row['bloco_titulo'] ?? '')),
                    'bloco_modelo_id' => (int) ($row['bloco_modelo_id'] ?? 0),
                    'bloco_modelo_nome' => trim((string) ($row['bloco_modelo_nome'] ?? '')),
                    'bloco_bimestre' => !empty($row['bloco_bimestre']) ? (int) $row['bloco_bimestre'] : 0,
                    'bloco_ano_letivo' => !empty($row['bloco_ano_letivo']) ? (int) $row['bloco_ano_letivo'] : 0,
                    'sample_prova_titulo' => '',
                ];
            }

            $provaTit = trim((string) ($row['prova_titulo'] ?? ''));
            if ($provaTit !== '' && $colCandidates[$colKey]['sample_prova_titulo'] === '') {
                $colCandidates[$colKey]['sample_prova_titulo'] = $provaTit;
            }

            if (!isset($grid[$mk])) {
                $grid[$mk] = [];
            }
            if (!isset($grid[$mk][$colKey])) {
                $grid[$mk][$colKey] = ['q' => 0, 'acertos' => 0];
            }
            $grid[$mk][$colKey]['q'] += $totalQ;
            $grid[$mk][$colKey]['acertos'] += $acertos;
        }

        uasort($colCandidates, function ($a, $b) {
            if ($a['sort_ts'] === $b['sort_ts']) {
                return strcmp((string) $a['key'], (string) $b['key']);
            }
            return $a['sort_ts'] <=> $b['sort_ts'];
        });

        $colunas = [];
        foreach ($colCandidates as $meta) {
            $colunas[] = [
                'key' => $meta['key'],
                'titulo' => '',
                'data_label' => $meta['data_label'],
                'bloco_titulo' => $meta['bloco_titulo'],
                'bloco_modelo_id' => (int) ($meta['bloco_modelo_id'] ?? 0),
                'bloco_modelo_nome' => (string) ($meta['bloco_modelo_nome'] ?? ''),
                'bloco_bimestre' => (int) ($meta['bloco_bimestre'] ?? 0),
                'bloco_ano_letivo' => (int) ($meta['bloco_ano_letivo'] ?? 0),
                'bloco_id' => (int) ($meta['bloco_id'] ?? 0),
                'sample_prova_titulo' => (string) ($meta['sample_prova_titulo'] ?? ''),
            ];
        }

        $grupos = [];
        foreach ($colunas as $col) {
            $grupo = $this->inferGrupoTabelaBlocoLetra($col);
            $bimestreCol = (int) ($col['bloco_bimestre'] ?? 0);
            $gk = $grupo['key'] . '|bim:' . $bimestreCol;
            if (!isset($grupos[$gk])) {
                $tituloSecao = $grupo['label'];
                if ($bimestreCol > 0) {
                    $tituloSecao .= ' — ' . $bimestreCol . 'º Bimestre';
                }
                $grupos[$gk] = [
                    'key' => $gk,
                    'titulo_secao' => $tituloSecao,
                    'bimestre' => $bimestreCol,
                    'ano_letivo' => (int) ($col['bloco_ano_letivo'] ?? 0),
                    'ord_primary' => $grupo['ord_primary'],
                    'ord_secondary' => $grupo['ord_secondary'],
                    'colunas_raw' => [],
                ];
            }
            $grupos[$gk]['colunas_raw'][] = $col;
        }

        uasort($grupos, function ($a, $b) {
            if (($a['bimestre'] ?? 0) !== ($b['bimestre'] ?? 0)) {
                return ($a['bimestre'] ?? 0) <=> ($b['bimestre'] ?? 0);
            }
            if ($a['ord_primary'] === $b['ord_primary']) {
                if ($a['ord_secondary'] === $b['ord_secondary']) {
                    return strcmp((string) $a['titulo_secao'], (string) $b['titulo_secao']);
                }
                return $a['ord_secondary'] <=> $b['ord_secondary'];
            }
            return $a['ord_primary'] <=> $b['ord_primary'];
        });

        $tabelas = [];
        foreach ($grupos as $grupo) {
            $colsGrupo = $grupo['colunas_raw'] ?? [];
            $colsFinal = [];
            $ord = 1;
            foreach ($colsGrupo as $col) {
                $col['titulo'] = 'S' . $ord++;
                $colsFinal[] = $col;
            }

            $materias = [];
            foreach ($materiaRotulo as $mk => $rotulo) {
                $celdas = [];
                $tq = 0;
                $ta = 0;
                foreach ($colsFinal as $col) {
                    $ck = $col['key'];
                    $base = ($grid[$mk][$ck] ?? []) + ['q' => 0, 'acertos' => 0];
                    $q = (int) ($base['q'] ?? 0);
                    $a = (int) ($base['acertos'] ?? 0);
                    $e = max(0, $q - $a);
                    $celdas[$ck] = ['q' => $q, 'acertos' => $a, 'erros' => $e];
                    $tq += $q;
                    $ta += $a;
                }
                if ($tq <= 0 && $ta <= 0) {
                    continue;
                }
                $materias[] = [
                    'materia' => $rotulo,
                    'celdas' => $celdas,
                    'totais' => ['q' => $tq, 'acertos' => $ta, 'erros' => max(0, $tq - $ta)],
                ];
            }

            usort($materias, function ($a, $b) {
                return strnatcasecmp((string) ($a['materia'] ?? ''), (string) ($b['materia'] ?? ''));
            });

            if ($colsFinal !== [] && $materias !== []) {
                $tabelas[] = [
                    'titulo_secao' => $grupo['titulo_secao'],
                    'bimestre' => (int) ($grupo['bimestre'] ?? 0),
                    'ano_letivo' => (int) ($grupo['ano_letivo'] ?? 0),
                    'colunas' => $colsFinal,
                    'materias' => $materias,
                ];
            }
        }

        return ['tabelas' => array_values($tabelas), 'tem_dados' => !empty($tabelas)];
    }

    private function getProvasRealizadasAlunoComBloco(int $alunoId): array
    {
        $hasProvasMateriaId = false;
        $hasRealizacoesMateria = false;
        $hasRealizacoesDisciplina = false;
        $hasRealizacoesAreaConhecimento = false;
        $hasQuestoesTotal = false;
        $hasTotalQuestoes = false;
        $hasQtdQuestoes = false;
        $hasQuestoesCorretas = false;
        $hasAcertos = false;
        $hasQtdAcertos = false;
        $hasProvasRespostasCorreta = false;

        try { $hasProvasMateriaId = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas LIKE 'materia_id'")); } catch (Exception $e) {}
        try { $hasRealizacoesMateria = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'materia'")); } catch (Exception $e) {}
        try { $hasRealizacoesDisciplina = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'disciplina'")); } catch (Exception $e) {}
        try { $hasRealizacoesAreaConhecimento = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'area_conhecimento'")); } catch (Exception $e) {}
        try { $hasQuestoesTotal = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'questoes_total'")); } catch (Exception $e) {}
        try { $hasTotalQuestoes = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'total_questoes'")); } catch (Exception $e) {}
        try { $hasQtdQuestoes = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'qtd_questoes'")); } catch (Exception $e) {}
        try { $hasQuestoesCorretas = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'questoes_corretas'")); } catch (Exception $e) {}
        try { $hasAcertos = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'acertos'")); } catch (Exception $e) {}
        try { $hasQtdAcertos = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'qtd_acertos'")); } catch (Exception $e) {}
        try { $hasProvasRespostasCorreta = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_respostas LIKE 'correta'")); } catch (Exception $e) {}

        $materiaParts = [];
        if ($hasProvasMateriaId) { $materiaParts[] = "NULLIF(TRIM(COALESCE(m.nome, '')), '')"; }
        if ($hasRealizacoesMateria) { $materiaParts[] = "NULLIF(TRIM(COALESCE(pr.materia, '')), '')"; }
        if ($hasRealizacoesDisciplina) { $materiaParts[] = "NULLIF(TRIM(COALESCE(pr.disciplina, '')), '')"; }
        if ($hasRealizacoesAreaConhecimento) { $materiaParts[] = "NULLIF(TRIM(COALESCE(pr.area_conhecimento, '')), '')"; }
        $materiaExpr = !empty($materiaParts) ? "COALESCE(" . implode(", ", $materiaParts) . ")" : "NULL";

        $qtdParts = [];
        if ($hasQuestoesTotal) { $qtdParts[] = "pr.questoes_total"; }
        if ($hasTotalQuestoes) { $qtdParts[] = "pr.total_questoes"; }
        if ($hasQtdQuestoes) { $qtdParts[] = "pr.qtd_questoes"; }
        $qtdParts[] = "(SELECT COUNT(*) FROM provas_questoes pq WHERE pq.prova_id = pr.prova_id)";
        $qtdExpr = !empty($qtdParts) ? "COALESCE(" . implode(", ", $qtdParts) . ", 0)" : "0";

        $acertosParts = [];
        if ($hasQuestoesCorretas) { $acertosParts[] = "pr.questoes_corretas"; }
        if ($hasAcertos) { $acertosParts[] = "pr.acertos"; }
        if ($hasQtdAcertos) { $acertosParts[] = "pr.qtd_acertos"; }
        if ($hasProvasRespostasCorreta) { $acertosParts[] = "(SELECT COUNT(*) FROM provas_respostas prs WHERE prs.prova_id = pr.prova_id AND prs.aluno_id = pr.aluno_id AND prs.correta = 1)"; }
        $acertosExpr = !empty($acertosParts) ? "COALESCE(" . implode(", ", $acertosParts) . ", 0)" : "0";

        $sqlComBloco =
            "SELECT pr.id, pr.prova_id, pr.nota, pr.iniciado_em, pr.status, p.titulo as prova_titulo,
                    {$materiaExpr} as prova_materia,
                    {$qtdExpr} as prova_total_questoes,
                    {$acertosExpr} as prova_acertos,
                    pb.id as bloco_id,
                    pb.bloco_modelo_id as bloco_modelo_id,
                    pbm.nome as bloco_modelo_nome,
                    pb.data_prova as bloco_data_prova,
                    pb.titulo as bloco_titulo,
                    pb.bimestre as bloco_bimestre,
                    pb.ano_letivo as bloco_ano_letivo
             FROM provas_realizacoes pr
             INNER JOIN provas p ON p.id = pr.prova_id
             LEFT JOIN materias m ON m.id = p.materia_id
             LEFT JOIN (
                SELECT prova_id, MAX(bloco_id) AS bloco_id
                FROM provas_blocos_vinculo
                GROUP BY prova_id
             ) pbv ON pbv.prova_id = p.id
             LEFT JOIN provas_blocos pb ON pb.id = pbv.bloco_id
             LEFT JOIN provas_blocos_modelos pbm ON pbm.id = pb.bloco_modelo_id
             WHERE pr.aluno_id = :id
             ORDER BY pr.iniciado_em DESC
             LIMIT 100";

        $sqlSemBloco =
            "SELECT pr.id, pr.prova_id, pr.nota, pr.iniciado_em, pr.status, p.titulo as prova_titulo,
                    {$materiaExpr} as prova_materia,
                    {$qtdExpr} as prova_total_questoes,
                    {$acertosExpr} as prova_acertos,
                    NULL as bloco_id,
                    NULL as bloco_modelo_id,
                    NULL as bloco_modelo_nome,
                    NULL as bloco_data_prova,
                    NULL as bloco_titulo,
                    NULL as bloco_bimestre,
                    NULL as bloco_ano_letivo
             FROM provas_realizacoes pr
             INNER JOIN provas p ON p.id = pr.prova_id
             LEFT JOIN materias m ON m.id = p.materia_id
             WHERE pr.aluno_id = :id
             ORDER BY pr.iniciado_em DESC
             LIMIT 100";

        try {
            return $this->db->fetchAll($sqlComBloco, ['id' => $alunoId]);
        } catch (Exception $eBloco) {
            return $this->db->fetchAll($sqlSemBloco, ['id' => $alunoId]);
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            return !empty($this->db->fetchAll("SHOW COLUMNS FROM {$table} LIKE '{$column}'"));
        } catch (Exception $e) {
            return false;
        }
    }

    private function parentJornadasService(): ParentJornadasService
    {
        require_once __DIR__ . '/../../Services/ParentJornadasService.php';
        return new ParentJornadasService($this->db);
    }

    private function getJornadasDetalhadasAluno(int $alunoId, int $turmaId, ?int $anoLetivo = null, ?int $bimestre = null): array
    {
        return $this->parentJornadasService()->listarDetalhadas($alunoId, $turmaId, $anoLetivo, $bimestre);
    }

    private function buildKpisJornadas(array $jornadas): array
    {
        $total = count($jornadas);
        $feitas = 0;
        $naoFeitas = 0;
        $concluidas = 0;
        $interacoes = 0;
        $modulosTotal = 0;
        $modulosFeitos = 0;

        foreach ($jornadas as $j) {
            $fez = !empty($j['fez']);
            if ($fez) {
                $feitas++;
            } else {
                $naoFeitas++;
            }
            if (!empty($j['concluiu'])) {
                $concluidas++;
            }
            $interacoes += (int) ($j['total_interacoes'] ?? 0);
            $modulosTotal += (int) ($j['total_modulos'] ?? 0);
            $modulosFeitos += (int) ($j['modulos_feitos'] ?? 0);
        }

        return [
            'total' => $total,
            'feitas' => $feitas,
            'nao_feitas' => $naoFeitas,
            'concluidas' => $concluidas,
            'taxa_execucao' => $total > 0 ? (int) round(($feitas / $total) * 100) : 0,
            'taxa_conclusao' => $total > 0 ? (int) round(($concluidas / $total) * 100) : 0,
            'interacoes' => $interacoes,
            'modulos_total' => $modulosTotal,
            'modulos_feitos' => $modulosFeitos,
            'taxa_modulos' => $modulosTotal > 0 ? (int) round(($modulosFeitos / $modulosTotal) * 100) : 0,
        ];
    }

    private function buildKpisProvas(array $provasRealizadas): array
    {
        $total = count($provasRealizadas);
        $totalComQuestoes = 0;
        $totalSemQuestoes = 0;
        $acertos = 0;
        $erros = 0;
        $questoes = 0;

        foreach ($provasRealizadas as &$p) {
            $q = (int) ($p['prova_total_questoes'] ?? 0);
            $a = (int) ($p['prova_acertos'] ?? 0);
            $e = max(0, $q - $a);
            $status = strtolower(trim((string) ($p['status'] ?? '')));
            $statusLabel = 'Não iniciada';
            if (in_array($status, ['finalizada', 'concluida', 'concluída'], true)) {
                $statusLabel = 'Finalizada';
            } elseif (in_array($status, ['iniciado', 'iniciada', 'em_andamento'], true)) {
                $statusLabel = 'Em andamento';
            }

            $p['acertos'] = $a;
            $p['erros'] = $e;
            $p['questoes'] = $q;
            $p['taxa_acerto'] = $q > 0 ? (int) round(($a / $q) * 100) : 0;
            $p['status_label'] = $statusLabel;

            if ($q > 0) {
                $totalComQuestoes++;
                $acertos += $a;
                $erros += $e;
                $questoes += $q;
            } else {
                $totalSemQuestoes++;
            }
        }
        unset($p);

        return [
            'total_realizadas' => $total,
            'total_com_questoes' => $totalComQuestoes,
            'total_sem_questoes' => $totalSemQuestoes,
            'acertos' => $acertos,
            'erros' => $erros,
            'questoes' => $questoes,
            'taxa_acerto_geral' => $questoes > 0 ? (int) round(($acertos / $questoes) * 100) : 0,
        ];
    }

    /**
     * Seleciona qual filho ver (salva na sessão e redireciona)
     */
    public function selecionarFilho()
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $filhos = $this->getFilhos();
        if ($id) {
            foreach ($filhos as $f) {
                if ((int)$f['id'] === $id) {
                    $_SESSION['pais_filho_id'] = $id;
                    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '/pais/dashboard';
                    if (strpos($redirect, 'http') === 0) {
                        header('Location: ' . $redirect, true, 302);
                        exit;
                    }
                    $this->redirect($redirect);
                    return;
                }
            }
        }
        unset($_SESSION['pais_filho_id']);
        $this->redirect('/pais/dashboard');
    }
    
    /**
     * Dashboard dos pais
     */
    public function dashboard()
    {
        $user = $this->auth->getUser();
        
        // Busca dados do pai
        $pai = $this->db->fetch(
            "SELECT p.* 
             FROM responsaveis p 
             WHERE p.id = :user_id",
            ['user_id' => $user['id']]
        );
        
        // Busca filhos do pai
        $filhos = $this->db->fetchAll(
            "SELECT a.*, t.nome as turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             WHERE a.ativo = 1
               AND (
                    a.responsavel_id = :pai_id_legacy
                    OR EXISTS (
                        SELECT 1
                        FROM alunos_responsaveis ar
                        WHERE ar.aluno_id = a.id
                          AND ar.responsavel_id = :pai_id_rel
                          AND ar.ativo = 1
                    )
               )
             ORDER BY a.nome ASC",
            [
                'pai_id_legacy' => $pai['id'],
                'pai_id_rel' => $pai['id']
            ]
        );
        
        // Estatísticas dos filhos
        $stats = [
            'total_filhos' => count($filhos),
            'total_jornadas' => 0,
            'total_exercicios' => 0,
            'total_redacoes' => 0
        ];
        
        foreach ($filhos as $filho) {
            $stats['total_jornadas'] += $this->db->fetch(
                "SELECT COUNT(*) as count FROM jornadas WHERE turma_id = :turma_id",
                ['turma_id' => $filho['turma_id']]
            )['count'];
            
            $stats['total_exercicios'] += $this->db->fetch(
                "SELECT COUNT(*) as count FROM exercicios e JOIN jornadas j ON e.jornada_id = j.id WHERE j.turma_id = :turma_id",
                ['turma_id' => $filho['turma_id']]
            )['count'];
            
            $stats['total_redacoes'] += $this->db->fetch(
                "SELECT COUNT(*) as count FROM redacoes WHERE aluno_id = :aluno_id",
                ['aluno_id' => $filho['id']]
            )['count'];
        }
        
        $filho = $this->getFilhoSelecionado($filhos);
        $data = [
            'title' => 'Painel dos Pais - EducaTudo',
            'page_title' => 'Dashboard',
            'current_page' => 'dashboard',
            'pai' => $pai,
            'filhos' => $filhos,
            'filho' => $filho,
            'stats' => $stats,
            'user' => $user
        ];
        
        $this->viewWithLayout('parent', 'parents/dashboard', $data);
    }
    
    /**
     * Lista filhos do pai
     */
    public function filhos()
    {
        $user = $this->auth->getUser();
        $filhos = $this->getFilhos();
        $filho = $this->getFilhoSelecionado($filhos);
        
        $data = [
            'title' => 'Meus Filhos - EducaTudo',
            'page_title' => 'Meus Filhos',
            'current_page' => 'filhos',
            'filhos' => $filhos,
            'filho' => $filho,
            'user' => $user
        ];
        
        $this->viewWithLayout('parent', 'parents/filhos', $data);
    }
    
    /**
     * Detalhes de um filho específico
     */
    public function filhoDetalhes($id)
    {
        $user = $this->auth->getUser();
        $filhos = $this->getFilhos();
        $filho = $this->getFilhoById((int)$id);
        
        if (!$filho) {
            $this->redirect('/pais/filhos');
        }
        
        $jornadas = array_slice(
            $this->getJornadasDetalhadasAluno((int) $filho['id'], (int) ($filho['turma_id'] ?? 0)),
            0,
            10
        );
        
        $redacoes = $this->db->fetchAll(
            "SELECT * FROM redacoes 
             WHERE aluno_id = :aluno_id
             ORDER BY created_at DESC
             LIMIT 5",
            ['aluno_id' => $filho['id']]
        );

        $ocorrencias = $this->db->fetchAll(
            "SELECT data_ocorrencia, titulo, detalhe, nivel_gravidade, atitude_coordenacao, retorno_em
             FROM alunos_ocorrencias
             WHERE aluno_id = :aluno_id AND enviar_pais = 1
             ORDER BY data_ocorrencia DESC",
            ['aluno_id' => $filho['id']]
        );
        
        $data = [
            'title' => 'Detalhes do Filho - EducaTudo',
            'page_title' => 'Detalhes do Filho',
            'current_page' => 'filhos',
            'filhos' => $filhos,
            'filho' => $filho,
            'user' => $user,
            'jornadas' => $jornadas,
            'redacoes' => $redacoes,
            'ocorrencias' => $ocorrencias
        ];
        
        $this->viewWithLayout('parent', 'parents/filho-detalhes', $data);
    }
    
    /**
     * Desempenho de um filho
     */
    public function desempenhoFilho($id)
    {
        $this->redirect('/pais/filhos/' . (int) $id . '/notas');
    }

    public function notasFilho($id)
    {
        $user = $this->auth->getUser();
        $filhos = $this->getFilhos();
        $filho = $this->getFilhoById((int) $id);

        if (!$filho) {
            $this->redirect('/pais/filhos');
        }

        $secao = strtolower(trim((string) ($_GET['secao'] ?? 'boletim')));
        if (!in_array($secao, ['boletim', 'notas', 'provas'], true)) {
            $secao = 'boletim';
        }
        $anoLetivo = isset($_GET['ano_letivo']) && $_GET['ano_letivo'] !== '' ? (int) $_GET['ano_letivo'] : null;
        $bimestre = isset($_GET['bimestre']) && $_GET['bimestre'] !== '' ? (int) $_GET['bimestre'] : null;
        if ($bimestre !== null && ($bimestre < 1 || $bimestre > 4)) {
            $bimestre = null;
        }

        try {
            $provasRealizadasBase = $this->getProvasRealizadasAlunoComBloco((int) $filho['id']);
        } catch (\Throwable $e) {
            $provasRealizadasBase = [];
        }

        try {
            $provasRealizadas = array_values(array_filter($provasRealizadasBase, function ($row) use ($anoLetivo, $bimestre) {
                $dataRef = $row['bloco_data_prova'] ?? $row['iniciado_em'] ?? null;
                if (empty($dataRef)) {
                    return $anoLetivo === null && $bimestre === null;
                }
                $ts = strtotime((string) $dataRef);
                if ($ts <= 0) {
                    return $anoLetivo === null && $bimestre === null;
                }
                if ($anoLetivo !== null && (int) date('Y', $ts) !== $anoLetivo) {
                    return false;
                }
                if ($bimestre !== null) {
                    $mes = (int) date('n', $ts);
                    $bim = (int) floor(($mes - 1) / 3) + 1;
                    if ($bim !== $bimestre) {
                        return false;
                    }
                }
                return true;
            }));
            $provasMatrizBlocos = $this->buildProvasMatrizPorBlocoAplicado($provasRealizadas);
        } catch (\Throwable $e) {
            $provasRealizadas = [];
            $provasMatrizBlocos = ['tabelas' => [], 'tem_dados' => false];
        }

        $notasLancamentoEventos = [];
        try {
            require_once __DIR__ . '/../../Models/Exams/ExamBlockManualGrade.php';
            $notasLancamentoEventosBase = (new ExamBlockManualGrade())->fetchNotasPorAluno((int) $filho['id']);
        } catch (\Throwable $e) {
            $notasLancamentoEventosBase = [];
        }
        $notasLancamentoEventos = array_values(array_filter($notasLancamentoEventosBase, function ($row) use ($anoLetivo, $bimestre) {
            $dataRef = $row['bloco_data_prova'] ?? $row['updated_at'] ?? null;
            if (empty($dataRef)) {
                return $anoLetivo === null && $bimestre === null;
            }
            $ts = strtotime((string) $dataRef);
            if ($ts <= 0) {
                return $anoLetivo === null && $bimestre === null;
            }
            if ($anoLetivo !== null && (int) date('Y', $ts) !== $anoLetivo) {
                return false;
            }
            if ($bimestre !== null) {
                $mes = (int) date('n', $ts);
                $bim = (int) floor(($mes - 1) / 3) + 1;
                if ($bim !== $bimestre) {
                    return false;
                }
            }
            return true;
        }));

        $boletinsGerados = [];
        $boletimObservacao = ['conteudo' => '', 'updated_at' => null];
        try {
            require_once __DIR__ . '/../../Models/System/BoletimConfig.php';
            $boletimCfg = new BoletimConfig();
            $boletimCfg->ensureSchema();
            $boletinsGerados = $boletimCfg->getGeneratedBoletinsByAluno((int) $filho['id'], 'pais');
            $boletimObsRow = $boletimCfg->getObservacaoCoordenacao((int) $filho['id']);
            if ($boletimObsRow) {
                $boletimObservacao = [
                    'conteudo' => (string) ($boletimObsRow['conteudo'] ?? ''),
                    'updated_at' => $boletimObsRow['updated_at'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            $boletinsGerados = [];
        }

        $boletinsNotas = [];
        $boletinsBoletim = [];
        foreach ((array) $boletinsGerados as $ev) {
            $exibirEm = strtolower((string) ($ev['exibir_em'] ?? 'boletim'));
            if (!in_array($exibirEm, ['boletim', 'notas'], true)) {
                $exibirEm = 'boletim';
            }

            // Filtro para boletins de notas/provas: usa data_fim/data_inicio; fallback em periodo_ref b1..b4.
            if ($anoLetivo !== null || $bimestre !== null) {
                $pass = false;
                $dataRef = $ev['data_fim'] ?? $ev['data_inicio'] ?? null;
                if (!empty($dataRef)) {
                    $ts = strtotime((string) $dataRef);
                    if ($ts > 0) {
                        $pass = true;
                        if ($anoLetivo !== null && (int) date('Y', $ts) !== $anoLetivo) {
                            $pass = false;
                        }
                        if ($pass && $bimestre !== null) {
                            $mes = (int) date('n', $ts);
                            $bim = (int) floor(($mes - 1) / 3) + 1;
                            if ($bim !== $bimestre) {
                                $pass = false;
                            }
                        }
                    }
                }
                if (!$pass && $bimestre !== null) {
                    $periodoRef = strtolower(trim((string) ($ev['periodo_ref'] ?? '')));
                    if (preg_match('/\bb([1-4])\b/', $periodoRef, $m)) {
                        $pass = ((int) $m[1] === $bimestre);
                    }
                }
                if (!$pass && ($anoLetivo !== null || $bimestre !== null)) {
                    continue;
                }
            }

            if ($exibirEm === 'notas') {
                $boletinsNotas[] = $ev;
            } else {
                $boletinsBoletim[] = $ev;
            }
        }

        $anosDisponiveis = [];
        foreach ((array) $provasRealizadasBase as $pr) {
            $d = $pr['bloco_data_prova'] ?? $pr['iniciado_em'] ?? null;
            if (!empty($d)) {
                $ts = strtotime((string) $d);
                if ($ts > 0) { $anosDisponiveis[(int) date('Y', $ts)] = true; }
            }
        }
        foreach ((array) $notasLancamentoEventosBase as $nl) {
            $d = $nl['bloco_data_prova'] ?? $nl['updated_at'] ?? null;
            if (!empty($d)) {
                $ts = strtotime((string) $d);
                if ($ts > 0) { $anosDisponiveis[(int) date('Y', $ts)] = true; }
            }
        }
        foreach ((array) $boletinsGerados as $bg) {
            $d = $bg['data_fim'] ?? $bg['data_inicio'] ?? null;
            if (!empty($d)) {
                $ts = strtotime((string) $d);
                if ($ts > 0) { $anosDisponiveis[(int) date('Y', $ts)] = true; }
            }
        }
        $anosDisponiveis = array_values(array_filter(array_map('intval', array_keys($anosDisponiveis))));
        rsort($anosDisponiveis);

        $data = [
            'title' => 'Notas do Filho - EducaTudo',
            'page_title' => 'Notas',
            'current_page' => 'notas',
            'filhos' => $filhos,
            'filho' => $filho,
            'user' => $user,
            'secao_notas' => $secao,
            'filtro_ano_letivo' => $anoLetivo,
            'filtro_bimestre' => $bimestre,
            'anos_disponiveis' => $anosDisponiveis,
            'provas_realizadas' => $provasRealizadas,
            'provas_matriz_blocos' => $provasMatrizBlocos,
            'notas_lancamento_eventos' => $notasLancamentoEventos,
            'boletins_gerados' => $boletinsGerados,
            'boletins_gerados_notas' => $boletinsNotas,
            'boletins_gerados_boletim' => $boletinsBoletim,
            'boletim_observacao' => $boletimObservacao,
        ];

        $this->viewWithLayout('parent', 'parents/notas-filho', $data);
    }
    
    /**
     * Jornadas de um filho
     */
    public function jornadasFilho($id)
    {
        $user = $this->auth->getUser();
        $filhos = $this->getFilhos();
        $filho = $this->getFilhoById((int)$id);
        
        if (!$filho) {
            $this->redirect('/pais/filhos');
        }
        
        $anoLetivo = isset($_GET['ano_letivo']) && $_GET['ano_letivo'] !== '' ? (int) $_GET['ano_letivo'] : null;
        $bimestre = isset($_GET['bimestre']) && $_GET['bimestre'] !== '' ? (int) $_GET['bimestre'] : null;

        $jornadasSvc = $this->parentJornadasService();
        $jornadas = $jornadasSvc->listarDetalhadas((int) $filho['id'], (int) ($filho['turma_id'] ?? 0), $anoLetivo, $bimestre);
        $kpisJornadas = $this->buildKpisJornadas($jornadas);
        $anosDisponiveis = $jornadasSvc->anosDisponiveis((int) $filho['id'], (int) ($filho['turma_id'] ?? 0));
        
        $data = [
            'title' => 'Jornadas do Filho - EducaTudo',
            'page_title' => 'Histórico de Jornadas',
            'current_page' => 'jornadas',
            'filhos' => $filhos,
            'filho' => $filho,
            'user' => $user,
            'jornadas' => $jornadas,
            'kpis_jornadas' => $kpisJornadas,
            'filtro_ano_letivo' => $anoLetivo,
            'filtro_bimestre' => $bimestre,
            'anos_disponiveis' => $anosDisponiveis,
        ];
        
        $this->viewWithLayout('parent', 'parents/jornadas-filho', $data);
    }
    
    /**
     * Redações de um filho
     */
    public function redacoesFilho($id)
    {
        $user = $this->auth->getUser();
        $filhos = $this->getFilhos();
        $filho = $this->getFilhoById((int)$id);
        
        if (!$filho) {
            $this->redirect('/pais/filhos');
        }
        
        $redacoes = $this->db->fetchAll(
            "SELECT * FROM redacoes 
             WHERE aluno_id = :aluno_id
             ORDER BY created_at DESC",
            ['aluno_id' => $filho['id']]
        );
        
        $data = [
            'title' => 'Redações do Filho - EducaTudo',
            'page_title' => 'Redações do aluno',
            'current_page' => 'redacoes',
            'filhos' => $filhos,
            'filho' => $filho,
            'user' => $user,
            'redacoes' => $redacoes
        ];
        
        $this->viewWithLayout('parent', 'parents/redacoes-filho', $data);
    }
    
    /**
     * Relatórios de um filho
     */
    public function relatoriosFilho($id)
    {
        $user = $this->auth->getUser();
        $filhos = $this->getFilhos();
        $filho = $this->getFilhoById((int)$id);
        
        if (!$filho) {
            $this->redirect('/pais/filhos');
        }
        
        $relatorios = $this->db->fetchAll(
            "SELECT * FROM relatorios 
             WHERE aluno_id = :aluno_id
             ORDER BY created_at DESC",
            ['aluno_id' => $filho['id']]
        );
        
        $data = [
            'title' => 'Relatórios do Filho - EducaTudo',
            'page_title' => 'Relatórios do aluno',
            'current_page' => 'relatorios',
            'filhos' => $filhos,
            'filho' => $filho,
            'user' => $user,
            'relatorios' => $relatorios
        ];
        
        $this->viewWithLayout('parent', 'parents/relatorios-filho', $data);
    }

    /**
     * Planos de aula do filho (turma do aluno)
     */
    public function planoAulaFilho($id)
    {
        $user = $this->auth->getUser();
        $filhos = $this->getFilhos();
        $filho = $this->getFilhoById((int)$id);
        
        if (!$filho) {
            $this->redirect('/pais/filhos');
        }
        
        $planosAula = [];
        if (!empty($filho['turma_id'])) {
            $anoAtual = date('Y');
            $planosAula = $this->db->fetchAll(
                "SELECT pa.*, 
                        p.nome as professor_nome,
                        m.nome as materia_nome,
                        t.nome as turma_nome,
                        t.serie as turma_serie
                 FROM planos_aula pa
                 LEFT JOIN professores p ON pa.professor_id = p.id
                 LEFT JOIN materias m ON pa.materia_id = m.id
                 LEFT JOIN turmas t ON pa.turma_id = t.id
                 WHERE pa.turma_id = :turma_id 
                     AND pa.deleted_at IS NULL
                     AND YEAR(pa.created_at) = :ano_atual
                 ORDER BY pa.created_at DESC",
                [
                    'turma_id' => $filho['turma_id'],
                    'ano_atual' => $anoAtual
                ]
            );
            foreach ($planosAula as &$plano) {
                $plano['data_exibicao'] = $this->formatarDataPlanoAula($plano);
            }
            unset($plano);
        }
        
        $data = [
            'title' => 'Plano de Aula do aluno - EducaTudo',
            'page_title' => 'Plano de Aula',
            'current_page' => 'plano-aula',
            'filhos' => $filhos,
            'filho' => $filho,
            'user' => $user,
            'planos_aula' => $planosAula
        ];
        
        $this->viewWithLayout('parent', 'parents/plano-aula-filho', $data);
    }

    /**
     * Visualizar um plano de aula do filho (pai)
     */
    public function visualizarPlanoAulaFilho($id, $planoId)
    {
        $user = $this->auth->getUser();
        $filhos = $this->getFilhos();
        $filho = $this->getFilhoById((int)$id);
        if (!$filho) {
            $this->redirect('/pais/filhos');
            return;
        }
        $planoId = (int) $planoId;
        $plano = $this->db->fetch(
            "SELECT pa.*, p.nome as professor_nome, m.nome as materia_nome, t.nome as turma_nome
             FROM planos_aula pa
             LEFT JOIN professores p ON pa.professor_id = p.id
             LEFT JOIN materias m ON pa.materia_id = m.id
             LEFT JOIN turmas t ON pa.turma_id = t.id
             WHERE pa.id = :plano_id AND pa.deleted_at IS NULL",
            ['plano_id' => $planoId]
        );
        if (!$plano || (int)$plano['turma_id'] !== (int)$filho['turma_id']) {
            $this->redirect('/pais/filhos/' . (int)$id . '/plano-aula');
            return;
        }
        if (!empty($plano['recursos_lista'])) {
            $plano['recursos_lista'] = json_decode($plano['recursos_lista'], true);
        }
        $data = [
            'title' => 'Visualizar Plano de Aula - EducaTudo',
            'page_title' => 'Visualizar Plano de Aula',
            'current_page' => 'plano-aula',
            'filhos' => $filhos,
            'filho' => $filho,
            'user' => $user,
            'plano' => $plano,
            'filho_id' => (int)$id
        ];
        $this->viewWithLayout('parent', 'parents/plano-aula-visualizar', $data);
    }

    /**
     * Exportar PDF do plano de aula do filho (pai)
     */
    public function pdfPlanoAulaFilho($id, $planoId)
    {
        $user = $this->auth->getUser();
        $filho = $this->getFilhoById((int)$id);
        if (!$filho) {
            $this->redirect('/pais/filhos');
            return;
        }
        $planoId = (int) $planoId;
        $plano = $this->db->fetch(
            "SELECT * FROM planos_aula WHERE id = :plano_id AND deleted_at IS NULL",
            ['plano_id' => $planoId]
        );
        if (!$plano || (int)$plano['turma_id'] !== (int)$filho['turma_id']) {
            $this->redirect('/pais/filhos/' . (int)$id . '/plano-aula');
            return;
        }
        if (!empty($plano['recursos_lista'])) {
            $plano['recursos_lista'] = json_decode($plano['recursos_lista'], true);
        }
        $camposTexto = ['conteudo', 'conteudo_lista', 'objetivos', 'objetivos_lista', 'recursos', 'avaliacao', 'observacoes'];
        foreach ($camposTexto as $campo) {
            if (!empty($plano[$campo])) {
                $plano[$campo] = html_entity_decode(strip_tags($plano[$campo]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $plano[$campo] = preg_replace('/\s+/', ' ', trim($plano[$campo]));
            }
        }
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $data = [
            'title' => 'Plano de Aula - ' . ($plano['titulo'] ?? ''),
            'plano' => $plano,
            'logo_url' => LayoutHelper::getLogoUrl(),
            'logo_horizontal_url' => LayoutHelper::getLogoHorizontalUrl(),
            'system_title' => LayoutHelper::getSystemTitle()
        ];
        $this->view('teacher/planos-aula/pdf', $data);
    }

    private function formatarDataPlanoAula(array $plano): string
    {
        if (!empty($plano['data_aula'])) {
            $dataAula = $plano['data_aula'];
            if (is_string($dataAula)) {
                $dec = json_decode($dataAula, true);
                $dataAula = is_array($dec) ? $dec : [$dataAula];
            }
            if (is_array($dataAula) && !empty($dataAula)) {
                $datas = array_filter($dataAula);
                if (!empty($datas)) {
                    $formatadas = [];
                    foreach ($datas as $d) {
                        $formatadas[] = date('d/m/Y', strtotime($d));
                    }
                    return implode(', ', $formatadas);
                }
            }
            return date('d/m/Y', strtotime($plano['data_aula']));
        }
        if (!empty($plano['data_inicio']) && !empty($plano['data_fim'])) {
            return date('d/m/Y', strtotime($plano['data_inicio'])) . ' - ' . date('d/m/Y', strtotime($plano['data_fim']));
        }
        if (!empty($plano['data_inicio'])) {
            return 'A partir de ' . date('d/m/Y', strtotime($plano['data_inicio']));
        }
        return '';
    }

    /**
     * Mensagens (pais)
     */
    public function mensagens()
    {
        $user = $this->auth->getUser();
        $filhos = $this->getFilhos();
        $filho = $this->getFilhoSelecionado($filhos);
        $data = [
            'title' => 'Mensagens - EducaTudo',
            'page_title' => 'Mensagens',
            'current_page' => 'mensagens',
            'filhos' => $filhos,
            'filho' => $filho,
            'user' => $user
        ];
        $this->viewWithLayout('parent', 'parents/mensagens', $data);
    }

    /**
     * Notificações (pais)
     */
    public function notificacoes()
    {
        $user = $this->auth->getUser();
        $filhos = $this->getFilhos();
        $filho = $this->getFilhoSelecionado($filhos);
        $data = [
            'title' => 'Notificações - EducaTudo',
            'page_title' => 'Notificações',
            'current_page' => 'notificacoes',
            'filhos' => $filhos,
            'filho' => $filho,
            'user' => $user
        ];
        $this->viewWithLayout('parent', 'parents/notificacoes', $data);
    }

    /**
     * Enviar mensagem (POST)
     */
    public function enviarMensagem()
    {
        $this->redirect('/pais/mensagens');
    }

    public function alterarSenhaObrigatoria()
    {
        $user = $this->auth->getUser();
        $responsavel = $this->db->fetch(
            "SELECT id, force_password_change FROM responsaveis WHERE id = :id AND ativo = 1",
            ['id' => $user['id'] ?? 0]
        );
        if (!$responsavel || (int)($responsavel['force_password_change'] ?? 0) !== 1) {
            $this->redirect('/pais/dashboard');
            return;
        }

        $data = [
            'title' => 'Alterar Senha - Portal dos Pais',
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'dashboard'
        ];
        $this->viewWithLayout('parent', 'parents/alterar-senha-obrigatoria', $data);
    }

    public function processarAlteracaoSenha()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        try {
            $user = $this->auth->getUser();
            $novaSenha = trim((string)($_POST['nova_senha'] ?? ''));
            $confirmarSenha = trim((string)($_POST['confirmar_senha'] ?? ''));

            if ($novaSenha === '' || $confirmarSenha === '') {
                throw new Exception('Todos os campos são obrigatórios');
            }
            if ($novaSenha !== $confirmarSenha) {
                throw new Exception('As senhas não coincidem');
            }
            $auth = new Auth();
            $passwordValidation = $auth->validateStrongPassword($novaSenha);
            if ($passwordValidation !== true) {
                throw new Exception($passwordValidation);
            }

            $responsavel = $this->db->fetch(
                "SELECT id FROM responsaveis WHERE id = :id AND ativo = 1",
                ['id' => $user['id'] ?? 0]
            );
            if (!$responsavel) {
                throw new Exception('Responsável não encontrado');
            }

            $this->db->update(
                "UPDATE responsaveis
                 SET senha_hash = :senha_hash,
                     force_password_change = 0
                 WHERE id = :id",
                ['senha_hash' => password_hash($novaSenha, PASSWORD_DEFAULT), 'id' => $responsavel['id']]
            );

            $this->json([
                'success' => true,
                'message' => 'Senha alterada com sucesso!',
                'redirect' => URL . '/pais/dashboard'
            ]);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function financeiroFilho($id)
    {
        $alunoId = (int)$id;
        $user    = $this->auth->getUser();

        // Validar que esse pai tem acesso a esse aluno
        $filho = $this->db->fetch(
            "SELECT a.*, r.nome AS responsavel_nome FROM alunos a
             INNER JOIN responsaveis r ON r.aluno_id = a.id
             WHERE a.id = ? AND r.usuario_id = ?",
            [$alunoId, $user['id']]
        );
        if (!$filho) { $this->redirect('/pais/filhos'); return; }

        // Parcelas do contrato (mensalidades, matrícula, etc.)
        $parcelas = [];
        if ($this->hasTable('finance_installments') && $this->hasTable('finance_contracts')) {
            $parcelas = $this->db->fetchAll(
                "SELECT fi.id, fi.num_parcela, fi.descricao, fi.valor_cobrado AS valor_total, fi.data_vencimento,
                        fi.data_pagamento, fi.status, fi.forma_pagamento,
                        'parcela' AS tipo
                 FROM finance_installments fi
                 INNER JOIN finance_contracts fc ON fc.id = fi.contract_id
                 WHERE fc.aluno_id = ? AND fi.status <> 'cancelado'
                 ORDER BY fi.data_vencimento ASC",
                [$alunoId]
            ) ?: [];
        }

        // Cobranças avulsas (passeios, uniformes, eventos, etc.)
        $cobranças = [];
        if ($this->hasTable('finance_charges')) {
            $cobranças = $this->db->fetchAll(
                "SELECT id, categoria, descricao AS descricao, valor AS valor_total,
                        data_vencimento, data_pagamento, status, forma_pagamento,
                        'cobrança' AS tipo
                 FROM finance_charges
                 WHERE aluno_id = ? AND status <> 'cancelado'
                 ORDER BY data_vencimento ASC",
                [$alunoId]
            ) ?: [];
        }

        // Mesclar e ordenar por vencimento
        $faturas = array_merge($parcelas, $cobranças);
        usort($faturas, fn($a, $b) => strcmp($a['data_vencimento'] ?? '', $b['data_vencimento'] ?? ''));

        $this->viewWithLayout('parent', 'parents/financeiro-filho', [
            'title'   => 'Financeiro — ' . $filho['nome'],
            'user'    => $user,
            'filho'   => $filho,
            'faturas' => $faturas,
        ]);
    }

    private function hasTable(string $table): bool
    {
        return (bool)$this->db->fetch(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$table]
        );
    }
    
    /**
     * Redireciona para o dashboard correto baseado no tipo
     */
    private function redirectToCorrectDashboard($tipo)
    {
        switch ($tipo) {
            case 'aluno':
                $this->redirect('/dashboard');
                break;
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'admin_escola':
                $this->redirect('/admin/dashboard');
                break;
            default:
                $this->redirect('/pais');
        }
    }

    public function calendarioLetivo(): void
    {
        require_once __DIR__ . '/../../Services/SchoolCalendarService.php';
        $ano = (int) ($_GET['ano'] ?? date('Y'));
        if ($ano < 2000 || $ano > 2100) $ano = (int) date('Y');
        $service = new SchoolCalendarService($this->db);
        $cfg     = $service->getAno($ano);
        $todos   = $cfg ? $service->eventos((int) $cfg['id']) : [];
        $eventos = array_values(array_filter($todos, fn($e) => (int) ($e['visivel_pais'] ?? 0) === 1));
        $this->viewWithLayout('parent', 'parents/calendario-letivo', [
            'title'   => 'Calendário Letivo - EducaTudo',
            'user'    => $this->auth->getUser(),
            'ano'     => $ano,
            'eventos' => $eventos,
        ]);
    }

    /**
     * Mural de Recados do filho — somente leitura, mesmos recados que o aluno vê
     * (recados publicados para "todos" ou para a turma dele). O pai não marca como visto:
     * mural_recados_vistos só rastreia leitura por aluno_id, aqui é só exibido como informação.
     */
    public function muralRecadosFilho($id)
    {
        $user = $this->auth->getUser();
        $filhos = $this->getFilhos();
        $filho = $this->getFilhoById((int) $id);

        if (!$filho) {
            $this->redirect('/pais/filhos');
        }

        $turmaId = isset($filho['turma_id']) && $filho['turma_id'] !== '' ? (int) $filho['turma_id'] : 0;
        $params = [];
        $sql = "SELECT r.id, r.titulo, r.conteudo, r.data_publicacao, r.data_sai_mural, r.autor_tipo, r.autor_id,
                CASE WHEN r.autor_tipo = 'professor' THEN (SELECT p.nome FROM professores p WHERE p.id = r.autor_id LIMIT 1) ELSE 'Admin' END as autor_nome
                FROM mural_recados r
                WHERE (r.enviar_para_todos = 1" . ($turmaId > 0 ? " OR EXISTS (
                    SELECT 1 FROM mural_recados_turmas rt WHERE rt.mural_recado_id = r.id AND rt.turma_id = :turma_id
                  )" : "") . ")
                AND (CURDATE() <= r.data_sai_mural)
                ORDER BY r.data_publicacao DESC";
        if ($turmaId > 0) {
            $params['turma_id'] = $turmaId;
        }
        $recados = $this->db->fetchAll($sql, $params);

        foreach ($recados as &$recado) {
            $recado['ja_visto'] = (bool) $this->db->fetch(
                "SELECT 1 FROM mural_recados_vistos WHERE mural_recado_id = :rid AND aluno_id = :aid",
                ['rid' => $recado['id'], 'aid' => $filho['id']]
            );
        }
        unset($recado);

        $this->viewWithLayout('parent', 'parents/mural-recados-filho', [
            'title' => 'Mural de Recados do Filho - EducaTudo',
            'page_title' => 'Mural de Recados',
            'current_page' => 'mural-recados',
            'filhos' => $filhos,
            'filho' => $filho,
            'user' => $user,
            'recados' => $recados,
        ]);
    }

    /** Instancia os Models/Service do módulo Drive (compartilhados com aluno/professor) */
    private function driveModels(): array
    {
        require_once __DIR__ . '/../../Modulos/drive/Models/DriveItem.php';
        require_once __DIR__ . '/../../Modulos/drive/Models/DriveShare.php';
        require_once __DIR__ . '/../../Modulos/drive/Services/DriveStorageService.php';
        return [new DriveItem(), new DriveShare(), new DriveStorageService($this->config)];
    }

    private function driveEnabled(): bool
    {
        require_once __DIR__ . '/../../Core/FeatureGate.php';
        return FeatureGate::isModuleEnabled('drive');
    }

    /**
     * Drive do filho — somente leitura (visualizar/baixar), mesmos itens que o aluno vê
     * (arquivos/pastas dele + itens compartilhados com ele). Sem upload/criar pasta/
     * renomear/excluir/compartilhar — reaproveita a view do aluno com canEdit=false.
     */
    public function driveFilho($id)
    {
        $filhos = $this->getFilhos();
        $filho = $this->getFilhoById((int) $id);
        if (!$filho) {
            $this->redirect('/pais/filhos');
        }
        if (!$this->driveEnabled()) {
            $this->redirect('/pais/filhos/' . (int) $filho['id']);
        }

        [$driveItem, $driveShare] = $this->driveModels();
        $alunoId = (int) $filho['id'];
        $baseUrl = URL . '/pais/filhos/' . $alunoId . '/drive';

        $this->viewWithLayout('parent', 'aluno/drive/index', [
            'title' => 'Arquivos do Filho - EducaTudo',
            'current_page' => 'drive',
            'filhos' => $filhos,
            'filho' => $filho,
            'user' => $this->auth->getUser(),
            'baseUrl' => $baseUrl,
            'items' => $driveItem->listChildren($alunoId, 'aluno', null),
            'shared' => $driveShare->listSharedWithMe($alunoId, 'aluno'),
            'breadcrumb' => [['id' => null, 'name' => 'Arquivos de ' . ($filho['nome'] ?? 'Aluno')]],
            'currentFolderId' => null,
            'canEdit' => false,
            'flash' => null,
        ]);
    }

    public function driveFilhoFolder($id, $folderId)
    {
        $filhos = $this->getFilhos();
        $filho = $this->getFilhoById((int) $id);
        if (!$filho) {
            $this->redirect('/pais/filhos');
        }
        if (!$this->driveEnabled()) {
            $this->redirect('/pais/filhos/' . (int) $filho['id']);
        }

        [$driveItem, $driveShare] = $this->driveModels();
        $alunoId = (int) $filho['id'];
        $baseUrl = URL . '/pais/filhos/' . $alunoId . '/drive';
        $folderId = (int) $folderId;

        $folder = $driveItem->getById($folderId);
        if (!$folder || $folder['type'] !== 'folder' || !$driveShare->canAccess($folderId, $alunoId, 'aluno')) {
            $this->redirect('/pais/filhos/' . $alunoId . '/drive');
        }

        $isOwner = (int) $folder['owner_id'] === $alunoId && $folder['owner_type'] === 'student';
        $items = $isOwner
            ? $driveItem->listChildren($alunoId, 'aluno', $folderId)
            : $driveItem->listChildrenByParentId($folderId);

        $this->viewWithLayout('parent', 'aluno/drive/index', [
            'title' => $folder['name'] . ' - Arquivos do Filho - EducaTudo',
            'current_page' => 'drive',
            'filhos' => $filhos,
            'filho' => $filho,
            'user' => $this->auth->getUser(),
            'baseUrl' => $baseUrl,
            'items' => $items,
            'shared' => [],
            'breadcrumb' => $driveItem->getBreadcrumb($folderId),
            'currentFolderId' => $folderId,
            'canEdit' => false,
            'flash' => null,
        ]);
    }

    public function driveFilhoView($id, $itemId)
    {
        $filho = $this->getFilhoById((int) $id);
        if (!$filho) {
            $this->redirect('/pais/filhos');
        }
        if (!$this->driveEnabled()) {
            $this->redirect('/pais/filhos/' . (int) $filho['id']);
        }

        [$driveItem, $driveShare] = $this->driveModels();
        $alunoId = (int) $filho['id'];
        $baseUrl = URL . '/pais/filhos/' . $alunoId . '/drive';
        $itemId = (int) $itemId;

        $item = $driveItem->getById($itemId);
        if (!$item || $item['type'] !== 'file' || !$driveShare->canAccess($itemId, $alunoId, 'aluno')) {
            $this->redirect('/pais/filhos/' . $alunoId . '/drive');
        }

        $mime = $item['mime_type'] ?? '';
        $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
        $viewable = in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)
            || strpos($mime, 'pdf') !== false
            || strpos($mime, 'image/') === 0;

        $this->viewWithLayout('parent', 'aluno/drive/view', [
            'title' => $item['name'] . ' - Arquivos do Filho - EducaTudo',
            'baseUrl' => $baseUrl,
            'item' => $item,
            'parentId' => $item['parent_id'],
            'breadcrumb' => $driveItem->getBreadcrumb($itemId),
            'viewable' => $viewable,
        ]);
    }

    public function driveFilhoDownload($id, $itemId)
    {
        $filho = $this->getFilhoById((int) $id);
        if (!$filho || !$this->driveEnabled()) {
            $this->redirect('/pais/filhos');
        }

        [$driveItem, $driveShare, $driveStorage] = $this->driveModels();
        $alunoId = (int) $filho['id'];
        $itemId = (int) $itemId;

        $item = $driveItem->getById($itemId);
        if (!$item || $item['type'] !== 'file' || !$driveShare->canAccess($itemId, $alunoId, 'aluno')) {
            $this->redirect('/pais/filhos/' . $alunoId . '/drive');
        }

        $name = $item['name'];
        $key = $item['file_path'];

        if ($driveStorage->isS3()) {
            $url = $driveStorage->getDownloadUrl($key, $name);
            if ($url) {
                header('Location: ' . $url);
                exit;
            }
            $this->redirect('/pais/filhos/' . $alunoId . '/drive');
        }

        $path = $driveStorage->getLocalPath($key);
        if (!$path || !file_exists($path) || !is_readable($path)) {
            $this->redirect('/pais/filhos/' . $alunoId . '/drive');
        }

        header('Content-Type: ' . ($item['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . addslashes($name) . '"');
        header('Content-Length: ' . (int) $item['file_size']);
        readfile($path);
        exit;
    }

    public function driveFilhoServe($id, $itemId)
    {
        $filho = $this->getFilhoById((int) $id);
        if (!$filho || !$this->driveEnabled()) {
            http_response_code(404);
            exit;
        }

        [$driveItem, $driveShare, $driveStorage] = $this->driveModels();
        $alunoId = (int) $filho['id'];
        $itemId = (int) $itemId;

        $item = $driveItem->getById($itemId);
        if (!$item || $item['type'] !== 'file') {
            http_response_code(404);
            exit;
        }
        if (!$driveShare->canAccess($itemId, $alunoId, 'aluno')) {
            http_response_code(403);
            exit;
        }

        $name = $item['name'];
        $key = $item['file_path'];
        $mime = $item['mime_type'] ?: 'application/octet-stream';

        if ($driveStorage->isS3()) {
            if ($driveStorage->streamInline($key, $name, $mime)) {
                exit;
            }
            http_response_code(502);
            exit;
        }

        $path = $driveStorage->getLocalPath($key);
        if (!$path || !file_exists($path) || !is_readable($path)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . addslashes($name) . '"');
        header('Content-Length: ' . (int) $item['file_size']);
        readfile($path);
        exit;
    }
}
}
