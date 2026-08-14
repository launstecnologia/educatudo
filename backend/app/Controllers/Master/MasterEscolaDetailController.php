<?php

if (!class_exists('MasterEscolaDetailController')) {

class MasterEscolaDetailController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';

    private static $MODULOS_GERAL_MAP = [
        'geral_planos_aula'       => ['aluno_planos_aula', 'professor_planos_aula'],
        'geral_apostilas'         => ['aluno_apostilas', 'professor_apostilas'],
        // geral_arquivos: vem do ModuloRegistry (app/Modulos/arquivos/manifest.php)
        'geral_jornada'           => ['jornadas', 'professor_jornadas'],
        'geral_redacao_orientada' => ['redacao_configuravel', 'aluno_redacao_configuravel', 'professor_redacao_configuravel'],
        'geral_provas'            => ['aluno_provas', 'professor_provas'],
        'geral_mural_recados'     => ['mural_recados'],
        'geral_links_uteis'       => ['aluno_links_uteis', 'professor_links_uteis'],
        'geral_chat_professor'    => ['chat_professor'],
        'geral_ead'               => ['ead'],
        'geral_inclusao'          => ['inclusao'],
        'geral_aulas_online'      => ['aulas_online'],
    ];

    private static $MODULOS_GERAL_LABELS = [
        'geral_planos_aula'       => 'Plano de Aula',
        'geral_apostilas'         => 'Minha Apostila',
        'geral_jornada'           => 'Jornada do Aluno',
        'geral_redacao_orientada' => 'Jornada da Redação',
        'geral_provas'            => 'Provas',
        'geral_mural_recados'     => 'Mural de Recado',
        'geral_links_uteis'       => 'Links Úteis',
        'geral_chat_professor'    => 'Chat c/ Professor',
        'geral_ead'               => 'EAD / AVA (Minicursos)',
        'geral_inclusao'          => 'EducaInclui (Avaliação Adaptativa)',
        'geral_aulas_online'      => 'Aulas Online',
    ];

    private function getModulosGeralMap(): array
    {
        if (!class_exists('ModuloRegistry', false)) {
            require_once __DIR__ . '/../../Core/ModuloRegistry.php';
        }
        return array_merge(self::$MODULOS_GERAL_MAP, ModuloRegistry::masterGeralMap());
    }

    private function getModulosGeralLabels(): array
    {
        if (!class_exists('ModuloRegistry', false)) {
            require_once __DIR__ . '/../../Core/ModuloRegistry.php';
        }
        return array_merge(self::$MODULOS_GERAL_LABELS, ModuloRegistry::masterGeralLabels());
    }

    private function getModulosAluno(): array
    {
        if (!class_exists('ModuloRegistry', false)) {
            require_once __DIR__ . '/../../Core/ModuloRegistry.php';
        }
        return array_merge(self::$MODULOS_ALUNO, ModuloRegistry::masterAlunoExtras());
    }

    private static $MODULOS_PROFESSOR = [
        'professor_ai_agents'       => 'Agente de IA',
        'professor_gerar_slides'    => 'Slides',
        'professor_redacao_livre'   => 'Redação Livre',
        'professor_notifications'   => 'Notificações',
    ];

    private static $MODULOS_ALUNO = [
        'aluno_minicursos'   => 'Mini Cursos',
        'educa_livros'       => 'EducaLivro',
        'aluno_flashcards'   => 'FlashCard',
        'exercicios_ia'      => 'Exercícios por IA',
        'exercicios'         => 'Exercícios por Banco de Dados',
        'redacoes'           => 'Redação',
        'chat'               => 'Tudinha (chat)',
        'forum'              => 'Fórum',
        'jogos'              => 'Games',
        'educalabs'          => 'EducaLabs',
        'ingles'             => 'Ingles',
        'simulados'          => 'Simulados',
        'aluno_caderno_novo' => 'Meu Caderno',
        // drive: ModuloRegistry (app/Modulos/drive/manifest.php)
        'educa_hits'         => 'EducaHits',
        'vlibras'            => 'VLibras (Libras)',
    ];

    private static $MODULE_VALID_VALUES = ['1', '0'];
    private static $RELEASE_CHANNEL_VALUES = ['stable', 'canary'];

    private function getReleaseCatalog(): array
    {
        $path = dirname(__DIR__, 3) . '/config/release_versions.php';
        if (!is_file($path)) {
            return [];
        }

        $rows = require $path;
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = trim((string)($row['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            $label = trim((string)($row['label'] ?? $value));
            $commit = trim((string)($row['commit'] ?? ''));
            if ($commit !== '' && preg_match('/^[a-f0-9]{7,40}$/i', $commit) !== 1) {
                $commit = '';
            }
            $out[] = ['value' => $value, 'label' => $label, 'commit' => $commit];
        }

        return $out;
    }

    public function __construct()
    {
        parent::__construct();
    }

    private function parseMoneyToCentavos($value): int
    {
        require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';
        $reais = CreditosDecimalHelper::parsePost($value);
        return (int) round($reais * 100);
    }

    private function requireMaster(): void
    {
        if (empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            header('Location: ' . URL . '/master');
            exit;
        }
    }

    private function getEscolaOrFail(int $id): array
    {
        $db = Database::getInstance();
        $escola = $db->query(
            "SELECT e.*, b.host AS db_host, b.porta AS db_porta, b.nome_banco AS db_nome_banco,
                    b.usuario AS db_usuario, b.senha_criptografada AS db_senha
             FROM escolas e
             LEFT JOIN config_escolas_banco b ON b.escola_id = e.id
             WHERE e.id = ?",
            [$id]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$escola) {
            $this->setFlashMessage('Escola não encontrada.', 'error');
            header('Location: ' . URL . '/master/escolas');
            exit;
        }

        return $escola;
    }

    private function getTenantPdo(int $escolaId): ?PDO
    {
        $db = Database::getInstance();
        $banco = $db->fetch(
            "SELECT host, porta, nome_banco, usuario, senha_criptografada FROM config_escolas_banco WHERE escola_id = ?",
            [$escolaId]
        );
        return $banco ? $this->connectTenant($banco) : null;
    }

    private function connectTenant(array $banco): ?PDO
    {
        $host = $banco['db_host'] ?? $banco['host'] ?? null;
        $port = (int) ($banco['db_porta'] ?? $banco['porta'] ?? 3306);
        $dbName = $banco['db_nome_banco'] ?? $banco['nome_banco'] ?? null;
        $user = $banco['db_usuario'] ?? $banco['usuario'] ?? null;
        $pass = MasterSecretVault::decryptDbPassword($banco['db_senha'] ?? $banco['senha_criptografada'] ?? '');

        if (!$host || !$dbName || !$user) {
            return null;
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("SET time_zone = '-03:00'");
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            return $pdo;
        } catch (PDOException $e) {
            return null;
        }
    }

    private function ensureTenantWalletColumns(PDO $pdo): void
    {
        $saldoEscola = $pdo->query("SHOW COLUMNS FROM carteira_usuarios LIKE 'saldo_escola'")->fetch(PDO::FETCH_ASSOC);
        if (!$saldoEscola) {
            $pdo->exec("ALTER TABLE carteira_usuarios ADD COLUMN saldo_escola DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER saldo");
            $pdo->exec("UPDATE carteira_usuarios SET saldo_escola = COALESCE(saldo, 0)");
        }

        $saldoComprado = $pdo->query("SHOW COLUMNS FROM carteira_usuarios LIKE 'saldo_comprado'")->fetch(PDO::FETCH_ASSOC);
        if (!$saldoComprado) {
            $pdo->exec("ALTER TABLE carteira_usuarios ADD COLUMN saldo_comprado DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER saldo_escola");
        }

        $saldoOrigem = $pdo->query("SHOW COLUMNS FROM carteira_movimentacoes LIKE 'saldo_origem'")->fetch(PDO::FETCH_ASSOC);
        if (!$saldoOrigem) {
            $pdo->exec("ALTER TABLE carteira_movimentacoes ADD COLUMN saldo_origem ENUM('escola','comprado','misto') NULL DEFAULT NULL AFTER tipo");
            $pdo->exec(
                "UPDATE carteira_movimentacoes
                 SET saldo_origem = CASE
                    WHEN tipo IN ('compra', 'recarga_plano') THEN 'comprado'
                    WHEN tipo IN ('recarga_mensal', 'recarga_inicial', 'cortesia') THEN 'escola'
                    WHEN tipo IN ('consumo', 'estorno') THEN 'misto'
                    ELSE NULL
                 END
                 WHERE saldo_origem IS NULL"
            );
        }

        $observacao = $pdo->query("SHOW COLUMNS FROM carteira_movimentacoes LIKE 'observacao'")->fetch(PDO::FETCH_ASSOC);
        if (!$observacao) {
            $pdo->exec("ALTER TABLE carteira_movimentacoes ADD COLUMN observacao TEXT NULL AFTER referencia_id");
        }
    }

    private function getTenantWalletSaldos(PDO $pdo, string $userType, int $userId): array
    {
        $st = $pdo->prepare("SELECT saldo, saldo_escola, saldo_comprado FROM carteira_usuarios WHERE user_type = ? AND user_id = ?");
        $st->execute([$userType, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->prepare(
                "INSERT INTO carteira_usuarios (user_type, user_id, saldo, saldo_escola, saldo_comprado) VALUES (?, ?, 0, 0, 0)"
            )->execute([$userType, $userId]);
            return ['saldo' => 0.0, 'saldo_escola' => 0.0, 'saldo_comprado' => 0.0];
        }

        require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';
        return [
            'saldo' => CreditosDecimalHelper::fromSignedScalar($row['saldo'] ?? 0, 0.0),
            'saldo_escola' => CreditosDecimalHelper::fromSignedScalar($row['saldo_escola'] ?? 0, 0.0),
            'saldo_comprado' => CreditosDecimalHelper::fromSignedScalar($row['saldo_comprado'] ?? 0, 0.0),
        ];
    }

    private function saveTenantWalletSaldos(PDO $pdo, string $userType, int $userId, float $saldoEscola, float $saldoComprado): void
    {
        require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';
        $saldoEscola = CreditosDecimalHelper::fromScalar($saldoEscola, 0.0);
        $saldoComprado = CreditosDecimalHelper::fromScalar($saldoComprado, 0.0);
        $saldoTotal = CreditosDecimalHelper::fromScalar($saldoEscola + $saldoComprado, 0.0);
        $pdo->prepare(
            "UPDATE carteira_usuarios
             SET saldo = ?, saldo_escola = ?, saldo_comprado = ?, updated_at = NOW()
             WHERE user_type = ? AND user_id = ?"
        )->execute([$saldoTotal, $saldoEscola, $saldoComprado, $userType, $userId]);
    }

    private function renovarSaldoEscolaUsuarios(
        PDO $pdo,
        string $userType,
        ?string $tableName,
        float $novoSaldoEscola,
        ?int $userIdUnico = null
    ): int {
        require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';
        $novoSaldoEscola = CreditosDecimalHelper::fromScalar($novoSaldoEscola, 0.0);

        if ($userIdUnico !== null) {
            $ids = [$userIdUnico];
        } elseif ($tableName !== null && $tableName !== '') {
            $ids = $pdo->query("SELECT id FROM {$tableName} WHERE ativo = 1")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            return 0;
        }

        $renovados = 0;
        $insertMov = $pdo->prepare(
            "INSERT INTO carteira_movimentacoes
                (user_type, user_id, tipo, saldo_origem, valor, modulo_key, referencia_id, observacao)
             VALUES (?, ?, 'recarga_mensal', 'escola', ?, NULL, NULL, ?)"
        );

        foreach ($ids as $rawId) {
            $userId = (int) $rawId;
            $wallet = $this->getTenantWalletSaldos($pdo, $userType, $userId);
            $saldoComprado = (float) ($wallet['saldo_comprado'] ?? 0.0);
            $saldoEscolaAtual = (float) ($wallet['saldo_escola'] ?? 0.0);
            $delta = round($novoSaldoEscola - $saldoEscolaAtual, 4);

            $this->saveTenantWalletSaldos($pdo, $userType, $userId, $novoSaldoEscola, $saldoComprado);

            if (abs($delta) > 0.00009) {
                $observacao = sprintf(
                    'Renovação manual do saldo da escola para %s créditos.',
                    CreditosDecimalHelper::formatDisplay($novoSaldoEscola)
                );
                $insertMov->execute([$userType, $userId, $delta, $observacao]);
            }
            $renovados++;
        }

        return $renovados;
    }

    private function getLayoutConfig(int $escolaId): array
    {
        $db = Database::getInstance();
        $rows = $db->query(
            "SELECT config_key, config_value FROM config_escolas_layout WHERE escola_id = ?",
            [$escolaId]
        )->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $out[$r['config_key']] = $r['config_value'];
        }
        return $out;
    }

    /**
     * Lê config_layout diretamente do banco da escola (tenant).
     */
    private function getTenantLayoutConfig(array $escola): array
    {
        $pdo = $this->connectTenant($escola);
        if (!$pdo) {
            return [];
        }

        try {
            $rows = $pdo->query("SELECT config_key, config_value FROM config_layout")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $k = (string)($r['config_key'] ?? '');
            if ($k === '') {
                continue;
            }
            $out[$k] = (string)($r['config_value'] ?? '');
        }
        return $out;
    }

    /**
     * Master usa config_escolas_layout; se faltar valor, faz fallback para config_layout do tenant.
     */
    private function getMergedLayoutConfig(int $escolaId, array $escola): array
    {
        $master = $this->getLayoutConfig($escolaId);
        $tenant = $this->getTenantLayoutConfig($escola);

        if (empty($tenant)) {
            return $master;
        }

        foreach ($tenant as $key => $value) {
            $current = $master[$key] ?? null;
            if ($current === null || trim((string)$current) === '') {
                $master[$key] = $value;
            }
        }

        // Compatibilidade: prompts antigos salvos com chaves "prompt_gerar_*"
        $promptAliases = [
            'prompt_tema' => 'prompt_gerar_tema_redacao',
            'prompt_correcao' => 'prompt_corrigir_redacao',
            'prompt_ocr' => 'prompt_transcrever_imagem',
            'prompt_prova' => 'prompt_gerar_prova',
            'prompt_exercicios_jornada' => 'prompt_gerar_exercicios_jornada',
            'prompt_exercicios_personalizados' => 'prompt_gerar_exercicios_personalizados',
        ];
        foreach ($promptAliases as $uiKey => $legacyKey) {
            $uiValue = trim((string)($master[$uiKey] ?? ''));
            if ($uiValue === '') {
                $legacyValue = trim((string)($master[$legacyKey] ?? ''));
                if ($legacyValue !== '') {
                    $master[$uiKey] = $legacyValue;
                }
            }
        }

        return $master;
    }

    private function getEscolaSlug(int $escolaId): string
    {
        $db = Database::getInstance();
        $row = $db->query("SELECT slug FROM escolas WHERE id = ? LIMIT 1", [$escolaId])->fetch(PDO::FETCH_ASSOC);
        return strtolower(trim((string) ($row['slug'] ?? '')));
    }

    private function setLayoutConfig(int $escolaId, array $config): void
    {
        $db = Database::getInstance();
        foreach ($config as $key => $value) {
            if ($key === '' || strlen($key) > 128) continue;
            $db->query(
                "INSERT INTO config_escolas_layout (escola_id, config_key, config_value) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)",
                [$escolaId, $key, (string) $value]
            );
        }
    }

    private function removeLayoutConfigKey(int $escolaId, string $key): void
    {
        if ($key === '') return;
        $db = Database::getInstance();
        $db->query("DELETE FROM config_escolas_layout WHERE escola_id = ? AND config_key = ?", [$escolaId, $key]);
    }


    private function syncLayoutToTenant(int $escolaId): void
    {
        $db = Database::getInstance();
        $layout = $this->getLayoutConfig($escolaId);
        if (empty($layout)) return;

        $slug = $this->getEscolaSlug($escolaId);
        if ($slug !== '') {
            $layout['tenant_slug'] = $slug;
            $this->setLayoutConfig($escolaId, ['tenant_slug' => $slug]);
        }

        $banco = $db->query(
            "SELECT host, porta, nome_banco, usuario, senha_criptografada FROM config_escolas_banco WHERE escola_id = ?",
            [$escolaId]
        )->fetch(PDO::FETCH_ASSOC);

        if ($banco) {
            $port = (int) ($banco['porta'] ?? 3306);
            $dsn = "mysql:host={$banco['host']};port={$port};dbname={$banco['nome_banco']};charset=utf8mb4";
            try {
                $pdo = new PDO($dsn, $banco['usuario'], MasterSecretVault::decryptDbPassword($banco['senha_criptografada'] ?? ''), [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                return;
            }
        } else {
            $pdo = $db->getPdo();
            if (!$pdo) return;
        }

        foreach ($layout as $config_key => $config_value) {
            try {
                $pdo->prepare(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)"
                )->execute([$config_key, $config_value]);
            } catch (PDOException $e) {
                continue;
            }
        }
        // Invalidar cache de layout do tenant para o professor ver módulos atualizados
        require_once __DIR__ . '/../../Core/RedisCache.php';
        RedisCache::delete('config_layout_' . $escolaId);
        RedisCache::delete('config_layout');
    }

    private function renderDetail(int $escolaId, string $section, string $sectionView, array $extraData = []): void
    {
        $escola = $this->getEscolaOrFail($escolaId);
        $layoutConfig = $this->getMergedLayoutConfig($escolaId, $escola);

        $data = array_merge([
            'title'           => $escola['nome'] . ' - Painel Master',
            'page_title'      => $escola['nome'],
            'current_page'    => 'escolas',
            'current_section' => $section,
            'section_view'    => $sectionView,
            'master_nome'     => $_SESSION['master_user_nome'] ?? 'Admin',
            'escola'          => $escola,
            'escola_id'       => $escolaId,
            'layout_config'   => $layoutConfig,
            'flash'           => $this->getFlashMessage(),
            'csrf_token'      => $this->generateCsrfToken(),
        ], $extraData);

        // Requisição do offcanvas (fetch): devolve só o fragmento da seção, sem o layout completo.
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fragment') {
            header('Cache-Control: no-store');
            $this->view('master/escolas/detail/' . $sectionView, $data);
            return;
        }

        $this->viewWithLayout('master', 'master/escolas/detail-layout', $data);
    }

    private function uploadLayoutImage(int $escolaId, string $fieldName): ?string
    {
        $map = [
            'layout_logo_upload'        => 'logo',
            'layout_logo_1x1_upload'    => 'logo_1x1',
            'layout_logo_white_upload'  => 'logo_white',
            'layout_logo_horizontal_white_upload' => 'logo_horizontal_white',
            'layout_login_cover_upload' => 'login_cover',
        ];
        if (!isset($map[$fieldName])) {
            return null;
        }

        $file = $_FILES[$fieldName] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            return null;
        }

        $maxSize = 10 * 1024 * 1024; // 10 MB (capas de login costumam ser maiores)
        if (($file['size'] ?? 0) > $maxSize) {
            return null;
        }
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!$mimeReal || !in_array($mimeReal, $allowedMimes, true)) {
            return null;
        }

        $key = 'escola_' . $escolaId . '_' . $map[$fieldName] . '_' . time() . '.' . $ext;
        $contentType = $file['type'] ?? null;

        if (!class_exists('MediaStorageService')) {
            require_once __DIR__ . '/../../Services/MediaStorageService.php';
        }

        $config = $this->config;
        $slug = $this->getEscolaSlug($escolaId);
        if ($slug !== '') {
            $config['school'] = array_merge($config['school'] ?? [], ['code' => $slug]);
            $config['tenant'] = array_merge($config['tenant'] ?? [], ['slug' => $slug]);
            $config['media'] = array_merge($config['media'] ?? [], ['tenant_prefix' => true]);
        } else {
            // Escola sem slug: garantir que o upload vá para layout/key (sem prefixo de outro tenant).
            $config['school'] = array_merge($config['school'] ?? [], ['code' => '']);
            $config['tenant'] = array_merge($config['tenant'] ?? [], ['slug' => '']);
            $config['media'] = array_merge($config['media'] ?? [], ['tenant_prefix' => false]);
        }
        // URL pública no domínio da escola (não no master), para login/branding funcionarem sem sessão.
        $escolaRow = $this->getEscolaOrFail($escolaId);
        $dominio = trim((string) ($escolaRow['dominio'] ?? ''));
        if ($dominio !== '') {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                || ((string) ($_SERVER['REQUEST_SCHEME'] ?? '') === 'https');
            $protocol = $https ? 'https' : 'http';
            $folder = defined('FOLDER') ? (string) FOLDER : '';
            $config['app'] = array_merge($config['app'] ?? [], [
                'url' => rtrim($protocol . '://' . $dominio . $folder, '/'),
            ]);
        }
        $media = new MediaStorageService($config);

        if (!$media->put('layout', $key, $file['tmp_name'], $contentType)) {
            error_log('MasterEscolaDetailController uploadLayoutImage: put(layout) falhou para escola_id=' . $escolaId . ' field=' . $fieldName . '. Verifique permissões da pasta storage/files no servidor.');
            return null;
        }

        // Layout é sempre local (storage/files/{slug}/layout)
        return $media->getDisplayUrl('layout', $key);
    }

    // ─── Public methods ──────────────────────────────────────────────

    public function visaoGeral($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        $escola = $this->getEscolaOrFail($id);
        $pdo = $this->connectTenant($escola);

        $stats = [
            'alunos'      => 0,
            'professores' => 0,
            'admins'      => 0,
            'turmas'      => 0,
            'materias'    => 0,
        ];
        $tenantOk = false;

        if ($pdo) {
            $tenantOk = true;
            try {
                $stats['alunos'] = (int) $pdo->query("SELECT COUNT(*) FROM alunos WHERE ativo = 1")->fetchColumn();
            } catch (PDOException $e) {}
            try {
                $stats['professores'] = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'professor' AND ativo = 1")->fetchColumn();
            } catch (PDOException $e) {}
            try {
                $stats['admins'] = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'admin_escola'")->fetchColumn();
            } catch (PDOException $e) {}
            try {
                $stats['turmas'] = (int) $pdo->query("SELECT COUNT(*) FROM turmas WHERE ativo = 1")->fetchColumn();
            } catch (PDOException $e) {}
            try {
                $stats['materias'] = (int) $pdo->query("SELECT COUNT(*) FROM materias")->fetchColumn();
            } catch (PDOException $e) {}
        }

        $db = Database::getInstance();
        $migrations = $db->query(
            "SELECT * FROM migrations_escolas WHERE escola_id = ? ORDER BY executed_at DESC",
            [$id]
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->renderDetail($id, 'visao-geral', 'visao-geral', [
            'stats'      => $stats,
            'tenant_ok'  => $tenantOk,
            'migrations' => $migrations,
        ]);
    }

    /**
     * Gera token e redireciona para o domínio da escola para "entrar como" o usuário (admin, professor ou aluno).
     * GET: escola_id, tipo=admin|professor|aluno, id=(id do usuário na tabela correspondente)
     */
    public function entrarComo()
    {
        $this->requireMaster();
        $escolaId = (int) ($_GET['escola_id'] ?? 0);
        $tipo = trim((string) ($_GET['tipo'] ?? ''));
        $userId = (int) ($_GET['id'] ?? 0);

        if ($escolaId < 1 || $userId < 1 || !in_array($tipo, ['admin', 'professor', 'aluno'], true)) {
            $this->setFlashMessage('Parâmetros inválidos para Entrar como.', 'error');
            header('Location: ' . URL . '/master/escolas');
            exit;
        }

        $secret = $this->config['security']['entra_como_secret'] ?? '';
        if ($secret === '') {
            $this->setFlashMessage('Não foi possível gerar o token. Verifique a configuração do banco no .env.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $escolaId . '/detalhes');
            exit;
        }

        $escola = $this->getEscolaOrFail($escolaId);
        $dominio = trim((string) ($escola['dominio'] ?? ''));
        if ($dominio === '') {
            $this->setFlashMessage('Configure o domínio da escola para usar Entrar como.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $escolaId . '/detalhes');
            exit;
        }

        $pdo = $this->connectTenant($escola);
        if (!$pdo) {
            $this->setFlashMessage('Não foi possível conectar ao banco da escola.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $escolaId . '/detalhes');
            exit;
        }

        $user = null;
        try {
            if ($tipo === 'admin') {
                $stmt = $pdo->prepare("SELECT id, nome, email, perfil_admin, avatar_url FROM usuarios WHERE id = ? AND tipo = 'admin_escola' LIMIT 1");
                $stmt->execute([$userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $user = ['id' => (int) $row['id'], 'nome' => $row['nome'], 'email' => $row['email'], 'perfil_admin' => $row['perfil_admin'] ?? 'admin', 'avatar_url' => $row['avatar_url'] ?? null, 'tipo' => 'admin'];
                }
            } elseif ($tipo === 'professor') {
                $stmt = $pdo->prepare("SELECT p.id, p.nome, p.email FROM professores p WHERE p.id = ? AND p.ativo = 1 LIMIT 1");
                $stmt->execute([$userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $user = ['id' => (int) $row['id'], 'nome' => $row['nome'], 'email' => $row['email'], 'tipo' => 'professor'];
                }
            } else {
                $stmt = $pdo->prepare("SELECT a.id, a.nome, a.email, a.ra, a.turma_id, a.serie, t.nome AS turma_nome FROM alunos a LEFT JOIN turmas t ON a.turma_id = t.id WHERE a.id = ? AND a.ativo = 1 LIMIT 1");
                $stmt->execute([$userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $user = ['id' => (int) $row['id'], 'nome' => $row['nome'], 'email' => $row['email'], 'ra' => $row['ra'] ?? '', 'turma_id' => $row['turma_id'], 'turma_nome' => $row['turma_nome'] ?? '', 'serie' => $row['serie'] ?? '', 'tipo' => 'aluno'];
                }
            }
        } catch (PDOException $e) {
            $this->setFlashMessage('Erro ao buscar usuário.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $escolaId . '/detalhes');
            exit;
        }

        if (!$user) {
            $this->setFlashMessage('Usuário não encontrado.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $escolaId . '/detalhes');
            exit;
        }

        $exp = time() + 300;
        // jti = nonce único; o consumo marca como usado (Redis) para impedir replay do token.
        $jti = bin2hex(random_bytes(16));
        $payload = json_encode(['escola_id' => $escolaId, 'tipo' => $tipo, 'user_id' => $user['id'], 'exp' => $exp, 'jti' => $jti]);
        $sig = hash_hmac('sha256', $payload, $secret, true);
        $token = strtr(base64_encode($payload), '+/', '-_') . '.' . strtr(base64_encode($sig), '+/', '-_');

        // Auditoria: registra qual admin master gerou acesso "Entrar como" e para quem.
        if (!class_exists('Logger')) {
            require_once __DIR__ . '/../../Core/Logger.php';
        }
        Logger::warning('Master gerou acesso "Entrar como"', [
            'master_user_id' => $_SESSION['master_user_id'] ?? null,
            'master_user_nome' => $_SESSION['master_user_nome'] ?? null,
            'escola_id' => $escolaId,
            'tipo_alvo' => $tipo,
            'user_id_alvo' => (int) $user['id'],
            'jti' => $jti,
            'exp' => $exp,
        ], 'seguranca');

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $folder = defined('FOLDER') ? FOLDER : '';
        $base = $protocol . '://' . $dominio . $folder;
        $url = rtrim($base, '/') . '/auth/entrar-como?token=' . rawurlencode($token);
        header('Location: ' . $url);
        exit;
    }

    public function usuarios($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        $escola = $this->getEscolaOrFail($id);
        $pdo = $this->connectTenant($escola);

        $perPage = 20;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filtroBusca = trim((string) ($_GET['busca'] ?? ''));
        $filtroPerfil = trim((string) ($_GET['perfil'] ?? ''));
        $filtroStatus = trim((string) ($_GET['status'] ?? ''));

        $usuarios = [];
        $total = 0;

        if ($pdo) {
            $where = "tipo = 'admin_escola'";
            $params = [];
            if ($filtroBusca !== '') {
                $where .= " AND (nome LIKE ? OR email LIKE ?)";
                $params[] = '%' . $filtroBusca . '%';
                $params[] = '%' . $filtroBusca . '%';
            }
            if ($filtroPerfil !== '') {
                $where .= " AND perfil_admin = ?";
                $params[] = $filtroPerfil;
            }
            if ($filtroStatus === 'ativo') {
                $where .= " AND ativo = 1";
            } elseif ($filtroStatus === 'inativo') {
                $where .= " AND ativo = 0";
            }

            try {
                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE {$where}");
                $stmtCount->execute($params);
                $total = (int) $stmtCount->fetchColumn();
            } catch (PDOException $e) {}

            $offset = ($page - 1) * $perPage;
            try {
                $stmt = $pdo->prepare(
                    "SELECT id, nome, email, perfil_admin, ativo, created_at
                     FROM usuarios WHERE {$where}
                     ORDER BY nome
                     LIMIT {$perPage} OFFSET {$offset}"
                );
                $stmt->execute($params);
                $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {}
        }

        $totalPages = max(1, (int) ceil($total / $perPage));

        $this->renderDetail($id, 'usuarios', 'usuarios', [
            'usuarios'      => $usuarios,
            'total'         => $total,
            'page'          => $page,
            'total_pages'   => $totalPages,
            'filtro_busca'  => $filtroBusca,
            'filtro_perfil' => $filtroPerfil,
            'filtro_status' => $filtroStatus,
        ]);
    }

    /**
     * Lista professores da escola com botão "Entrar como".
     */
    public function professores($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        $escola = $this->getEscolaOrFail($id);
        $pdo = $this->connectTenant($escola);

        $perPage = 20;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filtroBusca = trim((string) ($_GET['busca'] ?? ''));
        $filtroStatus = trim((string) ($_GET['status'] ?? ''));
        if (!in_array($filtroStatus, ['', 'ativo', 'inativo'], true)) {
            $filtroStatus = '';
        }

        $professores = [];
        $total = 0;

        if ($pdo) {
            $where = '1=1';
            $params = [];
            if ($filtroStatus === 'ativo') {
                $where .= ' AND ativo = 1';
            } elseif ($filtroStatus === 'inativo') {
                $where .= ' AND ativo = 0';
            }
            if ($filtroBusca !== '') {
                $where .= ' AND (nome LIKE ? OR email LIKE ?)';
                $like = '%' . $filtroBusca . '%';
                $params[] = $like;
                $params[] = $like;
            }

            try {
                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM professores WHERE {$where}");
                $stmtCount->execute($params);
                $total = (int) $stmtCount->fetchColumn();
            } catch (PDOException $e) {
            }

            $offset = ($page - 1) * $perPage;
            try {
                $stmt = $pdo->prepare(
                    "SELECT id, nome, email, ativo FROM professores
                     WHERE {$where}
                     ORDER BY nome
                     LIMIT {$perPage} OFFSET {$offset}"
                );
                $stmt->execute($params);
                $professores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
            }
        }

        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $this->renderDetail($id, 'professores', 'professores', [
            'professores' => $professores,
            'total' => $total,
            'page' => $page,
            'total_pages' => $totalPages,
            'filtro_busca' => $filtroBusca,
            'filtro_status' => $filtroStatus,
        ]);
    }

    public function alunos($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        $escola = $this->getEscolaOrFail($id);
        $pdo = $this->connectTenant($escola);

        $perPage = 20;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filtroNome = trim((string) ($_GET['nome'] ?? ''));
        $filtroTurma = (int) ($_GET['turma_id'] ?? 0);
        $filtroCreditosOrdem = trim((string) ($_GET['creditos_ordem'] ?? ''));

        $turmas = [];
        $alunos = [];
        $total = 0;
        $creditosDisponiveis = false;

        if ($pdo) {
            try {
                $turmas = $pdo->query("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {}
            try {
                $creditosDisponiveis = (bool) $pdo->query("SHOW TABLES LIKE 'carteira_usuarios'")->fetchColumn();
            } catch (PDOException $e) {
                $creditosDisponiveis = false;
            }

            $where = "a.ativo = 1";
            $params = [];
            if ($filtroNome !== '') {
                $where .= " AND a.nome LIKE ?";
                $params[] = '%' . $filtroNome . '%';
            }
            if ($filtroTurma > 0) {
                $where .= " AND a.turma_id = ?";
                $params[] = $filtroTurma;
            }

            try {
                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM alunos a WHERE {$where}");
                $stmtCount->execute($params);
                $total = (int) $stmtCount->fetchColumn();
            } catch (PDOException $e) {}

            $offset = ($page - 1) * $perPage;
            try {
                $orderBy = "a.nome";
                if ($creditosDisponiveis && $filtroCreditosOrdem === 'maior') {
                    $orderBy = "saldo_creditos DESC, a.nome";
                } elseif ($creditosDisponiveis && $filtroCreditosOrdem === 'menor') {
                    $orderBy = "saldo_creditos ASC, a.nome";
                }
                $creditosJoin = $creditosDisponiveis
                    ? "LEFT JOIN carteira_usuarios cu ON cu.user_type = 'aluno' AND cu.user_id = a.id"
                    : "";
                $creditosSelect = $creditosDisponiveis
                    ? "COALESCE(cu.saldo, 0)"
                    : "0";
                $stmt = $pdo->prepare(
                    "SELECT a.id, a.nome, a.email, a.ra, t.nome AS turma_nome,
                            {$creditosSelect} AS saldo_creditos
                     FROM alunos a
                     LEFT JOIN turmas t ON a.turma_id = t.id
                     {$creditosJoin}
                     WHERE {$where}
                     ORDER BY {$orderBy}
                     LIMIT {$perPage} OFFSET {$offset}"
                );
                $stmt->execute($params);
                $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {}
        }

        $totalPages = max(1, (int) ceil($total / $perPage));

        $this->renderDetail($id, 'alunos', 'alunos', [
            'alunos'       => $alunos,
            'turmas'       => $turmas,
            'total'        => $total,
            'page'         => $page,
            'total_pages'  => $totalPages,
            'filtro_nome'  => $filtroNome,
            'filtro_turma' => $filtroTurma,
            'filtro_creditos_ordem' => $filtroCreditosOrdem,
            'creditos_disponiveis' => $creditosDisponiveis,
        ]);
    }

    public function usuarioStore($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/usuarios');
            exit;
        }
        $escola = $this->getEscolaOrFail($id);
        $pdo = $this->connectTenant($escola);

        if (!$pdo) {
            $this->setFlashMessage('Não foi possível conectar ao banco da escola.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/usuarios');
            exit;
        }

        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $perfilAdmin = trim($_POST['perfil_admin'] ?? 'admin');

        if ($nome === '' || $email === '' || $senha === '') {
            $this->setFlashMessage('Preencha todos os campos obrigatórios.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/usuarios');
            exit;
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO usuarios (tipo, perfil_admin, nome, email, senha_hash, ativo)
                 VALUES ('admin_escola', ?, ?, ?, ?, 1)"
            );
            $stmt->execute([$perfilAdmin, $nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
            $this->setFlashMessage('Usuário criado com sucesso.', 'success');
        } catch (PDOException $e) {
            $this->setFlashMessage('Erro ao criar usuário: ' . $e->getMessage(), 'error');
        }

        header('Location: ' . URL . '/master/escolas/' . $id . '/usuarios');
        exit;
    }

    public function usuarioUpdate($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/usuarios');
            exit;
        }
        $escola = $this->getEscolaOrFail($id);
        $pdo = $this->connectTenant($escola);

        if (!$pdo) {
            $this->setFlashMessage('Não foi possível conectar ao banco da escola.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/usuarios');
            exit;
        }

        $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $perfilAdmin = trim($_POST['perfil_admin'] ?? 'admin');
        $ativo = isset($_POST['ativo']) && $_POST['ativo'] === '1' ? 1 : 0;
        $senha = $_POST['senha'] ?? '';

        if ($usuarioId <= 0 || $nome === '' || $email === '') {
            $this->setFlashMessage('Dados inválidos.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/usuarios');
            exit;
        }

        try {
            $stmt = $pdo->prepare(
                "UPDATE usuarios SET nome = ?, email = ?, perfil_admin = ?, ativo = ? WHERE id = ? AND tipo = 'admin_escola'"
            );
            $stmt->execute([$nome, $email, $perfilAdmin, $ativo, $usuarioId]);

            if ($senha !== '') {
                $stmt = $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
                $stmt->execute([password_hash($senha, PASSWORD_DEFAULT), $usuarioId]);
            }

            $this->setFlashMessage('Usuário atualizado com sucesso.', 'success');
        } catch (PDOException $e) {
            $this->setFlashMessage('Erro ao atualizar usuário: ' . $e->getMessage(), 'error');
        }

        header('Location: ' . URL . '/master/escolas/' . $id . '/usuarios');
        exit;
    }

    public function modulos($id)
    {
        $this->requireMaster();
        $id = (int) $id;

        $this->renderDetail($id, 'modulos', 'modulos', [
            'modulos_geral'        => $this->getModulosGeralMap(),
            'modulos_geral_labels' => $this->getModulosGeralLabels(),
            'modulos_professor'    => self::$MODULOS_PROFESSOR,
            'modulos_aluno'        => $this->getModulosAluno(),
            'release_channels'     => self::$RELEASE_CHANNEL_VALUES,
            'release_catalog'      => $this->getReleaseCatalog(),
        ]);
    }

    public function modulosSalvar($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/modulos');
            exit;
        }
        $this->getEscolaOrFail($id);

        $senhaConfirmacao = (string) ($_POST['confirm_senha'] ?? '');
        $masterUser = Database::getInstance()->query(
            "SELECT senha_hash FROM usuarios_master WHERE id = ? AND ativo = 1",
            [$_SESSION[self::SESSION_MASTER_USER_ID] ?? 0]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$masterUser || !password_verify($senhaConfirmacao, $masterUser['senha_hash'])) {
            $this->setFlashMessage('Senha incorreta. Nenhuma alteração de módulos foi salva.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/modulos');
            exit;
        }

        $modules = $_POST['modules'] ?? [];
        $config = [];

        foreach ($this->getModulosGeralMap() as $formKey => $backendKeys) {
            $raw = isset($modules[$formKey]) ? (string) $modules[$formKey] : '1';
            $value = in_array($raw, self::$MODULE_VALID_VALUES, true) ? $raw : '1';
            foreach ($backendKeys as $bk) {
                $config['module_' . $bk] = $value;
            }
        }

        $directKeys = array_merge(array_keys(self::$MODULOS_PROFESSOR), array_keys($this->getModulosAluno()));
        if (!class_exists('ModuloRegistry', false)) {
            require_once __DIR__ . '/../../Core/ModuloRegistry.php';
        }
        foreach ($directKeys as $mod) {
            $fallback = ModuloRegistry::featureDefault((string) $mod);
            $raw = isset($modules[$mod]) ? (string) $modules[$mod] : $fallback;
            $config['module_' . $mod] = in_array($raw, self::$MODULE_VALID_VALUES, true) ? $raw : $fallback;
        }

        require_once __DIR__ . '/../../Core/CreditosModuleRegistry.php';
        $layoutAtual = $this->getLayoutConfig($id);
        $tudicoinsOn = (($layoutAtual['creditos_habilitado'] ?? '0') === '1')
            || (($config['creditos_habilitado'] ?? '') === '1');
        if (!$tudicoinsOn) {
            foreach (CreditosModuleRegistry::getFeatureModulesQueExigemTudiCoins() as $fm) {
                $config['module_' . $fm] = '0';
            }
        }

        // Controle de rollout por escola (canário).
        $releaseChannel = strtolower(trim((string) ($_POST['release_channel'] ?? 'stable')));
        if (!in_array($releaseChannel, self::$RELEASE_CHANNEL_VALUES, true)) {
            $releaseChannel = 'stable';
        }
        $releaseVersionManual = trim((string) ($_POST['release_version_manual'] ?? ''));
        $releaseVersionSelect = trim((string) ($_POST['release_version_select'] ?? ''));
        $releaseVersion = $releaseVersionManual !== '' ? $releaseVersionManual : $releaseVersionSelect;
        $releaseVersion = substr($releaseVersion, 0, 120);
        $releaseFlagsRaw = trim((string) ($_POST['release_flags'] ?? ''));
        $releaseFlags = [];
        if ($releaseFlagsRaw !== '') {
            foreach (preg_split('/[\s,;]+/', $releaseFlagsRaw) ?: [] as $flag) {
                $flag = strtolower(trim((string) $flag));
                if ($flag !== '' && preg_match('/^[a-z0-9_\-\.]{2,64}$/', $flag) === 1) {
                    $releaseFlags[$flag] = true;
                }
            }
        }
        $config['release_channel'] = $releaseChannel;
        $config['release_version'] = $releaseVersion;
        $config['release_flags'] = implode(',', array_keys($releaseFlags));

        $this->setLayoutConfig($id, $config);
        $this->syncLayoutToTenant($id);

        $this->setFlashMessage('Módulos e release salvos com sucesso.', 'success');
        header('Location: ' . URL . '/master/escolas/' . $id . '/modulos');
        exit;
    }

    /**
     * Exporta a configuração da escola (config_escolas_layout) em JSON para uso em outra escola.
     */
    public function exportarConfigJson($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        $escola = $this->getEscolaOrFail($id);
        $config = $this->getLayoutConfig($id);
        $slug = $this->getEscolaSlug($id);
        $payload = [
            'escola_id'    => $id,
            'escola_nome'  => $escola['nome'] ?? '',
            'slug'         => $slug,
            'exportado_em' => date('Y-m-d H:i:s'),
            'config'       => $config,
        ];
        $filename = 'config-escola-' . preg_replace('/[^a-z0-9_-]/i', '-', $slug) . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Importa configuração a partir de um JSON (exportado de outra escola).
     * POST: json_config (string JSON) ou arquivo enviado. Aplica em this escola (id).
     */
    public function importarConfigJson($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/layout');
            exit;
        }
        $this->getEscolaOrFail($id);

        $raw = trim((string) ($_POST['json_config'] ?? ''));
        if ($raw === '' && !empty($_FILES['json_file']['tmp_name']) && is_uploaded_file($_FILES['json_file']['tmp_name'])) {
            $raw = file_get_contents($_FILES['json_file']['tmp_name']);
            $raw = is_string($raw) ? trim($raw) : '';
        }
        if ($raw === '') {
            $this->setFlashMessage('Envie o JSON (cole no campo ou anexe o arquivo).', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/layout');
            exit;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['config']) || !is_array($data['config'])) {
            $this->setFlashMessage('JSON inválido. Use um arquivo exportado pelo Master (Exportar configuração).', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/layout');
            exit;
        }

        $config = [];
        foreach ($data['config'] as $key => $value) {
            if (is_string($key) && $key !== '' && strlen($key) <= 128) {
                $config[$key] = is_scalar($value) ? (string) $value : json_encode($value);
            }
        }
        if (empty($config)) {
            $this->setFlashMessage('Nenhuma chave de configuração válida no JSON.', 'warning');
            header('Location: ' . URL . '/master/escolas/' . $id . '/layout');
            exit;
        }

        $this->setLayoutConfig($id, $config);
        $this->syncLayoutToTenant($id);
        $this->setFlashMessage('Configuração importada com sucesso (' . count($config) . ' chaves).', 'success');
        header('Location: ' . URL . '/master/escolas/' . $id . '/layout');
        exit;
    }

    public function creditos($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        $escola = $this->getEscolaOrFail($id);
        $layoutConfig = $this->getMergedLayoutConfig($id, $escola);
        require_once __DIR__ . '/../../Core/CreditosModuleRegistry.php';
        $labels = CreditosModuleRegistry::getModuleLabels();
        $precosGlobais = [];
        try {
            $db = Database::getInstance();
            $rows = $db->query("SELECT modulo_key, creditos FROM modulos_preco_creditos WHERE ativo = 1")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $precosGlobais[$r['modulo_key']] = round((float) $r['creditos'], 4);
            }
        } catch (PDOException $e) {
            // Tabela master pode não existir
        }
        $catalogoTabelas = [];
        $catalogoPacotes = [];
        $vinculoTabelaId = null;
        $vinculoPacoteIds = [];
        $catalogoDisponivel = false;

        try {
            $dbMaster = Database::getInstance();
            try {
                $catalogoTabelas = $dbMaster->fetchAll(
                    'SELECT id, nome, ativo, padrao FROM creditos_tabela_custo ORDER BY padrao DESC, nome'
                );
            } catch (Throwable $e) {
                $catalogoTabelas = $dbMaster->fetchAll(
                    'SELECT id, nome, ativo FROM creditos_tabela_custo ORDER BY nome'
                );
            }
            $catalogoPacotes = $dbMaster->fetchAll(
                'SELECT id, nome, creditos, valor_centavos, ativo FROM creditos_pacotes_catalogo ORDER BY nome'
            );
            $vinculo = $dbMaster->fetch(
                'SELECT tabela_custo_id FROM escolas_creditos_vinculo WHERE escola_id = ?',
                [$id]
            );
            $vinculoTabelaId = $vinculo ? (int) ($vinculo['tabela_custo_id'] ?? 0) : null;
            if ($vinculoTabelaId !== null && $vinculoTabelaId <= 0) {
                $vinculoTabelaId = null;
            }
            $vinculoPacoteIds = array_map(
                static fn ($r) => (int) $r['catalogo_pacote_id'],
                $dbMaster->fetchAll('SELECT catalogo_pacote_id FROM escolas_creditos_pacotes WHERE escola_id = ?', [$id])
            );
            $catalogoDisponivel = true;
        } catch (Throwable $e) {
            // Migration 051 pode não ter rodado no master
            $catalogoDisponivel = false;
        }

        $this->renderDetail($id, 'creditos', 'creditos', [
            'layout_config'         => $layoutConfig,
            'creditos_labels'       => $labels,
            'precos_globais'        => $precosGlobais,
            'catalogo_tabelas'      => $catalogoTabelas,
            'catalogo_pacotes'      => $catalogoPacotes,
            'vinculo_tabela_id'     => $vinculoTabelaId,
            'vinculo_pacote_ids'    => $vinculoPacoteIds,
            'catalogo_disponivel'   => $catalogoDisponivel,
        ]);
    }

    public function creditosCatalogoVinculosSalvar($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/creditos#catalogo-vinculos');
            exit;
        }
        $this->getEscolaOrFail($id);
        require_once __DIR__ . '/../../Services/MasterCreditosCatalogoSyncService.php';

        $db = Database::getInstance();
        try {
            $idsPacotesValidos = array_map(
                static fn ($r) => (int) $r['id'],
                $db->fetchAll('SELECT id FROM creditos_pacotes_catalogo')
            );
            $idsTabelasValidas = array_map(
                static fn ($r) => (int) $r['id'],
                $db->fetchAll('SELECT id FROM creditos_tabela_custo')
            );
        } catch (Throwable $e) {
            $this->setFlashMessage('Catálogo Master indisponível. Rode a migration 051_creditos_catalogos_master.sql antes de vincular.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/creditos#catalogo-vinculos');
            exit;
        }

        $tid = (int) ($_POST['tabela_custo_id'] ?? 0);
        $tabelaId = ($tid > 0 && in_array($tid, $idsTabelasValidas, true)) ? $tid : null;

        $pacRaw = $_POST['pacotes_catalogo'] ?? [];
        $pacIds = is_array($pacRaw) ? array_values(array_filter(array_map('intval', $pacRaw), static fn ($v) => $v > 0)) : [];
        $pacIds = array_values(array_intersect($pacIds, $idsPacotesValidos));
        // Planos saíram da UI: preserva vínculos existentes para não apagar ao salvar pacotes/tabela.
        $plIds = array_map(
            static fn ($r) => (int) $r['catalogo_plano_id'],
            $db->fetchAll('SELECT catalogo_plano_id FROM escolas_creditos_planos WHERE escola_id = ?', [$id])
        );

        $vinculosAnterioresPac = $db->fetchAll('SELECT catalogo_pacote_id FROM escolas_creditos_pacotes WHERE escola_id = ?', [$id]);
        $tinhaVinculos = $vinculosAnterioresPac !== [];
        $confirmClear = (string) ($_POST['confirm_clear_vinculos'] ?? '') === '1';
        if ($tinhaVinculos && $pacIds === [] && !$confirmClear) {
            $this->setFlashMessage('Para remover todos os pacotes vinculados, confirme a limpeza no formulário.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/creditos#catalogo-vinculos');
            exit;
        }

        try {
            \App\Services\MasterCreditosCatalogoSyncService::salvarVinculos($id, $tabelaId, $pacIds, $plIds);
            $res = \App\Services\MasterCreditosCatalogoSyncService::sincronizarTenant($id);
            if (!empty($res['layout_patch'])) {
                $this->setLayoutConfig($id, $res['layout_patch']);
            }
            $this->syncLayoutToTenant($id);
            if (!empty($res['errors'])) {
                $this->setFlashMessage('Vínculos salvos. Avisos: ' . implode(' ', $res['errors']), 'error');
            } else {
                $this->setFlashMessage('Catálogos vinculados e banco da escola sincronizado.', 'success');
            }
        } catch (Throwable $e) {
            $this->setFlashMessage('Erro ao sincronizar: ' . $e->getMessage(), 'error');
        }
        header('Location: ' . URL . '/master/escolas/' . $id . '/creditos#catalogo-vinculos');
        exit;
    }

    public function creditosRenovarTodos($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/creditos');
            exit;
        }
        $escola = $this->getEscolaOrFail($id);
        $layoutConfig = $this->getMergedLayoutConfig($id, $escola);
        $creditosMensalAluno = max(0, (float) ($layoutConfig['creditos_mensal_aluno'] ?? 0));
        $creditosMensalProfessor = max(0, (float) ($layoutConfig['creditos_mensal_professor'] ?? 0));
        $creditosMensalEscola = max(0, (float) ($layoutConfig['creditos_mensal_escola'] ?? 0));
        $modoPool = ($layoutConfig['creditos_modo_pool_escola'] ?? '0') === '1';

        $pdo = $this->getTenantPdo($id);
        if (!$pdo) {
            $this->setFlashMessage('Não foi possível conectar ao banco da escola.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/creditos');
            exit;
        }

        try {
            require_once __DIR__ . '/../../Core/CreditosModuleRegistry.php';
            $pdo->beginTransaction();
            $this->ensureTenantWalletColumns($pdo);
            $renovadosAlunos = 0;
            $renovadosProfessores = 0;
            $renovadaEscola = 0;
            if ($modoPool) {
                $qAlunos = (int) $pdo->query('SELECT COUNT(*) FROM alunos WHERE ativo = 1')->fetchColumn();
                $qProfs = (int) $pdo->query('SELECT COUNT(*) FROM professores WHERE ativo = 1')->fetchColumn();
                $cotaPool = round(
                    ($qAlunos * $creditosMensalAluno) + ($qProfs * $creditosMensalProfessor) + $creditosMensalEscola,
                    4
                );
                $renovadaEscola = $this->renovarSaldoEscolaUsuarios(
                    $pdo,
                    'escola',
                    null,
                    $cotaPool,
                    \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID
                );
                $renovadosAlunos = $qAlunos;
                $renovadosProfessores = $qProfs;
            } else {
                $renovadosAlunos = $this->renovarSaldoEscolaUsuarios($pdo, 'aluno', 'alunos', $creditosMensalAluno);
                $renovadosProfessores = $this->renovarSaldoEscolaUsuarios($pdo, 'professor', 'professores', $creditosMensalProfessor);
                $renovadaEscola = $this->renovarSaldoEscolaUsuarios(
                    $pdo,
                    'escola',
                    null,
                    $creditosMensalEscola,
                    \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID
                );
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->setFlashMessage('Erro ao renovar créditos: ' . $e->getMessage(), 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/creditos');
            exit;
        }

        $this->setFlashMessage(
            sprintf(
                'Renovação concluída. Alunos: %d. Professores: %d. Escola: %s. Créditos comprados foram preservados.',
                $renovadosAlunos,
                $renovadosProfessores,
                $renovadaEscola > 0 ? 'sim' : 'não'
            ),
            'success'
        );
        header('Location: ' . URL . '/master/escolas/' . $id . '/creditos');
        exit;
    }

    public function creditosPlanosSalvar($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        header('Location: ' . URL . '/master/escolas/' . $id . '/creditos');
        exit;
    }

    public function creditosPacotesSalvar($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        header('Location: ' . URL . '/master/escolas/' . $id . '/creditos');
        exit;
    }

    public function creditosPacotesEditar($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        header('Location: ' . URL . '/master/escolas/' . $id . '/creditos');
        exit;
    }

    public function creditosPacotesToggle($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        header('Location: ' . URL . '/master/escolas/' . $id . '/creditos');
        exit;
    }

    public function creditosSalvar($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/creditos');
            exit;
        }
        $this->getEscolaOrFail($id);
        require_once __DIR__ . '/../../Core/CreditosModuleRegistry.php';
        $config = [];
        $config['creditos_habilitado'] = (!empty($_POST['creditos_habilitado']) && $_POST['creditos_habilitado'] === '1') ? '1' : '0';
        $config['creditos_liberar_escola_comprar'] = (!empty($_POST['creditos_liberar_escola_comprar']) && $_POST['creditos_liberar_escola_comprar'] === '1') ? '1' : '0';
        $config['creditos_exibir_menu_carteira'] = (!empty($_POST['creditos_exibir_menu_carteira']) && $_POST['creditos_exibir_menu_carteira'] === '1') ? '1' : '0';
        $config['creditos_modo_pool_escola'] = (!empty($_POST['creditos_modo_pool_escola']) && $_POST['creditos_modo_pool_escola'] === '1') ? '1' : '0';
        $config['creditos_aluno_pode_comprar'] = (!empty($_POST['creditos_aluno_pode_comprar']) && $_POST['creditos_aluno_pode_comprar'] === '1') ? '1' : '0';
        // EducaShop: se o aluno pode comprar, libera o menu automaticamente.
        if ($config['creditos_aluno_pode_comprar'] === '1') {
            $config['creditos_exibir_menu_comprar'] = '1';
        } else {
            $config['creditos_exibir_menu_comprar'] = (!empty($_POST['creditos_exibir_menu_comprar']) && $_POST['creditos_exibir_menu_comprar'] === '1') ? '1' : '0';
        }
        require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';
        $config['creditos_mensal_aluno'] = (string) CreditosDecimalHelper::parsePost($_POST['creditos_mensal_aluno'] ?? 0);
        $config['creditos_mensal_professor'] = (string) CreditosDecimalHelper::parsePost($_POST['creditos_mensal_professor'] ?? 0);
        $config['creditos_mensal_escola'] = (string) CreditosDecimalHelper::parsePost($_POST['creditos_mensal_escola'] ?? 0);
        // Custos/cobra por ação vêm da tabela de preço vinculada (não editados aqui).
        // Com TudiCoins off, força off módulos 100% IA no layout da escola.
        if ($config['creditos_habilitado'] !== '1') {
            foreach (CreditosModuleRegistry::getFeatureModulesQueExigemTudiCoins() as $fm) {
                $config['module_' . $fm] = '0';
            }
        }
        $this->setLayoutConfig($id, $config);
        $this->syncLayoutToTenant($id);
        $this->setFlashMessage('TudiCoins salvos com sucesso.', 'success');
        header('Location: ' . URL . '/master/escolas/' . $id . '/creditos');
        exit;
    }

    public function layout($id)
    {
        $this->requireMaster();
        $id = (int) $id;

        $this->renderDetail($id, 'layout', 'layout');
    }

    public function sliders($id)
    {
        $this->requireMaster();
        $id = (int) $id;

        $this->renderDetail($id, 'sliders', 'sliders');
    }

    public function slidersSalvar($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/sliders');
            exit;
        }
        $this->getEscolaOrFail($id);

        $raw = trim((string) ($_POST['dashboard_slider_items'] ?? '[]'));
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $this->setFlashMessage('JSON de slides inválido.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/sliders');
            exit;
        }

        $sanitized = [];
        $uploadFailures = [];
        $files = $_FILES['slider_images'] ?? null;
        foreach ($decoded as $rowIndex => $item) {
            if (!is_array($item)) {
                continue;
            }
            $img = trim((string) ($item['image_url'] ?? ''));
            $originalImage = trim((string) ($item['original_image_url'] ?? ''));
            $imageOptimized = !empty($item['image_optimized']) ? 1 : 0;
            $actionType = trim((string) ($item['action_type'] ?? 'external'));
            $moduleKey = trim((string) ($item['module_key'] ?? ''));
            if (!in_array($actionType, ['external', 'module'], true)) {
                $actionType = 'external';
            }
            $idx = isset($item['upload_index']) ? (int) $item['upload_index'] : (int) $rowIndex;
            if ($files && isset($files['error'][$idx]) && (int)$files['error'][$idx] === UPLOAD_ERR_OK) {
                try {
                    $uploaded = $this->uploadSliderImageForEscola($id, [
                        'name' => $files['name'][$idx] ?? '',
                        'type' => $files['type'][$idx] ?? '',
                        'tmp_name' => $files['tmp_name'][$idx] ?? '',
                        'error' => $files['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
                        'size' => $files['size'][$idx] ?? 0,
                    ]);
                    $img = $uploaded['image_url'];
                    $originalImage = $uploaded['original_image_url'];
                    $imageOptimized = (int) $uploaded['image_optimized'];
                } catch (Throwable $e) {
                    $uploadFailures[] = 'Slide ' . ($idx + 1) . ': ' . $e->getMessage();
                }
            }
            if ($img === '') {
                continue;
            }
            $sanitized[] = [
                'title' => trim((string) ($item['title'] ?? '')),
                'image_url' => $img,
                'original_image_url' => $originalImage !== '' ? $originalImage : $img,
                'image_optimized' => $imageOptimized,
                'link_url' => trim((string) ($item['link_url'] ?? '')),
                'action_type' => $actionType,
                'module_key' => $moduleKey,
                'active' => !empty($item['active']) ? 1 : 0,
            ];
        }

        if ($uploadFailures !== []) {
            $this->setFlashMessage('Nenhuma alteração foi salva. Corrija os uploads: ' . implode(' ', $uploadFailures), 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/sliders');
            exit;
        }

        $this->setLayoutConfig($id, [
            'dashboard_slider_items' => json_encode($sanitized, JSON_UNESCAPED_UNICODE),
        ]);
        $this->syncLayoutToTenant($id);
        $this->setFlashMessage('Slides do dashboard salvos com sucesso.', 'success');
        header('Location: ' . URL . '/master/escolas/' . $id . '/sliders');
        exit;
    }

    private function uploadSliderImageForEscola(int $escolaId, array $file): array
    {
        require_once __DIR__ . '/../../Services/ImagemSliderService.php';
        $config = $this->config;
        $slug = $this->getEscolaSlug($escolaId);
        if ($slug === '') {
            throw new RuntimeException('Cadastre o slug da escola antes de enviar imagens.');
        }
        $config['school'] = array_merge($config['school'] ?? [], ['code' => $slug]);
        $config['tenant'] = array_merge($config['tenant'] ?? [], ['slug' => $slug]);
        $config['media'] = array_merge($config['media'] ?? [], ['tenant_prefix' => true]);

        return (new ImagemSliderService($config))->salvar($file);
    }

    public function layoutSalvar($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/layout');
            exit;
        }
        $this->getEscolaOrFail($id);

        $config = [];
        $uploadFailures = [];

        foreach ([
            'layout_logo_upload' => 'Logo principal',
            'layout_logo_1x1_upload' => 'Logo quadrado (1x1)',
            'layout_logo_white_upload' => 'Logo branca (modo dark/navbar)',
            'layout_logo_horizontal_white_upload' => 'Logo horizontal branca (modo dark/navbar)',
            'layout_login_cover_upload' => 'Capa da página de login'
        ] as $field => $label) {
            $url = $this->uploadLayoutImage($id, $field);
            if ($url !== null) {
                match ($field) {
                    'layout_logo_upload' => (function () use (&$config, $url) {
                        $config['logo_url'] = $url;
                        $config['logo_horizontal_url'] = $url;
                        $config['logo_white_url'] = $url;
                        $config['logo_horizontal_white_url'] = $url;
                    })(),
                    'layout_logo_1x1_upload' => $config['logo_1x1_url'] = $url,
                    'layout_logo_white_upload' => $config['logo_white_url'] = $url,
                    'layout_logo_horizontal_white_upload' => $config['logo_horizontal_white_url'] = $url,
                    'layout_login_cover_upload' => $config['login_cover_url'] = $url,
                    default => null,
                };
            } else {
                $file = $_FILES[$field] ?? null;
                if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $uploadFailures[] = $label;
                }
            }
        }

        $layoutKeys = [
            'layout_primary_color'      => 'primary_color',
            'layout_primary_text_color' => 'primary_text_color',
            'layout_logo_url'           => 'logo_url',
            'layout_logo_1x1_url'       => 'logo_1x1_url',
            'layout_logo_white_url'     => 'logo_white_url',
            'layout_logo_horizontal_white_url' => 'logo_horizontal_white_url',
            'layout_login_cover_url'    => 'login_cover_url',
            'layout_logo_use_login'     => 'logo_use_login',
            'layout_logo_use_navbar'    => 'logo_use_navbar',
            'layout_logo_size_login'    => 'logo_size_login',
            'layout_logo_size_navbar'   => 'logo_size_navbar',
            'layout_system_title'       => 'system_title',
            'layout_system_subtitle'    => 'system_subtitle',
            'layout_menu_colag_nome'    => 'menu_colag_nome',
            'layout_jornadas_ativas'    => 'jornadas_ativas_layout',
        ];

        foreach ($layoutKeys as $postKey => $cfgKey) {
            $v = trim((string) ($_POST[$postKey] ?? ''));
            if ($v !== '' && !isset($config[$cfgKey])) {
                $config[$cfgKey] = $v;
            }
        }
        // Permitir limpar "qual logo usar" (Automático) salvando string vazia
        if (array_key_exists('layout_logo_use_login', $_POST)) {
            $config['logo_use_login'] = trim((string) ($_POST['layout_logo_use_login'] ?? ''));
        }
        if (array_key_exists('layout_logo_use_navbar', $_POST)) {
            $config['logo_use_navbar'] = trim((string) ($_POST['layout_logo_use_navbar'] ?? ''));
        }

        $mobileLayoutAluno = trim((string) ($_POST['layout_mobile_layout_aluno'] ?? ''));
        if ($mobileLayoutAluno !== '') {
            $decoded = json_decode($mobileLayoutAluno, true);
            if (!is_array($decoded)) {
                $this->setFlashMessage('O JSON do Bottom Nav (Mobile) está inválido. Corrija e salve novamente.', 'error');
                header('Location: ' . URL . '/master/escolas/' . $id . '/layout');
                exit;
            }
            $config['mobile_layout_aluno'] = $mobileLayoutAluno;
        } else {
            $this->removeLayoutConfigKey($id, 'mobile_layout_aluno');
        }

        $this->setLayoutConfig($id, $config);
        $this->syncLayoutToTenant($id);

        if (!empty($uploadFailures)) {
            $this->setFlashMessage(
                'Layout salvo, mas a(s) imagem(ns) "' . implode('", "', $uploadFailures) . '" não puderam ser enviadas. Verifique formato (jpg, png, gif, webp), tamanho (máx. 10 MB) e permissões da pasta storage/files no servidor.',
                'warning'
            );
        } else {
            $this->setFlashMessage('Layout salvo com sucesso.', 'success');
        }
        header('Location: ' . URL . '/master/escolas/' . $id . '/layout');
        exit;
    }

    public function linksUteis($id)
    {
        $this->requireMaster();
        $id = (int) $id;

        $this->renderDetail($id, 'links-uteis', 'links-uteis');
    }

    public function linksUteisSalvar($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/links-uteis');
            exit;
        }
        $this->getEscolaOrFail($id);

        $config = [];
        $menuLinks = trim((string) ($_POST['layout_menu_links_submenu'] ?? ''));
        if ($menuLinks !== '') {
            $decoded = json_decode($menuLinks, true);
            $config['menu_links_submenu'] = is_array($decoded) ? json_encode($decoded) : $menuLinks;
        }

        $this->setLayoutConfig($id, $config);
        $this->syncLayoutToTenant($id);

        $this->setFlashMessage('Links úteis salvos com sucesso.', 'success');
        header('Location: ' . URL . '/master/escolas/' . $id . '/links-uteis');
        exit;
    }

    public function appsExternos($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        $escola = $this->getEscolaOrFail($id);
        $logValidacao = [];
        $logValidacaoMaster = [];
        $pdo = $this->connectTenant($escola);
        if ($pdo) {
            try {
                $stmt = $pdo->query(
                    "SELECT id, app, evento, detalhes, created_at FROM log_validacao_apps_externos ORDER BY created_at DESC LIMIT 100"
                );
                if ($stmt) {
                    $logValidacao = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (Throwable $e) {
                // Tabela pode não existir ainda (migration 032 não executada)
            }
        }
        try {
            $masterDb = Database::getInstance();
            $stmt = $masterDb->query(
                "SELECT id, app, evento, detalhes, created_at FROM log_validacao_apps_externos ORDER BY created_at DESC LIMIT 100"
            );
            if ($stmt) {
                $logValidacaoMaster = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            // Tabela pode não existir no master (migration 033 não executada)
        }
        $this->renderDetail($id, 'apps-externos', 'apps-externos', [
            'log_validacao' => $logValidacao,
            'log_validacao_master' => $logValidacaoMaster,
        ]);
    }

    public function appsExternosSalvar($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/apps-externos');
            exit;
        }
        $this->getEscolaOrFail($id);

        $config = [];
        $appsKeys = [
            'educalabs_external_url',
            'games_external_url',
            'notes_external_url',
            'external_institution_id',
            'external_apps_validate_url',
        ];
        foreach ($appsKeys as $ak) {
            $v = trim((string) ($_POST[$ak] ?? ''));
            if ($v !== '') {
                $config[$ak] = $v;
            }
        }

        $externalAppsLinks = trim((string) ($_POST['layout_external_apps_links'] ?? ''));
        if ($externalAppsLinks !== '') {
            $decoded = json_decode($externalAppsLinks, true);
            $config['external_apps_links'] = is_array($decoded) ? json_encode($decoded) : $externalAppsLinks;
        }

        $this->setLayoutConfig($id, $config);
        $this->syncLayoutToTenant($id);

        $this->setFlashMessage('Apps externos salvos com sucesso.', 'success');
        header('Location: ' . URL . '/master/escolas/' . $id . '/apps-externos');
        exit;
    }

    public function prompts($id)
    {
        $this->requireMaster();
        $id = (int) $id;

        $this->renderDetail($id, 'prompts', 'prompts');
    }

    public function promptsSalvar($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/prompts');
            exit;
        }
        $this->getEscolaOrFail($id);

        $promptKeys = [
            'prompt_tema', 'prompt_correcao', 'prompt_tudinha_chat', 'prompt_ocr',
            'prompt_ocr_formatacao', 'prompt_ocr_vision_system',
            'prompt_prova', 'prompt_prova_imagens', 'prompt_exercicios_jornada', 'prompt_exercicios_personalizados',
        ];

        $config = [];
        foreach ($promptKeys as $key) {
            $config[$key] = trim((string) ($_POST[$key] ?? ''));
        }

        // Compatibilidade com chaves usadas no tenant em versões antigas
        $legacyPromptMap = [
            'prompt_tema' => 'prompt_gerar_tema_redacao',
            'prompt_correcao' => 'prompt_corrigir_redacao',
            'prompt_ocr' => 'prompt_transcrever_imagem',
            'prompt_prova' => 'prompt_gerar_prova',
            'prompt_exercicios_jornada' => 'prompt_gerar_exercicios_jornada',
            'prompt_exercicios_personalizados' => 'prompt_gerar_exercicios_personalizados',
        ];
        foreach ($legacyPromptMap as $modernKey => $legacyKey) {
            $v = trim((string)($config[$modernKey] ?? ''));
            if ($v !== '') {
                $config[$legacyKey] = $v;
            }
        }

        $this->setLayoutConfig($id, $config);
        $this->syncLayoutToTenant($id);

        $this->setFlashMessage('Prompts salvos com sucesso.', 'success');
        header('Location: ' . URL . '/master/escolas/' . $id . '/prompts');
        exit;
    }

    public function limites($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        $escola = $this->getEscolaOrFail($id);

        $db = Database::getInstance();
        $limites = $db->query(
            "SELECT * FROM limites_escolas WHERE escola_id = ?",
            [$id]
        )->fetch(PDO::FETCH_ASSOC);

        $counts = [
            'alunos'      => 0,
            'professores' => 0,
            'admins'      => 0,
        ];

        $pdo = $this->connectTenant($escola);
        if ($pdo) {
            try {
                $counts['alunos'] = (int) $pdo->query("SELECT COUNT(*) FROM alunos WHERE ativo = 1")->fetchColumn();
            } catch (PDOException $e) {}
            try {
                $counts['professores'] = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'professor' AND ativo = 1")->fetchColumn();
            } catch (PDOException $e) {}
            try {
                $counts['admins'] = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'admin_escola'")->fetchColumn();
            } catch (PDOException $e) {}
        }

        $this->renderDetail($id, 'limites', 'limites', [
            'limites' => $limites ?: [],
            'counts'  => $counts,
        ]);
    }

    public function limitesSalvar($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas/' . $id . '/limites');
            exit;
        }
        $this->getEscolaOrFail($id);

        $fields = [
            'max_alunos', 'max_professores', 'max_admins',
            'max_storage_mb', 'max_tokens_ia_mes', 'max_custo_ia_mes_usd',
        ];

        $values = [];
        foreach ($fields as $f) {
            $values[$f] = $_POST[$f] ?? null;
        }

        $db = Database::getInstance();
        $setClauses = [];
        $params = [$id];
        $insertCols = ['escola_id'];
        $insertPlaceholders = ['?'];

        foreach ($fields as $f) {
            $insertCols[] = $f;
            $insertPlaceholders[] = '?';
            $setClauses[] = "{$f} = VALUES({$f})";
            $params[] = $values[$f] !== null && $values[$f] !== '' ? $values[$f] : null;
        }

        $sql = "INSERT INTO limites_escolas (" . implode(', ', $insertCols) . ")
                VALUES (" . implode(', ', $insertPlaceholders) . ")
                ON DUPLICATE KEY UPDATE " . implode(', ', $setClauses);

        $db->query($sql, $params);

        $this->setFlashMessage('Limites salvos com sucesso.', 'success');
        header('Location: ' . URL . '/master/escolas/' . $id . '/limites');
        exit;
    }


    public function banco($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        $escola = $this->getEscolaOrFail($id);

        $db = Database::getInstance();

        $migrationsExecutadas = $db->query(
            "SELECT migration_name FROM migrations_escolas WHERE escola_id = ?",
            [$id]
        )->fetchAll(PDO::FETCH_COLUMN);

        $migrationDir = __DIR__ . '/../../../database/migrations';
        $migrationFiles = [];
        if (is_dir($migrationDir)) {
            $files = scandir($migrationDir);
            foreach ($files as $f) {
                if (pathinfo($f, PATHINFO_EXTENSION) !== 'sql') continue;
                if (stripos($f, 'master') !== false) continue;
                $migrationFiles[] = $f;
            }
            sort($migrationFiles);
        }

        $connectionOk = null;
        $connectionError = null;
        if (!empty($escola['db_host']) && !empty($escola['db_nome_banco'])) {
            $pdo = $this->connectTenant($escola);
            if ($pdo) {
                $connectionOk = true;
            } else {
                $connectionOk = false;
                $connectionError = 'Não foi possível conectar ao banco de dados da escola.';
            }
        }

        $this->renderDetail($id, 'banco', 'banco', [
            'migration_files'      => $migrationFiles,
            'migrations_executadas'=> $migrationsExecutadas,
            'connection_ok'        => $connectionOk,
            'connection_error'     => $connectionError,
        ]);
    }

    /**
     * Painel somente leitura: andamento das provas no banco da escola.
     * Não altera realização, bloco nem acesso do aluno.
     */
    public function provasAoVivo($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        $escola = $this->getEscolaOrFail($id);
        header('Cache-Control: no-store');

        require_once __DIR__ . '/../../Services/MasterProvasAoVivoService.php';

        $pdo = $this->connectTenant($escola);
        if (!$pdo) {
            $this->renderDetail($id, 'provas-ao-vivo', 'provas-ao-vivo', [
                'tenant_ok' => false,
                'atualizado_em' => date('H:i:s'),
            ]);
            return;
        }

        $servico = new MasterProvasAoVivoService();
        $painel = $servico->montarPainel($pdo, (int) ($_GET['bloco_id'] ?? 0));

        $this->renderDetail($id, 'provas-ao-vivo', 'provas-ao-vivo', array_merge($painel, [
            'tenant_ok' => true,
            'atualizado_em' => date('H:i:s'),
        ]));
    }
}

}
