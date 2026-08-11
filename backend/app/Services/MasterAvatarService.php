<?php
/**
 * Upload de foto de perfil para usuários master (storage/files/master/avatars/).
 */

if (!class_exists('MasterAvatarService')) {

class MasterAvatarService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Faz upload da foto. Retorna URL de exibição ou null se nenhum arquivo enviado.
     * Lança InvalidArgumentException se o arquivo for inválido.
     */
    public function upload(int $userId, string $fieldName = 'avatar_upload'): ?string
    {
        $file = $_FILES[$fieldName] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Falha no envio da foto. Tente novamente.');
        }
        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new InvalidArgumentException('Arquivo de foto inválido.');
        }

        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            throw new InvalidArgumentException('Formato não permitido. Use JPG, PNG, WebP ou GIF.');
        }

        $maxSize = 2 * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxSize) {
            throw new InvalidArgumentException('A foto deve ter no máximo 2 MB.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (finfo_file($finfo, $file['tmp_name']) ?: '') : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!isset($mimeToExt[$mime])) {
            throw new InvalidArgumentException('Tipo de arquivo inválido. Envie uma imagem.');
        }
        $ext = $mimeToExt[$mime];

        if (!class_exists('MediaStorageService')) {
            require_once __DIR__ . '/MediaStorageService.php';
        }

        $config = $this->config;
        $config['school'] = array_merge($config['school'] ?? [], ['code' => 'master']);
        $config['tenant'] = array_merge($config['tenant'] ?? [], ['slug' => 'master']);
        $config['media'] = array_merge($config['media'] ?? [], ['tenant_prefix' => true]);

        $key = MediaStorageService::userKey('master', $userId, 'avatar_' . time() . '.' . $ext);
        $media = new MediaStorageService($config);

        if (!$media->put('avatars', $key, $file['tmp_name'], $mime)) {
            throw new RuntimeException('Não foi possível salvar a foto. Verifique permissões de storage.');
        }

        return $media->getDisplayUrl('avatars', $key);
    }

    public static function iniciais(string $nome): string
    {
        $nome = trim($nome);
        if ($nome === '') {
            return 'AD';
        }
        $partes = preg_split('/\s+/u', $nome, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($partes) >= 2) {
            return mb_strtoupper(
                mb_substr($partes[0], 0, 1) . mb_substr($partes[count($partes) - 1], 0, 1)
            );
        }
        return mb_strtoupper(mb_substr($nome, 0, 2));
    }
}

}
