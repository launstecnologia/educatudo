<!-- Header Section -->
<div class="mb-8">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">
                Exercícios do Módulo - <?= htmlspecialchars($modulo['titulo'] ?? 'Módulo') ?>
            </h2>
            <p class="text-gray-600">
                Jornada: <?= htmlspecialchars($jornada['titulo']) ?> • <?= htmlspecialchars($jornada['materia_nome'] ?? 'Sem matéria') ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="<?= URL ?>/professor/jornadas/modulos/<?= (int)$modulo['id'] ?>/exercicios/criar"
               class="inline-flex items-center justify-center gap-2 rounded-lg border border-cyan-700 bg-cyan-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-cyan-800">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"></path>
                </svg>
                Novo Exercício
            </a>
            <button type="button" onclick="abrirModalBancoQuestoes('educatudo')"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50">
                <svg class="h-4 w-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"></path>
                </svg>
                Questões do EducaTudo
            </button>
            <button type="button" onclick="abrirModalBancoQuestoes('professor')"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50">
                <svg class="h-4 w-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Questões do Professor
            </button>
            <a href="<?= URL ?>/professor/jornadas/<?= (int)$modulo['jornada_id'] ?>/modulos"
               class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50">
                <svg class="h-4 w-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 17l5-5-5-5"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12H9"></path>
                </svg>
                Voltar
            </a>
        </div>
    </div>
</div>

<div id="ia-geracao-status-card"
     class="hidden mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 shadow-sm"
     data-status="idle">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white">
                <svg id="ia-geracao-status-spinner" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                <svg id="ia-geracao-status-check" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path d="M20 6L9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </div>
            <div>
                <p id="ia-geracao-status-titulo" class="text-sm font-semibold text-blue-950">A Tudinha está gerando exercícios</p>
                <p id="ia-geracao-status-texto" class="mt-1 text-sm text-blue-800">A geração está em segundo plano. Você pode continuar usando a lista normalmente.</p>
                <p id="ia-geracao-status-meta" class="mt-2 text-xs font-medium text-blue-700"></p>
            </div>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
            <button type="button" id="ia-geracao-status-atualizar"
                    class="hidden rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                Atualizar lista
            </button>
            <button type="button" id="ia-geracao-status-ocultar"
                    class="rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                Ocultar
            </button>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 border border-blue-200">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <h3 class="text-lg font-semibold text-gray-900">Lista de Exercícios</h3>
        <?php
        $totalPublicados = 0;
        $totalRascunhos = 0;
        $nivelBadges = [
            'facil' => ['label' => 'Fácil', 'class' => 'bg-emerald-100 text-emerald-800'],
            'fácil' => ['label' => 'Fácil', 'class' => 'bg-emerald-100 text-emerald-800'],
            'medio' => ['label' => 'Médio', 'class' => 'bg-amber-100 text-amber-800'],
            'médio' => ['label' => 'Médio', 'class' => 'bg-amber-100 text-amber-800'],
            'dificil' => ['label' => 'Difícil', 'class' => 'bg-red-100 text-red-800'],
            'difícil' => ['label' => 'Difícil', 'class' => 'bg-red-100 text-red-800'],
        ];
        foreach ($exercicios as $ex) {
            if (($ex['status'] ?? '') === 'publicado') $totalPublicados++;
            else $totalRascunhos++;
        }
        ?>
        <div class="flex gap-2 text-sm">
            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full"><?= $totalPublicados ?> publicado(s)</span>
            <?php if ($totalRascunhos > 0): ?>
                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full"><?= $totalRascunhos ?> rascunho(s)</span>
            <?php endif; ?>
        </div>
    </div>

    <div id="exerciciosList" class="space-y-4">
        <?php if (empty($exercicios)): ?>
            <div class="text-center py-10 text-gray-500 border border-dashed border-gray-300 rounded-xl">
                <p class="mb-2">Nenhum exercício criado ainda</p>
                <a href="<?= URL ?>/professor/jornadas/modulos/<?= (int)$modulo['id'] ?>/exercicios/criar"
                   class="inline-block mt-2 text-blue-600 hover:text-blue-800 font-medium">Criar primeiro exercício</a>
            </div>
        <?php else: ?>
            <?php foreach ($exercicios as $exercicio): ?>
                <?php
                $enunciadoResumo = trim(preg_replace('/\s+/', ' ', strip_tags((string)($exercicio['enunciado'] ?? ''))));
                $enunciadoResumo = mb_substr($enunciadoResumo, 0, 220) . (mb_strlen($enunciadoResumo) > 220 ? '...' : '');
                $nivelKey = mb_strtolower(trim((string)($exercicio['nivel_dificuldade'] ?? '')), 'UTF-8');
                $nivelBadge = $nivelBadges[$nivelKey] ?? null;
                ?>
                <div class="border border-gray-200 rounded-xl p-4 hover:shadow-md transition-shadow">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded"><?= ucfirst(str_replace('_', ' ', (string)$exercicio['tipo'])) ?></span>
                                <?php if ($nivelBadge): ?>
                                    <span class="px-2 py-1 text-xs rounded <?= $nivelBadge['class'] ?>"><?= htmlspecialchars($nivelBadge['label']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($exercicio['gerado_ia'])): ?>
                                    <span class="px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded">IA</span>
                                <?php endif; ?>
                                <span class="px-2 py-1 text-xs <?= ($exercicio['status'] ?? '') === 'publicado' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?> rounded">
                                    <?= ucfirst((string)($exercicio['status'] ?? 'rascunho')) ?>
                                </span>
                            </div>
                            <p class="text-gray-800 leading-relaxed"><?= htmlspecialchars($enunciadoResumo) ?></p>
                            <p class="text-xs text-gray-500 mt-2">Pontuação: <?= htmlspecialchars((string)($exercicio['pontuacao'] ?? '1.00')) ?> pontos</p>
                        </div>

                        <div class="flex items-center gap-2 md:pl-4">
                            <a href="<?= URL ?>/professor/jornadas/modulos/<?= (int)$modulo['id'] ?>/exercicios/criar?editar=<?= (int)$exercicio['id'] ?>"
                               class="px-3 py-1.5 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors">Editar</a>
                            <button type="button" onclick="removerExercicio(<?= (int)$exercicio['id'] ?>)"
                                    class="px-3 py-1.5 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition-colors">Remover</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
