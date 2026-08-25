<?php
require_once __DIR__ . '/../../Services/ClassDiaryService.php';

use App\Modulos\Diario\Services\ClassDiaryService;
$situacaoClasses = [
    'em_dia' => 'bg-green-100 text-green-800',
    'atencao' => 'bg-amber-100 text-amber-800',
    'atraso' => 'bg-red-100 text-red-800',
];
$sit = (string) ($resumo['situacao'] ?? 'em_dia');
?>
<div class="space-y-6">
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase">Aulas previstas</p>
            <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int) ($resumo['aulas_previstas'] ?? 0) ?></p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase">Aulas realizadas</p>
            <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int) ($resumo['aulas_finalizadas'] ?? 0) ?></p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase">Chamadas realizadas</p>
            <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int) ($resumo['aulas_registradas'] ?? 0) ?>/<?= (int) ($resumo['aulas_previstas_ate_hoje'] ?? 0) ?></p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase">Planos relacionados</p>
            <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int) ($resumo['planos_relacionados'] ?? 0) ?>/<?= (int) ($resumo['planos_previstos'] ?? 0) ?></p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase">Avaliações no período</p>
            <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int) ($resumo['avaliacoes_encerradas'] ?? 0) ?>/<?= (int) ($resumo['avaliacoes'] ?? 0) ?></p>
            <p class="text-xs text-gray-400 mt-1">Encerradas / total (evento de prova)</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase">Situação</p>
            <span class="inline-flex mt-2 px-2.5 py-1 rounded-full text-xs font-semibold <?= $situacaoClasses[$sit] ?? 'bg-slate-100 text-slate-700' ?>">
                <?= htmlspecialchars(ClassDiaryService::situacaoLabel($sit)) ?>
            </span>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Pendências</h2>
            <p class="text-sm text-gray-500 mt-0.5">Chamadas e conteúdos ainda em aberto neste período.</p>
        </div>
        <?php if (empty($pendencias)): ?>
            <p class="p-6 text-sm text-gray-500">Nenhuma pendência — diário em dia. 🎉</p>
        <?php else: ?>
            <ul class="divide-y divide-gray-100">
                <?php foreach ($pendencias as $p):
                    $tipoPendencia = (string) ($p['tipo_pendencia'] ?? 'chamada');
                    $texto = $tipoPendencia === 'conteudo' ? 'conteúdo não preenchido' : 'chamada não realizada';
                ?>
                    <li class="px-5 py-3 flex items-center justify-between gap-4">
                        <span class="text-sm text-gray-700">
                            <?= date('d/m/Y', strtotime((string) $p['data_aula'])) ?> — <?= $texto ?>
                        </span>
                        <a href="<?= URL ?>/professor/diario/abrir?grade_id=<?= (int) $p['grade_horaria_id'] ?>&data=<?= urlencode((string) $p['data_aula']) ?>&origem=diario"
                           class="text-xs font-semibold text-purple-700 hover:underline">Lançar</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <?php if (!empty($eventos_proximos)): ?>
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Eventos avaliativos nos próximos 14 dias</h2>
            <p class="text-sm text-gray-500 mt-0.5">Fonte: Evento de Prova/Nota. O lançamento continua na tela do evento.</p>
        </div>
        <ul class="divide-y divide-gray-100">
            <?php foreach ($eventos_proximos as $ev): ?>
                <li class="px-5 py-3 flex items-center justify-between gap-4">
                    <span class="text-sm text-gray-700">
                        <?= date('d/m/Y', strtotime((string) $ev['data_prova'])) ?> — <?= htmlspecialchars((string) $ev['titulo']) ?>
                    </span>
                    <a href="<?= URL ?>/professor/provas/evento-lancar-notas/<?= (int) $ev['id'] ?>"
                       class="text-xs font-semibold text-purple-700 hover:underline">Abrir evento</a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
