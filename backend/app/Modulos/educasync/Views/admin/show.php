<?php

use App\Modulos\EducaSync\Models\Cenario;
use App\Modulos\EducaSync\Models\Execucao;
use App\Modulos\EducaSync\Models\Slot;
use App\Modulos\EducaSync\Models\Versao;

$cenario = is_array($cenario ?? null) ? $cenario : [];
$slots = is_array($slots ?? null) ? $slots : [];
$demandas = is_array($demandas ?? null) ? $demandas : [];
$versoes = is_array($versoes ?? null) ? $versoes : [];
$execucoes = is_array($execucoes ?? null) ? $execucoes : [];
$execucaoAtiva = is_array($execucao_ativa ?? null) ? $execucao_ativa : null;
$turmas = is_array($turmas ?? null) ? $turmas : [];
$materias = is_array($materias ?? null) ? $materias : [];
$professores = is_array($professores ?? null) ? $professores : [];
$salas = is_array($salas ?? null) ? $salas : [];
$bloqueiosProfessores = is_array($bloqueios_professores ?? null) ? $bloqueios_professores : [];
$resumoBloqueios = is_array($resumo_bloqueios ?? null) ? $resumo_bloqueios : [];
$professoresSobrecarregados = is_array($professores_sobrecarregados ?? null) ? $professores_sobrecarregados : [];
$idsSobrecarregados = [];
foreach ($professoresSobrecarregados as $ps) {
    $idsSobrecarregados[(int) ($ps['professor_id'] ?? 0)] = $ps;
}
$assistenteDisponivel = !empty($assistente_disponivel);
$csrf_token = (string) ($csrf_token ?? '');
$tudicoinsDisponivel = !empty($tudicoins_disponivel);

$cenarioId = (int) ($cenario['id'] ?? 0);
$anoLetivoCenario = (int) ($cenario['ano_letivo'] ?? 0);
$base = URL . '/admin/educasync/' . $cenarioId;
$temVersoes = $versoes !== [];
$totalDemandas = count($demandas);
$anosLetivos = is_array($anos_letivos ?? null) ? $anos_letivos : [];
if ($anosLetivos === []) {
    $anosLetivos = [$anoLetivoCenario > 0 ? $anoLetivoCenario : (int) date('Y')];
}

$slotsPorDia = [];
foreach ($slots as $s) {
    $slotsPorDia[(int) $s['dia_semana']][] = $s;
}

$classeInput = 'w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent';
$classeRotulo = 'block text-sm font-medium text-gray-700 mb-1.5';
$classeFaixa = 'flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4';
$classeCirculo = 'flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white';
$classeTh = 'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
?>
<div class="mb-8">
    <div class="flex flex-wrap justify-between items-start gap-4">
        <div class="min-w-0">
            <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars((string) ($cenario['nome'] ?? 'Cenário')) ?></h2>
            <p class="text-gray-600">
                <?= htmlspecialchars(Cenario::PERIODOS[$cenario['periodo'] ?? ''] ?? '') ?> ·
                solver até <?= (int) ($cenario['max_time_s'] ?? 60) ?>s
            </p>
            <?php if ($temVersoes): ?>
            <p class="mt-2 text-sm text-gray-600">Ano letivo <strong><?= $anoLetivoCenario ?></strong> (travado: há versões geradas)</p>
            <?php else: ?>
            <form method="POST" action="<?= $base ?>/ano-letivo" class="mt-3 flex flex-wrap items-end gap-2"
                  onsubmit="var a=this.ano_letivo.value; var n=<?= (int) $totalDemandas ?>; var msg='Mudar o ano letivo para '+a+'?'; if(n>0){msg+='\n\nAs '+n+' demanda(s) atuais serão removidas (as turmas mudam com o ano).'} return confirm(msg);">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div>
                    <label for="educasync_ano_letivo" class="block text-xs font-medium text-gray-500 mb-1">Ano letivo do cenário</label>
                    <input type="number" id="educasync_ano_letivo" name="ano_letivo" min="2000" max="2100" required
                           value="<?= $anoLetivoCenario ?>"
                           class="w-28 px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <button type="submit" class="px-3 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Aplicar ano
                </button>
            </form>
            <p class="mt-1.5 text-xs text-gray-500">A importação da matriz usa as turmas ativas deste ano (ex.: 2027 = seed EducaSync).</p>
            <?php endif; ?>
        </div>
        <a href="<?= URL ?>/admin/educasync" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<?php include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php'; ?>

