<?php
/** Menu lateral reduzido: perfil Secretaria (@see AdminSecretariaAccess) */
if (!class_exists('AdminSecretariaAccess')) {
    require_once __DIR__ . '/../../../Core/AdminSecretariaAccess.php';
}
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
$linkCls = static function (bool $ativo): string {
    return 'flex items-center px-4 py-2 ' . ($ativo
        ? 'text-white bg-white/20'
        : 'text-purple-100 hover:bg-white/20 hover:text-white') . ' rounded-lg transition-all duration-200';
};
$academicoOpen = in_array($cur, [
    'academico', 'students', 'ano_letivo', 'componentes-curriculares', 'curso',
    'grade_horaria', 'matriz-curricular', 'teachers', 'regras-academicas',
    'salas', 'serie', 'turmas',
], true);
$avaliacoesOpen = in_array($cur, [
    'avaliacoes', 'provas', 'provas_blocos', 'exercises',
    'journeys', 'journeys_relatorio', 'essays_teacher', 'essays_teacher_report',
], true);
$gestaoOpen = in_array($cur, [
    'gestao_escolar', 'censo_escolar', 'conselho_classe', 'diario_classe',
    'faltas', 'presenca', 'modelos_documentos', 'ocorrencias',
    'almoxarifado', 'patrimonio', 'resultados-finais', 'saude_academica',
], true) || $curMovimentacao;
?>
<?php if ($secCan(['dashboard'])): ?>
<a href="<?= $urlBase ?>/admin/dashboard" class="flex items-center px-4 py-3 <?= $cur === 'dashboard' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200">
    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
    </svg>
    <span class="sidebar-text">Dashboard</span>
</a>
<?php endif; ?>

