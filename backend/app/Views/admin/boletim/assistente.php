<?php
$csrfToken = (string) ($csrf_token ?? '');
$selectedRegraId = (int) ($selected_regra_id ?? 0);
$boletimId = (int) ($boletim_id ?? 0);
$voltarBoletins = !empty($voltar_boletins);
$boletimAssistenteDisponivel = !empty($boletim_assistente_disponivel);
$boletimAssistentePageMode = true;
$boletimAssistenteReturnUrl = $voltarBoletins
    ? URL . '/admin/boletins'
    : (URL . '/admin/boletim-configuracao' . ($selectedRegraId > 0 ? '?regra_id=' . $selectedRegraId : '?novo=1'));

$estadoCabecalho = is_array($boletim_assistente_estado_inicial ?? null) ? $boletim_assistente_estado_inicial : [];
$nomeEventoCabecalho = trim((string) ($estadoCabecalho['nome'] ?? ''));
$refEventoCabecalho = $selectedRegraId > 0 ? $selectedRegraId : (int) ($estadoCabecalho['regra_id'] ?? 0);
$page_header_back_url = $boletimAssistenteReturnUrl;
$page_header_title = $nomeEventoCabecalho !== '' ? $nomeEventoCabecalho : 'Evento de Notas';
$page_header_title_id = 'evento-notas-titulo';
$page_header_subtitle = $refEventoCabecalho > 0 ? ('Ref ' . $refEventoCabecalho) : 'Novo evento, ainda sem ref';
$page_header_subtitle_id = 'evento-notas-ref';
include __DIR__ . '/../_partials/page_header_form.php';
?>

<div class="space-y-6">
    <?php include __DIR__ . '/_assistente_wizard.php'; ?>
</div>
