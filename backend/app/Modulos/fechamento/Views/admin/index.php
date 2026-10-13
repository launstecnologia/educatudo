<?php

$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$periodoTipo = (string) ($periodo_tipo ?? 'ano');
$periodoNumero = (int) ($periodo_numero ?? 0);
$schemaPronto = !empty($schema_pronto);

$page_header_title = 'Fechamento';
$page_header_subtitle = 'Painel por turma e período: pendências, recuperação, homologação e retificação.';
$turmaIdFiltro = (int) ($turma_id ?? 0);
$filtrosAtivosCount = 0;
if ($anoLetivo !== (int) date('Y')) {
    $filtrosAtivosCount++;
}
if ($periodoTipo !== 'ano') {
    $filtrosAtivosCount++;
}
if ($periodoNumero > 0) {
    $filtrosAtivosCount++;
}
if ($turmaIdFiltro > 0) {
    $filtrosAtivosCount++;
}
$loteTitulo = 'Carregando as turmas deste filtro.';
ob_start();
?>
<a href="<?= URL ?>/admin/resultados-finais/relatorios" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Relatórios</a>
<span id="fech-lote-slot" class="inline-flex items-center px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-medium text-gray-400 bg-gray-50 cursor-wait"
      title="<?= htmlspecialchars($loteTitulo, ENT_QUOTES, 'UTF-8') ?>">
    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Consolidação Final
</span>
<?php
$ui_btn_variant = 'filtro';
$ui_btn_label = 'Filtros';
$ui_btn_icon = 'fa-solid fa-filter';
$ui_btn_onclick = 'openFilterDrawer()';
$ui_btn_filter_count = $filtrosAtivosCount;
include __DIR__ . '/../../../../Views/admin/_partials/ui/btn.php';
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';

if (!$schemaPronto):
?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    Rode a migration <code class="text-sm">2026_09_09_fechamento_periodo.sql</code> no painel Master. O painel já lista turmas; o estado canônico só persiste depois da migration.
</div>
<?php endif;

$filtros_mostrar_turma = true;
$filtros_action = URL . '/admin/fechamento';
$filtros_offcanvas = true;
include __DIR__ . '/../../../../Views/admin/resultados-finais/_filtros.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" aria-busy="true" id="fech-painel-tabela">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alunos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frequência</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recuperação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pendências</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody id="fech-painel-corpo" class="bg-white divide-y divide-gray-200">
                <?php for ($sk = 0; $sk < 8; $sk++): ?>
                <tr aria-hidden="true">
                    <?php for ($col = 0; $col < 7; $col++): ?>
                    <td class="px-6 py-4">
                        <div class="h-4 rounded bg-gray-100 animate-pulse <?= $col === 0 ? 'w-24' : ($col === 6 ? 'w-16 ml-auto' : 'w-14') ?>"></div>
                    </td>
                    <?php endfor; ?>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <p id="fech-painel-status" class="px-6 py-3 text-sm text-gray-500 border-t border-gray-100">
        <i class="fa-solid fa-spinner fa-spin mr-2"></i>Carregando turmas…
    </p>
</div>
<?php include __DIR__ . '/_drawer_consulta.php'; ?>
<script>
(function () {
    var corpo = document.getElementById('fech-painel-corpo');
    var status = document.getElementById('fech-painel-status');
    var tabela = document.getElementById('fech-painel-tabela');
    if (!corpo) return;

    function falha() {
        if (tabela) tabela.setAttribute('aria-busy', 'false');
        corpo.innerHTML = '<tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">'
            + '<p class="mb-3">Não foi possível carregar o painel.</p>'
            + '<button type="button" class="text-sm font-medium text-indigo-700 hover:underline" id="fech-painel-retry">Tentar de novo</button>'
            + '</td></tr>';
        if (status) status.classList.add('hidden');
        document.getElementById('fech-painel-retry')?.addEventListener('click', carregar);
    }

    function aplicarLote(info) {
        var el = document.getElementById('fech-lote-slot');
        if (!el || !info) return;
        var titulo = info.titulo || '';
        if (info.pode && info.href) {
            var link = document.createElement('a');
            link.id = 'fech-lote-slot';
            link.href = info.href;
            link.title = titulo;
            link.className = 'inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50';
            link.innerHTML = '<i class="fa-solid fa-print mr-2"></i> Consolidação Final';
            el.replaceWith(link);
            return;
        }
        el.className = 'inline-flex items-center px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-medium text-gray-400 bg-gray-50 cursor-not-allowed';
        el.title = titulo;
        el.innerHTML = '<i class="fa-solid fa-print mr-2"></i> Consolidação Final';
    }

    function carregar() {
        if (tabela) tabela.setAttribute('aria-busy', 'true');
        if (status) {
            status.classList.remove('hidden');
            status.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Carregando turmas…';
        }
        var url = <?= json_encode(URL . '/admin/fechamento/painel', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> + window.location.search;
        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error('painel');
                return res.json();
            })
            .then(function (data) {
                corpo.innerHTML = data.html || '';
                if (tabela) tabela.setAttribute('aria-busy', 'false');
                if (status) status.classList.add('hidden');
                aplicarLote(data.lote || null);
            })
            .catch(falha);
    }

    carregar();
})();
</script>
