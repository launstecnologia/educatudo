<?php

namespace App\Performance;

/**
 * Coleta TODAS as queries do request atual (não só as lentas — para isso já existe
 * o PerfLogger). É a base para detectar N+1: uma query de 2ms repetida 120x não é
 * "lenta" sozinha, mas em conjunto é o maior tipo de gargalo do EducaTudo.
 *
 * Buffer 100% em memória, por request; nunca grava em disco sozinho (quem grava é
 * o PerformanceLogger, uma vez, no fim do request).
 */
final class QueryCollector
{
    /** @var list<array<string,mixed>> */
    private static array $queries = [];

    /**
     * Valores reais dos parâmetros, indexados por seq — só em memória, só para
     * o ExplainAnalyzer conseguir rodar EXPLAIN de verdade no fim do request.
     * NUNCA sai daqui: não entra no payload do PerformanceLogger nem é
     * persistido em disco (evita PII/dado sensível no JSONL).
     *
     * @var array<int,array<int|string,mixed>>
     */
    private static array $paramsBySeq = [];

    private static int $sequence = 0;

    public static function record(
        string $sql,
        array $params,
        float $durationSec,
        ?int $rowCount,
        ?string $error
    ): void {
        if (!Profiler::isEnabled()) {
            return;
        }

        self::$sequence++;
        $seq = self::$sequence;

        self::$queries[] = [
            'seq' => $seq,
            'sql' => self::sanitizeSql($sql),
            'fingerprint' => \PerfLogger::fingerprintSql($sql),
            'params_count' => count($params),
            // Nunca guarda o valor cru dos parâmetros no log final — só metadados
            // (evita PII em disco). Os valores reais ficam só em $paramsBySeq,
            // em memória, para uso interno do ExplainAnalyzer neste mesmo request.
            'duration_ms' => round($durationSec * 1000, 3),
            'row_count' => $rowCount,
            'error' => $error,
            'is_select' => (bool) preg_match('/^\s*SELECT\b/i', $sql),
            'memory_at_query_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ];
        self::$paramsBySeq[$seq] = $params;

        // Guarda-costas: nunca deixa o buffer crescer sem limite numa request patológica
        // (ex.: loop infinito). 5000 queries num único request já é sinal de bug grave,
        // e a partir daí só contamos, não guardamos detalhe de todas.
        if (count(self::$queries) > 5000) {
            $dropped = array_shift(self::$queries);
            if ($dropped !== null) {
                unset(self::$paramsBySeq[$dropped['seq']]);
            }
        }
    }

    /**
     * Valores reais dos parâmetros de uma query já coletada (só em memória,
     * uso interno do ExplainAnalyzer). Nunca serializar isso para log/disco.
     *
     * @return array<int|string,mixed>
     */
    public static function paramsForSeq(int $seq): array
    {
        return self::$paramsBySeq[$seq] ?? [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function all(): array
    {
        return self::$queries;
    }

    public static function count(): int
    {
        return count(self::$queries);
    }

    public static function totalDurationMs(): float
    {
        $sum = 0.0;
        foreach (self::$queries as $q) {
            $sum += $q['duration_ms'];
        }
        return round($sum, 3);
    }

    public static function reset(): void
    {
        self::$queries = [];
        self::$paramsBySeq = [];
        self::$sequence = 0;
    }

    private static function sanitizeSql(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);
        return strlen($sql) > 1500 ? substr($sql, 0, 1500) . '…' : $sql;
    }
}
