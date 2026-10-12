<?php
declare(strict_types=1);

$base = 'http://colag.localhost';
$cookieFile = tempnam(sys_get_temp_dir(), 'ck');

function req(string $method, string $url, ?string $cookieFile, array $opts = []): array
{
    $ch = curl_init($url);
    $headers = ['Host: colag.localhost'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25,
    ]);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if (!empty($opts['body'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $parts = explode("\r\n\r\n", (string) $raw, 2);
    if (count($parts) < 2) {
        $parts = explode("\n\n", (string) $raw, 2);
    }
    $hdr = $parts[0] ?? '';
    $body = $parts[1] ?? '';
    // handle multiple header blocks (HTTP/1.1 100 Continue etc)
    while (preg_match('/^HTTP\/\d/i', $body)) {
        $more = explode("\r\n\r\n", $body, 2);
        if (count($more) < 2) {
            break;
        }
        $hdr .= "\n" . $more[0];
        $body = $more[1];
    }
    $loc = null;
    if (preg_match('/^Location:\s*(.+)$/mi', $hdr, $m)) {
        $loc = trim($m[1]);
    }
    return compact('code', 'hdr', 'body', 'loc', 'err');
}

function extractCsrf(string $html): array
{
    $out = [];
    if (preg_match_all('/<input[^>]+>/i', $html, $inputs)) {
        foreach ($inputs[0] as $inp) {
            if (!preg_match('/name=["\']([^"\']+)["\']/i', $inp, $nm)) {
                continue;
            }
            $name = $nm[1];
            $val = '';
            if (preg_match('/value=["\']([^"\']*)["\']/i', $inp, $vm)) {
                $val = $vm[1];
            }
            if (stripos($name, 'csrf') !== false || $name === '_token' || $name === 'token') {
                $out[$name] = $val;
            }
        }
    }
    return $out;
}

function titleOf(string $html): string
{
    return preg_match('/<title>([^<]+)/i', $html, $m) ? trim($m[1]) : '';
}

function hasFatal(string $html): ?string
{
    if (preg_match('/Fatal error|Parse error|Uncaught (?:Error|Exception)|Class .+ not found|Failed opening required/i', $html, $m)) {
        if (preg_match('/.{0,40}' . preg_quote($m[0], '/') . '.{0,120}/i', $html, $s)) {
            return trim(html_entity_decode(strip_tags($s[0])));
        }
        return $m[0];
    }
    return null;
}

$ok = 0;
$fail = 0;
function pass(string $l): void
{
    global $ok;
    echo "OK   $l\n";
    $ok++;
}
function bad(string $l, string $d = ''): void
{
    global $fail;
    echo "FAIL $l" . ($d !== '' ? " — $d" : '') . "\n";
    $fail++;
}

// 1) Login page
$r = req('GET', "$base/login", $cookieFile);
$csrf = extractCsrf($r['body']);
echo "CSRF fields: " . json_encode($csrf) . "\n";
if ($r['code'] !== 200) {
    bad('login page', (string) $r['code']);
} else {
    pass('login page');
}

// 2) Login admin
// Prefer admin login page
$rAdminPage = req('GET', "$base/admin/login", $cookieFile);
if ($rAdminPage['code'] === 200 && extractCsrf($rAdminPage['body'])) {
    $csrf = extractCsrf($rAdminPage['body']);
    $loginUrl = "$base/admin/login";
    echo "Using /admin/login\n";
} else {
    $loginUrl = "$base/login";
    echo "Using /login\n";
}
$post = [
    'email' => 'admin@colag.local',
    'password' => '123456',
    'site_url' => '',
];
foreach ($csrf as $k => $v) {
    $post[$k] = $v;
}
// views usam _token
if (!isset($post['_token']) && isset($post['csrf_token'])) {
    $post['_token'] = $post['csrf_token'];
}
$r = req('POST', $loginUrl, $cookieFile, ['body' => http_build_query($post)]);
echo "LOGIN code={$r['code']} loc={$r['loc']}\n";
if ($r['code'] === 302 && $r['loc'] && (str_contains($r['loc'], '/admin') || str_contains($r['loc'], 'dashboard'))) {
    pass('login admin redirect');
} elseif ($r['code'] === 302) {
    // follow once and inspect
    $r2 = req('GET', $r['loc'], $cookieFile);
    echo "FOLLOW title=" . titleOf($r2['body']) . "\n";
    if (str_contains(titleOf($r2['body']), 'Admin') || str_contains($r2['body'], '/admin/')) {
        pass('login admin (follow)');
    } else {
        bad('login admin redirect', $r['loc'] . ' title=' . titleOf($r2['body']));
    }
} else {
    bad('login admin', 'code ' . $r['code']);
}

// Probe session via /admin
$paths = [
    '/admin' => 'admin home',
    '/admin/dashboard' => 'admin dashboard',
    '/admin/students' => 'alunos',
    '/admin/teachers' => 'professores',
    '/admin/tudicoins' => 'tudicoins',
    '/admin/finance' => 'financeiro',
    '/admin/enrollment' => 'matricula',
    '/admin/exams' => 'provas',
    '/admin/users' => 'usuarios',
    '/admin/school-settings' => 'config',
    '/admin/mural' => 'mural',
    '/admin/boletins' => 'boletins',
    '/admin/compliance' => 'conformidade',
    '/admin/patrimony' => 'patrimonio',
    '/admin/dev-settings/pwa' => 'dev pwa',
    '/admin/classes' => 'turmas',
    '/hits' => 'educahits (aluno rota — pode 302)',
];

foreach ($paths as $path => $label) {
    $resp = req('GET', $base . $path, $cookieFile);
    $code = $resp['code'];
    // follow one redirect if 302 to same host
    if ($code === 302 && $resp['loc']) {
        $next = $resp['loc'];
        if (str_starts_with($next, '/')) {
            $next = $base . $next;
        }
        $resp = req('GET', $next, $cookieFile);
        $code = $resp['code'];
    }
    $fatal = hasFatal($resp['body']);
    $title = titleOf($resp['body']);
    $looksLogin = (bool) preg_match('/Login Administração|name=["\']password["\']/i', $resp['body']);
    if ($fatal) {
        bad("$label [$code]", $fatal);
    } elseif ($looksLogin && str_starts_with($path, '/admin')) {
        bad("$label [$code]", 'caiu no login admin — sessão inválida');
    } elseif (in_array($code, [200, 403], true)) {
        pass("$label [$code] $title");
    } elseif ($code === 404) {
        bad("$label [404]", 'rota não encontrada');
    } else {
        bad("$label [$code]", "title=$title loc=" . ($resp['loc'] ?? ''));
    }
}

@unlink($cookieFile);
echo "\nResumo admin HTTP: OK=$ok FAIL=$fail\n";
exit($fail > 0 ? 1 : 0);
