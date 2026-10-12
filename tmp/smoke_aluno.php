<?php
declare(strict_types=1);

$ck = tempnam(sys_get_temp_dir(), 'ck');

function req(string $m, string $u, string $ck, ?array $post = null): array
{
    $ch = curl_init($u);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $ck,
        CURLOPT_COOKIEFILE => $ck,
        CURLOPT_HTTPHEADER => ['Host: colag.localhost'],
        CURLOPT_TIMEOUT => 25,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
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

$page = req('GET', 'http://colag.localhost/login', $ck);
preg_match('/name="_token"[^>]*value="([^"]+)/', $page['body'], $tm);
$login = req('POST', 'http://colag.localhost/login', $ck, [
    '_token' => $tm[1],
    'tipo' => 'aluno',
    'login' => 'aluno.teste',
    'senha' => '123456',
    'site_url' => '',
]);
echo "LOGIN {$login['code']} {$login['loc']}\n";
echo "JAR:\n" . file_get_contents($ck) . "\n";

$paths = ['/dashboard', '/', '/educalabs', '/jogos', '/jogo-milhao', '/hits', '/notes/access', '/flashcards'];
foreach ($paths as $path) {
    $r = req('GET', 'http://colag.localhost' . $path, $ck);
    $hops = 0;
    while ($r['code'] === 302 && $r['loc'] && $hops < 5) {
        $next = str_starts_with($r['loc'], 'http') ? $r['loc'] : 'http://colag.localhost' . $r['loc'];
        echo "  redirect $path -> {$r['loc']}\n";
        $r = req('GET', $next, $ck);
        $hops++;
    }
    preg_match('/<title>([^<]+)/', $r['body'], $t);
    $fatal = preg_match('/Fatal error|Parse error|Uncaught|Class .+ not found/i', $r['body'], $mm) ? $mm[0] : '';
    $loginForm = preg_match('/name="senha"/', $r['body']) ? 'LOGIN' : 'OK';
    echo ($fatal ? 'FAIL' : $loginForm) . " $path [{$r['code']}] " . ($t[1] ?? '') . ($fatal ? " $fatal" : '') . "\n";
}
