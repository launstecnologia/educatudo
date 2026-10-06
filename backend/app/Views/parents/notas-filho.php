<?php
$filho = $filho ?? [];
$filtroAnoLetivo = isset($filtro_ano_letivo) ? (int) $filtro_ano_letivo : 0;
$filtroBimestre = isset($filtro_bimestre) ? (int) $filtro_bimestre : 0;
$anosDisponiveis = is_array($anos_disponiveis ?? null) ? $anos_disponiveis : [];
$baseUrlNotas = URL . '/pais/filhos/' . (int) ($filho['id'] ?? 0) . '/notas';
$nomeFilho = htmlspecialchars((string) ($filho['nome'] ?? 'seu filho'), ENT_QUOTES, 'UTF-8');
$secaoInicial = (isset($_GET['secao']) && (string) $_GET['secao'] === 'provas') ? 'provas' : 'notas';
?>

<div class="max-w-7xl mx-auto p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Notas e provas</h1>
        <p class="text-gray-600">Acompanhe as notas e as provas de <?= $nomeFilho ?>.</p>
    </div>

    <div class="flex gap-2 border-b border-gray-200 mb-6" role="tablist">
        <button type="button" data-aba-portal="notas" class="aba-portal px-4 py-2 text-sm font-semibold border-b-2 -mb-px" role="tab">Notas</button>
        <button type="button" data-aba-portal="provas" class="aba-portal px-4 py-2 text-sm font-semibold border-b-2 -mb-px" role="tab">Provas</button>
    </div>

    <div data-painel-portal="notas" id="notas">
        <?php
        $notas_perfil = 'pais';
        require __DIR__ . '/../partials/notas_portal_coordenacao.php';
        ?>
    </div>

    <div data-painel-portal="provas" class="hidden">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <form method="get" action="<?= htmlspecialchars($baseUrlNotas, ENT_QUOTES, 'UTF-8') ?>" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">
                <input type="hidden" name="secao" value="provas">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Ano letivo</label>
                    <select name="ano_letivo" data-periodo-ano class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                        <option value="">Todos</option>
                        <?php foreach ($anosDisponiveis as $anoOpt): ?>
                            <option value="<?= (int) $anoOpt ?>" <?= $filtroAnoLetivo === (int) $anoOpt ? 'selected' : '' ?>><?= (int) $anoOpt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1" data-periodo-label>Bimestre</label>
                    <select name="bimestre" data-periodo-letivo-select data-periodo-todos="1" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                        <?php
                        if (!class_exists('PeriodoLetivo')) {
                            require_once __DIR__ . '/../../../Core/PeriodoLetivo.php';
                        }
                        $anoFiltroPais = $filtroAnoLetivo > 0 ? $filtroAnoLetivo : (int) date('Y');
                        echo PeriodoLetivo::optionsHtml($anoFiltroPais, $filtroBimestre, ['todos' => true]);
                        ?>
                    </select>
                </div>
                <div class="md:col-span-2 flex items-end gap-2">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">Filtrar</button>
                    <a href="<?= htmlspecialchars($baseUrlNotas . '?secao=provas', ENT_QUOTES, 'UTF-8') ?>" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">Limpar</a>
                </div>
            </form>

            <?php require __DIR__ . '/../partials/provas_matriz_blocos.php'; ?>
        </div>
    </div>
</div>
<script>
(function () {
    var inicial = <?= json_encode($secaoInicial, JSON_UNESCAPED_UNICODE) ?>;
    var botoes = document.querySelectorAll('[data-aba-portal]');
    var paineis = document.querySelectorAll('[data-painel-portal]');
    function abrir(aba) {
        botoes.forEach(function (btn) {
            var on = btn.getAttribute('data-aba-portal') === aba;
            btn.classList.toggle('border-blue-600', on);
            btn.classList.toggle('text-blue-700', on);
            btn.classList.toggle('border-transparent', !on);
            btn.classList.toggle('text-gray-500', !on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        paineis.forEach(function (painel) {
            painel.classList.toggle('hidden', painel.getAttribute('data-painel-portal') !== aba);
        });
    }
    botoes.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var aba = btn.getAttribute('data-aba-portal') || 'notas';
            abrir(aba);
            if (history.replaceState) {
                history.replaceState(null, '', aba === 'provas' ? '?secao=provas' : location.pathname);
            }
        });
    });
    var hash = (location.hash || '').replace('#', '');
    if (hash === 'provas') inicial = 'provas';
    if (hash === 'notas') inicial = 'notas';
    abrir(inicial === 'provas' ? 'provas' : 'notas');
})();
</script>
