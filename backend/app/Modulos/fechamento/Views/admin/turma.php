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
$especiais = is_array($especiais ?? null) ? $especiais : [];
$componentes = is_array($componentes ?? null) ? $componentes : [];
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
$page_header_subtitle = ($periodo['label'] ?? 'Ano letivo') . ' / ' . $anoLetivo;
ob_start();
?>
<a href="<?= URL ?>/admin/fechamento?<?= htmlspecialchars($qs) ?>" class="text-gray-600 hover:text-gray-900 text-sm">← Voltar</a>
<a href="<?= URL ?>/admin/resultados-finais/turma/<?= $turmaId ?>/ata?<?= htmlspecialchars($qs) ?>"
   class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
    <i class="fa-solid fa-file-lines mr-2"></i> Ata
</a>
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

<div id="frequencia" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <div class="text-xs text-gray-500 uppercase tracking-wide">Estado do período</div>
        <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-xs font-medium <?= $statusBadge($statusPeriodo) ?>">
            <?= htmlspecialchars(FechamentoMaquinaEstados::rotulo($statusPeriodo)) ?>
        </span>
        <?php if (!empty($fechamento['periodo_ref'])): ?>
            <span class="ml-2 text-xs text-gray-500"><?= htmlspecialchars((string) $fechamento['periodo_ref']) ?></span>
        <?php endif; ?>
        <?php if ($travado): ?>
            <p class="text-xs text-amber-800 mt-2">Notas, faltas e boletim travados. Para alterar, retifique com justificativa.</p>
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

<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <?php
    $cards = [
        ['Alunos', (int) ($resumo['total'] ?? 0), 'text-gray-900', 'alunos'],
        ['Homologados', (int) ($resumo['homologados'] ?? 0), 'text-green-700', ''],
        ['Aprovados', (int) ($resumo['aprovados'] ?? 0), 'text-emerald-700', ''],
        ['Reprovados', (int) ($resumo['reprovados'] ?? 0), 'text-rose-700', ''],
        ['Pendências', (int) ($resumo['pendencias'] ?? 0), 'text-gray-900', ''],
    ];
    foreach ($cards as [$lab, $val, $cls, $anchor]):
    ?>
    <div <?= $anchor !== '' ? 'id="' . htmlspecialchars($anchor) . '"' : '' ?> class="rounded-xl border border-gray-200 bg-white p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide"><?= htmlspecialchars($lab) ?></div>
        <div class="mt-1 text-2xl font-semibold <?= htmlspecialchars($cls) ?>"><?= $val ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php if (!$travado): ?>
<p class="text-sm text-gray-600 mb-6">
    Chamadas pendentes: <span class="font-medium text-gray-900"><?= (int) ($resumo['chamadas_pendentes'] ?? 0) ?></span>
    · Recuperação: <span id="recuperacao" class="font-medium text-gray-900"><?= (int) ($resumo['recuperacao'] ?? 0) ?></span>
</p>
<?php else: ?>
<span id="recuperacao" class="hidden"></span>
<?php endif; ?>

