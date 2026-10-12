<?php
$voltarUrl = URL . '/monitor/dashboard' . (!empty($bloco_id) ? ('?bloco_id=' . (int)$bloco_id) : '');
?>
<?php if (!empty($provas_canceladas)): ?>
<div class="mb-6 rounded-xl border-2 border-red-400 bg-red-50 p-5 shadow-sm">
    <div class="flex items-start gap-3">
        <span class="text-3xl" aria-hidden="true">⚠️</span>
        <div>
            <h2 class="text-lg font-bold text-red-800">Prova cancelada — modo seguro</h2>
            <p class="text-red-700 mt-1 text-sm">
                Este aluno saiu do modo seguro durante a prova. A realização foi cancelada e é necessária
                <strong>liberação da coordenação</strong> para tentar novamente.
            </p>
            <ul class="mt-3 space-y-1 text-sm text-red-800">
                <?php foreach ($provas_canceladas as $pc): ?>
                <li>• <?= htmlspecialchars($pc['prova_titulo'] ?? 'Prova') ?>
                    <?php if (!empty($pc['iniciado_em'])): ?>
                        (início <?= date('d/m H:i', strtotime($pc['iniciado_em'])) ?>)
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="mb-6">
    <a href="<?= $voltarUrl ?>" class="text-teal-600 hover:text-teal-800 text-sm font-medium">← Voltar ao painel</a>
    <h1 class="text-2xl font-bold text-gray-900 mt-2"><?= htmlspecialchars($aluno['nome']) ?></h1>
    <p class="text-gray-600">
        RA: <?= htmlspecialchars($aluno['ra'] ?? '-') ?> •
        Turma: <?= htmlspecialchars($aluno['turma_nome'] ?? '-') ?>
    </p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow p-6 border-l-4 border-teal-500">
        <h2 class="font-semibold text-gray-900 mb-3">Status agora</h2>
        <?php if ($sessao_ativa): ?>
            <p class="text-green-700 font-medium flex items-center gap-2">
                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> Online
            </p>
            <?php if (!empty($sessao_ativa['contexto_label'])): ?>
                <p class="text-sm text-gray-700 mt-2"><?= htmlspecialchars($sessao_ativa['contexto_label']) ?></p>
            <?php endif; ?>
            <?php if (!empty($sessao_ativa['contexto_tipo'])): ?>
                <p class="text-xs text-gray-500 mt-1">Tipo: <?= htmlspecialchars($sessao_ativa['contexto_tipo']) ?></p>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-gray-500">Offline ou sem sessão ativa</p>
        <?php endif; ?>
    </div>
    <div class="bg-white rounded-xl shadow p-6 lg:col-span-2">
        <h2 class="font-semibold text-gray-900 mb-3">Ações rápidas</h2>
        <button type="button" id="btn-reset-senha"
                class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
            Resetar senha para 123456
        </button>
        <p class="text-xs text-gray-500 mt-2">É necessário informar sua senha de monitor para confirmar.</p>
        <p id="msg-reset" class="text-sm mt-2 hidden"></p>
    </div>
</div>

