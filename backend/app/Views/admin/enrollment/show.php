<?php
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$fmtDt = fn($d) => $d ? date('d/m/Y H:i', strtotime($d)) : '—';
$statusColors = [
    'rascunho'              => 'bg-gray-100 text-gray-700',
    'aguardando_contrato'   => 'bg-yellow-100 text-yellow-700',
    'aguardando_assinatura' => 'bg-blue-100 text-blue-700',
    'confirmada'            => 'bg-green-100 text-green-700',
    'enturmada'             => 'bg-emerald-100 text-emerald-700',
    'abandonada'            => 'bg-orange-100 text-orange-700',
    'cancelada'             => 'bg-red-100 text-red-700',
    'lista_espera'          => 'bg-purple-100 text-purple-700',
];
$statusLabels = [
    'rascunho'=>'Rascunho','aguardando_contrato'=>'Aguard. Contrato',
    'aguardando_assinatura'=>'Aguard. Assinatura','confirmada'=>'Confirmada',
    'enturmada'=>'Enturmada','abandonada'=>'Abandonada',
    'cancelada'=>'Cancelada','lista_espera'=>'Lista de Espera',
];
$tipoLabel = ['nova'=>'Matrícula Nova','rematricula'=>'Rematrícula','transferencia'=>'Transferência'];

$page_header_title    = 'Matrícula #' . (int)$enrollment['id'];
$page_header_subtitle = ($tipoLabel[$enrollment['tipo']] ?? '') . ' — ' . ($enrollment['aluno_nome'] ?? '');
ob_start(); ?>
<a href="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/edit" class="btn-secondary text-sm">
    <i class="fa-solid fa-pen mr-1.5"></i> Editar
</a>
<a href="<?= URL ?>/admin/enrollment" class="btn-secondary text-sm">← Voltar</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php'; ?>

