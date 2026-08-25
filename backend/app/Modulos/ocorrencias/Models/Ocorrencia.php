<?php

namespace App\Modulos\Ocorrencias\Models;

use Database;

/**
 * EducaTudo - Modelo de Ocorrência do aluno
 * Registro central da vida escolar. Não altera nota nem frequência.
 */
class Ocorrencia
{
    public const STATUS = [
        'aberta' => 'Aberta',
        'em_acompanhamento' => 'Em acompanhamento',
        'encerrada' => 'Encerrada',
    ];

    public const GRAVIDADES = [
        'leve' => 'Leve',
        'moderado' => 'Moderado',
        'grave' => 'Grave',
    ];

    private $db;

    /** @var array<string,bool> */
    private array $colunaCache = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function schemaEstendido(): bool
    {
        return $this->colunaExiste('status') && $this->colunaExiste('categoria_id');
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $sql = $this->selectBase()
            . " WHERE o.id = :id GROUP BY o.id LIMIT 1";
        $row = $this->db->fetch($sql, ['id' => $id]);

        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $filtros
     * @return list<array<string,mixed>>
     */
    public function listar(array $filtros = [], int $limit = 200, int $offset = 0): array
    {
        [$where, $params] = $this->montarFiltros($filtros);
        $sql = $this->selectBase()
            . $where
            . " GROUP BY o.id ORDER BY o.data_ocorrencia DESC, o.created_at DESC"
            . " LIMIT " . (int) $limit . " OFFSET " . (int) $offset;

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    /**
     * @param array<string,mixed> $filtros
     */
    public function contar(array $filtros = []): int
    {
        [$where, $params] = $this->montarFiltros($filtros);
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM (
                SELECT o.id
                FROM alunos_ocorrencias o
                LEFT JOIN alunos_ocorrencias_itens oi ON oi.ocorrencia_id = o.id
                LEFT JOIN alunos a ON a.id = oi.aluno_id
                LEFT JOIN alunos a2 ON a2.id = o.aluno_id
                " . $this->joinsTurma() . "
                {$where}
                GROUP BY o.id
             ) x",
            $params
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarPorAluno(int $alunoId, int $limit = 100): array
    {
        return $this->listar(['aluno_id' => $alunoId], $limit, 0);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarParaPais(int $alunoId): array
    {
        return $this->listar([
            'aluno_id' => $alunoId,
            'enviar_pais' => 1,
        ], 200, 0);
    }

    /**
     * @param list<int> $alunoIds
     */
    public function create(array $data, array $alunoIds): int
    {
        $alunoIds = array_values(array_unique(array_map('intval', $alunoIds)));
        $alunoIds = array_values(array_filter($alunoIds, static fn($id) => $id > 0));
        if ($alunoIds === []) {
            throw new InvalidArgumentException('Informe ao menos um aluno.');
        }

        $alunoPrincipal = (int) ($data['aluno_id'] ?? $alunoIds[0]);
        if (!in_array($alunoPrincipal, $alunoIds, true)) {
            $alunoPrincipal = $alunoIds[0];
        }

        $this->db->beginTransaction();
        try {
        if ($this->schemaEstendido()) {
            $id = (int) $this->db->insert(
                "INSERT INTO alunos_ocorrencias
                    (aluno_id, categoria_id, data_ocorrencia, titulo, detalhe, nivel_gravidade, status,
                     turma_id, ano_letivo_id, diario_aula_id, materia_id, local, encaminhamento,
                     retorno_em, enviar_pais, responsavel_comunicado_em, criado_por, created_at)
                 VALUES
                    (:aluno_id, :categoria_id, :data_ocorrencia, :titulo, :detalhe, :nivel_gravidade, :status,
                     :turma_id, :ano_letivo_id, :diario_aula_id, :materia_id, :local, :encaminhamento,
                     :retorno_em, :enviar_pais, :responsavel_comunicado_em, :criado_por, NOW())",
                [
                    'aluno_id' => $alunoPrincipal,
                    'categoria_id' => $data['categoria_id'] ?? null,
                    'data_ocorrencia' => $data['data_ocorrencia'],
                    'titulo' => $data['titulo'],
                    'detalhe' => $data['detalhe'],
                    'nivel_gravidade' => $data['nivel_gravidade'],
                    'status' => $data['status'] ?? 'aberta',
                    'turma_id' => $data['turma_id'] ?? null,
                    'ano_letivo_id' => $data['ano_letivo_id'] ?? null,
                    'diario_aula_id' => $data['diario_aula_id'] ?? null,
                    'materia_id' => $data['materia_id'] ?? null,
                    'local' => $data['local'] ?? null,
                    'encaminhamento' => $data['encaminhamento'] ?? null,
                    'retorno_em' => $data['retorno_em'] ?? null,
                    'enviar_pais' => (int) ($data['enviar_pais'] ?? 0),
                    'responsavel_comunicado_em' => $data['responsavel_comunicado_em'] ?? null,
                    'criado_por' => (int) $data['criado_por'],
                ]
            );
        } else {
            $id = (int) $this->db->insert(
                "INSERT INTO alunos_ocorrencias
                    (aluno_id, data_ocorrencia, titulo, detalhe, nivel_gravidade, atitude_coordenacao, retorno_em, enviar_pais, criado_por, created_at)
                 VALUES
                    (:aluno_id, :data_ocorrencia, :titulo, :detalhe, :nivel_gravidade, :atitude, :retorno_em, :enviar_pais, :criado_por, NOW())",
                [
                    'aluno_id' => $alunoPrincipal,
                    'data_ocorrencia' => $data['data_ocorrencia'],
                    'titulo' => $data['titulo'],
                    'detalhe' => $data['detalhe'],
                    'nivel_gravidade' => $data['nivel_gravidade'],
                    'atitude' => $data['atitude_coordenacao'] ?? null,
                    'retorno_em' => $data['retorno_em'] ?? null,
                    'enviar_pais' => (int) ($data['enviar_pais'] ?? 0),
                    'criado_por' => (int) $data['criado_por'],
                ]
            );
        }

        foreach ($alunoIds as $alunoId) {
            $this->db->insert(
                "INSERT INTO alunos_ocorrencias_itens (ocorrencia_id, aluno_id, created_at)
                 VALUES (:ocorrencia_id, :aluno_id, NOW())",
                ['ocorrencia_id' => $id, 'aluno_id' => $alunoId]
            );
        }

        $this->db->commit();
        return $id;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    public function atualizarStatus(int $id, string $status, int $usuarioId): void
    {
        if (!$this->colunaExiste('status')) {
            return;
        }

        $params = [
            'status' => $status,
            'id' => $id,
        ];
        $setEncerramento = '';
        if ($status === 'encerrada' && $this->colunaExiste('encerrado_em')) {
            $setEncerramento = ', encerrado_em = NOW(), encerrado_por = :encerrado_por';
            $params['encerrado_por'] = $usuarioId;
        } elseif ($this->colunaExiste('encerrado_em')) {
            $setEncerramento = ', encerrado_em = NULL, encerrado_por = NULL';
        }

        $this->db->update(
            "UPDATE alunos_ocorrencias
             SET status = :status, updated_at = NOW() {$setEncerramento}
             WHERE id = :id",
            $params
        );
    }

    public function atualizarComunicacaoPais(int $id, bool $enviarPais): void
    {
        $sql = "UPDATE alunos_ocorrencias SET enviar_pais = :enviar_pais, updated_at = NOW()";
        $params = ['enviar_pais' => $enviarPais ? 1 : 0, 'id' => $id];

        if ($this->colunaExiste('responsavel_comunicado_em')) {
            if ($enviarPais) {
                $sql .= ", responsavel_comunicado_em = COALESCE(responsavel_comunicado_em, NOW())";
            } else {
                $sql .= ", responsavel_comunicado_em = NULL";
            }
        }

        $this->db->update($sql . " WHERE id = :id", $params);
    }

    public function atualizarEncaminhamento(int $id, string $encaminhamento): void
    {
        if (!$this->colunaExiste('encaminhamento')) {
            return;
        }
        $this->db->update(
            "UPDATE alunos_ocorrencias SET encaminhamento = :encaminhamento, updated_at = NOW() WHERE id = :id",
            ['encaminhamento' => $encaminhamento !== '' ? $encaminhamento : null, 'id' => $id]
        );
    }

    public function registrarHistorico(int $ocorrenciaId, int $usuarioId, string $acao, ?string $motivo = null): void
    {
        if (!$this->tabelaExiste('ocorrencias_historico')) {
            return;
        }
        $this->db->insert(
            "INSERT INTO ocorrencias_historico (ocorrencia_id, usuario_id, acao, motivo, created_at)
             VALUES (:ocorrencia_id, :usuario_id, :acao, :motivo, NOW())",
            [
                'ocorrencia_id' => $ocorrenciaId,
                'usuario_id' => $usuarioId,
                'acao' => $acao,
                'motivo' => $motivo,
            ]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function historico(int $ocorrenciaId): array
    {
        if (!$this->tabelaExiste('ocorrencias_historico')) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT h.*, u.nome AS usuario_nome
             FROM ocorrencias_historico h
             LEFT JOIN usuarios u ON u.id = h.usuario_id
             WHERE h.ocorrencia_id = :id
             ORDER BY h.created_at DESC",
            ['id' => $ocorrenciaId]
        ) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function categorias(bool $apenasAtivas = true): array
    {
        if (!$this->tabelaExiste('ocorrencias_categorias')) {
            return [];
        }
        $sql = "SELECT * FROM ocorrencias_categorias";
        if ($apenasAtivas) {
            $sql .= " WHERE ativo = 1";
        }
        $sql .= " ORDER BY ordem ASC, nome ASC";
        return $this->db->fetchAll($sql) ?: [];
    }

    public function categoriaExiste(int $id): bool
    {
        if ($id <= 0 || !$this->tabelaExiste('ocorrencias_categorias')) {
            return false;
        }
        $row = $this->db->fetch(
            "SELECT id FROM ocorrencias_categorias WHERE id = :id AND ativo = 1",
            ['id' => $id]
        );
        return $row !== false && $row !== null;
    }

    public function criarCategoria(string $nome): int
    {
        $nome = trim($nome);
        $slug = $this->slugCategoria($nome);
        $base = $slug;
        $n = 2;
        while ($this->db->fetch("SELECT id FROM ocorrencias_categorias WHERE slug = :slug", ['slug' => $slug])) {
            $slug = $base . '-' . $n;
            $n++;
        }
        $max = $this->db->fetch("SELECT MAX(ordem) AS m FROM ocorrencias_categorias");
        return (int) $this->db->insert(
            "INSERT INTO ocorrencias_categorias (slug, nome, ordem, ativo) VALUES (:slug, :nome, :ordem, 1)",
            [
                'slug' => $slug,
                'nome' => $nome,
                'ordem' => (int) ($max['m'] ?? 0) + 1,
            ]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function buscarAlunos(string $termo, int $turmaId = 0): array
    {
        $where = [];
        $params = [];
        if ($termo !== '') {
            $where[] = "a.nome LIKE :termo";
            $params['termo'] = '%' . $termo . '%';
        }
        if ($turmaId > 0) {
            $where[] = "a.turma_id = :turma_id";
            $params['turma_id'] = $turmaId;
        }
        if ($where === []) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT a.id, a.nome, a.turma_id, t.nome AS turma_nome, t.ano_letivo
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY a.nome ASC
             LIMIT 20",
            $params
        ) ?: [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function dadosAluno(int $alunoId): ?array
    {
        if ($alunoId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT a.id, a.nome, a.turma_id, t.nome AS turma_nome, t.ano_letivo
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = :id LIMIT 1",
            ['id' => $alunoId]
        );
        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function turmasAtivas(): array
    {
        return $this->db->fetchAll(
            "SELECT id, nome, ano_letivo FROM turmas WHERE ativo = 1 ORDER BY ano_letivo DESC, nome ASC"
        ) ?: [];
    }

    private function selectBase(): string
    {
        $extra = '';
        if ($this->schemaEstendido()) {
            $extra = ", MAX(cat.nome) AS categoria_nome, MAX(cat.slug) AS categoria_slug,
                       MAX(COALESCE(t_snap.nome, t_atual.nome)) AS turma_nome,
                       MAX(m.nome) AS materia_nome";
        } else {
            $extra = ", NULL AS categoria_nome, NULL AS categoria_slug,
                       MAX(t_atual.nome) AS turma_nome, NULL AS materia_nome";
        }

        return "SELECT o.*,
                    GROUP_CONCAT(DISTINCT COALESCE(a.nome, a2.nome) ORDER BY COALESCE(a.nome, a2.nome) SEPARATOR ', ') AS alunos_nomes,
                    MAX(u.nome) AS criado_por_nome
                    {$extra}
             FROM alunos_ocorrencias o
             LEFT JOIN alunos_ocorrencias_itens oi ON oi.ocorrencia_id = o.id
             LEFT JOIN alunos a ON a.id = oi.aluno_id
             LEFT JOIN alunos a2 ON a2.id = o.aluno_id
             " . $this->joinsTurma() . "
             LEFT JOIN usuarios u ON u.id = o.criado_por";
    }

    private function joinsTurma(): string
    {
        $joins = " LEFT JOIN turmas t_atual ON t_atual.id = COALESCE(a.turma_id, a2.turma_id)";
        if ($this->schemaEstendido()) {
            $joins .= " LEFT JOIN turmas t_snap ON t_snap.id = o.turma_id";
            $joins .= " LEFT JOIN ocorrencias_categorias cat ON cat.id = o.categoria_id";
            $joins .= " LEFT JOIN materias m ON m.id = o.materia_id";
        }
        return $joins;
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{0:string,1:array<string,mixed>}
     */
    private function montarFiltros(array $filtros): array
    {
        $where = [];
        $params = [];

        $alunoId = (int) ($filtros['aluno_id'] ?? 0);
        if ($alunoId > 0) {
            $where[] = "(o.aluno_id = :aluno_id OR oi.aluno_id = :aluno_id2)";
            $params['aluno_id'] = $alunoId;
            $params['aluno_id2'] = $alunoId;
        }

        if (isset($filtros['enviar_pais'])) {
            $where[] = "o.enviar_pais = :enviar_pais";
            $params['enviar_pais'] = (int) $filtros['enviar_pais'];
        }

        $dataInicio = trim((string) ($filtros['data_inicio'] ?? ''));
        if ($dataInicio !== '') {
            $where[] = "DATE(o.data_ocorrencia) >= :data_inicio";
            $params['data_inicio'] = $dataInicio;
        }
        $dataFim = trim((string) ($filtros['data_fim'] ?? ''));
        if ($dataFim !== '') {
            $where[] = "DATE(o.data_ocorrencia) <= :data_fim";
            $params['data_fim'] = $dataFim;
        }

        if ($this->schemaEstendido()) {
            $status = trim((string) ($filtros['status'] ?? ''));
            if ($status !== '' && isset(self::STATUS[$status])) {
                $where[] = "o.status = :status";
                $params['status'] = $status;
            }
            $categoriaId = (int) ($filtros['categoria_id'] ?? 0);
            if ($categoriaId > 0) {
                $where[] = "o.categoria_id = :categoria_id";
                $params['categoria_id'] = $categoriaId;
            }
            $turmaId = (int) ($filtros['turma_id'] ?? 0);
            if ($turmaId > 0) {
                $where[] = "(o.turma_id = :turma_id OR (o.turma_id IS NULL AND COALESCE(a.turma_id, a2.turma_id) = :turma_id2))";
                $params['turma_id'] = $turmaId;
                $params['turma_id2'] = $turmaId;
            }
            $materiaId = (int) ($filtros['materia_id'] ?? 0);
            if ($materiaId > 0) {
                $where[] = "o.materia_id = :materia_id";
                $params['materia_id'] = $materiaId;
            }
        }

        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
        return [$whereSql, $params];
    }

    private function slugCategoria(string $nome): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
        $s = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', (string) ($ascii !== false ? $ascii : $nome)));
        $s = trim($s, '-');
        return $s !== '' ? substr($s, 0, 40) : ('cat-' . substr(md5($nome), 0, 8));
    }

    private function colunaExiste(string $coluna): bool
    {
        if (isset($this->colunaCache[$coluna])) {
            return $this->colunaCache[$coluna];
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos_ocorrencias' AND COLUMN_NAME = :col",
            ['col' => $coluna]
        );
        $this->colunaCache[$coluna] = ((int) ($row['c'] ?? 0)) > 0;
        return $this->colunaCache[$coluna];
    }

    private function tabelaExiste(string $tabela): bool
    {
        return $this->db->tableExists($tabela);
    }
}