<?php
$fechHomologarQs = http_build_query([
    'ano_letivo' => (int) $anoLetivo,
    'periodo_tipo' => $periodoTipo,
    'periodo_numero' => (int) $periodoNumero,
]);
?>
<form method="POST" action="<?= URL ?>/admin/fechamento/turma/<?= $turmaId ?>/homologar?<?= htmlspecialchars($fechHomologarQs) ?>" class="bg-white rounded-xl shadow-sm border border-gray-200 mb-8"
      id="fech-form-homologar"
      onsubmit="return window.fechIniciarHomologacao(this);">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="ano_letivo" value="<?= (int) $anoLetivo ?>">
    <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo) ?>">
    <input type="hidden" name="periodo_numero" value="<?= (int) $periodoNumero ?>">
    <input type="hidden" name="homologar_todos" id="fech-homologar-todos" value="">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <?php $colunas = $travado ? 7 : 8; ?>
            <thead class="bg-gray-50">
                <tr>
                    <?php if (!$travado): ?>
                    <th class="px-3 py-3"><input type="checkbox" id="fech-check-all" class="rounded border-gray-300"></th>
                    <?php endif; ?>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notas</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Frequência</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conselho</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resultado</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pendência</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($linhas === []): ?>
                <tr><td colspan="<?= $colunas ?>" class="px-6 py-12 text-center text-gray-500">Nenhum aluno nesta turma.</td></tr>
                <?php else: foreach ($linhas as $idx => $linha):
                    $aluno = $linha['aluno'] ?? [];
                    $aid = (int) ($aluno['id'] ?? 0);
                    $statusAluno = (string) ($linha['status'] ?? 'em_andamento');
                    $criticas = !empty($linha['pendencias_criticas']);
                    $freq = $linha['frequencia']['percentual'] ?? null;
                    $freqTxt = is_numeric($freq) ? number_format((float) $freq, 1, ',', '.') . '%' : '—';
                    $conselhoLinha = is_array($linha['conselho'] ?? null) ? $linha['conselho'] : [];
                    $qsAluno = $qs . '&turma_id=' . $turmaId;
                    $podeMarcar = $statusAluno !== 'homologado' && !$criticas && !$travado;
                ?>
                <tr class="hover:bg-gray-50 <?= !empty($aluno['transferido']) ? 'opacity-70' : '' ?>">
                    <?php if (!$travado): ?>
                    <td class="px-3 py-3">
                        <?php if ($podeMarcar): ?>
                        <input type="checkbox" name="aluno_ids[]" value="<?= $aid ?>" class="fech-check rounded border-gray-300">
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900"><?= htmlspecialchars((string) ($aluno['nome'] ?? '')) ?></div>
                        <div class="text-xs text-gray-500">
                            <?= htmlspecialchars((string) ($aluno['ra'] ?? '')) ?>
                            <?php if (!empty($aluno['transferido'])): ?> · transferido<?php endif; ?>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php if (!empty($linha['notas_completas'])): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">OK</span>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Incompletas</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($freqTxt) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) ($conselhoLinha['resultado'] ?? '—')) ?></td>
                    <td class="px-4 py-3">
                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($linha['rotulo'] ?? '—')) ?></div>
                        <?php if (!$travado): ?>
                        <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-xs font-medium <?= $statusAluno === 'homologado' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?>">
                            <?= htmlspecialchars(ResultadoAcademico::STATUS[$statusAluno] ?? $statusAluno) ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600">
                        <?= !empty($linha['pendencias']) ? htmlspecialchars(implode(', ', $linha['pendencias'])) : '—' ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?php ob_start(); ?>
                        <button type="button"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                onclick="document.getElementById('conta-<?= $idx ?>').classList.toggle('hidden')">
                            <i class="fa-solid fa-list text-gray-400 w-4 text-center"></i> Disciplinas
                        </button>
                        <a href="<?= URL ?>/admin/resultados-finais/aluno/<?= $aid ?>/ficha?<?= htmlspecialchars($qsAluno) ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-id-card text-gray-400 w-4 text-center"></i> Ficha
                        </a>
                        <a href="<?= URL ?>/admin/students/<?= $aid ?>/historico-escolar"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-scroll text-gray-400 w-4 text-center"></i> Histórico
                        </a>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-fech-al-' . $aid;
                        include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <tr id="conta-<?= $idx ?>" class="hidden bg-slate-50">
                    <td colspan="<?= $colunas ?>" class="px-6 py-4">
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
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$travado): ?>
    <div class="px-6 py-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-gray-500">Homologar grava o resultado oficial. Recuperação e exame final ficam de fora até o resultado ser definitivo.</p>
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
            <button type="submit"
                    class="btn-primary-custom px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm hover:opacity-90"
                    onclick="var f=document.getElementById('fech-form-homologar'); var h=document.getElementById('fech-homologar-todos'); if(h) h.value=''; if(f) f.dataset.confirmMsg='Homologar só os alunos marcados?';">
                Homologar selecionados
            </button>
            <button type="submit"
                    class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    onclick="var f=document.getElementById('fech-form-homologar'); var h=document.getElementById('fech-homologar-todos'); if(h) h.value='1'; if(f) f.dataset.confirmMsg='Homologar todos os alunos elegíveis? Quem estiver em recuperação ou exame final não entra até ter resultado definitivo.';">
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
    <?php endif; ?>
