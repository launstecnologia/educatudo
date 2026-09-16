<?php
require_once __DIR__ . '/../../../../Models/Education/ResultadoAcademico.php';
require_once __DIR__ . '/../../Services/FechamentoMaquinaEstados.php';
require_once __DIR__ . '/../../Services/FechamentoGates.php';

$preview = is_array($preview ?? null) ? $preview : [];
$turma = is_array($preview['turma'] ?? null) ? $preview['turma'] : [];
$periodo = is_array($preview['periodo'] ?? null) ? $preview['periodo'] : [];
$linhas = is_array($preview['linhas'] ?? null) ? $preview['linhas'] : [];
$resumo = is_array($preview['resumo'] ?? null) ? $preview['resumo'] : [];
$fechamento = is_array($fechamento ?? null) ? $fechamento : null;
$historico = is_array($historico ?? null) ? $historico : [];
$anoLetivo = (int) ($ano_letivo ?? ($periodo['ano_letivo'] ?? date('Y')));
$periodoTipo = (string) ($periodo_tipo ?? ($periodo['tipo'] ?? 'ano'));
$periodoNumero = (int) ($periodo_numero ?? ($periodo['numero'] ?? 0));
$turmaId = (int) ($turma['id'] ?? 0);
$qs = http_build_query(['ano_letivo' => $anoLetivo, 'periodo_tipo' => $periodoTipo, 'periodo_numero' => $periodoNumero]);
$csrf_token = $csrf_token ?? '';
$statusPeriodo = FechamentoMaquinaEstados::normalizar((string) ($fechamento['status'] ?? FechamentoMaquinaEstados::ABERTO));
$travado = FechamentoMaquinaEstados::estaTravado($statusPeriodo);
$errosIniciar = FechamentoGates::errosIniciarFechamento($resumo);
$podeIniciar = $statusPeriodo === FechamentoMaquinaEstados::ABERTO && $errosIniciar === [];
$motivoIniciar = $errosIniciar[0] ?? '';

$page_header_title = 'Fechamento · ' . (string) ($turma['nome'] ?? 'Turma');
$page_header_subtitle = ($periodo['label'] ?? 'Ano letivo') . ' / ' . $anoLetivo . ' — conferência por aluno e disciplina antes de homologar.';
ob_start();
?>
<a href="<?= URL ?>/admin/fechamento?<?= htmlspecialchars($qs) ?>" class="text-gray-600 hover:text-gray-900 text-sm">← Voltar</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';

$filtros_mostrar_turma = false;
$filtros_action = URL . '/admin/fechamento/turma/' . $turmaId;
include __DIR__ . '/../../../../Views/admin/resultados-finais/_filtros.php';

