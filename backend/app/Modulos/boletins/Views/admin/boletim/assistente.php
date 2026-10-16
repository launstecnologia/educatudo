<?php
$csrfToken = (string) ($csrf_token ?? '');
$selectedRegraId = (int) ($selected_regra_id ?? 0);
$boletimId = (int) ($boletim_id ?? 0);
$voltarBoletins = !empty($voltar_boletins);
$voltarGerarAvaliacoes = !empty($voltar_gerar_avaliacoes);
$boletimAssistenteDisponivel = !empty($boletim_assistente_disponivel);
$boletimAssistentePageMode = true;
$retornoInformado = trim((string) ($boletim_assistente_return_url ?? ''));
$boletimAssistenteReturnUrl = $retornoInformado !== ''
    ? (preg_match('#^https?://#i', $retornoInformado) ? $retornoInformado : (URL . $retornoInformado))
    : ($voltarBoletins
        ? URL . '/admin/boletins'
        : (URL . '/admin/boletim-configuracao' . ($selectedRegraId > 0 ? '?regra_id=' . $selectedRegraId : '?novo=1')));
$continuarRascunhoAssistente = !empty($continuar_rascunho);
$podeVerQuadro = !empty($pode_ver_quadro);
$podeCadastrarQuadro = !empty($pode_cadastrar_quadro);
$anoAssistente = (int) ($ano_letivo_assistente ?? 0);
$bimestreAssistente = (int) ($bimestre_assistente ?? 0);

$estadoCabecalho = is_array($boletim_assistente_estado_inicial ?? null) ? $boletim_assistente_estado_inicial : [];
$nomeEventoCabecalho = trim((string) ($estadoCabecalho['nome'] ?? ''));
$refEventoCabecalho = $selectedRegraId > 0 ? $selectedRegraId : (int) ($estadoCabecalho['regra_id'] ?? 0);
$page_header_back_url = $boletimAssistenteReturnUrl;
$page_header_title = $nomeEventoCabecalho !== '' ? $nomeEventoCabecalho : 'Evento de Notas';
$page_header_title_id = 'evento-notas-titulo';
$page_header_subtitle = $refEventoCabecalho > 0 ? ('Ref ' . $refEventoCabecalho) : 'Novo evento, ainda sem ref';
$page_header_subtitle_id = 'evento-notas-ref';
include \App\Core\ViewPaths::adminPartial('page_header_form.php');
include \App\Core\ViewPaths::adminPartial('flash_message.php');
?>

<div class="space-y-6">
    <?php if ($voltarGerarAvaliacoes): ?>
    <div class="p-4 rounded-lg bg-white border border-gray-200 text-sm text-gray-700">
        Salvar sem gerar notas grava a fórmula deste período e não publica as notas dos alunos.
        <a href="<?= htmlspecialchars($boletimAssistenteReturnUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-indigo-700 underline">Voltar e configurar os demais períodos</a>.
    </div>
    <?php elseif ($voltarBoletins): ?>
    <div class="p-4 rounded-lg bg-white border border-gray-200 text-sm text-gray-700">
        Confira o modelo, o ano e o período antes de salvar. Abrir esta tela não grava o período.
        Salvar sem gerar notas grava a configuração e não publica as notas dos alunos.
        <a href="<?= htmlspecialchars($boletimAssistenteReturnUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-indigo-700 underline">Voltar ao modelo</a>.
    </div>
    <?php endif; ?>
    <?php include __DIR__ . '/_assistente_wizard.php'; ?>
</div>
