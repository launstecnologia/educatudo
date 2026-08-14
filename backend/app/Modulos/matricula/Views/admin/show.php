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
$produtos = $produtos ?? [];
$documentos = $documentos ?? [];
$faltando_enturmar = $faltando_enturmar ?? [];
$zapsign_ativo = !empty($zapsign_ativo);
$podeEnturmar = !in_array($enrollment['status'] ?? '', ['enturmada', 'cancelada'], true);
$email_url = $email_url ?? '';
$foco_contrato = !empty($foco_contrato) || (($_GET['foco'] ?? '') === 'contrato');
$mostrarContratoCard = $foco_contrato || !in_array($enrollment['status'] ?? '', ['enturmada', 'cancelada'], true);

$tiposCobrancaLabel = [
    'mensalidade'       => 'Mensalidade',
    'matricula'         => 'Matrícula',
    'material_didatico' => 'Material didático',
    'uniforme'          => 'Uniforme',
    'taxa'              => 'Taxa',
    'outros'            => 'Outros',
];
$cobrancasShow = $enrollment['finance_cobrancas'] ?? null;
if (is_string($cobrancasShow)) {
    $cobrancasShow = json_decode($cobrancasShow, true);
}
if (!is_array($cobrancasShow)) {
    $cobrancasShow = [];
}
$planosById = [];
foreach (($planos_financeiros ?? []) as $p) {
    $planosById[(int)$p['id']] = $p['nome'] ?? ('Plano #' . $p['id']);
}
// Fallback legado: só finance_plan_id
if ($cobrancasShow === [] && !empty($enrollment['finance_plan_id'])) {
    $cobrancasShow[] = [
        'tipo' => 'mensalidade',
        'plan_id' => (int)$enrollment['finance_plan_id'],
    ];
}

$page_header_title    = 'Matrícula #' . (int)$enrollment['id'];
$page_header_subtitle = ($tipoLabel[$enrollment['tipo']] ?? '') . ' — ' . ($enrollment['aluno_nome'] ?? '');
ob_start(); ?>
<a href="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/edit" class="btn-secondary text-sm">
    <i class="fa-solid fa-pen mr-1.5"></i> Editar
</a>
<a href="<?= URL ?>/admin/enrollment" class="btn-secondary text-sm">← Voltar</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php'; ?>

<?php if (!empty($status_message)): ?>
<div class="mb-4 p-3 rounded-xl text-sm <?= ($status_type ?? '') === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
    <?= $esc($status_message) ?>
</div>
<?php endif; ?>

<?php if (!empty($faltando_enturmar)): ?>
<div class="mb-4 p-4 rounded-xl text-sm bg-amber-50 text-amber-800 border border-amber-200">
    <p class="font-medium mb-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Campos faltando para enturmar</p>
    <ul class="list-disc ml-5 space-y-0.5">
        <?php foreach ($faltando_enturmar as $campo): ?>
        <li><?= $esc($campo) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($mostrarContratoCard): ?>
<?php
    $contratosProcesso = $contratos_processo ?? [];
    $tiposContratoLabels = [
        'matricula' => 'Matrícula',
        'mensalidade' => 'Mensalidade',
        'material_didatico' => 'Material didático',
        'uniforme' => 'Uniforme',
        'taxa' => 'Taxa',
        'outros' => 'Outros',
    ];
    $destaqueContrato = (($_GET['foco'] ?? '') === 'contrato') || empty($enrollment['contrato_pdf_path']);
