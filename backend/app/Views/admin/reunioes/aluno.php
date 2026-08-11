<?php
$aluno    = $aluno ?? [];
$reunioes = $reunioes ?? [];
$csrf     = htmlspecialchars($csrf_token ?? '');
$alunoId  = (int) ($aluno['id'] ?? 0);

$page_header_title    = 'ATAs de Reunião com Pais';
$page_header_subtitle = 'Aluno: ' . htmlspecialchars($aluno['nome'] ?? '');

ob_start();
?>
<a href="/admin/students/<?= $alunoId ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">
    <i class="fa-solid fa-arrow-left mr-2"></i> Voltar ao aluno
</a>
<button onclick="document.getElementById('modalNovaAta').classList.remove('hidden')"
        class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-teal-600 hover:bg-teal-700">
    <i class="fa-solid fa-plus mr-2"></i> Nova ATA
</button>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php'; ?>

<?php if (empty($reunioes)): ?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-12 text-center text-gray-500">
    <i class="fa-regular fa-file-lines text-4xl text-gray-300 mb-3 block"></i>
    Nenhuma reunião registrada para este aluno.<br>
    <span class="text-sm">Clique em <strong>Nova ATA</strong> para registrar a primeira reunião com os pais/responsáveis.</span>
</div>
<?php else: ?>
<div class="space-y-4">
<?php foreach ($reunioes as $r):
    $nomes    = $r['anexo_nomes']    ? explode('|', $r['anexo_nomes'])    : [];
    $caminhos = $r['anexo_caminhos'] ? explode('|', $r['anexo_caminhos']) : [];
?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="flex items-start justify-between px-6 py-4 border-b border-gray-100">
        <div>
            <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($r['titulo']) ?></h3>
            <p class="text-sm text-gray-500 mt-0.5">
                <?= date('d/m/Y', strtotime($r['data_reuniao'])) ?>
                <?= $r['hora_inicio'] ? ' · ' . substr($r['hora_inicio'],0,5) : '' ?>
                <?= $r['hora_fim']    ? '–' . substr($r['hora_fim'],0,5) : '' ?>
                <?= $r['local_reuniao'] ? ' · <i class="fa-solid fa-location-dot"></i> ' . htmlspecialchars($r['local_reuniao']) : '' ?>
            </p>
            <?php if ($r['responsavel_nome']): ?>
            <span class="inline-flex items-center gap-1 mt-1 text-xs text-teal-700 bg-teal-50 border border-teal-200 px-2 py-0.5 rounded-full">
                <i class="fa-solid fa-user-tie text-xs"></i> <?= htmlspecialchars($r['responsavel_nome']) ?>
            </span>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= URL ?>/admin/reunioes/aluno/excluir" onsubmit="return confirm('Remover esta ATA?')">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="aluno_id" value="<?= $alunoId ?>">
            <button type="submit" class="text-red-400 hover:text-red-600 text-sm"><i class="fa-solid fa-trash-can"></i></button>
        </form>
    </div>
    <?php if ($r['descricao']): ?>
    <div class="px-6 py-4 text-sm text-gray-700 prose prose-sm max-w-none border-b border-gray-100">
        <?= rich_text_render($r['descricao']) ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($nomes)): ?>
    <div class="px-6 py-3 flex flex-wrap gap-2 bg-gray-50">
        <?php foreach ($nomes as $idx => $nome): ?>
        <a href="<?= URL ?>/<?= htmlspecialchars($caminhos[$idx] ?? '#') ?>" target="_blank"
           class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-100">
            <i class="fa-solid fa-paperclip"></i> <?= htmlspecialchars($nome) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal: Nova ATA -->
<div id="modalNovaAta" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 flex flex-col max-h-[92vh]">
        <div class="sticky top-0 bg-white flex items-center justify-between px-6 py-4 border-b border-gray-100 rounded-t-2xl">
            <h3 class="text-lg font-bold text-gray-900">Nova ATA de Reunião com Pais</h3>
            <button onclick="document.getElementById('modalNovaAta').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto">
            <form method="post" action="<?= URL ?>/admin/reunioes/aluno/salvar" enctype="multipart/form-data" id="formAta" class="p-6 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="aluno_id" value="<?= $alunoId ?>">

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Título / Assunto <span class="text-red-500">*</span></label>
                    <input type="text" name="titulo" required placeholder="Ex: Reunião sobre desempenho acadêmico"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Data <span class="text-red-500">*</span></label>
                        <input type="date" name="data_reuniao" required value="<?= date('Y-m-d') ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Responsável presente</label>
                        <input type="text" name="responsavel_nome" placeholder="Nome do pai/mãe/responsável"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Início</label>
                        <input type="time" name="hora_inicio"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Fim</label>
                        <input type="time" name="hora_fim"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Local</label>
                        <input type="text" name="local_reuniao" placeholder="Sala da direção..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">O que foi discutido</label>
                    <div id="editorAtaDescricao" style="min-height:180px"></div>
                    <textarea name="descricao" id="hiddenAtaDescricao" class="hidden"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5"><i class="fa-solid fa-paperclip mr-1 text-gray-400"></i>Anexos (fotos, documentos)</label>
                    <input type="file" name="anexos[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                        class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100">
                    <p class="text-xs text-gray-400 mt-1">Imagens, PDF, Word, Excel. Múltiplos arquivos permitidos.</p>
                </div>
            </form>
        </div>
        <div class="flex gap-3 px-6 py-4 border-t border-gray-100">
            <button type="button" onclick="document.getElementById('modalNovaAta').classList.add('hidden')"
                class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
            <button type="button" onclick="submitAta()"
                class="flex-1 px-4 py-2.5 bg-teal-600 text-white rounded-lg text-sm font-semibold hover:bg-teal-700">
                <i class="fa-solid fa-check mr-2"></i>Salvar ATA
            </button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.quilljs.com/1.3.7/quill.snow.css">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
var quillAta = new Quill('#editorAtaDescricao', {
    theme: 'snow',
    modules: { toolbar: [['bold','italic','underline'],['blockquote'],[{'list':'ordered'},{'list':'bullet'}],[{'color':[]},{'background':[]}],['clean']] },
    placeholder: 'Descreva o que foi discutido na reunião, decisões tomadas, acordos firmados...'
});

function submitAta() {
    document.getElementById('hiddenAtaDescricao').value = quillAta.root.innerHTML === '<p><br></p>' ? '' : quillAta.root.innerHTML;
    document.getElementById('formAta').submit();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') document.getElementById('modalNovaAta').classList.add('hidden');
});
document.getElementById('modalNovaAta').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>
