<?php
$title = $title ?? 'Minicursos - Admin';
$lista = $lista ?? [];
$csrf_token = $csrf_token ?? '';
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Minicursos</h1>
            <p class="text-gray-600 mt-1">Cadastre minicursos com módulos e aulas (Vídeo, Slides, PDF, Link). Certificado ao concluir.</p>
        </div>
        <a href="<?= URL ?>/admin/minicursos/criar" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90">+ Novo Minicurso</a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="mb-4 p-4 rounded-lg <?= ($_SESSION['flash_type'] ?? '') === 'error' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
        <?= htmlspecialchars($_SESSION['flash_message']) ?>
    </div>
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
<?php endif; ?>

<div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($lista)): ?>
                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">Nenhum minicurso. <a href="<?= URL ?>/admin/minicursos/criar" class="text-indigo-600 hover:underline">Criar o primeiro</a>.</td></tr>
            <?php else: ?>
                <?php foreach ($lista as $row): ?>
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($row['titulo']) ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded-full <?= $row['ativo'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                                <?= $row['ativo'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="<?= URL ?>/admin/minicursos/<?= (int)$row['id'] ?>" class="text-indigo-600 hover:underline text-sm">Módulos e Aulas</a>
                            <a href="<?= URL ?>/admin/minicursos/editar/<?= (int)$row['id'] ?>" class="text-gray-600 hover:underline text-sm ml-2">Editar</a>
                            <form method="post" action="<?= URL ?>/admin/minicursos/excluir/<?= (int)$row['id'] ?>" class="inline ml-2" onsubmit="return confirm('Excluir este minicurso e todos os módulos/aulas?');">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <button type="submit" class="text-red-600 hover:underline text-sm">Excluir</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