<?php if ($secCan(['alunos', 'ano_letivo', 'curso', 'series', 'matriz_curricular', 'regras_academicas', 'turmas', 'salas', 'professores', 'grade_horaria', 'materias'])): ?>
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
    <div id="sec-academico-submenu" class="<?= $academicoOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php if ($secCan(['alunos'])): ?>
        <a href="<?= $urlBase ?>/admin/students" class="<?= $linkCls($cur === 'students') ?>">
            <i class="fa-solid fa-user-graduate w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Alunos</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['ano_letivo'])): ?>
        <a href="<?= $urlBase ?>/admin/ano-letivo" class="<?= $linkCls($cur === 'ano_letivo') ?>">
            <i class="fa-regular fa-calendar w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Ano Letivo</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['materias'])): ?>
        <a href="<?= $urlBase ?>/admin/componentes-curriculares" class="<?= $linkCls($cur === 'componentes-curriculares') ?>">
            <i class="fa-solid fa-book w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Componentes Curriculares</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['curso'])): ?>
        <a href="<?= $urlBase ?>/admin/curso" class="<?= $linkCls($cur === 'curso') ?>">
            <i class="fa-solid fa-graduation-cap w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Curso</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['grade_horaria'])): ?>
        <a href="<?= $urlBase ?>/admin/grade-horaria" class="<?= $linkCls($cur === 'grade_horaria') ?>">
            <i class="fa-regular fa-calendar-days w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Grade Horária</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['matriz_curricular'])): ?>
        <a href="<?= $urlBase ?>/admin/matrizes-curriculares" class="<?= $linkCls($cur === 'matriz-curricular') ?>">
            <i class="fa-solid fa-sitemap w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Matriz Curricular</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['professores'])): ?>
        <a href="<?= $urlBase ?>/admin/teachers" class="<?= $linkCls($cur === 'teachers') ?>">
            <i class="fa-solid fa-chalkboard-user w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Professores</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['regras_academicas'])): ?>
        <a href="<?= $urlBase ?>/admin/regras-academicas" class="<?= $linkCls($cur === 'regras-academicas') ?>">
            <i class="fa-solid fa-scale-balanced w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Regras Acadêmicas</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['salas']) && AdminSecretariaAccess::requestPathIsAllowed('/admin/salas')): ?>
        <a href="<?= $urlBase ?>/admin/salas" class="<?= $linkCls($cur === 'salas') ?>">
            <i class="fa-solid fa-door-open w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Salas / Ambientes</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['series'])): ?>
        <a href="<?= $urlBase ?>/admin/serie" class="<?= $linkCls($cur === 'serie') ?>">
            <i class="fa-solid fa-layer-group w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Série</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['turmas'])): ?>
        <a href="<?= $urlBase ?>/admin/turmas" class="<?= $linkCls($cur === 'turmas') ?>">
            <i class="fa-solid fa-school w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Turmas</span>
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
    <div id="sec-avaliacoes-submenu" class="<?= $avaliacoesOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php if ($secCan(['provas_online']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('professor_provas'))): ?>
        <a href="<?= $urlBase ?>/admin/provas" class="<?= $linkCls($cur === 'provas' || $cur === 'provas_blocos') ?>">
            <i class="fa-regular fa-clipboard w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Avaliações/Notas</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['redacao_professor']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('redacao_configuravel'))): ?>
        <a href="<?= $urlBase ?>/admin/redacao-professor" class="<?= $linkCls(in_array($cur, ['essays_teacher', 'essays_teacher_report'], true)) ?>">
            <i class="fa-solid fa-pen-to-square w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Jornada da Redação</span>
        </a>
        <?php endif; ?>
        <?php require_once __DIR__ . '/../../../Core/LayoutHelper.php'; require_once __DIR__ . '/../../../Core/FeatureGate.php'; ?>
        <?php if (FeatureGate::isModuleEnabled('jornadas') && $secCan(['jornadas_aluno'])): ?>
        <a href="<?= $urlBase ?>/admin/jornadas" class="<?= $linkCls(in_array($cur, ['journeys', 'journeys_relatorio'], true)) ?>">
            <i class="fa-solid fa-route w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Jornada do Aluno</span>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($secCan(['faltas', 'presenca', 'diario_classe', 'conselho_classe', 'censo_escolar', 'ocorrencias', 'saude_academica', 'almoxarifado', 'patrimonio', 'modelos_documentos', 'resultados_finais', 'transferencia'])): ?>
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
    <div id="sec-gestao-escolar-submenu" class="<?= $gestaoOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php if ($secCan(['censo_escolar']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('censo_escolar'))): ?>
        <a href="<?= $urlBase ?>/admin/censo" class="<?= $linkCls($cur === 'censo_escolar') ?>">
            <i class="fa-solid fa-school-flag w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Censo Escolar</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['conselho_classe']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('conselho_classe'))): ?>
        <a href="<?= $urlBase ?>/admin/conselhos" class="<?= $linkCls($cur === 'conselho_classe') ?>">
            <i class="fa-solid fa-chalkboard-user w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Conselho de Classe</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['diario_classe']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('diario_classe'))): ?>
        <div class="flex items-center rounded-lg <?= $cur === 'diario_classe' ? 'bg-white/20' : '' ?>">
            <a href="<?= $urlBase ?>/admin/diario" class="flex-1 <?= $linkCls($cur === 'diario_classe') ?>">
                <i class="fa-regular fa-address-book w-4 h-4 mr-3 flex-shrink-0"></i>
                <span class="sidebar-text text-sm">Diário de Classe</span>
            </a>
            <button type="button" onclick="toggleNestedMenu('diario-classe')" class="px-2 py-2 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                <svg id="diario-classe-arrow" class="w-3 h-3 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
        </div>
        <div id="diario-classe-nested" class="<?= !empty($diarioNestedOpen) ? '' : 'hidden' ?> ml-6 mt-1 space-y-1 border-l border-white/20 pl-2">
            <?php if ($secCan(['faltas'])): ?>
            <a href="<?= $urlBase ?>/admin/faltas" class="<?= $linkCls($cur === 'faltas') ?>">
                <span class="sidebar-text text-sm">Faltas</span>
            </a>
            <?php endif; ?>
            <?php if ($secCan(['presenca']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('presenca'))): ?>
            <a href="<?= $urlBase ?>/admin/presenca" class="<?= $linkCls($cur === 'presenca') ?>">
                <span class="sidebar-text text-sm">Presença</span>
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <?php if ($secCan(['faltas'])): ?>
        <a href="<?= $urlBase ?>/admin/faltas" class="<?= $linkCls($cur === 'faltas') ?>">
            <i class="fa-regular fa-clipboard w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Faltas</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['presenca']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('presenca'))): ?>
        <a href="<?= $urlBase ?>/admin/presenca" class="<?= $linkCls($cur === 'presenca') ?>">
            <i class="fa-solid fa-right-to-bracket w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Presença</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($secCan(['modelos_documentos'])): ?>
        <a href="<?= $urlBase ?>/admin/modelos-documentos" class="<?= $linkCls($cur === 'modelos_documentos') ?>">
            <i class="fa-solid fa-file-contract w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Layout de documentos</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['transferencia'])): ?>
        <a href="<?= $urlBase ?>/admin/students/remanejamento" class="<?= $linkCls($curMovimentacao) ?>">
            <i class="fa-solid fa-people-arrows w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Movimentação de alunos</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['ocorrencias']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('ocorrencias'))): ?>
        <a href="<?= $urlBase ?>/admin/ocorrencias" class="<?= $linkCls($cur === 'ocorrencias') ?>">
            <i class="fa-regular fa-clock w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Ocorrências</span>
        </a>
        <?php endif; ?>
        <?php
        $secAlmoxarifadoOk = $secCan(['almoxarifado']) && class_exists('AdminSecretariaAccess') && AdminSecretariaAccess::requestPathIsAllowed('/admin/almoxarifado');
        $secPatrimonioOk = $secCan(['patrimonio']) && class_exists('AdminSecretariaAccess') && AdminSecretariaAccess::requestPathIsAllowed('/admin/patrimonio');
        if ($secAlmoxarifadoOk || $secPatrimonioOk):
        ?>
        <button type="button" onclick="toggleNestedMenu('recursos-fisicos')" class="w-full flex items-center px-4 py-2 <?= !empty($recursosNestedOpen) ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 text-left">
            <i class="fa-solid fa-boxes-stacked w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm flex-1">Recursos Físicos</span>
            <svg id="recursos-fisicos-arrow" class="w-3 h-3 flex-shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
        <div id="recursos-fisicos-nested" class="<?= !empty($recursosNestedOpen) ? '' : 'hidden' ?> ml-6 mt-1 space-y-1 border-l border-white/20 pl-2">
            <?php if ($secAlmoxarifadoOk): ?>
            <a href="<?= $urlBase ?>/admin/almoxarifado" class="<?= $linkCls($cur === 'almoxarifado') ?>">
                <span class="sidebar-text text-sm">Almoxarifado</span>
            </a>
            <?php endif; ?>
            <?php if ($secPatrimonioOk): ?>
            <a href="<?= $urlBase ?>/admin/patrimonio" class="<?= $linkCls($cur === 'patrimonio') ?>">
                <span class="sidebar-text text-sm">Patrimônio</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($secCan(['resultados_finais'])): ?>
        <a href="<?= $urlBase ?>/admin/resultados-finais" class="<?= $linkCls($cur === 'resultados-finais') ?>">
            <i class="fa-solid fa-clipboard-check w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Resultados Finais</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['saude_academica']) && class_exists('AdminSecretariaAccess') && AdminSecretariaAccess::requestPathIsAllowed('/admin/saude-academica')): ?>
        <a href="<?= $urlBase ?>/admin/saude-academica" class="<?= $linkCls($cur === 'saude_academica') ?>">
            <i class="fa-solid fa-heart-pulse w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Saúde Acadêmica</span>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
