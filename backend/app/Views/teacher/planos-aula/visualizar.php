<?php $contextoIa = trim((string) ($plano['contexto_llm'] ?? '')); ?>
<!-- Header Section -->
<div class="mb-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Visualizar Plano de Aula</h1>
            <p class="text-gray-600 mt-2">Detalhes completos do plano de aula</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <?php if ($contextoIa !== ''): ?>
            <button type="button"
                    onclick="abrirContextoIaModal()"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary shadow-sm transition-colors hover:opacity-90">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                Texto IA
            </button>
            <?php endif; ?>
            <a href="<?= URL ?>/professor/planos-aula/pdf/<?= $plano['id'] ?>" target="_blank"
               class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50">
                <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Exportar PDF
            </a>
            <a href="<?= URL ?>/professor/planos-aula/editar/<?= $plano['id'] ?>"
               class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary shadow-sm transition-colors hover:opacity-90">
                Editar
            </a>
            <a href="<?= URL ?>/professor/planos-aula"
               class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50">
                Voltar
            </a>
        </div>
    </div>
</div>

<!-- Informações Básicas -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-2">Informações Básicas</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <p class="text-sm text-gray-500 mb-1">Título</p>
            <p class="text-lg font-medium text-gray-900"><?= htmlspecialchars($plano['titulo']) ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">Datas da Aula</p>
            <?php
            // Tenta decodificar as datas se estiverem em JSON
            $datas = [];
            if (!empty($plano['data_aula'])) {
                $datasJson = json_decode($plano['data_aula'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($datasJson)) {
                    $datas = $datasJson;
                } else {
                    // Se não for JSON, assume que é uma data única (compatibilidade)
                    $datas = [$plano['data_aula']];
                }
            }
            ?>
            <p class="text-lg font-medium text-gray-900">
                <?php if (!empty($datas)): ?>
                    <?php foreach ($datas as $index => $dataItem): ?>
                        <?= date('d/m/Y', strtotime($dataItem)) ?><?= $index < count($datas) - 1 ? ', ' : '' ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    N/A
                <?php endif; ?>
            </p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">Matéria</p>
            <p class="text-lg font-medium text-gray-900"><?= htmlspecialchars($plano['materia_nome']) ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">Turma</p>
            <p class="text-lg font-medium text-gray-900"><?= htmlspecialchars($plano['turma_nome']) ?></p>
        </div>
            <?php if (!empty($plano['ano_disciplina'])): ?>
            <div>
                <p class="text-sm text-gray-500 mb-1">Ano/Disciplina</p>
                <?php $anoDisciplina = preg_replace('/\s*\(\+\d+\)/', '', $plano['ano_disciplina']); ?>
                <p class="text-lg font-medium text-gray-900"><?= htmlspecialchars($anoDisciplina) ?></p>
            </div>
            <?php endif; ?>
    </div>
</div>

<!-- Estrutura do Conteúdo -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-2">Estrutura do Conteúdo</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php if (!empty($plano['modulo'])): ?>
        <div>
            <p class="text-sm text-gray-500 mb-1">Módulo</p>
            <p class="text-base font-medium text-gray-900"><?= htmlspecialchars($plano['modulo']) ?></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($plano['aula_num'])): ?>
        <div>
            <p class="text-sm text-gray-500 mb-1">Aula Nº</p>
            <p class="text-base font-medium text-gray-900"><?= htmlspecialchars($plano['aula_num']) ?></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($plano['paginas'])): ?>
        <div>
            <p class="text-sm text-gray-500 mb-1">Páginas</p>
            <p class="text-base font-medium text-gray-900"><?= htmlspecialchars($plano['paginas']) ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php if (!empty($plano['conteudo'])): ?>
    <div class="mt-4">
        <p class="text-sm text-gray-500 mb-2">Conteúdo</p>
        <div class="bg-gray-50 rounded-lg p-4">
            <div class="text-gray-800 prose prose-sm max-w-none"><?= rich_text_render($plano['conteudo']) ?></div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($plano['conteudo_lista'])): ?>
    <div class="mt-4">
        <p class="text-sm text-gray-500 mb-2">Lista de Conteúdos</p>
        <div class="bg-gray-50 rounded-lg p-4">
            <div class="text-gray-800 prose prose-sm max-w-none"><?= rich_text_render($plano['conteudo_lista']) ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Objetivos -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-2">Objetivos</h2>
    <?php if (!empty($plano['objetivos'])): ?>
    <div class="mb-4">
        <p class="text-sm text-gray-500 mb-2">O Aluno deverá ser capaz de:</p>
        <div class="bg-gray-50 rounded-lg p-4">
            <div class="text-gray-800 prose prose-sm max-w-none"><?= $plano['objetivos'] ?></div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($plano['objetivos_lista'])): ?>
    <div>
        <p class="text-sm text-gray-500 mb-2">Lista de Objetivos Específicos</p>
        <div class="bg-gray-50 rounded-lg p-4">
            <div class="text-gray-800 prose prose-sm max-w-none"><?= $plano['objetivos_lista'] ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Recursos -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-2">Recursos</h2>
    <?php if (!empty($plano['recursos'])): ?>
    <div class="mb-4">
        <p class="text-sm text-gray-500 mb-2">Ferramentas utilizadas para que os objetivos sejam atingidos:</p>
        <div class="bg-gray-50 rounded-lg p-4">
            <div class="text-gray-800 prose prose-sm max-w-none"><?= $plano['recursos'] ?></div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($plano['recursos_lista'])): ?>
    <div>
        <p class="text-sm text-gray-500 mb-2">Recursos Selecionados</p>
        <div class="flex flex-wrap gap-2">
            <?php 
            $recursos = is_array($plano['recursos_lista']) ? $plano['recursos_lista'] : json_decode($plano['recursos_lista'], true);
            if ($recursos): 
                foreach ($recursos as $recurso): ?>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                        <?= htmlspecialchars($recurso) ?>
                    </span>
                <?php endforeach; 
            endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Aula da Tarde -->
