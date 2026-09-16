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
$modOn = is_callable($modOn ?? null) ? $modOn : static function (string $key): bool {
    return !class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled($key);
};
$academicoOpen = in_array($cur, [
    'academico', 'students', 'ano_letivo', 'calendario_letivo', 'componentes-curriculares',
    'agrupamentos-componentes', 'curso', 'cursos-series', 'grade_horaria', 'matriz-curricular',
    'teachers', 'salas', 'serie', 'turmas', 'tipos_avaliacao', 'boletins', 'boletim_guia',
    'grupos-regras-notas', 'quadros-notas', 'regras-academicas', 'implantar-academico',
    'provas', 'provas_blocos',
], true);
$rotinaOpen = in_array($cur, [
    'rotina', 'exercises',
    'diario_classe', 'faltas', 'presenca', 'frequencia',
    'ocorrencias',
], true);
$avaliacoesOpen = $rotinaOpen;
$pedagogicoOpen = in_array($cur, [
    'pedagogico',
    'journeys', 'journeys_relatorio', 'essays_teacher', 'essays_teacher_report',
], true);
$fechamentoOpen = in_array($cur, [
    'fechamento', 'homologacoes', 'documentos-periodo', 'resultados-finais',
    'conselho_classe',
], true);
$secretariaNavOpen = in_array($cur, [
    'gestao_escolar', 'secretaria', 'censo_escolar', 'modelos_documentos',
    'vida_escolar', 'vida_escolar_oficios', 'almoxarifado', 'patrimonio',
], true) || $curMovimentacao;
$paineisOpen = in_array($cur, [
    'paineis', 'dashboard', 'saude_academica', 'relatorios', 'reports_boletim_coordenacao',
], true);
$gestaoOpen = $secretariaNavOpen;
?>
<?php if ($secCan(['alunos', 'ano_letivo', 'curso', 'series', 'matriz_curricular', 'regras_academicas', 'agrupamentos_componentes', 'turmas', 'salas', 'professores', 'grade_horaria', 'materias', 'grupos_regras_notas', 'configuracao_boletim', 'provas_online'])): ?>
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
        <?php if ($secCan(['configuracao_boletim']) && $modOn('boletim')): ?>
        <a href="<?= $urlBase ?>/admin/implantar-academico" class="<?= $linkCls($cur === 'implantar-academico') ?>">
            <i class="fa-solid fa-list-check w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Implantar acadêmico</span>
        </a>
        <?php endif; ?>
        <?php $menu_subcab_titulo = 'Estrutura'; $menu_subcab_ordenar = true; $menu_subcab_id = 'estrutura'; $menu_subcab_aberto = !empty($estruturaNestedOpen); require __DIR__ . '/admin_sidebar_subcabecalho.php'; ?>
        <div id="estrutura-nested" class="<?= !empty($estruturaNestedOpen) ? '' : 'hidden' ?>">
        <div class="sidebar-sublista">
        <?php if ($secCan(['ano_letivo'])): ?>
        <a href="<?= $urlBase ?>/admin/ano-letivo" class="<?= $linkCls($cur === 'ano_letivo') ?>">
            <i class="fa-regular fa-calendar w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Ano Letivo</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['curso']) || $secCan(['series'])): ?>
        <a href="<?= $urlBase ?>/admin/cursos-series" class="<?= $linkCls(in_array($cur, ['curso', 'serie', 'cursos-series'], true)) ?>">
            <i class="fa-solid fa-graduation-cap w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Cursos e Séries</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['materias']) && $modOn('componentes_curriculares')): ?>
        <a href="<?= $urlBase ?>/admin/componentes-curriculares" class="<?= $linkCls($cur === 'componentes-curriculares') ?>">
            <i class="fa-solid fa-book w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Componentes Curriculares</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['matriz_curricular']) && $modOn('matriz_curricular')): ?>
        <a href="<?= $urlBase ?>/admin/matrizes-curriculares" class="<?= $linkCls($cur === 'matriz-curricular') ?>">
            <i class="fa-solid fa-sitemap w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Matriz Curricular</span>
        </a>
        <?php endif; ?>
        </div>
        </div>
        <?php $menu_subcab_titulo = 'Pessoas e turmas'; $menu_subcab_periodo = ''; $menu_subcab_ordenar = true; $menu_subcab_id = 'pessoas-turmas'; $menu_subcab_aberto = !empty($pessoasNestedOpen); require __DIR__ . '/admin_sidebar_subcabecalho.php'; ?>
        <div id="pessoas-turmas-nested" class="<?= !empty($pessoasNestedOpen) ? '' : 'hidden' ?>">
        <div class="sidebar-sublista">
        <?php if ($secCan(['professores'])): ?>
        <a href="<?= $urlBase ?>/admin/teachers" class="<?= $linkCls($cur === 'teachers') ?>">
            <i class="fa-solid fa-chalkboard-user w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Professores</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['alunos'])): ?>
        <a href="<?= $urlBase ?>/admin/students" class="<?= $linkCls($cur === 'students') ?>">
            <i class="fa-solid fa-user-graduate w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Alunos</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['turmas'])): ?>
        <a href="<?= $urlBase ?>/admin/turmas" class="<?= $linkCls($cur === 'turmas') ?>">
            <i class="fa-solid fa-school w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Turmas</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['grade_horaria']) && $modOn('grade_horaria')): ?>
        <a href="<?= $urlBase ?>/admin/grade-horaria" class="<?= $linkCls($cur === 'grade_horaria') ?>">
            <i class="fa-regular fa-calendar-days w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Grade Horária</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['salas']) && $modOn('salas') && AdminSecretariaAccess::requestPathIsAllowed('/admin/salas')): ?>
        <a href="<?= $urlBase ?>/admin/salas" class="<?= $linkCls($cur === 'salas') ?>">
            <i class="fa-solid fa-door-open w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Salas / Ambientes</span>
        </a>
        <?php endif; ?>
        </div>
        </div>
        <?php $menu_subcab_titulo = 'Como a escola avalia'; $menu_subcab_periodo = ''; $menu_subcab_ordenar = true; $menu_subcab_id = 'como-avalia'; $menu_subcab_aberto = !empty($avaliaNestedOpen); require __DIR__ . '/admin_sidebar_subcabecalho.php'; ?>
        <div id="como-avalia-nested" class="<?= !empty($avaliaNestedOpen) ? '' : 'hidden' ?>">
        <div class="sidebar-sublista">
        <?php if ($secCan(['regras_academicas']) && $modOn('regras_academicas')): ?>
        <a href="<?= $urlBase ?>/admin/regras-academicas" class="<?= $linkCls($cur === 'regras-academicas') ?>">
            <i class="fa-solid fa-scale-balanced w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Regras de Aprovação</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['provas_online']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('professor_provas'))): ?>
        <a href="<?= $urlBase ?>/admin/provas/tipos-avaliacao" class="<?= $linkCls($cur === 'tipos_avaliacao') ?>">
            <i class="fa-solid fa-tags w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Tipos de Nota</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['grupos_regras_notas']) && $modOn('grupos_regras_notas')): ?>
        <a href="<?= $urlBase ?>/admin/quadros-notas" class="<?= $linkCls(in_array($cur, ['grupos-regras-notas', 'quadros-notas'], true)) ?>">
            <i class="fa-solid fa-layer-group w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Quadro de Notas</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['provas_online']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('professor_provas'))): ?>
        <a href="<?= $urlBase ?>/admin/provas" class="<?= $linkCls($cur === 'provas' || $cur === 'provas_blocos') ?>">
            <i class="fa-regular fa-clipboard w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Lançamento de Notas</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['configuracao_boletim']) && $modOn('boletim')): ?>
        <a href="<?= $urlBase ?>/admin/boletins" class="<?= $linkCls($cur === 'boletins') ?>">
            <i class="fa-solid fa-file-circle-check w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Modelo de Boletim</span>
        </a>
        <?php endif; ?>
        </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($secCan(['diario_classe', 'faltas', 'presenca', 'ocorrencias'])): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cur === 'rotina' ? 'bg-white/20' : '' ?>">
        <a href="<?= $urlBase ?>/admin/rotina" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-regular fa-clipboard w-5 h-5 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text">Rotina</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('sec-rotina')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="sec-rotina-arrow" class="w-4 h-4 flex-shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="sec-rotina-submenu" class="<?= $rotinaOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php $menu_subcab_titulo = 'O dia a dia'; $menu_subcab_periodo = ''; $menu_subcab_ordenar = true; require __DIR__ . '/admin_sidebar_subcabecalho.php'; ?>
        <div class="sidebar-sublista">
        <?php if ($secCan(['diario_classe']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('diario_classe'))): ?>
        <a href="<?= $urlBase ?>/admin/diario" class="<?= $linkCls($cur === 'diario_classe') ?>">
            <i class="fa-regular fa-address-book w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Diário de Classe</span>
        </a>
        <?php endif; ?>
        <?php if (($secCan(['faltas']) && $modOn('faltas')) || ($secCan(['presenca']) && $modOn('presenca'))): ?>
        <a href="<?= $urlBase ?>/admin/frequencia" class="<?= $linkCls(in_array($cur, ['frequencia', 'faltas', 'presenca'], true)) ?>">
            <i class="fa-solid fa-user-check w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Frequência</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['ocorrencias']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('ocorrencias'))): ?>
        <a href="<?= $urlBase ?>/admin/ocorrencias" class="<?= $linkCls($cur === 'ocorrencias') ?>">
            <i class="fa-regular fa-clock w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Ocorrências</span>
        </a>
        <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($secCan(['redacao_professor', 'jornadas_aluno'])): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cur === 'pedagogico' ? 'bg-white/20' : '' ?>">
        <a href="<?= $urlBase ?>/admin/pedagogico" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-regular fa-id-card w-5 h-5 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text">Pedagógico</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('sec-pedagogico')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="sec-pedagogico-arrow" class="w-4 h-4 flex-shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="sec-pedagogico-submenu" class="<?= $pedagogicoOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php require_once __DIR__ . '/../../../Core/LayoutHelper.php'; require_once __DIR__ . '/../../../Core/FeatureGate.php'; ?>
        <?php if ($secCan(['redacao_professor']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('redacao_configuravel'))): ?>
        <a href="<?= $urlBase ?>/admin/redacao-professor" class="<?= $linkCls(in_array($cur, ['essays_teacher', 'essays_teacher_report'], true)) ?>">
            <i class="fa-solid fa-pen-to-square w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Jornada da Redação</span>
        </a>
        <?php endif; ?>
        <?php if (FeatureGate::isModuleEnabled('jornadas') && $secCan(['jornadas_aluno'])): ?>
        <a href="<?= $urlBase ?>/admin/jornadas" class="<?= $linkCls(in_array($cur, ['journeys', 'journeys_relatorio'], true)) ?>">
            <i class="fa-solid fa-route w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Jornada do Aluno</span>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($secCan(['resultados_finais', 'conselho_classe'])): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= !empty($fechamentoOpen) ? 'bg-white/20' : '' ?>">
        <a href="<?= $urlBase ?>/admin/<?= ($secCan(['resultados_finais']) && $modOn('resultados_finais')) ? 'fechamento' : 'conselhos' ?>" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-solid fa-clipboard-check w-5 h-5 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text">Fechamento</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('sec-fechamento')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="sec-fechamento-arrow" class="w-4 h-4 flex-shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="sec-fechamento-submenu" class="<?= !empty($fechamentoOpen) ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php $menu_subcab_titulo = 'Fim de período'; $menu_subcab_periodo = ''; $menu_subcab_ordenar = true; require __DIR__ . '/admin_sidebar_subcabecalho.php'; ?>
        <div class="sidebar-sublista">
        <?php if ($secCan(['resultados_finais']) && $modOn('resultados_finais')): ?>
        <a href="<?= $urlBase ?>/admin/fechamento" class="<?= $linkCls(in_array($cur, ['fechamento', 'homologacoes', 'documentos-periodo'], true)) ?>">
            <i class="fa-solid fa-flag-checkered w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Painel de Fechamento</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['conselho_classe']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('conselho_classe'))): ?>
        <a href="<?= $urlBase ?>/admin/conselhos" class="<?= $linkCls($cur === 'conselho_classe') ?>">
            <i class="fa-solid fa-chalkboard-user w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Conselho de Classe</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['resultados_finais']) && $modOn('resultados_finais')): ?>
        <a href="<?= $urlBase ?>/admin/resultados-finais" class="<?= $linkCls($cur === 'resultados-finais') ?>">
            <i class="fa-solid fa-check-double w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Resultados Finais</span>
        </a>
        <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($secCan(['faltas', 'presenca', 'diario_classe', 'censo_escolar', 'modelos_documentos', 'transferencia', 'vida_escolar', 'almoxarifado', 'patrimonio'])): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= in_array($cur, ['gestao_escolar', 'secretaria'], true) ? 'bg-white/20' : '' ?>">
        <a href="<?= $urlBase ?>/admin/gestao-escolar" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-regular fa-folder-open w-5 h-5 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text">Secretaria</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('sec-secretaria')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="sec-secretaria-arrow" class="w-4 h-4 flex-shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="sec-secretaria-submenu" class="<?= $secretariaNavOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php if ($secCan(['transferencia'])): ?>
        <a href="<?= $urlBase ?>/admin/students/remanejamento" class="<?= $linkCls($curMovimentacao) ?>">
            <i class="fa-solid fa-people-arrows w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Movimentação de alunos</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['vida_escolar']) && $modOn('vida_escolar')): ?>
        <a href="<?= $urlBase ?>/admin/vida-escolar" class="<?= $linkCls(in_array($cur, ['vida_escolar', 'vida_escolar_oficios'], true)) ?>">
            <i class="fa-solid fa-scroll w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Vida Escolar</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['modelos_documentos'])): ?>
        <a href="<?= $urlBase ?>/admin/modelos-documentos" class="<?= $linkCls($cur === 'modelos_documentos') ?>">
            <i class="fa-solid fa-file-contract w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Layout de documentos</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['censo_escolar']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('censo_escolar'))): ?>
        <a href="<?= $urlBase ?>/admin/censo" class="<?= $linkCls($cur === 'censo_escolar') ?>">
            <i class="fa-solid fa-school-flag w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Censo Escolar</span>
        </a>
        <?php endif; ?>
        <?php
        $secAlmoxarifadoOk = $modOn('recursos_fisicos') && $secCan(['almoxarifado']) && class_exists('AdminSecretariaAccess') && AdminSecretariaAccess::requestPathIsAllowed('/admin/almoxarifado');
        $secPatrimonioOk = $modOn('recursos_fisicos') && $secCan(['patrimonio']) && class_exists('AdminSecretariaAccess') && AdminSecretariaAccess::requestPathIsAllowed('/admin/patrimonio');
        if ($secAlmoxarifadoOk || $secPatrimonioOk):
        ?>
        <?php $menu_subcab_titulo = 'Outros da escola'; $menu_subcab_periodo = ''; require __DIR__ . '/admin_sidebar_subcabecalho.php'; ?>
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
    </div>
