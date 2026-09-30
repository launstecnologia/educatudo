<?php
$boletim = is_array($boletim ?? null) ? $boletim : [];
$periodos = is_array($periodos ?? null) ? $periodos : [];
$eventosModelo = is_array($eventos_modelo ?? null) ? $eventos_modelo : [];
$modelosPorBimestre = is_array($modelos_por_bimestre ?? null) ? $modelos_por_bimestre : [];
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$usouCalendario = !empty($usou_calendario);
$csrf_token = $csrf_token ?? '';
$boletimId = (int) ($boletim['id'] ?? 0);
$temPeriodo = $periodos !== [];

$page_header_title = 'Gerar avaliações do ano';
$page_header_subtitle = 'Cria os bimestres a partir do calendário letivo (tipo Avaliação) ou de 4 períodos padrão, clipados no ano letivo.';
$page_header_back_url = URL . '/admin/boletins';
include __DIR__ . '/../../../../Views/admin/_partials/page_header_form.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<div class="bg-white rounded-xl shadow-lg p-6 w-full">
    <div class="mb-6">
        <p class="text-sm text-gray-700">
            Modelo: <strong><?= htmlspecialchars((string) ($boletim['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
            · Ano <?= $anoLetivo ?>
            · <?= $usouCalendario ? 'Datas do calendário letivo' : 'Períodos padrão (4 bimestres)' ?>
        </p>
        <p class="text-xs text-gray-500 mt-1">
            Marque os bimestres a criar ou substituir. Em cada um, escolha o evento vigente a copiar.
            Ao substituir, o evento antigo é desativado e o histórico de notas deixa de ficar vigente.
        </p>
    </div>

    <?php if ($eventosModelo === []): ?>
        <div class="p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm mb-6">
            Não há evento vigente de notas para usar como modelo. Gere um em
            <a href="<?= URL ?>/admin/boletim-configuracao" class="underline">Notas da Coordenação</a>
            ou cadastre a primeira avaliação em
            <a href="<?= URL ?>/admin/boletim-configuracao?novo=1" class="underline">Avaliações</a>.
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= URL ?>/admin/boletins/<?= $boletimId ?>/gerar-avaliacoes" class="space-y-4" id="form-gerar-avaliacoes">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">

        <div class="overflow-x-auto mb-2">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-16">Gerar</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Período</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Início</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fim</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Origem</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Evento modelo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($periodos === []): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">Nenhum período calculado.</td>
                    </tr>
                    <?php else: foreach ($periodos as $p):
                        $bim = (int) ($p['bimestre'] ?? 0);
                        $jaExiste = !empty($p['ja_existe']);
                        $modeloSel = (int) ($modelosPorBimestre[$bim] ?? 0);
                        $rowId = 'bim-gerar-' . $bim;
                        $checkedPadrao = !$jaExiste;
                    ?>
                    <tr class="<?= $jaExiste ? 'bg-gray-50/40' : '' ?>" data-ja-existe="<?= $jaExiste ? '1' : '0' ?>">
                        <td class="px-4 py-3">
                            <input
                                type="checkbox"
                                name="bimestres[]"
                                value="<?= $bim ?>"
                                id="<?= htmlspecialchars($rowId, ENT_QUOTES, 'UTF-8') ?>"
                                class="js-bim-gerar rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                data-existe="<?= $jaExiste ? '1' : '0' ?>"
                                <?= $checkedPadrao ? 'checked' : '' ?>
                                <?= $eventosModelo === [] ? 'disabled' : '' ?>
                            >
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                            <?php if ($eventosModelo !== []): ?>
                                <label for="<?= htmlspecialchars($rowId, ENT_QUOTES, 'UTF-8') ?>" class="cursor-pointer">
                                    <?= htmlspecialchars((string) ($p['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </label>
                            <?php else: ?>
                                <?= htmlspecialchars((string) ($p['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) ($p['inicio'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) ($p['fim'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= (($p['origem'] ?? '') === 'calendario') ? 'Calendário' : 'Padrão' ?></td>
                        <td class="px-4 py-3">
                            <?php if ($jaExiste): ?>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 js-status-existe">Já existe</span>
                                <span class="hidden inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-900 js-status-substituir">Será substituído</span>
                            <?php else: ?>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 js-status-criar">Será criado</span>
                                <span class="hidden inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-800 border border-amber-200 js-status-pular">Não gerar agora</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 min-w-[18rem]">
                            <?php if ($eventosModelo === []): ?>
                                <span class="text-xs text-amber-700">Sem modelo disponível</span>
                            <?php else: ?>
                                <select
                                    name="modelo_por_bimestre[<?= $bim ?>]"
                                    class="js-modelo-select w-full max-w-xl px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm"
                                    data-bim="<?= $bim ?>"
                                    <?= $checkedPadrao ? 'required' : 'disabled' ?>
                                >
                                    <?php foreach ($eventosModelo as $ev):
                                        $evId = (int) ($ev['id'] ?? 0);
                                        $label = (string) ($ev['nome_exibicao'] ?? $ev['nome'] ?? ('#' . $evId));
                                    ?>
                                        <option value="<?= $evId ?>" <?= $modeloSel === $evId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($jaExiste): ?>
                                    <p class="text-[11px] text-gray-400 mt-1 js-hint-substituir <?= $checkedPadrao ? '' : 'hidden' ?>">O evento atual deste bimestre será desativado.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex items-center gap-3">
            <button
                type="submit"
                id="btn-gerar-avaliacoes"
                class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90"
                <?= ($eventosModelo === [] || !$temPeriodo) ? 'disabled' : '' ?>
            >
                Gerar selecionados
            </button>
            <a href="<?= URL ?>/admin/boletins" class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">Cancelar</a>
        </div>
    </form>
</div>
<?php if ($temPeriodo && $eventosModelo !== []): ?>
<script>
(function () {
    var form = document.getElementById('form-gerar-avaliacoes');
    if (!form) return;
    var btn = document.getElementById('btn-gerar-avaliacoes');

    function syncRow(cb) {
        var tr = cb.closest('tr');
        if (!tr) return;
        var on = !!cb.checked;
        var existe = cb.getAttribute('data-existe') === '1';
        var criar = tr.querySelector('.js-status-criar');
        var pular = tr.querySelector('.js-status-pular');
        var existeEl = tr.querySelector('.js-status-existe');
        var subst = tr.querySelector('.js-status-substituir');
        var hint = tr.querySelector('.js-hint-substituir');
        var sel = tr.querySelector('.js-modelo-select');
        if (existe) {
            if (existeEl) existeEl.classList.toggle('hidden', on);
            if (subst) subst.classList.toggle('hidden', !on);
            if (hint) hint.classList.toggle('hidden', !on);
        } else {
            if (criar) criar.classList.toggle('hidden', !on);
            if (pular) pular.classList.toggle('hidden', on);
        }
        if (sel) {
            sel.disabled = !on;
            sel.required = on;
        }
    }

    function syncAll() {
        var checks = form.querySelectorAll('.js-bim-gerar');
        var any = false;
        checks.forEach(function (cb) {
            syncRow(cb);
            if (cb.checked) any = true;
        });
        if (btn) btn.disabled = !any;
    }

    form.querySelectorAll('.js-bim-gerar').forEach(function (cb) {
        cb.addEventListener('change', function () {
            if (cb.checked && cb.getAttribute('data-existe') === '1') {
                var ok = window.confirm('Substituir o evento atual deste bimestre? O antigo será desativado e deixa de ser vigente.');
                if (!ok) {
                    cb.checked = false;
                }
            }
            syncAll();
        });
    });

    form.addEventListener('submit', function (ev) {
        var subst = form.querySelectorAll('.js-bim-gerar[data-existe="1"]:checked');
        if (subst.length > 0) {
            var ok = window.confirm('Há ' + subst.length + ' bimestre(s) marcados para substituição. Confirma?');
            if (!ok) {
                ev.preventDefault();
            }
        }
    });

    syncAll();
})();
</script>
<?php endif; ?>
