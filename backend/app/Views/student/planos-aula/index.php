<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Planos de Aula 📚</h1>
            <p class="text-gray-600 mt-2">Acompanhe os planos de aula da sua turma</p>
        </div>
    </div>
</div>

<!-- Informações da Turma -->
<?php if (!empty($aluno['turma_nome'])): ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex items-center space-x-4">
        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
        </div>
        <div>
            <p class="text-sm text-gray-500">Turma</p>
            <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($aluno['turma_nome']) ?></p>
        </div>
        <?php if (!empty($aluno['turma_serie'])): ?>
        <div>
            <p class="text-sm text-gray-500">Série</p>
            <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($aluno['turma_serie']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Filtros -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Filtrar por Título</label>
            <input type="text" id="filtroTitulo" placeholder="Digite o título..." 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Filtrar por Matéria</label>
            <select id="filtroMateria" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Todas as matérias</option>
                <?php 
                $materias = array_unique(array_filter(array_column($planos_aula, 'materia_nome')));
                foreach ($materias as $materia): 
                ?>
                    <option value="<?= htmlspecialchars($materia) ?>"><?= htmlspecialchars($materia) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Filtrar por Professor</label>
            <select id="filtroProfessor" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Todos os professores</option>
                <?php 
                $professores = array_unique(array_filter(array_column($planos_aula, 'professor_nome')));
                foreach ($professores as $professor): 
                ?>
                    <option value="<?= htmlspecialchars($professor) ?>"><?= htmlspecialchars($professor) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<!-- Planos de Aula Lista -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <?php if (empty($planos_aula)): ?>
        <div class="p-12 text-center">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="text-lg font-medium text-gray-900 mb-2">Nenhum plano de aula disponível</p>
            <p class="text-sm text-gray-500">Não há planos de aula aprovados para sua turma no momento.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matéria</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Professor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="planosTableBody">
                    <?php foreach ($planos_aula as $plano): ?>
                        <tr class="hover:bg-gray-50 transition-colors plano-row" 
                            data-titulo="<?= strtolower(htmlspecialchars($plano['titulo'] ?? '')) ?>"
                            data-materia="<?= htmlspecialchars($plano['materia_nome'] ?? '') ?>"
                            data-professor="<?= htmlspecialchars($plano['professor_nome'] ?? '') ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($plano['titulo'] ?? 'Sem título') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600"><?= htmlspecialchars($plano['materia_nome'] ?? 'Não especificada') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600"><?= htmlspecialchars($plano['professor_nome'] ?? 'Não especificado') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600">
                                    <?php if (!empty($plano['data_exibicao'])): ?>
                                        <?= htmlspecialchars($plano['data_exibicao']) ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">Não informado</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="<?= URL ?>/aluno/planos-aula/visualizar/<?= $plano['id'] ?>" 
                                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    Ver Detalhes
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filtroTitulo = document.getElementById('filtroTitulo');
    const filtroMateria = document.getElementById('filtroMateria');
    const filtroProfessor = document.getElementById('filtroProfessor');
    const planosRows = document.querySelectorAll('.plano-row');
    
    function filtrarPlanos() {
        const tituloFiltro = filtroTitulo.value.toLowerCase().trim();
        const materiaFiltro = filtroMateria.value;
        const professorFiltro = filtroProfessor.value;
        
        let visibleCount = 0;
        
        planosRows.forEach(row => {
            const titulo = row.dataset.titulo || '';
            const materia = row.dataset.materia || '';
            const professor = row.dataset.professor || '';
            
            const matchTitulo = !tituloFiltro || titulo.includes(tituloFiltro);
            const matchMateria = !materiaFiltro || materia === materiaFiltro;
            const matchProfessor = !professorFiltro || professor === professorFiltro;
            
            if (matchTitulo && matchMateria && matchProfessor) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Mostra mensagem se nenhum plano for encontrado
        const tableBody = document.getElementById('planosTableBody');
        let noResultsMsg = document.getElementById('noResultsMsg');
        
        if (visibleCount === 0 && planosRows.length > 0) {
            if (!noResultsMsg) {
                noResultsMsg = document.createElement('tr');
                noResultsMsg.id = 'noResultsMsg';
                noResultsMsg.innerHTML = `
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                        Nenhum plano de aula encontrado com os filtros selecionados.
                    </td>
                `;
                tableBody.appendChild(noResultsMsg);
            }
            noResultsMsg.style.display = '';
        } else if (noResultsMsg) {
            noResultsMsg.style.display = 'none';
        }
    }
    
    filtroTitulo.addEventListener('input', filtrarPlanos);
    filtroMateria.addEventListener('change', filtrarPlanos);
    filtroProfessor.addEventListener('change', filtrarPlanos);
});
</script>

