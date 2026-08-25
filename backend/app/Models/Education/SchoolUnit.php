<?php
/**
 * EducaTudo - Modelo de Unidades da Escola (matriz/filial)
 * Guarda os dados institucionais usados no cabeçalho de documentos oficiais
 * (declarações). O aluno é vinculado a uma unidade via alunos.unidade_id.
 */

class SchoolUnit
{
    private $db;

    /** Colunas editáveis da tabela unidades. */
    private const FIELDS = [
        'nome', 'tipo', 'razao_social', 'cnpj', 'inep', 'dependencia_administrativa', 'endereco', 'numero',
        'complemento', 'bairro', 'cidade', 'uf', 'cep', 'telefone', 'email',
        'diretor_nome', 'secretario_nome',
        'ato_autorizacao', 'ato_credenciamento', 'ato_reconhecimento',
        'diretor_registro', 'secretario_registro',
        'logo_url', 'ativo',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Indica se a tabela unidades existe no schema atual (evita erro antes da migration).
     */
    public function tableExists(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'unidades' LIMIT 1"
            );
            $cache = !empty($row['ok']);
        } catch (\Throwable $e) {
            $cache = false;
        }
        return $cache;
    }

    public function getAll(): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT * FROM unidades ORDER BY tipo = 'matriz' DESC, nome ASC"
        ) ?: [];
    }

    public function getActive(): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT * FROM unidades WHERE ativo = 1 ORDER BY tipo = 'matriz' DESC, nome ASC"
        ) ?: [];
    }

    public function findById($id)
    {
        if (!$this->tableExists()) {
            return false;
        }
        return $this->db->fetch(
            "SELECT * FROM unidades WHERE id = :id LIMIT 1",
            ['id' => (int) $id]
        );
    }

    /**
     * Resolve a unidade de um aluno. Faz fallback para a unidade matriz (mais antiga)
     * quando o aluno não tem unidade vinculada, garantindo cabeçalho no documento.
     */
    public function findForStudent($alunoId)
    {
        if (!$this->tableExists()) {
            return false;
        }
        if ($this->colunaAlunoUnidadeExiste()) {
            $row = $this->db->fetch(
                "SELECT u.* FROM alunos a
                 INNER JOIN unidades u ON u.id = a.unidade_id
                 WHERE a.id = :id LIMIT 1",
                ['id' => (int) $alunoId]
            );
            if ($row) {
                return $row;
            }
        }
        return $this->db->fetch(
            "SELECT * FROM unidades WHERE ativo = 1 ORDER BY tipo = 'matriz' DESC, id ASC LIMIT 1"
        );
    }

    public function create(array $data)
    {
        $payload = $this->sanitize($data);
        if (trim((string) ($payload['nome'] ?? '')) === '') {
            throw new \InvalidArgumentException('Nome da unidade é obrigatório');
        }
        $columns = array_keys($payload);
        $placeholders = array_map(static fn ($c) => ':' . $c, $columns);
        $sql = 'INSERT INTO unidades (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        return $this->db->insert($sql, $payload);
    }

    public function update($id, array $data)
    {
        $payload = $this->sanitize($data);
        if (trim((string) ($payload['nome'] ?? '')) === '') {
            throw new \InvalidArgumentException('Nome da unidade é obrigatório');
        }
        $sets = [];
        foreach ($payload as $col => $_val) {
            $sets[] = $col . ' = :' . $col;
        }
        $payload['id'] = (int) $id;
        $sql = 'UPDATE unidades SET ' . implode(', ', $sets) . ' WHERE id = :id';
        return $this->db->update($sql, $payload);
    }

    public function delete($id)
    {
        return $this->db->delete("DELETE FROM unidades WHERE id = :id", ['id' => (int) $id]);
    }

    /**
     * Quantos alunos estão vinculados a esta unidade (para impedir exclusão acidental).
     */
    public function countStudents($id): int
    {
        if (!$this->colunaAlunoUnidadeExiste()) {
            return 0;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM alunos WHERE unidade_id = :id",
            ['id' => (int) $id]
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Indica se alunos.unidade_id existe (a migration original pode ter criado
     * a tabela unidades sem a coluna, e a listagem de Instituição quebrava).
     */
    private function colunaAlunoUnidadeExiste(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->fetch(
                "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'unidade_id'"
            );
            $cache = !empty($row['total']);
        } catch (\Throwable $e) {
            $cache = false;
        }
        return $cache;
    }

    /**
     * Mantém apenas as colunas conhecidas e normaliza tipos básicos.
     *
     * @return array<string, mixed>
     */
    private function existingColumns(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $rows = $this->db->fetchAll(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unidades'"
            ) ?: [];
            $cache = array_map(static fn ($r) => $r['COLUMN_NAME'], $rows);
        } catch (\Throwable $e) {
            $cache = self::FIELDS;
        }

        return $cache;
    }

    private function sanitize(array $data): array
    {
        $colunasExistentes = $this->existingColumns();
        $out = [];
        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            if (!in_array($field, $colunasExistentes, true)) {
                continue;
            }
            $value = $data[$field];
            if ($field === 'ativo') {
                $out[$field] = !empty($value) ? 1 : 0;
                continue;
            }
            if ($field === 'tipo') {
                $out[$field] = $value === 'filial' ? 'filial' : 'matriz';
                continue;
            }
            if ($field === 'uf') {
                $out[$field] = strtoupper(substr(trim((string) $value), 0, 2)) ?: null;
                continue;
            }
            $str = is_string($value) ? trim($value) : $value;
            $out[$field] = ($str === '' ? null : $str);
        }
        return $out;
    }
}