</div>
<?php endif; ?>

<?php if ($secCan(['dashboard', 'saude_academica', 'relatorios_gerais'])): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cur === 'paineis' ? 'bg-white/20' : '' ?>">
        <a href="<?= $urlBase ?>/admin/paineis" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-solid fa-chart-pie w-5 h-5 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text">Painéis</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('sec-paineis')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="sec-paineis-arrow" class="w-4 h-4 flex-shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="sec-paineis-submenu" class="<?= $paineisOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php if ($secCan(['dashboard'])): ?>
        <a href="<?= $urlBase ?>/admin/dashboard" class="<?= $linkCls($cur === 'dashboard') ?>">
            <i class="fa-solid fa-gauge-high w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Dashboard</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['saude_academica']) && $modOn('saude_academica') && class_exists('AdminSecretariaAccess') && AdminSecretariaAccess::requestPathIsAllowed('/admin/saude-academica')): ?>
        <a href="<?= $urlBase ?>/admin/saude-academica" class="<?= $linkCls($cur === 'saude_academica') ?>">
            <i class="fa-solid fa-heart-pulse w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Saúde Acadêmica</span>
        </a>
        <?php endif; ?>
        <?php if ($secCan(['relatorios_gerais'])): ?>
        <a href="<?= $urlBase ?>/admin/relatorios" class="<?= $linkCls(in_array($cur, ['relatorios', 'reports_boletim_coordenacao'], true)) ?>">
            <i class="fa-solid fa-chart-column w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Relatórios</span>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
