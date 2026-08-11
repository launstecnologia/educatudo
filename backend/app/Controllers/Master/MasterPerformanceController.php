<?php
/**
 * EducaTudo - Dashboard de Performance (Master → Ferramentas → Performance).
 *
 * Lê os JSONL gerados pelo Performance Profiler (app/Performance/*), que só
 * coleta dado quando APP_DEBUG=true. Painel é master-only porque os logs
 * abrangem TODAS as escolas (é um único processo PHP atendendo todos os
 * tenants) — não é apropriado mostrar isso pra admin de escola.
 */

use App\Performance\Profiler;
use App\Performance\Reports\CsvExporter;
use App\Performance\Reports\PdfExporter;
use App\Performance\Reports\RequestLogReader;

if (!class_exists('MasterPerformanceController')) {

class MasterPerformanceController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';

    private function requireMaster(): void
    {
        if (empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            header('Location: ' . URL . '/master');
            exit;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function filtersFromRequest(): array
    {
        $filters = [];
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = (string) $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = (string) $_GET['date_to'];
        }
        if (!empty($_GET['tenant'])) {
            $filters['tenant'] = (string) $_GET['tenant'];
        }
        if (!empty($_GET['route'])) {
            $filters['route'] = (string) $_GET['route'];
        }
        if (!empty($_GET['controller'])) {
            $filters['controller'] = (string) $_GET['controller'];
        }
        if (!empty($_GET['min_ms'])) {
            $filters['min_ms'] = (float) $_GET['min_ms'];
        }
        if (!empty($_GET['min_queries'])) {
            $filters['min_queries'] = (int) $_GET['min_queries'];
        }
        if (!empty($_GET['only_alerts'])) {
            $filters['only_alerts'] = true;
        }

        // Padrão: hoje (evita ler todo o histórico sem querer numa base grande).
        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            $filters['date_from'] = date('Y-m-d');
            $filters['date_to'] = date('Y-m-d');
        }

        return $filters;
    }

    public function index(): void
    {
        $this->requireMaster();

        $filters = $this->filtersFromRequest();
        $rows = RequestLogReader::read($filters, 5000);
        $summary = RequestLogReader::summary($rows);
        $byRoute = RequestLogReader::byRoute($rows);
        $topQueries = RequestLogReader::topQueries($rows, 50, (string) ($_GET['order_by'] ?? 'avg'));
        $nPlusOne = RequestLogReader::nPlusOneAcrossRequests($rows);

        // Tendência diária: janela PRÓPRIA (não presa ao filtro de data das tabelas
        // acima — se o filtro é só "hoje", o gráfico ficaria com 1 ponto só, o que
        // não responde "está piorando com o tempo?"). Reaproveita só escola/rota/
        // controller do filtro; min_ms/min_queries/only_alerts não fazem sentido
        // aqui (queremos a média de TODAS as requisições, não só as piores).
        $trendDays = max(2, min(90, (int) ($_GET['trend_days'] ?? 14)));
        $trendFilters = array_intersect_key($filters, array_flip(['tenant', 'route', 'controller']));
        $trendFilters['date_from'] = date('Y-m-d', strtotime('-' . ($trendDays - 1) . ' days'));
        $trendFilters['date_to'] = date('Y-m-d');
        $trendRows = RequestLogReader::read($trendFilters, 20000);
        $byDay = RequestLogReader::byDay($trendRows);

        // Tenants distintos vistos no período — só pra popular o <select> de filtro.
        $tenants = array_values(array_unique(array_filter(array_map(
            static fn ($r) => $r['tenant_slug'] ?? '',
            $rows
        ))));
        sort($tenants);

        $this->viewWithLayout('master', 'master/performance/index', [
            'title' => 'Performance - Painel Master',
            'page_title' => 'Performance',
            'current_page' => 'performance',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'app_debug_on' => !empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true',
            'profiler_enabled' => Profiler::isEnabled(),
            'profiler_paused' => Profiler::isPaused(),
            'csrf_token' => $this->generateCsrfToken(),
            'filters' => $filters,
            'summary' => $summary,
            'by_route' => $byRoute,
            'top_queries' => $topQueries,
            'n_plus_one' => $nPlusOne,
            'tenants' => $tenants,
            'by_day' => $byDay,
            'trend_days' => $trendDays,
            'requests_sample' => array_slice($rows, 0, 200),
        ]);
    }

    /**
     * Pausa/retoma a coleta em tempo real (sem editar .env nem reiniciar nada).
     * Só tem efeito se APP_DEBUG=true no servidor — essa trava é fixa e não é
     * contornável por aqui, de propósito (ver Profiler::isEnabled()).
     */
    public function toggle(): void
    {
        $this->requireMaster();

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada, tente novamente.', 'error');
            $this->redirect('/master/performance');
            return;
        }

        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'pause') {
            Profiler::pause();
            $this->setFlashMessage('Coleta de performance pausada.', 'success');
        } elseif ($action === 'resume') {
            Profiler::resume();
            $this->setFlashMessage('Coleta de performance retomada.', 'success');
        }

        $this->redirect('/master/performance');
    }

    public function export(): void
    {
        $this->requireMaster();

        $filters = $this->filtersFromRequest();
        $rows = RequestLogReader::read($filters, 20000);
        $format = strtolower((string) ($_GET['format'] ?? 'csv'));
        $dataset = (string) ($_GET['dataset'] ?? 'routes');

        $byRoute = RequestLogReader::byRoute($rows);
        $topQueries = RequestLogReader::topQueries($rows, 200, (string) ($_GET['order_by'] ?? 'avg'));
        $nPlusOne = RequestLogReader::nPlusOneAcrossRequests($rows);
        $summary = RequestLogReader::summary($rows);

        if ($format === 'pdf') {
            PdfExporter::stream($summary, $byRoute, $topQueries, $nPlusOne, 'performance-' . date('Y-m-d'));
            return;
        }

        $data = match ($dataset) {
            'queries' => $topQueries,
            'n_plus_one' => $nPlusOne,
            'requests' => $rows,
            default => $byRoute,
        };

        if ($format === 'json') {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="performance-' . $dataset . '-' . date('Y-m-d') . '.json"');
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        CsvExporter::stream($data, 'performance-' . $dataset . '-' . date('Y-m-d') . '.csv');
    }
}
}
