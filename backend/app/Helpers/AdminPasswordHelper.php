<?php

namespace App\Helpers;


use App\Core\Database;
class AdminPasswordHelper
{
    public static function verifyAdminPassword(Database $db, array $user, string $senha): bool
    {
        if ($senha === '') {
            return false;
        }
        if (!in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            return false;
        }
        $usuario = $db->fetch(
            "SELECT senha_hash FROM usuarios WHERE id = :id AND tipo IN ('admin', 'admin_escola')",
            ['id' => (int) $user['id']]
        );

        return $usuario && password_verify($senha, (string) ($usuario['senha_hash'] ?? ''));
    }

    /**
     * @param mixed $raw
     * @return int[]
     */
    public static function parseIds($raw): array
    {
        if (!is_array($raw)) {
            $raw = [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $raw), static function (int $v): bool {
            return $v > 0;
        })));
    }
}

if (!class_exists('AdminPasswordHelper', false) && class_exists(__NAMESPACE__ . '\\AdminPasswordHelper', false)) {
    class_alias(__NAMESPACE__ . '\\AdminPasswordHelper', 'AdminPasswordHelper');
}
