<?php
/**
 * Gerenciar Provas de um Bloco
 * Acesso: Coordenação
 */
$modoLancamentoNota = !empty($modo_lancamento_nota);
$colunaVisivelPortalAluno = !empty($coluna_visivel_portal_aluno);
$lancamentoPorCoordenacao = $modoLancamentoNota && (($bloco['configuracao_nota'] ?? '') === 'coordenacao_calcula');
$notaUnicaTodasMaterias = $lancamentoPorCoordenacao && !empty($bloco['nota_unica_todas_materias']);
?>

<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                <?php if ($modoLancamentoNota): ?>
                    Lançamento de notas: <?= htmlspecialchars($bloco['titulo']) ?>
                <?php else: ?>
                    Gerenciar Provas: <?= htmlspecialchars($bloco['titulo']) ?>
                <?php endif; ?>
            </h2>
            <p class="text-gray-600">
                Status: <span class="font-semibold"><?= ucfirst(str_replace('_', ' ', $bloco['status'])) ?></span>
                <?php if ($bloco['prazo_entrega_professor']): ?>
                    | Prazo: <?= date('d/m/Y H:i', strtotime($bloco['prazo_entrega_professor'])) ?>
                <?php endif; ?>
                <?php if ($modoLancamentoNota): ?>
                    <span class="block mt-1 text-sm text-purple-700">
                        Modo: <?= $lancamentoPorCoordenacao ? 'coordenação' : 'professor' ?> informa nota cheia por aluno (0 a 10). Não há prova com questões neste evento.
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-2 justify-end">
            <?php if ($modoLancamentoNota && $lancamentoPorCoordenacao): ?>
            <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/lancar-notas-coordenacao"
               class="btn-primary-custom inline-flex items-center gap-2 px-4 py-2 rounded-lg hover:opacity-90">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                Lançar notas (coordenação)
            </a>
            <?php endif; ?>
            <?php if ($modoLancamentoNota): ?>
            <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/resultados"
               class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                Relatório de notas
            </a>
            <?php else: ?>
            <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/resultados-novos"
               class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                Resultados
            </a>
            <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/canceladas"
               class="inline-flex items-center gap-2 bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 <?= !empty($total_canceladas) ? 'ring-2 ring-amber-300' : '' ?>">
                <i class="fa-solid fa-ban" aria-hidden="true"></i>
                Cancelados<?= !empty($total_canceladas) ? ' (' . (int)$total_canceladas . ')' : '' ?>
            </a>
            <?php if (!empty($bloco['gabarito_liberado'])): ?>
            <span class="inline-flex items-center gap-2 bg-emerald-100 text-emerald-800 px-4 py-2 rounded-lg font-medium">
                <i class="fa-solid fa-unlock-keyhole" aria-hidden="true"></i>
                Gabarito liberado
            </span>
            <?php else: ?>
            <form method="post" action="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/liberar-gabarito" class="inline"
                  onsubmit="return confirm('Liberar o gabarito deste bloco para todos os alunos?');">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string)($csrf_token ?? '')) ?>">
                <input type="hidden" name="origem" value="gerenciar">
                <button type="submit" class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">
                    <i class="fa-solid fa-unlock-keyhole" aria-hidden="true"></i>
                    Liberar gabarito
                </button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
            <?php if (!$modoLancamentoNota): ?>
            <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/visualizar-completo"
               class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                Prova Completa
            </a>
            <?php endif; ?>
            <?php if (!$modoLancamentoNota && isset($mostrarBotaoAprovacaoFinal) && $mostrarBotaoAprovacaoFinal): ?>
            <button onclick="aprovarBlocoFinal(<?= $bloco['id'] ?>)" 
                    class="inline-flex items-center gap-2 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                Aprovação Final
            </button>
            <?php endif; ?>
            <a href="<?= URL ?>/admin/provas" 
               class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                Voltar
            </a>
        </div>
    </div>
</div>

