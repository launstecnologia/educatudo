<?php
$diag = is_array($diag ?? null) ? $diag : ['passos' => [], 'completos' => 0, 'total' => 0, 'percentual' => 0, 'ano' => (int) date('Y')];
$passos = is_array($diag['passos'] ?? null) ? $diag['passos'] : [];
$percentual = (int) ($diag['percentual'] ?? 0);

$page_header_title = 'Implantar acadêmico';
$page_header_subtitle = 'Progresso detectado no estado real desta escola — da estrutura anual ao boletim, na ordem de dependência.';
$page_header_back_url = URL . '/admin/academico';
include __DIR__ . '/../../../../Views/admin/_partials/page_header_form.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<div class="bg-white rounded-xl shadow-lg p-6 w-full mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <div>
            <p class="text-sm font-medium text-gray-900">Ano <?= (int) ($diag['ano'] ?? date('Y')) ?></p>
            <p class="text-sm text-gray-600"><?= (int) ($diag['completos'] ?? 0) ?> de <?= (int) ($diag['total'] ?? 0) ?> passos prontos</p>
        </div>
        <span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold <?= $percentual === 100 ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' ?>">
            <?= $percentual ?>%
        </span>
    </div>
    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
        <div class="h-2 bg-primary rounded-full" style="width: <?= max(0, min(100, $percentual)) ?>%"></div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 w-full">
    <ul class="divide-y divide-gray-100">
        <?php foreach ($passos as $p):
            $ok = !empty($p['ok']);
            $comoFazer = is_array($p['como_fazer'] ?? null) ? $p['como_fazer'] : [];
            $erros = is_array($p['erros_comuns'] ?? null) ? $p['erros_comuns'] : [];
            $prints = is_array($p['prints'] ?? null) ? $p['prints'] : [];
            $printsVisiveis = array_values(array_filter($prints, static fn ($pr) => !empty($pr['existe'])));
            $temProfundidade = $comoFazer !== [] || $erros !== [] || $printsVisiveis !== [] || !empty($p['usado_depois_em']);
            ?>
        <li class="py-4">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div class="flex items-start gap-3 min-w-0">
                    <span class="mt-0.5 inline-flex w-6 h-6 items-center justify-center rounded-full text-xs font-bold shrink-0 <?= $ok ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                        <?= $ok ? '✓' : '·' ?>
                    </span>
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($p['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="text-sm text-gray-600"><?= htmlspecialchars((string) ($p['detalhe'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if (!empty($p['aceite'])): ?>
                        <p class="text-xs text-gray-500 mt-1"><span class="font-medium text-gray-600">Como saber que deu certo:</span> <?= htmlspecialchars((string) $p['aceite'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <?php if ($temProfundidade): ?>
                        <details class="mt-2 group">
                            <summary class="cursor-pointer text-xs font-semibold text-gray-700 hover:underline list-none flex items-center gap-1">
                                <i class="fa-solid fa-chevron-right text-[10px] text-gray-400 transition-transform group-open:rotate-90"></i>
                                Como fazer
                            </summary>
                            <div class="mt-3 rounded-lg border border-gray-100 bg-gray-50 p-3 space-y-3">
                                <?php if ($comoFazer !== []): ?>
                                <ol class="list-decimal list-inside space-y-1 text-xs text-gray-700">
                                    <?php foreach ($comoFazer as $passoTexto): ?>
                                    <li><?= htmlspecialchars((string) $passoTexto, ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                </ol>
                                <?php endif; ?>
                                <?php if ($printsVisiveis !== []): ?>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    <?php foreach ($printsVisiveis as $pr):
                                        $src = URL . htmlspecialchars((string) ($pr['url'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $cap = (string) ($pr['rotulo'] ?? 'Print');
                                        ?>
                                    <button type="button"
                                            class="js-implantar-print block text-left rounded-lg border border-gray-200 overflow-hidden bg-white hover:border-gray-400"
                                            data-src="<?= $src ?>"
                                            data-cap="<?= htmlspecialchars($cap, ENT_QUOTES, 'UTF-8') ?>">
                                        <img src="<?= $src ?>" alt="<?= htmlspecialchars($cap, ENT_QUOTES, 'UTF-8') ?>" class="w-full h-24 object-cover object-top">
                                        <span class="block px-2 py-1 text-[11px] font-medium text-gray-600 truncate"><?= htmlspecialchars($cap, ENT_QUOTES, 'UTF-8') ?></span>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($erros !== []): ?>
                                <div>
                                    <p class="text-xs font-semibold text-amber-800 mb-1">Erros comuns</p>
                                    <ul class="list-disc list-inside space-y-1 text-xs text-amber-800">
                                        <?php foreach ($erros as $erro): ?>
                                        <li><?= htmlspecialchars((string) $erro, ENT_QUOTES, 'UTF-8') ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($p['usado_depois_em'])): ?>
                                <p class="text-xs text-gray-500"><span class="font-medium text-gray-600">Usado depois em:</span> <?= htmlspecialchars((string) $p['usado_depois_em'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                        </details>
                        <?php elseif (!$ok && !empty($p['erro_comum'])): ?>
                        <p class="text-xs text-amber-700 mt-1"><span class="font-medium">Erro comum:</span> <?= htmlspecialchars((string) $p['erro_comum'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="<?= URL . htmlspecialchars((string) ($p['href'] ?? '/admin/academico'), ENT_QUOTES, 'UTF-8') ?>"
                   class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 shrink-0">
                    <?= $ok ? 'Abrir' : 'Configurar' ?>
                </a>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
</div>

<div id="implantar-lb" class="hidden fixed inset-0 z-50 bg-slate-900/80 p-6 overflow-auto" onclick="this.classList.add('hidden')">
    <div class="max-w-5xl mx-auto">
        <img alt="" class="w-full rounded-lg">
        <p class="text-center text-sm text-indigo-100 mt-3"></p>
    </div>
</div>
<script>
document.querySelectorAll('.js-implantar-print').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        var box = document.getElementById('implantar-lb');
        box.querySelector('img').src = btn.getAttribute('data-src');
        box.querySelector('p').textContent = btn.getAttribute('data-cap') || '';
        box.classList.remove('hidden');
    });
});
</script>
