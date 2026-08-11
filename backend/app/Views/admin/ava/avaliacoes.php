<?php
$disciplina = $disciplina ?? [];
$avaliacoes = $avaliacoes ?? [];
$provas_disponiveis = $provas_disponiveis ?? [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$flash_status = (string) ($flash_type ?? '');
$dId = (int) ($disciplina['id'] ?? 0);

$page_header_back_url = URL . '/admin/ava/disciplinas/' . $dId;
$page_header_title = 'Avaliações';
$page_header_subtitle = 'Vincule uma prova existente e libere-a quando o aluno atingir o progresso definido — ' . (string) ($disciplina['nome'] ?? '');
include __DIR__ . '/../_partials/page_header_form.php';
include __DIR__ . '/../_partials/flash_message.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Cadastro (único nesta tela): vincular prova -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Vincular avaliação</h3>
            <?php if (empty($provas_disponiveis)): ?>
                <p class="text-sm text-gray-500">Nenhuma prova disponível para vincular. Crie uma prova no módulo <strong>Provas</strong> primeiro.</p>
            <?php else: ?>
            <form method="post" action="<?= URL ?>/admin/ava/disciplinas/<?= $dId ?>/avaliacoes" class="space-y-4">
                <input type="hidden" name="_token" value="<?= $csrf ?>">
                <div>
                    <label for="prova_id" class="block text-sm font-medium text-gray-700 mb-2">Prova <span class="text-red-500">*</span></label>
                    <select id="prova_id" name="prova_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">Selecione…</option>
                        <?php foreach ($provas_disponiveis as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars((string) $p['titulo']) ?><?= !empty($p['materia_nome']) ? ' — ' . htmlspecialchars((string) $p['materia_nome']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="titulo" class="block text-sm font-medium text-gray-700 mb-2">Rótulo (opcional)</label>
                    <input type="text" id="titulo" name="titulo" placeholder="Ex.: Avaliação Final"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="requisito_progresso_pct" class="block text-sm font-medium text-gray-700 mb-2">Liberar a partir de (% de progresso)</label>
                    <input type="number" id="requisito_progresso_pct" name="requisito_progresso_pct" value="80" min="0" max="100" step="1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <p class="text-xs text-gray-500 mt-1">O aluno só faz a avaliação após atingir esse progresso na disciplina.</p>
                </div>
                <div>
                    <label for="peso" class="block text-sm font-medium text-gray-700 mb-2">Peso na nota final</label>
                    <input type="number" id="peso" name="peso" value="1" min="0" step="0.1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="obrigatoria" value="1" checked class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                    Obrigatória
                </label>
                <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                    <i class="fa-solid fa-plus mr-2"></i> Vincular avaliação
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lista -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avaliação</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Libera em</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Peso</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status prova</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($avaliacoes)): ?>
                            <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500"><i class="fa-solid fa-file-pen text-3xl text-gray-300 mb-3"></i><p>Nenhuma avaliação vinculada.</p></td></tr>
                        <?php else: foreach ($avaliacoes as $av):
                            $titulo = ($av['titulo'] ?? null) ?: ($av['prova_titulo'] ?? 'Avaliação');
                            $liberadaProva = (int) ($av['prova_liberada'] ?? 0) === 1 && (int) ($av['prova_ativo'] ?? 0) === 1;
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $titulo) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars((string) ($av['prova_titulo'] ?? '')) ?><?= ($av['obrigatoria'] ?? 1) ? ' · obrigatória' : ' · opcional' ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?= (float) ($av['requisito_progresso_pct'] ?? 0) ?>%</td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?= (float) ($av['peso'] ?? 1) ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($liberadaProva): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Liberada</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Não liberada</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <?php ob_start(); ?>
                                    <a href="<?= URL ?>/admin/provas/visualizar/<?= (int) $av['prova_id'] ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <i class="fa-solid fa-up-right-from-square text-gray-400 w-4 text-center"></i> Ver prova
                                    </a>
                                    <div class="border-t border-gray-100 my-1"></div>
                                    <form method="post" action="<?= URL ?>/admin/ava/avaliacoes/<?= (int) $av['id'] ?>/excluir" onsubmit="return confirm('Remover esta avaliação da disciplina?');">
                                        <input type="hidden" name="_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="disciplina_id" value="<?= $dId ?>">
                                        <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Remover
                                        </button>
                                    </form>
                                    <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                                    <?php $row_actions_dropdown_id = 'row-actions-aval-' . (int) $av['id']; ?>
                                    <?php include __DIR__ . '/../_partials/row_actions_dropdown.php'; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
