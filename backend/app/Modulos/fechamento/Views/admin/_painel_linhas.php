<?php

$paineis = is_array($paineis ?? null) ? $paineis : [];
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$periodoTipo = (string) ($periodo_tipo ?? 'ano');
$periodoNumero = (int) ($periodo_numero ?? 0);

$statusBadge = static function (string $status): string {
    return match (\App\Modulos\Fechamento\Services\FechamentoMaquinaEstados::normalizar($status)) {
        \App\Modulos\Fechamento\Services\FechamentoMaquinaEstados::HOMOLOGADO => 'bg-green-100 text-green-700',
        \App\Modulos\Fechamento\Services\FechamentoMaquinaEstados::RETIFICADO => 'bg-purple-100 text-purple-700',
        \App\Modulos\Fechamento\Services\FechamentoMaquinaEstados::EM_RECUPERACAO => 'bg-amber-100 text-amber-800',
        \App\Modulos\Fechamento\Services\FechamentoMaquinaEstados::EM_FECHAMENTO => 'bg-sky-100 text-sky-800',
        default => 'bg-gray-100 text-gray-600',
    };
};

if ($paineis === []):
?>
<tr>
    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
        <i class="fa-solid fa-clipboard-check text-4xl text-gray-300 mb-4"></i>
        <p>Nenhuma turma encontrada para este filtro.</p>
    </td>
</tr>
<?php
else:
    foreach ($paineis as $p):
        $turma = $p['turma'] ?? [];
        $resumo = $p['resumo'] ?? [];
        $tid = (int) ($turma['id'] ?? 0);
        $qs = http_build_query([
            'ano_letivo' => $anoLetivo,
            'periodo_tipo' => $periodoTipo,
            'periodo_numero' => $periodoNumero,
        ]);
        $status = (string) ($p['status'] ?? 'ABERTO');
        $pend = (int) ($p['pendencias'] ?? 0);
        $totalAlunos = (int) ($resumo['total'] ?? 0);
        $chamadasPendentes = (int) ($resumo['chamadas_pendentes'] ?? 0);
        $urlTurma = URL . '/admin/fechamento/turma/' . $tid . '?' . htmlspecialchars($qs);
        $periodoEncerrado = $status === \App\Modulos\Fechamento\Services\FechamentoMaquinaEstados::HOMOLOGADO;
?>
<tr class="hover:bg-gray-50">
    <td class="px-6 py-4">
        <div class="font-medium text-gray-900"><?= htmlspecialchars((string) ($turma['nome'] ?? '')) ?></div>
        <div class="text-xs text-gray-500"><?= htmlspecialchars((string) ($p['periodo']['label'] ?? '')) ?> / <?= $anoLetivo ?></div>
    </td>
    <td class="px-6 py-4">
        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $statusBadge($status) ?>">
            <?= htmlspecialchars((string) ($p['status_rotulo'] ?? $status)) ?>
        </span>
    </td>
    <td class="px-6 py-4 text-sm">
        <a href="<?= $urlTurma ?>#alunos" class="text-indigo-700 hover:underline font-medium">
            <?= $totalAlunos ?>
        </a>
    </td>
    <td class="px-6 py-4 text-sm">
        <a href="<?= $urlTurma ?>#frequencia" class="text-indigo-700 hover:underline font-medium">
            <?php if ($periodoEncerrado): ?>
                Encerrado
            <?php elseif ($chamadasPendentes > 0): ?>
                <?= $chamadasPendentes ?> chamada<?= $chamadasPendentes === 1 ? '' : 's' ?> pendente<?= $chamadasPendentes === 1 ? '' : 's' ?>
            <?php else: ?>
                Em dia
            <?php endif; ?>
        </a>
    </td>
    <td class="px-6 py-4 text-sm">
        <a href="<?= $urlTurma ?>#recuperacao" class="text-indigo-700 hover:underline font-medium">
            <?= (int) ($resumo['recuperacao'] ?? 0) ?>
        </a>
    </td>
    <td class="px-6 py-4">
        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $pend > 0 ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-700' ?>">
            <?= $pend > 0 ? $pend . ' crítica(s)' : 'OK' ?>
        </span>
    </td>
    <td class="px-6 py-4 text-right">
        <?php ob_start(); ?>
        <a href="<?= $urlTurma ?>"
           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <i class="fa-solid fa-user-group text-gray-400 w-4 text-center"></i> Alunos
        </a>
        <?php if ($periodoEncerrado): ?>
        <a href="<?= URL ?>/admin/fechamento/impressao-lote?<?= htmlspecialchars($qs) ?>&amp;turma_id=<?= $tid ?>"
           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <i class="fa-solid fa-folder-open text-gray-400 w-4 text-center"></i> Documentação
        </a>
        <?php endif; ?>
        <button type="button"
                data-fechamento-consulta="homologacoes"
                data-turma-id="<?= $tid ?>"
                data-turma-nome="<?= htmlspecialchars((string) ($turma['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <i class="fa-solid fa-book text-gray-400 w-4 text-center"></i> Registro de homologações
        </button>
        <?php
        $row_actions_dropdown_items = ob_get_clean();
        $row_actions_dropdown_id = 'row-fech-' . $tid;
        $row_actions_dropdown_menu_class = 'w-80';
        include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php';
        ?>
    </td>
</tr>
<?php
    endforeach;
endif;
