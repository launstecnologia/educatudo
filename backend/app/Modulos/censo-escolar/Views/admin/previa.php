<?php
$edicao = is_array($edicao ?? null) ? $edicao : null;
$previa = is_array($previa ?? null) ? $previa : [];
$eid = (int) ($edicao['id'] ?? 0);
$contagens = $previa['contagens'] ?? [];
$layout = $previa['layout'] ?? [];
$pode = $previa['pode_gerar'] ?? ['ok' => false, 'motivo' => ''];
$page_header_title = 'Prévia da exportação';
$page_header_subtitle = 'Contagens e bloqueios antes de gerar o arquivo da edição.';
include dirname(__DIR__, 4) . '/Views/admin/_partials/page_header_list.php';
include dirname(__DIR__, 4) . '/Views/admin/_partials/flash_message.php';
$nav_atual = 'exportacoes';
include __DIR__ . '/_nav.php';
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="bg-white rounded-xl border border-gray-200 p-6 mb-4 text-sm text-gray-700 space-y-1">
    <p>Leiaute: <strong><?= $esc($layout['versao'] ?? '—') ?></strong> · oficial: <strong><?= !empty($layout['oficial']) ? 'sim' : 'não' ?></strong></p>
    <p>Unidade: <strong><?= $esc($edicao['unidade_nome'] ?? 'Toda a escola') ?></strong></p>
    <p>Usuário: <strong><?= $esc($user['nome'] ?? '') ?></strong> · <?= date('d/m/Y H:i') ?></p>
</div>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
            <th class="px-4 py-3 text-left">Categoria</th><th class="px-4 py-3 text-left">Total</th>
            <th class="px-4 py-3 text-left">Prontos</th><th class="px-4 py-3 text-left">Erros</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($contagens as $k => $n): ?>
            <tr>
                <td class="px-4 py-3"><?= $esc($k) ?></td>
                <td class="px-4 py-3"><?= (int) ($n['total'] ?? 0) ?></td>
                <td class="px-4 py-3"><?= (int) ($n['prontos'] ?? 0) ?></td>
                <td class="px-4 py-3"><?= (int) ($n['erros'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="text-sm <?= empty($pode['ok']) ? 'text-amber-800' : 'text-green-800' ?> mb-4"><?= $esc($pode['motivo'] ?? (empty($pode['ok']) ? '' : 'Pronto para gerar.')) ?></p>
<a href="<?= URL ?>/admin/censo/<?= $eid ?>/exportacoes" class="btn-primary-custom inline-flex px-4 py-2 rounded-lg text-sm font-semibold">Ir para exportações</a>
