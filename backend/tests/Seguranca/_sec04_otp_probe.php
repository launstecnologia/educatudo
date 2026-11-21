<?php
/**
 * Probe OTP Master: 5 códigos errados → esgotado; código válido em outro desafio → ok.
 * Uso: docker exec php_app_educatudo php /var/www/html/tests/Seguranca/_sec04_otp_probe.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
define('ENV_FILE_PATH', BASE_PATH . '/.env');

require BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/app/Core/Database.php';
require_once BASE_PATH . '/app/Core/PerfLogger.php';
require_once BASE_PATH . '/app/Painel/Master/Services/CodigoAcessoMasterService.php';

use App\Core\Database;
use App\Painel\Master\Services\CodigoAcessoMasterService;

try {
    $pdo = Database::createMasterPdo();
    $db = Database::createFromPdo($pdo);
    Database::setCurrentInstance($db);

    $svc = new CodigoAcessoMasterService($db);
    if (!$svc->tabelaPronta()) {
        echo json_encode(['ok' => false, 'err' => 'no otp table'], JSON_UNESCAPED_UNICODE);
        exit(1);
    }

    $uid = (int) $pdo->query('SELECT id FROM usuarios_master WHERE ativo = 1 ORDER BY id LIMIT 1')->fetchColumn();
    if ($uid < 1) {
        echo json_encode(['ok' => false, 'err' => 'no master user'], JSON_UNESCAPED_UNICODE);
        exit(1);
    }

    $pdo->prepare('DELETE FROM usuarios_master_codigos_acesso WHERE usuario_id = ?')->execute([$uid]);
    $hash = password_hash('654321', PASSWORD_DEFAULT);
    $pdo->prepare(
        'INSERT INTO usuarios_master_codigos_acesso (usuario_id, codigo_hash, expira_em, tentativas)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0)'
    )->execute([$uid, $hash]);
    $codigoId = (int) $pdo->lastInsertId();

    $resultados = [];
    for ($i = 1; $i <= 5; $i++) {
        $resultados[] = $svc->confirmar($uid, $codigoId, '000000');
    }
    $sexto = $svc->confirmar($uid, $codigoId, '000000');
    $tentativas = (int) $pdo->query("SELECT tentativas FROM usuarios_master_codigos_acesso WHERE id = {$codigoId}")->fetchColumn();

    $hashOk = password_hash('112233', PASSWORD_DEFAULT);
    $pdo->prepare(
        'INSERT INTO usuarios_master_codigos_acesso (usuario_id, codigo_hash, expira_em, tentativas)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0)'
    )->execute([$uid, $hashOk]);
    $codigoOkId = (int) $pdo->lastInsertId();
    $ok = $svc->confirmar($uid, $codigoOkId, '112233');

    $pdo->prepare('DELETE FROM usuarios_master_codigos_acesso WHERE usuario_id = ?')->execute([$uid]);

    $esgotadoNaQuinta = ($resultados[4] ?? '') === 'esgotado';
    echo json_encode([
        'ok' => true,
        'usuario_id' => $uid,
        'codigo_id' => $codigoId,
        'resultados' => $resultados,
        'sexto' => $sexto,
        'tentativas' => $tentativas,
        'max' => CodigoAcessoMasterService::MAX_TENTATIVAS,
        'esgotado' => $esgotadoNaQuinta && $sexto === 'esgotado' && $tentativas >= 5,
        'codigo_valido' => $ok,
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}
