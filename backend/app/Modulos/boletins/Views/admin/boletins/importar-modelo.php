<?php
$csrf_token = (string) ($csrf_token ?? '');
$flash = is_array($flash ?? null) ? $flash : null;
$job_id = isset($job_id) ? (int) $job_id : 0;
$job = is_array($job ?? null) ? $job : null;
$rascunho = is_array($rascunho_modelo_ia ?? null) ? $rascunho_modelo_ia : null;
$catalogoOpcoes = is_array($catalogo_opcoes ?? null) ? $catalogo_opcoes : [];
$tudicoinsDisponivel = !empty($tudicoins_disponivel);

$opcoesPecasCatalogo = [];
foreach ((array) ($catalogoOpcoes['pecas'] ?? []) as $pecaCat) {
    if (!is_array($pecaCat)) {
        continue;
    }
    $keyCat = trim((string) ($pecaCat['key'] ?? ''));
    if ($keyCat === '') {
        continue;
    }
    $opcoesPecasCatalogo[] = [
        'id' => $keyCat,
        'label' => trim((string) ($pecaCat['label'] ?? $keyCat)),
    ];
}
$opcoesTiposCatalogo = [];
foreach ((array) ($catalogoOpcoes['tipos'] ?? []) as $tipoCat) {
    if (!is_array($tipoCat)) {
        continue;
    }
    $idCat = (int) ($tipoCat['id'] ?? 0);
    if ($idCat <= 0) {
        continue;
    }
    $opcoesTiposCatalogo[] = [
        'id' => $idCat,
        'label' => trim((string) ($tipoCat['nome'] ?? ('Tipo #' . $idCat))),
    ];
}
$opcoesMateriasCatalogo = [];
foreach ((array) ($catalogoOpcoes['materias'] ?? []) as $matCat) {
    if (!is_array($matCat)) {
        continue;
    }
    $idCat = (int) ($matCat['id'] ?? 0);
    if ($idCat <= 0) {
        continue;
    }
    $opcoesMateriasCatalogo[] = [
        'id' => $idCat,
        'label' => trim((string) ($matCat['nome'] ?? ('Matéria #' . $idCat))),
    ];
}

/**
 * Une candidatos do match com o catálogo completo (catálogo por último, sem duplicar id).
 *
 * @param list<array<string,mixed>> $candidatos
 * @param list<array<string,mixed>> $catalogo
 * @return list<array<string,mixed>>
 */
$mesclarOpcoesSelect = static function (array $candidatos, array $catalogo): array {
    $vistos = [];
    $out = [];
    foreach (array_merge($candidatos, $catalogo) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = (string) ($item['id'] ?? '');
        if ($id === '' || isset($vistos[$id])) {
            continue;
        }
        $vistos[$id] = true;
        $out[] = $item;
    }
    return $out;
};

$jobStatus = $job ? strtolower((string) ($job['status'] ?? '')) : '';
$jobEmAndamento = $job_id > 0 && in_array($jobStatus, ['pending', 'processing', 'running'], true);

include __DIR__ . '/../../../../../Views/admin/_partials/flash_message.php';

/**
 * @param array<string,mixed> $match
 */
$renderBadgeOk = static function (array $match, string $rotulo): string {
    $status = (string) ($match['status'] ?? '');
    if ($status === 'titulo') {
        return '<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-700">'
            . '<i class="fa-solid fa-heading" aria-hidden="true"></i> '
            . 'Título de seção'
            . '</span>';
    }
    if ($status !== 'ok') {
        return '';
    }
    $nome = trim((string) ($match['label'] ?? ''));
    if ($nome === '') {
        return '';
    }
    return '<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">'
        . '<i class="fa-solid fa-check" aria-hidden="true"></i> '
        . htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') . ': '
        . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8')
        . '</span>';
};

/**
 * @param list<array<string,mixed>> $candidatos
 */
