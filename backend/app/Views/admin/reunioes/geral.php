<?php
$reunioes = $reunioes ?? [];
$turmas   = $turmas   ?? [];
$csrf     = htmlspecialchars($csrf_token ?? '');

$page_header_title    = 'Reuniões Gerais';
$page_header_subtitle = 'Registre reuniões com turmas, séries ou toda a escola.';

ob_start();
?>
<button onclick="document.getElementById('modalNovaReuniao').classList.remove('hidden')"
        class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-teal-600 hover:bg-teal-700">
    <i class="fa-solid fa-plus mr-2"></i> Nova Reunião
</button>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php'; ?>

<?php if (empty($reunioes)): ?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-12 text-center text-gray-500">
    <i class="fa-solid fa-users text-4xl text-gray-300 mb-3 block"></i>
    Nenhuma reunião geral registrada.<br>
    <span class="text-sm">Clique em <strong>Nova Reunião</strong> para criar o primeiro registro.</span>
</div>
<?php else: ?>
<div class="space-y-4">
<?php foreach ($reunioes as $r):
    $nomes    = $r['anexo_nomes']    ? explode('|', $r['anexo_nomes'])    : [];
    $caminhos = $r['anexo_caminhos'] ? explode('|', $r['anexo_caminhos']) : [];
?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="flex items-start justify-between px-6 py-4 border-b border-gray-100">
        <div class="flex-1 min-w-0">
            <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($r['titulo']) ?></h3>
            <p class="text-sm text-gray-500 mt-0.5">
                <?= date('d/m/Y', strtotime($r['data_reuniao'])) ?>
                <?= $r['hora_inicio'] ? ' · ' . substr($r['hora_inicio'],0,5) : '' ?>
                <?= $r['hora_fim']    ? '–' . substr($r['hora_fim'],0,5) : '' ?>
                <?= $r['local_reuniao'] ? ' · <i class="fa-solid fa-location-dot"></i> ' . htmlspecialchars($r['local_reuniao']) : '' ?>
            </p>
            <?php if ($r['turmas_nomes']): ?>
            <div class="flex flex-wrap gap-1.5 mt-2">
                <?php foreach (explode(', ', $r['turmas_nomes']) as $tn): ?>
                <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 border border-indigo-200 text-indigo-700"><?= htmlspecialchars($tn) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= URL ?>/admin/reunioes/geral/excluir" onsubmit="return confirm('Remover esta reunião?')" class="ml-4 flex-shrink-0">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
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
            <?php
            $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
            $icon = in_array($ext, ['jpg','jpeg','png','webp','gif']) ? 'fa-image' : ($ext === 'pdf' ? 'fa-file-pdf' : 'fa-file');
            ?>
            <i class="fa-solid <?= $icon ?> text-gray-400"></i> <?= htmlspecialchars($nome) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal: Nova Reunião Geral -->
<div id="modalNovaReuniao" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 flex flex-col max-h-[92vh]">
        <div class="sticky top-0 bg-white flex items-center justify-between px-6 py-4 border-b border-gray-100 rounded-t-2xl">
            <h3 class="text-lg font-bold text-gray-900">Nova Reunião Geral</h3>
            <button onclick="document.getElementById('modalNovaReuniao').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto">
            <form method="post" action="<?= URL ?>/admin/reunioes/geral/salvar" enctype="multipart/form-data" id="formReuniao" class="p-6 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Título / Pauta <span class="text-red-500">*</span></label>
                    <input type="text" name="titulo" required placeholder="Ex: Reunião de pais do 1º bimestre"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Data <span class="text-red-500">*</span></label>
                        <input type="date" name="data_reuniao" required value="<?= date('Y-m-d') ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                    </div>
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
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Local</label>
                    <input type="text" name="local_reuniao" placeholder="Ex: Auditório, Pátio, Google Meet..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                </div>

                <?php if (!empty($turmas)): ?>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Turmas / Séries participantes</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-3 bg-gray-50">
                        <?php foreach ($turmas as $t): ?>
                        <label class="flex items-center gap-2 cursor-pointer text-sm">
                            <input type="checkbox" name="turma_ids[]" value="<?= (int) $t['id'] ?>"
                                class="w-4 h-4 text-teal-600 rounded border-gray-300 focus:ring-teal-500">
                            <span class="text-gray-700 truncate"><?= htmlspecialchars($t['nome']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Deixe em branco para indicar reunião com toda a escola.</p>
                </div>
                <?php endif; ?>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Descrição / Ata</label>
                    <div id="editorReuniaoDescricao" style="min-height:180px"></div>
                    <textarea name="descricao" id="hiddenReuniaoDescricao" class="hidden"></textarea>
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
            <button type="button" onclick="document.getElementById('modalNovaReuniao').classList.add('hidden')"
                class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
            <button type="button" onclick="submitReuniao()"
                class="flex-1 px-4 py-2.5 bg-teal-600 text-white rounded-lg text-sm font-semibold hover:bg-teal-700">
                <i class="fa-solid fa-check mr-2"></i>Salvar Reunião
            </button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.quilljs.com/1.3.7/quill.snow.css">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
var quillReuniao = new Quill('#editorReuniaoDescricao', {
    theme: 'snow',
    modules: { toolbar: [['bold','italic','underline'],['blockquote'],[{'list':'ordered'},{'list':'bullet'}],[{'color':[]},{'background':[]}],['clean']] },
    placeholder: 'Descreva a pauta, o que foi discutido, decisões e encaminhamentos...'
});

function submitReuniao() {
    document.getElementById('hiddenReuniaoDescricao').value = quillReuniao.root.innerHTML === '<p><br></p>' ? '' : quillReuniao.root.innerHTML;
    document.getElementById('formReuniao').submit();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') document.getElementById('modalNovaReuniao').classList.add('hidden');
});
document.getElementById('modalNovaReuniao').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>
