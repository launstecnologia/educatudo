<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Configurações ⚙️
            </h2>
            <p class="text-gray-600">
                Gerencie as configurações gerais do sistema
            </p>
        </div>
    </div>
</div>

<div id="slider-dashboard" class="mt-8 bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Slider do Dashboard do Aluno</h3>
        <p class="text-sm text-gray-500 mt-1">Cadastre imagens com link clicável. Proporção recomendada: 16:9 (ex.: 1600×900). A imagem é exibida inteira, sem corte.</p>
    </div>
    <div class="p-6">
        <form method="post" action="<?= URL ?>/admin/settings/sliders-dashboard" id="form-slider-dashboard" enctype="multipart/form-data">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="dashboard_slider_items" id="dashboard-slider-items-json" value="">
            <div id="dashboard-slider-list" class="space-y-3 mb-4"></div>
            <div class="flex items-center justify-between">
                <button type="button" id="btn-add-slide-dashboard" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 text-sm">+ Adicionar slide</button>
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm hover:opacity-90">Salvar slides</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const initial = <?= json_encode($dashboard_slider_items ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const list = document.getElementById('dashboard-slider-list');
    const inputJson = document.getElementById('dashboard-slider-items-json');
    const addBtn = document.getElementById('btn-add-slide-dashboard');
    const form = document.getElementById('form-slider-dashboard');

    function escapeHtml(v) {
        return String(v || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function render(items) {
        list.innerHTML = '';
        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'text-sm text-gray-500';
            empty.textContent = 'Nenhum slide cadastrado.';
            list.appendChild(empty);
            return;
        }
        items.forEach((item, idx) => {
            const row = document.createElement('div');
            row.className = 'border border-gray-200 rounded-lg p-3';
            row.innerHTML = `
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-2 mb-2">
                    <input class="sd-title lg:col-span-2 px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Título" value="${escapeHtml(item.title || '')}">
                    <input class="sd-image lg:col-span-4 px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="URL da imagem (ou use upload)" value="${escapeHtml(item.image_url || '')}">
                    <input type="file" name="slider_images[]" class="sd-file lg:col-span-3 px-2 py-2 border border-gray-300 rounded-lg text-xs" accept=".jpg,.jpeg,.png,.gif,.webp,.svg">
                    <select class="sd-action lg:col-span-2 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="external" ${(item.action_type || 'external') === 'external' ? 'selected' : ''}>Link externo</option>
                        <option value="module" ${(item.action_type || '') === 'module' ? 'selected' : ''}>Módulo interno</option>
                    </select>
                    <div class="lg:col-span-1 flex items-center justify-between lg:justify-end gap-3">
                        <label class="text-xs text-gray-600 whitespace-nowrap"><input type="checkbox" class="sd-active mr-1" ${item.active ? 'checked' : ''}>Ativo</label>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-2">
                    <select class="sd-module lg:col-span-4 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">Selecione módulo</option>
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
                    <input class="sd-link lg:col-span-7 px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="URL externa (usada quando ação = link externo)" value="${escapeHtml(item.link_url || '')}">
                    <div class="lg:col-span-1 flex items-center justify-end">
                        <button type="button" class="sd-remove text-red-600 hover:text-red-800 text-sm">Remover</button>
                    </div>
                </div>
            `;
            row.querySelector('.sd-remove').addEventListener('click', () => {
                items.splice(idx, 1);
                render(items);
            });
            list.appendChild(row);
        });
    }

    function collect() {
        const rows = list.querySelectorAll('.border');
        const out = [];
        rows.forEach((row) => {
            const imageUrl = (row.querySelector('.sd-image')?.value || '').trim();
            const hasFile = !!(row.querySelector('.sd-file')?.value || '').trim();
            if (!imageUrl && !hasFile) return;
            out.push({
                title: (row.querySelector('.sd-title')?.value || '').trim(),
                image_url: imageUrl,
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

<!-- Settings Content -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Turmas -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Turmas</h3>
        </div>
        <div class="p-6">
            <?php if (empty($classes)): ?>
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <p class="text-gray-500">Nenhuma turma cadastrada</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($classes as $class): ?>
                        <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($class['nome']) ?></p>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($class['serie']) ?></p>
                            </div>
                            <span class="inline-block bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded">
                                <?= $class['ativo'] ? 'Ativa' : 'Inativa' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Matérias -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Matérias</h3>
        </div>
        <div class="p-6">
            <?php if (empty($subjects)): ?>
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <p class="text-gray-500">Nenhuma matéria cadastrada</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($subjects as $subject): ?>
                        <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($subject['nome']) ?></p>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($subject['descricao'] ?? 'Sem descrição') ?></p>
                            </div>
                            <span class="inline-block bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded">
                                <?= $subject['ativo'] ? 'Ativa' : 'Inativa' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Admin Users -->
<div class="mt-8 bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Usuários Administrativos</h3>
    </div>
    <div class="p-6">
        <?php if (empty($admin_users)): ?>
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                <p class="text-gray-500">Nenhum usuário administrativo cadastrado</p>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($admin_users as $admin): ?>
                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                                <span class="text-sm font-medium text-purple-600">
                                    <?= strtoupper(substr($admin['nome'], 0, 2)) ?>
                                </span>
                            </div>
                            <div class="ml-3">
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($admin['nome']) ?></p>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($admin['email']) ?></p>
                            </div>
                        </div>
                        <span class="inline-block bg-purple-100 text-purple-600 text-xs px-2 py-1 rounded">
                            <?= ucfirst($admin['perfil_admin']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
