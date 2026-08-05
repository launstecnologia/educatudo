<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center mb-4">
        <a href="<?= URL ?>/admin/students" class="text-gray-500 hover:text-gray-700 mr-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Cadastrar Novo Aluno 👨‍🎓
            </h2>
            <p class="text-gray-600">
                Preencha os dados do aluno. A foto poderá ser enviada após o cadastro.
            </p>
        </div>
    </div>
</div>

<!-- Form -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <form id="studentForm" class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="p-6 space-y-6">
            <h3 class="text-lg font-semibold text-gray-900">Identificação</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="nome" class="block text-sm font-medium text-gray-700 mb-2">Nome Completo *</label>
                    <input type="text" id="nome" name="nome" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                           placeholder="Digite o nome completo">
                </div>
                <div>
                    <label for="codigo_aluno" class="block text-sm font-medium text-gray-700 mb-2">Código do Aluno</label>
                    <input type="text" id="codigo_aluno" name="codigo_aluno"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                           placeholder="Ex: 11442">
                    <input type="hidden" id="ra" name="ra">
                </div>
                <div>
                    <label for="sexo" class="block text-sm font-medium text-gray-700 mb-2">Sexo (lista de chamada)</label>
                    <select id="sexo" name="sexo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">Não informado</option>
                        <option value="F">Feminino</option>
                        <option value="M">Masculino</option>
                        <option value="N">Neutro / outro</option>
                    </select>
                </div>
                <?php if (!empty($units)): ?>
                <div>
                    <label for="unidade_id" class="block text-sm font-medium text-gray-700 mb-2">Unidade</label>
                    <select id="unidade_id" name="unidade_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">Não informada</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= (int) $unit['id'] ?>"><?= htmlspecialchars($unit['nome'] ?? '') ?> (<?= ($unit['tipo'] ?? '') === 'matriz' ? 'Matriz' : 'Filial' ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <?php include __DIR__ . '/_student_documento_endereco_fields.php'; ?>
                <?php include __DIR__ . '/_student_identificacao_civil_fields.php'; ?>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <h3 class="text-lg font-semibold text-gray-900">Contato</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php include __DIR__ . '/_student_contato_fields.php'; ?>
            </div>
        </div>

        <?php include __DIR__ . '/_student_ficha_complementar_fields.php'; ?>

        <div class="p-6 space-y-6">
            <h3 class="text-lg font-semibold text-gray-900">Acesso ao sistema</h3>
            <p class="text-sm text-gray-500">O aluno entra na plataforma com <strong>nickname</strong> e <strong>senha</strong>.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="nickname" class="block text-sm font-medium text-gray-700 mb-2">Nickname (login do aluno)</label>
                    <input type="text" id="nickname" name="nickname" autocomplete="username"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                           placeholder="Ex: joao.silva">
                </div>
                <div>
                    <label for="senha" class="block text-sm font-medium text-gray-700 mb-2">Senha</label>
                    <input type="password" id="senha" name="senha" autocomplete="new-password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                           placeholder="Senha para login">
                </div>
            </div>
            <p class="text-xs text-gray-500">A matrícula em turma é feita depois, em Ações Rápidas na ficha do aluno. Sem matrícula, o status ficará pendente.</p>
        </div>

        <div class="p-6 space-y-3">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Status</h3>
            <div>
                <label class="flex items-center">
                    <input type="checkbox" name="ativo" value="1" checked
                           class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-700">Aluno ativo</span>
                </label>
            </div>
            <div>
                <label class="flex items-center">
                    <input type="checkbox" name="pagante" value="1" checked
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-700">Aluno pagante</span>
                </label>
                <p class="ml-6 text-xs text-gray-500 mt-1">Indica se a escola paga por este aluno</p>
            </div>
        </div>

        <div class="p-6">
            <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"></div>
            <div id="successMessage" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"></div>
            <div class="flex justify-end space-x-4">
                <a href="<?= URL ?>/admin/students"
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancelar
                </a>
                <button type="submit"
                        class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">
                    Cadastrar Aluno
                </button>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/_student_form_masks.php'; ?>

<script>
document.getElementById('studentForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const codigo = (formData.get('codigo_aluno') || '').toString().trim();
    if (codigo !== '') {
        formData.set('ra', codigo);
    }
    if (typeof window.studentFormNormalizeDocumentoEndereco === 'function') {
        window.studentFormNormalizeDocumentoEndereco(formData);
    }

    const errorDiv = document.getElementById('errorMessage');
    const successDiv = document.getElementById('successMessage');

    errorDiv.classList.add('hidden');
    successDiv.classList.add('hidden');

    try {
        const response = await fetch('<?= URL ?>/admin/students', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (response.ok) {
            successDiv.textContent = 'Aluno cadastrado com sucesso!';
            successDiv.classList.remove('hidden');

            const alunoId = result.id;
            setTimeout(() => {
                if (alunoId) {
                    window.location.href = '<?= URL ?>/admin/students/' + alunoId + '/edit?foto=1';
                } else {
                    window.location.href = '<?= URL ?>/admin/students';
                }
            }, 1500);
        } else {
            errorDiv.textContent = result.error || 'Erro ao cadastrar aluno';
            errorDiv.classList.remove('hidden');
        }
    } catch (error) {
        errorDiv.textContent = 'Erro de conexão. Tente novamente.';
        errorDiv.classList.remove('hidden');
    }
});
</script>
