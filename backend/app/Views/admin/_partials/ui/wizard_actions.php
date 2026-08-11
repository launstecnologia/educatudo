<?php
/**
 * Rodapé de navegação de uma etapa do wizard (Voltar / Próximo|Concluir).
 * O botão Voltar leva a classe `.wizard-step-back` + `data-go-step`; o botão
 * avançar leva a classe `.wizard-step-next` — o JS da página liga os
 * handlers de clique (ver ui-modelos/wizard.php).
 *
 * Variáveis:
 * - $ui_wizard_actions_back_step (int|null) — etapa alvo do Voltar; null omite o botão (1ª etapa)
 * - $ui_wizard_actions_next_label (string) — default "Próximo"
 * - $ui_wizard_actions_next_icon (string) — ícone à direita do botão avançar; '' remove (default seta)
 * - $ui_wizard_actions_next_id (string, opcional)
 * - $ui_wizard_actions_next_type (string): button|submit (default button)
 */
$ui_wizard_actions_back_step = $ui_wizard_actions_back_step ?? null;
$ui_wizard_actions_next_label = (string) ($ui_wizard_actions_next_label ?? 'Próximo');
$ui_wizard_actions_next_icon = (string) ($ui_wizard_actions_next_icon ?? 'fa-solid fa-arrow-right');
$ui_wizard_actions_next_id = (string) ($ui_wizard_actions_next_id ?? '');
$ui_wizard_actions_next_type = (string) ($ui_wizard_actions_next_type ?? 'button');
?>
<div class="flex items-center <?= $ui_wizard_actions_back_step !== null ? 'justify-between' : 'justify-end' ?> pt-2">
    <?php if ($ui_wizard_actions_back_step !== null): ?>
        <button type="button" class="wizard-step-back px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium" data-go-step="<?= (int) $ui_wizard_actions_back_step ?>">
            <i class="fa-solid fa-arrow-left mr-2"></i>Voltar
        </button>
    <?php endif; ?>
    <button type="<?= htmlspecialchars($ui_wizard_actions_next_type) ?>"
            class="wizard-step-next btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm"
            <?= $ui_wizard_actions_next_id !== '' ? 'id="' . htmlspecialchars($ui_wizard_actions_next_id) . '"' : '' ?>>
        <?= htmlspecialchars($ui_wizard_actions_next_label) ?>
        <?php if ($ui_wizard_actions_next_icon !== ''): ?>
            <i class="<?= htmlspecialchars($ui_wizard_actions_next_icon) ?> ml-2"></i>
        <?php endif; ?>
    </button>
</div>
<?php
unset(
    $ui_wizard_actions_back_step, $ui_wizard_actions_next_label, $ui_wizard_actions_next_icon,
    $ui_wizard_actions_next_id, $ui_wizard_actions_next_type
);
?>
