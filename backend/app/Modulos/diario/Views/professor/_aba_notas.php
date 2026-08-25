<div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200">
        <h2 class="text-base font-semibold text-gray-900">Avaliações e Notas</h2>
        <p class="text-sm text-gray-500 mt-0.5">Eventos de nota deste período. O lançamento é feito na tela do evento — aqui é só consulta.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <?php foreach (['Evento', 'Data', 'Situação', 'Aula do diário', 'Ação'] as $header): ?>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= htmlspecialchars($header) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($eventos)): ?>
                    <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">Nenhum evento de nota neste período.</td></tr>
                <?php else: ?>
                    <?php foreach ($eventos as $ev):
                        $coordenacaoCalcula = (string) ($ev['configuracao_nota'] ?? '') === 'coordenacao_calcula';
                        $liberado = !empty($ev['liberado']);
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $ev['titulo']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <?= date('d/m/Y', strtotime((string) $ev['data_prova'])) ?>
                                · <?= htmlspecialchars(substr((string) $ev['hora_inicio'], 0, 5)) ?>–<?= htmlspecialchars(substr((string) $ev['hora_fim'], 0, 5)) ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $liberado ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' ?>">
                                    <?= $liberado ? 'Liberado' : 'Não liberado' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <?php
                                $vinculos = $ev['aulas_vinculadas'] ?? [];
                                if ($vinculos === []):
                                ?>
                                    <span class="text-gray-300">Não vinculada</span>
                                <?php else: ?>
                                    <?= htmlspecialchars(implode(', ', array_map(static fn($a) => date('d/m', strtotime((string) $a['data_aula'])), $vinculos))) ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <?php if ($coordenacaoCalcula): ?>
                                    <span class="text-gray-400">Notas calculadas pela coordenação</span>
                                <?php else: ?>
                                    <a href="<?= URL ?>/professor/provas/evento-lancar-notas/<?= (int) $ev['id'] ?>" class="text-xs font-semibold text-purple-700 hover:underline">
                                        Lançar notas
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
