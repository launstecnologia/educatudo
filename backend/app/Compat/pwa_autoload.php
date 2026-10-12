<?php
/** Autoload compatível para App\Pwa\* → App\PWA\*. */
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\Pwa\\', 8) !== 0) {
        return;
    }
    $newFqcn = 'App\\PWA\\' . substr($class, 8);
    if (!class_exists($newFqcn, true) && !interface_exists($newFqcn, true)) {
        return;
    }
    if (!class_exists($class, false) && !interface_exists($class, false)) {
        class_alias($newFqcn, $class);
    }
}, true, true);
