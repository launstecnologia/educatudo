<?php
$title = $title ?? 'Grade Horária - EducaTudo';
$user = $user ?? null;
$current_page = $current_page ?? 'grade_horaria';
$itens = $itens ?? [];
$dias_semana = $dias_semana ?? [];
$turmas = $turmas ?? [];
$turmas_filtro = $turmas_filtro ?? $turmas;
$professores = $professores ?? [];
$materias = $materias ?? [];
$tipos_ensino = $tipos_ensino ?? [];
$series = $series ?? [];
$filtros = $filtros ?? [];
$csrf_token = $csrf_token ?? '';
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<div class="grade-no-print">
<?php
ob_start();
?>
<button type="button" onclick="document.getElementById('modal-imagem-ia').classList.remove('hidden')"
        class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-regular fa-image mr-2 text-gray-500"></i>
    Importar por imagem
</button>
<button type="button" onclick="openAulaDrawer()"
        class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
    <i class="fa-solid fa-plus mr-2"></i>
    Nova aula
</button>
<?php
$page_header_actions = ob_get_clean();
$page_header_title = 'Grade Horária de Aulas';
$page_header_subtitle = 'Organize as aulas da escola em uma visão semanal.';
include __DIR__ . '/../_partials/page_header_list.php';
?>

<?php if ($success_message): ?>
    <div class="mb-6 p-4 rounded-lg bg-green-100 text-green-800 border border-green-200"><?= htmlspecialchars($success_message) ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="mb-6 p-4 rounded-lg bg-red-100 text-red-800 border border-red-200"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>
</div>

<?php include __DIR__ . '/_conteudo.php'; ?>

