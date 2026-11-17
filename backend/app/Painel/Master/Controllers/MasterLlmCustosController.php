<?php

namespace App\Painel\Master\Controllers;
/**
 * Painel Master — Custo LLM (tokens input/output + USD agregados por escola).
 */


use App\Core\BaseController;
use App\Core\Database;
if (!class_exists(__NAMESPACE__ . '\\MasterLlmCustosController', false)) {

class MasterLlmCustosController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';

    private function requireMaster(): void
    {
        if (empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            header('Location: ' . URL . '/master');
            exit;
        }
    }

    public function index()
    {
        $this->requireMaster();

        $dateStart = trim((string) ($_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'))));
        $dateEnd = trim((string) ($_GET['data_fim'] ?? date('Y-m-d')));
        $escolaId = (int) ($_GET['escola_id'] ?? 0);
        $cotacaoRaw = str_replace(',', '.', trim((string) ($_GET['cotacao'] ?? '')));
        $cotacao = is_numeric($cotacaoRaw) && (float) $cotacaoRaw > 0
            ? (float) $cotacaoRaw
            : null;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart)) {
            $dateStart = date('Y-m-d', strtotime('-30 days'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)) {
            $dateEnd = date('Y-m-d');
        }
        if ($dateStart > $dateEnd) {
            $tmp = $dateStart;
            $dateStart = $dateEnd;
            $dateEnd = $tmp;
        }

        $db = Database::getInstance();
        $escolas = $db->query(
            "SELECT e.id, e.nome, e.slug
             FROM escolas e
             INNER JOIN config_escolas_banco b ON b.escola_id = e.id
             WHERE e.ativo = 1
             ORDER BY e.nome"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $relatorio = \App\Services\MasterLlmCustosService::agregar($dateStart, $dateEnd, $escolaId, $cotacao);

        $this->viewWithLayout('master', 'master/llm_custos/index', [
            'title' => 'Custo LLM - Painel Master',
            'page_title' => 'Custo LLM',
            'current_page' => 'llm_custos',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'escolas' => $escolas,
            'relatorio' => $relatorio,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'filtro_escola' => $escolaId,
            'filtro_cotacao' => (float) (($relatorio['financeiro']['cotacao_usd_brl'] ?? \App\Services\MasterLlmCustosService::cotacaoUsdBrl($cotacao))),
        ]);
    }

    /**
     * JSON: detalhe de chats/chamadas LLM de um dia.
     * GET /master/llm-custos/dia?data=YYYY-MM-DD&escola_id=0
     */
    public function detalheDia()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Não autenticado.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $date = trim((string) ($_GET['data'] ?? ''));
        $escolaId = (int) ($_GET['escola_id'] ?? 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Data inválida.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $detalhe = \App\Services\MasterLlmCustosService::detalheDia($date, $escolaId);
        echo json_encode(['success' => true] + $detalhe, JSON_UNESCAPED_UNICODE);
    }
}

}

if (!class_exists('MasterLlmCustosController', false) && class_exists(__NAMESPACE__ . '\\MasterLlmCustosController', false)) {
    class_alias(__NAMESPACE__ . '\\MasterLlmCustosController', 'MasterLlmCustosController');
}
