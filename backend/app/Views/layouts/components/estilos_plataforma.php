<?php
/**
 * CSS Tailwind compilado — incluir no <head> no lugar do Play CDN.
 * Regenerar: cd backend && npm run build:css
 */
if (!class_exists('EstilosPlataforma', false)) {
}
echo \App\Helpers\EstilosPlataforma::tagLink() . "\n";