$statusBadge = static function (string $status): string {
    return match (FechamentoMaquinaEstados::normalizar($status)) {
        FechamentoMaquinaEstados::HOMOLOGADO, 'HOMOLOGADO' => 'bg-green-100 text-green-700',
        'homologado' => 'bg-green-100 text-green-700',
        FechamentoMaquinaEstados::RETIFICADO => 'bg-purple-100 text-purple-700',
        FechamentoMaquinaEstados::EM_FECHAMENTO => 'bg-sky-100 text-sky-800',
        FechamentoMaquinaEstados::EM_RECUPERACAO => 'bg-amber-100 text-amber-800',
        default => 'bg-gray-100 text-gray-600',
    };
};
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <div class="text-xs text-gray-500 uppercase tracking-wide">Estado do período</div>
        <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-xs font-medium <?= $statusBadge($statusPeriodo) ?>">
            <?= htmlspecialchars(FechamentoMaquinaEstados::rotulo($statusPeriodo)) ?>
        </span>
        <?php if (!empty($fechamento['periodo_ref'])): ?>
            <span class="ml-2 text-xs text-gray-500"><?= htmlspecialchars((string) $fechamento['periodo_ref']) ?></span>
        <?php endif; ?>
        <?php if ($travado): ?>
            <p class="text-xs text-amber-800 mt-2">Período homologado: notas, faltas e boletim oficial estão travados. Só retificação com justificativa reabre o fluxo oficial.</p>
        <?php elseif ($statusPeriodo === FechamentoMaquinaEstados::ABERTO && $motivoIniciar !== ''): ?>
            <p class="text-xs text-amber-800 mt-2">Conferir a turma não inicia o fechamento. <?= htmlspecialchars($motivoIniciar) ?></p>
        <?php elseif ($statusPeriodo === FechamentoMaquinaEstados::EM_FECHAMENTO && $motivoIniciar !== ''): ?>
            <p class="text-xs text-amber-800 mt-2"><?= htmlspecialchars($motivoIniciar) ?> Use Voltar para aberto se o fechamento foi iniciado com pendência.</p>
        <?php endif; ?>
    </div>
    <div class="flex flex-wrap gap-2">
        <?php if ($podeIniciar): ?>
        <form method="POST" action="<?= URL ?>/admin/fechamento/turma/<?= $turmaId ?>/iniciar">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
            <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo) ?>">
            <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
            <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Iniciar fechamento</button>
        </form>
        <?php elseif ($statusPeriodo === FechamentoMaquinaEstados::ABERTO): ?>
        <span class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed" title="<?= htmlspecialchars($motivoIniciar, ENT_QUOTES, 'UTF-8') ?>">Iniciar fechamento</span>
        <?php endif; ?>
        <?php if ($statusPeriodo === FechamentoMaquinaEstados::EM_FECHAMENTO): ?>
        <form method="POST" action="<?= URL ?>/admin/fechamento/turma/<?= $turmaId ?>/reabrir">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
            <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo) ?>">
            <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
            <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Voltar para aberto</button>
        </form>
        <?php endif; ?>
        <?php if ($travado): ?>
        <button type="button" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100"
                onclick="document.getElementById('fech-retificar').classList.remove('hidden')">
            Retificar período
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($travado): ?>
<form id="fech-retificar" method="POST" action="<?= URL ?>/admin/fechamento/turma/<?= $turmaId ?>/retificar"
      class="hidden mb-6 bg-amber-50 border border-amber-200 rounded-xl p-4">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
    <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo) ?>">
    <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
    <p class="text-sm font-medium text-amber-900 mb-2">Retificação versionada — o snapshot homologado permanece no livro de registros.</p>
    <textarea name="justificativa" required rows="3" class="w-full border border-amber-300 rounded-lg px-3 py-2 text-sm mb-2" placeholder="Justificativa obrigatória da retificação"></textarea>
    <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100">Confirmar retificação</button>
</form>
<?php endif; ?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php
    $cards = [
        ['Alunos', (string) (int) ($resumo['total'] ?? 0), 'alunos'],
        ['Chamadas pendentes', $travado ? 'Encerrado' : (string) (int) ($resumo['chamadas_pendentes'] ?? 0), ''],
        ['Pendências', (string) (int) ($resumo['pendencias'] ?? 0), ''],
        ['Recuperação', (string) (int) ($resumo['recuperacao'] ?? 0), 'recuperacao'],
    ];
    foreach ($cards as [$lab, $val, $anchor]):
    ?>
    <div <?= $anchor !== '' ? 'id="' . htmlspecialchars($anchor) . '"' : '' ?> class="rounded-xl border border-gray-200 bg-white p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide"><?= htmlspecialchars($lab) ?></div>
        <div class="mt-1 text-2xl font-semibold text-gray-900"><?= htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <?php endforeach; ?>
</div>

