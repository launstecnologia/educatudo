<?php
$filho = $filho ?? [];
$nomeFilho = htmlspecialchars((string) ($filho['nome'] ?? 'seu filho'), ENT_QUOTES, 'UTF-8');
$secaoPedida = isset($_GET['secao']) ? (string) $_GET['secao'] : '';
$secaoInicial = in_array($secaoPedida, ['boletim', 'notas', 'provas'], true) ? $secaoPedida : 'boletim';
?>

<div class="max-w-7xl mx-auto p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Boletim, notas e provas</h1>
        <p class="text-gray-600">Acompanhe o boletim, as notas e as provas de <?= $nomeFilho ?>.</p>
    </div>

    <div class="flex gap-2 border-b border-gray-200 mb-6" role="tablist">
        <button type="button" data-aba-portal="boletim" class="aba-portal px-4 py-2 text-sm font-semibold border-b-2 -mb-px" role="tab">Boletim</button>
        <button type="button" data-aba-portal="notas" class="aba-portal px-4 py-2 text-sm font-semibold border-b-2 -mb-px" role="tab">Notas</button>
        <button type="button" data-aba-portal="provas" class="aba-portal px-4 py-2 text-sm font-semibold border-b-2 -mb-px" role="tab">Provas</button>
    </div>

    <div data-painel-portal="boletim" id="boletim">
        <?php require __DIR__ . '/../partials/boletim_portal.php'; ?>
    </div>

    <div data-painel-portal="notas" id="notas" class="hidden">
        <?php
        $notas_perfil = 'pais';
        require __DIR__ . '/../partials/notas_portal_coordenacao.php';
        ?>
    </div>

    <div data-painel-portal="provas" class="hidden">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
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
            var aba = btn.getAttribute('data-aba-portal') || 'boletim';
            abrir(aba);
            if (history.replaceState) {
                history.replaceState(null, '', aba === 'boletim' ? location.pathname : '?secao=' + aba);
            }
        });
    });
    var hash = (location.hash || '').replace('#', '');
    if (hash === 'boletim' || hash === 'notas' || hash === 'provas') inicial = hash;
    abrir(inicial === 'notas' || inicial === 'provas' ? inicial : 'boletim');
})();
</script>
