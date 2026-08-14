<?php
$page_header_title = 'Matrículas';
$page_header_subtitle = '';
$page_header_actions = '';
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php'; ?>

<div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-8 text-center max-w-lg mx-auto mt-8">
    <i class="fa-solid fa-database text-4xl text-yellow-500 mb-4 block"></i>
    <h3 class="font-semibold text-gray-800 text-lg mb-2">Migration necessária</h3>
    <p class="text-sm text-gray-600 mb-4">
        Execute a migration <code class="bg-white border border-yellow-200 px-1.5 py-0.5 rounded text-xs">2026_07_02_enrollment.sql</code>
        no painel Master para habilitar o módulo de matrículas.
    </p>
    <a href="<?= URL ?>/master/migrations" class="btn-primary text-sm">
        Ir para Migrations
    </a>
</div>
