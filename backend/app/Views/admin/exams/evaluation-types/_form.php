<?php
$tipo = is_array($tipo ?? null) ? $tipo : [];
$ehEdicao = !empty($tipo['id']);
$action = $ehEdicao
    ? URL . '/admin/provas/tipos-avaliacao/' . (int) $tipo['id']
    : URL . '/admin/provas/tipos-avaliacao';
$csrf = (string) ($csrf_token ?? '');
$temRegras = !empty($tem_regras);
$origens = is_array($origens ?? null) ? $origens : [];
$registros = is_array($registros ?? null) ? $registros : [];
$criterios = is_array($criterios ?? null) ? $criterios : [];
$origemSel = (string) ($tipo['origem'] ?? 'lancamento_direto');
$registroSel = (string) ($tipo['registro_evento'] ?? 'nota');
$criterioSel = (string) ($tipo['criterio_fechamento'] ?? 'ultima');
$qtdEsperada = $tipo['quantidade_eventos_esperada'] ?? '';
include __DIR__ . '/../../_partials/flash_message.php';
?>

<form method="POST" action="<?= htmlspecialchars($action) ?>" id="form-tipo-nota" class="space-y-6">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

    <div class="bg-white rounded-xl shadow-lg p-6 w-full">
        <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-6">Identificação</h3>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Nome <span class="text-red-500">*</span></label>
            <input type="text" name="nome" required value="<?= htmlspecialchars((string) ($tipo['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                   placeholder="Ex.: Prova Semanal">
        </div>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
            <textarea name="descricao" rows="3"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?= htmlspecialchars((string) ($tipo['descricao'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="mb-2">
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" name="ativo" value="1" <?= !isset($tipo['ativo']) || !empty($tipo['ativo']) ? 'checked' : '' ?> class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                <span class="ml-2 text-sm text-gray-700">Ativo</span>
            </label>
        </div>
    </div>

    <?php if ($temRegras): ?>
    <div class="bg-white rounded-xl shadow-lg p-6 w-full">
        <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-2">Como esta nota é calculada</h3>
        <p class="text-sm text-gray-500 mb-6">O boletim pega só a <strong>nota final</strong> deste tipo — não soma prova a prova de novo.</p>

        <div class="mb-6">
            <span class="block text-sm font-medium text-gray-700 mb-2">Origem</span>
            <p class="text-sm text-gray-500 mb-3">Onde a avaliação acontece.</p>
            <?php
            $canalSel = TipoNotaRegraService::canalDaOrigem($origemSel);
            $canais = TipoNotaRegraService::CANAIS_ORIGEM;
            $modosOffline = TipoNotaRegraService::MODOS_OFFLINE;
            ?>
            <input type="hidden" name="origem" id="campo-origem" value="<?= htmlspecialchars($origemSel, ENT_QUOTES, 'UTF-8') ?>">
            <div class="space-y-3">
                <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
                    <input type="radio" name="canal_origem" value="online" class="mt-1 js-canal"
                           <?= $canalSel === 'online' ? 'checked' : '' ?>>
                    <span>
                        <span class="block text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $canais['online']['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="block text-xs text-gray-500 mt-0.5"><?= htmlspecialchars((string) $canais['online']['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                </label>
                <div class="rounded-lg border border-gray-200 p-3">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" name="canal_origem" value="offline" class="mt-1 js-canal"
                               <?= $canalSel === 'offline' ? 'checked' : '' ?>>
                        <span>
                            <span class="block text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $canais['offline']['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="block text-xs text-gray-500 mt-0.5"><?= htmlspecialchars((string) $canais['offline']['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                    </label>
                    <div class="js-bloco-offline mt-3 ml-7 space-y-2 <?= $canalSel === 'offline' ? '' : 'hidden' ?>">
                        <p class="text-xs font-medium text-gray-600">Como a nota é informada</p>
                        <?php foreach ($modosOffline as $modoKey => $modo): ?>
                        <label class="flex items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 cursor-pointer hover:bg-white">
                            <input type="radio" name="modo_offline" value="<?= htmlspecialchars((string) $modoKey, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 js-modo-offline"
                                   <?= $origemSel === $modoKey ? 'checked' : '' ?>>
                            <span>
                                <span class="block text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $modo['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="block text-xs text-gray-500 mt-0.5"><?= htmlspecialchars((string) $modo['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 js-bloco-eventos<?= $origemSel === 'lancamento_direto' ? ' hidden' : '' ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">O que cada evento registra</label>
                <select name="registro_evento" id="registro_evento" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <?php foreach ($registros as $k => $lab): ?>
                        <option value="<?= htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') ?>" <?= $registroSel === $k ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $lab, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Quantidade de eventos esperada</label>
                <input type="number" name="quantidade_eventos_esperada" min="0" max="40"
                       value="<?= $qtdEsperada !== '' && $qtdEsperada !== null ? (int) $qtdEsperada : '' ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Vazio = aberta">
                <p class="text-xs text-gray-500 mt-1">Ex.: 8 provas semanais. Deixe vazio se variar.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <div class="flex items-center gap-1.5 mb-2">
                    <label class="text-sm font-medium text-gray-700" for="criterio_fechamento">Como fecha a nota final</label>
                    <button type="button"
                            class="js-ajuda-tipo inline-flex h-5 w-5 items-center justify-center rounded-full text-gray-400 hover:text-primary hover:bg-gray-100"
                            data-ajuda="criterio"
                            aria-label="O que significa cada forma de fechar a nota">
                        <i class="fa-solid fa-circle-info text-sm"></i>
                    </button>
                </div>
                <select name="criterio_fechamento" id="criterio_fechamento" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <?php foreach ($criterios as $k => $lab): ?>
                        <option value="<?= htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') ?>" <?= $criterioSel === $k ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $lab, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1 js-hint-nq">Soma todos os acertos e todas as questões do período, depois converte para a escala. Não é a média das notas 0–10 de cada prova.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Escala</label>
                <input type="number" name="escala_max" min="1" max="100" step="0.01"
                       value="<?= htmlspecialchars(number_format((float) ($tipo['escala_max'] ?? 10), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
        </div>

        <div class="mb-2">
            <div class="flex items-center gap-1.5 mb-2">
                <label class="text-sm font-medium text-gray-700">Professores do mesmo componente</label>
                <button type="button"
                        class="js-ajuda-tipo inline-flex h-5 w-5 items-center justify-center rounded-full text-gray-400 hover:text-primary hover:bg-gray-100"
                        data-ajuda="professores"
                        aria-label="Como juntar notas de dois professores">
                    <i class="fa-solid fa-circle-info text-sm"></i>
                </button>
            </div>
            <?php
            $criteriosProfessores = is_array($criterios_professores ?? null) ? $criterios_professores : [];
            $criterioProfSel = strtolower(trim((string) ($tipo['criterio_professores_mesmo_componente'] ?? '')));
            if ($criterioProfSel === '' && !empty($tipo['media_professores_mesmo_componente'])) {
                $criterioProfSel = 'media';
            }
            if ($criterioProfSel === '' || !isset($criteriosProfessores[$criterioProfSel])) {
                $criterioProfSel = 'nenhum';
            }
            ?>
            <select name="criterio_professores_mesmo_componente" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <?php foreach ($criteriosProfessores as $k => $lab): ?>
                    <option value="<?= htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') ?>" <?= $criterioProfSel === $k ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $lab, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-500 mt-1">Ex.: um professor lança 4 e o outro 6 — média dá 5,0; soma dá 10,0 (limitada à escala).</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex justify-end space-x-4">
        <a href="<?= URL ?>/admin/provas/tipos-avaliacao" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</a>
        <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg hover:opacity-90"><?= $ehEdicao ? 'Salvar Alterações' : 'Salvar' ?></button>
    </div>
</form>

<?php if ($temRegras): ?>
<div id="modal-ajuda-tipo" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true" aria-labelledby="modal-ajuda-tipo-titulo">
    <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-start justify-between gap-3 mb-4">
            <h3 id="modal-ajuda-tipo-titulo" class="text-lg font-semibold text-gray-900 js-ajuda-titulo"></h3>
            <button type="button" class="js-ajuda-fechar h-8 w-8 rounded-lg text-gray-500 hover:bg-gray-100" aria-label="Fechar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="js-ajuda-corpo text-sm text-gray-700 space-y-3"></div>
        <div class="mt-6 flex justify-end">
            <button type="button" class="js-ajuda-fechar px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Fechar</button>
        </div>
    </div>
</div>
<script>
(function () {
    var form = document.getElementById('form-tipo-nota');
    if (!form) return;
    var blocoEventos = form.querySelector('.js-bloco-eventos');
    var blocoOffline = form.querySelector('.js-bloco-offline');
    var campoOrigem = document.getElementById('campo-origem');
    var criterio = document.getElementById('criterio_fechamento');
    var registro = document.getElementById('registro_evento');
    var hintNq = form.querySelector('.js-hint-nq');

    function canal() {
        var el = form.querySelector('input[name="canal_origem"]:checked');
        return el ? el.value : 'offline';
    }

    function modoOffline() {
        var el = form.querySelector('input[name="modo_offline"]:checked');
        return el ? el.value : 'lancamento_direto';
    }

    function origem() {
        if (canal() === 'online') return 'prova_online';
        var modo = modoOffline();
        return (modo === 'eventos') ? 'eventos' : 'lancamento_direto';
    }

    function garantirModoOffline() {
        if (canal() !== 'offline') return;
        if (form.querySelector('input[name="modo_offline"]:checked')) return;
        var primeiro = form.querySelector('input[name="modo_offline"][value="lancamento_direto"]')
            || form.querySelector('input[name="modo_offline"]');
        if (primeiro) primeiro.checked = true;
    }

    function sync() {
        garantirModoOffline();
        var o = origem();
        if (campoOrigem) campoOrigem.value = o;
        if (blocoOffline) blocoOffline.classList.toggle('hidden', canal() !== 'offline');
        var eventos = o !== 'lancamento_direto';
        if (blocoEventos) blocoEventos.classList.toggle('hidden', !eventos);
        if (criterio) {
            var nq = criterio.querySelector('option[value="aproveitamento_nq"]');
            if (nq) nq.disabled = o === 'lancamento_direto';
            if (o === 'lancamento_direto' && criterio.value === 'aproveitamento_nq') {
                criterio.value = 'ultima';
            }
            if (o === 'prova_online' && registro) {
                registro.value = 'acertos_questoes';
            }
            if (o === 'prova_online' && criterio.value === 'ultima') {
                criterio.value = 'aproveitamento_nq';
            }
        }
        if (hintNq) {
            hintNq.classList.toggle('hidden', !criterio || criterio.value !== 'aproveitamento_nq');
        }
    }

    form.querySelectorAll('.js-canal, .js-modo-offline').forEach(function (el) {
        el.addEventListener('change', sync);
    });
    if (criterio) criterio.addEventListener('change', sync);
    if (registro) registro.addEventListener('change', function () {
        if (registro.value === 'acertos_questoes' && criterio && criterio.value === 'ultima' && origem() !== 'lancamento_direto') {
            criterio.value = 'aproveitamento_nq';
        }
        sync();
    });
    sync();

    var modalAjuda = document.getElementById('modal-ajuda-tipo');
    var ajudaTitulo = modalAjuda ? modalAjuda.querySelector('.js-ajuda-titulo') : null;
    var ajudaCorpo = modalAjuda ? modalAjuda.querySelector('.js-ajuda-corpo') : null;
    var textosAjuda = {
        criterio: {
            titulo: 'Como fecha a nota final',
            html: '<p>O boletim lê só essa nota consolidada do tipo, por matéria, no período. Ele não soma prova a prova de novo.</p>'
                + '<p class="text-xs text-gray-500">Exemplo com 4 semanais de Gramática (escala 10): S1 3/5 = 6,0 · S2 8/10 = 8,0 · S3 4/5 = 8,0 · S4 2/5 = 4,0.</p>'
                + '<p><strong>Última nota lançada.</strong> Fica a mais recente pela data. No exemplo: 4,0 (S4). Use quando o último lançamento substitui os anteriores. Em evento único (bimestral/trabalho) é o padrão.</p>'
                + '<p><strong>Maior nota.</strong> Fica o melhor valor. No exemplo: 8,0. Use quando o aluno pode melhorar e você quer guardar o pico.</p>'
                + '<p><strong>Média das notas.</strong> Média das notas 0–10. No exemplo: (6+8+8+4) ÷ 4 = 6,5. Cada prova pesa igual, mesmo se uma teve 5 questões e outra 10. Não é o critério certo para semanal online.</p>'
                + '<p><strong>Soma (depois limita na escala).</strong> Soma as notas e corta no teto. No exemplo: 26 → 10,0. Use só se cada evento vale um pedaço que, juntos, não devem passar de 10.</p>'
                + '<p><strong>Aproveitamento: (soma acertos ÷ soma questões) × escala.</strong> Junta todos os acertos e questões. No exemplo: 17/25 × 10 = 6,8. A prova com mais questões pesa mais. É o critério da avaliação online (Prova Semanal). Em evento único offline fica desabilitado, porque não há acertos/questões.</p>'
                + '<p class="text-xs text-gray-500">Para o teste do EM: Semanal → Aproveitamento. Bimestral e Trabalho → Última nota lançada.</p>'
        },
        professores: {
            titulo: 'Professores do mesmo componente',
            html: '<p>Vale quando dois (ou mais) professores lançam a mesma matéria no mesmo período.</p>'
                + '<p><strong>Não junta — cada lançamento entra no critério acima.</strong> As notas dos dois entram na mesma lista e o fechamento (última, média, maior…) trata tudo junto, sem primeiro fechar por professor.</p>'
                + '<p><strong>Média das notas dos professores.</strong> Fecha a nota de cada professor e depois tira a média. Um lança 4 e o outro 6 → 5,0.</p>'
                + '<p><strong>Soma das notas dos professores.</strong> Fecha a nota de cada um e soma, limitada à escala. 4 e 6 → 10,0.</p>'
                + '<p class="text-xs text-gray-500">Se só um professor lança a matéria, as três opções dão o mesmo resultado. No teste do EM, deixe em “Não junta”.</p>'
        }
    };

    function fecharAjuda() {
        if (!modalAjuda) return;
        modalAjuda.classList.add('hidden');
        modalAjuda.classList.remove('flex');
    }

    function abrirAjuda(chave) {
        var item = textosAjuda[chave];
        if (!modalAjuda || !item) return;
        if (ajudaTitulo) ajudaTitulo.textContent = item.titulo;
        if (ajudaCorpo) ajudaCorpo.innerHTML = item.html;
        modalAjuda.classList.remove('hidden');
        modalAjuda.classList.add('flex');
    }

    document.querySelectorAll('.js-ajuda-tipo').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            abrirAjuda(btn.getAttribute('data-ajuda') || '');
        });
    });
    if (modalAjuda) {
        modalAjuda.querySelectorAll('.js-ajuda-fechar').forEach(function (btn) {
            btn.addEventListener('click', fecharAjuda);
        });
        modalAjuda.addEventListener('click', function (e) {
            if (e.target === modalAjuda) fecharAjuda();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') fecharAjuda();
    });
})();
</script>
<?php endif; ?>
