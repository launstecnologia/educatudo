<?php
$professores = is_array($professores ?? null) ? $professores : [];
$materias = is_array($materias ?? null) ? $materias : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$periodo = is_array($periodo ?? null) ? $periodo : [];
$rotulos = is_array($periodo['rotulos'] ?? null) ? $periodo['rotulos'] : [1 => '1º', 2 => '2º', 3 => '3º', 4 => '4º'];
$criterios = is_array($criterios ?? null) ? $criterios : [];
$registros = is_array($registros ?? null) ? $registros : [];
$criteriosProfessores = is_array($criterios_professores ?? null) ? $criterios_professores : [];
$canais = is_array($canais ?? null) ? $canais : [];
$modosOffline = is_array($modos_offline ?? null) ? $modos_offline : [];
$ano = (int) ($ano_letivo ?? date('Y'));
$periodoSel = (int) ($bimestre_selecionado ?? 0);
$periodosExtra = is_array($periodos_extra ?? null) ? $periodos_extra : [];
$rotuloPeriodoSel = '';
foreach ($rotulos as $numero => $rotulo) {
    if ((int) $numero === $periodoSel) {
        $rotuloPeriodoSel = (string) $rotulo;
        break;
    }
}
if ($rotuloPeriodoSel === '') {
    foreach ($periodosExtra as $extra) {
        if ((int) ($extra['codigo'] ?? 0) === $periodoSel) {
            $rotuloPeriodoSel = (string) ($extra['rotulo'] ?? '');
            break;
        }
    }
}
if ($rotuloPeriodoSel === '' && $periodoSel > 0) {
    $rotuloPeriodoSel = $periodoSel . 'º período';
}
$csrf = (string) ($csrf_token ?? '');
$edicao = is_array($edicao ?? null) ? $edicao : null;
$eventoIdForm = (int) ($edicao['id'] ?? 0);
$editando = $eventoIdForm > 0;
$canalEdicao = (string) ($edicao['canal'] ?? 'offline');
$cfgEdicao = (string) ($edicao['configuracao_nota'] ?? 'professor_por_questao');
$modoEdicao = (string) ($edicao['modo_offline'] ?? 'lancamento_direto');
$criterioEdicao = (string) ($edicao['criterio_fechamento'] ?? 'ultima');
$criterioProfEdicao = (string) ($edicao['criterio_professores'] ?? 'nenhum');
$escalaEdicao = (string) ($edicao['escala_max'] ?? '10.00');
$prazoEdicao = (string) ($edicao['prazo'] ?? '');
$registroEdicao = (string) ($edicao['registro_evento'] ?? '');
$turmasMarcadas = [];
foreach (($edicao['turmas'] ?? []) as $turmaMarcada) {
    $turmasMarcadas[(int) $turmaMarcada] = true;
}

$catalogoJs = [
    'professores' => array_map(static function (array $professor): array {
        $nomes = json_decode((string) ($professor['materias'] ?? '[]'), true);
        return [
            'id' => (int) ($professor['id'] ?? 0),
            'nome' => (string) ($professor['nome'] ?? ''),
            'materias' => is_array($nomes) ? array_values($nomes) : [],
        ];
    }, $professores),
    'materias' => array_map(static function (array $materia): array {
        return [
            'id' => (int) ($materia['id'] ?? 0),
            'nome' => (string) ($materia['nome'] ?? ''),
        ];
    }, $materias),
    'turmas' => array_map(static function (array $turma): array {
        return [
            'id' => (int) ($turma['id'] ?? 0),
            'nome' => (string) ($turma['nome'] ?? ''),
            'serie' => (string) ($turma['serie'] ?? ''),
            'serie_id' => (int) ($turma['serie_id'] ?? 0),
        ];
    }, $turmas),
];

$page_header_back_url = URL . '/admin/eventos-avaliativos?ano=' . $ano . '&periodo=' . (int) ($bimestre_selecionado ?? 0);
$page_header_title = $editando ? 'Editar evento avaliativo' : 'Novo evento avaliativo';
$page_header_subtitle = $editando
    ? 'Identidade, estrutura e revisão antes de salvar.'
    : 'Identidade, estrutura e revisão antes de criar.';
include \App\Core\ViewPaths::adminPartial('page_header_form.php');
include \App\Core\ViewPaths::adminPartial('flash_message.php');

$ui = \App\Core\ViewPaths::adminPartial('ui');
$ui_wizard_steps = [
    ['label' => 'Identidade', 'sub' => 'Evento'],
    ['label' => 'Estrutura', 'sub' => 'Peças'],
    ['label' => 'Revisão', 'sub' => 'Confirmar'],
];

$gruposEnsino = [];
foreach ($turmas as $turmaEnsino) {
    $rotuloEnsino = \App\Modulos\Fechamento\Services\ImpressaoLoteFechamentoService::segmentoDaTurma($turmaEnsino);
    if ($rotuloEnsino === '') {
        $rotuloEnsino = 'Outras';
    }
    $gruposEnsino[$rotuloEnsino][] = $turmaEnsino;
}
ksort($gruposEnsino, SORT_NATURAL | SORT_FLAG_CASE);
$umSoEnsino = count($gruposEnsino) <= 1;
$ensinoEdicao = '';
if ($editando) {
    foreach ($gruposEnsino as $rotuloEnsino => $listaEnsino) {
        foreach ($listaEnsino as $turmaEnsinoMarcada) {
            if (isset($turmasMarcadas[(int) ($turmaEnsinoMarcada['id'] ?? 0)])) {
                $ensinoEdicao = (string) $rotuloEnsino;
                break 2;
            }
        }
    }
    if (is_array($edicao)) {
        $edicao['ensino'] = $ensinoEdicao;
    }
}
$ui_wizard_current = 1;
$ui_wizard_nav_id = 'evento-wizard-steps';
?>

