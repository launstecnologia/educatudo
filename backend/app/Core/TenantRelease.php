<?php

require_once __DIR__ . '/LayoutHelper.php';

class TenantRelease
{
    public static function getChannel(): string
    {
        $raw = strtolower(trim((string) LayoutHelper::get('release_channel', 'stable')));
        return in_array($raw, ['stable', 'canary'], true) ? $raw : 'stable';
    }

    public static function getVersion(): string
    {
        return trim((string) LayoutHelper::get('release_version', ''));
    }

    public static function getFlags(): array
    {
        $raw = trim((string) LayoutHelper::get('release_flags', ''));
        if ($raw === '') {
            return [];
        }

        $flags = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $flag) {
            $flag = strtolower(trim((string) $flag));
            if ($flag !== '' && preg_match('/^[a-z0-9_\-\.]{2,64}$/', $flag) === 1) {
                $flags[$flag] = true;
            }
        }

        return $flags;
    }

    public static function hasFlag(string $flag): bool
    {
        $flag = strtolower(trim($flag));
        if ($flag === '') {
            return false;
        }
        $flags = self::getFlags();
        return isset($flags[$flag]);
    }

    public static function isCanary(): bool
    {
        return self::getChannel() === 'canary';
    }

    public static function shouldUse(string $flag, bool $default = false): bool
    {
        if (self::hasFlag($flag)) {
            return true;
        }
        if (self::isCanary()) {
            return true;
        }
        return $default;
    }
}

