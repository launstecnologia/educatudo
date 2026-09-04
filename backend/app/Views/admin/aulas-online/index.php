<?php include __DIR__ . '/../_partials/flash_message.php'; ?>

<!-- Cabeçalho DS -->
<div class="mb-6">
    <div class="flex justify-between items-center gap-4 flex-wrap">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Aulas Online</h2>
            <p class="text-gray-600 text-sm">Gerencie as aulas ao vivo da escola.</p>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
            <form method="post" action="<?= URL ?>/admin/aulas-online/sincronizar-gravacoes">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    <i class="fa-solid fa-cloud-arrow-down mr-2 text-gray-500"></i>
                    Atualizar gravações
                </button>
            </form>
            <a href="<?= URL ?>/admin/aulas-online/criar"
               class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                <i class="fa-solid fa-plus mr-2"></i>
                Nova aula online
            </a>
        </div>
    </div>
</div>

<!-- Listagem -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <?php if (empty($aulas)): ?>
        <div class="px-6 py-16 text-center text-gray-500">
            <i class="fa-solid fa-video text-4xl text-gray-300 mb-4 block"></i>
            <p class="text-sm font-medium text-gray-600 mb-3">Nenhuma aula online cadastrada.</p>
            <a href="<?= URL ?>/admin/aulas-online/criar"
               class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors">
                <i class="fa-solid fa-plus mr-2"></i>
                Criar primeira aula
            </a>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Início</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turmas</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Integração</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gravação</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($aulas as $item): ?>
                        <?php
                        $itemPlatformLower = mb_strtolower((string) ($item['plataforma'] ?? ''), 'UTF-8');
                        $isJitsiItem = $itemPlatformLower === 'jitsi meet';
                        $itemLink = trim((string) ($item['link_aula'] ?? ''));
                        $itemLinkGravacao = trim((string) ($item['link_gravacao'] ?? ''));
                        $statusIntegracao = (string) ($item['panda_integracao_status'] ?? 'nao_solicitada');
                        $temGravacao = $itemLinkGravacao !== ''
                                    || trim((string) ($item['jaas_recording_path'] ?? '')) !== ''
                                    || trim((string) ($item['panda_recording_player'] ?? '')) !== '';
                        $fimTs = !empty($item['fim_em']) ? strtotime((string) $item['fim_em']) : false;
                        $encerrada = $fimTs !== false && time() > $fimTs;
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($item['titulo'] ?? '')) ?></div>
                                <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars((string) ($item['plataforma'] ?? '—')) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                <?= !empty($item['inicio_em']) ? date('d/m/Y H:i', strtotime((string) $item['inicio_em'])) : '—' ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <?php if (!empty($item['enviar_para_todos'])): ?>
                                    <span class="text-xs text-gray-500">Todas</span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-700"><?= !empty($item['turmas']) ? htmlspecialchars(implode(', ', array_map(static fn($t) => (string)($t['nome'] ?? ''), (array)$item['turmas']))) : '—' ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if (!empty($item['publicado'])): ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Publicado</span>
                                <?php else: ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Rascunho</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <?php if ($isJitsiItem): ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Jitsi</span>
                                    <?php if ($itemLink !== ''): ?>
                                        <div class="mt-1"><a href="<?= URL ?>/admin/aulas-online/chat?id=<?= (int)($item['id'] ?? 0) ?>" class="text-xs text-blue-600 hover:underline">Abrir sala</a></div>
                                    <?php endif; ?>
                                <?php elseif ($statusIntegracao === 'sucesso'): ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Panda OK</span>
                                    <?php if (!empty($item['panda_live_player'])): ?>
                                        <div class="mt-1"><a class="text-xs text-blue-600 hover:underline" href="<?= htmlspecialchars((string)$item['panda_live_player']) ?>" target="_blank" rel="noopener noreferrer">Player</a></div>
                                    <?php endif; ?>
                                <?php elseif ($statusIntegracao === 'erro'): ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Erro Panda</span>
                                <?php elseif ($statusIntegracao === 'pendente'): ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Pendente</span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($temGravacao): ?>
                                    <?php if ($itemLinkGravacao !== ''): ?>
                                        <a href="<?= htmlspecialchars($itemLinkGravacao) ?>" target="_blank" rel="noopener noreferrer"
                                           class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 hover:bg-emerald-200">
                                            <i class="fa-solid fa-circle-play mr-1"></i> Assistir
                                        </a>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">
                                            <i class="fa-solid fa-circle-play mr-1"></i> Disponível
                                        </span>
                                    <?php endif; ?>
                                <?php elseif ($encerrada): ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-600">Aguardando</span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <?php ob_start(); ?>
                                <a href="<?= URL ?>/admin/aulas-online/editar?id=<?= (int)($item['id'] ?? 0) ?>"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                                </a>
                                <?php if ($isJitsiItem && $itemLink !== ''): ?>
                                <a href="<?= URL ?>/admin/aulas-online/chat?id=<?= (int)($item['id'] ?? 0) ?>"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" target="_blank" rel="noopener noreferrer">
                                    <i class="fa-solid fa-video text-gray-400 w-4 text-center"></i> Abrir sala
                                </a>
                                <?php endif; ?>
                                <a href="<?= URL ?>/admin/aulas-online/chat?id=<?= (int)($item['id'] ?? 0) ?>"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-comments text-gray-400 w-4 text-center"></i> Chat
                                </a>
                                <?php if ((int)($item['gerar_panda'] ?? 0) === 1): ?>
                                <form method="post" action="<?= URL ?>/admin/aulas-online/retry-integracao" class="w-full">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                    <input type="hidden" name="id" value="<?= (int)($item['id'] ?? 0) ?>">
                                    <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <i class="fa-solid fa-rotate text-gray-400 w-4 text-center"></i> Tentar integração
                                    </button>
                                </form>
                                <?php endif; ?>
                                <div class="border-t border-gray-100 my-1"></div>
                                <form method="post" action="<?= URL ?>/admin/aulas-online/excluir"
                                      onsubmit="return confirm('Excluir esta aula online?');" class="w-full">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                    <input type="hidden" name="id" value="<?= (int)($item['id'] ?? 0) ?>">
                                    <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                        <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                                    </button>
                                </form>
                                <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                                <?php $row_actions_dropdown_id = 'row-actions-aula-' . (int)($item['id'] ?? 0); ?>
                                <?php include __DIR__ . '/../_partials/row_actions_dropdown.php'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
