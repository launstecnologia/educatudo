<?php

use App\Modulos\ConselhoClasse\Models\ConselhoSessao;
use App\Modulos\ConselhoClasse\Services\ConselhoService;

$sessao = is_array($sessao ?? null) ? $sessao : [];
$matriz = is_array($matriz ?? null) ? $matriz : [];
$componentes = is_array($matriz['componentes'] ?? null) ? $matriz['componentes'] : [];
$linhas = is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
$pendencias = is_array($matriz['pendencias'] ?? null) ? $matriz['pendencias'] : [];
$participantes = is_array($matriz['participantes'] ?? null) ? $matriz['participantes'] : [];
$professoresTurma = is_array($matriz['professores_turma'] ?? null) ? $matriz['professores_turma'] : [];
$ocorrenciasPorAluno = is_array($ocorrencias_por_aluno ?? null) ? $ocorrencias_por_aluno : [];
$podeRegistrar = !empty($pode_registrar);
$alunoInicial = (int) ($aluno_inicial ?? 0);
$notaMinima = (float) ($matriz['nota_minima'] ?? 6);
$fila = [];
foreach ($linhas as $linhaFila) {
    if (!is_array($linhaFila)) {
        continue;
    }
    $codigoFila = (string) ($linhaFila['resultado_preliminar']['codigo'] ?? '');
    if ($codigoFila === 'recuperacao' || $codigoFila === 'baixa_frequencia') {
        $fila[] = $linhaFila;
    }
}
$indiceInicial = 0;
if ($alunoInicial > 0) {
    foreach ($fila as $iFila => $linhaFila) {
        if ((int) ($linhaFila['aluno']['id'] ?? 0) === $alunoInicial) {
            $indiceInicial = $iFila;
            break;
        }
    }
} else {
    foreach ($fila as $iFila => $linhaFila) {
        if (empty($linhaFila['resultado_homologado'])) {
            $indiceInicial = $iFila;
            break;
        }
    }
}
$csrf_token = $csrf_token ?? '';
$status = (string) ($sessao['status'] ?? '');
$sid = (int) ($sessao['id'] ?? 0);
$periodo = class_exists('PeriodoLetivo')
    ? \App\Core\PeriodoLetivo::rotulo((int) ($sessao['ano_letivo'] ?? 0), (int) ($sessao['bimestre'] ?? 0))
    : '';
if ($periodo === '') {
    $periodo = (int) ($sessao['bimestre'] ?? 0) . 'º período';
}
?>
<div class="mb-8">
    <div class="flex justify-between items-start gap-4 flex-wrap">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Conselho · <?= htmlspecialchars((string) ($sessao['turma_nome'] ?? '')) ?>
            </h2>
            <p class="text-gray-600">
                <?= htmlspecialchars($periodo) ?> / <?= (int) ($sessao['ano_letivo'] ?? 0) ?>
                <?php if (!empty($sessao['data_reuniao'])): ?>
                    · reunião <?= date('d/m/Y', strtotime((string) $sessao['data_reuniao'])) ?>
                <?php endif; ?>
            </p>
        </div>
        <a href="<?= URL ?>/admin/conselhos?ano_letivo=<?= (int) ($sessao['ano_letivo'] ?? 0) ?>&bimestre=<?= (int) ($sessao['bimestre'] ?? 0) ?>" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<?php include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php'; ?>

<div class="flex flex-wrap items-center gap-3 mb-6">
    <?php
    $ui_badge_variant = \App\Modulos\ConselhoClasse\Services\ConselhoService::statusBadge($status);
    $ui_badge_label = \App\Modulos\ConselhoClasse\Services\ConselhoService::statusLabel($status);
    include __DIR__ . '/../../../../Views/admin/_partials/ui/badge.php';
    ?>
    <span class="text-sm text-gray-600"><?= (int) ($pendencias['total'] ?? 0) ?> pendência(s)</span>
    <?php if ((int) ($pendencias['diarios'] ?? 0) > 0): ?>
        <span class="text-xs text-amber-700"><?= (int) $pendencias['diarios'] ?> diário(s) aberto(s)</span>
    <?php endif; ?>
    <?php if ((int) ($pendencias['notas'] ?? 0) > 0): ?>
        <span class="text-xs text-amber-700"><?= (int) $pendencias['notas'] ?> aluno(s) sem nota</span>
    <?php endif; ?>
    <?php if ((int) ($pendencias['frequencia'] ?? 0) > 0): ?>
        <span class="text-xs text-amber-700"><?= (int) $pendencias['frequencia'] ?> com baixa frequência</span>
    <?php endif; ?>
