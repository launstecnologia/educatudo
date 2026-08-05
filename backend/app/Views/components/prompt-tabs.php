<?php
/**
 * Componente reutilizável de tabs para edição de prompts de IA.
 *
 * Variáveis esperadas:
 *   $promptTabs   - array associativo: chave => ['label' => 'Nome da tab', 'icon' => 'emoji', 'hint' => 'texto ajuda', 'rows' => int]
 *   $promptValues - array associativo: chave => valor atual do prompt
 *   $tabsId       - string única para evitar conflito de IDs quando múltiplas instâncias existirem na mesma página
 */

$promptTabs   = $promptTabs ?? [];
$promptValues = $promptValues ?? [];
$tabsId       = $tabsId ?? 'prompt-tabs-' . uniqid();
$tabKeys      = array_keys($promptTabs);
?>

<div id="<?= htmlspecialchars($tabsId) ?>" class="prompt-tabs-component">
    <div class="flex flex-wrap gap-1 border-b border-gray-200 mb-4" role="tablist">
        <?php foreach ($promptTabs as $key => $tab): ?>
        <button type="button"
                role="tab"
                aria-selected="<?= $key === $tabKeys[0] ? 'true' : 'false' ?>"
                data-tab-target="<?= htmlspecialchars($tabsId . '-' . $key) ?>"
                class="prompt-tab-btn px-3 py-2 text-sm font-medium rounded-t-lg border-b-2 transition-colors
                       <?= $key === $tabKeys[0]
                           ? 'border-indigo-500 text-indigo-600 bg-indigo-50/50'
                           : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
            <?php if (!empty($tab['icon'])): ?><span class="mr-1"><?= $tab['icon'] ?></span><?php endif; ?>
            <?= htmlspecialchars($tab['label']) ?>
        </button>
        <?php endforeach; ?>
    </div>

    <?php foreach ($promptTabs as $key => $tab): ?>
    <div id="<?= htmlspecialchars($tabsId . '-' . $key) ?>"
         role="tabpanel"
         class="prompt-tab-panel <?= $key !== $tabKeys[0] ? 'hidden' : '' ?>">
        <label class="block text-sm font-semibold text-gray-800 mb-2">
            <?php if (!empty($tab['icon'])): ?><span class="mr-1"><?= $tab['icon'] ?></span><?php endif; ?>
            <?= htmlspecialchars($tab['label']) ?>
        </label>
        <textarea name="<?= htmlspecialchars($key) ?>"
                  id="<?= htmlspecialchars($tabsId . '-textarea-' . $key) ?>"
                  rows="<?= (int) ($tab['rows'] ?? 6) ?>"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm font-mono"
                  placeholder="<?= htmlspecialchars($tab['placeholder'] ?? 'Digite o prompt aqui...') ?>"
        ><?= htmlspecialchars($promptValues[$key] ?? '') ?></textarea>
        <?php if (!empty($tab['hint'])): ?>
        <p class="mt-1 text-xs text-gray-500"><?= $tab['hint'] ?></p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<script>
(function() {
    var container = document.getElementById('<?= $tabsId ?>');
    if (!container) return;
    var buttons = container.querySelectorAll('.prompt-tab-btn');
    var panels  = container.querySelectorAll('.prompt-tab-panel');

    buttons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            buttons.forEach(function(b) {
                b.classList.remove('border-indigo-500', 'text-indigo-600', 'bg-indigo-50/50');
                b.classList.add('border-transparent', 'text-gray-500');
                b.setAttribute('aria-selected', 'false');
            });
            panels.forEach(function(p) { p.classList.add('hidden'); });

            btn.classList.add('border-indigo-500', 'text-indigo-600', 'bg-indigo-50/50');
            btn.classList.remove('border-transparent', 'text-gray-500');
            btn.setAttribute('aria-selected', 'true');
            var target = document.getElementById(btn.getAttribute('data-tab-target'));
            if (target) target.classList.remove('hidden');
        });
    });
})();
</script>
