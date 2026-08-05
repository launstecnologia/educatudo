<?php
/**
 * Provas canceladas no bloco: lista de alunos que precisam de liberação para refazer
 */
$canceladas = $canceladas ?? [];
?>

<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Canceladas: <?= htmlspecialchars($bloco['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                Alunos com prova cancelada (saída do modo seguro). Se o aluno já respondeu, use <strong>Validar nota</strong>. Caso contrário, libere nova tentativa.
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/resultados-novos"
               class="inline-flex items-center gap-2 bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                Resultados
            </a>
            <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/gerenciar"
               class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
                Voltar ao bloco
            </a>
        </div>
    </div>
</div>

<?php if (!empty($flash_message)): ?>
<div class="mb-6 p-4 rounded-lg border-2 <?= ($flash_type ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-800' : (($flash_type ?? '') === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-blue-50 border-blue-200 text-blue-800') ?>">
    <p class="font-medium"><?= htmlspecialchars($flash_message) ?></p>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Provas canceladas que precisam de liberação</h3>
    <?php if (!empty($canceladas)): ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">RA</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prova / Matéria</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Respostas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ação</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($canceladas as $row): ?>
                <tr>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($row['aluno_nome'] ?? '') ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($row['aluno_ra'] ?? '') ?></td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['prova_titulo'] ?? '') ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars($row['materia_nome'] ?? '') ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?php $qtdRespostas = (int)($row['respostas_count'] ?? 0); ?>
                        <?= $qtdRespostas > 0 ? $qtdRespostas . ' salva(s)' : '—' ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex flex-wrap gap-2">
                        <?php if ($qtdRespostas > 0): ?>
                        <form method="post" action="<?= URL ?>/admin/provas/validar-tentativa/<?= (int)$row['prova_id'] ?>/<?= (int)$row['aluno_id'] ?>" class="inline form-validar-nota">
                            <input type="hidden" name="return_url" value="/admin/provas/blocos/<?= (int)$bloco['id'] ?>/canceladas">
                            <input type="hidden" name="senha_coordenador" value="">
                            <button type="button"
                                    class="btn-validar-nota bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-sm font-medium cursor-pointer"
                                    data-aluno-nome="<?= htmlspecialchars($row['aluno_nome'] ?? '', ENT_QUOTES) ?>">
                                Validar nota
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="post" action="<?= URL ?>/admin/provas/liberar-tentativa/<?= (int)$row['prova_id'] ?>/<?= (int)$row['aluno_id'] ?>" class="inline" onsubmit="return confirm('Liberar nova tentativa para <?= htmlspecialchars(addslashes($row['aluno_nome'] ?? '')) ?>? As respostas atuais serão apagadas e o aluno precisará refazer a prova.');">
                            <input type="hidden" name="return_url" value="/admin/provas/blocos/<?= (int)$bloco['id'] ?>/canceladas">
                            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-1.5 rounded text-sm font-medium cursor-pointer">
                                Liberar nova tentativa
                            </button>
                        </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="text-gray-500">Nenhuma prova cancelada neste bloco. Quando um aluno sair do modo seguro sem finalizar, a prova aparecerá aqui para você liberar.</p>
    <?php endif; ?>
</div>

<!-- Histórico de validações de nota -->
<div class="bg-white rounded-xl shadow-lg p-6 mt-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-1">Histórico de validações de nota</h3>
    <p class="text-sm text-gray-500 mb-4">Registro de todas as notas validadas neste bloco: aluno, nota, quem validou e quando.</p>
    <?php if (!empty($historico_validacoes)): ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">RA</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prova / Matéria</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nota</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Validado por</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($historico_validacoes as $h): ?>
                <tr>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($h['aluno_nome'] ?? '') ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($h['aluno_ra'] ?? '') ?></td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($h['prova_titulo'] ?? '') ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars($h['materia_nome'] ?? '') ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                        <?= $h['nota'] !== null ? number_format((float)$h['nota'], 2, ',', '.') : '—' ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900"><?= htmlspecialchars($h['validado_por_nome'] ?? '') ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars(ucfirst((string)($h['validado_por_tipo'] ?? ''))) ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?= !empty($h['created_at']) ? date('d/m/Y H:i', strtotime($h['created_at'])) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="text-gray-500">Nenhuma validação de nota registrada neste bloco ainda.</p>
    <?php endif; ?>
</div>

<!-- Modal: senha do coordenador para validar nota -->
<div id="modal-senha-validar" class="fixed inset-0 z-[10000] hidden items-center justify-center bg-black/60 p-4" style="display: none;">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
        <h4 class="text-lg font-semibold text-gray-900 mb-1">Confirmar validação de nota</h4>
        <p class="text-sm text-gray-600 mb-4">
            Você está validando a nota de <strong id="modal-senha-aluno-nome"></strong> com as respostas já salvas.
            A prova será marcada como finalizada. Digite sua senha para confirmar.
        </p>
        <label class="block text-sm font-medium text-gray-700 mb-1" for="modal-senha-input">Senha do coordenador</label>
        <input type="password" id="modal-senha-input" autocomplete="current-password"
               class="w-full rounded-lg border-gray-300 focus:ring-green-500 focus:border-green-500 mb-1" placeholder="Sua senha">
        <p id="modal-senha-erro" class="text-xs text-red-600 mb-3 hidden">Informe a senha para confirmar.</p>
        <div class="flex gap-3 mt-3">
            <button type="button" id="modal-senha-cancelar" class="flex-1 py-2.5 rounded-lg border border-gray-300 text-gray-700 font-medium hover:bg-gray-50">
                Cancelar
            </button>
            <button type="button" id="modal-senha-confirmar" class="btn-primary-custom flex-1 py-2.5 rounded-lg font-semibold hover:opacity-90">
                Confirmar e validar
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    var formAtual = null;
    var modal = document.getElementById('modal-senha-validar');
    var inputSenha = document.getElementById('modal-senha-input');
    var erro = document.getElementById('modal-senha-erro');

    function abrirModal(form, alunoNome) {
        formAtual = form;
        document.getElementById('modal-senha-aluno-nome').textContent = alunoNome || 'este aluno';
        inputSenha.value = '';
        erro.classList.add('hidden');
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        setTimeout(function() { inputSenha.focus(); }, 50);
    }

    function fecharModal() {
        modal.style.display = 'none';
        modal.classList.add('hidden');
        formAtual = null;
    }

    document.querySelectorAll('.btn-validar-nota').forEach(function(btn) {
        btn.addEventListener('click', function() {
            abrirModal(btn.closest('form'), btn.getAttribute('data-aluno-nome'));
        });
    });

    document.getElementById('modal-senha-cancelar').addEventListener('click', fecharModal);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) fecharModal();
    });

    function confirmar() {
        var senha = inputSenha.value;
        if (!senha) {
            erro.classList.remove('hidden');
            inputSenha.focus();
            return;
        }
        if (formAtual) {
            formAtual.querySelector('input[name="senha_coordenador"]').value = senha;
            formAtual.submit();
        }
        fecharModal();
    }

    document.getElementById('modal-senha-confirmar').addEventListener('click', confirmar);
    inputSenha.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            confirmar();
        }
    });
})();
</script>
