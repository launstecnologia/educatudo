<?php
require_once __DIR__ . '/../../Services/ApoioDigitacaoFechamentoService.php';

$escola = is_array($escola ?? null) ? $escola : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$emissoes = is_array($emissoes ?? null) ? $emissoes : [];
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$periodoTipo = (string) ($periodo_tipo ?? 'ano');
$periodoNumero = (int) ($periodo_numero ?? 0);
$turmaId = (int) ($turma_id ?? 0);
$schemaPronto = !empty($schema_pronto);
$disponivel = !empty($escola['disponivel']);
$uf = (string) ($escola['uf'] ?? '');
$ehSp = $uf === 'SP';
$statusRotulos = is_array($status_rotulos ?? null) ? $status_rotulos : ApoioDigitacaoFechamentoService::STATUS;
$pagina = max(1, (int) ($pagina ?? 1));
$paginas = max(1, (int) ($paginas ?? 1));
$totalEmissoes = (int) ($emissoes_total ?? 0);
$qsBase = [
    'ano_letivo' => $anoLetivo,
    'periodo_tipo' => $periodoTipo,
    'periodo_numero' => $periodoNumero,
];
if ($turmaId > 0) {
    $qsBase['turma_id'] = $turmaId;
}
$qs = http_build_query($qsBase);

$page_header_title = 'Apoio à digitação';
$page_header_subtitle = $disponivel
    ? ('Planilha e TXT para lançar o fechamento no ' . (string) ($escola['canal'] ?? '') . '. O arquivo não é importado pelo sistema estadual.')
    : 'Planilha e TXT de apoio para a SED (São Paulo) e o SERE (Paraná).';
ob_start();
?>
<a href="<?= URL ?>/admin/fechamento?<?= htmlspecialchars($qs) ?>" class="text-gray-600 hover:text-gray-900 text-sm">← Fechamento</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';

$filtros_mostrar_turma = true;
$filtros_action = URL . '/admin/fechamento/apoio-digitacao';
include __DIR__ . '/../../../../Views/admin/resultados-finais/_filtros.php';

$inputCls = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent';
$statusBadge = static function (string $status): string {
    return match ($status) {
        'validado' => 'bg-green-100 text-green-700',
        'enviado' => 'bg-amber-100 text-amber-800',
        'digitado' => 'bg-sky-100 text-sky-800',
        default => 'bg-gray-100 text-gray-600',
    };
};
?>

<?php if (!$schemaPronto): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    Rode a migration <code>2026_10_06_fechamento_apoio_digitacao.sql</code> no painel Master para gravar as versões e os códigos oficiais.
</div>
<?php endif; ?>

<?php if (!$disponivel): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-2">Escola fora de São Paulo e do Paraná</h3>
    <p class="text-sm text-gray-600">
        A unidade matriz está com UF <strong><?= $uf !== '' ? htmlspecialchars($uf) : 'não informada' ?></strong>.
        O apoio sai só para SP (digitação na SED) e PR (conferência no SERE).
        Ajuste a UF em Unidades da escola se o cadastro estiver incompleto.
    </p>
