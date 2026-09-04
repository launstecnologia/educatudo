<?php
/** Menu lateral completo do admin (exceto perfis financeiro e secretaria). */
if (!class_exists('FeatureGate')) {
    require_once __DIR__ . '/../../../Core/FeatureGate.php';
}
$linkCls = static function (bool $ativo): string {
    return 'flex items-center px-4 py-2 ' . ($ativo
        ? 'text-white bg-white/20'
        : 'text-purple-100 hover:bg-white/20 hover:text-white') . ' rounded-lg transition-all duration-200';
};
$showAvaliacoesGroup = ($canViewSidebar(['inclusao']) && $modOn('inclusao'))
    || $modOn('aluno_provas') || $modOn('professor_provas')
    || $modOn('redacao_configuravel')
    || FeatureGate::isModuleEnabled('jornadas')
    || $modOn('boletim')
    || $canViewSidebar(['relatorios_gerais']);
$showPedagogicoGroup = $modOn('aulas_online')
    || FeatureGate::isModuleEnabled('ead')
    || $modOn('bncc')
    || $modOn('aluno_minicursos')
    || $modOn('professor_planos_aula');
$showSistemaGroup = $canViewSidebar(['avatares_alunos'])
    || $modOn('redacao_configuravel')
    || (($user['perfil_admin'] ?? '') === 'dev');
$showEscolaExpoGroup = FeatureGate::isModuleEnabled('expo_colag') && $canViewSidebar(['expo_colag']);
$nomeMenuEscola = '';
if (class_exists('LayoutHelper')) {
    $nomeMenuEscola = trim((string) LayoutHelper::get('menu_colag_nome', ''));
    if ($nomeMenuEscola === '') {
        $nomeMenuEscola = trim((string) LayoutHelper::getSystemTitle());
    }
}
if ($nomeMenuEscola === '') {
    $nomeMenuEscola = 'Escola';
}
?>
<!-- Dashboard -->
<?php if ($showDashboardMenu): ?>
<a href="<?= URL ?>/admin/dashboard" class="flex items-center px-4 py-3 <?= $cp === 'dashboard' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200">
    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
    </svg>
    <span class="sidebar-text">Dashboard</span>
</a>
<?php endif; ?>
<?php if ($modOn('assistente') && in_array(($user['perfil_admin'] ?? ''), ['dev', 'diretor', 'coordenador'], true)): ?>
<a href="<?= URL ?>/admin/assistente" class="flex items-center px-4 py-3 <?= $cp === 'assistente' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200">
    <i class="fa-solid fa-comments w-5 h-5 mr-3"></i>
    <span class="sidebar-text">Assistente</span>
</a>
<?php endif; ?>