<div class="space-y-6">

    <!-- 1. Horários -->
    <div id="educasync-secao-horarios" class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden scroll-mt-24">
        <div class="<?= $classeFaixa ?>">
            <div class="flex items-center gap-3 min-w-0">
                <span class="<?= $classeCirculo ?>">1</span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Horários (slots)</p>
                    <p class="text-sm text-gray-500 mt-0.5">Os encaixes de aula da semana. Duração e aulas/dia vêm da Matriz Curricular; informe só o horário de início e o intervalo.</p>
                </div>
            </div>
        </div>
        <div class="p-5">
            <?php
            $sugestaoSlots = is_array($sugestao_slots ?? null) ? $sugestao_slots : [];
            $duracaoSug = (int) ($sugestaoSlots['duracao_min'] ?? 50);
            $aulasSug = (int) ($sugestaoSlots['aulas_por_dia'] ?? 5);
            $horaSug = (string) ($sugestaoSlots['hora_inicio'] ?? '07:00');
            $veioMatriz = !empty($sugestaoSlots['veio_matriz']);
            ?>
            <?php if ($temVersoes): ?>
            <p class="mb-4 text-sm text-amber-700">Este cenário já tem versões geradas: os horários ficam travados. Crie um novo cenário para alterá-los.</p>
            <?php else: ?>
            <?php if ($veioMatriz): ?>
            <p class="mb-4 text-sm text-gray-600">
                Pré-preenchido pela Matriz Curricular
                (<?= (int) ($sugestaoSlots['matrizes'] ?? 0) ?> matriz(es)):
                <strong><?= $duracaoSug ?> min</strong> por aula,
                até <strong><?= $aulasSug ?></strong> aulas/dia para caber a carga semanal.
            </p>
            <?php elseif (!empty($sugestaoSlots['aviso'])): ?>
            <p class="mb-4 text-sm text-amber-700"><?= htmlspecialchars((string) $sugestaoSlots['aviso']) ?></p>
            <?php endif; ?>
            <form method="POST" action="<?= $base ?>/slots" class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="md:col-span-6">
                    <span class="<?= $classeRotulo ?>">Dias da semana</span>
                    <div class="flex flex-wrap gap-4">
                        <?php foreach (Slot::DIAS as $num => $nome): if ($num > 6) { continue; } ?>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="dias[]" value="<?= (int) $num ?>" <?= $num <= 5 ? 'checked' : '' ?>> <?= htmlspecialchars($nome) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label for="hora_inicio" class="<?= $classeRotulo ?>">Primeira aula</label>
                    <input type="time" id="hora_inicio" name="hora_inicio" value="<?= htmlspecialchars($horaSug) ?>" required class="<?= $classeInput ?>">
                </div>
                <div>
                    <label for="duracao_min" class="<?= $classeRotulo ?>">Duração (min)<?= $veioMatriz ? ' · matriz' : '' ?></label>
                    <input type="number" id="duracao_min" name="duracao_min" min="20" max="120" value="<?= $duracaoSug ?>" required class="<?= $classeInput ?>">
                </div>
                <div>
                    <label for="aulas_por_dia" class="<?= $classeRotulo ?>">Aulas por dia<?= $veioMatriz ? ' · matriz' : '' ?></label>
                    <input type="number" id="aulas_por_dia" name="aulas_por_dia" min="1" max="12" value="<?= $aulasSug ?>" required class="<?= $classeInput ?>">
                </div>
                <div>
                    <label for="intervalo_hora" class="<?= $classeRotulo ?>">Início do intervalo</label>
                    <input type="time" id="intervalo_hora" name="intervalo_hora" value="09:40" class="<?= $classeInput ?>">
                </div>
                <div>
                    <label for="intervalo_min" class="<?= $classeRotulo ?>">Duração do intervalo (min)</label>
                    <input type="number" id="intervalo_min" name="intervalo_min" min="0" max="90" value="20" class="<?= $classeInput ?>">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="btn-primary-custom w-full px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90">Gerar horários</button>
                </div>
            </form>
            <?php endif; ?>

            <?php if ($slotsPorDia === []): ?>
            <p class="text-sm text-gray-500">Nenhum horário cadastrado.</p>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <?php foreach ($slotsPorDia as $dia => $lista): ?>
                <div class="rounded-lg border border-gray-200">
                    <p class="px-3 py-2 text-sm font-semibold text-gray-800 bg-gray-50 border-b border-gray-200 rounded-t-lg"><?= htmlspecialchars(Slot::DIAS[$dia] ?? (string) $dia) ?></p>
                    <ul class="divide-y divide-gray-100">
                        <?php foreach ($lista as $s): ?>
                        <li class="flex items-center justify-between px-3 py-1.5 text-sm text-gray-700">
                            <span><?= (int) $s['ordem'] ?>ª · <?= htmlspecialchars(substr((string) $s['horario_de'], 0, 5)) ?>–<?= htmlspecialchars(substr((string) $s['horario_ate'], 0, 5)) ?></span>
                            <?php if (!$temVersoes): ?>
                            <form method="POST" action="<?= $base ?>/slots/<?= (int) $s['id'] ?>/excluir" data-csp-confirm="Remover este horário?">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <button type="submit" class="text-gray-400 hover:text-red-600" title="Remover horário" aria-label="Remover horário">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. Demandas -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="<?= $classeFaixa ?>">
            <div class="flex items-center gap-3 min-w-0">
                <span class="<?= $classeCirculo ?>">2</span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Demandas</p>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Carga da Matriz Curricular + professor vinculado.
                        Importa as turmas ativas de <strong class="text-gray-700"><?= $anoLetivoCenario ?></strong>.
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <?php if ($temVersoes): ?>
                <div>
                    <span class="block text-xs font-medium text-gray-500 mb-1">Ano letivo</span>
                    <span class="inline-flex items-center px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-sm font-semibold text-gray-700"><?= $anoLetivoCenario ?></span>
                </div>
                <p class="text-xs text-amber-700 max-w-xs self-center">Travado: há versões geradas. Crie um cenário novo para mudar o ano.</p>
                <?php else: ?>
                <form method="POST" action="<?= $base ?>/ano-letivo" class="flex flex-wrap items-end gap-2"
                      onsubmit="var a=this.ano_letivo.value; var n=<?= (int) $totalDemandas ?>; var msg='Mudar o ano letivo para '+a+'?'; if(n>0){msg+='\n\nAs '+n+' demanda(s) atuais serão removidas (as turmas mudam com o ano).'} return confirm(msg);">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div>
                        <label for="educasync_ano_letivo_demandas" class="block text-xs font-medium text-gray-500 mb-1">Ano letivo</label>
                        <select id="educasync_ano_letivo_demandas" name="ano_letivo" required
                                class="min-w-[7rem] px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <?php foreach ($anosLetivos as $anoOpt): ?>
                            <option value="<?= (int) $anoOpt ?>" <?= (int) $anoOpt === $anoLetivoCenario ? 'selected' : '' ?>><?= (int) $anoOpt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="px-3 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Aplicar ano
                    </button>
                </form>
                <?php if ($totalDemandas > 0): ?>
                <form method="POST" action="<?= $base ?>/demandas/excluir-todas"
                      data-csp-confirm="Excluir as &lt;?= (int) $totalDemandas ?&gt; demanda(s) deste cenário de uma vez?\n\nIsso não remove horários nem versões — só a lista de matéria/professor/aulas.">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg border border-red-200 bg-white text-sm font-semibold text-red-700 hover:bg-red-50">
                        <i class="fa-solid fa-trash-can mr-2"></i>Excluir todas
                    </button>
                </form>
                <?php endif; ?>
                <form method="POST" action="<?= $base ?>/demandas/importar-matriz"
                      data-csp-confirm="Importar as demandas das matrizes das turmas ativas de &lt;?= $anoLetivoCenario ?&gt;?\n\nO professor vem do vínculo matéria no cadastro do professor (e, se houver vários, do histórico da grade). Demandas já existentes da mesma turma/matéria serão atualizadas.">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        <i class="fa-solid fa-file-import mr-2"></i>Importar da Matriz
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="p-5" id="educasync-demandas">
            <?php
            $demandasPorTurma = [];
            foreach ($demandas as $d) {
                $tid = (int) ($d['turma_id'] ?? 0);
                if ($tid <= 0) {
                    continue;
                }
                $demandasPorTurma[$tid][] = $d;
            }
            $abasTurmas = [];
            $gruposCurso = [];
            foreach ($turmas as $t) {
                $tid = (int) ($t['id'] ?? 0);
                if ($tid <= 0) {
                    continue;
                }
                $cursoNome = trim((string) ($t['curso_nome'] ?? 'Outros'));
                if ($cursoNome === '') {
                    $cursoNome = 'Outros';
                }
                $serieNome = trim((string) ($t['serie_nome'] ?? 'Sem série'));
                if ($serieNome === '') {
                    $serieNome = 'Sem série';
                }
                $abasTurmas[$tid] = [
                    'id' => $tid,
                    'nome' => (string) ($t['nome'] ?? ('Turma ' . $tid)),
                    'curso' => $cursoNome,
                    'serie' => $serieNome,
                ];
                $gruposCurso[$cursoNome][$tid] = true;
            }
            foreach ($demandasPorTurma as $tid => $listaDemanda) {
                if (!isset($abasTurmas[$tid])) {
                    $abasTurmas[$tid] = [
                        'id' => $tid,
                        'nome' => (string) ($listaDemanda[0]['turma_nome'] ?? ('Turma ' . $tid)),
                        'curso' => 'Outros',
                        'serie' => 'Sem série',
                    ];
                    $gruposCurso['Outros'][$tid] = true;
                }
            }
            $rotuloCursoCurto = static function (string $nome): string {
                $n = mb_strtolower($nome);
                if (str_contains($n, 'anos finais')) {
                    return 'Anos Finais';
                }
                if (str_contains($n, 'anos iniciais')) {
                    return 'Anos Iniciais';
                }
                if (str_contains($n, 'médio') || str_contains($n, 'medio')) {
                    return 'Ensino Médio';
                }
                return $nome;
            };
            $turmasPorCursoSerie = [];
            foreach ($abasTurmas as $tid => $aba) {
                $turmasPorCursoSerie[$aba['curso']][$aba['serie']][$tid] = $aba;
            }
            $nomesCursos = array_keys($turmasPorCursoSerie);
            $cursoInicial = (string) ($nomesCursos[0] ?? '');
            $turmaAbaInicial = 0;
            foreach ($abasTurmas as $tid => $aba) {
                if ($aba['curso'] === $cursoInicial) {
                    $turmaAbaInicial = (int) $tid;
                    break;
                }
            }
            if ($turmaAbaInicial <= 0) {
                $turmaAbaInicial = (int) (array_key_first($abasTurmas) ?: 0);
            }
            $variosCursos = count($nomesCursos) > 1;
            ?>

            <?php if ($abasTurmas === []): ?>
            <p class="text-sm text-amber-700">Nenhuma turma ativa em <?= (int) ($cenario['ano_letivo'] ?? 0) ?>. Cadastre as turmas do ano letivo antes de criar demandas.</p>
            <?php else: ?>

            <div class="mb-5" id="educasync-demandas-nav">
                <?php if ($variosCursos): ?>
                <nav aria-label="Cursos" class="border-b border-gray-200 overflow-x-auto mb-3">
                    <div class="flex min-w-max gap-1" role="tablist">
                        <?php foreach ($nomesCursos as $cursoNome):
                            $cursoAtivo = $cursoNome === $cursoInicial;
                            $qtdCurso = 0;
                            foreach (array_keys($gruposCurso[$cursoNome] ?? []) as $tidCurso) {
                                $qtdCurso += count($demandasPorTurma[$tidCurso] ?? []);
                            }
                            ?>
                        <button type="button"
                                class="educasync-demanda-curso inline-flex items-center gap-2 px-4 py-2.5 text-sm whitespace-nowrap border-b-2 -mb-px focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 <?= $cursoAtivo ? 'text-teal-800 font-semibold border-teal-600' : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300' ?>"
                                data-curso="<?= htmlspecialchars($cursoNome, ENT_QUOTES) ?>"
                                aria-pressed="<?= $cursoAtivo ? 'true' : 'false' ?>">
                            <span><?= htmlspecialchars($rotuloCursoCurto($cursoNome)) ?></span>
                            <span class="educasync-demanda-curso-badge inline-flex min-w-[1.25rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-medium <?= $cursoAtivo ? 'bg-teal-100 text-teal-800' : 'bg-gray-100 text-gray-500' ?>"><?= $qtdCurso ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </nav>
                <?php endif; ?>

                <?php foreach ($turmasPorCursoSerie as $cursoNome => $seriesDoCurso):
                    $painelCursoAtivo = !$variosCursos || $cursoNome === $cursoInicial;
                    ?>
                <div class="educasync-demanda-curso-painel rounded-xl border border-gray-200 bg-slate-50/70 p-3 space-y-2 <?= $painelCursoAtivo ? '' : 'hidden' ?>"
                     data-curso="<?= htmlspecialchars($cursoNome, ENT_QUOTES) ?>">
                    <?php if ($variosCursos): ?>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 px-0.5"><?= htmlspecialchars($rotuloCursoCurto($cursoNome)) ?> · turmas</p>
                    <?php endif; ?>
                    <?php foreach ($seriesDoCurso as $serieNome => $turmasSerie): ?>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="w-24 shrink-0 text-xs font-semibold text-slate-500 truncate" title="<?= htmlspecialchars($serieNome) ?>"><?= htmlspecialchars($serieNome) ?></span>
                        <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Turmas de <?= htmlspecialchars($serieNome) ?>">
                            <?php foreach ($turmasSerie as $tid => $aba):
                                $qtd = count($demandasPorTurma[$tid] ?? []);
                                $ativa = $tid === $turmaAbaInicial;
                                ?>
                            <button type="button"
                                    role="tab"
                                    id="educasync-aba-turma-<?= $tid ?>"
                                    data-turma-id="<?= $tid ?>"
                                    data-curso="<?= htmlspecialchars($aba['curso'], ENT_QUOTES) ?>"
                                    data-serie="<?= htmlspecialchars($aba['serie'], ENT_QUOTES) ?>"
                                    aria-selected="<?= $ativa ? 'true' : 'false' ?>"
                                    aria-controls="educasync-painel-turma-<?= $tid ?>"
                                    class="educasync-demanda-aba inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-sm border transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 <?= $ativa ? 'bg-teal-600 text-white border-teal-600 font-semibold shadow-sm' : 'bg-white text-gray-700 border-gray-200 hover:border-teal-400 hover:text-teal-800' ?>">
                                <span><?= htmlspecialchars($aba['nome']) ?></span>
                                <span class="educasync-demanda-aba-badge inline-flex min-w-[1.1rem] items-center justify-center rounded-md px-1 py-0.5 text-[10px] font-semibold <?= $ativa ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' ?>"><?= $qtd ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!$temVersoes): ?>
            <form method="POST" action="<?= $base ?>/demandas" id="educasync-demanda-form" class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="turma_id" id="turma_id" value="<?= $turmaAbaInicial ?>">
                <div>
                    <label for="materia_id" class="<?= $classeRotulo ?>">Matéria</label>
                    <select id="materia_id" name="materia_id" required class="<?= $classeInput ?>">
                        <option value="">Selecione</option>
                        <?php foreach ($materias as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" data-nome="<?= htmlspecialchars(mb_strtolower((string) $m['nome']), ENT_QUOTES) ?>"><?= htmlspecialchars((string) $m['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="professor_id" class="<?= $classeRotulo ?>">Professor</label>
                    <select id="professor_id" name="professor_id" required class="<?= $classeInput ?>">
                        <option value="">Selecione</option>
                        <?php foreach ($professores as $p):
                            $listaMaterias = is_array($p['materias_lista'] ?? null)
                                ? $p['materias_lista']
                                : (json_decode((string) ($p['materias'] ?? '[]'), true) ?: []);
                            $listaMaterias = is_array($listaMaterias) ? array_values(array_filter(array_map('strval', $listaMaterias))) : [];
                            $materiasAttr = mb_strtolower(implode('|', $listaMaterias));
                            $rotulo = (string) ($p['rotulo'] ?? $p['nome'] ?? '');
                            ?>
                        <option value="<?= (int) $p['id'] ?>" data-materias="<?= htmlspecialchars($materiasAttr, ENT_QUOTES) ?>"><?= htmlspecialchars($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Lista prioriza quem já tem a matéria no cadastro do professor.</p>
                </div>
                <div>
                    <label for="aulas_semana" class="<?= $classeRotulo ?>">Aulas por semana</label>
                    <input type="number" id="aulas_semana" name="aulas_semana" min="1" max="40" value="2" required class="<?= $classeInput ?>">
                </div>
                <div>
                    <label for="sala_id" class="<?= $classeRotulo ?>">Sala (opcional)</label>
                    <select id="sala_id" name="sala_id" class="<?= $classeInput ?>">
                        <option value="0">Sala da turma</option>
                        <?php foreach ($salas as $sala): ?>
                        <option value="<?= (int) $sala['id'] ?>"><?= htmlspecialchars((string) $sala['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="btn-primary-custom w-full px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90">Salvar demanda</button>
                </div>
            </form>
            <?php endif; ?>

            <?php foreach ($abasTurmas as $tid => $aba):
                $lista = $demandasPorTurma[$tid] ?? [];
                $totalAulas = 0;
                foreach ($lista as $d) {
                    $totalAulas += (int) ($d['aulas_semana'] ?? 0);
                }
                $painelAtivo = $tid === $turmaAbaInicial;
                ?>
            <div id="educasync-painel-turma-<?= $tid ?>"
                 role="tabpanel"
                 aria-labelledby="educasync-aba-turma-<?= $tid ?>"
                 data-turma-id="<?= $tid ?>"
                 class="educasync-demanda-painel <?= $painelAtivo ? '' : 'hidden' ?>">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-gray-600">
                        <strong class="text-gray-900"><?= htmlspecialchars($aba['nome']) ?></strong>
                        · <?= count($lista) ?> matéria(s)
                        · <?= $totalAulas ?> aula(s)/semana
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="<?= $classeTh ?>">Matéria</th>
                                <th class="<?= $classeTh ?>">Professor</th>
                                <th class="<?= $classeTh ?>">Aulas/semana</th>
                                <th class="<?= $classeTh ?> text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($lista === []): ?>
                            <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">Nenhuma demanda nesta turma. Importe a matriz ou cadastre abaixo.</td></tr>
                            <?php else: ?>
                            <?php foreach ($lista as $d): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars((string) ($d['materia_nome'] ?? '—')) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <?php if (!empty($d['professor_nome'])): ?>
                                    <?= htmlspecialchars((string) $d['professor_nome']) ?>
                                    <?php else: ?>
                                    <span class="text-amber-700">Sem professor</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700"><?= (int) $d['aulas_semana'] ?></td>
                                <td class="px-4 py-3 text-right">
                                    <?php if (!$temVersoes): ?>
                                    <form method="POST" action="<?= $base ?>/demandas/<?= (int) $d['id'] ?>/excluir" class="inline" data-csp-confirm="Remover esta demanda?">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <button type="submit" class="text-gray-400 hover:text-red-600" title="Remover demanda" aria-label="Remover demanda">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-gray-300">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </div>

    <!-- 3. Disponibilidade dos professores -->
    <div id="educasync-secao-disponibilidade" class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden scroll-mt-24">
        <div class="<?= $classeFaixa ?>">
            <div class="flex items-center gap-3 min-w-0">
                <span class="<?= $classeCirculo ?>">3</span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Disponibilidade dos professores</p>
                    <p class="text-sm text-gray-500 mt-0.5">Vá professor a professor e marque quando ele <strong>não</strong> pode dar aula. O solver monta a grade só nos horários livres e ainda tenta evitar janelas.</p>
                </div>
            </div>
        </div>
        <div class="p-5 space-y-5">
            <?php if ($professoresSobrecarregados !== []): ?>
            <div id="educasync-alerta-sobrecarga" class="rounded-lg border border-red-300 bg-red-50 px-4 py-3">
                <p class="text-sm font-semibold text-red-900">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                    Professores sem horário suficiente (grade inviável)
                </p>
                <p class="mt-1 text-sm text-red-800">
                    Os bloqueios foram mantidos, mas estes docentes têm mais aulas do que horários livres.
                    Alivie a disponibilidade ou redistribua/contrate reforço antes de gerar a grade.
                </p>
                <ul class="mt-3 space-y-2">
                    <?php foreach ($professoresSobrecarregados as $ps): ?>
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-red-200 bg-white px-3 py-2 text-sm text-red-900">
                        <span>
                            <strong><?= htmlspecialchars((string) ($ps['nome'] ?? '')) ?></strong>
                            · <?= (int) ($ps['aulas'] ?? 0) ?> aulas ·
                            <?= (int) ($ps['livres'] ?? 0) ?> livres ·
                            faltam <strong><?= (int) ($ps['faltam'] ?? 0) ?></strong>
                        </span>
                        <?php if (!$temVersoes && $slotsPorDia !== []): ?>
                        <button type="button"
                                class="educasync-disp-editar inline-flex items-center gap-1 rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700"
                                data-professor-id="<?= (int) ($ps['professor_id'] ?? 0) ?>">
                            Ajustar bloqueios
                        </button>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($slotsPorDia === []): ?>
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-sm font-semibold text-amber-900">Ainda não há horários neste cenário</p>
                <p class="mt-1 text-sm text-amber-800">
                    Sem os slots da seção 1 não dá para marcar quando o professor <strong>não</strong> pode dar aula.
                    Gere os horários (ex.: Seg–Sex, 07:10, 50 min, 6 aulas/dia) e volte aqui.
                </p>
                <a href="#educasync-secao-horarios"
                   class="mt-3 inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                    <i class="fa-solid fa-clock"></i>
                    Ir para Gerar horários
                </a>
            </div>
            <?php elseif ($temVersoes): ?>
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-sm font-semibold text-amber-900">Disponibilidade travada</p>
                <p class="mt-1 text-sm text-amber-800">
                    Este cenário já tem versão gerada, então os bloqueios não podem mudar aqui.
                    Para ajustar disponibilidade: crie um <strong>novo cenário</strong>, gere os horários, importe as demandas e marque os bloqueios <em>antes</em> de gerar a grade —
                    ou use o chat da Tudinha (se ainda não houver versão).
                </p>
            </div>
            <?php else: ?>
            <form method="POST" action="<?= $base ?>/disponibilidades/professor" id="educasync-disp-form" class="space-y-4"
                  data-bloqueios="<?= htmlspecialchars((string) json_encode($bloqueiosProfessores, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES) ?>">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="max-w-md">
                    <label for="disp_professor_id" class="<?= $classeRotulo ?>">Professor</label>
                    <select id="disp_professor_id" name="professor_id" required class="<?= $classeInput ?>">
                        <option value="">Selecione</option>
                        <?php foreach ($professores as $p):
                            $pidOpt = (int) $p['id'];
                            $sobra = $idsSobrecarregados[$pidOpt] ?? null;
                            $rotuloOpt = (string) ($p['rotulo'] ?? $p['nome'] ?? '');
                            if ($sobra) {
                                $rotuloOpt .= ' ⚠ ' . (int) $sobra['aulas'] . ' aulas / ' . (int) $sobra['livres'] . ' livres';
                            }
                            ?>
                        <option value="<?= $pidOpt ?>" <?= $sobra ? 'data-sobrecarga="1"' : '' ?>><?= htmlspecialchars($rotuloOpt) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Só professores vinculados às turmas de <?= $anoLetivoCenario ?> (ou já usados nas demandas deste cenário).</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <?php foreach ($slotsPorDia as $dia => $lista): ?>
                    <div class="rounded-lg border border-gray-200 educasync-disp-dia" data-dia="<?= (int) $dia ?>">
                        <label class="flex items-center gap-2 px-3 py-2 text-sm font-semibold text-gray-800 bg-gray-50 border-b border-gray-200 rounded-t-lg cursor-pointer">
                            <input type="checkbox" class="educasync-disp-dia-flag rounded border-gray-300 text-teal-600 focus:ring-teal-500" title="Bloquear o dia inteiro">
                            <span><?= htmlspecialchars(Slot::DIAS[$dia] ?? (string) $dia) ?></span>
                        </label>
                        <ul class="divide-y divide-gray-100">
                            <?php foreach ($lista as $s): ?>
                            <li class="px-3 py-1.5 text-sm text-gray-700">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" name="slots[]" value="<?= (int) $s['id'] ?>" class="educasync-disp-slot">
                                    <?= (int) $s['ordem'] ?>ª · <?= htmlspecialchars(substr((string) $s['horario_de'], 0, 5)) ?>–<?= htmlspecialchars(substr((string) $s['horario_ate'], 0, 5)) ?>
                                </label>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90">Salvar bloqueios</button>
                    <button type="submit" formaction="<?= $base ?>/disponibilidades/professor/limpar" formnovalidate
                            onclick="return document.getElementById('disp_professor_id').value !== '' && confirm('Remover todos os bloqueios deste professor?');"
                            class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">Limpar bloqueios</button>
                </div>
            </form>
            <?php endif; ?>

            <div>
                <h3 class="text-sm font-semibold text-gray-900 mb-2">Bloqueios atuais</h3>
                <?php if ($resumoBloqueios === []): ?>
                <p class="text-sm text-gray-500">Nenhum professor com horário bloqueado.</p>
                <?php else: ?>
                <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200">
                    <?php foreach ($resumoBloqueios as $b):
                        $pidBloq = (int) $b['professor_id'];
                        $sobraBloq = $idsSobrecarregados[$pidBloq] ?? null;
                        ?>
                    <li class="px-4 py-2 text-sm flex flex-wrap items-center justify-between gap-2 <?= $sobraBloq ? 'bg-red-50 text-red-900' : 'text-gray-700' ?>">
                        <span>
                            <?php if ($sobraBloq): ?>
                            <i class="fa-solid fa-triangle-exclamation text-red-600 mr-1" title="Mais aulas do que horários livres"></i>
                            <?php endif; ?>
                            <strong><?= htmlspecialchars((string) ($b['professor_nome'] ?? ('Professor ' . $pidBloq))) ?></strong>
                            · <?= (int) $b['total_bloqueados'] ?> horário(s) bloqueado(s) de <?= count($slots) ?>
                            <?php if ($sobraBloq): ?>
                            <span class="ml-1 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">
                                pendente · faltam <?= (int) $sobraBloq['faltam'] ?> horário(s)
                            </span>
                            <?php endif; ?>
                        </span>
                        <?php if (!$temVersoes && $slotsPorDia !== []): ?>
                        <button type="button" class="educasync-disp-editar text-sm font-medium <?= $sobraBloq ? 'text-red-700 hover:text-red-900' : 'text-purple-700 hover:text-purple-900' ?>"
                                data-professor-id="<?= $pidBloq ?>">Editar</button>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 4. Otimização e versões -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="<?= $classeFaixa ?>">
            <div class="flex items-center gap-3 min-w-0">
                <span class="<?= $classeCirculo ?>">4</span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Gerar e publicar</p>
                    <p class="text-sm text-gray-500 mt-0.5">Com demandas + disponibilidade, o solver monta a grade respeitando choques e priorizando menos janelas na agenda do professor.</p>
                </div>
            </div>
            <?php if ($tudicoinsDisponivel): ?>
            <form method="POST" action="<?= $base ?>/gerar"
                  onsubmit="return confirm(<?= $professoresSobrecarregados !== []
                      ? json_encode('Há professores sem horário suficiente. A grade tende a ficar inviável. Gerar mesmo assim?', JSON_UNESCAPED_UNICODE)
                      : json_encode('Gerar uma nova versão? Isso consome TudiCoins da escola.', JSON_UNESCAPED_UNICODE) ?>);">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button type="submit" <?= $execucaoAtiva ? 'disabled' : '' ?>
                        class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-wand-magic-sparkles mr-2"></i>Gerar grade
                </button>
            </form>
            <?php endif; ?>
        </div>
        <div class="p-5 space-y-6">
            <?php if ($professoresSobrecarregados !== []): ?>
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                <?= count($professoresSobrecarregados) ?> professor(es) com carga maior que os horários livres —
                <a href="#educasync-alerta-sobrecarga" class="font-semibold underline hover:no-underline">ver pendências</a>.
            </div>
            <?php endif; ?>
            <?php if (!$tudicoinsDisponivel): ?>
            <p class="text-sm text-amber-700">Ative os TudiCoins desta escola no Master para gerar grades com o EducaSync.</p>
            <?php endif; ?>

            <?php if ($execucaoAtiva): ?>
            <div id="educasync-andamento" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800"
                 data-url="<?= $base ?>/execucoes/<?= (int) $execucaoAtiva['id'] ?>/status">
                <i class="fa-solid fa-spinner fa-spin mr-2"></i>
                <span id="educasync-andamento-texto">Otimizando a grade… isso pode levar até <?= (int) ($cenario['max_time_s'] ?? 60) ?> segundos.</span>
            </div>
            <?php endif; ?>

            <div>
                <h3 class="text-sm font-semibold text-gray-900 mb-2">Versões geradas</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="<?= $classeTh ?>">Versão</th>
                                <th class="<?= $classeTh ?>">Status</th>
                                <th class="<?= $classeTh ?>">Solver</th>
                                <th class="<?= $classeTh ?>">Gerada em</th>
                                <th class="<?= $classeTh ?> text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($versoes === []): ?>
                            <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Nenhuma versão gerada ainda.</td></tr>
                            <?php else: ?>
                            <?php foreach ($versoes as $v):
                                $statusVersao = (string) $v['status'];
                                $variantVersao = $statusVersao === 'publicada' ? 'ativo' : ($statusVersao === 'descartada' ? 'inativo' : 'info');
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">#<?= (int) $v['id'] ?></td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php $ui_badge_variant = $variantVersao; $ui_badge_label = Versao::STATUS[$statusVersao] ?? $statusVersao; include __DIR__ . '/../../../../Views/admin/_partials/ui/badge.php'; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) ($v['solver_status'] ?? '—')) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $v['created_at']))) ?></td>
                                <td class="px-4 py-3 text-right">
                                    <a href="<?= $base ?>/versoes/<?= (int) $v['id'] ?>" class="text-sm font-medium text-purple-700 hover:text-purple-900">Ver versão</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($execucoes !== []): ?>
            <div>
                <h3 class="text-sm font-semibold text-gray-900 mb-2">Últimas execuções</h3>
                <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200">
                    <?php foreach ($execucoes as $e): ?>
                    <li class="px-4 py-2 text-sm text-gray-700 flex flex-wrap items-center justify-between gap-2">
                        <span>
                            #<?= (int) $e['id'] ?> · <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $e['created_at']))) ?> ·
                            <strong><?= htmlspecialchars(Execucao::STATUS[$e['status']] ?? (string) $e['status']) ?></strong>
                            <?php if (!empty($e['solver_status'])): ?> (<?= htmlspecialchars((string) $e['solver_status']) ?>)<?php endif; ?>
                            <?php if ($e['tempo_ms'] !== null): ?> · <?= number_format(((int) $e['tempo_ms']) / 1000, 1, ',', '.') ?>s<?php endif; ?>
                        </span>
                        <?php if (!empty($e['log_resumo'])): ?>
                        <span class="text-gray-500"><?= htmlspecialchars((string) $e['log_resumo']) ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/_assistente.php'; ?>
</div>

<script>
(function () {
    var raiz = document.getElementById('educasync-demandas');
    if (!raiz) { return; }
    var abas = Array.prototype.slice.call(raiz.querySelectorAll('.educasync-demanda-aba'));
    var paineis = raiz.querySelectorAll('.educasync-demanda-painel');
    var cursos = Array.prototype.slice.call(raiz.querySelectorAll('.educasync-demanda-curso'));
    var paineisCurso = Array.prototype.slice.call(raiz.querySelectorAll('.educasync-demanda-curso-painel'));
    var turmaInput = document.getElementById('turma_id');

    function estiloAba(aba, ativa) {
        aba.setAttribute('aria-selected', ativa ? 'true' : 'false');
        aba.className = 'educasync-demanda-aba inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-sm border transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 '
            + (ativa
                ? 'bg-teal-600 text-white border-teal-600 font-semibold shadow-sm'
                : 'bg-white text-gray-700 border-gray-200 hover:border-teal-400 hover:text-teal-800');
        var badge = aba.querySelector('.educasync-demanda-aba-badge');
        if (badge) {
            badge.className = 'educasync-demanda-aba-badge inline-flex min-w-[1.1rem] items-center justify-center rounded-md px-1 py-0.5 text-[10px] font-semibold '
                + (ativa ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500');
        }
    }

    function estiloCurso(botao, ativo) {
        botao.setAttribute('aria-pressed', ativo ? 'true' : 'false');
        botao.className = 'educasync-demanda-curso inline-flex items-center gap-2 px-4 py-2.5 text-sm whitespace-nowrap border-b-2 -mb-px focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 '
            + (ativo
                ? 'text-teal-800 font-semibold border-teal-600'
                : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300');
        var badge = botao.querySelector('.educasync-demanda-curso-badge');
        if (badge) {
            badge.className = 'educasync-demanda-curso-badge inline-flex min-w-[1.25rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-medium '
                + (ativo ? 'bg-teal-100 text-teal-800' : 'bg-gray-100 text-gray-500');
        }
    }

    function filtrarCurso(cursoNome, selecionarPrimeira) {
        if (!cursoNome) { return; }
        cursos.forEach(function (botao) {
            estiloCurso(botao, botao.getAttribute('data-curso') === cursoNome);
        });
        paineisCurso.forEach(function (painel) {
            painel.classList.toggle('hidden', painel.getAttribute('data-curso') !== cursoNome);
        });
        if (selecionarPrimeira) {
            var primeira = abas.find(function (aba) { return aba.getAttribute('data-curso') === cursoNome; });
            if (primeira) {
                ativarTurma(primeira.getAttribute('data-turma-id'));
                return;
            }
        }
    }

    function ativarTurma(turmaId) {
        var alvo = abas.find(function (aba) { return aba.getAttribute('data-turma-id') === String(turmaId); });
        if (alvo) {
            filtrarCurso(alvo.getAttribute('data-curso') || '', false);
        }
        abas.forEach(function (aba) {
            estiloAba(aba, aba.getAttribute('data-turma-id') === String(turmaId));
        });
        paineis.forEach(function (painel) {
            painel.classList.toggle('hidden', painel.getAttribute('data-turma-id') !== String(turmaId));
        });
        if (turmaInput) {
            turmaInput.value = String(turmaId);
        }
    }

    cursos.forEach(function (botao) {
        botao.addEventListener('click', function () {
            filtrarCurso(botao.getAttribute('data-curso') || '', true);
        });
    });

    abas.forEach(function (aba) {
        aba.addEventListener('click', function () {
            ativarTurma(aba.getAttribute('data-turma-id'));
        });
    });

    var hash = (window.location.hash || '').match(/^#turma-(\d+)$/);
    if (hash) {
        ativarTurma(hash[1]);
    }
})();

(function () {
    var materia = document.getElementById('materia_id');
    var professor = document.getElementById('professor_id');
    if (materia && professor) {
        var opcoes = Array.prototype.slice.call(professor.querySelectorAll('option'));
        function normalizar(v) {
            return String(v || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
        }
        function filtrarProfessores() {
            var optMat = materia.options[materia.selectedIndex];
            var id = String(materia.value || '');
            var nome = normalizar(optMat ? optMat.getAttribute('data-nome') : '');
            var atual = professor.value;
            var visiveisVinculados = 0;
            opcoes.forEach(function (opt) {
                if (!opt.value) {
                    opt.hidden = false;
                    return;
                }
                var lista = normalizar(opt.getAttribute('data-materias') || '');
                var itens = lista ? lista.split('|') : [];
                var bate = !id || itens.some(function (item) {
                    item = normalizar(item);
                    return item === id || (nome && item === nome);
                });
                // Sem vínculo cadastrado: mantém na lista (cadastro incompleto), mas os vinculados aparecem primeiro.
                opt.hidden = false;
                opt.disabled = false;
                if (bate && itens.length) {
                    opt.dataset.prioridade = '1';
                    visiveisVinculados++;
                } else {
                    opt.dataset.prioridade = '0';
                }
            });
            opcoes.sort(function (a, b) {
                if (!a.value) return -1;
                if (!b.value) return 1;
                var pa = a.dataset.prioridade === '1' ? 0 : 1;
                var pb = b.dataset.prioridade === '1' ? 0 : 1;
                if (pa !== pb) return pa - pb;
                return a.text.localeCompare(b.text, 'pt-BR');
            }).forEach(function (opt) { professor.appendChild(opt); });
            if (atual) {
                professor.value = atual;
            }
            if (visiveisVinculados === 1) {
                var unico = opcoes.find(function (opt) { return opt.value && opt.dataset.prioridade === '1'; });
                if (unico) { professor.value = unico.value; }
            }
        }
        materia.addEventListener('change', filtrarProfessores);
        filtrarProfessores();
    }
})();

(function () {
    var form = document.getElementById('educasync-disp-form');
    if (!form) { return; }
    var select = document.getElementById('disp_professor_id');
    var bloqueios = {};
    try { bloqueios = JSON.parse(form.getAttribute('data-bloqueios') || '{}'); } catch (e) { bloqueios = {}; }

    function sincronizarDia(coluna) {
        var slots = coluna.querySelectorAll('.educasync-disp-slot');
        var flag = coluna.querySelector('.educasync-disp-dia-flag');
        if (!flag || !slots.length) { return; }
        var marcados = 0;
        slots.forEach(function (cb) { if (cb.checked) { marcados++; } });
        flag.checked = marcados === slots.length;
        flag.indeterminate = marcados > 0 && marcados < slots.length;
    }

    function sincronizarTodosDias() {
        form.querySelectorAll('.educasync-disp-dia').forEach(sincronizarDia);
    }

    function marcar() {
        var ids = (bloqueios[select.value] || []).map(String);
        form.querySelectorAll('.educasync-disp-slot').forEach(function (cb) {
            cb.checked = ids.indexOf(cb.value) !== -1;
        });
        sincronizarTodosDias();
    }

    form.querySelectorAll('.educasync-disp-dia').forEach(function (coluna) {
        var flag = coluna.querySelector('.educasync-disp-dia-flag');
        if (!flag) { return; }
        flag.addEventListener('change', function () {
            coluna.querySelectorAll('.educasync-disp-slot').forEach(function (cb) {
                cb.checked = flag.checked;
            });
            flag.indeterminate = false;
        });
        coluna.querySelectorAll('.educasync-disp-slot').forEach(function (cb) {
            cb.addEventListener('change', function () { sincronizarDia(coluna); });
        });
    });

    select.addEventListener('change', marcar);
    document.querySelectorAll('.educasync-disp-editar').forEach(function (botao) {
        botao.addEventListener('click', function () {
            select.value = botao.getAttribute('data-professor-id');
            marcar();
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });
    sincronizarTodosDias();
})();
</script>

<?php if ($execucaoAtiva): ?>
<script>
(function () {
    var caixa = document.getElementById('educasync-andamento');
    if (!caixa) { return; }
    var texto = document.getElementById('educasync-andamento-texto');
    var url = caixa.getAttribute('data-url');
    var falhas = 0;

    function consultar() {
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                falhas = 0;
                if (dados && dados.terminou) {
                    window.location.reload();
                    return;
                }
                if (dados && dados.status_label) {
                    texto.textContent = dados.status_label + '…';
                }
                setTimeout(consultar, 3000);
            })
            .catch(function () {
                falhas++;
                if (falhas < 5) { setTimeout(consultar, 5000); }
            });
    }
    setTimeout(consultar, 2000);
})();
</script>
<?php endif; ?>
