<?php
$ui = \App\Core\ViewPaths::adminPartial('ui');
$totalPages = max(1, (int) ceil(($total ?? 0) / ($limit ?? 30)));
$page = max(1, (int) ($page ?? 1));

$filtrosAtivos = 0;
foreach (['filtro_materia', 'filtro_tipo', 'filtro_dificuldade', 'filtro_q', 'filtro_ano', 'filtro_topico', 'filtro_tag', 'filtro_origem_titulo'] as $chaveFiltro) {
    if (trim((string) ($$chaveFiltro ?? '')) !== '') {
        $filtrosAtivos++;
    }
}

$opcoesMateria = ['' => 'Todas'];
foreach (($materias ?? []) as $row) {
    $m = (string) ($row['materia'] ?? '');
    if ($m === '') {
        continue;
    }
    $opcoesMateria[$m] = $m . (isset($row['total']) ? ' (' . (int) $row['total'] . ')' : '');
}
$opcoesTipo = ['' => 'Todos'];
foreach (($tipos ?? []) as $row) {
    $t = (string) ($row['tipo'] ?? '');
    if ($t === '') {
        continue;
    }
    $opcoesTipo[$t] = $t . (isset($row['total']) ? ' (' . (int) $row['total'] . ')' : '');
}
$opcoesDificuldade = ['' => 'Todas'];
foreach (($dificuldades ?? []) as $row) {
    $v = (string) ($row['valor'] ?? '');
    if ($v === '') {
        continue;
    }
    $opcoesDificuldade[$v] = $v . (isset($row['total']) ? ' (' . (int) $row['total'] . ')' : '');
}

$base = URL . '/professor/questoes?materia=' . urlencode((string) ($filtro_materia ?? ''))
    . '&tipo=' . urlencode((string) ($filtro_tipo ?? ''))
    . '&ano=' . urlencode((string) ($filtro_ano ?? ''))
    . '&origem_titulo=' . urlencode((string) ($filtro_origem_titulo ?? ''))
    . '&dificuldade=' . urlencode((string) ($filtro_dificuldade ?? ''))
    . '&topico=' . urlencode((string) ($filtro_topico ?? ''))
    . '&tag=' . urlencode((string) ($filtro_tag ?? ''))
    . '&q=' . urlencode((string) ($filtro_q ?? ''));

$rotulosOrigem = [
    'jornada_ia' => 'Gerada por IA',
    'jornada_manual' => 'Criada na jornada',
    'jornada_importacao' => 'Importada na jornada',
    'jornada_reuso' => 'Reutilizada',
    'banco_questoes' => 'Banco de questões',
    'banco_manual' => 'Criada no banco',
    'banco_ia' => 'Gerada pela Tudinha',
    'banco_transcricao' => 'Transcrita',
    'prova_manual' => 'Criada na prova',
    'api_externa' => 'Catálogo externo',
];
$rotulosTipo = [
    'multipla_escolha' => 'Múltipla Escolha',
    'verdadeiro_falso' => 'Verdadeiro ou Falso',
    'dissertativa' => 'Dissertativa',
    'alternativas' => 'Múltipla Escolha',
];
$rotulosNivel = ['facil' => 'Fácil', 'medio' => 'Médio', 'dificil' => 'Difícil'];
$badgeNivel = ['facil' => 'ativo', 'medio' => 'pendente', 'dificil' => 'erro'];

ob_start();
?>
<button type="button" data-csp-click="abrirModalNovaQuestao" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700">
    Nova questão
</button>
<?php
$ui_btn_variant = 'filtro';
$ui_btn_label = 'Filtros';
$ui_btn_icon = 'fa-solid fa-filter';
$ui_btn_onclick = 'openFiltroDrawer()';
$ui_btn_filter_count = $filtrosAtivos;
include $ui . '/btn.php';
$page_header_actions = ob_get_clean();

$page_header_title = 'Banco de Questões';
$page_header_subtitle = 'Questões que você criou na prova, no banco ou nas jornadas. Selecione e monte listas para usar com os alunos.';
include \App\Core\ViewPaths::adminPartial('page_header_list.php');
?>

