<?php
/**
 * EducaTudo - Helper de Layout
 * Gerencia configurações de layout do sistema
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/RedisCache.php';

class LayoutHelper
{
    private static $config = null;
    private static $db = null;

    /**
     * Verifica se a requisição atual é do painel master.
     * No master não existe config_layout (existe config_escolas_layout); evitar query para não quebrar.
     */
    private static function isMasterContext(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path === null || $path === false) {
            $path = $uri;
        }
        $path = '/' . trim((string) $path, '/');
        $first = trim((string) strstr(ltrim($path, '/'), '/', true) ?: ltrim($path, '/'), '/');
        return strtolower($first) === 'master';
    }

    /**
     * Obter configurações de layout
     */
    public static function getConfig()
    {
        if (self::$config === null) {
            if (self::isMasterContext()) {
                self::$config = [];
                return self::$config;
            }
            // Multi-tenant sem escola resolvida: .env aponta ao master — não consultar config_layout aqui.
            $multiTenant = defined('MULTI_TENANT_ACTIVE') && MULTI_TENANT_ACTIVE === true;
            $tenantId = defined('TENANT_ID') ? (int) TENANT_ID : 0;
            if ($multiTenant && $tenantId <= 0) {
                self::$config = [];
                return self::$config;
            }
            // Single-tenant (MULTI_TENANT=false): TENANT_ID não existe; Database já é o banco da escola — carregar config_layout.
            $cacheKey = $tenantId > 0 ? 'config_layout_' . $tenantId : 'config_layout_single';
            $cached = RedisCache::get($cacheKey);
            if ($cached !== null) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    self::$config = $decoded;
                    return self::$config;
                }
            }
            try {
                self::$db = Database::getInstance();
                $configs = self::$db->fetchAll("SELECT config_key, config_value FROM config_layout");

                self::$config = [];
                foreach ($configs as $config) {
                    self::$config[$config['config_key']] = $config['config_value'];
                }
                RedisCache::set($cacheKey, json_encode(self::$config), 300);
            } catch (Throwable $e) {
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/Logger.php';
                }
                Logger::error("Erro ao carregar config_layout: " . $e->getMessage(), [
                    'exception' => $e
                ], 'general');
                self::$config = [];
            }
        }

        return self::$config;
    }

    /**
     * Invalida o cache em memória e no Redis após gravar config_layout.
     */
    public static function invalidateCache(): void
    {
        self::invalidateCacheDaEscola(0);
    }

    /**
     * Invalida o cache de layout da escola (Master acabou de sincronizar módulos).
     */
    public static function invalidateCacheDaEscola(int $escolaId): void
    {
        self::$config = null;
        if (!class_exists('RedisCache', false)) {
            require_once __DIR__ . '/RedisCache.php';
        }
        RedisCache::delete('config_layout');
        RedisCache::delete('config_layout_single');
        if ($escolaId > 0) {
            RedisCache::delete('config_layout_' . $escolaId);
        }
        if (defined('TENANT_ID') && (int) TENANT_ID > 0) {
            RedisCache::delete('config_layout_' . (int) TENANT_ID);
        }
        $sessionTenant = (int) ($_SESSION['tenant_id'] ?? 0);
        if ($sessionTenant > 0) {
            RedisCache::delete('config_layout_' . $sessionTenant);
        }
    }
    
    /**
     * Obter uma configuração específica
     */
    public static function get($key, $default = '')
    {
        $config = self::getConfig();
        $value = $config[$key] ?? $default;
        // Logos/capas gravadas no Master apontam para master.*/media/serve?tenant=…
        // Anônimos no master levam 403; servir sempre no Host da escola (path relativo).
        if (is_string($value) && is_string($key) && substr($key, -4) === '_url') {
            $value = self::toTenantRelativeMediaUrl($value);
        }
        return $value;
    }

    /**
     * Converte URL absoluta de /media/serve (ex.: master.educatudo.com) em path relativo
     * no host atual, preservando type/key/tenant.
     */
    public static function toTenantRelativeMediaUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || stripos($url, 'media/serve') === false) {
            return $url;
        }
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }
        $query = (string) ($parts['query'] ?? '');
        if ($query === '') {
            return $url;
        }
        $folder = defined('FOLDER') ? rtrim((string) FOLDER, '/') : '';
        return ($folder !== '' ? $folder : '') . '/media/serve?' . $query;
    }
    
    /**
     * Obter valor de configuração (alias de get)
     */
    public static function getConfigValue($key, $default = '')
    {
        return self::get($key, $default);
    }

    /**
     * Saldo de TudiCoins no header do aluno.
     * Respeita creditos_exibir_menu_carteira (escola pode pagar em pool e ocultar saldo).
     */
    public static function creditosExibirSaldoTopoAluno(): bool
    {
        return self::creditosExibirMenuCarteiraAluno();
    }

    /**
     * Exibir Minha Carteira no menu do aluno.
     * creditos_exibir_menu_carteira (padrão 1) pode ocultar mantendo TudiCoins habilitados para consumo.
     */
    public static function creditosExibirMenuCarteiraAluno(): bool
    {
        if (self::get('creditos_habilitado', '0') !== '1') {
            return false;
        }
        return self::get('creditos_exibir_menu_carteira', '1') === '1';
    }

    /**
     * Liberar atalho EducaShop (comprar TudiCoins) no menu do aluno.
     * Se o aluno pode comprar sozinho, o menu abre automaticamente.
     * Caso contrário: escola liberou compra no admin E flag de exibição ligada.
     */
    public static function creditosExibirMenuComprarAluno(): bool
    {
        if (self::get('creditos_habilitado', '0') !== '1') {
            return false;
        }
        if (self::get('creditos_aluno_pode_comprar', '0') === '1') {
            return true;
        }
        if (self::get('creditos_liberar_escola_comprar', '0') !== '1') {
            return false;
        }
        return self::get('creditos_exibir_menu_comprar', '1') === '1';
    }

    public static function creditosModoPoolEscola(): bool
    {
        return self::get('creditos_modo_pool_escola', '0') === '1';
    }

    public static function creditosAlunoPodeComprar(): bool
    {
        return self::get('creditos_aluno_pode_comprar', '0') === '1';
    }

    /**
     * Converte #RGB / #RRGGBB em [r,g,b] 0–255. Retorna null se inválido.
     */
    private static function parseHexColor(?string $hex): ?array
    {
        $hex = trim((string) $hex);
        if ($hex === '') {
            return null;
        }
        if ($hex[0] === '#') {
            $hex = substr($hex, 1);
        }
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Luminância relativa WCAG (0 = preto, 1 = branco).
     */
    private static function relativeLuminance(array $rgb): float
    {
        $channels = [];
        foreach ($rgb as $c) {
            $c = $c / 255;
            $channels[] = $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * Contraste WCAG entre duas cores hex (mínimo 1, máximo 21).
     */
    private static function contrastRatio(?string $a, ?string $b): float
    {
        $ra = self::parseHexColor($a);
        $rb = self::parseHexColor($b);
        if (!$ra || !$rb) {
            return 1.0;
        }
        $l1 = self::relativeLuminance($ra);
        $l2 = self::relativeLuminance($rb);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Cor de texto legível sobre o fundo da sidebar.
     * Se primary_text_color contrastar bem com a primária, mantém;
     * senão força branco (sidebar colorida escura) ou preto (primária clara).
     */
    public static function resolveSidebarTextColor(string $bgColor, string $preferredText): string
    {
        $preferred = trim($preferredText) !== '' ? $preferredText : '#ffffff';
        if (self::contrastRatio($bgColor, $preferred) >= 3.0) {
            return $preferred;
        }
        $bg = self::parseHexColor($bgColor);
        if (!$bg) {
            return '#ffffff';
        }
        return self::relativeLuminance($bg) > 0.55 ? '#111827' : '#ffffff';
    }

    /**
     * Cores efetivas da sidebar (bg + texto com contraste garantido).
     * Em fundos coloridos escuros/médios (padrão Colag/GOLAG) o texto é SEMPRE branco,
     * ignorando primary_text_color quebrada no banco (ex.: preto).
     */
    public static function getSidebarColors(): array
    {
        $bg = (string) self::get('primary_color', '#a855f7');
        $rgb = self::parseHexColor($bg);
        $lum = $rgb ? self::relativeLuminance($rgb) : 0.3;
        // Primária típica de escola (azul/roxo) → texto branco, como no print de referência
        $text = $lum > 0.62 ? '#111827' : '#ffffff';
        return [
            'bg' => $bg,
            'text' => $text,
        ];
    }

    /**
     * Gerar CSS customizado baseado nas configurações
     */
    public static function generateCustomCSS()
    {
        $config = self::getConfig();

        $sidebarBg = (string) ($config['primary_color'] ?? '#a855f7');
        $preferredText = (string) ($config['primary_text_color'] ?? '#ffffff');
        // Sidebar: mesma regra do getSidebarColors (branco em fundo escuro/médio)
        $sidebarColors = self::getSidebarColors();
        $sidebarText = $sidebarColors['text'];
        $sidebarBg = $sidebarColors['bg'];

        $css = "
        :root {
            --primary-color: " . $sidebarBg . ";
            --primary-text-color: " . $preferredText . ";
            --secondary-color: " . (string) ($config['secondary_color'] ?? '#0ea5e9') . ";
            --secondary-text-color: " . (string) ($config['secondary_text_color'] ?? '#ffffff') . ";
            --navbar-bg-color: " . $sidebarBg . ";
            --button-primary-color: " . $sidebarBg . ";
            --button-secondary-color: " . (string) ($config['secondary_color'] ?? '#0ea5e9') . ";
            --sidebar-bg-color: " . $sidebarBg . ";
            --sidebar-text-color: " . $sidebarText . ";
            --card-bg-color: " . (string) ($config['card_bg_color'] ?? '#ffffff') . ";
            --card-border-color: " . (string) ($config['card_border_color'] ?? '#e5e7eb') . ";
            --text-primary-color: " . (string) ($config['text_primary_color'] ?? '#111827') . ";
            --text-secondary-color: " . (string) ($config['text_secondary_color'] ?? '#6b7280') . ";
            --success-color: " . (string) ($config['success_color'] ?? '#10b981') . ";
            --warning-color: " . (string) ($config['warning_color'] ?? '#f59e0b') . ";
            --error-color: " . (string) ($config['error_color'] ?? '#ef4444') . ";
            --info-color: " . (string) ($config['info_color'] ?? '#3b82f6') . ";
            --page-background-color: " . (string) ($config['page_background_color'] ?? '#f8fafc') . ";
            --logo-login-size: " . ((float) ($config['logo_size_login'] ?? '1') * 4) . "rem;
            --logo-navbar-size: " . ((float) ($config['logo_size_navbar'] ?? '1') * 2.5) . "rem;
        }
        
        .logo-login-wrap img { max-height: var(--logo-login-size) !important; height: auto !important; width: auto !important; object-fit: contain; }
        .logo-navbar-wrap { max-width: 100%; min-width: 0; }
        .logo-navbar-wrap img { max-height: var(--logo-navbar-size) !important; height: var(--logo-navbar-size) !important; width: auto !important; max-width: 100% !important; object-fit: contain; }
        
        /* Aplicar cores customizadas */
        .bg-primary { background-color: var(--primary-color) !important; }
        .text-primary { color: var(--primary-text-color) !important; }
        .text-accent { color: var(--primary-color) !important; }
        .border-accent { border-color: var(--primary-color) !important; }
        .bg-secondary { background-color: var(--secondary-color) !important; }
        .text-secondary { color: var(--secondary-text-color) !important; }
        
        /* Background da página */
        .page-background { background-color: var(--page-background-color) !important; }
        
        /* Navbar */
        .navbar-custom { background-color: var(--navbar-bg-color) !important; }
        .navbar-custom .text-white { color: var(--primary-text-color) !important; }
        
        /* Botões */
        .btn-primary-custom { 
            background-color: var(--button-primary-color) !important; 
            color: var(--primary-text-color) !important; 
        }
        .btn-ai-primary {
            background-color: var(--button-primary-color, var(--primary-color)) !important;
            color: var(--primary-text-color, #ffffff) !important;
        }
        .btn-ai-primary:hover:not(:disabled) {
            filter: brightness(0.92);
        }
        .btn-secondary-custom { 
            background-color: var(--button-secondary-color) !important; 
            color: var(--secondary-text-color) !important; 
        }
        
        /* Sidebar */
        .sidebar-custom { 
            background: linear-gradient(to bottom, var(--sidebar-bg-color), color-mix(in srgb, var(--sidebar-bg-color) 80%, black)) !important; 
        }

        /* ============================================================
           Navbar/Sidebar — texto legivel no fundo colorido.
           Nao misturar com keyword transparent: no CSS isso e preto
           com alpha 0, e color-mix(branco, transparent) vira cinza/preto.
           ============================================================ */

        /* Texto principal (item ativo, títulos, nome do usuário) */
        .sidebar-custom .text-white,
        .sidebar-custom .text-gray-50,
        .sidebar-custom .sidebar-user-info .sidebar-text {
            color: var(--sidebar-text-color) !important;
        }

        /* Itens inativos: mesma cor do texto (como no padrão Colag/GOLAG),
           levemente suavizados misturando COM O FUNDO da sidebar — nunca com transparent */
        .sidebar-custom .text-purple-100,
        .sidebar-custom .text-purple-200,
        .sidebar-custom .text-blue-100,
        .sidebar-custom .text-blue-200,
        .sidebar-custom .text-indigo-100,
        .sidebar-custom .text-indigo-200,
        .sidebar-custom .text-gray-300,
        .sidebar-custom nav a,
        .sidebar-custom nav button,
        .sidebar-custom .menu-group > button {
            color: color-mix(in srgb, var(--sidebar-text-color) 92%, var(--sidebar-bg-color)) !important;
        }

        /* Hover / ativo: cor cheia */
        .sidebar-custom .hover\\:text-white:hover,
        .sidebar-custom a:hover .sidebar-text,
        .sidebar-custom button:hover .sidebar-text,
        .sidebar-custom .menu-group > button:hover,
        .sidebar-custom nav a:hover,
        .sidebar-custom nav a.bg-white\\/20,
        .sidebar-custom nav a.bg-white\\/10,
        .sidebar-custom nav a.text-white {
            color: var(--sidebar-text-color) !important;
        }

        /* Itens semânticos (Sair / online) */
        .sidebar-custom .text-red-100,
        .sidebar-custom .text-red-200,
        .sidebar-custom .hover\\:text-red-100:hover,
        .sidebar-custom .hover\\:text-red-200:hover,
        .sidebar-custom a.text-red-200,
        .sidebar-custom a.text-red-200 .sidebar-text {
            color: color-mix(in srgb, #fca5a5 70%, var(--sidebar-text-color)) !important;
        }
        .sidebar-custom .text-green-100,
        .sidebar-custom .text-green-200,
        .sidebar-custom .hover\\:text-green-100:hover,
        .sidebar-custom .hover\\:text-green-200:hover {
            color: color-mix(in srgb, #86efac 70%, var(--sidebar-text-color)) !important;
        }

        /* Fundo do item ATIVO = branco translúcido (padrão visual da referência) */
        .sidebar-custom .bg-white\\/10,
        .sidebar-custom .bg-white\\/20 {
            background-color: rgba(255, 255, 255, 0.18) !important;
        }
        .sidebar-custom nav a.bg-white\\/20,
        .sidebar-custom nav a.bg-white\\/10,
        .sidebar-custom nav summary.bg-white\\/20 { font-weight: 600; }

        /* Hover = branco translúcido mais suave */
        .sidebar-custom .hover\\:bg-white\\/10:hover,
        .sidebar-custom .hover\\:bg-white\\/15:hover,
        .sidebar-custom .hover\\:bg-white\\/20:hover,
        .sidebar-custom .hover\\:bg-white\\/30:hover {
            background-color: rgba(255, 255, 255, 0.12) !important;
        }

        .sidebar-custom .hover\\:bg-red-500\\/20:hover {
            background-color: rgba(239, 68, 68, 0.18) !important;
        }
        .sidebar-custom .hover\\:bg-green-500\\/20:hover {
            background-color: rgba(34, 197, 94, 0.18) !important;
        }

        /* Bordas dos submenus */
        .sidebar-custom .border-white\\/15,
        .sidebar-custom .border-white\\/20,
        .sidebar-custom .border-white\\/30,
        .sidebar-custom .border-white\\/50 {
            border-color: rgba(255, 255, 255, 0.22) !important;
        }

        /* Card do usuário / ícone do header */
        .sidebar-custom .sidebar-user-info,
        .sidebar-custom .sidebar-icon {
            background-color: rgba(255, 255, 255, 0.12) !important;
        }

        /* Ícones herdam a cor do item */
        .sidebar-custom nav a svg,
        .sidebar-custom nav a i,
        .sidebar-custom .menu-group > button svg,
        .sidebar-custom .menu-group > button i,
        .sidebar-custom .sidebar-toggle,
        .sidebar-custom .sidebar-header button {
            color: var(--sidebar-text-color) !important;
            stroke: currentColor;
        }
        .sidebar-custom nav a svg,
        .sidebar-custom .menu-group > button svg {
            color: inherit !important;
        }
        
        /* Sidebar Header */
        .sidebar-custom .sidebar-header {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
        }
        
        /* Sidebar User Info - Simplificado */
        .sidebar-custom .sidebar-user-info {
            background-color: rgba(255, 255, 255, 0.1) !important;
            backdrop-filter: blur(4px) !important;
            border-radius: 0.75rem !important;
            padding: 1rem !important;
            margin-bottom: 1.5rem !important;
        }
        
        /* Avatar no sidebar */
        .sidebar-custom .sidebar-user-info img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            border-radius: 50% !important;
        }
        
        /* Container de texto - sem restrições */
        .sidebar-custom .sidebar-text-container {
            flex: 1 !important;
            min-width: 0 !important;
        }
        
        .sidebar-custom .sidebar-text-container p {
            margin: 0 !important;
            line-height: 1.2 !important;
        }
        
        /* Controle de visibilidade da logo no sidebar */
        .sidebar-custom .sidebar-text-container img {
            transition: all 0.3s ease-in-out;
            display: block !important;
            margin: 0 !important;
            max-width: 100% !important;
        }
        
        /* Quando sidebar está colapsado (w-16), mostrar logo pequena centralizada */
        .sidebar-custom.collapsed .sidebar-text-container img {
            height: 2rem !important; /* h-8 - logo pequena */
            width: auto !important;
            margin: 0 auto !important;
        }

        /* Quando sidebar está expandido, usa o tamanho configurado (logo_size_navbar) */
        .sidebar-custom:not(.collapsed) .sidebar-text-container img {
            height: var(--logo-navbar-size) !important;
            max-height: var(--logo-navbar-size) !important;
            width: auto !important;
            margin-bottom: 0.5rem !important;
        }
        
        /* Garantir que o container da logo tenha o alinhamento correto */
        .sidebar-custom .sidebar-text-container {
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            width: 100% !important;
        }
        
        /* Quando colapsado, centralizar tudo */
        .sidebar-custom.collapsed .sidebar-text-container {
            align-items: center !important;
        }
        
        /* Esconder texto quando colapsado */
        .sidebar-custom.collapsed .sidebar-text-container h1,
        .sidebar-custom.collapsed .sidebar-text-container p {
            opacity: 0;
            pointer-events: none;
        }
        
        /* Mostrar texto quando expandido */
        .sidebar-custom:not(.collapsed) .sidebar-text-container h1,
        .sidebar-custom:not(.collapsed) .sidebar-text-container p {
            opacity: 1;
        }
        
        /* Controle do ícone de expansão */
        .sidebar-custom .sidebar-icon {
            transition: all 0.3s ease-in-out;
        }
        
        /* Quando sidebar está expandido, esconder o ícone de expansão */
        .sidebar-custom:not(.collapsed) .sidebar-icon {
            display: none !important;
        }
        
        /* Quando sidebar está colapsado, mostrar o ícone de expansão */
        .sidebar-custom.collapsed .sidebar-icon {
            display: flex !important;
            background-color: rgba(255, 255, 255, 0.2) !important;
            backdrop-filter: blur(4px) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
        }
        
        /* Cards */
        .card-custom { 
            background-color: var(--card-bg-color) !important; 
            border-color: var(--card-border-color) !important; 
        }
        
        /* Textos */
        .text-primary-custom { color: var(--text-primary-color) !important; }
        .text-secondary-custom { color: var(--text-secondary-color) !important; }
        
        /* Estados */
        .text-success-custom { color: var(--success-color) !important; }
        .text-warning-custom { color: var(--warning-color) !important; }
        .text-error-custom { color: var(--error-color) !important; }
        .text-info-custom { color: var(--info-color) !important; }
        
        .bg-success-custom { background-color: var(--success-color) !important; }
        .bg-warning-custom { background-color: var(--warning-color) !important; }
        .bg-error-custom { background-color: var(--error-color) !important; }
        .bg-info-custom { background-color: var(--info-color) !important; }
        ";
        
        return $css;
    }
    
    /**
     * Obter URL da logo
     */
    public static function getLogoUrl()
    {
        return self::get('logo_url', '');
    }

    /**
     * Logo padrão do sistema: documentos, declarações e PDFs.
     * Não usa a variante do navbar (geralmente clara, ruim em papel branco).
     */
    public static function getDocumentLogoUrl()
    {
        foreach (['logo_url', 'logo_horizontal_url', 'logo_login_url'] as $key) {
            $url = trim((string) self::get($key, ''));
            if ($url !== '') {
                return $url;
            }
        }
        return '';
    }
    
    /**
     * Obter URL da logo quadrada (1x1)
     */
    public static function getLogo1x1Url()
    {
        return self::get('logo_1x1_url', '');
    }
    
    /**
     * Obter URL da logo horizontal
     */
    public static function getLogoHorizontalUrl()
    {
        return self::get('logo_horizontal_url', '');
    }
    
    /**
     * Obter URL da logo branca
     */
    public static function getLogoWhiteUrl()
    {
        return self::get('logo_white_url', '');
    }
    
    /**
     * Obter URL da logo horizontal branca
     */
    public static function getLogoHorizontalWhiteUrl()
    {
        return self::get('logo_horizontal_white_url', '');
    }

    /**
     * Mapa de chave de logo (config) para URL.
     */
    public static function getLogoUrlByKey($key)
    {
        $map = [
            'logo' => self::get('logo_url', ''),
            'logo_horizontal' => self::get('logo_horizontal_url', ''),
            'logo_1x1' => self::get('logo_1x1_url', ''),
            'logo_white' => self::get('logo_white_url', ''),
            'logo_horizontal_white' => self::get('logo_horizontal_white_url', ''),
            'logo_navbar' => self::get('logo_navbar_url', ''),
            'logo_login' => self::get('logo_login_url', ''),
        ];
        $key = (string) $key;
        return isset($map[$key]) ? $map[$key] : '';
    }

    /**
     * URL da logo exibida na tela de login (respeita logo_use_login).
     */
    public static function getLoginLogoUrl()
    {
        $dedicada = trim((string) self::get('logo_login_url', ''));
        if ($dedicada !== '') {
            return $dedicada;
        }
        $use = trim((string) self::get('logo_use_login', ''));
        if ($use !== '') {
            $url = self::getLogoUrlByKey($use);
            if ($url !== '') {
                return $url;
            }
        }
        if (self::getLogoHorizontalUrl() !== '') {
            return self::getLogoHorizontalUrl();
        }
        return self::getLogoUrl();
    }

    /**
     * URL da logo exibida na navbar/sidebar (respeita logo_use_navbar).
     * Em fundo escuro prefere a variante branca, mas cai na colorida se a branca não existir.
     */
    public static function getNavbarLogoUrl()
    {
        $dedicada = trim((string) self::get('logo_navbar_url', ''));
        if ($dedicada !== '') {
            return $dedicada;
        }
        $use = trim((string) self::get('logo_use_navbar', ''));
        if ($use !== '') {
            $url = self::getLogoUrlByKey($use);
            if ($url !== '') {
                return $url;
            }
        }
        $primaryColor = (string) self::get('primary_color', '#a855f7');
        $order = self::isColorDark($primaryColor)
            ? ['logo_horizontal_white', 'logo_white', 'logo_horizontal', 'logo', 'logo_1x1']
            : ['logo_horizontal', 'logo', 'logo_1x1', 'logo_horizontal_white', 'logo_white'];
        foreach ($order as $key) {
            $url = self::getLogoUrlByKey($key);
            if ($url !== '') {
                return $url;
            }
        }
        return '';
    }
    
    /**
     * Obter título personalizado do sistema
     */
    public static function getSystemTitle()
    {
        return self::get('system_title', 'EducaTudo');
    }
    
    /**
     * Obter subtítulo personalizado do sistema
     */
    public static function getSystemSubtitle()
    {
        return self::get('system_subtitle', 'Sistema Educacional');
    }

    /**
     * Primeiro nome em title case (DIOGO → Diogo). Usado na saudação do portal do aluno.
     */
    public static function primeiroNome(string $nomeCompleto): string
    {
        $nomeCompleto = trim(preg_replace('/\s+/u', ' ', $nomeCompleto) ?? '');
        if ($nomeCompleto === '') {
            return '';
        }
        $partes = preg_split('/\s+/u', $nomeCompleto, 2) ?: [];
        $primeiro = trim((string) ($partes[0] ?? ''));
        if ($primeiro === '') {
            return '';
        }
        return mb_convert_case($primeiro, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Saudação curta para o card do aluno: "Olá, Diogo!".
     */
    public static function saudacaoPrimeiroNome(?string $nomeCompleto, string $fallback = 'Aluno'): string
    {
        $primeiro = self::primeiroNome((string) $nomeCompleto);
        if ($primeiro === '') {
            $primeiro = $fallback;
        }
        return 'Olá, ' . $primeiro . '!';
    }

    /**
     * Obter nome da IA (chat)
     */
    public static function getIaName()
    {
        return self::get('ia_name', 'Tudinha');
    }

    /**
     * Obter URL do ícone/avatar da IA (chat) — vazio se a escola não configurou.
     */
    public static function getIaAvatarUrl()
    {
        return self::get('ia_avatar_url', '');
    }

    /**
     * Verificar se a turma é obrigatória no primeiro acesso
     */
    public static function isPrimeiroAcessoTurmaObrigatoria()
    {
        return self::get('primeiro_acesso_turma_obrigatoria', '1') === '1';
    }

    /**
     * Obter URL da capa de login
     */
    public static function getLoginCoverUrl()
    {
        return self::get('login_cover_url', '');
    }
    
    /**
     * Escala do tamanho da logo na página de login (1 = 100%, 1.25 = 125%, etc.)
     */
    public static function getLogoSizeLogin()
    {
        return self::get('logo_size_login', '1');
    }
    
    /**
     * Escala do tamanho da logo na navbar/sidebar (1 = 100%, 1.25 = 125%, etc.)
     */
    public static function getLogoSizeNavbar()
    {
        return self::get('logo_size_navbar', '1');
    }
    
    /**
     * Obter cor de background da página
     */
    public static function getPageBackgroundColor()
    {
        return self::get('page_background_color', '#f8fafc');
    }
    
    /**
     * Gerar tag de imagem da logo
     */
    public static function getLogoTag($class = '', $alt = 'Logo')
    {
        $url = self::getLogoUrl();
        if (empty($url)) {
            return '';
        }
        
        return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($alt) . '" class="' . htmlspecialchars($class) . '">';
    }
    
    /**
     * Gerar tag de imagem da logo quadrada (1x1)
     */
    public static function getLogo1x1Tag($class = '', $alt = 'Logo')
    {
        $url = self::getLogo1x1Url();
        if (empty($url)) {
            return '';
        }
        
        return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($alt) . '" class="' . htmlspecialchars($class) . '">';
    }
    
    /**
     * Gerar tag de imagem da logo horizontal
     */
    public static function getLogoHorizontalTag($class = '', $alt = 'Logo')
    {
        $url = self::getLogoHorizontalUrl();
        if (empty($url)) {
            return '';
        }
        
        return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($alt) . '" class="' . htmlspecialchars($class) . '">';
    }
    
    /**
     * Gerar tag de imagem da logo branca
     */
    public static function getLogoWhiteTag($class = '', $alt = 'Logo')
    {
        $url = self::getLogoWhiteUrl();
        if (empty($url)) {
            return '';
        }
        
        return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($alt) . '" class="' . htmlspecialchars($class) . '">';
    }
    
    /**
     * Gerar tag de imagem da logo horizontal branca
     */
    public static function getLogoHorizontalWhiteTag($class = '', $alt = 'Logo')
    {
        $url = self::getLogoHorizontalWhiteUrl();
        if (empty($url)) {
            return '';
        }
        
        return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($alt) . '" class="' . htmlspecialchars($class) . '">';
    }
    
    /**
     * Gerar tag de imagem da capa de login
     */
    public static function getLoginCoverTag($class = '', $alt = 'Capa de Login')
    {
        $url = self::getLoginCoverUrl();
        if (empty($url)) {
            return '';
        }
        
        return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($alt) . '" class="' . htmlspecialchars($class) . '">';
    }
    
    /**
     * Determina se uma cor hexadecimal é considerada escura
     */
    public static function isColorDark($hexColor)
    {
        // Garantir que seja uma string
        $hexColor = (string) $hexColor;
        
        // Remove # se presente
        $hexColor = ltrim($hexColor, '#');
        
        // Lida com códigos hex abreviados (ex: #FFF -> #FFFFFF)
        if (strlen($hexColor) == 3) {
            $hexColor = $hexColor[0] . $hexColor[0] . $hexColor[1] . $hexColor[1] . $hexColor[2] . $hexColor[2];
        }
        
        if (strlen($hexColor) != 6) {
            // Cor hex inválida, assume claro por segurança
            return false;
        }
        
        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));
        
        // Calcula a luminância (baseado na recomendação W3C)
        // Um valor de 0.5 é um bom ponto de corte para determinar claro/escuro
        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
        
        return $luminance < 0.5;
    }
    
    /**
     * Obter logo contextual baseada na cor primária
     */
    public static function getContextualLogo($context = 'default', $class = '', $alt = 'Logo')
    {
        switch ($context) {
            case 'navbar':
            case 'sidebar':
                $url = self::getNavbarLogoUrl();
                if ($url !== '') {
                    return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($alt) . '" class="' . htmlspecialchars($class) . '" onerror="this.style.display=\'none\';var f=this.nextElementSibling;if(f&&f.classList.contains(\'sidebar-logo-fallback\')){f.classList.remove(\'hidden\');}">';
                }
                return '';
                
            case 'favicon':
                if (self::getLogo1x1Url()) {
                    return self::getLogo1x1Tag($class, $alt);
                }
                $doc = self::getDocumentLogoUrl();
                if ($doc !== '') {
                    return '<img src="' . htmlspecialchars($doc) . '" alt="' . htmlspecialchars($alt) . '" class="' . htmlspecialchars($class) . '">';
                }
                return self::getLogoTag($class, $alt);
                
            case 'login':
                $url = self::getLoginLogoUrl();
                if ($url !== '') {
                    return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($alt) . '" class="' . htmlspecialchars($class) . '">';
                }
                return self::getLogoTag($class, $alt);
                
            default:
                return self::getLogoTag($class, $alt);
        }
    }
    
    /**
     * Aplicar classes customizadas em elementos
     */
    public static function applyCustomClasses($element, $type = 'primary')
    {
        $classes = [];
        
        switch ($type) {
            case 'primary':
                $classes[] = 'bg-primary';
                $classes[] = 'text-primary';
                break;
            case 'secondary':
                $classes[] = 'bg-secondary';
                $classes[] = 'text-secondary';
                break;
            case 'navbar':
                $classes[] = 'navbar-custom';
                break;
            case 'sidebar':
                $classes[] = 'sidebar-custom';
                break;
            case 'card':
                $classes[] = 'card-custom';
                break;
            case 'btn-primary':
                $classes[] = 'btn-primary-custom';
                break;
            case 'btn-secondary':
                $classes[] = 'btn-secondary-custom';
                break;
        }
        
        return implode(' ', $classes);
    }
    
    /**
     * Retorna o status do módulo: '1' = habilitado, '0' = desabilitado (visível + mensagem ao clicar), '2' = inativo (oculto e inacessível)
     */
    public static function getModuleStatus($module)
    {
        $config = self::getConfig();
        $key = 'module_' . $module;
        if (!array_key_exists($key, $config)) {
            if (!class_exists('ModuloRegistry', false)) {
                require_once __DIR__ . '/ModuloRegistry.php';
            }
            $value = ModuloRegistry::featureDefault((string) $module);
        } else {
            $value = $config[$key];
        }
        $v = (string) $value;
        return ($v === '2' || $v === '0') ? $v : '1';
    }

    /**
     * Verifica se um módulo está habilitado (acesso total).
     * Módulos 100% IA exigem TudiCoins ligado.
     */
    public static function isModuleEnabled($module)
    {
        if (self::getModuleStatus($module) !== '1') {
            return false;
        }
        return !self::moduloBloqueadoPorTudiCoins((string) $module);
    }

    /**
     * Verifica se o módulo deve aparecer no menu do admin (habilitado ou desabilitado com mensagem).
     * Inativo (2) não aparece. Módulos 100% IA somem se TudiCoins off.
     * No portal do aluno use isModuleEnabled() — desativado não aparece no navbar.
     */
    public static function isModuleVisible($module)
    {
        if (self::moduloBloqueadoPorTudiCoins((string) $module)) {
            return false;
        }
        $status = self::getModuleStatus($module);
        return $status === '1' || $status === '0';
    }

    /**
     * Módulo FeatureGate que some quando TudiCoins está desligado.
     */
    public static function moduloBloqueadoPorTudiCoins(string $module): bool
    {
        if (self::get('creditos_habilitado', '0') === '1') {
            return false;
        }
        if (!class_exists('CreditosModuleRegistry', false)) {
            require_once __DIR__ . '/CreditosModuleRegistry.php';
        }
        static $exige = null;
        if ($exige === null) {
            $exige = array_fill_keys(\CreditosModuleRegistry::getFeatureModulesQueExigemTudiCoins(), true);
        }
        return isset($exige[$module]);
    }

    /**
     * Verifica se o módulo está inativo (oculto, escola não contratou)
     */
    public static function isModuleInactive($module)
    {
        return self::getModuleStatus($module) === '2';
    }

    /**
     * Converte enunciado salvo como HTML (eq-chip + img) para formato exibível:
     * texto + \( latex \) + imagens permitidas. Para usar na visualização da prova.
     */
    /**
     * Extrai o texto do enunciado quando ele foi salvo como JSON bruto (resposta de IA),
     * ex.: { "enunciado": "..." } — inclusive quando o JSON está malformado (aspas/quebras
     * de linha não escapadas). Se não houver wrapper JSON, devolve a string original.
     */
    private static function extrairEnunciadoDeJson(string $str): string
    {
        $trimmed = trim($str);
        if ($trimmed === '' || strpos($trimmed, '"enunciado"') === false) {
            return $str;
        }

        // Remove cercas de código markdown (```json ... ```)
        $trimmed = trim(preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $trimmed));

        // 1) JSON válido
        $json = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json) && isset($json['enunciado'])) {
            return trim((string) $json['enunciado']);
        }

        // 2) Regex para JSON corretamente escapado
        if (preg_match('/"enunciado"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/us', $trimmed, $m)) {
            $dec = json_decode('"' . $m[1] . '"');
            return trim((string) ($dec ?? $m[1]));
        }

        // 3) Fallback para JSON malformado: captura tudo entre "enunciado":" e o fechamento "}
        if (preg_match('/"enunciado"\s*:\s*"([\s\S]*)"\s*\}\s*$/u', $trimmed, $m)
            || preg_match('/"enunciado"\s*:\s*"([\s\S]*)$/u', $trimmed, $m)) {
            $cand = preg_replace('/"\s*\}?\s*$/u', '', $m[1]);
            $cand = strtr($cand, ['\\"' => '"', '\\n' => "\n", '\\t' => "\t", '\\/' => '/', '\\\\' => '\\']);
            return trim($cand);
        }

        return $str;
    }

    public static function renderEnunciadoProva($str): string
    {
        if ($str === null || $str === '') {
            return '';
        }
        $str = (string) $str;

        // EducaInclui: enunciados reescritos por IA podem ter sido salvos como JSON bruto
        // ({ "enunciado": "..." }). Extrai o texto limpo antes de qualquer renderização.
        $str = self::extrairEnunciadoDeJson($str);

        // Conteúdo vindo do servidor/Word pode estar com entidades HTML (&lt; &gt; &quot;) ou duplamente codificado (&amp;lt;) — decodifica
        $decoded = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded !== $str) {
            $str = $decoded;
            $decoded = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded !== $str) {
                $str = $decoded;
            }
        }

        // Se o conteúdo é HTML com eq-chip/data-latex, converte para texto + \( latex \) (e preserva <img>)
        if (strpos($str, '<') !== false && (strpos($str, 'eq-chip') !== false || strpos($str, 'data-latex') !== false)) {
            $str = self::enunciadoHtmlToLatexSerialized($str);
        } elseif (strpos($str, '<') !== false) {
            // HTML rico do editor (enter = <br>/<div>/<p>) — permitir tags seguras para exibir renderizado (admin/aluno)
            // Inclui <sup> e <sub> para preservar elevação/abaixamento (ex.: 10³ em alternativas de provas)
            $allowed = '<p><div><br><br/><span><b><i><u><strong><em><sup><sub><img>';
            $str = strip_tags($str, $allowed);
            // Sanitizar <img>: manter só src e alt para evitar XSS
            $str = preg_replace_callback('/<img\s+([^>]*)>/i', function ($m) {
                $attrs = $m[1];
                $src = '';
                $alt = '';
                if (preg_match('/src=(["\'])([^"\']+)\1/i', $attrs, $x)) {
                    $src = htmlspecialchars($x[2], ENT_QUOTES, 'UTF-8');
                }
                if (preg_match('/alt=(["\'])([^"\']*)\1/i', $attrs, $x)) {
                    $alt = htmlspecialchars($x[2], ENT_QUOTES, 'UTF-8');
                }
                return $src !== '' ? '<img src="' . $src . '" alt="' . $alt . '">' : '';
            }, $str);
            $str = preg_replace('/\$\$([^$]*?)\$\$/s', ' \\( $1 \\) ', $str);
            return $str;
        }

        // Converte $$...$$ para \( ... \) para renderizar como fórmula inline (ex.: enunciado salvo com $$)
        $str = preg_replace('/\$\$([^$]*?)\$\$/s', ' \\( $1 \\) ', $str);

        // Preserva blocos LaTeX, <img> e <br>; escapa o resto
        $placeholders = [];
        $t = preg_replace_callback('/\\\\\\((.+?)\\\\\\)|\\\\\\[(.+?)\\\\\\]|<img\s[^>]*>|<br\s*\/?>/i', function ($m) use (&$placeholders) {
            $placeholders[] = $m[0];
            return '___PROVA_' . (count($placeholders) - 1) . '___';
        }, $str);
        $t = htmlspecialchars($t ?? '', ENT_QUOTES, 'UTF-8');
        foreach ($placeholders as $i => $p) {
            $t = str_replace('___PROVA_' . $i . '___', $p, $t);
        }
        // Evita que cada fórmula inline fique em uma linha: colapsa espaços/quebras entre \) e \(
        $t = preg_replace('/(\\\\\))\s+(\\\\\()/', '$1 $2', $t);
        return nl2br($t);
    }

    /**
     * Converte HTML do enunciado (span.eq-chip + img) para texto + \( latex \) + <img>.
     */
    private static function enunciadoHtmlToLatexSerialized(string $html): string
    {
        $dom = new \DOMDocument();
        $useErrors = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_use_internal_errors($useErrors);
        $root = $dom->getElementById('root');
        if (!$root) {
            return $html;
        }
        $out = [];
        self::walkEnunciadoNodes($root, $out);
        $s = trim(implode('', $out));
        return preg_replace('/\s+/', ' ', $s);
    }

    private static function walkEnunciadoNodes(\DOMNode $node, array &$out): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $out[] = $node->textContent;
            return;
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }
        /** @var \DOMElement $node */
        if ($node->nodeName === 'img') {
            $src = $node->getAttribute('src');
            if ($src !== '') {
                $alt = $node->getAttribute('alt');
                $style = $node->getAttribute('style');
                $w = $node->getAttribute('width');
                $h = $node->getAttribute('height');
                $parts = ['src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"'];
                if ($alt !== '') {
                    $parts[] = 'alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"';
                }
                if ($style !== '') {
                    $parts[] = 'style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"';
                }
                if ($w !== '') {
                    $parts[] = 'width="' . htmlspecialchars($w, ENT_QUOTES, 'UTF-8') . '"';
                }
                if ($h !== '') {
                    $parts[] = 'height="' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '"';
                }
                $out[] = '<img ' . implode(' ', $parts) . '>';
            }
            return;
        }
        if ($node->nodeName === 'br') {
            $out[] = '<br>';
            return;
        }
        if ($node->getAttribute('class') && strpos($node->getAttribute('class'), 'eq-chip') !== false) {
            $latex = $node->getAttribute('data-latex');
            if ($latex !== '') {
                $out[] = ' \\( ' . $latex . ' \\) ';
            }
            return;
        }
        foreach ($node->childNodes as $child) {
            self::walkEnunciadoNodes($child, $out);
        }
    }

    /**
     * Escapa HTML para exibição segura preservando blocos LaTeX \( ... \) e \[ ... \]
     * para renderização pelo MathLive na tela do aluno.
     */
    public static function safeHtmlMath($str)
    {
        if ($str === null || $str === '') {
            return '';
        }
        $str = (string) $str;
        $placeholders = [];
        $pattern = '/\\\\\\((.+?)\\\\\\)|\\\\\\[(.+?)\\\\\\]/s';
        $t = preg_replace_callback($pattern, function ($m) use (&$placeholders) {
            $placeholders[] = $m[0];
            return '___MATH' . (count($placeholders) - 1) . '___';
        }, $str);
        $t = htmlspecialchars($t ?? '', ENT_QUOTES, 'UTF-8');
        foreach ($placeholders as $i => $p) {
            $t = str_replace('___MATH' . $i . '___', $p, $t);
        }
        return $t;
    }

    /**
     * Renderiza HTML rico preservando blocos LaTeX \( ... \) e \[ ... \].
     * Usa HTML Purifier para sanitização segura antes da exibição.
     * Use este método para enunciados de questões que contêm formatação HTML.
     */
    public static function renderHtmlMath($str): string
    {
        if ($str === null || $str === '') {
            return '';
        }
        $str = (string) $str;

        // Extrai blocos LaTeX como placeholders
        $placeholders = [];
        $pattern = '/\\\\\\((.+?)\\\\\\)|\\\\\\[(.+?)\\\\\\]/s';
        $t = preg_replace_callback($pattern, function ($m) use (&$placeholders) {
            $placeholders[] = $m[0];
            return '___MATH' . (count($placeholders) - 1) . '___';
        }, $str);

        // Sanitiza HTML — tenta HTMLPurifier, senão usa strip_tags como fallback
        if (!class_exists('HTMLPurifier')) {
            $vendorPaths = [
                __DIR__ . '/../../vendor/autoload.php',
                __DIR__ . '/../../../vendor/autoload.php',
            ];
            foreach ($vendorPaths as $vp) {
                if (@file_exists($vp)) {
                    require_once $vp;
                    break;
                }
            }
        }
        if (class_exists('HTMLPurifier')) {
            $config = \HTMLPurifier_Config::createDefault();
            $cacheDir = dirname(__DIR__, 2) . '/storage/cache/htmlpurifier';
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0775, true);
            }
            if (is_dir($cacheDir) && is_writable($cacheDir)) {
                $config->set('Cache.SerializerPath', $cacheDir);
            }
            $config->set('HTML.Allowed', 'p,br,b,strong,i,em,u,ul,ol,li,h1,h2,h3,h4,h5,h6,blockquote,pre,code,span,div,img[src|alt|width|height],a[href|target],sub,sup');
            $config->set('CSS.AllowedProperties', 'font-weight,font-style,text-decoration,color');
            $config->set('AutoFormat.AutoParagraph', false);
            $config->set('AutoFormat.RemoveEmpty', false);
            $purifier = new \HTMLPurifier($config);
            $t = $purifier->purify($t ?? '');
        } else {
            $t = strip_tags($t ?? '', '<p><br><b><strong><i><em><u><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><pre><code><span><div><img><a><sub><sup>');
        }

        // Restaura blocos LaTeX
        foreach ($placeholders as $i => $p) {
            $t = str_replace('___MATH' . $i . '___', $p, $t);
        }

        return $t;
    }

    /**
     * Converte enunciado (HTML com eq-chip + img) para HTML adequado ao PDF.
     * Fórmulas são convertidas para texto legível (Unicode); imagens são preservadas.
     */
    public static function enunciadoParaPdf($str): string
    {
        if ($str === null || $str === '') {
            return '';
        }
        $str = (string) $str;
        if (strpos($str, '<') === false || (strpos($str, 'eq-chip') === false && strpos($str, 'data-latex') === false)) {
            // Sem HTML de fórmula: preserva <img> e tags de formatação/estrutura (enter = <br>/<p>/<div>)
            $t = strip_tags($str, '<img><b><strong><i><em><u><br><p><div><span>');
            return $t;
        }
        $out = [];
        self::walkEnunciadoNodesParaPdf($str, $out);
        return trim(implode('', $out));
    }

    /**
     * Converte HTML (eq-chip + img) para array de strings PDF-safe.
     */
    private static function walkEnunciadoNodesParaPdf(string $html, array &$out): void
    {
        $dom = new \DOMDocument();
        $useErrors = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_use_internal_errors($useErrors);
        $root = $dom->getElementById('root');
        if (!$root) {
            $out[] = strip_tags($html, '<img><b><strong><i><em><u><br><p>');
            return;
        }
        self::walkEnunciadoNodesPdf($root, $out);
    }

    private static function walkEnunciadoNodesPdf(\DOMNode $node, array &$out): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $out[] = htmlspecialchars($node->textContent, ENT_QUOTES, 'UTF-8');
            return;
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }
        /** @var \DOMElement $node */
        if ($node->nodeName === 'img') {
            $src = $node->getAttribute('src');
            if ($src !== '') {
                $out[] = '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" style="max-width:100%;height:auto;" />';
            }
            return;
        }
        if ($node->getAttribute('class') && strpos($node->getAttribute('class'), 'eq-chip') !== false) {
            $latex = $node->getAttribute('data-latex');
            if ($latex !== '') {
                $out[] = ' ' . self::latexToPdfText($latex) . ' ';
            }
            return;
        }
        $tag = $node->nodeName;
        $allowed = ['b', 'strong', 'i', 'em', 'u', 'br', 'p'];
        if (in_array($tag, $allowed, true)) {
            if ($tag === 'br') {
                $out[] = '<br/>';
                return;
            }
            $out[] = '<' . $tag . '>';
        }
        foreach ($node->childNodes as $child) {
            self::walkEnunciadoNodesPdf($child, $out);
        }
        if (in_array($tag, $allowed, true) && $tag !== 'br') {
            $out[] = '</' . $tag . '>';
        }
    }

    /**
     * Converte LaTeX simples para texto legível no PDF (Unicode quando possível).
     */
    public static function latexToPdfText(string $latex): string
    {
        $s = $latex;
        // \frac{a}{b} → (a)/(b); suporta aninhamento simples
        $s = preg_replace_callback('/\\\\frac\s*\{([^{}]*)\}\s*\{([^{}]*)\}/', function ($m) {
            return '(' . self::latexToPdfText($m[1]) . ')/(' . self::latexToPdfText($m[2]) . ')';
        }, $s);
        // Símbolos gregos e comuns
        $map = [
            '\\Delta' => 'Δ', '\\delta' => 'δ', '\\alpha' => 'α', '\\beta' => 'β', '\\gamma' => 'γ',
            '\\pi' => 'π', '\\theta' => 'θ', '\\omega' => 'ω', '\\mu' => 'μ', '\\sigma' => 'σ',
            '\\cdot' => '·', '\\times' => '×', '\\div' => '÷', '\\pm' => '±', '\\mp' => '∓',
            '\\leq' => '≤', '\\geq' => '≥', '\\neq' => '≠', '\\approx' => '≈', '\\infty' => '∞',
            '\\sqrt' => '√', '\\left' => '', '\\right' => '',
        ];
        foreach ($map as $k => $v) {
            $s = str_replace($k, $v, $s);
        }
        // \sqrt{x} → √(x)
        $s = preg_replace('/√\s*\{([^{}]*)\}/', '√($1)', $s);
        // Subscritos numéricos
        $s = preg_replace('/_0\b/', '₀', $s);
        $s = preg_replace('/_1\b/', '₁', $s);
        $s = preg_replace('/_2\b/', '₂', $s);
        $s = preg_replace('/_3\b/', '₃', $s);
        $s = preg_replace('/_4\b/', '₄', $s);
        $s = preg_replace('/_5\b/', '₅', $s);
        $s = preg_replace('/_6\b/', '₆', $s);
        $s = preg_replace('/_7\b/', '₇', $s);
        $s = preg_replace('/_8\b/', '₈', $s);
        $s = preg_replace('/_9\b/', '₉', $s);
        // Sobrescritos numéricos (potência)
        $s = preg_replace('/\^2\b/', '²', $s);
        $s = preg_replace('/\^3\b/', '³', $s);
        $s = preg_replace('/\^1\b/', '¹', $s);
        // Remove chaves restantes que sobraram (conteúdo simples)
        $s = preg_replace('/\{([^{}]*)\}/', '$1', $s);
        $s = trim($s);
        return $s;
    }
}
