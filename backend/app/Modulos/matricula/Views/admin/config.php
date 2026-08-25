<?php
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$config = $config ?? [];
$documentos_assinatura = $documentos_assinatura ?? [];
$pagante_modos = $pagante_modos ?? [];

$page_header_title    = 'Configuração de Matrícula';
$page_header_subtitle = 'Documentos, regras de contrato e pagantes.';
ob_start(); ?>
<a href="<?= URL ?>/admin/configuracao/assinatura-digital" class="btn-secondary text-sm">
    <i class="fa-solid fa-pen-nib mr-1.5"></i> Assinatura Digital
</a>
<a href="<?= URL ?>/admin/enrollment" class="btn-secondary text-sm">← Matrículas</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php'; ?>

<?php if (!empty($status_message)): ?>
<div class="mb-4 p-3 rounded-xl text-sm <?= ($status_type ?? '') === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
    <?= $esc($status_message) ?>
</div>
<?php endif; ?>

<form method="POST" action="<?= URL ?>/admin/enrollment/config" class="space-y-6" id="form-config-matricula">
    <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">

    <div class="bg-white rounded-xl shadow-lg p-6 space-y-5">
        <div>
            <h3 class="font-semibold text-gray-800">Assinatura e contrato</h3>
            <p class="text-sm text-gray-500 mt-1">Documento padrão (fallback) e regras de pagante. Tokens de ZapSign/DocuSign ficam em <a href="<?= URL ?>/admin/configuracao/assinatura-digital" class="text-primary underline">Assinatura Digital</a>.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="documento_codigo" class="block text-sm font-medium text-gray-700 mb-1">Documento padrão (fallback)</label>
                <select id="documento_codigo" name="documento_codigo"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                    <?php foreach ($documentos_assinatura as $codigo => $rotulo): ?>
                    <option value="<?= $esc($codigo) ?>"
                        <?= ($config['documento_codigo'] ?? '') === $codigo ? 'selected' : '' ?>>
                        <?= $esc($rotulo) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-gray-500">Usado se não houver regra ativa do tipo Matrícula.</p>
            </div>
            <div>
                <label for="pagante_modo" class="block text-sm font-medium text-gray-700 mb-1">Modo de pagante</label>
                <select id="pagante_modo" name="pagante_modo"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                    <?php foreach ($pagante_modos as $modo => $rotulo): ?>
                    <option value="<?= $esc($modo) ?>"
                        <?= ($config['pagante_modo'] ?? 'um') === $modo ? 'selected' : '' ?>>
                        <?= $esc($rotulo) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="space-y-3">
            <label class="flex items-center gap-3 text-sm text-gray-700">
                <input type="checkbox" name="contrato_com_valores" value="1"
                       class="rounded border-gray-300 text-primary focus:ring-primary"
                       <?= !empty($config['contrato_com_valores']) ? 'checked' : '' ?>>
                Incluir valores no contrato
            </label>
            <label class="flex items-center gap-3 text-sm text-gray-700">
                <input type="checkbox" name="assinar_contrato" value="1"
                       class="rounded border-gray-300 text-primary focus:ring-primary"
                       <?= !empty($config['assinar_contrato']) ? 'checked' : '' ?>>
                Assinar contrato de matrícula
            </label>
            <label class="flex items-center gap-3 text-sm text-gray-700">
                <input type="checkbox" name="assinar_ficha" value="1"
                       class="rounded border-gray-300 text-primary focus:ring-primary"
                       <?= !empty($config['assinar_ficha']) ? 'checked' : '' ?>>
                Assinar ficha de matrícula
            </label>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <h3 class="font-semibold text-gray-800">
                    <i class="fa-solid fa-link mr-1 text-primary"></i> Regras de contratos
                </h3>
                <p class="text-sm text-gray-500 mt-1">
                    Vincule um modelo de documento a cada tipo (matrícula, material, uniforme…).
                    Variáveis: <code class="text-xs bg-gray-100 px-1 rounded">{{aluno_nome}}</code> ou legado <code class="text-xs bg-gray-100 px-1 rounded">@aluno</code>.
                </p>
            </div>
            <?php if (!empty($schema_regras_ok)): ?>
            <button type="button" id="btn-add-regra"
                    class="btn-secondary inline-flex items-center px-3 py-2 rounded-lg text-sm shrink-0">
                <i class="fa-solid fa-plus mr-1.5"></i> Adicionar regra
            </button>
            <?php endif; ?>
        </div>

        <?php if (empty($schema_regras_ok)): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Migration <code class="text-xs">2026_08_07_matricula_contrato_regras</code> ainda não aplicada neste tenant.
            Rode pelo Master → Migrações.
        </div>
        <?php else: ?>
        <div id="regras-contrato-list" class="space-y-3">
            <?php
            $regras = $regras_contrato ?? [];
            if ($regras === []) {
                $regras = [
                    ['nome' => 'Contrato de Matrícula', 'tipo' => 'matricula', 'modelo_documento_codigo' => 'contrato_matricula', 'ativo' => 1, 'enviar_zapsign' => 1],
                    ['nome' => 'Contrato de Material Didático', 'tipo' => 'material_didatico', 'modelo_documento_codigo' => 'contrato_material_didatico', 'ativo' => 1, 'enviar_zapsign' => 1],
                ];
            }
            foreach ($regras as $i => $regra):
            ?>
            <div class="regra-row grid grid-cols-1 lg:grid-cols-12 gap-3 p-4 border border-gray-200 rounded-xl bg-gray-50/50" data-regra-row>
                <div class="lg:col-span-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nome</label>
                    <input type="text" name="regras[<?= (int)$i ?>][nome]" required
                           value="<?= $esc($regra['nome'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                           placeholder="Ex.: Contrato de Matrícula">
                </div>
                <div class="lg:col-span-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
                    <select name="regras[<?= (int)$i ?>][tipo]" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                        <?php foreach (($tipos_contrato ?? []) as $tipo => $label): ?>
                        <option value="<?= $esc($tipo) ?>" <?= ($regra['tipo'] ?? '') === $tipo ? 'selected' : '' ?>>
                            <?= $esc($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lg:col-span-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Modelo de documento</label>
                    <select name="regras[<?= (int)$i ?>][modelo_documento_codigo]" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                        <?php foreach ($documentos_assinatura as $codigo => $rotulo): ?>
                        <option value="<?= $esc($codigo) ?>"
                            <?= ($regra['modelo_documento_codigo'] ?? '') === $codigo ? 'selected' : '' ?>>
                            <?= $esc($rotulo) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lg:col-span-2 flex flex-col justify-end gap-2 pb-1">
                    <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                        <input type="checkbox" name="regras[<?= (int)$i ?>][ativo]" value="1"
                               class="rounded border-gray-300 text-primary"
                               <?= !isset($regra['ativo']) || !empty($regra['ativo']) ? 'checked' : '' ?>>
                        Ativa
                    </label>
                    <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                        <input type="checkbox" name="regras[<?= (int)$i ?>][enviar_zapsign]" value="1"
                               class="rounded border-gray-300 text-primary"
                               <?= !isset($regra['enviar_zapsign']) || !empty($regra['enviar_zapsign']) ? 'checked' : '' ?>>
                        ZapSign
                    </label>
                </div>
                <div class="lg:col-span-1 flex items-end justify-end pb-1">
                    <button type="button" class="btn-regra-remover text-red-500 hover:text-red-700 p-2" title="Remover">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="text-xs text-gray-500">
            Crie os modelos em <a href="<?= URL ?>/admin/modelos-documentos" class="text-primary underline">Modelos de Documentos</a>
            e vincule aqui. Um tipo só pode ter uma regra.
        </p>
        <?php endif; ?>
    </div>

    <?php
    $checklist_itens = $checklist_itens ?? [];
    $checklistPorTipo = ['nova' => [], 'rematricula' => [], 'transferencia' => []];
    foreach ($checklist_itens as $item) {
        $tp = (string) ($item['tipo_processo'] ?? 'nova');
        if (!isset($checklistPorTipo[$tp])) {
            $tp = 'nova';
        }
        $checklistPorTipo[$tp][] = $item;
    }
    $checklistTiposLabel = ['nova' => 'Matrícula nova', 'rematricula' => 'Rematrícula', 'transferencia' => 'Transferência'];
    ?>
    <div class="bg-white rounded-xl shadow-lg p-6 space-y-5">
        <div>
            <h3 class="font-semibold text-gray-800">Checklist documental</h3>
            <p class="text-sm text-gray-500 mt-1">Itens obrigatórios travam a enturmação até o anexo correspondente.</p>
        </div>
        <?php if ($checklist_itens === []): ?>
        <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-3">
            Rode a migration <code class="text-xs">2026_08_15_matricula_secretaria_ciclo</code> para habilitar o checklist.
        </p>
        <?php else: ?>
        <?php $idxCheck = 0; foreach ($checklistPorTipo as $tipo => $itens): ?>
        <div>
            <h4 class="text-sm font-semibold text-gray-700 mb-2"><?= htmlspecialchars($checklistTiposLabel[$tipo]) ?></h4>
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Código</th>
                            <th class="px-3 py-2 text-left">Rótulo</th>
                            <th class="px-3 py-2 text-center">Obrigatório</th>
                            <th class="px-3 py-2 text-center">Ativo</th>
                            <th class="px-3 py-2 text-left">Ordem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $item): ?>
                        <tr class="border-t border-gray-100">
                            <td class="px-3 py-2">
                                <input type="hidden" name="checklist[<?= $idxCheck ?>][id]" value="<?= (int) ($item['id'] ?? 0) ?>">
                                <input type="hidden" name="checklist[<?= $idxCheck ?>][tipo_processo]" value="<?= htmlspecialchars($tipo) ?>">
                                <input type="text" name="checklist[<?= $idxCheck ?>][codigo]" value="<?= htmlspecialchars($item['codigo'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded px-2 py-1 text-xs" maxlength="80">
                            </td>
                            <td class="px-3 py-2">
                                <input type="text" name="checklist[<?= $idxCheck ?>][rotulo]" value="<?= htmlspecialchars($item['rotulo'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                            </td>
                            <td class="px-3 py-2 text-center">
                                <input type="hidden" name="checklist[<?= $idxCheck ?>][obrigatorio]" value="0">
                                <input type="checkbox" name="checklist[<?= $idxCheck ?>][obrigatorio]" value="1" class="rounded border-gray-300 text-primary"
                                       <?= !empty($item['obrigatorio']) ? 'checked' : '' ?>>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <input type="hidden" name="checklist[<?= $idxCheck ?>][ativo]" value="0">
                                <input type="checkbox" name="checklist[<?= $idxCheck ?>][ativo]" value="1" class="rounded border-gray-300 text-primary"
                                       <?= !empty($item['ativo']) ? 'checked' : '' ?>>
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" min="1" name="checklist[<?= $idxCheck ?>][ordem]" value="<?= (int) ($item['ordem'] ?? 1) ?>"
                                       class="w-20 border border-gray-300 rounded px-2 py-1 text-sm">
                            </td>
                        </tr>
                        <?php $idxCheck++; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="btn-primary">
            <i class="fa-solid fa-check mr-1.5"></i> Salvar configuração
        </button>
        <a href="<?= URL ?>/admin/enrollment" class="btn-secondary">Cancelar</a>
    </div>
