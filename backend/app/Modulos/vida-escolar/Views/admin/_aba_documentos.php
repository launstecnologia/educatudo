<?php
$esc = $esc ?? static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = $base ?? (URL . '/admin/students/' . (int) ($aluno_id ?? 0) . '/vida-escolar');
$token = (string) ($csrf_token ?? $token ?? '');
$alunoId = (int) ($aluno_id ?? 0);

if (!isset($catalogo_emissao) || !isset($log_emissoes)) {
    $pathEmissao = dirname(__DIR__, 2) . '/Services/EmissaoDocumentosAlunoService.php';
    if (is_file($pathEmissao)) {
        require_once $pathEmissao;
    }
    if (class_exists(\App\Modulos\VidaEscolar\Services\EmissaoDocumentosAlunoService::class, false)) {
        $svcEmissao = new \App\Modulos\VidaEscolar\Services\EmissaoDocumentosAlunoService();
        $catalogo_emissao = $svcEmissao->catalogo($alunoId);
        $log_emissoes = $svcEmissao->listar($alunoId);
    }
}
$catalogoEmissao = is_array($catalogo_emissao ?? null) ? $catalogo_emissao : [];
$pathArquivoPdf = dirname(__DIR__, 2) . '/Services/ArquivoPdfAlunoService.php';
if (is_file($pathArquivoPdf)) {
    require_once $pathArquivoPdf;
}
$svcArquivoPdf = class_exists(\App\Modulos\VidaEscolar\Services\ArquivoPdfAlunoService::class, false)
    ? new \App\Modulos\VidaEscolar\Services\ArquivoPdfAlunoService()
    : null;
$pdfsEmitidos = $svcArquivoPdf ? $svcArquivoPdf->listar($alunoId) : [];
$arquivoPdfPronto = $svcArquivoPdf ? $svcArquivoPdf->tabelaPronta() : false;
$formatarQuando = static function (string $quando): string {
    $quando = trim($quando);
    if ($quando === '') {
        return '';
    }
    $ts = strtotime($quando);
    return $ts !== false ? date('d/m/Y H:i', $ts) : $quando;
};
?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900">Emitir documento</h3>
    <p class="text-sm text-gray-500 mt-1 mb-4">O PDF fica guardado no arquivo da escola e aparece no registro abaixo.</p>
    <ul class="divide-y divide-gray-100 border border-gray-200 rounded-lg">
        <?php foreach ($catalogoEmissao as $doc): ?>
        <li class="flex items-center justify-between gap-3 px-4 py-3">
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-gray-900"><?= $esc($doc['nome'] ?? '') ?></span>
                <span class="block text-sm text-gray-500 mt-0.5"><?= $esc($doc['descricao'] ?? '') ?></span>
            </span>
            <form method="post" action="<?= $esc($base) ?>/emitir/<?= $esc($doc['tipo'] ?? '') ?>" target="_blank" class="shrink-0"
                  onsubmit="setTimeout(function(){window.location.reload()},700)">
                <input type="hidden" name="_token" value="<?= $esc($token) ?>">
                <button class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Emitir</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>
</div>

<div class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900">Registro de emissões</h3>
    <p class="text-sm text-gray-500 mt-1 mb-4">Boletim, notas, declarações e os demais PDFs gerados para este aluno, com data e hora.</p>
    <?php if (!$arquivoPdfPronto): ?>
    <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-4">Aplique a migration <code>2026_10_04_aluno_pdfs_emitidos.sql</code> no Master para guardar os PDFs.</p>
    <?php endif; ?>
    <?php if ($pdfsEmitidos === []): ?>
    <p class="text-sm text-gray-500">Nenhum PDF emitido ainda.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b border-gray-200">
                    <th class="py-2 pr-4 font-medium">Documento</th>
                    <th class="py-2 pr-4 font-medium">Data e hora</th>
                    <th class="py-2 pr-4 font-medium">Quem emitiu</th>
                    <th class="py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pdfsEmitidos as $linha):
                    $hrefPdf = ($linha['origem'] ?? '') === 'modelo'
                        ? URL . '/admin/paineis/documentos/' . (int) ($linha['id'] ?? 0) . '/pdf'
                        : $base . '/pdf-emitido/' . (int) ($linha['id'] ?? 0);
                ?>
                <tr class="border-b border-gray-100">
                    <td class="py-2 pr-4 text-gray-900"><?= $esc($linha['titulo'] ?? '') ?></td>
                    <td class="py-2 pr-4 text-gray-600 whitespace-nowrap"><?= $esc($formatarQuando((string) ($linha['quando'] ?? ''))) ?></td>
                    <td class="py-2 pr-4 text-gray-600"><?= $esc($linha['quem'] ?? '') ?></td>
                    <td class="py-2 text-right">
                        <a href="<?= $esc($hrefPdf) ?>" target="_blank" class="text-sm font-semibold text-gray-800 hover:underline">Abrir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
