<?php

namespace App\Services;

require_once __DIR__ . '/AlunoTurmaResolver.php';

class SaudeAcademicaService
{
    public const TIPO_SEM_MATRICULA = 'sem_matricula_ano';
    public const TIPO_MATRICULA_DIVERGENTE = 'matricula_divergente';
    public const TIPO_SEM_CHAMADA = 'sem_chamada';
    public const TIPO_PENDING_SEM_TURMA = 'pending_sem_turma';
    public const TIPO_EXTRA_APENAS_MATRICULA = 'extra_apenas_matricula';

    /** @var list<string> */
    public const TIPOS_ALERTA = [
        self::TIPO_SEM_MATRICULA,
        self::TIPO_MATRICULA_DIVERGENTE,
        self::TIPO_SEM_CHAMADA,
        self::TIPO_PENDING_SEM_TURMA,
        self::TIPO_EXTRA_APENAS_MATRICULA,
    ];

    private $db;
    private $resolver;

    public function __construct()
    {
        $this->db = \Database::getInstance();
        $this->resolver = new AlunoTurmaResolver();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarAnosLetivo(): array
    {
        try {
            if ($this->db->fetch("SHOW TABLES LIKE 'ano_letivo'") === false) {
                return [];
            }

            return $this->db->fetchAll(
                'SELECT id, ano FROM ano_letivo WHERE ativo = 1 ORDER BY ano DESC'
            ) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function resolverAnoLetivoPadrao(): int
    {
        $anos = $this->listarAnosLetivo();

        return !empty($anos) ? (int) $anos[0]['id'] : 0;
    }

    /**
     * @return array<string, int>
     */
    public function calcularKpis(int $anoLetivoId): array
    {
        $contagens = [];
        foreach (self::TIPOS_ALERTA as $tipo) {
            $contagens[$tipo] = count($this->buscarAlertas($anoLetivoId, $tipo, 0));
        }
        $contagens['total'] = array_sum($contagens);

        return $contagens;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buscarAlertas(int $anoLetivoId, ?string $tipo = null, int $turmaId = 0): array
    {
        if ($anoLetivoId <= 0) {
            return [];
        }

        $tipos = $tipo !== null && $tipo !== '' ? [$tipo] : self::TIPOS_ALERTA;
        $linhas = [];

        foreach ($tipos as $t) {
            $chunk = match ($t) {
                self::TIPO_SEM_MATRICULA => $this->alertasSemMatriculaAno($anoLetivoId, $turmaId),
                self::TIPO_MATRICULA_DIVERGENTE => $this->alertasMatriculaDivergente($anoLetivoId, $turmaId),
                self::TIPO_SEM_CHAMADA => $this->alertasSemChamada($anoLetivoId, $turmaId),
                self::TIPO_PENDING_SEM_TURMA => $this->alertasPendingSemTurma($turmaId),
                self::TIPO_EXTRA_APENAS_MATRICULA => $this->alertasExtraApenasMatricula($anoLetivoId, $turmaId),
                default => [],
            };
            foreach ($chunk as $row) {
                $row['tipo_alerta'] = $t;
                $linhas[] = $row;
            }
        }

        usort($linhas, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? ''));

            return $cmp !== 0 ? $cmp : strcmp((string) ($a['tipo_alerta'] ?? ''), (string) ($b['tipo_alerta'] ?? ''));
        });

        return $linhas;
    }

    public function rotuloTipoAlerta(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_SEM_MATRICULA => 'Sem matrícula no ano',
            self::TIPO_MATRICULA_DIVERGENTE => 'Matrícula ≠ turma principal',
            self::TIPO_SEM_CHAMADA => 'Sem nº na lista de chamada',
            self::TIPO_PENDING_SEM_TURMA => 'Pendente sem turma',
            self::TIPO_EXTRA_APENAS_MATRICULA => 'Curso extra (informativo)',
            default => $tipo,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function alertasSemMatriculaAno(int $anoLetivoId, int $turmaId): array
    {
        if (!$this->resolver->supportsMatricula()) {
            return [];
        }

        $sql = "SELECT a.id AS aluno_id, a.nome, a.ra, a.turma_id, t.nome AS turma_nome,
                       'Ativo sem matrícula formal no ano letivo selecionado.' AS descricao
                FROM alunos a
                INNER JOIN turmas t ON t.id = a.turma_id
                WHERE a.ativo = 1
                  AND a.turma_id IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM matricula m
                      WHERE m.aluno_id = a.id
                        AND m.ano_letivo_id = :ano
                        AND m.status = 'ativa'
                        AND m.data_saida IS NULL
                  )";
        $params = ['ano' => $anoLetivoId];
        if ($turmaId > 0) {
            $sql .= ' AND a.turma_id = :turma_id';
            $params['turma_id'] = $turmaId;
        }
        $sql .= ' ORDER BY a.nome ASC LIMIT 500';

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function alertasMatriculaDivergente(int $anoLetivoId, int $turmaId): array
    {
        if (!$this->resolver->supportsMatricula()) {
            return [];
        }

        $sql = "SELECT DISTINCT a.id AS aluno_id, a.nome, a.ra, a.turma_id,
                       tp.nome AS turma_nome,
                       tm.nome AS turma_matricula_nome,
                       'Matrícula ativa em turma diferente da turma principal do cadastro.' AS descricao
                FROM alunos a
                INNER JOIN matricula m ON m.aluno_id = a.id
                    AND m.status = 'ativa'
                    AND m.data_saida IS NULL
                    AND m.ano_letivo_id = :ano
                    AND m.turma_id != a.turma_id
                INNER JOIN turmas tm ON tm.id = m.turma_id
                LEFT JOIN curso c ON c.id = tm.curso_novo_id
                LEFT JOIN turmas tp ON tp.id = a.turma_id
                WHERE a.ativo = 1
                  AND a.turma_id IS NOT NULL
                  AND COALESCE(c.tipo, 'regular') != 'extra'";
        $params = ['ano' => $anoLetivoId];
        if ($turmaId > 0) {
            $sql .= ' AND (a.turma_id = :turma_id OR m.turma_id = :turma_id2)';
            $params['turma_id'] = $turmaId;
            $params['turma_id2'] = $turmaId;
        }
        $sql .= ' ORDER BY a.nome ASC LIMIT 500';

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function alertasSemChamada(int $anoLetivoId, int $turmaId): array
    {
        if (!$this->resolver->supportsListaChamada()) {
            return [];
        }

        $sql = "SELECT a.id AS aluno_id, a.nome, a.ra, a.turma_id, t.nome AS turma_nome,
                       'Aluno ativo com turma principal, mas sem número na lista de chamada deste ano.' AS descricao
                FROM alunos a
                INNER JOIN turmas t ON t.id = a.turma_id
                WHERE a.ativo = 1
                  AND a.turma_id IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM alunos_turma_chamada c
                      WHERE c.aluno_id = a.id
                        AND c.turma_id = a.turma_id
                        AND c.ano_letivo_id = :ano
                  )";
        $params = ['ano' => $anoLetivoId];
        if ($turmaId > 0) {
            $sql .= ' AND a.turma_id = :turma_id';
            $params['turma_id'] = $turmaId;
        }
        $sql .= ' ORDER BY a.nome ASC LIMIT 500';

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function alertasPendingSemTurma(int $turmaId): array
    {
        if (!$this->resolver->supportsStatusGovernanca()) {
            return [];
        }
        if ($turmaId > 0) {
            return [];
        }

        return $this->db->fetchAll(
            "SELECT a.id AS aluno_id, a.nome, a.ra, a.turma_id, NULL AS turma_nome,
                    'Cadastro com status PENDING e sem turma principal definida.' AS descricao
             FROM alunos a
             WHERE a.status = 'PENDING'
               AND (a.turma_id IS NULL OR a.turma_id = 0)
             ORDER BY a.nome ASC
             LIMIT 500"
        ) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function alertasExtraApenasMatricula(int $anoLetivoId, int $turmaId): array
    {
        if (!$this->resolver->supportsMatricula()) {
            return [];
        }

        try {
            if ($this->db->fetch("SHOW COLUMNS FROM turmas LIKE 'curso_novo_id'") === false) {
                return [];
            }
        } catch (\Exception $e) {
            return [];
        }

        $sql = "SELECT DISTINCT a.id AS aluno_id, a.nome, a.ra, a.turma_id,
                       tp.nome AS turma_nome,
                       te.nome AS turma_extra_nome,
                       'Matriculado em curso extra; turma principal permanece no regular.' AS descricao
                FROM alunos a
                INNER JOIN matricula m ON m.aluno_id = a.id
                    AND m.status = 'ativa'
                    AND m.data_saida IS NULL
                    AND m.ano_letivo_id = :ano
                    AND m.turma_id != COALESCE(a.turma_id, 0)
                INNER JOIN turmas te ON te.id = m.turma_id
                INNER JOIN curso ce ON ce.id = te.curso_novo_id AND ce.tipo = 'extra'
                LEFT JOIN turmas tp ON tp.id = a.turma_id
                WHERE a.ativo = 1";
        $params = ['ano' => $anoLetivoId];
        if ($turmaId > 0) {
            $sql .= ' AND (a.turma_id = :turma_id OR m.turma_id = :turma_id2)';
            $params['turma_id'] = $turmaId;
            $params['turma_id2'] = $turmaId;
        }
        $sql .= ' ORDER BY a.nome ASC LIMIT 500';

        return $this->db->fetchAll($sql, $params) ?: [];
    }
}