<!-- Acadêmico -->
<?php if ($showAcademicoGroup): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cp === 'academico' ? 'bg-white/20' : '' ?>">
        <a href="<?= URL ?>/admin/academico" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-solid fa-book-open w-5 h-5 mr-3"></i>
            <span class="sidebar-text">Acadêmico</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('academico')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="academico-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="academico-submenu" class="<?= $academicoOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php if ($showAlunosMenu): ?>
        <a href="<?= URL ?>/admin/students" class="<?= $linkCls($cp === 'students') ?>">
            <i class="fa-solid fa-user-graduate w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Alunos</span>
        </a>
        <?php endif; ?>
        <a href="<?= URL ?>/admin/ano-letivo" class="<?= $linkCls($cp === 'ano_letivo') ?>">
            <i class="fa-regular fa-calendar w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Ano Letivo</span>
        </a>
        <?php if ($modOn('calendario_letivo')): ?>
        <a href="<?= URL ?>/admin/calendario-letivo" class="<?= $linkCls($cp === 'calendario_letivo') ?>">
            <i class="fa-solid fa-calendar-check w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Calendário Letivo</span>
        </a>
        <?php endif; ?>
        <?php if ($modOn('componentes_curriculares')): ?>
        <a href="<?= URL ?>/admin/componentes-curriculares" class="<?= $linkCls($cp === 'componentes-curriculares') ?>">
            <i class="fa-solid fa-book w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Componentes Curriculares</span>
        </a>
        <?php endif; ?>
        <a href="<?= URL ?>/admin/curso" class="<?= $linkCls($cp === 'curso') ?>">
            <i class="fa-solid fa-graduation-cap w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Curso</span>
        </a>
        <?php if ($modOn('grade_horaria')): ?>
        <a href="<?= URL ?>/admin/grade-horaria" class="<?= $linkCls($cp === 'grade_horaria') ?>">
            <i class="fa-regular fa-calendar-days w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Grade Horária</span>
        </a>
        <?php endif; ?>
        <?php if ($modOn('matriz_curricular') && $canViewSidebar(['matriz_curricular'])): ?>
        <a href="<?= URL ?>/admin/matrizes-curriculares" class="<?= $linkCls($cp === 'matriz-curricular') ?>">
            <i class="fa-solid fa-sitemap w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Matriz Curricular</span>
        </a>
        <?php endif; ?>
        <?php if ($canViewSidebar(['professores'])): ?>
        <a href="<?= URL ?>/admin/teachers" class="<?= $linkCls($cp === 'teachers') ?>">
            <i class="fa-solid fa-chalkboard-user w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Professores</span>
        </a>
        <?php endif; ?>
        <?php if (class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('agrupamentos_componentes') && $canViewSidebar(['agrupamentos_componentes'])): ?>
        <a href="<?= URL ?>/admin/agrupamentos-componentes" class="<?= $linkCls($cp === 'agrupamentos-componentes') ?>">
            <i class="fa-solid fa-object-group w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Agrupamentos</span>
        </a>
        <?php endif; ?>
        <?php if (class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('grupos_regras_notas') && $canViewSidebar(['grupos_regras_notas'])): ?>
        <a href="<?= URL ?>/admin/grupos-regras-notas" class="<?= $linkCls($cp === 'grupos-regras-notas') ?>">
            <i class="fa-solid fa-layer-group w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Grupos de regras de notas</span>
        </a>
        <?php endif; ?>
        <?php if ($modOn('regras_academicas') && $canViewSidebar(['regras_academicas'])): ?>
        <a href="<?= URL ?>/admin/regras-academicas" class="<?= $linkCls($cp === 'regras-academicas') ?>">
            <i class="fa-solid fa-scale-balanced w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Regras Acadêmicas</span>
        </a>
        <?php endif; ?>
        <?php if ($modOn('salas') && $canViewSidebar(['salas'])): ?>
        <a href="<?= URL ?>/admin/salas" class="<?= $linkCls($cp === 'salas') ?>">
            <i class="fa-solid fa-door-open w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Salas / Ambientes</span>
        </a>
        <?php endif; ?>
        <a href="<?= URL ?>/admin/serie" class="<?= $linkCls($cp === 'serie') ?>">
            <i class="fa-solid fa-layer-group w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Série</span>
        </a>
        <a href="<?= URL ?>/admin/turmas" class="<?= $linkCls($cp === 'turmas') ?>">
            <i class="fa-solid fa-school w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Turmas</span>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Avaliações -->
<?php if ($showAvaliacoesGroup): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cp === 'avaliacoes' ? 'bg-white/20' : '' ?>">
        <a href="<?= URL ?>/admin/avaliacoes" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-regular fa-clipboard w-5 h-5 mr-3"></i>
            <span class="sidebar-text">Avaliações</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('avaliacoes')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="avaliacoes-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="avaliacoes-submenu" class="<?= $avaliacoesOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php if ($canViewSidebar(['inclusao']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('inclusao'))): ?>
        <a href="<?= URL ?>/admin/inclusao/versoes" class="<?= $linkCls($cp === 'inclusao') ?>">
            <i class="fa-solid fa-universal-access w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Avaliação Adaptativa</span>
        </a>
        <?php endif; ?>
        <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('aluno_provas') || LayoutHelper::isModuleEnabled('professor_provas')): ?>
        <a href="<?= URL ?>/admin/provas" class="<?= $linkCls($cp === 'provas' || $cp === 'provas_blocos') ?>">
            <i class="fa-regular fa-clipboard w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Avaliações/Notas</span>
        </a>
        <?php endif; ?>
        <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('redacao_configuravel')): ?>
        <a href="<?= URL ?>/admin/redacao-professor" class="<?= $linkCls(in_array($cp, ['essays_teacher', 'essays_teacher_report'], true)) ?>">
            <i class="fa-solid fa-pen-to-square w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Jornada da Redação</span>
        </a>
        <?php endif; ?>
        <?php if (FeatureGate::isModuleEnabled('jornadas')): ?>
        <a href="<?= URL ?>/admin/jornadas" class="<?= $linkCls(in_array($cp, ['journeys', 'journeys_relatorio'], true)) ?>">
            <i class="fa-solid fa-route w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Jornada do Aluno</span>
        </a>
        <?php endif; ?>
        <?php if ($modOn('boletim')): ?>
        <div class="flex items-center rounded-lg <?= $cp === 'boletim_config' ? 'bg-white/20' : '' ?>">
            <a href="<?= URL ?>/admin/boletim" class="flex-1 <?= $linkCls($cp === 'boletim_config') ?>">
                <i class="fa-regular fa-file-lines w-4 h-4 mr-3"></i>
                <span class="sidebar-text text-sm">Eventos de Notas</span>
            </a>
            <button type="button" onclick="toggleNestedMenu('boletim')" class="px-2 py-2 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                <svg id="boletim-arrow" class="w-3 h-3 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
        </div>
        <div id="boletim-nested" class="<?= $boletimNestedOpen ? '' : 'hidden' ?> ml-6 mt-1 space-y-1 border-l border-white/20 pl-2">
            <a href="<?= URL ?>/admin/boletim-guia" class="<?= $linkCls($cp === 'boletim_guia') ?>">
                <span class="sidebar-text text-sm">Guia do Boletim</span>
            </a>
        </div>
        <?php endif; ?>
        <?php if ($canViewSidebar(['relatorios_gerais'])): ?>
        <a href="<?= URL ?>/admin/relatorios" class="<?= $linkCls(in_array($cp, ['relatorios', 'reports_boletim_coordenacao'], true)) ?>">
            <i class="fa-solid fa-chart-pie w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Relatórios</span>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($showEscolaExpoGroup): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= !empty($escolaOpen) ? 'bg-white/20' : '' ?>">
        <a href="<?= URL ?>/admin/expo-colag" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-solid fa-school w-5 h-5 mr-3"></i>
            <span class="sidebar-text"><?= htmlspecialchars($nomeMenuEscola) ?></span>
        </a>
        <button type="button" onclick="toggleMenuGroup('escola')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="escola-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="escola-submenu" class="<?= !empty($escolaOpen) ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <a href="<?= URL ?>/admin/expo-colag" class="<?= $linkCls($cp === 'expo-colag') ?>">
            <i class="fa-solid fa-flask-vial w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Expo Colag</span>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Comunicação -->
