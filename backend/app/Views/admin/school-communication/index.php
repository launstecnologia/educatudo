<?php
$items = is_array($items ?? null) ? $items : [];
$classes = is_array($classes ?? null) ? $classes : [];
$students = is_array($students ?? null) ? $students : [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$publicoLabels = ['todos' => 'Todos os responsáveis', 'turmas' => 'Por turma', 'alunos' => 'Individual'];
$prioridadeLabels = ['normal' => 'Normal', 'importante' => 'Importante', 'urgente' => 'Urgente'];

$flash_status = (string) ($flash_type ?? '');
include __DIR__ . '/../_partials/flash_message.php';

$page_header_title = 'Comunicação Escolar';
$page_header_subtitle = 'Mensagens para responsáveis, separadas do mural dos alunos.';
ob_start();
?>
<a href="<?= URL ?>/admin/calendario-escolar" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-regular fa-calendar-days mr-2 text-gray-500"></i>Calendário
</a>
<button type="button" onclick="openComunicacaoDrawer()" class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
    <i class="fa-solid fa-plus mr-2"></i>Nova comunicação
</button>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
<?php if (empty($items)): ?>
    <div class="px-6 py-12 text-center text-gray-500">
        <i class="fa-solid fa-envelope-open-text text-3xl text-gray-300 mb-3 block"></i>
        <p>Nenhuma comunicação publicada.</p>
        <button type="button" onclick="openComunicacaoDrawer()" class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
            <i class="fa-solid fa-plus mr-2"></i>Nova comunicação
        </button>
    </div>
<?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Comunicação</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Público</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prioridade</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Leituras</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Respostas</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($items as $item):
                $pub = (string) ($item['publico'] ?? 'todos');
                $prio = (string) ($item['prioridade'] ?? 'normal');
            ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-4">
                        <strong class="text-gray-900"><?= htmlspecialchars((string) $item['titulo']) ?></strong>
                        <div class="text-xs text-gray-500 mt-0.5"><?= date('d/m/Y H:i', strtotime((string) $item['created_at'])) ?> · <?= (int) $item['attachment_count'] ?> anexo(s)</div>
                    </td>
                    <td class="px-4 py-4 text-gray-600"><?= htmlspecialchars($publicoLabels[$pub] ?? $pub) ?></td>
                    <td class="px-4 py-4 text-gray-600"><?= htmlspecialchars($prioridadeLabels[$prio] ?? $prio) ?></td>
                    <td class="px-4 py-4 text-center"><?= (int) $item['read_count'] ?></td>
                    <td class="px-4 py-4 text-center"><?= (int) $item['reply_count'] ?></td>
                    <td class="px-4 py-4 text-right">
                        <a class="text-sm font-medium text-blue-600 hover:text-blue-800" href="<?= URL ?>/admin/comunicacao-escolar/<?= (int) $item['id'] ?>">Abrir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>

<div id="comunicacaoDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeComunicacaoDrawer()"></div>
<aside id="comunicacaoDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-900">Nova comunicação</h2>
        <button type="button" onclick="closeComunicacaoDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form id="comunicacao-form" method="post" action="<?= URL ?>/admin/comunicacao-escolar" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden">
        <input type="hidden" name="_token" value="<?= $csrf ?>">
        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <p class="text-sm text-gray-600">Será enviada aos responsáveis no app e por push.</p>
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Mensagem</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="com_titulo" class="block text-sm font-medium text-gray-700 mb-1">Título <span class="text-red-500">*</span></label>
                        <input type="text" id="com_titulo" name="titulo" required maxlength="255" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="com_conteudo" class="block text-sm font-medium text-gray-700 mb-1">Mensagem <span class="text-red-500">*</span></label>
                        <textarea id="com_conteudo" name="conteudo" rows="6" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                    </div>
                    <div>
                        <label for="com_prioridade" class="block text-sm font-medium text-gray-700 mb-1">Prioridade</label>
                        <select id="com_prioridade" name="prioridade" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="normal">Normal</option>
                            <option value="importante">Importante</option>
                            <option value="urgente">Urgente</option>
                        </select>
                    </div>
                    <div>
                        <label for="com_expires" class="block text-sm font-medium text-gray-700 mb-1">Expira em</label>
                        <input type="datetime-local" id="com_expires" name="expires_at" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="permite_resposta" value="1" checked class="w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-500">
                            <span class="text-sm text-gray-700">Permitir resposta</span>
                        </label>
                    </div>
                </div>
            </section>
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Destinatários</h3>
                <div class="flex flex-wrap gap-5 mb-4">
                    <label class="inline-flex items-center gap-2 cursor-pointer"><input type="radio" name="publico" value="todos" checked class="text-green-600 border-gray-300 focus:ring-green-500"><span class="text-sm text-gray-700">Todos os responsáveis</span></label>
                    <label class="inline-flex items-center gap-2 cursor-pointer"><input type="radio" name="publico" value="turmas" class="text-green-600 border-gray-300 focus:ring-green-500"><span class="text-sm text-gray-700">Por turma</span></label>
                    <label class="inline-flex items-center gap-2 cursor-pointer"><input type="radio" name="publico" value="alunos" class="text-green-600 border-gray-300 focus:ring-green-500"><span class="text-sm text-gray-700">Individual</span></label>
                </div>
                <div id="com-turmas" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Turmas</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-3">
                        <?php foreach ($classes as $c): ?>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="turmas[]" value="<?= (int) $c['id'] ?>" class="rounded border-gray-300 text-green-600">
                            <?= htmlspecialchars((string) $c['nome']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div id="com-alunos" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Alunos / responsáveis</label>
                    <input type="search" id="com-aluno-filtro" placeholder="Pesquisar aluno..." class="w-full px-3 py-2 mb-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    <div id="com-alunos-lista" class="grid grid-cols-1 gap-2 max-h-56 overflow-y-auto border border-gray-200 rounded-lg p-3">
                        <?php foreach ($students as $s): ?>
                        <label class="flex items-center gap-2 text-sm text-gray-700" data-nome="<?= htmlspecialchars(mb_strtolower((string) $s['nome'])) ?>">
                            <input type="checkbox" name="alunos[]" value="<?= (int) $s['id'] ?>" class="rounded border-gray-300 text-green-600">
                            <?= htmlspecialchars((string) ($s['nome'] . ' · ' . ($s['turma_nome'] ?? 'Sem turma'))) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Anexos</h3>
                <input type="file" name="attachments[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf,.doc,.docx,.txt" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                <p class="text-xs text-gray-500 mt-1">Até 10 MB por arquivo.</p>
            </section>
        </div>
        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeComunicacaoDrawer()" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Cancelar</button>
            <button type="submit" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">Publicar e notificar</button>
        </div>
    </form>
</aside>

<script>
function togglePublicoComunicacao() {
    var v = document.querySelector('#comunicacao-form [name=publico]:checked');
    v = v ? v.value : 'todos';
    document.getElementById('com-turmas').classList.toggle('hidden', v !== 'turmas');
    document.getElementById('com-alunos').classList.toggle('hidden', v !== 'alunos');
}
document.querySelectorAll('#comunicacao-form [name=publico]').forEach(function (r) {
    r.addEventListener('change', togglePublicoComunicacao);
});
document.getElementById('com-aluno-filtro').addEventListener('input', function () {
    var q = this.value.toLowerCase();
    document.querySelectorAll('#com-alunos-lista label').forEach(function (el) {
        el.classList.toggle('hidden', q !== '' && (el.getAttribute('data-nome') || '').indexOf(q) === -1);
    });
});

function showComunicacaoDrawer() {
    document.getElementById('comunicacaoDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('comunicacaoDrawer');
    drawer.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function () { drawer.classList.remove('translate-x-full'); });
    document.body.style.overflow = 'hidden';
}
function closeComunicacaoDrawer() {
    document.getElementById('comunicacaoDrawerBackdrop').classList.add('hidden');
    var drawer = document.getElementById('comunicacaoDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
function openComunicacaoDrawer() {
    document.getElementById('comunicacao-form').reset();
    document.querySelector('#comunicacao-form [name=publico][value=todos]').checked = true;
    togglePublicoComunicacao();
    showComunicacaoDrawer();
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeComunicacaoDrawer();
});
if (new URLSearchParams(window.location.search).get('novo') === '1') {
    openComunicacaoDrawer();
}
</script>
