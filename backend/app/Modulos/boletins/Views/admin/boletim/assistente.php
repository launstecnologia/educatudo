<?php
$csrfToken = (string) ($csrf_token ?? '');
$selectedRegraId = (int) ($selected_regra_id ?? 0);
$boletimId = (int) ($boletim_id ?? 0);
$voltarBoletins = !empty($voltar_boletins);
$voltarBoletimLayout = !empty($voltar_boletim_layout);
$quadroSantaMaria = !empty($quadro_santa_maria);
$quadroComposto = !empty($quadro_composto) || $quadroSantaMaria;
$quadroImpressao = !empty($quadro_impressao);
$voltarGerarAvaliacoes = !empty($voltar_gerar_avaliacoes);
$boletimAssistenteDisponivel = !empty($boletim_assistente_disponivel);
$boletimAssistentePageMode = true;
$retornoInformado = trim((string) ($boletim_assistente_return_url ?? ''));
$boletimAssistenteReturnUrl = $retornoInformado !== ''
    ? (preg_match('#^https?://#i', $retornoInformado) ? $retornoInformado : (URL . $retornoInformado))
    : ($voltarBoletins
        ? URL . '/admin/boletins'
        : (URL . '/admin/boletim-configuracao' . ($selectedRegraId > 0 ? '?regra_id=' . $selectedRegraId : '?novo=1')));
$rotuloRetornoAssistente = trim((string) ($boletim_assistente_return_label ?? ''));
if ($rotuloRetornoAssistente === '') {
    $rotuloRetornoAssistente = $voltarBoletins ? 'Voltar ao modelo' : ($selectedRegraId > 0 ? 'Voltar ao evento' : 'Voltar aos períodos');
}
$voltarPeriodos = !empty($voltar_periodos);
$voltarEvento = !empty($voltar_evento);
$navegacaoLista = is_array($navegacao_lista ?? null) ? $navegacao_lista : [];
$continuarRascunhoAssistente = !empty($continuar_rascunho);
$podeVerQuadro = !empty($pode_ver_quadro);
$podeCadastrarQuadro = !empty($pode_cadastrar_quadro);
$anoAssistente = (int) ($ano_letivo_assistente ?? 0);
$bimestreAssistente = (int) ($bimestre_assistente ?? 0);

$estadoCabecalho = is_array($boletim_assistente_estado_inicial ?? null) ? $boletim_assistente_estado_inicial : [];
$nomeEventoCabecalho = trim((string) ($estadoCabecalho['nome'] ?? ''));
$refEventoCabecalho = $selectedRegraId > 0 ? $selectedRegraId : (int) ($estadoCabecalho['regra_id'] ?? 0);
$anoContexto = (int) ($estadoCabecalho['ano_letivo'] ?? $anoAssistente);
$bimestreContexto = (int) ($estadoCabecalho['bimestre'] ?? $bimestreAssistente);
$boletimContexto = (int) ($estadoCabecalho['boletim_id'] ?? $boletimId);
$nomeModeloContexto = '';
$catalogoCabecalho = is_array($boletim_assistente_catalogo_inicial ?? null) ? $boletim_assistente_catalogo_inicial : [];
foreach ((array) ($catalogoCabecalho['boletins'] ?? []) as $boletimCabecalho) {
    if ((int) ($boletimCabecalho['id'] ?? 0) !== $boletimContexto) {
        continue;
    }
    $nomeModeloContexto = trim((string) ($boletimCabecalho['nome'] ?? ''));
    break;
}
$rotuloPeriodoContexto = ($anoContexto > 0 && $bimestreContexto > 0)
    ? \App\Core\PeriodoLetivo::rotulo($anoContexto, $bimestreContexto)
    : '';

$page_header_title = $nomeEventoCabecalho !== ''
    ? $nomeEventoCabecalho
    : ($quadroSantaMaria ? 'Print Santa Maria' : ($quadroComposto ? 'Quadro do boletim' : ($quadroImpressao ? 'Quadro livre' : 'Evento de Notas')));

$subtituloPartes = [];
if ($nomeModeloContexto !== '') {
    $subtituloPartes[] = $nomeModeloContexto;
}
if ($anoContexto > 0) {
    $subtituloPartes[] = (string) $anoContexto;
}
if ($rotuloPeriodoContexto !== '') {
    $subtituloPartes[] = $rotuloPeriodoContexto;
}
if ($refEventoCabecalho > 0) {
    $subtituloPartes[] = 'Ref ' . $refEventoCabecalho;
}
$page_header_subtitle = $subtituloPartes !== []
    ? implode(' · ', $subtituloPartes)
    : ($quadroImpressao || $quadroComposto || $quadroSantaMaria
        ? 'Monte o quadro de impressão do boletim'
        : 'Configure o evento de notas');

