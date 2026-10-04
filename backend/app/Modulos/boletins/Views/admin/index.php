<?php
$itens = is_array($itens ?? null) ? $itens : [];
$schemaPronto = !empty($schema_pronto);
$csrf_token = $csrf_token ?? '';
$podeDev = !empty($pode_dev);
$classeSoDev = $podeDev ? '' : ' js-so-dev';

include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-4">Modelo de Boletim</h2>
    <div class="flex items-center justify-between flex-wrap gap-3">
        <a href="<?= URL ?>/admin/academico"
           class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-arrow-left mr-2 text-gray-500"></i>
            Voltar
        </a>
        <div class="flex items-center gap-3 flex-wrap">
            <a href="<?= URL ?>/admin/boletim-configuracao/gerados"
               class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors<?= $classeSoDev ?>">
                <i class="fa-solid fa-file-lines mr-2 text-gray-500"></i>
                Notas Geradas
            </a>
            <a href="<?= URL ?>/admin/boletim"
               class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors<?= $classeSoDev ?>">
                <i class="fa-solid fa-table mr-2 text-gray-500"></i>
                Avaliações
            </a>
            <a href="<?= URL ?>/admin/reports/boletim-coordenacao?fonte=vida_escolar"
               class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-file-pdf mr-2 text-gray-500"></i>
                Relatório PDF
            </a>
            <a href="<?= URL ?>/admin/boletins/novo"
               class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90<?= $classeSoDev ?>">
                <i class="fa-solid fa-plus mr-2"></i>
                Novo modelo
            </a>
        </div>
    </div>
</div>