$renderSelectOpcoes = static function (array $candidatos, string $campo): string {
    $html = '<option value="">Selecione…</option>';
    foreach ($candidatos as $cand) {
        if (!is_array($cand)) {
            continue;
        }
        $id = $cand['id'] ?? '';
        if ($id === '' || $id === null) {
            continue;
        }
        $label = trim((string) ($cand['label'] ?? $id));
        $valor = $campo === 'tipo_avaliacao_id' || $campo === 'materia_id'
            ? (string) (int) $id
            : htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8');
        $html .= '<option value="' . htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
};
?>

<?php
$page_header_title = 'Importar modelo de boletim (IA)';
$page_header_subtitle = 'Envie um print ou PDF do boletim. A IA lê colunas e o sistema tenta casar com peças e matérias cadastradas.';
$page_header_icon = 'fa-solid fa-wand-magic-sparkles';
$page_header_back_url = URL . '/admin/eventos-avaliativos/boletim';
$page_header_back_label = 'Voltar ao Boletim';
$trilhaItens = \App\Helpers\TrilhaAdmin::montar(
    '/admin/eventos-avaliativos/boletim',
    'Importar modelo',
    [
        ['rotulo' => 'Acadêmico', 'url' => URL . '/admin/academico'],
        ['rotulo' => 'Boletim', 'url' => URL . '/admin/eventos-avaliativos/boletim'],
        ['rotulo' => 'Importar modelo'],
    ]
);
include \App\Core\ViewPaths::adminPartial('page_header_form.php');
?>

<?php if (!$tudicoinsDisponivel): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm">
    <p class="font-semibold">Leitura por IA indisponível</p>
    <p class="mt-1">Ative <strong>TudiCoins</strong> para o módulo de leitura de modelo de boletim nesta escola no painel Master.</p>
</div>
<?php endif; ?>

<?php if ($jobEmAndamento): ?>
<div class="mb-6 bg-white rounded-xl shadow-sm border border-indigo-200 p-8 text-center" id="painel-job-ia" data-job-id="<?= (int) $job_id ?>">
    <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-indigo-50 text-indigo-600 mb-4" aria-hidden="true">
        <i class="fa-solid fa-spinner fa-spin text-2xl"></i>
    </div>
    <p class="text-base font-semibold text-gray-900">Lendo o modelo com IA…</p>
    <p class="text-sm text-gray-600 mt-2">Isso pode levar alguns segundos. A página atualiza quando terminar.</p>
    <p id="job-ia-erro" class="hidden mt-4 text-sm rounded-lg p-3 bg-red-100 text-red-800"></p>
</div>
<?php endif; ?>

<?php if (!$jobEmAndamento): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <h3 class="text-sm font-semibold text-gray-900 mb-1">Arquivo do modelo</h3>
    <p class="text-sm text-gray-500 mb-4">PDF ou imagem (JPG, PNG, WebP). Prefira boa legibilidade nas legendas e cabeçalhos das colunas.</p>
    <form method="POST"
          action="<?= URL ?>/admin/boletins/importar-modelo/analisar"
          enctype="multipart/form-data"
          id="form-analisar-modelo-ia"
          class="space-y-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2" for="arquivo-modelo-ia">Selecionar, arrastar ou colar</label>
            <div id="dropzone-modelo-ia"
                 tabindex="0"
                 role="button"
                 aria-controls="arquivo-modelo-ia"
                 class="rounded-xl border-2 border-dashed border-indigo-300 bg-indigo-50/40 px-4 py-6 text-center transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-300 <?= $tudicoinsDisponivel ? 'cursor-pointer' : 'opacity-50 cursor-not-allowed' ?>">
                <input type="file"
                       id="arquivo-modelo-ia"
                       name="arquivo"
                       accept=".pdf,application/pdf,image/jpeg,image/png,image/webp"
                       required
                       class="sr-only"
                       <?= $tudicoinsDisponivel ? '' : 'disabled' ?>>
                <div id="estado-vazio-modelo-ia">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-indigo-600" aria-hidden="true"></i>
                    <p class="mt-3 text-sm font-semibold text-gray-900">Clique, arraste ou cole um print aqui</p>
                    <p class="mt-1 text-xs text-gray-500">Ctrl/Cmd+V cola imagem copiada · PDF também pode ser escolhido ou arrastado</p>
                </div>
                <div id="preview-modelo-ia" class="hidden">
                    <img id="thumb-modelo-ia" src="" alt="Prévia do arquivo" class="mx-auto max-h-40 rounded-lg border border-gray-200 bg-white object-contain shadow-sm hidden">
                    <div class="mt-3 flex flex-wrap items-center justify-center gap-3 text-sm">
                        <span id="nome-modelo-ia" class="font-medium text-gray-700"></span>
                        <button type="button" id="btn-limpar-modelo-ia" class="text-red-600 hover:text-red-700">Remover</button>
                    </div>
                </div>
            </div>
            <p id="erro-arquivo-modelo-ia" class="hidden mt-2 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2"></p>
        </div>
        <div class="flex justify-end">
            <button type="submit"
                    class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed"
                    <?= $tudicoinsDisponivel ? '' : 'disabled' ?>>
                <i class="fa-solid fa-wand-magic-sparkles mr-2" aria-hidden="true"></i>
                Ler modelo com IA
                <span class="ml-2 text-xs font-normal opacity-90">(TudiCoins)</span>
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($rascunho !== null): ?>
<?php
$legendas = is_array($rascunho['legendas'] ?? null) ? $rascunho['legendas'] : [];
$colunas = is_array($rascunho['colunas'] ?? null) ? $rascunho['colunas'] : [];
$componentes = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
$nomeSugestao = trim((string) ($rascunho['nome_sugestao'] ?? ''));
$obsLayout = trim((string) ($rascunho['observacoes_layout'] ?? ''));
?>
<div class="space-y-6">
    <?php if ($nomeSugestao !== '' || $obsLayout !== ''): ?>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5 text-sm text-gray-700">
        <?php if ($nomeSugestao !== ''): ?>
        <p><span class="font-medium text-gray-900">Nome sugerido:</span> <?= htmlspecialchars($nomeSugestao, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($obsLayout !== ''): ?>
        <p class="mt-2 text-gray-600"><?= nl2br(htmlspecialchars($obsLayout, ENT_QUOTES, 'UTF-8')) ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="POST"
          action="<?= URL ?>/admin/boletins/importar-modelo/preparar"
          id="form-preparar-modelo-ia"
          class="space-y-6">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="atribuicoes" id="input-atribuicoes-json" value="{}">

        <?php if ($legendas !== []): ?>
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden w-full">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">1</span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Legendas</p>
                        <p class="text-sm text-gray-500 mt-0.5">Atribua uma vez por sigla (ex.: PP). Vale para todos os bimestres nas colunas.</p>
                    </div>
                </div>
            </div>
            <div class="p-5 space-y-3">
                <?php foreach ($legendas as $item): ?>
                <?php
                if (!is_array($item)) {
                    continue;
                }
                $sigla = trim((string) ($item['sigla'] ?? ''));
                $descricao = trim((string) ($item['descricao'] ?? ''));
                $pendente = !empty($item['pendente']);
                $matchPeca = is_array($item['match_peca'] ?? null) ? $item['match_peca'] : [];
                $matchTipo = is_array($item['match_tipo'] ?? null) ? $item['match_tipo'] : [];
                $cardClass = $pendente ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-white';
                ?>
                <div class="rounded-lg border p-4 <?= htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">
                                <?= $sigla !== '' ? htmlspecialchars($sigla, ENT_QUOTES, 'UTF-8') : '—' ?>
                                <?php if ($descricao !== ''): ?>
                                <span class="font-normal text-gray-600">— <?= htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <?= $renderBadgeOk($matchPeca, 'Peça') ?>
                            <?= $renderBadgeOk($matchTipo, 'Tipo') ?>
                        </div>
                    </div>
                    <?php if ($pendente): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php
                        $candPeca = $mesclarOpcoesSelect(
                            is_array($matchPeca['candidatos'] ?? null) ? $matchPeca['candidatos'] : [],
                            $opcoesPecasCatalogo
                        );
                        $candTipo = $mesclarOpcoesSelect(
                            is_array($matchTipo['candidatos'] ?? null) ? $matchTipo['candidatos'] : [],
                            $opcoesTiposCatalogo
                        );
                        ?>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Peça no sistema</label>
                            <select class="js-atribuicao-select js-pendente js-legenda-sigla w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm"
                                    data-chave="<?= htmlspecialchars($sigla, ENT_QUOTES, 'UTF-8') ?>"
                                    data-sigla="<?= htmlspecialchars($sigla, ENT_QUOTES, 'UTF-8') ?>"
                                    data-grupo="legendas"
                                    data-campo="peca_key">
                                <?= $renderSelectOpcoes($candPeca, 'peca_key') ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tipo de avaliação (alternativa)</label>
                            <select class="js-atribuicao-select js-pendente js-legenda-sigla w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm"
                                    data-chave="<?= htmlspecialchars($sigla, ENT_QUOTES, 'UTF-8') ?>"
                                    data-sigla="<?= htmlspecialchars($sigla, ENT_QUOTES, 'UTF-8') ?>"
                                    data-grupo="legendas"
                                    data-campo="tipo_avaliacao_id">
                                <?= $renderSelectOpcoes($candTipo, 'tipo_avaliacao_id') ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($colunas !== []): ?>
        <?php
        $colunasAgrupadas = [];
        foreach ($colunas as $item) {
            if (!is_array($item)) {
                continue;
            }
            $chave = trim((string) ($item['chave'] ?? ''));
            $rotulo = trim((string) ($item['rotulo'] ?? ''));
            $codigo = $rotulo !== '' ? $rotulo : $chave;
            if ($codigo === '') {
                $codigo = 'coluna_' . count($colunasAgrupadas);
            }
            $codigoKey = mb_strtoupper($codigo);
            if (!isset($colunasAgrupadas[$codigoKey])) {
                $colunasAgrupadas[$codigoKey] = [
                    'codigo' => $codigo,
                    'chave_ref' => $chave !== '' ? $chave : $codigo,
                    'itens' => [],
                    'periodos' => [],
                    'pendente' => false,
                    'match_peca' => is_array($item['match_peca'] ?? null) ? $item['match_peca'] : [],
                    'match_tipo' => is_array($item['match_tipo'] ?? null) ? $item['match_tipo'] : [],
                    'tipo_sugerido' => strtolower(trim((string) ($item['tipo_sugerido'] ?? ''))),
                ];
            }
            $colunasAgrupadas[$codigoKey]['itens'][] = $item;
            $grupo = trim((string) ($item['grupo'] ?? ''));
            if ($grupo !== '' && !in_array($grupo, $colunasAgrupadas[$codigoKey]['periodos'], true)) {
                $colunasAgrupadas[$codigoKey]['periodos'][] = $grupo;
            }
            if (!empty($item['pendente'])) {
                $colunasAgrupadas[$codigoKey]['pendente'] = true;
            }
            $mp = is_array($item['match_peca'] ?? null) ? $item['match_peca'] : [];
            $mt = is_array($item['match_tipo'] ?? null) ? $item['match_tipo'] : [];
            if (($mp['status'] ?? '') === 'ok') {
                $colunasAgrupadas[$codigoKey]['match_peca'] = $mp;
            }
            if (($mt['status'] ?? '') === 'ok') {
                $colunasAgrupadas[$codigoKey]['match_tipo'] = $mt;
            }
        }
        ?>
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden w-full">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">2</span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Colunas</p>
                        <p class="text-sm text-gray-500 mt-0.5">Uma atribuição por sigla vale para todos os bimestres.</p>
                    </div>
                </div>
            </div>
            <div class="p-5 space-y-3">
                <?php foreach ($colunasAgrupadas as $grupoCol): ?>
                <?php
                $codigo = (string) ($grupoCol['codigo'] ?? '');
                $chaveRef = (string) ($grupoCol['chave_ref'] ?? $codigo);
                $periodos = is_array($grupoCol['periodos'] ?? null) ? $grupoCol['periodos'] : [];
                $pendente = !empty($grupoCol['pendente']);
                $matchPeca = is_array($grupoCol['match_peca'] ?? null) ? $grupoCol['match_peca'] : [];
                $matchTipo = is_array($grupoCol['match_tipo'] ?? null) ? $grupoCol['match_tipo'] : [];
                $tipoSug = (string) ($grupoCol['tipo_sugerido'] ?? '');
                $qtd = count($grupoCol['itens'] ?? []);
                $cardClass = $pendente ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-white';
                $rotuloPeriodos = $periodos !== []
                    ? implode(', ', $periodos)
                    : ($qtd > 1 ? $qtd . ' ocorrências' : '1 ocorrência');
                ?>
                <div class="rounded-lg border p-4 <?= htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">
                                <?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <?= htmlspecialchars($rotuloPeriodos, ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($tipoSug !== ''): ?> · <?= htmlspecialchars($tipoSug, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <?= $renderBadgeOk($matchPeca, 'Peça') ?>
                            <?= $renderBadgeOk($matchTipo, 'Tipo') ?>
                        </div>
                    </div>
                    <?php if ($pendente): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php
                        $candPeca = $mesclarOpcoesSelect(
                            is_array($matchPeca['candidatos'] ?? null) ? $matchPeca['candidatos'] : [],
                            $opcoesPecasCatalogo
                        );
                        $candTipo = $mesclarOpcoesSelect(
                            is_array($matchTipo['candidatos'] ?? null) ? $matchTipo['candidatos'] : [],
                            $opcoesTiposCatalogo
                        );
                        ?>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Peça</label>
                            <select class="js-atribuicao-select js-pendente js-coluna-por-sigla w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm"
                                    data-chave="<?= htmlspecialchars($chaveRef, ENT_QUOTES, 'UTF-8') ?>"
                                    data-sigla="<?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?>"
                                    data-grupo="colunas"
                                    data-campo="peca_key">
                                <?= $renderSelectOpcoes($candPeca, 'peca_key') ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tipo de avaliação</label>
                            <select class="js-atribuicao-select js-pendente js-coluna-por-sigla w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm"
                                    data-chave="<?= htmlspecialchars($chaveRef, ENT_QUOTES, 'UTF-8') ?>"
                                    data-sigla="<?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?>"
                                    data-grupo="colunas"
                                    data-campo="tipo_avaliacao_id">
                                <?= $renderSelectOpcoes($candTipo, 'tipo_avaliacao_id') ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($componentes !== []): ?>
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden w-full">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">3</span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Componentes curriculares</p>
                        <p class="text-sm text-gray-500 mt-0.5">Disciplinas listadas no boletim.</p>
                    </div>
                </div>
            </div>
            <div class="p-5 space-y-3">
                <?php foreach ($componentes as $item): ?>
                <?php
                if (!is_array($item)) {
                    continue;
                }
                $nome = trim((string) ($item['nome'] ?? ''));
                $pendente = !empty($item['pendente']);
                $matchMateria = is_array($item['match_materia'] ?? null) ? $item['match_materia'] : [];
                $ehTituloSecao = !empty($item['eh_titulo_secao']) || (($matchMateria['status'] ?? '') === 'titulo');
                $cardClass = $pendente ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-white';
                ?>
                <div class="rounded-lg border p-4 <?= htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php if ($ehTituloSecao): ?>
                            <p class="text-xs text-gray-500 mt-0.5">Cabeçalho de área no print — não precisa vincular como linha de nota.</p>
                            <?php endif; ?>
                        </div>
                        <?= $renderBadgeOk($matchMateria, 'Matéria') ?>
                    </div>
                    <?php if ($pendente): ?>
                    <?php
                    $candMat = $mesclarOpcoesSelect(
                        is_array($matchMateria['candidatos'] ?? null) ? $matchMateria['candidatos'] : [],
                        $opcoesMateriasCatalogo
                    );
                    ?>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Matéria no sistema</label>
                        <select class="js-atribuicao-select js-pendente w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm"
                                data-chave="<?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>"
                                data-grupo="componentes"
                                data-campo="materia_id">
                            <?= $renderSelectOpcoes($candMat, 'materia_id') ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="flex justify-end">
            <button type="submit"
                    id="btn-preparar-assistente"
                    disabled
                    class="btn-primary-custom inline-flex items-center px-5 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fa-solid fa-arrow-right mr-2" aria-hidden="true"></i>
                Levar ao Assistente
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php include \App\Core\ViewPaths::component('ai-job-poller.php'); ?>
<script>
(function () {
    var dropzone = document.getElementById('dropzone-modelo-ia');
    var inputArquivo = document.getElementById('arquivo-modelo-ia');
    var estadoVazio = document.getElementById('estado-vazio-modelo-ia');
    var preview = document.getElementById('preview-modelo-ia');
    var thumb = document.getElementById('thumb-modelo-ia');
    var nomeArquivo = document.getElementById('nome-modelo-ia');
    var btnLimpar = document.getElementById('btn-limpar-modelo-ia');
    var erroArquivo = document.getElementById('erro-arquivo-modelo-ia');
    var formAnalisar = document.getElementById('form-analisar-modelo-ia');

    function mostrarErroArquivo(msg) {
        if (!erroArquivo) return;
        if (!msg) {
            erroArquivo.textContent = '';
            erroArquivo.classList.add('hidden');
            return;
        }
        erroArquivo.textContent = msg;
        erroArquivo.classList.remove('hidden');
    }

    function extensaoOk(file) {
        if (!file) return false;
        var mime = String(file.type || '').toLowerCase();
        if (mime === 'application/pdf' || mime.indexOf('image/jpeg') === 0 || mime === 'image/png' || mime === 'image/webp') {
            return true;
        }
        var n = String(file.name || '').toLowerCase();
        return /\.(pdf|jpe?g|png|webp)$/.test(n);
    }

    function limparArquivoModelo() {
        if (!inputArquivo) return;
        inputArquivo.value = '';
        if (thumb) {
            thumb.src = '';
            thumb.classList.add('hidden');
        }
        if (nomeArquivo) nomeArquivo.textContent = '';
        if (preview) preview.classList.add('hidden');
        if (estadoVazio) estadoVazio.classList.remove('hidden');
        mostrarErroArquivo('');
    }

    function aplicarArquivoModelo(file) {
        if (!inputArquivo || inputArquivo.disabled) return;
        if (!extensaoOk(file)) {
            mostrarErroArquivo('Use PDF ou imagem (JPG, PNG, WebP).');
            return;
        }
        var dt = new DataTransfer();
        dt.items.add(file);
        inputArquivo.files = dt.files;
        if (nomeArquivo) nomeArquivo.textContent = file.name || 'imagem-colada.png';
        if (estadoVazio) estadoVazio.classList.add('hidden');
        if (preview) preview.classList.remove('hidden');
        mostrarErroArquivo('');
        if (thumb) {
            if (String(file.type || '').indexOf('image/') === 0) {
                var reader = new FileReader();
                reader.onload = function (ev) {
                    thumb.src = String(ev.target && ev.target.result ? ev.target.result : '');
                    thumb.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            } else {
                thumb.src = '';
                thumb.classList.add('hidden');
            }
        }
    }

    if (dropzone && inputArquivo && !inputArquivo.disabled) {
        dropzone.addEventListener('click', function (ev) {
            if (ev.target === btnLimpar) return;
            inputArquivo.click();
        });
        dropzone.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                inputArquivo.click();
            }
        });
        ['dragenter', 'dragover'].forEach(function (nome) {
            dropzone.addEventListener(nome, function (ev) {
                ev.preventDefault();
                dropzone.classList.add('border-indigo-500', 'bg-indigo-50');
            });
        });
        ['dragleave', 'drop'].forEach(function (nome) {
            dropzone.addEventListener(nome, function (ev) {
                ev.preventDefault();
                dropzone.classList.remove('border-indigo-500', 'bg-indigo-50');
            });
        });
        dropzone.addEventListener('drop', function (ev) {
            var file = ev.dataTransfer && ev.dataTransfer.files && ev.dataTransfer.files[0];
            if (file) aplicarArquivoModelo(file);
        });
        inputArquivo.addEventListener('change', function () {
            if (inputArquivo.files && inputArquivo.files[0]) {
                aplicarArquivoModelo(inputArquivo.files[0]);
            }
        });
        if (btnLimpar) {
            btnLimpar.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                limparArquivoModelo();
            });
        }
        document.addEventListener('paste', function (ev) {
            if (!formAnalisar || formAnalisar.classList.contains('hidden')) return;
            var alvo = ev.target;
            if (alvo && (alvo.tagName === 'INPUT' || alvo.tagName === 'TEXTAREA' || alvo.isContentEditable)) {
                return;
            }
            var items = ev.clipboardData && ev.clipboardData.items ? Array.from(ev.clipboardData.items) : [];
            for (var i = 0; i < items.length; i++) {
                if (String(items[i].type || '').indexOf('image/') === 0) {
                    var file = items[i].getAsFile();
                    if (file) {
                        var mime = file.type || 'image/png';
                        var ext = mime.indexOf('jpeg') >= 0 ? 'jpg' : (mime.indexOf('webp') >= 0 ? 'webp' : 'png');
                        aplicarArquivoModelo(new File([file], 'print-boletim-colado.' + ext, { type: mime }));
                        ev.preventDefault();
                        break;
                    }
                }
            }
        });
    }

    if (formAnalisar) {
        formAnalisar.addEventListener('submit', function (ev) {
            if (!inputArquivo || !inputArquivo.files || !inputArquivo.files[0]) {
                ev.preventDefault();
                mostrarErroArquivo('Selecione, arraste ou cole um PDF/imagem do boletim.');
            }
        });
    }

    var formPreparar = document.getElementById('form-preparar-modelo-ia');
    var btnPreparar = document.getElementById('btn-preparar-assistente');
    var inputJson = document.getElementById('input-atribuicoes-json');

    function chaveGrupoResolvida(grupo, chave, selectsNoGrupo) {
        var comValor = selectsNoGrupo.filter(function (s) { return s.value && String(s.value).trim() !== ''; });
        if (comValor.length > 0) {
            return true;
        }
        var pendentes = selectsNoGrupo.filter(function (s) { return s.classList.contains('js-pendente'); });
        return pendentes.length === 0;
    }

    function atualizarBotaoPreparar() {
        if (!btnPreparar) {
            return;
        }
        var pendentes = document.querySelectorAll('.js-atribuicao-select.js-pendente');
        if (pendentes.length === 0) {
            btnPreparar.disabled = false;
            return;
        }
        var gruposChaves = {};
        pendentes.forEach(function (sel) {
            var g = sel.getAttribute('data-grupo') || '';
            var c = sel.getAttribute('data-chave') || '';
            var k = g + '\0' + c;
            if (!gruposChaves[k]) {
                gruposChaves[k] = { grupo: g, chave: c, selects: [] };
            }
            gruposChaves[k].selects.push(sel);
        });
        var todosOk = true;
        Object.keys(gruposChaves).forEach(function (k) {
            var item = gruposChaves[k];
            if (!chaveGrupoResolvida(item.grupo, item.chave, item.selects)) {
                todosOk = false;
            }
        });
        btnPreparar.disabled = !todosOk;
    }

    function montarAtribuicoes() {
        var out = { legendas: {}, colunas: {}, componentes: {} };
        document.querySelectorAll('.js-atribuicao-select').forEach(function (sel) {
            var val = String(sel.value || '').trim();
            if (val === '') {
                return;
            }
            var grupo = sel.getAttribute('data-grupo') || '';
            var chave = sel.getAttribute('data-chave') || '';
            var campo = sel.getAttribute('data-campo') || '';
            if (!out[grupo]) {
                out[grupo] = {};
            }
            if (!out[grupo][chave]) {
                out[grupo][chave] = {};
            }
            if (campo === 'tipo_avaliacao_id' || campo === 'materia_id') {
                out[grupo][chave][campo] = parseInt(val, 10) || 0;
            } else {
                out[grupo][chave][campo] = val;
            }
        });
        return out;
    }

    if (formPreparar && inputJson) {
        formPreparar.addEventListener('submit', function () {
            inputJson.value = JSON.stringify(montarAtribuicoes());
        });
        function normalizarSigla(v) {
            return String(v || '').trim().toUpperCase();
        }

        function sincronizarLegendaParaColunas(selLegenda) {
            var sigla = normalizarSigla(selLegenda.getAttribute('data-sigla') || selLegenda.getAttribute('data-chave'));
            var campo = selLegenda.getAttribute('data-campo') || '';
            if (!sigla || !campo) return;
            var valor = String(selLegenda.value || '');
            document.querySelectorAll('.js-coluna-por-sigla').forEach(function (selCol) {
                if (normalizarSigla(selCol.getAttribute('data-sigla') || selCol.getAttribute('data-chave')) !== sigla) {
                    return;
                }
                if ((selCol.getAttribute('data-campo') || '') !== campo) {
                    return;
                }
                if (valor === '') return;
                var existe = false;
                Array.prototype.forEach.call(selCol.options || [], function (opt) {
                    if (String(opt.value) === valor) existe = true;
                });
                if (existe) {
                    selCol.value = valor;
                }
            });
        }

        document.querySelectorAll('.js-atribuicao-select').forEach(function (sel) {
            sel.addEventListener('change', function () {
                if (sel.classList.contains('js-legenda-sigla')) {
                    sincronizarLegendaParaColunas(sel);
                }
                atualizarBotaoPreparar();
            });
        });
        atualizarBotaoPreparar();
    }

    var painelJob = document.getElementById('painel-job-ia');
    if (painelJob && typeof window.AIJobPoller === 'function') {
        var jobId = parseInt(painelJob.getAttribute('data-job-id') || '0', 10);
        var erroEl = document.getElementById('job-ia-erro');
        if (jobId > 0) {
            window.AIJobPoller(jobId, {
                interval: 2000,
                statusUrl: <?= json_encode(rtrim((string) URL, '/') . '/admin/ai-job/{id}/status') ?>,
                onDone: function () {
                    var base = <?= json_encode(URL . '/admin/boletins/importar-modelo?job_id=') ?>;
                    window.location.href = base + encodeURIComponent(String(jobId));
                },
                onFailed: function (msg) {
                    if (erroEl) {
                        erroEl.textContent = msg || 'Falha ao processar o modelo.';
                        erroEl.classList.remove('hidden');
                    } else {
                        window.location.href = <?= json_encode(URL . '/admin/boletins/importar-modelo') ?>;
                    }
                }
            });
        }
    }
})();
</script>