<?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('comunicacao') || $modOn('calendario_escolar')): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cp === 'comunicacao' ? 'bg-white/20' : '' ?>">
        <a href="<?= URL ?>/admin/comunicacao" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-regular fa-comments w-5 h-5 mr-3"></i>
            <span class="sidebar-text">Comunicação</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('comunicacao')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="comunicacao-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="comunicacao-submenu" class="<?= $comunicacaoOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <a href="<?= URL ?>/admin/comunicacao-escolar" class="<?= $linkCls($cp === 'school-communication') ?>">
            <i class="fa-solid fa-envelope-open-text w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Comunicação Escolar</span>
        </a>
        <?php if ($modOn('calendario_escolar')): ?>
        <a href="<?= URL ?>/admin/calendario-escolar" class="<?= $linkCls($cp === 'school-calendar') ?>">
            <i class="fa-regular fa-calendar-days w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Calendário Escolar</span>
        </a>
        <?php endif; ?>
        <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('forum')): ?>
        <div class="flex items-center rounded-lg <?= $forumAtivo ? 'bg-white/20' : '' ?>">
            <a href="<?= URL ?>/forum" class="flex-1 <?= $linkCls($forumAtivo) ?>">
                <i class="fa-regular fa-comments w-4 h-4 mr-3"></i>
                <span class="sidebar-text text-sm">Fórum</span>
            </a>
            <button type="button" onclick="toggleNestedMenu('forum')" class="px-2 py-2 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                <svg id="forum-arrow" class="w-3 h-3 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
        </div>
        <div id="forum-nested" class="<?= $forumNestedOpen ? '' : 'hidden' ?> ml-6 mt-1 space-y-1 border-l border-white/20 pl-2">
            <a href="<?= URL ?>/forum/moderation/reports" class="<?= $linkCls($forumDenunciasAtivo) ?>">
                <span class="sidebar-text text-sm">Denúncias Fórum</span>
            </a>
        </div>
        <?php endif; ?>
        <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('mural_recados')): ?>
        <a href="<?= URL ?>/admin/mural-recados" class="<?= $linkCls($cp === 'mural-recados') ?>">
            <i class="fa-solid fa-bullhorn w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Mural de Recados</span>
        </a>
        <?php endif; ?>
        <div class="flex items-center rounded-lg <?= $cp === 'notifications' ? 'bg-white/20' : '' ?>">
            <a href="<?= URL ?>/admin/notifications" class="flex-1 <?= $linkCls($cp === 'notifications') ?>">
                <i class="fa-regular fa-bell w-4 h-4 mr-3"></i>
                <span class="sidebar-text text-sm">Notificações</span>
            </a>
            <button type="button" onclick="toggleNestedMenu('notificacoes')" class="px-2 py-2 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                <svg id="notificacoes-arrow" class="w-3 h-3 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
        </div>
        <div id="notificacoes-nested" class="<?= $notificacoesNestedOpen ? '' : 'hidden' ?> ml-6 mt-1 space-y-1 border-l border-white/20 pl-2">
            <a href="<?= URL ?>/admin/notificacoes-push" class="<?= $linkCls($cp === 'notificacoes-push') ?>">
                <span class="sidebar-text text-sm">Notificações Push</span>
            </a>
        </div>
        <a href="<?= URL ?>/admin/reunioes/geral" class="<?= $linkCls(in_array($cp, ['reunioes_geral', 'reunioes'], true)) ?>">
            <i class="fa-solid fa-people-group w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Reuniões</span>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Conteúdo -->
