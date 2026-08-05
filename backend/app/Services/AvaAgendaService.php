<?php

require_once __DIR__ . '/../Core/Database.php';

/**
 * EducaTudo - AvaAgendaService
 *
 * Agenda do aluno no AVA: consolida prazos de atividades e aulas ao vivo das
 * disciplinas em que o aluno está matriculado. Não duplica calendário escolar
 * existente — é uma visão derivada dos próprios dados do AVA.
 */
class AvaAgendaService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Eventos da agenda do aluno (atividades + aulas ao vivo), ordenados por data.
     * @return list<array<string,mixed>>  cada item: tipo, titulo, disciplina_id, disciplina_nome, quando, link, status
     */
    public function studentEvents(int $alunoId): array
    {
        $disciplinaIds = $this->enrolledDisciplines($alunoId);
        if (empty($disciplinaIds)) {
            return [];
        }
        $place = implode(',', array_fill(0, count($disciplinaIds), '?'));

        $eventos = [];

        // Prazos de atividades publicadas com data de entrega.
        $atividades = $this->db->fetchAll(
            "SELECT a.id, a.titulo, a.disciplina_id, a.data_entrega AS quando, d.nome AS disciplina_nome
             FROM ava_atividades a
             INNER JOIN ava_disciplinas d ON d.id = a.disciplina_id
             WHERE a.disciplina_id IN ($place) AND a.status = 'publicada' AND a.data_entrega IS NOT NULL",
            $disciplinaIds
        ) ?: [];
        foreach ($atividades as $a) {
            $eventos[] = [
                'tipo' => 'atividade',
                'titulo' => (string) $a['titulo'],
                'disciplina_id' => (int) $a['disciplina_id'],
                'disciplina_nome' => (string) $a['disciplina_nome'],
                'quando' => (string) $a['quando'],
                'link' => '/cursos/atividade/' . (int) $a['id'],
                'status' => '',
            ];
        }

        // Aulas ao vivo agendadas (não canceladas).
        $lives = $this->db->fetchAll(
            "SELECT l.id, l.titulo, l.disciplina_id, l.inicio_em AS quando, l.status, d.nome AS disciplina_nome
             FROM ava_aulas_ao_vivo l
             INNER JOIN ava_disciplinas d ON d.id = l.disciplina_id
             WHERE l.disciplina_id IN ($place) AND l.inicio_em IS NOT NULL AND l.status <> 'cancelada'",
            $disciplinaIds
        ) ?: [];
        foreach ($lives as $l) {
            $eventos[] = [
                'tipo' => 'ao_vivo',
                'titulo' => (string) $l['titulo'],
                'disciplina_id' => (int) $l['disciplina_id'],
                'disciplina_nome' => (string) $l['disciplina_nome'],
                'quando' => (string) $l['quando'],
                'link' => '/cursos/ao-vivo/' . (int) $l['id'],
                'status' => (string) $l['status'],
            ];
        }

        usort($eventos, static function ($x, $y) {
            return strtotime($x['quando']) <=> strtotime($y['quando']);
        });

        return $eventos;
    }

    /**
     * Separa os eventos em próximos (>= agora) e passados (mais recentes primeiro).
     * @param list<array<string,mixed>> $eventos
     * @return array{proximos:list<array<string,mixed>>,passados:list<array<string,mixed>>}
     */
    public function split(array $eventos): array
    {
        $agora = time();
        $proximos = [];
        $passados = [];
        foreach ($eventos as $e) {
            if (strtotime((string) $e['quando']) >= $agora) {
                $proximos[] = $e;
            } else {
                $passados[] = $e;
            }
        }
        $passados = array_reverse($passados);
        return ['proximos' => $proximos, 'passados' => $passados];
    }

    /** @return list<int> */
    private function enrolledDisciplines(int $alunoId): array
    {
        if ($alunoId <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT disciplina_id FROM ava_matriculas_disciplina
             WHERE aluno_id = :a AND status IN ('ativa','concluida')",
            ['a' => $alunoId]
        ) ?: [];
        return array_values(array_map(static fn($r) => (int) $r['disciplina_id'], $rows));
    }
}
