<?php

require_once __DIR__ . '/../Core/Database.php';

/**
 * EducaTudo - BnccService
 * Calcula a cobertura de habilidades da BNCC a partir dos planos de curso
 * (habilidades previstas) e da marcação de "trabalhada" feita pela coordenação.
 */
class BnccService
{
    /** @var Database */
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    private function pronto(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $row = $this->db->fetch(
                "SELECT COUNT(*) AS n FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name IN ('bncc_habilidades','plano_curso_habilidades')"
            );
            $ok = (int) ($row['n'] ?? 0) >= 2;
        } catch (Throwable $e) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * Cobertura geral: habilidades trabalhadas / previstas (distintas) nos planos de curso.
     *
     * @return array{previstas:int,trabalhadas:int,percentual:?float}
     */
    public function coberturaGeral(): array
    {
        if (!$this->pronto()) {
            return ['previstas' => 0, 'trabalhadas' => 0, 'percentual' => null];
        }
        $row = $this->db->fetch(
            "SELECT COUNT(DISTINCT habilidade_id) AS previstas,
                    COUNT(DISTINCT CASE WHEN trabalhada = 1 THEN habilidade_id END) AS trabalhadas
             FROM plano_curso_habilidades"
        );
        $previstas = (int) ($row['previstas'] ?? 0);
        $trabalhadas = (int) ($row['trabalhadas'] ?? 0);
        return [
            'previstas' => $previstas,
            'trabalhadas' => $trabalhadas,
            'percentual' => $previstas > 0 ? round(($trabalhadas / $previstas) * 100, 1) : null,
        ];
    }

    /**
     * Cobertura por componente curricular.
     *
     * @return list<array{componente:string,previstas:int,trabalhadas:int,percentual:?float}>
     */
    public function coberturaPorComponente(): array
    {
        if (!$this->pronto()) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT COALESCE(h.componente, 'Sem componente') AS componente,
                    COUNT(DISTINCT pch.habilidade_id) AS previstas,
                    COUNT(DISTINCT CASE WHEN pch.trabalhada = 1 THEN pch.habilidade_id END) AS trabalhadas
             FROM plano_curso_habilidades pch
             INNER JOIN bncc_habilidades h ON h.id = pch.habilidade_id
             GROUP BY COALESCE(h.componente, 'Sem componente')
             ORDER BY componente"
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $prev = (int) $r['previstas'];
            $trab = (int) $r['trabalhadas'];
            $out[] = [
                'componente' => (string) $r['componente'],
                'previstas' => $prev,
                'trabalhadas' => $trab,
                'percentual' => $prev > 0 ? round(($trab / $prev) * 100, 1) : null,
            ];
        }
        return $out;
    }

    /**
     * Habilidades previstas ainda não trabalhadas (pendências BNCC).
     *
     * @return list<array<string,mixed>>
     */
    public function pendentes(int $limite = 200): array
    {
        if (!$this->pronto()) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT DISTINCT h.codigo, h.descricao, h.componente
             FROM plano_curso_habilidades pch
             INNER JOIN bncc_habilidades h ON h.id = pch.habilidade_id
             WHERE pch.trabalhada = 0
               AND NOT EXISTS (
                   SELECT 1 FROM plano_curso_habilidades p2
                   WHERE p2.habilidade_id = pch.habilidade_id AND p2.trabalhada = 1
               )
             ORDER BY h.componente, h.codigo
             LIMIT " . (int) $limite
        ) ?: [];
    }
}
