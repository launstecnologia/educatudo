<?php
$edicao = $edicao ?? null;
$itens = $itens ?? [];
$setores = $setores ?? [];
$stands = $stands ?? [];
$csrf_token = $csrf_token ?? '';
?>
<div class="max-w-5xl mx-auto space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="<?= URL ?>/admin/expo-colag" class="text-sm text-primary hover:underline">← Expo Colag</a>
            <h2 class="text-2xl font-bold text-gray-900 mt-1">Programação e stands</h2>
            <p class="text-sm text-gray-600">Itens da agenda pública e QR dos stands gerados pelos professores.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-3">
            <h3 class="font-semibold text-gray-900">Novo item</h3>
            <form method="post" action="<?= URL ?>/admin/expo-colag/programacao" class="space-y-3 text-sm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="acao" value="item">
                <input type="text" name="titulo" required placeholder="Título" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                <div class="grid grid-cols-2 gap-2">
                    <input type="datetime-local" name="hora_inicio" required class="border border-gray-300 rounded-lg px-3 py-2 bg-white">
                    <input type="datetime-local" name="hora_fim" class="border border-gray-300 rounded-lg px-3 py-2 bg-white">
                </div>
                <input type="text" name="local" placeholder="Local" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                <input type="text" name="tipo" value="Geral" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                <select name="setor_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                    <option value="">Sem setor</option>
                    <?php foreach ($setores as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['nome'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <textarea name="descricao" rows="2" placeholder="Descrição" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white"></textarea>
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium">Adicionar</button>
            </form>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-3">
            <h3 class="font-semibold text-gray-900">Novo setor</h3>
            <form method="post" action="<?= URL ?>/admin/expo-colag/programacao" class="flex gap-2 text-sm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="acao" value="setor">
                <input type="text" name="nome" required placeholder="Nome do setor" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 bg-white">
                <button type="submit" class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50">Criar</button>
            </form>
            <?php if ($setores): ?>
                <ul class="text-sm text-gray-700 list-disc pl-5">
                    <?php foreach ($setores as $s): ?>
                        <li><?= htmlspecialchars($s['nome'] ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 font-semibold text-gray-900">Agenda</div>
        <?php if (empty($itens)): ?>
            <p class="px-5 py-6 text-sm text-gray-500">Nenhum item.</p>
        <?php else: ?>
            <ul class="divide-y divide-gray-100 text-sm">
                <?php foreach ($itens as $item): ?>
                    <li class="px-5 py-3 flex flex-wrap justify-between gap-2">
                        <div>
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($item['titulo'] ?? '') ?></p>
                            <p class="text-xs text-gray-500">
                                <?= !empty($item['hora_inicio']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($item['hora_inicio']))) : '' ?>
                                <?php if (!empty($item['local'])): ?> · <?= htmlspecialchars($item['local']) ?><?php endif; ?>
                            </p>
                        </div>
                        <form method="post" action="<?= URL ?>/admin/expo-colag/programacao" onsubmit="return confirm('Excluir?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                            <button type="submit" class="text-xs text-red-600 hover:underline">Excluir</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 font-semibold text-gray-900">Stands com QR</div>
        <?php if (empty($stands)): ?>
            <p class="px-5 py-6 text-sm text-gray-500">Nenhum stand gerado. Os professores criam na aba Stand do projeto.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">Projeto</th>
                            <th class="px-4 py-3">Nº</th>
                            <th class="px-4 py-3">Setor</th>
                            <th class="px-4 py-3">QR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($stands as $s): ?>
                            <tr>
                                <td class="px-4 py-3 font-medium"><?= htmlspecialchars($s['projeto_titulo'] ?? '') ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($s['numero'] ?? '—') ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($s['setor_nome'] ?? '—') ?></td>
                                <td class="px-4 py-3">
                                    <a href="<?= URL ?>/expo-colag/s/<?= htmlspecialchars($s['qr_token'] ?? '') ?>" target="_blank" rel="noopener" class="text-primary hover:underline text-xs">
                                        Abrir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
