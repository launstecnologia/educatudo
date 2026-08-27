<?php
$student = is_array($student ?? null) ? $student : [];
$documentos = is_array($documentos ?? null) ? $documentos : [];
$checklist = is_array($checklist ?? null) ? $checklist : ['ok' => false, 'itens' => []];
$schemaReady = !empty($schema_ready);
$alunoId = (int) ($student['id'] ?? 0);
$statusCores = [
    'Rascunho' => 'bg-slate-100 text-slate-700',
    'Conferido' => 'bg-amber-100 text-amber-800',
    'Emitido' => 'bg-blue-100 text-blue-800',
    'Assinado' => 'bg-emerald-100 text-emerald-800',
    'Entregue' => 'bg-emerald-100 text-emerald-900',
    'Cancelado' => 'bg-red-100 text-red-700',
];
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <a href="<?= URL ?>/admin/students/<?= $alunoId ?>" class="text-sm text-slate-500 hover:text-slate-700">
                <i class="fa-solid fa-arrow-left mr-1"></i> Voltar ao aluno
            </a>
            <h1 class="text-2xl font-bold text-slate-900 mt-1">Histórico Escolar</h1>
            <p class="text-sm text-slate-600"><?= htmlspecialchars((string) ($student['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>

    <?php if (!empty($flash_message)): ?>
        <div class="rounded-lg px-4 py-3 text-sm <?= ($flash_type ?? '') === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' ?>">
            <?= htmlspecialchars((string) $flash_message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!$schemaReady): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900 text-sm">
            A migration <code>2026_07_15_historico_escolar_oficial</code> ainda não foi aplicada neste tenant.
            Execute pelo painel Master → Migrations.
        </div>
    <?php else: ?>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-slate-800 mb-3">
            <i class="fa-solid fa-clipboard-check text-emerald-600 mr-1"></i>
            Checklist pré-emissão
            <?php if (!empty($checklist['ok'])): ?>
                <span class="ml-2 text-xs font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">Pronto para emitir</span>
            <?php else: ?>
                <span class="ml-2 text-xs font-medium text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full">Pendências</span>
            <?php endif; ?>
        </h2>
        <ul class="grid sm:grid-cols-2 gap-2">
            <?php foreach (($checklist['itens'] ?? []) as $item): ?>
                <li class="flex items-start gap-2 text-sm <?= !empty($item['ok']) ? 'text-slate-700' : 'text-amber-800' ?>">
                    <i class="fa-solid <?= !empty($item['ok']) ? 'fa-circle-check text-emerald-500' : 'fa-circle-exclamation text-amber-500' ?> mt-0.5"></i>
                    <span><?= htmlspecialchars((string) ($item['mensagem'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-slate-800 mb-3">Gerar / atualizar rascunho</h2>
        <p class="text-xs text-slate-500 mb-4">Consolida notas dos boletins gerados do aluno. Se já existir rascunho/conferido, ele é atualizado.</p>
        <form method="post" action="<?= URL ?>/admin/students/<?= $alunoId ?>/historico-escolar/gerar" class="flex flex-col sm:flex-row gap-3 items-end">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <div class="flex-1 w-full">
                <label class="block text-xs font-medium text-slate-600 mb-1">Finalidade</label>
                <select name="finalidade" class="w-full border border-slate-300 rounded-lg px-3 py-2 bg-white text-sm">
                    <option value="Solicitacao">Solicitação</option>
                    <option value="Transferencia">Transferência</option>
                    <option value="Conclusao">Conclusão de etapa</option>
                </select>
            </div>
            <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm">
                <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Gerar rascunho
            </button>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-800">Versões do histórico</h2>
        </div>
        <?php if ($documentos === []): ?>
            <p class="p-5 text-sm text-slate-500">Nenhuma versão gerada ainda.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600 text-left">
                        <tr>
                            <th class="px-4 py-2 font-medium">Versão</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 font-medium">Finalidade</th>
                            <th class="px-4 py-2 font-medium">Emitido em</th>
                            <th class="px-4 py-2 font-medium text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($documentos as $doc): ?>
                            <?php
                            $st = (string) ($doc['status'] ?? '');
                            $badge = $statusCores[$st] ?? 'bg-slate-100 text-slate-700';
                            ?>
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-800">v<?= (int) ($doc['versao'] ?? 1) ?></td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $badge ?>"><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars((string) ($doc['finalidade'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-slate-600">
                                    <?php
                                    if (!empty($doc['emitido_em'])) {
                                        $ts = strtotime((string) $doc['emitido_em']);
                                        echo $ts ? htmlspecialchars(date('d/m/Y H:i', $ts), ENT_QUOTES, 'UTF-8') : '—';
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <a href="<?= URL ?>/admin/students/<?= $alunoId ?>/historico-escolar/<?= (int) $doc['id'] ?>"
                                       class="text-blue-600 hover:text-blue-800 font-medium">Abrir</a>
                                    <?php if (in_array($st, ['Emitido', 'Assinado', 'Entregue', 'Cancelado'], true)): ?>
                                        <a href="<?= URL ?>/admin/students/<?= $alunoId ?>/historico-escolar/<?= (int) $doc['id'] ?>/pdf" download
                                           class="text-rose-600 hover:text-rose-800 font-medium">PDF</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
