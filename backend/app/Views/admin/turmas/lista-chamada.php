<?php
$turma = $turma ?? [];
$config = $config ?? [];
$alunos = $alunos ?? [];
$ano_letivo_id = (int) ($ano_letivo_id ?? 0);
$lista_schema_ready = (bool) ($lista_schema_ready ?? false);
$campos_exportacao = $campos_exportacao ?? [];
$totais_listagem = $totais_listagem ?? ['total' => count($alunos), 'masculino' => 0, 'feminino' => 0];
$csrf_token = $csrf_token ?? '';
$turmaId = (int) ($turma['id'] ?? 0);

$grupos_exportacao = [
    'lista' => 'Lista de chamada',
    'aluno' => 'Dados do aluno',
    'endereco' => 'Endereço',
];
$campos_por_grupo = [];
foreach ($campos_exportacao as $key => $meta) {
    $grupo = $meta['grupo'] ?? 'aluno';
    $campos_por_grupo[$grupo][] = ['key' => $key, 'label' => $meta['label'] ?? $key];
}
$campos_padrao = ['numero_chamada', 'nome', 'ra'];
$exportUrl = URL . '/admin/turmas/' . $turmaId . '/lista-chamada/exportar';

$criterioAtual = (string) ($config['criterio_ordem'] ?? 'alfabetica');
$criterioLabel = match ($criterioAtual) {
    'meninas_primeiro' => 'Meninas 1º',
    'meninos_primeiro' => 'Meninos 1º',
    default => 'A–Z',
};

$page_header_title = 'Lista de Chamada';
$page_header_subtitle = 'Numeração e ordenação da turma ' . (string) ($turma['nome'] ?? '');
ob_start();
?>
<a href="<?= URL ?>/admin/turmas/<?= $turmaId ?>"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-arrow-left mr-2 text-gray-500"></i>
    Voltar à turma
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';

$flash_status = (($flash_type ?? '') === 'success') ? 'success' : ((($flash_message ?? '') !== '') ? 'error' : '');
include __DIR__ . '/../_partials/flash_message.php';
?>

<?php if (!$lista_schema_ready): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <p class="font-medium">A lista de chamada ainda não está disponível neste banco.</p>
        <p class="mt-1">Execute a migration <code class="text-xs bg-amber-100 px-1 py-0.5 rounded">059_lista_chamada.sql</code> (e <code class="text-xs bg-amber-100 px-1 py-0.5 rounded">2026_08_23_alunos_sexo.sql</code>) no painel Master.</p>
    </div>
