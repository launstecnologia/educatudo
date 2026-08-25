<?php
require_once __DIR__ . '/../../Models/ConselhoSessao.php';
require_once __DIR__ . '/../../Services/ConselhoService.php';

use App\Modulos\ConselhoClasse\Models\ConselhoSessao;
use App\Modulos\ConselhoClasse\Services\ConselhoService;

$sessao = is_array($sessao ?? null) ? $sessao : [];
$ficha = is_array($ficha ?? null) ? $ficha : [];
$aluno = is_array($ficha['aluno'] ?? null) ? $ficha['aluno'] : [];
$linha = is_array($ficha['linha'] ?? null) ? $ficha['linha'] : [];
$prelim = is_array($linha['resultado_preliminar'] ?? null) ? $linha['resultado_preliminar'] : [];
$csrf_token = $csrf_token ?? '';
$podeRegistrar = !empty($pode_registrar);
$sid = (int) ($sessao['id'] ?? 0);
$alunoId = (int) ($aluno['id'] ?? 0);
$ocorrencias = is_array($ficha['ocorrencias'] ?? null) ? $ficha['ocorrencias'] : [];
$deliberacoes = is_array($ficha['deliberacoes'] ?? null) ? $ficha['deliberacoes'] : [];
$encaminhamentos = is_array($ficha['encaminhamentos'] ?? null) ? $ficha['encaminhamentos'] : [];
$observacoes = is_array($ficha['observacoes'] ?? null) ? $ficha['observacoes'] : [];
$componentesAluno = is_array($linha['componentes'] ?? null) ? $linha['componentes'] : [];
$freq = $linha['frequencia'] ?? null;
?>
<div class="mb-8">
    <div class="flex justify-between items-start gap-4 flex-wrap">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars((string) ($aluno['nome'] ?? 'Aluno')) ?></h2>
            <p class="text-gray-600">
                Ficha no Conselho · <?= htmlspecialchars((string) ($sessao['turma_nome'] ?? '')) ?>
                · <?= (int) ($sessao['bimestre'] ?? 0) ?>º Bimestre / <?= (int) ($sessao['ano_letivo'] ?? 0) ?>
            </p>
        </div>
        <a href="<?= URL ?>/admin/conselhos/<?= $sid ?>" class="text-gray-600 hover:text-gray-900">← Voltar à matriz</a>
    </div>
</div>

