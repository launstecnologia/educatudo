<?php

namespace App\Modulos\Redacoes\Services;

use App\Core\Database;

/**
 * Consultas do fluxo legado /redacoes (tabela redacoes), sem IA/créditos.
 */
class RedacaoAlunoLegadoService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarHistorico(int $alunoId): array
    {
        if ($alunoId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT r.*,
                    CASE
                        WHEN r.corrigida_em IS NOT NULL THEN 'Corrigida'
                        ELSE 'Pendente'
                    END as status
             FROM redacoes r
             WHERE r.aluno_id = :aluno_id
             ORDER BY r.created_at DESC",
            ['aluno_id' => $alunoId]
        ) ?: [];
    }

    /**
     * Últimas redações do aluno para o card do dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarRecentesParaDashboard(int $alunoId, int $limite = 5): array
    {
        if ($alunoId <= 0) {
            return [];
        }
        $limite = max(1, min(20, $limite));
        try {
            return $this->db->fetchAll(
                "SELECT r.*, t.titulo AS tema_titulo
                 FROM redacoes r
                 LEFT JOIN redacoes_temas t ON r.tema_id = t.id
                 WHERE r.aluno_id = :aluno_id
                 ORDER BY r.created_at DESC
                 LIMIT {$limite}",
                ['aluno_id' => $alunoId]
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarDoAluno(int $id, int $alunoId): ?array
    {
        if ($id <= 0 || $alunoId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT r.*, a.nome as aluno_nome
             FROM redacoes r
             LEFT JOIN alunos a ON r.aluno_id = a.id
             WHERE r.id = :id AND r.aluno_id = :aluno_id",
            ['id' => $id, 'aluno_id' => $alunoId]
        );
        return $row && is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarRascunhoDoAluno(int $id, int $alunoId): ?array
    {
        if ($id <= 0 || $alunoId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT r.* FROM redacoes r
             WHERE r.id = :id AND r.aluno_id = :aluno_id AND r.eh_rascunho = 1",
            ['id' => $id, 'aluno_id' => $alunoId]
        );
        return $row && is_array($row) ? $row : null;
    }

    public function colunaEhRascunhoExiste(): bool
    {
        try {
            $columnCheck = $this->db->fetch("SHOW COLUMNS FROM redacoes LIKE 'eh_rascunho'");
            return !empty($columnCheck);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarRascunhoParaExcluir(int $id, int $alunoId, bool $exigeEhRascunho): ?array
    {
        if ($id <= 0 || $alunoId <= 0) {
            return null;
        }
        if ($exigeEhRascunho) {
            $row = $this->db->fetch(
                'SELECT id FROM redacoes WHERE id = :id AND aluno_id = :aluno_id AND eh_rascunho = 1',
                ['id' => $id, 'aluno_id' => $alunoId]
            );
        } else {
            $row = $this->db->fetch(
                'SELECT id FROM redacoes WHERE id = :id AND aluno_id = :aluno_id',
                ['id' => $id, 'aluno_id' => $alunoId]
            );
        }
        return $row && is_array($row) ? $row : null;
    }

    public function excluirRascunho(int $id, int $alunoId, bool $exigeEhRascunho): void
    {
        if ($exigeEhRascunho) {
            $this->db->delete(
                'DELETE FROM redacoes WHERE id = :id AND aluno_id = :aluno_id AND eh_rascunho = 1',
                ['id' => $id, 'aluno_id' => $alunoId]
            );
            return;
        }
        $this->db->delete(
            'DELETE FROM redacoes WHERE id = :id AND aluno_id = :aluno_id',
            ['id' => $id, 'aluno_id' => $alunoId]
        );
    }

    public function ocultarDoAluno(int $id, int $alunoId): bool
    {
        $redacao = $this->db->fetch(
            'SELECT id FROM redacoes WHERE id = :id AND aluno_id = :aluno_id',
            ['id' => $id, 'aluno_id' => $alunoId]
        );
        if (!$redacao) {
            return false;
        }
        $this->db->update(
            'UPDATE redacoes SET oculto = 1 WHERE id = :id AND aluno_id = :aluno_id',
            ['id' => $id, 'aluno_id' => $alunoId]
        );
        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarAlunoComTurma(int $alunoId): ?array
    {
        if ($alunoId <= 0) {
            return null;
        }
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             WHERE a.id = :user_id",
            ['user_id' => $alunoId]
        );
        return $aluno && is_array($aluno) ? $aluno : null;
    }

    /**
     * Temas ativos do hub (sem IA).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarTemasDisponiveis(): array
    {
        try {
            $columns = $this->db->fetchAll('SHOW COLUMNS FROM redacoes_temas');
            $columnNames = array_column($columns ?: [], 'Field');
            $hasAtivo = in_array('ativo', $columnNames, true);
            if ($hasAtivo) {
                return $this->db->fetchAll(
                    'SELECT * FROM redacoes_temas WHERE ativo = 1 ORDER BY created_at DESC'
                ) ?: [];
            }
            return $this->db->fetchAll(
                'SELECT * FROM redacoes_temas ORDER BY created_at DESC'
            ) ?: [];
        } catch (\Throwable $e) {
            error_log('RedacaoAlunoLegadoService::listarTemasDisponiveis: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Redações do aluno no hub (exclui ocultas e rascunhos quando colunas existem).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarRedacoesHub(int $alunoId): array
    {
        if ($alunoId <= 0) {
            return [];
        }
        try {
            $columns = $this->db->fetchAll('SHOW COLUMNS FROM redacoes');
            $columnNames = array_column($columns ?: [], 'Field');
            $hasOculto = in_array('oculto', $columnNames, true);
            $hasEhRascunho = in_array('eh_rascunho', $columnNames, true);

            $sql = "SELECT r.*, t.titulo as tema_titulo, t.tipo
                    FROM redacoes r
                    LEFT JOIN redacoes_temas t ON r.tema_id = t.id
                    WHERE r.aluno_id = :aluno_id";
            if ($hasOculto) {
                $sql .= ' AND (r.oculto IS NULL OR r.oculto = 0)';
            }
            if ($hasEhRascunho) {
                $sql .= ' AND (r.eh_rascunho IS NULL OR r.eh_rascunho = 0)';
            }
            $sql .= ' ORDER BY r.created_at DESC LIMIT 50';
            return $this->db->fetchAll($sql, ['aluno_id' => $alunoId]) ?: [];
        } catch (\Throwable $e) {
            error_log('RedacaoAlunoLegadoService::listarRedacoesHub: ' . $e->getMessage());
            try {
                return $this->db->fetchAll(
                    "SELECT r.*, t.titulo as tema_titulo, t.tipo
                     FROM redacoes r
                     LEFT JOIN redacoes_temas t ON r.tema_id = t.id
                     WHERE r.aluno_id = :aluno_id
                     ORDER BY r.created_at DESC
                     LIMIT 50",
                    ['aluno_id' => $alunoId]
                ) ?: [];
            } catch (\Throwable $e2) {
                return [];
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarRascunhosHub(int $alunoId): array
    {
        if ($alunoId <= 0 || !$this->colunaEhRascunhoExiste()) {
            return [];
        }
        try {
            return $this->db->fetchAll(
                "SELECT r.*, t.titulo as tema_titulo, t.tipo
                 FROM redacoes r
                 LEFT JOIN redacoes_temas t ON r.tema_id = t.id
                 WHERE r.aluno_id = :aluno_id
                 AND r.eh_rascunho = 1
                 ORDER BY COALESCE(r.updated_at, r.created_at) DESC",
                ['aluno_id' => $alunoId]
            ) ?: [];
        } catch (\Throwable $e) {
            try {
                return $this->db->fetchAll(
                    "SELECT r.*, t.titulo as tema_titulo, t.tipo
                     FROM redacoes r
                     LEFT JOIN redacoes_temas t ON r.tema_id = t.id
                     WHERE r.aluno_id = :aluno_id
                     AND r.eh_rascunho = 1
                     ORDER BY r.created_at DESC",
                    ['aluno_id' => $alunoId]
                ) ?: [];
            } catch (\Throwable $e2) {
                return [];
            }
        }
    }

    public function colunaTempoEscritaExiste(): bool
    {
        try {
            return !empty($this->db->fetch("SHOW COLUMNS FROM redacoes LIKE 'tempo_escrita'"));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Persiste rascunho (create/update) com ownership — sem IA/créditos.
     *
     * @param array{rascunho_id?:int|string|null,titulo?:string,conteudo?:string,tema_texto?:string|null,tempo_escrita?:int} $dados
     * @return array{success:bool,rascunho_id:int,message:string}
     */
    public function salvarRascunho(int $alunoId, array $dados): array
    {
        if ($alunoId <= 0) {
            throw new \InvalidArgumentException('Aluno inválido');
        }
        if (!$this->colunaEhRascunhoExiste()) {
            throw new \RuntimeException('Coluna eh_rascunho não existe. Execute o script: php install/add_rascunho_redacao.php');
        }

        $titulo = (string) ($dados['titulo'] ?? 'Rascunho sem título');
        $conteudo = (string) ($dados['conteudo'] ?? '');
        $temaTexto = $dados['tema_texto'] ?? null;
        $tempo = isset($dados['tempo_escrita']) ? (int) $dados['tempo_escrita'] : 0;
        $rascunhoId = isset($dados['rascunho_id']) && $dados['rascunho_id'] !== '' && $dados['rascunho_id'] !== null
            ? (int) $dados['rascunho_id']
            : 0;

        if ($conteudo === '') {
            throw new \InvalidArgumentException('Conteúdo não pode estar vazio');
        }

        $temTempo = $this->colunaTempoEscritaExiste();

        if ($rascunhoId > 0) {
            $existe = $this->buscarRascunhoDoAluno($rascunhoId, $alunoId);
            if (!$existe) {
                throw new \RuntimeException('Rascunho não encontrado');
            }
            if ($temTempo) {
                $this->db->update(
                    "UPDATE redacoes
                     SET titulo = :titulo, conteudo = :conteudo, tema_texto = :tema_texto,
                         tempo_escrita = :tempo_escrita, updated_at = NOW()
                     WHERE id = :id AND aluno_id = :aluno_id AND eh_rascunho = 1",
                    [
                        'id' => $rascunhoId,
                        'aluno_id' => $alunoId,
                        'titulo' => $titulo,
                        'conteudo' => $conteudo,
                        'tema_texto' => $temaTexto,
                        'tempo_escrita' => $tempo,
                    ]
                );
            } else {
                $this->db->update(
                    "UPDATE redacoes
                     SET titulo = :titulo, conteudo = :conteudo, tema_texto = :tema_texto, updated_at = NOW()
                     WHERE id = :id AND aluno_id = :aluno_id AND eh_rascunho = 1",
                    [
                        'id' => $rascunhoId,
                        'aluno_id' => $alunoId,
                        'titulo' => $titulo,
                        'conteudo' => $conteudo,
                        'tema_texto' => $temaTexto,
                    ]
                );
            }
            return [
                'success' => true,
                'rascunho_id' => $rascunhoId,
                'message' => 'Rascunho atualizado com sucesso!',
            ];
        }

        if ($temTempo) {
            $novoId = (int) $this->db->insert(
                "INSERT INTO redacoes (aluno_id, titulo, conteudo, tema_texto, tempo_escrita, eh_rascunho, created_at, updated_at)
                 VALUES (:aluno_id, :titulo, :conteudo, :tema_texto, :tempo_escrita, 1, NOW(), NOW())",
                [
                    'aluno_id' => $alunoId,
                    'titulo' => $titulo,
                    'conteudo' => $conteudo,
                    'tema_texto' => $temaTexto,
                    'tempo_escrita' => $tempo,
                ]
            );
        } else {
            $novoId = (int) $this->db->insert(
                "INSERT INTO redacoes (aluno_id, titulo, conteudo, tema_texto, eh_rascunho, created_at, updated_at)
                 VALUES (:aluno_id, :titulo, :conteudo, :tema_texto, 1, NOW(), NOW())",
                [
                    'aluno_id' => $alunoId,
                    'titulo' => $titulo,
                    'conteudo' => $conteudo,
                    'tema_texto' => $temaTexto,
                ]
            );
        }

        return [
            'success' => true,
            'rascunho_id' => $novoId,
            'message' => 'Rascunho salvo com sucesso!',
        ];
    }
}
