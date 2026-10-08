<?php
$evento = $evento ?? [];
$materia = $materia ?? [];
$turmas_com_alunos = $turmas_com_alunos ?? [];
$notas_map = $notas_map ?? [];
$csrf_token = $csrf_token ?? '';
$materiaId = (int) ($materia['id'] ?? $_GET['materia_id'] ?? 0);
?>
<div class="mb-6">
    <a href="<?= URL ?>/professor/provas" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">← Voltar às provas</a>
</div>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Lançamento de notas</h1>
    <p class="text-gray-600 mt-2"><?= htmlspecialchars((string) ($evento['titulo'] ?? '')) ?> · <?= htmlspecialchars((string) ($materia['nome'] ?? '')) ?></p>
    <p class="text-sm text-gray-500 mt-1">Informe a nota de cada aluno (0 a 10). Deixe em branco para não lançar ainda. Use vírgula ou ponto para decimais.</p>
    <?php if (!empty($evento['nota_unica_todas_materias'])): ?>
    <div class="mt-3 px-3 py-2 rounded-lg bg-violet-100 border border-violet-200 text-violet-900 text-sm">
        Configuração ativa: mesma nota para todas as matérias. Ao salvar, a nota deste aluno será replicada para as outras matérias do evento.
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg <?= (!empty($flash['type']) && $flash['type'] === 'error') ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>

<form method="post" action="<?= URL ?>/professor/provas/evento-lancar-notas/<?= (int) ($evento['id'] ?? 0) ?>" class="space-y-8">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="materia_id" value="<?= $materiaId ?>">

    <?php foreach ($turmas_com_alunos as $blocoTurma): ?>
        <?php
        $tid = (int) ($blocoTurma['turma_id'] ?? 0);
        $tNome = (string) ($blocoTurma['turma_nome'] ?? ('Turma #' . $tid));
        $alunos = $blocoTurma['alunos'] ?? [];
        ?>
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-slate-50">
                <h2 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($tNome) ?></h2>
                <p class="text-sm text-gray-500"><?= count($alunos) ?> aluno(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-40">Nota (0–10)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php
                        if (!class_exists('AlunoLancamentoNotaHelper', false)) {
                            require_once __DIR__ . '/../../../Helpers/AlunoLancamentoNotaHelper.php';
                        }
                        foreach ($alunos as $al):
                            $aid = (int) ($al['id'] ?? 0);
                            $nk = $tid . '_' . $aid;
                            $nv = $notas_map[$nk] ?? null;
                            $notaStr = ($nv && isset($nv['nota']) && $nv['nota'] !== null) ? number_format((float) $nv['nota'], 2, '.', '') : '';
                            $transferido = !empty($al['transferido']);
                            $nomeAluno = AlunoLancamentoNotaHelper::rotuloNomeComTransferencia(
                                (string) ($al['nome'] ?? ''),
                                $transferido
                            );
                        ?>
                            <tr class="<?= $transferido ? 'bg-gray-100 text-gray-500' : 'hover:bg-gray-50' ?>">
                                <td class="px-4 py-3 font-medium <?= $transferido ? 'text-gray-500' : 'text-gray-900' ?>"><?= htmlspecialchars($nomeAluno) ?></td>
                                <td class="px-4 py-3">
                                    <?php if ($transferido): ?>
                                        <input type="text" inputmode="decimal" value="<?= htmlspecialchars($notaStr) ?>"
                                               class="w-full max-w-xs border border-gray-300 rounded-lg px-2 py-1.5 text-sm bg-gray-100 text-gray-500"
                                               placeholder="—" autocomplete="off" disabled readonly>
                                    <?php else: ?>
                                        <input type="text" inputmode="decimal" name="notas[<?= $tid ?>][<?= $aid ?>]" value="<?= htmlspecialchars($notaStr) ?>"
                                               class="w-full max-w-xs border border-gray-300 rounded-lg px-2 py-1.5 text-sm"
                                               placeholder="—" autocomplete="off">
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($turmas_com_alunos)): ?>
        <p class="text-gray-600">Nenhum aluno encontrado nas turmas deste evento para você.</p>
    <?php else: ?>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="inline-flex items-center px-6 py-3 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700">
                Salvar notas
            </button>
            <a href="<?= URL ?>/professor/provas" class="inline-flex items-center px-6 py-3 rounded-lg border border-gray-300 text-gray-700 font-medium hover:bg-gray-50">
                Cancelar
            </a>
        </div>
    <?php endif; ?>
</form>
