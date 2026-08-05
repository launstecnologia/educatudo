<?php
$projeto = $projeto ?? null;
$relacoes = $relacoes ?? [];
$catalogos = $catalogos ?? [];
$csrf_token = $csrf_token ?? '';
$config = $catalogos['config_edicao'] ?? [];
$materias = $catalogos['materias'] ?? [];
$professores = $catalogos['professores'] ?? [];
$series = $catalogos['series'] ?? [];
$criteriosPadrao = $catalogos['criterios_banca_padrao'] ?? [];

$pid = (int) ($projeto['id'] ?? 0);
$status = (string) ($projeto['status'] ?? 'Rascunho');

$matConectadas = array_map('intval', array_column($relacoes['materias'] ?? [], 'materia_id'));
$profParceiros = array_map('intval', array_column($relacoes['professores'] ?? [], 'professor_id'));
$objetivosTxt = implode("\n", array_column($relacoes['objetivos'] ?? [], 'texto'));
$tiposTrabalho = array_column($relacoes['tipos_trabalho'] ?? [], 'tipo');
$visib = $relacoes['visibilidade'] ?? [];
$visSeries = [];
$visTurmas = [];
$visAlunos = [];
foreach ($visib as $v) {
    if (($v['escopo'] ?? '') === 'Serie') {
        $visSeries[] = (int) $v['referencia_id'];
    } elseif (($v['escopo'] ?? '') === 'Turma') {
        $visTurmas[] = (int) $v['referencia_id'];
    } elseif (($v['escopo'] ?? '') === 'Aluno') {
        $visAlunos[] = (int) $v['referencia_id'];
    }
}

$etapas = $relacoes['etapas'] ?? [];
$encontros = $relacoes['encontros'] ?? [];
$rubrica = $relacoes['rubrica'] ?? [];
if ($rubrica === [] && $criteriosPadrao) {
    $rubrica = $criteriosPadrao;
}
$papeis = $relacoes['papeis'] ?? [];
$habilidades = $relacoes['habilidades'] ?? [];
$materiais = $relacoes['materiais'] ?? [];

$inscIni = !empty($projeto['inscricoes_inicio']) ? substr($projeto['inscricoes_inicio'], 0, 10) : ($config['inscricoes_inicio'] ?? '');
$inscFim = !empty($projeto['inscricoes_fim']) ? substr($projeto['inscricoes_fim'], 0, 10) : ($config['inscricoes_fim'] ?? '');

$steps = [
    ['n' => 1, 'label' => 'Identificação', 'sub' => 'Capa e matérias'],
    ['n' => 2, 'label' => 'Proposta', 'sub' => 'Pedagógica'],
    ['n' => 3, 'label' => 'Formato', 'sub' => 'Participação'],
    ['n' => 4, 'label' => 'Visibilidade', 'sub' => 'Quem vê'],
    ['n' => 5, 'label' => 'Cronograma', 'sub' => 'Etapas'],
    ['n' => 6, 'label' => 'Recursos', 'sub' => 'Tudinha'],
];

