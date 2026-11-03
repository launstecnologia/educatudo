<?php

namespace App\Modulos\Carteirinha\Models;

class Carteirinha
{
    private $db;
    /** @var bool|null */
    private $temNomeSocial = null;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function tabelasProntas(): bool
    {
        return $this->db->tableExists('carteirinha_config')
            && $this->db->tableExists('carteirinhas');
    }

    public function habilitada(): bool
    {
        if (!$this->db->tableExists('carteirinha_config')) {
            return false;
        }
        $row = $this->db->fetch('SELECT habilitada FROM carteirinha_config WHERE id = 1 LIMIT 1');
        return (int) ($row['habilitada'] ?? 0) === 1;
    }

    public function definirHabilitada(bool $ligada): void
    {
        if (!$this->tabelasProntas()) {
            throw new \RuntimeException('Rode a migration 2026_11_03_carteirinha.sql no Master.');
        }
        $this->db->query(
            'INSERT INTO carteirinha_config (id, habilitada) VALUES (1, :habilitada)
             ON DUPLICATE KEY UPDATE habilitada = VALUES(habilitada)',
            ['habilitada' => $ligada ? 1 : 0]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function turmas(): array
    {
        return $this->db->fetchAll(
            'SELECT id, nome, serie, ano_letivo
             FROM turmas
             WHERE ativo = 1
             ORDER BY ano_letivo DESC, nome ASC'
        ) ?: [];
    }

    /**
     * @return array{id:int,ano:int,data_fim:?string}|null
     */
    public function anoLetivoAtivo(): ?array
    {
        if (!$this->db->tableExists('ano_letivo')) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT id, ano, data_fim FROM ano_letivo WHERE ativo = 1 ORDER BY ano DESC LIMIT 1'
        );
        if (!$row) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'ano' => (int) $row['ano'],
            'data_fim' => $row['data_fim'] !== null ? (string) $row['data_fim'] : null,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buscarAluno(int $alunoId): ?array
    {
        if ($alunoId <= 0) {
            return null;
        }
        $nomeSocial = $this->colunaNomeSocial();
        $row = $this->db->fetch(
            "SELECT a.id, a.nome, {$nomeSocial}, a.ra, a.codigo_aluno, a.foto_url, a.ativo, a.turma_id,
                    t.nome AS turma_nome, t.serie AS turma_serie
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = :id
             LIMIT 1",
            ['id' => $alunoId]
        );
        return $row ?: null;
    }

    /**
     * @return list<int>
     */
    public function idsAtivosDaTurma(int $turmaId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id FROM alunos WHERE turma_id = :turma_id AND ativo = 1 ORDER BY nome ASC',
            ['turma_id' => $turmaId]
        ) ?: [];
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    public function turmaExiste(int $turmaId): bool
    {
        if ($turmaId <= 0) {
            return false;
        }
        $row = $this->db->fetch('SELECT id FROM turmas WHERE id = :id LIMIT 1', ['id' => $turmaId]);
        return (bool) $row;
    }

    /**
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public function listarAlunos(int $turmaId, string $busca, int $pagina, int $porPagina): array
    {
        $porPagina = max(1, min(50, $porPagina));
        $pagina = max(1, $pagina);
        $offset = ($pagina - 1) * $porPagina;
        [$where, $params] = $this->filtroAlunos($turmaId, $busca);
        $totalRow = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM alunos a WHERE {$where}",
            $params
        );
        $total = (int) ($totalRow['total'] ?? 0);
        $nomeSocial = $this->colunaNomeSocial();
        $rows = $this->db->fetchAll(
            "SELECT a.id, a.nome, {$nomeSocial}, a.ra, a.codigo_aluno, a.foto_url, a.ativo,
                    t.nome AS turma_nome, t.serie AS turma_serie,
                    c.id AS carteirinha_id, c.token, c.valida_ate, c.emitida_em, c.versao, c.status,
                    c.nome_exibicao, c.ra AS ra_cartao, c.turma_nome AS turma_cartao, c.foto_url AS foto_cartao
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             LEFT JOIN carteirinhas c ON c.id = (
                SELECT MAX(c2.id) FROM carteirinhas c2
                WHERE c2.aluno_id = a.id AND c2.status = 'emitida'
             )
             WHERE {$where}
             ORDER BY a.nome ASC
             LIMIT {$offset}, {$porPagina}",
            $params
        ) ?: [];
        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function vigenteDoAluno(int $alunoId): ?array
    {
        if ($alunoId <= 0 || !$this->db->tableExists('carteirinhas')) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT c.*, a.ativo AS aluno_ativo
             FROM carteirinhas c
             INNER JOIN alunos a ON a.id = c.aluno_id
             WHERE c.aluno_id = :aluno_id AND c.status = 'emitida'
             ORDER BY c.id DESC
             LIMIT 1",
            ['aluno_id' => $alunoId]
        );
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        $token = strtolower(trim($token));
        if ($token === '' || !$this->db->tableExists('carteirinhas')) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT c.*, a.ativo AS aluno_ativo
             FROM carteirinhas c
             INNER JOIN alunos a ON a.id = c.aluno_id
             WHERE c.token = :token
             LIMIT 1",
            ['token' => $token]
        );
        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function emitidasDaTurma(int $turmaId): array
    {
        return $this->db->fetchAll(
            "SELECT c.*, a.ativo AS aluno_ativo
             FROM carteirinhas c
             INNER JOIN alunos a ON a.id = c.aluno_id
             WHERE a.turma_id = :turma_id AND a.ativo = 1 AND c.status = 'emitida'
             ORDER BY c.nome_exibicao ASC",
            ['turma_id' => $turmaId]
        ) ?: [];
    }

    public function proximaVersao(int $alunoId): int
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(MAX(versao), 0) AS versao FROM carteirinhas WHERE aluno_id = :aluno_id',
            ['aluno_id' => $alunoId]
        );
        return (int) ($row['versao'] ?? 0) + 1;
    }

    public function cancelarVigentes(int $alunoId, string $motivo): void
    {
        $this->db->query(
            "UPDATE carteirinhas
             SET status = 'cancelada', cancelada_em = NOW(), motivo_cancelamento = :motivo
             WHERE aluno_id = :aluno_id AND status = 'emitida'",
            ['motivo' => $motivo, 'aluno_id' => $alunoId]
        );
    }

    public function cancelarPorId(int $id, string $motivo): bool
    {
        $row = $this->db->fetch(
            "SELECT id, aluno_id FROM carteirinhas WHERE id = :id AND status = 'emitida' LIMIT 1",
            ['id' => $id]
        );
        if (!$row) {
            return false;
        }
        $this->db->query(
            "UPDATE carteirinhas
             SET status = 'cancelada', cancelada_em = NOW(), motivo_cancelamento = :motivo
             WHERE id = :id AND status = 'emitida'",
            ['motivo' => $motivo, 'id' => $id]
        );
        return true;
    }

    /**
     * @param array<string,mixed> $dados
     */
    public function inserir(array $dados): int
    {
        return (int) $this->db->insert(
            'INSERT INTO carteirinhas
                (aluno_id, ano_letivo_id, token, status, nome_exibicao, ra, turma_nome, foto_url,
                 valida_ate, emitida_por, versao)
             VALUES
                (:aluno_id, :ano_letivo_id, :token, \'emitida\', :nome_exibicao, :ra, :turma_nome, :foto_url,
                 :valida_ate, :emitida_por, :versao)',
            [
                'aluno_id' => (int) $dados['aluno_id'],
                'ano_letivo_id' => $dados['ano_letivo_id'],
                'token' => (string) $dados['token'],
                'nome_exibicao' => (string) $dados['nome_exibicao'],
                'ra' => $dados['ra'],
                'turma_nome' => $dados['turma_nome'],
                'foto_url' => $dados['foto_url'],
                'valida_ate' => (string) $dados['valida_ate'],
                'emitida_por' => $dados['emitida_por'],
                'versao' => (int) $dados['versao'],
            ]
        );
    }

    public function begin(): void
    {
        $this->db->beginTransaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollback(): void
    {
        $this->db->rollback();
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function filtroAlunos(int $turmaId, string $busca): array
    {
        $where = 'a.ativo = 1';
        $params = [];
        if ($turmaId > 0) {
            $where .= ' AND a.turma_id = :turma_id';
            $params['turma_id'] = $turmaId;
        }
        if ($busca !== '') {
            $where .= ' AND (a.nome LIKE :busca_nome OR a.ra LIKE :busca_ra OR a.codigo_aluno LIKE :busca_codigo';
            $params['busca_nome'] = '%' . $busca . '%';
            $params['busca_ra'] = '%' . $busca . '%';
            $params['busca_codigo'] = '%' . $busca . '%';
            if ($this->temNomeSocial()) {
                $where .= ' OR a.nome_social LIKE :busca_social';
                $params['busca_social'] = '%' . $busca . '%';
            }
            $where .= ')';
        }
        return [$where, $params];
    }

    private function colunaNomeSocial(): string
    {
        return $this->temNomeSocial() ? 'a.nome_social' : 'NULL AS nome_social';
    }

    private function temNomeSocial(): bool
    {
        if ($this->temNomeSocial !== null) {
            return $this->temNomeSocial;
        }
        $row = $this->db->fetch(
            "SELECT 1 AS ok FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'alunos' AND column_name = 'nome_social'
             LIMIT 1"
        );
        $this->temNomeSocial = !empty($row['ok']);
        return $this->temNomeSocial;
    }
}
