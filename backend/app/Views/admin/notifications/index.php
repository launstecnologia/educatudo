<?php
$notificacoes = is_array($notificacoes ?? null) ? $notificacoes : [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));

$flash_status = (string) ($flash_type ?? '');
$flash_message = (string) ($flash_message ?? '');
include __DIR__ . '/../_partials/flash_message.php';

$page_header_title = 'Notificações';
$page_header_subtitle = 'Avisos internos para alunos, professores, administradores e responsáveis.';
ob_start();
?>
<button type="button" onclick="openNotificacaoDrawer()" class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
    <i class="fa-solid fa-plus mr-2"></i>Nova notificação
</button>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
<?php if (empty($notificacoes)): ?>
    <div class="px-6 py-12 text-center text-gray-500">
        <i class="fa-solid fa-bell text-3xl text-gray-300 mb-3 block"></i>
        <p>Nenhuma notificação encontrada.</p>
        <button type="button" onclick="openNotificacaoDrawer()" class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
            <i class="fa-solid fa-plus mr-2"></i>Nova notificação
        </button>
    </div>
<?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notificação</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Enviado por</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prioridade</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destinatários</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($notificacoes as $notificacao):
                $prioridade = (string) ($notificacao['prioridade'] ?? 'normal');
                $prioridadeClasses = [
                    'baixa' => 'bg-gray-100 text-gray-800',
                    'normal' => 'bg-blue-100 text-blue-800',
                    'alta' => 'bg-yellow-100 text-yellow-800',
                    'urgente' => 'bg-red-100 text-red-800',
                ];
                $classe = $prioridadeClasses[$prioridade] ?? 'bg-gray-100 text-gray-800';
                $previewTexto = trim(strip_tags($notificacao['conteudo'] ?? ''));
                $totalDest = (int) ($notificacao['total_destinatarios'] ?? 0);
                $totalLidas = (int) ($notificacao['total_lidas'] ?? 0);
                $percentual = $totalDest > 0 ? ($totalLidas / $totalDest) * 100 : 0;
            ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-4">
                        <strong class="text-gray-900"><?= htmlspecialchars((string) $notificacao['titulo']) ?></strong>
                        <div class="text-xs text-gray-500 mt-0.5">
                            <?= $previewTexto !== '' ? htmlspecialchars(mb_substr($previewTexto, 0, 50)) . (mb_strlen($previewTexto) > 50 ? '…' : '') : 'Sem conteúdo de texto' ?>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-gray-600"><?= htmlspecialchars((string) ($notificacao['nome_enviador'] ?? '')) ?></td>
                    <td class="px-4 py-4">
                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $classe ?>"><?= htmlspecialchars(ucfirst($prioridade)) ?></span>
                    </td>
                    <td class="px-4 py-4 text-gray-600"><?= $totalDest ?> destinatário(s)</td>
                    <td class="px-4 py-4">
                        <div class="text-sm text-gray-900"><?= $totalLidas ?> / <?= $totalDest ?> lidas</div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?= (float) $percentual ?>%"></div>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-gray-500"><?= date('d/m/Y H:i', strtotime((string) $notificacao['created_at'])) ?></td>
                    <td class="px-4 py-4 text-right">
                        <?php ob_start(); ?>
                        <a href="<?= URL ?>/admin/notifications/<?= (int) $notificacao['id'] ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-eye text-gray-400 w-4 text-center"></i> Ver
                        </a>
                        <div class="border-t border-gray-100 my-1"></div>
                        <a href="<?= URL ?>/admin/notifications/<?= (int) $notificacao['id'] ?>/delete"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                           onclick="return confirm('Tem certeza que deseja excluir esta notificação?')">
                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                        </a>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-notif-' . (int) $notificacao['id'];
                        include __DIR__ . '/../_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>