/* Modal banco de questões — enunciado isolado para HTML externo não quebrar o layout */
#modal-banco-questoes .bq-card { border: 1px solid #e5e7eb; border-radius: 0.75rem; background: #fff; }
#modal-banco-questoes .bq-card:hover { border-color: #a5b4fc; }
#modal-banco-questoes .bq-card.is-selected { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,.15); background: #f8fafc; }
#modal-banco-questoes .bq-enunciado {
    font-size: 15px; line-height: 1.65; color: #1f2937;
    max-height: min(55vh, 560px); overflow: auto; padding: 0.25rem 0.15rem;
}
#modal-banco-questoes .bq-enunciado img,
#modal-banco-questoes .bq-enunciado svg {
    max-width: 100% !important; height: auto !important; display: block; margin: 0.75rem auto;
}
#modal-banco-questoes .bq-enunciado table { display: block; max-width: 100%; overflow-x: auto; border-collapse: collapse; }
#modal-banco-questoes .bq-enunciado p { margin: 0.5rem 0; }
#modal-banco-questoes .bq-enunciado figure { margin: 0.75rem 0; }
</style>
<div id="modal-banco-questoes" class="fixed inset-0 z-50 hidden items-stretch justify-center bg-slate-900/55 p-2 sm:p-4" style="display: none;">
    <div class="flex h-[96vh] w-full max-w-7xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
        <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
            <div class="min-w-0">
                <h3 id="bq-modal-titulo" class="text-xl font-bold text-slate-900">Banco de Questões</h3>
                <p id="bq-modal-descricao" class="mt-1 text-sm text-slate-600">Selecione questões para importar neste módulo.</p>
            </div>
            <button type="button" onclick="fecharModalBancoQuestoes()" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Fechar">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="shrink-0 space-y-3 border-b border-slate-100 px-5 py-3">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                <input id="bq-q" type="text" placeholder="Buscar por título, assunto ou enunciado"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 sm:col-span-2">
                <select id="bq-materia" class="select-safari w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></select>
                <select id="bq-tipo" class="select-safari w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></select>
                <select id="bq-dificuldade" class="select-safari w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></select>
                <select id="bq-topico" class="select-safari w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></select>
                <select id="bq-tag" class="select-safari w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></select>
                <select id="bq-origem" class="select-safari w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></select>
                <input id="bq-ano" type="text" placeholder="Ano"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="buscarBancoQuestoes(true)"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-700">
                    Buscar
                </button>
                <button type="button" onclick="limparFiltrosBancoQuestoes()"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50">
                    Limpar filtros
                </button>
                <span id="bq-total-info" class="text-sm text-slate-600"></span>
            </div>
        </div>

        <div class="flex min-h-0 flex-1 flex-col gap-0 lg:flex-row">
            <div class="flex min-h-0 min-w-0 flex-1 flex-col px-5 py-3">
                <div id="bq-loading" class="mb-2 hidden text-sm font-medium text-indigo-700">Carregando questões...</div>
                <div id="bq-lista" class="min-h-0 flex-1 space-y-4 overflow-y-auto pr-1"></div>
            </div>
            <aside class="flex max-h-[28vh] w-full shrink-0 flex-col border-t border-slate-200 bg-slate-50 lg:max-h-none lg:w-72 lg:border-l lg:border-t-0">
                <div class="flex items-center justify-between px-4 py-3">
                    <h4 class="text-sm font-semibold text-slate-800">Selecionadas</h4>
                    <span id="bq-selecionadas" class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-700">0</span>
                </div>
                <div id="bq-selecionadas-lista" class="min-h-0 flex-1 space-y-2 overflow-y-auto px-4 pb-3 text-sm text-slate-700">
                    <p class="text-xs text-slate-500">Nenhuma questão selecionada.</p>
                </div>
            </aside>
        </div>

        <div class="flex shrink-0 flex-col gap-3 border-t border-slate-200 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <button type="button" id="bq-prev" onclick="mudarPaginaBancoQuestoes(-1)"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 disabled:opacity-40">
                    Anterior
                </button>
                <button type="button" id="bq-next" onclick="mudarPaginaBancoQuestoes(1)"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 disabled:opacity-40">
                    Próxima
                </button>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="fecharModalBancoQuestoes()"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Fechar
                </button>
                <button type="button" onclick="importarSelecionadasBancoQuestoes()"
                        class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Importar selecionadas
                </button>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/components/ai-job-poller.php'; ?>
