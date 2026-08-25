<?php
require_once __DIR__ . '/../../Models/ConselhoSessao.php';
require_once __DIR__ . '/../../Services/ConselhoService.php';

use App\Modulos\ConselhoClasse\Models\ConselhoSessao;
use App\Modulos\ConselhoClasse\Services\ConselhoService;

$sessao = is_array($sessao ?? null) ? $sessao : [];
$matriz = is_array($matriz ?? null) ? $matriz : [];
$componentes = is_array($matriz['componentes'] ?? null) ? $matriz['componentes'] : [];
$linhas = is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
$pendencias = is_array($matriz['pendencias'] ?? null) ? $matriz['pendencias'] : [];
$participantes = is_array($matriz['participantes'] ?? null) ? $matriz['participantes'] : [];
$professoresTurma = is_array($matriz['professores_turma'] ?? null) ? $matriz['professores_turma'] : [];
$csrf_token = $csrf_token ?? '';
$status = (string) ($sessao['status'] ?? '');
$sid = (int) ($sessao['id'] ?? 0);
$periodo = (int) ($sessao['bimestre'] ?? 0) . 'º Bimestre';
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
    $ui_badge_variant = ConselhoService::statusBadge($status);
    $ui_badge_label = ConselhoService::statusLabel($status);
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
    <div class="px-5 py-4 border-b border-gray-200">
        <h3 class="text-base font-semibold text-gray-900">Matriz do Conselho</h3>
        <p class="text-sm text-gray-500 mt-0.5">Médias do boletim gerado. Clique no aluno para deliberar sem alterar a nota original.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50">Aluno</th>
                    <?php foreach ($componentes as $comp): ?>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap"><?= htmlspecialchars((string) $comp['nome']) ?></th>
                    <?php endforeach; ?>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frequência</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Preliminar</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Homologado</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($linhas === []): ?>
                <tr>
                    <td colspan="<?= 4 + count($componentes) ?>" class="px-6 py-12 text-center text-gray-500">Nenhum aluno nesta turma.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($linhas as $linha):
                    $aluno = $linha['aluno'];
                    $prelim = $linha['resultado_preliminar'] ?? [];
                    $homolog = $linha['resultado_homologado'];
                    $freq = $linha['frequencia']['percentual'] ?? null;
                    $abaixo = $prelim['abaixo'] ?? [];
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm sticky left-0 bg-white">
                        <a href="<?= URL ?>/admin/conselhos/<?= $sid ?>/aluno/<?= (int) $aluno['id'] ?>" class="font-medium text-gray-900 hover:underline">
                            <?= htmlspecialchars((string) $aluno['nome']) ?>
                        </a>
                        <?php if (!empty($aluno['transferido'])): ?>
                            <span class="block text-xs text-gray-500">Transferido</span>
                        <?php endif; ?>
                    </td>
                    <?php foreach ($componentes as $comp):
                        $chave = mb_strtolower((string) $comp['nome']);
                        $cel = $linha['componentes'][$chave] ?? null;
                        $media = $cel['media'] ?? null;
                        $isAbaixo = $media !== null && in_array((string) $comp['nome'], $abaixo, true);
                    ?>
                    <td class="px-4 py-3 text-sm <?= $isAbaixo ? 'text-amber-800 font-semibold' : 'text-gray-700' ?>">
                        <?= $media !== null ? number_format((float) $media, 1, ',', '.') : '—' ?>
                    </td>
                    <?php endforeach; ?>
                    <td class="px-4 py-3 text-sm <?= ($freq !== null && $freq < 75) ? 'text-amber-800 font-semibold' : 'text-gray-700' ?>">
                        <?= $freq !== null ? number_format((float) $freq, 1, ',', '.') . '%' : '—' ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php
                        $codigo = (string) ($prelim['codigo'] ?? '');
                        $ui_badge_variant = $codigo === 'aprovado' ? 'ativo' : ($codigo === 'sem_notas' || $codigo === 'transferido' ? 'neutro' : 'pendente');
                        $ui_badge_label = (string) ($prelim['label'] ?? '—');
                        include __DIR__ . '/../../../../Views/admin/_partials/ui/badge.php';
                        ?>
                        <?php if (!empty($prelim['detalhe'])): ?>
                        <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars((string) $prelim['detalhe']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                        <?= $homolog ? htmlspecialchars(ConselhoSessao::RESULTADOS[$homolog] ?? $homolog) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

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
                                <?php foreach (ConselhoSessao::CARGOS as $valor => $rotulo): ?>
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