<div id="notificacaoDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeNotificacaoDrawer()"></div>
<aside id="notificacaoDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-900">Nova notificação</h2>
        <button type="button" onclick="closeNotificacaoDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form id="notificacao-form" method="post" action="<?= URL ?>/admin/notifications/store" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden">
        <input type="hidden" name="_token" value="<?= $csrf ?>">
        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Mensagem</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="notif_titulo" class="block text-sm font-medium text-gray-700 mb-1">Título <span class="text-red-500">*</span></label>
                        <input type="text" id="notif_titulo" name="titulo" required maxlength="255"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="Digite o título da notificação">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipos de conteúdo <span class="text-red-500">*</span></label>
                        <div class="flex flex-wrap gap-4">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="incluir_texto" name="tipos_conteudo[]" value="texto" checked class="rounded border-gray-300 text-green-600" onchange="toggleNotifMedia()">
                                <span class="text-sm text-gray-700">Texto</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="incluir_imagem" name="tipos_conteudo[]" value="imagem" class="rounded border-gray-300 text-green-600" onchange="toggleNotifMedia()">
                                <span class="text-sm text-gray-700">Imagem</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="incluir_video" name="tipos_conteudo[]" value="video" class="rounded border-gray-300 text-green-600" onchange="toggleNotifMedia()">
                                <span class="text-sm text-gray-700">Vídeo</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label for="notif_prioridade" class="block text-sm font-medium text-gray-700 mb-1">Prioridade</label>
                        <select id="notif_prioridade" name="prioridade" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="baixa">Baixa</option>
                            <option value="normal" selected>Normal</option>
                            <option value="alta">Alta</option>
                            <option value="urgente">Urgente</option>
                        </select>
                    </div>
                    <div>
                        <label for="notif_expira" class="block text-sm font-medium text-gray-700 mb-1">Expira em</label>
                        <input type="datetime-local" id="notif_expira" name="data_expiracao"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="sm:col-span-2" id="notif-texto-content">
                        <label for="notif_conteudo" class="block text-sm font-medium text-gray-700 mb-1">Conteúdo <span class="text-red-500">*</span></label>
                        <textarea id="notif_conteudo" name="conteudo" rows="6"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                  placeholder="Escreva a mensagem da notificação"></textarea>
                    </div>
                    <div class="sm:col-span-2 hidden" id="notif-image-upload">
                        <label for="arquivo_imagem" class="block text-sm font-medium text-gray-700 mb-1">Imagem</label>
                        <input type="file" id="arquivo_imagem" name="arquivo_imagem" accept="image/jpeg,image/png,image/gif,image/webp"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <p class="text-xs text-gray-500 mt-1">PNG, JPG, GIF ou WebP até 10 MB.</p>
                    </div>
                    <div class="sm:col-span-2 hidden" id="notif-video-url">
                        <label for="video_url" class="block text-sm font-medium text-gray-700 mb-1">URL do vídeo</label>
                        <input type="url" id="video_url" name="video_url"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="https://www.youtube.com/watch?v=...">
                    </div>
                    <div class="sm:col-span-2 hidden" id="notif-video-file">
                        <label for="arquivo_video" class="block text-sm font-medium text-gray-700 mb-1">Arquivo de vídeo</label>
                        <input type="file" id="arquivo_video" name="arquivo_video" accept="video/*"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <p class="text-xs text-gray-500 mt-1">MP4, AVI ou MOV até 50 MB. Informe a URL ou o arquivo.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_update" value="1" class="w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-500">
                            <span class="text-sm text-gray-700">Notificação de atualização do sistema</span>
                        </label>
                        <p class="text-xs text-gray-500 mt-1 ml-6">Ao abrir, o destinatário atualiza a página e entra de novo na conta.</p>
                    </div>
                </div>
            </section>
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Destinatários <span class="text-red-500">*</span></h3>
                <label class="inline-flex items-center gap-2 cursor-pointer mb-4">
                    <input type="checkbox" id="todos_usuarios" name="todos_usuarios" value="1" class="w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-500" onchange="toggleNotifTodos()">
                    <span class="text-sm font-medium text-gray-700">Todos os usuários</span>
                </label>
                <div id="notif-categorias" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-green-400">
                        <input type="checkbox" name="categorias[]" value="todos_admins" class="rounded border-gray-300 text-green-600 notif-categoria" onchange="toggleNotifCategorias()">
                        <span class="text-sm text-gray-700">Todos os administradores</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-green-400">
                        <input type="checkbox" name="categorias[]" value="todos_professores" class="rounded border-gray-300 text-green-600 notif-categoria" onchange="toggleNotifCategorias()">
                        <span class="text-sm text-gray-700">Todos os professores</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-green-400">
                        <input type="checkbox" name="categorias[]" value="todos_alunos" class="rounded border-gray-300 text-green-600 notif-categoria" onchange="toggleNotifCategorias()">
                        <span class="text-sm text-gray-700">Todos os alunos</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-green-400">
                        <input type="checkbox" name="categorias[]" value="todos_pais" class="rounded border-gray-300 text-green-600 notif-categoria" onchange="toggleNotifCategorias()">
                        <span class="text-sm text-gray-700">Todos os responsáveis</span>
                    </label>
                </div>
            </section>
        </div>
        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeNotificacaoDrawer()" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Cancelar</button>
            <button type="submit" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">Enviar notificação</button>
        </div>
    </form>
