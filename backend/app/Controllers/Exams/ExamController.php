<?php
/**
 * EducaTudo - Controller de Provas Online
 * Gerencia provas online para professores, alunos e administração
 */

require_once __DIR__ . '/../../Models/Exams/Exam.php';
require_once __DIR__ . '/../../Models/Exams/ProvaLogEvento.php';
require_once __DIR__ . '/../../Models/Education/ComponenteCurricular.php';
require_once __DIR__ . '/../../Models/Education/ClassRoom.php';
require_once __DIR__ . '/../../Models/User/Teacher.php';
require_once __DIR__ . '/../../Models/User/Student.php';
require_once __DIR__ . '/../../Models/Education/LessonPlan.php';

if (!class_exists('ExamController')) {
class ExamController extends BaseController
{
    private $auth;
    private $db;
    private $provaModel;
    private $subjectModel;
    private $turmaModel;
    private $teacherModel;
    private $studentModel;
    private $planoAulaModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $this->provaModel = new Exam();
        $this->subjectModel = new ComponenteCurricular();
        $this->turmaModel = new ClassRoom();
        $this->teacherModel = new Teacher();
        $this->studentModel = new Student();
        $this->planoAulaModel = new LessonPlan();
        
        // Log de Provas (Master): endpoint tolerante a sessão quebrada — é usado
        // justamente para reportar quando a sessão caiu, então não pode exigir
        // login válido (senão perdemos exatamente os casos que queremos ver).
        // A validação de identidade (sessão OU token, nunca id "no olho" vindo do
        // corpo) acontece dentro de logEvento(), não aqui.
        // Path EXATO da única rota registrada pra esse método (config/routes/student.php)
        // — não usar sufixo/regex solto aqui: este controller atende professor/admin/aluno,
        // e um match frouxo poderia acabar pulando o login de outra rota por engano.
        $uriAtual = rtrim((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''), '/');
        $isLogEventoRoute = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST'
            && $uriAtual === '/aluno/provas/log-evento';

        // Verifica se está logado
        if (!$isLogEventoRoute && !$this->auth->isLoggedIn()) {
            if ($this->wantsJsonResponse()) {
                $this->registrarLogProva('erro_sessao', [
                    'detalhe' => 'Sessão expirada/inválida em ' . ($_SERVER['REQUEST_URI'] ?? ''),
                ]);
                $this->json(['error' => 'Sessão expirada. Faça login novamente.'], 401);
                return;
            }
            $this->redirect('/');
            return;
        }
        
        $user = $this->auth->getUser();
        // Verifica se é professor, aluno ou admin
        if ($user && !in_array($user['tipo'], ['professor', 'admin', 'admin_escola', 'aluno'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
    }

    /**
     * Registra uma anomalia da tela de prova no Log de Provas (Master).
     * Nunca lança exceção — log não pode derrubar a prova do aluno.
     *
     * @param array<string,mixed> $extra aluno_id, prova_id, bloco_id, detalhe (sobrescreve os automáticos)
     */
    private function registrarLogProva(string $tipoEvento, array $extra = []): void
    {
        try {
            $user = $this->auth->getUser();
            (new ProvaLogEvento())->registrar(array_merge([
                'tipo_evento' => $tipoEvento,
                'aluno_id' => $user && ($user['tipo'] ?? '') === 'aluno' ? $user['id'] : null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ], $extra));
        } catch (Throwable $e) {
            error_log('registrarLogProva falhou: ' . $e->getMessage());
        }
    }

    /**
     * Log de Provas (Master): gera um token opaco (guardado no Redis, TTL curto) amarrando
     * aluno_id/prova_id/bloco_id — emitido enquanto a sessão AINDA está válida (na própria
     * renderização da prova). É o que permite `logEvento()` confiar em quem é o aluno mesmo
     * depois que a sessão já caiu, SEM aceitar um aluno_id arbitrário mandado pelo cliente
     * (isso permitiria um aluno forjar evento de "tentativa de burlar prova" em nome de outro).
     * Se o Redis estiver fora do ar, retorna '' e o evento simplesmente fica sem aluno_id
     * nesse caso raro (nunca um aluno_id não verificado).
     */
    private function gerarTokenLogProva(int $alunoId, int $provaId, int $blocoId): string
    {
        try {
            $token = bin2hex(random_bytes(16));
            $ok = RedisCache::set(
                'log_prova_token_' . $token,
                json_encode(['aluno_id' => $alunoId, 'prova_id' => $provaId, 'bloco_id' => $blocoId], JSON_UNESCAPED_UNICODE),
                14400 // 4h — mais que suficiente para a duração de qualquer prova
            );
            return $ok ? $token : '';
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * @return array{aluno_id:int,prova_id:int,bloco_id:int}|null
     */
    private function resolverTokenLogProva(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        try {
            $raw = RedisCache::get('log_prova_token_' . $token);
            if ($raw === null || $raw === '') {
                return null;
            }
            $dados = json_decode($raw, true);
            if (!is_array($dados)) {
                return null;
            }
            return [
                'aluno_id' => (int) ($dados['aluno_id'] ?? 0),
                'prova_id' => (int) ($dados['prova_id'] ?? 0),
                'bloco_id' => (int) ($dados['bloco_id'] ?? 0),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Log de Provas (Master): recebe eventos de anomalia direto do navegador do aluno
     * durante a prova — tentativa de sair da tela cheia/modo seguro, F5, voltar
     * navegador, etc. Não exige sessão válida (ver __construct: rota tolerante a
     * sessão quebrada, é justamente pra isso que ela existe).
     *
     * IMPORTANTE: como a rota é pública, o aluno_id/prova_id/bloco_id do corpo NUNCA
     * é aceito "no olho": com sessão válida usamos só a identidade da sessão; sem
     * sessão, só aceitamos o que vier amarrado a um token emitido por gerarTokenLogProva()
     * (ver __construct/realizar()) — sem isso, qualquer um poderia forjar um evento de
     * "tentativa de burlar prova" em nome de outro aluno real.
     */
    public function logEvento()
    {
        $input = file_get_contents('php://input');
        $body = is_string($input) ? json_decode($input, true) : null;
        if (!is_array($body)) {
            $body = $_POST;
        }

        $tipoEvento = (string) ($body['tipo_evento'] ?? 'outro');
        if (!in_array($tipoEvento, ProvaLogEvento::TIPOS_VALIDOS, true)) {
            $tipoEvento = 'outro';
        }

        $extra = [
            'aluno_id' => null,
            'prova_id' => null,
            'bloco_id' => null,
            'detalhe' => (string) ($body['detalhe'] ?? ''),
        ];

        $user = $this->auth->getUser();
        if ($user && ($user['tipo'] ?? '') === 'aluno') {
            // Sessão ainda válida: identidade vem só da sessão, nunca do corpo da requisição.
            $extra['aluno_id'] = (int) $user['id'];
            $provaIdBody = !empty($body['prova_id']) ? (int) $body['prova_id'] : null;
            if ($provaIdBody !== null && $this->db->fetch("SELECT id FROM provas WHERE id = :id", ['id' => $provaIdBody])) {
                $extra['prova_id'] = $provaIdBody;
            }
            $extra['bloco_id'] = !empty($body['bloco_id']) ? (int) $body['bloco_id'] : null;
        } else {
            // Sessão já quebrada: só confia em aluno_id/prova_id/bloco_id vindos do token
            // emitido enquanto a sessão ainda era válida. Sem token válido, o evento é
            // gravado mesmo assim (detalhe/tipo/ip/user-agent ainda são úteis pro Master),
            // só que sem identificar aluno/prova — nunca com um id não verificado.
            $resolvido = $this->resolverTokenLogProva((string) ($body['token'] ?? ''));
            if ($resolvido) {
                $extra['aluno_id'] = $resolvido['aluno_id'] > 0 ? $resolvido['aluno_id'] : null;
                $extra['prova_id'] = $resolvido['prova_id'] > 0 ? $resolvido['prova_id'] : null;
                $extra['bloco_id'] = $resolvido['bloco_id'] > 0 ? $resolvido['bloco_id'] : null;
            }
        }

        $this->registrarLogProva($tipoEvento, $extra);

        $this->json(['success' => true]);
    }

    /**
     * POST de API da prova (finalizar / salvar resposta) deve responder JSON, não redirect HTML.
     */
    private function wantsJsonResponse(): bool
    {
        $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
        if (preg_match('#/provas/bloco/\d+/cancelar-seguro#', $uri)) {
            return true;
        }
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return false;
        }
        return (bool) preg_match('#/provas/(finalizar|salvar-resposta|cancelar-seguro)/#', $uri);
    }

    /**
     * Respeita provas_blocos.visivel_no_portal_aluno (evento oculto para o aluno no portal).
     */
    private function alunoPodeVerBlocoNoPortalLinha(array $blocoRow): bool
    {
        static $blocoModel = null;
        if ($blocoModel === null) {
            require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
            $blocoModel = new ExamBlock();
        }
        return $blocoModel->alunoPodeVerBlocoNoPortal($blocoRow);
    }
    
    /**
     * ============================================
     * ROTAS PARA PROFESSORES
     * ============================================
     */
    
    /**
     * Lista provas do professor
     */
    public function index()
    {
        $user = $this->auth->getUser();
        
        if ($user['tipo'] !== 'professor') {
            $this->redirect('/admin/provas');
            return;
        }
        
        // Busca professor
        $professor = $this->teacherModel->findById($user['id']);
        if (!$professor) {
            $this->setFlashMessage('Professor não encontrado', 'error');
            $this->redirect('/professor/dashboard');
            return;
        }
        
        // Busca eventos de prova (igual ao dashboard: uma linha por bloco+matéria, sem duplicar)
        require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
        $blocoModel = new ExamBlock();
        
        // Subquery com GROUP BY bloco_id, materia_id garante UMA linha por (bloco, matéria) do professor
        $eventosRaw = $this->db->fetchAll(
            "SELECT pb.*, 
                    rel.materia_id,
                    rel.materia_nome,
                    COUNT(DISTINCT CASE WHEN p.professor_id = :professor_id_join THEN pbp.prova_id END) as provas_criadas_professor,
                    COUNT(DISTINCT pbp.prova_id) as total_provas_bloco
             FROM provas_blocos pb
             INNER JOIN (
                 SELECT bloco_id, materia_id, MAX(m.nome) AS materia_nome
                 FROM provas_blocos_professores pbp_rel
                 INNER JOIN materias m ON m.id = pbp_rel.materia_id
                 WHERE pbp_rel.professor_id = :professor_id_where
                 GROUP BY bloco_id, materia_id
             ) rel ON rel.bloco_id = pb.id
             LEFT JOIN provas_blocos_vinculo pbp ON pbp.bloco_id = pb.id
             LEFT JOIN provas p ON p.id = pbp.prova_id
             WHERE pb.deleted_at IS NULL
             GROUP BY pb.id, rel.materia_id
             ORDER BY pb.data_prova DESC, pb.hora_inicio DESC, pb.id DESC, rel.materia_id ASC",
            [
                'professor_id_where' => $professor['id'],
                'professor_id_join' => $professor['id']
            ]
        );
        
        // Normaliza DATETIME vazios
        foreach ($eventosRaw as &$ev) {
            foreach (['data_prova', 'hora_inicio', 'hora_fim', 'prazo_entrega_professor'] as $campo) {
                if (isset($ev[$campo]) && trim((string) $ev[$campo]) === '') {
                    $ev[$campo] = null;
                }
            }
        }
        unset($ev);

        $idsFmt = [];
        foreach ($eventosRaw as $er) {
            $idsFmt[(int) ($er['id'] ?? 0)] = true;
        }
        unset($idsFmt[0]);
        $formatoPorBloco = $blocoModel->fetchFormatoEventoPorBlocoIds(array_keys($idsFmt));
        
        // Deduplica por chave numérica (segurança extra) e monta estrutura por evento
        $eventos = [];
        $vistos = [];
        require_once __DIR__ . '/../../Models/Exams/ExamBlockManualGrade.php';
        foreach ($eventosRaw as $ev) {
            $bidFmt = (int) ($ev['id'] ?? 0);
            if ($bidFmt > 0 && isset($formatoPorBloco[$bidFmt])) {
                $ev['formato_evento'] = $formatoPorBloco[$bidFmt];
            }
            $chave = (int)$ev['id'] . '_' . (int)$ev['materia_id'];
            if (isset($vistos[$chave])) {
                continue;
            }
            $vistos[$chave] = true;
            $turmasEvento = $this->db->fetchAll(
                "SELECT DISTINCT t.id, t.nome
                 FROM provas_blocos_professores pbp_rel
                 INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp_rel.id
                 INNER JOIN turmas t ON t.id = pbpt.turma_id
                 WHERE pbp_rel.bloco_id = :bloco_id
                   AND pbp_rel.professor_id = :professor_id
                   AND pbp_rel.materia_id = :materia_id
                 ORDER BY t.nome ASC",
                [
                    'bloco_id' => $ev['id'],
                    'professor_id' => $professor['id'],
                    'materia_id' => $ev['materia_id'],
                ]
            );
            if (empty($turmasEvento)) {
                $turmasEvento = $this->db->fetchAll(
                    "SELECT DISTINCT t.id, t.nome FROM turmas t
                     INNER JOIN provas_blocos_turmas pbt ON t.id = pbt.turma_id
                     WHERE pbt.bloco_id = :bloco_id ORDER BY t.nome ASC",
                    ['bloco_id' => $ev['id']]
                );
            }
            $provaExistente = $this->db->fetch(
                "SELECT p.id, p.titulo, p.status FROM provas p
                 INNER JOIN provas_blocos_vinculo pbp ON p.id = pbp.prova_id
                 WHERE pbp.bloco_id = :bloco_id AND p.professor_id = :professor_id AND p.materia_id = :materia_id AND p.deleted_at IS NULL LIMIT 1",
                ['bloco_id' => $ev['id'], 'professor_id' => $professor['id'], 'materia_id' => $ev['materia_id']]
            );
            // Quando a prova já foi aprovada pela coordenação, não exibe mais no card de eventos.
            // Assim ela passa a aparecer apenas na lista de provas abaixo.
            if (!empty($provaExistente) && (($provaExistente['status'] ?? '') === 'aprovada')) {
                continue;
            }
            $formatoEvento = $ev['formato_evento'] ?? 'online_questoes';
            $lancamentoPreenchidas = 0;
            $lancamentoTotalAlunos = 0;
            if ($formatoEvento === 'lancamento_nota') {
                $turmasProfRows = $this->db->fetchAll(
                    'SELECT pbpt.turma_id FROM provas_blocos_professores pbp
                     INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp.id
                     WHERE pbp.bloco_id = :bloco_id AND pbp.professor_id = :professor_id AND pbp.materia_id = :materia_id',
                    [
                        'bloco_id' => $ev['id'],
                        'professor_id' => $professor['id'],
                        'materia_id' => $ev['materia_id'],
                    ]
                );
                $tidsL = array_values(array_filter(array_map('intval', array_column($turmasProfRows ?: [], 'turma_id'))));
                $alunosL = !empty($tidsL) ? $this->turmaModel->getAlunosByTurmasIds($tidsL) : [];
                $uniqAlunos = [];
                foreach ($alunosL as $al) {
                    $uniqAlunos[(int) ($al['id'] ?? 0)] = true;
                }
                $lancamentoTotalAlunos = count(array_filter(array_keys($uniqAlunos)));
                $notasMg = new ExamBlockManualGrade();
                $lancamentoPreenchidas = $notasMg->countComNota((int) $ev['id'], (int) $professor['id'], (int) $ev['materia_id']);
            }
            $eventos[] = [
                'id' => $ev['id'],
                'titulo' => $ev['titulo'],
                'descricao' => $ev['descricao'] ?? null,
                'tipo_prova' => $ev['tipo_prova'] ?? 'original',
                'formato_evento' => $formatoEvento,
                'data_prova' => $ev['data_prova'],
                'hora_inicio' => $ev['hora_inicio'],
                'hora_fim' => $ev['hora_fim'],
                'prazo_entrega_professor' => $ev['prazo_entrega_professor'] ?? null,
                'configuracao_nota' => $ev['configuracao_nota'] ?? 'coordenacao_calcula',
                'liberar_gabarito' => $ev['liberar_gabarito'] ?? 'imediatamente',
                'materia_id' => $ev['materia_id'],
                'materia_nome' => $ev['materia_nome'],
                'provas_criadas_professor' => (int)($ev['provas_criadas_professor'] ?? 0),
                'lancamento_notas_preenchidas' => $lancamentoPreenchidas,
                'lancamento_total_alunos' => $lancamentoTotalAlunos,
                'turmas' => $turmasEvento,
                'prova_existente' => $provaExistente,
                'prova_existente_id' => $provaExistente['id'] ?? null,
            ];
        }
        
        // Busca provas do professor (não vinculadas a eventos)
        $provas = $this->provaModel->findByProfessor($professor['id']);
        
        // Remove provas que estão em eventos (qualquer status: rascunho, agendada, enviada, etc.)
        $blocoIds = array_values(array_map('intval', array_unique(array_column($eventos, 'id'))));
        $provasIdsEmEventos = [];
        if (!empty($blocoIds)) {
            $blocoParams = [];
            $blocoPlaceholders = [];
            foreach ($blocoIds as $idx => $blocoId) {
                $param = 'bloco_id_' . $idx;
                $blocoPlaceholders[] = ':' . $param;
                $blocoParams[$param] = $blocoId;
            }
            $vinculos = $this->db->fetchAll(
                "SELECT pbp.prova_id FROM provas_blocos_vinculo pbp
                 INNER JOIN provas p ON p.id = pbp.prova_id AND p.deleted_at IS NULL
                 WHERE pbp.bloco_id IN (" . implode(',', $blocoPlaceholders) . ")",
                $blocoParams
            );
            $provasIdsEmEventos = array_unique(array_column($vinculos, 'prova_id'));
        }
        
        $provas = array_filter($provas, function($prova) use ($provasIdsEmEventos) {
            return !in_array($prova['id'], $provasIdsEmEventos);
        });
        $provas = array_values($provas);

        // Resumo de resultados por prova (somente provas do professor já filtradas acima)
        $resumoPorProva = [];
        $provaIds = array_values(array_map('intval', array_column($provas, 'id')));
        if (!empty($provaIds)) {
            $provaParams = [];
            $provaPlaceholders = [];
            foreach ($provaIds as $idx => $provaId) {
                $param = 'prova_id_' . $idx;
                $provaPlaceholders[] = ':' . $param;
                $provaParams[$param] = $provaId;
            }
            $resumos = $this->db->fetchAll(
                "SELECT 
                    prova_id,
                    COUNT(DISTINCT aluno_id) AS total_alunos,
                    COUNT(DISTINCT CASE WHEN status = 'finalizado' THEN aluno_id END) AS total_finalizados,
                    AVG(CASE WHEN status = 'finalizado' THEN nota END) AS nota_media
                 FROM provas_realizacoes
                 WHERE prova_id IN (" . implode(',', $provaPlaceholders) . ")
                 GROUP BY prova_id",
                $provaParams
            );

            foreach ($resumos as $resumo) {
                $resumoPorProva[(int)$resumo['prova_id']] = [
                    'total_alunos' => (int)($resumo['total_alunos'] ?? 0),
                    'total_finalizados' => (int)($resumo['total_finalizados'] ?? 0),
                    'nota_media' => isset($resumo['nota_media']) ? (float)$resumo['nota_media'] : null,
                ];
            }
        }
        
        // Adiciona status formatado para cada prova
        foreach ($provas as &$prova) {
            $prova['status_formatado'] = $this->getStatusFormatadoProva($prova);
            $resumo = $resumoPorProva[(int)$prova['id']] ?? null;
            $prova['resultado_resumo'] = [
                'total_alunos' => $resumo['total_alunos'] ?? 0,
                'total_finalizados' => $resumo['total_finalizados'] ?? 0,
                'nota_media' => $resumo['nota_media'] ?? null,
            ];
        }
        unset($prova);
        
        // Estatísticas
        $totalEventos = count($eventos);
        $stats = [
            'total' => count($provas),
            'liberadas' => count(array_filter($provas, fn($p) => $p['liberada'] == 1)),
            'bloqueadas' => count(array_filter($provas, fn($p) => $p['liberada'] == 0)),
            'ativas' => count(array_filter($provas, fn($p) => $p['ativo'] == 1)),
            'total_eventos' => $totalEventos
        ];
        
        $data = [
            'title' => 'Minhas Provas - EducaTudo',
            'user' => $user,
            'professor' => $professor,
            'eventos' => $eventos,
            'provas' => $provas,
            'stats' => $stats,
            'current_page' => 'provas',
        ];
        
        $this->viewWithLayout('professor', 'teacher/exams/index', $data);
    }

    public function provasBimestrais()
    {
        $user = $this->auth->getUser();
        if (($user['tipo'] ?? '') !== 'professor') {
            $this->redirect('/admin/provas');
            return;
        }

        $professor = $this->teacherModel->findById($user['id']);
        if (!$professor) {
            $this->setFlashMessage('Professor não encontrado', 'error');
            $this->redirect('/professor/dashboard');
            return;
        }

        $filters = [
            'busca' => trim((string) ($_GET['busca'] ?? '')),
            'turma_id' => max(0, (int) ($_GET['turma_id'] ?? 0)),
            'ano' => max(0, (int) ($_GET['ano'] ?? 0)),
        ];

        $provas = $this->buscarProvasBimestraisProfessor((int) $professor['id'], $filters);
        $turmas = $this->db->fetchAll("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome ASC");
        $anos = $this->db->fetchAll(
            "SELECT DISTINCT YEAR(data_inicio) AS ano
             FROM provas
             WHERE professor_id = :professor_id
               AND deleted_at IS NULL
               AND data_inicio IS NOT NULL
             ORDER BY ano DESC",
            ['professor_id' => (int) $professor['id']]
        );

        $this->viewWithLayout('professor', 'teacher/exams/bimestral', [
            'title' => 'Provas Bimestrais - EducaTudo',
            'user' => $user,
            'professor' => $professor,
            'filters' => $filters,
            'provas' => $provas,
            'turmas' => $turmas,
            'anos' => $anos,
            'current_page' => 'provas_bimestral',
        ]);
    }

    public function baixarProvasBimestrais()
    {
        $user = $this->auth->getUser();
        if (($user['tipo'] ?? '') !== 'professor') {
            http_response_code(403);
            exit('Sem permissão');
        }

        $professor = $this->teacherModel->findById($user['id']);
        if (!$professor) {
            http_response_code(404);
            exit('Professor não encontrado');
        }

        $ids = $_POST['provas'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));

        if (empty($ids)) {
            $this->setFlashMessage('Selecione ao menos uma prova para baixar.', 'error');
            $this->redirect('/professor/provas-bimestral');
            return;
        }

        $provas = $this->buscarProvasPorIdsProfessor((int) $professor['id'], $ids);
        if (empty($provas)) {
            $this->setFlashMessage('Nenhuma prova válida foi encontrada para exportação.', 'error');
            $this->redirect('/professor/provas-bimestral');
            return;
        }

        foreach ($provas as &$prova) {
            $questoes = $this->provaModel->getQuestoes((int) $prova['id']);
            foreach ($questoes as &$q) {
                if (($q['tipo'] ?? '') === 'multipla_escolha') {
                    $q['alternativas'] = $this->provaModel->getAlternativas((int) $q['id']);
                }
            }
            unset($q);
            $this->embedQuestoesImagensForPdf($questoes);
            $prova['questoes'] = $questoes;
        }
        unset($prova);

        $html = $this->renderPdfTemplateProvasBimestrais($provas, (string) ($professor['nome'] ?? 'Professor'));
        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');

        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $filename = 'provas_bimestrais_' . date('Ymd_His') . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $dompdf->output();
            exit;
        } finally {
            ini_set('display_errors', (string) $oldDisplayErrors);
        }
    }

    private function buscarProvasBimestraisProfessor(int $professorId, array $filters = []): array
    {
        $sql = "SELECT p.*,
                       m.nome AS materia_nome,
                       t.nome AS turma_principal_nome,
                       YEAR(p.data_inicio) AS ano_referencia,
                       COALESCE(NULLIF(t.nome, ''), turmas_rel.nomes, 'Todas as turmas') AS turmas_exibicao
                FROM provas p
                LEFT JOIN materias m ON m.id = p.materia_id
                LEFT JOIN turmas t ON t.id = p.turma_id
                LEFT JOIN (
                    SELECT pt.prova_id,
                           GROUP_CONCAT(DISTINCT t2.nome ORDER BY t2.nome SEPARATOR ', ') AS nomes
                    FROM provas_turmas pt
                    INNER JOIN turmas t2 ON t2.id = pt.turma_id
                    GROUP BY pt.prova_id
                ) turmas_rel ON turmas_rel.prova_id = p.id
                WHERE p.professor_id = :professor_id
                  AND p.deleted_at IS NULL
                  AND (p.status IS NULL OR p.status <> 'reprovada')";
        $params = ['professor_id' => $professorId];

        if (!empty($filters['busca'])) {
            $sql .= " AND (p.titulo LIKE :busca OR m.nome LIKE :busca OR COALESCE(t.nome, turmas_rel.nomes, '') LIKE :busca)";
            $params['busca'] = '%' . $filters['busca'] . '%';
        }
        if (!empty($filters['ano'])) {
            $sql .= " AND YEAR(p.data_inicio) = :ano";
            $params['ano'] = (int) $filters['ano'];
        }
        if (!empty($filters['turma_id'])) {
            $sql .= " AND (
                        p.turma_id = :turma_id
                        OR EXISTS (
                            SELECT 1
                            FROM provas_turmas ptf
                            WHERE ptf.prova_id = p.id
                              AND ptf.turma_id = :turma_id
                        )
                    )";
            $params['turma_id'] = (int) $filters['turma_id'];
        }

        $sql .= " ORDER BY p.data_inicio DESC, p.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    private function buscarProvasPorIdsProfessor(int $professorId, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = [];
        $params = ['professor_id' => $professorId];
        foreach ($ids as $idx => $id) {
            $key = 'id_' . $idx;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $id;
        }

        $sql = "SELECT p.*,
                       m.nome AS materia_nome,
                       pr.nome AS professor_nome,
                       COALESCE(NULLIF(t.nome, ''), turmas_rel.nomes, 'Todas as turmas') AS turmas_exibicao
                FROM provas p
                LEFT JOIN materias m ON m.id = p.materia_id
                LEFT JOIN professores pr ON pr.id = p.professor_id
                LEFT JOIN turmas t ON t.id = p.turma_id
                LEFT JOIN (
                    SELECT pt.prova_id,
                           GROUP_CONCAT(DISTINCT t2.nome ORDER BY t2.nome SEPARATOR ', ') AS nomes
                    FROM provas_turmas pt
                    INNER JOIN turmas t2 ON t2.id = pt.turma_id
                    GROUP BY pt.prova_id
                ) turmas_rel ON turmas_rel.prova_id = p.id
                WHERE p.professor_id = :professor_id
                  AND p.deleted_at IS NULL
                  AND p.id IN (" . implode(',', $placeholders) . ")
                ORDER BY p.data_inicio DESC, p.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Determina o status formatado da prova baseado nos 4 status definidos:
     * 1. Professor envia para aprovação → "Não avaliada" (status = 'aguardando_aprovacao')
     * 2. Coordenação Aprova → "Aprovado" (status = 'aprovada')
     * 3. Aluno fazendo a prova → "Em Andamento" (status = 'aprovada' + liberada + aluno iniciado)
     * 4. Finalizou a prova → "Concluído" (status = 'aprovada' + liberada + todos finalizados)
     */
    private function getStatusFormatadoProva($prova) {
        $provaId = $prova['id'] ?? null;
        
        // Busca status DIRETAMENTE do banco para garantir dados atualizados
        if (!$provaId) {
            return [
                'texto' => 'Não avaliada',
                'classe' => 'bg-gray-100 text-gray-600',
                'valor' => 'nao_avaliada'
            ];
        }
        
        $provaBanco = $this->db->fetch(
            "SELECT status, liberada FROM provas WHERE id = :id",
            ['id' => $provaId]
        );
        
        if (!$provaBanco) {
            return [
                'texto' => 'Não avaliada',
                'classe' => 'bg-gray-100 text-gray-600',
                'valor' => 'nao_avaliada'
            ];
        }
        
        $statusBanco = $provaBanco['status'] ?? '';
        $liberadaBanco = (int)($provaBanco['liberada'] ?? 0);
        
        // STATUS 1: Enviada para aprovação
        if ($statusBanco === 'enviada' || $statusBanco === 'aguardando_aprovacao') {
            return [
                'texto' => 'Aguardando Aprovação',
                'classe' => 'bg-yellow-100 text-yellow-800',
                'valor' => 'enviada'
            ];
        }
        // STATUS: Prova excluída (ex-reprovada)
        if ($statusBanco === 'reprovada') {
            return [
                'texto' => 'Prova excluída',
                'classe' => 'bg-red-100 text-red-800',
                'valor' => 'reprovada'
            ];
        }
        // STATUS: Pendente ou agendada (vinculada ao bloco, aguardando professor enviar à coordenação)
        if ($statusBanco === 'pendente' || $statusBanco === 'agendada') {
            return [
                'texto' => 'Aguardando envio pelo professor',
                'classe' => 'bg-amber-100 text-amber-800',
                'valor' => 'pendente'
            ];
        }
        // STATUS 2, 3 ou 4: Aprovada
        elseif ($statusBanco === 'aprovada') {
            // Se está liberada, verifica se há alunos fazendo ou se todos terminaram
            if ($liberadaBanco == 1) {
                // Verifica alunos fazendo (status = 'iniciado')
                $alunosFazendo = $this->db->fetch(
                    "SELECT COUNT(*) as total FROM provas_realizacoes 
                     WHERE prova_id = :prova_id AND status = 'iniciado'",
                    ['prova_id' => $provaId]
                );
                
                // Verifica se todos finalizaram
                $totalAlunos = $this->db->fetch(
                    "SELECT COUNT(DISTINCT aluno_id) as total FROM provas_realizacoes 
                     WHERE prova_id = :prova_id",
                    ['prova_id' => $provaId]
                );
                $alunosFinalizados = $this->db->fetch(
                    "SELECT COUNT(DISTINCT aluno_id) as total FROM provas_realizacoes 
                     WHERE prova_id = :prova_id AND status = 'finalizado'",
                    ['prova_id' => $provaId]
                );
                
                $total = (int)($totalAlunos['total'] ?? 0);
                $fazendo = (int)($alunosFazendo['total'] ?? 0);
                $finalizados = (int)($alunosFinalizados['total'] ?? 0);
                
                // STATUS 3: Em Andamento (há alunos fazendo)
                if ($fazendo > 0) {
                    return [
                        'texto' => 'Em Andamento',
                        'classe' => 'bg-blue-100 text-blue-800',
                        'valor' => 'em_andamento'
                    ];
                }
                // STATUS 4: Concluído (todos finalizaram)
                elseif ($total > 0 && $total === $finalizados) {
                    return [
                        'texto' => 'Concluído',
                        'classe' => 'bg-purple-100 text-purple-800',
                        'valor' => 'concluido'
                    ];
                }
                // STATUS 2: Aprovado (liberada mas ninguém começou ainda)
                else {
                    return [
                        'texto' => 'Aprovado',
                        'classe' => 'bg-green-100 text-green-800',
                        'valor' => 'aprovado'
                    ];
                }
            } else {
                // STATUS 2: Aprovado (mas não liberada ainda)
                return [
                    'texto' => 'Aprovado',
                    'classe' => 'bg-green-100 text-green-800',
                    'valor' => 'aprovado'
                ];
            }
        }
        // Outros status → Não avaliada
        else {
            return [
                'texto' => 'Não avaliada',
                'classe' => 'bg-gray-100 text-gray-600',
                'valor' => 'nao_avaliada'
            ];
        }
    }

    /**
     * Valida se professor pode modificar prova.
     * Após aprovação da coordenação, professor não pode mais editar/excluir.
     */
    private function validarPermissaoProfessorModificarProva($prova, $professorId, $acao = 'modificar')
    {
        if (!$prova) {
            throw new Exception('Prova não encontrada');
        }

        if ((int)$prova['professor_id'] !== (int)$professorId) {
            throw new Exception('Você não tem permissão para ' . $acao . ' esta prova');
        }

        if (($prova['status'] ?? '') === 'aprovada') {
            throw new Exception('Esta prova foi aprovada pela coordenação e não pode mais ser alterada pelo professor.');
        }
    }
    
    /**
     * Formulário de criação de prova
     * @param int|null $evento_id ID do evento de prova (opcional)
     */
    public function criar($evento_id = null)
    {
        $user = $this->auth->getUser();
        
        if ($user['tipo'] !== 'professor') {
            $this->redirect('/admin/provas');
            return;
        }
        
        // Busca professor
        $professor = $this->teacherModel->findById($user['id']);
        if (!$professor) {
            $this->setFlashMessage('Professor não encontrado', 'error');
            $this->redirect('/professor/dashboard');
            return;
        }
        
        $evento = null;
        if ($evento_id) {
            // Busca evento
            require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
            $blocoModel = new ExamBlock();
            $evento = $blocoModel->findById($evento_id);
            
            if (!$evento) {
                $this->setFlashMessage('Evento não encontrado', 'error');
                $this->redirect('/professor/provas');
                return;
            }
            $fmtMap = $blocoModel->fetchFormatoEventoPorBlocoIds([(int) $evento_id]);
            if (isset($fmtMap[(int) $evento_id])) {
                $evento['formato_evento'] = $fmtMap[(int) $evento_id];
            }
            
            // Verifica se o professor está vinculado ao evento
            $professorNoEvento = false;
            $materiaEvento = null;
            $turmasEvento = [];
            $materiaIdRequest = $_GET['materia_id'] ?? null;
            
            if (!empty($evento['professores'])) {
                foreach ($evento['professores'] as $profEvento) {
                    if ($profEvento['professor_id'] == $professor['id']) {
                        // Se foi especificada uma matéria na URL, verifica se é essa
                        if ($materiaIdRequest && $profEvento['materia_id'] != $materiaIdRequest) {
                            continue;
                        }
                        
                        $professorNoEvento = true;
                        $materiaEvento = $this->subjectModel->findById($profEvento['materia_id']);
                        $turmasEvento = $profEvento['turmas'] ?? [];
                        
                        // Se foi especificada uma matéria, usa apenas essa
                        if ($materiaIdRequest) {
                            break;
                        }
                    }
                }
            }
            
            if (!$professorNoEvento) {
                $this->setFlashMessage('Você não tem permissão para criar prova neste evento', 'error');
                $this->redirect('/professor/provas');
                return;
            }

            $materiaIdParaBusca = $materiaIdRequest ? (int) $materiaIdRequest : (int) ($materiaEvento['id'] ?? 0);
            $formatoEvento = $evento['formato_evento'] ?? 'online_questoes';
            if ($formatoEvento === 'lancamento_nota' && $materiaIdParaBusca > 0) {
                $this->redirect('/professor/provas/evento-lancar-notas/' . (int) $evento_id . '?materia_id=' . $materiaIdParaBusca);
                return;
            }
            
            // Evita duplicação: se já existe prova deste professor nesta matéria neste bloco, redireciona para editar
            if ($materiaIdParaBusca > 0) {
                $provaExistente = $this->db->fetch(
                    "SELECT p.id FROM provas p
                     INNER JOIN provas_blocos_vinculo pbp ON p.id = pbp.prova_id
                     WHERE pbp.bloco_id = :bloco_id AND p.professor_id = :professor_id AND p.materia_id = :materia_id AND p.deleted_at IS NULL
                     LIMIT 1",
                    ['bloco_id' => $evento_id, 'professor_id' => $professor['id'], 'materia_id' => $materiaIdParaBusca]
                );
                if ($provaExistente && !empty($provaExistente['id'])) {
                    $this->redirect('/professor/provas/editar/' . (int)$provaExistente['id']);
                    return;
                }
            }
            
            // Busca matéria do evento para este professor
            $materias = [];
            if ($materiaEvento) {
                $materias = [$materiaEvento];
            }
            
            // Busca turmas do evento para este professor
            $turmas = $turmasEvento;
        } else {
            // Busca matérias do professor
            $materias_professor = json_decode($professor['materias'], true) ?: [];
            
            if (empty($materias_professor)) {
                $materias = [];
            } else {
                $placeholders = str_repeat('?,', count($materias_professor) - 1) . '?';
                $materias = $this->db->fetchAll(
                    "SELECT * FROM materias WHERE nome IN ($placeholders) ORDER BY nome ASC",
                    $materias_professor
                );
            }
            
            // Busca turmas do professor
            $turmas_professor = json_decode($professor['turmas'], true) ?: [];
            
            if (empty($turmas_professor)) {
                $turmas = [];
            } else {
                $placeholders = str_repeat('?,', count($turmas_professor) - 1) . '?';
                $turmas = $this->db->fetchAll(
                    "SELECT * FROM turmas WHERE id IN ($placeholders) ORDER BY nome",
                    $turmas_professor
                );
            }
        }
        
        $data = [
            'title' => $evento ? 'Criar Prova no Evento - EducaTudo' : 'Criar Prova - EducaTudo',
            'user' => $user,
            'professor' => $professor,
            'evento' => $evento,
            'materias' => $materias,
            'turmas' => $turmas,
            'prova' => null,
            'current_page' => 'provas'
        ];
        
        $this->viewWithLayout('professor', 'teacher/exams/create', $data);
    }

    /**
     * Evento em formato "lançamento de nota": lista alunos por turma para o professor informar notas (0 a 10).
     */
    public function eventoLancarNotas($evento_id)
    {
        $user = $this->auth->getUser();
        if ($user['tipo'] !== 'professor') {
            $this->redirect('/admin/provas');
            return;
        }
        $professor = $this->teacherModel->findById($user['id']);
        if (!$professor) {
            $this->setFlashMessage('Professor não encontrado', 'error');
            $this->redirect('/professor/dashboard');
            return;
        }
        $materiaId = (int) ($_GET['materia_id'] ?? 0);
        if ($materiaId <= 0) {
            $this->setFlashMessage('Matéria não informada.', 'error');
            $this->redirect('/professor/provas');
            return;
        }
        require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
        require_once __DIR__ . '/../../Models/Exams/ExamBlockManualGrade.php';
        $blocoModel = new ExamBlock();
        $evento = $blocoModel->findById((int) $evento_id);
        if (!$evento) {
            $this->setFlashMessage('Evento não encontrado', 'error');
            $this->redirect('/professor/provas');
            return;
        }
        $fmtMap = $blocoModel->fetchFormatoEventoPorBlocoIds([(int) $evento_id]);
        if (isset($fmtMap[(int) $evento_id])) {
            $evento['formato_evento'] = $fmtMap[(int) $evento_id];
        }
        if (($evento['formato_evento'] ?? 'online_questoes') !== 'lancamento_nota') {
            $this->setFlashMessage('Este evento não está no modo lançamento de notas.', 'error');
            $this->redirect('/professor/provas/criar/evento/' . (int) $evento_id . '?materia_id=' . $materiaId);
            return;
        }
        if (($evento['configuracao_nota'] ?? '') === 'coordenacao_calcula') {
            $this->setFlashMessage('Neste evento, somente a coordenação lança as notas.', 'error');
            $this->redirect('/professor/provas');
            return;
        }
        $turmasIds = [];
        $ok = false;
        foreach ($evento['professores'] ?? [] as $pe) {
            if ((int) ($pe['professor_id'] ?? 0) !== (int) $professor['id']) {
                continue;
            }
            if ((int) ($pe['materia_id'] ?? 0) !== $materiaId) {
                continue;
            }
            $ok = true;
            foreach ($pe['turmas'] ?? [] as $t) {
                $tid = is_array($t) ? (int) ($t['id'] ?? 0) : (int) $t;
                if ($tid > 0) {
                    $turmasIds[$tid] = true;
                }
            }
            break;
        }
        if (!$ok || empty($turmasIds)) {
            $this->setFlashMessage('Você não tem permissão para lançar notas neste evento.', 'error');
            $this->redirect('/professor/provas');
            return;
        }
        $turmaIdList = array_keys($turmasIds);
        $alunosFlat = $this->turmaModel->getAlunosByTurmasIds($turmaIdList);
        $porTurma = [];
        foreach ($alunosFlat as $a) {
            $tid = (int) ($a['turma_id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            if (!isset($porTurma[$tid])) {
                $porTurma[$tid] = [
                    'turma_id' => $tid,
                    'turma_nome' => (string) ($a['turma_nome'] ?? ''),
                    'alunos' => [],
                ];
            }
            $porTurma[$tid]['alunos'][] = $a;
        }
        ksort($porTurma);
        $notasModel = new ExamBlockManualGrade();
        $notasMap = $notasModel->fetchMap((int) $evento_id, (int) $professor['id'], $materiaId);
        $materia = $this->subjectModel->findById($materiaId);

        $this->viewWithLayout('professor', 'teacher/exams/evento_lancar_notas', [
            'title' => 'Lançar notas — ' . ($evento['titulo'] ?? 'Evento'),
            'user' => $user,
            'current_page' => 'provas',
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
            'evento' => $evento,
            'materia' => $materia,
            'turmas_com_alunos' => array_values($porTurma),
            'notas_map' => $notasMap,
        ]);
    }

    /**
     * Grava notas do evento (modo lançamento).
     */
    public function eventoLancarNotasSalvar($evento_id)
    {
        $user = $this->auth->getUser();
        if ($user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página.', 'error');
            $this->redirect('/professor/provas');
            return;
        }
        $professor = $this->teacherModel->findById($user['id']);
        if (!$professor) {
            $this->setFlashMessage('Professor não encontrado', 'error');
            $this->redirect('/professor/dashboard');
            return;
        }
        $materiaId = (int) ($_POST['materia_id'] ?? 0);
        if ($materiaId <= 0) {
            $this->setFlashMessage('Matéria inválida.', 'error');
            $this->redirect('/professor/provas');
            return;
        }
        require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
        require_once __DIR__ . '/../../Models/Exams/ExamBlockManualGrade.php';
        $blocoModel = new ExamBlock();
        $evento = $blocoModel->findById((int) $evento_id);
        if ($evento) {
            $fmtMap = $blocoModel->fetchFormatoEventoPorBlocoIds([(int) $evento_id]);
            if (isset($fmtMap[(int) $evento_id])) {
                $evento['formato_evento'] = $fmtMap[(int) $evento_id];
            }
        }
        if (!$evento || (($evento['formato_evento'] ?? '') !== 'lancamento_nota')) {
            $this->setFlashMessage('Evento inválido.', 'error');
            $this->redirect('/professor/provas');
            return;
        }
        if (($evento['configuracao_nota'] ?? '') === 'coordenacao_calcula') {
            $this->setFlashMessage('Neste evento, somente a coordenação lança as notas.', 'error');
            $this->redirect('/professor/provas');
            return;
        }
        $turmasPermitidas = [];
        $ok = false;
        foreach ($evento['professores'] ?? [] as $pe) {
            if ((int) ($pe['professor_id'] ?? 0) !== (int) $professor['id'] || (int) ($pe['materia_id'] ?? 0) !== $materiaId) {
                continue;
            }
            $ok = true;
            foreach ($pe['turmas'] ?? [] as $t) {
                $tid = is_array($t) ? (int) ($t['id'] ?? 0) : (int) $t;
                if ($tid > 0) {
                    $turmasPermitidas[$tid] = true;
                }
            }
            break;
        }
        if (!$ok) {
            $this->setFlashMessage('Sem permissão.', 'error');
            $this->redirect('/professor/provas');
            return;
        }
        $alunosPermitidos = [];
        $alunosFlat = $this->turmaModel->getAlunosByTurmasIds(array_keys($turmasPermitidas));
        foreach ($alunosFlat as $a) {
            $tid = (int) ($a['turma_id'] ?? 0);
            $aid = (int) ($a['id'] ?? 0);
            if ($tid > 0 && $aid > 0 && isset($turmasPermitidas[$tid])) {
                $alunosPermitidos[$tid . '_' . $aid] = ['turma_id' => $tid, 'aluno_id' => $aid];
            }
        }
        $notasPost = $_POST['notas'] ?? [];
        $obsPost = $_POST['observacoes'] ?? [];
        if (!is_array($notasPost)) {
            $notasPost = [];
        }
        if (!is_array($obsPost)) {
            $obsPost = [];
        }
        $linhas = [];
        foreach ($alunosPermitidos as $pair) {
            $turmaId = (int) $pair['turma_id'];
            $alunoId = (int) $pair['aluno_id'];
            $valorRaw = $notasPost[$turmaId][$alunoId] ?? '';
            $obs = '';
            if (isset($obsPost[$turmaId][$alunoId])) {
                $obs = substr((string) $obsPost[$turmaId][$alunoId], 0, 500);
            }
            $valorStr = is_string($valorRaw) ? trim(str_replace(',', '.', $valorRaw)) : '';
            $notaVal = null;
            if ($valorStr !== '') {
                if (!is_numeric($valorStr)) {
                    $this->setFlashMessage('Nota inválida para um ou mais alunos.', 'error');
                    $this->redirect('/professor/provas/evento-lancar-notas/' . (int) $evento_id . '?materia_id=' . $materiaId);
                    return;
                }
                $nf = (float) $valorStr;
                if ($nf < 0 || $nf > 10) {
                    $this->setFlashMessage('Notas devem estar entre 0 e 10.', 'error');
                    $this->redirect('/professor/provas/evento-lancar-notas/' . (int) $evento_id . '?materia_id=' . $materiaId);
                    return;
                }
                $notaVal = round($nf, 2);
            }
            $linhas[] = [
                'turma_id' => $turmaId,
                'aluno_id' => $alunoId,
                'nota' => $notaVal,
                'observacao' => $obs,
            ];
        }
        $notasModel = new ExamBlockManualGrade();
        if (!$notasModel->tableExists()) {
            $this->setFlashMessage('Tabela de notas ainda não existe. Execute a migração SQL no banco.', 'error');
            $this->redirect('/professor/provas/evento-lancar-notas/' . (int) $evento_id . '?materia_id=' . $materiaId);
            return;
        }
        $notaUnicaTodasMaterias = !empty($evento['nota_unica_todas_materias']);
        if (!$notaUnicaTodasMaterias) {
            $notasModel->upsertLinhas((int) $evento_id, (int) $professor['id'], $materiaId, $linhas);
            $this->setFlashMessage('Notas salvas com sucesso.', 'success');
            $this->redirect('/professor/provas/evento-lancar-notas/' . (int) $evento_id . '?materia_id=' . $materiaId);
            return;
        }

        // ENAC/nota única: replica a nota lançada por aluno para todas as matérias do professor no evento.
        $mapPorAluno = [];
        foreach ($linhas as $ln) {
            $aid = (int) ($ln['aluno_id'] ?? 0);
            if ($aid <= 0) {
                continue;
            }
            $mapPorAluno[$aid] = [
                'nota' => $ln['nota'] ?? null,
                'observacao' => (string) ($ln['observacao'] ?? ''),
            ];
        }

        $combosMateria = [];
        foreach ($evento['professores'] ?? [] as $pe) {
            if ((int) ($pe['professor_id'] ?? 0) !== (int) $professor['id']) {
                continue;
            }
            $mid = (int) ($pe['materia_id'] ?? 0);
            if ($mid <= 0) {
                continue;
            }
            if (!isset($combosMateria[$mid])) {
                $combosMateria[$mid] = [];
            }
            foreach ($pe['turmas'] ?? [] as $t) {
                $tid = is_array($t) ? (int) ($t['id'] ?? 0) : (int) $t;
                if ($tid > 0) {
                    $combosMateria[$mid][$tid] = $tid;
                }
            }
        }

        foreach ($combosMateria as $mid => $turmasMid) {
            $idsTurma = array_values($turmasMid);
            if (empty($idsTurma)) {
                continue;
            }
            $alunosMid = $this->turmaModel->getAlunosByTurmasIds($idsTurma);
            $linhasMid = [];
            foreach ($alunosMid as $a) {
                $aid = (int) ($a['id'] ?? 0);
                $tid = (int) ($a['turma_id'] ?? 0);
                if ($aid <= 0 || $tid <= 0 || !array_key_exists($aid, $mapPorAluno)) {
                    continue;
                }
                $linhasMid[] = [
                    'turma_id' => $tid,
                    'aluno_id' => $aid,
                    'nota' => $mapPorAluno[$aid]['nota'],
                    'observacao' => $mapPorAluno[$aid]['observacao'],
                ];
            }
            if (!empty($linhasMid)) {
                $notasModel->upsertLinhas((int) $evento_id, (int) $professor['id'], (int) $mid, $linhasMid);
            }
        }

        $this->setFlashMessage('Notas salvas e replicadas para todas as matérias do evento.', 'success');
        $this->redirect('/professor/provas/evento-lancar-notas/' . (int) $evento_id . '?materia_id=' . $materiaId);
    }
    
    /**
     * Salva nova prova
     */
    public function salvar()
    {
        $user = $this->auth->getUser();
        
        if ($user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        try {
            // Lê dados JSON do body
            $input = file_get_contents('php://input');
            $postData = json_decode($input, true);
            
            // Se não conseguir ler JSON, tenta $_POST
            if (!$postData) {
                $postData = $_POST;
            }
            
            // Validação
            $errors = [];
            
            if (empty($postData['materia_id'])) {
                $errors['materia_id'] = 'Matéria é obrigatória';
            }
            
            // Se houver evento_id, busca informações do evento para preencher dados faltantes
            if (!empty($postData['evento_id'])) {
                require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
                $blocoModel = new ExamBlock();
                $evento = $blocoModel->findById($postData['evento_id']);
                
                if ($evento) {
                    // Preenche título se não fornecido
                    if (empty($postData['titulo'])) {
                        $postData['titulo'] = $evento['titulo'] ?? 'Prova do Evento';
                    }
                    
                    // Preenche data/hora se não fornecidas
                    if (empty($postData['data_inicio'])) {
                        $postData['data_inicio'] = date('Y-m-d H:i:s', strtotime($evento['data_prova'] . ' ' . $evento['hora_inicio']));
                    }
                    if (empty($postData['data_fim'])) {
                        $postData['data_fim'] = date('Y-m-d H:i:s', strtotime($evento['data_prova'] . ' ' . $evento['hora_fim']));
                    }
                    
                    // Preenche liberar_resultado se não fornecido
                    if (empty($postData['liberar_resultado'])) {
                        $postData['liberar_resultado'] = $evento['liberar_gabarito'] === 'imediatamente' ? 'imediatamente' : 'apos_todos';
                    }
                }
            }
            
            if (empty($postData['titulo'])) {
                $errors['titulo'] = 'Título é obrigatório';
            }
            if (empty($postData['data_inicio'])) {
                $errors['data_inicio'] = 'Data de início é obrigatória';
            }
            if (empty($postData['data_fim'])) {
                $errors['data_fim'] = 'Data de término é obrigatória';
            }
            
            if (!empty($errors)) {
                $this->json(['error' => 'Dados inválidos', 'errors' => $errors], 400);
                return;
            }
            
            // Busca professor
            $professor = $this->teacherModel->findById($user['id']);
            if (!$professor) {
                throw new Exception('Professor não encontrado');
            }
            
            // Determina status da prova
            // Se liberada = 1, status = 'liberada'
            // Se não, status = 'rascunho' (professor ainda editando) ou 'pendente' (aguardando agrupamento)
            $status = 'rascunho';
            if (!empty($postData['status'])) {
                $status = $postData['status'];
            } elseif (!empty($postData['liberada']) && $postData['liberada'] == 1) {
                $status = 'liberada';
            }
            
            // Evita duplicação: se já existe prova deste professor nesta matéria neste evento, retorna o id existente
            if (!empty($postData['evento_id']) && !empty($postData['materia_id'])) {
                $existente = $this->db->fetch(
                    "SELECT p.id FROM provas p
                     INNER JOIN provas_blocos_vinculo pbp ON p.id = pbp.prova_id
                     WHERE pbp.bloco_id = :bloco_id AND p.professor_id = :professor_id AND p.materia_id = :materia_id AND p.deleted_at IS NULL
                     LIMIT 1",
                    ['bloco_id' => $postData['evento_id'], 'professor_id' => $professor['id'], 'materia_id' => $postData['materia_id']]
                );
                if ($existente && !empty($existente['id'])) {
                    $this->json([
                        'success' => true,
                        'message' => 'Prova já existe neste evento. Redirecionando para edição.',
                        'id' => (int)$existente['id'],
                        'existe' => true
                    ]);
                    return;
                }
            }
            
            // Se houver evento_id, busca todas as turmas do evento para este professor e matéria
            $turmasEvento = [];
            $turmasEventoIds = [];
            if (!empty($postData['evento_id'])) {
                require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
                $blocoModel = new ExamBlock();
                $evento = $blocoModel->findById($postData['evento_id']);
                
                if ($evento && !empty($evento['professores'])) {
                    foreach ($evento['professores'] as $profEvento) {
                        if ($profEvento['professor_id'] == $professor['id'] && 
                            $profEvento['materia_id'] == $postData['materia_id']) {
                            $turmasEvento = $profEvento['turmas'] ?? [];
                            // Extrai apenas os IDs das turmas (pode vir como array de objetos ou array de IDs)
                            if (!empty($turmasEvento)) {
                                foreach ($turmasEvento as $turma) {
                                    if (is_array($turma) && isset($turma['id'])) {
                                        $turmasEventoIds[] = (int)$turma['id'];
                                    } elseif (is_numeric($turma)) {
                                        $turmasEventoIds[] = (int)$turma;
                                    }
                                }
                            }
                            break;
                        }
                    }
                }
            }
            
            // Prepara dados
            $data = [
                'professor_id' => $professor['id'],
                'materia_id' => $postData['materia_id'],
                'turma_id' => !empty($turmasEventoIds) ? null : ($postData['turma_id'] ?? null), // Se tem turmas do evento, deixa null para aplicar a todas
                'titulo' => $postData['titulo'],
                'descricao' => $postData['descricao'] ?? null,
                'data_inicio' => $postData['data_inicio'],
                'data_fim' => $postData['data_fim'],
                'tempo_limite' => !empty($postData['tempo_limite']) ? (int)$postData['tempo_limite'] : null,
                'valor_total' => $postData['valor_total'] ?? 100.00,
                'mostrar_resultado' => $postData['mostrar_resultado'] ?? 1,
                'permite_correcao' => $postData['permite_correcao'] ?? 0,
                'liberar_resultado' => $postData['liberar_resultado'] ?? 'imediatamente',
                'ativo' => $postData['ativo'] ?? 1,
                'liberada' => $postData['liberada'] ?? 0,
                'status' => $status,
                'turmas' => !empty($turmasEventoIds) ? $turmasEventoIds : ($postData['turmas'] ?? []) // Usa IDs das turmas do evento se disponível
            ];
            
            $id = $this->provaModel->create($data);
            
            // Se foi fornecido evento_id, vincula a prova ao evento
            if (!empty($postData['evento_id'])) {
                require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
                $blocoModel = new ExamBlock();
                
                // Verifica se o professor está vinculado ao evento
                $evento = $blocoModel->findById($postData['evento_id']);
                if ($evento && !empty($evento['professores'])) {
                    $professorNoEvento = false;
                    foreach ($evento['professores'] as $profEvento) {
                        if ($profEvento['professor_id'] == $professor['id'] && 
                            $profEvento['materia_id'] == $postData['materia_id']) {
                            $professorNoEvento = true;
                            break;
                        }
                    }
                    
                    if ($professorNoEvento) {
                        // Adiciona prova ao bloco
                        $provasExistentes = $blocoModel->getProvas($evento['id']);
                        $ordem = count($provasExistentes);
                        
                        $this->db->query(
                            "INSERT INTO provas_blocos_vinculo (bloco_id, prova_id, ordem) 
                             VALUES (:bloco_id, :prova_id, :ordem)
                             ON DUPLICATE KEY UPDATE ordem = :ordem_update",
                            [
                                'bloco_id' => $evento['id'],
                                'prova_id' => $id,
                                'ordem' => $ordem,
                                'ordem_update' => $ordem
                            ]
                        );
                        
                        // Se o bloco está liberado, marca a prova como liberada também
                        if ($evento['liberado'] == 1) {
                            $this->db->query(
                                "UPDATE provas SET status = 'agendada', liberada = 1 WHERE id = :id",
                                ['id' => $id]
                            );
                            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                                    error_log("DEBUG criar(): Prova ID {$id} vinculada ao bloco ID {$evento['id']} e marcada como liberada");
                                }
                            }
                        } else {
                            // Atualiza status da prova para 'agendada' mas não libera ainda
                            $this->db->query(
                                "UPDATE provas SET status = 'agendada' WHERE id = :id",
                                ['id' => $id]
                            );
                        }
                    }
                }
            }
            
            $this->json([
                'success' => true,
                'message' => 'Prova criada com sucesso',
                'id' => $id
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao salvar prova: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Visualiza prova
     */
    public function visualizar($id)
    {
        $user = $this->auth->getUser();
        
        $prova = $this->provaModel->findById($id);
        if (!$prova) {
            $this->setFlashMessage('Prova não encontrada', 'error');
            if ($user['tipo'] === 'professor') {
                $this->redirect('/professor/provas');
            } else {
                $this->redirect('/admin/provas');
            }
            return;
        }
        
        // Verifica permissão
        if ($user['tipo'] === 'professor' && $prova['professor_id'] != $user['id']) {
            $this->setFlashMessage('Você não tem permissão para visualizar esta prova', 'error');
            $this->redirect('/professor/provas');
            return;
        }
        
        // Busca questões
        $questoes = $this->provaModel->getQuestoes($id);
        
        // Para cada questão, busca alternativas se for múltipla escolha
        foreach ($questoes as &$questao) {
            if ($questao['tipo'] === 'multipla_escolha') {
                $questao['alternativas'] = $this->provaModel->getAlternativas($questao['id']);
            }
        }
        
        // Busca turmas da prova
        $turmas = $this->provaModel->getTurmas($id);
        
        // Busca alunos que realizaram
        $alunosRealizacao = $this->provaModel->getAlunosRealizacao($id);
        
        // Busca bloco_id da prova e nome do coordenador que criou o evento (se estiver em um bloco)
        $blocoId = null;
        $eventoCoordenadorNome = null;
        $bloco = $this->db->fetch(
            "SELECT bloco_id FROM provas_blocos_vinculo WHERE prova_id = :prova_id LIMIT 1",
            ['prova_id' => $id]
        );
        if ($bloco) {
            $blocoId = $bloco['bloco_id'];
            $row = $this->db->fetch(
                "SELECT u.nome FROM provas_blocos pb LEFT JOIN usuarios u ON pb.criado_por = u.id WHERE pb.id = :bloco_id LIMIT 1",
                ['bloco_id' => $blocoId]
            );
            if ($row && !empty($row['nome'])) {
                $eventoCoordenadorNome = trim($row['nome']);
            }
        }
        
        // Adiciona status formatado
        $prova['status_formatado'] = $this->getStatusFormatadoProva($prova);
        
        $data = [
            'title' => 'Visualizar Prova - EducaTudo',
            'user' => $user,
            'prova' => $prova,
            'questoes' => $questoes,
            'turmas' => $turmas,
            'alunosRealizacao' => $alunosRealizacao,
            'isAdmin' => in_array($user['tipo'], ['admin', 'admin_escola']),
            'bloco_id' => $blocoId,
            'evento_coordenador_nome' => $eventoCoordenadorNome,
            'current_page' => 'provas',
            'additional_css' => '<link rel="stylesheet" href="' . URL . '/public/static/css/mathlive-static.css">',
            'additional_js' => '<script src="' . URL . '/public/static/js/mathlive.min.js"></script><script>document.addEventListener("DOMContentLoaded",function(){if(typeof MathLive!=="undefined"&&MathLive.renderMathInDocument)MathLive.renderMathInDocument();});</script>',
        ];
        
        $layout = $user['tipo'] === 'professor' ? 'professor' : 'admin';
        $view = $user['tipo'] === 'professor' ? 'teacher/exams/view' : 'admin/exams/view';
        
        $this->viewWithLayout($layout, $view, $data);
    }

    /**
     * Preview da prova como o aluno vê (somente professor, somente leitura).
     */
    public function previewComoAluno($id)
    {
        $user = $this->auth->getUser();
        if ($user['tipo'] !== 'professor') {
            $this->redirect('/professor/provas');
            return;
        }
        $id = (int) $id;
        $prova = $this->provaModel->findById($id);
        if (!$prova) {
            $this->setFlashMessage('Prova não encontrada', 'error');
            $this->redirect('/professor/provas');
            return;
        }
        if (!$this->provaModel->canEdit($id, $user['id'])) {
            $this->setFlashMessage('Você não tem permissão para visualizar esta prova', 'error');
            $this->redirect('/professor/provas');
            return;
        }
        $questoes = $this->provaModel->getQuestoes($id);
        if (empty($questoes)) {
            $this->setFlashMessage('A prova não possui questões para visualizar.', 'error');
            $this->redirect('/professor/provas/editar/' . $id);
            return;
        }
        foreach ($questoes as &$q) {
            if ($q['tipo'] === 'multipla_escolha') {
                $q['alternativas'] = $this->provaModel->getAlternativas($q['id']);
            }
        }
        unset($q);
        $materia = $this->subjectModel->findById($prova['materia_id']);
        $prova['materia_nome'] = $materia['nome'] ?? 'Sem matéria';
        $data = [
            'title' => 'Preview da Prova - EducaTudo',
            'user' => $user,
            'prova' => $prova,
            'questoes' => $questoes,
            'preview' => true,
            'current_page' => 'provas',
            'additional_css' => '<link rel="stylesheet" href="' . URL . '/public/static/css/mathlive-static.css">',
            'additional_js' => '<script src="' . URL . '/public/static/js/mathlive.min.js"></script><script>document.addEventListener("DOMContentLoaded",function(){if(typeof MathLive!=="undefined"&&MathLive.renderMathInDocument)MathLive.renderMathInDocument();});</script>',
        ];
        $this->viewWithLayout('professor', 'teacher/exams/preview', $data);
    }

    /**
     * Libera nova tentativa para aluno que teve a prova cancelada (modo seguro).
     * Apenas professor ou admin/coordenador.
     */
    public function liberarTentativa($provaId, $alunoId)
    {
        $user = $this->auth->getUser();
        if (!in_array($user['tipo'], ['professor', 'admin', 'admin_escola'])) {
            $this->setFlashMessage('Acesso negado', 'error');
            $this->redirect($user['tipo'] === 'professor' ? '/professor/provas' : '/admin/provas');
            return;
        }
        $provaId = (int) $provaId;
        $alunoId = (int) $alunoId;
        if ($provaId <= 0 || $alunoId <= 0) {
            $this->setFlashMessage('Dados inválidos', 'error');
            $this->redirect(($user['tipo'] === 'professor') ? '/professor/provas' : '/admin/provas');
            return;
        }
        $ok = $this->provaModel->liberarNovaTentativa($provaId, $alunoId);
        if ($ok) {
            $this->setFlashMessage('Nova tentativa liberada para o aluno. Ele poderá realizar a prova novamente.', 'success');
        } else {
            $this->setFlashMessage('Não foi possível liberar. A realização não está cancelada ou não existe.', 'error');
        }
        $returnUrl = trim((string)($_POST['return_url'] ?? ''));
        if ($returnUrl !== '' && strpos($returnUrl, '/admin/provas/blocos/') === 0 && strpos($returnUrl, '/canceladas') !== false) {
            $this->redirect($returnUrl);
            return;
        }
        $base = ($user['tipo'] === 'professor') ? '/professor/provas/visualizar/' . $provaId : '/admin/provas/visualizar/' . $provaId;
        $this->redirect($base);
    }

    /**
     * Verifica a senha do usuário logado (coordenador/admin/professor) para ações sensíveis.
     */
    private function verificarSenhaUsuarioAtual(array $user, string $senha): bool
    {
        if ($senha === '') {
            return false;
        }
        try {
            if (in_array($user['tipo'], ['admin', 'admin_escola'])) {
                $row = $this->db->fetch("SELECT senha_hash FROM usuarios WHERE id = :id LIMIT 1", ['id' => (int) $user['id']]);
            } elseif ($user['tipo'] === 'professor') {
                $row = $this->db->fetch("SELECT senha_hash FROM professores WHERE id = :id LIMIT 1", ['id' => (int) $user['id']]);
            } else {
                return false;
            }
            return !empty($row['senha_hash']) && password_verify($senha, $row['senha_hash']);
        } catch (Exception $e) {
            error_log('verificarSenhaUsuarioAtual: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Valida tentativa cancelada que já possui respostas (recupera nota sem refazer a prova).
     * Apenas professor ou admin/coordenador. Exige a senha do usuário logado e registra histórico.
     */
    public function validarTentativaCancelada($provaId, $alunoId)
    {
        $user = $this->auth->getUser();
        if (!in_array($user['tipo'], ['professor', 'admin', 'admin_escola'])) {
            $this->setFlashMessage('Acesso negado', 'error');
            $this->redirect($user['tipo'] === 'professor' ? '/professor/provas' : '/admin/provas');
            return;
        }
        $provaId = (int) $provaId;
        $alunoId = (int) $alunoId;
        if ($provaId <= 0 || $alunoId <= 0) {
            $this->setFlashMessage('Dados inválidos', 'error');
            $this->redirect(($user['tipo'] === 'professor') ? '/professor/provas' : '/admin/provas');
            return;
        }

        $returnUrl = trim((string)($_POST['return_url'] ?? ''));
        $redirectVoltar = function () use ($returnUrl, $user, $provaId) {
            if ($returnUrl !== '' && strpos($returnUrl, '/admin/provas/blocos/') === 0 && strpos($returnUrl, '/canceladas') !== false) {
                $this->redirect($returnUrl);
                return;
            }
            $base = ($user['tipo'] === 'professor') ? '/professor/provas/visualizar/' . $provaId : '/admin/provas/visualizar/' . $provaId;
            $this->redirect($base);
        };

        // Confirmação por senha do coordenador (usuário logado)
        $senha = (string) ($_POST['senha_coordenador'] ?? '');
        if (!$this->verificarSenhaUsuarioAtual($user, $senha)) {
            $this->setFlashMessage('Senha incorreta. A validação da nota não foi realizada.', 'error');
            $redirectVoltar();
            return;
        }

        $ok = $this->provaModel->validarTentativaCancelada($provaId, $alunoId);
        if ($ok) {
            // Registra histórico: quem validou, quando e qual nota resultou
            $realizacao = $this->provaModel->getRealizacao($provaId, $alunoId);
            $blocoRow = $this->db->fetch(
                "SELECT bloco_id FROM provas_blocos_vinculo WHERE prova_id = :prova_id LIMIT 1",
                ['prova_id' => $provaId]
            );
            $this->provaModel->registrarValidacaoNota(
                $provaId,
                $alunoId,
                $blocoRow['bloco_id'] ?? null,
                $realizacao['nota'] ?? null,
                ['id' => $user['id'], 'nome' => $user['nome'] ?? '', 'tipo' => $user['tipo']]
            );
            $this->setFlashMessage('Nota calculada e prova marcada como finalizada com sucesso.', 'success');
        } else {
            $this->setFlashMessage('Não foi possível validar. A prova não está cancelada ou não possui respostas salvas.', 'error');
        }
        $redirectVoltar();
    }
    
    /**
     * Formulário de edição de prova
     */
    public function editar($id)
    {
        $user = $this->auth->getUser();
        $isAdmin = in_array($user['tipo'], ['admin', 'admin_escola']);

        if (!$isAdmin && $user['tipo'] !== 'professor') {
            $this->redirect('/admin/provas');
            return;
        }

        $prova = $this->provaModel->findById($id);
        if (!$prova) {
            $this->setFlashMessage('Prova não encontrada', 'error');
            $this->redirect($isAdmin ? '/admin/provas' : '/professor/provas');
            return;
        }

        if (!$isAdmin) {
            // Professor: verifica permissão e bloqueia se já enviada/aprovada
            if (!$this->provaModel->canEdit($id, $user['id'])) {
                $this->setFlashMessage('Você não tem permissão para editar esta prova', 'error');
                $this->redirect('/professor/provas');
                return;
            }
            if (in_array($prova['status'], ['enviada', 'aprovada'])) {
                $this->setFlashMessage('Esta prova não pode ser editada após o envio para a coordenação.', 'error');
                $this->redirect('/professor/provas/visualizar/' . $id);
                return;
            }
        }

        // Professor logado ou (admin editando: usa dados do professor dono da prova)
        $professorId = $isAdmin ? ($prova['professor_id'] ?? $prova['user_id'] ?? null) : $user['id'];
        $professor = $this->teacherModel->findById($professorId);
        if (!$professor) {
            $this->setFlashMessage('Professor da prova não encontrado', 'error');
            $this->redirect($isAdmin ? '/admin/provas' : '/professor/dashboard');
            return;
        }

        // Garantir token CSRF na sessão para upload de imagem (colar/selecionar)
        $this->generateCsrfToken();
        
        // Busca matérias do professor
        $materias_professor = json_decode($professor['materias'], true) ?: [];
        
        if (empty($materias_professor)) {
            $materias = [];
        } else {
            $placeholders = str_repeat('?,', count($materias_professor) - 1) . '?';
            $materias = $this->db->fetchAll(
                "SELECT * FROM materias WHERE nome IN ($placeholders) ORDER BY nome ASC",
                $materias_professor
            );
        }
        
        // Busca turmas do professor
        $turmas_professor = json_decode($professor['turmas'], true) ?: [];
        
        if (empty($turmas_professor)) {
            $turmas = [];
        } else {
            $placeholders = str_repeat('?,', count($turmas_professor) - 1) . '?';
            $turmas = $this->db->fetchAll(
                "SELECT * FROM turmas WHERE id IN ($placeholders) ORDER BY nome",
                $turmas_professor
            );
        }
        
        // Busca questões
        $questoes = $this->provaModel->getQuestoes($id);
        
        // Para cada questão, busca alternativas se for múltipla escolha
        foreach ($questoes as &$questao) {
            if ($questao['tipo'] === 'multipla_escolha') {
                $questao['alternativas'] = $this->provaModel->getAlternativas($questao['id']);
            }
        }
        
        // Busca turmas da prova
        $turmasProva = $this->provaModel->getTurmas($id);
        $prova['turmas_ids'] = array_column($turmasProva, 'id');
        
        // Busca se a prova está vinculada a um evento
        $evento = null;
        $blocoProva = $this->db->fetch(
            "SELECT pb.id, pb.titulo, pb.prazo_entrega_professor, pb.configuracao_nota, 
                    pb.data_prova, pb.hora_inicio, pb.hora_fim, pbp.bloco_id 
             FROM provas_blocos_vinculo pbp
             INNER JOIN provas_blocos pb ON pbp.bloco_id = pb.id
             WHERE pbp.prova_id = :prova_id
             AND pb.deleted_at IS NULL
             LIMIT 1",
            ['prova_id' => $id]
        );
        
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        
                error_log("DEBUG editar prova ID $id - blocoProva encontrado: " . ($blocoProva ? 'SIM' : 'NÃO'));
        
            }
        
        }
        if ($blocoProva) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG editar prova - prazo_entrega_professor: " . var_export($blocoProva['prazo_entrega_professor'] ?? 'NULL', true));
                }
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG editar prova - blocoProva completo: " . print_r($blocoProva, true));
                }
            }
            
            $evento = [
                'id' => $blocoProva['id'],
                'titulo' => $blocoProva['titulo'],
                'configuracao_nota' => $blocoProva['configuracao_nota'] ?? 'professor_por_questao',
                'prazo_entrega_professor' => !empty($blocoProva['prazo_entrega_professor']) ? $blocoProva['prazo_entrega_professor'] : null,
                'data_prova' => $blocoProva['data_prova'] ?? null,
                'hora_inicio' => $blocoProva['hora_inicio'] ?? null,
                'hora_fim' => $blocoProva['hora_fim'] ?? null
            ];
            
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                    error_log("DEBUG editar prova - evento criado com prazo: " . var_export($evento['prazo_entrega_professor'], true));
            
                }
            
            }
        } else {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG editar prova - Nenhum bloco encontrado para prova ID: $id");
                }
            }
        }
        
        // Número obrigatório de questões (bloco: definido por professor/matéria)
        $numeroQuestoesObrigatorio = 0;
        $configProva = $this->db->fetch(
            "SELECT numero_questoes FROM provas_professores WHERE prova_id = :prova_id LIMIT 1",
            ['prova_id' => $id]
        );
        if ($configProva && isset($configProva['numero_questoes']) && (int)$configProva['numero_questoes'] > 0) {
            $numeroQuestoesObrigatorio = (int)$configProva['numero_questoes'];
        } else {
            // Fallback: quantidade definida no bloco (provas_blocos_professores)
            $blocoQtd = $this->db->fetch(
                "SELECT pbp.quantidade_questoes
                 FROM provas_blocos_vinculo pbv
                 INNER JOIN provas_blocos_professores pbp ON pbp.bloco_id = pbv.bloco_id
                   AND pbp.professor_id = :professor_id AND pbp.materia_id = :materia_id
                 WHERE pbv.prova_id = :prova_id
                 LIMIT 1",
                ['prova_id' => $id, 'professor_id' => $prova['professor_id'], 'materia_id' => $prova['materia_id']]
            );
            if ($blocoQtd && isset($blocoQtd['quantidade_questoes']) && (int)$blocoQtd['quantidade_questoes'] > 0) {
                $numeroQuestoesObrigatorio = (int)$blocoQtd['quantidade_questoes'];
            }
        }
        
        // Busca planos de aula do professor
        $planosAula = $this->planoAulaModel->findByProfessor($professor['id']);
        
        $data = [
            'title' => 'Editar Prova - EducaTudo',
            'user' => $user,
            'prova' => $prova,
            'questoes' => $questoes,
            'materias' => $materias,
            'turmas' => $turmas,
            'planosAula' => $planosAula,
            'evento' => $evento,
            'numero_questoes_obrigatorio' => $numeroQuestoesObrigatorio,
            'current_page' => 'provas',
            'additional_css' => '<link rel="stylesheet" href="' . URL . '/public/static/css/mathlive-static.css">' . "\n"
                . '    <link rel="stylesheet" href="' . URL . '/public/static/css/math-editor.css">',
            'additional_js' => '<script src="' . URL . '/public/static/js/mathlive.min.js"></script>' . "\n"
                . '    <script src="' . URL . '/public/static/js/math-editor.js"></script>',
            'is_admin_edit' => $isAdmin,
            'voltar_url' => $isAdmin ? (URL . '/admin/provas/visualizar/' . $id) : (URL . '/professor/provas'),
        ];

        $this->viewWithLayout($isAdmin ? 'admin' : 'professor', 'teacher/exams/edit', $data);
    }
    
    /**
     * Atualiza prova
     */
    public function atualizar($id)
    {
        $user = $this->auth->getUser();
        $isAdmin = in_array($user['tipo'], ['admin', 'admin_escola']);

        if (!$isAdmin && $user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        try {
            if (!$isAdmin && !$this->provaModel->canEdit($id, $user['id'])) {
                throw new Exception('Você não tem permissão para editar esta prova');
            }
            if (!$isAdmin) {
                $prova = $this->provaModel->findById($id);
                $this->validarPermissaoProfessorModificarProva($prova, $user['id'], 'editar');
            }
            
            // Lê dados JSON do body
            $input = file_get_contents('php://input');
            $postData = json_decode($input, true);
            
            // Se não conseguir ler JSON, tenta $_POST
            if (!$postData) {
                $postData = $_POST;
            }
            
            // Validação
            $errors = [];
            
            if (empty($postData['materia_id'])) {
                $errors['materia_id'] = 'Matéria é obrigatória';
            }
            if (empty($postData['titulo'])) {
                $errors['titulo'] = 'Título é obrigatório';
            }
            if (empty($postData['data_inicio'])) {
                $errors['data_inicio'] = 'Data de início é obrigatória';
            }
            if (empty($postData['data_fim'])) {
                $errors['data_fim'] = 'Data de término é obrigatória';
            }
            
            if (!empty($errors)) {
                $this->json(['error' => 'Dados inválidos', 'errors' => $errors], 400);
                return;
            }
            
            // Determina status da prova
            $status = 'rascunho';
            if (!empty($postData['status'])) {
                $status = $postData['status'];
            } elseif (!empty($postData['liberada']) && $postData['liberada'] == 1) {
                $status = 'liberada';
            }
            
            // Prepara dados
            $data = [
                'materia_id' => $postData['materia_id'],
                'turma_id' => $postData['turma_id'] ?? null,
                'titulo' => $postData['titulo'],
                'descricao' => $postData['descricao'] ?? null,
                'data_inicio' => $postData['data_inicio'],
                'data_fim' => $postData['data_fim'],
                'data_prova' => $postData['data_prova'] ?? null,
                'data_limite_envio' => $postData['data_limite_envio'] ?? null,
                'tempo_limite' => !empty($postData['tempo_limite']) ? (int)$postData['tempo_limite'] : null,
                'valor_total' => $postData['valor_total'] ?? 100.00,
                'mostrar_resultado' => $postData['mostrar_resultado'] ?? 1,
                'permite_correcao' => $postData['permite_correcao'] ?? 0,
                'liberar_resultado' => $postData['liberar_resultado'] ?? 'imediatamente',
                'ativo' => $postData['ativo'] ?? 1,
                'liberada' => $postData['liberada'] ?? 0,
                'status' => $status,
                'turmas' => $postData['turmas'] ?? []
            ];
            
            $this->provaModel->update($id, $data);
            
            $this->json([
                'success' => true,
                'message' => 'Prova atualizada com sucesso'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao atualizar prova: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Atualiza apenas as informações da prova (data_prova e data_limite_envio)
     */
    public function atualizarInformacoes($id)
    {
        $user = $this->auth->getUser();
        $isAdmin = in_array($user['tipo'], ['admin', 'admin_escola']);

        if (!$isAdmin && $user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        try {
            if (!$isAdmin && !$this->provaModel->canEdit($id, $user['id'])) {
                throw new Exception('Você não tem permissão para editar esta prova');
            }
            if (!$isAdmin) {
                $prova = $this->provaModel->findById($id);
                $this->validarPermissaoProfessorModificarProva($prova, $user['id'], 'editar');
            }
            
            // Lê dados JSON do body
            $input = file_get_contents('php://input');
            $postData = json_decode($input, true);
            
            // Se não conseguir ler JSON, tenta $_POST
            if (!$postData) {
                $postData = $_POST;
            }
            
            // Validação
            if (empty($postData['data_limite_envio'])) {
                throw new Exception('Data limite para envio é obrigatória');
            }
            
            // Verifica se a prova está em um bloco com prazo definido
            $blocoProva = $this->db->fetch(
                "SELECT pb.prazo_entrega_professor 
                 FROM provas_blocos_vinculo pbp
                 INNER JOIN provas_blocos pb ON pbp.bloco_id = pb.id
                 WHERE pbp.prova_id = :prova_id
                 AND pb.deleted_at IS NULL
                 AND pb.prazo_entrega_professor IS NOT NULL
                 LIMIT 1",
                ['prova_id' => $id]
            );
            
            if ($blocoProva) {
                throw new Exception('O prazo de envio foi definido pelo administrador no bloco de provas e não pode ser alterado.');
            }
            
            // Atualiza apenas o campo de data limite para envio
            $this->db->update(
                "UPDATE provas SET 
                    data_limite_envio = :data_limite_envio,
                    updated_at = NOW()
                 WHERE id = :id AND deleted_at IS NULL",
                [
                    'data_limite_envio' => $postData['data_limite_envio'],
                    'id' => $id
                ]
            );
            
            $this->json([
                'success' => true,
                'message' => 'Informações da prova atualizadas com sucesso'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao atualizar informações da prova: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Upload de imagem para questão (enunciado/alternativas).
     * No servidor: se der 400, verifique PHP (upload_max_filesize e post_max_size >= 10M)
     * e Nginx (client_max_body_size >= 10M) ou Apache (LimitRequestBody).
     */
    public function uploadImagemQuestao()
    {
        $csrfToken = trim((string) ($_POST['_token'] ?? ''));
        if ($csrfToken === '') {
            if (function_exists('getallheaders')) {
                $headers = array_change_key_case(getallheaders(), CASE_LOWER);
                $csrfToken = trim((string) ($headers['x-csrf-token'] ?? ''));
            }
            if ($csrfToken === '' && !empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
                $csrfToken = trim((string) $_SERVER['HTTP_X_CSRF_TOKEN']);
            }
        }
        if ($csrfToken === '') {
            $this->json(['error' => 'Token de segurança não enviado. Recarregue a página e tente novamente.', 'csrf_token' => $this->refreshCsrfToken()], 400);
            return;
        }
        if (!$this->verifyCsrfToken($csrfToken)) {
            $this->json(['error' => 'Token inválido. Recarregue a página e tente novamente.', 'csrf_token' => $this->refreshCsrfToken()], 400);
            return;
        }

        $user = $this->auth->getUser();
        $isAdmin = in_array($user['tipo'], ['admin', 'admin_escola']);

        if (!$isAdmin && $user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        try {
            if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
                $err = isset($_FILES['imagem']['error']) ? $_FILES['imagem']['error'] : UPLOAD_ERR_NO_FILE;
                if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                    throw new Exception('Arquivo muito grande. Máximo 10MB. No servidor, aumente upload_max_filesize e post_max_size (PHP) e client_max_body_size (Nginx).');
                }
                if ($err === UPLOAD_ERR_NO_FILE) {
                    $hint = (empty($_FILES) && empty($_POST['_token'])) ? ' Se a imagem for grande, o servidor pode estar rejeitando; verifique post_max_size e upload_max_filesize no PHP.' : '';
                    throw new Exception('Nenhuma imagem enviada. Tente colar novamente (Ctrl+V) ou selecione um arquivo (JPG, PNG, GIF, WebP).' . $hint);
                }
                throw new Exception('Erro no upload da imagem (código ' . $err . '). Tente outro formato ou tamanho menor.');
            }
            
            $file = $_FILES['imagem'];
            
            // Validar tipo: aceita pelo type do request ou inferido (colagem às vezes vem com type vazio)
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $mime = trim((string) ($file['type'] ?? ''));
            if ($mime === '' && !empty($file['tmp_name']) && is_readable($file['tmp_name']) && function_exists('finfo_open')) {
                $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $detected = @finfo_file($finfo, $file['tmp_name']);
                    if ($detected) $mime = $detected;
                    finfo_close($finfo);
                }
            }
            if ($mime === '' || !in_array($mime, $allowedTypes)) {
                throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WebP.');
            }
            
            // Validar tamanho (max 10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                throw new Exception('Arquivo muito grande. Máximo 10MB.');
            }

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($extension === '') {
                $map = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
                $extension = $map[$mime] ?? 'png';
            }
            $filename = 'questao_' . $user['id'] . '_' . time() . '_' . uniqid() . '.' . $extension;
            require_once __DIR__ . '/../../Services/MediaStorageService.php';
            $key = MediaStorageService::userKey('teacher', $user['id'], $filename);
            $media = new MediaStorageService($this->config);
            if (!$media->put('provas_questoes', $key, $file['tmp_name'], $mime)) {
                error_log("[Upload prova] MediaStorageService put FALHOU key=" . $key);
                throw new Exception('Erro ao salvar arquivo. Ver log do servidor (tag: [Upload prova]).');
            }
            $imageUrl = rtrim(URL, '/') . '/media/serve?type=provas_questoes&key=' . rawurlencode($key);

            $this->json([
                'success' => true,
                'image_url' => $imageUrl
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao fazer upload de imagem: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Serve imagem de questão pela aplicação (MediaStorageService: local ou S3).
     * GET /professor/provas/ver-imagem-questao?f=nome_arquivo.jpg
     */
    public function verImagemQuestao()
    {
        if (ob_get_level()) {
            ob_end_clean();
        }
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['professor', 'admin', 'admin_escola', 'aluno'])) {
            http_response_code(403);
            exit;
        }
        $f = isset($_GET['f']) ? trim((string) $_GET['f']) : '';
        if ($f === '' || strpos($f, '..') !== false || preg_match('/[^a-zA-Z0-9_\-\.\/]/', $f)) {
            http_response_code(400);
            exit;
        }
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        if ($media->isS3()) {
            $url = $media->getViewUrl('provas_questoes', $f, $f);
            if ($url !== null && $url !== '') {
                header('Location: ' . $url);
                exit;
            }
        }
        $filepath = $media->getLocalPath('provas_questoes', $f);
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

    /**
     * Adiciona questão à prova
     */
    public function adicionarQuestao($id)
    {
        $user = $this->auth->getUser();
        $isAdmin = in_array($user['tipo'], ['admin', 'admin_escola']);

        if (!$isAdmin && $user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        try {
            if (!$isAdmin && !$this->provaModel->canEdit($id, $user['id'])) {
                throw new Exception('Você não tem permissão para editar esta prova');
            }
            if (!$isAdmin) {
                $prova = $this->provaModel->findById($id);
                $this->validarPermissaoProfessorModificarProva($prova, $user['id'], 'editar');
            }
            
            // Lê dados JSON do body
            $input = file_get_contents('php://input');
            $postData = json_decode($input, true);
            
            if (!$postData) {
                $postData = $_POST;
            }
            
            // Validação
            if (empty($postData['enunciado'])) {
                $this->json(['error' => 'Enunciado é obrigatório'], 400);
                return;
            }
            
            // Busca questões para determinar ordem
            $questoes = $this->provaModel->getQuestoes($id);
            $ordem = count($questoes);
            
            $data = [
                'enunciado' => $postData['enunciado'],
                'imagem_url' => $postData['imagem_url'] ?? null,
                'tipo' => $postData['tipo'] ?? 'multipla_escolha',
                'valor' => $postData['valor'] ?? 1.00,
                'ordem' => $ordem,
                'alternativas' => $postData['alternativas'] ?? []
            ];
            
            $questaoId = $this->provaModel->addQuestao($id, $data);
            
            // Log para debug
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG ProvaController::adicionarQuestao - Prova ID: {$id}, Questão ID criada: {$questaoId}");
                }
            }
            
            // Verifica se a questão foi realmente salva
            $questaoVerificada = $this->provaModel->getQuestaoById($questaoId);
            if (!$questaoVerificada) {
                throw new Exception('Questão não foi salva corretamente');
            }
            
            $this->json([
                'success' => true,
                'message' => 'Questão adicionada com sucesso',
                'questao_id' => $questaoId
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao adicionar questão: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Atualiza questão
     */
    public function atualizarQuestao($id, $questaoId)
    {
        $user = $this->auth->getUser();
        $isAdmin = in_array($user['tipo'], ['admin', 'admin_escola']);

        if (!$isAdmin && $user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        try {
            if (!$isAdmin && !$this->provaModel->canEdit($id, $user['id'])) {
                throw new Exception('Você não tem permissão para editar esta prova');
            }
            if (!$isAdmin) {
                $prova = $this->provaModel->findById($id);
                $this->validarPermissaoProfessorModificarProva($prova, $user['id'], 'editar');
            }
            
            // Lê dados JSON do body
            $input = file_get_contents('php://input');
            $postData = json_decode($input, true);
            
            if (!$postData) {
                $postData = $_POST;
            }
            
            $data = [
                'enunciado' => $postData['enunciado'],
                'imagem_url' => $postData['imagem_url'] ?? null,
                'tipo' => $postData['tipo'] ?? 'multipla_escolha',
                'valor' => $postData['valor'] ?? 1.00,
                'ordem' => $postData['ordem'] ?? 0,
                'alternativas' => $postData['alternativas'] ?? []
            ];
            
            $this->provaModel->updateQuestao($questaoId, $data);
            
            $this->json([
                'success' => true,
                'message' => 'Questão atualizada com sucesso'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao atualizar questão: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Remove questão
     */
    public function removerQuestao($id, $questaoId)
    {
        $user = $this->auth->getUser();
        $isAdmin = in_array($user['tipo'], ['admin', 'admin_escola']);

        if (!$isAdmin && $user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        try {
            if (!$isAdmin && !$this->provaModel->canEdit($id, $user['id'])) {
                throw new Exception('Você não tem permissão para editar esta prova');
            }
            if (!$isAdmin) {
                $prova = $this->provaModel->findById($id);
                $this->validarPermissaoProfessorModificarProva($prova, $user['id'], 'editar');
            }
            
            $this->provaModel->deleteQuestao($questaoId);
            
            $this->json([
                'success' => true,
                'message' => 'Questão removida com sucesso'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao remover questão: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Toggle liberação da prova
     */
    public function toggleLiberada($id)
    {
        $user = $this->auth->getUser();
        
        if ($user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        try {
            // Verifica se pode editar
            if (!$this->provaModel->canEdit($id, $user['id'])) {
                throw new Exception('Você não tem permissão para editar esta prova');
            }
            
            $this->provaModel->toggleLiberada($id);
            $prova = $this->provaModel->findById($id);
            
            $this->json([
                'success' => true,
                'message' => $prova['liberada'] ? 'Prova liberada' : 'Prova bloqueada',
                'liberada' => $prova['liberada']
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao alterar liberação: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Exclui prova (soft delete)
     */
    public function excluir($id)
    {
        $user = $this->auth->getUser();
        
        if ($user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        try {
            $prova = $this->provaModel->findById($id);
            $this->validarPermissaoProfessorModificarProva($prova, $user['id'], 'excluir');
            
            $this->provaModel->delete($id);
            
            $this->json([
                'success' => true,
                'message' => 'Prova excluída com sucesso'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao excluir prova: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * ============================================
     * ROTAS PARA ALUNOS
     * ============================================
     */
    
    /**
     * Lista provas disponíveis para o aluno
     */
    public function indexAluno()
    {
        $user = $this->auth->getUser();
        
        if ($user['tipo'] !== 'aluno') {
            $this->redirect('/professor/provas');
            return;
        }
        
        // Busca aluno
        $aluno = $this->studentModel->findById($user['id']);
        if (!$aluno) {
            $this->setFlashMessage('Aluno não encontrado', 'error');
            $this->redirect('/dashboard');
            return;
        }

        // Cancelamento garantido via redirect (modo seguro): a saída do modo seguro redireciona
        // para cá com ?cancelar_bloco=ID; grava 'cancelada' no banco antes de renderizar.
        $cancelarBlocoId = (int) ($_GET['cancelar_bloco'] ?? 0);
        if ($cancelarBlocoId > 0) {
            $afetadas = $this->provaModel->cancelarRealizacoesBlocoSeguro($cancelarBlocoId, (int) $aluno['id']);
            $this->logProvas('Cancelamento via redirect Minhas Provas', [
                'bloco_id' => $cancelarBlocoId,
                'aluno_id' => (int) $aluno['id'],
                'afetadas' => $afetadas,
            ]);
            $this->setFlashMessage('Sua prova foi cancelada por saída do modo seguro. Apenas o coordenador pode liberar nova tentativa.', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        
        // Sincronizar provas existentes com provas_turmas (correção automática)
        $this->sincronizarProvasTurmas();
        
        // Busca blocos de provas disponíveis
        require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
        $blocoModel = new ExamBlock();
        $blocos = $blocoModel->findByAluno($aluno['id']);
        
        // Para cada bloco, busca as provas com status de realização
        $blocosComProvas = [];
        foreach ($blocos as $bloco) {
            $agoraBloco = date('Y-m-d H:i:s');
            $inicioBloco = ($bloco['data_prova'] ?? '') . ' ' . ($bloco['hora_inicio'] ?? '00:00:00');
            $fimBloco = ($bloco['data_prova'] ?? '') . ' ' . ($bloco['hora_fim'] ?? '23:59:59');
            $visivelPortal = !array_key_exists('visivel_no_portal_aluno', $bloco) || !empty($bloco['visivel_no_portal_aluno']);
            if (empty($bloco['liberado'])
                && !empty($bloco['ativo'])
                && $visivelPortal
                && $inicioBloco <= $agoraBloco
                && $fimBloco >= $agoraBloco) {
                $this->db->query(
                    "UPDATE provas_blocos
                     SET liberado = 1, ativo = 1, status = 'liberado'
                     WHERE id = :id AND deleted_at IS NULL",
                    ['id' => (int) $bloco['id']]
                );
                $bloco['liberado'] = 1;
                $bloco['ativo'] = 1;
                $bloco['status'] = 'liberado';
            }

            // Se o bloco está liberado, garante que as provas vinculadas também estejam liberadas
            if ($bloco['liberado'] == 1) {
                $blocoModel->garantirProvasLiberadas((int) $bloco['id']);
            }
            
            $provasDoBloco = $blocoModel->getProvas($bloco['id'], $aluno['id']);
            $resumoBloco = $blocoModel->getResumoRealizacaoAlunoNoBloco($bloco['id'], $aluno['id']);
            $bloco['alguma_cancelada'] = !empty($resumoBloco['alguma_cancelada']);
            $bloco['todas_finalizadas'] = !empty($resumoBloco['todas_finalizadas']);

            foreach ($provasDoBloco as &$provaLinha) {
                if (!isset($provaLinha['realizacao_status']) || $provaLinha['realizacao_status'] === null || $provaLinha['realizacao_status'] === '') {
                    $real = $this->provaModel->getRealizacao($provaLinha['id'], $aluno['id']);
                    $provaLinha['realizacao_status'] = $real['status'] ?? null;
                }
            }
            unset($provaLinha);
            
            // Debug individual
            if (defined('DEBUG') && DEBUG) {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                            error_log("DEBUG indexAluno Bloco {$bloco['id']} ({$bloco['titulo']}): " . count($provasDoBloco) . " provas encontradas");
                        }
                    }
                if (empty($provasDoBloco)) {
                    // Verifica se há provas no bloco sem filtros
                    $todasProvas = $this->db->fetchAll(
                        "SELECT p.*, pbp.ordem 
                         FROM provas_blocos_vinculo pbp 
                         INNER JOIN provas p ON pbp.prova_id = p.id 
                         WHERE pbp.bloco_id = :bloco_id",
                        ['bloco_id' => $bloco['id']]
                    );
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                            error_log("DEBUG indexAluno Bloco {$bloco['id']}: Total de provas no bloco (sem filtros): " . count($todasProvas));
                        }
                    }
                    if (!empty($todasProvas)) {
                        foreach ($todasProvas as $tp) {
                            error_log("  - Prova ID {$tp['id']}: {$tp['titulo']} | deleted_at: " . ($tp['deleted_at'] ?? 'NULL') . " | ativo: " . ($tp['ativo'] ?? 'N/A') . " | liberada: " . ($tp['liberada'] ?? 'N/A'));
                        }
                    }
                } else {
                    foreach ($provasDoBloco as $p) {
                        error_log("  - Prova ID {$p['id']}: {$p['titulo']} | materia: {$p['materia_nome']}");
                    }
                }
            }
            
            // Dentro do período = entre data_prova+hora_inicio e data_prova+hora_fim (libera no horário)
            $inicio = ($bloco['data_prova'] ?? '') . ' ' . ($bloco['hora_inicio'] ?? '00:00:00');
            $fim = ($bloco['data_prova'] ?? '') . ' ' . ($bloco['hora_fim'] ?? '23:59:59');
            $agora = date('Y-m-d H:i:s');
            $bloco['dentro_periodo'] = ($agora >= $inicio && $agora <= $fim);
            $bloco['disponivel_em'] = ($agora < $inicio) ? $inicio : null; // só preenchido se ainda não começou
            $bloco['provas'] = $provasDoBloco;
            $blocosComProvas[] = $bloco;
        }
        
        // Debug: informações sobre blocos
        if (defined('DEBUG') && DEBUG) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG indexAluno: Encontrados " . count($blocos) . " blocos, " . count($blocosComProvas) . " com provas");
                }
            }
        }
        
        // Também busca provas individuais (sem bloco) para compatibilidade
        $provas = $this->provaModel->findByAluno($aluno['id']);
        $provas = $this->aplicarEntregaEducaIncluiNaListagem($provas, (int) $aluno['id']);
        
        // Debug detalhado: Verificar informações do aluno e provas
        $todasProvasLiberadas = $this->db->fetchAll(
            "SELECT id, titulo, liberada, ativo, turma_id, data_inicio, data_fim, deleted_at 
             FROM provas 
             WHERE liberada = 1 AND ativo = 1 AND deleted_at IS NULL"
        );
        
        // Uma única query para provas_turmas de todas as provas liberadas (evita N+1)
        $provasTurmasPorProva = [];
        if (!empty($todasProvasLiberadas)) {
            $provaIds = array_column($todasProvasLiberadas, 'id');
            $placeholders = implode(',', array_fill(0, count($provaIds), '?'));
            $allProvasTurmas = $this->db->fetchAll(
                "SELECT prova_id, turma_id FROM provas_turmas WHERE prova_id IN ($placeholders)",
                $provaIds
            );
            foreach ($allProvasTurmas as $row) {
                $pid = (int) $row['prova_id'];
                if (!isset($provasTurmasPorProva[$pid])) {
                    $provasTurmasPorProva[$pid] = [];
                }
                $provasTurmasPorProva[$pid][] = (int) $row['turma_id'];
            }
        }

        $debugProvas = [];
        foreach ($todasProvasLiberadas as $prova) {
            $provasTurmasIds = $provasTurmasPorProva[(int) $prova['id']] ?? [];
            $matchTurmaDireto = ($prova['turma_id'] == $aluno['turma_id']);
            $matchProvasTurmas = in_array((int) $aluno['turma_id'], $provasTurmasIds, true);
            $now = date('Y-m-d H:i:s');
            $dentroPeriodo = ($prova['data_inicio'] <= $now && $prova['data_fim'] >= $now);
            $debugProvas[] = [
                'prova_id' => $prova['id'],
                'titulo' => $prova['titulo'],
                'turma_id_prova' => $prova['turma_id'],
                'aluno_turma_id' => $aluno['turma_id'],
                'provas_turmas' => $provasTurmasIds,
                'match_turma_direto' => $matchTurmaDireto,
                'match_provas_turmas' => $matchProvasTurmas,
                'dentro_periodo' => $dentroPeriodo,
                'data_inicio' => $prova['data_inicio'],
                'data_fim' => $prova['data_fim'],
                'agora' => $now
            ];
        }
        
        // Debug: Buscar todos os blocos para diagnóstico
        $todosBlocos = $this->db->fetchAll(
            "SELECT pb.*, 
                    COUNT(DISTINCT pbp.prova_id) as total_provas
             FROM provas_blocos pb
             LEFT JOIN provas_blocos_vinculo pbp ON pb.id = pbp.bloco_id
             WHERE pb.deleted_at IS NULL
             GROUP BY pb.id"
        );
        
        // Adiciona informações de debug (sempre, para ajudar no diagnóstico)
        $debugInfo = null;
        if (isset($debugProvas)) {
            $debugInfo = [
                'aluno_id' => $aluno['id'],
                'aluno_nome' => $aluno['nome'],
                'aluno_turma_id' => $aluno['turma_id'] ?? 'NULL',
                'provas_encontradas' => count($provas),
                'blocos_encontrados' => count($blocos),
                'blocos_com_provas' => count($blocosComProvas),
                'total_blocos_sistema' => count($todosBlocos),
                'total_provas_liberadas' => count($todasProvasLiberadas),
                'debug_provas' => $debugProvas,
                'debug_blocos' => array_map(function($b) use ($aluno) {
                    $now = date('Y-m-d H:i:s');
                    $dataHoraInicio = $b['data_prova'] . ' ' . $b['hora_inicio'];
                    $dataHoraFim = $b['data_prova'] . ' ' . $b['hora_fim'];
                    return [
                        'bloco_id' => $b['id'],
                        'titulo' => $b['titulo'],
                        'liberado' => $b['liberado'],
                        'ativo' => $b['ativo'],
                        'turma_id_bloco' => $b['turma_id'],
                        'aluno_turma_id' => $aluno['turma_id'],
                        'data_prova' => $b['data_prova'],
                        'hora_inicio' => $b['hora_inicio'],
                        'hora_fim' => $b['hora_fim'],
                        'dentro_periodo' => ($dataHoraInicio <= $now && $dataHoraFim >= $now),
                        'agora' => $now
                    ];
                }, $todosBlocos)
            ];
            
            if (defined('DEBUG') && DEBUG) {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG Provas Aluno: " . json_encode($debugInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    }
                }
            }
        }
        
        // Remove provas que estão em blocos das provas individuais (blocos visíveis no portal)
        $provasIdsEmBlocos = [];
        foreach ($blocosComProvas as $bloco) {
            if (!empty($bloco['provas'])) {
                foreach ($bloco['provas'] as $provaBloco) {
                    $provasIdsEmBlocos[] = $provaBloco['id'];
                }
            }
        }
        // Provas só em blocos ocultos para o aluno: não devem aparecer como "prova avulsa"
        $provasIdsEmBlocosOcultos = [];
        if ($blocoModel->columnExistsOnBloco('visivel_no_portal_aluno')) {
            $ocultas = $this->db->fetchAll(
                "SELECT DISTINCT pbp.prova_id AS id
                 FROM provas_blocos_vinculo pbp
                 INNER JOIN provas_blocos pb ON pb.id = pbp.bloco_id
                 WHERE pb.deleted_at IS NULL AND pb.visivel_no_portal_aluno = 0"
            );
            foreach ($ocultas ?: [] as $row) {
                if (!empty($row['id'])) {
                    $provasIdsEmBlocosOcultos[] = (int) $row['id'];
                }
            }
        }
        $provasIdsExcluirIndividual = array_values(array_unique(array_merge($provasIdsEmBlocos, $provasIdsEmBlocosOcultos)));
        
        if (!empty($provasIdsExcluirIndividual)) {
            $provas = array_filter($provas, function ($prova) use ($provasIdsExcluirIndividual) {
                return !in_array($prova['id'], $provasIdsExcluirIndividual, true);
            });
            // Reindexa o array
            $provas = array_values($provas);
        }
        
        // Diagnóstico (apenas com ?debug=1): mostra IDs de turma do aluno x dos blocos
        // para identificar mismatch de turma (ex.: "1ºA" do Fundamental x "1ª A" do EM).
        $blocoDiag = null;
        if (isset($_GET['debug'])) {
            $blocoDiag = $this->buildBlocoDiagnostico((int) $aluno['id']);
        }

        $flash = $this->getFlashMessage();
        $data = [
            'title' => 'Minhas Provas - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'blocosComProvas' => $blocosComProvas, // Nome correto para a view
            'provas' => $provas, // Provas individuais (sem bloco)
            'current_page' => 'provas',
            'debug_info' => $debugInfo,
            'bloco_diag' => $blocoDiag,
            'flash_message' => $flash['message'] ?? null,
            'flash_type' => $flash['type'] ?? 'info'
        ];
        
        $this->viewWithLayout('student', 'student/exams/index', $data);
    }

    private function aplicarEntregaEducaIncluiNaListagem(array $provas, int $alunoId): array
    {
        if ($alunoId <= 0) {
            return $provas;
        }

        try {
            require_once __DIR__ . '/../../Models/EducaInclui/VersaoAdaptada.php';
            $versions = new VersaoAdaptada();
            $presentes = [];
            foreach ($provas as $idx => $prova) {
                $provaId = (int) ($prova['id'] ?? 0);
                if ($provaId <= 0) {
                    continue;
                }
                $presentes[$provaId] = true;
                $ver = $versions->getAnyFor($provaId, $alunoId);
                if (!$ver || ($ver['status_aprovacao'] ?? '') !== 'aprovada' || empty($ver['adapted_prova_id'])) {
                    continue;
                }
                $provas[$idx]['ei_adapted_prova_id'] = (int) $ver['adapted_prova_id'];
                $provas[$idx]['ei_adapted_version_id'] = (int) $ver['id'];
                $provas[$idx]['ei_adapted'] = true;
            }

            $extras = $this->db->fetchAll(
                "SELECT p.*,
                        m.nome as materia_nome,
                        t.nome as turma_nome,
                        pr.id as realizacao_id,
                        pr.status as realizacao_status,
                        pr.nota as realizacao_nota,
                        v.id as ei_adapted_version_id,
                        v.adapted_prova_id as ei_adapted_prova_id
                 FROM versoes_adaptadas v
                 INNER JOIN provas p ON p.id = v.prova_id
                 LEFT JOIN materias m ON p.materia_id = m.id
                 LEFT JOIN turmas t ON p.turma_id = t.id
                 LEFT JOIN provas_realizacoes pr ON pr.prova_id = v.adapted_prova_id AND pr.aluno_id = :aluno_id_realizacao
                 WHERE v.aluno_id = :aluno_id
                   AND v.status_aprovacao = 'aprovada'
                   AND v.adapted_prova_id IS NOT NULL
                   AND p.liberada = 1
                   AND p.ativo = 1
                   AND p.deleted_at IS NULL
                   AND p.data_inicio <= :now_inicio
                   AND p.data_fim >= :now_fim
                 ORDER BY p.data_inicio DESC",
                [
                    'aluno_id_realizacao' => $alunoId,
                    'aluno_id' => $alunoId,
                    'now_inicio' => date('Y-m-d H:i:s'),
                    'now_fim' => date('Y-m-d H:i:s'),
                ]
            );
            foreach ($extras as $extra) {
                $originalId = (int) ($extra['id'] ?? 0);
                if ($originalId <= 0 || isset($presentes[$originalId])) {
                    continue;
                }
                $extra['ei_adapted'] = true;
                $extra['ei_adapted_prova_id'] = (int) ($extra['ei_adapted_prova_id'] ?? 0);
                $extra['ei_adapted_version_id'] = (int) ($extra['ei_adapted_version_id'] ?? 0);
                $provas[] = $extra;
                $presentes[$originalId] = true;
            }
        } catch (Throwable $e) {
            error_log('EducaInclui listagem provas: ' . $e->getMessage());
        }

        return $provas;
    }

    /**
     * Diagnóstico de visibilidade de blocos para um aluno (somente ?debug=1).
     * Lista as turmas do aluno (principal + matrículas ativas) e, para cada bloco
     * não excluído, as turmas vinculadas + se houve match. Ajuda a identificar
     * mismatch de turma (mesmo nome, IDs diferentes) e flags liberado/ativo/visível.
     *
     * @return array<string,mixed>
     */
    private function buildBlocoDiagnostico(int $alunoId): array
    {
        require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
        $blocoModel = new ExamBlock();
        $hasVisivel = $blocoModel->columnExistsOnBloco('visivel_no_portal_aluno');

        $principal = $this->db->fetch(
            "SELECT a.turma_id, t.nome AS turma_nome
             FROM alunos a LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = ?",
            [$alunoId]
        ) ?: [];

        $matriculas = [];
        try {
            if ($this->db->fetch("SHOW TABLES LIKE 'matricula'") !== false) {
                $matriculas = $this->db->fetchAll(
                    "SELECT m.turma_id, t.nome AS turma_nome, m.status, m.ano_letivo_id
                     FROM matricula m INNER JOIN turmas t ON t.id = m.turma_id
                     WHERE m.aluno_id = ? AND m.status = 'ativa' AND m.data_saida IS NULL
                     ORDER BY m.data_entrada DESC, m.id DESC",
                    [$alunoId]
                ) ?: [];
            }
        } catch (\Throwable $e) {
            // sem tabela matricula
        }

        $turmaIdsAluno = [];
        if (!empty($principal['turma_id'])) {
            $turmaIdsAluno[] = (int) $principal['turma_id'];
        }
        foreach ($matriculas as $m) {
            if (!empty($m['turma_id'])) {
                $turmaIdsAluno[] = (int) $m['turma_id'];
            }
        }
        $turmaIdsAluno = array_values(array_unique($turmaIdsAluno));

        $blocos = $this->db->fetchAll(
            "SELECT pb.id, pb.titulo, pb.liberado, pb.ativo, pb.turma_id"
            . ($hasVisivel ? ", pb.visivel_no_portal_aluno" : "")
            . " FROM provas_blocos pb WHERE pb.deleted_at IS NULL ORDER BY pb.id DESC"
        ) ?: [];

        $blocosOut = [];
        foreach ($blocos as $b) {
            $turmasBloco = $this->db->fetchAll(
                "SELECT pbt.turma_id, t.nome AS turma_nome
                 FROM provas_blocos_turmas pbt INNER JOIN turmas t ON t.id = pbt.turma_id
                 WHERE pbt.bloco_id = ?",
                [(int) $b['id']]
            ) ?: [];
            $turmaIdsBloco = array_map(static fn ($r) => (int) $r['turma_id'], $turmasBloco);
            if (!empty($b['turma_id'])) {
                $turmaIdsBloco[] = (int) $b['turma_id'];
            }
            $turmaIdsBloco = array_values(array_unique($turmaIdsBloco));
            $blocosOut[] = [
                'id' => (int) $b['id'],
                'titulo' => (string) $b['titulo'],
                'liberado' => (int) $b['liberado'],
                'ativo' => (int) $b['ativo'],
                'visivel' => $hasVisivel ? (int) $b['visivel_no_portal_aluno'] : null,
                'turmas' => $turmasBloco,
                'turma_ids' => $turmaIdsBloco,
                'match' => array_values(array_intersect($turmaIdsAluno, $turmaIdsBloco)),
            ];
        }

        return [
            'aluno_id' => $alunoId,
            'turma_principal' => $principal,
            'matriculas' => $matriculas,
            'turma_ids_aluno' => $turmaIdsAluno,
            'has_visivel_col' => $hasVisivel,
            'blocos' => $blocosOut,
        ];
    }

    /**
     * Inicia realização da prova
     */
    /**
     * AVA/EAD — a prova foi liberada para este aluno por progresso (vínculo em
     * ava_disciplina_avaliacoes)? Quando true, dispensa os gates de
     * liberada/ativo/data/turma (o controle passa a ser o progresso na disciplina).
     */
    private function avaLiberadaParaAluno($provaId, $alunoId): bool
    {
        try {
            require_once __DIR__ . '/../../Services/AvaEvaluationService.php';
            return (new AvaEvaluationService())->provaLiberadaParaAluno((int) $provaId, (int) $alunoId);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function iniciar($id)
    {
        $user = $this->auth->getUser();
        
        if ($user['tipo'] !== 'aluno') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        try {
            $prova = $this->provaModel->findById($id);
            if (!$prova) {
                throw new Exception('Prova não encontrada');
            }

            $avaLiberadaIniciar = $this->avaLiberadaParaAluno($id, $user['id']);

            $blocoVinculadoIniciar = $this->db->fetch(
                "SELECT pb.* FROM provas_blocos pb
                 INNER JOIN provas_blocos_vinculo pbp ON pb.id = pbp.bloco_id
                 WHERE pbp.prova_id = :prova_id AND pb.deleted_at IS NULL
                 LIMIT 1",
                ['prova_id' => $id]
            );
            if ($blocoVinculadoIniciar && !$this->alunoPodeVerBlocoNoPortalLinha($blocoVinculadoIniciar)) {
                throw new Exception('Esta prova não está disponível no portal para os alunos.');
            }
            
            // EducaInclui — prova adaptada (clone) é ativo=0/liberada=0 por design (invisível
            // em listagens). Libera o início apenas para o aluno dono da versão.
            $eiIsAdaptedOwn = false;
            try {
                require_once __DIR__ . '/../../Models/EducaInclui/VersaoAdaptada.php';
                $eiAdaptedIni = (new VersaoAdaptada())->getByAdaptedProvaId((int) $id);
                if ($eiAdaptedIni && (int) $eiAdaptedIni['aluno_id'] === (int) $user['id']) {
                    $eiIsAdaptedOwn = true;
                }
            } catch (Throwable $eEiIni) {
                error_log('EducaInclui iniciar(): ' . $eEiIni->getMessage());
            }

            // Verifica se pode iniciar
            if (!$eiIsAdaptedOwn && !$avaLiberadaIniciar) {
                if (!$prova['liberada'] || !$prova['ativo']) {
                    throw new Exception('Prova não está disponível');
                }

                $now = date('Y-m-d H:i:s');
                if ($now < $prova['data_inicio'] || $now > $prova['data_fim']) {
                    throw new Exception('Prova fora do prazo');
                }
            }
            
            // Verifica se já iniciou
            $realizacao = $this->provaModel->getRealizacao($id, $user['id']);
            if ($realizacao && $realizacao['status'] === 'finalizado') {
                throw new Exception('Prova já foi finalizada');
            }
            
            // Busca questões
            $questoes = $this->provaModel->getQuestoes($id);
            
            // Sorteia ordem aleatória
            $questoesIds = array_column($questoes, 'id');
            shuffle($questoesIds);
            
            // Inicia realização
            if (!$realizacao) {
                $this->provaModel->iniciarRealizacao($id, $user['id'], $questoesIds);
            }
            
            $this->json([
                'success' => true,
                'message' => 'Prova iniciada',
                'questoes_ids' => $questoesIds
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao iniciar prova: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Realiza prova (página de realização)
     */
    /**
     * EducaInclui — monta o CSS de acessibilidade e a classe do wrapper da prova
     * a partir das regras da máscara ativa do aluno.
     *
     * @param array<string,string> $rules
     * @return array{0:string,1:string} [styleHtml, wrapperClass]
     */
    private function buildAccessibilityHead(array $rules): array
    {
        require_once __DIR__ . '/../../Helpers/AccessibilityCss.php';
        return AccessibilityCss::build($rules);
    }

    /**
     * EducaInclui — reduz o nº de alternativas de uma questão de múltipla escolha,
     * mantendo SEMPRE a(s) correta(s). Seleção e ordem determinísticas por aluno
     * (estável entre recarregamentos da prova), para não trocar as opções a cada load.
     *
     * @param array<int,array<string,mixed>> $alternativas
     * @return array<int,array<string,mixed>>
     */
    private function reduceAlternativas(array $alternativas, int $keep, int $seed): array
    {
        $corretas = [];
        $distratores = [];
        foreach ($alternativas as $alt) {
            if (!empty($alt['correta'])) {
                $corretas[] = $alt;
            } else {
                $distratores[] = $alt;
            }
        }
        // Segurança: sem gabarito, não esconder nada (nunca remover a resposta certa).
        if (empty($corretas)) {
            return $alternativas;
        }

        $ordenarPor = static function (string $prefixo) use ($seed) {
            return static function ($a, $b) use ($prefixo, $seed) {
                return strcmp(
                    md5($prefixo . '-' . $seed . '-' . ($a['id'] ?? '')),
                    md5($prefixo . '-' . $seed . '-' . ($b['id'] ?? ''))
                );
            };
        };

        usort($distratores, $ordenarPor('pick'));
        $faltam = max(0, $keep - count($corretas));
        $selecionadas = array_merge($corretas, array_slice($distratores, 0, $faltam));
        usort($selecionadas, $ordenarPor('ord'));

        return $selecionadas;
    }

    public function realizar($id)
    {
        try {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG realizar(): INÍCIO - Prova ID: {$id}");
                }
            }
            
            $user = $this->auth->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG realizar(): Usuário não é aluno - Tipo: {$user['tipo']}");
                    }
                }
                $this->redirect('/professor/provas');
                return;
            }
            
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                    error_log("DEBUG realizar(): Usuário é aluno - ID: {$user['id']}");
            
                }
            
            }
            
            $prova = $this->provaModel->findById($id);
        if (!$prova) {
            $this->logProvas('[REALIZAR] REDIRECIONANDO: prova não encontrada', ['prova_id' => $id]);
            $this->setFlashMessage('Prova não encontrada', 'error');
            $this->redirect('/aluno/provas');
            return;
        }

        // AVA/EAD: prova liberada por progresso na disciplina dispensa gates de liberada/data.
        $avaLiberadaRealizar = $this->avaLiberadaParaAluno($id, $user['id']);
        
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        
                error_log("DEBUG realizar(): Prova encontrada - ID: {$id}, Título: {$prova['titulo']}, Liberada: " . ($prova['liberada'] ? 'SIM' : 'NÃO') . ", Ativo: " . ($prova['ativo'] ? 'SIM' : 'NÃO'));
        
            }
        
        }
        
        // Verifica se a prova está em um bloco e valida o bloco
        $blocoProva = $this->db->fetch(
            "SELECT pb.* FROM provas_blocos pb
             INNER JOIN provas_blocos_vinculo pbp ON pb.id = pbp.bloco_id
             WHERE pbp.prova_id = :prova_id AND pb.deleted_at IS NULL
             LIMIT 1",
            ['prova_id' => $id]
        );

        // EducaInclui — entrega de versão adaptada significativa (clone) via redirect nativo.
        // O clone é uma prova ativo=0/liberada=0 (invisível em listagens). Só o dono a abre.
        $eiIsAdaptedOwnRealizar = false;
        $eiAdaptedRulesSnapshot = [];
        $eiAdaptedAccommodationId = 0;
        try {
            require_once __DIR__ . '/../../Models/EducaInclui/VersaoAdaptada.php';
            require_once __DIR__ . '/../../Models/EducaInclui/VersaoAdaptadaLog.php';
            require_once __DIR__ . '/../../Services/MascaraResolver.php';
            $eiVersions = new VersaoAdaptada();
            $eiAlunoId = (int) ($user['id'] ?? 0);

            $eiAdapted = $eiVersions->getByAdaptedProvaId((int) $id);
            if ($eiAdapted) {
                // Esta prova é um clone adaptado: somente o aluno dono pode acessá-la.
                if ((int) $eiAdapted['aluno_id'] !== $eiAlunoId) {
                    $this->setFlashMessage('Prova não encontrada', 'error');
                    $this->redirect('/aluno/provas');
                    return;
                }
                // Dono acessando seu clone: libera os gates de ativo/liberada (são 0 por design).
                $eiIsAdaptedOwnRealizar = true;
                $eiAdaptedAccommodationId = (int) ($eiAdapted['mascara_id'] ?? 0);
                if (!empty($eiAdapted['regras_snapshot_json'])) {
                    $decodedRules = json_decode((string) $eiAdapted['regras_snapshot_json'], true);
                    if (is_array($decodedRules)) {
                        $eiAdaptedRulesSnapshot = $decodedRules;
                    }
                }
            } else {
                // Prova original: verificar se há versão adaptada significativa a entregar.
                // Vale para prova avulsa E para prova dentro de bloco/evento (Opção B:
                // a versão adaptada roda com tempo próprio, fora do timer compartilhado).
                $eiMask = MascaraResolver::resolveForAluno($eiAlunoId);
                if (!empty($eiMask['active']) && MascaraResolver::requiresClone($eiMask['rules'])) {
                    $eiVer = $eiVersions->getAnyFor((int) $id, $eiAlunoId);
                    if (!$eiVer) {
                        // Sem versão ainda: fora de bloco geramos o rascunho on-open (humano no loop).
                        // Em bloco, a geração ocorre na aprovação final do bloco — não geramos durante a prova.
                        if (!$blocoProva) {
                            require_once __DIR__ . '/../../Services/AssessmentVersionGenerator.php';
                            (new AssessmentVersionGenerator())->ensureDraft((int) $id, $eiAlunoId, (int) $eiMask['mascara_id'], $eiMask['rules']);
                        }
                    } elseif (($eiVer['status_aprovacao'] ?? '') === 'aprovada' && !empty($eiVer['adapted_prova_id'])) {
                        require_once __DIR__ . '/../../Services/AssessmentHasher.php';
                        $eiHashAtual = (new AssessmentHasher())->hashProva((int) $id);
                        if ($eiHashAtual !== '' && $eiHashAtual === (string) $eiVer['hash_prova_origem']) {
                            (new VersaoAdaptadaLog())->record('entrega_redirect', [
                                'versao_adaptada_id' => (int) $eiVer['id'],
                                'mascara_id' => (int) $eiVer['mascara_id'],
                                'aluno_id' => $eiAlunoId,
                                'prova_id' => (int) $id,
                                'user_id' => $eiAlunoId,
                                'details' => ['adapted_prova_id' => (int) $eiVer['adapted_prova_id']],
                            ]);
                            $query = [];
                            foreach (['bloco_id', 'modo_bloco', 'modo_seguro', 'embed'] as $param) {
                                if (isset($_GET[$param]) && $_GET[$param] !== '') {
                                    $query[$param] = (string) $_GET[$param];
                                }
                            }
                            if (!isset($query['modo_seguro'])) {
                                $query['modo_seguro'] = '1';
                            }
                            $suffix = $query ? ('?' . http_build_query($query)) : '';
                            $this->redirect('/aluno/provas/realizar/' . (int) $eiVer['adapted_prova_id'] . $suffix);
                            return;
                        }
                        // Drift: prova original mudou após a aprovação → invalida e entrega original.
                        $eiVersions->markDrift((int) $eiVer['id']);
                        (new VersaoAdaptadaLog())->record('drift_detectado', [
                            'versao_adaptada_id' => (int) $eiVer['id'],
                            'mascara_id' => (int) $eiVer['mascara_id'],
                            'aluno_id' => $eiAlunoId,
                            'prova_id' => (int) $id,
                            'user_id' => $eiAlunoId,
                        ]);
                    }
                }
            }
        } catch (Throwable $eEi) {
            error_log('EducaInclui entrega adaptada: ' . $eEi->getMessage());
        }

        $modoBloco = isset($_GET['modo_bloco']) && $_GET['modo_bloco'] == '1';
        $provasBloco = [];
        if ($modoBloco && $blocoProva) {
            $provasBloco = $this->db->fetchAll(
                "SELECT p.* FROM provas_blocos_vinculo pbp
                 INNER JOIN provas p ON pbp.prova_id = p.id
                 WHERE pbp.bloco_id = :bloco_id
                 AND p.liberada = 1
                 AND p.ativo = 1
                 AND p.deleted_at IS NULL
                 ORDER BY pbp.ordem ASC",
                ['bloco_id' => $blocoProva['id']]
            );
        }
        
        if ($blocoProva) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG realizar(): Prova está em bloco ID {$blocoProva['id']} - {$blocoProva['titulo']}");
                }
            }
            if (!$this->alunoPodeVerBlocoNoPortalLinha($blocoProva)) {
                $this->logProvas('[REALIZAR] REDIRECIONANDO: bloco oculto no portal do aluno', ['bloco_id' => $blocoProva['id']]);
                $this->setFlashMessage('Este evento de provas não está disponível no portal para os alunos.', 'error');
                $this->redirect('/aluno/provas');
                return;
            }
            // Se está em um bloco, valida o bloco
            if (!$blocoProva['liberado'] || !$blocoProva['ativo']) {
                $this->logProvas('[REALIZAR] REDIRECIONANDO: bloco não liberado ou inativo', ['bloco_id' => $blocoProva['id']]);
                $this->setFlashMessage('O bloco de provas não está disponível', 'error');
                $this->redirect('/aluno/provas');
                return;
            }
            
            // Valida período do bloco (início e fim)
            $now = date('Y-m-d H:i:s');
            $dataHoraInicio = $blocoProva['data_prova'] . ' ' . $blocoProva['hora_inicio'];
            $dataHoraFim = $blocoProva['data_prova'] . ' ' . $blocoProva['hora_fim'];
            if ($now < $dataHoraInicio || $now > $dataHoraFim) {
                $this->logProvas('[REALIZAR] REDIRECIONANDO: bloco fora do prazo', ['bloco_id' => $blocoProva['id'], 'now' => $now, 'inicio' => $dataHoraInicio, 'fim' => $dataHoraFim]);
                $this->setFlashMessage('O bloco de provas está fora do prazo', 'error');
                $this->redirect('/aluno/provas');
                return;
            }
            
            // Valida se o aluno pertence à turma do bloco (principal + matrículas)
            $aluno = $this->studentModel->findById($user['id']);
            if ($aluno) {
                require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
                $blocoAcessoModel = new ExamBlock();
                if (!$blocoAcessoModel->alunoTemAcessoAoBloco($blocoProva, (int) $aluno['id'])) {
                    $this->logProvas('[REALIZAR] REDIRECIONANDO: aluno sem acesso ao bloco (turma não vinculada)', [
                        'bloco_id' => $blocoProva['id'],
                        'aluno_id' => (int) $aluno['id'],
                    ]);
                    $this->setFlashMessage('Você não tem acesso a este bloco de provas', 'error');
                    $this->redirect('/aluno/provas');
                    return;
                }
                if (!$this->provaModel->alunoPodeAcessarProva((int) $id, (int) $aluno['id'])) {
                    $this->logProvas('[REALIZAR] REDIRECIONANDO: prova não é da turma do aluno', [
                        'prova_id' => (int) $id,
                        'aluno_id' => (int) $aluno['id'],
                    ]);
                    $this->setFlashMessage('Não há prova disponível para a sua turma neste bloco.', 'error');
                    $this->redirect('/aluno/provas');
                    return;
                }
            }
        }
        
        // Verifica se pode realizar (prova adaptada de inclusão é ativo=0/liberada=0 por design)
        if (!$eiIsAdaptedOwnRealizar && !$avaLiberadaRealizar && (!$prova['liberada'] || !$prova['ativo'])) {
            $this->logProvas('[REALIZAR] REDIRECIONANDO: prova não liberada ou inativa', ['prova_id' => $id]);
            $this->setFlashMessage('Prova não está disponível', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        
        // Prazo: em modo bloco usamos o período do bloco (já validado acima); senão usamos data_inicio/data_fim da prova
        $now = date('Y-m-d H:i:s');
        if (!$eiIsAdaptedOwnRealizar && !$avaLiberadaRealizar && (!$modoBloco || !$blocoProva)) {
            if ($now < $prova['data_inicio'] || $now > $prova['data_fim']) {
                $this->logProvas('[REALIZAR] REDIRECIONANDO: prova fora do prazo', ['prova_id' => $id, 'now' => $now, 'data_inicio' => $prova['data_inicio'] ?? null, 'data_fim' => $prova['data_fim'] ?? null]);
                $this->setFlashMessage('Prova fora do prazo', 'error');
                $this->redirect('/aluno/provas');
                return;
            }
        }
        
        // Verifica se há questões antes de continuar
        $questoes = $this->provaModel->getQuestoes($id);
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("DEBUG realizar(): Questões encontradas: " . count($questoes));
            }
        }
        
        if (empty($questoes)) {
            $this->logProvas('[REALIZAR] REDIRECIONANDO: prova sem questões', ['prova_id' => $id]);
            $this->setFlashMessage('Esta prova não possui questões cadastradas. Entre em contato com seu professor.', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        
        // Busca ou cria realização
        $realizacao = $this->provaModel->getRealizacao($id, $user['id']);
        
        if (!$realizacao) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG realizar(): Criando nova realização...");
                }
            }
            // Inicia realização
            $questoesIds = array_column($questoes, 'id');
            shuffle($questoesIds);
            
            try {
                $this->provaModel->iniciarRealizacao($id, $user['id'], $questoesIds);
                $realizacao = $this->provaModel->getRealizacao($id, $user['id']);
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG realizar(): Realização criada - ID: " . ($realizacao['id'] ?? 'N/A'));
                    }
                }
            } catch (Exception $e) {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG realizar(): ERRO ao criar realização: " . $e->getMessage());
                    }
                }
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG realizar(): Stack trace: " . $e->getTraceAsString());
                    }
                }
                $this->setFlashMessage('Erro ao iniciar a prova: ' . $e->getMessage(), 'error');
                $this->redirect('/aluno/provas');
                return;
            }
        } else {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG realizar(): Realização já existe - ID: {$realizacao['id']}, Status: {$realizacao['status']}");
                }
            }
        }
        
        if (!$realizacao) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG realizar(): ERRO - Realização não foi criada!");
                }
            }
            $this->setFlashMessage('Erro ao iniciar a prova. Tente novamente.', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        
        if ($realizacao['status'] === 'finalizado') {
            $this->setFlashMessage('Prova já foi finalizada', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        if ($realizacao['status'] === 'cancelada') {
            $this->setFlashMessage('Esta prova foi cancelada por saída do modo seguro. Aguarde a liberação do coordenador para realizar novamente.', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        
        // Busca questões na ordem sorteada
        $ordemQuestoes = json_decode($realizacao['ordem_questoes'], true) ?: [];
        $questoes = $this->provaModel->getQuestoes($id);
        
        if (empty($questoes)) {
            $this->setFlashMessage('Esta prova não possui questões cadastradas', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        
        // Ordena questões conforme ordem sorteada
        if (!empty($ordemQuestoes)) {
            usort($questoes, function($a, $b) use ($ordemQuestoes) {
                $posA = array_search($a['id'], $ordemQuestoes);
                $posB = array_search($b['id'], $ordemQuestoes);
                // Se não encontrar na ordem, coloca no final
                if ($posA === false) $posA = 9999;
                if ($posB === false) $posB = 9999;
                return $posA <=> $posB;
            });
        }
        
        // Para cada questão, busca alternativas se for múltipla escolha
        foreach ($questoes as &$questao) {
            if ($questao['tipo'] === 'multipla_escolha') {
                $questao['alternativas'] = $this->provaModel->getAlternativas($questao['id']);
                // Embaralha alternativas
                shuffle($questao['alternativas']);
            }
        }
        
        // Busca respostas já salvas (de todas as provas do bloco se for modo bloco)
        $respostasMap = [];
        if ($modoBloco && !empty($provasBloco)) {
            foreach ($provasBloco as $provaBloco) {
                $respostas = $this->provaModel->getRespostas($provaBloco['id'], $user['id']);
                foreach ($respostas as $resposta) {
                    $respostasMap[$resposta['questao_id']] = $resposta;
                }
            }
        } else {
            $respostas = $this->provaModel->getRespostas($id, $user['id']);
            foreach ($respostas as $resposta) {
                $respostasMap[$resposta['questao_id']] = $resposta;
            }
        }
        
        // Calcula tempo restante (usa o tempo do bloco se for modo bloco)
        $tempoRestante = null;
        $tempoLimite = null;
        if ($modoBloco && $blocoProva) {
            // Calcula tempo total do bloco
            $horaInicio = new DateTime($blocoProva['data_prova'] . ' ' . $blocoProva['hora_inicio']);
            $horaFim = new DateTime($blocoProva['data_prova'] . ' ' . $blocoProva['hora_fim']);
            $diff = $horaInicio->diff($horaFim);
            $tempoLimite = ($diff->h * 60) + $diff->i;
            
            if ($realizacao && $realizacao['iniciado_em']) {
                $iniciadoEm = new DateTime($realizacao['iniciado_em']);
                $agora = new DateTime();
                $diffGasto = $iniciadoEm->diff($agora);
                $tempoGasto = ($diffGasto->h * 60) + $diffGasto->i;
                $tempoRestante = max(0, $tempoLimite - $tempoGasto);
            } else {
                $tempoRestante = $tempoLimite;
            }
        } elseif ($prova['tempo_limite']) {
            $tempoLimite = $prova['tempo_limite'];
            if ($realizacao && $realizacao['iniciado_em']) {
                $iniciadoEm = new DateTime($realizacao['iniciado_em']);
                $agora = new DateTime();
                $diff = $iniciadoEm->diff($agora);
                $tempoGasto = ($diff->h * 60) + $diff->i;
                $tempoRestante = max(0, $prova['tempo_limite'] - $tempoGasto);
            } else {
                $tempoRestante = $prova['tempo_limite'];
            }
        }
        
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        
                error_log("DEBUG realizar(): Preparando dados para view - Questões: " . count($questoes) . ", Respostas: " . count($respostasMap) . ", Modo Bloco: " . ($modoBloco ? 'SIM' : 'NÃO'));
        
            }
        
        }
        
        // EducaInclui — aplica a Máscara de Acessibilidade ativa do aluno (adaptações de acesso)
        $acessibilidade_style = '';
        $acessibilidade_wrapper_class = '';
        $acessibilidadeAtiva = false;
        $accommodationRelaxSecure = false;
        $acessibilidade_tts = false;
        $acessibilidade_auto_read = false;
        $acessibilidade_read_speed = 'normal';
        $acessibilidade_highlight = false;
        $acessibilidade_glossary = false;
        $acessibilidade_audio = false;
        $acessibilidade_progress = false;
        $acessibilidade_pause = false;
        $acessibilidade_hide_timer = false;
        try {
            require_once __DIR__ . '/../../Services/MascaraResolver.php';
            $alunoIdAtual = (int) ($user['id'] ?? 0);
            $mascaraAcc = MascaraResolver::resolveForAluno($alunoIdAtual);
            if (!empty($mascaraAcc['active']) || $eiAdaptedRulesSnapshot !== []) {
                $acessibilidadeAtiva = true;
                $rulesAcc = array_merge($eiAdaptedRulesSnapshot, !empty($mascaraAcc['rules']) ? $mascaraAcc['rules'] : []);

                // Tempo extra: aplicado fora do modo bloco (evento com término compartilhado)
                $pctExtra = MascaraResolver::extraTimePct($rulesAcc);
                if ($pctExtra > 0 && !$modoBloco && $tempoLimite !== null) {
                    $gasto = max(0, (int) $tempoLimite - (int) ($tempoRestante ?? $tempoLimite));
                    $tempoLimite = (int) ceil((int) $tempoLimite * (1 + $pctExtra / 100));
                    $tempoRestante = max(0, $tempoLimite - $gasto);
                }

                $accommodationRelaxSecure = MascaraResolver::requiresSecureRelax($rulesAcc);
                $acessibilidade_auto_read = MascaraResolver::isOn($rulesAcc, 'visual_auto_read_page');
                $acessibilidade_tts = MascaraResolver::isOn($rulesAcc, 'enable_tts')
                    || MascaraResolver::isOn($rulesAcc, 'visual_read_aloud')
                    || $acessibilidade_auto_read;
                $readSpeed = (string) ($rulesAcc['visual_read_speed'] ?? 'normal');
                $acessibilidade_read_speed = in_array($readSpeed, ['lenta', 'normal', 'rapida'], true) ? $readSpeed : 'normal';
                $acessibilidade_highlight = MascaraResolver::isOn($rulesAcc, 'highlight_keywords');
                $acessibilidade_glossary = MascaraResolver::isOn($rulesAcc, 'glossary_enabled');
                $acessibilidade_audio = MascaraResolver::isOn($rulesAcc, 'allow_audio_answer');
                $acessibilidade_progress = MascaraResolver::isOn($rulesAcc, 'progress_indicator');
                // Pausar timer: só faz sentido fora de bloco (bloco tem término compartilhado)
                $acessibilidade_pause = !$modoBloco && MascaraResolver::isOn($rulesAcc, 'allow_pause_timer');
                // Ocultar cronômetro: esconde o relógio (o tempo continua valendo e finaliza ao zerar)
                $acessibilidade_hide_timer = MascaraResolver::isOn($rulesAcc, 'hide_timer');
                [$acessibilidade_style, $acessibilidade_wrapper_class] = $this->buildAccessibilityHead($rulesAcc);

                // Adaptação significativa: reduzir alternativas mantendo a correta (determinístico por aluno).
                $keepOptions = MascaraResolver::reduceOptionsKeep($rulesAcc);
                if ($keepOptions >= 2 && !empty($questoes)) {
                    foreach ($questoes as &$qReduce) {
                        if (($qReduce['tipo'] ?? '') === 'multipla_escolha'
                            && !empty($qReduce['alternativas'])
                            && count($qReduce['alternativas']) > $keepOptions) {
                            $qReduce['alternativas'] = $this->reduceAlternativas($qReduce['alternativas'], $keepOptions, $alunoIdAtual);
                        }
                    }
                    unset($qReduce);
                }

                try {
                    require_once __DIR__ . '/../../Models/EducaInclui/VersaoAdaptadaLog.php';
                    (new VersaoAdaptadaLog())->record('prova_adaptada_entregue', [
                        'mascara_id' => (int) ($mascaraAcc['mascara_id'] ?? $eiAdaptedAccommodationId),
                        'aluno_id' => $alunoIdAtual,
                        'prova_id' => (int) $id,
                        'user_id' => $alunoIdAtual,
                        'details' => [
                            'rules' => array_keys($rulesAcc),
                            'extra_time_pct' => $pctExtra,
                            'reduce_options' => $keepOptions,
                        ],
                    ]);
                } catch (Throwable $eLog) {
                    // auditoria não pode quebrar a prova
                }
            }
        } catch (Throwable $eAcc) {
            error_log('EducaInclui realizar(): ' . $eAcc->getMessage());
        }

        $modoSeguro = !isset($_GET['modo_seguro']) || $_GET['modo_seguro'] != '0';
        // Acessibilidade: leitor de tela/navegação por teclado exige relaxar o modo seguro (anti-cola)
        if ($accommodationRelaxSecure) {
            $modoSeguro = false;
        }
        $modoEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';
        $blocoTerminoIso = null;
        if ($modoBloco && $blocoProva && !empty($blocoProva['data_prova']) && !empty($blocoProva['hora_fim'])) {
            $blocoTerminoIso = $blocoProva['data_prova'] . ' ' . $blocoProva['hora_fim'];
        }
        $logProvaToken = $this->gerarTokenLogProva((int) $user['id'], (int) $id, $modoBloco ? (int) ($blocoProva['id'] ?? 0) : 0);
        $data = [
            'title' => $modoBloco ? 'Realizar Bloco de Provas - EducaTudo' : 'Realizar Prova - EducaTudo',
            'user' => $user,
            'log_prova_token' => $logProvaToken,
            'prova' => $prova,
            'questoes' => $questoes,
            'respostas' => $respostasMap,
            'realizacao' => $realizacao,
            'tempo_limite' => $tempoLimite,
            'tempo_restante' => $tempoRestante,
            'modo_bloco' => $modoBloco,
            'modo_seguro' => $modoSeguro,
            'modo_embed' => $modoEmbed,
            'acessibilidade_ativa' => $acessibilidadeAtiva,
            'acessibilidade_style' => $acessibilidade_style,
            'acessibilidade_wrapper_class' => $acessibilidade_wrapper_class,
            'acessibilidade_tts' => $acessibilidade_tts,
            'acessibilidade_auto_read' => $acessibilidade_auto_read,
            'acessibilidade_read_speed' => $acessibilidade_read_speed,
            'acessibilidade_highlight' => $acessibilidade_highlight,
            'acessibilidade_glossary' => $acessibilidade_glossary,
            'acessibilidade_audio' => $acessibilidade_audio,
            'acessibilidade_progress' => $acessibilidade_progress,
            'acessibilidade_pause' => $acessibilidade_pause,
            'acessibilidade_hide_timer' => $acessibilidade_hide_timer,
            'bloco' => $modoBloco ? $blocoProva : null,
            'bloco_termino_iso' => $blocoTerminoIso,
            'provas_bloco' => $modoBloco ? $provasBloco : null,
            'current_page' => 'provas',
            'additional_css' => '',
            'additional_js' => '<script>window.MathJax={tex:{inlineMath:[["$","$"],["\\\\(","\\\\)"]],displayMath:[["$$","$$"],["\\\\[","\\\\]"]],processEscapes:true},svg:{fontCache:"global"}};</script>'
                . '<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js"></script>'
                . '<script>'
                . '(function(){function run(){if(window.MathJax&&window.MathJax.typesetPromise){MathJax.typesetPromise().catch(function(e){console.warn("MathJax:",e);});}}'
                . 'run();setTimeout(run,300);setTimeout(run,800);'
                . 'if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",run);else run();'
                . 'window.renderMathProva=run;})();'
                . '</script>',
        ];
            
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                    error_log("DEBUG realizar(): Renderizando view...");
            
                }
            
            }
            $layout = 'student_exam_secure';
            $this->viewWithLayout($layout, 'student/exams/realizar', $data);
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG realizar(): View renderizada com sucesso!");
                }
            }
            
        } catch (Exception $e) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG realizar(): EXCEÇÃO CAPTURADA: " . $e->getMessage());
                }
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG realizar(): Stack trace: " . $e->getTraceAsString());
                }
            }
            $this->setFlashMessage('Erro ao acessar a prova: ' . $e->getMessage(), 'error');
            $this->redirect('/aluno/provas');
        }
    }
    
    /**
     * Salva resposta do aluno
     */
    public function salvarResposta($id)
    {
        $user = $this->auth->getUser();
        
        if ($user['tipo'] !== 'aluno') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        try {
            // Lê dados JSON do body
            $input = file_get_contents('php://input');
            $postData = json_decode($input, true);
            
            if (!$postData) {
                $postData = $_POST;
            }
            
            if (empty($postData['questao_id'])) {
                $this->json(['error' => 'Questão é obrigatória'], 400);
                return;
            }
            
            // Se está em modo bloco, precisa encontrar a prova correta da questão
            $provaIdParaSalvar = $id;
            if (!empty($postData['prova_id_original'])) {
                // A questão pertence a outra prova do bloco
                $provaIdParaSalvar = $postData['prova_id_original'];
            } else {
                // Busca a prova da questão
                $questao = $this->db->fetch(
                    "SELECT prova_id FROM provas_questoes WHERE id = :questao_id",
                    ['questao_id' => $postData['questao_id']]
                );
                if ($questao) {
                    $provaIdParaSalvar = $questao['prova_id'];
                }
            }

            $blocoDaProva = $this->db->fetch(
                "SELECT pb.* FROM provas_blocos pb
                 INNER JOIN provas_blocos_vinculo pbp ON pb.id = pbp.bloco_id
                 WHERE pbp.prova_id = :prova_id AND pb.deleted_at IS NULL
                 LIMIT 1",
                ['prova_id' => $provaIdParaSalvar]
            );
            if ($blocoDaProva && !$this->alunoPodeVerBlocoNoPortalLinha($blocoDaProva)) {
                $this->json(['error' => 'Este evento de provas não está disponível no portal para os alunos.'], 403);
                return;
            }
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $this->provaModel->salvarResposta(
                $provaIdParaSalvar,
                $user['id'],
                $postData['questao_id'],
                $postData['alternativa_id'] ?? null,
                $postData['resposta_texto'] ?? null,
                $ip,
                $userAgent
            );
            
            $this->json([
                'success' => true,
                'message' => 'Resposta salva'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao salvar resposta: " . $e->getMessage());
            $this->registrarLogProva('erro_salvar_resposta', [
                'prova_id' => $provaIdParaSalvar ?? $id,
                'detalhe' => $e->getMessage(),
            ]);
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Finaliza prova para aprovação (professor)
     */
    public function finalizarProva($id)
    {
        // Limpa qualquer output anterior
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Desabilita exibição de erros para não quebrar o JSON
        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', 0);
        
        try {
            $user = $this->auth->getUser();
            
            if ($user['tipo'] !== 'professor') {
                $this->json(['error' => 'Não autorizado'], 403);
                return;
            }
            
            // Verifica se pode editar
            if (!$this->provaModel->canEdit($id, $user['id'])) {
                throw new Exception('Você não tem permissão para finalizar esta prova');
            }
            
            $prova = $this->provaModel->findById($id);
            if (!$prova) {
                throw new Exception('Prova não encontrada');
            }
            
            // Verifica se a prova tem questões
            $questoes = $this->provaModel->getQuestoes($id);
            if (empty($questoes)) {
                throw new Exception('A prova deve ter pelo menos uma questão antes de ser finalizada');
            }
            
            $totalQuestoes = count($questoes);
            
            // Quantidade obrigatória (bloco: número definido por professor/matéria)
            $numeroObrigatorio = 0;
            $configBloco = $this->db->fetch(
                "SELECT numero_questoes FROM provas_professores WHERE prova_id = :prova_id LIMIT 1",
                ['prova_id' => $id]
            );
            if ($configBloco && isset($configBloco['numero_questoes']) && (int)$configBloco['numero_questoes'] > 0) {
                $numeroObrigatorio = (int)$configBloco['numero_questoes'];
            } else {
                $blocoQtd = $this->db->fetch(
                    "SELECT pbp.quantidade_questoes
                     FROM provas_blocos_vinculo pbv
                     INNER JOIN provas_blocos_professores pbp ON pbp.bloco_id = pbv.bloco_id
                       AND pbp.professor_id = :professor_id AND pbp.materia_id = :materia_id
                     WHERE pbv.prova_id = :prova_id
                     LIMIT 1",
                    ['prova_id' => $id, 'professor_id' => $prova['professor_id'], 'materia_id' => $prova['materia_id']]
                );
                if ($blocoQtd && isset($blocoQtd['quantidade_questoes']) && (int)$blocoQtd['quantidade_questoes'] > 0) {
                    $numeroObrigatorio = (int)$blocoQtd['quantidade_questoes'];
                }
            }
            
            if ($numeroObrigatorio > 0) {
                if ($totalQuestoes < $numeroObrigatorio) {
                    $faltam = $numeroObrigatorio - $totalQuestoes;
                    throw new Exception("Faltam {$faltam} questão(ões). O total deve ser {$numeroObrigatorio} (inclui questões feitas à mão e por IA).");
                }
                if ($totalQuestoes > $numeroObrigatorio) {
                    $excesso = $totalQuestoes - $numeroObrigatorio;
                    throw new Exception("Há {$excesso} questão(ões) a mais. Remova {$excesso} questão(ões) para finalizar. O total permitido é {$numeroObrigatorio}.");
                }
            }
            
            // Valida transição de status permitida (rascunho, agendada, pendente = ainda não enviada; reprovada = pode reenviar)
            $statusPermitidos = ['rascunho', 'agendada', 'reprovada', 'pendente'];
            if (!in_array($prova['status'], $statusPermitidos)) {
                throw new Exception('Esta prova não pode ser reenviada no status atual.');
            }

            // Verifica se a prova está vinculada a um bloco
            $blocoVinculado = $this->db->fetch(
                "SELECT bloco_id FROM provas_blocos_vinculo WHERE prova_id = :prova_id LIMIT 1",
                ['prova_id' => $id]
            );

            // Atualiza status para enviada
            $this->db->query(
                "UPDATE provas SET status = 'enviada', liberada = 0, data_envio = NOW() WHERE id = :id",
                ['id' => $id]
            );

            error_log("Prova {$id} enviada para coordenação pelo professor {$user['id']}.");

            $this->json([
                'success' => true,
                'message' => 'Prova enviada com sucesso! Aguardando aprovação da coordenação.'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao finalizar prova: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        } finally {
            // Restaura display_errors
            ini_set('display_errors', $oldDisplayErrors);
        }
    }
    
    /**
     * Libera prova para alunos (apenas admin/coordenação)
     */
    /**
     * Reprova uma prova (admin/coordenação)
     */
    public function reprovar($id)
    {
        $user = $this->auth->getUser();

        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        try {
            if (ob_get_length()) {
                ob_clean();
            }
            ini_set('display_errors', 0);
            error_reporting(0);

            $input  = json_decode(file_get_contents('php://input'), true) ?: [];
            $motivo = trim($input['motivo_reprovacao'] ?? '');

            if (empty($motivo)) {
                throw new Exception('O motivo da reprovação é obrigatório.');
            }

            $prova = $this->provaModel->findById($id);

            if (!$prova) {
                throw new Exception('Prova não encontrada.');
            }

            if ($prova['status'] === 'aprovada') {
                throw new Exception('Não é possível reprovar uma prova já aprovada.');
            }

            $this->db->query(
                "UPDATE provas
                 SET status            = 'reprovada',
                     liberada          = 0,
                     motivo_reprovacao = :motivo,
                     coordenador_id    = :coord_id,
                     data_reprovacao   = NOW()
                 WHERE id = :id",
                [
                    'motivo'   => $motivo,
                    'coord_id' => $user['id'],
                    'id'       => $id,
                ]
            );

            error_log("Prova {$id} reprovada pelo coordenador {$user['id']}. Motivo: {$motivo}");

            $this->json([
                'success' => true,
                'message' => 'Prova reprovada com sucesso.',
            ]);

        } catch (Exception $e) {
            error_log("Erro ao reprovar prova: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Remover prova: retira do bloco (alunos não veem mais) e exclui para o professor.
     * Exige confirmação com senha do admin.
     */
    public function remover($id)
    {
        $user = $this->auth->getUser();

        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        try {
            if (ob_get_length()) {
                ob_clean();
            }
            ini_set('display_errors', 0);
            error_reporting(0);

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $senha = $input['senha'] ?? '';
            $motivo = trim($input['motivo'] ?? '');

            if ($senha === '') {
                $this->json(['error' => 'Digite sua senha para confirmar a remoção.'], 400);
                return;
            }

            $usuario = $this->db->fetch(
                "SELECT senha_hash FROM usuarios WHERE id = :id",
                ['id' => $user['id']]
            );
            if (!$usuario || !password_verify($senha, $usuario['senha_hash'] ?? '')) {
                $this->json(['error' => 'Senha incorreta. Tente novamente.'], 400);
                return;
            }

            $prova = $this->provaModel->findById($id);
            if (!$prova) {
                $this->json(['error' => 'Prova não encontrada.'], 404);
                return;
            }

            // Remove do vínculo com blocos (prova deixa de aparecer para os alunos)
            $this->db->query(
                "DELETE FROM provas_blocos_vinculo WHERE prova_id = :prova_id",
                ['prova_id' => (int)$id]
            );

            // Soft delete: prova deixa de aparecer para o professor e no sistema
            $this->provaModel->delete($id);

            if ($motivo !== '') {
                error_log("Prova {$id} removida pelo admin {$user['id']}. Motivo: {$motivo}");
            } else {
                error_log("Prova {$id} removida pelo admin {$user['id']}.");
            }

            $this->json([
                'success' => true,
                'message' => 'Prova removida. Ela foi retirada do bloco e não aparecerá mais para alunos nem para o professor.',
            ]);
        } catch (Exception $e) {
            error_log("Erro ao remover prova: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Retorna prova ao professor com observações
     */
    public function retornar($id)
    {
        ini_set('display_errors', 0);
        error_reporting(0);
        if (ob_get_length()) {
            ob_clean();
        }

        $user = $this->auth->getUser();

        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $observacao = trim($input['observacao'] ?? '');

            if (empty($observacao)) {
                throw new Exception('Observação é obrigatória');
            }

            $prova = $this->provaModel->findById($id);

            if (!$prova) {
                throw new Exception('Prova não encontrada');
            }

            // Verifica se a prova está enviada para aprovação
            $statusAtual = $prova['status'] ?? '';
            if (!in_array($statusAtual, ['enviada', 'aguardando_aprovacao'])) {
                throw new Exception('Apenas provas enviadas para aprovação podem ser retornadas');
            }

            // Verifica colunas disponíveis para observação
            $colunasProva = $this->db->fetchAll("SHOW COLUMNS FROM provas");
            $nomesColunas = array_column($colunasProva, 'Field');
            $temObservacao = in_array('observacao_coordenacao', $nomesColunas);
            $temObservacaoData = in_array('observacao_coordenacao_data', $nomesColunas);

            if ($temObservacao) {
                $sql = "UPDATE provas SET status = 'rascunho', liberada = 0, observacao_coordenacao = :observacao";
                $params = ['id' => $id, 'observacao' => $observacao];
                if ($temObservacaoData) {
                    $sql .= ", observacao_coordenacao_data = NOW()";
                }
                $sql .= " WHERE id = :id";
                $this->db->query($sql, $params);
            } else {
                $this->db->query(
                    "UPDATE provas SET status = 'rascunho', liberada = 0 WHERE id = :id",
                    ['id' => $id]
                );
                error_log("Observação coordenação para prova {$id}: {$observacao}");
            }

            $this->json([
                'success' => true,
                'message' => 'Prova retornada ao professor com sucesso'
            ]);

        } catch (Exception $e) {
            error_log("Erro ao retornar prova: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Gera e faz download do PDF da prova completa.
     */
    public function gerarPdf($id)
    {
        $user = $this->auth->getUser();

        if (!in_array($user['tipo'], ['admin', 'admin_escola', 'professor'])) {
            http_response_code(403);
            exit('Sem permissão');
        }

        $prova = $this->provaModel->findById($id);
        if (!$prova) {
            http_response_code(404);
            exit('Prova não encontrada');
        }
        // Não gera PDF de prova excluída (reprovada) ou removida (soft delete)
        if (($prova['status'] ?? '') === 'reprovada' || !empty($prova['deleted_at'])) {
            http_response_code(404);
            exit('Prova excluída ou removida. Não é possível gerar o PDF.');
        }

        $questoes = $this->provaModel->getQuestoes($id);
        foreach ($questoes as &$q) {
            if ($q['tipo'] === 'multipla_escolha') {
                $q['alternativas'] = $this->provaModel->getAlternativas($q['id']);
            }
        }
        unset($q);

        $this->embedQuestoesImagensForPdf($questoes);
        $html = $this->renderPdfTemplate($prova, $questoes);

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'prova_' . $id . '_' . date('Ymd') . '.pdf';
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }

    /**
     * Página HTML para impressão / Salvar como PDF (prova única).
     * As imagens carregam via URL no navegador; ao imprimir → Salvar como PDF as imagens aparecem.
     * GET /admin/provas/{id}/imprimir
     */
    public function verImpressao($id)
    {
        $user = $this->auth->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola', 'professor'])) {
            http_response_code(403);
            exit('Sem permissão');
        }

        $prova = $this->provaModel->findById($id);
        if (!$prova) {
            http_response_code(404);
            exit('Prova não encontrada');
        }
        if (($prova['status'] ?? '') === 'reprovada' || !empty($prova['deleted_at'])) {
            http_response_code(404);
            exit('Prova excluída ou removida.');
        }

        $questoes = $this->provaModel->getQuestoes($id);
        foreach ($questoes as &$q) {
            if ($q['tipo'] === 'multipla_escolha') {
                $q['alternativas'] = $this->provaModel->getAlternativas($q['id']);
            }
        }
        unset($q);

        $this->viewWithLayout('partial', 'admin/exams/imprimir-prova', [
            'prova' => $prova,
            'questoes' => $questoes,
        ]);
    }

    /**
     * Página HTML para impressão / Salvar como PDF (prova completa do bloco).
     * GET /admin/provas/blocos/{id}/imprimir
     */
    public function verImpressaoBlocoCompleto($blocoId)
    {
        $user = $this->auth->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            http_response_code(403);
            exit('Sem permissão');
        }

        $bloco = $this->db->fetch(
            "SELECT id, titulo, data_prova, hora_inicio, hora_fim FROM provas_blocos WHERE id = :id",
            ['id' => $blocoId]
        );
        if (!$bloco) {
            http_response_code(404);
            exit('Bloco não encontrado');
        }

        $provas = $this->db->fetchAll(
            "SELECT p.*, pr.nome as professor_nome, m.nome as materia_nome, pbp.ordem
             FROM provas_blocos_vinculo pbp
             INNER JOIN provas p ON pbp.prova_id = p.id
             LEFT JOIN professores pr ON p.professor_id = pr.id
             LEFT JOIN materias m ON p.materia_id = m.id
             WHERE pbp.bloco_id = :bloco_id
             AND p.deleted_at IS NULL
             AND (p.status IS NULL OR p.status != 'reprovada')
             ORDER BY pbp.ordem ASC, m.nome ASC",
            ['bloco_id' => $blocoId]
        );

        foreach ($provas as &$prova) {
            $questoes = $this->provaModel->getQuestoes($prova['id']);
            foreach ($questoes as &$q) {
                if ($q['tipo'] === 'multipla_escolha') {
                    $q['alternativas'] = $this->provaModel->getAlternativas($q['id']);
                }
            }
            unset($q);
            $prova['questoes'] = $questoes;
        }
        unset($prova);

        $this->viewWithLayout('partial', 'admin/exams/blocks/imprimir-completo', [
            'bloco' => $bloco,
            'provas' => $provas,
        ]);
    }

    /**
     * Converte imagem_url da questão em data URI (base64) para o PDF não depender de URL externa.
     * Aceita: /media/serve?type=provas_questoes&key=... (S3/local), ver-imagem-questao?f=..., ou nome de arquivo.
     */
    private function imagemQuestaoToDataUri(string $imagemUrl): ?string
    {
        $key = '';
        // URL do media/serve (upload retorna: type=provas_questoes&key=teacher/123/questao_xxx.jpg)
        if (strpos($imagemUrl, 'type=provas_questoes') !== false && preg_match('/[?&]key=([^&]+)/', $imagemUrl, $m)) {
            $key = trim(rawurldecode($m[1]));
        } elseif (preg_match('/[?&]f=([^&]+)/', $imagemUrl, $m)) {
            $key = trim($m[1]);
        } elseif (preg_match('/\.(jpe?g|png|gif|webp)$/i', $imagemUrl)) {
            $path = parse_url($imagemUrl, PHP_URL_PATH);
            $key = $path !== null && $path !== '' ? basename($path) : trim($imagemUrl);
        } else {
            $key = trim($imagemUrl);
        }
        if ($key === '' || strpos($key, '..') !== false || preg_match('/[^a-zA-Z0-9_\-\.\/]/', $key)) {
            return null;
        }
        if (!class_exists('MediaStorageService')) {
            require_once __DIR__ . '/../../Services/MediaStorageService.php';
        }
        $media = new MediaStorageService($this->config);
        $content = $media->getContents('provas_questoes', $key);
        if ($content === null || $content === '') {
            return null;
        }
        $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        $mime = $mimes[$ext] ?? 'application/octet-stream';
        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    /**
     * Substitui imagem_url das questões por data URI para exibição no PDF.
     */
    private function embedQuestoesImagensForPdf(array &$questoes): void
    {
        foreach ($questoes as &$q) {
            if (!empty($q['imagem_url'])) {
                $dataUri = $this->imagemQuestaoToDataUri($q['imagem_url']);
                if ($dataUri !== null) {
                    $q['imagem_url'] = $dataUri;
                }
            }
        }
        unset($q);
    }

    /**
     * Remove bytes inválidos para evitar notices de iconv/mbstring no pipeline do PDF.
     */
    private function sanitizeUtf8Value($value): string
    {
        if ($value === null) {
            return '';
        }

        if (!is_string($value)) {
            $value = (string) $value;
        }

        if ($value === '') {
            return '';
        }

        if (preg_match('//u', $value) !== 1) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            } else {
                $value = utf8_encode(utf8_decode($value));
            }
        }

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    }

    private function sanitizePdfHtmlFragment($value): string
    {
        return $this->sanitizeUtf8Value($value);
    }

    private function safePdfText($value): string
    {
        return htmlspecialchars($this->sanitizeUtf8Value($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Conteúdo HTML de uma prova para PDF do ALUNO (sem gabarito): alternativas sem marcar correta.
     */
    private function renderPdfProvaContentAluno(array $prova, array $questoes): string
    {
        if (!class_exists('LayoutHelper')) {
            require_once __DIR__ . '/../../Core/LayoutHelper.php';
        }
        ob_start();
        ?>
<h1><?= $this->safePdfText($prova['titulo'] ?? '') ?></h1>
<div class="meta">
    <strong>Disciplina:</strong> <?= $this->safePdfText($prova['materia_nome'] ?? '—') ?>
</div>
<?= $this->renderPdfQuestoesAluno($questoes) ?>
        <?php
        return ob_get_clean();
    }

    private function renderPdfQuestoesAluno(array $questoes): string
    {
        if (!class_exists('LayoutHelper')) {
            require_once __DIR__ . '/../../Core/LayoutHelper.php';
        }
        $letras = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
        ob_start();
        foreach ($questoes as $i => $q): ?>
<div class="questao">
    <div class="questao-num">
        Questão <?= $i + 1 ?>
        <span class="badge"><?= $this->safePdfText(ucfirst($this->sanitizeUtf8Value($q['dificuldade'] ?? ''))) ?></span>
        &nbsp;<?= number_format((float)($q['valor'] ?? 0), 1, ',', '') ?> pt(s)
    </div>
    <div class="enunciado"><?= LayoutHelper::enunciadoParaPdf($this->sanitizePdfHtmlFragment($q['enunciado'] ?? '')) ?></div>
    <?php if (!empty($q['imagem_url'])): ?>
        <img src="<?= $this->safePdfText($q['imagem_url']) ?>" style="max-height:180px;">
    <?php endif; ?>
    <?php if (($q['tipo'] ?? '') === 'multipla_escolha' && !empty($q['alternativas'])): ?>
    <div class="alternativas">
        <?php foreach ($q['alternativas'] as $idx => $alt): ?>
        <div class="alternativa">
            (<?= $letras[$idx] ?? ($idx + 1) ?>) <?= LayoutHelper::enunciadoParaPdf($this->sanitizePdfHtmlFragment($alt['texto'] ?? '')) ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php
        endforeach;
        return (string) ob_get_clean();
    }

    /**
     * Conteúdo HTML de uma prova para PDF (título, meta, questões). Usado por gerarPdf e gerarPdfBlocoCompleto.
     */
    private function renderPdfProvaContent(array $prova, array $questoes): string
    {
        if (!class_exists('LayoutHelper')) {
            require_once __DIR__ . '/../../Core/LayoutHelper.php';
        }
        ob_start();
        ?>
<h1><?= $this->safePdfText($prova['titulo'] ?? '') ?></h1>
<div class="meta">
    <strong>Professor:</strong> <?= $this->safePdfText($prova['professor_nome'] ?? '—') ?> &nbsp;|&nbsp;
    <strong>Disciplina:</strong> <?= $this->safePdfText($prova['materia_nome'] ?? '—') ?> &nbsp;|&nbsp;
    <strong>Data:</strong> <?= !empty($prova['data_inicio']) ? date('d/m/Y', strtotime($prova['data_inicio'])) : '—' ?>
    <?php if (!empty($prova['hora_inicio'])): ?> às <?= $this->safePdfText(substr((string) $prova['hora_inicio'], 0, 5)) ?><?php endif; ?>
</div>
<?php foreach ($questoes as $i => $q): ?>
<div class="questao">
    <div class="questao-num">
        Questão <?= $i + 1 ?>
        <span class="badge"><?= $this->safePdfText(ucfirst($this->sanitizeUtf8Value($q['dificuldade'] ?? ''))) ?></span>
        &nbsp;<?= number_format((float)($q['valor'] ?? 0), 1, ',', '') ?> pt(s)
    </div>
    <div class="enunciado"><?= LayoutHelper::enunciadoParaPdf($this->sanitizePdfHtmlFragment($q['enunciado'] ?? '')) ?></div>
    <?php if (!empty($q['imagem_url'])): ?>
        <img src="<?= $this->safePdfText($q['imagem_url']) ?>" style="max-height:180px;">
    <?php endif; ?>
    <?php if ($q['tipo'] === 'multipla_escolha' && !empty($q['alternativas'])): ?>
    <div class="alternativas">
        <?php foreach ($q['alternativas'] as $alt): ?>
        <div class="alternativa <?= $alt['correta'] ? 'correta' : '' ?>">
            <?= $alt['correta'] ? '✓' : '◦' ?> <?= LayoutHelper::enunciadoParaPdf($this->sanitizePdfHtmlFragment($alt['texto'] ?? '')) ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
        <?php
        return ob_get_clean();
    }

    private function renderPdfTemplate(array $prova, array $questoes): string
    {
        $content = $this->renderPdfProvaContent($prova, $questoes);
        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 30px; }
    h1 { font-size: 18px; margin-bottom: 4px; }
    .meta { color: #555; font-size: 11px; margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 8px; }
    .questao { margin-bottom: 22px; border-top: 1px solid #e0e0e0; padding-top: 12px; }
    .questao-num { font-weight: bold; font-size: 13px; margin-bottom: 6px; color: #1a1a1a; }
    .enunciado { margin-bottom: 10px; line-height: 1.6; }
    .alternativas { padding-left: 18px; }
    .alternativa { margin-bottom: 5px; }
    .correta { font-weight: bold; color: #16a34a; }
    .badge { display: inline-block; font-size: 10px; padding: 1px 7px; border-radius: 4px; background: #e5e7eb; margin-left: 6px; }
    img { max-width: 100%; margin: 6px 0; }
    .prova-section { page-break-before: always; }
    .prova-section:first-of-type { page-break-before: auto; }
</style></head><body>' . $content . '</body></html>';
    }

    private function renderPdfTemplateProvasBimestrais(array $provas, string $professorNome): string
    {
        if (!class_exists('LayoutHelper')) {
            require_once __DIR__ . '/../../Core/LayoutHelper.php';
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 28px; }
        h1 { font-size: 22px; margin: 0 0 8px 0; }
        h2 { font-size: 18px; margin: 0 0 6px 0; }
        .cover { margin-bottom: 26px; border-bottom: 1px solid #d1d5db; padding-bottom: 14px; }
        .cover p { margin: 4px 0; color: #4b5563; }
        .prova-section { page-break-before: always; }
        .prova-section:first-of-type { page-break-before: auto; }
        .meta { color: #555; font-size: 11px; margin-bottom: 18px; padding-bottom: 8px; border-bottom: 1px solid #ccc; }
        .questao { margin-bottom: 22px; border-top: 1px solid #e0e0e0; padding-top: 12px; }
        .questao-num { font-weight: bold; font-size: 13px; margin-bottom: 6px; color: #1a1a1a; }
        .enunciado { margin-bottom: 10px; line-height: 1.6; }
        .alternativas { padding-left: 18px; }
        .alternativa { margin-bottom: 5px; }
        .badge { display: inline-block; font-size: 10px; padding: 1px 7px; border-radius: 4px; background: #e5e7eb; margin-left: 6px; }
        img { max-width: 100%; margin: 6px 0; }
    </style>
</head>
<body>
    <div class="cover">
        <h1>Provas Bimestrais</h1>
        <p><strong>Professor:</strong> <?= $this->safePdfText($professorNome) ?></p>
        <p><strong>Total de provas:</strong> <?= count($provas) ?></p>
        <p><strong>Gerado em:</strong> <?= date('d/m/Y H:i') ?></p>
    </div>
    <?php foreach ($provas as $prova): ?>
        <div class="prova-section">
            <h2><?= $this->safePdfText($prova['titulo'] ?? 'Prova') ?></h2>
            <div class="meta">
                <strong>Disciplina:</strong> <?= $this->safePdfText($prova['materia_nome'] ?? '—') ?> &nbsp;|&nbsp;
                <strong>Turma(s):</strong> <?= $this->safePdfText($prova['turmas_exibicao'] ?? '—') ?> &nbsp;|&nbsp;
                <strong>Data:</strong> <?= !empty($prova['data_inicio']) ? date('d/m/Y', strtotime($prova['data_inicio'])) : '—' ?>
            </div>
            <?= $this->renderPdfQuestoesAluno($prova['questoes'] ?? []) ?>
        </div>
    <?php endforeach; ?>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Gera um único PDF com todas as provas do bloco (admin).
     * GET /admin/provas/blocos/{id}/pdf-completo
     */
    public function gerarPdfBlocoCompleto($blocoId)
    {
        $user = $this->auth->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            http_response_code(403);
            exit('Sem permissão');
        }

        $bloco = $this->db->fetch(
            "SELECT id, titulo, data_prova, hora_inicio, hora_fim FROM provas_blocos WHERE id = :id",
            ['id' => $blocoId]
        );
        if (!$bloco) {
            http_response_code(404);
            exit('Bloco não encontrado');
        }

        $provas = $this->db->fetchAll(
            "SELECT p.*, pr.nome as professor_nome, m.nome as materia_nome, pbp.ordem
             FROM provas_blocos_vinculo pbp
             INNER JOIN provas p ON pbp.prova_id = p.id
             LEFT JOIN professores pr ON p.professor_id = pr.id
             LEFT JOIN materias m ON p.materia_id = m.id
             WHERE pbp.bloco_id = :bloco_id
             AND p.deleted_at IS NULL
             AND (p.status IS NULL OR p.status != 'reprovada')
             ORDER BY pbp.ordem ASC, m.nome ASC",
            ['bloco_id' => $blocoId]
        );

        foreach ($provas as &$prova) {
            $questoes = $this->provaModel->getQuestoes($prova['id']);
            foreach ($questoes as &$q) {
                if ($q['tipo'] === 'multipla_escolha') {
                    $q['alternativas'] = $this->provaModel->getAlternativas($q['id']);
                }
            }
            unset($q);
            $this->embedQuestoesImagensForPdf($questoes);
            $prova['questoes'] = $questoes;
        }
        unset($prova);

        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 30px; }
    h1 { font-size: 18px; margin-bottom: 4px; }
    .meta { color: #555; font-size: 11px; margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 8px; }
    .questao { margin-bottom: 22px; border-top: 1px solid #e0e0e0; padding-top: 12px; }
    .questao-num { font-weight: bold; font-size: 13px; margin-bottom: 6px; color: #1a1a1a; }
    .enunciado { margin-bottom: 10px; line-height: 1.6; }
    .alternativas { padding-left: 18px; }
    .alternativa { margin-bottom: 5px; }
    .correta { font-weight: bold; color: #16a34a; }
    .badge { display: inline-block; font-size: 10px; padding: 1px 7px; border-radius: 4px; background: #e5e7eb; margin-left: 6px; }
    img { max-width: 100%; margin: 6px 0; }
    .prova-section { page-break-before: always; }
    .prova-section:first-of-type { page-break-before: auto; }
</style></head><body>';
        $html .= '<h1>Bloco: ' . htmlspecialchars($bloco['titulo']) . '</h1>';
        $html .= '<div class="meta">Data: ' . date('d/m/Y', strtotime($bloco['data_prova'])) . ' — Horário: ' . date('H:i', strtotime($bloco['hora_inicio'])) . ' às ' . date('H:i', strtotime($bloco['hora_fim'])) . '</div>';

        foreach ($provas as $idx => $prova) {
            $html .= '<div class="prova-section">';
            $html .= $this->renderPdfProvaContent($prova, $prova['questoes'] ?? []);
            $html .= '</div>';
        }
        $html .= '</body></html>';

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $filename = 'bloco_' . $blocoId . '_todas_provas_' . date('Ymd') . '.pdf';
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }

    /**
     * Gera PDF da prova do bloco para o aluno: sem gabarito, com logo da escola e campos Nome/Turma.
     * GET /admin/provas/blocos/{id}/prova-aluno-pdf
     */
    public function gerarPdfProvaAluno($blocoId)
    {
        $user = $this->auth->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            http_response_code(403);
            exit('Sem permissão');
        }

        $bloco = $this->db->fetch(
            "SELECT id, titulo, data_prova, hora_inicio, hora_fim FROM provas_blocos WHERE id = :id",
            ['id' => $blocoId]
        );
        if (!$bloco) {
            http_response_code(404);
            exit('Bloco não encontrado');
        }

        $provas = $this->db->fetchAll(
            "SELECT p.*, pr.nome as professor_nome, m.nome as materia_nome, pbp.ordem
             FROM provas_blocos_vinculo pbp
             INNER JOIN provas p ON pbp.prova_id = p.id
             LEFT JOIN professores pr ON p.professor_id = pr.id
             LEFT JOIN materias m ON p.materia_id = m.id
             WHERE pbp.bloco_id = :bloco_id
             AND p.deleted_at IS NULL
             AND (p.status IS NULL OR p.status != 'reprovada')
             ORDER BY pbp.ordem ASC, m.nome ASC",
            ['bloco_id' => $blocoId]
        );

        foreach ($provas as &$prova) {
            $questoes = $this->provaModel->getQuestoes($prova['id']);
            foreach ($questoes as &$q) {
                if ($q['tipo'] === 'multipla_escolha') {
                    $q['alternativas'] = $this->provaModel->getAlternativas($q['id']);
                }
            }
            unset($q);
            $this->embedQuestoesImagensForPdf($questoes);
            $prova['questoes'] = $questoes;
        }
        unset($prova);

        if (!class_exists('LayoutHelper')) {
            require_once __DIR__ . '/../../Core/LayoutHelper.php';
        }
        $logoUrl = LayoutHelper::get('logo_url', '');
        if ($logoUrl !== '' && strpos($logoUrl, 'http') !== 0 && defined('URL')) {
            $logoUrl = rtrim(URL, '/') . '/' . ltrim($logoUrl, '/');
        }

        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 30px; }
    .cabecalho-aluno { margin-bottom: 28px; padding-bottom: 20px; border-bottom: 2px solid #333; }
    .cabecalho-aluno .logo-escola { max-height: 60px; max-width: 220px; margin-bottom: 16px; display: block; }
    .cabecalho-aluno .campos-aluno { margin-top: 16px; font-size: 13px; }
    .cabecalho-aluno .campos-aluno .linha { margin-bottom: 10px; }
    .cabecalho-aluno .campos-aluno .linha label { display: inline-block; width: 80px; font-weight: bold; }
    .cabecalho-aluno .campos-aluno .linha .sublinhado { display: inline-block; min-width: 200px; border-bottom: 1px solid #111; margin-left: 8px; }
    h1 { font-size: 18px; margin-bottom: 4px; }
    .meta { color: #555; font-size: 11px; margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 8px; }
    .questao { margin-bottom: 22px; border-top: 1px solid #e0e0e0; padding-top: 12px; }
    .questao-num { font-weight: bold; font-size: 13px; margin-bottom: 6px; color: #1a1a1a; }
    .enunciado { margin-bottom: 10px; line-height: 1.6; }
    .alternativas { padding-left: 18px; }
    .alternativa { margin-bottom: 5px; }
    .badge { display: inline-block; font-size: 10px; padding: 1px 7px; border-radius: 4px; background: #e5e7eb; margin-left: 6px; }
    img { max-width: 100%; margin: 6px 0; }
    .prova-section { page-break-before: always; }
    .prova-section:first-of-type { page-break-before: auto; }
</style></head><body>';

        $html .= '<div class="cabecalho-aluno">';
        if ($logoUrl !== '') {
            $html .= '<img src="' . htmlspecialchars($logoUrl) . '" class="logo-escola" alt="Logo">';
        }
        $html .= '<div class="campos-aluno">';
        $html .= '<div class="linha"><label>Nome:</label> <span class="sublinhado">&nbsp;</span></div>';
        $html .= '<div class="linha"><label>Turma:</label> <span class="sublinhado">&nbsp;</span></div>';
        $html .= '</div></div>';

        $html .= '<h1>Bloco: ' . htmlspecialchars($bloco['titulo']) . '</h1>';
        $html .= '<div class="meta">Data: ' . date('d/m/Y', strtotime($bloco['data_prova'])) . ' — Horário: ' . date('H:i', strtotime($bloco['hora_inicio'])) . ' às ' . date('H:i', strtotime($bloco['hora_fim'])) . '</div>';

        foreach ($provas as $idx => $prova) {
            $html .= '<div class="prova-section">';
            $html .= $this->renderPdfProvaContentAluno($prova, $prova['questoes'] ?? []);
            $html .= '</div>';
        }
        $html .= '</body></html>';

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $filename = 'prova_aluno_' . $blocoId . '_' . date('Ymd') . '.pdf';
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }

    public function liberarProva($id)
    {
        // Limpa qualquer output anterior
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Desabilita exibição de erros para não quebrar o JSON
        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', 0);
        
        try {
            $user = $this->auth->getUser();
            
            // Apenas admin/coordenação pode liberar
            if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
                $this->json(['error' => 'Não autorizado'], 403);
                return;
            }
            
            $prova = $this->provaModel->findById($id);
            if (!$prova) {
                throw new Exception('Prova não encontrada');
            }
            
            // Verifica se a prova tem questões
            $questoes = $this->provaModel->getQuestoes($id);
            if (empty($questoes)) {
                throw new Exception('A prova deve ter pelo menos uma questão antes de ser liberada');
            }
            
            // Verifica primeiro se a coluna status existe na tabela provas
            $pdo = $this->db->getPdo();
            $checkColumn = $pdo->query("SHOW COLUMNS FROM `provas` LIKE 'status'");
            $columnExists = $checkColumn->fetch(PDO::FETCH_ASSOC);
            
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                    error_log("DEBUG liberarProva(): Verificação de coluna status: " . var_export($columnExists, true));
            
                }
            
            }
            
            if (!$columnExists) {
                // Se a coluna não existe, cria ela
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG liberarProva(): Coluna 'status' não existe na tabela provas. Criando...");
                    }
                }
                try {
                    $pdo->exec("ALTER TABLE `provas` ADD COLUMN `status` VARCHAR(50) DEFAULT 'rascunho' COMMENT 'Status da prova' AFTER `descricao`");
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                            error_log("DEBUG liberarProva(): Coluna 'status' criada com sucesso");
                        }
                    }
                    // Aguarda um momento para garantir que a coluna foi criada
                    usleep(50000);
                } catch (Exception $e) {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                            error_log("DEBUG liberarProva(): Erro ao criar coluna status: " . $e->getMessage());
                        }
                    }
                    throw new Exception('Erro ao criar coluna status na tabela provas: ' . $e->getMessage());
                }
            }
            
            // Verifica primeiro o status atual antes de atualizar
            $provaAntes = $this->db->fetch(
                "SELECT status, liberada FROM provas WHERE id = :id",
                ['id' => $id]
            );
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG liberarProva(): Status ANTES da atualização: " . var_export($provaAntes, true));
                }
            }
            
            // Verifica todas as colunas da tabela para debug
            $allColumns = $pdo->query("SHOW COLUMNS FROM `provas`")->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($allColumns, 'Field');
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG liberarProva(): Colunas da tabela provas: " . implode(', ', $columnNames));
                }
            }
            
            // Atualiza status para 'aprovada' e liberada = 1
            // IMPORTANTE: O status 'aprovada' é o que determina que foi aprovado pela coordenação
            // Usa uma query separada para status para garantir que funciona
            $stmt1 = $pdo->prepare("UPDATE `provas` SET `status` = :status WHERE `id` = :id");
            $result1 = $stmt1->execute(['status' => 'aprovada', 'id' => $id]);
            
            if (!$result1) {
                $errorInfo = $stmt1->errorInfo();
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG liberarProva(): Erro ao atualizar status: " . print_r($errorInfo, true));
                    }
                }
                throw new Exception('Erro ao atualizar status: ' . ($errorInfo[2] ?? 'Erro desconhecido'));
            }
            
            $rowsAffected1 = $stmt1->rowCount();
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG liberarProva(): Linhas afetadas pelo UPDATE de status: {$rowsAffected1}");
                }
            }
            
            // Se nenhuma linha foi afetada, pode ser porque o valor já era 'aprovada' (MySQL/PDO retorna 0 nesse caso)
            if ($rowsAffected1 === 0) {
                $statusAtual = $pdo->prepare("SELECT `status` FROM `provas` WHERE `id` = :id");
                $statusAtual->execute(['id' => $id]);
                $statusValue = $statusAtual->fetchColumn(0);
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG liberarProva(): Status atual no banco (rowCount=0): " . var_export($statusValue, true));
                    }
                }
                // Se já está aprovada, considera sucesso
                if ($statusValue === 'aprovada' || trim((string)$statusValue) === 'aprovada') {
                    $rowsAffected1 = 1; // para passar na verificação abaixo e seguir o fluxo
                } else {
                    // Status vazio/NULL: tenta UPDATE direto
                    if ($statusValue === null || $statusValue === '' || trim((string)$statusValue) === '') {
                        $idEscapado = (int)$id;
                        $rowsAffected1 = $pdo->exec("UPDATE `provas` SET `status` = 'aprovada' WHERE `id` = {$idEscapado}");
                        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                                error_log("DEBUG liberarProva(): Linhas afetadas pela query direta: {$rowsAffected1}");
                            }
                        }
                    }
                }
            }
            
            // Só exige "pelo menos uma linha afetada" se o status ainda não for 'aprovada'
            if ($rowsAffected1 === 0) {
                $stmtCheck = $pdo->prepare("SELECT `status` FROM `provas` WHERE `id` = :id");
                $stmtCheck->execute(['id' => $id]);
                $statusAgora = $stmtCheck->fetchColumn(0);
                if ($statusAgora === 'aprovada' || trim((string)$statusAgora) === 'aprovada') {
                    $rowsAffected1 = 1; // já está aprovada, segue
                } else {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                            error_log("DEBUG liberarProva(): Nenhuma linha foi atualizada para prova ID {$id}. Status atual: " . var_export($statusAgora, true));
                        }
                    }
                    throw new Exception('Nenhuma linha foi atualizada. Verifique se a prova existe e se a coluna status aceita o valor "aprovada".');
                }
            }
            
            // Aguarda um momento para garantir que a atualização foi commitada
            usleep(100000); // 0.1 segundo
            
            // Verifica se a atualização foi bem-sucedida
            // Usa uma query mais direta para garantir que funciona
            $pdo = $this->db->getPdo();
            $stmt = $pdo->prepare("SELECT `status`, `liberada` FROM `provas` WHERE `id` = :id");
            $stmt->execute(['id' => $id]);
            $provaBanco = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                    error_log("DEBUG liberarProva(): Query executada. Resultado: " . var_export($provaBanco, true));
            
                }
            
            }
            
            if (!$provaBanco || $provaBanco === false || empty($provaBanco)) {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG liberarProva(): Prova ID {$id} não encontrada após atualização. Resultado: " . var_export($provaBanco, true));
                    }
                }
                throw new Exception('Prova não encontrada após atualização');
            }
            
            // Garante que é um array associativo
            if (!is_array($provaBanco)) {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG liberarProva(): Resultado não é array. Tipo: " . gettype($provaBanco));
                    }
                }
                throw new Exception('Erro ao buscar dados da prova após atualização');
            }
            
            // Verifica todas as chaves disponíveis
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG liberarProva(): Chaves disponíveis: " . implode(', ', array_keys($provaBanco)));
                }
            }
            
            // Obtém o status - tenta diferentes formas de acesso
            $statusAtual = '';
            if (isset($provaBanco['status'])) {
                $statusAtual = is_string($provaBanco['status']) ? trim($provaBanco['status']) : (string)$provaBanco['status'];
            } elseif (isset($provaBanco[0])) {
                $statusAtual = is_string($provaBanco[0]) ? trim($provaBanco[0]) : (string)$provaBanco[0];
            }
            
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                    error_log("DEBUG liberarProva(): Status obtido: '{$statusAtual}' (tipo: " . gettype($statusAtual) . ", vazio: " . (empty($statusAtual) ? 'sim' : 'não') . ")");
            
                }
            
            }
            
            if (empty($statusAtual) || $statusAtual !== 'aprovada') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG liberarProva(): Status não atualizado corretamente. Esperado: 'aprovada', Obtido: '{$statusAtual}' (tipo: " . gettype($statusAtual) . "). Dados completos: " . json_encode($provaBanco, JSON_UNESCAPED_UNICODE));
                    }
                }
                
                // Tenta verificar diretamente no banco novamente
                $stmt2 = $pdo->prepare("SELECT `status` FROM `provas` WHERE `id` = :id");
                $stmt2->execute(['id' => $id]);
                $statusDireto = $stmt2->fetchColumn(0);
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG liberarProva(): Verificação direta do status: '{$statusDireto}'");
                    }
                }
                
                throw new Exception("Status não foi atualizado corretamente. Status atual: '{$statusAtual}'");
            }
            
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                    error_log("DEBUG liberarProva(): Prova ID {$id} aprovada com sucesso. Status: '{$statusAtual}', Liberada: {$provaBanco['liberada']}");
            
                }
            
            }
            
            // Se a prova está vinculada a um bloco, sincroniza o status
            if (!empty($prova['bloco_id'])) {
                $this->sincronizarStatusProvasBlocos();
            }

            // EducaInclui: ao aprovar a prova, pré-gera as versões adaptadas dos
            // alunos com máscara significativa para já entrarem na fila de aprovação
            // da coordenação (sem depender do aluno abrir a prova). Só prova avulsa.
            $this->pregerarVersoesAdaptadasAvulsas((int) $id);

            $this->json([
                'success' => true,
                'message' => 'Prova aprovada com sucesso! O professor não poderá mais editar. A prova só será liberada para os alunos quando você fizer a aprovação final do bloco.'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao liberar prova: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        } finally {
            // Restaura display_errors
            ini_set('display_errors', $oldDisplayErrors);
        }
    }

    /**
     * EducaInclui — pré-gera versões adaptadas (clones) para alunos com máscara
     * significativa no momento em que a coordenação aprova a prova, para que já
     * entrem na fila de aprovação (/admin/inclusao/versoes) sem depender do aluno
     * abrir a prova.
     *
     * Só vale para provas AVULSAS (fora de bloco/evento): em blocos/eventos seguros
     * a entrega da versão adaptada permanece manual (salvaguarda de término
     * compartilhado). Idempotente — ensureDraft não regenera versões existentes —
     * e tolerante a falhas para nunca quebrar a aprovação da prova.
     */
    private function pregerarVersoesAdaptadasAvulsas(int $provaId): void
    {
        if ($provaId <= 0) {
            return;
        }
        try {
            // Só avulsa: se a prova está em qualquer bloco/evento, não pré-gera.
            $emBloco = $this->db->fetch(
                "SELECT 1 FROM provas_blocos_vinculo WHERE prova_id = :pid LIMIT 1",
                ['pid' => $provaId]
            );
            if ($emBloco) {
                return;
            }

            // Turmas-alvo da prova (mesmo critério da listagem do aluno: turma_id + provas_turmas).
            $prova = $this->db->fetch("SELECT turma_id FROM provas WHERE id = :id", ['id' => $provaId]);
            $turmaIds = [];
            if (!empty($prova['turma_id'])) {
                $turmaIds[] = (int) $prova['turma_id'];
            }
            $rowsPT = $this->db->fetchAll(
                "SELECT turma_id FROM provas_turmas WHERE prova_id = :pid",
                ['pid' => $provaId]
            );
            foreach ($rowsPT as $r) {
                if (!empty($r['turma_id'])) {
                    $turmaIds[] = (int) $r['turma_id'];
                }
            }
            $turmaIds = array_values(array_unique($turmaIds));
            $provaParaTodas = $turmaIds === []; // turma_id NULL e sem provas_turmas = todas as turmas

            require_once __DIR__ . '/../../Services/MascaraResolver.php';
            require_once __DIR__ . '/../../Services/AssessmentVersionGenerator.php';

            // Máscaras ativas e vigentes (poucos alunos): itera por elas, não por turma.
            $masks = $this->db->fetchAll(
                "SELECT aluno_id FROM mascaras_alunos
                 WHERE status = 'ativa'
                   AND (data_inicio IS NULL OR data_inicio <= CURDATE())
                   AND (data_fim IS NULL OR data_fim >= CURDATE())"
            );
            if (empty($masks)) {
                return;
            }

            $gen = new AssessmentVersionGenerator();
            foreach ($masks as $m) {
                $alunoId = (int) ($m['aluno_id'] ?? 0);
                if ($alunoId <= 0) {
                    continue;
                }
                $mask = MascaraResolver::resolveForAluno($alunoId);
                if (empty($mask['active']) || !MascaraResolver::requiresClone($mask['rules'])) {
                    continue;
                }
                // Confere se a prova é deste aluno (turma principal), como na listagem avulsa.
                if (!$provaParaTodas) {
                    $row = $this->db->fetch("SELECT turma_id FROM alunos WHERE id = :id", ['id' => $alunoId]);
                    $turmaAluno = (int) ($row['turma_id'] ?? 0);
                    if ($turmaAluno <= 0 || !in_array($turmaAluno, $turmaIds, true)) {
                        continue;
                    }
                }
                $gen->ensureDraft($provaId, $alunoId, (int) $mask['mascara_id'], $mask['rules']);
            }
        } catch (\Throwable $e) {
            error_log('pregerarVersoesAdaptadasAvulsas: ' . $e->getMessage());
        }
    }

    /**
     * Finaliza prova (aluno). Aceita comprovante_base64 no body para salvar imagem da revisão no S3.
     */
    public function finalizar($id)
    {
        // Garantir resposta sempre em JSON (limpa qualquer output anterior)
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        $id = (int) $id;
        $user = $this->auth->getUser();
        if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        try {
            $input = file_get_contents('php://input');
            $body = is_string($input) ? json_decode($input, true) : null;
            $comprovanteBase64 = isset($body['comprovante_base64']) && $body['comprovante_base64'] !== '' ? $body['comprovante_base64'] : null;
            if ($comprovanteBase64 !== null && is_string($comprovanteBase64)) {
                if (preg_match('/^data:image\/\w+;base64,/', $comprovanteBase64)) {
                    $comprovanteBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $comprovanteBase64);
                }
            }

            // Verifica se está em modo bloco e modo seguro (escolher próxima matéria após cada prova)
            $modoBloco = isset($_GET['modo_bloco']) && $_GET['modo_bloco'] == '1';
            $modoSeguro = !isset($_GET['modo_seguro']) || $_GET['modo_seguro'] != '0';
            $blocoId = null;
            $provasBloco = [];
            $realizacoesParaComprovante = [];

            if ($modoBloco && !$modoSeguro) {
                // Modo bloco tradicional: finaliza todas as provas do bloco de uma vez
                $blocoProva = $this->db->fetch(
                    "SELECT pb.* FROM provas_blocos pb
                     INNER JOIN provas_blocos_vinculo pbp ON pb.id = pbp.bloco_id
                     WHERE pbp.prova_id = :prova_id AND pb.deleted_at IS NULL
                     LIMIT 1",
                    ['prova_id' => $id]
                );
                
                if ($blocoProva) {
                    if (!$this->alunoPodeVerBlocoNoPortalLinha($blocoProva)) {
                        $this->json(['error' => 'Este evento de provas não está disponível no portal para os alunos.'], 403);
                        return;
                    }
                    $blocoId = $blocoProva['id'];
                    $provasBloco = $this->db->fetchAll(
                        "SELECT p.* FROM provas_blocos_vinculo pbp
                         INNER JOIN provas p ON pbp.prova_id = p.id
                         WHERE pbp.bloco_id = :bloco_id
                         AND p.liberada = 1
                         AND p.ativo = 1
                         AND p.deleted_at IS NULL
                         ORDER BY pbp.ordem ASC",
                        ['bloco_id' => $blocoId]
                    );
                    foreach ($provasBloco as $provaBloco) {
                        $r = $this->provaModel->getRealizacao($provaBloco['id'], $user['id']);
                        if ($r && $r['status'] !== 'finalizado') {
                            $realizacoesParaComprovante[] = $r;
                        }
                    }
                    $this->processarComprovanteFinalizacao($comprovanteBase64, $realizacoesParaComprovante);

                    $notaTotal = 0;
                    $valorTotal = 0;
                    foreach ($provasBloco as $provaBloco) {
                        $realizacao = $this->provaModel->getRealizacao($provaBloco['id'], $user['id']);
                        if ($realizacao && $realizacao['status'] !== 'finalizado') {
                            $resultadoProva = $this->provaModel->finalizarProva($provaBloco['id'], $user['id']);
                            if ($comprovanteBase64 === null || $comprovanteBase64 === '') {
                                $this->safeGerarComprovanteServidor($provaBloco['id'], $user['id']);
                            }
                            $notaTotal += $resultadoProva['nota'] ?? 0;
                            $valorTotal += $resultadoProva['valor_total'] ?? 0;
                        } elseif ($realizacao && $realizacao['status'] === 'finalizado') {
                            $notaTotal += $realizacao['nota'] ?? 0;
                            $provaBlocoData = $this->provaModel->findById($provaBloco['id']);
                            $valorTotal += $provaBlocoData['valor_total'] ?? 0;
                        }
                    }
                    
                    $this->json([
                        'success' => true,
                        'message' => 'Bloco de provas finalizado com sucesso! Aguarde a coordenação liberar o gabarito.',
                        'nota' => $notaTotal,
                        'valor_total' => $valorTotal,
                        'pode_mostrar' => false,
                        'bloco_id' => $blocoId,
                        'proxima_prova' => null,
                        'modo_bloco' => true
                    ]);
                    return;
                }
            }
            
            if ($modoBloco && $modoSeguro) {
                // Modo seguro: finaliza apenas esta prova e volta para escolher próxima matéria
                $blocoProva = $this->db->fetch(
                    "SELECT pb.* FROM provas_blocos pb
                     INNER JOIN provas_blocos_vinculo pbp ON pb.id = pbp.bloco_id
                     WHERE pbp.prova_id = :prova_id AND pb.deleted_at IS NULL
                     LIMIT 1",
                    ['prova_id' => $id]
                );
                if ($blocoProva) {
                    if (!$this->alunoPodeVerBlocoNoPortalLinha($blocoProva)) {
                        $this->json(['error' => 'Este evento de provas não está disponível no portal para os alunos.'], 403);
                        return;
                    }
                    $r = $this->provaModel->getRealizacao($id, $user['id']);
                    if (!$r) {
                        $this->json(['error' => 'Realização não encontrada. Recarregue a página e tente iniciar a prova novamente.'], 400);
                        return;
                    }
                    if ($r['status'] === 'finalizado') {
                        $this->json([
                            'success' => true,
                            'message' => 'Prova já estava finalizada.',
                            'bloco_id' => (int) $blocoProva['id'],
                            'voltar_escolher_materia' => true,
                        ]);
                        return;
                    }
                    $realizacoesParaComprovante[] = $r;
                    $this->processarComprovanteFinalizacao($comprovanteBase64, $realizacoesParaComprovante);
                    $this->provaModel->finalizarProva($id, $user['id']);
                    if ($comprovanteBase64 === null || $comprovanteBase64 === '') {
                        $this->safeGerarComprovanteServidor($id, $user['id']);
                    }
                    $this->json([
                        'success' => true,
                        'message' => 'Prova finalizada. Escolha a próxima matéria.',
                        'bloco_id' => (int) $blocoProva['id'],
                        'voltar_escolher_materia' => true
                    ]);
                    return;
                }
            }

            $r = $this->provaModel->getRealizacao($id, $user['id']);
            if (!$r) {
                $this->json(['error' => 'Realização não encontrada. Recarregue a página e tente iniciar a prova novamente.'], 400);
                return;
            }
            if ($r['status'] === 'finalizado') {
                $this->json(['success' => true, 'message' => 'Prova já estava finalizada', 'pode_mostrar' => false]);
                return;
            }
            $realizacoesParaComprovante[] = $r;
            $this->processarComprovanteFinalizacao($comprovanteBase64, $realizacoesParaComprovante);
            
            $resultado = $this->provaModel->finalizarProva($id, $user['id']);
            if ($comprovanteBase64 === null || $comprovanteBase64 === '') {
                $this->safeGerarComprovanteServidor($id, $user['id']);
            }

            // EducaInclui — se esta é uma prova adaptada (clone), espelha a nota na prova
            // ORIGINAL para que apareça em todos os boletins/telas de resultado do professor.
            $this->mirrorAdaptedNotaToOriginal((int) $id, (int) $user['id'], $resultado);
            
            $payload = [
                'success' => true,
                'message' => 'Prova finalizada com sucesso',
                'nota' => $resultado['nota'] ?? 0,
                'valor_total' => $resultado['valor_total'] ?? 0,
                'pode_mostrar' => false,
                'bloco_id' => null,
                'proxima_prova' => null,
            ];
            try {
                $prova = $this->provaModel->findById($id);
                if (is_array($prova)) {
                    $liberar = (string) ($prova['liberar_resultado'] ?? '');
                    if ($liberar === 'imediatamente') {
                        $payload['pode_mostrar'] = true;
                    } elseif ($liberar === 'apos_todos') {
                        $payload['pode_mostrar'] = $this->provaModel->todosFinalizaram($id);
                    }
                    $payload['liberar_resultado'] = $liberar;
                }
                $blocoProva = $this->db->fetch(
                    "SELECT pb.id as bloco_id, pbp.ordem
                     FROM provas_blocos_vinculo pbp
                     INNER JOIN provas_blocos pb ON pbp.bloco_id = pb.id
                     WHERE pbp.prova_id = :prova_id
                     LIMIT 1",
                    ['prova_id' => $id]
                );
                if ($blocoProva) {
                    $payload['bloco_id'] = $blocoProva['bloco_id'];
                    $payload['proxima_prova'] = $this->db->fetch(
                        "SELECT p.id, p.titulo, m.nome as materia_nome
                         FROM provas_blocos_vinculo pbp
                         INNER JOIN provas p ON pbp.prova_id = p.id
                         LEFT JOIN materias m ON p.materia_id = m.id
                         WHERE pbp.bloco_id = :bloco_id
                         AND pbp.ordem > :ordem_atual
                         AND p.liberada = 1
                         AND p.ativo = 1
                         AND p.deleted_at IS NULL
                         ORDER BY pbp.ordem ASC
                         LIMIT 1",
                        [
                            'bloco_id' => $blocoProva['bloco_id'],
                            'ordem_atual' => $blocoProva['ordem'],
                        ]
                    ) ?: null;
                }
            } catch (Throwable $posFinalize) {
                error_log('Pós-finalização prova (não bloqueante): ' . $posFinalize->getMessage());
            }
            $this->json($payload);
            
        } catch (Throwable $e) {
            error_log("Erro ao finalizar prova: " . $e->getMessage());
            $this->registrarLogProva('erro_finalizar', [
                'prova_id' => $id,
                'bloco_id' => $blocoId ?? null,
                'detalhe' => $e->getMessage(),
            ]);
            $this->json(['error' => $e->getMessage()], 400);
        } finally {
            ini_set('display_errors', (string) $oldDisplayErrors);
        }
    }

    /**
     * EducaInclui — espelha a nota de uma prova adaptada (clone) na prova ORIGINAL.
     *
     * A versão adaptada já é corrigida nativamente na sua própria escala (igual à
     * original, graças ao escalonamento de `valor` na geração). Aqui apenas
     * gravamos uma `provas_realizacoes` finalizada na prova original com essa nota,
     * para que todas as telas de resultado/boletim (que leem por prova original)
     * exibam o resultado — sem precisar alterar nenhuma query de leitura.
     *
     * @param array<string,mixed> $resultado retorno de Exam::finalizarProva()
     */
    private function mirrorAdaptedNotaToOriginal(int $adaptedProvaId, int $alunoId, array $resultado): void
    {
        try {
            require_once __DIR__ . '/../../Models/EducaInclui/VersaoAdaptada.php';
            require_once __DIR__ . '/../../Models/EducaInclui/VersaoAdaptadaLog.php';
            $ver = (new VersaoAdaptada())->getByAdaptedProvaId($adaptedProvaId);
            if (!$ver || (int) $ver['aluno_id'] !== $alunoId) {
                return;
            }
            $originalId = (int) $ver['prova_id'];
            if ($originalId <= 0) {
                return;
            }
            $nota = (float) ($resultado['nota'] ?? 0);
            $tempo = (int) ($resultado['tempo_gasto'] ?? 0);

            $existente = $this->provaModel->getRealizacao($originalId, $alunoId);
            if ($existente) {
                $this->db->update(
                    "UPDATE provas_realizacoes
                     SET nota = :nota, status = 'finalizado', finalizado_em = NOW(), tempo_gasto = :tempo
                     WHERE id = :id",
                    ['nota' => $nota, 'tempo' => $tempo, 'id' => $existente['id']]
                );
            } else {
                $this->db->insert(
                    "INSERT INTO provas_realizacoes (prova_id, aluno_id, iniciado_em, finalizado_em, tempo_gasto, nota, status)
                     VALUES (:prova_id, :aluno_id, NOW(), NOW(), :tempo, :nota, 'finalizado')",
                    ['prova_id' => $originalId, 'aluno_id' => $alunoId, 'tempo' => $tempo, 'nota' => $nota]
                );
            }

            (new VersaoAdaptadaLog())->record('nota_espelhada', [
                'versao_adaptada_id' => (int) $ver['id'],
                'mascara_id' => (int) $ver['mascara_id'],
                'aluno_id' => $alunoId,
                'prova_id' => $originalId,
                'user_id' => $alunoId,
                'details' => ['adapted_prova_id' => $adaptedProvaId, 'nota' => $nota],
            ]);
        } catch (Throwable $e) {
            error_log('EducaInclui mirrorAdaptedNota: ' . $e->getMessage());
        }
    }

    /**
     * Gera comprovante sem impedir a finalização da prova em caso de falha auxiliar.
     */
    private function safeGerarComprovanteServidor(int $provaId, int $alunoId): void
    {
        try {
            $this->gerarComprovanteServidor($provaId, $alunoId);
        } catch (Throwable $e) {
            error_log('Comprovante de finalização (não bloqueante): ' . $e->getMessage());
        }
    }

    /**
     * Processa comprovante em base64: decodifica, grava em S3/local e atualiza marcacao_final_key nas realizações.
     * @param string|null $comprovanteBase64
     * @param array $realizacoes Lista de registros de provas_realizacoes (com 'id')
     */
    private function processarComprovanteFinalizacao($comprovanteBase64, array $realizacoes)
    {
        if ($comprovanteBase64 === null || $comprovanteBase64 === '' || empty($realizacoes)) {
            return;
        }
        $bin = base64_decode($comprovanteBase64, true);
        if ($bin === false || strlen($bin) > 5 * 1024 * 1024) {
            return;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'comprovante_');
        if ($tmp === false || @file_put_contents($tmp, $bin) === false) {
            if ($tmp !== false) {
                @unlink($tmp);
            }
            return;
        }
        try {
            if (!class_exists('MediaStorageService')) {
                require_once __DIR__ . '/../../Services/MediaStorageService.php';
            }
            $media = new MediaStorageService($this->config);
            $primeiraId = (int) $realizacoes[0]['id'];
            $key = 'realizacao_' . $primeiraId . '_' . date('YmdHis') . '.png';
            if ($media->put('provas_marcacao_final', $key, $tmp, 'image/png')) {
                $ids = array_map(function ($r) {
                    return (int) $r['id'];
                }, $realizacoes);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $this->db->query(
                    "UPDATE provas_realizacoes SET marcacao_final_key = ? WHERE id IN ($placeholders)",
                    array_merge([$key], $ids)
                );
            }
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Gera comprovante em HTML (revisão das respostas) no servidor e salva no S3/local.
     * Chamado após finalizarProva para que a coordenação possa visualizar o que o aluno confirmou.
     */
    private function gerarComprovanteServidor($provaId, $alunoId)
    {
        $realizacao = $this->provaModel->getRealizacao($provaId, $alunoId);
        if (!$realizacao) {
            return;
        }
        $prova = $this->provaModel->findById($provaId);
        $aluno = $this->studentModel->findById($alunoId);
        if (!$prova || !$aluno) {
            return;
        }
        $questoes = $this->provaModel->getQuestoes($provaId);
        $respostas = $this->provaModel->getRespostas($provaId, $alunoId);
        $respostasPorQuestao = [];
        foreach ($respostas as $r) {
            $respostasPorQuestao[(int)$r['questao_id']] = $r;
        }
        $itens = [];
        $numero = 0;
        foreach ($questoes as $q) {
            $numero++;
            $r = $respostasPorQuestao[(int)$q['id']] ?? null;
            $texto = '';
            if ($r) {
                if (!empty($r['alternativa_id'])) {
                    $alternativas = $this->provaModel->getAlternativas($q['id']);
                    foreach ($alternativas as $alt) {
                        if ((int)$alt['id'] === (int)$r['alternativa_id']) {
                            $texto = trim(strip_tags($alt['texto'] ?? ''));
                            break;
                        }
                    }
                } else {
                    $texto = 'Dissertativa: ' . mb_substr(trim(strip_tags($r['resposta_texto'] ?? '')), 0, 500);
                }
            }
            if ($texto === '') {
                $texto = '(sem resposta)';
            }
            $itens[] = ['numero' => $numero, 'texto' => $texto];
        }
        $dataHora = $realizacao['finalizado_em'] ? date('d/m/Y H:i', strtotime($realizacao['finalizado_em'])) : date('d/m/Y H:i');
        $tituloProva = htmlspecialchars($prova['titulo'] ?? 'Prova');
        $nomeAluno = htmlspecialchars($aluno['nome'] ?? 'Aluno');
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Comprovante - ' . $tituloProva . '</title>';
        $html .= '<style>body{font-family:system-ui,sans-serif;max-width:700px;margin:24px auto;padding:0 16px;}';
        $html .= 'h1{font-size:1.25rem;color:#111;} .meta{color:#666;margin-bottom:20px;}';
        $html .= '.item{margin:12px 0;padding:10px;background:#f5f5f5;border-radius:8px;} .item strong{color:#333;}</style></head><body>';
        $html .= '<h1>Revisão das respostas</h1>';
        $html .= '<div class="meta">Prova: ' . $tituloProva . ' &bull; Aluno: ' . $nomeAluno . ' &bull; ' . $dataHora . '</div>';
        foreach ($itens as $item) {
            $html .= '<div class="item"><strong>Questão ' . (int)$item['numero'] . '</strong>: ' . htmlspecialchars($item['texto']) . '</div>';
        }
        $html .= '</body></html>';
        $tmp = tempnam(sys_get_temp_dir(), 'comprovante_html_');
        if ($tmp === false || @file_put_contents($tmp, $html) === false) {
            if ($tmp !== false) {
                @unlink($tmp);
            }
            return;
        }
        try {
            if (!class_exists('MediaStorageService')) {
                require_once __DIR__ . '/../../Services/MediaStorageService.php';
            }
            $media = new MediaStorageService($this->config);
            $key = 'realizacao_' . (int)$realizacao['id'] . '_' . date('YmdHis') . '.html';
            if ($media->put('provas_marcacao_final', $key, $tmp, 'text/html')) {
                $this->db->query(
                    "UPDATE provas_realizacoes SET marcacao_final_key = :marcacao_final_key WHERE id = :id",
                    ['marcacao_final_key' => $key, 'id' => (int)$realizacao['id']]
                );
            }
        } finally {
            @unlink($tmp);
        }
    }
    
    /**
     * Visualiza resultado da prova
     */
    public function resultado($id)
    {
        $user = $this->auth->getUser();
        
        if ($user['tipo'] !== 'aluno') {
            $this->redirect('/professor/provas');
            return;
        }
        
        $prova = $this->provaModel->findById($id);
        if (!$prova) {
            $this->setFlashMessage('Prova não encontrada', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        
        // Busca realização
        $realizacao = $this->provaModel->getRealizacao($id, $user['id']);
        if (!$realizacao || $realizacao['status'] !== 'finalizado') {
            $this->setFlashMessage('Prova não foi finalizada', 'error');
            $this->redirect('/aluno/provas');
            return;
        }

        $blocoProva = $this->db->fetch(
            "SELECT pb.id, pb.gabarito_liberado
             FROM provas_blocos pb
             INNER JOIN provas_blocos_vinculo pbp ON pb.id = pbp.bloco_id
             WHERE pbp.prova_id = :prova_id
             AND pb.deleted_at IS NULL
             LIMIT 1",
            ['prova_id' => $id]
        );

        if ($blocoProva && empty($blocoProva['gabarito_liberado'])) {
            $this->setFlashMessage('O gabarito deste bloco ainda não foi liberado pela coordenação.', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        
        // Verifica se pode mostrar resultado
        $podeMostrar = false;
        if ($prova['liberar_resultado'] === 'imediatamente') {
            $podeMostrar = true;
        } elseif ($prova['liberar_resultado'] === 'apos_todos') {
            $podeMostrar = $this->provaModel->todosFinalizaram($id);
        }
        
        if (!$podeMostrar) {
            $this->setFlashMessage('Resultado ainda não está disponível', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        
        // Busca questões e respostas
        $questoes = $this->provaModel->getQuestoes($id);
        $respostas = $this->provaModel->getRespostas($id, $user['id']);
        $respostasMap = [];
        foreach ($respostas as $resposta) {
            $respostasMap[$resposta['questao_id']] = $resposta;
        }
        
        // Para cada questão, busca alternativas se for múltipla escolha
        foreach ($questoes as &$questao) {
            if ($questao['tipo'] === 'multipla_escolha') {
                $questao['alternativas'] = $this->provaModel->getAlternativas($questao['id']);
            }
            $questao['resposta'] = $respostasMap[$questao['id']] ?? null;
        }
        
        $data = [
            'title' => 'Resultado da Prova - EducaTudo',
            'user' => $user,
            'prova' => $prova,
            'questoes' => $questoes,
            'realizacao' => $realizacao,
            'current_page' => 'provas',
            'additional_css' => '',
            'additional_js' => '<script>window.MathJax={tex:{inlineMath:[["$","$"],["\\\\(","\\\\)"]],displayMath:[["$$","$$"],["\\\\[","\\\\]"]],processEscapes:true},svg:{fontCache:"global"}};</script>'
                . '<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js"></script>'
                . '<script>(function(){function run(){if(window.MathJax&&window.MathJax.typesetPromise)MathJax.typesetPromise().catch(function(e){console.warn("MathJax:",e);});}run();setTimeout(run,300);setTimeout(run,1000);})();</script>',
        ];
        
        $this->viewWithLayout('student', 'student/exams/resultado', $data);
    }
    
    /**
     * Sincroniza provas existentes com a tabela provas_turmas
     * Garante que provas com turma_id também tenham registro em provas_turmas
     */
    private function sincronizarProvasTurmas()
    {
        try {
            // Adiciona provas_turmas para provas que têm turma_id mas não estão na tabela provas_turmas
            $this->db->query(
                "INSERT INTO provas_turmas (prova_id, turma_id)
                 SELECT p.id, p.turma_id
                 FROM provas p
                 WHERE p.turma_id IS NOT NULL
                 AND p.deleted_at IS NULL
                 AND NOT EXISTS (
                     SELECT 1 FROM provas_turmas pt 
                     WHERE pt.prova_id = p.id 
                     AND pt.turma_id = p.turma_id
                 )"
            );
        } catch (Exception $e) {
            // Se houver erro, apenas loga (não quebra o fluxo)
            error_log("Erro ao sincronizar provas_turmas: " . $e->getMessage());
        }
    }
    
    /**
     * ============================================
     * ROTAS PARA ADMINISTRAÇÃO
     * ============================================
     */
    
    /**
     * Lista todas as provas (admin)
     */
    public function indexAdmin()
    {
        $user = $this->auth->getUser();
        
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirect('/professor/provas');
            return;
        }
        
        // Busca blocos usando o modelo
        require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
        
        $blocoModel = new ExamBlock();
        
        // Filtros (GET)
        // Status: padrão = todos menos concluídos. Use status=todos para incluir concluídos;
        // status=concluido (ou outro) para filtrar um status específico.
        $statusRaw = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
        $filters = [
            'titulo' => trim($_GET['titulo'] ?? ''),
            'data_prova' => trim($_GET['data_prova'] ?? ''),
            'bloco_modelo_id' => !empty($_GET['bloco_modelo_id']) ? (int)$_GET['bloco_modelo_id'] : 0,
            'turma_id' => !empty($_GET['turma_id']) ? (int)$_GET['turma_id'] : 0,
            'materia_id' => !empty($_GET['materia_id']) ? (int)$_GET['materia_id'] : 0,
            'status' => '',
            'bimestre' => !empty($_GET['bimestre']) ? (int)$_GET['bimestre'] : 0,
            'tipo_avaliacao_id' => !empty($_GET['tipo_avaliacao_id']) ? (int)$_GET['tipo_avaliacao_id'] : 0,
        ];
        if ($statusRaw === null || $statusRaw === '' || $statusRaw === 'exceto_concluidos') {
            $filters['excluir_status'] = 'concluido';
            $filters['status'] = '';
        } elseif ($statusRaw === 'todos') {
            $filters['status'] = 'todos';
        } else {
            $filters['status'] = $statusRaw;
        }
        if ($filters['turma_id'] === 0) {
            unset($filters['turma_id']);
        }
        if ($filters['bloco_modelo_id'] === 0) {
            unset($filters['bloco_modelo_id']);
        }
        if ($filters['materia_id'] === 0) {
            unset($filters['materia_id']);
        }
        if ($filters['bimestre'] === 0) {
            unset($filters['bimestre']);
        }
        if ($filters['tipo_avaliacao_id'] === 0) {
            unset($filters['tipo_avaliacao_id']);
        }
        $filters = array_filter($filters, function ($v) { return $v !== '' && $v !== null; });
        // Garante status='' na view quando o padrão é "exceto concluídos"
        if (!isset($filters['status']) && !empty($filters['excluir_status'])) {
            $filters['status'] = '';
        }
        // Paginação: 10 por página
        $perPage = 10;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;
        
        $totalBlocos = $blocoModel->getCountFiltered($filters);
        $blocos = $blocoModel->getAllFiltered($filters, $perPage, $offset);
        
        // Estatísticas por status (getAll já chama marcarConcluidos)
        $stats = $this->db->fetch(
            "SELECT 
                COUNT(*) as total_blocos,
                COUNT(CASE WHEN status = 'aguardando' THEN 1 END) as blocos_aguardando,
                COUNT(CASE WHEN status = 'aprovado' THEN 1 END) as blocos_aprovados,
                COUNT(CASE WHEN status = 'liberado' THEN 1 END) as blocos_liberados,
                COUNT(CASE WHEN status = 'concluido' THEN 1 END) as blocos_concluidos,
                COUNT(CASE WHEN data_prova >= CURDATE() THEN 1 END) as blocos_futuros
             FROM provas_blocos
             WHERE deleted_at IS NULL"
        );
        
        // Busca provas pendentes
        $provasPendentes = $blocoModel->getProvasPendentes();

        // Provas canceladas (modo seguro) por bloco: alerta o coordenador na listagem
        $canceladasPorBloco = [];
        try {
            $rowsCanceladas = $this->db->fetchAll(
                "SELECT pbp.bloco_id, COUNT(*) AS c
                 FROM provas_realizacoes pr
                 INNER JOIN provas_blocos_vinculo pbp ON pbp.prova_id = pr.prova_id
                 INNER JOIN provas p ON p.id = pr.prova_id AND p.deleted_at IS NULL
                 WHERE pr.status = 'cancelada'
                 GROUP BY pbp.bloco_id"
            );
            foreach ($rowsCanceladas as $rc) {
                $canceladasPorBloco[(int) $rc['bloco_id']] = (int) $rc['c'];
            }
        } catch (Exception $e) {
            error_log('indexAdmin: canceladas por bloco: ' . $e->getMessage());
        }
        
        // Turmas e matérias para os filtros
        $turmas = $this->db->fetchAll("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome ASC");
        $materias = $this->db->fetchAll("SELECT id, nome FROM materias ORDER BY nome ASC");
        // Lista de Blocos Modelo para o filtro "Bloco"
        $blocosParaFiltro = $this->db->fetchAll(
            "SELECT id, nome FROM provas_blocos_modelos WHERE deleted_at IS NULL ORDER BY nome ASC"
        );

        $tiposAvaliacaoParaFiltro = [];
        try {
            $tiposAvaliacaoParaFiltro = $this->db->fetchAll(
                "SELECT id, nome FROM provas_tipos_avaliacao WHERE deleted_at IS NULL ORDER BY nome ASC"
            ) ?: [];
        } catch (Exception $e) {
            $tiposAvaliacaoParaFiltro = [];
        }

        if (!class_exists('LayoutHelper')) {
            require_once __DIR__ . '/../../Core/LayoutHelper.php';
        }
        $primaryColor = LayoutHelper::get('primary_color', '#3b82f6');
        $primaryTextColor = LayoutHelper::get('primary_text_color', '#ffffff');
        
        $totalPages = $perPage > 0 ? (int)ceil($totalBlocos / $perPage) : 1;
        $pagination = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalBlocos,
            'total_pages' => $totalPages
        ];
        
        $data = [
            'title' => 'Provas Online - Administração - EducaTudo',
            'user' => $user,
            'blocos' => $blocos,
            'stats' => $stats,
            'provas_pendentes' => $provasPendentes,
            'canceladas_por_bloco' => $canceladasPorBloco,
            'primary_color' => $primaryColor,
            'primary_text_color' => $primaryTextColor,
            'current_page' => 'provas',
            'filters' => $filters,
            'turmas' => $turmas,
            'materias' => $materias,
            'blocos_para_filtro' => $blocosParaFiltro,
            'tipos_avaliacao_para_filtro' => $tiposAvaliacaoParaFiltro,
            'pagination' => $pagination,
            'csrf_token' => $this->generateCsrfToken(),
        ];
        
        $this->viewWithLayout('admin', 'admin/exams/index', $data);
    }
    
    /**
     * Corrige questão dissertativa manualmente
     */
    public function corrigirQuestao($id, $alunoId, $questaoId)
    {
        $user = $this->auth->getUser();
        
        if (!in_array($user['tipo'], ['professor', 'admin', 'admin_escola'])) {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        try {
            $prova = $this->provaModel->findById($id);
            if (!$prova) {
                throw new Exception('Prova não encontrada');
            }
            
            // Verifica se professor pode corrigir (se for professor, só se for dono)
            if ($user['tipo'] === 'professor' && $prova['professor_id'] != $user['id']) {
                throw new Exception('Você não tem permissão para corrigir esta prova');
            }
            
            // Lê dados JSON do body
            $input = file_get_contents('php://input');
            $postData = json_decode($input, true);
            
            if (!$postData) {
                $postData = $_POST;
            }
            
            $correta = isset($postData['correta']) ? (bool)$postData['correta'] : false;
            $pontuacao = floatval($postData['pontuacao'] ?? 0);
            
            $this->provaModel->corrigirQuestao($id, $alunoId, $questaoId, $correta, $pontuacao);
            
            $this->json([
                'success' => true,
                'message' => 'Questão corrigida com sucesso'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao corrigir questão: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Coordenação/Admin: invalida ou revalida uma questão da prova.
     */
    public function invalidarQuestao($questaoId)
    {
        $user = $this->auth->getUser();
        if (!in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input') ?: '{}', true);
            if (!is_array($input)) {
                $input = $_POST;
            }
            $invalidar = !empty($input['invalidar']);
            $observacao = trim((string) ($input['observacao'] ?? ''));
            $senha = (string) ($input['senha'] ?? '');

            if ($observacao === '') {
                throw new Exception('Informe o motivo da invalidação.');
            }
            if (trim($senha) === '') {
                throw new Exception('Digite sua senha para confirmar.');
            }
            $usuario = $this->db->fetch(
                "SELECT senha_hash FROM usuarios WHERE id = :id",
                ['id' => (int) ($user['id'] ?? 0)]
            );
            if (!$usuario || !password_verify($senha, (string) ($usuario['senha_hash'] ?? ''))) {
                throw new Exception('Senha inválida.');
            }

            $this->provaModel->definirInvalidacaoQuestao((int) $questaoId, $invalidar, $observacao, (int) ($user['id'] ?? 0));

            $this->json([
                'success' => true,
                'message' => $invalidar
                    ? 'Questão invalidada e notas recalculadas.'
                    : 'Questão revalidada e notas recalculadas.',
            ]);
        } catch (Exception $e) {
            error_log('Erro invalidarQuestao: ' . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Coordenação/Admin: salva observação de uma prova/matéria.
     */
    public function salvarObservacaoCoordenacao($id)
    {
        $user = $this->auth->getUser();
        if (!in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        try {
            $provaId = (int) $id;
            if ($provaId <= 0) {
                throw new Exception('Prova inválida.');
            }
            $prova = $this->provaModel->findById($provaId);
            if (!$prova) {
                throw new Exception('Prova não encontrada.');
            }

            $input = json_decode(file_get_contents('php://input') ?: '{}', true);
            if (!is_array($input)) {
                $input = $_POST;
            }
            $obs = trim((string) ($input['observacao_coordenacao'] ?? ''));
            $obs = $obs === '' ? null : mb_substr($obs, 0, 2000);

            $this->db->query(
                "UPDATE provas
                 SET observacao_coordenacao = :obs,
                     observacao_coordenacao_data = NOW()
                 WHERE id = :id",
                ['obs' => $obs, 'id' => $provaId]
            );

            $this->json([
                'success' => true,
                'message' => 'Observação salva com sucesso.',
                'observacao_coordenacao' => $obs ?? '',
            ]);
        } catch (Exception $e) {
            error_log('Erro salvarObservacaoCoordenacao: ' . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Visualiza resultado de aluno específico (professor/admin)
     */
    public function visualizarResultadoAluno($id, $alunoId)
    {
        $user = $this->auth->getUser();
        
        if (!in_array($user['tipo'], ['professor', 'admin', 'admin_escola'])) {
            $this->redirect('/professor/provas');
            return;
        }
        
        $prova = $this->provaModel->findById($id);
        if (!$prova) {
            $this->setFlashMessage('Prova não encontrada', 'error');
            $this->redirect('/admin/provas');
            return;
        }
        
        // Verifica se professor pode ver (se for professor, só se for dono)
        if ($user['tipo'] === 'professor' && $prova['professor_id'] != $user['id']) {
            $this->setFlashMessage('Você não tem permissão para ver este resultado', 'error');
            $this->redirect('/professor/provas');
            return;
        }
        
        // Busca aluno
        $aluno = $this->studentModel->findById($alunoId);
        if (!$aluno) {
            $this->setFlashMessage('Aluno não encontrado', 'error');
            $this->redirect('/admin/provas');
            return;
        }
        
        // Busca realização
        $realizacao = $this->provaModel->getRealizacao($id, $alunoId);
        if (!$realizacao) {
            $this->setFlashMessage('Aluno não realizou esta prova', 'error');
            $this->redirect('/admin/provas');
            return;
        }
        
        // EducaInclui — se o aluno recebeu uma versão adaptada (clone) aprovada, as
        // respostas item-a-item vivem na prova clonada. Carregamos o conteúdo do clone
        // para o professor ver as questões realmente respondidas (a nota já é espelhada).
        $provaConteudoId = (int) $id;
        $versaoAdaptada = false;
        try {
            $adaptRow = $this->db->fetch(
                "SELECT adapted_prova_id FROM versoes_adaptadas
                 WHERE prova_id = :pid AND aluno_id = :aid
                   AND status_aprovacao = 'aprovada' AND adapted_prova_id IS NOT NULL
                 ORDER BY id DESC LIMIT 1",
                ['pid' => (int) $id, 'aid' => (int) $alunoId]
            );
            if ($adaptRow && !empty($adaptRow['adapted_prova_id'])) {
                $provaConteudoId = (int) $adaptRow['adapted_prova_id'];
                $versaoAdaptada = true;
            }
        } catch (Throwable $eAdaptView) {
            // módulo desativado / tabela ausente: usa a prova original
        }

        // Busca questões e respostas (do clone, se houver versão adaptada)
        $questoes = $this->provaModel->getQuestoes($provaConteudoId);
        $respostas = $this->provaModel->getRespostas($provaConteudoId, $alunoId);
        $respostasMap = [];
        foreach ($respostas as $resposta) {
            $respostasMap[$resposta['questao_id']] = $resposta;
        }
        
        // Para cada questão, busca alternativas se for múltipla escolha
        foreach ($questoes as &$questao) {
            if ($questao['tipo'] === 'multipla_escolha') {
                $questao['alternativas'] = $this->provaModel->getAlternativas($questao['id']);
            }
            $questao['resposta'] = $respostasMap[$questao['id']] ?? null;
        }
        unset($questao);

        // EducaInclui — flags operacionais (sem diagnóstico) para exibir ao professor
        $eiNoSpelling = false;
        try {
            require_once __DIR__ . '/../../Services/MascaraResolver.php';
            $eiMaskAluno = MascaraResolver::resolveForAluno((int) $alunoId);
            if (!empty($eiMaskAluno['active'])) {
                $eiNoSpelling = MascaraResolver::isOn($eiMaskAluno['rules'], 'no_spelling_penalty');
            }
        } catch (Throwable $eMaskView) {
            // ignora
        }
        
        // Busca bloco e configuracao_nota se a prova estiver em um bloco
        $bloco = null;
        $configuracaoNota = 'professor_por_questao'; // padrão
        $blocoProva = $this->db->fetch(
            "SELECT pb.* FROM provas_blocos_vinculo pbp
             INNER JOIN provas_blocos pb ON pbp.bloco_id = pb.id
             WHERE pbp.prova_id = :prova_id
             AND pb.deleted_at IS NULL
             LIMIT 1",
            ['prova_id' => $id]
        );
        if ($blocoProva) {
            $bloco = $blocoProva;
            $configuracaoNota = $blocoProva['configuracao_nota'] ?? 'professor_por_questao';
        }
        
        $data = [
            'title' => 'Resultado do Aluno - EducaTudo',
            'user' => $user,
            'prova' => $prova,
            'aluno' => $aluno,
            'questoes' => $questoes,
            'realizacao' => $realizacao,
            'bloco' => $bloco,
            'configuracao_nota' => $configuracaoNota,
            'isAdmin' => in_array($user['tipo'], ['admin', 'admin_escola']),
            'versao_adaptada' => $versaoAdaptada,
            'ei_no_spelling_penalty' => $eiNoSpelling,
            'current_page' => 'provas',
            'additional_css' => '<link rel="stylesheet" href="' . URL . '/public/static/css/mathlive-static.css">',
            'additional_js' => '<script src="' . URL . '/public/static/js/mathlive.min.js"></script><script>document.addEventListener("DOMContentLoaded",function(){if(typeof MathLive!=="undefined"&&MathLive.renderMathInDocument)MathLive.renderMathInDocument();});</script>',
        ];
        
        $layout = $user['tipo'] === 'professor' ? 'professor' : 'admin';
        $view = $user['tipo'] === 'professor' ? 'teacher/exams/student-result' : 'admin/exams/student-result';
        
        $this->viewWithLayout($layout, $view, $data);
    }

    /**
     * Histórico de alterações de respostas do aluno na prova (provas_respostas_log).
     * GET admin/provas/resultado-aluno/{id}/{alunoId}/historico-respostas
     */
    public function historicoRespostasAluno($id, $alunoId)
    {
        $user = $this->auth->getUser();
        if (!in_array($user['tipo'], ['professor', 'admin', 'admin_escola'])) {
            $this->redirect('/');
            return;
        }
        $prova = $this->provaModel->findById($id);
        if (!$prova) {
            $this->setFlashMessage('Prova não encontrada', 'error');
            $this->redirect('/admin/provas');
            return;
        }
        if ($user['tipo'] === 'professor' && (int)$prova['professor_id'] !== (int)$user['id']) {
            $this->setFlashMessage('Sem permissão', 'error');
            $this->redirect('/professor/provas');
            return;
        }
        $aluno = $this->studentModel->findById($alunoId);
        if (!$aluno) {
            $this->setFlashMessage('Aluno não encontrado', 'error');
            $this->redirect('/admin/provas');
            return;
        }
        $logs = $this->db->fetchAll(
            "SELECT l.id, l.questao_id, l.alternativa_id, l.resposta_texto, l.tipo_acao, l.created_at, l.ip, l.user_agent,
                    a.texto as alternativa_texto
             FROM provas_respostas_log l
             LEFT JOIN provas_alternativas a ON a.id = l.alternativa_id
             WHERE l.prova_id = :prova_id AND l.aluno_id = :aluno_id
             ORDER BY l.created_at ASC",
            ['prova_id' => $id, 'aluno_id' => $alunoId]
        );
        $data = [
            'title' => 'Histórico de respostas - EducaTudo',
            'user' => $user,
            'prova' => $prova,
            'aluno' => $aluno,
            'logs' => $logs,
            'current_page' => 'provas',
        ];
        $layout = $user['tipo'] === 'professor' ? 'professor' : 'admin';
        $this->viewWithLayout($layout, 'admin/exams/historico-respostas', $data);
    }

    /**
     * Dashboard de resultados da prova (professor/admin)
     * Exibe KPIs por questão e por aluno, com acesso ao detalhe individual.
     */
    public function resultadosProfessor($id)
    {
        $user = $this->auth->getUser();
        $isAdmin = in_array($user['tipo'], ['admin', 'admin_escola']);

        if (!$isAdmin && $user['tipo'] !== 'professor') {
            $this->redirect('/professor/provas');
            return;
        }

        $prova = $this->provaModel->findById($id);
        if (!$prova) {
            $this->setFlashMessage('Prova não encontrada', 'error');
            $this->redirect($isAdmin ? '/admin/provas' : '/professor/provas');
            return;
        }

        if (!$isAdmin && (int)$prova['professor_id'] !== (int)$user['id']) {
            $this->setFlashMessage('Você não tem permissão para ver os resultados desta prova', 'error');
            $this->redirect('/professor/provas');
            return;
        }

        $porQuestao = $this->db->fetchAll(
            "SELECT r.questao_id,
                    COUNT(*) as total_respostas,
                    SUM(IF(r.correta = 1, 1, 0)) as total_acertos
             FROM provas_respostas r
             INNER JOIN provas_realizacoes pr ON r.prova_id = pr.prova_id
                 AND r.aluno_id = pr.aluno_id
                 AND pr.status = 'finalizado'
             WHERE r.prova_id = :prova_id
             GROUP BY r.questao_id",
            ['prova_id' => $id]
        );

        $questoesMap = [];
        foreach ($porQuestao as $row) {
            $total = (int)$row['total_respostas'];
            $acertos = (int)$row['total_acertos'];
            $erros = $total - $acertos;
            $questoesMap[(int)$row['questao_id']] = [
                'questao_id' => (int)$row['questao_id'],
                'total_respostas' => $total,
                'total_acertos' => $acertos,
                'total_erros' => $erros,
                'taxa_acerto' => $total > 0 ? round(100 * $acertos / $total, 1) : 0.0,
                'taxa_erro' => $total > 0 ? round(100 * $erros / $total, 1) : 0.0,
                'enunciado' => '',
            ];
        }

        if (!empty($questoesMap)) {
            $questaoIds = array_keys($questoesMap);
            $ph = implode(',', array_fill(0, count($questaoIds), '?'));
            $questoesInfo = $this->db->fetchAll(
                "SELECT id, enunciado FROM provas_questoes WHERE id IN ($ph)",
                $questaoIds
            );
            foreach ($questoesInfo as $q) {
                $qid = (int)$q['id'];
                if (isset($questoesMap[$qid])) {
                    $questoesMap[$qid]['enunciado'] = $q['enunciado'] ?? '';
                }
            }
        }

        $listaQuestoes = array_values($questoesMap);
        usort($listaQuestoes, function ($a, $b) {
            return ($b['taxa_acerto'] ?? 0) <=> ($a['taxa_acerto'] ?? 0);
        });
        $maisAcertadas = array_slice($listaQuestoes, 0, 20);
        usort($listaQuestoes, function ($a, $b) {
            return ($b['taxa_erro'] ?? 0) <=> ($a['taxa_erro'] ?? 0);
        });
        $maisErradas = array_slice($listaQuestoes, 0, 20);

        $porAluno = $this->db->fetchAll(
            "SELECT pr.aluno_id,
                    a.nome,
                    a.ra,
                    pr.nota,
                    COUNT(r.id) as total_respostas,
                    SUM(IF(r.correta = 1, 1, 0)) as total_acertos
             FROM provas_realizacoes pr
             INNER JOIN alunos a ON a.id = pr.aluno_id
             LEFT JOIN provas_respostas r ON r.prova_id = pr.prova_id
                 AND r.aluno_id = pr.aluno_id
             WHERE pr.prova_id = :prova_id
             AND pr.status = 'finalizado'
             GROUP BY pr.aluno_id, a.nome, a.ra, pr.nota
             ORDER BY a.nome ASC",
            ['prova_id' => $id]
        );

        // EducaInclui — alunos que receberam versão adaptada (clone): a nota foi espelhada
        // na prova original (sem respostas item-a-item), então o % vem de nota/valor_total.
        $adaptadosSet = [];
        try {
            $adaptadosRows = $this->db->fetchAll(
                "SELECT DISTINCT aluno_id FROM versoes_adaptadas
                 WHERE prova_id = :pid AND status_aprovacao = 'aprovada'",
                ['pid' => $id]
            );
            foreach ($adaptadosRows as $ar) {
                $adaptadosSet[(int) $ar['aluno_id']] = true;
            }
        } catch (Throwable $eAdap) {
            // tabela ausente / módulo desativado: ignora silenciosamente
        }
        $valorTotalProva = (float) ($prova['valor_total'] ?? 0);

        require_once __DIR__ . '/../../Services/MascaraResolver.php';

        $alunosAcima40 = [];
        $alunosAbaixo40 = [];
        $todosAlunos = [];
        foreach ($porAluno as $row) {
            $alunoId = (int)$row['aluno_id'];
            $totalRespostas = (int)($row['total_respostas'] ?? 0);
            $totalAcertos = (int)($row['total_acertos'] ?? 0);
            $isAdaptada = isset($adaptadosSet[$alunoId]);

            // EducaInclui — flags operacionais para o professor (sem diagnóstico/laudo)
            $semOrtografia = false;
            try {
                $maskAluno = MascaraResolver::resolveForAluno($alunoId);
                if (!empty($maskAluno['active'])) {
                    $semOrtografia = MascaraResolver::isOn($maskAluno['rules'], 'no_spelling_penalty');
                }
            } catch (Throwable $eMask) {
                // módulo desativado / tabela ausente: ignora
            }

            if ($isAdaptada && $valorTotalProva > 0) {
                // % com base na nota espelhada (a prova adaptada já está na mesma escala).
                $percentual = round(100 * (float) ($row['nota'] ?? 0) / $valorTotalProva, 1);
            } else {
                $percentual = $totalRespostas > 0 ? round(100 * $totalAcertos / $totalRespostas, 1) : 0.0;
            }

            $item = [
                'aluno_id' => $alunoId,
                'nome' => $row['nome'] ?? '',
                'ra' => $row['ra'] ?? '',
                'total_respostas' => $totalRespostas,
                'total_acertos' => $totalAcertos,
                'percentual_acerto' => $percentual,
                'adaptada' => $isAdaptada,
                'no_spelling_penalty' => $semOrtografia,
            ];
            $todosAlunos[] = $item;
            if ($percentual >= 40) {
                $alunosAcima40[] = $item;
            } else {
                $alunosAbaixo40[] = $item;
            }
        }

        $data = [
            'title' => 'Resultados da Prova - EducaTudo',
            'user' => $user,
            'prova' => $prova,
            'stats_questoes' => [
                'mais_acertadas' => $maisAcertadas,
                'mais_erradas' => $maisErradas
            ],
            'alunos_acima_40' => $alunosAcima40,
            'alunos_abaixo_40' => $alunosAbaixo40,
            'todos_alunos' => $todosAlunos,
            'current_page' => 'provas'
        ];

        $this->viewWithLayout($isAdmin ? 'admin' : 'professor', 'teacher/exams/results', $data);
    }
    
    /**
     * Gera questões para prova usando OpenAI
     */
    public function gerarQuestoesIA($id)
    {
        set_time_limit(240);
        $user = $this->auth->getUser();
        $isAdmin = in_array($user['tipo'], ['admin', 'admin_escola']);

        if (!$isAdmin && $user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        try {
            if (!$isAdmin && !$this->provaModel->canEdit($id, $user['id'])) {
                throw new Exception('Você não tem permissão para editar esta prova');
            }
            
            $input = file_get_contents('php://input');
            $postData = json_decode($input, true);
            
            if (!$postData) {
                $postData = $_POST;
            }
            
            $tipo = $postData['tipo'] ?? 'alternativas';
            $quantidade = (int)($postData['quantidade'] ?? 5);
            $niveis = $postData['niveis'] ?? ['Fácil', 'Médio', 'Difícil'];
            $quantidadesPorNivel = $postData['quantidades_por_nivel'] ?? [];
            $serie = trim($postData['serie'] ?? '');
            $comImagens = ($postData['com_imagens'] ?? '0') === '1';
            
            $planosAulaIds = $postData['planos_aula_id'] ?? [];
            if (is_string($planosAulaIds)) {
                $planosAulaIds = [$planosAulaIds];
            }
            $planosAulaIds = array_map('intval', array_filter($planosAulaIds));
            
            $contexto = $postData['contexto'] ?? '';
            
            $prova = $this->provaModel->findById($id);
            if (!$prova) {
                throw new Exception('Prova não encontrada');
            }
            
            $materia = $this->subjectModel->findById($prova['materia_id']);
            
            // Tenta obter série das turmas vinculadas se não informada
            if (empty($serie) && !empty($prova['turma_id'])) {
                $turmaInfo = $this->db->fetch(
                    "SELECT t.nome, a.serie FROM turmas t LEFT JOIN alunos a ON a.turma_id = t.id WHERE t.id = :tid LIMIT 1",
                    ['tid' => $prova['turma_id']]
                );
                if ($turmaInfo && !empty($turmaInfo['serie'])) {
                    $serie = $turmaInfo['serie'];
                } elseif ($turmaInfo && !empty($turmaInfo['nome'])) {
                    $serie = $turmaInfo['nome'];
                }
            }
            
            $tema = $prova['titulo'];
            $contextoAdicional = $contexto;
            $materiaNome = $materia['nome'] ?? 'Geral';

            require_once __DIR__ . '/../../Services/CreditosService.php';
            $creditosService = new \App\Services\CreditosService();
            $papelCarteira = \App\Services\CreditosService::tipoCarteiraDoUsuario((string) ($user['tipo'] ?? 'professor'));
            $moduloCreditoSelecionado = null;
            if ($creditosService->moduloCobraCredito('gerar_exercicio_ia_professor') && $creditosService->getCustoModulo('gerar_exercicio_ia_professor') > 0) {
                $moduloCreditoSelecionado = 'gerar_exercicio_ia_professor';
            }

            if ($moduloCreditoSelecionado !== null && !$creditosService->podeConsumir($papelCarteira, (int) $user['id'], $moduloCreditoSelecionado)) {
                throw new Exception('Créditos insuficientes para gerar questões da prova por IA.');
            }
            
            require_once __DIR__ . '/../../Services/OpenAIService.php';
            $openaiService = new \App\Services\OpenAIService();
            
            $exercicios = [];
            
            if (!empty($quantidadesPorNivel) && is_array($quantidadesPorNivel)) {
                foreach ($quantidadesPorNivel as $nivel => $qtd) {
                    if ($qtd > 0 && in_array($nivel, $niveis)) {
                        try {
                            $resultado = $openaiService->gerarProvaIA(
                                $tema, $materiaNome, $qtd, $nivel, $contextoAdicional, $tipo, $serie, $comImagens
                            );
                            
                            $exerciciosNivel = $resultado['exercicios'] ?? $resultado['questoes'] ?? [];
                            if (!empty($exerciciosNivel) && is_array($exerciciosNivel)) {
                                foreach ($exerciciosNivel as $ex) {
                                    $ex['nivel'] = $nivel;
                                    $exercicios[] = $ex;
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Erro ao gerar questões para nível {$nivel}: " . $e->getMessage());
                        }
                    }
                }
            } else {
                $contextoCompleto = "Matéria: {$materiaNome}\n";
                $contextoCompleto .= "Título da Prova: {$prova['titulo']}\n";
                if ($prova['descricao']) {
                    $contextoCompleto .= "Descrição: {$prova['descricao']}\n";
                }
                
                if ($contexto) {
                    $contextoCompleto .= "\n=== CONTEXTO ADICIONAL ===\n";
                    $contextoCompleto .= $contexto . "\n";
                    $contextoCompleto .= "==========================\n";
                }
                
                $niveisStr = is_array($niveis) ? implode(', ', $niveis) : $niveis;
                $contextoCompleto .= "Tipo de questão: {$tipo}\n";
                $contextoCompleto .= "Quantidade: {$quantidade} questões\n";
                $contextoCompleto .= "Níveis de dificuldade: {$niveisStr}\n";
                
                $resultado = $openaiService->gerarProvaIA(
                    $tema, $materiaNome, $quantidade, $niveis, $contextoAdicional, $tipo, $serie, $comImagens
                );
                
                $exercicios = $resultado['exercicios'] ?? $resultado['questoes'] ?? [];
            }
            
            if (empty($exercicios) || !is_array($exercicios)) {
                throw new Exception('Erro ao gerar questões com IA: nenhuma questão foi gerada');
            }
            
            // Processar imagens SOMENTE se o professor ativou o toggle
            $temImagens = false;
            if ($comImagens) {
                foreach ($exercicios as $ex) {
                    if (!empty($ex['imagem']) && is_array($ex['imagem'])) {
                        $temImagens = true;
                        break;
                    }
                }
            }

            if ($temImagens) {
                try {
                    require_once __DIR__ . '/../../Services/ExamImageService.php';
                    $imageService = new \ExamImageService($this->config);
                    $exercicios = $imageService->processarImagens($exercicios, [
                        'materia' => $materiaNome,
                        'serie' => $serie,
                    ]);
                } catch (Exception $e) {
                    error_log("Erro ao processar imagens das questões IA: " . $e->getMessage());
                }
            }
            
            $questoesExistentes = $this->provaModel->getQuestoes($id);
            $ordemInicial = count($questoesExistentes);
            
            $questoesIds = [];
            
            foreach ($exercicios as $index => $exercicio) {
                $enunciado = $exercicio['pergunta'] ?? $exercicio['enunciado'] ?? '';
                if (!mb_check_encoding($enunciado, 'UTF-8')) {
                    $enunciado = mb_convert_encoding($enunciado, 'UTF-8', 'UTF-8');
                }
                $enunciado = preg_replace('/\s*[\r\n]+\s*/u', ' ', $enunciado) ?? $enunciado;
                $enunciado = trim(preg_replace('/\s+/u', ' ', $enunciado) ?? $enunciado);
                
                error_log("PROVA IA - Questão " . ($index + 1) . " enunciado (primeiros 100 chars): " . mb_substr($enunciado, 0, 100));
                
                $nivelDificuldade = $exercicio['nivel'] ?? $exercicio['nivel_dificuldade'] ?? null;
                if ($nivelDificuldade) {
                    $nivelDificuldade = ucfirst(mb_strtolower($nivelDificuldade));
                    if (!in_array($nivelDificuldade, ['Fácil', 'Médio', 'Difícil', 'Desafio'])) {
                        $nivelLower = mb_strtolower($nivelDificuldade);
                        if (in_array($nivelLower, ['facil', 'fácil', 'easy'])) {
                            $nivelDificuldade = 'Fácil';
                        } elseif (in_array($nivelLower, ['medio', 'médio', 'medium'])) {
                            $nivelDificuldade = 'Médio';
                        } elseif (in_array($nivelLower, ['dificil', 'difícil', 'hard'])) {
                            $nivelDificuldade = 'Difícil';
                        } elseif (in_array($nivelLower, ['desafio', 'challenge', 'vestibular'])) {
                            $nivelDificuldade = 'Desafio';
                        } else {
                            $nivelDificuldade = null;
                        }
                    }
                }

                // Extrair imagem_url do resultado processado
                $imagemUrl = null;
                if (!empty($exercicio['imagem']) && is_array($exercicio['imagem'])) {
                    $imagemUrl = $exercicio['imagem']['url'] ?? null;
                    // Se a imagem tem fallback textual e não foi gerada, inclui no enunciado
                    if (empty($imagemUrl) && !empty($exercicio['imagem']['descricao'])) {
                        $enunciado .= "\n\n[Imagem: " . $exercicio['imagem']['descricao'] . "]";
                    }
                }
                
                $data = [
                    'enunciado' => $enunciado,
                    'imagem_url' => $imagemUrl,
                    'tipo' => $tipo === 'alternativas' ? 'multipla_escolha' : ($tipo === 'verdadeiro_falso' ? 'verdadeiro_falso' : 'dissertativa'),
                    'valor' => 1.00,
                    'nivel_dificuldade' => $nivelDificuldade,
                    'ordem' => $ordemInicial + $index,
                    'alternativas' => [],
                    'explicacao' => $exercicio['explicacao'] ?? null,
                ];
                
                if ($tipo === 'alternativas' && isset($exercicio['alternativas'])) {
                    $alternativas = [];
                    $correta = $exercicio['correta'] ?? $exercicio['resposta_correta'] ?? 'A';
                    
                    foreach ($exercicio['alternativas'] as $letra => $texto) {
                        $textoAlternativa = is_string($texto) ? $texto : (string) $texto;
                        if (!mb_check_encoding($textoAlternativa, 'UTF-8')) {
                            $textoAlternativa = mb_convert_encoding($textoAlternativa, 'UTF-8', 'UTF-8');
                        }
                        $textoAlternativa = preg_replace('/\s*[\r\n]+\s*/u', ' ', $textoAlternativa) ?? $textoAlternativa;
                        $textoAlternativa = trim(preg_replace('/\s+/u', ' ', $textoAlternativa) ?? $textoAlternativa);
                        
                        $alternativas[] = [
                            'texto' => $textoAlternativa,
                            'correta' => (strtoupper($letra) === strtoupper($correta)) ? 1 : 0,
                            'ordem' => count($alternativas)
                        ];
                    }
                    
                    $data['alternativas'] = $alternativas;
                }
                
                $questaoId = $this->provaModel->addQuestao($id, $data);
                $questoesIds[] = $questaoId;
            }

            if ($moduloCreditoSelecionado !== null) {
                $creditosService->consumir($papelCarteira, (int) $user['id'], $moduloCreditoSelecionado, (string) $id);
            }
            
            $this->json([
                'success' => true,
                'message' => count($questoesIds) . ' questão(ões) gerada(s) com sucesso',
                'questoes_ids' => $questoesIds
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao gerar questões com IA: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Lê imagem e extrai questão usando OCR
     */
    public function lerImagemQuestao($id)
    {
        // Limpa qualquer output anterior
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Desabilita exibição de erros para não quebrar o JSON
        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', 0);
        
        $user = $this->auth->getUser();
        $isAdmin = in_array($user['tipo'], ['admin', 'admin_escola']);

        if (!$isAdmin && $user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        try {
            if (!$isAdmin && !$this->provaModel->canEdit($id, $user['id'])) {
                throw new Exception('Você não tem permissão para editar esta prova');
            }

            // Verifica se foi enviada uma imagem
            if (empty($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Nenhuma imagem foi enviada ou ocorreu um erro no upload');
            }
            
            $imagem = $_FILES['imagem'];
            
            // Valida tipo de arquivo
            $tiposPermitidos = ['image/png', 'image/jpeg', 'image/jpg'];
            if (!in_array($imagem['type'], $tiposPermitidos)) {
                throw new Exception('Tipo de arquivo não permitido. Use PNG, JPG ou JPEG');
            }
            
            // Valida tamanho (10MB)
            if ($imagem['size'] > 10 * 1024 * 1024) {
                throw new Exception('Imagem muito grande. Tamanho máximo: 10MB');
            }
            
            // Lê imagem e converte para base64
            $imageData = base64_encode(file_get_contents($imagem['tmp_name']));
            
            // Usa OpenAIService para transcrever com Google Vision
            require_once __DIR__ . '/../../Services/OpenAIService.php';
            $openaiService = new \App\Services\OpenAIService();
            
            // Transcreve a imagem
            $textoTranscrito = $openaiService->transcreverComGoogleVision($imageData);
            
            if (empty($textoTranscrito)) {
                throw new Exception('Não foi possível transcrever o texto da imagem');
            }
            
            // Usa IA para extrair enunciado e questões do texto transcrito
            $systemPrompt = 'Você é um assistente especializado em extrair questões de provas a partir de texto transcrito por OCR. 

Sua tarefa é analisar o texto transcrito e extrair:
1. O ENUNCIADO COMPLETO da questão (todo o texto antes das alternativas, incluindo o contexto e a pergunta)
2. As alternativas (A, B, C, D, E, etc.) se for múltipla escolha
3. NÃO marque nenhuma alternativa como correta (sempre false) - o professor vai marcar depois
4. O tipo de questão (multipla_escolha ou dissertativa)

REGRAS IMPORTANTES:
- O enunciado deve incluir TODO o texto antes das alternativas, incluindo contexto, explicações e a pergunta final
- Se houver texto como "QUESTÃO 180" ou números, remova apenas esses identificadores, mas mantenha todo o conteúdo da questão
- Para múltipla escolha, extraia TODAS as alternativas encontradas (A, B, C, D, E, etc.)
- Cada alternativa deve conter apenas o texto da opção, sem o prefixo "A)", "B)", etc.
- SEMPRE marque todas as alternativas como "correta": false - o professor vai selecionar a correta depois
- Limpe artefatos de OCR (quebras de linha estranhas no meio de frases, caracteres especiais)
- Mantenha parágrafos e quebras de linha naturais do texto

Retorne APENAS um JSON válido no seguinte formato:
{
  "enunciado": "texto completo do enunciado incluindo contexto e pergunta",
  "tipo": "multipla_escolha",
  "valor": 1.0,
  "alternativas": [
    {"texto": "texto da alternativa A sem o prefixo A)", "correta": false},
    {"texto": "texto da alternativa B sem o prefixo B)", "correta": false},
    {"texto": "texto da alternativa C sem o prefixo C)", "correta": false},
    {"texto": "texto da alternativa D sem o prefixo D)", "correta": false},
    {"texto": "texto da alternativa E sem o prefixo E)", "correta": false}
  ]
}';
            
            $userPrompt = "Extraia a questão do seguinte texto transcrito por OCR:\n\n" . $textoTranscrito;
            
            // Usa chatCompletion que é público
            // Aumenta timeout para processamento de imagem
            set_time_limit(300);
            ini_set('max_execution_time', 300);
            
            $response = $openaiService->chatCompletion(
                [
                    [
                        'role' => 'user',
                        'content' => $userPrompt
                    ]
                ],
                $systemPrompt,
                'gpt-4o',
                0.3,
                4000
            );
            
            $content = $response['resposta'] ?? '';
            
            if (empty($content)) {
                error_log("Resposta vazia da IA ao processar imagem");
                error_log("Resposta completa: " . print_r($response, true));
                throw new Exception('A IA não retornou nenhuma resposta. Tente novamente.');
            }
            
            // Garante que o conteúdo está em UTF-8 válido
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'auto');
            }
            
            error_log("Conteúdo bruto da IA (primeiros 1000 chars): " . substr($content, 0, 1000));
            
            // Limpar caracteres de controle (mas preservar UTF-8)
            $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $content);
            
            // Remove BOM UTF-8 se presente
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $content = substr($content, 3);
            }
            
            // Extrai JSON da resposta
            $jsonContent = null;
            
            // Tenta extrair de markdown code block
            if (preg_match('/```json\s*([\s\S]*?)\s*```/', $content, $matches)) {
                $jsonContent = trim($matches[1]);
            } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $content, $matches)) {
                $jsonContent = trim($matches[1]);
            } elseif (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $jsonContent = $matches[0];
            } else {
                // Se não encontrou JSON, tenta usar o conteúdo completo
                $jsonContent = trim($content);
            }
            
            if (empty($jsonContent)) {
                error_log("Não foi possível extrair JSON do conteúdo");
                error_log("Conteúdo completo: " . $content);
                throw new Exception('Não foi possível extrair a questão do texto. O formato da resposta não é válido.');
            }
            
            // Tenta decodificar JSON
            $questao = json_decode($jsonContent, true, 512, JSON_UNESCAPED_UNICODE);
            
            // Se falhar, tenta corrigir JSON comum
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Erro ao decodificar JSON (primeira tentativa): " . json_last_error_msg());
                error_log("JSON recebido (primeiros 1000 chars): " . substr($jsonContent, 0, 1000));
                
                // Tenta corrigir JSON comum
                $jsonContent = preg_replace('/,\s*}/', '}', $jsonContent); // Remove vírgulas finais
                $jsonContent = preg_replace('/,\s*]/', ']', $jsonContent); // Remove vírgulas finais em arrays
                $jsonContent = preg_replace('/,\s*,/', ',', $jsonContent); // Remove vírgulas duplicadas
                
                $questao = json_decode($jsonContent, true, 512, JSON_UNESCAPED_UNICODE);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("Erro ao decodificar JSON (segunda tentativa): " . json_last_error_msg());
                    error_log("JSON corrigido (primeiros 1000 chars): " . substr($jsonContent, 0, 1000));
                    throw new Exception('Erro ao processar a resposta da IA: ' . json_last_error_msg() . '. Tente novamente.');
                }
            }
            
            if (!$questao || !is_array($questao)) {
                error_log("JSON decodificado mas não é um array válido");
                throw new Exception('A resposta da IA não está no formato esperado. Tente novamente.');
            }
            
            // Garante que o tipo seja válido
            if (!isset($questao['tipo']) || !in_array($questao['tipo'], ['multipla_escolha', 'dissertativa', 'verdadeiro_falso'])) {
                $questao['tipo'] = 'multipla_escolha';
            }
            
            // Garante que valor seja numérico
            if (!isset($questao['valor']) || !is_numeric($questao['valor'])) {
                $questao['valor'] = 1.0;
            }
            
            // Garante que alternativas seja array
            if (!isset($questao['alternativas']) || !is_array($questao['alternativas'])) {
                $questao['alternativas'] = [];
            }
            
            // Garante que enunciado exista
            if (empty($questao['enunciado'])) {
                $questao['enunciado'] = $textoTranscrito; // Usa texto transcrito como fallback
            }
            
            $this->json([
                'success' => true,
                'message' => 'Questão extraída com sucesso',
                'questao' => $questao,
                'texto_transcrito' => substr($textoTranscrito, 0, 200) . '...' // Para debug
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao ler imagem de questão: " . $e->getMessage());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("Stack trace: " . $e->getTraceAsString());
                }
            }
            $this->json(['error' => $e->getMessage()], 400);
        } finally {
            // Restaura display_errors
            ini_set('display_errors', $oldDisplayErrors);
        }
    }
    
    /**
     * Inicia bloco completo de provas em sequência
     */
    public function iniciarBloco($blocoId)
    {
        $this->logProvas('[INICIAR_BLOCO] INÍCIO', ['bloco_id' => $blocoId, 'request_uri' => $_SERVER['REQUEST_URI'] ?? '']);
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("[INICIAR_BLOCO] INÍCIO blocoId=" . $blocoId . " REQUEST_URI=" . ($_SERVER['REQUEST_URI'] ?? ''));
            }
        }
        
        $user = $this->auth->getUser();
        
        if ($user['tipo'] !== 'aluno') {
            $this->logProvas('[INICIAR_BLOCO] REDIRECIONANDO: usuário não é aluno', ['tipo' => $user['tipo'] ?? null]);
            $this->redirect('/professor/provas');
            return;
        }
        
        require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
        $blocoModel = new ExamBlock();
        
        $bloco = $blocoModel->findById($blocoId);
        if (!$bloco) {
            $this->logProvas('[INICIAR_BLOCO] REDIRECIONANDO: bloco não encontrado', ['bloco_id' => $blocoId]);
            $this->setFlashMessage('Bloco de provas não disponível', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        if (!$bloco['liberado'] || !$bloco['ativo']) {
            $this->logProvas('[INICIAR_BLOCO] REDIRECIONANDO: bloco não liberado ou inativo', ['bloco_id' => $blocoId, 'liberado' => $bloco['liberado'] ?? null, 'ativo' => $bloco['ativo'] ?? null]);
            $this->setFlashMessage('Bloco de provas não disponível', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        if (!$this->alunoPodeVerBlocoNoPortalLinha($bloco)) {
            $this->setFlashMessage('Este evento de provas não está disponível no portal para os alunos.', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        if (!$blocoModel->alunoTemAcessoAoBloco($bloco, (int) $user['id'])) {
            $this->setFlashMessage('Você não tem acesso a este bloco de provas', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        
        if (!empty($bloco['liberado'])) {
            $blocoModel->garantirProvasLiberadas((int) $blocoId);
        }

        $provas = $blocoModel->getProvas($blocoId, (int) $user['id']);
        
        if (empty($provas)) {
            $this->logProvas('[INICIAR_BLOCO] REDIRECIONANDO: nenhuma prova no bloco para o aluno', ['bloco_id' => $blocoId, 'aluno_id' => (int) $user['id']]);
            $this->setFlashMessage('Não há prova disponível para a sua turma neste bloco.', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        
        // Verifica se já existe realização do bloco (usa a primeira prova como referência)
        $provaPrincipal = $provas[0];
        $realizacaoBloco = $this->provaModel->getRealizacao($provaPrincipal['id'], $user['id']);
        
        // Se já existe e está finalizada, redireciona para resultados
        if ($realizacaoBloco && $realizacaoBloco['status'] === 'finalizado') {
            $this->logProvas('[INICIAR_BLOCO] REDIRECIONANDO: bloco já finalizado -> resultados', ['bloco_id' => $blocoId]);
            $this->redirect('/aluno/provas/bloco/' . $blocoId . '/resultados');
            return;
        }
        
        $urlRealizar = '/aluno/provas/realizar/' . $provaPrincipal['id'] . '?bloco_id=' . $blocoId . '&modo_bloco=1';
        $this->logProvas('[INICIAR_BLOCO] OK redirecionando para realizar', ['prova_id' => $provaPrincipal['id'], 'url' => $urlRealizar]);
        $this->redirect($urlRealizar);
    }
    
    /**
     * Escreve no log da aplicação (storage/logs/app_*.log) para diagnóstico de provas
     */
    private function logProvas($message, $context = [])
    {
        try {
            if (!class_exists('Logger')) {
                require_once __DIR__ . '/../Core/Logger.php';
            }
            Logger::info($message, $context, 'provas');
        } catch (Throwable $e) {
            error_log("logProvas: " . $e->getMessage());
        }
    }
    
    /**
     * Inicia bloco em modo seguro: fullscreen, escolha de matéria, anti-cola.
     * Aluno só pode sair ao finalizar todas as provas do bloco.
     */
    public function iniciarBlocoSeguro($blocoId)
    {
        $blocoId = (int) $blocoId;
        $user = $this->auth->getUser();
        
        if ($user['tipo'] !== 'aluno') {
            $this->redirect('/professor/provas');
            return;
        }
        
        require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
        $blocoModel = new ExamBlock();
        
        $bloco = $blocoModel->findById($blocoId);
        if (!$bloco) {
            $this->setFlashMessage('Bloco de provas não encontrado', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        $blocoLiberadoAtivo = !empty($bloco['liberado']) && !empty($bloco['ativo']);
        if (!$blocoLiberadoAtivo) {
            $this->setFlashMessage('Bloco de provas não disponível', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        if (!$this->alunoPodeVerBlocoNoPortalLinha($bloco)) {
            $this->setFlashMessage('Este evento de provas não está disponível no portal para os alunos.', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        // Só permite iniciar dentro do período (data_prova + hora_inicio até hora_fim)
        $inicio = ($bloco['data_prova'] ?? '') . ' ' . ($bloco['hora_inicio'] ?? '00:00:00');
        $fim = ($bloco['data_prova'] ?? '') . ' ' . ($bloco['hora_fim'] ?? '23:59:59');
        $agora = date('Y-m-d H:i:s');
        if ($agora < $inicio) {
            $this->setFlashMessage('Esta prova estará disponível em ' . date('d/m/Y', strtotime($inicio)) . ' às ' . date('H:i', strtotime($inicio)) . '.', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        $isPartial = isset($_GET['partial']) && $_GET['partial'] === '1';
        if ($agora > $fim && !$isPartial) {
            $this->setFlashMessage('O período para realizar esta prova já encerrou.', 'error');
            $this->redirect('/aluno/provas');
            return;
        }

        $aluno = $this->studentModel->findById($user['id']);
        $alunoIniciar = $aluno;
        if (!$alunoIniciar) {
            $this->setFlashMessage('Aluno não encontrado', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        $alunoIniciarId = (int) $alunoIniciar['id'];

        if (!$blocoModel->alunoTemAcessoAoBloco($bloco, $alunoIniciarId)) {
            $this->setFlashMessage('Você não tem acesso a este bloco de provas', 'error');
            $this->redirect('/aluno/provas');
            return;
        }

        if ($aluno && isset($_GET['encerrado']) && $_GET['encerrado'] === '1') {
            $this->provaModel->cancelarRealizacoesBlocoSeguro($blocoId, (int) $aluno['id']);
            $this->logProvas('Cancelamento modo seguro (encerrado=1)', [
                'bloco_id' => $blocoId,
                'aluno_id' => (int) $aluno['id'],
            ]);
        }
        
        if (!empty($bloco['liberado'])) {
            $blocoModel->garantirProvasLiberadas($blocoId);
        }

        $provas = $blocoModel->getProvas($blocoId, $alunoIniciarId);
        
        if (empty($provas)) {
            $this->setFlashMessage('Não há prova disponível para a sua turma neste bloco.', 'error');
            $this->redirect('/aluno/provas');
            return;
        }

        $resumoIniciar = $blocoModel->getResumoRealizacaoAlunoNoBloco($blocoId, $alunoIniciarId);
        if (!empty($resumoIniciar['alguma_cancelada'])) {
            $this->setFlashMessage('Prova cancelada. Aguarde liberação do coordenador.', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        
        foreach ($provas as &$prova) {
            $realizacao = $this->provaModel->getRealizacao($prova['id'], $alunoIniciarId);
            $prova['realizacao_status'] = $realizacao['status'] ?? null;
        }
        unset($prova);

        // Se qualquer prova do bloco foi cancelada, bloqueia reentrada no modo seguro
        foreach ($provas as $p) {
            if (($p['realizacao_status'] ?? '') === 'cancelada') {
                $this->setFlashMessage('Prova cancelada. Aguarde liberação do coordenador.', 'error');
                $this->redirect('/aluno/provas');
                return;
            }
        }
        
        // Se todas as provas do bloco estão canceladas para este aluno, não permite entrar
        $todasCanceladas = true;
        foreach ($provas as $p) {
            if (($p['realizacao_status'] ?? '') !== 'cancelada') {
                $todasCanceladas = false;
                break;
            }
        }
        if ($todasCanceladas && count($provas) > 0) {
            $this->setFlashMessage('Prova cancelada. Aguarde liberação do coordenador.', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        
        $todasFinalizadas = true;
        foreach ($provas as $p) {
            if (($p['realizacao_status'] ?? '') !== 'finalizado') {
                $todasFinalizadas = false;
                break;
            }
        }
        
        $blocoTerminou = ($agora >= $fim);

        // EducaInclui — acomodações do aluno também afetam a tela pai do modo seguro
        // (cronômetro flutuante e bloqueios de tela cheia/Esc).
        $acessibilidadeHideTimer = false;
        $acessibilidadeRelaxSecure = false;
        try {
            require_once __DIR__ . '/../../Services/MascaraResolver.php';
            $mascaraSeguro = MascaraResolver::resolveForAluno($alunoIniciarId);
            if (!empty($mascaraSeguro['active']) && !empty($mascaraSeguro['rules'])) {
                $acessibilidadeHideTimer = MascaraResolver::isOn($mascaraSeguro['rules'], 'hide_timer');
                $acessibilidadeRelaxSecure = MascaraResolver::requiresSecureRelax($mascaraSeguro['rules']);
            }
        } catch (Throwable $eSeguro) {
            error_log('EducaInclui iniciarSeguro(): ' . $eSeguro->getMessage());
        }

        $data = [
            'title' => 'Prova em modo seguro - EducaTudo',
            'user' => $user,
            'bloco' => $bloco,
            'provas' => $provas,
            'todas_finalizadas' => $todasFinalizadas,
            'bloco_terminou' => $blocoTerminou,
            'acessibilidade_hide_timer' => $acessibilidadeHideTimer,
            'acessibilidade_relax_secure' => $acessibilidadeRelaxSecure,
        ];
        
        if ($isPartial) {
            $this->viewWithLayout('partial', 'student/exams/iniciar-seguro-fragment', $data);
            return;
        }
        
        $this->viewWithLayout('student_exam_secure', 'student/exams/iniciar-seguro', $data);
    }

    /**
     * Cancela o bloco em modo seguro por saída da tela cheia/aba/navegador.
     * Marca todas as realizações não finalizadas do aluno no bloco como 'cancelada'.
     * Só o coordenador pode liberar nova tentativa.
     */
    public function cancelarBlocoSeguro($blocoId)
    {
        $user = $this->auth->getUser();
        if ($user['tipo'] !== 'aluno') {
            $this->json(['success' => false, 'error' => 'Acesso negado'], 403);
            return;
        }
        $blocoId = (int) $blocoId;
        if ($blocoId <= 0) {
            $this->json(['success' => false, 'error' => 'Bloco inválido'], 400);
            return;
        }

        $aluno = $this->studentModel->findById($user['id']);
        if (!$aluno) {
            $this->json(['success' => false, 'error' => 'Aluno não encontrado'], 404);
            return;
        }

        require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
        $blocoModel = new ExamBlock();
        $bloco = $blocoModel->findById($blocoId);
        if (!$bloco) {
            $this->json(['success' => false, 'error' => 'Bloco inválido'], 404);
            return;
        }

        $afetadas = $this->provaModel->cancelarRealizacoesBlocoSeguro($blocoId, (int) $aluno['id']);
        $this->logProvas('Cancelamento modo seguro', [
            'bloco_id' => $blocoId,
            'aluno_id' => (int) $aluno['id'],
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'afetadas' => $afetadas,
        ]);
        // O cliente dispara essa chamada de até 3 formas em paralelo (img ping, sendBeacon,
        // fetch) de propósito — a aba pode fechar antes de qualquer uma completar, então
        // manda todas pra aumentar a chance de uma chegar (ver realizar.php:381-406). Sem
        // essa checagem, as 3 batidas nesse endpoint gerariam 3 linhas idênticas no Log de
        // Provas pra uma única saída real do aluno.
        // Nunca pode quebrar o cancelamento (ex.: migration do Log de Provas ainda não
        // rodou nessa escola) — nesse caso segue e loga normalmente (pior caso: volta a
        // duplicar, não perde o cancelamento em si).
        $jaLogado = null;
        try {
            $jaLogado = $this->db->fetch(
                "SELECT id FROM provas_log_eventos
                 WHERE tipo_evento = 'saida_modo_seguro' AND aluno_id = :aluno_id AND bloco_id = :bloco_id
                   AND created_at >= (NOW() - INTERVAL 30 SECOND)
                 LIMIT 1",
                ['aluno_id' => (int) $aluno['id'], 'bloco_id' => $blocoId]
            );
        } catch (Throwable $e) {
            // segue com $jaLogado = null
        }
        if (!$jaLogado) {
            $this->registrarLogProva('saida_modo_seguro', [
                'aluno_id' => (int) $aluno['id'],
                'bloco_id' => $blocoId,
                'detalhe' => 'Saiu do modo seguro (aba trocada/fechada) — ' . (int) $afetadas . ' realização(ões) cancelada(s).',
            ]);
        }
        $this->json(['success' => true, 'afetadas' => $afetadas]);
    }
    
    /**
     * Exibe resultados de todas as provas do bloco
     */
    public function resultadosBloco($blocoId)
    {
        $user = $this->auth->getUser();
        
        if ($user['tipo'] !== 'aluno') {
            $this->redirect('/professor/provas');
            return;
        }
        
        require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
        $blocoModel = new ExamBlock();
        
        $bloco = $blocoModel->findById($blocoId);
        if (!$bloco) {
            $this->setFlashMessage('Bloco não encontrado', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        if (!$this->alunoPodeVerBlocoNoPortalLinha($bloco)) {
            $this->setFlashMessage('Este evento de provas não está disponível no portal para os alunos.', 'error');
            $this->redirect('/aluno/provas');
            return;
        }

        if (empty($bloco['gabarito_liberado'])) {
            $this->setFlashMessage('O gabarito deste bloco ainda não foi liberado pela coordenação.', 'error');
            $this->redirect('/aluno/provas');
            return;
        }
        
        // Busca todas as provas do bloco
        $provas = $this->db->fetchAll(
            "SELECT p.*, m.nome as materia_nome, pbp.ordem
             FROM provas_blocos_vinculo pbp
             INNER JOIN provas p ON pbp.prova_id = p.id
             LEFT JOIN materias m ON p.materia_id = m.id
             WHERE pbp.bloco_id = :bloco_id
             AND p.deleted_at IS NULL
             ORDER BY pbp.ordem ASC, m.nome ASC",
            ['bloco_id' => $blocoId]
        );
        
        // Busca realização de cada prova e contagem de acertos/erros
        $resultados = [];
        $notaTotal = 0;
        $valorTotal = 0;
        $totalAcertos = 0;
        $totalErros = 0;
        
        foreach ($provas as $prova) {
            $realizacao = $this->provaModel->getRealizacao($prova['id'], $user['id']);
            if ($realizacao && $realizacao['status'] === 'finalizado') {
                $contagem = $this->provaModel->getContagemAcertosErros($prova['id'], $user['id']);
                $totalAcertos += $contagem['acertos'];
                $totalErros += $contagem['erros'];
                $resultados[] = [
                    'prova' => $prova,
                    'realizacao' => $realizacao,
                    'acertos' => $contagem['acertos'],
                    'erros' => $contagem['erros']
                ];
                $notaTotal += $realizacao['nota'] ?? 0;
                $valorTotal += $prova['valor_total'] ?? 0;
            }
        }
        
        $data = [
            'title' => 'Resultados do Bloco - EducaTudo',
            'user' => $user,
            'bloco' => $bloco,
            'resultados' => $resultados,
            'notaTotal' => $notaTotal,
            'valorTotal' => $valorTotal,
            'totalAcertos' => $totalAcertos,
            'totalErros' => $totalErros
        ];
        
        $this->viewWithLayout('student', 'student/exams/resultados-bloco', $data);
    }
    
    public function apiBancoQuestoesFacetsProva($id)
    {
        try {
            $user = $this->auth->getUser();
            $isAdmin = in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true);
            if (!$isAdmin && ($user['tipo'] ?? '') !== 'professor') {
                throw new Exception('Não autorizado');
            }
            $prova = $this->provaModel->findById((int)$id);
            if (!$prova) throw new Exception('Prova não encontrada');
            if (!$isAdmin && (int)$prova['professor_id'] !== (int)$user['id']) {
                throw new Exception('Você não tem permissão para acessar esta prova');
            }
            $query = $this->buildBancoQuestoesQueryFromRequest($_GET);
            $payload = $this->bancoQuestoesApiGet('/api/facets', $query);
            $this->json(['success' => true, 'data' => $payload]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function apiBancoQuestoesListarProva($id)
    {
        try {
            $user = $this->auth->getUser();
            $isAdmin = in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true);
            if (!$isAdmin && ($user['tipo'] ?? '') !== 'professor') {
                throw new Exception('Não autorizado');
            }
            $prova = $this->provaModel->findById((int)$id);
            if (!$prova) throw new Exception('Prova não encontrada');
            if (!$isAdmin && (int)$prova['professor_id'] !== (int)$user['id']) {
                throw new Exception('Você não tem permissão para acessar esta prova');
            }

            $query = $this->buildBancoQuestoesQueryFromRequest($_GET);
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            if ($limit < 1) $limit = 20;
            if ($limit > 100) $limit = 100;
            if ($offset < 0) $offset = 0;
            $query['limit'] = $limit;
            $query['offset'] = $offset;

            $payload = $this->bancoQuestoesApiGet('/api/questoes', $query);
            $this->json(['success' => true, 'data' => $payload]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function apiBancoQuestoesImportarProva($id)
    {
        try {
            $user = $this->auth->getUser();
            $isAdmin = in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true);
            if (!$isAdmin && ($user['tipo'] ?? '') !== 'professor') {
                throw new Exception('Não autorizado');
            }
            $prova = $this->provaModel->findById((int)$id);
            if (!$prova) throw new Exception('Prova não encontrada');
            if (!$isAdmin && (int)$prova['professor_id'] !== (int)$user['id']) {
                throw new Exception('Você não tem permissão para editar esta prova');
            }

            $raw = json_decode((string)file_get_contents('php://input'), true);
            if (!is_array($raw)) throw new Exception('Payload JSON inválido');
            $ids = isset($raw['questao_ids']) && is_array($raw['questao_ids']) ? $raw['questao_ids'] : [];
            $ids = array_values(array_unique(array_filter(array_map(function ($qid) {
                return trim((string)$qid);
            }, $ids), function ($qid) {
                return $qid !== '';
            })));
            if (empty($ids)) throw new Exception('Selecione ao menos uma questão');

            require_once __DIR__ . '/../../Services/CreditosService.php';
            $creditosService = new \App\Services\CreditosService();
            // Banco de Questões da Prova deve consumir no módulo de Prova (1 crédito conforme precificação).
            $moduloCredito = 'gerar_exercicio_ia_professor';
            $ordemBase = count($this->provaModel->getQuestoes((int)$id));
            $inseridos = 0;
            $falhas = [];
            $creditosConsumidos = 0;

            foreach ($ids as $idx => $questaoId) {
                $creditoReferencia = null;
                try {
                    $payload = $this->bancoQuestoesApiGet('/api/questoes', ['id' => $questaoId, 'limit' => 1, 'offset' => 0]);
                    $questoes = is_array($payload['questoes'] ?? null) ? $payload['questoes'] : [];
                    if (empty($questoes[0]) || !is_array($questoes[0])) throw new Exception('Questão não encontrada na API');
                    $map = $this->mapBancoQuestaoToProvaQuestao($questoes[0]);
                    $map['ordem'] = $ordemBase + $idx;

                    if ($moduloCredito !== null) {
                        $creditoReferencia = 'bancoq:prova:' . (int)$id . ':' . $questaoId . ':' . uniqid();
                        $creditosService->consumir('professor', (int)$prova['professor_id'], $moduloCredito, $creditoReferencia);
                    }

                    $this->provaModel->addQuestao((int)$id, $map);
                    $inseridos++;
                    if ($moduloCredito !== null) {
                        $creditosConsumidos++;
                    }
                } catch (Exception $qe) {
                    if ($creditoReferencia !== null && $moduloCredito !== null) {
                        try { $creditosService->estornarPorReferencia($moduloCredito, $creditoReferencia); }
                        catch (Exception $e2) { error_log('Falha no estorno de crédito (prova banco questões): ' . $e2->getMessage()); }
                    }
                    $falhas[] = ['id' => $questaoId, 'erro' => $qe->getMessage()];
                }
            }

            $this->json(['success' => true, 'importados' => $inseridos, 'creditos_consumidos' => $creditosConsumidos, 'falhas' => $falhas]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    private function buildBancoQuestoesQueryFromRequest(array $src): array
    {
        $allowed = ['q', 'id', 'materia', 'dificuldade', 'tag', 'topico', 'ano', 'origem_titulo', 'tipo'];
        $query = [];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $src)) continue;
            $v = trim((string)$src[$k]);
            if ($v !== '') $query[$k] = $v;
        }
        return $query;
    }

    private function bancoQuestoesApiBaseUrl(): string
    {
        $fromEnv = getenv('BANCO_QUESTOES_API_BASE');
        if (is_string($fromEnv) && trim($fromEnv) !== '') return rtrim(trim($fromEnv), '/');
        return 'http://69.62.86.185:8080';
    }

    private function bancoQuestoesApiGet(string $path, array $query = []): array
    {
        $url = $this->bancoQuestoesApiBaseUrl() . $path;
        if (!empty($query)) $url .= '?' . http_build_query($query);
        if (!function_exists('curl_init')) throw new Exception('cURL não está disponível no servidor.');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '') throw new Exception('Falha ao consultar API de questões: ' . $error);
        if ($httpCode < 200 || $httpCode >= 300) throw new Exception('API de questões retornou HTTP ' . $httpCode);
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) throw new Exception('Resposta inválida da API de questões.');
        return $decoded;
    }

    private function mapBancoQuestaoToProvaQuestao(array $q): array
    {
        $enunciadoRaw = (string)($q['enunciado_html'] ?? $q['enunciado'] ?? '');
        $enunciado = \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($enunciadoRaw);
        if (trim(strip_tags($enunciado)) === '' && trim($enunciado) === '') throw new Exception('Enunciado vazio');

        $alternativas = isset($q['alternativas']) && is_array($q['alternativas']) ? $q['alternativas'] : [];
        $gabarito = strtoupper(trim((string)($q['gabarito'] ?? '')));
        $tipo = !empty($alternativas) ? 'multipla_escolha' : 'dissertativa';
        $alternativasFormatadas = [];

        if ($tipo === 'multipla_escolha') {
            $ordemLetras = ['A', 'B', 'C', 'D', 'E', 'F'];
            foreach ($ordemLetras as $letra) {
                if (!array_key_exists($letra, $alternativas)) continue;
                $alternativasFormatadas[] = [
                    'texto' => \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages((string)$alternativas[$letra]),
                    'correta' => ($gabarito !== '' && strtoupper($letra) === $gabarito) ? 1 : 0,
                    'ordem' => count($alternativasFormatadas),
                ];
            }
            if (count($alternativasFormatadas) < 2) throw new Exception('Questão sem alternativas suficientes');
        }

        $dif = strtolower(trim((string)($q['dificuldade'] ?? '')));
        $nivel = null;
        if (in_array($dif, ['fácil', 'facil'], true)) $nivel = 'Fácil';
        if ($dif === 'médio' || $dif === 'medio') $nivel = 'Médio';
        if ($dif === 'difícil' || $dif === 'dificil') $nivel = 'Difícil';

        return [
            'enunciado' => $enunciado,
            'imagem_url' => null,
            'tipo' => $tipo,
            'valor' => 1.00,
            'nivel_dificuldade' => $nivel,
            'ordem' => 0,
            'alternativas' => $alternativasFormatadas,
            'explicacao' => null,
        ];
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
            case 'admin':
                $this->redirect('/admin/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/');
        }
    }

    /**
     * Versão assíncrona de gerarQuestoesIA.
     * Enfileira o job e retorna {job_id} imediatamente.
     * O frontend faz polling em GET /ai-job/{job_id}/status.
     *
     * Rota: POST /professor/provas/{id}/gerar-questoes-ia-async
     */
    public function gerarQuestoesIAAsync($id): void
    {
        $user = $this->auth->getUser();
        $isAdmin = in_array($user['tipo'], ['admin', 'admin_escola']);

        if (!$isAdmin && $user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        try {
            if (!$isAdmin && !$this->provaModel->canEdit($id, $user['id'])) {
                $this->json(['error' => 'Sem permissão para editar esta prova'], 403);
                return;
            }

            $input    = file_get_contents('php://input');
            $postData = json_decode($input, true) ?: $_POST;

            $prova = $this->provaModel->find($id);
            if (!$prova) {
                $this->json(['error' => 'Prova não encontrada'], 404);
                return;
            }

            require_once __DIR__ . '/../../AI/GeradorQuestaoService.php';

            $tipoQuestao = ($postData['tipo'] ?? 'alternativas') === 'dissertativas' ? 'dissertativa' : 'multipla_escolha';

            $blueprint = [
                'disciplina' => $postData['materia'] ?? '',
                'assunto' => $prova['titulo'],
                'serie' => $postData['serie'] ?? '',
                'dificuldade' => $postData['nivel'] ?? 'medio',
                'tipo_questao' => $tipoQuestao,
                'quantidade' => (int) ($postData['quantidade'] ?? 5),
                'com_recurso_visual' => !empty($postData['com_imagens']) ? 'auto' : false,
                'contexto_adicional' => $postData['contexto'] ?? '',
                'origem' => 'prova',
                'usuario_id' => (int) $user['id'],
                'papel' => $isAdmin ? 'admin' : 'professor',
                'config' => $this->config,
                'prova_id' => (int) $id, // acompanha o payload, lido depois em importarQuestoesIA
            ];

            $jobId = \App\AI\GeradorQuestaoService::solicitar($blueprint);

            $this->json(['job_id' => $jobId]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Chamado pelo frontend (AIJobPoller) quando o job assíncrono do motor
     * único de geração de questão (gerar_questao_ia) termina — persiste as
     * questões do formato canônico em provas_questoes/provas_alternativas
     * via Exam::addQuestao() (já existente, sem mudança).
     */
    public function importarQuestoesIA($jobId): void
    {
        $user = $this->auth->getUser();
        $isAdmin = in_array($user['tipo'], ['admin', 'admin_escola']);
        if (!$isAdmin && $user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        require_once __DIR__ . '/../../Services/AIJobService.php';
        $job = \App\Services\AIJobService::getJob((int) $jobId);
        if (!$job || $job['status'] !== 'done') {
            $this->json(['error' => 'Job não concluído ou não encontrado'], 404);
            return;
        }

        $payload = json_decode($job['payload'], true) ?: [];
        $provaId = (int) ($payload['prova_id'] ?? 0);
        if ($provaId <= 0 || (!$isAdmin && !$this->provaModel->canEdit($provaId, $user['id']))) {
            $this->json(['error' => 'Sem permissão para editar esta prova'], 403);
            return;
        }

        $result = json_decode($job['result'], true) ?: [];
        $questoes = $result['questoes'] ?? [];
        if (empty($questoes)) {
            $this->json(['error' => 'Nenhuma questão gerada'], 422);
            return;
        }

        $importadas = 0;
        foreach ($questoes as $ordem => $questao) {
            $this->provaModel->addQuestao($provaId, [
                'enunciado' => $questao['enunciado'] ?? '',
                'imagem_url' => $questao['imagem']['url'] ?? null,
                'tipo' => $questao['tipo'] ?? 'multipla_escolha',
                'nivel_dificuldade' => $questao['nivel_dificuldade'] ?? 'medio',
                'ordem' => $ordem,
                'explicacao' => $questao['explicacao'] ?? null,
                'alternativas' => $questao['alternativas'] ?? [],
            ]);
            $importadas++;
        }

        $this->json(['success' => true, 'questoes_importadas' => $importadas]);
    }
}
}
