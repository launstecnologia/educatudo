<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - AVA: Curso.
 */
class Course
{
    private $db;

    public const MODALIDADES = [
        'fundamental' => 'Ensino Fundamental',
        'medio' => 'Ensino Médio',
        'tecnico' => 'Ensino Técnico',
        'graduacao' => 'Graduação',
        'pos' => 'Pós-graduação',
        'livre' => 'Curso Livre',
    ];

    public const STATUS = ['rascunho' => 'Rascunho', 'ativo' => 'Ativo', 'arquivado' => 'Arquivado'];

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
            $row = $this->db->fetch("SHOW TABLES LIKE 'ava_cursos'");
            $cache = $row !== false && !empty($row);
        } catch (Throwable $e) {
            $cache = false;
        }
        return $cache;
    }

    /** @return list<array<string,mixed>> */
    public function all(string $busca = '', string $status = ''): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $where = [];
        $params = [];
        if ($busca !== '') {
            $where[] = '(c.nome LIKE :busca OR c.codigo LIKE :busca)';
            $params['busca'] = '%' . $busca . '%';
        }
        if ($status !== '') {
            $where[] = 'c.status = :status';
            $params['status'] = $status;
        }
        $sql = "SELECT c.*, cat.nome AS categoria_nome,
                       (SELECT COUNT(*) FROM ava_disciplinas d WHERE d.curso_id = c.id) AS total_disciplinas
                FROM ava_cursos c
                LEFT JOIN ava_categorias cat ON cat.id = c.categoria_id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY c.nome ASC';
        return $this->db->fetchAll($sql, $params) ?: [];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists()) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT c.*, cat.nome AS categoria_nome FROM ava_cursos c
             LEFT JOIN ava_categorias cat ON cat.id = c.categoria_id WHERE c.id = :id",
            ['id' => $id]
        );
        return $row ?: null;
    }

    /** @param array<string,mixed> $data */
    public function save(array $data, ?int $id = null): int
    {
        $params = [
            'nome' => trim((string) ($data['nome'] ?? '')),
            'codigo' => trim((string) ($data['codigo'] ?? '')) ?: null,
            'modalidade' => isset(self::MODALIDADES[$data['modalidade'] ?? '']) ? $data['modalidade'] : 'livre',
            'categoria_id' => (int) ($data['categoria_id'] ?? 0) ?: null,
            'carga_horaria' => (int) ($data['carga_horaria'] ?? 0),
            'descricao' => trim((string) ($data['descricao'] ?? '')) ?: null,
            'objetivos' => trim((string) ($data['objetivos'] ?? '')) ?: null,
            'competencias' => trim((string) ($data['competencias'] ?? '')) ?: null,
            'bibliografia' => trim((string) ($data['bibliografia'] ?? '')) ?: null,
            'certificacao' => !empty($data['certificacao']) ? 1 : 0,
            'imagem_key' => $data['imagem_key'] ?? null,
            'banner_key' => $data['banner_key'] ?? null,
            'status' => isset(self::STATUS[$data['status'] ?? '']) ? $data['status'] : 'rascunho',
        ];
        if ($id !== null && $id > 0) {
            $params['id'] = $id;
            $this->db->update(
                "UPDATE ava_cursos SET nome=:nome, codigo=:codigo, modalidade=:modalidade, categoria_id=:categoria_id,
                    carga_horaria=:carga_horaria, descricao=:descricao, objetivos=:objetivos, competencias=:competencias,
                    bibliografia=:bibliografia, certificacao=:certificacao, imagem_key=:imagem_key, banner_key=:banner_key,
                    status=:status, updated_at=NOW() WHERE id=:id",
                $params
            );
            return $id;
        }
        $params['created_by'] = (int) ($data['created_by'] ?? 0) ?: null;
        return (int) $this->db->insert(
            "INSERT INTO ava_cursos (nome, codigo, modalidade, categoria_id, carga_horaria, descricao, objetivos,
                competencias, bibliografia, certificacao, imagem_key, banner_key, status, created_by)
             VALUES (:nome, :codigo, :modalidade, :categoria_id, :carga_horaria, :descricao, :objetivos,
                :competencias, :bibliografia, :certificacao, :imagem_key, :banner_key, :status, :created_by)",
            $params
        );
    }

    public function delete(int $id): void
    {
        if ($id > 0 && $this->tableExists()) {
            $this->db->query("DELETE FROM ava_cursos WHERE id = :id", ['id' => $id]);
        }
    }

    public static function modalidadeLabel(string $m): string
    {
        return self::MODALIDADES[$m] ?? $m;
    }
}
