<?php
/**
 * EducaTudo - Controller de Administracao (extraido de AdminController)
 */

require_once __DIR__ . '/../../Core/LayoutHelper.php';
require_once __DIR__ . '/AdminBaseController.php';

if (!class_exists('SchoolSettingsAdminController')) {
class SchoolSettingsAdminController extends AdminBaseController
{
    public function configuracoes()
    {
        $turmas = $this->db->fetchAll("SELECT * FROM turmas ORDER BY nome ASC");
        $materias = $this->db->fetchAll("SELECT * FROM materias ORDER BY nome ASC");
        $usuarios_admin = $this->db->fetchAll(
            "SELECT * FROM usuarios WHERE tipo = 'admin_escola' ORDER BY nome ASC"
        );
        $sliderConfig = $this->db->fetch(
            "SELECT config_value FROM config_layout WHERE config_key = :k LIMIT 1",
            ['k' => 'dashboard_slider_items']
        );
        $sliderItems = [];
        if (!empty($sliderConfig['config_value'])) {
            $decoded = json_decode((string) $sliderConfig['config_value'], true);
            if (is_array($decoded)) {
                $sliderItems = $decoded;
            }
        }
        
        $data = [
            'title' => 'Configurações - EducaTudo',
            'classes' => $turmas,
            'subjects' => $materias,
            'admin_users' => $usuarios_admin,
            'dashboard_slider_items' => $sliderItems,
            'csrf_token' => $this->generateCsrfToken(),
            'user' => $this->auth->getUser(),
            'current_page' => 'settings'
        ];
        
        $this->viewWithLayout('admin', 'admin/settings/index', $data);
    }

    public function salvarSlidersDashboard()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/settings');
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/settings');
            return;
        }

        $raw = trim((string) ($_POST['dashboard_slider_items'] ?? '[]'));
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $this->setFlashMessage('JSON de slides inválido.', 'error');
            $this->redirect('/admin/settings');
            return;
        }

        $sanitized = [];
        $files = $_FILES['slider_images'] ?? null;
        foreach ($decoded as $rowIndex => $item) {
            if (!is_array($item)) {
                continue;
            }
            $img = trim((string) ($item['image_url'] ?? ''));
            $link = trim((string) ($item['link_url'] ?? ''));
            $title = trim((string) ($item['title'] ?? ''));
            $actionType = trim((string) ($item['action_type'] ?? 'external'));
            $moduleKey = trim((string) ($item['module_key'] ?? ''));
            $active = !empty($item['active']) ? 1 : 0;
            if (!in_array($actionType, ['external', 'module'], true)) {
                $actionType = 'external';
            }
            $idx = (int) $rowIndex;
            if ($files && isset($files['error'][$idx]) && (int)$files['error'][$idx] === UPLOAD_ERR_OK) {
                $uploaded = $this->uploadSliderImageFromFile([
                    'name' => $files['name'][$idx] ?? '',
                    'type' => $files['type'][$idx] ?? '',
                    'tmp_name' => $files['tmp_name'][$idx] ?? '',
                    'error' => $files['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $files['size'][$idx] ?? 0,
                ]);
                if ($uploaded !== '') {
                    $img = $uploaded;
                }
            }
            if ($img === '') {
                continue;
            }
            $sanitized[] = [
                'image_url' => $img,
                'link_url' => $link,
                'title' => $title,
                'action_type' => $actionType,
                'module_key' => $moduleKey,
                'active' => $active,
            ];
        }

        $payload = json_encode($sanitized, JSON_UNESCAPED_UNICODE);
        $this->db->query(
            "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
            ['dashboard_slider_items', (string) $payload]
        );

        require_once __DIR__ . '/../../Core/RedisCache.php';
        RedisCache::delete('config_layout');
        RedisCache::delete('config_layout_' . ((int) ($_SESSION['tenant_id'] ?? 0)));

        $this->setFlashMessage('Slides do dashboard salvos com sucesso.', 'success');
        $this->redirect('/admin/settings');
    }

    private function uploadSliderImageFromFile(array $file): string
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            return '';
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return '';
        }
        if ((int)($file['size'] ?? 0) > 10 * 1024 * 1024) {
            return '';
        }
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo ? finfo_file($finfo, (string)($file['tmp_name'] ?? '')) : false;
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!$mimeReal || !in_array($mimeReal, $allowedMimes, true)) {
            return '';
        }

        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $fileName = 'dashboard_slider_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $ok = $media->put('layout', $fileName, (string)($file['tmp_name'] ?? ''), (string)($file['type'] ?? 'application/octet-stream'));
        if (!$ok) {
            return '';
        }
        return $media->isS3()
            ? rtrim((string)(defined('URL') ? URL : ''), '/') . '/media/serve?type=layout&key=' . rawurlencode($fileName)
            : $media->getDisplayUrl('layout', $fileName);
    }

    public function salvarModulos()
    {
        $user = $this->auth->getUser();
        if (!$user || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
        }
        
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $modules = $_POST['modules'] ?? [];
            $validValues = ['1', '0', '2'];

            // Geral: uma chave do form grava em várias config_keys (aluno + professor + admin)
            if (!class_exists('ModuloRegistry', false)) {
                require_once __DIR__ . '/../../Core/ModuloRegistry.php';
            }
            $geralToBackend = array_merge([
                'geral_planos_aula'    => ['aluno_planos_aula', 'professor_planos_aula'],
                'geral_jornada'        => ['jornadas', 'professor_jornadas'],
                'geral_provas'         => ['aluno_provas', 'professor_provas'],
                'geral_chat_professor' => ['chat_professor'],
                'geral_redacao_orientada' => ['redacao_configuravel', 'aluno_redacao_configuravel', 'professor_redacao_configuravel'],
                'geral_minicursos'     => ['aluno_minicursos'],
                'geral_aulas_online'   => ['aulas_online'],
            ], ModuloRegistry::masterGeralMap());

            // Chaves diretas (Professor + Aluno) + extras do ModuloRegistry (ex.: aluno_recuperacao)
            $directKeys = array_values(array_unique(array_merge([
                'professor_ai_agents', 'professor_gerar_slides',
                'educa_livros', 'educalabs', 'aluno_flashcards', 'exercicios', 'exercicios_ia',
                'ingles', 'redacoes', 'simulados', 'chat', 'aluno_caderno_novo', 'forum', 'drive', 'jogos',
                'educa_hits',
            ], array_keys(ModuloRegistry::masterAlunoExtras()))));

            $save = function ($configKey, $value) {
                $fallback = ModuloRegistry::featureDefault((string) $configKey);
                $value = in_array((string) $value, ['1', '0', '2'], true) ? (string) $value : $fallback;
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    ['module_' . $configKey, $value]
                );
            };

            foreach ($geralToBackend as $formKey => $backendKeys) {
                $raw = isset($modules[$formKey]) ? (string) $modules[$formKey] : '1';
                $value = in_array($raw, $validValues, true) ? $raw : '1';
                foreach ($backendKeys as $bk) {
                    $save($bk, $value);
                }
            }

            foreach ($directKeys as $m) {
                $fallback = ModuloRegistry::featureDefault((string) $m);
                $raw = isset($modules[$m]) ? (string) $modules[$m] : $fallback;
                $save($m, $raw);
            }

            $this->json(['success' => true, 'message' => 'Módulos atualizados com sucesso']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Salva ordem (drag-and-drop) e nomes customizados de itens do menu do aluno.
     * Grava em config_layout: menu_order_<grupo> (array JSON de chaves, na ordem
     * escolhida) e menu_labels (mapa JSON chave -> nome customizado, compartilhado
     * entre todos os grupos). Lido por src/app/Views/layouts/student.php.
     */
    public function salvarMenuOrder()
    {
        $user = $this->auth->getUser();
        if (!$user || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        try {
            $allowedGroups = ['estudo', 'colag', 'entretenimento', 'sistema'];
            $order = $_POST['order'] ?? [];
            $labels = $_POST['labels'] ?? [];

            $save = function (string $configKey, string $value) {
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    [$configKey, $value]
                );
            };

            foreach ($allowedGroups as $grupo) {
                $itens = $order[$grupo] ?? null;
                if (!is_array($itens)) {
                    continue;
                }
                $itens = array_values(array_filter(array_map(
                    static fn($v) => preg_replace('/[^a-z0-9_]/', '', strtolower((string) $v)),
                    $itens
                ), static fn($v) => $v !== ''));
                $save('menu_order_' . $grupo, json_encode($itens, JSON_UNESCAPED_UNICODE));
            }

            if (is_array($labels)) {
                $labelsLimpos = [];
                foreach ($labels as $chave => $valor) {
                    $chaveLimpa = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $chave));
                    $valorLimpo = trim((string) $valor);
                    if ($chaveLimpa !== '' && $valorLimpo !== '') {
                        $labelsLimpos[$chaveLimpa] = $valorLimpo;
                    }
                }
                $save('menu_labels', json_encode($labelsLimpos, JSON_UNESCAPED_UNICODE));
            }

            $this->json(['success' => true, 'message' => 'Menu atualizado com sucesso']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function layout()
    {
        $user = $this->auth->getUser();
        if (!$user || $user['perfil_admin'] !== 'dev') {
            // Debug: mostrar informações do usuário
            error_log("Layout access denied. User: " . json_encode($user));
            $this->redirect('/admin/dashboard');
        }
        
        // Buscar configurações atuais (URLs de mídia no host da escola, igual ao sidebar)
        $configs = $this->db->fetchAll("SELECT * FROM config_layout ORDER BY config_key");
        $layoutConfig = [];
        foreach ($configs as $config) {
            $key = (string) ($config['config_key'] ?? '');
            $value = $config['config_value'] ?? '';
            if (is_string($value) && substr($key, -4) === '_url') {
                $value = LayoutHelper::toTenantRelativeMediaUrl((string) $value);
            }
            $layoutConfig[$key] = $value;
        }
        
        $data = [
            'title' => 'Layout do Sistema - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'dev',
            'config_layout' => $layoutConfig,
            'csrf_token' => $this->generateCsrfToken()
        ];
        
        $this->viewWithLayout('admin', 'admin/dev/layout', $data);
    }

    public function saveLayout()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
        }
        
        // Verificar CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $configs = $_POST['config'] ?? [];
            if (!is_array($configs)) {
                $configs = [];
            }

            $tamanhosPermitidos = ['1', '1.25', '1.5', '2'];
            foreach (['logo_size_navbar', 'logo_size_login'] as $chaveTamanho) {
                if (!array_key_exists($chaveTamanho, $configs)) {
                    continue;
                }
                $valor = (string) $configs[$chaveTamanho];
                if (!in_array($valor, $tamanhosPermitidos, true)) {
                    unset($configs[$chaveTamanho]);
                }
            }
            
            foreach ($configs as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (:config_key, :config_value)
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    ['config_key' => $key, 'config_value' => $value]
                );
            }

            LayoutHelper::invalidateCache();

            $this->json(['success' => true, 'message' => 'Configurações salvas com sucesso!']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function salvarEmailConfig()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
        }
        
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $configs = [
                'email_smtp_host' => $_POST['email_smtp_host'] ?? '',
                'email_smtp_port' => $_POST['email_smtp_port'] ?? '587',
                'email_smtp_secure' => $_POST['email_smtp_secure'] ?? 'tls',
                'email_smtp_username' => $_POST['email_smtp_username'] ?? '',
                'email_smtp_password' => $_POST['email_smtp_password'] ?? '',
                'email_from_email' => $_POST['email_from_email'] ?? '',
                'email_from_name' => $_POST['email_from_name'] ?? 'EducaTudo',
                'email_reply_to' => $_POST['email_reply_to'] ?? ''
            ];
            
            foreach ($configs as $key => $value) {
                $this->db->query(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                    [$key, $value]
                );
            }
            
            $this->json(['success' => true, 'message' => 'Configurações de e-mail salvas com sucesso!']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function uploadImage()
    {
        $user = $this->auth->getUser();
        if (!$user || !isset($user['perfil_admin']) || $user['perfil_admin'] !== 'dev') {
            $this->json(['error' => 'Acesso negado'], 403);
        }
        
        // Verificar CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $type = $_POST['type'] ?? ''; // 'logo', 'logo-1x1', ..., 'pwa_icon' (apenas retorna URL, não grava em config)
            $allowedTypes = ['logo', 'logo-1x1', 'logo-horizontal', 'logo-white', 'logo-horizontal-white', 'cover', 'pwa_icon'];
            
            if (!in_array($type, $allowedTypes)) {
                throw new Exception('Tipo de upload inválido');
            }
            
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erro no upload da imagem');
            }
            
            $file = $_FILES['image'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedExtensions, true)) {
                throw new Exception('Tipo de arquivo não permitido. Use: ' . implode(', ', $allowedExtensions));
            }
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeReal = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
            if ($finfo) {
                finfo_close($finfo);
            }
            if (!$mimeReal || !in_array($mimeReal, $allowedMimes, true)) {
                throw new Exception('Tipo de arquivo não permitido. Use: ' . implode(', ', $allowedExtensions));
            }
            
            $fileName = $type . '_' . time() . '.' . $fileExtension;
            $contentType = $file['type'] ?? null;
            require_once __DIR__ . '/../../Services/MediaStorageService.php';
            $media = new MediaStorageService($this->config);
            if (!$media->put('layout', $fileName, $file['tmp_name'], $contentType)) {
                throw new Exception('Erro ao salvar arquivo');
            }
            // layout sempre vai para storage local; getDisplayUrl inclui tenant quando necessário
            $url = LayoutHelper::toTenantRelativeMediaUrl($media->getDisplayUrl('layout', $fileName));
            if ($url === '') {
                throw new Exception('Erro ao gerar URL da imagem');
            }
            
            // pwa_icon: apenas retorna a URL (ícone PWA é salvo ao clicar em "Salvar PWA")
            if ($type === 'pwa_icon') {
                $this->json(['success' => true, 'message' => 'Imagem enviada. Salve o formulário PWA para aplicar.', 'url' => $url]);
                return;
            }
            
            // Mapear tipo para chave de configuração
            $configKeyMap = [
                'logo' => 'logo_url',
                'logo-1x1' => 'logo_1x1_url',
                'logo-horizontal' => 'logo_horizontal_url',
                'logo-white' => 'logo_white_url',
                'logo-horizontal-white' => 'logo_horizontal_white_url',
                'cover' => 'login_cover_url'
            ];
            
            $configKey = $configKeyMap[$type] ?? 'logo_url';
            
            $this->db->query(
                "INSERT INTO config_layout (config_key, config_value) VALUES (:config_key, :config_value)
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                ['config_key' => $configKey, 'config_value' => $url]
            );

            LayoutHelper::invalidateCache();
            
            $this->json([
                'success' => true, 
                'message' => 'Imagem enviada com sucesso!',
                'url' => $url,
                'config_key' => $configKey
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function turmas()
    {
        $turmas = $this->db->fetchAll(
            "SELECT t.*, 
                (SELECT COUNT(*) FROM alunos WHERE turma_id = t.id AND ativo = 1) as total_alunos
             FROM turmas t
             ORDER BY t.ano_letivo DESC, t.nome ASC"
        );
        
        $data = [
            'title' => 'Gestão de Turmas - EducaTudo',
            'classes' => $turmas,
            'user' => $this->auth->getUser(),
            'current_page' => 'classes'
        ];
        
        $this->viewWithLayout('admin', 'admin/classes/index', $data);
    }

    // Ações de "Matérias" migraram para Admin/ComponenteCurricularAdminController
    // (módulo renomeado para "Componentes Curriculares" — ver .claude/docs/nomenclatura.md).

    public function creditosPacotes()
    {
        $user = $this->auth->getUser();
        try {
            $pacotes = $this->db->fetchAll(
                "SELECT id, nome, creditos, valor_centavos, ativo
                 FROM pacotes_creditos
                 ORDER BY ativo DESC, id DESC"
            );
        } catch (Exception $e) {
            $pacotes = [];
        }

        $data = [
            'title' => 'Pacotes de Créditos - EducaTudo',
            'user' => $user,
            'pacotes' => $pacotes,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'creditos_pacotes',
        ];

        $this->viewWithLayout('admin', 'admin/creditos/pacotes', $data);
    }

    public function creditosPacotesSalvar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/creditos/pacotes');
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/creditos/pacotes');
            return;
        }

        require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';
        $nome = trim((string) ($_POST['pacote_nome'] ?? ''));
        $creditos = \CreditosDecimalHelper::parsePost($_POST['pacote_creditos'] ?? 0);
        $valorCentavos = max(0, (int) ($_POST['pacote_valor_centavos'] ?? 0));

        if ($nome === '' || $creditos <= 0 || $valorCentavos <= 0) {
            $this->setFlashMessage('Informe nome, créditos e valor do pacote.', 'error');
            $this->redirect('/admin/creditos/pacotes');
            return;
        }

        try {
            $this->db->insert(
                "INSERT INTO pacotes_creditos (nome, creditos, valor_centavos, ativo, created_at, updated_at)
                 VALUES (:nome, :creditos, :valor_centavos, 1, NOW(), NOW())",
                [
                    'nome' => $nome,
                    'creditos' => $creditos,
                    'valor_centavos' => $valorCentavos,
                ]
            );
            $this->setFlashMessage('Pacote cadastrado com sucesso.', 'success');
        } catch (Exception $e) {
            $this->setFlashMessage('Erro ao salvar pacote: ' . $e->getMessage(), 'error');
        }

        $this->redirect('/admin/creditos/pacotes');
    }

    public function creditosPacotesToggle()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/creditos/pacotes');
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/creditos/pacotes');
            return;
        }

        $pacoteId = (int) ($_POST['pacote_id'] ?? 0);
        $novoAtivo = (string) ($_POST['pacote_ativo'] ?? '0') === '1' ? 1 : 0;
        if ($pacoteId <= 0) {
            $this->setFlashMessage('Pacote inválido.', 'error');
            $this->redirect('/admin/creditos/pacotes');
            return;
        }

        try {
            $this->db->query(
                "UPDATE pacotes_creditos SET ativo = :ativo, updated_at = NOW() WHERE id = :id",
                ['ativo' => $novoAtivo, 'id' => $pacoteId]
            );
            $this->setFlashMessage('Status do pacote atualizado.', 'success');
        } catch (Exception $e) {
            $this->setFlashMessage('Erro ao atualizar pacote: ' . $e->getMessage(), 'error');
        }

        $this->redirect('/admin/creditos/pacotes');
    }
}
}
