<?php

namespace App\Webhooks\Controllers;

use App\Core\BaseController;
use App\Core\Logger;

/**
 * SEC-02 — recebe relatórios CSP (report-uri / report-to).
 * Rota pública, sem sessão; body limitado; só loga (não altera estado).
 */
class CspReportController extends BaseController
{
    private const MAX_BYTES = 16384;

    /** Loga ~1 em 10 relatórios para reduzir flood de disco/CPU. */
    private const SAMPLE_RATE = 10;

    public function receber(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
            return;
        }

        $raw = file_get_contents('php://input', false, null, 0, self::MAX_BYTES + 1);
        if ($raw === false || $raw === '') {
            http_response_code(204);
            return;
        }
        if (strlen($raw) > self::MAX_BYTES) {
            http_response_code(413);
            return;
        }

        // Sempre responde 204; só amostra o log.
        if (random_int(1, self::SAMPLE_RATE) !== 1) {
            http_response_code(204);
            return;
        }

        $decoded = json_decode($raw, true);
        $report = is_array($decoded) ? ($decoded['csp-report'] ?? $decoded) : ['_raw' => substr($raw, 0, 500)];

        try {
            Logger::warning('CSP violation report', [
                'document-uri' => $report['document-uri'] ?? $report['documentURL'] ?? null,
                'violated-directive' => $report['violated-directive'] ?? $report['effectiveDirective'] ?? null,
                'blocked-uri' => $report['blocked-uri'] ?? $report['blockedURL'] ?? null,
                'tenant' => defined('TENANT_SLUG') ? TENANT_SLUG : null,
            ], 'csp');
        } catch (\Throwable $e) {
            // nunca quebrar o navegador por falha de log
        }

        http_response_code(204);
    }
}
