<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - BnccSkill
 * Catálogo de habilidades da BNCC (códigos tipo EF06MA17).
 */
class BnccSkill
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tableExists(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'bncc_habilidades'");
            $cache = $row !== false && !empty($row);
        } catch (Throwable $e) {
            $cache = false;
        }
        return $cache;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(string $busca = '', string $componente = '', int $limite = 300): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $where = ['ativo = 1'];
        $params = [];
        if ($busca !== '') {
            $where[] = '(codigo LIKE :busca OR descricao LIKE :busca)';
            $params['busca'] = '%' . $busca . '%';
        }
        if ($componente !== '') {
            $where[] = 'componente = :componente';
            $params['componente'] = $componente;
        }
        return $this->db->fetchAll(
            "SELECT * FROM bncc_habilidades WHERE " . implode(' AND ', $where) .
            " ORDER BY componente ASC, codigo ASC LIMIT " . (int) $limite,
            $params
        ) ?: [];
    }

    /** @return list<string> */
    public function componentes(): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT componente FROM bncc_habilidades WHERE ativo = 1 AND componente IS NOT NULL AND componente <> '' ORDER BY componente"
        ) ?: [];
        return array_map(static fn($r) => (string) $r['componente'], $rows);
    }

    public function total(): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $row = $this->db->fetch("SELECT COUNT(*) AS n FROM bncc_habilidades WHERE ativo = 1");
        return (int) ($row['n'] ?? 0);
    }

    /** @return list<array<string,mixed>> */
    public function byIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($v) => $v > 0)));
        if (!$ids || !$this->tableExists()) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return $this->db->fetchAll("SELECT * FROM bncc_habilidades WHERE id IN ($ph) ORDER BY codigo", $ids) ?: [];
    }

    /**
     * Importa/atualiza habilidades a partir de uma lista (upsert por código).
     *
     * @param list<array<string,mixed>> $habilidades
     * @return int quantidade processada
     */
    public function importar(array $habilidades): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $n = 0;
        foreach ($habilidades as $h) {
            $codigo = trim((string) ($h['codigo'] ?? ''));
            $descricao = trim((string) ($h['descricao'] ?? ''));
            if ($codigo === '' || $descricao === '') {
                continue;
            }
            $this->db->query(
                "INSERT INTO bncc_habilidades (codigo, descricao, etapa, componente, ano_serie, unidade_tematica, objeto_conhecimento)
                 VALUES (:codigo, :descricao, :etapa, :componente, :ano_serie, :unidade_tematica, :objeto_conhecimento)
                 ON DUPLICATE KEY UPDATE descricao = VALUES(descricao), etapa = VALUES(etapa),
                    componente = VALUES(componente), ano_serie = VALUES(ano_serie),
                    unidade_tematica = VALUES(unidade_tematica), objeto_conhecimento = VALUES(objeto_conhecimento),
                    updated_at = CURRENT_TIMESTAMP",
                [
                    'codigo' => mb_substr($codigo, 0, 20),
                    'descricao' => $descricao,
                    'etapa' => $h['etapa'] ?? null,
                    'componente' => $h['componente'] ?? null,
                    'ano_serie' => $h['ano_serie'] ?? null,
                    'unidade_tematica' => $h['unidade_tematica'] ?? null,
                    'objeto_conhecimento' => $h['objeto_conhecimento'] ?? null,
                ]
            );
            $n++;
        }
        return $n;
    }
}
