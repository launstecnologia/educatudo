<?php
$bloco = $bloco ?? [];
$linhas = $linhas ?? [];
$materiasFiltro = $materias_filtro ?? [];
$turmasFiltro = $turmas_filtro ?? [];
$seriesFiltro = $series_filtro ?? [];
$materiaIdFiltro = (int) ($materia_id_filtro ?? 0);
$professorIdFiltro = (int) ($professor_id_filtro ?? 0);
$professorNomeFiltro = trim((string) ($professor_nome_filtro ?? ''));
$turmaIdFiltro = (int) ($turma_id_filtro ?? 0);
$serieIdFiltro = (int) ($serie_id_filtro ?? 0);
$ordenarFiltro = (string) ($ordenar_filtro ?? 'nome');
$ordensOk = ['nome', 'nome_desc', 'chamada', 'chamada_desc', 'sexo'];
if (!in_array($ordenarFiltro, $ordensOk, true)) {
    $ordenarFiltro = 'nome';
}
$pagina = max(1, (int) ($pagina ?? 1));
$porPagina = (int) ($por_pagina ?? 40);
if (!in_array($porPagina, [20, 40, 60, 100], true)) {
    $porPagina = 40;
}
$totalLinhas = (int) ($total_linhas ?? count($linhas));
$totalPaginas = max(1, (int) ($total_paginas ?? 1));
$historico = is_array($historico_alteracoes ?? null) ? $historico_alteracoes : [];
$edicaoDesbloqueada = !empty($edicao_desbloqueada);
$csrfToken = $csrf_token ?? '';
$flash = $flash ?? [];
$notaUnicaTodasMaterias = !empty($bloco['nota_unica_todas_materias']);
$colunasTabela = ($notaUnicaTodasMaterias ? 4 : 6) + 1;
$blocoId = (int) ($bloco['id'] ?? 0);
$actionFiltro = URL . '/admin/provas/blocos/' . $blocoId . '/lancar-notas-coordenacao';
$urlDesbloquear = URL . '/admin/provas/blocos/' . $blocoId . '/lancar-notas-coordenacao/desbloquear';

$filtrosAtivos = [];
if (!$notaUnicaTodasMaterias && $materiaIdFiltro > 0) {
    $filtrosAtivos[] = 'Matéria: ' . ($materiasFiltro[$materiaIdFiltro] ?? ('#' . $materiaIdFiltro));
}
if (!$notaUnicaTodasMaterias && $professorIdFiltro > 0) {
    $filtrosAtivos[] = 'Professor: ' . ($professorNomeFiltro !== '' ? $professorNomeFiltro : ('#' . $professorIdFiltro));
}
if ($serieIdFiltro > 0) {
    $filtrosAtivos[] = 'Série: ' . ($seriesFiltro[$serieIdFiltro] ?? ('#' . $serieIdFiltro));
}
if ($turmaIdFiltro > 0) {
    $filtrosAtivos[] = 'Turma: ' . ($turmasFiltro[$turmaIdFiltro] ?? ('#' . $turmaIdFiltro));
}
$rotulosOrdenar = [
    'nome' => 'Nome (A–Z)',
    'nome_desc' => 'Nome (Z–A)',
    'chamada' => 'Nº da chamada',
    'chamada_desc' => 'Nº da chamada (decrescente)',
    'sexo' => 'Sexo',
];
$filtrosAtivos[] = 'Ordem: ' . ($rotulosOrdenar[$ordenarFiltro] ?? $ordenarFiltro);

$qsBase = array_filter([
    'materia_id' => $materiaIdFiltro > 0 ? $materiaIdFiltro : null,
    'professor_id' => $professorIdFiltro > 0 ? $professorIdFiltro : null,
    'turma_id' => $turmaIdFiltro > 0 ? $turmaIdFiltro : null,
    'serie_id' => $serieIdFiltro > 0 ? $serieIdFiltro : null,
    'ordenar' => $ordenarFiltro !== 'nome' ? $ordenarFiltro : null,
    'por_pagina' => $porPagina !== 40 ? $porPagina : null,
], static fn($v) => $v !== null && $v !== '');
$urlPagina = static function (int $p) use ($actionFiltro, $qsBase): string {
    $q = $qsBase;
    if ($p > 1) {
        $q['pagina'] = $p;
    }
    $qs = http_build_query($q);
    return $qs !== '' ? $actionFiltro . '?' . $qs : $actionFiltro;
};
?>

