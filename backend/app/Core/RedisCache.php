<?php
/**
 * EducaTudo - Cache Redis
 * Singleton por request; fallback silencioso se Redis indisponível.
 * Timeouts curtos — nunca bloquear o worker PHP-FPM.
 */

class RedisCache
{
    private const PREFIX = 'educatudo_';
    private const HOST = '127.0.0.1';
    private const PORT = 6379;
    private const CONNECT_TIMEOUT = 1.0;
    private const READ_TIMEOUT = 1.0;

    /** @var Redis|null */
    private static $redis = null;

    /** @var bool */
    private static $failed = false;

    private static function redisHost(): string
    {
        $fromEnv = self::readEnvValue('REDIS_HOST');
        if ($fromEnv !== '') {
            return $fromEnv;
        }
        return self::HOST;
    }

    private static function redisPort(): int
    {
        $fromEnv = self::readEnvValue('REDIS_PORT');
        if ($fromEnv !== '' && ctype_digit($fromEnv)) {
            return (int) $fromEnv;
        }
        return self::PORT;
    }

    /** Lê variável do .env / ambiente sem depender de env() (bootstrap roda antes de config/app.php). */
    private static function readEnvValue(string $name): string
    {
        $val = getenv($name);
        if ($val !== false && $val !== '') {
            return trim((string) $val);
        }
        $paths = [];
        if (defined('ENV_FILE_PATH')) {
            $paths[] = ENV_FILE_PATH;
        }
        $paths[] = dirname(__DIR__, 2) . '/.env';
        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }
            $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (stripos($line, $name . '=') === 0) {
                    return trim(substr($line, strlen($name) + 1), " \t\"'");
                }
            }
        }
        return '';
    }

    private static function connect(): ?Redis
    {
        if (self::$failed) {
            return null;
        }
        if (self::$redis !== null) {
            return self::$redis;
        }
        try {
            if (!class_exists('Redis')) {
                self::$failed = true;
                return null;
            }
            $redis = new Redis();
            $ok = @$redis->connect(self::redisHost(), self::redisPort(), self::CONNECT_TIMEOUT);
            if ($ok !== true) {
                self::$failed = true;
                return null;
            }
            // Evita hang infinito em get/set se Redis travar a meio do request.
            if (defined('Redis::OPT_READ_TIMEOUT')) {
                $redis->setOption(Redis::OPT_READ_TIMEOUT, self::READ_TIMEOUT);
            }
            // Ping barato: confirma que o socket responde antes de cachear o client.
            $pong = $redis->ping();
            $pongOk = $pong === true || $pong === '+PONG' || $pong === 'PONG';
            if (!$pongOk) {
                self::$failed = true;
                try {
                    $redis->close();
                } catch (Throwable $e) {
                    // ignore
                }
                return null;
            }
            self::$redis = $redis;
            return $redis;
        } catch (Throwable $e) {
            self::$failed = true;
            self::$redis = null;
            return null;
        }
    }

    public static function get(string $key): ?string
    {
        $redis = self::connect();
        if ($redis === null) {
            return null;
        }
        try {
            $fullKey = self::PREFIX . $key;
            $value = $redis->get($fullKey);
            return ($value !== false && $value !== null) ? (string) $value : null;
        } catch (Throwable $e) {
            self::$failed = true;
            self::$redis = null;
            return null;
        }
    }

    public static function set(string $key, string $value, int $ttl = 300): bool
    {
        $redis = self::connect();
        if ($redis === null) {
            return false;
        }
        try {
            $fullKey = self::PREFIX . $key;
            if ($ttl > 0) {
                return (bool) $redis->setex($fullKey, $ttl, $value);
            }
            return (bool) $redis->set($fullKey, $value);
        } catch (Throwable $e) {
            self::$failed = true;
            self::$redis = null;
            return false;
        }
    }

    /** Remove uma chave do cache (ex.: invalidar config_layout após sync do Master). */
    public static function delete(string $key): bool
    {
        $redis = self::connect();
        if ($redis === null) {
            return false;
        }
        try {
            return $redis->del(self::PREFIX . $key) > 0;
        } catch (Throwable $e) {
            self::$failed = true;
            self::$redis = null;
            return false;
        }
    }
}
