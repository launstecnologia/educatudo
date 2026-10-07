<?php
/** Timbre compartilhado dos PDFs do fechamento. Define $timbre_parte: css, cabecalho ou rodape. */
$vars = is_array($vars ?? null) ? $vars : [];
$parte = (string) ($timbre_parte ?? 'css');
$escTimbre = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if ($parte === 'css'):
?>
<style>
    @page { margin: 24mm 10mm 16mm 10mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
    .cabecalho-fixo { position: fixed; top: -18mm; left: 0; right: 0; height: 16mm; }
    .cabecalho-fixo table { width: 100%; border-collapse: collapse; border: none; }
    .cabecalho-fixo td { border: none; vertical-align: middle; padding: 0; }
    .cabecalho-fixo img { max-height: 46px; max-width: 140px; }
    .cabecalho-fixo .escola { font-size: 13px; font-weight: bold; text-align: center; }
    .cabecalho-fixo .linha { font-size: 8px; color: #444; text-align: center; }
    .rodape-fixo { position: fixed; bottom: -12mm; left: 0; right: 0; border-top: 1px solid #ccc; padding-top: 3px; font-size: 8px; color: #444; text-align: center; }
    h2 { font-size: 14px; margin: 0 0 6px; text-align: center; }
    p { margin: 0 0 6px; }
    table.dados, table.grade, table.assin { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.dados td, table.grade th, table.grade td { border: 1px solid #ccc; padding: 3px 4px; }
    table.dados td.label, table.grade th { background: #f3f4f6; font-size: 8px; }
    .muted, .lead { color: #444; font-size: 10px; text-align: center; }
    .capa { page-break-after: always; }
    .quebra { page-break-before: always; }
    table.assin td { border: none; text-align: center; padding-top: 28px; width: 50%; }
    table.assin span { display: block; border-top: 1px solid #333; margin: 0 28px; padding-top: 4px; font-size: 9px; }
    table.grade { font-size: 7px; }
    table.grade th.disc { font-size: 6.5px; max-width: 42px; }
    table.grade td.aluno, table.grade th.aluno { text-align: left; min-width: 90px; }
    table.grade td { text-align: center; }
    .totais { margin-top: 10px; text-align: center; font-size: 10px; }
</style>
<?php elseif ($parte === 'cabecalho'): ?>
<div class="cabecalho-fixo">
    <table>
        <tr>
            <td style="width:150px;"><?= $vars['logo_html'] ?? '' ?></td>
            <td>
                <div class="escola"><?= $vars['escola_nome'] ?? 'Escola' ?></div>
                <?php if (!empty($vars['escola_endereco'])): ?><div class="linha"><?= $vars['escola_endereco'] ?></div><?php endif; ?>
                <?php if (!empty($vars['escola_docs'])): ?><div class="linha"><?= $vars['escola_docs'] ?></div><?php endif; ?>
            </td>
            <td style="width:150px;"></td>
        </tr>
    </table>
</div>
<?php else: ?>
<div class="rodape-fixo">
    <?= $escTimbre(trim(implode(' · ', array_filter([
        html_entity_decode((string) ($vars['escola_nome'] ?? ''), ENT_QUOTES, 'UTF-8'),
        !empty($vars['escola_telefone']) && $vars['escola_telefone'] !== '—' ? 'Tel. ' . html_entity_decode((string) $vars['escola_telefone'], ENT_QUOTES, 'UTF-8') : '',
        'nº ' . (string) ($vars['numero'] ?? '') . '/' . (string) ($vars['ano'] ?? ''),
        (string) ($vars['data_hoje'] ?? ''),
    ])))) ?>
</div>
<?php endif; ?>
