<?php
/**
 * EducaTudo - Controller de Administracao (extraido de AdminController)
 */

require_once __DIR__ . '/AdminBaseController.php';

if (!class_exists('OccurrenceAdminController')) {
class OccurrenceAdminController extends AdminBaseController
{
    public function alertasSensiveis()
    {
        $user = $this->auth->getUser();
        if (!$this->podeVerAlertasSensiveis($user)) {
            $this->redirect('/admin');
            return;
        }

        $status = $_GET['status'] ?? 'novo';
        $nivel = $_GET['nivel'] ?? '';
        $categoria = $_GET['categoria'] ?? '';

        $where = [];
        $params = [];
        if ($status !== '' && $status !== 'todos') {
            $where[] = "a.status = :status";
            $params['status'] = $status;
        }
        if ($nivel !== '') {
            $where[] = "a.nivel = :nivel";
            $params['nivel'] = $nivel;
        }
        if ($categoria !== '') {
            $where[] = "a.categoria = :categoria";
            $params['categoria'] = $categoria;
        }

        $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

        $alertas = $this->db->fetchAll(
            "SELECT a.*, al.nome as aluno_nome, t.nome as turma_nome
             FROM alertas_sensiveis a
             LEFT JOIN alunos al ON a.aluno_id = al.id
             LEFT JOIN turmas t ON a.turma_id = t.id
             {$whereSql}
             ORDER BY a.created_at DESC
             LIMIT 200",
            $params
        );

        $alertasNovos = $this->db->fetch(
            "SELECT COUNT(*) as count FROM alertas_sensiveis WHERE status = 'novo'"
        )['count'];

        $data = [
            'title' => 'Alertas Sensíveis - EducaTudo',
            'page_title' => 'Alertas Sensíveis',
            'user' => $user,
            'current_page' => 'monitoramento_alertas',
            'alertas' => $alertas,
            'filtros' => [
                'status' => $status,
                'nivel' => $nivel,
                'categoria' => $categoria
            ],
            'alertas_novos' => $alertasNovos,
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('admin', 'admin/monitoramento/alertas-sensiveis', $data);
    }

    public function atualizarAlertaSensivel()
    {
        $user = $this->auth->getUser();
        if (!$this->podeVerAlertasSensiveis($user)) {
            $this->redirect('/admin');
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect('/admin/monitoramento/alertas?erro=token');
            return;
        }

        $alertaId = (int)($_POST['alerta_id'] ?? 0);
        $acao = $_POST['acao'] ?? '';
        $observacoes = trim($_POST['observacoes'] ?? '');

        if ($alertaId <= 0) {
            $this->redirect('/admin/monitoramento/alertas?erro=alerta');
            return;
        }

        $statusMap = [
            'visualizado' => 'visualizado',
            'acompanhamento' => 'em_acompanhamento',
            'resolvido' => 'resolvido'
        ];

        if (isset($statusMap[$acao])) {
            $this->db->update(
                "UPDATE alertas_sensiveis SET status = :status, updated_at = NOW() WHERE id = :id",
                ['status' => $statusMap[$acao], 'id' => $alertaId]
            );
        }

        $this->db->insert(
            "INSERT INTO alertas_sensiveis_acoes (alerta_id, usuario_id, acao, observacoes, created_at)
             VALUES (:alerta_id, :usuario_id, :acao, :observacoes, NOW())",
            [
                'alerta_id' => $alertaId,
                'usuario_id' => $user['id'],
                'acao' => $acao,
                'observacoes' => $observacoes !== '' ? $observacoes : null
            ]
        );

        $this->redirect('/admin/monitoramento/alertas');
    }

    public function verConteudoAlertaSensivel()
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = $this->auth->getUser();
        if (!$this->podeVerAlertasSensiveis($user)) {
            $this->json(['error' => 'Sem permissão'], 403);
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        $alertaId = (int)($_POST['alerta_id'] ?? 0);
        $senha = trim($_POST['senha'] ?? '');
        if ($alertaId <= 0 || $senha === '') {
            $this->json(['error' => 'ID do alerta e senha são obrigatórios'], 400);
            return;
        }
        $usuario = $this->db->fetch(
            "SELECT id, senha_hash FROM usuarios WHERE id = :id AND tipo IN ('admin', 'admin_escola')",
            ['id' => $user['id']]
        );
        if (!$usuario || !password_verify($senha, $usuario['senha_hash'] ?? '')) {
            $this->json(['error' => 'Senha incorreta'], 401);
            return;
        }
        $alerta = $this->db->fetch(
            "SELECT id, mensagem_aluno, mensagem_chat_id FROM alertas_sensiveis WHERE id = :id",
            ['id' => $alertaId]
        );
        if (!$alerta) {
            $this->json(['error' => 'Alerta não encontrado'], 404);
            return;
        }
        $perguntaAluno = $alerta['mensagem_aluno'] ?? '';
        $respostaTudinha = '';
        if (!empty($alerta['mensagem_chat_id'])) {
            $resposta = $this->db->fetch(
                "SELECT m2.mensagem FROM tudinha_mensagens m1
                 INNER JOIN tudinha_mensagens m2 ON m2.conversa_id = m1.conversa_id AND m2.is_ia = 1 AND m2.created_at > m1.created_at
                 WHERE m1.id = :mensagem_chat_id
                 ORDER BY m2.created_at ASC LIMIT 1",
                ['mensagem_chat_id' => $alerta['mensagem_chat_id']]
            );
            $respostaTudinha = $resposta['mensagem'] ?? '';
        }
        $this->json([
            'success' => true,
            'pergunta_aluno' => $perguntaAluno,
            'resposta_tudinha' => $respostaTudinha
        ]);
    }

    public function ocorrenciasIndex()
    {
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->redirect('/admin');
            return;
        }

        $ocorrencias = $this->db->fetchAll(
            "SELECT o.*,
                    GROUP_CONCAT(DISTINCT COALESCE(a.nome, a2.nome) ORDER BY COALESCE(a.nome, a2.nome) SEPARATOR ', ') as alunos_nomes,
                    GROUP_CONCAT(DISTINCT t.nome ORDER BY t.nome SEPARATOR ', ') as turmas_nomes,
                    u.nome as criado_por_nome
             FROM alunos_ocorrencias o
             LEFT JOIN alunos_ocorrencias_itens oi ON oi.ocorrencia_id = o.id
             LEFT JOIN alunos a ON a.id = oi.aluno_id
             LEFT JOIN alunos a2 ON a2.id = o.aluno_id
             LEFT JOIN turmas t ON t.id = COALESCE(a.turma_id, a2.turma_id)
             LEFT JOIN usuarios u ON u.id = o.criado_por
             GROUP BY o.id
             ORDER BY o.data_ocorrencia DESC, o.created_at DESC
             LIMIT 200"
        );

        $data = [
            'title' => 'Ocorrências - EducaTudo',
            'page_title' => 'Ocorrências',
            'user' => $user,
            'current_page' => 'ocorrencias',
            'ocorrencias' => $ocorrencias,
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('admin', 'admin/ocorrencias/index', $data);
    }

    public function tentativasLoginIndex()
    {
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->redirect('/admin');
            return;
        }

        $dataInicio = trim((string) ($_GET['data_inicio'] ?? ''));
        $dataFim = trim((string) ($_GET['data_fim'] ?? ''));
        $tipoFiltro = trim((string) ($_GET['tipo'] ?? 'aluno'));

        $where = ["tl.success = 0"];
        $params = [];
        if ($dataInicio !== '') {
            $where[] = "DATE(tl.created_at) >= :data_inicio";
            $params['data_inicio'] = $dataInicio;
        }
        if ($dataFim !== '') {
            $where[] = "DATE(tl.created_at) <= :data_fim";
            $params['data_fim'] = $dataFim;
        }

        $hasTipo = false;
        try {
            $cols = $this->db->fetchAll("SHOW COLUMNS FROM tentativas_login LIKE 'tipo'");
            $hasTipo = !empty($cols);
        } catch (Exception $e) {
        }

        if ($hasTipo && $tipoFiltro !== '' && in_array($tipoFiltro, ['aluno', 'admin_escola', 'professor', 'pai'], true)) {
            $where[] = "tl.tipo = :tipo";
            $params['tipo'] = $tipoFiltro;
        }
        $whereSql = implode(' AND ', $where);

        try {
            if ($hasTipo) {
                $tentativas = $this->db->fetchAll(
                    "SELECT tl.id, tl.email as login_nickname, tl.ip_address, tl.created_at, tl.tipo, tl.motivo_falha,
                            a.nome as aluno_nome, a.nickname as aluno_nickname, t.nome as turma_nome
                     FROM tentativas_login tl
                     LEFT JOIN alunos a ON a.nickname = tl.email AND tl.tipo = 'aluno'
                     LEFT JOIN turmas t ON t.id = a.turma_id
                     WHERE {$whereSql}
                     ORDER BY tl.created_at DESC
                     LIMIT 500",
                    $params
                );
            } else {
                $tentativas = $this->db->fetchAll(
                    "SELECT tl.id, tl.email as login_nickname, tl.ip_address, tl.created_at,
                            a.nome as aluno_nome, a.nickname as aluno_nickname, t.nome as turma_nome
                     FROM tentativas_login tl
                     LEFT JOIN alunos a ON a.nickname = tl.email
                     LEFT JOIN turmas t ON t.id = a.turma_id
                     WHERE {$whereSql}
                     ORDER BY tl.created_at DESC
                     LIMIT 500",
                    $params
                );
                foreach ($tentativas as &$row) {
                    $row['tipo'] = 'aluno';
                    $row['motivo_falha'] = null;
                }
                unset($row);
            }
        } catch (Exception $e) {
            $tentativas = [];
        }

        $data = [
            'title' => 'Tentativas de login - EducaTudo',
            'page_title' => 'Tentativas de login',
            'user' => $user,
            'current_page' => 'tentativas_login',
            'tentativas' => $tentativas,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'tipo_filtro' => $tipoFiltro,
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('admin', 'admin/tentativas_login/index', $data);
    }

    public function salvarOcorrenciaGeral()
    {
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->json(['success' => false, 'error' => 'Acesso não autorizado'], 403);
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 400);
        }

        $alunos = $_POST['alunos'] ?? [];
        if (!is_array($alunos) || count($alunos) === 0) {
            $this->json(['success' => false, 'error' => 'Selecione pelo menos um aluno'], 400);
        }

        $alunos = array_values(array_unique(array_map('intval', $alunos)));
        $alunoPrincipal = $alunos[0];

        $dataOcorrencia = trim($_POST['data_ocorrencia'] ?? '');
        $titulo = trim($_POST['titulo'] ?? '');
        $detalhe = trim($_POST['detalhe'] ?? '');
        $nivel = trim($_POST['nivel_gravidade'] ?? '');
        $atitude = trim($_POST['atitude_coordenacao'] ?? '');
        $retorno = trim($_POST['retorno_em'] ?? '');
        $enviarPais = isset($_POST['enviar_pais']) ? 1 : 0;

        if ($dataOcorrencia === '' || $titulo === '' || $detalhe === '' || $nivel === '') {
            $this->json(['success' => false, 'error' => 'Preencha os campos obrigatórios'], 400);
        }

        $nivelPermitido = ['leve', 'moderado', 'grave'];
        if (!in_array($nivel, $nivelPermitido, true)) {
            $this->json(['success' => false, 'error' => 'Nível de gravidade inválido'], 400);
        }

        $atitudesPermitidas = ['advertencia', 'suspensao', 'orientacao', ''];
        if (!in_array($atitude, $atitudesPermitidas, true)) {
            $this->json(['success' => false, 'error' => 'Atitude inválida'], 400);
        }

        $dataOcorrenciaSql = date('Y-m-d H:i:s', strtotime($dataOcorrencia));
        $retornoSql = $retorno !== '' ? date('Y-m-d', strtotime($retorno)) : null;

        $ocorrenciaId = $this->db->insert(
            "INSERT INTO alunos_ocorrencias (aluno_id, data_ocorrencia, titulo, detalhe, nivel_gravidade, atitude_coordenacao, retorno_em, enviar_pais, criado_por, created_at)
             VALUES (:aluno_id, :data_ocorrencia, :titulo, :detalhe, :nivel, :atitude, :retorno, :enviar_pais, :criado_por, NOW())",
            [
                'aluno_id' => $alunoPrincipal,
                'data_ocorrencia' => $dataOcorrenciaSql,
                'titulo' => $titulo,
                'detalhe' => $detalhe,
                'nivel' => $nivel,
                'atitude' => $atitude !== '' ? $atitude : null,
                'retorno' => $retornoSql,
                'enviar_pais' => $enviarPais,
                'criado_por' => $user['id']
            ]
        );

        foreach ($alunos as $alunoId) {
            $this->db->insert(
                "INSERT INTO alunos_ocorrencias_itens (ocorrencia_id, aluno_id, created_at)
                 VALUES (:ocorrencia_id, :aluno_id, NOW())",
                [
                    'ocorrencia_id' => $ocorrenciaId,
                    'aluno_id' => (int)$alunoId
                ]
            );
        }

        $this->json(['success' => true]);
    }

    public function transcreverOcorrenciaAudioGeral()
    {
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->json(['success' => false, 'error' => 'Acesso não autorizado'], 403);
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 400);
        }

        if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'error' => 'Áudio inválido'], 400);
        }

        $audio = $_FILES['audio'];
        $allowedTypes = [
            'audio/mpeg', 'audio/mp3', 'audio/mpga',
            'audio/wav', 'audio/webm', 'audio/ogg', 'audio/oga',
            'audio/flac', 'audio/m4a', 'audio/mp4'
        ];
        $allowedExtensions = ['mp3', 'wav', 'webm', 'ogg', 'oga', 'flac', 'm4a', 'mp4', 'mpga'];

        $extension = strtolower(pathinfo($audio['name'], PATHINFO_EXTENSION));
        if (!in_array($audio['type'], $allowedTypes, true) && !in_array($extension, $allowedExtensions, true)) {
            $this->json(['success' => false, 'error' => 'Formato de áudio não suportado'], 400);
        }

        $tempDir = __DIR__ . '/../../storage/ocorrencias/audio/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $filename = 'ocorrencia_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = $tempDir . $filename;
        if (!move_uploaded_file($audio['tmp_name'], $filepath)) {
            $this->json(['success' => false, 'error' => 'Erro ao salvar áudio'], 400);
        }

        require_once __DIR__ . '/../../Services/ElevenLabsService.php';
        $elevenLabsService = new \App\Services\ElevenLabsService();
        $texto = $elevenLabsService->vozParaTexto($filepath);

        $this->json(['success' => true, 'texto' => $texto]);
    }

    public function autoPreencherOcorrenciaGeral()
    {
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->json(['success' => false, 'error' => 'Acesso não autorizado'], 403);
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 400);
        }

        $texto = trim($_POST['texto'] ?? '');
        if ($texto === '') {
            $this->json(['success' => false, 'error' => 'Texto inválido'], 400);
        }

        require_once __DIR__ . '/../../Services/OpenAIService.php';
        $openAIService = new \App\Services\OpenAIService();

        $systemPrompt = "Extraia campos de uma ocorrência escolar.\n"
            . "Retorne SOMENTE JSON válido com: titulo, detalhe, nivel_gravidade (leve|moderado|grave), atitude_coordenacao (advertencia|suspensao|orientacao|vazio), retorno_em (AAAA-MM-DD ou vazio), alunos (lista de objetos {nome, turma}) e turmas (lista de turmas mencionadas).\n"
            . "Use turma como texto ex: '2º A'. Não invente datas se não houver. Seja objetivo e seguro.";

        try {
            $resposta = $openAIService->chatCompletion(
                [['role' => 'user', 'content' => $texto]],
                $systemPrompt,
                'gpt-4o-mini',
                0.2,
                400
            );
        } catch (\Exception $e) {
            error_log('OccurrenceAdminController: falha ao interpretar ocorrência — ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Não foi possível interpretar o texto com a IA. Tente novamente.'], 400);
            return;
        }

        $raw = trim($resposta['resposta'] ?? '');
        $json = $raw;
        if (strpos($raw, '{') !== false) {
            $start = strpos($raw, '{');
            $end = strrpos($raw, '}');
            if ($end !== false && $end > $start) {
                $json = substr($raw, $start, $end - $start + 1);
            }
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            $this->json(['success' => false, 'error' => 'Falha ao interpretar a resposta da IA'], 400);
        }

        $alunosSug = [];
        $turmasSug = [];
        $naoLocalizados = [];

        $alunosRaw = array_slice((array)($data['alunos'] ?? []), 0, 6);
        foreach ($alunosRaw as $item) {
            $nome = '';
            $turmaAi = '';
            if (is_array($item)) {
                $nome = trim((string)($item['nome'] ?? ''));
                $turmaAi = trim((string)($item['turma'] ?? ''));
            } else {
                $nome = trim((string)$item);
            }
            if ($nome === '') {
                continue;
            }
            $result = $this->db->fetchAll(
                "SELECT a.id, a.nome, t.nome as turma_nome
                 FROM alunos a
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 WHERE a.nome LIKE :nome
                 ORDER BY a.nome ASC
                 LIMIT 5",
                ['nome' => '%' . $nome . '%']
            );
            if (empty($result)) {
                $naoLocalizados[] = $nome;
            }
            foreach ($result as $row) {
                $alunosSug[$row['id']] = [
                    'id' => (int)$row['id'],
                    'nome' => $row['nome'],
                    'turma_nome' => $row['turma_nome'],
                    'turma_ia' => $turmaAi
                ];
            }
        }

        $turmasNomes = array_slice(array_filter((array)($data['turmas'] ?? [])), 0, 6);
        foreach ($turmasNomes as $nome) {
            $nome = trim((string)$nome);
            if ($nome === '') continue;
            $result = $this->db->fetchAll(
                "SELECT id, nome FROM turmas WHERE nome LIKE :nome ORDER BY nome ASC LIMIT 5",
                ['nome' => '%' . $nome . '%']
            );
            foreach ($result as $row) {
                $turmasSug[$row['id']] = [
                    'id' => (int)$row['id'],
                    'nome' => $row['nome']
                ];
            }
        }

        $this->json([
            'success' => true,
            'data' => $data,
            'sugestoes_alunos' => array_values($alunosSug),
            'sugestoes_turmas' => array_values($turmasSug),
            'alunos_nao_localizados' => array_values(array_unique($naoLocalizados))
        ]);
    }

    public function buscarAlunosOcorrencias()
    {
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->json(['success' => false, 'error' => 'Acesso não autorizado'], 403);
        }

        $term = trim($_GET['term'] ?? '');
        $turmaId = (int)($_GET['turma_id'] ?? 0);
        $params = [];
        $where = [];
        if ($term !== '') {
            $where[] = "a.nome LIKE :term";
            $params['term'] = '%' . $term . '%';
        }
        if ($turmaId > 0) {
            $where[] = "a.turma_id = :turma_id";
            $params['turma_id'] = $turmaId;
        }
        if (!$where) {
            $this->json(['success' => true, 'alunos' => []]);
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $alunos = $this->db->fetchAll(
            "SELECT a.id, a.nome, t.nome as turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             {$sqlWhere}
             ORDER BY a.nome ASC
             LIMIT 20",
            $params
        );

        $this->json(['success' => true, 'alunos' => $alunos]);
    }

    public function salvarOcorrencia($id)
    {
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->json(['success' => false, 'error' => 'Acesso não autorizado'], 403);
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 400);
        }

        $dataOcorrencia = trim($_POST['data_ocorrencia'] ?? '');
        $titulo = trim($_POST['titulo'] ?? '');
        $detalhe = trim($_POST['detalhe'] ?? '');
        $nivel = trim($_POST['nivel_gravidade'] ?? '');
        $atitude = trim($_POST['atitude_coordenacao'] ?? '');
        $retorno = trim($_POST['retorno_em'] ?? '');
        $enviarPais = isset($_POST['enviar_pais']) ? 1 : 0;

        if ($dataOcorrencia === '' || $titulo === '' || $detalhe === '' || $nivel === '') {
            $this->json(['success' => false, 'error' => 'Preencha os campos obrigatórios'], 400);
        }

        $nivelPermitido = ['leve', 'moderado', 'grave'];
        if (!in_array($nivel, $nivelPermitido, true)) {
            $this->json(['success' => false, 'error' => 'Nível de gravidade inválido'], 400);
        }

        $atitudesPermitidas = ['advertencia', 'suspensao', 'orientacao', ''];
        if (!in_array($atitude, $atitudesPermitidas, true)) {
            $this->json(['success' => false, 'error' => 'Atitude inválida'], 400);
        }

        $dataOcorrenciaSql = date('Y-m-d H:i:s', strtotime($dataOcorrencia));
        $retornoSql = $retorno !== '' ? date('Y-m-d', strtotime($retorno)) : null;

        $this->db->insert(
            "INSERT INTO alunos_ocorrencias (aluno_id, data_ocorrencia, titulo, detalhe, nivel_gravidade, atitude_coordenacao, retorno_em, enviar_pais, criado_por, created_at)
             VALUES (:aluno_id, :data_ocorrencia, :titulo, :detalhe, :nivel, :atitude, :retorno, :enviar_pais, :criado_por, NOW())",
            [
                'aluno_id' => $id,
                'data_ocorrencia' => $dataOcorrenciaSql,
                'titulo' => $titulo,
                'detalhe' => $detalhe,
                'nivel' => $nivel,
                'atitude' => $atitude !== '' ? $atitude : null,
                'retorno' => $retornoSql,
                'enviar_pais' => $enviarPais,
                'criado_por' => $user['id']
            ]
        );

        $this->json(['success' => true]);
    }

    public function transcreverOcorrenciaAudio($id)
    {
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->json(['success' => false, 'error' => 'Acesso não autorizado'], 403);
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 400);
        }

        if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'error' => 'Áudio inválido'], 400);
        }

        $audio = $_FILES['audio'];
        $allowedTypes = [
            'audio/mpeg', 'audio/mp3', 'audio/mpga',
            'audio/wav', 'audio/webm', 'audio/ogg', 'audio/oga',
            'audio/flac', 'audio/m4a', 'audio/mp4'
        ];
        $allowedExtensions = ['mp3', 'wav', 'webm', 'ogg', 'oga', 'flac', 'm4a', 'mp4', 'mpga'];

        $extension = strtolower(pathinfo($audio['name'], PATHINFO_EXTENSION));
        if (!in_array($audio['type'], $allowedTypes, true) && !in_array($extension, $allowedExtensions, true)) {
            $this->json(['success' => false, 'error' => 'Formato de áudio não suportado'], 400);
        }

        $tempDir = __DIR__ . '/../../storage/ocorrencias/audio/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $filename = 'ocorrencia_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = $tempDir . $filename;
        if (!move_uploaded_file($audio['tmp_name'], $filepath)) {
            $this->json(['success' => false, 'error' => 'Erro ao salvar áudio'], 400);
        }

        require_once __DIR__ . '/../../Services/ElevenLabsService.php';
        $elevenLabsService = new \App\Services\ElevenLabsService();
        $texto = $elevenLabsService->vozParaTexto($filepath);

        $this->json(['success' => true, 'texto' => $texto]);
    }

    public function autoPreencherOcorrencia($id)
    {
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->json(['success' => false, 'error' => 'Acesso não autorizado'], 403);
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 400);
        }

        $texto = trim($_POST['texto'] ?? '');
        if ($texto === '') {
            $this->json(['success' => false, 'error' => 'Texto inválido'], 400);
        }

        require_once __DIR__ . '/../../Services/OpenAIService.php';
        $openAIService = new \App\Services\OpenAIService();

        $systemPrompt = "Você é um assistente que extrai campos estruturados de relatos de ocorrências escolares.\n"
            . "Retorne SOMENTE JSON válido com os campos: titulo, detalhe, nivel_gravidade (leve|moderado|grave), atitude_coordenacao (advertencia|suspensao|orientacao|vazio), retorno_em (AAAA-MM-DD ou vazio).\n"
            . "Não invente datas se não houver no texto. Seja objetivo e seguro.";

        try {
            $resposta = $openAIService->chatCompletion(
                [['role' => 'user', 'content' => $texto]],
                $systemPrompt,
                'gpt-4o-mini',
                0.2,
                300
            );
        } catch (\Exception $e) {
            error_log('OccurrenceAdminController: falha ao interpretar ocorrência — ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Não foi possível interpretar o texto com a IA. Tente novamente.'], 400);
            return;
        }

        $raw = trim($resposta['resposta'] ?? '');
        $json = $raw;
        if (strpos($raw, '{') !== false) {
            $start = strpos($raw, '{');
            $end = strrpos($raw, '}');
            if ($end !== false && $end > $start) {
                $json = substr($raw, $start, $end - $start + 1);
            }
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            $this->json(['success' => false, 'error' => 'Falha ao interpretar a resposta da IA'], 400);
        }

        $this->json(['success' => true, 'data' => $data]);
    }
}
}