<form method="POST" action="<?= URL ?>/admin/fechamento/turma/<?= $turmaId ?>/homologar" class="bg-white rounded-xl shadow-sm border border-gray-200 mb-8"
      onsubmit="return confirm('Homologar os alunos elegíveis? Quem estiver em recuperação ou exame final não entra no snapshot até ter resultado definitivo (aprovado ou reprovado).');">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
    <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo) ?>">
    <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-3"><input type="checkbox" id="fech-check-all" class="rounded border-gray-300" <?= $travado ? 'disabled' : '' ?>></th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Regra</th>
                    <th id="frequencia" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Frequência</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resultado</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pendência</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Conta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($linhas === []): ?>
                <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">Nenhum aluno nesta turma.</td></tr>
                <?php else: foreach ($linhas as $idx => $linha):
                    $aluno = $linha['aluno'] ?? [];
                    $aid = (int) ($aluno['id'] ?? 0);
                    $statusAluno = (string) ($linha['status'] ?? 'em_andamento');
                    $criticas = !empty($linha['pendencias_criticas']);
                    $freq = $linha['frequencia']['percentual'] ?? null;
                    $freqTxt = is_numeric($freq) ? number_format((float) $freq, 1, ',', '.') . '%' : '—';
                    $regraNome = (string) ($linha['regra']['nome'] ?? '');
                    $qsAluno = $qs . '&turma_id=' . $turmaId;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-3">
                        <?php if ($statusAluno !== 'homologado' && !$criticas && !$travado): ?>
                        <input type="checkbox" name="aluno_ids[]" value="<?= $aid ?>" class="fech-check rounded border-gray-300">
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900"><?= htmlspecialchars((string) ($aluno['nome'] ?? '')) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars((string) ($aluno['ra'] ?? '')) ?></div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                        <?= $regraNome !== '' ? htmlspecialchars($regraNome) : '—' ?>
                        <?php if (!empty($linha['regra']['versao'])): ?>
                            <div class="text-xs text-gray-500">v<?= (int) $linha['regra']['versao'] ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($freqTxt) ?></td>
                    <td class="px-4 py-3">
                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($linha['rotulo'] ?? '—')) ?></div>
                        <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-xs font-medium <?= $statusAluno === 'homologado' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?>">
                            <?= htmlspecialchars(ResultadoAcademico::STATUS[$statusAluno] ?? $statusAluno) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600">
                        <?= !empty($linha['pendencias']) ? htmlspecialchars(implode(', ', $linha['pendencias'])) : '—' ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button type="button"
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100"
                                onclick="document.getElementById('conta-<?= $idx ?>').classList.toggle('hidden')">
                            Ver a conta
                        </button>
                    </td>
                </tr>
                <tr id="conta-<?= $idx ?>" class="hidden bg-slate-50">
                    <td colspan="7" class="px-6 py-4">
                        <?php
                        $regra = is_array($linha['regra'] ?? null) ? $linha['regra'] : [];
                        $comps = is_array($linha['componentes'] ?? null) ? $linha['componentes'] : [];
                        $conselho = is_array($linha['conselho'] ?? null) ? $linha['conselho'] : [];
                        ?>
                        <div class="text-xs text-gray-500 mb-2">
                            Regra: <?= htmlspecialchars((string) ($regra['nome'] ?? 'fallback do boletim')) ?>
                            <?php if (isset($regra['media_minima'])): ?> · mínima <?= htmlspecialchars((string) $regra['media_minima']) ?><?php endif; ?>
                            <?php if (isset($regra['frequencia_minima'])): ?> · freq. mín. <?= htmlspecialchars((string) $regra['frequencia_minima']) ?>%<?php endif; ?>
                            · conselho: <?= htmlspecialchars((string) ($conselho['resultado'] ?? '—')) ?>
                            <?php if (!empty($aluno['transferido'])): ?> · transferência<?php endif; ?>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs text-gray-500 uppercase">
                                        <th class="pr-4 py-1">Disciplina</th>
                                        <th class="pr-4 py-1">Média</th>
                                        <th class="pr-4 py-1">Recuperação</th>
                                        <th class="pr-4 py-1">Final</th>
                                        <th class="pr-4 py-1">Frequência</th>
                                        <th class="pr-4 py-1">Situação</th>
                                        <th class="py-1">Exceção</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($comps === []): ?>
                                    <tr><td colspan="7" class="py-2 text-gray-500">Sem componentes neste período.</td></tr>
                                    <?php else: foreach ($comps as $comp): ?>
                                    <tr>
                                        <td class="pr-4 py-1"><?= htmlspecialchars((string) ($comp['materia_nome'] ?? '')) ?></td>
                                        <td class="pr-4 py-1"><?= isset($comp['media']) && is_numeric($comp['media']) ? number_format((float) $comp['media'], 1, ',', '.') : '—' ?></td>
                                        <td class="pr-4 py-1"><?= isset($comp['recuperacao']) && is_numeric($comp['recuperacao']) ? number_format((float) $comp['recuperacao'], 1, ',', '.') : '—' ?></td>
                                        <td class="pr-4 py-1"><?= isset($comp['media_final']) && is_numeric($comp['media_final']) ? number_format((float) $comp['media_final'], 1, ',', '.') : '—' ?></td>
                                        <td class="pr-4 py-1"><?= isset($comp['frequencia_percentual']) && is_numeric($comp['frequencia_percentual']) ? number_format((float) $comp['frequencia_percentual'], 1, ',', '.') . '%' : '—' ?></td>
                                        <td class="pr-4 py-1"><?= htmlspecialchars((string) ($comp['rotulo'] ?? $comp['situacao'] ?? '—')) ?></td>
                                        <td class="py-1"><?= htmlspecialchars((string) ($comp['situacao_especial'] ?? '—')) ?></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a href="<?= URL ?>/admin/resultados-finais/aluno/<?= $aid ?>/ficha?<?= htmlspecialchars($qsAluno) ?>" class="text-xs text-accent underline">Ficha</a>
                            <a href="<?= URL ?>/admin/resultados-finais/aluno/<?= $aid ?>/boletim/pdf?<?= htmlspecialchars($qsAluno) ?>" target="_blank" rel="noopener" class="text-xs text-accent underline">Boletim (rascunho se não homologado)</a>
                            <a href="<?= URL ?>/admin/students/<?= $aid ?>/historico-escolar" class="text-xs text-accent underline">Histórico escolar</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-gray-500">Homologação grava o snapshot oficial. Aluno em recuperação ou exame final não fecha — só aprovado ou reprovado. Período homologado não reabre: só retifica.</p>
        <?php
        $pendTurma = (int) ($resumo['pendencias'] ?? 0);
        $chamadasTurma = (int) ($resumo['chamadas_pendentes'] ?? 0);
        $recTurma = (int) ($resumo['recuperacao'] ?? 0);
        $elegiveisTurma = (int) ($resumo['elegiveis'] ?? 0);
        $podeHomologarTurma = !$travado && $chamadasTurma === 0 && $elegiveisTurma > 0;
        $motivoHomologarTurma = $chamadasTurma > 0
            ? FechamentoGates::mensagemChamadasPendentes($chamadasTurma)
            : ($recTurma > 0 && $elegiveisTurma === 0
                ? FechamentoGates::mensagemAlunosEmRecuperacao($recTurma)
                : ($pendTurma > 0 && $elegiveisTurma === 0
                    ? ('Há ' . $pendTurma . ' pendência(s) crítica(s). Resolva antes de homologar.')
                    : 'Nenhum aluno elegível nesta turma.'));
        ?>
        <?php if (!$travado && $podeHomologarTurma): ?>
        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm hover:opacity-90">
                Homologar selecionados
            </button>
            <button type="submit" name="homologar_todos" value="1"
                    class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                Homologar todos os elegíveis
            </button>
        </div>
        <?php elseif (!$travado): ?>
        <span class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed"
              title="<?= htmlspecialchars($motivoHomologarTurma, ENT_QUOTES, 'UTF-8') ?>">
            Homologar — <?= htmlspecialchars($motivoHomologarTurma, ENT_QUOTES, 'UTF-8') ?>
        </span>
        <?php endif; ?>
    </div>
</form>

<?php if ($historico !== []): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">Auditoria do período</h3>
    <ul class="space-y-2 text-sm text-gray-700">
        <?php foreach ($historico as $h): ?>
        <li>
            <span class="font-medium"><?= htmlspecialchars((string) ($h['status_anterior'] ?? '—')) ?></span>
            → <span class="font-medium"><?= htmlspecialchars((string) ($h['status_novo'] ?? '')) ?></span>
            <span class="text-gray-500">· <?= htmlspecialchars((string) ($h['created_at'] ?? '')) ?></span>
            <?php if (!empty($h['usuario_nome'])): ?> · <?= htmlspecialchars((string) $h['usuario_nome']) ?><?php endif; ?>
            <?php if (!empty($h['justificativa'])): ?>
                <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars((string) $h['justificativa']) ?></div>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<script>
document.getElementById('fech-check-all')?.addEventListener('change', function () {
    document.querySelectorAll('.fech-check').forEach(function (el) { el.checked = this.checked; }, this);
});
</script>
