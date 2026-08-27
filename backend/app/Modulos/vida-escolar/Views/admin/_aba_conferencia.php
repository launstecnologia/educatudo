<?php
$esc = $esc ?? static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sed = is_array($sed ?? null) ? $sed : ['itens' => []];
$inep = is_array($inep ?? null) ? $inep : ['edicoes' => []];
$links = is_array($links ?? null) ? $links : [];
$resultados = is_array($resultados ?? null) ? $resultados : [];
?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex justify-between items-start gap-3 flex-wrap mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Conferência SED (São Paulo)</h3>
            <p class="text-sm text-gray-500">Não há API pública. Use a planilha para lançar no portal da SED. <?= (int) ($sed['ok_qtd'] ?? 0) ?>/<?= (int) ($sed['total'] ?? 0) ?> campos preenchidos.</p>
        </div>
        <a href="<?= URL . ($links['sed'] ?? '#') ?>" download class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Baixar planilha SED (PDF)</a>
    </div>
    <ul class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
        <?php foreach ($sed['itens'] ?? [] as $item): ?>
        <li class="flex items-center justify-between gap-2 border border-gray-100 rounded-lg px-3 py-2">
            <span><?= $esc($item['mensagem'] ?? '') ?><?php if (empty($item['obrigatorio'])): ?> <span class="text-xs text-gray-400">(opcional)</span><?php endif; ?></span>
            <?php if (!empty($item['ok'])): ?>
                <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-800">Ok</span>
            <?php else: ?>
                <span class="text-xs px-2 py-0.5 rounded-full <?= !empty($item['obrigatorio']) ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' ?>">Falta</span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php if (empty($sed['ok'])): ?>
    <p class="mt-3 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Complete os campos obrigatórios no cadastro do aluno e na unidade escolar antes de lançar na SED.</p>
    <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex justify-between items-start gap-3 flex-wrap mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Educacenso / INEP</h3>
            <p class="text-sm text-gray-500"><?= $esc($inep['resumo'] ?? '') ?>. O TXT oficial é gerado no módulo Censo (escola inteira).</p>
        </div>
        <a href="<?= URL . ($links['censo'] ?? '/admin/censo') ?>" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Abrir Censo Escolar</a>
    </div>
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm mb-4">
        <div>
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">INEP da escola</dt>
            <dd class="mt-0.5 font-medium"><?= $esc(($inep['codigo_escola'] ?? '') !== '' ? $inep['codigo_escola'] : 'Não informado') ?></dd>
        </div>
        <div>
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">INEP do aluno</dt>
            <dd class="mt-0.5 font-medium"><?= $esc(($inep['codigo_aluno'] ?? '') !== '' ? $inep['codigo_aluno'] : 'Gerado pelo Educacenso se vazio') ?></dd>
        </div>
    </dl>
    <?php if (($inep['edicoes'] ?? []) === []): ?>
        <p class="text-sm text-gray-500">Nenhuma edição do Censo neste tenant. Crie em Gestão Escolar → Censo Escolar.</p>
    <?php else: ?>
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ano</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Etapa</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($inep['edicoes'] as $ed): ?>
                    <tr>
                        <td class="px-3 py-2"><?= (int) ($ed['ano'] ?? 0) ?></td>
                        <td class="px-3 py-2"><?= $esc($ed['etapa'] ?? '') ?></td>
                        <td class="px-3 py-2"><?= $esc($ed['status'] ?? '') ?></td>
                        <td class="px-3 py-2">
                            <?php if (!empty($ed['matricula'])): ?>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-800">Na matrícula inicial</span>
                            <?php else: ?>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Não sincronizado</span>
                            <?php endif; ?>
                            <?php if (($ed['situacao'] ?? '') !== ''): ?>
                                <span class="text-xs text-gray-500 ml-1"><?= $esc($ed['situacao']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-lg p-6">
    <div class="flex justify-between items-start gap-3 flex-wrap mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Resultados homologados</h3>
            <p class="text-sm text-gray-500">Snapshot de Resultados Finais (ata, ficha individual e boletim de fechamento).</p>
        </div>
        <a href="<?= URL . ($links['resultados_finais'] ?? '/admin/resultados-finais') ?>" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Resultados finais</a>
    </div>
    <?php if ($resultados === []): ?>
        <p class="text-sm text-gray-500">Nenhum resultado homologado ainda.</p>
    <?php else: ?>
        <ul class="text-sm divide-y divide-gray-100">
            <?php foreach ($resultados as $r): ?>
            <li class="py-2 flex justify-between gap-3">
                <span><?= (int) ($r['ano_letivo'] ?? 0) ?> · <?= $esc($r['turma_nome'] ?? '') ?> · <?= $esc($r['periodo_tipo'] ?? '') ?> <?= (int) ($r['periodo_numero'] ?? 0) ?></span>
                <span class="text-xs px-2 py-0.5 rounded-full <?= ($r['status'] ?? '') === 'homologado' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-700' ?>"><?= $esc($r['status'] ?? '') ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