<!-- Criar/Editar aula em drawer lateral -->
<div id="aulaDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeAulaDrawer()"></div>
<aside id="aulaDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="aulaDrawerTitle" class="text-xl font-bold text-gray-900">Nova Aula</h2>
        <button type="button" onclick="closeAulaDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="aula-form" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" id="aula_id" value="">

        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <div id="aula-form-erro" class="hidden text-sm rounded-lg p-3 bg-red-100 text-red-800"></div>

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Dados da Aula</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label for="aula_dia_semana" class="block text-sm font-medium text-gray-700 mb-1">Dia da Semana *</label>
                        <select id="aula_dia_semana" name="dia_semana" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Selecione</option>
                            <?php foreach ($dias_semana as $num => $nome): ?>
                                <option value="<?= (int) $num ?>"><?= htmlspecialchars($nome) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="aula_periodo" class="block text-sm font-medium text-gray-700 mb-1">Período *</label>
                        <select id="aula_periodo" name="periodo" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="manha">Manhã</option>
                            <option value="tarde">Tarde</option>
                        </select>
                    </div>
                    <div>
                        <label for="aula_horario_de" class="block text-sm font-medium text-gray-700 mb-1">Horário de *</label>
                        <input type="time" id="aula_horario_de" name="horario_de" required value="07:00"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="aula_horario_ate" class="block text-sm font-medium text-gray-700 mb-1">Horário até *</label>
                        <input type="time" id="aula_horario_ate" name="horario_ate" required value="08:00"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="aula_turma_id" class="block text-sm font-medium text-gray-700 mb-1">Turma *</label>
                        <select id="aula_turma_id" name="turma_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Selecione a turma</option>
                            <?php foreach ($turmas as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="aula_professor_id" class="block text-sm font-medium text-gray-700 mb-1">Professor *</label>
                        <select id="aula_professor_id" name="professor_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Selecione o professor</option>
                            <?php foreach ($professores as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="aula_materia_id" class="block text-sm font-medium text-gray-700 mb-1">Matéria *</label>
                        <select id="aula_materia_id" name="materia_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Selecione a matéria</option>
                            <?php foreach ($materias as $m): ?>
                                <option value="<?= (int) $m['id'] ?>"><?= htmlspecialchars($m['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>
        </div>

        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeAulaDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="aula-form-submit-label">Salvar</span>
            </button>
        </div>
    </form>
</aside>

<!-- Modal: Importar por imagem (IA) - passo 1: upload / passo 2: preview e salvar -->
<div id="modal-imagem-ia" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 py-6">
        <div class="fixed inset-0 bg-black/50" onclick="fecharModalImportacao()"></div>
        <div id="modal-imagem-ia-box" class="relative bg-white rounded-xl shadow-xl w-full max-w-6xl max-h-[95vh] flex flex-col">
            <!-- Overlay de loading (spinner) -->
            <div id="loading-ia-overlay" class="hidden absolute inset-0 z-10 flex flex-col items-center justify-center bg-white/90 rounded-xl">
                <style>.loading-spinner-ia{animation:loading-spin-ia .8s linear infinite;}@keyframes loading-spin-ia{to{transform:rotate(360deg);}}</style>
                <div class="loading-spinner-ia w-12 h-12 border-4 border-indigo-200 border-t-indigo-600 rounded-full"></div>
                <p id="loading-ia-text" class="mt-3 text-sm font-medium text-gray-700">Processando...</p>
            </div>
            <!-- Passo 1: Upload -->
            <div id="painel-upload-ia" class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Importar grade por imagem (IA)</h3>
                <p class="text-sm text-gray-600 mb-4">Envie, arraste ou cole um print da grade horária. A IA extrairá os dados em segundo plano e você poderá conferir antes de salvar.</p>
                <form id="form-imagem-ia" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Imagem (JPG, PNG, WEBP)</label>
                        <div id="dropzone-grade-ia" tabindex="0"
                             class="rounded-xl border-2 border-dashed border-blue-300 bg-blue-50/40 px-4 py-6 text-center transition-colors focus:outline-none focus:ring-2 focus:ring-blue-300">
                            <input id="input-imagem-ia" type="file" name="imagem" accept="image/jpeg,image/png,image/webp" required class="hidden">
                            <div id="estado-vazio-imagem-ia">
                                <svg class="mx-auto h-9 w-9 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <path d="M17 8 12 3 7 8"></path>
                                    <path d="M12 3v12"></path>
                                </svg>
                                <p class="mt-3 text-sm font-semibold text-gray-900">Clique, arraste ou cole um print aqui</p>
                                <p class="mt-1 text-xs text-gray-500">Use Ctrl/Cmd+V para colar uma imagem copiada.</p>
                            </div>
                            <div id="preview-imagem-ia" class="hidden">
                                <img id="thumb-imagem-ia" src="" alt="Prévia da imagem enviada" class="mx-auto max-h-40 rounded-lg border border-gray-200 bg-white object-contain shadow-sm">
                                <div class="mt-3 flex items-center justify-center gap-3 text-sm">
                                    <span id="nome-imagem-ia" class="font-medium text-gray-700"></span>
                                    <button type="button" onclick="event.stopPropagation(); limparImagemIA()" class="text-red-600 hover:text-red-700">Remover</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="resultado-ia" class="hidden text-sm rounded-lg p-3"></div>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="fecharModalImportacao()"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</button>
                        <button type="submit" id="btn-processar" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Processar</button>
                    </div>
                </form>
            </div>

            <!-- Passo 2: Preview para conferir e salvar -->
            <div id="painel-preview-ia" class="hidden flex flex-col flex-1 min-h-0 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Conferir antes de salvar</h3>
                <p class="text-sm text-gray-600 mb-4">Verifique os dados extraídos. Marque as linhas que deseja incluir e clique em Salvar.</p>
                <div class="flex-1 overflow-auto border border-gray-200 rounded-lg mb-4">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left w-10"></th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">Dia</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">Horário</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">Turma</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">Professor</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">Matéria</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">Período</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">Status</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-preview-ia" class="divide-y divide-gray-200 bg-white">
                        </tbody>
                    </table>
                </div>
                <div id="resumo-preview-ia" class="text-sm text-gray-600 mb-4"></div>
                <div id="resultado-salvar-ia" class="hidden text-sm rounded-lg p-3 mb-4"></div>
                <div class="flex justify-end gap-2 flex-shrink-0">
                    <button type="button" onclick="voltarUploadIA()"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Enviar outra imagem</button>
                    <button type="button" id="btn-salvar-importacao" onclick="salvarImportacaoIA()"
                            class="btn-primary-custom px-4 py-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:opacity-90">
                        Salvar <span id="contador-salvar-ia">0</span> aula(s)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var previewItensIA = [];
var diasSemanaIA = {};
var turmasIA = [];
var professoresIA = [];
var materiasIA = [];
var csrfTokenIA = '<?= htmlspecialchars($csrf_token) ?>';
var urlBase = '<?= URL ?>';
var pollingGradeIA = null;

// ---- Criar/Editar aula (drawer) ----
function showAulaDrawer() {
    document.getElementById('aulaDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('aulaDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeAulaDrawer() {
    document.getElementById('aulaDrawerBackdrop').classList.add('hidden');
    var drawer = document.getElementById('aulaDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function openAulaDrawer(id) {
    var form = document.getElementById('aula-form');
    form.reset();
    document.getElementById('aula_id').value = '';
    document.getElementById('aula-form-erro').classList.add('hidden');

    if (!id) {
        form.dataset.mode = 'create';
        document.getElementById('aulaDrawerTitle').textContent = 'Nova Aula';
        document.getElementById('aula-form-submit-label').textContent = 'Salvar';
        showAulaDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    document.getElementById('aulaDrawerTitle').textContent = 'Editar Aula';
    document.getElementById('aula-form-submit-label').textContent = 'Salvar Alterações';
    showAulaDrawer();

    fetch(urlBase + '/admin/grade-horaria/' + id + '/dados')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                alert('Erro: ' + (data.error || 'Não foi possível carregar a aula.'));
                closeAulaDrawer();
                return;
            }
            var item = data.item;
            document.getElementById('aula_id').value = item.id;
            document.getElementById('aula_dia_semana').value = item.dia_semana;
            document.getElementById('aula_periodo').value = item.periodo;
            document.getElementById('aula_horario_de').value = item.horario_de;
            document.getElementById('aula_horario_ate').value = item.horario_ate;
            document.getElementById('aula_turma_id').value = item.turma_id;
            document.getElementById('aula_professor_id').value = item.professor_id;
            document.getElementById('aula_materia_id').value = item.materia_id;
        })
        .catch(function() {
            alert('Erro de conexão.');
            closeAulaDrawer();
        });
}

document.getElementById('aula-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var mode = this.dataset.mode;
    var id = document.getElementById('aula_id').value;
    var url = mode === 'create' ? (urlBase + '/admin/grade-horaria') : (urlBase + '/admin/grade-horaria/' + id);
    var erroEl = document.getElementById('aula-form-erro');
    erroEl.classList.add('hidden');
    fetch(url, { method: 'POST', body: new FormData(this) })
        .then(function(r) { return r.json(); })
        .then(function(result) {
            if (result.success) {
                window.location.reload();
            } else {
                erroEl.textContent = result.error || 'Erro ao salvar.';
                erroEl.classList.remove('hidden');
            }
        })
        .catch(function() {
            erroEl.textContent = 'Erro de conexão. Tente novamente.';
            erroEl.classList.remove('hidden');
        });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAulaDrawer();
    }
});

function fecharModalImportacao() {
    if (pollingGradeIA) {
        clearTimeout(pollingGradeIA);
        pollingGradeIA = null;
    }
    esconderLoadingIA();
    document.getElementById('modal-imagem-ia').classList.add('hidden');
}

function voltarUploadIA() {
    document.getElementById('painel-preview-ia').classList.add('hidden');
    document.getElementById('painel-upload-ia').classList.remove('hidden');
    document.getElementById('resultado-salvar-ia').classList.add('hidden');
    limparImagemIA();
}

function setArquivoImagemIA(file) {
    if (!file || !file.type || file.type.indexOf('image/') !== 0) {
        return;
    }
    var input = document.getElementById('input-imagem-ia');
    var dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    document.getElementById('estado-vazio-imagem-ia').classList.add('hidden');
    document.getElementById('preview-imagem-ia').classList.remove('hidden');
    document.getElementById('nome-imagem-ia').textContent = file.name || 'imagem-colada.png';
    var reader = new FileReader();
    reader.onload = function(ev) {
        document.getElementById('thumb-imagem-ia').src = ev.target.result;
    };
    reader.readAsDataURL(file);
}

function limparImagemIA() {
    var input = document.getElementById('input-imagem-ia');
    input.value = '';
    document.getElementById('thumb-imagem-ia').src = '';
    document.getElementById('nome-imagem-ia').textContent = '';
    document.getElementById('preview-imagem-ia').classList.add('hidden');
    document.getElementById('estado-vazio-imagem-ia').classList.remove('hidden');
}

function optsSelect(lista, valorAtual) {
    var html = '<option value="">Selecione...</option>';
    (lista || []).forEach(function(o) {
        var v = (o.id || o).toString();
        html += '<option value="' + escapeHtml(v) + '"' + (v === String(valorAtual) ? ' selected' : '') + '>' + escapeHtml(o.nome || o) + '</option>';
    });
    return html;
}

function renderPreview(itens, dias_semana, turmas, professores, materias) {
    previewItensIA = itens;
    diasSemanaIA = dias_semana || {};
    turmasIA = turmas || [];
    professoresIA = professores || [];
    materiasIA = materias || [];
    var tbody = document.getElementById('tbody-preview-ia');
    tbody.innerHTML = '';
    var podeInserirCount = 0;
    itens.forEach(function(item, idx) {
        var tr = document.createElement('tr');
        tr.className = item.pode_inserir ? 'hover:bg-gray-50' : 'bg-amber-50';
        tr.dataset.idx = idx;
        var check = '';
        if (item.pode_inserir) {
            podeInserirCount++;
            check = '<input type="checkbox" class="cb-preview-ia rounded border-gray-300" data-idx="' + idx + '" checked>';
        } else {
            check = '<input type="checkbox" class="cb-preview-ia cb-manual-ia rounded border-gray-300 hidden" data-idx="' + idx + '" checked>';
        }
        var diaNome = diasSemanaIA[item.dia_semana] || item.dia_semana;
        var celTurma, celProf, celMat, status;
        if (item.pode_inserir) {
            celTurma = escapeHtml(item.turma_nome || '');
            celProf = escapeHtml(item.professor_nome || '');
            celMat = escapeHtml(item.materia_nome || '');
            status = '<span class="text-green-700 font-medium">OK</span>';
        } else {
            celTurma = '<select class="sel-turma-ia w-full text-xs border border-gray-300 rounded px-2 py-1" data-idx="' + idx + '">' + optsSelect(turmasIA, item.turma_id) + '</select>';
            celProf = '<select class="sel-professor-ia w-full text-xs border border-gray-300 rounded px-2 py-1" data-idx="' + idx + '">' + optsSelect(professoresIA, item.professor_id) + '</select>';
            celMat = '<select class="sel-materia-ia w-full text-xs border border-gray-300 rounded px-2 py-1" data-idx="' + idx + '">' + optsSelect(materiasIA, item.materia_id) + '</select>';
            status = '<span class="text-amber-700 text-xs" id="status-manual-' + idx + '">Selecione turma, professor e matéria</span>';
        }
        tr.innerHTML =
            '<td class="px-3 py-2">' + check + '</td>' +
            '<td class="px-3 py-2">' + escapeHtml(diaNome) + '</td>' +
            '<td class="px-3 py-2">' + escapeHtml((item.horario_de || '').substring(0,5)) + ' – ' + escapeHtml((item.horario_ate || '').substring(0,5)) + '</td>' +
            '<td class="px-3 py-2">' + celTurma + '</td>' +
            '<td class="px-3 py-2">' + celProf + '</td>' +
            '<td class="px-3 py-2">' + celMat + '</td>' +
            '<td class="px-3 py-2">' + (item.periodo === 'tarde' ? 'Tarde' : 'Manhã') + '</td>' +
            '<td class="px-3 py-2">' + status + '</td>';
        tbody.appendChild(tr);
    });
    atualizarResumoContador();
}

function onSelectManuaisChange(ev) {
    var el = ev.target;
    if (!el.classList.contains('sel-turma-ia') && !el.classList.contains('sel-professor-ia') && !el.classList.contains('sel-materia-ia')) return;
    var idx = parseInt(el.dataset.idx, 10);
    var item = previewItensIA[idx];
    if (!item || item.pode_inserir) return;
    var turmaEl = document.querySelector('.sel-turma-ia[data-idx="' + idx + '"]');
    var profEl = document.querySelector('.sel-professor-ia[data-idx="' + idx + '"]');
    var matEl = document.querySelector('.sel-materia-ia[data-idx="' + idx + '"]');
    item.turma_id = turmaEl && turmaEl.value ? parseInt(turmaEl.value, 10) : null;
    item.professor_id = profEl && profEl.value ? parseInt(profEl.value, 10) : null;
    item.materia_id = matEl && matEl.value ? parseInt(matEl.value, 10) : null;
    var completo = item.turma_id && item.professor_id && item.materia_id;
    var cb = document.querySelector('.cb-manual-ia[data-idx="' + idx + '"]');
    var statusEl = document.getElementById('status-manual-' + idx);
    if (cb) {
        if (completo) { cb.classList.remove('hidden'); cb.checked = true; if (statusEl) statusEl.innerHTML = '<span class="text-green-700 font-medium">Pronto</span>'; }
        else { cb.classList.add('hidden'); cb.checked = false; if (statusEl) statusEl.textContent = 'Selecione turma, professor e matéria'; }
    }
    atualizarResumoContador();
}

function atualizarResumoContador() {
    var totalProntas = 0;
    var totalCheckadas = 0;
    document.querySelectorAll('.cb-preview-ia:checked').forEach(function(cb) {
        var idx = parseInt(cb.dataset.idx, 10);
        var item = previewItensIA[idx];
        if (item && item.turma_id && item.professor_id && item.materia_id) {
            totalProntas++;
            totalCheckadas++;
        }
    });
    var totalCompletos = 0;
    previewItensIA.forEach(function(item) {
        if (item.pode_inserir || (item.turma_id && item.professor_id && item.materia_id)) totalCompletos++;
    });
    document.getElementById('contador-salvar-ia').textContent = totalCheckadas;
    document.getElementById('btn-salvar-importacao').disabled = totalCheckadas === 0;
    var semMatch = previewItensIA.length - totalCompletos;
    document.getElementById('resumo-preview-ia').textContent =
        previewItensIA.length + ' aula(s) extraída(s). ' + totalCompletos + ' pronta(s) para salvar.' +
        (semMatch > 0 ? ' ' + semMatch + ' com turma/professor/matéria não encontrado(s) — selecione manualmente nos dropdowns.' : '');
}

function escapeHtml(s) {
    if (!s) return '';
    var div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
}

function atualizarContadorSalvar() {
    atualizarResumoContador();
}

function salvarImportacaoIA() {
    var selecionados = [];
    document.querySelectorAll('.cb-preview-ia:checked').forEach(function(cb) {
        var idx = parseInt(cb.dataset.idx, 10);
        var item = previewItensIA[idx];
        if (item && item.turma_id && item.professor_id && item.materia_id) {
            selecionados.push({
                dia_semana: item.dia_semana,
                horario_de: item.horario_de,
                horario_ate: item.horario_ate,
                turma_id: item.turma_id,
                professor_id: item.professor_id,
                materia_id: item.materia_id,
                periodo: item.periodo
            });
        }
    });
    if (selecionados.length === 0) return;
    var btn = document.getElementById('btn-salvar-importacao');
    var resultado = document.getElementById('resultado-salvar-ia');
    resultado.classList.add('hidden');
    btn.disabled = true;
    btn.innerHTML = 'Salvando...';
    mostrarLoadingIA('Salvando aulas...');
    fetch(urlBase + '/admin/grade-horaria/salvar-importacao-ia', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ _token: csrfTokenIA, itens: selecionados })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        resultado.classList.remove('hidden');
        if (data.success) {
            resultado.className = 'text-sm rounded-lg p-3 bg-green-100 text-green-800';
            resultado.textContent = data.message || data.inseridos + ' aula(s) adicionada(s).';
            esconderLoadingIA();
            setTimeout(function() { window.location.reload(); }, 1200);
        } else {
            esconderLoadingIA();
            resultado.className = 'text-sm rounded-lg p-3 bg-red-100 text-red-800';
            resultado.textContent = data.error || 'Erro ao salvar.';
            btn.disabled = false;
            btn.innerHTML = 'Salvar <span id="contador-salvar-ia">' + selecionados.length + '</span> aula(s)';
        }
    })
    .catch(function() {
        esconderLoadingIA();
        resultado.classList.remove('hidden');
        resultado.className = 'text-sm rounded-lg p-3 bg-red-100 text-red-800';
        resultado.textContent = 'Erro de conexão. Tente novamente.';
        btn.disabled = false;
        btn.innerHTML = 'Salvar <span id="contador-salvar-ia">' + selecionados.length + '</span> aula(s)';
    });
}

