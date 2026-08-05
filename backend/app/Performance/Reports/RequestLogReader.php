<?php

namespace App\Performance\Reports;

use App\Performance\PerformanceLogger;

/**
 * Lê e agrega os arquivos requests_YYYY-MM-DD.jsonl gerados pelo PerformanceLogger,
 * para alimentar o dashboard (Master → Performance).
 *
 * Não usa banco de dados — os arquivos já são o "banco" (JSON Lines, um por dia).
 * Para volumes grandes, os filtros de data limitam quantos arquivos são lidos.
 */
final class RequestLogReader
{
    /**
     * @param array{date_from?:string,date_to?:string,tenant?:string,route?:string,
     *              controller?:string,min_ms?:float,min_queries?:int,only_alerts?:bool} $filters
     * @return list<array<string,mixed>>
     */
    public static function read(array $filters = [], int $limit = 5000): array
    {
        $files = self::filesForRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);
        $rows = [];

        foreach ($files as $file) {
            $handle = @fopen($file, 'r');
            if ($handle === false) {
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $data = json_decode($line, true);
                if (!is_array($data)) {
                    continue;
                }
                if (!self::matchesFilters($data, $filters)) {
                    continue;
                }
                $rows[] = $data;
                if (count($rows) >= $limit) {
                    break 2;
                }
            }
            fclose($handle);
        }

        // Mais recente primeiro.
        usort($rows, static fn ($a, $b) => strcmp((string) ($b['ts'] ?? ''), (string) ($a['ts'] ?? '')));

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{total_requests:int,total_queries:int,avg_queries:float,
     *               avg_time_ms:float,requests_with_alerts:int,requests_with_n1:int}
     */
    public static function summary(array $rows): array
    {
        $total = count($rows);
        if ($total === 0) {
            return [
                'total_requests' => 0,
                'total_queries' => 0,
                'avg_queries' => 0.0,
                'avg_time_ms' => 0.0,
                'requests_with_alerts' => 0,
                'requests_with_n1' => 0,
            ];
        }

        $totalQueries = 0;
        $totalTime = 0.0;
        $withAlerts = 0;
        $withN1 = 0;
        foreach ($rows as $r) {
            $totalQueries += (int) ($r['queries_count'] ?? 0);
            $totalTime += (float) ($r['time_total_ms'] ?? 0);
            if (!empty($r['alerts'])) {
                $withAlerts++;
            }
            if (!empty($r['has_n_plus_one'])) {
                $withN1++;
            }
        }

        return [
            'total_requests' => $total,
            'total_queries' => $totalQueries,
            'avg_queries' => round($totalQueries / $total, 1),
            'avg_time_ms' => round($totalTime / $total, 1),
            'requests_with_alerts' => $withAlerts,
            'requests_with_n1' => $withN1,
        ];
    }

