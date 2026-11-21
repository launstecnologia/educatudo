<?php

use App\Modulos\EducaSync\Models\Slot;
use App\Modulos\EducaSync\Models\Versao;

$cenario = is_array($cenario ?? null) ? $cenario : [];
$versao = is_array($versao ?? null) ? $versao : [];
$metricas = is_array($metricas ?? null) ? $metricas : [];
$alocacoes = is_array($alocacoes ?? null) ? $alocacoes : [];
$simulacao = is_array($simulacao ?? null) ? $simulacao : ['success' => false];
$csrf_token = (string) ($csrf_token ?? '');
$tudicoinsDisponivel = !empty($tudicoins_disponivel);
$execucaoAtiva = is_array($execucao_ativa ?? null) ? $execucao_ativa : null;

$cenarioId = (int) ($cenario['id'] ?? 0);
$versaoId = (int) ($versao['id'] ?? 0);
$voltarUrl = URL . '/admin/educasync/' . $cenarioId;
$statusVersao = (string) ($versao['status'] ?? '');
$jaPublicada = $statusVersao === 'publicada';
$diff = is_array($simulacao['diff'] ?? null) ? $simulacao['diff'] : [];
$conflitosPublicacao = is_array($simulacao['conflitos'] ?? null) ? $simulacao['conflitos'] : [];
$sugestoesPublicacao = is_array($simulacao['sugestoes'] ?? null) ? $simulacao['sugestoes'] : [];
$chavesConflito = [];
$turmasComConflito = [];
$professoresComConflito = [];
foreach ($conflitosPublicacao as $c) {
    if (!is_array($c)) {
        continue;
    }
    $pid = (int) ($c['professor_id'] ?? 0);
    $tid = (int) ($c['turma_id'] ?? 0);
    $dia = (int) ($c['dia_semana'] ?? 0);
    $hora = substr((string) ($c['horario_de'] ?? ''), 0, 5);
    if ($pid > 0 && $dia > 0 && $hora !== '') {
        $chavesConflito[$pid . '|' . $dia . '|' . $hora] = (string) ($c['turma_externa'] ?? '?');
    }
    if ($tid > 0) {
        $turmasComConflito[$tid] = true;
    }
    if ($pid > 0) {
        $professoresComConflito[$pid] = true;
    }
}

$rotulosMetricas = [
    'aulas_alocadas' => 'Aulas alocadas',
    'janelas_total' => 'Janelas (total)',
    'tempo_ms' => 'Tempo do solver (ms)',
];

$diasFixar = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex'];
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Versão #<?= $versaoId ?></h2>
            <p class="text-gray-600">
                <?= htmlspecialchars((string) ($cenario['nome'] ?? '')) ?> ·
                gerada em <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($versao['created_at'] ?? 'now')))) ?>
            </p>
        </div>
        <a href="<?= $voltarUrl ?>" class="text-gray-600 hover:text-gray-900">← Voltar ao cenário</a>
    </div>
</div>

<?php include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php'; ?>