<script>
const iaGeracaoModuloId = <?= (int)$modulo['id'] ?>;
const iaGeracaoStorageKey = 'educatudo:jornada:exercicios-ia:' + iaGeracaoModuloId;
const csrfTokenModuloExercicios = <?= json_encode($csrf_token ?? '') ?>;
const bancoQuestoesBaseUrl = '<?= rtrim(URL, "/") ?>';
let bqSelecionadas = new Set();
let bqSelecionadasMeta = {};
let bqTotal = 0;
let bqLimit = 10;
let bqOffset = 0;
let bqFonteAtual = 'educatudo';

function getIaGeracaoCard() {
    return document.getElementById('ia-geracao-status-card');
}

function setIaGeracaoCardState(state, titulo, texto, meta) {
    const card = getIaGeracaoCard();
    if (!card) return;
    const tituloEl = document.getElementById('ia-geracao-status-titulo');
    const textoEl = document.getElementById('ia-geracao-status-texto');
    const metaEl = document.getElementById('ia-geracao-status-meta');
    const spinner = document.getElementById('ia-geracao-status-spinner');
    const check = document.getElementById('ia-geracao-status-check');
    const atualizar = document.getElementById('ia-geracao-status-atualizar');

    card.dataset.status = state || 'processing';
    card.classList.remove('hidden', 'border-blue-200', 'bg-blue-50', 'border-green-200', 'bg-green-50', 'border-red-200', 'bg-red-50');
    card.classList.add(
        state === 'done' ? 'border-green-200' : (state === 'failed' ? 'border-red-200' : 'border-blue-200'),
        state === 'done' ? 'bg-green-50' : (state === 'failed' ? 'bg-red-50' : 'bg-blue-50')
    );

    if (tituloEl) {
        tituloEl.textContent = titulo || 'A Tudinha está gerando exercícios';
        tituloEl.className = 'text-sm font-semibold ' + (state === 'done' ? 'text-green-950' : (state === 'failed' ? 'text-red-950' : 'text-blue-950'));
    }
    if (textoEl) {
        textoEl.textContent = texto || 'A geração está em segundo plano. Você pode continuar usando a lista normalmente.';
        textoEl.className = 'mt-1 text-sm ' + (state === 'done' ? 'text-green-800' : (state === 'failed' ? 'text-red-800' : 'text-blue-800'));
    }
    if (metaEl) {
        metaEl.textContent = meta || '';
        metaEl.className = 'mt-2 text-xs font-medium ' + (state === 'done' ? 'text-green-700' : (state === 'failed' ? 'text-red-700' : 'text-blue-700'));
    }
    if (spinner) spinner.classList.toggle('hidden', state === 'done' || state === 'failed');
    if (check) check.classList.toggle('hidden', state !== 'done');
    if (atualizar) atualizar.classList.toggle('hidden', state !== 'done');
}

