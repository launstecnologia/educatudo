<?php
/**
 * EducaTudo - ProvaBlocoController
 * Gerencia blocos de provas para admin/coordenador/diretor
 */

require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
require_once __DIR__ . '/../../Models/Exams/Exam.php';
require_once __DIR__ . '/../../Models/Exams/ExamEvaluationType.php';

if (!class_exists('ExamBlockController')) {
class ExamBlockController extends BaseController
{
    private $authManager;
    private $db;
    private $blocoModel;
    private $provaModel;
    private $evaluationTypeModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        $this->blocoModel = new ExamBlock();
        $this->provaModel = new Exam();
        $this->evaluationTypeModel = new ExamEvaluationType();
        
        // Verifica se está logado
        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
        }
        
        // Verifica se é admin/coordenador/diretor
        $user = $this->authManager->getUser();
        if (!$user || ($user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola')) {
            $this->redirectToCorrectDashboard($user['tipo'] ?? 'aluno');
            return;
        }
        
        if (!class_exists('AdminSecretariaAccess')) {
            require_once __DIR__ . '/../../Core/AdminSecretariaAccess.php';
        }
        // Verifica perfil_admin se for admin_escola
        if ($user['tipo'] === 'admin_escola' && !in_array($user['perfil_admin'] ?? '', AdminSecretariaAccess::perfisAdminEscolaGestaoPedagogica(), true)) {
            $this->redirect('/admin/dashboard');
        }
    }

    /**
     * Colunas obrigatórias para cadastro/edição completo do evento (evita gravar sem bimestre/formato por detecção errada).
     *
     * @return list<string> nomes faltando
     */
    private function colunasEscopoProvasBlocosFaltando(): array
    {
        $obrigatorias = ['ano_letivo', 'bimestre', 'formato_evento'];
        $faltando = [];
        foreach ($obrigatorias as $col) {
            if (!$this->blocoModel->columnExistsOnBloco($col)) {
                $faltando[] = $col;
            }
        }
        return $faltando;
    }

    /**
     * Normaliza data/hora para MySQL DATETIME.
     * Aceita: YYYY-mm-ddTHH:ii, YYYY-mm-dd HH:ii[:ss], dd/mm/YYYY[, ]HH:ii.
     */
    private function normalizarDataHoraParaDb($valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $raw = trim((string) $valor);
        if ($raw === '') {
            return null;
        }

        // HTML datetime-local
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $raw) === 1) {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $raw);
            return $dt ? $dt->format('Y-m-d H:i:s') : null;
        }

        // MySQL-like já com espaço
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?$/', $raw) === 1) {
            $dt = DateTime::createFromFormat(strlen($raw) > 16 ? 'Y-m-d H:i:s' : 'Y-m-d H:i', $raw);
            return $dt ? $dt->format('Y-m-d H:i:s') : null;
        }

        // Formato brasileiro: 08/05/2026, 00:00  ou 08/05/2026 00:00
        $br = str_replace(', ', ' ', $raw);
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}$/', $br) === 1) {
            $dt = DateTime::createFromFormat('d/m/Y H:i', $br);
            return $dt ? $dt->format('Y-m-d H:i:s') : null;
        }

        // Fallback tolerante: aceita separadores variados (ex.: 08/05/2026, 00,00)
        if (preg_match('/^\s*(\d{1,2})\D+(\d{1,2})\D+(\d{4})\D+(\d{1,2})\D+(\d{1,2})\s*$/', $raw, $m) === 1) {
            $d = (int) ($m[1] ?? 0);
            $mo = (int) ($m[2] ?? 0);
            $y = (int) ($m[3] ?? 0);
            $h = (int) ($m[4] ?? 0);
            $i = (int) ($m[5] ?? 0);
            if (checkdate($mo, $d, $y) && $h >= 0 && $h <= 23 && $i >= 0 && $i <= 59) {
                return sprintf('%04d-%02d-%02d %02d:%02d:00', $y, $mo, $d, $h, $i);
            }
        }

        return null;
    }

    /**
     * Fallback para prazo do professor quando vier vazio:
     * usa data da prova + hora fim (ou hora início).
     */
    private function montarPrazoFallback(array $postData): ?string
    {
        $data = trim((string) ($postData['data_prova'] ?? ''));
        $horaFim = trim((string) ($postData['hora_fim'] ?? ''));
        $horaInicio = trim((string) ($postData['hora_inicio'] ?? ''));
        $hora = $horaFim !== '' ? $horaFim : $horaInicio;
        if ($data === '' || $hora === '') {
            return null;
        }
        return $this->normalizarDataHoraParaDb($data . ' ' . $hora);
    }
    
    /**
     * Lista todos os blocos
     */
    public function index()
    {
        $user = $this->authManager->getUser();
        
        $blocos = $this->blocoModel->getAll();
        
        // Atualiza concluídos e busca estatísticas por status
        $this->blocoModel->marcarConcluidos();
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
        $provasPendentes = $this->blocoModel->getProvasPendentes();
        
        $data = [
            'title' => 'Gestão de Blocos de Provas - EducaTudo',
            'user' => $user,
            'blocos' => $blocos,
            'stats' => $stats,
            'provas_pendentes' => $provasPendentes,
            'current_page' => 'provas_blocos'
        ];
        
        $this->viewWithLayout('admin', 'admin/exams/blocks/index', $data);
    }
    
    /**
     * Exibe formulário de criação de bloco
     */
    public function criar()
    {
        $user = $this->authManager->getUser();
        
        // Busca professores ativos
        $professores = $this->db->fetchAll(
            "SELECT * FROM professores WHERE ativo = 1 ORDER BY nome ASC"
        );
        
        // Processa professores para incluir matérias como array de nomes
        foreach ($professores as &$prof) {
            $materiasJson = $prof['materias'] ?? '[]';
            $materiasNomes = json_decode($materiasJson, true) ?: [];
            
            // O campo materias já contém nomes (strings), usa diretamente
            $prof['materias'] = is_array($materiasNomes) ? $materiasNomes : [];
        }
        
        // Busca matérias
        $materias = $this->db->fetchAll(
            "SELECT * FROM materias ORDER BY nome ASC"
        );
        
        // Busca turmas
        $turmas = $this->db->fetchAll(
            "SELECT * FROM turmas WHERE ativo = 1 ORDER BY nome ASC"
        );
        
        // Busca blocos modelo
        require_once __DIR__ . '/../../Models/Exams/ExamBlockModel.php';
        $blocoModeloModel = new ExamBlockModel();
        $blocosModelo = $blocoModeloModel->getAll();
        $tiposAvaliacao = $this->evaluationTypeModel->getAllActive();
        
        $data = [
            'title' => 'Criar Evento Prova Online - EducaTudo',
            'user' => $user,
            'professores' => $professores,
            'materias' => $materias,
            'turmas' => $turmas,
            'blocosModelo' => $blocosModelo,
            'tiposAvaliacao' => $tiposAvaliacao,
            'current_page' => 'provas_blocos'
        ];
        
        $this->viewWithLayout('admin', 'admin/exams/blocks/create', $data);
    }
    
    /**
     * Salva um novo bloco
     */
    public function salvar()
    {
        $user = $this->authManager->getUser();
        
        try {
            $input = file_get_contents('php://input');
            $postData = json_decode($input, true);
            
            if (!$postData) {
                $postData = $_POST;
            }
            $postData['prazo_entrega_professor'] = $this->normalizarDataHoraParaDb($postData['prazo_entrega_professor'] ?? null);
            if (empty($postData['prazo_entrega_professor'])) {
                $postData['prazo_entrega_professor'] = $this->montarPrazoFallback($postData);
            }
            
            // Validação
            $errors = [];

            $formatoEvento = $postData['formato_evento'] ?? 'online_questoes';
            if (!in_array($formatoEvento, ['online_questoes', 'lancamento_nota'], true)) {
                $formatoEvento = 'online_questoes';
            }
            $configuracaoNota = (string) ($postData['configuracao_nota'] ?? '');
            if ($formatoEvento === 'lancamento_nota') {
                // Compatível com schema legado: usa somente valores já existentes no banco
                if (in_array($configuracaoNota, ['coordenacao_lanca', 'coordenacao_calcula'], true)) {
                    $configuracaoNota = 'coordenacao_calcula';
                } elseif (in_array($configuracaoNota, ['professor_lanca', 'professor_por_questao'], true)) {
                    $configuracaoNota = 'professor_por_questao';
                } else {
                    $configuracaoNota = 'coordenacao_calcula';
                }
            } else {
                if (!in_array($configuracaoNota, ['professor_por_questao', 'coordenacao_calcula'], true)) {
                    $configuracaoNota = 'professor_por_questao';
                }
            }
            $exigeDataHoraProva = ($formatoEvento === 'online_questoes');
            $exigePrazoProfessor = ($formatoEvento === 'online_questoes')
                || ($formatoEvento === 'lancamento_nota' && $configuracaoNota === 'professor_por_questao');
            
            if (empty($postData['titulo'])) {
                $errors['titulo'] = 'Título é obrigatório';
            }
            if (empty($postData['ano_letivo']) || (int)$postData['ano_letivo'] < 2000) {
                $errors['ano_letivo'] = 'Ano letivo é obrigatório';
            }
            if (empty($postData['bimestre']) || !in_array((int)$postData['bimestre'], [1, 2, 3, 4], true)) {
                $errors['bimestre'] = 'Bimestre inválido';
            }
            $tipoAvaliacaoId = isset($postData['tipo_avaliacao_id']) ? (int)$postData['tipo_avaliacao_id'] : 0;
            if ($tipoAvaliacaoId <= 0 || !$this->evaluationTypeModel->findById($tipoAvaliacaoId)) {
                $errors['tipo_avaliacao_id'] = 'Tipo de avaliação é obrigatório';
            }
            if (empty($postData['professores']) || !is_array($postData['professores']) || count($postData['professores']) === 0) {
                $errors['professores'] = 'Adicione pelo menos um professor';
            }
            if ($exigeDataHoraProva && empty($postData['data_prova'])) {
                $errors['data_prova'] = 'Data da prova é obrigatória';
            }
            if ($exigeDataHoraProva && empty($postData['hora_inicio'])) {
                $errors['hora_inicio'] = 'Horário de início é obrigatório';
            }
            if ($exigeDataHoraProva && empty($postData['hora_fim'])) {
                $errors['hora_fim'] = 'Horário de término é obrigatório';
            }
            if ($exigePrazoProfessor && empty($postData['prazo_entrega_professor'])) {
                $errors['prazo_entrega_professor'] = 'Prazo para professor é obrigatório';
            }
            
            // Valida turmas do bloco
            if (empty($postData['turmas']) || !is_array($postData['turmas']) || count($postData['turmas']) === 0) {
                $errors['turmas'] = "Selecione pelo menos uma turma para o bloco";
            }
            
            // Valida cada professor
            if (!empty($postData['professores'])) {
                foreach ($postData['professores'] as $index => $professor) {
                    if (empty($professor['professor_id'])) {
                        $errors["professor_{$index}"] = "Professor #{$index}: Professor é obrigatório";
                    }
                    if (empty($professor['materia_id'])) {
                        $errors["materia_{$index}"] = "Professor #{$index}: Matéria é obrigatória";
                    }
                }
            }
            
            if (!empty($errors)) {
                $this->json(['error' => 'Dados inválidos', 'errors' => $errors], 400);
                return;
            }

            $faltandoCols = $this->colunasEscopoProvasBlocosFaltando();
            if (!empty($faltandoCols)) {
                $this->json([
                    'error' => 'O banco desta escola não tem colunas necessárias em provas_blocos. Execute no MySQL as migrações: database/migrations/2026_04_17_provas_blocos_ano_letivo_bimestre.sql e 2026_04_16_provas_blocos_formato_notas_lancadas.sql (colunas faltando: ' . implode(', ', $faltandoCols) . ').',
                    'errors' => ['_schema' => 'Migrações SQL pendentes'],
                    'missing_columns' => $faltandoCols,
                ], 400);
                return;
            }
            
            $visivelPortal = !empty($postData['visivel_no_portal_aluno']) ? 1 : 0;
            $liberadoInicial = 0;
            if ($visivelPortal
                && $formatoEvento === 'online_questoes'
                && !empty($postData['data_prova'])
                && !empty($postData['hora_inicio'])
                && !empty($postData['hora_fim'])) {
                $agora = date('Y-m-d H:i:s');
                $inicio = $postData['data_prova'] . ' ' . $postData['hora_inicio'];
                $fim = $postData['data_prova'] . ' ' . $postData['hora_fim'];
                $liberadoInicial = ($inicio <= $agora && $fim >= $agora) ? 1 : 0;
            }

            // Prepara dados
            $data = [
                'titulo' => $postData['titulo'],
                'descricao' => $postData['descricao'] ?? null,
                'ano_letivo' => isset($postData['ano_letivo']) ? (int)$postData['ano_letivo'] : null,
                'bimestre' => isset($postData['bimestre']) ? (int)$postData['bimestre'] : null,
                'tipo_avaliacao_id' => $tipoAvaliacaoId > 0 ? $tipoAvaliacaoId : null,
                'data_prova' => $exigeDataHoraProva ? ($postData['data_prova'] ?? null) : null,
                'hora_inicio' => $exigeDataHoraProva ? ($postData['hora_inicio'] ?? null) : null,
                'hora_fim' => $exigeDataHoraProva ? ($postData['hora_fim'] ?? null) : null,
                'criado_por' => $user['id'],
                'professores' => $postData['professores'] ?? [], // Array de professores com matérias (sem turmas)
                'turmas' => $postData['turmas'] ?? [], // Turmas do bloco (não por professor)
                'tipo_prova' => $postData['tipo_prova'] ?? 'original',
                'formato_evento' => $formatoEvento,
                'configuracao_nota' => $configuracaoNota,
                'liberar_gabarito' => 'imediatamente',
                'provas' => $postData['provas'] ?? [], // Provas serão criadas pelo professor depois
                'ativo' => $postData['ativo'] ?? 1,
                'liberado' => $liberadoInicial,
                'bloco_modelo_id' => !empty($postData['bloco_modelo_id']) ? (int)$postData['bloco_modelo_id'] : null,
                'visivel_no_portal_aluno' => $visivelPortal,
                'nota_unica_todas_materias' => !empty($postData['nota_unica_todas_materias']) ? 1 : 0,
                'prazo_entrega_professor' => $exigePrazoProfessor ? ($postData['prazo_entrega_professor'] ?? null) : null,
            ];
            
            $blocoId = $this->blocoModel->create($data);
            
            $this->json([
                'success' => true,
                'message' => 'Bloco criado com sucesso',
                'id' => $blocoId
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao salvar bloco: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Exibe formulário de edição
     */
    public function editar($id)
    {
        $user = $this->authManager->getUser();
        
        $bloco = $this->blocoModel->findById($id);
        
        if (!$bloco) {
            $this->setFlashMessage('Bloco não encontrado', 'error');
            $this->redirect('/admin/provas');
            return;
        }
        
        // Busca provas pendentes + provas já no bloco
        $provasPendentes = $this->blocoModel->getProvasPendentes();
        $provasNoBloco = $bloco['provas'] ?? [];
        $provasIdsNoBloco = array_column($provasNoBloco, 'id');
        
        // Adiciona provas do bloco à lista (mesmo que não estejam pendentes)
        foreach ($provasNoBloco as $prova) {
            if (!in_array($prova['id'], array_column($provasPendentes, 'id'))) {
                $provasPendentes[] = $prova;
            }
        }
        
        // Busca professores ativos (com matérias para o dropdown)
        $professores = $this->db->fetchAll(
            "SELECT * FROM professores WHERE ativo = 1 ORDER BY nome ASC"
        );
        foreach ($professores as &$prof) {
            $materiasJson = $prof['materias'] ?? '[]';
            $materiasNomes = json_decode($materiasJson, true) ?: [];
            $prof['materias'] = is_array($materiasNomes) ? $materiasNomes : [];
        }
        unset($prof);
        
        // Busca matérias
        $materias = $this->db->fetchAll(
            "SELECT * FROM materias ORDER BY nome ASC"
        );
        
        // Busca turmas
        $turmas = $this->db->fetchAll(
            "SELECT * FROM turmas WHERE ativo = 1 ORDER BY nome ASC"
        );
        $tiposAvaliacao = $this->evaluationTypeModel->getAll();
        
        $data = [
            'title' => 'Editar Bloco de Provas - EducaTudo',
            'user' => $user,
            'bloco' => $bloco,
            'professores' => $professores,
            'materias' => $materias,
            'provas_pendentes' => $provasPendentes,
            'provas_ids_no_bloco' => $provasIdsNoBloco,
            'turmas' => $turmas,
            'tiposAvaliacao' => $tiposAvaliacao,
            'current_page' => 'provas_blocos'
        ];
        
        $this->viewWithLayout('admin', 'admin/exams/blocks/edit', $data);
    }
    
    /**
     * Atualiza um bloco
     */
    public function atualizar($id)
    {
        $user = $this->authManager->getUser();
        
        try {
            $bloco = $this->blocoModel->findById($id);
            
            if (!$bloco) {
                $this->json(['error' => 'Bloco não encontrado'], 404);
                return;
            }
            
            $input = file_get_contents('php://input');
            $postData = json_decode($input, true);
            
            if (!$postData) {
                $postData = $_POST;
            }
            $postData['prazo_entrega_professor'] = $this->normalizarDataHoraParaDb($postData['prazo_entrega_professor'] ?? null);
            if (empty($postData['prazo_entrega_professor'])) {
                $postData['prazo_entrega_professor'] = $this->montarPrazoFallback($postData);
            }
            
            // Usa turmas gerais do bloco (não por professor).
            // Se vier vazio, mantém as turmas já vinculadas para não perder configuração antiga.
            $turmasFinal = [];
            foreach (($postData['turmas'] ?? []) as $tid) {
                $tidInt = (int) (is_array($tid) ? ($tid['id'] ?? 0) : $tid);
                if ($tidInt > 0) {
                    $turmasFinal[$tidInt] = true;
                }
            }
            $turmasFinal = array_values(array_keys($turmasFinal));
            if (empty($turmasFinal)) {
                $turmasBlocoAtual = $this->blocoModel->getTurmas((int) $id);
                $turmasFinal = array_values(array_unique(array_filter(array_map(static function ($t) {
                    if (is_array($t)) {
                        return (int) ($t['id'] ?? 0);
                    }
                    return (int) $t;
                }, $turmasBlocoAtual ?: []), static function ($v) {
                    return $v > 0;
                })));
            }
            $postData['turmas'] = $turmasFinal;
            
            // Validação
            $errors = [];

            $formatoEvento = $postData['formato_evento'] ?? 'online_questoes';
            if (!in_array($formatoEvento, ['online_questoes', 'lancamento_nota'], true)) {
                $formatoEvento = 'online_questoes';
            }
            $configuracaoNota = (string) ($postData['configuracao_nota'] ?? '');
            if ($formatoEvento === 'lancamento_nota') {
                // Compatível com schema legado: usa somente valores já existentes no banco
                if (in_array($configuracaoNota, ['coordenacao_lanca', 'coordenacao_calcula'], true)) {
                    $configuracaoNota = 'coordenacao_calcula';
                } elseif (in_array($configuracaoNota, ['professor_lanca', 'professor_por_questao'], true)) {
                    $configuracaoNota = 'professor_por_questao';
                } else {
                    $configuracaoNota = 'coordenacao_calcula';
                }
            } else {
                if (!in_array($configuracaoNota, ['professor_por_questao', 'coordenacao_calcula'], true)) {
                    $configuracaoNota = 'professor_por_questao';
                }
            }
            $exigeDataHoraProva = ($formatoEvento === 'online_questoes');
            $exigePrazoProfessor = ($formatoEvento === 'online_questoes')
                || ($formatoEvento === 'lancamento_nota' && $configuracaoNota === 'professor_por_questao');
            
            if (empty($postData['titulo'])) {
                $errors['titulo'] = 'Título é obrigatório';
            }
            if (empty($postData['ano_letivo']) || (int)$postData['ano_letivo'] < 2000) {
                $errors['ano_letivo'] = 'Ano letivo é obrigatório';
            }
            if (empty($postData['bimestre']) || !in_array((int)$postData['bimestre'], [1, 2, 3, 4], true)) {
                $errors['bimestre'] = 'Bimestre inválido';
            }
            $tipoAvaliacaoId = isset($postData['tipo_avaliacao_id']) ? (int)$postData['tipo_avaliacao_id'] : 0;
            if ($tipoAvaliacaoId <= 0 || !$this->evaluationTypeModel->findById($tipoAvaliacaoId)) {
                $errors['tipo_avaliacao_id'] = 'Tipo de avaliação é obrigatório';
            }
            if (empty($postData['professores']) || !is_array($postData['professores']) || count($postData['professores']) === 0) {
                $errors['professores'] = 'Adicione pelo menos um professor';
            }
            if ($exigeDataHoraProva && empty($postData['data_prova'])) {
                $errors['data_prova'] = 'Data da prova é obrigatória';
            }
            if ($exigeDataHoraProva && empty($postData['hora_inicio'])) {
                $errors['hora_inicio'] = 'Horário de início é obrigatório';
            }
            if ($exigeDataHoraProva && empty($postData['hora_fim'])) {
                $errors['hora_fim'] = 'Horário de término é obrigatório';
            }
            if ($exigePrazoProfessor && empty($postData['prazo_entrega_professor'])) {
                $errors['prazo_entrega_professor'] = 'Prazo para professor é obrigatório';
            }
            
            if (empty($postData['turmas']) || !is_array($postData['turmas']) || count($postData['turmas']) === 0) {
                $errors['turmas'] = "Selecione pelo menos uma turma para o bloco";
            }
            
            // Valida cada professor
            if (!empty($postData['professores'])) {
                foreach ($postData['professores'] as $index => $professor) {
                    if (empty($professor['professor_id'])) {
                        $errors["professor_{$index}"] = "Professor #{$index}: Professor é obrigatório";
                    }
                    if (empty($professor['materia_id'])) {
                        $errors["materia_{$index}"] = "Professor #{$index}: Matéria é obrigatória";
                    }
                }
            }
            
            if (!empty($errors)) {
                $this->json(['error' => 'Dados inválidos', 'errors' => $errors], 400);
                return;
            }

            $faltandoCols = $this->colunasEscopoProvasBlocosFaltando();
            if (!empty($faltandoCols)) {
                $this->json([
                    'error' => 'O banco desta escola não tem colunas necessárias em provas_blocos. Execute no MySQL as migrações: database/migrations/2026_04_17_provas_blocos_ano_letivo_bimestre.sql e 2026_04_16_provas_blocos_formato_notas_lancadas.sql (colunas faltando: ' . implode(', ', $faltandoCols) . ').',
                    'errors' => ['_schema' => 'Migrações SQL pendentes'],
                    'missing_columns' => $faltandoCols,
                ], 400);
                return;
            }
            
            $visivelPortal = !empty($postData['visivel_no_portal_aluno']) ? 1 : 0;
            $liberadoAtual = isset($postData['liberado']) ? (int) $postData['liberado'] : (int) ($bloco['liberado'] ?? 0);
            if ($visivelPortal
                && $formatoEvento === 'online_questoes'
                && !empty($postData['data_prova'])
                && !empty($postData['hora_inicio'])
                && !empty($postData['hora_fim'])) {
                $agora = date('Y-m-d H:i:s');
                $inicio = $postData['data_prova'] . ' ' . $postData['hora_inicio'];
                $fim = $postData['data_prova'] . ' ' . $postData['hora_fim'];
                if ($inicio <= $agora && $fim >= $agora) {
                    $liberadoAtual = 1;
                }
            }

            // Prepara dados
            $data = [
                'titulo' => $postData['titulo'],
                'descricao' => $postData['descricao'] ?? null,
                'ano_letivo' => isset($postData['ano_letivo']) ? (int)$postData['ano_letivo'] : null,
                'bimestre' => isset($postData['bimestre']) ? (int)$postData['bimestre'] : null,
                'tipo_avaliacao_id' => $tipoAvaliacaoId > 0 ? $tipoAvaliacaoId : null,
                'data_prova' => $exigeDataHoraProva ? ($postData['data_prova'] ?? null) : null,
                'hora_inicio' => $exigeDataHoraProva ? ($postData['hora_inicio'] ?? null) : null,
                'hora_fim' => $exigeDataHoraProva ? ($postData['hora_fim'] ?? null) : null,
                'professores' => $postData['professores'] ?? [],
                'turmas' => $postData['turmas'] ?? [],
                'tipo_prova' => $postData['tipo_prova'] ?? 'original',
                'formato_evento' => $formatoEvento,
                'configuracao_nota' => $configuracaoNota,
                'liberar_gabarito' => 'imediatamente',
                'ativo' => $postData['ativo'] ?? 1,
                'liberado' => $liberadoAtual,
                'visivel_no_portal_aluno' => $visivelPortal,
                'nota_unica_todas_materias' => !empty($postData['nota_unica_todas_materias']) ? 1 : 0,
                'prazo_entrega_professor' => $exigePrazoProfessor ? ($postData['prazo_entrega_professor'] ?? null) : null,
            ];
            if (array_key_exists('provas', $postData) && is_array($postData['provas'])) {
                $data['provas'] = $postData['provas'];
            }
            
            $this->blocoModel->update($id, $data);
            
            $this->json([
                'success' => true,
                'message' => 'Bloco atualizado com sucesso'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao atualizar bloco: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Visualiza um bloco
     */
    public function visualizar($id)
    {
        $user = $this->authManager->getUser();
        
        $bloco = $this->blocoModel->findById($id);
        
        if (!$bloco) {
            $this->setFlashMessage('Bloco não encontrado', 'error');
            $this->redirect('/admin/provas');
            return;
        }
        
        $data = [
            'title' => 'Visualizar Bloco de Provas - EducaTudo',
            'user' => $user,
            'bloco' => $bloco,
            'current_page' => 'provas_blocos'
        ];
        
        $this->viewWithLayout('admin', 'admin/exams/blocks/view', $data);
    }
    
    /**
     * Exclui (desativa) um bloco. Exige confirmação com senha e registra quem excluiu.
     */
    public function excluir($id)
    {
        $user = $this->authManager->getUser();
        
        if (!in_array($user['tipo'] ?? '', ['admin', 'admin_escola'])) {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        $body = json_decode(file_get_contents('php://input') ?: '{}', true);
        $senha = $body['senha'] ?? '';
        
        if ($senha === '') {
            $this->json(['error' => 'Digite sua senha para confirmar a exclusão.'], 400);
            return;
        }
        
        try {
            $usuario = $this->db->fetch(
                "SELECT senha_hash FROM usuarios WHERE id = :id",
                ['id' => $user['id']]
            );
            if (!$usuario || !password_verify($senha, $usuario['senha_hash'] ?? '')) {
                $this->json(['error' => 'Senha incorreta. Tente novamente.'], 400);
                return;
            }
            
            $bloco = $this->blocoModel->findById($id);
            if (!$bloco) {
                $this->json(['error' => 'Bloco não encontrado'], 404);
                return;
            }
            
            $this->blocoModel->delete($id, (int) $user['id']);
            
            $this->json([
                'success' => true,
                'message' => 'Bloco desativado. Deixou de aparecer para alunos e professores; os dados são mantidos (LGPD).'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao excluir bloco: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Toggle liberação de bloco
     */
    public function toggleLiberado($id)
    {
        $user = $this->authManager->getUser();
        
        try {
            $bloco = $this->blocoModel->findById($id);
            
            if (!$bloco) {
                $this->json(['error' => 'Bloco não encontrado'], 404);
                return;
            }
            
            $statusAtual = $bloco['status'] ?? 'aguardando';
            if ($statusAtual === 'concluido') {
                $this->json(['error' => 'Bloco já concluído; não é possível alterar.'], 400);
                return;
            }
            if ($statusAtual !== 'aprovado' && $statusAtual !== 'liberado') {
                $this->json(['error' => 'Aprove o bloco em Gerenciar antes de liberar para os alunos.'], 400);
                return;
            }
            $novoStatus = $bloco['liberado'] ? 0 : 1;
            $novoStatusBloco = $novoStatus ? 'liberado' : 'aprovado';
            $this->db->query(
                "UPDATE provas_blocos SET status = :status, liberado = :liberado, ativo = :ativo WHERE id = :id",
                ['id' => $id, 'status' => $novoStatusBloco, 'liberado' => $novoStatus, 'ativo' => $novoStatus ? 1 : ($bloco['ativo'] ?? 1)]
            );
            if ($novoStatus == 1) {
                // Todas as provas do bloco ficam liberada e ativa para o aluno
                $vinculos = $this->db->fetchAll(
                    "SELECT prova_id FROM provas_blocos_vinculo WHERE bloco_id = :bloco_id",
                    ['bloco_id' => $id]
                );
                $provasIds = array_column($vinculos, 'prova_id');
                if (!empty($provasIds)) {
                    $placeholders = implode(',', array_fill(0, count($provasIds), '?'));
                    $this->db->query(
                        "UPDATE provas SET liberada = 1, ativo = 1 WHERE id IN ($placeholders) AND deleted_at IS NULL",
                        $provasIds
                    );
                }
            }
            
            $this->json([
                'success' => true,
                'message' => $novoStatus ? 'Bloco liberado' : 'Bloco bloqueado',
                'liberado' => $novoStatus
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao alterar status do bloco: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Marca o bloco/evento como concluído manualmente (coordenação).
     */
    public function marcarComoConcluido($id)
    {
        try {
            $resultado = $this->aplicarConclusaoManual([(int) $id]);
            if (($resultado['nao_encontrados'] ?? 0) > 0 && ($resultado['ok'] ?? 0) === 0) {
                $this->json(['error' => 'Bloco não encontrado'], 404);
                return;
            }
            $this->json([
                'success' => true,
                'message' => ($resultado['ok'] ?? 0) > 0
                    ? 'Bloco marcado como concluído'
                    : 'Bloco já estava concluído',
            ]);
        } catch (Exception $e) {
            error_log("Erro ao marcar bloco como concluído: " . $e->getMessage());
            $this->json(['error' => 'Não foi possível marcar o bloco como concluído.'], 400);
        }
    }

    /**
     * Marca vários blocos como concluídos (seleção em lote).
     */
    public function marcarComoConcluidoLote()
    {
        try {
            $body = json_decode(file_get_contents('php://input') ?: '{}', true);
            if (!is_array($body)) {
                $body = $_POST;
            }
            if (!$this->verificarCsrfDeBody($body)) {
                $this->json(['error' => 'Token inválido. Recarregue a página.'], 400);
                return;
            }
            $ids = $this->normalizarIdsLote($body['ids'] ?? $body['bloco_ids'] ?? []);
            if (empty($ids)) {
                $this->json(['error' => 'Selecione pelo menos um evento.'], 400);
                return;
            }
            if (count($ids) > 100) {
                $this->json(['error' => 'Selecione no máximo 100 eventos por vez.'], 400);
                return;
            }

            $resultado = $this->aplicarConclusaoManual($ids);
            if (($resultado['ok'] ?? 0) === 0 && ($resultado['nao_encontrados'] ?? 0) > 0 && ($resultado['ignorados'] ?? 0) === 0) {
                $this->json(['error' => 'Nenhum evento válido encontrado.'], 404);
                return;
            }
            $this->json([
                'success' => true,
                'message' => $resultado['ok'] . ' evento(s) marcado(s) como concluído(s).'
                    . ($resultado['ignorados'] > 0 ? ' ' . $resultado['ignorados'] . ' já estavam concluídos.' : '')
                    . ($resultado['nao_encontrados'] > 0 ? ' ' . $resultado['nao_encontrados'] . ' não encontrado(s).' : ''),
                'ok' => $resultado['ok'],
                'ignorados' => $resultado['ignorados'],
                'nao_encontrados' => $resultado['nao_encontrados'],
            ]);
        } catch (Exception $e) {
            error_log("Erro ao marcar blocos como concluídos em lote: " . $e->getMessage());
            $this->json(['error' => 'Não foi possível marcar os eventos como concluídos.'], 400);
        }
    }

    /**
     * Exclui (desativa) vários blocos. Exige senha uma vez para o lote.
     */
    public function excluirLote()
    {
        $user = $this->authManager->getUser();

        if (!in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }

        $body = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($body)) {
            $body = [];
        }
        if (!$this->verificarCsrfDeBody($body)) {
            $this->json(['error' => 'Token inválido. Recarregue a página.'], 400);
            return;
        }
        $senha = (string) ($body['senha'] ?? '');
        $ids = $this->normalizarIdsLote($body['ids'] ?? $body['bloco_ids'] ?? []);

        if ($senha === '') {
            $this->json(['error' => 'Digite sua senha para confirmar a exclusão.'], 400);
            return;
        }
        if (empty($ids)) {
            $this->json(['error' => 'Selecione pelo menos um evento.'], 400);
            return;
        }
        if (count($ids) > 100) {
            $this->json(['error' => 'Selecione no máximo 100 eventos por vez.'], 400);
            return;
        }

        try {
            $usuario = $this->db->fetch(
                "SELECT senha_hash FROM usuarios WHERE id = :id",
                ['id' => (int) $user['id']]
            );
            if (!$usuario || !password_verify($senha, $usuario['senha_hash'] ?? '')) {
                $this->json(['error' => 'Senha incorreta. Tente novamente.'], 400);
                return;
            }

            $ok = 0;
            $falhas = 0;
            foreach ($ids as $id) {
                $bloco = $this->blocoModel->findById($id);
                if (!$bloco) {
                    $falhas++;
                    continue;
                }
                $this->blocoModel->delete($id, (int) $user['id']);
                $ok++;
            }

            $this->json([
                'success' => true,
                'message' => $ok . ' evento(s) desativado(s).'
                    . ($falhas > 0 ? ' ' . $falhas . ' não encontrado(s).' : ''),
                'ok' => $ok,
                'falhas' => $falhas,
            ]);
        } catch (Exception $e) {
            error_log("Erro ao excluir blocos em lote: " . $e->getMessage());
            $this->json(['error' => 'Não foi possível excluir os eventos selecionados.'], 400);
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function verificarCsrfDeBody(array $body): bool
    {
        $token = (string) ($body['_token'] ?? $body['csrf_token'] ?? '');
        if ($token === '' && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        return $token !== '' && $this->verifyCsrfToken($token);
    }

    /**
     * @param array<int, mixed> $ids
     * @return array<int, int>
     */
    private function normalizarIdsLote($ids): array
    {
        if (!is_array($ids)) {
            return [];
        }
        $out = [];
        foreach ($ids as $id) {
            $idInt = (int) $id;
            if ($idInt > 0) {
                $out[$idInt] = $idInt;
            }
        }
        return array_values($out);
    }

    /**
     * @param array<int, int> $ids
     * @return array{ok:int,ignorados:int,nao_encontrados:int}
     */
    private function aplicarConclusaoManual(array $ids): array
    {
        $ok = 0;
        $ignorados = 0;
        $naoEncontrados = 0;
        $setExtra = $this->blocoModel->columnExistsOnBloco('conclusao_manual')
            ? ', conclusao_manual = 1'
            : '';

        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                $naoEncontrados++;
                continue;
            }
            $bloco = $this->blocoModel->findById($id);
            if (!$bloco) {
                $naoEncontrados++;
                continue;
            }
            $statusAtual = (string) ($bloco['status'] ?? 'aguardando');
            if ($statusAtual === 'concluido') {
                $ignorados++;
                continue;
            }
            $this->db->query(
                "UPDATE provas_blocos
                 SET status = 'concluido', liberado = 0{$setExtra}
                 WHERE id = :id AND deleted_at IS NULL",
                ['id' => $id]
            );
            $ok++;
        }

        return [
            'ok' => $ok,
            'ignorados' => $ignorados,
            'nao_encontrados' => $naoEncontrados,
        ];
    }

    /**
     * Define se o evento aparece no portal do aluno (Minhas provas / links do bloco).
     */
    public function definirVisivelPortalAluno($id)
    {
        try {
            $id = (int) $id;
            if ($id <= 0) {
                $this->json(['error' => 'Bloco inválido'], 400);
                return;
            }
            if (!$this->blocoModel->columnExistsOnBloco('visivel_no_portal_aluno')) {
                $this->json([
                    'error' => 'Coluna visivel_no_portal_aluno ausente em provas_blocos. Execute a migração database/migrations/2026_04_18_provas_blocos_visivel_portal_aluno.sql (após ano_letivo/bimestre).',
                ], 400);
                return;
            }
            $bloco = $this->blocoModel->findById($id);
            if (!$bloco) {
                $this->json(['error' => 'Bloco não encontrado'], 404);
                return;
            }
            $input = file_get_contents('php://input');
            $body = is_string($input) ? json_decode($input, true) : null;
            if (!is_array($body)) {
                $body = $_POST;
            }
            $vis = null;
            if (array_key_exists('visivel', $body)) {
                $vis = (int) (!empty($body['visivel']));
            } elseif (array_key_exists('visivel_no_portal_aluno', $body)) {
                $vis = (int) (!empty($body['visivel_no_portal_aluno']));
            }
            if ($vis === null) {
                $this->json(['error' => 'Envie visivel ou visivel_no_portal_aluno (0 ou 1).'], 400);
                return;
            }
            $vis = $vis ? 1 : 0;
            $this->db->query(
                'UPDATE provas_blocos SET visivel_no_portal_aluno = :v WHERE id = :id AND deleted_at IS NULL',
                ['v' => $vis, 'id' => $id]
            );
            $this->json([
                'success' => true,
                'message' => $vis ? 'Evento visível para os alunos no portal.' : 'Evento oculto do portal dos alunos.',
                'visivel_no_portal_aluno' => $vis,
            ]);
        } catch (Exception $e) {
            error_log('Erro definirVisivelPortalAluno: ' . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Duplica um bloco de provas com todas as provas e questões (cópia para edição).
     */
    public function duplicar($id)
    {
        $user = $this->authManager->getUser();
        
        $bloco = $this->blocoModel->findById($id);
        if (!$bloco) {
            $this->setFlashMessage('Bloco não encontrado', 'error');
            $this->redirect('/admin/provas');
            return;
        }
        
        $provasVinculo = $this->db->fetchAll(
            "SELECT pbp.prova_id, pbp.ordem
             FROM provas_blocos_vinculo pbp
             INNER JOIN provas p ON pbp.prova_id = p.id AND p.deleted_at IS NULL
             WHERE pbp.bloco_id = :bloco_id
             ORDER BY pbp.ordem ASC",
            ['bloco_id' => $id]
        );
        
        $this->db->beginTransaction();
        try {
            $novoTitulo = trim($bloco['titulo']) . ' (cópia)';
            $novoBlocoId = $this->db->insert(
                "INSERT INTO provas_blocos (
                    titulo, descricao, data_prova, hora_inicio, hora_fim,
                    criado_por, tipo_prova, configuracao_nota, liberar_gabarito,
                    turma_id, ativo, liberado, status, prazo_entrega_professor
                ) VALUES (
                    :titulo, :descricao, :data_prova, :hora_inicio, :hora_fim,
                    :criado_por, :tipo_prova, :configuracao_nota, :liberar_gabarito,
                    :turma_id, :ativo, :liberado, :status, :prazo_entrega_professor
                )",
                [
                    'titulo' => $novoTitulo,
                    'descricao' => $bloco['descricao'] ?? null,
                    'data_prova' => $bloco['data_prova'] ?? null,
                    'hora_inicio' => $bloco['hora_inicio'] ?? null,
                    'hora_fim' => $bloco['hora_fim'] ?? null,
                    'criado_por' => $bloco['criado_por'] ?? $user['id'],
                    'tipo_prova' => $bloco['tipo_prova'] ?? 'original',
                    'configuracao_nota' => $bloco['configuracao_nota'] ?? 'professor_por_questao',
                    'liberar_gabarito' => $bloco['liberar_gabarito'] ?? 'imediatamente',
                    'turma_id' => $bloco['turma_id'] ?? null,
                    'ativo' => 1,
                    'liberado' => 0,
                    'status' => $bloco['status'] ?? 'rascunho',
                    'prazo_entrega_professor' => $bloco['prazo_entrega_professor'] ?? null
                ]
            );
            
            $turmas = $this->db->fetchAll("SELECT turma_id FROM provas_blocos_turmas WHERE bloco_id = :bloco_id", ['bloco_id' => $id]);
            foreach ($turmas as $row) {
                $this->db->insert(
                    "INSERT INTO provas_blocos_turmas (bloco_id, turma_id) VALUES (:bloco_id, :turma_id)",
                    ['bloco_id' => $novoBlocoId, 'turma_id' => $row['turma_id']]
                );
            }
            
            $professores = $this->db->fetchAll(
                "SELECT professor_id, materia_id FROM provas_blocos_professores WHERE bloco_id = :bloco_id",
                ['bloco_id' => $id]
            );
            foreach ($professores as $row) {
                $this->db->insert(
                    "INSERT INTO provas_blocos_professores (bloco_id, professor_id, materia_id) VALUES (:bloco_id, :professor_id, :materia_id)",
                    [
                        'bloco_id' => $novoBlocoId,
                        'professor_id' => $row['professor_id'],
                        'materia_id' => $row['materia_id']
                    ]
                );
            }
            
            foreach ($provasVinculo as $idx => $vinculo) {
                $prova = $this->provaModel->findById($vinculo['prova_id']);
                if (!$prova) continue;
                
                $novoProvaId = $this->db->insert(
                    "INSERT INTO provas (
                        professor_id, materia_id, turma_id, titulo, descricao,
                        data_inicio, data_fim, tempo_limite, valor_total,
                        mostrar_resultado, permite_correcao, liberar_resultado,
                        ativo, liberada, status
                    ) VALUES (
                        :professor_id, :materia_id, :turma_id, :titulo, :descricao,
                        :data_inicio, :data_fim, :tempo_limite, :valor_total,
                        :mostrar_resultado, :permite_correcao, :liberar_resultado,
                        :ativo, :liberada, :status
                    )",
                    [
                        'professor_id' => $prova['professor_id'],
                        'materia_id' => $prova['materia_id'],
                        'turma_id' => $prova['turma_id'] ?? null,
                        'titulo' => trim($prova['titulo']) . ' (cópia)',
                        'descricao' => $prova['descricao'] ?? null,
                        'data_inicio' => $prova['data_inicio'],
                        'data_fim' => $prova['data_fim'],
                        'tempo_limite' => $prova['tempo_limite'] ?? null,
                        'valor_total' => $prova['valor_total'] ?? 100.00,
                        'mostrar_resultado' => $prova['mostrar_resultado'] ?? 1,
                        'permite_correcao' => $prova['permite_correcao'] ?? 0,
                        'liberar_resultado' => $prova['liberar_resultado'] ?? 'imediatamente',
                        'ativo' => 1,
                        'liberada' => 0,
                        'status' => 'rascunho'
                    ]
                );
                
                $questoes = $this->provaModel->getQuestoes($prova['id']);
                $mapQuestao = [];
                foreach ($questoes as $q) {
                    $novaQuestaoId = $this->db->insert(
                        "INSERT INTO provas_questoes (prova_id, enunciado, imagem_url, tipo, valor, nivel_dificuldade, ordem) 
                         VALUES (:prova_id, :enunciado, :imagem_url, :tipo, :valor, :nivel_dificuldade, :ordem)",
                        [
                            'prova_id' => $novoProvaId,
                            'enunciado' => $q['enunciado'],
                            'imagem_url' => $q['imagem_url'] ?? null,
                            'tipo' => $q['tipo'] ?? 'multipla_escolha',
                            'valor' => $q['valor'] ?? 1.00,
                            'nivel_dificuldade' => \Exam::normalizarNivelDificuldadeParaDb($q['nivel_dificuldade'] ?? null),
                            'ordem' => $q['ordem'] ?? 0
                        ]
                    );
                    $mapQuestao[$q['id']] = $novaQuestaoId;
                    
                    $alternativas = $this->provaModel->getAlternativas($q['id']);
                    foreach ($alternativas as $alt) {
                        $this->db->insert(
                            "INSERT INTO provas_alternativas (questao_id, texto, correta, ordem) VALUES (:questao_id, :texto, :correta, :ordem)",
                            [
                                'questao_id' => $novaQuestaoId,
                                'texto' => $alt['texto'],
                                'correta' => $alt['correta'] ?? 0,
                                'ordem' => $alt['ordem'] ?? 0
                            ]
                        );
                    }
                }
                
                $this->db->insert(
                    "INSERT INTO provas_blocos_vinculo (bloco_id, prova_id, ordem) VALUES (:bloco_id, :prova_id, :ordem)",
                    ['bloco_id' => $novoBlocoId, 'prova_id' => $novoProvaId, 'ordem' => $vinculo['ordem'] ?? $idx]
                );
            }
            
            $this->db->commit();
            $this->setFlashMessage('Bloco duplicado com sucesso. O novo bloco está como "Não liberado" e as provas em rascunho.', 'success');
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Erro ao duplicar bloco: " . $e->getMessage());
            $this->setFlashMessage('Erro ao duplicar bloco: ' . $e->getMessage(), 'error');
        }
        
        $this->redirect('/admin/provas');
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
                break;
        }
    }
}
}
