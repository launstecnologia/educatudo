<?php
$curso = $curso ?? null;
$turmas = $turmas ?? [];
$csrf_token = $csrf_token ?? '';
$status = (string) ($_GET['status'] ?? '');
$message = (string) ($_GET['message'] ?? '');
$turma_pre_selecionada = (int) ($turma_pre_selecionada ?? 0);
if (!$curso) {
    header('Location: ' . (defined('URL') ? URL : '') . '/admin/curso');
    exit;
}

$cursoExtra = (isset($curso['tipo']) && $curso['tipo'] === 'extra');
$inputClass = 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500';

$page_header_back_url = URL . '/admin/curso';
$page_header_title = 'Importar alunos';
$page_header_subtitle_html = 'Curso <strong>' . htmlspecialchars($curso['nome']) . '</strong> — envie um CSV para cadastrar ou vincular alunos.';
include \App\Core\ViewPaths::adminPartial('page_header_form.php');

$flash_status = $status;
$flash_message = $message;
include \App\Core\ViewPaths::adminPartial('flash_message.php');

$senhasImportacao = [];
if (!empty($_SESSION['senhas_importacao_alunos']) && is_array($_SESSION['senhas_importacao_alunos'])) {
    $senhasImportacao = $_SESSION['senhas_importacao_alunos'];
    unset($_SESSION['senhas_importacao_alunos']);
}
?>
<?php if ($senhasImportacao !== []): ?>
<div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200 w-full overflow-x-auto">
    <div class="p-6">
        <h3 class="text-lg font-semibold text-gray-900">Senhas temporárias</h3>
        <p class="text-sm text-gray-600 mt-1">Anote agora. Esta lista some ao recarregar a página.</p>
        <table class="mt-4 w-full text-sm text-left">
            <thead>
                <tr class="border-b border-gray-200 text-gray-500">
                    <th class="py-2 pr-4 font-medium">Nome</th>
                    <th class="py-2 pr-4 font-medium">RA</th>
                    <th class="py-2 font-medium">Senha</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($senhasImportacao as $item): ?>
                <tr class="border-b border-gray-100">
                    <td class="py-2 pr-4"><?= htmlspecialchars((string) ($item['nome'] ?? '')) ?></td>
                    <td class="py-2 pr-4"><?= htmlspecialchars((string) ($item['ra'] ?? '')) ?></td>
                    <td class="py-2 font-mono"><?= htmlspecialchars((string) ($item['senha'] ?? '')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 w-full">
    <form method="POST"
          action="<?= URL ?>/admin/curso/<?= (int) $curso['id'] ?>/importar-alunos/processar"
          enctype="multipart/form-data"
          class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="p-6 space-y-6">
            <h3 class="text-lg font-semibold text-gray-900">Arquivo e turma</h3>

            <p class="text-sm text-gray-600">
                Envie um arquivo <strong>CSV</strong> (exporte do Excel como "CSV UTF-8" ou "CSV").
                A primeira coluna deve ser o <strong>nome</strong> do aluno.
                <?php if ($cursoExtra): ?>
                Alunos já cadastrados recebem <strong>matrícula adicional</strong> nesta turma; a turma principal <strong>não será alterada</strong>.
                Alunos novos serão criados com esta turma como principal.
                <?php else: ?>
                Alunos não cadastrados serão criados e matriculados na turma selecionada.
                <?php endif; ?>
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Turma para matrícula <span class="text-red-500">*</span>
                    </label>
                    <select id="turma_id" name="turma_id" required class="<?= $inputClass ?>">
                        <option value="">Selecione a turma</option>
                        <?php foreach ($turmas as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= $turma_pre_selecionada === (int) $t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($turmas)): ?>
                    <p class="text-xs text-amber-700 mt-1">Nenhuma turma ativa vinculada a este curso. Cadastre em Turmas primeiro.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="arquivo" class="block text-sm font-medium text-gray-700 mb-2">
                        Arquivo CSV <span class="text-red-500">*</span>
                    </label>
                    <input type="file" id="arquivo" name="arquivo" accept=".csv" required
                           class="<?= $inputClass ?> file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                    <p class="text-xs text-gray-500 mt-1">Colunas: nome (obrigatório), email, data_nasc, série. A primeira linha pode ser cabeçalho.</p>
                </div>
            </div>

            <div>
                <a href="<?= URL ?>/admin/curso/<?= (int) $curso['id'] ?>/modelo-csv"
                   class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-800">
                    <i class="fa-solid fa-download"></i>
                    Baixar modelo CSV
                </a>
            </div>
        </div>

        <?php
        $form_cancel_url = URL . '/admin/curso';
        $form_cancel_label = 'Voltar aos cursos';
        $form_submit_label = 'Importar alunos';
        include \App\Core\ViewPaths::adminPartial('form_actions.php');
        ?>
    </form>
</div>
