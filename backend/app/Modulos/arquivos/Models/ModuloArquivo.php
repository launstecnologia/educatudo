<?php
/**
 * Model — publicações do módulo Arquivos (tabela modulos_arquivos).
 */

if (!class_exists('ModuloArquivo')) {
class ModuloArquivo
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM modulos_arquivos WHERE id = :id',
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function findByIdComNomes(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT ma.*, m.nome as materia_nome, p.nome as professor_nome
             FROM modulos_arquivos ma
             LEFT JOIN materias m ON ma.materia_id = m.id
             LEFT JOIN professores p ON ma.professor_id = p.id
             WHERE ma.id = :id',
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function findByIdDoProfessor(int $id, int $professorId): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM modulos_arquivos WHERE id = :id AND professor_id = :prof_id',
            ['id' => $id, 'prof_id' => $professorId]
        );
        return $row ?: null;
    }

    public function findByIdDoProfessorComNomes(int $id, int $professorId): ?array
    {
        $row = $this->db->fetch(
            'SELECT ma.*, m.nome as materia_nome, p.nome as professor_nome
             FROM modulos_arquivos ma
             LEFT JOIN materias m ON ma.materia_id = m.id
             LEFT JOIN professores p ON ma.professor_id = p.id
             WHERE ma.id = :id AND ma.professor_id = :prof_id',
            ['id' => $id, 'prof_id' => $professorId]
        );
        return $row ?: null;
    }

    /**
     * Publicação visível para o aluno (turma / multi-turma / aluno específico).
     */
    public function findVisivelParaAluno(int $id, int $turmaId, int $alunoId): ?array
    {
        $row = $this->db->fetch(
            'SELECT ma.*, m.nome as materia_nome, p.nome as professor_nome
             FROM modulos_arquivos ma
             LEFT JOIN materias m ON ma.materia_id = m.id
             LEFT JOIN professores p ON ma.professor_id = p.id
             WHERE ma.id = :id
               AND ((ma.aluno_id IS NULL AND (ma.turma_id = :turma_id OR EXISTS (
                    SELECT 1 FROM modulos_arquivos_turmas mat
                    WHERE mat.modulo_arquivo_id = ma.id AND mat.turma_id = :turma_id2
               ))) OR ma.aluno_id = :aluno_id)',
            [
                'id' => $id,
                'turma_id' => $turmaId,
                'turma_id2' => $turmaId,
                'aluno_id' => $alunoId,
            ]
        );
        return $row ?: null;
    }

    public function alunoPodeVer(int $moduloArquivoId, int $turmaId, int $alunoId): bool
    {
        $row = $this->db->fetch(
            'SELECT 1 FROM modulos_arquivos ma
             WHERE ma.id = :id
               AND ((ma.aluno_id IS NULL AND (ma.turma_id = :tid OR EXISTS (
                    SELECT 1 FROM modulos_arquivos_turmas mat
                    WHERE mat.modulo_arquivo_id = ma.id AND mat.turma_id = :tid2
               ))) OR ma.aluno_id = :aid)',
            [
                'id' => $moduloArquivoId,
                'tid' => $turmaId,
                'tid2' => $turmaId,
                'aid' => $alunoId,
            ]
        );
        return (bool) $row;
    }

    public function professorPodeVer(int $id, int $professorId): bool
    {
        return (bool) $this->db->fetch(
            'SELECT 1 FROM modulos_arquivos ma WHERE ma.id = :id AND ma.professor_id = :prof_id',
            ['id' => $id, 'prof_id' => $professorId]
        );
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO modulos_arquivos
                (turma_id, materia_id, professor_id, aluno_id, pasta_id, titulo, descricao, recuperacao)
             VALUES
                (:turma_id, :materia_id, :professor_id, :aluno_id, :pasta_id, :titulo, :descricao, :recuperacao)',
            [
                'turma_id' => $data['turma_id'] !== null ? (int) $data['turma_id'] : null,
                'materia_id' => !empty($data['materia_id']) ? (int) $data['materia_id'] : null,
                'professor_id' => isset($data['professor_id']) ? (int) $data['professor_id'] : null,
                'aluno_id' => $data['aluno_id'] !== null ? (int) $data['aluno_id'] : null,
                'pasta_id' => $data['pasta_id'] !== null ? (int) $data['pasta_id'] : null,
                'titulo' => (string) $data['titulo'],
                'descricao' => (string) ($data['descricao'] ?? ''),
                'recuperacao' => !empty($data['recuperacao']) ? 1 : 0,
            ]
        );
    }

    public function update(int $id, array $data): void
    {
        $this->db->query(
            'UPDATE modulos_arquivos SET
                turma_id = :turma_id,
                materia_id = :materia_id,
                aluno_id = :aluno_id,
                titulo = :titulo,
                descricao = :descricao,
                recuperacao = :recuperacao
             WHERE id = :id',
            [
                'turma_id' => $data['turma_id'] !== null ? (int) $data['turma_id'] : null,
                'materia_id' => !empty($data['materia_id']) ? (int) $data['materia_id'] : null,
                'aluno_id' => $data['aluno_id'] !== null ? (int) $data['aluno_id'] : null,
                'titulo' => (string) $data['titulo'],
                'descricao' => (string) ($data['descricao'] ?? ''),
                'recuperacao' => !empty($data['recuperacao']) ? 1 : 0,
                'id' => $id,
            ]
        );
    }

    public function updateAdmin(int $id, array $data): void
    {
        $this->db->query(
            'UPDATE modulos_arquivos SET
                titulo = :titulo,
                descricao = :descricao,
                materia_id = :materia_id,
                professor_id = :professor_id,
                turma_id = :turma_id,
                recuperacao = :recuperacao
             WHERE id = :id',
            [
                'id' => $id,
                'titulo' => (string) $data['titulo'],
                'descricao' => (string) ($data['descricao'] ?? ''),
                'materia_id' => !empty($data['materia_id']) ? (int) $data['materia_id'] : null,
                'professor_id' => !empty($data['professor_id']) ? (int) $data['professor_id'] : null,
                'turma_id' => (int) $data['turma_id'],
                'recuperacao' => !empty($data['recuperacao']) ? 1 : 0,
            ]
        );
    }

    public function listForProfessor(int $professorId, ?int $pastaId): array
    {
        $params = ['prof_id' => $professorId];
        $whereExtra = '';
        if ($pastaId !== null) {
            $whereExtra = ' AND ma.pasta_id = :pasta_id';
            $params['pasta_id'] = $pastaId;
        }

        return $this->db->fetchAll(
            "SELECT ma.*,
                    COALESCE(
                      (SELECT GROUP_CONCAT(t2.nome ORDER BY t2.nome)
                       FROM modulos_arquivos_turmas mat
                       JOIN turmas t2 ON mat.turma_id = t2.id
                       WHERE mat.modulo_arquivo_id = ma.id),
                      t.nome
                    ) AS turma_nome,
                    m.nome AS materia_nome,
                    (SELECT COUNT(*) FROM modulos_arquivos_anexos WHERE modulo_arquivo_id = ma.id) AS total_anexos,
                    (SELECT nome FROM alunos WHERE id = ma.aluno_id LIMIT 1) AS aluno_nome
             FROM modulos_arquivos ma
             LEFT JOIN turmas t ON ma.turma_id = t.id
             LEFT JOIN materias m ON ma.materia_id = m.id
             WHERE ma.professor_id = :prof_id{$whereExtra}
             ORDER BY ma.created_at DESC",
            $params
        ) ?: [];
    }

    public function listTurmaIdsWithFallback(int $moduloArquivoId): array
    {
        $ids = $this->listTurmaIds($moduloArquivoId);
        if (!empty($ids)) {
            return $ids;
        }
        $item = $this->findById($moduloArquivoId);
        if ($item && !empty($item['turma_id'])) {
            return [(int) $item['turma_id']];
        }
        return [];
    }

    /**
     * @return array{items: array, total: int}
     */
    public function listAdmin(array $filtros, int $perPage, int $page): array
    {
        $where = ['1=1'];
        $params = [];

        if (($filtros['materia_id'] ?? 0) > 0) {
            $where[] = 'ma.materia_id = :materia_id';
            $params['materia_id'] = (int) $filtros['materia_id'];
        }
        if (($filtros['professor_id'] ?? 0) > 0) {
            $where[] = 'ma.professor_id = :professor_id';
            $params['professor_id'] = (int) $filtros['professor_id'];
        }
        if (($filtros['turma_id'] ?? 0) > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM modulos_arquivos_turmas mat
                        WHERE mat.modulo_arquivo_id = ma.id AND mat.turma_id = :turma_id)';
            $params['turma_id'] = (int) $filtros['turma_id'];
        }
        if (($filtros['assunto'] ?? '') !== '') {
            $where[] = '(ma.titulo LIKE :assunto OR ma.descricao LIKE :assunto2)';
            $params['assunto'] = '%' . $filtros['assunto'] . '%';
            $params['assunto2'] = '%' . $filtros['assunto'] . '%';
        }
        if (($filtros['data_de'] ?? '') !== '') {
            $where[] = 'DATE(ma.created_at) >= :data_de';
            $params['data_de'] = $filtros['data_de'];
        }
        if (($filtros['data_ate'] ?? '') !== '') {
            $where[] = 'DATE(ma.created_at) <= :data_ate';
            $params['data_ate'] = $filtros['data_ate'];
        }
        if (array_key_exists('pasta_id', $filtros) && $filtros['pasta_id'] !== null && $filtros['pasta_id'] !== '') {
            if ($filtros['pasta_id'] === 'null' || $filtros['pasta_id'] === 0) {
                $where[] = 'ma.pasta_id IS NULL';
            } else {
                $where[] = 'ma.pasta_id = :pasta_id';
                $params['pasta_id'] = (int) $filtros['pasta_id'];
            }
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) ($this->db->fetch(
            "SELECT COUNT(*) AS c FROM modulos_arquivos ma WHERE {$whereSql}",
            $params
        )['c'] ?? 0);

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = min(max(1, $page), $totalPages);
        $offset = ($page - 1) * $perPage;

        $items = $this->db->fetchAll(
            "SELECT ma.id, ma.titulo, ma.descricao, ma.created_at,
                    ma.materia_id, ma.professor_id, ma.pasta_id, ma.recuperacao,
                    m.nome AS materia_nome, p.nome AS professor_nome,
                    aa.id AS anexo_id, aa.caminho, aa.nome_original, aa.extensao, aa.tamanho,
                    (SELECT GROUP_CONCAT(mat.turma_id ORDER BY mat.turma_id SEPARATOR ',')
                       FROM modulos_arquivos_turmas mat
                      WHERE mat.modulo_arquivo_id = ma.id) AS turma_ids_csv,
                    (SELECT GROUP_CONCAT(t.nome ORDER BY t.nome SEPARATOR ', ')
                       FROM modulos_arquivos_turmas mat
                       JOIN turmas t ON t.id = mat.turma_id
                      WHERE mat.modulo_arquivo_id = ma.id) AS turmas_nomes
             FROM modulos_arquivos ma
             LEFT JOIN materias m ON m.id = ma.materia_id
             LEFT JOIN professores p ON p.id = ma.professor_id
             LEFT JOIN modulos_arquivos_anexos aa
                    ON aa.id = (
                        SELECT a2.id FROM modulos_arquivos_anexos a2
                        WHERE a2.modulo_arquivo_id = ma.id
                        ORDER BY a2.ordem ASC, a2.id ASC
                        LIMIT 1
                    )
             WHERE {$whereSql}
             ORDER BY ma.created_at DESC, ma.id DESC
             LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset,
            $params
        ) ?: [];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    public function updatePasta(int $id, ?int $pastaId): void
    {
        $this->db->query(
            'UPDATE modulos_arquivos SET pasta_id = :pasta_id WHERE id = :id',
            ['pasta_id' => $pastaId, 'id' => $id]
        );
    }

    public function clearPasta(int $pastaId, ?int $professorId = null): void
    {
        if ($professorId !== null) {
            $this->db->query(
                'UPDATE modulos_arquivos SET pasta_id = NULL WHERE pasta_id = :id AND professor_id = :prof_id',
                ['id' => $pastaId, 'prof_id' => $professorId]
            );
            return;
        }
        $this->db->query(
            'UPDATE modulos_arquivos SET pasta_id = NULL WHERE pasta_id = :id',
            ['id' => $pastaId]
        );
    }

    public function delete(int $id): void
    {
        $this->db->query('DELETE FROM modulos_arquivos WHERE id = :id', ['id' => $id]);
    }

    public function syncTurmas(int $moduloArquivoId, array $turmaIds): void
    {
        $this->db->query(
            'DELETE FROM modulos_arquivos_turmas WHERE modulo_arquivo_id = :id',
            ['id' => $moduloArquivoId]
        );
        foreach ($turmaIds as $turmaId) {
            $turmaId = (int) $turmaId;
            if ($turmaId <= 0) {
                continue;
            }
            $this->db->insert(
                'INSERT INTO modulos_arquivos_turmas (modulo_arquivo_id, turma_id)
                 VALUES (:ma_id, :turma_id)',
                ['ma_id' => $moduloArquivoId, 'turma_id' => $turmaId]
            );
        }
    }

    public function listTurmaIds(int $moduloArquivoId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT turma_id FROM modulos_arquivos_turmas WHERE modulo_arquivo_id = :id ORDER BY turma_id',
            ['id' => $moduloArquivoId]
        );
        return array_map(static fn($r) => (int) $r['turma_id'], $rows ?: []);
    }

    public static function sqlVisibilidadeAluno(): string
    {
        return '((ma.aluno_id IS NULL AND (ma.turma_id = :turma_id OR EXISTS (
                    SELECT 1 FROM modulos_arquivos_turmas mat
                    WHERE mat.modulo_arquivo_id = ma.id AND mat.turma_id = :turma_id2
               ))) OR ma.aluno_id = :aluno_id)';
    }
}
}
