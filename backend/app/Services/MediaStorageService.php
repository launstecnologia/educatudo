<?php
/**
 * EducaTudo - Armazenamento unificado de mídia (local ou AWS S3)
 * Pastas por tipo: layout/, provas/questoes/, chat/, jornadas/exercicios/, redacoes/, essays/proposals/
 */
require_once __DIR__ . '/../../vendor/autoload.php';

class MediaStorageService
{
    private $config;
    private $s3Client = null;
    private $bucket = null;
    private $useS3 = false;

    /** Tipos que sempre usam storage local (storage/files/{slug}/), mesmo com S3 configurado */
    private static $localFilesTypes = ['layout', 'avatars', 'professores', 'notifications'];

    /** Tipos que usam S3 quando disponível, mas mantêm isolamento por tenant no fallback local */
    private static $tenantIsolatedLocalTypes = ['dashboard_sliders'];

    /** Mapeamento tipo interno -> prefixo S3 (e subpasta local) */
    private static $typePrefixes = [
        'layout' => 'layout',
        'dashboard_sliders' => 'dashboard/sliders',
        'professores' => 'professores',
        'avatars' => 'avatars',
        'provas_questoes' => 'provas/questoes',
        'chat' => 'chat',
        'jornadas_exercicios' => 'jornadas/exercicios',
        'jornadas_documentos' => 'documentos',
        'jornadas_videos' => 'videos',
        'redacoes' => 'redacoes',
        'essays_proposals' => 'essays/proposals',
        'essays_submissions' => 'essays/submissions',
        'essays_feedback_audio' => 'essays/feedback_audio',
        'arquivos' => 'arquivos',
        'apostilas' => 'apostilas',
        'provas_marcacao_final' => 'provas/marcacao_final',
        'tickets' => 'tickets',
        'notifications' => 'notifications',
        'simulados_questoes' => 'simulados/questoes',
        'simulados_alternativas' => 'simulados/alternativas',
    ];

    public function __construct(array $config)
    {
        $this->config = $config;
        $media = $config['media'] ?? [];
        $aws = $config['aws'] ?? [];

        $storage = $media['storage'] ?? 'local';
        $bucket = trim($aws['bucket'] ?? '');
        $key = trim($aws['key'] ?? '');
        $secret = trim($aws['secret'] ?? '');

        if ($storage === 's3' && $bucket !== '' && $key !== '' && $secret !== '') {
            $this->prepareAwsRuntimeEnv();
            $this->bucket = $bucket;
            $this->useS3 = true;
            $this->s3Client = new Aws\S3\S3Client([
                'version' => 'latest',
                'region' => $aws['region'] ?? 'us-east-1',
                'defaults_mode' => 'standard',
                // Evita leitura de ~/.aws/config em hosts com open_basedir restrito.
                'use_aws_shared_config_files' => false,
                'credentials' => [
                    'key' => $key,
                    'secret' => $secret,
                ],
            ]);
        }
    }

    public function isS3(): bool
    {
        return $this->useS3;
    }

    /**
     * Indica se o tipo usa sempre storage local (storage/files/{slug}/).
     */
    public function useLocalFilesForType(string $type): bool
    {
        return in_array($type, self::$localFilesTypes, true);
    }

    /**
     * Slug do tenant para path local (storage/files/{slug}/). Retorna 'default' se vazio.
     */
    private function getTenantSlug(): string
    {
        $slug = trim((string) ($this->config['tenant']['slug'] ?? ''));
        if ($slug === '' && defined('TENANT_SLUG')) {
            $slug = trim((string) TENANT_SLUG);
        }
        if ($slug === '') {
            $slug = trim((string) ($this->config['school']['code'] ?? ''));
        }
        return $slug !== '' ? $slug : 'default';
    }

