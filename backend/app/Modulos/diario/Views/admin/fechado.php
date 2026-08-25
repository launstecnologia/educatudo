<?php
require_once __DIR__ . '/../../Services/ClassDiaryService.php';

use App\Modulos\Diario\Services\ClassDiaryService;

$fechamentoId = (int) ($fechamento['id'] ?? 0);
$abas = [
    'resumo' => 'Resumo',
    'aulas' => 'Aulas',
    'frequencia' => 'Frequência',
];
$abaAtual = array_key_exists($aba ?? '', $abas) ? $aba : 'resumo';
$baseQuery = 'id=' . $fechamentoId;
$periodoInicio = (string) ($periodo['inicio'] ?? '');
$periodoFim = (string) ($periodo['fim'] ?? '');
?>
<div class="mb-6">
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-4">
            <a href="<?= URL ?>/admin/diario?aba=fechados"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
               aria-label="Voltar">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-2xl font-bold text-gray-900">
                        <?= htmlspecialchars((string) ($info['materia_nome'] ?? '')) ?>
                        — <?= htmlspecialchars((string) ($info['turma_nome'] ?? '')) ?>
                    </h2>
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                        <i class="fa-solid fa-lock mr-1.5"></i>Fechado
                    </span>
                </div>
                <p class="text-sm text-gray-600 mt-1">
                    <?= htmlspecialchars((string) ($info['professor_nome'] ?? '')) ?>
                    · <?= (int) ($fechamento['ano_letivo'] ?? 0) ?> · <?= (int) ($fechamento['bimestre'] ?? 0) ?>º bimestre
                    <?php if ($periodoInicio !== '' && $periodoFim !== ''): ?>
                        · <?= date('d/m/Y', strtotime($periodoInicio)) ?> a <?= date('d/m/Y', strtotime($periodoFim)) ?>
                    <?php endif; ?>
                    <?php if (!empty($fechamento['fechado_em'])): ?>
                        · fechado em <?= date('d/m/Y H:i', strtotime((string) $fechamento['fechado_em'])) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <form method="post" action="<?= URL ?>/admin/diario/reabrir" onsubmit="return confirm('Reabrir este diário? O professor poderá editar chamadas novamente.')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrf_token) ?>">
            <input type="hidden" name="fechamento_id" value="<?= $fechamentoId ?>">
            <button type="submit" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fa-solid fa-lock-open mr-2 text-amber-600"></i>
                Reabrir
            </button>
        </form>
    </div>
</div>

<?php include dirname(__DIR__, 4) . '/Views/admin/_partials/flash_message.php'; ?>

<p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-6">
    Visualização somente leitura. Para corrigir chamadas, reabra o diário.
</p>

<div class="border-b border-gray-200 mb-6">
    <nav class="flex flex-wrap gap-1 -mb-px">
        <?php foreach ($abas as $chave => $label): ?>
            <a href="<?= URL ?>/admin/diario/fechado?<?= $baseQuery ?>&aba=<?= $chave ?>"
               class="px-4 py-2.5 text-sm font-semibold border-b-2 <?= $abaAtual === $chave ? 'border-accent text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<?php
$partial = __DIR__ . '/_aba_' . $abaAtual . '.php';
if (is_file($partial)) {
    require $partial;
}
?>
