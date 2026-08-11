<?php

namespace App\Performance;

/**
 * EducaTudo — Performance Profiler (diagnóstico de queries/páginas lentas).
 *
 * Gate único de habilitação para todo o subsistema de performance. Segue o
 * mesmo padrão já usado no restante do código (`$_ENV['APP_DEBUG'] === 'true'`).
 *
 * IMPORTANTE: este sistema é só de LEITURA/diagnóstico — nunca altera dados,
 * nunca cria índice automaticamente, e fica 100% desligado quando
 * APP_DEBUG != 'true' (ou seja, sempre desligado em produção por padrão).
 *
 * Overhead quando desligado: uma leitura de bool cacheada por request. Nada
 * mais roda (nem coleta, nem I/O).
 */
final class Profiler
{
    private static ?bool $enabled = null;

    private function __construct()
    {
    }

    public static function isEnabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        // Interruptor mestre: só existe a POSSIBILIDADE de ligar com APP_DEBUG=true
        // no .env do servidor. Isso NUNCA é contornável pelo painel — é a trava de
        // segurança pra nunca vazar isso em produção sem querer (exige editar .env
        // + reiniciar o processo, de propósito).
        $appDebug = !empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true';
        if (!$appDebug) {
            self::$enabled = false;
            return false;
        }

        // Permite desligar só o profiler completo mesmo com APP_DEBUG=true
        // (ex.: você quer stack trace de erro mas não quer o overhead do profiler).
        $flag = $_ENV['PERF_PROFILER'] ?? getenv('PERF_PROFILER');
        if ($flag !== false && $flag !== null && $flag !== '') {
            $explicit = in_array(strtolower((string) $flag), ['1', 'true', 'yes', 'on'], true);
            if (!$explicit) {
                self::$enabled = false;
                return false;
            }
        }

        // Pausa/retoma pelo painel (Master → Performance) — não precisa editar .env
        // nem reiniciar nada; só funciona com APP_DEBUG=true (a trava acima).
        self::$enabled = !self::isPaused();
        return self::$enabled;
    }

    private static function pauseFlagFile(): string
    {
        return __DIR__ . '/../../storage/logs/performance/.paused';
    }

    /** Coleta está pausada pelo painel? (não confundir com APP_DEBUG=false). */
    public static function isPaused(): bool
    {
        return is_file(self::pauseFlagFile());
    }

    /** Pausa a coleta em tempo real, sem editar .env nem reiniciar o processo. */
    public static function pause(): void
    {
        $dir = dirname(self::pauseFlagFile());
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents(self::pauseFlagFile(), date('c'), LOCK_EX);
        self::$enabled = null;
    }

    /** Retoma a coleta pausada pelo painel. */
    public static function resume(): void
    {
        @unlink(self::pauseFlagFile());
        self::$enabled = null;
    }

    /** Limite (ms) acima do qual uma query dispara EXPLAIN ANALYZE automático. */
    public static function explainThresholdMs(): float
    {
        $v = $_ENV['PERF_EXPLAIN_THRESHOLD_MS'] ?? getenv('PERF_EXPLAIN_THRESHOLD_MS');
        return $v !== false && $v !== null && $v !== '' ? max(1.0, (float) $v) : 50.0;
    }

    /** Repetições mínimas do mesmo fingerprint de SQL para considerar N+1. */
    public static function nPlusOneThreshold(): int
    {
        $v = $_ENV['PERF_N1_THRESHOLD'] ?? getenv('PERF_N1_THRESHOLD');
        return $v !== false && $v !== null && $v !== '' ? max(2, (int) $v) : 5;
    }

    /** Máximo de EXPLAIN ANALYZE reais por request (limita overhead). */
    public static function maxExplainsPerRequest(): int
    {
        $v = $_ENV['PERF_MAX_EXPLAINS'] ?? getenv('PERF_MAX_EXPLAINS');
        return $v !== false && $v !== null && $v !== '' ? max(0, (int) $v) : 5;
    }

    /**
     * Dias de retenção dos arquivos requests_*.jsonl — arquivos mais antigos que
     * isso são apagados automaticamente (ver PerformanceLogger::maybeCleanup()).
     * 0 desativa a limpeza automática (mantém tudo pra sempre).
     */
    public static function retentionDays(): int
    {
        $v = $_ENV['PERF_RETENTION_DAYS'] ?? getenv('PERF_RETENTION_DAYS');
        return $v !== false && $v !== null && $v !== '' ? max(0, (int) $v) : 7;
    }

    /** Reseta o cache do enabled (uso em testes). */
    public static function resetCache(): void
    {
        self::$enabled = null;
    }
}
