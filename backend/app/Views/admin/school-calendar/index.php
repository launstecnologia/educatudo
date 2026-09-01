<?php
$events = is_array($events ?? null) ? $events : [];
$classes = is_array($classes ?? null) ? $classes : [];
$students = is_array($students ?? null) ? $students : [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$categorias = ['evento' => 'Evento', 'reunião' => 'Reunião', 'prova' => 'Prova', 'feriado' => 'Feriado', 'passeio' => 'Passeio', 'apresentação' => 'Apresentação'];
$prioridades = ['normal' => 'Normal', 'importante' => 'Importante', 'urgente' => 'Urgente'];
$publicoLabels = ['todos' => 'Todos', 'turmas' => 'Turmas', 'alunos' => 'Alunos específicos'];
$statusClasses = [
    'publicado' => 'bg-green-100 text-green-800',
    'cancelado' => 'bg-red-100 text-red-800',
    'rascunho' => 'bg-gray-100 text-gray-700',
];

$flash_status = (string) ($flash_type ?? '');
include __DIR__ . '/../_partials/flash_message.php';

$page_header_title = 'Calendário escolar';
$page_header_subtitle = 'Eventos exibidos aos responsáveis no app.';
ob_start();
?>
<a href="<?= URL ?>/admin/comunicacao-escolar" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-envelope-open-text mr-2 text-gray-500"></i>Comunicações
</a>
<button type="button" onclick="openEventoEscolarDrawer()" class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
    <i class="fa-solid fa-plus mr-2"></i>Novo evento
</button>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
<?php if (!$events): ?>
    <div class="px-6 py-12 text-center text-gray-500">
        <i class="fa-regular fa-calendar-days text-3xl text-gray-300 mb-3 block"></i>
        <p>Nenhum evento cadastrado.</p>
        <button type="button" onclick="openEventoEscolarDrawer()" class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
            <i class="fa-solid fa-plus mr-2"></i>Novo evento
        </button>
    </div>
<?php else: ?>
    <div class="divide-y divide-gray-100">
    <?php foreach ($events as $e):
        $status = (string) ($e['status'] ?? 'publicado');
        $cat = (string) ($e['categoria'] ?? 'evento');
        $prio = (string) ($e['prioridade'] ?? 'normal');
        $pub = (string) ($e['publico'] ?? 'todos');
    ?>
        <div class="p-5 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                    <?= htmlspecialchars($categorias[$cat] ?? $cat) ?> · <?= htmlspecialchars($prioridades[$prio] ?? $prio) ?>
                </div>
                <h3 class="font-bold text-lg text-gray-900"><?= htmlspecialchars((string) $e['titulo']) ?></h3>
                <p class="text-sm text-gray-500 mt-0.5">
                    <?= date('d/m/Y H:i', strtotime((string) $e['inicio_em'])) ?>
                    <?= !empty($e['local']) ? ' · ' . htmlspecialchars((string) $e['local']) : '' ?>
                    · <?= htmlspecialchars($publicoLabels[$pub] ?? $pub) ?>
                </p>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0">
                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusClasses[$status] ?? 'bg-gray-100 text-gray-700' ?>">
                    <?= htmlspecialchars(ucfirst($status)) ?>
                </span>
                <?php if ($status === 'publicado'): ?>
                    <?php ob_start(); ?>
                    <button type="button" onclick="openEventoEscolarDrawer(<?= (int) $e['id'] ?>)"
                            class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                    </button>
                    <div class="border-t border-gray-100 my-1"></div>
                    <form method="post" action="<?= URL ?>/admin/calendario-escolar/<?= (int) $e['id'] ?>/cancelar" onsubmit="return confirm('Cancelar evento e notificar os responsáveis?')">
                        <input type="hidden" name="_token" value="<?= $csrf ?>">
                        <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fa-solid fa-ban text-red-400 w-4 text-center"></i> Cancelar
                        </button>
                    </form>
                    <?php
                    $row_actions_dropdown_items = ob_get_clean();
                    $row_actions_dropdown_id = 'row-actions-cal-esc-' . (int) $e['id'];
                    include __DIR__ . '/../_partials/row_actions_dropdown.php';
                    ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>

<div id="eventoEscolarDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeEventoEscolarDrawer()"></div>
<aside id="eventoEscolarDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="eventoEscolarDrawerTitle" class="text-xl font-bold text-gray-900">Novo evento</h2>
        <button type="button" onclick="closeEventoEscolarDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form id="evento-escolar-form" method="post" action="<?= URL ?>/admin/calendario-escolar" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= $csrf ?>">
        <input type="hidden" id="evento_escolar_id" value="">
        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Dados do evento</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="esc_titulo" class="block text-sm font-medium text-gray-700 mb-1">Título <span class="text-red-500">*</span></label>
                        <input type="text" id="esc_titulo" name="titulo" required maxlength="255"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="esc_descricao" class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                        <textarea id="esc_descricao" name="descricao" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                    </div>
                    <div>
                        <label for="esc_categoria" class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                        <select id="esc_categoria" name="categoria" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <?php foreach ($categorias as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="esc_prioridade" class="block text-sm font-medium text-gray-700 mb-1">Prioridade</label>
                        <select id="esc_prioridade" name="prioridade" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <?php foreach ($prioridades as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="esc_local" class="block text-sm font-medium text-gray-700 mb-1">Local</label>
                        <input type="text" id="esc_local" name="local"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="esc_inicio" class="block text-sm font-medium text-gray-700 mb-1">Início <span class="text-red-500">*</span></label>
                        <input type="datetime-local" id="esc_inicio" name="inicio_em" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="esc_fim" class="block text-sm font-medium text-gray-700 mb-1">Fim</label>
                        <input type="datetime-local" id="esc_fim" name="fim_em"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" id="esc_dia_inteiro" name="dia_inteiro" value="1" class="w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-500">
                            <span class="text-sm text-gray-700">Evento de dia inteiro</span>
                        </label>
                    </div>
                </div>
            </section>
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Público</h3>
                <div class="flex flex-wrap gap-5 mb-4">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="publico" value="todos" checked class="text-green-600 border-gray-300 focus:ring-green-500">
                        <span class="text-sm text-gray-700">Todos</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="publico" value="turmas" class="text-green-600 border-gray-300 focus:ring-green-500">
                        <span class="text-sm text-gray-700">Turmas</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="publico" value="alunos" class="text-green-600 border-gray-300 focus:ring-green-500">
                        <span class="text-sm text-gray-700">Alunos específicos</span>
                    </label>
                </div>
                <div id="esc-turmas" class="hidden">
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
                <div id="esc-alunos" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Alunos</label>
                    <div class="grid grid-cols-1 gap-2 max-h-56 overflow-y-auto border border-gray-200 rounded-lg p-3">
                        <?php foreach ($students as $s): ?>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="alunos[]" value="<?= (int) $s['id'] ?>" class="rounded border-gray-300 text-green-600">
                            <?= htmlspecialchars((string) ($s['nome'] . ' · ' . ($s['turma_nome'] ?? 'Sem turma'))) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>
        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeEventoEscolarDrawer()" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Cancelar</button>
            <button type="submit" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="evento-escolar-submit-label">Publicar e notificar</span>
            </button>
        </div>
    </form>
</aside>

<script>
var URL_BASE = <?= json_encode(defined('URL') ? URL : '') ?>;

function togglePublicoEscolar() {
    var v = document.querySelector('#evento-escolar-form [name=publico]:checked');
    v = v ? v.value : 'todos';
    document.getElementById('esc-turmas').classList.toggle('hidden', v !== 'turmas');
    document.getElementById('esc-alunos').classList.toggle('hidden', v !== 'alunos');
}
document.querySelectorAll('#evento-escolar-form [name=publico]').forEach(function (r) {
    r.addEventListener('change', togglePublicoEscolar);
});

function showEventoEscolarDrawer() {
    document.getElementById('eventoEscolarDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('eventoEscolarDrawer');
    drawer.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function () { drawer.classList.remove('translate-x-full'); });
    document.body.style.overflow = 'hidden';
}
function closeEventoEscolarDrawer() {
    document.getElementById('eventoEscolarDrawerBackdrop').classList.add('hidden');
    var drawer = document.getElementById('eventoEscolarDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function openEventoEscolarDrawer(id) {
    var form = document.getElementById('evento-escolar-form');
    form.reset();
    document.getElementById('evento_escolar_id').value = '';
    form.action = URL_BASE + '/admin/calendario-escolar';
    form.dataset.mode = 'create';
    document.getElementById('eventoEscolarDrawerTitle').textContent = 'Novo evento';
    document.getElementById('evento-escolar-submit-label').textContent = 'Publicar e notificar';
    document.querySelector('#evento-escolar-form [name=publico][value=todos]').checked = true;
    togglePublicoEscolar();

    if (!id) {
        showEventoEscolarDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    form.action = URL_BASE + '/admin/calendario-escolar/' + id;
    document.getElementById('eventoEscolarDrawerTitle').textContent = 'Editar evento';
    document.getElementById('evento-escolar-submit-label').textContent = 'Salvar e notificar alteração';
    showEventoEscolarDrawer();

    fetch(URL_BASE + '/admin/calendario-escolar/' + id + '/dados', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                alert('Erro: ' + (data.error || 'Não foi possível carregar o evento.'));
                closeEventoEscolarDrawer();
                return;
            }
            var item = data.item;
            document.getElementById('evento_escolar_id').value = item.id;
            document.getElementById('esc_titulo').value = item.titulo || '';
            document.getElementById('esc_descricao').value = item.descricao || '';
            document.getElementById('esc_categoria').value = item.categoria || 'evento';
            document.getElementById('esc_prioridade').value = item.prioridade || 'normal';
            document.getElementById('esc_local').value = item.local || '';
            document.getElementById('esc_inicio').value = item.inicio_em || '';
            document.getElementById('esc_fim').value = item.fim_em || '';
            document.getElementById('esc_dia_inteiro').checked = !!item.dia_inteiro;
            var pub = item.publico || 'todos';
            var radio = document.querySelector('#evento-escolar-form [name=publico][value="' + pub + '"]');
            if (radio) radio.checked = true;
            var turmas = item.turmas || [];
            document.querySelectorAll('#esc-turmas input[type=checkbox]').forEach(function (cb) {
                cb.checked = turmas.indexOf(parseInt(cb.value, 10)) !== -1;
            });
            var alunos = item.alunos || [];
            document.querySelectorAll('#esc-alunos input[type=checkbox]').forEach(function (cb) {
                cb.checked = alunos.indexOf(parseInt(cb.value, 10)) !== -1;
            });
            togglePublicoEscolar();
        })
        .catch(function () {
            alert('Erro de conexão ao carregar o evento.');
            closeEventoEscolarDrawer();
        });
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeEventoEscolarDrawer();
});

(function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get('novo') === '1') {
        openEventoEscolarDrawer();
        return;
    }
    var eventoId = parseInt(params.get('evento') || '', 10);
    if (eventoId > 0) openEventoEscolarDrawer(eventoId);
})();
</script>
