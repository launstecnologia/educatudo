<?php

namespace App\Helpers;
/**
 * Stream de áudio de feedback da correção (local ou S3).
 */

use App\Services\MediaStorageService;
class EssayFeedbackAudioHelper
{
    public static function streamKey(array $config, string $key): void
    {
        $key = trim($key);
        if ($key === '') {
            http_response_code(404);
            exit;
        }
        $media = new MediaStorageService($config);
        if ($media->isS3()) {
            $url = $media->getViewUrl('essays_feedback_audio', $key, basename($key), 3600);
            if ($url !== null && $url !== '') {
                header('Location: ' . $url);
                exit;
            }
        }
        $localPath = $media->getLocalPath('essays_feedback_audio', $key);
        if ($localPath === null || !is_file($localPath) || !is_readable($localPath)) {
            http_response_code(404);
            exit('Arquivo não encontrado');
        }
        $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
        $mimes = [
            'webm' => 'audio/webm',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'mp4' => 'audio/mp4',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'opus' => 'audio/opus',
        ];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($localPath));
        header('Cache-Control: private, max-age=3600');
        readfile($localPath);
        exit;
    }
}

if (!class_exists('EssayFeedbackAudioHelper', false) && class_exists(__NAMESPACE__ . '\\EssayFeedbackAudioHelper', false)) {
    class_alias(__NAMESPACE__ . '\\EssayFeedbackAudioHelper', 'EssayFeedbackAudioHelper');
}
