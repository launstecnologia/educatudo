<?php $filho_id = (int)($filho_id ?? $filho['id'] ?? 0); ?>
<div class="mb-8">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Visualizar Plano de Aula</h1>
            <p class="text-gray-600 mt-2">Detalhes completos do plano de aula</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= URL ?>/pais/filhos/<?= $filho_id ?>/plano-aula/pdf/<?= (int)$plano['id'] ?>" target="_blank" rel="noopener noreferrer"
               class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Abrir PDF
            </a>
            <a href="<?= URL ?>/pais/filhos/<?= $filho_id ?>/plano-aula"
               class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors">
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
            <p class="text-lg font-medium text-gray-900"><?= htmlspecialchars($plano['titulo'] ?? '') ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">Datas da Aula</p>
            <?php
            $datas = [];
            if (!empty($plano['data_aula'])) {
                $datasJson = json_decode($plano['data_aula'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($datasJson)) {
                    $datas = $datasJson;
                } else {
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
            <p class="text-lg font-medium text-gray-900"><?= htmlspecialchars($plano['materia_nome'] ?? '') ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">Turma</p>
            <p class="text-lg font-medium text-gray-900"><?= htmlspecialchars($plano['turma_nome'] ?? '') ?></p>
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
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm"><?= htmlspecialchars($recurso) ?></span>
                <?php endforeach;
            endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($plano['aulas_tarde_oficinas']) || !empty($plano['periodo_tarde_tema']) || !empty($plano['periodo_tarde_exercicios'])): ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-2">Aula da Tarde</h2>
    <?php if (!empty($plano['aulas_tarde_oficinas'])): ?>
    <div class="mb-4">
        <p class="text-sm text-gray-500 mb-2">Ferramentas utilizadas para a aula da tarde / oficinas</p>
        <div class="bg-gray-50 rounded-lg p-4">
            <div class="text-gray-800 prose prose-sm max-w-none"><?= $plano['aulas_tarde_oficinas'] ?></div>
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

<?php if (!empty($plano['observacoes'])): ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-2">Observações</h2>
    <div class="bg-gray-50 rounded-lg p-4">
        <p class="text-gray-800 whitespace-pre-wrap"><?= htmlspecialchars($plano['observacoes']) ?></p>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-lg p-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-2">Informações Adicionais</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
        <div>
            <p class="text-gray-500 mb-1">Criado em</p>
            <p class="font-medium text-gray-900"><?= date('d/m/Y H:i', strtotime($plano['created_at'] ?? 'now')) ?></p>
        </div>
        <div>
            <p class="text-gray-500 mb-1">Última atualização</p>
            <p class="font-medium text-gray-900"><?= date('d/m/Y H:i', strtotime($plano['updated_at'] ?? $plano['created_at'] ?? 'now')) ?></p>
        </div>
    </div>
</div>