<?php if (!empty($success)): ?>
    <div class="mb-6 bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 text-sm">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div id="modal-nova-questao" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50" style="display: none;">
    <div class="bg-white rounded-xl shadow-2xl p-4 md:p-6 max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Nova questão</h2>
            <div class="flex flex-wrap items-center gap-2">
                <?php if (\App\Core\CreditosModuleRegistry::gerarExercicioProvaLiberado()): ?>
                <button type="button" data-csp-click="abrirModalGerarIaBanco" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Gerar com a Tudinha</button>
                <?php endif; ?>
                <button type="button" data-csp-click="abrirModalTranscreverBanco" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Transcrever</button>
                <button type="button" data-csp-click="fecharModalNovaQuestao" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
        </div>
    <form id="form-nova-questao" method="post" action="<?= URL ?>/professor/questoes/criar" class="questao-form space-y-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
        <input type="hidden" name="enunciado" id="banco-enunciado">
        <input type="hidden" name="alternativas_json" id="banco-alternativas-json" value="[]">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="banco-tipo" class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de questão</label>
                <select id="banco-tipo" name="tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="multipla_escolha">Múltipla Escolha</option>
                    <option value="verdadeiro_falso">Verdadeiro ou Falso</option>
                    <option value="dissertativa">Dissertativa</option>
                </select>
            </div>
            <div>
                <label for="banco-nivel" class="block text-sm font-medium text-gray-700 mb-1.5">Nível</label>
                <select id="banco-nivel" name="nivel" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="facil">Fácil</option>
                    <option value="medio">Médio</option>
                    <option value="dificil">Difícil</option>
                </select>
            </div>
            <div>
                <label for="banco-valor" class="block text-sm font-medium text-gray-700 mb-1.5">Pontuação</label>
                <input id="banco-valor" type="number" name="valor" step="0.1" min="0" value="1.00" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        <div>
            <label for="banco-materia" class="block text-sm font-medium text-gray-700 mb-1.5">Matéria</label>
            <?php if (!empty($materias_professor)): ?>
                <select id="banco-materia" name="materia" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Selecione</option>
                    <?php foreach ($materias_professor as $matProf): ?>
                        <option value="<?= htmlspecialchars((string) ($matProf['nome'] ?? '')) ?>"><?= htmlspecialchars((string) ($matProf['nome'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input id="banco-materia" type="text" name="materia" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ex.: Biologia">
            <?php endif; ?>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Enunciado</label>
            <div class="editor-toolbar">
                <button type="button" class="tb-btn" data-csp-click="fmtBancoEnunciado" data-csp-arg="bold"><b>B</b></button>
                <button type="button" class="tb-btn" data-csp-click="fmtBancoEnunciado" data-csp-arg="italic"><i>I</i></button>
                <button type="button" class="tb-btn" data-csp-click="fmtBancoEnunciado" data-csp-arg="underline"><u>U</u></button>
            </div>
            <div id="banco-editor-enunciado" class="rich-editor" contenteditable="true" data-placeholder="Digite o enunciado da questão"></div>
        </div>
        <div id="banco-opcoes">
            <label class="block text-sm font-medium text-gray-700 mb-2">Alternativas</label>
            <div id="banco-opcoes-lista" class="alt-list mb-3"></div>
            <div class="flex flex-wrap items-center gap-4">
                <button type="button" data-csp-click="adicionarAlternativaBanco" class="px-4 py-2 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">+ Adicionar Alternativa</button>
                <span class="text-xs text-gray-500">Máximo 5 alternativas (A, B, C, D, E)</span>
            </div>
        </div>
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">Adicionar Questão</button>
    </form>
    </div>
</div>

<div id="modal-gerar-ia-banco" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[60]" style="display: none;">
    <div class="bg-white rounded-xl shadow-2xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-900">Gerar com a Tudinha</h3>
            <button type="button" data-csp-click="fecharModalGerarIaBanco" class="text-gray-400 hover:text-gray-600" aria-label="Fechar"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form id="form-gerar-ia-banco" class="space-y-4">
            <div>
                <label for="ia-materia" class="block text-sm font-medium text-gray-700 mb-1.5">Matéria</label>
                <input id="ia-materia" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Ex.: Biologia">
            </div>
            <div>
                <label for="ia-contexto" class="block text-sm font-medium text-gray-700 mb-1.5">Contexto</label>
                <textarea id="ia-contexto" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Tema, objetivos ou o que a questão deve cobrar"></textarea>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="ia-tipo" class="block text-sm font-medium text-gray-700 mb-1.5">Tipo</label>
                    <select id="ia-tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                        <option value="alternativas">Múltipla Escolha</option>
                        <option value="verdadeiro_falso">Verdadeiro ou Falso</option>
                        <option value="dissertativa">Dissertativa</option>
                    </select>
                </div>
                <div>
                    <label for="ia-serie" class="block text-sm font-medium text-gray-700 mb-1.5">Série</label>
                    <input id="ia-serie" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Ex.: 1ª série EM">
                </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <label class="text-sm text-gray-700">Fácil<input id="ia-facil" type="number" min="0" max="20" value="0" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg"></label>
                <label class="text-sm text-gray-700">Médio<input id="ia-medio" type="number" min="0" max="20" value="0" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg"></label>
                <label class="text-sm text-gray-700">Difícil<input id="ia-dificil" type="number" min="0" max="20" value="0" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg"></label>
                <label class="text-sm text-gray-700">Desafio<input id="ia-desafio" type="number" min="0" max="20" value="0" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg"></label>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" data-csp-click="fecharModalGerarIaBanco" class="px-4 py-2 bg-gray-500 text-white rounded-lg">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Gerar questões</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-transcrever-banco" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[60]" style="display: none;">
    <div class="bg-white rounded-xl shadow-2xl p-6 max-w-2xl w-full mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-900">Transcrever</h3>
            <button type="button" data-csp-click="fecharModalTranscreverBanco" class="text-gray-400 hover:text-gray-600" aria-label="Fechar"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form id="form-transcrever-banco" class="space-y-4" enctype="multipart/form-data">
            <p class="text-sm text-gray-600">Envie o PDF ou a imagem da questão. O texto, as alternativas e o gabarito entram no banco.</p>
            <input id="arquivo-transcrever-banco" name="imagem" type="file" accept="application/pdf,image/png,image/jpeg,image/webp" class="block w-full text-sm text-gray-700">
            <p id="transcrever-status" class="hidden text-sm text-green-700">Lendo o arquivo e separando as questões...</p>
            <div class="flex justify-end gap-3">
                <button type="button" data-csp-click="fecharModalTranscreverBanco" class="px-4 py-2 bg-gray-500 text-white rounded-lg">Cancelar</button>
                <button type="submit" id="btn-transcrever-banco" class="px-4 py-2 bg-green-600 text-white rounded-lg">Transcrever</button>
            </div>
        </form>
    </div>
</div>

<form id="form-questoes" method="post" class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
            <label for="titulo-lista" class="block text-sm font-medium text-gray-700 mb-1">Título da lista</label>
            <input id="titulo-lista" type="text" name="titulo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Ex.: Revisão de Biologia">
        </div>
        <?php
        $ui_btn_variant = 'complementar';
        $ui_btn_label = 'Marcar página';
        $ui_btn_id = 'btn-marcar-todas';
        $ui_btn_icon = '';
        include $ui . '/btn.php';

        $ui_btn_variant = 'complementar';
        $ui_btn_label = 'Desmarcar';
        $ui_btn_id = 'btn-desmarcar-todas';
        include $ui . '/btn.php';

        $ui_btn_variant = 'confirm';
        $ui_btn_label = 'Salvar lista';
        $ui_btn_type = 'submit';
        $ui_btn_attrs = 'formaction="' . htmlspecialchars(URL . '/professor/questoes/montagens/salvar', ENT_QUOTES) . '" formmethod="post"';
        include $ui . '/btn.php';

        $ui_btn_variant = 'primary';
        $ui_btn_label = 'Baixar PDF';
        $ui_btn_type = 'submit';
        $ui_btn_attrs = 'formaction="' . htmlspecialchars(URL . '/professor/questoes/pdf/selecionadas', ENT_QUOTES) . '" formmethod="post"';
        include $ui . '/btn.php';
        ?>
        <span class="text-sm text-gray-500 pb-2"><?= (int) ($total ?? 0) ?> questão(ões)</span>
    </div>

    <?php
    ob_start();
    if (empty($questoes)):
        $ui_tabela_vazia_colspan = 7;
        $ui_tabela_vazia_icone = 'fa-solid fa-list-check';
        $ui_tabela_vazia_mensagem = 'Nenhuma questão encontrada no seu banco.';
        include $ui . '/tabela_vazia.php';
    else:
        foreach ($questoes as $q):
            $nivel = (string) ($q['nivel_dificuldade'] ?? '');
            $nivelLabel = $rotulosNivel[$nivel] ?? $nivel;
            $origemRaw = (string) ($q['origem'] ?? '');
            $origemLabel = $rotulosOrigem[$origemRaw] ?? $origemRaw;
            $preview = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($q['enunciado_html'] ?? ''))));
            if (mb_strlen($preview) > 180) {
                $preview = mb_substr($preview, 0, 180) . '…';
            }
            $tituloQuestao = trim((string) ($q['titulo'] ?? ''));
            if ($tituloQuestao === '') {
                $tituloQuestao = $preview !== '' ? mb_substr($preview, 0, 80) : 'Questão';
            }
            $alternativasDecoded = json_decode((string) ($q['alternativas_json'] ?? ''), true);
            $alternativasSafe = is_array($alternativasDecoded) ? json_encode($alternativasDecoded, JSON_UNESCAPED_UNICODE) : '{}';
            ?>
            <tr class="hover:bg-gray-50 align-top">
                <td class="px-6 py-4">
                    <input type="checkbox" name="questao_ids[]" value="<?= (int) $q['id'] ?>" class="checkbox-questao w-4 h-4 rounded border-gray-300">
                </td>
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($tituloQuestao) ?></div>
                    <div class="mt-1 flex flex-wrap gap-2 text-xs text-gray-500">
                        <?php if (!empty($q['assunto'])): ?>
                            <span><?= htmlspecialchars((string) $q['assunto']) ?></span>
                        <?php endif; ?>
                        <?php if ($origemLabel !== ''): ?>
                            <span><?= htmlspecialchars($origemLabel) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($preview !== ''): ?>
                        <p class="mt-2 text-sm text-gray-600 line-clamp-3"><?= htmlspecialchars($preview) ?></p>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap"><?= htmlspecialchars((string) ($q['materia'] ?? '')) ?></td>
                <td class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap"><?= htmlspecialchars($rotulosTipo[(string) ($q['tipo'] ?? '')] ?? (string) ($q['tipo'] ?? '')) ?></td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <?php if ($nivelLabel !== ''): ?>
                        <?php
                        $ui_badge_variant = $badgeNivel[$nivel] ?? 'neutro';
                        $ui_badge_label = $nivelLabel;
                        include $ui . '/badge.php';
                        ?>
                    <?php else: ?>
                        <span class="text-gray-400 text-sm">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="text-sm font-semibold text-gray-900"><?= htmlspecialchars((string) (($q['gabarito'] ?? '') !== '' ? $q['gabarito'] : '—')) ?></span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                    <?php
                        $ui_btn_variant = 'detalhes';
                        $ui_btn_label = 'Visualizar';
                        $ui_btn_class = 'btn-visualizar';
                        $ui_btn_type = 'button';
                        $ui_btn_attrs = 'data-external-id="' . htmlspecialchars((string) ($q['external_id'] ?? ''), ENT_QUOTES) . '"'
                            . ' data-materia="' . htmlspecialchars((string) ($q['materia'] ?? ''), ENT_QUOTES) . '"'
                            . ' data-tipo="' . htmlspecialchars((string) ($q['tipo'] ?? ''), ENT_QUOTES) . '"'
                            . ' data-enunciado="' . htmlspecialchars((string) ($q['enunciado_html'] ?? ''), ENT_QUOTES) . '"'
                            . ' data-gabarito="' . htmlspecialchars((string) ($q['gabarito'] ?? ''), ENT_QUOTES) . '"'
                            . ' data-alternativas="' . htmlspecialchars((string) $alternativasSafe, ENT_QUOTES) . '"';
                        include $ui . '/btn.php';
                        ?>
                </td>
            </tr>
            <?php
        endforeach;
    endif;
    $ui_tabela_body = ob_get_clean();
    $ui_tabela_colunas = [
        ['label' => '', 'class' => 'px-6 py-3 w-12'],
        'Questão',
        'Matéria',
        'Tipo',
        'Nível',
        'Gabarito',
        ['label' => 'Ações', 'class' => 'px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider'],
    ];
    include $ui . '/tabela.php';
    ?>