</div>
<?php else: ?>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-1"><?= htmlspecialchars((string) ($escola['nome'] ?? 'Escola')) ?></h3>
    <p class="text-sm text-gray-500 mb-4">
        <?= $ehSp ? 'CIE da SED. O INEP não substitui o CIE.' : 'Código da escola no SERE. O INEP fica só como referência.' ?>
        <?php if (!empty($escola['inep'])): ?>
            INEP: <?= htmlspecialchars((string) $escola['inep']) ?>.
        <?php endif; ?>
    </p>
    <form method="POST" action="<?= URL ?>/admin/fechamento/apoio-digitacao/identificadores" class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
        <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
        <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo) ?>">
        <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
        <?php if ($turmaId > 0): ?>
        <input type="hidden" name="turma_id_filtro" value="<?= $turmaId ?>">
        <?php endif; ?>
        <div>
            <label for="codigo_estadual" class="block text-sm font-medium text-gray-700 mb-1.5"><?= $ehSp ? 'CIE' : 'Código SERE' ?></label>
            <input type="text" id="codigo_estadual" name="codigo_estadual" maxlength="30" inputmode="text"
                   value="<?= htmlspecialchars((string) ($escola['codigo_estadual'] ?? '')) ?>"
                   class="<?= $inputCls ?>" autocomplete="off">
        </div>
        <div>
            <?php if ($schemaPronto): ?>
            <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium bg-primary text-white hover:opacity-90">Salvar código</button>
            <?php else: ?>
            <button type="button" disabled class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium bg-gray-200 text-gray-500 cursor-not-allowed">Salvar código</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-lg font-semibold text-gray-900">Turmas</h3>
        <p class="text-sm text-gray-500">
            <?= $ehSp
                ? 'Informe o número da classe na SED, separado do nome da turma interna. Concluinte sai em branco de propósito.'
                : 'Informe o código oficial do curso. Uma linha da planilha é um aluno em um componente.' ?>
            Gerar de novo cria outra versão e mantém a anterior.
        </p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= $ehSp ? 'Nº da classe na SED' : 'Nº da classe (se houver)' ?></th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código do curso</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gerar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php
                $linhasTurma = $turmas;
                if ($turmaId > 0) {
                    $linhasTurma = array_values(array_filter($turmas, static fn ($t) => (int) ($t['id'] ?? 0) === $turmaId));
                }
                ?>
                <?php if ($linhasTurma === []): ?>
                <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">Nenhuma turma neste filtro.</td></tr>
                <?php else: foreach ($linhasTurma as $turma):
                    $tid = (int) ($turma['id'] ?? 0);
                ?>
                <tr>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <div class="font-medium"><?= htmlspecialchars((string) ($turma['nome'] ?? '')) ?></div>
                        <div class="text-gray-500"><?= htmlspecialchars(trim((string) (($turma['serie'] ?? '') . ' ' . ($turma['turno'] ?? '')))) ?></div>
                    </td>
                    <td class="px-6 py-4">
                        <form id="apoio-turma-<?= $tid ?>" method="POST" action="<?= URL ?>/admin/fechamento/apoio-digitacao/emitir">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                            <input type="hidden" name="turma_id" value="<?= $tid ?>">
                            <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
                            <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo) ?>">
                            <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
                            <?php if ($turmaId > 0): ?>
                            <input type="hidden" name="turma_id_filtro" value="<?= $turmaId ?>">
                            <?php endif; ?>
                            <input type="text" name="numero_classe_oficial" maxlength="40" form="apoio-turma-<?= $tid ?>"
                                   value="<?= htmlspecialchars((string) ($turma['numero_classe_oficial'] ?? '')) ?>"
                                   class="<?= $inputCls ?>" placeholder="<?= $ehSp ? 'Classe na SED' : 'Opcional' ?>" autocomplete="off">
                        </form>
                    </td>
                    <td class="px-6 py-4">
                        <input type="text" name="codigo_curso_oficial" maxlength="40" form="apoio-turma-<?= $tid ?>"
                               value="<?= htmlspecialchars((string) ($turma['codigo_curso_oficial'] ?? '')) ?>"
                               class="<?= $inputCls ?>" placeholder="Código oficial" autocomplete="off">
                    </td>
                    <td class="px-6 py-4 text-right">
                        <?php if ($schemaPronto): ?>
                        <button type="submit" form="apoio-turma-<?= $tid ?>" class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium bg-primary text-white hover:opacity-90">
                            Planilha e TXT
                        </button>
                        <?php else: ?>
                        <button type="button" disabled class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium bg-gray-200 text-gray-500 cursor-not-allowed">
                            Planilha e TXT
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Versões geradas</h3>
            <p class="text-sm text-gray-500">Exportado, digitado, enviado e validado são momentos diferentes. Validar exige turma homologada e sem pendência crítica.</p>
        </div>
        <span class="text-sm text-gray-500"><?= $totalEmissoes ?> no ano</span>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quando</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Versão</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pendências</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($emissoes === []): ?>
                <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">Nenhuma versão neste filtro.</td></tr>
                <?php else: foreach ($emissoes as $em):
                    $eid = (int) ($em['id'] ?? 0);
                    $status = (string) ($em['status'] ?? 'exportado');
                    $pendencias = ApoioDigitacaoFechamentoService::lerPendencias($em['pendencias_json'] ?? '');
                    $criticas = 0;
                    foreach ($pendencias as $pend) {
                        if (!empty($pend['critica'])) {
                            $criticas++;
                        }
                    }
                    $planilhaNome = (string) ($em['arquivo_planilha'] ?? '');
                    $rotuloPlanilha = str_ends_with(strtolower($planilhaNome), '.csv') ? 'Baixar CSV' : 'Baixar planilha';
                ?>
                <tr class="hover:bg-gray-50 align-top">
                    <td class="px-6 py-4 text-sm text-gray-700">
                        <?= htmlspecialchars((string) ($em['created_at'] ?? '')) ?>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars((string) ($em['usuario_nome'] ?? '')) ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <?= htmlspecialchars((string) ($em['turma_nome'] ?? 'Turma')) ?>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars((string) ($em['uf_destino'] ?? '')) ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm"><?= (int) ($em['versao'] ?? 0) ?></td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusBadge($status) ?>">
                            <?= htmlspecialchars((string) ($statusRotulos[$status] ?? $status)) ?>
                        </span>
                        <?php if (!empty($em['protocolo'])): ?>
                        <div class="text-xs text-gray-500 mt-1">Protocolo <?= htmlspecialchars((string) $em['protocolo']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($em['homologada'])): ?>
                        <div class="text-xs text-amber-700 mt-1">Prévia</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?php if ($pendencias === []): ?>
                        Nenhuma
                        <?php else: ?>
                        <span class="<?= $criticas > 0 ? 'text-amber-800' : '' ?>"><?= count($pendencias) ?> · <?= $criticas ?> crítica(s)</span>
                        <ul class="mt-1 text-xs text-gray-500 list-disc pl-4">
                            <?php foreach (array_slice($pendencias, 0, 3) as $pend): ?>
                            <li><?= htmlspecialchars((string) $pend['texto']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex flex-col items-end gap-2">
                            <?php ob_start(); ?>
                            <a href="<?= URL ?>/admin/fechamento/apoio-digitacao/arquivo/<?= $eid ?>/planilha" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-file-excel text-gray-400 w-4 text-center"></i> <?= htmlspecialchars($rotuloPlanilha) ?>
                            </a>
                            <a href="<?= URL ?>/admin/fechamento/apoio-digitacao/arquivo/<?= $eid ?>/txt" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-file-lines text-gray-400 w-4 text-center"></i> Baixar TXT
                            </a>
                            <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                            <?php $row_actions_dropdown_id = 'apoio-acoes-' . $eid; ?>
                            <?php include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php'; ?>
                            <form method="POST" action="<?= URL ?>/admin/fechamento/apoio-digitacao/status" class="flex flex-col items-stretch gap-2 w-56">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                                <input type="hidden" name="id" value="<?= $eid ?>">
                                <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
                                <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo) ?>">
                                <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
                                <?php if ($turmaId > 0): ?>
                                <input type="hidden" name="turma_id_filtro" value="<?= $turmaId ?>">
                                <?php endif; ?>
                                <select name="status" class="<?= $inputCls ?>">
                                    <?php foreach ($statusRotulos as $cod => $rotulo): ?>
                                    <option value="<?= htmlspecialchars((string) $cod) ?>" <?= $status === $cod ? 'selected' : '' ?>><?= htmlspecialchars((string) $rotulo) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="protocolo" maxlength="80" value="<?= htmlspecialchars((string) ($em['protocolo'] ?? '')) ?>" class="<?= $inputCls ?>" placeholder="Protocolo">
                                <button type="submit" class="inline-flex items-center justify-center px-3 py-2 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Atualizar</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginas > 1): ?>
    <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between text-sm">
        <?php if ($pagina > 1): ?>
        <a class="text-accent underline" href="<?= URL ?>/admin/fechamento/apoio-digitacao?<?= htmlspecialchars(http_build_query($qsBase + ['pagina' => $pagina - 1])) ?>">Anterior</a>
        <?php else: ?><span></span><?php endif; ?>
        <span class="text-gray-500">Página <?= $pagina ?> de <?= $paginas ?></span>
        <?php if ($pagina < $paginas): ?>
        <a class="text-accent underline" href="<?= URL ?>/admin/fechamento/apoio-digitacao?<?= htmlspecialchars(http_build_query($qsBase + ['pagina' => $pagina + 1])) ?>">Próxima</a>
        <?php else: ?><span></span><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
