<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Redação Livre</h2>
            <p class="text-gray-600">Envie redações para corrigir sem criar proposta ou jornada. Vincule ao aluno pelo nome no arquivo ou manualmente.</p>
        </div>
    </div>
</div>

<!-- Upload -->
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200 p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Enviar redação(ões)</h3>
    <form id="formUpload" class="space-y-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome do aluno (opcional; pode vir do nome do arquivo)</label>
                <input type="text" name="student_name" id="student_name" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-purple-500 focus:border-purple-500" placeholder="Ex.: João Silva">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Vincular à sala (opcional)</label>
                <select name="turma_id" id="turma_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="">— Selecionar —</option>
                    <?php foreach ($turmas as $t): ?>
                    <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Arquivo(s) — imagem ou PDF (vários de uma vez)</label>
            <input type="file" name="arquivos[]" id="arquivos" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
            <p class="text-xs text-gray-500 mt-1">O nome do arquivo pode ser usado para sugerir o aluno (ex.: joao_silva.pdf)</p>
        </div>
        <div class="border-t border-gray-200 pt-4">
            <p class="text-sm text-gray-600 mb-2">Ou digite/cole o texto da redação (sem arquivo):</p>
            <textarea name="content_text" id="content_text" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-purple-500 focus:border-purple-500" placeholder="Texto da redação..."></textarea>
        </div>
        <button type="submit" id="btnEnviar" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Enviar</button>
    </form>
    <div id="uploadResult" class="mt-4 hidden p-4 rounded-lg bg-green-50 border border-green-200 text-green-800"></div>
</div>

<!-- Lista de envios -->
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Redações enviadas</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno / Arquivo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sala</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($envios)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                            Nenhuma redação enviada. Use o formulário acima para enviar arquivo(s) ou texto.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($envios as $e): 
                        $nome = $e['student_name'] ?: ($e['aluno_nome'] ?? $e['original_filename'] ?? 'Sem nome');
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm">
                            <span class="font-medium text-gray-900"><?= htmlspecialchars($nome) ?></span>
                            <?php if ($e['original_filename']): ?>
                            <br><span class="text-gray-500 text-xs"><?= htmlspecialchars($e['original_filename']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($e['turma_nome'] ?? '—') ?></td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($e['created_at'])) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="<?= URL ?>/professor/redacao-livre/envios/<?= (int)$e['id'] ?>/corrigir" class="text-purple-600 hover:text-purple-900 font-medium">Corrigir</a>
                            <span class="text-gray-300 mx-1">|</span>
                            <button type="button" class="text-red-600 hover:text-red-800 font-medium excluir-envio" data-envio-id="<?= (int)$e['id'] ?>" data-csrf="<?= htmlspecialchars($csrf_token) ?>">Excluir</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function() {
    var form = document.getElementById('formUpload');
    var btn = document.getElementById('btnEnviar');
    var resultDiv = document.getElementById('uploadResult');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var files = document.getElementById('arquivos').files;
        var text = document.getElementById('content_text').value.trim();
        if (files.length === 0 && text === '') {
            alert('Envie pelo menos um arquivo ou digite o texto da redação.');
            return;
        }
        var fd = new FormData(form);
        if (files.length === 0) fd.delete('arquivos[]');
        btn.disabled = true;
        btn.textContent = 'Enviando...';
        resultDiv.classList.add('hidden');
        fetch('<?= URL ?>/professor/redacao-livre/upload', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                btn.disabled = false;
                btn.textContent = 'Enviar';
                if (d.success) {
                    resultDiv.textContent = d.message + ' ' + (d.results.filter(function(x){ return x.ok; }).length) + ' item(ns).';
                    resultDiv.classList.remove('hidden');
                    resultDiv.classList.add('bg-green-50', 'text-green-800');
                    resultDiv.classList.remove('bg-red-50', 'text-red-800');
                    document.getElementById('content_text').value = '';
                    document.getElementById('arquivos').value = '';
                    setTimeout(function(){ location.reload(); }, 1200);
                } else {
                    resultDiv.textContent = d.error || 'Erro ao enviar';
                    resultDiv.classList.remove('hidden');
                    resultDiv.classList.add('bg-red-50', 'text-red-800');
                    resultDiv.classList.remove('bg-green-50', 'text-green-800');
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.textContent = 'Enviar';
                resultDiv.textContent = 'Erro de conexão';
                resultDiv.classList.remove('hidden');
                resultDiv.classList.add('bg-red-50', 'text-red-800');
            });
    });

    document.querySelectorAll('.excluir-envio').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('Excluir esta redação? Esta ação não pode ser desfeita.')) return;
            var id = this.getAttribute('data-envio-id');
            var token = this.getAttribute('data-csrf');
            var row = this.closest('tr');
            var fd = new FormData();
            fd.append('_token', token);
            fetch('<?= URL ?>/professor/redacao-livre/envios/' + id + '/excluir', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success) {
                        location.reload();
                    } else {
                        alert(d.error || 'Erro ao excluir');
                    }
                })
                .catch(function() { alert('Erro de conexão'); });
        });
    });
})();
</script>