<div class="mb-6 flex flex-wrap justify-between items-start gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Lançamento de notas (Coordenação)</h2>
        <p class="text-gray-600 mt-1"><?= htmlspecialchars((string) ($bloco['titulo'] ?? '')) ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <button type="button" onclick="openFilterDrawer()"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
            <i class="fa-solid fa-filter" aria-hidden="true"></i>
            Filtros
        </button>
        <button type="button" onclick="openHistoricoDrawer()"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-800 text-sm font-medium hover:bg-indigo-100">
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
            Histórico
        </button>
        <?php if (!$edicaoDesbloqueada): ?>
        <button type="button" id="btnDesbloquearEdicao" onclick="abrirModalSenha()"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-amber-300 bg-amber-50 text-amber-900 text-sm font-semibold hover:bg-amber-100">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            Desbloquear edição
        </button>
        <?php else: ?>
        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 text-sm font-medium">
            <i class="fa-solid fa-lock-open" aria-hidden="true"></i>
            Edição liberada
        </span>
        <?php endif; ?>
        <a href="<?= URL ?>/admin/provas/blocos/<?= $blocoId ?>/gerenciar"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
            ← Voltar
        </a>
    </div>
</div>

<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg <?= (!empty($flash['type']) && $flash['type'] === 'error') ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>

<div class="mb-4 flex flex-wrap items-center gap-2 text-sm text-gray-600">
    <?php foreach ($filtrosAtivos as $chip): ?>
        <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-medium"><?= htmlspecialchars($chip) ?></span>
    <?php endforeach; ?>
    <span class="text-xs text-gray-500"><?= (int) $totalLinhas ?> aluno(s) · página <?= (int) $pagina ?>/<?= (int) $totalPaginas ?></span>
</div>

<p class="text-xs text-gray-500 mb-4">
    Notas novas podem ser lançadas normalmente. Notas já salvas ficam bloqueadas até você confirmar com a senha de acesso.
    <?php if ($notaUnicaTodasMaterias): ?>
        <span class="text-violet-800">Neste evento a nota é única e replica para todas as matérias.</span>
    <?php endif; ?>
</p>

