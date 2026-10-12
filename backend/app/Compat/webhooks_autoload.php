<?php
/** Autoload compatível para App\Painel\Webhooks\* → App\Webhooks\*. */
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\Painel\\Webhooks\\', 20) !== 0) {
        return;
    }
    $newFqcn = 'App\\Webhooks\\' . substr($class, 20);
    if (!class_exists($newFqcn, true) && !interface_exists($newFqcn, true)) {
        return;
    }
    if (!class_exists($class, false) && !interface_exists($class, false)) {
        class_alias($newFqcn, $class);
    }
}, true, true);
