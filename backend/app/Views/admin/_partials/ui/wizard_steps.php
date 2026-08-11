<?php
/**
 * Navegação de etapas (wizard) — nº + label + conector, com 4 estados
 * (ativo | completo | erro | pendente). Referência real: admin/inclusao/manage.php.
 * Ativo usa cor do sistema (whitelabel); completo/erro usam cor semântica
 * fixa (verde/vermelho), igual ao padrão de badge.php (`ativo`/`erro`).
 *
 * Precedência de estado por etapa: erro > ativo > completo > pendente.
 *
 * O estado inicial é definido por PHP; ao trocar de etapa/validar campos via
 * JS, o próprio script da página recalcula e troca as classes + o badge de
 * canto (ver ui-modelos/wizard.php) — não depender de variantes Tailwind
 * `data-[...]` porque `bg-primary`/`text-primary`/`border-accent` são classes
 * utilitárias customizadas (LayoutHelper), não utilities nativas do Tailwind.
 *
 * Variáveis:
 * - $ui_wizard_steps (array<int, array{label:string, sub?:string}>) — índice 0 = etapa 1
 * - $ui_wizard_current (int) — etapa ativa (1-based)
 * - $ui_wizard_completed (array<int>, opcional) — etapas já validadas/concluídas
 * - $ui_wizard_error_steps (array<int>, opcional) — etapas com validação pendente
 * - $ui_wizard_nav_id (string, opcional) — default "wizardStepsNav"
 */
$ui_wizard_steps = (array) ($ui_wizard_steps ?? []);
$ui_wizard_current = (int) ($ui_wizard_current ?? 1);
$ui_wizard_completed = array_map('intval', (array) ($ui_wizard_completed ?? []));
$ui_wizard_error_steps = array_map('intval', (array) ($ui_wizard_error_steps ?? []));
$ui_wizard_nav_id = (string) ($ui_wizard_nav_id ?? 'wizardStepsNav');
$ui_wizard_total = count($ui_wizard_steps);

$ui_wizard_cls_map = [
    'ativo' => 'border-accent bg-primary text-primary shadow-md',
    'completo' => 'border-green-500 bg-green-50 text-green-700',
    'erro' => 'border-red-400 bg-red-50 text-red-700',
    'pendente' => 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:bg-gray-50',
];
$ui_wizard_badge_map = [
    'completo' => ['bg-green-500', 'fa-solid fa-check'],
    'erro' => ['bg-red-500', 'fa-solid fa-exclamation'],
];
?>
<div class="flex flex-col sm:flex-row sm:items-center w-full gap-2 sm:gap-0" id="<?= htmlspecialchars($ui_wizard_nav_id) ?>" role="tablist">
    <?php foreach ($ui_wizard_steps as $ui_wizard_i => $ui_wizard_s): ?>
        <?php
        $ui_wizard_n = $ui_wizard_i + 1;
        if (in_array($ui_wizard_n, $ui_wizard_error_steps, true)) {
            $ui_wizard_estado = 'erro';
        } elseif ($ui_wizard_n === $ui_wizard_current) {
            $ui_wizard_estado = 'ativo';
        } elseif (in_array($ui_wizard_n, $ui_wizard_completed, true)) {
            $ui_wizard_estado = 'completo';
        } else {
            $ui_wizard_estado = 'pendente';
        }
        $ui_wizard_badge = $ui_wizard_badge_map[$ui_wizard_estado] ?? null;
        ?>
        <button type="button" data-step-target="<?= $ui_wizard_n ?>"
                data-active="<?= $ui_wizard_estado === 'ativo' ? 'true' : 'false' ?>"
                data-step-state="<?= htmlspecialchars($ui_wizard_estado) ?>"
                class="step-nav-btn flex items-center gap-3 rounded-xl border-2 px-4 py-3 text-left transition sm:flex-1 <?= htmlspecialchars($ui_wizard_cls_map[$ui_wizard_estado]) ?>">
            <span class="wizard-step-circle relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold border-2 border-current">
                <span class="wizard-step-num"><?= $ui_wizard_n ?></span>
                <?php if ($ui_wizard_badge !== null): ?>
                    <span class="wizard-step-corner absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full text-white text-[9px] <?= htmlspecialchars($ui_wizard_badge[0]) ?>">
                        <i class="<?= htmlspecialchars($ui_wizard_badge[1]) ?>"></i>
                    </span>
                <?php endif; ?>
            </span>
            <span class="min-w-0">
                <span class="block font-semibold leading-tight"><?= htmlspecialchars((string) ($ui_wizard_s['label'] ?? '')) ?></span>
                <?php if (!empty($ui_wizard_s['sub'])): ?>
                    <span class="block text-xs opacity-80"><?= htmlspecialchars((string) $ui_wizard_s['sub']) ?></span>
                <?php endif; ?>
            </span>
        </button>
        <?php if ($ui_wizard_i < $ui_wizard_total - 1): ?>
            <?php $ui_wizard_conector_ok = in_array($ui_wizard_n, $ui_wizard_completed, true) && !in_array($ui_wizard_n, $ui_wizard_error_steps, true); ?>
            <div class="hidden sm:block flex-1 min-w-[1rem] h-0.5 <?= $ui_wizard_conector_ok ? 'bg-green-400' : 'bg-gray-200' ?>" data-connector-after="<?= $ui_wizard_n ?>" aria-hidden="true"></div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php
unset(
    $ui_wizard_steps, $ui_wizard_current, $ui_wizard_completed, $ui_wizard_error_steps,
    $ui_wizard_nav_id, $ui_wizard_total, $ui_wizard_cls_map, $ui_wizard_badge_map,
    $ui_wizard_i, $ui_wizard_s, $ui_wizard_n, $ui_wizard_estado, $ui_wizard_badge,
    $ui_wizard_conector_ok
);
?>
