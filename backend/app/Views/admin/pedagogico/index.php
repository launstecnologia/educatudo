<?php
$page_header_title    = 'Pedagógico';
$page_header_subtitle = 'Gerencie as atividades pedagógicas da escola.';
ob_start(); ?>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php'; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

    <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('aulas_online')): ?>
    <a href="<?= URL ?>/admin/aulas-online"
       class="group bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all duration-200 p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center group-hover:bg-blue-200 transition-colors">
            <i class="fa-solid fa-video text-blue-600 text-xl"></i>
        </div>
        <div>
            <h3 class="font-semibold text-gray-900 text-base">Aulas Online</h3>
            <p class="text-sm text-gray-500 mt-0.5">Gerencie as aulas ao vivo, gravações e salas virtuais.</p>
        </div>
        <span class="mt-auto inline-flex items-center text-sm font-medium text-blue-600 group-hover:text-blue-700">
            Acessar <i class="fa-solid fa-arrow-right ml-1.5 text-xs transition-transform group-hover:translate-x-1"></i>
        </span>
    </a>
    <?php endif; ?>

    <a href="<?= URL ?>/admin/planos-aula"
       class="group bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:border-violet-300 transition-all duration-200 p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-violet-100 flex items-center justify-center group-hover:bg-violet-200 transition-colors">
            <i class="fa-regular fa-file-lines text-violet-600 text-xl"></i>
        </div>
        <div>
            <h3 class="font-semibold text-gray-900 text-base">Plano de Aula</h3>
            <p class="text-sm text-gray-500 mt-0.5">Visualize e aprove os planos de aula dos professores.</p>
        </div>
        <span class="mt-auto inline-flex items-center text-sm font-medium text-violet-600 group-hover:text-violet-700">
            Acessar <i class="fa-solid fa-arrow-right ml-1.5 text-xs transition-transform group-hover:translate-x-1"></i>
        </span>
    </a>

    <?php if (class_exists('FeatureGate') && FeatureGate::isModuleEnabled('ead')): ?>
    <a href="<?= URL ?>/admin/ava"
       class="group bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all duration-200 p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center group-hover:bg-indigo-200 transition-colors">
            <i class="fa-solid fa-graduation-cap text-indigo-600 text-xl"></i>
        </div>
        <div>
            <h3 class="font-semibold text-gray-900 text-base">AVA / EAD</h3>
            <p class="text-sm text-gray-500 mt-0.5">Ambiente virtual de aprendizagem e cursos a distância.</p>
        </div>
        <span class="mt-auto inline-flex items-center text-sm font-medium text-indigo-600 group-hover:text-indigo-700">
            Acessar <i class="fa-solid fa-arrow-right ml-1.5 text-xs transition-transform group-hover:translate-x-1"></i>
        </span>
    </a>
    <?php endif; ?>

    <a href="<?= URL ?>/admin/minicursos"
       class="group bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:border-orange-300 transition-all duration-200 p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center group-hover:bg-orange-200 transition-colors">
            <i class="fa-solid fa-play-circle text-orange-600 text-xl"></i>
        </div>
        <div>
            <h3 class="font-semibold text-gray-900 text-base">EducaCursos</h3>
            <p class="text-sm text-gray-500 mt-0.5">Crie e gerencie minicursos para alunos e professores.</p>
        </div>
        <span class="mt-auto inline-flex items-center text-sm font-medium text-orange-600 group-hover:text-orange-700">
            Acessar <i class="fa-solid fa-arrow-right ml-1.5 text-xs transition-transform group-hover:translate-x-1"></i>
        </span>
    </a>

</div>