<?php if ($modoLancamentoNota && !empty($flash_importacao_notas['message'])): ?>
<?php
$flashTipo = (string) ($flash_importacao_notas['type'] ?? 'info');
$flashClasses = [
    'success' => 'bg-green-50 border-green-200 text-green-800',
    'error' => 'bg-red-50 border-red-200 text-red-800',
    'warning' => 'bg-amber-50 border-amber-200 text-amber-900',
    'info' => 'bg-blue-50 border-blue-200 text-blue-800',
];
?>
<div class="mb-6 rounded-lg border px-4 py-3 text-sm <?= $flashClasses[$flashTipo] ?? $flashClasses['info'] ?>" role="status">
    <?= htmlspecialchars((string) $flash_importacao_notas['message']) ?>
</div>
<?php endif; ?>

<?php if ($modoLancamentoNota): ?>
<!-- Painel lançamento de notas -->
<?php if (isset($contagem)): ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-gray-400">
        <p class="text-sm text-gray-600">Professor sem notas lançadas</p>
        <p class="text-3xl font-bold text-gray-900"><?= (int)($contagem['ln_nao_iniciado'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-amber-500">
        <p class="text-sm text-gray-600">Em andamento (parcial)</p>
        <p class="text-3xl font-bold text-gray-900"><?= (int)($contagem['ln_em_andamento'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-gray-600">Concluído (todos os alunos)</p>
        <p class="text-3xl font-bold text-gray-900"><?= (int)($contagem['ln_concluido'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-gray-600">Notas abaixo de 6 (linhas)</p>
        <p class="text-3xl font-bold text-gray-900"><?= (int)($contagem['ln_abaixo_seis'] ?? 0) ?></p>
    </div>
</div>
<?php endif; ?>

<?php if ($modoLancamentoNota && $colunaVisivelPortalAluno): ?>
<div class="mb-6 p-4 bg-white rounded-xl shadow border border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div class="flex items-start gap-3">
        <input type="checkbox"
               id="chkVisivelPortalAluno"
               class="mt-1 w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500"
               <?= !empty($bloco['visivel_no_portal_aluno']) ? 'checked' : '' ?>
               onchange="salvarVisivelPortalAluno(<?= (int)($bloco['id'] ?? 0) ?>, this)">
        <div>
            <label for="chkVisivelPortalAluno" class="text-sm font-medium text-gray-900 cursor-pointer">Mostrar este evento no portal do aluno</label>
            <p class="text-xs text-gray-500 mt-1">Quando desmarcado, o aluno não vê em &quot;Minhas provas&quot; e não acessa por link (avaliações bimestrais internas, etc.).</p>
        </div>
    </div>
    <span id="msgVisivelPortalAluno" class="text-xs text-gray-500 shrink-0"></span>
</div>
<script>
function salvarVisivelPortalAluno(blocoId, el) {
    var msg = document.getElementById('msgVisivelPortalAluno');
    if (msg) { msg.textContent = 'Salvando...'; }
    fetch('<?= URL ?>/admin/provas/blocos/' + blocoId + '/visivel-portal-aluno', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ visivel: el.checked ? 1 : 0 })
    })
    .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
    .then(function(res) {
        if (res.ok && res.data.success) {
            if (msg) { msg.textContent = res.data.message || 'Salvo.'; }
            setTimeout(function() { if (msg) msg.textContent = ''; }, 4000);
            return;
        }
        if (msg) { msg.textContent = ''; }
        alert(res.data.error || 'Não foi possível salvar.');
        el.checked = !el.checked;
    })
    .catch(function() {
        if (msg) { msg.textContent = ''; }
        alert('Erro de conexão ao salvar.');
        el.checked = !el.checked;
    });
}
</script>
<?php elseif ($modoLancamentoNota && !$colunaVisivelPortalAluno): ?>
<div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-900 text-sm">
    Para ativar o controle &quot;exibir no portal do aluno&quot; nesta tela, rode pelo painel Master (Migrations) ou execute no MySQL do tenant o arquivo <code class="bg-amber-100 px-1 rounded">database/migrations/2026_04_18_provas_blocos_visivel_portal_aluno.sql</code> (na pasta do código: <code class="bg-amber-100 px-1 rounded">src/database/migrations/</code>).
</div>
<?php endif; ?>

<?php
$fontesImportacao = is_array($fontes_importacao_notas ?? null) ? $fontes_importacao_notas : [];
$eventosImportacao = [];
foreach ($fontesImportacao as $fonte) {
    $fid = (int) ($fonte['bloco_id'] ?? 0);
    if ($fid <= 0) {
        continue;
    }
    $eventosImportacao[$fid] = [
        'titulo' => (string) ($fonte['bloco_titulo'] ?? ('Evento #' . $fid)),
        'data' => !empty($fonte['data_prova']) ? date('d/m/Y', strtotime((string) $fonte['data_prova'])) : '',
        'bimestre' => (int) ($fonte['bimestre'] ?? 0),
    ];
}
?>
<div id="importacao-notas-internas" class="bg-white rounded-xl shadow-lg p-6 mb-6 border-l-4 border-indigo-500 scroll-mt-6">
    <div class="flex items-start gap-3 mb-5">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 shrink-0">
            <i class="fa-solid fa-file-import" aria-hidden="true"></i>
        </span>
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Importação de notas internas</h3>
            <p class="text-sm text-gray-600 mt-1">Traga notas já lançadas em outro evento para o professor e a matéria correspondentes deste evento.</p>
        </div>
    </div>

    <?php if (empty($fontesImportacao)): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Nenhum evento anterior possui notas de professor e matéria compatíveis com este evento.
        </div>
    <?php else: ?>
        <form method="post" action="<?= URL ?>/admin/provas/blocos/<?= (int) $bloco['id'] ?>/importar-notas-internas"
              class="grid grid-cols-1 lg:grid-cols-2 gap-4" id="formImportacaoNotas">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token_importacao ?? '')) ?>">
            <input type="hidden" name="fonte_professor_id" id="fonteProfessorId" value="">
            <input type="hidden" name="fonte_materia_id" id="fonteMateriaId" value="">

            <div>
                <label for="fonteBlocoId" class="block text-sm font-medium text-gray-700 mb-1">1. Evento de origem</label>
                <select name="fonte_bloco_id" id="fonteBlocoId" required
                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Selecione o evento</option>
                    <?php foreach ($eventosImportacao as $eventoId => $evento): ?>
                        <option value="<?= (int) $eventoId ?>">
                            <?= htmlspecialchars($evento['titulo']) ?>
                            <?= $evento['bimestre'] > 0 ? ' · ' . (int) $evento['bimestre'] . 'º bimestre' : '' ?>
                            <?= $evento['data'] !== '' ? ' · ' . htmlspecialchars($evento['data']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="fonteProfessorMateria" class="block text-sm font-medium text-gray-700 mb-1">2. Professor e matéria</label>
                <select id="fonteProfessorMateria" required disabled
                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                    <option value="">Selecione primeiro o evento</option>
                    <?php foreach ($fontesImportacao as $fonte): ?>
                        <option value="<?= (int) $fonte['professor_id'] ?>_<?= (int) $fonte['materia_id'] ?>"
                                data-evento="<?= (int) $fonte['bloco_id'] ?>"
                                data-professor="<?= (int) $fonte['professor_id'] ?>"
                                data-materia="<?= (int) $fonte['materia_id'] ?>"
                                data-total="<?= (int) ($fonte['total_notas'] ?? 0) ?>"
                                hidden>
                            <?= htmlspecialchars((string) ($fonte['professor_nome'] ?? '')) ?> · <?= htmlspecialchars((string) ($fonte['materia_nome'] ?? '')) ?>
                            (<?= (int) ($fonte['total_notas'] ?? 0) ?> notas)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lg:col-span-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pt-1">
                <label class="inline-flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" name="sobrescrever" value="1"
                           class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span><strong>Sobrescrever notas já lançadas</strong><br><span class="text-xs text-gray-500">Desmarcado, o sistema preserva as notas existentes e completa somente as que faltam.</span></span>
                </label>
                <button type="submit" id="btnImportarNotas" disabled
                        class="btn-primary-custom inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed shrink-0 hover:opacity-90">
                    <i class="fa-solid fa-file-import" aria-hidden="true"></i>
                    Importar notas
                </button>
            </div>
        </form>

        <script>
        (function () {
            var evento = document.getElementById('fonteBlocoId');
            var combo = document.getElementById('fonteProfessorMateria');
            var professor = document.getElementById('fonteProfessorId');
            var materia = document.getElementById('fonteMateriaId');
            var botao = document.getElementById('btnImportarNotas');
            if (!evento || !combo) return;

            function limparDestino() {
                professor.value = '';
                materia.value = '';
                botao.disabled = true;
            }
            evento.addEventListener('change', function () {
                var id = evento.value;
                var encontrou = false;
                Array.prototype.forEach.call(combo.options, function (option, index) {
                    if (index === 0) return;
                    var mostrar = option.dataset.evento === id;
                    option.hidden = !mostrar;
                    option.disabled = !mostrar;
                    if (mostrar) encontrou = true;
                });
                combo.value = '';
                combo.disabled = !encontrou;
                combo.options[0].textContent = encontrou ? 'Selecione o professor e a matéria' : 'Nenhuma nota compatível neste evento';
                limparDestino();
            });
            combo.addEventListener('change', function () {
                var option = combo.options[combo.selectedIndex];
                professor.value = option && option.dataset.professor ? option.dataset.professor : '';
                materia.value = option && option.dataset.materia ? option.dataset.materia : '';
                botao.disabled = professor.value === '' || materia.value === '';
            });
            document.getElementById('formImportacaoNotas').addEventListener('submit', function (e) {
                if (!professor.value || !materia.value) {
                    e.preventDefault();
                    return;
                }
                if (!window.confirm('Importar as notas selecionadas para este evento?')) {
                    e.preventDefault();
                    return;
                }
                botao.disabled = true;
                botao.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Importando...';
            });
        })();
        </script>
    <?php endif; ?>
</div>

<?php if ($notaUnicaTodasMaterias): ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border-l-4 border-violet-500">
    <h3 class="text-lg font-semibold text-gray-900">Nota única para todas as matérias</h3>
    <p class="text-sm text-gray-600 mt-2">Neste evento, a coordenação lança uma única nota por aluno e o sistema replica automaticamente para todas as matérias.</p>
    <div class="mt-4">
        <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/lancar-notas-coordenacao"
           class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg hover:opacity-90">
            Lançar/editar nota única dos alunos
        </a>
    </div>
</div>
<?php elseif (empty($lancamentoPorMateria)): ?>
<div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-amber-900 mb-6">
    Nenhum professor/matéria vinculado a este evento. Edite o bloco e adicione professores com turmas.
</div>
<?php else: ?>
<?php foreach ($lancamentoPorMateria as $materiaNome => $linhas): ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4"><?= htmlspecialchars($materiaNome) ?></h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Professor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Progresso</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Média</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">&lt; 6</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($linhas as $row): ?>
                <tr>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($row['professor_nome'] ?? '') ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700">
                        <?= (int)($row['com_nota'] ?? 0) ?> / <?= (int)($row['total_esperado'] ?? 0) ?>
                        <?php if (($row['total_esperado'] ?? 0) > 0): ?>
                            <span class="text-gray-500">(<?= htmlspecialchars((string)($row['perc'] ?? '')) ?>%)</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= $row['media_nota'] !== null ? htmlspecialchars(number_format((float)$row['media_nota'], 2, ',', '.')) : '—' ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= (int)($row['abaixo_seis'] ?? 0) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php
                        $st = $row['status'] ?? '';
                        $map = [
                            'nao_iniciado' => ['bg-red-100 text-red-800', 'Não iniciou'],
                            'em_andamento' => ['bg-amber-100 text-amber-800', 'Em andamento'],
                            'concluido' => ['bg-green-100 text-green-800', 'Concluído'],
                            'sem_alunos' => ['bg-gray-100 text-gray-700', 'Sem alunos nas turmas'],
                        ];
                        $pair = $map[$st] ?? ['bg-gray-100 text-gray-800', $st];
                        ?>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $pair[0] ?>"><?= htmlspecialchars($pair[1]) ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <?php if ($lancamentoPorCoordenacao): ?>
                            <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/lancar-notas-coordenacao?materia_id=<?= (int)($row['materia_id'] ?? 0) ?>"
                               class="text-violet-700 hover:text-violet-900 font-medium">Lançar/editar notas</a>
                        <?php else: ?>
                            <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/notas-lancadas?professor_id=<?= (int)($row['professor_id'] ?? 0) ?>&materia_id=<?= (int)($row['materia_id'] ?? 0) ?>"
                               class="text-indigo-600 hover:text-indigo-900 font-medium">Ver notas dos alunos</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php else: ?>

<!-- Cards de Indicador (padrão: borda esquerda, label, valor, ícone circular) -->
<?php if (isset($contagem)): ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Em Andamento</p>
                <p class="text-3xl font-bold text-gray-900"><?= $contagem['em_andamento'] ?? 0 ?></p>
            </div>
            <div class="bg-blue-100 rounded-full p-3">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Enviadas</p>
                <p class="text-3xl font-bold text-gray-900"><?= $contagem['enviada'] ?? 0 ?></p>
            </div>
            <div class="bg-yellow-100 rounded-full p-3">
                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Não Enviadas</p>
                <p class="text-3xl font-bold text-gray-900"><?= $contagem['nao_enviada'] ?? 0 ?></p>
            </div>
            <div class="bg-red-100 rounded-full p-3">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Aprovadas</p>
                <p class="text-3xl font-bold text-gray-900"><?= $contagem['aprovada'] ?? 0 ?></p>
            </div>
            <div class="bg-green-100 rounded-full p-3">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-amber-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Retornado Professor</p>
                <p class="text-3xl font-bold text-gray-900"><?= $contagem['retornada'] ?? 0 ?></p>
            </div>
            <div class="bg-amber-100 rounded-full p-3">
                <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Provas Excluídas</p>
                <p class="text-3xl font-bold text-gray-900"><?= $contagem['reprovada'] ?? 0 ?></p>
            </div>
            <div class="bg-orange-100 rounded-full p-3">
                <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filtros -->
<?php $statusFiltro = $status_filtro ?? $_GET['status'] ?? ''; ?>
<div class="bg-white rounded-xl shadow-lg p-4 mb-6">
    <div class="flex flex-wrap gap-2">
        <a href="?" 
           class="px-4 py-2 <?= $statusFiltro === '' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?> rounded-lg transition-colors">
            Todas
        </a>
        <a href="?status=enviada" 
           class="px-4 py-2 <?= $statusFiltro === 'enviada' ? 'bg-yellow-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-yellow-600 hover:text-white' ?> rounded-lg transition-colors">
            Enviadas
        </a>
        <a href="?status=nao_enviada" 
           class="px-4 py-2 <?= $statusFiltro === 'nao_enviada' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-red-600 hover:text-white' ?> rounded-lg transition-colors">
            Não Enviadas
        </a>
        <a href="?status=retornada" 
           class="px-4 py-2 <?= $statusFiltro === 'retornada' ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-amber-600 hover:text-white' ?> rounded-lg transition-colors">
            Retornado Professor
        </a>
        <a href="?status=excluido" 
           class="px-4 py-2 <?= $statusFiltro === 'excluido' ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-orange-600 hover:text-white' ?> rounded-lg transition-colors">
            Excluído
        </a>
    </div>
</div>

<!-- Provas Agrupadas por Matéria -->
<?php foreach ($provasPorMateria as $materiaNome => $provasMateria): ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4"><?= htmlspecialchars($materiaNome) ?></h3>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Professor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data Envio</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Questões</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($provasMateria as $prova): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($prova['professor_nome']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php
                        // Mesmos rótulos dos cards da tela: Em Andamento, Enviadas, Não Enviadas, Aprovadas, Provas Excluídas
                        $statusClasses = [
                            'nao_avaliada' => 'bg-yellow-100 text-yellow-800',
                            'aprovado' => 'bg-green-100 text-green-800',
                            'em_andamento' => 'bg-blue-100 text-blue-800',
                            'concluido' => 'bg-purple-100 text-purple-800',
                            'reprovada' => 'bg-orange-100 text-orange-800',
                            'nao_enviada' => 'bg-red-100 text-red-800',
                            'retornada' => 'bg-amber-100 text-amber-800',
                            'pendente' => 'bg-amber-100 text-amber-800'
                        ];
                        $statusLabels = [
                            'nao_avaliada' => 'Enviada',
                            'aprovado' => 'Aprovada',
                            'em_andamento' => 'Em Andamento',
                            'concluido' => 'Concluída',
                            'reprovada' => 'Prova excluída',
                            'nao_enviada' => 'Não Enviada',
                            'retornada' => 'Retornada ao professor',
                            'pendente' => 'Aguardando envio'
                        ];
                        $statusExibicao = $prova['status'] ?? 'nao_enviada';
                        $statusClass = $statusClasses[$statusExibicao] ?? 'bg-gray-100 text-gray-800';
                        $statusLabel = $statusLabels[$statusExibicao] ?? $statusExibicao;
                        ?>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $statusClass ?>">
                            <?= $statusLabel ?>
                        </span>
                        <?php if (($prova['status'] ?? '') === 'retornada' && !empty($prova['observacao_coordenacao'])): ?>
                        <div class="text-xs text-amber-700 mt-1 max-w-md" title="<?= htmlspecialchars($prova['observacao_coordenacao']) ?>">
                            <?= htmlspecialchars(mb_substr($prova['observacao_coordenacao'], 0, 60)) ?><?= mb_strlen($prova['observacao_coordenacao']) > 60 ? '...' : '' ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($prova['travada'] ?? false): ?>
                            <span class="ml-2 text-red-600" title="Travada">🔒</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= $prova['data_envio'] ? date('d/m/Y H:i', strtotime($prova['data_envio'])) : '-' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= $prova['numero_questoes'] ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <?php if ($prova['prova_id']): ?>
                            <a href="<?= URL ?>/admin/provas/visualizar/<?= $prova['prova_id'] ?>" 
                               class="text-blue-600 hover:text-blue-900 mr-3">
                                Ver Prova
                            </a>
                            <?php if (!empty($prova['professor_id']) && !empty($prova['materia_id'])): ?>
                                <button type="button" 
                                        onclick="abrirModalTrocar(<?= (int)$bloco['id'] ?>, <?= (int)$prova['professor_id'] ?>, <?= (int)$prova['materia_id'] ?>, '<?= htmlspecialchars(addslashes($prova['professor_nome'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($prova['materia_nome'] ?? '')) ?>', <?= (int)$prova['prova_id'] ?>)"
                                        class="text-amber-600 hover:text-amber-900 font-medium">
                                    Trocar prova
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php 
                        // Mostra botões de aprovar/reprovar para provas enviadas OU ainda pendentes/agendadas (coordenação pode aprovar mesmo sem envio)
                        $statusOriginalAcoes = $prova['status_original'] ?? '';
                        $podeAprovarReprovar = in_array($statusOriginalAcoes, ['enviada', 'aguardando_aprovacao', 'pendente', 'agendada']) && $prova['prova_id'];
                        if ($podeAprovarReprovar): ?>
                            <button onclick="aprovarProva(<?= $prova['prova_id'] ?>)" 
                                    class="text-green-600 hover:text-green-900 mr-3">
                                ✅ Aprovar
                            </button>
                            <button onclick="reprovarProva(<?= $prova['prova_id'] ?>)" 
                                    class="text-red-600 hover:text-red-900">
                                ❌ Reprovar
                            </button>
                        <?php elseif ($prova['status'] === 'nao_enviada'): ?>
                            <?php if (!empty($prova['professor_id']) && !empty($prova['materia_id'])): ?>
                                <button type="button" 
                                        onclick="abrirModalVincular(<?= (int)$bloco['id'] ?>, <?= (int)$prova['professor_id'] ?>, <?= (int)$prova['materia_id'] ?>, '<?= htmlspecialchars(addslashes($prova['professor_nome'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($prova['materia_nome'] ?? '')) ?>')"
                                        class="text-indigo-600 hover:text-indigo-900 font-medium">
                                    🔗 Vincular prova
                                </button>
                            <?php else: ?>
                                <span class="text-gray-400 italic">Aguardando criação da prova</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<?php if (!$modoLancamentoNota): ?>
<!-- Modal Vincular Prova -->
<div id="modalVincularProva" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="fecharModalVincular()"></div>
        <div class="relative inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle bg-white rounded-xl shadow-xl">
            <h3 class="text-lg font-semibold text-gray-900" id="modalVincularTitulo">Vincular prova</h3>
            <p class="mt-1 text-sm text-gray-500" id="modalVincularSubtitulo"></p>
            <div id="modalVincularLista" class="mt-4 max-h-64 overflow-y-auto">
                <p class="text-gray-500">Carregando...</p>
            </div>
            <div id="modalVincularVazio" class="mt-4 hidden">
                <p class="text-amber-700">Nenhuma prova disponível para vincular (provas do professor nesta matéria que ainda não estão em outro bloco).</p>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="fecharModalVincular()" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var modalVincularBlocoId = null;
var modalVincularProvaAtualId = null;

function abrirModalVincular(blocoId, professorId, materiaId, professorNome, materiaNome) {
    modalVincularProvaAtualId = null;
    abrirModalVincularOuTrocar(blocoId, professorId, materiaId, professorNome, materiaNome, null);
}

function abrirModalTrocar(blocoId, professorId, materiaId, professorNome, materiaNome, provaAtualId) {
    modalVincularProvaAtualId = provaAtualId || null;
    abrirModalVincularOuTrocar(blocoId, professorId, materiaId, professorNome, materiaNome, provaAtualId);
}

function abrirModalVincularOuTrocar(blocoId, professorId, materiaId, professorNome, materiaNome, provaAtualId) {
    var isTrocar = !!provaAtualId;
    modalVincularBlocoId = blocoId;
    document.getElementById('modalVincularTitulo').textContent = isTrocar ? 'Trocar prova' : 'Vincular prova';
    document.getElementById('modalVincularSubtitulo').textContent = professorNome + ' – ' + materiaNome;
    document.getElementById('modalVincularLista').innerHTML = '<p class="text-gray-500">Carregando...</p>';
    document.getElementById('modalVincularLista').classList.remove('hidden');
    document.getElementById('modalVincularVazio').classList.add('hidden');
    document.getElementById('modalVincularProva').classList.remove('hidden');
    document.getElementById('modalVincularVazio').querySelector('p').textContent = isTrocar
        ? 'Nenhuma outra prova disponível para colocar no lugar (provas do professor nesta matéria que ainda não estão em outro bloco).'
        : 'Nenhuma prova disponível para vincular (provas do professor nesta matéria que ainda não estão em outro bloco).';
    fetch('<?= URL ?>/admin/provas/blocos/' + blocoId + '/provas-disponiveis?professor_id=' + encodeURIComponent(professorId) + '&materia_id=' + encodeURIComponent(materiaId))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var lista = document.getElementById('modalVincularLista');
            var vazio = document.getElementById('modalVincularVazio');
            if (!data.provas || data.provas.length === 0) {
                lista.classList.add('hidden');
                vazio.classList.remove('hidden');
                return;
            }
            var html = '<ul class="space-y-2">';
            data.provas.forEach(function(p) {
                var dataEnvio = p.data_envio ? new Date(p.data_envio).toLocaleDateString('pt-BR') : '-';
                var turmaTexto = (p.turma_nome && p.turma_nome.trim()) ? ' · ' + (p.turma_nome.trim()) : '';
                var btnLabel = isTrocar ? 'Trocar' : 'Vincular';
                var btnOnclick = isTrocar
                    ? 'trocarProvaSubmit(' + blocoId + ',' + provaAtualId + ',' + p.id + ')'
                    : 'vincularProvaSubmit(' + blocoId + ',' + p.id + ')';
                var urlVer = '<?= URL ?>/admin/provas/visualizar/' + p.id;
                html += '<li class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50">';
                html += '<div class="flex items-center gap-3">';
                html += '<span class="shrink-0 px-2 py-0.5 text-xs font-mono font-semibold rounded bg-gray-200 text-gray-700" title="ID da prova">#' + (p.id || '') + '</span>';
                html += '<div><span class="font-medium">' + (p.titulo || 'Prova #' + p.id) + '</span><span class="text-sm text-gray-500 ml-2">' + (p.numero_questoes || 0) + ' questões · ' + dataEnvio + turmaTexto + '</span></div>';
                html += '</div>';
                html += '<div class="flex items-center gap-2 shrink-0">';
                html += '<a href="' + urlVer + '" target="_blank" rel="noopener" class="px-3 py-1 text-sm text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Visualizar</a>';
                html += '<button type="button" onclick="' + btnOnclick + '" class="px-3 py-1 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700">' + btnLabel + '</button>';
                html += '</div></li>';
            });
            html += '</ul>';
            lista.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('modalVincularLista').innerHTML = '<p class="text-red-600">Erro ao carregar provas.</p>';
        });
}