    /**
     * Agrega por rota (item 9 do spec: métricas por página).
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array{route:string,hits:int,avg_time_ms:float,avg_queries:float,max_time_ms:float,avg_memory_mb:float}>
     */
    public static function byRoute(array $rows): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $key = ($r['controller_action'] ?? null) ?: ($r['route'] ?? 'desconhecida');
            $groups[$key]['route'] ??= $r['route'] ?? '';
            $groups[$key]['hits'] = ($groups[$key]['hits'] ?? 0) + 1;
            $groups[$key]['sum_time'] = ($groups[$key]['sum_time'] ?? 0.0) + (float) ($r['time_total_ms'] ?? 0);
            $groups[$key]['sum_queries'] = ($groups[$key]['sum_queries'] ?? 0.0) + (float) ($r['queries_count'] ?? 0);
            $groups[$key]['sum_memory'] = ($groups[$key]['sum_memory'] ?? 0.0) + (float) ($r['memory_peak_mb'] ?? 0);
            $groups[$key]['max_time'] = max($groups[$key]['max_time'] ?? 0.0, (float) ($r['time_total_ms'] ?? 0));
        }

        $result = [];
        foreach ($groups as $key => $g) {
            $hits = $g['hits'];
            $result[] = [
                'controller_action' => (string) $key,
                'route' => (string) $g['route'],
                'hits' => $hits,
                'avg_time_ms' => round($g['sum_time'] / $hits, 1),
                'avg_queries' => round($g['sum_queries'] / $hits, 1),
                'max_time_ms' => round($g['max_time'], 1),
                'avg_memory_mb' => round($g['sum_memory'] / $hits, 1),
            ];
        }

        usort($result, static fn ($a, $b) => $b['avg_time_ms'] <=> $a['avg_time_ms']);

        return $result;
    }

    /**
     * Tendência dia a dia — pra responder "isso está piorando com o tempo?" sem
     * precisar ficar comparando filtro de data manualmente.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array{date:string,hits:int,avg_time_ms:float,avg_queries:float,max_time_ms:float,requests_with_alerts:int}>
     */
    public static function byDay(array $rows): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $date = substr((string) ($r['ts'] ?? ''), 0, 10);
            if ($date === '') {
                continue;
            }
            $groups[$date]['hits'] = ($groups[$date]['hits'] ?? 0) + 1;
            $groups[$date]['sum_time'] = ($groups[$date]['sum_time'] ?? 0.0) + (float) ($r['time_total_ms'] ?? 0);
            $groups[$date]['sum_queries'] = ($groups[$date]['sum_queries'] ?? 0.0) + (float) ($r['queries_count'] ?? 0);
            $groups[$date]['max_time'] = max($groups[$date]['max_time'] ?? 0.0, (float) ($r['time_total_ms'] ?? 0));
            $groups[$date]['alerts'] = ($groups[$date]['alerts'] ?? 0) + (empty($r['alerts']) ? 0 : 1);
        }

        $result = [];
        foreach ($groups as $date => $g) {
            $hits = $g['hits'];
            $result[] = [
                'date' => (string) $date,
                'hits' => $hits,
                'avg_time_ms' => round($g['sum_time'] / $hits, 1),
                'avg_queries' => round($g['sum_queries'] / $hits, 1),
                'max_time_ms' => round($g['max_time'], 1),
                'requests_with_alerts' => $g['alerts'],
            ];
        }

        usort($result, static fn ($a, $b) => strcmp($a['date'], $b['date'])); // ordem cronológica
        return $result;
    }

    /**
     * Top N consultas mais lentas em todos os requests do período (item 5 do spec).
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public static function topQueries(array $rows, int $limit = 50, string $orderBy = 'avg'): array
    {
        $groups = [];
        foreach ($rows as $r) {
            foreach (($r['slow_queries'] ?? []) as $q) {
                $fp = $q['fingerprint'] ?? '';
                if ($fp === '') {
                    continue;
                }
                $groups[$fp]['sample_sql'] ??= $q['sql'] ?? '';
                $groups[$fp]['count'] = ($groups[$fp]['count'] ?? 0) + 1;
                $groups[$fp]['total_ms'] = ($groups[$fp]['total_ms'] ?? 0.0) + (float) ($q['duration_ms'] ?? 0);
                $groups[$fp]['max_ms'] = max($groups[$fp]['max_ms'] ?? 0.0, (float) ($q['duration_ms'] ?? 0));
                $groups[$fp]['rows_examined_hint'] = $q['row_count'] ?? null;
                $groups[$fp]['route'] ??= $r['route'] ?? '';
                $groups[$fp]['flags'] = array_values(array_unique(array_merge(
                    $groups[$fp]['flags'] ?? [],
                    $q['explain']['flags'] ?? []
                )));
                $groups[$fp]['index_advice'] ??= $q['index_advice']['suggestion'] ?? null;
            }
        }

        $result = [];
        foreach ($groups as $fp => $g) {
            $result[] = [
                'fingerprint' => $fp,
                'sample_sql' => $g['sample_sql'],
                'count' => $g['count'],
                'avg_ms' => round($g['total_ms'] / $g['count'], 2),
                'total_ms' => round($g['total_ms'], 2),
                'max_ms' => round($g['max_ms'], 2),
                'route' => $g['route'],
                'flags' => $g['flags'],
                'index_advice' => $g['index_advice'],
            ];
        }

        usort($result, static function ($a, $b) use ($orderBy) {
            return match ($orderBy) {
                'total' => $b['total_ms'] <=> $a['total_ms'],
                'count' => $b['count'] <=> $a['count'],
                'max' => $b['max_ms'] <=> $a['max_ms'],
                default => $b['avg_ms'] <=> $a['avg_ms'],
            };
        });

        return array_slice($result, 0, $limit);
    }

    /**
     * Todos os alertas de N+1 do período, agregados por fingerprint (item 4 do spec).
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public static function nPlusOneAcrossRequests(array $rows): array
    {
        $groups = [];
        foreach ($rows as $r) {
            foreach (($r['n_plus_one'] ?? []) as $n1) {
                $fp = $n1['fingerprint'] ?? '';
                if ($fp === '') {
                    continue;
                }
                $groups[$fp]['sample_sql'] ??= $n1['sample_sql'] ?? '';
                $groups[$fp]['occurrences'] = ($groups[$fp]['occurrences'] ?? 0) + 1;
                $groups[$fp]['max_count'] = max($groups[$fp]['max_count'] ?? 0, (int) ($n1['count'] ?? 0));
                $groups[$fp]['total_wasted_ms'] = ($groups[$fp]['total_wasted_ms'] ?? 0.0) + (float) ($n1['wasted_ms'] ?? 0);
                $groups[$fp]['suggestion'] ??= $n1['suggestion'] ?? '';
                $groups[$fp]['route'] ??= $r['controller_action'] ?? $r['route'] ?? '';
            }
        }

        $result = [];
        foreach ($groups as $fp => $g) {
            $result[] = [
                'fingerprint' => $fp,
                'sample_sql' => $g['sample_sql'],
                'occurrences' => $g['occurrences'],
                'max_repeats_in_one_request' => $g['max_count'],
                'total_wasted_ms' => round($g['total_wasted_ms'], 2),
                'suggestion' => $g['suggestion'],
                'route' => $g['route'],
            ];
        }

        usort($result, static fn ($a, $b) => $b['total_wasted_ms'] <=> $a['total_wasted_ms']);

        return $result;
    }

    /**
     * @return list<string>
     */
    private static function filesForRange(?string $from, ?string $to): array
    {
        $all = PerformanceLogger::availableFiles();
        if ($from === null && $to === null) {
            return $all;
        }

        return array_values(array_filter($all, static function ($file) use ($from, $to) {
            if (!preg_match('/requests_(\d{4}-\d{2}-\d{2})\.jsonl$/', $file, $m)) {
                return true;
            }
            $date = $m[1];
            if ($from !== null && $date < $from) {
                return false;
            }
            if ($to !== null && $date > $to) {
                return false;
            }
            return true;
        }));
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $filters
     */
    private static function matchesFilters(array $data, array $filters): bool
    {
        if (!empty($filters['tenant']) && ($data['tenant_slug'] ?? '') !== $filters['tenant']) {
            return false;
        }
        if (!empty($filters['route']) && !str_contains((string) ($data['route'] ?? ''), (string) $filters['route'])) {
            return false;
        }
        if (!empty($filters['controller']) && !str_contains((string) ($data['controller_action'] ?? ''), (string) $filters['controller'])) {
            return false;
        }
        if (!empty($filters['min_ms']) && (float) ($data['time_total_ms'] ?? 0) < (float) $filters['min_ms']) {
            return false;
        }
        if (!empty($filters['min_queries']) && (int) ($data['queries_count'] ?? 0) < (int) $filters['min_queries']) {
            return false;
        }
        if (!empty($filters['only_alerts']) && empty($data['alerts'])) {
            return false;
        }
        return true;
    }
}
