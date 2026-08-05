<div class="max-w-3xl mx-auto space-y-6">
    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-lg px-4 py-3 <?= ($flash['type'] ?? 'info') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Editar apostila</h2>

        <form action="<?= URL ?>/admin/apostilas-ia/<?= (int)$item['id'] ?>/atualizar" method="POST" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Título</label>
                <input type="text" name="titulo" required maxlength="255"
                       value="<?= htmlspecialchars((string)($item['titulo'] ?? '')) ?>"
                       class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Série (ano/nível)</label>
                    <select name="serie_id" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
                        <option value="">Não informado</option>
                        <?php foreach (($series ?? []) as $serie): ?>
                            <option value="<?= (int)$serie['id'] ?>"
                                <?= (int)$item['serie_id'] === (int)$serie['id'] ? 'selected' : '' ?>>
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
                                <input type="checkbox" name="turma_ids[]" value="<?= (int)$turma['id'] ?>"
                                       class="rounded border-gray-300"
                                       <?= in_array((int)$turma['id'], array_map('intval', $turmas_vinculadas ?? []), true) ? 'checked' : '' ?>>
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
                            <option value="<?= (int)$materia['id'] ?>"
                                <?= (int)$item['disciplina_id'] === (int)$materia['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($materia['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Professor responsável</label>
                    <select name="professor_id" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
                        <option value="">Não vincular professor</option>
                        <?php foreach (($professores ?? []) as $prof): ?>
                            <option value="<?= (int)$prof['id'] ?>"
                                <?= (int)$item['professor_id'] === (int)$prof['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prof['nome']) ?><?= !empty($prof['email']) ? ' (' . htmlspecialchars($prof['email']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg transition-colors hover:opacity-90">
                    Salvar alterações
                </button>
                <a href="<?= URL ?>/admin/apostilas-ia" class="px-4 py-2 text-gray-600 hover:text-gray-900">Cancelar</a>
            </div>
        </form>
    </div>

    <?php
    $arquivoPdf = (string)($item['arquivo_pdf'] ?? '');
    $isLegado = strpos($arquivoPdf, 'legado:') === 0;
    ?>
    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-1">Capa da apostila</h3>
        <p class="text-sm text-gray-500 mb-4">Imagem exibida no card do aluno. Aceita JPG, PNG ou WEBP (máx. 10MB).</p>

        <?php if (!empty($capa_atual_url)): ?>
            <div class="mb-4">
                <p class="text-xs text-gray-500 mb-1">Capa atual:</p>
                <img src="<?= htmlspecialchars($capa_atual_url) ?>" alt="Capa atual"
                     class="h-40 object-cover rounded-lg border border-gray-200">
            </div>
        <?php endif; ?>

        <form action="<?= URL ?>/admin/apostilas-ia/<?= (int)$item['id'] ?>/enviar-capa" method="POST" enctype="multipart/form-data" class="space-y-3">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <div>
                <input type="file" name="capa" required accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                       class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
            </div>
            <div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                    Enviar capa
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-1">PDF da apostila</h3>
        <?php if ($isLegado): ?>
            <p class="text-sm text-gray-500 mb-4">Esta apostila foi migrada do módulo legado. Faça upload de um novo PDF para substituí-la — escolha se quer apenas exibir ou também processar com IA.</p>
        <?php else: ?>
            <p class="text-sm text-gray-500 mb-4">Faça upload de um novo PDF para substituir o atual. Escolha se quer apenas exibir ou também processar com IA.</p>
        <?php endif; ?>

        <form action="<?= URL ?>/admin/apostilas-ia/<?= (int)$item['id'] ?>/enviar-pdf" method="POST" enctype="multipart/form-data" class="space-y-3">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Arquivo PDF</label>
                <input type="file" name="arquivo" required accept="application/pdf,.pdf"
                       class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Modo de uso do PDF</label>
                <div class="space-y-2">
                    <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="radio" name="modo_upload" value="exibir" checked class="mt-0.5">
                        <span>
                            <span class="font-medium">Somente exibir</span>
                            <span class="text-gray-500"> — PDF disponível para visualização, sem processamento por IA</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="radio" name="modo_upload" value="ia" class="mt-0.5">
                        <span>
                            <span class="font-medium">Processar com IA</span>
                            <span class="text-gray-500"> — extrai conteúdo, gera exercícios e habilita o chat de dúvidas</span>
                        </span>
                    </label>
                </div>
            </div>

            <div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                    Enviar PDF
                </button>
            </div>
        </form>
    </div>
</div>
