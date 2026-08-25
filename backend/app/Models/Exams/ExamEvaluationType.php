<?php
/**
 * EducaTudo - Tipos de Avaliação de Provas Online
 */

class ExamEvaluationType
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll(): array
    {
        return $this->db->fetchAll(
            "SELECT *
             FROM provas_tipos_avaliacao
             WHERE deleted_at IS NULL
             ORDER BY ativo DESC, ordem ASC, nome ASC"
        ) ?: [];
    }

    public function getAllActive(): array
    {
        return $this->db->fetchAll(
            "SELECT *
             FROM provas_tipos_avaliacao
             WHERE deleted_at IS NULL AND ativo = 1
             ORDER BY ordem ASC, nome ASC"
        ) ?: [];
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch(
            "SELECT *
             FROM provas_tipos_avaliacao
             WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function existsByName(string $nome, ?int $ignoreId = null): bool
    {
        $sql = "SELECT id FROM provas_tipos_avaliacao WHERE deleted_at IS NULL AND LOWER(nome) = LOWER(:nome)";
        $params = ['nome' => trim($nome)];
        if ($ignoreId !== null && $ignoreId > 0) {
            $sql .= " AND id != :id";
            $params['id'] = $ignoreId;
        }
        $row = $this->db->fetch($sql . " LIMIT 1", $params);
        return !empty($row);
    }

    public static function chavesQuadro(): array
    {
        return [
            'semanal' => 'Prova semanal (S1–S8)',
            'prova_bim' => 'Prova bimestral',
            'enac' => 'ENAC',
            'participacao' => 'Participação',
            'trabalho' => 'Trabalho',
            'recuperacao' => 'Recuperação',
        ];
    }

    public function temColunaChaveQuadro(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'provas_tipos_avaliacao'
                   AND COLUMN_NAME = 'chave_quadro'
                 LIMIT 1"
            );
            $cache = !empty($row);
        } catch (Exception $e) {
            $cache = false;
        }
        return $cache;
    }

    public function sanitizarChaveQuadro($valor): ?string
    {
        $c = strtolower(trim((string) $valor));
        return array_key_exists($c, self::chavesQuadro()) ? $c : null;
    }

    public function create(array $data): int
    {
        $params = [
            'nome' => trim((string) ($data['nome'] ?? '')),
            'descricao' => !empty($data['descricao']) ? trim((string) $data['descricao']) : null,
            'ativo' => !empty($data['ativo']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
        ];
        if ($this->temColunaChaveQuadro()) {
            $params['chave_quadro'] = $this->sanitizarChaveQuadro($data['chave_quadro'] ?? null);
            return (int) $this->db->insert(
                "INSERT INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem, chave_quadro)
                 VALUES (:nome, :descricao, :ativo, :ordem, :chave_quadro)",
                $params
            );
        }
        return (int) $this->db->insert(
            "INSERT INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem)
             VALUES (:nome, :descricao, :ativo, :ordem)",
            $params
        );
    }

    public function update(int $id, array $data): bool
    {
        $params = [
            'id' => $id,
            'nome' => trim((string) ($data['nome'] ?? '')),
            'descricao' => !empty($data['descricao']) ? trim((string) $data['descricao']) : null,
            'ativo' => !empty($data['ativo']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
        ];
        $setChave = '';
        if ($this->temColunaChaveQuadro()) {
            $setChave = ', chave_quadro = :chave_quadro';
            $params['chave_quadro'] = $this->sanitizarChaveQuadro($data['chave_quadro'] ?? null);
        }
        return (bool) $this->db->query(
            "UPDATE provas_tipos_avaliacao
             SET nome = :nome,
                 descricao = :descricao,
                 ativo = :ativo,
                 ordem = :ordem
                 {$setChave}
             WHERE id = :id AND deleted_at IS NULL",
            $params
        );
    }

    public function softDelete(int $id): bool
    {
        return (bool) $this->db->query(
            "UPDATE provas_tipos_avaliacao
             SET deleted_at = NOW()
             WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
    }
}