?>
<div id="contrato-assinatura" class="mb-4 p-5 rounded-2xl border-2 <?= $destaqueContrato ? 'border-primary/30 bg-primary/5' : 'border-gray-200 bg-white' ?> shadow-sm space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <i class="fa-solid fa-file-signature text-primary"></i>
                Contratos e assinatura
            </h3>
            <p class="mt-1 text-sm text-gray-600">
                Gere o PDF de cada contrato configurado e, se a ZapSign estiver ativa, envie para assinatura.
            </p>
            <?php if (!empty($enrollment['assinado_em'])): ?>
            <p class="mt-2 text-sm text-green-700">
                <i class="fa-solid fa-check-circle mr-1"></i>
                Contrato principal assinado em <?= $fmtDt($enrollment['assinado_em']) ?>
                <?= !empty($enrollment['assinante_nome']) ? ' — ' . $esc($enrollment['assinante_nome']) : '' ?>
            </p>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if (!empty($whatsapp_url)): ?>
            <a href="<?= $esc($whatsapp_url) ?>" target="_blank"
               class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium bg-green-500 hover:bg-green-600 text-white">
                <i class="fa-brands fa-whatsapp mr-1.5"></i> WhatsApp
            </a>
            <?php endif; ?>
            <?php if (!empty($email_url)): ?>
            <a href="<?= $esc($email_url) ?>"
               class="btn-secondary inline-flex items-center px-3 py-2 rounded-lg text-sm">
                <i class="fa-solid fa-envelope mr-1.5"></i> E-mail
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($contratosProcesso === []): ?>
    <div class="rounded-xl border border-dashed border-gray-300 bg-white/70 p-4 text-sm text-gray-600">
        Nenhuma regra de contrato ativa.
        <a href="<?= URL ?>/admin/enrollment/config" class="text-primary underline">Configurar em Z-Configuração → Matrícula</a>.
        Tokens ZapSign em <a href="<?= URL ?>/admin/configuracao/assinatura-digital" class="text-primary underline">Assinatura Digital</a>.
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <?php foreach ($contratosProcesso as $c):
            $regraId = (int) ($c['id'] ?? 0);
            $instId = (int) (($c['instancia']['id'] ?? 0));
            $temPdf = !empty($c['pdf_path']);
            $zsStatus = (string) ($c['zapsign_status'] ?? '');
            $tipoLabel = $tiposContratoLabels[$c['tipo'] ?? ''] ?? ($c['tipo'] ?? '');
            $podeGerar = $enrollment['status'] !== 'cancelada' && $enrollment['status'] !== 'enturmada' && $regraId > 0;
        ?>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm flex flex-col gap-3">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900 leading-snug"><?= $esc($c['nome'] ?? 'Contrato') ?></p>
                    <p class="mt-1 text-xs text-gray-500">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold"
                              style="background-color: color-mix(in srgb, var(--primary-color) 12%, white); color: var(--primary-color);">
                            <?= $esc($tipoLabel) ?>
                        </span>
                        <span class="ml-1"><?= $esc($c['modelo_documento_codigo'] ?? '') ?></span>
                    </p>
                </div>
                <div class="text-right shrink-0">
                    <?php if (!empty($c['assinado_em'])): ?>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium bg-green-100 text-green-700">Assinado</span>
                    <?php elseif ($zsStatus !== ''): ?>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium bg-indigo-100 text-indigo-700">ZapSign: <?= $esc($zsStatus) ?></span>
                    <?php elseif ($temPdf): ?>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-100 text-amber-700">PDF gerado</span>
                    <?php else: ?>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600">Pendente</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mt-auto">
                <?php if ($podeGerar): ?>
                <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/contratos/<?= $regraId ?>/gerar">
                    <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                    <button type="submit" class="btn-primary-custom inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold">
                        <i class="fa-solid fa-file-pdf mr-1"></i>
                        <?= $temPdf ? 'Regerar PDF' : 'Gerar PDF' ?>
                    </button>
                </form>
                <?php if (!empty($zapsign_ativo) && !empty($c['enviar_zapsign'])): ?>
                <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/contratos/<?= $regraId ?>/gerar">
                    <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                    <input type="hidden" name="enviar_zapsign" value="1">
                    <button type="submit" class="btn-secondary inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium">
                        <i class="fa-solid fa-pen-nib mr-1"></i>
                        <?= !empty($c['zapsign_doc_token']) ? 'Reenviar ZapSign' : 'Enviar ZapSign' ?>
                    </button>
                </form>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($temPdf && $instId > 0): ?>
                <a href="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/contratos/<?= $instId ?>/download"
                   class="btn-secondary inline-flex items-center px-3 py-1.5 rounded-lg text-xs">
                    <i class="fa-solid fa-download mr-1"></i> Download
                </a>
                <?php elseif ($temPdf && ($c['tipo'] ?? '') === 'matricula'): ?>
                <a href="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/contrato/download"
                   class="btn-secondary inline-flex items-center px-3 py-1.5 rounded-lg text-xs">
                    <i class="fa-solid fa-download mr-1"></i> Download
                </a>
                <?php endif; ?>

                <?php if (!empty($c['zapsign_sign_url'])): ?>
                <a href="<?= $esc($c['zapsign_sign_url']) ?>" target="_blank" rel="noopener"
                   class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100">
                    <i class="fa-solid fa-external-link mr-1"></i> Link assinatura
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($enrollment['assinado_em']) && !in_array($enrollment['status'] ?? '', ['enturmada', 'cancelada', 'abandonada'], true)): ?>
    <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/contrato-assinado"
          enctype="multipart/form-data"
          class="pt-4 border-t border-primary/20 grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
        <input type="hidden" name="tipo_documento" value="contrato_assinado">
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">Contrato assinado manualmente (PDF/JPG)</label>
            <input type="file" name="documento" required accept=".pdf,.jpg,.jpeg,.png,.webp"
                   class="w-full text-sm text-gray-600">
        </div>
        <button type="submit" class="btn-secondary inline-flex items-center justify-center px-3 py-2 rounded-lg text-sm font-medium">
            <i class="fa-solid fa-upload mr-1.5"></i> Registrar assinatura manual
        </button>
    </form>
    <?php elseif (!empty($enrollment['contrato_assinado_path'])): ?>
    <p class="text-xs text-gray-500">
        <i class="fa-solid fa-paperclip mr-1"></i>
        Arquivo de assinatura manual anexado.
    </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Coluna principal -->
    <div class="lg:col-span-2 space-y-5">

        <!-- Status atual -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <span class="px-3 py-1 rounded-full text-sm font-medium <?= $statusColors[$enrollment['status']] ?? 'bg-gray-100 text-gray-700' ?>">
                    <?= $esc($statusLabels[$enrollment['status']] ?? $enrollment['status']) ?>
                </span>
                <span class="text-gray-500 text-sm">Criado em <?= date('d/m/Y', strtotime($enrollment['created_at'])) ?></span>
            </div>
            <div class="flex gap-2 flex-wrap">
                <?php if ($enrollment['status'] !== 'cancelada' && $enrollment['status'] !== 'enturmada'): ?>
                <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/contrato" class="inline">
                    <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                    <button type="submit" class="text-xs text-blue-700 border border-blue-200 rounded-lg px-3 py-1.5 hover:bg-blue-50 transition">
                        <i class="fa-solid fa-file-contract mr-1"></i>
                        <?= !empty($enrollment['contrato_pdf_path']) ? 'Regerar contrato' : 'Gerar contrato' ?>
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($podeEnturmar): ?>
                <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/status" class="inline"
                      onsubmit="return confirm('Enturmar este aluno?')">
                    <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                    <input type="hidden" name="novo_status" value="enturmada">
                    <button type="submit" class="text-xs text-emerald-700 border border-emerald-200 rounded-lg px-3 py-1.5 hover:bg-emerald-50 transition">
                        <i class="fa-solid fa-user-plus mr-1"></i> Enturmar
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($zapsign_ativo && !empty($enrollment['zapsign_doc_token'])): ?>
                <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/zapsign/sincronizar" class="inline">
                    <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                    <button type="submit" class="text-xs text-indigo-700 border border-indigo-200 rounded-lg px-3 py-1.5 hover:bg-indigo-50 transition">
                        <i class="fa-solid fa-rotate mr-1"></i> Sync ZapSign
                    </button>
                </form>
                <?php endif; ?>

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

        <!-- Cobranças configuradas (finance_cobrancas) -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Cobranças / Planos</h3>
            <?php if (empty($cobrancasShow)): ?>
            <p class="text-sm text-gray-400">Nenhuma cobrança configurada neste processo.</p>
            <?php else: ?>
            <ul class="space-y-2">
                <?php foreach ($cobrancasShow as $cob):
                    $tipoCob = (string)($cob['tipo'] ?? '');
                    $planIdCob = (int)($cob['plan_id'] ?? 0);
                    $labelTipo = $tiposCobrancaLabel[$tipoCob] ?? ucfirst(str_replace('_', ' ', $tipoCob));
                    $nomePlano = $planosById[$planIdCob] ?? ($planIdCob > 0 ? 'Plano #' . $planIdCob : '—');
                ?>
                <li class="flex items-center justify-between gap-3 text-sm border border-gray-100 rounded-lg px-3 py-2.5">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-800"><?= $esc($labelTipo) ?></p>
                        <p class="text-xs text-gray-500 truncate"><?= $esc($nomePlano) ?></p>
                    </div>
                    <span class="shrink-0 text-xs text-gray-400">#<?= $planIdCob ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
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

        <!-- Produtos -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Produtos derivados</h3>
            <?php if (empty($produtos)): ?>
            <p class="text-sm text-gray-400">Nenhum produto gerado a partir das cobranças.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500 border-b border-gray-100">
                        <tr>
                            <th class="text-left py-2 pr-3 font-medium">Descrição</th>
                            <th class="text-left py-2 pr-3 font-medium">Tipo</th>
                            <th class="text-right py-2 pr-3 font-medium">Valor</th>
                            <th class="text-right py-2 font-medium">Parcelas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($produtos as $prod): ?>
                        <tr>
                            <td class="py-2 pr-3 text-gray-800"><?= $esc($prod['descricao'] ?? '—') ?></td>
                            <td class="py-2 pr-3 text-gray-500"><?= $esc($prod['tipo'] ?? '—') ?></td>
                            <td class="py-2 pr-3 text-right text-gray-700">R$ <?= number_format((float)($prod['valor_base'] ?? 0), 2, ',', '.') ?></td>
                            <td class="py-2 text-right text-gray-700"><?= (int)($prod['num_parcelas'] ?? 1) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Documentos -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Documentos</h3>

            <?php if (!empty($documentos)): ?>
            <ul class="space-y-2 mb-4">
                <?php foreach ($documentos as $doc): ?>
                <li class="flex items-center justify-between gap-3 text-sm border border-gray-100 rounded-lg px-3 py-2">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-800 truncate"><?= $esc($doc['nome_original'] ?? 'Documento') ?></p>
                        <p class="text-xs text-gray-400">
                            <?= $esc($doc['tipo'] ?? 'outro') ?>
                            <?php if (!empty($doc['created_at'])): ?> · <?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?><?php endif; ?>
                        </p>
                    </div>
                    <form method="POST"
                          action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/documentos/<?= (int)$doc['id'] ?>/remover"
                          onsubmit="return confirm('Remover este documento?')">
                        <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                        <button type="submit" class="text-xs text-red-500 hover:text-red-700">Remover</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-sm text-gray-400 mb-4">Nenhum documento anexado.</p>
            <?php endif; ?>

            <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/documentos"
                  enctype="multipart/form-data" class="space-y-3 border-t border-gray-100 pt-4">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Tipo</label>
                        <select name="tipo_documento" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="rg">RG</option>
                            <option value="cpf">CPF</option>
                            <option value="comprovante_residencia">Comprovante de residência</option>
                            <option value="historico">Histórico escolar</option>
                            <option value="outro" selected>Outro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Arquivo (PDF/JPG/PNG, máx. 10MB)</label>
                        <input type="file" name="documento" required accept=".pdf,.jpg,.jpeg,.png,.webp"
                               class="w-full text-sm text-gray-600">
                    </div>
                </div>
                <button type="submit" class="btn-secondary text-sm">
                    <i class="fa-solid fa-upload mr-1.5"></i> Enviar documento
                </button>
            </form>
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

            <?php if (!empty($enrollment['zapsign_status'])): ?>
            <div class="text-xs text-gray-500 mb-3">
                ZapSign: <span class="font-medium text-gray-700"><?= $esc($enrollment['zapsign_status']) ?></span>
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
            <?php endif; ?>

            <?php if (!empty($whatsapp_url)): ?>
            <a href="<?= $esc($whatsapp_url) ?>" target="_blank"
               class="w-full flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium py-2.5 rounded-xl transition mb-3">
                <i class="fa-brands fa-whatsapp text-base"></i> Enviar por WhatsApp
            </a>
            <?php endif; ?>

            <?php if (!empty($email_url)): ?>
            <a href="<?= $esc($email_url) ?>"
               class="w-full btn-secondary text-sm flex items-center justify-center gap-2 mb-3">
                <i class="fa-solid fa-envelope"></i> Enviar por e-mail
            </a>
            <?php endif; ?>

            <?php if ($enrollment['status'] !== 'cancelada' && $enrollment['status'] !== 'enturmada'): ?>
            <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/contrato" class="mt-1">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <button type="submit" class="w-full btn-primary-custom text-sm">
                    <i class="fa-solid fa-file-contract mr-1.5"></i>
                    <?= $enrollment['contrato_pdf_path'] ? 'Regerar Contrato' : 'Gerar Contrato PDF' ?>
                </button>
            </form>
            <?php endif; ?>

            <?php if (empty($enrollment['assinado_em']) && !in_array($enrollment['status'] ?? '', ['enturmada', 'cancelada', 'abandonada'], true)): ?>
            <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/contrato-assinado"
                  enctype="multipart/form-data" class="mt-3 space-y-2 border-t border-gray-100 pt-3">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <input type="hidden" name="tipo_documento" value="contrato_assinado">
                <label class="block text-xs text-gray-500">Assinatura manual (PDF/JPG)</label>
                <input type="file" name="documento" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="w-full text-xs">
                <button type="submit" class="w-full btn-secondary text-sm">
                    <i class="fa-solid fa-upload mr-1.5"></i> Registrar assinatura manual
                </button>
            </form>
            <?php endif; ?>

            <?php if ($podeEnturmar): ?>
            <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/status" class="mt-2"
                  onsubmit="return confirm('Enturmar este aluno?')">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <input type="hidden" name="novo_status" value="enturmada">
                <button type="submit" class="w-full btn-secondary text-sm">
                    <i class="fa-solid fa-user-plus mr-1.5"></i> Enturmar
                </button>
            </form>
            <?php endif; ?>

            <?php if ($zapsign_ativo && !empty($enrollment['zapsign_doc_token'])): ?>
            <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/zapsign/sincronizar" class="mt-2">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <button type="submit" class="w-full btn-secondary text-sm">
                    <i class="fa-solid fa-rotate mr-1.5"></i> Sync ZapSign
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
                <select name="novo_status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-3">
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
