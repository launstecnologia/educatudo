<?php

/**
 * Normaliza URLs de avatar de usuários admin (tabela usuarios).
 */
class AvatarUrlHelper
{
    /**
     * Busca avatar atualizado no banco e retorna URL pronta para exibição.
     */
    public static function resolveAdminUserAvatar(array $user): ?string
    {
        $avatarUrl = null;

        if (!empty($user['id'])) {
            try {
                require_once __DIR__ . '/../Core/Database.php';
                $db = Database::getInstance();
                $row = $db->fetch(
                    'SELECT avatar_url FROM usuarios WHERE id = :id',
                    ['id' => (int) $user['id']]
                );
                if (!empty($row['avatar_url'])) {
                    $avatarUrl = (string) $row['avatar_url'];
                }
            } catch (Exception $e) {
                // Mantém fallback da sessão.
            }
        }

        if (($avatarUrl === null || trim($avatarUrl) === '') && !empty($user['avatar_url'])) {
            $avatarUrl = (string) $user['avatar_url'];
        }

        return self::normalizeAdminAvatarUrl($avatarUrl);
    }

    /**
     * Converte caminhos legados e relativos em URL absoluta utilizável no navegador.
     */
    public static function normalizeAdminAvatarUrl(?string $avatarUrl): ?string
    {
        if ($avatarUrl === null) {
            return null;
        }

        $avatarUrl = trim($avatarUrl);
        if ($avatarUrl === '') {
            return null;
        }

        $baseUrl = rtrim(defined('URL') ? URL : '', '/');
        $tenant = self::resolveTenantSlug();

        if (preg_match('#^https?://#i', $avatarUrl)) {
            $pathOnly = parse_url($avatarUrl, PHP_URL_PATH);
            $query = parse_url($avatarUrl, PHP_URL_QUERY);
            if (is_string($pathOnly) && strpos($pathOnly, '/media/serve') !== false) {
                return $baseUrl . $pathOnly . ($query !== null && $query !== '' ? '?' . $query : '');
            }

            return self::rewriteLegacyRemoteAvatar($avatarUrl, $baseUrl, $tenant);
        }

        $pathOnly = parse_url($avatarUrl, PHP_URL_PATH);
        $query = parse_url($avatarUrl, PHP_URL_QUERY);
        $normalizedPath = is_string($pathOnly) && $pathOnly !== '' ? $pathOnly : $avatarUrl;

        if (strpos($normalizedPath, '/media/serve') !== false || strpos($avatarUrl, 'media/serve') !== false) {
            if (strpos($avatarUrl, '/media/serve') === 0) {
                return $baseUrl . $avatarUrl;
            }
            if (strpos($avatarUrl, 'media/serve') === 0) {
                return $baseUrl . '/' . $avatarUrl;
            }
            if (is_string($pathOnly) && strpos($pathOnly, '/media/serve') !== false) {
                return $baseUrl . $pathOnly . ($query !== null && $query !== '' ? '?' . $query : '');
            }
        }

        if (strpos($normalizedPath, '/public/uploads/') !== false) {
            $normalizedPath = str_replace('/public/uploads/', '/uploads/', $normalizedPath);
        }

        if (strpos($normalizedPath, '/uploads/avatars/') !== false || strpos($normalizedPath, 'uploads/avatars/') !== false) {
            $filename = basename($normalizedPath);
            if ($filename === '' || $filename === '.' || $filename === '..') {
                return null;
            }
            $url = $baseUrl . '/media/serve?type=avatars&key=' . rawurlencode($filename);
            if ($tenant !== '') {
                $url .= '&tenant=' . rawurlencode($tenant);
            }
            return $url;
        }

        if (strpos($normalizedPath, '/uploads/') === 0) {
            return $baseUrl . $normalizedPath . ($query ? '?' . $query : '');
        }

        if ($normalizedPath !== '' && $normalizedPath[0] !== '/') {
            return $baseUrl . '/' . ltrim($avatarUrl, '/');
        }

        return $baseUrl . $avatarUrl;
    }

    private static function rewriteLegacyRemoteAvatar(string $avatarUrl, string $baseUrl, string $tenant): string
    {
        if (!preg_match('/s3\.amazonaws\.com|s3\.[a-z0-9\-]+\.amazonaws\.com/i', $avatarUrl)) {
            return $avatarUrl;
        }

        $pathOnly = parse_url($avatarUrl, PHP_URL_PATH);
        $pathOnly = is_string($pathOnly) ? trim($pathOnly, '/') : '';
        $segments = $pathOnly !== '' ? explode('/', $pathOnly) : [];
        $filename = $pathOnly !== '' ? basename($pathOnly) : '';

        if ($filename === '' || $filename === '.' || $filename === '..') {
            return $avatarUrl;
        }

        $url = $baseUrl . '/media/serve?type=avatars&key=' . rawurlencode($filename);
        if (count($segments) >= 3 && $segments[1] === 'avatars') {
            $url .= '&tenant=' . rawurlencode($segments[0]);
        } elseif ($tenant !== '') {
            $url .= '&tenant=' . rawurlencode($tenant);
        }

        return $url;
    }

    /**
     * Caminho relativo estável para gravar em usuarios.avatar_url (sem depender do host).
     */
    public static function buildStoredAvatarPath(string $fileName, ?string $tenantSlug = null): string
    {
        $tenantSlug = $tenantSlug ?? self::resolveTenantSlug();
        $path = '/media/serve?type=avatars&key=' . rawurlencode($fileName);
        if ($tenantSlug !== '') {
            $path .= '&tenant=' . rawurlencode($tenantSlug);
        }

        return $path;
    }

    private static function resolveTenantSlug(): string
    {
        $fromSession = trim((string) ($_SESSION['tenant_slug'] ?? ''));
        if ($fromSession !== '') {
            return $fromSession;
        }
        if (defined('TENANT_SLUG') && trim((string) TENANT_SLUG) !== '') {
            return trim((string) TENANT_SLUG);
        }

        return '';
    }
}
