<?php
$diag = is_array($diag ?? null) ? $diag : ['passos' => [], 'grupos' => [], 'completos' => 0, 'total' => 0, 'percentual' => 0, 'ano' => (int) date('Y'), 'proximo' => null];
$grupos = is_array($diag['grupos'] ?? null) ? $diag['grupos'] : [];
$percentual = (int) ($diag['percentual'] ?? 0);
$completos = (int) ($diag['completos'] ?? 0);
$total = (int) ($diag['total'] ?? 0);
$ano = (int) ($diag['ano'] ?? date('Y'));
$proximo = is_array($diag['proximo'] ?? null) ? $diag['proximo'] : null;

$implantarPublico = !empty($implantar_publico);
if (!$implantarPublico) {
    $page_header_title = 'Implantar acadêmico';
    $page_header_subtitle = 'Monte o ano letivo na ordem de dependência. O progresso é lido do que a escola já cadastrou.';
    $page_header_back_url = URL . '/admin/academico';
    include __DIR__ . '/../../../../Views/admin/_partials/page_header_form.php';
    include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
}
?>

<div class="bg-primary text-primary rounded-xl shadow-lg p-6 w-full mb-6">
    <p class="text-xs font-semibold uppercase tracking-widest opacity-80">Acadêmico · Implantação</p>
    <h3 class="text-2xl font-bold mt-2 leading-tight">Vamos montar o ano letivo da sua escola.</h3>
    <p class="mt-3 text-sm opacity-90 max-w-3xl leading-relaxed">
        Este é o módulo onde a escola se descreve: <strong>quem estuda, quem ensina, quando acontece cada aula e como as notas viram boletim.</strong>
        Você preenche uma vez — e o sistema usa isso o ano inteiro.
    </p>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-1">1 · Estrutura</p>
        <p class="text-sm font-semibold text-gray-900">O esqueleto do ano</p>
        <p class="text-sm text-gray-600 mt-1">O ano letivo, os cursos, as séries, as disciplinas e o calendário.</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-1">2 · Pessoas e turmas</p>
        <p class="text-sm font-semibold text-gray-900">Quem está onde</p>
        <p class="text-sm text-gray-600 mt-1">Professores, salas, turmas, alunos e a grade de horários.</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-1">3 · Como a escola avalia</p>
        <p class="text-sm font-semibold text-gray-900">O boletim do seu jeito</p>
        <p class="text-sm text-gray-600 mt-1">Regras de aprovação, tipos de nota, quadro e o modelo de boletim.</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <h3 class="text-sm font-semibold text-gray-900 mb-3">O que esperar daqui</h3>
    <ul class="space-y-2 text-sm text-gray-700">
        <li class="flex gap-2"><i class="fa-solid fa-check text-green-600 mt-0.5 shrink-0"></i><span><strong>São <?= $total ?> passos, uma vez por ano.</strong> Depois disso, só volta aqui no ano que vem.</span></li>
        <li class="flex gap-2"><i class="fa-solid fa-check text-green-600 mt-0.5 shrink-0"></i><span><strong>A ordem já está pronta.</strong> Cada passo prepara o próximo.</span></li>
        <li class="flex gap-2"><i class="fa-solid fa-check text-green-600 mt-0.5 shrink-0"></i><span><strong>Cada passo diz como saber que deu certo.</strong> Você não precisa marcar nada — o progresso sai do cadastro.</span></li>
        <li class="flex gap-2"><i class="fa-solid fa-check text-green-600 mt-0.5 shrink-0"></i><span><strong>Nesta trilha nada é irreversível.</strong> Homologar o período, depois, é o ponto sem volta — e fica fora daqui.</span></li>
    </ul>
</div>

<div class="rounded-xl border border-green-200 bg-green-50 p-4 mb-6 text-sm text-green-900">
    <strong>No fim destes passos:</strong> o boletim sai com a cara da escola, o diário funciona e a frequência tem onde se apoiar. Lançar notas e fechar o período são o dia a dia — entram nas próximas partes desta trilha.
</div>

