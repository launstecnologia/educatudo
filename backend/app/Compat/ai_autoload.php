<?php
/** Autoload compatível para App\AI\* sem pasta app/AI. */
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\AI\\', 7) !== 0) {
        return;
    }
    static $map = null;
    if ($map === null) {
        $map = require __DIR__ . '/ai_map.php';
    }
    if (!isset($map[$class])) {
        return;
    }
    [$newFqcn, $rel] = $map[$class];
    $file = dirname(__DIR__) . '/' . $rel;
    if (is_file($file)) {
        require_once $file;
    }
    if ((class_exists($newFqcn, false) || interface_exists($newFqcn, false) || trait_exists($newFqcn, false))
        && !class_exists($class, false) && !interface_exists($class, false) && !trait_exists($class, false)) {
        class_alias($newFqcn, $class);
    }
}, true, true);