<form method="post" action="<?= URL ?>/admin/provas/blocos/<?= $blocoId ?>/lancar-notas-coordenacao" class="space-y-4" id="formLancamentoNotas">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
    <input type="hidden" name="materia_id_filtro" value="<?= $materiaIdFiltro ?>">
    <input type="hidden" name="professor_id_filtro" value="<?= $professorIdFiltro ?>">
    <input type="hidden" name="turma_id_filtro" value="<?= $turmaIdFiltro ?>">
    <input type="hidden" name="serie_id_filtro" value="<?= $serieIdFiltro ?>">
    <input type="hidden" name="ordenar_filtro" value="<?= htmlspecialchars($ordenarFiltro) ?>">
    <input type="hidden" name="pagina" value="<?= (int) $pagina ?>">
    <input type="hidden" name="por_pagina" value="<?= (int) $porPagina ?>">
    <input type="hidden" name="senha_confirmacao" id="senhaConfirmacaoHidden" value="">

    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <?php if (!$notaUnicaTodasMaterias): ?>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matéria</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Professor</th>
                        <?php endif; ?>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-16">Nº</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-40">Nota</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Observação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($linhas)): ?>
                    <tr>
                        <td colspan="<?= (int) $colunasTabela ?>" class="px-4 py-8 text-center text-gray-500">Nenhum aluno encontrado para o filtro selecionado.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($linhas as $ln): ?>
                            <?php
                            $pid = (int) ($ln['professor_id'] ?? 0);
                            $mid = (int) ($ln['materia_id'] ?? 0);
                            $tid = (int) ($ln['turma_id'] ?? 0);
                            $aid = (int) ($ln['aluno_id'] ?? 0);
                            $temNota = ($ln['nota'] !== null && $ln['nota'] !== '');
                            $notaStr = $temNota ? number_format((float) $ln['nota'], 2, '.', '') : '';
                            $nChamada = (int) ($ln['numero_chamada'] ?? 0);
                            $bloqueada = $temNota && !$edicaoDesbloqueada;
                            $transferido = !empty($ln['transferido']);
                            $nomeAluno = (string) ($ln['aluno_nome'] ?? '');
                            ?>
                            <tr class="<?= $transferido ? 'bg-gray-100 text-gray-500' : 'hover:bg-gray-50' ?>" data-tem-nota="<?= $temNota ? '1' : '0' ?>">
                                <?php if (!$notaUnicaTodasMaterias): ?>
                                <td class="px-4 py-3 <?= $transferido ? 'text-gray-500' : 'text-gray-700' ?>"><?= htmlspecialchars((string) ($ln['materia_nome'] ?? '')) ?></td>
                                <td class="px-4 py-3 <?= $transferido ? 'text-gray-500' : 'text-gray-700' ?>"><?= htmlspecialchars((string) ($ln['professor_nome'] ?? '')) ?></td>
                                <?php endif; ?>
                                <td class="px-4 py-3 <?= $transferido ? 'text-gray-500' : 'text-gray-700' ?>"><?= htmlspecialchars((string) ($ln['turma_nome'] ?? '')) ?></td>
                                <td class="px-4 py-3 text-center <?= $transferido ? 'text-gray-500' : 'text-gray-700' ?> font-semibold"><?= $nChamada > 0 ? $nChamada : '—' ?></td>
                                <td class="px-4 py-3 font-medium <?= $transferido ? 'text-gray-500' : 'text-gray-900' ?>">
                                    <?php if ($transferido): ?>
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold tracking-wide bg-gray-300 text-gray-700">TR</span>
                                            <span><?= htmlspecialchars($nomeAluno) ?></span>
                                        </span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($nomeAluno) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1.5">
                                        <input type="text"
                                               inputmode="decimal"
                                               name="notas[<?= $pid ?>][<?= $mid ?>][<?= $tid ?>][<?= $aid ?>]"
                                               value="<?= htmlspecialchars($notaStr) ?>"
                                               class="js-nota-input w-full px-2 py-1.5 border border-gray-300 rounded-lg <?= $bloqueada ? 'bg-gray-100 text-gray-600' : '' ?>"
                                               placeholder="—"
                                               autocomplete="off"
                                               data-original="<?= htmlspecialchars($notaStr) ?>"
                                               <?= $bloqueada ? 'readonly' : '' ?>>
                                        <?php if ($bloqueada): ?>
                                        <button type="button" class="shrink-0 text-amber-600" title="Bloqueada — use Desbloquear edição" onclick="abrirModalSenha()" aria-label="Desbloquear">
                                            <i class="fa-solid fa-lock text-xs" aria-hidden="true"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text"
                                           name="observacoes[<?= $pid ?>][<?= $mid ?>][<?= $tid ?>][<?= $aid ?>]"
                                           value="<?= htmlspecialchars((string) ($ln['observacao'] ?? '')) ?>"
                                           class="js-obs-input w-full px-2 py-1.5 border border-gray-300 rounded-lg <?= $bloqueada ? 'bg-gray-100 text-gray-600' : '' ?>"
                                           maxlength="500"
                                           placeholder="Opcional"
                                           data-original="<?= htmlspecialchars((string) ($ln['observacao'] ?? '')) ?>"
                                           <?= $bloqueada ? 'readonly' : '' ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPaginas > 1): ?>
    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p class="text-gray-600">
            Mostrando <?= count($linhas) ?> de <?= (int) $totalLinhas ?>
        </p>
        <div class="flex flex-wrap gap-1">
            <?php if ($pagina > 1): ?>
                <a href="<?= htmlspecialchars($urlPagina($pagina - 1)) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50">Anterior</a>
            <?php endif; ?>
            <?php
            $ini = max(1, $pagina - 2);
            $fim = min($totalPaginas, $pagina + 2);
            for ($p = $ini; $p <= $fim; $p++):
            ?>
                <a href="<?= htmlspecialchars($urlPagina($p)) ?>"
                   class="px-3 py-1.5 rounded-lg border <?= $p === $pagina ? 'border-indigo-500 bg-indigo-50 text-indigo-800 font-semibold' : 'border-gray-300 bg-white hover:bg-gray-50' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
            <?php if ($pagina < $totalPaginas): ?>
                <a href="<?= htmlspecialchars($urlPagina($pagina + 1)) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50">Próxima</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($linhas)): ?>
    <div class="flex gap-3">
        <button type="submit" class="btn-primary-custom inline-flex items-center px-6 py-3 rounded-lg font-semibold hover:opacity-90">
            Salvar notas
        </button>
        <a href="<?= URL ?>/admin/provas/blocos/<?= $blocoId ?>/gerenciar"
           class="inline-flex items-center px-6 py-3 rounded-lg border border-gray-300 text-gray-700 font-medium hover:bg-gray-50">
            Cancelar
        </a>
    </div>
    <?php endif; ?>
