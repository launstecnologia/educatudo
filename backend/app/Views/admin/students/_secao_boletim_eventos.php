<?php
$boletins_gerados = is_array($boletins_gerados ?? null) ? $boletins_gerados : [];
?>
<?php if (!empty($boletins_gerados)): ?>
<div class="flex justify-end mb-4">
    <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/boletim/pdf"
       class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium text-sm shadow-sm">
        <i class="fa-solid fa-download"></i>
        Baixar PDF
    </a>
</div>
<?php endif; ?>

<?php if (empty($boletins_gerados)): ?>
    <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
        <p class="text-gray-500">Nenhum evento gerado para este aluno ainda.</p>
    </div>
<?php else: ?>
    <?php
    $boletim_pode_excluir = (bool) ($boletim_pode_excluir ?? false);
    $boletim_aluno_id = (int) ($student['id'] ?? 0);
    $boletim_csrf_token = (string) ($csrf_token ?? '');
    require __DIR__ . '/../../partials/boletins_gerados.php';
    ?>

    <?php
    $obsConteudo = (string) (($boletim_observacao['conteudo'] ?? '') ?: '');
    $obsTokenInit = htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8');
    ?>
    <div id="boletim-observacao-block"
         class="mt-6 rounded-xl border border-gray-200 bg-white p-5"
         data-aluno-id="<?= (int) ($student['id'] ?? 0) ?>"
         data-csrf-token="<?= $obsTokenInit ?>"
         data-endpoint="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/boletim/observacao">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-semibold text-gray-900">Observação</h3>
            <button type="button"
                    id="btn-editar-observacao"
                    class="<?= $obsConteudo === '' ? 'hidden' : '' ?> text-sm text-indigo-600 hover:text-indigo-700 font-medium">
                Editar
            </button>
        </div>

        <div id="observacao-view" class="<?= $obsConteudo === '' ? 'hidden' : '' ?>">
            <p id="observacao-texto" class="text-sm text-gray-800 whitespace-pre-wrap break-words"><?= htmlspecialchars($obsConteudo, ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <div id="observacao-edit" class="<?= $obsConteudo === '' ? '' : 'hidden' ?> space-y-3">
            <textarea id="observacao-textarea"
                      rows="5"
                      maxlength="5000"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                      placeholder="Escreva uma observação que ficará no boletim e no PDF…"><?= htmlspecialchars($obsConteudo, ENT_QUOTES, 'UTF-8') ?></textarea>
            <div class="flex items-center gap-2">
                <button type="button"
                        id="btn-salvar-observacao"
                        class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90">
                    Salvar
                </button>
                <button type="button"
                        id="btn-cancelar-observacao"
                        class="<?= $obsConteudo === '' ? 'hidden' : '' ?> px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg text-sm font-medium">
                    Cancelar
                </button>
                <span id="observacao-status" class="text-xs text-gray-500"></span>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var block = document.getElementById('boletim-observacao-block');
        if (!block) return;
        var viewEl = document.getElementById('observacao-view');
        var editEl = document.getElementById('observacao-edit');
        var textoEl = document.getElementById('observacao-texto');
        var taEl = document.getElementById('observacao-textarea');
        var btnEditar = document.getElementById('btn-editar-observacao');
        var btnSalvar = document.getElementById('btn-salvar-observacao');
        var btnCancelar = document.getElementById('btn-cancelar-observacao');
        var statusEl = document.getElementById('observacao-status');
        var endpoint = block.getAttribute('data-endpoint') || '';
        var csrf = block.getAttribute('data-csrf-token') || '';
        var ultimoSalvo = (textoEl && textoEl.textContent) ? textoEl.textContent : '';

        function entrarEdicao() {
            if (viewEl) viewEl.classList.add('hidden');
            if (editEl) editEl.classList.remove('hidden');
            if (btnEditar) btnEditar.classList.add('hidden');
            if (btnCancelar) btnCancelar.classList.toggle('hidden', ultimoSalvo.trim() === '');
            if (taEl) {
                taEl.value = ultimoSalvo;
                taEl.focus();
            }
        }

        function sairEdicao() {
            if (textoEl) textoEl.textContent = ultimoSalvo;
            var temConteudo = ultimoSalvo.trim() !== '';
            if (viewEl) viewEl.classList.toggle('hidden', !temConteudo);
            if (editEl) editEl.classList.toggle('hidden', temConteudo);
            if (btnEditar) btnEditar.classList.toggle('hidden', !temConteudo);
            if (btnCancelar) btnCancelar.classList.toggle('hidden', !temConteudo);
        }

        function salvar() {
            if (!taEl) return;
            var conteudo = taEl.value || '';
            statusEl.textContent = 'Salvando…';
            statusEl.classList.remove('text-red-600');
            statusEl.classList.add('text-gray-500');
            var form = new FormData();
            form.append('_token', csrf);
            form.append('conteudo', conteudo);
            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                body: form,
            }).then(function (resp) {
                return resp.json().then(function (data) { return { ok: resp.ok, data: data }; });
            }).then(function (res) {
                if (!res.ok || !res.data || res.data.success !== true) {
                    var msg = (res.data && res.data.error) ? res.data.error : 'Falha ao salvar.';
                    statusEl.textContent = msg;
                    statusEl.classList.remove('text-gray-500');
                    statusEl.classList.add('text-red-600');
                    return;
                }
                ultimoSalvo = (res.data.conteudo !== undefined ? String(res.data.conteudo) : conteudo);
                statusEl.textContent = 'Salvo.';
                sairEdicao();
                setTimeout(function () { statusEl.textContent = ''; }, 1800);
            }).catch(function (err) {
                statusEl.textContent = 'Falha de rede.';
                statusEl.classList.remove('text-gray-500');
                statusEl.classList.add('text-red-600');
                console.error(err);
            });
        }

        if (btnEditar) btnEditar.addEventListener('click', entrarEdicao);
        if (btnSalvar) btnSalvar.addEventListener('click', salvar);
        if (btnCancelar) btnCancelar.addEventListener('click', sairEdicao);
    })();
    </script>
<?php endif; ?>
