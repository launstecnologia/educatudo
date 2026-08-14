<?php
$escola = $escola ?? [];
$current_section = $current_section ?? 'visao-geral';
$section_view = $section_view ?? 'visao-geral';

$sections = [
    ['id' => 'provas-ao-vivo', 'url' => 'provas-ao-vivo', 'label' => 'Provas ao vivo', 'desc' => 'Quem está fazendo, quem já fez e matérias concluídas.', 'pagina_cheia' => true, 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>'],
    ['id' => 'usuarios', 'url' => 'usuarios', 'label' => 'Usuários', 'desc' => 'Gerenciar administradores da escola.', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>'],
    ['id' => 'professores', 'url' => 'professores', 'label' => 'Professores', 'desc' => 'Listar professores e acessar como usuário.', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'],
    ['id' => 'alunos', 'url' => 'alunos', 'label' => 'Alunos', 'desc' => 'Consultar alunos, turmas e créditos.', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>'],
    ['id' => 'modulos', 'url' => 'modulos', 'label' => 'Módulos', 'desc' => 'Ativar ou desativar funcionalidades.', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>'],
    ['id' => 'creditos', 'url' => 'creditos', 'label' => 'TudiCoins', 'desc' => 'Planos, pacotes e saldo de TudiCoins.', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'],
    ['id' => 'layout', 'url' => 'layout', 'label' => 'Layout', 'desc' => 'Cores, logos e aparência da escola.', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>'],
    ['id' => 'sliders', 'url' => 'sliders', 'label' => 'Sliders', 'desc' => 'Banners e carrosséis do portal.', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/><circle cx="8" cy="6" r="1.5"/><circle cx="16" cy="12" r="1.5"/><circle cx="10" cy="18" r="1.5"/>'],
    ['id' => 'links-uteis', 'url' => 'links-uteis', 'label' => 'Links Úteis', 'desc' => 'Links rápidos exibidos aos usuários.', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 015.656 0l1.414 1.414a4 4 0 010 5.656l-1.414 1.414a4 4 0 01-5.656 0M10.172 13.828a4 4 0 01-5.656 0L3.102 12.414a4 4 0 010-5.656L4.516 5.344a4 4 0 015.656 0"/>'],
    ['id' => 'apps-externos', 'url' => 'apps-externos', 'label' => 'Apps Externos', 'desc' => 'Integrações e aplicativos vinculados.', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 015.656 0l1.414 1.414a4 4 0 010 5.656l-1.414 1.414a4 4 0 01-5.656 0M10.172 13.828a4 4 0 01-5.656 0L3.102 12.414a4 4 0 010-5.656L4.516 5.344a4 4 0 015.656 0"/>'],
    ['id' => 'prompts', 'url' => 'prompts', 'label' => 'Prompts IA', 'desc' => 'Templates de prompts de inteligência artificial.', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>'],
    ['id' => 'limites', 'url' => 'limites', 'label' => 'Limites', 'desc' => 'Capacidade máxima de usuários e recursos.', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>'],
    ['id' => 'banco', 'url' => 'banco', 'label' => 'Banco de Dados', 'desc' => 'Conexão, migrations e diagnóstico.', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>'],
];
?>

<div class="mb-6">
    <a href="<?= URL ?>/master/escolas" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 transition-colors duration-200">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Voltar às escolas
    </a>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3 flex-wrap min-w-0">
            <h2 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($escola['nome'] ?? '') ?></h2>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700"><?= htmlspecialchars($escola['slug'] ?? '') ?></span>
            <?php if (!empty($escola['dominio'])): ?>
            <span class="text-sm text-slate-500"><?= htmlspecialchars($escola['dominio']) ?></span>
            <?php endif; ?>
            <?php if (!empty($escola['ativo'])): ?>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">Ativa</span>
            <?php else: ?>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-200">Inativa</span>
            <?php endif; ?>
        </div>
        <a href="<?= URL ?>/master/escolas/editar?id=<?= (int) $escola['id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition-all duration-200 shrink-0">
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar dados básicos
        </a>
    </div>
</div>

<?php $flash_msg = isset($flash) ? ($flash['message'] ?? null) : null; ?>
<?php if (!empty($flash_msg)): ?>
<div class="mb-6 px-4 py-3 rounded-lg border <?= (isset($flash['type']) && $flash['type'] === 'error') ? 'bg-red-50 border-red-200 text-red-800' : ((isset($flash['type']) && $flash['type'] === 'warning') ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-green-50 border-green-200 text-green-800') ?>">
    <?= htmlspecialchars($flash_msg) ?>
</div>
<?php endif; ?>

<?php
$backUrl = URL . '/master/escolas/' . $escola['id'] . '/detalhes';
$escolaBasePath = '/master/escolas/' . $escola['id'] . '/';
$paginasCheias = ['visao-geral', 'provas-ao-vivo'];
$hasOffcanvasContent = !in_array($current_section, $paginasCheias, true);
$currentLabel = 'Visão Geral';
foreach ($sections as $s) {
    if ($s['id'] === $current_section) {
        $currentLabel = $s['label'];
        break;
    }
}
?>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-6">
    <?php foreach ($sections as $s):
        $active = ($current_section === $s['id']);
        $cardClass = $active
            ? 'border-blue-500 ring-1 ring-blue-100 bg-blue-50/30'
            : 'border-slate-200 hover:border-blue-500 hover:shadow-md hover:-translate-y-0.5';
    ?>
    <a href="<?= URL ?>/master/escolas/<?= $escola['id'] ?>/<?= $s['url'] ?>"
       data-section-card data-section-id="<?= $s['id'] ?>" data-section-label="<?= htmlspecialchars($s['label']) ?>"
       <?= !empty($s['pagina_cheia']) ? 'data-pagina-cheia="1"' : '' ?>
       class="group block bg-white rounded-xl border shadow-sm transition-all duration-200 p-6 <?= $cardClass ?>">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center mb-4 group-hover:bg-slate-200/80 transition-colors duration-200">
                    <svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $s['icon'] ?></svg>
                </div>
                <h3 class="text-base font-semibold text-slate-900"><?= htmlspecialchars($s['label']) ?></h3>
                <p class="text-sm text-slate-500 mt-1 leading-snug"><?= htmlspecialchars($s['desc']) ?></p>
            </div>
            <svg class="w-5 h-5 text-slate-400 group-hover:text-blue-600 shrink-0 mt-1 transition-colors duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<?php if (in_array($current_section, $paginasCheias, true)): ?>
<?php include __DIR__ . '/detail/' . $section_view . '.php'; ?>
<?php endif; ?>

<div id="master-offcanvas-overlay"
     class="fixed inset-0 bg-black/40 z-[60] <?= $hasOffcanvasContent ? '' : 'hidden' ?>"></div>
<div id="master-offcanvas-panel"
     class="fixed top-0 right-0 h-full w-full max-w-2xl bg-white shadow-2xl z-[70] overflow-y-auto transition-transform duration-200 border-l border-slate-200 <?= $hasOffcanvasContent ? '' : 'hidden translate-x-full' ?>">
    <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between z-10">
        <h3 id="master-offcanvas-title" class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($currentLabel) ?></h3>
        <a href="<?= $backUrl ?>" id="master-offcanvas-close" class="p-1 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition-colors duration-200" aria-label="Fechar">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </a>
    </div>
    <div id="master-offcanvas-body" class="p-6">
        <?php if ($hasOffcanvasContent): ?>
        <?php include __DIR__ . '/detail/' . $section_view . '.php'; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var backUrl = <?= json_encode($backUrl) ?>;
    var escolaBasePath = <?= json_encode($escolaBasePath) ?>;
    var overlay = document.getElementById('master-offcanvas-overlay');
    var panel = document.getElementById('master-offcanvas-panel');
    var panelBody = document.getElementById('master-offcanvas-body');
    var panelTitle = document.getElementById('master-offcanvas-title');

    function executeScripts(container) {
        container.querySelectorAll('script').forEach(function(oldScript) {
            var newScript = document.createElement('script');
            for (var i = 0; i < oldScript.attributes.length; i++) {
                newScript.setAttribute(oldScript.attributes[i].name, oldScript.attributes[i].value);
            }
            newScript.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    }

    function openPanel() {
        overlay.classList.remove('hidden');
        panel.classList.remove('hidden');
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                panel.classList.remove('translate-x-full');
            });
        });
    }

    function closePanel(pushBack) {
        panel.classList.add('translate-x-full');
        overlay.classList.add('hidden');
        setTimeout(function() { panel.classList.add('hidden'); }, 200);
        if (pushBack !== false) history.pushState({ section: 'visao-geral' }, '', backUrl);
    }

    function loadSection(url, label, sectionId, pushState) {
        panelBody.innerHTML = '<div class="p-10 text-center text-slate-400 text-sm">Carregando…</div>';
        panelTitle.textContent = label;
        openPanel();
        fetch(url, { headers: { 'X-Requested-With': 'fragment' } })
            .then(function(res) {
                if (!res.ok) throw new Error('fragment request failed');
                return res.text();
            })
            .then(function(html) {
                panelBody.innerHTML = html;
                executeScripts(panelBody);
                if (pushState !== false) history.pushState({ section: sectionId }, '', url);
            })
            .catch(function() {
                window.location.href = url;
            });
    }

    function isFragmentLink(link) {
        if (!link || link.target || link.hasAttribute('download') || link.dataset.noAjax !== undefined) return false;
        var href = link.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#') return false;
        try {
            var u = new URL(href, window.location.origin);
            return u.origin === window.location.origin && u.pathname.indexOf(escolaBasePath) === 0;
        } catch (e) {
            return false;
        }
    }

    document.querySelectorAll('[data-section-card]').forEach(function(card) {
        card.addEventListener('click', function(e) {
            if (card.getAttribute('data-pagina-cheia') === '1') return;
            e.preventDefault();
            loadSection(card.getAttribute('href'), card.getAttribute('data-section-label'), card.getAttribute('data-section-id'));
        });
    });

    panelBody.addEventListener('click', function(e) {
        var link = e.target.closest('a');
        if (!isFragmentLink(link)) return;
        e.preventDefault();
        loadSection(link.getAttribute('href'), panelTitle.textContent, null);
    });

    overlay.addEventListener('click', function() { closePanel(); });
    document.getElementById('master-offcanvas-close').addEventListener('click', function(e) {
        e.preventDefault();
        closePanel();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !panel.classList.contains('hidden')) closePanel();
    });
    window.addEventListener('popstate', function() {
        window.location.reload();
    });

    <?php if ($hasOffcanvasContent): ?>
    panel.classList.add('translate-x-full');
    requestAnimationFrame(function() {
        requestAnimationFrame(function() {
            panel.classList.remove('translate-x-full');
        });
    });
    <?php endif; ?>
});
</script>