</form>

<?php if (!empty($schema_regras_ok)): ?>
<template id="tpl-regra-row">
    <div class="regra-row grid grid-cols-1 lg:grid-cols-12 gap-3 p-4 border border-gray-200 rounded-xl bg-gray-50/50" data-regra-row>
        <div class="lg:col-span-3">
            <label class="block text-xs font-medium text-gray-600 mb-1">Nome</label>
            <input type="text" name="regras[__I__][nome]" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                   placeholder="Ex.: Contrato de Uniforme">
        </div>
        <div class="lg:col-span-3">
            <label class="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
            <select name="regras[__I__][tipo]" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                <?php foreach (($tipos_contrato ?? []) as $tipo => $label): ?>
                <option value="<?= $esc($tipo) ?>"><?= $esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="lg:col-span-3">
            <label class="block text-xs font-medium text-gray-600 mb-1">Modelo de documento</label>
            <select name="regras[__I__][modelo_documento_codigo]" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                <?php foreach ($documentos_assinatura as $codigo => $rotulo): ?>
                <option value="<?= $esc($codigo) ?>"><?= $esc($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="lg:col-span-2 flex flex-col justify-end gap-2 pb-1">
            <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                <input type="checkbox" name="regras[__I__][ativo]" value="1" class="rounded border-gray-300 text-primary" checked> Ativa
            </label>
            <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                <input type="checkbox" name="regras[__I__][enviar_zapsign]" value="1" class="rounded border-gray-300 text-primary" checked> ZapSign
            </label>
        </div>
        <div class="lg:col-span-1 flex items-end justify-end pb-1">
            <button type="button" class="btn-regra-remover text-red-500 hover:text-red-700 p-2" title="Remover">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    </div>
</template>
<script>
(function () {
    var list = document.getElementById('regras-contrato-list');
    var tpl = document.getElementById('tpl-regra-row');
    var btn = document.getElementById('btn-add-regra');
    if (!list || !tpl || !btn) return;
    var idx = list.querySelectorAll('[data-regra-row]').length;
    btn.addEventListener('click', function () {
        var html = tpl.innerHTML.replace(/__I__/g, String(idx++));
        list.insertAdjacentHTML('beforeend', html);
    });
    list.addEventListener('click', function (e) {
        var rem = e.target.closest('.btn-regra-remover');
        if (!rem) return;
        var row = rem.closest('[data-regra-row]');
        if (row && list.querySelectorAll('[data-regra-row]').length > 1) {
            row.remove();
        }
    });
})();
</script>
<?php endif; ?>
