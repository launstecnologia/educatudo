<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
.quill-contexto-wrap .ql-toolbar.ql-snow { border: 1px solid #e5e7eb; border-bottom: none; border-radius: 8px 8px 0 0; background: #f9fafb; padding: 8px; }
.quill-contexto-wrap .ql-container.ql-snow { border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px; }
.quill-contexto-wrap .ql-editor { min-height: 140px; }
.quill-contexto-wrap .ql-container { border: none; }
.repertorio-quill-wrap .ql-toolbar.ql-snow { border: 1px solid #e5e7eb; border-bottom: none; border-radius: 8px 8px 0 0; background: #f9fafb; padding: 6px 8px; }
.repertorio-quill-wrap .ql-container.ql-snow { border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px; }
.repertorio-quill-wrap .ql-editor { min-height: 100px; }
.repertorio-quill-wrap .ql-container { border: none; }
.quill-proposta .ql-toolbar .ql-image { background: none !important; }
.quill-proposta .ql-toolbar button { width: 28px; }
</style>
<?php
$themeMode = $proposal && isset($proposal['theme_mode']) ? $proposal['theme_mode'] : 'configurar';
$submissionMode = $proposal && isset($proposal['submission_mode']) ? $proposal['submission_mode'] : 'texto';
$showTitleField = $proposal && isset($proposal['show_title_field']) ? (int)$proposal['show_title_field'] : 1;
$startsAt = $proposal && !empty($proposal['starts_at']) ? date('Y-m-d\TH:i', strtotime($proposal['starts_at'])) : '';
$endsAt = $proposal && !empty($proposal['ends_at']) ? date('Y-m-d\TH:i', strtotime($proposal['ends_at'])) : '';
$temaProntoFile = $proposal && !empty($proposal['tema_pronto_file']) ? $proposal['tema_pronto_file'] : '';
$basePrefix = isset($base_prefix) ? rtrim($base_prefix, '/') : '/professor/redacao-configuravel';
$isAdminForm = !empty($is_admin_form);
$allProfessors = isset($all_professors) && is_array($all_professors) ? $all_professors : [];
$selectedTeacherId = isset($selected_teacher_id) ? (int)$selected_teacher_id : 0;
$selectedProfessorIds = isset($selected_professor_ids) && is_array($selected_professor_ids) ? array_map('intval', $selected_professor_ids) : [];
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= $proposal ? 'Jornada da Redação - Editar proposta' : 'Jornada da Redação - Nova proposta' ?></h2>
            <p class="text-gray-600"><a href="<?= URL . $basePrefix ?>" class="text-purple-600 hover:underline">Voltar às propostas</a></p>
        </div>
        <a href="<?= URL . $basePrefix ?>" class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center">Voltar</a>
    </div>
</div>
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Dados da Proposta</h3>
    </div>
    <form id="form" class="p-6 space-y-6">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="tema_pronto_file" id="tema_pronto_file" value="<?= htmlspecialchars($temaProntoFile) ?>">

        <!-- 1. Dados da proposta -->
        <div>
            <label for="board_id" class="block text-sm font-semibold text-gray-700 mb-2">Banca <span class="text-red-500">*</span></label>
            <select id="board_id" name="board_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                <option value="">Selecione a banca</option>
                <?php foreach ($boards as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= ($proposal && (int)$proposal['board_id'] === (int)$b['id']) ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="text_type_id" class="block text-sm font-semibold text-gray-700 mb-2">Tipo Textual <span class="text-red-500">*</span></label>
            <select id="text_type_id" name="text_type_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                <option value="">Selecione o tipo textual</option>
                <?php foreach ($textTypes as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= ($proposal && (int)$proposal['text_type_id'] === (int)$t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="title" class="block text-sm font-semibold text-gray-700 mb-2">Título do evento <span class="text-red-500">*</span></label>
            <input type="text" id="title" name="title" required value="<?= $proposal ? htmlspecialchars($proposal['title']) : '' ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="Ex: Redação de avaliação do dia 15">
        </div>
        <?php if ($isAdminForm): ?>
        <div>
            <label for="teacher_id" class="block text-sm font-semibold text-gray-700 mb-2">Professor responsável <span class="text-red-500">*</span></label>
            <select id="teacher_id" name="teacher_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                <option value="">Selecione o professor responsável</option>
                <?php foreach ($allProfessors as $prof): ?>
                <option value="<?= (int)$prof['id'] ?>" <?= $selectedTeacherId === (int)$prof['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($prof['nome']) ?><?= !empty($prof['email']) ? ' (' . htmlspecialchars($prof['email']) . ')' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="allowed_professor_ids" class="block text-sm font-semibold text-gray-700 mb-2">Professores com acesso para corrigir (pode ser mais de um)</label>
            <select id="allowed_professor_ids" name="allowed_professor_ids[]" multiple class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 min-h-[140px]">
                <?php foreach ($allProfessors as $prof): ?>
                <option value="<?= (int)$prof['id'] ?>" <?= in_array((int)$prof['id'], $selectedProfessorIds, true) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($prof['nome']) ?><?= !empty($prof['email']) ? ' (' . htmlspecialchars($prof['email']) . ')' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-500 mt-2">Use Cmd/Ctrl para selecionar múltiplos professores.</p>
        </div>
        <?php endif; ?>

        <!-- 2. Como definir o tema -->
        <div class="border-t border-gray-200 pt-6">
            <h4 class="text-md font-semibold text-gray-800 mb-3">Como definir o tema</h4>
            <div class="space-y-2 mb-4">
                <label class="flex items-center cursor-pointer">
                    <input type="radio" name="theme_mode" value="configurar" class="theme-mode-radio text-purple-600 focus:ring-purple-500" <?= $themeMode === 'configurar' ? 'checked' : '' ?>>
                    <span class="ml-2 text-sm text-gray-700">Configurar tema (definir tema e repertório em texto)</span>
                </label>
                <label class="flex items-center cursor-pointer">
                    <input type="radio" name="theme_mode" value="arquivo" class="theme-mode-radio text-purple-600 focus:ring-purple-500" <?= $themeMode === 'arquivo' ? 'checked' : '' ?>>
                    <span class="ml-2 text-sm text-gray-700">Tema pronto (PDF ou imagem)</span>
                </label>
            </div>

            <div id="configurar-tema-block" class="space-y-4 <?= $themeMode !== 'configurar' ? 'hidden' : '' ?>">
                <div>
                    <label for="theme" class="block text-sm font-semibold text-gray-700 mb-2">Tema da Redação</label>
                    <input type="text" id="theme" name="theme" value="<?= $proposal ? htmlspecialchars($proposal['theme'] ?? '') : '' ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="Ex: O impacto da inteligência artificial no mercado de trabalho">
                </div>
                <div>
                    <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                        <label for="contexto-quill-container" class="block text-sm font-semibold text-gray-700">Contexto / Descrição</label>
                        <button type="button" id="btn-gerar-contexto-ia" class="text-sm px-3 py-1.5 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200">Gerar com IA</button>
                    </div>
                    <p class="text-sm text-gray-500 mb-2">Com base no tema acima, use &quot;Gerar com IA&quot; para criar o contexto. Use a barra de ferramentas (negrito, itálico, listas, imagem) e o ícone de imagem para inserir ou colar (Ctrl+V) imagens. As imagens são salvas no S3.</p>
                    <input type="hidden" name="contexto" id="contexto-hidden" value="">
                    <div id="contexto-quill-wrapper" class="quill-contexto-wrap quill-proposta border border-gray-300 rounded-lg bg-white">
                        <div id="contexto-toolbar" class="ql-toolbar ql-snow">
                            <span class="ql-formats">
                                <button type="button" class="ql-bold" title="Negrito"></button>
                                <button type="button" class="ql-italic" title="Itálico"></button>
                            </span>
                            <span class="ql-formats">
                                <select class="ql-header" title="Estilo">
                                    <option value="1">Título 1</option>
                                    <option value="2">Título 2</option>
                                    <option value="3">Título 3</option>
                                    <option selected>Normal</option>
                                </select>
                            </span>
                            <span class="ql-formats">
                                <button type="button" class="ql-list" value="ordered" title="Lista numerada"></button>
                                <button type="button" class="ql-list" value="bullet" title="Lista com marcadores"></button>
                            </span>
                            <span class="ql-formats">
                                <button type="button" class="ql-image" title="Inserir imagem (salvo no S3)"></button>
                            </span>
                        </div>
                        <div id="contexto-quill-container" style="min-height: 160px;"></div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Repertório</label>
                    <p class="text-sm text-gray-500 mb-3">Textos de repertório ou instruções. Adicione quantos precisar. Clique em &quot;Editar&quot; para alterar.</p>
                    <div id="repertorios-container" class="space-y-4">
                        <?php
                        $repertorios = [];
                        if (!empty($proposal['repertoire'])) {
                            $raw = trim($proposal['repertoire']);
                            if (preg_match('/^\s*\[/', $raw)) {
                                $dec = json_decode($raw, true);
                                $repertorios = is_array($dec) ? $dec : [$raw];
                            } else {
                                $repertorios = [strip_tags($raw)];
                            }
                        }
                        if (empty($repertorios)) $repertorios = [''];
                        foreach ($repertorios as $idx => $txt):
                            $isEmpty = trim($txt) === '';
                            $initialMode = $isEmpty ? 'edit' : 'view';
                        ?>
                        <div class="repertorio-item border border-gray-200 rounded-lg p-3 bg-gray-50/50" data-mode="<?= $initialMode ?>">
                            <div class="repertorio-view <?= $initialMode === 'view' ? '' : 'hidden' ?>">
                                <div class="repertorio-view-content text-gray-700 prose prose-sm max-w-none min-h-[40px]"><?= $isEmpty ? '<span class="text-gray-400">Vazio</span>' : $txt ?></div>
                                <div class="flex justify-between items-center mt-2 flex-wrap gap-2">
                                    <button type="button" class="btn-editar-repertorio text-sm px-3 py-1.5 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200">Editar</button>
                                    <button type="button" class="btn-remove-repertorio px-2 py-1.5 text-red-600 hover:bg-red-50 rounded-lg text-sm" title="Remover">✕ Remover</button>
                                </div>
                            </div>
                            <div class="repertorio-edit <?= $initialMode === 'edit' ? '' : 'hidden' ?>">
                                <div class="repertorio-quill-wrap quill-proposta border border-gray-300 rounded-lg bg-white">
                                    <div id="rep-toolbar-<?= (int)$idx ?>" class="ql-toolbar ql-snow">
                                        <span class="ql-formats"><button type="button" class="ql-bold" title="Negrito"></button><button type="button" class="ql-italic" title="Itálico"></button></span>
                                        <span class="ql-formats"><select class="ql-header"><option value="1">Título 1</option><option value="2">Título 2</option><option value="3">Título 3</option><option selected>Normal</option></select></span>
                                        <span class="ql-formats"><button type="button" class="ql-list" value="ordered"></button><button type="button" class="ql-list" value="bullet"></button></span>
                                        <span class="ql-formats"><button type="button" class="ql-image" title="Inserir imagem (S3)"></button></span>
                                    </div>
                                    <div id="rep-quill-<?= (int)$idx ?>" class="repertorio-quill-editor"></div>
                                </div>
                                <div class="flex justify-end gap-2 mt-2">
                                    <button type="button" class="btn-salvar-repertorio text-sm px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Salvar</button>
                                    <button type="button" class="btn-remove-repertorio px-2 py-1.5 text-red-600 hover:bg-red-50 rounded-lg text-sm" title="Remover">✕ Remover</button>
                                </div>
                                <input type="hidden" name="repertorios[]" class="repertorio-hidden" value="<?= htmlspecialchars($txt) ?>">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex justify-between items-center mt-3 flex-wrap gap-2">
                        <button type="button" id="btn-add-repertorio" class="text-sm px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 border border-gray-300">+ Adicionar mais</button>
                        <span class="text-sm text-gray-500">Salve a proposta ao final para guardar as alterações.</span>
                    </div>
                </div>
            </div>

            <div id="tema-pronto-block" class="space-y-4 <?= $themeMode !== 'arquivo' ? 'hidden' : '' ?>">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Subir documento (PDF) ou imagem</label>
                    <p class="text-sm text-gray-500 mb-2">O aluno verá este arquivo como tema da proposta.</p>
                    <input type="file" id="temaProntoUpload" name="tema_pronto" accept=".pdf,image/jpeg,image/png,image/gif,image/webp" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                </div>
                <div id="tema-pronto-preview" class="border border-gray-300 rounded-lg p-4 min-h-[160px] bg-gray-50">
                    <p class="text-sm text-gray-500 hidden" id="tema-pronto-placeholder">Nenhum arquivo selecionado. O preview aparecerá abaixo após enviar.</p>
                    <div id="tema-pronto-preview-content"><?php if ($temaProntoFile): ?><p class="text-sm text-gray-600 mb-2">Arquivo do tema:</p><?php if (preg_match('/\.(jpe?g|png|gif|webp)$/i', $temaProntoFile)): ?><img src="<?= htmlspecialchars($temaProntoFile) ?>" alt="Tema" class="max-w-full max-h-64 object-contain rounded border"><?php else: ?><a href="<?= htmlspecialchars($temaProntoFile) ?>" target="_blank" rel="noopener" class="text-purple-600 hover:underline">Abrir arquivo (PDF)</a><?php endif; ?><?php else: ?><span class="text-sm text-gray-500">—</span><?php endif; ?></div>
                </div>
            </div>
        </div>

        <input type="hidden" name="images_json" id="images_json" value="<?= $proposal && $proposal['images_json'] !== null && $proposal['images_json'] !== '' ? htmlspecialchars($proposal['images_json']) : '[]' ?>">

        <!-- 3. Modo de envio do aluno -->
        <div class="border-t border-gray-200 pt-6">
            <h4 class="text-md font-semibold text-gray-800 mb-3">Como o aluno deve enviar a redação</h4>
            <div class="space-y-2">
                <label class="flex items-center cursor-pointer">
                    <input type="radio" name="submission_mode" value="texto" class="text-purple-600 focus:ring-purple-500" <?= $submissionMode === 'texto' ? 'checked' : '' ?>>
                    <span class="ml-2 text-sm text-gray-700">Digitar no editor (com opção de upload + OCR)</span>
                </label>
                <label class="flex items-center cursor-pointer">
                    <input type="radio" name="submission_mode" value="foto" class="text-purple-600 focus:ring-purple-500" <?= $submissionMode === 'foto' ? 'checked' : '' ?>>
                    <span class="ml-2 text-sm text-gray-700">Somente foto da redação manuscrita (sem transcrição obrigatória)</span>
                </label>
                <label class="flex items-center cursor-pointer">
                    <input type="radio" name="submission_mode" value="texto_ou_foto" class="text-purple-600 focus:ring-purple-500" <?= $submissionMode === 'texto_ou_foto' ? 'checked' : '' ?>>
                    <span class="ml-2 text-sm text-gray-700">Aluno escolhe: digitar ou enviar só a foto</span>
                </label>
            </div>
            <p class="text-xs text-gray-500 mt-2">No modo foto, o professor corrige rabiscando diretamente na imagem (tablet/celular com caneta ou dedo).</p>
        </div>

        <!-- 4. Exibir título da redação para o aluno -->
        <div class="border-t border-gray-200 pt-6">
            <label class="flex items-center cursor-pointer">
                <input type="hidden" name="show_title_field" id="show_title_field" value="<?= $showTitleField ?>">
                <input type="checkbox" id="show_title_field_cb" <?= $showTitleField ? 'checked' : '' ?> class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                <span class="ml-2 text-sm text-gray-700">Exibir para o aluno o campo de título da redação</span>
            </label>
        </div>

        <!-- 4. Turmas e alunos -->
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Turmas <span class="text-red-500">*</span></label>
            <p class="text-sm text-gray-500 mb-2">Selecione as turmas que poderão ver esta proposta (pode escolher mais de uma).</p>
            <div class="border border-gray-300 rounded-lg p-3 max-h-40 overflow-y-auto space-y-2">
                <?php if (empty($turmas)): ?>
                    <p class="text-sm text-gray-500">Você não possui turmas vinculadas. Configure no painel admin.</p>
                <?php else: ?>
                    <?php foreach ($turmas as $t): ?>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="turma_ids[]" value="<?= (int)$t['id'] ?>" class="turma-cb rounded border-gray-300 text-purple-600 focus:ring-purple-500" <?= in_array((int)$t['id'], $proposalTurmas ?? []) ? 'checked' : '' ?>>
                        <span class="ml-2 text-sm text-gray-700"><?= htmlspecialchars($t['nome']) ?></span>
                    </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Alunos (opcional)</label>
            <p class="text-sm text-gray-500 mb-2">Deixe em branco para todos os alunos das turmas selecionadas. Ou selecione apenas os alunos que poderão ver a proposta.</p>
            <div id="alunosContainer" class="border border-gray-300 rounded-lg p-3 max-h-48 overflow-y-auto space-y-2 hidden">
                <p id="alunosLoading" class="text-sm text-gray-500 hidden">Carregando...</p>
                <div id="alunosList"></div>
            </div>
        </div>

        <!-- 5. Período de realização -->
        <div class="border-t border-gray-200 pt-6">
            <h4 class="text-md font-semibold text-gray-800 mb-3">Período para o aluno realizar</h4>
            <p class="text-sm text-gray-500 mb-3">Data e horário de início e fim em que a proposta ficará disponível para entrega. Deixe em branco para sem limite.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="starts_at" class="block text-sm font-medium text-gray-700 mb-1">Data e horário de início</label>
                    <input type="datetime-local" id="starts_at" name="starts_at" value="<?= htmlspecialchars($startsAt) ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                <div>
                    <label for="ends_at" class="block text-sm font-medium text-gray-700 mb-1">Data e horário de fim</label>
                    <input type="datetime-local" id="ends_at" name="ends_at" value="<?= htmlspecialchars($endsAt) ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
            </div>
        </div>

        <!-- 6. Status -->
        <div class="border-t border-gray-200 pt-6">
            <label class="flex items-center">
                <input type="radio" name="status" value="draft" <?= ($proposal && $proposal['status'] === 'draft') || !$proposal ? 'checked' : '' ?> class="text-purple-600 focus:ring-purple-500">
                <span class="ml-2 text-sm text-gray-700">Rascunho</span>
            </label>
            <label class="flex items-center mt-1">
                <input type="radio" name="status" value="published" <?= ($proposal && $proposal['status'] === 'published') ? 'checked' : '' ?> class="text-purple-600 focus:ring-purple-500">
                <span class="ml-2 text-sm text-gray-700">Publicada (visível para alunos)</span>
            </label>
        </div>
        <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"></div>
        <div id="successMessage" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg"></div>
        <div class="flex justify-end space-x-4">
            <a href="<?= URL . $basePrefix ?>" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Cancelar</a>
            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700"><?= $proposal ? 'Atualizar' : 'Salvar' ?></button>
        </div>
    </form>
</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
(function() {
    const form = document.getElementById('form');
    const boardSelect = document.getElementById('board_id');
    const textTypeSelect = document.getElementById('text_type_id');
    const isEdit = <?= $proposal ? 'true' : 'false' ?>;
    const proposalId = <?= $proposal ? (int)$proposal['id'] : 'null' ?>;
    const token = document.querySelector('input[name="_token"]').value;
    const proposalStudents = <?= json_encode($proposalStudents ?? []) ?>;
    const contextoInitial = <?= json_encode($proposal['contexto'] ?? '') ?>;
    const repertoriosInitial = <?= json_encode($repertorios ?? ['']) ?>;

    // Toggle "Configurar tema" vs "Tema pronto" blocks
    const configurarBlock = document.getElementById('configurar-tema-block');
    const temaProntoBlock = document.getElementById('tema-pronto-block');
    document.querySelectorAll('.theme-mode-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            const isConfigurar = this.value === 'configurar';
            configurarBlock.classList.toggle('hidden', !isConfigurar);
            temaProntoBlock.classList.toggle('hidden', isConfigurar);
        });
    });

    // Sync show_title_field hidden with checkbox
    const showTitleCb = document.getElementById('show_title_field_cb');
    const showTitleHidden = document.getElementById('show_title_field');
    function syncShowTitle() {
        showTitleHidden.value = showTitleCb.checked ? '1' : '0';
    }
    showTitleCb.addEventListener('change', syncShowTitle);
    syncShowTitle();

    // Tema pronto: upload + preview
    const temaProntoUpload = document.getElementById('temaProntoUpload');
    const temaProntoFileHidden = document.getElementById('tema_pronto_file');
    const temaProntoPlaceholder = document.getElementById('tema-pronto-placeholder');
    const temaProntoPreviewContent = document.getElementById('tema-pronto-preview-content');
    function setTemaProntoPreview(url) {
        if (!temaProntoFileHidden) return;
        temaProntoFileHidden.value = url || '';
        if (temaProntoPlaceholder) temaProntoPlaceholder.classList.toggle('hidden', !!url);
        if (!temaProntoPreviewContent) return;
        if (!url) {
            temaProntoPreviewContent.innerHTML = '<span class="text-sm text-gray-500">—</span>';
            return;
        }
        if (/\.(jpe?g|png|gif|webp)(\?|$)/i.test(url)) {
            temaProntoPreviewContent.innerHTML = '<p class="text-sm text-gray-600 mb-2">Preview:</p><img src="' + url.replace(/"/g, '&quot;') + '" alt="Tema" class="max-w-full max-h-64 object-contain rounded border">';
        } else {
            temaProntoPreviewContent.innerHTML = '<p class="text-sm text-gray-600 mb-2">Arquivo do tema:</p><a href="' + url.replace(/"/g, '&quot;') + '" target="_blank" rel="noopener" class="text-purple-600 hover:underline">Abrir arquivo (PDF)</a>';
        }
    }
    if (temaProntoUpload) {
        temaProntoUpload.addEventListener('change', function() {
            const file = this.files && this.files[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('_token', token);
            fd.append('tema_pronto', file);
            fetch('<?= URL . $basePrefix ?>/upload-tema-pronto', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success && d.file_url) {
                        setTemaProntoPreview(d.file_url);
                    } else {
                        alert(d.error || 'Erro no upload');
                    }
                })
                .catch(() => alert('Erro de conexão'));
            this.value = '';
        });
    }
    if (temaProntoFileHidden && temaProntoFileHidden.value && temaProntoPlaceholder) {
        temaProntoPlaceholder.classList.add('hidden');
    }

    // Upload de imagem para Quill (contexto e repertório)
    function uploadQuillImage(file) {
        if (!file || !file.type.startsWith('image/')) return Promise.reject(new Error('Arquivo não é uma imagem'));
        var fd = new FormData();
        fd.append('_token', token);
        fd.append('imagem', file);
        return fetch('<?= URL . $basePrefix ?>/upload-imagem', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d || !d.success) throw new Error(d && d.error ? d.error : 'Erro no upload');
                return d.image_url || '';
            });
    }

    function setupQuillImageHandlers(quill) {
        quill.getModule('toolbar').addHandler('image', function() {
            var input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');
            input.onchange = function() {
                var file = input.files && input.files[0];
                if (!file) return;
                var range = quill.getSelection(true) || { index: quill.getLength() };
                uploadQuillImage(file).then(function(url) {
                    quill.insertEmbed(range.index, 'image', url);
                    quill.setSelection(range.index + 1);
                }).catch(function(err) { alert('Erro ao enviar imagem: ' + (err.message || err)); });
                input.value = '';
            };
            input.click();
        });
        if (quill.root) {
            quill.root.addEventListener('paste', function(e) {
                var items = e.clipboardData && e.clipboardData.items;
                if (!items) return;
                for (var i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') !== -1) {
                        e.preventDefault();
                        var file = items[i].getAsFile();
                        if (!file) return;
                        var range = quill.getSelection(true) || { index: quill.getLength() };
                        uploadQuillImage(file).then(function(url) {
                            quill.insertEmbed(range.index, 'image', url);
                            quill.setSelection(range.index + 1);
                        }).catch(function(err) { alert('Erro ao enviar imagem: ' + (err.message || err)); });
                        return;
                    }
                }
            });
        }
    }

    var quillContexto = null;
    if (typeof Quill !== 'undefined') {
        var contextoEditorEl = document.getElementById('contexto-quill-container');
        var contextoHidden = document.getElementById('contexto-hidden');
        if (contextoEditorEl && contextoHidden && document.getElementById('contexto-toolbar')) {
            quillContexto = new Quill('#contexto-quill-container', {
                theme: 'snow',
                placeholder: 'Digite o contexto ou descrição da proposta...',
                modules: { toolbar: '#contexto-toolbar' }
            });
            quillContexto.root.innerHTML = (contextoInitial || '').trim() || '';
            contextoHidden.value = quillContexto.root.innerHTML;
            setupQuillImageHandlers(quillContexto);
            quillContexto.on('text-change', function() { contextoHidden.value = quillContexto.root.innerHTML; });
        }

        // Um Quill por item de repertório; modo view/editar
        document.querySelectorAll('.repertorio-item').forEach(function(item, idx) {
            var editorEl = item.querySelector('.repertorio-quill-editor');
            var toolbarEl = item.querySelector('.ql-toolbar');
            var hiddenEl = item.querySelector('.repertorio-hidden');
            if (!editorEl || !hiddenEl || !editorEl.id || !toolbarEl) return;
            var q = new Quill('#' + editorEl.id, {
                theme: 'snow',
                placeholder: 'Texto de repertório ou instrução',
                modules: { toolbar: toolbarEl }
            });
            var initial = (repertoriosInitial[idx] !== undefined ? repertoriosInitial[idx] : '').trim();
            q.root.innerHTML = initial || '';
            hiddenEl.value = q.root.innerHTML;
            setupQuillImageHandlers(q);
            q.on('text-change', function() { hiddenEl.value = q.root.innerHTML; });

            item.querySelector('.btn-editar-repertorio') && item.querySelector('.btn-editar-repertorio').addEventListener('click', function() {
                item.querySelector('.repertorio-view').classList.add('hidden');
                item.querySelector('.repertorio-edit').classList.remove('hidden');
                item.setAttribute('data-mode', 'edit');
            });
            item.querySelector('.btn-salvar-repertorio') && item.querySelector('.btn-salvar-repertorio').addEventListener('click', function() {
                var html = q.root.innerHTML;
                hiddenEl.value = html;
                var viewContent = item.querySelector('.repertorio-view-content');
                if (viewContent) viewContent.innerHTML = (html || '').trim() ? html : '<span class="text-gray-400">Vazio</span>';
                item.querySelector('.repertorio-view').classList.remove('hidden');
                item.querySelector('.repertorio-edit').classList.add('hidden');
                item.setAttribute('data-mode', 'view');
            });
        });
    }

    // Gerar contexto com IA (usa o campo Tema da Redação)
    const themeInput = document.getElementById('theme');
    const btnGerarContextoIA = document.getElementById('btn-gerar-contexto-ia');
    if (btnGerarContextoIA && themeInput && quillContexto) {
        btnGerarContextoIA.addEventListener('click', function() {
            var theme = (themeInput.value || '').trim();
            if (!theme) {
                alert('Preencha o campo "Tema da Redação" para gerar o contexto com IA.');
                return;
            }
            btnGerarContextoIA.disabled = true;
            btnGerarContextoIA.textContent = 'Gerando...';
            var fd = new FormData();
            fd.append('_token', token);
            fd.append('theme', theme);
            fetch('<?= URL . $basePrefix ?>/gerar-repertorio-ia', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success && d.text) {
                        var raw = (d.text || '').trim();
                        try {
                            if (raw.charAt(0) === '{') {
                                var data = JSON.parse(raw);
                                var texto = (data.contexto || data.descricao || '').trim();
                                if (data.proposta_intervencao) texto = (texto ? texto + '\n\n' : '') + 'Proposta de intervenção: ' + data.proposta_intervencao;
                                if (texto) quillContexto.root.innerHTML = '<p>' + texto.replace(/\n/g, '</p><p>') + '</p>';
                            }
                            if (!quillContexto.root.innerHTML.trim() && raw) quillContexto.root.innerHTML = '<p>' + raw.replace(/\n/g, '</p><p>').replace(/<[^>]+>/g, '') + '</p>';
                        } catch (e) {
                            if (raw) quillContexto.root.innerHTML = '<p>' + raw.replace(/\n/g, '</p><p>').replace(/<[^>]+>/g, '') + '</p>';
                        }
                        contextoHidden.value = quillContexto.root.innerHTML;
                        if (quillContexto.root.innerHTML.trim()) alert('Contexto gerado com sucesso!');
                    } else {
                        alert(d.error || 'Erro ao gerar contexto');
                    }
                })
                .catch(function() { alert('Erro de conexão'); })
                .finally(function() {
                    btnGerarContextoIA.disabled = false;
                    btnGerarContextoIA.textContent = 'Gerar com IA';
                });
        });
    }

    // Repertório: adicionar mais (cada novo com Quill + imagem)
    const repertoriosContainer = document.getElementById('repertorios-container');
    const btnAddRepertorio = document.getElementById('btn-add-repertorio');
    var repertorioQuillIndex = (repertoriosInitial && repertoriosInitial.length) ? repertoriosInitial.length : 1;
    var repertorioToolbarHtml = '<div class="ql-toolbar ql-snow"><span class="ql-formats"><button type="button" class="ql-bold" title="Negrito"></button><button type="button" class="ql-italic" title="Itálico"></button></span><span class="ql-formats"><select class="ql-header"><option value="1">Título 1</option><option value="2">Título 2</option><option value="3">Título 3</option><option selected>Normal</option></select></span><span class="ql-formats"><button type="button" class="ql-list" value="ordered"></button><button type="button" class="ql-list" value="bullet"></button></span><span class="ql-formats"><button type="button" class="ql-image" title="Inserir imagem (S3)"></button></span></div>';
    if (btnAddRepertorio && repertoriosContainer && typeof Quill !== 'undefined') {
        btnAddRepertorio.addEventListener('click', function() {
            var id = 'rep-quill-' + (repertorioQuillIndex++);
            var div = document.createElement('div');
            div.className = 'repertorio-item border border-gray-200 rounded-lg p-3 bg-gray-50/50';
            div.setAttribute('data-mode', 'edit');
            div.innerHTML = '<div class="repertorio-view hidden"><div class="repertorio-view-content text-gray-700 prose prose-sm max-w-none min-h-[40px]"><span class="text-gray-400">Vazio</span></div><div class="flex justify-between items-center mt-2 flex-wrap gap-2"><button type="button" class="btn-editar-repertorio text-sm px-3 py-1.5 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200">Editar</button><button type="button" class="btn-remove-repertorio px-2 py-1.5 text-red-600 hover:bg-red-50 rounded-lg text-sm" title="Remover">✕ Remover</button></div></div><div class="repertorio-edit"><div class="repertorio-quill-wrap quill-proposta border border-gray-300 rounded-lg bg-white">' + repertorioToolbarHtml + '<div id="' + id + '" class="repertorio-quill-editor"></div></div><div class="flex justify-end gap-2 mt-2"><button type="button" class="btn-salvar-repertorio text-sm px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Salvar</button><button type="button" class="btn-remove-repertorio px-2 py-1.5 text-red-600 hover:bg-red-50 rounded-lg text-sm" title="Remover">✕ Remover</button></div><input type="hidden" name="repertorios[]" class="repertorio-hidden" value=""></div>';
            repertoriosContainer.appendChild(div);
            var toolbarEl = div.querySelector('.ql-toolbar');
            var hiddenEl = div.querySelector('.repertorio-hidden');
            var q = new Quill('#' + id, {
                theme: 'snow',
                placeholder: 'Texto de repertório ou instrução',
                modules: { toolbar: toolbarEl }
            });
            hiddenEl.value = '';
            setupQuillImageHandlers(q);
            q.on('text-change', function() { hiddenEl.value = q.root.innerHTML; });
            div.querySelector('.btn-editar-repertorio').addEventListener('click', function() {
                div.querySelector('.repertorio-view').classList.add('hidden');
                div.querySelector('.repertorio-edit').classList.remove('hidden');
                div.setAttribute('data-mode', 'edit');
            });
            div.querySelector('.btn-salvar-repertorio').addEventListener('click', function() {
                var html = q.root.innerHTML;
                hiddenEl.value = html;
                var viewContent = div.querySelector('.repertorio-view-content');
                if (viewContent) viewContent.innerHTML = (html || '').trim() ? html : '<span class="text-gray-400">Vazio</span>';
                div.querySelector('.repertorio-view').classList.remove('hidden');
                div.querySelector('.repertorio-edit').classList.add('hidden');
                div.setAttribute('data-mode', 'view');
            });
            div.querySelectorAll('.btn-remove-repertorio').forEach(function(btn) { btn.addEventListener('click', function() { div.remove(); }); });
        });
    }
    repertoriosContainer && repertoriosContainer.addEventListener('click', function(e) {
        if (e.target && e.target.classList && e.target.classList.contains('btn-remove-repertorio')) {
            var item = e.target.closest('.repertorio-item');
            if (item) item.remove();
        }
    });

    function loadAlunos() {
        const turmaIds = Array.from(document.querySelectorAll('.turma-cb:checked')).map(cb => cb.value);
        const container = document.getElementById('alunosContainer');
        const list = document.getElementById('alunosList');
        const loading = document.getElementById('alunosLoading');
        if (turmaIds.length === 0) {
            container.classList.add('hidden');
            list.innerHTML = '';
            return;
        }
        container.classList.remove('hidden');
        loading.classList.remove('hidden');
        list.innerHTML = '';
        const params = new URLSearchParams();
        turmaIds.forEach(id => params.append('turma_ids[]', id));
        fetch('<?= URL . $basePrefix ?>/api/alunos-by-turmas?' + params)
            .then(r => r.json())
            .then(alunos => {
                loading.classList.add('hidden');
                let html = '';
                alunos.forEach(a => {
                    const checked = proposalStudents.indexOf(parseInt(a.id)) !== -1 ? ' checked' : '';
                    html += '<label class="flex items-center cursor-pointer"><input type="checkbox" name="student_ids[]" value="' + a.id + '" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500"' + checked + '><span class="ml-2 text-sm text-gray-700">' + (a.nome || '') + ' (' + (a.turma_nome || '') + ')</span></label>';
                });
                list.innerHTML = html || '<p class="text-sm text-gray-500">Nenhum aluno nas turmas selecionadas.</p>';
            })
            .catch(() => { loading.classList.add('hidden'); list.innerHTML = '<p class="text-sm text-red-500">Erro ao carregar alunos.</p>'; });
    }
    document.querySelectorAll('.turma-cb').forEach(cb => cb.addEventListener('change', loadAlunos));
    if (document.querySelector('.turma-cb:checked')) loadAlunos();

    boardSelect.addEventListener('change', function() {
        const boardId = this.value;
        textTypeSelect.innerHTML = '<option value="">Carregando...</option>';
        if (!boardId) { textTypeSelect.innerHTML = '<option value="">Selecione o tipo textual</option>'; return; }
        fetch('<?= URL . $basePrefix ?>/api/tipos-textuais/' + boardId)
            .then(r => r.json())
            .then(arr => {
                let html = '<option value="">Selecione o tipo textual</option>';
                arr.forEach(t => { html += '<option value="' + t.id + '">' + (t.name || '') + '</option>'; });
                textTypeSelect.innerHTML = html;
            })
            .catch(() => { textTypeSelect.innerHTML = '<option value="">Erro ao carregar</option>'; });
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        syncShowTitle();
        if (quillContexto) document.getElementById('contexto-hidden').value = quillContexto.root.innerHTML;
        document.querySelectorAll('.repertorio-item').forEach(function(item) {
            var el = item.querySelector('.ql-editor');
            var hidden = item.querySelector('.repertorio-hidden');
            if (el && hidden) hidden.value = el.innerHTML;
        });
        document.getElementById('errorMessage').classList.add('hidden');
        document.getElementById('successMessage').classList.add('hidden');
        const fd = new FormData(this);
        if (isEdit) fd.append('_method', 'PUT');
        const url = isEdit ? '<?= URL . $basePrefix ?>/' + proposalId : '<?= URL . $basePrefix ?>';
        const errEl = document.getElementById('errorMessage');
        fetch(url, { method: 'POST', body: fd })
            .then(function(r) {
                return r.text().then(function(text) {
                    try {
                        return { ok: r.ok, status: r.status, data: text ? JSON.parse(text) : {} };
                    } catch (e) {
                        return { ok: false, status: r.status, data: { error: r.ok ? 'Resposta inválida do servidor.' : ('Erro ' + r.status + (text ? ': ' + text.substring(0, 200) : '')) } };
                    }
                });
            })
            .then(function(result) {
                var d = result.data;
                if (result.ok && d.success) {
                    document.getElementById('successMessage').textContent = d.message;
                    document.getElementById('successMessage').classList.remove('hidden');
                    setTimeout(function() { window.location.href = '<?= URL . $basePrefix ?>'; }, 1500);
                } else {
                    errEl.textContent = d.error || ('Erro ao salvar (status ' + result.status + '). Tente novamente ou recarregue a página.');
                    errEl.classList.remove('hidden');
                }
            })
            .catch(function(e) {
                errEl.textContent = 'Erro de conexão. Verifique sua internet e se você ainda está logado. ' + (e.message || '');
                errEl.classList.remove('hidden');
            });
    });
})();
</script>
