<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Meus Filhos</h2>
    <p class="text-gray-600">Gerencie e acompanhe o progresso dos seus filhos</p>
</div>

<?php if (empty($filhos)): ?>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
        </svg>
        <p class="text-gray-500 text-lg">Nenhum filho cadastrado</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($filhos as $f): ?>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                        <span class="text-2xl font-medium text-purple-600"><?= strtoupper(substr($f['nome'] ?? '', 0, 2)) ?></span>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($f['nome'] ?? '') ?></h3>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($f['turma_nome'] ?? '') ?> - <?= htmlspecialchars($f['serie'] ?? '') ?></p>
                    </div>
                </div>
                <div class="space-y-2 mb-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">RA:</span>
                        <span class="font-medium"><?= htmlspecialchars($f['ra'] ?? '') ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Status:</span>
                        <span class="inline-block bg-green-100 text-green-600 text-xs px-2 py-1 rounded">Ativo</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="<?= URL ?>/pais/filhos/<?= $f['id'] ?>" class="flex-1 min-w-[100px] bg-purple-600 text-white text-center py-2 rounded text-sm hover:bg-purple-700 transition-colors">Ver Detalhes</a>
                    <a href="<?= URL ?>/pais/filhos/<?= $f['id'] ?>/notas" class="flex-1 min-w-[100px] bg-blue-600 text-white text-center py-2 rounded text-sm hover:bg-blue-700 transition-colors">Notas</a>
                    <a href="<?= URL ?>/pais/filhos/<?= $f['id'] ?>/jornadas" class="flex-1 min-w-[100px] bg-indigo-600 text-white text-center py-2 rounded text-sm hover:bg-indigo-700 transition-colors">Jornadas</a>
                    <a href="<?= URL ?>/pais/filhos/<?= $f['id'] ?>/plano-aula" class="flex-1 min-w-[100px] bg-gray-600 text-white text-center py-2 rounded text-sm hover:bg-gray-700 transition-colors">Plano de Aula</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
