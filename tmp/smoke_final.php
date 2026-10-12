<?php
declare(strict_types=1);

$base = 'http://colag.localhost';
$ok = 0;
$fail = 0;

function pass(string $l): void { global $ok; echo "OK   $l\n"; $ok++; }
function bad(string $l, string $d = ''): void { global $fail; echo "FAIL $l" . ($d !== '' ? " — $d" : '') . "\n"; $fail++; }

function http(string $method, string $url, string $ck, ?array $post = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['Host: colag.localhost'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_COOKIEJAR => $ck,
        CURLOPT_COOKIEFILE => $ck,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdr = '';
    $body = $raw;
    while (preg_match('/^HTTP\//', $body)) {
        $parts = preg_split("/\r?\n\r?\n/", $body, 2);
        $hdr .= ($parts[0] ?? '') . "\n";
        $body = $parts[1] ?? '';
        if (!preg_match('/^HTTP\//', $body)) {
            break;
        }
    }
    $loc = preg_match('/^Location:\s*(.+)$/mi', $hdr, $m) ? trim($m[1]) : null;
    return compact('code', 'body', 'loc');
}

function title(string $html): string
{
    return preg_match('/<title>([^<]+)/i', $html, $m) ? trim(html_entity_decode($m[1])) : '';
}

function fatal(?string $html): ?string
{
    if (!$html) {
        return null;
    }
    return preg_match('/Fatal error|Parse error|Uncaught (?:Error|Exception)|Class .+ not found|Failed opening required/i', $html, $m)
        ? trim(strip_tags($m[0]))
        : null;
}

function loginAs(string $loginPage, string $user, string $pass): string
{
    $ck = tempnam(sys_get_temp_dir(), 'ck');
    $page = http('GET', 'http://colag.localhost' . $loginPage, $ck);
    if (!preg_match('/name="_token"[^>]*value="([^"]+)"/', $page['body'], $tm)) {
        throw new RuntimeException("CSRF não encontrado em $loginPage");
    }
    $tipo = preg_match('/name="tipo"[^>]*value="([^"]+)"/', $page['body'], $t) ? $t[1] : '';
    $r = http('POST', 'http://colag.localhost/login', $ck, [
        '_token' => $tm[1],
        'tipo' => $tipo,
        'login' => $user,
        'senha' => $pass,
        'site_url' => '',
    ]);
    echo "login $user via $loginPage → {$r['code']} {$r['loc']}\n";
    if ($r['code'] !== 302) {
        throw new RuntimeException("login falhou code={$r['code']}");
    }
    return $ck;
}

function check(string $ck, string $path, string $label, bool $mustNotBeLogin = true): void
{
    $r = http('GET', 'http://colag.localhost' . $path, $ck);
    $code = $r['code'];
    $body = $r['body'];
    if ($code === 302 && $r['loc']) {
        $next = str_starts_with($r['loc'], 'http') ? $r['loc'] : 'http://colag.localhost' . $r['loc'];
        $r2 = http('GET', $next, $ck);
        if ($f = fatal($r2['body'])) {
            bad("$label [$code]", $f);
            return;
        }
        $body = $r2['body'];
        // se redirecionou para login, marcar
        if ($mustNotBeLogin && preg_match('/Login |name="senha"/i', $r2['body']) && preg_match('/name="login"/', $r2['body'])) {
            bad("$label", 'redirecionou para login');
            return;
        }
        $code = $r2['code'];
    }
    if ($f = fatal($body)) {
        bad("$label [$code]", $f);
        return;
    }
    if ($mustNotBeLogin && preg_match('/Login Administração|Portal do Aluno - EducaTudo/i', title($body)) && preg_match('/name="senha"/', $body)) {
        // página de login
        bad("$label [$code]", 'página de login: ' . title($body));
        return;
    }
    if (in_array($code, [200, 403], true)) {
        pass("$label [$code] " . title($body));
    } else {
        bad("$label [$code]", title($body));
    }
}

echo "======== Público ========\n";
$anon = tempnam(sys_get_temp_dir(), 'a');
foreach ([
    '/api/v1/status' => 'API status',
    '/api/docs' => 'API docs',
    '/api/openapi.json' => 'openapi',
    '/manifest-aluno.json' => 'PWA aluno',
    '/manifest-admin.json' => 'PWA admin',
    '/validar/historico/x' => 'Publico historico',
    '/educalabs/validate-token' => 'EducaLabs token',
    '/games/validate-token' => 'Games token',
    '/notes/validate-token' => 'Notes token',
    '/external-apps/validate-token' => 'ExternalApps token',
] as $p => $l) {
    $r = http('GET', 'http://colag.localhost' . $p, $anon);
    if ($f = fatal($r['body'])) {
        bad("$l [{$r['code']}]", $f);
    } elseif (in_array($r['code'], [200, 302, 400, 401, 403, 404], true)) {
        pass("$l [{$r['code']}]");
    } else {
        bad("$l [{$r['code']}]");
    }
}

echo "\n======== Admin autenticado ========\n";
try {
    $ck = loginAs('/admin', 'admin@colag.local', '123456');
    foreach ([
        '/admin/dashboard' => 'dashboard',
        '/admin/students' => 'alunos (Dominio)',
        '/admin/teachers' => 'professores (Dominio)',
        '/admin/tudicoins' => 'tudicoins',
        '/admin/finance' => 'financeiro (Modulo)',
        '/admin/enrollment' => 'matricula (Modulo)',
        '/admin/boletins' => 'boletins (Modulo)',
        '/admin/dev-settings/pwa' => 'dev PWA',
        '/admin/conformidade' => 'conformidade (Modulo)',
        '/admin/settings' => 'config escola (Dominio)',
    ] as $p => $l) {
        check($ck, $p, "admin $l");
    }
} catch (Throwable $e) {
    bad('login admin', $e->getMessage());
}

echo "\n======== Aluno autenticado ========\n";
try {
    $ck = loginAs('/login', 'aluno.teste@colag.local', '123456');
    // se login falhar (senha), não marcar páginas de login como OK
    $home = http('GET', 'http://colag.localhost/', $ck);
    $logado = !preg_match('/name="senha"/', $home['body']);
    if (!$logado) {
        bad('login aluno', 'sessão não estabelecida (credencial?)');
    } else {
        foreach ([
            '/' => 'home',
            '/educalabs' => 'EducaLabs',
            '/jogos' => 'Jogos',
            '/jogo-milhao' => 'Milhão',
            '/notes/access' => 'Notes',
            '/hits' => 'EducaHits',
            '/flashcards' => 'Flashcards',
        ] as $p => $l) {
            check($ck, $p, "aluno $l");
        }
    }
} catch (Throwable $e) {
    bad('login aluno', $e->getMessage());
}

echo "\n======== RESUMO: OK=$ok FAIL=$fail ========\n";
exit($fail > 0 ? 1 : 0);
