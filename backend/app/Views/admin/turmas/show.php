<?php
$title = $title ?? 'Detalhes da Turma - EducaTudo';
$user = $user ?? null;
$current_page = $current_page ?? '';
$turma = $turma ?? [];
$alunos = $alunos ?? [];
$csrf_token = $csrf_token ?? '';
$matriculas_schema_ready = (bool)($matriculas_schema_ready ?? false);
$anos_letivo_para_vinculo = is_array($anos_letivo_para_vinculo ?? null) ? $anos_letivo_para_vinculo : [];
$cursoExtra = (($turma['curso_tipo'] ?? 'regular') === 'extra');
$cursoId = (int)($turma['curso_id'] ?? 0);
$turmaId = (int)($turma['id'] ?? 0);
?>

<div class="min-h-screen bg-gradient-to-br from-purple-50 via-indigo-50 to-blue-50">
    <!-- Header -->
    <div class="bg-white/80 backdrop-blur-sm border-b border-purple-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Detalhes da Turma</h1>
                    <p class="mt-2 text-gray-600">Informações completas da turma <?= htmlspecialchars($turma['nome'] ?? '') ?></p>
                </div>
                <div class="flex space-x-3">
                    <a href="<?= URL ?>/admin/turmas/<?= $turma['id'] ?? '' ?>/lista-chamada"
                       class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors flex items-center">
                        Lista de chamada
                    </a>
                    <a href="<?= URL ?>/admin/turmas/<?= $turma['id'] ?? '' ?>/edit" 
                       class="btn-primary-custom px-4 py-2 rounded-lg transition-colors flex items-center hover:opacity-90">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Editar
                    </a>
                    <a href="<?= URL ?>/admin/turmas" 
                       class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Turma Info Card -->
            <div class="lg:col-span-2">
                <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">Informações da Turma</h3>
                            <div class="flex space-x-2">
                                <a href="<?= URL ?>/admin/turmas/<?= $turma['id'] ?? '' ?>/edit" 
                                   class="btn-primary-custom px-4 py-2 rounded-lg transition-colors text-sm hover:opacity-90">
                                    Editar
                                </a>
                                <button onclick="toggleStatus(<?= $turma['id'] ?? 0 ?>, <?= ($turma['ativo'] ?? 0) ? 'false' : 'true' ?>)" 
                                        class="px-4 py-2 <?= ($turma['ativo'] ?? 0) ? 'bg-orange-600 hover:bg-orange-700' : 'bg-green-600 hover:bg-green-700' ?> text-white rounded-lg transition-colors text-sm">
                                    <?= ($turma['ativo'] ?? 0) ? 'Desativar' : 'Ativar' ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Nome da Turma</label>
                                <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($turma['nome'] ?? '') ?></p>
                            </div>
                            
                            
                            <?php if (!empty($turma['curso_nome'])): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Curso</label>
                                <p class="text-lg font-semibold text-gray-900">
                                    <?= htmlspecialchars($turma['curso_nome']) ?>
                                    <?php if ($cursoExtra): ?>
                                    <span class="ml-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800">Extra</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Série</label>
                                <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($turma['serie'] ?? '—') ?></p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Status</label>
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full <?= ($turma['ativo'] ?? 0) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= ($turma['ativo'] ?? 0) ? 'Ativa' : 'Inativa' ?>
                                </span>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Data de Criação</label>
                                <p class="text-lg text-gray-900"><?= isset($turma['created_at']) ? date('d/m/Y H:i', strtotime($turma['created_at'])) : 'N/A' ?></p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Última Atualização</label>
                                <p class="text-lg text-gray-900"><?= isset($turma['updated_at']) ? date('d/m/Y H:i', strtotime($turma['updated_at'])) : 'N/A' ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alunos da Turma -->
                <div class="mt-8 bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
                    <div class="p-6 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-gray-900">Alunos da Turma</h3>
                        <?php if ($matriculas_schema_ready): ?>
                        <button type="button" onclick="abrirModalVincularAluno()" class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90">
                            <i class="fa-solid fa-user-plus mr-2"></i> Vincular aluno
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-6">
                        <?php if (empty($alunos)): ?>
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum aluno nesta turma</h3>
                                <p class="mt-1 text-sm text-gray-500">Os alunos serão exibidos aqui quando forem vinculados à turma.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RA</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vínculo</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($alunos as $aluno): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                            <span class="text-sm font-medium text-blue-600"><?= strtoupper(substr($aluno['nome'] ?? '', 0, 2)) ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($aluno['nome'] ?? '') ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= htmlspecialchars($aluno['ra'] ?? '') ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= htmlspecialchars($aluno['email'] ?? '') ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php $vinculo = $aluno['vinculo_tipo'] ?? 'principal'; ?>
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $vinculo === 'principal' ? 'bg-blue-100 text-blue-800' : 'bg-indigo-100 text-indigo-800' ?>">
                                                    <?= $vinculo === 'principal' ? 'Principal' : 'Matriculado' ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= ($aluno['ativo'] ?? 0) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                    <?= ($aluno['ativo'] ?? 0) ? 'Ativo' : 'Inativo' ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="<?= URL ?>/admin/students/<?= $aluno['id'] ?? '' ?>" 
                                                   class="text-blue-600 hover:text-blue-900 transition-colors">Ver</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Ações Rápidas</h3>
                    <div class="space-y-3">
                        <?php if ($matriculas_schema_ready): ?>
                        <button type="button" onclick="abrirModalVincularAluno()"
                                class="btn-primary-custom w-full flex items-center justify-center px-4 py-2 rounded-lg transition-colors hover:opacity-90">
                            <i class="fa-solid fa-user-plus mr-2"></i>
                            Vincular aluno
                        </button>
                        <?php endif; ?>
                        <?php if ($cursoExtra): ?>
                        <a href="<?= URL ?>/admin/turmas/<?= $turmaId ?>/export-alunos-csv"
                           class="w-full flex items-center justify-center px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors">
                            <i class="fa-solid fa-file-csv mr-2"></i>
                            Exportar alunos (CSV)
                        </a>
                        <?php endif; ?>
                        <?php if ($cursoId > 0): ?>
                        <a href="<?= URL ?>/admin/curso/<?= $cursoId ?>/importar-alunos?turma_id=<?= $turmaId ?>"
                           class="btn-primary-custom w-full flex items-center justify-center px-4 py-2 rounded-lg transition-colors hover:opacity-90">
                            <i class="fa-solid fa-file-import mr-2"></i>
                            Importar / vincular (CSV)
                        </a>
                        <?php endif; ?>
                        <a href="<?= URL ?>/admin/turmas/<?= $turma['id'] ?? '' ?>/edit" 
                           class="btn-primary-custom w-full flex items-center justify-center px-4 py-2 rounded-lg transition-colors hover:opacity-90">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Editar Turma
                        </a>
                        
                        <button onclick="toggleStatus(<?= $turma['id'] ?? 0 ?>, <?= ($turma['ativo'] ?? 0) ? 'false' : 'true' ?>)" 
                                class="w-full flex items-center justify-center px-4 py-2 <?= ($turma['ativo'] ?? 0) ? 'bg-orange-600 hover:bg-orange-700' : 'bg-green-600 hover:bg-green-700' ?> text-white rounded-lg transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path>
                            </svg>
                            <?= ($turma['ativo'] ?? 0) ? 'Desativar' : 'Ativar' ?>
                        </button>
                        
                        <button onclick="deleteTurma(<?= $turma['id'] ?? 0 ?>)" 
                                class="w-full flex items-center justify-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Excluir Turma
                        </button>
                    </div>
                </div>

                <!-- Turma Stats -->
                <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Estatísticas</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Total de Alunos</span>
                            <span class="text-lg font-semibold text-blue-600"><?= $turma['total_alunos'] ?? 0 ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Alunos Ativos</span>
                            <span class="text-lg font-semibold text-green-600"><?= count(array_filter($alunos, fn($a) => $a['ativo'] ?? 0)) ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Alunos Inativos</span>
                            <span class="text-lg font-semibold text-red-600"><?= count(array_filter($alunos, fn($a) => !($a['ativo'] ?? 0))) ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Capacidade Média</span>
                            <span class="text-lg font-semibold text-purple-600">30</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($matriculas_schema_ready): ?>
