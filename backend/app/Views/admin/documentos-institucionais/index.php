<?php
$documentos = $documentos ?? [];
$tipos = $tipos ?? [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));

$page_header_title = 'Documentos Institucionais';
$page_header_subtitle = 'PPP, Regimento Escolar e demais documentos oficiais da escola, versionados e disponíveis para auditoria.';
include __DIR__ . '/../_partials/page_header_list.php';
include __DIR__ . '/../_partials/flash_message.php';

if (!($schema_pronto ?? false)): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 mb-6">
        <i class="fa-solid fa-triangle-exclamation mr-2"></i> Execute a migration <code>2026_06_25_documentos_institucionais.sql</code> no painel Master.
    </div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Adicionar documento</h3>
    <form method="post" action="<?= URL ?>/admin/documentos-institucionais/salvar" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
            <select name="tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <?php foreach ($tipos as $k => $v): ?>
                    <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Título</label>
            <input type="text" name="titulo" placeholder="Ex.: PPP 2026" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Versão</label>
            <input type="text" name="versao" placeholder="Ex.: 1.0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Arquivo (PDF, DOC, imagem)</label>
            <input type="file" name="arquivo" accept=".pdf,.doc,.docx,.odt,.jpg,.jpeg,.png" class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-green-50 file:text-green-700 file:font-medium hover:file:bg-green-100">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-2">Observação</label>
            <input type="text" name="observacao" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
        </div>
        <div class="md:col-span-2 flex justify-end">
            <button type="submit" class="inline-flex items-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors">
                <i class="fa-solid fa-upload mr-2"></i> Salvar documento
            </button>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <?php foreach (['Tipo', 'Título', 'Versão', 'Enviado em', ''] as $h): ?>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= htmlspecialchars($h) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($documentos)): ?>
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">Nenhum documento cadastrado.</td></tr>
                <?php else: foreach ($documentos as $d): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm text-gray-700 whitespace-nowrap"><?= htmlspecialchars($tipos[$d['tipo']] ?? (string) $d['tipo']) ?></td>
                        <td class="px-6 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $d['titulo']) ?></td>
                        <td class="px-6 py-3 text-sm text-gray-600"><?= htmlspecialchars((string) ($d['versao'] ?? '—')) ?></td>
                        <td class="px-6 py-3 text-sm text-gray-600 whitespace-nowrap"><?= !empty($d['created_at']) ? date('d/m/Y', strtotime((string) $d['created_at'])) : '—' ?></td>
                        <td class="px-6 py-3 text-right whitespace-nowrap">
                            <?php if (!empty($d['arquivo_key'])): ?>
                                <a href="<?= URL ?>/admin/documentos-institucionais/baixar?id=<?= (int) $d['id'] ?>" target="_blank" class="text-sm font-medium text-green-700 hover:text-green-900 mr-4">Baixar</a>
                            <?php endif; ?>
                            <form method="post" action="<?= URL ?>/admin/documentos-institucionais/excluir" onsubmit="return confirm('Remover este documento?');" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                                <button type="submit" class="text-sm text-red-600 hover:text-red-800">Remover</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
