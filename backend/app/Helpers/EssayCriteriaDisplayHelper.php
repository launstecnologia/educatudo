<?php

/**
 * Rótulos e totais de critérios de redação (ENEM usa "Competência"; demais bancas usam "Correção").
 */
class EssayCriteriaDisplayHelper
{
    public static function isEnemBoard(?string $boardName, ?string $boardSlug = null): bool
    {
        $slug = strtolower(trim((string) $boardSlug));
        $name = strtolower(trim((string) $boardName));

        if ($slug !== '' && strpos($slug, 'enem') !== false) {
            return true;
        }

        return $name !== '' && strpos($name, 'enem') !== false;
    }

    /**
     * Monta lista de critérios para exibição (admin > IA > padrão ENEM).
     *
     * @return array<int, array{slug:string,name:string,max_score:float,order_position:int}>
     */
    public static function buildCriteriaDisplay(array $criteria, array $gradesJson, bool $isEnem): array
    {
        if (!empty($criteria)) {
            $items = [];
            $pos = 0;
            foreach ($criteria as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $slug = trim((string) ($row['slug'] ?? ''));
                if ($slug === '') {
                    continue;
                }
                $pos++;
                $items[] = [
                    'slug' => $slug,
                    'name' => (string) ($row['name'] ?? ''),
                    'max_score' => (float) ($row['max_score'] ?? 200),
                    'order_position' => (int) ($row['order_position'] ?? $pos),
                ];
            }
            if (!empty($items)) {
                return $items;
            }
        }

        if (!empty($gradesJson)) {
            $items = [];
            $pos = 0;
            foreach ($gradesJson as $slug => $item) {
                if (!is_string($slug) || $slug === '') {
                    continue;
                }
                $pos++;
                $max = 200.0;
                if (is_array($item)) {
                    if (isset($item['max_score']) && is_numeric($item['max_score'])) {
                        $max = (float) $item['max_score'];
                    } elseif (isset($item['max']) && is_numeric($item['max'])) {
                        $max = (float) $item['max'];
                    }
                }
                $items[] = [
                    'slug' => $slug,
                    'name' => self::defaultCriterionName($pos, $isEnem),
                    'max_score' => $max,
                    'order_position' => $pos,
                ];
            }
            if (!empty($items)) {
                return $items;
            }
        }

        if (!$isEnem) {
            return [];
        }

        $enemNomes = [
            'Domínio da norma padrão',
            'Compreensão do tema',
            'Seleção de argumentos',
            'Conhecimento dos mecanismos linguísticos',
            'Proposta de intervenção',
        ];
        $items = [];
        foreach ($enemNomes as $i => $nome) {
            $items[] = [
                'slug' => 'competencia_' . ($i + 1),
                'name' => $nome,
                'max_score' => 200.0,
                'order_position' => $i + 1,
            ];
        }

        return $items;
    }

    public static function getSectionTitle(bool $isEnem, bool $plural = true): string
    {
        if ($isEnem) {
            return $plural ? 'Competências' : 'Competência';
        }

        return $plural ? 'Correções' : 'Correção';
    }

    public static function formatCriterionLabel(int $position, string $name, bool $isEnem): string
    {
        $name = trim($name);
        $prefix = $isEnem ? 'Competência' : 'Correção';

        if ($name === '') {
            return $prefix . ' ' . $position;
        }

        return $prefix . ' ' . $position . ': ' . $name;
    }

    public static function calculateMaxTotal(array $criteriaDisplay, bool $isEnem): float
    {
        $sum = 0.0;
        foreach ($criteriaDisplay as $criterion) {
            $sum += (float) ($criterion['max_score'] ?? 0);
        }

        if ($sum > 0) {
            return $sum;
        }

        return $isEnem ? 1000.0 : 100.0;
    }

    private static function defaultCriterionName(int $position, bool $isEnem): string
    {
        return $isEnem
            ? ('Competência ' . $position)
            : ('Correção ' . $position);
    }
}
