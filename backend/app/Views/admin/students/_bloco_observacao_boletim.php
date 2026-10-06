<?php
/**
 * Bloco editável de observação do boletim (coordenação).
 * Requer: $aluno_id ou $student['id'], $csrf_token, $boletim_observacao (opcional).
 */
$obsAlunoId = (int) ($aluno_id ?? ($student['id'] ?? 0));
$obsConteudo = (string) (($boletim_observacao['conteudo'] ?? '') ?: '');
$obsTokenInit = htmlspecialchars((string) ($csrf_token ?? $token ?? ''), ENT_QUOTES, 'UTF-8');
if ($obsAlunoId <= 0) {
    return;
}
?>
<div id="boletim-observacao-block"
     class="mt-6 rounded-xl border border-gray-200 bg-white p-5"
     data-aluno-id="<?= $obsAlunoId ?>"
     data-csrf-token="<?= $obsTokenInit ?>"
     data-endpoint="<?= URL ?>/admin/students/<?= $obsAlunoId ?>/boletim/observacao">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-base font-semibold text-gray-900">Observação</h3>
        <div class="flex items-center gap-3">
            <button type="button"
                    id="btn-apagar-observacao"
                    class="<?= $obsConteudo === '' ? 'hidden' : '' ?> text-sm font-medium text-gray-600 hover:text-gray-800">
                Apagar observação
            </button>
            <button type="button"
                    id="btn-editar-observacao"
                    class="<?= $obsConteudo === '' ? 'hidden' : '' ?> text-sm text-indigo-600 hover:text-indigo-700 font-medium">
                Editar
            </button>
        </div>
    </div>

    <div id="observacao-view" class="<?= $obsConteudo === '' ? 'hidden' : '' ?>">
        <p id="observacao-texto" class="text-sm text-gray-800 whitespace-pre-wrap break-words"><?= htmlspecialchars($obsConteudo, ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div id="observacao-edit" class="<?= $obsConteudo === '' ? '' : 'hidden' ?> space-y-3">
        <textarea id="observacao-textarea"
                  rows="5"
                  maxlength="5000"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                  placeholder="Escreva uma observação que ficará no boletim oficial e no PDF…"><?= htmlspecialchars($obsConteudo, ENT_QUOTES, 'UTF-8') ?></textarea>
        <div class="flex items-center gap-2 flex-wrap">
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
    <?php
    $obs_versoes = is_array($boletim_observacao['versoes'] ?? null) ? $boletim_observacao['versoes'] : [];
    $obs_log = is_array($boletim_observacao['log'] ?? null) ? $boletim_observacao['log'] : [];
    require dirname(__DIR__, 2) . '/partials/observacao_coordenacao_historico.php';
    ?>
    <div id="observacao-purge-wrap" class="mt-3 <?= $obs_versoes === [] ? 'hidden' : '' ?>">
        <button type="button" id="btn-apagar-versoes" class="text-sm font-medium text-red-700 hover:text-red-800">Apagar todas as versões</button>
        <div id="observacao-purge-box" class="hidden mt-2 rounded-lg border border-red-200 bg-red-50 p-3">
            <p class="text-xs text-red-800 mb-2">Apagar todas as versões exige a sua senha. O registro de quem alterou permanece.</p>
            <div class="flex flex-wrap items-center gap-2">
                <input type="password" autocomplete="off" id="observacao-senha" class="w-48 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Sua senha">
                <button type="button" id="btn-confirmar-versoes" class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700">Confirmar exclusão</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var block = document.getElementById('boletim-observacao-block');
    if (!block || block.getAttribute('data-obs-bound') === '1') return;
    block.setAttribute('data-obs-bound', '1');
    var viewEl = document.getElementById('observacao-view');
    var editEl = document.getElementById('observacao-edit');
    var textoEl = document.getElementById('observacao-texto');
    var taEl = document.getElementById('observacao-textarea');
    var btnEditar = document.getElementById('btn-editar-observacao');
    var btnSalvar = document.getElementById('btn-salvar-observacao');
    var btnCancelar = document.getElementById('btn-cancelar-observacao');
    var btnApagar = document.getElementById('btn-apagar-observacao');
    var btnVersoes = document.getElementById('btn-apagar-versoes');
    var purgeWrap = document.getElementById('observacao-purge-wrap');
    var purgeBox = document.getElementById('observacao-purge-box');
    var btnConfirmarVersoes = document.getElementById('btn-confirmar-versoes');
    var senhaEl = document.getElementById('observacao-senha');
    var historico = block.querySelector('.obs-historico');
    var statusEl = document.getElementById('observacao-status');
    var endpoint = block.getAttribute('data-endpoint') || '';
    var csrf = block.getAttribute('data-csrf-token') || '';
    var ultimoSalvo = (textoEl && textoEl.textContent) ? textoEl.textContent : '';

    function escaparObs(valor) {
        return String(valor || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function pintarHistoricoObs(data) {
        if (!historico || !data) return;
        var versoes = Array.isArray(data.versoes) ? data.versoes : [];
        var log = Array.isArray(data.log) ? data.log : [];
        var html = '<div class="obs-versoes">';
        if (versoes.length) {
            html += '<p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Versões escritas</p><ul class="space-y-2">';
            versoes.forEach(function (versao) {
                var quem = [versao.usuario_nome || '', versao.criado_em || ''].filter(Boolean).join(' · ');
                html += '<li class="rounded-lg border border-gray-200 bg-white px-3 py-2"><p class="text-sm text-gray-800 whitespace-pre-wrap break-words">' + escaparObs(versao.conteudo) + '</p><p class="mt-1 text-xs text-gray-500">' + escaparObs(quem) + '</p></li>';
            });
            html += '</ul>';
        }
        html += '</div><div class="obs-log">';
        if (log.length) {
            html += '<p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Registro de alterações</p><ul class="space-y-1">';
            log.forEach(function (item) {
                html += '<li class="text-xs text-gray-600">' + escaparObs(item.criado_em) + ' — ' + escaparObs(item.usuario_nome || 'Coordenação') + ' — ' + escaparObs(item.rotulo) + '</li>';
            });
            html += '</ul>';
        }
        html += '</div>';
        historico.innerHTML = html;
        if (purgeWrap) purgeWrap.classList.toggle('hidden', versoes.length === 0);
    }
    function enviarObs(url, extra) {
        var form = new FormData();
        form.append('_token', csrf);
        Object.keys(extra || {}).forEach(function (chave) { form.append(chave, extra[chave]); });
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
            body: form,
        }).then(function (resp) {
            return resp.json().then(function (data) { return { ok: resp.ok, data: data }; });
        }).then(function (res) {
            if (!res.ok || !res.data || res.data.success !== true) {
                throw new Error((res.data && res.data.error) ? res.data.error : 'Falha ao salvar.');
            }
            return res.data;
        });
    }

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
        if (btnApagar) btnApagar.classList.toggle('hidden', !temConteudo);
    }

    function salvar() {
        if (!taEl) return;
        var conteudo = taEl.value || '';
        statusEl.textContent = 'Salvando…';
        statusEl.classList.remove('text-red-600');
        statusEl.classList.add('text-gray-500');
        enviarObs(endpoint, { conteudo: conteudo }).then(function (data) {
            ultimoSalvo = data.conteudo !== undefined ? String(data.conteudo) : conteudo;
            pintarHistoricoObs(data);
            statusEl.textContent = 'Salvo.';
            sairEdicao();
            setTimeout(function () { statusEl.textContent = ''; }, 1800);
        }).catch(function (err) {
            statusEl.textContent = err.message || 'Falha ao salvar.';
            statusEl.classList.remove('text-gray-500');
            statusEl.classList.add('text-red-600');
        });
    }

    if (btnEditar) btnEditar.addEventListener('click', entrarEdicao);
    if (btnSalvar) btnSalvar.addEventListener('click', salvar);
    if (btnCancelar) btnCancelar.addEventListener('click', sairEdicao);
    if (btnApagar) btnApagar.addEventListener('click', function () {
        if (!window.confirm('Apagar a observação atual? O texto continua salvo nas versões.')) return;
        statusEl.textContent = 'Apagando…';
        enviarObs(endpoint + '/limpar', {}).then(function (data) {
            ultimoSalvo = data.conteudo !== undefined ? String(data.conteudo) : '';
            if (taEl) taEl.value = ultimoSalvo;
            pintarHistoricoObs(data);
            statusEl.textContent = 'Observação apagada. A versão escrita foi mantida.';
            sairEdicao();
        }).catch(function (err) {
            statusEl.textContent = err.message || 'Falha ao apagar.';
            statusEl.classList.remove('text-gray-500');
            statusEl.classList.add('text-red-600');
        });
    });
    if (btnVersoes && purgeBox) {
        btnVersoes.addEventListener('click', function () {
            purgeBox.classList.toggle('hidden');
            if (senhaEl && !purgeBox.classList.contains('hidden')) senhaEl.focus();
        });
    }
    if (btnConfirmarVersoes) {
        btnConfirmarVersoes.addEventListener('click', function () {
            var senha = senhaEl ? senhaEl.value : '';
            if (!senha) {
                statusEl.textContent = 'Informe sua senha.';
                statusEl.classList.remove('text-gray-500');
                statusEl.classList.add('text-red-600');
                return;
            }
            enviarObs(endpoint + '/versoes/excluir', { senha: senha }).then(function (data) {
                ultimoSalvo = '';
                if (taEl) taEl.value = '';
                if (senhaEl) senhaEl.value = '';
                purgeBox.classList.add('hidden');
                pintarHistoricoObs(data);
                statusEl.textContent = 'Versões apagadas.';
                statusEl.classList.remove('text-red-600');
                statusEl.classList.add('text-gray-500');
                sairEdicao();
            }).catch(function (err) {
                statusEl.textContent = err.message || 'Falha ao apagar as versões.';
                statusEl.classList.remove('text-gray-500');
                statusEl.classList.add('text-red-600');
            });
        });
    }
})();
</script>
