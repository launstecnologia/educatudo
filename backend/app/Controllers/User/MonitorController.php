<?php
/**
 * Painel do Monitor de sala
 */

require_once __DIR__ . '/../../Models/User/Monitor.php';

if (!class_exists('MonitorController')) {
    class MonitorController extends BaseController
    {
        private $authManager;
        private $db;
        private $monitorModel;

        public function __construct()
        {
            parent::__construct();
            $this->authManager = new AuthManager();
            $this->db = Database::getInstance();
            $this->monitorModel = new Monitor();

            if (!$this->authManager->isLoggedIn()) {
                $this->redirect('/monitor');
                return;
            }

            $user = $this->authManager->getUser();
            if ($user && $user['tipo'] !== 'monitor') {
                $this->redirectToCorrectDashboard($user['tipo']);
            }
        }

        public function alterarSenhaObrigatoria()
        {
            $user = $this->authManager->getUser();
            $monitor = $this->getMonitorRecord();
            if (!$monitor || !password_verify('123456', $monitor['senha_hash'])) {
                $this->redirect('/monitor/dashboard');
                return;
            }
            $this->viewWithLayout('monitor', 'monitor/alterar-senha', [
                'title' => 'Alterar Senha - Monitor',
                'user' => $user,
                'csrf_token' => $this->generateCsrfToken(),
                'current_page' => 'dashboard',
            ]);
        }

        public function processarAlteracaoSenha()
        {
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
                return;
            }
            try {
                $user = $this->authManager->getUser();
                $nova = $_POST['nova_senha'] ?? '';
                $confirmar = $_POST['confirmar_senha'] ?? '';
                if ($nova === '' || $nova !== $confirmar) {
                    throw new Exception('Senhas não conferem');
                }
                if ($nova === '123456') {
                    throw new Exception('Escolha uma senha diferente da padrão');
                }
                $auth = new Auth();
                $check = $auth->validateStrongPassword($nova);
                if ($check !== true) {
                    throw new Exception($check);
                }
                $this->db->update(
                    "UPDATE monitores SET senha_hash = :hash WHERE id = :id",
                    ['hash' => password_hash($nova, PASSWORD_DEFAULT), 'id' => $user['id']]
                );
                $this->json(['success' => true, 'redirect' => URL . '/monitor/dashboard']);
            } catch (Exception $e) {
                $this->json(['error' => $e->getMessage()], 400);
            }
        }

        public function dashboard()
        {
            $user = $this->authManager->getUser();
            $monitor = $this->getMonitorRecord();
            $turmasIds = $this->monitorModel->getTurmasIds($monitor ?: []);
            $turmas = $this->getTurmasInfo($turmasIds);
            $blocoId = $this->getBlocoIdFiltro();
            $eventos = $this->getEventosParaMonitor($turmasIds);

            if ($blocoId && !$this->blocoAcessivelAoMonitor($blocoId, $turmasIds)) {
                $blocoId = null;
            }

            $data = [
                'title' => 'Monitor de Sala - EducaTudo',
                'user' => $user,
                'monitor' => $monitor,
                'turmas' => $turmas,
                'turmas_ids' => $turmasIds,
                'eventos' => $eventos,
                'bloco_id' => $blocoId,
                'current_page' => 'dashboard',
                'csrf_token' => $this->generateCsrfToken(),
            ];

            $this->viewWithLayout('monitor', 'monitor/dashboard', $data);
        }

        public function apiAlunosOnline()
        {
            $monitor = $this->requireMonitor();
            if (!$monitor) {
                return;
            }

            $this->json($this->buildAlunosOnlinePayload($monitor));
        }

        public function apiAlunosOnlineStream()
        {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', 0);
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $user = $this->authManager->getUser();
            if (!$user || $user['tipo'] !== 'monitor') {
                echo "event: error\n";
                echo "data: " . json_encode(['error' => 'Não autorizado']) . "\n\n";
                flush();
                exit;
            }

            $monitor = $this->getMonitorRecord();

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $lastHash = null;
            $start = time();
            $lastPing = time();

            while (time() - $start < 55) {
                if (connection_aborted()) {
                    break;
                }

                $payload = $monitor ? $this->buildAlunosOnlinePayload($monitor) : [
                    'alunos' => [],
                    'total' => 0,
                    'total_escola' => 0,
                    'turmas_incompletas' => false,
                    'alertas' => ['canceladas' => 0, 'em_prova' => 0],
                ];
                $hash = md5(json_encode($payload));

                if ($lastHash === null || $hash !== $lastHash) {
                    echo "event: online\n";
                    echo "data: " . json_encode($payload) . "\n\n";
                    flush();
                    $lastHash = $hash;
                }

                if (time() - $lastPing >= 15) {
                    echo "event: ping\n";
                    echo "data: {}\n\n";
                    flush();
                    $lastPing = time();
                }

                usleep(10 * 1000000);
            }

            exit;
        }

        public function verAluno($id)
        {
            $monitor = $this->requireMonitor();
            if (!$monitor) {
                return;
            }

            $aluno = $this->fetchAlunoAutorizado((int) $id, $monitor);
            if (!$aluno) {
                $this->setFlashMessage('Aluno não encontrado ou fora do seu escopo.', 'error');
                $this->redirect('/monitor/dashboard');
                return;
            }

            $this->logAcao($monitor['id'], (int) $aluno['id'], 'ver_aluno');

            $sessaoAtiva = $this->db->fetch(
                "SELECT s.* FROM alunos_sessoes_acesso s
                 WHERE s.aluno_id = :aluno_id AND s.status = 'ativo' AND s.logout_at IS NULL
                 ORDER BY COALESCE(s.ultima_atividade_at, s.login_at) DESC LIMIT 1",
                ['aluno_id' => $aluno['id']]
            );

            $provasAndamento = $this->db->fetchAll(
                "SELECT pr.*, p.titulo as prova_titulo, m.nome as materia_nome
                 FROM provas_realizacoes pr
                 INNER JOIN provas p ON p.id = pr.prova_id
                 LEFT JOIN materias m ON m.id = p.materia_id
                 WHERE pr.aluno_id = :aluno_id
                 AND pr.status IN ('iniciado', 'cancelada')
                 ORDER BY pr.iniciado_em DESC
                 LIMIT 10",
                ['aluno_id' => $aluno['id']]
            );

            $jornadasLista = $this->db->fetchAll(
                "SELECT DISTINCT j.id, j.titulo
                 FROM jornadas j
                 INNER JOIN jornadas_progresso_alunos jpa ON jpa.jornada_id = j.id AND jpa.aluno_id = :aluno_id
                 ORDER BY j.id DESC
                 LIMIT 20",
                ['aluno_id' => $aluno['id']]
            );
            $jornadasComStatus = [];
            foreach ($jornadasLista as $jRow) {
                $jid = (int) $jRow['id'];
                $eng = $this->getEngajamentoJornada($jid, (int) $aluno['id']);
                $jornadasComStatus[] = array_merge($jRow, $eng);
            }

            $provasCanceladas = array_filter($provasAndamento, function ($p) {
                return ($p['status'] ?? '') === 'cancelada';
            });
            $blocoId = $this->getBlocoIdFiltro();

            $data = [
                'title' => 'Aluno: ' . $aluno['nome'] . ' - Monitor',
                'user' => $this->authManager->getUser(),
                'aluno' => $aluno,
                'sessao_ativa' => $sessaoAtiva,
                'provas_andamento' => $provasAndamento,
                'provas_canceladas' => array_values($provasCanceladas),
                'jornadas' => $jornadasComStatus,
                'bloco_id' => $blocoId,
                'current_page' => 'aluno',
                'csrf_token' => $this->generateCsrfToken(),
            ];

            $this->viewWithLayout('monitor', 'monitor/aluno', $data);
        }

        public function verJornada($alunoId, $jornadaId)
        {
            $monitor = $this->requireMonitor();
            if (!$monitor) {
                return;
            }

            $aluno = $this->fetchAlunoAutorizado((int) $alunoId, $monitor);
            if (!$aluno) {
                $this->setFlashMessage('Aluno não encontrado.', 'error');
                $this->redirect('/monitor/dashboard');
                return;
            }

            $jornadaId = (int) $jornadaId;
            $jornada = $this->db->fetch(
                "SELECT j.*, m.nome as materia_nome, t.nome as turma_nome
                 FROM jornadas j
                 LEFT JOIN jornadas_materias m ON j.materia_id = m.id
                 LEFT JOIN turmas t ON j.turma_id = t.id
                 WHERE j.id = :jornada_id",
                ['jornada_id' => $jornadaId]
            );

            if (!$jornada) {
                $this->setFlashMessage('Jornada não encontrada.', 'error');
                $this->redirect('/monitor/aluno/' . $aluno['id']);
                return;
            }

            $this->logAcao($monitor['id'], (int) $aluno['id'], 'ver_jornada', ['jornada_id' => $jornadaId]);

            $exercicios = $this->fetchExerciciosJornadaAluno($jornadaId, (int) $aluno['id']);
            $engajamento = $this->getEngajamentoJornada($jornadaId, (int) $aluno['id']);
            $estatisticas = $this->calcularEstatisticasExercicios($exercicios);

            $blocoId = $this->getBlocoIdFiltro();
            $data = [
                'title' => 'Jornada - ' . $aluno['nome'],
                'user' => $this->authManager->getUser(),
                'jornada' => $jornada,
                'aluno' => $aluno,
                'exercicios' => $exercicios,
                'engajamento' => $engajamento,
                'estatisticas' => $estatisticas,
                'base_url_jornadas' => URL . '/monitor/aluno/' . $aluno['id'] . ($blocoId ? ('?bloco_id=' . $blocoId) : ''),
                'current_page' => 'aluno',
                'csrf_token' => $this->generateCsrfToken(),
            ];

            $this->viewWithLayout('monitor', 'monitor/jornada-detalhe', $data);
        }

        public function verProva($alunoId, $provaId)
        {
            $monitor = $this->requireMonitor();
            if (!$monitor) {
                return;
            }

            $aluno = $this->fetchAlunoAutorizado((int) $alunoId, $monitor);
            if (!$aluno) {
                $this->setFlashMessage('Aluno não encontrado.', 'error');
                $this->redirect('/monitor/dashboard');
                return;
            }

            $provaId = (int) $provaId;
            $prova = $this->db->fetch("SELECT * FROM provas WHERE id = :id AND deleted_at IS NULL", ['id' => $provaId]);
            if (!$prova) {
                $this->setFlashMessage('Prova não encontrada.', 'error');
                $this->redirect('/monitor/aluno/' . $aluno['id']);
                return;
            }

            $realizacao = $this->db->fetch(
                "SELECT * FROM provas_realizacoes WHERE prova_id = :prova_id AND aluno_id = :aluno_id",
                ['prova_id' => $provaId, 'aluno_id' => $aluno['id']]
            );

            $questoes = $this->db->fetchAll(
                "SELECT q.* FROM provas_questoes q WHERE q.prova_id = :prova_id ORDER BY q.ordem ASC",
                ['prova_id' => $provaId]
            );

            $respostas = $this->db->fetchAll(
                "SELECT r.*, a.texto as alternativa_texto
                 FROM provas_respostas r
                 LEFT JOIN provas_alternativas a ON a.id = r.alternativa_id
                 WHERE r.prova_id = :prova_id AND r.aluno_id = :aluno_id",
                ['prova_id' => $provaId, 'aluno_id' => $aluno['id']]
            );

            $respostasMap = [];
            foreach ($respostas as $r) {
                $respostasMap[$r['questao_id']] = $r;
            }
            foreach ($questoes as &$questao) {
                $questao['resposta'] = $respostasMap[$questao['id']] ?? null;
            }
            unset($questao);

            $this->logAcao($monitor['id'], (int) $aluno['id'], 'ver_prova', ['prova_id' => $provaId]);

            $data = [
                'title' => 'Prova - ' . $aluno['nome'],
                'user' => $this->authManager->getUser(),
                'prova' => $prova,
                'aluno' => $aluno,
                'realizacao' => $realizacao,
                'questoes' => $questoes,
                'current_page' => 'aluno',
                'csrf_token' => $this->generateCsrfToken(),
            ];

            $this->viewWithLayout('monitor', 'monitor/prova-detalhe', $data);
        }

        public function updateStudentPassword($id)
        {
            ob_clean();
            error_reporting(0);
            ini_set('display_errors', 0);

            try {
                if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                    $this->json(['error' => 'Token inválido'], 400);
                    return;
                }

                $monitor = $this->requireMonitor(true);
                if (!$monitor) {
                    return;
                }

                $aluno = $this->fetchAlunoAutorizado((int) $id, $monitor);
                if (!$aluno) {
                    throw new Exception('Aluno não encontrado ou fora do escopo');
                }

                $senhaMonitor = (string) ($_POST['senha_monitor'] ?? '');
                if ($senhaMonitor === '') {
                    throw new Exception('Informe sua senha para confirmar o reset');
                }
                if (!password_verify($senhaMonitor, $monitor['senha_hash'] ?? '')) {
                    throw new Exception('Senha incorreta');
                }

                $senhaPadrao = '123456';
                $result = $this->db->update(
                    "UPDATE alunos SET senha_hash = :senha WHERE id = :aluno_id",
                    [
                        'senha' => password_hash($senhaPadrao, PASSWORD_DEFAULT),
                        'aluno_id' => $aluno['id'],
                    ]
                );

                if ($result === 0) {
                    throw new Exception('Erro ao atualizar senha');
                }

                $this->logAcao($monitor['id'], (int) $aluno['id'], 'resetar_senha');

                $this->json([
                    'success' => true,
                    'message' => 'Senha alterada para o padrão (123456).',
                    'senha_padrao' => $senhaPadrao,
                    'aluno_nome' => $aluno['nome'],
                ]);
            } catch (Exception $e) {
                ob_clean();
                $this->json(['error' => $e->getMessage()], 400);
            }
        }

        private function requireMonitor($json = false)
        {
            $user = $this->authManager->getUser();
            if (!$user || $user['tipo'] !== 'monitor') {
                if ($json) {
                    $this->json(['error' => 'Não autorizado'], 403);
                } else {
                    $this->redirect('/monitor');
                }
                return null;
            }

            $monitor = $this->getMonitorRecord();
            if (!$monitor || empty($monitor['ativo'])) {
                if ($json) {
                    $this->json(['error' => 'Monitor inativo'], 403);
                } else {
                    $this->setFlashMessage('Conta de monitor inativa.', 'error');
                    $this->redirect('/logout');
                }
                return null;
            }

            return $monitor;
        }

        private function getMonitorRecord()
        {
            $user = $this->authManager->getUser();
            if (!$user || $user['tipo'] !== 'monitor') {
                return null;
            }
            return $this->monitorModel->findById($user['id']);
        }

        private function fetchAlunoAutorizado(int $alunoId, array $monitor)
        {
            $turmasIds = $this->monitorModel->getTurmasIds($monitor);
            if (empty($turmasIds)) {
                return null;
            }

            $placeholders = implode(',', array_fill(0, count($turmasIds), '?'));
            return $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome, t.serie
                 FROM alunos a
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 WHERE a.id = ? AND a.turma_id IN ($placeholders)",
                array_merge([$alunoId], $turmasIds)
            );
        }

        private function fetchExerciciosJornadaAluno(int $jornadaId, int $alunoId): array
        {
            $exercicios = $this->db->fetchAll(
                "SELECT me.id, me.modulo_id, me.tipo, me.titulo, me.enunciado, me.questoes_json,
                        me.resposta_correta, me.gabarito, me.pontuacao, me.imagem_url,
                        m.titulo AS modulo_titulo, m.ordem AS modulo_ordem,
                        jpa.resposta AS resposta_aluno, jpa.pontuacao AS pontuacao_aluno,
                        jpa.status AS status_aluno, jpa.data_conclusao
                 FROM jornadas_modulos m
                 JOIN jornadas_modulos_exercicios me ON me.modulo_id = m.id
                 LEFT JOIN jornadas_progresso_alunos jpa ON jpa.exercicio_modulo_id = me.id
                     AND jpa.aluno_id = :aluno_id AND jpa.jornada_id = m.jornada_id
                     AND jpa.atividade_tipo = 'exercicio_modulo'
                 WHERE m.jornada_id = :jornada_id
                   AND (m.tipo_modulo = 'exercicios' OR m.tipo_modulo = 'exercicio' OR m.tipo_modulo IS NULL)
                 ORDER BY m.ordem ASC, me.ordem ASC",
                ['jornada_id' => $jornadaId, 'aluno_id' => $alunoId]
            );

            if (empty($exercicios)) {
                $exercicios = $this->db->fetchAll(
                    "SELECT je.id, je.jornada_id AS modulo_id, je.tipo, je.titulo,
                            je.descricao AS enunciado, je.questoes_json, NULL AS resposta_correta,
                            NULL AS gabarito, 1.0 AS pontuacao, je.imagem_url,
                            'Exercícios da Jornada' AS modulo_titulo, 1 AS modulo_ordem,
                            jpa.resposta AS resposta_aluno, jpa.pontuacao AS pontuacao_aluno,
                            jpa.status AS status_aluno, jpa.data_conclusao
                     FROM jornadas_exercicios je
                     LEFT JOIN jornadas_progresso_alunos jpa ON jpa.exercicio_id = je.id
                         AND jpa.aluno_id = :aluno_id AND jpa.jornada_id = je.jornada_id
                         AND jpa.atividade_tipo = 'exercicio'
                     WHERE je.jornada_id = :jornada_id
                       AND je.status IN ('aprovado', 'publicado')
                     ORDER BY je.created_at ASC",
                    ['jornada_id' => $jornadaId, 'aluno_id' => $alunoId]
                );
            }

            foreach ($exercicios as &$ex) {
                $ex['imagem_url'] = $this->normalizeImagemExercicioUrl($ex['imagem_url'] ?? null);
                $ex['enunciado_html'] = $this->fixEnunciadoImagens($ex['enunciado'] ?? '');
            }
            unset($ex);

            return $exercicios;
        }

        private function getEngajamentoJornada(int $jornadaId, int $alunoId): array
        {
            $stats = $this->db->fetch(
                "SELECT COUNT(DISTINCT jpa.exercicio_modulo_id) AS respondidos
                 FROM jornadas_progresso_alunos jpa
                 WHERE jpa.aluno_id = :aluno_id AND jpa.jornada_id = :jornada_id
                   AND jpa.atividade_tipo = 'exercicio_modulo'
                   AND jpa.resposta IS NOT NULL AND TRIM(jpa.resposta) <> ''",
                ['aluno_id' => $alunoId, 'jornada_id' => $jornadaId]
            );
            $respondidos = (int) ($stats['respondidos'] ?? 0);

            if ($respondidos === 0) {
                $legacy = $this->db->fetch(
                    "SELECT COUNT(DISTINCT jpa.exercicio_id) AS respondidos
                     FROM jornadas_progresso_alunos jpa
                     WHERE jpa.aluno_id = :aluno_id AND jpa.jornada_id = :jornada_id
                       AND jpa.atividade_tipo = 'exercicio'
                       AND jpa.resposta IS NOT NULL AND TRIM(jpa.resposta) <> ''",
                    ['aluno_id' => $alunoId, 'jornada_id' => $jornadaId]
                );
                $respondidos = (int) ($legacy['respondidos'] ?? 0);
            }

            $visualizou = $this->db->fetch(
                "SELECT status FROM jornadas_progresso_alunos
                 WHERE aluno_id = :aluno_id AND jornada_id = :jornada_id
                   AND atividade_tipo IS NULL
                   AND status IN ('visualizado', 'iniciado', 'em_andamento', 'concluido')
                 ORDER BY id DESC LIMIT 1",
                ['aluno_id' => $alunoId, 'jornada_id' => $jornadaId]
            );

            if ($respondidos > 0) {
                $codigo = 'fez';
                $label = 'Realizou exercícios';
                $cor = 'green';
            } elseif ($visualizou) {
                $codigo = 'viu';
                $label = 'Abriu a jornada, mas ainda não respondeu';
                $cor = 'amber';
            } else {
                $codigo = 'nao_viu';
                $label = 'Ainda não abriu ou não há registro de acesso';
                $cor = 'gray';
            }

            return [
                'codigo' => $codigo,
                'label' => $label,
                'cor' => $cor,
                'respondidos' => $respondidos,
                'visualizou' => (bool) $visualizou,
            ];
        }

        private function calcularEstatisticasExercicios(array $exercicios): array
        {
            $total = count($exercicios);
            $acertos = 0;
            $erros = 0;
            $respondidos = 0;
            $notaTotal = 0.0;

            foreach ($exercicios as $ex) {
                if (!empty($ex['resposta_aluno'])) {
                    $respondidos++;
                    $pts = (float) ($ex['pontuacao_aluno'] ?? 0);
                    $notaTotal += $pts;
                    if ($pts > 0) {
                        $acertos++;
                    } else {
                        $erros++;
                    }
                }
            }

            return [
                'total' => $total,
                'respondidos' => $respondidos,
                'acertos' => $acertos,
                'erros' => $erros,
                'nota_total' => $notaTotal,
                'percentual' => $total > 0 ? round(($acertos / $total) * 100, 1) : 0,
            ];
        }

        private function normalizeImagemExercicioUrl(?string $url): ?string
        {
            if ($url === null || trim($url) === '') {
                return null;
            }

            $url = trim($url);
            $url = preg_replace('#/public/uploads/#', '/uploads/', $url);
            $url = preg_replace('#^public/uploads/#', '/uploads/', $url);

            if (preg_match('#^https?://#i', $url)) {
                return preg_replace('#/public/uploads/#', '/uploads/', $url);
            }

            $base = defined('URL') ? rtrim((string) URL, '/') : '';

            if (strpos($url, 'media/serve') !== false) {
                if (strpos($url, '/') !== 0) {
                    $url = '/' . ltrim($url, '/');
                }
                return $base . $url;
            }

            if (preg_match('#^/uploads/jornadas/exercicios/#', $url)) {
                return $base . $url;
            }

            if (strpos($url, '/uploads/') === 0) {
                return $base . $url;
            }

            if (strpos($url, 'uploads/') === 0) {
                return $base . '/' . $url;
            }

            if (strpos($url, '/') !== 0) {
                return $base . '/uploads/jornadas/exercicios/' . ltrim($url, '/');
            }

            return $base . $url;
        }

        private function fixEnunciadoImagens(?string $html): string
        {
            if ($html === null || trim($html) === '') {
                return '';
            }

            $base = defined('URL') ? rtrim((string) URL, '/') : '';
            $html = preg_replace('#/public/uploads/#', '/uploads/', $html);

            $html = preg_replace_callback(
                '#\bsrc=(["\'])([^"\']+)\1#i',
                function (array $m) use ($base) {
                    $src = trim($m[2]);
                    $src = preg_replace('#/public/uploads/#', '/uploads/', $src);
                    $src = preg_replace('#^public/uploads/#', '/uploads/', $src);
                    if ($src === '' || preg_match('#^https?://#i', $src) || strpos($src, 'data:') === 0) {
                        return $m[0];
                    }
                    if (strpos($src, '//') === 0) {
                        return $m[0];
                    }
                    if (strpos($src, 'media/serve') !== false) {
                        if (strpos($src, '/') !== 0) {
                            $src = '/' . ltrim($src, '/');
                        }
                        return 'src=' . $m[1] . $base . $src . $m[1];
                    }
                    if (strpos($src, '/uploads/') === 0 || strpos($src, 'uploads/') === 0) {
                        if (strpos($src, '/') !== 0) {
                            $src = '/' . $src;
                        }
                        return 'src=' . $m[1] . $base . $src . $m[1];
                    }
                    if (strpos($src, '/') !== 0) {
                        $src = '/uploads/jornadas/exercicios/' . ltrim($src, '/');
                    }
                    return 'src=' . $m[1] . $base . $src . $m[1];
                },
                $html
            );

            return $html;
        }

        private function getTurmasInfo(array $turmasIds): array
        {
            if (empty($turmasIds)) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($turmasIds), '?'));
            return $this->db->fetchAll(
                "SELECT id, nome FROM turmas WHERE id IN ($placeholders) ORDER BY nome ASC",
                $turmasIds
            );
        }

        private function buildAlunosOnlinePayload(array $monitor): array
        {
            $turmasIds = $this->monitorModel->getTurmasIds($monitor);
            if (empty($turmasIds)) {
                return [
                    'alunos' => [],
                    'total' => 0,
                    'total_escola' => 0,
                    'turmas_incompletas' => false,
                    'alertas' => ['canceladas' => 0, 'em_prova' => 0],
                    'bloco_id' => null,
                ];
            }

            $blocoId = $this->getBlocoIdFiltro();
            if ($blocoId && !$this->blocoAcessivelAoMonitor($blocoId, $turmasIds)) {
                $blocoId = null;
            }

            $totalEscola = count($this->authManager->getAlunosOnline(null));

            $alunos = $this->authManager->getAlunosOnline($turmasIds);
            if ($blocoId) {
                $alunos = $this->filtrarAlunosPorBloco($alunos, $blocoId, $turmasIds);
            }

            $statusMap = $this->enriquecerStatusProvas($alunos, $blocoId);
            $formatted = $this->formatAlunosOnline($alunos, $statusMap, $blocoId);
            $turmasIncompletas = $this->monitorTemTurmasIncompletas($turmasIds);

            $canceladas = 0;
            $emProva = 0;
            foreach ($formatted as $a) {
                if (($a['alerta'] ?? '') === 'prova_cancelada') {
                    $canceladas++;
                }
                if (in_array($a['alerta'] ?? '', ['prova_andamento', 'prova_cancelada'], true)) {
                    $emProva++;
                }
            }

            return [
                'alunos' => $formatted,
                'total' => count($formatted),
                'total_escola' => $totalEscola,
                'turmas_incompletas' => $turmasIncompletas,
                'alertas' => [
                    'canceladas' => $canceladas,
                    'em_prova' => $emProva,
                ],
                'bloco_id' => $blocoId,
            ];
        }

        private function monitorTemTurmasIncompletas(array $turmasMonitor): bool
        {
            $ativas = $this->db->fetchAll("SELECT id FROM turmas WHERE ativo = 1");
            $todasIds = array_values(array_unique(array_filter(array_map('intval', array_column($ativas, 'id')))));
            sort($todasIds);
            $monitorIds = array_values(array_unique(array_filter(array_map('intval', $turmasMonitor))));
            sort($monitorIds);
            if (empty($todasIds)) {
                return false;
            }
            return $monitorIds !== $todasIds;
        }

        private function getBlocoIdFiltro(): ?int
        {
            $id = (int) ($_GET['bloco_id'] ?? 0);
            return $id > 0 ? $id : null;
        }

        private function getEventosParaMonitor(array $turmasIds): array
        {
            if (empty($turmasIds)) {
                return [];
            }

            $ph = implode(',', array_fill(0, count($turmasIds), '?'));
            $turmaFilter = "(
                pb.turma_id IN ($ph)
                OR EXISTS (SELECT 1 FROM provas_blocos_turmas pbt WHERE pbt.bloco_id = pb.id AND pbt.turma_id IN ($ph))
                OR EXISTS (
                    SELECT 1 FROM provas_blocos_professores pbp
                    INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp.id
                    WHERE pbp.bloco_id = pb.id AND pbpt.turma_id IN ($ph)
                )
            )";
            $params = array_merge($turmasIds, $turmasIds, $turmasIds);

            try {
                return $this->db->fetchAll(
                    "SELECT DISTINCT pb.id, pb.titulo, pb.data_prova, pb.hora_inicio, pb.hora_fim, pb.status,
                            CASE
                                WHEN pb.status = 'liberado'
                                 AND CONCAT(pb.data_prova, ' ', COALESCE(pb.hora_inicio, '00:00:00')) <= NOW()
                                 AND CONCAT(pb.data_prova, ' ', COALESCE(pb.hora_fim, '23:59:59')) >= NOW()
                                THEN 1 ELSE 0
                            END AS em_andamento
                     FROM provas_blocos pb
                     WHERE pb.deleted_at IS NULL
                     AND pb.status IN ('liberado', 'aprovado', 'concluido')
                     AND DATE(pb.data_prova) >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
                     AND DATE(pb.data_prova) <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
                     AND {$turmaFilter}
                     ORDER BY em_andamento DESC, pb.data_prova ASC, pb.hora_inicio ASC",
                    $params
                );
            } catch (Exception $e) {
                return [];
            }
        }

        private function blocoAcessivelAoMonitor(int $blocoId, array $turmasIds): bool
        {
            $turmasBloco = $this->getTurmasIdsDoBloco($blocoId);
            return !empty(array_intersect($turmasBloco, $turmasIds));
        }

        private function getTurmasIdsDoBloco(int $blocoId): array
        {
            $ids = [];
            $bloco = $this->db->fetch("SELECT turma_id FROM provas_blocos WHERE id = :id AND deleted_at IS NULL", ['id' => $blocoId]);
            if ($bloco && !empty($bloco['turma_id'])) {
                $ids[] = (int) $bloco['turma_id'];
            }
            $extras = $this->db->fetchAll(
                "SELECT turma_id FROM provas_blocos_turmas WHERE bloco_id = :id",
                ['id' => $blocoId]
            );
            foreach ($extras as $row) {
                $ids[] = (int) $row['turma_id'];
            }
            $profTurmas = $this->db->fetchAll(
                "SELECT pbpt.turma_id
                 FROM provas_blocos_professores pbp
                 INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp.id
                 WHERE pbp.bloco_id = :id",
                ['id' => $blocoId]
            );
            foreach ($profTurmas as $row) {
                $ids[] = (int) $row['turma_id'];
            }
            return array_values(array_unique(array_filter($ids)));
        }

        private function filtrarAlunosPorBloco(array $alunos, int $blocoId, array $monitorTurmasIds): array
        {
            $turmasBloco = array_intersect($this->getTurmasIdsDoBloco($blocoId), $monitorTurmasIds);
            if (empty($turmasBloco)) {
                return [];
            }

            return array_values(array_filter($alunos, function ($aluno) use ($turmasBloco) {
                return in_array((int) ($aluno['turma_id'] ?? 0), $turmasBloco, true);
            }));
        }

        private function enriquecerStatusProvas(array $alunos, ?int $blocoId = null): array
        {
            if (empty($alunos)) {
                return [];
            }

            $alunoIds = array_values(array_unique(array_map('intval', array_column($alunos, 'aluno_id'))));
            if (empty($alunoIds)) {
                return [];
            }
            $ph = implode(',', array_fill(0, count($alunoIds), '?'));

            $sql = "SELECT pr.aluno_id, pr.status, pr.prova_id, p.titulo AS prova_titulo
                    FROM provas_realizacoes pr
                    INNER JOIN provas p ON p.id = pr.prova_id";
            $params = [];

            if ($blocoId) {
                $sql .= " INNER JOIN provas_blocos_vinculo pbp ON pbp.prova_id = pr.prova_id AND pbp.bloco_id = ?";
                $params[] = $blocoId;
            }

            $sql .= " WHERE pr.aluno_id IN ($ph) AND pr.status IN ('iniciado', 'cancelada')";
            $params = array_merge($params, $alunoIds);

            $rows = $this->db->fetchAll($sql, $params);
            $map = [];

            foreach ($rows as $row) {
                $aid = (int) $row['aluno_id'];
                $prio = ($row['status'] === 'cancelada') ? 2 : 1;
                if (!isset($map[$aid]) || $prio > ($map[$aid]['_prio'] ?? 0)) {
                    $map[$aid] = [
                        'status' => $row['status'],
                        'prova_id' => (int) $row['prova_id'],
                        'prova_titulo' => $row['prova_titulo'],
                        '_prio' => $prio,
                    ];
                }
            }

            foreach ($alunos as $aluno) {
                $aid = (int) $aluno['aluno_id'];
                if (isset($map[$aid])) {
                    continue;
                }
                if (($aluno['contexto_tipo'] ?? '') === 'prova' && !empty($aluno['contexto_id'])) {
                    $real = $this->db->fetch(
                        "SELECT pr.status, pr.prova_id, p.titulo AS prova_titulo
                         FROM provas_realizacoes pr
                         INNER JOIN provas p ON p.id = pr.prova_id
                         WHERE pr.aluno_id = :aluno_id AND pr.prova_id = :prova_id
                         AND pr.status IN ('iniciado', 'cancelada')
                         LIMIT 1",
                        ['aluno_id' => $aid, 'prova_id' => (int) $aluno['contexto_id']]
                    );
                    if ($real) {
                        $map[$aid] = [
                            'status' => $real['status'],
                            'prova_id' => (int) $real['prova_id'],
                            'prova_titulo' => $real['prova_titulo'],
                            '_prio' => $real['status'] === 'cancelada' ? 2 : 1,
                        ];
                    }
                }
            }

            foreach ($map as &$item) {
                unset($item['_prio']);
            }

            return $map;
        }

        private function formatAlunosOnline(array $alunos, array $statusMap = [], ?int $blocoId = null): array
        {
            return array_map(function ($aluno) use ($statusMap, $blocoId) {
                $tempo = (int) ($aluno['tempo_online_segundos'] ?? 0);
                $aid = (int) $aluno['aluno_id'];
                $st = $statusMap[$aid] ?? null;

                $alerta = null;
                $provaStatus = null;
                $provaTitulo = null;
                if ($st) {
                    $provaStatus = $st['status'];
                    $provaTitulo = $st['prova_titulo'] ?? null;
                    $alerta = ($st['status'] === 'cancelada') ? 'prova_cancelada' : 'prova_andamento';
                } elseif (($aluno['contexto_tipo'] ?? '') === 'prova') {
                    $alerta = 'prova_andamento';
                }

                $qs = $blocoId ? ('?bloco_id=' . $blocoId) : '';

                return [
                    'id' => $aid,
                    'nome' => $aluno['nome'],
                    'ra' => $aluno['ra'],
                    'turma_nome' => $aluno['turma_nome'] ?? 'Sem turma',
                    'login_at' => $aluno['login_at'],
                    'ultima_atividade_at' => $aluno['ultima_atividade_at'] ?? null,
                    'contexto_tipo' => $aluno['contexto_tipo'] ?? null,
                    'contexto_id' => $aluno['contexto_id'] ?? null,
                    'contexto_label' => $aluno['contexto_label'] ?? null,
                    'alerta' => $alerta,
                    'prova_status' => $provaStatus,
                    'prova_titulo' => $provaTitulo,
                    'tempo_online' => [
                        'total_segundos' => $tempo,
                        'formatado' => sprintf(
                            '%02d:%02d:%02d',
                            floor($tempo / 3600),
                            floor(($tempo % 3600) / 60),
                            $tempo % 60
                        ),
                    ],
                    'url' => URL . '/monitor/aluno/' . $aid . $qs,
                ];
            }, $alunos);
        }

        private function logAcao(int $monitorId, ?int $alunoId, string $acao, array $detalhes = [])
        {
            try {
                $this->db->insert(
                    "INSERT INTO monitor_acoes_log (monitor_id, aluno_id, acao, detalhes, ip_address)
                     VALUES (:monitor_id, :aluno_id, :acao, :detalhes, :ip)",
                    [
                        'monitor_id' => $monitorId,
                        'aluno_id' => $alunoId,
                        'acao' => $acao,
                        'detalhes' => !empty($detalhes) ? json_encode($detalhes) : null,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    ]
                );
            } catch (Exception $e) {
                // tabela pode não existir antes da migration
            }
        }

        private function redirectToCorrectDashboard($tipo)
        {
            switch ($tipo) {
                case 'monitor':
                    $this->redirect('/monitor/dashboard');
                    break;
                case 'aluno':
                    $this->redirect('/dashboard');
                    break;
                case 'professor':
                    $this->redirect('/professor/dashboard');
                    break;
                case 'pai':
                    $this->redirect('/pais/dashboard');
                    break;
                case 'admin':
                case 'admin_escola':
                    $this->redirect('/admin/dashboard');
                    break;
                default:
                    $this->redirect('/');
            }
        }
    }
}
