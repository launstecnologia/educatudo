<?php
/**
 * EducaTudo - Controller de Administracao (extraido de AdminController)
 */

require_once __DIR__ . '/AdminBaseController.php';

if (!class_exists('DevAdminController')) {
class DevAdminController extends AdminBaseController
{
    /**
     * Menu de navegação das 6 categorias de Configurações Avançadas (Módulos,
     * Aparência, IA, Integrações, Operação, Conteúdo) — cada uma com página
     * própria (ver devModulos/devAparencia/devIa/devIntegracoes/devOperacao/
     * devConteudo). Antes esta rota renderizava as 9 abas inteiras numa
     * página só (2.300+ linhas, tudo carregado de uma vez); dividir em
     * páginas por categoria resolveu o tempo de carregamento.
     */
    public function dev()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
            return;
        }

        $flash = $this->getFlashMessage();
        $data = [
            'title' => 'Configurações Avançadas - EducaTudo',
            'user' => $user,
            'current_page' => 'dev',
            'csrf_token' => $this->generateCsrfToken(),
            'flash_message' => $flash['message'] ?? '',
            'flash_type' => $flash['type'] ?? '',
        ];

        $this->viewWithLayout('admin', 'admin/dev/index', $data);
    }

    /**
     * Integrações Externas: Chaves de API, E-mail (SMTP), WhatsApp (Evolution
     * API) e Webhooks. Extraído de dev() / admin/dev/index.php (que continha
     * as 9 telas inteiras numa página só, carregadas de uma vez) para reduzir
     * o tamanho da página e separar por categoria.
     */
    public function devIntegracoes()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
            return;
        }

        $emailConfigs = $this->db->fetchAll(
            "SELECT config_key, config_value FROM config_layout WHERE config_key LIKE 'email_%'"
        );

        $email_config = [];
        foreach ($emailConfigs as $config) {
            $key = str_replace('email_', '', $config['config_key']);
            $email_config[$key] = $config['config_value'];
        }

        if (empty($email_config['smtp_port'])) {
            $email_config['smtp_port'] = '587';
        }
        if (empty($email_config['smtp_secure'])) {
            $email_config['smtp_secure'] = 'tls';
        }
        if (empty($email_config['from_name'])) {
            $email_config['from_name'] = 'EducaTudo';
        }

        $flash = $this->getFlashMessage();
        $data = [
            'title' => 'Integrações Externas - EducaTudo',
            'user' => $user,
            'current_page' => 'dev',
            'csrf_token' => $this->generateCsrfToken(),
            'email_config' => $email_config,
            'flash_message' => $flash['message'] ?? '',
            'flash_type' => $flash['type'] ?? '',
        ];

        $this->viewWithLayout('admin', 'admin/dev/integracoes', $data);
    }

    /**
     * Módulos e Limites: liga/desliga funcionalidades por perfil (aluno,
     * professor, geral) + limites diários de uso + valor por usuário/
     * configurações financeiras. Extraído de dev() / admin/dev/index.php
     * (ver devIntegracoes() para o contexto da divisão por categoria).
     */
    public function devModulos()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
            return;
        }

        $flash = $this->getFlashMessage();
        $data = [
            'title' => 'Módulos e Limites - EducaTudo',
            'user' => $user,
            'current_page' => 'dev',
            'csrf_token' => $this->generateCsrfToken(),
            'flash_message' => $flash['message'] ?? '',
            'flash_type' => $flash['type'] ?? '',
        ];

        $this->viewWithLayout('admin', 'admin/dev/modulos', $data);
    }

    /**
     * Aparência e Menu: ordem/nomes do menu do aluno, links customizados do
     * submenu, e links para as páginas já separadas de Layout do Sistema/PWA/
     * Layout Mobile. Extraído de dev() / admin/dev/index.php.
     */
    public function devAparencia()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
            return;
        }

        $flash = $this->getFlashMessage();
        $data = [
            'title' => 'Aparência e Menu - EducaTudo',
            'user' => $user,
            'current_page' => 'dev',
            'csrf_token' => $this->generateCsrfToken(),
            'flash_message' => $flash['message'] ?? '',
            'flash_type' => $flash['type'] ?? '',
        ];

        $this->viewWithLayout('admin', 'admin/dev/aparencia', $data);
    }

    /**
     * Inteligência Artificial: prompts usados pela IA (redação, chat, prova,
     * exercícios) + link para Custos LLM (já é página própria). Extraído de
     * dev() / admin/dev/index.php.
     */
    public function devIa()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
            return;
        }

        $flash = $this->getFlashMessage();
        $data = [
            'title' => 'Inteligência Artificial - EducaTudo',
            'user' => $user,
            'current_page' => 'dev',
            'csrf_token' => $this->generateCsrfToken(),
            'flash_message' => $flash['message'] ?? '',
            'flash_type' => $flash['type'] ?? '',
        ];

        $this->viewWithLayout('admin', 'admin/dev/ia', $data);
    }

    /**
     * Conteúdo: gestão de tutoriais em vídeo exibidos no menu do professor.
     * Extraído de dev() / admin/dev/index.php.
     */
    public function devConteudo()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
            return;
        }

        $data = [
            'title' => 'Conteúdo - EducaTudo',
            'user' => $user,
            'current_page' => 'dev',
            'csrf_token' => $this->generateCsrfToken(),
        ];

        $this->viewWithLayout('admin', 'admin/dev/conteudo', $data);
    }

    /**
     * Operação e Infraestrutura: monitoramento (métricas IA/BD/sistema) +
     * links para Logs, Logins, Dev Settings key-value, Migrations e SSH
     * (que já são páginas próprias). Extraído de dev() / admin/dev/index.php.
     */
    public function devOperacao()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
            return;
        }

        $data = [
            'title' => 'Operação e Infraestrutura - EducaTudo',
            'user' => $user,
            'current_page' => 'dev',
            'csrf_token' => $this->generateCsrfToken(),
        ];

        $this->viewWithLayout('admin', 'admin/dev/operacao', $data);
    }

    public function devPwaSettings()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
        }

        $data = [
            'title' => 'PWA - Dev Settings',
            'user' => $user,
            'current_page' => 'dev',
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('admin', 'admin/dev/pwa', $data);
    }

    public function devLayoutMobileSettings()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
        }

        $data = [
            'title' => 'Layout Mobile - Dev Settings',
            'user' => $user,
            'current_page' => 'dev',
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('admin', 'admin/dev/layout-mobile', $data);
    }

    public function devCustosLLM()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
        }

        $dateStart = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateEnd = $_GET['data_fim'] ?? date('Y-m-d');

        // Prioridade: dados do banco (llm_usage_log). Fallback: cache JSON (histórico antigo).
        $report = \App\Services\MetricsService::getLLMCostsFromDatabase($dateStart, $dateEnd);
        if ($report['total_tokens'] === 0 && $report['total_cost'] == 0.0) {
            $report = \App\Services\MetricsService::getLLMCostsByDateRange($dateStart, $dateEnd);
        }
        $report['by_usage_type'] = $report['by_usage_type'] ?? [];

        $data = [
            'title' => 'Custos LLM - Dev Settings',
            'user' => $user,
            'current_page' => 'dev',
            'csrf_token' => $this->generateCsrfToken(),
            'report' => $report,
            'date_start' => $dateStart,
            'date_end' => $dateEnd
        ];

        $this->viewWithLayout('admin', 'admin/dev/custos-llm', $data);
    }

    public function devCustosLLMBackfill()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/dev-settings/custos-llm');
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/dev-settings/custos-llm');
            return;
        }
        $result = \App\Services\MetricsService::backfillLLMUsageFromDatabase();
        if ($result['success']) {
            $this->setFlashMessage($result['message'], 'success');
        } else {
            $this->setFlashMessage($result['message'], 'error');
        }
        $this->redirect('/admin/dev-settings/custos-llm');
    }

    public function devCustosLLMProcessarData()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/dev-settings/custos-llm');
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/dev-settings/custos-llm');
            return;
        }
        $dataParam = trim((string)($_POST['data_processar'] ?? ''));
        if ($dataParam === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataParam)) {
            $this->setFlashMessage('Informe uma data válida (AAAA-MM-DD).', 'error');
            $this->redirect('/admin/dev-settings/custos-llm');
            return;
        }
        $result = \App\Services\MetricsService::processDateForLLMUsage($dataParam, 'computed');
        if ($result['success']) {
            $this->setFlashMessage($result['message'], 'success');
        } else {
            $this->setFlashMessage($result['message'], 'error');
        }
        $this->redirect('/admin/dev-settings/custos-llm');
    }

    public function devLogs()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
        }

        $logDir = Logger::getLogDir();
        $realLogDir = realpath($logDir);
        if ($realLogDir === false || !is_dir($realLogDir)) {
            $logFiles = [];
        } else {
            $files = glob($logDir . '/*.log');
            $logFiles = [];
            foreach ($files as $path) {
                $basename = basename($path);
                if (preg_match('/^[a-zA-Z0-9_.-]+\.log$/', $basename)) {
                    $logFiles[] = [
                        'name' => $basename,
                        'path' => $path,
                        'size' => file_exists($path) ? filesize($path) : 0,
                        'mtime' => file_exists($path) ? filemtime($path) : 0,
                    ];
                }
            }
            usort($logFiles, function ($a, $b) {
                return ($b['mtime'] <=> $a['mtime']);
            });
        }

        $selectedFileName = null;
        $content = '';
        $fileParam = isset($_GET['file']) ? trim((string) $_GET['file']) : '';
        if ($fileParam !== '' && preg_match('/^[a-zA-Z0-9_.-]+\.log$/', $fileParam)) {
            $safePath = $logDir . '/' . $fileParam;
            $realPath = realpath($safePath);
            if ($realPath !== false && $realLogDir !== false && strpos($realPath, $realLogDir) === 0 && is_file($realPath)) {
                $selectedFileName = $fileParam;
                $content = file_get_contents($realPath);
            }
        }
        if ($selectedFileName === null && !empty($logFiles)) {
            $selectedFileName = $logFiles[0]['name'];
            $safePath = $logDir . '/' . $selectedFileName;
            $realPath = realpath($safePath);
            if ($realPath !== false && is_file($realPath)) {
                $content = file_get_contents($realPath);
            }
        }

        $flash = $this->getFlashMessage();
        $data = [
            'title' => 'Logs do Sistema - Dev Settings',
            'user' => $user,
            'current_page' => 'dev',
            'log_files' => $logFiles,
            'selected_file' => $selectedFileName,
            'content' => $content,
            'csrf_token' => $this->generateCsrfToken(),
            'flash_message' => $flash['message'] ?? '',
            'flash_type' => $flash['type'] ?? '',
        ];

        // Diagnóstico: se este header aparecer na resposta, o controller está sendo executado
        header('X-Logs-Page: dev-layout-v2');
        $this->viewWithLayout('admin', 'admin/dev/logs', $data);
    }

    public function devLogsDelete()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/dev-settings/logs');
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/dev-settings/logs');
            return;
        }
        $fileParam = isset($_POST['file']) ? trim((string) $_POST['file']) : '';
        if ($fileParam === '' || !preg_match('/^[a-zA-Z0-9_.-]+\.log$/', $fileParam)) {
            $this->setFlashMessage('Arquivo inválido.', 'error');
            $this->redirect('/admin/dev-settings/logs');
            return;
        }
        $logDir = Logger::getLogDir();
        $realLogDir = realpath($logDir);
        $safePath = $logDir . '/' . $fileParam;
        $realPath = realpath($safePath);
        if ($realPath === false || $realLogDir === false || strpos($realPath, $realLogDir) !== 0 || !is_file($realPath)) {
            $this->setFlashMessage('Arquivo não encontrado ou não permitido.', 'error');
            $this->redirect('/admin/dev-settings/logs');
            return;
        }
        if (@unlink($realPath)) {
            $this->setFlashMessage('Log "' . $fileParam . '" excluído.', 'success');
        } else {
            $this->setFlashMessage('Erro ao excluir o arquivo.', 'error');
        }
        $this->redirect('/admin/dev-settings/logs');
    }

    public function devWhatsappEvolutionTest()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
                return;
            }
            $this->redirect('/admin/dashboard');
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Método inválido.']);
                return;
            }
            $this->redirect('/admin/dev');
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Token inválido. Recarregue a página e tente novamente.']);
                return;
            }
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/dev');
            return;
        }
        $testMessage = '[EducaTudo] Teste de envio – ' . date('d/m/Y H:i:s') . ' – Se você recebeu esta mensagem, o WhatsApp (Evolution API) está funcionando.';
        if (!class_exists('EvolutionApiService')) {
            $path = __DIR__ . '/../../Services/EvolutionApiService.php';
            if (file_exists($path)) {
                require_once $path;
            }
        }
        if (!class_exists('EvolutionApiService')) {
            $msg = 'Serviço Evolution API não encontrado.';
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => $msg]);
                return;
            }
            $this->setFlashMessage($msg, 'error');
            $this->redirect('/admin/dev');
            return;
        }
        $ok = EvolutionApiService::sendTextToLogGroup($testMessage, 5);
        Logger::info('Teste WhatsApp Evolution API executado.', ['resultado' => $ok ? 'sucesso' : 'falha', 'user_id' => $user['id'] ?? null], 'evolution');
        $successMsg = 'Mensagem de teste enviada para o grupo WhatsApp. Confira o grupo.';
        $errorMsg = 'Falha ao enviar. Verifique em Dev Settings → Chaves de API → WhatsApp (Evolution API): URL, instância, grupo e API Key. Veja storage/logs/evolution_*.log para detalhes.';
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => $ok,
                'message' => $ok ? $successMsg : $errorMsg,
            ]);
            return;
        }
        if ($ok) {
            $this->setFlashMessage($successMsg, 'success');
        } else {
            $this->setFlashMessage($errorMsg, 'error');
        }
        $this->redirect('/admin/dev#tools');
    }

    public function devLogins()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
        }

        $perPage = 50;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $nome = trim((string)($_GET['nome'] ?? ''));
        $tipo = trim((string)($_GET['tipo'] ?? ''));
        $dataInicio = trim((string)($_GET['data_inicio'] ?? ''));
        $dataFim = trim((string)($_GET['data_fim'] ?? ''));

        $logins = [];
        $paramsAluno = [];
        $paramsAdmin = [];
        $paramsProf = [];
        $paramsPai = [];

        $whereAluno = [];
        if ($nome !== '') {
            $whereAluno[] = 'a.nome LIKE :nome';
            $paramsAluno['nome'] = '%' . $nome . '%';
        }
        if ($dataInicio !== '') {
            $whereAluno[] = 'DATE(s.login_at) >= :data_inicio';
            $paramsAluno['data_inicio'] = $dataInicio;
        }
        if ($dataFim !== '') {
            $whereAluno[] = 'DATE(s.login_at) <= :data_fim';
            $paramsAluno['data_fim'] = $dataFim;
        }
        $whereAlunoSql = empty($whereAluno) ? '' : ' AND ' . implode(' AND ', $whereAluno);

        $whereAudit = [];
        if ($nome !== '') {
            $paramsAdmin['nome'] = $paramsProf['nome'] = $paramsPai['nome'] = '%' . $nome . '%';
        }
        if ($dataInicio !== '') {
            $paramsAdmin['data_inicio'] = $paramsProf['data_inicio'] = $paramsPai['data_inicio'] = $dataInicio;
        }
        if ($dataFim !== '') {
            $paramsAdmin['data_fim'] = $paramsProf['data_fim'] = $paramsPai['data_fim'] = $dataFim;
        }
        $whereAuditSql = '';
        if ($nome !== '') {
            $whereAuditSql .= ' AND u.nome LIKE :nome';
        }
        if ($dataInicio !== '') {
            $whereAuditSql .= ' AND DATE(a.created_at) >= :data_inicio';
        }
        if ($dataFim !== '') {
            $whereAuditSql .= ' AND DATE(a.created_at) <= :data_fim';
        }
        $whereProfSql = str_replace('u.nome', 'p.nome', $whereAuditSql);
        $wherePaiSql = str_replace('u.nome', 'p.nome', $whereAuditSql);

        $runAluno = ($tipo === '' || $tipo === 'todos' || $tipo === 'Aluno');
        $runAdmin = ($tipo === '' || $tipo === 'todos' || strpos($tipo, 'Admin') === 0);
        $runProf = ($tipo === '' || $tipo === 'todos' || $tipo === 'Professor');
        $runPai = ($tipo === '' || $tipo === 'todos' || $tipo === 'Pai');

        if ($runAluno) {
            try {
                $rows = $this->db->fetchAll(
                    "SELECT s.login_at as data_hora, a.nome, 'Aluno' as tipo, t.nome as turma_nome
                     FROM alunos_sessoes_acesso s
                     INNER JOIN alunos a ON s.aluno_id = a.id
                     LEFT JOIN turmas t ON a.turma_id = t.id
                     WHERE 1=1 {$whereAlunoSql}
                     ORDER BY s.login_at DESC
                     LIMIT 2000",
                    $paramsAluno
                );
                foreach ($rows as $r) {
                    $logins[] = [
                        'data_hora' => $r['data_hora'],
                        'nome' => $r['nome'],
                        'tipo' => $r['tipo'],
                        'turma_nome' => $r['turma_nome'] ?? null,
                    ];
                }
            } catch (Exception $e) {
                // Tabela pode não existir
            }
        }

        if ($runAdmin || $runProf || $runPai) {
            try {
                if ($runAdmin) {
                    $admin = $this->db->fetchAll(
                        "SELECT a.created_at as data_hora, u.nome,
                                CASE WHEN u.perfil_admin IS NOT NULL AND u.perfil_admin != '' THEN CONCAT('Admin (', u.perfil_admin, ')') ELSE 'Admin' END as tipo
                         FROM logs_auditoria a
                         INNER JOIN usuarios u ON u.id = a.user_id
                         WHERE a.action = 'LOGIN' AND a.user_role IN ('admin', 'admin_escola') {$whereAuditSql}
                         ORDER BY a.created_at DESC
                         LIMIT 2000",
                        $paramsAdmin
                    );
                    foreach ($admin as $r) {
                        $logins[] = [
                            'data_hora' => $r['data_hora'],
                            'nome' => $r['nome'],
                            'tipo' => $r['tipo'],
                            'turma_nome' => null,
                        ];
                    }
                }
                if ($runProf) {
                    $prof = $this->db->fetchAll(
                        "SELECT a.created_at as data_hora, p.nome, 'Professor' as tipo
                         FROM logs_auditoria a
                         INNER JOIN professores p ON p.id = a.user_id
                         WHERE a.action = 'LOGIN' AND a.user_role = 'professor' {$whereProfSql}
                         ORDER BY a.created_at DESC
                         LIMIT 2000",
                        $paramsProf
                    );
                    foreach ($prof as $r) {
                        $logins[] = [
                            'data_hora' => $r['data_hora'],
                            'nome' => $r['nome'],
                            'tipo' => $r['tipo'],
                            'turma_nome' => null,
                        ];
                    }
                }
                if ($runPai) {
                    $pais = $this->db->fetchAll(
                        "SELECT a.created_at as data_hora, p.nome, 'Pai' as tipo
                         FROM logs_auditoria a
                         INNER JOIN responsaveis p ON p.id = a.user_id
                         WHERE a.action = 'LOGIN' AND a.user_role = 'pai' {$wherePaiSql}
                         ORDER BY a.created_at DESC
                         LIMIT 2000",
                        $paramsPai
                    );
                    foreach ($pais as $r) {
                        $logins[] = [
                            'data_hora' => $r['data_hora'],
                            'nome' => $r['nome'],
                            'tipo' => $r['tipo'],
                            'turma_nome' => null,
                        ];
                    }
                }
            } catch (Exception $e) {
                // logs_auditoria ou joins podem falhar
            }
        }

        usort($logins, function ($a, $b) {
            return strcmp($b['data_hora'], $a['data_hora']);
        });

        $total = count($logins);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = min(max(1, $page), $totalPages);
        $offset = ($page - 1) * $perPage;
        $logins = array_slice($logins, $offset, $perPage);

        $data = [
            'title' => 'Logins - Dev Settings',
            'user' => $user,
            'current_page' => 'dev',
            'logins' => $logins,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'filtros' => [
                'nome' => $nome,
                'tipo' => $tipo,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
            ],
        ];

        $this->viewWithLayout('admin', 'admin/dev/logins', $data);
    }

    public function devTickets()
    {
        $user = $this->auth->getUser();
        if (!$user || ($user['perfil_admin'] ?? '') !== 'dev') {
            $this->redirect('/admin/dashboard');
        }

        $suporte_tickets = $this->db->fetchAll(
            "SELECT t.*,
                    a.nome as aluno_nome,
                    a.nickname,
                    t.id as ticket_id,
                    (SELECT COUNT(*) FROM suporte_tickets_mensagens tm
                     WHERE tm.ticket_id = t.id
                       AND tm.remetente_tipo = 'aluno'
                       AND tm.lida = 0) as mensagens_nao_lidas,
                    (SELECT MAX(tm.criado_em) FROM suporte_tickets_mensagens tm WHERE tm.ticket_id = t.id) as ultima_mensagem
             FROM suporte_tickets t
             INNER JOIN alunos a ON a.id = t.aluno_id
             ORDER BY t.status ASC, t.atualizado_em DESC, t.criado_em DESC"
        );

        $openCount = $this->db->fetch(
            "SELECT COUNT(*) as total FROM suporte_tickets WHERE status = 'aberto'"
        )['total'] ?? 0;

        $data = [
            'title' => 'Tickets de Suporte - Dev',
            'page_title' => 'Tickets de Suporte',
            'user' => $user,
            'current_page' => 'dev_tickets',
            'tickets' => $suporte_tickets,
            'open_count' => (int) $openCount,
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('admin', 'admin/dev/tickets/index', $data);
    }

    public function devTicketShow($id)
    {
        $user = $this->auth->getUser();
        if (!$user || ($user['perfil_admin'] ?? '') !== 'dev') {
            $this->redirect('/admin/dashboard');
        }

        $ticketId = (int) $id;
        if ($ticketId <= 0) {
            $this->redirect('/admin/dev/tickets');
        }

        $ticket = $this->db->fetch(
            "SELECT t.*, a.nome as aluno_nome, a.nickname, a.ra
             FROM suporte_tickets t
             INNER JOIN alunos a ON a.id = t.aluno_id
             WHERE t.id = :ticket_id",
            ['ticket_id' => $ticketId]
        );

        if (!$ticket) {
            $this->redirect('/admin/dev/tickets');
        }

        $mensagens = $this->db->fetchAll(
            "SELECT tm.*,
                    CASE
                        WHEN tm.remetente_tipo = 'admin' THEN 'Suporte'
                        ELSE 'Aluno'
                    END as remetente_nome
             FROM suporte_tickets_mensagens tm
             WHERE tm.ticket_id = :ticket_id
             ORDER BY tm.criado_em ASC",
            ['ticket_id' => $ticketId]
        );

        $this->db->update(
            "UPDATE suporte_tickets_mensagens
             SET lida = 1
             WHERE ticket_id = :ticket_id
               AND remetente_tipo = 'aluno'
               AND lida = 0",
            ['ticket_id' => $ticketId]
        );

        $flash = $this->getFlashMessage();

        $data = [
            'title' => 'Ticket #' . $ticketId,
            'page_title' => 'Ticket #' . $ticketId,
            'user' => $user,
            'current_page' => 'dev_tickets',
            'ticket' => $ticket,
            'mensagens' => $mensagens,
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('admin', 'admin/dev/tickets/show', $data);
    }

    public function devTicketReply($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/dev/tickets/' . $id);
        }

        $user = $this->auth->getUser();
        if (!$user || ($user['perfil_admin'] ?? '') !== 'dev') {
            $this->redirect('/admin/dashboard');
        }

        $ticketId = (int) $id;
        $mensagem = trim($_POST['mensagem'] ?? '');
        if ($ticketId <= 0 || $mensagem === '') {
            $this->setFlashMessage('Mensagem inválida.', 'error');
            $this->redirect('/admin/dev/tickets/' . $ticketId);
        }

        $ticket = $this->db->fetch(
            "SELECT id FROM suporte_tickets WHERE id = :ticket_id",
            ['ticket_id' => $ticketId]
        );

        if (!$ticket) {
            $this->setFlashMessage('Ticket não encontrado.', 'error');
            $this->redirect('/admin/dev/tickets');
        }

        $this->db->insert(
            "INSERT INTO suporte_tickets_mensagens (ticket_id, remetente_tipo, remetente_id, mensagem, lida)
             VALUES (:ticket_id, 'admin', :remetente_id, :mensagem, 0)",
            [
                'ticket_id' => $ticketId,
                'remetente_id' => $user['id'],
                'mensagem' => $mensagem
            ]
        );

        $this->db->update(
            "UPDATE suporte_tickets SET status = 'aberto', atualizado_em = NOW() WHERE id = :ticket_id",
            ['ticket_id' => $ticketId]
        );

        $this->setFlashMessage('Resposta enviada com sucesso.', 'success');
        $this->redirect('/admin/dev/tickets/' . $ticketId);
    }

    public function devTicketClose($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/dev/tickets/' . $id);
        }

        $user = $this->auth->getUser();
        if (!$user || ($user['perfil_admin'] ?? '') !== 'dev') {
            $this->redirect('/admin/dashboard');
        }

        $ticketId = (int) $id;
        if ($ticketId <= 0) {
            $this->setFlashMessage('Ticket inválido.', 'error');
            $this->redirect('/admin/dev/tickets');
        }

        $this->db->update(
            "UPDATE suporte_tickets SET status = 'fechado', atualizado_em = NOW() WHERE id = :ticket_id",
            ['ticket_id' => $ticketId]
        );

        $this->setFlashMessage('Ticket fechado com sucesso.', 'success');
        $this->redirect('/admin/dev/tickets/' . $ticketId);
    }

    public function salvarPromptsRedacao()
    {
        $user = $this->auth->getUser();
        if (!$user || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
        }
        
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $prompt_tema = $_POST['prompt_tema'] ?? '';
            $prompt_correcao = $_POST['prompt_correcao'] ?? '';
            $prompt_tudinha_chat = $_POST['prompt_tudinha_chat'] ?? '';
            $prompt_ocr = $_POST['prompt_ocr'] ?? '';
            $prompt_prova = $_POST['prompt_prova'] ?? '';
            $prompt_prova_imagens = $_POST['prompt_prova_imagens'] ?? '';
            $prompt_exercicios_jornada = $_POST['prompt_exercicios_jornada'] ?? '';
            $prompt_exercicios_personalizados = $_POST['prompt_exercicios_personalizados'] ?? '';
            
            if (empty($prompt_tema) || empty($prompt_correcao)) {
                throw new Exception('Prompt de tema e correção são obrigatórios');
            }
            
            // Salvar prompts no banco
            $result1 = $this->db->query(
                "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                ['prompt_gerar_tema_redacao', $prompt_tema]
            );
            
            $result2 = $this->db->query(
                "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                ['prompt_corrigir_redacao', $prompt_correcao]
            );

            $this->db->query(
                "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                ['prompt_tudinha_chat', $prompt_tudinha_chat]
            );
            
            // Salvar prompt de OCR (opcional, pode estar vazio)
            $result3 = $this->db->query(
                "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                ['prompt_transcrever_imagem', $prompt_ocr]
            );

            $result4 = $this->db->query(
                "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                ['prompt_gerar_prova', $prompt_prova]
            );

            $this->db->query(
                "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                ['prompt_prova_imagens', $prompt_prova_imagens]
            );

            $result5 = $this->db->query(
                "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                ['prompt_gerar_exercicios_jornada', $prompt_exercicios_jornada]
            );

            $result6 = $this->db->query(
                "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                ['prompt_gerar_exercicios_personalizados', $prompt_exercicios_personalizados]
            );
            
            $this->json([
                'success' => true, 
                'message' => 'Prompts salvos com sucesso no banco de dados',
                'saved_keys' => [
                    'prompt_gerar_tema_redacao',
                    'prompt_corrigir_redacao',
                    'prompt_tudinha_chat',
                    'prompt_transcrever_imagem',
                    'prompt_gerar_prova',
                    'prompt_prova_imagens',
                    'prompt_gerar_exercicios_jornada',
                    'prompt_gerar_exercicios_personalizados'
                ]
            ]);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function buscarPromptsRedacao()
    {
        $user = $this->auth->getUser();
        if (!$user || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
        }
        
        try {
            $prompt_tema = $this->db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                ['prompt_gerar_tema_redacao']
            );
            
            $prompt_correcao = $this->db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                ['prompt_corrigir_redacao']
            );
            
            $prompt_ocr = $this->db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                ['prompt_transcrever_imagem']
            );

            $prompt_prova = $this->db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                ['prompt_gerar_prova']
            );

            $prompt_prova_imagens = $this->db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                ['prompt_prova_imagens']
            );

            $prompt_exercicios_jornada = $this->db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                ['prompt_gerar_exercicios_jornada']
            );

            $prompt_exercicios_personalizados = $this->db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                ['prompt_gerar_exercicios_personalizados']
            );

            $prompt_tudinha_chat = $this->db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                ['prompt_tudinha_chat']
            );
            
            $this->json([
                'success' => true,
                'prompt_tema' => $prompt_tema['config_value'] ?? '',
                'prompt_correcao' => $prompt_correcao['config_value'] ?? '',
                'prompt_tudinha_chat' => $prompt_tudinha_chat['config_value'] ?? '',
                'prompt_ocr' => $prompt_ocr['config_value'] ?? '',
                'prompt_prova' => $prompt_prova['config_value'] ?? '',
                'prompt_prova_imagens' => $prompt_prova_imagens['config_value'] ?? '',
                'prompt_exercicios_jornada' => $prompt_exercicios_jornada['config_value'] ?? '',
                'prompt_exercicios_personalizados' => $prompt_exercicios_personalizados['config_value'] ?? ''
            ]);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function salvarValorUsuario()
    {
        $user = $this->auth->getUser();
        if (!$user || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
        }
        
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $valor_por_usuario = $_POST['valor_por_usuario'] ?? '0.00';
            
            // Validar que é um número válido
            $valor_por_usuario = floatval($valor_por_usuario);
            if ($valor_por_usuario < 0) {
                throw new Exception('O valor não pode ser negativo');
            }
            
            // Salvar no banco
            $this->db->query(
                "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                ['valor_por_usuario', number_format($valor_por_usuario, 2, '.', '')]
            );
            
            $this->json([
                'success' => true, 
                'message' => 'Valor por usuário salvo com sucesso'
            ]);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function salvarLimitesDiarios()
    {
        $user = $this->auth->getUser();
        if (!$user || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
        }
        
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $limites = [
                'limit_chat_interacoes' => intval($_POST['chat_interacoes'] ?? 0),
                'limit_gerar_tema_redacao' => intval($_POST['gerar_tema_redacao'] ?? 0),
                'limit_corrigir_redacao' => intval($_POST['corrigir_redacao'] ?? 0),
                'limit_exercicios' => intval($_POST['exercicios'] ?? 0),
                'limit_simulados' => intval($_POST['simulados'] ?? 0),
            ];
            
            // Salvar limites no banco
            foreach ($limites as $key => $value) {
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    [$key, $value]
                );
            }
            
            $this->json([
                'success' => true, 
                'message' => 'Limites diários salvos com sucesso'
            ]);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function salvarApiKeys()
    {
        $user = $this->auth->getUser();
        if (!$user || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado. Apenas desenvolvedores podem alterar chaves de API.'], 403);
        }
        
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $messages = [];
            
            // Processar chave OpenAI
            $openai_key = trim($_POST['openai_api_key'] ?? '');
            $openai_key = preg_replace('/\s+/', '', $openai_key);
            
            if (!empty($openai_key)) {
                // Validar formato básico da chave OpenAI (deve começar com sk-)
                if (strpos($openai_key, 'sk-') !== 0) {
                    $this->json(['error' => 'Formato da chave OpenAI inválido. A chave deve começar com "sk-".'], 400);
                    return;
                }
                
                if (strlen($openai_key) < 13) {
                    $this->json(['error' => 'Chave OpenAI muito curta. Verifique se copiou a chave completa.'], 400);
                    return;
                }
                
                if (!preg_match('/^sk-[a-zA-Z0-9_.-]+$/', $openai_key)) {
                    $this->json(['error' => 'Chave OpenAI contém caracteres inválidos. Verifique se copiou corretamente.'], 400);
                    return;
                }
                
                // Salvar chave OpenAI no banco
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    ['openai_api_key', $openai_key]
                );
                $messages[] = 'Chave OpenAI salva';
            } else {
                // Remover chave OpenAI do banco se estiver vazia
                $this->db->query(
                    "DELETE FROM config_layout WHERE config_key = ?",
                    ['openai_api_key']
                );
                $messages[] = 'Chave OpenAI removida (usará .env)';
            }
            
            // Processar chave Gamma
            $gamma_key = trim($_POST['gamma_api_key'] ?? '');
            $gamma_key = preg_replace('/\s+/', '', $gamma_key);
            
            if (!empty($gamma_key)) {
                // Validar comprimento mínimo da chave Gamma
                if (strlen($gamma_key) < 10) {
                    $this->json(['error' => 'Chave Gamma muito curta. Verifique se copiou a chave completa.'], 400);
                    return;
                }
                
                // Salvar chave Gamma no banco
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    ['gamma_api_key', $gamma_key]
                );
                $messages[] = 'Chave Gamma salva';
            } else {
                // Remover chave Gamma do banco se estiver vazia
                $this->db->query(
                    "DELETE FROM config_layout WHERE config_key = ?",
                    ['gamma_api_key']
                );
                $messages[] = 'Chave Gamma removida (usará .env)';
            }
            
            // Processar chave Replicate (API Token)
            $replicate_key = trim($_POST['replicate_api_key'] ?? '');
            $replicate_key = preg_replace('/\s+/', '', $replicate_key);
            
            if (!empty($replicate_key)) {
                // Validar formato da chave Replicate (deve começar com "r8_")
                if (strpos($replicate_key, 'r8_') !== 0) {
                    $this->json(['error' => 'Formato da chave Replicate inválido. A chave deve começar com "r8_".'], 400);
                    return;
                }
                
                if (strlen($replicate_key) < 10) {
                    $this->json(['error' => 'Chave Replicate muito curta. Verifique se copiou a chave completa.'], 400);
                    return;
                }
                
                if (!preg_match('/^r8_[a-zA-Z0-9]+$/', $replicate_key)) {
                    $this->json(['error' => 'Chave Replicate contém caracteres inválidos. Verifique se copiou corretamente.'], 400);
                    return;
                }
                
                // Salvar chave Replicate no banco
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    ['replicate_api_key', $replicate_key]
                );
                $messages[] = 'Chave Replicate salva';
            } else {
                // Remover chave Replicate do banco se estiver vazia
                $this->db->query(
                    "DELETE FROM config_layout WHERE config_key = ?",
                    ['replicate_api_key']
                );
                $messages[] = 'Chave Replicate removida (usará .env)';
            }
            
            // Processar versão do modelo Replicate
            $replicate_model = trim($_POST['replicate_model_version'] ?? '');
            
            if (!empty($replicate_model)) {
                // Salvar versão do modelo no banco
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    ['replicate_model_version', $replicate_model]
                );
                $messages[] = 'Versão do modelo Replicate salva';
            } else {
                // Remover versão do modelo do banco se estiver vazia
                $this->db->query(
                    "DELETE FROM config_layout WHERE config_key = ?",
                    ['replicate_model_version']
                );
                $messages[] = 'Versão do modelo Replicate removida (usará padrão)';
            }
            
            // Processar chave Nano Banana (compatibilidade - manter por enquanto)
            $nanobanana_key = trim($_POST['nanobanana_api_key'] ?? '');
            $nanobanana_key = preg_replace('/\s+/', '', $nanobanana_key);
            
            if (!empty($nanobanana_key)) {
                // Validar comprimento mínimo
                if (strlen($nanobanana_key) < 10) {
                    $this->json(['error' => 'Chave Nano Banana muito curta. Verifique se copiou a chave completa.'], 400);
                    return;
                }
                
                // Salvar chave Nano Banana no banco
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    ['nanobanana_api_key', $nanobanana_key]
                );
                $messages[] = 'Chave Nano Banana salva';
            } else {
                // Remover chave Nano Banana do banco se estiver vazia
                $this->db->query(
                    "DELETE FROM config_layout WHERE config_key = ?",
                    ['nanobanana_api_key']
                );
                $messages[] = 'Chave Nano Banana removida (usará .env)';
            }
            
            // Processar chave ElevenLabs
            $elevenlabs_key = trim($_POST['elevenlabs_api_key'] ?? '');
            $elevenlabs_key = preg_replace('/\s+/', '', $elevenlabs_key);
            
            if (!empty($elevenlabs_key)) {
                // Validar comprimento mínimo
                if (strlen($elevenlabs_key) < 10) {
                    $this->json(['error' => 'Chave ElevenLabs muito curta. Verifique se copiou a chave completa.'], 400);
                    return;
                }
                
                // Salvar chave ElevenLabs no banco
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    ['elevenlabs_api_key', $elevenlabs_key]
                );
                $messages[] = 'Chave ElevenLabs salva';
            } else {
                // Remover chave ElevenLabs do banco se estiver vazia
                $this->db->query(
                    "DELETE FROM config_layout WHERE config_key = ?",
                    ['elevenlabs_api_key']
                );
                $messages[] = 'Chave ElevenLabs removida (usará .env)';
            }
            
            // Processar OneSignal App ID
            $onesignal_app_id = trim($_POST['onesignal_app_id'] ?? '');
            $onesignal_app_id = preg_replace('/\s+/', '', $onesignal_app_id);
            if (!empty($onesignal_app_id)) {
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    ['onesignal_app_id', $onesignal_app_id]
                );
                $messages[] = 'OneSignal App ID salvo';
            } else {
                $this->db->query("DELETE FROM config_layout WHERE config_key = ?", ['onesignal_app_id']);
                $messages[] = 'OneSignal App ID removido (usará .env)';
            }
            
            // Processar OneSignal REST API Key
            $onesignal_rest_key = trim($_POST['onesignal_rest_api_key'] ?? '');
            $onesignal_rest_key = preg_replace('/\s+/', '', $onesignal_rest_key);
            if (!empty($onesignal_rest_key)) {
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    ['onesignal_rest_api_key', $onesignal_rest_key]
                );
                $messages[] = 'OneSignal REST API Key salva';
            } else {
                $this->db->query("DELETE FROM config_layout WHERE config_key = ?", ['onesignal_rest_api_key']);
                $messages[] = 'OneSignal REST API Key removida (usará .env)';
            }
            
            // Processar Evolution API (WhatsApp - notificação de logs)
            $evolution_api_url = trim($_POST['evolution_api_url'] ?? '');
            $evolution_instance = trim($_POST['evolution_instance'] ?? '');
            $evolution_group_id = trim($_POST['evolution_group_id'] ?? '');
            $evolution_api_key = trim($_POST['evolution_api_key'] ?? '');
            $evolution_api_key = preg_replace('/\s+/', '', $evolution_api_key);
            if (!empty($evolution_api_url)) {
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    ['evolution_api_url', $evolution_api_url]
                );
            } else {
                $this->db->query("DELETE FROM config_layout WHERE config_key = ?", ['evolution_api_url']);
            }
            if (!empty($evolution_instance)) {
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    ['evolution_instance', $evolution_instance]
                );
            } else {
                $this->db->query("DELETE FROM config_layout WHERE config_key = ?", ['evolution_instance']);
            }
            if (!empty($evolution_group_id)) {
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    ['evolution_group_id', $evolution_group_id]
                );
            } else {
                $this->db->query("DELETE FROM config_layout WHERE config_key = ?", ['evolution_group_id']);
            }
            if (!empty($evolution_api_key)) {
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    ['evolution_api_key', $evolution_api_key]
                );
                $messages[] = 'Evolution API (WhatsApp) salva';
            } else {
                // Não apagar a API Key quando o campo vem vazio: o campo é type="password" e o navegador
                // costuma não enviar o valor ao salvar, então empty = "não alterar", não "remover".
                // Para remover de fato, o usuário pode limpar no .env ou usar um valor conhecido (ex.: REMOVER).
            }
            
            // Logar a ação (sem expor as chaves)
            if (!class_exists('Logger')) {
                require_once __DIR__ . '/../Core/Logger.php';
            }
            Logger::info(
                "Chaves de API atualizadas via interface admin",
                [
                    'user_id' => $user['id'],
                    'user_name' => $user['nome'] ?? 'N/A',
                    'openai_updated' => !empty($openai_key),
                    'gamma_updated' => !empty($gamma_key),
                    'replicate_updated' => !empty($replicate_key),
                    'replicate_model_updated' => !empty($replicate_model),
                    'nanobanana_updated' => !empty($nanobanana_key),
                    'elevenlabs_updated' => !empty($elevenlabs_key),
                    'onesignal_app_id_updated' => !empty($onesignal_app_id),
                    'onesignal_rest_api_key_updated' => !empty($onesignal_rest_key),
                    'evolution_updated' => !empty($evolution_api_url) || !empty($evolution_api_key)
                ],
                'api'
            );
            
            $this->json([
                'success' => true, 
                'message' => implode(' e ', $messages) . ' com sucesso'
            ]);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function migrations()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
            return;
        }
        
        // Buscar escolas cadastradas
        $escolas = $this->db->fetchAll(
            "SELECT * FROM config_escolas_database WHERE ativo = 1 ORDER BY escola_nome ASC"
        );
        
        // Buscar todas as migrations disponíveis
        // Caminho correto: de app/Controllers/User para database/migrations (3 níveis acima)
        $migrationsDir = dirname(dirname(dirname(__DIR__))) . '/database/migrations';
        $migrations = [];
        
        if (is_dir($migrationsDir)) {
            $files = scandir($migrationsDir);
            foreach ($files as $file) {
                // Ignorar arquivos especiais e não-SQL
                if ($file === '.' || $file === '..' || $file === 'README.md') {
                    continue;
                }
                
                // Aceitar apenas arquivos .sql
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if ($extension === 'sql') {
                    $filePath = $migrationsDir . '/' . $file;
                    if (is_file($filePath)) {
                        $migrations[] = [
                            'file' => $file,
                            'path' => $filePath,
                            'size' => filesize($filePath),
                            'modified' => filemtime($filePath)
                        ];
                    }
                }
            }
            // Ordenar por nome (que geralmente contém data)
            usort($migrations, function($a, $b) {
                return strcmp($a['file'], $b['file']);
            });
        } else {
            // Log erro se diretório não existir
            error_log("Diretório de migrations não encontrado: {$migrationsDir}");
        }
        
        // Para cada escola, verificar quais migrations já foram executadas
        foreach ($escolas as &$escola) {
            $migrationsExecutadas = $this->db->fetchAll(
                "SELECT migration_file, executada_em, status, mensagem_erro 
                 FROM migrations_executadas 
                 WHERE escola_database_config_id = :escola_id 
                 ORDER BY executada_em DESC",
                ['escola_id' => $escola['id']]
            );
            
            $escola['migrations_executadas'] = [];
            foreach ($migrationsExecutadas as $mig) {
                $escola['migrations_executadas'][$mig['migration_file']] = $mig;
            }
        }
        
        $data = [
            'title' => 'Gerenciamento de Migrations - EducaTudo',
            'user' => $user,
            'current_page' => 'dev',
            'csrf_token' => $this->generateCsrfToken(),
            'escolas' => $escolas,
            'migrations' => $migrations
        ];
        
        $this->viewWithLayout('admin', 'admin/dev/migrations', $data);
    }

    public function salvarEscolaDatabase()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
            return;
        }
        
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            $escola_nome = trim($_POST['escola_nome'] ?? '');
            $db_host = trim($_POST['db_host'] ?? '');
            $db_port = !empty($_POST['db_port']) ? (int)$_POST['db_port'] : 3306;
            $db_name = trim($_POST['db_name'] ?? '');
            $db_user = trim($_POST['db_user'] ?? '');
            $db_pass = $_POST['db_pass'] ?? '';
            $ativo = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1;
            
            // Campos SSH (opcionais, apenas para demo.educatudo.com)
            $ssh_host = trim($_POST['ssh_host'] ?? '');
            $ssh_port = !empty($_POST['ssh_port']) ? (int)$_POST['ssh_port'] : 22;
            $ssh_user = trim($_POST['ssh_user'] ?? '');
            $ssh_pass = $_POST['ssh_pass'] ?? '';
            $ssh_path = trim($_POST['ssh_path'] ?? '');
            
            if (empty($escola_nome) || empty($db_host) || empty($db_name) || empty($db_user) || empty($db_pass)) {
                throw new Exception('Todos os campos de banco de dados são obrigatórios');
            }
            
            if ($id) {
                // Atualizar
                $this->db->update(
                    "UPDATE config_escolas_database 
                     SET escola_nome = :escola_nome, db_host = :db_host, db_port = :db_port, 
                         db_name = :db_name, db_user = :db_user, db_pass = :db_pass, ativo = :ativo,
                         ssh_host = :ssh_host, ssh_port = :ssh_port, ssh_user = :ssh_user, 
                         ssh_pass = :ssh_pass, ssh_path = :ssh_path
                     WHERE id = :id",
                    [
                        'id' => $id,
                        'escola_nome' => $escola_nome,
                        'db_host' => $db_host,
                        'db_port' => $db_port,
                        'db_name' => $db_name,
                        'db_user' => $db_user,
                        'db_pass' => $db_pass,
                        'ativo' => $ativo,
                        'ssh_host' => $ssh_host ?: null,
                        'ssh_port' => $ssh_port,
                        'ssh_user' => $ssh_user ?: null,
                        'ssh_pass' => $ssh_pass ?: null,
                        'ssh_path' => $ssh_path ?: null
                    ]
                );
                $message = 'Configuração atualizada com sucesso';
            } else {
                // Criar
                $this->db->insert(
                    "INSERT INTO config_escolas_database 
                     (escola_nome, db_host, db_port, db_name, db_user, db_pass, ativo, ssh_host, ssh_port, ssh_user, ssh_pass, ssh_path) 
                     VALUES (:escola_nome, :db_host, :db_port, :db_name, :db_user, :db_pass, :ativo, :ssh_host, :ssh_port, :ssh_user, :ssh_pass, :ssh_path)",
                    [
                        'escola_nome' => $escola_nome,
                        'db_host' => $db_host,
                        'db_port' => $db_port,
                        'db_name' => $db_name,
                        'db_user' => $db_user,
                        'db_pass' => $db_pass,
                        'ativo' => $ativo,
                        'ssh_host' => $ssh_host ?: null,
                        'ssh_port' => $ssh_port,
                        'ssh_user' => $ssh_user ?: null,
                        'ssh_pass' => $ssh_pass ?: null,
                        'ssh_path' => $ssh_path ?: null
                    ]
                );
                $message = 'Configuração criada com sucesso';
            }
            
            $this->json(['success' => true, 'message' => $message]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function executarMigration()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
            return;
        }
        
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $escola_id = (int)($_POST['escola_id'] ?? 0);
            $migration_file = trim($_POST['migration_file'] ?? '');
            
            if (empty($escola_id) || empty($migration_file)) {
                throw new Exception('Escola e migration são obrigatórios');
            }
            
            // Verificar se já foi executada com sucesso (permitir reexecutar se houve erro)
            $jaExecutada = $this->db->fetch(
                "SELECT * FROM migrations_executadas 
                 WHERE escola_database_config_id = :escola_id AND migration_file = :migration_file",
                ['escola_id' => $escola_id, 'migration_file' => $migration_file]
            );
            
            if ($jaExecutada && $jaExecutada['status'] === 'sucesso') {
                throw new Exception('Esta migration já foi executada com sucesso para esta escola');
            }
            
            // Buscar configuração da escola
            $escola = $this->db->fetch(
                "SELECT * FROM config_escolas_database WHERE id = :id AND ativo = 1",
                ['id' => $escola_id]
            );
            
            if (!$escola) {
                throw new Exception('Escola não encontrada ou inativa');
            }
            
            // Ler arquivo de migration
            $migrationsDir = dirname(dirname(dirname(__DIR__))) . '/database/migrations';
            $migrationPath = $migrationsDir . '/' . $migration_file;
            
            if (!file_exists($migrationPath)) {
                throw new Exception('Arquivo de migration não encontrado: ' . $migration_file);
            }
            
            $sql = file_get_contents($migrationPath);
            if (empty(trim($sql))) {
                throw new Exception('Arquivo de migration está vazio');
            }
            
            // Conectar ao banco da escola
            $dsn = "mysql:host={$escola['db_host']};port={$escola['db_port']};dbname={$escola['db_name']};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, $escola['db_user'], $escola['db_pass'], $options);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Executar SQL diretamente (sem transação para evitar problemas)
            // Dividir por ; mas manter prepared statements intactos
            $queries = [];
            $currentQuery = '';
            $inString = false;
            $stringChar = '';
            
            // Processar SQL caractere por caractere para não quebrar strings
            for ($i = 0; $i < strlen($sql); $i++) {
                $char = $sql[$i];
                
                if (!$inString && ($char === '"' || $char === "'" || $char === '`')) {
                    $inString = true;
                    $stringChar = $char;
                    $currentQuery .= $char;
                } elseif ($inString && $char === $stringChar) {
                    // Verificar se não é escape
                    if ($i > 0 && $sql[$i - 1] !== '\\') {
                        $inString = false;
                        $stringChar = '';
                    }
                    $currentQuery .= $char;
                } elseif (!$inString && $char === ';') {
                    $query = trim($currentQuery);
                    if (!empty($query)) {
                        $queries[] = $query;
                    }
                    $currentQuery = '';
                } else {
                    $currentQuery .= $char;
                }
            }
            
            // Adicionar última query se houver
            $lastQuery = trim($currentQuery);
            if (!empty($lastQuery)) {
                $queries[] = $lastQuery;
            }
            
            $erro = null;
            $queriesExecutadas = 0;
            
            // Executar cada query
            foreach ($queries as $query) {
                if (empty(trim($query))) {
                    continue;
                }
                
                try {
                    $pdo->exec($query);
                    $queriesExecutadas++;
                } catch (PDOException $e) {
                    $erro = $e->getMessage();
                    error_log("Erro ao executar query: " . $erro);
                    error_log("Query que falhou: " . substr($query, 0, 200));
                    break; // Parar na primeira query com erro
                }
            }
            
            // Registrar resultado
            if ($erro) {
                // Registrar erro
                if ($jaExecutada) {
                    $this->db->update(
                        "UPDATE migrations_executadas 
                         SET status = 'erro', mensagem_erro = :erro, executada_em = NOW(), executado_por = :user_id
                         WHERE id = :id",
                        [
                            'id' => $jaExecutada['id'],
                            'erro' => $erro,
                            'user_id' => $user['id']
                        ]
                    );
                } else {
                    $this->db->insert(
                        "INSERT INTO migrations_executadas 
                         (escola_database_config_id, migration_file, status, mensagem_erro, executado_por) 
                         VALUES (:escola_id, :migration_file, 'erro', :erro, :user_id)",
                        [
                            'escola_id' => $escola_id,
                            'migration_file' => $migration_file,
                            'erro' => $erro,
                            'user_id' => $user['id']
                        ]
                    );
                }
                
                throw new Exception('Erro ao executar migration: ' . $erro);
            } else {
                // Registrar sucesso
                if ($jaExecutada) {
                    $this->db->update(
                        "UPDATE migrations_executadas 
                         SET status = 'sucesso', mensagem_erro = NULL, executada_em = NOW(), executado_por = :user_id
                         WHERE id = :id",
                        ['id' => $jaExecutada['id'], 'user_id' => $user['id']]
                    );
                } else {
                    $this->db->insert(
                        "INSERT INTO migrations_executadas 
                         (escola_database_config_id, migration_file, status, executado_por) 
                         VALUES (:escola_id, :migration_file, 'sucesso', :user_id)",
                        [
                            'escola_id' => $escola_id,
                            'migration_file' => $migration_file,
                            'user_id' => $user['id']
                        ]
                    );
                }
                
                $this->json([
                    'success' => true, 
                    'message' => "Migration executada com sucesso! ({$queriesExecutadas} queries executadas)"
                ]);
            }
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function executarTodasMigrations()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
            return;
        }
        
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $escola_id = isset($_POST['escola_id']) ? (int)$_POST['escola_id'] : null;
            
            require_once __DIR__ . '/../../app/Core/MigrationRunner.php';
            
            // Criar tabela de controle se não existir
            \App\Core\MigrationRunner::createMigrationsTable();
            
            $targetDb = null;
            if ($escola_id) {
                $escola = $this->db->fetch(
                    "SELECT * FROM config_escolas_database WHERE id = :id AND ativo = 1",
                    ['id' => $escola_id]
                );
                
                if (!$escola) {
                    throw new Exception('Escola não encontrada ou inativa');
                }
                
                $targetDb = $escola;
            }
            
            $runner = new \App\Core\MigrationRunner($targetDb);
            $result = $runner->run(false);
            
            $this->json([
                'success' => true,
                'message' => "Executadas {$result['executed']} de {$result['total']} migrations",
                'result' => $result
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function deletarEscolaDatabase()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
            return;
        }
        
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $id = (int)($_POST['id'] ?? 0);
            if (empty($id)) {
                throw new Exception('ID inválido');
            }
            
            $this->db->query("DELETE FROM config_escolas_database WHERE id = :id", ['id' => $id]);
            
            $this->json(['success' => true, 'message' => 'Configuração deletada com sucesso']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function ssh()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->redirect('/admin/dashboard');
            return;
        }
        
        // Verificar se é demo.educatudo.com
        if (!$this->isDemoEducatudo()) {
            $this->setFlashMessage('Acesso SSH disponível apenas em demo.educatudo.com', 'error');
            $this->redirect('/admin/dev');
            return;
        }
        
        // Buscar escola demo.educatudo.com
        $escola = $this->db->fetch(
            "SELECT * FROM config_escolas_database 
             WHERE escola_nome LIKE '%demo%' OR escola_nome LIKE '%Demo%' 
             ORDER BY id DESC LIMIT 1"
        );
        
        // Se não encontrar, buscar qualquer escola com SSH configurado
        if (!$escola || empty($escola['ssh_host'])) {
            $escola = $this->db->fetch(
                "SELECT * FROM config_escolas_database 
                 WHERE ssh_host IS NOT NULL AND ssh_host != '' 
                 ORDER BY id DESC LIMIT 1"
            );
        }
        
        $data = [
            'title' => 'SSH e Git - EducaTudo',
            'user' => $user,
            'current_page' => 'dev',
            'csrf_token' => $this->generateCsrfToken(),
            'escola' => $escola
        ];
        
        $this->viewWithLayout('admin', 'admin/dev/ssh', $data);
    }

    public function executarSSH()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
            return;
        }
        
        // Verificar se é demo.educatudo.com
        if (!$this->isDemoEducatudo()) {
            $this->json(['error' => 'Acesso SSH disponível apenas em demo.educatudo.com'], 403);
            return;
        }
        
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $escola_id = (int)($_POST['escola_id'] ?? 0);
            $comando = trim($_POST['comando'] ?? '');
            
            if (empty($escola_id) || empty($comando)) {
                throw new Exception('Escola e comando são obrigatórios');
            }
            
            // Buscar configuração da escola
            $escola = $this->db->fetch(
                "SELECT * FROM config_escolas_database WHERE id = :id",
                ['id' => $escola_id]
            );
            
            if (!$escola || empty($escola['ssh_host']) || empty($escola['ssh_user'])) {
                throw new Exception('Configuração SSH não encontrada para esta escola');
            }
            
            // Validar comando (permitir apenas comandos seguros)
            $comandosPermitidos = [
                'git pull',
                'git status',
                'git log',
                'git branch',
                'git fetch',
                'git diff',
                'ls',
                'pwd',
                'whoami',
                'php -v',
                'composer --version'
            ];
            
            $comandoValido = false;
            foreach ($comandosPermitidos as $cmd) {
                if (strpos($comando, $cmd) === 0) {
                    $comandoValido = true;
                    break;
                }
            }
            
            // Permitir comandos git customizados (mas com validação)
            if (!$comandoValido && strpos($comando, 'git ') === 0) {
                // Validar que não contém comandos perigosos
                $comandosPerigosos = ['rm', 'delete', 'drop', 'truncate', '>', '>>', '|', '&', ';', '&&', '||'];
                $perigoso = false;
                foreach ($comandosPerigosos as $cmd) {
                    if (strpos($comando, $cmd) !== false) {
                        $perigoso = true;
                        break;
                    }
                }
                if (!$perigoso) {
                    $comandoValido = true;
                }
            }
            
            if (!$comandoValido) {
                throw new Exception('Comando não permitido. Use apenas comandos git, ls, pwd, whoami, php -v ou composer --version');
            }
            
            // Verificar se extensão SSH2 está disponível
            if (!function_exists('ssh2_connect')) {
                throw new Exception('Extensão PHP SSH2 não está instalada. Instale com: sudo apt-get install php-ssh2');
            }
            
            // Executar comando via SSH
            $connection = @ssh2_connect($escola['ssh_host'], $escola['ssh_port']);
            if (!$connection) {
                throw new Exception('Não foi possível conectar ao servidor SSH. Verifique host, porta e conectividade.');
            }
            
            if (!@ssh2_auth_password($connection, $escola['ssh_user'], $escola['ssh_pass'])) {
                throw new Exception('Falha na autenticação SSH. Verifique usuário e senha.');
            }
            
            // Navegar para o diretório do projeto se especificado
            $comandoFinal = $comando;
            if (!empty($escola['ssh_path'])) {
                $comandoFinal = "cd " . escapeshellarg($escola['ssh_path']) . " && " . $comando;
            }
            
            $stream = @ssh2_exec($connection, $comandoFinal);
            if (!$stream) {
                throw new Exception('Falha ao executar comando');
            }
            
            stream_set_blocking($stream, true);
            $output = stream_get_contents($stream);
            $errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
            $error = $errorStream ? stream_get_contents($errorStream) : null;
            fclose($stream);
            
            $this->json([
                'success' => true,
                'output' => $output ?: '(sem saída)',
                'error' => $error ?: null,
                'comando' => $comandoFinal
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function listarTutoriais()
    {
        // Se for admin, retornar todos (incluindo inativos)
        // Se for professor, retornar apenas ativos
        $user = $this->auth->getUser();
        $isAdmin = $user && (($user['tipo'] === 'admin' || $user['tipo'] === 'admin_escola') && isset($user['perfil_admin']) && $user['perfil_admin'] === 'dev');
        
        if ($isAdmin) {
            // Admin vê todos os tutoriais
            $tutoriais = $this->db->fetchAll(
                "SELECT * FROM tutoriais ORDER BY ordem ASC, id ASC"
            );
        } else {
            // Professor vê apenas os ativos
            $tutoriais = $this->db->fetchAll(
                "SELECT * FROM tutoriais WHERE ativo = 1 ORDER BY ordem ASC, id ASC"
            );
        }
        
        $this->json(['success' => true, 'tutoriais' => $tutoriais]);
    }

    public function salvarTutorial()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            $titulo = trim($_POST['titulo'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $link_youtube = trim($_POST['link_youtube'] ?? '');
            $ordem = !empty($_POST['ordem']) ? (int)$_POST['ordem'] : 0;
            $ativo = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1;
            
            if (empty($titulo) || empty($link_youtube)) {
                throw new Exception('Título e link do YouTube são obrigatórios');
            }
            
            // Validar link do YouTube
            if (!preg_match('/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/', $link_youtube)) {
                throw new Exception('Link do YouTube inválido');
            }
            
            if ($id) {
                // Atualizar
                $this->db->update(
                    "UPDATE tutoriais 
                     SET titulo = :titulo, descricao = :descricao, link_youtube = :link_youtube, 
                         ordem = :ordem, ativo = :ativo, updated_at = NOW()
                     WHERE id = :id",
                    [
                        'id' => $id,
                        'titulo' => $titulo,
                        'descricao' => $descricao,
                        'link_youtube' => $link_youtube,
                        'ordem' => $ordem,
                        'ativo' => $ativo
                    ]
                );
                $message = 'Tutorial atualizado com sucesso';
            } else {
                // Criar
                $this->db->insert(
                    "INSERT INTO tutoriais (titulo, descricao, link_youtube, ordem, ativo) 
                     VALUES (:titulo, :descricao, :link_youtube, :ordem, :ativo)",
                    [
                        'titulo' => $titulo,
                        'descricao' => $descricao,
                        'link_youtube' => $link_youtube,
                        'ordem' => $ordem,
                        'ativo' => $ativo
                    ]
                );
                $message = 'Tutorial criado com sucesso';
            }
            
            $this->json(['success' => true, 'message' => $message]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function deletarTutorial()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $id = (int)($_POST['id'] ?? 0);
            
            if (empty($id)) {
                throw new Exception('ID do tutorial é obrigatório');
            }
            
            $this->db->delete("DELETE FROM tutoriais WHERE id = :id", ['id' => $id]);
            
            $this->json(['success' => true, 'message' => 'Tutorial deletado com sucesso']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function getMetrics()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
            return;
        }

        try {
            $date = $_GET['date'] ?? null;
            if (!empty($date)) {
                $metrics = \App\Services\MetricsService::getMetricsByDate($date);
                if ($metrics === null) {
                    $this->json(['error' => 'Nenhuma métrica encontrada para a data informada'], 404);
                    return;
                }
            } else {
                $metricsData = \App\Services\MetricsService::getMetrics();
            $metrics = $metricsData['metrics'] ?? [];
                $date = date('Y-m-d');
            }

            // Formatar resposta para o frontend
            $response = [
                'success' => true,
                'date' => $date,
                'metrics' => [
                    'ai' => [
                        'requests' => $metrics['ai_requests'] ?? 0,
                        'tokens' => $metrics['tokens_today'] ?? 0,
                        'cost' => $metrics['cost_today'] ?? 0,
                        'errors' => $metrics['ai_errors'] ?? 0,
                        'avg_time' => $metrics['avg_request_time'] ?? 0
                    ],
                    'db' => [
                        'queries' => $metrics['db_queries'] ?? 0,
                        'slow_queries' => $metrics['slow_queries'] ?? 0,
                        'errors' => $metrics['db_errors'] ?? 0,
                        'avg_time' => $metrics['avg_db_time'] ?? 0
                    ],
                    'system' => [
                        'memory_peak' => $metrics['memory_peak'] ?? 0,
                        'requests_minute' => $metrics['requests_minute'] ?? 0,
                        'errors_500' => $metrics['errors_500'] ?? 0,
                        'last_updated' => $metrics['last_updated'] ?? time(),
                        'accesses_total' => $metrics['accesses_total'] ?? 0,
                        'accesses_by_type' => $metrics['accesses_by_type'] ?? []
                    ]
                ]
            ];

            $this->json($response);

        } catch (Exception $e) {
            $this->json(['error' => 'Erro ao obter métricas: ' . $e->getMessage()], 500);
        }
    }

    public function sendMetrics()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        try {
            // Executar o script de envio de métricas
            $scriptPath = __DIR__ . '/../../../cron/send_metrics.php';

            if (!file_exists($scriptPath)) {
                $this->json(['error' => 'Script de envio não encontrado'], 500);
                return;
            }

            // Executar o script via PHP
            $command = "php \"$scriptPath\"";
            $output = shell_exec($command);

            // Verificar se foi bem-sucedido baseado na saída
            if (strpos($output, 'Métricas enviadas com sucesso') !== false) {
                $this->json(['success' => true, 'message' => 'Métricas enviadas com sucesso']);
            } else {
                $this->json(['error' => 'Erro ao enviar métricas: ' . substr($output, 0, 200)], 500);
            }

        } catch (Exception $e) {
            $this->json(['error' => 'Erro ao enviar métricas: ' . $e->getMessage()], 500);
        }
    }
}
}
