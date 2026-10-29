<?php
$item = is_array($item ?? null) ? $item : null;
$ehEdicao = !empty($item['id']);
$anos = is_array($anos ?? null) ? $anos : [(int) date('Y')];
$materias = is_array($materias ?? null) ? $materias : [];
$series = is_array($series ?? null) ? $series : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$regras = is_array($regras_academicas ?? null) ? $regras_academicas : [];
$finalidade = (($item['finalidade'] ?? 'oficial') === 'complementar') ? 'complementar' : 'oficial';
$anoSel = (int) ($item['ano_letivo'] ?? ($ano ?? date('Y')));
$idsMaterias = array_fill_keys((array) ($item['materias_ids'] ?? []), true);
$idsSeries = array_fill_keys((array) ($item['series_ids'] ?? []), true);
$idsTurmas = array_fill_keys((array) ($item['turmas_ids'] ?? []), true);
$regraId = (int) ($item['regra_academica_id'] ?? 0);
$acao = $ehEdicao
    ? URL . '/admin/eventos-avaliativos/boletim/' . (int) $item['id'] . '/dados'
    : URL . '/admin/eventos-avaliativos/boletim/novo';

$page_header_title = $ehEdicao ? 'Editar boletim' : 'Novo boletim';
$page_header_subtitle = !empty($so_regra)
    ? 'O boletim só vincula uma regra de aprovação. Modelo, público, ativo e disciplinas ficam na regra. O desenho das colunas fica no layout.'
    : 'Defina se é o oficial da vida escolar ou um extra, e quais disciplinas entram. O desenho das colunas fica no layout.';
$page_header_actions = '<a href="' . URL . '/admin/eventos-avaliativos/boletim?ano=' . $anoSel . '" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Voltar</a>';
include \App\Core\ViewPaths::adminPartial('page_header_list.php');
include \App\Core\ViewPaths::adminPartial('flash_message.php');
?>

