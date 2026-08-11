<?php
$page_header_title = 'AVA / EAD';
$page_header_subtitle = 'Ambiente Virtual de Aprendizagem.';
include __DIR__ . '/../_partials/page_header_list.php';
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
    <i class="fa-solid fa-database text-3xl text-amber-500 mb-3"></i>
    <h3 class="text-lg font-semibold text-gray-900 mb-2">Estrutura do AVA ainda não criada</h3>
    <p class="text-sm text-gray-600 max-w-xl mx-auto">
        Execute, no painel Master, as migrations nesta ordem:
        <code class="block mt-3 text-left bg-gray-50 rounded-lg p-3 text-xs">
            1) 2026_06_26_ava_01_fase1.sql<br>
            2) 2026_06_26_ava_02_professor_tutoria.sql
        </code>
    </p>
</div>
