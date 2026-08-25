<?php
/**
 * Cron: recarga mensal B2B de TudiCoins (cota aluno/professor/admin/escola) em cada tenant.
 * Usa creditos_mensal_aluno / creditos_mensal_professor / creditos_mensal_admin / creditos_mensal_escola de config_layout.
 * Preserva saldo_comprado; zera/ajusta saldo_escola para o valor mensal (mesmo comportamento da renovação manual do Master).
 *
 * Recomendado: dia 1 de cada mês à 01:00 (America/Sao_Paulo)
 * 0 1 1 * * /usr/bin/php /caminho/projeto/src/cron/tudicoins_recarga_mensal.php >> /caminho/projeto/src/storage/logs/cron_tudicoins_recarga.log 2>&1
 */

$basePath = dirname(__DIR__);
date_default_timezone_set('America/Sao_Paulo');

require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Core/CreditosDecimalHelper.php';
require_once $basePath . '/app/Core/CreditosModuleRegistry.php';

$cronMultiTenantPath = $basePath . '/app/Core/cron_multi_tenant_helper.php';
if (file_exists($cronMultiTenantPath)) {
    require_once $cronMultiTenantPath;
}

function logTudiCoinsRecarga(string $msg, string $basePath): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($basePath . '/storage/logs/cron_tudicoins_recarga.log', $line, FILE_APPEND);
    echo $line;
}

function tudicoinsConfigLayout(PDO $pdo): array
{
    try {
        $rows = $pdo->query('SELECT config_key, config_value FROM config_layout')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $out[(string) $r['config_key']] = (string) ($r['config_value'] ?? '');
    }
    return $out;
}

