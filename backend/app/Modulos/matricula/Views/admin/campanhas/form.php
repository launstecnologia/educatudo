<?php
$esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$campanha = $campanha ?? [];
$anos_letivos = $anos_letivos ?? [];
$planos = $planos ?? [];
$isEdit = !empty($campanha['id']);
$inicio = substr((string) ($campanha['inicio'] ?? ''), 0, 10);
$fim = substr((string) ($campanha['fim'] ?? ''), 0, 10);

$page_header_title = $isEdit ? 'Editar campanha' : 'Nova campanha';
$page_header_subtitle = 'Defina o prazo da rematrícula e o reajuste do ano seguinte.';
ob_start(); ?>
<a href="<?= URL ?>/admin/enrollment/campanhas" class="btn-secondary text-sm">← Campanhas</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../../Views/admin/_partials/page_header_list.php'; ?>

<?php if (!empty($status_message)): ?>
<div class="mb-4 p-3 rounded-xl text-sm <?= ($status_type ?? '') === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
    <?= $esc($status_message) ?>
</div>
<?php endif; ?>

<form method="POST" action="<?= $isEdit ? URL . '/admin/enrollment/campanhas/' . (int) $campanha['id'] : URL . '/admin/enrollment/campanhas' ?>" class="space-y-6">
    <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">

    <div class="bg-white rounded-xl shadow-lg p-6 space-y-5 w-full">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label for="nome" class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
                <input type="text" id="nome" name="nome" required maxlength="160"
                       value="<?= $esc($campanha['nome'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm"
                       placeholder="Ex.: Rematrícula 2027">
            </div>
            <div>
                <label for="ano_origem_id" class="block text-sm font-medium text-gray-700 mb-1">Ano de origem <span class="text-red-500">*</span></label>
                <select id="ano_origem_id" name="ano_origem_id" required <?= $isEdit ? 'disabled' : '' ?>
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                    <option value="">Selecione</option>
                    <?php foreach ($anos_letivos as $al): ?>
                    <option value="<?= (int) $al['id'] ?>" <?= (int) ($campanha['ano_origem_id'] ?? 0) === (int) $al['id'] ? 'selected' : '' ?>>
                        <?= $esc($al['ano']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($isEdit): ?>
                <input type="hidden" name="ano_origem_id" value="<?= (int) ($campanha['ano_origem_id'] ?? 0) ?>">
                <?php endif; ?>
            </div>
            <div>
                <label for="ano_destino_id" class="block text-sm font-medium text-gray-700 mb-1">Ano de destino <span class="text-red-500">*</span></label>
                <select id="ano_destino_id" name="ano_destino_id" required <?= $isEdit ? 'disabled' : '' ?>
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                    <option value="">Selecione</option>
                    <?php foreach ($anos_letivos as $al): ?>
                    <option value="<?= (int) $al['id'] ?>" <?= (int) ($campanha['ano_destino_id'] ?? 0) === (int) $al['id'] ? 'selected' : '' ?>>
                        <?= $esc($al['ano']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($isEdit): ?>
                <input type="hidden" name="ano_destino_id" value="<?= (int) ($campanha['ano_destino_id'] ?? 0) ?>">
                <?php endif; ?>
            </div>
            <div>
                <label for="inicio" class="block text-sm font-medium text-gray-700 mb-1">Início <span class="text-red-500">*</span></label>
                <input type="date" id="inicio" name="inicio" required value="<?= $esc($inicio) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
            </div>
            <div>
                <label for="fim" class="block text-sm font-medium text-gray-700 mb-1">Fim <span class="text-red-500">*</span></label>
                <input type="date" id="fim" name="fim" required value="<?= $esc($fim) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
            </div>
            <div>
                <label for="reajuste_pct" class="block text-sm font-medium text-gray-700 mb-1">Reajuste de referência (%)</label>
                <input type="number" step="0.01" id="reajuste_pct" name="reajuste_pct"
                       value="<?= $esc($campanha['reajuste_pct'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm"
                       placeholder="Ex.: 8.5">
                <p class="mt-1 text-xs text-gray-500">Informativo. O valor efetivo vem dos planos clonados no financeiro.</p>
            </div>
            <div>
                <label for="plano_padrao_id" class="block text-sm font-medium text-gray-700 mb-1">Plano padrão (destino)</label>
                <select id="plano_padrao_id" name="plano_padrao_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                    <option value="">Nenhum</option>
                    <?php foreach ($planos as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= (int) ($campanha['plano_padrao_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                        <?= $esc($p['nome'] ?? ('Plano #' . $p['id'])) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="space-y-3 pt-2">
            <label class="flex items-center gap-3 text-sm text-gray-700">
                <input type="checkbox" name="fila_auto_oferecer" value="1"
                       class="rounded border-gray-300 text-primary focus:ring-primary"
                       <?= !isset($campanha['fila_auto_oferecer']) || !empty($campanha['fila_auto_oferecer']) ? 'checked' : '' ?>>
                Oferecer vaga automaticamente ao primeiro da fila
            </label>
            <label class="flex items-center gap-3 text-sm text-gray-700">
                <input type="checkbox" name="exige_censo" value="1"
                       class="rounded border-gray-300 text-primary focus:ring-primary"
                       <?= !empty($campanha['exige_censo']) ? 'checked' : '' ?>>
                Exigir dados do Censo (nome da mãe) no portal da família
            </label>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="btn-primary">
            <i class="fa-solid fa-check mr-1.5"></i> <?= $isEdit ? 'Salvar' : 'Criar campanha' ?>
        </button>
        <a href="<?= URL ?>/admin/enrollment/campanhas" class="btn-secondary">Cancelar</a>
    </div>
</form>
