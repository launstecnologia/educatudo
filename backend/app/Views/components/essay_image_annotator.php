<?php
/**
 * Componente: anotador de imagem da redação (Pointer Events).
 */
$annotatorId = $annotator_id ?? 'essayAnnotator';
$imageUrl = $image_url ?? '';
$initialAnnotations = $initial_annotations ?? null;
$readonly = !empty($readonly);
$submissionId = (int) ($submission_id ?? 0);
$saveUrl = $save_url ?? '';
$csrfToken = $csrf_token ?? '';
$toolbarId = $annotatorId . '-toolbar';
$annotatorColors = ['#ef4444', '#2563eb', '#16a34a', '#ca8a04', '#9333ea', '#111827'];
$annotatorJsVersion = '20250612';
?>
<style>
.essay-annotator-wrap { width: 100%; }
.essay-annotator-toolbar {
    display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
    padding: 8px; background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 10px 10px 0 0;
}
.essay-annotator-btn {
    width: 36px; height: 36px; border-radius: 8px; border: 1px solid #d1d5db;
    background: #fff; color: #374151; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 600; line-height: 1; padding: 0;
    touch-action: manipulation; -webkit-tap-highlight-color: transparent;
}
.essay-annotator-btn.is-active { background: #7c3aed; color: #fff; border-color: #7c3aed; }
.essay-annotator-btn.is-disabled,
.essay-annotator-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.essay-annotator-btn-icon {
    display: block; font-size: 17px; line-height: 1; pointer-events: none;
}
.essay-annotator-btn.is-active .essay-annotator-btn-icon { color: #fff; }
.essay-annotator-color {
    width: 24px; height: 24px; border-radius: 50%; border: 2px solid #fff;
    box-shadow: 0 0 0 1px #d1d5db; cursor: pointer; padding: 0;
}
.essay-annotator-color.is-active { box-shadow: 0 0 0 2px #7c3aed; }
.essay-annotator-sep { width: 1px; height: 24px; background: #e5e7eb; margin: 0 4px; flex-shrink: 0; }
.essay-annotator-stage-wrap { width: 100%; }
.essay-annotator-stage {
    width: 100%; background: #f3f4f6;
    border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 10px 10px;
    overflow: auto; max-height: 75vh; touch-action: none;
}
.essay-annotator-inner {
    position: relative; width: 100%; line-height: 0;
}
.essay-annotator-image {
    display: block; width: 100%; height: auto; max-width: 100%;
    user-select: none; -webkit-user-drag: none;
}
.essay-annotator-canvas {
    position: absolute; left: 0; top: 0;
    cursor: crosshair; touch-action: none; pointer-events: auto;
}
.essay-annotator-canvas.tool-text { cursor: text; }
.essay-annotator-text-editor {
    position: absolute; z-index: 20; min-width: 160px; max-width: min(320px, 85%);
    background: #fff; border: 2px solid #7c3aed; border-radius: 8px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
    padding: 0; overflow: hidden;
}
.essay-annotator-text-editor textarea {
    display: block; width: 100%; min-height: 72px; max-height: 140px;
    padding: 10px 12px; border: none; outline: none; resize: vertical;
    font: 600 16px/1.4 system-ui, -apple-system, sans-serif;
    background: #fff; color: #111827; caret-color: currentColor;
}
.essay-annotator-text-editor-hint {
    padding: 6px 10px; font-size: 11px; color: #6b7280;
    background: #f9fafb; border-top: 1px solid #e5e7eb;
}
.essay-annotator-text-editor-actions {
    display: flex; justify-content: flex-end; gap: 6px;
    padding: 6px 8px; background: #f9fafb; border-top: 1px solid #e5e7eb;
}
.essay-annotator-text-editor-actions button {
    font-size: 12px; font-weight: 600; border-radius: 6px; padding: 5px 10px; cursor: pointer;
}
.essay-annotator-text-editor-cancel {
    border: 1px solid #d1d5db; background: #fff; color: #374151;
}
.essay-annotator-text-editor-ok {
    border: none; background: #7c3aed; color: #fff;
}
.essay-annotator-actions { margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap; }
</style>

<div class="essay-annotator-wrap">
<?php if (!$readonly): ?>
<div id="<?= htmlspecialchars($toolbarId) ?>" class="essay-annotator-toolbar" role="toolbar" aria-label="Ferramentas de correção na imagem">
    <button type="button" data-tool="pen" class="essay-annotator-btn is-active" title="Caneta" aria-label="Caneta"><span class="essay-annotator-btn-icon" aria-hidden="true">✏</span></button>
    <button type="button" data-tool="eraser" class="essay-annotator-btn" title="Borracha" aria-label="Borracha"><span class="essay-annotator-btn-icon" aria-hidden="true">⌫</span></button>
    <button type="button" data-tool="text" class="essay-annotator-btn" title="Texto" aria-label="Texto"><span class="essay-annotator-btn-icon" aria-hidden="true">T</span></button>
    <span class="essay-annotator-sep" aria-hidden="true"></span>
    <?php foreach ($annotatorColors as $i => $color): ?>
    <button type="button" class="essay-annotator-color<?= $i === 0 ? ' is-active' : '' ?>" data-color="<?= htmlspecialchars($color) ?>" style="background:<?= htmlspecialchars($color) ?>" aria-label="Cor <?= htmlspecialchars($color) ?>"></button>
    <?php endforeach; ?>
    <span class="essay-annotator-sep" aria-hidden="true"></span>
    <button type="button" data-action="undo" class="essay-annotator-btn" title="Desfazer" aria-label="Desfazer"><span class="essay-annotator-btn-icon" aria-hidden="true">↶</span></button>
    <button type="button" data-action="clear" class="essay-annotator-btn" title="Limpar rabiscos" aria-label="Limpar rabiscos"><span class="essay-annotator-btn-icon" aria-hidden="true">🗑</span></button>
</div>
<?php endif; ?>
<div id="<?= htmlspecialchars($annotatorId) ?>" class="essay-annotator-mount essay-annotator-stage-wrap" data-toolbar-id="<?= htmlspecialchars($toolbarId) ?>"></div>
</div>

<?php if (!$readonly && $saveUrl !== ''): ?>
<div class="essay-annotator-actions">
    <button type="button" id="<?= htmlspecialchars($annotatorId) ?>-save" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-medium">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Salvar rabiscos na imagem
    </button>
    <span id="<?= htmlspecialchars($annotatorId) ?>-status" class="text-sm text-gray-500 self-center"></span>
</div>
<?php endif; ?>

<script src="<?= URL ?>/public/static/js/essay-image-annotator.js?v=<?= htmlspecialchars($annotatorJsVersion) ?>"></script>
<script>
(function() {
    var mount = document.getElementById(<?= json_encode($annotatorId) ?>);
    if (!mount || typeof EssayImageAnnotator === 'undefined') return;

    var annotator = new EssayImageAnnotator(mount, {
        imageUrl: <?= json_encode($imageUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        readonly: <?= $readonly ? 'true' : 'false' ?>,
        initialData: <?= json_encode($initialAnnotations ?? new stdClass(), JSON_UNESCAPED_UNICODE) ?>,
        toolbarId: <?= json_encode($toolbarId) ?>
    });

    <?php if (!$readonly && $saveUrl !== ''): ?>
    var saveBtn = document.getElementById(<?= json_encode($annotatorId . '-save') ?>);
    var statusEl = document.getElementById(<?= json_encode($annotatorId . '-status') ?>);
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            saveBtn.disabled = true;
            if (statusEl) statusEl.textContent = 'Salvando...';
            annotator.exportFlattenedBase64().then(function(base64) {
                var fd = new FormData();
                fd.append('_token', <?= json_encode($csrfToken) ?>);
                fd.append('annotations_json', JSON.stringify(annotator.getAnnotations()));
                fd.append('annotated_image_base64', base64);
                return fetch(<?= json_encode($saveUrl) ?>, { method: 'POST', body: fd });
            }).then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    if (statusEl) statusEl.textContent = d.message || 'Salvo!';
                } else {
                    alert(d.error || 'Erro ao salvar');
                    if (statusEl) statusEl.textContent = '';
                }
            }).catch(function() {
                alert('Erro de conexão ao salvar rabiscos.');
                if (statusEl) statusEl.textContent = '';
            }).finally(function() {
                saveBtn.disabled = false;
            });
        });
    }
    <?php endif; ?>
})();
</script>
