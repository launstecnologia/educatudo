<?php

namespace App\Services;

/**
 * Resolve valores de ENUM conforme o schema real do tenant (compatível com bancos legados).
 */
class MatriculaSchemaHelper
{
    /** @var array<string, bool|null> */
    private static $enumCache = [];

    public static function statusEncerramentoManual(): string
    {
        return self::enumContains('matricula', 'status', 'concluido') ? 'concluido' : 'transferido';
    }

    public static function alunoStatusSemMatricula(): string
    {
        return self::enumContains('alunos', 'status', 'PENDING') ? 'PENDING' : 'ACTIVE';
    }

    public static function normalizarStatusEncerramentoMatricula(string $status): string
    {
        if ($status === 'concluido') {
            return self::statusEncerramentoManual();
        }

        return in_array($status, ['ativa', 'transferido', 'concluido'], true) ? $status : 'transferido';
    }

    private static function enumContains(string $table, string $column, string $value): bool
    {
        $key = $table . '.' . $column . '.' . $value;
        if (array_key_exists($key, self::$enumCache)) {
            return self::$enumCache[$key];
        }

        try {
            $db = \Database::getInstance();
            $row = $db->fetch(
                "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl AND COLUMN_NAME = :col",
                ['tbl' => $table, 'col' => $column]
            );
            self::$enumCache[$key] = $row
                && stripos((string) ($row['COLUMN_TYPE'] ?? ''), "'" . $value . "'") !== false;
        } catch (\Exception $e) {
            self::$enumCache[$key] = false;
        }

        return self::$enumCache[$key];
    }
}
