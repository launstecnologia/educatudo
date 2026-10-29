<?php

$item = is_array($item ?? null) ? $item : null;
$isEdit = $item !== null;
$historico = is_array($historico ?? null) ? $historico : [];
$cursos = is_array($cursos ?? null) ? $cursos : [];
$series = is_array($series ?? null) ? $series : [];
$anosLetivos = is_array($anos_letivos ?? null) ? $anos_letivos : [];
$cardsPorAno = is_array($cards_por_ano ?? null) ? $cards_por_ano : [];
$csrf_token = $csrf_token ?? '';
$recPodeCadastrar = !empty($recuperacao_pode_cadastrar);
$recPodeAlterar = !empty($recuperacao_pode_alterar);
$recPodeExcluir = !empty($recuperacao_pode_excluir);
$action = $isEdit
    ? URL . '/admin/regras-academicas/' . (int) $item['id'] . '/update'
    : URL . '/admin/regras-academicas';
$val = static function (?array $item, string $key, $default = '') {
    if (!$item) {
        return $default;
    }
    return $item[$key] ?? $default;
};
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= $isEdit ? 'Editar regra de aprovação' : 'Nova regra de aprovação' ?></h2>
            <p class="text-gray-600">Define como a escola aprova, recupera e exige frequência. Modelo, público e disciplinas desta regra valem para o demonstrativo e o boletim.</p>
        </div>
        <a href="<?= URL ?>/admin/regras-academicas" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<?php include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php'; ?>

