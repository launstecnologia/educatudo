<?php
$edicao = is_array($edicao ?? null) ? $edicao : null;
$eid = (int) ($edicao['id'] ?? 0);
$retornos = is_array($retornos ?? null) ? $retornos : [];
$exportacoes = is_array($exportacoes ?? null) ? $exportacoes : [];
$page_header_title = 'Retornos do Educacenso';
$page_header_subtitle = 'O arquivo original é guardado. Códigos INEP só são associados depois da conferência, sem aplicação silenciosa.';
include dirname(__DIR__, 4) . '/Views/admin/_partials/page_header_list.php';
include dirname(__DIR__, 4) . '/Views/admin/_partials/flash_message.php';
include __DIR__ . '/_contexto.php';
$nav_atual = 'retornos';
include __DIR__ . '/_nav.php';
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="bg-white rounded-xl shadow-lg p-6 w-full mb-6">
    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Arquivo de retorno</label>
            <input type="file" name="arquivo" required class="w-full text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Exportação relacionada</label>
            <select name="exportacao_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                <option value="0">Não vincular</option>
                <?php foreach ($exportacoes as $ex): ?>
                    <option value="<?= (int) $ex['id'] ?>">v<?= (int) $ex['versao'] ?> — <?= $esc($ex['nome_original']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-2">
            <button class="btn-primary-custom px-4 py-2 rounded-lg font-semibold">Importar retorno</button>
        </div>
    </form>
</div>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3 text-left">Arquivo</th>
                <th class="px-4 py-3 text-left">Exportação</th>
                <th class="px-4 py-3 text-left">Resumo</th>
                <th class="px-4 py-3 text-left">Importado em</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if ($retornos === []): ?>
            <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">Nenhum retorno importado.</td></tr>
            <?php endif; ?>
            <?php foreach ($retornos as $r):
                $resumo = json_decode((string) ($r['resumo_json'] ?? ''), true);
                $resumo = is_array($resumo) ? $resumo : [];
            ?>
            <tr>
                <td class="px-4 py-3"><?= $esc($r['nome_original'] ?? '') ?></td>
                <td class="px-4 py-3"><?= $r['exportacao_versao'] ? 'v' . (int) $r['exportacao_versao'] : '—' ?></td>
                <td class="px-4 py-3 text-gray-600"><?= $esc($resumo['aviso'] ?? ($resumo['linhas'] ?? '') . ' linhas') ?></td>
                <td class="px-4 py-3"><?= $esc($r['importado_em'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
