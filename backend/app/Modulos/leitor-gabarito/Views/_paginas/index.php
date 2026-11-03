<?php
$lista = $lista ?? ['itens' => [], 'pagina' => 1, 'paginas' => 1, 'total' => 0];
$busca = (string) ($busca ?? '');
$baseUrl = URL . ($base ?? '');
$qs = $busca !== '' ? '&q=' . urlencode($busca) : '';
?>
<div class="w-full space-y-6">
    <?php require __DIR__ . '/flash.php'; ?>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Leitor de gabarito</h2>
            <p class="text-gray-600">Gere a folha de respostas, imprima e envie o scan para lançar a nota na prova.</p>
        </div>
    </div>

    <?php if (empty($tabelas_prontas)): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Falta rodar a migration <span class="font-medium">2026_11_01_leitor_gabarito.sql</span> no painel Master.
    </div>
    <?php endif; ?>

    <form method="get" action="<?= htmlspecialchars($baseUrl) ?>" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[16rem]">
                <label for="q" class="block text-sm font-medium text-gray-700 mb-1">Prova ou matéria</label>
                <input id="q" name="q" value="<?= htmlspecialchars($busca) ?>" type="search"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Buscar">
            </div>
            <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium">Filtrar</button>
        </div>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prova</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matéria</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Questões</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Folhas</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pendentes</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ação</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($lista['itens'])): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <i class="fa-solid fa-barcode text-4xl text-gray-300 mb-4 block"></i>
                            <p>Nenhuma prova objetiva encontrada.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($lista['itens'] as $item): ?>
                    <?php
                        $data = (string) ($item['data_prova'] ?? '');
                        $dataTexto = $data !== '' ? date('d/m/Y', strtotime($data)) : '—';
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $item['titulo']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars((string) ($item['materia_nome'] ?? '—')) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap"><?= htmlspecialchars($dataTexto) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?= (int) $item['questoes_objetivas'] ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?= (int) ($item['folhas'] ?? 0) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?= (int) ($item['pendentes'] ?? 0) ?></td>
                        <td class="px-6 py-4 text-right">
                            <a href="<?= htmlspecialchars($baseUrl) ?>/provas/<?= (int) $item['id'] ?>"
                               class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Abrir
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ((int) ($lista['paginas'] ?? 1) > 1): ?>
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200">
            <p class="text-sm text-gray-500">Página <?= (int) $lista['pagina'] ?> de <?= (int) $lista['paginas'] ?></p>
            <div class="flex gap-2">
                <?php if ((int) $lista['pagina'] > 1): ?>
                <a class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50"
                   href="<?= htmlspecialchars($baseUrl) ?>?pagina=<?= (int) $lista['pagina'] - 1 ?><?= $qs ?>">Anterior</a>
                <?php endif; ?>
                <?php if ((int) $lista['pagina'] < (int) $lista['paginas']): ?>
                <a class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50"
                   href="<?= htmlspecialchars($baseUrl) ?>?pagina=<?= (int) $lista['pagina'] + 1 ?><?= $qs ?>">Próxima</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
