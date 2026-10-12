<?php
declare(strict_types=1);

$base = 'http://colag.localhost';
$ok = 0;
$fail = 0;
$warn = 0;

function pass(string $l): void { global $ok; echo "OK   $l\n"; $ok++; }
function bad(string $l, string $d = ''): void { global $fail; echo "FAIL $l" . ($d !== '' ? " — $d" : '') . "\n"; $fail++; }
function soft(string $l, string $d = ''): void { global $warn; echo "WARN $l" . ($d !== '' ? " — $d" : '') . "\n"; $warn++; }

function http(string $method, string $url, string $cookieFile, array $opts = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['Host: colag.localhost'],
        CURLOPT_TIMEOUT => 25,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    if (!empty($opts['body'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
    }
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // split headers
    $hdr = '';
    $body = $raw;
    while (preg_match("/^HTTP\\//", $body)) {
        $parts = preg_split("/\r?\n\r?\n/", $body, 2);
        $hdr .= ($parts[0] ?? '') . "\n";
        $body = $parts[1] ?? '';
        if (!preg_match("/^HTTP\\//", $body)) {
            break;
        }
    }
    $loc = null;
    if (preg_match('/^Location:\s*(.+)$/mi', $hdr, $m)) {
        $loc = trim($m[1]);
    }
    return compact('code', 'hdr', 'body', 'loc');
}

function csrf(string $html): array
{
    $out = [];
    if (preg_match_all('/<input[^>]+>/i', $html, $ins)) {
        foreach ($ins[0] as $inp) {
            if (!preg_match('/name=["\']([^"\']+)["\']/i', $inp, $n)) {
                continue;
            }
            $name = $n[1];
            $val = preg_match('/value=["\']([^"\']*)["\']/i', $inp, $v) ? $v[1] : '';
            if (stripos($name, 'csrf') !== false || $name === '_token' || $name === 'token') {
                $out[$name] = $val;
            }
        }
    }
    return $out;
}

function title(string $html): string
{
    return preg_match('/<title>([^<]+)/i', $html, $m) ? trim(html_entity_decode($m[1])) : '';
}

function fatal(string $html): ?string
{
    if (preg_match('/Fatal error|Parse error|Uncaught (?:Error|Exception)|Class .+ not found|Failed opening required/i', $html, $m)) {
        return trim(strip_tags($m[0]));
    }
    return null;
}

function hit(string $cookie, string $path, string $label, array $expect = [200, 302, 403, 404]): void
{
    $r = http('GET', 'http://colag.localhost' . $path, $cookie);
    // follow one redirect if needed for fatal check
    $body = $r['body'];
    $code = $r['code'];
    if ($code === 302 && $r['loc']) {
        $next = str_starts_with($r['loc'], 'http') ? $r['loc'] : 'http://colag.localhost' . (str_starts_with($r['loc'], '/') ? $r['loc'] : '/' . $r['loc']);
        $r2 = http('GET', $next, $cookie);
        // keep original code for expectation, but check fatal on final
        if ($f = fatal($r2['body'])) {
            bad("$label [$code→{$r2['code']}]", $f);
            return;
        }
        $body = $r2['body'];
    }
    if ($f = fatal($body)) {
        bad("$label [$code]", $f);
        return;
    }
    if (in_array($code, $expect, true)) {
        pass("$label [$code] " . title($body));
    } else {
        bad("$label [$code]", 'esperado ' . implode(',', $expect));
    }
}

echo "======== Público / API / PWA ========\n";
$anon = tempnam(sys_get_temp_dir(), 'anon');
$paths = [
    ['/login', 'login', [200]],
    ['/admin/login', 'admin login page', [200]],
    ['/api/v1/status', 'API status', [200]],
    ['/api/docs', 'API docs', [200, 301, 302]],
    ['/api/openapi.json', 'openapi', [200]],
    ['/manifest-aluno.json', 'PWA aluno', [200]],
    ['/manifest-professor.json', 'PWA professor', [200]],
    ['/manifest-admin.json', 'PWA admin', [200]],
    ['/manifest-pais.json', 'PWA pais', [200]],
    ['/validar/historico/x', 'Publico historico', [200, 404]],
    ['/educalabs/validate-token', 'EducaLabs token', [200, 302, 400, 401, 403]],
    ['/games/validate-token', 'Games token', [200, 302, 400, 401, 403]],
    ['/notes/validate-token', 'Notes token', [200, 302, 400, 401, 403]],
    ['/external-apps/validate-token', 'ExternalApps token', [200, 302, 400, 401, 403]],
    ['/api/auth/login', 'API auth GET', [200, 400, 401, 404, 405]],
];
foreach ($paths as [$p, $l, $e]) {
    hit($anon, $p, $l, $e);
}

echo "\n======== Login admin ========\n";
$admin = tempnam(sys_get_temp_dir(), 'adm');
$r = http('GET', 'http://colag.localhost/admin/login', $admin);
$tokens = csrf($r['body']);
echo 'CSRF: ' . json_encode($tokens) . "\n";
$post = array_merge($tokens, [
    'email' => 'admin@colag.local',
    'password' => '123456',
    'site_url' => '',
]);
if (!isset($post['_token']) && isset($post['csrf_token'])) {
    $post['_token'] = $post['csrf_token'];
}
// descobrir action do form
$action = '/admin/login';
if (preg_match('/<form[^>]+action=["\']([^"\']*)["\']/i', $r['body'], $m)) {
    $action = $m[1] !== '' ? $m[1] : $action;
}
if (!str_starts_with($action, 'http')) {
    $action = 'http://colag.localhost' . (str_starts_with($action, '/') ? $action : '/' . $action);
}
echo "POST $action\n";
$login = http('POST', $action, $admin, ['body' => http_build_query($post)]);
echo "login code={$login['code']} loc={$login['loc']}\n";

$sessionOk = false;
if ($login['code'] === 302 && $login['loc'] && str_contains($login['loc'], 'admin')) {
    pass('login admin → ' . $login['loc']);
    $sessionOk = true;
} else {
    // tentar /login genérico
    $r = http('GET', 'http://colag.localhost/login', $admin);
    $tokens = csrf($r['body']);
    $post = array_merge($tokens, [
        'email' => 'admin@colag.local',
        'password' => '123456',
        'site_url' => '',
    ]);
    if (!isset($post['_token']) && isset($post['csrf_token'])) {
        $post['_token'] = $post['csrf_token'];
    }
    $login = http('POST', 'http://colag.localhost/login', $admin, ['body' => http_build_query($post)]);
    echo "fallback /login code={$login['code']} loc={$login['loc']}\n";
    if ($login['code'] === 302) {
        soft('login redirect', (string) $login['loc']);
        // probe admin
        $probe = http('GET', 'http://colag.localhost/admin/dashboard', $admin);
        $looksLogin = (bool) preg_match('/Login Administração|name=["\']password["\']/i', $probe['body']);
        if ($probe['code'] === 200 && !$looksLogin && !fatal($probe['body'])) {
            pass('sessão admin válida via /admin/dashboard');
            $sessionOk = true;
        } elseif ($probe['code'] === 302 && $probe['loc'] && str_contains($probe['loc'], '/admin') && !str_contains($probe['loc'], 'login')) {
            $probe2 = http('GET', str_starts_with($probe['loc'], 'http') ? $probe['loc'] : 'http://colag.localhost' . $probe['loc'], $admin);
            $looksLogin = (bool) preg_match('/Login Administração|name=["\']password["\']/i', $probe2['body']);
            if (!$looksLogin && !fatal($probe2['body'])) {
                pass('sessão admin (follow) ' . title($probe2['body']));
                $sessionOk = true;
            } else {
                bad('sessão admin', 'ainda no login');
            }
        } else {
            bad('sessão admin', "code={$probe['code']} title=" . title($probe['body']));
        }
    } else {
        bad('login admin', 'code ' . $login['code']);
    }
}

echo "\n======== Páginas admin (com sessão se ok) ========\n";
$adminPaths = [
    '/admin' => 'dashboard shell',
    '/admin/dashboard' => 'dashboard',
    '/admin/students' => 'alunos',
    '/admin/teachers' => 'professores',
    '/admin/tudicoins' => 'tudicoins',
    '/admin/finance' => 'financeiro',
    '/admin/enrollment' => 'matricula',
    '/admin/boletins' => 'boletins',
    '/admin/dev-settings/pwa' => 'dev pwa',
];
foreach ($adminPaths as $p => $l) {
    $r = http('GET', 'http://colag.localhost' . $p, $admin);
    $code = $r['code'];
    $body = $r['body'];
    if ($code === 302 && $r['loc']) {
        $next = str_starts_with($r['loc'], 'http') ? $r['loc'] : 'http://colag.localhost' . $r['loc'];
        $r2 = http('GET', $next, $admin);
        if ($f = fatal($r2['body'])) {
            bad("admin $l [$code]", $f);
            continue;
        }
        $body = $r2['body'];
        $code = $r2['code'];
    }
    if ($f = fatal($body)) {
        bad("admin $l [$code]", $f);
        continue;
    }
    $looksLogin = (bool) preg_match('/Login Administração|name=["\']password["\']/i', $body);
    if ($sessionOk && $looksLogin) {
        bad("admin $l", 'perdeu sessão / login');
    } elseif (!$sessionOk && $looksLogin) {
        soft("admin $l [$code]", 'sem sessão (esperado)');
    } else {
        pass("admin $l [$code] " . title($body));
    }
}

echo "\n======== Aluno (login genérico) ========\n";
$stu = tempnam(sys_get_temp_dir(), 'stu');
$r = http('GET', 'http://colag.localhost/login', $stu);
$tokens = csrf($r['body']);
$post = array_merge($tokens, [
    'email' => 'aluno.teste@colag.local',
    'password' => '123456',
    'site_url' => '',
]);
if (!isset($post['_token']) && isset($post['csrf_token'])) {
    $post['_token'] = $post['csrf_token'];
}
$login = http('POST', 'http://colag.localhost/login', $stu, ['body' => http_build_query($post)]);
echo "aluno login code={$login['code']} loc={$login['loc']}\n";
foreach ([
    '/' => 'home',
    '/educalabs' => 'EducaLabs',
    '/jogos' => 'Jogos',
    '/jogo-milhao' => 'Milhão',
    '/notes/access' => 'Notes',
    '/hits' => 'EducaHits',
    '/flashcards' => 'Flashcards',
] as $p => $l) {
    hit($stu, $p, "aluno $l", [200, 302, 403, 404]);
}

@unlink($anon);
@unlink($admin);
@unlink($stu);

echo "\n======== RESUMO: OK=$ok FAIL=$fail WARN=$warn ========\n";
exit($fail > 0 ? 1 : 0);
