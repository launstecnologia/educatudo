<?php
$filtros = is_array($filtros ?? null) ? $filtros : [];
$catalogo = is_array($catalogo ?? null) ? $catalogo : [];
$anos = is_array($anos ?? null) ? $anos : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$marcados = is_array($bancos_marcados ?? null) ? $bancos_marcados : [];
$grupos = [];
foreach ($catalogo as $chave => $banco) {
    $grupo = (string) ($banco['grupo'] ?? 'Outros');
    $grupos[$grupo][$chave] = $banco;
}
$valor = static function (string $campo) use ($filtros): string {
    return htmlspecialchars((string) ($filtros[$campo] ?? ''), ENT_QUOTES, 'UTF-8');
};
?>
<div class="mb-8">
    <div class="flex justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Relatório completo</h2>
            <p class="text-gray-600">Marque os bancos que entram no arquivo e baixe em Excel ou PDF.</p>
        </div>
        <a href="<?= URL ?>/admin/relatorios" class="text-gray-600 hover:text-gray-900 shrink-0">← Voltar</a>
    </div>
</div>

<?php include \App\Core\ViewPaths::adminPartial('flash_message.php'); ?>

<?php if (is_array($versao_recente ?? null) && !empty($versao_recente['id'])): ?>
<div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
    A versão de <?= htmlspecialchars((string) ($versao_recente['quando'] ?? ''), ENT_QUOTES, 'UTF-8') ?> continua em Documentos.
    <a href="<?= URL ?>/admin/relatorios/completo/<?= (int) $versao_recente['id'] ?>" class="font-semibold underline">Baixar de novo</a>
    ou abra
    <a href="<?= URL ?>/admin/paineis/documentos?ano=<?= (int) (($filtros['ano'] ?? 0) > 0 ? $filtros['ano'] : date('Y')) ?>" class="font-semibold underline">Secretaria → Documentos</a>.
</div>
<?php endif; ?>

<form method="POST" action="<?= URL ?>/admin/relatorios/completo" class="space-y-6">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="formulario" value="1">

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
            <div class="flex items-center gap-3 min-w-0">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">1</span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Quem entra</p>
                    <p class="text-sm text-gray-500 mt-0.5">Sem turma e sem nome, o arquivo usa os alunos ativos do recorte. Acima de 1.500 alunos, escolha uma turma.</p>
                </div>
            </div>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="ano" class="block text-sm font-medium text-gray-700 mb-2">Ano letivo</label>
                    <select id="ano" name="ano" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white">
                        <option value="0">Todos</option>
                        <?php foreach ($anos as $ano): ?>
                        <option value="<?= (int) $ano ?>" <?= (int) ($filtros['ano'] ?? 0) === (int) $ano ? 'selected' : '' ?>><?= (int) $ano ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-2">Turma</label>
                    <select id="turma_id" name="turma_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white">
                        <option value="0">Todas</option>
                        <?php foreach ($turmas as $turma): ?>
                        <option value="<?= (int) $turma['id'] ?>" <?= (int) ($filtros['turma_id'] ?? 0) === (int) $turma['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $turma['nome'], ENT_QUOTES, 'UTF-8') ?><?= (int) $turma['ano'] > 0 ? ' · ' . (int) $turma['ano'] : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="busca" class="block text-sm font-medium text-gray-700 mb-2">Aluno</label>
                    <input id="busca" name="busca" type="text" value="<?= $valor('busca') ?>" placeholder="Nome, RA ou código" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="data_inicio" class="block text-sm font-medium text-gray-700 mb-2">De</label>
                        <input id="data_inicio" name="data_inicio" type="date" value="<?= $valor('data_inicio') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white">
                    </div>
                    <div>
                        <label for="data_fim" class="block text-sm font-medium text-gray-700 mb-2">Até</label>
                        <input id="data_fim" name="data_fim" type="date" value="<?= $valor('data_fim') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white">
                    </div>
                </div>
            </div>
            <label class="mt-6 flex items-start gap-3 text-sm text-gray-700">
                <input type="checkbox" name="incluir_inativos" value="1" class="mt-0.5 rounded border-gray-300" <?= !empty($filtros['incluir_inativos']) ? 'checked' : '' ?>>
                <span>Incluir alunos inativos. As datas valem para faltas, ocorrências, redações, provas e exercícios. Em branco, o ano letivo limita esses bancos.</span>
            </label>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
            <div class="flex items-center gap-3 min-w-0">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">2</span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Bancos do arquivo</p>
                    <p class="text-sm text-gray-500 mt-0.5">Cada banco marcado vira uma aba no Excel e uma seção no PDF. Senha não entra.</p>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="button" id="marcar-bancos" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-white">Marcar todos</button>
                <button type="button" id="limpar-bancos" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-white">Limpar</button>
            </div>
        </div>
        <div class="p-5 space-y-6">
            <?php foreach ($grupos as $grupo => $bancos): ?>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3"><?= htmlspecialchars((string) $grupo, ENT_QUOTES, 'UTF-8') ?></p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php foreach ($bancos as $chave => $banco): ?>
                    <label class="flex items-start gap-3 rounded-lg border border-gray-200 px-4 py-3 hover:border-gray-300">
                        <input type="checkbox" name="bancos[]" value="<?= htmlspecialchars((string) $chave, ENT_QUOTES, 'UTF-8') ?>" class="banco-check mt-0.5 rounded border-gray-300" <?= in_array((string) $chave, $marcados, true) ? 'checked' : '' ?>>
                        <span>
                            <span class="block text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($banco['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="block text-sm text-gray-500 mt-0.5"><?= htmlspecialchars((string) ($banco['texto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">O arquivo entra em Secretaria → Documentos, com a data e a hora. O mesmo recorte, os mesmos bancos e o mesmo formato não podem ser gerados de novo dentro de 1 hora.</p>
        <div class="flex flex-wrap justify-end gap-3">
            <button type="submit" name="formato" value="pdf" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-800 hover:bg-gray-50">Gerar PDF</button>
            <button type="submit" name="formato" value="excel" class="btn-primary-custom px-6 py-2 rounded-lg">Gerar Excel</button>
        </div>
    </div>
</form>

<script>
(function () {
    var checks = function () { return document.querySelectorAll('.banco-check'); };
    var marcar = document.getElementById('marcar-bancos');
    var limpar = document.getElementById('limpar-bancos');
    if (marcar) {
        marcar.addEventListener('click', function () {
            checks().forEach(function (el) { el.checked = true; });
        });
    }
    if (limpar) {
        limpar.addEventListener('click', function () {
            checks().forEach(function (el) { el.checked = false; });
        });
    }
})();
</script>
