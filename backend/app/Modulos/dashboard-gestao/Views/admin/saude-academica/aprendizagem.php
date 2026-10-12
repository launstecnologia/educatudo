<?php
$filtros = is_array($filtros ?? null) ? $filtros : [];
$anos_letivo = is_array($anos_letivo ?? null) ? $anos_letivo : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$kpis = is_array($kpis ?? null) ? $kpis : [];
$linhas = is_array($linhas ?? null) ? $linhas : [];
$fontes = is_array($fontes ?? null) ? $fontes : [];
$regras = is_array($regras ?? null) ? $regras : [];
$niveis = is_array($niveis ?? null) ? $niveis : [];
$executar = !empty($filtros['executar']);
$anoSel = (int) ($filtros['ano_letivo_id'] ?? 0);
$turmaSel = (int) ($filtros['turma_id'] ?? 0);
$nivelSel = (string) ($filtros['nivel'] ?? '');

$nivelClasses = [
    'critico' => 'bg-red-100 text-red-800 border-red-200',
    'atencao' => 'bg-amber-100 text-amber-900 border-amber-200',
    'monitorar' => 'bg-blue-100 text-blue-800 border-blue-200',
    'saudavel' => 'bg-green-100 text-green-800 border-green-200',
    'sem_dados' => 'bg-gray-100 text-gray-700 border-gray-200',
];
$cardClasses = [
    'critico' => ['border-red-200', 'text-red-700', 'fa-triangle-exclamation'],
    'atencao' => ['border-amber-200', 'text-amber-700', 'fa-circle-exclamation'],
    'monitorar' => ['border-blue-200', 'text-blue-700', 'fa-eye'],
    'saudavel' => ['border-green-200', 'text-green-700', 'fa-circle-check'],
    'sem_dados' => ['border-gray-200', 'text-gray-600', 'fa-circle-question'],
];
$queryNivel = static function (string $nivel) use ($filtros): string {
    $params = [
        'aba' => 'aprendizagem',
        'ano_letivo_id' => (int) ($filtros['ano_letivo_id'] ?? 0),
        'turma_id' => (int) ($filtros['turma_id'] ?? 0),
        'nivel' => $nivel,
        'executar' => 1,
    ];
    if ($params['turma_id'] <= 0) unset($params['turma_id']);
    if ($nivel === '') unset($params['nivel']);
    return URL . '/admin/saude-academica?' . http_build_query($params);
};
$formatPct = static fn ($value): string => $value === null ? '—' : number_format((float) $value, 1, ',', '.') . '%';
$formatNumber = static function ($value): string {
    if ($value === null) return '—';
    $v = (float) $value;
    return number_format($v, fmod($v, 1.0) === 0.0 ? 0 : 1, ',', '.');
};
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-1">Saúde Acadêmica</h2>
    <p class="text-gray-600 text-sm">Priorização pedagógica baseada em boletim/notas, exercícios, jornadas e faltas.</p>
</div>

<div class="mb-6 border-b border-gray-200">
    <nav class="flex flex-wrap gap-2" aria-label="Áreas da Saúde Acadêmica">
        <a href="<?= URL ?>/admin/saude-academica"
           class="px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300">
            <i class="fa-solid fa-link mr-2"></i>Saúde cadastral
        </a>
        <a href="<?= URL ?>/admin/saude-academica?aba=aprendizagem"
           class="px-4 py-3 text-sm font-semibold border-b-2 border-blue-600 text-blue-700">
            <i class="fa-solid fa-chart-line mr-2"></i>Saúde da aprendizagem
        </a>
    </nav>
</div>

