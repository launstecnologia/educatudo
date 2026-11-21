<?php

namespace App\Modulos\Jornadas\Services;

use App\Core\Database;

/**
 * Consultas e regras de acesso aos planos de aula no portal do aluno.
 *
 * Status liberado ao aluno: apenas `aprovado` (fluxo LessonPlan::approve*).
 * `rascunho`, `rejeitado` e valores desconhecidos/nulos NÃO são expostos.
 */
class PlanoAulaAlunoService
{
    /** Status que o aluno (e APIs do portal que espelham o aluno) podem ver. */
    public const STATUS_LIBERADOS_ALUNO = ['aprovado'];

    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function statusLiberadoParaAluno(?string $status): bool
    {
        return in_array((string) $status, self::STATUS_LIBERADOS_ALUNO, true);
    }

    /**
     * Fragmento SQL + params para status liberado (allowlist).
     *
     * @return array{0: string, 1: array<string, string>}
     */
    public function sqlEParamsStatusLiberado(string $alias = 'pa'): array
    {
        $marcas = [];
        $params = [];
        foreach (self::STATUS_LIBERADOS_ALUNO as $i => $status) {
            $chave = 'status_lib_' . $i;
            $marcas[] = ':' . $chave;
            $params[$chave] = $status;
        }
        $sql = $alias . '.status IN (' . implode(', ', $marcas) . ')';
        return [$sql, $params];
    }

    /**
     * Listagem: turma do aluno + não deletados + status liberado.
     * Por padrão filtra pelo ano civil de created_at (portal aluno / API pais).
     * Passe $anoAtual = false para listar sem filtro de ano (ex.: ParentAcademicReadService).
     *
     * @param int|false|null $anoAtual Ano civil, null = ano atual, false = sem filtro de ano
     * @return array<int, array<string, mixed>>
     */
    public function listarParaTurma(int $turmaId, $anoAtual = null): array
    {
        if ($turmaId <= 0) {
            return [];
        }
        [$sqlStatus, $paramsStatus] = $this->sqlEParamsStatusLiberado('pa');
        $filtroAno = '';
        $paramsAno = [];
        if ($anoAtual !== false) {
            $ano = $anoAtual === null ? (int) date('Y') : (int) $anoAtual;
            $filtroAno = 'AND YEAR(pa.created_at) = :ano_atual';
            $paramsAno['ano_atual'] = $ano;
        }
        $planos = $this->db->fetchAll(
            "SELECT pa.*,
                    p.nome as professor_nome,
                    m.nome as materia_nome,
                    t.nome as turma_nome,
                    t.serie as turma_serie,
                    YEAR(CURDATE()) as turma_ano_letivo
             FROM planos_aula pa
             LEFT JOIN professores p ON pa.professor_id = p.id
             LEFT JOIN materias m ON pa.materia_id = m.id
             LEFT JOIN turmas t ON pa.turma_id = t.id
             WHERE pa.turma_id = :turma_id
                 AND pa.deleted_at IS NULL
                 {$filtroAno}
                 AND {$sqlStatus}
             ORDER BY pa.created_at DESC",
            array_merge(
                ['turma_id' => $turmaId],
                $paramsAno,
                $paramsStatus
            )
        ) ?: [];

        foreach ($planos as &$plano) {
            $plano['data_exibicao'] = $this->formatarDataExibicao($plano);
        }
        unset($plano);

        return $planos;
    }

    /**
     * Detalhe para aluno: só não-deletado e status liberado.
     * Rascunho/rejeitado retornam null (mesmo padrão de “não encontrado”).
     *
     * @return array<string, mixed>|null
     */
    public function buscarNaoDeletado(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        [$sqlStatus, $paramsStatus] = $this->sqlEParamsStatusLiberado('pa');
        $row = $this->db->fetch(
            "SELECT pa.*,
                    p.nome as professor_nome,
                    m.nome as materia_nome,
                    t.nome as turma_nome,
                    t.serie as turma_serie
             FROM planos_aula pa
             LEFT JOIN professores p ON pa.professor_id = p.id
             LEFT JOIN materias m ON pa.materia_id = m.id
             LEFT JOIN turmas t ON pa.turma_id = t.id
             WHERE pa.id = :id
             AND pa.deleted_at IS NULL
             AND {$sqlStatus}",
            array_merge(['id' => $id], $paramsStatus)
        );
        return $row && is_array($row) ? $row : null;
    }

    /**
     * Autorização do detalhe: status liberado + (mesma turma OU mesma série + ano_letivo).
     *
     * @param array<string, mixed> $aluno
     * @param array<string, mixed> $plano
     */
    public function alunoPodeVisualizar(array $aluno, array $plano): bool
    {
        if (!$this->statusLiberadoParaAluno(isset($plano['status']) ? (string) $plano['status'] : null)) {
            return false;
        }
        $turmaAlunoId = (int) ($aluno['turma_id'] ?? 0);
        $turmaPlanoId = (int) ($plano['turma_id'] ?? 0);
        if ($turmaPlanoId > 0 && $turmaPlanoId === $turmaAlunoId) {
            return true;
        }
        if ($turmaAlunoId <= 0 || $turmaPlanoId <= 0) {
            return false;
        }

        $turmaAluno = $this->db->fetch(
            'SELECT t.* FROM turmas t WHERE t.id = :turma_id',
            ['turma_id' => $turmaAlunoId]
        );
        $turmaPlano = $this->db->fetch(
            'SELECT t.* FROM turmas t WHERE t.id = :turma_id',
            ['turma_id' => $turmaPlanoId]
        );
        if (
            !empty($turmaAluno['serie']) && !empty($turmaAluno['ano_letivo'])
            && !empty($turmaPlano['serie']) && !empty($turmaPlano['ano_letivo'])
            && $turmaAluno['serie'] == $turmaPlano['serie']
            && $turmaAluno['ano_letivo'] == $turmaPlano['ano_letivo']
        ) {
            return true;
        }
        return false;
    }

    /**
     * @param array<string, mixed> $plano
     */
    public function formatarDataExibicao(array $plano): string
    {
        if (!empty($plano['data_aula'])) {
            $dataAula = $plano['data_aula'];
            if (is_string($dataAula)) {
                $dec = json_decode($dataAula, true);
                $dataAula = is_array($dec) ? $dec : [$dataAula];
            }
            if (is_array($dataAula) && !empty($dataAula)) {
                $datas = array_filter($dataAula);
                if (!empty($datas)) {
                    $formatadas = [];
                    foreach ($datas as $d) {
                        $formatadas[] = date('d/m/Y', strtotime((string) $d));
                    }
                    return implode(', ', $formatadas);
                }
            }
            return date('d/m/Y', strtotime((string) $plano['data_aula']));
        }
        if (!empty($plano['data_inicio']) && !empty($plano['data_fim'])) {
            return date('d/m/Y', strtotime((string) $plano['data_inicio']))
                . ' - '
                . date('d/m/Y', strtotime((string) $plano['data_fim']));
        }
        if (!empty($plano['data_inicio'])) {
            return 'A partir de ' . date('d/m/Y', strtotime((string) $plano['data_inicio']));
        }
        return '';
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
            "SELECT a.*, t.nome as turma_nome, t.serie as turma_serie, YEAR(CURDATE()) as turma_ano_letivo
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             WHERE a.id = :user_id",
            ['user_id' => $alunoId]
        );
        return $aluno && is_array($aluno) ? $aluno : null;
    }
}
