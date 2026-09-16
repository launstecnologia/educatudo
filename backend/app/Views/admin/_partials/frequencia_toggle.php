<?php
/**
 * Toggle Faltas | Presença em /admin/frequencia.
 *
 * @var string $frequencia_modo faltas|presenca
 */
$frequenciaModo = (string) ($frequencia_modo ?? 'faltas');
$base = rtrim((string) (defined('URL') ? URL : ''), '/');
$qsFaltas = http_build_query(array_merge($_GET, ['modo' => 'faltas']));
$qsPresenca = http_build_query(array_merge($_GET, ['modo' => 'presenca']));
$clsAtivo = 'bg-white text-gray-900 shadow-sm border-gray-200';
$clsInativo = 'text-gray-600 hover:text-gray-900 border-transparent';
?>
<div class="inline-flex rounded-lg border border-gray-200 bg-gray-100 p-0.5 text-sm font-medium">
    <a href="<?= htmlspecialchars($base . '/admin/frequencia?' . $qsFaltas, ENT_QUOTES, 'UTF-8') ?>"
       class="px-3 py-1.5 rounded-md border <?= $frequenciaModo === 'faltas' ? $clsAtivo : $clsInativo ?>">
        Faltas
    </a>
    <a href="<?= htmlspecialchars($base . '/admin/frequencia?' . $qsPresenca, ENT_QUOTES, 'UTF-8') ?>"
       class="px-3 py-1.5 rounded-md border <?= $frequenciaModo === 'presenca' ? $clsAtivo : $clsInativo ?>">
        Presença
    </a>
</div>