document.getElementById('painel-preview-ia').addEventListener('change', function(ev) {
    if (ev.target && ev.target.classList.contains('cb-preview-ia')) atualizarContadorSalvar();
    else onSelectManuaisChange(ev);
});
function mostrarLoadingIA(texto) {
    var el = document.getElementById('loading-ia-overlay');
    var txt = document.getElementById('loading-ia-text');
    if (txt) txt.textContent = texto || 'Processando...';
    if (el) el.classList.remove('hidden');
}
function esconderLoadingIA() {
    var el = document.getElementById('loading-ia-overlay');
    if (el) el.classList.add('hidden');
}

function finalizarProcessamentoGradeIA(data, btn, resultado) {
    esconderLoadingIA();
    btn.disabled = false;
    btn.textContent = 'Processar';
    if (data.success && data.itens && data.itens.length > 0) {
        document.getElementById('painel-upload-ia').classList.add('hidden');
        document.getElementById('painel-preview-ia').classList.remove('hidden');
        renderPreview(data.itens, data.dias_semana, data.turmas, data.professores, data.materias);
    } else if (data.success && (!data.itens || data.itens.length === 0)) {
        resultado.classList.remove('hidden');
        resultado.className = 'text-sm rounded-lg p-3 bg-amber-100 text-amber-800';
        resultado.textContent = 'Nenhuma aula extraída da imagem. Tente outra imagem.';
    } else {
        resultado.classList.remove('hidden');
        resultado.className = 'text-sm rounded-lg p-3 bg-red-100 text-red-800';
        resultado.textContent = data.error || 'Erro ao processar.';
    }
}