<?php
$conteudoArquivosOn = !class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('aluno_arquivos');
$conteudoHitsOn = class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('educa_hits');
$conteudoMaterialOn = !class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('aluno_apostilas');
?>
<?php if ($conteudoArquivosOn || $conteudoHitsOn || $conteudoMaterialOn): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cp === 'conteudo' ? 'bg-white/20' : '' ?>">
        <a href="<?= URL ?>/admin/conteudo" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-regular fa-folder w-5 h-5 mr-3"></i>
            <span class="sidebar-text">Conteúdo</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('conteudo')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="conteudo-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="conteudo-submenu" class="<?= $conteudoOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php if ($conteudoArquivosOn): ?>
        <a href="<?= URL ?>/admin/arquivos" class="<?= $linkCls($cp === 'arquivos') ?>">
            <i class="fa-regular fa-folder w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Arquivos</span>
        </a>
        <?php endif; ?>
        <?php if (class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('educa_hits')): ?>
        <?php require_once __DIR__ . '/../../../Core/EducaHitsConfig.php'; ?>
        <a href="<?= htmlspecialchars(EducaHitsConfig::portalLoginUrl()) ?>" target="_blank" rel="noopener noreferrer" class="flex items-center px-4 py-2 text-purple-100 hover:bg-white/20 hover:text-white rounded-lg transition-all duration-200">
            <i class="fa-solid fa-music w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">EducaHits (portal)</span>
        </a>
        <?php endif; ?>
        <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('aluno_apostilas')): ?>
        <a href="<?= URL ?>/admin/apostilas-ia" class="<?= $linkCls($cp === 'apostilas-ia') ?>">
            <i class="fa-solid fa-wand-magic-sparkles w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Meu Material</span>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Financeiro -->
<?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('financeiro')): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cp === 'financeiro' ? 'bg-white/20' : '' ?>">
        <a href="<?= URL ?>/admin/financeiro-escolar" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-regular fa-money-bill-1 w-5 h-5 mr-3"></i>
            <span class="sidebar-text">Financeiro</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('financeiro-escolar')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="financeiro-escolar-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="financeiro-escolar-submenu" class="<?= in_array($cp, $finPages, true) ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php $renderFinNav($finItems, $cp); ?>
    </div>
</div>
<?php endif; ?>

