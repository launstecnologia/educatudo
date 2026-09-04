<?php
$avatares = is_array($avatares ?? null) ? $avatares : [];
$flash_status = ($flash_message ?? '') !== ''
    ? (($flash_type ?? '') === 'error' ? 'error' : 'success')
    : '';

$page_header_title = 'Avatares dos Alunos';
$page_header_subtitle = 'Adicione imagens 500x500px para aparecerem na escolha de avatar do portal do aluno.';
$page_header_actions = '
    <button type="button" onclick="document.getElementById(\'avatar-upload\').click()" class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
        <i class="fa-solid fa-upload mr-2"></i>
        Adicionar avatares
    </button>';
?>

<?php include __DIR__ . '/../_partials/page_header_list.php'; ?>
<?php include __DIR__ . '/../_partials/flash_message.php'; ?>

<div class="space-y-6">
    <form method="POST" action="<?= URL ?>/admin/avatares-alunos/upload" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <div class="flex flex-col lg:flex-row lg:items-center gap-4">
            <div class="flex-1">
                <label for="avatar-upload" class="block text-sm font-medium text-gray-700 mb-2">Imagens dos avatares</label>
                <input id="avatar-upload" type="file" name="avatares[]" accept=".jpg,.jpeg,.png,.webp" multiple
                       class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                <p class="mt-2 text-sm text-gray-600">Formatos aceitos: JPG, PNG e WebP. Tamanho obrigatorio: 500x500px.</p>
            </div>
            <button type="submit" class="inline-flex items-center justify-center px-6 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors">
                <i class="fa-solid fa-check mr-2"></i>
                Enviar
            </button>
        </div>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4 flex-wrap">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Catalogo disponivel</h3>
                <p class="text-sm text-gray-600"><?= count($avatares) ?> avatar(es) exibidos para os alunos.</p>
            </div>
            <?php if (!empty($avatares)): ?>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" id="btn-selecionar-avatares" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    <i class="fa-regular fa-square-check mr-2"></i>
                    Selecionar todos
                </button>
                <form id="form-excluir-selecionados" method="POST" action="<?= URL ?>/admin/avatares-alunos/excluir-selecionados" onsubmit="return confirmarExcluirSelecionados();">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                    <button type="submit" id="btn-excluir-selecionados" disabled class="inline-flex items-center px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fa-solid fa-trash-can mr-2"></i>
                        Excluir selecionados
                    </button>
                </form>
                <form method="POST" action="<?= URL ?>/admin/avatares-alunos/excluir-todos" onsubmit="return confirm('Excluir todos os avatares disponiveis para alunos? As selecoes atuais dos alunos serao limpas.');">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                    <button type="submit" class="inline-flex items-center px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 transition-colors">
                        <i class="fa-solid fa-trash-can mr-2"></i>
                        Excluir todos
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($avatares)): ?>
            <div class="px-6 py-12 text-center">
                <div class="mx-auto w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                    <i class="fa-regular fa-image text-2xl text-gray-400"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-900">Nenhum avatar cadastrado</h3>
                <p class="mt-1 text-sm text-gray-600">Envie imagens 500x500px para liberar a escolha no acesso do aluno.</p>
            </div>
        <?php else: ?>
            <div class="p-6 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-4">
                <?php foreach ($avatares as $avatar): ?>
                <div class="border border-gray-200 rounded-lg p-3 bg-white hover:shadow-sm transition-shadow">
                    <label class="mb-2 inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                        <input type="checkbox"
                               name="arquivos[]"
                               value="<?= htmlspecialchars((string) $avatar['arquivo']) ?>"
                               form="form-excluir-selecionados"
                               class="avatar-checkbox rounded border-gray-300 text-primary focus:ring-primary">
                        Selecionar
                    </label>
                    <div class="aspect-square rounded-lg bg-gray-50 overflow-hidden border border-gray-100">
                        <img src="<?= htmlspecialchars((string) $avatar['url']) ?>" alt="Avatar" class="w-full h-full object-cover">
                    </div>
                    <div class="mt-3 space-y-1">
                        <p class="text-xs font-medium text-gray-900 truncate" title="<?= htmlspecialchars((string) ($avatar['nome'] ?? $avatar['arquivo'])) ?>">
                            <?= htmlspecialchars((string) ($avatar['nome'] ?? $avatar['arquivo'])) ?>
                        </p>
                        <p class="text-xs text-gray-600"><?= htmlspecialchars((string) $avatar['tamanho']) ?> · <?= (int) $avatar['usos'] ?> uso(s)</p>
                        <p class="text-xs text-gray-500">
                            <?= htmlspecialchars((string) ($avatar['origem'] ?? 'Escola')) ?> · <?= htmlspecialchars((string) $avatar['modificado']) ?>
                        </p>
                    </div>
                    <form method="POST" action="<?= URL ?>/admin/avatares-alunos/excluir" class="mt-3" onsubmit="return confirm('Excluir este avatar? Alunos que usam essa imagem ficarao sem avatar selecionado.');">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                        <input type="hidden" name="arquivo" value="<?= htmlspecialchars((string) $avatar['arquivo']) ?>">
                        <button type="submit" class="w-full inline-flex items-center justify-center px-3 py-2 border border-red-200 rounded-lg text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100 transition-colors">
                            <i class="fa-solid fa-trash-can mr-2"></i>
                            Excluir
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.avatar-checkbox'));
    var btnExcluir = document.getElementById('btn-excluir-selecionados');
    var btnSelecionar = document.getElementById('btn-selecionar-avatares');

    function selecionados() {
        return checkboxes.filter(function(cb) { return cb.checked; }).length;
    }

    function atualizar() {
        var total = selecionados();
        if (btnExcluir) {
            btnExcluir.disabled = total === 0;
            btnExcluir.innerHTML = '<i class="fa-solid fa-trash-can mr-2"></i>' + (total > 0 ? 'Excluir ' + total + ' selecionado(s)' : 'Excluir selecionados');
        }
        if (btnSelecionar) {
            var todos = checkboxes.length > 0 && total === checkboxes.length;
            btnSelecionar.innerHTML = '<i class="fa-regular fa-square-check mr-2"></i>' + (todos ? 'Limpar seleção' : 'Selecionar todos');
        }
    }

    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', atualizar);
    });

    if (btnSelecionar) {
        btnSelecionar.addEventListener('click', function() {
            var marcar = selecionados() !== checkboxes.length;
            checkboxes.forEach(function(cb) { cb.checked = marcar; });
            atualizar();
        });
    }

    window.confirmarExcluirSelecionados = function() {
        var total = selecionados();
        if (total === 0) {
            return false;
        }
        return confirm('Excluir ' + total + ' avatar(es) selecionado(s)? As selecoes atuais dos alunos que usam essas imagens serao limpas.');
    };

    atualizar();
})();
</script>
