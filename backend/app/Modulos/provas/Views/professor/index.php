<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<?php
$stats = is_array($stats ?? null) ? $stats : [];
$statCards = [
    ['label' => 'Total', 'value' => (int)($stats['total'] ?? 0), 'icon' => 'fa-regular fa-file-lines', 'color' => 'blue'],
    ['label' => 'Liberadas', 'value' => (int)($stats['liberadas'] ?? 0), 'icon' => 'fa-solid fa-circle-check', 'color' => 'green'],
    ['label' => 'Bloqueadas', 'value' => (int)($stats['bloqueadas'] ?? 0), 'icon' => 'fa-solid fa-lock', 'color' => 'amber'],
    ['label' => 'Ativas', 'value' => (int)($stats['ativas'] ?? 0), 'icon' => 'fa-solid fa-bolt', 'color' => 'blue'],
];
?>

<div class="mb-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Minhas Provas</h2>
            <p class="text-gray-600 text-sm">Gerencie suas provas online e acompanhe o desempenho dos alunos.</p>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
            <a href="<?= URL ?>/professor/provas/criar"
               class="inline-flex items-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors shadow-sm">
                <i class="fa-solid fa-plus mr-2"></i>
                Novo Evento
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    <?php foreach ($statCards as $card): ?>
        <?php
        $color = $card['color'];
        $iconClasses = [
            'blue' => 'bg-blue-50 text-blue-600',
            'green' => 'bg-green-50 text-green-600',
            'amber' => 'bg-amber-50 text-amber-600',
        ][$color] ?? 'bg-slate-50 text-slate-600';
        $borderClasses = [
            'blue' => 'border-blue-200',
            'green' => 'border-green-200',
            'amber' => 'border-amber-200',
        ][$color] ?? 'border-gray-200';
        ?>
        <div class="bg-white rounded-xl shadow-sm border <?= $borderClasses ?> p-5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-600"><?= htmlspecialchars($card['label']) ?></p>
                    <p class="mt-1 text-3xl font-bold text-gray-900"><?= (int)$card['value'] ?></p>
                </div>
                <div class="w-11 h-11 rounded-lg flex items-center justify-center <?= $iconClasses ?>">
                    <i class="<?= htmlspecialchars($card['icon']) ?>"></i>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($eventos)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Eventos de Prova</h3>
        <p class="text-sm text-gray-600 mt-1">Eventos criados pela coordenação: prova online com questões ou lançamento de notas.</p>
    </div>
    <div class="p-6">
        <div class="space-y-4">
            <?php foreach ($eventos as $evento): ?>
                <?php
                $isLancamentoNota = (($evento['formato_evento'] ?? 'online_questoes') === 'lancamento_nota');
                $lancamentoCoordOnly = ($isLancamentoNota && (($evento['configuracao_nota'] ?? '') === 'coordenacao_calcula'));
                if ($isLancamentoNota) {
                    $urlProva = $lancamentoCoordOnly
                        ? (URL . '/professor/provas')
                        : (URL . '/professor/provas/evento-lancar-notas/' . (int)$evento['id'] . '?materia_id=' . (int)$evento['materia_id']);
                } else {
                    $urlProva = !empty($evento['prova_existente_id'])
                        ? URL . '/professor/provas/editar/' . (int)$evento['prova_existente_id']
                        : URL . '/professor/provas/criar/evento/' . (int)$evento['id'] . '?materia_id=' . (int)$evento['materia_id'];
                }
                $prazo = $evento['prazo_entrega_professor'] ?? null;
                $usaPrazo = !empty($prazo) && strtotime($prazo);
                $dataExibir = $usaPrazo ? strtotime($prazo) : (!empty($evento['data_prova']) ? strtotime($evento['data_prova']) : null);
                $horaExibir = null;
                if (!empty($evento['data_prova']) && !empty($evento['hora_inicio'])) {
                    $horaExibir = strtotime($evento['data_prova'] . ' ' . $evento['hora_inicio']);
                } elseif ($usaPrazo) {
                    $horaExibir = strtotime($prazo);
                }
                $totA = (int)($evento['lancamento_total_alunos'] ?? 0);
                $preen = (int)($evento['lancamento_notas_preenchidas'] ?? 0);
                $okLanc = $totA > 0 && $preen >= $totA;
                if ($isLancamentoNota) {
                    $actionLabel = $lancamentoCoordOnly ? 'Coordenação lança notas' : 'Lançar notas';
                    $actionIcon = $lancamentoCoordOnly ? 'fa-solid fa-user-shield' : 'fa-solid fa-pen-to-square';
                    $actionClass = $lancamentoCoordOnly
                        ? 'border-gray-300 bg-white text-gray-700'
                        : 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 hover:border-blue-300';
                } elseif (!empty($evento['prova_existente_id'])) {
                    $actionLabel = 'Continuar edição';
                    $actionIcon = 'fa-solid fa-pen';
                    $actionClass = 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 hover:border-blue-300';
                } else {
                    $actionLabel = 'Criar prova';
                    $actionIcon = 'fa-solid fa-plus';
                    $actionClass = 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 hover:border-blue-300';
                }
                ?>
                <a href="<?= htmlspecialchars($urlProva) ?>"
                   class="block border border-gray-200 rounded-lg p-5 hover:bg-gray-50 transition-colors">
                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center flex-wrap gap-2 mb-2">
                                <h4 class="text-base font-semibold text-gray-900"><?= htmlspecialchars($evento['titulo'] ?? 'Evento de prova') ?></h4>
                                <?php if (($evento['tipo_prova'] ?? '') === 'substitutiva'): ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Substitutiva</span>
                                <?php else: ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Original</span>
                                <?php endif; ?>
                                <?php if ($isLancamentoNota): ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">Lançamento de notas</span>
                                    <?php if ($totA <= 0): ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-700">Sem alunos</span>
                                    <?php elseif ($okLanc): ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Notas completas</span>
                                    <?php elseif ($preen > 0): ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Notas parciais (<?= $preen ?>/<?= $totA ?>)</span>
                                    <?php else: ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Pendente</span>
                                    <?php endif; ?>
                                <?php elseif (($evento['provas_criadas_professor'] ?? 0) > 0): ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Prova criada</span>
                                <?php else: ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Pendente</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($evento['descricao'])): ?>
                                <p class="text-sm text-gray-600 mb-3"><?= htmlspecialchars($evento['descricao']) ?></p>
                            <?php endif; ?>
                            <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                                <span><span class="font-medium text-gray-700">Matéria:</span> <?= htmlspecialchars($evento['materia_nome'] ?? 'N/A') ?></span>
                                <span><span class="font-medium text-gray-700">Turma(s):</span> <?= !empty($evento['turmas']) ? htmlspecialchars(implode(', ', array_column($evento['turmas'], 'nome'))) : 'N/A' ?></span>
                                <span><span class="font-medium text-gray-700">Data limite:</span> <?= $dataExibir ? date('d/m/Y', $dataExibir) : '—' ?></span>
                                <span><span class="font-medium text-gray-700">Horário:</span> <?= $horaExibir ? date('H:i', $horaExibir) : '—' ?></span>
                            </div>
                        </div>
                        <span class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium transition-colors <?= $actionClass ?>">
                            <i class="<?= $actionIcon ?>"></i>
                            <?= htmlspecialchars($actionLabel) ?>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Provas</h3>
        <p class="text-sm text-gray-600 mt-1">Lista de provas criadas fora dos eventos de coordenação.</p>
    </div>

    <?php if (empty($provas)): ?>
        <div class="px-6 py-12 text-center text-gray-500">
            <i class="fa-regular fa-file-lines text-4xl text-gray-300 mb-4"></i>
            <p class="text-sm font-medium text-gray-700">Nenhuma prova criada ainda</p>
            <p class="text-sm text-gray-500 mt-1 mb-4">Crie sua primeira prova para começar.</p>
            <a href="<?= URL ?>/professor/provas/criar"
               class="inline-flex items-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors shadow-sm">
                <i class="fa-solid fa-plus mr-2"></i>
                Criar Prova
            </a>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matéria</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Período</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($provas as $prova): ?>
                        <?php
                        $provaId = (int)($prova['id'] ?? 0);
                        $provaAprovada = (($prova['status'] ?? '') === 'aprovada');
                        $statusFormatado = $prova['status_formatado'] ?? [
                            'texto' => 'Em andamento',
                            'classe' => 'bg-blue-100 text-blue-800',
                        ];
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($prova['titulo'] ?? 'Prova') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600"><?= htmlspecialchars($prova['materia_nome'] ?? '-') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600">
                                    <?= !empty($prova['data_inicio']) ? date('d/m/Y H:i', strtotime($prova['data_inicio'])) : '—' ?>
                                    <span class="text-gray-400">até</span>
                                    <?= !empty($prova['data_fim']) ? date('d/m/Y H:i', strtotime($prova['data_fim'])) : '—' ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= htmlspecialchars($statusFormatado['classe'] ?? 'bg-slate-100 text-slate-700') ?>">
                                    <?= htmlspecialchars($statusFormatado['texto'] ?? 'Em andamento') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="relative inline-block text-left">
                                    <button type="button"
                                            class="inline-flex items-center gap-2 px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                                            data-teacher-exam-actions-toggle="actions-prova-<?= $provaId ?>">
                                        Ações
                                        <i class="fa-solid fa-chevron-down text-xs text-gray-400"></i>
                                    </button>
                                    <div id="actions-prova-<?= $provaId ?>"
                                         class="hidden absolute right-0 z-20 mt-2 w-48 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 border border-gray-100 overflow-hidden"
                                         data-teacher-exam-actions-menu>
                                        <a href="<?= URL ?>/professor/provas/visualizar/<?= $provaId ?>"
                                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                            <i class="fa-solid fa-eye text-gray-400 w-4 text-center"></i> Ver
                                        </a>
                                        <a href="<?= URL ?>/professor/provas/resultados/<?= $provaId ?>"
                                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                            <i class="fa-solid fa-chart-simple text-gray-400 w-4 text-center"></i> Resultados
                                        </a>
                                        <?php if (!$provaAprovada): ?>
                                            <a href="<?= URL ?>/professor/provas/editar/<?= $provaId ?>"
                                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                                            </a>
                                            <button type="button"
                                                    onclick="toggleLiberada(<?= $provaId ?>)"
                                                    class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                <i class="fa-solid <?= !empty($prova['liberada']) ? 'fa-lock' : 'fa-unlock' ?> text-gray-400 w-4 text-center"></i>
                                                <?= !empty($prova['liberada']) ? 'Bloquear' : 'Liberar' ?>
                                            </button>
                                            <div class="border-t border-gray-100 my-1"></div>
                                            <button type="button"
                                                    onclick="excluirProva(<?= $provaId ?>)"
                                                    class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleLiberada(id) {
    fetch('<?= URL ?>/professor/provas/toggle-liberada/' + id, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro ao alterar liberação'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao alterar liberação da prova');
    });
}

function excluirProva(id) {
    if (!confirm('Tem certeza que deseja excluir esta prova?')) {
        return;
    }
    
    fetch('<?= URL ?>/professor/provas/excluir/' + id, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro ao excluir prova'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao excluir prova');
    });
}

document.addEventListener('click', function (event) {
    const toggle = event.target.closest('[data-teacher-exam-actions-toggle]');
    document.querySelectorAll('[data-teacher-exam-actions-menu]').forEach(function (menu) {
        if (!toggle || menu.id !== toggle.getAttribute('data-teacher-exam-actions-toggle')) {
            menu.classList.add('hidden');
        }
    });
    if (toggle) {
        event.preventDefault();
        const menu = document.getElementById(toggle.getAttribute('data-teacher-exam-actions-toggle'));
        if (menu) {
            menu.classList.toggle('hidden');
        }
    }
});
</script>
