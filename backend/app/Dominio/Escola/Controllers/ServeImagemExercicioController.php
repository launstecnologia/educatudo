<?php

namespace App\Dominio\Escola\Controllers;
/**
 * Serve imagens de enunciado de exercícios (jornadas).
 * A URL antiga continua válida. Só sai para quem tem sessão e vínculo com a jornada.
 */


use App\Core\BaseController;
use App\Infra\Services\AcessoArquivoPrivado;
use App\Services\MediaStorageService;
if (!class_exists(__NAMESPACE__ . '\\ServeImagemExercicioController', false)) {
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
        if (empty($_SESSION['master_user_id']) && (empty($_SESSION['user_id']) || empty($_SESSION['user_type']))) {
            http_response_code(401);
            header('X-Content-Type-Options: nosniff');
            exit;
        }
        if (!AcessoArquivoPrivado::podeLer('jornadas_exercicios', $filename)) {
            http_response_code(403);
            header('X-Content-Type-Options: nosniff');
            exit;
        }
        $media = new MediaStorageService($this->config);
        if ($media->isS3()) {
            $url = $media->getViewUrl('jornadas_exercicios', $filename, $filename);
            if ($url !== null && $url !== '') {
                header('Cache-Control: private, no-store');
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
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
}

if (!class_exists('ServeImagemExercicioController', false) && class_exists(__NAMESPACE__ . '\\ServeImagemExercicioController', false)) {
    class_alias(__NAMESPACE__ . '\\ServeImagemExercicioController', 'ServeImagemExercicioController');
}
