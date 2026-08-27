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
$foco_contrato = !empty($foco_contrato) || (($_GET['foco'] ?? '') === 'contrato');
$tiposDocumentoLabel = [
    'rg' => 'RG',
    'cpf' => 'CPF',
    'comprovante_residencia' => 'Comprovante de residência',
    'historico' => 'Histórico escolar',
    'certidao' => 'Certidão de nascimento',
    'declaracao_transferencia' => 'Declaração de transferência',
    'contrato_assinado' => 'Contrato assinado',
    'outro' => 'Outro',
];
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
$passoFaltando = 1;
foreach ($faltando_enturmar as $campoFalta) {
    $campoFalta = (string) $campoFalta;
    if ($campoFalta === 'Ano letivo' || $campoFalta === 'Turma') {
        $passoFaltando = 1;
        break;
    }
    if (str_starts_with($campoFalta, 'Aluno:')) {
        $passoFaltando = 2;
        break;
    }
    if (str_starts_with($campoFalta, 'Responsável:')) {
        $passoFaltando = 3;
        break;
    }
}
$editUrl = URL . '/admin/enrollment/' . (int)$enrollment['id'] . '/edit';
if (!empty($faltando_enturmar)) {
    $editUrl .= '?passo=' . (int) $passoFaltando;
}
$podeEditarProcesso = !in_array($enrollment['status'] ?? '', ['enturmada', 'cancelada'], true);
$alunoIdFicha = (int) ($enrollment['aluno_id'] ?? 0);
$mostrarVidaEscolar = $alunoIdFicha > 0 && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('vida_escolar'));
ob_start(); ?>
<?php if ($alunoIdFicha > 0): ?>
<a href="<?= URL ?>/admin/students/<?= $alunoIdFicha ?>" class="btn-secondary text-sm">
    <i class="fa-solid fa-id-card mr-1.5"></i> Ficha da aluna
</a>
<?php if ($mostrarVidaEscolar): ?>
<a href="<?= URL ?>/admin/students/<?= $alunoIdFicha ?>/vida-escolar" class="btn-primary text-sm">
    <i class="fa-solid fa-scroll mr-1.5"></i> Vida escolar
</a>
<?php endif; ?>
<?php endif; ?>
<?php if ($podeEditarProcesso): ?>
<a href="<?= $esc($editUrl) ?>" class="btn-secondary text-sm">
    <i class="fa-solid fa-pen mr-1.5"></i> Editar
</a>
<?php endif; ?>
<a href="<?= URL ?>/admin/enrollment" class="btn-secondary text-sm">← Voltar</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php'; ?>

<?php if (!empty($status_message)): ?>
<div class="mb-4 p-3 rounded-xl text-sm <?= ($status_type ?? '') === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
    <?= $esc($status_message) ?>
</div>
<?php endif; ?>

