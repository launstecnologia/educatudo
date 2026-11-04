<?php
$leitura = $leitura ?? [];
$grupos = $grupos ?? [];
$alunos = $alunos ?? [];
$leituraId = (int) ($leitura['id'] ?? 0);
$provaId = (int) ($leitura['prova_id'] ?? 0);
$baseUrl = URL . ($base ?? '');
$letrasPossiveis = ['A', 'B', 'C', 'D', 'E'];
$nomesGrupo = [];
foreach ($grupos as $grupoNome) {
    $nome = trim((string) ($grupoNome['nome'] ?? ''));
    if ($nome !== '') {
        $nomesGrupo[] = $nome;
    }
}
$variasMaterias = count($grupos) > 1;
?>
<div class="w-full space-y-6">
    <?php require __DIR__ . '/flash.php'; ?>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Revisar leitura</h2>
            <p class="text-gray-600"><?= $variasMaterias
                ? 'Gabarito com ' . htmlspecialchars(implode(', ', $nomesGrupo))
                : htmlspecialchars((string) ($leitura['prova_titulo'] ?? '')) ?></p>
        </div>
        <a href="<?= htmlspecialchars(URL . (string) ($voltar ?? (($base ?? '') . '/provas/' . $provaId))) ?>" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>

    <?php if (!empty($leitura['mensagem']) && ($leitura['status'] ?? '') !== 'confirmada'): ?>
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

            <?php if ($grupos === []): ?>
            <p class="text-sm text-gray-600">Ainda não há marcações nesta imagem.</p>
            <?php else: ?>
            <p class="text-sm text-gray-600">A nota de cada matéria sai das questões certas, no valor cadastrado no gabarito.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php foreach ($grupos as $indiceGrupo => $grupo): ?>
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                    <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars((string) $grupo['nome']) ?></p>
                    <p class="text-sm text-gray-600 mt-1" data-resumo="<?= (int) $indiceGrupo ?>">
                        <?= (int) $grupo['acertos'] ?> de <?= (int) $grupo['total'] ?> acertos · nota <?= htmlspecialchars(number_format((float) $grupo['nota'], 2, ',', '.')) ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Questão</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Leitura</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Gabarito</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Resposta</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($grupos as $indiceGrupo => $grupo): ?>
                        <tr class="bg-gray-50">
                            <td colspan="4" class="px-4 py-2 text-sm font-semibold text-gray-900"><?= htmlspecialchars((string) $grupo['nome']) ?></td>
                        </tr>
                        <?php foreach ($grupo['questoes'] as $questao): ?>
                        <?php
                            $situacao = (string) ($questao['situacao'] ?? '');
                            $destaque = in_array($situacao, ['dupla', 'duvida'], true) ? 'bg-amber-50' : '';
                            $opcoes = $questao['opcoes'] ?? [];
                            $letraAtual = strtoupper((string) ($questao['letra'] ?? ''));
                            $gabarito = strtoupper((string) ($questao['gabarito'] ?? ''));
                            $acertou = $gabarito !== '' && $letraAtual === $gabarito;
                        ?>
                        <tr class="<?= $destaque ?>"
                            data-linha="1"
                            data-grupo="<?= (int) $indiceGrupo ?>"
                            data-valor="<?= htmlspecialchars((string) ($questao['valor'] ?? 0)) ?>"
                            data-gabarito="<?= htmlspecialchars($gabarito) ?>">
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
                            <td class="px-4 py-2 text-sm font-semibold text-gray-900"><?= $gabarito === '' ? '—' : htmlspecialchars($gabarito) ?></td>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <select name="letra[<?= (int) $questao['questao_id'] ?>]" class="px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-sm">
                                        <option value="" <?= $letraAtual === '' ? 'selected' : '' ?>>Em branco</option>
                                        <?php foreach ($opcoes as $opcao): ?>
                                        <option value="<?= htmlspecialchars((string) $opcao['letra']) ?>" <?= $letraAtual === (string) $opcao['letra'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string) $opcao['letra']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                        <?php if ($opcoes === []): ?>
                                        <?php foreach ($letrasPossiveis as $letra): ?>
                                        <option value="<?= $letra ?>" <?= $letraAtual === $letra ? 'selected' : '' ?>><?= $letra ?></option>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <span data-acerto class="text-xs font-medium <?= $gabarito === '' ? 'text-gray-400' : ($letraAtual === '' ? 'text-gray-500' : ($acertou ? 'text-green-700' : 'text-red-700')) ?>">
                                        <?php
                                        if ($gabarito === '') {
                                            echo '';
                                        } elseif ($letraAtual === '') {
                                            echo 'em branco';
                                        } elseif ($acertou) {
                                            echo 'certa';
                                        } else {
                                            echo 'errada';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium" <?= $grupos === [] ? 'disabled' : '' ?>>
                    <?= $variasMaterias ? 'Lançar notas' : 'Lançar nota' ?>
                </button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var form = document.querySelector('form[action$="/confirmar"]');
    if (!form) {
        return;
    }
    function formato(valor) {
        return valor.toFixed(2).replace('.', ',');
    }
    function atualizar() {
        var grupos = {};
        form.querySelectorAll('[data-linha]').forEach(function (linha) {
            var indice = linha.getAttribute('data-grupo');
            if (!grupos[indice]) {
                grupos[indice] = { acertos: 0, total: 0, nota: 0 };
            }
            var gabarito = (linha.getAttribute('data-gabarito') || '').toUpperCase();
            var select = linha.querySelector('select');
            var letra = select ? (select.value || '').toUpperCase() : '';
            var acertou = gabarito !== '' && letra === gabarito;
            grupos[indice].total += 1;
            if (acertou) {
                grupos[indice].acertos += 1;
                grupos[indice].nota += parseFloat(linha.getAttribute('data-valor') || '0') || 0;
            }
            var selo = linha.querySelector('[data-acerto]');
            if (!selo) {
                return;
            }
            selo.className = 'text-xs font-medium';
            if (gabarito === '') {
                selo.textContent = '';
                return;
            }
            if (letra === '') {
                selo.textContent = 'em branco';
                selo.className += ' text-gray-500';
                return;
            }
            selo.textContent = acertou ? 'certa' : 'errada';
            selo.className += acertou ? ' text-green-700' : ' text-red-700';
        });
        Object.keys(grupos).forEach(function (indice) {
            var alvo = form.querySelector('[data-resumo="' + indice + '"]');
            if (!alvo) {
                return;
            }
            var item = grupos[indice];
            alvo.textContent = item.acertos + ' de ' + item.total + ' acertos · nota ' + formato(item.nota);
        });
    }
    form.addEventListener('change', atualizar);
})();
</script>
