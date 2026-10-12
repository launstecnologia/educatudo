<div class="max-w-5xl mx-auto space-y-6">
    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-lg px-4 py-3 <?= ($flash['type'] ?? 'info') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-2">Enviar apostila</h2>
        <p class="text-sm text-gray-600 mb-4">Formatos permitidos: PDF e imagens (JPG, PNG, WEBP, GIF).</p>

        <form action="<?= URL ?>/admin/apostilas/upload" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Título</label>
                <input type="text" name="titulo" required maxlength="180" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2" placeholder="Ex.: Apostila de Matemática - 1º Bimestre">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                <textarea name="descricao" rows="3" maxlength="1000" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2" placeholder="Resumo do conteúdo da apostila"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quem pode visualizar</label>
                <select name="visibilidade" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
                    <option value="aluno">Aluno</option>
                    <option value="professor">Professor</option>
                    <option value="ambos" selected>Ambos</option>
                </select>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Curso</label>
                    <select name="curso_id" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
                        <option value="">Todos/Não informado</option>
                        <?php foreach (($cursos ?? []) as $curso): ?>
                            <option value="<?= (int)$curso['id'] ?>"><?= htmlspecialchars($curso['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Matéria</label>
                    <select name="materia_id" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
                        <option value="">Todas/Não informada</option>
                        <?php foreach (($materias ?? []) as $materia): ?>
                            <option value="<?= (int)$materia['id'] ?>"><?= htmlspecialchars($materia['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Professor (opcional)</label>
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
                <label class="block text-sm font-medium text-gray-700 mb-1">Turmas que podem visualizar</label>
                <div class="max-h-48 overflow-auto border border-gray-300 rounded-lg p-3 space-y-2 bg-gray-50">
                    <?php foreach (($turmas ?? []) as $turma): ?>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="turma_ids[]" value="<?= (int)$turma['id'] ?>" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span><?= htmlspecialchars($turma['nome']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="text-xs text-gray-500 mt-1">Marque uma ou mais turmas.</p>
            </div>
            <div>
                <input type="file" name="arquivo" required accept=".pdf,.jpg,.jpeg,.png,.webp,.gif" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
            </div>
            <div>
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg transition-colors hover:opacity-90">
                    Enviar apostila
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Apostilas cadastradas</h3>

        <?php if (empty($items)): ?>
            <p class="text-gray-500">Nenhuma apostila cadastrada ainda.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($items as $item): ?>
                    <?php
                    $turmaIdsSelecionadas = [];
                    $turmaIdsCsv = trim((string)($item['turma_ids_csv'] ?? ''));
                    if ($turmaIdsCsv !== '') {
                        foreach (explode(',', $turmaIdsCsv) as $tid) {
                            $tid = (int)trim($tid);
                            if ($tid > 0) {
                                $turmaIdsSelecionadas[] = $tid;
                            }
                        }
                    }
                    ?>
                    <div class="flex items-center justify-between border border-gray-200 rounded-lg px-4 py-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 truncate"><?= htmlspecialchars($item['titulo'] ?? $item['nome_original']) ?></p>
                            <?php if (!empty($item['descricao'])): ?>
                                <p class="text-sm text-gray-600 truncate"><?= htmlspecialchars($item['descricao']) ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500">
                                <?php
                                $visLabel = 'Ambos';
                                if (($item['visibilidade'] ?? '') === 'aluno') $visLabel = 'Aluno';
                                if (($item['visibilidade'] ?? '') === 'professor') $visLabel = 'Professor';
                                ?>
                                <?= htmlspecialchars($visLabel) ?> ·
                                <?= !empty($item['created_at']) ? date('d/m/Y H:i', strtotime($item['created_at'])) : '-' ?> ·
                                <?= number_format(((int)($item['tamanho'] ?? 0)) / 1024, 1, ',', '.') ?> KB
                            </p>
                            <?php if (!empty($item['curso_nome']) || !empty($item['materia_nome']) || !empty($item['professor_nome'])): ?>
                                <p class="text-xs text-gray-500">
                                    <?= !empty($item['curso_nome']) ? 'Curso: ' . htmlspecialchars($item['curso_nome']) : 'Curso: —' ?> ·
                                    <?= !empty($item['materia_nome']) ? 'Matéria: ' . htmlspecialchars($item['materia_nome']) : 'Matéria: —' ?> ·
                                    <?= !empty($item['professor_nome']) ? 'Professor: ' . htmlspecialchars($item['professor_nome']) : 'Professor: —' ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($item['turmas_nomes'])): ?>
                                <p class="text-xs text-gray-500 truncate">Turmas: <?= htmlspecialchars($item['turmas_nomes']) ?></p>
                            <?php endif; ?>
                            <details class="mt-3">
                                <summary class="cursor-pointer text-sm text-indigo-600 hover:text-indigo-800 font-medium">Editar apostila</summary>
                                <form action="<?= URL ?>/admin/apostilas/editar" method="POST" class="mt-3 space-y-3 border border-gray-200 rounded-lg p-3 bg-gray-50">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Título</label>
                                        <input type="text" name="titulo" required maxlength="180" value="<?= htmlspecialchars((string)($item['titulo'] ?? $item['nome_original'] ?? '')) ?>" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Descrição</label>
                                        <textarea name="descricao" rows="3" maxlength="1000" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2"><?= htmlspecialchars((string)($item['descricao'] ?? '')) ?></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Quem pode visualizar</label>
                                        <select name="visibilidade" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
                                            <option value="aluno" <?= (($item['visibilidade'] ?? '') === 'aluno') ? 'selected' : '' ?>>Aluno</option>
                                            <option value="professor" <?= (($item['visibilidade'] ?? '') === 'professor') ? 'selected' : '' ?>>Professor</option>
                                            <option value="ambos" <?= (($item['visibilidade'] ?? 'ambos') === 'ambos') ? 'selected' : '' ?>>Ambos</option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">Curso</label>
                                            <select name="curso_id" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
                                                <option value="">Todos/Não informado</option>
                                                <?php foreach (($cursos ?? []) as $curso): ?>
                                                    <option value="<?= (int)$curso['id'] ?>" <?= ((int)($item['curso_id'] ?? 0) === (int)$curso['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($curso['nome']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">Matéria</label>
                                            <select name="materia_id" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
                                                <option value="">Todas/Não informada</option>
                                                <?php foreach (($materias ?? []) as $materia): ?>
                                                    <option value="<?= (int)$materia['id'] ?>" <?= ((int)($item['materia_id'] ?? 0) === (int)$materia['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($materia['nome']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">Professor (opcional)</label>
                                            <select name="professor_id" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2">
                                                <option value="">Não vincular professor</option>
                                                <?php foreach (($professores ?? []) as $prof): ?>
                                                    <option value="<?= (int)$prof['id'] ?>" <?= ((int)($item['professor_id'] ?? 0) === (int)$prof['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($prof['nome']) ?><?= !empty($prof['email']) ? ' (' . htmlspecialchars($prof['email']) . ')' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Turmas que podem visualizar</label>
                                        <div class="max-h-40 overflow-auto border border-gray-300 rounded-lg p-2 space-y-1 bg-white">
                                            <?php foreach (($turmas ?? []) as $turma): ?>
                                                <?php $isChecked = in_array((int)$turma['id'], $turmaIdsSelecionadas, true); ?>
                                                <label class="flex items-center gap-2 text-xs text-gray-700">
                                                    <input type="checkbox" name="turma_ids[]" value="<?= (int)$turma['id'] ?>" <?= $isChecked ? 'checked' : '' ?> class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                    <span><?= htmlspecialchars($turma['nome']) ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <button type="submit" class="btn-primary-custom px-3 py-1.5 rounded-lg text-sm hover:opacity-90">
                                            Salvar alterações
                                        </button>
                                    </div>
                                </form>
                            </details>
                        </div>
                        <div class="ml-3">
                            <form action="<?= URL ?>/admin/apostilas/excluir" method="POST" onsubmit="return confirm('Deseja remover esta apostila?');">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                    Excluir
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
