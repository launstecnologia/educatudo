<?php include __DIR__ . '/../_partials/page_header_list.php'; ?>
<div class="bg-amber-50 border border-amber-200 rounded-2xl p-8 text-center max-w-lg mx-auto mt-10">
    <i class="fa-solid fa-triangle-exclamation text-amber-400 text-4xl mb-4"></i>
    <h2 class="text-lg font-semibold text-amber-800 mb-2">Migration pendente</h2>
    <p class="text-amber-700 text-sm">Execute a migration <code class="bg-amber-100 px-1 rounded">2026_07_02_finance.sql</code> no painel Master antes de usar o módulo financeiro.</p>
    <a href="<?= URL ?>/master/migrations" class="mt-4 inline-block btn-primary text-sm">Ir para Migrations</a>
</div>
