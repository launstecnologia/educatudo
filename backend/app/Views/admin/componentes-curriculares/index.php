<?php
$page_header_title = 'Componentes Curriculares';
$page_header_subtitle = 'Cadastre e organize os componentes curriculares oferecidos pela escola.';
ob_start();
?>
<button type="button" onclick="openComponenteDrawer()"
        class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Novo Componente Curricular
</button>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ordem</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sigla</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Área do Conhecimento</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Etapas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Situação</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-book text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhum componente curricular cadastrado</p>
                        <button type="button" onclick="openComponenteDrawer()" class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                            <i class="fa-solid fa-plus mr-2"></i> Novo Componente Curricular
                        </button>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($items as $item): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= (int) ($item['ordem'] ?? 0) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block w-3 h-3 rounded-full border border-gray-200" style="background-color: <?= htmlspecialchars($item['cor'] ?? '#3B82F6') ?>"></span>
                            <?= htmlspecialchars($item['nome'] ?? '') ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['codigo'] ?? '') ?: '—' ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['sigla'] ?? '') ?: '—' ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= htmlspecialchars($areas_conhecimento[$item['area_conhecimento'] ?? ''] ?? ($item['area_conhecimento'] ?? '—')) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= htmlspecialchars($tipos[$item['tipo'] ?? ''] ?? ($item['tipo'] ?? '—')) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-xs">
                        <?php
                        $etapasResumo = [
                            'Infantil' => $item['etapa_infantil'] ?? 'nao_aplica',
                            'Fund. I' => $item['etapa_fund_i'] ?? 'nao_aplica',
                            'Fund. II' => $item['etapa_fund_ii'] ?? 'nao_aplica',
                            'Médio' => $item['etapa_medio'] ?? 'nao_aplica',
                        ];
                        ?>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($etapasResumo as $etapaLabel => $etapaValor): ?>
                                <?php if ($etapaValor === 'nao_aplica') continue; ?>
                                <span class="inline-flex px-1.5 py-0.5 rounded <?= $etapaValor === 'obrigatoria' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600' ?>">
                                    <?= htmlspecialchars($etapaLabel) ?>
                                </span>
                            <?php endforeach; ?>
                            <?php if (!array_filter($etapasResumo, fn($v) => $v !== 'nao_aplica')): ?>
                                <span class="text-gray-400">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= !empty($item['ativo']) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= !empty($item['ativo']) ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <?php ob_start(); ?>
                        <button type="button" onclick="openComponenteDrawer(<?= (int) $item['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        <button type="button" onclick="deleteComponente(<?= (int) $item['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                        </button>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-cc-' . (int) $item['id'];
                        include __DIR__ . '/../_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Criar/Editar componente curricular em drawer lateral -->
<div id="componenteDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeComponenteDrawer()"></div>
<aside id="componenteDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="componenteDrawerTitle" class="text-xl font-bold text-gray-900">Novo Componente Curricular</h2>
        <button type="button" onclick="closeComponenteDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="componente-form" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <input type="hidden" id="cc_id" value="">
        <input type="hidden" name="_method" id="cc_method" value="" disabled>

        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Identificação</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="cc_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
                        <input type="text" id="cc_nome" name="nome" required placeholder="Ex: Matemática"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="cc_codigo" class="block text-sm font-medium text-gray-700 mb-1">Código *</label>
                        <input type="text" id="cc_codigo" name="codigo" required placeholder="Ex: MAT"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 uppercase">
                    </div>
                    <div>
                        <label for="cc_sigla" class="block text-sm font-medium text-gray-700 mb-1">Sigla</label>
                        <input type="text" id="cc_sigla" name="sigla" placeholder="Ex: MAT"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 uppercase">
                    </div>
                    <div>
                        <label for="cc_area_conhecimento" class="block text-sm font-medium text-gray-700 mb-1">Área do Conhecimento *</label>
                        <div class="relative">
                            <select id="cc_area_conhecimento" name="area_conhecimento" required
                                    class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <?php foreach ($areas_conhecimento as $valor => $rotulo): ?>
                                <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($rotulo) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <label for="cc_tipo" class="block text-sm font-medium text-gray-700 mb-1">Tipo/Categoria *</label>
                        <div class="relative">
                            <select id="cc_tipo" name="tipo" required
                                    class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <?php foreach ($tipos as $valor => $rotulo): ?>
                                <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($rotulo) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <label for="cc_cor" class="block text-sm font-medium text-gray-700 mb-1">Cor</label>
                        <input type="color" id="cc_cor" name="cor" value="#3B82F6"
                               class="h-10 w-full px-1 py-1 border border-gray-300 rounded-lg cursor-pointer">
                    </div>
                    <div>
                        <label for="cc_ordem" class="block text-sm font-medium text-gray-700 mb-1">Ordem de exibição</label>
                        <input type="number" id="cc_ordem" name="ordem" value="0" min="0" step="1"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="cc_descricao" class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                        <textarea id="cc_descricao" name="descricao" rows="3" placeholder="Ex: Componente curricular de Matemática"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                    </div>
                </div>
            </section>

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Etapas de Ensino</h3>
                <p class="text-xs text-gray-500 mb-4 -mt-2">Para cada etapa, defina se o componente é obrigatório, ofertado como eletiva/optativa, ou não se aplica.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <?php
                    $etapasCampos = [
                        'etapa_infantil' => 'Educação Infantil',
                        'etapa_fund_i' => 'Ensino Fundamental I',
                        'etapa_fund_ii' => 'Ensino Fundamental II',
                        'etapa_medio' => 'Ensino Médio',
                    ];
                    ?>
                    <?php foreach ($etapasCampos as $campo => $rotulo): ?>
                    <div>
                        <label for="cc_<?= $campo ?>" class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($rotulo) ?></label>
                        <div class="relative">
                            <select id="cc_<?= $campo ?>" name="<?= $campo ?>"
                                    class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <?php foreach ($etapas as $valor => $rotuloEtapa): ?>
                                <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($rotuloEtapa) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Permissões e Situação</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <label class="flex items-center gap-3">
                        <input type="checkbox" id="cc_permite_avaliacao" name="permite_avaliacao" value="1" checked
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        <span class="text-sm font-medium text-gray-700">Permite avaliação</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" id="cc_permite_frequencia" name="permite_frequencia" value="1" checked
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        <span class="text-sm font-medium text-gray-700">Permite frequência</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" id="cc_permite_plano_aula" name="permite_plano_aula" value="1" checked
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        <span class="text-sm font-medium text-gray-700">Permite plano de aula</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" id="cc_permite_diario" name="permite_diario" value="1" checked
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        <span class="text-sm font-medium text-gray-700">Permite diário</span>
                    </label>
                    <label class="flex items-center gap-3 sm:col-span-2 pt-2 border-t border-gray-100">
                        <input type="checkbox" id="cc_ativo" name="ativo" value="1" checked
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        <span>
                            <span class="block text-sm font-medium text-gray-700">Ativo</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Componentes inativos ficam ocultos nas telas de cadastro que os utilizam.</span>
                        </span>
                    </label>
                </div>
            </section>
        </div>

        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeComponenteDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="componente-form-submit-label">Cadastrar Componente</span>
            </button>
        </div>
    </form>
</aside>

<script>
const URL_BASE = <?= json_encode(URL) ?>;
const componenteCheckboxFields = ['permite_avaliacao', 'permite_frequencia', 'permite_plano_aula', 'permite_diario', 'ativo'];
const componenteTextFields = ['nome', 'codigo', 'sigla', 'area_conhecimento', 'tipo', 'etapa_infantil', 'etapa_fund_i', 'etapa_fund_ii', 'etapa_medio', 'cor', 'ordem', 'descricao'];

function showComponenteDrawer() {
    document.getElementById('componenteDrawerBackdrop').classList.remove('hidden');
    const drawer = document.getElementById('componenteDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeComponenteDrawer() {
    document.getElementById('componenteDrawerBackdrop').classList.add('hidden');
    const drawer = document.getElementById('componenteDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function openComponenteDrawer(id) {
    const form = document.getElementById('componente-form');
    form.reset();
    document.getElementById('cc_id').value = '';
    document.getElementById('cc_method').value = '';
    document.getElementById('cc_method').disabled = true;
    document.getElementById('cc_cor').value = '#3B82F6';

    if (!id) {
        form.dataset.mode = 'create';
        document.getElementById('componenteDrawerTitle').textContent = 'Novo Componente Curricular';
        document.getElementById('componente-form-submit-label').textContent = 'Cadastrar Componente';
        componenteCheckboxFields.forEach((field) => {
            document.getElementById('cc_' + field).checked = true;
        });
        showComponenteDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    document.getElementById('componenteDrawerTitle').textContent = 'Editar Componente Curricular';
    document.getElementById('componente-form-submit-label').textContent = 'Salvar Alterações';
    document.getElementById('cc_method').value = 'PUT';
    document.getElementById('cc_method').disabled = false;

    showComponenteDrawer();

    fetch(URL_BASE + '/admin/componentes-curriculares/' + id + '/dados')
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                alert('Erro: ' + (data.error || 'Não foi possível carregar o componente curricular'));
                closeComponenteDrawer();
                return;
            }
            const item = data.item;
            document.getElementById('cc_id').value = item.id;
            componenteTextFields.forEach((field) => {
                const el = document.getElementById('cc_' + field);
                if (el && item[field] !== null && item[field] !== undefined && item[field] !== '') {
                    el.value = item[field];
                }
            });
            componenteCheckboxFields.forEach((field) => {
                document.getElementById('cc_' + field).checked = !!parseInt(item[field], 10);
            });
        })
        .catch(() => {
            alert('Erro de conexão ao carregar componente curricular.');
            closeComponenteDrawer();
        });
}

document.getElementById('componente-form').addEventListener('submit', function (e) {
    e.preventDefault();

    const mode = this.dataset.mode;
    const id = document.getElementById('cc_id').value;
    const submitBtn = this.querySelector('button[type="submit"]');
    const submitLabel = document.getElementById('componente-form-submit-label');
    const originalText = submitLabel.textContent;

    submitBtn.disabled = true;
    submitLabel.textContent = 'Salvando...';

    const url = mode === 'create' ? (URL_BASE + '/admin/componentes-curriculares') : (URL_BASE + '/admin/componentes-curriculares/' + id);

    fetch(url, { method: 'POST', body: new FormData(this), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Erro: ' + (data.error || 'Erro ao salvar'));
            }
        })
        .catch(() => alert('Erro de conexão. Tente novamente.'))
        .finally(() => {
            submitBtn.disabled = false;
            submitLabel.textContent = originalText;
        });
});

function deleteComponente(id) {
    if (!confirm('Tem certeza que deseja excluir este componente curricular? Esta ação não pode ser desfeita.')) return;
    const formData = new FormData();
    formData.append('_method', 'DELETE');
    formData.append('_token', <?= json_encode($csrf_token ?? '') ?>);
    fetch(URL_BASE + '/admin/componentes-curriculares/' + id, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((r) => r.json())
        .then((data) => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao excluir: ' + (data.error || 'tente novamente'));
            }
        })
        .catch(() => alert('Erro de conexão'));
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeComponenteDrawer();
    }
});
</script>