<?php if (!empty($status_message)): ?>
<div class="mb-4 p-3 rounded-xl text-sm <?= ($status_type ?? '') === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
    <?= $esc($status_message) ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Coluna principal -->
    <div class="lg:col-span-2 space-y-5">

        <!-- Status atual -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="px-3 py-1 rounded-full text-sm font-medium <?= $statusColors[$enrollment['status']] ?? 'bg-gray-100 text-gray-700' ?>">
                    <?= $esc($statusLabels[$enrollment['status']] ?? $enrollment['status']) ?>
                </span>
                <span class="text-gray-500 text-sm">Criado em <?= date('d/m/Y', strtotime($enrollment['created_at'])) ?></span>
            </div>
            <div class="flex gap-2">
                <?php if ($enrollment['status'] !== 'cancelada'): ?>
                <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/cancelar" class="inline"
                      onsubmit="return confirm('Cancelar esta matrícula?')">
                    <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                    <button type="submit" class="text-xs text-red-600 hover:text-red-800 border border-red-200 rounded-lg px-3 py-1.5 hover:bg-red-50 transition">
                        Cancelar
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dados do aluno -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Dados do Aluno</h3>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div class="col-span-2"><dt class="text-gray-400 text-xs">Nome</dt><dd class="font-medium text-gray-800"><?= $esc($enrollment['aluno_nome']) ?></dd></div>
                <div><dt class="text-gray-400 text-xs">CPF</dt><dd class="text-gray-700"><?= $esc($enrollment['aluno_cpf'] ?? '—') ?></dd></div>
                <div><dt class="text-gray-400 text-xs">Nascimento</dt><dd class="text-gray-700"><?= $enrollment['aluno_data_nasc'] ? date('d/m/Y', strtotime($enrollment['aluno_data_nasc'])) : '—' ?></dd></div>
                <div><dt class="text-gray-400 text-xs">E-mail</dt><dd class="text-gray-700"><?= $esc($enrollment['aluno_email'] ?? '—') ?></dd></div>
                <div><dt class="text-gray-400 text-xs">Telefone</dt><dd class="text-gray-700"><?= $esc($enrollment['aluno_telefone'] ?? '—') ?></dd></div>
                <div><dt class="text-gray-400 text-xs">Turma</dt><dd class="text-gray-700"><?= $esc($enrollment['turma_nome'] ?? '—') ?> <?= $enrollment['turma_serie'] ? '— ' . $esc($enrollment['turma_serie']) : '' ?></dd></div>
                <div><dt class="text-gray-400 text-xs">Ano Letivo</dt><dd class="text-gray-700"><?= $esc($enrollment['ano_letivo_nome'] ?? '—') ?></dd></div>
            </dl>
        </div>

        <!-- Dados do responsável -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Responsável Legal</h3>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div class="col-span-2"><dt class="text-gray-400 text-xs">Nome</dt><dd class="font-medium text-gray-800"><?= $esc($enrollment['resp_nome']) ?></dd></div>
                <div><dt class="text-gray-400 text-xs">CPF</dt><dd class="text-gray-700"><?= $esc($enrollment['resp_cpf'] ?? '—') ?></dd></div>
                <div><dt class="text-gray-400 text-xs">Parentesco</dt><dd class="text-gray-700"><?= $esc($enrollment['resp_parentesco'] ?? '—') ?></dd></div>
                <div><dt class="text-gray-400 text-xs">E-mail</dt><dd class="text-gray-700"><?= $esc($enrollment['resp_email'] ?? '—') ?></dd></div>
                <div><dt class="text-gray-400 text-xs">WhatsApp</dt><dd class="text-gray-700"><?= $esc($enrollment['resp_telefone'] ?? '—') ?></dd></div>
                <?php if ($enrollment['resp_endereco']): ?>
                <div class="col-span-2"><dt class="text-gray-400 text-xs">Endereço</dt><dd class="text-gray-700"><?= $esc($enrollment['resp_endereco']) ?></dd></div>
                <?php endif; ?>
            </dl>
        </div>

        <?php if ($enrollment['observacoes']): ?>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-2">Observações</h3>
            <p class="text-sm text-gray-600"><?= nl2br($esc($enrollment['observacoes'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- Trilha de auditoria -->
        <?php if (!empty($audit)): ?>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Histórico</h3>
            <ol class="space-y-2">
                <?php foreach ($audit as $log): ?>
                <li class="flex items-start gap-3 text-sm">
                    <span class="mt-0.5 w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>
                    <div>
                        <span class="text-gray-700"><?= $esc($log['acao'] ?? 'transição') ?></span>
                        <?php if ($log['status_de']): ?>
                        <span class="text-gray-400 mx-1">·</span>
                        <span class="text-gray-400 text-xs"><?= $esc($log['status_de']) ?> → <?= $esc($log['status_para']) ?></span>
                        <?php endif; ?>
                        <div class="text-gray-400 text-xs"><?= $fmtDt($log['created_at']) ?><?= $log['usuario_nome'] ? ' — ' . $esc($log['usuario_nome']) : '' ?></div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar de ações -->
    <div class="space-y-5">

        <!-- Contrato -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Contrato</h3>

            <?php if ($enrollment['assinado_em']): ?>
            <div class="bg-green-50 rounded-xl p-3 text-sm text-green-700 mb-4">
                <i class="fa-solid fa-check-circle mr-1"></i>
                Assinado em <?= $fmtDt($enrollment['assinado_em']) ?><br>
                <span class="text-xs text-green-600">por <?= $esc($enrollment['assinante_nome'] ?? '') ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($enrollment['contrato_pdf_path'])): ?>
            <a href="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/contrato/download"
               class="w-full btn-secondary text-sm flex items-center justify-center gap-2 mb-3">
                <i class="fa-solid fa-file-pdf text-red-500"></i> Baixar PDF
            </a>
            <div class="text-xs text-gray-400 mb-3 break-all">
                Link público:<br>
                <a href="<?= URL ?>/matricula/contrato/<?= $esc($enrollment['contrato_token']) ?>"
                   target="_blank" class="text-blue-600 hover:underline">
                    <?= URL ?>/matricula/contrato/<?= substr($esc($enrollment['contrato_token']), 0, 16) ?>...
                </a>
            </div>
            <?php if (!empty($whatsapp_url)): ?>
            <a href="<?= $esc($whatsapp_url) ?>" target="_blank"
               class="w-full flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium py-2.5 rounded-xl transition">
                <i class="fa-brands fa-whatsapp text-base"></i> Enviar por WhatsApp
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($enrollment['status'] !== 'cancelada' && $enrollment['status'] !== 'enturmada'): ?>
            <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/contrato" class="mt-3">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <button type="submit" class="w-full btn-primary text-sm">
                    <i class="fa-solid fa-file-contract mr-1.5"></i>
                    <?= $enrollment['contrato_pdf_path'] ? 'Regerar Contrato' : 'Gerar Contrato PDF' ?>
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Contrato Financeiro -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-3">
                <i class="fa-solid fa-dollar-sign text-green-600 mr-1"></i> Contrato Financeiro
            </h3>

            <?php if (!empty($finance_contract)): ?>
            <div class="bg-green-50 border border-green-200 rounded-xl p-3 mb-3 text-sm text-green-700">
                <i class="fa-solid fa-check-circle mr-1"></i>
                Contrato #<?= $finance_contract['id'] ?> —
                <span class="font-medium"><?= ucfirst($finance_contract['status']) ?></span><br>
                <span class="text-xs text-green-600">
                    <?= count($finance_contract_installments ?? []) ?> parcelas ·
                    R$ <?= number_format($finance_contract['valor_liquido'] ?? 0, 2, ',', '.') ?> líquido
                </span>
            </div>
            <a href="<?= URL ?>/admin/finance/contracts/<?= $finance_contract['id'] ?>"
               class="w-full flex items-center justify-center gap-2 bg-blue-50 text-blue-700 hover:bg-blue-100 text-sm font-medium py-2 rounded-xl transition mb-2">
                <i class="fa-solid fa-eye"></i> Ver Contrato Financeiro
            </a>
            <?php else: ?>
            <p class="text-xs text-gray-400 mb-3">Nenhum contrato financeiro vinculado.</p>
            <?php endif; ?>

            <?php if (in_array($enrollment['status'], ['confirmada','enturmada','aguardando_assinatura','aguardando_contrato'])): ?>
            <?php
            $financeUrl = URL . '/admin/finance/contracts/create?enrollment_id=' . (int)$enrollment['id']
                . '&aluno_id=' . (int)($enrollment['aluno_id'] ?? 0)
                . '&ano_letivo_id=' . (int)($enrollment['ano_letivo_id'] ?? 0)
                . '&resp_nome=' . urlencode($enrollment['resp_nome'] ?? '')
                . '&resp_email=' . urlencode($enrollment['resp_email'] ?? '')
                . '&resp_telefone=' . urlencode($enrollment['resp_telefone'] ?? '');
            ?>
            <a href="<?= $financeUrl ?>"
               class="w-full flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium py-2.5 rounded-xl transition">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <?= !empty($finance_contract) ? 'Novo Contrato Financeiro' : 'Gerar Contrato Financeiro' ?>
            </a>
            <?php endif; ?>
        </div>

        <!-- Mudar status manualmente -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Alterar Status</h3>
            <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/status">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <select name="novo_status" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm mb-3">
                    <?php foreach ($statusLabels as $v => $l): ?>
                    <option value="<?= $esc($v) ?>" <?= $enrollment['status'] === $v ? 'selected' : '' ?>><?= $esc($l) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="w-full btn-secondary text-sm">Atualizar Status</button>
            </form>
        </div>

        <?php if ($enrollment['expira_em']): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-700">
            <i class="fa-solid fa-clock mr-1"></i>
            Expira em <?= date('d/m/Y', strtotime($enrollment['expira_em'])) ?>
        </div>
        <?php endif; ?>
    </div>

</div>
