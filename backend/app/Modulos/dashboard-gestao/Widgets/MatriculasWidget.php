<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardConsulta;
use App\Modulos\DashboardGestao\Services\DashboardFiltro;

class MatriculasWidget implements WidgetDashboard
{
    private DashboardConsulta $consulta;

    public function __construct(DashboardConsulta $consulta)
    {
        $this->consulta = $consulta;
    }

    public function chave(): string
    {
        return 'matriculas';
    }

    public function montar(DashboardFiltro $filtro): array
    {
        $ativas = $this->consulta->contarAlunosMatriculados($filtro);
        $novas = 0;
        $transferencias = 0;
        $cancelamentos = 0;

        if ($this->consulta->tabelaExiste('matricula') && !$filtro->semTurmas) {
            $db = \Database::getInstance();
            $params = ['inicio' => $filtro->inicio, 'fim' => $filtro->fim];
            $anoSql = '';
            if ($filtro->anoLetivoId > 0) {
                $anoSql = ' AND m.ano_letivo_id = :ano_letivo_id';
                $params['ano_letivo_id'] = $filtro->anoLetivoId;
            }
            [$turmaSql, $turmaParams] = $this->consulta->sqlInTurmas($filtro->turmaIds, 'm.turma_id');
            $params = array_merge($params, $turmaParams);

            $novas = (int) ($db->fetch(
                "SELECT COUNT(DISTINCT m.aluno_id) AS n FROM matricula m
                 WHERE m.data_entrada BETWEEN :inicio AND :fim{$anoSql}{$turmaSql}",
                $params
            )['n'] ?? 0);

            $transferencias = (int) ($db->fetch(
                "SELECT COUNT(DISTINCT m.aluno_id) AS n FROM matricula m
                 WHERE m.status = 'transferido'
                   AND COALESCE(m.data_saida, DATE(m.updated_at)) BETWEEN :inicio AND :fim{$anoSql}{$turmaSql}",
                $params
            )['n'] ?? 0);

            $cancelamentos = (int) ($db->fetch(
                "SELECT COUNT(DISTINCT m.aluno_id) AS n FROM matricula m
                 WHERE m.status = 'concluido'
                   AND COALESCE(m.data_saida, DATE(m.updated_at)) BETWEEN :inicio AND :fim{$anoSql}{$turmaSql}",
                $params
            )['n'] ?? 0);
        }

        if ($this->consulta->tabelaExiste('matricula_transferencias')) {
            $db = \Database::getInstance();
            $row = $db->fetch(
                'SELECT COUNT(*) AS n FROM matricula_transferencias
                 WHERE data_transferencia BETWEEN :inicio AND :fim',
                ['inicio' => $filtro->inicio, 'fim' => $filtro->fim]
            );
            $viaProtocolo = (int) ($row['n'] ?? 0);
            if ($viaProtocolo > $transferencias) {
                $transferencias = $viaProtocolo;
            }
        }

        return [
            'ok' => true,
            'disponivel' => true,
            'ativas' => $ativas,
            'novas' => $novas,
            'transferencias' => $transferencias,
            'cancelamentos' => $cancelamentos,
            'href' => '/admin/students',
            'href_processos' => '/admin/enrollment',
        ];
    }
}
