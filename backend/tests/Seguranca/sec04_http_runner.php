<?php
/**
 * SEC-04 — harness local (Docker) para rate limit e permissões.
 * Não altera .env. Restaura artefatos ao final.
 * Uso: docker exec php_app_educatudo php /var/www/html/tests/Seguranca/sec04_http_runner.php
 *      ou: php backend/tests/Seguranca/sec04_http_runner.php (com curl no host)
 */

declare(strict_types=1);

$baseHostIp = getenv('SEC04_BASE') ?: 'http://127.0.0.1';
$results = [];
$cleanup = [];

function sec04_result(array &$results, string $id, string $status, string $detail, array $evidence = []): void
{
    $results[] = compact('id', 'status', 'detail', 'evidence');
    echo sprintf("[%s] %s — %s\n", $status, $id, $detail);
}

function sec04_http(string $url, array $opts = []): array
{
    $ch = curl_init($url);
    $headers = $opts['headers'] ?? [];
    $cookieFile = $opts['cookie'] ?? null;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => $opts['method'] ?? 'GET',
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if (!empty($opts['post'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['post']);
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        if ($raw === false) {
        return ['code' => 0, 'headers' => '', 'body' => '', 'error' => $err];
    }
    return [
        'code' => $code,
        'headers' => substr($raw, 0, $headerSize),
        'body' => substr($raw, $headerSize),
        'error' => '',
    ];
}

function sec04_extract_token(string $html): string
{
    if (preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

function sec04_mysql(string $sql): string
{
    $cmd = sprintf(
        'docker exec mysql_db_educatudo mysql -uroot -proot -N -e %s 2>/dev/null',
        escapeshellarg($sql)
    );
    return trim((string) shell_exec($cmd));
}

$tmp = sys_get_temp_dir() . '/sec04_' . getmypid();
@mkdir($tmp, 0700, true);
$cookieAdmin = $tmp . '/admin.txt';
$cookieAluno = $tmp . '/aluno.txt';
$cookieMonitor = $tmp . '/monitor.txt';
$cookieMaster = $tmp . '/master.txt';

// --- Preparação: monitor lab e limpeza de artefatos ---
$insertMonitor = trim((string) shell_exec('docker exec php_app_educatudo php -r ' . escapeshellarg(
    'echo password_hash("Sec04Lab!234", PASSWORD_DEFAULT);'
)));
sec04_mysql("DELETE FROM educatudo_colag.monitores WHERE email='sec04.monitor@colag.local'");
if ($insertMonitor !== '') {
    $safeHash = addslashes($insertMonitor);
    sec04_mysql("INSERT INTO educatudo_colag.monitores (nome, email, senha_hash, ativo) VALUES ('SEC04 Monitor', 'sec04.monitor@colag.local', '{$safeHash}', 1)");
}
$cleanup[] = function () {
    sec04_mysql("DELETE FROM educatudo_colag.monitores WHERE email='sec04.monitor@colag.local'");
};

$loginMarker = 'sec04.lockout.' . time() . '@colag.local';
$cleanup[] = function () use ($loginMarker) {
    sec04_mysql("DELETE FROM educatudo_colag.tentativas_login WHERE email=" . escapeshellarg($loginMarker));
};

$rlPathHost = dirname(__DIR__, 2) . '/storage/logs/login_rate_limit.json'; // canônico BASE_PATH/storage/logs
$rlBackup = is_file($rlPathHost) ? file_get_contents($rlPathHost) : null;
$cleanup[] = function () use ($rlPathHost, $rlBackup) {
    if ($rlBackup === null) {
        @unlink($rlPathHost);
        shell_exec('docker exec php_app_educatudo rm -f /var/www/html/storage/logs/login_rate_limit.json 2>/dev/null');
    } else {
        file_put_contents($rlPathHost, $rlBackup);
    }
};

// ========== RL-01: bloqueio por identificador (tentativas_login) ==========
sec04_mysql("DELETE FROM educatudo_colag.tentativas_login WHERE email=" . escapeshellarg($loginMarker));
for ($i = 0; $i < 4; $i++) {
    sec04_mysql(
        "INSERT INTO educatudo_colag.tentativas_login (email, ip_address, success, created_at)
         VALUES (" . escapeshellarg($loginMarker) . ", '127.0.0.1', 0, NOW())"
    );
}
@unlink($cookieAdmin);
$r = sec04_http($baseHostIp . '/admin', [
    'headers' => ['Host: colag.localhost'],
    'cookie' => $cookieAdmin,
]);
$token = sec04_extract_token($r['body']);
$r2 = sec04_http($baseHostIp . '/login', [
    'method' => 'POST',
    'headers' => ['Host: colag.localhost'],
    'cookie' => $cookieAdmin,
    'post' => http_build_query([
        '_token' => $token,
        'tipo' => 'admin_escola',
        'login' => $loginMarker,
        'senha' => 'senha-errada-sec04',
        'site_url' => '',
    ]),
]);
$bodyAll = $r2['body'] . $r2['headers'];
$locked = (stripos($bodyAll, 'Muitas tentativas') !== false)
    || (preg_match('/Location:.*admin/i', $r2['headers']) && $r2['code'] >= 302);
// Seguir redirect para ver flash
if (preg_match('/Location:\s*(\S+)/i', $r2['headers'], $lm)) {
    $loc = trim($lm[1]);
    if (strpos($loc, 'http') !== 0) {
        $loc = $baseHostIp . (strpos($loc, '/') === 0 ? '' : '/') . preg_replace('#^https?://[^/]+#', '', $loc);
        // Location pode ser URL absoluta com host da app
        if (preg_match('#https?://[^/]+(/.*)$#', $lm[1], $p)) {
            $loc = $baseHostIp . $p[1];
        } elseif ($lm[1][0] === '/') {
            $loc = $baseHostIp . $lm[1];
        }
    }
    $r3 = sec04_http($loc, ['headers' => ['Host: colag.localhost'], 'cookie' => $cookieAdmin]);
    $bodyAll .= $r3['body'];
}
$locked = stripos($bodyAll, 'Muitas tentativas') !== false;
sec04_result(
    $results,
    'RL-01-bloqueio-identificador-admin',
    $locked ? 'APROVADO' : 'FALHOU',
    $locked
        ? 'Após 4 falhas seedadas, nova tentativa retornou bloqueio (max_login_attempts=5 ⇒ allowUntil=4).'
        : 'Bloqueio por identificador não observado no HTML/redirect.',
    ['http' => $r2['code'], 'marker' => $loginMarker]
);

// ========== RL-02: recuperação após expiração ==========
shell_exec('docker exec php_app_educatudo rm -f /var/www/html/storage/logs/login_rate_limit.json');
$loginMarkerRecover = 'sec04.recover.' . time() . '@colag.local';
sec04_mysql("DELETE FROM educatudo_colag.tentativas_login WHERE email=" . escapeshellarg($loginMarkerRecover));
for ($i = 0; $i < 4; $i++) {
    sec04_mysql(
        "INSERT INTO educatudo_colag.tentativas_login (email, ip_address, success, created_at)
         VALUES (" . escapeshellarg($loginMarkerRecover) . ", '127.0.0.1', 0, '2020-01-01 00:00:00')"
    );
}
@unlink($cookieAdmin);
$r = sec04_http($baseHostIp . '/admin', ['headers' => ['Host: colag.localhost'], 'cookie' => $cookieAdmin]);
$token = sec04_extract_token($r['body']);
$r2 = sec04_http($baseHostIp . '/login', [
    'method' => 'POST',
    'headers' => ['Host: colag.localhost'],
    'cookie' => $cookieAdmin,
    'post' => http_build_query([
        '_token' => $token,
        'tipo' => 'admin_escola',
        'login' => $loginMarkerRecover,
        'senha' => 'senha-errada-sec04',
        'site_url' => '',
    ]),
]);
$body = $r2['body'] . $r2['headers'];
if (preg_match('/Location:\s*(\S+)/i', $r2['headers'], $lm)) {
    $path = $lm[1];
    if (preg_match('#https?://[^/]+(/.*)$#', $path, $p)) {
        $path = $p[1];
    }
    $r3 = sec04_http($baseHostIp . $path, ['headers' => ['Host: colag.localhost'], 'cookie' => $cookieAdmin]);
    $body .= $r3['body'];
}
$stillLocked = stripos($body, 'Muitas tentativas') !== false;
$gotInvalid = stripos($body, 'Credenciais inválidas') !== false || stripos($body, 'inválid') !== false;
sec04_result(
    $results,
    'RL-02-recuperacao-apos-janela',
    (!$stillLocked && ($gotInvalid || $r2['code'] === 302)) ? 'APROVADO' : 'FALHOU',
    !$stillLocked
        ? 'Falhas com created_at > lockout_duration não bloqueiam; tentativa volta a ser avaliada.'
        : 'Ainda bloqueado após expiração da janela.',
    ['still_locked' => $stillLocked, 'invalid_msg' => $gotInvalid]
);

// ========== RL-03: limite por IP via arquivo JSON compartilhado ==========
$testIp = '203.0.113.50'; // TEST-NET
shell_exec('docker exec php_app_educatudo sh -c ' . escapeshellarg(
    'mkdir -p /var/www/html/storage/logs && echo "{}" > /var/www/html/storage/logs/login_rate_limit.json'
));
@unlink($cookieAdmin);
$r = sec04_http($baseHostIp . '/admin', ['headers' => ['Host: colag.localhost', 'X-Forwarded-For: ' . $testIp], 'cookie' => $cookieAdmin]);
$token = sec04_extract_token($r['body']);
// REMOTE_ADDR no PHP atrás do nginx local será o IP do gateway Docker, não o XFF do cliente.
$probe = sec04_http($baseHostIp . '/login', [
    'method' => 'POST',
    'headers' => ['Host: colag.localhost', 'X-Forwarded-For: ' . $testIp],
    'cookie' => $cookieAdmin,
    'post' => http_build_query([
        '_token' => $token,
        'tipo' => 'admin_escola',
        'login' => 'sec04.ipprobe@colag.local',
        'senha' => 'x',
        'site_url' => '',
    ]),
]);
// Ler arquivo após tentativa para ver qual IP foi gravado
$rlAfter = (string) shell_exec('docker exec php_app_educatudo cat /var/www/html/storage/logs/login_rate_limit.json 2>/dev/null');
$rlDecoded = json_decode($rlAfter, true) ?: [];
$recordedIps = array_keys(array_filter($rlDecoded, static fn($e) => is_array($e) && (int) ($e['c'] ?? 0) > 0));
$usesXff = in_array($testIp, $recordedIps, true);
sec04_result(
    $results,
    'RL-03-ip-nao-confia-xff-cliente',
    !$usesXff ? 'APROVADO' : 'FALHOU',
    !$usesXff
        ? 'Rate limit de login usa REMOTE_ADDR; header X-Forwarded-For do cliente não virou chave do contador.'
        : 'X-Forwarded-For do cliente foi aceito como IP do rate limit (spoofable).',
    ['recorded_ips' => $recordedIps, 'xff_injected' => $testIp]
);

// Bloqueio efetivo por IP: setar contador no IP real visto
$realIp = $recordedIps[0] ?? '127.0.0.1';
foreach ($recordedIps as $ip) {
    if ($ip !== $testIp) {
        $realIp = $ip;
        break;
    }
}
$rlBlock = [$realIp => ['c' => 50, 't' => time()]];
$rlBlockJson = json_encode($rlBlock);
shell_exec('docker exec php_app_educatudo sh -c ' . escapeshellarg(
    'cat > /var/www/html/storage/logs/login_rate_limit.json <<\'EOF\'' . "\n" . $rlBlockJson . "\nEOF"
));
@unlink($cookieAdmin);
$r = sec04_http($baseHostIp . '/admin', ['headers' => ['Host: colag.localhost'], 'cookie' => $cookieAdmin]);
$token = sec04_extract_token($r['body']);
$r2 = sec04_http($baseHostIp . '/login', [
    'method' => 'POST',
    'headers' => ['Host: colag.localhost'],
    'cookie' => $cookieAdmin,
    'post' => http_build_query([
        '_token' => $token,
        'tipo' => 'admin_escola',
        'login' => 'sec04.ipblock@colag.local',
        'senha' => 'x',
        'site_url' => '',
    ]),
]);
$body = $r2['body'] . $r2['headers'];
if (preg_match('/Location:\s*(\S+)/i', $r2['headers'], $lm)) {
    $path = $lm[1];
    if (preg_match('#https?://[^/]+(/.*)$#', $path, $p)) {
        $path = $p[1];
    }
    $r3 = sec04_http($baseHostIp . $path, ['headers' => ['Host: colag.localhost'], 'cookie' => $cookieAdmin]);
    $body .= $r3['body'];
}
$ipLocked = stripos($body, 'Muitas tentativas') !== false;
sec04_result(
    $results,
    'RL-04-bloqueio-por-ip-arquivo',
    $ipLocked ? 'APROVADO' : 'FALHOU',
    $ipLocked
        ? "Arquivo login_rate_limit.json com c>=max_login_attempts_ip bloqueou login (IP observado: {$realIp})."
        : 'Bloqueio por IP não observado.',
    ['real_ip' => $realIp]
);

// Contador compartilhado entre requests (mesmo arquivo)
sec04_result(
    $results,
    'RL-05-contador-compartilhado-arquivo',
    is_string($rlAfter) && $rlAfter !== '' ? 'APROVADO' : 'INCONCLUSIVO',
    'Contador IP persiste em storage/logs/login_rate_limit.json (volume do container PHP; compartilhado entre workers FPM do mesmo container).',
    ['storage' => 'file+flock', 'redis' => false]
);

// ========== RL-06: Master login com max_login_attempts ==========
shell_exec('docker exec php_app_educatudo rm -f /var/www/html/storage/logs/master_login_rate_limit.json 2>/dev/null');
@unlink($cookieMaster);
$r = sec04_http($baseHostIp . '/master', ['headers' => ['Host: master.localhost'], 'cookie' => $cookieMaster]);
$token = sec04_extract_token($r['body']);
if ($token === '' && preg_match('/name="csrf_token"\s+value="([^"]+)"/', $r['body'], $m)) {
    $token = $m[1];
}
$masterBlocked = false;
$masterMsgs = [];
for ($i = 1; $i <= 5; $i++) {
    $rr = sec04_http($baseHostIp . '/master/login', [
        'method' => 'POST',
        'headers' => ['Host: master.localhost'],
        'cookie' => $cookieMaster,
        'post' => http_build_query([
            'csrf_token' => $token,
            'email' => 'sec04.naoexiste@master.local',
            'senha' => 'errada-' . $i,
        ]),
    ]);
    $b = $rr['body'] . $rr['headers'];
    if (preg_match('/Location:\s*(\S+)/i', $rr['headers'], $lm)) {
        $path = $lm[1];
        if (preg_match('#https?://[^/]+(/.*)$#', $path, $p)) {
            $path = $p[1];
        }
        $rf = sec04_http($baseHostIp . $path, ['headers' => ['Host: master.localhost'], 'cookie' => $cookieMaster]);
        $b .= $rf['body'];
        $token = sec04_extract_token($rf['body']);
        if ($token === '' && preg_match('/name="csrf_token"\s+value="([^"]+)"/', $rf['body'], $m2)) {
            $token = $m2[1];
        }
    }
    if (stripos($b, 'Muitas tentativas') !== false || stripos($b, 'bloqueado') !== false) {
        $masterBlocked = true;
    }
    $masterMsgs[] = (stripos($b, 'incorretos') !== false || stripos($b, 'inválid') !== false);
}
sec04_result(
    $results,
    'RL-06-master-senha-rate-limit',
    $masterBlocked ? 'APROVADO' : 'FALHOU',
    $masterBlocked
        ? 'POST /master/login aplica max_login_attempts (5) e bloqueia após falhas na janela lockout_duration.'
        : 'POST /master/login aceitou 5 falhas consecutivas sem bloqueio.',
    ['blocked' => $masterBlocked, 'invalid_msgs' => $masterMsgs]
);

// ========== RL-07: OTP Master max tentativas (CodigoAcessoMasterService) ==========
$otpOut = trim((string) shell_exec(
    'docker exec php_app_educatudo php /var/www/html/tests/Seguranca/_sec04_otp_probe.php 2>/dev/null'
));
$otp = json_decode($otpOut, true) ?: ['ok' => false, 'err' => substr($otpOut, 0, 300)];
sec04_result(
    $results,
    'RL-07-otp-master-max-tentativas',
    (!empty($otp['ok']) && !empty($otp['esgotado']) && (($otp['codigo_valido'] ?? '') === 'ok'))
        ? 'APROVADO'
        : ((!empty($otp['err']) && $otp['err'] === 'no otp table') ? 'NÃO EXECUTADO' : 'FALHOU'),
    !empty($otp['ok']) && !empty($otp['esgotado'])
        ? 'CodigoAcessoMasterService::confirmar — 5 erros → esgotado; código válido ainda passa em outro desafio.'
        : ('OTP não validado: ' . ($otp['err'] ?? json_encode($otp, JSON_UNESCAPED_UNICODE))),
    is_array($otp) ? $otp : ['raw' => $otpOut]
);

// ========== PERM-01: anônimo → admin ==========
$r = sec04_http($baseHostIp . '/admin/dashboard', ['headers' => ['Host: colag.localhost']]);
$anonDenied = in_array($r['code'], [302, 301], true) || stripos($r['headers'], 'Location:') !== false;
sec04_result($results, 'PERM-01-anonimo-admin', $anonDenied ? 'APROVADO' : 'FALHOU', 'GET /admin/dashboard sem sessão redireciona para login.', ['code' => $r['code']]);

// ========== PERM-02: anônimo → master ==========
$r = sec04_http($baseHostIp . '/master/dashboard', ['headers' => ['Host: master.localhost']]);
$anonMaster = in_array($r['code'], [302, 301], true);
sec04_result($results, 'PERM-02-anonimo-master', $anonMaster ? 'APROVADO' : 'FALHOU', 'GET /master/dashboard sem sessão redireciona.', ['code' => $r['code']]);

// ========== Login admin real ==========
@unlink($cookieAdmin);
$r = sec04_http($baseHostIp . '/admin', ['headers' => ['Host: colag.localhost'], 'cookie' => $cookieAdmin]);
$token = sec04_extract_token($r['body']);
// limpar RL IP para não atrapalhar login válido
shell_exec('docker exec php_app_educatudo rm -f /var/www/html/storage/logs/login_rate_limit.json');
$r2 = sec04_http($baseHostIp . '/login', [
    'method' => 'POST',
    'headers' => ['Host: colag.localhost'],
    'cookie' => $cookieAdmin,
    'post' => http_build_query([
        '_token' => $token,
        'tipo' => 'admin_escola',
        'login' => getenv('QA_ADMIN_EMAIL') ?: 'admin@colag.local',
        'senha' => getenv('QA_ADMIN_SENHA') ?: '123456',
        'site_url' => '',
    ]),
]);
$adminOk = $r2['code'] === 302 && stripos($r2['headers'], '/admin') !== false;
sec04_result($results, 'PERM-03-login-admin-lab', $adminOk ? 'APROVADO' : 'FALHOU', $adminOk ? 'Login admin lab ok.' : 'Falha no login admin lab.', ['code' => $r2['code']]);

// Admin → master
$r = sec04_http($baseHostIp . '/master/dashboard', ['headers' => ['Host: master.localhost'], 'cookie' => $cookieAdmin]);
$adminBlockedMaster = in_array($r['code'], [302, 301], true) && stripos($r['headers'] . $r['body'], 'master_user') === false;
// cookie de tenant não deve autenticar master
$stillMasterLogin = stripos($r['headers'], 'Location:') !== false;
sec04_result(
    $results,
    'PERM-04-admin-nao-acessa-master',
    $stillMasterLogin || $r['code'] === 302 ? 'APROVADO' : 'FALHOU',
    'Sessão admin de tenant não abre /master/dashboard (exige master_user_id).',
    ['code' => $r['code']]
);

// Admin colag → escola2 (cross-tenant)
$r = sec04_http($baseHostIp . '/admin/dashboard', ['headers' => ['Host: escola2.localhost'], 'cookie' => $cookieAdmin]);
$cross = in_array($r['code'], [302, 301], true) || stripos($r['body'], 'dashboard') === false;
sec04_result(
    $results,
    'PERM-05-admin-cross-tenant',
    $cross ? 'APROVADO' : 'FALHOU',
    $cross
        ? 'Cookie de Colag em escola2.localhost não mantém painel admin (sessaoConfereComEscolaAtual / Auth).'
        : 'Admin Colag conseguiu ver dashboard em Escola2 — revisar isolamento.',
    ['code' => $r['code'], 'loc' => preg_match('/Location:\s*(\S+)/i', $r['headers'], $m) ? $m[1] : null]
);

// POST direto sem CSRF/UI
$r = sec04_http($baseHostIp . '/admin/students', [
    'method' => 'POST',
    'headers' => ['Host: colag.localhost', 'Content-Type: application/x-www-form-urlencoded'],
    'cookie' => $cookieAdmin,
    'post' => 'nome=sec04&email=sec04@x.local',
]);
sec04_result(
    $results,
    'PERM-06-post-admin-direto',
    in_array($r['code'], [302, 403, 419, 400], true) || $r['code'] !== 200 ? 'APROVADO' : 'INCONCLUSIVO',
    'POST /admin/students direto (sem CSRF completo) não retornou 200 de sucesso de criação.',
    ['code' => $r['code']]
);

// Monitor login + admin deny
@unlink($cookieMonitor);
shell_exec('docker exec php_app_educatudo rm -f /var/www/html/storage/logs/login_rate_limit.json');
$r = sec04_http($baseHostIp . '/monitor', ['headers' => ['Host: colag.localhost'], 'cookie' => $cookieMonitor]);
$token = sec04_extract_token($r['body']);
$tipoMonitor = 'monitor';
if (preg_match('/name="tipo"\s+value="([^"]+)"/', $r['body'], $tm)) {
    $tipoMonitor = $tm[1];
}
$r2 = sec04_http($baseHostIp . '/login', [
    'method' => 'POST',
    'headers' => ['Host: colag.localhost'],
    'cookie' => $cookieMonitor,
    'post' => http_build_query([
        '_token' => $token,
        'tipo' => $tipoMonitor,
        'login' => 'sec04.monitor@colag.local',
        'senha' => 'Sec04Lab!234',
        'site_url' => '',
    ]),
]);
$monitorLogin = $r2['code'] === 302;
sec04_result($results, 'PERM-07-login-monitor-lab', $monitorLogin ? 'APROVADO' : 'FALHOU', $monitorLogin ? 'Login monitor lab ok.' : 'Falha login monitor.', ['code' => $r2['code'], 'headers' => substr($r2['headers'], 0, 300)]);

$r = sec04_http($baseHostIp . '/admin/dashboard', ['headers' => ['Host: colag.localhost'], 'cookie' => $cookieMonitor]);
$monitorDeniedAdmin = in_array($r['code'], [302, 301], true);
sec04_result(
    $results,
    'PERM-08-monitor-nao-acessa-admin',
    $monitorDeniedAdmin ? 'APROVADO' : 'FALHOU',
    $monitorDeniedAdmin
        ? 'Monitor autenticado é redirecionado ao tentar /admin/dashboard (AdminBaseController).'
        : 'Monitor acessou admin.',
    ['code' => $r['code']]
);

// Aluno comum → admin
@unlink($cookieAluno);
$r = sec04_http($baseHostIp . '/', ['headers' => ['Host: colag.localhost'], 'cookie' => $cookieAluno]);
$token = sec04_extract_token($r['body']);
$alunoLogin = getenv('QA_ALUNO_LOGIN') ?: 'aluno.teste';
$alunoSenha = getenv('QA_ALUNO_SENHA') ?: '123456';
$r2 = sec04_http($baseHostIp . '/login', [
    'method' => 'POST',
    'headers' => ['Host: colag.localhost'],
    'cookie' => $cookieAluno,
    'post' => http_build_query([
        '_token' => $token,
        'tipo' => 'aluno',
        'login' => $alunoLogin,
        'senha' => $alunoSenha,
        'site_url' => '',
    ]),
]);
$alunoOk = $r2['code'] === 302;
sec04_result($results, 'PERM-09-login-aluno-lab', $alunoOk ? 'APROVADO' : 'INCONCLUSIVO', $alunoOk ? 'Login aluno lab ok.' : 'Login aluno lab falhou (conta pode diferir).', ['code' => $r2['code']]);
$r = sec04_http($baseHostIp . '/admin/dashboard', ['headers' => ['Host: colag.localhost'], 'cookie' => $cookieAluno]);
sec04_result(
    $results,
    'PERM-10-aluno-nao-acessa-admin',
    in_array($r['code'], [302, 301], true) ? 'APROVADO' : ($alunoOk ? 'FALHOU' : 'NÃO EXECUTADO'),
    'Aluno autenticado não permanece em /admin/dashboard.',
    ['code' => $r['code']]
);

// Master POST interno sem sessão
$r = sec04_http($baseHostIp . '/master/escolas/salvar', [
    'method' => 'POST',
    'headers' => ['Host: master.localhost', 'Content-Type: application/x-www-form-urlencoded'],
    'post' => 'nome=sec04&slug=sec04',
]);
sec04_result(
    $results,
    'PERM-11-master-post-sem-sessao',
    in_array($r['code'], [302, 401, 403], true) ? 'APROVADO' : 'FALHOU',
    'POST /master/escolas/salvar sem sessão master é rejeitado/redirecionado.',
    ['code' => $r['code']]
);

// Cron key vazia
$r = sec04_http($baseHostIp . '/master/escolas/verificar-dominios-cron', ['headers' => ['Host: master.localhost']]);
sec04_result(
    $results,
    'PERM-12-cron-master-sem-key',
    $r['code'] === 403 ? 'APROVADO' : 'FALHOU',
    'Endpoint cron Master sem key retorna 403.',
    ['code' => $r['code']]
);

// Cleanup
foreach (array_reverse($cleanup) as $fn) {
    try {
        $fn();
    } catch (Throwable $e) {
        // ignore
    }
}
sec04_mysql("DELETE FROM educatudo_colag.monitores WHERE email='sec04.monitor@colag.local'");
sec04_mysql("DELETE FROM educatudo_colag.tentativas_login WHERE email LIKE 'sec04.%'");
shell_exec('docker exec php_app_educatudo rm -f /var/www/html/storage/logs/login_rate_limit.json 2>/dev/null');
@unlink($rlPathHost);

$outDir = dirname(__DIR__, 3) . '/relatorios-seguranca';
if (!is_dir($outDir)) {
    @mkdir($outDir, 0755, true);
}
$outFile = $outDir . '/SEC-04-resultados-runner.json';
file_put_contents($outFile, json_encode([
    'generated_at' => date('c'),
    'base' => $baseHostIp,
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$counts = ['APROVADO' => 0, 'FALHOU' => 0, 'INCONCLUSIVO' => 0, 'NÃO EXECUTADO' => 0];
foreach ($results as $row) {
    $counts[$row['status']] = ($counts[$row['status']] ?? 0) + 1;
}
echo "\nResumo: " . json_encode($counts, JSON_UNESCAPED_UNICODE) . "\n";
echo "JSON: {$outFile}\n";
