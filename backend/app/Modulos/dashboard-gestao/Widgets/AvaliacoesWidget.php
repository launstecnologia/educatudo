<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardConsulta;
use App\Modulos\DashboardGestao\Services\DashboardFiltro;

require_once dirname(__DIR__, 3) . '/Models/Exams/ExamBlock.php';

class AvaliacoesWidget implements WidgetDashboard
{
    private DashboardConsulta $consulta;

    public function __construct(DashboardConsulta $consulta)
    {
        $this->consulta = $consulta;
    }

    public function chave(): string
    {
        return 'avaliacoes';
    }

    public function montar(DashboardFiltro $filtro): array
    {
        $blocos = new \ExamBlock();
        $hoje = $filtro->hoje;
        $inicioSemana = date('Y-m-d', strtotime('monday this week'));
        $fimSemana = date('Y-m-d', strtotime('sunday this week'));

        $base = [];
        if ($filtro->turmaId > 0) {
            $base['turma_id'] = $filtro->turmaId;
        }
        if ($filtro->bimestre > 0) {
            $base['bimestre'] = $filtro->bimestre;
        }

        $hojeN = $blocos->getCountFiltered(array_merge($base, ['data_prova' => $hoje]));
        $aguardando = $blocos->getCountFiltered(array_merge($base, ['status' => 'aguardando']));
        $semana = $this->contarNoIntervalo($inicioSemana, $fimSemana, $filtro);
        $proximas = $this->proximas($filtro, 5);
        $notasPendentes = $this->eventosNotasPendentes($filtro);

        return [
            'ok' => true,
            'disponivel' => true,
            'hoje' => $hojeN,
            'semana' => $semana,
            'aguardando_correcao' => $aguardando,
            'notas_pendentes' => $notasPendentes,
            'proximas' => $proximas,
            'href' => '/admin/provas',
        ];
    }

    private function contarNoIntervalo(string $inicio, string $fim, DashboardFiltro $filtro): int
    {
        $db = \Database::getInstance();
        $where = ['pb.deleted_at IS NULL', 'DATE(pb.data_prova) BETWEEN :inicio AND :fim'];
        $params = ['inicio' => $inicio, 'fim' => $fim];
        if ($filtro->bimestre > 0) {
            $where[] = 'pb.bimestre = :bimestre';
            $params['bimestre'] = $filtro->bimestre;
        }
        $turmaSql = $this->sqlTurmaEvento($filtro, $params);
        $row = $db->fetch(
            'SELECT COUNT(DISTINCT pb.id) AS n FROM provas_blocos pb WHERE ' . implode(' AND ', $where) . $turmaSql,
            $params
        );
        return (int) ($row['n'] ?? 0);
    }

    /**
     * @return list<array{titulo:string,data:string,status:string}>
     */
    private function proximas(DashboardFiltro $filtro, int $limite): array
    {
        $db = \Database::getInstance();
        $where = ['pb.deleted_at IS NULL', 'DATE(pb.data_prova) >= :hoje'];
        $params = ['hoje' => $filtro->hoje];
        if ($filtro->bimestre > 0) {
            $where[] = 'pb.bimestre = :bimestre';
            $params['bimestre'] = $filtro->bimestre;
        }
        $turmaSql = $this->sqlTurmaEvento($filtro, $params);
        $limite = max(1, min(10, $limite));
        $rows = $db->fetchAll(
            "SELECT pb.titulo, DATE(pb.data_prova) AS data_prova, pb.status
             FROM provas_blocos pb
             WHERE " . implode(' AND ', $where) . "{$turmaSql}
             ORDER BY pb.data_prova ASC
             LIMIT {$limite}",
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'titulo' => (string) ($row['titulo'] ?? 'Avaliação'),
                'data' => (string) ($row['data_prova'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
            ];
        }
        return $out;
    }

    private function eventosNotasPendentes(DashboardFiltro $filtro): int
    {
        if (!$this->consulta->colunaExiste('provas_blocos', 'formato_evento')) {
            return 0;
        }
        $db = \Database::getInstance();
        $where = [
            'pb.deleted_at IS NULL',
            "pb.formato_evento = 'lancamento_nota'",
            "pb.status IN ('liberado','aguardando')",
        ];
        $params = [];
        if ($filtro->bimestre > 0) {
            $where[] = 'pb.bimestre = :bimestre';
            $params['bimestre'] = $filtro->bimestre;
        }
        $turmaSql = $this->sqlTurmaEvento($filtro, $params);
        $row = $db->fetch(
            'SELECT COUNT(DISTINCT pb.id) AS n FROM provas_blocos pb WHERE ' . implode(' AND ', $where) . $turmaSql,
            $params
        );
        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param array<string,mixed> $params
     */
    private function sqlTurmaEvento(DashboardFiltro $filtro, array &$params): string
    {
        if ($filtro->turmaId <= 0) {
            return '';
        }
        $params['turma_id'] = $filtro->turmaId;
        return ' AND (pb.turma_id = :turma_id
            OR EXISTS (SELECT 1 FROM provas_blocos_turmas pbt WHERE pbt.bloco_id = pb.id AND pbt.turma_id = :turma_id)
            OR EXISTS (
                SELECT 1 FROM provas_blocos_professores pbp2
                INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp2.id
                WHERE pbp2.bloco_id = pb.id AND pbpt.turma_id = :turma_id
            ))';
    }
}
