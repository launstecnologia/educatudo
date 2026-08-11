<?php
/**
 * EducaTudo - Ficha complementar do aluno (saude, alimentacao, transporte).
 * Relacao 1:1 com alunos (PK = aluno_id).
 */

class StudentComplementaryRecord
{
    private $db;

    /** Campos editaveis da ficha (texto). */
    public const FIELDS = [
        'tipo_sanguineo',
        'alergias',
        'medicamentos_uso',
        'condicoes_cronicas',
        'deficiencias_obs',
        'plano_saude',
        'plano_saude_numero',
        'hospital_referencia',
        'contato_emergencia_nome',
        'contato_emergencia_telefone',
        'contato_emergencia_parentesco',
        'restricoes_alimentares',
        'alimentacao_obs',
        'transporte_tipo',
        'transporte_rota',
        'transporte_ponto',
        'transporte_responsavel',
        'transporte_telefone',
        'observacoes_gerais',
    ];

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
            $row = $this->db->fetch("SHOW TABLES LIKE 'alunos_ficha_complementar'");
            $cache = $row !== false && !empty($row);
        } catch (\Throwable $e) {
            $cache = false;
        }

        return $cache;
    }

    /** @return array<string,mixed> */
    public function getByAluno(int $alunoId): array
    {
        if ($alunoId <= 0 || !$this->tableExists()) {
            return [];
        }
        $row = $this->db->fetch(
            'SELECT * FROM alunos_ficha_complementar WHERE aluno_id = :id',
            ['id' => $alunoId]
        );

        return is_array($row) ? $row : [];
    }

    /**
     * Cria ou atualiza a ficha do aluno.
     * @param array<string,mixed> $data
     */
    public function upsert(int $alunoId, array $data): void
    {
        if ($alunoId <= 0 || !$this->tableExists()) {
            return;
        }

        $usaTransporte = !empty($data['usa_transporte_escolar']) ? 1 : 0;

        $columns = ['aluno_id', 'usa_transporte_escolar'];
        $placeholders = [':aluno_id', ':usa_transporte_escolar'];
        $params = ['aluno_id' => $alunoId, 'usa_transporte_escolar' => $usaTransporte];
        $updates = ['usa_transporte_escolar = VALUES(usa_transporte_escolar)'];

        foreach (self::FIELDS as $field) {
            $value = $data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    $value = null;
                }
            }
            $columns[] = $field;
            $placeholders[] = ':' . $field;
            $params[$field] = $value;
            $updates[] = "{$field} = VALUES({$field})";
        }

        $sql = 'INSERT INTO alunos_ficha_complementar (' . implode(', ', $columns) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')'
            . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates) . ', updated_at = NOW()';

        $this->db->query($sql, $params);
    }
}
