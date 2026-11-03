<?php

namespace App\Modulos\Mural\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Modulos\Mural\Services\MuralRecadoService;

/**
 * Entrega o anexo do mural só para quem pode ver o recado.
 */
class MuralAnexoController extends BaseController
{
    public function baixar($id): void
    {
        $auth = new AuthManager();
        if (!$auth->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        $servico = new MuralRecadoService();
        $anexo = $servico->buscarAnexo((int) $id);
        $user = $auth->getUser();
        if (!$anexo || !$servico->usuarioPodeVerAnexo(is_array($user) ? $user : [], (int) $anexo['mural_recado_id'])) {
            http_response_code(404);
            echo 'Anexo não encontrado.';
            return;
        }
        $path = $servico->caminhoFisicoAnexo((string) $anexo['arquivo']);
        if ($path === null) {
            http_response_code(404);
            echo 'Arquivo não encontrado.';
            return;
        }
        $permitidos = [
            'image/jpeg' => true,
            'image/png' => true,
            'image/webp' => true,
            'image/gif' => true,
            'application/pdf' => true,
        ];
        $mime = (string) ($anexo['tipo_arquivo'] ?? '');
        if (!isset($permitidos[$mime])) {
            $mime = 'application/octet-stream';
        }
        $nome = str_replace(['"', "\r", "\n"], '', (string) ($anexo['arquivo_nome'] ?? 'anexo'));
        $disposicao = $mime === 'application/pdf' ? 'attachment' : 'inline';
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . $disposicao . '; filename="' . $nome . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }
}
