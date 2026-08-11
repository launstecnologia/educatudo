<?php
$page_header_title    = 'Acadêmico';
$page_header_subtitle = 'Gerencie a estrutura acadêmica da escola.';
ob_start(); ?>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php'; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

    <a href="<?= URL ?>/admin/ano-letivo"
       class="group bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all duration-200 p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center group-hover:bg-indigo-200 transition-colors">
            <i class="fa-regular fa-calendar text-indigo-600 text-xl"></i>
        </div>
        <div>
            <h3 class="font-semibold text-gray-900 text-base">Ano Letivo</h3>
            <p class="text-sm text-gray-500 mt-0.5">Crie e gerencie os anos letivos da escola, períodos e datas.</p>
        </div>
        <span class="mt-auto inline-flex items-center text-sm font-medium text-indigo-600 group-hover:text-indigo-700">
            Acessar <i class="fa-solid fa-arrow-right ml-1.5 text-xs transition-transform group-hover:translate-x-1"></i>
        </span>
    </a>

    <a href="<?= URL ?>/admin/curso"
       class="group bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:border-violet-300 transition-all duration-200 p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-violet-100 flex items-center justify-center group-hover:bg-violet-200 transition-colors">
            <i class="fa-solid fa-graduation-cap text-violet-600 text-xl"></i>
        </div>
        <div>
            <h3 class="font-semibold text-gray-900 text-base">Curso</h3>
            <p class="text-sm text-gray-500 mt-0.5">Configure os cursos oferecidos pela instituição.</p>
        </div>
        <span class="mt-auto inline-flex items-center text-sm font-medium text-violet-600 group-hover:text-violet-700">
            Acessar <i class="fa-solid fa-arrow-right ml-1.5 text-xs transition-transform group-hover:translate-x-1"></i>
        </span>
    </a>

    <a href="<?= URL ?>/admin/students/remanejamento"
       class="group bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:border-amber-300 transition-all duration-200 p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center group-hover:bg-amber-200 transition-colors">
            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h10M7 7l3-3M7 7l3 3M17 17H7m10 0l-3-3m3 3l-3 3"></path>
            </svg>
        </div>
        <div>
            <h3 class="font-semibold text-gray-900 text-base">Movimentação de Alunos</h3>
            <p class="text-sm text-gray-500 mt-0.5">Transferências, remanejamentos e histórico de movimentação.</p>
        </div>
        <span class="mt-auto inline-flex items-center text-sm font-medium text-amber-600 group-hover:text-amber-700">
            Acessar <i class="fa-solid fa-arrow-right ml-1.5 text-xs transition-transform group-hover:translate-x-1"></i>
        </span>
    </a>

    <a href="<?= URL ?>/admin/serie"
       class="group bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:border-teal-300 transition-all duration-200 p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-teal-100 flex items-center justify-center group-hover:bg-teal-200 transition-colors">
            <i class="fa-solid fa-layer-group text-teal-600 text-xl"></i>
        </div>
        <div>
            <h3 class="font-semibold text-gray-900 text-base">Série</h3>
            <p class="text-sm text-gray-500 mt-0.5">Cadastre e organize as séries/anos de cada curso.</p>
        </div>
        <span class="mt-auto inline-flex items-center text-sm font-medium text-teal-600 group-hover:text-teal-700">
            Acessar <i class="fa-solid fa-arrow-right ml-1.5 text-xs transition-transform group-hover:translate-x-1"></i>
        </span>
    </a>

    <a href="<?= URL ?>/admin/subjects"
       class="group bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:border-emerald-300 transition-all duration-200 p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center group-hover:bg-emerald-200 transition-colors">
            <i class="fa-solid fa-book text-emerald-600 text-xl"></i>
        </div>
        <div>
            <h3 class="font-semibold text-gray-900 text-base">Matérias</h3>
            <p class="text-sm text-gray-500 mt-0.5">Gerencie as disciplinas/matérias oferecidas pela escola.</p>
        </div>
        <span class="mt-auto inline-flex items-center text-sm font-medium text-emerald-600 group-hover:text-emerald-700">
            Acessar <i class="fa-solid fa-arrow-right ml-1.5 text-xs transition-transform group-hover:translate-x-1"></i>
        </span>
    </a>

    <a href="<?= URL ?>/admin/turmas"
       class="group bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md hover:border-sky-300 transition-all duration-200 p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-sky-100 flex items-center justify-center group-hover:bg-sky-200 transition-colors">
            <i class="fa-solid fa-school text-sky-600 text-xl"></i>
        </div>
        <div>
            <h3 class="font-semibold text-gray-900 text-base">Turmas</h3>
            <p class="text-sm text-gray-500 mt-0.5">Gerencie as turmas, vincule alunos e configure a grade horária.</p>
        </div>
        <span class="mt-auto inline-flex items-center text-sm font-medium text-sky-600 group-hover:text-sky-700">
            Acessar <i class="fa-solid fa-arrow-right ml-1.5 text-xs transition-transform group-hover:translate-x-1"></i>
        </span>
    </a>

</div>
