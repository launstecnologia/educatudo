<?php
$edicao = is_array($edicao ?? null) ? $edicao : null;
$eid = (int) ($edicao['id'] ?? 0);
$exportacoes = is_array($exportacoes ?? null) ? $exportacoes : [];
$podeGerar = is_array($pode_gerar ?? null) ? $pode_gerar : ['ok' => false, 'motivo' => ''];
$page_header_title = 'Exportações';
$page_header_subtitle = 'Cada geração cria uma nova versão com snapshot e hash. Arquivos anteriores não são sobrescritos.';
include dirname(__DIR__, 4) . '/Views/admin/_partials/page_header_list.php';
include dirname(__DIR__, 4) . '/Views/admin/_partials/flash_message.php';
include __DIR__ . '/_contexto.php';
$nav_atual = 'exportacoes';
include __DIR__ . '/_nav.php';
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<p class="text-sm text-gray-600 mb-4">
    Cada geração cria o TXT de <strong>importação</strong> (registros 00–60 + 99|, ISO-8859-1, nome até 20 caracteres)
    e, se houver aluno sem código INEP, o TXT de <strong>identificação</strong>. Use a edição <strong>Matrícula Inicial</strong>.
</p>
<div class="mb-4 flex flex-wrap gap-3">
    <span title="<?= $esc($podeGerar['motivo'] ?? '') ?>">
        <form method="POST" class="inline">
            <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
            <button <?= empty($podeGerar['ok']) ? 'disabled' : '' ?> class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed">Gerar TXT de importação</button>
        </form>
    </span>
    <a href="<?= URL ?>/admin/censo/<?= $eid ?>/previa" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm">Prévia</a>
</div>
<?php if (empty($podeGerar['ok'])): ?>
<p class="text-sm text-amber-800 mb-4"><?= $esc($podeGerar['motivo'] ?? '') ?></p>
<?php endif; ?>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3 text-left">Versão</th>
                <th class="px-4 py-3 text-left">Tipo</th>
                <th class="px-4 py-3 text-left">Arquivo</th>
                <th class="px-4 py-3 text-left">Hash SHA-256</th>
                <th class="px-4 py-3 text-left">Linhas</th>
                <th class="px-4 py-3 text-left">Gerado em</th>
                <th class="px-4 py-3 text-right">Ação</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if ($exportacoes === []): ?>
            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">Nenhuma exportação gerada.</td></tr>
            <?php endif; ?>
            <?php foreach ($exportacoes as $ex): ?>
            <tr>
                <td class="px-4 py-3">v<?= (int) $ex['versao'] ?></td>
                <td class="px-4 py-3"><?= $esc(($ex['tipo'] ?? '') === 'identificacao' ? 'Identificação' : 'Importação') ?></td>
                <td class="px-4 py-3"><?= $esc($ex['nome_original'] ?? '') ?></td>
                <td class="px-4 py-3 font-mono text-xs"><?= $esc(substr((string) ($ex['hash_sha256'] ?? ''), 0, 16)) ?>…</td>
                <td class="px-4 py-3"><?= (int) ($ex['total_linhas'] ?? 0) ?></td>
                <td class="px-4 py-3"><?= $esc($ex['gerado_em'] ?? '') ?></td>
                <td class="px-4 py-3 text-right">
                    <a class="text-indigo-600 font-medium" href="<?= URL ?>/admin/censo/<?= $eid ?>/exportacoes/<?= (int) $ex['id'] ?>/download">Baixar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
