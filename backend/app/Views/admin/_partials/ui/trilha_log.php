<?php
/**
 * Log de cadastro (perfil dev): tabela + segundo offcanvas com o que mudou.
 *
 * Variáveis:
 * - $trilha_log_id (string) — prefixo, ex.: trilhaCursosSeries
 * - $trilha_log_titulo (string)
 * - $trilha_log_url (string) — GET JSON
 * - $trilha_log_vazio (string)
 */
$trilha_log_id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($trilha_log_id ?? 'trilhaLog'));
if ($trilha_log_id === '') {
    $trilha_log_id = 'trilhaLog';
}
$trilha_log_titulo = (string) ($trilha_log_titulo ?? 'Log');
$trilha_log_url = (string) ($trilha_log_url ?? '');
$trilha_log_vazio = (string) ($trilha_log_vazio ?? 'Nenhum cadastro ou alteração registrado.');
$trilhaFn = preg_replace('/[^a-zA-Z0-9]/', '', ucwords(str_replace(['-', '_'], ' ', $trilha_log_id)));
$trilhaAbrir = 'abrir' . $trilhaFn;
$trilhaOpen = 'open' . $trilhaFn . 'Drawer';
$trilhaClose = 'close' . $trilhaFn . 'Drawer';
$trilhaFecharDetalhe = 'fecharDetalhe' . $trilhaFn;
$trilhaCorpoId = $trilha_log_id . 'Corpo';
$trilhaDetalheId = $trilha_log_id . 'Detalhe';
$trilhaDetalheBackdropId = $trilha_log_id . 'DetalheBackdrop';
$trilhaDetalheCorpoId = $trilha_log_id . 'DetalheCorpo';
$trilhaDetalheTituloId = $trilha_log_id . 'DetalheTitulo';

ob_start();
?>
<div class="flex flex-col flex-1 min-h-0 overflow-hidden">
    <div id="<?= htmlspecialchars($trilhaCorpoId) ?>" class="flex-1 overflow-y-auto px-6 py-4"></div>
</div>
<?php
$ui_offcanvas_body = ob_get_clean();
$ui_offcanvas_id = $trilha_log_id;
$ui_offcanvas_titulo = $trilha_log_titulo;
$ui_offcanvas_max_w = 'max-w-3xl';
include __DIR__ . '/offcanvas.php';
?>
<div id="<?= htmlspecialchars($trilhaDetalheBackdropId) ?>" class="fixed inset-0 bg-black/40 z-[60] hidden" onclick="<?= htmlspecialchars($trilhaFecharDetalhe) ?>()"></div>
<aside id="<?= htmlspecialchars($trilhaDetalheId) ?>"
       class="fixed top-0 right-0 h-full w-full max-w-lg bg-white shadow-2xl z-[70] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true"
       role="dialog"
       aria-labelledby="<?= htmlspecialchars($trilhaDetalheTituloId) ?>">
    <div class="flex items-center justify-between px-6 py-5 border-b border-gray-200">
        <h2 id="<?= htmlspecialchars($trilhaDetalheTituloId) ?>" class="text-xl font-bold text-gray-900">O que foi alterado</h2>
        <button type="button" onclick="<?= htmlspecialchars($trilhaFecharDetalhe) ?>()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <div id="<?= htmlspecialchars($trilhaDetalheCorpoId) ?>" class="flex-1 overflow-y-auto px-6 py-5"></div>
