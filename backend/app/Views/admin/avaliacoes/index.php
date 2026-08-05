<?php
$page_header_title    = 'Avaliações';
$page_header_subtitle = 'Gerencie avaliações, exercícios e jornadas de aprendizagem.';
ob_start(); ?>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php'; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

    <a href="<?= URL ?>/admin/provas"
       class="group bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all duration-200 p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center group-hover:bg-blue-200 transition-colors">
            <i class="fa-regular fa-clipboard text-blue-600 text-xl"></i>
        </div>
        <div>
            <h3 class="font-semibold text-gray-900 text-base">Avaliações / Notas</h3>
            <p class="text-sm text-gray-500 mt-0.5">Crie provas, aplique e registre notas dos alunos.</p>
        </div>
        <span class="mt-auto inline-flex items-center text-sm font-medium text-blue-600 group-hover:text-blue-700">
            Acessar <i class="fa-solid fa-arrow-right ml-1.5 text-xs transition-transform group-hover:translate-x-1"></i>
        </span>
    </a>

    <a href="<?= URL ?>/admin/exercises"
       class="group bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:border-green-300 transition-all duration-200 p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center group-hover:bg-green-200 transition-colors">
            <i class="fa-solid fa-check-double text-green-600 text-xl"></i>
        </div>
        <div>
            <h3 class="font-semibold text-gray-900 text-base">Exercícios</h3>
            <p class="text-sm text-gray-500 mt-0.5">Banco de exercícios e atividades para os alunos.</p>
        </div>
        <span class="mt-auto inline-flex items-center text-sm font-medium text-green-600 group-hover:text-green-700">
            Acessar <i class="fa-solid fa-arrow-right ml-1.5 text-xs transition-transform group-hover:translate-x-1"></i>
        </span>
    </a>

    <?php if (class_exists('FeatureGate') && FeatureGate::isModuleEnabled('jornadas')): ?>
    <a href="<?= URL ?>/admin/jornadas"
       class="group bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:border-violet-300 transition-all duration-200 p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-violet-100 flex items-center justify-center group-hover:bg-violet-200 transition-colors">
            <i class="fa-solid fa-route text-violet-600 text-xl"></i>
        </div>
        <div>
            <h3 class="font-semibold text-gray-900 text-base">Jornada do Aluno</h3>
            <p class="text-sm text-gray-500 mt-0.5">Trilhas de aprendizagem personalizadas por aluno.</p>
        </div>
        <span class="mt-auto inline-flex items-center text-sm font-medium text-violet-600 group-hover:text-violet-700">
            Acessar <i class="fa-solid fa-arrow-right ml-1.5 text-xs transition-transform group-hover:translate-x-1"></i>
        </span>
    </a>
    <?php endif; ?>

    <a href="<?= URL ?>/admin/redacao-professor"
       class="group bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:border-rose-300 transition-all duration-200 p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-rose-100 flex items-center justify-center group-hover:bg-rose-200 transition-colors">
            <i class="fa-solid fa-pen-to-square text-rose-600 text-xl"></i>
        </div>
        <div>
            <h3 class="font-semibold text-gray-900 text-base">Jornada da Redação</h3>
            <p class="text-sm text-gray-500 mt-0.5">Acompanhe as redações enviadas e corrija com IA.</p>
        </div>
        <span class="mt-auto inline-flex items-center text-sm font-medium text-rose-600 group-hover:text-rose-700">
            Acessar <i class="fa-solid fa-arrow-right ml-1.5 text-xs transition-transform group-hover:translate-x-1"></i>
        </span>
    </a>

    <a href="<?= URL ?>/admin/inclusao"
       class="group bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:border-teal-300 transition-all duration-200 p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-teal-100 flex items-center justify-center group-hover:bg-teal-200 transition-colors">
            <i class="fa-solid fa-universal-access text-teal-600 text-xl"></i>
        </div>
        <div>
            <h3 class="font-semibold text-gray-900 text-base">EducaInclui</h3>
            <p class="text-sm text-gray-500 mt-0.5">Versões adaptadas de avaliações para alunos com necessidades especiais.</p>
        </div>
        <span class="mt-auto inline-flex items-center text-sm font-medium text-teal-600 group-hover:text-teal-700">
            Acessar <i class="fa-solid fa-arrow-right ml-1.5 text-xs transition-transform group-hover:translate-x-1"></i>
        </span>
    </a>

</div>
