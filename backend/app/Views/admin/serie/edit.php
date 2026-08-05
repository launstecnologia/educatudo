<?php
$item = $item ?? null;
$cursos = $cursos ?? [];
$csrf_token = $csrf_token ?? '';
if (!$item) {
    header('Location: ' . (defined('URL') ? URL : '') . '/admin/serie');
    exit;
}

$page_header_back_url = URL . '/admin/serie';
$page_header_title = 'Editar Série';
$page_header_subtitle_html = 'Altere curso, nome ou ordem da série <strong>' . htmlspecialchars($item['nome']) . '</strong>.';
include __DIR__ . '/../_partials/page_header_form.php';
?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 w-full">
    <form method="POST" action="<?= URL ?>/admin/serie/<?= (int)$item['id'] ?>/update" class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="p-6 space-y-6">
            <h3 class="text-lg font-semibold text-gray-900">Dados da série</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="curso_id" class="block text-sm font-medium text-gray-700 mb-2">Curso <span class="text-red-500">*</span></label>
                    <select id="curso_id" name="curso_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <?php foreach ($cursos as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$item['curso_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="nome" class="block text-sm font-medium text-gray-700 mb-2">Nome da série <span class="text-red-500">*</span></label>
                    <input type="text" id="nome" name="nome" required value="<?= htmlspecialchars($item['nome']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="ordem" class="block text-sm font-medium text-gray-700 mb-2">Ordem</label>
                    <input type="number" id="ordem" name="ordem" value="<?= (int)$item['ordem'] ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <p class="mt-1 text-xs text-gray-500">Use múltiplos de 10 (10, 20, 30…) para ordenar na listagem.</p>
                </div>
                <div class="flex items-end pb-1">
                    <label class="flex items-center">
                        <input type="checkbox" name="ativo" value="1" <?= $item['ativo'] ? 'checked' : '' ?>
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Série ativa</span>
                    </label>
                </div>
            </div>
        </div>

        <?php
        $form_cancel_url = URL . '/admin/serie';
        $form_submit_label = 'Salvar alterações';
        include __DIR__ . '/../_partials/form_actions.php';
        ?>
    </form>
</div>
