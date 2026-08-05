<?php

namespace App\Performance;

/**
 * Detecta padrão N+1: o mesmo "formato" de SQL (fingerprint — sem literais/ids)
 * executado várias vezes dentro do mesmo request.
 *
 * Exemplo clássico: `SELECT * FROM turma WHERE id = ?` disparado uma vez por
 * item de uma lista (120 alunos → 120 SELECTs) em vez de um único
 * `WHERE id IN (...)` ou um JOIN.
 */
final class NPlusOneDetector
{
    /**
     * @param list<array<string,mixed>> $queries saída de QueryCollector::all()
     * @return list<array{fingerprint:string,sample_sql:string,count:int,total_ms:float,wasted_ms:float,suggestion:string}>
     */
    public static function detect(array $queries, ?int $minRepeats = null): array
    {
        $minRepeats ??= Profiler::nPlusOneThreshold();
        if ($queries === []) {
            return [];
        }

        $groups = [];
        foreach ($queries as $q) {
            $fp = $q['fingerprint'] ?? '';
            if ($fp === '') {
                continue;
            }
            $groups[$fp]['count'] = ($groups[$fp]['count'] ?? 0) + 1;
            $groups[$fp]['total_ms'] = ($groups[$fp]['total_ms'] ?? 0.0) + (float) $q['duration_ms'];
            $groups[$fp]['sample_sql'] ??= $q['sql'];
        }

        $result = [];
        foreach ($groups as $fp => $data) {
            if ($data['count'] < $minRepeats) {
                continue;
            }
            // "Tempo desperdiçado": tudo além da 1ª execução — se fosse resolvido com
            // um único IN()/JOIN, o custo tenderia ao de UMA consulta, não N.
            $avgMs = $data['total_ms'] / $data['count'];
            $wastedMs = $data['total_ms'] - $avgMs;

            $result[] = [
                'fingerprint' => $fp,
                'sample_sql' => $data['sample_sql'],
                'count' => $data['count'],
                'total_ms' => round($data['total_ms'], 2),
                'wasted_ms' => round($wastedMs, 2),
                'suggestion' => self::buildSuggestion($data['sample_sql'], $data['count']),
            ];
        }

        usort($result, static fn ($a, $b) => $b['wasted_ms'] <=> $a['wasted_ms']);

        return $result;
    }

    private static function buildSuggestion(string $sql, int $count): string
    {
        if (preg_match('/where\s+`?(\w+)`?\s*=\s*\??/i', $sql, $m)) {
            $col = $m[1];
            return "Executada {$count}x trocando só o valor de `{$col}`. Provável loop "
                . "(N+1). Considere buscar tudo de uma vez com `WHERE {$col} IN (...)` "
                . "ou um JOIN, fora do loop.";
        }

        return "Executada {$count}x no mesmo request com o mesmo formato de SQL. "
            . "Verifique se não está dentro de um loop (foreach) que poderia virar "
            . "uma única consulta com IN(...)/JOIN, ou usar cache/memoização.";
    }
}
