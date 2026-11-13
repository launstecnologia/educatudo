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
