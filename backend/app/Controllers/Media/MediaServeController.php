<?php
/**
 * Serve mídia (layout, essays, etc.) por type + key.
 * S3/local: streaming pelo backend com fallback de keys legadas.
 *
 * Tipos públicos (logo/capa/avatar): acessíveis sem login, sempre no tenant do Host.
 * Tipos privados (chat, redações, tickets…): exigem sessão autenticada.
 * Parâmetro ?tenant= só é aceito se coincidir com o tenant resolvido na request.
 */
require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Services/MediaStorageService.php';

class MediaServeController extends BaseController
{
    private const TYPE_PREFIXES = [
        'layout' => 'layout',
        'dashboard_sliders' => 'dashboard/sliders',
        'professores' => 'professores',
        'avatars' => 'avatars',
        'essays_proposals' => 'redacoes_propostas',
        'redacoes' => 'redacoes',
        'provas_questoes' => 'provas/questoes',
        'chat' => 'chat',
        'jornadas_exercicios' => 'jornadas/exercicios',
        'jornadas_documentos' => 'documentos',
        'jornadas_videos' => 'videos',
        'arquivos' => 'arquivos',
        'apostilas' => 'apostilas',
        'tickets' => 'tickets',
        'simulados_questoes' => 'simulados/questoes',
        'simulados_alternativas' => 'simulados/alternativas',
    ];

    /** Tipos que podem aparecer em páginas públicas (login, branding). */
    private const PUBLIC_TYPES = [
        'layout',
        'dashboard_sliders',
        'professores',
        'avatars',
        'essays_proposals',
    ];

    /**
     * GET /media/serve?type=layout&key=teacher/45/logo_123.jpg
     */
    public function serve()
    {
        if (ob_get_level()) {
            ob_end_clean();
        }
        $type = isset($_GET['type']) ? trim((string) $_GET['type']) : '';
        $key = isset($_GET['key']) ? trim((string) $_GET['key']) : '';
        $tenantSlug = isset($_GET['tenant']) ? strtolower(trim((string) $_GET['tenant'])) : '';
        $allowedTypes = [
            'layout', 'dashboard_sliders', 'professores', 'avatars', 'essays_proposals',
            'redacoes', 'provas_questoes', 'chat', 'jornadas_exercicios', 'jornadas_documentos',
            'jornadas_videos', 'arquivos', 'apostilas', 'tickets',
        ];
        if ($type === '' || $key === '' || !in_array($type, $allowedTypes, true)) {
            http_response_code(400);
            exit;
        }
        if (strpos($key, '..') !== false || preg_match('/[^a-zA-Z0-9_\-\.\/]/', $key)) {
            http_response_code(400);
            exit;
        }
        if ($tenantSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $tenantSlug) !== 1) {
            http_response_code(400);
            exit;
        }

        $isMaster = !empty($_SESSION['master_user_id']);
        $isLoggedIn = $isMaster
            || (!empty($_SESSION['user_id']) && !empty($_SESSION['user_type']));

        // Tipos privados: exige sessão autenticada (cookie enviado pelo <img>/<a>).
        if (!in_array($type, self::PUBLIC_TYPES, true) && !$isLoggedIn) {
            http_response_code(401);
            exit;
        }

        $config = $this->config;
        $resolvedTenant = strtolower(trim((string) (
            (defined('TENANT_SLUG') ? TENANT_SLUG : '')
            ?: ($config['tenant']['slug'] ?? '')
            ?: ($config['school']['code'] ?? '')
        )));

        // Impede troca de tenant via query por anônimos/usuários de escola.
        // Exceção: sessão Master (tickets/assets de escolas no painel master.*).
        if ($tenantSlug !== '') {
            $sameTenant = $resolvedTenant !== '' && hash_equals($resolvedTenant, $tenantSlug);
            if (!$sameTenant && !$isMaster) {
                http_response_code(403);
                exit;
            }
            if (!$sameTenant && $isMaster) {
                $config['tenant'] = array_merge($config['tenant'] ?? [], ['slug' => $tenantSlug]);
                $config['school'] = array_merge($config['school'] ?? [], ['code' => $tenantSlug]);
                $config['media'] = array_merge($config['media'] ?? [], ['tenant_prefix' => true]);
                $resolvedTenant = $tenantSlug;
            }
        }

        // Compatibilidade legada: arquivos antigos de layout sem prefixo tenant.
        if ($type === 'layout' && strpos($key, 'escola_') === 0 && $tenantSlug === '') {
            $config['school'] = array_merge($config['school'] ?? [], ['code' => '']);
            $config['tenant'] = array_merge($config['tenant'] ?? [], ['slug' => '']);
            $config['media'] = array_merge($config['media'] ?? [], ['tenant_prefix' => false]);
        }