<!-- Gestão Escolar -->
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cp === 'gestao_escolar' ? 'bg-white/20' : '' ?>">
        <a href="<?= URL ?>/admin/gestao-escolar" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-regular fa-chart-bar w-5 h-5 mr-3"></i>
            <span class="sidebar-text">Gestão Escolar</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('gestao-escolar')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="gestao-escolar-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="gestao-escolar-submenu" class="<?= $gestaoOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php if ($canViewSidebar(['censo_escolar']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('censo_escolar'))): ?>
        <a href="<?= URL ?>/admin/censo" class="<?= $linkCls($cp === 'censo_escolar') ?>">
            <i class="fa-solid fa-school-flag w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Censo Escolar</span>
        </a>
        <?php endif; ?>
        <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('conformidade')): ?>
        <a href="<?= URL ?>/admin/conformidade" class="<?= $linkCls($cp === 'conformidade') ?>">
            <i class="fa-solid fa-clipboard-check w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Conformidade</span>
        </a>
        <?php endif; ?>
        <?php if ($canViewSidebar(['conselho_classe']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('conselho_classe'))): ?>
        <a href="<?= URL ?>/admin/conselhos" class="<?= $linkCls($cp === 'conselho_classe') ?>">
            <i class="fa-solid fa-chalkboard-user w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Conselho de Classe</span>
        </a>
        <?php endif; ?>
        <?php if ($canViewSidebar(['diario_classe']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('diario_classe'))): ?>
        <div class="flex items-center rounded-lg <?= $cp === 'diario_classe' ? 'bg-white/20' : '' ?>">
            <a href="<?= URL ?>/admin/diario" class="flex-1 <?= $linkCls($cp === 'diario_classe') ?>">
                <i class="fa-regular fa-address-book w-4 h-4 mr-3 flex-shrink-0"></i>
                <span class="sidebar-text text-sm">Diário de Classe</span>
            </a>
            <button type="button" onclick="toggleNestedMenu('diario-classe')" class="px-2 py-2 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                <svg id="diario-classe-arrow" class="w-3 h-3 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
        </div>
        <div id="diario-classe-nested" class="<?= $diarioNestedOpen ? '' : 'hidden' ?> ml-6 mt-1 space-y-1 border-l border-white/20 pl-2">
            <?php if ($modOn('faltas')): ?>
            <a href="<?= URL ?>/admin/faltas" class="<?= $linkCls($cp === 'faltas') ?>">
                <span class="sidebar-text text-sm">Faltas</span>
            </a>
            <?php endif; ?>
            <?php if ($canViewSidebar(['presenca']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('presenca'))): ?>
            <a href="<?= URL ?>/admin/presenca" class="<?= $linkCls($cp === 'presenca') ?>">
                <span class="sidebar-text text-sm">Presença</span>
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <?php if ($modOn('faltas')): ?>
        <a href="<?= URL ?>/admin/faltas" class="<?= $linkCls($cp === 'faltas') ?>">
            <i class="fa-regular fa-clipboard w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Faltas</span>
        </a>
        <?php endif; ?>
        <?php if ($canViewSidebar(['presenca']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('presenca'))): ?>
        <a href="<?= URL ?>/admin/presenca" class="<?= $linkCls($cp === 'presenca') ?>">
            <i class="fa-solid fa-right-to-bracket w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Presença</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>
        <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('documentos_institucionais')): ?>
        <div class="flex items-center rounded-lg <?= $cp === 'documentos_institucionais' ? 'bg-white/20' : '' ?>">
            <a href="<?= URL ?>/admin/documentos-institucionais" class="flex-1 <?= $linkCls($cp === 'documentos_institucionais') ?>">
                <i class="fa-solid fa-file-shield w-4 h-4 mr-3 flex-shrink-0"></i>
                <span class="sidebar-text text-sm">Documentos Institucionais</span>
            </a>
            <?php if ((class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('processo_matricula') && $canViewSidebar(['processos_matricula'])) || $canViewSidebar(['modelos_documentos'])): ?>
            <button type="button" onclick="toggleNestedMenu('documentos-institucionais')" class="px-2 py-2 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                <svg id="documentos-institucionais-arrow" class="w-3 h-3 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <?php endif; ?>
        </div>
        <?php if ((class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('processo_matricula') && $canViewSidebar(['processos_matricula'])) || $canViewSidebar(['modelos_documentos'])): ?>
        <div id="documentos-institucionais-nested" class="<?= $documentosNestedOpen ? '' : 'hidden' ?> ml-6 mt-1 space-y-1 border-l border-white/20 pl-2">
            <?php if (class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('processo_matricula') && $canViewSidebar(['processos_matricula'])): ?>
            <a href="<?= URL ?>/admin/configuracao/assinatura-digital" class="<?= $linkCls($cp === 'assinatura_digital') ?>">
                <span class="sidebar-text text-sm">Assinatura Digital</span>
            </a>
            <?php endif; ?>
            <?php if ($canViewSidebar(['modelos_documentos'])): ?>
            <a href="<?= URL ?>/admin/modelos-documentos" class="<?= $linkCls($cp === 'modelos_documentos') ?>">
                <span class="sidebar-text text-sm">Layout de documentos</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <?php if (class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('processo_matricula')): ?>
        <div class="flex items-center rounded-lg <?= $cp === 'enrollment' ? 'bg-white/20' : '' ?>">
            <a href="<?= URL ?>/admin/enrollment" class="flex-1 <?= $linkCls($cp === 'enrollment') ?>">
                <i class="fa-solid fa-file-signature w-4 h-4 mr-3 flex-shrink-0"></i>
                <span class="sidebar-text text-sm">Matrículas</span>
            </a>
            <button type="button" onclick="toggleNestedMenu('matriculas')" class="px-2 py-2 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                <svg id="matriculas-arrow" class="w-3 h-3 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
        </div>
        <div id="matriculas-nested" class="<?= $matriculasNestedOpen ? '' : 'hidden' ?> ml-6 mt-1 space-y-1 border-l border-white/20 pl-2">
            <?php if ($canViewSidebar(['processos_matricula'])): ?>
            <a href="<?= URL ?>/admin/enrollment/config" class="<?= $linkCls($cp === 'enrollment_config') ?>">
                <span class="sidebar-text text-sm">Configuração de Matrícula</span>
            </a>
            <?php endif; ?>
            <?php if ($canViewSidebar(['transferencia'])): ?>
            <a href="<?= URL ?>/admin/students/remanejamento" class="<?= $linkCls($curMovimentacao) ?>">
                <span class="sidebar-text text-sm">Movimentação de alunos</span>
            </a>
            <?php endif; ?>
        </div>
        <?php elseif ($canViewSidebar(['transferencia'])): ?>
        <a href="<?= URL ?>/admin/students/remanejamento" class="<?= $linkCls($curMovimentacao) ?>">
            <i class="fa-solid fa-people-arrows w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Movimentação de alunos</span>
        </a>
        <?php endif; ?>
        <?php if ($canViewSidebar(['ocorrencias']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('ocorrencias'))): ?>
        <a href="<?= URL ?>/admin/ocorrencias" class="<?= $linkCls($cp === 'ocorrencias') ?>">
            <i class="fa-regular fa-clock w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Ocorrências</span>
        </a>
        <?php endif; ?>
        <?php if ($showInventoryGroup): ?>
        <button type="button" onclick="toggleNestedMenu('recursos-fisicos')" class="w-full flex items-center px-4 py-2 <?= $recursosNestedOpen ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 text-left">
            <i class="fa-solid fa-boxes-stacked w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm flex-1">Recursos Físicos</span>
            <svg id="recursos-fisicos-arrow" class="w-3 h-3 flex-shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
        <div id="recursos-fisicos-nested" class="<?= $recursosNestedOpen ? '' : 'hidden' ?> ml-6 mt-1 space-y-1 border-l border-white/20 pl-2">
            <?php if ($canViewSidebar(['almoxarifado'])): ?>
            <a href="<?= URL ?>/admin/almoxarifado" class="<?= $linkCls($cp === 'almoxarifado') ?>">
                <span class="sidebar-text text-sm">Almoxarifado</span>
            </a>
            <?php endif; ?>
            <?php if ($canViewSidebar(['patrimonio'])): ?>
            <a href="<?= URL ?>/admin/patrimonio" class="<?= $linkCls($cp === 'patrimonio') ?>">
                <span class="sidebar-text text-sm">Patrimônio</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($canViewSidebar(['resultados_finais']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('resultados_finais'))): ?>
        <a href="<?= URL ?>/admin/resultados-finais" class="<?= $linkCls($cp === 'resultados-finais') ?>">
            <i class="fa-solid fa-clipboard-check w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Resultados Finais</span>
        </a>
        <?php endif; ?>
        <?php if ($canViewSidebar(['vida_escolar']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('vida_escolar'))): ?>
        <div class="flex items-center rounded-lg <?= in_array($cp, ['vida_escolar', 'vida_escolar_oficios'], true) ? 'bg-white/20' : '' ?>">
            <a href="<?= URL ?>/admin/vida-escolar" class="flex-1 <?= $linkCls(in_array($cp, ['vida_escolar', 'vida_escolar_oficios'], true)) ?>">
                <i class="fa-solid fa-scroll w-4 h-4 mr-3 flex-shrink-0"></i>
                <span class="sidebar-text text-sm">Vida Escolar</span>
            </a>
            <button type="button" onclick="toggleNestedMenu('vida-escolar')" class="px-2 py-2 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                <svg id="vida-escolar-arrow" class="w-3 h-3 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
        </div>
        <div id="vida-escolar-nested" class="<?= !empty($vidaEscolarNestedOpen) ? '' : 'hidden' ?> ml-6 mt-1 space-y-1 border-l border-white/20 pl-2">
            <a href="<?= URL ?>/admin/vida-escolar/oficios" class="<?= $linkCls($cp === 'vida_escolar_oficios') ?>">
                <span class="sidebar-text text-sm">Ofícios</span>
            </a>
        </div>
        <?php endif; ?>
        <?php if ($modOn('saude_academica')): ?>
        <a href="<?= URL ?>/admin/saude-academica" class="<?= $linkCls($cp === 'saude_academica') ?>">
            <i class="fa-solid fa-heart-pulse w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Saúde Acadêmica</span>
        </a>
        <?php endif; ?>
        <div class="flex items-center rounded-lg <?= $cp === 'tudicoins_escola' ? 'bg-white/20' : '' ?>">
            <a href="<?= URL ?>/admin/tudicoins" class="flex-1 <?= $linkCls($cp === 'tudicoins_escola') ?>">
                <i class="fa-solid fa-wallet w-4 h-4 mr-3 flex-shrink-0"></i>
                <span class="sidebar-text text-sm">TudiCoins da Escola</span>
            </a>
            <button type="button" onclick="toggleNestedMenu('tudicoins')" class="px-2 py-2 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                <svg id="tudicoins-arrow" class="w-3 h-3 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
        </div>
        <div id="tudicoins-nested" class="<?= $tudicoinsNestedOpen ? '' : 'hidden' ?> ml-6 mt-1 space-y-1 border-l border-white/20 pl-2">
            <a href="<?= URL ?>/admin/creditos/pacotes" class="<?= $linkCls($cp === 'creditos_pacotes') ?>">
                <span class="sidebar-text text-sm">Pacotes de TudiCoins</span>
            </a>
        </div>
    </div>