</div>

<div class="flex flex-wrap gap-2 mb-6">
    <?php if ($status === 'em_preparacao' || $status === 'reaberto'): ?>
    <form method="POST" action="<?= URL ?>/admin/conselhos/<?= $sid ?>/abrir">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token) ?>">
        <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Colocar em andamento</button>
    </form>
    <?php endif; ?>
    <?php if ($status === 'em_andamento' || $status === 'reaberto'): ?>
    <form method="POST" action="<?= URL ?>/admin/conselhos/<?= $sid ?>/finalizar" onsubmit="return confirm('Finalizar este Conselho? Depois só perfis autorizados poderão reabrir.');">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token) ?>">
        <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold border border-gray-300 bg-white text-gray-800 hover:bg-gray-50">Finalizar</button>
    </form>
    <?php endif; ?>
    <?php if ($status === 'finalizado'): ?>
    <form method="POST" action="<?= URL ?>/admin/conselhos/<?= $sid ?>/reabrir" onsubmit="return confirm('Reabrir o Conselho? Toda alteração posterior fica auditada.');">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token) ?>">
        <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100">Reabrir</button>
    </form>
    <?php endif; ?>
    <a href="<?= URL ?>/admin/conselhos/<?= $sid ?>/ata" class="px-4 py-2 rounded-lg text-sm font-semibold border border-gray-300 bg-white text-gray-800 hover:bg-gray-50">Ata</a>
</div>

