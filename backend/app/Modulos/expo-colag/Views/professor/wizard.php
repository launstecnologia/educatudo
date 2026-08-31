<?php
$projeto = is_array($projeto ?? null) ? $projeto : [];
$relacoes = $relacoes ?? [];
$catalogos = $catalogos ?? [];
$csrf_token = $csrf_token ?? '';
$user = $user ?? [];
$config = $catalogos['config_edicao'] ?? [];
$materias = $catalogos['materias'] ?? [];
$professores = $catalogos['professores'] ?? [];
$series = $catalogos['series'] ?? [];

$pid = (int) ($projeto['id'] ?? 0);
$status = (string) ($projeto['status'] ?? 'Rascunho');
$profAtualId = (int) ($user['id'] ?? 0);
$capaUrl = (string) ($projeto['capa_url'] ?? '');
$capaSrc = (string) ($projeto['capa_src'] ?? ExpoColagService::resolverUrlCapa($capaUrl, $pid));

$matConectadas = array_map('intval', array_column($relacoes['materias'] ?? [], 'materia_id'));
$profParceiros = array_map('intval', array_column($relacoes['professores'] ?? [], 'professor_id'));
$objetivosLista = [];
foreach ($relacoes['objetivos'] ?? [] as $o) {
    $t = trim((string) ($o['texto'] ?? ''));
    if ($t !== '') {
        $objetivosLista[] = $t;
    }
}
if ($objetivosLista === []) {
    $objetivosLista = [''];
}
$conexoesLista = preg_split('/\s*[;\n,•·]+\s*/u', (string) ($projeto['conexoes_interdisciplinares'] ?? '')) ?: [];
$conexoesLista = array_values(array_filter(array_map('trim', $conexoesLista), static fn ($v) => $v !== ''));
$tiposTrabalho = array_column($relacoes['tipos_trabalho'] ?? [], 'tipo');
$tiposOpcoes = ['Pesquisa', 'Experimentação', 'Protótipo', 'Maquete', 'Campanha', 'Documentário', 'Outro'];
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
$papeis = $relacoes['papeis'] ?? [];
$materiais = $relacoes['materiais'] ?? [];
$listaAlmox = ExpoColagService::decodificarMateriaisNecessarios($projeto['materiais_necessarios'] ?? []);
if ($listaAlmox === []) {
    $listaAlmox = [['nome' => '', 'quantidade' => '', 'observacao' => '']];
}

$inscIni = !empty($projeto['inscricoes_inicio']) ? substr($projeto['inscricoes_inicio'], 0, 10) : ($config['inscricoes_inicio'] ?? '');
$inscFim = !empty($projeto['inscricoes_fim']) ? substr($projeto['inscricoes_fim'], 0, 10) : ($config['inscricoes_fim'] ?? '');

$steps = [
    ['n' => 1, 'label' => 'Identificação', 'sub' => 'Capa e matérias'],
    ['n' => 2, 'label' => 'Proposta', 'sub' => 'Pedagógica'],
    ['n' => 3, 'label' => 'Formato', 'sub' => 'Participação'],
    ['n' => 4, 'label' => 'Visibilidade', 'sub' => 'Quem vê'],
    ['n' => 5, 'label' => 'Cronograma', 'sub' => 'Etapas'],
    ['n' => 6, 'label' => 'Recursos', 'sub' => 'Materiais'],
];
?>
<style>
#expoWizard .step-nav-btn {
    display: flex;
    align-items: center;
    gap: .5rem;
    border-radius: .75rem;
    border: 2px solid #e5e7eb;
    background: #ffffff;
    color: #334155;
    padding: .5rem .75rem;
    text-align: left;
    transition: background .15s, color .15s, border-color .15s;
    box-shadow: none;
}
#expoWizard .step-nav-btn:hover {
    border-color: #94a3b8;
    background: #f8fafc;
}
#expoWizard .step-nav-btn .step-num {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    flex-shrink: 0;
    border-radius: 9999px;
    border: 2px solid #94a3b8;
    background: #f1f5f9;
    color: #334155;
    font-size: .875rem;
    font-weight: 700;
}
/* Cores explícitas: variáveis da escola às vezes ficam claras e “somem” no fundo branco */
#expoWizard .step-nav-btn.is-active {
    background: #1e3a8a !important;
    background-color: #1e3a8a !important;
    border-color: #1e3a8a !important;
    color: #ffffff !important;
}
#expoWizard .step-nav-btn.is-active .step-num {
    background: rgba(255, 255, 255, 0.2) !important;
    border-color: #ffffff !important;
    color: #ffffff !important;
}
#expoWizard .step-nav-btn.is-active span {
    color: #ffffff !important;
}
#expoWizard .wizard-actions {
    position: static !important;
    bottom: auto !important;
    top: auto !important;
    left: auto !important;
    right: auto !important;
    inset: auto !important;
    z-index: auto !important;
    margin-top: 2rem;
    padding: 1.5rem 7.5rem 6rem 0;
    border-top: 1px solid #e2e8f0;
    background: transparent !important;
    backdrop-filter: none !important;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: .75rem;
}
#expoWizard .wizard-actions .autosave-status {
    margin-right: auto;
    font-size: .75rem;
    color: #64748b;
    min-height: 1.25rem;
}
#expoWizard .wizard-actions .btn-nav {
    display: inline-flex;
    align-items: center;
    padding: .625rem 1.15rem;
    border-radius: .5rem;
    border: 1px solid #64748b;
    background: #ffffff !important;
    color: #0f172a !important;
    font-size: .875rem;
    font-weight: 600;
    text-decoration: none;
}
#expoWizard .wizard-actions .btn-nav:hover:not(:disabled) { background: #f1f5f9 !important; }
#expoWizard .wizard-actions .btn-nav:disabled { opacity: .4; cursor: not-allowed; }
#expoWizard .expo-chip {
    display: inline-flex;
    cursor: pointer;
    margin: 0;
}
#expoWizard .expo-chip.hidden,
#expoWizard .expo-chip.is-oculto {
    display: none !important;
}
#expoWizard .expo-chip span {
    display: inline-block;
    padding: .4rem .75rem;
    border-radius: .5rem;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #334155;
    font-size: .8125rem;
    line-height: 1.25;
    transition: background .15s, color .15s, border-color .15s;
}
#expoWizard .expo-chip .chip-prof {
    display: none;
    font-size: .65rem;
    font-weight: 500;
    opacity: .9;
    margin-top: .15rem;
    line-height: 1.2;
}
#expoWizard .expo-chip input:checked + span .chip-prof {
    display: block;
}
#expoWizard .expo-chip.is-sugerido span {
    border-color: #1e3a8a;
    background: #eef2ff;
    color: #1e3a8a;
}
#expoWizard .expo-chip input:checked + span {
    background: #1e3a8a;
    border-color: #1e3a8a;
    color: #ffffff;
}
#expoWizard .expo-chip input:focus-visible + span {
    outline: 2px solid #1e3a8a;
    outline-offset: 2px;
}
#expoWizard .capa-box {
    position: relative;
    border: 2px dashed #cbd5e1;
    border-radius: .75rem;
    overflow: hidden;
    background: #f8fafc;
    min-height: 10rem;
}
#expoWizard .proposta-card {
    border: 1px solid #e2e8f0;
    border-radius: .9rem;
    background: #f8fafc;
    padding: 1rem 1.1rem 1.15rem;
}
#expoWizard .proposta-card h3 {
    font-size: .95rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
