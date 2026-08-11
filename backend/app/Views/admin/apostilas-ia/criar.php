<div class="max-w-3xl mx-auto space-y-6">
    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-lg px-4 py-3 <?= ($flash['type'] ?? 'info') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-2">Nova apostila com IA</h2>
        <p class="text-sm text-gray-600 mb-4">Apenas arquivos PDF são aceitos (máx. 200MB). O processamento (extração de páginas, exercícios e geração de busca por IA) é feito por um serviço dedicado e pode levar alguns minutos.</p>

        <form action="<?= URL ?>/admin/apostilas-ia/upload" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Título</label>
                <input type="text" name="titulo" required maxlength="255" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2" placeholder="Ex.: Apostila de Matemática - 1º Bimestre">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Série (ano/nível)</label>
                    <select name="serie_id" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
                        <option value="">Não informado</option>
                        <?php foreach (($series ?? []) as $serie): ?>
                            <option value="<?= (int)$serie['id'] ?>">
                                <?= htmlspecialchars($serie['curso_nome']) ?> - <?= htmlspecialchars($serie['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Turmas (pode marcar mais de uma)</label>
                    <div class="border border-gray-300 rounded-lg p-2 max-h-40 overflow-y-auto space-y-1">
                        <?php if (empty($turmas)): ?>
                            <p class="text-xs text-gray-400">Nenhuma turma ativa cadastrada.</p>
                        <?php endif; ?>
                        <?php foreach (($turmas ?? []) as $turma): ?>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="turma_ids[]" value="<?= (int)$turma['id'] ?>" class="rounded border-gray-300">
                                <?= htmlspecialchars($turma['nome']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Disciplina</label>
                    <select name="disciplina_id" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
                        <option value="">Não informado</option>
                        <?php foreach (($materias ?? []) as $materia): ?>
                            <option value="<?= (int)$materia['id'] ?>"><?= htmlspecialchars($materia['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Professor responsável</label>
                    <select name="professor_id" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
                        <option value="">Não vincular professor</option>
                        <?php foreach (($professores ?? []) as $prof): ?>
                            <option value="<?= (int)$prof['id'] ?>">
                                <?= htmlspecialchars($prof['nome']) ?><?= !empty($prof['email']) ? ' (' . htmlspecialchars($prof['email']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Arquivo PDF</label>
                <input type="file" name="arquivo" required accept="application/pdf,.pdf" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
            </div>

            <div>
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg transition-colors hover:opacity-90">
                    Enviar e processar
                </button>
                <a href="<?= URL ?>/admin/apostilas-ia" class="ml-2 px-4 py-2 text-gray-600 hover:text-gray-900">Cancelar</a>
            </div>
        </form>
    </div>
</div>
