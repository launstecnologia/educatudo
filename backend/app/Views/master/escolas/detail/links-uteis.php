<?php
$layout_config = $layout_config ?? [];
$escola_id = $escola_id ?? 0;
$menuLinks = json_decode((string)($layout_config['menu_links_submenu'] ?? '[]'), true);
if (!is_array($menuLinks)) {
    $menuLinks = [];
}
$csrf_token = $csrf_token ?? '';
?>

<div class="bg-white rounded-xl shadow border border-slate-200 p-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-2">Links no Submenu (Aluno/Professor)</h3>
    <p class="text-sm text-slate-600 mb-4">Configure links úteis e escolha visibilidade (aluno, professor ou ambos).</p>

    <form method="post" action="<?= URL ?>/master/escolas/<?= (int) $escola_id ?>/links-uteis">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="layout_menu_links_submenu" id="links-uteis-json" value="<?= htmlspecialchars(json_encode($menuLinks, JSON_UNESCAPED_UNICODE)) ?>">
        <div id="links-uteis-list" class="space-y-3 mb-4"></div>

        <div class="flex items-center justify-between gap-3">
            <button type="button" id="links-uteis-add" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-200 text-sm">
                + Adicionar link
            </button>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                Salvar Links
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    var inputJson = document.getElementById('links-uteis-json');
    var list = document.getElementById('links-uteis-list');
    var addBtn = document.getElementById('links-uteis-add');

    function getLinks() {
        try { return JSON.parse(inputJson.value || '[]'); } catch (e) { return []; }
    }
    function setLinks(arr) {
        inputJson.value = JSON.stringify(arr);
    }
    function toVisibilidade(link) {
        if (typeof link.visibilidade === 'string' && link.visibilidade !== '') return link.visibilidade;
        var aluno = !!link.aluno;
        var professor = !!link.professor;
        if (aluno && professor) return 'ambos';
        if (aluno) return 'aluno';
        if (professor) return 'professor';
        return 'aluno';
    }

    function renderLinks() {
        var links = getLinks();
        list.innerHTML = '';

        if (!links.length) {
            var empty = document.createElement('div');
            empty.className = 'text-sm text-slate-500 py-2';
            empty.textContent = 'Nenhum link cadastrado.';
            list.appendChild(empty);
            return;
        }

        links.forEach(function(link, i) {
            var vis = toVisibilidade(link);
            var row = document.createElement('div');
            row.className = 'grid grid-cols-1 md:grid-cols-12 gap-2 items-center border border-slate-200 rounded-lg p-3';
            row.innerHTML =
                '<input type="text" class="lu-nome md:col-span-3 px-2 py-1.5 border rounded-lg text-sm" placeholder="Nome" value="' + (link.nome || '').replace(/"/g, '&quot;') + '">' +
                '<input type="text" class="lu-url md:col-span-5 px-2 py-1.5 border rounded-lg text-sm" placeholder="URL" value="' + (link.url || '').replace(/"/g, '&quot;') + '">' +
                '<select class="lu-vis md:col-span-2 px-2 py-1.5 border rounded-lg text-sm">' +
                    '<option value="aluno"' + (vis === 'aluno' ? ' selected' : '') + '>Aluno</option>' +
                    '<option value="professor"' + (vis === 'professor' ? ' selected' : '') + '>Professor</option>' +
                    '<option value="ambos"' + (vis === 'ambos' ? ' selected' : '') + '>Ambos</option>' +
                '</select>' +
                '<label class="md:col-span-1 text-sm"><input type="checkbox" class="lu-nova-guia rounded" ' + (link.nova_guia ? 'checked' : '') + '> Nova guia</label>' +
                '<button type="button" class="lu-remove text-red-600 hover:text-red-800 text-sm md:col-span-1 font-medium">Remover</button>';

            row.querySelector('.lu-remove').addEventListener('click', function() {
                links.splice(i, 1);
                setLinks(links);
                renderLinks();
            });
            list.appendChild(row);
        });
    }

    function collectLinks() {
        var rows = list.querySelectorAll('.grid');
        var out = [];
        rows.forEach(function(row) {
            var vis = ((row.querySelector('.lu-vis') || {}).value || 'aluno');
            out.push({
                nome: (row.querySelector('.lu-nome') || {}).value || '',
                url: (row.querySelector('.lu-url') || {}).value || '',
                visibilidade: vis,
                aluno: vis === 'aluno' || vis === 'ambos',
                professor: vis === 'professor' || vis === 'ambos',
                nova_guia: !!(row.querySelector('.lu-nova-guia') && row.querySelector('.lu-nova-guia').checked)
            });
        });
        setLinks(out);
    }

    addBtn.addEventListener('click', function() {
        var links = getLinks();
        links.push({ nome: '', url: '', visibilidade: 'aluno', aluno: true, professor: false, nova_guia: true });
        setLinks(links);
        renderLinks();
    });

    document.querySelector('form').addEventListener('submit', function() {
        collectLinks();
    });

    renderLinks();
})();
</script>

