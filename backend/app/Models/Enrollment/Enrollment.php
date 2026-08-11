<?php

namespace App\Models\Enrollment;

use Database;

class Enrollment
{
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function schemaReady(): bool
    {
        try {
            $row = $this->db->fetch(
                "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                ['enrollment']
            );
            return $row !== false && !empty($row);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch(
            "SELECT e.*,
                    al.ano AS ano_letivo_nome,
                    t.nome AS turma_nome, t.serie AS turma_serie,
                    al2.nome AS aluno_nome_atual
             FROM enrollment e
             LEFT JOIN ano_letivo al  ON al.id  = e.ano_letivo_id
             LEFT JOIN turmas t       ON t.id   = e.turma_id
             LEFT JOIN alunos al2     ON al2.id = e.aluno_id
             WHERE e.id = ?",
            [$id]
        );
        return $row ?: null;
    }

    public function list(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'e.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['tipo'])) {
            $where[]  = 'e.tipo = ?';
            $params[] = $filters['tipo'];
        }
        if (!empty($filters['ano_letivo_id'])) {
            $where[]  = 'e.ano_letivo_id = ?';
            $params[] = (int) $filters['ano_letivo_id'];
        }
        if (!empty($filters['turma_id'])) {
            $where[]  = 'e.turma_id = ?';
            $params[] = (int) $filters['turma_id'];
        }
        if (!empty($filters['q'])) {
            $where[]  = '(e.aluno_nome LIKE ? OR e.resp_nome LIKE ? OR e.resp_telefone LIKE ?)';
            $like     = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT e.*,
                       al.ano AS ano_letivo_nome,
                       t.nome AS turma_nome, t.serie AS turma_serie
                FROM enrollment e
                LEFT JOIN ano_letivo al ON al.id = e.ano_letivo_id
                LEFT JOIN turmas t      ON t.id  = e.turma_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY e.created_at DESC
                LIMIT " . (int) $limit . " OFFSET " . (int) $offset;

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    public function count(array $filters = []): int
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) { $where[] = 'e.status = ?'; $params[] = $filters['status']; }
        if (!empty($filters['tipo']))   { $where[] = 'e.tipo = ?';   $params[] = $filters['tipo']; }
        if (!empty($filters['ano_letivo_id'])) { $where[] = 'e.ano_letivo_id = ?'; $params[] = (int)$filters['ano_letivo_id']; }
        if (!empty($filters['q'])) {
            $where[] = '(e.aluno_nome LIKE ? OR e.resp_nome LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like; $params[] = $like;
        }

        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM enrollment e WHERE " . implode(' AND ', $where),
            $params
        );
        return (int) ($row['total'] ?? 0);
    }

    public function create(array $data): int
    {
        $this->db->insert(
            "INSERT INTO enrollment
             (tipo, status, aluno_id, ano_letivo_id, turma_id, serie_id,
              aluno_nome, aluno_cpf, aluno_data_nasc, aluno_genero, aluno_email, aluno_telefone,
              resp_nome, resp_cpf, resp_email, resp_telefone, resp_parentesco, resp_endereco,
              origem, observacoes, expira_em, criado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $data['tipo']           ?? 'nova',
                $data['status']         ?? 'rascunho',
                $data['aluno_id']       ?? null,
                $data['ano_letivo_id']  ?? null,
                $data['turma_id']       ?? null,
                $data['serie_id']       ?? null,
                $data['aluno_nome']     ?? '',
                $data['aluno_cpf']      ?? null,
                $data['aluno_data_nasc'] ?? null,
                $data['aluno_genero']   ?? null,
                $data['aluno_email']    ?? null,
                $data['aluno_telefone'] ?? null,
                $data['resp_nome']      ?? '',
                $data['resp_cpf']       ?? null,
                $data['resp_email']     ?? null,
                $data['resp_telefone']  ?? null,
                $data['resp_parentesco'] ?? null,
                $data['resp_endereco']  ?? null,
                $data['origem']         ?? 'interno',
                $data['observacoes']    ?? null,
                $data['expira_em']      ?? null,
                $data['criado_por']     ?? null,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = [
            'status','aluno_id','ano_letivo_id','turma_id','serie_id',
            'aluno_nome','aluno_cpf','aluno_data_nasc','aluno_genero','aluno_email','aluno_telefone',
            'resp_nome','resp_cpf','resp_email','resp_telefone','resp_parentesco','resp_endereco',
            'contrato_pdf_path','contrato_token','contrato_hash',
            'assinado_em','assinante_ip','assinante_nome',
            'observacoes','expira_em',
        ];
        $sets   = [];
        $params = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $sets[]   = "`$k` = ?";
                $params[] = $v;
            }
        }
        if (empty($sets)) return;
        $params[] = $id;
        $this->db->update("UPDATE enrollment SET " . implode(', ', $sets) . " WHERE id = ?", $params);
    }

    public function transition(int $id, string $newStatus, ?array $user = null, string $acao = ''): void
    {
        $current = $this->db->fetch("SELECT status FROM enrollment WHERE id = ?", [$id]);
        $this->db->update("UPDATE enrollment SET status = ? WHERE id = ?", [$newStatus, $id]);
        $this->db->insert(
            "INSERT INTO enrollment_audit (enrollment_id, status_de, status_para, acao, usuario_id, usuario_nome, ip)
             VALUES (?,?,?,?,?,?,?)",
            [
                $id,
                $current['status'] ?? null,
                $newStatus,
                $acao ?: null,
                $user['id']   ?? null,
                $user['nome'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }

    public function findByToken(string $token): ?array
    {
        $row = $this->db->fetch(
            "SELECT e.*, al.ano AS ano_letivo_nome, t.nome AS turma_nome, t.serie AS turma_serie
             FROM enrollment e
             LEFT JOIN ano_letivo al ON al.id = e.ano_letivo_id
             LEFT JOIN turmas t      ON t.id  = e.turma_id
             WHERE e.contrato_token = ?",
            [$token]
        );
        return $row ?: null;
    }

    public function countsByStatus(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT status, COUNT(*) AS total FROM enrollment GROUP BY status"
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['total'];
        }
        return $out;
    }

    public function getAuditTrail(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM enrollment_audit WHERE enrollment_id = ? ORDER BY created_at ASC",
            [$id]
        ) ?: [];
    }
}