<?php if (!empty($sessao['pauta'])): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
    <h3 class="text-sm font-semibold text-gray-900 mb-1">Pauta</h3>
    <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars((string) $sessao['pauta']) ?></p>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
    <div class="px-5 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-gray-900">Alunos para deliberar</h3>
            <p class="text-sm text-gray-500 mt-0.5">Só quem ficou abaixo da média depois da recuperação, ou abaixo da frequência. Um aluno por vez.</p>
        </div>
        <?php if ($fila !== []): ?>
        <div class="flex items-center gap-2">
            <button type="button" id="conselho-anterior" class="px-3 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Anterior</button>
            <span id="conselho-posicao" class="text-sm text-gray-600 min-w-[4.5rem] text-center"><?= $indiceInicial + 1 ?> de <?= count($fila) ?></span>
            <button type="button" id="conselho-proximo" class="px-3 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Próximo</button>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($fila === []): ?>
    <p class="px-5 py-12 text-center text-gray-500">Nenhum aluno ficou abaixo da média nem da frequência neste Conselho.</p>
    <?php else: ?>
    <?php foreach ($fila as $iFila => $linha):
        $aluno = is_array($linha['aluno'] ?? null) ? $linha['aluno'] : [];
        $prelim = is_array($linha['resultado_preliminar'] ?? null) ? $linha['resultado_preliminar'] : [];
        $abaixo = is_array($prelim['abaixo'] ?? null) ? $prelim['abaixo'] : [];
        $freq = is_array($linha['frequencia'] ?? null) ? $linha['frequencia'] : [];
        $pct = $freq['percentual'] ?? null;
        $baixaFreq = $pct !== null && (float) $pct < \App\Services\FrequencyService::MINIMO_LEGAL;
        $alunoIdCard = (int) ($aluno['id'] ?? 0);
        $proximoId = (int) ($fila[$iFila + 1]['aluno']['id'] ?? 0);
        $homolog = $linha['resultado_homologado'] ?? null;
        $ocs = is_array($ocorrenciasPorAluno[$alunoIdCard] ?? null) ? $ocorrenciasPorAluno[$alunoIdCard] : [];
        $nomeCc = \App\Helpers\AlunoLancamentoNotaHelper::rotuloNomeComTransferencia(
            (string) ($aluno['nome'] ?? ''),
            !empty($aluno['transferido'])
        );
    ?>
    <article class="conselho-aluno px-5 py-5 <?= $iFila === $indiceInicial ? '' : 'hidden' ?>" data-indice="<?= (int) $iFila ?>" data-aluno="<?= $alunoIdCard ?>">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
            <div>
                <h4 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($nomeCc) ?></h4>
                <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars((string) ($prelim['detalhe'] ?? '')) ?></p>
            </div>
            <div class="text-sm text-gray-700">
                <?php if ($homolog): ?>
                Deliberado: <?= htmlspecialchars(\App\Modulos\ConselhoClasse\Models\ConselhoSessao::RESULTADOS[$homolog] ?? (string) $homolog) ?>
                <?php else: ?>
                Sem deliberação
                <?php endif; ?>
            </div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <?php foreach ($componentes as $comp):
                $chave = mb_strtolower((string) $comp['nome']);
                $cel = $linha['componentes'][$chave] ?? null;
                $media = is_array($cel) ? ($cel['media'] ?? null) : null;
                $isAbaixo = $media !== null && in_array((string) $comp['nome'], $abaixo, true);
            ?>
            <div class="rounded-lg border <?= $isAbaixo ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-50' ?> px-3 py-2">
                <div class="text-xs text-gray-500"><?= htmlspecialchars((string) ($comp['sigla'] ?? $comp['nome'])) ?></div>
                <div class="text-base font-semibold <?= $isAbaixo ? 'text-amber-800' : 'text-gray-900' ?>"><?= $media !== null ? number_format((float) $media, 1, ',', '.') : '—' ?></div>
            </div>
            <?php endforeach; ?>
            <div class="rounded-lg border <?= $baixaFreq ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-50' ?> px-3 py-2">
                <div class="text-xs text-gray-500">Frequência</div>
                <div class="text-base font-semibold <?= $baixaFreq ? 'text-amber-800' : 'text-gray-900' ?>"><?= $pct !== null ? number_format((float) $pct, 1, ',', '.') . '%' : '—' ?></div>
                <div class="text-xs text-gray-500"><?= (int) ($freq['faltas'] ?? 0) ?> falta(s)</div>
            </div>
        </div>
        <div class="mb-5">
            <h5 class="text-sm font-semibold text-gray-900 mb-2">Ocorrências</h5>
            <?php if ($ocs === []): ?>
            <p class="text-sm text-gray-500">Nenhuma ocorrência cadastrada.</p>
            <?php else: ?>
            <ul class="space-y-1">
                <?php foreach ($ocs as $oc): ?>
                <li class="text-sm text-gray-700">
                    <a href="<?= URL ?>/admin/ocorrencias/<?= (int) ($oc['id'] ?? 0) ?>" class="hover:underline"><?= htmlspecialchars((string) ($oc['titulo'] ?? '')) ?></a>
                    <?php if (!empty($oc['data'])): ?>
                    <span class="text-xs text-gray-500"> · <?= date('d/m/Y', strtotime((string) $oc['data'])) ?></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php if ($podeRegistrar): ?>
        <form method="POST" action="<?= URL ?>/admin/conselhos/<?= $sid ?>/deliberar" class="border-t border-gray-100 pt-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token) ?>">
            <input type="hidden" name="aluno_id" value="<?= $alunoIdCard ?>">
            <input type="hidden" name="origem" value="fila">
            <input type="hidden" name="proximo_aluno_id" value="<?= $proximoId ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="block">
                    <span class="block text-sm font-medium text-gray-700 mb-1.5">Decisão</span>
                    <select name="resultado_decisao" required class="w-full h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm">
                        <option value="">Selecione</option>
                        <?php foreach (\App\Modulos\ConselhoClasse\Models\ConselhoSessao::RESULTADOS as $valor => $rotulo): ?>
                        <option value="<?= htmlspecialchars($valor) ?>" <?= $homolog === $valor ? 'selected' : '' ?>><?= htmlspecialchars($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block md:col-span-2">
                    <span class="block text-sm font-medium text-gray-700 mb-1.5">Justificativa</span>
                    <textarea name="justificativa" rows="3" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                </label>
            </div>
            <div class="flex justify-end mt-4">
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold"><?= $proximoId > 0 ? 'Registrar e ir ao próximo' : 'Registrar deliberação' ?></button>
            </div>
        </form>
        <?php else: ?>
        <p class="text-sm text-gray-500 border-t border-gray-100 pt-4">Coloque o Conselho em andamento para deliberar. A média mínima deste boletim é <?= number_format($notaMinima, 1, ',', '.') ?>.</p>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
<script>
(function () {
    var cards = Array.prototype.slice.call(document.querySelectorAll('.conselho-aluno'));
    var posicao = document.getElementById('conselho-posicao');
    var atual = 0;
    cards.forEach(function (card, i) {
        if (!card.classList.contains('hidden')) atual = i;
    });
    function mostrar(indice) {
        if (!cards.length) return;
        atual = Math.max(0, Math.min(cards.length - 1, indice));
        cards.forEach(function (card, i) {
            card.classList.toggle('hidden', i !== atual);
        });
        if (posicao) posicao.textContent = (atual + 1) + ' de ' + cards.length;
    }
    var anterior = document.getElementById('conselho-anterior');
    var proximo = document.getElementById('conselho-proximo');
    if (anterior) anterior.addEventListener('click', function () { mostrar(atual - 1); });
    if (proximo) proximo.addEventListener('click', function () { mostrar(atual + 1); });
})();
</script>

<div class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-base font-semibold text-gray-900 mb-1">Participantes</h3>
    <p class="text-sm text-gray-500 mb-4">Professores da grade são sugeridos ao iniciar. Ajuste presença e cargos.</p>
    <form method="POST" action="<?= URL ?>/admin/conselhos/<?= $sid ?>/participantes">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token) ?>">
        <?php
        $lista = $participantes;
        if ($lista === [] && $professoresTurma !== []) {
            foreach ($professoresTurma as $prof) {
                $lista[] = [
                    'nome' => $prof['nome'],
                    'cargo' => 'professor',
                    'presente' => 1,
                    'professor_id' => $prof['id'],
                ];
            }
        }
        ?>
        <div class="overflow-x-auto mb-4">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cargo</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Presente</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($lista as $i => $p): ?>
                    <tr>
                        <td class="px-4 py-2">
                            <input type="hidden" name="professor_id[<?= (int) $i ?>]" value="<?= (int) ($p['professor_id'] ?? 0) ?>">
                            <input type="text" name="nome[<?= (int) $i ?>]" value="<?= htmlspecialchars((string) ($p['nome'] ?? '')) ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" <?= $status === 'finalizado' ? 'readonly' : '' ?>>
                        </td>
                        <td class="px-4 py-2">
                            <select name="cargo[<?= (int) $i ?>]" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" <?= $status === 'finalizado' ? 'disabled' : '' ?>>
                                <?php foreach (\App\Modulos\ConselhoClasse\Models\ConselhoSessao::CARGOS as $valor => $rotulo): ?>
                                    <option value="<?= htmlspecialchars($valor) ?>" <?= ($p['cargo'] ?? '') === $valor ? 'selected' : '' ?>><?= htmlspecialchars($rotulo) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="px-4 py-2">
                            <input type="checkbox" name="presente[<?= (int) $i ?>]" value="1" class="rounded border-gray-300" <?= !empty($p['presente']) ? 'checked' : '' ?> <?= $status === 'finalizado' ? 'disabled' : '' ?>>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($status !== 'finalizado'): ?>
        <div class="flex justify-end">
            <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Salvar participantes</button>
        </div>
        <?php endif; ?>
    </form>
</div>
