<?php

if (!class_exists('MasterFaturamentoController')) {

class MasterFaturamentoController extends BaseController
{
    public function index()
    {
        require_once __DIR__ . '/../../Core/Database.php';
        require_once __DIR__ . '/../../Core/MasterTenantConnection.php';
        require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';
        require_once __DIR__ . '/../../Services/TenantCreditsCheckoutService.php';

        if (empty($_SESSION['master_user_id']) && empty($_SESSION['master_user_email']) && empty($_SESSION['master_user_nome'])) {
            header('Location: ' . URL . '/master');
            exit;
        }

        $db = Database::getInstance();
        $escolas = $db->query(
            "SELECT e.id, e.nome, e.slug
             FROM escolas e
             INNER JOIN config_escolas_banco b ON b.escola_id = e.id
             WHERE e.ativo = 1
             ORDER BY e.nome"
        )->fetchAll(PDO::FETCH_ASSOC);

        $fEscola = (int) ($_GET['escola_id'] ?? 0);
        $fStatus = trim((string) ($_GET['status'] ?? ''));
        $fBilling = strtoupper(trim((string) ($_GET['billing_type'] ?? '')));
        $fUserType = trim((string) ($_GET['user_type'] ?? ''));
        $fSearch = trim((string) ($_GET['q'] ?? ''));

        $compras = [];
        $totalBruto = 0.0;
        $totalPago = 0.0;
        $totalPendentes = 0;

        foreach ($escolas as $escola) {
            $escolaId = (int) ($escola['id'] ?? 0);
            if ($fEscola > 0 && $fEscola !== $escolaId) {
                continue;
            }

            $conn = MasterTenantConnection::getPdoAndEscola($escolaId);
            if (!$conn || empty($conn['pdo'])) {
                continue;
            }

            $tenantPdo = $conn['pdo'];
            try {
                \App\Services\TenantCreditsCheckoutService::ensureComprasCreditosAsaasColumns($tenantPdo);
                $rows = $tenantPdo->query(
                    "SELECT c.id, c.user_type, c.user_id, c.valor_centavos, c.status, c.billing_type, c.asaas_payment_id, c.created_at, c.updated_at, c.paid_at,
                            p.nome AS pacote_nome, p.creditos,
                            CASE WHEN c.user_type = 'aluno' THEN (SELECT nome FROM alunos WHERE id = c.user_id LIMIT 1)
                                 ELSE (SELECT nome FROM professores WHERE id = c.user_id LIMIT 1) END AS usuario_nome,
                            CASE WHEN c.user_type = 'aluno' THEN (SELECT email FROM alunos WHERE id = c.user_id LIMIT 1)
                                 ELSE (SELECT email FROM professores WHERE id = c.user_id LIMIT 1) END AS usuario_email
                     FROM compras_creditos c
                     INNER JOIN pacotes_creditos p ON p.id = c.pacote_id
                     ORDER BY c.id DESC
                     LIMIT 200"
                )->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                continue;
            }

            foreach ($rows as $row) {
                $status = (string) ($row['status'] ?? '');
                $billingType = strtoupper((string) ($row['billing_type'] ?? ''));
                $userType = (string) ($row['user_type'] ?? '');
                $searchBlob = mb_strtolower(
                    trim(
                        (string) ($row['usuario_nome'] ?? '') . ' ' .
                        (string) ($row['usuario_email'] ?? '') . ' ' .
                        (string) ($row['pacote_nome'] ?? '') . ' ' .
                        (string) ($row['asaas_payment_id'] ?? '') . ' ' .
                        (string) ($escola['nome'] ?? '')
                    )
                );
                if ($fStatus !== '' && $status !== $fStatus) {
                    continue;
                }
                // Cancelados ficam fora da listagem padrão; só aparecem se filtrar por status=cancelled.
                if ($fStatus === '' && $status === 'cancelled') {
                    continue;
                }
                if ($fBilling !== '' && $billingType !== $fBilling) {
                    continue;
                }
                if ($fUserType !== '' && $userType !== $fUserType) {
                    continue;
                }
                if ($fSearch !== '' && mb_stripos($searchBlob, mb_strtolower($fSearch)) === false) {
                    continue;
                }

                $valor = ((int) ($row['valor_centavos'] ?? 0)) / 100;
                $totalBruto += $valor;
                if ($status === 'paid') {
                    $totalPago += $valor;
                }
                if ($status === 'pending') {
                    $totalPendentes++;
                }

                $row['escola_id'] = $escolaId;
                $row['escola_nome'] = (string) ($escola['nome'] ?? '');
                $compras[] = $row;
            }
        }

        usort($compras, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        $perPage = 10;
        $totalCompras = count($compras);
        $totalPages = $totalCompras > 0 ? (int) ceil($totalCompras / $perPage) : 0;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $comprasPaginadas = $totalCompras > 0 ? array_slice($compras, $offset, $perPage) : [];

        $this->viewWithLayout('master', 'master/faturamento/index', [
            'title' => 'Faturamento - EducaTudo',
            'page_title' => 'Faturamento',
            'current_page' => 'faturamento',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'escolas' => $escolas,
            'compras' => $comprasPaginadas,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalCompras,
                'total_pages' => $totalPages,
            ],
            'totais' => [
                'quantidade' => $totalCompras,
                'bruto' => $totalBruto,
                'pago' => $totalPago,
                'pendentes' => $totalPendentes,
            ],
            'filtro_escola' => $fEscola,
            'filtro_status' => $fStatus,
            'filtro_billing_type' => $fBilling,
            'filtro_user_type' => $fUserType,
            'filtro_q' => $fSearch,
        ]);
    }
}

}
