<?php
/**
 * Projeto da Expo Colag.
 */

class ExpoColagProjeto
{
    private $db;

    /** Transições válidas da máquina de estados. */
    public const TRANSICOES = [
        'Rascunho' => ['Publicado', 'Cancelado'],
        'Publicado' => ['Inscricoes_abertas', 'Cancelado'],
        'Inscricoes_abertas' => ['Em_execucao', 'Cancelado'],
        'Em_execucao' => ['Entrega', 'Cancelado'],
        'Entrega' => ['Avaliacao', 'Cancelado'],
        'Avaliacao' => ['Concluido', 'Cancelado'],
        'Concluido' => [],
        'Cancelado' => [],
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT p.*, pr.nome AS professor_nome
             FROM expo_colag_projetos p
             LEFT JOIN professores pr ON pr.id = p.professor_id
             WHERE p.id = :id',
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function listarPorProfessor(int $professorId, ?int $edicaoId = null): array
    {
        if ($edicaoId !== null && $edicaoId > 0) {
            return $this->db->fetchAll(
                'SELECT * FROM expo_colag_projetos
                 WHERE professor_id = :professor_id AND edicao_id = :edicao_id
                 ORDER BY updated_at DESC',
                ['professor_id' => $professorId, 'edicao_id' => $edicaoId]
            ) ?: [];
        }

        return $this->db->fetchAll(
            'SELECT * FROM expo_colag_projetos
             WHERE professor_id = :professor_id
             ORDER BY updated_at DESC',
            ['professor_id' => $professorId]
        ) ?: [];
    }

    public function listarTodos(array $filtros = []): array
    {
        $where = [];
        $params = [];

        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.titulo LIKE :q OR p.subtitulo LIKE :q OR p.area LIKE :q OR pr.nome LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $status = trim((string) ($filtros['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'p.status = :status';
            $params['status'] = $status;
        }

        $professorId = (int) ($filtros['professor_id'] ?? 0);
        if ($professorId > 0) {
            $where[] = 'p.professor_id = :professor_id';
            $params['professor_id'] = $professorId;
        }

        $sql = 'SELECT p.*, pr.nome AS professor_nome
                FROM expo_colag_projetos p
                LEFT JOIN professores pr ON pr.id = p.professor_id';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.updated_at DESC, p.id DESC';

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    /** Projetos visíveis no mural (não rascunho/cancelado). */
    public function listarPublicados(): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, pr.nome AS professor_nome
             FROM expo_colag_projetos p
             LEFT JOIN professores pr ON pr.id = p.professor_id
             WHERE p.ativo = 1
               AND p.status NOT IN ('Rascunho','Cancelado')
             ORDER BY p.destaque DESC, p.inscricoes_fim ASC, p.titulo ASC"
        ) ?: [];
    }

    public function contarPorStatusProfessor(int $professorId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT status, COUNT(*) AS total
             FROM expo_colag_projetos
             WHERE professor_id = :professor_id
             GROUP BY status',
            ['professor_id' => $professorId]
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['status']] = (int) $row['total'];
        }
        return $out;
    }

    public function contarPorStatusTodos(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT status, COUNT(*) AS total
             FROM expo_colag_projetos
             GROUP BY status'
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['status']] = (int) $row['total'];
        }
        return $out;
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO expo_colag_projetos
                (edicao_id, titulo, subtitulo, area, professor_id, materia_principal_id,
                 descricao, modalidade, vagas_totais, vagas_minimas, tamanho_grupo,
                 modo_ingresso, inscricoes_inicio, inscricoes_fim, data_inicio, data_fim,
                 vale_nota, tudinha_ativa, permite_solicitacao_recursos, destaque, ativo, status)
             VALUES
                (:edicao_id, :titulo, :subtitulo, :area, :professor_id, :materia_principal_id,
                 :descricao, :modalidade, :vagas_totais, :vagas_minimas, :tamanho_grupo,
                 :modo_ingresso, :inscricoes_inicio, :inscricoes_fim, :data_inicio, :data_fim,
                 :vale_nota, :tudinha_ativa, :permite_solicitacao_recursos, :destaque, :ativo, :status)',
            [
                'edicao_id' => $data['edicao_id'] ?? null,
                'titulo' => $data['titulo'],
                'subtitulo' => $data['subtitulo'] ?? null,
                'area' => $data['area'] ?? null,
                'professor_id' => (int) $data['professor_id'],
                'materia_principal_id' => $data['materia_principal_id'] ?? null,
                'descricao' => $data['descricao'] ?? null,
                'modalidade' => $data['modalidade'] ?? 'Grupo',
                'vagas_totais' => (int) ($data['vagas_totais'] ?? 5),
                'vagas_minimas' => (int) ($data['vagas_minimas'] ?? 3),
                'tamanho_grupo' => $data['tamanho_grupo'] ?? null,
                'modo_ingresso' => $data['modo_ingresso'] ?? 'Livre',
                'inscricoes_inicio' => $data['inscricoes_inicio'] ?? null,
                'inscricoes_fim' => $data['inscricoes_fim'] ?? null,
                'data_inicio' => $data['data_inicio'] ?? null,
                'data_fim' => $data['data_fim'] ?? null,
                'vale_nota' => (int) ($data['vale_nota'] ?? 0),
                'tudinha_ativa' => (int) ($data['tudinha_ativa'] ?? 0),
                'permite_solicitacao_recursos' => (int) ($data['permite_solicitacao_recursos'] ?? 1),
                'destaque' => (int) ($data['destaque'] ?? 0),
                'ativo' => (int) ($data['ativo'] ?? 1),
                'status' => $data['status'] ?? 'Rascunho',
            ]
        );
    }

    public function update(int $id, array $data): bool
    {
        $campos = [];
        $params = ['id' => $id];
        $permitidos = [
            'edicao_id', 'titulo', 'subtitulo', 'area', 'capa_url', 'materia_principal_id',
            'descricao', 'contexto_pratico', 'produto_esperado', 'conexoes_interdisciplinares',
            'pre_requisitos', 'modalidade', 'vagas_totais', 'vagas_minimas', 'tamanho_grupo',
            'modo_ingresso', 'exige_justificativa', 'lista_espera_ativa', 'publicar_em',
            'inscricoes_inicio', 'inscricoes_fim', 'data_inicio', 'data_fim', 'data_apresentacao',
            'briefing_entrega', 'formatos_aceitos', 'vale_nota', 'evento_avaliativo_id',
            'tudinha_ativa', 'educalabs_ativa', 'tudinha_contexto', 'custo_tudicoins',
            'materiais_necessarios', 'permite_solicitacao_recursos',
            'destaque', 'ativo', 'status', 'motivo_cancelamento', 'professor_id',
        ];
        foreach ($permitidos as $campo) {
            if (!array_key_exists($campo, $data)) {
                continue;
            }
            $campos[] = "`{$campo}` = :{$campo}";
            $params[$campo] = $data[$campo];
        }
        if ($campos === []) {
            return false;
        }
        return (bool) $this->db->query(
            'UPDATE expo_colag_projetos SET ' . implode(', ', $campos) . ' WHERE id = :id',
            $params
        );
    }

    public function podeTransitar(string $de, string $para): bool
    {
        $destinos = self::TRANSICOES[$de] ?? [];
        return in_array($para, $destinos, true);
    }

    public function excluir(int $id): void
    {
        $this->db->query('DELETE FROM expo_colag_projetos WHERE id = :id', ['id' => $id]);
    }
}