<?php
if (!class_exists('PeriodoLetivo')) {
    require_once __DIR__ . '/../../../../Core/PeriodoLetivo.php';
}
?>
<?php if (!$schemaPronto): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    Rode as migrations <code class="text-sm">2026_09_02_boletins.sql</code> e <code class="text-sm">2026_09_02_boletins_regra_academica.sql</code> no painel Master antes de cadastrar.
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Finalidade</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ano</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Regra de aprovação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matérias</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Eventos de notas</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($itens === []): ?>
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <p>Nenhum modelo de boletim cadastrado.</p>
                        <p class="text-sm mt-1">Crie o oficial da vida escolar e, se precisar, um extra para cursos complementares.</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($itens as $item): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                        <?= htmlspecialchars((string) ($item['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= (($item['finalidade'] ?? '') === 'complementar') ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>">
                            <?= (($item['finalidade'] ?? '') === 'complementar') ? 'Extra' : 'Oficial' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= !empty($item['ano_letivo']) ? (int) $item['ano_letivo'] : '—' ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?php $nomeRa = trim((string) ($item['regra_academica_nome'] ?? '')); ?>
                        <?php if ($nomeRa !== ''): ?>
                            <?= htmlspecialchars($nomeRa, ENT_QUOTES, 'UTF-8') ?>
                            <?php $minRa = (float) (($item['criterios']['nota_minima_aprovacao'] ?? 6)); ?>
                            <div class="text-xs text-gray-500">mín. <?= htmlspecialchars(number_format($minRa, 2, ',', ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <?php else: ?>
                            <span class="text-gray-400">Motor (fallback 6,0)</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= count($item['materias_ids'] ?? []) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= (int) ($item['eventos_notas_qtd'] ?? 0) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <?php
                        $anoModelo = (int) ($item['ano_letivo'] ?? 0);
                        $rotuloPeriodo = mb_strtolower((string) (PeriodoLetivo::doAno($anoModelo > 0 ? $anoModelo : (int) date('Y'))['rotulo_campo'] ?? 'Bimestre'), 'UTF-8');
                        $eventosVinculados = is_array($item['eventos_notas'] ?? null) ? $item['eventos_notas'] : [];
                        ob_start();
                        ?>
                        <a href="<?= URL ?>/admin/boletins/<?= (int) $item['id'] ?>/editar"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 whitespace-nowrap<?= $classeSoDev ?>">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center shrink-0"></i> Editar
                        </a>
                        <button type="button"
                                class="js-notas-eventos flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 whitespace-nowrap<?= $classeSoDev ?>"
                                data-notas-alvo="notas-eventos-<?= (int) $item['id'] ?>"
                                aria-expanded="false"
                                aria-haspopup="true">
                            <i class="fa-solid fa-file-lines text-gray-400 w-4 text-center shrink-0"></i>
                            <span class="flex-1">Notas</span>
                            <i class="js-notas-chevron fa-solid fa-chevron-left text-[10px] text-gray-400"></i>
                        </button>
                        <div id="notas-eventos-<?= (int) $item['id'] ?>"
                             class="js-notas-eventos-lista hidden w-72 max-h-72 overflow-y-auto rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 py-1"
                             style="position:fixed;z-index:60;">
                            <p class="px-3 pt-1.5 pb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Eventos</p>
                            <?php if ($eventosVinculados === []): ?>
                            <p class="px-3 py-2 text-sm text-gray-500">Nenhum evento vinculado.</p>
                            <?php else: ?>
                            <?php foreach ($eventosVinculados as $eventoNota): ?>
                            <?php $eventoNotaId = (int) ($eventoNota['id'] ?? 0); ?>
                            <?php if ($eventoNotaId <= 0) { continue; } ?>
                            <a href="<?= URL ?>/admin/boletim-configuracao/assistente?regra_id=<?= $eventoNotaId ?>&amp;boletim_id=<?= (int) $item['id'] ?>&amp;voltar=boletins"
                               class="flex items-start gap-2 mx-1 px-2 py-2 text-sm text-gray-700 rounded-md hover:bg-gray-50<?= $classeSoDev ?>"
                               title="Abrir na edição">
                                <i class="fa-solid fa-pen text-gray-400 w-4 text-center shrink-0 mt-0.5"></i>
                                <span class="break-words leading-snug"><?= htmlspecialchars((string) ($eventoNota['nome'] ?? ('Evento #' . $eventoNotaId)), ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <a href="<?= URL ?>/admin/boletins/<?= (int) $item['id'] ?>/gerar-avaliacoes"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 whitespace-nowrap<?= $classeSoDev ?>">
                            <i class="fa-solid fa-calendar-plus text-gray-400 w-4 text-center shrink-0"></i> Novo <?= htmlspecialchars($rotuloPeriodo, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <div class="border-t border-gray-100 my-1"></div>
                        <form method="POST" action="<?= URL ?>/admin/boletins/<?= (int) $item['id'] ?>/delete"
                              class="js-form-excluir-boletim<?= $podeDev ? '' : ' js-so-dev' ?>">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="senha" value="">
                            <button type="button" class="js-excluir-boletim flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 whitespace-nowrap"
                                    data-nome="<?= htmlspecialchars((string) ($item['nome'] ?? 'este modelo'), ENT_QUOTES, 'UTF-8') ?>">
                                <i class="fa-solid fa-trash-can text-red-400 w-4 text-center shrink-0"></i> Excluir
                            </button>
                        </form>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'boletim-cadastro-' . (int) $item['id'];
                        $row_actions_dropdown_menu_class = 'w-56';
                        include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if (!empty($podeDev)): ?>
<div id="modal-excluir-boletim" class="hidden fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto">
    <div class="absolute inset-0 bg-gray-600/50" data-fechar-excluir-boletim></div>
    <div class="relative top-24 mx-4 p-5 border w-full max-w-md shadow-lg rounded-xl bg-white z-10">
        <h3 class="text-lg font-medium text-gray-900 mb-2">Excluir modelo de boletim</h3>
        <p class="text-sm text-gray-600 mb-4">O modelo <strong id="nome-excluir-boletim"></strong> deixa de aparecer nesta lista. O cadastro continua no banco, com as avaliações e as notas já geradas. Digite sua senha para confirmar.</p>
        <label for="senha-excluir-boletim" class="block text-sm font-medium text-gray-700 mb-2">Sua senha</label>
        <input type="password" id="senha-excluir-boletim" autocomplete="current-password"
               class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm focus:border-red-500 focus:ring-2 focus:ring-red-100"
               placeholder="Digite sua senha">
        <p id="erro-excluir-boletim" class="mt-2 text-sm text-red-600 hidden"></p>
        <div class="flex justify-end gap-3 mt-5">
            <button type="button" data-fechar-excluir-boletim class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</button>
            <button type="button" id="confirmar-excluir-boletim" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold">Excluir</button>
        </div>
    </div>
</div>
<?php endif; ?>
<script>
(function () {
    function marcarAbertos() {
        document.querySelectorAll('.js-notas-eventos').forEach(function (btn) {
            var lista = document.getElementById(btn.getAttribute('data-notas-alvo') || '');
            var aberto = !!(lista && !lista.classList.contains('hidden'));
            btn.setAttribute('aria-expanded', aberto ? 'true' : 'false');
            btn.classList.toggle('bg-gray-50', aberto);
        });
    }

    function fecharListas() {
        document.querySelectorAll('.js-notas-eventos-lista').forEach(function (lista) {
            lista.classList.add('hidden');
        });
        marcarAbertos();
    }

    function posicionar(btn, lista) {
        if (lista.parentElement !== document.body) {
            document.body.appendChild(lista);
        }
        lista.classList.remove('hidden');
        var br = btn.getBoundingClientRect();
        var largura = lista.offsetWidth;
        var altura = lista.offsetHeight;
        var left = br.left - largura - 8;
        if (left < 8) {
            left = Math.min(br.right + 8, window.innerWidth - largura - 8);
        }
        var top = br.top;
        if (top + altura > window.innerHeight - 8) {
            top = Math.max(8, window.innerHeight - altura - 8);
        }
        lista.style.left = left + 'px';
        lista.style.top = top + 'px';
        marcarAbertos();
    }

    var modalExcluir = document.getElementById('modal-excluir-boletim');
    var formExcluirPendente = null;
    var senhaExcluir = document.getElementById('senha-excluir-boletim');
    var erroExcluir = document.getElementById('erro-excluir-boletim');
    var nomeExcluir = document.getElementById('nome-excluir-boletim');

    function fecharModalExcluir() {
        if (!modalExcluir) return;
        modalExcluir.classList.add('hidden');
        formExcluirPendente = null;
        if (senhaExcluir) senhaExcluir.value = '';
        if (erroExcluir) {
            erroExcluir.textContent = '';
            erroExcluir.classList.add('hidden');
        }
    }

    function abrirModalExcluir(btn) {
        var form = btn.closest('form');
        if (!form || !modalExcluir) return;
        formExcluirPendente = form;
        if (nomeExcluir) nomeExcluir.textContent = btn.getAttribute('data-nome') || 'este modelo';
        if (senhaExcluir) senhaExcluir.value = '';
        if (erroExcluir) erroExcluir.classList.add('hidden');
        modalExcluir.classList.remove('hidden');
        fecharListas();
        if (senhaExcluir) senhaExcluir.focus();
    }

    document.addEventListener('click', function (e) {
        var bloqueado = e.target.closest('.js-so-dev');
        if (bloqueado) {
            e.preventDefault();
            e.stopPropagation();
            window.alert('Você não tem permissão. Contate o Administrador.');
            return;
        }
        var excluirBtn = e.target.closest('.js-excluir-boletim');
        if (excluirBtn) {
            e.preventDefault();
            e.stopPropagation();
            abrirModalExcluir(excluirBtn);
            return;
        }
        if (e.target.closest('[data-fechar-excluir-boletim]')) {
            e.preventDefault();
            fecharModalExcluir();
            return;
        }
        var btn = e.target.closest('.js-notas-eventos');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            var lista = document.getElementById(btn.getAttribute('data-notas-alvo') || '');
            if (!lista) return;
            var vaiAbrir = lista.classList.contains('hidden');
            fecharListas();
            if (vaiAbrir) posicionar(btn, lista);
            return;
        }
        if (!e.target.closest('.js-notas-eventos-lista')) {
            fecharListas();
        }
    });

    window.addEventListener('scroll', function (e) {
        var alvo = e.target;
        if (alvo && alvo.nodeType === 1 && alvo.closest && alvo.closest('.js-notas-eventos-lista')) return;
        fecharListas();
    }, true);
    window.addEventListener('resize', fecharListas);

    var confirmarExcluir = document.getElementById('confirmar-excluir-boletim');
    if (confirmarExcluir) {
        confirmarExcluir.addEventListener('click', function () {
            if (!formExcluirPendente) return;
            var senha = senhaExcluir ? String(senhaExcluir.value || '') : '';
            if (senha.trim() === '') {
                if (erroExcluir) {
                    erroExcluir.textContent = 'Digite sua senha para confirmar.';
                    erroExcluir.classList.remove('hidden');
                }
                if (senhaExcluir) senhaExcluir.focus();
                return;
            }
            var campo = formExcluirPendente.querySelector('input[name="senha"]');
            if (campo) campo.value = senha;
            formExcluirPendente.submit();
        });
    }
    if (senhaExcluir) {
        senhaExcluir.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                if (confirmarExcluir) confirmarExcluir.click();
            }
            if (ev.key === 'Escape') fecharModalExcluir();
        });
    }
})();
</script>
