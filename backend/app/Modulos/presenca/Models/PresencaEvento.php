<?php

require_once __DIR__ . '/../../../Core/Database.php';

class PresencaEvento
{
    public const ORIGENS = ['integracao', 'manual_secretaria', 'facial', 'importacao'];
    public const TIPOS = ['entrada', 'saida'];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tabelasProntas(): bool
    {
        return $this->db->tableExists('presenca_eventos');
    }

    public function findByIdExterno(string $idExterno): ?array
    {
        if ($idExterno === '' || !$this->tabelasProntas()) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT * FROM presenca_eventos WHERE id_externo = :id LIMIT 1',
            ['id' => $idExterno]
        );
        return $row ?: null;
    }

    /**
     * @return array{id:int, duplicado:bool}
     */
    public function inserir(array $dados): array
    {
        $idExterno = trim((string) ($dados['id_externo'] ?? ''));
        if ($idExterno === '') {
            throw new InvalidArgumentException('id_externo é obrigatório.');
        }
        $existente = $this->findByIdExterno($idExterno);
        if ($existente) {
            return ['id' => (int) $existente['id'], 'duplicado' => true];
        }
        $tipo = (string) ($dados['tipo'] ?? '');
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new InvalidArgumentException('Tipo deve ser entrada ou saida.');
        }
        $origem = (string) ($dados['origem'] ?? 'integracao');
        if (!in_array($origem, self::ORIGENS, true)) {
            $origem = 'integracao';
        }
        $alunoId = (int) ($dados['aluno_id'] ?? 0);
        try {
            $id = (int) $this->db->insert(
                'INSERT INTO presenca_eventos
                    (aluno_id, tipo, ocorrido_em, origem, integracao_id, id_externo, identificador_bruto, registrado_por)
                 VALUES
                    (:aluno_id, :tipo, :ocorrido_em, :origem, :integracao_id, :id_externo, :identificador, :registrado_por)',
                [
                    'aluno_id' => $alunoId > 0 ? $alunoId : null,
                    'tipo' => $tipo,
                    'ocorrido_em' => (string) $dados['ocorrido_em'],
                    'origem' => $origem,
                    'integracao_id' => !empty($dados['integracao_id']) ? (int) $dados['integracao_id'] : null,
                    'id_externo' => $idExterno,
                    'identificador' => $dados['identificador_bruto'] ?? null,
                    'registrado_por' => !empty($dados['registrado_por']) ? (int) $dados['registrado_por'] : null,
                ]
            );
            return ['id' => $id, 'duplicado' => false];
        } catch (Throwable $e) {
            $existente = $this->findByIdExterno($idExterno);
            if ($existente) {
                return ['id' => (int) $existente['id'], 'duplicado' => true];
            }
            throw $e;
        }
    }

    public function marcarProcessado(int $id, ?string $erro = null): void
    {
        if ($id <= 0 || !$this->tabelasProntas()) {
            return;
        }
        $this->db->update(
            'UPDATE presenca_eventos
             SET processado_em = NOW(), erro_processamento = :erro
             WHERE id = :id',
            ['id' => $id, 'erro' => $erro]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function doAlunoNoDia(int $alunoId, string $data): array
    {
        if ($alunoId <= 0 || !$this->tabelasProntas()) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT * FROM presenca_eventos
             WHERE aluno_id = :aluno_id AND DATE(ocorrido_em) = :data
             ORDER BY ocorrido_em ASC, id ASC',
            ['aluno_id' => $alunoId, 'data' => $data]
        ) ?: [];
    }

    /**
     * @return array{rows:list<array<string,mixed>>, total:int}
     */
    public function listar(string $data, int $page = 1, int $perPage = 10, int $alunoId = 0): array
    {
        if (!$this->tabelasProntas()) {
            return ['rows' => [], 'total' => 0];
        }
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;
        $where = ['DATE(e.ocorrido_em) = :data'];
        $params = ['data' => $data];
        if ($alunoId > 0) {
            $where[] = 'e.aluno_id = :aluno_id';
            $params['aluno_id'] = $alunoId;
        }
        $sqlWhere = implode(' AND ', $where);
        $total = (int) ($this->db->fetch(
            "SELECT COUNT(*) AS n FROM presenca_eventos e WHERE {$sqlWhere}",
            $params
        )['n'] ?? 0);
        $rows = $this->db->fetchAll(
            "SELECT e.*, a.nome AS aluno_nome, a.ra, t.nome AS turma_nome
             FROM presenca_eventos e
             LEFT JOIN alunos a ON a.id = e.aluno_id
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE {$sqlWhere}
             ORDER BY e.ocorrido_em DESC, e.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        ) ?: [];
        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Alunos da turma com aula no dia e sem nenhum evento de entrada.
     *
     * @return list<int>
     */
    public function alunosSemEntradaNoDia(int $turmaId, string $data): array
    {
        if ($turmaId <= 0 || !$this->tabelasProntas()) {
            return [];
        }
        $semEntrada = "NOT EXISTS (
                   SELECT 1 FROM presenca_eventos e
                   WHERE e.aluno_id = a.id
                     AND e.tipo = 'entrada'
                     AND DATE(e.ocorrido_em) = :data
               )";
        if ($this->db->tableExists('matricula')) {
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT a.id
                 FROM alunos a
                 LEFT JOIN matricula m ON m.aluno_id = a.id
                   AND m.turma_id = :turma_id
                   AND m.data_entrada <= :data_mat
                   AND (m.data_saida IS NULL OR m.data_saida >= :data_mat2)
                 WHERE a.ativo = 1
                   AND (a.turma_id = :turma_id2 OR m.id IS NOT NULL)
                   AND {$semEntrada}",
                [
                    'turma_id' => $turmaId,
                    'turma_id2' => $turmaId,
                    'data' => $data,
                    'data_mat' => $data,
                    'data_mat2' => $data,
                ]
            ) ?: [];
        } else {
            $rows = $this->db->fetchAll(
                "SELECT a.id
                 FROM alunos a
                 WHERE a.ativo = 1 AND a.turma_id = :turma_id
                   AND {$semEntrada}",
                ['turma_id' => $turmaId, 'data' => $data]
            ) ?: [];
        }
        $ids = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }
}