<?php include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php'; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-xs font-medium text-gray-500 uppercase mb-2">Resultado preliminar</p>
        <?php
        $codigo = (string) ($prelim['codigo'] ?? '');
        $ui_badge_variant = $codigo === 'aprovado' ? 'ativo' : ($codigo === 'sem_notas' || $codigo === 'transferido' ? 'neutro' : 'pendente');
        $ui_badge_label = (string) ($prelim['label'] ?? '—');
        include __DIR__ . '/../../../../Views/admin/_partials/ui/badge.php';
        ?>
        <p class="text-sm text-gray-600 mt-2"><?= htmlspecialchars((string) ($prelim['detalhe'] ?? '')) ?></p>
        <p class="text-xs text-gray-400 mt-3">Calculado a partir do boletim gerado e da frequência do diário. O Conselho não recalcula a fórmula.</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-xs font-medium text-gray-500 uppercase mb-2">Frequência</p>
        <p class="text-2xl font-semibold text-gray-900">
            <?= isset($freq['percentual']) && $freq['percentual'] !== null ? number_format((float) $freq['percentual'], 1, ',', '.') . '%' : '—' ?>
        </p>
        <p class="text-sm text-gray-500 mt-1"><?= (int) ($freq['faltas'] ?? 0) ?> falta(s) em <?= (int) ($freq['total_aulas'] ?? 0) ?> aula(s)</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-xs font-medium text-gray-500 uppercase mb-2">Homologado</p>
        <?php $homolog = $linha['resultado_homologado'] ?? null; ?>
        <p class="text-lg font-semibold text-gray-900"><?= $homolog ? htmlspecialchars(ConselhoSessao::RESULTADOS[$homolog] ?? $homolog) : 'Ainda sem deliberação' ?></p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
    <div class="px-5 py-4 border-b border-gray-200">
        <h3 class="text-base font-semibold text-gray-900">Médias por componente</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Componente</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Média</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($componentesAluno === []): ?>
                <tr><td colspan="2" class="px-6 py-8 text-center text-gray-500 text-sm">Sem boletim gerado neste período.</td></tr>
                <?php else: ?>
                <?php foreach ($componentesAluno as $comp):
                    $abaixo = in_array((string) $comp['nome'], $prelim['abaixo'] ?? [], true);
                ?>
                <tr>
                    <td class="px-6 py-3 text-sm text-gray-900"><?= htmlspecialchars((string) $comp['nome']) ?></td>
                    <td class="px-6 py-3 text-sm <?= $abaixo ? 'text-amber-800 font-semibold' : 'text-gray-700' ?>">
                        <?= isset($comp['media']) && $comp['media'] !== null ? number_format((float) $comp['media'], 1, ',', '.') : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-1">Deliberação</h3>
        <p class="text-sm text-gray-500 mb-4">Registra a decisão colegiada. A nota original do evento de prova permanece intacta.</p>
        <?php if ($podeRegistrar): ?>
        <form method="POST" action="<?= URL ?>/admin/conselhos/<?= $sid ?>/deliberar">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token) ?>">
            <input type="hidden" name="aluno_id" value="<?= $alunoId ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Decisão <span class="text-red-500">*</span></label>
                <select name="resultado_decisao" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione</option>
                    <?php foreach (ConselhoSessao::RESULTADOS as $valor => $rotulo): ?>
                        <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Justificativa <span class="text-red-500">*</span></label>
                <textarea name="justificativa" rows="3" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Registrar deliberação</button>
            </div>
        </form>
        <?php else: ?>
        <p class="text-sm text-gray-500">Coloque o Conselho em andamento (ou reabra) para deliberar.</p>
        <?php endif; ?>

        <div class="mt-6 border-t border-gray-100 pt-4 space-y-3">
            <?php if ($deliberacoes === []): ?>
                <p class="text-sm text-gray-400">Nenhuma deliberação ainda.</p>
            <?php else: ?>
                <?php foreach ($deliberacoes as $d): ?>
                <div class="text-sm border border-gray-100 rounded-lg p-3">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars(ConselhoSessao::RESULTADOS[$d['resultado_decisao']] ?? $d['resultado_decisao']) ?></div>
                    <div class="text-xs text-gray-500 mt-1">Antes: <?= htmlspecialchars((string) $d['resultado_anterior']) ?> · <?= date('d/m/Y H:i', strtotime((string) $d['created_at'])) ?></div>
                    <p class="text-gray-700 mt-2 whitespace-pre-wrap"><?= htmlspecialchars((string) $d['justificativa']) ?></p>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-1">Encaminhamentos</h3>
        <p class="text-sm text-gray-500 mb-4">Pode gerar uma ocorrência pedagógica vinculada, sem alterar nota ou falta.</p>
        <?php if ($podeRegistrar): ?>
        <form method="POST" action="<?= URL ?>/admin/conselhos/<?= $sid ?>/encaminhar">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token) ?>">
            <input type="hidden" name="aluno_id" value="<?= $alunoId ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo <span class="text-red-500">*</span></label>
                <select name="tipo" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione</option>
                    <?php foreach (ConselhoSessao::ENCAMINHAMENTOS as $valor => $rotulo): ?>
                        <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Detalhe <span class="text-red-500">*</span></label>
                <textarea name="detalhe" rows="3" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
            </div>
            <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('ocorrencias')): ?>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 mb-4">
                <input type="checkbox" name="gerar_ocorrencia" value="1" class="rounded border-gray-300">
                Criar ocorrência pedagógica vinculada
            </label>
            <?php endif; ?>
            <div class="flex justify-end">
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Registrar encaminhamento</button>
            </div>
        </form>
        <?php endif; ?>

        <div class="mt-6 border-t border-gray-100 pt-4 space-y-3">
            <?php if ($encaminhamentos === []): ?>
                <p class="text-sm text-gray-400">Nenhum encaminhamento.</p>
            <?php else: ?>
                <?php foreach ($encaminhamentos as $e): ?>
                <div class="text-sm border border-gray-100 rounded-lg p-3">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars(ConselhoSessao::ENCAMINHAMENTOS[$e['tipo']] ?? $e['tipo']) ?></div>
                    <p class="text-gray-700 mt-1 whitespace-pre-wrap"><?= htmlspecialchars((string) $e['detalhe']) ?></p>
                    <?php if (!empty($e['ocorrencia_id']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('ocorrencias'))): ?>
                        <a href="<?= URL ?>/admin/ocorrencias/<?= (int) $e['ocorrencia_id'] ?>" class="text-xs text-purple-700 hover:underline mt-1 inline-block">Ver ocorrência</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-3">Observações dos professores</h3>
        <?php if ($observacoes === []): ?>
            <p class="text-sm text-gray-400">Nenhuma observação neste Conselho.</p>
        <?php else: ?>
            <ul class="space-y-3">
                <?php foreach ($observacoes as $obs): ?>
                <li class="text-sm">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars((string) ($obs['professor_nome'] ?? 'Professor')) ?></div>
                    <p class="text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars((string) $obs['texto']) ?></p>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('ocorrencias')): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-3">Ocorrências do aluno</h3>
        <?php if ($ocorrencias === []): ?>
            <p class="text-sm text-gray-400">Nenhuma ocorrência cadastrada.</p>
        <?php else: ?>
            <ul class="space-y-2">
                <?php foreach (array_slice($ocorrencias, 0, 8) as $oc): ?>
                <li class="text-sm">
                    <a href="<?= URL ?>/admin/ocorrencias/<?= (int) $oc['id'] ?>" class="font-medium text-gray-900 hover:underline"><?= htmlspecialchars((string) ($oc['titulo'] ?? '')) ?></a>
                    <span class="text-xs text-gray-500"> · <?= date('d/m/Y', strtotime((string) ($oc['data_ocorrencia'] ?? 'now'))) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
