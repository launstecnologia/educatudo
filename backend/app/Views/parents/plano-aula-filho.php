<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Plano de Aula</h1>
    <p class="text-gray-600 mt-2">Planos de aula da turma de <?= htmlspecialchars($filho['nome'] ?? '') ?></p>
</div>

<?php if (!empty($filho['turma_nome'])): ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex items-center space-x-4">
        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
        </div>
        <div>
            <p class="text-sm text-gray-500">Turma do aluno</p>
            <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($filho['turma_nome']) ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($planos_aula)): ?>
<!-- Filtros -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Filtrar por Professor</label>
            <select id="filtroProfessor" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Todos os professores</option>
                <?php
                $professores = array_unique(array_filter(array_column($planos_aula, 'professor_nome')));
                sort($professores);
                foreach ($professores as $prof): ?>
                    <option value="<?= htmlspecialchars($prof) ?>"><?= htmlspecialchars($prof) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Filtrar por Matéria</label>
            <select id="filtroMateria" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Todas as matérias</option>
                <?php
                $materias = array_unique(array_filter(array_column($planos_aula, 'materia_nome')));
                sort($materias);
                foreach ($materias as $mat): ?>
                    <option value="<?= htmlspecialchars($mat) ?>"><?= htmlspecialchars($mat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Filtrar por Data</label>
            <select id="filtroData" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Todas as datas</option>
                <?php
                $datas = array_unique(array_filter(array_column($planos_aula, 'data_exibicao')));
                sort($datas);
                foreach ($datas as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <?php if (empty($planos_aula)): ?>
        <div class="p-12 text-center">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="text-lg font-medium text-gray-900 mb-2">Nenhum plano de aula disponível</p>
            <p class="text-sm text-gray-500">Não há planos de aula para a turma do aluno no momento.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">Data</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-48">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="planosTableBody">
                    <?php
                    $filhoId = (int)($filho['id'] ?? 0);
                    foreach ($planos_aula as $plano):
                        $planoId = (int)($plano['id'] ?? 0);
                        $professorNome = $plano['professor_nome'] ?? '—';
                        $materiaNome = $plano['materia_nome'] ?? '—';
                    ?>
                        <tr class="hover:bg-gray-50 plano-row"
                            data-professor="<?= htmlspecialchars($professorNome) ?>"
                            data-materia="<?= htmlspecialchars($materiaNome) ?>"
                            data-data="<?= htmlspecialchars($plano['data_exibicao'] ?? '') ?>">
                            <td class="px-6 py-3">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($plano['titulo'] ?? 'Sem título') ?></div>
                                <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($professorNome) ?> – <?= htmlspecialchars($materiaNome) ?></div>
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600 whitespace-nowrap"><?= htmlspecialchars($plano['data_exibicao'] ?? '—') ?></td>
                            <td class="px-6 py-3 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <a href="<?= URL ?>/pais/filhos/<?= $filhoId ?>/plano-aula/visualizar/<?= $planoId ?>" class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700 transition-colors" title="Visualizar plano de aula">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        Visualizar
                                    </a>
                                    <a href="<?= URL ?>/pais/filhos/<?= $filhoId ?>/plano-aula/pdf/<?= $planoId ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-3 py-1.5 bg-gray-700 text-white text-xs font-medium rounded-lg hover:bg-gray-800 transition-colors" title="Abrir PDF">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        PDF
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var filtroProfessor = document.getElementById('filtroProfessor');
            var filtroMateria = document.getElementById('filtroMateria');
            var filtroData = document.getElementById('filtroData');
            var rows = document.querySelectorAll('.plano-row');

            function filtrar() {
                var professor = (filtroProfessor && filtroProfessor.value) || '';
                var materia = (filtroMateria && filtroMateria.value) || '';
                var data = (filtroData && filtroData.value) || '';
                var visiveis = 0;

                rows.forEach(function(tr) {
                    var p = (tr.getAttribute('data-professor') || '');
                    var m = (tr.getAttribute('data-materia') || '');
                    var d = (tr.getAttribute('data-data') || '');
                    var ok = (!professor || p === professor) && (!materia || m === materia) && (!data || d === data);
                    tr.style.display = ok ? '' : 'none';
                    if (ok) visiveis++;
                });

                var tbody = document.getElementById('planosTableBody');
                var msg = document.getElementById('planosNenhumResultado');
                if (visiveis === 0 && rows.length > 0) {
                    if (!msg) {
                        msg = document.createElement('tr');
                        msg.id = 'planosNenhumResultado';
                        msg.innerHTML = '<td colspan="3" class="px-6 py-8 text-center text-gray-500">Nenhum plano de aula encontrado com os filtros selecionados.</td>';
                        tbody.appendChild(msg);
                    }
                    msg.style.display = '';
                } else if (msg) {
                    msg.style.display = 'none';
                }
            }

            if (filtroProfessor) filtroProfessor.addEventListener('change', filtrar);
            if (filtroMateria) filtroMateria.addEventListener('change', filtrar);
            if (filtroData) filtroData.addEventListener('change', filtrar);
        });
        </script>
    <?php endif; ?>
</div>