<div id="modalVincularAluno" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Vincular aluno à turma</h3>
            <button type="button" onclick="fecharModalVincularAluno()" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form id="formVincularAluno" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div>
                <label for="vinc_busca" class="block text-sm font-medium text-gray-700 mb-1">Buscar aluno</label>
                <input type="text" id="vinc_busca" placeholder="Nome, RA, login ou e-mail..." autocomplete="off"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                <p class="mt-1 text-xs text-gray-500">Digite ao menos 1 letra. Use nome completo ou parte do RA/login.</p>
                <div id="vinc_resultados" class="mt-2 max-h-52 overflow-y-auto border border-gray-200 rounded-lg hidden"></div>
                <input type="hidden" id="vinc_aluno_id" name="aluno_id" value="">
                <p id="vinc_aluno_selecionado" class="text-sm text-gray-600 mt-2 hidden"></p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="vinc_ano_letivo_id" class="block text-sm font-medium text-gray-700 mb-1">Ano letivo</label>
                    <select id="vinc_ano_letivo_id" name="ano_letivo_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">Selecione</option>
                        <?php foreach ($anos_letivo_para_vinculo as $al): ?>
                        <option value="<?= (int)$al['id'] ?>"><?= (int)$al['ano'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="vinc_data_entrada" class="block text-sm font-medium text-gray-700 mb-1">Data entrada</label>
                    <input type="date" id="vinc_data_entrada" name="data_entrada" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
            </div>
            <?php if (!$cursoExtra): ?>
            <label class="flex items-start gap-2 cursor-pointer rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                <input type="checkbox" id="vinc_definir_principal" name="definir_turma_principal" value="1" class="mt-0.5 rounded border-gray-300 text-teal-600">
                <span class="text-sm text-gray-700">Definir como turma principal do aluno</span>
            </label>
            <?php else: ?>
            <p class="text-xs text-gray-500 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">Curso extra: a turma principal do aluno <strong>não será alterada</strong>.</p>
            <?php endif; ?>
            <p id="vincMsg" class="text-sm hidden"></p>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="fecharModalVincularAluno()" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700">Cancelar</button>
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg font-semibold hover:opacity-90">Vincular</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Toggle Status
function toggleStatus(id, newStatus) {
    if (confirm(`Tem certeza que deseja ${newStatus ? 'ativar' : 'desativar'} esta turma?`)) {
        fetch(`<?= URL ?>/admin/turmas/${id}/toggle-status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `_token=${encodeURIComponent(<?= json_encode($csrf_token) ?>)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro de conexão. Tente novamente.');
        });
    }
}

// Delete Turma
function deleteTurma(id) {
    if (confirm('Tem certeza que deseja excluir esta turma? Esta ação não pode ser desfeita.')) {
        fetch(`<?= URL ?>/admin/turmas/${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `_token=${encodeURIComponent(<?= json_encode($csrf_token) ?>)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '<?= URL ?>/admin/turmas';
            } else {
                alert('Erro: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro de conexão. Tente novamente.');
        });
    }
}

<?php if ($matriculas_schema_ready): ?>
var vincBuscaTimer = null;
function abrirModalVincularAluno() {
    document.getElementById('formVincularAluno')?.reset();
    document.getElementById('vinc_data_entrada').value = '<?= date('Y-m-d') ?>';
    document.getElementById('vinc_aluno_id').value = '';
    document.getElementById('vinc_aluno_selecionado').classList.add('hidden');
    document.getElementById('vinc_resultados').classList.add('hidden');
    document.getElementById('vincMsg').classList.add('hidden');
    document.getElementById('modalVincularAluno')?.classList.remove('hidden');
}
function fecharModalVincularAluno() {
    document.getElementById('modalVincularAluno')?.classList.add('hidden');
}
function vincEscHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function vincRenderResultados(alunos) {
    var box = document.getElementById('vinc_resultados');
    if (!box) return;

    box.innerHTML = alunos.map(function(a) {
        var meta = [];
        if (a.ra) meta.push('RA ' + a.ra);
        else if (a.codigo_aluno) meta.push('Cód. ' + a.codigo_aluno);
        if (a.nickname) meta.push('@' + a.nickname);
        if (a.email) meta.push(a.email);
        if (a.turma_nome) meta.push('Turma: ' + a.turma_nome);
        if (!a.ativo || a.ativo === '0') meta.push('Inativo');

        var jaVinculado = String(a.ja_vinculado) === '1' || a.ja_vinculado === true;
        var disabled = jaVinculado ? ' disabled opacity-60 cursor-not-allowed' : '';
        var badge = jaVinculado ? '<span class="ml-2 text-xs font-medium text-amber-700">Já na turma</span>' : '';

        return '<button type="button"' + disabled +
            ' class="w-full text-left px-3 py-2.5 text-sm hover:bg-teal-50 border-b border-gray-100 last:border-b-0' + disabled + '"' +
            ' data-id="' + vincEscHtml(a.id) + '"' +
            ' data-nome="' + vincEscHtml(a.nome || '') + '">' +
            '<span class="font-medium text-gray-900">' + vincEscHtml(a.nome || 'Sem nome') + '</span>' + badge +
            (meta.length ? '<span class="block text-xs text-gray-500 mt-0.5">' + vincEscHtml(meta.join(' · ')) + '</span>' : '') +
            '</button>';
    }).join('');

    box.querySelectorAll('button[data-id]:not([disabled])').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('vinc_aluno_id').value = btn.getAttribute('data-id') || '';
            var sel = document.getElementById('vinc_aluno_selecionado');
            sel.textContent = 'Selecionado: ' + (btn.getAttribute('data-nome') || '');
            sel.classList.remove('hidden');
            box.classList.add('hidden');
        });
    });
    box.classList.remove('hidden');
}

