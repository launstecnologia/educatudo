<?php

namespace App\Dominio\Pessoas\Services;

use App\Core\Database;
use App\Helpers\AvatarUrlHelper;
use App\Infra\Services\MediaStorageService;

/**
 * Upload e persistência da foto de perfil do responsável (app dos pais).
 */
class ResponsavelFotoService
{
    private $db;
    private array $config;
    private static ?bool $temColunaFoto = null;

    public function __construct($db = null, ?array $config = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->config = $config ?? (require __DIR__ . '/../../../../config/app.php');
    }

    public function temColunaFoto(): bool
    {
        if (self::$temColunaFoto !== null) {
            return self::$temColunaFoto;
        }

        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'responsaveis'
                   AND COLUMN_NAME = 'foto_url'
                 LIMIT 1"
            );
            self::$temColunaFoto = !empty($row);
        } catch (\Throwable $e) {
            self::$temColunaFoto = false;
        }

        return self::$temColunaFoto;
    }

    /**
     * @param array $file Entrada de $_FILES
     * @return array{foto_url: string, photo_url: string}
     */
    public function salvar(int $responsavelId, array $file): array
    {
        if ($responsavelId <= 0) {
            throw new \InvalidArgumentException('Responsável inválido.');
        }
        if (!$this->temColunaFoto()) {
            throw new \RuntimeException('Foto de perfil ainda não está disponível neste ambiente.');
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new \InvalidArgumentException('Envie uma imagem.');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Falha no envio da foto. Tente novamente.');
        }
        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new \InvalidArgumentException('Arquivo de foto inválido.');
        }

        $maxSize = 2 * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) > $maxSize) {
            throw new \InvalidArgumentException('A foto deve ter no máximo 2 MB.');
        }

        $tmp = (string) $file['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (finfo_file($finfo, $tmp) ?: '') : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($mimeToExt[$mime])) {
            throw new \InvalidArgumentException('Tipo de arquivo inválido. Use JPG, PNG ou WebP.');
        }
        $ext = $mimeToExt[$mime];

        $key = MediaStorageService::userKey(
            'responsavel',
            $responsavelId,
            'avatar_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext
        );
        $media = new MediaStorageService($this->config);
        if (!$media->put('avatars', $key, $tmp, $mime)) {
            throw new \RuntimeException('Não foi possível salvar a foto. Verifique permissões de storage.');
        }

        $displayUrl = $media->getDisplayUrl('avatars', $key);
        $storedPath = AvatarUrlHelper::buildStoredAvatarPath($key);

        $this->db->update(
            'UPDATE responsaveis SET foto_url = :foto_url, updated_at = NOW() WHERE id = :id',
            ['foto_url' => $storedPath, 'id' => $responsavelId]
        );

        $normalized = AvatarUrlHelper::normalizeAdminAvatarUrl($displayUrl !== '' ? $displayUrl : $storedPath);

        return [
            'foto_url' => $storedPath,
            'photo_url' => $normalized,
        ];
    }

    public function urlNormalizada(?string $fotoUrl): ?string
    {
        if ($fotoUrl === null || trim($fotoUrl) === '') {
            return null;
        }

        return AvatarUrlHelper::normalizeAdminAvatarUrl($fotoUrl);
    }
}

if (!class_exists('ResponsavelFotoService', false) && class_exists(__NAMESPACE__ . '\\ResponsavelFotoService', false)) {
    class_alias(__NAMESPACE__ . '\\ResponsavelFotoService', 'ResponsavelFotoService');
}
