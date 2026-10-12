<?php
/** Autoload compatível para App\Painel\Support\* → App\Modulos\Tickets\*. */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\Painel\\Support\\';
    $len = strlen($prefix);
    if (strncmp($class, $prefix, $len) !== 0) {
        return;
    }
    $rest = substr($class, $len);
    // SupportTicketController foi renomeado para TicketController
    if ($rest === 'Controllers\\SupportTicketController') {
        $rest = 'Controllers\\TicketController';
    }
    $newFqcn = 'App\\Modulos\\Tickets\\' . $rest;
    if (!class_exists($newFqcn, false) && !interface_exists($newFqcn, false)) {
        if (!class_exists($newFqcn, true) && !interface_exists($newFqcn, true)) {
            return;
        }
    }
    if (!class_exists($class, false) && !interface_exists($class, false)) {
        class_alias($newFqcn, $class);
    }
}, true, true);
