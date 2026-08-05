<?php
/**
 * Tarefas e atribuições do projeto (Expo Colag S4).
 */

class ExpoColagTarefa
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM expo_colag_projeto_tarefas WHERE id = :id',
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function listarPorProjeto(int $projetoId): array
    {
        return $this->db->fetchAll(
            'SELECT t.*, e.titulo AS etapa_titulo, e.ordem AS etapa_ordem
             FROM expo_colag_projeto_tarefas t
             LEFT JOIN expo_colag_projeto_etapas e ON e.id = t.etapa_id
             WHERE t.projeto_id = :projeto_id
             ORDER BY COALESCE(e.ordem, 999), t.id ASC',
            ['projeto_id' => $projetoId]
        ) ?: [];
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO expo_colag_projeto_tarefas
                (projeto_id, etapa_id, titulo, descricao, tipo_entregavel, data_limite, obrigatoria, criada_por)
             VALUES
                (:projeto_id, :etapa_id, :titulo, :descricao, :tipo_entregavel, :data_limite, :obrigatoria, :criada_por)',
            [
                'projeto_id' => (int) $data['projeto_id'],
                'etapa_id' => !empty($data['etapa_id']) ? (int) $data['etapa_id'] : null,
                'titulo' => mb_substr(trim((string) $data['titulo']), 0, 255),
                'descricao' => trim((string) ($data['descricao'] ?? '')) ?: null,
                'tipo_entregavel' => $data['tipo_entregavel'] ?? 'Nenhum',
                'data_limite' => $data['data_limite'] ?? null,
                'obrigatoria' => !empty($data['obrigatoria']) ? 1 : 0,
                'criada_por' => $data['criada_por'] ?? null,
            ]
        );
    }

    public function atualizar(int $id, array $data): void
    {
        $this->db->query(
            'UPDATE expo_colag_projeto_tarefas
             SET etapa_id = :etapa_id, titulo = :titulo, descricao = :descricao,
                 tipo_entregavel = :tipo_entregavel, data_limite = :data_limite,
                 obrigatoria = :obrigatoria
             WHERE id = :id',
            [
                'id' => $id,
                'etapa_id' => !empty($data['etapa_id']) ? (int) $data['etapa_id'] : null,
                'titulo' => mb_substr(trim((string) $data['titulo']), 0, 255),
                'descricao' => trim((string) ($data['descricao'] ?? '')) ?: null,
                'tipo_entregavel' => $data['tipo_entregavel'] ?? 'Nenhum',
                'data_limite' => $data['data_limite'] ?? null,
                'obrigatoria' => !empty($data['obrigatoria']) ? 1 : 0,
            ]
        );
    }

    public function excluir(int $id): void
    {
        $this->db->query(
            'DELETE FROM expo_colag_projeto_tarefa_atribuicoes WHERE tarefa_id = :id',
            ['id' => $id]
        );
        $this->db->query(
            'DELETE FROM expo_colag_projeto_tarefas WHERE id = :id',
            ['id' => $id]
        );
    }

    public function listarAtribuicoesTarefa(int $tarefaId): array
    {
        return $this->db->fetchAll(
            'SELECT a.*, i.aluno_id, al.nome AS aluno_nome, i.status AS inscricao_status
             FROM expo_colag_projeto_tarefa_atribuicoes a
             INNER JOIN expo_colag_inscricoes i ON i.id = a.inscricao_id
             LEFT JOIN alunos al ON al.id = i.aluno_id
             WHERE a.tarefa_id = :tarefa_id
             ORDER BY al.nome ASC',
            ['tarefa_id' => $tarefaId]
        ) ?: [];
    }

    public function listarAtribuicoesProjeto(int $projetoId): array
    {
        return $this->db->fetchAll(
            'SELECT a.*, t.titulo AS tarefa_titulo, t.etapa_id, t.data_limite, t.obrigatoria,
                    t.tipo_entregavel, t.projeto_id, i.aluno_id, al.nome AS aluno_nome
             FROM expo_colag_projeto_tarefa_atribuicoes a
             INNER JOIN expo_colag_projeto_tarefas t ON t.id = a.tarefa_id
             INNER JOIN expo_colag_inscricoes i ON i.id = a.inscricao_id
             LEFT JOIN alunos al ON al.id = i.aluno_id
             WHERE t.projeto_id = :projeto_id
             ORDER BY t.id ASC, al.nome ASC',
            ['projeto_id' => $projetoId]
        ) ?: [];
    }

    public function listarMinhasAtribuicoes(int $alunoId, int $projetoId): array
    {
        return $this->db->fetchAll(
            'SELECT a.*, t.titulo AS tarefa_titulo, t.descricao AS tarefa_descricao,
                    t.etapa_id, t.data_limite, t.obrigatoria, t.tipo_entregavel,
                    e.titulo AS etapa_titulo
             FROM expo_colag_projeto_tarefa_atribuicoes a
             INNER JOIN expo_colag_projeto_tarefas t ON t.id = a.tarefa_id
             INNER JOIN expo_colag_inscricoes i ON i.id = a.inscricao_id
             LEFT JOIN expo_colag_projeto_etapas e ON e.id = t.etapa_id
             WHERE i.aluno_id = :aluno_id AND t.projeto_id = :projeto_id AND i.status = \'Aprovada\'
             ORDER BY COALESCE(e.ordem, 999), t.id ASC',
            ['aluno_id' => $alunoId, 'projeto_id' => $projetoId]
        ) ?: [];
    }

    public function findAtribuicao(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT a.*, t.projeto_id, t.titulo AS tarefa_titulo, t.tipo_entregavel, t.data_limite,
                    t.obrigatoria, i.aluno_id, i.status AS inscricao_status
             FROM expo_colag_projeto_tarefa_atribuicoes a
             INNER JOIN expo_colag_projeto_tarefas t ON t.id = a.tarefa_id
             INNER JOIN expo_colag_inscricoes i ON i.id = a.inscricao_id
             WHERE a.id = :id',
            ['id' => $id]
        );
        return $row ?: null;
    }

    /** Atribui a todos os aprovados que ainda não têm a tarefa. */
    public function atribuirAprovados(int $tarefaId, int $projetoId): int
    {
        $inscricoes = $this->db->fetchAll(
            "SELECT id FROM expo_colag_inscricoes
             WHERE projeto_id = :projeto_id AND status = 'Aprovada'",
            ['projeto_id' => $projetoId]
        ) ?: [];
        $n = 0;
        foreach ($inscricoes as $insc) {
            $exists = $this->db->fetch(
                'SELECT id FROM expo_colag_projeto_tarefa_atribuicoes
                 WHERE tarefa_id = :t AND inscricao_id = :i',
                ['t' => $tarefaId, 'i' => (int) $insc['id']]
            );
            if ($exists) {
                continue;
            }
            $this->db->insert(
                'INSERT INTO expo_colag_projeto_tarefa_atribuicoes (tarefa_id, inscricao_id, status)
                 VALUES (:t, :i, \'Pendente\')',
                ['t' => $tarefaId, 'i' => (int) $insc['id']]
            );
            $n++;
        }
        return $n;
    }

    /** @param list<int> $inscricaoIds */
    public function atribuirInscricoes(int $tarefaId, array $inscricaoIds): int
    {
        $n = 0;
        foreach ($inscricaoIds as $inscId) {
            $inscId = (int) $inscId;
            if ($inscId <= 0) {
                continue;
            }
            $exists = $this->db->fetch(
                'SELECT id FROM expo_colag_projeto_tarefa_atribuicoes
                 WHERE tarefa_id = :t AND inscricao_id = :i',
                ['t' => $tarefaId, 'i' => $inscId]
            );
            if ($exists) {
                continue;
            }
            $this->db->insert(
                'INSERT INTO expo_colag_projeto_tarefa_atribuicoes (tarefa_id, inscricao_id, status)
                 VALUES (:t, :i, \'Pendente\')',
                ['t' => $tarefaId, 'i' => $inscId]
            );
            $n++;
        }
        return $n;
    }

    public function atualizarAtribuicao(int $id, array $data): bool
    {
        $params = ['id' => $id];
        $sets = [];
        if (isset($data['status'])) {
            $sets[] = 'status = :status';
            $params['status'] = $data['status'];
        }
        if (array_key_exists('entrega_conteudo', $data)) {
            $sets[] = 'entrega_conteudo = :entrega_conteudo';
            $params['entrega_conteudo'] = $data['entrega_conteudo'];
        }
        if (array_key_exists('entrega_arquivo_url', $data)) {
            $sets[] = 'entrega_arquivo_url = :entrega_arquivo_url';
            $params['entrega_arquivo_url'] = $data['entrega_arquivo_url'];
        }
        if (!empty($data['marcar_entregue'])) {
            $sets[] = 'entregue_em = NOW()';
        }
        if (!empty($data['marcar_avaliado'])) {
            $sets[] = 'avaliado_em = NOW()';
        }
        if (array_key_exists('comentario_professor', $data)) {
            $sets[] = 'comentario_professor = :comentario_professor';
            $params['comentario_professor'] = $data['comentario_professor'];
        }
        if ($sets === []) {
            return false;
        }
        $this->db->query(
            'UPDATE expo_colag_projeto_tarefa_atribuicoes SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
        return true;
    }

    /** Marca Pendente/Em_andamento vencidas como Atrasada (chamado sob demanda). */
    public function marcarAtrasadasProjeto(int $projetoId): void
    {
        $this->db->query(
            "UPDATE expo_colag_projeto_tarefa_atribuicoes a
             INNER JOIN expo_colag_projeto_tarefas t ON t.id = a.tarefa_id
             SET a.status = 'Atrasada'
             WHERE t.projeto_id = :projeto_id
               AND t.data_limite IS NOT NULL
               AND t.data_limite < NOW()
               AND a.status IN ('Pendente','Em_andamento')",
            ['projeto_id' => $projetoId]
        );
    }

    public function contarAtrasadasProfessor(int $professorId): int
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total
             FROM expo_colag_projeto_tarefa_atribuicoes a
             INNER JOIN expo_colag_projeto_tarefas t ON t.id = a.tarefa_id
             INNER JOIN expo_colag_projetos p ON p.id = t.projeto_id
             WHERE p.professor_id = :professor_id AND a.status = 'Atrasada'",
            ['professor_id' => $professorId]
        );
        return (int) ($row['total'] ?? 0);
    }

    public function contarPendentesAvaliacaoProfessor(int $professorId): int
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total
             FROM expo_colag_projeto_tarefa_atribuicoes a
             INNER JOIN expo_colag_projeto_tarefas t ON t.id = a.tarefa_id
             INNER JOIN expo_colag_projetos p ON p.id = t.projeto_id
             WHERE p.professor_id = :professor_id AND a.status = 'Entregue'",
            ['professor_id' => $professorId]
        );
        return (int) ($row['total'] ?? 0);
    }

    /** Fecha etapa se todas as tarefas obrigatórias estiverem Concluida. */
    public function tentarFecharEtapa(?int $etapaId): void
    {
        if ($etapaId === null || $etapaId <= 0) {
            return;
        }
        $obrigatorias = $this->db->fetchAll(
            'SELECT id FROM expo_colag_projeto_tarefas
             WHERE etapa_id = :etapa_id AND obrigatoria = 1',
            ['etapa_id' => $etapaId]
        ) ?: [];
        if ($obrigatorias === []) {
            return;
        }
        foreach ($obrigatorias as $t) {
            $pendente = $this->db->fetch(
                "SELECT id FROM expo_colag_projeto_tarefa_atribuicoes
                 WHERE tarefa_id = :t AND status NOT IN ('Concluida')
                 LIMIT 1",
                ['t' => (int) $t['id']]
            );
            if ($pendente) {
                return;
            }
            // Tarefa sem atribuição ainda não fecha a etapa
            $temAtr = $this->db->fetch(
                'SELECT id FROM expo_colag_projeto_tarefa_atribuicoes WHERE tarefa_id = :t LIMIT 1',
                ['t' => (int) $t['id']]
            );
            if (!$temAtr) {
                return;
            }
        }
        $this->db->query(
            "UPDATE expo_colag_projeto_etapas SET status = 'Concluida' WHERE id = :id",
            ['id' => $etapaId]
        );
    }
}