function tudicoinsEnsureWallet(PDO $pdo): void
{
    try {
        $col = $pdo->query("SHOW COLUMNS FROM carteira_usuarios LIKE 'saldo_escola'")->fetch(PDO::FETCH_ASSOC);
        if (!$col) {
            $pdo->exec("ALTER TABLE carteira_usuarios ADD COLUMN saldo_escola DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER saldo");
            $pdo->exec('UPDATE carteira_usuarios SET saldo_escola = COALESCE(saldo, 0)');
        }
        $col2 = $pdo->query("SHOW COLUMNS FROM carteira_usuarios LIKE 'saldo_comprado'")->fetch(PDO::FETCH_ASSOC);
        if (!$col2) {
            $pdo->exec("ALTER TABLE carteira_usuarios ADD COLUMN saldo_comprado DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER saldo_escola");
        }
        $enumRow = $pdo->query(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'carteira_usuarios'
               AND COLUMN_NAME = 'user_type'
             LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        $colType = strtolower((string) ($enumRow['COLUMN_TYPE'] ?? ''));
        if ($colType !== '' && (strpos($colType, "'escola'") === false || strpos($colType, "'admin'") === false)) {
            $pdo->exec("ALTER TABLE carteira_usuarios MODIFY COLUMN user_type ENUM('aluno','professor','escola','admin') NOT NULL");
            $pdo->exec("ALTER TABLE carteira_movimentacoes MODIFY COLUMN user_type ENUM('aluno','professor','escola','admin') NOT NULL");
        }
    } catch (Throwable $e) {
        // ignore
    }
}

function tudicoinsRenovarTipo(PDO $pdo, string $userType, string $idsSql, float $novoSaldoEscola): int
{
    $novoSaldoEscola = CreditosDecimalHelper::fromScalar($novoSaldoEscola, 0.0);
    if ($novoSaldoEscola <= 0) {
        return 0;
    }
    $ids = $pdo->query($idsSql)->fetchAll(PDO::FETCH_COLUMN);
    $stGet = $pdo->prepare('SELECT saldo_escola, saldo_comprado FROM carteira_usuarios WHERE user_type = ? AND user_id = ?');
    $stUpsert = $pdo->prepare(
        'INSERT INTO carteira_usuarios (user_type, user_id, saldo, saldo_escola, saldo_comprado)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE saldo = VALUES(saldo), saldo_escola = VALUES(saldo_escola), saldo_comprado = VALUES(saldo_comprado), updated_at = NOW()'
    );
    $stMov = $pdo->prepare(
        "INSERT INTO carteira_movimentacoes (user_type, user_id, tipo, saldo_origem, valor, observacao)
         VALUES (?, ?, 'recarga_mensal', 'escola', ?, ?)"
    );
    $n = 0;
    foreach ($ids as $rawId) {
        $uid = (int) $rawId;
        $stGet->execute([$userType, $uid]);
        $row = $stGet->fetch(PDO::FETCH_ASSOC);
        $saldoComprado = CreditosDecimalHelper::fromScalar($row['saldo_comprado'] ?? 0, 0.0);
        $saldoEscolaAtual = CreditosDecimalHelper::fromScalar($row['saldo_escola'] ?? 0, 0.0);
        $delta = round($novoSaldoEscola - $saldoEscolaAtual, CreditosDecimalHelper::SCALE);
        $total = round($novoSaldoEscola + $saldoComprado, CreditosDecimalHelper::SCALE);
        $stUpsert->execute([$userType, $uid, $total, $novoSaldoEscola, $saldoComprado]);
        if (abs($delta) > 0.00009) {
            $obs = 'Recarga mensal automática B2B para ' . CreditosDecimalHelper::formatDisplay($novoSaldoEscola) . ' TudiCoins.';
            $stMov->execute([$userType, $uid, $delta, $obs]);
        }
        $n++;
    }
    return $n;
}

$runner = function (?int $escolaId) use ($basePath): void {
    if ($escolaId === null || $escolaId < 1) {
        return;
    }
    try {
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        $cfg = tudicoinsConfigLayout($pdo);
        if (($cfg['creditos_habilitado'] ?? '0') !== '1') {
            logTudiCoinsRecarga("escola_id={$escolaId} skip (TudiCoins off)", $basePath);
            return;
        }
        $mensalAluno = (float) ($cfg['creditos_mensal_aluno'] ?? 0);
        $mensalProf = (float) ($cfg['creditos_mensal_professor'] ?? 0);
        $mensalAdmin = (float) ($cfg['creditos_mensal_admin'] ?? 0);
        $mensalEscola = (float) ($cfg['creditos_mensal_escola'] ?? 0);
        if ($mensalAluno <= 0 && $mensalProf <= 0 && $mensalAdmin <= 0 && $mensalEscola <= 0) {
            logTudiCoinsRecarga("escola_id={$escolaId} skip (cota 0)", $basePath);
            return;
        }
        tudicoinsEnsureWallet($pdo);
        $pdo->beginTransaction();
        $modoPool = ($cfg['creditos_modo_pool_escola'] ?? '0') === '1';
        $sqlAdmins = "SELECT id FROM usuarios WHERE ativo = 1 AND tipo IN ('admin','admin_escola')";
        if ($modoPool) {
            // Pool: cota B2B vai para a carteira institucional (soma aluno+professor+admin ativos × cota + cota escola).
            $qAlunos = (int) $pdo->query("SELECT COUNT(*) FROM alunos WHERE ativo = 1")->fetchColumn();
            $qProfs = (int) $pdo->query("SELECT COUNT(*) FROM professores WHERE ativo = 1")->fetchColumn();
            $qAdmins = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1 AND tipo IN ('admin','admin_escola')")->fetchColumn();
            $cotaPool = round(
                ($qAlunos * $mensalAluno) + ($qProfs * $mensalProf) + ($qAdmins * $mensalAdmin) + $mensalEscola,
                CreditosDecimalHelper::SCALE
            );
            $a = 0;
            $p = 0;
            $d = 0;
            if ($cotaPool > 0) {
                $stGet = $pdo->prepare(
                    "SELECT saldo_escola, saldo_comprado FROM carteira_usuarios
                     WHERE user_type = 'escola' AND user_id = ?"
                );
                $stGet->execute([CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID]);
                $row = $stGet->fetch(PDO::FETCH_ASSOC);
                $saldoComprado = CreditosDecimalHelper::fromScalar($row['saldo_comprado'] ?? 0, 0.0);
                $saldoEscolaAtual = CreditosDecimalHelper::fromScalar($row['saldo_escola'] ?? 0, 0.0);
                $delta = round($cotaPool - $saldoEscolaAtual, CreditosDecimalHelper::SCALE);
                $total = round($cotaPool + $saldoComprado, CreditosDecimalHelper::SCALE);
                $pdo->prepare(
                    "INSERT INTO carteira_usuarios (user_type, user_id, saldo, saldo_escola, saldo_comprado)
                     VALUES ('escola', ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE saldo = VALUES(saldo), saldo_escola = VALUES(saldo_escola),
                        saldo_comprado = VALUES(saldo_comprado), updated_at = NOW()"
                )->execute([CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID, $total, $cotaPool, $saldoComprado]);
                if (abs($delta) > 0.00009) {
                    $obs = 'Recarga mensal automática B2B (pool) para '
                        . CreditosDecimalHelper::formatDisplay($cotaPool) . ' TudiCoins.';
                    $pdo->prepare(
                        "INSERT INTO carteira_movimentacoes (user_type, user_id, tipo, saldo_origem, valor, observacao)
                         VALUES ('escola', ?, 'recarga_mensal', 'escola', ?, ?)"
                    )->execute([CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID, $delta, $obs]);
                }
                $a = $qAlunos;
                $p = $qProfs;
                $d = $qAdmins;
            }
            $pdo->commit();
            logTudiCoinsRecarga(
                "escola_id={$escolaId} pool=1 cota={$cotaPool} alunos={$a} professores={$p} admins={$d} mensal_escola={$mensalEscola}",
                $basePath
            );
        } else {
            $a = tudicoinsRenovarTipo($pdo, 'aluno', 'SELECT id FROM alunos WHERE ativo = 1', $mensalAluno);
            $p = tudicoinsRenovarTipo($pdo, 'professor', 'SELECT id FROM professores WHERE ativo = 1', $mensalProf);
            $d = tudicoinsRenovarTipo($pdo, 'admin', $sqlAdmins, $mensalAdmin);
            $e = 0;
            if ($mensalEscola > 0) {
                $stGet = $pdo->prepare(
                    "SELECT saldo_escola, saldo_comprado FROM carteira_usuarios
                     WHERE user_type = 'escola' AND user_id = ?"
                );
                $stGet->execute([CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID]);
                $row = $stGet->fetch(PDO::FETCH_ASSOC);
                $saldoComprado = CreditosDecimalHelper::fromScalar($row['saldo_comprado'] ?? 0, 0.0);
                $saldoEscolaAtual = CreditosDecimalHelper::fromScalar($row['saldo_escola'] ?? 0, 0.0);
                $delta = round($mensalEscola - $saldoEscolaAtual, CreditosDecimalHelper::SCALE);
                $total = round($mensalEscola + $saldoComprado, CreditosDecimalHelper::SCALE);
                $pdo->prepare(
                    "INSERT INTO carteira_usuarios (user_type, user_id, saldo, saldo_escola, saldo_comprado)
                     VALUES ('escola', ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE saldo = VALUES(saldo), saldo_escola = VALUES(saldo_escola),
                        saldo_comprado = VALUES(saldo_comprado), updated_at = NOW()"
                )->execute([CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID, $total, $mensalEscola, $saldoComprado]);
                if (abs($delta) > 0.00009) {
                    $obs = 'Recarga mensal automática B2B (escola) para '
                        . CreditosDecimalHelper::formatDisplay($mensalEscola) . ' TudiCoins.';
                    $pdo->prepare(
                        "INSERT INTO carteira_movimentacoes (user_type, user_id, tipo, saldo_origem, valor, observacao)
                         VALUES ('escola', ?, 'recarga_mensal', 'escola', ?, ?)"
                    )->execute([CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID, $delta, $obs]);
                }
                $e = 1;
            }
            $pdo->commit();
            logTudiCoinsRecarga("escola_id={$escolaId} alunos={$a} professores={$p} admins={$d} escola={$e}", $basePath);
        }
    } catch (Throwable $e) {
        try {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable $e2) {
        }
        logTudiCoinsRecarga("Erro escola_id={$escolaId}: " . $e->getMessage(), $basePath);
    }
};

logTudiCoinsRecarga('Início recarga mensal TudiCoins', $basePath);
if (class_exists('CronMultiTenantHelper')) {
    CronMultiTenantHelper::run($runner, true);
} else {
    logTudiCoinsRecarga('CronMultiTenantHelper ausente', $basePath);
}
logTudiCoinsRecarga('Fim recarga mensal TudiCoins', $basePath);
