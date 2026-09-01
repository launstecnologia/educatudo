<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$oficio = is_array($oficio ?? null) ? $oficio : null;
$turmas = is_array($turmas ?? null) ? $turmas : [];
$alunos = is_array($alunos ?? null) ? $alunos : [];
$editando = is_array($oficio);
$turmaId = (int) ($turma_id ?? ($oficio['turma_id'] ?? 0));
$alunoId = (int) ($aluno_id ?? ($oficio['aluno_id'] ?? 0));
$status = (string) ($oficio['status'] ?? 'rascunho');
$somenteLeitura = $editando && $status !== 'rascunho';
$action = $editando
    ? URL . '/admin/vida-escolar/oficios/' . (int) $oficio['id']
    : URL . '/admin/vida-escolar/oficios';

$page_header_back_url = URL . '/admin/vida-escolar/oficios';
$page_header_title = $editando ? 'Editar ofício' : 'Novo ofício';
$page_header_subtitle = $editando && (int) ($oficio['numero'] ?? 0) > 0
    ? 'Ofício nº ' . (int) $oficio['numero'] . '/' . (int) ($oficio['ano'] ?? 0)
    : 'Correspondência oficial da secretaria. O número é gerado na primeira emissão do PDF.';
include dirname(__DIR__, 5) . '/Views/admin/_partials/page_header_form.php';
include dirname(__DIR__, 5) . '/Views/admin/_partials/flash_message.php';
?>

<?php if (empty($schema_pronto)): ?>
<div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-amber-900 text-sm">
    Aplique a migration <code>2026_08_31_secretaria_oficios</code> no painel Master.
</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-lg p-6">
    <form method="POST" action="<?= $esc($action) ?>" class="w-full">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data do ofício <span class="text-red-500">*</span></label>
                <input type="date" name="data_oficio" required <?= $somenteLeitura ? 'readonly' : '' ?>
                       value="<?= $esc($oficio['data_oficio'] ?? date('Y-m-d')) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Instituição (opcional)</label>
                <input type="text" name="instituicao" maxlength="255" <?= $somenteLeitura ? 'readonly' : '' ?>
                       value="<?= $esc($oficio['instituicao'] ?? '') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Ex.: Diretoria de Ensino">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Destinatário <span class="text-red-500">*</span></label>
                <input type="text" name="destinatario" required maxlength="255" <?= $somenteLeitura ? 'readonly' : '' ?>
                       value="<?= $esc($oficio['destinatario'] ?? '') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Nome de quem recebe">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Cargo (opcional)</label>
                <input type="text" name="cargo_destinatario" maxlength="255" <?= $somenteLeitura ? 'readonly' : '' ?>
                       value="<?= $esc($oficio['cargo_destinatario'] ?? '') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Ex.: Diretor(a)">
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Assunto <span class="text-red-500">*</span></label>
            <input type="text" name="assunto" required maxlength="255" <?= $somenteLeitura ? 'readonly' : '' ?>
                   value="<?= $esc($oficio['assunto'] ?? '') ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Turma (opcional)</label>
                <select name="turma_id" id="oficio_turma_id" <?= $somenteLeitura ? 'disabled' : '' ?>
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="0">Sem turma</option>
                    <?php foreach ($turmas as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= $turmaId === (int) $t['id'] ? 'selected' : '' ?>><?= $esc($t['nome'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Aluno (opcional)</label>
                <select name="aluno_id" id="oficio_aluno_id" <?= $somenteLeitura ? 'disabled' : '' ?>
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="0">Sem aluno</option>
                    <?php foreach ($alunos as $a): ?>
                    <option value="<?= (int) $a['id'] ?>" <?= $alunoId === (int) $a['id'] ? 'selected' : '' ?>><?= $esc($a['nome'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Texto do ofício <span class="text-red-500">*</span></label>
            <textarea name="corpo" rows="10" required <?= $somenteLeitura ? 'readonly' : '' ?>
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?= $esc($oficio['corpo'] ?? '') ?></textarea>
        </div>

        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/admin/vida-escolar/oficios" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Voltar</a>
            <?php if ($editando && $status !== 'cancelado'): ?>
            <a href="<?= URL ?>/admin/vida-escolar/oficios/<?= (int) $oficio['id'] ?>/pdf"
               class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                <?= $status === 'rascunho' ? 'Prévia PDF' : 'Baixar PDF' ?>
            </a>
            <?php endif; ?>
            <?php if ($editando && $status === 'rascunho'): ?>
            <button type="submit" form="form-emitir-oficio" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Emitir</button>
            <?php endif; ?>
            <?php if (!$somenteLeitura): ?>
            <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Salvar rascunho</button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php if ($editando && $status === 'rascunho'): ?>
<form id="form-emitir-oficio" method="post" action="<?= URL ?>/admin/vida-escolar/oficios/<?= (int) $oficio['id'] ?>/emitir">
    <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
</form>
<?php endif; ?>
<?php if (!$somenteLeitura): ?>
<script>
(function () {
    var turma = document.getElementById('oficio_turma_id');
    var aluno = document.getElementById('oficio_aluno_id');
    if (!turma || !aluno) return;
    turma.addEventListener('change', function () {
        var id = parseInt(turma.value, 10) || 0;
        aluno.innerHTML = '<option value="0">Carregando…</option>';
        if (id <= 0) {
            aluno.innerHTML = '<option value="0">Sem aluno</option>';
            return;
        }
        fetch(<?= json_encode(URL . '/admin/vida-escolar/oficios/alunos') ?> + '?turma_id=' + id, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                aluno.innerHTML = '';
                var vazio = document.createElement('option');
                vazio.value = '0';
                vazio.textContent = 'Sem aluno';
                aluno.appendChild(vazio);
                (data.alunos || []).forEach(function (a) {
                    var opt = document.createElement('option');
                    opt.value = String(a.id || '');
                    opt.textContent = a.nome || '';
                    aluno.appendChild(opt);
                });
            })
            .catch(function () {
                aluno.innerHTML = '';
                var vazio = document.createElement('option');
                vazio.value = '0';
                vazio.textContent = 'Sem aluno';
                aluno.appendChild(vazio);
            });
    });
})();
</script>
<?php endif; ?>
<?php endif; ?>