</div>

<!-- Monitoramento -->
<?php if ($modOn('monitoramento')): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cp === 'monitoramento' ? 'bg-white/20' : '' ?>">
        <a href="<?= URL ?>/admin/monitoramento-escolar" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-regular fa-circle-check w-5 h-5 mr-3"></i>
            <span class="sidebar-text">Monitoramento</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('monitoramento')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="monitoramento-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="monitoramento-submenu" class="<?= $monitoramentoOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php if ($podeVerAlertas): ?>
        <a href="<?= URL ?>/admin/monitoramento/alertas" class="<?= $linkCls($cp === 'monitoramento_alertas') ?>">
            <i class="fa-regular fa-clock w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Alertas Sensíveis</span>
            <?php if ($alertasSensiveisNovos > 0): ?>
            <span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full sidebar-text"><?= (int)$alertasSensiveisNovos ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <button type="button" onclick="abrirModalAlunosOnline()" class="w-full flex items-center px-4 py-2 text-green-200 hover:bg-green-500/20 hover:text-green-100 rounded-lg transition-all duration-200 text-left">
            <i class="fa-solid fa-circle-check w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Alunos Online</span>
            <span id="badge-online-count" class="ml-auto bg-green-500 text-white text-xs font-bold px-2 py-1 rounded-full sidebar-text">0</span>
        </button>
        <a href="<?= URL ?>/admin/tentativas-login" class="<?= $linkCls($cp === 'tentativas_login') ?>">
            <i class="fa-solid fa-lock w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Tentativas de login</span>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Pedagógico -->
