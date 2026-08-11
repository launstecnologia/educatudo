<?php
require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../../../Core/Database.php';
require_once __DIR__ . '/../../../Middleware/MobileApiAuthMiddleware.php';

class MobileNotificationController extends BaseController
{
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        $parentId = (int) MobileApiAuthMiddleware::$parentId;
        $rows = $this->db->fetchAll(
            "SELECT pn.id, pn.titulo, pn.mensagem, pn.url, pn.created_at,
                    e.entregue, e.entregue_em, e.visualizado, e.visualizado_em, e.clicado, e.clicado_em
             FROM notificacoes_push_envios e
             INNER JOIN notificacoes_push pn ON pn.id = e.notificacao_id
             WHERE e.user_id = :parent_id AND e.role = 'pai'
             ORDER BY pn.created_at DESC
             LIMIT 200",
            ['parent_id' => $parentId]
        );

        $data = array_map(function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'title' => (string) $row['titulo'],
                'message' => (string) $row['mensagem'],
                'url' => $row['url'] !== null ? (string) $row['url'] : null,
                'is_delivered' => !empty($row['entregue']),
                'delivered_at' => $this->isoDate($row['entregue_em'] ?? null),
                'is_read' => !empty($row['visualizado']),
                'read_at' => $this->isoDate($row['visualizado_em'] ?? null),
                'is_clicked' => !empty($row['clicado']),
                'clicked_at' => $this->isoDate($row['clicado_em'] ?? null),
                'created_at' => $this->isoDate($row['created_at'] ?? null),
            ];
        }, $rows);

        $unread = 0;
        foreach ($data as $notification) {
            if (!$notification['is_read']) $unread++;
        }
        $this->json(['data' => $data, 'meta' => ['count' => count($data), 'unread_count' => $unread]]);
    }

    public function read($id): void
    {
        $notificationId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($notificationId === false) $this->notFound();

        $row = $this->db->fetch(
            "SELECT id FROM notificacoes_push_envios
             WHERE notificacao_id = :notification_id AND user_id = :parent_id AND role = 'pai' LIMIT 1",
            ['notification_id' => (int) $notificationId, 'parent_id' => (int) MobileApiAuthMiddleware::$parentId]
        );
        if (!$row) $this->notFound();

        $this->db->update(
            "UPDATE notificacoes_push_envios
             SET visualizado = 1, visualizado_em = COALESCE(visualizado_em, NOW())
             WHERE id = :id",
            ['id' => (int) $row['id']]
        );
        $updated = $this->db->fetch(
            'SELECT visualizado_em FROM notificacoes_push_envios WHERE id = :id',
            ['id' => (int) $row['id']]
        );
        $this->json(['data' => [
            'id' => (int) $notificationId,
            'is_read' => true,
            'read_at' => $this->isoDate($updated['visualizado_em'] ?? null),
        ]]);
    }

    private function notFound(): void
    {
        // Não revela notificações pertencentes a outro responsável.
        $this->json(['error' => [
            'code' => 'notification_not_found',
            'message' => 'Notificação não encontrada.',
        ]], 404);
    }

    private function isoDate($value): ?string
    {
        if ($value === null || $value === '') return null;
        $timestamp = strtotime((string) $value);
        return $timestamp === false ? null : date(DATE_ATOM, $timestamp);
    }
}
