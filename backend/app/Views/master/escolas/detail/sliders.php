<?php
$layout_config = $layout_config ?? [];
$escola_id = (int) ($escola_id ?? 0);
$items = [];
if (!empty($layout_config['dashboard_slider_items'])) {
    $decoded = json_decode((string) $layout_config['dashboard_slider_items'], true);
    if (is_array($decoded)) {
        $items = $decoded;
    }
}
$csrf_token = $csrf_token ?? '';
?>

<div class="bg-white rounded-xl shadow border border-slate-200 overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-200">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-slate-800">Sliders do Dashboard do Aluno</h3>
                <p class="text-sm text-slate-500 mt-1">Pré-visualize, ordene e configure as imagens do carrossel principal.</p>
            </div>
            <span class="hidden sm:inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium">
                Original preservada
            </span>
        </div>
        <div class="mt-4 rounded-lg bg-blue-50 border border-blue-100 px-4 py-3 text-xs text-blue-800">
            Imagens acima de 1 MB, 1600 px de largura ou 900 px de altura são exibidas em WebP compactado. A original permanece armazenada para futuras utilizações.
        </div>
    </div>

    <form method="post" action="<?= URL ?>/master/escolas/<?= $escola_id ?>/sliders" id="form-master-sliders" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="dashboard_slider_items" id="master-slider-items-json" value="">
        <div id="master-slider-list" class="p-6 space-y-5"></div>
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
            <button type="button" id="btn-master-add-slide" class="inline-flex justify-center items-center px-4 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-100 text-sm font-medium">
                + Adicionar slide
            </button>
            <button type="submit" class="inline-flex justify-center items-center px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium shadow-sm">
                Salvar slides
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    const initial = <?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const list = document.getElementById('master-slider-list');
    const inputJson = document.getElementById('master-slider-items-json');
    const addBtn = document.getElementById('btn-master-add-slide');
    const form = document.getElementById('form-master-sliders');

    function esc(v) {
        return String(v || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function render(items) {
        list.innerHTML = '';
        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'rounded-xl border-2 border-dashed border-slate-200 py-12 px-6 text-center';
            empty.innerHTML = '<p class="text-sm font-medium text-slate-600">Nenhum slide cadastrado</p><p class="text-xs text-slate-400 mt-1">Adicione a primeira imagem para montar o carrossel.</p>';
            list.appendChild(empty);
            return;
        }
        items.forEach((item, idx) => {
            const row = document.createElement('div');
            const imageUrl = item.image_url || '';
            const originalUrl = item.original_image_url || imageUrl;
            const optimized = !!item.image_optimized;
            row.className = 'master-slider-item border border-slate-200 rounded-xl overflow-hidden bg-white';
            row.dataset.originalImageUrl = originalUrl;
            row.dataset.imageOptimized = optimized ? '1' : '0';
            row.innerHTML = `
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-slate-200 text-slate-700 text-xs font-semibold">${idx + 1}</span>
                        <span class="text-sm font-medium text-slate-700">Slide ${idx + 1}</span>
                        <span class="sd-status px-2 py-0.5 rounded-full text-[11px] font-medium ${optimized ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'}">
                            ${optimized ? 'WebP otimizado' : 'Imagem original'}
                        </span>
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="inline-flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                            <input type="checkbox" class="sd-active rounded border-slate-300 text-blue-600" ${item.active ? 'checked' : ''}>
                            Ativo
                        </label>
                        <button type="button" class="sd-remove text-red-600 hover:text-red-800 text-xs font-medium">Remover</button>
                    </div>
                </div>
                <div class="p-4 grid grid-cols-1 xl:grid-cols-12 gap-5">
                    <div class="xl:col-span-5">
                        <div class="sd-preview relative overflow-hidden rounded-xl bg-slate-100 border border-slate-200 aspect-[16/6]">
                            ${imageUrl
                                ? `<img src="${esc(imageUrl)}" alt="Prévia do slide ${idx + 1}" class="w-full h-full object-cover">`
                                : '<div class="w-full h-full flex items-center justify-center text-xs text-slate-400">Prévia da imagem</div>'}
                        </div>
                        <div class="mt-3">
                            <label class="block text-xs font-medium text-slate-600 mb-1.5">Substituir imagem</label>
                            <input type="file" name="slider_images[]" class="sd-file block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 file:font-medium hover:file:bg-blue-100" accept=".jpg,.jpeg,.png,.gif,.webp">
                            <p class="sd-file-info text-[11px] text-slate-400 mt-1.5">JPG, PNG, WebP ou GIF · máximo 10 MB</p>
                        </div>
                        ${originalUrl ? `<a href="${esc(originalUrl)}" target="_blank" rel="noopener" class="inline-flex mt-2 text-xs font-medium text-blue-600 hover:text-blue-800">Abrir imagem original ↗</a>` : ''}
                    </div>
                    <div class="xl:col-span-7 grid grid-cols-1 md:grid-cols-2 gap-4 content-start">
                        <label class="md:col-span-2 block">
                            <span class="block text-xs font-medium text-slate-600 mb-1.5">Título</span>
                            <input class="sd-title w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Ex.: Conheça a Tudinha 2.0" value="${esc(item.title || '')}">
                        </label>
                        <label class="md:col-span-2 block">
                            <span class="block text-xs font-medium text-slate-600 mb-1.5">URL da imagem</span>
                            <input class="sd-image w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Cole uma URL ou envie um arquivo" value="${esc(imageUrl)}">
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-slate-600 mb-1.5">Ação ao clicar</span>
                            <select class="sd-action w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm bg-white">
                                <option value="external" ${(item.action_type || 'external') === 'external' ? 'selected' : ''}>Link externo</option>
                                <option value="module" ${(item.action_type || '') === 'module' ? 'selected' : ''}>Módulo interno</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-slate-600 mb-1.5">Módulo</span>
                            <select class="sd-module w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm bg-white">
                                <option value="">Selecione o módulo</option>
                                <option value="chat_tudinha" ${(item.module_key || '') === 'chat_tudinha' ? 'selected' : ''}>Tudinha</option>
                                <option value="chat_tudinha_2" ${(item.module_key || '') === 'chat_tudinha_2' ? 'selected' : ''}>Tudinha 2.0</option>
                                <option value="educahits" ${(item.module_key || '') === 'educahits' ? 'selected' : ''}>EducaHits</option>
                                <option value="redacoes" ${(item.module_key || '') === 'redacoes' ? 'selected' : ''}>Redações</option>
                                <option value="exercicios" ${(item.module_key || '') === 'exercicios' ? 'selected' : ''}>Exercícios</option>
                                <option value="jornadas" ${(item.module_key || '') === 'jornadas' ? 'selected' : ''}>Jornadas</option>
                                <option value="simulados" ${(item.module_key || '') === 'simulados' ? 'selected' : ''}>Simulados</option>
                                <option value="notas" ${(item.module_key || '') === 'notas' ? 'selected' : ''}>Notas</option>
                                <option value="boletim" ${(item.module_key || '') === 'boletim' ? 'selected' : ''}>Boletim</option>
                            </select>
                        </label>
                        <label class="md:col-span-2 block">
                            <span class="block text-xs font-medium text-slate-600 mb-1.5">Destino do link externo</span>
                            <input class="sd-link w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://..." value="${esc(item.link_url || '')}">
                        </label>
                    </div>
                </div>
            `;
            row.querySelector('.sd-remove').addEventListener('click', () => {
                items.splice(idx, 1);
                render(items);
            });
            row.querySelector('.sd-file').addEventListener('change', (event) => {
                const file = event.target.files && event.target.files[0];
                if (!file) return;
                const preview = row.querySelector('.sd-preview');
                const objectUrl = URL.createObjectURL(file);
                preview.innerHTML = `<img src="${objectUrl}" alt="Nova prévia do slide ${idx + 1}" class="w-full h-full object-cover">`;
                row.querySelector('.sd-file-info').textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB`;
                const previewImage = preview.querySelector('img');
                previewImage.addEventListener('load', () => {
                    const seraOtimizada = file.size > 1048576
                        || previewImage.naturalWidth > 1600
                        || previewImage.naturalHeight > 900;
                    row.querySelector('.sd-status').textContent = seraOtimizada ? 'Será otimizada' : 'Nova imagem';
                    row.querySelector('.sd-status').className = 'sd-status px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-100 text-blue-700';
                    URL.revokeObjectURL(objectUrl);
                }, { once: true });
            });
            row.querySelector('.sd-image').addEventListener('change', (event) => {
                const url = event.target.value.trim();
                if (!url) return;
                row.querySelector('.sd-preview').innerHTML = `<img src="${esc(url)}" alt="Prévia do slide ${idx + 1}" class="w-full h-full object-cover">`;
                row.dataset.originalImageUrl = url;
                row.dataset.imageOptimized = '0';
            });
            list.appendChild(row);
        });
    }

    function collect() {
        const rows = list.querySelectorAll('.master-slider-item');
        const out = [];
        rows.forEach((row, uploadIndex) => {
            const imageUrl = (row.querySelector('.sd-image')?.value || '').trim();
            const hasFile = !!(row.querySelector('.sd-file')?.value || '').trim();
            if (!imageUrl && !hasFile) return;
            out.push({
                title: (row.querySelector('.sd-title')?.value || '').trim(),
                upload_index: uploadIndex,
                image_url: imageUrl,
                original_image_url: row.dataset.originalImageUrl || imageUrl,
                image_optimized: row.dataset.imageOptimized === '1' ? 1 : 0,
                link_url: (row.querySelector('.sd-link')?.value || '').trim(),
                action_type: (row.querySelector('.sd-action')?.value || 'external'),
                module_key: (row.querySelector('.sd-module')?.value || '').trim(),
                active: !!row.querySelector('.sd-active')?.checked
            });
        });
        inputJson.value = JSON.stringify(out);
    }

    const items = Array.isArray(initial) ? initial.slice() : [];
    addBtn.addEventListener('click', () => {
        items.push({ title: '', image_url: '', link_url: '', action_type: 'external', module_key: '', active: true });
        render(items);
    });
    form.addEventListener('submit', collect);
    render(items);
})();
</script>