<?php if ($showPedagogicoGroup): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cp === 'pedagogico' ? 'bg-white/20' : '' ?>">
        <a href="<?= URL ?>/admin/pedagogico" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-regular fa-id-card w-5 h-5 mr-3"></i>
            <span class="sidebar-text">Pedagógico</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('pedagogico')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="pedagogico-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="pedagogico-submenu" class="<?= $pedagogicoOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('aulas_online')): ?>
        <a href="<?= URL ?>/admin/aulas-online" class="<?= $linkCls($cp === 'aulas_online') ?>">
            <i class="fa-solid fa-video w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Aulas Online</span>
        </a>
        <?php endif; ?>
        <?php if (class_exists('FeatureGate') && FeatureGate::isModuleEnabled('ead')): ?>
        <a href="<?= URL ?>/admin/ava" class="<?= $linkCls($cp === 'ava') ?>">
            <i class="fa-solid fa-graduation-cap w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">AVA / EAD</span>
        </a>
        <?php endif; ?>
        <?php if ($modOn('bncc')): ?>
        <a href="<?= URL ?>/admin/bncc" class="<?= $linkCls($cp === 'bncc') ?>">
            <i class="fa-solid fa-list-check w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">BNCC / Plano de Curso</span>
        </a>
        <?php endif; ?>
        <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('aluno_minicursos')): ?>
        <a href="<?= URL ?>/admin/minicursos" class="<?= $linkCls($cp === 'minicursos') ?>">
            <i class="fa-solid fa-play-circle w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">EducaCursos</span>
        </a>
        <?php endif; ?>
        <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('professor_planos_aula')): ?>
        <a href="<?= URL ?>/admin/planos-aula" class="<?= $linkCls($cp === 'planos-aula') ?>">
            <i class="fa-regular fa-file-lines w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Plano de Aula</span>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Sistema -->
