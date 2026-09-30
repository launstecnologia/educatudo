<div class="mb-8 flex flex-wrap justify-between items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Relatório de notas lançadas</h2>
        <p class="text-gray-600 mt-1"><?= htmlspecialchars($bloco['titulo'] ?? '') ?></p>
    </div>
    <div class="flex gap-2">
        <a href="<?= URL ?>/admin/provas/blocos/<?= (int)($bloco['id'] ?? 0) ?>/gerenciar"
           class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">← Painel do evento</a>
        <a href="<?= URL ?>/admin/provas" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">Lista de eventos</a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
    <div class="px-4 py-3 border-b border-gray-200 bg-purple-50">
        <p class="text-sm text-purple-900">Todas as notas informadas pelos professores neste evento (nota cheia 0 a 10 por aluno).</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Matéria</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Professor</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nota</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Atualizado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($notas_linhas)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">Ainda não há notas lançadas neste evento.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($notas_linhas as $ln): ?>
                    <?php
                    $transferido = !empty($ln['transferido']);
                    $nomeAluno = (string) ($ln['aluno_nome'] ?? '');
                    $classeLinha = $transferido
                        ? 'bg-gray-100 text-gray-500'
                        : (($ln['nota'] !== null && $ln['nota'] !== '' && (float)$ln['nota'] < 6) ? 'bg-red-50' : '');
                    ?>
                    <tr class="<?= $classeLinha ?>">
                        <td class="px-3 py-2"><?= htmlspecialchars($ln['materia_nome'] ?? '') ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($ln['professor_nome'] ?? '') ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($ln['turma_nome'] ?? '') ?></td>
                        <td class="px-3 py-2 font-medium">
                            <?php if ($transferido): ?>
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold tracking-wide bg-gray-300 text-gray-700">TR</span>
                                    <span><?= htmlspecialchars($nomeAluno) ?></span>
                                </span>
                            <?php else: ?>
                                <?= htmlspecialchars($nomeAluno) ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2">
                            <?php if ($ln['nota'] === null || $ln['nota'] === ''): ?>
                                —
                            <?php else: ?>
                                <?= htmlspecialchars(number_format((float)$ln['nota'], 2, ',', '.')) ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-gray-500">
                            <?= !empty($ln['updated_at']) ? date('d/m/Y H:i', strtotime($ln['updated_at'])) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
