<?php

namespace App\Performance;

/**
 * Roda EXPLAIN ANALYZE (ou EXPLAIN FORMAT=JSON como fallback) para queries SELECT
 * que passaram do limiar configurado (Profiler::explainThresholdMs()).
 *
 * Segurança/impacto:
 * - Só SELECT (nunca UPDATE/INSERT/DELETE — EXPLAIN ANALYZE no MySQL 8 chega a
 *   EXECUTAR a query de verdade; para DML isso duplicaria o efeito).
 * - Limite de execuções por request (Profiler::maxExplainsPerRequest()) — não
 *   soma custo real quando há muitas queries lentas de uma vez.
 * - Nunca lança exceção pro request original: falha aqui só é logada.
 */
final class ExplainAnalyzer
{
    private static int $executedThisRequest = 0;

    public static function reset(): void
    {
        self::$executedThisRequest = 0;
    }

    /**
     * @param array<int|string,mixed> $params
     * @return array{raw:?string,format:string,flags:list<string>}|null
     */
    public static function analyze(\PDO $pdo, string $sql, array $params): ?array
    {
        if (!self::isSafeSelect($sql)) {
            return null;
        }
        if (self::$executedThisRequest >= Profiler::maxExplainsPerRequest()) {
            return null;
        }

        self::$executedThisRequest++;

        try {
            $result = self::tryExplainAnalyze($pdo, $sql, $params);
            if ($result !== null) {
                return $result;
            }
            return self::tryExplainJson($pdo, $sql, $params);
        } catch (\Throwable $e) {
            error_log('ExplainAnalyzer: ' . $e->getMessage());
            return null;
        }
    }

    private static function isSafeSelect(string $sql): bool
    {
        $trimmed = ltrim($sql);
        if (str_ends_with($trimmed, '…')) {
            // SQL truncado pelo QueryCollector (query gigante) — não dá pra reexecutar com segurança.
            return false;
        }
        return (bool) preg_match('/^select\b/i', $trimmed)
            && !preg_match('/\binto\s+outfile\b/i', $trimmed);
    }

    /**
     * @return array{raw:string,format:string,flags:list<string>}|null
     */
    private static function tryExplainAnalyze(\PDO $pdo, string $sql, array $params): ?array
    {
        try {
            $stmt = $pdo->prepare('EXPLAIN ANALYZE ' . $sql);
            self::bindLoosely($stmt, $params);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
            $text = implode("\n", array_map(static fn ($r) => (string) ($r[0] ?? ''), $rows));
            if ($text === '') {
                return null;
            }
            return [
                'raw' => strlen($text) > 4000 ? substr($text, 0, 4000) . '…' : $text,
                'format' => 'analyze',
                'flags' => self::detectFlags($text),
            ];
        } catch (\Throwable $e) {
            // MySQL < 8.0.18 não tem EXPLAIN ANALYZE — cai pro fallback.
            return null;
        }
    }

    /**
     * @return array{raw:string,format:string,flags:list<string>}|null
     */
    private static function tryExplainJson(\PDO $pdo, string $sql, array $params): ?array
    {
        try {
            $stmt = $pdo->prepare('EXPLAIN FORMAT=JSON ' . $sql);
            self::bindLoosely($stmt, $params);
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_NUM);
            $json = (string) ($row[0] ?? '');
            if ($json === '') {
                return null;
            }
            return [
                'raw' => strlen($json) > 4000 ? substr($json, 0, 4000) . '…' : $json,
                'format' => 'json',
                'flags' => self::detectFlags($json),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Marca sinais clássicos de problema no texto do EXPLAIN.
     *
     * @return list<string>
     */
    private static function detectFlags(string $text): array
    {
        $flags = [];
        $lower = strtolower($text);
        $map = [
            'Full table scan' => ['type": "all"', 'all"', 'table scan'],
            'Filesort' => ['using filesort', 'sort_key'],
            'Temporary table' => ['using temporary', 'temporary_table'],
            'Nested loop' => ['nested_loop', 'nested loop'],
            'No index / index não usado' => ['possible_keys": null', 'no matching row'],
        ];
        foreach ($map as $label => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($lower, $needle)) {
                    $flags[] = $label;
                    break;
                }
            }
        }
        return array_values(array_unique($flags));
    }

    /**
     * Bind "solto": EXPLAIN não precisa de tipos exatos, só precisa não quebrar.
     * Aceita array posicional (?) ou nomeado (:x).
     */
    private static function bindLoosely(\PDOStatement $stmt, array $params): void
    {
        $isList = array_is_list($params);
        foreach ($params as $key => $value) {
            $param = $isList ? $key + 1 : (str_starts_with((string) $key, ':') ? $key : ':' . $key);
            $stmt->bindValue($param, $value);
        }
    }
}
