<?php
$aula = $aula ?? null;
$editing = !empty($aula);
$arquivos = $aula_arquivos ?? [];
$selectedTurmas = [];
if (!empty($aula['turmas']) && is_array($aula['turmas'])) {
    foreach ($aula['turmas'] as $t) {
        $selectedTurmas[] = (int) ($t['id'] ?? 0);
    }
}
?>

<!-- Cabeçalho DS com voltar -->
<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/aulas-online"
           class="text-gray-500 hover:text-gray-700 flex-shrink-0" aria-label="Voltar">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">
                <?= $editing ? 'Editar aula online' : 'Nova aula online' ?>
            </h2>
            <p class="text-gray-600 text-sm">
                <?= $editing ? 'Altere os dados da aula e salve.' : 'Preencha os dados para criar uma nova aula online.' ?>
            </p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../_partials/flash_message.php'; ?>

<?php if ($editing): ?>
<form id="form-sync-gravacao" method="post" action="<?= URL ?>/admin/aulas-online/sincronizar-gravacoes" class="hidden">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
    <input type="hidden" name="id" value="<?= (int) ($aula['id'] ?? 0) ?>">
</form>
<?php endif; ?>

<!-- Formulário -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
    <form method="post"
          action="<?= URL . ($editing ? '/admin/aulas-online/atualizar' : '/admin/aulas-online/salvar') ?>"
          class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= (int) ($aula['id'] ?? 0) ?>">
        <?php endif; ?>

        <!-- Dados básicos -->
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <h3 class="md:col-span-2 text-base font-semibold text-gray-800">Informações da aula</h3>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Título <span class="text-red-500">*</span></label>
                <input type="text" name="titulo" required maxlength="255"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       value="<?= htmlspecialchars((string) ($aula['titulo'] ?? '')) ?>">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                <textarea name="descricao" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"><?= htmlspecialchars((string) ($aula['descricao'] ?? '')) ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Plataforma</label>
                <?php $plataformaAtual = (string) ($aula['plataforma'] ?? ''); ?>
                <select name="plataforma" id="plataforma"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <option value="">Selecione</option>
                    <option value="Panda Video Live" <?= $plataformaAtual === 'Panda Video Live' ? 'selected' : '' ?>>Panda Video Live</option>
                    <option value="Jitsi Meet" <?= $plataformaAtual === 'Jitsi Meet' ? 'selected' : '' ?>>Jitsi Meet</option>
                    <option value="Google Meet" <?= $plataformaAtual === 'Google Meet' ? 'selected' : '' ?>>Google Meet</option>
                    <option value="Zoom" <?= $plataformaAtual === 'Zoom' ? 'selected' : '' ?>>Zoom</option>
                    <option value="YouTube Live" <?= $plataformaAtual === 'YouTube Live' ? 'selected' : '' ?>>YouTube Live</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Link da aula</label>
                <input type="url" id="link_aula" name="link_aula"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       value="<?= htmlspecialchars((string) ($aula['link_aula'] ?? '')) ?>">
                <p class="mt-1 text-xs text-gray-500">Panda e JaaS/Jitsi configurado preenchem automaticamente.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Início <span class="text-red-500">*</span></label>
                <input type="datetime-local" name="inicio_em" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       value="<?= !empty($aula['inicio_em']) ? date('Y-m-d\TH:i', strtotime((string) $aula['inicio_em'])) : '' ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Fim</label>
                <input type="datetime-local" name="fim_em"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       value="<?= !empty($aula['fim_em']) ? date('Y-m-d\TH:i', strtotime((string) $aula['fim_em'])) : '' ?>">
            </div>
        </div>

        <!-- Turmas -->
        <div class="p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-4">Turmas</h3>
            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 mb-3 cursor-pointer">
                <input type="checkbox" id="enviar_para_todos" name="enviar_para_todos" value="1"
                       class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                       <?= !empty($aula['enviar_para_todos']) ? 'checked' : '' ?>>
                Disponível para todas as turmas
            </label>
            <div id="box-turmas" class="grid grid-cols-1 md:grid-cols-3 gap-2 mt-2">
                <?php foreach (($turmas ?? []) as $turma): ?>
                    <?php $tid = (int) ($turma['id'] ?? 0); ?>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" name="turmas[]" value="<?= $tid ?>"
                               class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                               <?= in_array($tid, $selectedTurmas, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars((string) ($turma['nome'] ?? '')) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Configurações -->
        <div class="p-6 space-y-4">
            <h3 class="text-base font-semibold text-gray-800">Configurações</h3>

            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                <input type="checkbox" name="publicado" value="1"
                       class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                       <?= !isset($aula['publicado']) || !empty($aula['publicado']) ? 'checked' : '' ?>>
                Publicar agora (visível no aluno)
            </label>

            <!-- Integração Panda/Jitsi -->
            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 space-y-2">
                <?php $gerarPandaChecked = $editing ? !empty($aula['gerar_panda']) : true; ?>
                <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-800 cursor-pointer">
                    <input type="checkbox" id="gerar_panda" name="gerar_panda" value="1"
                           class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                           <?= $gerarPandaChecked ? 'checked' : '' ?>>
                    Gerar live automaticamente via API Panda Video
                </label>
                <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                    <input type="checkbox" id="reusar_config_panda" name="reusar_config_panda" value="1"
                           class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                           <?= !$editing ? 'checked' : '' ?>>
                    Reusar configuração OBS/stream key Panda
                </label>
                <p class="text-xs <?= !empty($panda_configured) ? 'text-emerald-700' : 'text-red-600' ?>">
                    <i class="fa-solid <?= !empty($panda_configured) ? 'fa-circle-check' : 'fa-circle-xmark' ?> mr-1"></i>
                    <?= !empty($panda_configured) ? 'Integração Panda configurada.' : 'Integração Panda não configurada no .env.' ?>
                </p>
                <p class="text-xs <?= !empty($jaas_configured) ? 'text-emerald-700' : 'text-gray-500' ?>">
                    <i class="fa-solid <?= !empty($jaas_configured) ? 'fa-circle-check' : 'fa-circle-info' ?> mr-1"></i>
                    <?= !empty($jaas_configured) ? 'JaaS/Jitsi configurado para JWT automático.' : 'Salas abertas no meet.launs.com.br (modo padrão). Configure JaaS em Dev Settings para JWT/gravação.' ?>
                </p>
            </div>

            <!-- URL de gravação manual (só ao editar) -->
            <?php if ($editing): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fa-solid fa-circle-play mr-1 text-emerald-600"></i>
                    URL da gravação
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                    <input type="url" name="link_gravacao"
                           class="flex-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                           placeholder="https://meet.launs.com.br/gravacoes/..."
                           value="<?= htmlspecialchars((string) ($aula['link_gravacao'] ?? '')) ?>">
                    <button type="submit" form="form-sync-gravacao"
                            class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors whitespace-nowrap">
                        <i class="fa-solid fa-cloud-arrow-down mr-2 text-gray-500"></i>
                        Buscar gravação
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-500">Cole a URL do vídeo gravado ou busque automaticamente na API. O aluno verá automaticamente após o fim da aula.</p>
            </div>
            <?php endif; ?>

            <!-- Recorrência (só ao criar) -->
            <?php if (!$editing): ?>
            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 space-y-3">
                <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-800 cursor-pointer">
                    <input type="checkbox" id="recorrente_semanal" name="recorrente_semanal" value="1"
                           class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                    Repetir semanalmente (mesmo dia e horário)
                </label>
                <div id="box-recorrencia" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Quantidade de aulas <span class="text-red-500">*</span>
                    </label>
                    <input type="number" min="2" max="52" id="recorrencia_semanas" name="recorrencia_semanas"
                           value="4" disabled
                           class="w-32 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <p class="mt-2 text-xs text-gray-500">
                        <i class="fa-solid fa-circle-info mr-1 text-blue-500"></i>
                        Todas as aulas serão criadas e ficam visíveis ao aluno, porém bloqueadas até o dia e horário de cada uma.
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Rodapé -->
        <div class="px-6 py-4 bg-gray-50 rounded-b-xl flex items-center gap-3">
            <button type="submit"
                    class="btn-primary-custom inline-flex items-center px-5 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                <i class="fa-solid <?= $editing ? 'fa-floppy-disk' : 'fa-plus' ?> mr-2"></i>
                <?= $editing ? 'Salvar alterações' : 'Criar aula online' ?>
            </button>
            <a href="<?= URL ?>/admin/aulas-online"
               class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                Cancelar
            </a>
        </div>
    </form>
</div>

<!-- Arquivos da aula (só ao editar) -->
<?php if ($editing): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
        <h3 class="text-lg font-semibold text-gray-900">Documentos e arquivos</h3>
        <label class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold cursor-pointer hover:opacity-90 transition-colors shadow-sm">
            <i class="fa-solid fa-upload mr-2"></i>
            Enviar arquivo
            <input type="file" id="input-upload-arquivo" class="hidden"
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.jpg,.jpeg,.png,.gif,.webp">
        </label>
    </div>
    <div class="p-6">
        <div id="upload-progress" class="hidden mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Enviando arquivo...
        </div>
        <div id="lista-arquivos">
            <?php if (empty($arquivos)): ?>
                <p class="text-sm text-gray-400" id="msg-sem-arquivos">
                    <i class="fa-solid fa-folder-open mr-2"></i>
                    Nenhum arquivo vinculado a esta aula.
                </p>
            <?php else: ?>
                <?php foreach ($arquivos as $arq): ?>
                    <?php include __DIR__ . '/_arquivo_item.php'; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    const chkTodos = document.getElementById('enviar_para_todos');
    const box = document.getElementById('box-turmas');
    const chkPanda = document.getElementById('gerar_panda');
    const campoLink = document.getElementById('link_aula');
    const plataforma = document.getElementById('plataforma');
    const chkReusarPanda = document.getElementById('reusar_config_panda');
    const chkRecorrencia = document.getElementById('recorrente_semanal');
    const campoSemanas = document.getElementById('recorrencia_semanas');
    const boxRecorrencia = document.getElementById('box-recorrencia');

    if (chkTodos && box) {
        function updateTurmasState() {
            const disable = chkTodos.checked;
            box.style.opacity = disable ? '0.4' : '1';
            box.querySelectorAll('input[type="checkbox"]').forEach(el => el.disabled = disable);
        }
        chkTodos.addEventListener('change', updateTurmasState);
        updateTurmasState();
    }

    function updatePandaState() {
        if (!chkPanda || !campoLink) return;
        const panda = chkPanda.checked;
        const jitsiAuto = plataforma && plataforma.value === 'Jitsi Meet';
        campoLink.required = !panda && !jitsiAuto;
        if (!panda && chkReusarPanda) chkReusarPanda.checked = false;
        if (chkReusarPanda) chkReusarPanda.disabled = !panda;
        if (panda && plataforma && plataforma.value !== 'Panda Video Live') plataforma.value = 'Panda Video Live';
    }
    if (chkPanda) { chkPanda.addEventListener('change', updatePandaState); updatePandaState(); }
    if (plataforma) {
        plataforma.addEventListener('change', function () {
            if (plataforma.value === 'Jitsi Meet' && chkPanda) chkPanda.checked = false;
            updatePandaState();
        });
    }

    if (chkRecorrencia && campoSemanas && boxRecorrencia) {
        function updateRecorrenciaState() {
            const on = chkRecorrencia.checked;
            boxRecorrencia.classList.toggle('hidden', !on);
            campoSemanas.disabled = !on;
            if (!on) campoSemanas.value = '1';
            if (on && parseInt(campoSemanas.value || '0', 10) < 2) campoSemanas.value = '4';
        }
        chkRecorrencia.addEventListener('change', updateRecorrenciaState);
        updateRecorrenciaState();
    }

    // Upload de arquivos
    const inputUpload = document.getElementById('input-upload-arquivo');
    const progressEl = document.getElementById('upload-progress');
    const listaEl = document.getElementById('lista-arquivos');

    if (inputUpload) {
        inputUpload.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('_token', '<?= htmlspecialchars($csrf_token ?? '') ?>');
            fd.append('aula_id', '<?= (int)($aula['id'] ?? 0) ?>');
            fd.append('arquivo', file);
            const BASE = '<?= rtrim(URL ?? '', "/") ?>';

            progressEl.classList.remove('hidden');
            fetch(BASE + '/admin/aulas-online/arquivo/upload', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    progressEl.classList.add('hidden');
                    inputUpload.value = '';
                    if (!data.success) { alert(data.error || 'Erro ao enviar arquivo.'); return; }
                    const msgVazia = document.getElementById('msg-sem-arquivos');
                    if (msgVazia) msgVazia.remove();
                    const div = document.createElement('div');
                    div.className = 'flex items-center justify-between py-2 border-b border-gray-100 last:border-0';
                    div.id = 'arq-' + data.id;
                    div.innerHTML = '<div class="flex items-center gap-3 min-w-0"><i class="fa-solid fa-file text-gray-400 flex-shrink-0"></i><span class="text-sm text-gray-800 truncate">' + data.nome + '</span></div>'
                        + '<div class="flex items-center gap-3 flex-shrink-0">'
                        + '<a href="' + BASE + '/admin/aulas-online/arquivo/download?id=' + data.id + '" class="text-xs text-blue-600 hover:underline">Baixar</a>'
                        + '<button type="button" onclick="excluirArquivo(' + data.id + ')" class="text-xs text-red-600 hover:underline">Excluir</button>'
                        + '</div>';
                    listaEl.appendChild(div);
                })
                .catch(() => { progressEl.classList.add('hidden'); alert('Erro de conexão.'); inputUpload.value = ''; });
        });
    }

    window.excluirArquivo = function (id) {
        if (!confirm('Excluir este arquivo?')) return;
        const fd = new FormData();
        fd.append('_token', '<?= htmlspecialchars($csrf_token ?? '') ?>');
        fd.append('arquivo_id', id);
        const BASE = '<?= rtrim(URL ?? '', "/") ?>';
        fetch(BASE + '/admin/aulas-online/arquivo/excluir', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) { const el = document.getElementById('arq-' + id); if (el) el.remove(); }
                else alert(data.error || 'Erro ao excluir.');
            });
    };
})();
</script>
