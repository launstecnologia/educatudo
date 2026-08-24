<?php
$filtro_materia_id   = $filtro_materia_id ?? null;
$filtro_professor_id = $filtro_professor_id ?? null;
$filtro_titulo       = $filtro_titulo ?? '';
$filtro_pasta_id     = $filtro_pasta_id ?? null;
$materias            = $materias ?? [];
$professores         = $professores ?? [];
$pastas              = $pastas ?? [];
$pasta_atual         = $pasta_atual ?? null;
$total               = (int)($total ?? 0);
$page                = (int)($page ?? 1);
$per_page            = (int)($per_page ?? 15);
$total_pages         = (int)($total_pages ?? 1);
$modo_recuperacao    = !empty($modo_recuperacao);
$base_path           = $base_path ?? ($modo_recuperacao ? '/aluno/recuperacao' : '/aluno/arquivos');
$baseUrl             = URL . $base_path;
$tituloLista         = $modo_recuperacao ? 'Recuperação' : 'Arquivos';
$subtituloLista      = $subtituloLista ?? ($modo_recuperacao
    ? 'Materiais de recuperação disponibilizados pelo professor para sua turma.'
    : 'Materiais disponibilizados pelo professor para sua turma.');
$url_ver_base        = $url_ver_base ?? (URL . '/aluno/arquivos/ver');
$temFiltroAtivo      = $filtro_materia_id || $filtro_professor_id || $filtro_titulo !== '';
?>

<?php if (!empty($_SESSION['flash_message'] ?? '')): ?>
<div class="mb-4 p-4 rounded-lg <?= ($_SESSION['flash_type'] ?? '') === 'error' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
    <?= htmlspecialchars($_SESSION['flash_message']) ?>
</div>
<?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>

<!-- Cabeçalho -->
<div class="mb-5">
    <?php if ($pasta_atual): ?>
    <nav class="flex items-center gap-2 text-sm mb-2">
        <a href="<?= $baseUrl ?>" class="text-indigo-600 hover:underline flex items-center gap-1">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            <?= htmlspecialchars($tituloLista) ?>
        </a>
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="font-medium text-gray-700"><?= htmlspecialchars((string)($pasta_atual['nome'] ?? '')) ?></span>
    </nav>
    <?php endif; ?>
    <h2 class="text-2xl font-bold text-gray-900"><?= $pasta_atual ? htmlspecialchars((string)($pasta_atual['nome'] ?? '')) : htmlspecialchars($tituloLista) ?></h2>
    <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($subtituloLista) ?></p>
</div>

<!-- Filtros -->
<?php if ($temFiltroAtivo || $filtro_materia_id || $filtro_professor_id || $filtro_titulo !== ''): ?>
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
<?php else: ?>
<details class="bg-white rounded-xl border border-gray-200 mb-4">
    <summary class="px-4 py-3 text-sm font-medium text-gray-600 cursor-pointer select-none hover:bg-gray-50 rounded-xl">
        Filtros de busca
    </summary>
    <div class="px-4 pb-4 pt-2">
