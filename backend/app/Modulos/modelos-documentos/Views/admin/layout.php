<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$layout = is_array($layout ?? null) ? $layout : [];
$unidades = is_array($unidades ?? null) ? $unidades : [];
$cargos = is_array($cargos ?? null) ? $cargos : [];
$cargoAtual = (string) ($layout['cargo_assinante'] ?? 'direcao');

$page_header_back_url = URL . '/admin/modelos-documentos';
$page_header_title = 'Papel timbrado';
$page_header_subtitle = 'Identidade visual compartilhada: logo, cabeçalho, rodapé e quem assina. Declarações e documentos oficiais podem herdar este layout.';
include __DIR__ . '/../../../../Views/admin/_partials/page_header_form.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<?php if (empty($layout_pronto)): ?>
<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 mb-6">
    Execute a migration <code>2026_08_22_secretaria_layout_documentos.sql</code> no painel Master para criar a tabela do papel timbrado.
</div>
<?php endif; ?>

<form method="post" action="<?= URL ?>/admin/modelos-documentos/layout" enctype="multipart/form-data"
      class="bg-white rounded-xl shadow-lg w-full divide-y divide-gray-200">
    <input type="hidden" name="csrf_token" value="<?= $esc($csrf_token ?? '') ?>">
    <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">

    <div class="p-6 space-y-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Dados institucionais</h3>
            <p class="text-sm text-gray-600 mt-1">Usados nos placeholders {{razao_social}} e {{cnpj_layout}}. Se vazios, a emissão usa os dados da unidade do aluno.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="razao_social" class="block text-sm font-medium text-gray-700 mb-2">Razão social</label>
                <input type="text" id="razao_social" name="razao_social"
                       value="<?= $esc($layout['razao_social'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>
            <div>
                <label for="cnpj" class="block text-sm font-medium text-gray-700 mb-2">CNPJ</label>
                <input type="text" id="cnpj" name="cnpj"
                       value="<?= $esc($layout['cnpj'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>
        </div>
    </div>

    <div class="p-6 space-y-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Faixas de imagem</h3>
            <p class="text-sm text-gray-600 mt-1">PNG ou JPG · máx. 5MB. A faixa de cabeçalho ocupa a largura do A4 (ideal para um banner timbrado).</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="imagem_cabecalho" class="block text-sm font-medium text-gray-700 mb-2">Imagem de cabeçalho / logo em faixa</label>
                <?php if (!empty($preview_cabecalho)): ?>
                <div class="mb-2 rounded-lg border border-gray-200 bg-gray-50 p-2">
                    <img src="<?= $esc($preview_cabecalho) ?>" alt="Cabeçalho" class="max-h-28 mx-auto object-contain">
                </div>
                <label class="flex items-center mb-2">
                    <input type="checkbox" name="remover_imagem_cabecalho" value="1"
                           class="rounded border-gray-300 text-red-600 shadow-sm">
                    <span class="ml-2 text-xs text-red-600">Remover imagem atual</span>
                </label>
                <?php endif; ?>
                <input type="file" id="imagem_cabecalho" name="imagem_cabecalho" accept="image/png,image/jpeg"
                       class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-100 file:text-gray-700 file:font-medium hover:file:bg-gray-200">
            </div>
            <div>
                <label for="imagem_rodape" class="block text-sm font-medium text-gray-700 mb-2">Imagem de rodapé</label>
                <?php if (!empty($preview_rodape)): ?>
                <div class="mb-2 rounded-lg border border-gray-200 bg-gray-50 p-2">
                    <img src="<?= $esc($preview_rodape) ?>" alt="Rodapé" class="max-h-20 mx-auto object-contain">
                </div>
                <label class="flex items-center mb-2">
                    <input type="checkbox" name="remover_imagem_rodape" value="1"
                           class="rounded border-gray-300 text-red-600 shadow-sm">
                    <span class="ml-2 text-xs text-red-600">Remover imagem atual</span>
                </label>
                <?php endif; ?>
                <input type="file" id="imagem_rodape" name="imagem_rodape" accept="image/png,image/jpeg"
                       class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-100 file:text-gray-700 file:font-medium hover:file:bg-gray-200">
            </div>
        </div>
    </div>

    <div class="p-6 space-y-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Quem assina</h3>
            <p class="text-sm text-gray-600 mt-1">Preenche {{assinante_nome}} e {{assinante_cargo}}. Se o nome estiver vazio, usa o diretor/coordenador/secretário da unidade escolhida.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="unidade_assinatura_id" class="block text-sm font-medium text-gray-700 mb-2">Unidade da assinatura</label>
                <select id="unidade_assinatura_id" name="unidade_assinatura_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="0">Usar unidade do aluno na emissão</option>
                    <?php foreach ($unidades as $u): ?>
                    <option value="<?= (int) ($u['id'] ?? 0) ?>"
                        <?= (int) ($layout['unidade_assinatura_id'] ?? 0) === (int) ($u['id'] ?? 0) ? 'selected' : '' ?>>
                        <?= $esc($u['nome'] ?? ('Unidade #' . (int) ($u['id'] ?? 0))) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="cargo_assinante" class="block text-sm font-medium text-gray-700 mb-2">Cargo</label>
                <select id="cargo_assinante" name="cargo_assinante"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                    <?php foreach ($cargos as $cod => $lab): ?>
                    <option value="<?= $esc($cod) ?>" <?= $cargoAtual === $cod ? 'selected' : '' ?>><?= $esc($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="assinante_nome" class="block text-sm font-medium text-gray-700 mb-2">Nome de quem assina (opcional)</label>
                <input type="text" id="assinante_nome" name="assinante_nome"
                       value="<?= $esc($layout['assinante_nome'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       placeholder="Deixe em branco para usar o cadastro da unidade">
            </div>
        </div>
    </div>

    <div class="p-6 space-y-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">HTML do cabeçalho e rodapé</h3>
            <p class="text-sm text-gray-600 mt-1">
                Substituem o cabeçalho (e o rodapé, se preenchido) dos modelos que marcarem “Usar papel timbrado”.
                Placeholders: <code>{{escola_nome}}</code>, <code>{{logo_html}}</code>, <code>{{escola_endereco}}</code>.
            </p>
        </div>
        <div>
            <label for="cabecalho_html" class="block text-sm font-medium text-gray-700 mb-2">Cabeçalho</label>
            <textarea id="cabecalho_html" name="cabecalho_html" rows="7"
                      class="js-doc-editor w-full px-3 py-2 border border-gray-300 rounded-lg"><?= $esc($layout['cabecalho_html'] ?? '') ?></textarea>
        </div>
        <div>
            <label for="rodape_html" class="block text-sm font-medium text-gray-700 mb-2">Rodapé compartilhado (opcional)</label>
            <textarea id="rodape_html" name="rodape_html" rows="5"
                      class="js-doc-editor w-full px-3 py-2 border border-gray-300 rounded-lg"><?= $esc($layout['rodape_html'] ?? '') ?></textarea>
            <p class="mt-1 text-xs text-gray-500">Se ficar vazio, cada modelo mantém as próprias assinaturas.</p>
        </div>
    </div>

    <?php
    $form_cancel_url = URL . '/admin/modelos-documentos';
    $form_submit_label = 'Salvar papel timbrado';
    include __DIR__ . '/../../../../Views/admin/_partials/form_actions.php';
    ?>
</form>

<script src="https://cdn.ckeditor.com/ckeditor5/41.2.1/classic/ckeditor.js"></script>
<script>
(function () {
    var toolbar = ['heading', '|', 'bold', 'italic', '|', 'link', 'bulletedList', 'numberedList', '|', 'undo', 'redo'];
    document.querySelectorAll('textarea.js-doc-editor').forEach(function (ta) {
        if (typeof ClassicEditor === 'undefined') return;
        ClassicEditor.create(ta, { toolbar: toolbar, language: 'pt-br' }).catch(function (err) { console.error(err); });
    });
})();
</script>
