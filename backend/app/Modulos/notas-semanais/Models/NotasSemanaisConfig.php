<?php
/**
 * Persistência da config do quadro de notas semanais.
 */

class NotasSemanaisConfig
{
    /** @var Database */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tabelasProntas(): bool
    {
        try {
            return $this->db->fetch("SHOW TABLES LIKE 'notas_semanais_config'") !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function obter(): array
    {
        $padrao = $this->padrao();
        if (!$this->tabelasProntas()) {
            return $padrao;
        }
        $row = $this->db->fetch('SELECT * FROM notas_semanais_config WHERE id = 1');
        if (!$row) {
            $this->db->insert('INSERT INTO notas_semanais_config (id) VALUES (1)');
            return $padrao;
        }

        return array_merge($padrao, [
            'semanas_grupo_a' => $this->parseSemanas((string) ($row['semanas_grupo_a'] ?? '1,3,5,7')),
            'semanas_grupo_b' => $this->parseSemanas((string) ($row['semanas_grupo_b'] ?? '2,4,6,8')),
            'peso_media_sem' => (float) ($row['peso_media_sem'] ?? 4),
            'peso_prova_bim' => (float) ($row['peso_prova_bim'] ?? 4),
            'peso_enac' => (float) ($row['peso_enac'] ?? 1),
            'peso_participacao' => (float) ($row['peso_participacao'] ?? 0.5),
            'peso_trabalho' => (float) ($row['peso_trabalho'] ?? 0.5),
            'regra_recuperacao' => in_array((string) ($row['regra_recuperacao'] ?? ''), ['maior', 'substitui_se_abaixo'], true)
                ? (string) $row['regra_recuperacao']
                : 'maior',
            'media_minima' => (float) ($row['media_minima'] ?? 6),
        ]);
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function salvar(array $dados): void
    {
        if (!$this->tabelasProntas()) {
            return;
        }
        $a = $this->normalizarSemanas($dados['semanas_grupo_a'] ?? [], [1, 3, 5, 7]);
        $b = $this->normalizarSemanas($dados['semanas_grupo_b'] ?? [], [2, 4, 6, 8]);
        $b = array_values(array_diff($b, $a));
        if ($b === []) {
            $b = array_values(array_diff([2, 4, 6, 8], $a));
        }
        $this->db->query(
            'UPDATE notas_semanais_config
             SET semanas_grupo_a = :a,
                 semanas_grupo_b = :b
             WHERE id = 1',
            [
                'a' => implode(',', $a),
                'b' => implode(',', $b),
            ]
        );
    }

    /**
     * @return array<int, string> materia_id => A|B
     */
    public function mapaGruposMaterias(): array
    {
        if (!$this->tabelasProntas()) {
            return [];
        }
        $rows = $this->db->fetchAll('SELECT materia_id, grupo FROM notas_semanais_materias') ?: [];
        $out = [];
        foreach ($rows as $r) {
            $id = (int) ($r['materia_id'] ?? 0);
            $g = strtoupper((string) ($r['grupo'] ?? ''));
            if ($id > 0 && ($g === 'A' || $g === 'B')) {
                $out[$id] = $g;
            }
        }
        return $out;
    }

    /**
     * @param array<int, string> $mapa materia_id => A|B
     */
    public function salvarGruposMaterias(array $mapa): void
    {
        if (!$this->tabelasProntas()) {
            return;
        }
        $this->db->query('DELETE FROM notas_semanais_materias');
        foreach ($mapa as $materiaId => $grupo) {
            $id = (int) $materiaId;
            $g = strtoupper((string) $grupo);
            if ($id <= 0 || ($g !== 'A' && $g !== 'B')) {
                continue;
            }
            $this->db->insert(
                'INSERT INTO notas_semanais_materias (materia_id, grupo) VALUES (:id, :grupo)',
                ['id' => $id, 'grupo' => $g]
            );
        }
    }

    /**
     * @return list<array{id:int,nome:string,grupo:?string}>
     */
    public function listarMateriasComGrupo(): array
    {
        $mapa = $this->mapaGruposMaterias();
        $rows = $this->db->fetchAll('SELECT id, nome FROM materias ORDER BY nome ASC') ?: [];
        $out = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nome' => (string) ($r['nome'] ?? ''),
                'grupo' => $mapa[$id] ?? null,
            ];
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function padrao(): array
    {
        return [
            'semanas_grupo_a' => [1, 3, 5, 7],
            'semanas_grupo_b' => [2, 4, 6, 8],
            'peso_media_sem' => 4.0,
            'peso_prova_bim' => 4.0,
            'peso_enac' => 1.0,
            'peso_participacao' => 0.5,
            'peso_trabalho' => 0.5,
            'regra_recuperacao' => 'maior',
            'media_minima' => 6.0,
        ];
    }

    /**
     * @return list<int>
     */
    public function parseSemanas(string $csv): array
    {
        $out = [];
        foreach (explode(',', $csv) as $p) {
            $n = (int) trim($p);
            if ($n >= 1 && $n <= 8) {
                $out[] = $n;
            }
        }
        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    /**
     * @param mixed $raw
     * @param list<int> $fallback
     * @return list<int>
     */
    private function normalizarSemanas($raw, array $fallback): array
    {
        if (is_string($raw)) {
            $parsed = $this->parseSemanas($raw);
            return $parsed === [] ? $fallback : $parsed;
        }
        if (!is_array($raw)) {
            return $fallback;
        }
        $out = [];
        foreach ($raw as $v) {
            $n = (int) $v;
            if ($n >= 1 && $n <= 8) {
                $out[] = $n;
            }
        }
        $out = array_values(array_unique($out));
        sort($out);
        return $out === [] ? $fallback : $out;
    }
}