</form>

<!-- Offcanvas filtros -->
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-white shadow-2xl transform translate-x-full transition-transform duration-200 ease-out flex flex-col"
       aria-hidden="true">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Filtros e ordenação</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl" aria-hidden="true"></i>
        </button>
    </div>
    <form method="get" action="<?= htmlspecialchars($actionFiltro) ?>" class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
        <?php if ($professorIdFiltro > 0): ?>
        <input type="hidden" name="professor_id" value="<?= $professorIdFiltro ?>">
        <?php endif; ?>
        <?php if (!$notaUnicaTodasMaterias): ?>
        <div>
            <label for="materia_id" class="block text-sm font-medium text-gray-700 mb-1">Matéria</label>
            <select id="materia_id" name="materia_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                <option value="0" <?= $materiaIdFiltro === 0 ? 'selected' : '' ?>>Todas as matérias</option>
                <?php foreach ($materiasFiltro as $mid => $mnome): ?>
                    <option value="<?= (int) $mid ?>" <?= $materiaIdFiltro === (int) $mid ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $mnome) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label for="serie_id" class="block text-sm font-medium text-gray-700 mb-1">Série</label>
            <select id="serie_id" name="serie_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                <option value="0" <?= $serieIdFiltro === 0 ? 'selected' : '' ?>>Todas as séries</option>
                <?php foreach ($seriesFiltro as $sid => $snome): ?>
                    <option value="<?= (int) $sid ?>" <?= $serieIdFiltro === (int) $sid ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $snome) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-1">Turma</label>
            <select id="turma_id" name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                <option value="0" <?= $turmaIdFiltro === 0 ? 'selected' : '' ?>>Todas as turmas</option>
                <?php foreach ($turmasFiltro as $tid => $tnome): ?>
                    <option value="<?= (int) $tid ?>" <?= $turmaIdFiltro === (int) $tid ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $tnome) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="ordenar" class="block text-sm font-medium text-gray-700 mb-1">Ordenar por</label>
            <select id="ordenar" name="ordenar" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                <?php foreach ($rotulosOrdenar as $val => $lab): ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= $ordenarFiltro === $val ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="por_pagina" class="block text-sm font-medium text-gray-700 mb-1">Por página</label>
            <select id="por_pagina" name="por_pagina" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                <?php foreach ([20, 40, 60, 100] as $n): ?>
                    <option value="<?= $n ?>" <?= $porPagina === $n ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="pt-2 flex gap-3">
            <a href="<?= htmlspecialchars($actionFiltro) ?>"
               class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-center text-gray-700 bg-white hover:bg-gray-50">
                Limpar
            </a>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 btn-primary-custom rounded-lg text-sm font-semibold hover:opacity-90">
                Aplicar
            </button>
        </div>
    </form>
</aside>

