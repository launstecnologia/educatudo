<?php
$csrfToken = (string) ($csrf_token ?? '');
$selectedRegraId = (int) ($selected_regra_id ?? 0);
$boletimId = (int) ($boletim_id ?? 0);
$voltarBoletins = !empty($voltar_boletins);
$voltarBoletimLayout = !empty($voltar_boletim_layout);
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
$contextoEvento = ($nomeModeloContexto !== '' ? $nomeModeloContexto : 'Sem modelo')
    . ' · ' . ($anoContexto > 0 ? (string) $anoContexto : 'Ano não informado')
    . ' · ' . ($rotuloPeriodoContexto !== '' ? $rotuloPeriodoContexto : 'Período não informado');
if ($refEventoCabecalho > 0) {
    $contextoEvento .= ' · Ref ' . $refEventoCabecalho;
}
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
$page_header_back_url = $boletimAssistenteReturnUrl;
$page_header_back_label = $rotuloRetornoAssistente;
$page_header_title = $nomeEventoCabecalho !== '' ? $nomeEventoCabecalho : 'Evento de Notas';
$page_header_title_id = 'evento-notas-titulo';
$page_header_subtitle = $contextoEvento;
$page_header_subtitle_id = 'evento-notas-ref';
?>
<nav aria-label="Caminho" class="mb-3 text-sm text-gray-500 flex flex-wrap items-center gap-1">
    <?php if ($voltarBoletimLayout): ?>
    <a href="<?= URL ?>/admin/eventos-avaliativos/boletim?ano=<?= (int) $anoContexto ?>" class="hover:text-gray-800">Boletim</a>
    <?php else: ?>
    <a href="<?= URL ?>/admin/boletins" class="hover:text-gray-800">Boletins</a>
    <?php endif; ?>
    <span aria-hidden="true">/</span>
    <?php if ($voltarGerarAvaliacoes): ?>
    <a href="<?= htmlspecialchars($boletimAssistenteReturnUrl, ENT_QUOTES, 'UTF-8') ?>" class="hover:text-gray-800">Adicionar períodos</a>
    <?php elseif ($voltarBoletimLayout): ?>
    <a href="<?= htmlspecialchars($boletimAssistenteReturnUrl, ENT_QUOTES, 'UTF-8') ?>" class="hover:text-gray-800">Layout</a>
    <?php elseif ($voltarBoletins): ?>
    <a href="<?= htmlspecialchars($boletimAssistenteReturnUrl, ENT_QUOTES, 'UTF-8') ?>" class="hover:text-gray-800">Modelos</a>
    <?php else: ?>
    <a href="<?= htmlspecialchars($urlListaPeriodos, ENT_QUOTES, 'UTF-8') ?>" class="hover:text-gray-800">Períodos e fórmulas</a>
    <?php if ($voltarEvento || (!$voltarPeriodos && $selectedRegraId > 0)): ?>
    <span aria-hidden="true">/</span>
    <a href="<?= htmlspecialchars($boletimAssistenteReturnUrl, ENT_QUOTES, 'UTF-8') ?>" class="hover:text-gray-800">Evento</a>
    <?php endif; ?>
    <?php endif; ?>
    <span aria-hidden="true">/</span>
    <span class="text-gray-900">Configurar notas</span>
</nav>
<?php
include \App\Core\ViewPaths::adminPartial('page_header_form.php');
include \App\Core\ViewPaths::adminPartial('flash_message.php');
?>

<div class="space-y-6">
    <?php if ($voltarGerarAvaliacoes): ?>
    <div class="p-4 rounded-lg bg-white border border-gray-200 text-sm text-gray-700">
        Salvar sem gerar notas grava a fórmula deste período e não publica as notas dos alunos.
        <a href="<?= htmlspecialchars($boletimAssistenteReturnUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-indigo-700 underline"><?= htmlspecialchars($rotuloRetornoAssistente, ENT_QUOTES, 'UTF-8') ?></a>.
    </div>
    <?php elseif ($voltarBoletins || $voltarPeriodos || $voltarEvento): ?>
    <div class="p-4 rounded-lg bg-white border border-gray-200 text-sm text-gray-700">
        Confira o modelo, o ano e o período antes de salvar. Abrir esta tela não grava o período.
        Salvar sem gerar notas grava a configuração e não publica as notas dos alunos.
        <a href="<?= htmlspecialchars($boletimAssistenteReturnUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-indigo-700 underline"><?= htmlspecialchars($rotuloRetornoAssistente, ENT_QUOTES, 'UTF-8') ?></a>.
    </div>
    <?php endif; ?>
    <?php include __DIR__ . '/_assistente_wizard.php'; ?>
</div>
