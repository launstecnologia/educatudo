<?php
$ui = __DIR__ . '/../../admin/_partials/ui';
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
    'api_externa' => 'Catálogo externo',
];
$rotulosNivel = ['facil' => 'Fácil', 'medio' => 'Médio', 'dificil' => 'Difícil'];
$badgeNivel = ['facil' => 'ativo', 'medio' => 'pendente', 'dificil' => 'erro'];

ob_start();
$ui_btn_variant = 'filtro';
$ui_btn_label = 'Filtros';
$ui_btn_icon = 'fa-solid fa-filter';
$ui_btn_onclick = 'openFiltroDrawer()';
$ui_btn_filter_count = $filtrosAtivos;
include $ui . '/btn.php';
$page_header_actions = ob_get_clean();

$page_header_title = 'Banco de Questões';
$page_header_subtitle = 'Questões que você criou ou gerou nas jornadas. Selecione e monte listas para usar com os alunos.';
include __DIR__ . '/../../admin/_partials/page_header_list.php';
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
                <td class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap"><?= htmlspecialchars((string) ($q['tipo'] ?? '')) ?></td>
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

<script>
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