<div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-950">
    <div class="flex gap-3">
        <i class="fa-solid fa-circle-info mt-0.5 text-blue-600"></i>
        <div>
            <strong>Ferramenta de apoio, não de punição.</strong>
            A classificação apenas ajuda a coordenação a priorizar acompanhamento. Ela não altera notas, não reprova e sempre deve ser interpretada por uma pessoa.
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6 mb-6">
    <form method="get" action="<?= URL ?>/admin/saude-academica" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <input type="hidden" name="aba" value="aprendizagem">
        <input type="hidden" name="executar" value="1">
        <div>
            <label for="ano_letivo_id" class="block text-sm font-medium text-gray-700 mb-1">Ano letivo</label>
            <select id="ano_letivo_id" name="ano_letivo_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <?php if (empty($anos_letivo)): ?>
                <option value="">Nenhum ano letivo ativo</option>
                <?php else: foreach ($anos_letivo as $ano): ?>
                <option value="<?= (int) ($ano['id'] ?? 0) ?>" <?= $anoSel === (int) ($ano['id'] ?? 0) ? 'selected' : '' ?>><?= (int) ($ano['ano'] ?? 0) ?></option>
                <?php endforeach; endif; ?>
            </select>
        </div>
        <div>
            <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-1">Turma (opcional)</label>
            <select id="turma_id" name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Todas</option>
                <?php foreach ($turmas as $t): ?>
                <option value="<?= (int) ($t['id'] ?? 0) ?>" <?= $turmaSel === (int) ($t['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($t['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="nivel" class="block text-sm font-medium text-gray-700 mb-1">Prioridade</label>
            <select id="nivel" name="nivel" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Todas</option>
                <?php foreach ($niveis as $key => $label): ?>
                <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $nivelSel === $key ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
            <i class="fa-solid fa-magnifying-glass mr-2"></i>Analisar
        </button>
    </form>
</div>

<?php if ($executar): ?>
<div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
    <a href="<?= htmlspecialchars($queryNivel(''), ENT_QUOTES, 'UTF-8') ?>" class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:border-gray-400">
        <p class="text-xs font-medium text-gray-500 uppercase">Alunos analisados</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int) ($kpis['total'] ?? 0) ?></p>
    </a>
    <?php foreach ($niveis as $key => $label): $cc = $cardClasses[$key] ?? $cardClasses['sem_dados']; ?>
    <a href="<?= htmlspecialchars($queryNivel($key), ENT_QUOTES, 'UTF-8') ?>" class="bg-white rounded-xl border <?= $cc[0] ?> p-4 shadow-sm hover:shadow-md transition-shadow">
        <p class="text-xs font-medium text-gray-500 uppercase leading-tight"><i class="fa-solid <?= $cc[2] ?> mr-1"></i><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
        <p class="text-2xl font-bold <?= $cc[1] ?> mt-1"><?= (int) ($kpis[$key] ?? 0) ?></p>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($executar && in_array(false, $fontes, true)): ?>
<div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
    Algumas fontes ainda não estão disponíveis neste banco. A análise usou somente:
    <?php
    $fontesLabels = ['boletim' => 'boletim e eventos de notas', 'provas' => 'provas on-line', 'exercicios' => 'exercícios', 'jornadas' => 'jornadas', 'faltas' => 'faltas'];
    $ativas = [];
    foreach ($fontesLabels as $k => $label) if (!empty($fontes[$k])) $ativas[] = $label;
    ?>
    <strong><?= htmlspecialchars(implode(', ', $ativas) ?: 'nenhuma fonte', ENT_QUOTES, 'UTF-8') ?></strong>.
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <h3 class="text-base font-semibold text-gray-900">Prioridades de acompanhamento</h3>
            <p class="text-sm text-gray-500 mt-1">
                <?= !$executar ? 'Selecione os filtros e clique em Analisar.' : count($linhas) . ' aluno(s) no filtro atual. Limite de 500 alunos.' ?>
            </p>
        </div>
        <button type="button" onclick="document.getElementById('criterios-saude').classList.toggle('hidden')" class="text-sm font-medium text-blue-700 hover:text-blue-900">
            <i class="fa-solid fa-sliders mr-1"></i>Como é calculado
        </button>
    </div>

    <div id="criterios-saude" class="hidden border-b border-gray-100 bg-gray-50 px-4 sm:px-6 py-4 text-sm text-gray-700">
        <p class="font-semibold text-gray-900 mb-2">Regras do MVP</p>
        <ul class="list-disc pl-5 space-y-1">
            <li>O boletim oficial é a fonte principal. Se ainda não existir, são usadas notas dos eventos; provas on-line entram somente como último fallback.</li>
            <li>Resultado consolidado abaixo de 70% da média mínima ou metade das linhas abaixo da média: sinal alto.</li>
            <li>Quando só existem provas on-line: aproveitamento abaixo de 40% é sinal alto; abaixo de 60% é atenção.</li>
            <li>Média de pelo menos 2 exercícios abaixo de 40%: sinal alto; abaixo de 60%: monitoramento.</li>
            <li>Jornadas abaixo de 30%: sinal alto; abaixo de 60%: monitoramento.</li>
            <li>20 ou mais faltas: sinal alto; 10 ou mais: monitoramento.</li>
            <li>Crítico exige combinação de sinais (4 pontos ou mais). Sem atividade registrada, o aluno aparece como “Sem dados”.</li>
        </ul>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Notas / boletim</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Exercícios</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Jornadas</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Faltas</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prioridade e motivos</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                <?php if (!$executar): ?>
                <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500"><i class="fa-solid fa-chart-line text-4xl text-gray-300 mb-3 block"></i>Nenhuma análise executada ainda.</td></tr>
                <?php elseif (empty($linhas)): ?>
                <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">Nenhum aluno encontrado para os filtros selecionados.</td></tr>
                <?php else: foreach ($linhas as $ln):
                    $nivel = (string) ($ln['nivel'] ?? 'sem_dados');
                    $motivos = is_array($ln['motivos'] ?? null) ? $ln['motivos'] : [];
                ?>
                <tr class="hover:bg-gray-50/80 align-top">
                    <td class="px-4 py-3">
                        <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars((string) ($ln['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars((string) ($ln['ra'] ?? 'Sem RA'), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($ln['turma_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    </td>
                    <td class="px-4 py-3 text-center text-sm">
                        <?php if (($ln['notas_media'] ?? null) !== null): ?>
                        <strong><?= number_format((float) $ln['notas_media'], 1, ',', '.') ?></strong>
                        <span class="block text-xs text-gray-500">mínima <?= number_format((float) ($ln['notas_minima'] ?? 6), 1, ',', '.') ?> · <?= (int) ($ln['notas_abaixo'] ?? 0) ?> abaixo</span>
                        <span class="block text-[11px] text-gray-400"><?= ($ln['notas_fonte'] ?? '') === 'boletim' ? 'Boletim oficial' : 'Evento de notas' ?></span>
                        <?php else: ?>
                        <strong><?= $formatPct($ln['provas_media_pct'] ?? null) ?></strong>
                        <span class="block text-xs text-gray-500"><?= (int) ($ln['provas_total'] ?? 0) ?> prova(s) on-line</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center text-sm">
                        <strong><?= $formatPct($ln['exercicios_media_pct'] ?? null) ?></strong>
                        <span class="block text-xs text-gray-500"><?= (int) ($ln['exercicios_total'] ?? 0) ?> lista(s)</span>
                    </td>
                    <td class="px-4 py-3 text-center text-sm">
                        <strong><?= $formatPct($ln['jornadas_progresso_pct'] ?? null) ?></strong>
                        <span class="block text-xs text-gray-500"><?= (int) ($ln['jornadas_modulos_concluidos'] ?? 0) ?>/<?= (int) ($ln['jornadas_modulos_total'] ?? 0) ?> módulos</span>
                    </td>
                    <td class="px-4 py-3 text-center text-sm"><strong><?= $formatNumber($ln['faltas_total'] ?? null) ?></strong></td>
                    <td class="px-4 py-3 min-w-72">
                        <span class="inline-flex items-center px-2 py-1 rounded-full border text-xs font-semibold <?= $nivelClasses[$nivel] ?? $nivelClasses['sem_dados'] ?>">
                            <?= htmlspecialchars($niveis[$nivel] ?? $nivel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php if ($motivos): ?>
                        <ul class="mt-2 space-y-1">
                            <?php foreach ($motivos as $motivo): ?>
                            <li class="text-xs text-gray-600"><i class="fa-solid fa-angle-right mr-1 text-gray-400"></i><?= htmlspecialchars((string) ($motivo['texto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php elseif ($nivel === 'saudavel'): ?>
                        <p class="mt-2 text-xs text-green-700">Nenhum sinal ultrapassou os limites atuais.</p>
                        <?php else: ?>
                        <p class="mt-2 text-xs text-gray-500">Ainda não há atividade suficiente para classificar.</p>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <a href="<?= URL ?>/admin/students/<?= (int) ($ln['aluno_id'] ?? 0) ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 text-xs font-medium hover:bg-blue-100">
                            <i class="fa-solid fa-user-graduate"></i>Ficha do aluno
                        </a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