<form method="POST" action="<?= $acao ?>" class="w-full">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <div class="bg-white rounded-xl shadow-lg p-6 w-full">
        <?php if (!empty($so_regra)): ?>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2" for="boletim-regra">Regra de aprovação <span class="text-red-500">*</span></label>
            <?php if ($regras === []): ?>
                <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Cadastre uma regra de aprovação antes de criar o boletim.</p>
            <?php else: ?>
                <select name="regra_academica_id" id="boletim-regra" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <option value="">Selecione</option>
                    <?php foreach ($regras as $regra): ?>
                        <?php
                            $idRegra = (int) ($regra['id'] ?? 0);
                            if ($idRegra <= 0) {
                                continue;
                            }
                            $rotulo = (string) ($regra['nome'] ?? '');
                            if (!empty($regra['ano_letivo'])) {
                                $rotulo .= ' · ' . (int) $regra['ano_letivo'];
                            }
                        ?>
                        <option value="<?= $idRegra ?>" <?= $regraId === $idRegra ? 'selected' : '' ?>><?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-2">Modelo oficial ou extra, aluno, pais, ativo e disciplinas saem da regra escolhida. <a href="<?= URL ?>/admin/regras-academicas" class="text-indigo-600 underline">Gerenciar regras</a></p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2" for="boletim-nome">Nome <span class="text-red-500">*</span></label>
                <input type="text" name="nome" id="boletim-nome" required maxlength="180"
                       value="<?= htmlspecialchars((string) ($item['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       placeholder="Ex.: Boletim oficial 2026">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2" for="boletim-ano-form">Ano letivo</label>
                <select name="ano_letivo" id="boletim-ano-form" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <?php foreach ($anos as $anoOpt): ?>
                        <option value="<?= (int) $anoOpt ?>" <?= $anoSel === (int) $anoOpt ? 'selected' : '' ?>><?= (int) $anoOpt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mb-6">
            <span class="block text-sm font-medium text-gray-700 mb-2">Finalidade</span>
            <div class="space-y-2">
                <label class="flex items-start gap-2 text-sm text-gray-800">
                    <input type="radio" name="finalidade" value="oficial" class="mt-0.5" <?= $finalidade === 'oficial' ? 'checked' : '' ?>>
                    <span><strong>Modelo oficial</strong> — matriz regular. Vai para o histórico e a vida escolar.</span>
                </label>
                <label class="flex items-start gap-2 text-sm text-gray-800">
                    <input type="radio" name="finalidade" value="complementar" class="mt-0.5" <?= $finalidade === 'complementar' ? 'checked' : '' ?>>
                    <span><strong>Modelo extra</strong> — curso complementar (música, robótica…). Não entra no histórico oficial.</span>
                </label>
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2" for="boletim-regra">Regra de aprovação</label>
            <select name="regra_academica_id" id="boletim-regra" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <option value="">Nenhuma — na geração, a mínima segue a regra da série ou do curso (6,0 se não houver regra)</option>
                <?php foreach ($regras as $regra): ?>
                    <?php
                        $idRegra = (int) ($regra['id'] ?? 0);
                        $rotulo = (string) ($regra['nome'] ?? '');
                        if (!empty($regra['ano_letivo'])) {
                            $rotulo .= ' · ' . (int) $regra['ano_letivo'];
                        }
                        if (!empty($regra['curso_nome'])) {
                            $rotulo .= ' · ' . $regra['curso_nome'];
                        }
                        if (!empty($regra['serie_nome'])) {
                            $rotulo .= ' · ' . $regra['serie_nome'];
                        }
                        $minima = isset($regra['media_minima']) ? number_format((float) $regra['media_minima'], 2, ',', '') : '6,00';
                    ?>
                    <option value="<?= $idRegra ?>" <?= $regraId === $idRegra ? 'selected' : '' ?>><?= htmlspecialchars($rotulo . ' (mín. ' . $minima . ')', ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-500 mt-1">A média mínima, o arredondamento e a frequência saem desta regra. <a href="<?= URL ?>/admin/regras-academicas" class="text-indigo-600 underline">Gerenciar regras</a></p>
        </div>

        <div class="flex items-center gap-4 flex-wrap mb-6">
            <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                <input type="checkbox" name="vis_aluno" value="1" class="rounded border-gray-300 text-teal-600" <?= !$ehEdicao || (int) ($item['vis_aluno'] ?? 0) === 1 ? 'checked' : '' ?>>
                Aluno vê
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                <input type="checkbox" name="vis_pais" value="1" class="rounded border-gray-300 text-teal-600" <?= !$ehEdicao || (int) ($item['vis_pais'] ?? 0) === 1 ? 'checked' : '' ?>>
                Pais veem
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                <input type="checkbox" name="ativo" value="1" class="rounded border-gray-300 text-teal-600" <?= !$ehEdicao || (int) ($item['ativo'] ?? 0) === 1 ? 'checked' : '' ?>>
                Ativo
            </label>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Disciplinas deste boletim <span class="text-red-500">*</span></label>
            <p class="text-xs text-gray-500 mb-2">Áreas oficiais (o pai). Os desdobramentos vêm do cadastro de Componentes Curriculares.</p>
            <div class="border border-gray-200 rounded-lg p-4 max-h-72 overflow-y-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                    <?php foreach ($materias as $materia): ?>
                        <?php $mid = (int) ($materia['id'] ?? 0); if ($mid <= 0) { continue; } ?>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                            <input type="checkbox" name="materias_ids[]" value="<?= $mid ?>" class="rounded border-gray-300 text-teal-600" <?= isset($idsMaterias[$mid]) ? 'checked' : '' ?>>
                            <span>
                                <?= htmlspecialchars((string) ($materia['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($materia['eh_rotulo'])): ?>
                                    <span class="text-xs text-gray-500">(área)</span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Séries (opcional)</label>
                <div class="border border-gray-200 rounded-lg p-4 max-h-56 overflow-y-auto">
                    <?php foreach ($series as $serie): ?>
                        <?php $sid = (int) ($serie['id'] ?? 0); if ($sid <= 0) { continue; } ?>
                        <label class="flex items-center gap-2 text-sm text-gray-800 mb-1">
                            <input type="checkbox" name="series_ids[]" value="<?= $sid ?>" class="rounded border-gray-300 text-teal-600" <?= isset($idsSeries[$sid]) ? 'checked' : '' ?>>
                            <?= htmlspecialchars(trim((string) ($serie['curso_nome'] ?? '') . ' ' . (string) ($serie['nome'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Turmas (opcional)</label>
                <div class="border border-gray-200 rounded-lg p-4 max-h-56 overflow-y-auto">
                    <?php foreach ($turmas as $turma): ?>
                        <?php $tid = (int) ($turma['id'] ?? 0); if ($tid <= 0) { continue; } ?>
                        <label class="flex items-center gap-2 text-sm text-gray-800 mb-1">
                            <input type="checkbox" name="turmas_ids[]" value="<?= $tid ?>" class="rounded border-gray-300 text-teal-600" <?= isset($idsTurmas[$tid]) ? 'checked' : '' ?>>
                            <?= htmlspecialchars(trim((string) ($turma['nome'] ?? '') . (!empty($turma['ano_letivo']) ? ' · ' . (int) $turma['ano_letivo'] : '')), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="flex justify-end gap-3">
            <a href="<?= URL ?>/admin/eventos-avaliativos/boletim?ano=<?= $anoSel ?>" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 bg-white">Cancelar</a>
            <button type="submit" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 shadow-sm"><?= $ehEdicao ? 'Salvar' : 'Cadastrar' ?></button>
        </div>
    </div>
</form>
