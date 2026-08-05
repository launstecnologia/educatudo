<?php

require_once __DIR__ . '/../Models/PushNotifications/PushNotification.php';
require_once __DIR__ . '/FirebaseMessagingService.php';
require_once __DIR__ . '/MobileDeviceService.php';

/** Registra a notificação no histórico e envia FCM aos responsáveis do aluno. */
class FacialAttendanceNotificationService
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

    public function notify(array $student, int $eventId, string $kind, DateTimeInterface $eventAt, int $createdBy): array
    {
        $parentIds = $this->parentIds((int) $student['id']);
        if ($parentIds === []) {
            return ['parents' => 0, 'sent' => 0, 'failed' => 0];
        }
        $isEntry = $kind === 'entrada';
        $title = $isEntry ? 'Entrada registrada' : 'Saída registrada';
        $verb = $isEntry ? 'entrou na escola' : 'saiu da escola';
        $message = (string) $student['nome'] . ' ' . $verb . ' às ' . $eventAt->format('H:i') . '.';
        $notificationId = $this->notifications->create(
            $title,
            $message,
            '/notifications',
            'pais',
            null,
            $createdBy
        );
        $deviceRows = $this->devices->enabledTokensForParents($parentIds);
        $tokensByParent = [];
        foreach ($deviceRows as $device) {
            $tokensByParent[(int) $device['parent_id']][] = (string) $device['fcm_token'];
        }
        $sent = 0;
        $failed = 0;
        foreach ($parentIds as $parentId) {
            $trackingToken = $this->notifications->addEnvio($notificationId, $parentId, 'pai');
            foreach ($tokensByParent[$parentId] ?? [] as $token) {
                $result = $this->firebase->sendToToken($token, $title, $message, [
                    'tracking_token' => $trackingToken,
                    'notificacao_id' => (string) $notificationId,
                    'type' => 'attendance',
                    'event_id' => (string) $eventId,
                    'event_kind' => $kind,
                    'student_id' => (string) $student['id'],
                    'route' => '/notifications',
                ]);
                if (!empty($result['success'])) {
                    $sent++;
                    $this->notifications->marcarEntregueDestinatario($notificationId, $parentId, 'pai');
                } else {
                    $failed++;
                    if (!empty($result['invalid_token'])) {
                        $this->devices->disableToken($token);
                    }
                }
            }
        }
        return ['parents' => count($parentIds), 'sent' => $sent, 'failed' => $failed];
    }

    private function parentIds(int $studentId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT parent_id FROM (
                SELECT a.responsavel_id parent_id FROM alunos a
                 WHERE a.id = :legacy_student AND a.responsavel_id IS NOT NULL
                UNION
                SELECT ar.responsavel_id FROM alunos_responsaveis ar
                 WHERE ar.aluno_id = :linked_student AND ar.ativo = 1
             ) links
             INNER JOIN responsaveis r ON r.id = links.parent_id AND r.ativo = 1",
            ['legacy_student' => $studentId, 'linked_student' => $studentId]
        );
        return array_values(array_unique(array_map('intval', array_column($rows, 'parent_id'))));
    }
}
