<?php
$csrfToken = (string) ($csrf_token ?? '');
$selectedRegraId = (int) ($selected_regra_id ?? 0);
$boletimAssistenteDisponivel = !empty($boletim_assistente_disponivel);
$boletimAssistentePageMode = true;
$boletimAssistenteReturnUrl = URL . '/admin/boletim-configuracao' . ($selectedRegraId > 0 ? '?regra_id=' . $selectedRegraId : '?novo=1');

$page_header_back_url = $boletimAssistenteReturnUrl;
$page_header_title = 'Assistente do Boletim';
$page_header_subtitle = 'Página própria com etapas para montar, revisar e aplicar a configuração do boletim';
include __DIR__ . '/../_partials/page_header_form.php';
?>

<div class="space-y-6">
    <?php include __DIR__ . '/_assistente_wizard.php'; ?>
</div>
