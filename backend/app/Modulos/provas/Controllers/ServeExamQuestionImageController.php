<?php

namespace App\Modulos\Provas\Controllers;
/**
 * Serve imagens de enunciado/alternativas de questões (provas).
 * Rota pública para que o <img> carregue no editor e na visualização.
 */


use App\Core\BaseController;
use App\Services\MediaStorageService;
if (!class_exists(__NAMESPACE__ . '\\ServeExamQuestionImageController', false)) {
class ServeExamQuestionImageController extends BaseController
{
    /**
     * Serve um arquivo de imagem de questão (MediaStorageService: local ou S3).
     * Rota: /uploads/provas/questoes/{filename}
     */
    public function serve($filename)
    {
        $filename = trim($filename, '/');
        if ($filename === '' || strpos($filename, '..') !== false || preg_match('/[^a-zA-Z0-9_.\-\/]/', $filename)) {
            http_response_code(400);
            exit('Nome de arquivo inválido');
        }
        $media = new MediaStorageService($this->config);
        if ($media->isS3()) {
            $url = $media->getViewUrl('provas_questoes', $filename, $filename);
            if ($url !== null && $url !== '') {
                header('Location: ' . $url);
                exit;
            }
        }
        $path = $media->getLocalPath('provas_questoes', $filename);
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

if (!class_exists('ServeExamQuestionImageController', false) && class_exists(__NAMESPACE__ . '\\ServeExamQuestionImageController', false)) {
    class_alias(__NAMESPACE__ . '\\ServeExamQuestionImageController', 'ServeExamQuestionImageController');
}