function fecharModalVincular() {
    document.getElementById('modalVincularProva').classList.add('hidden');
}

function vincularProvaSubmit(blocoId, provaId) {
    if (!confirm('Vincular esta prova ao bloco? Ela voltará a aparecer como enviada/aprovada neste evento.')) return;
    fetch('<?= URL ?>/admin/provas/blocos/' + blocoId + '/vincular', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({ prova_id: provaId, _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?> })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert(data.message || 'Prova vinculada.');
            fecharModalVincular();
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Não foi possível vincular'));
        }
    })
    .catch(function(e) {
        alert('Erro de conexão: ' + e.message);
    });
}

function trocarProvaSubmit(blocoId, provaAtualId, novaProvaId) {
    if (!confirm('Trocar a prova vinculada por esta? A prova atual será desvinculada do bloco.')) return;
    fetch('<?= URL ?>/admin/provas/blocos/' + blocoId + '/trocar-prova', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            prova_atual_id: provaAtualId,
            nova_prova_id: novaProvaId,
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert(data.message || 'Prova trocada.');
            fecharModalVincular();
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Não foi possível trocar'));
        }
    })
    .catch(function(e) {
        alert('Erro de conexão: ' + e.message);
    });
}

function aprovarProva(provaId) {
    if (!confirm('Deseja aprovar esta avaliação? O professor não poderá mais editar. A prova só será liberada para os alunos na aprovação final do bloco.')) {
        return;
    }
    
    fetch(`<?= URL ?>/admin/provas/liberar/${provaId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(async response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error('Resposta do servidor não é JSON válido: ' + text.substring(0, 200));
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('Prova aprovada com sucesso! O professor não poderá mais editar. Libere para os alunos na aprovação final do bloco.');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro ao aprovar prova'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
        console.error(error);
    });
}

function reprovarProva(provaId) {
    if (!confirm('Deseja reprovar esta prova? A prova será marcada como não aprovada.')) {
        return;
    }
    
    // Atualiza status da prova para 'rascunho' e remove liberação
    fetch(`<?= URL ?>/admin/provas/${provaId}/reprovar`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Prova reprovada.');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro ao reprovar prova'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
        console.error(error);
    });
}

function aprovarBlocoFinal(blocoId) {
    if (!confirm('Deseja fazer a aprovação final do bloco? Isso irá liberar todas as provas aprovadas para os alunos quando estiver na data e horário corretos.')) {
        return;
    }
    
    fetch(`<?= URL ?>/admin/provas/blocos/${blocoId}/aprovar-final`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Bloco aprovado e liberado com sucesso! As provas estarão disponíveis para os alunos na data e horário programados.');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro ao aprovar bloco'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
        console.error(error);
    });
}

function editarObsCoordenacao(provaId, obsAtual) {
    var novoTexto = prompt('Observação da coordenação para esta prova/matéria:', obsAtual || '');
    if (novoTexto === null) return;
    fetch(`<?= URL ?>/admin/provas/${provaId}/observacao-coordenacao`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            observacao_coordenacao: novoTexto,
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Observação salva com sucesso.');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Não foi possível salvar a observação.'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
    });
}
</script>
<?php endif; ?>