</form>

<div class="mt-4 flex items-center justify-between gap-3">
    <div class="text-sm text-gray-500">Página <?= $page ?> de <?= $totalPages ?></div>
    <div class="flex gap-2">
        <?php
        $ui_btn_variant = 'complementar';
        $ui_btn_label = 'Anterior';
        $ui_btn_href = $base . '&page=' . max(1, $page - 1);
        $ui_btn_class = $page <= 1 ? 'pointer-events-none opacity-50' : '';
        include $ui . '/btn.php';

        $ui_btn_variant = 'complementar';
        $ui_btn_label = 'Próxima';
        $ui_btn_href = $base . '&page=' . min($totalPages, $page + 1);
        $ui_btn_class = $page >= $totalPages ? 'pointer-events-none opacity-50' : '';
        include $ui . '/btn.php';
        ?>
    </div>
</div>

<div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Listas montadas</h3>
    <?php if (empty($montagens)): ?>
        <p class="text-sm text-gray-500">Nenhuma lista montada ainda.</p>
    <?php else: ?>
        <div class="divide-y divide-gray-200">
            <?php foreach ($montagens as $m): ?>
                <div class="flex items-center justify-between gap-3 py-3">
                    <div>
                        <div class="font-medium text-gray-900"><?= htmlspecialchars((string) ($m['titulo'] ?? '')) ?></div>
                        <div class="text-xs text-gray-500"><?= (int) ($m['total_itens'] ?? 0) ?> questão(ões) · <?= htmlspecialchars((string) ($m['created_at'] ?? '')) ?></div>
                    </div>
                    <?php
                    $ui_btn_variant = 'detalhes';
                    $ui_btn_label = 'Baixar PDF';
                    $ui_btn_href = URL . '/professor/questoes/montagens/' . (int) $m['id'] . '/pdf';
                    include $ui . '/btn.php';
                    ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
