<?php

namespace App\Modulos\VidaEscolar\Models;

use Database;

/**
 * Ofícios da secretaria (numeração anual, PDF no papel timbrado).
 */
class Oficio
{
    public const STATUS = [
        'rascunho' => 'Rascunho',
        'emitido' => 'Emitido',
        'cancelado' => 'Cancelado',
    ];

    private $db;

    private ?bool $schemaProntoCache = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function schemaPronto(): bool
    {
        if ($this->schemaProntoCache !== null) {
            return $this->schemaProntoCache;
        }
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'secretaria_oficios'");
            $this->schemaProntoCache = $row !== false && !empty($row);
        } catch (\Throwable $e) {
            $this->schemaProntoCache = false;
        }
        return $this->schemaProntoCache;
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0 || !$this->schemaPronto()) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT o.*, a.nome AS aluno_nome, t.nome AS turma_nome
             FROM secretaria_oficios o
             LEFT JOIN alunos a ON a.id = o.aluno_id
             LEFT JOIN turmas t ON t.id = o.turma_id
             WHERE o.id = :id
             LIMIT 1",
            ['id' => $id]
        );
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(array $filtros = [], int $limit = 10, int $offset = 0): array
    {
        if (!$this->schemaPronto()) {
            return [];
        }
        [$where, $params] = $this->montarFiltros($filtros);
        $sql = "SELECT o.*, a.nome AS aluno_nome, t.nome AS turma_nome
                FROM secretaria_oficios o
                LEFT JOIN alunos a ON a.id = o.aluno_id
                LEFT JOIN turmas t ON t.id = o.turma_id
                {$where}
                ORDER BY o.ano DESC, o.numero DESC, o.id DESC
                LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
        return $this->db->fetchAll($sql, $params) ?: [];
    }

    /**
     * @param array<string,mixed> $filtros
     */
    public function contar(array $filtros = []): int
    {
        if (!$this->schemaPronto()) {
            return 0;
        }
        [$where, $params] = $this->montarFiltros($filtros);
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM secretaria_oficios o {$where}",
            $params
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function criar(array $data): int
    {
        $id = $this->db->insert(
            "INSERT INTO secretaria_oficios
                (numero, ano, data_oficio, destinatario, cargo_destinatario, instituicao,
                 assunto, corpo, aluno_id, turma_id, status, criado_por, created_at)
             VALUES
                (:numero, :ano, :data_oficio, :destinatario, :cargo_destinatario, :instituicao,
                 :assunto, :corpo, :aluno_id, :turma_id, :status, :criado_por, NOW())",
            $data
        );
        return (int) $id;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function atualizar(int $id, array $data): void
    {
        $this->db->update(
            "UPDATE secretaria_oficios SET
                data_oficio = :data_oficio,
                destinatario = :destinatario,
                cargo_destinatario = :cargo_destinatario,
                instituicao = :instituicao,
                assunto = :assunto,
                corpo = :corpo,
                aluno_id = :aluno_id,
                turma_id = :turma_id,
                ano = :ano,
                updated_at = NOW()
             WHERE id = :id",
            array_merge($data, ['id' => $id])
        );
    }

    public function marcarEmitido(int $id, int $numero, int $ano): void
    {
        $this->db->update(
            "UPDATE secretaria_oficios
             SET numero = :numero, ano = :ano, status = 'emitido', updated_at = NOW()
             WHERE id = :id",
            ['numero' => $numero, 'ano' => $ano, 'id' => $id]
        );
    }

    public function marcarCancelado(int $id): void
    {
        $this->db->update(
            "UPDATE secretaria_oficios SET status = 'cancelado', updated_at = NOW() WHERE id = :id",
            ['id' => $id]
        );
    }

    public function proximoNumero(int $ano): int
    {
        $row = $this->db->fetch(
            "SELECT COALESCE(MAX(numero), 0) + 1 AS prox FROM secretaria_oficios WHERE ano = :ano",
            ['ano' => $ano]
        );
        return max(1, (int) ($row['prox'] ?? 1));
    }

    public function alunoPorId(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT id, nome, turma_id FROM alunos WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        return is_array($row) ? $row : null;
    }

    public function turmaExiste(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $row = $this->db->fetch('SELECT id FROM turmas WHERE id = :id LIMIT 1', ['id' => $id]);
        return is_array($row);
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    public function alunosDaTurma(int $turmaId): array
    {
        if ($turmaId <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT id, nome FROM alunos
             WHERE turma_id = :turma_id AND (ativo = 1 OR ativo IS NULL)
             ORDER BY nome ASC",
            ['turma_id' => $turmaId]
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array{id:int,nome:string,ano_letivo:?int}>
     */
    public function turmasAtivas(?int $anoLetivo = null): array
    {
        $sql = 'SELECT id, nome, ano_letivo FROM turmas WHERE ativo = 1';
        $params = [];
        if ($anoLetivo !== null && $anoLetivo > 0) {
            $sql .= ' AND ano_letivo = :ano';
            $params['ano'] = $anoLetivo;
        }
        $sql .= ' ORDER BY nome ASC';
        $rows = $this->db->fetchAll($sql, $params);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{0:string,1:array<string,mixed>}
     */
    private function montarFiltros(array $filtros): array
    {
        $where = [];
        $params = [];
        $ano = (int) ($filtros['ano'] ?? 0);
        if ($ano > 0) {
            $where[] = 'o.ano = :ano';
            $params['ano'] = $ano;
        }
        $status = trim((string) ($filtros['status'] ?? ''));
        if ($status !== '' && isset(self::STATUS[$status])) {
            $where[] = 'o.status = :status';
            $params['status'] = $status;
        }
        $alunoId = (int) ($filtros['aluno_id'] ?? 0);
        if ($alunoId > 0) {
            $where[] = 'o.aluno_id = :aluno_id';
            $params['aluno_id'] = $alunoId;
        }
        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(o.destinatario LIKE :q OR o.assunto LIKE :q OR o.instituicao LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        $sql = $where === [] ? '' : (' WHERE ' . implode(' AND ', $where));
        return [$sql, $params];
    }
}
