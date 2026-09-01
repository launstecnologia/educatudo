<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardFiltro;
use App\Modulos\Diario\Services\ClassDiaryService;
use App\Modulos\Ocorrencias\Services\OcorrenciaService;

require_once dirname(__DIR__, 3) . '/Services/ClassDiaryService.php';
require_once dirname(__DIR__, 3) . '/Models/Exams/ExamBlock.php';

/**
 * Ações do recorte: diários em atraso (critério oficial), notas do bimestre, ocorrências da janela curta.
 */
class PendenciasWidget implements WidgetDashboard
{
    public function chave(): string
    {
        return 'pendencias';
    }

    public function montar(DashboardFiltro $filtro): array
    {
        [$inicio, $fim] = $filtro->recorteCurto();
        $janelaVazia = $inicio === '' || $fim === '';
        $qsDiario = http_build_query(array_merge($filtro->queryModulo(), ['status' => 'pendente']));
        $cards = [];

        if (!class_exists('LayoutHelper', false) || \LayoutHelper::isModuleEnabled('diario_classe')) {
            $turmasAtraso = $janelaVazia ? 0 : $this->contarTurmasComDiarioAtrasado($filtro, $inicio, $fim);
            $cards[] = [
                'label' => 'Diários em atraso',
                'valor' => $turmasAtraso,
                'href' => '/admin/diario/indicadores?' . $qsDiario,
                'icon' => 'fa-book-open',
                'valueClass' => $turmasAtraso > 0 ? 'text-amber-700' : 'text-green-700',
                'iconClass' => 'bg-amber-50 text-amber-600',
            ];
        }

        $notasPendentes = $this->contarNotasPendentes($filtro);
        $cards[] = [
            'label' => 'Notas a lançar',
            'valor' => $notasPendentes,
            'href' => '/admin/provas',
            'icon' => 'fa-pen-to-square',
            'valueClass' => $notasPendentes > 0 ? 'text-amber-700' : 'text-green-700',
            'iconClass' => 'bg-amber-50 text-amber-600',
        ];

        if ($this->podeVerOcorrencias()) {
            $ocorrencias = $janelaVazia ? 0 : $this->contarOcorrenciasAbertas($filtro, $inicio, $fim);
            $cards[] = [
                'label' => 'Ocorrências abertas',
                'valor' => $ocorrencias,
                'href' => '/admin/ocorrencias',
                'icon' => 'fa-clock',
                'valueClass' => $ocorrencias > 0 ? 'text-amber-700' : 'text-green-700',
                'iconClass' => 'bg-amber-50 text-amber-600',
            ];
        }

        return [
            'ok' => true,
            'disponivel' => true,
            'cards' => $cards,
        ];
    }

    /**
     * Turmas com indicador oficial "atraso" (aulas vencidas sem registro) no recorte curto.
     */
    private function contarTurmasComDiarioAtrasado(DashboardFiltro $filtro, string $inicio, string $fim): int
    {
        if ($filtro->semTurmas) {
            return 0;
        }
        $diario = new ClassDiaryService();
        $indicadores = $diario->indicadores($inicio, $fim);
        $permitidas = $filtro->turmaId > 0
            ? [(int) $filtro->turmaId => true]
            : array_flip($filtro->turmaIds);
        $turmas = [];
        foreach ($indicadores as $linha) {
            $tid = (int) ($linha['turma_id'] ?? 0);
            if ($tid <= 0 || !isset($permitidas[$tid])) {
                continue;
            }
            if ((string) ($linha['situacao'] ?? '') === 'atraso') {
                $turmas[$tid] = true;
            }
        }

        return count($turmas);
    }

    private function contarNotasPendentes(DashboardFiltro $filtro): int
    {
        try {
            $blocos = new \ExamBlock();
        } catch (\Throwable $e) {
            return 0;
        }
        $filters = ['status' => 'aguardando', 'bimestre' => $filtro->bimestre];
        if ($filtro->turmaId > 0) {
            $filters['turma_id'] = $filtro->turmaId;
        }
        return $blocos->getCountFiltered($filters);
    }

    private function contarOcorrenciasAbertas(DashboardFiltro $filtro, string $inicio, string $fim): int
    {
        if ($filtro->semTurmas) {
            return 0;
        }
        try {
            require_once dirname(__DIR__, 3) . '/Services/OcorrenciaService.php';
            $svc = new OcorrenciaService();
            if ($filtro->turmaId > 0) {
                $base = [
                    'data_inicio' => $inicio,
                    'data_fim' => $fim,
                    'turma_id' => $filtro->turmaId,
                ];
                if ($svc->model()->schemaEstendido()) {
                    $base['status'] = 'aberta';
                }
                return $svc->model()->contar($base);
            }
            return $this->contarOcorrenciasNasTurmas($svc, $filtro->turmaIds, $inicio, $fim);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @param list<int> $turmaIds
     */
    private function contarOcorrenciasNasTurmas(OcorrenciaService $svc, array $turmaIds, string $inicio, string $fim): int
    {
        $turmaIds = array_values(array_filter(array_map('intval', $turmaIds), static fn ($id) => $id > 0));
        if ($turmaIds === []) {
            return 0;
        }
        $params = ['inicio' => $inicio, 'fim' => $fim];
        $ph = [];
        foreach ($turmaIds as $i => $id) {
            $chave = 'tid_' . $i;
            $ph[] = ':' . $chave;
            $params[$chave] = $id;
        }
        $inTurmas = implode(', ', $ph);
        $sql = "SELECT COUNT(*) AS n FROM alunos_ocorrencias o
                WHERE DATE(o.data_ocorrencia) BETWEEN :inicio AND :fim
                  AND o.turma_id IN ($inTurmas)";
        if ($svc->model()->schemaEstendido()) {
            $sql .= " AND o.status = 'aberta'";
        }
        $row = \Database::getInstance()->fetch($sql, $params);

        return (int) ($row['n'] ?? 0);
    }

    private function podeVerOcorrencias(): bool
    {
        if (class_exists('LayoutHelper', false) && !\LayoutHelper::isModuleEnabled('ocorrencias')) {
            return false;
        }
        try {
            if (!class_exists('AdminPermissionMatrix', false)) {
                require_once dirname(__DIR__, 3) . '/Core/AdminPermissionMatrix.php';
            }
            if (!class_exists('AuthManager', false)) {
                require_once dirname(__DIR__, 3) . '/Core/AuthManager.php';
            }
            $user = (new \AuthManager())->getUser() ?? [];
            $perms = \AdminPermissionMatrix::effectivePermissionsForUser(\Database::getInstance(), $user);
            return \AdminPermissionMatrix::can($perms, 'ocorrencias', 'visualizar');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
