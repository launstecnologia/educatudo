<?php
/**
 * CSS Tailwind compilado — incluir no <head> no lugar do Play CDN.
 * Regenerar: cd backend && npm run build:css
 */
if (!class_exists('EstilosPlataforma', false)) {
    require_once dirname(__DIR__, 3) . '/Helpers/EstilosPlataforma.php';
}
echo EstilosPlataforma::tagLink() . "\n";