<div class="space-y-6">

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
            <div>
                <p class="text-sm font-semibold text-gray-900">Resumo</p>
                <p class="text-sm text-gray-500 mt-0.5">Métricas devolvidas pelo solver.</p>
            </div>
            <?php $ui_badge_variant = $jaPublicada ? 'ativo' : 'info'; $ui_badge_label = Versao::STATUS[$statusVersao] ?? $statusVersao; include __DIR__ . '/../../../../Views/admin/_partials/ui/badge.php'; ?>
        </div>
        <div class="p-5 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <p class="text-xs uppercase text-gray-500">Status do solver</p>
                <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars((string) ($versao['solver_status'] ?? '—')) ?></p>
            </div>
            <?php foreach ($metricas as $chave => $valor): if (!is_scalar($valor)) { continue; } ?>
            <div>
                <p class="text-xs uppercase text-gray-500"><?= htmlspecialchars($rotulosMetricas[$chave] ?? str_replace('_', ' ', (string) $chave)) ?></p>
                <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars((string) $valor) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="border-b border-gray-200 bg-gray-50 px-5 py-4">
            <p class="text-sm font-semibold text-gray-900">Publicar na Grade Horária</p>
            <p class="text-sm text-gray-500 mt-0.5">Substitui as aulas das turmas desta versão. Aulas idênticas às atuais são mantidas.</p>
        </div>
        <div class="p-5">
            <?php if (empty($simulacao['success'])): ?>
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                <p class="text-sm font-semibold text-red-800">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                    <?= htmlspecialchars((string) ($simulacao['error'] ?? 'Esta versão não pode ser publicada.')) ?>
                </p>
                <?php if ($conflitosPublicacao !== []): ?>
                <p class="mt-2 text-xs text-red-700">As aulas em conflito estão marcadas em vermelho na grade abaixo.</p>
                <ul class="mt-3 max-h-40 overflow-y-auto space-y-1.5 text-sm text-red-800">
                    <?php foreach (array_slice($conflitosPublicacao, 0, 20) as $c): ?>
                    <li class="flex items-start gap-2">
                        <span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-red-500 flex-shrink-0"></span>
                        <span><?= htmlspecialchars((string) ($c['rotulo'] ?? '')) ?></span>
                    </li>
                    <?php endforeach; ?>
                    <?php if (count($conflitosPublicacao) > 20): ?>
                    <li class="text-xs text-red-600">… e mais <?= count($conflitosPublicacao) - 20 ?> conflito(s).</li>
                    <?php endif; ?>
                </ul>
                <?php endif; ?>
                <?php if ($sugestoesPublicacao !== []): ?>
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3">
                    <p class="text-sm font-semibold text-amber-900">
                        <i class="fa-solid fa-lightbulb mr-1"></i>O que fazer
                    </p>
                    <ul class="mt-2 space-y-1.5 text-sm text-amber-900">
                        <?php foreach ($sugestoesPublicacao as $sugestao): ?>
                        <li class="flex items-start gap-2">
                            <span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-amber-500 flex-shrink-0"></span>
                            <span><?= htmlspecialchars((string) $sugestao) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="mt-2 text-xs text-amber-800">Quer um plano mais detalhado? Abra o chat da Tudinha e pergunte: “como resolver os conflitos de publicação?”</p>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <p class="text-sm text-gray-700 mb-4">
                Afeta <strong><?= (int) ($diff['turmas'] ?? 0) ?></strong> turma(s):
                <strong><?= (int) ($diff['inseridas'] ?? 0) ?></strong> aulas novas,
                <strong><?= (int) ($diff['removidas'] ?? 0) ?></strong> removidas,
                <strong><?= (int) ($diff['mantidas'] ?? 0) ?></strong> mantidas.
            </p>
            <form method="POST" action="<?= $voltarUrl ?>/versoes/<?= $versaoId ?>/publicar" class="flex flex-wrap items-center gap-4">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="confirmar" value="1" required>
                    Entendo que a grade atual dessas turmas será substituída.
                </label>
                <button type="submit" class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90">
                    <i class="fa-solid fa-cloud-arrow-up mr-2"></i><?= $jaPublicada ? 'Publicar novamente' : 'Publicar' ?>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="border-b border-gray-200 bg-gray-50 px-5 py-4">
            <p class="text-sm font-semibold text-gray-900">Reotimizar mantendo dias</p>
            <p class="text-sm text-gray-500 mt-0.5">Gera uma nova versão fixando as aulas dos dias marcados e otimizando o restante. Consome TudiCoins, como Gerar grade.</p>
        </div>
        <div class="p-5">
            <?php if (!$tudicoinsDisponivel): ?>
            <p class="text-sm text-amber-700">Ative os TudiCoins desta escola no Master para reotimizar.</p>
            <?php elseif ($alocacoes === []): ?>
            <p class="text-sm text-gray-500">Esta versão não tem aulas para fixar.</p>
            <?php else: ?>
            <form method="POST" action="<?= $voltarUrl ?>/versoes/<?= $versaoId ?>/reotimizar" class="space-y-4"
                  data-csp-confirm="Reotimizar a partir desta versão? Isso consome TudiCoins da escola.">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div>
                    <span class="block text-sm font-medium text-gray-700 mb-1.5">Manter aulas destes dias</span>
                    <div class="flex flex-wrap gap-4">
                        <?php foreach ($diasFixar as $num => $rotulo): ?>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="fixar_dias[]" value="<?= (int) $num ?>" <?= $num === 1 ? 'checked' : '' ?>>
                            <?= htmlspecialchars($rotulo) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="fixar_todas" value="1">
                    Fixar todas as aulas desta versão (só reavalia o que não estiver travado por conflito)
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="confirmar" value="1" required>
                    Confirmo a reotimização parcial.
                </label>
                <div>
                    <button type="submit" <?= $execucaoAtiva ? 'disabled' : '' ?>
                            class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fa-solid fa-rotate mr-2"></i>Reotimizar
                    </button>
                    <?php if ($execucaoAtiva): ?>
                    <p class="mt-2 text-sm text-blue-700">Já existe uma otimização em andamento neste cenário.</p>
                    <?php endif; ?>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $paletaCores = [
        ['bg' => 'bg-violet-100', 'text' => 'text-violet-900', 'border' => 'border-violet-200'],
        ['bg' => 'bg-sky-100', 'text' => 'text-sky-900', 'border' => 'border-sky-200'],
        ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-900', 'border' => 'border-emerald-200'],
        ['bg' => 'bg-amber-100', 'text' => 'text-amber-900', 'border' => 'border-amber-200'],
        ['bg' => 'bg-rose-100', 'text' => 'text-rose-900', 'border' => 'border-rose-200'],
        ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-900', 'border' => 'border-indigo-200'],
        ['bg' => 'bg-teal-100', 'text' => 'text-teal-900', 'border' => 'border-teal-200'],
        ['bg' => 'bg-orange-100', 'text' => 'text-orange-900', 'border' => 'border-orange-200'],
        ['bg' => 'bg-fuchsia-100', 'text' => 'text-fuchsia-900', 'border' => 'border-fuchsia-200'],
        ['bg' => 'bg-lime-100', 'text' => 'text-lime-900', 'border' => 'border-lime-200'],
        ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-900', 'border' => 'border-cyan-200'],
        ['bg' => 'bg-pink-100', 'text' => 'text-pink-900', 'border' => 'border-pink-200'],
    ];
    $nomeProfessorCurto = static function (string $nome): string {
        $nome = trim($nome);
        if ($nome === '') {
            return '—';
        }
        $partes = preg_split('/\s+/u', $nome) ?: [];
        if (count($partes) <= 2) {
            return $nome;
        }
        return $partes[0] . ' ' . $partes[count($partes) - 1];
    };

    $agrupar = static function (array $itens, string $chaveId, string $chaveNome, string $rotuloFallback): array {
        $grupos = [];
        foreach ($itens as $a) {
            $id = (int) ($a[$chaveId] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $grupos[$id]['nome'] = (string) ($a[$chaveNome] ?? ($rotuloFallback . ' ' . $id));
            $grupos[$id]['itens'][] = $a;
        }
        uasort($grupos, static function (array $x, array $y): int {
            return strcasecmp((string) $x['nome'], (string) $y['nome']);
        });
        return $grupos;
    };

    $alocacoesPorTurma = $agrupar($alocacoes, 'turma_id', 'turma_nome', 'Turma');
    $alocacoesPorProfessor = $agrupar($alocacoes, 'professor_id', 'professor_nome', 'Professor');
    $turmaGradeInicial = (int) (array_key_first($alocacoesPorTurma) ?: 0);
    $professorGradeInicial = (int) (array_key_first($alocacoesPorProfessor) ?: 0);

    $diasVisiveis = [];
    foreach ([1, 2, 3, 4, 5] as $num) {
        $diasVisiveis[$num] = Slot::DIAS[$num] ?? ('Dia ' . $num);
    }
    foreach ($alocacoes as $a) {
        $diaNum = (int) ($a['dia_semana'] ?? 0);
        if (($diaNum === 6 || $diaNum === 7) && isset(Slot::DIAS[$diaNum])) {
            $diasVisiveis[$diaNum] = Slot::DIAS[$diaNum];
        }
    }
    ksort($diasVisiveis);
    $colunasDias = count($diasVisiveis);

    $montarLinhasGrade = static function (array $itens) use ($diasVisiveis): array {
        $slotsMap = [];
        foreach ($itens as $item) {
            $de = substr((string) ($item['horario_de'] ?? ''), 0, 5);
            $ate = substr((string) ($item['horario_ate'] ?? ''), 0, 5);
            if ($de === '' || $ate === '') {
                continue;
            }
            $slotsMap[$de . '|' . $ate] = ['de' => $de, 'ate' => $ate];
        }
        $slots = array_values($slotsMap);
        usort($slots, static function ($a, $b) {
            return strcmp($a['de'], $b['de']) ?: strcmp($a['ate'], $b['ate']);
        });

        $linhas = [];
        $horarioAnteriorFim = null;
        foreach ($slots as $slot) {
            if ($horarioAnteriorFim !== null && strcmp($slot['de'], $horarioAnteriorFim) > 0) {
                $linhas[] = [
                    'tipo' => 'intervalo',
                    'de' => $horarioAnteriorFim,
                    'ate' => $slot['de'],
                ];
            }
            $aulasPorDia = [];
            foreach (array_keys($diasVisiveis) as $num) {
                $aulasPorDia[$num] = [];
            }
            foreach ($itens as $item) {
                $de = substr((string) ($item['horario_de'] ?? ''), 0, 5);
                $ate = substr((string) ($item['horario_ate'] ?? ''), 0, 5);
                $dia = (int) ($item['dia_semana'] ?? 0);
                if ($de === $slot['de'] && $ate === $slot['ate'] && isset($aulasPorDia[$dia])) {
                    $aulasPorDia[$dia][] = $item;
                }
            }
            $linhas[] = [
                'tipo' => 'aula',
                'de' => $slot['de'],
                'ate' => $slot['ate'],
                'aulas' => $aulasPorDia,
            ];
            $horarioAnteriorFim = $slot['ate'];
        }
        return $linhas;
    };

    $renderGradePainel = static function (
        string $modo,
        int $entidadeId,
        array $grupo,
        bool $ativo,
        bool $mostrarTurmaNoCard,
        bool $mostrarProfessorNoCard
    ) use ($montarLinhasGrade, $diasVisiveis, $colunasDias, $paletaCores, $nomeProfessorCurto, $chavesConflito): void {
        $itens = $grupo['itens'];
        $linhasGrade = $montarLinhasGrade($itens);
        $coresPorMateria = [];
        foreach ($itens as $item) {
            $materiaId = (int) ($item['materia_id'] ?? 0);
            if (!isset($coresPorMateria[$materiaId])) {
                $coresPorMateria[$materiaId] = $paletaCores[$materiaId % count($paletaCores)];
            }
        }
        $attrId = $modo === 'professor' ? 'data-professor-id' : 'data-turma-id';
        ?>
        <div class="educasync-versao-painel <?= $ativo ? '' : 'hidden' ?>" data-modo="<?= htmlspecialchars($modo) ?>" <?= $attrId ?>="<?= $entidadeId ?>">
            <div class="overflow-x-auto min-w-0">
                <?php if ($linhasGrade === []): ?>
                <p class="text-sm text-gray-500 text-center py-8">Nenhuma aula nesta visualização.</p>
                <?php else: ?>
                <table class="w-full table-fixed border-collapse min-w-[48rem]">
                    <colgroup>
                        <col style="width: 4.75rem">
                        <?php foreach ($diasVisiveis as $_diaNome): ?>
                        <col>
                        <?php endforeach; ?>
                    </colgroup>
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="sticky left-0 z-10 bg-gray-50 px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 border-b border-r border-gray-200">Horário</th>
                            <?php foreach ($diasVisiveis as $nomeDia): ?>
                            <th class="px-1.5 py-2 text-center text-xs font-semibold text-gray-800 border-b border-gray-200"><?= htmlspecialchars($nomeDia) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linhasGrade as $linha): ?>
                            <?php if (($linha['tipo'] ?? '') === 'intervalo'): ?>
                            <tr>
                                <td colspan="<?= 1 + $colunasDias ?>" class="bg-slate-50 border-y border-gray-200 py-2">
                                    <div class="flex items-center justify-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                        <span class="hidden sm:inline h-px w-16 bg-slate-200"></span>
                                        <i class="fa-solid fa-mug-saucer"></i>
                                        Intervalo
                                        <span class="font-normal normal-case tracking-normal text-slate-400">
                                            <?= htmlspecialchars((string) $linha['de']) ?>–<?= htmlspecialchars((string) $linha['ate']) ?>
                                        </span>
                                        <span class="hidden sm:inline h-px w-16 bg-slate-200"></span>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <tr class="align-top">
                                <th class="sticky left-0 z-10 bg-white px-2 py-1.5 text-left border-b border-r border-gray-100 align-top">
                                    <span class="block text-[11px] font-semibold text-gray-800 leading-tight"><?= htmlspecialchars((string) $linha['de']) ?></span>
                                    <span class="block text-[10px] text-gray-400 leading-tight"><?= htmlspecialchars((string) $linha['ate']) ?></span>
                                </th>
                                <?php foreach (array_keys($diasVisiveis) as $diaNum):
                                    $aulasCelula = $linha['aulas'][$diaNum] ?? [];
                                    ?>
                                <td class="px-1 py-1 border-b border-gray-100 align-top">
                                    <?php if ($aulasCelula === []): ?>
                                    <div class="rounded-md border border-dashed border-gray-200 bg-slate-50/60 px-1 py-2 text-center text-[10px] text-gray-400">Sem aula</div>
                                    <?php else: ?>
                                    <div class="space-y-1">
                                        <?php foreach ($aulasCelula as $aula):
                                            $chaveAula = (int) ($aula['professor_id'] ?? 0) . '|' . (int) ($aula['dia_semana'] ?? 0) . '|' . substr((string) ($aula['horario_de'] ?? ''), 0, 5);
                                            $emConflito = isset($chavesConflito[$chaveAula]);
                                            $turmaExterna = $emConflito ? (string) $chavesConflito[$chaveAula] : '';
                                            $cor = $emConflito
                                                ? ['bg' => 'bg-red-100', 'text' => 'text-red-900', 'border' => 'border-red-400']
                                                : ($coresPorMateria[(int) ($aula['materia_id'] ?? 0)] ?? $paletaCores[0]);
                                            $salaAula = trim((string) ($aula['sala_nome'] ?? ''));
                                            ?>
                                        <div class="rounded-md border <?= htmlspecialchars($cor['border']) ?> <?= htmlspecialchars($cor['bg']) ?> <?= htmlspecialchars($cor['text']) ?> px-2 py-1.5 <?= $emConflito ? 'ring-2 ring-red-500/40' : '' ?>"
                                             <?php if ($emConflito): ?>title="Conflito: professor já tem aula na turma <?= htmlspecialchars($turmaExterna) ?> neste horário"<?php endif; ?>>
                                            <?php if ($emConflito): ?>
                                            <p class="mb-0.5 text-[10px] font-bold uppercase tracking-wide text-red-700">
                                                <i class="fa-solid fa-triangle-exclamation mr-0.5"></i>Conflito · <?= htmlspecialchars($turmaExterna) ?>
                                            </p>
                                            <?php endif; ?>
                                            <p class="text-[11px] font-semibold leading-snug break-words"><?= htmlspecialchars((string) ($aula['materia_nome'] ?? '')) ?></p>
                                            <?php if ($mostrarTurmaNoCard): ?>
                                            <p class="mt-0.5 text-[11px] opacity-80"><?= htmlspecialchars((string) ($aula['turma_nome'] ?? '—')) ?></p>
                                            <?php endif; ?>
                                            <?php if ($mostrarProfessorNoCard): ?>
                                            <p class="mt-0.5 text-[11px] opacity-80"><?= htmlspecialchars($nomeProfessorCurto((string) ($aula['professor_nome'] ?? ''))) ?></p>
                                            <?php endif; ?>
                                            <?php if ($salaAula !== ''): ?>
                                            <p class="text-[11px] opacity-70"><i class="fa-solid fa-location-dot mr-0.5"></i><?= htmlspecialchars($salaAula) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    };
    ?>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden" id="educasync-versao-grade">
        <div class="border-b border-gray-200 bg-gray-50 px-5 py-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-gray-900">Grade da versão</p>
                <p class="text-sm text-gray-500 mt-0.5">
                    <?= count($alocacoes) ?> aula(s) alocada(s) ·
                    <span id="educasync-versao-modo-label">visualização por turma</span>
                </p>
            </div>
            <?php if ($alocacoes !== []): ?>
            <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5" role="group" aria-label="Modo de visualização">
                <button type="button" class="educasync-versao-modo px-3 py-1.5 rounded-md text-sm font-semibold bg-teal-600 text-white" data-modo="turma">Por turma</button>
                <button type="button" class="educasync-versao-modo px-3 py-1.5 rounded-md text-sm font-medium text-gray-600 hover:text-gray-900" data-modo="professor">Por professor</button>
            </div>
            <?php endif; ?>
        </div>
        <div class="p-5 space-y-4">
            <?php if ($alocacoes === []): ?>
            <p class="text-sm text-gray-500 text-center py-8">Esta versão não tem aulas.</p>
            <?php else: ?>

            <div class="educasync-versao-abas flex flex-wrap gap-2" data-modo="turma" role="tablist" aria-label="Turmas da versão">
                <?php foreach ($alocacoesPorTurma as $tid => $grupo):
                    $ativa = $tid === $turmaGradeInicial;
                    $temConflito = isset($turmasComConflito[(int) $tid]);
                    ?>
                <button type="button"
                        class="educasync-versao-aba inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm border focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 <?= $ativa ? 'bg-gray-900 text-white border-gray-900 font-semibold' : ($temConflito ? 'bg-red-50 text-red-800 border-red-300 hover:border-red-400' : 'bg-white text-gray-700 border-gray-200 hover:border-gray-400') ?>"
                        data-modo="turma"
                        data-id="<?= (int) $tid ?>"
                        data-conflito="<?= $temConflito ? '1' : '0' ?>"
                        aria-selected="<?= $ativa ? 'true' : 'false' ?>">
                    <?php if ($temConflito): ?><i class="fa-solid fa-triangle-exclamation text-red-600 text-xs"></i><?php endif; ?>
                    <span><?= htmlspecialchars((string) $grupo['nome']) ?></span>
                    <span class="educasync-versao-aba-badge inline-flex min-w-[1.25rem] items-center justify-center rounded-full px-1.5 py-0.5 text-xs font-medium <?= $ativa ? 'bg-white/20 text-white' : ($temConflito ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600') ?>"><?= count($grupo['itens']) ?></span>
                </button>
                <?php endforeach; ?>
            </div>

            <div class="educasync-versao-abas hidden flex-wrap gap-2" data-modo="professor" role="tablist" aria-label="Professores da versão">
                <?php foreach ($alocacoesPorProfessor as $pid => $grupo):
                    $ativa = $pid === $professorGradeInicial;
                    $temConflito = isset($professoresComConflito[(int) $pid]);
                    ?>
                <button type="button"
                        class="educasync-versao-aba inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm border focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 <?= $ativa ? 'bg-gray-900 text-white border-gray-900 font-semibold' : ($temConflito ? 'bg-red-50 text-red-800 border-red-300 hover:border-red-400' : 'bg-white text-gray-700 border-gray-200 hover:border-gray-400') ?>"
                        data-modo="professor"
                        data-id="<?= (int) $pid ?>"
                        data-conflito="<?= $temConflito ? '1' : '0' ?>"
                        aria-selected="<?= $ativa ? 'true' : 'false' ?>">
                    <?php if ($temConflito): ?><i class="fa-solid fa-triangle-exclamation text-red-600 text-xs"></i><?php endif; ?>
                    <span><?= htmlspecialchars($nomeProfessorCurto((string) $grupo['nome'])) ?></span>
                    <span class="educasync-versao-aba-badge inline-flex min-w-[1.25rem] items-center justify-center rounded-full px-1.5 py-0.5 text-xs font-medium <?= $ativa ? 'bg-white/20 text-white' : ($temConflito ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600') ?>"><?= count($grupo['itens']) ?></span>
                </button>
                <?php endforeach; ?>
            </div>

            <?php
            foreach ($alocacoesPorTurma as $tid => $grupo) {
                $renderGradePainel('turma', (int) $tid, $grupo, $tid === $turmaGradeInicial, false, true);
            }
            foreach ($alocacoesPorProfessor as $pid => $grupo) {
                $renderGradePainel('professor', (int) $pid, $grupo, false, true, false);
            }
            ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($alocacoes !== []): ?>