<form method="POST" action="<?= URL ?>/admin/eventos-avaliativos<?= $editando ? '/' . $eventoIdForm : '' ?>" id="form-evento-avaliativo" class="space-y-6" novalidate<?= $editando ? ' data-edicao="1"' : '' ?>>
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="origem" id="campo-origem" value="lancamento_direto">
    <input type="hidden" name="incluir_media" id="incluir_media" value="1">

    <?php include $ui . '/wizard_steps.php'; ?>

    <div id="evento-wizard-shell" class="w-full flex flex-col">
        <div id="evento-wizard-erro" class="hidden mb-4 p-4 rounded-lg border border-red-200 bg-red-50 text-red-800 text-sm"></div>

        <div class="space-y-6" data-wizard-panel="1">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
                <div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">1</span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Identidade</p>
                        <p class="text-sm text-gray-500 mt-0.5">Nome, ano e período deste evento.</p>
                    </div>
                </div>
                <div class="p-5">
            <div class="mb-6 grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_11rem] gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2" for="nome">Nome <span class="text-red-500">*</span></label>
                    <input type="text" name="nome" id="nome" maxlength="120"
                           value="<?= htmlspecialchars((string) ($edicao['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                           placeholder="Ex.: Prova Bimestral">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2" for="sigla">Sigla</label>
                    <input type="text" name="sigla" id="sigla" maxlength="12"
                           value="<?= htmlspecialchars((string) ($edicao['sigla'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent uppercase"
                           placeholder="Ex.: PB" autocomplete="off">
                    <p class="text-xs text-gray-500 mt-1">Aparece na fórmula das notas.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2" for="ano_letivo">Ano letivo</label>
                    <input type="hidden" name="ano_letivo" value="<?= $ano ?>">
                    <select id="ano_letivo" disabled
                            class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-100 text-gray-600 cursor-not-allowed">
                        <option value="<?= $ano ?>" selected><?= $ano ?></option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2" for="bimestre">Período</label>
                    <input type="hidden" name="bimestre" value="<?= $periodoSel ?>">
                    <select id="bimestre" disabled
                            class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-100 text-gray-600 cursor-not-allowed">
                        <option value="<?= $periodoSel ?>" selected><?= htmlspecialchars($rotuloPeriodoSel, ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>
            </div>

            <label class="mt-6 flex items-start gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
                <input type="checkbox" name="liberar_nota_aluno" id="liberar_nota_aluno" value="1"
                       class="mt-0.5 rounded border-gray-300 text-teal-600 focus:ring-teal-500"<?= !empty($edicao['liberar_nota_aluno']) ? ' checked' : '' ?>>
                <span>
                    <span class="block text-sm font-medium text-gray-900">Disponibilizar para o aluno ao lançar a nota</span>
                    <span class="block text-xs text-gray-500 mt-0.5">O aluno vê cada nota deste evento assim que ela é lançada. A média do período só aparece quando o boletim for gerado.</span>
                </span>
            </label>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
                <div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">2</span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Turmas <span class="text-red-500">*</span></p>
                        <p class="text-sm text-gray-500 mt-0.5">Escolha o ensino e marque as turmas deste evento.</p>
                    </div>
                </div>
                <div class="p-5">
                <?php if (!$umSoEnsino): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
                    <?php foreach ($gruposEnsino as $rotuloEnsino => $listaEnsino): ?>
                    <label class="flex items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 cursor-pointer hover:border-gray-300 hover:bg-white">
                        <input type="radio" name="filtro_ensino" value="<?= htmlspecialchars((string) $rotuloEnsino, ENT_QUOTES, 'UTF-8') ?>" class="js-filtro-ensino mt-1"<?= $ensinoEdicao !== '' && $ensinoEdicao === (string) $rotuloEnsino ? ' checked' : '' ?>>
                        <span>
                            <span class="block text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $rotuloEnsino, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="block text-xs text-gray-500 mt-0.5"><?= count($listaEnsino) === 1 ? '1 turma' : count($listaEnsino) . ' turmas' ?></span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($turmas === []): ?>
                    <p class="text-sm text-gray-500">Nenhuma turma ativa.</p>
                <?php else: ?>
                    <p id="turmas-ensino-vazio" class="text-sm text-gray-500 <?= $umSoEnsino ? 'hidden' : '' ?>">Escolha o ensino para ver as turmas.</p>
                    <div id="turmas-ensino-lista" class="<?= $umSoEnsino ? '' : 'hidden' ?>">
                        <div class="mb-2 flex justify-end">
                            <button type="button" id="btn-marcar-turmas-ensino" class="text-xs font-medium text-purple-700 hover:text-purple-900">Marcar todas</button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            <?php foreach ($gruposEnsino as $rotuloEnsino => $listaEnsino): ?>
                                <?php foreach ($listaEnsino as $turma): ?>
                                <label class="js-turma-item flex items-start gap-2 rounded-lg border border-gray-200 p-3 hover:bg-gray-50 cursor-pointer <?= $umSoEnsino ? '' : 'hidden' ?>"
                                       data-ensino="<?= htmlspecialchars((string) $rotuloEnsino, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="checkbox" name="turmas[]" value="<?= (int) ($turma['id'] ?? 0) ?>" class="js-turma mt-1 rounded border-gray-300"<?= isset($turmasMarcadas[(int) ($turma['id'] ?? 0)]) ? ' checked' : '' ?>>
                                    <span class="min-w-0">
                                        <span class="js-turma-nome block text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($turma['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($turma['serie'])): ?>
                                            <span class="block text-xs text-gray-500"><?= htmlspecialchars((string) $turma['serie'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php
                $periodoEhRecuperacao = $periodoSel >= 60;
                $alunosRecuperacaoMarcados = [];
                foreach ((array) ($edicao['alunos_recuperacao'] ?? []) as $itemRec) {
                    if (is_array($itemRec)) {
                        $alunoRecId = (int) ($itemRec['aluno_id'] ?? 0);
                        $materiaRecId = (int) ($itemRec['materia_id'] ?? 0);
                    } else {
                        $alunoRecId = (int) $itemRec;
                        $materiaRecId = 0;
                    }
                    if ($alunoRecId > 0) {
                        $alunosRecuperacaoMarcados[] = $alunoRecId . ':' . $materiaRecId;
                    }
                }
                ?>
                <?php if ($periodoEhRecuperacao): ?>
                <div id="painel-recuperacao" class="mt-5 rounded-lg border border-gray-200 bg-gray-50 p-4"
                     data-ano="<?= $ano ?>"
                     data-periodo="<?= $periodoSel ?>"
                     data-regra="<?= (int) ($regra_id ?? 0) ?>"
                     data-marcados="<?= htmlspecialchars(implode(',', $alunosRecuperacaoMarcados), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Quem faz a recuperação</p>
                            <p class="mt-0.5 text-xs text-gray-500">Cada matéria fica separada. A linha vermelha é só a matéria em que o aluno ficou abaixo. Nas outras, marque só quem quiser subir a nota.</p>
                        </div>
                        <button type="button" id="btn-puxar-abaixo" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Puxar alunos abaixo da média
                        </button>
                    </div>
                    <input type="hidden" name="definir_alunos_recuperacao" id="definir-alunos-recuperacao" value="<?= $alunosRecuperacaoMarcados !== [] ? '1' : '0' ?>">
                    <p id="recuperacao-resumo" class="mt-3 text-xs text-gray-500">Marque as turmas para listar os alunos.</p>
                    <div id="recuperacao-lista" class="mt-4"></div>
                </div>
                <?php endif; ?>
                <label class="mt-5 flex items-start gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" name="disponibilizar_calendario" id="disponibilizar_calendario" value="1"
                           class="mt-0.5 rounded border-gray-300 text-teal-600 focus:ring-teal-500"<?= !empty($edicao['no_calendario']) ? ' checked' : '' ?>>
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Disponibilizar no calendário letivo</span>
                        <span class="block text-xs text-gray-500 mt-0.5">Cada data entra no calendário do curso das turmas marcadas. Curso sem calendário neste ano impede de salvar com esta opção.</span>
                    </span>
                </label>
                </div>
            </div>
        </div>

        <div class="hidden space-y-6" data-wizard-panel="2">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
                <div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">1</span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Tipo de Avaliação</p>
                        <p class="text-sm text-gray-500 mt-0.5">Defina se a avaliação será online (via plataforma) ou offline (presencial).</p>
                    </div>
                </div>
                <div class="p-5">
                <div class="space-y-3">
                    <div class="rounded-lg border border-gray-200 p-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="radio" name="canal_origem" value="online" class="mt-1 js-canal"<?= $canalEdicao === 'online' ? ' checked' : '' ?>>
                            <span>
                                <span class="block text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($canais['online']['label'] ?? 'Avaliação online'), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="block text-xs text-gray-500 mt-0.5"><?= htmlspecialchars((string) ($canais['online']['hint'] ?? 'Ocorre dentro da plataforma — o aluno faz a prova no sistema.'), ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                        </label>
                        <div id="visivelPortalBox" class="hidden mt-3 ml-7 p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <label class="flex items-start cursor-pointer">
                                <input type="checkbox" id="visivel_no_portal_aluno" name="visivel_no_portal_aluno" value="1" class="mt-1 w-4 h-4 text-purple-600 border-gray-300 rounded"<?= !empty($edicao['visivel_portal']) ? ' checked' : '' ?>>
                                <span class="ml-3">
                                    <span class="block text-sm font-medium text-gray-900">Mostrar este evento no portal do aluno</span>
                                    <span class="block text-xs text-gray-600 mt-1">Desmarcado: o aluno não vê em "Minhas provas" nem acessa por link. A coordenação pode ativar depois na edição do evento.</span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="canal_origem" value="offline" class="mt-1 js-canal"<?= $canalEdicao !== 'online' ? ' checked' : '' ?>>
                        <span>
                            <span class="block text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($canais['offline']['label'] ?? 'Avaliações offline'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="block text-xs text-gray-500 mt-0.5"><?= htmlspecialchars((string) ($canais['offline']['hint'] ?? 'Ocorre fora da plataforma — a escola informa a nota.'), ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                    </label>
                </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">2</span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Disciplina <span class="text-red-500">*</span></p>
                            <p class="text-xs text-gray-500 mt-0.5 js-hint-par">O resumo fica aqui. No painel, professor e disciplina se filtram um pelo outro.</p>
                        </div>
                    </div>
                    <button type="button" id="btn-add-par-padrao" class="btn-primary-custom px-4 py-2 text-sm font-semibold rounded-lg">+ Adicionar disciplina</button>
                </div>
                <div class="p-5">
                <div id="pares-padrao" data-prefix="pares_padrao" data-par="0">
                    <div class="js-pares space-y-3"></div>
                </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden" id="responsavelEventoBox">
                <div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">3</span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900" id="labelResponsavelEvento">Quem lança a nota <span class="text-red-500">*</span></p>
                        <p class="text-sm text-gray-500 mt-0.5" id="helpResponsavelEvento">Atribua ao professor ou deixe a escola lançar a nota. O professor é opcional.</p>
                    </div>
                </div>
                <div class="p-5">
                <div id="cfgOnlineOptions" class="hidden flex flex-wrap gap-6">
                    <label class="flex items-center"><input type="radio" name="configuracao_nota" value="professor_por_questao" class="js-config w-4 h-4 text-purple-600 border-gray-300"<?= $canalEdicao === 'online' && $cfgEdicao === 'professor_por_questao' ? ' checked' : '' ?>> <span class="ml-2 text-gray-700">Atribuir professor</span></label>
                    <label class="flex items-center"><input type="radio" name="configuracao_nota" value="coordenacao_calcula" class="js-config w-4 h-4 text-purple-600 border-gray-300"<?= $canalEdicao === 'online' && $cfgEdicao === 'coordenacao_calcula' ? ' checked' : '' ?>> <span class="ml-2 text-gray-700">Coordenação faz a prova</span></label>
                </div>
                <div id="cfgLancamentoOptions" class="flex flex-wrap gap-6">
                    <label class="flex items-center"><input type="radio" name="configuracao_nota" value="professor_por_questao" class="js-config w-4 h-4 text-purple-600 border-gray-300"<?= $canalEdicao !== 'online' && $cfgEdicao === 'professor_por_questao' ? ' checked' : '' ?>> <span class="ml-2 text-gray-700">Atribuir ao professor</span></label>
                    <label class="flex items-center"><input type="radio" name="configuracao_nota" value="coordenacao_calcula" class="js-config w-4 h-4 text-purple-600 border-gray-300"<?= $canalEdicao !== 'online' && $cfgEdicao === 'coordenacao_calcula' ? ' checked' : '' ?>> <span class="ml-2 text-gray-700">Escola lança a nota</span></label>
                </div>
                <div id="prazoProfessorContainer" class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2" id="labelPrazoProfessor" for="prazo_entrega_professor">Prazo para o professor lançar as notas <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="prazo_entrega_professor" id="prazo_entrega_professor" value="<?= htmlspecialchars($prazoEdicao, ENT_QUOTES, 'UTF-8') ?>" class="w-full max-w-xs px-4 py-2 border border-gray-300 rounded-lg bg-white">
                    <p class="text-xs text-gray-500 mt-1">Após este prazo, provas não enviadas serão automaticamente marcadas como "Não Enviadas" e travadas.</p>
                </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden" id="periodicidadeCard">
                <div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">4</span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Periodicidade</p>
                        <p class="text-sm text-gray-500 mt-0.5">Vale para avaliação online e offline. Evento único acontece uma vez. Eventos múltiplos repetem em várias datas.</p>
                    </div>
                </div>
                <div class="p-5">
                <div class="mb-4 space-y-3">
                    <?php foreach ($modosOffline as $modoKey => $modo): ?>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 hover:border-gray-300 hover:bg-white">
                        <div class="flex items-center justify-between gap-3">
                            <label class="flex shrink-0 cursor-pointer items-center gap-3">
                                <input type="radio" name="modo_offline" value="<?= htmlspecialchars((string) $modoKey, ENT_QUOTES, 'UTF-8') ?>" class="js-modo-offline"
                                       <?= (string) $modoKey === $modoEdicao ? 'checked' : '' ?>>
                                <span class="whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($modo['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                            <?php if ((string) $modoKey === 'eventos'): ?>
                            <label for="quantidade-subeventos" class="js-qtd-subeventos flex shrink-0 items-center gap-2<?= (string) $modoEdicao === 'eventos' ? '' : ' hidden' ?>">
                                <span class="text-xs font-medium text-gray-600 whitespace-nowrap">Quantidade de subeventos</span>
                                <input type="number" id="quantidade-subeventos" min="1" max="20" value="1" inputmode="numeric"
                                       class="w-20 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm">
                            </label>
                            <?php endif; ?>
                        </div>
                        <p class="mt-1 pl-7 text-xs text-gray-500"><?= htmlspecialchars((string) ($modo['hint'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="quantidade_eventos_esperada" id="quantidade_eventos_esperada" value="1">

                <div id="painel-unico" class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-800 mb-2" for="data-unica">Data da avaliação</label>
                            <input type="date" id="data-unica" readonly class="js-data-calendario w-full cursor-pointer px-4 py-2.5 border border-gray-300 rounded-lg bg-white text-sm">
                        </div>
                        <div class="js-horas-online hidden">
                            <label class="block text-sm font-medium text-gray-800 mb-2" for="hora_inicio">Horário de início <span class="text-red-500">*</span></label>
                            <input type="time" id="hora_inicio" value="08:00" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-white text-sm">
                        </div>
                        <div class="js-horas-online hidden">
                            <label class="block text-sm font-medium text-gray-800 mb-2" for="hora_fim">Horário de término <span class="text-red-500">*</span></label>
                            <input type="time" id="hora_fim" value="18:00" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-white text-sm">
                        </div>
                    </div>
                    <div id="agenda-unica" class="mt-4"></div>
                </div>

                <div id="painel-multiplos" class="hidden">
                    <div class="mb-3">
                        <p class="text-sm font-medium text-gray-800">Subeventos</p>
                        <p class="text-xs text-gray-500 mt-0.5">Cada subevento tem a própria data. Série ou turma pode ter outro horário.</p>
                    </div>
                    <div id="lista-subeventos" class="space-y-4"></div>
                    <p id="lista-subeventos-vazia" class="hidden rounded-xl border border-dashed border-gray-200 px-4 py-6 text-sm text-gray-500">Informe a quantidade de subeventos.</p>
                </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden" id="calculoNotaCard">
                <div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">5</span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Como esta nota é calculada</p>
                        <p class="text-sm text-gray-500 mt-0.5">As provas deste evento viram uma nota só. O boletim mostra essa nota, sem somar as provas de novo.</p>
                    </div>
                </div>
                <div class="p-5">

                <div class="mb-5 js-bloco-eventos hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2" for="registro_evento">O que cada evento registra</label>
                    <select name="registro_evento" id="registro_evento" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white">
                        <?php foreach ($registros as $chave => $rotulo): ?>
                            <option value="<?= htmlspecialchars((string) $chave, ENT_QUOTES, 'UTF-8') ?>"<?= $registroEdicao !== '' && (string) $chave === $registroEdicao ? ' selected' : '' ?>><?= htmlspecialchars((string) $rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="criterio_fechamento">Tipo de nota do evento avaliativo</label>
                        <select name="criterio_fechamento" id="criterio_fechamento" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white">
                            <?php foreach ($criterios as $chave => $rotulo): ?>
                                <option value="<?= htmlspecialchars((string) $chave, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $chave === $criterioEdicao ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $rotulo) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1 js-hint-nq hidden">Soma todos os acertos e todas as questões do período, depois converte para a escala. Não é a média das notas 0–10 de cada prova.</p>
                        <div id="notaUnicaTodasMateriasBox" class="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-lg hidden">
                            <label class="flex items-start cursor-pointer">
                                <input type="checkbox" id="nota_unica_todas_materias" name="nota_unica_todas_materias" value="1" class="mt-1 w-4 h-4 text-teal-600 border-gray-300 rounded"<?= !empty($edicao['nota_unica']) ? ' checked' : '' ?>>
                                <span class="ml-3">
                                    <span class="block text-sm font-medium text-gray-900">Mesma nota em todas as matérias do evento</span>
                                    <span class="block text-xs text-gray-600 mt-1">Ao lançar a nota do aluno em uma matéria, a mesma nota vale para as demais matérias do evento. A nota lançada pela escola se repete nas outras matérias somente com esta opção marcada.</span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="escala_max">Escala (Nota Máxima)</label>
                        <input type="number" name="escala_max" id="escala_max" min="1" max="100" step="0.01" value="<?= htmlspecialchars($escalaEdicao, ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2" for="criterio_professores_mesmo_componente">Professores da mesma disciplina (que dividem frente)</label>
                    <select name="criterio_professores_mesmo_componente" id="criterio_professores_mesmo_componente"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white">
                        <?php foreach ($criteriosProfessores as $chave => $rotulo): ?>
                            <option value="<?= htmlspecialchars((string) $chave, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $chave === $criterioProfEdicao ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $rotulo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Ex.: um professor lança 4 e o outro 6 — média dá 5,0; soma dá 10,0 (limitada à escala).</p>
                </div>
                </div>
            </div>
        </div>

        <div class="hidden space-y-6" data-wizard-panel="3">
            <div id="evento-revisao" class="space-y-6"></div>
        </div>

        <div id="evento-wizard-rodape" class="flex items-center justify-between pt-6 mt-6">
            <button type="button" id="evento-wizard-voltar" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">
                <i class="fa-solid fa-arrow-left mr-2"></i>Voltar
            </button>
            <div class="flex gap-2">
                <a href="<?= URL ?>/admin/eventos-avaliativos" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm">Cancelar</a>
                <button type="button" id="evento-wizard-avancar" class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
                    Continuar <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>
    </div>

    <div id="disciplinaBackdrop" class="fixed inset-0 bg-black/40 z-[70] hidden"></div>
    <aside id="disciplinaDrawer"
           class="fixed top-0 right-0 z-[80] flex h-full w-full max-w-lg translate-x-full flex-col bg-white shadow-2xl transition-transform duration-300 ease-in-out"
           aria-hidden="true" aria-labelledby="disciplinaDrawerTitulo">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
            <div>
                <h3 id="disciplinaDrawerTitulo" class="text-base font-semibold text-gray-900">Disciplina</h3>
                <p class="mt-0.5 text-xs text-gray-500">Professor, disciplina e turmas desta linha.</p>
            </div>
            <button type="button" id="btn-fechar-disciplina" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="Fechar">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <div id="disciplinaDrawerCorpo" class="flex-1 overflow-y-auto px-5 py-5"></div>
        <div class="border-t border-gray-200 bg-gray-50 px-5 py-4">
            <button type="button" id="btn-concluir-disciplina" class="w-full rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">Concluir</button>
        </div>
    </aside>
</form>

<div id="calendarioBackdrop" class="fixed inset-0 bg-black/40 z-[70] hidden"></div>
<aside id="calendarioDrawer"
       class="fixed top-0 right-0 z-[80] flex h-full w-full max-w-sm translate-x-full flex-col bg-white shadow-2xl transition-transform duration-300 ease-in-out"
       aria-hidden="true" aria-labelledby="calendarioDrawerTitulo">
    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
        <div>
            <h3 id="calendarioDrawerTitulo" class="text-base font-semibold text-gray-900">Escolher data</h3>
            <p id="calendario-contador" class="mt-0.5 text-xs text-gray-500">Selecione o dia</p>
        </div>
        <button type="button" id="btn-fechar-calendario" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto px-5 py-5">
        <div id="calendario-periodicidade"></div>
        <div id="calendario-chips" class="mt-5 flex flex-wrap gap-2"></div>
        <p class="mt-4 text-xs leading-relaxed text-gray-500">Clique no dia para preencher a data deste campo.</p>
    </div>
    <div class="border-t border-gray-200 bg-gray-50 px-5 py-4">
        <button type="button" id="btn-concluir-calendario" class="w-full rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">Concluir</button>
    </div>
</aside>

<style>
#evento-wizard-steps { align-items: center; }
#evento-wizard-steps .step-nav-btn { flex: 1 1 0; min-width: 0; height: 4.75rem; }
#evento-wizard-steps .step-nav-btn .font-semibold,
#evento-wizard-steps .step-nav-btn .text-xs { white-space: nowrap; }
#evento-wizard-steps [data-connector-after] { flex: 0 0 1.75rem; min-width: 1.75rem; align-self: center; }
.ev-preview { border-collapse: collapse; width: 100%; font-size: 12px; text-align: center; }
.ev-preview th { background: var(--sidebar-bg-color, #1e3a5f); color: var(--sidebar-text-color, #fff); border: 1px solid color-mix(in srgb, var(--sidebar-bg-color, #1e3a5f) 75%, #000); padding: 6px 8px; font-weight: 600; }
.ev-preview td { border: 1px solid #d1d5db; padding: 6px 8px; }
.ev-preview td.mat { text-align: left; font-weight: 600; color: #111827; white-space: nowrap; }
.ev-preview tr:nth-child(even) td { background: #f8fafc; }
</style>

<script>
(function () {
    var form = document.getElementById('form-evento-avaliativo');
    var lista = document.getElementById('lista-subeventos');
    var erroEl = document.getElementById('evento-wizard-erro');
    var btnVoltar = document.getElementById('evento-wizard-voltar');
    var btnAvancar = document.getElementById('evento-wizard-avancar');
    var nav = document.getElementById('evento-wizard-steps');
    var revisao = document.getElementById('evento-revisao');
    var catalogo = <?= json_encode($catalogoJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var edicao = <?= json_encode($edicao, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var amostras = [8, 7.5, 9, 6.5, 8.5, 7, 9.5, 6];
    var atual = 1;
    var erroPasso = 0;
    var seq = 0;
    var estadosCls = {
        ativo: ['border-accent', 'bg-primary', 'text-primary', 'shadow-md'],
        completo: ['border-green-500', 'bg-green-50', 'text-green-700'],
        erro: ['border-red-400', 'bg-red-50', 'text-red-700'],
        pendente: ['border-gray-200', 'bg-white', 'text-gray-600']
    };
    var todasCls = estadosCls.ativo.concat(estadosCls.completo, estadosCls.erro, estadosCls.pendente);

    function esc(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function nota(n) {
        return n.toFixed(2).replace('.', ',');
    }

    function dataBr(iso) {
        var p = String(iso || '').split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : '';
    }

    function textoSelecionado(id) {
        var el = document.getElementById(id);
        if (!el || !el.options || el.selectedIndex < 0) return '';
        return el.options[el.selectedIndex].text || '';
    }

    function formatoAtual() {
        var el = form.querySelector('input[name="canal_origem"]:checked');
        return el && el.value === 'online' ? 'online_questoes' : 'lancamento_nota';
    }
    function caixaConfig() {
        return document.getElementById(formatoAtual() === 'online_questoes' ? 'cfgOnlineOptions' : 'cfgLancamentoOptions');
    }
    function configAtual() {
        var box = caixaConfig();
        var el = box ? box.querySelector('input[name="configuracao_nota"]:checked') : null;
        return el ? el.value : 'professor_por_questao';
    }
    function professorOpcional() {
        return configAtual() === 'coordenacao_calcula';
    }
    function exigeQuestoes() {
        return formatoAtual() === 'online_questoes' && configAtual() === 'professor_por_questao';
    }
    function turmasEvento() {
        var ids = Array.from(form.querySelectorAll('.js-turma:checked')).map(function (el) { return String(el.value); });
        return (catalogo.turmas || []).filter(function (t) { return ids.indexOf(String(t.id)) !== -1; });
    }
    function opcoes(itens, valor, vazio) {
        var html = '<option value="">' + esc(vazio) + '</option>';
        (itens || []).forEach(function (item) {
            html += '<option value="' + esc(item.id) + '"' + (String(item.id) === String(valor || '') ? ' selected' : '') + '>' + esc(item.nome) + '</option>';
        });
        return html;
    }
    function chaveNome(nome) {
        return String(nome || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR').trim();
    }
    function professorEnsina(prof, materia) {
        var lista = prof && Array.isArray(prof.materias) ? prof.materias : [];
        var idAlvo = Number(materia && materia.id);
        var nomeAlvo = chaveNome(materia && materia.nome);
        return lista.some(function (item) {
            if (item && typeof item === 'object') {
                var idObj = Number(item.id || item.materia_id || 0);
                if (idObj > 0 && idObj === idAlvo) return true;
                return chaveNome(item.nome) === nomeAlvo;
            }
            if (typeof item === 'number' || /^\d+$/.test(String(item))) {
                return Number(item) === idAlvo;
            }
            return chaveNome(item) === nomeAlvo;
        });
    }
    function materiasDoProfessor(profId) {
        if (!profId) return catalogo.materias || [];
        var prof = (catalogo.professores || []).filter(function (p) { return String(p.id) === String(profId); })[0];
        if (!prof || !prof.materias || !prof.materias.length) return catalogo.materias || [];
        var filtradas = (catalogo.materias || []).filter(function (m) { return professorEnsina(prof, m); });
        return filtradas.length ? filtradas : (catalogo.materias || []);
    }
    function professoresDaDisciplina(materiaId) {
        if (!materiaId) return catalogo.professores || [];
        var mat = (catalogo.materias || []).filter(function (m) { return String(m.id) === String(materiaId); })[0];
        if (!mat) return [];
        return (catalogo.professores || []).filter(function (p) { return professorEnsina(p, mat); });
    }
    function preencherMaterias(sel, profId, materiaId) {
        if (!sel) return;
        sel.innerHTML = opcoes(materiasDoProfessor(profId), materiaId, 'Selecione a disciplina');
        if (materiaId && Array.prototype.some.call(sel.options, function (o) { return String(o.value) === String(materiaId); })) {
            sel.value = String(materiaId);
        }
    }
    function preencherProfessores(sel, materiaId, professorId) {
        if (!sel) return;
        var vazio = professorOpcional() ? 'Nenhum (opcional)' : 'Selecione o professor';
        sel.innerHTML = opcoes(professoresDaDisciplina(materiaId), professorId, vazio);
        if (professorId && Array.prototype.some.call(sel.options, function (o) { return String(o.value) === String(professorId); })) {
            sel.value = String(professorId);
        }
    }
    function htmlTurmasPar(prefixo, seqPar, marcadas) {
        var turmas = turmasEvento();
        if (!turmas.length) {
            return '<p class="text-sm text-gray-500 col-span-full">Selecione as turmas do evento para liberar as turmas deste professor.</p>';
        }
        return turmas.map(function (t) {
            var on = (marcadas || []).indexOf(String(t.id)) !== -1 ? ' checked' : '';
            var serie = t.serie ? '<span class="block text-[11px] text-gray-500 leading-tight">' + esc(t.serie) + '</span>' : '';
            return '<label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm cursor-pointer hover:border-gray-300">'
                + '<input type="checkbox" name="' + prefixo + '[' + seqPar + '][turmas][]" value="' + esc(t.id) + '"' + on + ' class="js-turma-par rounded border-gray-300">'
                + '<span class="min-w-0"><span class="block font-medium text-gray-800 leading-tight">' + esc(t.nome) + '</span>' + serie + '</span></label>';
        }).join('');
    }
    var parUid = 0;
    var parAberto = null;
    function camposDoPar(card) {
        if (!card) return null;
        var local = card.querySelector('.js-par-campos');
        if (local) return local;
        var corpo = document.getElementById('disciplinaDrawerCorpo');
        var movido = corpo ? corpo.querySelector('.js-par-campos') : null;
        if (movido && movido.getAttribute('data-uid') === card.getAttribute('data-uid')) return movido;
        return null;
    }
    function pintarResumoPar(card) {
        var campos = card.querySelector('.js-par-campos');
        var titulo = card.querySelector('.js-par-titulo');
        var resumo = card.querySelector('.js-par-resumo');
        if (!campos || !titulo || !resumo) return;
        var mat = campos.querySelector('.js-mat');
        var prof = campos.querySelector('.js-prof');
        var nomeMat = mat && mat.value ? ((mat.selectedOptions[0] || {}).textContent || '').trim() : '';
        var nomeProf = prof && prof.value && !prof.disabled ? ((prof.selectedOptions[0] || {}).textContent || '').trim() : '';
        var turmas = Array.from(campos.querySelectorAll('.js-turma-par:checked')).map(function (el) {
            var nome = el.parentElement ? el.parentElement.querySelector('.font-medium') : null;
            return nome ? nome.textContent.trim() : '';
        }).filter(Boolean);
        titulo.textContent = nomeMat || ('Disciplina ' + card.getAttribute('data-par'));
        if (!nomeMat && !nomeProf && !turmas.length) {
            resumo.textContent = 'Defina professor, disciplina e turmas.';
            return;
        }
        var partes = [];
        partes.push(nomeProf || (professorOpcional() ? 'Sem professor' : 'Professor não definido'));
        partes.push(turmas.length ? turmas.join(', ') : 'Nenhuma turma');
        resumo.textContent = partes.join(' · ');
    }
    function guardarDisciplina() {
        if (!parAberto) return;
        var corpo = document.getElementById('disciplinaDrawerCorpo');
        var campos = corpo ? corpo.querySelector('.js-par-campos') : null;
        if (campos) {
            campos.classList.add('hidden');
            parAberto.appendChild(campos);
        }
        pintarResumoPar(parAberto);
        parAberto = null;
    }
    function fecharDisciplina() {
        guardarDisciplina();
        var backdrop = document.getElementById('disciplinaBackdrop');
        var drawer = document.getElementById('disciplinaDrawer');
        if (backdrop) backdrop.classList.add('hidden');
        if (drawer) {
            drawer.classList.add('translate-x-full');
            drawer.setAttribute('aria-hidden', 'true');
        }
    }
    function abrirDisciplina(card) {
        if (parAberto === card) return;
        guardarDisciplina();
        var campos = card.querySelector('.js-par-campos');
        var corpo = document.getElementById('disciplinaDrawerCorpo');
        var drawer = document.getElementById('disciplinaDrawer');
        var backdrop = document.getElementById('disciplinaBackdrop');
        if (!campos || !corpo || !drawer || !backdrop) return;
        parAberto = card;
        campos.classList.remove('hidden');
        corpo.appendChild(campos);
        var titulo = document.getElementById('disciplinaDrawerTitulo');
        var nome = (card.querySelector('.js-par-titulo') || {}).textContent || 'Disciplina';
        if (titulo) titulo.textContent = nome;
        backdrop.classList.remove('hidden');
        drawer.classList.remove('translate-x-full');
        drawer.setAttribute('aria-hidden', 'false');
        atualizarParesUi();
    }
    function adicionarPar(row, dados, abrir) {
        dados = dados || {};
        var prefixo = row.getAttribute('data-prefix');
        var seqPar = parseInt(row.getAttribute('data-par') || '0', 10) + 1;
        var uid = String(++parUid);
        row.setAttribute('data-par', String(seqPar));
        var card = document.createElement('div');
        card.className = 'js-par rounded-xl border border-gray-200 bg-white p-3';
        card.setAttribute('data-par', String(seqPar));
        card.setAttribute('data-uid', uid);
        card.innerHTML =
            '<div class="flex items-center gap-3">'
                + '<button type="button" class="js-editar-par min-w-0 flex-1 text-left">'
                    + '<span class="js-par-titulo block text-sm font-medium text-gray-900">Disciplina ' + seqPar + '</span>'
                    + '<span class="js-par-resumo block text-xs text-gray-500 mt-0.5">Defina professor, disciplina e turmas.</span>'
                + '</button>'
                + '<button type="button" class="js-remover-par shrink-0 text-xs font-medium text-gray-400 hover:text-red-600">Remover</button>'
            + '</div>'
            + '<div class="js-par-campos hidden" data-uid="' + uid + '">'
                + '<div class="grid grid-cols-1 gap-4 mb-4">'
                    + '<div class="js-campo-prof"><label class="block text-xs font-medium text-gray-500 mb-1.5">Professor <span class="js-req-prof text-red-500">*</span></label>'
                        + '<select name="' + prefixo + '[' + seqPar + '][professor_id]" class="js-prof w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">'
                        + opcoes(professoresDaDisciplina(dados.materia_id), dados.professor_id, 'Selecione o professor') + '</select></div>'
                    + '<div class="js-campo-disc"><label class="block text-xs font-medium text-gray-500 mb-1.5">Disciplina <span class="text-red-500">*</span></label>'
                        + '<select name="' + prefixo + '[' + seqPar + '][materia_id]" class="js-mat w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">'
                        + opcoes(materiasDoProfessor(dados.professor_id), dados.materia_id, 'Selecione a disciplina') + '</select></div>'
                + '</div>'
                + '<div class="js-campo-qtd mb-4"><label class="block text-xs font-medium text-gray-500 mb-1.5">Número de questões <span class="text-red-500">*</span></label>'
                    + '<input type="number" min="1" value="' + esc(dados.qtd || '5') + '" name="' + prefixo + '[' + seqPar + '][quantidade_questoes]" class="js-qtd w-28 px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm"></div>'
                + '<div class="flex items-center justify-between gap-3 mb-2">'
                    + '<span class="text-xs font-medium text-gray-500">Turmas <span class="text-red-500">*</span></span>'
                    + '<button type="button" class="js-marcar-turmas text-xs font-medium text-purple-700 hover:text-purple-900">Marcar todas</button>'
                + '</div>'
                + '<div class="js-turmas-par grid grid-cols-1 sm:grid-cols-2 gap-2">'
                    + htmlTurmasPar(prefixo, seqPar, dados.turmas || turmasEvento().map(function (t) { return String(t.id); }))
                + '</div>'
            + '</div>';
        card.querySelector('.js-prof').addEventListener('change', function () {
            var campos = this.closest('.js-par-campos');
            var sel = campos ? campos.querySelector('.js-mat') : null;
            preencherMaterias(sel, this.value, sel ? sel.value : '');
            pintarResumoPar(card);
        });
        card.querySelector('.js-mat').addEventListener('change', function () {
            var campos = this.closest('.js-par-campos');
            var sel = campos ? campos.querySelector('.js-prof') : null;
            preencherProfessores(sel, this.value, sel ? sel.value : '');
            pintarResumoPar(card);
        });
        card.querySelector('.js-editar-par').addEventListener('click', function () { abrirDisciplina(card); });
        card.querySelector('.js-remover-par').addEventListener('click', function () {
            if (row.querySelectorAll('.js-par').length <= 1) return;
            if (parAberto === card) {
                parAberto = null;
                var corpo = document.getElementById('disciplinaDrawerCorpo');
                if (corpo) corpo.innerHTML = '';
                fecharDisciplina();
            }
            card.remove();
        });
        card.querySelector('.js-marcar-turmas').addEventListener('click', function () {
            var campos = camposDoPar(card);
            if (!campos) return;
            campos.querySelectorAll('.js-turma-par').forEach(function (el) { el.checked = true; });
        });
        row.querySelector('.js-pares').appendChild(card);
        atualizarParesUi();
        pintarResumoPar(card);
        if (abrir) abrirDisciplina(card);
    }
    function lerPares(row) {
        if (!row) return [{}];
        var pares = Array.from(row.querySelectorAll('.js-par')).map(function (card) {
            var campos = camposDoPar(card);
            if (!campos) return {};
            return {
                professor_id: (campos.querySelector('.js-prof') || {}).value || '',
                materia_id: (campos.querySelector('.js-mat') || {}).value || '',
                turmas: Array.from(campos.querySelectorAll('.js-turma-par:checked')).map(function (el) { return el.value; }),
                qtd: (campos.querySelector('.js-qtd') || {}).value || '5'
            };
        });
        return pares.length ? pares : [{}];
    }
    function estadoAgenda(row) {
        if (!row._agenda) row._agenda = { modo: 'todas', blocos: [] };
        return row._agenda;
    }
    function iniciarAgenda(row, modo, grupos) {
        var blocos = [];
        (grupos || []).forEach(function (grupo) {
            var turmas = (grupo.turmas || []).map(String).filter(Boolean);
            if (!turmas.length) return;
            blocos.push({
                turmas: turmas,
                data: grupo.data || '',
                inicio: String(grupo.hora_inicio || grupo.inicio || '').slice(0, 5),
                fim: String(grupo.hora_fim || grupo.fim || '').slice(0, 5)
            });
        });
        row._agenda = { modo: modo === 'serie' || modo === 'turma' ? modo : 'todas', blocos: blocos };
    }
    function opcoesAgenda(modo) {
        var turmas = turmasEvento();
        if (modo === 'turma') {
            return turmas.map(function (t) {
                return { chave: 't' + t.id, nome: t.nome, rotulo: t.serie ? t.nome + ' · ' + t.serie : t.nome, turmas: [String(t.id)] };
            });
        }
        var mapa = {};
        var ordem = [];
        turmas.forEach(function (t) {
            var chave = 's' + (t.serie_id || t.serie || t.id);
            if (!mapa[chave]) {
                mapa[chave] = { chave: chave, rotulo: t.serie || t.nome, turmas: [] };
                ordem.push(chave);
            }
            mapa[chave].turmas.push(String(t.id));
        });
        return ordem.map(function (chave) { return mapa[chave]; });
    }
    function sincronizarBlocos(estado, base) {
        var opcoes = estado.modo === 'todas' ? [] : opcoesAgenda(estado.modo);
        var anteriores = (estado.blocos || []).map(function (bloco) {
            return {
                turmas: (bloco.turmas || []).map(String),
                data: bloco.data || '',
                inicio: bloco.inicio || '',
                fim: bloco.fim || ''
            };
        });
        if (estado.modo === 'serie') {
            estado.blocos = opcoes.map(function (opcao) {
                var existente = null;
                anteriores.forEach(function (bloco) {
                    if (existente) return;
                    if (opcao.turmas.some(function (id) { return bloco.turmas.indexOf(String(id)) !== -1; })) existente = bloco;
                });
                return {
                    turmas: opcao.turmas.slice(),
                    data: (existente && existente.data) || base.data,
                    inicio: (existente && existente.inicio) || base.inicio,
                    fim: (existente && existente.fim) || base.fim
                };
            });
            return opcoes;
        }
        if (estado.modo === 'turma') {
            var vivos = {};
            opcoes.forEach(function (opcao) {
                opcao.turmas.forEach(function (id) { vivos[String(id)] = true; });
            });
            var vistos = {};
            estado.blocos = [];
            anteriores.forEach(function (bloco) {
                var ids = bloco.turmas.filter(function (turmaId) {
                    if (!vivos[turmaId] || vistos[turmaId]) return false;
                    vistos[turmaId] = true;
                    return true;
                });
                estado.blocos.push({
                    turmas: ids,
                    data: bloco.data || base.data,
                    inicio: bloco.inicio || base.inicio,
                    fim: bloco.fim || base.fim
                });
            });
            return opcoes;
        }
        estado.blocos = [];
        return [];
    }
    function fallbackAgenda(row) {
        var data = (row.querySelector('input[name*="[data]"]') || {}).value || (document.getElementById('data-unica') || {}).value || '';
        var inicio = (row.querySelector('.js-hora-inicio') || {}).value || (document.getElementById('hora_inicio') || {}).value || '08:00';
        var fim = (row.querySelector('.js-hora-fim') || {}).value || (document.getElementById('hora_fim') || {}).value || '18:00';
        return { data: data, inicio: inicio || '08:00', fim: fim || '18:00' };
    }
    function chipAgenda(rotulo, ids, marcada, ocupada) {
        var caixa = marcada ? 'border-teal-600 bg-teal-600 text-white' : 'border-gray-300 bg-white text-transparent';
        var pill = ocupada
            ? 'cursor-not-allowed border-gray-200 bg-gray-50 text-gray-400'
            : (marcada
                ? 'cursor-pointer border-teal-600 bg-teal-50 text-teal-900'
                : 'cursor-pointer border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50');
        if (ocupada) caixa = 'border-gray-200 bg-gray-100 text-transparent';
        return '<label class="inline-flex items-center gap-2 rounded-lg border px-2.5 py-1.5 text-sm font-medium ' + pill + '">'
            + '<input type="checkbox" class="js-agenda-opcao sr-only" data-turmas="' + esc(ids) + '"'
            + (marcada ? ' checked' : '') + (ocupada ? ' disabled' : '') + '>'
            + '<span class="flex h-4 w-4 shrink-0 items-center justify-center rounded border ' + caixa + '"><i class="fa-solid fa-check text-[10px]"></i></span>'
            + '<span>' + esc(rotulo) + '</span></label>';
    }
    function camposAgenda(prefixo, indice, bloco) {
        var html = '<div class="min-w-0 flex-1"><label class="mb-1 block text-xs font-medium text-gray-600">Data</label>'
            + '<input type="date" readonly class="js-agenda-data js-data-calendario w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" value="' + esc(bloco.data) + '"></div>';
        html += '<div class="w-28 shrink-0"><label class="mb-1 block text-xs font-medium text-gray-600">Início</label>'
            + '<input type="time" class="js-agenda-inicio w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" value="' + esc(bloco.inicio) + '"></div>';
        html += '<div class="w-28 shrink-0"><label class="mb-1 block text-xs font-medium text-gray-600">Término</label>'
            + '<input type="time" class="js-agenda-fim w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" value="' + esc(bloco.fim) + '"></div>';
        (bloco.turmas || []).forEach(function (id) {
            html += '<input type="hidden" name="' + prefixo + '[agendas][' + indice + '][turmas][]" value="' + esc(id) + '">';
        });
        if ((bloco.turmas || []).length) {
            html += '<input type="hidden" name="' + prefixo + '[agendas][' + indice + '][data]" value="' + esc(bloco.data) + '">';
            html += '<input type="hidden" name="' + prefixo + '[agendas][' + indice + '][hora_inicio]" value="' + esc(bloco.inicio) + '">';
            html += '<input type="hidden" name="' + prefixo + '[agendas][' + indice + '][hora_fim]" value="' + esc(bloco.fim) + '">';
        }
        return html;
    }
    function pintarAgenda(row, destino) {
        if (!row || !destino) return;
        var estado = estadoAgenda(row);
        var prefixo = row.getAttribute('data-agenda') || '';
        var base = fallbackAgenda(row);
        var opcoes = sincronizarBlocos(estado, base);
        var aninhada = destino.classList && destino.classList.contains('js-agenda-lugar');
        var html = '<div class="js-agenda ' + (aninhada ? 'pt-3' : 'mt-4 border-t border-gray-200 pt-4') + '">';
        html += '<input type="hidden" name="' + prefixo + '[agenda_modo]" value="' + esc(estado.modo) + '">';
        html += '<div class="mb-3 flex flex-wrap gap-2">';
        [['todas', 'Mesma data para todas'], ['serie', 'Por série / ano'], ['turma', 'Por turma']].forEach(function (opcao) {
            var ativo = estado.modo === opcao[0];
            html += '<button type="button" data-modo="' + opcao[0] + '" class="js-modo-agenda rounded-lg border px-3 py-2 text-sm font-medium '
                + (ativo ? 'border-teal-600 bg-teal-50 text-teal-800' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50') + '">'
                + esc(opcao[1]) + '</button>';
        });
        html += '</div>';
        if (estado.modo === 'todas') {
            if (aninhada) {
                html += '<div class="flex flex-nowrap items-end gap-3">';
                html += '<div class="w-40 shrink-0"><label class="mb-1 block text-xs font-medium text-gray-600">Data</label>'
                    + '<input type="date" readonly class="js-todas-data js-data-calendario w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" value="' + esc(base.data) + '"></div>';
                html += '<div class="w-28 shrink-0"><label class="mb-1 block text-xs font-medium text-gray-600">Início</label>'
                    + '<input type="time" class="js-todas-inicio w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" value="' + esc(base.inicio) + '"></div>';
                html += '<div class="w-28 shrink-0"><label class="mb-1 block text-xs font-medium text-gray-600">Término</label>'
                    + '<input type="time" class="js-todas-fim w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" value="' + esc(base.fim) + '"></div>';
                html += '</div>';
            } else {
                html += '<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">';
                html += '<div><label class="mb-2 block text-sm font-medium text-gray-800">Data da avaliação</label>'
                    + '<input type="date" readonly class="js-todas-data js-data-calendario w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm" value="' + esc(base.data) + '"></div>';
                html += '<div><label class="mb-2 block text-sm font-medium text-gray-800">Horário de início</label>'
                    + '<input type="time" class="js-todas-inicio w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm" value="' + esc(base.inicio) + '"></div>';
                html += '<div><label class="mb-2 block text-sm font-medium text-gray-800">Horário de término</label>'
                    + '<input type="time" class="js-todas-fim w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm" value="' + esc(base.fim) + '"></div>';
                html += '</div>';
            }
            html += '<p class="mt-2 text-xs text-gray-500">Todas as turmas usam esta data e este horário.</p>';
        } else if (!opcoes.length) {
            html += '<p class="text-sm text-gray-500">Marque as turmas do evento para montar as datas.</p>';
        } else if (estado.modo === 'serie') {
            html += '<p class="mb-2 text-xs text-gray-500">Uma data e um horário para cada série.</p>';
            html += '<div class="divide-y divide-gray-100">';
            estado.blocos.forEach(function (bloco, indice) {
                var opcao = opcoes[indice] || { rotulo: 'Série' };
                html += '<div class="js-agenda-card flex flex-nowrap items-end gap-3 py-3">';
                html += '<p class="w-36 shrink-0 pb-2 text-sm font-medium text-gray-800">' + esc(opcao.rotulo) + '</p>';
                html += camposAgenda(prefixo, indice, bloco);
                html += '</div>';
            });
            html += '</div>';
        } else {
            var seriesAgenda = opcoesAgenda('serie');
            html += '<p class="mb-2 text-xs text-gray-500">Em cada horário, marque várias turmas ou a série inteira. Toda turma do evento precisa de um horário, e cada uma entra uma vez só.</p>';
            estado.blocos.forEach(function (bloco, indice) {
                var usadas = {};
                estado.blocos.forEach(function (outro, i) {
                    if (i === indice) return;
                    (outro.turmas || []).forEach(function (id) { usadas[String(id)] = true; });
                });
                html += '<div class="js-agenda-card mb-3 grid grid-cols-1 gap-4 rounded-lg border border-gray-200 bg-white p-3 sm:grid-cols-2">';
                html += '<div>';
                html += '<p class="mb-2 text-xs font-medium text-gray-500">Série / ano</p>';
                html += '<div class="flex flex-wrap gap-2">';
                seriesAgenda.forEach(function (opcao) {
                    var noBloco = opcao.turmas.length && opcao.turmas.every(function (id) { return bloco.turmas.indexOf(String(id)) !== -1; });
                    var ocupada = opcao.turmas.some(function (id) { return usadas[String(id)]; });
                    html += chipAgenda(opcao.rotulo, opcao.turmas.join(','), noBloco, ocupada);
                });
                html += '</div>';
                html += '<p class="mb-2 mt-3 text-xs font-medium text-gray-500">Turmas</p>';
                html += '<div class="flex flex-wrap gap-2">';
                opcoes.forEach(function (opcao) {
                    var id = String(opcao.turmas[0] || '');
                    var marcada = bloco.turmas.indexOf(id) !== -1;
                    var ocupada = !marcada && !!usadas[id];
                    html += chipAgenda(opcao.nome || opcao.rotulo, id, marcada, ocupada);
                });
                html += '</div></div>';
                html += '<div class="flex flex-col gap-3 sm:border-l sm:border-gray-100 sm:pl-4">';
                html += '<div><label class="mb-1 block text-xs font-medium text-gray-600">Data</label>'
                    + '<input type="date" readonly class="js-agenda-data js-data-calendario w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" value="' + esc(bloco.data) + '"></div>';
                html += '<div class="flex flex-nowrap items-end gap-3">';
                html += '<div class="min-w-0 flex-1"><label class="mb-1 block text-xs font-medium text-gray-600">Início</label>'
                    + '<input type="time" class="js-agenda-inicio w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" value="' + esc(bloco.inicio) + '"></div>';
                html += '<div class="min-w-0 flex-1"><label class="mb-1 block text-xs font-medium text-gray-600">Término</label>'
                    + '<input type="time" class="js-agenda-fim w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" value="' + esc(bloco.fim) + '"></div>';
                html += '</div>';
                html += '<div class="text-right"><button type="button" class="js-agenda-remover text-sm font-medium text-gray-500 hover:text-red-600">Remover</button></div>';
                html += '</div>';
                (bloco.turmas || []).forEach(function (id) {
                    html += '<input type="hidden" name="' + prefixo + '[agendas][' + indice + '][turmas][]" value="' + esc(id) + '">';
                });
                if ((bloco.turmas || []).length) {
                    html += '<input type="hidden" name="' + prefixo + '[agendas][' + indice + '][data]" value="' + esc(bloco.data) + '">';
                    html += '<input type="hidden" name="' + prefixo + '[agendas][' + indice + '][hora_inicio]" value="' + esc(bloco.inicio) + '">';
                    html += '<input type="hidden" name="' + prefixo + '[agendas][' + indice + '][hora_fim]" value="' + esc(bloco.fim) + '">';
                }
                html += '</div>';
            });
            var livres = opcoes.some(function (opcao) {
                var id = String(opcao.turmas[0] || '');
                return !estado.blocos.some(function (bloco) { return bloco.turmas.indexOf(id) !== -1; });
            });
            if (livres) {
                html += '<button type="button" class="js-agenda-adicionar mt-1 inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">'
                    + '<i class="fa-solid fa-plus text-xs"></i> Adicionar horário</button>';
                var faltam = opcoes.filter(function (opcao) {
                    var id = String(opcao.turmas[0] || '');
                    return !estado.blocos.some(function (bloco) { return bloco.turmas.indexOf(id) !== -1; });
                }).map(function (opcao) { return opcao.nome || opcao.rotulo; });
                if (faltam.length && estado.blocos.some(function (bloco) { return bloco.turmas.length; })) {
                    html += '<p class="mt-2 text-xs text-amber-700">Falta horário: ' + esc(faltam.join(', ')) + '</p>';
                }
            }
        }
        html += '</div>';
        destino.innerHTML = html;
        if (estado.modo === 'todas') {
            var alvosData = aninhada ? [row.querySelector('.js-data-linha')] : [document.getElementById('data-unica'), row.querySelector('.js-data-linha')];
            var alvosInicio = aninhada ? [row.querySelector('.js-hora-inicio')] : [document.getElementById('hora_inicio'), row.querySelector('.js-hora-inicio')];
            var alvosFim = aninhada ? [row.querySelector('.js-hora-fim')] : [document.getElementById('hora_fim'), row.querySelector('.js-hora-fim')];
            espelharCampo(destino.querySelector('.js-todas-data'), alvosData);
            espelharCampo(destino.querySelector('.js-todas-inicio'), alvosInicio);
            espelharCampo(destino.querySelector('.js-todas-fim'), alvosFim);
        }
        destino.querySelectorAll('.js-modo-agenda').forEach(function (botao) {
            botao.addEventListener('click', function () {
                var modo = botao.getAttribute('data-modo') || 'todas';
                estado.modo = modo;
                estado.blocos = modo === 'turma' ? [blocoVazio(fallbackAgenda(row))] : [];
                pintarAgenda(row, destino);
            });
        });
        destino.querySelectorAll('.js-agenda-card').forEach(function (card, indice) {
            var bloco = estado.blocos[indice];
            if (!bloco) return;
            card.querySelectorAll('.js-agenda-opcao').forEach(function (caixa) {
                caixa.addEventListener('change', function () {
                    var ids = (caixa.getAttribute('data-turmas') || '').split(',').filter(Boolean);
                    if (caixa.checked) {
                        ids.forEach(function (id) {
                            if (bloco.turmas.indexOf(id) < 0) bloco.turmas.push(id);
                        });
                    } else {
                        bloco.turmas = bloco.turmas.filter(function (id) { return ids.indexOf(id) < 0; });
                    }
                    pintarAgenda(row, destino);
                });
            });
            var campoData = card.querySelector('.js-agenda-data');
            var campoInicio = card.querySelector('.js-agenda-inicio');
            var campoFim = card.querySelector('.js-agenda-fim');
            function gravarHorario() {
                bloco.data = campoData ? campoData.value : '';
                bloco.inicio = campoInicio ? campoInicio.value : '';
                bloco.fim = campoFim ? campoFim.value : '';
                var ocultos = card.querySelectorAll('input[type="hidden"]');
                ocultos.forEach(function (oculto) {
                    if (oculto.name.indexOf('[data]') !== -1) oculto.value = bloco.data;
                    if (oculto.name.indexOf('[hora_inicio]') !== -1) oculto.value = bloco.inicio;
                    if (oculto.name.indexOf('[hora_fim]') !== -1) oculto.value = bloco.fim;
                });
                var dataUnica = document.getElementById('data-unica');
                if (dataUnica && estado.modo !== 'todas' && indice === 0 && bloco.data) dataUnica.value = bloco.data;
            }
            if (campoData) {
                campoData.addEventListener('input', gravarHorario);
                campoData.addEventListener('change', gravarHorario);
            }
            if (campoInicio) campoInicio.addEventListener('input', gravarHorario);
            if (campoFim) campoFim.addEventListener('input', gravarHorario);
            var remover = card.querySelector('.js-agenda-remover');
            if (remover) remover.addEventListener('click', function () {
                estado.blocos.splice(indice, 1);
                pintarAgenda(row, destino);
            });
        });
        var adicionar = destino.querySelector('.js-agenda-adicionar');
        if (adicionar) adicionar.addEventListener('click', function () {
            estado.blocos.push(blocoVazio(fallbackAgenda(row)));
            pintarAgenda(row, destino);
        });
        atualizarHorasLinhas();
    }
    function espelharCampo(visivel, reais) {
        if (!visivel) return;
        var listaReais = (reais || []).filter(Boolean);
        listaReais.forEach(function (real) {
            if (real.value) visivel.value = real.value;
        });
        listaReais.forEach(function (real) { real.value = visivel.value; });
        function copiar() {
            listaReais.forEach(function (real) {
                real.value = visivel.value;
            });
        }
        visivel.addEventListener('input', copiar);
        visivel.addEventListener('change', copiar);
    }
    function blocoVazio(base) {
        return { turmas: [], data: base.data || '', inicio: base.inicio || '08:00', fim: base.fim || '18:00' };
    }
    function atualizarAgendas() {
        var slot = document.getElementById('agenda-unica');
        var unico = origemOffline() !== 'eventos';
        if (slot) slot.innerHTML = '';
        if (!lista) return;
        lista.querySelectorAll('.js-agenda-lugar').forEach(function (lugar) { lugar.innerHTML = ''; });
        if (unico) {
            if (lista.children[0]) pintarAgenda(lista.children[0], slot);
            return;
        }
        lista.querySelectorAll('.subevento-row').forEach(function (row) {
            pintarAgenda(row, row.querySelector('.js-agenda-lugar'));
        });
    }
    function blocoAgenda(row) {
        if (origemOffline() !== 'eventos' && lista && lista.children[0] === row) {
            var slot = document.getElementById('agenda-unica');
            return slot ? slot.querySelector('.js-agenda') : null;
        }
        return row.querySelector('.js-agenda');
    }
    function validarAgenda(row, titulo) {
        var estado = estadoAgenda(row);
        if (estado.modo === 'todas') return '';
        var nome = estado.modo === 'serie' ? 'série' : 'turma';
        var comTurma = estado.blocos.filter(function (bloco) { return bloco.turmas && bloco.turmas.length; });
        if (!comTurma.length) {
            return estado.modo === 'serie'
                ? 'Marque as turmas do evento para informar a data de cada série em ' + titulo + '.'
                : 'Adicione um horário e marque as turmas em ' + titulo + '.';
        }
        for (var i = 0; i < estado.blocos.length; i++) {
            var bloco = estado.blocos[i];
            if (estado.modo === 'turma' && !(bloco.turmas || []).length) {
                return 'Escolha as turmas ou a série deste horário em ' + titulo + '.';
            }
            if (!(bloco.turmas || []).length) continue;
            if (!/^\d{4}-\d{2}-\d{2}$/.test(bloco.data || '')) return 'Informe a data de cada ' + nome + ' em ' + titulo + '.';
            if (!bloco.inicio || !bloco.fim || bloco.inicio >= bloco.fim) return 'Informe o horário de início e de término de cada ' + nome + ' em ' + titulo + '.';
        }
        if (estado.modo === 'turma') {
            var faltam = turmasEvento().filter(function (turma) {
                return !estado.blocos.some(function (bloco) {
                    return (bloco.turmas || []).indexOf(String(turma.id)) !== -1;
                });
            }).map(function (turma) { return turma.nome; });
            if (faltam.length) return 'Informe o horário de ' + faltam.join(', ') + ' em ' + titulo + '.';
        }
        return '';
    }
    function resumoAgenda(row) {
        var estado = estadoAgenda(row);
        if (estado.modo === 'todas') return '';
        var opcoes = opcoesAgenda(estado.modo);
        var partes = [];
        estado.blocos.forEach(function (bloco) {
            if (!(bloco.turmas || []).length) return;
            var nomes = opcoes.filter(function (opcao) {
                return opcao.turmas.some(function (id) { return bloco.turmas.indexOf(String(id)) !== -1; });
            }).map(function (opcao) { return opcao.rotulo; });
            var hora = bloco.inicio && bloco.fim ? ' ' + bloco.inicio + '–' + bloco.fim : '';
            partes.push(esc(nomes.join(', ') || 'Turma') + ': ' + esc((dataBr(bloco.data) || 'sem data') + hora));
        });
        return partes.join('<br>');
    }
    function linha(titulo, dataIso, extra) {
        extra = extra || {};
        seq += 1;
        var wrap = document.createElement('div');
        wrap.className = 'subevento-row overflow-hidden rounded-xl border border-gray-200 bg-white';
        wrap.setAttribute('data-seq', String(seq));
        wrap.setAttribute('data-prefix', 'subeventos[' + seq + '][pares]');
        wrap.setAttribute('data-agenda', 'subeventos[' + seq + ']');
        wrap.setAttribute('data-par', '0');
        wrap.innerHTML =
            '<div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3">' +
                '<span class="js-subevento-numero flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">1</span>' +
                '<p class="js-subevento-rotulo min-w-0 truncate text-sm font-semibold text-gray-900">Evento</p>' +
                '<div class="ml-auto flex shrink-0 items-center gap-3">' +
                    '<button type="button" class="js-toggle-ajuste inline-flex items-center gap-1.5 text-xs font-medium text-purple-700 hover:text-purple-900"><i class="fa-solid fa-sliders"></i><span>Ajustar</span></button>' +
                    '<button type="button" class="js-remover text-gray-400 hover:text-red-600" aria-label="Remover"><i class="fa-solid fa-trash-can"></i></button>' +
                '</div>' +
            '</div>' +
            '<div class="space-y-3 p-4">' +
                '<div class="flex flex-wrap items-center gap-3">' +
                    '<input type="date" readonly name="subeventos[' + seq + '][data]" aria-label="Data" value="' + esc(dataIso || '') + '" class="js-data-linha js-data-calendario w-36 shrink-0 cursor-pointer px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">' +
                    '<input type="time" name="subeventos[' + seq + '][hora_inicio]" aria-label="Horário de início" value="08:00" class="js-hora-inicio js-horas-linha hidden w-28 shrink-0 px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">' +
                    '<input type="time" name="subeventos[' + seq + '][hora_fim]" aria-label="Horário de término" value="18:00" class="js-hora-fim js-horas-linha hidden w-28 shrink-0 px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm">' +
                    '<input type="text" name="subeventos[' + seq + '][titulo]" maxlength="150" value="' + esc(titulo) + '" aria-label="Nome" placeholder="Nome do subevento" class="min-w-[12rem] flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">' +
                '</div>' +
                '<input type="checkbox" class="js-ajuste hidden" name="subeventos[' + seq + '][ajuste_proprio]" value="1">' +
                '<div class="js-agenda-lugar rounded-lg border border-gray-200 bg-gray-50 px-3 pb-3"></div>' +
            '<div class="js-ajuste-box hidden rounded-lg border border-dashed border-gray-200 bg-gray-50 p-3 space-y-3">' +
                '<div class="flex items-center justify-between"><p class="text-xs text-gray-500">Só esta data</p><button type="button" class="js-add-par text-xs font-medium text-purple-700">+ Matéria</button></div>' +
                '<div class="js-pares space-y-3"></div>' +
            '</div></div>';
        wrap.querySelector('input[type="date"]').addEventListener('change', function () {
            sincronizarQuantidade(lista.children.length);
            renderCalendario();
        });
        wrap.querySelector('input[name*="[titulo]"]').addEventListener('input', numerarSubeventos);
        wrap.querySelector('.js-remover').addEventListener('click', function () {
            if (origemOffline() !== 'eventos' && lista.children.length <= 1) return;
            wrap.remove();
            sincronizarQuantidade(lista.children.length);
            renderCalendario();
            atualizarListaVazia();
        });
        wrap.querySelector('.js-add-par').addEventListener('click', function () {
            adicionarPar(wrap, { turmas: turmasEvento().map(function (t) { return String(t.id); }) }, true);
        });
        var ajuste = wrap.querySelector('.js-ajuste');
        var toggle = wrap.querySelector('.js-toggle-ajuste');
        function pintarAjuste() {
            var rotulo = toggle.querySelector('span');
            if (rotulo) rotulo.textContent = ajuste.checked ? 'Usando ajuste' : 'Ajustar';
            toggle.classList.toggle('text-purple-900', ajuste.checked);
        }
        toggle.addEventListener('click', function () {
            ajuste.checked = !ajuste.checked;
            ajuste.dispatchEvent(new Event('change'));
        });
        ajuste.addEventListener('change', function () {
            var box = wrap.querySelector('.js-ajuste-box');
            box.classList.toggle('hidden', !ajuste.checked);
            pintarAjuste();
            if (ajuste.checked && !wrap.querySelector('.js-par')) {
                lerPares(document.getElementById('pares-padrao')).forEach(function (par) { adicionarPar(wrap, par); });
            }
            if (!ajuste.checked) {
                wrap.querySelector('.js-pares').innerHTML = '';
                wrap.setAttribute('data-par', '0');
            }
        });
        lista.appendChild(wrap);
        iniciarAgenda(wrap, extra.agenda_modo || 'todas', extra.agendas || []);
        atualizarAgendas();
        if (extra.hora_inicio) {
            var campoInicio = wrap.querySelector('.js-hora-inicio');
            if (campoInicio) campoInicio.value = String(extra.hora_inicio).slice(0, 5);
        }
        if (extra.hora_fim) {
            var campoFim = wrap.querySelector('.js-hora-fim');
            if (campoFim) campoFim.value = String(extra.hora_fim).slice(0, 5);
        }
        if (extra.id) {
            var oculto = document.createElement('input');
            oculto.type = 'hidden';
            oculto.name = 'subeventos[' + seq + '][id]';
            oculto.value = String(extra.id);
            wrap.appendChild(oculto);
        }
        atualizarHorasLinhas();
        numerarSubeventos();
        return wrap;
    }
    function numerarSubeventos() {
        if (!lista) return;
        lista.querySelectorAll('.subevento-row').forEach(function (row, indice) {
            var numero = String(indice + 1);
            var badge = row.querySelector('.js-subevento-numero');
            if (badge) badge.textContent = numero;
            var rotulo = row.querySelector('.js-subevento-rotulo');
            var nome = ((row.querySelector('input[name*="[titulo]"]') || {}).value || '').trim();
            if (rotulo) rotulo.textContent = nome ? ('Evento ' + numero + ' · ' + nome) : ('Evento ' + numero);
        });
    }
    var qtdAplicada = 0;
    var calCursor = new Date();
    calCursor.setDate(1);
    var modoPeriodicidade = '';

    function pad2(n) { return n < 10 ? '0' + n : String(n); }
    function isoDe(ano, mes, dia) { return ano + '-' + pad2(mes) + '-' + pad2(dia); }
    function datasDasLinhas() {
        var mapa = {};
        form.querySelectorAll('input.js-data-calendario').forEach(function (el) {
            if (/^\d{4}-\d{2}-\d{2}$/.test(el.value || '')) mapa[el.value] = true;
        });
        return mapa;
    }
    function atualizarListaVazia() {
        var vazio = document.getElementById('lista-subeventos-vazia');
        if (vazio) vazio.classList.toggle('hidden', lista.children.length > 0);
        var contador = document.getElementById('calendario-contador');
        if (contador) {
            var n = lista.children.length;
            contador.textContent = n === 0 ? 'Nenhuma data marcada' : (n === 1 ? '1 data marcada' : n + ' datas marcadas');
        }
        numerarSubeventos();
    }
    var campoDataAlvo = null;
    function abrirCalendario(campo) {
        var backdrop = document.getElementById('calendarioBackdrop');
        var drawer = document.getElementById('calendarioDrawer');
        if (!backdrop || !drawer) return;
        campoDataAlvo = campo || null;
        if (campoDataAlvo && /^\d{4}-\d{2}-\d{2}$/.test(campoDataAlvo.value || '')) {
            var partes = campoDataAlvo.value.split('-');
            calCursor = new Date(Number(partes[0]), Number(partes[1]) - 1, 1);
        }
        var contador = document.getElementById('calendario-contador');
        if (contador) contador.textContent = campoDataAlvo && campoDataAlvo.value ? dataBr(campoDataAlvo.value) : 'Selecione o dia';
        backdrop.classList.remove('hidden');
        drawer.classList.remove('translate-x-full');
        drawer.setAttribute('aria-hidden', 'false');
        renderCalendario();
    }
    function fecharCalendario() {
        var backdrop = document.getElementById('calendarioBackdrop');
        var drawer = document.getElementById('calendarioDrawer');
        campoDataAlvo = null;
        if (!backdrop || !drawer) return;
        backdrop.classList.add('hidden');
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
    }
    function escolherData(iso) {
        if (!campoDataAlvo || !/^\d{4}-\d{2}-\d{2}$/.test(iso || '')) return;
        var campo = campoDataAlvo;
        campo.value = iso;
        campo.dispatchEvent(new Event('input', { bubbles: true }));
        campo.dispatchEvent(new Event('change', { bubbles: true }));
        fecharCalendario();
    }
    function linhaPorData(iso) {
        var achou = null;
        lista.querySelectorAll('.subevento-row').forEach(function (row) {
            var campo = row.querySelector('input[type="date"]');
            if (campo && campo.value === iso) achou = row;
        });
        return achou;
    }
    function renderCalendario() {
        var box = document.getElementById('calendario-periodicidade');
        if (!box) return;
        var ano = calCursor.getFullYear();
        var mes = calCursor.getMonth();
        var marcadas = datasDasLinhas();
        var escolhida = campoDataAlvo ? (campoDataAlvo.value || '') : '';
        var contador = document.getElementById('calendario-contador');
        if (contador) {
            var total = Object.keys(marcadas).length;
            contador.textContent = total === 0 ? 'Nenhuma data marcada' : (total === 1 ? '1 data marcada' : total + ' datas marcadas');
        }
        var nomes = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        var offset = (new Date(ano, mes, 1).getDay() + 6) % 7;
        var dias = new Date(ano, mes + 1, 0).getDate();
        var hoje = new Date();
        var hojeIso = isoDe(hoje.getFullYear(), hoje.getMonth() + 1, hoje.getDate());
        var html = '<div class="mb-4 flex items-center justify-between">'
            + '<button type="button" class="js-cal-prev flex h-9 w-9 items-center justify-center rounded-full text-gray-600 hover:bg-gray-100" aria-label="Mês anterior"><i class="fa-solid fa-chevron-left text-xs"></i></button>'
            + '<p class="text-sm font-semibold text-gray-900">' + nomes[mes] + ' ' + ano + '</p>'
            + '<button type="button" class="js-cal-next flex h-9 w-9 items-center justify-center rounded-full text-gray-600 hover:bg-gray-100" aria-label="Próximo mês"><i class="fa-solid fa-chevron-right text-xs"></i></button>'
            + '</div>'
            + '<div class="mb-2 grid grid-cols-7 text-center text-[11px] font-medium uppercase tracking-wide text-gray-400"><span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sáb</span><span>Dom</span></div>'
            + '<div class="grid grid-cols-7 gap-y-1">';
        var i;
        for (i = 0; i < offset; i++) html += '<span class="h-10"></span>';
        for (var dia = 1; dia <= dias; dia++) {
            var iso = isoDe(ano, mes + 1, dia);
            var on = !!marcadas[iso];
            var atual = iso === escolhida;
            var ehHoje = iso === hojeIso;
            var classe = 'js-cal-dia mx-auto flex h-10 w-10 items-center justify-center rounded-full text-sm transition-colors ';
            if (on && atual) classe += 'bg-teal-600 font-semibold text-white shadow-sm ring-2 ring-offset-2 ring-teal-600';
            else if (on) classe += 'bg-teal-600 font-semibold text-white shadow-sm';
            else if (ehHoje) classe += 'font-semibold text-teal-700 ring-1 ring-teal-400 hover:bg-teal-50';
            else classe += 'text-gray-700 hover:bg-gray-100';
            html += '<button type="button" data-iso="' + iso + '" class="' + classe + '">' + dia + '</button>';
        }
        html += '</div>';
        box.innerHTML = html;
        var chips = document.getElementById('calendario-chips');
        if (chips) {
            var marcadasLista = Object.keys(marcadas).sort();
            chips.innerHTML = marcadasLista.map(function (isoChip) {
                var partes = isoChip.split('-');
                return '<span class="inline-flex items-center rounded-full bg-teal-50 px-3 py-1 text-xs font-medium text-teal-800">'
                    + partes[2] + '/' + partes[1] + '</span>';
            }).join('');
        }
        box.querySelector('.js-cal-prev').addEventListener('click', function () {
            calCursor.setMonth(calCursor.getMonth() - 1);
            renderCalendario();
        });
        box.querySelector('.js-cal-next').addEventListener('click', function () {
            calCursor.setMonth(calCursor.getMonth() + 1);
            renderCalendario();
        });
        box.querySelectorAll('.js-cal-dia').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (campoDataAlvo) escolherData(btn.getAttribute('data-iso'));
                else alternarData(btn.getAttribute('data-iso'));
            });
        });
    }
    function alternarData(iso) {
        var row = linhaPorData(iso);
        if (row) {
            row.remove();
        } else if (lista.children.length < 20) {
            linha('Semana ' + (lista.children.length + 1), iso);
        }
        sincronizarQuantidade(lista.children.length);
        renderCalendario();
        atualizarListaVazia();
    }
    function aplicarPeriodicidade() {
        var multi = origemOffline() === 'eventos';
        var chave = multi ? 'eventos' : 'unico';
        var mudou = modoPeriodicidade !== chave;
        var unico = document.getElementById('painel-unico');
        var varios = document.getElementById('painel-multiplos');
        var dataUnica = document.getElementById('data-unica');
        if (unico) unico.classList.toggle('hidden', multi);
        if (varios) varios.classList.toggle('hidden', !multi);
        document.querySelectorAll('.js-qtd-subeventos').forEach(function (el) {
            el.classList.toggle('hidden', !multi);
        });
        if (!multi) {
            while (lista.children.length > 1) lista.removeChild(lista.lastElementChild);
            if (!lista.children.length) linha('Avaliação', dataUnica ? dataUnica.value : '');
            var row = lista.children[0];
            var campoData = row.querySelector('input[type="date"]');
            var campoTitulo = row.querySelector('input[name*="[titulo]"]');
            if (dataUnica && campoData) {
                if (dataUnica.value) campoData.value = dataUnica.value;
                else if (campoData.value) dataUnica.value = campoData.value;
            }
            var nome = (document.getElementById('nome').value || '').trim();
            if (campoTitulo) campoTitulo.value = nome || campoTitulo.value || 'Avaliação';
            var horaInicio = document.getElementById('hora_inicio');
            var horaFim = document.getElementById('hora_fim');
            var campoInicio = row.querySelector('.js-hora-inicio');
            var campoFim = row.querySelector('.js-hora-fim');
            if (horaInicio && campoInicio) campoInicio.value = horaInicio.value;
            if (horaFim && campoFim) campoFim.value = horaFim.value;
            sincronizarQuantidade(1);
        } else {
            if (mudou && lista.children.length === 1) {
                var vazio = lista.children[0].querySelector('input[type="date"]');
                if (vazio && !vazio.value) lista.children[0].remove();
            }
            if (mudou && dataUnica && dataUnica.value && !linhaPorData(dataUnica.value)) {
                linha('Semana 1', dataUnica.value);
            }
            if (!lista.children.length) {
                var qtdInicial = parseInt((document.getElementById('quantidade-subeventos') || {}).value, 10) || 1;
                qtdInicial = Math.max(1, Math.min(20, qtdInicial));
                for (var i = 1; i <= qtdInicial; i++) linha('Semana ' + i, i === 1 && dataUnica ? dataUnica.value : '');
            }
            sincronizarQuantidade(lista.children.length);
        }
        modoPeriodicidade = chave;
        atualizarHorasLinhas();
        atualizarListaVazia();
        atualizarAgendas();
        if (!multi) fecharCalendario();
    }
    function atualizarHorasLinhas() {
        var multi = origemOffline() === 'eventos';
        var modoUnico = 'todas';
        if (!multi && lista && lista.children[0]) modoUnico = estadoAgenda(lista.children[0]).modo;
        var escondeDataUnica = !multi && (modoUnico === 'serie' || modoUnico === 'turma');
        var grade = document.querySelector('#painel-unico > .grid');
        if (grade) grade.classList.add('hidden');
        document.querySelectorAll('#painel-unico .js-horas-online').forEach(function (el) {
            el.classList.toggle('hidden', multi || escondeDataUnica);
        });
        if (!lista) return;
        lista.querySelectorAll('.subevento-row').forEach(function (row) {
            var modo = multi ? estadoAgenda(row).modo : 'todas';
            row.querySelectorAll('.js-horas-linha').forEach(function (el) {
                el.classList.add('hidden');
                el.disabled = !(multi && modo === 'todas');
            });
            var dataLinha = row.querySelector('.js-data-linha');
            if (dataLinha) dataLinha.classList.toggle('hidden', multi);
        });
    }
    function sincronizarQuantidade(qtd) {
        qtdAplicada = qtd;
        var campo = document.getElementById('quantidade_eventos_esperada');
        if (campo) campo.value = String(qtd);
        var visivel = document.getElementById('quantidade-subeventos');
        if (visivel && document.activeElement !== visivel) visivel.value = String(Math.max(1, qtd));
    }
    function aplicarQuantidadeSubeventos() {
        if (origemOffline() !== 'eventos' || !lista) return;
        var campo = document.getElementById('quantidade-subeventos');
        var qtd = Math.max(1, Math.min(20, parseInt(campo && campo.value, 10) || 1));
        if (campo) campo.value = String(qtd);
        while (lista.children.length > qtd) lista.removeChild(lista.lastElementChild);
        while (lista.children.length < qtd) linha('Semana ' + (lista.children.length + 1), '');
        sincronizarQuantidade(lista.children.length);
        atualizarListaVazia();
        atualizarAgendas();
    }
    function gerar(qtd) {
        lista.innerHTML = '';
        seq = 0;
        var n = Math.max(1, Math.min(20, qtd));
        for (var i = 1; i <= n; i++) linha('Semana ' + i);
    }
    function atualizarTurmasPares() {
        form.querySelectorAll('.js-par').forEach(function (card) {
            var campos = camposDoPar(card);
            if (!campos) return;
            var row = card.closest('[data-prefix]');
            var existentes = campos.querySelectorAll('.js-turma-par');
            var marcadas = Array.from(existentes).filter(function (el) { return el.checked; }).map(function (el) { return el.value; });
            if (!existentes.length) marcadas = turmasEvento().map(function (t) { return String(t.id); });
            var box = campos.querySelector('.js-turmas-par');
            if (box && row) box.innerHTML = htmlTurmasPar(row.getAttribute('data-prefix'), card.getAttribute('data-par'), marcadas);
            if (card.querySelector('.js-par-campos')) pintarResumoPar(card);
        });
        atualizarAgendas();
    }
    function nomesDoPar(par, materias, materiaIds, professores) {
        var mat = (catalogo.materias || []).filter(function (m) { return String(m.id) === String(par.materia_id); })[0];
        var prof = (catalogo.professores || []).filter(function (p) { return String(p.id) === String(par.professor_id); })[0];
        if (mat && materiaIds.indexOf(String(mat.id)) === -1) {
            materias.push(mat.nome);
            materiaIds.push(String(mat.id));
        }
        if (prof && professores.indexOf(prof.nome) === -1) professores.push(prof.nome);
    }
    function lerSubs() {
        var base = lerPares(document.getElementById('pares-padrao'));
        return Array.from(lista.querySelectorAll('.subevento-row')).map(function (row, i) {
            var ajuste = row.querySelector('.js-ajuste');
            var pares = ajuste && ajuste.checked ? lerPares(row) : base;
            var materias = [];
            var materiaIds = [];
            var professores = [];
            pares.forEach(function (par) { nomesDoPar(par, materias, materiaIds, professores); });
            return {
                titulo: (row.querySelector('input[name*="[titulo]"]') || {}).value || '',
                data: (row.querySelector('input[name*="[data]"]') || {}).value || '',
                inicio: (row.querySelector('.js-hora-inicio') || {}).value || '',
                fim: (row.querySelector('.js-hora-fim') || {}).value || '',
                proprio: !!(ajuste && ajuste.checked),
                agenda: resumoAgenda(row),
                professores: professores,
                materias: materias,
                materiaIds: materiaIds,
                amostra: amostras[i % amostras.length]
            };
        });
    }

    function fechar(valores, criterio, escala) {
        if (!valores.length || criterio === 'aproveitamento_nq') return null;
        var r = valores[valores.length - 1];
        if (criterio === 'maior') r = Math.max.apply(null, valores);
        if (criterio === 'media') r = valores.reduce(function (a, b) { return a + b; }, 0) / valores.length;
        if (criterio === 'soma') r = valores.reduce(function (a, b) { return a + b; }, 0);
        if (r > escala) r = escala;
        return r;
    }

    function rotuloMarcado(input) {
        if (!input) return '';
        var label = input.closest('label');
        if (!label) return '';
        var forte = label.querySelector('.font-medium');
        if (forte) return forte.textContent.trim();
        var texto = label.querySelector('span');
        return texto ? texto.textContent.trim() : '';
    }
    function nomeTurmaId(id) {
        var turma = (catalogo.turmas || []).filter(function (t) { return String(t.id) === String(id); })[0];
        return turma ? turma.nome : '';
    }
    function dataHoraBr(valor) {
        var texto = String(valor || '').replace('T', ' ');
        var partes = texto.split(' ');
        var data = dataBr(partes[0]);
        var hora = partes[1] ? partes[1].slice(0, 5) : '';
        return hora ? data + ' ' + hora : data;
    }
    function cardSecao(numero, titulo, subtitulo, corpo) {
        return '<div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">'
            + '<div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">'
            + '<span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">' + numero + '</span>'
            + '<div><p class="text-sm font-semibold text-gray-900">' + titulo + '</p>'
            + '<p class="text-sm text-gray-500 mt-0.5">' + subtitulo + '</p></div></div>'
            + '<div class="p-5">' + corpo + '</div></div>';
    }
    function cartaoResumo(numero, titulo, subtitulo, linhas) {
        var corpo = '<dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">';
        linhas.forEach(function (linha) {
            if (!linha || !linha.valor) return;
            corpo += '<div><dt class="text-xs text-gray-500">' + esc(linha.rotulo) + '</dt>';
            corpo += '<dd class="text-sm font-medium text-gray-900 mt-0.5">' + linha.valor + '</dd></div>';
        });
        corpo += '</dl>';
        return cardSecao(numero, titulo, subtitulo, corpo);
    }
    function montarRevisao() {
        var subs = lerSubs();
        var nome = (document.getElementById('nome').value || 'Este evento').trim();
        var periodo = textoSelecionado('bimestre');
        var ano = document.getElementById('ano_letivo').value || '';
        var online = formatoAtual() === 'online_questoes';
        var porProfessor = configAtual() === 'professor_por_questao';
        var turmas = Array.from(form.querySelectorAll('.js-turma:checked')).map(function (el) {
            var nomeTurma = el.parentElement ? el.parentElement.querySelector('.js-turma-nome') : null;
            return nomeTurma ? nomeTurma.textContent.trim() : el.value;
        });
        var incluirMedia = (document.getElementById('incluir_media') || {}).value === '1' && subs.length > 1;
        var criterio = (document.getElementById('criterio_fechamento') || {}).value || 'ultima';
        var escala = parseFloat(document.getElementById('escala_max').value) || 10;
        var pares = lerPares(document.getElementById('pares-padrao')).filter(function (par) {
            return par.materia_id || par.professor_id;
        });
        var materias = [];
        subs.forEach(function (sub) {
            sub.materias.forEach(function (mat, idx) {
                var id = sub.materiaIds[idx];
                if (!materias.some(function (m) { return m.id === id; })) materias.push({ id: id, nome: mat });
            });
        });
        if (!materias.length) materias.push({ id: '', nome: 'Exemplo' });

        var canal = rotuloMarcado(form.querySelector('input[name="canal_origem"]:checked'));
        var modo = rotuloMarcado(form.querySelector('input[name="modo_offline"]:checked'));
        var caixa = caixaConfig();
        var quem = rotuloMarcado(caixa ? caixa.querySelector('input[name="configuracao_nota"]:checked') : null);
        var prazo = dataHoraBr((document.getElementById('prazo_entrega_professor') || {}).value || '');
        var notaUnica = document.getElementById('nota_unica_todas_materias');
        var portal = document.getElementById('visivel_no_portal_aluno');

        var html = '';
        html += cartaoResumo(1, 'Evento', 'Nome, período e turmas.', [
            { rotulo: 'Nome', valor: esc(nome) },
            { rotulo: 'Período', valor: esc([periodo, ano].filter(Boolean).join(' · ')) },
            { rotulo: 'Turmas', valor: turmas.length ? esc(turmas.join(', ')) : '' },
            { rotulo: 'Calendário letivo', valor: (document.getElementById('disponibilizar_calendario') || {}).checked ? 'Entra no calendário do curso' : 'Não entra' }
        ]);
        html += cartaoResumo(2, 'Avaliação', 'Onde acontece e quem lança a nota.', [
            { rotulo: 'Onde acontece', valor: esc(canal) },
            { rotulo: 'Periodicidade', valor: esc(modo) },
            { rotulo: online ? 'Quem elabora' : 'Quem lança', valor: esc(quem) },
            { rotulo: 'Prazo do professor', valor: porProfessor && prazo ? esc(prazo) : '' },
            { rotulo: 'Portal do aluno', valor: online ? (portal && portal.checked ? 'Visível' : 'Oculto') : '' }
        ]);
        html += cartaoResumo(3, 'Nota', 'Como esta nota fecha no período.', [
            { rotulo: 'Tipo de nota', valor: esc(textoSelecionado('criterio_fechamento')) },
            { rotulo: 'Mesma nota', valor: !online && notaUnica && notaUnica.checked ? 'Vale para todas as matérias' : '' },
            { rotulo: 'Escala', valor: esc(nota(escala)) },
            { rotulo: 'Professores da mesma disciplina', valor: esc(textoSelecionado('criterio_professores_mesmo_componente')) }
        ]);

        var corpoDatas = '<div class="overflow-x-auto"><table class="min-w-full text-sm">';
        corpoDatas += '<thead><tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">';
        corpoDatas += '<th class="pb-2 pr-4 font-medium">Nome</th><th class="pb-2 pr-4 font-medium">Data</th>';
        if (online) corpoDatas += '<th class="pb-2 font-medium">Horário</th>';
        corpoDatas += '</tr></thead><tbody class="divide-y divide-gray-100">';
        subs.forEach(function (sub) {
            var horario = online && sub.inicio && sub.fim ? sub.inicio + '–' + sub.fim : '';
            corpoDatas += '<tr><td class="py-2.5 pr-4 font-medium text-gray-900">' + esc(sub.titulo || 'Sem nome');
            if (sub.proprio && sub.materias.length) {
                corpoDatas += '<span class="mt-0.5 block text-xs font-normal text-gray-500">Nesta data: ' + esc(sub.materias.join(', ')) + '</span>';
            }
            if (sub.agenda) {
                corpoDatas += '<span class="mt-0.5 block text-xs font-normal text-gray-500">' + sub.agenda + '</span>';
            }
            corpoDatas += '</td><td class="py-2.5 pr-4 text-gray-700 whitespace-nowrap">' + esc(dataBr(sub.data) || 'Sem data') + '</td>';
            if (online) corpoDatas += '<td class="py-2.5 text-gray-700 whitespace-nowrap">' + esc(horario || '—') + '</td>';
            corpoDatas += '</tr>';
        });
        corpoDatas += '</tbody></table></div>';
        html += cardSecao(4, 'Datas', 'Cada data vira uma coluna do quadro.', corpoDatas);

        var numeroQuadro = 5;
        if (pares.length) {
            var corpoDisc = '<div class="overflow-x-auto"><table class="min-w-full text-sm">';
            corpoDisc += '<thead><tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">';
            corpoDisc += '<th class="pb-2 pr-4 font-medium">Disciplina</th><th class="pb-2 pr-4 font-medium">Professor</th><th class="pb-2 font-medium">Turmas</th>';
            corpoDisc += '</tr></thead><tbody class="divide-y divide-gray-100">';
            pares.forEach(function (par) {
                var mat = (catalogo.materias || []).filter(function (m) { return String(m.id) === String(par.materia_id); })[0];
                var prof = (catalogo.professores || []).filter(function (p) { return String(p.id) === String(par.professor_id); })[0];
                var nomesTurmas = (par.turmas || []).map(nomeTurmaId).filter(Boolean);
                var nomeProf = prof ? prof.nome : (porProfessor ? 'Não definido' : 'Sem professor');
                corpoDisc += '<tr><td class="py-2.5 pr-4 font-medium text-gray-900">' + esc(mat ? mat.nome : 'Disciplina') + '</td>';
                corpoDisc += '<td class="py-2.5 pr-4 text-gray-700">' + esc(nomeProf) + '</td>';
                corpoDisc += '<td class="py-2.5 text-gray-700">' + esc(nomesTurmas.length ? nomesTurmas.join(', ') : '—') + '</td></tr>';
            });
            corpoDisc += '</tbody></table></div>';
            html += cardSecao(5, 'Disciplinas', 'Professor e turmas de cada linha.', corpoDisc);
            numeroQuadro = 6;
        }

        var corpoQuadro = '<div class="overflow-x-auto"><table class="ev-preview"><thead><tr><th>Matéria</th>';
        subs.forEach(function (sub) { corpoQuadro += '<th>' + esc(sub.titulo || 'Coluna') + '</th>'; });
        if (incluirMedia) corpoQuadro += '<th>Média</th>';
        corpoQuadro += '<th>Nota final</th></tr></thead><tbody>';
        materias.forEach(function (mat) {
            var nums = [];
            corpoQuadro += '<tr><td class="mat">' + esc(mat.nome) + '</td>';
            subs.forEach(function (sub) {
                var entra = mat.id === '' || sub.materiaIds.indexOf(mat.id) !== -1;
                if (entra) {
                    nums.push(sub.amostra);
                    corpoQuadro += '<td>' + nota(sub.amostra) + '</td>';
                } else {
                    corpoQuadro += '<td>—</td>';
                }
            });
            if (incluirMedia) {
                var media = nums.length ? nums.reduce(function (a, b) { return a + b; }, 0) / nums.length : null;
                corpoQuadro += '<td>' + (media === null ? '—' : nota(media)) + '</td>';
            }
            var finalNota = fechar(nums, criterio, escala);
            corpoQuadro += '<td>' + (finalNota === null ? '—' : nota(finalNota)) + '</td></tr>';
        });
        corpoQuadro += '</tbody></table></div>';
        html += cardSecao(numeroQuadro, 'Como a nota aparece no quadro', 'Números de exemplo, só para mostrar o cálculo.', corpoQuadro);
        revisao.innerHTML = html;
    }

    function mostrarErro(msg) {
        if (!msg) {
            erroEl.classList.add('hidden');
            erroEl.textContent = '';
            return;
        }
        erroEl.textContent = msg;
        erroEl.classList.remove('hidden');
    }

    function validar(passo) {
        if (passo === 1) {
            if (!(document.getElementById('nome').value || '').trim()) return 'Informe o nome do evento.';
            var ano = parseInt(document.getElementById('ano_letivo').value, 10);
            if (!ano || ano < 2000 || ano > 2100) return 'Informe o ano letivo.';
            if (!form.querySelector('.js-turma:checked')) return 'Marque pelo menos uma turma.';
        }
        if (passo === 2) {
            var erroDisc = validarPares(document.getElementById('pares-padrao'), 'Disciplinas', true);
            if (erroDisc) return erroDisc;
            if (configAtual() === 'professor_por_questao' && !(document.getElementById('prazo_entrega_professor').value || '').trim()) {
                return 'Informe o prazo para o professor.';
            }
            if (formatoAtual() === 'online_questoes' && origemOffline() !== 'eventos') {
                var modoHora = lista && lista.children[0] ? estadoAgenda(lista.children[0]).modo : 'todas';
                if (modoHora === 'todas') {
                    var inicio = (document.getElementById('hora_inicio') || {}).value || '';
                    var fim = (document.getElementById('hora_fim') || {}).value || '';
                    if (!inicio || !fim || inicio >= fim) return 'Informe o horário de início e de término da prova online.';
                }
            }
            if (origemOffline() !== 'eventos' && lista && lista.children[0]) {
                var modoAntes = estadoAgenda(lista.children[0]).modo;
                var campoDataUnica = document.getElementById('data-unica');
                if (modoAntes !== 'todas' && campoDataUnica && !campoDataUnica.value) {
                    var dataSerie = (estadoAgenda(lista.children[0]).blocos[0] || {}).data || '';
                    if (dataSerie) campoDataUnica.value = dataSerie;
                }
            }
            aplicarPeriodicidade();
            if (origemOffline() === 'eventos') {
                var datas = lista.querySelectorAll('.subevento-row');
                if (!datas.length) return 'Marque pelo menos uma data no calendário.';
                for (var d = 0; d < datas.length; d++) {
                    var nomeData = ((datas[d].querySelector('input[name*="[titulo]"]') || {}).value || '').trim();
                    var modoLinha = estadoAgenda(datas[d]).modo;
                    var campoData = datas[d].querySelector('.js-data-linha');
                    if (modoLinha !== 'todas' && campoData && !/^\d{4}-\d{2}-\d{2}$/.test(campoData.value || '')) {
                        var primeiraDataLinha = (estadoAgenda(datas[d]).blocos[0] || {}).data || '';
                        if (/^\d{4}-\d{2}-\d{2}$/.test(primeiraDataLinha)) campoData.value = primeiraDataLinha;
                    }
                    var isoData = (campoData || {}).value || '';
                    if (!nomeData) return 'Informe o nome do subevento ' + (d + 1) + '.';
                    if (modoLinha === 'todas' && !/^\d{4}-\d{2}-\d{2}$/.test(isoData)) return 'Informe a data de ' + nomeData + '.';
                    if (formatoAtual() === 'online_questoes') {
                        var iniData = ((datas[d].querySelector('.js-hora-inicio') || {}).value || '');
                        var fimData = ((datas[d].querySelector('.js-hora-fim') || {}).value || '');
                        if (!iniData || !fimData || iniData >= fimData) return 'Informe o horário de início e de término de ' + nomeData + '.';
                    }
                    var erroAgenda = validarAgenda(datas[d], nomeData);
                    if (erroAgenda) return erroAgenda;
                }
            } else if (lista.children[0]) {
                var modoUnico = estadoAgenda(lista.children[0]).modo;
                var dataUnicaCampo = document.getElementById('data-unica');
                if (modoUnico !== 'todas' && dataUnicaCampo && !dataUnicaCampo.value) {
                    var primeiraData = (estadoAgenda(lista.children[0]).blocos[0] || {}).data || '';
                    if (primeiraData) dataUnicaCampo.value = primeiraData;
                }
                if (modoUnico === 'todas' && !((dataUnicaCampo || {}).value || '')) return 'Informe a data do evento.';
                if (modoUnico === 'todas') {
                    var inicioPadrao = (document.getElementById('hora_inicio') || {}).value || '';
                    var fimPadrao = (document.getElementById('hora_fim') || {}).value || '';
                    if (!inicioPadrao || !fimPadrao || inicioPadrao >= fimPadrao) return 'Informe o horário de início e de término.';
                }
                var erroUnico = validarAgenda(lista.children[0], 'esta avaliação');
                if (erroUnico) return erroUnico;
            }
            var rowsAjuste = lista.querySelectorAll('.subevento-row');
            for (var i = 0; i < rowsAjuste.length; i++) {
                var ajuste = rowsAjuste[i].querySelector('.js-ajuste');
                if (!ajuste || !ajuste.checked) continue;
                var tituloAjuste = ((rowsAjuste[i].querySelector('input[name*="[titulo]"]') || {}).value || '').trim();
                var erroSemana = validarPares(rowsAjuste[i], tituloAjuste || ('Data ' + (i + 1)), true);
                if (erroSemana) return erroSemana;
            }
        }
        return '';
    }

    function validarPares(row, rotulo, exigirTurmas) {
        var cards = row ? row.querySelectorAll('.js-par') : [];
        if (!cards.length) return 'Adicione uma disciplina em ' + rotulo + '.';
        fecharDisciplina();
        for (var c = 0; c < cards.length; c++) {
            var campos = camposDoPar(cards[c]);
            if (!campos) return 'Selecione a disciplina em ' + rotulo + '.';
            var prof = (campos.querySelector('.js-prof') || {}).value || '';
            var mat = (campos.querySelector('.js-mat') || {}).value || '';
            if (!professorOpcional() && !prof) return 'Selecione o professor em ' + rotulo + '.';
            if (!mat) return 'Selecione a disciplina em ' + rotulo + '.';
            if (exigeQuestoes()) {
                var qtd = parseInt((campos.querySelector('.js-qtd') || {}).value || '0', 10);
                if (!qtd || qtd < 1) return 'Informe o número de questões em ' + rotulo + '.';
            }
            if (exigirTurmas !== false && !campos.querySelector('.js-turma-par:checked')) return 'Marque as turmas em ' + rotulo + '.';
        }
        return '';
    }

    function pintarPasso(btn, estado) {
        todasCls.forEach(function (c) { btn.classList.remove(c); });
        estadosCls[estado].forEach(function (c) { btn.classList.add(c); });
        btn.setAttribute('data-step-state', estado);
        btn.setAttribute('data-active', estado === 'ativo' ? 'true' : 'false');
        var circle = btn.querySelector('.wizard-step-circle');
        var corner = circle.querySelector('.wizard-step-corner');
        if (estado === 'completo' || estado === 'erro') {
            if (!corner) {
                corner = document.createElement('span');
                corner.className = 'wizard-step-corner absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full text-white text-[9px]';
                corner.innerHTML = '<i></i>';
                circle.appendChild(corner);
            }
            corner.classList.remove('bg-green-500', 'bg-red-500');
            corner.classList.add(estado === 'completo' ? 'bg-green-500' : 'bg-red-500');
            corner.querySelector('i').className = estado === 'completo' ? 'fa-solid fa-check' : 'fa-solid fa-exclamation';
        } else if (corner) {
            corner.remove();
        }
    }

    function render() {
        form.querySelectorAll('[data-wizard-panel]').forEach(function (painel) {
            painel.classList.toggle('hidden', parseInt(painel.getAttribute('data-wizard-panel'), 10) !== atual);
        });
        var shell = document.getElementById('evento-wizard-shell');
        if (shell) {
            var solto = true;
            ['bg-white', 'rounded-xl', 'shadow-lg', 'p-6', 'min-h-[calc(100vh-20rem)]'].forEach(function (classe) {
                shell.classList.toggle(classe, !solto);
            });
        }
        var rodape = document.getElementById('evento-wizard-rodape');
        if (rodape) {
            rodape.classList.remove('border-t', 'border-gray-100');
        }
        if (nav) {
            nav.querySelectorAll('.step-nav-btn').forEach(function (btn) {
                var n = parseInt(btn.getAttribute('data-step-target'), 10);
                var estado = 'pendente';
                if (n === erroPasso) estado = 'erro';
                else if (n === atual) estado = 'ativo';
                else if (n < atual) estado = 'completo';
                pintarPasso(btn, estado);
            });
            nav.querySelectorAll('[data-connector-after]').forEach(function (el) {
                var after = parseInt(el.getAttribute('data-connector-after'), 10);
                var ok = after < atual && after !== erroPasso;
                el.classList.toggle('bg-green-400', ok);
                el.classList.toggle('bg-gray-200', !ok);
            });
        }
        if (atual === 2) {
            aplicarDisciplinasRecuperacao();
            atualizarTurmasPares();
        }
        btnVoltar.disabled = atual === 1;
        if (atual === 3) {
            montarRevisao();
            btnAvancar.innerHTML = 'Concluir';
        } else {
            btnAvancar.innerHTML = 'Continuar <i class="fa-solid fa-arrow-right ml-2"></i>';
        }
    }

    function irPara(destino) {
        if (destino === atual || destino < 1 || destino > 3) return;
        if (destino < atual) {
            erroPasso = 0;
            mostrarErro('');
            atual = destino;
            render();
            return;
        }
        for (var i = atual; i < destino; i++) {
            var msg = validar(i);
            if (msg) {
                erroPasso = i;
                atual = i;
                mostrarErro(msg);
                render();
                return;
            }
        }
        erroPasso = 0;
        mostrarErro('');
        atual = destino;
        render();
    }

    var disciplinasAutoChave = '';
    function chaveDisciplinas(pares) {
        return (pares || []).map(function (par) {
            var turmas = (par.turmas || []).map(String).slice().sort();
            return String(par.materia_id || '') + ':' + turmas.join(',');
        }).join('|');
    }
    function disciplinasDaRecuperacao() {
        var painel = document.getElementById('painel-recuperacao');
        if (!painel) return null;
        var marcados = painel.querySelectorAll('.js-aluno-rec:checked');
        var usarMarcados = marcados.length > 0;
        var fonte = usarMarcados ? marcados : painel.querySelectorAll('.js-aluno-rec');
        var porMateria = {};
        var ordem = [];
        Array.prototype.forEach.call(fonte, function (el) {
            var label = el.closest('label');
            var abaixo = !!(label && (label.getAttribute('style') || '').indexOf('#fef2f2') !== -1);
            if (!usarMarcados && !abaixo) return;
            var mid = parseInt(String(el.value || '').split(':')[1], 10) || 0;
            if (mid <= 0) return;
            if (!porMateria[mid]) {
                porMateria[mid] = { materia_id: String(mid), turmas: [] };
                ordem.push(porMateria[mid]);
            }
            var secao = el.closest('section');
            var tid = secao ? String(secao.getAttribute('data-turma') || '') : '';
            if (tid && porMateria[mid].turmas.indexOf(tid) === -1) porMateria[mid].turmas.push(tid);
        });
        return ordem;
    }
    function aplicarDisciplinasRecuperacao() {
        var novas = disciplinasDaRecuperacao();
        if (!novas || !novas.length) return;
        var box = document.getElementById('pares-padrao');
        if (!box) return;
        var atuais = lerPares(box);
        var chaveAtual = chaveDisciplinas(atuais);
        var vazias = atuais.every(function (par) { return !par.materia_id; });
        if (!vazias && chaveAtual !== disciplinasAutoChave) return;
        var chaveNova = chaveDisciplinas(novas);
        if (chaveAtual === chaveNova) {
            disciplinasAutoChave = chaveNova;
            return;
        }
        fecharDisciplina();
        var listaPares = box.querySelector('.js-pares');
        if (listaPares) listaPares.innerHTML = '';
        box.setAttribute('data-par', '0');
        novas.forEach(function (par) { adicionarPar(box, par); });
        disciplinasAutoChave = chaveDisciplinas(lerPares(box));
    }
    document.addEventListener('recuperacao-lista-pronta', function () {
        if (atual === 2) aplicarDisciplinasRecuperacao();
    });
    document.getElementById('btn-add-par-padrao').addEventListener('click', function () {
        adicionarPar(document.getElementById('pares-padrao'), { turmas: turmasEvento().map(function (t) { return String(t.id); }) }, true);
    });
    document.getElementById('btn-fechar-disciplina').addEventListener('click', fecharDisciplina);
    document.getElementById('btn-concluir-disciplina').addEventListener('click', fecharDisciplina);
    document.getElementById('disciplinaBackdrop').addEventListener('click', fecharDisciplina);
    form.addEventListener('mousedown', function (ev) {
        var campo = ev.target.closest ? ev.target.closest('input.js-data-calendario') : null;
        if (!campo || !form.contains(campo)) return;
        ev.preventDefault();
        abrirCalendario(campo);
    });
    var campoQuantidade = document.getElementById('quantidade-subeventos');
    if (campoQuantidade) {
        campoQuantidade.addEventListener('change', aplicarQuantidadeSubeventos);
    }
    document.getElementById('btn-fechar-calendario').addEventListener('click', fecharCalendario);
    document.getElementById('btn-concluir-calendario').addEventListener('click', fecharCalendario);
    document.getElementById('calendarioBackdrop').addEventListener('click', fecharCalendario);
    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Escape') return;
        if (parAberto) fecharDisciplina();
        else fecharCalendario();
    });
    var campoDataUnica = document.getElementById('data-unica');
    if (campoDataUnica) campoDataUnica.addEventListener('change', aplicarPeriodicidade);
    ['hora_inicio', 'hora_fim'].forEach(function (id) {
        var campoHora = document.getElementById(id);
        if (campoHora) campoHora.addEventListener('change', aplicarPeriodicidade);
    });
    function filtrarTurmasEnsino() {
        var radio = form.querySelector('input[name="filtro_ensino"]:checked');
        var temFiltro = !!form.querySelector('input[name="filtro_ensino"]');
        var escolhido = radio ? radio.value : '';
        form.querySelectorAll('.js-turma-item').forEach(function (label) {
            var ok = !temFiltro || label.getAttribute('data-ensino') === escolhido;
            label.classList.toggle('hidden', !ok);
            if (!ok) {
                var box = label.querySelector('.js-turma');
                if (box) box.checked = false;
            }
        });
        var mostrar = !temFiltro || escolhido !== '';
        var vazio = document.getElementById('turmas-ensino-vazio');
        var listaTurmas = document.getElementById('turmas-ensino-lista');
        if (vazio) vazio.classList.toggle('hidden', mostrar);
        if (listaTurmas) listaTurmas.classList.toggle('hidden', !mostrar);
        atualizarTurmasPares();
    }
    form.querySelectorAll('.js-filtro-ensino').forEach(function (el) {
        el.addEventListener('change', filtrarTurmasEnsino);
    });
    var btnMarcarTurmas = document.getElementById('btn-marcar-turmas-ensino');
    if (btnMarcarTurmas) {
        btnMarcarTurmas.addEventListener('click', function () {
            var primeiro = null;
            form.querySelectorAll('.js-turma-item:not(.hidden) .js-turma').forEach(function (el) {
                el.checked = true;
                if (!primeiro) primeiro = el;
            });
            atualizarTurmasPares();
            if (primeiro) primeiro.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }
    form.querySelectorAll('.js-turma').forEach(function (el) {
        el.addEventListener('change', atualizarTurmasPares);
    });
    function marcarRadio(nome, valor) {
        form.querySelectorAll('input[name="' + nome + '"]').forEach(function (el) {
            el.checked = el.value === valor;
        });
    }
    function setValor(id, valor) {
        var el = document.getElementById(id);
        if (!el || valor === undefined || valor === null || valor === '') return;
        el.value = String(valor);
    }
    function remarcarTurmas(ids) {
        var mapa = {};
        (ids || []).forEach(function (id) { mapa[String(id)] = true; });
        form.querySelectorAll('.js-turma').forEach(function (el) {
            if (mapa[el.value]) el.checked = true;
        });
        atualizarTurmasPares();
    }
    function aplicarEdicao(dados) {
        setValor('nome', dados.nome || '');
        var siglaEl = document.getElementById('sigla');
        if (siglaEl) siglaEl.value = String(dados.sigla || '');
        marcarRadio('canal_origem', dados.canal || 'offline');
        marcarRadio('modo_offline', dados.modo_offline || 'lancamento_direto');
        var cfg = dados.configuracao_nota || 'professor_por_questao';
        form.querySelectorAll('input[name="configuracao_nota"]').forEach(function (el) {
            el.checked = el.value === cfg;
        });
        setValor('registro_evento', dados.registro_evento);
        setValor('criterio_fechamento', dados.criterio_fechamento);
        setValor('escala_max', dados.escala_max);
        setValor('criterio_professores_mesmo_componente', dados.criterio_professores);
        setValor('prazo_entrega_professor', dados.prazo);
        setValor('hora_inicio', dados.hora_inicio);
        setValor('hora_fim', dados.hora_fim);
        var portal = document.getElementById('visivel_no_portal_aluno');
        if (portal) portal.checked = !!dados.visivel_portal;
        var liberarNota = document.getElementById('liberar_nota_aluno');
        if (liberarNota) liberarNota.checked = !!dados.liberar_nota_aluno;
        var notaUnica = document.getElementById('nota_unica_todas_materias');
        if (notaUnica) notaUnica.checked = !!dados.nota_unica;
        if (dados.ensino) {
            form.querySelectorAll('.js-filtro-ensino').forEach(function (radio) {
                radio.checked = radio.value === dados.ensino;
            });
        }

        var boxPadrao = document.getElementById('pares-padrao');
        var listaPares = boxPadrao ? boxPadrao.querySelector('.js-pares') : null;
        if (listaPares) listaPares.innerHTML = '';
        if (boxPadrao) boxPadrao.setAttribute('data-par', '0');
        var pares = dados.pares && dados.pares.length ? dados.pares : [{}];
        pares.forEach(function (par) { adicionarPar(boxPadrao, par); });

        var subs = dados.subeventos || [];
        var multi = dados.modo_offline === 'eventos' || subs.length > 1;
        var dataUnica = document.getElementById('data-unica');
        if (multi) {
            marcarRadio('modo_offline', 'eventos');
            if (dataUnica) dataUnica.value = '';
        } else if (subs[0] && dataUnica) {
            dataUnica.value = subs[0].data || '';
            setValor('hora_inicio', subs[0].hora_inicio || dados.hora_inicio);
            setValor('hora_fim', subs[0].hora_fim || dados.hora_fim);
        }
        lista.innerHTML = '';
        seq = 0;
        if (!subs.length) {
            linha('Avaliação', '');
        }
        subs.forEach(function (sub) {
            var row = linha(sub.titulo || 'Avaliação', sub.data || '', sub);
            if (sub.ajuste && sub.pares && sub.pares.length) {
                sub.pares.forEach(function (par) { adicionarPar(row, par); });
                var ajuste = row.querySelector('.js-ajuste');
                if (ajuste) {
                    ajuste.checked = true;
                    ajuste.dispatchEvent(new Event('change'));
                }
            }
        });
        modoPeriodicidade = multi ? 'eventos' : 'unico';
    }
    if (edicao && edicao.id) {
        aplicarEdicao(edicao);
    } else {
        adicionarPar(document.getElementById('pares-padrao'), {});
        gerar(1);
    }

    btnVoltar.addEventListener('click', function () { irPara(atual - 1); });
    btnAvancar.addEventListener('click', function () {
        if (atual < 3) {
            irPara(atual + 1);
            return;
        }
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
    });
    if (nav) {
        nav.querySelectorAll('.step-nav-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                irPara(parseInt(btn.getAttribute('data-step-target'), 10));
            });
        });
    }
    form.addEventListener('submit', function (e) {
        if (atual < 3) {
            e.preventDefault();
            irPara(atual + 1);
            return;
        }
        for (var i = 1; i <= 2; i++) {
            var msg = validar(i);
            if (msg) {
                e.preventDefault();
                erroPasso = i;
                atual = i;
                mostrarErro(msg);
                render();
                return;
            }
        }
    });

    var blocoEventos = form.querySelector('.js-bloco-eventos');
    var campoOrigem = document.getElementById('campo-origem');
    var criterio = document.getElementById('criterio_fechamento');
    var registro = document.getElementById('registro_evento');
    var hintNq = form.querySelector('.js-hint-nq');
    var estavaOnline = false;

    function atualizarParesUi() {
        var opc = professorOpcional();
        var esconderProf = opc && formatoAtual() === 'online_questoes';
        var qtd = exigeQuestoes();
        form.querySelectorAll('.js-par').forEach(function (card) {
            var campos = camposDoPar(card);
            if (!campos) return;
            var campoProf = campos.querySelector('.js-campo-prof');
            var prof = campos.querySelector('.js-prof');
            if (campoProf) campoProf.classList.toggle('hidden', esconderProf);
            var mat = campos.querySelector('.js-mat');
            var professorId = prof && !esconderProf ? prof.value : '';
            var materiaId = mat ? mat.value : '';
            if (prof) {
                prof.disabled = esconderProf;
                if (esconderProf) prof.value = '';
            }
            if (mat && esconderProf) {
                mat.innerHTML = opcoes(catalogo.materias || [], materiaId, 'Selecione a disciplina');
                if (materiaId) mat.value = String(materiaId);
            } else if (mat && prof) {
                preencherMaterias(mat, professorId, materiaId);
            }
            if (prof && !esconderProf) {
                preencherProfessores(prof, mat ? mat.value : materiaId, professorId);
            }
            campos.querySelectorAll('.js-req-prof').forEach(function (el) {
                el.classList.toggle('hidden', opc);
            });
            var campo = campos.querySelector('.js-campo-qtd');
            if (campo) campo.classList.toggle('hidden', !qtd);
            if (card.querySelector('.js-par-campos')) pintarResumoPar(card);
        });
        form.querySelectorAll('.js-hint-par').forEach(function (el) {
            el.textContent = esconderProf
                ? 'A coordenação elabora a prova, só com a disciplina.'
                : (opc
                    ? 'A escola lança a nota. O professor é opcional.'
                    : 'O resumo fica aqui. No painel, professor e disciplina se filtram um pelo outro.');
        });
    }

    function sync() {
        var online = formatoAtual() === 'online_questoes';
        var boxOn = document.getElementById('cfgOnlineOptions');
        var boxOff = document.getElementById('cfgLancamentoOptions');
        var ativo = online ? boxOn : boxOff;
        var inativo = online ? boxOff : boxOn;
        var valor = (ativo.querySelector('input:checked') || {}).value
            || (inativo.querySelector('input:checked') || {}).value
            || 'professor_por_questao';
        if (boxOn) boxOn.classList.toggle('hidden', !online);
        if (boxOff) boxOff.classList.toggle('hidden', online);
        if (inativo) inativo.querySelectorAll('input').forEach(function (el) { el.checked = false; });
        if (ativo) {
            var alvo = ativo.querySelector('input[value="' + valor + '"]');
            if (alvo) alvo.checked = true;
        }
        var label = document.getElementById('labelResponsavelEvento');
        var ajuda = document.getElementById('helpResponsavelEvento');
        var prazoLabel = document.getElementById('labelPrazoProfessor');
        if (label) label.innerHTML = online ? 'Quem elabora a prova <span class="text-red-500">*</span>' : 'Quem lança a nota <span class="text-red-500">*</span>';
        if (ajuda) ajuda.textContent = online
            ? 'Atribua ao professor para ele criar as questões, ou a coordenação elabora a prova.'
            : 'Atribua ao professor ou deixe a escola lançar a nota. O professor é opcional.';
        if (prazoLabel) prazoLabel.innerHTML = online
            ? 'Prazo para professores enviarem provas <span class="text-red-500">*</span>'
            : 'Prazo para o professor lançar as notas <span class="text-red-500">*</span>';
        var porProfessor = configAtual() === 'professor_por_questao';
        var prazoBox = document.getElementById('prazoProfessorContainer');
        var notaUnica = document.getElementById('notaUnicaTodasMateriasBox');
        if (prazoBox) prazoBox.classList.toggle('hidden', !porProfessor);
        if (notaUnica) notaUnica.classList.toggle('hidden', online || porProfessor);
        var portal = document.getElementById('visivelPortalBox');
        var portalCheck = document.getElementById('visivel_no_portal_aluno');
        if (portal) portal.classList.toggle('hidden', !online);
        if (portalCheck) {
            portalCheck.disabled = !online;
            if (!online) portalCheck.checked = false;
        }
        aplicarPeriodicidade();
        if (blocoEventos) blocoEventos.classList.toggle('hidden', !online && origemOffline() === 'lancamento_direto');
        if (campoOrigem) campoOrigem.value = online ? 'prova_online' : origemOffline();
        if (criterio) {
            var o = online ? 'prova_online' : origemOffline();
            if (!online && estavaOnline) {
                if (registro && registro.value === 'acertos_questoes') registro.value = 'nota';
                if (criterio.value === 'aproveitamento_nq') criterio.value = 'ultima';
            }
            var nq = criterio.querySelector('option[value="aproveitamento_nq"]');
            if (nq) nq.disabled = o === 'lancamento_direto';
            if (o === 'lancamento_direto' && criterio.value === 'aproveitamento_nq') criterio.value = 'ultima';
            if (o === 'prova_online' && registro) registro.value = 'acertos_questoes';
            if (o === 'prova_online' && criterio.value === 'ultima') criterio.value = 'aproveitamento_nq';
        }
        estavaOnline = online;
        if (hintNq) hintNq.classList.toggle('hidden', !criterio || criterio.value !== 'aproveitamento_nq');
        atualizarParesUi();
    }
    function origemOffline() {
        var modo = form.querySelector('input[name="modo_offline"]:checked');
        return modo && modo.value === 'eventos' ? 'eventos' : 'lancamento_direto';
    }
    form.querySelectorAll('.js-canal, .js-config, .js-modo-offline').forEach(function (el) {
        el.addEventListener('change', sync);
    });
    if (criterio) criterio.addEventListener('change', sync);
    sync();
    render();
    if (edicao && edicao.id) {
        filtrarTurmasEnsino();
        remarcarTurmas(edicao.turmas || []);
    }
})();
</script>
<script>
(function () {
    var painel = document.getElementById('painel-recuperacao');
    if (!painel) return;
    var lista = document.getElementById('recuperacao-lista');
    var resumo = document.getElementById('recuperacao-resumo');
    var botao = document.getElementById('btn-puxar-abaixo');
    var definir = document.getElementById('definir-alunos-recuperacao');
    var marcados = {};
    (painel.getAttribute('data-marcados') || '').split(',').forEach(function (chave) {
        chave = String(chave || '').trim();
        if (chave.indexOf(':') > 0) marcados[chave] = true;
    });
    var alunos = [];
    var media = 6;
    var pedido = 0;
    var tabelaPronta = true;

    function turmasMarcadas() {
        var ids = [];
        document.querySelectorAll('.js-turma:checked').forEach(function (el) {
            var id = parseInt(el.value, 10);
            if (id > 0) ids.push(id);
        });
        return ids;
    }

    function escapar(texto) {
        return String(texto == null ? '' : texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function notaTexto(nota) {
        if (nota === null || nota === undefined || nota === '') return '';
        return Number(nota).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    }

    function chaveAluno(aluno) {
        return aluno.id + ':' + (aluno.materia_id || 0);
    }

    function atualizarResumo() {
        var abaixo = 0;
        var escolhidos = 0;
        alunos.forEach(function (aluno) {
            if (aluno.abaixo) abaixo += 1;
            if (marcados[chaveAluno(aluno)]) escolhidos += 1;
        });
        if (!alunos.length) return;
        resumo.textContent = 'Média ' + notaTexto(media)
            + ' · ' + abaixo + ' abaixo na matéria'
            + ' · ' + escolhidos + (escolhidos === 1 ? ' marcado' : ' marcados')
            + (tabelaPronta ? '' : '. Para gravar por matéria, rode a migration 2026_10_29_eventos_avaliativos_alunos_materia.sql no painel Master.');
    }

    function notaVisivel(aluno) {
        if (aluno.nota === null) {
            return '<span class="shrink-0 text-sm text-gray-400">Sem nota</span>';
        }
        var estilo = aluno.abaixo
            ? 'display:inline-flex;min-width:3.5rem;justify-content:center;align-items:center;border-radius:0.5rem;padding:0.3rem 0.55rem;font-size:1.05rem;font-weight:700;line-height:1.2;background-color:#dc2626;color:#fff'
            : 'display:inline-flex;min-width:3.5rem;justify-content:center;align-items:center;border-radius:0.5rem;padding:0.3rem 0.55rem;font-size:1.05rem;font-weight:700;line-height:1.2;background-color:#e5e7eb;color:#111827';
        return '<span class="shrink-0" style="' + estilo + '">' + notaTexto(aluno.nota) + '</span>';
    }

    function linhaAluno(aluno) {
        var selo = notaVisivel(aluno);
        var linha = aluno.abaixo
            ? 'text-red-900'
            : 'bg-white text-gray-900 hover:bg-gray-50';
        var faixa = aluno.abaixo
            ? ' style="background-color:#fef2f2;box-shadow:inset 4px 0 0 #dc2626"'
            : '';
        var chave = chaveAluno(aluno);
        return '<label class="flex items-center justify-between gap-3 border-b border-gray-100 px-3 py-2.5 cursor-pointer ' + linha + '"' + faixa + '>'
            + '<span class="flex min-w-0 items-center gap-2">'
            + '<input type="checkbox" name="alunos_recuperacao[]" value="' + chave + '" class="js-aluno-rec rounded border-gray-300"'
            + (marcados[chave] ? ' checked' : '') + '>'
            + '<span class="truncate text-sm font-medium">' + escapar(aluno.nome) + '</span>'
            + '</span>' + selo + '</label>';
    }

    var abaAtiva = 0;

    function materiasAgrupadas() {
        var materias = [];
        var porMateria = {};
        alunos.forEach(function (aluno) {
            var mid = aluno.materia_id || 0;
            if (!porMateria[mid]) {
                porMateria[mid] = { id: mid, nome: aluno.materia || 'Disciplina', turmas: [], abaixo: 0 };
                materias.push(porMateria[mid]);
            }
            var grupo = porMateria[mid];
            if (aluno.abaixo) grupo.abaixo += 1;
            var turma = null;
            grupo.turmas.forEach(function (item) {
                if (item.id === aluno.turma_id) turma = item;
            });
            if (!turma) {
                turma = { id: aluno.turma_id, nome: aluno.turma, itens: [] };
                grupo.turmas.push(turma);
            }
            turma.itens.push(aluno);
        });
        return materias;
    }

    function htmlTurmas(materia) {
        return materia.turmas.map(function (turma) {
            var abaixoTurma = 0;
            turma.itens.forEach(function (aluno) {
                if (aluno.abaixo) abaixoTurma += 1;
            });
            var resumoTurma = abaixoTurma > 0
                ? '<span class="text-xs font-medium text-red-700">' + abaixoTurma + ' abaixo</span>'
                : '<span class="text-xs text-gray-500">Ninguém abaixo</span>';
                return '<section class="overflow-hidden rounded-lg border border-gray-200 bg-white" data-turma="' + turma.id + '">'
                + '<header class="flex items-center justify-between gap-3 border-b border-gray-100 bg-gray-50 px-3 py-2">'
                + '<p class="min-w-0 text-sm font-semibold text-gray-800">' + escapar(turma.nome) + '</p>'
                + resumoTurma
                + '</header>'
                + '<div>' + turma.itens.map(linhaAluno).join('') + '</div>'
                + '</section>';
        }).join('');
    }

    function desenhar() {
        if (!alunos.length) {
            lista.innerHTML = '';
            return;
        }
        var materias = materiasAgrupadas();
        var ids = materias.map(function (materia) { return materia.id; });
        if (ids.indexOf(abaAtiva) < 0) abaAtiva = materias[0].id;
        var abas = materias.map(function (materia) {
            var ativa = materia.id === abaAtiva;
            var selo = materia.abaixo > 0
                ? '<span class="ml-1.5 rounded-full bg-red-100 px-1.5 py-0.5 text-xs font-semibold text-red-800">' + materia.abaixo + '</span>'
                : '';
            return '<button type="button" class="js-aba-materia inline-flex items-center rounded-full px-3 py-1 text-sm '
                + (ativa ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-100')
                + '" data-materia="' + materia.id + '" aria-pressed="' + (ativa ? 'true' : 'false') + '">'
                + escapar(materia.nome) + selo + '</button>';
        }).join('');
        var paineis = materias.map(function (materia) {
            var visivel = materia.id === abaAtiva;
            return '<div class="mt-3 space-y-3' + (visivel ? '' : ' hidden') + '" data-painel-materia="' + materia.id + '">'
                + htmlTurmas(materia)
                + '</div>';
        }).join('');
        lista.innerHTML = '<div class="flex flex-wrap gap-2" role="tablist">' + abas + '</div>' + paineis;
        atualizarResumo();
        document.dispatchEvent(new CustomEvent('recuperacao-lista-pronta'));
    }

    function puxarAbaixo() {
        var achou = false;
        alunos.forEach(function (aluno) {
            if (!aluno.abaixo) return;
            marcados[chaveAluno(aluno)] = true;
            achou = true;
        });
        if (definir && achou) definir.value = '1';
        desenhar();
        if (!achou) {
            resumo.textContent = alunos.length
                ? 'Nenhum aluno ficou abaixo da média. Você ainda pode marcar quem quiser fazer a prova.'
                : 'Marque as turmas para listar os alunos.';
        }
    }

    function carregar(opcoes) {
        var puxar = !!(opcoes && opcoes.puxar);
        var turmas = turmasMarcadas();
        pedido += 1;
        var atual = pedido;
        if (!turmas.length) {
            alunos = [];
            lista.innerHTML = '';
            resumo.textContent = 'Marque as turmas para listar os alunos.';
            return;
        }
        resumo.textContent = 'Carregando alunos…';
        var url = '/admin/eventos-avaliativos/alunos-recuperacao?ano=' + encodeURIComponent(painel.getAttribute('data-ano') || '')
            + '&periodo=' + encodeURIComponent(painel.getAttribute('data-periodo') || '')
            + '&regra=' + encodeURIComponent(painel.getAttribute('data-regra') || '0')
            + '&turmas=' + encodeURIComponent(turmas.join(','));
        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (dados) {
                if (atual !== pedido) return;
                if (!dados || !dados.ok) {
                    alunos = [];
                    lista.innerHTML = '';
                    resumo.textContent = (dados && dados.erro) ? dados.erro : 'Não foi possível carregar os alunos.';
                    return;
                }
                media = Number(dados.media) || 6;
                tabelaPronta = dados.tabela !== false;
                alunos = (dados.alunos || []).map(function (aluno) {
                    return {
                        id: parseInt(aluno.id, 10) || 0,
                        nome: aluno.nome || '',
                        turma_id: parseInt(aluno.turma_id, 10) || 0,
                        turma: aluno.turma || '',
                        materia_id: parseInt(aluno.materia_id, 10) || 0,
                        materia: aluno.materia || '',
                        nota: aluno.nota === null || aluno.nota === undefined ? null : Number(aluno.nota),
                        abaixo: !!aluno.abaixo
                    };
                });
                if (!alunos.length) {
                    lista.innerHTML = '';
                    resumo.textContent = 'Nenhuma nota do período nessas turmas para separar por matéria.';
                    return;
                }
                if (puxar) puxarAbaixo();
                else desenhar();
            })
            .catch(function () {
                if (atual !== pedido) return;
                alunos = [];
                lista.innerHTML = '';
                resumo.textContent = 'Não foi possível carregar os alunos.';
            });
    }

    lista.addEventListener('click', function (evento) {
        var btn = evento.target && evento.target.closest ? evento.target.closest('.js-aba-materia') : null;
        if (!btn || !lista.contains(btn)) return;
        var id = parseInt(btn.getAttribute('data-materia'), 10) || 0;
        if (!id || id === abaAtiva) return;
        abaAtiva = id;
        desenhar();
    });

    document.addEventListener('change', function (evento) {
        var alvo = evento.target;
        if (!alvo) return;
        if (alvo.classList && alvo.classList.contains('js-turma')) {
            carregar();
            return;
        }
        if (alvo.classList && alvo.classList.contains('js-aluno-rec')) {
            var chave = String(alvo.value || '');
            if (chave.indexOf(':') > 0) {
                if (alvo.checked) marcados[chave] = true;
                else delete marcados[chave];
            }
            if (definir) definir.value = '1';
            atualizarResumo();
        }
    });

    if (botao) {
        botao.addEventListener('click', function () {
            if (!turmasMarcadas().length) {
                resumo.textContent = 'Marque as turmas para listar os alunos.';
                return;
            }
            carregar({ puxar: true });
        });
    }

    carregar();
})();
</script>