ob_start();
?>
<form method="get" action="<?= URL ?>/professor/questoes" class="flex flex-col flex-1 overflow-hidden">
    <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-2">
        <?php
        $ui_form_campo_label = 'Matéria';
        $ui_form_campo_name = 'materia';
        $ui_form_campo_tipo = 'select';
        $ui_form_campo_opcoes = $opcoesMateria;
        $ui_form_campo_value = (string) ($filtro_materia ?? '');
        $ui_form_campo_span = 'full';
        $ui_form_campo_mb = 'mb-4';
        $ui_form_campo_obrigatorio = false;
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Tipo';
        $ui_form_campo_name = 'tipo';
        $ui_form_campo_tipo = 'select';
        $ui_form_campo_opcoes = $opcoesTipo;
        $ui_form_campo_value = (string) ($filtro_tipo ?? '');
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Dificuldade';
        $ui_form_campo_name = 'dificuldade';
        $ui_form_campo_tipo = 'select';
        $ui_form_campo_opcoes = $opcoesDificuldade;
        $ui_form_campo_value = (string) ($filtro_dificuldade ?? '');
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Busca';
        $ui_form_campo_name = 'q';
        $ui_form_campo_tipo = 'text';
        $ui_form_campo_value = (string) ($filtro_q ?? '');
        $ui_form_campo_placeholder = 'Título ou texto do enunciado';
        $ui_form_campo_opcoes = [];
        include $ui . '/form_campo.php';

        if (!empty($integracao_api_externa)):
            $opcoesAno = ['' => 'Todos'];
            foreach (($anos ?? []) as $row) {
                $v = (string) ($row['valor'] ?? '');
                if ($v !== '') {
                    $opcoesAno[$v] = $v . ' (' . (int) ($row['total'] ?? 0) . ')';
                }
            }
            $opcoesTopico = ['' => 'Todos'];
            foreach (($topicos ?? []) as $row) {
                $v = (string) ($row['valor'] ?? '');
                if ($v !== '') {
                    $opcoesTopico[$v] = $v . ' (' . (int) ($row['total'] ?? 0) . ')';
                }
            }
            $opcoesTag = ['' => 'Todas'];
            foreach (($tags ?? []) as $row) {
                $v = (string) ($row['valor'] ?? '');
                if ($v !== '') {
                    $opcoesTag[$v] = $v . ' (' . (int) ($row['total'] ?? 0) . ')';
                }
            }
            $opcoesOrigem = ['' => 'Todas'];
            foreach (($origens_titulo ?? []) as $row) {
                $v = (string) ($row['valor'] ?? '');
                if ($v !== '') {
                    $opcoesOrigem[$v] = $v . ' (' . (int) ($row['total'] ?? 0) . ')';
                }
            }

            $ui_form_campo_label = 'Ano';
            $ui_form_campo_name = 'ano';
            $ui_form_campo_tipo = 'select';
            $ui_form_campo_opcoes = $opcoesAno;
            $ui_form_campo_value = (string) ($filtro_ano ?? '');
            $ui_form_campo_placeholder = '';
            include $ui . '/form_campo.php';

            $ui_form_campo_label = 'Tópico';
            $ui_form_campo_name = 'topico';
            $ui_form_campo_opcoes = $opcoesTopico;
            $ui_form_campo_value = (string) ($filtro_topico ?? '');
            include $ui . '/form_campo.php';

            $ui_form_campo_label = 'Tag';
            $ui_form_campo_name = 'tag';
            $ui_form_campo_opcoes = $opcoesTag;
            $ui_form_campo_value = (string) ($filtro_tag ?? '');
            include $ui . '/form_campo.php';

            $ui_form_campo_label = 'Origem/Título';
            $ui_form_campo_name = 'origem_titulo';
            $ui_form_campo_opcoes = $opcoesOrigem;
            $ui_form_campo_value = (string) ($filtro_origem_titulo ?? '');
            include $ui . '/form_campo.php';
        endif;
        ?>
    </div>
    <div class="px-6 sm:px-8 py-4 border-t border-gray-200 flex gap-3">
        <?php
        $ui_btn_variant = 'complementar';
        $ui_btn_label = 'Limpar';
        $ui_btn_href = URL . '/professor/questoes';
        $ui_btn_class = 'flex-1 justify-center';
        $ui_btn_type = 'button';
        $ui_btn_attrs = '';
        include $ui . '/btn.php';

        $ui_btn_variant = 'confirm';
        $ui_btn_label = 'Aplicar filtros';
        $ui_btn_type = 'submit';
        $ui_btn_href = '';
        $ui_btn_class = 'flex-1 justify-center';
        include $ui . '/btn.php';
        ?>
    </div>