function obterGeracaoIAPendente() {
    try {
        const raw = localStorage.getItem(iaGeracaoStorageKey);
        return raw ? JSON.parse(raw) : null;
    } catch (e) {
        return null;
    }
}

function limparGeracaoIAPendente() {
    try {
        localStorage.removeItem(iaGeracaoStorageKey);
    } catch (e) {}
}

function finalizarImportacaoExerciciosIA(jobId, meta) {
    setIaGeracaoCardState('processing', 'Salvando exercícios...', 'A geração terminou. Agora estamos vinculando os exercícios a este módulo.', meta || 'Quase pronto.');
    const fd = new FormData();
    fd.append('_token', csrfTokenModuloExercicios);

    return fetch('<?= URL ?>/professor/jornadas/modulos/importar-exercicios-ia/' + jobId, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
    })
    .then(function(r) {
        return r.json().then(function(body) { return { ok: r.ok, body: body }; });
    })
    .then(function(res) {
        if (!res.ok || !res.body.success) {
            throw new Error((res.body && res.body.error) ? res.body.error : 'Exercícios gerados, mas falha ao salvar.');
        }
        const qtd = res.body.exercicios_ids ? res.body.exercicios_ids.length : 0;
        limparGeracaoIAPendente();
        setIaGeracaoCardState(
            'done',
            'Exercícios prontos',
            qtd + ' exercício(s) gerado(s) e salvos neste módulo.',
            'Atualize a lista para visualizar os novos exercícios.'
        );
    });
}

function acompanharGeracaoExerciciosIA(jobId, totalQuestoes) {
    let importStarted = false;
    const totalMeta = totalQuestoes ? (totalQuestoes + (totalQuestoes === 1 ? ' questão solicitada.' : ' questões solicitadas.')) : '';
    setIaGeracaoCardState('processing', 'Na fila da Tudinha...', 'A geração está em segundo plano. Você pode continuar usando a lista normalmente.', totalMeta);

    new AIJobPoller(jobId, {
        onProgress: function(status) {
            setIaGeracaoCardState(
                'processing',
                status === 'pending' ? 'Na fila da Tudinha...' : 'A Tudinha está criando os exercícios...',
                'Processando em segundo plano. Esta lista será atualizada quando você clicar em atualizar.',
                totalMeta
            );
        },
        onDone: function() {
            if (importStarted) return;
            importStarted = true;
            finalizarImportacaoExerciciosIA(jobId, totalMeta).catch(function(err) {
                setIaGeracaoCardState('failed', 'Não foi possível salvar os exercícios', err && err.message ? err.message : 'Falha ao salvar os exercícios gerados.', 'Tente abrir novamente a tela de criação ou gerar outra vez.');
            });
        },
        onFailed: function(err) {
            limparGeracaoIAPendente();
            setIaGeracaoCardState('failed', 'Falha na geração pela Tudinha', err || 'Falha no processamento da IA.', 'Tente gerar novamente.');
        }
    });
}

document.getElementById('ia-geracao-status-ocultar')?.addEventListener('click', function() {
    const card = getIaGeracaoCard();
    if (!card) return;
    if (card.dataset.status !== 'processing') {
        limparGeracaoIAPendente();
    }
    card.classList.add('hidden');
});

document.getElementById('ia-geracao-status-atualizar')?.addEventListener('click', function() {
    window.location.reload();
});

document.addEventListener('DOMContentLoaded', function() {
    const pendente = obterGeracaoIAPendente();
    if (pendente && pendente.job_id) {
        acompanharGeracaoExerciciosIA(pendente.job_id, Number(pendente.quantidade || 0));
    }
});

