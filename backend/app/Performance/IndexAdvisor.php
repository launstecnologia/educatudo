<?php

namespace App\Performance;

/**
 * Sugere (nunca cria) índices possivelmente ausentes, a partir do texto da query.
 *
 * É uma heurística baseada em regex sobre o SQL — não analisa o schema real nem
 * o EXPLAIN. Serve para apontar candidatos óbvios (WHERE/ORDER BY em colunas que
 * não aparecem já combinadas num índice conhecido). Sempre valide com
 * `EXPLAIN`/`SHOW INDEX` antes de criar qualquer índice em produção.
 */
final class IndexAdvisor
{
    /**
     * @return array{table:?string,where_columns:list<string>,order_columns:list<string>,suggestion:?string}
     */
    public static function suggest(string $sql): array
    {
        // Remove o conteúdo de subqueries entre parênteses antes de analisar — sem isso,
        // um `SELECT (SELECT COUNT(*) FROM outra_tabela WHERE ...) FROM tabela_principal ...`
        // faz o regex pegar a tabela/colunas da subquery em vez da consulta principal.
        $outerSql = self::stripParenGroups($sql);

        $table = self::extractTable($outerSql);
        $whereColumns = self::extractWhereColumns($outerSql);
        $orderColumns = self::extractOrderColumns($outerSql);

        if ($table === null || ($whereColumns === [] && $orderColumns === [])) {
            return [
                'table' => $table,
                'where_columns' => $whereColumns,
                'order_columns' => $orderColumns,
                'suggestion' => null,
            ];
        }

        // Ordem sugerida: igualdade (WHERE) primeiro, depois ordenação (ORDER BY) —
        // regra prática de "equality before range/sort" para índices compostos no MySQL.
        $columns = array_values(array_unique(array_merge($whereColumns, $orderColumns)));
        $columns = array_slice($columns, 0, 5); // não sugerir índice gigante

        $suggestion = sprintf(
            'CREATE INDEX idx_%s_%s ON `%s` (%s); -- sugestão heurística, valide com EXPLAIN antes de aplicar',
            $table,
            implode('_', $columns),
            $table,
            implode(', ', array_map(static fn ($c) => "`{$c}`", $columns))
        );

        return [
            'table' => $table,
            'where_columns' => $whereColumns,
            'order_columns' => $orderColumns,
            'suggestion' => $suggestion,
        ];
    }

    /**
     * Apaga só os grupos "(...)" de nível mais alto que contenham uma subquery
     * (SELECT em qualquer profundidade dentro do grupo) — ex.: colunas calculadas
     * `(SELECT COUNT(*) FROM x WHERE ...)` ou `IN (SELECT id FROM y)`.
     * Grupos de agrupamento lógico puro, tipo `(a OR (b AND c))`, ficam intactos
     * (por definição não têm SELECT em lugar nenhum dentro deles).
     */
    private static function stripParenGroups(string $sql): string
    {
        $len = strlen($sql);
        $result = $sql;
        // Processa de fora pra dentro: acha cada par top-level (...) do nível 0.
        $spans = [];
        $depth = 0;
        $start = null;
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($ch === '(') {
                if ($depth === 0) {
                    $start = $i;
                }
                $depth++;
            } elseif ($ch === ')') {
                if ($depth > 0) {
                    $depth--;
                    if ($depth === 0 && $start !== null) {
                        $spans[] = [$start, $i];
                        $start = null;
                    }
                }
            }
        }

        // Aplica de trás pra frente pra não invalidar os offsets já calculados.
        foreach (array_reverse($spans) as [$open, $close]) {
            $inner = substr($sql, $open + 1, $close - $open - 1);
            if (preg_match('/\bselect\b/i', $inner)) {
                $result = substr($result, 0, $open + 1) . str_repeat(' ', $close - $open - 1) . substr($result, $close);
            }
        }

        return $result;
    }

    private static function extractTable(string $sql): ?string
    {
        if (preg_match('/from\s+`?(\w+)`?/i', $sql, $m)) {
            return $m[1];
        }
        if (preg_match('/update\s+`?(\w+)`?/i', $sql, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * @return list<string>
     */
    private static function extractWhereColumns(string $sql): array
    {
        if (!preg_match('/\bwhere\b(.*?)(\border\s+by\b|\bgroup\s+by\b|\blimit\b|$)/is', $sql, $m)) {
            return [];
        }
        $whereClause = $m[1];
        $columns = [];
        // Colunas em comparações simples: coluna = / > / < / IN / LIKE valor.
        // Aceita opcionalmente um alias antes do ponto (ex.: `j.turma_id =`) e usa
        // só o nome da coluna, não o alias da tabela.
        if (preg_match_all('/(?:`?\w+`?\.)?`?(\w+)`?\s*(?:=|>|<|>=|<=|<>|!=|\bin\b|\blike\b)/i', $whereClause, $mm)) {
            foreach ($mm[1] as $col) {
                $colLower = strtolower($col);
                if (in_array($colLower, ['and', 'or', 'not', 'null', 'is'], true)) {
                    continue;
                }
                $columns[] = $col;
            }
        }
        return array_values(array_unique($columns));
    }

    /**
     * @return list<string>
     */
    private static function extractOrderColumns(string $sql): array
    {
        if (!preg_match('/order\s+by\s+(.*?)(\blimit\b|$)/is', $sql, $m)) {
            return [];
        }
        $cols = [];
        foreach (explode(',', $m[1]) as $part) {
            $part = trim($part);
            // Aceita `alias.coluna` ou só `coluna` — sempre pega o nome da coluna, não o alias.
            if (preg_match('/^(?:`?\w+`?\.)?`?(\w+)`?/', $part, $mm)) {
                $cols[] = $mm[1];
            }
        }
        return array_values(array_unique($cols));
    }
}
