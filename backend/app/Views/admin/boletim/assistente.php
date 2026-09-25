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

$page_header_back_url = $boletimAssistenteReturnUrl;
$page_header_title = 'Evento de Notas';
$page_header_subtitle = 'Monte o cálculo do bimestre por etapas. Provas, eventos e jornadas entram pelo bimestre da peça.';
include __DIR__ . '/../_partials/page_header_form.php';
?>

<div class="space-y-6">
    <?php include __DIR__ . '/_assistente_wizard.php'; ?>
</div>
<?php include __DIR__ . '/_assistente_consulta.php'; ?>
