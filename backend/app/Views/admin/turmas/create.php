<?php
$csrf_token = $csrf_token ?? '';
$series = $series ?? [];
$cursos = $cursos ?? [];
$cursosNovo = $cursosNovo ?? [];
$seriesPorCurso = $seriesPorCurso ?? [];
$ano_letivo_id = $ano_letivo_id ?? null;

$page_header_back_url = URL . '/admin/turmas';
$page_header_title = 'Cadastrar Turma';
$page_header_subtitle = 'Preencha os dados da nova turma e vincule ao curso/série.';
include __DIR__ . '/../_partials/page_header_form.php';
?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 w-full">
    <form id="turmaForm" class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="p-6 space-y-6">
            <h3 class="text-lg font-semibold text-gray-900">Dados da turma</h3>
            <?php
            $turma_form_item = null;
            include __DIR__ . '/_turma_form_fields.php';
            ?>

            <div id="errorMessage" class="hidden p-4 rounded-lg bg-red-100 text-red-800 border border-red-200 text-sm"></div>
            <div id="successMessage" class="hidden p-4 rounded-lg bg-green-100 text-green-800 border border-green-200 text-sm"></div>
        </div>

        <div class="p-6 bg-gray-50/50 border-t border-gray-200">
            <div class="flex justify-end gap-3 flex-wrap">
                <a href="<?= URL ?>/admin/turmas"
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancelar
                </a>
                <button type="submit"
                        class="btn-primary-custom px-6 py-2 rounded-lg font-medium transition-colors shadow-sm hover:opacity-90">
                    Cadastrar Turma
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('turmaForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    var formData = new FormData(this);
    var errorDiv = document.getElementById('errorMessage');
    var successDiv = document.getElementById('successMessage');
    errorDiv.classList.add('hidden');
    successDiv.classList.add('hidden');
    try {
        var response = await fetch('<?= URL ?>/admin/turmas', { method: 'POST', body: formData });
        var result = await response.json();
        if (response.ok) {
            successDiv.textContent = result.message || 'Turma cadastrada com sucesso.';
            successDiv.classList.remove('hidden');
            setTimeout(function () { window.location.href = '<?= URL ?>/admin/turmas'; }, 1500);
        } else {
            errorDiv.textContent = result.error || 'Erro ao cadastrar turma';
            errorDiv.classList.remove('hidden');
        }
    } catch (error) {
        errorDiv.textContent = 'Erro de conexão. Tente novamente.';
        errorDiv.classList.remove('hidden');
    }
});
</script>