$urlListaPeriodos = URL . '/admin/boletim';
if ($navegacaoLista !== []) {
    $filtrosVolta = [];
    $mapaVolta = [
        'lista_nome' => 'nome',
        'lista_ano' => 'ano_letivo',
        'lista_bimestre' => 'bimestre',
        'lista_serie' => 'serie_id',
        'lista_boletim' => 'boletim_id',
        'lista_ordem' => 'ordem',
        'lista_dir' => 'dir',
        'lista_page' => 'page',
        'lista_desabilitados' => 'desabilitados',
    ];
    foreach ($mapaVolta as $chaveLista => $chaveFiltro) {
        if (!isset($navegacaoLista[$chaveLista]) || (string) $navegacaoLista[$chaveLista] === '') {
            continue;
        }
        $filtrosVolta[$chaveFiltro] = $navegacaoLista[$chaveLista];
    }
    if ($filtrosVolta !== []) {
        $urlListaPeriodos .= '?' . http_build_query($filtrosVolta);
    }
}

$trilhaManual = [];
if ($voltarBoletimLayout || $quadroImpressao || $quadroComposto || $quadroSantaMaria) {
    $trilhaManual = [
        ['rotulo' => 'Acadêmico', 'url' => URL . '/admin/academico'],
        ['rotulo' => 'Boletim', 'url' => URL . '/admin/eventos-avaliativos/boletim' . ($anoContexto > 0 ? ('?ano=' . $anoContexto) : '')],
        ['rotulo' => 'Modelos de Boletim', 'url' => $boletimAssistenteReturnUrl],
        ['rotulo' => $quadroImpressao || $quadroComposto || $quadroSantaMaria ? 'Quadro de impressão' : 'Configurar'],
    ];
} elseif ($voltarBoletins) {
    $trilhaManual = [
        ['rotulo' => 'Acadêmico', 'url' => URL . '/admin/academico'],
        ['rotulo' => 'Demonstrativo de notas', 'url' => URL . '/admin/boletins'],
        ['rotulo' => 'Modelos', 'url' => $boletimAssistenteReturnUrl],
        ['rotulo' => 'Configurar notas'],
    ];
} elseif ($voltarGerarAvaliacoes) {
    $trilhaManual = [
        ['rotulo' => 'Acadêmico', 'url' => URL . '/admin/academico'],
        ['rotulo' => 'Demonstrativo de notas', 'url' => URL . '/admin/boletins'],
        ['rotulo' => 'Adicionar períodos', 'url' => $boletimAssistenteReturnUrl],
        ['rotulo' => 'Configurar notas'],
    ];
} else {
    $trilhaManual = [
        ['rotulo' => 'Acadêmico', 'url' => URL . '/admin/academico'],
        ['rotulo' => 'Boletins', 'url' => URL . '/admin/boletins'],
        ['rotulo' => 'Períodos e fórmulas', 'url' => $urlListaPeriodos],
        ['rotulo' => 'Configurar notas'],
    ];
}
$trilhaItens = \App\Helpers\TrilhaAdmin::montar(
    '/admin/boletim-configuracao/assistente',
    $page_header_title,
    $trilhaManual
);

$iconeCabecalho = ($quadroImpressao || $quadroComposto || $quadroSantaMaria || $voltarBoletimLayout)
    ? 'fa-file-circle-check'
    : 'fa-table-cells';
?>
<div class="mb-6">
    <?php include \App\Core\ViewPaths::layoutComponent('trilha.php'); ?>
    <div class="mt-4 flex justify-between items-center gap-4">
        <div class="flex items-center gap-3 min-w-0">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary text-white">
                <i class="fa-solid <?= htmlspecialchars($iconeCabecalho, ENT_QUOTES, 'UTF-8') ?> text-lg" aria-hidden="true"></i>
            </span>
            <div class="min-w-0">
                <h2 id="evento-notas-titulo" class="text-xl font-bold text-gray-900 truncate"><?= htmlspecialchars($page_header_title, ENT_QUOTES, 'UTF-8') ?></h2>
                <p id="evento-notas-ref" class="text-sm text-gray-500 mt-0.5 truncate"><?= htmlspecialchars($page_header_subtitle, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 flex-shrink-0 ml-auto">
            <a href="<?= htmlspecialchars($boletimAssistenteReturnUrl, ENT_QUOTES, 'UTF-8') ?>"
               class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-arrow-left mr-2 text-gray-500" aria-hidden="true"></i>
                <?= htmlspecialchars($rotuloRetornoAssistente, ENT_QUOTES, 'UTF-8') ?>
            </a>
        </div>
    </div>
</div>

<?php include \App\Core\ViewPaths::adminPartial('flash_message.php'); ?>

<div class="space-y-6">
    <?php include __DIR__ . '/_assistente_wizard.php'; ?>
</div>
