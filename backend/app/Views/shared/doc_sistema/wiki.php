<?php
$paginas = is_array($paginas ?? null) ? $paginas : [];
$pagina = is_array($pagina_atual ?? null) ? $pagina_atual : null;
$slugAtual = (string) ($pagina['slug'] ?? '');
$md = (string) ($pagina['conteudo_md'] ?? '');
$urlBase = rtrim((string) ($wiki_url_base ?? (URL . '/admin/doc-sistema')), '/');
$voltarHref = (string) ($wiki_voltar_href ?? (URL . '/admin/assistente'));
$voltarLabel = (string) ($wiki_voltar_label ?? 'Assistente');
$subtitulo = (string) ($wiki_subtitulo ?? 'Documentação viva em doc_sistema/. Edite o .md — a página atualiza sozinha.');
$tituloWiki = (string) ($wiki_titulo ?? 'Documentação');
?>
<div class="doc-wiki space-y-4" id="doc-wiki-root">
<script type="application/json" id="doc-wiki-md"><?= json_encode($md, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>

    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Wiki interna</p>
            <h1 class="text-2xl font-bold text-gray-900 mt-0.5"><?= htmlspecialchars($tituloWiki, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($subtitulo, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="<?= htmlspecialchars($voltarHref, ENT_QUOTES, 'UTF-8') ?>"
               class="inline-flex items-center gap-1.5 px-3 py-2 text-sm rounded-xl border border-gray-200 text-gray-700 hover:bg-gray-50">
                <?= htmlspecialchars($voltarLabel, ENT_QUOTES, 'UTF-8') ?>
            </a>
        </div>
    </div>

    <?php if ($paginas === []): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Nenhum arquivo <code>.md</code> em <code>doc_sistema/</code>.
        </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex flex-col lg:flex-row"
         style="min-height: min(78vh, 48rem);">

        <!-- Índice de páginas -->
        <aside class="w-full lg:w-56 shrink-0 border-b lg:border-b-0 lg:border-r border-gray-200 bg-slate-50 flex flex-col">
            <div class="px-3 py-2.5 border-b border-gray-200 text-xs font-semibold uppercase tracking-wide text-slate-500">
                Páginas
            </div>
            <nav class="flex-1 overflow-y-auto p-2 space-y-0.5">
                <?php foreach ($paginas as $p): ?>
                    <?php
                    $ativo = ($p['slug'] ?? '') === $slugAtual;
                    $href = $urlBase . '/' . rawurlencode((string) $p['slug']);
                    ?>
                    <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                       class="block px-3 py-2 rounded-lg text-sm transition-colors <?= $ativo
                           ? 'bg-primary/10 text-gray-900 font-semibold border border-primary/20'
                           : 'text-slate-700 hover:bg-white border border-transparent' ?>">
                        <?= htmlspecialchars((string) ($p['titulo'] ?? $p['slug']), ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($p['atualizado_em'])): ?>
                            <span class="block text-[10px] font-normal text-slate-400 mt-0.5"><?= htmlspecialchars($p['atualizado_em'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <!-- Conteúdo + TOC -->
        <div class="flex-1 min-w-0 flex flex-col lg:flex-row">
            <article class="flex-1 min-w-0 overflow-y-auto px-4 sm:px-8 py-6">
                <?php if (!empty($pagina['atualizado_em'])): ?>
                    <p class="text-xs text-slate-400 mb-4">Atualizado em <?= htmlspecialchars($pagina['atualizado_em'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <div id="doc-wiki-body" class="doc-wiki-prose max-w-3xl">
                    <p class="text-slate-500 text-sm">Carregando documentação…</p>
                </div>
            </article>

            <aside class="hidden xl:block w-52 shrink-0 border-l border-gray-100 bg-white px-3 py-6 overflow-y-auto">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 mb-2">Nesta página</p>
                <nav id="doc-wiki-toc" class="space-y-1 text-xs text-slate-600"></nav>
            </aside>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.doc-wiki-prose { color: #1e293b; line-height: 1.65; font-size: 0.95rem; }
.doc-wiki-prose h1 { font-size: 1.75rem; font-weight: 700; margin: 0 0 1rem; color: #0f172a; letter-spacing: -0.02em; }
.doc-wiki-prose h2 {
    font-size: 1.25rem; font-weight: 700; margin: 2rem 0 0.75rem; padding-bottom: 0.35rem;
    border-bottom: 1px solid #e2e8f0; color: #0f172a; scroll-margin-top: 5rem;
}
.doc-wiki-prose h3 { font-size: 1.05rem; font-weight: 650; margin: 1.5rem 0 0.5rem; color: #1e293b; scroll-margin-top: 5rem; }
.doc-wiki-prose h4 { font-size: 0.95rem; font-weight: 600; margin: 1.25rem 0 0.4rem; color: #334155; }
.doc-wiki-prose p { margin: 0.65rem 0; }
.doc-wiki-prose ul, .doc-wiki-prose ol { margin: 0.65rem 0; padding-left: 1.35rem; }
.doc-wiki-prose li { margin: 0.25rem 0; }
.doc-wiki-prose ul { list-style: disc; }
.doc-wiki-prose ol { list-style: decimal; }
.doc-wiki-prose blockquote {
    margin: 1rem 0; padding: 0.75rem 1rem; border-left: 3px solid var(--primary-color, #6366f1);
    background: #f8fafc; border-radius: 0 0.5rem 0.5rem 0; color: #475569; font-size: 0.9rem;
}
.doc-wiki-prose code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.82em; background: #f1f5f9; color: #0f172a; padding: 0.1em 0.35em; border-radius: 0.25rem;
}
.doc-wiki-prose pre {
    margin: 1rem 0; padding: 0.9rem 1rem; background: #0f172a; color: #e2e8f0;
    border-radius: 0.75rem; overflow-x: auto; font-size: 0.8rem; line-height: 1.5;
}
.doc-wiki-prose pre code { background: transparent; color: inherit; padding: 0; font-size: inherit; }
.doc-wiki-prose table {
    width: 100%; margin: 1rem 0; border-collapse: collapse; font-size: 0.875rem;
    border: 1px solid #e2e8f0; border-radius: 0.5rem; overflow: hidden;
}
.doc-wiki-prose thead { background: #f8fafc; }
.doc-wiki-prose th {
    text-align: left; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em;
    color: #64748b; font-weight: 600; padding: 0.55rem 0.75rem; border-bottom: 1px solid #e2e8f0;
}
.doc-wiki-prose td { padding: 0.55rem 0.75rem; border-top: 1px solid #f1f5f9; vertical-align: top; }
.doc-wiki-prose tr:nth-child(even) td { background: #fafbfc; }
.doc-wiki-prose a { color: var(--primary-color, #4f46e5); text-decoration: underline; text-underline-offset: 2px; }
.doc-wiki-prose a:hover { opacity: 0.85; }
.doc-wiki-prose hr { border: 0; border-top: 1px solid #e2e8f0; margin: 1.75rem 0; }
.doc-wiki-prose strong { font-weight: 650; color: #0f172a; }
#doc-wiki-toc a { display: block; padding: 0.2rem 0; border-radius: 0.25rem; color: #64748b; text-decoration: none; }
#doc-wiki-toc a:hover { color: #0f172a; }
#doc-wiki-toc a.toc-h3 { padding-left: 0.75rem; font-size: 0.7rem; }
</style>

<script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
<script>
(function () {
    var root = document.getElementById('doc-wiki-root');
    var body = document.getElementById('doc-wiki-body');
    var toc = document.getElementById('doc-wiki-toc');
    if (!root || !body || typeof marked === 'undefined') {
        if (body) body.innerHTML = '<p class="text-rose-600 text-sm">Não foi possível carregar o renderizador Markdown.</p>';
        return;
    }

    var mdEl = document.getElementById('doc-wiki-md');
    var md = '';
    try { md = JSON.parse(mdEl ? mdEl.textContent : '""'); } catch (e) { md = ''; }
    marked.setOptions({ gfm: true, breaks: false });

    function slugify(text) {
        return String(text || '')
            .toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'sec';
    }

    body.innerHTML = marked.parse(md);

    // Âncoras + links internos *.md → wiki
    var used = {};
    body.querySelectorAll('h1, h2, h3, h4').forEach(function (el) {
        var base = slugify(el.textContent);
        var id = base;
        if (used[id]) { used[id]++; id = base + '-' + used[id]; } else { used[id] = 1; }
        el.id = id;
    });
    var wikiBase = <?= json_encode($urlBase . '/', JSON_UNESCAPED_SLASHES) ?>;
    body.querySelectorAll('a[href]').forEach(function (a) {
        var h = a.getAttribute('href') || '';
        if (/^[a-z0-9_-]+\.md$/i.test(h)) {
            a.setAttribute('href', wikiBase + h.replace(/\.md$/i, ''));
        }
    });

    if (toc) {
        var headings = body.querySelectorAll('h2, h3');
        if (!headings.length) {
            toc.innerHTML = '<span class="text-slate-400">—</span>';
        } else {
            var html = '';
            headings.forEach(function (el) {
                var cls = el.tagName === 'H3' ? 'toc-h3' : '';
                html += '<a class="' + cls + '" href="#' + el.id + '">' + (el.textContent || '') + '</a>';
            });
            toc.innerHTML = html;
        }
    }
})();
</script>
