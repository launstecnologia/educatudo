<?php
$layout_config = $layout_config ?? [];
$escola_id = $escola_id ?? 0;
$log_validacao = $log_validacao ?? [];
$log_validacao_master = $log_validacao_master ?? [];
$externalAppsLinksRaw = $layout_config['external_apps_links'] ?? '[]';
$externalAppsLinks = json_decode((string) $externalAppsLinksRaw, true);
if (!is_array($externalAppsLinks)) {
    $externalAppsLinks = [];
}
if (empty($externalAppsLinks)) {
    $legacyDefaults = [
        ['nome' => 'EducaLabs', 'url' => (string) ($layout_config['educalabs_external_url'] ?? 'https://educalabs.educatudo.com/login'), 'menu_grupo' => 'estudo', 'menu_icone' => 'sparkles', 'substituir_nativo' => 'educalabs', 'aluno' => true, 'professor' => false, 'nova_guia' => true],
        ['nome' => 'Games', 'url' => (string) ($layout_config['games_external_url'] ?? 'https://games.educatudo.com'), 'menu_grupo' => 'entretenimento', 'menu_icone' => 'game', 'substituir_nativo' => 'jogos', 'aluno' => true, 'professor' => false, 'nova_guia' => true],
        ['nome' => 'Notes', 'url' => (string) ($layout_config['notes_external_url'] ?? 'https://notes.educatudo.com'), 'menu_grupo' => 'estudo', 'menu_icone' => 'document', 'substituir_nativo' => 'notes', 'aluno' => true, 'professor' => false, 'nova_guia' => true],
    ];
    foreach ($legacyDefaults as $item) {
        if (trim((string) ($item['url'] ?? '')) !== '') {
            $externalAppsLinks[] = $item;
        }
    }
}
$csrf_token = $csrf_token ?? '';
?>

<form method="post" action="<?= URL ?>/master/escolas/<?= (int) $escola_id ?>/apps-externos" class="space-y-6">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <div class="bg-white rounded-xl shadow border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-2">Apps Externos</h3>
        <p class="text-sm text-slate-600 mb-4">Cadastre links para o aluno e/ou professor. <strong>Onde no menu</strong> define a seção do sidebar. <strong>Ícone</strong> personaliza o desenho ao lado do nome. <strong>Ocultar item nativo</strong> remove o atalho fixo equivalente (ex.: Games do Entretenimento) para evitar duplicata — use quando o app externo substitui o módulo. <strong>Enviar JSON de contexto do aluno</strong> habilita envio de provas, jornadas (com professor/matéria) e planos de aula no <code>/external-apps/validate-token</code> para esse app. Links em <em>Links úteis</em> com a mesma URL de um app externo deixam de aparecer em Colag (evita duas entradas). <strong>Nova guia</strong> também vale para &quot;EducaHits → Escutar música&quot; quando o app se chama EducaHits.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Slug da escola</label>
                <input type="text" name="external_institution_id" value="<?= htmlspecialchars($layout_config['external_institution_id'] ?? '') ?>"
                       placeholder="Ex: educa, coleg" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Endpoint único de validação</label>
                <input type="text" name="external_apps_validate_url" value="<?= htmlspecialchars($layout_config['external_apps_validate_url'] ?? (rtrim(URL, '/') . '/external-apps/validate-token')) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        <input type="hidden" name="layout_external_apps_links" id="ea-links-json" value="<?= htmlspecialchars(json_encode($externalAppsLinks, JSON_UNESCAPED_UNICODE)) ?>">
        <div id="ea-links-list" class="space-y-3 mb-3"></div>
        <button type="button" id="ea-add-link" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-200 text-sm">+ Adicionar app externo</button>

        <div class="mt-6 pt-4 border-t border-slate-100">
            <p class="text-xs text-slate-500 mb-3">Campos de compatibilidade com integrações antigas (opcional):</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">URL EducaLabs</label>
                    <input type="text" name="educalabs_external_url" value="<?= htmlspecialchars($layout_config['educalabs_external_url'] ?? 'https://educalabs.educatudo.com/login') ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs text-slate-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">URL Games</label>
                    <input type="text" name="games_external_url" value="<?= htmlspecialchars($layout_config['games_external_url'] ?? 'https://games.educatudo.com') ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs text-slate-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">URL Notes</label>
                    <input type="text" name="notes_external_url" value="<?= htmlspecialchars($layout_config['notes_external_url'] ?? 'https://notes.educatudo.com') ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs text-slate-500">
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">Salvar apps externos</button>
</form>

