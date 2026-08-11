<?php

namespace App\Performance;

/**
 * Grava, uma vez por request, o payload consolidado do RequestProfiler em
 * `storage/logs/performance/requests_YYYY-MM-DD.jsonl` (JSON Lines — uma linha
 * por request, fácil de tail -f / grep / carregar aos poucos no dashboard).
 *
 * Arquivo separado do PerfLogger (que já existe e grava só slow_queries/slow_requests)
 * para não misturar os dois formatos nem competir pelo mesmo arquivo.
 */
final class PerformanceLogger
{
    public static function log(array $payload): void
    {
        try {
            $dir = self::logDir();
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $file = $dir . '/requests_' . date('Y-m-d') . '.jsonl';
            $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($line === false) {
                return;
            }
            @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
            self::maybeCleanup($dir);
        } catch (\Throwable $e) {
            error_log('PerformanceLogger: ' . $e->getMessage());
        }
    }

    public static function logDir(): string
    {
        return __DIR__ . '/../../storage/logs/performance';
    }

    /**
     * Apaga requests_*.jsonl mais velhos que Profiler::retentionDays(). Roda no
     * máximo 1x por dia por servidor (marcador em arquivo), não a cada request —
     * o custo real (varrer o diretório) só acontece uma vez/dia.
     *
     * PERF_RETENTION_DAYS=0 desativa a limpeza (mantém tudo pra sempre).
     */
    private static function maybeCleanup(string $dir): void
    {
        $retentionDays = Profiler::retentionDays();
        if ($retentionDays <= 0) {
            return;
        }

        $marker = $dir . '/.last_cleanup';
        $today = date('Y-m-d');
        if (is_file($marker) && trim((string) @file_get_contents($marker)) === $today) {
            return; // já rodou hoje
        }

        $cutoff = strtotime('-' . $retentionDays . ' days');
        foreach (glob($dir . '/requests_*.jsonl') ?: [] as $file) {
            if (!preg_match('/requests_(\d{4}-\d{2}-\d{2})\.jsonl$/', $file, $m)) {
                continue;
            }
            $fileTime = strtotime($m[1]);
            if ($fileTime !== false && $fileTime < $cutoff) {
                @unlink($file);
            }
        }

        @file_put_contents($marker, $today, LOCK_EX);
    }

    /**
     * Lista os arquivos requests_*.jsonl disponíveis (mais recente primeiro).
     *
     * @return list<string>
     */
    public static function availableFiles(): array
    {
        $dir = self::logDir();
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/requests_*.jsonl') ?: [];
        rsort($files);
        return $files;
    }
}
