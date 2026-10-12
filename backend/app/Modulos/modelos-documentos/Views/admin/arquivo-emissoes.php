<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$filtro = is_array($filtro ?? null) ? $filtro : [];
$opcoes = is_array($opcoes ?? null) ? $opcoes : [];
$catalogo = is_array($catalogo ?? null) ? $catalogo : [];
$alunos = is_array($alunos ?? null) ? $alunos : [];
$emissoes = is_array($emissoes ?? null) ? $emissoes : [];
$categorias = is_array($categorias ?? null) ? $categorias : [];
$ano = (int) ($filtro['ano'] ?? date('Y'));
$serieId = (int) ($filtro['serie_id'] ?? 0);
$turmaId = (int) ($filtro['turma_id'] ?? 0);
$alunoId = (int) ($filtro['aluno_id'] ?? 0);
$modeloId = (int) ($filtro['modelo_id'] ?? 0);
$qs = http_build_query([
    'ano' => $ano,
    'serie_id' => $serieId,
    'turma_id' => $turmaId,
    'aluno_id' => $alunoId,
]);

$page_header_title = 'Documentos';
$page_header_subtitle = 'Gere declarações e autorizações por ano, série, turma ou aluno. Cada PDF fica guardado como uma versão.';
ob_start(); ?>
<a href="<?= URL ?>/admin/paineis" class="text-gray-600 hover:text-gray-900 text-sm">← Painéis</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<?php if (empty($tabela_pronta)): ?>
<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 mb-6">
    Execute a migration <code>2026_10_02_documentos_emissoes.sql</code> no painel Master para guardar as versões.
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <form method="get" action="<?= URL ?>/admin/paineis/documentos" class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div>
            <label for="ano" class="block text-sm font-medium text-gray-700 mb-2">Ano letivo</label>
            <select id="ano" name="ano" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white" onchange="this.form.submit()">
                <?php foreach (($opcoes['anos'] ?? []) as $itemAno): ?>
                <option value="<?= (int) $itemAno ?>" <?= $ano === (int) $itemAno ? 'selected' : '' ?>><?= (int) $itemAno ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="serie_id" class="block text-sm font-medium text-gray-700 mb-2">Série</label>
            <select id="serie_id" name="serie_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white" onchange="this.form.submit()">
                <option value="0">Todas</option>
                <?php foreach (($opcoes['series'] ?? []) as $serie): ?>
                <option value="<?= (int) $serie['id'] ?>" <?= $serieId === (int) $serie['id'] ? 'selected' : '' ?>><?= $esc($serie['nome'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-2">Turma</label>
            <select id="turma_id" name="turma_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white" onchange="this.form.submit()">
                <option value="0">Todas</option>
                <?php foreach (($opcoes['turmas'] ?? []) as $turma):
                    if ($serieId > 0 && (int) ($turma['serie_id'] ?? 0) > 0 && (int) $turma['serie_id'] !== $serieId) {
                        continue;
                    }
                ?>
                <option value="<?= (int) $turma['id'] ?>" <?= $turmaId === (int) $turma['id'] ? 'selected' : '' ?>><?= $esc($turma['nome'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="aluno_id" class="block text-sm font-medium text-gray-700 mb-2">Aluno</label>
            <select id="aluno_id" name="aluno_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white" onchange="this.form.submit()" <?= $turmaId <= 0 ? 'disabled' : '' ?>>
                <option value="0"><?= $turmaId > 0 ? 'Todos da turma' : 'Escolha a turma' ?></option>
                <?php foreach ($alunos as $aluno): ?>
                <option value="<?= (int) $aluno['id'] ?>" <?= $alunoId === (int) $aluno['id'] ? 'selected' : '' ?>><?= $esc($aluno['nome'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <form method="post" action="<?= URL ?>/admin/paineis/documentos/gerar" id="form-gerar-documento">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrf_token ?? '') ?>">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
        <input type="hidden" name="ano" value="<?= $ano ?>">
        <input type="hidden" name="serie_id" value="<?= $serieId ?>">
        <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
        <input type="hidden" name="aluno_id" value="<?= $alunoId ?>">

        <div class="mb-6">
            <label for="modelo_id" class="block text-sm font-medium text-gray-700 mb-2">Documento</label>
            <select id="modelo_id" name="modelo_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                <option value="">Selecione</option>
                <?php
                $gruposCat = [];
                foreach ($catalogo as $item) {
                    $cat = (string) ($item['categoria'] ?? 'outro');
                    $gruposCat[$cat][] = $item;
                }
                foreach ($categorias as $chaveCat => $rotuloCat):
                    if (empty($gruposCat[$chaveCat])) {
                        continue;
                    }
                ?>
                <optgroup label="<?= $esc($rotuloCat) ?>">
                    <?php foreach ($gruposCat[$chaveCat] as $item): ?>
                    <option value="<?= (int) $item['id'] ?>" <?= $modeloId === (int) $item['id'] ? 'selected' : '' ?>><?= $esc($item['nome'] ?? '') ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endforeach; ?>
            </select>
        </div>

        <?php foreach ($catalogo as $item):
            $mid = (int) ($item['id'] ?? 0);
            $camposItem = is_array($item['campos'] ?? null) ? $item['campos'] : [];
        ?>
        <fieldset class="js-campos-modelo mb-6 <?= $modeloId === $mid ? '' : 'hidden' ?>" data-modelo="<?= $mid ?>" <?= $modeloId === $mid ? '' : 'disabled' ?>>
            <?php if ($camposItem === []): ?>
            <p class="text-sm text-gray-500">Este documento usa só os dados do cadastro. Não há campo para preencher.</p>
            <?php else: ?>
            <p class="text-sm text-gray-600 mb-4">Estes valores entram iguais em todos os alunos desta emissão.</p>
            <?php
            $campos_variaveis = $camposItem;
            $campos_variaveis_prefix = 'arq-' . $mid;
            include __DIR__ . '/../../../../Views/admin/_partials/campos_variaveis_documento.php';
            ?>
            <?php endif; ?>
        </fieldset>
        <?php endforeach; ?>

        <div class="flex justify-end">
            <button type="submit" id="btn-gerar-documento" class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold" <?= empty($tabela_pronta) ? 'disabled' : '' ?>>
                <i class="fa-solid fa-box-archive mr-2"></i> <span class="js-rotulo-gerar">Gerar e guardar</span>
            </button>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-base font-semibold text-gray-900">Versões guardadas</h3>
        <p class="text-sm text-gray-500">Baixar abre o PDF já gerado. Uma nova geração não apaga as anteriores.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <?php foreach (['Documento', 'Versão', 'Escopo', 'Alunos', 'Quando', ''] as $h): ?>
                    <th class="px-6 py-3 <?= $h === '' ? 'text-right' : 'text-left' ?> text-xs font-medium text-gray-500 uppercase tracking-wider"><?= $esc($h) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($emissoes === []): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">Nenhuma versão neste filtro.</td>
                </tr>
                <?php else: foreach ($emissoes as $row):
                    $quando = (string) ($row['created_at'] ?? '');
                    $dt = $quando !== '' ? date('d/m/Y H:i', strtotime($quando)) : '';
                    $escopo = array_filter([
                        (string) ($row['serie_nome'] ?? ''),
                        (string) ($row['turma_nome'] ?? ''),
                        (string) ($row['aluno_nome'] ?? ''),
                    ]);
                    if ($escopo === []) {
                        $escopo = ['Ano ' . (int) ($row['ano_letivo'] ?? 0)];
                    }
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-900"><?= $esc($row['nome'] ?? '') ?></td>
                    <td class="px-6 py-3 text-gray-700">v<?= (int) ($row['versao'] ?? 1) ?></td>
                    <td class="px-6 py-3 text-gray-600"><?= $esc(implode(' · ', $escopo)) ?></td>
                    <td class="px-6 py-3 text-gray-600"><?= (int) ($row['total_alunos'] ?? 0) ?></td>
                    <td class="px-6 py-3 text-gray-600">
                        <?= $esc($dt) ?>
                        <?php if (!empty($row['emitido_nome'])): ?>
                        <div class="text-xs text-gray-400"><?= $esc($row['emitido_nome']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-right">
                        <a href="<?= URL ?>/admin/paineis/documentos/<?= (int) ($row['id'] ?? 0) ?>/pdf" target="_blank" rel="noopener"
                           class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-gray-900">
                            <i class="fa-solid fa-file-pdf mr-1.5"></i> Baixar
                        </a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="<?= URL ?>/static/js/campos-variaveis-documento.js?v=1"></script>
<script>
(function () {
    var sel = document.getElementById('modelo_id');
    if (!sel) return;
    function mostrar() {
        var id = sel.value;
        document.querySelectorAll('.js-campos-modelo').forEach(function (box) {
            var ativo = box.getAttribute('data-modelo') === id;
            box.classList.toggle('hidden', !ativo);
            box.disabled = !ativo;
        });
    }
    sel.addEventListener('change', mostrar);
    mostrar();

    var form = document.getElementById('form-gerar-documento');
    if (form) {
        form.addEventListener('submit', function () {
            document.querySelectorAll('.js-campos-modelo').forEach(function (box) {
                if (box.classList.contains('hidden')) {
                    box.disabled = true;
                }
            });
            var botao = document.getElementById('btn-gerar-documento');
            var rotulo = form.querySelector('.js-rotulo-gerar');
            setTimeout(function () {
                if (rotulo) rotulo.textContent = 'Gerando o PDF…';
                if (botao) botao.disabled = true;
            }, 0);
        });
    }
})();
</script>
