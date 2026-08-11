<?php
$statusBadge = static function (string $status): string {
    $map = [
        'pendente'           => ['Pendente', 'bg-amber-100 text-amber-700'],
        'aprovada_professor' => ['Aprov. professor', 'bg-blue-100 text-blue-700'],
        'aprovada_aee'       => ['Aprov. AEE', 'bg-blue-100 text-blue-700'],
        'aprovada'           => ['Aprovada', 'bg-green-100 text-green-700'],
        'invalidada_drift'   => ['Invalidada (drift)', 'bg-red-100 text-red-700'],
        'rejeitada'          => ['Rejeitada', 'bg-red-100 text-red-700'],
    ];
    [$label, $cls] = $map[$status] ?? ['—', 'bg-gray-100 text-gray-700'];
    return '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium ' . $cls . '">' . $label . '</span>';
};
?>
<div class="space-y-6">
    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div class="max-w-4xl">
                <h1 class="text-2xl font-bold text-gray-900 mb-1">EducaInclui — Versões adaptadas</h1>
                <p class="text-sm text-gray-600">Provas clonadas geradas para alunos com adaptação <strong>significativa</strong> (ex.: redução de questões). A versão só é entregue ao aluno depois de <strong>aprovada</strong> aqui.</p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-3 xl:justify-end">
                <form method="post" action="<?= URL ?>/admin/inclusao/versoes/gerar" onsubmit="return educaIncluiGerarDiffsLoading(this, 'Gerar agora as versões adaptadas que faltam para as provas já aprovadas dos alunos com máscara significativa?');">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                    <button type="submit" class="btn-primary-custom inline-flex min-w-[190px] items-center justify-center gap-2 whitespace-nowrap px-4 py-2.5 rounded-lg hover:opacity-90 text-sm font-medium leading-none">
                        <i class="fa-solid fa-rotate mr-1"></i> Gerar todos os diffs
                    </button>
                </form>
                <a href="<?= URL ?>/admin/inclusao" class="inline-flex items-center gap-1 whitespace-nowrap text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                    <i class="fa-solid fa-arrow-left text-xs"></i> Voltar
                </a>
            </div>
        </div>
        <p class="mt-4 max-w-6xl text-xs leading-relaxed text-gray-500">Use <strong>Gerar todos os diffs</strong> para varrer provas ativas ou futuras dos alunos com máscara significativa salva ou ativa e criar as versões adaptadas que ainda faltam. Provas com data final já vencida não entram nessa geração. Se tiver reescrita por IA, ela pode levar alguns minutos para aparecer no comparativo.</p>
    </div>

    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-lg px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars((string) $flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">Aguardando aprovação</h2>
        </div>
        <?php if (empty($pendentes)): ?>
            <p class="px-6 py-8 text-sm text-gray-500 text-center">Nenhuma versão pendente de aprovação.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold text-gray-500 uppercase">
                            <th class="px-6 py-3">Aluno</th>
                            <th class="px-6 py-3">Prova original</th>
                            <th class="px-6 py-3">Tipo</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Gerada em</th>
                            <th class="px-6 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($pendentes as $v): ?>
                            <tr>
                                <td class="px-6 py-3 font-medium text-gray-900"><?= htmlspecialchars((string) ($v['aluno_nome'] ?? ('Aluno #' . (int) $v['aluno_id']))) ?></td>
                                <td class="px-6 py-3 text-gray-600"><?= htmlspecialchars((string) ($v['prova_titulo'] ?? ('Prova #' . (int) $v['prova_id']))) ?></td>
                                <td class="px-6 py-3 text-gray-600"><?= $v['tipo_versao'] === 'significativa' ? 'Significativa' : 'Acesso' ?></td>
                                <td class="px-6 py-3"><?= $statusBadge((string) $v['status_aprovacao']) ?></td>
                                <td class="px-6 py-3 text-gray-500"><?= !empty($v['created_at']) ? date('d/m/Y H:i', strtotime((string) $v['created_at'])) : '—' ?></td>
                                <td class="px-6 py-3">
                                    <div class="flex gap-2 justify-end">
                                        <a href="<?= URL ?>/admin/inclusao/versoes/<?= (int) $v['id'] ?>/diff" class="px-3 py-1.5 bg-indigo-100 hover:bg-indigo-200 text-indigo-700 rounded-lg text-xs font-medium">Ver diff</a>
                                        <a href="<?= URL ?>/admin/inclusao/versoes/<?= (int) $v['id'] ?>/pdf" target="_blank" rel="noopener" class="px-3 py-1.5 bg-rose-100 hover:bg-rose-200 text-rose-700 rounded-lg text-xs font-medium">
                                            <i class="fa-regular fa-file-pdf mr-1"></i> PDF
                                        </a>
                                        <form method="post" action="<?= URL ?>/admin/inclusao/versoes/aprovar" onsubmit="return confirm('Aprovar esta versão? Ela passará a ser entregue ao aluno.');">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                                            <input type="hidden" name="version_id" value="<?= (int) $v['id'] ?>">
                                            <input type="hidden" name="acao" value="aprovar">
                                            <button type="submit" class="btn-primary-custom px-3 py-1.5 rounded-lg hover:opacity-90 text-xs font-medium">Aprovar</button>
                                        </form>
                                        <form method="post" action="<?= URL ?>/admin/inclusao/versoes/aprovar" onsubmit="return confirm('Reprovar esta versão?');">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                                            <input type="hidden" name="version_id" value="<?= (int) $v['id'] ?>">
                                            <input type="hidden" name="acao" value="reprovar">
                                            <button type="submit" class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-xs font-medium">Reprovar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">Aprovadas</h2>
            <p class="text-xs text-gray-500 mt-1">Versões já aprovadas e entregues ao aluno. Ficam aqui para consulta.</p>
        </div>
        <?php if (empty($aprovadas)): ?>
            <p class="px-6 py-8 text-sm text-gray-500 text-center">Nenhuma versão aprovada ainda.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold text-gray-500 uppercase">
                            <th class="px-6 py-3">Aluno</th>
                            <th class="px-6 py-3">Prova original</th>
                            <th class="px-6 py-3">Tipo</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Aprovada em</th>
                            <th class="px-6 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($aprovadas as $v): ?>
                            <tr>
                                <td class="px-6 py-3 font-medium text-gray-900"><?= htmlspecialchars((string) ($v['aluno_nome'] ?? ('Aluno #' . (int) $v['aluno_id']))) ?></td>
                                <td class="px-6 py-3 text-gray-600"><?= htmlspecialchars((string) ($v['prova_titulo'] ?? ('Prova #' . (int) $v['prova_id']))) ?></td>
                                <td class="px-6 py-3 text-gray-600"><?= $v['tipo_versao'] === 'significativa' ? 'Significativa' : 'Acesso' ?></td>
                                <td class="px-6 py-3"><?= $statusBadge((string) $v['status_aprovacao']) ?></td>
                                <td class="px-6 py-3 text-gray-500"><?= !empty($v['updated_at']) ? date('d/m/Y H:i', strtotime((string) $v['updated_at'])) : '—' ?></td>
                                <td class="px-6 py-3">
                                    <div class="flex gap-2 justify-end">
                                        <a href="<?= URL ?>/admin/inclusao/versoes/<?= (int) $v['id'] ?>/diff" class="px-3 py-1.5 bg-indigo-100 hover:bg-indigo-200 text-indigo-700 rounded-lg text-xs font-medium">Ver diff</a>
                                        <a href="<?= URL ?>/admin/inclusao/versoes/<?= (int) $v['id'] ?>/pdf" target="_blank" rel="noopener" class="px-3 py-1.5 bg-rose-100 hover:bg-rose-200 text-rose-700 rounded-lg text-xs font-medium">
                                            <i class="fa-regular fa-file-pdf mr-1"></i> PDF
                                        </a>
                                        <form method="post" action="<?= URL ?>/admin/inclusao/versoes/aprovar" onsubmit="return confirm('Reprovar esta versão? Ela deixará de ser entregue ao aluno.');">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                                            <input type="hidden" name="version_id" value="<?= (int) $v['id'] ?>">
                                            <input type="hidden" name="acao" value="reprovar">
                                            <button type="submit" class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-xs font-medium">Reprovar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="educaIncluiDiffLoading" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-gray-900/50 px-4">
    <div class="bg-white rounded-xl shadow-xl border border-gray-200 p-6 max-w-sm w-full text-center">
        <div class="mx-auto mb-4 h-10 w-10 rounded-full border-4 border-indigo-100 border-t-indigo-600 animate-spin"></div>
        <p class="text-base font-semibold text-gray-900">Gerando diffs...</p>
        <p class="mt-1 text-sm text-gray-500">Aguarde enquanto as versões adaptadas e reescritas por IA são processadas.</p>
    </div>
</div>
<script>
function educaIncluiGerarDiffsLoading(form, message) {
    if (!confirm(message)) {
        return false;
    }
    var button = form.querySelector('button[type="submit"]');
    if (button) {
        button.disabled = true;
        button.classList.add('opacity-70', 'cursor-wait');
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Gerando...';
    }
    var overlay = document.getElementById('educaIncluiDiffLoading');
    if (overlay) {
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
    }
    return true;
}
</script>