<form id="regra-aprovacao-form" method="POST" action="<?= htmlspecialchars($action) ?>" class="space-y-6">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars((string) $val($item, 'periodo_tipo', 'bimestre'), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="formula_media" value="<?= htmlspecialchars((string) $val($item, 'formula_media'), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="recuperacao_tipo" value="<?= htmlspecialchars((string) $val($item, 'recuperacao_tipo', 'periodo'), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="recuperacao_composicao" value="<?= htmlspecialchars((string) $val($item, 'recuperacao_composicao', 'maior_nota'), ENT_QUOTES, 'UTF-8') ?>">

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
            <div class="flex items-center gap-3 min-w-0">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">1</span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Identificação e vigência</p>
                    <p class="text-sm text-gray-500 mt-0.5">Nome da regra e para quem ela vale.</p>
                </div>
            </div>
            <label class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 cursor-pointer hover:bg-gray-50" title="Desmarcada, a regra fica guardada e não entra na aprovação.">
                <input type="hidden" name="ativo" value="0">
                <input type="checkbox" name="ativo" value="1" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500" <?= !$isEdit || !empty($item['ativo']) ? 'checked' : '' ?>>
                <span class="text-sm font-medium text-gray-900">Regra ativa</span>
            </label>
        </div>
        <div class="p-5">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="regra-nome">Nome <span class="text-red-500">*</span></label>
            <input type="text" id="regra-nome" name="nome" required maxlength="150" value="<?= htmlspecialchars((string) $val($item, 'nome')) ?>"
                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                   placeholder="Ex.: Ensino Médio 2026">
        </div>
        <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
            <p class="text-sm font-medium text-gray-800 mb-3">Vale para</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="regra-ano-letivo">Ano letivo</label>
                    <select name="ano_letivo" id="regra-ano-letivo" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <?php
                            $anoSelecionado = (int) $val($item, 'ano_letivo', 0);
                            $anosOpcoes = array_map('intval', $anosLetivos);
                            if ($anoSelecionado > 0 && !in_array($anoSelecionado, $anosOpcoes, true)) {
                                $anosOpcoes[] = $anoSelecionado;
                                rsort($anosOpcoes);
                            }
                        ?>
                        <option value="">Qualquer ano</option>
                        <?php foreach ($anosOpcoes as $ano): ?>
                            <option value="<?= $ano ?>" <?= $anoSelecionado === $ano ? 'selected' : '' ?>><?= $ano ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="regra-curso">Ciclo/Etapa/Curso</label>
                    <select name="curso_id" id="regra-curso" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value="">Todos os cursos</option>
                        <?php foreach ($cursos as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) $val($item, 'curso_id', 0) === (int) $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $c['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="regra-serie-botao">Série / Ano Escolar</label>
                    <div class="relative" id="regra-serie-campo">
                        <button type="button" id="regra-serie-botao" aria-haspopup="listbox" aria-expanded="false"
                                class="flex w-full items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-left text-sm text-gray-900 focus:border-transparent focus:ring-2 focus:ring-purple-500">
                            <span id="regra-serie-texto" class="truncate">Todas as Séries / Anos</span>
                            <i class="fa-solid fa-chevron-down shrink-0 text-xs text-gray-400" aria-hidden="true"></i>
                        </button>
                        <div id="regra-serie-painel" class="hidden max-h-60 overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg" role="listbox" aria-multiselectable="true"></div>
                        <div id="regra-serie-valores"></div>
                    </div>
                    <p id="regra-serie-aviso" class="hidden text-xs text-red-600 mt-1">Marque ao menos uma série ou escolha Todas as Séries / Anos.</p>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-3">Em branco, o campo vale para todos.</p>
            <div id="regra-cards" class="mt-4">
                <p class="text-sm font-medium text-gray-800">Cards de avaliação</p>
                <p id="regra-cards-ajuda" class="text-xs text-gray-500 mt-1">Escolha o ano letivo para ver os cards de bimestre ou trimestre.</p>
                <div id="regra-cards-lista" class="mt-3 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3"></div>
            </div>
        </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">2</span>
            <div>
                <p class="text-sm font-semibold text-gray-900">Critérios de Aprovação</p>
                <p class="text-sm text-gray-500 mt-0.5">Configure notas, médias, frequência e regras de aprovação dos alunos.</p>
            </div>
        </div>
        <?php
            $escalaAtual = (string) $val($item, 'escala', '10');
            if (!isset(\App\Modulos\RegrasAcademicas\Models\RegraAcademica::ESCALAS[$escalaAtual])) {
                $escalaAtual = '10';
            }
            $conceitosDaEscala = \App\Modulos\RegrasAcademicas\Models\RegraAcademica::conceitosDa($escalaAtual) ?? [];
            $conceitoAtual = (string) $val($item, 'conceito_minimo', '');
            if (!isset($conceitosDaEscala[$conceitoAtual])) {
                $conceitoAtual = (string) array_key_last($conceitosDaEscala);
            }
            $tetoEscala = $escalaAtual === '100' ? '100' : '10';
            $escalaNumerica = \App\Modulos\RegrasAcademicas\Models\RegraAcademica::numerica($escalaAtual);
        ?>
        <div class="p-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1" for="regra-escala">Escala de Avaliação <span class="text-red-500">*</span></label>
                <select name="escala" id="regra-escala" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <?php foreach (\App\Modulos\RegrasAcademicas\Models\RegraAcademica::ESCALAS as $chave => $rotulo): ?>
                        <option value="<?= htmlspecialchars((string) $chave) ?>" <?= $escalaAtual === (string) $chave ? 'selected' : '' ?>><?= htmlspecialchars((string) $rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
                <p id="regra-escala-dica" class="text-xs text-gray-500 mt-1">Define como o desempenho dos alunos será registrado.</p>
            </div>
            <div id="regra-media-box" class="<?= $escalaNumerica ? '' : 'hidden' ?>">
                <label class="block text-sm font-medium text-gray-700 mb-1" for="regra-media">Média Mínima para Aprovação <span class="text-red-500">*</span></label>
                <input type="number" id="regra-media" name="media_minima" min="0" max="<?= $tetoEscala ?>" step="0.01"
                       value="<?= htmlspecialchars(number_format((float) $val($item, 'media_minima', 6), 2, '.', '')) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <div id="regra-conceito-box" class="<?= $conceitosDaEscala !== [] ? '' : 'hidden' ?>">
                <label class="block text-sm font-medium text-gray-700 mb-1" for="regra-conceito">Conceito mínimo para aprovar</label>
                <select name="conceito_minimo" id="regra-conceito" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <?php foreach ($conceitosDaEscala as $chave => $rotulo): ?>
                        <option value="<?= htmlspecialchars((string) $chave) ?>" <?= $conceitoAtual === (string) $chave ? 'selected' : '' ?>><?= htmlspecialchars((string) $rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Aprova quem ficar neste conceito ou em um melhor.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="regra-frequencia">Frequência Mínima Exigida (%) <span class="text-red-500">*</span></label>
                <input type="number" id="regra-frequencia" name="frequencia_minima" min="0" max="100" step="0.1"
                       value="<?= htmlspecialchars(number_format((float) $val($item, 'frequencia_minima', 75), 1, '.', '')) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="regra-arredondamento">Regra de Arredondamento</label>
                <select name="round_mode" id="regra-arredondamento" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="none" <?= (string) $val($item, 'round_mode', 'none') === 'none' ? 'selected' : '' ?>>Arredondamento convencional</option>
                    <option value="half" <?= (string) $val($item, 'round_mode', 'none') === 'half' ? 'selected' : '' ?>>Faixa .00 / .50 / próximo inteiro</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="regra-casas">Casas Decimais da Nota</label>
                <select name="decimal_places" id="regra-casas" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <?php $casasAtuais = (int) $val($item, 'decimal_places', 2); ?>
                    <option value="0" <?= $casasAtuais === 0 ? 'selected' : '' ?>>Nenhuma (6)</option>
                    <option value="1" <?= $casasAtuais === 1 ? 'selected' : '' ?>>1 casa (6,5)</option>
                    <option value="2" <?= $casasAtuais === 2 ? 'selected' : '' ?>>2 casas (6,50)</option>
                </select>
            </div>
        </div>
        <div class="space-y-3">
            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
                <input type="checkbox" name="usar_frequencia" value="1" class="mt-0.5 rounded border-gray-300 text-teal-600 focus:ring-teal-500" <?= !empty($item['usar_frequencia']) ? 'checked' : '' ?>>
                <span>
                    <span class="block text-sm font-medium text-gray-900">Exigir Frequência Mínima para Aprovação</span>
                    <span class="block text-xs text-gray-500 mt-0.5">O aluno deverá atingir a média e a frequência mínima exigida.</span>
                </span>
            </label>
            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
                <input type="checkbox" name="aprovacao_so_frequencia" value="1" class="mt-0.5 rounded border-gray-300 text-teal-600 focus:ring-teal-500" <?= !empty($item['aprovacao_so_frequencia']) ? 'checked' : '' ?>>
                <span>
                    <span class="block text-sm font-medium text-gray-900">Avaliação Baseada Apenas na Frequência</span>
                    <span class="block text-xs text-gray-500 mt-0.5">A aprovação considera apenas a frequência, sem exigir média.</span>
                </span>
            </label>
        </div>
        </div>
    </div>

    <?php if (!empty($boletim_pronto)): ?>
    <?php
        $finalidadeRegra = ((string) $val($item, 'finalidade', 'oficial')) === 'complementar' ? 'complementar' : 'oficial';
        $materiasRegra = is_array($materias ?? null) ? $materias : [];
        $materiasMarcadas = [];
        foreach ((array) ($item['materias_ids'] ?? []) as $midMarcada) {
            $midMarcada = (int) $midMarcada;
            if ($midMarcada > 0) {
                $materiasMarcadas[$midMarcada] = true;
            }
        }
    ?>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">3</span>
            <div>
                <p class="text-sm font-semibold text-gray-900">Demonstrativo e boletim</p>
                <p class="text-sm text-gray-500 mt-0.5">Escolha o modelo de boletim, selecione as disciplinas e disponibilize.</p>
            </div>
        </div>
        <div class="p-5 space-y-6">
            <div>
                <p class="text-sm font-semibold text-gray-900">Modelo</p>
                <p class="text-sm text-gray-500 mt-0.5 mb-3">Define se entra na vida escolar.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="finalidade" value="oficial" class="mt-1 rounded-full border-gray-300 text-teal-600 focus:ring-teal-500" <?= $finalidadeRegra === 'oficial' ? 'checked' : '' ?>>
                        <span>
                            <span class="block text-sm font-medium text-gray-900">Modelo oficial</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Matriz regular. Vai para o histórico e a vida escolar.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="finalidade" value="complementar" class="mt-1 rounded-full border-gray-300 text-teal-600 focus:ring-teal-500" <?= $finalidadeRegra === 'complementar' ? 'checked' : '' ?>>
                        <span>
                            <span class="block text-sm font-medium text-gray-900">Modelo extra</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Curso complementar, como música ou robótica. Fica fora do histórico oficial.</span>
                        </span>
                    </label>
                </div>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-900">Disponibilidade</p>
                <p class="text-sm text-gray-500 mt-0.5 mb-3">Desmarcado, a pessoa não vê este demonstrativo nem este boletim.</p>
                <div class="flex flex-wrap gap-3">
                    <label class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" name="vis_aluno" value="1" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500" <?= !$isEdit || (int) $val($item, 'vis_aluno', 1) === 1 ? 'checked' : '' ?>>
                        <span class="text-sm font-medium text-gray-900">Disponível para aluno</span>
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" name="vis_pais" value="1" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500" <?= !$isEdit || (int) $val($item, 'vis_pais', 1) === 1 ? 'checked' : '' ?>>
                        <span class="text-sm font-medium text-gray-900">Disponível para famílias</span>
                    </label>
                </div>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-900">Disciplinas <span class="text-red-500">*</span></p>
                <p class="text-sm text-gray-500 mt-0.5 mb-3">Áreas oficiais. Os desdobramentos vêm do cadastro de Componentes Curriculares.</p>
                <div class="border border-gray-200 rounded-lg p-4 max-h-72 overflow-y-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                        <?php foreach ($materiasRegra as $materia): ?>
                            <?php $mid = (int) ($materia['id'] ?? 0); if ($mid <= 0) { continue; } ?>
                            <label class="inline-flex items-center gap-2 rounded-lg px-1 py-1.5 text-sm text-gray-900 cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" name="materias_ids[]" value="<?= $mid ?>" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500" <?= isset($materiasMarcadas[$mid]) ? 'checked' : '' ?>>
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
        </div>
    </div>
    <?php endif; ?>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
            <div class="flex items-center gap-3 min-w-0">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white"><?= !empty($boletim_pronto) ? '4' : '3' ?></span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Recuperação desta regra</p>
                    <p class="text-sm text-gray-500 mt-0.5">Cada regra guarda as próprias recuperações. Os cards de cada bimestre ou trimestre já nascem com a regra.</p>
                </div>
            </div>
            <?php if ($recPodeCadastrar): ?>
            <button type="button" id="rec-nova" onclick="abrirRecuperacao()"
                    class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90">
                + Nova recuperação
            </button>
            <?php endif; ?>
        </div>
        <div class="p-5">
            <p id="rec-sem-ano" class="hidden text-sm text-gray-600">Escolha o ano letivo da regra para cadastrar as recuperações.</p>
            <p id="rec-sem-regra" class="hidden text-sm text-gray-600">Salve a regra para cadastrar as recuperações dela.</p>
            <p id="rec-schema" class="hidden text-sm text-amber-800">Rode a migration 2026_10_27_recuperacoes.sql no painel Master antes de cadastrar.</p>
            <p id="rec-isolado" class="hidden text-sm text-amber-800 mb-3">Rode a migration 2026_10_28_recuperacao_por_regra.sql no painel Master. Enquanto isso, um ano com mais de uma regra não mostra recuperação aqui.</p>
            <div id="rec-tabela" class="overflow-x-auto bg-white border border-gray-200 rounded-lg">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Nome</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Quando</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Quem faz</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Nota máxima</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Efeito</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="rec-corpo" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
        <div id="campo-formula-final" class="mt-6 <?= (string) $val($item, 'recuperacao_composicao', 'maior_nota') === 'formula' ? '' : 'hidden' ?>">
            <label class="block text-sm font-medium text-gray-700 mb-2">Fórmula da recuperação</label>
            <input type="text" name="formula_final" value="<?= htmlspecialchars((string) $val($item, 'formula_final')) ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                   placeholder="Ex.: max(media, rec) ou (media + rec) / 2">
            <p class="text-xs text-gray-500 mt-1">Use <code>media</code> e <code>rec</code>. Ex.: max(media, rec).</p>
        </div>
        </div>
    </div>

    <?php if ($isEdit && $historico !== []): ?>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="border-b border-gray-200 bg-gray-50 px-5 py-4">
            <p class="text-sm font-semibold text-gray-900">Histórico de versões</p>
        </div>
        <div class="p-5">
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Versão</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Quando</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Responsável</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($historico as $h): ?>
                    <tr>
                        <td class="px-4 py-2">v<?= (int) $h['versao'] ?></td>
                        <td class="px-4 py-2 text-gray-600"><?= !empty($h['created_at']) ? date('d/m/Y H:i', strtotime((string) $h['created_at'])) : '—' ?></td>
                        <td class="px-4 py-2 text-gray-600"><?= htmlspecialchars((string) ($h['usuario_nome'] ?? '—')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex justify-end space-x-4">
        <a href="<?= URL ?>/admin/regras-academicas" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 bg-white">Cancelar</a>
        <button type="submit" form="regra-aprovacao-form" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 shadow-sm">
            <?= $isEdit ? 'Salvar nova versão' : 'Cadastrar regra' ?>
        </button>
    </div>
</form>

<div id="recuperacao-backdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="fecharRecuperacao()"></div>
<aside id="recuperacao-drawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="recuperacao-titulo" class="text-xl font-bold text-gray-900">Nova recuperação</h2>
        <button type="button" onclick="fecharRecuperacao()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form id="recuperacao-form" method="POST" action="<?= URL ?>/admin/regras-academicas/recuperacao" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" form="recuperacao-form" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="_ajax" form="recuperacao-form" value="1">
        <input type="hidden" name="ano_letivo" id="rec-ano" form="recuperacao-form" value="">
        <input type="hidden" name="regra_id" id="rec-regra" form="recuperacao-form" value="<?= $isEdit ? (int) $item['id'] : 0 ?>">
        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Como funciona</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="rec-nome" class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
                        <input type="text" id="rec-nome" name="nome" form="recuperacao-form" maxlength="120" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="rec-quando" class="block text-sm font-medium text-gray-700 mb-1">Quando <span class="text-red-500">*</span></label>
                        <select id="rec-quando" name="quando" form="recuperacao-form" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <?php foreach (\App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig::QUANDOS as $chave => $rotulo): ?>
                                <option value="<?= htmlspecialchars((string) $chave) ?>"><?= htmlspecialchars((string) $rotulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="rec-efeito" class="block text-sm font-medium text-gray-700 mb-1">O que a nota faz <span class="text-red-500">*</span></label>
                        <select id="rec-efeito" name="efeito" form="recuperacao-form" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <?php foreach (\App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig::EFEITOS as $chave => $rotulo): ?>
                                <option value="<?= htmlspecialchars((string) $chave) ?>"><?= htmlspecialchars((string) $rotulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="rec-aplica-box" class="sm:col-span-2 hidden">
                        <label for="rec-aplica" class="block text-sm font-medium text-gray-700 mb-1">A nota substitui <span class="text-red-500">*</span></label>
                        <select id="rec-aplica" name="aplica_em" form="recuperacao-form"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <?php foreach (\App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig::APLICA_EM as $chave => $rotulo): ?>
                                <option value="<?= htmlspecialchars((string) $chave) ?>"><?= htmlspecialchars((string) $rotulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p id="rec-aplica-ajuda" class="text-xs text-gray-500 mt-1">Os dois períodos: a mesma nota vale para o par. Cada período: entra separado em cada um. Só o primeiro: substitui o que abre o par. Só o último: substitui o que fecha.</p>
                    </div>
                    <?php include __DIR__ . '/_formula_rec.php'; ?>
                    <div>
                        <label for="rec-minima" class="block text-sm font-medium text-gray-700 mb-1">Faz quem ficou abaixo de <span class="text-red-500">*</span></label>
                        <input type="number" id="rec-minima" name="nota_minima" form="recuperacao-form" min="0" max="1000" step="0.01" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="rec-maxima" class="block text-sm font-medium text-gray-700 mb-1">Nota máxima da REC <span class="text-red-500">*</span></label>
                        <input type="number" id="rec-maxima" name="nota_maxima" form="recuperacao-form" min="0" max="1000" step="0.01" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <label class="sm:col-span-2 flex items-start gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" id="rec-permite" name="permite_aumentar" form="recuperacao-form" value="1"
                               class="mt-0.5 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                        <span>
                            <span class="block text-sm font-medium text-gray-900">Quem não ficou de recuperação também pode fazer</span>
                            <span class="block text-xs text-gray-500 mt-0.5">O aluno faz se quiser, para aumentar a nota.</span>
                        </span>
                    </label>
                </div>
                <p id="rec-ajuda" class="text-xs text-gray-500 mt-4">A cada Período Letivo: a prova entra no card de cada período do ano. Rec Final: uma prova só, no fim do ano, com card próprio em Eventos Avaliativos.</p>
            </section>
        </div>
        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="fecharRecuperacao()" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Cancelar</button>
            <button type="submit" form="recuperacao-form" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="recuperacao-salvar">Salvar</span>
            </button>
        </div>
    </form>
</aside>
<script>
(function () {
    var base = <?= json_encode(URL) ?>;
    var podeAlterar = <?= $recPodeAlterar ? 'true' : 'false' ?>;
    var podeExcluir = <?= $recPodeExcluir ? 'true' : 'false' ?>;
    var pedido = 0;
    var onde = {
        bimestre: 'Dentro do card de cada período',
        par: 'Um card a cada dois períodos',
        final: 'Card da Rec Final'
    };

    function preencherSelect(select, opcoes, atual) {
        if (!select || !opcoes) return;
        var html = '';
        Object.keys(opcoes).forEach(function (chave) {
            html += '<option value="' + texto(chave) + '">' + texto(opcoes[chave]) + '</option>';
        });
        select.innerHTML = html;
        if (atual && opcoes[atual]) select.value = atual;
    }

    function aplicarTextos(textos) {
        if (!textos) return;
        onde = textos.onde || onde;
        var quando = document.getElementById('rec-quando');
        var aplica = document.getElementById('rec-aplica');
        preencherSelect(quando, textos.quandos, quando ? quando.value : '');
        preencherSelect(aplica, textos.aplica, aplica ? aplica.value : '');
        var ajuda = document.getElementById('rec-ajuda');
        var aplicaAjuda = document.getElementById('rec-aplica-ajuda');
        if (ajuda && textos.ajuda) ajuda.textContent = textos.ajuda;
        if (aplicaAjuda && textos.aplica_ajuda) aplicaAjuda.textContent = textos.aplica_ajuda;
    }

    function anoAtual() {
        var sel = document.getElementById('regra-ano-letivo');
        var ano = sel ? parseInt(sel.value || '0', 10) : 0;
        return ano >= 2000 ? ano : 0;
    }

    function regraAtual() {
        var campo = document.getElementById('rec-regra');
        return campo ? (parseInt(campo.value || '0', 10) || 0) : 0;
    }

    function texto(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    window.mostrarRecuperacao = function () {
        document.getElementById('recuperacao-backdrop').classList.remove('hidden');
        var drawer = document.getElementById('recuperacao-drawer');
        drawer.classList.remove('translate-x-full');
        drawer.setAttribute('aria-hidden', 'false');
    };
    window.fecharRecuperacao = function () {
        document.getElementById('recuperacao-backdrop').classList.add('hidden');
        var drawer = document.getElementById('recuperacao-drawer');
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
    };
    window.abrirRecuperacao = function (id) {
        if (!anoAtual()) {
            alert('Escolha o ano letivo da regra.');
            return;
        }
        if (!regraAtual()) {
            alert('Salve a regra para cadastrar as recuperações dela.');
            return;
        }
        var atual = ++pedido;
        var form = document.getElementById('recuperacao-form');
        form.reset();
        document.getElementById('rec-ano').value = String(anoAtual());
        form.action = base + '/admin/regras-academicas/recuperacao';
        document.getElementById('recuperacao-titulo').textContent = 'Nova recuperação';
        document.getElementById('recuperacao-salvar').textContent = 'Salvar';
        form.dataset.mode = 'create';
        atualizarRecCampos();
        if (!id) {
            mostrarRecuperacao();
            return;
        }
        fetch(base + '/admin/regras-academicas/recuperacao/' + id + '/dados?regra=' + regraAtual())
            .then(function (resposta) { return resposta.json(); })
            .then(function (dados) {
                if (atual !== pedido) return;
                if (!dados || dados.error) {
                    alert(dados && dados.error ? dados.error : 'Não foi possível carregar.');
                    return;
                }
                form.dataset.mode = 'edit';
                form.action = base + '/admin/regras-academicas/recuperacao/' + id;
                document.getElementById('recuperacao-titulo').textContent = 'Editar recuperação';
                document.getElementById('recuperacao-salvar').textContent = 'Salvar alterações';
                document.getElementById('rec-nome').value = dados.nome || '';
                document.getElementById('rec-quando').value = dados.quando || '';
                document.getElementById('rec-efeito').value = dados.efeito || '';
                document.getElementById('rec-aplica').value = dados.aplica_em || 'par';
                if (window.recFormulaDefinir) window.recFormulaDefinir(dados.formula || '');
                document.getElementById('rec-permite').checked = String(dados.permite_aumentar) === '1';
                document.getElementById('rec-minima').value = dados.nota_minima || '';
                document.getElementById('rec-maxima').value = dados.nota_maxima || '';
                document.getElementById('rec-ano').value = String(anoAtual());
                atualizarRecCampos();
                mostrarRecuperacao();
            })
            .catch(function () {
                if (atual === pedido) alert('Não foi possível carregar.');
            });
    };
    window.excluirRecuperacao = function (id) {
        if (!confirm('Excluir esta recuperação?')) return;
        var corpo = new FormData();
        corpo.append('_token', document.querySelector('#recuperacao-form input[name="_token"]').value);
        corpo.append('_ajax', '1');
        corpo.append('regra_id', String(regraAtual()));
        fetch(base + '/admin/regras-academicas/recuperacao/' + id + '/excluir', { method: 'POST', body: corpo })
            .then(function (resposta) { return resposta.json(); })
            .then(function (dados) {
                if (!dados || !dados.success) {
                    alert(dados && dados.error ? dados.error : 'Não foi possível excluir.');
                    return;
                }
                carregarRecuperacoes();
            })
            .catch(function () { alert('Não foi possível excluir.'); });
    };

    function render(itens) {
        var corpo = document.getElementById('rec-corpo');
        if (!itens.length) {
            corpo.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Nenhuma recuperação nesta regra.</td></tr>';
            return;
        }
        corpo.innerHTML = itens.map(function (item) {
            var id = parseInt(item.id, 10) || 0;
            var acoes = '';
            if (podeAlterar) {
                acoes += '<button type="button" title="Editar" aria-label="Editar" class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50" onclick="abrirRecuperacao(' + id + ')"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>';
            }
            if (podeExcluir) {
                acoes += '<button type="button" title="Excluir" aria-label="Excluir" class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-300 bg-white text-red-500 hover:bg-red-50" onclick="excluirRecuperacao(' + id + ')"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>';
            }
            var quem = 'Abaixo de ' + texto(item.nota_minima);
            if (String(item.permite_aumentar) === '1') {
                quem += '<p class="text-xs text-gray-500 mt-1">Também quem quiser aumentar a nota</p>';
            }
            var efeito = texto(item.efeito_rotulo);
            if (item.aplica_rotulo) {
                efeito += '<p class="text-xs text-gray-500 mt-1">' + texto(item.aplica_rotulo) + '</p>';
            }
            return '<tr>'
                + '<td class="px-4 py-3 font-medium text-gray-900">' + texto(item.nome) + '</td>'
                + '<td class="px-4 py-3 text-gray-700">' + texto(item.quando_rotulo) + '<p class="text-xs text-gray-500 mt-1">' + texto(onde[item.quando] || '') + '</p></td>'
                + '<td class="px-4 py-3 text-gray-700">' + quem + '</td>'
                + '<td class="px-4 py-3 text-gray-700">' + texto(item.nota_maxima) + '</td>'
                + '<td class="px-4 py-3 text-gray-700">' + efeito + '</td>'
                + '<td class="px-4 py-3 text-right"><div class="inline-flex items-center justify-end gap-2">' + acoes + '</div></td>'
                + '</tr>';
        }).join('');
    }

    function carregarRecuperacoes() {
        var ano = anoAtual();
        var regra = regraAtual();
        var semAno = document.getElementById('rec-sem-ano');
        var semRegra = document.getElementById('rec-sem-regra');
        var schema = document.getElementById('rec-schema');
        var isolado = document.getElementById('rec-isolado');
        var tabela = document.getElementById('rec-tabela');
        var botao = document.getElementById('rec-nova');
        schema.classList.add('hidden');
        isolado.classList.add('hidden');
        semAno.classList.add('hidden');
        semRegra.classList.add('hidden');
        if (!ano) {
            semAno.classList.remove('hidden');
            tabela.classList.add('hidden');
            if (botao) botao.classList.add('hidden');
            return;
        }
        if (!regra) {
            semRegra.classList.remove('hidden');
            tabela.classList.add('hidden');
            if (botao) botao.classList.add('hidden');
            return;
        }
        tabela.classList.remove('hidden');
        if (botao) botao.classList.remove('hidden');
        fetch(base + '/admin/regras-academicas/recuperacao/lista?ano=' + ano + '&regra=' + regra)
            .then(function (resposta) { return resposta.json(); })
            .then(function (dados) {
                if (dados && dados.schema_pronto === false) {
                    schema.classList.remove('hidden');
                    tabela.classList.add('hidden');
                    if (botao) botao.classList.add('hidden');
                    return;
                }
                if (dados && dados.bloqueado) {
                    isolado.classList.remove('hidden');
                    if (botao) botao.classList.add('hidden');
                } else if (dados && dados.isolado === false) {
                    isolado.classList.remove('hidden');
                }
                aplicarTextos(dados && dados.textos);
                render((dados && dados.itens) || []);
            })
            .catch(function () {
                document.getElementById('rec-corpo').innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Não foi possível carregar.</td></tr>';
            });
    }

    document.getElementById('recuperacao-form').addEventListener('submit', function (evento) {
        evento.preventDefault();
        document.getElementById('rec-ano').value = String(anoAtual());
        fetch(this.action, { method: 'POST', body: new FormData(this) })
            .then(function (resposta) { return resposta.json(); })
            .then(function (dados) {
                if (!dados || !dados.success) {
                    alert(dados && dados.error ? dados.error : 'Não foi possível salvar.');
                    return;
                }
                fecharRecuperacao();
                carregarRecuperacoes();
            })
            .catch(function () { alert('Não foi possível salvar.'); });
    });

    function atualizarRecCampos() {
        var quando = document.getElementById('rec-quando').value;
        var efeito = document.getElementById('rec-efeito').value;
        var aplica = document.getElementById('rec-aplica');
        var formula = document.getElementById('rec-formula');
        var mostraAplica = quando === 'par';
        var mostraFormula = efeito === 'formula';
        document.getElementById('rec-aplica-box').classList.toggle('hidden', !mostraAplica);
        document.getElementById('rec-formula-box').classList.toggle('hidden', !mostraFormula);
        aplica.required = mostraAplica;
        formula.required = mostraFormula;
        if (!mostraAplica) aplica.value = 'par';
        if (!mostraFormula && window.recFormulaDefinir) window.recFormulaDefinir('');
    }

    document.getElementById('rec-quando').addEventListener('change', atualizarRecCampos);
    document.getElementById('rec-efeito').addEventListener('change', atualizarRecCampos);
    var conceitosPorEscala = <?= json_encode([
        'abcd' => \App\Modulos\RegrasAcademicas\Models\RegraAcademica::conceitosDa('abcd'),
        'conceito' => \App\Modulos\RegrasAcademicas\Models\RegraAcademica::conceitosDa('conceito'),
        'descritiva' => \App\Modulos\RegrasAcademicas\Models\RegraAcademica::conceitosDa('descritiva'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    function atualizarEscala() {
        var escala = document.getElementById('regra-escala').value;
        var mediaBox = document.getElementById('regra-media-box');
        var conceitoBox = document.getElementById('regra-conceito-box');
        var media = document.getElementById('regra-media');
        var conceitoSel = document.getElementById('regra-conceito');
        var numerica = escala === '10' || escala === '100';
        var conceitos = conceitosPorEscala[escala] || null;
        mediaBox.classList.toggle('hidden', !numerica);
        conceitoBox.classList.toggle('hidden', !conceitos);
        if (escala === '100') media.max = '100';
        if (escala === '10') media.max = '10';
        if (!conceitos || !conceitoSel) return;
        var atual = conceitoSel.value;
        var achou = false;
        conceitoSel.innerHTML = '';
        Object.keys(conceitos).forEach(function (chave) {
            var opcao = document.createElement('option');
            opcao.value = chave;
            opcao.textContent = conceitos[chave];
            if (chave === atual) {
                opcao.selected = true;
                achou = true;
            }
            conceitoSel.appendChild(opcao);
        });
        if (!achou && conceitoSel.options.length) {
            conceitoSel.selectedIndex = conceitoSel.options.length - 1;
        }
    }
    document.getElementById('regra-escala').addEventListener('change', atualizarEscala);
    atualizarEscala();

    var cardsPorAno = <?= json_encode($cardsPorAno, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    function desenharCards() {
        var ano = anoAtual();
        var lista = document.getElementById('regra-cards-lista');
        var ajuda = document.getElementById('regra-cards-ajuda');
        var tipoInput = document.querySelector('input[name="periodo_tipo"]');
        var info = ano && cardsPorAno ? (cardsPorAno[ano] || cardsPorAno[String(ano)] || null) : null;
        if (!info || !info.rotulos || !info.rotulos.length) {
            lista.innerHTML = '';
            if (tipoInput) tipoInput.value = 'bimestre';
            ajuda.textContent = 'Escolha o ano letivo para ver os cards de bimestre ou trimestre.';
            return;
        }
        if (tipoInput) tipoInput.value = info.tipo || 'bimestre';
        var campo = String(info.campo || 'Período');
        ajuda.textContent = 'Cada ' + campo.toLowerCase() + ' deste ano já é um card de avaliação em Eventos Avaliativos.';
        lista.innerHTML = info.rotulos.map(function (rotulo) {
            return '<div class="rounded-xl border border-gray-200 bg-white p-4">'
                + '<p class="text-xs font-medium uppercase tracking-wide text-gray-500">' + texto(campo) + '</p>'
                + '<p class="mt-1 text-base font-semibold text-gray-900">' + texto(rotulo) + '</p>'
                + '<p class="mt-1 text-xs text-gray-500">Avaliação</p>'
                + '</div>';
        }).join('');
    }
    var anoSel = document.getElementById('regra-ano-letivo');
    if (anoSel) anoSel.addEventListener('change', function () {
        carregarRecuperacoes();
        desenharCards();
    });
    atualizarRecCampos();
    carregarRecuperacoes();
    desenharCards();

    var seriesCatalogo = <?= json_encode(array_map(static function (array $serie): array {
        return [
            'id' => (int) ($serie['id'] ?? 0),
            'curso_id' => (int) ($serie['curso_id'] ?? 0),
            'nome' => (string) ($serie['nome'] ?? ''),
        ];
    }, $series), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var seriesMarcadas = <?= json_encode(array_values(array_filter(array_map('intval', array_merge(
        (array) $val($item, 'series_ids', []),
        [(int) $val($item, 'serie_id', 0)]
    )))), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var seriesModo = seriesMarcadas.length ? 'algumas' : 'todas';
    var cursoSel = document.getElementById('regra-curso');
    var serieCampo = document.getElementById('regra-serie-campo');
    var serieBotao = document.getElementById('regra-serie-botao');
    var serieTexto = document.getElementById('regra-serie-texto');
    var seriePainel = document.getElementById('regra-serie-painel');
    var serieValores = document.getElementById('regra-serie-valores');
    function seriesVisiveis() {
        var curso = cursoSel ? cursoSel.value : '';
        return seriesCatalogo.filter(function (serie) {
            return serie.id && (!curso || String(serie.curso_id) === String(curso));
        });
    }
    function painelAberto() {
        return seriePainel && !seriePainel.classList.contains('hidden');
    }
    function posicionarPainel() {
        if (!serieBotao || !seriePainel) return;
        var rect = serieBotao.getBoundingClientRect();
        seriePainel.style.position = 'fixed';
        seriePainel.style.left = rect.left + 'px';
        seriePainel.style.top = (rect.bottom + 4) + 'px';
        seriePainel.style.width = rect.width + 'px';
        seriePainel.style.zIndex = '60';
    }
    function abrirPainelSeries(abrir) {
        if (!seriePainel || !serieBotao) return;
        seriePainel.classList.toggle('hidden', !abrir);
        serieBotao.setAttribute('aria-expanded', abrir ? 'true' : 'false');
        if (abrir) posicionarPainel();
    }
    function desenharSeries() {
        if (!seriePainel || !serieTexto || !serieValores || !serieBotao) return;
        var aberto = painelAberto();
        var visiveis = seriesVisiveis();
        var marcadas = {};
        seriesMarcadas.forEach(function (id) { marcadas[String(id)] = true; });
        var ainda = visiveis.filter(function (serie) { return marcadas[String(serie.id)]; });
        seriesMarcadas = ainda.map(function (serie) { return serie.id; });
        if (seriesModo === 'algumas' && seriesMarcadas.length === 0) seriesModo = 'nenhuma';
        var todasAtiva = seriesModo === 'todas';
        var nenhumaAtiva = seriesModo === 'nenhuma';
        serieTexto.textContent = todasAtiva
            ? 'Todas as Séries / Anos'
            : (nenhumaAtiva ? 'Nenhuma' : ainda.map(function (serie) { return serie.nome; }).join(', '));
        serieBotao.classList.toggle('border-red-400', nenhumaAtiva);
        serieBotao.classList.toggle('border-gray-300', !nenhumaAtiva);
        var serieAviso = document.getElementById('regra-serie-aviso');
        if (serieAviso) serieAviso.classList.toggle('hidden', !nenhumaAtiva);
        seriePainel.innerHTML = '';
        serieValores.innerHTML = '';
        var modoCampo = document.createElement('input');
        modoCampo.type = 'hidden';
        modoCampo.name = 'series_modo';
        modoCampo.value = seriesModo;
        serieValores.appendChild(modoCampo);
        function opcao(nome, modo, aoClicar) {
            var forte = modo === 'forte';
            var suave = modo === 'suave';
            var botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-gray-50';
            botao.className += ' text-gray-800';
            botao.setAttribute('role', 'option');
            botao.setAttribute('aria-selected', (forte || suave) ? 'true' : 'false');
            botao.appendChild(document.createTextNode(nome));
            if (forte || suave) {
                var marca = document.createElement('i');
                marca.className = 'fa-solid fa-check text-xs ' + (suave ? 'text-blue-300' : 'text-gray-500');
                marca.setAttribute('aria-hidden', 'true');
                botao.appendChild(marca);
            }
            botao.addEventListener('click', function (evento) {
                evento.preventDefault();
                evento.stopPropagation();
                aoClicar();
            });
            seriePainel.appendChild(botao);
        }
        opcao('Todas as Séries / Anos', todasAtiva ? 'forte' : '', function () {
            seriesModo = 'todas';
            seriesMarcadas = [];
            desenharSeries();
        });
        opcao('Desmarcar todos', '', function () {
            seriesModo = 'nenhuma';
            seriesMarcadas = [];
            desenharSeries();
        });
        ainda.forEach(function (serie) {
            var campo = document.createElement('input');
            campo.type = 'hidden';
            campo.name = 'series_ids[]';
            campo.value = String(serie.id);
            serieValores.appendChild(campo);
        });
        visiveis.forEach(function (serie) {
            var ativa = marcadas[String(serie.id)] === true;
            opcao(serie.nome, todasAtiva ? 'suave' : (ativa ? 'forte' : ''), function () {
                if (todasAtiva) {
                    seriesModo = 'algumas';
                    seriesMarcadas = visiveis.filter(function (item) { return item.id !== serie.id; }).map(function (item) { return item.id; });
                } else if (ativa) {
                    seriesMarcadas = seriesMarcadas.filter(function (id) { return id !== serie.id; });
                    seriesModo = seriesMarcadas.length ? 'algumas' : 'nenhuma';
                } else {
                    seriesModo = 'algumas';
                    if (seriesMarcadas.indexOf(serie.id) < 0) seriesMarcadas.push(serie.id);
                }
                desenharSeries();
            });
        });
        if (aberto) abrirPainelSeries(true);
    }
    if (serieBotao) {
        serieBotao.addEventListener('click', function () {
            abrirPainelSeries(!painelAberto());
        });
    }
    document.addEventListener('click', function (evento) {
        if (!serieCampo || !painelAberto()) return;
        if (!serieCampo.contains(evento.target) && !seriePainel.contains(evento.target)) abrirPainelSeries(false);
    });
    window.addEventListener('resize', function () { if (painelAberto()) posicionarPainel(); });
    window.addEventListener('scroll', function (evento) {
        if (!painelAberto()) return;
        if (seriePainel && (evento.target === seriePainel || seriePainel.contains(evento.target))) return;
        abrirPainelSeries(false);
    }, true);
    if (cursoSel) cursoSel.addEventListener('change', desenharSeries);
    var regraForm = document.getElementById('regra-aprovacao-form');
    if (regraForm) {
        regraForm.addEventListener('submit', function (evento) {
            if (seriesModo !== 'nenhuma') return;
            evento.preventDefault();
            desenharSeries();
            abrirPainelSeries(true);
            if (serieBotao) serieBotao.focus();
        });
    }
    desenharSeries();
})();
</script>
