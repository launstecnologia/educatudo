<?php
$curso = $curso ?? null;
$categorias = $categorias ?? [];
$modalidades = $modalidades ?? [];
$status_opcoes = $status_opcoes ?? [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$flash_status = (string) ($flash_type ?? '');
$isEdit = $curso !== null;
$action = $isEdit ? (URL . '/admin/ava/cursos/' . (int) $curso['id']) : (URL . '/admin/ava/cursos');
$val = static fn($k, $d = '') => htmlspecialchars((string) ($curso[$k] ?? $d));
require_once __DIR__ . '/../../components/wysiwyg.php';
?>

<?php
$page_header_back_url = URL . '/admin/ava';
$page_header_title = $isEdit ? 'Editar Curso' : 'Novo Curso';
$page_header_subtitle = 'Defina os dados gerais do curso. As disciplinas são adicionadas depois, na gestão do curso.';
include __DIR__ . '/../_partials/page_header_form.php';
include __DIR__ . '/../_partials/flash_message.php';
?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 w-full">
    <form method="POST" action="<?= $action ?>" class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= $csrf ?>">

        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Identificação</h3>
                <p class="mt-1 text-sm text-gray-500">Dados básicos do curso.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="nome" class="block text-sm font-medium text-gray-700 mb-2">Nome do curso <span class="text-red-500">*</span></label>
                    <input type="text" id="nome" name="nome" required value="<?= $val('nome') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="codigo" class="block text-sm font-medium text-gray-700 mb-2">Código</label>
                    <input type="text" id="codigo" name="codigo" value="<?= $val('codigo') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="modalidade" class="block text-sm font-medium text-gray-700 mb-2">Modalidade</label>
                    <select id="modalidade" name="modalidade" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <?php foreach ($modalidades as $k => $v): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= ($curso['modalidade'] ?? 'livre') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="categoria_id" class="block text-sm font-medium text-gray-700 mb-2">Categoria</label>
                    <select id="categoria_id" name="categoria_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">Sem categoria</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= (int) ($curso['categoria_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $cat['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="carga_horaria" class="block text-sm font-medium text-gray-700 mb-2">Carga horária (h)</label>
                    <input type="number" min="0" id="carga_horaria" name="carga_horaria" value="<?= $val('carga_horaria', '0') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <?php foreach ($status_opcoes as $k => $v): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= ($curso['status'] ?? 'rascunho') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <label class="flex items-start gap-3 md:col-span-2">
                    <input type="checkbox" name="certificacao" value="1" <?= !empty($curso['certificacao']) ? 'checked' : '' ?>
                           class="mt-0.5 rounded border-gray-300 text-green-600 shadow-sm focus:ring focus:ring-green-200 focus:ring-opacity-50">
                    <span>
                        <span class="block text-sm font-medium text-gray-700">Emite certificado</span>
                        <span class="block text-xs text-gray-500 mt-0.5">Alunos concluintes poderão emitir certificado (fase posterior).</span>
                    </span>
                </label>
            </div>
        </section>

        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Conteúdo programático</h3>
                <p class="mt-1 text-sm text-gray-500">Descrição, objetivos e competências do curso.</p>
            </div>
            <div class="grid grid-cols-1 gap-6">
                <div><?php wysiwyg_field(['name' => 'descricao', 'label' => 'Descrição', 'value' => $curso['descricao'] ?? '', 'rows' => 3]); ?></div>
                <div><?php wysiwyg_field(['name' => 'objetivos', 'label' => 'Objetivos', 'value' => $curso['objetivos'] ?? '', 'rows' => 3]); ?></div>
                <div><?php wysiwyg_field(['name' => 'competencias', 'label' => 'Competências', 'value' => $curso['competencias'] ?? '', 'rows' => 3]); ?></div>
                <div><?php wysiwyg_field(['name' => 'bibliografia', 'label' => 'Bibliografia', 'value' => $curso['bibliografia'] ?? '', 'rows' => 3]); ?></div>
            </div>
        </section>

<?php
        $form_cancel_url = URL . '/admin/ava';
        $form_submit_label = $isEdit ? 'Salvar Alterações' : 'Criar Curso';
        include __DIR__ . '/../_partials/form_actions.php';
        ?>
    </form>
</div>
