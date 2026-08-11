<?php

if (!class_exists('MasterCreditosExtratoController')) {

class MasterCreditosExtratoController extends BaseController
{
    public function index()
    {
        require_once __DIR__ . '/../../Core/Database.php';
        require_once __DIR__ . '/../../Core/MasterTenantConnection.php';
        require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';
        require_once __DIR__ . '/../../Core/CreditosModuleRegistry.php';

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
        $fUserType = trim((string) ($_GET['user_type'] ?? ''));
        $fModuloKey = trim((string) ($_GET['modulo_key'] ?? ''));
        $fTipo = trim((string) ($_GET['tipo'] ?? ''));
        if (!in_array($fTipo, ['plataforma', 'app_externo'], true)) {
            $fTipo = '';
        }
        $fDataIni = trim((string) ($_GET['data_ini'] ?? date('Y-m-01')));
        $fDataFim = trim((string) ($_GET['data_fim'] ?? date('Y-m-t')));
        $fSearch = trim((string) ($_GET['q'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fDataIni)) {
            $fDataIni = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fDataFim)) {
            $fDataFim = date('Y-m-t');
        }
        if ($fDataIni > $fDataFim) {
            $tmp = $fDataIni;
            $fDataIni = $fDataFim;
            $fDataFim = $tmp;
        }

        $movimentacoes = [];
        $totalConsumidoGeral = 0.0;
        $totalConsumidoFiltrado = 0.0;
        $quantidadeFiltrada = 0;

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
                $saldoOrigemCol = (bool) $tenantPdo->query("SHOW COLUMNS FROM carteira_movimentacoes LIKE 'saldo_origem'")->fetch(PDO::FETCH_ASSOC);
                $observacaoCol = (bool) $tenantPdo->query("SHOW COLUMNS FROM carteira_movimentacoes LIKE 'observacao'")->fetch(PDO::FETCH_ASSOC);

                $totalRow = $tenantPdo->query(
                    "SELECT COALESCE(SUM(ABS(valor)), 0) AS total
                     FROM carteira_movimentacoes
                     WHERE tipo = 'consumo' AND user_type IN ('aluno', 'professor')"
                )->fetch(PDO::FETCH_ASSOC);
                $totalConsumidoGeral += (float) ($totalRow['total'] ?? 0);

                $sql = "SELECT m.id, m.user_type, m.user_id, m.tipo, m.valor, m.modulo_key, m.referencia_id, m.created_at";
                $sql .= $saldoOrigemCol ? ", m.saldo_origem" : ", NULL AS saldo_origem";
                $sql .= $observacaoCol ? ", m.observacao" : ", NULL AS observacao";
                $sql .= ",
                    CASE
                        WHEN m.user_type = 'aluno' THEN (SELECT nome FROM alunos WHERE id = m.user_id LIMIT 1)
                        ELSE (SELECT nome FROM professores WHERE id = m.user_id LIMIT 1)
                    END AS usuario_nome,
                    CASE
                        WHEN m.user_type = 'aluno' THEN (SELECT email FROM alunos WHERE id = m.user_id LIMIT 1)
                        ELSE (SELECT email FROM professores WHERE id = m.user_id LIMIT 1)
                    END AS usuario_email
                    FROM carteira_movimentacoes m
                    WHERE m.tipo = 'consumo' AND m.user_type IN ('aluno', 'professor')";

                $params = [];
                if ($fUserType !== '') {
                    $sql .= " AND m.user_type = :user_type";
                    $params['user_type'] = $fUserType;
                }
                if ($fModuloKey !== '') {
                    $sql .= " AND m.modulo_key = :modulo_key";
                    $params['modulo_key'] = $fModuloKey;
                }
                if ($fDataIni !== '') {
                    $sql .= " AND DATE(m.created_at) >= :data_ini";
                    $params['data_ini'] = $fDataIni;
                }
                if ($fDataFim !== '') {
                    $sql .= " AND DATE(m.created_at) <= :data_fim";
                    $params['data_fim'] = $fDataFim;
                }
                $sql .= " ORDER BY m.created_at DESC";

                $stmt = $tenantPdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                continue;
            }

            foreach ($rows as $row) {
                $moduloKeyRow = (string) ($row['modulo_key'] ?? '');
                $isExterno = \CreditosModuleRegistry::isAppExterno($moduloKeyRow);
                if ($fTipo === 'app_externo' && !$isExterno) {
                    continue;
                }
                if ($fTipo === 'plataforma' && $isExterno) {
                    continue;
                }

                $searchBlob = mb_strtolower(
                    trim(
                        (string) ($row['usuario_nome'] ?? '') . ' ' .
                        (string) ($row['usuario_email'] ?? '') . ' ' .
                        (string) ($row['modulo_key'] ?? '') . ' ' .
                        (string) ($row['referencia_id'] ?? '') . ' ' .
                        (string) ($row['observacao'] ?? '') . ' ' .
                        (string) ($escola['nome'] ?? '')
                    )
                );
                if ($fSearch !== '' && mb_stripos($searchBlob, mb_strtolower($fSearch)) === false) {
                    continue;
                }

                $valorConsumido = abs((float) ($row['valor'] ?? 0));
                $totalConsumidoFiltrado += $valorConsumido;
                $quantidadeFiltrada++;
                $row['valor_consumido'] = $valorConsumido;
                $row['escola_id'] = $escolaId;
                $row['escola_nome'] = (string) ($escola['nome'] ?? '');
                $row['origem_tipo'] = $isExterno ? 'app_externo' : 'plataforma';
                $movimentacoes[] = $row;
            }
        }

        usort($movimentacoes, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        $modulos = [];
        foreach (\CreditosModuleRegistry::getModuleKeys() as $key) {
            $modulos[$key] = \CreditosModuleRegistry::getLabel($key);
        }

        $perPage = 10;
        $totalMovimentacoes = count($movimentacoes);
        $totalPages = $totalMovimentacoes > 0 ? (int) ceil($totalMovimentacoes / $perPage) : 0;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $movimentacoesPaginadas = $totalMovimentacoes > 0 ? array_slice($movimentacoes, $offset, $perPage) : [];

        $this->viewWithLayout('master', 'master/creditos/extrato', [
            'title' => 'Extrato de TudiCoins - EducaTudo',
            'page_title' => 'Extrato de TudiCoins',
            'current_page' => 'creditos_extrato',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'escolas' => $escolas,
            'movimentacoes' => $movimentacoesPaginadas,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalMovimentacoes,
                'total_pages' => $totalPages,
            ],
            'totais' => [
                'quantidade_filtrada' => $quantidadeFiltrada,
                'quantidade_exibida' => count($movimentacoesPaginadas),
                'consumido_filtrado' => $totalConsumidoFiltrado,
                'consumido_geral' => $totalConsumidoGeral,
            ],
            'modulos' => $modulos,
            'filtro_escola' => $fEscola,
            'filtro_user_type' => $fUserType,
            'filtro_modulo_key' => $fModuloKey,
            'filtro_tipo' => $fTipo,
            'filtro_data_ini' => $fDataIni,
            'filtro_data_fim' => $fDataFim,
            'filtro_q' => $fSearch,
        ]);
    }
}

}
