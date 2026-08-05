<?php
/**
 * EducaTudo - Controller de Administracao (extraido de AdminController)
 */

require_once __DIR__ . '/../../Models/User/Monitor.php';
require_once __DIR__ . '/AdminBaseController.php';

if (!class_exists('MonitorAdminController')) {
class MonitorAdminController extends AdminBaseController
{
    public function monitores()
    {
        $monitorModel = new Monitor();
        $monitores = $monitorModel->getAll();
        $turmasMap = [];
        foreach ($this->db->fetchAll("SELECT id, nome FROM turmas ORDER BY nome") as $t) {
            $turmasMap[(string) $t['id']] = $t['nome'];
        }
        $this->viewWithLayout('admin', 'admin/monitors/index', [
            'title' => 'Monitores de Sala - EducaTudo',
            'monitors' => $monitores,
            'turmas_map' => $turmasMap,
            'turmas_disponiveis' => $this->db->fetchAll("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome"),
            'user' => $this->auth->getUser(),
            'current_page' => 'monitors',
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function salvarMonitor()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        try {
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $turmas = $_POST['turmas'] ?? [];
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            if ($nome === '' || $email === '') {
                throw new Exception('Nome e email são obrigatórios');
            }
            if (empty($turmas)) {
                throw new Exception('Selecione ao menos uma turma');
            }
            $model = new Monitor();
            if ($model->emailExists($email)) {
                throw new Exception('Email já cadastrado');
            }
            $model->create([
                'nome' => $nome,
                'email' => $email,
                'senha' => '123456',
                'turmas' => $turmas,
                'ativo' => $ativo,
            ]);
            $this->json(['success' => true, 'message' => 'Monitor cadastrado com sucesso']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function dadosMonitor($id)
    {
        $model = new Monitor();
        $monitor = $model->findById($id);
        if (!$monitor) {
            $this->json(['error' => 'Monitor não encontrado'], 404);
            return;
        }
        $this->json([
            'success' => true,
            'monitor' => [
                'id' => (int) $monitor['id'],
                'nome' => $monitor['nome'],
                'email' => $monitor['email'],
                'ativo' => (bool) $monitor['ativo'],
                'turmas_array' => array_map('intval', json_decode($monitor['turmas'] ?? '[]', true) ?: []),
            ],
        ]);
    }

    public function atualizarMonitor($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        try {
            $model = new Monitor();
            $monitor = $model->findById($id);
            if (!$monitor) {
                throw new Exception('Monitor não encontrado');
            }
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $turmas = $_POST['turmas'] ?? [];
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $senha = trim($_POST['senha'] ?? '');
            if ($nome === '' || $email === '') {
                throw new Exception('Nome e email são obrigatórios');
            }
            if (empty($turmas)) {
                throw new Exception('Selecione ao menos uma turma');
            }
            if ($model->emailExists($email, $id)) {
                throw new Exception('Email já cadastrado');
            }
            $data = [
                'nome' => $nome,
                'email' => $email,
                'turmas' => $turmas,
                'ativo' => $ativo,
            ];
            if ($senha !== '') {
                $data['senha'] = $senha;
            }
            $model->update($id, $data);
            $this->json(['success' => true, 'message' => 'Monitor atualizado']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function excluirMonitor($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        try {
            (new Monitor())->delete($id);
            $this->json(['success' => true]);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function alunosOnline()
    {
        $user = $this->auth->getUser();
        
        if (!$user || $user['tipo'] !== 'admin') {
            $this->redirect('/admin');
            return;
        }
        
        $authManager = new AuthManager();
        $alunos_online = $authManager->getAlunosOnline();
        
        $data = [
            'title' => 'Alunos Online - EducaTudo',
            'user' => $user,
            'current_page' => 'alunos-online',
            'alunos_online' => $alunos_online
        ];
        
        $this->viewWithLayout('admin', 'admin/alunos-online', $data);
    }

    public function apiAlunosOnline()
    {
        header('Content-Type: application/json');
        
        $user = $this->auth->getUser();
        if (!$user || $user['tipo'] !== 'admin') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        $authManager = new AuthManager();
        $alunos_online = $authManager->getAlunosOnline();
        $formatted = $this->formatAlunosOnline($alunos_online);
        
        $this->json(['alunos' => $formatted, 'total' => count($formatted)]);
    }

    public function apiAlunosOnlineStream()
    {
        // Headers SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', 0);
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $user = $this->auth->getUser();
        if (!$user || $user['tipo'] !== 'admin') {
            echo "event: error\n";
            echo "data: " . json_encode(['error' => 'Não autorizado']) . "\n\n";
            @ob_flush();
            flush();
            exit;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $authManager = new AuthManager();
        $lastHash = null;
        $start = time();
        $lastPing = time();
        $checkIntervalSeconds = 10;

        while (time() - $start < 55) {
            if (connection_aborted()) {
                break;
            }

            $alunos_online = $authManager->getAlunosOnline();
            $formatted = $this->formatAlunosOnline($alunos_online);
            $payload = ['alunos' => $formatted, 'total' => count($formatted)];
            $hash = md5(json_encode($payload));

            if ($lastHash === null || $hash !== $lastHash) {
                echo "event: online\n";
                echo "data: " . json_encode($payload) . "\n\n";
                @ob_flush();
                flush();
                $lastHash = $hash;
            }

            if (time() - $lastPing >= 15) {
                echo "event: ping\n";
                echo "data: {}\n\n";
                @ob_flush();
                flush();
                $lastPing = time();
            }

            usleep($checkIntervalSeconds * 1000000);
        }

        exit;
    }

    private function formatAlunosOnline($alunos_online)
    {
        return array_map(function($aluno) {
            $tempo_online = $aluno['tempo_online_segundos'] ?? 0;
            $horas = floor($tempo_online / 3600);
            $minutos = floor(($tempo_online % 3600) / 60);
            $segundos = $tempo_online % 60;
            
            return [
                'id' => $aluno['aluno_id'],
                'nome' => $aluno['nome'],
                'ra' => $aluno['ra'],
                'turma_nome' => $aluno['turma_nome'] ?? 'Sem turma',
                'login_at' => $aluno['login_at'],
                'tempo_online' => [
                    'total_segundos' => $tempo_online,
                    'formatado' => sprintf('%02d:%02d:%02d', $horas, $minutos, $segundos)
                ]
            ];
        }, $alunos_online);
    }

    private function getSessoesAcesso($filtros)
    {
        $where_clauses = [];
        $params = [];
        
        if ($filtros['tipo'] === 'turma' && !empty($filtros['turma_id'])) {
            $where_clauses[] = "a.turma_id = :turma_id";
            $params['turma_id'] = $filtros['turma_id'];
        } elseif ($filtros['tipo'] === 'usuario' && !empty($filtros['aluno_id'])) {
            $where_clauses[] = "s.aluno_id = :aluno_id";
            $params['aluno_id'] = $filtros['aluno_id'];
        }
        
        if (!empty($filtros['data_inicio'])) {
            $where_clauses[] = "DATE(s.login_at) >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where_clauses[] = "DATE(s.login_at) <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'];
        }
        
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
        
        return $this->db->fetchAll(
            "SELECT s.*, a.nome as aluno_nome, a.ra, t.nome as turma_nome,
                    CASE 
                        WHEN s.logout_at IS NOT NULL THEN 
                            CONCAT(
                                FLOOR(s.tempo_uso_segundos / 3600), 'h ',
                                FLOOR((s.tempo_uso_segundos % 3600) / 60), 'm ',
                                s.tempo_uso_segundos % 60, 's'
                            )
                        ELSE 
                            CONCAT(
                                FLOOR(TIMESTAMPDIFF(SECOND, s.login_at, NOW()) / 3600), 'h ',
                                FLOOR((TIMESTAMPDIFF(SECOND, s.login_at, NOW()) % 3600) / 60), 'm ',
                                TIMESTAMPDIFF(SECOND, s.login_at, NOW()) % 60, 's'
                            )
                    END as tempo_formatado,
                    TIMESTAMPDIFF(SECOND, s.login_at, COALESCE(s.logout_at, NOW())) as tempo_total_segundos
             FROM alunos_sessoes_acesso s
             INNER JOIN alunos a ON s.aluno_id = a.id
             LEFT JOIN turmas t ON a.turma_id = t.id
             {$where_sql}
             ORDER BY s.login_at DESC
             LIMIT 200",
            $params
        );
    }
}
}
