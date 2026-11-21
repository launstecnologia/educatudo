<?php

namespace App\Painel\User\Controllers;


use App\Core\AuthManager;
use App\Core\BaseController;
use App\Core\ContextoAluno;
use App\Services\DashboardAlunoService;
use App\Core\Database;
use App\Models\Essays\EssayProposal;
use App\Models\Exams\Exam;
use App\Core\LayoutHelper;
use App\Core\Logger;
use App\Services\MediaStorageService;
use App\Models\Education\OnlineClass;
use App\Modulos\GrupoSeb\Services\GrupoSebService;
use App\Dominio\Pessoas\Services\AlunoLimiteDiarioService;
use App\Modulos\Mural\Services\MuralRecadoService;
use App\Services\StudentAgendaService;
if (!class_exists(__NAMESPACE__ . '\\StudentController', false)) {
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
                    'sort_ts' => $tsColuna > 0 ? $tsColuna : \PHP_INT_MAX,
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
                    $tsColuna > 0 ? $tsColuna : \PHP_INT_MAX
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
                $aTs = isset($a['data_label']) && $a['data_label'] !== '—' ? strtotime(str_replace('/', '-', $a['data_label'])) : \PHP_INT_MAX;
                $bTs = isset($b['data_label']) && $b['data_label'] !== '—' ? strtotime(str_replace('/', '-', $b['data_label'])) : \PHP_INT_MAX;
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
            $this->authManager->liberarAcessoAposTrocaDeSenha();
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
                throw new \Exception('Todos os campos são obrigatórios');
            }
            
            if (strlen($novaSenha) < 6) {
                throw new \Exception('A senha deve ter pelo menos 6 caracteres');
            }
            
            if ($novaSenha !== $confirmarSenha) {
                throw new \Exception('As senhas não coincidem');
            }
            
            if ($novaSenha === '123456') {
                throw new \Exception('A nova senha não pode ser a senha padrão');
            }
            
            // Atualizar senha no banco
            $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
            
            $result = $this->db->query(
                "UPDATE alunos SET senha_hash = :senha_hash WHERE id = :user_id",
                ['senha_hash' => $senhaHash, 'user_id' => $user['id']]
            );
            
            if ($result === false) {
                throw new \Exception('Erro ao atualizar senha');
            }

            $this->authManager->liberarAcessoAposTrocaDeSenha();
            
            $this->json([
                'success' => true, 
                'message' => 'Senha alterada com sucesso!',
                'redirect' => URL . '/dashboard'
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function dashboard()
    {
        $inicioTotal = microtime(true);
        $user = $this->authManager->getUser();

        if (!class_exists('ContextoAluno')) {
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

        $jornadas_habilitadas = LayoutHelper::isModuleEnabled('jornadas');
        $dashboardAcaoCards = $this->isDashboardAcaoCardsLayout();
        $primaryColor = LayoutHelper::get('primary_color', $this->config['school']['colors']['primary'] ?? '#3b82f6');
        $jornadasAtivasLayout = LayoutHelper::get('jornadas_ativas_layout', 'default');
        $onboardingCompletado = !empty($aluno['onboarding_completado']);

        // Grupo Seb: dashboard enxuto (calendário escolar + aula do dia + mural)
        if (GrupoSebService::estaAtivo()) {
            $grupoSeb = new GrupoSebService($this->db);
            $calendario = $grupoSeb->dadosCalendarioEscolar($turmaId);
            $aulasDoDia = $grupoSeb->aulasDoDia($turmaId);
            $muralSvcSeb = new MuralRecadoService($this->db);
            $muralRecados = array_slice($muralSvcSeb->listarParaAluno($alunoId, $turmaId) ?: [], 0, 5);
            $vistosMap = [];
            if ($muralRecados !== []) {
                $ids = array_values(array_filter(array_map(
                    static fn($r) => (int) ($r['id'] ?? 0),
                    $muralRecados
                )));
                if ($ids !== []) {
                    $placeholders = [];
                    $paramsVistos = ['aid' => $alunoId];
                    foreach ($ids as $i => $rid) {
                        $ph = 'rid' . $i;
                        $placeholders[] = ':' . $ph;
                        $paramsVistos[$ph] = $rid;
                    }
                    $vistosRows = $this->db->fetchAll(
                        'SELECT mural_recado_id FROM mural_recados_vistos
                         WHERE aluno_id = :aid AND mural_recado_id IN (' . implode(', ', $placeholders) . ')',
                        $paramsVistos
                    ) ?: [];
                    foreach ($vistosRows as $vr) {
                        $vistosMap[(int) ($vr['mural_recado_id'] ?? 0)] = true;
                    }
                }
            }
            foreach ($muralRecados as &$recadoSeb) {
                $rid = (int) ($recadoSeb['id'] ?? 0);
                $recadoSeb['ja_visto'] = $rid > 0 && !empty($vistosMap[$rid]);
            }
            unset($recadoSeb);
            $muralNaoLidos = $muralSvcSeb->contarParaAluno($alunoId, $turmaId, true);

            $this->viewWithLayout('student', 'student/grupo-seb/dashboard', [
                'title' => 'Dashboard - EducaTudo',
                'user' => $user,
                'aluno' => $aluno,
                'primary_color' => $primaryColor,
                'current_page' => 'dashboard',
                'csrf_token' => $this->generateCsrfToken(),
                'turmas_cursos_select' => $turmasCursosSelect,
                'aulas_do_dia' => $aulasDoDia,
                'mural_recados' => $muralRecados,
                'mural_recados_nao_lidos_count' => $muralNaoLidos,
                'calendario_ano' => (int) ($calendario['ano'] ?? date('Y')),
                'calendario_mes' => (int) ($calendario['mes'] ?? date('n')),
                'calendario_eventos' => $calendario['eventos'] ?? [],
                'calendario_tipo_labels' => $calendario['tipoLabels'] ?? [],
                'calendario_tipo_bg' => $calendario['tipoBg'] ?? [],
                'calendario_tipo_text' => $calendario['tipoText'] ?? [],
                'calendario_nome' => (string) ($calendario['calendario_nome'] ?? ''),
                'calendario_cor' => (string) ($calendario['calendario_cor'] ?? ''),
                'calendario_cor_texto' => (string) ($calendario['calendario_cor_texto'] ?? ''),
                'grupo_seb_ativo' => true,
            ]);
            return;
        }

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
            return (new AlunoLimiteDiarioService($this->db))->listarParaDashboard($alunoId);
        });

        $muralSvc = new MuralRecadoService($this->db);
        $muralRecadosNaoLidosCount = $this->medirDashboard('mural_count', function () use ($muralSvc, $alunoId, $turmaId) {
            return $muralSvc->contarParaAluno($alunoId, $turmaId, true);
        });

        $muralRecadosNaoVistos = $this->medirDashboard('mural_lista', function () use ($muralSvc, $alunoId, $turmaId) {
            return $muralSvc->listarNaoVistosResumoParaAluno($alunoId, $turmaId, 10);
        });

        $muralRecadosCount = $this->medirDashboard('mural_total', function () use ($muralSvc, $alunoId, $turmaId) {
            return $muralSvc->contarParaAluno($alunoId, $turmaId, false);
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
                    return (new OnlineClass())->listLiveAndUpcomingForAluno($alunoId, $turmaId, 5);
                } catch (\Throwable $e) {
                    error_log('dashboard aulas_online: ' . $e->getMessage());
                    return [];
                }
            });
        }

        $agendaEventos = $this->medirDashboard('agenda', function () use ($alunoId, $turmaId, $agendaAno, $agendaMes) {
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

        $jornadasHabilitadas = LayoutHelper::isModuleEnabled('jornadas');
        $acaoCards = $this->isDashboardAcaoCardsLayout();

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
            $eventos = (new StudentAgendaService($this->db))->getMonthEvents($alunoId, $turmaId, $ano, $mes);
            $this->json([
                'success' => true,
                'month' => $mes,
                'year' => $ano,
                'events' => $eventos,
            ]);
        } catch (\Throwable $e) {
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

            (new DashboardAlunoService($this->db))->invalidarCacheAluno($alunoId, $turmaId);
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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

        $primaryColor = \App\Core\LayoutHelper::get('primary_color', $this->config['school']['colors']['primary'] ?? '#3b82f6');

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

        $primaryColor = \App\Core\LayoutHelper::get('primary_color', $this->config['school']['colors']['primary'] ?? '#3b82f6');

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

    /**
     * Notas ou boletim liberados para o aluno (vis_aluno) ou para o responsável (vis_pais).
     *
     * @return list<array<string,mixed>>
     */
    private function boletinsVisiveisNoPortal(int $alunoId, string $perfil, string $exibir = 'notas'): array
    {
        if ($alunoId <= 0) {
            return [];
        }
        $exibir = $exibir === 'boletim' ? 'boletim' : 'notas';
        try {
            if (!class_exists(\App\Models\System\BoletimConfig::class)) {
                return [];
            }
            $cfg = new \App\Models\System\BoletimConfig();
            $lista = [];
            foreach ($cfg->getGeneratedBoletinsByAluno($alunoId, $perfil, $exibir) as $ev) {
                if (!is_array($ev)) {
                    continue;
                }
                // Complementar/itinerário também entra no portal (ficha + snapshot legado).
                $lista[] = $ev;
            }

            return $lista;
        } catch (\Throwable $e) {
            error_log('Portal de ' . $exibir . ' (' . $perfil . ') aluno #' . $alunoId . ': ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Ficha do boletim oficial, a mesma do detalhe do aluno.
     *
     * @return array{quadro:?array<string,mixed>,fichas:list<array<string,mixed>>,ficha_id:int,observacao:array<string,mixed>}
     */
    private function boletimOficialPortal(int $alunoId, int $fichaId = 0): array
    {
        $vazio = [
            'quadro' => null,
            'fichas' => [],
            'ficha_id' => 0,
            'observacao' => ['conteudo' => '', 'updated_at' => null],
            'quadro_impressao_html' => '',
        ];
        if ($alunoId <= 0) {
            return $vazio;
        }
        try {
            if (!class_exists('LayoutHelper', false)) {
            }
            if (!\App\Core\LayoutHelper::isModuleEnabled('vida_escolar')) {
                return $vazio;
            }
            $vida = new \App\Modulos\VidaEscolar\Services\VidaEscolarService();
            $model = $vida->model();
            if ($model->schemaPronto()) {
                try {
                    $vida->materializarFichaOficialSeFaltar($alunoId);
                } catch (\Throwable $e) {
                    error_log('Portal boletim materializar aluno #' . $alunoId . ': ' . $e->getMessage());
                }
                try {
                    // Regular + itinerário/extra: cria ficha e preenche se ainda estiver vazia.
                    $vida->garantirFichasMatriculasAtivas($alunoId);
                } catch (\Throwable $e) {
                    error_log('Portal boletim fichas matrículas aluno #' . $alunoId . ': ' . $e->getMessage());
                }
            }
            $prontuarioSvc = new \App\Modulos\VidaEscolar\Services\ProntuarioVidaEscolarService();
            $fichas = $model->schemaPronto() ? $model->listarFichasAluno($alunoId) : [];
            try {
                $fichas = $prontuarioSvc->enriquecerFichasComModelo($fichas, $alunoId);
            } catch (\Throwable $e) {
                error_log('Portal boletim enriquecer fichas aluno #' . $alunoId . ': ' . $e->getMessage());
            }
            $fichaIdEscolhida = 0;
            if ($fichaId > 0) {
                foreach ($fichas as $ficha) {
                    if ((int) ($ficha['id'] ?? 0) === $fichaId) {
                        $fichaIdEscolhida = $fichaId;
                        break;
                    }
                }
            }
            // Após ordenar Oficial → Itinerário, a primeira ficha é a oficial.
            if ($fichaIdEscolhida <= 0 && $fichas !== []) {
                $fichaIdEscolhida = (int) ($fichas[0]['id'] ?? 0);
            }
            $observacao = ['conteudo' => '', 'updated_at' => null];
            try {
                $row = (new \App\Models\System\BoletimConfig())->getObservacaoCoordenacao($alunoId);
                if (is_array($row)) {
                    $observacao = $row;
                }
            } catch (\Throwable $e) {
                error_log('Observação do boletim aluno #' . $alunoId . ': ' . $e->getMessage());
            }

            if ($fichaIdEscolhida > 0) {
                $vida->recolherDesdobramentosSePrecisar($fichaIdEscolhida);
            }

            $quadro = $fichaIdEscolhida > 0 ? $vida->quadro($fichaIdEscolhida, true) : null;

            // Grupo Seb: monta o HTML do quadro na request (portal não usa o AJAX do admin).
            $quadroImpressaoHtml = '';
            if (GrupoSebService::estaAtivo() && is_array($quadro)) {
                $fichaAtual = is_array($quadro['ficha'] ?? null) ? $quadro['ficha'] : [];
                if ((int) ($fichaAtual['id'] ?? 0) <= 0 && $fichaIdEscolhida > 0) {
                    $fichaAtual['id'] = $fichaIdEscolhida;
                }
                if ((int) ($fichaAtual['id'] ?? 0) > 0) {
                    try {
                        $quadroImpressaoHtml = $prontuarioSvc->htmlQuadroImpressaoParaAluno($alunoId, $fichaAtual);
                    } catch (\Throwable $e) {
                        error_log('Portal boletim quadro impressão aluno #' . $alunoId . ': ' . $e->getMessage());
                        $quadroImpressaoHtml = '';
                    }
                }
            }

            return [
                'quadro' => $quadro,
                'fichas' => $fichas,
                'ficha_id' => $fichaIdEscolhida,
                'observacao' => $observacao,
                'quadro_impressao_html' => $quadroImpressaoHtml,
            ];
        } catch (\Throwable $e) {
            error_log('Portal de boletim aluno #' . $alunoId . ': ' . $e->getMessage());

            return $vazio;
        }
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
        // Demonstrativo = quadro ao vivo + snapshots já gerados (1º/2º etc.).
        $notasEventosAoVivo = $this->notasEventosAoVivoDoAluno($aluno, []);
        $fichaIdPortal = isset($_GET['ficha_id']) ? (int) $_GET['ficha_id'] : 0;
        $boletimOficial = $this->boletimOficialPortal((int) $aluno['id'], $fichaIdPortal);
        $boletinsPortalOficiais = $this->boletinsVisiveisNoPortal((int) $aluno['id'], 'aluno', 'boletim');
        $boletinsPortalNotas = $this->boletinsVisiveisNoPortal((int) $aluno['id'], 'aluno', 'notas');

        $primaryColor = LayoutHelper::get('primary_color', $this->config['school']['colors']['primary'] ?? '#3b82f6');

        $data = [
            'title' => 'Provas - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'secao_notas' => 'provas',
            'provas_realizadas' => $provasRealizadas,
            'provas_matriz_blocos' => $provasMatrizBlocos,
            'notas_lancamento_eventos' => [],
            'boletins_gerados' => [],
            // Snapshots gerados (exibir_em=notas) — sem isso o 1º/2º ficam cinza se o ao vivo não montar.
            'boletins_gerados_notas' => $boletinsPortalNotas,
            'notas_eventos_ao_vivo' => $notasEventosAoVivo,
            'boletins_gerados_notas_extra' => [],
            'boletins_gerados_boletim' => $boletinsPortalOficiais,
            'boletim_oficial' => $boletimOficial,
            'boletim_url_ficha' => rtrim((string) URL, '/') . '/notas-boletins',
            'boletins_gerados_complementar' => [],
            'boletim_observacao' => $boletimOficial['observacao'],
            'quadro_oficial' => null,
            'paineis_notas' => [],
            'portal_notas_ocultas' => true,
            'primary_color' => $primaryColor,
            'current_page' => 'notas_boletins',
            'csrf_token' => $this->generateCsrfToken(),
        ];

        $this->viewWithLayout('student', 'student/notas-boletins', $data);
    }

    /**
     * Demonstrativo ao vivo: notas lançadas nos eventos.
     * $boletinsVisiveis só filtra se for boletim oficial do período (não snapshot de demonstrativo).
     *
     * @param array<string,mixed> $aluno
     * @param list<array<string,mixed>> $boletinsVisiveis
     * @return list<array<string,mixed>>
     */
    private function notasEventosAoVivoDoAluno(array $aluno, array $boletinsVisiveis): array
    {
        if (!LayoutHelper::isModuleEnabled('eventos_avaliativos')) {
            return [];
        }
        try {
            $periodos = (new \App\Modulos\EventosAvaliativos\Services\EventoAvaliativoService())
                ->notasAoVivoDoAluno((int) ($aluno['id'] ?? 0), (int) ($aluno['turma_id'] ?? 0));
        } catch (\Throwable $e) {
            error_log('Notas ao vivo do aluno #' . (int) ($aluno['id'] ?? 0) . ': ' . $e->getMessage());
            return [];
        }
        if ($boletinsVisiveis === []) {
            return is_array($periodos) ? $periodos : [];
        }
        $fechados = [];
        foreach ($boletinsVisiveis as $boletim) {
            if (!is_array($boletim)) {
                continue;
            }
            // Só snapshot de boletim oficial fecha o ao vivo — demonstrativo (notas) não.
            if (strtolower(trim((string) ($boletim['exibir_em'] ?? 'boletim'))) === 'notas') {
                continue;
            }
            $ano = (int) ($boletim['ano_letivo'] ?? 0);
            $bimestre = (int) ($boletim['bimestre'] ?? 0);
            if ($ano > 0 && $bimestre > 0) {
                $fechados[$ano . ':' . $bimestre] = true;
            }
        }
        if ($fechados === []) {
            return is_array($periodos) ? $periodos : [];
        }
        $abertos = [];
        foreach ($periodos as $periodo) {
            $chave = (int) ($periodo['ano'] ?? 0) . ':' . (int) ($periodo['bimestre'] ?? 0);
            if (empty($fechados[$chave])) {
                $abertos[] = $periodo;
            }
        }
        return $abertos;
    }

    public function notas()
    {
        $this->redirect('/notas-boletins');
    }

    public function boletim()
    {
        $this->redirect('/notas-boletins');
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
            'modulos_opcao_filtro' => \App\Core\CreditosModuleRegistry::getModuleLabels(),
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
        } catch (\Exception $e) {
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
            $voltarErro = (($_GET['from'] ?? '') === 'educashop') ? URL . '/educashop' : \URL . '/carteira';
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
            'voltar_url' => $fromEducashop ? URL . '/educashop' : \URL . '/carteira',
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
        if (LayoutHelper::get('creditos_liberar_b2c', '0') !== '1') {
            $this->setFlashMessage('Planos de assinatura não estão disponíveis para esta escola.', 'info');
            header('Location: ' . URL . '/carteira');
            exit;
        }
        try {
            $planos = $this->db->fetchAll("SELECT id, nome, creditos_mensais, valor_mensal, destino FROM planos_creditos WHERE ativo = 1 AND (destino = 'aluno' OR destino = 'ambos') ORDER BY creditos_mensais");
            $assinaturas = $this->db->fetchAll("SELECT a.id, a.plano_id, a.inicio_em, a.ativa, p.nome as plano_nome, p.creditos_mensais FROM assinaturas_creditos a JOIN planos_creditos p ON p.id = a.plano_id WHERE a.user_type = 'aluno' AND a.user_id = ? ORDER BY a.id DESC", [$aluno['id']]);
        } catch (\Exception $e) {
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

    // Chat Tudinha (chat/createConversation/sendMessage/...): descontinuado — Etapa 7
    // Rotas /chat* → User/ChatTudinhaDescontinuadoController@indisponivel (HTTP 410).
    // vozParaTexto permanece: usado por /aluno/provas/voz-para-texto.

    public function vozParaTexto()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido', 'csrf_token' => $this->refreshCsrfToken()], 400);
        }

        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                throw new \Exception('Acesso negado');
            }

            if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception('Arquivo de áudio inválido');
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
                throw new \Exception('Tipo de áudio não suportado. Formatos aceitos: \MP3, WAV, WebM, OGG, FLAC, M4A');
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
                throw new \Exception('Erro ao preparar gravação de áudio. Tente novamente.');
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
                throw new \Exception('Arquivo de áudio inválido ou muito grande. Tente gravar de novo.');
            }
            if (!move_uploaded_file($tmpName, $filepath)) {
                error_log('vozParaTexto: move_uploaded_file falhou de ' . $tmpName . ' para ' . $filepath);
                throw new \Exception('Erro ao salvar áudio. Tente novamente.');
            }

            // Se o app usa S3 para mídia, enviar cópia do áudio para o S3 (tipo chat)
            $mediaStorage = new MediaStorageService($this->config);
            if ($mediaStorage->isS3()) {
                $s3Key = 'audio/' . $filename;
                $contentType = $audio['type'] ?: 'audio/webm';
                if (!$mediaStorage->put('chat', $s3Key, $filepath, $contentType)) {
                    error_log('vozParaTexto: falha ao enviar áudio para S3, key=' . $s3Key);
                }
            }

            $elevenLabsService = new \App\Services\ElevenLabsService();
            
            $texto = $elevenLabsService->vozParaTexto($filepath);

            // Remover arquivo temporário
            @unlink($filepath);

            $this->json([
                'success' => true,
                'texto' => $texto
            ]);

        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    // createEssay/gerarTemaIA/transcreverImagem/corrigirRedacao → Modulos/redacoes/AlunoRedacaoLegadoController (Etapa 6)

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
            return (new Exam())->countAvailableForStudent($alunoId, $turmaId);
        } catch (\Throwable $e) {
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

        if (LayoutHelper::get('module_redacao_configuravel', '1') !== '1') {
            return 0;
        }
        if (LayoutHelper::get('module_aluno_redacao_configuravel', '1') !== '1') {
            return 0;
        }

        return (new EssayProposal())->countPendingForStudent($alunoId);
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
    
    // Limites diários → AlunoLimiteDiarioService (Etapa 8)

    /**
     * Salvar rascunho de redação da jornada (rotas em Modulos/jornadas/routes.php).
     */
    public function salvarRedacaoJornada()
    {
        try {
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'aluno') {
                if (!class_exists('Logger')) {
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
                }
                Logger::error('jornada_aluno salvarRedacaoJornada: \ID da jornada de redação não fornecido', [
                    'aluno_id' => $user['id'],
                ], 'jornadas');
                $this->json(['success' => false, 'error' => 'ID da jornada de redação não fornecido. Por favor, recarregue a página e tente novamente.'], 400);
                return;
            }
            
            if (empty($conteudo)) {
                if (!class_exists('Logger')) {
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
                } catch (\Exception $e) {
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
                } catch (\Exception $e) {
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
            
        } catch (\Exception $e) {
            if (!class_exists('Logger')) {
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
                $errorMessage = 'Erro no banco de dados: \A coluna tempo_escrita não existe na tabela redacoes. Por favor, execute o script SQL: database/adicionar_tempo_escrita_redacoes.sql no seu banco de dados.';
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
                $log("ERRO: \Acesso negado - user tipo: " . ($user['tipo'] ?? 'null'));
                if (!class_exists('Logger')) {
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
                $log("ERRO: \Token CSRF inválido");
                if (!class_exists('Logger')) {
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
                }
                Logger::error('jornada_aluno finalizarRedacaoJornada: conteúdo vazio', [
                    'aluno_id' => $user['id'],
                    'jornada_redacao_id' => $jornadaRedacaoId,
                    'conteudo_length' => strlen($conteudo),
                ], 'jornadas');
                $_SESSION['error_message'] = 'ERRO: \O conteúdo da redação não pode estar vazio. Por favor, escreva sua redação antes de finalizar.';
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
                } catch (\Exception $e) {
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
                // IMPORTANTE: \Verificar se o conteúdo não está vazio
                if (empty($conteudo) || strlen(trim($conteudo)) == 0) {
                    $log("ERRO: Conteúdo vazio ao finalizar - conteudo_length=" . strlen($conteudo));
                    $_SESSION['error_message'] = 'ERRO: \O conteúdo da redação não pode estar vazio. Por favor, escreva sua redação antes de finalizar.';
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
                } catch (\Exception $e) {
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
                    $log("ERRO CRÍTICO: \Nenhuma linha foi afetada pelo UPDATE! A redação pode não existir ou o ID está incorreto.");
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
                // IMPORTANTE: \Buscar pelo redacao_id apenas, pois pode haver múltiplos vínculos
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
                    // IMPORTANTE: \Atualizar pelo ID do vínculo para garantir que atualiza o correto
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
                        error_log("AVISO: \Nenhuma linha afetada pelo UPDATE pelo ID. Tentando atualizar todos os vínculos da redação...");
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
                            error_log("ERRO: \Status ainda é 'rascunho' após UPDATE. Forçando atualização...");
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
            
        } catch (\Exception $e) {
            $log("EXCEÇÃO: \Erro ao finalizar redação da jornada: " . $e->getMessage());
            $log("Stack trace: " . $e->getTraceAsString());
            error_log("Erro ao finalizar redação da jornada: " . $e->getMessage());
            if (!class_exists('Logger')) {
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
                $errorMessage = 'Erro no banco de dados: \A coluna tempo_escrita não existe na tabela redacoes. Por favor, execute o script SQL: database/adicionar_tempo_escrita_redacoes.sql no seu banco de dados.';
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
    
    private function assertAlunoAtivo(array $aluno)
    {
        $status = strtoupper((string)($aluno['status'] ?? 'ACTIVE'));
        if ((int)($aluno['ativo'] ?? 1) !== 1 || $status !== 'ACTIVE') {
            throw new \Exception('Acesso do aluno está inativo');
        }
    }

}
}

if (!class_exists('StudentController', false) && class_exists(__NAMESPACE__ . '\\StudentController', false)) {
    class_alias(__NAMESPACE__ . '\\StudentController', 'StudentController');
}
