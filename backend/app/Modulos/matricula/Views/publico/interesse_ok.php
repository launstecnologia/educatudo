<?php
$esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="bg-green-600 text-white px-6 py-8 text-center">
        <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-check text-white text-3xl"></i>
        </div>
        <h1 class="font-bold text-2xl">Interesse registrado!</h1>
        <p class="text-green-100 mt-1">Recebemos sua solicitação com sucesso.</p>
    </div>

    <div class="p-6 space-y-4 text-center">
        <?php if (!empty($aluno_nome)): ?>
        <p class="text-sm text-gray-600">
            Interesse de matrícula para <strong class="text-gray-800"><?= $esc($aluno_nome) ?></strong>.
        </p>
        <?php endif; ?>
        <p class="text-sm text-gray-600">
            A secretaria de <?= $esc($nomeEscola ?? ($escola['nome'] ?? 'nossa escola')) ?> entrará em contato
            pelos dados informados para dar continuidade ao processo.
        </p>
        <div class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3 text-sm text-gray-500">
            Não é necessário enviar o formulário novamente.
        </div>
    </div>
</div>
