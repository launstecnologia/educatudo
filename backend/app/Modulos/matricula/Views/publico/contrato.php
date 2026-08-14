<?php
$esc   = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$fmtDt = function($d) {
    if (!$d || $d === '0000-00-00') return '—';
    $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
    return $dt ? $dt->format('d/m/Y') : '—';
};
$tipoLabel = ['nova' => 'Matrícula', 'rematricula' => 'Rematrícula', 'transferencia' => 'Transferência'];
$tipo = $tipoLabel[$enrollment['tipo'] ?? 'nova'] ?? 'Matrícula';
$temHtmlModelo = is_string($contrato_html ?? null) && trim((string) $contrato_html) !== '';
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
    <!-- Cabeçalho -->
    <div class="bg-blue-600 text-white px-6 py-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-file-contract text-white text-lg"></i>
            </div>
            <div>
                <h1 class="font-bold text-lg">Contrato de <?= $esc($tipo) ?></h1>
                <p class="text-blue-100 text-sm">Ano letivo <?= $esc($enrollment['ano_letivo_nome'] ?? date('Y')) ?></p>
            </div>
        </div>
    </div>

    <div class="p-6 space-y-5">

        <?php if ($temHtmlModelo): ?>
        <div class="contrato-modelo prose prose-sm max-w-none text-gray-800 border border-gray-100 rounded-xl p-4 overflow-x-auto">
            <?= $contrato_html ?>
        </div>
        <?php else: ?>
        <!-- Fallback: resumo estruturado quando o modelo HTML não está disponível -->
        <div>
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Dados do Aluno</h2>
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <span class="text-xs text-gray-400">Nome</span>
                    <p class="font-semibold text-gray-800"><?= $esc($enrollment['aluno_nome']) ?></p>
                </div>
                <?php if ($enrollment['aluno_cpf']): ?>
                <div>
                    <span class="text-xs text-gray-400">CPF</span>
                    <p class="text-gray-700"><?= $esc($enrollment['aluno_cpf']) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($enrollment['aluno_data_nasc']): ?>
                <div>
                    <span class="text-xs text-gray-400">Nascimento</span>
                    <p class="text-gray-700"><?= $fmtDt($enrollment['aluno_data_nasc']) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($enrollment['turma_nome']): ?>
                <div>
                    <span class="text-xs text-gray-400">Turma</span>
                    <p class="text-gray-700"><?= $esc($enrollment['turma_nome']) ?><?= $enrollment['turma_serie'] ? ' — ' . $esc($enrollment['turma_serie']) : '' ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <hr class="border-gray-100">

        <div>
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Responsável Legal</h2>
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <span class="text-xs text-gray-400">Nome</span>
                    <p class="font-semibold text-gray-800"><?= $esc($enrollment['resp_nome']) ?></p>
                </div>
                <?php if ($enrollment['resp_cpf']): ?>
                <div>
                    <span class="text-xs text-gray-400">CPF</span>
                    <p class="text-gray-700"><?= $esc($enrollment['resp_cpf']) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($enrollment['resp_telefone']): ?>
                <div>
                    <span class="text-xs text-gray-400">Telefone</span>
                    <p class="text-gray-700"><?= $esc($enrollment['resp_telefone']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($enrollment['observacoes']): ?>
        <hr class="border-gray-100">
        <div>
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Observações</h2>
            <p class="text-gray-600 text-sm"><?= nl2br($esc($enrollment['observacoes'])) ?></p>
        </div>
        <?php endif; ?>

        <hr class="border-gray-100">

        <div class="bg-gray-50 rounded-xl p-4 text-sm text-gray-600 space-y-2">
            <p><strong>Ao assinar este contrato, o responsável declara que:</strong></p>
            <ul class="list-disc ml-4 space-y-1">
                <li>Leu e concorda com os termos da <?= $esc($tipo) ?> para o ano letivo <?= $esc($enrollment['ano_letivo_nome'] ?? date('Y')) ?>.</li>
                <li>Os dados informados são verdadeiros e atualizados.</li>
                <li>Compromete-se a comunicar à escola qualquer alteração cadastral relevante.</li>
            </ul>
            <p class="text-xs text-gray-400 mt-3">
                Esta assinatura digital tem validade jurídica. Será registrado: IP de acesso, data/hora e nome do assinante.
            </p>
        </div>
        <?php endif; ?>

        <?php if (!empty($zapsign_sign_url)): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-900">
            <p class="font-medium mb-2">Assinatura digital externa disponível</p>
            <a href="<?= $esc($zapsign_sign_url) ?>" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 text-amber-800 underline font-semibold">
                <i class="fa-solid fa-external-link"></i> Abrir link ZapSign
            </a>
        </div>
        <?php endif; ?>

        <?php if (!empty($enrollment['contrato_pdf_path'])): ?>
        <a href="<?= URL ?>/matricula/contrato/<?= $esc($enrollment['contrato_token']) ?>/pdf ?>"
           class="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline">
            <i class="fa-solid fa-file-pdf"></i> Baixar PDF do contrato
        </a>
        <?php endif; ?>

        <!-- Formulário de assinatura -->
        <form method="POST" action="<?= URL ?>/matricula/contrato/<?= $esc($enrollment['contrato_token']) ?>/assinar" class="space-y-4">
            <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Confirme seu nome completo para assinar <span class="text-red-500">*</span>
                </label>
                <input type="text" name="nome_assinante"
                       value="<?= $esc($enrollment['resp_nome']) ?>"
                       required
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>

            <div class="flex items-start gap-3">
                <input type="checkbox" id="aceite" required class="mt-1 w-4 h-4 accent-blue-600">
                <label for="aceite" class="text-sm text-gray-600">
                    Li e concordo com os termos do contrato de <?= $esc($tipo) ?> acima.
                </label>
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl transition-colors flex items-center justify-center gap-2">
                <i class="fa-solid fa-signature"></i>
                Assinar e Confirmar <?= $esc($tipo) ?>
            </button>
        </form>

    </div>
</div>