<script>
(function () {
    var raiz = document.getElementById('educasync-versao-grade');
    if (!raiz) { return; }
    var modos = raiz.querySelectorAll('.educasync-versao-modo');
    var abasGrupos = raiz.querySelectorAll('.educasync-versao-abas');
    var paineis = raiz.querySelectorAll('.educasync-versao-painel');
    var label = document.getElementById('educasync-versao-modo-label');
    var modoAtual = 'turma';
    var selecionados = {
        turma: '<?= (int) $turmaGradeInicial ?>',
        professor: '<?= (int) $professorGradeInicial ?>'
    };

    function estiloAba(aba, ativa) {
        var conflito = aba.getAttribute('data-conflito') === '1';
        aba.setAttribute('aria-selected', ativa ? 'true' : 'false');
        aba.className = 'educasync-versao-aba inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm border focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 '
            + (ativa
                ? 'bg-gray-900 text-white border-gray-900 font-semibold'
                : (conflito
                    ? 'bg-red-50 text-red-800 border-red-300 hover:border-red-400'
                    : 'bg-white text-gray-700 border-gray-200 hover:border-gray-400'));
        var badge = aba.querySelector('.educasync-versao-aba-badge');
        if (badge) {
            badge.className = 'educasync-versao-aba-badge inline-flex min-w-[1.25rem] items-center justify-center rounded-full px-1.5 py-0.5 text-xs font-medium '
                + (ativa ? 'bg-white/20 text-white' : (conflito ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'));
        }
    }

    function estiloModo(botao, ativo) {
        botao.className = 'educasync-versao-modo px-3 py-1.5 rounded-md text-sm '
            + (ativo ? 'font-semibold bg-teal-600 text-white' : 'font-medium text-gray-600 hover:text-gray-900');
    }

    function ativarEntidade(modo, id) {
        selecionados[modo] = String(id);
        raiz.querySelectorAll('.educasync-versao-aba[data-modo="' + modo + '"]').forEach(function (aba) {
            estiloAba(aba, aba.getAttribute('data-id') === String(id));
        });
        paineis.forEach(function (painel) {
            var mesmoModo = painel.getAttribute('data-modo') === modo;
            var mesmoId = painel.getAttribute(modo === 'professor' ? 'data-professor-id' : 'data-turma-id') === String(id);
            painel.classList.toggle('hidden', !(mesmoModo && mesmoId));
        });
    }

    function trocarModo(modo) {
        modoAtual = modo;
        if (label) {
            label.textContent = modo === 'professor' ? 'visualização por professor' : 'visualização por turma';
        }
        modos.forEach(function (botao) {
            estiloModo(botao, botao.getAttribute('data-modo') === modo);
        });
        abasGrupos.forEach(function (grupo) {
            var ativo = grupo.getAttribute('data-modo') === modo;
            grupo.classList.toggle('hidden', !ativo);
            grupo.classList.toggle('flex', ativo);
        });
        ativarEntidade(modo, selecionados[modo] || '0');
    }

    modos.forEach(function (botao) {
        botao.addEventListener('click', function () {
            trocarModo(botao.getAttribute('data-modo') || 'turma');
        });
    });

    raiz.querySelectorAll('.educasync-versao-aba').forEach(function (aba) {
        aba.addEventListener('click', function () {
            ativarEntidade(aba.getAttribute('data-modo') || modoAtual, aba.getAttribute('data-id'));
        });
    });
})();
</script>
<?php endif; ?>
