<?php

if (!class_exists('MasterCreditosAlunosController')) {

class MasterCreditosAlunosController extends BaseController
{
    public function index()
    {
        $this->requireMaster();
        require_once __DIR__ . '/../../Core/Database.php';
        require_once __DIR__ . '/../../Core/MasterTenantConnection.php';
        require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';

        $db = Database::getInstance();
        $escolas = $this->listarEscolasComBanco($db);

        $fEscola = (int) ($_GET['escola_id'] ?? 0);
        $fNome = trim((string) ($_GET['nome'] ?? ''));
        $fTurma = trim((string) ($_GET['turma'] ?? ''));
        $fOrdem = trim((string) ($_GET['creditos_ordem'] ?? ''));
        if (!in_array($fOrdem, ['maior', 'menor', 'nome'], true)) {
            $fOrdem = 'nome';
        }

        $alunos = [];
        $turmasPorEscola = [];
        $totalTudicoins = 0.0;
        $escolasConsultadas = 0;

        foreach ($escolas as $escola) {
            $escolaId = (int) ($escola['id'] ?? 0);
            if ($fEscola > 0 && $fEscola !== $escolaId) {
                continue;
            }

            $conn = MasterTenantConnection::getPdoAndEscola($escolaId);
            if (!$conn || empty($conn['pdo'])) {
                continue;
            }

            $pdo = $conn['pdo'];
            try {
                $escolasConsultadas++;
                $turmasPorEscola[$escolaId] = $this->listarTurmas($pdo);
                foreach ($this->listarAlunosDaEscola($pdo, $escola, $fNome, $fTurma) as $aluno) {
                    $saldo = CreditosDecimalHelper::fromSignedScalar($aluno['saldo_creditos'] ?? 0, 0.0);
                    $totalTudicoins += $saldo;
                    $aluno['saldo_creditos'] = $saldo;
                    $alunos[] = $aluno;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        usort($alunos, static function (array $a, array $b) use ($fOrdem): int {
            if ($fOrdem === 'maior') {
                $cmp = ((float) ($b['saldo_creditos'] ?? 0)) <=> ((float) ($a['saldo_creditos'] ?? 0));
                return $cmp !== 0 ? $cmp : strcasecmp((string) ($a['aluno_nome'] ?? ''), (string) ($b['aluno_nome'] ?? ''));
            }
            if ($fOrdem === 'menor') {
                $cmp = ((float) ($a['saldo_creditos'] ?? 0)) <=> ((float) ($b['saldo_creditos'] ?? 0));
                return $cmp !== 0 ? $cmp : strcasecmp((string) ($a['aluno_nome'] ?? ''), (string) ($b['aluno_nome'] ?? ''));
            }
            $cmpEscola = strcasecmp((string) ($a['escola_nome'] ?? ''), (string) ($b['escola_nome'] ?? ''));
            return $cmpEscola !== 0 ? $cmpEscola : strcasecmp((string) ($a['aluno_nome'] ?? ''), (string) ($b['aluno_nome'] ?? ''));
        });

        $perPage = 20;
        $totalAlunos = count($alunos);
        $totalPages = $totalAlunos > 0 ? (int) ceil($totalAlunos / $perPage) : 1;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $this->viewWithLayout('master', 'master/creditos/alunos', [
            'title' => 'Alunos TudiCoins - EducaTudo',
            'page_title' => 'Alunos TudiCoins',
            'current_page' => 'creditos_alunos',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'flash' => $this->getFlashMessage(),
            'csrf_token' => $this->generateCsrfToken(),
            'escolas' => $escolas,
            'alunos' => array_slice($alunos, $offset, $perPage),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalAlunos,
                'total_pages' => $totalPages,
            ],
            'totais' => [
                'alunos' => $totalAlunos,
                'escolas_consultadas' => $escolasConsultadas,
                'tudicoins' => $totalTudicoins,
            ],
            'turmas_por_escola' => $turmasPorEscola,
            'filtro_escola' => $fEscola,
            'filtro_nome' => $fNome,
            'filtro_turma' => $fTurma,
            'filtro_creditos_ordem' => $fOrdem,
        ]);
    }

    public function creditar()
    {
        $this->requireMaster();
        require_once __DIR__ . '/../../Core/MasterTenantConnection.php';
        require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';

        $escolaId = (int) ($_POST['escola_id'] ?? 0);
        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $voltar = $this->buildVoltarUrl();

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . $voltar);
            exit;
        }

        $valor = CreditosDecimalHelper::parsePost($_POST['valor'] ?? 0);
        if ($escolaId <= 0 || $alunoId <= 0 || $valor <= 0 || $valor > 100000) {
            $this->setFlashMessage('Informe aluno, escola e valor de TudiCoins maior que zero (máx. 100.000).', 'error');
            header('Location: ' . $voltar);
            exit;
        }

        $conn = MasterTenantConnection::getPdoAndEscola($escolaId);
        if (!$conn || empty($conn['pdo'])) {
            $this->setFlashMessage('Não foi possível conectar ao banco da escola.', 'error');
            header('Location: ' . $voltar);
            exit;
        }

        try {
            $pdo = $conn['pdo'];
            if (!$this->tenantWalletDisponivel($pdo)) {
                $this->setFlashMessage('Carteira de TudiCoins indisponível nesta escola. Rode as migrations do tenant antes de creditar.', 'error');
                header('Location: ' . $voltar);
                exit;
            }

            $stmt = $pdo->prepare('SELECT id, nome FROM alunos WHERE id = ? AND ativo = 1 LIMIT 1');
            $stmt->execute([$alunoId]);
            $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$aluno) {
                $this->setFlashMessage('Aluno não encontrado nesta escola.', 'error');
                header('Location: ' . $voltar);
                exit;
            }

            $nome = trim((string) ($aluno['nome'] ?? 'Aluno'));
            $observacao = 'Cortesia Master para ' . $nome . '.';
            $this->creditarCortesiaUsuario($pdo, 'aluno', $alunoId, $valor, $observacao);
        } catch (Throwable $e) {
            $this->setFlashMessage('Erro ao creditar TudiCoins: ' . $e->getMessage(), 'error');
            header('Location: ' . $voltar);
            exit;
        }

        $this->setFlashMessage(
            sprintf('%s creditados para %s.', CreditosDecimalHelper::formatDisplay($valor), $nome),
            'success'
        );
        header('Location: ' . $voltar);
        exit;
    }

    private function requireMaster(): void
    {
        if (!empty($_SESSION['master_user_id']) || !empty($_SESSION['master_user_email']) || !empty($_SESSION['master_user_nome'])) {
            return;
        }
        header('Location: ' . URL . '/master');
        exit;
    }

    private function listarEscolasComBanco(Database $db): array
    {
        return $db->query(
            "SELECT e.id, e.nome, e.slug
             FROM escolas e
             INNER JOIN config_escolas_banco b ON b.escola_id = e.id
             WHERE e.ativo = 1
             ORDER BY e.nome"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    private function listarTurmas(PDO $pdo): array
    {
        try {
            return $pdo->query("SELECT DISTINCT nome FROM turmas WHERE ativo = 1 AND nome IS NOT NULL AND nome <> '' ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            return [];
        }
    }

    private function listarAlunosDaEscola(PDO $pdo, array $escola, string $fNome, string $fTurma): array
    {
        $temCarteira = $this->tenantWalletDisponivel($pdo);
        $params = [];
        $where = 'a.ativo = 1';
        if ($fNome !== '') {
            $where .= ' AND (a.nome LIKE ? OR a.email LIKE ? OR a.ra LIKE ?)';
            $like = '%' . $fNome . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($fTurma !== '') {
            $where .= ' AND t.nome LIKE ?';
            $params[] = '%' . $fTurma . '%';
        }

        $creditosJoin = $temCarteira
            ? "LEFT JOIN carteira_usuarios cu ON cu.user_type = 'aluno' AND cu.user_id = a.id"
            : '';
        $creditosSelect = $temCarteira ? 'COALESCE(cu.saldo, 0)' : '0';

        $stmt = $pdo->prepare(
            "SELECT a.id AS aluno_id, a.nome AS aluno_nome, a.email, a.ra, t.nome AS turma_nome,
                    {$creditosSelect} AS saldo_creditos
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             {$creditosJoin}
             WHERE {$where}
             ORDER BY a.nome"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['escola_id'] = (int) ($escola['id'] ?? 0);
            $row['escola_nome'] = (string) ($escola['nome'] ?? '');
            $row['escola_slug'] = (string) ($escola['slug'] ?? '');
            $row['creditos_disponiveis'] = $temCarteira;
        }
        unset($row);
        return $rows;
    }

    private function tenantWalletDisponivel(PDO $pdo): bool
    {
        return (bool) $pdo->query("SHOW TABLES LIKE 'carteira_usuarios'")->fetchColumn()
            && (bool) $pdo->query("SHOW TABLES LIKE 'carteira_movimentacoes'")->fetchColumn();
    }

    private function buildVoltarUrl(): string
    {
        $allowed = ['nome', 'turma', 'creditos_ordem', 'page'];
        $params = [];
        if (isset($_POST['filtro_escola_id']) && (int) $_POST['filtro_escola_id'] > 0) {
            $params['escola_id'] = (int) $_POST['filtro_escola_id'];
        }
        foreach ($allowed as $key) {
            if (isset($_POST[$key]) && trim((string) $_POST[$key]) !== '') {
                $params[$key] = trim((string) $_POST[$key]);
            }
        }
        return URL . '/master/creditos/alunos' . (!empty($params) ? '?' . http_build_query($params) : '');
    }

    private function ensureTenantWalletColumns(PDO $pdo): void
    {
        $saldoEscola = $pdo->query("SHOW COLUMNS FROM carteira_usuarios LIKE 'saldo_escola'")->fetch(PDO::FETCH_ASSOC);
        if (!$saldoEscola) {
            $pdo->exec("ALTER TABLE carteira_usuarios ADD COLUMN saldo_escola DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER saldo");
            $pdo->exec("UPDATE carteira_usuarios SET saldo_escola = COALESCE(saldo, 0)");
        }

        $saldoComprado = $pdo->query("SHOW COLUMNS FROM carteira_usuarios LIKE 'saldo_comprado'")->fetch(PDO::FETCH_ASSOC);
        if (!$saldoComprado) {
            $pdo->exec("ALTER TABLE carteira_usuarios ADD COLUMN saldo_comprado DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER saldo_escola");
        }

        $saldoOrigem = $pdo->query("SHOW COLUMNS FROM carteira_movimentacoes LIKE 'saldo_origem'")->fetch(PDO::FETCH_ASSOC);
        if (!$saldoOrigem) {
            $pdo->exec("ALTER TABLE carteira_movimentacoes ADD COLUMN saldo_origem ENUM('escola','comprado','misto') NULL DEFAULT NULL AFTER tipo");
            $pdo->exec(
                "UPDATE carteira_movimentacoes
                 SET saldo_origem = CASE
                    WHEN tipo IN ('compra', 'recarga_plano') THEN 'comprado'
                    WHEN tipo IN ('recarga_mensal', 'recarga_inicial', 'cortesia') THEN 'escola'
                    WHEN tipo IN ('consumo', 'estorno') THEN 'misto'
                    ELSE NULL
                 END
                 WHERE saldo_origem IS NULL"
            );
        }

        $observacao = $pdo->query("SHOW COLUMNS FROM carteira_movimentacoes LIKE 'observacao'")->fetch(PDO::FETCH_ASSOC);
        if (!$observacao) {
            $pdo->exec("ALTER TABLE carteira_movimentacoes ADD COLUMN observacao TEXT NULL AFTER referencia_id");
        }
    }

    private function creditarCortesiaUsuario(PDO $pdo, string $userType, int $userId, float $valor, string $observacao): void
    {
        $valor = CreditosDecimalHelper::fromScalar($valor, 0.0);
        if ($valor <= 0) {
            return;
        }
        $this->ensureTenantWalletColumns($pdo);
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO carteira_usuarios (user_type, user_id, saldo, saldo_escola, saldo_comprado)
                 VALUES (?, ?, ?, ?, 0)
                 ON DUPLICATE KEY UPDATE
                    saldo = saldo + VALUES(saldo_escola),
                    saldo_escola = saldo_escola + VALUES(saldo_escola),
                    updated_at = NOW()"
            )->execute([$userType, $userId, $valor, $valor]);
            $pdo->prepare(
                "INSERT INTO carteira_movimentacoes
                    (user_type, user_id, tipo, saldo_origem, valor, modulo_key, referencia_id, observacao)
                 VALUES (?, ?, 'cortesia', 'escola', ?, NULL, NULL, ?)"
            )->execute([$userType, $userId, $valor, $observacao]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

}