<?php if (!empty($faltando_enturmar)): ?>
<div class="mb-4 p-4 rounded-xl text-sm bg-red-50 text-red-800 border border-red-200">
    <p class="font-semibold mb-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Campos faltando para enturmar</p>
    <ul class="list-disc ml-5 space-y-0.5">
        <?php foreach ($faltando_enturmar as $campo): ?>
        <li><?= $esc($campo) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php if ($podeEditarProcesso): ?>
    <a href="<?= $esc($editUrl) ?>" class="inline-flex items-center mt-3 text-sm font-semibold hover:underline">
        <i class="fa-solid fa-pen mr-1.5"></i> Preencher no wizard
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (($enrollment['status'] ?? '') === 'lista_espera'): ?>
<div class="mb-4 p-4 rounded-xl text-sm bg-purple-50 text-purple-900 border border-purple-200 flex flex-wrap items-center justify-between gap-3">
    <p>
        <i class="fa-solid fa-clock mr-1"></i>
        Na lista de espera<?= !empty($enrollment['fila_posicao']) ? ' — posição ' . (int) $enrollment['fila_posicao'] : '' ?>.
        <?php
        $vr = $vagas_resumo ?? null;
        if (is_array($vr) && empty($vr['ilimitado'])):
        ?>
        Turma: <?= (int) ($vr['ocupadas'] ?? 0) + (int) ($vr['reservadas'] ?? 0) ?>/<?= (int) ($vr['vagas'] ?? 0) ?>.
        <?php endif; ?>
    </p>
    <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int) $enrollment['id'] ?>/oferecer-vaga">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
        <button type="submit" class="btn-primary text-sm">Oferecer vaga</button>
    </form>
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
                <i class="fa-solid fa-file-signature" style="color: var(--primary-color);"></i>
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
        <?php if (!empty($whatsapp_url)): ?>
        <a href="<?= $esc($whatsapp_url) ?>" target="_blank"
           class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium bg-green-500 hover:bg-green-600 text-white shrink-0">
            <i class="fa-brands fa-whatsapp mr-1.5"></i> WhatsApp
        </a>
        <?php endif; ?>
    </div>

    <?php if ($contratosProcesso === []): ?>
    <div class="rounded-xl border border-dashed border-gray-300 bg-white/70 p-4 text-sm text-gray-600">
        Nenhuma regra de contrato ativa.
        <a href="<?= URL ?>/admin/enrollment/config" class="underline font-medium" style="color: var(--primary-color);">Configurar em Z-Configuração → Matrícula</a>.
        Tokens ZapSign em <a href="<?= URL ?>/admin/configuracao/assinatura-digital" class="underline font-medium" style="color: var(--primary-color);">Assinatura Digital</a>.
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
          class="pt-4 border-t border-gray-200">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
        <input type="hidden" name="tipo_documento" value="contrato_assinado">
        <p class="text-sm font-medium text-gray-800 mb-1">Assinatura manual</p>
        <p class="text-xs text-gray-500 mb-3">Anexe o contrato já assinado em PDF ou imagem.</p>
        <div class="flex flex-col sm:flex-row gap-3 sm:items-stretch">
            <div class="js-dropzone flex-1 rounded-xl border-2 border-dashed border-gray-300 bg-white px-4 py-5 text-center cursor-pointer transition-colors hover:bg-gray-50"
                 role="button" tabindex="0">
                <input type="file" name="documento" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="sr-only js-file-input">
                <div class="js-dropzone-idle">
                    <i class="fa-solid fa-cloud-arrow-up text-xl text-gray-400 mb-1"></i>
                    <p class="text-sm font-medium text-gray-800">Arraste e solte o contrato assinado</p>
                    <p class="text-xs text-gray-500 mt-0.5">ou clique para escolher</p>
                </div>
                <div class="js-dropzone-ready hidden">
                    <p class="js-file-label text-sm font-medium text-gray-800 truncate"></p>
                    <p class="text-xs text-gray-500 mt-0.5">Clique ou solte para trocar</p>
                </div>
            </div>
            <button type="submit" class="btn-secondary inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium whitespace-nowrap">
                <i class="fa-solid fa-upload mr-1.5"></i> Registrar assinatura
            </button>
        </div>
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
                <div class="col-span-2">
                    <dt class="text-gray-400 text-xs">Nome</dt>
                    <dd class="font-medium text-gray-800">
                        <?php if ($alunoIdFicha > 0): ?>
                        <a href="<?= URL ?>/admin/students/<?= $alunoIdFicha ?>" class="hover:underline"><?= $esc($enrollment['aluno_nome']) ?></a>
                        <?php else: ?>
                        <?= $esc($enrollment['aluno_nome']) ?>
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs">CPF</dt>
                    <?php
                    $cpfShowDigits = preg_replace('/\D+/', '', (string) ($enrollment['aluno_cpf'] ?? '')) ?? '';
                    $cpfVazio = strlen($cpfShowDigits) !== 11;
                    ?>
                    <dd class="<?= $cpfVazio ? 'text-red-600 font-medium' : 'text-gray-700' ?>">
                        <?= $cpfVazio ? 'Não preenchido' : $esc($enrollment['aluno_cpf']) ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs">Nascimento</dt>
                    <?php
                    $nascShow = trim((string) ($enrollment['aluno_data_nasc'] ?? ''));
                    $nascVazio = $nascShow === '' || $nascShow === '0000-00-00';
                    ?>
                    <dd class="<?= $nascVazio ? 'text-red-600 font-medium' : 'text-gray-700' ?>">
                        <?= $nascVazio ? 'Não preenchido' : date('d/m/Y', strtotime($nascShow)) ?>
                    </dd>
                </div>
                <div><dt class="text-gray-400 text-xs">E-mail</dt><dd class="text-gray-700"><?= $esc($enrollment['aluno_email'] ?? '—') ?></dd></div>
                <div><dt class="text-gray-400 text-xs">Telefone</dt><dd class="text-gray-700"><?= $esc($enrollment['aluno_telefone'] ?? '—') ?></dd></div>
                <div><dt class="text-gray-400 text-xs">Turma</dt><dd class="text-gray-700"><?= $esc($enrollment['turma_nome'] ?? '—') ?> <?= $enrollment['turma_serie'] ? '— ' . $esc($enrollment['turma_serie']) : '' ?></dd></div>
                <div><dt class="text-gray-400 text-xs">Ano Letivo</dt><dd class="text-gray-700"><?= $esc($enrollment['ano_letivo_nome'] ?? '—') ?></dd></div>
                <div><dt class="text-gray-400 text-xs">Nome da mãe</dt><dd class="text-gray-700"><?= $esc($enrollment['aluno_nome_mae'] ?? '—') ?></dd></div>
                <div><dt class="text-gray-400 text-xs">Nome do pai</dt><dd class="text-gray-700"><?= $esc($enrollment['aluno_nome_pai'] ?? '—') ?></dd></div>
                <div><dt class="text-gray-400 text-xs">Cor / raça</dt><dd class="text-gray-700"><?= $esc($enrollment['aluno_cor_raca'] ?? '—') ?></dd></div>
                <div><dt class="text-gray-400 text-xs">Nacionalidade</dt><dd class="text-gray-700"><?= $esc($enrollment['aluno_nacionalidade'] ?? '—') ?></dd></div>
                <?php if (!empty($enrollment['aluno_codigo_inep'])): ?>
                <div><dt class="text-gray-400 text-xs">Código INEP</dt><dd class="text-gray-700"><?= $esc($enrollment['aluno_codigo_inep']) ?></dd></div>
                <?php endif; ?>
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
            <?php
            $checklist_itens = $checklist_itens ?? [];
            $checklist_faltando = $checklist_faltando ?? [];
            $anexadosTipo = [];
            foreach ($documentos as $doc) {
                $anexadosTipo[strtolower((string) ($doc['tipo'] ?? ''))] = true;
            }
            if ($checklist_itens !== []):
            ?>
            <ul class="mb-4 space-y-1.5 text-sm">
                <?php foreach ($checklist_itens as $item):
                    $okDoc = !empty($anexadosTipo[strtolower((string) ($item['codigo'] ?? ''))]);
                ?>
                <li class="flex items-center gap-2 <?= $okDoc ? 'text-green-700' : (!empty($item['obrigatorio']) ? 'text-red-700' : 'text-gray-500') ?>">
                    <i class="fa-solid <?= $okDoc ? 'fa-circle-check' : 'fa-circle' ?> text-xs"></i>
                    <?= $esc($item['rotulo'] ?? $item['codigo']) ?>
                    <?php if (!empty($item['obrigatorio']) && !$okDoc): ?><span class="text-xs">(obrigatório)</span><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <?php if (!empty($documentos)): ?>
            <ul class="space-y-2 mb-4">
                <?php foreach ($documentos as $doc):
                    $tipoDoc = (string) ($doc['tipo'] ?? 'outro');
                ?>
                <li class="flex items-center justify-between gap-3 text-sm border border-gray-200 rounded-lg px-3 py-2.5 bg-gray-50/60">
                    <div class="min-w-0 flex items-start gap-3">
                        <span class="mt-0.5 w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center shrink-0 text-gray-500">
                            <i class="fa-solid fa-file-lines text-sm"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="font-medium text-gray-800 truncate"><?= $esc($doc['nome_original'] ?? 'Documento') ?></p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <?= $esc($tiposDocumentoLabel[$tipoDoc] ?? $tipoDoc) ?>
                                <?php if (!empty($doc['created_at'])): ?> · <?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?><?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <form method="POST"
                          action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/documentos/<?= (int)$doc['id'] ?>/remover"
                          onsubmit="return confirm('Remover este documento?')">
                        <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                        <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-800 px-2 py-1 rounded-md hover:bg-red-50">Remover</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-sm text-gray-500 mb-4">Nenhum documento anexado.</p>
            <?php endif; ?>

            <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/documentos"
                  enctype="multipart/form-data" class="border-t border-gray-100 pt-4 space-y-3">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <div class="max-w-xs">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                    <select name="tipo_documento" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white">
                        <option value="rg">RG</option>
                        <option value="cpf">CPF</option>
                        <option value="certidao">Certidão de nascimento</option>
                        <option value="comprovante_residencia">Comprovante de residência</option>
                        <option value="historico">Histórico escolar</option>
                        <option value="declaracao_transferencia">Declaração de transferência</option>
                        <option value="outro" selected>Outro</option>
                    </select>
                </div>
                <div class="js-dropzone rounded-xl border-2 border-dashed border-gray-300 bg-gray-50/70 px-4 py-8 text-center cursor-pointer transition-colors hover:bg-gray-50"
                     role="button" tabindex="0">
                    <input type="file" name="documento" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="sr-only js-file-input">
                    <div class="js-dropzone-idle">
                        <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-400 mb-2"></i>
                        <p class="text-sm font-semibold text-gray-800">Arraste e solte o arquivo aqui</p>
                        <p class="text-xs text-gray-500 mt-1">ou clique para escolher · PDF, JPG ou PNG · máx. 10MB</p>
                    </div>
                    <div class="js-dropzone-ready hidden">
                        <i class="fa-solid fa-file-circle-check text-2xl mb-2" style="color: var(--primary-color);"></i>
                        <p class="js-file-label text-sm font-medium text-gray-800 truncate px-2"></p>
                        <p class="text-xs text-gray-500 mt-1">Clique ou solte outro arquivo para trocar</p>
                    </div>
                </div>
                <button type="submit" class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
                    <i class="fa-solid fa-upload mr-2"></i> Enviar documento
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
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 space-y-4">
            <h3 class="font-semibold text-gray-800">Contrato</h3>

            <?php if ($enrollment['assinado_em']): ?>
            <div class="bg-green-50 border border-green-100 rounded-xl p-3 text-sm text-green-700">
                <i class="fa-solid fa-check-circle mr-1"></i>
                Assinado em <?= $fmtDt($enrollment['assinado_em']) ?>
                <?php if (!empty($enrollment['assinante_nome'])): ?>
                <span class="block text-xs text-green-600 mt-0.5">por <?= $esc($enrollment['assinante_nome']) ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($enrollment['zapsign_status'])): ?>
            <p class="text-xs text-gray-500">
                ZapSign: <span class="font-medium text-gray-700"><?= $esc($enrollment['zapsign_status']) ?></span>
            </p>
            <?php endif; ?>

            <?php if ($enrollment['status'] !== 'cancelada' && $enrollment['status'] !== 'enturmada'): ?>
            <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/contrato">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <button type="submit" class="w-full btn-primary-custom inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
                    <i class="fa-solid fa-file-contract"></i>
                    <?= $enrollment['contrato_pdf_path'] ? 'Regerar contrato PDF' : 'Gerar contrato PDF' ?>
                </button>
            </form>
            <?php endif; ?>

            <?php if (!empty($enrollment['contrato_pdf_path'])): ?>
            <a href="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/contrato/download"
               class="w-full btn-secondary inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium">
                <i class="fa-solid fa-download text-gray-500"></i> Baixar PDF
            </a>
            <?php if (!empty($enrollment['contrato_token'])): ?>
            <p class="text-xs text-gray-400 break-all">
                Link público:
                <a href="<?= URL ?>/matricula/contrato/<?= $esc($enrollment['contrato_token']) ?>"
                   target="_blank" class="hover:underline" style="color: var(--primary-color);">
                    abrir contrato
                </a>
            </p>
            <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($whatsapp_url)): ?>
            <a href="<?= $esc($whatsapp_url) ?>" target="_blank"
               class="w-full inline-flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium py-2.5 rounded-lg transition">
                <i class="fa-brands fa-whatsapp text-base"></i> Enviar por WhatsApp
            </a>
            <?php endif; ?>

            <?php if (empty($enrollment['assinado_em']) && !in_array($enrollment['status'] ?? '', ['enturmada', 'cancelada', 'abandonada'], true)): ?>
            <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/contrato-assinado"
                  enctype="multipart/form-data" class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 space-y-3">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <input type="hidden" name="tipo_documento" value="contrato_assinado">
                <div>
                    <p class="text-sm font-medium text-gray-800">Assinatura manual</p>
                    <p class="text-xs text-gray-500 mt-0.5">PDF ou imagem do contrato já assinado</p>
                </div>
                <div class="js-dropzone rounded-xl border-2 border-dashed border-gray-300 bg-white px-3 py-5 text-center cursor-pointer transition-colors hover:bg-gray-50"
                     role="button" tabindex="0">
                    <input type="file" name="documento" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="sr-only js-file-input">
                    <div class="js-dropzone-idle">
                        <i class="fa-solid fa-cloud-arrow-up text-lg text-gray-400 mb-1"></i>
                        <p class="text-sm font-medium text-gray-800">Arraste e solte aqui</p>
                        <p class="text-xs text-gray-500 mt-0.5">ou clique para escolher</p>
                    </div>
                    <div class="js-dropzone-ready hidden">
                        <p class="js-file-label text-sm font-medium text-gray-800 truncate"></p>
                    </div>
                </div>
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-upload text-gray-500"></i> Registrar assinatura
                </button>
            </form>
            <?php endif; ?>

            <?php if ($podeEnturmar): ?>
            <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/status"
                  onsubmit="return confirm('Enturmar este aluno?')">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <input type="hidden" name="novo_status" value="enturmada">
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-user-plus text-gray-500"></i> Enturmar
                </button>
            </form>
            <?php endif; ?>

            <?php if ($zapsign_ativo && !empty($enrollment['zapsign_doc_token'])): ?>
            <form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/zapsign/sincronizar">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-rotate text-gray-500"></i> Sync ZapSign
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

<style>
.js-dropzone.is-dragover {
    border-color: var(--primary-color) !important;
    background-color: color-mix(in srgb, var(--primary-color) 8%, white) !important;
}
</style>
<script>
(function () {
    function setInputFile(input, file) {
        try {
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
        } catch (err) {
            return false;
        }
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    function markReady(zone, fileName) {
        var idle = zone.querySelector('.js-dropzone-idle');
        var ready = zone.querySelector('.js-dropzone-ready');
        var label = zone.querySelector('.js-file-label');
        if (idle) idle.classList.add('hidden');
        if (ready) ready.classList.remove('hidden');
        if (label) label.textContent = fileName || '';
    }

    document.querySelectorAll('.js-dropzone').forEach(function (zone) {
        var input = zone.querySelector('.js-file-input');
        if (!input) return;

        zone.addEventListener('click', function (e) {
            if (e.target === input) return;
            input.click();
        });
        zone.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                input.click();
            }
        });
        ['dragenter', 'dragover'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('is-dragover');
            });
        });
        zone.addEventListener('drop', function (e) {
            var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (!file) return;
            setInputFile(input, file);
        });
        input.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                markReady(zone, this.files[0].name);
            }
        });
    });
})();
</script>