document.getElementById('vinc_busca')?.addEventListener('input', function() {
    clearTimeout(vincBuscaTimer);
    var q = this.value.trim();
    var box = document.getElementById('vinc_resultados');
    if (!box) return;

    if (q.length < 1) {
        box.classList.add('hidden');
        box.innerHTML = '';
        return;
    }

    box.classList.remove('hidden');
    box.innerHTML = '<p class="p-3 text-sm text-gray-500">Buscando...</p>';

    vincBuscaTimer = setTimeout(function() {
        fetch('<?= URL ?>/admin/turmas/<?= $turmaId ?>/buscar-alunos?q=' + encodeURIComponent(q), {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function(r) {
            return r.text().then(function(text) {
                var data = null;
                if (text) {
                    try { data = JSON.parse(text); } catch (e) { data = null; }
                }
                return { ok: r.ok, data: data };
            });
        })
        .then(function(result) {
            var data = result.data || {};
            if (!result.ok || data.success === false) {
                box.innerHTML = '<p class="p-3 text-sm text-red-600">' + vincEscHtml(data.error || 'Erro ao buscar alunos.') + '</p>';
                return;
            }
            if (!data.alunos || !data.alunos.length) {
                box.innerHTML = '<p class="p-3 text-sm text-gray-500">Nenhum aluno encontrado para &quot;' + vincEscHtml(q) + '&quot;.</p>';
                return;
            }
            vincRenderResultados(data.alunos);
        })
        .catch(function() {
            box.innerHTML = '<p class="p-3 text-sm text-red-600">Erro de conexão ao buscar alunos.</p>';
        });
    }, 250);
});
document.getElementById('formVincularAluno')?.addEventListener('submit', function(e) {
    e.preventDefault();
    var msg = document.getElementById('vincMsg');
    msg.classList.add('hidden');
    if (!document.getElementById('vinc_aluno_id').value) {
        msg.textContent = 'Selecione um aluno na busca.';
        msg.className = 'text-sm text-red-700';
        msg.classList.remove('hidden');
        return;
    }
    var formData = new FormData(this);
    fetch('<?= URL ?>/admin/turmas/<?= $turmaId ?>/vincular-aluno', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            location.reload();
            return;
        }
        msg.textContent = data.error || 'Erro ao vincular aluno.';
        msg.className = 'text-sm text-red-700';
        msg.classList.remove('hidden');
    })
    .catch(function() {
        msg.textContent = 'Erro de conexão.';
        msg.className = 'text-sm text-red-700';
        msg.classList.remove('hidden');
    });
});
<?php endif; ?>
</script>