<div class="bg-white rounded-xl shadow-lg p-6 w-full mb-6">
    <?php if ($implantarPublico): ?>
    <p class="text-sm text-gray-700">
        <strong class="text-gray-900">Guia dos <?= $total ?> passos.</strong>
        O progresso da escola só aparece no painel, depois do login.
        Comece pelo Ano Letivo.
    </p>
    <?php else: ?>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <div>
            <p class="text-sm font-medium text-gray-900">Ano <?= $ano ?></p>
            <p class="text-sm text-gray-600"><?= $completos ?> de <?= $total ?> passos prontos</p>
        </div>
        <span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold <?= $percentual === 100 ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' ?>">
            <?= $percentual ?>%
        </span>
    </div>
    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
        <div class="h-2 bg-primary rounded-full" style="width: <?= max(0, min(100, $percentual)) ?>%"></div>
    </div>
    <?php if ($proximo !== null): ?>
    <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p class="text-sm text-gray-700">
            <strong class="text-gray-900">Próximo passo:</strong>
            <?= htmlspecialchars((string) ($proximo['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>.
            <?= htmlspecialchars((string) ($proximo['detalhe'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <a href="<?= URL . htmlspecialchars((string) ($proximo['href'] ?? '/admin/academico'), ENT_QUOTES, 'UTF-8') ?>"
           class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 shrink-0">
            Começar
        </a>
    </div>
    <?php else: ?>
    <p class="mt-4 text-sm text-green-800 bg-green-50 border border-green-200 rounded-lg px-4 py-3">
        Os <?= $total ?> passos desta fase estão prontos. O ano já pode rodar: lançar notas, diário e fechamento.
    </p>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php foreach ($grupos as $grupo):
    $passosGrupo = is_array($grupo['passos'] ?? null) ? $grupo['passos'] : [];
    if ($passosGrupo === []) {
        continue;
    }
    ?>
<p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 mt-8 mb-2 px-1">
    <?= htmlspecialchars((string) ($grupo['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
</p>
<div class="space-y-2 mb-2">
    <?php foreach ($passosGrupo as $p):
        $ok = !empty($p['ok']);
        $atual = !empty($p['atual']);
        $comoFazer = is_array($p['como_fazer'] ?? null) ? $p['como_fazer'] : [];
        $erros = is_array($p['erros_comuns'] ?? null) ? $p['erros_comuns'] : [];
        $prints = is_array($p['prints'] ?? null) ? $p['prints'] : [];
        $printsVisiveis = array_values(array_filter($prints, static fn ($pr) => !empty($pr['existe'])));
        $nota = trim((string) ($p['nota'] ?? ''));
        $temProfundidade = $comoFazer !== [] || $erros !== [] || $printsVisiveis !== [] || !empty($p['usado_depois_em']) || $nota !== '';
        $borda = $ok
            ? 'border-green-200'
            : ($atual ? 'border-gray-400 shadow-sm' : 'border-gray-200');
        ?>
    <article class="bg-white rounded-xl border <?= $borda ?> overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-start gap-3 p-4">
            <span class="mt-0.5 inline-flex w-6 h-6 items-center justify-center rounded-full text-xs font-bold shrink-0 <?= $ok ? 'bg-green-100 text-green-700' : ($atual ? 'bg-primary text-primary' : 'bg-gray-100 text-gray-500') ?>">
                <?= $ok ? '✓' : ($atual ? '→' : '·') ?>
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars((string) ($p['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($atual): ?>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-primary text-primary">Comece por aqui</span>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-gray-600 mt-0.5"><?= htmlspecialchars((string) ($p['detalhe'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!empty($p['aceite'])): ?>
                <p class="text-xs text-gray-500 mt-1"><span class="font-medium text-gray-600">Como saber que deu certo:</span> <?= htmlspecialchars((string) $p['aceite'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($temProfundidade): ?>
                <details class="mt-2 group" <?= $atual ? 'open' : '' ?>>
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
                        <?php if ($nota !== ''): ?>
                        <p class="text-xs text-gray-700 bg-white border-l-4 border-gray-300 rounded-r-md px-3 py-2"><?= htmlspecialchars($nota, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                </details>
                <?php endif; ?>
            </div>
            <a href="<?= URL . htmlspecialchars((string) ($p['href'] ?? '/admin/academico'), ENT_QUOTES, 'UTF-8') ?>"
               class="<?= $atual && !$ok
                   ? 'btn-primary-custom'
                   : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' ?> inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-medium shrink-0">
                <?= $ok ? 'Abrir' : 'Configurar' ?>
            </a>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

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
        e.stopPropagation();
        var box = document.getElementById('implantar-lb');
        box.querySelector('img').src = btn.getAttribute('data-src');
        box.querySelector('p').textContent = btn.getAttribute('data-cap') || '';
        box.classList.remove('hidden');
    });
});
</script>