<?php endif; ?>

    <form method="get" action="<?= $baseUrl ?>" class="flex flex-wrap gap-3 items-end">
        <?php if ($filtro_pasta_id): ?>
            <input type="hidden" name="pasta_id" value="<?= (int)$filtro_pasta_id ?>">
        <?php endif; ?>
        <div class="min-w-[130px]">
            <label class="block text-xs font-medium text-gray-600 mb-1">Matéria</label>
            <select name="materia_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">Todas</option>
                <?php foreach ($materias as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= $filtro_materia_id === (int)$m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="min-w-[150px]">
            <label class="block text-xs font-medium text-gray-600 mb-1">Professor</label>
            <select name="professor_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">Todos</option>
                <?php foreach ($professores as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $filtro_professor_id === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-[160px]">
            <label class="block text-xs font-medium text-gray-600 mb-1">Título ou descrição</label>
            <input type="text" name="titulo" value="<?= htmlspecialchars($filtro_titulo) ?>" placeholder="Buscar..."
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Filtrar</button>
            <a href="<?= $baseUrl ?><?= $filtro_pasta_id ? '?pasta_id=' . (int)$filtro_pasta_id : '' ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300">Limpar</a>
        </div>
    </form>

<?php if ($temFiltroAtivo): ?>
</div>
<?php else: ?>
    </div>
</details>
<?php endif; ?>

<!-- Lista principal (pastas + arquivos) -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

    <!-- Cabeçalho da tabela -->
    <div class="grid grid-cols-[auto_1fr_auto] items-center px-4 py-2 bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
        <span class="w-8"></span>
        <span class="pl-3">Nome</span>
        <span></span>
    </div>

    <?php $hasContent = !empty($pastas) || !empty($lista); ?>

    <?php if (!$hasContent): ?>
        <p class="text-gray-500 text-center py-10">
            Nenhum arquivo encontrado<?= ($pasta_atual || $temFiltroAtivo) ? ' com os filtros aplicados.' : ' ainda.' ?>
        </p>
        <?php if ($pasta_atual): ?>
            <p class="text-center pb-6"><a href="<?= $baseUrl ?>" class="text-sm text-indigo-600 hover:underline">← Voltar para <?= htmlspecialchars(strtolower($tituloLista)) ?></a></p>
        <?php endif; ?>
    <?php else: ?>

        <!-- Pastas (só na raiz sem filtros) -->
        <?php if (!$temFiltroAtivo && !empty($pastas)): ?>
            <?php foreach ($pastas as $pasta): ?>
            <a href="<?= $baseUrl ?>?pasta_id=<?= (int)$pasta['id'] ?>"
               class="grid grid-cols-[auto_1fr_auto] items-center px-4 py-3 border-b border-gray-100 hover:bg-indigo-50 transition-colors group">
                <!-- Ícone pasta -->
                <div class="w-8 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="<?= htmlspecialchars((string)($pasta['cor'] ?? '#6366f1')) ?>" viewBox="0 0 24 24">
                        <path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.9-2-2-2h-8l-2-2z"/>
                    </svg>
                </div>
                <!-- Nome + info -->
                <div class="pl-3 min-w-0">
                    <span class="font-medium text-gray-800 group-hover:text-indigo-700 truncate block"><?= htmlspecialchars((string)($pasta['nome'] ?? '')) ?></span>
                    <span class="text-xs text-gray-400"><?= (int)($pasta['total_arquivos'] ?? 0) ?> arquivo(s)</span>
                </div>
                <!-- Seta -->
                <div class="pl-4">
                    <svg class="w-4 h-4 text-gray-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Arquivos -->
        <?php foreach ($lista as $row): ?>
        <div class="grid grid-cols-[auto_1fr_auto] items-center px-4 py-3 border-b border-gray-100 hover:bg-gray-50 transition-colors">
            <!-- Ícone arquivo -->
            <div class="w-8 flex items-center justify-center">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <!-- Nome + meta -->
            <div class="pl-3 min-w-0">
                <span class="font-medium text-gray-800 truncate block"><?= htmlspecialchars((string)($row['titulo'] ?? '')) ?></span>
                <div class="flex flex-wrap gap-x-2 text-xs text-gray-400 mt-0.5">
                    <?php if (!empty($row['materia_nome'])): ?>
                        <span><?= htmlspecialchars($row['materia_nome']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($row['professor_nome'])): ?>
                        <span>· <?= htmlspecialchars($row['professor_nome']) ?></span>
                    <?php endif; ?>
                    <?php if ((int)($row['total_anexos'] ?? 0) > 0): ?>
                        <span>· <?= (int)$row['total_anexos'] ?> anexo(s)</span>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Botão ver -->
            <div class="pl-4">
                <a href="<?= htmlspecialchars($url_ver_base) ?>/<?= (int)$row['id'] ?>"
                   class="px-3 py-1.5 text-sm font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 whitespace-nowrap">
                    Ver
                </a>
            </div>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>

    <!-- Paginação -->
    <?php if ($total_pages > 1): ?>
    <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            <?= ($page - 1) * $per_page + 1 ?>–<?= min($page * $per_page, $total) ?> de <?= $total ?> arquivo(s)
        </p>
        <div class="flex gap-2">
            <?php
            $query = array_filter([
                'pasta_id'    => $filtro_pasta_id ?: null,
                'materia_id'  => $filtro_materia_id ?: null,
                'professor_id'=> $filtro_professor_id ?: null,
                'titulo'      => $filtro_titulo !== '' ? $filtro_titulo : null,
            ]);
            $qs = $query ? '&' . http_build_query($query) : '';
            ?>
            <?php if ($page > 1): ?>
                <a href="<?= $baseUrl ?>?page=<?= $page - 1 ?><?= $qs ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-white">Anterior</a>
            <?php endif; ?>
            <span class="px-3 py-1.5 text-sm text-gray-500">Página <?= $page ?> / <?= $total_pages ?></span>
            <?php if ($page < $total_pages): ?>
                <a href="<?= $baseUrl ?>?page=<?= $page + 1 ?><?= $qs ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-white">Próxima</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
