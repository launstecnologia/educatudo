<?php

use App\Modulos\EducaSync\Models\Cenario;

$csrf_token = $csrf_token ?? '';
$ano_letivo = (int) ($ano_letivo ?? date('Y'));
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Novo cenário</h2>
            <p class="text-gray-600">Defina o ano letivo, o período e o tempo que o solver pode usar. Horários e demandas vêm no próximo passo.</p>
        </div>
        <a href="<?= URL ?>/admin/educasync" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<?php include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php'; ?>

<div class="bg-white rounded-xl shadow-lg p-6 w-full">
    <form method="POST" action="<?= URL ?>/admin/educasync">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token) ?>">

        <div class="mb-6">
            <label for="nome" class="block text-sm font-medium text-gray-700 mb-2">Nome do cenário</label>
            <input type="text" id="nome" name="nome" maxlength="150" required placeholder="Ex.: Grade 2027 — Ensino Fundamental II (manhã)"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="ano_letivo" class="block text-sm font-medium text-gray-700 mb-2">Ano letivo</label>
                <input type="number" id="ano_letivo" name="ano_letivo" min="2000" max="2100" required value="<?= $ano_letivo ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <div>
                <label for="periodo" class="block text-sm font-medium text-gray-700 mb-2">Período</label>
                <select id="periodo" name="periodo" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <?php foreach (Cenario::PERIODOS as $valor => $rotulo): ?>
                    <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">A Grade Horária oficial só aceita manhã e tarde nesta fase.</p>
            </div>
            <div>
                <label for="max_time_s" class="block text-sm font-medium text-gray-700 mb-2">Tempo máximo do solver (segundos)</label>
                <input type="number" id="max_time_s" name="max_time_s" min="5" max="600" value="60"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <div>
                <label for="peso_janelas" class="block text-sm font-medium text-gray-700 mb-2">Peso para evitar janelas (0 a 1000)</label>
                <input type="number" id="peso_janelas" name="peso_janelas" min="0" max="1000" value="300"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <p class="text-xs text-gray-500 mt-1">Quanto maior, mais o solver compacta a agenda do professor (menos “furos” entre a 1ª e a última aula do dia), alinhado à boa prática / legislação de janelas.</p>
            </div>
        </div>

        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/admin/educasync" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</a>
            <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg font-semibold hover:opacity-90">Criar cenário</button>
        </div>
    </form>
</div>