function abrirModalBancoQuestoes(fonte) {
    bqFonteAtual = fonte === 'professor' ? 'professor' : 'educatudo';
    bqOffset = 0;
    bqSelecionadas = new Set();
    bqSelecionadasMeta = {};
    atualizarTextoModalBancoQuestoes();
    atualizarTotalSelecionadasBancoQuestoes();

    const modal = document.getElementById('modal-banco-questoes');
    if (modal) {
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }
    inicializarBancoQuestoes();
}

function atualizarTextoModalBancoQuestoes() {
    const titulo = document.getElementById('bq-modal-titulo');
    const descricao = document.getElementById('bq-modal-descricao');
    if (bqFonteAtual === 'professor') {
        if (titulo) titulo.textContent = 'Questões do Professor';
        if (descricao) descricao.textContent = 'Questões que este professor já criou, gerou por IA ou reutilizou nas jornadas.';
        return;
    }
    if (titulo) titulo.textContent = 'Questões do EducaTudo';
    if (descricao) descricao.textContent = 'Banco geral de questões do EducaTudo para compor os exercícios deste módulo.';
}

function fecharModalBancoQuestoes() {
    const modal = document.getElementById('modal-banco-questoes');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
}

function parseBancoQuestoesJson(response) {
    return response.text().then(function(text) {
        var body = null;
        var raw = String(text || '').trim();
        try {
            body = raw ? JSON.parse(raw) : null;
        } catch (e) {
            var trecho = raw.replace(/\s+/g, ' ').slice(0, 180);
            throw new Error(
                'Resposta inválida do servidor (HTTP ' + response.status + ')'
                + (trecho ? ': ' + trecho : '. Verifique login/sessão e tente novamente.')
            );
        }
        if (!response.ok) {
            var msgErro = body && body.error != null ? String(body.error).trim() : '';
            if (msgErro) throw new Error(msgErro);
            var trechoVazio = raw.replace(/\s+/g, ' ').slice(0, 180);
            throw new Error(
                'Erro na requisição (HTTP ' + response.status + ')'
                + (trechoVazio ? ': ' + trechoVazio : ' — sem detalhe do servidor. Tente novamente.')
            );
        }
        return body || {};
    });
}

function filtroBancoQuestoesAtual() {
    const getVal = function(id) {
        const el = document.getElementById(id);
        return el ? String(el.value || '').trim() : '';
    };
    const filtro = {
        fonte: bqFonteAtual,
        q: getVal('bq-q'),
        materia: getVal('bq-materia'),
        tipo: getVal('bq-tipo'),
        ano: getVal('bq-ano'),
        dificuldade: getVal('bq-dificuldade'),
        topico: getVal('bq-topico'),
        tag: getVal('bq-tag'),
        origem_titulo: getVal('bq-origem')
    };
    Object.keys(filtro).forEach(function(key) {
        if (!filtro[key]) delete filtro[key];
    });
    return filtro;
}

function preencherSelectBancoQuestoes(id, itens, placeholder) {
    const el = document.getElementById(id);
    if (!el) return;
    const atual = el.value;
    const options = ['<option value="">' + escapeHtmlBancoQuestoes(placeholder) + '</option>'];
    (itens || []).forEach(function(item) {
        let valor = '';
        let total = null;
        if (typeof item === 'string') {
            valor = item;
        } else if (item && typeof item === 'object') {
            valor = String(item.valor || item.materia || item.tipo || '').trim();
            total = Number(item.total);
        }
        if (!valor) return;
        const label = (Number.isFinite(total) && total >= 0) ? valor + ' (' + total + ')' : valor;
        options.push('<option value="' + escapeHtmlBancoQuestoes(valor) + '">' + escapeHtmlBancoQuestoes(label) + '</option>');
    });
    el.innerHTML = options.join('');
    el.value = atual || '';
}