</div>
<?php else: ?>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mb-6">
    <?php
    $resumoCards = [
        ['label' => 'Alunos na lista', 'value' => (string) (int) ($totais_listagem['total'] ?? 0), 'icon' => 'fa-list-ol', 'valueClass' => 'text-gray-900', 'iconClass' => 'bg-slate-100 text-slate-600', 'text' => false],
        ['label' => 'Masculino', 'value' => (string) (int) ($totais_listagem['masculino'] ?? 0), 'icon' => 'fa-mars', 'valueClass' => 'text-blue-700', 'iconClass' => 'bg-blue-50 text-blue-600', 'text' => false],
        ['label' => 'Feminino', 'value' => (string) (int) ($totais_listagem['feminino'] ?? 0), 'icon' => 'fa-venus', 'valueClass' => 'text-pink-700', 'iconClass' => 'bg-pink-50 text-pink-600', 'text' => false],
        ['label' => 'Critério', 'value' => $criterioLabel, 'icon' => 'fa-arrow-down-a-z', 'valueClass' => 'text-gray-900', 'iconClass' => 'bg-slate-100 text-slate-600', 'text' => true],
    ];
    foreach ($resumoCards as $card):
    ?>
    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500"><?= htmlspecialchars($card['label']) ?></p>
                <p class="mt-1 <?= $card['text'] ? 'text-lg' : 'text-2xl' ?> font-bold leading-none <?= htmlspecialchars($card['valueClass']) ?>">
                    <?= htmlspecialchars($card['value']) ?>
                </p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl <?= htmlspecialchars($card['iconClass']) ?>">
                <i class="fa-solid <?= htmlspecialchars($card['icon']) ?> text-sm"></i>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Configuração</h3>
                <p class="text-sm text-gray-500 mt-0.5">Critério de ordem e entrada tardia.</p>
            </div>
            <form method="POST" action="<?= URL ?>/admin/turmas/<?= $turmaId ?>/lista-chamada/config" class="p-6 space-y-6">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="ano_letivo_id" value="<?= $ano_letivo_id ?>">

                <div>
                    <label for="criterio_ordem" class="block text-sm font-medium text-gray-700 mb-2">Critério de ordem</label>
                    <select id="criterio_ordem" name="criterio_ordem"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value="alfabetica" <?= $criterioAtual === 'alfabetica' ? 'selected' : '' ?>>Alfabética</option>
                        <option value="meninas_primeiro" <?= $criterioAtual === 'meninas_primeiro' ? 'selected' : '' ?>>Meninas primeiro</option>
                        <option value="meninos_primeiro" <?= $criterioAtual === 'meninos_primeiro' ? 'selected' : '' ?>>Meninos primeiro</option>
                    </select>
                </div>

                <div>
                    <label for="data_corte" class="block text-sm font-medium text-gray-700 mb-2">Data limite (entrada tardia vai ao final)</label>
                    <input type="date" id="data_corte" name="data_corte" value="<?= htmlspecialchars($config['data_corte'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>

                <button type="submit" class="btn-primary-custom w-full px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
                    Salvar configuração
                </button>
            </form>
            <div class="px-6 pb-6">
                <form method="POST" action="<?= URL ?>/admin/turmas/<?= $turmaId ?>/lista-chamada/recalcular"
                      onsubmit="return confirm('Recalcular números de chamada conforme o critério atual?');">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="ano_letivo_id" value="<?= $ano_letivo_id ?>">
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <i class="fa-solid fa-arrows-rotate mr-2 text-gray-500"></i>
                        Recalcular lista
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Exportar lista</h3>
                <p class="text-sm text-gray-500 mt-0.5">Escolha os campos e o formato do documento.</p>
            </div>
            <form id="form-exportar-lista-chamada" method="GET" action="<?= htmlspecialchars($exportUrl) ?>" class="p-6 space-y-6">
                <input type="hidden" name="formato" id="export-formato" value="pdf">

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">Campos a exibir</label>
                        <button type="button" id="btn-toggle-todos-campos"
                                class="text-xs font-medium text-gray-600 hover:text-gray-900">
                            Selecionar todos
                        </button>
                    </div>
                    <div class="max-h-56 overflow-y-auto border border-gray-200 rounded-lg p-3 space-y-3 bg-gray-50">
                        <?php foreach ($grupos_exportacao as $grupoKey => $grupoLabel): ?>
                            <?php if (empty($campos_por_grupo[$grupoKey])) continue; ?>
                            <div>
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1"><?= htmlspecialchars($grupoLabel) ?></p>
                                <div class="space-y-1">
                                    <?php foreach ($campos_por_grupo[$grupoKey] as $campo): ?>
                                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                            <input type="checkbox" name="campos[]" value="<?= htmlspecialchars($campo['key']) ?>"
                                                   class="campo-exportacao rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                                                   <?= in_array($campo['key'], $campos_padrao, true) ? 'checked' : '' ?>>
                                            <span><?= htmlspecialchars($campo['label']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" name="assinatura" value="1"
                               class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                        <span>Espaço para assinatura na última coluna</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" name="logo" value="1" checked
                               class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                        <span>Incluir logo no topo</span>
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Orientação da página</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="radio" name="orientacao" value="vertical" checked
                                   class="border-gray-300 text-purple-600 focus:ring-purple-500">
                            <span>Vertical</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="radio" name="orientacao" value="horizontal"
                                   class="border-gray-300 text-purple-600 focus:ring-purple-500">
                            <span>Horizontal</span>
                        </label>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <button type="submit" onclick="document.getElementById('export-formato').value='pdf';"
                            class="btn-primary-custom w-full px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
                        <i class="fa-solid fa-file-pdf mr-2"></i> Exportar PDF
                    </button>
                    <button type="submit" onclick="document.getElementById('export-formato').value='excel';"
                            class="w-full inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <i class="fa-solid fa-file-excel mr-2 text-gray-500"></i> Exportar Excel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Alunos</h3>
            <span class="text-sm text-gray-500"><?= count($alunos) ?> na lista</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nº</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RA</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sexo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs.</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($alunos)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <i class="fa-solid fa-clipboard-list text-4xl text-gray-300 mb-4"></i>
                                <p>Nenhum aluno na lista ainda.</p>
                                <p class="text-sm mt-1">Clique em <strong>Recalcular lista</strong> para trazer os alunos já vinculados à turma.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($alunos as $a): ?>
                            <?php
                            $sexo = strtoupper(trim((string) ($a['sexo'] ?? '')));
                            $sexoTexto = match ($sexo) {
                                'F' => 'Feminino',
                                'M' => 'Masculino',
                                'N' => 'Neutro / outro',
                                default => '—',
                            };
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900"><?= (int) ($a['numero_chamada'] ?? 0) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($a['nome'] ?? '') ?>
                                    <?php if (!empty($a['marcado_tr'])): ?>
                                        <span class="ml-1 inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-800">TR</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($a['ra'] ?? '') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($sexoTexto) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if (!empty($a['entrada_tardia'])): ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Entrada tardia</span>
                                    <?php else: ?>
                                        <span class="text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($alunos)): ?>
        <div class="px-6 py-3 border-t border-gray-200 bg-gray-50 text-sm text-gray-700 flex flex-wrap gap-x-6 gap-y-1">
            <span><strong>Total:</strong> <?= (int) ($totais_listagem['total'] ?? 0) ?></span>
            <span><strong>Masculino:</strong> <?= (int) ($totais_listagem['masculino'] ?? 0) ?></span>
            <span><strong>Feminino:</strong> <?= (int) ($totais_listagem['feminino'] ?? 0) ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('form-exportar-lista-chamada');
    var toggleBtn = document.getElementById('btn-toggle-todos-campos');
    if (!form || !toggleBtn) return;

    form.addEventListener('submit', function (e) {
        var checked = form.querySelectorAll('.campo-exportacao:checked');
        if (checked.length === 0) {
            e.preventDefault();
            alert('Selecione pelo menos um campo para exportar.');
        }
    });

    toggleBtn.addEventListener('click', function () {
        var boxes = form.querySelectorAll('.campo-exportacao');
        var allChecked = Array.prototype.every.call(boxes, function (b) { return b.checked; });
        boxes.forEach(function (b) { b.checked = !allChecked; });
        toggleBtn.textContent = allChecked ? 'Selecionar todos' : 'Desmarcar todos';
    });
})();
</script>

<?php endif; ?>
