<?php
declare(strict_types=1);

$root = dirname(__DIR__) . '/backend';
require $root . '/vendor/autoload.php';
require_once $root . '/app/Core/ControllerLocator.php';

$routeFiles = glob($root . '/config/routes/*.php') ?: [];
$handlers = [];
foreach ($routeFiles as $file) {
    $src = file_get_contents($file);
    if (preg_match_all("/['\"]([A-Za-z0-9_\\/]+)@([A-Za-z0-9_]+)['\"]/", $src, $m)) {
        foreach ($m[1] as $ctrl) {
            $handlers[$ctrl] = basename($file);
        }
    }
}

ksort($handlers);
$ok = 0;
$fail = 0;
$fails = [];
foreach ($handlers as $ctrl => $from) {
    $r = App\Core\ControllerLocator::resolve($ctrl);
    if ($r && is_file($r['path'])) {
        $ok++;
        continue;
    }
    $fail++;
    $fails[] = "$ctrl (em $from)";
}

echo "Handlers únicos nas rotas: " . count($handlers) . "\n";
echo "OK resolve: $ok\n";
echo "FAIL resolve: $fail\n";
if ($fails) {
    echo "Não resolvidos:\n";
    foreach (array_slice($fails, 0, 80) as $f) {
        echo "  - $f\n";
    }
    if (count($fails) > 80) {
        echo "  ... +" . (count($fails) - 80) . "\n";
    }
}

// Tenta carregar FQCN de cada resolve OK (amostra + todos os movidos)
$loadFail = 0;
$loadOk = 0;
$checked = 0;
foreach ($handlers as $ctrl => $from) {
    $r = App\Core\ControllerLocator::resolve($ctrl);
    if (!$r || empty($r['fqcn'])) {
        continue;
    }
    $fqcn = $r['fqcn'];
    // pular se já alias global conflita — carregar via require path
    try {
        require_once $r['path'];
        if (class_exists($fqcn, false) || interface_exists($fqcn, false)) {
            $loadOk++;
        } else {
            $loadFail++;
            echo "LOAD FAIL $ctrl → $fqcn (arquivo ok, classe não declarada)\n";
        }
    } catch (Throwable $e) {
        $loadFail++;
        echo "LOAD ERR $ctrl → " . $e->getMessage() . "\n";
    }
    $checked++;
}
echo "Load check: OK=$loadOk FAIL=$loadFail (de $checked com fqcn)\n";
exit(($fail + $loadFail) > 0 ? 1 : 0);
