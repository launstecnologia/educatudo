<?php
/**
 * Key-value settings for dev/config (e.g. flashcard_prompt).
 * No SQL in controllers; all access via this model.
 */
require_once __DIR__ . '/../../Core/Database.php';

class DevSetting
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get value by key. Returns null if not found.
     */
    public function get($keyName)
    {
        $row = $this->db->fetch(
            'SELECT value FROM config_dev WHERE key_name = :k LIMIT 1',
            ['k' => $keyName]
        );
        return $row ? $row['value'] : null;
    }

    /**
     * Set value (insert or update). Updates updated_at.
     */
    public function set($keyName, $value)
    {
        $existing = $this->db->fetch(
            'SELECT id FROM config_dev WHERE key_name = :k LIMIT 1',
            ['k' => $keyName]
        );
        if ($existing) {
            $this->db->query(
                'UPDATE config_dev SET value = :v, updated_at = NOW() WHERE key_name = :k',
                ['v' => $value, 'k' => $keyName]
            );
            return true;
        }
        $this->db->query(
            'INSERT INTO config_dev (key_name, value) VALUES (:k, :v)',
            ['k' => $keyName, 'v' => $value]
        );
        return true;
    }

    /**
     * List all settings (key_name, value, updated_at).
     */
    public function listAll()
    {
        return $this->db->fetchAll(
            'SELECT id, key_name, value, created_at, updated_at FROM config_dev ORDER BY key_name'
        );
    }
}
