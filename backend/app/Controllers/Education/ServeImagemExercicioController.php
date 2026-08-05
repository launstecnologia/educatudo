<?php
/**
 * Serve imagens de enunciado de exercícios (jornadas).
 * Rota pública para que o <img> carregue mesmo com cache/CDN.
 */

if (!class_exists('ServeImagemExercicioController')) {
class ServeImagemExercicioController extends BaseController
{
    /**
     * Serve um arquivo de imagem de exercício (MediaStorageService: local ou S3).
     * Rota: /uploads/jornadas/exercicios/{filename}
     */
    public function serve($filename)
    {
        $filename = trim($filename, '/');
        if ($filename === '' || strpos($filename, '..') !== false || preg_match('/[^a-zA-Z0-9_.\-\/]/', $filename)) {
            http_response_code(400);
            exit('Nome de arquivo inválido');
        }
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        if ($media->isS3()) {
            $url = $media->getViewUrl('jornadas_exercicios', $filename, $filename);
            if ($url !== null && $url !== '') {
                header('Location: ' . $url);
                exit;
            }
        }
        $path = $media->getLocalPath('jornadas_exercicios', $filename);
        if ($path === null || !is_file($path)) {
            http_response_code(404);
            exit('Imagem não encontrada');
        }
        $mimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = $mimes[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        readfile($path);
        exit;
    }
}
}
