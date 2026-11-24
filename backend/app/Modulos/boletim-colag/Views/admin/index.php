<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Boletim Colag</h2>
    <p class="text-gray-600">Modelo desta escola. O módulo Evento Avaliativo é outro e não entra neste quadro.</p>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 w-full space-y-6">
    <div>
        <p class="text-sm font-semibold text-gray-900">Dois módulos</p>
        <ul class="mt-2 text-sm text-gray-700 space-y-1">
            <li><strong>Boletim Colag</strong> — prova semanal em Bloco A (S<?= (int) ($semanas['a'][0] ?? 1) ?>, S<?= (int) ($semanas['a'][1] ?? 3) ?>, S<?= (int) ($semanas['a'][2] ?? 5) ?>) e Bloco B (S<?= (int) ($semanas['b'][0] ?? 2) ?>, S<?= (int) ($semanas['b'][1] ?? 4) ?>, S<?= (int) ($semanas['b'][2] ?? 6) ?>), com bloco de cálculo.</li>
            <li><strong>Evento Avaliativo</strong> — o quadro das outras escolas, quando esse módulo está ligado.</li>
        </ul>
    </div>
    <div>
        <a href="<?= URL ?>/admin/boletins" class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium">Abrir o assistente</a>
    </div>
</div>
