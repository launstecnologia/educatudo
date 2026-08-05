<?php
/**
 * EducaTudo - Armazenamento do Drive (local ou AWS S3)
 * Quando AWS_BUCKET e credenciais estão definidos, usa S3; senão usa app/storage/drive.
 */
require_once __DIR__ . '/../../vendor/autoload.php';

class DriveStorageService
{
    private $config;
    private $localPath;
    private $s3Client = null;
    private $bucket = null;
    private $useS3 = false;

    public function __construct(array $config)
    {
        $this->config = $config;
        $drive = $config['drive'] ?? [];
        $aws = $config['aws'] ?? [];
        $this->localPath = rtrim($drive['local_path'] ?? __DIR__ . '/../../storage/drive', DIRECTORY_SEPARATOR);

        $storage = $drive['storage'] ?? 'local';
        $bucket = trim($aws['bucket'] ?? '');
        $key = trim($aws['key'] ?? '');
        $secret = trim($aws['secret'] ?? '');

        if ($storage === 's3' && $bucket !== '' && $key !== '' && $secret !== '') {
            $this->bucket = $bucket;
            $this->useS3 = true;
            $this->s3Client = new Aws\S3\S3Client([
                'version' => 'latest',
                'region' => $aws['region'] ?? 'us-east-1',
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
     * Salva arquivo. $key = caminho lógico (ex: student_1/20240217120000_abc.pdf).
     * $sourcePath = caminho do arquivo temporário (upload).
     */
    public function put(string $key, string $sourcePath, ?string $contentType = null): bool
    {
        $key = $this->normalizeKey($key);
        if ($this->useS3) {
            $body = file_get_contents($sourcePath);
            if ($body === false) {
                return false;
            }
            $params = [
                'Bucket' => $this->bucket,
                'Key' => 'drive/' . $key,
                'Body' => $body,
                'ContentType' => $contentType ?: 'application/octet-stream',
            ];
            try {
                $this->s3Client->putObject($params);
                return true;
            } catch (Exception $e) {
                error_log('DriveStorageService S3 putObject: ' . $e->getMessage());
                return false;
            }
        }

        $fullPath = $this->localPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true) || @mkdir($dir, 0777, true);
        }
        return @copy($sourcePath, $fullPath) || @move_uploaded_file($sourcePath, $fullPath);
    }

    /**
     * Retorna URL assinada para download (S3) ou null (servir localmente).
     */
    public function getDownloadUrl(string $key, string $filename, int $expiresIn = 900): ?string
    {
        $key = $this->normalizeKey($key);
        if (!$this->useS3) {
            return null;
        }
        try {
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => 'drive/' . $key,
                'ResponseContentDisposition' => 'attachment; filename="' . addslashes($filename) . '"',
            ]);
            $request = $this->s3Client->createPresignedRequest($cmd, "+{$expiresIn} seconds");
            return (string) $request->getUri();
        } catch (Exception $e) {
            error_log('DriveStorageService S3 getDownloadUrl: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Retorna URL assinada para exibição inline (S3) ou null (servir localmente).
     */
    public function getViewUrl(string $key, string $filename, int $expiresIn = 900): ?string
    {
        $key = $this->normalizeKey($key);
        if (!$this->useS3) {
            return null;
        }
        try {
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => 'drive/' . $key,
                'ResponseContentDisposition' => 'inline; filename="' . addslashes($filename) . '"',
            ]);
            $request = $this->s3Client->createPresignedRequest($cmd, "+{$expiresIn} seconds");
            return (string) $request->getUri();
        } catch (Exception $e) {
            error_log('DriveStorageService S3 getViewUrl: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Faz stream inline do arquivo S3 via aplicação.
     */
    public function streamInline(string $key, string $filename, string $contentType = 'application/octet-stream'): bool
    {
        $key = $this->normalizeKey($key);
        if (!$this->useS3 || $key === '') {
            return false;
        }
        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->bucket,
                'Key' => 'drive/' . $key,
                'ResponseContentDisposition' => 'inline; filename="' . addslashes($filename) . '"',
                'ResponseContentType' => $contentType,
            ]);

            if (!headers_sent()) {
                header('Content-Type: ' . $contentType);
                header('Content-Disposition: inline; filename="' . str_replace('"', '\\"', $filename) . '"');
                if (isset($result['ContentLength'])) {
                    header('Content-Length: ' . (int)$result['ContentLength']);
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
            error_log('DriveStorageService S3 streamInline: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Caminho absoluto no disco (apenas modo local). Retorna null se S3.
     */
    public function getLocalPath(string $key): ?string
    {
        if ($this->useS3) {
            return null;
        }
        $key = $this->normalizeKey($key);
        $path = $this->localPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
        return $path;
    }

    /**
     * Remove arquivo do storage.
     */
    public function delete(string $key): bool
    {
        $key = $this->normalizeKey($key);
        if ($this->useS3) {
            try {
                $this->s3Client->deleteObject([
                    'Bucket' => $this->bucket,
                    'Key' => 'drive/' . $key,
                ]);
                return true;
            } catch (Exception $e) {
                error_log('DriveStorageService S3 deleteObject: ' . $e->getMessage());
                return false;
            }
        }
        $path = $this->getLocalPath($key);
        return $path && file_exists($path) && @unlink($path);
    }

    /**
     * Verifica se o arquivo existe.
     */
    public function exists(string $key): bool
    {
        $key = $this->normalizeKey($key);
        if ($this->useS3) {
            try {
                $this->s3Client->headObject([
                    'Bucket' => $this->bucket,
                    'Key' => 'drive/' . $key,
                ]);
                return true;
            } catch (Exception $e) {
                return false;
            }
        }
        $path = $this->getLocalPath($key);
        return $path && file_exists($path) && is_readable($path);
    }

    private function normalizeKey(string $key): string
    {
        $key = str_replace('\\', '/', trim($key));
        $key = trim($key, '/');

        // Compatibilidade com chaves legadas no banco.
        if (strpos($key, 'storage/drive/') === 0) {
            $key = substr($key, strlen('storage/drive/'));
        } elseif (strpos($key, 'public/storage/drive/') === 0) {
            $key = substr($key, strlen('public/storage/drive/'));
        } elseif (strpos($key, 'drive/') === 0) {
            $key = substr($key, strlen('drive/'));
        }

        return trim($key, '/');
    }
}