<!-- Offcanvas histórico -->
<div id="historicoDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeHistoricoDrawer()"></div>
<aside id="historicoDrawer"
       class="fixed inset-y-0 right-0 z-50 w-full max-w-lg bg-white shadow-2xl transform translate-x-full transition-transform duration-200 ease-out flex flex-col"
       aria-hidden="true">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Histórico de alterações</h3>
        <button type="button" onclick="closeHistoricoDrawer()" class="text-gray-400 hover:text-gray-600" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl" aria-hidden="true"></i>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto px-6 py-4 space-y-3">
        <?php if ($historico === []): ?>
            <p class="text-sm text-gray-500">Nenhuma alteração registrada ainda. Rode a migration <code class="text-xs bg-gray-100 px-1 rounded">2026_09_29_provas_blocos_notas_lancadas_log</code> se a tabela ainda não existir.</p>
        <?php else: ?>
            <?php foreach ($historico as $h): ?>
                <?php
                $quando = !empty($h['criado_em']) ? date('d/m/Y H:i', strtotime((string) $h['criado_em'])) : '—';
                $na = $h['nota_anterior'];
                $nn = $h['nota_nova'];
                $naTxt = ($na === null || $na === '') ? '—' : number_format((float) $na, 2, ',', '.');
                $nnTxt = ($nn === null || $nn === '') ? '—' : number_format((float) $nn, 2, ',', '.');
                ?>
                <div class="rounded-lg border border-gray-200 p-3 text-sm">
                    <div class="flex justify-between gap-2 text-xs text-gray-500 mb-1">
                        <span><?= htmlspecialchars($quando) ?></span>
                        <span><?= htmlspecialchars((string) ($h['alterado_por_nome'] ?? 'Sistema')) ?></span>
                    </div>
                    <p class="font-medium text-gray-900"><?= htmlspecialchars((string) ($h['aluno_nome'] ?? '')) ?></p>
                    <p class="text-gray-600 text-xs mt-0.5">
                        <?= htmlspecialchars((string) ($h['materia_nome'] ?? '')) ?>
                        · <?= htmlspecialchars((string) ($h['turma_nome'] ?? '')) ?>
                    </p>
                    <p class="mt-2 text-gray-800">
                        Nota: <span class="font-semibold"><?= htmlspecialchars($naTxt) ?></span>
                        → <span class="font-semibold text-indigo-700"><?= htmlspecialchars($nnTxt) ?></span>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</aside>

