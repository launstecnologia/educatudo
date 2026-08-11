<?php

namespace App\Performance\Reports;

/**
 * Exporta um resumo do relatório de performance em PDF, usando o dompdf que já
 * é dependência do projeto (composer.json) — mesmo padrão já usado em
 * Admin\ReportAdminController.
 */
final class PdfExporter
{
    /**
     * @param array<string,mixed> $summary
     * @param list<array<string,mixed>> $byRoute
     * @param list<array<string,mixed>> $topQueries
     * @param list<array<string,mixed>> $nPlusOne
     */
    public static function stream(
        array $summary,
        array $byRoute,
        array $topQueries,
        array $nPlusOne,
        string $filenameBase = 'performance-report'
    ): void {
        $html = self::buildHtml($summary, $byRoute, $topQueries, $nPlusOne);

        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . addslashes($filenameBase) . '.pdf"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            echo $dompdf->output();
            exit;
        } finally {
            ini_set('display_errors', (string) $oldDisplayErrors);
        }
    }

    private static function buildHtml(array $summary, array $byRoute, array $topQueries, array $nPlusOne): string
    {
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $rowsRoute = '';
        foreach (array_slice($byRoute, 0, 30) as $r) {
            $rowsRoute .= '<tr>'
                . '<td>' . $esc($r['controller_action']) . '</td>'
                . '<td>' . $esc($r['hits']) . '</td>'
                . '<td>' . $esc($r['avg_time_ms']) . '</td>'
                . '<td>' . $esc($r['avg_queries']) . '</td>'
                . '<td>' . $esc($r['max_time_ms']) . '</td>'
                . '</tr>';
        }

        $rowsQueries = '';
        foreach (array_slice($topQueries, 0, 30) as $q) {
            $rowsQueries .= '<tr>'
                . '<td style="font-family:monospace;font-size:8px;">' . $esc(substr($q['sample_sql'], 0, 140)) . '</td>'
                . '<td>' . $esc($q['count']) . '</td>'
                . '<td>' . $esc($q['avg_ms']) . '</td>'
                . '<td>' . $esc($q['total_ms']) . '</td>'
                . '<td>' . $esc(implode(', ', $q['flags'])) . '</td>'
                . '</tr>';
        }

        $rowsN1 = '';
        foreach (array_slice($nPlusOne, 0, 30) as $n) {
            $rowsN1 .= '<tr>'
                . '<td style="font-family:monospace;font-size:8px;">' . $esc(substr($n['sample_sql'], 0, 140)) . '</td>'
                . '<td>' . $esc($n['max_repeats_in_one_request']) . '</td>'
                . '<td>' . $esc($n['total_wasted_ms']) . '</td>'
                . '</tr>';
        }

        return <<<HTML
        <html>
        <head><meta charset="UTF-8"><style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
            h1 { font-size: 16px; } h2 { font-size: 13px; margin-top: 18px; }
            table { width: 100%; border-collapse: collapse; margin-top: 6px; }
            th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
            th { background: #f0f0f0; }
        </style></head>
        <body>
            <h1>EducaTudo — Relatório de Performance</h1>
            <p>Gerado em {$esc(date('d/m/Y H:i'))}</p>
            <h2>Resumo</h2>
            <table>
                <tr><th>Requests</th><th>Queries totais</th><th>Média de queries/req</th><th>Tempo médio (ms)</th><th>Com alerta</th><th>Com N+1</th></tr>
                <tr>
                    <td>{$esc($summary['total_requests'])}</td>
                    <td>{$esc($summary['total_queries'])}</td>
                    <td>{$esc($summary['avg_queries'])}</td>
                    <td>{$esc($summary['avg_time_ms'])}</td>
                    <td>{$esc($summary['requests_with_alerts'])}</td>
                    <td>{$esc($summary['requests_with_n1'])}</td>
                </tr>
            </table>

            <h2>Páginas mais lentas</h2>
            <table>
                <tr><th>Controller@Action</th><th>Hits</th><th>Tempo médio (ms)</th><th>Queries médias</th><th>Pico (ms)</th></tr>
                {$rowsRoute}
            </table>

            <h2>Top consultas SQL</h2>
            <table>
                <tr><th>SQL</th><th>Ocorrências</th><th>Média (ms)</th><th>Total (ms)</th><th>Flags</th></tr>
                {$rowsQueries}
            </table>

            <h2>N+1 detectados</h2>
            <table>
                <tr><th>SQL</th><th>Máx. repetições/req</th><th>Tempo desperdiçado total (ms)</th></tr>
                {$rowsN1}
            </table>
        </body>
        </html>
        HTML;
    }
}