function consultarJobGradeIA(jobId, btn, resultado, tentativa) {
    tentativa = tentativa || 1;
    mostrarLoadingIA(tentativa < 4 ? 'Lendo imagem com OpenAI...' : 'Estruturando grade e conferindo cadastros...');
    fetch(urlBase + '/admin/ai-job/' + jobId + '/status')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.status === 'done') {
                finalizarProcessamentoGradeIA(data.result || {}, btn, resultado);
                return;
            }
            if (data.status === 'failed' || data.status === 'error' || data.status === 'not_found') {
                finalizarProcessamentoGradeIA({ success: false, error: data.error || 'Falha ao processar imagem.' }, btn, resultado);
                return;
            }
            pollingGradeIA = setTimeout(function() {
                consultarJobGradeIA(jobId, btn, resultado, tentativa + 1);
            }, 2000);
        })
        .catch(function() {
            pollingGradeIA = setTimeout(function() {
                consultarJobGradeIA(jobId, btn, resultado, tentativa + 1);
            }, 2500);
        });
}

var dropzoneGradeIA = document.getElementById('dropzone-grade-ia');
var inputImagemIA = document.getElementById('input-imagem-ia');
dropzoneGradeIA.addEventListener('click', function() { inputImagemIA.click(); });
dropzoneGradeIA.addEventListener('dragover', function(ev) {
    ev.preventDefault();
    dropzoneGradeIA.classList.add('border-blue-500', 'bg-blue-50');
});
dropzoneGradeIA.addEventListener('dragleave', function() {
    dropzoneGradeIA.classList.remove('border-blue-500', 'bg-blue-50');
});
dropzoneGradeIA.addEventListener('drop', function(ev) {
    ev.preventDefault();
    dropzoneGradeIA.classList.remove('border-blue-500', 'bg-blue-50');
    if (ev.dataTransfer.files && ev.dataTransfer.files[0]) {
        setArquivoImagemIA(ev.dataTransfer.files[0]);
    }
});
inputImagemIA.addEventListener('change', function() {
    if (inputImagemIA.files && inputImagemIA.files[0]) {
        setArquivoImagemIA(inputImagemIA.files[0]);
    }
});
document.addEventListener('paste', function(ev) {
    if (document.getElementById('modal-imagem-ia').classList.contains('hidden')) {
        return;
    }
    var items = ev.clipboardData && ev.clipboardData.items ? Array.from(ev.clipboardData.items) : [];
    for (var i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image/') === 0) {
            var file = items[i].getAsFile();
            if (file) {
                setArquivoImagemIA(new File([file], 'print-grade-horaria.png', { type: file.type || 'image/png' }));
                ev.preventDefault();
                break;
            }
        }
    }
});

document.getElementById('form-imagem-ia').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('btn-processar');
    var resultado = document.getElementById('resultado-ia');
    resultado.classList.add('hidden');
    if (!inputImagemIA.files || !inputImagemIA.files[0]) {
        resultado.classList.remove('hidden');
        resultado.className = 'text-sm rounded-lg p-3 bg-amber-100 text-amber-800';
        resultado.textContent = 'Selecione, arraste ou cole uma imagem da grade horária.';
        return;
    }
    btn.disabled = true;
    btn.textContent = 'Processando...';
    mostrarLoadingIA('Processando imagem...');
    var formData = new FormData(this);
    fetch(urlBase + '/admin/grade-horaria/processar-imagem-ia', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success && data.job_id) {
            consultarJobGradeIA(data.job_id, btn, resultado, 1);
        } else {
            finalizarProcessamentoGradeIA(data, btn, resultado);
        }
    })
    .catch(function() {
        esconderLoadingIA();
        resultado.classList.remove('hidden');
        resultado.className = 'text-sm rounded-lg p-3 bg-red-100 text-red-800';
        resultado.textContent = 'Erro de conexão. Tente novamente.';
        btn.disabled = false;
        btn.textContent = 'Processar';
    });
});
</script>
