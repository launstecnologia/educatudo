<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Relatório de Pagantes</h1>
            <p class="text-gray-600 mt-2">Pagantes e não pagantes (alunos e professores).</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6 border border-green-200">
        <h2 class="text-lg font-semibold text-gray-900">Alunos Pagantes</h2>
        <p class="text-sm text-gray-500"><?= count($alunos_pagantes) ?> aluno(s)</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border border-red-200">
        <h2 class="text-lg font-semibold text-gray-900">Alunos Não Pagantes</h2>
        <p class="text-sm text-gray-500"><?= count($alunos_nao_pagantes) ?> aluno(s)</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border border-green-200">
        <h2 class="text-lg font-semibold text-gray-900">Professores Pagantes</h2>
        <p class="text-sm text-gray-500"><?= count($prof_pagantes) ?> professor(es)</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border border-red-200">
        <h2 class="text-lg font-semibold text-gray-900">Professores Não Pagantes</h2>
        <p class="text-sm text-gray-500"><?= count($prof_nao_pagantes) ?> professor(es)</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Alunos Pagantes</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RA</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($alunos_pagantes)): ?>
                    <tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Nenhum aluno pagante.</td></tr>
                <?php else: ?>
                    <?php foreach ($alunos_pagantes as $aluno): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?= htmlspecialchars($aluno['nome'] ?? '') ?>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($aluno['nickname'] ?? '') ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($aluno['ra'] ?? '') ?></td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($aluno['turma_nome'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Alunos Não Pagantes</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RA</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($alunos_nao_pagantes)): ?>
                    <tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Nenhum aluno não pagante.</td></tr>
                <?php else: ?>
                    <?php foreach ($alunos_nao_pagantes as $aluno): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?= htmlspecialchars($aluno['nome'] ?? '') ?>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($aluno['nickname'] ?? '') ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($aluno['ra'] ?? '') ?></td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($aluno['turma_nome'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Professores Pagantes</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Professor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($prof_pagantes)): ?>
                    <tr><td colspan="2" class="px-6 py-4 text-center text-gray-500">Nenhum professor pagante.</td></tr>
                <?php else: ?>
                    <?php foreach ($prof_pagantes as $professor): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($professor['nome'] ?? '') ?></td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($professor['email'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Professores Não Pagantes</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Professor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($prof_nao_pagantes)): ?>
                    <tr><td colspan="2" class="px-6 py-4 text-center text-gray-500">Nenhum professor não pagante.</td></tr>
                <?php else: ?>
                    <?php foreach ($prof_nao_pagantes as $professor): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($professor['nome'] ?? '') ?></td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($professor['email'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

