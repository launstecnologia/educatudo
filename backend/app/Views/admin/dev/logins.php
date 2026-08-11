<?php
/**
 * Dev Settings - Histórico de logins
 * Alunos (alunos_sessoes_acesso), Admin, Professor, Pai (logs_auditoria LOGIN). Filtros e paginação.
 */
$logins = $logins ?? [];
$page = (int)($page ?? 1);
$per_page = (int)($per_page ?? 50);
$total = (int)($total ?? 0);
$total_pages = (int)($total_pages ?? 1);
$filtros = $filtros ?? ['nome' => '', 'tipo' => '', 'data_inicio' => '', 'data_fim' => ''];
$filtro_aplicado = ($filtros['nome'] !== '' || $filtros['tipo'] !== '' || $filtros['data_inicio'] !== '' || $filtros['data_fim'] !== '');

function logins_query_string($filtros, $page = null) {
    $q = [];
    if (isset($filtros['nome']) && $filtros['nome'] !== '') $q['nome'] = $filtros['nome'];
    if (isset($filtros['tipo']) && $filtros['tipo'] !== '') $q['tipo'] = $filtros['tipo'];
    if (isset($filtros['data_inicio']) && $filtros['data_inicio'] !== '') $q['data_inicio'] = $filtros['data_inicio'];
    if (isset($filtros['data_fim']) && $filtros['data_fim'] !== '') $q['data_fim'] = $filtros['data_fim'];
    if ($page !== null && $page > 1) $q['page'] = $page;
    return $q ? '?' . http_build_query($q) : '';
}
?>
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Histórico de logins</h2>
            <p class="text-sm text-gray-600 mt-1">Data e horário em que cada aluno, professor, admin e pai fizeram login. Para alunos, a turma é exibida quando houver.</p>
            <a href="<?= URL ?>/admin/dev" class="inline-block mt-4 text-indigo-600 hover:text-indigo-800 text-sm font-medium">← Voltar para Dev Settings</a>
        </div>

        <!-- Filtros -->
        <div class="p-6 border-b border-gray-100 bg-gray-50">
            <style>
                /* Select alinhado aos inputs no iOS/Safari (remove aparência nativa e seta própria) */
                .logins-filtro-select {
                    -webkit-appearance: none;
                    appearance: none;
                    background-color: #fff;
                    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
                    background-repeat: no-repeat;
                    background-position: right 0.5rem center;
                    background-size: 1.25rem;
                    padding-right: 2rem;
                    min-height: 2.5rem;
                }
            </style>
            <form method="get" action="<?= URL ?>/admin/dev-settings/logins" class="flex flex-wrap items-end gap-4">
                <input type="hidden" name="page" value="1">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($filtros['nome']) ?>"
                           placeholder="Buscar por nome" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48 min-h-[2.5rem]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                    <select name="tipo" class="logins-filtro-select border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[8rem]">
                        <option value="">Todos</option>
                        <option value="Aluno" <?= $filtros['tipo'] === 'Aluno' ? 'selected' : '' ?>>Aluno</option>
                        <option value="Professor" <?= $filtros['tipo'] === 'Professor' ? 'selected' : '' ?>>Professor</option>
                        <option value="Admin" <?= $filtros['tipo'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="Pai" <?= $filtros['tipo'] === 'Pai' ? 'selected' : '' ?>>Pai</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data início</label>
                    <input type="date" name="data_inicio" value="<?= htmlspecialchars($filtros['data_inicio']) ?>"
                           class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-h-[2.5rem]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data fim</label>
                    <input type="date" name="data_fim" value="<?= htmlspecialchars($filtros['data_fim']) ?>"
                           class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-h-[2.5rem]">
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">
                    Filtrar
                </button>
                <a href="<?= URL ?>/admin/dev-settings/logins" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium">
                    Limpar
                </a>
            </form>
            <?php if ($filtro_aplicado): ?>
                <p class="mt-4 text-sm font-medium text-indigo-700">
                    <?= $total ?> registro(s) encontrado(s) com os filtros aplicados
                </p>
            <?php endif; ?>
        </div>

        <div class="p-6 overflow-x-auto">
            <?php if (empty($logins)): ?>
                <p class="text-gray-500">Nenhum login encontrado com os filtros informados. Logins de alunos vêm de <code>alunos_sessoes_acesso</code>; admin, professor e pai de <code>logs_auditoria</code> (action=LOGIN).</p>
                <?php if ($filtro_aplicado): ?>
                    <p class="mt-2 text-sm text-gray-600">Total: <strong>0</strong> registro(s) com os filtros aplicados.</p>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-sm text-gray-500 mb-4">
                    <?= $total ?> registro(s) <?= $filtro_aplicado ? 'encontrado(s) com os filtros' : 'no total' ?> — página <?= $page ?> de <?= $total_pages ?>
                </p>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data e horário</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($logins as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 font-mono">
                                <?= date('d/m/Y H:i:s', strtotime($row['data_hora'])) ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?= htmlspecialchars($row['nome']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                <?= htmlspecialchars($row['tipo']) ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                <?= $row['turma_nome'] !== null && $row['turma_nome'] !== '' ? htmlspecialchars($row['turma_nome']) : '—' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Paginação -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-6 flex items-center justify-between border-t border-gray-200 pt-4">
                    <div class="text-sm text-gray-500">
                        <?= (($page - 1) * $per_page) + 1 ?>–<?= min($page * $per_page, $total) ?> de <?= $total ?>
                    </div>
                    <div class="flex gap-1">
                        <?php if ($page > 1): ?>
                            <a href="<?= URL ?>/admin/dev-settings/logins<?= logins_query_string($filtros, $page - 1) ?>" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Anterior</a>
                        <?php endif; ?>
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <a href="<?= URL ?>/admin/dev-settings/logins<?= logins_query_string($filtros, $i === 1 ? null : $i) ?>" class="px-3 py-1.5 border rounded-lg text-sm <?= $i === $page ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 text-gray-700 hover:bg-gray-50' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="<?= URL ?>/admin/dev-settings/logins<?= logins_query_string($filtros, $page + 1) ?>" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Próxima</a>
                        <?php endif; ?>
                    </div>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
