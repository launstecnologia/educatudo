<?php
require_once __DIR__ . '/../Core/Database.php';

class MobileDeviceService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function upsert(int $parentId, string $deviceId, array $input): array
    {
        $this->db->query(
            "INSERT INTO mobile_devices
                (parent_id, device_id, fcm_token, token_hash, platform, app_version, enabled, last_seen_at)
             VALUES
                (:parent_id, :device_id, :fcm_token, :token_hash, :platform, :app_version, 1, NOW())
             ON DUPLICATE KEY UPDATE
                parent_id = VALUES(parent_id), device_id = VALUES(device_id), fcm_token = VALUES(fcm_token),
                token_hash = VALUES(token_hash),
                platform = VALUES(platform), app_version = VALUES(app_version),
                enabled = 1, last_seen_at = NOW(), updated_at = NOW()",
            [
                'parent_id' => $parentId, 'device_id' => $deviceId,
                'fcm_token' => $input['fcm_token'], 'token_hash' => hash('sha256', $input['fcm_token']),
                'platform' => $input['platform'],
                'app_version' => $input['app_version'],
            ]
        );
        return ['device_id' => $deviceId, 'platform' => $input['platform'], 'enabled' => true];
    }

    public function disable(int $parentId, string $deviceId): bool
    {
        return $this->db->update(
            "UPDATE mobile_devices SET enabled = 0, fcm_token = NULL, token_hash = NULL, updated_at = NOW()
             WHERE parent_id = :parent_id AND device_id = :device_id",
            ['parent_id' => $parentId, 'device_id' => $deviceId]
        ) > 0;
    }

    public function enabledTokensForParents(array $parentIds): array
    {
        $parentIds = array_values(array_unique(array_filter(array_map('intval', $parentIds))));
        if ($parentIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($parentIds), '?'));
        return $this->db->fetchAll(
            "SELECT parent_id, device_id, fcm_token FROM mobile_devices
             WHERE enabled = 1 AND fcm_token IS NOT NULL AND parent_id IN ({$placeholders})",
            $parentIds
        );
    }

    public function disableToken(string $token): void
    {
        $this->db->update(
            'UPDATE mobile_devices SET enabled = 0, fcm_token = NULL, token_hash = NULL, updated_at = NOW() WHERE token_hash = :hash',
            ['hash' => hash('sha256', $token)]
        );
    }
}