<!-- Modal senha -->
<div id="modalSenha" class="fixed inset-0 z-[60] hidden" aria-modal="true" role="dialog">
    <div class="absolute inset-0 bg-black/50" onclick="fecharModalSenha()"></div>
    <div class="relative mx-auto mt-24 w-full max-w-md bg-white rounded-xl shadow-xl p-6">
        <h3 class="text-lg font-semibold text-gray-900">Confirmar senha</h3>
        <p class="text-sm text-gray-600 mt-1">Digite a senha da sua conta para liberar a alteração de notas já lançadas.</p>
        <div class="mt-4">
            <label for="senhaDesbloqueio" class="block text-sm font-medium text-gray-700 mb-1">Senha de acesso</label>
            <input type="password" id="senhaDesbloqueio" autocomplete="current-password"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                   onkeydown="if(event.key==='Enter'){event.preventDefault();confirmarSenhaDesbloqueio();}">
            <p id="senhaDesbloqueioErro" class="hidden text-xs text-red-600 mt-2"></p>
        </div>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" onclick="fecharModalSenha()" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm">Cancelar</button>
            <button type="button" id="btnConfirmarSenha" onclick="confirmarSenhaDesbloqueio()"
                    class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90">
                Desbloquear
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var edicaoLiberada = <?= $edicaoDesbloqueada ? 'true' : 'false' ?>;
    var urlDesbloquear = <?= json_encode($urlDesbloquear, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var csrfToken = <?= json_encode((string) $csrfToken, JSON_UNESCAPED_UNICODE) ?>;

    window.openFilterDrawer = function () {
        document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
        var d = document.getElementById('filterDrawer');
        d.classList.remove('translate-x-full');
        d.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };
    window.closeFilterDrawer = function () {
        document.getElementById('filterDrawerBackdrop').classList.add('hidden');
        var d = document.getElementById('filterDrawer');
        d.classList.add('translate-x-full');
        d.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };
    window.openHistoricoDrawer = function () {
        document.getElementById('historicoDrawerBackdrop').classList.remove('hidden');
        var d = document.getElementById('historicoDrawer');
        d.classList.remove('translate-x-full');
        d.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };
    window.closeHistoricoDrawer = function () {
        document.getElementById('historicoDrawerBackdrop').classList.add('hidden');
        var d = document.getElementById('historicoDrawer');
        d.classList.add('translate-x-full');
        d.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };
    window.abrirModalSenha = function () {
        var m = document.getElementById('modalSenha');
        var err = document.getElementById('senhaDesbloqueioErro');
        var inp = document.getElementById('senhaDesbloqueio');
        if (err) { err.classList.add('hidden'); err.textContent = ''; }
        if (inp) { inp.value = ''; }
        m.classList.remove('hidden');
        setTimeout(function () { if (inp) inp.focus(); }, 50);
    };
    window.fecharModalSenha = function () {
        document.getElementById('modalSenha').classList.add('hidden');
    };

    function liberarCamposBloqueados() {
        edicaoLiberada = true;
        document.querySelectorAll('.js-nota-input[readonly], .js-obs-input[readonly]').forEach(function (el) {
            el.removeAttribute('readonly');
            el.classList.remove('bg-gray-100', 'text-gray-600');
        });
        document.querySelectorAll('button[title="Bloqueada — use Desbloquear edição"]').forEach(function (b) {
            b.remove();
        });
        var btn = document.getElementById('btnDesbloquearEdicao');
        if (btn) {
            var span = document.createElement('span');
            span.className = 'inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 text-sm font-medium';
            span.innerHTML = '<i class="fa-solid fa-lock-open" aria-hidden="true"></i> Edição liberada';
            btn.replaceWith(span);
        }
    }

    window.confirmarSenhaDesbloqueio = function () {
        var inp = document.getElementById('senhaDesbloqueio');
        var err = document.getElementById('senhaDesbloqueioErro');
        var btn = document.getElementById('btnConfirmarSenha');
        var senha = (inp && inp.value) ? inp.value : '';
        if (!senha) {
            if (err) { err.textContent = 'Informe sua senha.'; err.classList.remove('hidden'); }
            return;
        }
        if (btn) { btn.disabled = true; btn.textContent = 'Verificando...'; }
        fetch(urlDesbloquear, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ _token: csrfToken, senha: senha })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (btn) { btn.disabled = false; btn.textContent = 'Desbloquear'; }
            if (!data || !data.success) {
                if (err) { err.textContent = (data && data.error) ? data.error : 'Não foi possível desbloquear.'; err.classList.remove('hidden'); }
                return;
            }
            document.getElementById('senhaConfirmacaoHidden').value = '';
            liberarCamposBloqueados();
            fecharModalSenha();
        })
        .catch(function () {
            if (btn) { btn.disabled = false; btn.textContent = 'Desbloquear'; }
            if (err) { err.textContent = 'Erro de conexão.'; err.classList.remove('hidden'); }
        });
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeFilterDrawer();
            closeHistoricoDrawer();
            fecharModalSenha();
        }
    });

    var form = document.getElementById('formLancamentoNotas');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (edicaoLiberada) return;
            var mudouBloqueada = false;
            document.querySelectorAll('tr[data-tem-nota="1"]').forEach(function (tr) {
                var nota = tr.querySelector('.js-nota-input');
                var obs = tr.querySelector('.js-obs-input');
                if (nota && nota.value !== (nota.getAttribute('data-original') || '')) mudouBloqueada = true;
                if (obs && obs.value !== (obs.getAttribute('data-original') || '')) mudouBloqueada = true;
            });
            if (mudouBloqueada) {
                e.preventDefault();
                abrirModalSenha();
            }
        });
    }
})();
</script>
