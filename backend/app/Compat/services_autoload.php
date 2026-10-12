<?php
/** Autoload compatível para App\Services\* sem pasta app/Services. */
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\Services\\', 13) !== 0) {
        return;
    }
    static $map = null;
    if ($map === null) {
        $map = require __DIR__ . '/services_map.php';
    }
    if (!isset($map[$class])) {
        return;
    }
    [$newFqcn, $rel] = $map[$class];
    $file = dirname(__DIR__) . '/' . $rel;
    if (is_file($file)) {
        require_once $file;
    }
    if (class_exists($newFqcn, false) && !class_exists($class, false)) {
        class_alias($newFqcn, $class);
    }
}, true, true);
