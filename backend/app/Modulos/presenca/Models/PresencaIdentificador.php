<?php

require_once __DIR__ . '/../../../Core/Database.php';

class PresencaIdentificador
{
    public const TIPOS = ['cartao', 'ra', 'codigo_aluno', 'externo'];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tabelasProntas(): bool
    {
        return $this->db->tableExists('presenca_identificadores');
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(int $limit = 200): array
    {
        if (!$this->tabelasProntas()) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        return $this->db->fetchAll(
            "SELECT i.id, i.aluno_id, i.tipo, i.valor, a.nome AS aluno_nome, a.ra, t.nome AS turma_nome
             FROM presenca_identificadores i
             INNER JOIN alunos a ON a.id = i.aluno_id
             LEFT JOIN turmas t ON t.id = a.turma_id
             ORDER BY a.nome ASC
             LIMIT {$limit}"
        ) ?: [];
    }

    public function findAlunoId(string $tipo, string $valor): ?int
    {
        $valor = trim($valor);
        if ($valor === '' || !$this->tabelasProntas()) {
            return null;
        }
        if (!in_array($tipo, self::TIPOS, true)) {
            $tipo = 'cartao';
        }
        $row = $this->db->fetch(
            'SELECT aluno_id FROM presenca_identificadores WHERE tipo = :tipo AND valor = :valor LIMIT 1',
            ['tipo' => $tipo, 'valor' => $valor]
        );
        $id = (int) ($row['aluno_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    public function criar(int $alunoId, string $tipo, string $valor): int
    {
        if ($alunoId <= 0) {
            throw new InvalidArgumentException('Aluno inválido.');
        }
        $aluno = $this->db->fetch('SELECT id FROM alunos WHERE id = :id AND ativo = 1 LIMIT 1', ['id' => $alunoId]);
        if (!$aluno) {
            throw new InvalidArgumentException('Aluno não encontrado ou inativo.');
        }
        $valor = trim($valor);
        if ($valor === '' || mb_strlen($valor) > 80) {
            throw new InvalidArgumentException('Identificador inválido.');
        }
        if (!in_array($tipo, self::TIPOS, true)) {
            $tipo = 'cartao';
        }
        if (!$this->tabelasProntas()) {
            throw new RuntimeException('Rode a migration 2026_08_22_gestao_presenca.sql no Master.');
        }
        return (int) $this->db->insert(
            'INSERT INTO presenca_identificadores (aluno_id, tipo, valor)
             VALUES (:aluno_id, :tipo, :valor)',
            ['aluno_id' => $alunoId, 'tipo' => $tipo, 'valor' => $valor]
        );
    }

    public function excluir(int $id): void
    {
        if ($id <= 0 || !$this->tabelasProntas()) {
            return;
        }
        $this->db->query('DELETE FROM presenca_identificadores WHERE id = :id', ['id' => $id]);
    }
}
