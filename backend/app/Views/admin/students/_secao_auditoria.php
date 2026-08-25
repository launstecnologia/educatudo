<?php
/** Extraído de show.php — conteúdo existente, sem alteração de regra de negócio. */
?>
    <!-- Perf: histórico de auditoria saiu da carga principal e virou página própria,
         pra não rodar SHOW TABLES + SELECT em toda visita ao perfil do aluno. -->
    <div id="section-auditoria-aluno" class="student-card min-w-0 mb-6">
        <div class="student-card-body flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-clock-rotate-left text-slate-500"></i>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Histórico de auditoria</h3>
                    <p class="text-sm text-slate-500 mt-0.5">Últimas ações sensíveis sobre este aluno</p>
                </div>
            </div>
            <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/auditoria"
               class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shrink-0">
                Ver histórico
            </a>
        </div>
    </div>