</form>
<?php
$ui_offcanvas_body = ob_get_clean();
$ui_offcanvas_id = 'filtro';
$ui_offcanvas_titulo = 'Filtros';
$ui_offcanvas_max_w = 'max-w-md';
include $ui . '/offcanvas.php';
?>

<div id="modal-questao" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[60] px-4">
    <div class="bg-white w-full max-w-3xl rounded-xl shadow-xl border border-gray-200">
        <div class="flex items-center justify-between px-6 py-5 border-b border-gray-200">
            <h3 class="text-xl font-bold text-gray-900">Visualizar questão</h3>
            <button type="button" id="modal-fechar" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <div class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
            <div class="text-sm text-gray-600">
                <span><strong>Matéria:</strong> <span id="m-materia"></span></span>
                <span class="mx-2">·</span>
                <span><strong>Tipo:</strong> <span id="m-tipo"></span></span>
            </div>
            <div>
                <h4 class="font-semibold text-gray-900 mb-2">Enunciado</h4>
                <div id="m-enunciado" class="prose prose-sm max-w-none text-gray-800 border border-gray-200 rounded-lg p-3"></div>
            </div>
            <div>
                <h4 class="font-semibold text-gray-900 mb-2">Alternativas</h4>
                <ul id="m-alternativas" class="space-y-2 text-sm text-gray-800"></ul>
            </div>
            <div class="bg-green-50 border border-green-200 rounded-lg px-3 py-2 text-green-800">
                <strong>Gabarito:</strong> <span id="m-gabarito"></span>
            </div>
        </div>
    </div>
