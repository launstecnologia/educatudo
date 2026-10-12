<?php
/** Autoload compatível para App\Painel\Media\* → App\Infra\Media\*. */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\Painel\\Media\\';
    $len = strlen($prefix);
    if (strncmp($class, $prefix, $len) !== 0) {
        return;
    }
    $newFqcn = 'App\\Infra\\Media\\' . substr($class, $len);
    if (!class_exists($newFqcn, false) && !interface_exists($newFqcn, false)) {
        if (!class_exists($newFqcn, true) && !interface_exists($newFqcn, true)) {
            return;
        }
    }
    if (!class_exists($class, false) && !interface_exists($class, false)) {
        class_alias($newFqcn, $class);
    }
}, true, true);