$formatos = [];
if (!empty($projeto['formatos_aceitos'])) {
    $decoded = is_string($projeto['formatos_aceitos'])
        ? json_decode($projeto['formatos_aceitos'], true)
        : $projeto['formatos_aceitos'];
    $formatos = is_array($decoded) ? $decoded : [];
}
?>
<div class="max-w-5xl mx-auto px-4 py-6 space-y-6" id="expoWizard"
     data-projeto-id="<?= $pid ?>"
     data-url-base="<?= htmlspecialchars(URL) ?>"
     data-alunos-url="<?= htmlspecialchars(URL . '/professor/expo-colag/alunos-turma') ?>"
     data-bncc-url="<?= htmlspecialchars(URL . '/professor/expo-colag/bncc') ?>">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="<?= URL ?>/professor/expo-colag" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50" aria-label="Voltar">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900"><?= $pid ? 'Editar projeto' : 'Criar projeto' ?></h1>
                <p class="text-sm text-gray-600">Expo Colag · rascunho persistente em 6 blocos</p>
            </div>
        </div>
        <span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-slate-100 text-slate-700">
            <?= htmlspecialchars(str_replace('_', ' ', $status)) ?>
        </span>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-center gap-2 sm:gap-0" role="tablist">
        <?php foreach ($steps as $i => $s): ?>
            <button type="button" data-step-target="<?= (int) $s['n'] ?>"
                    class="step-nav-btn group flex items-center gap-2 rounded-xl border-2 px-3 py-2 text-left transition sm:flex-1
                           border-gray-200 bg-white text-gray-600 hover:border-primary/40
                           data-[active=true]:border-primary data-[active=true]:bg-primary data-[active=true]:text-white">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold border-2 border-current"><?= (int) $s['n'] ?></span>
                <span class="min-w-0 hidden md:block">
                    <span class="block text-sm font-semibold leading-tight"><?= htmlspecialchars($s['label']) ?></span>
                    <span class="block text-xs opacity-80"><?= htmlspecialchars($s['sub']) ?></span>
                </span>
            </button>
            <?php if ($i < count($steps) - 1): ?>
                <div class="hidden sm:block w-4 h-0.5 bg-gray-200 shrink-0" aria-hidden="true"></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <form id="expoWizardForm" method="post" action="<?= URL ?>/professor/expo-colag/projetos/salvar" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="projeto_id" value="<?= $pid ?>">
        <input type="hidden" name="acao" id="campoAcao" value="rascunho">
        <input type="hidden" name="ajax" value="1">
        <input type="hidden" name="visibilidade" id="campoVisibilidade" value="">
        <input type="hidden" name="papeis" id="campoPapeis" value="">
        <input type="hidden" name="etapas" id="campoEtapas" value="">
        <input type="hidden" name="encontros" id="campoEncontros" value="">
        <input type="hidden" name="rubrica" id="campoRubrica" value="">
        <input type="hidden" name="materiais" id="campoMateriais" value="">
        <input type="hidden" name="habilidades" id="campoHabilidades" value="">

        <!-- Bloco 1 -->
        <section data-step="1" class="wizard-step rounded-xl border border-gray-200 bg-white p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">1. Identificação</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Título *</label>
                    <input type="text" name="titulo" required maxlength="255" value="<?= htmlspecialchars($projeto['titulo'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subtítulo</label>
                    <input type="text" name="subtitulo" maxlength="255" value="<?= htmlspecialchars($projeto['subtitulo'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Área</label>
                    <input type="text" name="area" maxlength="120" value="<?= htmlspecialchars($projeto['area'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white" placeholder="Ciências, Tecnologia…">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Matéria principal</label>
                    <select name="materia_principal_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <option value="">—</option>
                        <?php foreach ($materias as $m): ?>
                            <option value="<?= (int) $m['id'] ?>" <?= (int) ($projeto['materia_principal_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Capa (JPG/PNG/WebP)</label>
                    <input type="file" name="capa" accept="image/jpeg,image/png,image/webp"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm">
                    <?php if (!empty($projeto['capa_url'])): ?>
                        <p class="text-xs text-gray-500 mt-1">Atual: <a href="<?= htmlspecialchars($projeto['capa_url']) ?>" target="_blank" class="text-primary">ver capa</a></p>
                        <input type="hidden" name="capa_url" value="<?= htmlspecialchars($projeto['capa_url']) ?>">
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Matérias conectadas</label>
                <select name="materias_conectadas[]" multiple size="5" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                    <?php foreach ($materias as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" <?= in_array((int) $m['id'], $matConectadas, true) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Segure Ctrl/Cmd para selecionar várias.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Professores parceiros</label>
                <select name="professores_parceiros[]" multiple size="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                    <?php foreach ($professores as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $profParceiros, true) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </section>

        <!-- Bloco 2 -->
        <section data-step="2" class="wizard-step hidden rounded-xl border border-gray-200 bg-white p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">2. Proposta pedagógica</h2>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descrição *</label>
                <textarea name="descricao" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white"><?= htmlspecialchars($projeto['descricao'] ?? '') ?></textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contexto prático</label>
                    <textarea name="contexto_pratico" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white"><?= htmlspecialchars($projeto['contexto_pratico'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Produto esperado</label>
                    <textarea name="produto_esperado" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white"><?= htmlspecialchars($projeto['produto_esperado'] ?? '') ?></textarea>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Objetivos (um por linha)</label>
                <textarea name="objetivos" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white"><?= htmlspecialchars($objetivosTxt) ?></textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Conexões interdisciplinares</label>
                    <textarea name="conexoes_interdisciplinares" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white"><?= htmlspecialchars($projeto['conexoes_interdisciplinares'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pré-requisitos</label>
                    <textarea name="pre_requisitos" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white"><?= htmlspecialchars($projeto['pre_requisitos'] ?? '') ?></textarea>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Habilidades BNCC</label>
                <div class="flex gap-2 mb-2">
                    <input type="text" id="bnccBusca" placeholder="Buscar código ou descrição…" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm">
                    <button type="button" id="btnBnccBusca" class="px-3 py-2 rounded-lg bg-gray-100 text-sm font-medium hover:bg-gray-200">Buscar</button>
                </div>
                <div id="bnccResultados" class="text-sm space-y-1 mb-2 max-h-32 overflow-y-auto"></div>
                <div id="bnccSelecionadas" class="flex flex-wrap gap-2">
                    <?php foreach ($habilidades as $h): ?>
                        <span class="bncc-chip inline-flex items-center gap-1 px-2 py-1 rounded-full bg-indigo-50 text-indigo-800 text-xs" data-codigo="<?= htmlspecialchars($h['codigo_habilidade']) ?>" data-hid="<?= (int) ($h['habilidade_id'] ?? 0) ?>">
                            <?= htmlspecialchars($h['codigo_habilidade']) ?>
                            <button type="button" class="bncc-remove font-bold">&times;</button>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Bloco 3 -->
        <section data-step="3" class="wizard-step hidden rounded-xl border border-gray-200 bg-white p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">3. Formato e participação</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Modalidade</label>
                    <select name="modalidade" id="campoModalidade" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <?php foreach (['Grupo', 'Individual', 'Grupo_com_papeis'] as $mod): ?>
                            <option value="<?= $mod ?>" <?= ($projeto['modalidade'] ?? 'Grupo') === $mod ? 'selected' : '' ?>><?= str_replace('_', ' ', $mod) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vagas totais</label>
                    <input type="number" name="vagas_totais" min="1" value="<?= (int) ($projeto['vagas_totais'] ?? ($config['grupo_max'] ?? 5)) ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vagas mínimas</label>
                    <input type="number" name="vagas_minimas" min="1" value="<?= (int) ($projeto['vagas_minimas'] ?? ($config['grupo_min'] ?? 3)) ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tamanho do grupo</label>
                    <input type="number" name="tamanho_grupo" min="1" value="<?= (int) ($projeto['tamanho_grupo'] ?? 0) ?: '' ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Modo de ingresso</label>
                    <select name="modo_ingresso" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <?php foreach (['Livre' => 'Livre', 'Com_aprovacao' => 'Com aprovação', 'Convite_direto' => 'Convite direto'] as $val => $lab): ?>
                            <option value="<?= $val ?>" <?= ($projeto['modo_ingresso'] ?? 'Livre') === $val ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="exige_justificativa" value="1" <?= !empty($projeto['exige_justificativa']) ? 'checked' : '' ?>> Exige justificativa</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="lista_espera_ativa" value="1" <?= ($projeto['lista_espera_ativa'] ?? 1) ? 'checked' : '' ?>> Lista de espera</label>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipos de trabalho</label>
                <?php
                $tiposOpcoes = ['Pesquisa', 'Experimentação', 'Protótipo', 'Maquete', 'Campanha', 'Documentário', 'Outro'];
                foreach ($tiposOpcoes as $tipo):
                ?>
                    <label class="inline-flex items-center gap-2 mr-4 mb-2 text-sm">
                        <input type="checkbox" name="tipos_trabalho[]" value="<?= htmlspecialchars($tipo) ?>" <?= in_array($tipo, $tiposTrabalho, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($tipo) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div id="blocoPapeis" class="<?= ($projeto['modalidade'] ?? '') === 'Grupo_com_papeis' ? '' : 'hidden' ?>">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-gray-700">Papéis no grupo</label>
                    <button type="button" id="btnAddPapel" class="text-sm text-primary font-medium">+ Papel</button>
                </div>
                <div id="listaPapeis" class="space-y-2">
                    <?php if ($papeis === []): ?>
                        <div class="papel-row grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <input type="text" class="papel-nome border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Nome do papel">
                            <input type="text" class="papel-desc border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Descrição">
                            <input type="number" class="papel-vagas border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" min="1" value="1" placeholder="Vagas">
                        </div>
                    <?php else: foreach ($papeis as $papel): ?>
                        <div class="papel-row grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <input type="text" class="papel-nome border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" value="<?= htmlspecialchars($papel['nome'] ?? '') ?>">
                            <input type="text" class="papel-desc border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" value="<?= htmlspecialchars($papel['descricao'] ?? '') ?>">
                            <input type="number" class="papel-vagas border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" min="1" value="<?= (int) ($papel['vagas'] ?? 1) ?>">
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </section>

        <!-- Bloco 4 -->
        <section data-step="4" class="wizard-step hidden rounded-xl border border-gray-200 bg-white p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">4. Visibilidade e inscrições</h2>
            <p class="text-sm text-gray-600">Marque séries e/ou turmas. Opcionalmente refine por aluno.</p>
            <div id="arvoreVisibilidade" class="space-y-3 max-h-80 overflow-y-auto border border-gray-100 rounded-lg p-3">
                <?php foreach ($series as $serie): ?>
                    <?php $sid = (int) ($serie['referencia_id'] ?? $serie['id'] ?? 0); ?>
                    <div class="serie-block">
                        <label class="inline-flex items-center gap-2 font-medium text-sm text-gray-800">
                            <input type="checkbox" class="vis-serie" data-serie-id="<?= $sid ?>" <?= in_array($sid, $visSeries, true) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($serie['nome'] ?? 'Série') ?>
                        </label>
                        <div class="ml-6 mt-1 space-y-1">
                            <?php foreach ($serie['turmas'] ?? [] as $turma): ?>
                                <div class="turma-block">
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" class="vis-turma" data-turma-id="<?= (int) $turma['id'] ?>" <?= in_array((int) $turma['id'], $visTurmas, true) ? 'checked' : '' ?>>
                                        <?= htmlspecialchars($turma['nome']) ?>
                                    </label>
                                    <button type="button" class="btn-load-alunos text-xs text-primary ml-2" data-turma-id="<?= (int) $turma['id'] ?>">alunos</button>
                                    <div class="alunos-box ml-4 mt-1 space-y-1 hidden" data-turma-id="<?= (int) $turma['id'] ?>"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <script type="application/json" id="visAlunosSeed"><?= json_encode(array_values($visAlunos)) ?></script>
            <div id="visAlunosHidden">
                <?php foreach ($visAlunos as $aid): ?>
                    <input type="hidden" class="vis-aluno-seed" data-aluno-id="<?= (int) $aid ?>" value="<?= (int) $aid ?>">
                <?php endforeach; ?>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Inscrições — início</label>
                    <input type="date" name="inscricoes_inicio" value="<?= htmlspecialchars($inscIni) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Inscrições — fim</label>
                    <input type="date" name="inscricoes_fim" value="<?= htmlspecialchars($inscFim) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                </div>
            </div>
        </section>

        <!-- Bloco 5 -->
        <section data-step="5" class="wizard-step hidden rounded-xl border border-gray-200 bg-white p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">5. Cronograma e entrega</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Início do projeto</label>
                    <input type="date" name="data_inicio" value="<?= htmlspecialchars($projeto['data_inicio'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fim do projeto</label>
                    <input type="date" name="data_fim" value="<?= htmlspecialchars($projeto['data_fim'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Apresentação</label>
                    <input type="datetime-local" name="data_apresentacao" value="<?= !empty($projeto['data_apresentacao']) ? htmlspecialchars(str_replace(' ', 'T', substr($projeto['data_apresentacao'], 0, 16))) : '' ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                </div>
            </div>
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-gray-700">Etapas</label>
                    <button type="button" id="btnAddEtapa" class="text-sm text-primary font-medium">+ Etapa</button>
                </div>
                <div id="listaEtapas" class="space-y-3">
                    <?php
                    $etapasRender = $etapas !== [] ? $etapas : [['titulo' => '', 'data_limite' => '', 'descricao' => '', 'entregavel_esperado' => '']];
                    foreach ($etapasRender as $et):
                    ?>
                    <div class="etapa-row grid grid-cols-1 sm:grid-cols-2 gap-2 border border-gray-100 rounded-lg p-3">
                        <input type="text" class="etapa-titulo border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Título da etapa" value="<?= htmlspecialchars($et['titulo'] ?? '') ?>">
                        <input type="date" class="etapa-data border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" value="<?= htmlspecialchars($et['data_limite'] ?? '') ?>">
                        <input type="text" class="etapa-desc border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm sm:col-span-2" placeholder="Descrição" value="<?= htmlspecialchars($et['descricao'] ?? '') ?>">
                        <input type="text" class="etapa-entregavel border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm sm:col-span-2" placeholder="Entregável esperado" value="<?= htmlspecialchars($et['entregavel_esperado'] ?? '') ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-gray-700">Encontros</label>
                    <button type="button" id="btnAddEncontro" class="text-sm text-primary font-medium">+ Encontro</button>
                </div>
                <div id="listaEncontros" class="space-y-2">
                    <?php foreach ($encontros as $en): ?>
                    <div class="encontro-row grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <input type="text" class="encontro-rotulo border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" value="<?= htmlspecialchars($en['rotulo'] ?? '') ?>" placeholder="Rótulo">
                        <input type="datetime-local" class="encontro-data border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" value="<?= !empty($en['data_hora']) ? htmlspecialchars(str_replace(' ', 'T', substr($en['data_hora'], 0, 16))) : '' ?>">
                        <input type="url" class="encontro-link border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" value="<?= htmlspecialchars($en['link'] ?? '') ?>" placeholder="Link (opcional)">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Briefing de entrega</label>
                <textarea name="briefing_entrega" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white"><?= htmlspecialchars($projeto['briefing_entrega'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Formatos aceitos (separados por vírgula)</label>
                <input type="text" name="formatos_aceitos" value="<?= htmlspecialchars(implode(', ', $formatos)) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white" placeholder="PDF, vídeo, link, maquete…">
            </div>
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-gray-700">Rubrica</label>
                    <button type="button" id="btnAddCriterio" class="text-sm text-primary font-medium">+ Critério</button>
                </div>
                <div id="listaRubrica" class="space-y-2">
                    <?php foreach ($rubrica as $r): ?>
                    <div class="rubrica-row grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <input type="text" class="rubrica-criterio border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" value="<?= htmlspecialchars($r['criterio'] ?? '') ?>" placeholder="Critério">
                        <input type="number" class="rubrica-peso border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" step="0.01" value="<?= htmlspecialchars((string) ($r['peso'] ?? 0)) ?>" placeholder="Peso %">
                        <input type="text" class="rubrica-desc border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" value="<?= htmlspecialchars($r['descricao'] ?? '') ?>" placeholder="Descrição">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="vale_nota" value="1" <?= !empty($projeto['vale_nota']) ? 'checked' : '' ?>> Vale nota
            </label>
        </section>

        <!-- Bloco 6 -->
        <section data-step="6" class="wizard-step hidden rounded-xl border border-gray-200 bg-white p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">6. Recursos</h2>
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-gray-700">Materiais / links iniciais</label>
                    <button type="button" id="btnAddMaterial" class="text-sm text-primary font-medium">+ Material</button>
                </div>
                <div id="listaMateriais" class="space-y-2">
                    <?php foreach ($materiais as $mat): ?>
                    <div class="material-row grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <input type="text" class="mat-titulo border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" value="<?= htmlspecialchars($mat['titulo'] ?? '') ?>" placeholder="Título">
                        <input type="url" class="mat-link border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" value="<?= htmlspecialchars($mat['link_externo'] ?? $mat['arquivo_url'] ?? '') ?>" placeholder="URL">
                        <select class="mat-tipo border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm">
                            <option value="link" <?= ($mat['tipo'] ?? '') === 'link' ? 'selected' : '' ?>>Link</option>
                            <option value="arquivo" <?= ($mat['tipo'] ?? '') === 'arquivo' ? 'selected' : '' ?>>Arquivo</option>
                        </select>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="tudinha_ativa" value="1" <?= !empty($projeto['tudinha_ativa']) ? 'checked' : '' ?>> Tudinha ativa neste projeto</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="permite_solicitacao_recursos" value="1" <?= ($projeto['permite_solicitacao_recursos'] ?? 1) ? 'checked' : '' ?>> Permitir solicitação de recursos</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="destaque" value="1" <?= !empty($projeto['destaque']) ? 'checked' : '' ?>> Destacar no mural</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="ativo" value="1" <?= ($projeto['ativo'] ?? 1) ? 'checked' : '' ?>> Ativo</label>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contexto da Tudinha</label>
                <textarea name="tudinha_contexto" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white"><?= htmlspecialchars($projeto['tudinha_contexto'] ?? '') ?></textarea>
            </div>
            <div class="max-w-xs">
                <label class="block text-sm font-medium text-gray-700 mb-1">Custo TudiCoins</label>
                <input type="number" name="custo_tudicoins" min="0" step="0.01" value="<?= htmlspecialchars((string) ($projeto['custo_tudicoins'] ?? '0')) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
            </div>
        </section>

        <div id="wizardMsg" class="hidden rounded-lg px-4 py-3 text-sm"></div>

        <div class="flex flex-wrap items-center gap-3 sticky bottom-0 bg-gray-50/95 backdrop-blur border-t border-gray-200 py-4 -mx-4 px-4">
            <button type="button" id="btnPrev" class="px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200">Anterior</button>
            <button type="button" id="btnNext" class="px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200">Próximo</button>
            <div class="flex-1"></div>
            <button type="submit" data-acao="rascunho" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium">Salvar rascunho</button>
            <?php if ($pid > 0): ?>
                <a href="<?= URL ?>/professor/expo-colag/projetos/<?= $pid ?>/preview" class="px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 bg-white hover:bg-gray-50">Pré-visualizar</a>
            <?php endif; ?>
            <button type="submit" data-acao="publicar" class="px-4 py-2 rounded-lg text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700">Publicar</button>
        </div>
    </form>
</div>

<script>
(function () {
    var root = document.getElementById('expoWizard');
    if (!root) return;
    var step = 1;
    var maxStep = 6;
    var form = document.getElementById('expoWizardForm');
    var alunosSeed = [];
    try { alunosSeed = JSON.parse(document.getElementById('visAlunosSeed').textContent || '[]'); } catch (e) {}

    function showStep(n) {
        step = Math.max(1, Math.min(maxStep, n));
        root.querySelectorAll('.wizard-step').forEach(function (el) {
            el.classList.toggle('hidden', parseInt(el.getAttribute('data-step'), 10) !== step);
        });
        root.querySelectorAll('.step-nav-btn').forEach(function (btn) {
            btn.setAttribute('data-active', parseInt(btn.getAttribute('data-step-target'), 10) === step ? 'true' : 'false');
        });
        document.getElementById('btnPrev').disabled = step === 1;
        document.getElementById('btnNext').disabled = step === maxStep;
    }

    root.querySelectorAll('.step-nav-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            showStep(parseInt(btn.getAttribute('data-step-target'), 10));
        });
    });
    document.getElementById('btnPrev').addEventListener('click', function () { showStep(step - 1); });
    document.getElementById('btnNext').addEventListener('click', function () { showStep(step + 1); });

    document.getElementById('campoModalidade').addEventListener('change', function () {
        document.getElementById('blocoPapeis').classList.toggle('hidden', this.value !== 'Grupo_com_papeis');
    });

    document.getElementById('btnAddPapel').addEventListener('click', function () {
        var wrap = document.getElementById('listaPapeis');
        var div = document.createElement('div');
        div.className = 'papel-row grid grid-cols-1 sm:grid-cols-3 gap-2';
        div.innerHTML = '<input type="text" class="papel-nome border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Nome do papel">' +
            '<input type="text" class="papel-desc border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Descrição">' +
            '<input type="number" class="papel-vagas border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" min="1" value="1">';
        wrap.appendChild(div);
    });

    function addEtapa(data) {
        data = data || {};
        var wrap = document.getElementById('listaEtapas');
        var div = document.createElement('div');
        div.className = 'etapa-row grid grid-cols-1 sm:grid-cols-2 gap-2 border border-gray-100 rounded-lg p-3';
        div.innerHTML = '<input type="text" class="etapa-titulo border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Título da etapa" value="' + (data.titulo || '') + '">' +
            '<input type="date" class="etapa-data border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" value="' + (data.data_limite || '') + '">' +
            '<input type="text" class="etapa-desc border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm sm:col-span-2" placeholder="Descrição" value="' + (data.descricao || '') + '">' +
            '<input type="text" class="etapa-entregavel border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm sm:col-span-2" placeholder="Entregável" value="' + (data.entregavel_esperado || '') + '">';
        wrap.appendChild(div);
    }
    document.getElementById('btnAddEtapa').addEventListener('click', function () { addEtapa(); });

    document.getElementById('btnAddEncontro').addEventListener('click', function () {
        var wrap = document.getElementById('listaEncontros');
        var div = document.createElement('div');
        div.className = 'encontro-row grid grid-cols-1 sm:grid-cols-3 gap-2';
        div.innerHTML = '<input type="text" class="encontro-rotulo border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Rótulo">' +
            '<input type="datetime-local" class="encontro-data border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm">' +
            '<input type="url" class="encontro-link border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Link">';
        wrap.appendChild(div);
    });

    document.getElementById('btnAddCriterio').addEventListener('click', function () {
        var wrap = document.getElementById('listaRubrica');
        var div = document.createElement('div');
        div.className = 'rubrica-row grid grid-cols-1 sm:grid-cols-3 gap-2';
        div.innerHTML = '<input type="text" class="rubrica-criterio border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Critério">' +
            '<input type="number" class="rubrica-peso border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" step="0.01" placeholder="Peso %">' +
            '<input type="text" class="rubrica-desc border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Descrição">';
        wrap.appendChild(div);
    });

    document.getElementById('btnAddMaterial').addEventListener('click', function () {
        var wrap = document.getElementById('listaMateriais');
        var div = document.createElement('div');
        div.className = 'material-row grid grid-cols-1 sm:grid-cols-3 gap-2';
        div.innerHTML = '<input type="text" class="mat-titulo border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Título">' +
            '<input type="url" class="mat-link border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="URL">' +
            '<select class="mat-tipo border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm"><option value="link">Link</option><option value="arquivo">Arquivo</option></select>';
        wrap.appendChild(div);
    });

    // BNCC
    document.getElementById('btnBnccBusca').addEventListener('click', function () {
        var q = document.getElementById('bnccBusca').value.trim();
        var box = document.getElementById('bnccResultados');
        box.innerHTML = 'Buscando…';
        fetch(root.getAttribute('data-bncc-url') + '?q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                box.innerHTML = '';
                (data.habilidades || []).forEach(function (h) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'block w-full text-left px-2 py-1 rounded hover:bg-indigo-50 text-xs';
                    btn.textContent = h.codigo + ' — ' + (h.descricao || '').slice(0, 80);
                    btn.addEventListener('click', function () { addBnccChip(h.codigo, h.id); });
                    box.appendChild(btn);
                });
                if (!(data.habilidades || []).length) box.textContent = 'Nenhuma habilidade encontrada.';
            });
    });

    function addBnccChip(codigo, hid) {
        var wrap = document.getElementById('bnccSelecionadas');
        if (wrap.querySelector('[data-codigo="' + codigo + '"]')) return;
        var span = document.createElement('span');
        span.className = 'bncc-chip inline-flex items-center gap-1 px-2 py-1 rounded-full bg-indigo-50 text-indigo-800 text-xs';
        span.setAttribute('data-codigo', codigo);
        span.setAttribute('data-hid', hid || 0);
        span.innerHTML = codigo + ' <button type="button" class="bncc-remove font-bold">&times;</button>';
        span.querySelector('.bncc-remove').addEventListener('click', function () { span.remove(); });
        wrap.appendChild(span);
    }
    document.querySelectorAll('.bncc-remove').forEach(function (btn) {
        btn.addEventListener('click', function () { btn.closest('.bncc-chip').remove(); });
    });

    // Alunos por turma
    root.querySelectorAll('.btn-load-alunos').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tid = btn.getAttribute('data-turma-id');
            var box = root.querySelector('.alunos-box[data-turma-id="' + tid + '"]');
            if (!box) return;
            box.classList.remove('hidden');
            if (box.dataset.loaded === '1') return;
            box.textContent = 'Carregando…';
            fetch(root.getAttribute('data-alunos-url') + '?turma_id=' + encodeURIComponent(tid))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    box.innerHTML = '';
                    (data.alunos || []).forEach(function (a) {
                        var lab = document.createElement('label');
                        lab.className = 'inline-flex items-center gap-2 text-xs text-gray-600 mr-3';
                        var checked = alunosSeed.indexOf(parseInt(a.id, 10)) >= 0 ? ' checked' : '';
                        lab.innerHTML = '<input type="checkbox" class="vis-aluno" data-aluno-id="' + a.id + '"' + checked + '> ' + a.nome;
                        box.appendChild(lab);
                    });
                    box.dataset.loaded = '1';
                });
        });
    });

    function collectJsonFields() {
        var vis = [];
        root.querySelectorAll('.vis-serie:checked').forEach(function (el) {
            vis.push({ escopo: 'Serie', referencia_id: parseInt(el.getAttribute('data-serie-id'), 10) });
        });
        root.querySelectorAll('.vis-turma:checked').forEach(function (el) {
            vis.push({ escopo: 'Turma', referencia_id: parseInt(el.getAttribute('data-turma-id'), 10) });
        });
        var alunosSeen = {};
        root.querySelectorAll('.vis-aluno:checked').forEach(function (el) {
            var id = parseInt(el.getAttribute('data-aluno-id'), 10);
            if (id && !alunosSeen[id]) {
                alunosSeen[id] = true;
                vis.push({ escopo: 'Aluno', referencia_id: id });
            }
        });
        // Seeds: alunos já salvos que ainda não foram recarregados no DOM
        root.querySelectorAll('.vis-aluno-seed').forEach(function (el) {
            var id = parseInt(el.getAttribute('data-aluno-id') || el.value, 10);
            if (id && !alunosSeen[id]) {
                alunosSeen[id] = true;
                vis.push({ escopo: 'Aluno', referencia_id: id });
            }
        });
        document.getElementById('campoVisibilidade').value = JSON.stringify(vis);

        var papeis = [];
        root.querySelectorAll('.papel-row').forEach(function (row) {
            var nome = (row.querySelector('.papel-nome') || {}).value || '';
            if (!nome.trim()) return;
            papeis.push({
                nome: nome.trim(),
                descricao: ((row.querySelector('.papel-desc') || {}).value || '').trim(),
                vagas: parseInt((row.querySelector('.papel-vagas') || {}).value || '1', 10) || 1
            });
        });
        document.getElementById('campoPapeis').value = JSON.stringify(papeis);

        var etapas = [];
        root.querySelectorAll('.etapa-row').forEach(function (row, idx) {
            var titulo = ((row.querySelector('.etapa-titulo') || {}).value || '').trim();
            if (!titulo) return;
            etapas.push({
                ordem: idx + 1,
                titulo: titulo,
                data_limite: (row.querySelector('.etapa-data') || {}).value || '',
                descricao: ((row.querySelector('.etapa-desc') || {}).value || '').trim(),
                entregavel_esperado: ((row.querySelector('.etapa-entregavel') || {}).value || '').trim()
            });
        });
        document.getElementById('campoEtapas').value = JSON.stringify(etapas);

        var encontros = [];
        root.querySelectorAll('.encontro-row').forEach(function (row) {
            var rotulo = ((row.querySelector('.encontro-rotulo') || {}).value || '').trim();
            var data = (row.querySelector('.encontro-data') || {}).value || '';
            if (!rotulo || !data) return;
            encontros.push({
                rotulo: rotulo,
                data_hora: data,
                link: ((row.querySelector('.encontro-link') || {}).value || '').trim()
            });
        });
        document.getElementById('campoEncontros').value = JSON.stringify(encontros);

        var rubrica = [];
        root.querySelectorAll('.rubrica-row').forEach(function (row) {
            var criterio = ((row.querySelector('.rubrica-criterio') || {}).value || '').trim();
            if (!criterio) return;
            rubrica.push({
                criterio: criterio,
                peso: parseFloat((row.querySelector('.rubrica-peso') || {}).value || '0') || 0,
                descricao: ((row.querySelector('.rubrica-desc') || {}).value || '').trim()
            });
        });
        document.getElementById('campoRubrica').value = JSON.stringify(rubrica);

        var materiais = [];
        root.querySelectorAll('.material-row').forEach(function (row) {
            var titulo = ((row.querySelector('.mat-titulo') || {}).value || '').trim();
            if (!titulo) return;
            var link = ((row.querySelector('.mat-link') || {}).value || '').trim();
            var tipo = (row.querySelector('.mat-tipo') || {}).value || 'link';
            materiais.push({ titulo: titulo, tipo: tipo, link_externo: link });
        });
        document.getElementById('campoMateriais').value = JSON.stringify(materiais);

        var habs = [];
        root.querySelectorAll('.bncc-chip').forEach(function (chip) {
            habs.push({
                codigo: chip.getAttribute('data-codigo'),
                habilidade_id: parseInt(chip.getAttribute('data-hid') || '0', 10) || null
            });
        });
        document.getElementById('campoHabilidades').value = JSON.stringify(habs);
    }

    form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('campoAcao').value = btn.getAttribute('data-acao') || 'rascunho';
        });
    });

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        collectJsonFields();
        var msg = document.getElementById('wizardMsg');
        msg.classList.add('hidden');
        var fd = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: fd,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
              msg.classList.remove('hidden');
              msg.className = 'rounded-lg px-4 py-3 text-sm ' + (res.j.success ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200');
              msg.textContent = res.j.message || (res.j.success ? 'Salvo.' : 'Erro.');
              if (res.j.success && res.j.id && !form.querySelector('[name="projeto_id"]').value) {
                  form.querySelector('[name="projeto_id"]').value = res.j.id;
                  history.replaceState(null, '', root.getAttribute('data-url-base') + '/professor/expo-colag/projetos/' + res.j.id + '/editar');
              }
              if (res.j.success && res.j.redirect && document.getElementById('campoAcao').value === 'publicar') {
                  window.location.href = res.j.redirect;
              }
          }).catch(function () {
              msg.classList.remove('hidden');
              msg.className = 'rounded-lg px-4 py-3 text-sm bg-red-50 text-red-800 border border-red-200';
              msg.textContent = 'Falha de rede ao salvar.';
          });
    });

    showStep(1);
})();
</script>