    /**
     * Prefixo S3 e subpasta local para um tipo. Opcional: prefixo escola (multi-tenant).
     * Estrutura: {slug-escola}/{tipo}
     */
    private function fullPrefix(string $type, ?string $key = null): string
    {
        $prefix = self::$typePrefixes[$type] ?? $type;
        if (empty($this->config['media']['tenant_prefix'])) {
            return $prefix;
        }

        $key = $key !== null ? $this->normalizeKey($key) : null;
        $tenantSlug = trim((string) ($this->config['tenant']['slug'] ?? ''));
        if ($tenantSlug === '') {
            $tenantSlug = trim((string) ($this->config['school']['code'] ?? ''));
        }

        // Compatibilidade legada: layout "escola_*" sem tenant informado continua no prefixo antigo.
        if ($type === 'layout' && $key !== null && strpos($key, 'escola_') === 0 && $tenantSlug === '') {
            return $prefix;
        }
        if ($tenantSlug === '') {
            return $prefix;
        }

        return trim($tenantSlug, '/') . '/' . $prefix;
    }

    /**
     * Monta key com prefixo de role/userId.
     * Ex: userKey('student', 42, 'foto.jpg') => 'student/42/foto.jpg'
     */
    public static function userKey(string $role, $userId, string $filename): string
    {
        $role = trim($role);
        $userId = (int) $userId;
        $filename = trim($filename, '/');
        return "{$role}/{$userId}/{$filename}";
    }