function inicializarBancoQuestoes() {
    const loading = document.getElementById('bq-loading');
    if (loading) loading.classList.remove('hidden');
    const filtro = filtroBancoQuestoesAtual();
    const query = new URLSearchParams(filtro).toString();
    fetch(bancoQuestoesBaseUrl + '/professor/jornadas/modulos/banco-questoes/facets' + (query ? '?' + query : ''), {
        headers: { 'Accept': 'application/json' }
    })
        .then(parseBancoQuestoesJson)
        .then(function(data) {
            if (!data.success) throw new Error(data.error || 'Erro ao carregar filtros');
            const facets = (data.data && data.data.facets) ? data.data.facets : {};
            preencherSelectBancoQuestoes('bq-materia', facets.materias || [], 'Todas as matérias');
            preencherSelectBancoQuestoes('bq-tipo', facets.tipos || [], 'Todos os tipos');
            preencherSelectBancoQuestoes('bq-origem', facets.origens_titulo || [], 'Todas as origens');
            preencherSelectBancoQuestoes('bq-dificuldade', facets.dificuldades || [], 'Todas as dificuldades');
            preencherSelectBancoQuestoes('bq-topico', facets.topicos || [], 'Todos os tópicos');
            preencherSelectBancoQuestoes('bq-tag', facets.tags || [], 'Todas as tags');
            buscarBancoQuestoes(true);
        })
        .catch(function(err) {
            if (loading) loading.classList.add('hidden');
            alert('Erro ao carregar banco de questões: ' + (err.message || err));
        });
}