</div>

<script <?= $csp_nonce_attr ?? \App\Core\CspPolitica::attrNonce() ?>>
var tokenBanco = <?= json_encode((string) ($csrf_token ?? '')) ?>;
var urlStatusJob = <?= json_encode(rtrim((string) URL, '/') . '/professor/ai-job/') ?>;
function abrirModal(id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
}
function fecharModal(id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('hidden');
    modal.style.display = 'none';
}
function abrirModalNovaQuestao() { abrirModal('modal-nova-questao'); }
function fecharModalNovaQuestao() { fecharModal('modal-nova-questao'); }
function abrirModalGerarIaBanco() { abrirModal('modal-gerar-ia-banco'); }
function fecharModalGerarIaBanco() { fecharModal('modal-gerar-ia-banco'); }
function abrirModalTranscreverBanco() { abrirModal('modal-transcrever-banco'); }
function fecharModalTranscreverBanco() { fecharModal('modal-transcrever-banco'); }
function esperarJob(jobId) {
    var tentativas = 0;
    return new Promise(function (resolve, reject) {
        function consultar() {
            tentativas++;
            if (tentativas > 90) {
                reject(new Error('A operação demorou demais. Tente de novo.'));
                return;
            }
            fetch(urlStatusJob + jobId + '/status', { credentials: 'include', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (resposta) { return resposta.json(); })
                .then(function (data) {
                    if (data.status === 'done') {
                        resolve(data);
                        return;
                    }
                    if (data.status === 'failed' || data.status === 'error' || data.status === 'not_found') {
                        reject(new Error(data.error || 'Não foi possível concluir.'));
                        return;
                    }
                    setTimeout(consultar, 2000);
                })
                .catch(reject);
        }
        consultar();
    });
}
document.getElementById('form-gerar-ia-banco')?.addEventListener('submit', function (event) {
    event.preventDefault();
    var pedidos = {
        'Fácil': parseInt(document.getElementById('ia-facil').value, 10) || 0,
        'Médio': parseInt(document.getElementById('ia-medio').value, 10) || 0,
        'Difícil': parseInt(document.getElementById('ia-dificil').value, 10) || 0,
        'Desafio': parseInt(document.getElementById('ia-desafio').value, 10) || 0
    };
    var total = pedidos['Fácil'] + pedidos['Médio'] + pedidos['Difícil'] + pedidos['Desafio'];
    if (total <= 0) {
        alert('Informe a quantidade em pelo menos um nível.');
        return;
    }
    var botao = event.submitter;
    if (botao) botao.disabled = true;
    fetch(<?= json_encode(rtrim((string) URL, '/') . '/professor/questoes/gerar-ia') ?>, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({
            _token: tokenBanco,
            materia: document.getElementById('ia-materia').value,
            contexto: document.getElementById('ia-contexto').value,
            tipo: document.getElementById('ia-tipo').value,
            serie: document.getElementById('ia-serie').value,
            quantidades_por_nivel: pedidos
        })
    }).then(function (resposta) { return resposta.json(); })
        .then(function (data) {
            if (!data.success || !data.job_id) throw new Error(data.error || 'Não foi possível gerar.');
            return esperarJob(data.job_id).then(function () {
                return fetch(<?= json_encode(rtrim((string) URL, '/') . '/professor/questoes/importar-ia') ?>, {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ _token: tokenBanco, job_id: data.job_id })
                }).then(function (resposta) { return resposta.json(); });
            });
        })
        .then(function (data) {
            if (!data.success) throw new Error(data.error || 'Não foi possível salvar as questões.');
            window.location.href = <?= json_encode(rtrim((string) URL, '/') . '/professor/questoes?success=') ?> + encodeURIComponent(data.message || 'Questões adicionadas ao banco.');
        })
        .catch(function (erro) {
            alert(erro.message || 'Não foi possível gerar as questões.');
            if (botao) botao.disabled = false;
        });
});
document.getElementById('form-transcrever-banco')?.addEventListener('submit', function (event) {
    event.preventDefault();
    var arquivo = document.getElementById('arquivo-transcrever-banco');
    if (!arquivo || !arquivo.files || !arquivo.files[0]) {
        alert('Selecione um PDF ou uma imagem.');
        return;
    }
    var dados = new FormData();
    dados.append('_token', tokenBanco);
    dados.append('imagem', arquivo.files[0]);
    var materia = document.getElementById('banco-materia');
    var status = document.getElementById('transcrever-status');
    var botao = document.getElementById('btn-transcrever-banco');
    if (status) status.classList.remove('hidden');
    if (botao) botao.disabled = true;
    fetch(<?= json_encode(rtrim((string) URL, '/') . '/professor/questoes/transcrever') ?>, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: dados
    }).then(function (resposta) { return resposta.json(); })
        .then(function (data) {
            if (!data.success || !data.job_id) throw new Error(data.error || 'Não foi possível transcrever.');
            return esperarJob(data.job_id).then(function () {
                return fetch(<?= json_encode(rtrim((string) URL, '/') . '/professor/questoes/importar-transcricao') ?>, {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({
                        _token: tokenBanco,
                        job_id: data.job_id,
                        materia: materia ? materia.value : ''
                    })
                }).then(function (resposta) { return resposta.json(); });
            });
        })
        .then(function (data) {
            if (!data.success) throw new Error(data.error || 'Não foi possível salvar a transcrição.');
            window.location.href = <?= json_encode(rtrim((string) URL, '/') . '/professor/questoes?success=') ?> + encodeURIComponent(data.message || 'Questões transcritas no banco.');
        })
        .catch(function (erro) {
            alert(erro.message || 'Não foi possível transcrever.');
            if (status) status.classList.add('hidden');
            if (botao) botao.disabled = false;
        });
});
var bancoOpcoes = 0;
var bancoLetras = ['A', 'B', 'C', 'D', 'E'];
function fmtBancoEnunciado(cmd) {
    var editor = document.getElementById('banco-editor-enunciado');
    if (editor) editor.focus();
    document.execCommand(cmd, false, null);
}
function adicionarAlternativaBanco() {
    if (bancoOpcoes >= 5) {
        alert('Máximo de 5 alternativas permitidas (A, B, C, D, E)');
        return;
    }
    var letra = bancoLetras[bancoOpcoes];
    var idx = bancoOpcoes;
    bancoOpcoes++;
    var item = document.createElement('div');
    item.className = 'alt-item';
    item.setAttribute('data-opcao-index', String(idx));
    item.innerHTML = '<div class="alt-letter">' + letra + '</div>'
        + '<div class="alt-input-wrap"><div class="alt-editor" contenteditable="true" data-placeholder="Alternativa ' + letra + '..."></div></div>'
        + '<div class="alt-actions flex items-center gap-3">'
        + '<label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="banco_gabarito" value="' + idx + '" class="w-5 h-5"><span class="alt-correct">Gabarito</span></label>'
        + '<button type="button" data-csp-click="removerAlternativaBanco" data-csp-pass="el" class="px-3 py-2 bg-red-500 text-white rounded text-sm hover:bg-red-600">Remover</button>'
        + '</div>';
    document.getElementById('banco-opcoes-lista').appendChild(item);
}
function removerAlternativaBanco(botao) {
    var item = botao.closest('.alt-item');
    if (item) item.remove();
    bancoOpcoes = Math.max(0, bancoOpcoes - 1);
    document.querySelectorAll('#banco-opcoes-lista .alt-item').forEach(function (opcao, index) {
        var letra = bancoLetras[index];
        opcao.setAttribute('data-opcao-index', String(index));
        var letter = opcao.querySelector('.alt-letter');
        if (letter) letter.textContent = letra;
        var radio = opcao.querySelector('input[type="radio"]');
        if (radio) radio.value = String(index);
    });
}
function atualizarAlternativasBanco() {
    var tipo = document.getElementById('banco-tipo');
    var caixa = document.getElementById('banco-opcoes');
    if (!tipo || !caixa) return;
    if (tipo.value === 'multipla_escolha') {
        caixa.classList.remove('hidden');
        if (bancoOpcoes === 0) {
            adicionarAlternativaBanco();
            adicionarAlternativaBanco();
        }
    } else {
        caixa.classList.add('hidden');
    }
}
document.getElementById('banco-tipo')?.addEventListener('change', atualizarAlternativasBanco);
atualizarAlternativasBanco();
document.getElementById('form-nova-questao')?.addEventListener('submit', function (event) {
    var editor = document.getElementById('banco-editor-enunciado');
    var hidden = document.getElementById('banco-enunciado');
    if (editor && hidden) hidden.value = editor.innerHTML || '';
    var texto = editor ? (editor.innerText || '').trim() : '';
    if (texto === '') {
        event.preventDefault();
        alert('O enunciado é obrigatório.');
        return;
    }
    var tipo = document.getElementById('banco-tipo');
    var lista = [];
    if (tipo && tipo.value === 'multipla_escolha') {
        var marcado = document.querySelector('#banco-opcoes-lista input[name="banco_gabarito"]:checked');
        if (!marcado) {
            event.preventDefault();
            alert('Marque o gabarito.');
            return;
        }
        document.querySelectorAll('#banco-opcoes-lista .alt-item').forEach(function (item) {
            var ed = item.querySelector('.alt-editor');
            var radio = item.querySelector('input[type="radio"]');
            lista.push({
                texto: ed ? (ed.innerHTML || '').trim() : '',
                correta: radio && radio.checked ? 1 : 0
            });
        });
        if (lista.filter(function (item) { return (item.texto || '').replace(/<[^>]*>/g, '').trim() !== ''; }).length < 2) {
            event.preventDefault();
            alert('Adicione pelo menos 2 alternativas.');
            return;
        }
    }
    var campo = document.getElementById('banco-alternativas-json');
    if (campo) campo.value = JSON.stringify(lista);
});

