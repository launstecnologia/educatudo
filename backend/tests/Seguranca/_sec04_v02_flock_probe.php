<?php
/**
 * Probe flock sem pcntl: N processos PHP em paralelo via shell.
 * Uso: docker exec php_app_educatudo php /var/www/html/tests/Seguranca/_sec04_v02_flock_probe.php
 */
$f = dirname(__DIR__, 2) . '/storage/logs/login_rate_limit.json';
@mkdir(dirname($f), 0755, true);
file_put_contents($f, '{}');

$workers = 8;
$perWorker = 20;
$workerScript = sys_get_temp_dir() . '/sec04_v02_flock_worker.php';
file_put_contents($workerScript, <<<'PHP'
<?php
$f = $argv[1];
$n = (int) $argv[2];
for ($j = 0; $j < $n; $j++) {
    $fp = fopen($f, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        $raw = stream_get_contents($fp);
        $d = $raw !== false && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
        $d['fork'] = ['c' => (int) ($d['fork']['c'] ?? 0) + 1, 't' => time()];
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($d));
        flock($fp, LOCK_UN);
        fclose($fp);
    } elseif ($fp) {
        fclose($fp);
    }
}
PHP);

$procs = [];
for ($i = 0; $i < $workers; $i++) {
    $procs[] = proc_open(
        [PHP_BINARY, $workerScript, $f, (string) $perWorker],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
}
foreach ($procs as $p) {
    if (is_resource($p)) {
        proc_close($p);
    }
}
@unlink($workerScript);

$d = json_decode((string) file_get_contents($f), true);
$expected = $workers * $perWorker;
$count = (int) ($d['fork']['c'] ?? 0);
echo json_encode([
    'flock_count' => $count,
    'expected' => $expected,
    'ok' => is_array($d) && $count === $expected,
    'json_valid' => is_array($d),
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
