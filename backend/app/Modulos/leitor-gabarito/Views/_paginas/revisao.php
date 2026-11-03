<?php
$leitura = $leitura ?? [];
$questoes = $questoes ?? [];
$alunos = $alunos ?? [];
$leituraId = (int) ($leitura['id'] ?? 0);
$provaId = (int) ($leitura['prova_id'] ?? 0);
$baseUrl = URL . ($base ?? '');
$letrasPossiveis = ['A', 'B', 'C', 'D', 'E'];
?>
<div class="w-full space-y-6">
    <?php require __DIR__ . '/flash.php'; ?>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Revisar leitura</h2>
            <p class="text-gray-600"><?= htmlspecialchars((string) ($leitura['prova_titulo'] ?? '')) ?></p>
        </div>
        <a href="<?= htmlspecialchars($baseUrl) ?>/provas/<?= $provaId ?>" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>

    <?php if (!empty($leitura['mensagem'])): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"><?= htmlspecialchars((string) $leitura['mensagem']) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-lg p-4">
            <img src="<?= htmlspecialchars($baseUrl) ?>/leituras/<?= $leituraId ?>/imagem" alt="Scan da folha" class="w-full h-auto rounded-lg border border-gray-200">
            <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/leituras/<?= $leituraId ?>/reler" class="mt-4">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                <button type="submit" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Ler de novo</button>
            </form>
        </div>

        <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/leituras/<?= $leituraId ?>/confirmar" class="bg-white rounded-xl shadow-lg p-6 space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
            <div>
                <label for="aluno_id" class="block text-sm font-medium text-gray-700 mb-1">Aluno</label>
                <select id="aluno_id" name="aluno_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione</option>
                    <?php foreach ($alunos as $aluno): ?>
                    <option value="<?= (int) $aluno['id'] ?>" <?= (int) ($leitura['aluno_id'] ?? 0) === (int) $aluno['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $aluno['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($questoes === []): ?>
            <p class="text-sm text-gray-600">Ainda não há marcações nesta imagem.</p>
            <?php else: ?>
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Questão</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Leitura</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Resposta</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($questoes as $questao): ?>
                        <?php
                            $situacao = (string) ($questao['situacao'] ?? '');
                            $destaque = in_array($situacao, ['dupla', 'duvida'], true) ? 'bg-amber-50' : '';
                            $opcoes = $questao['opcoes'] ?? [];
                            $letraAtual = (string) ($questao['letra'] ?? '');
                        ?>
                        <tr class="<?= $destaque ?>">
                            <td class="px-4 py-2 text-sm text-gray-900"><?= (int) ($questao['numero'] ?? 0) ?></td>
                            <td class="px-4 py-2 text-sm text-gray-600">
                                <?php
                                $partes = [];
                                foreach ($opcoes as $opcao) {
                                    $partes[] = $opcao['letra'] . ' ' . (int) round(((float) $opcao['preenchimento']) * 100) . '%';
                                }
                                echo htmlspecialchars(implode(' · ', $partes));
                                ?>
                            </td>
                            <td class="px-4 py-2">
                                <select name="letra[<?= (int) $questao['questao_id'] ?>]" class="px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-sm">
                                    <option value="" <?= $letraAtual === '' ? 'selected' : '' ?>>Em branco</option>
                                    <?php foreach ($opcoes as $opcao): ?>
                                    <option value="<?= htmlspecialchars((string) $opcao['letra']) ?>" <?= $letraAtual === (string) $opcao['letra'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) $opcao['letra']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                    <?php if ($opcoes === []): ?>
                                    <?php foreach ($letrasPossiveis as $letra): ?>
                                    <option value="<?= $letra ?>"><?= $letra ?></option>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium" <?= $questoes === [] ? 'disabled' : '' ?>>
                    Lançar nota
                </button>
            </div>
        </form>
    </div>
</div>
