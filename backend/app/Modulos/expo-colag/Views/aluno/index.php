<?php
$projetos = $projetos ?? [];
$secoes = $secoes ?? ['abertas' => [], 'meus' => []];
$filtros = $filtros ?? [];
$areas = $areas ?? [];
$edicao = $edicao ?? null;
$minhas = $minhas_inscricoes ?? [];

$renderCard = static function (array $p): void {
    $restantes = (int) ($p['vagas_restantes'] ?? 0);
    $totais = max(1, (int) ($p['vagas_totais'] ?? 1));
    $pct = min(100, (int) round((($totais - $restantes) / $totais) * 100));
    $barClass = $pct >= 100 ? 'bg-red-500' : ($pct >= 70 ? 'bg-amber-500' : 'bg-emerald-500');
    ?>
    <a href="<?= URL ?>/expo-colag/projeto/<?= (int) $p['id'] ?>"
       class="block rounded-xl border border-gray-200 bg-white p-4 hover:border-accent hover:shadow-md transition">
        <div class="flex flex-wrap gap-1 mb-2">
            <?php if (!empty($p['minha_inscricao'])): ?>
                <span class="inline-flex text-xs font-medium px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">Meu projeto</span>
            <?php endif; ?>
            <?php if (!empty($p['janela_aberta']) && empty($p['lotado'])): ?>
                <span class="inline-flex text-xs font-medium px-2 py-0.5 rounded-full bg-sky-100 text-sky-800">Inscrições abertas</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($p['capa_url'])): ?>
            <img src="<?= htmlspecialchars($p['capa_url']) ?>" alt="" class="w-full aspect-[3/1] object-cover rounded-lg mb-3">
        <?php endif; ?>
        <h2 class="font-semibold text-gray-900 line-clamp-2"><?= htmlspecialchars($p['titulo'] ?? '') ?></h2>
        <?php if (!empty($p['area'])): ?>
            <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($p['area']) ?></p>
        <?php endif; ?>
        <p class="text-sm text-gray-600 mt-2"><?= htmlspecialchars($p['professor_nome'] ?? 'Professor') ?></p>
        <div class="mt-3">
            <div class="flex justify-between text-xs text-gray-500 mb-1">
                <span>Vagas</span>
                <span><?= !empty($p['lotado']) ? 'LOTADO' : ($restantes . ' restantes') ?></span>
            </div>
            <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full <?= $barClass ?>" style="width: <?= $pct ?>%"></div>
            </div>
        </div>
        <?php if (!empty($p['inscricoes_fim'])): ?>
            <p class="text-xs text-gray-400 mt-2">Inscrições até <?= htmlspecialchars(date('d/m/Y', strtotime($p['inscricoes_fim']))) ?></p>
        <?php endif; ?>
    </a>
    <?php
};
?>
<div class="max-w-6xl mx-auto px-4 py-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Expo Colag</h1>
            <p class="text-sm text-gray-600 mt-1">
                <?php if (!empty($edicao['data_evento'])): ?>
                    Feira de projetos · evento em <?= htmlspecialchars(date('d/m/Y', strtotime($edicao['data_evento']))) ?>
                <?php else: ?>
                    Mural de projetos da feira
                <?php endif; ?>
            </p>
        </div>
        <a href="<?= URL ?>/expo-colag/programacao" class="px-3 py-2 rounded-lg text-sm border border-gray-300 bg-white hover:bg-gray-50">Programação</a>
    </div>

    <form method="get" action="<?= URL ?>/expo-colag" class="rounded-xl border border-gray-200 bg-white p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[160px]">
            <label class="block text-xs text-gray-500 mb-1">Buscar</label>
            <input type="text" name="q" value="<?= htmlspecialchars($filtros['q'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Título, área, professor…">
        </div>
        <div class="min-w-[140px]">
            <label class="block text-xs text-gray-500 mb-1">Área</label>
            <select name="area" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm">
                <option value="">Todas</option>
                <?php foreach ($areas as $area): ?>
                    <option value="<?= htmlspecialchars($area) ?>" <?= ($filtros['area'] ?? '') === $area ? 'selected' : '' ?>><?= htmlspecialchars($area) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <label class="inline-flex items-center gap-2 text-sm pb-2">
            <input type="checkbox" name="so_com_vagas" value="1" <?= !empty($filtros['so_com_vagas']) ? 'checked' : '' ?>> Só com vagas
        </label>
        <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium">Filtrar</button>
    </form>

    <?php
    $meusCards = $secoes['meus'] ?? [];
    $minhasAtivas = [];
    foreach ($minhas as $m) {
        if (in_array($m['status'] ?? '', ['Aguardando', 'Aprovada', 'Lista_espera'], true)) {
            $minhasAtivas[] = $m;
        }
    }
    ?>
    <?php if ($meusCards !== [] || $minhasAtivas !== []): ?>
        <section>
            <h2 class="text-lg font-semibold text-gray-900 mb-3">Meus projetos</h2>
            <?php if ($meusCards !== []): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($meusCards as $p) { $renderCard($p); } ?>
                </div>
            <?php else: ?>
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4">
                    <ul class="text-sm space-y-1">
                        <?php foreach ($minhasAtivas as $m): ?>
                            <li>
                                <a href="<?= URL ?>/expo-colag/projeto/<?= (int) $m['projeto_id'] ?>" class="text-accent hover:underline font-medium">
                                    <?= htmlspecialchars($m['projeto_titulo'] ?? '') ?>
                                </a>
                                <span class="text-gray-500">· <?= htmlspecialchars(str_replace('_', ' ', $m['status'] ?? '')) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php
    $blocos = [
        ['key' => 'abertas', 'titulo' => 'Inscrições abertas'],
    ];
    $algum = $meusCards !== [] || $minhasAtivas !== [];
    $idsMostrados = [];
    foreach ($meusCards as $p) {
        $idsMostrados[(int) ($p['id'] ?? 0)] = true;
    }
    foreach ($blocos as $b):
        $lista = $secoes[$b['key']] ?? [];
        if ($lista === []) continue;
        $algum = true;
        foreach ($lista as $p) {
            $idsMostrados[(int) ($p['id'] ?? 0)] = true;
        }
    ?>
        <section>
            <h2 class="text-lg font-semibold text-gray-900 mb-3"><?= htmlspecialchars($b['titulo']) ?></h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($lista as $p) { $renderCard($p); } ?>
            </div>
        </section>
    <?php endforeach; ?>

    <?php
    $outros = [];
    foreach ($projetos as $p) {
        if (empty($idsMostrados[(int) ($p['id'] ?? 0)])) {
            $outros[] = $p;
        }
    }
    ?>
    <?php if ($outros !== []): ?>
        <section>
            <h2 class="text-lg font-semibold text-gray-900 mb-3"><?= $algum ? 'Outros projetos' : 'Todos os projetos' ?></h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($outros as $p) { $renderCard($p); } ?>
            </div>
        </section>
    <?php elseif (!$algum): ?>
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500">
            Nenhum projeto disponível com esses filtros.
        </div>
    <?php endif; ?>
</div>
