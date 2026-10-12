<?php
$tentativas = is_array($tentativas ?? null) ? $tentativas : [];
$data_inicio = $data_inicio ?? '';
$data_fim = $data_fim ?? '';
$tipo_filtro = $tipo_filtro ?? 'aluno';
$baseUrl = (defined('URL') ? URL : '') . '/admin/tentativas-login';
?>
<div class="mb-6">
    <h2 class="text-xl font-semibold text-gray-900">Tentativas de login (senha ou nickname inválido)</h2>
    <p class="text-sm text-gray-500">Visualize as tentativas falhas de login por período. Na 5ª tentativa errada o nickname é bloqueado por alguns minutos (configure LOCKOUT_DURATION no .env para 300 = 5 min).</p>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
    <form method="get" action="<?= htmlspecialchars($baseUrl) ?>" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Data início</label>
            <input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Data fim</label>
            <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
            <select name="tipo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Todos</option>
                <option value="aluno" <?= $tipo_filtro === 'aluno' ? 'selected' : '' ?>>Aluno</option>
                <option value="admin_escola" <?= $tipo_filtro === 'admin_escola' ? 'selected' : '' ?>>Admin</option>
                <option value="professor" <?= $tipo_filtro === 'professor' ? 'selected' : '' ?>>Professor</option>
                <option value="pai" <?= $tipo_filtro === 'pai' ? 'selected' : '' ?>>Pai</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Filtrar</button>
            <a href="<?= htmlspecialchars($baseUrl) ?>" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Limpar</a>
        </div>
    </form>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="p-4 border-b border-gray-200">
        <h3 class="text-sm font-semibold text-gray-700">Tentativas falhas no período</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="px-4 py-2 text-left">Data / Hora</th>
                    <th class="px-4 py-2 text-left">Nome do aluno</th>
                    <th class="px-4 py-2 text-left">Nickname / Login</th>
                    <th class="px-4 py-2 text-left">Turma</th>
                    <th class="px-4 py-2 text-left">Motivo</th>
                    <th class="px-4 py-2 text-left">Tipo</th>
                    <th class="px-4 py-2 text-left">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($tentativas)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">Nenhuma tentativa falha no período.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tentativas as $t): ?>
                        <tr>
                            <td class="px-4 py-2 text-gray-700"><?= date('d/m/Y H:i', strtotime($t['created_at'] ?? 'now')) ?></td>
                            <td class="px-4 py-2 text-gray-900"><?= htmlspecialchars($t['aluno_nome'] ?? $t['login_nickname'] ?? '-') ?></td>
                            <td class="px-4 py-2 text-gray-700"><?= htmlspecialchars($t['login_nickname'] ?? $t['aluno_nickname'] ?? '-') ?></td>
                            <td class="px-4 py-2 text-gray-700"><?= htmlspecialchars($t['turma_nome'] ?? '-') ?></td>
                            <td class="px-4 py-2">
                                <?php
                                $motivo = $t['motivo_falha'] ?? null;
                                if ($motivo === 'senha_invalida') {
                                    echo '<span class="text-amber-700">Senha inválida</span>';
                                } elseif ($motivo === 'nickname_invalido') {
                                    echo '<span class="text-red-700">Nickname/login inválido</span>';
                                } else {
                                    echo '<span class="text-gray-500">-</span>';
                                }
                                ?>
                            </td>
                            <td class="px-4 py-2 text-gray-700"><?= htmlspecialchars($t['tipo'] ?? '-') ?></td>
                            <td class="px-4 py-2 text-gray-500 font-mono text-xs"><?= htmlspecialchars($t['ip_address'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