<!-- Log de falhas de validação (jogo/app não abriu) -->
<div class="mt-8 bg-white rounded-xl shadow border border-slate-200 p-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-1">Log de falhas de validação</h3>
    <p class="text-sm text-slate-600 mb-2">Quando o jogo (Tudinha do Milhão), EducaLabs ou outro app externo não abre e exibe &quot;Acesso ao Jogo&quot; ou token inválido, a falha é registrada aqui para diagnóstico.</p>
    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-4">
        <strong>Migrations:</strong> Execute <code class="bg-amber-100 px-1 rounded">032_log_validacao_apps_externos.sql</code> no banco da <strong>escola (tenant)</strong>. Se o endpoint de validação for <strong>master.educatudo.com</strong>, execute também <code class="bg-amber-100 px-1 rounded">033_log_validacao_apps_externos_master.sql</code> no banco <strong>master</strong> — assim as falhas em que o jogo não envia o parâmetro &quot;slug&quot; também passam a ser registradas.
    </p>

    <h4 class="text-sm font-semibold text-slate-700 mt-4 mb-2">Falhas desta escola (banco da escola)</h4>
    <?php if (empty($log_validacao)): ?>
    <p class="text-sm text-slate-500 py-2">Nenhuma falha registrada para esta escola.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm border border-slate-200 rounded-lg">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left px-3 py-2 font-medium text-slate-700">Data/Hora</th>
                    <th class="text-left px-3 py-2 font-medium text-slate-700">App</th>
                    <th class="text-left px-3 py-2 font-medium text-slate-700">Evento</th>
                    <th class="text-left px-3 py-2 font-medium text-slate-700">Detalhes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($log_validacao as $entry): ?>
                <tr class="border-t border-slate-100 hover:bg-slate-50/50">
                    <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($entry['created_at'] ?? '') ?></td>
                    <td class="px-3 py-2"><span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800"><?= htmlspecialchars($entry['app'] ?? '') ?></span></td>
                    <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($entry['evento'] ?? '') ?></td>
                    <td class="px-3 py-2 text-slate-600 max-w-md">
                        <?php
                        $d = $entry['detalhes'] ?? '';
                        if (is_string($d)) {
                            $arr = json_decode($d, true);
                            $d = is_array($arr) ? $arr : $d;
                        }
                        if (is_array($d)) {
                            echo '<pre class="text-xs whitespace-pre-wrap break-all bg-slate-50 p-2 rounded">' . htmlspecialchars(json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                        } else {
                            echo htmlspecialchars((string) $d);
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="text-xs text-slate-500 mt-3">Últimas 100 ocorrências. Use os detalhes para ver slug, URI, diagnóstico (token expirado, não encontrado, etc.).</p>
    <?php endif; ?>

    <h4 class="text-sm font-semibold text-slate-700 mt-6 mb-2">Falhas sem escola identificada (banco master)</h4>
    <p class="text-xs text-slate-500 mb-2">Quando o endpoint é master.educatudo.com e o jogo não envia o parâmetro &quot;slug&quot; na URL de validação, a falha cai aqui. Execute a migration 033 no banco master para criar a tabela.</p>
    <?php if (empty($log_validacao_master)): ?>
    <p class="text-sm text-slate-500 py-2">Nenhuma falha no master. Se o jogo não abre e nada aparece acima, execute <code class="bg-slate-100 px-1 rounded text-xs">033_log_validacao_apps_externos_master.sql</code> no banco master e tente de novo.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm border border-slate-200 rounded-lg">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left px-3 py-2 font-medium text-slate-700">Data/Hora</th>
                    <th class="text-left px-3 py-2 font-medium text-slate-700">App</th>
                    <th class="text-left px-3 py-2 font-medium text-slate-700">Evento</th>
                    <th class="text-left px-3 py-2 font-medium text-slate-700">Detalhes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($log_validacao_master as $entry): ?>
                <tr class="border-t border-slate-100 hover:bg-slate-50/50">
                    <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($entry['created_at'] ?? '') ?></td>
                    <td class="px-3 py-2"><span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800"><?= htmlspecialchars($entry['app'] ?? '') ?></span></td>
                    <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($entry['evento'] ?? '') ?></td>
                    <td class="px-3 py-2 text-slate-600 max-w-md">
                        <?php
                        $d = $entry['detalhes'] ?? '';
                        if (is_string($d)) {
                            $arr = json_decode($d, true);
                            $d = is_array($arr) ? $arr : $d;
                        }
                        if (is_array($d)) {
                            echo '<pre class="text-xs whitespace-pre-wrap break-all bg-slate-50 p-2 rounded">' . htmlspecialchars(json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                        } else {
                            echo htmlspecialchars((string) $d);
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    var linksJson = document.getElementById('ea-links-json');
    var linksList = document.getElementById('ea-links-list');
    var addBtn = document.getElementById('ea-add-link');

    function getLinks() {
        try { return JSON.parse(linksJson.value || '[]'); } catch (e) { return []; }
    }
    function setLinks(arr) {
        linksJson.value = JSON.stringify(arr);
    }
    function renderLinks() {
        var links = getLinks();
        linksList.innerHTML = '';
        links.forEach(function(link, i) {
            var wrap = document.createElement('div');
            wrap.className = 'border border-slate-200 rounded-lg p-3 space-y-2';
            var mg = link.menu_grupo || 'colag';
            if (['estudo', 'colag', 'entretenimento', 'sistema'].indexOf(mg) === -1) { mg = 'colag'; }
            var ic = (link.menu_icone || 'link').replace(/[^a-z0-9_-]/g, '') || 'link';
            var sn = link.substituir_nativo || '';
            if (['', 'jogos', 'educalabs', 'educa_hits', 'notes'].indexOf(sn) === -1) { sn = ''; }
            wrap.innerHTML =
                '<input type="hidden" class="ea-id" value="' + String(link.id || '').replace(/"/g, '&quot;') + '">' +
                '<div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end">' +
                '<input type="text" class="ea-nome md:col-span-2 px-2 py-1.5 border rounded-lg text-sm" placeholder="Nome no menu" value="' + (link.nome || '').replace(/"/g, '&quot;') + '">' +
                '<input type="text" class="ea-url md:col-span-3 px-2 py-1.5 border rounded-lg text-sm" placeholder="https://..." value="' + (link.url || '').replace(/"/g, '&quot;') + '">' +
                '<select class="ea-menu-grupo md:col-span-2 px-2 py-1.5 border rounded-lg text-sm">' +
                '<option value="colag"' + (mg === 'colag' ? ' selected' : '') + '>Menu: Colag</option>' +
                '<option value="estudo"' + (mg === 'estudo' ? ' selected' : '') + '>Menu: Estudo</option>' +
                '<option value="entretenimento"' + (mg === 'entretenimento' ? ' selected' : '') + '>Menu: Entretenim.</option>' +
                '<option value="sistema"' + (mg === 'sistema' ? ' selected' : '') + '>Menu: Sistema</option>' +
                '</select>' +
                '<select class="ea-menu-icone md:col-span-2 px-2 py-1.5 border rounded-lg text-sm">' +
                '<option value="link"' + (ic === 'link' ? ' selected' : '') + '>Ícone: link externo</option>' +
                '<option value="book"' + (ic === 'book' ? ' selected' : '') + '>Ícone: livro</option>' +
                '<option value="game"' + (ic === 'game' ? ' selected' : '') + '>Ícone: jogo</option>' +
                '<option value="music"' + (ic === 'music' ? ' selected' : '') + '>Ícone: música</option>' +
                '<option value="document"' + (ic === 'document' ? ' selected' : '') + '>Ícone: documento</option>' +
                '<option value="globe"' + (ic === 'globe' ? ' selected' : '') + '>Ícone: globo</option>' +
                '<option value="chat"' + (ic === 'chat' ? ' selected' : '') + '>Ícone: chat</option>' +
                '<option value="sparkles"' + (ic === 'sparkles' ? ' selected' : '') + '>Ícone: sparkles</option>' +
                '</select>' +
                '<select class="ea-subst-nativo md:col-span-2 px-2 py-1.5 border rounded-lg text-sm">' +
                '<option value=""' + (sn === '' ? ' selected' : '') + '>Não ocultar nativo</option>' +
                '<option value="jogos"' + (sn === 'jogos' ? ' selected' : '') + '>Ocultar: Games (Jogos)</option>' +
                '<option value="educalabs"' + (sn === 'educalabs' ? ' selected' : '') + '>Ocultar: EducaLabs</option>' +
                '<option value="educa_hits"' + (sn === 'educa_hits' ? ' selected' : '') + '>Ocultar: EducaHits</option>' +
                '<option value="notes"' + (sn === 'notes' ? ' selected' : '') + '>Ocultar: Meu Caderno</option>' +
                '</select>' +
                '<button type="button" class="ea-remove text-red-600 hover:text-red-800 text-sm md:col-span-1 font-medium">Remover</button>' +
                '</div>' +
                '<div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">' +
                '<label><input type="checkbox" class="ea-aluno rounded" ' + (link.aluno ? 'checked' : '') + '> Aluno</label>' +
                '<label><input type="checkbox" class="ea-professor rounded" ' + (link.professor ? 'checked' : '') + '> Professor</label>' +
                '<label><input type="checkbox" class="ea-nova-guia rounded" ' + (link.nova_guia ? 'checked' : '') + '> Nova guia</label>' +
                '<label><input type="checkbox" class="ea-enviar-contexto rounded" ' + (link.enviar_contexto_aluno ? 'checked' : '') + '> Enviar JSON de contexto</label>' +
                '</div>';
            wrap.querySelector('.ea-remove').addEventListener('click', function() {
                links.splice(i, 1);
                setLinks(links);
                renderLinks();
            });
            linksList.appendChild(wrap);
        });
    }
    function collectLinks() {
        var wraps = linksList.querySelectorAll(':scope > div');
        var out = [];
        wraps.forEach(function(wrap) {
            var row = wrap.querySelector('.grid');
            if (!row) { return; }
            var menuGrupo = (row.querySelector('.ea-menu-grupo') || {}).value || 'colag';
            if (['estudo', 'colag', 'entretenimento', 'sistema'].indexOf(menuGrupo) === -1) {
                menuGrupo = 'colag';
            }
            var menuIc = (row.querySelector('.ea-menu-icone') || {}).value || 'link';
            if (['link', 'book', 'game', 'music', 'document', 'globe', 'chat', 'sparkles'].indexOf(menuIc) === -1) {
                menuIc = 'link';
            }
            var subNat = (row.querySelector('.ea-subst-nativo') || {}).value || '';
            if (['', 'jogos', 'educalabs', 'educa_hits', 'notes'].indexOf(subNat) === -1) {
                subNat = '';
            }
            var idVal = ((wrap.querySelector('.ea-id') || {}).value || '').trim();
            var item = {
                nome: (row.querySelector('.ea-nome') || {}).value || '',
                url: (row.querySelector('.ea-url') || {}).value || '',
                menu_grupo: menuGrupo,
                menu_icone: menuIc,
                substituir_nativo: subNat,
                aluno: !!(wrap.querySelector('.ea-aluno') && wrap.querySelector('.ea-aluno').checked),
                professor: !!(wrap.querySelector('.ea-professor') && wrap.querySelector('.ea-professor').checked),
                nova_guia: !!(wrap.querySelector('.ea-nova-guia') && wrap.querySelector('.ea-nova-guia').checked),
                enviar_contexto_aluno: !!(wrap.querySelector('.ea-enviar-contexto') && wrap.querySelector('.ea-enviar-contexto').checked)
            };
            if (idVal !== '') {
                item.id = idVal;
            }
            out.push(item);
        });
        setLinks(out);
    }

    addBtn.addEventListener('click', function() {
        var links = getLinks();
        links.push({ nome: '', url: '', menu_grupo: 'colag', menu_icone: 'link', substituir_nativo: '', aluno: true, professor: false, nova_guia: true, enviar_contexto_aluno: false });
        setLinks(links);
        renderLinks();
    });

    document.querySelector('form').addEventListener('submit', function() {
        collectLinks();
    });

    renderLinks();
})();
</script>