</form>

<?php if ($especiais !== [] || !$travado): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-1">Dispensa, aproveitamento e dependência</h3>
    <p class="text-sm text-gray-500 mb-4">Situação que não é nota. O fechamento não inventa média para componente dispensado.</p>
    <?php if ($especiais !== []): ?>
    <div class="overflow-x-auto mb-6">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Componente</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Obs.</th>
                    <?php if (!$travado): ?><th class="px-4 py-2"></th><?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($especiais as $esp): ?>
                <tr>
                    <td class="px-4 py-2"><?= htmlspecialchars((string) ($esp['aluno_nome'] ?? '')) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars(ResultadoAcademico::ESPECIAIS[$esp['tipo'] ?? ''] ?? (string) ($esp['tipo'] ?? '')) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars((string) ($esp['materia_nome'] ?? 'Geral')) ?></td>
                    <td class="px-4 py-2 text-gray-500"><?= htmlspecialchars((string) ($esp['observacao'] ?? '')) ?></td>
                    <?php if (!$travado): ?>
                    <td class="px-4 py-2 text-right">
                        <form method="POST" action="<?= URL ?>/admin/resultados-finais/turma/<?= $turmaId ?>/especial/<?= (int) $esp['id'] ?>/excluir"
                              onsubmit="return confirm('Remover esta situação?')">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
                            <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo) ?>">
                            <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
                            <button type="submit" class="text-sm text-red-600 hover:underline">Excluir</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php if (!$travado): ?>
    <form method="POST" action="<?= URL ?>/admin/resultados-finais/turma/<?= $turmaId ?>/especial" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
        <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo) ?>">
        <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Aluno</label>
            <select name="aluno_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <option value="">Selecione</option>
                <?php foreach ($linhas as $linhaEsp): ?>
                    <option value="<?= (int) ($linhaEsp['aluno']['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($linhaEsp['aluno']['nome'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
            <select name="tipo" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <?php foreach (ResultadoAcademico::ESPECIAIS as $cod => $lab): ?>
                    <option value="<?= htmlspecialchars($cod) ?>"><?= htmlspecialchars($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Componente</label>
            <select name="materia_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <option value="0">Geral</option>
                <?php foreach ($componentes as $c): ?>
                    <option value="<?= (int) ($c['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($c['nome'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Observação</label>
            <input type="text" name="observacao" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" maxlength="255">
        </div>
        <div class="md:col-span-2 flex justify-end">
            <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-lg text-sm font-semibold">Registrar</button>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

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

<div id="fech-homologar-overlay" class="hidden fixed inset-0 z-[9999] bg-slate-900/60 flex items-center justify-center p-4" aria-live="polite" aria-busy="true">
    <div class="bg-white rounded-xl shadow-xl px-6 py-5 max-w-sm w-full text-center">
        <i class="fa-solid fa-spinner fa-spin text-2xl text-indigo-600 mb-3" aria-hidden="true"></i>
        <p class="text-base font-semibold text-gray-900">Homologando…</p>
        <p class="text-sm text-gray-500 mt-1">Gravando o resultado oficial. Aguarde sem fechar a página.</p>
    </div>
</div>

<script>
document.getElementById('fech-check-all')?.addEventListener('change', function () {
    document.querySelectorAll('.fech-check').forEach(function (el) { el.checked = this.checked; }, this);
});

window.fechIniciarHomologacao = function (form) {
    if (!form || form.dataset.fechEnviando === '1') {
        return false;
    }
    var msg = form.dataset.confirmMsg
        || 'Homologar os alunos elegíveis? Quem estiver em recuperação ou exame final não entra no snapshot até ter resultado definitivo (aprovado ou reprovado).';
    if (!window.confirm(msg)) {
        return false;
    }
    form.dataset.fechEnviando = '1';
    var overlay = document.getElementById('fech-homologar-overlay');
    if (overlay) {
        overlay.classList.remove('hidden');
    }
    form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
        btn.disabled = true;
        btn.classList.add('opacity-60', 'cursor-wait');
    });
    return true;
};
</script>
