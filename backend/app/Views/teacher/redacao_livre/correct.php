<?php
$displayName = $envio['student_name'] ?: ($envio['aluno_nome'] ?? 'Sem nome');
$contentText = !empty($envio['content_text']) ? $envio['content_text'] : ($envio['ocr_text'] ?? '');
$gradesJson = [];
$teacherGradesJson = [];
if ($correction && !empty($correction['grades_json'])) {
    $decoded = json_decode($correction['grades_json'], true);
    $gradesJson = is_array($decoded) ? $decoded : [];
}
if ($correction && !empty($correction['teacher_grades_json'])) {
    $decoded = json_decode($correction['teacher_grades_json'], true);
    $teacherGradesJson = is_array($decoded) ? $decoded : [];
}
$criteriaDisplay = $criteria;
if (empty($criteriaDisplay) && !empty($gradesJson)) {
    $pos = 0;
    foreach ($gradesJson as $slug => $item) {
        if (!is_string($slug) || $slug === '') continue;
        $pos++;
        $criteriaDisplay[] = ['slug' => $slug, 'name' => 'Competência ' . $pos, 'max_score' => 200, 'order_position' => $pos];
    }
}
if (empty($criteriaDisplay)) {
    $enemNomes = ['Domínio da norma padrão', 'Compreensão do tema', 'Seleção de argumentos', 'Conhecimento dos mecanismos linguísticos', 'Proposta de intervenção'];
    foreach ($enemNomes as $i => $nome) {
        $criteriaDisplay[] = ['slug' => 'competencia_' . ($i + 1), 'name' => $nome, 'max_score' => 200, 'order_position' => $i + 1];
    }
}
$baseUrl = URL . '/professor/redacao-livre/envios/' . (int)$envio['id'];
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Corrigir redação — <?= htmlspecialchars($displayName) ?></h2>
            <p class="text-gray-600">Redação Livre</p>
        </div>
        <a href="<?= URL ?>/professor/redacao-livre" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">Voltar</a>
    </div>
</div>

<style>
    #textoRedacaoLeitura.redacao-pautada, #textareaTextoRedacao.redacao-pautada {
        background-color: #fefefe !important;
        background-image: repeating-linear-gradient( transparent 0px, transparent 27px, rgba(203, 213, 225, 0.7) 27px, rgba(203, 213, 225, 0.7) 28px ) !important;
        background-position: 0 16px !important;
        line-height: 28px !important;
    }
    #textoRedacaoLeitura .paragrafo-redacao { text-indent: 2em; margin-bottom: 0.75em; }
    #textoRedacaoLeitura .paragrafo-redacao:last-child { margin-bottom: 0; }
    #textareaTextoRedacao { text-indent: 2em; }
