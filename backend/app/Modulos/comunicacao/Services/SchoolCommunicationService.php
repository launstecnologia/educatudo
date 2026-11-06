<?php

namespace App\Modulos\Comunicacao\Services;

use App\Core\Database;
use App\Models\PushNotifications\PushNotification;
use App\Services\FirebaseMessagingService;
use App\Services\MobileDeviceService;

/**
 * Push e anexos da comunicação / calendário escolar.
 */
class SchoolCommunicationService
{
    private $db;
    private PushNotification $notifications;
    private FirebaseMessagingService $firebase;
    private MobileDeviceService $devices;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
        $this->notifications = new PushNotification();
        $this->firebase = new FirebaseMessagingService();
        $this->devices = new MobileDeviceService();
    }

    public function parentIds(string $audience, array $classIds = [], array $studentIds = [], array $serieIds = [], array $cursoIds = []): array
    {
        $where = '1=1';
        $params = [];
        if ($audience === 'turmas') {
            $ids = array_values(array_unique(array_filter(array_map('intval', $classIds))));
            if ($ids === []) return [];
            $where = 'a.turma_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = $ids;
        } elseif ($audience === 'alunos') {
            $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
            if ($ids === []) return [];
            $where = 'a.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = $ids;
        } elseif ($audience === 'series') {
            $ids = array_values(array_unique(array_filter(array_map('intval', $serieIds))));
            if ($ids === []) return [];
            $where = 'a.turma_id IN (SELECT id FROM turmas WHERE serie_id IN (' . implode(',', array_fill(0, count($ids), '?')) . '))';
            $params = $ids;
        } elseif ($audience === 'cursos') {
            $ids = array_values(array_unique(array_filter(array_map('intval', $cursoIds))));
            if ($ids === []) return [];
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            $where = 'a.turma_id IN (SELECT id FROM turmas WHERE curso_novo_id IN (' . $marcas . ') OR curso_id IN (' . $marcas . '))';
            $params = array_merge($ids, $ids);
        }
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT parent_id FROM (
                SELECT a.responsavel_id parent_id FROM alunos a WHERE {$where} AND a.responsavel_id IS NOT NULL
                UNION
                SELECT ar.responsavel_id FROM alunos a INNER JOIN alunos_responsaveis ar ON ar.aluno_id = a.id AND ar.ativo = 1 WHERE {$where}
            ) recipients INNER JOIN responsaveis r ON r.id = recipients.parent_id AND r.ativo = 1",
            array_merge($params, $params)
        );
        return array_values(array_unique(array_map('intval', array_column($rows, 'parent_id'))));
    }

    public function push(array $parentIds, string $title, string $body, string $route, array $data, int $createdBy): array
    {
        $parentIds = array_values(array_unique(array_filter(array_map('intval', $parentIds))));
        if ($parentIds === []) return ['parents' => 0, 'sent' => 0, 'failed' => 0];
        $notificationId = $this->notifications->create($title, $body, $route, 'pais', null, $createdBy);
        $tokens = [];
        foreach ($this->devices->enabledTokensForParents($parentIds) as $device) {
            $tokens[(int)$device['parent_id']][] = (string)$device['fcm_token'];
        }
        $sent = 0;
        $failed = 0;
        foreach ($parentIds as $parentId) {
            $tracking = $this->notifications->addEnvio($notificationId, $parentId, 'pai');
            foreach ($tokens[$parentId] ?? [] as $token) {
                $result = $this->firebase->sendToToken($token, $title, $body, array_merge($data, [
                    'tracking_token' => $tracking,
                    'notificacao_id' => (string)$notificationId,
                    'route' => $route,
                ]));
                if (!empty($result['success'])) {
                    $sent++;
                    $this->notifications->marcarEntregueDestinatario($notificationId, $parentId, 'pai');
                } else {
                    $failed++;
                    if (!empty($result['invalid_token'])) $this->devices->disableToken($token);
                }
            }
        }
        return ['parents' => count($parentIds), 'sent' => $sent, 'failed' => $failed];
    }

    public function saveAttachments(int $communicationId, array $files): int
    {
        if (!isset($files['name']) || !is_array($files['name'])) return 0;
        $allowed = ['image/jpeg','image/png','image/webp','application/pdf','text/plain','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $tenant = defined('TENANT_SLUG') ? preg_replace('/[^a-z0-9_-]/i', '', (string)TENANT_SLUG) : 'default';
        $relativeDir = 'storage/uploads/school-communications/' . $tenant . '/' . $communicationId;
        // app/Modulos/comunicacao/Services → backend/
        $absoluteDir = dirname(__DIR__, 4) . '/' . $relativeDir;
        if (!is_dir($absoluteDir)) @mkdir($absoluteDir, 0775, true);
        $saved = 0;
        foreach ($files['name'] as $index => $original) {
            if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $tmp = (string)($files['tmp_name'][$index] ?? '');
            $size = (int)($files['size'][$index] ?? 0);
            if ($tmp === '' || $size <= 0 || $size > 10 * 1024 * 1024) continue;
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
            if (!in_array($mime, $allowed, true)) continue;
            $extension = strtolower(pathinfo((string)$original, PATHINFO_EXTENSION));
            $filename = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . preg_replace('/[^a-z0-9]/', '', $extension) : '');
            if (!move_uploaded_file($tmp, $absoluteDir . '/' . $filename)) continue;
            $path = '/' . $relativeDir . '/' . $filename;
            $this->db->insert(
                'INSERT INTO school_communication_attachments (communication_id, arquivo, arquivo_nome, tipo_arquivo, tamanho) VALUES (:id, :path, :name, :mime, :size)',
                ['id' => $communicationId, 'path' => $path, 'name' => basename((string)$original), 'mime' => $mime, 'size' => $size]
            );
            $saved++;
        }
        return $saved;
    }

    /**
     * @param array<string,mixed> $file
     * @return array{path:string,name:string}|null
     */
    public function guardarImagem(array $file, string $pasta): ?array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0 || $size > 8 * 1024 * 1024) {
            return null;
        }
        $mapa = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        if (!isset($mapa[$mime])) {
            return null;
        }
        $tenant = defined('TENANT_SLUG') ? preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_SLUG) : 'default';
        $pasta = preg_replace('/[^a-z0-9_-]/i', '', $pasta) ?: 'comunicacao';
        $relative = 'storage/uploads/' . $pasta . '/' . ($tenant !== '' ? $tenant : 'default');
        $absolute = dirname(__DIR__, 4) . '/' . $relative;
        if (!is_dir($absolute) && !@mkdir($absolute, 0775, true) && !is_dir($absolute)) {
            return null;
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $mapa[$mime];
        if (!move_uploaded_file($tmp, $absolute . '/' . $filename)) {
            return null;
        }
        $nome = basename((string) ($file['name'] ?? 'imagem'));

        return ['path' => '/' . $relative . '/' . $filename, 'name' => $nome !== '' ? $nome : 'imagem'];
    }

    public function avisarAdmins(string $titulo, string $conteudo): void
    {
        try {
            $admins = $this->db->fetchAll(
                "SELECT id FROM usuarios WHERE tipo IN ('admin', 'admin_escola') AND ativo = 1"
            );
        } catch (\Throwable $e) {
            $admins = $this->db->fetchAll(
                "SELECT id FROM usuarios WHERE tipo IN ('admin', 'admin_escola')"
            );
        }
        if (!is_array($admins) || $admins === []) {
            return;
        }
        $primeiro = (int) ($admins[0]['id'] ?? 0);
        if ($primeiro <= 0) {
            return;
        }
        $notificacaoId = (int) $this->db->insert(
            "INSERT INTO notificacoes (tipo_conteudo, titulo, conteudo, enviado_por, tipo_enviador, prioridade, data_envio, ativo)
             VALUES ('mensagem', :titulo, :conteudo, :enviado, 'admin', 'alta', NOW(), 1)",
            ['titulo' => $titulo, 'conteudo' => $conteudo, 'enviado' => $primeiro]
        );
        foreach ($admins as $admin) {
            $destino = (int) ($admin['id'] ?? 0);
            if ($destino <= 0) {
                continue;
            }
            $this->db->insert(
                "INSERT INTO notificacoes_destinatarios (notificacao_id, tipo_destinatario, destinatario_id, lida)
                 VALUES (:id, 'usuarios', :destinatario, 0)",
                ['id' => $notificacaoId, 'destinatario' => $destino]
            );
        }
    }

    public function canalFamiliaPronto(): bool
    {
        return $this->db->tableExists('familia_conversas') && $this->db->tableExists('familia_mensagens');
    }
}

if (!class_exists('SchoolCommunicationService', false) && class_exists(__NAMESPACE__ . '\\SchoolCommunicationService', false)) {
    class_alias(__NAMESPACE__ . '\\SchoolCommunicationService', 'SchoolCommunicationService');
}
