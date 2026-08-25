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
$vagas_resumo = $vagas_resumo ?? null;
$fila_espera = $fila_espera ?? [];
$totalAlunos = (int) ($turma['total_alunos'] ?? count($alunos));
$alunosAtivos = count(array_filter($alunos, static fn ($a) => !empty($a['ativo'])));
$alunosInativos = count(array_filter($alunos, static fn ($a) => empty($a['ativo'])));
$vagasLabel = 'Ilimitado';
if (is_array($vagas_resumo) && empty($vagas_resumo['ilimitado'])) {
    $vagasLabel = (int) ($vagas_resumo['ocupadas'] ?? 0) . '/' . (int) ($vagas_resumo['vagas'] ?? 0);
}

$page_header_title = 'Detalhes da Turma';
$page_header_subtitle = (string) ($turma['nome'] ?? '');
ob_start();
?>
<a href="<?= URL ?>/admin/turmas"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-arrow-left mr-2 text-gray-500"></i>
    Voltar
</a>
<a href="<?= URL ?>/admin/turmas/<?= $turmaId ?>/lista-chamada"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-clipboard-list mr-2 text-gray-500"></i>
    Lista de chamada
</a>
<a href="<?= URL ?>/admin/turmas?edit=<?= $turmaId ?>"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-pen mr-2"></i>
    Editar
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
?>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mb-6">
    <?php
    $resumoCards = [
        ['label' => 'Total de alunos', 'value' => (string) $totalAlunos, 'icon' => 'fa-users', 'valueClass' => 'text-gray-900', 'iconClass' => 'bg-slate-100 text-slate-600'],
        ['label' => 'Alunos ativos', 'value' => (string) $alunosAtivos, 'icon' => 'fa-user-check', 'valueClass' => 'text-green-700', 'iconClass' => 'bg-green-50 text-green-600'],
        ['label' => 'Alunos inativos', 'value' => (string) $alunosInativos, 'icon' => 'fa-user-xmark', 'valueClass' => 'text-red-700', 'iconClass' => 'bg-red-50 text-red-600'],
        ['label' => 'Vagas', 'value' => $vagasLabel, 'icon' => 'fa-door-open', 'valueClass' => 'text-gray-900', 'iconClass' => 'bg-slate-100 text-slate-600'],
    ];
    foreach ($resumoCards as $card):
    ?>
    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500"><?= htmlspecialchars($card['label']) ?></p>
                <p class="mt-1 text-2xl font-bold leading-none <?= htmlspecialchars($card['valueClass']) ?>"><?= htmlspecialchars($card['value']) ?></p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl <?= htmlspecialchars($card['iconClass']) ?>">
                <i class="fa-solid <?= htmlspecialchars($card['icon']) ?> text-sm"></i>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Turma Info Card -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Informações da turma</h3>
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
                                <label class="block text-sm font-medium text-gray-500 mb-1">Vagas</label>
                                <?php
                                if (!is_array($vagas_resumo) || !empty($vagas_resumo['ilimitado'])) {
                                    echo '<p class="text-lg text-gray-900">Ilimitado</p>';
                                } else {
                                    $ocup = (int) ($vagas_resumo['ocupadas'] ?? 0);
                                    $res = (int) ($vagas_resumo['reservadas'] ?? 0);
                                    $tot = (int) ($vagas_resumo['vagas'] ?? 0);
                                    $fila = (int) ($vagas_resumo['fila'] ?? 0);
                                    echo '<p class="text-lg font-semibold text-gray-900">' . $ocup . '/' . $tot . '</p>';
                                    echo '<p class="text-xs text-gray-500">Reservadas: ' . $res . ($fila > 0 ? ' · Fila: ' . $fila : '') . '</p>';
                                }
                                ?>
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
                <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200">
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
                                <i class="fa-solid fa-user-graduate text-4xl text-gray-300 mb-4"></i>
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
                                                        <div class="h-10 w-10 rounded-full bg-slate-100 flex items-center justify-center">
                                                            <span class="text-sm font-medium text-slate-600"><?= strtoupper(substr($aluno['nome'] ?? '', 0, 2)) ?></span>
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
                                                   class="inline-flex px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100">Detalhes</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Lista de espera</h3>
                        <p class="text-sm text-gray-500 mt-1">Processos na fila desta turma, por ordem de chegada.</p>
                    </div>
                    <div class="p-6">
                        <?php if ($fila_espera === []): ?>
                        <p class="text-sm text-gray-500">Ninguém na fila.</p>
                        <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Responsável</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Entrou</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Ação</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($fila_espera as $filaItem): ?>
                                    <tr>
                                        <td class="px-4 py-2"><?= (int) ($filaItem['fila_posicao'] ?? 0) ?: '—' ?></td>
                                        <td class="px-4 py-2 font-medium text-gray-900"><?= htmlspecialchars($filaItem['aluno_nome'] ?? '') ?></td>
                                        <td class="px-4 py-2 text-gray-600"><?= htmlspecialchars($filaItem['resp_nome'] ?? '—') ?></td>
                                        <td class="px-4 py-2 text-gray-500"><?= !empty($filaItem['entrou_fila_em']) ? date('d/m/Y H:i', strtotime((string) $filaItem['entrou_fila_em'])) : '—' ?></td>
                                        <td class="px-4 py-2 text-right">
                                            <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int) $filaItem['id'] ?>/oferecer-vaga" class="inline">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <button type="submit" class="text-sm text-primary hover:underline">Oferecer vaga</button>
                                            </form>
                                            <a href="<?= URL ?>/admin/enrollment/<?= (int) $filaItem['id'] ?>" class="text-sm text-gray-500 hover:underline ml-2">Abrir</a>
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

            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Ações rápidas</h3>
                    <div class="space-y-3">
                        <?php if ($matriculas_schema_ready): ?>
                        <button type="button" onclick="abrirModalVincularAluno()"
                                class="btn-primary-custom w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
                            <i class="fa-solid fa-user-plus mr-2"></i>
                            Vincular aluno
                        </button>
                        <?php endif; ?>
                        <?php if ($cursoExtra): ?>
                        <a href="<?= URL ?>/admin/turmas/<?= $turmaId ?>/export-alunos-csv"
                           class="w-full inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            <i class="fa-solid fa-file-csv mr-2 text-gray-500"></i>
                            Exportar alunos (CSV)
                        </a>
                        <?php endif; ?>
                        <?php if ($cursoId > 0): ?>
                        <a href="<?= URL ?>/admin/curso/<?= $cursoId ?>/importar-alunos?turma_id=<?= $turmaId ?>"
                           class="w-full inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            <i class="fa-solid fa-file-import mr-2 text-gray-500"></i>
                            Importar / vincular (CSV)
                        </a>
                        <?php endif; ?>
                        <button type="button" onclick="toggleStatus(<?= $turma['id'] ?? 0 ?>, <?= ($turma['ativo'] ?? 0) ? 'false' : 'true' ?>)"
                                class="w-full inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            <i class="fa-solid fa-power-off mr-2 text-gray-500"></i>
                            <?= ($turma['ativo'] ?? 0) ? 'Desativar' : 'Ativar' ?>
                        </button>
                        <button type="button" onclick="deleteTurma(<?= $turma['id'] ?? 0 ?>)"
                                class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium">
                            <i class="fa-solid fa-trash-can mr-2"></i>
                            Excluir turma
                        </button>
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