</style>
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200 p-6 mb-6" id="blocoTextoRedacao">
    <div class="flex justify-between items-center mb-2">
        <h3 class="text-lg font-semibold text-gray-900">Texto da redação</h3>
        <div class="flex items-center gap-2">
            <button type="button" id="btnEditarRedacao" class="inline-flex items-center gap-2 px-3 py-1.5 bg-amber-100 text-amber-800 rounded-lg hover:bg-amber-200 text-sm font-medium"><i class="fas fa-edit"></i> Editar</button>
            <button type="button" id="btnSalvarTextoRedacao" class="hidden inline-flex items-center gap-2 px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium"><i class="fas fa-save"></i> Salvar</button>
            <button type="button" id="btnCancelarEditarRedacao" class="hidden inline-flex items-center gap-2 px-3 py-1.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium">Cancelar</button>
            <?php if (!empty($envio['content_image_path'])): ?>
            <a href="<?= $baseUrl ?>/original" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-3 py-1.5 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 text-sm font-medium"><i class="fas fa-external-link-alt"></i> Visualizar original</a>
            <?php endif; ?>
        </div>
    </div>
    <div id="textoRedacaoLeitura" class="redacao-pautada text-gray-800 border border-gray-200 rounded-lg px-4 py-4 min-h-[400px] max-h-[600px] overflow-y-auto"><?php
        $textoParaExibir = trim($contentText ?? '');
        if ($textoParaExibir === '') echo '<span class="text-gray-500">(Sem texto)</span>';
        else {
            $paragrafos = preg_split('/\n\s*\n/', $textoParaExibir, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($paragrafos as $p) {
                $p = trim($p);
                if ($p !== '') echo '<p class="paragrafo-redacao whitespace-pre-wrap">' . nl2br(htmlspecialchars($p)) . '</p>';
            }
        }
    ?></div>
    <div id="textoRedacaoEdicao" class="hidden">
        <textarea id="textareaTextoRedacao" class="redacao-pautada w-full min-h-[400px] p-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-y font-sans text-gray-800" placeholder="Digite ou edite o texto da redação..."><?= htmlspecialchars($contentText) ?></textarea>
    </div>
</div>
<script>
(function() {
    var envioId = <?= (int)$envio['id'] ?>;
    var token = <?= json_encode($csrf_token) ?>;
    var divLeitura = document.getElementById('textoRedacaoLeitura');
    var divEdicao = document.getElementById('textoRedacaoEdicao');
    var textarea = document.getElementById('textareaTextoRedacao');
    var btnEditar = document.getElementById('btnEditarRedacao');
    var btnSalvar = document.getElementById('btnSalvarTextoRedacao');
    var btnCancelar = document.getElementById('btnCancelarEditarRedacao');
    var textoOriginal = textarea.value;
    function rawTextFromLeitura() {
        if (divLeitura.querySelector('.text-gray-500')) return '';
        var paras = divLeitura.querySelectorAll('.paragrafo-redacao');
        var parts = []; for (var i = 0; i < paras.length; i++) parts.push(paras[i].textContent.trim());
        return parts.join('\n\n');
    }
    function renderParagrafos(t) {
        if (!t || !t.trim()) return '<span class="text-gray-500">(Sem texto)</span>';
        var paras = t.trim().split(/\n\s*\n/);
        var html = '';
        for (var i = 0; i < paras.length; i++) {
            var p = paras[i].trim();
            if (p) html += '<p class="paragrafo-redacao whitespace-pre-wrap">' + p.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>') + '</p>';
        }
        return html || '<span class="text-gray-500">(Sem texto)</span>';
    }
    function entrarEdicao() {
        divLeitura.classList.add('hidden');
        divEdicao.classList.remove('hidden');
        btnEditar.classList.add('hidden');
        btnSalvar.classList.remove('hidden');
        btnCancelar.classList.remove('hidden');
        textarea.value = rawTextFromLeitura();
        textarea.focus();
    }
    function sairEdicao(atualizarLeitura) {
        divLeitura.classList.remove('hidden');
        divEdicao.classList.add('hidden');
        btnEditar.classList.remove('hidden');
        btnSalvar.classList.add('hidden');
        btnCancelar.classList.add('hidden');
        if (atualizarLeitura) divLeitura.innerHTML = renderParagrafos(textarea.value);
    }
    btnEditar.addEventListener('click', function() { entrarEdicao(); });
    btnCancelar.addEventListener('click', function() { textarea.value = textoOriginal; sairEdicao(false); });
    btnSalvar.addEventListener('click', function() {
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
        var fd = new FormData();
        fd.append('_token', token);
        fd.append('content_text', textarea.value);
        fetch('<?= $baseUrl ?>/atualizar-texto', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Salvar';
                if (d.success) { textoOriginal = textarea.value; sairEdicao(true); } else alert(d.error || 'Erro ao salvar');
            })
            .catch(function() { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Salvar'; alert('Erro de conexão'); });
    });
})();
</script>

<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200 p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Correção (modelo ENEM)</h3>
    <div class="flex flex-wrap gap-3 mb-4">
        <button type="button" id="btnRunAI" class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700">Gerar correção por IA</button>
        <button type="button" id="btnCorrecaoProfessor" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Correção professor</button>
    </div>
    <div id="correctionResult" class="hidden mb-4 p-4 rounded-lg bg-green-50 border border-green-200 text-green-800"></div>
    <?php
    $hasTeacherData = $correction && (isset($correction['teacher_grades_json']) && $correction['teacher_grades_json'] !== '' && $correction['teacher_grades_json'] !== '[]');
    $hasAiCorrection = $correction && !empty(trim($correction['grades_json'] ?? '')) && trim($correction['grades_json']) !== '[]';
    $showCorrectionBlock = $correction && ($hasTeacherData || $hasAiCorrection);
    $aiTotal = isset($correction['ai_total_score']) ? (float)$correction['ai_total_score'] : null;
    if ($aiTotal === null && !empty($gradesJson)) {
        $aiTotal = 0;
        foreach ($gradesJson as $item) {
            if (is_array($item) && isset($item['score'])) $aiTotal += (float)$item['score'];
            elseif (is_numeric($item)) $aiTotal += (float)$item;
        }
    }
    $teacherTotal = isset($correction['teacher_total_score']) ? (float)$correction['teacher_total_score'] : null;
    $displayTotal = isset($correction['total_score']) ? (float)$correction['total_score'] : null;
    ?>
    <div id="blocoCorrecaoProfessor" class="<?= $showCorrectionBlock ? '' : 'hidden' ?>">
    <form id="correctionForm" class="space-y-6">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="mb-4 p-3 bg-blue-50 rounded-lg border border-blue-100">
            <div class="flex items-center gap-2">
                <input type="checkbox" id="use_average" name="use_average" value="1" <?= ($correction && !empty($correction['use_average'])) ? 'checked' : '' ?> class="h-4 w-4 text-purple-600 rounded">
                <label for="use_average" class="text-sm font-medium text-gray-700">Usar média entre nota da IA e minha nota</label>
            </div>
        </div>
        <div id="resumoNotas" class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="text-center p-3 bg-amber-50 rounded-lg border border-amber-200">
                <p class="text-xs font-medium text-amber-800 uppercase tracking-wide">Nota da IA</p>
                <p id="displayNotaIa" class="text-2xl font-bold text-amber-700"><?= $aiTotal !== null ? number_format($aiTotal, 0, ',', '.') : '—' ?></p>
            </div>
            <div class="text-center p-3 bg-blue-50 rounded-lg border border-blue-200">
                <p class="text-xs font-medium text-blue-800 uppercase tracking-wide">Minha nota</p>
                <p id="displayMinhaNota" class="text-2xl font-bold text-blue-700"><?= $teacherTotal !== null ? number_format($teacherTotal, 0, ',', '.') : '—' ?></p>
            </div>
            <div class="text-center p-3 bg-green-50 rounded-lg border border-green-200">
                <p class="text-xs font-medium text-green-800 uppercase tracking-wide">Nota final</p>
                <p id="displayMedia" class="text-2xl font-bold text-green-700"><?= $displayTotal !== null ? number_format($displayTotal, 0, ',', '.') : '—' ?></p>
            </div>
        </div>
        <h4 class="text-md font-semibold text-gray-800 border-b pb-2">Competências</h4>
        <?php $pos = 0; foreach ($criteriaDisplay as $c):
            $slug = $c['slug'];
            $name = $c['name'];
            $max = (float)$c['max_score'];
            $pos++;
            $val = $teacherGradesJson[$slug] ?? $gradesJson[$slug] ?? null;
            $scoreVal = ''; $feedbackVal = '';
            if (is_array($val)) { $scoreVal = isset($val['score']) ? $val['score'] : (isset($val['nota']) ? $val['nota'] : ''); $feedbackVal = $val['feedback'] ?? $val['explicacao'] ?? ''; }
            elseif ($val !== null && $val !== '') $scoreVal = $val;
            $notaIa = null;
            if (!empty($gradesJson[$slug])) { $g = $gradesJson[$slug]; $notaIa = is_array($g) && isset($g['score']) ? (float)$g['score'] : (is_numeric($g) ? (float)$g : null); }
        ?>
        <div class="border-l-4 border-blue-200 pl-4 py-2">
            <label for="grade_<?= htmlspecialchars($slug) ?>" class="block text-sm font-medium text-gray-900">Competência <?= $pos ?>: <?= htmlspecialchars($name) ?> (máx. <?= number_format($max, 0, ',', '.') ?>)</label>
            <div class="mt-1 flex flex-wrap items-center gap-4">
                <?php if ($notaIa !== null): ?><span class="text-xs text-amber-700 font-medium">Nota da IA: <?= number_format($notaIa, 0, ',', '.') ?></span><?php endif; ?>
                <input type="number" step="0.01" id="grade_<?= htmlspecialchars($slug) ?>" name="grade_<?= htmlspecialchars($slug) ?>" value="<?= htmlspecialchars($scoreVal) ?>" class="w-24 px-3 py-2 border border-gray-300 rounded-lg" min="0" max="<?= $max ?>" placeholder="0–<?= (int)$max ?>">
            </div>
            <textarea id="feedback_<?= htmlspecialchars($slug) ?>" name="feedback_<?= htmlspecialchars($slug) ?>" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Feedback..."><?= htmlspecialchars($feedbackVal) ?></textarea>
        </div>
        <?php endforeach; ?>
        <div>
            <label for="feedback_text" class="block text-sm font-medium text-gray-700">Comentários gerais</label>
            <textarea id="feedback_text" name="feedback_text" rows="4" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Comentários ao aluno..."><?= $correction ? htmlspecialchars($correction['feedback_text'] ?? '') : '' ?></textarea>
        </div>
        <div>
            <label for="suggestions_text" class="block text-sm font-medium text-gray-700">Sugestões de melhoria</label>
            <textarea id="suggestions_text" name="suggestions_text" rows="4" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg bg-green-50/50" placeholder="Sugestões..."><?= $correction ? htmlspecialchars($correction['suggestions_text'] ?? '') : '' ?></textarea>
        </div>
        <div id="formError" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"></div>
        <div id="formSuccess" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg"></div>
        <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Salvar correção</button>
    </form>
    </div>
</div>

<?php include __DIR__ . '/../../components/ai-job-poller.php'; ?>
<script>
(function() {
    var envioId = <?= (int)$envio['id'] ?>;
    var token = document.querySelector('input[name="_token"]').value;
    var aiTotal = <?= $aiTotal !== null ? json_encode((float)$aiTotal) : 'null' ?>;
    var baseUrl = <?= json_encode($baseUrl) ?>;
    function updateResumo() {
        var displayMinha = document.getElementById('displayMinhaNota');
        var displayMedia = document.getElementById('displayMedia');
        if (!displayMinha || !displayMedia) return;
        var sum = 0;
        document.querySelectorAll('input[name^="grade_"]').forEach(function(inp) { var v = parseFloat(inp.value); if (!isNaN(v)) sum += v; });
        displayMinha.textContent = sum > 0 ? sum.toLocaleString('pt-BR', { maximumFractionDigits: 0 }) : '—';
        var useAverage = document.getElementById('use_average') && document.getElementById('use_average').checked;
        if (useAverage && aiTotal != null) displayMedia.textContent = ((aiTotal + sum) / 2).toLocaleString('pt-BR', { maximumFractionDigits: 1 });
        else if (sum > 0) displayMedia.textContent = sum.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
        else displayMedia.textContent = aiTotal != null ? aiTotal.toLocaleString('pt-BR', { maximumFractionDigits: 0 }) : '—';
    }
    document.querySelectorAll('input[name^="grade_"]').forEach(function(inp) { inp.addEventListener('input', updateResumo); inp.addEventListener('change', updateResumo); });
    var useAvg = document.getElementById('use_average');
    if (useAvg) useAvg.addEventListener('change', updateResumo);
    updateResumo();
    document.getElementById('btnCorrecaoProfessor').addEventListener('click', function() { var b = document.getElementById('blocoCorrecaoProfessor'); if (b) b.classList.toggle('hidden'); });
    document.getElementById('btnRunAI').addEventListener('click', function() {
        var btn = this;
        btn.disabled = true;
        btn.textContent = 'Enviando...';
        var fd = new FormData();
        fd.append('_token', token);
        fetch(baseUrl + '/corrigir-ia', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.job_id) {
                    btn.disabled = false;
                    btn.textContent = 'Gerar correção por IA';
                    alert(d.error || 'Erro ao enfileirar correção');
                    return;
                }
                btn.textContent = 'Processando...';
                new AIJobPoller(d.job_id, {
                    statusUrl: '<?= URL ?>/professor/ai-job/{id}/status',
                    onDone: function() { window.location.reload(); },
                    onFailed: function(err) {
                        btn.disabled = false;
                        btn.textContent = 'Gerar correção por IA';
                        alert('Falha na correção por IA: ' + err);
                    }
                });
            })
            .catch(function() { btn.disabled = false; btn.textContent = 'Gerar correção por IA'; alert('Erro de conexão'); });
    });
    document.getElementById('correctionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        document.getElementById('formError').classList.add('hidden');
        document.getElementById('formSuccess').classList.add('hidden');
        var fd = new FormData(this);
        fetch(baseUrl + '/salvar-correcao', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) { document.getElementById('formSuccess').textContent = d.message; document.getElementById('formSuccess').classList.remove('hidden'); }
                else { document.getElementById('formError').textContent = d.error || 'Erro'; document.getElementById('formError').classList.remove('hidden'); }
            })
            .catch(function() { document.getElementById('formError').textContent = 'Erro de conexão'; document.getElementById('formError').classList.remove('hidden'); });
    });
})();
</script>
