<?php

namespace App\Modulos\ConselhoClasse\Models;

use Database;

/**
 * EducaTudo - Conselho de Classe
 * Sessão colegiada. Não altera nota nem frequência originais.
 */
class ConselhoSessao
{
    public const STATUS = [
        'em_preparacao' => 'Em preparação',
        'em_andamento' => 'Em andamento',
        'finalizado' => 'Finalizado',
        'reaberto' => 'Reaberto',
    ];

    public const RESULTADOS = [
        'aprovado' => 'Aprovado',
        'aprovado_conselho' => 'Aprovado pelo Conselho',
        'recuperacao' => 'Recuperação',
        'retido' => 'Retido',
        'transferido' => 'Transferido',
        'manter' => 'Manter resultado preliminar',
    ];

    public const ENCAMINHAMENTOS = [
        'recuperacao' => 'Recuperação',
        'acompanhamento_pedagogico' => 'Acompanhamento pedagógico',
        'contato_responsavel' => 'Contato com responsável',
        'atendimento' => 'Atendimento',
        'encaminhamento' => 'Encaminhamento',
        'observacao' => 'Observação',
        'decisao_final' => 'Decisão final',
    ];

    public const CARGOS = [
        'coordenacao' => 'Coordenação',
        'direcao' => 'Direção',
        'secretaria' => 'Secretaria',
        'professor' => 'Professor',
        'outro' => 'Outro',
    ];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function schemaPronto(): bool
    {
        return $this->tabelaExiste('conselho_sessoes');
    }