document.getElementById('btn-marcar-todas')?.addEventListener('click', function () {
    document.querySelectorAll('.checkbox-questao').forEach(function (el) { el.checked = true; });
});
document.getElementById('btn-desmarcar-todas')?.addEventListener('click', function () {
    document.querySelectorAll('.checkbox-questao').forEach(function (el) { el.checked = false; });
});

const modal = document.getElementById('modal-questao');
const modalFechar = document.getElementById('modal-fechar');
const mMateria = document.getElementById('m-materia');
const mTipo = document.getElementById('m-tipo');
const mEnunciado = document.getElementById('m-enunciado');
const mAlternativas = document.getElementById('m-alternativas');
const mGabarito = document.getElementById('m-gabarito');

document.querySelectorAll('.btn-visualizar').forEach(function (btn) {
    btn.addEventListener('click', function () {
        mMateria.textContent = btn.dataset.materia || '—';
        mTipo.textContent = btn.dataset.tipo || '—';
        mEnunciado.innerHTML = btn.dataset.enunciado || '';
        mGabarito.textContent = btn.dataset.gabarito || '—';

        mAlternativas.innerHTML = '';
        let alternativas = {};
        try {
            alternativas = JSON.parse(btn.dataset.alternativas || '{}');
        } catch (e) {
            alternativas = {};
        }
        const keys = Object.keys(alternativas);
        if (keys.length === 0) {
            const li = document.createElement('li');
            li.className = 'text-gray-500';
            li.textContent = 'Sem alternativas cadastradas.';
            mAlternativas.appendChild(li);
        } else {
            keys.forEach(function (k) {
                const li = document.createElement('li');
                const val = (alternativas[k] ?? '').toString();
                li.className = 'border border-gray-200 rounded-lg p-2';
                li.innerHTML = '<strong>' + k + ')</strong> ' + val;
                mAlternativas.appendChild(li);
            });
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    });
});

function fecharModalQuestao() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

modalFechar?.addEventListener('click', fecharModalQuestao);
modal?.addEventListener('click', function (e) {
    if (e.target === modal) fecharModalQuestao();
});
</script>
