<?php
$itens = is_array($itens ?? null) ? $itens : [];
$schemaPronto = !empty($schema_pronto);
$csrf_token = (string) ($csrf_token ?? '');
$podeCadastrar = !empty($pode_cadastrar);
$podeAlterar = !empty($pode_alterar);
$podeExcluir = !empty($pode_excluir);
$ano = (int) ($ano ?? date('Y'));
$periodoNum = (int) ($periodo_num ?? 0);
$periodoRotulo = (string) ($periodo_rotulo ?? '');
$regraId = (int) ($regra_id ?? 0);
$podeDuplicar = !empty($pode_duplicar);
$rotuloAnterior = trim((string) ($rotulo_anterior ?? ''));
$tipoRotulo = trim((string) ($tipo_rotulo ?? 'Avaliação'));
$opcoesPeriodo = is_array($periodos_opcoes ?? null) ? $periodos_opcoes : [];
$urlPeriodos = URL . '/admin/eventos-avaliativos?ano=' . $ano . ($regraId > 0 ? '&regra=' . $regraId : '');
$urlCriar = URL . '/admin/eventos-avaliativos/criar?ano=' . $ano . '&periodo=' . $periodoNum . ($regraId > 0 ? '&regra=' . $regraId : '');
$urlLista = URL . '/admin/eventos-avaliativos';

$totalEventos = count($itens);
$totalSubeventos = 0;
$turmasUnicas = [];
foreach ($itens as $rowContagem) {
    $subsContagem = is_array($rowContagem['subeventos'] ?? null) ? $rowContagem['subeventos'] : [];
    $totalSubeventos += $subsContagem !== [] ? count($subsContagem) : (int) ($rowContagem['total_subeventos'] ?? 0);
    $nomesTurma = trim((string) ($rowContagem['turmas_nomes'] ?? ''));
    if ($nomesTurma === '') {
        continue;
    }
    foreach (explode(',', $nomesTurma) as $nomeTurma) {
        $nomeTurma = trim($nomeTurma);
        if ($nomeTurma !== '') {
            $turmasUnicas[$nomeTurma] = true;
        }
    }
}
$totalTurmas = count($turmasUnicas);

$page_header_title = 'Eventos Avaliativos';
$page_header_subtitle = 'Gerencie os eventos avaliativos do período letivo.';
$page_header_actions = '<a href="' . htmlspecialchars($urlPeriodos, ENT_QUOTES, 'UTF-8') . '" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50"><i class="fa-solid fa-arrow-left mr-2"></i>Períodos</a>';
include \App\Core\ViewPaths::adminPartial('page_header_list.php');
include \App\Core\ViewPaths::adminPartial('flash_message.php');
?>

<?php if (!$schemaPronto): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    Rode a migration <code class="text-sm">2026_10_26_eventos_avaliativos.sql</code> no painel Master antes de cadastrar.
</div>
<?php endif; ?>