<?php if (!empty($provas_andamento)): ?>
<div class="bg-white rounded-xl shadow mb-8 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="font-semibold text-gray-900">Provas / simulados</h2>
    </div>
    <div class="divide-y divide-gray-100">
        <?php foreach ($provas_andamento as $pr): ?>
        <?php $cancelada = ($pr['status'] ?? '') === 'cancelada'; ?>
        <div class="px-6 py-4 flex flex-wrap items-center justify-between gap-2 <?= $cancelada ? 'bg-red-50' : '' ?>">
            <div>
                <p class="font-medium text-gray-900">
                    <?php if ($cancelada): ?><span class="text-red-600 mr-1">⚠</span><?php endif; ?>
                    <?= htmlspecialchars($pr['prova_titulo'] ?? 'Prova') ?>
                </p>
                <p class="text-sm text-gray-500">
                    Status: <span class="font-medium <?= $cancelada ? 'text-red-600' : 'text-teal-600' ?>">
                        <?= $cancelada ? 'Cancelada (modo seguro)' : htmlspecialchars($pr['status']) ?>
                    </span>
                    <?php if (!empty($pr['materia_nome'])): ?> • <?= htmlspecialchars($pr['materia_nome']) ?><?php endif; ?>
                </p>
            </div>
            <a href="<?= URL ?>/monitor/aluno/<?= (int)$aluno['id'] ?>/prova/<?= (int)$pr['prova_id'] ?>"
               class="text-teal-600 hover:text-teal-800 text-sm font-medium">Ver respostas →</a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
        <h2 class="font-semibold text-gray-900">Jornadas</h2>
    </div>
    <?php if (empty($jornadas)): ?>
        <p class="px-6 py-8 text-gray-500 text-center">Nenhuma atividade de jornada registrada.</p>
    <?php else: ?>
        <div class="divide-y divide-gray-100">
            <?php
            $badgeCores = [
                'fez' => 'bg-green-100 text-green-800',
                'viu' => 'bg-amber-100 text-amber-800',
                'nao_viu' => 'bg-gray-100 text-gray-600',
            ];
            foreach ($jornadas as $j):
                $cod = $j['codigo'] ?? 'nao_viu';
                $badge = $badgeCores[$cod] ?? $badgeCores['nao_viu'];
                $qs = !empty($bloco_id) ? ('?bloco_id=' . (int)$bloco_id) : '';
            ?>
            <div class="px-6 py-4 flex justify-between items-center gap-4">
                <div>
                    <p class="font-medium text-gray-900"><?= htmlspecialchars($j['titulo']) ?></p>
                    <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full <?= $badge ?>">
                        <?= htmlspecialchars($j['label'] ?? '') ?>
                    </span>
                </div>
                <a href="<?= URL ?>/monitor/aluno/<?= (int)$aluno['id'] ?>/jornada/<?= (int)$j['id'] ?><?= $qs ?>"
                   class="text-teal-600 hover:text-teal-800 text-sm font-medium whitespace-nowrap">Ver detalhes →</a>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div id="modal-reset-senha" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" aria-hidden="true">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6" role="dialog" aria-labelledby="modal-reset-titulo">
        <h2 id="modal-reset-titulo" class="text-lg font-bold text-gray-900 mb-2">Confirmar reset de senha</h2>
        <p class="text-sm text-gray-600 mb-4">
            A senha de <strong><?= htmlspecialchars($aluno['nome']) ?></strong> será alterada para <strong>123456</strong>.
            Digite sua senha de monitor para confirmar.
        </p>
        <form id="form-reset-senha" class="space-y-4">
            <div>
                <label for="senha-monitor-reset" class="block text-sm font-medium text-gray-700 mb-1">Sua senha</label>
                <input type="password" id="senha-monitor-reset" name="senha_monitor" required autocomplete="current-password"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-teal-500">
            </div>
            <p id="modal-reset-erro" class="text-sm text-red-600 hidden"></p>
            <div class="flex gap-3 justify-end">
                <button type="button" id="btn-cancelar-reset"
                        class="px-4 py-2 text-gray-600 hover:text-gray-800 text-sm font-medium">Cancelar</button>
                <button type="submit" id="btn-confirmar-reset"
                        class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    Confirmar reset
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const modal = document.getElementById('modal-reset-senha');
    const form = document.getElementById('form-reset-senha');
    const inputSenha = document.getElementById('senha-monitor-reset');
    const erroModal = document.getElementById('modal-reset-erro');
    const msg = document.getElementById('msg-reset');

    function abrirModal() {
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        erroModal.classList.add('hidden');
        erroModal.textContent = '';
        inputSenha.value = '';
        inputSenha.focus();
    }

    function fecharModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.getElementById('btn-reset-senha')?.addEventListener('click', abrirModal);
    document.getElementById('btn-cancelar-reset')?.addEventListener('click', fecharModal);
    modal?.addEventListener('click', function(e) {
        if (e.target === modal) fecharModal();
    });

    form?.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-confirmar-reset');
        btn.disabled = true;
        erroModal.classList.add('hidden');

        const fd = new FormData();
        fd.append('_token', '<?= htmlspecialchars($csrf_token) ?>');
        fd.append('senha_monitor', inputSenha.value);

        fetch('<?= URL ?>/monitor/aluno/<?= (int)$aluno['id'] ?>/senha', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(r => r.json()).then(function(data) {
            btn.disabled = false;
            if (data.success) {
                fecharModal();
                msg.classList.remove('hidden');
                msg.className = 'text-sm mt-2 text-green-700';
                msg.textContent = data.message || 'Senha resetada.';
            } else {
                erroModal.textContent = data.error || 'Erro ao resetar senha.';
                erroModal.classList.remove('hidden');
            }
        }).catch(function() {
            btn.disabled = false;
            erroModal.textContent = 'Erro de rede.';
            erroModal.classList.remove('hidden');
        });
    });
})();
</script>
