<?php
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$val = fn($k)  => $esc($enrollment[$k] ?? '');

// Pré-seleção a partir de finance_cobrancas (JSON) ou finance_plan_id
$cobrancas_selecionadas = [];
$rawCobrancas = $enrollment['finance_cobrancas'] ?? null;
if (is_string($rawCobrancas)) {
    $rawCobrancas = json_decode($rawCobrancas, true);
}
if (is_array($rawCobrancas)) {
    foreach ($rawCobrancas as $row) {
        if (!is_array($row)) continue;
        $t = (string)($row['tipo'] ?? '');
        $p = (int)($row['plan_id'] ?? 0);
        if ($t !== '' && $p > 0) {
            $cobrancas_selecionadas[$t] = $p;
        }
    }
}
if ($cobrancas_selecionadas === [] && !empty($enrollment['finance_plan_id'])) {
    $cobrancas_selecionadas['mensalidade'] = (int)$enrollment['finance_plan_id'];
}

$page_header_title    = 'Editar Matrícula #' . (int)$enrollment['id'];
$page_header_subtitle = $enrollment['aluno_nome'] ?? '';
ob_start(); ?>
<a href="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>" class="btn-secondary text-sm">← Voltar</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php'; ?>

<form method="POST" action="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>/edit" class="space-y-6 max-w-3xl">
    <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
    <input type="hidden" name="_method" value="PUT">

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
        <h3 class="font-semibold text-gray-800">Vínculo</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ano Letivo</label>
                <select name="ano_letivo_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                    <option value="">—</option>
                    <?php foreach ($anos_letivos as $al): ?>
                    <option value="<?= (int)$al['id'] ?>" <?= (int)($enrollment['ano_letivo_id'] ?? 0) === (int)$al['id'] ? 'selected' : '' ?>><?= $esc($al['ano']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Turma</label>
                <select name="turma_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                    <option value="">—</option>
                    <?php foreach ($turmas as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (int)($enrollment['turma_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
                        <?= $esc($t['nome']) ?> — <?= $esc($t['serie']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
        <h3 class="font-semibold text-gray-800">Cobranças / Planos</h3>
        <?php
        $selectClass = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm';
        include __DIR__ . '/_cobrancas_form.php';
        ?>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
        <h3 class="font-semibold text-gray-800">Dados do Aluno</h3>
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <input type="text" name="aluno_nome" value="<?= $val('aluno_nome') ?>" required class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">CPF / CIN</label>
                <input type="text" name="aluno_cpf" value="<?= $val('aluno_cpf') ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Nascimento</label>
                <input type="date" name="aluno_data_nasc" value="<?= $val('aluno_data_nasc') ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                <input type="email" name="aluno_email" value="<?= $val('aluno_email') ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                <input type="text" name="aluno_telefone" value="<?= $val('aluno_telefone') ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm"></div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
        <h3 class="font-semibold text-gray-800">Responsável</h3>
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <input type="text" name="resp_nome" value="<?= $val('resp_nome') ?>" required class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">CPF / CIN</label>
                <input type="text" name="resp_cpf" value="<?= $val('resp_cpf') ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Parentesco</label>
                <input type="text" name="resp_parentesco" value="<?= $val('resp_parentesco') ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                <input type="email" name="resp_email" value="<?= $val('resp_email') ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp</label>
                <input type="text" name="resp_telefone" value="<?= $val('resp_telefone') ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm"></div>
            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Endereço</label>
                <input type="text" name="resp_endereco" value="<?= $val('resp_endereco') ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
        <h3 class="font-semibold text-gray-800">Extras</h3>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Expira em</label>
                <input type="date" name="expira_em" value="<?= $enrollment['expira_em'] ? substr($enrollment['expira_em'], 0, 10) : '' ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm"></div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
            <textarea name="observacoes" rows="3" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm resize-none"><?= $val('observacoes') ?></textarea>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="btn-primary">Salvar alterações</button>
        <a href="<?= URL ?>/admin/enrollment/<?= (int)$enrollment['id'] ?>" class="btn-secondary">Cancelar</a>
    </div>
</form>