<?php if (!empty($plano['aulas_tarde_oficinas']) || !empty($plano['periodo_tarde_tema']) || !empty($plano['periodo_tarde_exercicios'])): ?>
<?php require_once __DIR__ . '/../../../Helpers/LessonPlanAfternoonHelper.php'; ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-2">Aula da Tarde</h2>
    <?php if (!empty($plano['aulas_tarde_oficinas'])): ?>
    <div class="mb-4">
        <p class="text-sm text-gray-500 mb-2">Oficinas de Aprendizagem / Salas de Estudo</p>
        <div class="bg-gray-50 rounded-lg p-4">
            <div class="text-gray-800"><?= LessonPlanAfternoonHelper::renderHtml($plano['aulas_tarde_oficinas']) ?></div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($plano['periodo_tarde_tema'])): ?>
    <div class="mb-4">
        <p class="text-sm text-gray-500 mb-2">Tema do período da tarde</p>
        <p class="text-base font-medium text-gray-900"><?= htmlspecialchars($plano['periodo_tarde_tema']) ?></p>
    </div>
    <?php endif; ?>
    <?php if (!empty($plano['periodo_tarde_exercicios'])): ?>
    <div>
        <p class="text-sm text-gray-500 mb-2">Exercícios do período da tarde</p>
        <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-gray-800 whitespace-pre-wrap"><?= htmlspecialchars($plano['periodo_tarde_exercicios']) ?></p>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Avaliação -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-2">Avaliação</h2>
    <?php if (!empty($plano['avaliacao'])): ?>
    <div class="mb-4">
        <p class="text-sm text-gray-500 mb-2">Como será avaliado</p>
        <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-gray-800 whitespace-pre-wrap"><?= htmlspecialchars($plano['avaliacao']) ?></p>
        </div>
    </div>
    <?php endif; ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <?php if (!empty($plano['avaliacao_apostila'])): ?>
        <div>
            <p class="text-sm text-gray-500 mb-1">Apostila da Avaliação</p>
            <p class="text-base font-medium text-gray-900"><?= htmlspecialchars($plano['avaliacao_apostila']) ?></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($plano['avaliacao_conteudo'])): ?>
        <div>
            <p class="text-sm text-gray-500 mb-1">Conteúdo da Avaliação</p>
            <p class="text-base font-medium text-gray-900"><?= htmlspecialchars($plano['avaliacao_conteudo']) ?></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($plano['avaliacao_paginas'])): ?>
        <div>
            <p class="text-sm text-gray-500 mb-1">Páginas da Avaliação</p>
            <p class="text-base font-medium text-gray-900"><?= htmlspecialchars($plano['avaliacao_paginas']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Observações -->
<?php if (!empty($plano['observacoes'])): ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-2">Observações</h2>
    <div class="bg-gray-50 rounded-lg p-4">
        <p class="text-gray-800 whitespace-pre-wrap"><?= htmlspecialchars($plano['observacoes']) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Informações Adicionais -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-2">Informações Adicionais</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
        <div>
            <p class="text-gray-500 mb-1">Criado em</p>
            <p class="font-medium text-gray-900"><?= date('d/m/Y H:i', strtotime($plano['created_at'])) ?></p>
        </div>
        <div>
            <p class="text-gray-500 mb-1">Última atualização</p>
            <p class="font-medium text-gray-900"><?= date('d/m/Y H:i', strtotime($plano['updated_at'])) ?></p>
        </div>
    </div>
</div>

<?php if ($contextoIa !== ''): ?>
<div id="contextoIaModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/50 p-4">
    <div class="flex max-h-[88vh] w-full max-w-4xl flex-col rounded-xl bg-white shadow-xl">
        <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Texto gerado pela IA</h3>
                <p class="mt-1 text-sm text-gray-500">Contexto completo para jornadas, atividades e exercícios.</p>
            </div>
            <button type="button" onclick="fecharContextoIaModal()" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700" aria-label="Fechar">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="overflow-y-auto px-5 py-4">
            <pre id="contextoIaTexto" class="whitespace-pre-wrap rounded-lg bg-gray-50 p-4 text-sm leading-6 text-gray-800"><?= htmlspecialchars($contextoIa) ?></pre>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 px-5 py-4">
            <button type="button" onclick="copiarContextoIa()" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary shadow-sm transition-colors hover:opacity-90">
                Copiar texto
            </button>
            <button type="button" onclick="fecharContextoIaModal()" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50">
                Fechar
            </button>
        </div>
    </div>
</div>

<script>
function abrirContextoIaModal() {
    const modal = document.getElementById('contextoIaModal');
    if (modal) modal.classList.remove('hidden');
    if (modal) modal.classList.add('flex');
}

function fecharContextoIaModal() {
    const modal = document.getElementById('contextoIaModal');
    if (modal) modal.classList.add('hidden');
    if (modal) modal.classList.remove('flex');
}

function copiarContextoIa() {
    const texto = document.getElementById('contextoIaTexto')?.innerText || '';
    navigator.clipboard?.writeText(texto);
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') fecharContextoIaModal();
});
</script>
<?php endif; ?>