        $media = new MediaStorageService($config);
        $serveLocalTypes = ['layout', 'avatars', 'professores'];
        $serveFromLocal = in_array($type, $serveLocalTypes, true);
        $candidateKeys = $this->buildCandidateKeys($type, $key, $resolvedTenant);

        if (!$serveFromLocal && $media->isS3()) {
            $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
            $contentType = $this->mimeForExtension($ext);
            $inline = $this->shouldServeInline($ext);

            foreach ($candidateKeys as $candidateKey) {
                $ok = $inline
                    ? $media->streamInline($type, $candidateKey, basename($candidateKey), $contentType)
                    : $media->streamAttachment($type, $candidateKey, basename($candidateKey), $contentType);
                if ($ok) {
                    exit;
                }
            }

            // Compatibilidade: alguns registros antigos podem ter sido salvos sem prefixo de tenant.
            $legacyConfig = $config;
            $legacyConfig['media'] = array_merge($legacyConfig['media'] ?? [], ['tenant_prefix' => false]);
            $legacyConfig['tenant'] = array_merge($legacyConfig['tenant'] ?? [], ['slug' => '']);
            $legacyConfig['school'] = array_merge($legacyConfig['school'] ?? [], ['code' => '']);
            $legacyMedia = new MediaStorageService($legacyConfig);
            foreach ($candidateKeys as $candidateKey) {
                $ok = $inline
                    ? $legacyMedia->streamInline($type, $candidateKey, basename($candidateKey), $contentType)
                    : $legacyMedia->streamAttachment($type, $candidateKey, basename($candidateKey), $contentType);
                if ($ok) {
                    exit;
                }
            }
        }

        $path = null;
        foreach ($candidateKeys as $candidateKey) {
            $candidatePath = $media->getLocalPath($type, $candidateKey);
            if ($candidatePath !== null && is_file($candidatePath)) {
                $path = $candidatePath;
                break;
            }
        }
        if ($path === null || !is_file($path)) {
            http_response_code(404);
            exit;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $disposition = $this->shouldServeInline($ext) ? 'inline' : 'attachment';
        header('Content-Type: ' . $this->mimeForExtension($ext));
        header('Content-Disposition: ' . $disposition . '; filename="' . addslashes(basename($path)) . '"');
        header('Cache-Control: ' . (in_array($type, self::PUBLIC_TYPES, true) ? 'public, max-age=86400' : 'private, max-age=3600'));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function mimeForExtension(string $ext): string
    {
        $mimes = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf', 'txt' => 'text/plain', 'csv' => 'text/csv',
            'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
        return $mimes[$ext] ?? 'application/octet-stream';
    }

    /** SVG nunca é inline (mitiga stored XSS de arquivos legados). */
    private function shouldServeInline(string $ext): bool
    {
        return $ext !== 'svg';
    }

    private function buildCandidateKeys(string $type, string $key, string $tenantSlug): array
    {
        $normalized = trim(str_replace('\\', '/', $key), '/');
        $tenantSlug = trim(strtolower($tenantSlug), '/');
        $typePrefix = trim((string)(self::TYPE_PREFIXES[$type] ?? $type), '/');
        $candidates = [];

        if ($normalized !== '') {
            $candidates[] = $normalized;
        }

        if ($tenantSlug !== '' && $normalized !== '' && strpos($normalized, $tenantSlug . '/') === 0) {
            $candidates[] = substr($normalized, strlen($tenantSlug . '/'));
        }

        if ($typePrefix !== '' && $normalized !== '' && strpos($normalized, $typePrefix . '/') === 0) {
            $candidates[] = substr($normalized, strlen($typePrefix . '/'));
        }

        if ($tenantSlug !== '' && $typePrefix !== '' && $normalized !== '' && strpos($normalized, $tenantSlug . '/' . $typePrefix . '/') === 0) {
            $candidates[] = substr($normalized, strlen($tenantSlug . '/' . $typePrefix . '/'));
        }

        if ($normalized !== '' && strpos($normalized, '/documentos/') !== false) {
            $parts = explode('/documentos/', $normalized, 2);
            if (isset($parts[1]) && $parts[1] !== '') {
                $candidates[] = $parts[1];
            }
        }

        return array_values(array_unique(array_filter($candidates, static function ($value) {
            return is_string($value) && trim($value) !== '';
        })));
    }
}
