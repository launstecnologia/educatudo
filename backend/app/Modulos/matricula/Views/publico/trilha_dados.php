<?php
$esc   = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$fmtDt = function ($d) {
    if (!$d || $d === '0000-00-00') return '—';
    $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
    return $dt ? $dt->format('d/m/Y') : '—';
};
$tipoLabel = ['nova' => 'Matrícula', 'rematricula' => 'Rematrícula', 'transferencia' => 'Transferência'];
$tipo = $tipoLabel[$enrollment['tipo'] ?? 'nova'] ?? 'Matrícula';
$token = $enrollment['contrato_token'] ?? '';
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="bg-blue-600 text-white px-6 py-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-user-check text-white text-lg"></i>
            </div>
            <div>
                <h1 class="font-bold text-lg">Confirme os dados</h1>
                <p class="text-blue-100 text-sm">Etapa 1 · <?= $esc($tipo) ?> <?= $esc($enrollment['ano_letivo_nome'] ?? date('Y')) ?></p>
            </div>
        </div>
    </div>

    <div class="p-6 space-y-5">
        <p class="text-sm text-gray-600">
            Revise os dados abaixo. Se algo estiver incorreto, entre em contato com a secretaria antes de continuar.
        </p>

        <div>
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Dados do Aluno</h2>
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <span class="text-xs text-gray-400">Nome</span>
                    <p class="font-semibold text-gray-800"><?= $esc($enrollment['aluno_nome'] ?? '') ?></p>
                </div>
                <?php if (!empty($enrollment['aluno_cpf'])): ?>
                <div>
                    <span class="text-xs text-gray-400">CPF</span>
                    <p class="text-gray-700"><?= $esc($enrollment['aluno_cpf']) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($enrollment['aluno_data_nasc'])): ?>
                <div>
                    <span class="text-xs text-gray-400">Nascimento</span>
                    <p class="text-gray-700"><?= $fmtDt($enrollment['aluno_data_nasc']) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($enrollment['aluno_email'])): ?>
                <div>
                    <span class="text-xs text-gray-400">E-mail</span>
                    <p class="text-gray-700"><?= $esc($enrollment['aluno_email']) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($enrollment['aluno_telefone'])): ?>
                <div>
                    <span class="text-xs text-gray-400">Telefone</span>
                    <p class="text-gray-700"><?= $esc($enrollment['aluno_telefone']) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($enrollment['turma_nome'])): ?>
                <div>
                    <span class="text-xs text-gray-400">Turma</span>
                    <p class="text-gray-700"><?= $esc($enrollment['turma_nome']) ?><?= !empty($enrollment['turma_serie']) ? ' — ' . $esc($enrollment['turma_serie']) : '' ?></p>
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
                    <p class="font-semibold text-gray-800"><?= $esc($enrollment['resp_nome'] ?? '') ?></p>
                </div>
                <?php if (!empty($enrollment['resp_cpf'])): ?>
                <div>
                    <span class="text-xs text-gray-400">CPF</span>
                    <p class="text-gray-700"><?= $esc($enrollment['resp_cpf']) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($enrollment['resp_parentesco'])): ?>
                <div>
                    <span class="text-xs text-gray-400">Parentesco</span>
                    <p class="text-gray-700"><?= $esc($enrollment['resp_parentesco']) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($enrollment['resp_email'])): ?>
                <div>
                    <span class="text-xs text-gray-400">E-mail</span>
                    <p class="text-gray-700"><?= $esc($enrollment['resp_email']) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($enrollment['resp_telefone'])): ?>
                <div>
                    <span class="text-xs text-gray-400">Telefone / WhatsApp</span>
                    <p class="text-gray-700"><?= $esc($enrollment['resp_telefone']) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($enrollment['resp_endereco'])): ?>
                <div class="col-span-2">
                    <span class="text-xs text-gray-400">Endereço</span>
                    <p class="text-gray-700"><?= $esc($enrollment['resp_endereco']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <form method="POST" action="<?= URL ?>/matricula/contrato/<?= $esc($token) ?>/dados" class="pt-2">
            <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl transition-colors flex items-center justify-center gap-2">
                <i class="fa-solid fa-check"></i>
                Confirmar dados
            </button>
        </form>
    </div>
</div>