    /**
     * Caminho local base para um tipo (usa paths do config ou padrão).
     * Para layout/avatars/professores usa storage/files/{slug}/{tipo}.
     */
    private function localBasePath(string $type): string
    {
        if (in_array($type, self::$tenantIsolatedLocalTypes, true)) {
            $base = rtrim($this->config['media']['local_base'] ?? __DIR__ . '/../../', DIRECTORY_SEPARATOR);
            $filesBase = trim((string) ($this->config['media']['files_base'] ?? 'storage/files'), '/');
            $slug = $this->getTenantSlug();
            $sub = self::$typePrefixes[$type] ?? $type;
            return $base . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $filesBase) . DIRECTORY_SEPARATOR
                . $slug . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $sub);
        }

        if ($this->useLocalFilesForType($type)) {
            $base = rtrim($this->config['media']['local_base'] ?? __DIR__ . '/../../', DIRECTORY_SEPARATOR);
            $filesBase = trim((string) ($this->config['media']['files_base'] ?? 'storage/files'), '/');
            $slug = $this->getTenantSlug();
            $sub = self::$typePrefixes[$type] ?? $type;
            return $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $filesBase) . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub);
        }

        $media = $this->config['media'] ?? [];
        $paths = $media['local_paths'] ?? [];
        if (isset($paths[$type]) && $paths[$type] !== '') {
            $p = $paths[$type];
            return strpos($p, '/') === 0 ? $p : (rtrim($media['local_base'] ?? __DIR__ . '/../../', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $p);
        }
        $base = rtrim($media['local_base'] ?? __DIR__ . '/../../', DIRECTORY_SEPARATOR);
        $sub = self::$typePrefixes[$type] ?? $type;
        return $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub);
    }

    /**
     * Salva arquivo. $key = nome do arquivo ou path relativo (ex: logo_123.jpg).
     * $sourcePath = caminho do arquivo temporário (upload).
     */
    public function put(string $type, string $key, string $sourcePath, ?string $contentType = null): bool
    {
        $key = $this->normalizeKey($key);

        if ($this->useLocalFilesForType($type)) {
            $basePath = $this->localBasePath($type);
            $fullPath = $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                if (!@mkdir($dir, 0775, true) && !@mkdir($dir, 0777, true)) {
                    error_log('MediaStorageService put (local): não foi possível criar diretório: ' . $dir . ' | ' . ((error_get_last() ?? [])['message'] ?? ''));
                    return false;
                }
                @chmod($dir, 0775);
            }
            $ok = @copy($sourcePath, $fullPath) || @move_uploaded_file($sourcePath, $fullPath);
            if (!$ok) {
                error_log('MediaStorageService put (local): falha ao gravar em ' . $fullPath . ' | ' . ((error_get_last() ?? [])['message'] ?? '') . ' | source: ' . (is_file($sourcePath) ? 'ok' : 'missing'));
            }
            return $ok;
        }

        if ($this->useS3) {
            $body = file_get_contents($sourcePath);
            if ($body === false) {
                return false;
            }
            $s3Key = $this->buildS3ObjectKey($type, $key);
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
                'Body' => $body,
                'ContentType' => $contentType ?: 'application/octet-stream',
            ];
            try {
                $this->s3Client->putObject($params);
                return true;
            } catch (Exception $e) {
                error_log('MediaStorageService S3 putObject: ' . $e->getMessage());
                return false;
            }
        }

        $basePath = $this->localBasePath($type);
        $fullPath = $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true) || @mkdir($dir, 0777, true);
        }
        return @copy($sourcePath, $fullPath) || @move_uploaded_file($sourcePath, $fullPath);
    }

    /**
     * Retorna URL assinada para exibição inline (S3) ou null (servir localmente).
     */
    public function getViewUrl(string $type, string $key, string $filename, int $expiresIn = 900): ?string
    {
        $key = $this->normalizeKey($key);
        if (!$this->useS3) {
            return null;
        }
        $s3Key = $this->buildS3ObjectKey($type, $key);
        try {
            $contentType = $this->guessMimeByFilename($filename);
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
                // Evita erro InvalidArgument no S3 com nomes UTF-8/acentuados.
                'ResponseContentDisposition' => 'inline',
                'ResponseContentType' => $contentType,
            ]);
            $request = $this->s3Client->createPresignedRequest($cmd, "+{$expiresIn} seconds");
            return (string) $request->getUri();
        } catch (Exception $e) {
            error_log('MediaStorageService S3 getViewUrl: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * URL para exibição: S3 = URL assinada; local = URL pública relativa ao app.
     * Para layout/avatars/professores retorna /media/serve?type=...&key=...&tenant=slug.
     */
    public function getDisplayUrl(string $type, string $key): string
    {
        $key = $this->normalizeKey($key);
        if ($key === '') {
            return '';
        }
        if ($this->useLocalFilesForType($type)) {
            $baseUrl = rtrim($this->config['app']['url'] ?? '', '/');
            $url = $baseUrl . '/media/serve?type=' . rawurlencode($type) . '&key=' . rawurlencode($key);
            $slug = $this->getTenantSlug();
            if ($slug !== 'default') {
                $url .= '&tenant=' . rawurlencode($slug);
            }
            return $url;
        }
        if ($this->useS3) {
            $url = $this->getViewUrl($type, $key, basename($key));
            return $url ?? '';
        }
        $media = $this->config['media'] ?? [];
        $paths = $media['local_paths'] ?? [];
        $relative = $paths[$type] ?? (self::$typePrefixes[$type] ?? $type);
        $baseUrl = rtrim($this->config['app']['url'] ?? '', '/');
        return $baseUrl . '/' . trim($relative, '/') . '/' . $key;
    }

    /**
     * Caminho absoluto no disco. Para layout/avatars/professores sempre retorna path local.
     * Para outros tipos retorna null se S3.
     */
    public function getLocalPath(string $type, string $key): ?string
    {
        $key = $this->normalizeKey($key);
        if ($key === '') {
            return null;
        }
        if ($this->useLocalFilesForType($type)) {
            $basePath = $this->localBasePath($type);
            return $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
        }
        if ($this->useS3) {
            return null;
        }
        $basePath = $this->localBasePath($type);
        $path = $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
        return $path;
    }

    /**
     * Verifica se o arquivo existe.
     */
    public function exists(string $type, string $key): bool
    {
        $key = $this->normalizeKey($key);
        if ($this->useLocalFilesForType($type)) {
            $path = $this->getLocalPath($type, $key);
            return $path !== null && file_exists($path) && is_readable($path);
        }
        if ($this->useS3) {
            $s3Key = $this->buildS3ObjectKey($type, $key);
            try {
                $this->s3Client->headObject([
                    'Bucket' => $this->bucket,
                    'Key' => $s3Key,
                ]);
                return true;
            } catch (Exception $e) {
                return false;
            }
        }
        $path = $this->getLocalPath($type, $key);
        return $path && file_exists($path) && is_readable($path);
    }

    /**
     * Remove arquivo no storage atual.
     */
    public function delete(string $type, string $key): bool
    {
        $key = $this->normalizeKey($key);
        if ($key === '') {
            return false;
        }

        if ($this->useLocalFilesForType($type)) {
            $path = $this->getLocalPath($type, $key);
            if ($path === null || !file_exists($path)) {
                return false;
            }
            return @unlink($path);
        }

        if ($this->useS3) {
            $s3Key = $this->buildS3ObjectKey($type, $key);
            try {
                $this->s3Client->deleteObject([
                    'Bucket' => $this->bucket,
                    'Key' => $s3Key,
                ]);
                return true;
            } catch (Exception $e) {
                error_log('MediaStorageService S3 deleteObject: ' . $e->getMessage());
                return false;
            }
        }

        $path = $this->getLocalPath($type, $key);
        if (!$path || !file_exists($path)) {
            return false;
        }
        return @unlink($path);
    }

    /**
     * Retorna o conteúdo do arquivo (para uso em OCR, etc.). Local: lê do disco; S3: baixa do bucket.
     * @return string|null Conteúdo binário do arquivo ou null se não existir/erro
     */
    public function getContents(string $type, string $key): ?string
    {
        $key = $this->normalizeKey($key);
        if ($key === '') {
            return null;
        }
        if ($this->useLocalFilesForType($type)) {
            $path = $this->getLocalPath($type, $key);
            if ($path === null || !is_file($path) || !is_readable($path)) {
                return null;
            }
            $content = @file_get_contents($path);
            return $content !== false ? $content : null;
        }
        if ($this->useS3) {
            $s3Key = $this->buildS3ObjectKey($type, $key);
            try {
                $result = $this->s3Client->getObject([
                    'Bucket' => $this->bucket,
                    'Key' => $s3Key,
                ]);
                $body = $result['Body'] ?? null;
                if ($body instanceof \Psr\Http\Message\StreamInterface) {
                    return $body->getContents();
                }
                return $body !== null ? (string) $body : null;
            } catch (Exception $e) {
                error_log('MediaStorageService S3 getContents: ' . $e->getMessage());
                return null;
            }
        }
        $path = $this->getLocalPath($type, $key);
        if (!$path || !is_file($path) || !is_readable($path)) {
            return null;
        }
        $content = @file_get_contents($path);
        return $content !== false ? $content : null;
    }

    /**
     * Faz stream inline do arquivo S3 para manter acesso pela URL da aplicação.
     */
    public function streamInline(string $type, string $key, string $filename, string $contentType = 'application/octet-stream'): bool
    {
        return $this->streamWithDisposition($type, $key, $filename, $contentType, 'inline');
    }

    /**
     * Stream como attachment (ex.: SVG legado — evita execução como stored XSS no browser).
     */
    public function streamAttachment(string $type, string $key, string $filename, string $contentType = 'application/octet-stream'): bool
    {
        return $this->streamWithDisposition($type, $key, $filename, $contentType, 'attachment');
    }

    private function streamWithDisposition(
        string $type,
        string $key,
        string $filename,
        string $contentType,
        string $disposition
    ): bool {
        $key = $this->normalizeKey($key);
        if (!$this->useS3 || $key === '') {
            return false;
        }
        $disposition = $disposition === 'attachment' ? 'attachment' : 'inline';

        $s3Key = $this->buildS3ObjectKey($type, $key);
        $safeName = str_replace('"', '\\"', $filename);

        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
                'ResponseContentDisposition' => $disposition . '; filename="' . $safeName . '"',
                'ResponseContentType' => $contentType,
            ]);

            if (!headers_sent()) {
                header('Content-Type: ' . $contentType);
                header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
                header('X-Content-Type-Options: nosniff');
                if (isset($result['ContentLength'])) {
                    header('Content-Length: ' . (int) $result['ContentLength']);
                }
                header('Cache-Control: private, max-age=3600');
            }

            $body = $result['Body'] ?? null;
            if ($body instanceof \Psr\Http\Message\StreamInterface) {
                while (!$body->eof()) {
                    echo $body->read(8192);
                }
            } else {
                echo (string) $body;
            }

            return true;
        } catch (Exception $e) {
            error_log('MediaStorageService S3 streamWithDisposition: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Monta a key final no S3 sem duplicar prefixos.
     */
    private function buildS3ObjectKey(string $type, string $key): string
    {
        $key = $this->normalizeKey($key);
        $typePrefix = trim((string)(self::$typePrefixes[$type] ?? $type), '/');
        $fullPrefix = trim($this->fullPrefix($type, $key), '/');
        $tenantSlug = trim((string)($this->config['tenant']['slug'] ?? ''));

        if ($key === '') {
            return $fullPrefix;
        }

        // Já veio como key completa no padrão final.
        if ($fullPrefix !== '' && ($key === $fullPrefix || strpos($key, $fullPrefix . '/') === 0)) {
            return $key;
        }

        // Quando já vem com "<tipo>/arquivo" e há tenant, prefixa apenas o tenant.
        if ($tenantSlug !== '' && $typePrefix !== '' && strpos($key, $typePrefix . '/') === 0) {
            return $tenantSlug . '/' . $key;
        }

        // Já veio com "<tenant>/<tipo>/arquivo".
        if ($tenantSlug !== '' && strpos($key, $tenantSlug . '/') === 0) {
            return $key;
        }

        // Sem tenant: se já vem com "<tipo>/arquivo", usa direto.
        if ($tenantSlug === '' && $typePrefix !== '' && strpos($key, $typePrefix . '/') === 0) {
            return $key;
        }

        return $fullPrefix . '/' . $key;
    }

    /**
     * Retorna tipos suportados (para validação).
     */
    public static function getSupportedTypes(): array
    {
        return array_keys(self::$typePrefixes);
    }

    private function normalizeKey(string $key): string
    {
        $key = str_replace('\\', '/', trim($key));
        $key = trim($key, '/');
        if ($key === '' || str_contains($key, "\0") || preg_match('#(^|/)\.\.(/|$)#', $key) === 1) {
            return '';
        }
        return $key;
    }

    private function guessMimeByFilename(string $filename): string
    {
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $map = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'opus' => 'audio/opus',
            'wav' => 'audio/wav',
            'html' => 'text/html',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        return $map[$ext] ?? 'application/octet-stream';
    }

    /**
     * Evita warnings do AWS SDK em ambientes com open_basedir restrito
     * (ex.: tentativa de ler ~/.aws/config fora das pastas permitidas).
     */
    private function prepareAwsRuntimeEnv(): void
    {
        $tmp = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        if ($tmp === '') {
            $tmp = '/tmp';
        }
        $this->setEnvIfEmpty('HOME', $tmp);
        $this->setEnvIfEmpty('AWS_CONFIG_FILE', $tmp . '/aws-config');
        $this->setEnvIfEmpty('AWS_SHARED_CREDENTIALS_FILE', $tmp . '/aws-credentials');
        $this->setEnvIfEmpty('AWS_EC2_METADATA_DISABLED', 'true');
    }

    private function setEnvIfEmpty(string $name, string $value): void
    {
        $current = getenv($name);
        if ($current !== false && $current !== '') {
            return;
        }
        if (function_exists('putenv')) {
            @putenv($name . '=' . $value);
        }
        if (!isset($_ENV[$name]) || $_ENV[$name] === '') {
            $_ENV[$name] = $value;
        }
        if (!isset($_SERVER[$name]) || $_SERVER[$name] === '') {
            $_SERVER[$name] = $value;
        }
    }
}
