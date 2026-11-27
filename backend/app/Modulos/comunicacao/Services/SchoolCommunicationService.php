<?php

namespace App\Modulos\Comunicacao\Services;

use App\Core\Database;
use App\Infra\Services\MediaStorageService;
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
    private array $config;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
        $this->notifications = new PushNotification();
        $this->firebase = new FirebaseMessagingService();
        $this->devices = new MobileDeviceService();
        $this->config = $this->resolverConfig();
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
     * @return array{path:string,name:string,tipo:string,mime:string}|null
     */
    public function guardarImagem(array $file, string $pasta): ?array
    {
        return $this->guardarAnexo($file, $pasta, ['imagem']);
    }

    /**
     * Salva imagem, vídeo, PDF ou áudio do chat família↔escola.
     *
     * @param array<string,mixed> $file
     * @param list<string> $tiposPermitidos imagem|video|arquivo|audio
     * @return array{path:string,name:string,tipo:string,mime:string}|null
     */
    public function guardarAnexo(array $file, string $pasta, array $tiposPermitidos = ['imagem', 'video', 'arquivo', 'audio']): ?array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0) {
            return null;
        }
        $mapa = [
            'image/jpeg' => ['ext' => 'jpg', 'tipo' => 'imagem', 'max' => 8 * 1024 * 1024],
            'image/png' => ['ext' => 'png', 'tipo' => 'imagem', 'max' => 8 * 1024 * 1024],
            'image/webp' => ['ext' => 'webp', 'tipo' => 'imagem', 'max' => 8 * 1024 * 1024],
            'video/mp4' => ['ext' => 'mp4', 'tipo' => 'video', 'max' => 40 * 1024 * 1024],
            'video/webm' => ['ext' => 'webm', 'tipo' => 'video', 'max' => 40 * 1024 * 1024],
            'application/pdf' => ['ext' => 'pdf', 'tipo' => 'arquivo', 'max' => 12 * 1024 * 1024],
            'audio/webm' => ['ext' => 'webm', 'tipo' => 'audio', 'max' => 12 * 1024 * 1024],
            'audio/ogg' => ['ext' => 'ogg', 'tipo' => 'audio', 'max' => 12 * 1024 * 1024],
            'audio/mpeg' => ['ext' => 'mp3', 'tipo' => 'audio', 'max' => 12 * 1024 * 1024],
            'audio/mp4' => ['ext' => 'm4a', 'tipo' => 'audio', 'max' => 12 * 1024 * 1024],
            'audio/x-m4a' => ['ext' => 'm4a', 'tipo' => 'audio', 'max' => 12 * 1024 * 1024],
            'audio/aac' => ['ext' => 'aac', 'tipo' => 'audio', 'max' => 12 * 1024 * 1024],
            'audio/wav' => ['ext' => 'wav', 'tipo' => 'audio', 'max' => 12 * 1024 * 1024],
            'audio/x-wav' => ['ext' => 'wav', 'tipo' => 'audio', 'max' => 12 * 1024 * 1024],
            'audio/wave' => ['ext' => 'wav', 'tipo' => 'audio', 'max' => 12 * 1024 * 1024],
        ];
        $mimeDetectado = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        $mimeCliente = strtolower(trim((string) ($file['type'] ?? '')));
        // Remove ";codecs=..." se vier no Content-Type
        $mimeCliente = preg_replace('/;.*$/', '', $mimeCliente) ?: '';
        $mime = $mimeDetectado !== '' ? $mimeDetectado : $mimeCliente;
        $nomeHint = strtolower((string) ($file['name'] ?? ''));
        $pareceAudio = str_starts_with($nomeHint, 'audio')
            || str_contains($nomeHint, 'audio')
            || str_ends_with($nomeHint, '.weba')
            || str_ends_with($nomeHint, '.m4a')
            || str_ends_with($nomeHint, '.mp3')
            || str_ends_with($nomeHint, '.aac')
            || str_starts_with($mimeCliente, 'audio/')
            || str_starts_with($mimeDetectado, 'audio/');

        // Safari/Chrome: áudio pode chegar como video/webm, application/octet-stream ou mp4 genérico
        if ($pareceAudio && in_array($mime, ['video/webm', 'video/mp4', 'application/octet-stream', 'application/ogg', ''], true)) {
            $ext = 'webm';
            if (str_ends_with($nomeHint, '.ogg') || $mimeCliente === 'audio/ogg') {
                $ext = 'ogg';
            } elseif (str_ends_with($nomeHint, '.m4a') || str_ends_with($nomeHint, '.mp4') || str_starts_with($mimeCliente, 'audio/mp4')) {
                $ext = 'm4a';
            } elseif (str_ends_with($nomeHint, '.mp3') || $mimeCliente === 'audio/mpeg') {
                $ext = 'mp3';
            }
            $mapa[$mime !== '' ? $mime : 'application/octet-stream'] = [
                'ext' => $ext,
                'tipo' => 'audio',
                'max' => 12 * 1024 * 1024,
            ];
            if ($mime === '') {
                $mime = 'application/octet-stream';
            }
        }
        if (!isset($mapa[$mime]) && $pareceAudio && isset($mapa[$mimeCliente])) {
            $mime = $mimeCliente;
        }
        if (!isset($mapa[$mime])) {
            return null;
        }
        $meta = $mapa[$mime];
        if (!in_array($meta['tipo'], $tiposPermitidos, true) || $size > $meta['max']) {
            return null;
        }
        // MIME canônico para gravar (não o genérico do finfo)
        $mimeCanonico = $mime;
        if ($meta['tipo'] === 'audio') {
            $mimeCanonico = match ($meta['ext']) {
                'm4a' => 'audio/mp4',
                'mp3' => 'audio/mpeg',
                'ogg' => 'audio/ogg',
                'wav' => 'audio/wav',
                'aac' => 'audio/aac',
                default => 'audio/webm',
            };
        }
        $pastaNormalizada = preg_replace('/[^a-z0-9_-]/i', '', $pasta) ?: 'comunicacao';
        $filename = bin2hex(random_bytes(16)) . '.' . $meta['ext'];
        $path = '';

        if (in_array($pastaNormalizada, ['fale-com-a-escola', 'school-communications'], true)) {
            $key = 'familia/' . $filename;
            $config = $this->config;
            // Isolamento multi-tenant obrigatório no S3/local para este tipo
            $config['media'] = array_merge($config['media'] ?? [], ['tenant_prefix' => true]);
            $media = new MediaStorageService($config);
            if (!$media->put('fale_com_escola', $key, $tmp, $mimeCanonico)) {
                return null;
            }
            $tenant = $this->tenantSlug();
            $path = '/media/serve?type=fale_com_escola&key=' . rawurlencode($key) . '&tenant=' . rawurlencode($tenant);
        } else {
            $tenant = $this->tenantSlug();
            $relative = 'storage/uploads/' . $pastaNormalizada . '/' . $tenant;
            $root = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__, 4);
            $absolute = rtrim($root, '/') . '/' . $relative;
            if (!is_dir($absolute) && !@mkdir($absolute, 0775, true) && !is_dir($absolute)) {
                return null;
            }
            if (!move_uploaded_file($tmp, $absolute . '/' . $filename)) {
                return null;
            }
            $path = '/' . $relative . '/' . $filename;
        }

        $nome = basename((string) ($file['name'] ?? $meta['tipo']));

        return [
            'path' => $path,
            'name' => $nome !== '' ? $nome : $meta['tipo'],
            'tipo' => $meta['tipo'],
            'mime' => $mimeCanonico,
        ];
    }

    private function resolverConfig(): array
    {
        if (isset($GLOBALS['config']) && is_array($GLOBALS['config'])) {
            return $GLOBALS['config'];
        }

        return require dirname(__DIR__, 4) . '/config/app.php';
    }

    private function tenantSlug(): string
    {
        $slug = '';
        if (defined('TENANT_SLUG')) {
            $slug = (string) TENANT_SLUG;
        }
        if ($slug === '') {
            $slug = (string) ($this->config['tenant']['slug'] ?? '');
        }
        if ($slug === '') {
            $slug = (string) ($this->config['school']['code'] ?? '');
        }

        $slug = preg_replace('/[^a-z0-9_-]/i', '', $slug) ?? '';
        return $slug !== '' ? $slug : 'default';
    }

    /**
     * Garante que o valor cabe no ENUM anexo_tipo atual do tenant.
     * Se 'audio' ainda não existe no ENUM, grava como 'arquivo' (UI usa mime).
     */
    public function normalizarTipoAnexo(?string $tipo): ?string
    {
        $tipo = trim((string) $tipo);
        if ($tipo === '') {
            return null;
        }
        $aceitos = $this->anexoTiposNoBanco();
        if ($aceitos === []) {
            return in_array($tipo, ['imagem', 'video', 'arquivo', 'audio'], true) ? $tipo : 'arquivo';
        }
        if (in_array($tipo, $aceitos, true)) {
            return $tipo;
        }
        if ($tipo === 'audio' && in_array('arquivo', $aceitos, true)) {
            return 'arquivo';
        }
        if (in_array('arquivo', $aceitos, true)) {
            return 'arquivo';
        }
        return $aceitos[0] ?? null;
    }

    /** @return list<string> */
    private function anexoTiposNoBanco(): array
    {
        static $lista = null;
        if ($lista !== null) {
            return $lista;
        }
        try {
            $row = $this->db->fetch(
                "SELECT COLUMN_TYPE AS t FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'familia_mensagens' AND COLUMN_NAME = 'anexo_tipo'
                 LIMIT 1"
            );
            $tipo = (string) ($row['t'] ?? '');
            if (!preg_match_all("/'([^']+)'/", $tipo, $m)) {
                $lista = [];
                return $lista;
            }
            $lista = array_values($m[1]);
        } catch (\Throwable $e) {
            $lista = [];
        }
        return $lista;
    }

    public function tabelaReacoesPronta(): bool
    {
        return $this->db->tableExists('familia_mensagem_reacoes');
    }

    /**
     * Alterna curtida (toggle). Retorna se ficou curtida.
     */
    public function alternarCurtida(int $mensagemId, string $autorTipo, int $autorId): bool
    {
        if (!$this->tabelaReacoesPronta() || $mensagemId <= 0 || $autorId <= 0) {
            return false;
        }
        if (!in_array($autorTipo, ['responsavel', 'escola'], true)) {
            return false;
        }
        $existente = $this->db->fetch(
            "SELECT id FROM familia_mensagem_reacoes
             WHERE mensagem_id = :msg AND autor_tipo = :tipo AND autor_id = :autor AND reacao = 'curtir'
             LIMIT 1",
            ['msg' => $mensagemId, 'tipo' => $autorTipo, 'autor' => $autorId]
        );
        if (is_array($existente)) {
            $this->db->query(
                'DELETE FROM familia_mensagem_reacoes WHERE id = :id',
                ['id' => (int) $existente['id']]
            );
            return false;
        }
        $this->db->insert(
            "INSERT INTO familia_mensagem_reacoes (mensagem_id, autor_tipo, autor_id, reacao)
             VALUES (:msg, :tipo, :autor, 'curtir')",
            ['msg' => $mensagemId, 'tipo' => $autorTipo, 'autor' => $autorId]
        );
        return true;
    }

    /**
     * Avisa admins no sino. $destinoUrl é caminho relativo interno
     * (ex.: /admin/comunicacao-escolar/fale-com-a-escola/12).
     */
    public function avisarAdmins(string $titulo, string $conteudo, ?string $destinoUrl = null): void
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
        $destinoUrl = $this->destinoInterno($destinoUrl);
        $temDestino = $destinoUrl !== null && $this->colunaDestinoUrlExiste();
        if ($temDestino) {
            $notificacaoId = (int) $this->db->insert(
                "INSERT INTO notificacoes (tipo_conteudo, titulo, conteudo, enviado_por, tipo_enviador, prioridade, data_envio, ativo, destino_url)
                 VALUES ('mensagem', :titulo, :conteudo, :enviado, 'admin', 'alta', NOW(), 1, :destino)",
                ['titulo' => $titulo, 'conteudo' => $conteudo, 'enviado' => $primeiro, 'destino' => $destinoUrl]
            );
        } else {
            $notificacaoId = (int) $this->db->insert(
                "INSERT INTO notificacoes (tipo_conteudo, titulo, conteudo, enviado_por, tipo_enviador, prioridade, data_envio, ativo)
                 VALUES ('mensagem', :titulo, :conteudo, :enviado, 'admin', 'alta', NOW(), 1)",
                ['titulo' => $titulo, 'conteudo' => $conteudo, 'enviado' => $primeiro]
            );
        }
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

    private function destinoInterno(?string $url): ?string
    {
        $url = preg_replace('/[\r\n\0]/', '', trim((string) $url)) ?? '';
        if ($url === '' || !str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            return null;
        }
        return mb_substr($url, 0, 500);
    }

    private function colunaDestinoUrlExiste(): bool
    {
        static $existe = null;
        if ($existe !== null) {
            return $existe;
        }
        try {
            $row = $this->db->fetch(
                "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacoes' AND COLUMN_NAME = 'destino_url'"
            );
            $existe = ((int) ($row['c'] ?? 0)) > 0;
        } catch (\Throwable $e) {
            $existe = false;
        }
        return $existe;
    }

    public function canalFamiliaPronto(): bool
    {
        return $this->db->tableExists('familia_conversas') && $this->db->tableExists('familia_mensagens');
    }
}

if (!class_exists('SchoolCommunicationService', false) && class_exists(__NAMESPACE__ . '\\SchoolCommunicationService', false)) {
    class_alias(__NAMESPACE__ . '\\SchoolCommunicationService', 'SchoolCommunicationService');
}