</aside>
<script>
(function () {
    var cfg = <?= json_encode([
        'url' => $trilha_log_url,
        'vazio' => $trilha_log_vazio,
        'corpo' => $trilhaCorpoId,
        'detalhe' => $trilhaDetalheId,
        'backdrop' => $trilhaDetalheBackdropId,
        'detalheCorpo' => $trilhaDetalheCorpoId,
        'abrirLista' => $trilhaOpen,
        'fecharLista' => $trilhaClose,
        'lista' => $trilha_log_id . 'Drawer',
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var itens = [];

    function texto(valor, classe) {
        var p = document.createElement('p');
        p.className = classe;
        p.textContent = valor;
        return p;
    }

    function fecharDetalhe() {
        var drawer = document.getElementById(cfg.detalhe);
        var backdrop = document.getElementById(cfg.backdrop);
        if (!drawer || !backdrop) return;
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
        backdrop.classList.add('hidden');
    }

    function montarDetalhe(log) {
        var bloco = document.createElement('div');
        var resumo = document.createElement('p');
        resumo.className = 'text-sm text-gray-600 mb-4';
        resumo.textContent = String(log.usuario || 'Não identificado')
            + ' · ' + String(log.registro || '')
            + ' · ' + String(log.acao || '')
            + ' · ' + String(log.quando || '');
        bloco.appendChild(resumo);

        var alteracoes = Array.isArray(log.alteracoes) ? log.alteracoes : [];
        if (alteracoes.length === 0) {
            bloco.appendChild(texto('Nenhum campo diferente do registro anterior.', 'text-sm text-gray-500'));
            return bloco;
        }

        var tabela = document.createElement('table');
        tabela.className = 'min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg';
        var cabeca = document.createElement('thead');
        cabeca.className = 'bg-gray-50';
        var trCabeca = document.createElement('tr');
        var semAntes = alteracoes.every(function (item) { return !item.antes; });
        var soValor = log.acao === 'Criado' || semAntes;
        (soValor ? ['Campo', 'Valor'] : ['Campo', 'Antes', 'Depois']).forEach(function (titulo) {
            var th = document.createElement('th');
            th.className = 'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
            th.textContent = titulo;
            trCabeca.appendChild(th);
        });
        cabeca.appendChild(trCabeca);
        tabela.appendChild(cabeca);
        var corpo = document.createElement('tbody');
        corpo.className = 'bg-white divide-y divide-gray-200';
        alteracoes.forEach(function (item) {
            var tr = document.createElement('tr');
            var valores = soValor ? [item.campo, item.depois] : [item.campo, item.antes, item.depois];
            valores.forEach(function (valor) {
                var td = document.createElement('td');
                td.className = 'px-4 py-3 text-sm text-gray-900 align-top';
                td.textContent = String(valor || '—');
                tr.appendChild(td);
            });
            corpo.appendChild(tr);
        });
        tabela.appendChild(corpo);
        bloco.appendChild(tabela);
        return bloco;
    }

    function abrirDetalhe(indice) {
        var log = itens[indice];
        var corpo = document.getElementById(cfg.detalheCorpo);
        var drawer = document.getElementById(cfg.detalhe);
        var backdrop = document.getElementById(cfg.backdrop);
        if (!log || !corpo || !drawer || !backdrop) return;
        corpo.replaceChildren();
        corpo.appendChild(montarDetalhe(log));
        backdrop.classList.remove('hidden');
        drawer.classList.remove('translate-x-full');
        drawer.setAttribute('aria-hidden', 'false');
    }

    function linha(log, indice) {
        var tr = document.createElement('tr');
        tr.className = 'hover:bg-gray-50';
        [log.usuario || 'Não identificado', log.registro || '', log.acao || '', log.quando || ''].forEach(function (valor) {
            var td = document.createElement('td');
            td.className = 'px-4 py-3 text-sm text-gray-900';
            td.textContent = String(valor);
            tr.appendChild(td);
        });
        var tdBtn = document.createElement('td');
        tdBtn.className = 'px-4 py-3 text-right whitespace-nowrap';
        var botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-medium text-gray-700 bg-white hover:bg-gray-50';
        botao.textContent = 'Ver';
        botao.addEventListener('click', function () { abrirDetalhe(indice); });
        tdBtn.appendChild(botao);
        tr.appendChild(tdBtn);
        return tr;
    }

    function tabela(logs) {
        var quadro = document.createElement('div');
        quadro.className = 'overflow-x-auto border border-gray-200 rounded-lg';
        var tabelaEl = document.createElement('table');
        tabelaEl.className = 'min-w-full divide-y divide-gray-200';
        var cabeca = document.createElement('thead');
        cabeca.className = 'bg-gray-50';
        var trCabeca = document.createElement('tr');
        ['Usuário', 'Registro', 'Ação', 'Data e horário', ''].forEach(function (titulo) {
            var th = document.createElement('th');
            th.className = 'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
            th.textContent = titulo;
            trCabeca.appendChild(th);
        });
        cabeca.appendChild(trCabeca);
        tabelaEl.appendChild(cabeca);
        var corpo = document.createElement('tbody');
        corpo.className = 'bg-white divide-y divide-gray-200';
        logs.forEach(function (log, indice) { corpo.appendChild(linha(log, indice)); });
        tabelaEl.appendChild(corpo);
        quadro.appendChild(tabelaEl);
        return quadro;
    }

    function carregar() {
        var corpo = document.getElementById(cfg.corpo);
        if (!corpo) return;
        corpo.replaceChildren();
        corpo.appendChild(texto('Carregando…', 'text-sm text-gray-500'));
        fetch(cfg.url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            })
            .then(function (resultado) {
                corpo.replaceChildren();
                var logs = resultado.ok && resultado.data && Array.isArray(resultado.data.logs) ? resultado.data.logs : null;
                if (!logs) {
                    corpo.appendChild(texto(
                        (resultado.data && resultado.data.error) ? resultado.data.error : 'Não foi possível carregar os logs.',
                        'text-sm text-red-600'
                    ));
                    return;
                }
                itens = logs;
                if (logs.length === 0) {
                    corpo.appendChild(texto(cfg.vazio, 'text-sm text-gray-500'));
                    return;
                }
                corpo.appendChild(tabela(logs));
            })
            .catch(function () {
                corpo.replaceChildren();
                corpo.appendChild(texto('Não foi possível carregar os logs.', 'text-sm text-red-600'));
            });
    }

    window[<?= json_encode($trilhaAbrir) ?>] = function () {
        if (typeof window[cfg.abrirLista] === 'function') {
            window[cfg.abrirLista]();
        }
        carregar();
    };
    window[<?= json_encode($trilhaFecharDetalhe) ?>] = fecharDetalhe;

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var detalhe = document.getElementById(cfg.detalhe);
        if (detalhe && detalhe.getAttribute('aria-hidden') === 'false') {
            e.stopImmediatePropagation();
            fecharDetalhe();
            return;
        }
        var lista = document.getElementById(cfg.lista);
        if (lista && lista.getAttribute('aria-hidden') === 'false' && typeof window[cfg.fecharLista] === 'function') {
            e.stopImmediatePropagation();
            window[cfg.fecharLista]();
        }
    }, true);
})();
</script>
<?php
unset(
    $trilha_log_id, $trilha_log_titulo, $trilha_log_url, $trilha_log_vazio,
    $trilhaFn, $trilhaAbrir, $trilhaOpen, $trilhaClose, $trilhaFecharDetalhe,
    $trilhaCorpoId, $trilhaDetalheId, $trilhaDetalheBackdropId, $trilhaDetalheCorpoId, $trilhaDetalheTituloId
);
?>
