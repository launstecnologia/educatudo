<?php
$esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$old = is_array($old ?? null) ? $old : [];
$erros = is_array($erros ?? null) ? $erros : [];
$val = static function (array $old, string $key, $default = '') {
    return $old[$key] ?? $default;
};
$parentescos = [
    'pai' => 'Pai',
    'mae' => 'Mãe',
    'avo' => 'Avô/Avó',
    'tio' => 'Tio/Tia',
    'responsavel' => 'Responsável legal',
    'outro' => 'Outro',
];
$anoPadrao = (int) ($ano_letivo_padrao ?? 0);
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="bg-blue-600 text-white px-6 py-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-hand-holding-heart text-white text-lg"></i>
            </div>
            <div>
                <h1 class="font-bold text-lg">Interesse de matrícula</h1>
                <p class="text-blue-100 text-sm">Preencha os dados e a escola entrará em contato.</p>
            </div>
        </div>
    </div>

    <form method="POST" action="<?= URL ?>/matricula/interesse" class="p-6 space-y-6">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">

        <?php if ($erros !== []): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm px-4 py-3 space-y-1">
            <?php foreach ($erros as $erro): ?>
            <p><?= $esc($erro) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div>
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Dados do aluno</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_nome">Nome completo <span class="text-red-500">*</span></label>
                    <input type="text" id="aluno_nome" name="aluno_nome" required maxlength="255"
                           value="<?= $esc($val($old, 'aluno_nome')) ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_data_nasc">Data de nascimento</label>
                    <input type="date" id="aluno_data_nasc" name="aluno_data_nasc"
                           value="<?= $esc($val($old, 'aluno_data_nasc')) ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_cpf">CPF</label>
                    <input type="text" id="aluno_cpf" name="aluno_cpf" maxlength="14" inputmode="numeric"
                           value="<?= $esc($val($old, 'aluno_cpf')) ?>"
                           placeholder="000.000.000-00"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="ano_letivo_id">Ano letivo de interesse</label>
                    <select id="ano_letivo_id" name="ano_letivo_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Selecione (opcional)</option>
                        <?php foreach (($anos_letivos ?? []) as $al): ?>
                        <?php
                            $idAl = (int) ($al['id'] ?? 0);
                            $sel = (int) $val($old, 'ano_letivo_id', $anoPadrao) === $idAl;
                        ?>
                        <option value="<?= $idAl ?>" <?= $sel ? 'selected' : '' ?>><?= $esc($al['ano'] ?? $idAl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <hr class="border-gray-100">

        <div>
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Responsável</h2>
            <p class="text-xs text-gray-500 mb-3">Informe telefone ou e-mail (pelo menos um).</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="resp_nome">Nome completo <span class="text-red-500">*</span></label>
                    <input type="text" id="resp_nome" name="resp_nome" required maxlength="255"
                           value="<?= $esc($val($old, 'resp_nome')) ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="resp_telefone">Telefone / WhatsApp</label>
                    <input type="tel" id="resp_telefone" name="resp_telefone" maxlength="20"
                           value="<?= $esc($val($old, 'resp_telefone')) ?>"
                           placeholder="(00) 00000-0000"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="resp_email">E-mail</label>
                    <input type="email" id="resp_email" name="resp_email" maxlength="255"
                           value="<?= $esc($val($old, 'resp_email')) ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="resp_parentesco">Parentesco</label>
                    <select id="resp_parentesco" name="resp_parentesco"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Selecione</option>
                        <?php foreach ($parentescos as $k => $label): ?>
                        <option value="<?= $esc($k) ?>" <?= $val($old, 'resp_parentesco') === $k ? 'selected' : '' ?>><?= $esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="observacoes">Observações</label>
            <textarea id="observacoes" name="observacoes" rows="3" maxlength="2000"
                      placeholder="Série desejada, turno, dúvidas…"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?= $esc($val($old, 'observacoes')) ?></textarea>
        </div>

        <button type="submit"
                class="w-full inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl px-4 py-3 text-sm transition">
            <i class="fa-solid fa-paper-plane"></i>
            Enviar interesse
        </button>

        <p class="text-xs text-center text-gray-400">
            Seus dados serão usados apenas pela escola para o processo de matrícula.
        </p>
    </form>
</div>
