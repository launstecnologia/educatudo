<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$modelos = is_array($modelos ?? null) ? $modelos : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];

$page_header_title = 'Emitir em lote';
$page_header_subtitle = 'Os campos do layout valem iguais para todos os alunos da turma.';
ob_start(); ?>
<a href="<?= URL ?>/admin/modelos-documentos" class="text-gray-600 hover:text-gray-900 text-sm">← Layout de documentos</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<?php if ($modelos === []): ?>
<div class="bg-white rounded-xl shadow-lg p-6">
    <p class="text-sm text-gray-600">Nenhum modelo tem campos da emissão. No editor do layout, crie data, horário, local ou valor e arraste o campo para o texto.</p>
</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-lg p-6">
    <form method="get" action="<?= URL ?>/admin/modelos-documentos/emitir-lote/pdf" target="_blank" id="form-emitir-lote">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="modelo_id" class="block text-sm font-medium text-gray-700 mb-2">Documento</label>
                <select id="modelo_id" name="modelo_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="">Selecione</option>
                    <?php foreach ($modelos as $modelo): ?>
                    <option value="<?= (int) ($modelo['id'] ?? 0) ?>"><?= $esc($modelo['nome'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-2">Turma</label>
                <select id="turma_id" name="turma_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="">Selecione</option>
                    <?php foreach ($turmas as $turma): ?>
                    <option value="<?= (int) ($turma['id'] ?? 0) ?>"><?= $esc($turma['nome'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php foreach ($modelos as $modelo):
            $mid = (int) ($modelo['id'] ?? 0);
        ?>
        <fieldset class="js-campos-modelo mb-6 hidden" data-modelo="<?= $mid ?>" disabled>
            <legend class="text-sm font-semibold text-gray-900 mb-4">Campos de <?= $esc($modelo['nome'] ?? '') ?></legend>
            <?php
            $campos_variaveis = is_array($modelo['campos'] ?? null) ? $modelo['campos'] : [];
            $campos_variaveis_prefix = 'lote-' . $mid;
            include __DIR__ . '/../../../../Views/admin/_partials/campos_variaveis_documento.php';
            ?>
        </fieldset>
        <?php endforeach; ?>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold">
                <i class="fa-solid fa-print mr-2"></i> Gerar PDF
            </button>
        </div>
    </form>
</div>
<script src="<?= URL ?>/static/js/campos-variaveis-documento.js?v=1"></script>
<script>
(function () {
    var sel = document.getElementById('modelo_id');
    if (!sel) return;
    function mostrar() {
        var id = sel.value;
        document.querySelectorAll('.js-campos-modelo').forEach(function (box) {
            var ativo = box.getAttribute('data-modelo') === id;
            box.classList.toggle('hidden', !ativo);
            box.disabled = !ativo;
        });
    }
    sel.addEventListener('change', mostrar);
    mostrar();
})();
</script>
<?php endif; ?>