#expoWizard .proposta-card .hint {
    font-size: .75rem;
    color: #64748b;
    margin: .2rem 0 .75rem;
}
#expoWizard .proposta-area {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: .65rem;
    background: #ffffff;
    padding: .85rem 1rem;
    font-size: 1rem;
    line-height: 1.65;
    color: #1e293b;
}
#expoWizard .proposta-area:focus {
    outline: 2px solid #1e3a8a;
    outline-offset: 1px;
    border-color: #1e3a8a;
}
#expoWizard .objetivo-row {
    display: flex;
    align-items: center;
    gap: .5rem;
}
#expoWizard .objetivo-num {
    flex-shrink: 0;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 9999px;
    background: #1e3a8a;
    color: #fff;
    font-size: .75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}
#expoWizard .conexao-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .35rem .7rem;
    border-radius: 9999px;
    background: #eef2ff;
    color: #1e3a8a;
    font-size: .8125rem;
    font-weight: 600;
}
#expoWizard .btn-subir-capa {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .5rem .9rem;
    border-radius: .5rem;
    background: #1e3a8a;
    color: #ffffff;
    font-size: .8125rem;
    font-weight: 600;
    border: 0;
    white-space: nowrap;
}
#expoWizard .btn-subir-capa:hover:not(:disabled) { background: #1e40af; }
#expoWizard .btn-subir-capa:disabled { opacity: .45; cursor: not-allowed; }
#expoWizard .etapa-row .etapa-cab {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .5rem;
}
#expoWizard .etapa-row .etapa-titulo { flex: 1; min-width: 0; }
#expoWizard .etapa-row .etapa-data { width: 10.5rem; flex-shrink: 0; }
#expoWizard .etapa-row label.mini {
    display: block;
    font-size: .75rem;
    font-weight: 600;
    color: #475569;
    margin: 0 0 .35rem;
}
</style>
<div class="mb-6 space-y-6" id="expoWizard"
     data-projeto-id="<?= $pid ?>"
     data-url-base="<?= htmlspecialchars(URL) ?>"
     data-alunos-url="<?= htmlspecialchars(URL . '/professor/expo-colag/alunos-turma') ?>">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="<?= URL ?>/professor/expo-colag" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50" aria-label="Voltar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-900"><?= $pid ? 'Editar projeto' : 'Criar projeto' ?></h2>
                <p class="text-sm text-gray-600">Expo Colag · alterações salvas automaticamente</p>
            </div>
        </div>
        <span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-slate-100 text-slate-700">
            <?= htmlspecialchars(str_replace('_', ' ', $status)) ?>
        </span>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-center gap-2 sm:gap-0" role="tablist">
        <?php foreach ($steps as $i => $s): ?>
            <button type="button"
                    data-step-target="<?= (int) $s['n'] ?>"
                    class="step-nav-btn sm:flex-1<?= (int) $s['n'] === 1 ? ' is-active' : '' ?>"
                    aria-current="<?= (int) $s['n'] === 1 ? 'step' : 'false' ?>">
                <span class="step-num"><?= (int) $s['n'] ?></span>
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
        <input type="hidden" name="materiais" id="campoMateriais" value="">
        <input type="hidden" name="materiais_necessarios" id="campoMateriaisNecessarios" value="">
        <input type="hidden" name="objetivos" id="campoObjetivos" value="">
        <input type="hidden" name="conexoes_interdisciplinares" id="campoConexoes" value="">
        <input type="hidden" name="habilidades" id="campoHabilidades" value="[]">

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
                    <div class="capa-box mb-2">
                        <img id="capaPreview" src="<?= $capaSrc !== '' ? htmlspecialchars($capaSrc) : '' ?>" alt="Prévia da capa"
                             class="w-full h-40 object-cover <?= $capaSrc !== '' ? '' : 'hidden' ?>">
                        <div id="capaPlaceholder" class="<?= $capaSrc !== '' ? 'hidden' : '' ?> flex flex-col items-center justify-center h-40 px-4 text-center text-sm text-gray-500">
                            <span>Nenhuma capa ainda</span>
                            <span class="text-xs mt-1">Escolha o arquivo e clique em Subir capa</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="file" name="capa" id="campoCapa" accept="image/jpeg,image/png,image/webp,image/jpg"
                               class="min-w-0 flex-1 text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <button type="button" id="btnSubirCapa" class="btn-subir-capa" disabled>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0 0l-4-4m4 4l4-4"/>
                            </svg>
                            <span class="btn-subir-label">Subir capa</span>
                        </button>
                    </div>
                    <p id="capaStatus" class="hidden text-sm mt-2 rounded-lg px-3 py-2" role="status"></p>
                    <p class="text-xs text-gray-500 mt-1">Até 10 MB. JPG, PNG ou WebP. Fotos grandes são compactadas automaticamente.</p>
                    <input type="hidden" name="capa_url" id="campoCapaUrl" value="<?= htmlspecialchars($capaUrl) ?>">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Matérias conectadas</label>
                <input type="search" id="filtroMaterias" placeholder="Buscar matéria…" autocomplete="off"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm mb-2">
                <div id="listaMateriasChips" class="flex flex-wrap gap-2 max-h-48 overflow-y-auto p-0.5">
                    <?php foreach ($materias as $m): ?>
                        <?php
                        $profsMat = is_array($m['professores'] ?? null) ? $m['professores'] : [];
                        $profIdsMat = [];
                        $profNomesMat = [];
                        foreach ($profsMat as $pm) {
                            $pidMat = (int) ($pm['id'] ?? 0);
                            $nomeMat = trim((string) ($pm['nome'] ?? ''));
                            if ($pidMat > 0) {
                                $profIdsMat[] = $pidMat;
                            }
                            if ($nomeMat !== '') {
                                $profNomesMat[] = $nomeMat;
                            }
                        }
                        $profLabel = $profNomesMat !== [] ? implode(', ', $profNomesMat) : '';
                        $nomeBusca = mb_strtolower((string) ($m['nome'] ?? ''), 'UTF-8');
                        if ($profLabel !== '') {
                            $nomeBusca .= ' ' . mb_strtolower($profLabel, 'UTF-8');
                        }
                        ?>
                        <label class="expo-chip"
                               data-nome="<?= htmlspecialchars($nomeBusca) ?>"
                               data-materia-nome="<?= htmlspecialchars((string) ($m['nome'] ?? '')) ?>"
                               data-professor-ids="<?= htmlspecialchars(implode(',', $profIdsMat)) ?>"
                               data-professor-nomes="<?= htmlspecialchars($profLabel) ?>">
                            <input type="checkbox" name="materias_conectadas[]" value="<?= (int) $m['id'] ?>" class="sr-only"
                                   <?= in_array((int) $m['id'], $matConectadas, true) ? 'checked' : '' ?>>
                            <span>
                                <?= htmlspecialchars($m['nome']) ?>
                                <?php if ($profLabel !== ''): ?>
                                    <small class="chip-prof"><?= htmlspecialchars($profLabel) ?></small>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p id="filtroMateriasVazio" class="hidden text-sm text-gray-500 mt-2">Nenhuma matéria encontrada com esse nome.</p>
                <div id="resumoMaterias" class="hidden mt-3 rounded-lg border border-indigo-100 bg-indigo-50/60 px-3 py-2 text-sm text-slate-700 space-y-1"></div>
                <p class="text-xs text-gray-500 mt-1">Digite para filtrar. Ao selecionar, o professor da matéria aparece abaixo e é marcado como parceiro (você pode desmarcar).</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Professores parceiros</label>
                <input type="search" id="filtroProfessores" placeholder="Buscar professor…" autocomplete="off"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm mb-2">
                <div id="listaProfsChips" class="flex flex-wrap gap-2 max-h-48 overflow-y-auto p-0.5">
                    <?php foreach ($professores as $p): ?>
                        <?php if ((int) $p['id'] === $profAtualId) continue; ?>
                        <label class="expo-chip" data-nome="<?= htmlspecialchars(mb_strtolower((string) $p['nome'])) ?>">
                            <input type="checkbox" name="professores_parceiros[]" value="<?= (int) $p['id'] ?>" class="sr-only"
                                   <?= in_array((int) $p['id'], $profParceiros, true) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($p['nome']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p id="filtroProfsVazio" class="hidden text-sm text-gray-500 mt-2">Nenhum professor encontrado com esse nome.</p>
                <p class="text-xs text-gray-500 mt-1">Os professores das matérias selecionadas são marcados automaticamente. Toque para desmarcar se não forem parceiros.</p>
            </div>
        </section>

        <!-- Bloco 2 -->
        <section data-step="2" class="wizard-step hidden rounded-xl border border-gray-200 bg-white p-6 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">2. Proposta pedagógica</h2>
                <p class="text-sm text-gray-500 mt-0.5">O que é o projeto, o que vai para a Expo e os objetivos.</p>
            </div>

            <div class="proposta-card">
                <h3>Sobre o projeto *</h3>
                <p class="hint">Conte a ideia em linguagem simples. Evite jargão — o aluno lê isso no mural.</p>
                <textarea name="descricao" rows="5" class="proposta-area" placeholder="Ex.: Vamos investigar a qualidade da água do córrego e mostrar o que isso revela sobre o aquífero…"><?= htmlspecialchars($projeto['descricao'] ?? '') ?></textarea>
            </div>

            <div class="proposta-card">
                <h3>O que apresentaremos</h3>
                <p class="hint">Marque o tipo de trabalho e descreva os detalhes (stand, experimento, vídeo…).</p>
                <div class="flex flex-wrap gap-2 mb-3">
                    <?php foreach ($tiposOpcoes as $tipo): ?>
                        <label class="expo-chip">
                            <input type="checkbox" name="tipos_trabalho[]" value="<?= htmlspecialchars($tipo) ?>" class="sr-only"
                                   <?= in_array($tipo, $tiposTrabalho, true) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($tipo) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <textarea name="produto_esperado" rows="3" class="proposta-area" placeholder="Ex.: Stand com aquário de bioindicadores, mapa do aquífero e um infográfico com os resultados…"><?= htmlspecialchars($projeto['produto_esperado'] ?? '') ?></textarea>
            </div>

            <div class="proposta-card">
                <div class="flex items-center justify-between gap-2 mb-1">
                    <h3>Objetivos</h3>
                    <button type="button" id="btnAddObjetivo" class="text-sm font-semibold text-indigo-800 hover:underline">+ Objetivo</button>
                </div>
                <p class="hint">Um objetivo por linha. Use frases curtas, no infinitivo (investigar, construir, comunicar…).</p>
                <div id="listaObjetivos" class="space-y-2">
                    <?php foreach ($objetivosLista as $i => $objTexto): ?>
                        <div class="objetivo-row">
                            <span class="objetivo-num"><?= (int) $i + 1 ?></span>
                            <input type="text" class="objetivo-texto flex-1 border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" value="<?= htmlspecialchars($objTexto) ?>" placeholder="Ex.: Identificar bioindicadores no córrego">
                            <button type="button" class="objetivo-remove text-gray-400 hover:text-red-600 px-1" aria-label="Remover objetivo">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="proposta-card">
                <h3>Conexões interdisciplinares</h3>
                <p class="hint">Digite e pressione Enter para adicionar (ex.: Química, Geografia, Matemática).</p>
                <div class="flex gap-2 mb-2">
                    <input type="text" id="conexaoInput" maxlength="80" placeholder="Adicionar conexão…" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm">
                    <button type="button" id="btnAddConexao" class="px-3 py-2 rounded-lg bg-white border border-gray-300 text-sm font-medium hover:bg-gray-50">Adicionar</button>
                </div>
                <div id="listaConexoes" class="flex flex-wrap gap-2 min-h-[1.75rem]">
                    <?php foreach ($conexoesLista as $cx): ?>
                        <span class="conexao-chip">
                            <?= htmlspecialchars($cx) ?>
                            <button type="button" class="conexao-remove font-bold leading-none" aria-label="Remover">&times;</button>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Modo de ingresso</label>
                    <select name="modo_ingresso" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <?php foreach (['Livre' => 'Livre', 'Com_aprovacao' => 'Com aprovação', 'Convite_direto' => 'Convite direto'] as $val => $lab): ?>
                            <option value="<?= $val ?>" <?= ($projeto['modo_ingresso'] ?? 'Livre') === $val ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="lista_espera_ativa" value="1" <?= ($projeto['lista_espera_ativa'] ?? 1) ? 'checked' : '' ?>> Lista de espera</label>
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
                    <?php
                    $sid = (int) ($serie['referencia_id'] ?? $serie['id'] ?? 0);
                    $sids = array_map('intval', $serie['referencia_ids'] ?? ($sid > 0 ? [$sid] : []));
                    $serieMarcada = $sid > 0 && in_array($sid, $visSeries, true);
                    foreach ($sids as $one) {
                        if ($one > 0 && in_array($one, $visSeries, true)) {
                            $serieMarcada = true;
                            break;
                        }
                    }
                    ?>
                    <div class="serie-block">
                        <label class="inline-flex items-center gap-2 font-medium text-sm text-gray-800">
                            <input type="checkbox" class="vis-serie"
                                   data-serie-id="<?= $sid ?>"
                                   data-serie-ids="<?= htmlspecialchars(implode(',', $sids)) ?>"
                                   <?= $serieMarcada ? 'checked' : '' ?>>
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

            <div class="proposta-card">
                <div class="flex items-center justify-between gap-2 mb-1">
                    <h3>Etapas</h3>
                    <button type="button" id="btnAddEtapa" class="btn-subir-capa">+ Etapa</button>
                </div>
                <p class="hint">Uma etapa por bloco. Descreva o que o grupo faz e o que deve entregar até a data.</p>
                <div id="listaEtapas" class="space-y-3">
                    <?php
                    $etapasRender = $etapas !== [] ? $etapas : [['titulo' => '', 'data_limite' => '', 'descricao' => '', 'entregavel_esperado' => '']];
                    foreach ($etapasRender as $i => $et):
                    ?>
                    <div class="etapa-row rounded-xl border border-slate-200 bg-white p-3 space-y-3">
                        <div class="etapa-cab">
                            <span class="objetivo-num etapa-num"><?= (int) $i + 1 ?></span>
                            <input type="text" class="etapa-titulo border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Título da etapa" value="<?= htmlspecialchars($et['titulo'] ?? '') ?>">
                            <input type="date" class="etapa-data border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" value="<?= htmlspecialchars($et['data_limite'] ?? '') ?>">
                            <button type="button" class="etapa-remove text-gray-400 hover:text-red-600 px-1 text-lg leading-none" aria-label="Remover etapa">&times;</button>
                        </div>
                        <div>
                            <label class="mini">Descrição</label>
                            <textarea class="etapa-desc proposta-area" rows="3" placeholder="O que acontece nesta etapa? Quem faz o quê?"><?= htmlspecialchars($et['descricao'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="mini">Entregável esperado</label>
                            <textarea class="etapa-entregavel proposta-area" rows="2" placeholder="O que o grupo deve entregar ao final desta etapa?"><?= htmlspecialchars($et['entregavel_esperado'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="proposta-card">
                <h3>Anotações do grupo</h3>
                <p class="hint">Cronograma de entrega das etapas e decisões tomadas nas reuniões.</p>
                <textarea name="briefing_entrega" rows="5" class="proposta-area" placeholder="Ex.: 12/09 — definição do tema; 20/09 — rascunho do infográfico. Reunião 15/09: o stand terá aquário e mapa do aquífero."><?= htmlspecialchars($projeto['briefing_entrega'] ?? '') ?></textarea>
            </div>

            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="vale_nota" value="1" <?= !empty($projeto['vale_nota']) ? 'checked' : '' ?>> Vale nota
            </label>
        </section>

        <!-- Bloco 6 -->
        <section data-step="6" class="wizard-step hidden rounded-xl border border-gray-200 bg-white p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">6. Recursos</h2>

            <div class="proposta-card">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                    <h3>Materiais do almoxarifado</h3>
                    <div class="flex items-center gap-2">
                        <button type="button" id="btnAddAlmox" class="text-sm font-semibold text-indigo-800 hover:underline">+ Item</button>
                        <button type="button" id="btnPdfMateriais" class="btn-subir-capa">Exportar PDF</button>
                    </div>
                </div>
                <p class="hint">Cola, cartolina, placa de isopor… A coordenação autoriza no PDF e o professor retira no almoxarifado.</p>
                <div id="listaAlmox" class="space-y-2">
                    <?php foreach ($listaAlmox as $item): ?>
                    <div class="almox-row grid grid-cols-1 sm:grid-cols-12 gap-2">
                        <input type="text" class="almox-nome sm:col-span-6 border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Material (ex.: cola branca)" value="<?= htmlspecialchars($item['nome'] ?? '') ?>">
                        <input type="text" class="almox-qtd sm:col-span-2 border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Qtd." value="<?= htmlspecialchars($item['quantidade'] ?? '') ?>">
                        <input type="text" class="almox-obs sm:col-span-3 border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Observação" value="<?= htmlspecialchars($item['observacao'] ?? '') ?>">
                        <button type="button" class="almox-remove sm:col-span-1 text-gray-400 hover:text-red-600 px-1 text-lg leading-none" aria-label="Remover item">&times;</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="proposta-card">
                <h3>Pesquisa e ideias</h3>
                <p class="hint">Libere as ferramentas para o grupo pesquisar e desenvolver ideias neste projeto.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 cursor-pointer">
                        <input type="checkbox" name="educalabs_ativa" value="1" class="mt-1" <?= !empty($projeto['educalabs_ativa']) ? 'checked' : '' ?>>
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">EducaLabs</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Pesquisa, experimentos e protótipos digitais.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 cursor-pointer">
                        <input type="checkbox" name="tudinha_ativa" value="1" class="mt-1" <?= !empty($projeto['tudinha_ativa']) ? 'checked' : '' ?>>
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">Tudinha</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Chat para tirar dúvidas e gerar ideias.</span>
                        </span>
                    </label>
                </div>
                <p class="hint mt-3 mb-0">
                    <a href="<?= htmlspecialchars(URL) ?>/professor/ai-agents" target="_blank" rel="noopener" class="font-semibold text-indigo-800 hover:underline">Abrir EducaProf</a>
                    para idear o projeto enquanto você monta esta ficha.
                </p>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-gray-700">Links e referências</label>
                    <button type="button" id="btnAddMaterial" class="text-sm font-semibold text-indigo-800 hover:underline">+ Link</button>
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
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="permite_solicitacao_recursos" value="1" <?= ($projeto['permite_solicitacao_recursos'] ?? 1) ? 'checked' : '' ?>> Permitir solicitação de recursos</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="destaque" value="1" <?= !empty($projeto['destaque']) ? 'checked' : '' ?>> Destacar no mural</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="ativo" value="1" <?= ($projeto['ativo'] ?? 1) ? 'checked' : '' ?>> Ativo</label>
            </div>
        </section>

        <div id="wizardMsg" class="hidden rounded-lg px-4 py-3 text-sm"></div>
    </form>

    <div class="wizard-actions" id="expoWizardActions">
        <p id="autosaveStatus" class="autosave-status" aria-live="polite"></p>
        <button type="button" id="btnPrev" class="btn-nav" disabled>Anterior</button>
        <button type="button" id="btnNext" class="btn-nav">Próximo</button>
        <a id="btnPreview" href="<?= $pid > 0 ? htmlspecialchars(URL . '/professor/expo-colag/projetos/' . $pid . '/preview') : '#' ?>"
           class="btn-nav hidden">Pré-visualizar</a>
        <button type="submit" form="expoWizardForm" id="btnPublicar" data-acao="publicar"
                class="hidden px-5 py-2.5 rounded-lg text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm">Publicar</button>
    </div>
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
    var capaPendente = null;
    var autosaveTimer = null;
    var salvando = false;
    var savePendente = false;

    function showStep(n) {
        step = Math.max(1, Math.min(maxStep, n));
        root.querySelectorAll('.wizard-step').forEach(function (el) {
            el.classList.toggle('hidden', parseInt(el.getAttribute('data-step'), 10) !== step);
        });
        root.querySelectorAll('.step-nav-btn').forEach(function (btn) {
            var ativo = parseInt(btn.getAttribute('data-step-target'), 10) === step;
            btn.classList.toggle('is-active', ativo);
            btn.setAttribute('aria-current', ativo ? 'step' : 'false');
            // Inline como fallback se CSS da escola sobrescrever
            if (ativo) {
                btn.style.setProperty('background-color', '#1e3a8a', 'important');
                btn.style.setProperty('border-color', '#1e3a8a', 'important');
                btn.style.setProperty('color', '#ffffff', 'important');
            } else {
                btn.style.removeProperty('background-color');
                btn.style.removeProperty('border-color');
                btn.style.removeProperty('color');
            }
        });
        var ultima = step === maxStep;
        document.getElementById('btnPrev').disabled = step === 1;
        document.getElementById('btnNext').classList.toggle('hidden', ultima);
        document.getElementById('btnPublicar').classList.toggle('hidden', !ultima);
        var prev = document.getElementById('btnPreview');
        var pidAtual = parseInt((form.querySelector('[name="projeto_id"]') || {}).value || '0', 10) || 0;
        prev.classList.toggle('hidden', !ultima || pidAtual <= 0);
    }

    function irParaEtapa(n) {
        flushAutosave();
        showStep(n);
    }

    root.querySelectorAll('.step-nav-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            irParaEtapa(parseInt(btn.getAttribute('data-step-target'), 10));
        });
    });
    document.getElementById('btnPrev').addEventListener('click', function () { irParaEtapa(step - 1); });
    document.getElementById('btnNext').addEventListener('click', function () { irParaEtapa(step + 1); });

    function normalizarBusca(texto) {
        return (texto || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }

    function filtrarChips(inputId, listId, vazioId) {
        var input = document.getElementById(inputId);
        var list = document.getElementById(listId);
        var vazio = vazioId ? document.getElementById(vazioId) : null;
        if (!input || !list) return;
        function aplicar() {
            var q = normalizarBusca(input.value);
            var visiveis = 0;
            list.querySelectorAll('.expo-chip').forEach(function (lab) {
                var nome = normalizarBusca(lab.getAttribute('data-nome') || '');
                var marcado = !!(lab.querySelector('input') && lab.querySelector('input').checked);
                var some = q !== '' && nome.indexOf(q) === -1 && !marcado;
                lab.classList.toggle('is-oculto', some);
                lab.classList.toggle('hidden', some);
                if (!some) visiveis++;
            });
            if (vazio) vazio.classList.toggle('hidden', visiveis > 0 || q === '');
        }
        input.addEventListener('input', aplicar);
        input.addEventListener('keyup', aplicar);
        input.addEventListener('search', aplicar);
    }
    filtrarChips('filtroMaterias', 'listaMateriasChips', 'filtroMateriasVazio');
    filtrarChips('filtroProfessores', 'listaProfsChips', 'filtroProfsVazio');

    function idsProfessoresDasMateriasSelecionadas() {
        var ids = {};
        document.querySelectorAll('#listaMateriasChips .expo-chip input:checked').forEach(function (cb) {
            var lab = cb.closest('.expo-chip');
            if (!lab) return;
            (lab.getAttribute('data-professor-ids') || '').split(',').forEach(function (raw) {
                var id = parseInt(raw, 10);
                if (id) ids[id] = true;
            });
        });
        return ids;
    }

    function aindaVinculadoPorMateria(professorId) {
        var ids = idsProfessoresDasMateriasSelecionadas();
        return !!ids[professorId];
    }

    function atualizarResumoMaterias() {
        var box = document.getElementById('resumoMaterias');
        if (!box) return;
        var linhas = [];
        document.querySelectorAll('#listaMateriasChips .expo-chip').forEach(function (lab) {
            var cb = lab.querySelector('input');
            if (!cb || !cb.checked) return;
            var materia = lab.getAttribute('data-materia-nome') || (lab.querySelector('span') || {}).textContent || '';
            var profs = (lab.getAttribute('data-professor-nomes') || '').trim();
            linhas.push({ materia: materia.trim(), profs: profs });
        });
        if (linhas.length === 0) {
            box.classList.add('hidden');
            box.textContent = '';
            return;
        }
        box.classList.remove('hidden');
        box.textContent = '';
        linhas.forEach(function (item) {
            var div = document.createElement('div');
            var strong = document.createElement('span');
            strong.className = 'font-semibold text-slate-800';
            strong.textContent = item.materia;
            var rest = document.createElement('span');
            rest.className = 'text-slate-600';
            rest.textContent = item.profs ? ' — ' + item.profs : ' — professor não cadastrado nesta matéria';
            div.appendChild(strong);
            div.appendChild(rest);
            box.appendChild(div);
        });
    }

    function marcarProfessoresDoChip(chip, incluir) {
        if (!chip) return;
        (chip.getAttribute('data-professor-ids') || '').split(',').forEach(function (raw) {
            var id = parseInt(raw, 10);
            if (!id) return;
            var profCb = document.querySelector('#listaProfsChips input[value="' + id + '"]');
            if (!profCb) return;
            if (incluir) {
                if (!profCb.checked && profCb.getAttribute('data-auto-materia') !== '0') {
                    profCb.checked = true;
                    profCb.setAttribute('data-auto-materia', '1');
                }
            } else if (profCb.getAttribute('data-auto-materia') === '1' && !aindaVinculadoPorMateria(id)) {
                profCb.checked = false;
                profCb.removeAttribute('data-auto-materia');
            }
        });
    }

    function destacarProfessoresParceiros() {
        var ids = idsProfessoresDasMateriasSelecionadas();
        var list = document.getElementById('listaProfsChips');
        if (!list) return;
        var chips = Array.prototype.slice.call(list.querySelectorAll('.expo-chip'));
        chips.forEach(function (lab) {
            var cb = lab.querySelector('input');
            var pid = cb ? parseInt(cb.value, 10) : 0;
            lab.classList.toggle('is-sugerido', !!ids[pid] && !(cb && cb.checked));
        });
        chips.sort(function (a, b) {
            var aSug = a.classList.contains('is-sugerido') || !!(a.querySelector('input') && a.querySelector('input').checked) ? 0 : 1;
            var bSug = b.classList.contains('is-sugerido') || !!(b.querySelector('input') && b.querySelector('input').checked) ? 0 : 1;
            return aSug - bSug;
        });
        chips.forEach(function (lab) { list.appendChild(lab); });
    }

    function aoMudarMateria(ev) {
        var cb = ev && ev.target;
        if (cb && cb.name === 'materias_conectadas[]') {
            marcarProfessoresDoChip(cb.closest('.expo-chip'), !!cb.checked);
        }
        atualizarResumoMaterias();
        destacarProfessoresParceiros();
        agendarAutosave();
    }

    var listaMaterias = document.getElementById('listaMateriasChips');
    if (listaMaterias) {
        listaMaterias.addEventListener('change', function (ev) {
            if (ev.target && ev.target.name === 'materias_conectadas[]') {
                aoMudarMateria(ev);
            }
        });
    }
    var listaProfs = document.getElementById('listaProfsChips');
    if (listaProfs) {
        listaProfs.addEventListener('change', function (ev) {
            var cb = ev.target;
            if (!cb || cb.name !== 'professores_parceiros[]') return;
            if (cb.getAttribute('data-auto-materia') === '1' && !cb.checked) {
                cb.setAttribute('data-auto-materia', '0');
            }
            cb.closest('.expo-chip') && cb.closest('.expo-chip').classList.toggle(
                'is-sugerido',
                !cb.checked && aindaVinculadoPorMateria(parseInt(cb.value, 10))
            );
        });
    }
    atualizarResumoMaterias();
    destacarProfessoresParceiros();

    function mostrarCapaPreview(src) {
        var img = document.getElementById('capaPreview');
        var ph = document.getElementById('capaPlaceholder');
        if (!img) return;
        if (src) {
            img.src = src;
            img.classList.remove('hidden');
            if (ph) ph.classList.add('hidden');
        } else {
            img.removeAttribute('src');
            img.classList.add('hidden');
            if (ph) ph.classList.remove('hidden');
        }
    }

    function compactarCapa(file) {
        var maxBytes = 10 * 1024 * 1024;
        var okTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        if (okTypes.indexOf(file.type) === -1) {
            return Promise.reject(new Error('Use JPG, PNG ou WebP.'));
        }
        if (file.size <= 1.5 * 1024 * 1024) {
            return Promise.resolve(file);
        }
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                var w = img.naturalWidth || img.width;
                var h = img.naturalHeight || img.height;
                var maxDim = 1920;
                var scale = Math.min(maxDim / w, maxDim / h, 1);
                w = Math.max(1, Math.round(w * scale));
                h = Math.max(1, Math.round(h * scale));
                var canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, w, h);
                URL.revokeObjectURL(url);
                var quality = 0.82;
                function blobToFile(blob) {
                    if (!blob) {
                        reject(new Error('Não foi possível compactar a capa.'));
                        return;
                    }
                    if (blob.size > maxBytes && quality > 0.55) {
                        quality -= 0.12;
                        canvas.toBlob(blobToFile, 'image/jpeg', quality);
                        return;
                    }
                    if (blob.size > maxBytes) {
                        reject(new Error('A capa ainda está acima de 10 MB após compactar.'));
                        return;
                    }
                    resolve(new File([blob], (file.name || 'capa').replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
                }
                canvas.toBlob(blobToFile, 'image/jpeg', quality);
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('Não foi possível ler a imagem da capa.'));
            };
            img.src = url;
        });
    }

    var campoCapa = document.getElementById('campoCapa');
    var btnSubirCapa = document.getElementById('btnSubirCapa');
    var capaObjectUrl = null;

    function setBotaoCapa(habilitado, texto) {
        if (!btnSubirCapa) return;
        btnSubirCapa.disabled = !habilitado;
        var label = btnSubirCapa.querySelector('span.btn-subir-label');
        if (!label) {
            var nodes = btnSubirCapa.childNodes;
            for (var i = 0; i < nodes.length; i++) {
                if (nodes[i].nodeType === 3 && nodes[i].textContent.trim()) {
                    nodes[i].textContent = ' ' + (texto || 'Subir capa');
                    return;
                }
            }
            return;
        }
        label.textContent = texto || 'Subir capa';
    }

    function mostrarCapaStatus(tipo, texto) {
        var el = document.getElementById('capaStatus');
        if (!el) return;
        el.classList.remove('hidden');
        var cls = 'text-sm mt-2 rounded-lg px-3 py-2 border ';
        if (tipo === 'ok') cls += 'bg-emerald-50 text-emerald-800 border-emerald-200';
        else if (tipo === 'info') cls += 'bg-sky-50 text-sky-800 border-sky-200';
        else cls += 'bg-red-50 text-red-800 border-red-200';
        el.className = cls;
        el.textContent = (tipo === 'ok' ? '✓ ' : '') + (texto || '');
    }

    if (campoCapa) {
        campoCapa.addEventListener('change', function () {
            var file = campoCapa.files && campoCapa.files[0];
            if (!file) {
                capaPendente = null;
                setBotaoCapa(false, 'Subir capa');
                return;
            }
            mostrarCapaStatus('info', 'Preparando imagem…');
            compactarCapa(file).then(function (compactado) {
                capaPendente = compactado;
                if (capaObjectUrl) URL.revokeObjectURL(capaObjectUrl);
                capaObjectUrl = URL.createObjectURL(compactado);
                mostrarCapaPreview(capaObjectUrl);
                setBotaoCapa(true, 'Subir capa');
                mostrarCapaStatus('info', 'Arquivo pronto. Clique em Subir capa.');
            }).catch(function (err) {
                capaPendente = null;
                campoCapa.value = '';
                setBotaoCapa(false, 'Subir capa');
                mostrarCapaStatus('erro', err.message || 'Falha ao processar a capa.');
            });
        });
    }
    if (btnSubirCapa) {
        btnSubirCapa.addEventListener('click', function () {
            if (!capaPendente) {
                mostrarCapaStatus('erro', 'Escolha um arquivo primeiro.');
                return;
            }
            if (!tituloPreenchido()) {
                mostrarCapaStatus('erro', 'Preencha o título antes de enviar a capa.');
                return;
            }
            setBotaoCapa(false, 'Enviando…');
            mostrarCapaStatus('info', 'Enviando capa…');
            salvar({ silencioso: true, capa: true });
        });
    }

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
        if (!wrap) return;
        var div = document.createElement('div');
        div.className = 'etapa-row rounded-xl border border-slate-200 bg-white p-3 space-y-3';
        div.innerHTML = '<div class="etapa-cab">' +
            '<span class="objetivo-num etapa-num"></span>' +
            '<input type="text" class="etapa-titulo border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Título da etapa">' +
            '<input type="date" class="etapa-data border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm">' +
            '<button type="button" class="etapa-remove text-gray-400 hover:text-red-600 px-1 text-lg leading-none" aria-label="Remover etapa">&times;</button>' +
            '</div>' +
            '<div><label class="mini">Descrição</label>' +
            '<textarea class="etapa-desc proposta-area" rows="3" placeholder="O que acontece nesta etapa? Quem faz o quê?"></textarea></div>' +
            '<div><label class="mini">Entregável esperado</label>' +
            '<textarea class="etapa-entregavel proposta-area" rows="2" placeholder="O que o grupo deve entregar ao final desta etapa?"></textarea></div>';
        var titulo = div.querySelector('.etapa-titulo');
        var dataEl = div.querySelector('.etapa-data');
        var desc = div.querySelector('.etapa-desc');
        var ent = div.querySelector('.etapa-entregavel');
        if (titulo) titulo.value = data.titulo || '';
        if (dataEl) dataEl.value = data.data_limite || '';
        if (desc) desc.value = data.descricao || '';
        if (ent) ent.value = data.entregavel_esperado || '';
        wrap.appendChild(div);
        renumerarEtapas();
        if (titulo) titulo.focus();
        agendarAutosave();
    }

    function limparEtapa(row) {
        row.querySelectorAll('input, textarea').forEach(function (el) { el.value = ''; });
    }

    function renumerarEtapas() {
        root.querySelectorAll('#listaEtapas .etapa-row').forEach(function (row, idx) {
            var num = row.querySelector('.etapa-num');
            if (num) num.textContent = String(idx + 1);
        });
    }

    var btnAddEtapa = document.getElementById('btnAddEtapa');
    if (btnAddEtapa) {
        btnAddEtapa.addEventListener('click', function () { addEtapa(); });
    }
    root.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.etapa-remove');
        if (!btn || !root.contains(btn)) return;
        var wrap = document.getElementById('listaEtapas');
        var rows = wrap ? wrap.querySelectorAll('.etapa-row') : [];
        if (rows.length <= 1) {
            if (rows[0]) limparEtapa(rows[0]);
        } else {
            btn.closest('.etapa-row').remove();
            renumerarEtapas();
        }
        agendarAutosave();
    });

    document.getElementById('btnAddMaterial').addEventListener('click', function () {
        var wrap = document.getElementById('listaMateriais');
        var div = document.createElement('div');
        div.className = 'material-row grid grid-cols-1 sm:grid-cols-3 gap-2';
        div.innerHTML = '<input type="text" class="mat-titulo border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Título">' +
            '<input type="url" class="mat-link border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="URL">' +
            '<select class="mat-tipo border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm"><option value="link">Link</option><option value="arquivo">Arquivo</option></select>';
        wrap.appendChild(div);
        agendarAutosave();
    });

    function addAlmox() {
        var wrap = document.getElementById('listaAlmox');
        if (!wrap) return;
        var div = document.createElement('div');
        div.className = 'almox-row grid grid-cols-1 sm:grid-cols-12 gap-2';
        div.innerHTML = '<input type="text" class="almox-nome sm:col-span-6 border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Material (ex.: cola branca)">' +
            '<input type="text" class="almox-qtd sm:col-span-2 border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Qtd.">' +
            '<input type="text" class="almox-obs sm:col-span-3 border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Observação">' +
            '<button type="button" class="almox-remove sm:col-span-1 text-gray-400 hover:text-red-600 px-1 text-lg leading-none" aria-label="Remover item">&times;</button>';
        wrap.appendChild(div);
        var nome = div.querySelector('.almox-nome');
        if (nome) nome.focus();
        agendarAutosave();
    }
    var btnAddAlmox = document.getElementById('btnAddAlmox');
    if (btnAddAlmox) btnAddAlmox.addEventListener('click', addAlmox);
    root.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.almox-remove');
        if (!btn || !root.contains(btn)) return;
        var wrap = document.getElementById('listaAlmox');
        var rows = wrap ? wrap.querySelectorAll('.almox-row') : [];
        if (rows.length <= 1) {
            if (rows[0]) rows[0].querySelectorAll('input').forEach(function (el) { el.value = ''; });
        } else {
            btn.closest('.almox-row').remove();
        }
        agendarAutosave();
    });

    var btnPdfMateriais = document.getElementById('btnPdfMateriais');
    if (btnPdfMateriais) {
        btnPdfMateriais.addEventListener('click', function () {
            salvar({
                silencioso: true,
                depois: function () {
                    var pidNovo = parseInt((form.querySelector('[name="projeto_id"]') || {}).value || '0', 10) || 0;
                    if (!pidNovo) {
                        setAutosaveStatus('Não foi possível gerar o PDF. Confira se o título está preenchido.', true);
                        return;
                    }
                    window.open(root.getAttribute('data-url-base') + '/professor/expo-colag/projetos/' + pidNovo + '/materiais-pdf', '_blank');
                }
            });
        });
    }

    function renumerarObjetivos() {
        root.querySelectorAll('#listaObjetivos .objetivo-row').forEach(function (row, idx) {
            var num = row.querySelector('.objetivo-num');
            if (num) num.textContent = String(idx + 1);
        });
    }

    function addObjetivo(texto) {
        var wrap = document.getElementById('listaObjetivos');
        if (!wrap) return;
        var div = document.createElement('div');
        div.className = 'objetivo-row';
        div.innerHTML = '<span class="objetivo-num"></span>' +
            '<input type="text" class="objetivo-texto flex-1 border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm" placeholder="Ex.: Identificar bioindicadores no córrego">' +
            '<button type="button" class="objetivo-remove text-gray-400 hover:text-red-600 px-1" aria-label="Remover objetivo">&times;</button>';
        var input = div.querySelector('.objetivo-texto');
        if (texto) input.value = texto;
        wrap.appendChild(div);
        renumerarObjetivos();
        input.focus();
        agendarAutosave();
    }

    var btnAddObjetivo = document.getElementById('btnAddObjetivo');
    if (btnAddObjetivo) {
        btnAddObjetivo.addEventListener('click', function () { addObjetivo(''); });
    }
    root.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.objetivo-remove');
        if (!btn || !root.contains(btn)) return;
        var wrap = document.getElementById('listaObjetivos');
        var rows = wrap ? wrap.querySelectorAll('.objetivo-row') : [];
        if (rows.length <= 1) {
            var input = rows[0] && rows[0].querySelector('.objetivo-texto');
            if (input) input.value = '';
        } else {
            btn.closest('.objetivo-row').remove();
            renumerarObjetivos();
        }
        agendarAutosave();
    });

    function addConexao(texto) {
        texto = (texto || '').trim();
        if (!texto) return;
        var wrap = document.getElementById('listaConexoes');
        if (!wrap) return;
        var existe = false;
        wrap.querySelectorAll('.conexao-chip').forEach(function (chip) {
            var atual = (chip.textContent || '').replace('×', '').replace('x', '').trim();
            if (atual.toLowerCase() === texto.toLowerCase()) existe = true;
        });
        if (existe) return;
        var span = document.createElement('span');
        span.className = 'conexao-chip';
        span.appendChild(document.createTextNode(texto + ' '));
        var rm = document.createElement('button');
        rm.type = 'button';
        rm.className = 'conexao-remove font-bold leading-none';
        rm.setAttribute('aria-label', 'Remover');
        rm.textContent = '\u00d7';
        span.appendChild(rm);
        wrap.appendChild(span);
        agendarAutosave();
    }

    var conexaoInput = document.getElementById('conexaoInput');
    var btnAddConexao = document.getElementById('btnAddConexao');
    if (btnAddConexao) {
        btnAddConexao.addEventListener('click', function () {
            addConexao(conexaoInput ? conexaoInput.value : '');
            if (conexaoInput) conexaoInput.value = '';
        });
    }
    if (conexaoInput) {
        conexaoInput.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                addConexao(conexaoInput.value);
                conexaoInput.value = '';
            }
        });
    }
    root.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.conexao-remove');
        if (!btn || !root.contains(btn)) return;
        var chip = btn.closest('.conexao-chip');
        if (chip) chip.remove();
        agendarAutosave();
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
            var seen = {};
            var raw = (el.getAttribute('data-serie-ids') || el.getAttribute('data-serie-id') || '').split(',');
            raw.forEach(function (part) {
                var id = parseInt(part, 10);
                if (id && !seen[id]) {
                    seen[id] = true;
                    vis.push({ escopo: 'Serie', referencia_id: id });
                }
            });
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

        var materiais = [];
        root.querySelectorAll('.material-row').forEach(function (row) {
            var titulo = ((row.querySelector('.mat-titulo') || {}).value || '').trim();
            if (!titulo) return;
            var link = ((row.querySelector('.mat-link') || {}).value || '').trim();
            var tipo = (row.querySelector('.mat-tipo') || {}).value || 'link';
            materiais.push({ titulo: titulo, tipo: tipo, link_externo: link });
        });
        document.getElementById('campoMateriais').value = JSON.stringify(materiais);

        var almox = [];
        root.querySelectorAll('.almox-row').forEach(function (row) {
            var nome = ((row.querySelector('.almox-nome') || {}).value || '').trim();
            if (!nome) return;
            almox.push({
                nome: nome,
                quantidade: ((row.querySelector('.almox-qtd') || {}).value || '').trim(),
                observacao: ((row.querySelector('.almox-obs') || {}).value || '').trim()
            });
        });
        var campoAlmox = document.getElementById('campoMateriaisNecessarios');
        if (campoAlmox) campoAlmox.value = JSON.stringify(almox);

        var objetivos = [];
        root.querySelectorAll('.objetivo-texto').forEach(function (el) {
            var t = (el.value || '').trim();
            if (t) objetivos.push(t);
        });
        var campoObj = document.getElementById('campoObjetivos');
        if (campoObj) campoObj.value = objetivos.join('\n');

        var conexoes = [];
        root.querySelectorAll('.conexao-chip').forEach(function (chip) {
            var clone = chip.cloneNode(true);
            var rm = clone.querySelector('.conexao-remove');
            if (rm) rm.remove();
            var t = (clone.textContent || '').trim();
            if (t) conexoes.push(t);
        });
        var campoCx = document.getElementById('campoConexoes');
        if (campoCx) campoCx.value = conexoes.join('\n');

        var campoHab = document.getElementById('campoHabilidades');
        if (campoHab) campoHab.value = '[]';
    }

    function setAutosaveStatus(texto, erro) {
        var el = document.getElementById('autosaveStatus');
        if (!el) return;
        el.textContent = texto || '';
        el.style.color = erro ? '#b91c1c' : '#64748b';
    }

    function tituloPreenchido() {
        var t = form.querySelector('[name="titulo"]');
        return !!(t && (t.value || '').trim());
    }

    function montarFormData(acao) {
        collectJsonFields();
        document.getElementById('campoAcao').value = acao || 'rascunho';
        var fd = new FormData(form);
        fd.delete('capa');
        if (capaPendente) {
            fd.set('capa', capaPendente, capaPendente.name || 'capa.jpg');
        }
        return fd;
    }

    function aplicarIdSalvo(id) {
        var pidInput = form.querySelector('[name="projeto_id"]');
        var pidAtual = parseInt((pidInput && pidInput.value) || '0', 10) || 0;
        if (id && pidInput && pidAtual <= 0) {
            pidInput.value = String(id);
            root.setAttribute('data-projeto-id', String(id));
            history.replaceState(null, '', root.getAttribute('data-url-base') + '/professor/expo-colag/projetos/' + id + '/editar');
        }
        var prev = document.getElementById('btnPreview');
        if (prev && id) {
            prev.href = root.getAttribute('data-url-base') + '/professor/expo-colag/projetos/' + id + '/preview';
            if (step === maxStep) prev.classList.remove('hidden');
        }
    }

    function salvar(opts) {
        opts = opts || {};
        var silencioso = !!opts.silencioso;
        var acao = opts.acao || 'rascunho';
        var envioCapa = !!opts.capa;
        if (salvando) {
            savePendente = acao === 'rascunho' && !envioCapa;
            return Promise.resolve();
        }
        if (acao === 'rascunho' && !tituloPreenchido()) {
            setAutosaveStatus('Digite o título para salvar automaticamente.');
            if (envioCapa) {
                mostrarCapaStatus('erro', 'Preencha o título antes de enviar a capa.');
                setBotaoCapa(!!capaPendente, 'Subir capa');
            }
            return Promise.resolve();
        }
        salvando = true;
        if (silencioso) setAutosaveStatus('Salvando…');
        var msg = document.getElementById('wizardMsg');
        if (!silencioso) msg.classList.add('hidden');
        var fd = montarFormData(acao);
        return fetch(form.action, {
            method: 'POST',
            body: fd,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
              salvando = false;
              if (res.j.success) {
                  aplicarIdSalvo(res.j.id);
                  if (res.j.capa_url) {
                      var hiddenCapa = document.getElementById('campoCapaUrl');
                      if (hiddenCapa) hiddenCapa.value = res.j.capa_url;
                  }
                  if (res.j.capa_src) mostrarCapaPreview(res.j.capa_src);
                  if (capaPendente && (res.j.capa_url || res.j.capa_src)) {
                      capaPendente = null;
                      if (campoCapa) campoCapa.value = '';
                      setBotaoCapa(false, 'Subir capa');
                      mostrarCapaStatus('ok', 'Capa enviada com sucesso.');
                  } else if (envioCapa) {
                      if (res.j.capa_src || res.j.capa_url) {
                          capaPendente = null;
                          if (campoCapa) campoCapa.value = '';
                          setBotaoCapa(false, 'Subir capa');
                          mostrarCapaStatus('ok', 'Capa enviada com sucesso.');
                      } else {
                          setBotaoCapa(true, 'Subir capa');
                          mostrarCapaStatus('erro', 'O projeto foi salvo, mas a capa não chegou ao servidor. Tente de novo.');
                      }
                  }
                  var agora = new Date();
                  var hh = String(agora.getHours()).padStart(2, '0');
                  var mm = String(agora.getMinutes()).padStart(2, '0');
                  setAutosaveStatus('Salvo automaticamente às ' + hh + ':' + mm);
                  if (!silencioso) {
                      msg.classList.remove('hidden');
                      msg.className = 'rounded-lg px-4 py-3 text-sm bg-green-50 text-green-800 border border-green-200';
                      msg.textContent = res.j.message || 'Salvo.';
                  }
                  if (acao === 'publicar' && res.j.redirect) {
                      window.location.href = res.j.redirect;
                      return;
                  }
              } else {
                  setAutosaveStatus(res.j.message || 'Não foi possível salvar.', true);
                  if (envioCapa) {
                      setBotaoCapa(!!capaPendente, 'Subir capa');
                      mostrarCapaStatus('erro', res.j.message || 'Não foi possível enviar a capa.');
                  }
                  if (!silencioso) {
                      msg.classList.remove('hidden');
                      msg.className = 'rounded-lg px-4 py-3 text-sm bg-red-50 text-red-800 border border-red-200';
                      msg.textContent = res.j.message || 'Erro.';
                  }
              }
              if (savePendente && acao === 'rascunho') {
                  savePendente = false;
                  salvar({ silencioso: true });
              }
              if (typeof opts.depois === 'function' && res.j.success) opts.depois();
          }).catch(function () {
              salvando = false;
              setAutosaveStatus('Falha de rede ao salvar.', true);
              if (envioCapa) {
                  setBotaoCapa(!!capaPendente, 'Subir capa');
                  mostrarCapaStatus('erro', 'Falha de rede ao enviar a capa. Tente de novo.');
              }
              if (!silencioso) {
                  msg.classList.remove('hidden');
                  msg.className = 'rounded-lg px-4 py-3 text-sm bg-red-50 text-red-800 border border-red-200';
                  msg.textContent = 'Falha de rede ao salvar.';
              }
          });
    }

    function agendarAutosave() {
        if (autosaveTimer) clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(function () {
            salvar({ silencioso: true });
        }, 1200);
    }

    function flushAutosave() {
        if (autosaveTimer) {
            clearTimeout(autosaveTimer);
            autosaveTimer = null;
        }
        salvar({ silencioso: true });
    }

    form.addEventListener('input', function (ev) {
        var id = (ev.target && ev.target.id) || '';
        if (id === 'campoCapa' || id === 'filtroMaterias' || id === 'filtroProfessores' || id === 'conexaoInput') return;
        agendarAutosave();
    });
    form.addEventListener('change', function (ev) {
        var id = (ev.target && ev.target.id) || '';
        if (id === 'campoCapa' || id === 'filtroMaterias' || id === 'filtroProfessores') return;
        agendarAutosave();
    });

    document.getElementById('btnPublicar').addEventListener('click', function () {
        document.getElementById('campoAcao').value = 'publicar';
    });

    document.getElementById('btnPreview').addEventListener('click', function (ev) {
        ev.preventDefault();
        var href = document.getElementById('btnPreview').getAttribute('href');
        if (!href || href === '#') return;
        salvar({
            silencioso: true,
            depois: function () { window.location.href = href; }
        });
    });

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var acao = document.getElementById('campoAcao').value || 'rascunho';
        salvar({ silencioso: acao === 'rascunho', acao: acao });
    });

    showStep(1);
    if (tituloPreenchido()) setAutosaveStatus('Alterações são salvas automaticamente.');
})();
</script>
