<?php

namespace App\Modulos\GrupoSeb\Services;

use App\Core\ContextoAluno;
use App\Core\Database;
use App\Core\LayoutHelper;
use App\Dominio\Escola\Services\GradeHorariaLeitura;
use App\Dominio\Escola\Services\SchoolCalendarService;

/**
 * Grupo Seb — helpers do portal do aluno (foto oficial, aulas do dia, calendário).
 */
class GrupoSebService
{
    /** @var Database */
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function estaAtivo(): bool
    {
        return LayoutHelper::isModuleEnabled('grupo_seb');
    }

    /**
     * Foto do cadastro admin (alunos.foto_url). Fallback null se vazia.
     */
    public function urlFotoOficial(int $alunoId): ?string
    {
        if ($alunoId <= 0) {
            return null;
        }

        $row = $this->db->fetch(
            'SELECT foto_url FROM alunos WHERE id = :id AND ativo = 1 LIMIT 1',
            ['id' => $alunoId]
        );
        if (!is_array($row)) {
            return null;
        }

        $url = trim((string) ($row['foto_url'] ?? ''));
        if ($url === '') {
            return null;
        }

        return ContextoAluno::normalizarUrlAvatar($url);
    }

    /**
     * Aulas do dia da turma (grade_horaria). dia_semana: 1=Seg … 7=Dom (igual date('N')).
     *
     * @return list<array<string,mixed>>
     */
    public function aulasDoDia(int $turmaId, ?\DateTimeInterface $quando = null): array
    {
        if ($turmaId <= 0) {
            return [];
        }

        $quando = $quando ?? new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo'));
        $diaSemana = (int) $quando->format('N');

        try {
            return (new GradeHorariaLeitura())->listar([
                'turma_id' => $turmaId,
                'dia_semana' => $diaSemana,
            ]);
        } catch (\Throwable $e) {
            error_log('GrupoSeb aulasDoDia: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Aulas de hoje do professor (grade_horaria). dia_semana: 1=Seg … 7=Dom.
     *
     * @return list<array<string,mixed>>
     */
    public function aulasDoDiaDoProfessor(int $professorId, ?\DateTimeInterface $quando = null): array
    {
        if ($professorId <= 0) {
            return [];
        }

        $quando = $quando ?? new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo'));
        $diaSemana = (int) $quando->format('N');

        try {
            return (new GradeHorariaLeitura())->listar([
                'professor_id' => $professorId,
                'dia_semana' => $diaSemana,
            ]);
        } catch (\Throwable $e) {
            error_log('GrupoSeb aulasDoDiaDoProfessor: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Média das notas das turmas e matérias em que o professor leciona.
     * Prioriza o lançamento de notas; completa com a nota das avaliações online.
     *
     * @return array{media_geral:?float,turmas:list<array{turma_id:int,turma_nome:string,materia_id:int,materia_nome:string,media:?float,alunos:int}>}
     */
    public function desempenhoTurmasProfessor(int $professorId): array
    {
        $vazio = ['media_geral' => null, 'turmas' => []];
        if ($professorId <= 0) {
            return $vazio;
        }

        try {
            $pares = $this->db->fetchAll(
                'SELECT g.turma_id, g.materia_id,
                        MAX(t.nome) AS turma_nome,
                        MAX(m.nome) AS materia_nome
                 FROM grade_horaria g
                 INNER JOIN turmas t ON t.id = g.turma_id
                 INNER JOIN materias m ON m.id = g.materia_id
                 WHERE g.professor_id = :professor_id
                 GROUP BY g.turma_id, g.materia_id
                 ORDER BY turma_nome ASC, materia_nome ASC',
                ['professor_id' => $professorId]
            ) ?: [];
        } catch (\Throwable $e) {
            error_log('GrupoSeb desempenhoTurmasProfessor: ' . $e->getMessage());
            return $vazio;
        }

        $medias = $this->mediasLancadasPorTurmaMateria($professorId);
        $online = $this->mediasProvasPorTurmaMateria($professorId);

        $turmas = [];
        $soma = 0.0;
        $peso = 0;
        foreach ($pares as $par) {
            $turmaId = (int) ($par['turma_id'] ?? 0);
            $materiaId = (int) ($par['materia_id'] ?? 0);
            if ($turmaId <= 0 || $materiaId <= 0) {
                continue;
            }
            $chave = $turmaId . ':' . $materiaId;
            $fonte = $medias[$chave] ?? $online[$chave] ?? null;
            $media = isset($fonte['media']) ? (float) $fonte['media'] : null;
            $alunos = (int) ($fonte['alunos'] ?? 0);
            $turmas[] = [
                'turma_id' => $turmaId,
                'turma_nome' => trim((string) ($par['turma_nome'] ?? '')),
                'materia_id' => $materiaId,
                'materia_nome' => trim((string) ($par['materia_nome'] ?? '')),
                'media' => $media,
                'alunos' => $alunos,
            ];
            if ($media !== null && $alunos > 0) {
                $soma += $media * $alunos;
                $peso += $alunos;
            }
        }

        return [
            'media_geral' => $peso > 0 ? round($soma / $peso, 1) : null,
            'turmas' => $turmas,
        ];
    }

    /**
     * @return array<string,array{media:float,alunos:int}>
     */
    private function mediasLancadasPorTurmaMateria(int $professorId): array
    {
        if (!$this->tabelaExiste('provas_blocos_notas_lancadas')) {
            return [];
        }

        try {
            $rows = $this->db->fetchAll(
                'SELECT n.turma_id, n.materia_id,
                        ROUND(AVG(n.nota), 1) AS media,
                        COUNT(DISTINCT n.aluno_id) AS alunos
                 FROM provas_blocos_notas_lancadas n
                 INNER JOIN provas_blocos pb ON pb.id = n.bloco_id AND pb.deleted_at IS NULL
                 WHERE n.professor_id = :professor_id
                   AND n.nota IS NOT NULL
                   AND n.turma_id IS NOT NULL
                   AND n.materia_id IS NOT NULL
                 GROUP BY n.turma_id, n.materia_id',
                ['professor_id' => $professorId]
            ) ?: [];
        } catch (\Throwable $e) {
            error_log('GrupoSeb mediasLancadas: ' . $e->getMessage());
            return [];
        }

        return $this->indexarMedias($rows);
    }

    /**
     * @return array<string,array{media:float,alunos:int}>
     */
    private function mediasProvasPorTurmaMateria(int $professorId): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT a.turma_id, p.materia_id,
                        ROUND(AVG(pr.nota), 1) AS media,
                        COUNT(DISTINCT pr.aluno_id) AS alunos
                 FROM provas p
                 INNER JOIN provas_realizacoes pr ON pr.prova_id = p.id
                   AND pr.nota IS NOT NULL
                   AND pr.status IN ('finalizado', 'finalizada')
                 INNER JOIN alunos a ON a.id = pr.aluno_id AND a.ativo = 1
                 WHERE p.professor_id = :professor_id
                   AND p.deleted_at IS NULL
                   AND (p.ativo = 1 OR p.ativo IS NULL)
                   AND a.turma_id IS NOT NULL
                   AND p.materia_id IS NOT NULL
                 GROUP BY a.turma_id, p.materia_id",
                ['professor_id' => $professorId]
            ) ?: [];
        } catch (\Throwable $e) {
            error_log('GrupoSeb mediasProvas: ' . $e->getMessage());
            return [];
        }

        return $this->indexarMedias($rows);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array{media:float,alunos:int}>
     */
    private function indexarMedias(array $rows): array
    {
        $mapa = [];
        foreach ($rows as $row) {
            $turmaId = (int) ($row['turma_id'] ?? 0);
            $materiaId = (int) ($row['materia_id'] ?? 0);
            if ($turmaId <= 0 || $materiaId <= 0 || !isset($row['media']) || $row['media'] === null) {
                continue;
            }
            $mapa[$turmaId . ':' . $materiaId] = [
                'media' => (float) $row['media'],
                'alunos' => (int) ($row['alunos'] ?? 0),
            ];
        }

        return $mapa;
    }

    private function tabelaExiste(string $tabela): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/', $tabela)) {
            return false;
        }
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = :tabela
                 LIMIT 1',
                ['tabela' => $tabela]
            );
            return !empty($row);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Dados do calendário escolar (letivo) visíveis ao aluno no mês corrente.
     *
     * @return array{
     *   ano:int,
     *   mes:int,
     *   eventos:list<array<string,mixed>>,
     *   tipoLabels:array<string,string>,
     *   tipoBg:array<string,string>,
     *   tipoText:array<string,string>,
     *   calendario_nome:string,
     *   calendario_cor:string,
     *   calendario_cor_texto:string
     * }
     */
    public function dadosCalendarioEscolar(int $turmaId, ?int $ano = null, ?int $mes = null): array
    {
        $tz = new \DateTimeZone('America/Sao_Paulo');
        $agora = new \DateTimeImmutable('now', $tz);
        $ano = $ano ?? (int) $agora->format('Y');
        $mes = $mes ?? (int) $agora->format('n');
        if ($ano < 2000 || $ano > 2100) {
            $ano = (int) $agora->format('Y');
        }
        if ($mes < 1 || $mes > 12) {
            $mes = (int) $agora->format('n');
        }

        $vazio = [
            'ano' => $ano,
            'mes' => $mes,
            'eventos' => [],
            'tipoLabels' => [],
            'tipoBg' => [],
            'tipoText' => [],
            'calendario_nome' => '',
            'calendario_cor' => '',
            'calendario_cor_texto' => '',
        ];

        try {
            $service = new SchoolCalendarService($this->db);
            $escopo = $service->escopoDaTurma($turmaId);
            $cfg = $service->resolver($ano, (int) ($escopo['serie_id'] ?? 0), (int) ($escopo['curso_id'] ?? 0));
            if (!$cfg) {
                return $vazio;
            }

            $todos = $service->eventos((int) $cfg['id']);
            $eventos = array_values(array_filter(
                $todos,
                static fn($e) => (int) ($e['visivel_aluno'] ?? 0) === 1
            ));
            $visuais = $service->visuaisTipos();

            return [
                'ano' => $ano,
                'mes' => $mes,
                'eventos' => $eventos,
                'tipoLabels' => $visuais['labels'] ?? [],
                'tipoBg' => $visuais['bg'] ?? [],
                'tipoText' => $visuais['text'] ?? [],
                'calendario_nome' => (string) ($cfg['nome'] ?? ''),
                'calendario_cor' => (string) ($cfg['cor'] ?? ''),
                'calendario_cor_texto' => (string) ($cfg['cor_texto'] ?? ''),
            ];
        } catch (\Throwable $e) {
            error_log('GrupoSeb dadosCalendarioEscolar: ' . $e->getMessage());
            return $vazio;
        }
    }
}
