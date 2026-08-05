<?php
/**
 * Botão primário de ação de IA — cor da escola (navbar / primary_color).
 *
 * Variáveis opcionais:
 *   $aiBtnLabel, $aiBtnLoadingLabel, $aiBtnType, $aiBtnId, $aiBtnClass, $aiBtnAttrs
 *
 * Requer CSS de components/ai-loading.php (incluído uma vez na página).
 */
$aiBtnLabel = $aiBtnLabel ?? 'Gerar com IA';
$aiBtnLoadingLabel = $aiBtnLoadingLabel ?? 'Gerando...';
$aiBtnType = $aiBtnType ?? 'submit';
$aiBtnId = $aiBtnId ?? 'aiSubmitBtn';
$aiBtnClass = $aiBtnClass ?? 'w-full py-4 px-6 rounded-xl font-semibold transition-all duration-300 transform hover:scale-[1.02]';
$aiBtnAttrs = $aiBtnAttrs ?? '';
?>
<button type="<?= htmlspecialchars($aiBtnType) ?>"
        id="<?= htmlspecialchars($aiBtnId) ?>"
        class="btn-ai-primary <?= htmlspecialchars($aiBtnClass) ?>"
        <?= $aiBtnAttrs ?>>
    <span class="btn-ai-label"><?= htmlspecialchars($aiBtnLabel) ?></span>
    <span class="btn-ai-loading" aria-live="polite">
        <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <?= htmlspecialchars($aiBtnLoadingLabel) ?>
    </span>
</button>
<?php
unset($aiBtnLabel, $aiBtnLoadingLabel, $aiBtnType, $aiBtnId, $aiBtnClass, $aiBtnAttrs);
?>
