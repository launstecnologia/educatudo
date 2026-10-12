<?php
$proximos = $proximos ?? [];
$passados = $passados ?? [];
$flash_status = (string) ($flash_type ?? '');

$badge = static function (string $tipo): array {
    return [
        'atividade' => ['Entrega', 'bg-amber-100 text-amber-700', 'fa-clipboard-list'],
        'ao_vivo' => ['Aula ao vivo', 'bg-red-100 text-red-700', 'fa-video'],
    ][$tipo] ?? ['Evento', 'bg-gray-100 text-gray-600', 'fa-calendar'];
};

$renderItem = static function (array $e) use ($badge): string {
    [$txt, $cls, $icon] = $badge((string) ($e['tipo'] ?? ''));
    $ts = strtotime((string) ($e['quando'] ?? ''));
    $data = $ts ? date('d/m/Y', $ts) : '';
    $hora = $ts ? date('H:i', $ts) : '';
    $titulo = htmlspecialchars((string) ($e['titulo'] ?? ''));
    $disc = htmlspecialchars((string) ($e['disciplina_nome'] ?? ''));
    $link = URL . htmlspecialchars((string) ($e['link'] ?? '#'));
    ob_start(); ?>
    <a href="<?= $link ?>" class="flex items-center gap-4 bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:border-green-300 hover:shadow transition">
        <div class="flex flex-col items-center justify-center w-14 shrink-0 text-center">
            <span class="text-xs text-gray-400 uppercase"><?= $data !== '' ? date('M', $ts) : '' ?></span>
            <span class="text-xl font-bold text-gray-900 leading-none"><?= $data !== '' ? date('d', $ts) : '--' ?></span>
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $cls ?>"><i class="fa-solid <?= $icon ?> mr-1"></i><?= htmlspecialchars($txt) ?></span>
                <span class="text-sm font-semibold text-gray-900 truncate"><?= $titulo ?></span>
            </div>
            <div class="mt-1 text-xs text-gray-500"><?= $disc ?><?php if ($hora !== ''): ?> · <?= $hora ?><?php endif; ?></div>
        </div>
        <i class="fa-solid fa-chevron-right text-gray-300"></i>
    </a>
    <?php return (string) ob_get_clean();
};
?>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/cursos" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Agenda</h2>
            <p class="text-sm text-gray-600">Prazos de atividades e aulas ao vivo dos seus cursos.</p>
        </div>
    </div>
</div>

<?php if ($flash_status !== '' && !empty($flash_message)):
    $cls = $flash_status === 'success' ? 'bg-green-100 text-green-800 border-green-200' : ($flash_status === 'error' ? 'bg-red-100 text-red-800 border-red-200' : 'bg-blue-100 text-blue-800 border-blue-200'); ?>
    <div class="mb-6 p-4 rounded-lg border <?= $cls ?>"><?= htmlspecialchars((string) $flash_message) ?></div>
<?php endif; ?>

<?php if (empty($proximos) && empty($passados)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-gray-500"><i class="fa-regular fa-calendar text-3xl mb-3 text-gray-300"></i><p>Nenhum evento na sua agenda no momento.</p></div>
<?php else: ?>
    <?php if (!empty($proximos)): ?>
    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Próximos</h3>
    <div class="space-y-3 mb-8">
        <?php foreach ($proximos as $e): echo $renderItem($e); endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($passados)): ?>
    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Anteriores</h3>
    <div class="space-y-3 opacity-75">
        <?php foreach ($passados as $e): echo $renderItem($e); endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
