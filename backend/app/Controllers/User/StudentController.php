<?php

if (!class_exists('StudentController')) {
    class StudentController extends BaseController
{
    private $authManager;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        
        // Verifica se é aluno (o middleware Auth já verificou se está logado)
        $user = $this->authManager->getUser();
        if ($user && $user['tipo'] !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
    }
    
    private function redirectToCorrectDashboard($tipo)
    {
        switch ($tipo) {
            case 'admin_escola':
                $this->redirect('/admin/dashboard');
                break;
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect(URL . '/');
        }
    }

    private function normalizarTextoBoletim(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        if ($texto === '') {
            return '';
        }

        $map = [
            'á' => 'a',
            'à' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'é' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
            'ª' => 'a',
            'º' => 'o',
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
                return [
                    'key' => 'avaliacao_diagnostica',
                    'label' => 'Avaliação Diagnóstica',
                    'ord_primary' => 10,
                    'ord_secondary' => 0,
                ];
            }

            if (preg_match('/\bbloco\s*[:\-]?\s*b(?:\s*(?:1|1a|1o|primeira|2|2a|2o|segunda))?\b/u', $texto)) {
                return [
                    'key' => 'bloco_b',
                    'label' => 'Bloco B',
                    'ord_primary' => 66,
                    'ord_secondary' => 0,
                ];
            }

            if (preg_match('/\bbloco\s*[:\-]?\s*([a-z])\b/u', $texto, $m)) {
                $letra = strtoupper($m[1]);
                return [
                    'key' => 'letra_' . $letra,
                    'label' => 'Bloco ' . $letra,
                    'ord_primary' => ord($letra),
                    'ord_secondary' => 0,
                ];
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

            return [
                'key' => 'bloco_' . $bid,
                'label' => $label,
                'ord_primary' => 200000,
                'ord_secondary' => $bid,
            ];
        }

        return [
            'key' => 'sem_bloco',
            'label' => 'Sem bloco',
            'ord_primary' => 500000,
            'ord_secondary' => 0,
        ];
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

            if ($blocoId > 0) {
                $colKey = 'b:' . $blocoId;
            } else {
                $dia = $tsColuna > 0 ? date('Y-m-d', $tsColuna) : 'sem_data';
                $colKey = 'd:' . $dia;
            }

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
            } else {
                $colCandidates[$colKey]['sort_ts'] = min(
                    (int) $colCandidates[$colKey]['sort_ts'],
                    $tsColuna > 0 ? $tsColuna : PHP_INT_MAX
                );
                if ($colCandidates[$colKey]['data_label'] === '—' && $tsColuna > 0) {
                    $colCandidates[$colKey]['data_label'] = date('d/m/Y', $tsColuna);
                }
                if ($colCandidates[$colKey]['bloco_titulo'] === '' && !empty($row['bloco_titulo'])) {
                    $colCandidates[$colKey]['bloco_titulo'] = trim((string) $row['bloco_titulo']);
                }
                if (($colCandidates[$colKey]['bloco_modelo_id'] ?? 0) <= 0 && !empty($row['bloco_modelo_id'])) {
                    $colCandidates[$colKey]['bloco_modelo_id'] = (int) $row['bloco_modelo_id'];
                }
                if (($colCandidates[$colKey]['bloco_modelo_nome'] ?? '') === '' && !empty($row['bloco_modelo_nome'])) {
                    $colCandidates[$colKey]['bloco_modelo_nome'] = trim((string) $row['bloco_modelo_nome']);
                }
            }

            $provaTit = trim((string) ($row['prova_titulo'] ?? ''));
            if ($provaTit !== '' && $colCandidates[$colKey]['sample_prova_titulo'] === '') {
                $colCandidates[$colKey]['sample_prova_titulo'] = $provaTit;
            }
            if ($colCandidates[$colKey]['bloco_titulo'] === '' && $provaTit !== '') {
                if ($colCandidates[$colKey]['sample_prova_titulo'] === '') {
                    $colCandidates[$colKey]['sample_prova_titulo'] = $provaTit;
                }
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
            usort($colsGrupo, function ($a, $b) {
                $aTs = isset($a['data_label']) && $a['data_label'] !== '—' ? strtotime(str_replace('/', '-', $a['data_label'])) : PHP_INT_MAX;
                $bTs = isset($b['data_label']) && $b['data_label'] !== '—' ? strtotime(str_replace('/', '-', $b['data_label'])) : PHP_INT_MAX;
                if ($aTs === $bTs) {
                    return strcmp((string) ($a['key'] ?? ''), (string) ($b['key'] ?? ''));
                }
                return $aTs <=> $bTs;
            });

            $colsFinal = [];
            $ord = 1;
            foreach ($colsGrupo as $col) {
                $col['titulo'] = 'S' . $ord;
                $ord++;
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
                    'totais' => [
                        'q' => $tq,
                        'acertos' => $ta,
                        'erros' => max(0, $tq - $ta),
                    ],
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

        return [
            'tabelas' => array_values($tabelas),
            'tem_dados' => !empty($tabelas),
        ];
    }

    /**
     * Quadro S1–S8 do módulo notas-semanais (opcional por escola).
     *
     * @return array<string, mixed>
     */
    private function montarQuadroNotasSemanais(int $alunoId): array
    {
        $vazio = ['modulo_ativo' => false, 'tem_dados' => false, 'tabelas' => []];
        try {
            if (!class_exists('LayoutHelper', false)) {
                require_once __DIR__ . '/../../Core/LayoutHelper.php';
            }
            if (!LayoutHelper::isModuleEnabled('notas_semanais')) {
                return $vazio;
            }
            $serviceFile = __DIR__ . '/../../Modulos/notas-semanais/Services/NotasSemanaisService.php';
            if (!is_file($serviceFile)) {
                return $vazio;
            }
            require_once $serviceFile;
            $bimestre = isset($_GET['bimestre']) ? (int) $_GET['bimestre'] : null;
            $ano = isset($_GET['ano']) ? (int) $_GET['ano'] : null;
            return (new NotasSemanaisService())->montarQuadroAluno(
                $alunoId,
                ($bimestre !== null && $bimestre >= 1 && $bimestre <= 4) ? $bimestre : null,
                ($ano !== null && $ano > 2000) ? $ano : null
            );
        } catch (\Throwable $e) {
            if (class_exists('Logger', false)) {
                Logger::error('Quadro notas semanais: ' . $e->getMessage(), [
                    'aluno_id' => $alunoId,
                    'exception' => $e,
                ]);
            } else {
                error_log('Quadro notas semanais: ' . $e->getMessage());
            }
            return $vazio;
        }
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

        try { $hasProvasMateriaId = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas LIKE 'materia_id'")); } catch (\Exception $e) {}
        try { $hasRealizacoesMateria = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'materia'")); } catch (\Exception $e) {}
        try { $hasRealizacoesDisciplina = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'disciplina'")); } catch (\Exception $e) {}
        try { $hasRealizacoesAreaConhecimento = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'area_conhecimento'")); } catch (\Exception $e) {}
        try { $hasQuestoesTotal = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'questoes_total'")); } catch (\Exception $e) {}
        try { $hasTotalQuestoes = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'total_questoes'")); } catch (\Exception $e) {}
        try { $hasQtdQuestoes = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'qtd_questoes'")); } catch (\Exception $e) {}
        try { $hasQuestoesCorretas = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'questoes_corretas'")); } catch (\Exception $e) {}
        try { $hasAcertos = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'acertos'")); } catch (\Exception $e) {}
        try { $hasQtdAcertos = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_realizacoes LIKE 'qtd_acertos'")); } catch (\Exception $e) {}
        try { $hasProvasRespostasCorreta = !empty($this->db->fetchAll("SHOW COLUMNS FROM provas_respostas LIKE 'correta'")); } catch (\Exception $e) {}

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

        $sqlProvasAlunoComBloco =
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
        $sqlProvasAlunoSemBloco =
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
            return $this->db->fetchAll($sqlProvasAlunoComBloco, ['id' => $alunoId]);
        } catch (\Exception $eBloco) {
            return $this->db->fetchAll($sqlProvasAlunoSemBloco, ['id' => $alunoId]);
        }
    }

    /**
     * Página de alteração obrigatória de senha
     */
    public function alterarSenhaObrigatoria()
    {
        $user = $this->authManager->getUser();
        
        // Verificar se realmente precisa alterar senha
        $aluno = $this->db->fetch(
            "SELECT * FROM alunos WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        if (!$aluno || !password_verify('123456', $aluno['senha_hash'])) {
            // Se não tem senha padrão, redireciona para dashboard
            $this->redirect(URL . '/dashboard');
        }
        
        $data = [
            'title' => 'Alterar Senha - EducaTudo',
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken()
        ];
        
        $this->viewWithLayout('student', 'student/alterar-senha-obrigatoria', $data);
    }

    /**
     * Processa alteração obrigatória de senha
     */
    public function processarAlteracaoSenha()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $user = $this->authManager->getUser();
            $novaSenha = $_POST['nova_senha'] ?? '';
            $confirmarSenha = $_POST['confirmar_senha'] ?? '';
            
            // Validações
            if (empty($novaSenha) || empty($confirmarSenha)) {
                throw new Exception('Todos os campos são obrigatórios');
            }
            
            if (strlen($novaSenha) < 6) {
                throw new Exception('A senha deve ter pelo menos 6 caracteres');
            }
            
            if ($novaSenha !== $confirmarSenha) {
                throw new Exception('As senhas não coincidem');
            }
            
            if ($novaSenha === '123456') {
                throw new Exception('A nova senha não pode ser a senha padrão');
            }
            
            // Atualizar senha no banco
            $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
            
            $result = $this->db->query(
                "UPDATE alunos SET senha_hash = :senha_hash WHERE id = :user_id",
                ['senha_hash' => $senhaHash, 'user_id' => $user['id']]
            );
            
            if ($result === false) {
                throw new Exception('Erro ao atualizar senha');
            }
            
            $this->json([
                'success' => true, 
                'message' => 'Senha alterada com sucesso!',
                'redirect' => URL . '/dashboard'
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function dashboard()
    {
        $inicioTotal = microtime(true);
        $user = $this->authManager->getUser();

        if (!class_exists('ContextoAluno')) {
            require_once __DIR__ . '/../../Core/ContextoAluno.php';
        }

        $aluno = $this->medirDashboard('contexto_aluno', function () {
            return ContextoAluno::resolver($this->db);
        });

        if (!$aluno) {
            $this->redirect('/logout');
        }

        $alunoId = (int) $aluno['id'];
        $turmaId = (int) ($aluno['turma_id'] ?? 0);

        $turmasCursosSelect = ContextoAluno::getTurmasCursosSelect($this->db);

        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $jornadas_habilitadas = LayoutHelper::isModuleEnabled('jornadas');
        $dashboardAcaoCards = $this->isDashboardAcaoCardsLayout();
        $primaryColor = LayoutHelper::get('primary_color', $this->config['school']['colors']['primary'] ?? '#3b82f6');
        $jornadasAtivasLayout = LayoutHelper::get('jornadas_ativas_layout', 'default');
        $onboardingCompletado = !empty($aluno['onboarding_completado']);

        $dashboardSliders = $this->medirDashboard('sliders', function () {
            return $this->parseDashboardSliders();
        });

        $agendaAno = (int) date('Y');
        $agendaMes = (int) date('n');

        if ($dashboardAcaoCards) {
            $jornadas_ativas = [];
            if ($jornadas_habilitadas && $turmaId > 0) {
                $jornadasAtivasTodas = $this->medirDashboard('jornadas', function () use ($aluno, $turmaId) {
                    return $this->getActiveJourneys($turmaId, (int) $aluno['id']);
                });
                $jornadas_ativas = array_slice($jornadasAtivasTodas, 0, 3);
            }

            $data = [
                'title' => 'Dashboard - EducaTudo',
                'user' => $user,
                'aluno' => $aluno,
                'stats' => null,
                'aulas_online' => [],
                'jornadas_ativas' => $jornadas_ativas,
                'jornadas_habilitadas' => $jornadas_habilitadas,
                'jornadas_ativas_layout' => $jornadasAtivasLayout,
                'redacoes_recentes' => [],
                'limites_diarios' => null,
                'onboarding_completado' => $onboardingCompletado,
                'onboarding' => null,
                'mural_recados_nao_vistos' => [],
                'mural_recados_count' => 0,
                'primary_color' => $primaryColor,
                'dashboard_sliders' => $dashboardSliders,
                'current_page' => 'dashboard',
                'csrf_token' => $this->generateCsrfToken(),
                'turmas_cursos_select' => $turmasCursosSelect,
                'jornadas_abertas_count' => null,
                'mural_recados_nao_lidos_count' => null,
                'provas_disponiveis_agora_count' => null,
                'dashboard_acao_cards' => true,
                'jornada_redacao_pendentes_count' => null,
                'agenda_eventos' => [],
                'agenda_ano' => $agendaAno,
                'agenda_mes' => $agendaMes,
                'dashboard_async' => true,
                'dashboard_agenda_defer' => true,
            ];

            $this->medirDashboard('render_view', function () use ($data) {
                $this->viewWithLayout('student', 'student/dashboard', $data);
                return null;
            });

            $this->logDashboardPerformance('TOTAL', (microtime(true) - $inicioTotal) * 1000);
            return;
        }

        $jornadasAtivasTodas = [];
        if ($jornadas_habilitadas && $turmaId > 0) {
            $jornadasAtivasTodas = $this->medirDashboard('jornadas', function () use ($aluno, $turmaId) {
                return $this->getActiveJourneys($turmaId, (int) $aluno['id']);
            });
        }

        $jornadasAbertasCount = $this->contarJornadasAbertasFromList($jornadasAtivasTodas);
        $jornadas_ativas = array_slice($jornadasAtivasTodas, 0, 3);

        $provasDisponiveisAgora = $this->medirDashboard('provas_disponiveis', function () use ($aluno, $alunoId, $turmaId) {
            return $this->getProvasDisponiveisAgoraCount($aluno, $alunoId, $turmaId);
        });

        $stats = $this->medirDashboard('student_stats', function () use ($alunoId) {
            return $this->getStudentStats($alunoId);
        });

        $redacoes_recentes = $this->medirDashboard('redacoes_recentes', function () use ($alunoId) {
            return $this->getRecentEssays($alunoId);
        });

        $limites_diarios = $this->medirDashboard('limites_diarios', function () use ($alunoId) {
            return $this->getLimitesDiarios($alunoId);
        });

        $muralRecadosNaoLidosCount = $this->medirDashboard('mural_count', function () use ($alunoId, $turmaId) {
            return $this->countUnreadMuralRecadosForAluno($alunoId, $turmaId);
        });

        $muralRecadosNaoVistos = $this->medirDashboard('mural_lista', function () use ($alunoId, $turmaId) {
            return $this->listUnreadMuralRecadosForAluno($alunoId, $turmaId, 10);
        });

        $muralRecadosCount = $this->medirDashboard('mural_total', function () use ($alunoId, $turmaId) {
            return $this->countMuralRecadosForAluno($alunoId, $turmaId);
        });

        $onboarding = null;
        if (!$onboardingCompletado) {
            $onboarding = $this->medirDashboard('onboarding', function () use ($alunoId) {
                return $this->db->fetch(
                    "SELECT * FROM alunos_onboarding WHERE aluno_id = :aluno_id",
                    ['aluno_id' => $alunoId]
                );
            });
        }

        $aulasOnline = [];
        if (LayoutHelper::isModuleEnabled('aulas_online') && $turmaId > 0) {
            $aulasOnline = $this->medirDashboard('aulas_online', function () use ($alunoId, $turmaId) {
                try {
                    require_once __DIR__ . '/../../Models/Education/OnlineClass.php';
                    return (new OnlineClass())->listLiveAndUpcomingForAluno($alunoId, $turmaId, 5);
                } catch (Throwable $e) {
                    error_log('dashboard aulas_online: ' . $e->getMessage());
                    return [];
                }
            });
        }

        $agendaEventos = $this->medirDashboard('agenda', function () use ($alunoId, $turmaId, $agendaAno, $agendaMes) {
            require_once __DIR__ . '/../../Services/StudentAgendaService.php';
            return (new StudentAgendaService($this->db))->getMonthEvents($alunoId, $turmaId, $agendaAno, $agendaMes);
        });

        $data = [
            'title' => 'Dashboard - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'stats' => $stats,
            'aulas_online' => $aulasOnline,
            'jornadas_ativas' => $jornadas_ativas,
            'jornadas_habilitadas' => $jornadas_habilitadas,
            'jornadas_ativas_layout' => $jornadasAtivasLayout,
            'redacoes_recentes' => $redacoes_recentes,
            'limites_diarios' => $limites_diarios,
            'onboarding_completado' => $onboardingCompletado,
            'onboarding' => $onboarding,
            'mural_recados_nao_vistos' => $muralRecadosNaoVistos,
            'mural_recados_count' => $muralRecadosCount,
            'primary_color' => $primaryColor,
            'dashboard_sliders' => $dashboardSliders,
            'current_page' => 'dashboard',
            'csrf_token' => $this->generateCsrfToken(),
            'turmas_cursos_select' => $turmasCursosSelect,
            'jornadas_abertas_count' => $jornadasAbertasCount,
            'mural_recados_nao_lidos_count' => $muralRecadosNaoLidosCount,
            'provas_disponiveis_agora_count' => $provasDisponiveisAgora,
            'dashboard_acao_cards' => false,
            'jornada_redacao_pendentes_count' => 0,
            'agenda_eventos' => $agendaEventos,
            'agenda_ano' => $agendaAno,
            'agenda_mes' => $agendaMes,
            'dashboard_async' => false,
            'dashboard_agenda_defer' => false,
        ];

        $this->medirDashboard('render_view', function () use ($data) {
            $this->viewWithLayout('student', 'student/dashboard', $data);
            return null;
        });

        $this->logDashboardPerformance('TOTAL', (microtime(true) - $inicioTotal) * 1000);
    }

    /**
     * GET /dashboard/api/montar — cards do dashboard (layout acao_cards, assíncrono).
     * Segurança: aluno_id vem somente da sessão.
     */
    public function dashboardApiMontar(): void
    {
        $aluno = $this->resolverAlunoAutenticadoParaApi();
        if (!$aluno) {
            return;
        }

        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $jornadasHabilitadas = LayoutHelper::isModuleEnabled('jornadas');
        $acaoCards = $this->isDashboardAcaoCardsLayout();

        require_once __DIR__ . '/../../Services/DashboardAlunoService.php';
        $service = new DashboardAlunoService($this->db);
        $payload = $service->montarCards($aluno, $acaoCards, $jornadasHabilitadas);

        $this->json([
            'success' => true,
            'cards' => $payload['cards'] ?? [],
            'mural' => $payload['mural'] ?? [],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolverAlunoAutenticadoParaApi(): ?array
    {
        $user = $this->authManager->getUser();
        if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
            $this->json(['success' => false, 'error' => 'Não autorizado'], 401);
            return null;
        }

        if (!class_exists('ContextoAluno')) {
            require_once __DIR__ . '/../../Core/ContextoAluno.php';
        }

        $alunoIdSessao = ContextoAluno::idDaSessao();
        if ($alunoIdSessao <= 0 || $alunoIdSessao !== (int) ($user['id'] ?? 0)) {
            $this->json(['success' => false, 'error' => 'Sessão inválida'], 401);
            return null;
        }

        $aluno = ContextoAluno::resolver($this->db);
        if (!$aluno || (int) ($aluno['id'] ?? 0) !== $alunoIdSessao) {
            $this->json(['success' => false, 'error' => 'Aluno não encontrado'], 404);
            return null;
        }

        return $aluno;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseDashboardSliders(): array
    {
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $items = [];
        $dashboardSlidersRaw = (string) LayoutHelper::get('dashboard_slider_items', '[]');
        if ($dashboardSlidersRaw === '') {
            return $items;
        }
        $decoded = json_decode($dashboardSlidersRaw, true);
        if (!is_array($decoded)) {
            return $items;
        }
        foreach ($decoded as $item) {
            if (!is_array($item) || empty($item['image_url']) || empty($item['active'])) {
                continue;
            }
            $items[] = [
                'title' => trim((string) ($item['title'] ?? '')),
                'image_url' => trim((string) ($item['image_url'] ?? '')),
                'link_url' => trim((string) ($item['link_url'] ?? '')),
                'action_type' => trim((string) ($item['action_type'] ?? 'external')),
                'module_key' => trim((string) ($item['module_key'] ?? '')),
            ];
        }
        return $items;
    }

    /**
     * GET /dashboard/agenda?ano=2026&mes=7 — eventos do mês (JSON).
     */
    public function dashboardAgendaJson(): void
    {
        $aluno = $this->resolverAlunoAutenticadoParaApi();
        if (!$aluno) {
            return;
        }

        $mes = (int) ($_GET['mes'] ?? date('n'));
        $ano = (int) ($_GET['ano'] ?? date('Y'));
        if ($mes < 1 || $mes > 12) {
            $this->json(['success' => false, 'error' => 'Mês inválido'], 400);
            return;
        }
        if ($ano < 2000 || $ano > 2100) {
            $this->json(['success' => false, 'error' => 'Ano inválido'], 400);
            return;
        }

        $alunoId = (int) ($aluno['id'] ?? 0);
        $turmaId = (int) ($aluno['turma_id'] ?? 0);
        try {
            require_once __DIR__ . '/../../Services/StudentAgendaService.php';
            $eventos = (new StudentAgendaService($this->db))->getMonthEvents($alunoId, $turmaId, $ano, $mes);
            $this->json([
                'success' => true,
                'month' => $mes,
                'year' => $ano,
                'events' => $eventos,
            ]);
        } catch (Throwable $e) {
            error_log('dashboardAgendaJson: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao carregar agenda'], 500);
        }
    }

    private function isDashboardPerformanceLogEnabled(): bool
    {
        $flag = strtolower(trim((string) ($_ENV['DASHBOARD_PERFORMANCE_LOG'] ?? '')));
        if ($flag === 'true' || $flag === '1') {
            return true;
        }
        if ($flag === 'false' || $flag === '0') {
            return false;
        }
        return !empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true';
    }

    private function logDashboardPerformance(string $nome, float $tempoMs): void
    {
        if (!$this->isDashboardPerformanceLogEnabled()) {
            return;
        }
        error_log(sprintf('[DASHBOARD PERFORMANCE] %s: %.2f ms', $nome, $tempoMs));
    }

    private function medirDashboard(string $nome, callable $callback)
    {
        $inicio = microtime(true);
        try {
            return $callback();
        } finally {
            $this->logDashboardPerformance($nome, (microtime(true) - $inicio) * 1000);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $jornadas
     */
    private function contarJornadasAbertasFromList(array $jornadas): int
    {
        $agoraTs = time();
        $count = 0;
        foreach ($jornadas as $jornada) {
            if (!empty($jornada['jornada_concluida'])) {
                continue;
            }
            if (!empty($jornada['data_fim'])) {
                $horaFim = trim((string) ($jornada['hora_fim'] ?? '')) ?: '23:59:59';
                $tsFim = strtotime((string) $jornada['data_fim'] . ' ' . $horaFim);
                if ($tsFim !== false && $agoraTs > $tsFim) {
                    continue;
                }
            }
            $count++;
        }
        return $count;
    }

    private function countMuralRecadosForAluno(int $alunoId, int $turmaId): int
    {
        $sql = $this->buildMuralRecadosBaseSql($turmaId, false);
        $params = $this->buildMuralRecadosBaseParams($alunoId, $turmaId, false);
        $row = $this->db->fetch("SELECT COUNT(*) AS total FROM mural_recados r WHERE {$sql}", $params);
        return (int) ($row['total'] ?? 0);
    }

    private function countUnreadMuralRecadosForAluno(int $alunoId, int $turmaId): int
    {
        $sql = $this->buildMuralRecadosBaseSql($turmaId, true);
        $params = $this->buildMuralRecadosBaseParams($alunoId, $turmaId, true);
        $row = $this->db->fetch("SELECT COUNT(*) AS total FROM mural_recados r WHERE {$sql}", $params);
        return (int) ($row['total'] ?? 0);
    }

    private function listUnreadMuralRecadosForAluno(int $alunoId, int $turmaId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $sql = $this->buildMuralRecadosBaseSql($turmaId, true);
        $params = $this->buildMuralRecadosBaseParams($alunoId, $turmaId, true);
        return $this->db->fetchAll(
            "SELECT r.id, r.titulo, r.conteudo, r.data_publicacao,
                    COALESCE(p.nome, 'Admin') AS autor_nome
             FROM mural_recados r
             LEFT JOIN professores p ON r.autor_tipo = 'professor' AND p.id = r.autor_id
             WHERE {$sql}
             ORDER BY r.data_publicacao DESC
             LIMIT {$limit}",
            $params
        );
    }

    private function buildMuralRecadosBaseSql(int $turmaId, bool $apenasNaoVistos): string
    {
        $turmaFiltro = $turmaId > 0
            ? " OR EXISTS (SELECT 1 FROM mural_recados_turmas rt WHERE rt.mural_recado_id = r.id AND rt.turma_id = :aluno_turma_id)"
            : '';
        $sql = "(r.enviar_para_todos = 1{$turmaFiltro})
                AND (CURDATE() <= r.data_sai_mural)";
        if ($apenasNaoVistos) {
            $sql .= " AND NOT EXISTS (
                SELECT 1 FROM mural_recados_vistos v
                WHERE v.mural_recado_id = r.id AND v.aluno_id = :aluno_id
            )";
        }
        return $sql;
    }

    private function buildMuralRecadosBaseParams(int $alunoId, int $turmaId, bool $apenasNaoVistos): array
    {
        $params = [];
        if ($turmaId > 0) {
            $params['aluno_turma_id'] = $turmaId;
        }
        if ($apenasNaoVistos) {
            $params['aluno_id'] = $alunoId;
        }
        return $params;
    }

    /**
     * Troca a turma ativa do aluno (entre matrículas / cursos).
     */
    public function trocarTurmaCurso()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Atualize a página e tente novamente.', 'error');
            $this->redirect('/dashboard');
            return;
        }

        if (!class_exists('ContextoAluno')) {
            require_once __DIR__ . '/../../Core/ContextoAluno.php';
        }

        $alunoId = ContextoAluno::idDaSessao();
        if ($alunoId <= 0) {
            $this->redirect('/logout');
            return;
        }

        $aluno = ContextoAluno::resolver($this->db);
        if (!$aluno || (int) ($aluno['id'] ?? 0) !== $alunoId) {
            $this->redirect('/logout');
            return;
        }

        $turmaId = (int) ($_POST['turma_id'] ?? 0);
        if ($turmaId <= 0) {
            $this->redirect('/dashboard');
            return;
        }

        $permitidas = $this->getTurmasCursosMatriculaAluno($alunoId, $aluno);
        $idsOk = array_column($permitidas, 'turma_id');
        if (!in_array($turmaId, $idsOk, true)) {
            $this->setFlashMessage('Turma não permitida para sua conta.', 'error');
            $this->redirect('/dashboard');
            return;
        }

        try {
            $this->db->update(
                'UPDATE alunos SET turma_id = :turma_id, updated_at = NOW() WHERE id = :id',
                ['turma_id' => $turmaId, 'id' => $alunoId]
            );
            ContextoAluno::atualizarTurmaAtiva($this->db, $turmaId);

            require_once __DIR__ . '/../../Services/DashboardAlunoService.php';
            (new DashboardAlunoService($this->db))->invalidarCacheAluno($alunoId, $turmaId);
        } catch (Exception $e) {
            error_log('trocarTurmaCurso: ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível alterar o curso/turma.', 'error');
            $this->redirect('/dashboard');
            return;
        }
        $this->redirect('/dashboard');
    }

    /**
     * Turmas/cursos em que o aluno está matriculado (matricula ativa) ou turma atual como fallback.
     *
     * @return array<int,array{turma_id:int,label:string}>
     */
    private function getTurmasCursosMatriculaAluno(int $alunoId, array $aluno): array
    {
        $out = [];
        try {
            $hasMatricula = $this->db->fetch("SHOW TABLES LIKE 'matricula'");
            if ($hasMatricula) {
                $rows = $this->db->fetchAll(
                    "SELECT m.turma_id, t.nome AS turma_nome, MAX(al.ano) AS ano_letivo_ano
                     FROM matricula m
                     INNER JOIN turmas t ON t.id = m.turma_id AND (t.ativo = 1 OR t.ativo IS NULL)
                     INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id
                     WHERE m.aluno_id = :aluno_id AND m.status = 'ativa'
                     GROUP BY m.turma_id, t.nome
                     ORDER BY t.nome ASC",
                    ['aluno_id' => $alunoId]
                );
                foreach ($rows as $r) {
                    $tid = (int) $r['turma_id'];
                    $nome = trim((string) ($r['turma_nome'] ?? ''));
                    $ano = isset($r['ano_letivo_ano']) ? (int) $r['ano_letivo_ano'] : 0;
                    $label = $nome !== '' ? $nome : ('Turma #' . $tid);
                    if ($ano > 0) {
                        $label .= ' (' . $ano . ')';
                    }
                    $out[] = ['turma_id' => $tid, 'label' => $label];
                }
            }
        } catch (Exception $e) {
            $out = [];
        }
        $ids = array_column($out, 'turma_id');
        $curTid = isset($aluno['turma_id']) ? (int) $aluno['turma_id'] : 0;
        if ($curTid > 0 && !in_array($curTid, $ids, true)) {
            $nome = trim((string) ($aluno['turma_nome'] ?? ''));
            $out[] = [
                'turma_id' => $curTid,
                'label' => $nome !== '' ? $nome : ('Turma #' . $curTid),
            ];
        }
        return $out;
    }

    /**
     * Exibe perfil do aluno com dados de onboarding
     */
    public function perfil()
    {
        $user = $this->authManager->getUser();
        
        // Buscar dados do aluno
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome 
             FROM alunos a 
             LEFT JOIN turmas t ON a.turma_id = t.id 
             WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );

        if (!$aluno) {
            $this->redirect('/logout');
        }

        // Buscar dados de onboarding
        $onboarding = $this->db->fetch(
            "SELECT * FROM alunos_onboarding WHERE aluno_id = :aluno_id",
            ['aluno_id' => $aluno['id']]
        );

        $data = [
            'title' => 'Meu Perfil - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'onboarding' => $onboarding ?: [],
            'current_page' => 'perfil',
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('student', 'student/perfil', $data);
    }

    public function desempenhoJornadas()
    {
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome FROM alunos a LEFT JOIN turmas t ON a.turma_id = t.id WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );
        if (!$aluno) { $this->redirect('/logout'); }

        $alunoId = $aluno['id'];
        $turmaId = $aluno['turma_id'] ?? 0;

        // Jornadas da turma (turma_id direto OU via estrutura.turmas_selecionadas)
        $jornadasAll = $this->db->fetchAll(
            "SELECT j.id, j.titulo, j.status, j.materia_id, j.turma_id, j.estrutura, m.nome as materia_nome
             FROM jornadas j
             LEFT JOIN materias m ON j.materia_id = m.id
             WHERE (j.turma_id = :turma_id OR (j.estrutura IS NOT NULL AND j.estrutura != ''))
               AND (j.ativo = 1 OR j.ativo IS NULL)
             ORDER BY j.created_at DESC",
            ['turma_id' => $turmaId]
        );
        $jornadasTurma = array_filter($jornadasAll, function ($j) use ($turmaId) {
            if ((int)$j['turma_id'] === (int)$turmaId) return true;
            $e = json_decode($j['estrutura'] ?? '', true);
            return is_array($e) && !empty($e['turmas_selecionadas']) && in_array((int)$turmaId, array_map('intval', $e['turmas_selecionadas']), true);
        });
        $jornadasTurma = array_values($jornadasTurma);

        // Jornadas concluídas pelo aluno
        $jornadasConcluidas = $this->db->fetchAll(
            "SELECT DISTINCT jpa.jornada_id
             FROM jornadas_progresso_alunos jpa
             WHERE jpa.aluno_id = :aluno_id AND jpa.atividade_tipo = 'jornada_concluida' AND jpa.status = 'concluido'",
            ['aluno_id' => $alunoId]
        );
        $jornadasConcluidasIds = array_column($jornadasConcluidas, 'jornada_id');

        // Considerar também como concluída se todos os módulos foram concluídos (batch em 2 queries)
        $pendentesIds = array_values(array_filter(array_column($jornadasTurma, 'id'), function ($jid) use ($jornadasConcluidasIds) {
            return !in_array($jid, $jornadasConcluidasIds);
        }));
        if (!empty($pendentesIds)) {
            $phJ = implode(',', array_fill(0, count($pendentesIds), '?'));
            $totalModPorJornada = $this->db->fetchAll(
                "SELECT jornada_id, COUNT(*) as total_mod FROM jornadas_modulos WHERE jornada_id IN ($phJ) GROUP BY jornada_id",
                $pendentesIds
            );
            $concluidosPorJornada = $this->db->fetchAll(
                "SELECT jornada_id, COUNT(*) as concluidos FROM jornadas_progresso_alunos
                 WHERE jornada_id IN ($phJ) AND aluno_id = ? AND atividade_tipo = 'modulo' AND status = 'concluido'
                 GROUP BY jornada_id",
                array_merge($pendentesIds, [$alunoId])
            );
            $mapTotalMod = array_column($totalModPorJornada, 'total_mod', 'jornada_id');
            $mapConcluidos = array_column($concluidosPorJornada, 'concluidos', 'jornada_id');
            foreach ($pendentesIds as $jid) {
                $totalMod = (int)($mapTotalMod[$jid] ?? 0);
                $concluidos = (int)($mapConcluidos[$jid] ?? 0);
                if ($totalMod > 0 && $concluidos >= $totalMod) {
                    $jornadasConcluidasIds[] = $jid;
                }
            }
        }

        $totalJornadas = count($jornadasTurma);
        $totalConcluidas = 0;
        foreach ($jornadasTurma as $j) {
            if (in_array($j['id'], $jornadasConcluidasIds)) $totalConcluidas++;
        }
        $totalPendentes = $totalJornadas - $totalConcluidas;
        $pctConcluidas = $totalJornadas > 0 ? round(($totalConcluidas / $totalJornadas) * 100, 1) : 0;

        $sqlAcertoBase = "CASE WHEN base.tipo = 'dissertativa' AND base.corrigida = 0 AND (base.pontuacao IS NULL OR base.pontuacao <= 0) THEN 0 WHEN base.pontuacao > 0 THEN 1 ELSE 0 END";
        $sqlErroBase = "CASE WHEN base.tipo = 'dissertativa' AND base.corrigida = 0 AND (base.pontuacao IS NULL OR base.pontuacao <= 0) THEN 0 WHEN base.pontuacao > 0 THEN 0 ELSE 1 END";
        $sqlInnerAgg = "MAX(jpa.pontuacao) as pontuacao,
                                   MAX(me.tipo) as tipo,
                                   MAX(CASE WHEN JSON_VALID(jpa.resposta) AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(jpa.resposta, '$.correcao_status')), '') = 'corrigida' THEN 1 ELSE 0 END) as corrigida";

        // Acertos/erros gerais (1 registro por exercício respondido)
        $geral = $this->db->fetch(
            "SELECT COUNT(*) as total,
                    SUM({$sqlAcertoBase}) as corretas,
                    SUM({$sqlErroBase}) as erros
             FROM (
                SELECT jpa.exercicio_modulo_id, {$sqlInnerAgg}
                FROM jornadas_progresso_alunos jpa
                LEFT JOIN jornadas_modulos_exercicios me ON me.id = jpa.exercicio_modulo_id
                WHERE jpa.aluno_id = :aluno_id
                  AND jpa.atividade_tipo = 'exercicio_modulo'
                  AND jpa.resposta IS NOT NULL
                GROUP BY jpa.exercicio_modulo_id
             ) base",
            ['aluno_id' => $alunoId]
        );
        $geralTotal = (int)($geral['total'] ?? 0);
        $geralCorretas = (int)($geral['corretas'] ?? 0);
        $geralErros = (int)($geral['erros'] ?? 0);
        $geralAvaliadas = $geralCorretas + $geralErros;
        $geralPct = $geralAvaliadas > 0 ? round(($geralCorretas / $geralAvaliadas) * 100, 1) : 0;

        // Acertos/erros por matéria
        $porMateria = $this->db->fetchAll(
            "SELECT m.nome as materia_nome,
                    COUNT(*) as total,
                    SUM({$sqlAcertoBase}) as corretas,
                    SUM({$sqlErroBase}) as erros
             FROM (
                SELECT jpa.jornada_id, jpa.exercicio_modulo_id, {$sqlInnerAgg}
                FROM jornadas_progresso_alunos jpa
                LEFT JOIN jornadas_modulos_exercicios me ON me.id = jpa.exercicio_modulo_id
                WHERE jpa.aluno_id = :aluno_id
                  AND jpa.atividade_tipo = 'exercicio_modulo'
                  AND jpa.resposta IS NOT NULL
                GROUP BY jpa.jornada_id, jpa.exercicio_modulo_id
             ) base
             JOIN jornadas j ON base.jornada_id = j.id
             LEFT JOIN materias m ON j.materia_id = m.id
             GROUP BY j.materia_id, m.nome
             ORDER BY total DESC",
            ['aluno_id' => $alunoId]
        );
        foreach ($porMateria as &$row) {
            $row['total'] = (int)$row['total'];
            $row['corretas'] = (int)$row['corretas'];
            $row['erros'] = (int)($row['erros'] ?? max(0, $row['total'] - $row['corretas']));
            $avaliadas = $row['corretas'] + $row['erros'];
            $row['percentual'] = $avaliadas > 0 ? round(($row['corretas'] / $avaliadas) * 100, 1) : 0;
            if (empty($row['materia_nome'])) $row['materia_nome'] = 'Sem matéria';
        }
        unset($row);

        // Detalhes por jornada (usando a lista filtrada de jornadasTurma)
        $jornadaIds = array_column($jornadasTurma, 'id');
        $porJornada = [];
        if (!empty($jornadaIds)) {
            $placeholders = implode(',', array_fill(0, count($jornadaIds), '?'));
            $params = array_merge([$alunoId], $jornadaIds);
            $porJornada = $this->db->fetchAll(
                "SELECT j.id, j.titulo, j.status, m.nome as materia_nome,
                        COUNT(base.exercicio_modulo_id) as total,
                        SUM({$sqlAcertoBase}) as corretas,
                        SUM({$sqlErroBase}) as erros
                 FROM jornadas j
                 LEFT JOIN materias m ON j.materia_id = m.id
                 LEFT JOIN (
                    SELECT jpa.jornada_id, jpa.exercicio_modulo_id, {$sqlInnerAgg}
                    FROM jornadas_progresso_alunos jpa
                    LEFT JOIN jornadas_modulos_exercicios me ON me.id = jpa.exercicio_modulo_id
                    WHERE jpa.aluno_id = ?
                      AND jpa.atividade_tipo = 'exercicio_modulo'
                      AND jpa.resposta IS NOT NULL
                      AND jpa.jornada_id IN ({$placeholders})
                    GROUP BY jpa.jornada_id, jpa.exercicio_modulo_id
                 ) base ON base.jornada_id = j.id
                 WHERE j.id IN ({$placeholders})
                 GROUP BY j.id, j.titulo, j.status, m.nome
                 ORDER BY j.created_at DESC",
                array_merge([$alunoId], $jornadaIds, $jornadaIds)
            );
        }
        foreach ($porJornada as &$row) {
            $row['total'] = (int)$row['total'];
            $row['corretas'] = (int)$row['corretas'];
            $row['erros'] = (int)($row['erros'] ?? max(0, $row['total'] - $row['corretas']));
            $avaliadas = $row['corretas'] + $row['erros'];
            $row['percentual'] = $avaliadas > 0 ? round(($row['corretas'] / $avaliadas) * 100, 1) : 0;
            $row['concluida'] = in_array($row['id'], $jornadasConcluidasIds);
        }
        unset($row);

        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $primaryColor = \LayoutHelper::get('primary_color', $this->config['school']['colors']['primary'] ?? '#3b82f6');

        $data = [
            'title' => 'Desempenho em Jornadas - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'total_jornadas' => $totalJornadas,
            'total_concluidas' => $totalConcluidas,
            'total_pendentes' => $totalPendentes,
            'pct_concluidas' => $pctConcluidas,
            'geral_total' => $geralTotal,
            'geral_corretas' => $geralCorretas,
            'geral_erros' => $geralErros,
            'geral_pct' => $geralPct,
            'por_materia' => $porMateria,
            'por_jornada' => $porJornada,
            'primary_color' => $primaryColor,
            'current_page' => 'dashboard',
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('student', 'student/desempenho-jornadas', $data);
    }

    public function desempenhoProvas()
    {
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome FROM alunos a LEFT JOIN turmas t ON a.turma_id = t.id WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );
        if (!$aluno) { $this->redirect('/logout'); }

        $alunoId = $aluno['id'];

        // Total de provas realizadas / disponíveis
        $realizacoes = $this->db->fetchAll(
            "SELECT pr.prova_id, pr.nota, pr.status as realizacao_status, pr.finalizado_em,
                    p.titulo, p.materia_id, p.valor_total, p.status as prova_status,
                    m.nome as materia_nome
             FROM provas_realizacoes pr
             JOIN provas p ON pr.prova_id = p.id
             LEFT JOIN materias m ON p.materia_id = m.id
             WHERE pr.aluno_id = :aluno_id
             ORDER BY pr.finalizado_em DESC",
            ['aluno_id' => $alunoId]
        );

        $totalProvas = count($realizacoes);
        $totalFinalizadas = 0;
        $somaNotas = 0;
        $somaValores = 0;
        foreach ($realizacoes as $r) {
            if (!empty($r['finalizado_em'])) {
                $totalFinalizadas++;
                $somaNotas += (float)($r['nota'] ?? 0);
                $somaValores += (float)($r['valor_total'] ?? 100);
            }
        }
        $mediaNotas = $totalFinalizadas > 0 ? round(($somaNotas / $totalFinalizadas), 1) : 0;

        // Acertos/erros gerais
        $geralProva = $this->db->fetch(
            "SELECT COUNT(*) as total,
                    SUM(CASE WHEN pr.correta = 1 THEN 1 ELSE 0 END) as corretas
             FROM provas_respostas pr
             WHERE pr.aluno_id = :aluno_id",
            ['aluno_id' => $alunoId]
        );
        $geralTotal = (int)($geralProva['total'] ?? 0);
        $geralCorretas = (int)($geralProva['corretas'] ?? 0);
        $geralErros = $geralTotal - $geralCorretas;
        $geralPct = $geralTotal > 0 ? round(($geralCorretas / $geralTotal) * 100, 1) : 0;

        // Acertos/erros por matéria
        $porMateria = $this->db->fetchAll(
            "SELECT m.nome as materia_nome,
                    COUNT(*) as total,
                    SUM(CASE WHEN pr.correta = 1 THEN 1 ELSE 0 END) as corretas
             FROM provas_respostas pr
             JOIN provas p ON pr.prova_id = p.id
             LEFT JOIN materias m ON p.materia_id = m.id
             WHERE pr.aluno_id = :aluno_id
             GROUP BY p.materia_id, m.nome
             ORDER BY total DESC",
            ['aluno_id' => $alunoId]
        );
        foreach ($porMateria as &$row) {
            $row['total'] = (int)$row['total'];
            $row['corretas'] = (int)$row['corretas'];
            $row['erros'] = $row['total'] - $row['corretas'];
            $row['percentual'] = $row['total'] > 0 ? round(($row['corretas'] / $row['total']) * 100, 1) : 0;
            if (empty($row['materia_nome'])) $row['materia_nome'] = 'Sem matéria';
        }
        unset($row);

        // Detalhes por prova
        $porProva = [];
        foreach ($realizacoes as $r) {
            $respostas = $this->db->fetch(
                "SELECT COUNT(*) as total,
                        SUM(CASE WHEN pr.correta = 1 THEN 1 ELSE 0 END) as corretas
                 FROM provas_respostas pr
                 WHERE pr.prova_id = :prova_id AND pr.aluno_id = :aluno_id",
                ['prova_id' => $r['prova_id'], 'aluno_id' => $alunoId]
            );
            $t = (int)($respostas['total'] ?? 0);
            $c = (int)($respostas['corretas'] ?? 0);
            $porProva[] = [
                'titulo' => $r['titulo'],
                'materia_nome' => $r['materia_nome'] ?? 'Sem matéria',
                'nota' => $r['nota'],
                'valor_total' => $r['valor_total'],
                'finalizado_em' => $r['finalizado_em'],
                'total' => $t,
                'corretas' => $c,
                'erros' => $t - $c,
                'percentual' => $t > 0 ? round(($c / $t) * 100, 1) : 0,
            ];
        }

        // Média de notas por matéria
        $notasPorMateria = $this->db->fetchAll(
            "SELECT m.nome as materia_nome,
                    COUNT(pr2.id) as total_provas,
                    AVG(pr2.nota) as media_nota,
                    MAX(pr2.nota) as melhor_nota,
                    MIN(pr2.nota) as pior_nota
             FROM provas_realizacoes pr2
             JOIN provas p ON pr2.prova_id = p.id
             LEFT JOIN materias m ON p.materia_id = m.id
             WHERE pr2.aluno_id = :aluno_id AND pr2.finalizado_em IS NOT NULL
             GROUP BY p.materia_id, m.nome
             ORDER BY media_nota DESC",
            ['aluno_id' => $alunoId]
        );
        foreach ($notasPorMateria as &$row) {
            $row['media_nota'] = round((float)($row['media_nota'] ?? 0), 1);
            $row['melhor_nota'] = round((float)($row['melhor_nota'] ?? 0), 1);
            $row['pior_nota'] = round((float)($row['pior_nota'] ?? 0), 1);
            if (empty($row['materia_nome'])) $row['materia_nome'] = 'Sem matéria';
        }
        unset($row);

        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $primaryColor = \LayoutHelper::get('primary_color', $this->config['school']['colors']['primary'] ?? '#3b82f6');

        $data = [
            'title' => 'Desempenho em Provas - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'total_provas' => $totalProvas,
            'total_finalizadas' => $totalFinalizadas,
            'media_notas' => $mediaNotas,
            'geral_total' => $geralTotal,
            'geral_corretas' => $geralCorretas,
            'geral_erros' => $geralErros,
            'geral_pct' => $geralPct,
            'por_materia' => $porMateria,
            'por_prova' => $porProva,
            'notas_por_materia' => $notasPorMateria,
            'primary_color' => $primaryColor,
            'current_page' => 'dashboard',
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('student', 'student/desempenho-provas', $data);
    }

    public function notasBoletins()
    {
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome FROM alunos a LEFT JOIN turmas t ON a.turma_id = t.id WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );
        if (!$aluno) {
            $this->redirect('/logout');
        }

        $secao = strtolower(trim((string) ($_GET['secao'] ?? 'boletim')));
        if (!in_array($secao, ['boletim', 'notas', 'provas'], true)) {
            $secao = 'boletim';
        }

        try {
            $provasRealizadas = $this->getProvasRealizadasAlunoComBloco((int) $aluno['id']);
        } catch (\Throwable $e) {
            $provasRealizadas = [];
        }

        try {
            $provasMatrizBlocos = $this->buildProvasMatrizPorBlocoAplicado($provasRealizadas);
        } catch (\Throwable $e) {
            $provasMatrizBlocos = ['tabelas' => [], 'tem_dados' => false];
        }

        $notasLancamentoEventos = [];
        try {
            require_once __DIR__ . '/../../Models/Exams/ExamBlockManualGrade.php';
            $notasLancamentoEventos = (new ExamBlockManualGrade())->fetchNotasPorAluno((int) $aluno['id']);
        } catch (\Throwable $e) {
            $notasLancamentoEventos = [];
        }

        $boletinsGerados = [];
        $boletinsGeradosNotas = [];
        $boletinsGeradosBoletim = [];
        try {
            require_once __DIR__ . '/../../Models/System/BoletimConfig.php';
            $boletimCfg = new BoletimConfig();
            $boletimCfg->ensureSchema();
            $boletinsGerados = $boletimCfg->getGeneratedBoletinsByAluno((int) $aluno['id'], 'aluno');
            foreach ($boletinsGerados as $ev) {
                $exibir = strtolower(trim((string) ($ev['exibir_em'] ?? 'boletim')));
                if ($exibir === 'notas') {
                    $boletinsGeradosNotas[] = $ev;
                } else {
                    $boletinsGeradosBoletim[] = $ev;
                }
            }
        } catch (\Throwable $e) {
            $boletinsGerados = [];
            $boletinsGeradosNotas = [];
            $boletinsGeradosBoletim = [];
        }

        $boletimObservacao = ['conteudo' => '', 'updated_at' => null];
        try {
            require_once __DIR__ . '/../../Models/System/BoletimConfig.php';
            $boletimObsRow = (new BoletimConfig())->getObservacaoCoordenacao((int) $aluno['id']);
            if ($boletimObsRow) {
                $boletimObservacao = [
                    'conteudo' => (string) ($boletimObsRow['conteudo'] ?? ''),
                    'updated_at' => $boletimObsRow['updated_at'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            $boletimObservacao = ['conteudo' => '', 'updated_at' => null];
        }

        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $primaryColor = LayoutHelper::get('primary_color', $this->config['school']['colors']['primary'] ?? '#3b82f6');
        $data = [
            'title' => 'Notas/Boletins - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'secao_notas' => $secao,
            'provas_realizadas' => $provasRealizadas,
            'provas_matriz_blocos' => $provasMatrizBlocos,
            'notas_lancamento_eventos' => $notasLancamentoEventos,
            'boletins_gerados' => $boletinsGerados,
            'boletins_gerados_notas' => $boletinsGeradosNotas,
            'boletins_gerados_boletim' => $boletinsGeradosBoletim,
            'boletim_observacao' => $boletimObservacao,
            'primary_color' => $primaryColor,
            'current_page' => 'notas_boletins',
            'csrf_token' => $this->generateCsrfToken(),
            'quadro_notas_semanais' => $this->montarQuadroNotasSemanais((int) $aluno['id']),
        ];

        $this->viewWithLayout('student', 'student/notas-boletins', $data);
    }

    public function notas()
    {
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome FROM alunos a LEFT JOIN turmas t ON a.turma_id = t.id WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );
        if (!$aluno) {
            $this->redirect('/logout');
        }

        try {
            $provasRealizadas = $this->getProvasRealizadasAlunoComBloco((int) $aluno['id']);
        } catch (\Throwable $e) {
            $provasRealizadas = [];
        }

        try {
            $provasMatrizBlocos = $this->buildProvasMatrizPorBlocoAplicado($provasRealizadas);
        } catch (\Throwable $e) {
            $provasMatrizBlocos = ['tabelas' => [], 'tem_dados' => false];
        }

        $notasLancamentoEventos = [];
        try {
            require_once __DIR__ . '/../../Models/Exams/ExamBlockManualGrade.php';
            $notasLancamentoEventos = (new ExamBlockManualGrade())->fetchNotasPorAluno((int) $aluno['id']);
        } catch (\Throwable $e) {
            $notasLancamentoEventos = [];
        }

        $boletinsGerados = [];
        $boletinsGeradosNotas = [];
        $boletinsGeradosBoletim = [];
        try {
            require_once __DIR__ . '/../../Models/System/BoletimConfig.php';
            $boletimCfg = new BoletimConfig();
            $boletimCfg->ensureSchema();
            $boletinsGerados = $boletimCfg->getGeneratedBoletinsByAluno((int) $aluno['id'], 'aluno', 'notas');
            foreach ($boletinsGerados as $ev) {
                $exibir = strtolower(trim((string) ($ev['exibir_em'] ?? 'boletim')));
                if ($exibir === 'notas') {
                    $boletinsGeradosNotas[] = $ev;
                } else {
                    $boletinsGeradosBoletim[] = $ev;
                }
            }
        } catch (\Throwable $e) {
            $boletinsGerados = [];
            $boletinsGeradosNotas = [];
            $boletinsGeradosBoletim = [];
        }

        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $primaryColor = LayoutHelper::get('primary_color', $this->config['school']['colors']['primary'] ?? '#3b82f6');
        $data = [
            'title' => 'Notas - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'provas_realizadas' => $provasRealizadas,
            'provas_matriz_blocos' => $provasMatrizBlocos,
            'notas_lancamento_eventos' => $notasLancamentoEventos,
            'boletins_gerados' => $boletinsGerados,
            'boletins_gerados_notas' => $boletinsGeradosNotas,
            'boletins_gerados_boletim' => $boletinsGeradosBoletim,
            'default_notas_tab' => 'notas',
            'primary_color' => $primaryColor,
            'current_page' => 'notas',
            'csrf_token' => $this->generateCsrfToken(),
            'quadro_notas_semanais' => $this->montarQuadroNotasSemanais((int) $aluno['id']),
        ];

        $this->viewWithLayout('student', 'student/notas', $data);
    }

    public function boletim()
    {
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome FROM alunos a LEFT JOIN turmas t ON a.turma_id = t.id WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );
        if (!$aluno) {
            $this->redirect('/logout');
        }

        $notasLancamentoEventos = [];
        try {
            require_once __DIR__ . '/../../Models/Exams/ExamBlockManualGrade.php';
            $notasLancamentoEventos = (new ExamBlockManualGrade())->fetchNotasPorAluno((int) $aluno['id']);
        } catch (\Throwable $e) {
            $notasLancamentoEventos = [];
        }

        $boletinsGerados = [];
        $boletinsGeradosNotas = [];
        $boletinsGeradosBoletim = [];
        try {
            require_once __DIR__ . '/../../Models/System/BoletimConfig.php';
            $boletimCfg = new BoletimConfig();
            $boletimCfg->ensureSchema();
            $boletinsGerados = $boletimCfg->getGeneratedBoletinsByAluno((int) $aluno['id'], 'aluno', 'boletim');
            foreach ($boletinsGerados as $ev) {
                $exibir = strtolower(trim((string) ($ev['exibir_em'] ?? 'boletim')));
                if ($exibir === 'notas') {
                    $boletinsGeradosNotas[] = $ev;
                } else {
                    $boletinsGeradosBoletim[] = $ev;
                }
            }
        } catch (\Throwable $e) {
            $boletinsGerados = [];
            $boletinsGeradosNotas = [];
            $boletinsGeradosBoletim = [];
        }

        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $primaryColor = LayoutHelper::get('primary_color', $this->config['school']['colors']['primary'] ?? '#3b82f6');
        $quadroOficial = null;
        try {
            if (!class_exists('LayoutHelper', false) || LayoutHelper::isModuleEnabled('vida_escolar')) {
                require_once __DIR__ . '/../../Modulos/vida-escolar/Services/VidaEscolarService.php';
                $quadroOficial = (new \App\Modulos\VidaEscolar\Services\VidaEscolarService())->quadroDoAluno((int) $aluno['id']);
            }
        } catch (\Throwable $e) {
            $quadroOficial = null;
        }
        $data = [
            'title' => 'Boletim - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'provas_realizadas' => [],
            'provas_matriz_blocos' => ['tabelas' => [], 'tem_dados' => false],
            'notas_lancamento_eventos' => $notasLancamentoEventos,
            'boletins_gerados' => $boletinsGerados,
            'boletins_gerados_notas' => $boletinsGeradosNotas,
            'boletins_gerados_boletim' => $boletinsGeradosBoletim,
            'quadro_oficial' => $quadroOficial,
            'default_notas_tab' => 'boletim',
            'primary_color' => $primaryColor,
            'current_page' => 'boletim',
            'csrf_token' => $this->generateCsrfToken(),
        ];

        $this->viewWithLayout('student', 'student/notas', $data);
    }

    /**
     * Minha Carteira (saldo de créditos e histórico de movimentações)
     */
    public function carteira()
    {
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch(
            "SELECT a.* FROM alunos a WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );
        if (!$aluno) {
            $this->redirect('/logout');
        }
        require_once __DIR__ . '/../../Services/TenantCreditsCheckoutService.php';
        require_once __DIR__ . '/../../Services/CreditosService.php';
        require_once __DIR__ . '/../../Core/CreditosModuleRegistry.php';
        \App\Services\TenantCreditsCheckoutService::ensureComprasCreditosAsaasColumns($this->db->getPdo());
        $creditosService = new \App\Services\CreditosService();
        $creditosService->aplicarRecargaInicialSeAplicavel('aluno', (int) $aluno['id']);
        $walletSaldos = $creditosService->getWalletSaldos('aluno', (int) $aluno['id']);
        $saldo = $walletSaldos['saldo_total'];
        $fTipo = trim((string) ($_GET['filtro_tipo'] ?? ''));
        $fMod = trim((string) ($_GET['filtro_modulo'] ?? ''));
        $fIni = trim((string) ($_GET['data_ini'] ?? ''));
        $fFim = trim((string) ($_GET['data_fim'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 10;
        $filtroTipo = $fTipo !== '' ? $fTipo : null;
        $filtroModulo = $fMod !== '' ? $fMod : null;
        $filtroIni = $fIni !== '' ? $fIni : null;
        $filtroFim = $fFim !== '' ? $fFim : null;
        $totalMovimentacoes = $creditosService->countMovimentacoesFiltradas(
            'aluno',
            (int) $aluno['id'],
            $filtroTipo,
            $filtroModulo,
            $filtroIni,
            $filtroFim
        );
        $totalPages = max(1, (int) ceil($totalMovimentacoes / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $movimentacoes = $creditosService->getMovimentacoesFiltradas(
            'aluno',
            (int) $aluno['id'],
            $perPage,
            ($page - 1) * $perPage,
            $filtroTipo,
            $filtroModulo,
            $filtroIni,
            $filtroFim
        );
        $creditosHabilitado = $creditosService->isCreditosHabilitado();
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $primaryColor = LayoutHelper::get('primary_color', $this->config['school']['colors']['primary'] ?? '#3b82f6');
        $data = [
            'title' => 'Minha Carteira - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'saldo' => $saldo,
            'wallet_saldos' => $walletSaldos,
            'movimentacoes' => $movimentacoes,
            'creditos_habilitado' => $creditosHabilitado,
            'primary_color' => $primaryColor,
            'current_page' => 'carteira',
            'csrf_token' => $this->generateCsrfToken(),
            'filtro_tipo' => $fTipo,
            'filtro_modulo' => $fMod,
            'data_ini' => $fIni,
            'data_fim' => $fFim,
            'modulos_opcao_filtro' => \CreditosModuleRegistry::getModuleLabels(),
            'pagination' => [
                'total' => $totalMovimentacoes,
                'per_page' => $perPage,
                'page' => $page,
                'total_pages' => $totalPages,
            ],
        ];
        $this->viewWithLayout('student', 'student/carteira', $data);
    }

    /**
     * Comprar créditos: lista de pacotes (GET) ou criar compra pendente (POST)
     */
    public function carteiraComprar()
    {
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch("SELECT a.* FROM alunos a WHERE a.id = :user_id", ['user_id' => $user['id']]);
        if (!$aluno) {
            $this->redirect('/logout');
        }
        try {
            $pacotes = $this->db->fetchAll("SELECT id, creditos, valor_centavos, nome FROM pacotes_creditos WHERE ativo = 1 ORDER BY creditos ASC");
        } catch (Exception $e) {
            $pacotes = [];
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['pacote_id'])) {
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->setFlashMessage('Token inválido.', 'error');
                header('Location: ' . URL . '/carteira/comprar');
                exit;
            }
            $pacoteId = (int) $_POST['pacote_id'];
            $pacote = $this->db->fetch("SELECT id, creditos, valor_centavos FROM pacotes_creditos WHERE id = ? AND ativo = 1", [$pacoteId]);
            if (!$pacote) {
                $this->setFlashMessage('Pacote inválido.', 'error');
                header('Location: ' . URL . '/carteira/comprar');
                exit;
            }
            $compraId = $this->db->insert(
                "INSERT INTO compras_creditos (user_type, user_id, pacote_id, valor_centavos, status) VALUES ('aluno', ?, ?, ?, 'pending')",
                [$aluno['id'], $pacoteId, $pacote['valor_centavos']]
            );
            header('Location: ' . URL . '/carteira/comprar/aguardando/' . $compraId);
            exit;
        }
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $primaryColor = LayoutHelper::get('primary_color', $this->config['school']['colors']['primary'] ?? '#3b82f6');
        $data = [
            'title' => 'Comprar créditos - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'pacotes' => $pacotes,
            'primary_color' => $primaryColor,
            'current_page' => 'carteira',
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->viewWithLayout('student', 'student/carteira-comprar', $data);
    }

    /**
     * EducaShop — mesma loja de pacotes com URL dedicada (requer créditos + compra B2C/liberada).
     */
    public function educaShop()
    {
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        if (LayoutHelper::get('creditos_habilitado', '0') !== '1') {
            $this->setFlashMessage('A loja de TudiCoins não está disponível para sua escola.', 'info');
            header('Location: ' . URL . '/dashboard');
            exit;
        }
        $escolaLiberou = LayoutHelper::get('creditos_liberar_escola_comprar', '0') === '1';
        $alunoPode = LayoutHelper::get('creditos_aluno_pode_comprar', '0') === '1';
        if (!$escolaLiberou && !$alunoPode) {
            $this->setFlashMessage('A loja de TudiCoins não está disponível para sua escola.', 'info');
            header('Location: ' . URL . '/dashboard');
            exit;
        }
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch("SELECT a.* FROM alunos a WHERE a.id = :user_id", ['user_id' => $user['id']]);
        if (!$aluno) {
            $this->redirect('/logout');
        }
        require_once __DIR__ . '/../../Services/EducaShopService.php';
        require_once __DIR__ . '/../../Services/CreditosService.php';
        $educaShopService = new \App\Services\EducaShopService($this->db);
        $creditosService = new \App\Services\CreditosService();
        $creditosService->aplicarRecargaInicialSeAplicavel('aluno', (int) $aluno['id']);
        $walletSaldos = $creditosService->getWalletSaldos('aluno', (int) $aluno['id']);
        $pacotes = $educaShopService->listarPacotesVitrine();
        $categorias = $educaShopService->agruparPorCategoria($pacotes);
        try {
            $compras = $this->db->fetchAll(
                "SELECT c.id, c.valor_centavos, c.status, c.billing_type, c.asaas_payment_id, c.created_at, c.updated_at, c.paid_at,
                        p.nome AS pacote_nome, p.creditos
                 FROM compras_creditos c
                 INNER JOIN pacotes_creditos p ON p.id = c.pacote_id
                 WHERE c.user_type = 'aluno' AND c.user_id = ?
                 ORDER BY c.id DESC
                 LIMIT 50",
                [(int) $aluno['id']]
            );
        } catch (\Throwable $e) {
            $compras = [];
        }
        $primaryColor = LayoutHelper::get('primary_color', $this->config['school']['colors']['primary'] ?? '#3b82f6');
        $this->viewWithLayout('student', 'student/educashop', [
            'title' => 'EducaShop - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'pacotes' => $pacotes,
            'categorias' => $categorias,
            'wallet_saldos' => $walletSaldos,
            'saldo' => $walletSaldos['saldo_total'],
            'compras' => $compras,
            'categorias_meta' => \App\Services\EducaShopService::CATEGORIAS,
            'primary_color' => $primaryColor,
            'current_page' => 'educashop',
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function educaShopComprar()
    {
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        if (LayoutHelper::get('creditos_habilitado', '0') !== '1') {
            header('Location: ' . URL . '/dashboard');
            exit;
        }
        $escolaLiberou = LayoutHelper::get('creditos_liberar_escola_comprar', '0') === '1';
        $alunoPode = LayoutHelper::get('creditos_aluno_pode_comprar', '0') === '1';
        if (!$escolaLiberou && !$alunoPode) {
            header('Location: ' . URL . '/dashboard');
            exit;
        }
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch("SELECT a.* FROM alunos a WHERE a.id = :user_id", ['user_id' => $user['id']]);
        if (!$aluno) {
            $this->redirect('/logout');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['pacote_id'])) {
            header('Location: ' . URL . '/educashop');
            exit;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            header('Location: ' . URL . '/educashop');
            exit;
        }
        $pacoteId = (int) $_POST['pacote_id'];
        $pacote = $this->db->fetch("SELECT id, creditos, valor_centavos FROM pacotes_creditos WHERE id = ? AND ativo = 1", [$pacoteId]);
        if (!$pacote) {
            $this->setFlashMessage('Pacote inválido.', 'error');
            header('Location: ' . URL . '/educashop');
            exit;
        }
        $compraId = $this->db->insert(
            "INSERT INTO compras_creditos (user_type, user_id, pacote_id, valor_centavos, status) VALUES ('aluno', ?, ?, ?, 'pending')",
            [(int) $aluno['id'], $pacoteId, $pacote['valor_centavos']]
        );
        header('Location: ' . URL . '/carteira/comprar/aguardando/' . $compraId . '?from=educashop');
        exit;
    }

    /**
     * Página aguardando pagamento (com link para simular, em desenvolvimento)
     */
    public function carteiraComprarAguardando($compraId)
    {
        $compraId = (int) $compraId;
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch("SELECT a.* FROM alunos a WHERE a.id = :user_id", ['user_id' => $user['id']]);
        if (!$aluno) {
            $this->redirect('/logout');
        }
        $compra = $this->db->fetch(
            "SELECT c.*, p.creditos, p.nome as pacote_nome FROM compras_creditos c JOIN pacotes_creditos p ON p.id = c.pacote_id WHERE c.id = ? AND c.user_type = 'aluno' AND c.user_id = ?",
            [$compraId, $aluno['id']]
        );
        if (!$compra || ($compra['status'] ?? '') !== 'pending') {
            $this->setFlashMessage('Compra não encontrada ou já processada.', 'error');
            $voltarErro = (($_GET['from'] ?? '') === 'educashop') ? URL . '/educashop' : URL . '/carteira';
            header('Location: ' . $voltarErro);
            exit;
        }
        $fromEducashop = (($_GET['from'] ?? '') === 'educashop');
        $data = [
            'title' => 'Aguardando pagamento - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'compra' => $compra,
            'current_page' => $fromEducashop ? 'educashop' : 'carteira',
            'voltar_url' => $fromEducashop ? URL . '/educashop' : URL . '/carteira',
            'voltar_label' => $fromEducashop ? 'Voltar ao EducaShop' : 'Voltar à carteira',
            'pagar_action' => URL . '/carteira/comprar/pagar/' . (int) $compraId,
            'verificar_action' => URL . '/carteira/comprar/verificar/' . (int) $compraId,
            'status_action' => URL . '/carteira/comprar/status/' . (int) $compraId,
            'csrf_token' => $this->generateCsrfToken(),
            'pix_checkout' => null,
            'erro_pagamento' => $_SESSION['flash_message'] ?? null,
            'pagador' => [
                'nome' => $aluno['nome'] ?? '',
                'email' => $aluno['email'] ?? '',
                'cpf_cnpj' => $aluno['cpf'] ?? $aluno['cpf_cnpj'] ?? '',
                'phone' => $aluno['telefone'] ?? $aluno['celular'] ?? $aluno['whatsapp'] ?? '',
            ],
        ];
        $this->viewWithLayout('student', 'student/carteira-aguardando', $data);
    }

    public function carteiraComprarPagar($compraId)
    {
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch("SELECT a.* FROM alunos a WHERE a.id = :user_id", ['user_id' => $user['id']]);
        if (!$aluno) {
            $this->redirect('/logout');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            header('Location: ' . URL . '/carteira/comprar/aguardando/' . (int) $compraId);
            exit;
        }

        $billingType = strtoupper(trim((string) ($_POST['billing_type'] ?? 'PIX')));
        if (!in_array($billingType, ['PIX', 'CREDIT_CARD'], true)) {
            $billingType = 'PIX';
        }

        $compra = $this->db->fetch(
            "SELECT c.*, p.creditos, p.nome as pacote_nome FROM compras_creditos c JOIN pacotes_creditos p ON p.id = c.pacote_id WHERE c.id = ? AND c.user_type = 'aluno' AND c.user_id = ?",
            [$compraId, $aluno['id']]
        );
        if (!$compra || ($compra['status'] ?? '') !== 'pending') {
            $this->setFlashMessage('Compra não encontrada ou já processada.', 'error');
            header('Location: ' . URL . '/carteira');
            exit;
        }

        require_once __DIR__ . '/../../Services/TenantCreditsCheckoutService.php';
        require_once __DIR__ . '/../../Services/CreditosTenantCheckoutHelper.php';
        $escolaId = \App\Services\CreditosTenantCheckoutHelper::escolaIdFromConfig($this->config);
        $result = \App\Services\TenantCreditsCheckoutService::createOrReuse(
            $this->db->getPdo(),
            $escolaId,
            $compra,
            $billingType,
            [
                'nome' => trim((string) ($_POST['payer_nome'] ?? ($aluno['nome'] ?? 'Aluno'))),
                'email' => trim((string) ($_POST['payer_email'] ?? ($aluno['email'] ?? 'sem-email@educatudo.local'))),
                'cpf_cnpj' => trim((string) ($_POST['payer_cpf_cnpj'] ?? ($aluno['cpf'] ?? $aluno['cpf_cnpj'] ?? ''))),
                'phone' => trim((string) ($_POST['payer_phone'] ?? ($aluno['telefone'] ?? $aluno['celular'] ?? $aluno['whatsapp'] ?? ''))),
            ]
        );

        if (!($result['ok'] ?? false)) {
            $data = [
                'title' => 'Aguardando pagamento - EducaTudo',
                'user' => $user,
                'aluno' => $aluno,
                'compra' => $compra,
                'current_page' => 'carteira',
                'pagar_action' => URL . '/carteira/comprar/pagar/' . (int) $compraId,
                'verificar_action' => URL . '/carteira/comprar/verificar/' . (int) $compraId,
                'status_action' => URL . '/carteira/comprar/status/' . (int) $compraId,
                'csrf_token' => $this->generateCsrfToken(),
                'pix_checkout' => null,
                'erro_pagamento' => $result['message'] ?? 'Falha ao iniciar pagamento.',
                'pagador' => [
                    'nome' => trim((string) ($_POST['payer_nome'] ?? ($aluno['nome'] ?? ''))),
                    'email' => trim((string) ($_POST['payer_email'] ?? ($aluno['email'] ?? ''))),
                    'cpf_cnpj' => trim((string) ($_POST['payer_cpf_cnpj'] ?? ($aluno['cpf'] ?? $aluno['cpf_cnpj'] ?? ''))),
                    'phone' => trim((string) ($_POST['payer_phone'] ?? ($aluno['telefone'] ?? $aluno['celular'] ?? $aluno['whatsapp'] ?? ''))),
                ],
            ];
            $this->viewWithLayout('student', 'student/carteira-aguardando', $data);
            return;
        }

        if ($billingType === 'PIX' && !empty($result['pix'])) {
            $data = [
                'title' => 'Aguardando pagamento - EducaTudo',
                'user' => $user,
                'aluno' => $aluno,
                'compra' => $compra,
                'current_page' => 'carteira',
                'pagar_action' => URL . '/carteira/comprar/pagar/' . (int) $compraId,
                'verificar_action' => URL . '/carteira/comprar/verificar/' . (int) $compraId,
                'status_action' => URL . '/carteira/comprar/status/' . (int) $compraId,
                'csrf_token' => $this->generateCsrfToken(),
                'pix_checkout' => $result['pix'],
                'erro_pagamento' => null,
                'pagador' => [
                    'nome' => trim((string) ($_POST['payer_nome'] ?? ($aluno['nome'] ?? ''))),
                    'email' => trim((string) ($_POST['payer_email'] ?? ($aluno['email'] ?? ''))),
                    'cpf_cnpj' => trim((string) ($_POST['payer_cpf_cnpj'] ?? ($aluno['cpf'] ?? $aluno['cpf_cnpj'] ?? ''))),
                    'phone' => trim((string) ($_POST['payer_phone'] ?? ($aluno['telefone'] ?? $aluno['celular'] ?? $aluno['whatsapp'] ?? ''))),
                ],
            ];
            $this->viewWithLayout('student', 'student/carteira-aguardando', $data);
            return;
        }

        if (!empty($result['checkout_url'])) {
            header('Location: ' . $result['checkout_url']);
            exit;
        }

        $this->setFlashMessage('Não foi possível iniciar o pagamento.', 'error');
        header('Location: ' . URL . '/carteira/comprar/aguardando/' . (int) $compraId);
        exit;
    }

    public function carteiraComprarVerificar($compraId)
    {
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch("SELECT a.* FROM alunos a WHERE a.id = :user_id", ['user_id' => $user['id']]);
        if (!$aluno) {
            $this->redirect('/logout');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            header('Location: ' . URL . '/carteira/comprar/aguardando/' . (int) $compraId);
            exit;
        }

        $compra = $this->db->fetch(
            "SELECT c.*, p.creditos, p.nome as pacote_nome FROM compras_creditos c JOIN pacotes_creditos p ON p.id = c.pacote_id WHERE c.id = ? AND c.user_type = 'aluno' AND c.user_id = ?",
            [$compraId, $aluno['id']]
        );
        if (!$compra) {
            $this->setFlashMessage('Compra não encontrada.', 'error');
            header('Location: ' . URL . '/carteira');
            exit;
        }
        if (($compra['status'] ?? '') === 'paid') {
            $this->setFlashMessage('Pagamento já confirmado e créditos liberados.', 'success');
            header('Location: ' . URL . '/carteira');
            exit;
        }

        require_once __DIR__ . '/../../Services/TenantCreditsCheckoutService.php';
        require_once __DIR__ . '/../../Services/CreditosTenantCheckoutHelper.php';
        $escolaId = \App\Services\CreditosTenantCheckoutHelper::escolaIdFromConfig($this->config);
        $result = \App\Services\TenantCreditsCheckoutService::verifyAndFulfill(
            $this->db->getPdo(),
            $escolaId,
            $compra
        );

        if ($result['ok'] ?? false) {
            $this->setFlashMessage('Pagamento confirmado e créditos liberados com sucesso.', 'success');
            header('Location: ' . URL . '/carteira');
            exit;
        }

        $this->setFlashMessage((string) ($result['message'] ?? 'Pagamento ainda não confirmado.'), 'info');
        header('Location: ' . URL . '/carteira/comprar/aguardando/' . (int) $compraId);
        exit;
    }

    public function carteiraComprarStatus($compraId)
    {
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch("SELECT a.* FROM alunos a WHERE a.id = :user_id", ['user_id' => $user['id']]);
        if (!$aluno) {
            $this->json(['ok' => false, 'message' => 'Aluno não encontrado.'], 404);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['ok' => false, 'message' => 'Token inválido.'], 400);
        }

        $compra = $this->db->fetch(
            "SELECT c.*, p.creditos, p.nome as pacote_nome FROM compras_creditos c JOIN pacotes_creditos p ON p.id = c.pacote_id WHERE c.id = ? AND c.user_type = 'aluno' AND c.user_id = ?",
            [$compraId, $aluno['id']]
        );
        if (!$compra) {
            $this->json(['ok' => false, 'message' => 'Compra não encontrada.'], 404);
        }
        if (($compra['status'] ?? '') === 'paid') {
            $this->json([
                'ok' => true,
                'paid' => true,
                'message' => 'Pagamento confirmado e créditos liberados.',
                'creditos' => (float) ($compra['creditos'] ?? 0),
                'redirect_url' => URL . '/carteira',
            ]);
        }

        require_once __DIR__ . '/../../Services/TenantCreditsCheckoutService.php';
        require_once __DIR__ . '/../../Services/CreditosTenantCheckoutHelper.php';
        $escolaId = \App\Services\CreditosTenantCheckoutHelper::escolaIdFromConfig($this->config);
        $result = \App\Services\TenantCreditsCheckoutService::verifyAndFulfill(
            $this->db->getPdo(),
            $escolaId,
            $compra
        );

        $this->json([
            'ok' => (bool) ($result['ok'] ?? false),
            'paid' => (bool) ($result['ok'] ?? false),
            'message' => (string) ($result['message'] ?? 'Pagamento ainda não confirmado.'),
            'status' => (string) ($result['status'] ?? ''),
            'creditos' => (float) ($compra['creditos'] ?? 0),
            'redirect_url' => URL . '/carteira',
        ]);
    }

    /**
     * Simular pagamento concluído (MVP/teste): marca compra como paga e credita
     */
    public function carteiraComprarSimular($compraId)
    {
        if (!defined('DEBUG') || !DEBUG) {
            http_response_code(404);
            exit;
        }
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch("SELECT a.* FROM alunos a WHERE a.id = :user_id", ['user_id' => $user['id']]);
        if (!$aluno) {
            $this->redirect('/logout');
        }
        $compra = $this->db->fetch(
            "SELECT c.*, p.creditos FROM compras_creditos c JOIN pacotes_creditos p ON p.id = c.pacote_id WHERE c.id = ? AND c.user_type = 'aluno' AND c.user_id = ? AND c.status = 'pending'",
            [$compraId, $aluno['id']]
        );
        if (!$compra) {
            $this->setFlashMessage('Compra não encontrada ou já processada.', 'error');
            header('Location: ' . URL . '/carteira');
            exit;
        }
        require_once __DIR__ . '/../../Services/CreditosService.php';
        $creditosService = new \App\Services\CreditosService();
        $creditosService->adicionarCreditos('aluno', (int) $aluno['id'], (float) ($compra['creditos'] ?? 0), 'compra', null, (string) $compraId);
        $this->db->query("UPDATE compras_creditos SET status = 'paid', updated_at = NOW() WHERE id = ?", [$compraId]);
        $this->setFlashMessage('Pagamento simulado: ' . $compra['creditos'] . ' créditos adicionados.', 'success');
        header('Location: ' . URL . '/carteira');
        exit;
    }

    /**
     * Planos de assinatura (créditos mensais): listar e assinar. Só acessível se B2C estiver liberado no Master.
     */
    public function carteiraPlanos()
    {
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch("SELECT a.* FROM alunos a WHERE a.id = :user_id", ['user_id' => $user['id']]);
        if (!$aluno) {
            $this->redirect('/logout');
        }
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        if (LayoutHelper::get('creditos_liberar_b2c', '0') !== '1') {
            $this->setFlashMessage('Planos de assinatura não estão disponíveis para esta escola.', 'info');
            header('Location: ' . URL . '/carteira');
            exit;
        }
        try {
            $planos = $this->db->fetchAll("SELECT id, nome, creditos_mensais, valor_mensal, destino FROM planos_creditos WHERE ativo = 1 AND (destino = 'aluno' OR destino = 'ambos') ORDER BY creditos_mensais");
            $assinaturas = $this->db->fetchAll("SELECT a.id, a.plano_id, a.inicio_em, a.ativa, p.nome as plano_nome, p.creditos_mensais FROM assinaturas_creditos a JOIN planos_creditos p ON p.id = a.plano_id WHERE a.user_type = 'aluno' AND a.user_id = ? ORDER BY a.id DESC", [$aluno['id']]);
        } catch (Exception $e) {
            $planos = [];
            $assinaturas = [];
        }
        $data = [
            'title' => 'Planos de créditos - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'planos' => $planos,
            'assinaturas' => $assinaturas,
            'current_page' => 'carteira',
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->viewWithLayout('student', 'student/carteira-planos', $data);
    }

    public function carteiraPlanosAssinar()
    {
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch("SELECT a.* FROM alunos a WHERE a.id = :user_id", ['user_id' => $user['id']]);
        if (!$aluno) {
            $this->redirect('/logout');
        }
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        if (LayoutHelper::get('creditos_liberar_b2c', '0') !== '1') {
            $this->setFlashMessage('Planos de assinatura não estão disponíveis para esta escola.', 'info');
            header('Location: ' . URL . '/carteira');
            exit;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            header('Location: ' . URL . '/carteira/planos');
            exit;
        }
        $planoId = (int) ($_POST['plano_id'] ?? 0);
        $plano = $this->db->fetch("SELECT id, creditos_mensais FROM planos_creditos WHERE id = ? AND ativo = 1 AND (destino = 'aluno' OR destino = 'ambos')", [$planoId]);
        if (!$plano) {
            $this->setFlashMessage('Plano inválido.', 'error');
            header('Location: ' . URL . '/carteira/planos');
            exit;
        }
        $hoje = date('Y-m-d');
        $this->db->insert(
            "INSERT INTO assinaturas_creditos (user_type, user_id, plano_id, inicio_em, ativa) VALUES ('aluno', ?, ?, ?, 1)",
            [$aluno['id'], $planoId, $hoje]
        );
        $this->setFlashMessage('Assinatura ativada. Você receberá ' . $plano['creditos_mensais'] . ' créditos por mês.', 'success');
        header('Location: ' . URL . '/carteira/planos');
        exit;
    }

    /**
     * Página do Mural de Recados (aluno) com filtros: Professor, Matéria, Entre datas.
     */
    public function muralRecados()
    {
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome FROM alunos a LEFT JOIN turmas t ON a.turma_id = t.id WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );
        if (!$aluno) {
            $this->redirect('/logout');
        }
        $aluno_turma_id = isset($aluno['turma_id']) && $aluno['turma_id'] !== '' ? (int) $aluno['turma_id'] : 0;
        $filtro_professor = isset($_GET['professor_id']) ? (int)$_GET['professor_id'] : '';
        $filtro_materia = isset($_GET['materia_id']) ? (int)$_GET['materia_id'] : '';
        $filtro_data_de = trim($_GET['data_de'] ?? '');
        $filtro_data_ate = trim($_GET['data_ate'] ?? '');
        $recados = $this->getMuralRecadosParaAlunoComFiltros($aluno['id'], $aluno_turma_id, [
            'professor_id' => $filtro_professor,
            'materia_id' => $filtro_materia,
            'data_de' => $filtro_data_de,
            'data_ate' => $filtro_data_ate,
        ]);
        $recadosCompletos = [];
        foreach ($recados as $r) {
            $r['ja_visto'] = (bool) $this->db->fetch("SELECT 1 FROM mural_recados_vistos WHERE mural_recado_id = :rid AND aluno_id = :aid", ['rid' => $r['id'], 'aid' => $aluno['id']]);
            $recadosCompletos[] = $r;
        }
        $recados = $recadosCompletos;
        $professores_opcoes = $this->db->fetchAll("SELECT DISTINCT p.id, p.nome FROM mural_recados r JOIN professores p ON p.id = r.autor_id WHERE r.autor_tipo = 'professor' ORDER BY p.nome");
        $materias_opcoes = [];
        try {
            $materias_opcoes = $this->db->fetchAll("SELECT id, nome FROM materias ORDER BY nome");
        } catch (\Throwable $e) {
        }
        if (empty($materias_opcoes)) {
            try {
                $materias_opcoes = $this->db->fetchAll("SELECT id, nome FROM jornadas_materias ORDER BY nome");
            } catch (\Throwable $e) {
            }
        }
        $data = [
            'title' => 'Mural de Recados - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'recados' => $recados,
            'filtro_professor' => $filtro_professor,
            'filtro_materia' => $filtro_materia,
            'filtro_data_de' => $filtro_data_de,
            'filtro_data_ate' => $filtro_data_ate,
            'professores_opcoes' => $professores_opcoes,
            'materias_opcoes' => $materias_opcoes,
            'current_page' => 'mural-recados',
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->viewWithLayout('student', 'student/mural-recados/index', $data);
    }

    /**
     * Marcar recado(s) do mural como visto (POST, JSON)
     */
    public function marcarVistoMuralRecado()
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch("SELECT id FROM alunos WHERE id = :user_id", ['user_id' => $user['id']]);
        if (!$aluno) {
            echo json_encode(['success' => false, 'error' => 'Aluno não encontrado']);
            return;
        }
        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', array_filter($_POST['ids'])) : [];
        if (empty($ids)) {
            echo json_encode(['success' => true]);
            return;
        }
        foreach ($ids as $rid) {
            $this->db->query(
                "INSERT IGNORE INTO mural_recados_vistos (mural_recado_id, aluno_id) VALUES (:rid, :aid)",
                ['rid' => $rid, 'aid' => $aluno['id']]
            );
        }
        echo json_encode(['success' => true]);
    }

    public function chat()
    {
        $user = $this->authManager->getUser();
        
        $aluno = $this->db->fetch(
            "SELECT a.* FROM alunos a 
             WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );

        if (!$aluno) {
            $this->redirect('/logout');
        }

        // Gerar token CSRF
        $csrf_token = $this->generateCsrfToken();

        // Buscar conversas do aluno
        $conversas = $this->getStudentConversations($aluno['id']);

        $data = [
            'title' => 'Chat Tudinha - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'conversas' => $conversas,
            'current_page' => 'chat',
            'csrf_token' => $csrf_token
        ];

        $this->viewWithLayout('student', 'student/chat', $data);
    }

    public function createConversation()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json([
                'error' => 'Token inválido',
                'csrf_token' => $this->refreshCsrfToken()
            ], 400);
        }

        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }
            
            $this->assertAlunoAtivo($aluno);

            $titulo = $_POST['titulo'] ?? '';
            $materia = $_POST['materia'] ?? '';

            if (empty($titulo)) {
                throw new Exception('Título da conversa é obrigatório');
            }

            $conversa_id = $this->db->insert(
                "INSERT INTO tudinha_conversas (aluno_id, titulo, materia, created_at) 
                 VALUES (:aluno_id, :titulo, :materia, NOW())",
                [
                    'aluno_id' => $aluno['id'],
                    'titulo' => $titulo,
                    'materia' => $materia
                ]
            );

            // Buscar dados da conversa criada
            $conversa = $this->db->fetch(
                "SELECT c.* 
                 FROM tudinha_conversas c 
                 WHERE c.id = :conversa_id",
                ['conversa_id' => $conversa_id]
            );
            
            if ($conversa) {
                $conversa['materia_nome'] = $conversa['materia']; // materia já contém o nome
            }

            $this->json(['success' => true, 'conversa' => $conversa]);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function uploadImage()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido', 'csrf_token' => $this->refreshCsrfToken()], 400);
        }

        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erro no upload da imagem');
            }

            $file = $_FILES['imagem'];
            
            // Validar tipo de arquivo
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('Tipo de arquivo não permitido');
            }

            // Validar tamanho (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('Arquivo muito grande (máximo 5MB)');
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'chat_' . $aluno['id'] . '_' . time() . '_' . uniqid() . '.' . $extension;
            require_once __DIR__ . '/../../Services/MediaStorageService.php';
            $key = MediaStorageService::userKey('student', $aluno['id'], $filename);
            $media = new MediaStorageService($this->config);
            if (!$media->put('chat', $key, $file['tmp_name'], $file['type'])) {
                throw new Exception('Erro ao salvar arquivo');
            }

            // Sempre retornar caminho relativo (evita "Rota não encontrada: /https://...")
            $path = (defined('FOLDER') && FOLDER !== '') ? rtrim(FOLDER, '/') : '';
            $path .= '/media/serve?type=chat&key=' . rawurlencode($key);
            if ($path !== '' && strpos($path, '/') !== 0) {
                $path = '/' . $path;
            }
            $this->json(['success' => true, 'image_url' => $path]);

        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function uploadPdf()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido', 'csrf_token' => $this->refreshCsrfToken()], 400);
        }

        try {
            $user = $this->authManager->getUser();

            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erro no upload do PDF');
            }

            $file = $_FILES['pdf'];

            // Validar tipo real do arquivo (MIME real, não a extensão/Content-Type do cliente)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeReal = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
            if ($finfo) {
                finfo_close($finfo);
            }
            if ($mimeReal !== 'application/pdf') {
                throw new Exception('Tipo de arquivo não permitido');
            }

            // Validar tamanho (max 15MB)
            if ($file['size'] > 15 * 1024 * 1024) {
                throw new Exception('Arquivo muito grande (máximo 15MB)');
            }

            $filename = 'chat_' . $aluno['id'] . '_' . time() . '_' . uniqid() . '.pdf';
            require_once __DIR__ . '/../../Services/MediaStorageService.php';
            $key = MediaStorageService::userKey('student', $aluno['id'], $filename);
            $media = new MediaStorageService($this->config);
            if (!$media->put('chat', $key, $file['tmp_name'], 'application/pdf')) {
                throw new Exception('Erro ao salvar arquivo');
            }

            $path = (defined('FOLDER') && FOLDER !== '') ? rtrim(FOLDER, '/') : '';
            $path .= '/media/serve?type=chat&key=' . rawurlencode($key);
            if ($path !== '' && strpos($path, '/') !== 0) {
                $path = '/' . $path;
            }
            $this->json(['success' => true, 'file_url' => $path]);

        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function sendMessage()
    {
        // Limpar qualquer output anterior
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Garantir que não há warnings/notices sendo exibidos
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
        ini_set('display_errors', 0);
        
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido', 'csrf_token' => $this->refreshCsrfToken()], 400);
            return;
        }

        // IA pode demorar; evitar que PHP mate o script antes da resposta
        set_time_limit(180);
        ini_set('max_execution_time', '180');

        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a 
                 WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }
            
            $this->assertAlunoAtivo($aluno);
            
            $this->assertAlunoAtivo($aluno);

            // Verificar limite diário de interações de chat
            $verificacao = $this->podeExecutarAcao($aluno['id'], 'chat_interacoes');
            if (!$verificacao['pode']) {
                $this->json(['error' => $verificacao['mensagem']], 403);
                return;
            }

            $conversa_id = $_POST['conversa_id'] ?? '';
            $mensagem = $_POST['mensagem'] ?? '';
            $tipo = $_POST['tipo'] ?? 'texto'; // texto, imagem, audio
            $imageUrl = $_POST['image_url'] ?? null;
            // Normalizar image_url para caminho relativo (para carregar sempre no host atual)
            if (!empty($imageUrl)) {
                $path = parse_url($imageUrl, PHP_URL_PATH);
                if ($path !== null && $path !== false && $path !== '' && strpos($path, 'storage/chat') !== false) {
                    $imageUrl = (strpos($path, '/') === 0 ? $path : '/' . $path);
                }
            }

            // Crédito distinto para envio de imagem/PDF no chat (processamento extra) vs. só texto
            $tudinhaModuloCredito = ((($tipo === 'imagem') || ($tipo === 'pdf')) && !empty($imageUrl)) ? 'tudinha_chat_imagem' : 'tudinha_mensagem';

            if (empty($conversa_id)) {
                throw new Exception('ID da conversa é obrigatório');
            }

            if (empty($mensagem) && empty($imageUrl)) {
                throw new Exception('Mensagem ou imagem é obrigatória');
            }

            // Verificar se a conversa pertence ao aluno
            $conversa = $this->db->fetch(
                "SELECT * FROM tudinha_conversas WHERE id = :conversa_id AND aluno_id = :aluno_id",
                ['conversa_id' => $conversa_id, 'aluno_id' => $aluno['id']]
            );

            if (!$conversa) {
                throw new Exception('Conversa não encontrada');
            }

            // Salvar mensagem do aluno
            $mensagem_id = $this->db->insert(
                "INSERT INTO tudinha_mensagens (conversa_id, aluno_id, mensagem, tipo, image_url, created_at) 
                 VALUES (:conversa_id, :aluno_id, :mensagem, :tipo, :image_url, NOW())",
                [
                    'conversa_id' => $conversa_id,
                    'aluno_id' => $aluno['id'],
                    'mensagem' => $mensagem,
                    'tipo' => $tipo,
                    'image_url' => $imageUrl
                ]
            );

            // Monitorar mensagem do aluno (passa mensagem_id para recuperar resposta Tudinha no alerta)
            $this->monitorarChatMensagem($aluno, $conversa_id, $mensagem, 'aluno', $mensagem_id);

            // Atualizar timestamp e recalcular interações da conversa
            $this->db->update(
                "UPDATE tudinha_conversas SET 
                 updated_at = NOW(),
                 ultima_atividade = NOW(),
                 total_interacoes = (
                     SELECT LEAST(
                         (SELECT COUNT(*) FROM tudinha_mensagens WHERE conversa_id = :conversa_id1 AND is_ia = 0),
                         (SELECT COUNT(*) FROM tudinha_mensagens WHERE conversa_id = :conversa_id2 AND is_ia = 1)
                     )
                 )
                 WHERE id = :conversa_id3",
                [
                    'conversa_id1' => $conversa_id,
                    'conversa_id2' => $conversa_id,
                    'conversa_id3' => $conversa_id
                ]
            );

            // Se o aluno enviou uma imagem, extrair texto via OCR para a Tudinha responder sobre o conteúdo
            if ($tipo === 'imagem' && !empty($imageUrl)) {
                require_once __DIR__ . '/../../Services/MediaStorageService.php';
                $mediaChat = new MediaStorageService($this->config);
                $imageContent = null;
                $chatKey = null;

                // URL no formato /media/serve?type=chat&key=student%2F3%2Fchat_xxx.jpg → extrair key
                if (strpos($imageUrl, '/media/serve') !== false || strpos($imageUrl, 'type=chat&key=') !== false) {
                    parse_str(parse_url($imageUrl, PHP_URL_QUERY) ?: '', $params);
                    $chatKey = isset($params['key']) ? rawurldecode($params['key']) : null;
                    if ($chatKey !== null && $chatKey !== '') {
                        $imageContent = $mediaChat->getContents('chat', $chatKey);
                    }
                }
                // Legado: image_url era path com filename (ex: /chat/ver-imagem?f=chat_3_xxx.jpg)
                if ($imageContent === null) {
                    $pathInfo = parse_url($imageUrl, PHP_URL_PATH);
                    $filename = $pathInfo ? basename($pathInfo) : '';
                    parse_str(parse_url($imageUrl, PHP_URL_QUERY) ?: '', $q);
                    if (isset($q['f']) && (string)$q['f'] !== '') {
                        $filename = $q['f'];
                    }
                    if (strpos($filename, '?') !== false) {
                        $filename = strstr($filename, '?', true) ?: $filename;
                    }
                    if ($filename && preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
                        $filePath = $mediaChat->getLocalPath('chat', $filename);
                        if ($filePath !== null && is_file($filePath)) {
                            $imageContent = @file_get_contents($filePath);
                        }
                    }
                }

                if ($imageContent !== null && $imageContent !== '') {
                    // A IA passa a "ver" a imagem de verdade (visão multimodal, montada em
                    // gerarResposta() logo abaixo) em vez de só ler texto extraído por OCR.
                    // A mensagem salva no banco vira só uma legenda curta (a que o aluno
                    // escreveu, se escreveu, senão um placeholder) — histórico de turnos
                    // futuros não reenvia a imagem (custo), só essa legenda como referência.
                    $finfoImg = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeImg = $finfoImg ? finfo_buffer($finfoImg, $imageContent) : false;
                    if ($finfoImg) {
                        finfo_close($finfoImg);
                    }
                    $mimeImg = in_array($mimeImg, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true) ? $mimeImg : 'image/jpeg';
                    $imagemDataUriVisao = 'data:' . $mimeImg . ';base64,' . base64_encode($imageContent);
                    if (trim($mensagem) === '') {
                        $mensagem = '[Imagem enviada pelo aluno]';
                        $this->db->update(
                            "UPDATE tudinha_mensagens SET mensagem = :mensagem WHERE id = :id",
                            ['mensagem' => $mensagem, 'id' => $mensagem_id]
                        );
                    }
                } else {
                    $mensagem = "[O aluno enviou uma imagem. O arquivo não está mais disponível para leitura. Peça que ele descreva ou envie novamente.]";
                    $this->db->update(
                        "UPDATE tudinha_mensagens SET mensagem = :mensagem WHERE id = :id",
                        ['mensagem' => $mensagem, 'id' => $mensagem_id]
                    );
                    $imageUrl = null;
                }
            }

            // PDF: extração de texto (sem OCR/visão — PDF é conteúdo textual, não imagem)
            if ($tipo === 'pdf' && !empty($imageUrl)) {
                $fileUrlPdf = $imageUrl;
                require_once __DIR__ . '/../../Services/MediaStorageService.php';
                require_once __DIR__ . '/../../Services/PdfTextExtractorService.php';
                $mediaChatPdf = new MediaStorageService($this->config);
                $pdfContent = null;

                if (strpos($fileUrlPdf, '/media/serve') !== false || strpos($fileUrlPdf, 'type=chat&key=') !== false) {
                    parse_str(parse_url($fileUrlPdf, PHP_URL_QUERY) ?: '', $paramsPdf);
                    $chatKeyPdf = isset($paramsPdf['key']) ? rawurldecode($paramsPdf['key']) : null;
                    if ($chatKeyPdf !== null && $chatKeyPdf !== '') {
                        $pdfContent = $mediaChatPdf->getContents('chat', $chatKeyPdf);
                    }
                }

                if ($pdfContent !== null && $pdfContent !== '') {
                    $extraido = \App\Services\PdfTextExtractorService::extrairTexto($pdfContent);
                    $textoPdf = trim($extraido['texto']);
                    $legendaAluno = trim($mensagem);
                    if ($textoPdf !== '') {
                        $mensagem = ($legendaAluno !== '' ? $legendaAluno . "\n\n" : '')
                            . "[Conteúdo extraído do PDF enviado pelo aluno:]\n\n" . $textoPdf
                            . ($extraido['truncado'] ? "\n\n[...texto truncado, PDF muito longo]" : '');
                    } else {
                        $mensagem = ($legendaAluno !== '' ? $legendaAluno . "\n\n" : '')
                            . "[O aluno enviou um PDF, mas não foi possível extrair texto dele (pode ser um PDF escaneado/imagem). Peça que ele descreva ou digite o conteúdo.]";
                    }
                    $this->db->update(
                        "UPDATE tudinha_mensagens SET mensagem = :mensagem WHERE id = :id",
                        ['mensagem' => $mensagem, 'id' => $mensagem_id]
                    );
                } else {
                    $mensagem = "[O aluno enviou um PDF. O arquivo não está mais disponível para leitura. Peça que ele descreva ou envie novamente.]";
                    $this->db->update(
                        "UPDATE tudinha_mensagens SET mensagem = :mensagem WHERE id = :id",
                        ['mensagem' => $mensagem, 'id' => $mensagem_id]
                    );
                }
            }

            // Verificar se o usuário pediu para criar/gerar uma imagem — checagem por
            // presença de verbo de criação E substantivo de imagem em qualquer lugar da
            // frase (não exige que fiquem colados: "criar uma imagem explicativa de..."
            // tem "uma"/"explicativa" no meio e não batia no regex antigo, que exigia
            // verbo+espaço+substantivo direto).
            $mensagemLower = mb_strtolower($mensagem ?? '', 'UTF-8');
            $temVerboCriacaoImagem = preg_match('/\b(criar?|crie|gerar?|gere|desenh\w*|fa[çc]a|faz|pode(ria)?|gostaria|quero|preciso|monta|monte|elabora|elabore)\b/iu', $mensagemLower);
            // "imagem" pluraliza pra "imagens" (troca o m final por ns) — não dá pra
            // simplificar com "imagens?" (isso só bateria com "imagen"/"imagens", nunca
            // com "imagem" sozinho, que é o caso mais comum). Listado à parte.
            $temSubstantivoImagem = preg_match('/\b(imagem|imagens|fotos?|desenhos?|ilustra[cç][aã]o|ilustra[cç][oõ]es|figuras?|gr[aá]ficos?|infogr[aá]fico|diagramas?)\b/iu', $mensagemLower);
            $pediuImagem = $temVerboCriacaoImagem && $temSubstantivoImagem;

            // Confirmação de uma oferta anterior: se a última resposta da IA continha o
            // marcador de oferta ("gere uma imagem", ver prompt) e o aluno respondeu com
            // uma confirmação curta, trata como pedido de imagem mesmo sem repetir a
            // palavra "imagem" (ex.: aluno só responde "sim" ou "pode gerar") — usa o
            // assunto da própria resposta da IA como base do prompt, já que "sim" sozinho
            // não diz nada pro gerador de imagem.
            $promptBaseConfirmacao = null;
            if (!$pediuImagem && preg_match('/^\s*(sim|pode|pode gerar|gera|gere|quero|manda ver|beleza|ok|claro)[\s,.!]*$/iu', trim($mensagem ?? ''))) {
                require_once __DIR__ . '/../../Services/TudinhaService.php';
                $tudinhaServiceCheckOferta = new \App\Services\TudinhaService();
                $historicoCheckOferta = $tudinhaServiceCheckOferta->carregarHistorico($conversa_id, 3);
                foreach (array_reverse($historicoCheckOferta) as $msgHist) {
                    if ($msgHist['is_ia'] == 1) {
                        if (stripos($msgHist['mensagem'], 'gere uma imagem') !== false || stripos($msgHist['mensagem'], 'gerar uma imagem') !== false) {
                            $pediuImagem = true;
                            $promptBaseConfirmacao = trim(strip_tags($msgHist['mensagem']));
                        }
                        break;
                    }
                }
            }

            // Verificar se o usuário pediu flashcards — mesma estratégia de detecção
            // (verbo de criação + substantivo), só roda se não foi pedido de imagem
            // (evita os dois branches disputarem a mesma mensagem).
            $pediuFlashcard = false;
            if (!$pediuImagem) {
                $temVerboCriacaoFlashcard = preg_match('/\b(criar?|crie|gerar?|gere|faz|fa[çc]a|pode(ria)?|gostaria|quero|preciso|monta|monte)\b/iu', $mensagemLower);
                $temSubstantivoFlashcard = preg_match('/\b(flash\s?cards?|cart[aã]o(?:zinho)?s?\s+de\s+(estudo|memoriza[cç][aã]o))\b/iu', $mensagemLower);
                $pediuFlashcard = $temVerboCriacaoFlashcard && $temSubstantivoFlashcard;
            }

            if ($pediuFlashcard) {
                $grade = '';
                try {
                    $alunoTurma = $this->db->fetch(
                        'SELECT t.nome as turma_nome FROM alunos a LEFT JOIN turmas t ON a.turma_id = t.id WHERE a.id = :id',
                        ['id' => $aluno['id']]
                    );
                    $grade = $alunoTurma['turma_nome'] ?? '';
                } catch (Exception $eGrade) {
                    // segue com série vazia
                }

                require_once __DIR__ . '/../../Services/TudinhaService.php';
                $tudinhaServiceFlash = new \App\Services\TudinhaService();
                $historicoFlash = $tudinhaServiceFlash->carregarHistorico($conversa_id, 10);
                $contextoConversaFlash = '';
                if (!empty($historicoFlash)) {
                    foreach (array_slice($historicoFlash, -3) as $msgFlash) {
                        if ($msgFlash['is_ia'] == 1) {
                            $contextoConversaFlash .= "Contexto: " . strip_tags($msgFlash['mensagem']) . "\n";
                        }
                    }
                }

                try {
                    require_once __DIR__ . '/../../AI/ContextoExecucao.php';
                    require_once __DIR__ . '/../../AI/ExecutorPipeline.php';
                    require_once __DIR__ . '/../../AI/Agentes/InterpretadorPedidoFlashcardAgent.php';
                    require_once __DIR__ . '/../../AI/Agentes/EnfileiradorFlashcardAgent.php';

                    $execContextoFlash = (new \App\AI\ContextoExecucao())
                        ->set('pedido_aluno', trim($mensagem))
                        ->set('contexto_conversa', trim($contextoConversaFlash))
                        ->set('aluno_id', (int) $aluno['id'])
                        ->set('grade', $grade);

                    $execContextoFlash = \App\AI\ExecutorPipeline::executar([
                        new \App\AI\Agentes\InterpretadorPedidoFlashcardAgent(),
                        new \App\AI\Agentes\EnfileiradorFlashcardAgent(),
                    ], $execContextoFlash);

                    $this->json([
                        'success' => true,
                        'flashcard_job_id' => $execContextoFlash->get('job_id'),
                        'topico' => $execContextoFlash->get('topico_flashcard'),
                    ]);
                    return;
                } catch (Exception $flashError) {
                    if (stripos($flashError->getMessage(), 'TudiCoins') !== false || stripos($flashError->getMessage(), 'insuficientes') !== false || stripos($flashError->getMessage(), 'Créditos') !== false) {
                        $mid = $this->db->insert(
                            "INSERT INTO tudinha_mensagens (conversa_id, aluno_id, mensagem, tipo, is_ia, created_at) VALUES (:cid, :aid, :msg, 'texto', 1, NOW())",
                            ['cid' => $conversa_id, 'aid' => $aluno['id'], 'msg' => $flashError->getMessage()]
                        );
                        $this->json(['success' => true, 'mensagem_id' => $mid]);
                        return;
                    }
                    $mid = $this->db->insert(
                        "INSERT INTO tudinha_mensagens (conversa_id, aluno_id, mensagem, tipo, is_ia, created_at) VALUES (:cid, :aid, :msg, 'texto', 1, NOW())",
                        ['cid' => $conversa_id, 'aid' => $aluno['id'], 'msg' => 'Não consegui criar os flashcards agora. Tente novamente em instantes.']
                    );
                    $this->json(['success' => true, 'mensagem_id' => $mid]);
                    return;
                }
            }

            if ($pediuImagem) {
                // Usa a mensagem inteira como prompt — o ConstrutorPromptImagemAgent
                // (pipeline de IA abaixo) que interpreta a frase, não precisa "limpar"
                // palavras de comando aqui. Se veio de uma confirmação curta ("sim"),
                // usa o assunto da resposta anterior da IA como base.
                $prompt = $promptBaseConfirmacao ?? trim($mensagem);
                if (empty($prompt)) {
                    $prompt = $mensagem;
                }

                // Carregar contexto da conversa pro pipeline de geração de imagem
                require_once __DIR__ . '/../../Services/TudinhaService.php';
                $tudinhaService = new \App\Services\TudinhaService();
                $historico = $tudinhaService->carregarHistorico($conversa_id, 10);

                $contextoConversaImg = '';
                if (!empty($historico)) {
                    $ultimasMensagens = array_slice($historico, -3); // Últimas 3 mensagens
                    foreach ($ultimasMensagens as $msg) {
                        if ($msg['is_ia'] == 1) {
                            $contextoConversaImg .= "Contexto: " . strip_tags($msg['mensagem']) . "\n";
                        }
                    }
                }

                // Pipeline de IA: interpreta o pedido + gera a imagem (2 agentes
                // encadeados via ExecutorPipeline — ver app/AI/).
                try {
                    require_once __DIR__ . '/../../AI/ContextoExecucao.php';
                    require_once __DIR__ . '/../../AI/ExecutorPipeline.php';
                    require_once __DIR__ . '/../../AI/Agentes/ConstrutorPromptImagemAgent.php';
                    require_once __DIR__ . '/../../AI/Agentes/GeradorImagemAgent.php';

                    $execContexto = (new \App\AI\ContextoExecucao())
                        ->set('pedido_aluno', $prompt)
                        ->set('contexto_conversa', trim($contextoConversaImg))
                        ->set('aluno_id', (int) $aluno['id'])
                        ->set('config', $this->config);

                    $execContexto = \App\AI\ExecutorPipeline::executar([
                        new \App\AI\Agentes\ConstrutorPromptImagemAgent(),
                        new \App\AI\Agentes\GeradorImagemAgent(),
                    ], $execContexto);

                    $resultado = ['image_url' => $execContexto->get('image_url')];
                    $provedorImagemUsado = $execContexto->get('provedor_imagem');

                    // Debitar crédito pelo uso da Tudinha (resposta com imagem)
                    require_once __DIR__ . '/../../Services/CreditosService.php';
                    $creditosServiceImg = new \App\Services\CreditosService();
                    try {
                        $creditosServiceImg->consumir('aluno', (int) $aluno['id'], 'tudinha_mensagem', $conversa_id);
                    } catch (Exception $e) {
                        if (stripos($e->getMessage(), 'TudiCoins') !== false || stripos($e->getMessage(), 'insuficientes') !== false || stripos($e->getMessage(), 'Créditos') !== false) {
                            $mid = $this->db->insert(
                                "INSERT INTO tudinha_mensagens (conversa_id, aluno_id, mensagem, tipo, is_ia, created_at) VALUES (:cid, :aid, :msg, 'texto', 1, NOW())",
                                ['cid' => $conversa_id, 'aid' => $aluno['id'], 'msg' => $e->getMessage()]
                            );
                            $this->json(['success' => true, 'mensagem_id' => $mid]);
                            return;
                        }
                        throw $e;
                    }
                    
                    // Salvar mensagem da IA com a imagem gerada
                    // Sem style inline de tamanho: o CSS de .ai-message-content img (chat.php)
                    // já limita o tamanho e deixa clicável pra expandir.
                    $resposta_ia = "<p>Aqui está a imagem que você pediu:</p><p><img src=\"{$resultado['image_url']}\" alt=\"Imagem gerada\" class=\"rounded-lg shadow-lg\"></p><p class=\"tudinha-imagem-provedor\">Imagem gerada por: {$provedorImagemUsado}</p>";
                    
                    $this->db->insert(
                        "INSERT INTO tudinha_mensagens (conversa_id, aluno_id, mensagem, tipo, is_ia, image_url, created_at) 
                         VALUES (:conversa_id, :aluno_id, :mensagem, :tipo, 1, :image_url, NOW())",
                        [
                            'conversa_id' => $conversa_id,
                            'aluno_id' => $aluno['id'],
                            'mensagem' => $resposta_ia,
                            'tipo' => 'imagem',
                            'image_url' => $resultado['image_url']
                        ]
                    );

                    $this->monitorarChatMensagem($aluno, $conversa_id, $resposta_ia, 'ia');
                    
                    // Registrar interação
                    $this->db->insert(
                        "INSERT INTO alunos_acoes_diarias (aluno_id, acao, created_at) 
                         VALUES (:aluno_id, 'chat_interacoes', NOW())",
                        [
                            'aluno_id' => $aluno['id']
                        ]
                    );
                    
                } catch (Exception $imagemError) {
                    // Se falhar, responder normalmente
                    error_log("Erro ao gerar imagem: " . $imagemError->getMessage());
                    
                    require_once __DIR__ . '/../../Services/CreditosService.php';
                    $creditosService = new \App\Services\CreditosService();
                    try {
                        $creditosService->consumir('aluno', (int) $aluno['id'], 'tudinha_mensagem', $conversa_id);
                    } catch (Exception $e) {
                        if (stripos($e->getMessage(), 'TudiCoins') !== false || stripos($e->getMessage(), 'insuficientes') !== false || stripos($e->getMessage(), 'Créditos') !== false) {
                            $mid = $this->db->insert(
                                "INSERT INTO tudinha_mensagens (conversa_id, aluno_id, mensagem, tipo, is_ia, created_at) VALUES (:cid, :aid, :msg, 'texto', 1, NOW())",
                                ['cid' => $conversa_id, 'aid' => $aluno['id'], 'msg' => $e->getMessage()]
                            );
                            $this->json(['success' => true, 'mensagem_id' => $mid]);
                            return;
                        }
                        throw $e;
                    }
                    require_once __DIR__ . '/../../Services/TudinhaService.php';
                    $tudinhaService = new \App\Services\TudinhaService();
                    try {
                        $resposta_ia = $tudinhaService->gerarResposta(
                            $conversa_id,
                            $mensagem,
                            $imageUrl,
                            $aluno['id'],
                            $imagemDataUriVisao ?? null
                        );

                        $this->db->insert(
                            "INSERT INTO tudinha_mensagens (conversa_id, aluno_id, mensagem, tipo, is_ia, created_at)
                             VALUES (:conversa_id, :aluno_id, :mensagem, :tipo, 1, NOW())",
                            [
                                'conversa_id' => $conversa_id,
                                'aluno_id' => $aluno['id'],
                                'mensagem' => $resposta_ia,
                                'tipo' => 'texto'
                            ]
                        );
                        $this->monitorarChatMensagem($aluno, $conversa_id, $resposta_ia, 'ia');
                        $this->db->insert(
                            "INSERT INTO alunos_acoes_diarias (aluno_id, acao, created_at) VALUES (:aluno_id, 'chat_interacoes', NOW())",
                            ['aluno_id' => $aluno['id']]
                        );
                    } catch (Exception $e) {
                        try {
                            $creditosService->estornarPorReferencia('tudinha_mensagem', $conversa_id);
                        } catch (Exception $e2) {
                            error_log("Estorno Tudinha (fallback imagem): " . $e2->getMessage());
                        }
                        $resposta_ia = "<p>Desculpe, estou com dificuldades técnicas no momento. Tente novamente em alguns instantes.</p>";
                        $this->db->insert(
                            "INSERT INTO tudinha_mensagens (conversa_id, aluno_id, mensagem, tipo, is_ia, created_at) VALUES (:cid, :aid, :msg, 'texto', 1, NOW())",
                            ['cid' => $conversa_id, 'aid' => $aluno['id'], 'msg' => $resposta_ia]
                        );
                        $this->monitorarChatMensagem($aluno, $conversa_id, $resposta_ia, 'ia');
                        $this->db->insert(
                            "INSERT INTO alunos_acoes_diarias (aluno_id, acao, created_at) VALUES (:aluno_id, 'chat_interacoes', NOW())",
                            ['aluno_id' => $aluno['id']]
                        );
                    }
                }
            } else {
                // Resposta normal de texto (OCR já pode ter consumido tempo; renovar limite para a chamada à IA)
                set_time_limit(120);
                require_once __DIR__ . '/../../Services/CreditosService.php';
                $creditosService = new \App\Services\CreditosService();
                try {
                    $creditosService->consumir('aluno', (int) $aluno['id'], $tudinhaModuloCredito, $conversa_id);
                } catch (Exception $e) {
                    if (stripos($e->getMessage(), 'TudiCoins') !== false || stripos($e->getMessage(), 'insuficientes') !== false || stripos($e->getMessage(), 'Créditos') !== false) {
                        $mid = $this->db->insert(
                            "INSERT INTO tudinha_mensagens (conversa_id, aluno_id, mensagem, tipo, is_ia, created_at) VALUES (:cid, :aid, :msg, 'texto', 1, NOW())",
                            ['cid' => $conversa_id, 'aid' => $aluno['id'], 'msg' => $e->getMessage()]
                        );
                        $this->json(['success' => true, 'mensagem_id' => $mid]);
                        return;
                    }
                    throw $e;
                }
                try {
                    require_once __DIR__ . '/../../Services/TudinhaService.php';
                    $tudinhaService = new \App\Services\TudinhaService();
                    // Gerar resposta da IA com histórico de contexto (+ visão real da
                    // imagem enviada nesta mensagem, se houver)
                    $resposta_ia = $tudinhaService->gerarResposta(
                        $conversa_id,
                        $mensagem,
                        $imageUrl,
                        $aluno['id'],
                        $imagemDataUriVisao ?? null
                    );

                    // Salvar resposta da IA (sem tentar gerar imagem automaticamente)
                    $this->db->insert(
                        "INSERT INTO tudinha_mensagens (conversa_id, aluno_id, mensagem, tipo, is_ia, created_at) 
                         VALUES (:conversa_id, :aluno_id, :mensagem, :tipo, 1, NOW())",
                        [
                            'conversa_id' => $conversa_id,
                            'aluno_id' => $aluno['id'],
                            'mensagem' => $resposta_ia,
                            'tipo' => 'texto'
                        ]
                    );

                    $this->monitorarChatMensagem($aluno, $conversa_id, $resposta_ia, 'ia');
                    
                    // Registrar interação completa (mensagem + resposta) como uso diário
                    $this->db->insert(
                        "INSERT INTO alunos_acoes_diarias (aluno_id, acao, created_at) 
                         VALUES (:aluno_id, 'chat_interacoes', NOW())",
                        [
                            'aluno_id' => $aluno['id']
                        ]
                    );
                } catch (Exception $tudinhaError) {
                    // Estornar créditos pois a IA falhou
                    try {
                        $creditosService->estornarPorReferencia($tudinhaModuloCredito, $conversa_id);
                    } catch (Exception $e2) {
                        error_log("Estorno Tudinha (erro IA): " . $e2->getMessage());
                    }
                    // Se a IA falhar, usar resposta de fallback
                    $errorMessage = $tudinhaError->getMessage();
                    $errorClass = get_class($tudinhaError);
                    
                    // Log detalhado do erro
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("=== ERRO AO GERAR RESPOSTA DA TUDINHA ===");
                    }
                    error_log("Tipo: " . $errorClass);
                    error_log("Mensagem: " . $errorMessage);
                    error_log("Arquivo: " . $tudinhaError->getFile());
                    error_log("Linha: " . $tudinhaError->getLine());
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                            error_log("Stack trace: " . $tudinhaError->getTraceAsString());
                        }
                    }
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("==========================================");
                    }
                    
                    // Verificar tipo de erro e retornar mensagem apropriada
                    if (strpos($errorMessage, 'OPENAI_API_KEY') !== false || 
                        strpos($errorMessage, 'API key') !== false ||
                        strpos($errorMessage, 'não configurada') !== false) {
                        $resposta_ia = "<p><strong>Erro de Configuração:</strong> A chave da API da OpenAI não está configurada. Por favor, entre em contato com o administrador do sistema.</p>";
                    } elseif (strpos($errorMessage, '401') !== false || strpos($errorMessage, 'Unauthorized') !== false) {
                        $resposta_ia = "<p><strong>Erro de Autenticação:</strong> A chave da API da OpenAI é inválida ou expirou. Por favor, entre em contato com o administrador.</p>";
                    } elseif (strpos($errorMessage, '429') !== false || strpos($errorMessage, 'rate limit') !== false) {
                        $resposta_ia = "<p>Desculpe, atingimos o limite de requisições. Por favor, tente novamente em alguns instantes.</p>";
                    } elseif (strpos($errorMessage, 'timeout') !== false || strpos($errorMessage, 'Connection') !== false) {
                        $resposta_ia = "<p>Desculpe, houve um problema de conexão. Por favor, verifique sua internet e tente novamente.</p>";
                    } else {
                        // Para outros erros, mostrar mensagem genérica mas logar o erro real
                        $resposta_ia = "<p>Desculpe, estou com dificuldades técnicas no momento. Tente novamente em alguns instantes.</p>";
                        // Em desenvolvimento, incluir detalhes do erro na mensagem
                        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                            $resposta_ia .= "<p><small style='color: red;'>Erro: " . htmlspecialchars($errorMessage) . "</small></p>";
                        }
                    }
                    
                    $this->db->insert(
                        "INSERT INTO tudinha_mensagens (conversa_id, aluno_id, mensagem, tipo, is_ia, created_at) 
                         VALUES (:conversa_id, :aluno_id, :mensagem, :tipo, 1, NOW())",
                        [
                            'conversa_id' => $conversa_id,
                            'aluno_id' => $aluno['id'],
                            'mensagem' => $resposta_ia,
                            'tipo' => 'texto'
                        ]
                    );

                    $this->monitorarChatMensagem($aluno, $conversa_id, $resposta_ia, 'ia');
                    
                    // Registrar interação completa mesmo com fallback
                    $this->db->insert(
                        "INSERT INTO alunos_acoes_diarias (aluno_id, acao, created_at) 
                         VALUES (:aluno_id, 'chat_interacoes', NOW())",
                        [
                            'aluno_id' => $aluno['id']
                        ]
                    );
                    
                    // Em desenvolvimento, incluir detalhes do erro na resposta JSON
                    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                        $this->json([
                            'success' => true, 
                            'mensagem_id' => $mensagem_id,
                            'debug' => [
                                'erro' => $errorMessage,
                                'tipo' => $errorClass,
                                'arquivo' => $tudinhaError->getFile(),
                                'linha' => $tudinhaError->getLine()
                            ]
                        ]);
                        return;
                    }
                }
            }

            $this->json(['success' => true, 'mensagem_id' => $mensagem_id]);

        } catch (Exception $e) {
            // Log do erro para debug
            require_once __DIR__ . '/../../Core/Logger.php';
            Logger::error('Erro em sendMessage: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ], 'chat');
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            // Capturar qualquer outro tipo de erro
            require_once __DIR__ . '/../../Core/Logger.php';
            Logger::error('Erro fatal em sendMessage: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ], 'chat');
            // Em desenvolvimento, devolver a mensagem real para facilitar debug
            $mensagemErro = 'Erro interno do servidor';
            $isDev = getenv('APP_DEBUG') === 'true' || getenv('ENVIRONMENT') === 'development'
                || (defined('DEBUG') && DEBUG) || (defined('ENVIRONMENT') && ENVIRONMENT === 'development');
            if ($isDev) {
                $mensagemErro = $e->getMessage();
            }
            $this->json(['success' => false, 'error' => $mensagemErro], 500);
        }
    }

    public function getMessages()
    {
        // Garantir resposta sempre em JSON (evitar HTML de erro na tela do chat)
        if (ob_get_level()) {
            ob_clean();
        }
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
        ini_set('display_errors', 0);

        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                $this->json(['success' => false, 'error' => 'Aluno não encontrado'], 400);
                return;
            }

            $conversa_id = $_GET['conversa_id'] ?? '';

            if (empty($conversa_id)) {
                $this->json(['success' => false, 'error' => 'ID da conversa é obrigatório'], 400);
                return;
            }

            // Verificar se a conversa pertence ao aluno e não está excluída
            $conversa = $this->db->fetch(
                "SELECT * FROM tudinha_conversas WHERE id = :conversa_id AND aluno_id = :aluno_id AND excluida = 0",
                ['conversa_id' => $conversa_id, 'aluno_id' => $aluno['id']]
            );

            if (!$conversa) {
                $this->json(['success' => false, 'error' => 'Conversa não encontrada'], 400);
                return;
            }

            // Buscar mensagens da conversa
            $mensagens = $this->db->fetchAll(
                "SELECT * FROM tudinha_mensagens 
                 WHERE conversa_id = :conversa_id 
                 ORDER BY created_at ASC",
                ['conversa_id' => $conversa_id]
            );

            // Formatar mensagens da IA usando ChatFormatter
            require_once __DIR__ . '/../../Utils/ChatFormatter.php';
            
            $basePath = (defined('FOLDER') && FOLDER !== '') ? rtrim(FOLDER, '/') : '';
            foreach ($mensagens as &$mensagem) {
                // Garantir que image_url sempre venha no JSON e seja caminho relativo (carrega no host atual)
                $imgUrl = isset($mensagem['image_url']) && (string)$mensagem['image_url'] !== '' ? (string) $mensagem['image_url'] : null;
                if ($imgUrl !== null && strpos($imgUrl, 'http') === 0) {
                    $pathParsed = parse_url($imgUrl, PHP_URL_PATH);
                    $imgUrl = ($pathParsed !== null && $pathParsed !== false && $pathParsed !== '') ? $pathParsed : $imgUrl;
                }
                // Evitar path malformado "/https://..." (legado)
                if ($imgUrl !== null && strpos($imgUrl, '/http') === 0) {
                    $pathParsed = parse_url(ltrim($imgUrl, '/'), PHP_URL_PATH);
                    $imgUrl = ($pathParsed !== null && $pathParsed !== false && $pathParsed !== '') ? $pathParsed : '/media/serve';
                }
                // No servidor (subpasta), garantir path com base quando necessário
                if ($imgUrl !== null && $basePath !== '' && strpos($imgUrl, $basePath) !== 0) {
                    $imgUrl = $basePath . (strpos($imgUrl, '/') === 0 ? $imgUrl : '/' . $imgUrl);
                }
                // URL já é /media/serve?type=chat&key=... → manter; legado storage/chat → usar ver-imagem
                if ($imgUrl !== null && $imgUrl !== '') {
                    if (strpos($imgUrl, '/media/serve') !== false || strpos($imgUrl, 'type=chat&key=') !== false) {
                        $mensagem['image_url'] = $imgUrl;
                    } else {
                        $filename = basename(parse_url($imgUrl, PHP_URL_PATH) ?: $imgUrl);
                        if ($filename !== '' && preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
                            $mensagem['image_url'] = $basePath . '/chat/ver-imagem?f=' . rawurlencode($filename);
                        } else {
                            $mensagem['image_url'] = $imgUrl;
                        }
                    }
                } else {
                    $mensagem['image_url'] = $imgUrl;
                }
                // Mensagem de usuário com imagem: não exibir texto extraído (OCR) no chat, só "Imagem enviada"
                if ($mensagem['is_ia'] == 0 && isset($mensagem['tipo']) && $mensagem['tipo'] === 'imagem' && $imgUrl !== null && $imgUrl !== '') {
                    $mensagem['mensagem'] = 'Imagem enviada';
                    $mensagem['mensagem_formatada'] = 'Imagem enviada';
                } elseif ($mensagem['is_ia'] == 1 && !empty($mensagem['mensagem'])) {
                    // Extrai o bloco de sugestões de aprofundamento (se houver) ANTES de
                    // sanitizar — o ChatFormatter não inclui <div>/data-* no allowlist e
                    // removeria o bloco silenciosamente (ver TudinhaService::extrairSugestoes).
                    require_once __DIR__ . '/../../Services/TudinhaService.php';
                    $extraido = \App\Services\TudinhaService::extrairSugestoes($mensagem['mensagem']);
                    $mensagem['sugestoes'] = $extraido['sugestoes'];
                    // Para mensagens da IA, formatar e garantir que HTML não seja escapado
                    $mensagem['mensagem_formatada'] = ChatFormatter::formatMessageWithClasses($extraido['html'], true);
                } else {
                    // Para mensagens do usuário, escapar para segurança
                    $mensagem['mensagem_formatada'] = htmlspecialchars($mensagem['mensagem'] ?? '', ENT_QUOTES, 'UTF-8');
                }
            }

            if (ob_get_level()) {
                ob_clean();
            }
            $this->json(['success' => true, 'mensagens' => $mensagens]);

        } catch (Throwable $e) {
            if (ob_get_level()) {
                ob_clean();
            }
            require_once __DIR__ . '/../../Core/Logger.php';
            Logger::error('getMessages erro: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString()], 'chat');
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Serve imagem do chat pela aplicação (funciona em qualquer servidor, não depende de pasta /storage no web server).
     * GET /chat/ver-imagem?f=nome_arquivo.jpg
     */
    public function verImagemChat()
    {
        if (ob_get_level()) {
            ob_end_clean();
        }
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            http_response_code(403);
            exit;
        }
        $f = $_GET['f'] ?? '';
        $f = trim($f);
        if ($f === '' || strpos($f, '..') !== false || preg_match('/[^a-zA-Z0-9_\-\.\/]/', $f)) {
            http_response_code(400);
            exit;
        }
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        if ($media->isS3()) {
            $url = $media->getViewUrl('chat', $f, $f);
            if ($url !== null && $url !== '') {
                header('Location: ' . $url);
                exit;
            }
        }
        $filepath = $media->getLocalPath('chat', $f);
        if ($filepath === null || !is_file($filepath)) {
            http_response_code(404);
            exit;
        }
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
        }
        header('Cache-Control: public, max-age=86400');
        readfile($filepath);
        exit;
    }

    public function getConversationInfo()
    {
        // Limpar qualquer output anterior
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Garantir que não há warnings/notices sendo exibidos
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
        ini_set('display_errors', 0);
        
        try {
            $conversa_id = $_GET['conversa_id'] ?? null;
            
            if (!$conversa_id) {
                $this->json(['success' => false, 'error' => 'ID da conversa não fornecido'], 400);
                return;
            }
            
            $user = $this->authManager->getUser();
            if (!$user || $user['tipo'] !== 'aluno') {
                $this->json(['success' => false, 'error' => 'Usuário não autenticado'], 401);
                return;
            }
            
            // Verificar se a conversa pertence ao aluno
            $conversa = $this->db->fetch(
                "SELECT cc.*, 
                    LEAST(
                        (SELECT COUNT(*) FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id AND mc.is_ia = 0),
                        (SELECT COUNT(*) FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id AND mc.is_ia = 1)
                    ) as interacoes
                 FROM tudinha_conversas cc
                 WHERE cc.id = :conversa_id AND cc.aluno_id = :aluno_id AND cc.excluida = 0",
                ['conversa_id' => $conversa_id, 'aluno_id' => $user['id']]
            );
            
            if (!$conversa) {
                $this->json(['success' => false, 'error' => 'Conversa não encontrada'], 404);
                return;
            }
            
            $this->json([
                'success' => true,
                'interacoes' => $conversa['interacoes'] ?? 0
            ]);
            
        } catch (Exception $e) {
            // Log do erro para debug
            require_once __DIR__ . '/../../Core/Logger.php';
            Logger::error('Erro em getConversationInfo: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ], 'chat');
            
            $this->json(['success' => false, 'error' => 'Erro ao buscar informações da conversa'], 500);
        }
    }

    public function deleteConversation()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido', 'csrf_token' => $this->refreshCsrfToken()], 400);
        }

        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $conversa_id = $_POST['conversa_id'] ?? '';

            if (empty($conversa_id)) {
                throw new Exception('ID da conversa é obrigatório');
            }

            // Verificar se a conversa pertence ao aluno
            $conversa = $this->db->fetch(
                "SELECT * FROM tudinha_conversas WHERE id = :conversa_id AND aluno_id = :aluno_id",
                ['conversa_id' => $conversa_id, 'aluno_id' => $aluno['id']]
            );

            if (!$conversa) {
                throw new Exception('Conversa não encontrada');
            }

            // Soft delete - apenas marca como excluída
            $this->db->update(
                "UPDATE tudinha_conversas SET excluida = 1, updated_at = NOW() WHERE id = :conversa_id",
                ['conversa_id' => $conversa_id]
            );

            $this->json(['success' => true, 'message' => 'Conversa excluída com sucesso']);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Gera imagem usando Nano Banana
     */
    public function gerarImagem()
    {
        // Limpar qualquer output anterior
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Garantir que não há warnings/notices sendo exibidos
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
        ini_set('display_errors', 0);
        
        // TEMPORÁRIO: Validação de token CSRF desabilitada para testes
        // TODO: Reativar após testes
        /*
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 400);
            return;
        }
        */

        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                throw new Exception('Acesso negado');
            }

            $prompt = $_POST['prompt'] ?? '';
            $conversa_id = $_POST['conversa_id'] ?? null;
            
            if (empty($prompt)) {
                throw new Exception('Prompt é obrigatório');
            }

            require_once __DIR__ . '/../../Services/TudinhaService.php';
            $tudinhaService = new \App\Services\TudinhaService();

            // Se tiver conversa_id, enriquecer o prompt com contexto
            if ($conversa_id) {
                $historico = $tudinhaService->carregarHistorico($conversa_id, 5);
                
                if (!empty($historico)) {
                    $ultimasMensagens = array_slice($historico, -2);
                    foreach ($ultimasMensagens as $msg) {
                        if ($msg['is_ia'] == 1) {
                            $prompt .= ". " . strip_tags($msg['mensagem']);
                        }
                    }
                }
            }
            
            // Pipeline de IA: interpreta o pedido (já enriquecido com contexto
            // acima) e gera a imagem (2 agentes encadeados — ver app/AI/).
            require_once __DIR__ . '/../../AI/ContextoExecucao.php';
            require_once __DIR__ . '/../../AI/ExecutorPipeline.php';
            require_once __DIR__ . '/../../AI/Agentes/ConstrutorPromptImagemAgent.php';
            require_once __DIR__ . '/../../AI/Agentes/GeradorImagemAgent.php';

            $execContexto = (new \App\AI\ContextoExecucao())
                ->set('pedido_aluno', $prompt)
                ->set('aluno_id', (int) $user['id'])
                ->set('config', $this->config);

            $execContexto = \App\AI\ExecutorPipeline::executar([
                new \App\AI\Agentes\ConstrutorPromptImagemAgent(),
                new \App\AI\Agentes\GeradorImagemAgent(),
            ], $execContexto);

            $resultado = ['image_url' => $execContexto->get('image_url')];
            $provedorImagemUsado = $execContexto->get('provedor_imagem');
            
            // Se tiver conversa_id, salvar a imagem na última mensagem da IA
            if ($conversa_id) {
                $aluno = $this->db->fetch(
                    "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                    ['user_id' => $user['id']]
                );
                
                if ($aluno) {
                    // Buscar última mensagem da IA da conversa
                    $ultimaMensagem = $this->db->fetch(
                        "SELECT * FROM tudinha_mensagens 
                         WHERE conversa_id = :conversa_id AND is_ia = 1 
                         ORDER BY created_at DESC LIMIT 1",
                        ['conversa_id' => $conversa_id]
                    );
                    
                    if ($ultimaMensagem) {
                        // Atualizar mensagem com a imagem
                        $mensagemComImagem = '<p><img src="' . $resultado['image_url'] . '" alt="Imagem gerada" class="rounded-lg shadow-lg"></p><p class="tudinha-imagem-provedor">Imagem gerada por: ' . $provedorImagemUsado . '</p>' . $ultimaMensagem['mensagem'];
                        
                        $this->db->update(
                            "UPDATE tudinha_mensagens 
                             SET mensagem = :mensagem, tipo = 'imagem', image_url = :image_url 
                             WHERE id = :mensagem_id",
                            [
                                'mensagem' => $mensagemComImagem,
                                'image_url' => $resultado['image_url'],
                                'mensagem_id' => $ultimaMensagem['id']
                            ]
                        );
                    }
                }
            }

            $this->json([
                'success' => true,
                'image_url' => $resultado['image_url'],
                'image_id' => $resultado['image_id'] ?? null,
                'revised_prompt' => $resultado['revised_prompt'] ?? $prompt
            ]);

        } catch (Exception $e) {
            // Log do erro para debug
            require_once __DIR__ . '/../../Core/Logger.php';
            if (class_exists('Logger')) {
                Logger::error('Erro em gerarImagem: ' . $e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString()
                ], 'chat');
            } else {
                error_log('Erro em gerarImagem: ' . $e->getMessage());
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log('Stack trace: ' . $e->getTraceAsString());
                    }
                }
            }
            
            // Retornar mensagem de erro mais amigável
            $errorMessage = $e->getMessage();
            
            // Log do erro real para debug
            error_log("Erro real em gerarImagem: " . $errorMessage);
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("Stack trace: " . $e->getTraceAsString());
                }
            }
            
            // Mensagens específicas para diferentes tipos de erro
            // REMOVIDO: Substituição genérica de "Token" para ver o erro real
            if (strpos($errorMessage, 'Acesso negado') !== false) {
                $errorMessage = 'Acesso negado. Você precisa estar logado como aluno.';
            } elseif (strpos($errorMessage, 'obrigatório') !== false) {
                $errorMessage = 'Prompt é obrigatório para gerar imagem.';
            } elseif (strpos($errorMessage, 'indisponível') !== false || strpos($errorMessage, 'não pôde ser encontrado') !== false) {
                // Manter mensagem de serviço indisponível
            } elseif (strpos($errorMessage, 'não configurada') !== false || strpos($errorMessage, 'REPLICATE_API_KEY') !== false) {
                $errorMessage = 'API do Replicate não está configurada. Entre em contato com o administrador.';
            } elseif (strpos($errorMessage, 'conexão') !== false || strpos($errorMessage, 'conectar') !== false) {
                // Manter mensagem de conexão
            }
            
            $this->json(['success' => false, 'error' => $errorMessage], 400);
        }
    }

    /**
     * Chamado pelo frontend (AIJobPoller) quando o job assíncrono de
     * flashcards pedido via chat termina — persiste a mensagem final da
     * Tudinha na conversa, com o link pro baralho pronto. Nunca confia
     * cegamente no deck_id vindo do client: sempre revalida ownership.
     */
    public function flashcardConcluido()
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            echo json_encode(['success' => false, 'error' => 'Acesso negado']);
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token inválido']);
            return;
        }

        $conversaId = $_POST['conversa_id'] ?? '';
        $deckId = (int) ($_POST['deck_id'] ?? 0);
        $topico = trim((string) ($_POST['topico'] ?? ''));

        if (empty($conversaId) || $deckId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
            return;
        }

        $conversa = $this->db->fetch(
            "SELECT * FROM tudinha_conversas WHERE id = :conversa_id AND aluno_id = :aluno_id",
            ['conversa_id' => $conversaId, 'aluno_id' => $user['id']]
        );
        if (!$conversa) {
            echo json_encode(['success' => false, 'error' => 'Conversa não encontrada']);
            return;
        }

        require_once __DIR__ . '/../../Models/Study/FlashcardDeck.php';
        $deckModel = new \FlashcardDeck();
        $deck = $deckModel->getByIdAndAluno($deckId, (int) $user['id']);
        if (!$deck) {
            echo json_encode(['success' => false, 'error' => 'Baralho não encontrado']);
            return;
        }

        $topicoEscapado = htmlspecialchars($topico !== '' ? $topico : (string) $deck['topic'], ENT_QUOTES, 'UTF-8');
        $resposta_ia = "<p>Prontinho! Seus flashcards sobre <strong>{$topicoEscapado}</strong> estão prontos.</p>"
            . "<p><a href=\"" . URL . "/flashcards/deck/{$deckId}\" class=\"tudinha-flashcard-abrir\" target=\"_blank\">Abrir Flashcards</a></p>";

        $mid = $this->db->insert(
            "INSERT INTO tudinha_mensagens (conversa_id, aluno_id, mensagem, tipo, is_ia, created_at) VALUES (:cid, :aid, :msg, 'texto', 1, NOW())",
            ['cid' => $conversaId, 'aid' => $user['id'], 'msg' => $resposta_ia]
        );

        echo json_encode(['success' => true, 'mensagem_id' => $mid]);
    }

    /**
     * Converte texto em voz usando OpenAI TTS
     */
    public function textoParaVoz()
    {
        // Limpar qualquer output anterior
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Garantir que não há warnings/notices sendo exibidos
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
        ini_set('display_errors', 0);
        
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 400);
            return;
        }

        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                throw new Exception('Acesso negado');
            }

            $texto = $_POST['texto'] ?? '';
            if (empty($texto)) {
                throw new Exception('Texto é obrigatório');
            }

            require_once __DIR__ . '/../../Services/OpenAIService.php';
            $openaiService = new \App\Services\OpenAIService();
            $voice = $_POST['voice'] ?? 'nova'; // OpenAI: alloy, nova, shimmer, etc.
            $options = ['voice' => $voice];

            $resultado = $openaiService->textoParaVoz($texto, null, $options);

            $this->json([
                'success' => true,
                'audio_url' => $resultado['url'],
                'filename' => $resultado['filename']
            ]);

        } catch (Exception $e) {
            // Log do erro para debug
            require_once __DIR__ . '/../../Core/Logger.php';
            if (class_exists('Logger')) {
                Logger::error('Erro em textoParaVoz: ' . $e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString()
                ], 'chat');
            } else {
                error_log('Erro em textoParaVoz: ' . $e->getMessage());
            }
            
            // Retornar mensagem de erro mais amigável
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'Token') !== false) {
                $errorMessage = 'Token de segurança inválido. Por favor, recarregue a página.';
            } elseif (strpos($errorMessage, 'Acesso negado') !== false) {
                $errorMessage = 'Acesso negado. Você precisa estar logado como aluno.';
            } elseif (strpos($errorMessage, 'obrigatório') !== false) {
                $errorMessage = 'Texto é obrigatório para gerar áudio.';
            } elseif (strpos($errorMessage, 'quota') !== false || strpos($errorMessage, 'credits') !== false) {
                $errorMessage = 'Limite de uso do recurso de voz atingido no momento. Tente novamente mais tarde.';
            }
            
            $this->json(['success' => false, 'error' => $errorMessage], 400);
        }
    }

    /**
     * Transcreve áudio em texto
     */
    public function vozParaTexto()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido', 'csrf_token' => $this->refreshCsrfToken()], 400);
        }

        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                throw new Exception('Acesso negado');
            }

            if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Arquivo de áudio inválido');
            }

            $audio = $_FILES['audio'];
            
            // Validar tipo - formatos suportados pela OpenAI Whisper
            $allowedTypes = [
                'audio/mpeg', 'audio/mp3', 'audio/mpga',
                'audio/wav', 
                'audio/webm', 
                'audio/ogg', 'audio/oga',
                'audio/flac',
                'audio/m4a', 'audio/mp4'
            ];
            
            // Verificar também pela extensão do arquivo
            $extension = strtolower(pathinfo($audio['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['mp3', 'wav', 'webm', 'ogg', 'oga', 'flac', 'm4a', 'mp4', 'mpga'];
            
            if (!in_array($audio['type'], $allowedTypes) && !in_array($extension, $allowedExtensions)) {
                throw new Exception('Tipo de áudio não suportado. Formatos aceitos: MP3, WAV, WebM, OGG, FLAC, M4A');
            }

            // Salvar temporariamente: preferir storage do app; fallback para temp do sistema (evita erro de permissão no servidor)
            $tempDir = __DIR__ . '/../../storage/chat/audio/';
            if (!is_dir($tempDir)) {
                if (!@mkdir($tempDir, 0755, true)) {
                    $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/educatudo_chat_audio';
                    if (!is_dir($tempDir)) {
                        @mkdir($tempDir, 0755, true);
                    }
                }
            }
            if (!is_dir($tempDir) || !is_writable($tempDir)) {
                $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/educatudo_chat_audio';
                if (!is_dir($tempDir)) {
                    @mkdir($tempDir, 0755, true);
                }
            }
            if (!is_dir($tempDir) || !is_writable($tempDir)) {
                error_log('vozParaTexto: nenhum diretório gravável (app storage e sys_temp)');
                throw new Exception('Erro ao preparar gravação de áudio. Tente novamente.');
            }
            $tempDir = rtrim($tempDir, '/') . '/';

            // Detectar extensão correta baseada no tipo MIME
            $extension = pathinfo($audio['name'], PATHINFO_EXTENSION);
            $mimeToExt = [
                'audio/webm' => 'webm',
                'audio/mp3' => 'mp3',
                'audio/mpeg' => 'mp3',
                'audio/wav' => 'wav',
                'audio/ogg' => 'ogg',
                'audio/oga' => 'oga',
                'audio/flac' => 'flac',
                'audio/m4a' => 'm4a',
                'audio/mp4' => 'm4a',
                'audio/mpga' => 'mpga'
            ];
            
            if (isset($mimeToExt[$audio['type']])) {
                $extension = $mimeToExt[$audio['type']];
            }
            
            $filename = 'audio_' . time() . '_' . uniqid() . '.' . $extension;
            $filepath = $tempDir . $filename;

            $tmpName = $audio['tmp_name'];
            if (!is_uploaded_file($tmpName)) {
                $err = $audio['error'] ?? 'unknown';
                error_log('vozParaTexto: upload inválido, error=' . $err . ' tmp_name=' . ($tmpName ?? 'null'));
                throw new Exception('Arquivo de áudio inválido ou muito grande. Tente gravar de novo.');
            }
            if (!move_uploaded_file($tmpName, $filepath)) {
                error_log('vozParaTexto: move_uploaded_file falhou de ' . $tmpName . ' para ' . $filepath);
                throw new Exception('Erro ao salvar áudio. Tente novamente.');
            }

            // Se o app usa S3 para mídia, enviar cópia do áudio para o S3 (tipo chat)
            require_once __DIR__ . '/../../Services/MediaStorageService.php';
            $mediaStorage = new MediaStorageService($this->config);
            if ($mediaStorage->isS3()) {
                $s3Key = 'audio/' . $filename;
                $contentType = $audio['type'] ?: 'audio/webm';
                if (!$mediaStorage->put('chat', $s3Key, $filepath, $contentType)) {
                    error_log('vozParaTexto: falha ao enviar áudio para S3, key=' . $s3Key);
                }
            }

            require_once __DIR__ . '/../../Services/ElevenLabsService.php';
            $elevenLabsService = new \App\Services\ElevenLabsService();
            
            $texto = $elevenLabsService->vozParaTexto($filepath);

            // Remover arquivo temporário
            @unlink($filepath);

            $this->json([
                'success' => true,
                'texto' => $texto
            ]);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Upload e processamento de arquivos (PDF, imagens, etc)
     */
    public function uploadArquivo()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                throw new Exception('Acesso negado');
            }

            if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Arquivo inválido');
            }

            require_once __DIR__ . '/../../Services/FileProcessorService.php';
            $fileProcessor = new \App\Services\FileProcessorService();
            
            $resultado = $fileProcessor->processarArquivo($_FILES['arquivo']);

            $this->json([
                'success' => true,
                'texto' => $resultado['texto'],
                'tipo' => $resultado['tipo'],
                'url' => $resultado['url'],
                'filename' => $resultado['filename'],
                'original_name' => $resultado['original_name']
            ]);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Minta uma sessão efêmera da Realtime API pro modo de voz em tempo real.
     * O navegador usa o client_secret retornado pra abrir WebRTC direto com a
     * OpenAI — a chave mestra nunca sai daqui.
     */
    public function iniciarSessaoVoz()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido', 'csrf_token' => $this->refreshCsrfToken()], 400);
            return;
        }

        try {
            $user = $this->authManager->getUser();

            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $conversa_id = $_POST['conversa_id'] ?? '';
            if (empty($conversa_id)) {
                throw new Exception('ID da conversa é obrigatório');
            }

            $conversa = $this->db->fetch(
                "SELECT * FROM tudinha_conversas WHERE id = :conversa_id AND aluno_id = :aluno_id",
                ['conversa_id' => $conversa_id, 'aluno_id' => $aluno['id']]
            );
            if (!$conversa) {
                throw new Exception('Conversa não encontrada');
            }

            $verificacao = $this->podeExecutarAcao($aluno['id'], 'chat_interacoes');
            if (!$verificacao['pode']) {
                $this->json(['error' => $verificacao['mensagem']], 403);
                return;
            }

            require_once __DIR__ . '/../../Services/CreditosService.php';
            $creditosServiceVoz = new \App\Services\CreditosService();
            try {
                $creditosServiceVoz->consumir('aluno', (int) $aluno['id'], 'tudinha_voz_realtime', $conversa_id);
            } catch (Exception $e) {
                if (stripos($e->getMessage(), 'TudiCoins') !== false || stripos($e->getMessage(), 'insuficientes') !== false || stripos($e->getMessage(), 'Créditos') !== false) {
                    $this->json(['error' => $e->getMessage()], 402);
                    return;
                }
                throw $e;
            }

            try {
                require_once __DIR__ . '/../../AI/TudinhaChatService.php';
                $sessao = \App\AI\TudinhaChatService::iniciarSessaoVoz((int) $aluno['id']);
            } catch (Exception $sessaoError) {
                try {
                    $creditosServiceVoz->estornarPorReferencia('tudinha_voz_realtime', $conversa_id);
                } catch (Exception $e2) {
                    error_log("Estorno voz realtime: " . $e2->getMessage());
                }
                throw $sessaoError;
            }

            $this->json([
                'success' => true,
                'client_secret' => $sessao['client_secret'],
                'expires_at' => $sessao['expires_at'],
                'model' => $sessao['model'],
            ]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Salva um resumo textual de uma conversa por voz encerrada, como
     * mensagens normais em tudinha_mensagens — assim o histórico de texto
     * (carregarHistoricoPrivado) já enxerga o que foi falado, sem precisar
     * de nenhuma mudança no mecanismo de histórico existente.
     */
    public function salvarTranscricaoVoz()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido', 'csrf_token' => $this->refreshCsrfToken()], 400);
            return;
        }

        try {
            $user = $this->authManager->getUser();

            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );
            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $conversa_id = $_POST['conversa_id'] ?? '';
            $turnosJson = $_POST['turnos'] ?? '';
            if (empty($conversa_id) || empty($turnosJson)) {
                throw new Exception('Dados inválidos');
            }
            // Cap de tamanho ANTES de decodificar/iterar — este endpoint pode ser chamado
            // direto (fora do fluxo normal do navegador), então não pode confiar só no
            // mb_substr() aplicado depois de decodificado.
            if (strlen($turnosJson) > 50000) {
                throw new Exception('Transcrição muito grande');
            }

            $conversa = $this->db->fetch(
                "SELECT * FROM tudinha_conversas WHERE id = :conversa_id AND aluno_id = :aluno_id",
                ['conversa_id' => $conversa_id, 'aluno_id' => $aluno['id']]
            );
            if (!$conversa) {
                throw new Exception('Conversa não encontrada');
            }

            $turnos = json_decode($turnosJson, true);
            if (!is_array($turnos) || empty($turnos)) {
                $this->json(['success' => true, 'salvo' => false]);
                return;
            }
            $turnos = array_slice($turnos, 0, 200);

            $linhasAluno = [];
            $linhasIa = [];
            foreach ($turnos as $turno) {
                if (!is_array($turno)) {
                    continue;
                }
                $role = $turno['role'] ?? '';
                $texto = trim((string) ($turno['texto'] ?? ''));
                if ($texto === '') {
                    continue;
                }
                $texto = mb_substr($texto, 0, 2000);
                if ($role === 'aluno') {
                    $linhasAluno[] = $texto;
                } elseif ($role === 'ia') {
                    $linhasIa[] = $texto;
                }
            }

            if (empty($linhasAluno) && empty($linhasIa)) {
                $this->json(['success' => true, 'salvo' => false]);
                return;
            }

            $mensagemAluno = "[Conversa por voz]\n" . implode("\n", $linhasAluno);
            $this->db->insert(
                "INSERT INTO tudinha_mensagens (conversa_id, aluno_id, mensagem, tipo, created_at) VALUES (:conversa_id, :aluno_id, :mensagem, 'texto', NOW())",
                ['conversa_id' => $conversa_id, 'aluno_id' => $aluno['id'], 'mensagem' => $mensagemAluno]
            );

            $mensagemIa = "[Conversa por voz]\n" . implode("\n", $linhasIa);
            $this->db->insert(
                "INSERT INTO tudinha_mensagens (conversa_id, aluno_id, mensagem, tipo, is_ia, created_at) VALUES (:conversa_id, :aluno_id, :mensagem, 'texto', 1, NOW())",
                ['conversa_id' => $conversa_id, 'aluno_id' => $aluno['id'], 'mensagem' => $mensagemIa]
            );

            $this->json(['success' => true, 'salvo' => true]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Envia mensagem com streaming (Server-Sent Events)
     * Aceita GET ou POST
     */
    public function sendMessageStream()
    {
        // Aceitar token de GET ou POST
        $token = $_GET['_token'] ?? $_POST['_token'] ?? '';
        if (!$this->verifyCsrfToken($token)) {
            http_response_code(400);
            echo "data: " . json_encode(['error' => 'Token inválido']) . "\n\n";
            flush();
            exit;
        }

        try {
            // Configurar headers para SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Desabilitar buffering do Nginx
            ob_implicit_flush(true);
            ob_end_flush();

            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            // Verificar limite diário
            $verificacao = $this->podeExecutarAcao($aluno['id'], 'chat_interacoes');
            if (!$verificacao['pode']) {
                echo "data: " . json_encode(['error' => $verificacao['mensagem']]) . "\n\n";
                flush();
                exit;
            }

            $conversa_id = $_GET['conversa_id'] ?? $_POST['conversa_id'] ?? '';
            $mensagem = $_GET['mensagem'] ?? $_POST['mensagem'] ?? '';
            $imageUrl = $_GET['image_url'] ?? $_POST['image_url'] ?? null;

            if (empty($conversa_id) || (empty($mensagem) && empty($imageUrl))) {
                throw new Exception('Dados inválidos');
            }

            // Pedido de geração de imagem não é tratado aqui (o streaming manda texto
            // token a token; gerar imagem é síncrono e tem fluxo de crédito/fallback
            // próprio em sendMessage()) — avisa o frontend pra cair no POST clássico
            // /chat/mensagem em vez de tentar responder em texto.
            $mensagemLowerStream = mb_strtolower($mensagem ?? '', 'UTF-8');
            $temVerboImgStream = preg_match('/\b(criar?|crie|gerar?|gere|desenh\w*|fa[çc]a|faz|pode(ria)?|gostaria|quero|preciso|monta|monte|elabora|elabore)\b/iu', $mensagemLowerStream);
            $temSubstantivoImgStream = preg_match('/\b(imagem|imagens|fotos?|desenhos?|ilustra[cç][aã]o|ilustra[cç][oõ]es|figuras?|gr[aá]ficos?|infogr[aá]fico|diagramas?)\b/iu', $mensagemLowerStream);
            $pedidoDireto = $temVerboImgStream && $temSubstantivoImgStream;

            // Confirmação curta ("sim"/"ok"/"quero") só conta como pedido de imagem se
            // a IA realmente ofereceu gerar uma no turno anterior — senão qualquer "ok"
            // do dia a dia perderia o streaming à toa (mesma checagem de histórico do
            // fluxo síncrono em sendMessage()).
            $ehConfirmacaoImagem = false;
            if (!$pedidoDireto && preg_match('/^\s*(sim|pode|pode gerar|gera|gere|quero|manda ver|beleza|ok|claro)[\s,.!]*$/iu', trim($mensagem ?? ''))) {
                require_once __DIR__ . '/../../Services/TudinhaService.php';
                $tudinhaServiceCheckOfertaStream = new \App\Services\TudinhaService();
                $historicoCheckOfertaStream = $tudinhaServiceCheckOfertaStream->carregarHistorico($conversa_id, 3);
                foreach (array_reverse($historicoCheckOfertaStream) as $msgHistStream) {
                    if ($msgHistStream['is_ia'] == 1) {
                        if (stripos($msgHistStream['mensagem'], 'gere uma imagem') !== false || stripos($msgHistStream['mensagem'], 'gerar uma imagem') !== false) {
                            $ehConfirmacaoImagem = true;
                        }
                        break;
                    }
                }
            }

            if ($pedidoDireto || $ehConfirmacaoImagem) {
                echo "data: " . json_encode(['pediu_imagem' => true]) . "\n\n";
                flush();
                exit;
            }

            // Mesma lógica pra flashcard: detecção leve aqui só pra avisar o frontend
            // de que precisa cair no POST clássico /chat/mensagem, que tem o pipeline
            // completo (interpretar pedido + enfileirar geração assíncrona).
            $temVerboFlashStream = preg_match('/\b(criar?|crie|gerar?|gere|faz|fa[çc]a|pode(ria)?|gostaria|quero|preciso|monta|monte)\b/iu', $mensagemLowerStream);
            $temSubstantivoFlashStream = preg_match('/\b(flash\s?cards?|cart[aã]o(?:zinho)?s?\s+de\s+(estudo|memoriza[cç][aã]o))\b/iu', $mensagemLowerStream);
            if ($temVerboFlashStream && $temSubstantivoFlashStream) {
                echo "data: " . json_encode(['pediu_flashcard' => true]) . "\n\n";
                flush();
                exit;
            }

            $tudinhaModuloCreditoStream = !empty($imageUrl) ? 'tudinha_chat_imagem' : 'tudinha_mensagem';

            // Verificar se a conversa pertence ao aluno
            $conversa = $this->db->fetch(
                "SELECT * FROM tudinha_conversas WHERE id = :conversa_id AND aluno_id = :aluno_id",
                ['conversa_id' => $conversa_id, 'aluno_id' => $aluno['id']]
            );

            if (!$conversa) {
                throw new Exception('Conversa não encontrada');
            }

            // Consumir crédito antes de gerar resposta (streaming)
            require_once __DIR__ . '/../../Services/CreditosService.php';
            $creditosServiceStream = new \App\Services\CreditosService();
            try {
                $creditosServiceStream->consumir('aluno', (int) $aluno['id'], $tudinhaModuloCreditoStream, $conversa_id);
            } catch (Exception $e) {
                if (stripos($e->getMessage(), 'TudiCoins') !== false || stripos($e->getMessage(), 'insuficientes') !== false || stripos($e->getMessage(), 'Créditos') !== false) {
                    echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
                    flush();
                    exit;
                }
                throw $e;
            }

            // Salvar mensagem do aluno
            $mensagem_id = $this->db->insert(
                "INSERT INTO tudinha_mensagens (conversa_id, aluno_id, mensagem, tipo, image_url, created_at) 
                 VALUES (:conversa_id, :aluno_id, :mensagem, :tipo, :image_url, NOW())",
                [
                    'conversa_id' => $conversa_id,
                    'aluno_id' => $aluno['id'],
                    'mensagem' => $mensagem,
                    'tipo' => 'texto',
                    'image_url' => $imageUrl
                ]
            );

            // Pipeline de IA (app/AI/): monta contexto, gera resposta com streaming e pós-processa.
            require_once __DIR__ . '/../../AI/TudinhaChatService.php';

            $respostaCompleta = \App\AI\TudinhaChatService::gerarRespostaStream(
                (int) $conversa_id,
                (string) $mensagem,
                function ($chunk) {
                    echo "data: " . json_encode(['chunk' => $chunk, 'done' => false]) . "\n\n";
                    flush();
                    if (connection_aborted()) {
                        exit;
                    }
                },
                $imageUrl,
                (int) $aluno['id']
            );

            // Salvar resposta completa (memórias já extraídas e salvas pelo pipeline)
            $this->db->insert(
                "INSERT INTO tudinha_mensagens (conversa_id, aluno_id, mensagem, tipo, is_ia, created_at)
                 VALUES (:conversa_id, :aluno_id, :mensagem, :tipo, 1, NOW())",
                [
                    'conversa_id' => $conversa_id,
                    'aluno_id' => $aluno['id'],
                    'mensagem' => $respostaCompleta,
                    'tipo' => 'texto'
                ]
            );

            $this->monitorarChatMensagem($aluno, $conversa_id, $respostaCompleta, 'ia');

            // Registrar interação
            $this->db->insert(
                "INSERT INTO alunos_acoes_diarias (aluno_id, acao, created_at) 
                 VALUES (:aluno_id, 'chat_interacoes', NOW())",
                ['aluno_id' => $aluno['id']]
            );

            // Enviar sinal de conclusão
            echo "data: " . json_encode(['done' => true, 'mensagem_id' => $mensagem_id]) . "\n\n";
            flush();

        } catch (Exception $e) {
            try {
                if (isset($creditosServiceStream) && isset($conversa_id) && isset($tudinhaModuloCreditoStream)) {
                    $creditosServiceStream->estornarPorReferencia($tudinhaModuloCreditoStream, $conversa_id);
                }
            } catch (Exception $e2) {
                error_log("Estorno Tudinha (stream): " . $e2->getMessage());
            }
            echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
            flush();
        }
    }

    public function exercises()
    {
        $user = $this->authManager->getUser();
        
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome 
             FROM alunos a 
             LEFT JOIN turmas t ON a.turma_id = t.id 
             WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );

        if (!$aluno) {
            $this->redirect('/logout');
        }

        // Buscar listas de exercícios disponíveis
        $listas_exercicios = $this->getAvailableExerciseLists($aluno['turma_id']);
        
        // Buscar exercícios já realizados
        $exercicios_realizados = $this->getCompletedExercises($aluno['id']);

        $data = [
            'title' => 'Exercícios - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'listas_exercicios' => $listas_exercicios,
            'exercicios_realizados' => $exercicios_realizados,
            'current_page' => 'exercises'
        ];

        $this->viewWithLayout('student', 'student/exercises/exercises', $data);
    }

    public function startExercise()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $lista_id = $_POST['lista_id'] ?? '';

            if (empty($lista_id)) {
                throw new Exception('Lista de exercícios não especificada');
            }

            // Verificar se a lista existe
            $lista = $this->db->fetch(
                "SELECT * FROM listas_exercicios WHERE id = :lista_id",
                ['lista_id' => $lista_id]
            );

            if (!$lista) {
                throw new Exception('Lista de exercícios não encontrada');
            }

            // Buscar exercícios da lista
            $exercicios = $this->db->fetchAll(
                "SELECT * FROM exercicios WHERE lista_id = :lista_id ORDER BY ordem",
                ['lista_id' => $lista_id]
            );

            if (empty($exercicios)) {
                throw new Exception('Nenhum exercício encontrado nesta lista');
            }

            // Criar sessão de exercício
            $sessao_id = $this->db->insert(
                "INSERT INTO exercicios_sessoes (aluno_id, lista_id, started_at) 
                 VALUES (:aluno_id, :lista_id, NOW())",
                [
                    'aluno_id' => $aluno['id'],
                    'lista_id' => $lista_id
                ]
            );

            $this->json(['success' => true, 'sessao_id' => $sessao_id, 'exercicios' => $exercicios]);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function submitExerciseAnswer()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $sessao_id = $_POST['sessao_id'] ?? '';
            $exercicio_id = $_POST['exercicio_id'] ?? '';
            $resposta = $_POST['resposta'] ?? '';

            if (empty($sessao_id) || empty($exercicio_id) || empty($resposta)) {
                throw new Exception('Dados obrigatórios não fornecidos');
            }

            // Buscar exercício
            $exercicio = $this->db->fetch(
                "SELECT * FROM exercicios WHERE id = :exercicio_id",
                ['exercicio_id' => $exercicio_id]
            );

            if (!$exercicio) {
                throw new Exception('Exercício não encontrado');
            }

            $is_correct = ($resposta === $exercicio['resposta_correta']);

            // Salvar resposta
            $this->db->insert(
                "INSERT INTO exercicios_respostas (sessao_id, exercicio_id, aluno_id, resposta, is_correct, answered_at) 
                 VALUES (:sessao_id, :exercicio_id, :aluno_id, :resposta, :is_correct, NOW())",
                [
                    'sessao_id' => $sessao_id,
                    'exercicio_id' => $exercicio_id,
                    'aluno_id' => $aluno['id'],
                    'resposta' => $resposta,
                    'is_correct' => $is_correct ? 1 : 0
                ]
            );

            $this->json(['success' => true, 'is_correct' => $is_correct, 'explicacao' => $exercicio['explicacao']]);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function essays()
    {
        try {
            $user = $this->authManager->getUser();
            
            if (!$user) {
                $this->redirect('/logout');
                return;
            }
            
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome 
                 FROM alunos a 
                 LEFT JOIN turmas t ON a.turma_id = t.id 
                 WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                $this->redirect('/logout');
                return;
            }

            // Buscar temas disponíveis
            try {
                $temas_disponiveis = $this->getAvailableEssayThemes();
            } catch (Exception $e) {
                error_log("Erro ao buscar temas: " . $e->getMessage());
                $temas_disponiveis = [];
            }
            
            // Buscar redações do aluno (não rascunhos)
            try {
                $redacoes_aluno = $this->getStudentEssays($aluno['id']);
            } catch (Exception $e) {
                error_log("Erro ao buscar redações: " . $e->getMessage());
                $redacoes_aluno = [];
            }
            
            // Buscar rascunhos
            try {
                $rascunhos = $this->getRascunhos($aluno['id']);
            } catch (Exception $e) {
                error_log("Erro ao buscar rascunhos: " . $e->getMessage());
                $rascunhos = [];
            }

            $data = [
                'title' => 'Redação - EducaTudo',
                'user' => $user,
                'aluno' => $aluno,
                'temas_disponiveis' => $temas_disponiveis,
                'redacoes_aluno' => $redacoes_aluno,
                'rascunhos' => $rascunhos,
                'current_page' => 'essays'
            ];

            $this->viewWithLayout('student', 'student/essays', $data);
        } catch (Exception $e) {
            error_log("Erro fatal em essays(): " . $e->getMessage() . " em " . $e->getFile() . " linha " . $e->getLine());
            if (DEBUG) {
                throw $e;
            } else {
                http_response_code(500);
                echo '<h1>Erro Interno</h1><p>Ocorreu um erro inesperado. Tente novamente mais tarde.</p>';
                if (DEBUG) {
                    echo '<p>Erro: ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
            }
        }
    }

    public function createEssay()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $tema_id = $_POST['tema_id'] ?? null;
            $titulo = $_POST['titulo'] ?? '';
            $conteudo = $_POST['conteudo'] ?? '';
            $tipo = $_POST['tipo'] ?? 'padrao';
            $tema_gerado = $_POST['tema_gerado'] ?? null;

            if (empty($titulo) || empty($conteudo)) {
                throw new Exception('Título e conteúdo são obrigatórios');
            }

            // Preparar dados para inserção
            $insertData = [
                'aluno_id' => $aluno['id'],
                'titulo' => $titulo,
                'conteudo' => $conteudo,
                'tipo' => $tipo
            ];

            $sql = "INSERT INTO redacoes (aluno_id, tema_id, titulo, conteudo, tipo, created_at) VALUES (:aluno_id, :tema_id, :titulo, :conteudo, :tipo, NOW())";
            
            if ($tema_id) {
                $insertData['tema_id'] = $tema_id;
            } else {
                $sql = str_replace(':tema_id', 'NULL', $sql);
                unset($insertData['tema_id']);
            }

            // Se for tema gerado pela IA, salvar os dados do tema
            if ($tipo === 'ia_gerado' && $tema_gerado) {
                $temaData = json_decode($tema_gerado, true);
                if ($temaData) {
                    $insertData['tema_gerado'] = $tema_gerado;
                    $sql = "INSERT INTO redacoes (aluno_id, tema_id, titulo, conteudo, tipo, tema_gerado, created_at) VALUES (:aluno_id, :tema_id, :titulo, :conteudo, :tipo, :tema_gerado, NOW())";
                }
            }

            // Criar redação
            $redacao_id = $this->db->insert($sql, $insertData);

            // Processar correção automática (simulada)
            $this->processEssayCorrection($redacao_id);

            $this->json(['success' => true, 'redacao_id' => $redacao_id]);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function gerarTemaIA()
    {
        // Incluir OpenAIService se não estiver carregado
        if (!class_exists('App\Services\OpenAIService')) {
            require_once __DIR__ . '/../../Services/OpenAIService.php';
        }
        
        try {
            $user = $this->authManager->getUser();
            
            if (!$user) {
                $_SESSION['error_message'] = 'Usuário não está logado';
                $this->redirectToRedacoes();
                return;
            }
            
            if ($user['tipo'] !== 'aluno') {
                $_SESSION['error_message'] = 'Acesso negado: apenas alunos podem gerar temas';
                $this->redirectToRedacoes();
                return;
            }
            
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                $_SESSION['error_message'] = 'Aluno não encontrado na base de dados';
                $this->redirectToRedacoes();
                return;
            }

            // Verificar limite diário de geração de tema
            $verificacao = $this->podeExecutarAcao($aluno['id'], 'gerar_tema_redacao');
            if (!$verificacao['pode']) {
                $_SESSION['error_message'] = $verificacao['mensagem'];
                $this->redirectToRedacoes();
                return;
            }

            $themeRequest = $_POST['theme_request'] ?? '';
            
            if (empty($themeRequest)) {
                $_SESSION['error_message'] = 'Descrição do tema é obrigatória';
                $this->redirectToRedacoes();
                return;
            }

            // Verificar CSRF token
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $_SESSION['error_message'] = 'Token inválido';
                $this->redirectToRedacoes();
                return;
            }

            require_once __DIR__ . '/../../Services/CreditosService.php';
            $creditosTema = new \App\Services\CreditosService();
            $refTemaRedacao = 'tema_redacao_' . (int) $aluno['id'] . '_' . str_replace('.', '', uniqid('', true));
            $debitedTemaRedacao = false;
            try {
                $creditosTema->consumir('aluno', (int) $aluno['id'], 'redacao_gerar_tema_aluno', $refTemaRedacao);
                $debitedTemaRedacao = true;
            } catch (Exception $e) {
                if (stripos($e->getMessage(), 'TudiCoins') !== false || stripos($e->getMessage(), 'insuficientes') !== false || stripos($e->getMessage(), 'Créditos') !== false) {
                    $_SESSION['error_message'] = $e->getMessage();
                    $this->redirectToRedacoes();
                    return;
                }
                throw $e;
            }

            // Usar OpenAI para gerar tema
            $openaiService = new \App\Services\OpenAIService();
            
            // Buscar prompt personalizado do banco de dados
            $promptConfig = $this->db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                ['prompt_gerar_tema_redacao']
            );
            
            // Se o prompt não existir, usar o padrão
            if (!$promptConfig || empty($promptConfig['config_value'])) {
                $promptBase = "Você é um especialista em educação brasileira e criação de temas de redação para o ENEM. 
            
            O aluno solicitou um tema sobre: '{$themeRequest}'
            
            Crie um tema de redação completo seguindo o formato do ENEM com:
            1. Um título claro e específico
            2. Uma descrição detalhada do tema
            3. Uma proposta de intervenção sugerida
            4. Contexto atual relevante
            
            Responda em formato JSON com as seguintes chaves:
            - titulo: título do tema
            - descricao: descrição detalhada do tema
            - proposta_intervencao: sugestão de proposta de intervenção
            - contexto: contexto atual do tema
            
            Seja específico e atual, considerando a realidade brasileira.";
            } else {
                // Usar prompt personalizado e substituir a variável {themeRequest}
                $promptBase = str_replace('{themeRequest}', $themeRequest, $promptConfig['config_value']);
            }

            $response = $openaiService->generateText($promptBase);
            
            // Tentar decodificar JSON
            $temaData = json_decode($response, true);
            
            if (!$temaData) {
                // Se não conseguir decodificar, criar estrutura básica
                $temaData = [
                    'titulo' => "Redação sobre {$themeRequest}",
                    'descricao' => $response,
                    'proposta_intervencao' => 'Desenvolva uma proposta de intervenção social que respeite os direitos humanos.',
                    'contexto' => 'Considere a realidade brasileira atual ao desenvolver sua argumentação.'
                ];
            }

            // Registrar ação de geração de tema
            $this->db->insert(
                "INSERT INTO alunos_acoes_diarias (aluno_id, acao, created_at) 
                 VALUES (:aluno_id, 'gerar_tema_redacao', NOW())",
                ['aluno_id' => $aluno['id']]
            );

            // Salvar tema na sessão e redirecionar
            $_SESSION['tema_gerado'] = $temaData;
            $this->redirectToEscrever();
            return;

        } catch (Exception $e) {
            if (!empty($debitedTemaRedacao) && isset($creditosTema, $refTemaRedacao)) {
                try {
                    $creditosTema->estornarPorReferencia('redacao_gerar_tema_aluno', $refTemaRedacao);
                } catch (Exception $e2) {
                    error_log('Estorno crédito gerar tema redação: ' . $e2->getMessage());
                }
            }
            $_SESSION['error_message'] = 'Erro ao gerar tema: ' . $e->getMessage();
            $this->redirectToRedacoes();
            return;
        }
    }

    private function redirectToRedacoes()
    {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Redirecionando...</title>
        </head>
        <body>
            <script>
                window.location.href = "' . URL . '/redacoes";
            </script>
            <p>Redirecionando...</p>
        </body>
        </html>';
        exit;
    }

    private function redirectToEscrever()
    {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Redirecionando...</title>
        </head>
        <body>
            <script>
                window.location.href = "' . URL . '/redacoes/escrever";
            </script>
            <p>Redirecionando para a página de redação...</p>
        </body>
        </html>';
        exit;
    }

    public function transcreverImagem()
    {
        // Garantir que a sessão está ativa
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Limpar qualquer output anterior
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Configurar para não exibir erros na tela
        $oldErrorReporting = error_reporting(E_ALL);
        $oldDisplayErrors = ini_set('display_errors', 0);
        
        // Log de debug detalhado
        $logFile = __DIR__ . '/../../storage/logs/transcricao_' . date('Y-m-d') . '.log';
        $log = function($message) use ($logFile) {
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[{$timestamp}] {$message}\n";
            error_log($logMessage, 3, $logFile);
            error_log($logMessage); // Também no log padrão
        };
        
        $log("=== INÍCIO TRANSCRIÇÃO DEBUG ===");
        $log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
        $log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        $log("Session ID: " . session_id());
        $log("Session status: " . session_status());
        $log("POST keys: " . implode(', ', array_keys($_POST)));
        $log("POST token: " . ($_POST['_token'] ?? 'NÃO ENVIADO'));
        $log("SESSION token: " . ($_SESSION['csrf_token'] ?? 'NÃO DEFINIDO'));
        $log("User ID: " . ($_SESSION['user_id'] ?? 'NÃO DEFINIDO'));
        $log("FILES keys: " . implode(', ', array_keys($_FILES)));
        if (isset($_FILES['imagem'])) {
            $log("FILES[imagem] error: " . ($_FILES['imagem']['error'] ?? 'N/A'));
            $log("FILES[imagem] name: " . ($_FILES['imagem']['name'] ?? 'N/A'));
            $log("FILES[imagem] size: " . ($_FILES['imagem']['size'] ?? 'N/A'));
            $log("FILES[imagem] type: " . ($_FILES['imagem']['type'] ?? 'N/A'));
        }
        
        // Verificar se há token CSRF
        if (!isset($_POST['_token']) || empty($_POST['_token'])) {
            $log("ERRO: Token CSRF não enviado");
            $log("POST completo: " . json_encode($_POST, JSON_UNESCAPED_UNICODE));
            $log("FILES completo: " . json_encode($_FILES, JSON_UNESCAPED_UNICODE));
            error_reporting($oldErrorReporting);
            ini_set('display_errors', $oldDisplayErrors);
            $this->json(['error' => 'Token CSRF não enviado. Recarregue a página e tente novamente.', 'csrf_token' => $this->refreshCsrfToken()], 400);
        }
        
        $log("Token CSRF encontrado, verificando...");
        if (!$this->verifyCsrfToken($_POST['_token'])) {
            $log("ERRO: Token CSRF inválido");
            $log("Token recebido: " . $_POST['_token']);
            $log("Token na sessão: " . ($_SESSION['csrf_token'] ?? 'NÃO DEFINIDO'));
            $log("Tokens são iguais? " . (($_POST['_token'] === ($_SESSION['csrf_token'] ?? '')) ? 'SIM' : 'NÃO'));
            error_reporting($oldErrorReporting);
            ini_set('display_errors', $oldDisplayErrors);
            $this->json(['error' => 'Token CSRF inválido. Recarregue a página e tente novamente.', 'csrf_token' => $this->refreshCsrfToken()], 400);
        }
        $log("Token CSRF válido!");

        try {
            $log("Buscando usuário...");
            $user = $this->authManager->getUser();
            $log("User encontrado: " . ($user['id'] ?? 'NÃO ENCONTRADO'));
            
            $log("Buscando aluno no banco...");
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                $log("ERRO: Aluno não encontrado no banco");
                throw new Exception('Aluno não encontrado');
            }
            $log("Aluno encontrado: ID " . $aluno['id']);

            // Verificar se foi enviada uma imagem
            $log("Verificando se imagem foi enviada...");
            if (!isset($_FILES['imagem'])) {
                $log("ERRO: FILES[imagem] não está definido");
                throw new Exception('Nenhuma imagem foi enviada');
            }
            
            if ($_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
                $log("ERRO: Erro no upload - código: " . $_FILES['imagem']['error']);
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'Arquivo excede o tamanho máximo permitido pelo PHP',
                    UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o tamanho máximo do formulário',
                    UPLOAD_ERR_PARTIAL => 'Upload parcial',
                    UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
                    UPLOAD_ERR_NO_TMP_DIR => 'Falta diretório temporário',
                    UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever arquivo',
                    UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão'
                ];
                $errorMsg = $errorMessages[$_FILES['imagem']['error']] ?? 'Erro desconhecido no upload';
                $log("Mensagem de erro: " . $errorMsg);
                throw new Exception('Erro no upload: ' . $errorMsg);
            }
            $log("Imagem enviada com sucesso!");

            $imagem = $_FILES['imagem'];
            $log("Imagem recebida - Nome: " . $imagem['name'] . ", Tipo: " . $imagem['type'] . ", Tamanho: " . $imagem['size']);

            // Validar tipo de arquivo
            $log("Validando tipo de arquivo...");
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!in_array($imagem['type'], $allowedTypes)) {
                $log("ERRO: Tipo de arquivo não permitido: " . $imagem['type']);
                throw new Exception('Tipo de arquivo não permitido. Use JPG, JPEG, PNG ou WebP.');
            }
            $log("Tipo de arquivo válido!");

            // Validar tamanho (10MB)
            $log("Validando tamanho do arquivo...");
            if ($imagem['size'] > 10 * 1024 * 1024) {
                $log("ERRO: Arquivo muito grande: " . $imagem['size'] . " bytes");
                throw new Exception('Arquivo muito grande. Máximo 10MB.');
            }
            $log("Tamanho do arquivo válido!");

            // Salvar imagem via MediaStorageService
            $log("Salvando imagem...");
            $fileName = 'redacao_' . $aluno['id'] . '_' . time() . '_' . uniqid() . '.' . pathinfo($imagem['name'], PATHINFO_EXTENSION);
            require_once __DIR__ . '/../../Services/MediaStorageService.php';
            $key = MediaStorageService::userKey('student', $aluno['id'], $fileName);
            $media = new MediaStorageService($this->config);
            if (!$media->put('redacoes', $key, $imagem['tmp_name'], $imagem['type'])) {
                $log("ERRO: Falha ao salvar imagem no storage");
                throw new Exception('Erro ao salvar imagem');
            }
            $log("Arquivo salvo no storage: redacoes/" . $key);

            // Usar OpenAI para transcrever texto da imagem
            $log("Carregando OpenAIService...");
            if (!class_exists('App\Services\OpenAIService')) {
                require_once __DIR__ . '/../../Services/OpenAIService.php';
            }
            $openaiService = new \App\Services\OpenAIService();
            $log("OpenAIService carregado!");
            
            // Ler imagem como base64 (conteúdo ainda em tmp)
            $log("Lendo imagem e convertendo para base64...");
            $fileContent = file_get_contents($imagem['tmp_name']);
            $imageData = base64_encode($fileContent);
            $log("Imagem convertida para base64 - Tamanho: " . strlen($imageData) . " caracteres");
            
            // Buscar prompt personalizado do banco de dados
            $log("Buscando prompt personalizado...");
            $promptConfig = $this->db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                ['prompt_transcrever_imagem']
            );
            
            // Se o prompt não existir, usar o padrão
            if (!$promptConfig || empty($promptConfig['config_value'])) {
                $prompt = "Transcreva todo o texto presente nesta imagem. Retorne apenas o texto transcrito, sem comentários adicionais.";
                $log("Usando prompt padrão");
            } else {
                $prompt = $promptConfig['config_value'];
                $log("Usando prompt personalizado do banco");
            }

            try {
                $log("Iniciando análise da imagem com OpenAI...");
                $log("Chamando analyzeImage() com prompt: " . substr($prompt, 0, 100) . "...");
                $inicioAnalise = microtime(true);
                
                // Aumentar timeout do PHP antes de chamar analyzeImage
                set_time_limit(300);
                ini_set('max_execution_time', 300);
                
                $textoTranscrito = $openaiService->analyzeImage($imageData, $prompt);
                
                $tempoAnalise = microtime(true) - $inicioAnalise;
                $log("Transcrição concluída em " . round($tempoAnalise, 2) . " segundos! Tamanho do texto: " . strlen($textoTranscrito) . " caracteres");
                
                // Retornar apenas o texto transcrito (sem criar redação ainda)
                $log("Retornando sucesso!");
                $this->json(['success' => true, 'texto_transcrito' => $textoTranscrito]);
            } catch (Exception $e) {
                $log("ERRO na análise da imagem: " . $e->getMessage());
                $log("Stack trace: " . $e->getTraceAsString());
                
                // Verificar se é erro de quota/limite
                $mensagemErro = $e->getMessage();
                $isQuotaError = false;
                $tipoErro = 'geral';
                
                // Verificar diferentes tipos de erro de quota/limite
                $mensagemLower = strtolower($mensagemErro);
                
                // Extrair tipo de erro da mensagem se disponível (formato: "[Tipo: tipo_erro]")
                if (preg_match('/\[tipo:\s*([^\]]+)\]/i', $mensagemErro, $matches)) {
                    $tipoErro = trim($matches[1]);
                    // Remover o tipo da mensagem para exibir apenas a mensagem limpa
                    $mensagemErro = preg_replace('/\s*\[tipo:\s*[^\]]+\]/i', '', $mensagemErro);
                }
                
                // Verificar erros de quota/limite
                if (strpos($mensagemLower, 'quota') !== false || 
                    strpos($mensagemLower, 'insufficient_quota') !== false ||
                    strpos($mensagemLower, 'rate limit') !== false ||
                    strpos($mensagemLower, 'billing') !== false ||
                    strpos($mensagemLower, 'payment') !== false ||
                    strpos($mensagemLower, 'credit') !== false ||
                    strpos($mensagemLower, 'limit exceeded') !== false ||
                    strpos($mensagemLower, 'resource exhausted') !== false ||
                    $tipoErro === 'quota') {
                    $isQuotaError = true;
                    $tipoErro = 'quota';
                    if (strpos($mensagemLower, 'google vision') !== false) {
                        $mensagemErro = 'A cota de uso do Google Vision foi excedida ou o limite de pagamento foi atingido. Verifique sua conta Google Cloud.';
                    } else {
                        $mensagemErro = 'A cota de uso da API foi excedida ou o limite de pagamento foi atingido. Por favor, verifique sua conta ou tente novamente mais tarde.';
                    }
                } elseif (strpos($mensagemLower, 'timeout') !== false || 
                          strpos($mensagemLower, 'timed out') !== false ||
                          $tipoErro === 'timeout') {
                    $tipoErro = 'timeout';
                    $mensagemErro = 'O tempo de processamento expirou. Tente novamente com uma imagem menor ou mais clara.';
                } elseif (strpos($mensagemLower, 'invalid') !== false || 
                          strpos($mensagemLower, 'invalid image') !== false ||
                          strpos($mensagemLower, 'formato') !== false ||
                          $tipoErro === 'imagem_invalida' ||
                          $tipoErro === 'formato_invalido') {
                    $tipoErro = 'imagem_invalida';
                    $mensagemErro = 'A imagem enviada é inválida ou não pôde ser processada. Verifique o formato e tente novamente.';
                } elseif (strpos($mensagemLower, 'credentials') !== false || 
                          strpos($mensagemLower, 'authentication') !== false ||
                          strpos($mensagemLower, 'credenciais') !== false ||
                          $tipoErro === 'credenciais' ||
                          $tipoErro === 'credenciais_ausentes') {
                    $tipoErro = 'credenciais';
                    $mensagemErro = 'Erro de autenticação com o serviço de IA. As credenciais podem estar inválidas ou expiradas. Contate o administrador.';
                } elseif (strpos($mensagemLower, 'erro temporário') !== false || 
                          strpos($mensagemLower, 'service unavailable') !== false ||
                          strpos($mensagemLower, 'internal error') !== false ||
                          $tipoErro === 'erro_servidor_google') {
                    $tipoErro = 'erro_servidor';
                    $mensagemErro = 'Erro temporário no serviço de IA. Tente novamente em alguns instantes.';
                } elseif (strpos($mensagemLower, 'too large') !== false || 
                          strpos($mensagemLower, 'imagem grande') !== false ||
                          $tipoErro === 'imagem_grande') {
                    $tipoErro = 'imagem_grande';
                    $mensagemErro = 'A imagem é muito grande para processamento. Tente com uma imagem menor (máximo 20MB).';
                }
                
                // Logar erro no sistema de logs
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/../Core/Logger.php';
                }
                Logger::openaiError(
                    "Erro na transcrição de imagem: " . $e->getMessage(),
                    [
                        'exception' => $e,
                        'aluno_id' => $aluno['id'] ?? null,
                        'image_size' => strlen($imageData),
                        'tipo_erro' => $tipoErro,
                        'is_quota_error' => $isQuotaError
                    ]
                );
                
                // Se não for erro de quota, tentar fallback com Google Vision
                if (!$isQuotaError) {
                    $log("Tentando fallback com Google Vision...");
                    error_log("Erro na transcrição: " . $e->getMessage());
                    try {
                        // Tentar obter apenas o texto bruto do Google Vision como último recurso
                        $textoBruto = $openaiService->transcreverComGoogleVision($imageData);
                        if (!empty($textoBruto)) {
                            $this->json([
                                'success' => true, 
                                'texto_transcrito' => $textoBruto,
                                'warning' => 'Texto transcrito sem formatação automática. Alguns serviços de IA podem estar indisponíveis no momento.'
                            ]);
                            return;
                        }
                    } catch (Exception $e2) {
                        error_log("Erro ao obter texto bruto: " . $e2->getMessage());
                        // Se o fallback também falhar com quota, não tentar novamente
                        $mensagemLower2 = strtolower($e2->getMessage());
                        if (strpos($mensagemLower2, 'quota') !== false || 
                            strpos($mensagemLower2, 'insufficient_quota') !== false ||
                            strpos($mensagemLower2, 'billing') !== false) {
                            $isQuotaError = true;
                            $tipoErro = 'quota';
                            $mensagemErro = 'A cota de uso da API foi excedida ou o limite de pagamento foi atingido. Por favor, verifique sua conta ou tente novamente mais tarde.';
                        }
                    }
                }
                
                // Se não conseguiu fallback, retornar erro
                error_reporting($oldErrorReporting);
                ini_set('display_errors', $oldDisplayErrors);
                
                $this->json([
                    'success' => false,
                    'error' => $mensagemErro,
                    'tipo_erro' => $tipoErro,
                    'is_quota_error' => $isQuotaError
                ], 400);
            }

        } catch (Exception $e) {
            $log("ERRO GERAL na transcrição: " . $e->getMessage());
            $log("Stack trace completo: " . $e->getTraceAsString());
            $log("=== FIM TRANSCRIÇÃO DEBUG ===");
            // Restaurar configurações de erro
            error_reporting($oldErrorReporting);
            ini_set('display_errors', $oldDisplayErrors);
            
            $this->json(['error' => $e->getMessage()], 400);
        } finally {
            // Restaurar configurações de erro
            error_reporting($oldErrorReporting);
            ini_set('display_errors', $oldDisplayErrors);
        }
    }

    public function corrigirRedacao()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $_SESSION['error_message'] = 'Token inválido';
            $this->redirectToRedacoes();
            return;
        }

        try {
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'aluno') {
                $_SESSION['error_message'] = 'Acesso negado';
                $this->redirectToRedacoes();
                return;
            }

            // Buscar aluno
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                $_SESSION['error_message'] = 'Aluno não encontrado';
                $this->redirectToRedacoes();
                return;
            }

            // Verificar limite diário de correção de redação
            $verificacao = $this->podeExecutarAcao($aluno['id'], 'corrigir_redacao');
            if (!$verificacao['pode']) {
                $_SESSION['error_message'] = $verificacao['mensagem'];
                $this->redirectToRedacoes();
                return;
            }

            $redacao_id = $_POST['redacao_id'] ?? null;
            $conteudo = $_POST['conteudo'] ?? '';
            $titulo = $_POST['titulo'] ?? 'Redação';
            $tema = $_POST['tema'] ?? 'Redação';
            
            if (empty($conteudo)) {
                $_SESSION['error_message'] = 'Conteúdo da redação é obrigatório';
                $this->redirectToRedacoes();
                return;
            }

            // Se tem redacao_id, verificar se é rascunho
            if ($redacao_id) {
                // Verificar se a coluna eh_rascunho existe antes de usar
                $hasEhRascunho = false;
                try {
                    $columnCheck = $this->db->fetch("SHOW COLUMNS FROM redacoes LIKE 'eh_rascunho'");
                    $hasEhRascunho = !empty($columnCheck);
                } catch (Exception $e) {
                    error_log("Erro ao verificar coluna eh_rascunho: " . $e->getMessage());
                }
                
                $rascunho = null;
                if ($hasEhRascunho) {
                    try {
                        $rascunho = $this->db->fetch(
                            "SELECT eh_rascunho FROM redacoes WHERE id = :id AND aluno_id = :aluno_id",
                            ['id' => $redacao_id, 'aluno_id' => $user['id']]
                        );
                    } catch (Exception $e) {
                        error_log("Erro ao buscar rascunho: " . $e->getMessage());
                        $rascunho = null;
                    }
                }
                
                if ($rascunho && $hasEhRascunho && isset($rascunho['eh_rascunho']) && $rascunho['eh_rascunho'] == 1) {
                    // Converter rascunho em redação final
                    // Buscar tema_texto do rascunho para preservar se for tema gerado pela IA
                    $rascunhoCompleto = $this->db->fetch(
                        "SELECT tema_texto FROM redacoes WHERE id = :id AND aluno_id = :aluno_id",
                        ['id' => $redacao_id, 'aluno_id' => $user['id']]
                    );
                    
                    $updateParams = [
                        'id' => $redacao_id,
                        'aluno_id' => $user['id'],
                        'titulo' => $titulo,
                        'conteudo' => $conteudo,
                        'tema' => $tema,
                        'texto' => $conteudo
                    ];
                    
                    $updateSql = "UPDATE redacoes 
                                 SET titulo = :titulo, conteudo = :conteudo, tema = :tema, texto = :texto, 
                                     eh_rascunho = 0, updated_at = NOW()
                                 WHERE id = :id AND aluno_id = :aluno_id";
                    
                    // Se tiver tema_texto (tema gerado pela IA), preservar
                    if ($rascunhoCompleto && !empty($rascunhoCompleto['tema_texto'])) {
                        $updateSql = "UPDATE redacoes 
                                     SET titulo = :titulo, conteudo = :conteudo, tema = :tema, texto = :texto, 
                                         tema_texto = :tema_texto, eh_rascunho = 0, updated_at = NOW()
                                     WHERE id = :id AND aluno_id = :aluno_id";
                        $updateParams['tema_texto'] = $rascunhoCompleto['tema_texto'];
                    }
                    
                    $this->db->update($updateSql, $updateParams);
                } else if ($rascunho && !$hasEhRascunho) {
                    // Se não tem coluna eh_rascunho, apenas atualizar normalmente
                    $this->db->update(
                        "UPDATE redacoes 
                         SET titulo = :titulo, conteudo = :conteudo, tema = :tema, texto = :texto, updated_at = NOW()
                         WHERE id = :id AND aluno_id = :aluno_id",
                        [
                            'id' => $redacao_id,
                            'aluno_id' => $user['id'],
                            'titulo' => $titulo,
                            'conteudo' => $conteudo,
                            'tema' => $tema,
                            'texto' => $conteudo
                        ]
                    );
                } else {
                    // Atualizar redação existente
                    $this->db->update(
                        "UPDATE redacoes 
                         SET titulo = :titulo, conteudo = :conteudo, tema = :tema, texto = :texto, updated_at = NOW()
                         WHERE id = :id AND aluno_id = :aluno_id",
                        [
                            'id' => $redacao_id,
                            'aluno_id' => $user['id'],
                            'titulo' => $titulo,
                            'conteudo' => $conteudo,
                            'tema' => $tema,
                            'texto' => $conteudo
                        ]
                    );
                }
            } else {
                // Criar nova redação
                // Verificar se a coluna eh_rascunho existe antes de usar
                $hasEhRascunho = false;
                try {
                    $columnCheck = $this->db->fetch("SHOW COLUMNS FROM redacoes LIKE 'eh_rascunho'");
                    $hasEhRascunho = !empty($columnCheck);
                } catch (Exception $e) {
                    error_log("Erro ao verificar coluna eh_rascunho: " . $e->getMessage());
                }
                
                if ($hasEhRascunho) {
                    $redacao_id = $this->db->insert(
                        "INSERT INTO redacoes (aluno_id, tema_id, titulo, conteudo, tema, texto, eh_rascunho, created_at) 
                         VALUES (:aluno_id, NULL, :titulo, :conteudo, :tema, :texto, 0, NOW())",
                        [
                            'aluno_id' => $user['id'],
                            'titulo' => $titulo,
                            'conteudo' => $conteudo,
                            'tema' => $tema,
                            'texto' => $conteudo
                        ]
                    );
                } else {
                    // Se a coluna não existe, inserir sem ela
                    $redacao_id = $this->db->insert(
                        "INSERT INTO redacoes (aluno_id, tema_id, titulo, conteudo, tema, texto, created_at) 
                         VALUES (:aluno_id, NULL, :titulo, :conteudo, :tema, :texto, NOW())",
                        [
                            'aluno_id' => $user['id'],
                            'titulo' => $titulo,
                            'conteudo' => $conteudo,
                            'tema' => $tema,
                            'texto' => $conteudo
                        ]
                    );
                }
            }

            // Buscar redação
            $redacao = $this->db->fetch(
                "SELECT r.*, a.nome as aluno_nome 
                 FROM redacoes r 
                 LEFT JOIN alunos a ON r.aluno_id = a.id 
                 WHERE r.id = :redacao_id",
                ['redacao_id' => $redacao_id]
            );

            if (!$redacao) {
                throw new Exception('Redação não encontrada');
            }

            // Usar OpenAI para corrigir com critérios ENEM
            // Incluir OpenAIService se não estiver carregado
            if (!class_exists('App\Services\OpenAIService')) {
                require_once __DIR__ . '/../../Services/OpenAIService.php';
            }
            
            $openaiService = new \App\Services\OpenAIService();
            
            // Buscar prompt personalizado do banco de dados
            $promptConfig = $this->db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                ['prompt_corrigir_redacao']
            );
            
            // Se o prompt não existir, usar o padrão
            if (!$promptConfig || empty($promptConfig['config_value'])) {
                $promptBase = "Você é um corretor especializado em redações do ENEM. 

            Corrija a redação abaixo nos critérios e parâmetros oficiais do ENEM:

            COMPETÊNCIA I – Domínio da norma padrão da Língua Portuguesa
            COMPETÊNCIA II – Compreensão da proposta e desenvolvimento do tema  
            COMPETÊNCIA III – Seleção e organização de argumentos
            COMPETÊNCIA IV – Coesão e coerência
            COMPETÊNCIA V – Proposta de intervenção

            REDAÇÃO:
            Título: {$redacao['titulo']}
            Conteúdo: {$redacao['conteudo']}

            Responda em formato JSON com as seguintes chaves:
            - competencia_1: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - competencia_2: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - competencia_3: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - competencia_4: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - competencia_5: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - nota_final: soma das 5 competências
            - comentarios_gerais: \"comentários gerais sobre a redação\"
            - sugestoes_melhoria: \"sugestões específicas para melhoria\"

            Seja detalhado e construtivo nas explicações.";
            } else {
                // Usar prompt personalizado e substituir variáveis
                $promptBase = $promptConfig['config_value'];
                $promptBase = str_replace('{titulo}', $redacao['titulo'], $promptBase);
                $promptBase = str_replace('{conteudo}', $redacao['conteudo'], $promptBase);
            }

            // Gerar correção com limite de tokens para resposta completa
            try {
                $response = $openaiService->generateText($promptBase, [
                    'max_tokens' => 4000,  // Suficiente para resposta de correção completa com todas as competências
                    'temperature' => 0.4
                ]);
                
                // Tentar decodificar JSON
                $correcaoData = json_decode($response, true);
                
                if (!$correcaoData) {
                    throw new Exception('Erro ao processar correção da IA');
                }
            } catch (Exception $e) {
                // Logar erro no sistema de logs
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/../Core/Logger.php';
                }
                Logger::openaiError(
                    "Erro ao corrigir redação: " . $e->getMessage(),
                    [
                        'exception' => $e,
                        'redacao_id' => $redacao_id,
                        'aluno_id' => $user['id'] ?? null
                    ]
                );
                
                error_log("Erro ao corrigir redação: " . $e->getMessage());
                $_SESSION['error_message'] = 'Não foi possível processar a correção no momento. Por favor, tente novamente mais tarde. Se o problema persistir, entre em contato com o suporte.';
                $this->redirectToRedacoes();
                return;
            }

            // Salvar correção no banco usando os campos corretos da tabela
            $this->db->update(
                "UPDATE redacoes SET 
                    competencia_1 = :comp1_nota,
                    competencia_2 = :comp2_nota,
                    competencia_3 = :comp3_nota,
                    competencia_4 = :comp4_nota,
                    competencia_5 = :comp5_nota,
                    nota_final = :nota_final,
                    feedback_ia = :feedback,
                    corrigida_em = NOW()
                 WHERE id = :redacao_id",
                [
                    'comp1_nota' => $correcaoData['competencia_1']['nota'] ?? 0,
                    'comp2_nota' => $correcaoData['competencia_2']['nota'] ?? 0,
                    'comp3_nota' => $correcaoData['competencia_3']['nota'] ?? 0,
                    'comp4_nota' => $correcaoData['competencia_4']['nota'] ?? 0,
                    'comp5_nota' => $correcaoData['competencia_5']['nota'] ?? 0,
                    'nota_final' => $correcaoData['nota_final'] ?? 0,
                    'feedback' => json_encode($correcaoData),
                    'redacao_id' => $redacao_id
                ]
            );

            $_SESSION['success_message'] = 'Redação corrigida com sucesso!';
            // Redirecionar para a página de resultado da correção
            $this->redirect(URL . '/redacoes/' . $redacao_id);

        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao corrigir redação: ' . $e->getMessage();
            $this->redirectToRedacoes();
        }
    }

    // Métodos auxiliares privados

    private function getStudentStats($aluno_id)
    {
        $aluno_id = (int) $aluno_id;
        $row = $this->db->fetch(
            "SELECT
                (SELECT COUNT(*) FROM exercicios_sessoes WHERE aluno_id = :id1) AS total_exercicios_bd,
                (SELECT COUNT(*) FROM listas_personalizadas_sessoes WHERE aluno_id = :id2 AND status = 'finalizado') AS total_exercicios_ia,
                (SELECT COUNT(*) FROM redacoes WHERE aluno_id = :id3) AS total_redacoes,
                (SELECT COUNT(*) FROM tudinha_conversas WHERE aluno_id = :id4 AND excluida = 0) AS total_conversas,
                (
                    SELECT SUM(
                        LEAST(
                            (SELECT COUNT(*) FROM tudinha_mensagens mc1 WHERE mc1.conversa_id = cc2.id AND mc1.is_ia = 0),
                            (SELECT COUNT(*) FROM tudinha_mensagens mc2 WHERE mc2.conversa_id = cc2.id AND mc2.is_ia = 1)
                        )
                    )
                    FROM tudinha_conversas cc2
                    WHERE cc2.aluno_id = :id5 AND cc2.excluida = 0
                ) AS total_interacoes_chat",
            [
                'id1' => $aluno_id,
                'id2' => $aluno_id,
                'id3' => $aluno_id,
                'id4' => $aluno_id,
                'id5' => $aluno_id,
            ]
        );

        $mediaRow = $this->db->fetch(
            "SELECT AVG(CASE WHEN re.is_correct = 1 THEN 1 ELSE 0 END) * 100 AS media_acertos
             FROM exercicios_respostas re
             INNER JOIN exercicios_sessoes se ON se.id = re.sessao_id
             WHERE se.aluno_id = :aluno_id",
            ['aluno_id' => $aluno_id]
        );

        $jornadaStats = $this->db->fetch(
            "SELECT COUNT(*) as total,
                    SUM(CASE WHEN base.tipo = 'dissertativa' AND base.corrigida = 0 AND (base.pontuacao IS NULL OR base.pontuacao <= 0) THEN 0 WHEN base.pontuacao > 0 THEN 1 ELSE 0 END) as corretas,
                    SUM(CASE WHEN base.tipo = 'dissertativa' AND base.corrigida = 0 AND (base.pontuacao IS NULL OR base.pontuacao <= 0) THEN 0 WHEN base.pontuacao > 0 THEN 0 ELSE 1 END) as erros
             FROM (
                SELECT jpa.exercicio_modulo_id,
                       MAX(jpa.pontuacao) as pontuacao,
                       MAX(me.tipo) as tipo,
                       MAX(CASE WHEN JSON_VALID(jpa.resposta) AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(jpa.resposta, '$.correcao_status')), '') = 'corrigida' THEN 1 ELSE 0 END) as corrigida
                FROM jornadas_progresso_alunos jpa
                LEFT JOIN jornadas_modulos_exercicios me ON me.id = jpa.exercicio_modulo_id
                WHERE jpa.aluno_id = :aluno_id
                  AND jpa.atividade_tipo = 'exercicio_modulo'
                  AND jpa.resposta IS NOT NULL
                GROUP BY jpa.exercicio_modulo_id
             ) base",
            ['aluno_id' => $aluno_id]
        );

        $provaStats = $this->db->fetch(
            "SELECT COUNT(*) as total, SUM(CASE WHEN pr.correta = 1 THEN 1 ELSE 0 END) as corretas
             FROM provas_respostas pr
             WHERE pr.aluno_id = :aluno_id",
            ['aluno_id' => $aluno_id]
        );

        $totalExerciciosBd = (int) ($row['total_exercicios_bd'] ?? 0);
        $totalExerciciosIa = (int) ($row['total_exercicios_ia'] ?? 0);
        $jornadaTotal = (int) ($jornadaStats['total'] ?? 0);
        $jornadaCorretas = (int) ($jornadaStats['corretas'] ?? 0);
        $jornadaErros = (int) ($jornadaStats['erros'] ?? 0);
        $jornadaAvaliadas = $jornadaCorretas + $jornadaErros;
        $jornadaPercentual = $jornadaAvaliadas > 0 ? round(($jornadaCorretas / $jornadaAvaliadas) * 100, 1) : 0;
        $provaTotal = (int) ($provaStats['total'] ?? 0);
        $provaCorretas = (int) ($provaStats['corretas'] ?? 0);
        $provaPercentual = $provaTotal > 0 ? round(($provaCorretas / $provaTotal) * 100, 1) : 0;

        return [
            'total_exercicios_realizados' => $totalExerciciosBd + $totalExerciciosIa,
            'total_redacoes' => (int) ($row['total_redacoes'] ?? 0),
            'total_conversas' => (int) ($row['total_conversas'] ?? 0),
            'total_interacoes_chat' => (int) ($row['total_interacoes_chat'] ?? 0),
            'media_acertos' => round((float) ($mediaRow['media_acertos'] ?? 0), 1),
            'jornada_percentual' => $jornadaPercentual,
            'jornada_total' => $jornadaTotal,
            'jornada_corretas' => $jornadaCorretas,
            'prova_percentual' => $provaPercentual,
            'prova_total' => $provaTotal,
            'prova_corretas' => $provaCorretas,
        ];
    }

    private function getActiveJourneys($turma_id, $aluno_id = null)
    {
        $params = ['turma_id' => $turma_id];
        $subModulos = '';
        $subConcluidos = '';
        if ($aluno_id) {
            $params['aluno_id'] = $aluno_id;
            $subModulos = ", (SELECT COUNT(*) FROM jornadas_modulos jm WHERE jm.jornada_id = j.id) as total_modulos";
            $subConcluidos = ", (SELECT COUNT(*) FROM jornadas_progresso_alunos jpa2 WHERE jpa2.jornada_id = j.id AND jpa2.aluno_id = :aluno_id AND jpa2.atividade_tipo = 'modulo' AND jpa2.status = 'concluido') as modulos_concluidos";
        }
        // Busca jornadas da turma principal OU com estrutura (turmas_selecionadas checado em PHP por compatibilidade MariaDB)
        $jornadas = $this->db->fetchAll(
            "SELECT j.*, 
                    p.nome as professor_nome,
                    m.nome as materia_nome
                    {$subModulos}
                    {$subConcluidos}
             FROM jornadas j
             JOIN professores p ON j.professor_id = p.id
             LEFT JOIN materias m ON j.materia_id = m.id
             WHERE (j.turma_id = :turma_id OR (j.estrutura IS NOT NULL AND j.estrutura != ''))
                 AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status IS NULL OR j.status = '')
                 AND (j.status != 'pausada' OR j.status IS NULL)
                 AND (j.ativo = 1 OR j.ativo IS NULL)
             ORDER BY j.created_at DESC
             LIMIT 30",
            $params
        );
        // Filtra em PHP: turma principal ou turma presente em estrutura.turmas_selecionadas
        $jornadas = array_filter($jornadas, function ($j) use ($turma_id) {
            if ((int) $j['turma_id'] === (int) $turma_id) {
                return true;
            }
            $e = json_decode($j['estrutura'] ?? '', true);
            return is_array($e) && !empty($e['turmas_selecionadas']) && in_array((int) $turma_id, array_map('intval', $e['turmas_selecionadas']), true);
        });
        $jornadas = array_slice(array_values($jornadas), 0, 30);

        $jornadasDisponiveis = [];
        $dataAtual = date('Y-m-d');

        foreach ($jornadas as $jornada) {
            if ($aluno_id) {
                $totalMod = (int)($jornada['total_modulos'] ?? 0);
                $concluidosMod = (int)($jornada['modulos_concluidos'] ?? 0);
                $jornada['jornada_concluida'] = ($totalMod > 0 && $concluidosMod >= $totalMod);
            }
            $dataInicio = null;
            if (!empty($jornada['estrutura'])) {
                $estrutura = json_decode($jornada['estrutura'], true);
                if (is_array($estrutura) && isset($estrutura['data_inicio'])) {
                    $dataInicio = $estrutura['data_inicio'];
                    $jornada['data_inicio'] = $dataInicio;
                }
                if (is_array($estrutura) && isset($estrutura['hora_inicio'])) {
                    $jornada['hora_inicio'] = $estrutura['hora_inicio'];
                }
                if (is_array($estrutura) && isset($estrutura['data_fim'])) {
                    $jornada['data_fim'] = $estrutura['data_fim'];
                }
                if (is_array($estrutura) && isset($estrutura['hora_fim'])) {
                    $jornada['hora_fim'] = $estrutura['hora_fim'];
                }
            }
            if (!$dataInicio || $dataAtual >= date('Y-m-d', strtotime($dataInicio))) {
                $jornadasDisponiveis[] = $jornada;
            }
        }
        
        return $jornadasDisponiveis;
    }

    /**
     * Resumo das jornadas do aluno no período de 30 dias (15 para trás e 15 para frente).
     */
    private function getJornadasPeriodoResumo(array $aluno): array
    {
        $turmaId = isset($aluno['turma_id']) ? (int) $aluno['turma_id'] : 0;
        $alunoId = isset($aluno['id']) ? (int) $aluno['id'] : 0;
        if ($turmaId <= 0 || $alunoId <= 0) {
            return [
                'inicio' => date('Y-m-d', strtotime('-15 days')),
                'fim' => date('Y-m-d', strtotime('+15 days')),
                'total' => 0,
                'em_andamento' => 0,
                'concluidas' => 0,
            ];
        }

        $periodoInicio = date('Y-m-d', strtotime('-15 days'));
        $periodoFim = date('Y-m-d', strtotime('+15 days'));

        $jornadas = $this->db->fetchAll(
            "SELECT j.id, j.turma_id, j.estrutura, j.created_at,
                    (SELECT COUNT(*) FROM jornadas_modulos jm WHERE jm.jornada_id = j.id) as total_modulos,
                    (SELECT COUNT(*) FROM jornadas_progresso_alunos jpa2 WHERE jpa2.jornada_id = j.id AND jpa2.aluno_id = :aluno_id AND jpa2.atividade_tipo = 'modulo' AND jpa2.status = 'concluido') as modulos_concluidos
             FROM jornadas j
             WHERE (j.turma_id = :turma_id OR (j.estrutura IS NOT NULL AND j.estrutura != ''))
               AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status IS NULL OR j.status = '')
               AND (j.status != 'pausada' OR j.status IS NULL)
               AND (j.ativo = 1 OR j.ativo IS NULL)
             ORDER BY j.created_at DESC
             LIMIT 200",
            [
                'turma_id' => $turmaId,
                'aluno_id' => $alunoId,
            ]
        );

        $total = 0;
        $emAndamento = 0;
        $concluidas = 0;

        foreach ($jornadas as $jornada) {
            $estrutura = [];
            if (!empty($jornada['estrutura'])) {
                $decoded = json_decode((string) $jornada['estrutura'], true);
                if (is_array($decoded)) {
                    $estrutura = $decoded;
                }
            }

            $turmaEhPermitida = ((int) $jornada['turma_id'] === $turmaId);
            if (!$turmaEhPermitida) {
                $turmasSelecionadas = isset($estrutura['turmas_selecionadas']) && is_array($estrutura['turmas_selecionadas'])
                    ? array_map('intval', $estrutura['turmas_selecionadas'])
                    : [];
                $turmaEhPermitida = in_array($turmaId, $turmasSelecionadas, true);
            }
            if (!$turmaEhPermitida) {
                continue;
            }

            $dataInicio = !empty($estrutura['data_inicio']) ? date('Y-m-d', strtotime((string) $estrutura['data_inicio'])) : null;
            $dataFim = !empty($estrutura['data_fim']) ? date('Y-m-d', strtotime((string) $estrutura['data_fim'])) : null;
            $referenciaInicio = $dataInicio ?: date('Y-m-d', strtotime((string) ($jornada['created_at'] ?? 'now')));
            $referenciaFim = $dataFim ?: $referenciaInicio;

            if ($referenciaFim < $periodoInicio || $referenciaInicio > $periodoFim) {
                continue;
            }

            $total++;
            $totalModulos = (int) ($jornada['total_modulos'] ?? 0);
            $modulosConcluidos = (int) ($jornada['modulos_concluidos'] ?? 0);
            $isConcluida = ($totalModulos > 0 && $modulosConcluidos >= $totalModulos);

            if ($isConcluida) {
                $concluidas++;
            } else {
                $emAndamento++;
            }
        }

        return [
            'inicio' => $periodoInicio,
            'fim' => $periodoFim,
            'total' => $total,
            'em_andamento' => $emAndamento,
            'concluidas' => $concluidas,
        ];
    }

    /**
     * Quantidade de jornadas abertas para o aluno no momento.
     */
    private function getJornadasAbertasCount(array $aluno): int
    {
        $turmaId = isset($aluno['turma_id']) ? (int) $aluno['turma_id'] : 0;
        $alunoId = isset($aluno['id']) ? (int) $aluno['id'] : 0;
        if ($turmaId <= 0 || $alunoId <= 0) {
            return 0;
        }

        $jornadas = $this->getActiveJourneys($turmaId, $alunoId);
        $agoraTs = time();
        $count = 0;
        foreach ($jornadas as $jornada) {
            if (!empty($jornada['jornada_concluida'])) {
                continue;
            }
            if (!empty($jornada['data_fim'])) {
                $horaFim = trim((string) ($jornada['hora_fim'] ?? '')) ?: '23:59:59';
                $tsFim = strtotime((string) $jornada['data_fim'] . ' ' . $horaFim);
                if ($tsFim !== false && $agoraTs > $tsFim) {
                    continue;
                }
            }
            $count++;
        }
        return $count;
    }

    /**
     * Quantidade de provas disponíveis para o aluno realizar agora.
     */
    private function getProvasDisponiveisAgoraCount(array $aluno, ?int $alunoId = null, ?int $turmaId = null): int
    {
        $alunoId = $alunoId ?? (int) ($aluno['id'] ?? 0);
        if ($alunoId <= 0) {
            return 0;
        }
        if ($turmaId === null) {
            $turmaId = (int) ($aluno['turma_id'] ?? 0);
        }

        try {
            require_once __DIR__ . '/../../Models/Exams/Exam.php';
            return (new Exam())->countAvailableForStudent($alunoId, $turmaId);
        } catch (Throwable $e) {
            error_log('getProvasDisponiveisAgoraCount: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Slug do tenant atual (constante ou config).
     */
    private function resolveTenantSlug(): string
    {
        if (defined('TENANT_SLUG')) {
            $slug = strtolower(trim((string) TENANT_SLUG));
            if ($slug !== '') {
                return $slug;
            }
        }

        $config = $this->config ?? [];
        $slug = strtolower(trim((string) ($config['tenant']['slug'] ?? $config['school']['code'] ?? '')));

        return $slug;
    }

    /**
     * Dashboard do aluno com cards de ação (sem métricas diárias).
     * Escola X da Questão e tenants com layout configurado.
     */
    private function isDashboardAcaoCardsLayout(): bool
    {
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        if (LayoutHelper::get('dashboard_aluno_layout', '') === 'acao_cards') {
            return true;
        }

        $slug = $this->resolveTenantSlug();

        return in_array($slug, ['xdq', 'xdaquestao', 'x-da-questao', 'colag'], true);
    }

    /**
     * Propostas de redação publicadas e ainda pendentes para o aluno.
     */
    private function getJornadaRedacaoPendentesCount(int $alunoId): int
    {
        if ($alunoId <= 0) {
            return 0;
        }

        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        if (LayoutHelper::get('module_redacao_configuravel', '1') !== '1') {
            return 0;
        }
        if (LayoutHelper::get('module_aluno_redacao_configuravel', '1') !== '1') {
            return 0;
        }

        require_once __DIR__ . '/../../Models/Essays/EssayProposal.php';
        return (new EssayProposal())->countPendingForStudent($alunoId);
    }

    /**
     * Recados do mural visíveis para o aluno (por turma ou todos); opcionalmente só os não vistos.
     */
    private function getMuralRecadosParaAluno($aluno_id, $turma_id, $apenas_nao_vistos = false)
    {
        return $this->getMuralRecadosParaAlunoComFiltros($aluno_id, $turma_id, [], $apenas_nao_vistos);
    }

    /**
     * Recados do mural para o aluno com filtros opcionais (professor_id, materia_id, data_de, data_ate).
     */
    private function getMuralRecadosParaAlunoComFiltros($aluno_id, $turma_id, array $filtros = [], $apenas_nao_vistos = false)
    {
        $turma_id = $turma_id !== null && $turma_id !== '' ? (int) $turma_id : 0;
        $params = [];
        $sql = "SELECT r.id, r.titulo, r.conteudo, r.data_publicacao, r.data_sai_mural, r.autor_tipo, r.autor_id,
                CASE WHEN r.autor_tipo = 'professor' THEN (SELECT p.nome FROM professores p WHERE p.id = r.autor_id LIMIT 1) ELSE 'Admin' END as autor_nome
                FROM mural_recados r
                WHERE (r.enviar_para_todos = 1" . ($turma_id > 0 ? " OR EXISTS (
                    SELECT 1 FROM mural_recados_turmas rt WHERE rt.mural_recado_id = r.id AND rt.turma_id = :aluno_turma_id
                  )" : "") . ")
                AND (CURDATE() <= r.data_sai_mural)";
        if ($turma_id > 0) {
            $params['aluno_turma_id'] = $turma_id;
        }
        if (!empty($filtros['professor_id'])) {
            $sql .= " AND r.autor_tipo = 'professor' AND r.autor_id = :filtro_professor_id";
            $params['filtro_professor_id'] = (int)$filtros['professor_id'];
        }
        if (!empty($filtros['materia_id'])) {
            $sql .= " AND r.materia_id = :filtro_materia_id";
            $params['filtro_materia_id'] = (int)$filtros['materia_id'];
        }
        if (!empty($filtros['data_de'])) {
            $sql .= " AND DATE(r.data_publicacao) >= :filtro_data_de";
            $params['filtro_data_de'] = $filtros['data_de'];
        }
        if (!empty($filtros['data_ate'])) {
            $sql .= " AND DATE(r.data_publicacao) <= :filtro_data_ate";
            $params['filtro_data_ate'] = $filtros['data_ate'];
        }
        if ($apenas_nao_vistos) {
            $sql .= " AND NOT EXISTS (SELECT 1 FROM mural_recados_vistos v WHERE v.mural_recado_id = r.id AND v.aluno_id = :aluno_id)";
            $params['aluno_id'] = $aluno_id;
        }
        $sql .= " ORDER BY r.data_publicacao DESC";
        return $this->db->fetchAll($sql, $params);
    }

    private function getRecentExercises($aluno_id)
    {
        return $this->db->fetchAll(
            "SELECT se.*, le.titulo as lista_titulo, le.materia
             FROM exercicios_sessoes se
             JOIN listas_exercicios le ON se.lista_id = le.id
             WHERE se.aluno_id = :aluno_id
             ORDER BY se.started_at DESC
             LIMIT 5",
            ['aluno_id' => $aluno_id]
        );
    }
    
    /**
     * Buscar matérias com mais erros do aluno (para exibir no dashboard)
     */
    private function getExerciciosComMaisErros($aluno_id)
    {
        return $this->db->fetchAll(
            "SELECT 
                materia,
                SUM(total_respostas) as total_respostas,
                SUM(total_erros) as total_erros,
                ROUND(SUM(total_erros) * 100.0 / NULLIF(SUM(total_respostas), 0), 0) as percentual_erro
             FROM (
                SELECT 
                    le.materia as materia,
                    COUNT(re.id) as total_respostas,
                    SUM(CASE WHEN re.is_correct = 0 THEN 1 ELSE 0 END) as total_erros
                 FROM exercicios_respostas re
                 JOIN questoes q ON re.exercicio_id = q.id
                 JOIN listas_exercicios le ON q.lista_id = le.id
                 WHERE re.aluno_id = ?
                 GROUP BY le.materia
                UNION ALL
                SELECT 
                    lep.materia as materia,
                    COUNT(rep.id) as total_respostas,
                    SUM(CASE WHEN rep.is_correct = 0 THEN 1 ELSE 0 END) as total_erros
                 FROM listas_personalizadas_respostas rep
                 JOIN listas_personalizadas_sessoes sep ON rep.sessao_id = sep.id
                 JOIN questoes_personalizadas qp ON rep.questao_id = qp.id
                 JOIN listas_personalizadas_exercicios lep ON qp.lista_id = lep.id
                 WHERE sep.aluno_id = ?
                 GROUP BY lep.materia
             ) as resumo
             WHERE materia IS NOT NULL AND materia != ''
             GROUP BY materia
             HAVING SUM(total_erros) > 0 AND SUM(total_respostas) >= 3
             ORDER BY percentual_erro DESC, total_erros DESC
             LIMIT 5",
            [$aluno_id, $aluno_id]
        );
    }

    /**
     * Buscar matérias de provas com mais erros do aluno (para exibir no dashboard)
     */
    private function getMateriasProvasOnlineComMaisErros($aluno_id)
    {
        try {
            $realizacoes = $this->getProvasRealizadasAlunoComBloco((int) $aluno_id);
        } catch (\Throwable $e) {
            $realizacoes = [];
        }

        $porMateria = [];
        foreach ($realizacoes as $row) {
            $materia = trim((string) ($row['prova_materia'] ?? ''));
            if ($materia === '') {
                $materia = 'Sem matéria';
            }

            $total = (int) ($row['prova_total_questoes'] ?? 0);
            $acertos = (int) ($row['prova_acertos'] ?? 0);
            if ($total <= 0) {
                continue;
            }

            $erros = max(0, $total - $acertos);

            if (!isset($porMateria[$materia])) {
                $porMateria[$materia] = [
                    'materia' => $materia,
                    'total_respostas' => 0,
                    'total_erros' => 0,
                    'percentual_erro' => 0,
                ];
            }

            $porMateria[$materia]['total_respostas'] += $total;
            $porMateria[$materia]['total_erros'] += $erros;
        }

        $out = [];
        foreach ($porMateria as $dados) {
            if ((int) $dados['total_erros'] <= 0) {
                continue;
            }
            $dados['percentual_erro'] = (int) round(((int) $dados['total_erros'] * 100) / max(1, (int) $dados['total_respostas']));
            $out[] = $dados;
        }

        usort($out, function ($a, $b) {
            if ((int) $a['percentual_erro'] !== (int) $b['percentual_erro']) {
                return (int) $b['percentual_erro'] <=> (int) $a['percentual_erro'];
            }
            if ((int) $a['total_erros'] !== (int) $b['total_erros']) {
                return (int) $b['total_erros'] <=> (int) $a['total_erros'];
            }
            return (int) $b['total_respostas'] <=> (int) $a['total_respostas'];
        });

        return array_slice($out, 0, 5);
    }
    
    private function getLimitesDiarios($aluno_id)
    {
        require_once __DIR__ . '/../../Core/LayoutHelper.php';

        $limite_chat = LayoutHelper::getConfigValue('limit_chat_interacoes', '10');
        $limite_tema = LayoutHelper::getConfigValue('limit_gerar_tema_redacao', '3');
        $limite_correcao = LayoutHelper::getConfigValue('limit_corrigir_redacao', '5');
        $limite_exercicios = LayoutHelper::getConfigValue('limit_exercicios', '10');
        $limite_simulados = LayoutHelper::getConfigValue('limit_simulados', '2');

        $usoPorAcao = $this->contarUsoDiarioAgrupado((int) $aluno_id);

        return [
            'chat' => ['usado' => (int) ($usoPorAcao['chat_interacoes'] ?? 0), 'limite' => $limite_chat],
            'tema' => ['usado' => (int) ($usoPorAcao['gerar_tema_redacao'] ?? 0), 'limite' => $limite_tema],
            'correcao' => ['usado' => (int) ($usoPorAcao['corrigir_redacao'] ?? 0), 'limite' => $limite_correcao],
            'exercicios' => ['usado' => (int) ($usoPorAcao['exercicios'] ?? 0), 'limite' => $limite_exercicios],
            'simulados' => ['usado' => (int) ($usoPorAcao['simulados'] ?? 0), 'limite' => $limite_simulados],
        ];
    }

    /**
     * Uso diário agrupado (uma query) — usado no dashboard.
     *
     * @return array<string, int>
     */
    private function contarUsoDiarioAgrupado(int $aluno_id): array
    {
        $inicio = date('Y-m-d') . ' 00:00:00';
        $fim = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
        $rows = $this->db->fetchAll(
            "SELECT acao, COUNT(*) AS total
             FROM alunos_acoes_diarias
             WHERE aluno_id = :aluno_id
               AND created_at >= :inicio
               AND created_at < :fim
             GROUP BY acao",
            ['aluno_id' => $aluno_id, 'inicio' => $inicio, 'fim' => $fim]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(string) ($row['acao'] ?? '')] = (int) ($row['total'] ?? 0);
        }
        return $out;
    }

    private function getRecentEssays($aluno_id)
    {
        return $this->db->fetchAll(
            "SELECT r.*, t.titulo as tema_titulo
             FROM redacoes r
             LEFT JOIN redacoes_temas t ON r.tema_id = t.id
             WHERE r.aluno_id = :aluno_id
             ORDER BY r.created_at DESC
             LIMIT 5",
            ['aluno_id' => $aluno_id]
        );
    }

    private function getStudentConversations($aluno_id)
    {
        return $this->db->fetchAll(
            "SELECT cc.*, 
                (SELECT COUNT(*) FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id) as total_mensagens,
                (SELECT COUNT(*) FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id AND mc.is_ia = 0) as total_perguntas,
                (SELECT COUNT(*) FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id AND mc.is_ia = 1) as total_respostas,
                LEAST(
                    (SELECT COUNT(*) FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id AND mc.is_ia = 0),
                    (SELECT COUNT(*) FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id AND mc.is_ia = 1)
                ) as interacoes,
                (SELECT mc.mensagem FROM tudinha_mensagens mc WHERE mc.conversa_id = cc.id ORDER BY mc.created_at DESC LIMIT 1) as ultima_mensagem
             FROM tudinha_conversas cc
             WHERE cc.aluno_id = :aluno_id AND cc.excluida = 0
             ORDER BY cc.updated_at DESC",
            ['aluno_id' => $aluno_id]
        );
    }

    private function getAvailableExerciseLists($turma_id)
    {
        return $this->db->fetchAll(
            "SELECT le.*, COUNT(e.id) as total_exercicios
             FROM listas_exercicios le
             LEFT JOIN exercicios e ON le.id = e.lista_id
             WHERE le.ativo = 1
             GROUP BY le.id
             ORDER BY le.created_at DESC"
        );
    }

    private function getCompletedExercises($aluno_id)
    {
        return $this->db->fetchAll(
            "SELECT se.*, le.titulo as lista_titulo, le.materia,
                COUNT(re.id) as total_respostas,
                SUM(CASE WHEN re.is_correct = 1 THEN 1 ELSE 0 END) as acertos
             FROM exercicios_sessoes se
             JOIN listas_exercicios le ON se.lista_id = le.id
             LEFT JOIN exercicios_respostas re ON se.id = re.sessao_id
             WHERE se.aluno_id = :aluno_id
             GROUP BY se.id
             ORDER BY se.started_at DESC",
            ['aluno_id' => $aluno_id]
        );
    }

    private function getAvailableEssayThemes()
    {
        try {
            // Verificar se a tabela existe
            $tableExists = $this->db->fetch("SHOW TABLES LIKE 'redacoes_temas'");
            if (!$tableExists) {
                error_log("Tabela redacoes_temas não existe");
                return [];
            }
            
            // Verificar se a coluna ativo existe
            $columns = $this->db->fetchAll("SHOW COLUMNS FROM redacoes_temas");
            $columnNames = array_column($columns, 'Field');
            $hasAtivo = in_array('ativo', $columnNames);
            
            if ($hasAtivo) {
                return $this->db->fetchAll(
                    "SELECT * FROM redacoes_temas WHERE ativo = 1 ORDER BY created_at DESC"
                );
            } else {
                // Se não tem coluna ativo, retorna todos
                return $this->db->fetchAll(
                    "SELECT * FROM redacoes_temas ORDER BY created_at DESC"
                );
            }
        } catch (Exception $e) {
            error_log("Erro em getAvailableEssayThemes: " . $e->getMessage());
            return [];
        }
    }

    private function getStudentEssays($aluno_id)
    {
        try {
            // Verificar se as colunas existem antes de usar
            $columns = $this->db->fetchAll("SHOW COLUMNS FROM redacoes");
            $columnNames = array_column($columns, 'Field');
            
            $hasOculto = in_array('oculto', $columnNames);
            $hasEhRascunho = in_array('eh_rascunho', $columnNames);
            
            $sql = "SELECT r.*, t.titulo as tema_titulo, t.tipo
                    FROM redacoes r
                    LEFT JOIN redacoes_temas t ON r.tema_id = t.id
                    WHERE r.aluno_id = :aluno_id";
            
            if ($hasOculto) {
                $sql .= " AND (r.oculto IS NULL OR r.oculto = 0)";
            }
            
            if ($hasEhRascunho) {
                $sql .= " AND (r.eh_rascunho IS NULL OR r.eh_rascunho = 0)";
            }
            
            $sql .= " ORDER BY r.created_at DESC LIMIT 50";
            
            return $this->db->fetchAll($sql, ['aluno_id' => $aluno_id]);
        } catch (Exception $e) {
            error_log("Erro em getStudentEssays: " . $e->getMessage());
            // Tenta query simplificada sem filtros de colunas opcionais
            try {
                return $this->db->fetchAll(
                    "SELECT r.*, t.titulo as tema_titulo, t.tipo
                     FROM redacoes r
                     LEFT JOIN redacoes_temas t ON r.tema_id = t.id
                     WHERE r.aluno_id = :aluno_id 
                     ORDER BY r.created_at DESC
                     LIMIT 50",
                    ['aluno_id' => $aluno_id]
                );
            } catch (Exception $e2) {
                error_log("Erro em getStudentEssays (fallback): " . $e2->getMessage());
                return [];
            }
        }
    }

    private function getRascunhos($aluno_id)
    {
        try {
            // Verificar se a coluna existe primeiro
            $columnCheck = $this->db->fetch("SHOW COLUMNS FROM redacoes LIKE 'eh_rascunho'");
            if (!$columnCheck) {
                error_log("Coluna eh_rascunho não existe na tabela redacoes");
                return [];
            }
            
            $rascunhos = $this->db->fetchAll(
                "SELECT r.*, t.titulo as tema_titulo, t.tipo
                 FROM redacoes r
                 LEFT JOIN redacoes_temas t ON r.tema_id = t.id
                 WHERE r.aluno_id = :aluno_id 
                 AND r.eh_rascunho = 1
                 ORDER BY COALESCE(r.updated_at, r.created_at) DESC",
                ['aluno_id' => $aluno_id]
            );
            
            return $rascunhos;
        } catch (Exception $e) {
            error_log("Erro ao buscar rascunhos: " . $e->getMessage());
            // Se der erro de coluna não encontrada, retorna array vazio
            if (strpos($e->getMessage(), "Unknown column 'eh_rascunho'") !== false) {
                error_log("Coluna eh_rascunho não existe. Execute: php install/add_rascunho_redacao.php");
                return [];
            }
            // Se der outro erro, tenta sem updated_at
            try {
                return $this->db->fetchAll(
                    "SELECT r.*, t.titulo as tema_titulo, t.tipo
                     FROM redacoes r
                     LEFT JOIN redacoes_temas t ON r.tema_id = t.id
                     WHERE r.aluno_id = :aluno_id 
                     AND r.eh_rascunho = 1
                     ORDER BY r.created_at DESC",
                    ['aluno_id' => $aluno_id]
                );
            } catch (Exception $e2) {
                error_log("Erro ao buscar rascunhos (fallback): " . $e2->getMessage());
                return [];
            }
        }
    }

    private function generateAIResponse($mensagem, $materia = '')
    {
        // Simulação de resposta da IA
        $respostas_base = [
            "Entendi sua dúvida! Vou te ajudar com isso.",
            "Ótima pergunta! Deixe-me explicar de forma clara.",
            "Interessante! Vamos analisar isso juntos.",
            "Perfeito! Essa é uma questão importante.",
            "Excelente! Vou te dar uma explicação detalhada."
        ];

        $resposta = $respostas_base[array_rand($respostas_base)];
        
        if (!empty($materia)) {
            $resposta .= " Em relação à matéria de " . $materia . ", posso te ajudar com mais detalhes se precisar.";
        }

        return $resposta;
    }

    private function processEssayCorrection($redacao_id)
    {
        try {
            // Buscar redação
            $redacao = $this->db->fetch(
                "SELECT * FROM redacoes WHERE id = :redacao_id",
                ['redacao_id' => $redacao_id]
            );

            if (!$redacao) {
                throw new Exception('Redação não encontrada');
            }

            // Usar OpenAI para corrigir com critérios ENEM
            // Incluir OpenAIService se não estiver carregado
            if (!class_exists('App\Services\OpenAIService')) {
                require_once __DIR__ . '/../../Services/OpenAIService.php';
            }
            
            $openaiService = new \App\Services\OpenAIService();
            
            // Buscar prompt personalizado do banco de dados
            $promptConfig = $this->db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                ['prompt_corrigir_redacao']
            );
            
            // Se o prompt não existir, usar o padrão
            if (!$promptConfig || empty($promptConfig['config_value'])) {
                $promptBase = "Você é um corretor especializado em redações do ENEM. 

            Corrija a redação abaixo nos critérios e parâmetros oficiais do ENEM:

            COMPETÊNCIA I – Domínio da norma padrão da Língua Portuguesa
            COMPETÊNCIA II – Compreensão da proposta e desenvolvimento do tema  
            COMPETÊNCIA III – Seleção e organização de argumentos
            COMPETÊNCIA IV – Coesão e coerência
            COMPETÊNCIA V – Proposta de intervenção

            REDAÇÃO:
            Título: {$redacao['titulo']}
            Conteúdo: {$redacao['conteudo']}

            Responda em formato JSON com as seguintes chaves:
            - competencia_1: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - competencia_2: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - competencia_3: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - competencia_4: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - competencia_5: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - nota_final: soma das 5 competências
            - comentarios_gerais: \"comentários gerais sobre a redação\"
            - sugestoes_melhoria: \"sugestões específicas para melhoria\"

            Seja detalhado e construtivo nas explicações.";
            } else {
                // Usar prompt personalizado e substituir variáveis
                $promptBase = $promptConfig['config_value'];
                $promptBase = str_replace('{titulo}', $redacao['titulo'], $promptBase);
                $promptBase = str_replace('{conteudo}', $redacao['conteudo'], $promptBase);
            }

            $response = $openaiService->generateText($promptBase, [
                'temperature' => 0.4
            ]);
            
            // Tentar decodificar JSON
            $correcaoData = json_decode($response, true);
            
            if (!$correcaoData) {
                // Fallback para correção simulada se a IA falhar
                $correcaoData = [
                    'competencia_1' => ['nota' => rand(120, 200), 'explicacao' => 'Bom domínio da norma padrão'],
                    'competencia_2' => ['nota' => rand(120, 200), 'explicacao' => 'Adequada compreensão do tema'],
                    'competencia_3' => ['nota' => rand(120, 200), 'explicacao' => 'Argumentos bem selecionados'],
                    'competencia_4' => ['nota' => rand(120, 200), 'explicacao' => 'Boa coesão e coerência'],
                    'competencia_5' => ['nota' => rand(120, 200), 'explicacao' => 'Proposta de intervenção adequada'],
                    'nota_final' => rand(600, 1000),
                    'comentarios_gerais' => 'Sua redação apresenta boa estrutura e argumentação. Continue praticando!',
                    'sugestoes_melhoria' => 'Continue desenvolvendo suas ideias e praticando a escrita.'
                ];
            }

            // Salvar correção no banco
        $this->db->update(
            "UPDATE redacoes SET 
                    competencia_1_nota = :comp1_nota,
                    competencia_1_explicacao = :comp1_exp,
                    competencia_2_nota = :comp2_nota,
                    competencia_2_explicacao = :comp2_exp,
                    competencia_3_nota = :comp3_nota,
                    competencia_3_explicacao = :comp3_exp,
                    competencia_4_nota = :comp4_nota,
                    competencia_4_explicacao = :comp4_exp,
                    competencia_5_nota = :comp5_nota,
                    competencia_5_explicacao = :comp5_exp,
                    nota_final = :nota_final,
                    comentarios_gerais = :comentarios,
                    sugestoes_melhoria = :sugestoes,
                corrigida_em = NOW()
             WHERE id = :redacao_id",
            [
                    'comp1_nota' => $correcaoData['competencia_1']['nota'] ?? 0,
                    'comp1_exp' => $correcaoData['competencia_1']['explicacao'] ?? '',
                    'comp2_nota' => $correcaoData['competencia_2']['nota'] ?? 0,
                    'comp2_exp' => $correcaoData['competencia_2']['explicacao'] ?? '',
                    'comp3_nota' => $correcaoData['competencia_3']['nota'] ?? 0,
                    'comp3_exp' => $correcaoData['competencia_3']['explicacao'] ?? '',
                    'comp4_nota' => $correcaoData['competencia_4']['nota'] ?? 0,
                    'comp4_exp' => $correcaoData['competencia_4']['explicacao'] ?? '',
                    'comp5_nota' => $correcaoData['competencia_5']['nota'] ?? 0,
                    'comp5_exp' => $correcaoData['competencia_5']['explicacao'] ?? '',
                    'nota_final' => $correcaoData['nota_final'] ?? 0,
                    'comentarios' => $correcaoData['comentarios_gerais'] ?? '',
                    'sugestoes' => $correcaoData['sugestoes_melhoria'] ?? '',
                'redacao_id' => $redacao_id
            ]
        );

        } catch (Exception $e) {
            // Log do erro mas não falha a criação da redação
            error_log("Erro na correção automática da redação {$redacao_id}: " . $e->getMessage());
        }
    }

    private function monitorarChatMensagem($aluno, $conversaId, $mensagem, $origem, $mensagemChatId = null)
    {
        try {
            $texto = trim(strip_tags((string)$mensagem));
            if ($texto === '') {
                return;
            }

            $contextoRecente = [];
            if ($origem === 'aluno') {
                $recentes = $this->db->fetchAll(
                    "SELECT mensagem FROM tudinha_mensagens
                     WHERE conversa_id = :conversa_id AND is_ia = 0
                     ORDER BY created_at DESC
                     LIMIT 5",
                    ['conversa_id' => $conversaId]
                );
                foreach ($recentes as $msg) {
                    $contextoRecente[] = trim(strip_tags($msg['mensagem'] ?? ''));
                }
            }

            require_once __DIR__ . '/../../Services/ChatSafetyMonitorService.php';
            $monitor = new \App\Services\ChatSafetyMonitorService();
            $monitor->analisarMensagem(
                $aluno['id'],
                $aluno['turma_id'] ?? null,
                $texto,
                $origem,
                $contextoRecente,
                $mensagemChatId
            );
        } catch (Exception $e) {
            error_log("Falha no monitoramento do chat: " . $e->getMessage());
        }
    }

    public function escreverRedacao()
    {
        $user = $this->authManager->getUser();
        
        // Obter tema da sessão (gerado pelo gerarTemaIA)
        $tema = $_SESSION['tema_gerado'] ?? null;
        
        // Limpar tema da sessão após usar
        if ($tema) {
            unset($_SESSION['tema_gerado']);
        }

        $this->viewWithLayout('student', 'student/escrever-redacao', [
            'title' => 'Escrever Redação - EducaTudo',
            'user' => $user,
            'tema' => $tema
        ]);
    }

    public function historicoRedacoes()
    {
        $user = $this->authManager->getUser();
        
        if (!$user || $user['tipo'] !== 'aluno') {
            $this->redirect(URL . '/');
            return;
        }

        $redacoes = $this->db->fetchAll(
            "SELECT r.*, 
                    CASE 
                        WHEN r.corrigida_em IS NOT NULL THEN 'Corrigida'
                        ELSE 'Pendente'
                    END as status
             FROM redacoes r 
             WHERE r.aluno_id = :aluno_id 
             ORDER BY r.created_at DESC",
            ['aluno_id' => $user['id']]
        );

        $this->viewWithLayout('student', 'student/historico-redacoes', [
            'title' => 'Histórico de Redações - EducaTudo',
            'user' => $user,
            'redacoes' => $redacoes
        ]);
    }

    public function verRedacao($id)
    {
        $user = $this->authManager->getUser();
        
        if (!$user || $user['tipo'] !== 'aluno') {
            $this->redirect(URL . '/');
            return;
        }

        $redacao = $this->db->fetch(
            "SELECT r.*, a.nome as aluno_nome
             FROM redacoes r 
             LEFT JOIN alunos a ON r.aluno_id = a.id 
             WHERE r.id = :id AND r.aluno_id = :aluno_id",
            ['id' => $id, 'aluno_id' => $user['id']]
        );

        if (!$redacao) {
            $_SESSION['error_message'] = 'Redação não encontrada';
            $this->redirect('/redacoes/historico');
            return;
        }

        // Decodificar feedback da IA se existir
        $feedback = null;
        if ($redacao['feedback_ia']) {
            $feedback = json_decode($redacao['feedback_ia'], true);
        }

        $this->viewWithLayout('student', 'student/ver-redacao', [
            'title' => 'Redação - EducaTudo',
            'user' => $user,
            'redacao' => $redacao,
            'feedback' => $feedback
        ]);
    }

    /**
     * Página para escrever redação livre
     */
    public function escreverRedacaoLivre()
    {
        $user = $this->authManager->getUser();
        
        // Verificar se há um rascunho_id na query string
        $rascunho_id = $_GET['rascunho_id'] ?? null;
        $rascunho = null;
        
        if ($rascunho_id) {
            $rascunho = $this->db->fetch(
                "SELECT r.* FROM redacoes r 
                 WHERE r.id = :id AND r.aluno_id = :aluno_id AND r.eh_rascunho = 1",
                ['id' => $rascunho_id, 'aluno_id' => $user['id']]
            );
        }
        
        $this->viewWithLayout('student', 'student/escrever-redacao-livre', [
            'title' => 'Redação Livre - EducaTudo',
            'user' => $user,
            'rascunho' => $rascunho
        ]);
    }

    /**
     * Página para transcrição de imagem
     */
    public function transcreverImagemPage()
    {
        $user = $this->authManager->getUser();
        
        // Gerar token CSRF
        $this->generateCsrfToken();
        
        $this->viewWithLayout('student', 'student/transcrever-imagem', [
            'title' => 'Transcrição de Imagem - EducaTudo',
            'user' => $user
        ]);
    }

    // ==============================================
    // MÉTODOS DE VERIFICAÇÃO DE LIMITES DIÁRIOS
    // ==============================================

    /**
     * Obter limite de uma ação específica
     */
    private function getLimiteDiario($tipo)
    {
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $key = 'limit_' . $tipo;
        $limite = LayoutHelper::get($key, '0');
        return intval($limite);
    }

    /**
     * Contar uso diário de uma ação específica
     */
    private function contarUsoDiario($aluno_id, $tipo)
    {
        $hoje = date('Y-m-d');
        
        switch ($tipo) {
            case 'chat_interacoes':
                // Contar interações do chat hoje
                return $this->db->fetch(
                    "SELECT COUNT(*) as total
                     FROM alunos_acoes_diarias 
                     WHERE aluno_id = :aluno_id 
                     AND acao = 'chat_interacoes'
                     AND DATE(created_at) = :hoje",
                    ['aluno_id' => $aluno_id, 'hoje' => $hoje]
                )['total'] ?? 0;

            case 'gerar_tema_redacao':
                // Contar vezes que o aluno solicitou geração de tema hoje
                return $this->db->fetch(
                    "SELECT COUNT(*) as total
                     FROM alunos_acoes_diarias 
                     WHERE aluno_id = :aluno_id 
                     AND acao = 'gerar_tema_redacao'
                     AND DATE(created_at) = :hoje",
                    ['aluno_id' => $aluno_id, 'hoje' => $hoje]
                )['total'] ?? 0;

            case 'corrigir_redacao':
                // Contar redações corrigidas pela IA hoje
                return $this->db->fetch(
                    "SELECT COUNT(*) as total
                     FROM alunos_acoes_diarias 
                     WHERE aluno_id = :aluno_id 
                     AND acao = 'corrigir_redacao'
                     AND DATE(created_at) = :hoje",
                    ['aluno_id' => $aluno_id, 'hoje' => $hoje]
                )['total'] ?? 0;

            case 'exercicios':
                // Contar exercícios iniciados hoje
                return $this->db->fetch(
                    "SELECT COUNT(*) as total
                     FROM alunos_acoes_diarias 
                     WHERE aluno_id = :aluno_id 
                     AND acao = 'exercicios'
                     AND DATE(created_at) = :hoje",
                    ['aluno_id' => $aluno_id, 'hoje' => $hoje]
                )['total'] ?? 0;

            case 'simulados':
                // Contar simulados iniciados hoje
                return $this->db->fetch(
                    "SELECT COUNT(*) as total
                     FROM alunos_acoes_diarias 
                     WHERE aluno_id = :aluno_id 
                     AND acao = 'simulados'
                     AND DATE(created_at) = :hoje",
                    ['aluno_id' => $aluno_id, 'hoje' => $hoje]
                )['total'] ?? 0;

            default:
                return 0;
        }
    }

    /**
     * Verificar se pode executar uma ação (respeitando limites diários)
     */
    private function podeExecutarAcao($aluno_id, $tipo)
    {
        $limite = $this->getLimiteDiario($tipo);
        
        // Se limite é 0, não há limite (pode executar)
        if ($limite === 0) {
            return ['pode' => true];
        }
        
        $uso = $this->contarUsoDiario($aluno_id, $tipo);
        
        if ($uso >= $limite) {
            $mensagens = [
                'chat_interacoes' => "Você atingiu o limite diário de $limite interações de chat. Tente novamente amanhã.",
                'gerar_tema_redacao' => "Você atingiu o limite diário de $limite gerações de tema de redação. Tente novamente amanhã.",
                'corrigir_redacao' => "Você atingiu o limite diário de $limite correções de redação. Tente novamente amanhã.",
                'exercicios' => "Você atingiu o limite diário de $limite exercícios. Tente novamente amanhã.",
                'simulados' => "Você atingiu o limite diário de $limite simulados. Tente novamente amanhã.",
            ];
            
            return [
                'pode' => false, 
                'mensagem' => $mensagens[$tipo] ?? "Você atingiu o limite diário de $limite desta ação."
            ];
        }
        
        return ['pode' => true, 'restantes' => $limite - $uso];
    }
    
    /**
     * Oculta uma redação (não exclui, apenas oculta da listagem)
     */
    /**
     * Salvar rascunho de redação
     */
    public function salvarRascunho()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $rascunho_id = $_POST['rascunho_id'] ?? null;
            $titulo = $_POST['titulo'] ?? 'Rascunho sem título';
            $conteudo = $_POST['conteudo'] ?? '';
            $tema_texto = $_POST['tema_texto'] ?? null;
            $tempo_escrita = isset($_POST['tempo_escrita']) ? intval($_POST['tempo_escrita']) : 0;

            if (empty($conteudo)) {
                throw new Exception('Conteúdo não pode estar vazio');
            }

            // Verificar se a coluna eh_rascunho existe
            try {
                $columnCheck = $this->db->fetch("SHOW COLUMNS FROM redacoes LIKE 'eh_rascunho'");
                if (!$columnCheck) {
                    throw new Exception('Coluna eh_rascunho não existe. Execute o script: php install/add_rascunho_redacao.php');
                }
            } catch (Exception $e) {
                error_log("Verificação de coluna: " . $e->getMessage());
                // Se não conseguir verificar, tenta mesmo assim
            }

            // Se tem ID, atualizar rascunho existente
            if ($rascunho_id) {
                error_log("Atualizando rascunho ID: $rascunho_id, Aluno ID: {$aluno['id']}");
                
                // Verificar se a coluna tempo_escrita existe
                $tempoColumnExists = false;
                try {
                    $columnCheck = $this->db->fetch("SHOW COLUMNS FROM redacoes LIKE 'tempo_escrita'");
                    $tempoColumnExists = !empty($columnCheck);
                } catch (Exception $e) {
                    // Coluna não existe, não atualizar tempo
                }
                
                if ($tempoColumnExists) {
                    // Atualizar com o tempo total recebido (já inclui tempo acumulado do frontend)
                    $this->db->update(
                        "UPDATE redacoes 
                         SET titulo = :titulo, conteudo = :conteudo, tema_texto = :tema_texto, tempo_escrita = :tempo_escrita, updated_at = NOW()
                         WHERE id = :id AND aluno_id = :aluno_id AND eh_rascunho = 1",
                        [
                            'id' => $rascunho_id,
                            'aluno_id' => $aluno['id'],
                            'titulo' => $titulo,
                            'conteudo' => $conteudo,
                            'tema_texto' => $tema_texto,
                            'tempo_escrita' => $tempo_escrita
                        ]
                    );
                } else {
                    $this->db->update(
                        "UPDATE redacoes 
                         SET titulo = :titulo, conteudo = :conteudo, tema_texto = :tema_texto, updated_at = NOW()
                         WHERE id = :id AND aluno_id = :aluno_id AND eh_rascunho = 1",
                        [
                            'id' => $rascunho_id,
                            'aluno_id' => $aluno['id'],
                            'titulo' => $titulo,
                            'conteudo' => $conteudo,
                            'tema_texto' => $tema_texto
                        ]
                    );
                }
                
                $this->json(['success' => true, 'rascunho_id' => $rascunho_id, 'message' => 'Rascunho atualizado com sucesso!']);
            } else {
                // Criar novo rascunho
                error_log("Criando novo rascunho para Aluno ID: {$aluno['id']}");
                
                // Verificar se a coluna tempo_escrita existe
                $tempoColumnExists = false;
                try {
                    $columnCheck = $this->db->fetch("SHOW COLUMNS FROM redacoes LIKE 'tempo_escrita'");
                    $tempoColumnExists = !empty($columnCheck);
                } catch (Exception $e) {
                    // Coluna não existe, não salvar tempo
                }
                
                if ($tempoColumnExists) {
                    $rascunho_id = $this->db->insert(
                        "INSERT INTO redacoes (aluno_id, titulo, conteudo, tema_texto, tempo_escrita, eh_rascunho, created_at, updated_at) 
                         VALUES (:aluno_id, :titulo, :conteudo, :tema_texto, :tempo_escrita, 1, NOW(), NOW())",
                        [
                            'aluno_id' => $aluno['id'],
                            'titulo' => $titulo,
                            'conteudo' => $conteudo,
                            'tema_texto' => $tema_texto,
                            'tempo_escrita' => $tempo_escrita
                        ]
                    );
                } else {
                    $rascunho_id = $this->db->insert(
                        "INSERT INTO redacoes (aluno_id, titulo, conteudo, tema_texto, eh_rascunho, created_at, updated_at) 
                         VALUES (:aluno_id, :titulo, :conteudo, :tema_texto, 1, NOW(), NOW())",
                        [
                            'aluno_id' => $aluno['id'],
                            'titulo' => $titulo,
                            'conteudo' => $conteudo,
                            'tema_texto' => $tema_texto
                        ]
                    );
                }
                
                error_log("Rascunho criado com ID: $rascunho_id");
                $this->json(['success' => true, 'rascunho_id' => $rascunho_id, 'message' => 'Rascunho salvo com sucesso!']);
            }

        } catch (Exception $e) {
            error_log("Erro ao salvar rascunho: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Continuar rascunho - carregar dados do rascunho para edição
     */
    public function continuarRascunho($id)
    {
        try {
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'aluno') {
                $this->redirect('/redacoes');
                return;
            }

            // Buscar rascunho
            $rascunho = $this->db->fetch(
                "SELECT r.* FROM redacoes r 
                 WHERE r.id = :id AND r.aluno_id = :aluno_id AND r.eh_rascunho = 1",
                ['id' => $id, 'aluno_id' => $user['id']]
            );

            if (!$rascunho) {
                $_SESSION['error_message'] = 'Rascunho não encontrado';
                $this->redirect('/redacoes');
                return;
            }

            // Decodificar tema se for JSON
            $tema = null;
            $isRedacaoLivre = false;
            
            if ($rascunho['tema_texto']) {
                $tema_decodificado = json_decode($rascunho['tema_texto'], true);
                if ($tema_decodificado && is_array($tema_decodificado)) {
                    // É JSON válido - redação normal com tema gerado
                    $tema = $tema_decodificado;
                } else {
                    // É texto simples - redação livre
                    $isRedacaoLivre = true;
                    $tema = ['titulo' => $rascunho['tema_texto']];
                }
            } else if (!$rascunho['tema_id']) {
                // Sem tema_id nem tema_texto - provavelmente redação livre
                $isRedacaoLivre = true;
            }

            // Se for redação livre, redirecionar para a página correta
            if ($isRedacaoLivre) {
                $this->redirect('/redacoes/escrever-livre?rascunho_id=' . $id);
                return;
            }

            // Redação normal com tema
            $this->viewWithLayout('student', 'student/escrever-redacao', [
                'title' => 'Continuar Redação - EducaTudo',
                'user' => $user,
                'tema' => $tema,
                'rascunho' => $rascunho
            ]);

        } catch (Exception $e) {
            error_log("Erro ao continuar rascunho: " . $e->getMessage());
            $_SESSION['error_message'] = 'Erro ao carregar rascunho';
            $this->redirect('/redacoes');
        }
    }

    public function excluirRascunho($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        try {
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'aluno') {
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }
            
            // Verificar se a coluna eh_rascunho existe
            $hasEhRascunho = false;
            try {
                $columnCheck = $this->db->fetch("SHOW COLUMNS FROM redacoes LIKE 'eh_rascunho'");
                $hasEhRascunho = !empty($columnCheck);
            } catch (Exception $e) {
                error_log("Erro ao verificar coluna eh_rascunho: " . $e->getMessage());
            }
            
            // Verificar se o rascunho pertence ao aluno
            if ($hasEhRascunho) {
                $rascunho = $this->db->fetch(
                    "SELECT id FROM redacoes WHERE id = :id AND aluno_id = :aluno_id AND eh_rascunho = 1",
                    ['id' => $id, 'aluno_id' => $user['id']]
                );
            } else {
                // Se não tem coluna, verificar apenas se pertence ao aluno
                $rascunho = $this->db->fetch(
                    "SELECT id FROM redacoes WHERE id = :id AND aluno_id = :aluno_id",
                    ['id' => $id, 'aluno_id' => $user['id']]
                );
            }
            
            if (!$rascunho) {
                $this->json(['error' => 'Rascunho não encontrado'], 404);
                return;
            }
            
            // Excluir do banco de dados
            if ($hasEhRascunho) {
                $this->db->delete(
                    "DELETE FROM redacoes WHERE id = :id AND aluno_id = :aluno_id AND eh_rascunho = 1",
                    ['id' => $id, 'aluno_id' => $user['id']]
                );
            } else {
                $this->db->delete(
                    "DELETE FROM redacoes WHERE id = :id AND aluno_id = :aluno_id",
                    ['id' => $id, 'aluno_id' => $user['id']]
                );
            }
            
            $this->json(['success' => true, 'message' => 'Rascunho excluído com sucesso']);
            
        } catch (Exception $e) {
            error_log("Erro ao excluir rascunho: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function ocultarRedacao($id)
    {
        try {
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'aluno') {
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }
            
            // Verificar se a redação pertence ao aluno
            $redacao = $this->db->fetch(
                "SELECT id FROM redacoes WHERE id = :id AND aluno_id = :aluno_id",
                ['id' => $id, 'aluno_id' => $user['id']]
            );
            
                if (!$redacao) {
                    $_SESSION['error_message'] = 'Redação não encontrada';
                    $this->redirect('/jornadas/' . $redacaoJornada['jornada_id']);
                    return;
                }
            
            // Marcar como oculta
            $this->db->update(
                "UPDATE redacoes SET oculto = 1 WHERE id = :id",
                ['id' => $id]
            );
            
            $this->json(['success' => true, 'message' => 'Redação oculta com sucesso']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * ============================================
     * REDAÇÕES DA JORNADA (ALUNO)
     * ============================================
     */
    
    /**
     * Salvar rascunho de redação da jornada
     */
    public function salvarRedacaoJornada()
    {
        try {
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'aluno') {
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/../../Core/Logger.php';
                }
                Logger::error('jornada_aluno salvarRedacaoJornada: acesso negado', [
                    'user_id' => $user['id'] ?? null,
                    'user_tipo' => $user['tipo'] ?? null,
                ], 'jornadas');
                $this->json(['success' => false, 'error' => 'Acesso negado. Você precisa estar logado como aluno.'], 403);
                return;
            }
            
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/../../Core/Logger.php';
                }
                Logger::error('jornada_aluno salvarRedacaoJornada: token CSRF inválido', [
                    'aluno_id' => $user['id'],
                ], 'jornadas');
                $this->json(['success' => false, 'error' => 'Token de segurança inválido. Por favor, recarregue a página e tente novamente.'], 400);
                return;
            }
            
            $jornadaRedacaoId = $_POST['jornada_redacao_id'] ?? null;
            $conteudo = trim($_POST['conteudo'] ?? '');
            $tempoEscrita = intval($_POST['tempo_escrita'] ?? 0);
            
            if (!$jornadaRedacaoId) {
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/../../Core/Logger.php';
                }
                Logger::error('jornada_aluno salvarRedacaoJornada: ID da jornada de redação não fornecido', [
                    'aluno_id' => $user['id'],
                ], 'jornadas');
                $this->json(['success' => false, 'error' => 'ID da jornada de redação não fornecido. Por favor, recarregue a página e tente novamente.'], 400);
                return;
            }
            
            if (empty($conteudo)) {
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/../../Core/Logger.php';
                }
                Logger::error('jornada_aluno salvarRedacaoJornada: conteúdo vazio', [
                    'aluno_id' => $user['id'],
                    'jornada_redacao_id' => $jornadaRedacaoId,
                ], 'jornadas');
                $this->json(['success' => false, 'error' => 'O conteúdo da redação não pode estar vazio. Por favor, escreva sua redação antes de salvar.'], 400);
                return;
            }
            
            // Buscar dados do aluno
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );
            
            if (!$aluno) {
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/../../Core/Logger.php';
                }
                Logger::error('jornada_aluno salvarRedacaoJornada: aluno não encontrado', [
                    'user_id' => $user['id'],
                ], 'jornadas');
                $this->json(['error' => 'Aluno não encontrado'], 404);
                return;
            }
            
            // Verificar se a redação da jornada existe, pertence à turma do aluno e a jornada está ativa
            $redacaoJornada = $this->db->fetch(
                "SELECT jr.*, j.turma_id
                 FROM jornadas_redacoes jr
                 INNER JOIN jornadas j ON jr.jornada_id = j.id
                 WHERE jr.id = :id AND j.turma_id = :turma_id AND j.status = 'ativa'",
                ['id' => $jornadaRedacaoId, 'turma_id' => $aluno['turma_id']]
            );
            
            if (!$redacaoJornada) {
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/../../Core/Logger.php';
                }
                Logger::error('jornada_aluno salvarRedacaoJornada: redação da jornada não encontrada ou inativa', [
                    'aluno_id' => $aluno['id'],
                    'jornada_redacao_id' => $jornadaRedacaoId,
                    'turma_id' => $aluno['turma_id'],
                ], 'jornadas');
                $this->json(['error' => 'Redação da jornada não encontrada ou jornada não está ativa'], 404);
                return;
            }
            
            $titulo = $redacaoJornada['tema_sugerido'];
            
            $redacaoIdParam = $_POST['redacao_id'] ?? null;
            
            // Verificar se já existe redação do aluno
            if ($redacaoIdParam) {
                // Buscar redação específica
                $redacaoExistente = $this->db->fetch(
                    "SELECT jra.*, r.id as redacao_id
                     FROM jornadas_redacoes_alunos jra
                     INNER JOIN redacoes r ON jra.redacao_id = r.id
                     WHERE jra.jornada_redacao_id = :jornada_redacao_id 
                       AND jra.aluno_id = :aluno_id
                       AND r.id = :redacao_id
                     ORDER BY jra.versao DESC
                     LIMIT 1",
                    [
                        'jornada_redacao_id' => $jornadaRedacaoId, 
                        'aluno_id' => $aluno['id'],
                        'redacao_id' => $redacaoIdParam
                    ]
                );
            } else {
                // Buscar rascunho mais recente (priorizar status rascunho, mas também considerar se eh_rascunho = 1)
                $redacaoExistente = $this->db->fetch(
                    "SELECT jra.*, r.id as redacao_id, r.eh_rascunho
                     FROM jornadas_redacoes_alunos jra
                     INNER JOIN redacoes r ON jra.redacao_id = r.id
                     WHERE jra.jornada_redacao_id = :jornada_redacao_id 
                       AND jra.aluno_id = :aluno_id
                       AND (jra.status = 'rascunho' OR r.eh_rascunho = 1)
                     ORDER BY jra.versao DESC, jra.created_at DESC
                     LIMIT 1",
                    ['jornada_redacao_id' => $jornadaRedacaoId, 'aluno_id' => $aluno['id']]
                );
            }
            
            if ($redacaoExistente) {
                // Verificar se a coluna tempo_escrita existe
                $hasTempoEscrita = false;
                try {
                    $columnCheck = $this->db->fetch("SHOW COLUMNS FROM redacoes LIKE 'tempo_escrita'");
                    $hasTempoEscrita = !empty($columnCheck);
                } catch (Exception $e) {
                    error_log("Erro ao verificar coluna tempo_escrita: " . $e->getMessage());
                }
                
                // Atualizar rascunho existente
                // Garantir que eh_rascunho seja 1 ao salvar rascunho
                if ($hasTempoEscrita) {
                    $rowsAffected = $this->db->update(
                        "UPDATE redacoes SET 
                            titulo = :titulo,
                            conteudo = :conteudo,
                            texto = :conteudo,
                            tema = :tema,
                            eh_rascunho = 1,
                            tempo_escrita = :tempo_escrita,
                            updated_at = NOW()
                         WHERE id = :redacao_id",
                        [
                            'titulo' => $titulo,
                            'conteudo' => $conteudo,
                            'tema' => $redacaoJornada['tema_sugerido'],
                            'tempo_escrita' => $tempoEscrita,
                            'redacao_id' => $redacaoExistente['redacao_id']
                        ]
                    );
                } else {
                    // Se a coluna não existe, atualizar sem tempo_escrita
                    $rowsAffected = $this->db->update(
                        "UPDATE redacoes SET 
                            titulo = :titulo,
                            conteudo = :conteudo,
                            texto = :conteudo,
                            tema = :tema,
                            eh_rascunho = 1,
                            updated_at = NOW()
                         WHERE id = :redacao_id",
                        [
                            'titulo' => $titulo,
                            'conteudo' => $conteudo,
                            'tema' => $redacaoJornada['tema_sugerido'],
                            'redacao_id' => $redacaoExistente['redacao_id']
                        ]
                    );
                }
                
                error_log("Salvar rascunho - UPDATE redacoes: {$rowsAffected} linhas afetadas para redacao_id={$redacaoExistente['redacao_id']}");
                
                // Atualizar status na tabela de vínculo se necessário
                if (isset($redacaoExistente['status']) && $redacaoExistente['status'] !== 'rascunho') {
                    $this->db->update(
                        "UPDATE jornadas_redacoes_alunos SET 
                            status = 'rascunho',
                            updated_at = NOW()
                         WHERE id = :jra_id",
                        ['jra_id' => $redacaoExistente['id']]
                    );
                    error_log("Salvar rascunho - Status atualizado para 'rascunho' no vínculo jra_id={$redacaoExistente['id']}");
                }
                
                $redacaoId = $redacaoExistente['redacao_id'];
            } else {
                // Verificar se a coluna tempo_escrita existe
                $hasTempoEscrita = false;
                try {
                    $columnCheck = $this->db->fetch("SHOW COLUMNS FROM redacoes LIKE 'tempo_escrita'");
                    $hasTempoEscrita = !empty($columnCheck);
                } catch (Exception $e) {
                    error_log("Erro ao verificar coluna tempo_escrita: " . $e->getMessage());
                }
                
                // Criar nova redação
                if ($hasTempoEscrita) {
                    $redacaoId = $this->db->insert(
                        "INSERT INTO redacoes 
                         (aluno_id, jornada_id, titulo, conteudo, texto, tema, tipo, eh_rascunho, tempo_escrita, created_at)
                         VALUES (:aluno_id, :jornada_id, :titulo, :conteudo, :conteudo, :tema, 'jornada', 1, :tempo_escrita, NOW())",
                        [
                            'aluno_id' => $aluno['id'],
                            'jornada_id' => $redacaoJornada['jornada_id'],
                            'titulo' => $titulo,
                            'conteudo' => $conteudo,
                            'tema' => $redacaoJornada['tema_sugerido'],
                            'tempo_escrita' => $tempoEscrita
                        ]
                    );
                } else {
                    // Se a coluna não existe, inserir sem tempo_escrita
                    $redacaoId = $this->db->insert(
                        "INSERT INTO redacoes 
                         (aluno_id, jornada_id, titulo, conteudo, texto, tema, tipo, eh_rascunho, created_at)
                         VALUES (:aluno_id, :jornada_id, :titulo, :conteudo, :conteudo, :tema, 'jornada', 1, NOW())",
                        [
                            'aluno_id' => $aluno['id'],
                            'jornada_id' => $redacaoJornada['jornada_id'],
                            'titulo' => $titulo,
                            'conteudo' => $conteudo,
                            'tema' => $redacaoJornada['tema_sugerido']
                        ]
                    );
                }
                
                // Vincular à jornada
                $versao = 1;
                $redacaoAnterior = $this->db->fetch(
                    "SELECT MAX(versao) as max_versao FROM jornadas_redacoes_alunos 
                     WHERE jornada_redacao_id = :jornada_redacao_id AND aluno_id = :aluno_id",
                    ['jornada_redacao_id' => $jornadaRedacaoId, 'aluno_id' => $aluno['id']]
                );
                
                if ($redacaoAnterior && $redacaoAnterior['max_versao']) {
                    $versao = $redacaoAnterior['max_versao'] + 1;
                }
                
                $this->db->insert(
                    "INSERT INTO jornadas_redacoes_alunos 
                     (jornada_redacao_id, redacao_id, aluno_id, versao, status, created_at)
                     VALUES (:jornada_redacao_id, :redacao_id, :aluno_id, :versao, 'rascunho', NOW())",
                    [
                        'jornada_redacao_id' => $jornadaRedacaoId,
                        'redacao_id' => $redacaoId,
                        'aluno_id' => $aluno['id'],
                        'versao' => $versao
                    ]
                );
            }
            
            $this->json([
                'success' => true,
                'message' => 'Rascunho salvo com sucesso',
                'redacao_id' => $redacaoId
            ]);
            
        } catch (Exception $e) {
            if (!class_exists('Logger')) {
                require_once __DIR__ . '/../../Core/Logger.php';
            }
            Logger::error('jornada_aluno salvarRedacaoJornada: exceção ao salvar rascunho', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'aluno_id' => $user['id'] ?? null,
                'jornada_redacao_id' => $jornadaRedacaoId ?? null,
            ], 'jornadas');
            error_log("Erro ao salvar rascunho da jornada: " . $e->getMessage());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("Stack trace: " . $e->getTraceAsString());
                }
            }
            
            // Mensagem de erro mais amigável
            $errorMessage = 'Erro ao salvar rascunho';
            $errorDetails = $e->getMessage();
            
            // Verificar se é erro de coluna faltante
            if (strpos($errorDetails, 'tempo_escrita') !== false || strpos($errorDetails, 'Column not found') !== false || strpos($errorDetails, 'Unknown column') !== false) {
                $errorMessage = 'Erro no banco de dados: A coluna tempo_escrita não existe na tabela redacoes. Por favor, execute o script SQL: database/adicionar_tempo_escrita_redacoes.sql no seu banco de dados.';
            } elseif (strpos($errorDetails, 'SQLSTATE') !== false) {
                $errorMessage = 'Erro no banco de dados. Por favor, verifique se todas as tabelas e colunas estão criadas corretamente. Detalhes: ' . htmlspecialchars(substr($errorDetails, 0, 200));
            } else {
                $errorMessage = 'Erro ao salvar rascunho: ' . htmlspecialchars($errorDetails);
            }
            
            $this->json([
                'success' => false,
                'error' => $errorMessage
            ], 500);
        }
    }
    
    /**
     * Finalizar redação da jornada (corrigir automaticamente pela IA)
     */
    public function finalizarRedacaoJornada()
    {
        // Criar arquivo de log específico
        $logFile = __DIR__ . '/../../storage/logs/finalizar_redacao_' . date('Y-m-d') . '.log';
        $log = function($message) use ($logFile) {
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[{$timestamp}] {$message}\n";
            error_log($logMessage, 3, $logFile);
            error_log($logMessage); // Também no log padrão
        };
        
        try {
            $log("=== INÍCIO FINALIZAR REDAÇÃO ===");
            $log("POST completo: " . json_encode($_POST, JSON_UNESCAPED_UNICODE));
            
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'aluno') {
                $log("ERRO: Acesso negado - user tipo: " . ($user['tipo'] ?? 'null'));
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/../../Core/Logger.php';
                }
                Logger::error('jornada_aluno finalizarRedacaoJornada: acesso negado', [
                    'user_id' => $user['id'] ?? null,
                    'user_tipo' => $user['tipo'] ?? null,
                ], 'jornadas');
                $_SESSION['error_message'] = 'Acesso negado';
                $this->redirect('/jornadas');
                return;
            }
            
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $log("ERRO: Token CSRF inválido");
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/../../Core/Logger.php';
                }
                Logger::error('jornada_aluno finalizarRedacaoJornada: token CSRF inválido', [
                    'aluno_id' => $user['id'],
                ], 'jornadas');
                $_SESSION['error_message'] = 'Token inválido';
                $this->redirect('/jornadas');
                return;
            }
            
            $jornadaRedacaoId = $_POST['jornada_redacao_id'] ?? null;
            $redacaoId = $_POST['redacao_id'] ?? null;
            $conteudo = $_POST['conteudo'] ?? '';
            $tempoEscrita = intval($_POST['tempo_escrita'] ?? 0);
            
            // Log detalhado dos dados recebidos
            $log("Dados POST recebidos:");
            $log("  jornada_redacao_id: " . ($jornadaRedacaoId ?? 'null'));
            $log("  redacao_id: " . ($redacaoId ?? 'null'));
            $log("  conteudo_length: " . strlen($conteudo));
            $log("  conteudo_preview (primeiros 500 chars): " . substr($conteudo, 0, 500));
            $log("  conteudo_raw (hex): " . bin2hex(substr($conteudo, 0, 100)));
            $log("  tempo_escrita: " . $tempoEscrita);
            $log("  POST keys: " . implode(', ', array_keys($_POST)));
            
            if (!$jornadaRedacaoId) {
                $log("ERRO: jornada_redacao_id não fornecido");
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/../../Core/Logger.php';
                }
                Logger::error('jornada_aluno finalizarRedacaoJornada: jornada_redacao_id não fornecido', [
                    'aluno_id' => $user['id'],
                ], 'jornadas');
                $_SESSION['error_message'] = 'ID da jornada de redação não fornecido';
                $this->redirect('/jornadas');
                return;
            }
            
            if (empty($conteudo) || strlen(trim($conteudo)) == 0) {
                $log("ERRO: Conteúdo vazio recebido - conteudo_length=" . strlen($conteudo));
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/../../Core/Logger.php';
                }
                Logger::error('jornada_aluno finalizarRedacaoJornada: conteúdo vazio', [
                    'aluno_id' => $user['id'],
                    'jornada_redacao_id' => $jornadaRedacaoId,
                    'conteudo_length' => strlen($conteudo),
                ], 'jornadas');
                $_SESSION['error_message'] = 'ERRO: O conteúdo da redação não pode estar vazio. Por favor, escreva sua redação antes de finalizar.';
                if (isset($jornadaRedacaoId) && $jornadaRedacaoId) {
                    $this->redirect('/jornadas/redacao/' . $jornadaRedacaoId . '/escrever');
                } else {
                    $this->redirect('/jornadas');
                }
                return;
            }
            
            // Remover espaços em branco apenas no início e fim, não no meio
            $conteudo = trim($conteudo);
            $log("Conteúdo após trim: length=" . strlen($conteudo));
            
            // Buscar dados do aluno
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );
            
            if (!$aluno) {
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/../../Core/Logger.php';
                }
                Logger::error('jornada_aluno finalizarRedacaoJornada: aluno não encontrado', [
                    'user_id' => $user['id'],
                ], 'jornadas');
                $_SESSION['error_message'] = 'Aluno não encontrado';
                $this->redirect('/jornadas');
                return;
            }
            
            // Buscar redação da jornada para pegar o tema (apenas se a jornada estiver ativa)
            $redacaoJornada = $this->db->fetch(
                "SELECT jr.*, j.id as jornada_id, j.turma_id
                 FROM jornadas_redacoes jr
                 INNER JOIN jornadas j ON jr.jornada_id = j.id
                 WHERE jr.id = :id AND j.turma_id = :turma_id AND j.status = 'ativa'",
                ['id' => $jornadaRedacaoId, 'turma_id' => $aluno['turma_id']]
            );
            
            if (!$redacaoJornada) {
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/../../Core/Logger.php';
                }
                Logger::error('jornada_aluno finalizarRedacaoJornada: redação da jornada não encontrada ou inativa', [
                    'aluno_id' => $aluno['id'],
                    'jornada_redacao_id' => $jornadaRedacaoId,
                    'turma_id' => $aluno['turma_id'],
                ], 'jornadas');
                $_SESSION['error_message'] = 'Redação da jornada não encontrada ou jornada não está ativa';
                $this->redirect('/jornadas');
                return;
            }
            
            $titulo = $redacaoJornada['tema_sugerido'];
            
            // Se não tem redacao_id, criar nova redação
            if (!$redacaoId) {
                // Verificar se a coluna tempo_escrita existe
                $hasTempoEscrita = false;
                try {
                    $columnCheck = $this->db->fetch("SHOW COLUMNS FROM redacoes LIKE 'tempo_escrita'");
                    $hasTempoEscrita = !empty($columnCheck);
                } catch (Exception $e) {
                    error_log("Erro ao verificar coluna tempo_escrita: " . $e->getMessage());
                }
                
                // Criar nova redação
                if ($hasTempoEscrita) {
                    $redacaoId = $this->db->insert(
                        "INSERT INTO redacoes 
                         (aluno_id, jornada_id, titulo, conteudo, texto, tema, tipo, eh_rascunho, tempo_escrita, created_at)
                         VALUES (:aluno_id, :jornada_id, :titulo, :conteudo, :texto, :tema, 'jornada', 0, :tempo_escrita, NOW())",
                        [
                            'aluno_id' => $aluno['id'],
                            'jornada_id' => $redacaoJornada['jornada_id'],
                            'titulo' => $titulo,
                            'conteudo' => $conteudo,
                            'texto' => $conteudo,
                            'tema' => $titulo,
                            'tempo_escrita' => $tempoEscrita
                        ]
                    );
                } else {
                    // Se a coluna não existe, inserir sem tempo_escrita
                    $redacaoId = $this->db->insert(
                        "INSERT INTO redacoes 
                         (aluno_id, jornada_id, titulo, conteudo, texto, tema, tipo, eh_rascunho, created_at)
                         VALUES (:aluno_id, :jornada_id, :titulo, :conteudo, :texto, :tema, 'jornada', 0, NOW())",
                        [
                            'aluno_id' => $aluno['id'],
                            'jornada_id' => $redacaoJornada['jornada_id'],
                            'titulo' => $titulo,
                            'conteudo' => $conteudo,
                            'texto' => $conteudo,
                            'tema' => $titulo
                        ]
                    );
                }
                
                // Vincular à jornada
                $versao = 1;
                $redacaoAnterior = $this->db->fetch(
                    "SELECT MAX(versao) as max_versao FROM jornadas_redacoes_alunos 
                     WHERE jornada_redacao_id = :jornada_redacao_id AND aluno_id = :aluno_id",
                    ['jornada_redacao_id' => $jornadaRedacaoId, 'aluno_id' => $aluno['id']]
                );
                
                if ($redacaoAnterior && $redacaoAnterior['max_versao']) {
                    $versao = $redacaoAnterior['max_versao'] + 1;
                }
                
                $this->db->insert(
                    "INSERT INTO jornadas_redacoes_alunos 
                     (jornada_redacao_id, redacao_id, aluno_id, versao, status, created_at)
                     VALUES (:jornada_redacao_id, :redacao_id, :aluno_id, :versao, 'entregue', NOW())",
                    [
                        'jornada_redacao_id' => $jornadaRedacaoId,
                        'redacao_id' => $redacaoId,
                        'aluno_id' => $aluno['id'],
                        'versao' => $versao
                    ]
                );
            } else {
                // Verificar se a redação pertence ao aluno e à jornada
                // Buscar o vínculo mais recente para esta redação
                $redacao = $this->db->fetch(
                    "SELECT r.*, jra.jornada_redacao_id, jra.versao, jra.status as jra_status
                     FROM redacoes r
                     INNER JOIN jornadas_redacoes_alunos jra ON r.id = jra.redacao_id
                     WHERE r.id = :redacao_id 
                       AND jra.aluno_id = :aluno_id 
                       AND jra.jornada_redacao_id = :jornada_redacao_id
                     ORDER BY jra.id DESC
                     LIMIT 1",
                    [
                        'redacao_id' => $redacaoId,
                        'aluno_id' => $aluno['id'],
                        'jornada_redacao_id' => $jornadaRedacaoId
                    ]
                );
                
                if (!$redacao) {
                    $log("ERRO: Redação não encontrada - redacao_id={$redacaoId}, aluno_id={$aluno['id']}, jornada_redacao_id={$jornadaRedacaoId}");
                    $_SESSION['error_message'] = 'Redação não encontrada';
                    $this->redirect('/jornadas/' . $redacaoJornada['jornada_id']);
                    return;
                }
                
                $log("Redação encontrada: id={$redacao['id']}, conteudo_atual_length=" . strlen($redacao['conteudo'] ?? ''));
                
                // Atualizar redação - garantir que eh_rascunho seja 0
                // IMPORTANTE: Verificar se o conteúdo não está vazio
                if (empty($conteudo) || strlen(trim($conteudo)) == 0) {
                    $log("ERRO: Conteúdo vazio ao finalizar - conteudo_length=" . strlen($conteudo));
                    $_SESSION['error_message'] = 'ERRO: O conteúdo da redação não pode estar vazio. Por favor, escreva sua redação antes de finalizar.';
                    $this->redirect('/jornadas/redacao/' . $jornadaRedacaoId . '/escrever');
                    return;
                }
                
                $log("Dados para UPDATE: redacao_id={$redacaoId}, conteudo_length=" . strlen($conteudo) . ", tempo_escrita={$tempoEscrita}");
                $log("Preview conteúdo (primeiros 500 chars): " . substr($conteudo, 0, 500));
                
                $log("Executando UPDATE na tabela redacoes...");
                // Verificar se a coluna tempo_escrita existe
                $hasTempoEscrita = false;
                try {
                    $columnCheck = $this->db->fetch("SHOW COLUMNS FROM redacoes LIKE 'tempo_escrita'");
                    $hasTempoEscrita = !empty($columnCheck);
                } catch (Exception $e) {
                    error_log("Erro ao verificar coluna tempo_escrita: " . $e->getMessage());
                }
                
                $log("Parâmetros: titulo={$titulo}, conteudo_length=" . strlen($conteudo) . ", tempo_escrita={$tempoEscrita}, redacao_id={$redacaoId}, hasTempoEscrita=" . ($hasTempoEscrita ? 'true' : 'false'));
                
                if ($hasTempoEscrita) {
                    $redacaoRowsAffected = $this->db->update(
                        "UPDATE redacoes SET 
                            titulo = :titulo,
                            conteudo = :conteudo,
                            texto = :texto,
                            eh_rascunho = 0,
                            tempo_escrita = :tempo_escrita,
                            updated_at = NOW()
                         WHERE id = :redacao_id",
                        [
                            'titulo' => $titulo,
                            'conteudo' => $conteudo,
                            'texto' => $conteudo,
                            'tempo_escrita' => $tempoEscrita,
                            'redacao_id' => $redacaoId
                        ]
                    );
                } else {
                    // Se a coluna não existe, atualizar sem tempo_escrita
                    $redacaoRowsAffected = $this->db->update(
                        "UPDATE redacoes SET 
                            titulo = :titulo,
                            conteudo = :conteudo,
                            texto = :texto,
                            eh_rascunho = 0,
                            updated_at = NOW()
                         WHERE id = :redacao_id",
                        [
                            'titulo' => $titulo,
                            'conteudo' => $conteudo,
                            'texto' => $conteudo,
                            'redacao_id' => $redacaoId
                        ]
                    );
                }
                
                $log("UPDATE redacoes executado: {$redacaoRowsAffected} linhas afetadas para redacao_id={$redacaoId}");
                
                if ($redacaoRowsAffected === 0) {
                    $log("ERRO CRÍTICO: Nenhuma linha foi afetada pelo UPDATE! A redação pode não existir ou o ID está incorreto.");
                    $_SESSION['error_message'] = 'ERRO: Não foi possível salvar a redação. A redação pode não existir ou o ID está incorreto. Por favor, tente novamente ou entre em contato com o suporte.';
                    $this->redirect('/jornadas/redacao/' . $jornadaRedacaoId . '/escrever');
                    return;
                }
                
                // Verificar se o conteúdo foi realmente salvo
                $log("Verificando se o conteúdo foi salvo...");
                $redacaoVerificada = $this->db->fetch(
                    "SELECT id, conteudo, texto, eh_rascunho FROM redacoes WHERE id = :redacao_id",
                    ['redacao_id' => $redacaoId]
                );
                
                if ($redacaoVerificada) {
                    $conteudoSalvoLength = strlen($redacaoVerificada['conteudo'] ?? '');
                    $log("Verificação pós-UPDATE: conteudo_length={$conteudoSalvoLength}, eh_rascunho={$redacaoVerificada['eh_rascunho']}");
                    $log("Preview conteúdo salvo (primeiros 500 chars): " . substr($redacaoVerificada['conteudo'] ?? '', 0, 500));
                    
                    if (empty($redacaoVerificada['conteudo'])) {
                        $log("ERRO: Conteúdo está vazio após UPDATE! Forçando atualização...");
                        $this->db->update(
                            "UPDATE redacoes SET 
                                conteudo = :conteudo,
                                texto = :texto,
                                eh_rascunho = 0,
                                updated_at = NOW()
                             WHERE id = :redacao_id",
                            [
                                'conteudo' => $conteudo,
                                'texto' => $conteudo,
                                'redacao_id' => $redacaoId
                            ]
                        );
                        $log("UPDATE forçado executado");
                    } else {
                        $log("SUCESSO: Conteúdo foi salvo corretamente!");
                    }
                } else {
                    $log("ERRO: Não foi possível verificar a redação após UPDATE!");
                }
                
                // Verificar se o vínculo existe antes de atualizar
                // IMPORTANTE: Buscar pelo redacao_id apenas, pois pode haver múltiplos vínculos
                $vinculoExistente = $this->db->fetch(
                    "SELECT id, status, versao FROM jornadas_redacoes_alunos 
                     WHERE redacao_id = :redacao_id 
                       AND aluno_id = :aluno_id
                       AND jornada_redacao_id = :jornada_redacao_id
                     ORDER BY id DESC
                     LIMIT 1",
                    [
                        'redacao_id' => $redacaoId,
                        'aluno_id' => $aluno['id'],
                        'jornada_redacao_id' => $jornadaRedacaoId
                    ]
                );
                
                if ($vinculoExistente) {
                    // Atualizar status na tabela de vínculo existente
                    // Atualizar TODOS os vínculos desta redação para garantir consistência
                    // IMPORTANTE: Atualizar pelo ID do vínculo para garantir que atualiza o correto
                    $rowsAffected = $this->db->update(
                        "UPDATE jornadas_redacoes_alunos SET 
                            status = 'entregue',
                            updated_at = NOW()
                         WHERE id = :vinculo_id",
                        [
                            'vinculo_id' => $vinculoExistente['id']
                        ]
                    );
                    
                    error_log("Finalizar redação - UPDATE jornadas_redacoes_alunos (vínculo existente): {$rowsAffected} linhas afetadas para vínculo_id={$vinculoExistente['id']}, redacao_id={$redacaoId}, versao={$vinculoExistente['versao']}, status_anterior={$vinculoExistente['status']}");
                    
                    // Se não atualizou, tentar atualizar todos os vínculos desta redação
                    if ($rowsAffected === 0) {
                        error_log("AVISO: Nenhuma linha afetada pelo UPDATE pelo ID. Tentando atualizar todos os vínculos da redação...");
                        $rowsAffected = $this->db->update(
                            "UPDATE jornadas_redacoes_alunos SET 
                                status = 'entregue',
                                updated_at = NOW()
                             WHERE redacao_id = :redacao_id 
                               AND aluno_id = :aluno_id
                               AND jornada_redacao_id = :jornada_redacao_id",
                            [
                                'redacao_id' => $redacaoId,
                                'aluno_id' => $aluno['id'],
                                'jornada_redacao_id' => $jornadaRedacaoId
                            ]
                        );
                        error_log("Finalizar redação - UPDATE alternativo: {$rowsAffected} linhas afetadas");
                    }
                    
                    // Verificar se o status foi realmente atualizado
                    $vinculoVerificado = $this->db->fetch(
                        "SELECT id, status, versao FROM jornadas_redacoes_alunos 
                         WHERE redacao_id = :redacao_id 
                           AND aluno_id = :aluno_id
                           AND jornada_redacao_id = :jornada_redacao_id
                         ORDER BY id DESC
                         LIMIT 1",
                        [
                            'redacao_id' => $redacaoId,
                            'aluno_id' => $aluno['id'],
                            'jornada_redacao_id' => $jornadaRedacaoId
                        ]
                    );
                    
                    if ($vinculoVerificado) {
                        error_log("Finalizar redação - Verificação pós-UPDATE: status={$vinculoVerificado['status']}, versao={$vinculoVerificado['versao']}");
                        
                        // Se ainda estiver como rascunho, forçar atualização
                        if ($vinculoVerificado['status'] === 'rascunho') {
                            error_log("ERRO: Status ainda é 'rascunho' após UPDATE. Forçando atualização...");
                            $this->db->update(
                                "UPDATE jornadas_redacoes_alunos SET 
                                    status = 'entregue',
                                    updated_at = NOW()
                                 WHERE id = :vinculo_id",
                                ['vinculo_id' => $vinculoVerificado['id']]
                            );
                        }
                    }
                } else {
                    // Criar novo vínculo se não existir
                    error_log("AVISO: Vínculo não encontrado. Criando novo vínculo para redacao_id={$redacaoId}, aluno_id={$aluno['id']}, jornada_redacao_id={$jornadaRedacaoId}");
                    
                    $versao = 1;
                    $redacaoAnterior = $this->db->fetch(
                        "SELECT MAX(versao) as max_versao FROM jornadas_redacoes_alunos 
                         WHERE jornada_redacao_id = :jornada_redacao_id AND aluno_id = :aluno_id",
                        ['jornada_redacao_id' => $jornadaRedacaoId, 'aluno_id' => $aluno['id']]
                    );
                    
                    if ($redacaoAnterior && $redacaoAnterior['max_versao']) {
                        $versao = $redacaoAnterior['max_versao'] + 1;
                    }
                    
                    $this->db->insert(
                        "INSERT INTO jornadas_redacoes_alunos 
                         (jornada_redacao_id, redacao_id, aluno_id, versao, status, created_at)
                         VALUES (:jornada_redacao_id, :redacao_id, :aluno_id, :versao, 'entregue', NOW())",
                        [
                            'jornada_redacao_id' => $jornadaRedacaoId,
                            'redacao_id' => $redacaoId,
                            'aluno_id' => $aluno['id'],
                            'versao' => $versao
                        ]
                    );
                    error_log("Vínculo criado com sucesso para redacao_id={$redacaoId}, versao={$versao}");
                }
            }
            
            // Verificação final: garantir que tudo foi salvo corretamente
            $log("Verificação final antes de redirecionar...");
            $verificacaoFinal = $this->db->fetch(
                "SELECT r.id, r.eh_rascunho, jra.status as jra_status, jra.id as jra_id
                 FROM redacoes r
                 LEFT JOIN jornadas_redacoes_alunos jra ON r.id = jra.redacao_id 
                    AND jra.aluno_id = :aluno_id 
                    AND jra.jornada_redacao_id = :jornada_redacao_id
                 WHERE r.id = :redacao_id
                 ORDER BY jra.id DESC
                 LIMIT 1",
                [
                    'redacao_id' => $redacaoId,
                    'aluno_id' => $aluno['id'],
                    'jornada_redacao_id' => $jornadaRedacaoId
                ]
            );
            
            if ($verificacaoFinal) {
                $log("Verificação final - eh_rascunho: " . ($verificacaoFinal['eh_rascunho'] ?? 'null') . ", jra_status: " . ($verificacaoFinal['jra_status'] ?? 'null'));
                
                // Se ainda houver inconsistência, forçar correção
                if (($verificacaoFinal['eh_rascunho'] ?? 1) == 1 || ($verificacaoFinal['jra_status'] ?? 'rascunho') === 'rascunho') {
                    $log("AVISO: Inconsistência detectada na verificação final. Forçando correção...");
                    
                    // Forçar atualização da redação
                    $this->db->update(
                        "UPDATE redacoes SET eh_rascunho = 0, updated_at = NOW() WHERE id = :redacao_id",
                        ['redacao_id' => $redacaoId]
                    );
                    
                    // Forçar atualização do vínculo
                    if ($verificacaoFinal['jra_id']) {
                        $this->db->update(
                            "UPDATE jornadas_redacoes_alunos SET status = 'entregue', updated_at = NOW() WHERE id = :jra_id",
                            ['jra_id' => $verificacaoFinal['jra_id']]
                        );
                    } else {
                        // Criar vínculo se não existir
                        $versao = 1;
                        $redacaoAnterior = $this->db->fetch(
                            "SELECT MAX(versao) as max_versao FROM jornadas_redacoes_alunos 
                             WHERE jornada_redacao_id = :jornada_redacao_id AND aluno_id = :aluno_id",
                            ['jornada_redacao_id' => $jornadaRedacaoId, 'aluno_id' => $aluno['id']]
                        );
                        if ($redacaoAnterior && $redacaoAnterior['max_versao']) {
                            $versao = $redacaoAnterior['max_versao'] + 1;
                        }
                        $this->db->insert(
                            "INSERT INTO jornadas_redacoes_alunos 
                             (jornada_redacao_id, redacao_id, aluno_id, versao, status, created_at)
                             VALUES (:jornada_redacao_id, :redacao_id, :aluno_id, :versao, 'entregue', NOW())",
                            [
                                'jornada_redacao_id' => $jornadaRedacaoId,
                                'redacao_id' => $redacaoId,
                                'aluno_id' => $aluno['id'],
                                'versao' => $versao
                            ]
                        );
                    }
                    $log("Correção forçada executada");
                }
            } else {
                $log("ERRO: Não foi possível verificar a redação final!");
            }
            
            // Sempre marcar como entregue - apenas o professor corrige
            // O professor pode solicitar correção da IA como referência, mas a correção final é sempre do professor
            $log("SUCESSO: Redação finalizada com sucesso! Redirecionando para próxima etapa...");
            $log("=== FIM FINALIZAR REDAÇÃO ===");
            $_SESSION['success_message'] = 'Redação enviada com sucesso! Aguardando correção do professor.';
            
            // Buscar o módulo de redação atual para encontrar a próxima etapa
            $moduloRedacao = $this->db->fetch(
                "SELECT m.* FROM jornadas_modulos m
                 INNER JOIN jornadas_redacoes jr ON m.jornada_id = jr.jornada_id
                 WHERE jr.id = :jornada_redacao_id AND m.tipo_modulo = 'redacao' AND m.status = 'ativo'
                 LIMIT 1",
                ['jornada_redacao_id' => $jornadaRedacaoId]
            );
            
            if ($moduloRedacao) {
                // Buscar o próximo módulo na ordem
                $proximoModulo = $this->db->fetch(
                    "SELECT * FROM jornadas_modulos 
                     WHERE jornada_id = :jornada_id AND status = 'ativo' AND ordem > :ordem_atual
                     ORDER BY ordem ASC LIMIT 1",
                    [
                        'jornada_id' => $redacaoJornada['jornada_id'],
                        'ordem_atual' => $moduloRedacao['ordem']
                    ]
                );
                
                if ($proximoModulo) {
                    // Calcular o índice da próxima etapa (ordem - 1, pois começa em 0)
                    $proximaEtapa = $proximoModulo['ordem'] - 1;
                    $this->redirect('/jornadas/' . $redacaoJornada['jornada_id'] . '?etapa=' . $proximaEtapa);
                } else {
                    // Não há próxima etapa, redireciona para a jornada
                    $this->redirect('/jornadas/' . $redacaoJornada['jornada_id']);
                }
            } else {
                // Se não encontrou o módulo, redireciona para a jornada
                $this->redirect('/jornadas/' . $redacaoJornada['jornada_id']);
            }
            
        } catch (Exception $e) {
            $log("EXCEÇÃO: Erro ao finalizar redação da jornada: " . $e->getMessage());
            $log("Stack trace: " . $e->getTraceAsString());
            error_log("Erro ao finalizar redação da jornada: " . $e->getMessage());
            if (!class_exists('Logger')) {
                require_once __DIR__ . '/../../Core/Logger.php';
            }
            Logger::error('jornada_aluno finalizarRedacaoJornada: exceção ao finalizar', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'aluno_id' => $user['id'] ?? null,
                'jornada_redacao_id' => $jornadaRedacaoId ?? null,
            ], 'jornadas');
            
            // Mensagem de erro mais amigável para o usuário
            $errorMessage = 'Erro ao finalizar redação';
            $errorDetails = $e->getMessage();
            
            // Verificar se é erro de coluna faltante
            if (strpos($errorDetails, 'tempo_escrita') !== false || strpos($errorDetails, 'Column not found') !== false || strpos($errorDetails, 'Unknown column') !== false) {
                $errorMessage = 'Erro no banco de dados: A coluna tempo_escrita não existe na tabela redacoes. Por favor, execute o script SQL: database/adicionar_tempo_escrita_redacoes.sql no seu banco de dados.';
            } elseif (strpos($errorDetails, 'SQLSTATE') !== false) {
                $errorMessage = 'Erro no banco de dados. Por favor, verifique se todas as tabelas e colunas estão criadas corretamente. Detalhes: ' . htmlspecialchars(substr($errorDetails, 0, 300));
            } else {
                $errorMessage = 'Erro ao finalizar redação: ' . htmlspecialchars($errorDetails);
            }
            
            $_SESSION['error_message'] = $errorMessage;
            
            // Se tiver jornada_redacao_id, redirecionar para a página de escrita para mostrar o erro
            if (isset($jornadaRedacaoId) && $jornadaRedacaoId) {
                $this->redirect('/jornadas/redacao/' . $jornadaRedacaoId . '/escrever');
            } else {
                $jornadaId = isset($redacaoJornada) && isset($redacaoJornada['jornada_id']) ? $redacaoJornada['jornada_id'] : null;
                if ($jornadaId) {
                    $this->redirect('/jornadas/' . $jornadaId);
                } else {
                    $this->redirect('/jornadas');
                }
            }
        }
    }
    
    /**
     * Formata a data do plano de aula para exibição na listagem (evita processamento na view).
     */
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
     * Lista planos de aula do aluno (filtrados pela turma do aluno)
     */
    public function planosAula()
    {
        $user = $this->authManager->getUser();
        
        // Buscar dados do aluno com informações da turma
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome, t.serie as turma_serie, YEAR(CURDATE()) as turma_ano_letivo
             FROM alunos a 
             LEFT JOIN turmas t ON a.turma_id = t.id 
             WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );

        if (!$aluno) {
            $this->redirect('/logout');
        }

        // Buscar planos de aula APENAS da turma do aluno (não de outras turmas/séries)
        $planosAula = [];
        if (!empty($aluno['turma_id'])) {
            $anoAtual = date('Y');
            $planosAula = $this->db->fetchAll(
                "SELECT pa.*, 
                        p.nome as professor_nome,
                        m.nome as materia_nome,
                        t.nome as turma_nome,
                        t.serie as turma_serie,
                        YEAR(CURDATE()) as turma_ano_letivo
                 FROM planos_aula pa
                 LEFT JOIN professores p ON pa.professor_id = p.id
                 LEFT JOIN materias m ON pa.materia_id = m.id
                 LEFT JOIN turmas t ON pa.turma_id = t.id
                 WHERE pa.turma_id = :turma_id 
                     AND pa.deleted_at IS NULL
                     AND YEAR(pa.created_at) = :ano_atual
                 ORDER BY pa.created_at DESC",
                [
                    'turma_id' => $aluno['turma_id'],
                    'ano_atual' => $anoAtual
                ]
            );
            // Pré-formata a coluna Data para evitar processamento pesado na view (json_decode/date por linha)
            foreach ($planosAula as &$plano) {
                $plano['data_exibicao'] = $this->formatarDataPlanoAula($plano);
            }
            unset($plano);
        }

        $data = [
            'title' => 'Planos de Aula - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'planos_aula' => $planosAula,
            'current_page' => 'planos-aula'
        ];

        $this->viewWithLayout('student', 'student/planos-aula/index', $data);
    }
    
    /**
     * Visualiza detalhes de um plano de aula
     */
    public function visualizarPlanoAula($id)
    {
        // Valida e converte ID para inteiro
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG visualizarPlanoAula - ID inválido: " . var_export($id, true));
                }
            }
            $this->setFlashMessage('ID do plano de aula inválido', 'error');
            $this->redirect('/aluno/planos-aula');
            return;
        }
        
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        
                error_log("DEBUG visualizarPlanoAula - ID recebido: " . $id);
        
            }
        
        }
        
        $user = $this->authManager->getUser();
        
        if (!$user || !is_array($user)) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG visualizarPlanoAula - Usuário não autenticado");
                }
            }
            $this->redirect('/logout');
            return;
        }
        
        // Buscar dados do aluno
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome 
             FROM alunos a 
             LEFT JOIN turmas t ON a.turma_id = t.id 
             WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );

        if (!$aluno || !is_array($aluno)) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG visualizarPlanoAula - Aluno não encontrado");
                }
            }
            $this->redirect('/logout');
            return;
        }

        // Buscar plano de aula (sem restrição de status, apenas não deletado)
        $planoAula = $this->db->fetch(
            "SELECT pa.*, 
                    p.nome as professor_nome,
                    m.nome as materia_nome,
                    t.nome as turma_nome
             FROM planos_aula pa
             LEFT JOIN professores p ON pa.professor_id = p.id
             LEFT JOIN materias m ON pa.materia_id = m.id
             LEFT JOIN turmas t ON pa.turma_id = t.id
             WHERE pa.id = :id 
             AND pa.deleted_at IS NULL",
            ['id' => $id]
        );

        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {

            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {

                error_log("DEBUG visualizarPlanoAula - Plano encontrado: " . ($planoAula ? 'SIM' : 'NÃO'));

            }

        }
        if ($planoAula) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG visualizarPlanoAula - Tipo do planoAula: " . gettype($planoAula));
                }
            }
        }

        if (!$planoAula || !is_array($planoAula)) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG visualizarPlanoAula - Plano não encontrado ou não é array, redirecionando");
                }
            }
            $this->setFlashMessage('Plano de aula não encontrado', 'error');
            $this->redirect('/aluno/planos-aula');
            return;
        }

        // Verificar se o plano de aula pertence à turma do aluno OU à mesma série e ano letivo
        $turmaAluno = $this->db->fetch(
            "SELECT t.* FROM turmas t WHERE t.id = :turma_id",
            ['turma_id' => $aluno['turma_id']]
        );
        
        $turmaPlano = $this->db->fetch(
            "SELECT t.* FROM turmas t WHERE t.id = :turma_id",
            ['turma_id' => $planoAula['turma_id']]
        );
        
        $podeVisualizar = false;
        if ($planoAula['turma_id'] == $aluno['turma_id']) {
            $podeVisualizar = true;
        } elseif (!empty($turmaAluno['serie']) && !empty($turmaAluno['ano_letivo']) 
                  && !empty($turmaPlano['serie']) && !empty($turmaPlano['ano_letivo'])
                  && $turmaAluno['serie'] == $turmaPlano['serie'] 
                  && $turmaAluno['ano_letivo'] == $turmaPlano['ano_letivo']) {
            $podeVisualizar = true;
        }
        
        if (!$podeVisualizar) {
            $this->setFlashMessage('Você não tem permissão para visualizar este plano de aula', 'error');
            $this->redirect('/aluno/planos-aula');
            return;
        }

        // Garante que todos os dados são arrays
        if (!is_array($planoAula)) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG visualizarPlanoAula - planoAula não é array: " . gettype($planoAula));
                }
            }
            $this->setFlashMessage('Plano de aula não encontrado', 'error');
            $this->redirect('/aluno/planos-aula');
            return;
        }
        
        if (!is_array($user)) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG visualizarPlanoAula - user não é array: " . gettype($user));
                }
            }
            $this->redirect('/logout');
            return;
        }
        
        if (!is_array($aluno)) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG visualizarPlanoAula - aluno não é array: " . gettype($aluno));
                }
            }
            $this->redirect('/logout');
            return;
        }
        
        // Valida todos os dados antes de criar o array
        if (!is_array($user)) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG visualizarPlanoAula - user não é array antes de criar data");
                }
            }
            $this->redirect('/logout');
            return;
        }
        
        if (!is_array($aluno)) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG visualizarPlanoAula - aluno não é array antes de criar data");
                }
            }
            $this->redirect('/logout');
            return;
        }
        
        if (!is_array($planoAula)) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG visualizarPlanoAula - planoAula não é array antes de criar data");
                }
            }
            $this->setFlashMessage('Plano de aula não encontrado', 'error');
            $this->redirect('/aluno/planos-aula');
            return;
        }
        
        $data = [
            'title' => 'Plano de Aula - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'plano_aula' => $planoAula,
            'current_page' => 'planos-aula'
        ];
        
        // Garante que $data é um array e tem as chaves esperadas
        if (!is_array($data)) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG visualizarPlanoAula - data não é array após criação: " . gettype($data));
                }
            }
            $this->setFlashMessage('Erro ao carregar dados', 'error');
            $this->redirect('/aluno/planos-aula');
            return;
        }
        
        // Valida que todas as chaves necessárias existem
        $requiredKeys = ['title', 'user', 'aluno', 'plano_aula', 'current_page'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG visualizarPlanoAula - Chave '{$key}' não existe em data");
                    }
                }
                $this->setFlashMessage('Erro ao carregar dados', 'error');
                $this->redirect('/aluno/planos-aula');
                return;
            }
        }
        
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        
                error_log("DEBUG visualizarPlanoAula - Data validado. Tipo: " . gettype($data) . ", Chaves: " . implode(', ', array_keys($data)));
        
            }
        
        }

        $this->viewWithLayout('student', 'student/planos-aula/visualizar', $data);
    }

    private function assertAlunoAtivo(array $aluno)
    {
        $status = strtoupper((string)($aluno['status'] ?? 'ACTIVE'));
        if ((int)($aluno['ativo'] ?? 1) !== 1 || $status !== 'ACTIVE') {
            throw new Exception('Acesso do aluno está inativo');
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
        $eventos = array_values(array_filter($todos, fn($e) => (int) ($e['visivel_aluno'] ?? 0) === 1));
        $this->viewWithLayout('student', 'student/calendario-letivo', [
            'title'   => 'Calendário Letivo - EducaTudo',
            'user'    => $this->auth->getUser(),
            'ano'     => $ano,
            'eventos' => $eventos,
        ]);
    }
}
}