function limparFiltrosBancoQuestoes() {
    ['bq-q', 'bq-materia', 'bq-tipo', 'bq-ano', 'bq-dificuldade', 'bq-topico', 'bq-tag', 'bq-origem'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    bqOffset = 0;
    bqSelecionadas = new Set();
    bqSelecionadasMeta = {};
    atualizarTotalSelecionadasBancoQuestoes();
    inicializarBancoQuestoes();
}

function buscarBancoQuestoes(resetOffset) {
    if (resetOffset) bqOffset = 0;
    const loading = document.getElementById('bq-loading');
    const lista = document.getElementById('bq-lista');
    if (loading) loading.classList.remove('hidden');
    if (lista) lista.innerHTML = '';
    const params = new URLSearchParams({
        ...filtroBancoQuestoesAtual(),
        limit: String(bqLimit),
        offset: String(bqOffset)
    });
    fetch(bancoQuestoesBaseUrl + '/professor/jornadas/modulos/banco-questoes/listar?' + params.toString(), {
        headers: { 'Accept': 'application/json' }
    })
        .then(parseBancoQuestoesJson)
        .then(function(data) {
            if (!data.success) throw new Error(data.error || 'Erro ao listar questões');
            renderBancoQuestoes(data.data || {});
        })
        .catch(function(err) {
            alert('Erro ao buscar questões: ' + (err.message || err));
        })
        .finally(function() {
            if (loading) loading.classList.add('hidden');
        });
}

function cssEscapeBancoQuestoes(value) {
    var s = String(value || '');
    if (window.CSS && typeof CSS.escape === 'function') return CSS.escape(s);
    return s.replace(/[^a-zA-Z0-9_-]/g, '\\$&');
}

function isolarHtmlEnunciadoBancoQuestoes(html) {
    // Remove tags que quebram o card (HTML externo do banco às vezes vem malformado).
    return String(html || '')
        .replace(/<\/?(script|iframe|object|embed|form|label)[^>]*>/gi, '')
        .replace(/on\w+\s*=\s*(['"]).*?\1/gi, '')
        .replace(/on\w+\s*=\s*[^\s>]+/gi, '');
}

function renderBancoQuestoes(payload) {
    const lista = document.getElementById('bq-lista');
    if (!lista) return;
    const questoes = Array.isArray(payload.questoes) ? payload.questoes : [];
    bqTotal = parseInt(payload.total || 0, 10) || 0;
    bqLimit = parseInt(payload.limit || bqLimit, 10) || bqLimit;
    bqOffset = parseInt(payload.offset || bqOffset, 10) || bqOffset;

    if (!questoes.length) {
        lista.innerHTML = '<div class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-600">Nenhuma questão encontrada com os filtros atuais.</div>';
    } else {
        lista.innerHTML = questoes.map(function(q) {
            const id = String(q.id || '');
            const selected = bqSelecionadas.has(id);
            const checked = selected ? 'checked' : '';
            const enunciadoTexto = ((q.enunciado_html || q.enunciado || '') + '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            bqSelecionadasMeta[id] = {
                id: id,
                materia: String(q.materia || ''),
                tipo: String(q.tipo || ''),
                enunciado: enunciadoTexto
            };
            const materia = q.materia ? '<span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">' + escapeHtmlBancoQuestoes(String(q.materia)) + '</span>' : '';
            const dif = q.dificuldade ? '<span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">' + escapeHtmlBancoQuestoes(String(q.dificuldade)) + '</span>' : '';
            const tipo = q.tipo ? '<span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">' + escapeHtmlBancoQuestoes(String(q.tipo)) + '</span>' : '';
            const origem = q.origem && q.origem.raw ? escapeHtmlBancoQuestoes(String(q.origem.raw)) : '';
            const enunciado = isolarHtmlEnunciadoBancoQuestoes(q.enunciado_html || q.enunciado || '');
            return '' +
                '<article class="bq-card ' + (selected ? 'is-selected' : '') + '" data-bq-card="' + escapeHtmlBancoQuestoes(id) + '">' +
                    '<div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3">' +
                        '<input type="checkbox" class="h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" data-bq-id="' + escapeHtmlBancoQuestoes(id) + '" ' + checked + ' onchange="toggleSelecionadaBancoQuestoes(this)">' +
                        '<div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">' +
                            '<span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700">ID ' + escapeHtmlBancoQuestoes(id) + '</span>' +
                            materia + tipo + dif +
                        '</div>' +
                        '<button type="button" class="shrink-0 text-xs font-medium text-indigo-600 hover:text-indigo-800" data-bq-toggle="' + escapeHtmlBancoQuestoes(id) + '" onclick="toggleSelecionadaBancoQuestoesById(this.getAttribute(\'data-bq-toggle\'))">' +
                            (selected ? 'Remover' : 'Selecionar') +
                        '</button>' +
                    '</div>' +
                    '<div class="bq-enunciado px-4 py-3">' + enunciado + '</div>' +
                    (origem ? '<div class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">' + origem + '</div>' : '') +
                '</article>';
        }).join('');
    }

    const info = document.getElementById('bq-total-info');
    if (info) {
        const ate = Math.min(bqOffset + bqLimit, bqTotal);
        info.textContent = bqTotal > 0 ? 'Mostrando ' + (bqOffset + 1) + '-' + ate + ' de ' + bqTotal : '0 resultados';
    }

    const btnPrev = document.getElementById('bq-prev');
    const btnNext = document.getElementById('bq-next');
    if (btnPrev) btnPrev.disabled = bqOffset <= 0;
    if (btnNext) btnNext.disabled = (bqOffset + bqLimit) >= bqTotal;
    renderSelecionadasLateralBancoQuestoes();
}

function syncCardSelecaoBancoQuestoes(id, selecionada) {
    const card = document.querySelector('[data-bq-card="' + cssEscapeBancoQuestoes(id) + '"]');
    if (!card) return;
    card.classList.toggle('is-selected', !!selecionada);
    const btn = card.querySelector('[data-bq-toggle]');
    if (btn) btn.textContent = selecionada ? 'Remover' : 'Selecionar';
    const cb = card.querySelector('input[data-bq-id]');
    if (cb) cb.checked = !!selecionada;
}

function toggleSelecionadaBancoQuestoesById(id) {
    id = String(id || '').trim();
    if (!id) return;
    const cb = document.querySelector('input[data-bq-id="' + cssEscapeBancoQuestoes(id) + '"]');
    if (cb) {
        cb.checked = !cb.checked;
        toggleSelecionadaBancoQuestoes(cb);
        return;
    }
    if (bqSelecionadas.has(id)) {
        bqSelecionadas.delete(id);
    } else {
        bqSelecionadas.add(id);
    }
    syncCardSelecaoBancoQuestoes(id, bqSelecionadas.has(id));
    atualizarTotalSelecionadasBancoQuestoes();
}

function toggleSelecionadaBancoQuestoes(el) {
    const id = String(el.getAttribute('data-bq-id') || '').trim();
    if (!id) return;
    if (el.checked) {
        bqSelecionadas.add(id);
    } else {
        bqSelecionadas.delete(id);
    }
    syncCardSelecaoBancoQuestoes(id, el.checked);
    atualizarTotalSelecionadasBancoQuestoes();
}

function atualizarTotalSelecionadasBancoQuestoes() {
    const el = document.getElementById('bq-selecionadas');
    if (el) el.textContent = String(bqSelecionadas.size);
    renderSelecionadasLateralBancoQuestoes();
}

function renderSelecionadasLateralBancoQuestoes() {
    const box = document.getElementById('bq-selecionadas-lista');
    if (!box) return;
    const ids = Array.from(bqSelecionadas);
    if (!ids.length) {
        box.innerHTML = '<p class="text-xs text-gray-500">Nenhuma questão selecionada.</p>';
        return;
    }
    box.innerHTML = ids.map(function(id) {
        const meta = bqSelecionadasMeta[id] || { id: id, materia: '', tipo: '', enunciado: '' };
        const texto = meta.enunciado ? meta.enunciado.slice(0, 120) + (meta.enunciado.length > 120 ? '...' : '') : 'Questão selecionada';
        return '' +
            '<div class="rounded-lg border border-gray-200 bg-white p-2">' +
                '<div class="flex items-start justify-between gap-2">' +
                    '<div class="min-w-0">' +
                        '<div class="text-xs font-semibold text-indigo-700">ID ' + escapeHtmlBancoQuestoes(meta.id) + '</div>' +
                        '<div class="text-xs text-gray-500">' + escapeHtmlBancoQuestoes([meta.materia, meta.tipo].filter(Boolean).join(' • ')) + '</div>' +
                    '</div>' +
                    '<button type="button" class="text-xs text-red-600 hover:text-red-700" onclick="removerSelecionadaBancoQuestoes(&quot;' + escapeHtmlBancoQuestoes(meta.id) + '&quot;)">Remover</button>' +
                '</div>' +
                '<p class="mt-1 text-xs leading-snug text-gray-700">' + escapeHtmlBancoQuestoes(texto) + '</p>' +
            '</div>';
    }).join('');
}

function removerSelecionadaBancoQuestoes(id) {
    const key = String(id || '').trim();
    if (!key) return;
    bqSelecionadas.delete(key);
    syncCardSelecaoBancoQuestoes(key, false);
    atualizarTotalSelecionadasBancoQuestoes();
}

function mudarPaginaBancoQuestoes(direction) {
    const novoOffset = bqOffset + (direction * bqLimit);
    if (novoOffset < 0) return;
    if (direction > 0 && novoOffset >= bqTotal) return;
    bqOffset = novoOffset;
    buscarBancoQuestoes(false);
}

function importarSelecionadasBancoQuestoes() {
    if (bqSelecionadas.size === 0) {
        alert('Selecione ao menos uma questão para importar.');
        return;
    }
    if (!confirm('Importar as questões selecionadas para esta jornada?')) {
        return;
    }
    fetch(bancoQuestoesBaseUrl + '/professor/jornadas/modulos/banco-questoes/importar', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            modulo_id: <?= (int)$modulo['id'] ?>,
            questao_ids: Array.from(bqSelecionadas),
            _token: <?= json_encode($csrf_token ?? ($_SESSION['csrf_token'] ?? '')) ?>
        })
    })
        .then(parseBancoQuestoesJson)
        .then(function(data) {
            if (!data.success) throw new Error(data.error || 'Erro ao importar');
            var falhas = Array.isArray(data.falhas) ? data.falhas : [];
            var importados = Number(data.importados || 0);
            var creditos = Number(data.creditos_consumidos || 0);
            if (falhas.length > 0) {
                var detalhe = (falhas[0] && falhas[0].erro) ? String(falhas[0].erro) : 'erro não informado';
                alert(
                    importados + ' questão(ões) importada(s), ' + falhas.length + ' falha(s).'
                    + ' Créditos: ' + creditos + '.\nDetalhe: ' + detalhe
                );
                if (importados > 0) {
                    window.location.reload();
                }
                return;
            }
            alert(importados + ' questão(ões) importada(s) com sucesso. Créditos: ' + creditos + '.');
            window.location.reload();
        })
        .catch(function(err) {
            alert('Erro ao importar questões: ' + (err.message || err));
        });
}

function escapeHtmlBancoQuestoes(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function removerExercicio(id) {
    if (!confirm('Tem certeza que deseja remover este exercício?')) {
        return;
    }

    const formData = new FormData();
    formData.append('exercicio_id', id);
    formData.append('_token', <?= json_encode($csrf_token) ?>);

    fetch('<?= URL ?>/professor/jornadas/modulos/remover-exercicio', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        alert('Erro de conexão');
        console.error(error);
    });
}
</script>
