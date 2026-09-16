<?php
$item = is_array($item ?? null) ? $item : null;
$pais = is_array($pais ?? null) ? $pais : [];
$filhosPorPai = is_array($filhos_por_pai ?? null) ? $filhos_por_pai : [];
$schemaPronto = !empty($schema_pronto);
$ehEdicao = !empty($item['id']);
$rotuloId = (int) ($item['materia_rotulo_id'] ?? 0);
$aplicarEm = ((string) ($item['aplicar_em'] ?? 'boletim')) === 'ambos' ? 'ambos' : 'boletim';
$modo = ((string) ($item['modo'] ?? 'media')) === 'soma' ? 'soma' : 'media';
$nomesPai = [];
foreach ($pais as $p) {
    $nomesPai[(int) ($p['id'] ?? 0)] = (string) ($p['nome'] ?? '');
}
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= $ehEdicao ? 'Editar regra de nota da área' : 'Nova regra de nota da área' ?></h2>
            <p class="text-gray-600">Os desdobramentos vêm do componente pai. Aqui você só define como a nota da área é calculada no boletim.</p>
        </div>
        <a href="<?= URL ?>/admin/agrupamentos-componentes" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<?php include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php'; ?>

<?php if (!$schemaPronto): ?>
<div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-6">
    Rode a migration <code class="text-sm">2026_09_02_agrupamentos_componentes.sql</code> no painel Master.
</div>
<?php else: ?>
<form method="POST" action="<?= URL ?>/admin/agrupamentos-componentes<?= $ehEdicao ? '/' . (int) $item['id'] . '/update' : '' ?>" class="w-full">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Área (componente pai) <span class="text-red-500">*</span></label>
                <select name="materia_rotulo_id" id="materia-rotulo-id" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione a área</option>
                    <?php foreach ($pais as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $rotuloId === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $c['nome'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Cadastre o pai e os filhos em Componentes Curriculares.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nome no boletim <span class="text-red-500">*</span></label>
                <input type="text" name="nome" id="agrupamento-nome" required maxlength="150"
                       value="<?= htmlspecialchars((string) ($item['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Ex.: Língua Portuguesa">
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Modo</label>
                <select name="modo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="media" <?= $modo === 'media' ? 'selected' : '' ?>>Média</option>
                    <option value="soma" <?= $modo === 'soma' ? 'selected' : '' ?>>Soma</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Onde agrupar</label>
                <select name="aplicar_em" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="boletim" <?= $aplicarEm === 'boletim' ? 'selected' : '' ?>>Só no boletim</option>
                    <option value="ambos" <?= $aplicarEm === 'ambos' ? 'selected' : '' ?>>No boletim e nas notas</option>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Divisor fixo (opcional)</label>
                <input type="number" name="divisor" min="0" step="0.01"
                       value="<?= htmlspecialchars((string) ($item['divisor'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Vazio = quantidade de desdobramentos">
            </div>
            <div class="flex items-end pb-2">
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="checkbox" name="ativo" value="1" class="rounded border-gray-300" <?= !isset($item['ativo']) || (int) $item['ativo'] === 1 ? 'checked' : '' ?>>
                    Ativo
                </label>
            </div>
        </div>

        <div class="mb-6">
            <p class="block text-sm font-medium text-gray-700 mb-2">Desdobramentos desta área</p>
            <div id="lista-filhos" class="border border-gray-200 rounded-lg p-4 text-sm text-gray-700 min-h-[3rem]"></div>
            <p class="text-xs text-gray-500 mt-1">Alterar filhos: Componentes Curriculares. Diário e provas continuam em cada desdobramento.</p>
        </div>

        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/admin/agrupamentos-componentes" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</a>
            <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg hover:opacity-90">Salvar</button>
        </div>
    </div>
</form>
<script>
(function () {
    var filhosPorPai = <?= json_encode($filhosPorPai, JSON_UNESCAPED_UNICODE) ?>;
    var nomesPai = <?= json_encode($nomesPai, JSON_UNESCAPED_UNICODE) ?>;
    var sel = document.getElementById('materia-rotulo-id');
    var nomeEl = document.getElementById('agrupamento-nome');
    var lista = document.getElementById('lista-filhos');
    var nomeManual = <?= json_encode($ehEdicao && trim((string) ($item['nome'] ?? '')) !== '', JSON_UNESCAPED_UNICODE) ?>;

    function filhosDoPai(pai) {
        return filhosPorPai[pai] || filhosPorPai[String(pai)] || [];
    }

    function renderFilhos() {
        var pai = parseInt(sel.value, 10) || 0;
        var filhos = filhosDoPai(pai);
        if (!pai) {
            lista.innerHTML = '<span class="text-gray-400">Selecione a área para ver os desdobramentos.</span>';
            return;
        }
        if (!filhos.length) {
            lista.innerHTML = '<span class="text-amber-700">Essa área ainda não tem desdobramentos cadastrados.</span>';
            return;
        }
        lista.innerHTML = filhos.map(function (f) {
            var nome = (typeof f === 'object' && f) ? (f.nome || '') : '';
            return '<span class="inline-flex items-center px-2.5 py-1 mr-2 mb-2 rounded-full bg-slate-100 text-slate-800">' +
                String(nome).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>';
        }).join('');
    }

    if (sel && nomeEl) {
        sel.addEventListener('change', function () {
            var pai = parseInt(sel.value, 10) || 0;
            var sugerido = nomesPai[pai] || nomesPai[String(pai)] || '';
            if (!nomeManual && sugerido) {
                nomeEl.value = sugerido;
            }
            renderFilhos();
        });
        nomeEl.addEventListener('input', function () { nomeManual = true; });
    }
    renderFilhos();
})();
</script>
<?php endif; ?>
