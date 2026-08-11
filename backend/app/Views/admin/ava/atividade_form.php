<?php
$disciplina = $disciplina ?? [];
$atividade = $atividade ?? null;
$modulos = $modulos ?? [];
$rubricas = $rubricas ?? [];
$tipos = $tipos ?? [];
$status_opcoes = $status_opcoes ?? [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$flash_status = (string) ($flash_type ?? '');
$base = rtrim((string) ($base_url ?? '/professor/ava'), '/');
$isEdit = $atividade !== null;
$disciplinaId = (int) ($disciplina['id'] ?? ($atividade['disciplina_id'] ?? 0));
$action = $isEdit ? (URL . $base . '/atividades/' . (int) $atividade['id']) : (URL . $base . '/disciplinas/' . $disciplinaId . '/atividades');
$val = static fn($k, $d = '') => htmlspecialchars((string) ($atividade[$k] ?? $d));
$dt = static fn($k) => ($atividade && !empty($atividade[$k])) ? date('Y-m-d\TH:i', strtotime((string) $atividade[$k])) : '';
$tipoSel = (string) ($atividade['tipo_entrega'] ?? 'arquivo');
$statusSel = (string) ($atividade['status'] ?? 'publicada');
require_once __DIR__ . '/../../components/wysiwyg.php';
?>

<?php
$page_header_back_url = URL . $base . '/disciplinas/' . $disciplinaId . '/atividades';
$page_header_title = $isEdit ? 'Editar Atividade' : 'Nova Atividade';
$page_header_subtitle = (string) ($disciplina['nome'] ?? '');
include __DIR__ . '/../_partials/page_header_form.php';
include __DIR__ . '/../_partials/flash_message.php';
?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 w-full">
    <form method="POST" action="<?= $action ?>" class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= $csrf ?>">

        <section class="p-6 space-y-6">
            <div><h3 class="text-lg font-semibold text-gray-900">Dados da atividade</h3></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="titulo" class="block text-sm font-medium text-gray-700 mb-2">Título <span class="text-red-500">*</span></label>
                    <input type="text" id="titulo" name="titulo" required value="<?= $val('titulo') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="modulo_id" class="block text-sm font-medium text-gray-700 mb-2">Módulo (opcional)</label>
                    <select id="modulo_id" name="modulo_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">— Sem módulo —</option>
                        <?php foreach ($modulos as $m): ?><option value="<?= (int) $m['id'] ?>" <?= (int) ($atividade['modulo_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $m['titulo']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <?php foreach ($status_opcoes as $k => $v): ?><option value="<?= htmlspecialchars($k) ?>" <?= $statusSel === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="descricao" class="block text-sm font-medium text-gray-700 mb-2">Descrição curta</label>
                    <input type="text" id="descricao" name="descricao" value="<?= $val('descricao') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div class="md:col-span-2"><?php wysiwyg_field(['name' => 'instrucoes', 'label' => 'Instruções detalhadas', 'value' => $atividade['instrucoes'] ?? '', 'rows' => 5, 'placeholder' => 'Descreva o que o aluno deve fazer...']); ?></div>
            </div>
        </section>

        <section class="p-6 space-y-6">
            <div><h3 class="text-lg font-semibold text-gray-900">Formato de entrega e prazos</h3></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="tipo_entrega" class="block text-sm font-medium text-gray-700 mb-2">Tipo de entrega</label>
                    <select id="tipo_entrega" name="tipo_entrega" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <?php foreach ($tipos as $k => $v): ?><option value="<?= htmlspecialchars($k) ?>" <?= $tipoSel === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="rubrica_id" class="block text-sm font-medium text-gray-700 mb-2">Rubrica de correção (opcional)</label>
                    <select id="rubrica_id" name="rubrica_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">— Sem rubrica —</option>
                        <?php foreach ($rubricas as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) ($atividade['rubrica_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $r['titulo']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="data_abertura" class="block text-sm font-medium text-gray-700 mb-2">Abertura (opcional)</label>
                    <input type="datetime-local" id="data_abertura" name="data_abertura" value="<?= $dt('data_abertura') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="data_entrega" class="block text-sm font-medium text-gray-700 mb-2">Prazo de entrega (opcional)</label>
                    <input type="datetime-local" id="data_entrega" name="data_entrega" value="<?= $dt('data_entrega') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="nota_maxima" class="block text-sm font-medium text-gray-700 mb-2">Nota máxima</label>
                    <input type="number" step="0.01" min="0" id="nota_maxima" name="nota_maxima" value="<?= $val('nota_maxima', '10') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="peso" class="block text-sm font-medium text-gray-700 mb-2">Peso</label>
                    <input type="number" step="0.01" min="0" id="peso" name="peso" value="<?= $val('peso', '1') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="max_arquivos" class="block text-sm font-medium text-gray-700 mb-2">Máx. de arquivos</label>
                    <input type="number" min="1" id="max_arquivos" name="max_arquivos" value="<?= $val('max_arquivos', '5') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="tamanho_max_mb" class="block text-sm font-medium text-gray-700 mb-2">Tamanho máx. por arquivo (MB)</label>
                    <input type="number" min="1" id="tamanho_max_mb" name="tamanho_max_mb" value="<?= $val('tamanho_max_mb', '20') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="aceita_atraso" value="1" <?= !empty($atividade['aceita_atraso']) ? 'checked' : '' ?> class="mt-0.5 rounded border-gray-300 text-green-600 focus:ring focus:ring-green-200">
                    <span class="text-sm font-medium text-gray-700">Aceitar entregas após o prazo (marca como atrasada)</span>
                </label>
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="permite_reenvio" value="1" <?= ($atividade['permite_reenvio'] ?? 1) ? 'checked' : '' ?> class="mt-0.5 rounded border-gray-300 text-green-600 focus:ring focus:ring-green-200">
                    <span class="text-sm font-medium text-gray-700">Permitir reenvio após avaliação</span>
                </label>
            </div>
        </section>

<?php
        $form_cancel_url = URL . $base . '/disciplinas/' . $disciplinaId . '/atividades';
        $form_submit_label = $isEdit ? 'Salvar Alterações' : 'Criar Atividade';
        include __DIR__ . '/../_partials/form_actions.php';
        ?>
    </form>
</div>