    public function tabelaExiste(string $tabela): bool
    {
        static $cache = [];
        $tabela = preg_replace('/[^a-z0-9_]/i', '', $tabela) ?? '';
        if ($tabela === '') {
            return false;
        }
        $tenantKey = defined('TENANT_ID') ? ('t' . (int) TENANT_ID) : 'no_tenant';
        if (isset($cache[$tenantKey][$tabela])) {
            return $cache[$tenantKey][$tabela];
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :tabela LIMIT 1",
                ['tabela' => $tabela]
            );
            $cache[$tenantKey][$tabela] = !empty($row['ok']);
        } catch (Throwable $e) {
            $cache[$tenantKey][$tabela] = false;
        }
        return $cache[$tenantKey][$tabela];
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0 || !$this->schemaPronto()) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT cs.*, t.nome AS turma_nome, t.serie AS turma_serie, t.ano_letivo AS turma_ano
             FROM conselho_sessoes cs
             INNER JOIN turmas t ON t.id = cs.turma_id
             WHERE cs.id = :id LIMIT 1",
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function findByTurmaPeriodo(int $turmaId, int $anoLetivo, int $bimestre): ?array
    {
        if (!$this->schemaPronto()) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT * FROM conselho_sessoes
             WHERE turma_id = :turma_id AND ano_letivo = :ano_letivo AND bimestre = :bimestre
             LIMIT 1",
            [
                'turma_id' => $turmaId,
                'ano_letivo' => $anoLetivo,
                'bimestre' => $bimestre,
            ]
        );
        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarPainel(int $anoLetivo, int $bimestre, int $turmaId = 0): array
    {
        if (!$this->schemaPronto()) {
            return [];
        }
        $params = ['ano_letivo' => $anoLetivo, 'bimestre' => $bimestre];
        $whereTurma = '';
        if ($turmaId > 0) {
            $whereTurma = ' AND t.id = :turma_id';
            $params['turma_id'] = $turmaId;
        }

        return $this->db->fetchAll(
            "SELECT t.id AS turma_id, t.nome AS turma_nome, t.serie AS turma_serie, t.ano_letivo,
                    (SELECT COUNT(*) FROM alunos a WHERE a.turma_id = t.id AND a.ativo = 1) AS total_alunos,
                    cs.id AS sessao_id, cs.status, cs.data_reuniao
             FROM turmas t
             LEFT JOIN conselho_sessoes cs
               ON cs.turma_id = t.id AND cs.ano_letivo = :ano_letivo AND cs.bimestre = :bimestre
             WHERE t.ativo = 1 AND t.ano_letivo = :ano_letivo {$whereTurma}
             ORDER BY t.serie ASC, t.nome ASC",
            $params
        ) ?: [];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function criar(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO conselho_sessoes (turma_id, ano_letivo, bimestre, status, data_reuniao, pauta, criado_por)
             VALUES (:turma_id, :ano_letivo, :bimestre, 'em_preparacao', :data_reuniao, :pauta, :criado_por)",
            [
                'turma_id' => (int) $data['turma_id'],
                'ano_letivo' => (int) $data['ano_letivo'],
                'bimestre' => (int) $data['bimestre'],
                'data_reuniao' => $data['data_reuniao'] ?? null,
                'pauta' => $data['pauta'] ?? null,
                'criado_por' => (int) ($data['criado_por'] ?? 0) ?: null,
            ]
        );
    }

    public function atualizarDados(int $id, ?string $dataReuniao, ?string $pauta): void
    {
        $this->db->query(
            "UPDATE conselho_sessoes SET data_reuniao = :data_reuniao, pauta = :pauta WHERE id = :id",
            ['id' => $id, 'data_reuniao' => $dataReuniao, 'pauta' => $pauta]
        );
    }

    public function marcarAberto(int $id, int $usuarioId): void
    {
        $this->db->query(
            "UPDATE conselho_sessoes
             SET status = 'em_andamento', aberto_por = :usuario, aberto_em = CURRENT_TIMESTAMP
             WHERE id = :id",
            ['id' => $id, 'usuario' => $usuarioId]
        );
    }

    public function marcarFinalizado(int $id, int $usuarioId): void
    {
        $this->db->query(
            "UPDATE conselho_sessoes
             SET status = 'finalizado', finalizado_por = :usuario, finalizado_em = CURRENT_TIMESTAMP
             WHERE id = :id",
            ['id' => $id, 'usuario' => $usuarioId]
        );
    }

    public function marcarReaberto(int $id, int $usuarioId): void
    {
        $this->db->query(
            "UPDATE conselho_sessoes
             SET status = 'reaberto', reaberto_por = :usuario, reaberto_em = CURRENT_TIMESTAMP
             WHERE id = :id",
            ['id' => $id, 'usuario' => $usuarioId]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function alunosDaTurma(int $turmaId): array
    {
        $atuais = $this->db->fetchAll(
            "SELECT a.id, a.nome, a.ra, a.ativo, 0 AS transferido
             FROM alunos a
             WHERE a.turma_id = :turma_id AND a.ativo = 1
             ORDER BY a.nome ASC",
            ['turma_id' => $turmaId]
        ) ?: [];

        $saidos = [];
        if ($this->tabelaExiste('alunos_turmas_historico')) {
            $saidos = $this->db->fetchAll(
                "SELECT a.id, a.nome, a.ra, a.ativo, 1 AS transferido
                 FROM alunos_turmas_historico h
                 INNER JOIN alunos a ON a.id = h.aluno_id
                 WHERE h.turma_id = :turma_id AND h.data_fim IS NOT NULL
                   AND (a.turma_id IS NULL OR a.turma_id <> :turma_id2)
                 ORDER BY a.nome ASC",
                ['turma_id' => $turmaId, 'turma_id2' => $turmaId]
            ) ?: [];
        }

        $vistos = [];
        $out = [];
        foreach (array_merge($atuais, $saidos) as $aluno) {
            $id = (int) $aluno['id'];
            if (isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;
            $out[] = $aluno;
        }
        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function professoresDaTurma(int $turmaId): array
    {
        if (!$this->tabelaExiste('grade_horaria')) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT DISTINCT p.id, p.nome, m.nome AS materia_nome, m.id AS materia_id
             FROM grade_horaria g
             INNER JOIN professores p ON p.id = g.professor_id
             LEFT JOIN materias m ON m.id = g.materia_id
             WHERE g.turma_id = :turma_id AND p.ativo = 1
             ORDER BY p.nome ASC, m.nome ASC",
            ['turma_id' => $turmaId]
        ) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarParticipantes(int $sessaoId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM conselho_participantes WHERE sessao_id = :sessao_id ORDER BY nome ASC",
            ['sessao_id' => $sessaoId]
        ) ?: [];
    }

    public function substituirParticipantes(int $sessaoId, array $linhas): void
    {
        $this->db->query(
            "DELETE FROM conselho_participantes WHERE sessao_id = :sessao_id",
            ['sessao_id' => $sessaoId]
        );
        foreach ($linhas as $linha) {
            $nome = trim((string) ($linha['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $this->db->insert(
                "INSERT INTO conselho_participantes (sessao_id, professor_id, usuario_id, nome, cargo, presente)
                 VALUES (:sessao_id, :professor_id, :usuario_id, :nome, :cargo, :presente)",
                [
                    'sessao_id' => $sessaoId,
                    'professor_id' => !empty($linha['professor_id']) ? (int) $linha['professor_id'] : null,
                    'usuario_id' => !empty($linha['usuario_id']) ? (int) $linha['usuario_id'] : null,
                    'nome' => $nome,
                    'cargo' => (string) ($linha['cargo'] ?? 'professor'),
                    'presente' => !empty($linha['presente']) ? 1 : 0,
                ]
            );
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    public function inserirDeliberacao(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO conselho_deliberacoes
                (sessao_id, aluno_id, materia_id, resultado_anterior, resultado_decisao, justificativa, registrado_por)
             VALUES
                (:sessao_id, :aluno_id, :materia_id, :resultado_anterior, :resultado_decisao, :justificativa, :registrado_por)",
            [
                'sessao_id' => (int) $data['sessao_id'],
                'aluno_id' => (int) $data['aluno_id'],
                'materia_id' => !empty($data['materia_id']) ? (int) $data['materia_id'] : null,
                'resultado_anterior' => (string) $data['resultado_anterior'],
                'resultado_decisao' => (string) $data['resultado_decisao'],
                'justificativa' => (string) $data['justificativa'],
                'registrado_por' => (int) $data['registrado_por'],
            ]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarDeliberacoes(int $sessaoId, int $alunoId = 0): array
    {
        $params = ['sessao_id' => $sessaoId];
        $whereAluno = '';
        if ($alunoId > 0) {
            $whereAluno = ' AND d.aluno_id = :aluno_id';
            $params['aluno_id'] = $alunoId;
        }
        return $this->db->fetchAll(
            "SELECT d.*, a.nome AS aluno_nome, m.nome AS materia_nome
             FROM conselho_deliberacoes d
             INNER JOIN alunos a ON a.id = d.aluno_id
             LEFT JOIN materias m ON m.id = d.materia_id
             WHERE d.sessao_id = :sessao_id {$whereAluno}
             ORDER BY d.created_at DESC, d.id DESC",
            $params
        ) ?: [];
    }

    /**
     * Última deliberação geral (materia_id IS NULL) por aluno da sessão.
     *
     * @return array<int,array<string,mixed>>
     */
    public function deliberacoesVigentes(int $sessaoId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT d.*
             FROM conselho_deliberacoes d
             INNER JOIN (
                SELECT aluno_id, MAX(id) AS max_id
                FROM conselho_deliberacoes
                WHERE sessao_id = :sessao_id AND materia_id IS NULL
                GROUP BY aluno_id
             ) x ON x.max_id = d.id",
            ['sessao_id' => $sessaoId]
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['aluno_id']] = $row;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function inserirEncaminhamento(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO conselho_encaminhamentos
                (sessao_id, aluno_id, tipo, detalhe, ocorrencia_id, criado_por)
             VALUES
                (:sessao_id, :aluno_id, :tipo, :detalhe, :ocorrencia_id, :criado_por)",
            [
                'sessao_id' => (int) $data['sessao_id'],
                'aluno_id' => (int) $data['aluno_id'],
                'tipo' => (string) $data['tipo'],
                'detalhe' => (string) $data['detalhe'],
                'ocorrencia_id' => !empty($data['ocorrencia_id']) ? (int) $data['ocorrencia_id'] : null,
                'criado_por' => (int) $data['criado_por'],
            ]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarEncaminhamentos(int $sessaoId, int $alunoId = 0): array
    {
        $params = ['sessao_id' => $sessaoId];
        $whereAluno = '';
        if ($alunoId > 0) {
            $whereAluno = ' AND e.aluno_id = :aluno_id';
            $params['aluno_id'] = $alunoId;
        }
        return $this->db->fetchAll(
            "SELECT e.*, a.nome AS aluno_nome
             FROM conselho_encaminhamentos e
             INNER JOIN alunos a ON a.id = e.aluno_id
             WHERE e.sessao_id = :sessao_id {$whereAluno}
             ORDER BY e.created_at DESC, e.id DESC",
            $params
        ) ?: [];
    }

    public function getAta(int $sessaoId): ?array
    {
        $row = $this->db->fetch(
            "SELECT * FROM conselho_atas WHERE sessao_id = :sessao_id LIMIT 1",
            ['sessao_id' => $sessaoId]
        );
        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function salvarAta(int $sessaoId, array $data): void
    {
        $existente = $this->getAta($sessaoId);
        $params = [
            'sessao_id' => $sessaoId,
            'pauta' => $data['pauta'] ?? null,
            'sintese' => $data['sintese'] ?? null,
            'decisoes' => $data['decisoes'] ?? null,
            'conteudo_json' => $data['conteudo_json'] ?? null,
            'gerada_por' => (int) ($data['gerada_por'] ?? 0) ?: null,
        ];
        if ($existente) {
            $this->db->query(
                "UPDATE conselho_atas
                 SET pauta = :pauta, sintese = :sintese, decisoes = :decisoes,
                     conteudo_json = :conteudo_json, gerada_por = :gerada_por, gerada_em = CURRENT_TIMESTAMP
                 WHERE sessao_id = :sessao_id",
                $params
            );
            return;
        }
        $this->db->insert(
            "INSERT INTO conselho_atas (sessao_id, pauta, sintese, decisoes, conteudo_json, gerada_por, gerada_em)
             VALUES (:sessao_id, :pauta, :sintese, :decisoes, :conteudo_json, :gerada_por, CURRENT_TIMESTAMP)",
            $params
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarObservacoes(int $sessaoId, int $alunoId = 0): array
    {
        $params = ['sessao_id' => $sessaoId];
        $whereAluno = '';
        if ($alunoId > 0) {
            $whereAluno = ' AND o.aluno_id = :aluno_id';
            $params['aluno_id'] = $alunoId;
        }
        return $this->db->fetchAll(
            "SELECT o.*, p.nome AS professor_nome
             FROM conselho_observacoes o
             LEFT JOIN professores p ON p.id = o.professor_id
             WHERE o.sessao_id = :sessao_id {$whereAluno}
             ORDER BY o.updated_at DESC",
            $params
        ) ?: [];
    }

    public function salvarObservacao(int $sessaoId, int $alunoId, int $professorId, string $texto): void
    {
        $this->db->query(
            "INSERT INTO conselho_observacoes (sessao_id, aluno_id, professor_id, texto)
             VALUES (:sessao_id, :aluno_id, :professor_id, :texto)
             ON DUPLICATE KEY UPDATE texto = VALUES(texto), updated_at = CURRENT_TIMESTAMP",
            [
                'sessao_id' => $sessaoId,
                'aluno_id' => $alunoId,
                'professor_id' => $professorId,
                'texto' => $texto,
            ]
        );
    }

    public function professorTemTurma(int $professorId, int $turmaId): bool
    {
        if (!$this->tabelaExiste('grade_horaria') || $professorId <= 0 || $turmaId <= 0) {
            return false;
        }
        $row = $this->db->fetch(
            "SELECT 1 AS ok FROM grade_horaria
             WHERE professor_id = :professor_id AND turma_id = :turma_id LIMIT 1",
            ['professor_id' => $professorId, 'turma_id' => $turmaId]
        );
        return !empty($row['ok']);
    }

    /**
     * @return list<int>
     */
    public function turmaIdsDoProfessor(int $professorId): array
    {
        if (!$this->tabelaExiste('grade_horaria') || $professorId <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT turma_id FROM grade_horaria WHERE professor_id = :professor_id",
            ['professor_id' => $professorId]
        ) ?: [];
        return array_values(array_filter(array_map(static fn ($r) => (int) ($r['turma_id'] ?? 0), $rows)));
    }

    public function turmaPorId(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT id, nome, serie, ano_letivo, ativo FROM turmas WHERE id = :id LIMIT 1",
            ['id' => $id]
        );
        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function turmasAtivas(int $anoLetivo = 0): array
    {
        $params = [];
        $where = 'WHERE t.ativo = 1';
        if ($anoLetivo > 0) {
            $where .= ' AND t.ano_letivo = :ano_letivo';
            $params['ano_letivo'] = $anoLetivo;
        }
        return $this->db->fetchAll(
            "SELECT t.id, t.nome, t.serie, t.ano_letivo FROM turmas t {$where} ORDER BY t.ano_letivo DESC, t.serie ASC, t.nome ASC",
            $params
        ) ?: [];
    }

    /**
     * @return list<int>
     */
    public function anosLetivosTurmas(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT ano_letivo FROM turmas WHERE ativo = 1 AND ano_letivo IS NOT NULL ORDER BY ano_letivo DESC"
        ) ?: [];
        $anos = array_values(array_filter(array_map(static fn ($r) => (int) ($r['ano_letivo'] ?? 0), $rows)));
        if ($anos === []) {
            $anos[] = (int) date('Y');
        }
        return $anos;
    }

    public function contarDiariosEsperados(int $turmaId): int
    {
        if (!$this->tabelaExiste('grade_horaria')) {
            return 0;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(DISTINCT CONCAT(materia_id, '-', professor_id)) AS total
             FROM grade_horaria WHERE turma_id = :turma_id",
            ['turma_id' => $turmaId]
        );
        return (int) ($row['total'] ?? 0);
    }

    public function contarDiariosFechados(int $turmaId, int $anoLetivo, int $bimestre): int
    {
        if (!$this->tabelaExiste('diario_fechamentos')) {
            return 0;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM diario_fechamentos
             WHERE turma_id = :turma_id AND ano_letivo = :ano_letivo AND bimestre = :bimestre AND status = 'fechado'",
            [
                'turma_id' => $turmaId,
                'ano_letivo' => $anoLetivo,
                'bimestre' => $bimestre,
            ]
        );
        return (int) ($row['total'] ?? 0);
    }

    public function alunoDaTurma(int $alunoId, int $turmaId): ?array
    {
        $row = $this->db->fetch(
            "SELECT id, nome, ra, ativo, turma_id FROM alunos WHERE id = :id LIMIT 1",
            ['id' => $alunoId]
        );
        if (!$row) {
            return null;
        }
        if ((int) $row['turma_id'] === $turmaId) {
            $row['transferido'] = 0;
            return $row;
        }
        if ($this->tabelaExiste('alunos_turmas_historico')) {
            $hist = $this->db->fetch(
                "SELECT 1 AS ok FROM alunos_turmas_historico
                 WHERE aluno_id = :aluno_id AND turma_id = :turma_id LIMIT 1",
                ['aluno_id' => $alunoId, 'turma_id' => $turmaId]
            );
            if ($hist) {
                $row['transferido'] = 1;
                return $row;
            }
        }
        return null;
    }
}
