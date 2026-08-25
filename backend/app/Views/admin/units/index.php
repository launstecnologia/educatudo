<?php
$page_header_title = 'Instituição';
$page_header_subtitle = 'Matriz e filiais da escola. Estes dados aparecem no cabeçalho dos documentos do aluno.';
ob_start();
?>
<a href="<?= URL ?>/admin/students/export-censo"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-file-csv mr-2 text-gray-500"></i>
    Exportar Censo (CSV)
</a>
<button type="button" onclick="openUnitDrawer()"
        class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Nova Unidade
</button>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';

$flash_status = ($flash_message ?? '') !== '' ? (($flash_type ?? '') === 'error' ? 'error' : 'success') : '';
include __DIR__ . '/../_partials/flash_message.php';
?>

<?php if (empty($schema_ready)): ?>
<div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
    A tabela de unidades ainda não foi criada neste banco. Execute a migration
    <code>2026_06_25_unidades_escola.sql</code> pelo painel Master antes de cadastrar unidades.
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CNPJ</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cidade/UF</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alunos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($units)): ?>
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-building text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhuma unidade cadastrada</p>
                        <button type="button" onclick="openUnitDrawer()" class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                            <i class="fa-solid fa-plus mr-2"></i> Nova Unidade
                        </button>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($units as $unit): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?= htmlspecialchars($unit['nome'] ?? '') ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= ($unit['tipo'] ?? '') === 'matriz' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700' ?>">
                            <?= ($unit['tipo'] ?? '') === 'matriz' ? 'Matriz' : 'Filial' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($unit['cnpj'] ?? '') ?: '—' ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= htmlspecialchars(trim(($unit['cidade'] ?? '') . (!empty($unit['uf']) ? ' / ' . $unit['uf'] : ''))) ?: '—' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= (int) ($unit['total_alunos'] ?? 0) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= !empty($unit['ativo']) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= !empty($unit['ativo']) ? 'Ativa' : 'Inativa' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <?php ob_start(); ?>
                        <button type="button" onclick="openUnitDrawer(<?= (int) $unit['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        <button type="button" onclick="deleteUnit(<?= (int) $unit['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                        </button>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-unit-' . (int) $unit['id'];
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

<!-- Criar/Editar unidade em drawer lateral -->
<div id="unitDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeUnitDrawer()"></div>
<aside id="unitDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="unitDrawerTitle" class="text-xl font-bold text-gray-900">Nova Unidade</h2>
        <button type="button" onclick="closeUnitDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="unit-form" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <input type="hidden" id="unit_id" value="">
        <input type="hidden" name="_method" id="unit_method" value="" disabled>

        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Identificação</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label for="unit_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome da unidade *</label>
                        <input type="text" id="unit_nome" name="nome" required placeholder="Ex: Unidade Centro"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_tipo" class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
                        <div class="relative">
                            <select id="unit_tipo" name="tipo"
                                    class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <option value="matriz">Matriz</option>
                                <option value="filial">Filial</option>
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="unit_razao_social" class="block text-sm font-medium text-gray-700 mb-1">Razão social</label>
                        <input type="text" id="unit_razao_social" name="razao_social" placeholder="Razão social que aparece nos documentos"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_cnpj" class="block text-sm font-medium text-gray-700 mb-1">CNPJ</label>
                        <input type="text" id="unit_cnpj" name="cnpj" placeholder="00.000.000/0000-00"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_inep" class="block text-sm font-medium text-gray-700 mb-1">Código INEP</label>
                        <input type="text" id="unit_inep" name="inep" placeholder="Código do censo escolar"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_dependencia_administrativa" class="block text-sm font-medium text-gray-700 mb-1">Dependência administrativa</label>
                        <div class="relative">
                            <select id="unit_dependencia_administrativa" name="dependencia_administrativa"
                                    class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <option value="">Não informada</option>
                                <option value="federal">Federal</option>
                                <option value="estadual">Estadual</option>
                                <option value="municipal">Municipal</option>
                                <option value="privada">Privada</option>
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </section>

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Endereço</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="unit_endereco" class="block text-sm font-medium text-gray-700 mb-1">Logradouro</label>
                        <input type="text" id="unit_endereco" name="endereco"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_numero" class="block text-sm font-medium text-gray-700 mb-1">Número</label>
                        <input type="text" id="unit_numero" name="numero"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_cep" class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                        <input type="text" id="unit_cep" name="cep"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_complemento" class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                        <input type="text" id="unit_complemento" name="complemento"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_bairro" class="block text-sm font-medium text-gray-700 mb-1">Bairro</label>
                        <input type="text" id="unit_bairro" name="bairro"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_cidade" class="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                        <input type="text" id="unit_cidade" name="cidade"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_uf" class="block text-sm font-medium text-gray-700 mb-1">UF</label>
                        <input type="text" id="unit_uf" name="uf" maxlength="2"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 uppercase">
                    </div>
                </div>
            </section>

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Contato e Responsáveis</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label for="unit_telefone" class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                        <input type="text" id="unit_telefone" name="telefone"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_email" class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                        <input type="email" id="unit_email" name="email"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_diretor_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome do(a) diretor(a)</label>
                        <input type="text" id="unit_diretor_nome" name="diretor_nome"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_secretario_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome do(a) secretário(a)</label>
                        <input type="text" id="unit_secretario_nome" name="secretario_nome"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="unit_ato_autorizacao" class="block text-sm font-medium text-gray-700 mb-1">Ato de autorização (nº / DO)</label>
                        <input type="text" id="unit_ato_autorizacao" name="ato_autorizacao" placeholder="Ex.: Portaria nº 123/2018, DOU 15/03/2018"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_ato_credenciamento" class="block text-sm font-medium text-gray-700 mb-1">Ato de credenciamento</label>
                        <input type="text" id="unit_ato_credenciamento" name="ato_credenciamento"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_ato_reconhecimento" class="block text-sm font-medium text-gray-700 mb-1">Ato de reconhecimento</label>
                        <input type="text" id="unit_ato_reconhecimento" name="ato_reconhecimento"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_diretor_registro" class="block text-sm font-medium text-gray-700 mb-1">Registro do(a) diretor(a)</label>
                        <input type="text" id="unit_diretor_registro" name="diretor_registro" placeholder="Nº de autorização / registro"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="unit_secretario_registro" class="block text-sm font-medium text-gray-700 mb-1">Registro do(a) secretário(a)</label>
                        <input type="text" id="unit_secretario_registro" name="secretario_registro"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="unit_logo" class="block text-sm font-medium text-gray-700 mb-1">Logo da unidade (opcional)</label>
                        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                            <div id="unit_logo_preview_box" class="w-20 h-20 flex-shrink-0 bg-gray-100 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden">
                                <img id="unit_logo_preview_img" src="" alt="Logo da unidade" class="max-w-full max-h-full object-contain hidden">
                                <i id="unit_logo_preview_placeholder" class="fa-solid fa-image text-gray-400 text-xl"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <input type="file" id="unit_logo" name="logo" accept="image/png,image/jpeg,image/webp,image/gif"
                                       class="block w-full text-sm text-gray-700 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                                <p class="text-xs text-gray-500 mt-1.5">PNG, JPG, WEBP ou GIF. Máx. 5MB. Sem arquivo, o logo atual é mantido. Em branco, usa o logo padrão da escola.</p>
                                <label id="unit_logo_remover_wrap" class="hidden mt-2 items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" id="unit_remover_logo" name="remover_logo" value="1"
                                           class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                                    Remover logo (usar o padrão da escola)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" id="unit_ativo" name="ativo" value="1" checked
                                   class="mt-0.5 rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                            <span>
                                <span class="block text-sm font-medium text-gray-700">Unidade ativa</span>
                                <span class="block text-xs text-gray-500 mt-0.5">Unidades inativas não ficam disponíveis para vínculo de alunos.</span>
                            </span>
                        </label>
                    </div>
                </div>
            </section>
        </div>

        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeUnitDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="unit-form-submit-label">Cadastrar Unidade</span>
            </button>
        </div>
    </form>
</aside>

<script>
const URL_BASE = <?= json_encode(URL) ?>;
const unitFields = ['nome', 'tipo', 'razao_social', 'cnpj', 'inep', 'dependencia_administrativa', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep', 'telefone', 'email', 'diretor_nome', 'secretario_nome', 'ato_autorizacao', 'ato_credenciamento', 'ato_reconhecimento', 'diretor_registro', 'secretario_registro'];

function resetLogoPreview() {
    const img = document.getElementById('unit_logo_preview_img');
    const placeholder = document.getElementById('unit_logo_preview_placeholder');
    const removeWrap = document.getElementById('unit_logo_remover_wrap');
    const fileInput = document.getElementById('unit_logo');
    const remover = document.getElementById('unit_remover_logo');
    if (fileInput) fileInput.value = '';
    if (remover) remover.checked = false;
    if (img) {
        img.src = '';
        img.classList.add('hidden');
    }
    if (placeholder) placeholder.classList.remove('hidden');
    if (removeWrap) {
        removeWrap.classList.add('hidden');
        removeWrap.classList.remove('flex');
    }
}

function showLogoPreview(url) {
    const img = document.getElementById('unit_logo_preview_img');
    const placeholder = document.getElementById('unit_logo_preview_placeholder');
    const removeWrap = document.getElementById('unit_logo_remover_wrap');
    if (!url) {
        resetLogoPreview();
        return;
    }
    if (img) {
        img.src = url;
        img.classList.remove('hidden');
    }
    if (placeholder) placeholder.classList.add('hidden');
    if (removeWrap) {
        removeWrap.classList.remove('hidden');
        removeWrap.classList.add('flex');
    }
}

document.getElementById('unit_logo').addEventListener('change', function () {
    const file = this.files && this.files[0];
    const remover = document.getElementById('unit_remover_logo');
    if (remover) remover.checked = false;
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (ev) {
        showLogoPreview(ev.target.result);
    };
    reader.readAsDataURL(file);
});

document.getElementById('unit_remover_logo').addEventListener('change', function () {
    if (!this.checked) return;
    const fileInput = document.getElementById('unit_logo');
    if (fileInput) fileInput.value = '';
    const img = document.getElementById('unit_logo_preview_img');
    const placeholder = document.getElementById('unit_logo_preview_placeholder');
    if (img) {
        img.src = '';
        img.classList.add('hidden');
    }
    if (placeholder) placeholder.classList.remove('hidden');
});

function showUnitDrawer() {
    document.getElementById('unitDrawerBackdrop').classList.remove('hidden');
    const drawer = document.getElementById('unitDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeUnitDrawer() {
    document.getElementById('unitDrawerBackdrop').classList.add('hidden');
    const drawer = document.getElementById('unitDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function openUnitDrawer(id) {
    const form = document.getElementById('unit-form');
    form.reset();
    document.getElementById('unit_id').value = '';
    document.getElementById('unit_method').value = '';
    document.getElementById('unit_method').disabled = true;
    resetLogoPreview();

    if (!id) {
        form.dataset.mode = 'create';
        document.getElementById('unitDrawerTitle').textContent = 'Nova Unidade';
        document.getElementById('unit-form-submit-label').textContent = 'Cadastrar Unidade';
        document.getElementById('unit_ativo').checked = true;
        showUnitDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    document.getElementById('unitDrawerTitle').textContent = 'Editar Unidade';
    document.getElementById('unit-form-submit-label').textContent = 'Salvar Alterações';
    document.getElementById('unit_method').value = 'PUT';
    document.getElementById('unit_method').disabled = false;

    showUnitDrawer();

    fetch(URL_BASE + '/admin/unidades/' + id + '/dados')
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                alert('Erro: ' + (data.error || 'Não foi possível carregar a unidade'));
                closeUnitDrawer();
                return;
            }
            const unit = data.unit;
            document.getElementById('unit_id').value = unit.id;
            unitFields.forEach((field) => {
                const el = document.getElementById('unit_' + field);
                if (el) el.value = unit[field] || '';
            });
            document.getElementById('unit_ativo').checked = !!parseInt(unit.ativo, 10);
            showLogoPreview(unit.logo_url || '');
        })
        .catch(() => {
            alert('Erro de conexão ao carregar unidade.');
            closeUnitDrawer();
        });
}

document.getElementById('unit-form').addEventListener('submit', function (e) {
    e.preventDefault();

    const mode = this.dataset.mode;
    const id = document.getElementById('unit_id').value;
    const submitBtn = this.querySelector('button[type="submit"]');
    const submitLabel = document.getElementById('unit-form-submit-label');
    const originalText = submitLabel.textContent;

    submitBtn.disabled = true;
    submitLabel.textContent = 'Salvando...';

    const url = mode === 'create' ? (URL_BASE + '/admin/unidades') : (URL_BASE + '/admin/unidades/' + id);

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

function deleteUnit(id) {
    if (!confirm('Tem certeza que deseja excluir esta unidade? Esta ação não pode ser desfeita.')) return;
    const formData = new FormData();
    formData.append('_method', 'DELETE');
    formData.append('_token', <?= json_encode($csrf_token ?? '') ?>);
    fetch(URL_BASE + '/admin/unidades/' + id, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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
        closeUnitDrawer();
    }
});
</script>
