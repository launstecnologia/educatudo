<?php
require_once __DIR__ . '/../../../Models/Education/ResultadoAcademico.php';
$tipos = is_array($tipos ?? null) ? $tipos : ResultadoAcademico::DOCUMENTO_TIPOS;
$escolhidos = is_array($escolhidos ?? null) ? $escolhidos : [];
$modelos = is_array($modelos ?? null) ? $modelos : [];
$config = is_array($config ?? null) ? $config : [];
$csrf_token = $csrf_token ?? '';

$page_header_title = 'Layouts dos documentos oficiais';
$page_header_subtitle = 'Cada escola escolhe o modelo HTML usado na emissão. Monte o layout em Layout de documentos (aba Documentos oficiais, código resultado_*).';
ob_start();
?>
<a href="<?= URL ?>/admin/resultados-finais" class="text-gray-600 hover:text-gray-900 text-sm">← Voltar</a>
<a href="<?= URL ?>/admin/modelos-documentos?categoria=oficial" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
    Montar modelos
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
include __DIR__ . '/../_partials/flash_message.php';
?>

<form method="POST" action="<?= URL ?>/admin/resultados-finais/layouts" class="space-y-6">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

    <div class="bg-white rounded-xl shadow-lg p-6 w-full">
        <h3 class="text-lg font-semibold text-gray-900 mb-1">Pendências críticas do fechamento</h3>
        <p class="text-sm text-gray-500 mb-4">Homologação é bloqueada enquanto houver pendência marcada como obrigatória.</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php
            $checks = [
                'exigir_notas' => ['Notas completas', !empty($config['exigir_notas'])],
                'exigir_frequencia' => ['Frequência lançada', !empty($config['exigir_frequencia'])],
                'exigir_conselho' => ['Conselho finalizado', !empty($config['exigir_conselho'])],
            ];
            foreach ($checks as $name => [$lab, $on]):
            ?>
            <label class="flex items-center gap-2 text-sm text-gray-700 border border-gray-200 rounded-lg px-4 py-3">
                <input type="checkbox" name="<?= htmlspecialchars($name) ?>" value="1" class="rounded border-gray-300"
                       <?= $on ? 'checked' : '' ?>>
                <?= htmlspecialchars($lab) ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 w-full">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Modelo por documento</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($tipos as $tipo => $label):
                $atual = $escolhidos[$tipo] ?? (ResultadoAcademico::LAYOUT_PADRAO[$tipo] ?? '');
            ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2"><?= htmlspecialchars($label) ?></label>
                <select name="layouts[<?= htmlspecialchars($tipo) ?>]" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                    <?php foreach ($modelos as $m): ?>
                        <option value="<?= htmlspecialchars((string) $m['codigo']) ?>" <?= $atual === ($m['codigo'] ?? '') ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($m['nome'] ?? $m['codigo'])) ?>
                            (<?= htmlspecialchars((string) $m['codigo']) ?>)
                        </option>
                    <?php endforeach; ?>
                    <?php if ($atual !== '' && !in_array($atual, array_column($modelos, 'codigo'), true)): ?>
                        <option value="<?= htmlspecialchars($atual) ?>" selected><?= htmlspecialchars($atual) ?> (atual)</option>
                    <?php endif; ?>
                </select>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    $form_cancel_url = URL . '/admin/resultados-finais';
    $form_submit_label = 'Salvar layouts';
    include __DIR__ . '/../_partials/form_actions.php';
    ?>
</form>