<div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center">
    <form method="GET" action="<?= $urlLista ?>" class="relative min-w-[16rem]">
        <input type="hidden" name="ano" value="<?= $ano ?>">
        <?php if ($regraId > 0): ?>
        <input type="hidden" name="regra" value="<?= $regraId ?>">
        <?php endif; ?>
        <i class="fa-regular fa-calendar pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true"></i>
        <select name="periodo" onchange="this.form.submit()" aria-label="Período"
                class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-3 text-sm font-medium text-gray-800">
            <?php foreach ($opcoesPeriodo as $opcao): ?>
                <?php $numeroOpcao = (int) ($opcao['numero'] ?? 0); ?>
                <option value="<?= $numeroOpcao ?>" <?= $numeroOpcao === $periodoNum ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) ($opcao['rotulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?> - <?= $ano ?>
                </option>
            <?php endforeach; ?>
            <?php if ($opcoesPeriodo === []): ?>
                <option value="<?= $periodoNum ?>" selected><?= htmlspecialchars($periodoRotulo, ENT_QUOTES, 'UTF-8') ?> - <?= $ano ?></option>
            <?php endif; ?>
        </select>
    </form>
    <div class="relative min-w-0 flex-1">
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true"></i>
        <input type="search" id="eventos-busca" placeholder="Buscar por nome, referência ou sigla..."
               class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-3 text-sm text-gray-800 placeholder:text-gray-400">
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <?php if ($podeCadastrar && $schemaPronto && $podeDuplicar): ?>
        <form method="POST" action="<?= URL ?>/admin/eventos-avaliativos/duplicar">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="ano" value="<?= $ano ?>">
            <input type="hidden" name="periodo" value="<?= $periodoNum ?>">
            <input type="hidden" name="regra" value="<?= $regraId ?>">
            <button type="submit" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <i class="fa-solid fa-copy mr-2 text-gray-500" aria-hidden="true"></i>
                Duplicar de <?= htmlspecialchars($rotuloAnterior !== '' ? $rotuloAnterior : 'o período anterior', ENT_QUOTES, 'UTF-8') ?>
            </button>
        </form>
        <?php endif; ?>
        <?php if ($podeCadastrar && $schemaPronto): ?>
        <a href="<?= $urlCriar ?>"
           class="btn-primary-custom inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm hover:opacity-90">
            <i class="fa-solid fa-plus mr-2"></i>
            Novo evento
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <?php
    $cartoes = [
        ['icone' => 'fa-clipboard-list', 'valor' => $totalEventos, 'legenda' => 'Eventos cadastrados'],
        ['icone' => 'fa-layer-group', 'valor' => $totalSubeventos, 'legenda' => 'Subeventos'],
        ['icone' => 'fa-user-group', 'valor' => $totalTurmas, 'legenda' => 'Turmas vinculadas'],
    ];
    foreach ($cartoes as $cartao):
    ?>
    <div class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
            <i class="fa-solid <?= htmlspecialchars($cartao['icone'], ENT_QUOTES, 'UTF-8') ?> text-lg" aria-hidden="true"></i>
        </span>
        <div>
            <p class="text-2xl font-semibold leading-none text-gray-900"><?= (int) $cartao['valor'] ?></p>
            <p class="mt-1 text-sm text-gray-500"><?= htmlspecialchars($cartao['legenda'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="mb-3 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h3 class="text-base font-semibold text-gray-900">Lista de Eventos</h3>
        <p id="eventos-contagem" class="text-sm text-gray-500"><?= $totalEventos === 1 ? '1 resultado encontrado.' : $totalEventos . ' resultados encontrados.' ?></p>
    </div>
    <?php if ($itens !== []): ?>
    <div class="flex items-center gap-2">
        <button type="button" id="eventos-expandir" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            <i class="fa-solid fa-chevron-down mr-2 text-xs text-gray-400" aria-hidden="true"></i>
            Expandir todos
        </button>
        <button type="button" id="eventos-recolher" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            <i class="fa-solid fa-chevron-up mr-2 text-xs text-gray-400" aria-hidden="true"></i>
            Recolher todos
        </button>
    </div>
    <?php endif; ?>
</div>

<?php if ($itens === []): ?>
<div class="rounded-xl border border-gray-200 bg-white px-6 py-12 text-center text-gray-500 shadow-sm">
    <i class="fa-solid fa-clipboard-list mb-4 text-4xl text-gray-300" aria-hidden="true"></i>
    <p class="font-medium text-gray-700">Nenhum evento avaliativo</p>
</div>
<?php else: ?>
<div id="eventos-lista" class="space-y-4">
    <?php foreach ($itens as $indiceEvento => $row): ?>
    <?php
        $eventoId = (int) ($row['id'] ?? 0);
        $subeventos = is_array($row['subeventos'] ?? null) ? $row['subeventos'] : [];
        $totalSub = count($subeventos) > 0 ? count($subeventos) : (int) ($row['total_subeventos'] ?? 0);
        $turmasNomes = trim((string) ($row['turmas_nomes'] ?? ''));
        $urlEditar = URL . '/admin/eventos-avaliativos/' . $eventoId . '/editar' . ($regraId > 0 ? '?regra=' . $regraId : '');
        $rascunho = !empty($row['rascunho']);
        $siglaEvento = strtoupper(trim((string) ($row['sigla'] ?? '')));
        $nomeEvento = (string) ($row['nome'] ?? '');
        $tom = $indiceEvento % 2 === 0
            ? ['caixa' => 'bg-sky-50 text-sky-600', 'selo' => 'bg-sky-50 text-sky-700']
            : ['caixa' => 'bg-violet-50 text-violet-600', 'selo' => 'bg-violet-50 text-violet-700'];
        $busca = mb_strtolower($eventoId . ' ' . $nomeEvento . ' ' . $siglaEvento);
        foreach ($subeventos as $subBusca) {
            $busca .= ' ' . mb_strtolower(trim((string) ($subBusca['titulo'] ?? '')));
        }
        $rotuloSub = $totalSub === 1 ? '1 Subevento' : $totalSub . ' Subeventos';
    ?>
    <article data-evento-card data-busca="<?= htmlspecialchars($busca, ENT_QUOTES, 'UTF-8') ?>"
             class="overflow-hidden rounded-xl border bg-white shadow-sm <?= $rascunho ? 'border-amber-300' : 'border-gray-200' ?>">
        <div class="flex flex-wrap items-center gap-4 px-4 py-4 sm:px-5">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl <?= $tom['caixa'] ?>">
                <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Ref #<?= $eventoId ?></p>
                <div class="mt-0.5 flex flex-wrap items-center gap-2">
                    <span class="text-base font-semibold text-gray-900"><?= htmlspecialchars($nomeEvento) ?></span>
                    <?php if ($siglaEvento !== ''): ?>
                    <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-semibold <?= $tom['selo'] ?>"><?= htmlspecialchars($siglaEvento) ?></span>
                    <?php endif; ?>
                    <?php if ($rascunho): ?>
                    <span class="inline-flex items-center rounded-md bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-800">Rascunho</span>
                    <?php endif; ?>
                </div>
                <p class="mt-0.5 text-sm text-gray-500">Tipo: <?= htmlspecialchars($tipoRotulo) ?></p>
            </div>
            <div class="min-w-[10rem] text-sm text-gray-600">
                <p class="text-xs text-gray-400"><i class="fa-solid fa-user-group mr-1" aria-hidden="true"></i>Turmas</p>
                <p class="mt-0.5"><?= $turmasNomes !== '' ? htmlspecialchars($turmasNomes) : '—' ?></p>
            </div>
            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700"><?= htmlspecialchars($rotuloSub) ?></span>
            <button type="button" data-evento-toggle class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-50" aria-expanded="true" aria-label="Mostrar subeventos">
                <i class="fa-solid fa-chevron-down text-sm" data-evento-seta aria-hidden="true"></i>
            </button>
            <?php ob_start(); ?>
            <?php if ($podeAlterar): ?>
            <a href="<?= $urlEditar ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                <i class="fa-solid fa-pen w-4 text-center text-gray-400"></i> Editar
            </a>
            <?php endif; ?>
            <?php if ($podeAlterar && $rascunho): ?>
            <form method="POST" action="<?= URL ?>/admin/eventos-avaliativos/<?= $eventoId ?>/publicar">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="regra" value="<?= $regraId ?>">
                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-check w-4 text-center text-gray-400"></i> Publicar
                </button>
            </form>
            <?php endif; ?>
            <?php if ($podeExcluir): ?>
            <form method="POST" action="<?= URL ?>/admin/eventos-avaliativos/<?= $eventoId ?>/excluir" onsubmit="return confirm('Excluir este evento avaliativo?');">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                    <i class="fa-solid fa-trash-can w-4 text-center text-red-400"></i> Excluir
                </button>
            </form>
            <?php endif; ?>
            <?php $row_actions_dropdown_items = ob_get_clean(); ?>
            <?php if (trim($row_actions_dropdown_items) !== ''): ?>
                <?php $row_actions_dropdown_id = 'row-actions-evento-' . $eventoId; ?>
                <?php include \App\Core\ViewPaths::adminPartial('row_actions_dropdown.php'); ?>
            <?php endif; ?>
        </div>

        <div data-evento-corpo class="border-t border-gray-100 px-4 py-4 sm:px-5">
            <div class="mb-3">
                <p class="text-sm font-semibold text-gray-800">Subeventos (<?= $totalSub ?>)</p>
            </div>
            <?php if ($subeventos === []): ?>
            <p class="text-sm text-gray-500">Nenhum subevento neste evento.</p>
            <?php else: ?>
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Ref</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nome do subevento</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Data</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Turmas</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <?php foreach ($subeventos as $indiceSub => $sub): ?>
                        <?php
                            $blocoId = (int) ($sub['id'] ?? 0);
                            if ($blocoId <= 0) {
                                continue;
                            }
                            $online = (string) ($sub['formato_evento'] ?? '') === 'online_questoes';
                            $urlNota = URL . '/admin/provas/blocos/' . $blocoId . '/' . ($online ? 'gerenciar' : 'lancar-notas-coordenacao');
                            $tituloSub = trim((string) ($sub['titulo'] ?? ''));
                            if ($tituloSub === '') {
                                $tituloSub = 'Subevento #' . $blocoId;
                            }
                            $dataSub = (string) ($sub['data_prova'] ?? '');
                            $tsSub = $dataSub !== '' ? strtotime($dataSub) : false;
                            $dataSub = ($tsSub !== false && $tsSub > 0) ? date('d/m/Y', $tsSub) : '';
                            $turmasSub = trim((string) ($sub['turmas_nomes'] ?? ''));
                            if ($turmasSub === '') {
                                $turmasSub = $turmasNomes;
                            }
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500"><?= $eventoId ?>.<?= $indiceSub + 1 ?></td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($tituloSub) ?></td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">
                                <?php if ($dataSub !== ''): ?>
                                <i class="fa-regular fa-calendar mr-1 text-gray-400" aria-hidden="true"></i><?= htmlspecialchars($dataSub) ?>
                                <?php else: ?>
                                —
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700"><?= $turmasSub !== '' ? htmlspecialchars($turmasSub) : '—' ?></td>
                            <td class="px-4 py-3 text-right">
                                <?php ob_start(); ?>
                                <a href="<?= $urlNota ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-pen-to-square w-4 text-center text-gray-400"></i> <?= $online ? 'Gerenciar prova' : 'Lançar notas' ?>
                                </a>
                                <a href="<?= URL ?>/admin/provas/blocos/<?= $blocoId ?>/resultados" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-chart-column w-4 text-center text-gray-400"></i> Ver resultado
                                </a>
                                <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                                <?php $row_actions_dropdown_id = 'row-actions-sub-' . $blocoId; ?>
                                <?php include \App\Core\ViewPaths::adminPartial('row_actions_dropdown.php'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<p id="eventos-vazio-busca" class="hidden rounded-xl border border-gray-200 bg-white px-6 py-10 text-center text-sm text-gray-500">Nenhum evento com esse nome, referência ou sigla.</p>
<?php endif; ?>

<script>
(function () {
    var campo = document.getElementById('eventos-busca');
    var lista = document.getElementById('eventos-lista');
    var contagem = document.getElementById('eventos-contagem');
    var vazio = document.getElementById('eventos-vazio-busca');
    if (!lista) return;

    function cards() {
        return Array.prototype.slice.call(lista.querySelectorAll('[data-evento-card]'));
    }

    function definirAbertos(aberto) {
        cards().forEach(function (card) {
            var corpo = card.querySelector('[data-evento-corpo]');
            var botao = card.querySelector('[data-evento-toggle]');
            var seta = card.querySelector('[data-evento-seta]');
            if (corpo) corpo.classList.toggle('hidden', !aberto);
            if (botao) botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
            if (seta) seta.classList.toggle('rotate-180', !aberto);
        });
    }

    lista.addEventListener('click', function (evento) {
        var botao = evento.target.closest('[data-evento-toggle]');
        if (!botao || !lista.contains(botao)) return;
        var card = botao.closest('[data-evento-card]');
        var corpo = card ? card.querySelector('[data-evento-corpo]') : null;
        if (!corpo) return;
        var abrir = corpo.classList.contains('hidden');
        corpo.classList.toggle('hidden', !abrir);
        botao.setAttribute('aria-expanded', abrir ? 'true' : 'false');
        var seta = botao.querySelector('[data-evento-seta]');
        if (seta) seta.classList.toggle('rotate-180', !abrir);
    });

    var expandir = document.getElementById('eventos-expandir');
    var recolher = document.getElementById('eventos-recolher');
    if (expandir) expandir.addEventListener('click', function () { definirAbertos(true); });
    if (recolher) recolher.addEventListener('click', function () { definirAbertos(false); });

    if (!campo) return;
    campo.addEventListener('input', function () {
        var termo = campo.value.trim().toLowerCase();
        var visiveis = 0;
        cards().forEach(function (card) {
            var texto = (card.getAttribute('data-busca') || '').toLowerCase();
            var ok = termo === '' || texto.indexOf(termo) !== -1;
            card.classList.toggle('hidden', !ok);
            if (ok) visiveis += 1;
        });
        if (contagem) {
            contagem.textContent = visiveis === 1 ? '1 resultado encontrado.' : visiveis + ' resultados encontrados.';
        }
        if (vazio) vazio.classList.toggle('hidden', visiveis > 0);
    });
})();
</script>