</aside>

<script>
function toggleNotifMedia() {
    var texto = document.getElementById('incluir_texto').checked;
    var imagem = document.getElementById('incluir_imagem').checked;
    var video = document.getElementById('incluir_video').checked;
    if (!texto && !imagem && !video) {
        document.getElementById('incluir_texto').checked = true;
        texto = true;
    }
    document.getElementById('notif-texto-content').classList.toggle('hidden', !texto);
    document.getElementById('notif-image-upload').classList.toggle('hidden', !imagem);
    document.getElementById('notif-video-url').classList.toggle('hidden', !video);
    document.getElementById('notif-video-file').classList.toggle('hidden', !video);
    document.getElementById('notif_conteudo').required = texto;
}
function toggleNotifTodos() {
    var todos = document.getElementById('todos_usuarios').checked;
    document.getElementById('notif-categorias').classList.toggle('opacity-50', todos);
    if (todos) {
        document.querySelectorAll('.notif-categoria').forEach(function (c) { c.checked = false; });
    }
}
function toggleNotifCategorias() {
    var alguma = Array.prototype.some.call(document.querySelectorAll('.notif-categoria'), function (c) { return c.checked; });
    if (alguma) document.getElementById('todos_usuarios').checked = false;
    toggleNotifTodos();
}
function showNotificacaoDrawer() {
    document.getElementById('notificacaoDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('notificacaoDrawer');
    drawer.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function () { drawer.classList.remove('translate-x-full'); });
    document.body.style.overflow = 'hidden';
}
function closeNotificacaoDrawer() {
    document.getElementById('notificacaoDrawerBackdrop').classList.add('hidden');
    var drawer = document.getElementById('notificacaoDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
function openNotificacaoDrawer() {
    document.getElementById('notificacao-form').reset();
    document.getElementById('incluir_texto').checked = true;
    toggleNotifMedia();
    toggleNotifTodos();
    showNotificacaoDrawer();
}
document.getElementById('notificacao-form').addEventListener('submit', function (e) {
    var texto = document.getElementById('incluir_texto').checked;
    if (texto && document.getElementById('notif_conteudo').value.trim() === '') {
        e.preventDefault();
        alert('Preencha o conteúdo de texto da notificação.');
        return;
    }
    var todos = document.getElementById('todos_usuarios').checked;
    var categorias = document.querySelectorAll('.notif-categoria:checked').length > 0;
    if (!todos && !categorias) {
        e.preventDefault();
        alert('Selecione pelo menos um destinatário.');
    }
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeNotificacaoDrawer();
});
if (new URLSearchParams(window.location.search).get('novo') === '1') {
    openNotificacaoDrawer();
}
</script>
