<?php
/** Menu lateral reduzido: perfil Secretaria (@see AdminSecretariaAccess) */
$urlBase = defined('URL') ? rtrim((string) URL, '/') : '';
$cur = $current_page ?? '';
$curMovimentacao = in_array($cur, ['students_remanejamento', 'students_transferencia_escolar'], true);
$secCan = static function (array $keys) use ($adminPermissionsSidebar): bool {
    foreach ($keys as $k) {
        if (!empty($adminPermissionsSidebar[$k]['visualizar'])) {
            return true;
        }
    }
    return false;
};
?>
<?php if ($secCan(['dashboard'])): ?>
<a href="<?= $urlBase ?>/admin/dashboard" class="flex items-center px-4 py-3 <?= $cur === 'dashboard' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200">
    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
    </svg>
    <span class="sidebar-text">Dashboard</span>
</a>
<?php endif; ?>
<?php if ($secCan(['alunos'])): ?>
<a href="<?= $urlBase ?>/admin/students" class="flex items-center px-4 py-3 <?= $cur === 'students' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200">
    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
    </svg>
    <span class="sidebar-text">Alunos</span>
</a>
<?php endif; ?>

<?php if ($secCan(['ano_letivo', 'curso', 'series', 'matriz_curricular', 'regras_academicas', 'turmas', 'transferencia'])): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cur === 'academico' ? 'bg-white/20' : '' ?>">
        <a href="<?= $urlBase ?>/admin/academico" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-solid fa-book-open w-5 h-5 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text">Acadêmico</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('sec-academico')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="sec-academico-arrow" class="w-4 h-4 flex-shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="sec-academico-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php if ($secCan(['ano_letivo'])): ?>
        <a href="<?= $urlBase ?>/admin/ano-letivo" class="flex items-center px-4 py-2 <?= $cur === 'ano_letivo' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-regular fa-calendar w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Ano Letivo</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['curso'])): ?>
        <a href="<?= $urlBase ?>/admin/curso" class="flex items-center px-4 py-2 <?= $cur === 'curso' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-graduation-cap w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Curso</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['transferencia'])): ?>
        <a href="<?= $urlBase ?>/admin/students/remanejamento" class="flex items-center px-4 py-2 <?= $curMovimentacao ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h10M7 7l3-3M7 7l3 3M17 17H7m10 0l-3-3m3 3l-3 3"></path>
            </svg>
            <span class="sidebar-text text-sm">Movimentação de alunos</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['series'])): ?>
        <a href="<?= $urlBase ?>/admin/serie" class="flex items-center px-4 py-2 <?= $cur === 'serie' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-layer-group w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Série</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['matriz_curricular'])): ?>
        <a href="<?= $urlBase ?>/admin/matrizes-curriculares" class="flex items-center px-4 py-2 <?= $cur === 'matriz-curricular' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-sitemap w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Matriz Curricular</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['regras_academicas'])): ?>
        <a href="<?= $urlBase ?>/admin/regras-academicas" class="flex items-center px-4 py-2 <?= $cur === 'regras-academicas' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-scale-balanced w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Regras Acadêmicas</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['resultados_finais'])): ?>
        <a href="<?= $urlBase ?>/admin/resultados-finais" class="flex items-center px-4 py-2 <?= $cur === 'resultados-finais' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-clipboard-check w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Resultados Finais</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['turmas'])): ?>
        <a href="<?= $urlBase ?>/admin/turmas" class="flex items-center px-4 py-2 <?= $cur === 'turmas' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-school w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Turmas</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['salas'])): ?>
        <a href="<?= $urlBase ?>/admin/salas" class="flex items-center px-4 py-2 <?= $cur === 'salas' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-door-open w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Salas / Ambientes</span>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($secCan(['exercicios', 'provas_online', 'jornadas_aluno', 'redacao_professor'])): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cur === 'avaliacoes' ? 'bg-white/20' : '' ?>">
        <a href="<?= $urlBase ?>/admin/avaliacoes" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-regular fa-clipboard w-5 h-5 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text">Avaliações</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('sec-avaliacoes')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="sec-avaliacoes-arrow" class="w-4 h-4 flex-shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="sec-avaliacoes-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php if ($secCan(['provas_online'])): ?>
        <a href="<?= $urlBase ?>/admin/provas" class="flex items-center px-4 py-2 <?= ($cur === 'provas' || $cur === 'provas_blocos') ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-regular fa-clipboard w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Avaliações/Notas</span>
        </a>
        <a href="<?= $urlBase ?>/admin/blocos-modelo" class="flex items-center px-4 py-2 text-purple-100 hover:bg-white/20 hover:text-white rounded-lg transition-all duration-200">
            <i class="fa-solid fa-cubes w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Blocos Modelo</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['exercicios'])): ?>
        <a href="<?= $urlBase ?>/admin/exercises" class="flex items-center px-4 py-2 <?= $cur === 'exercises' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-check-double w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Exercícios</span>
        </a>
        <?php endif; ?>
        <?php require_once __DIR__ . '/../../../Core/LayoutHelper.php'; require_once __DIR__ . '/../../../Core/FeatureGate.php'; ?>
        <?php if (FeatureGate::isModuleEnabled('jornadas') && $secCan(['jornadas_aluno'])): ?>
        <details class="sidebar-nav-item" <?= in_array($cur, ['journeys', 'journeys_relatorio'], true) ? 'open' : '' ?>>
            <summary class="flex items-center px-4 py-2 <?= in_array($cur, ['journeys', 'journeys_relatorio'], true) ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 list-none cursor-pointer">
                <i class="fa-solid fa-route w-4 h-4 mr-3 flex-shrink-0"></i>
                <span class="sidebar-text text-sm flex-1">Jornada do Aluno</span>
                <i class="fa-solid fa-chevron-down text-xs"></i>
            </summary>
            <div class="ml-6 mt-1 space-y-1">
                <a href="<?= $urlBase ?>/admin/jornadas" class="flex items-center px-4 py-2 <?= $cur === 'journeys' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 text-sm">
                    <span class="sidebar-text text-sm">Listagem</span>
                </a>
                <a href="<?= $urlBase ?>/admin/jornadas/relatorio" class="flex items-center px-4 py-2 <?= $cur === 'journeys_relatorio' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 text-sm">
                    <span class="sidebar-text text-sm">Relatório</span>
                </a>
            </div>
        </details>
        <?php endif; ?>
        <?php if ($secCan(['redacao_professor'])): ?>
        <details class="sidebar-nav-item" <?= in_array($cur, ['essays_teacher', 'essays_teacher_report'], true) ? 'open' : '' ?>>
            <summary class="flex items-center px-4 py-2 <?= in_array($cur, ['essays_teacher', 'essays_teacher_report'], true) ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 list-none cursor-pointer">
                <i class="fa-solid fa-pen-to-square w-4 h-4 mr-3 flex-shrink-0"></i>
                <span class="sidebar-text text-sm flex-1">Jornada da Redação</span>
                <i class="fa-solid fa-chevron-down text-xs"></i>
            </summary>
            <div class="ml-6 mt-1 space-y-1">
                <a href="<?= $urlBase ?>/admin/redacao-professor" class="flex items-center px-4 py-2 <?= $cur === 'essays_teacher' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 text-sm">
                    <span class="sidebar-text text-sm">Listagem</span>
                </a>
                <a href="<?= $urlBase ?>/admin/redacao-professor/relatorio" class="flex items-center px-4 py-2 <?= $cur === 'essays_teacher_report' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 text-sm">
                    <span class="sidebar-text text-sm">Relatório</span>
                </a>
            </div>
        </details>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($secCan(['grade_horaria', 'materias', 'ocorrencias', 'faltas', 'presenca', 'diario_classe', 'conselho_classe', 'censo_escolar', 'saude_academica', 'almoxarifado', 'patrimonio', 'modelos_documentos'])): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cur === 'gestao_escolar' ? 'bg-white/20' : '' ?>">
        <a href="<?= $urlBase ?>/admin/gestao-escolar" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-regular fa-chart-bar w-5 h-5 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text">Gestão Escolar</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('sec-gestao-escolar')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="sec-gestao-escolar-arrow" class="w-4 h-4 flex-shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="sec-gestao-escolar-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php if ($secCan(['faltas'])): ?>
        <a href="<?= $urlBase ?>/admin/faltas" class="flex items-center px-4 py-2 <?= $cur === 'faltas' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-regular fa-clipboard w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Faltas</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['presenca']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('presenca'))): ?>
        <a href="<?= $urlBase ?>/admin/presenca" class="flex items-center px-4 py-2 <?= $cur === 'presenca' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-right-to-bracket w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Presença</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['diario_classe']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('diario_classe'))): ?>
        <a href="<?= $urlBase ?>/admin/diario" class="flex items-center px-4 py-2 <?= $cur === 'diario_classe' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 12h6m-6 4h6"></path></svg>
            <span class="sidebar-text">Diário de Classe</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['conselho_classe']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('conselho_classe'))): ?>
        <a href="<?= $urlBase ?>/admin/conselhos" class="flex items-center px-4 py-2 <?= $cur === 'conselho_classe' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-chalkboard-user w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Conselho de Classe</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['censo_escolar']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('censo_escolar'))): ?>
        <a href="<?= $urlBase ?>/admin/censo" class="flex items-center px-4 py-2 <?= $cur === 'censo_escolar' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-school-flag w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Censo Escolar</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['grade_horaria'])): ?>
        <a href="<?= $urlBase ?>/admin/grade-horaria" class="flex items-center px-4 py-2 <?= $cur === 'grade_horaria' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-regular fa-calendar-days w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Grade Horária</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['materias'])): ?>
        <a href="<?= $urlBase ?>/admin/componentes-curriculares" class="flex items-center px-4 py-2 <?= $cur === 'componentes-curriculares' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-book w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Componentes Curriculares</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['unidades'])): ?>
        <a href="<?= $urlBase ?>/admin/unidades" class="flex items-center px-4 py-2 <?= $cur === 'unidades' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-building w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Instituição</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['modelos_documentos'])): ?>
        <a href="<?= $urlBase ?>/admin/modelos-documentos" class="flex items-center px-4 py-2 <?= $cur === 'modelos_documentos' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-file-contract w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Layout de documentos</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['ocorrencias']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('ocorrencias'))): ?>
        <a href="<?= $urlBase ?>/admin/ocorrencias" class="flex items-center px-4 py-2 <?= $cur === 'ocorrencias' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-regular fa-clock w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Ocorrências</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['saude_academica'])): ?>
        <a href="<?= $urlBase ?>/admin/saude-academica" class="flex items-center px-4 py-2 <?= $cur === 'saude_academica' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-heart-pulse w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Saúde Acadêmica</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['almoxarifado'])): ?>
        <a href="<?= $urlBase ?>/admin/almoxarifado" class="flex items-center px-4 py-2 <?= $cur === 'almoxarifado' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-boxes-stacked w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Almoxarifado</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['patrimonio'])): ?>
        <a href="<?= $urlBase ?>/admin/patrimonio" class="flex items-center px-4 py-2 <?= $cur === 'patrimonio' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <i class="fa-solid fa-barcode w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Patrimônio</span>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($secCan(['professores'])): ?>
<div class="menu-group">
    <button type="button" onclick="toggleMenuGroup('sec-usuarios')" class="w-full flex items-center justify-between px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
        <div class="flex items-center min-w-0">
            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
            </svg>
            <span class="sidebar-text">Pessoas</span>
        </div>
        <svg id="sec-usuarios-arrow" class="w-5 h-5 flex-shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>
    <div id="sec-usuarios-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <a href="<?= $urlBase ?>/admin/teachers" class="flex items-center px-4 py-2 <?= $cur === 'teachers' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
            <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            <span class="sidebar-text text-sm">Professores</span>
        </a>
    </div>
</div>
<?php endif; ?>
