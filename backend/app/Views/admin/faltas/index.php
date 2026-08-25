<?php
$eventos = $eventos ?? [];
$turmas = $turmas ?? [];
$turmasById = $turmas_by_id ?? [];
$csrfToken = (string) ($csrf_token ?? '');
$flashMessage = (string) ($flash_message ?? '');
$flashType = (string) ($flash_type ?? 'success');
$materiasCatalogo = $materias_catalogo ?? [];
$urlCriarFaltas = URL . '/admin/faltas/criar';
$urlExportarExcel = URL . '/admin/faltas/exportar-excel';
$urlAtualizarFaltas = URL . '/admin/faltas/atualizar';
$urlLancarFaltas = URL . '/admin/faltas/lancar';
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Só Faltas</h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= htmlspecialchars($urlExportarExcel, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 shadow-sm" title="Exportar os eventos de faltas para Excel" aria-label="Exportar faltas em Excel">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v6m0 0l-3-3m3 3l3-3M4 7h16M5 7l1-2h12l1 2"/></svg>
                <span class="hidden sm:inline">Exportar Excel</span>
            </a>
            <button type="button" id="btn-abrir-modal-novo-evento-faltas" class="btn-primary-custom shrink-0 inline-flex items-center justify-center p-2.5 rounded-xl hover:opacity-90 shadow-sm" title="Novo evento de faltas — bimestre, turmas e matérias" aria-label="Novo evento de faltas">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
        </div>
    </div>

    <?php if ($flashMessage !== ''): ?>
        <?php $bg = $flashType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?>
        <div class="p-4 rounded-lg border <?= $bg ?>"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Lançamento de faltas</h2>
        </div>
        <?php if ($eventos === []): ?>
            <p class="text-sm text-gray-500">Nenhum evento cadastrado. Use o botão <strong class="text-gray-700">+</strong> no canto superior direito.</p>
        <?php else: ?>
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Evento</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide whitespace-nowrap">Bimestre</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide whitespace-nowrap">Ano</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide whitespace-nowrap">Turmas</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wide whitespace-nowrap">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php foreach ($eventos as $ev): ?>
                            <?php
                            $eid = (int) ($ev['id'] ?? 0);
                            if ($eid <= 0) {
                                continue;
                            }
                            $turmasLbl = [];
                            foreach ((array) ($ev['turmas_ids'] ?? []) as $tidEv) {
                                $tidEv = (int) $tidEv;
                                if ($tidEv > 0 && isset($turmasById[$tidEv])) {
                                    $tx = $turmasById[$tidEv];
                                    $turmasLbl[] = trim((string) (($tx['serie_nome'] ?? '') . ' - ' . ($tx['nome'] ?? ('#' . $tidEv))));
                                }
                            }
                            $nt = count($turmasLbl);
                            $turmasJsonAttr = htmlspecialchars(json_encode($turmasLbl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                            $evPayload = [
                                'id' => $eid,
                                'nome' => (string) ($ev['nome'] ?? ''),
                                'bimestre' => (string) ($ev['bimestre'] ?? ''),
                                'ano_letivo' => (int) ($ev['ano_letivo'] ?? 0),
                                'turmas_ids' => array_values(array_map('intval', (array) ($ev['turmas_ids'] ?? []))),
                                'materias_ids' => array_values(array_map('intval', (array) ($ev['materias_ids'] ?? []))),
                                'origem' => (string) ($ev['origem'] ?? 'manual'),
                            ];
                            $evB64 = base64_encode(json_encode($evPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                            ?>
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    <?= htmlspecialchars((string) ($ev['nome'] ?? 'Evento'), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-3 py-3 text-gray-700 whitespace-nowrap"><?= htmlspecialchars((string) ($ev['bimestre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-3 text-gray-700 whitespace-nowrap"><?= (int) ($ev['ano_letivo'] ?? 0) ?></td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <button type="button" class="btn-faltas-ver-turmas inline-flex items-center justify-center p-2 text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100" data-turmas-json="<?= $turmasJsonAttr ?>" title="Ver turmas deste evento<?= $nt > 0 ? ' (' . $nt . ')' : '' ?>" aria-label="Ver turmas deste evento<?= $nt > 0 ? ' (' . $nt . ' turmas)' : '' ?>">
                                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        <button type="button" class="btn-faltas-editar-evento inline-flex items-center justify-center p-2 rounded-lg border border-gray-300 bg-white text-gray-800 hover:bg-gray-50" data-ev-b64="<?= htmlspecialchars($evB64, ENT_QUOTES, 'UTF-8') ?>" title="Editar evento (nome, bimestre, ano, turmas e matérias)" aria-label="Editar evento">
                                            <svg class="w-5 h-5 shrink-0 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <a href="<?= $urlLancarFaltas ?>?evento_id=<?= $eid ?>" class="inline-flex items-center justify-center p-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700" title="Abrir página para lançar faltas neste evento" aria-label="Lançar faltas">
                                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9h2m-2 4h2m-5-4h.01M12 16h.01"/></svg>
                                        </a>
                                        <form method="POST" action="<?= URL ?>/admin/faltas/excluir" class="inline" onsubmit="return confirm('Deseja realmente excluir este evento de faltas e todos os lançamentos associados?');">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="evento_id" value="<?= $eid ?>">
                                            <button type="submit" class="inline-flex items-center justify-center p-2 rounded-lg bg-red-50 text-red-700 border border-red-200 hover:bg-red-100" title="Excluir este evento permanentemente" aria-label="Excluir evento">
                                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal: lista de turmas do evento -->
    <div id="modal-faltas-turmas" class="fixed inset-0 z-[60] hidden" aria-hidden="true">
        <div class="faltas-modal-backdrop absolute inset-0 bg-black/40" data-faltas-modal-close title="Fechar"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none">
            <div class="pointer-events-auto bg-white rounded-xl shadow-xl border border-gray-200 max-w-lg w-full max-h-[85vh] overflow-hidden flex flex-col">
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-gray-900">Turmas do evento</h3>
                    <button type="button" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100" data-faltas-modal-close aria-label="Fechar" title="Fechar esta janela">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="px-5 py-4 overflow-y-auto">
                    <ul id="modal-faltas-turmas-ul" class="space-y-2 text-sm text-gray-800 list-disc list-inside"></ul>
                </div>
                <div class="px-5 py-3 border-t border-gray-100 flex justify-end">
                    <button type="button" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200" data-faltas-modal-close title="Fechar sem alterar">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: criar / editar evento -->
    <div id="modal-faltas-evento" class="fixed inset-0 z-[60] hidden" aria-hidden="true">
        <div class="faltas-modal-backdrop absolute inset-0 bg-black/40" data-faltas-modal-close title="Fechar"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none overflow-y-auto">
            <div class="pointer-events-auto bg-white rounded-xl shadow-xl border border-gray-200 w-full max-w-4xl max-h-[92vh] my-4 overflow-hidden flex flex-col">
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between gap-3 shrink-0">
                    <h3 id="modal-faltas-evento-titulo" class="text-lg font-semibold text-gray-900">Novo evento de faltas</h3>
                    <button type="button" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100" data-faltas-modal-close aria-label="Fechar" title="Fechar esta janela">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="px-5 py-4 overflow-y-auto">
                    <form method="POST" id="form-modal-faltas-evento" action="<?= htmlspecialchars($urlCriarFaltas, ENT_QUOTES, 'UTF-8') ?>" class="space-y-5" onsubmit="return validarTurmasModalFaltas(this);">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="evento_id" id="faltas-modal-evento-id" value="">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nome do evento</label>
                                <input type="text" name="nome" id="faltas-modal-nome" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Ex.: Faltas 1º Bimestre - Ensino Médio" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Bimestre</label>
                                <select name="bimestre" id="faltas-modal-bimestre" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                                    <option value="">Selecione</option>
                                    <option value="1º Bimestre">1º Bimestre</option>
                                    <option value="2º Bimestre">2º Bimestre</option>
                                    <option value="3º Bimestre">3º Bimestre</option>
                                    <option value="4º Bimestre">4º Bimestre</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ano letivo</label>
                                <input type="number" name="ano_letivo" id="faltas-modal-ano" min="2000" max="2100" value="<?= (int) date('Y') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Origem das faltas</label>
                            <select name="origem" id="faltas-modal-origem" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="manual">Lançamento manual (secretaria)</option>
                                <option value="diario">Diário / catraca (recalcula o total, não soma no escuro)</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Com origem Diário, a Gestão de Presença reescreve o número deste evento a partir das chamadas — eventos manuais antigos não são alterados.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Turmas do evento</label>
                            <div class="flex flex-wrap gap-x-6 gap-y-3 p-4 bg-gray-50 border border-gray-200 rounded-xl max-h-56 overflow-y-auto">
                                <?php foreach ($turmas as $t): ?>
                                    <?php
                                    $tid = (int) ($t['id'] ?? 0);
                                    if ($tid <= 0) {
                                        continue;
                                    }
                                    $serieNome = trim((string) ($t['serie_nome'] ?? ''));
                                    $turmaNome = trim((string) ($t['nome'] ?? ('Turma #' . $tid)));
                                    $lbl = ($serieNome !== '' ? ($serieNome . ' — ') : '') . $turmaNome;
                                    ?>
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-800 cursor-pointer select-none min-w-[12rem]">
                                        <input type="checkbox" name="turmas_ids[]" value="<?= $tid ?>" class="faltas-modal-cb-turma rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 shrink-0">
                                        <span><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Matérias do evento <span class="font-normal text-gray-500">(opcional)</span></label>
                            <div class="flex flex-wrap gap-x-6 gap-y-3 p-4 bg-gray-50 border border-gray-200 rounded-xl max-h-56 overflow-y-auto">
                                <?php foreach ($materiasCatalogo as $mat): ?>
                                    <?php
                                    $mid = (int) ($mat['id'] ?? 0);
                                    if ($mid <= 0) {
                                        continue;
                                    }
                                    ?>
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-800 cursor-pointer select-none min-w-[10rem]">
                                        <input type="checkbox" name="materias_ids[]" value="<?= $mid ?>" class="faltas-modal-cb-materia rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 shrink-0">
                                        <span><?= htmlspecialchars((string) ($mat['nome'] ?? ('#' . $mid)), ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 justify-end pt-2 border-t border-gray-100">
                            <button type="button" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200" data-faltas-modal-close title="Descartar e fechar o formulário">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Cancelar
                            </button>
                            <button type="submit" id="faltas-modal-submit" class="btn-primary-custom inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg hover:opacity-90" title="Criar o evento no sistema com os dados preenchidos">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span id="faltas-modal-submit-label">Criar evento</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var urlCriar = <?= json_encode($urlCriarFaltas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES) ?>;
        var urlAtualizar = <?= json_encode($urlAtualizarFaltas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES) ?>;
        var modalTurmas = document.getElementById('modal-faltas-turmas');
        var modalTurmasUl = document.getElementById('modal-faltas-turmas-ul');
        var modalEvento = document.getElementById('modal-faltas-evento');
        var formEvento = document.getElementById('form-modal-faltas-evento');
        var tituloEvento = document.getElementById('modal-faltas-evento-titulo');
        var inpEventoId = document.getElementById('faltas-modal-evento-id');
        var inpNome = document.getElementById('faltas-modal-nome');
        var selBim = document.getElementById('faltas-modal-bimestre');
        var inpAno = document.getElementById('faltas-modal-ano');
        var selOrigem = document.getElementById('faltas-modal-origem');
        var btnSubmit = document.getElementById('faltas-modal-submit');
        var btnSubmitLabel = document.getElementById('faltas-modal-submit-label');

        function openModal(el) {
            if (!el) return;
            el.classList.remove('hidden');
            el.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
        }
        function closeModal(el) {
            if (!el) return;
            el.classList.add('hidden');
            el.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
        }
        function closeAllFaltasModals() {
            closeModal(modalTurmas);
            closeModal(modalEvento);
        }

        document.querySelectorAll('[data-faltas-modal-close]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var m = btn.closest('#modal-faltas-turmas, #modal-faltas-evento');
                if (m) closeModal(m);
            });
        });

        document.querySelectorAll('.btn-faltas-ver-turmas').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var raw = btn.getAttribute('data-turmas-json') || '[]';
                var labels = [];
                try { labels = JSON.parse(raw); } catch (e) { labels = []; }
                modalTurmasUl.innerHTML = '';
                if (!labels.length) {
                    var li0 = document.createElement('li');
                    li0.className = 'text-gray-500 list-none -ml-4';
                    li0.textContent = 'Nenhuma turma vinculada.';
                    modalTurmasUl.appendChild(li0);
                } else {
                    labels.forEach(function (t) {
                        var li = document.createElement('li');
                        li.textContent = t;
                        modalTurmasUl.appendChild(li);
                    });
                }
                openModal(modalTurmas);
            });
        });

        function resetCheckboxes(root, sel) {
            root.querySelectorAll(sel).forEach(function (cb) { cb.checked = false; });
        }
        function setCheckboxesByIds(root, sel, ids) {
            var set = {};
            (ids || []).forEach(function (id) { set[String(id)] = true; });
            root.querySelectorAll(sel).forEach(function (cb) {
                cb.checked = !!set[String(cb.value)];
            });
        }

        function abrirModalNovoEvento() {
            formEvento.action = urlCriar;
            inpEventoId.value = '';
            inpEventoId.disabled = true;
            tituloEvento.textContent = 'Novo evento de faltas';
            if (btnSubmitLabel) btnSubmitLabel.textContent = 'Criar evento';
            if (btnSubmit) btnSubmit.title = 'Criar o evento no sistema com os dados preenchidos';
            inpNome.value = '';
            selBim.value = '';
            inpAno.value = String(new Date().getFullYear());
            if (selOrigem) selOrigem.value = 'manual';
            resetCheckboxes(formEvento, '.faltas-modal-cb-turma');
            resetCheckboxes(formEvento, '.faltas-modal-cb-materia');
            openModal(modalEvento);
            setTimeout(function () { inpNome.focus(); }, 100);
        }

        function abrirModalEditarEvento(b64) {
            var data;
            try {
                data = JSON.parse(atob(b64));
            } catch (e) {
                alert('Não foi possível carregar os dados do evento.');
                return;
            }
            formEvento.action = urlAtualizar;
            inpEventoId.disabled = false;
            inpEventoId.value = String(data.id || '');
            tituloEvento.textContent = 'Editar evento de faltas';
            if (btnSubmitLabel) btnSubmitLabel.textContent = 'Salvar alterações';
            if (btnSubmit) btnSubmit.title = 'Salvar alterações de nome, bimestre, ano, turmas e matérias neste evento';
            inpNome.value = data.nome || '';
            selBim.value = data.bimestre || '';
            inpAno.value = String(data.ano_letivo || new Date().getFullYear());
            if (selOrigem) selOrigem.value = data.origem || 'manual';
            setCheckboxesByIds(formEvento, '.faltas-modal-cb-turma', data.turmas_ids || []);
            setCheckboxesByIds(formEvento, '.faltas-modal-cb-materia', data.materias_ids || []);
            openModal(modalEvento);
        }

        var btnNovo = document.getElementById('btn-abrir-modal-novo-evento-faltas');
        if (btnNovo) btnNovo.addEventListener('click', abrirModalNovoEvento);

        document.querySelectorAll('.btn-faltas-editar-evento').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var b64 = btn.getAttribute('data-ev-b64');
                if (b64) abrirModalEditarEvento(b64);
            });
        });
    })();

    function validarTurmasModalFaltas(form) {
        var n = form.querySelectorAll('input[name="turmas_ids[]"]:checked').length;
        if (n < 1) {
            alert('Marque ao menos uma turma.');
            return false;
        }
        return true;
    }
    </script>
</div>
