<?php
$detalhe = $detalhe ?? [];
$bloco = $detalhe['bloco'] ?? [];
$blocoId = (int) ($bloco['id'] ?? 0);
$baseUrl = URL . ($base ?? '');
$turmas = $detalhe['turmas'] ?? [];
$provas = $detalhe['provas'] ?? [];
$leituras = $detalhe['leituras'] ?? ['itens' => [], 'pagina' => 1, 'paginas' => 1, 'total' => 0];
$nomes = [];
foreach ($provas as $provaBloco) {
    $nome = trim((string) ($provaBloco['materia_nome'] ?? $provaBloco['titulo'] ?? ''));
    if ($nome !== '') {
        $nomes[] = $nome;
    }
}
$badges = [
    'na_fila' => ['Na fila', 'bg-gray-100 text-gray-700'],
    'lendo' => ['Lendo', 'bg-blue-100 text-blue-800'],
    'revisao' => ['Revisão', 'bg-amber-100 text-amber-800'],
    'confirmada' => ['Lançada', 'bg-green-100 text-green-800'],
    'erro' => ['Erro', 'bg-red-100 text-red-800'],
];
$dropdown = dirname(__DIR__, 4) . '/Views/admin/_partials/row_actions_dropdown.php';
?>
<div class="w-full space-y-6">
    <?php require __DIR__ . '/flash.php'; ?>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars((string) ($bloco['titulo'] ?? 'Prova impressa')) ?></h2>
            <p class="text-gray-600">Gabarito de todas as matérias<?= $nomes === [] ? '' : ': ' . htmlspecialchars(implode(', ', $nomes)) ?></p>
        </div>
        <a href="<?= URL ?>/admin/provas" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
            <div>
                <p class="text-sm font-semibold text-gray-900">Folha do bloco</p>
                <p class="text-sm text-gray-500 mt-0.5">Esta leitura vale para o gabarito impresso em Imprimir prova, com todas as matérias na mesma folha.</p>
            </div>
        </div>
        <div class="p-5">
            <?php if (empty($turmas) || empty($tabelas_prontas)): ?>
            <p class="text-sm text-gray-600">Gere as folhas pelo botão Imprimir prova do bloco.</p>
            <?php else: ?>
            <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/blocos/<?= $blocoId ?>/gerar" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                <div>
                    <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-1">Turma</label>
                    <select id="turma_id" name="turma_id" class="px-4 py-2 border border-gray-300 rounded-lg bg-white">
                        <?php foreach ($turmas as $turma): ?>
                        <option value="<?= (int) $turma['id'] ?>"><?= htmlspecialchars((string) $turma['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium">Gerar folhas do bloco</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($captura_qr['png'])): ?>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="border-b border-gray-200 bg-gray-50 px-5 py-4">
            <p class="text-sm font-semibold text-gray-900">Ler pelo celular</p>
            <p class="text-sm text-gray-500 mt-0.5">O QR abre a câmera para fotografar o gabarito de todas as matérias. Vale 8 horas.</p>
        </div>
        <div class="p-5">
            <img src="<?= htmlspecialchars((string) $captura_qr['png']) ?>" alt="QR Code para fotografar o gabarito do bloco" width="220" height="220">
        </div>
    </div>
    <?php endif; ?>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="border-b border-gray-200 bg-gray-50 px-5 py-4">
            <p class="text-sm font-semibold text-gray-900">Scans</p>
            <p class="text-sm text-gray-500 mt-0.5">Envie a foto do gabarito que tem todas as matérias.</p>
        </div>
        <div class="p-5 space-y-4">
            <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/blocos/<?= $blocoId ?>/enviar" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                <input type="file" name="scans[]" accept="image/jpeg,image/png,image/webp" multiple
                       class="block text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700">
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium">Ler scans</button>
            </form>
            <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/blocos/<?= $blocoId ?>/processar">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                <button type="submit" class="text-sm font-medium text-gray-700 underline">Continuar leituras que ficaram na fila</button>
            </form>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="border-b border-gray-200 bg-gray-50 px-5 py-4">
            <p class="text-sm font-semibold text-gray-900">Leituras</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nota</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quando</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($leituras['itens'])): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">Nenhum scan enviado.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($leituras['itens'] as $leitura): ?>
                    <?php $badge = $badges[$leitura['status']] ?? ['—', 'bg-gray-100 text-gray-700']; ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <?php $notasPorMateria = ($leitura['status'] ?? '') === 'confirmada' && trim((string) ($leitura['mensagem'] ?? '')) !== ''; ?>
                            <div class="font-medium"><?= htmlspecialchars((string) ($leitura['aluno_nome'] ?? 'Aluno não identificado')) ?></div>
                            <?php if (!empty($leitura['mensagem']) && !$notasPorMateria): ?>
                            <div class="text-gray-500 mt-1"><?= htmlspecialchars((string) $leitura['mensagem']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium <?= $badge[1] ?>"><?= htmlspecialchars($badge[0]) ?></span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <?php if (!empty($notasPorMateria)): ?>
                            <?= htmlspecialchars((string) $leitura['mensagem']) ?>
                            <?php else: ?>
                            <?= $leitura['nota'] === null ? '—' : htmlspecialchars(number_format((float) $leitura['nota'], 2, ',', '.')) ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $leitura['criado_em']))) ?></td>
                        <td class="px-6 py-4 text-right">
                            <?php ob_start(); ?>
                            <a href="<?= htmlspecialchars($baseUrl) ?>/leituras/<?= (int) $leitura['id'] ?>"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-eye text-gray-400 w-4 text-center"></i> Revisar
                            </a>
                            <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/leituras/<?= (int) $leitura['id'] ?>/excluir"
                                  onsubmit="return confirm('Excluir esta leitura? A nota já lançada na prova permanece.');">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                                <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                                </button>
                            </form>
                            <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                            <?php $row_actions_dropdown_id = 'row-actions-leitura-bloco-' . (int) $leitura['id']; ?>
                            <?php include $dropdown; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
