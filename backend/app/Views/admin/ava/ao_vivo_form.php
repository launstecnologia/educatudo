<?php
$disciplina = $disciplina ?? [];
$aula = $aula ?? null;
$modulos = $modulos ?? [];
$plataformas = $plataformas ?? [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$flash_status = (string) ($flash_type ?? '');
$base = rtrim((string) ($base_url ?? '/professor/ava'), '/');
$isEdit = $aula !== null;
$disciplinaId = (int) ($disciplina['id'] ?? ($aula['disciplina_id'] ?? 0));
$action = $isEdit ? (URL . $base . '/ao-vivo/' . (int) $aula['id']) : (URL . $base . '/disciplinas/' . $disciplinaId . '/ao-vivo');
$val = static fn($k, $d = '') => htmlspecialchars((string) ($aula[$k] ?? $d));
$dt = static fn($k) => ($aula && !empty($aula[$k])) ? date('Y-m-d\TH:i', strtotime((string) $aula[$k])) : '';
$platSel = (string) ($aula['plataforma'] ?? 'jitsi');
require_once __DIR__ . '/../../components/wysiwyg.php';
?>

<?php
$page_header_back_url = URL . $base . '/disciplinas/' . $disciplinaId . '/ao-vivo';
$page_header_title = $isEdit ? 'Editar Aula ao vivo' : 'Nova Aula ao vivo';
$page_header_subtitle = (string) ($disciplina['nome'] ?? '');
include __DIR__ . '/../_partials/page_header_form.php';
include __DIR__ . '/../_partials/flash_message.php';
?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 w-full">
    <form method="POST" action="<?= $action ?>" class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= $csrf ?>">

        <section class="p-6 space-y-6">
            <div><h3 class="text-lg font-semibold text-gray-900">Dados da aula</h3></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="titulo" class="block text-sm font-medium text-gray-700 mb-2">Título <span class="text-red-500">*</span></label>
                    <input type="text" id="titulo" name="titulo" required value="<?= $val('titulo') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="modulo_id" class="block text-sm font-medium text-gray-700 mb-2">Módulo (opcional)</label>
                    <select id="modulo_id" name="modulo_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">— Sem módulo —</option>
                        <?php foreach ($modulos as $m): ?><option value="<?= (int) $m['id'] ?>" <?= (int) ($aula['modulo_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $m['titulo']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="plataforma" class="block text-sm font-medium text-gray-700 mb-2">Plataforma</label>
                    <select id="plataforma" name="plataforma" onchange="document.getElementById('linkExternoWrap').style.display = this.value === 'externo' ? '' : 'none';" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <?php foreach ($plataformas as $k => $v): ?><option value="<?= htmlspecialchars($k) ?>" <?= $platSel === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2" id="linkExternoWrap" style="<?= $platSel === 'externo' ? '' : 'display:none;' ?>">
                    <label for="link_externo" class="block text-sm font-medium text-gray-700 mb-2">Link externo (Meet/Zoom/YouTube)</label>
                    <input type="url" id="link_externo" name="link_externo" value="<?= $val('link_externo') ?>" placeholder="https://..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div class="md:col-span-2"><?php wysiwyg_field(['name' => 'descricao', 'label' => 'Descrição', 'value' => $aula['descricao'] ?? '', 'rows' => 3]); ?></div>
                <div>
                    <label for="inicio_em" class="block text-sm font-medium text-gray-700 mb-2">Início</label>
                    <input type="datetime-local" id="inicio_em" name="inicio_em" value="<?= $dt('inicio_em') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="fim_em" class="block text-sm font-medium text-gray-700 mb-2">Término previsto</label>
                    <input type="datetime-local" id="fim_em" name="fim_em" value="<?= $dt('fim_em') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
            </div>
            <p class="text-xs text-gray-500"><i class="fa-solid fa-circle-info mr-1"></i> Jitsi gera a sala automaticamente. Panda Video cria a transmissão (se a integração estiver configurada). Para Meet/Zoom/YouTube, escolha "Link externo".</p>
        </section>

<?php
        $form_cancel_url = URL . $base . '/disciplinas/' . $disciplinaId . '/ao-vivo';
        $form_submit_label = $isEdit ? 'Salvar' : 'Criar aula ao vivo';
        include __DIR__ . '/../_partials/form_actions.php';
        ?>
    </form>
</div>