<?php if ($showSistemaGroup): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cp === 'sistema' ? 'bg-white/20' : '' ?>">
        <a href="<?= URL ?>/admin/sistema" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-solid fa-gear w-5 h-5 mr-3"></i>
            <span class="sidebar-text">Sistema</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('sistema')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="sistema-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="sistema-submenu" class="<?= $sistemaOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php if ($canViewSidebar(['avatares_alunos'])): ?>
        <a href="<?= URL ?>/admin/avatares-alunos" class="<?= $linkCls($cp === 'avatares_alunos') ?>">
            <i class="fa-regular fa-images w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Avatares dos Alunos</span>
        </a>
        <?php endif; ?>
        <?php if ($modOn('redacao_configuravel')): ?>
        <a href="<?= URL ?>/admin/redacao-configuravel" class="<?= $linkCls($cp === 'essays_config') ?>">
            <i class="fa-solid fa-sliders w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Configuração de Prompt</span>
        </a>
        <?php endif; ?>
        <?php if (($user['perfil_admin'] ?? '') === 'dev'): ?>
        <a href="<?= URL ?>/admin/dev/tickets" class="<?= $linkCls($cp === 'dev_tickets') ?>">
            <i class="fa-solid fa-ticket w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Tickets</span>
            <?php if ($openTicketsCount > 0): ?>
            <span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full sidebar-text"><?= (int) $openTicketsCount ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Usuários -->
<?php if ($showUsuariosGroup): ?>
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cp === 'gestao_usuarios' ? 'bg-white/20' : '' ?>">
        <a href="<?= URL ?>/admin/gestao-usuarios" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-regular fa-user w-5 h-5 mr-3"></i>
            <span class="sidebar-text">Usuários</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('usuarios')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="usuarios-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="usuarios-submenu" class="<?= $usuariosOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <a href="<?= URL ?>/admin/usuarios" class="<?= $linkCls($cp === 'usuarios') ?>">
            <i class="fa-solid fa-user-shield w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Administradores</span>
        </a>
        <a href="<?= URL ?>/admin/monitors" class="<?= $linkCls($cp === 'monitors') ?>">
            <i class="fa-regular fa-eye w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Monitores</span>
        </a>
        <a href="<?= URL ?>/admin/permissoes-perfis" class="<?= $linkCls($permissoesAtivo) ?>">
            <i class="fa-regular fa-id-card w-4 h-4 mr-3 flex-shrink-0"></i>
            <span class="sidebar-text text-sm">Perfis de Permissão</span>
        </a>
    </div>
</div>
<?php endif; ?>

<?php if ($canViewSidebar(['dev_settings', 'unidades', 'modo_manutencao', 'slider_dashboard'])): ?>
<!-- Z-Configuração: só direção e dev -->
<div class="menu-group">
    <div class="flex items-center rounded-xl <?= $cp === 'z_configuracao' ? 'bg-white/20' : '' ?>">
        <a href="<?= URL ?>/admin/z-configuracao" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
            <i class="fa-solid fa-sliders w-5 h-5 mr-3"></i>
            <span class="sidebar-text">Z-Configuração</span>
        </a>
        <button type="button" onclick="toggleMenuGroup('z-configuracao')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
            <svg id="z-configuracao-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>
    <div id="z-configuracao-submenu" class="<?= $zConfigOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
        <?php if (($user['perfil_admin'] ?? '') === 'dev'): ?>
        <a href="<?= URL ?>/admin/dev" class="<?= $linkCls($cp === 'dev') ?>">
            <i class="fa-solid fa-code w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Dev Settings</span>
        </a>
        <?php endif; ?>
        <?php if ($canViewSidebar(['unidades'])): ?>
        <a href="<?= URL ?>/admin/unidades" class="<?= $linkCls($cp === 'unidades') ?>">
            <i class="fa-solid fa-building w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Instituição</span>
        </a>
        <?php endif; ?>
        <?php if ($canViewSidebar(['modo_manutencao'])): ?>
        <a href="<?= URL ?>/admin/maintenance/painel" class="<?= $linkCls($cp === 'maintenance_panel') ?>">
            <i class="fa-solid fa-screwdriver-wrench w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Modo Manutenção</span>
        </a>
        <?php endif; ?>
        <?php if ($canViewSidebar(['slider_dashboard'])): ?>
        <a href="<?= URL ?>/admin/settings#slider-dashboard" class="<?= $linkCls($cp === 'settings') ?>">
            <i class="fa-solid fa-images w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">Slider Dashboard</span>
        </a>
        <?php endif; ?>
        <?php if (($user['perfil_admin'] ?? '') === 'dev'): ?>
        <a href="<?= URL ?>/admin/configuracao/ui-modelos" class="<?= $linkCls($cp === 'ui_modelos') ?>">
            <i class="fa-solid fa-palette w-4 h-4 mr-3"></i>
            <span class="sidebar-text text-sm">UI Modelos</span>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
