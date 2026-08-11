<?php
$sugestoes = $sugestoes ?? [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$page_header_title = 'IA Auditora';
$page_header_subtitle = 'Pergunte em linguagem natural sobre a conformidade da escola. As respostas usam os dados reais do sistema.';
include __DIR__ . '/../_partials/page_header_list.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <div class="lg:col-span-3">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col" style="min-height: 480px;">
            <div id="iaChat" class="flex-1 p-6 space-y-4 overflow-y-auto">
                <div class="flex gap-3">
                    <span class="flex-shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-full bg-green-100 text-green-700"><i class="fa-solid fa-robot"></i></span>
                    <div class="bg-gray-50 rounded-lg px-4 py-3 text-sm text-gray-700">
                        Olá! Sou a IA auditora. Pergunte sobre documentação, Censo, frequência, diário, BNCC, calendário ou pendências.
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-200 p-4">
                <form id="iaForm" class="flex gap-3">
                    <input type="text" id="iaPergunta" autocomplete="off" placeholder="Digite sua pergunta..." class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <button type="submit" class="inline-flex items-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors">
                        <i class="fa-solid fa-paper-plane mr-2"></i> Perguntar
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Sugestões</h3>
            <div class="space-y-2">
                <?php foreach ($sugestoes as $s): ?>
                    <button type="button" class="ia-sugestao w-full text-left text-sm text-gray-600 hover:text-green-700 hover:bg-green-50 rounded-lg px-3 py-2 transition-colors"><?= htmlspecialchars($s) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var chat = document.getElementById('iaChat');
    var form = document.getElementById('iaForm');
    var input = document.getElementById('iaPergunta');
    var csrf = <?= json_encode((string) ($csrf_token ?? '')) ?>;

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

    function addUser(text) {
        chat.insertAdjacentHTML('beforeend',
            '<div class="flex gap-3 justify-end"><div class="bg-green-600 text-white rounded-lg px-4 py-3 text-sm max-w-lg">' + esc(text) +
            '</div><span class="flex-shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-full bg-gray-200 text-gray-600"><i class="fa-solid fa-user"></i></span></div>');
        chat.scrollTop = chat.scrollHeight;
    }
    function addBot(data) {
        var itens = '';
        if (data.itens && data.itens.length) {
            itens = '<ul class="mt-2 space-y-1 list-disc list-inside text-gray-600">';
            data.itens.forEach(function (i) { itens += '<li>' + esc(i) + '</li>'; });
            itens += '</ul>';
        }
        chat.insertAdjacentHTML('beforeend',
            '<div class="flex gap-3"><span class="flex-shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-full bg-green-100 text-green-700"><i class="fa-solid fa-robot"></i></span>' +
            '<div class="bg-gray-50 rounded-lg px-4 py-3 text-sm text-gray-700 max-w-2xl">' + esc(data.resposta) + itens + '</div></div>');
        chat.scrollTop = chat.scrollHeight;
    }

    function perguntar(text) {
        if (!text.trim()) return;
        addUser(text);
        input.value = '';
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('pergunta', text);
        fetch('<?= URL ?>/admin/conformidade/ia/perguntar', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.ok) { addBot(d); }
                else { addBot({ resposta: (d && d.error) ? d.error : 'Não foi possível responder.' }); }
            })
            .catch(function () { addBot({ resposta: 'Erro de conexão. Tente novamente.' }); });
    }

    form.addEventListener('submit', function (e) { e.preventDefault(); perguntar(input.value); });
    document.querySelectorAll('.ia-sugestao').forEach(function (b) {
        b.addEventListener('click', function () { perguntar(this.textContent); });
    });
})();
</script>
