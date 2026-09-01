<?php
$esc = $esc ?? static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = $base ?? (URL . '/admin/students/' . (int) ($aluno_id ?? 0) . '/vida-escolar');
$token = (string) ($csrf_token ?? $token ?? '');
$quadro = is_array($quadro ?? null) ? $quadro : null;
$ficha = is_array($quadro['ficha'] ?? null) ? $quadro['ficha'] : null;
$grid = is_array($quadro['grid'] ?? null) ? $quadro['grid'] : [];
$periodos = is_array($periodos ?? null) ? $periodos : [1 => '1º', 2 => '2º', 3 => '3º', 4 => '4º', 0 => 'FINAL'];
$fichas = is_array($fichas ?? null) ? $fichas : [];
$editavelFicha = $ficha && ($ficha['status'] ?? '') !== 'homologada';
$fichaHomologada = (bool) $ficha && ($ficha['status'] ?? '') === 'homologada';
$podeAlterarBoletim = !empty($admin_permissions['vida_escolar']['alterar']);
$rotuloStatusFicha = [
    'homologada' => 'Homologada',
    'em_curso' => 'Em curso',
    'fechada' => 'Fechada',
];
$rotuloAcao = [
    'homologar' => 'Homologou',
    'reabrir' => 'Reabriu',
    'salvar_celula' => 'Salvou nota',
    'importar_externa' => 'Importou de outra escola',
    'fechar_bimestre' => 'Fechou bimestre',
    'alimentar_calculo' => 'Sincronizou eventos',
    'alimentar' => 'Sincronizou eventos',
    'garantir_ficha' => 'Criou a ficha',
];
$fmtNota = static function ($c): string {
    if (!is_array($c)) {
        return '—';
    }
    if (!empty($c['conceito'])) {
        return htmlspecialchars((string) $c['conceito'], ENT_QUOTES, 'UTF-8');
    }
    if ($c['nota'] === null || $c['nota'] === '') {
        return '—';
    }
    return number_format((float) $c['nota'], 1, ',', '');
};
?>
<?php if ($fichas !== []): ?>
<div class="mb-4 flex flex-wrap gap-2">
    <?php foreach ($fichas as $f): ?>
        <a href="<?= isset($hrefAba) && is_callable($hrefAba) ? $esc($hrefAba('boletim', ['ficha_id' => (int) $f['id']])) : ($base . '?aba=boletim&amp;ficha_id=' . (int) $f['id']) ?>"
           class="px-3 py-1.5 rounded-full text-xs font-medium <?= (int) $ficha_id === (int) $f['id'] ? 'bg-primary text-white' : 'bg-slate-100 text-slate-700' ?>">
            <?= (int) $f['ano_letivo'] ?> · <?= $esc($f['turma_nome'] ?? 'turma') ?> · <?= $esc($rotuloStatusFicha[(string) ($f['status'] ?? '')] ?? ($f['status'] ?? '')) ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-lg p-6">
    <div class="flex justify-between items-center mb-4 gap-3 flex-wrap">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Boletim oficial</h3>
            <p class="text-sm text-gray-500">Preenchido pelos eventos de notas. Cada bimestre: nota à esquerda, falta à direita. A FINAL segue a fórmula das regras acadêmicas. <span class="text-violet-700">¹</span> = escola anterior.</p>
            <?php if ($fichaHomologada): ?>
            <p class="mt-2 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Boletim homologado — notas travadas. Para corrigir, reabra o período abaixo (motivo obrigatório; fica na auditoria).</p>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="post" action="<?= $base ?>/garantir">
                <input type="hidden" name="_token" value="<?= $esc($token) ?>">
                <button class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Criar ficha do ano</button>
            </form>
            <?php if ($ficha): ?>
            <a href="<?= $base ?>/pdf?ficha_id=<?= (int) $ficha['id'] ?>" download class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Baixar boletim (PDF)</a>
            <?php endif; ?>
            <?php if ($ficha && $editavelFicha): ?>
            <form method="post" action="<?= $base ?>/alimentar">
                <input type="hidden" name="_token" value="<?= $esc($token) ?>">
                <input type="hidden" name="periodo_ref" value="">
                <input type="hidden" name="ficha_id" value="<?= (int) $ficha['id'] ?>">
                <button class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Sincronizar eventos</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$quadro): ?>
        <p class="text-sm text-gray-500">Nenhuma ficha neste ano. Vincule o aluno a uma turma ou clique em “Criar ficha do ano”.</p>
    <?php else: ?>
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Componente</th>
                        <?php foreach ([1, 2, 3, 4, 0] as $p): ?>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase" colspan="2"><?= $esc($periodos[$p] ?? $p) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th></th>
                        <?php foreach ([1, 2, 3, 4, 0] as $p): ?>
                            <th class="px-2 py-1 text-center text-[10px] text-gray-400">Nota</th>
                            <th class="px-2 py-1 text-center text-[10px] text-gray-400">Falta</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($grid as $row):
                        $linha = $row['linha'];
                        $cels = $row['celulas'];
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-medium text-gray-900 whitespace-nowrap"><?= $esc($linha['componente_nome'] ?? '') ?></td>
                        <?php foreach ([1, 2, 3, 4, 0] as $p):
                            $c = $cels[$p] ?? null;
                            $origem = (string) ($c['origem'] ?? 'vazia');
                            $aberta = $c && in_array($c['status'] ?? '', ['aberta', 'reaberta'], true) && $p !== 0;
                            $formId = $c ? ('ve-cel-' . (int) $c['id']) : '';
                            $faltasTxt = (is_array($c) && isset($c['faltas']) && $c['faltas'] !== null && $c['faltas'] !== '')
                                ? (string) (int) $c['faltas']
                                : '—';
                        ?>
                            <?php if ($aberta && $editavelFicha): ?>
                            <td class="px-1.5 py-1.5 text-center whitespace-nowrap">
                                <form id="<?= $esc($formId) ?>" method="post" action="<?= $base ?>/celula/<?= (int) $c['id'] ?>"></form>
                                <input type="hidden" form="<?= $esc($formId) ?>" name="_token" value="<?= $esc($token) ?>">
                                <span class="inline-flex items-center justify-center gap-1">
                                    <input form="<?= $esc($formId) ?>" name="nota" value="<?= $c['nota'] !== null && $c['nota'] !== '' ? $esc(number_format((float) $c['nota'], 1, ',', '')) : '' ?>" class="w-14 h-8 border border-gray-300 rounded-md px-1 text-center text-sm tabular-nums" placeholder="—">
                                    <?php if ($origem === 'externa'): ?><sup class="text-violet-700">¹</sup><?php endif; ?>
                                    <button type="submit" form="<?= $esc($formId) ?>" class="text-indigo-600 hover:text-indigo-800 p-0.5" title="Salvar" aria-label="Salvar">
                                        <i class="fa-solid fa-check text-xs"></i>
                                    </button>
                                </span>
                            </td>
                            <td class="px-1.5 py-1.5 text-center">
                                <input form="<?= $esc($formId) ?>" name="faltas" value="<?= $c['faltas'] !== null && $c['faltas'] !== '' ? (int) $c['faltas'] : '' ?>" class="w-12 h-8 border border-gray-300 rounded-md px-1 text-center text-sm tabular-nums" placeholder="—">
                            </td>
                            <?php else:
                                $notaTxt = $fmtNota($c);
                            ?>
                            <td class="px-2 py-2 text-center whitespace-nowrap tabular-nums">
                                <span class="font-medium text-gray-900"><?= $notaTxt ?></span><?php if ($origem === 'externa' && $notaTxt !== '—'): ?><sup class="text-violet-700">¹</sup><?php endif; ?>
                            </td>
                            <td class="px-2 py-2 text-center text-gray-600 tabular-nums"><?= $esc($faltasTxt) ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($grid === []): ?>
                    <tr><td colspan="11" class="px-3 py-6 text-center text-gray-500">Ficha sem componentes. Vincule uma matriz curricular à turma.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($podeAlterarBoletim): ?>
        <div class="mt-6 grid grid-cols-1 <?= $editavelFicha ? 'lg:grid-cols-2' : '' ?> gap-4">
            <?php if ($editavelFicha): ?>
            <div class="rounded-xl border border-gray-200 bg-slate-50 p-4">
                <h4 class="text-sm font-semibold text-gray-900">Encerrar período</h4>
                <p class="text-xs text-gray-500 mt-0.5 mb-3">Fecha um bimestre ou homologa o ano e trava as notas.</p>
                <div class="flex flex-col sm:flex-row sm:items-end gap-3">
                    <form method="post" action="<?= $base ?>/fechar-bimestre" class="flex-1 min-w-0 flex items-end gap-2">
                        <input type="hidden" name="_token" value="<?= $esc($token) ?>">
                        <input type="hidden" name="ficha_id" value="<?= (int) $ficha['id'] ?>">
                        <div class="flex-1 min-w-0">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bimestre</label>
                            <select name="bimestre" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?>º bimestre</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button class="shrink-0 h-10 px-4 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Fechar</button>
                    </form>
                    <form method="post" action="<?= $base ?>/homologar" class="shrink-0" onsubmit="return confirm('Homologar o boletim deste ano? As células preenchidas serão travadas.');">
                        <input type="hidden" name="_token" value="<?= $esc($token) ?>">
                        <input type="hidden" name="ficha_id" value="<?= (int) $ficha['id'] ?>">
                        <label class="hidden sm:block text-sm font-medium text-gray-700 mb-1">&nbsp;</label>
                        <button class="h-10 px-4 rounded-lg btn-primary-custom text-sm font-semibold">Homologar ano</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <div class="rounded-xl border <?= $fichaHomologada ? 'border-amber-200 bg-amber-50/60' : 'border-gray-200 bg-slate-50' ?> p-4">
                <h4 class="text-sm font-semibold text-gray-900">Corrigir notas</h4>
                <p class="text-xs text-gray-500 mt-0.5 mb-3">Reabre o período travado. O motivo é obrigatório e fica na auditoria.</p>
                <form method="post" action="<?= $base ?>/reabrir" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end" onsubmit="return confirm('Reabrir para correção? A versão atual permanece na auditoria.');">
                    <input type="hidden" name="_token" value="<?= $esc($token) ?>">
                    <input type="hidden" name="ficha_id" value="<?= (int) $ficha['id'] ?>">
                    <div class="sm:col-span-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Período</label>
                        <select name="bimestre" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                            <option value="-1">Ano inteiro</option>
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?>º bimestre</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                        <input name="motivo" required placeholder="Ex.: correção de digitação" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-3">
                        <button class="w-full h-10 px-4 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Reabrir</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($quadro['auditoria'])): ?>
        <div class="mt-6 rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                <h4 class="text-sm font-semibold text-gray-900">Auditoria</h4>
            </div>
            <ul class="divide-y divide-gray-100 max-h-56 overflow-y-auto">
                <?php foreach ($quadro['auditoria'] as $log): ?>
                    <?php $acao = (string) ($log['acao'] ?? ''); ?>
                    <li class="px-4 py-2.5 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        <span class="text-gray-500 tabular-nums w-36 shrink-0"><?= !empty($log['created_at']) ? $esc(date('d/m/Y H:i', strtotime((string) $log['created_at']))) : '—' ?></span>
                        <span class="font-medium text-gray-900 w-44 shrink-0"><?= $esc($rotuloAcao[$acao] ?? $acao) ?></span>
                        <span class="text-gray-600 min-w-0">
                            <?= $esc($log['usuario_nome'] ?? '') ?>
                            <?php if (!empty($log['motivo'])): ?>
                                <span class="text-gray-400"> — <?= $esc($log['motivo']) ?></span>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
