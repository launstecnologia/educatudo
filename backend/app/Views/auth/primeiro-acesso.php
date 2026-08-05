<?php
require_once __DIR__ . '/../../Core/LayoutHelper.php';
$logo_horizontal_url = LayoutHelper::getLogoHorizontalUrl();
$system_title = LayoutHelper::getSystemTitle();
$turma_obrigatoria = LayoutHelper::isPrimeiroAcessoTurmaObrigatoria();
$error = $error ?? '';
$matches = $matches ?? [];
$turma_id_preselect = (int)($turma_id_preselect ?? 0);
$aluno_nome_preselect = trim((string)($aluno_nome_preselect ?? ''));
$tem_matches = count($matches) > 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Primeiro acesso - <?= htmlspecialchars($system_title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-6">
    <div class="w-full max-w-xl bg-white rounded-xl shadow-lg p-8">
        <div class="text-center mb-6">
            <?php if (!empty($logo_horizontal_url)): ?>
                <img src="<?= htmlspecialchars($logo_horizontal_url) ?>" alt="Logo" class="h-12 mx-auto mb-3">
            <?php endif; ?>
            <h1 class="text-2xl font-bold text-gray-900">Primeiro acesso ao EducaTudo</h1>
            <p class="text-gray-600"><?= $turma_obrigatoria ? 'Selecione sua turma e seu nome' : 'Digite seu nome completo' ?></p>
        </div>

        <form action="<?= rtrim(URL, '/') ?>/primeiro-acesso" method="post" class="space-y-5">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <?php if ($turma_obrigatoria): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Turma</label>
                <select name="turma_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                    <option value="">Selecione a turma</option>
                    <?php foreach ($turmas as $turma): ?>
                        <?php
                        $tipoEnsino = trim((string)($turma['tipo_ensino'] ?? ''));
                        $tipoLabel = '';
                        if ($tipoEnsino !== '') {
                            $tipoLower = mb_strtolower($tipoEnsino);
                            if (strpos($tipoLower, 'medio') !== false) {
                                $tipoLabel = 'Ensino Médio';
                            } elseif (strpos($tipoLower, 'fundamental') !== false) {
                                $tipoLabel = 'Ensino Fundamental';
                            } else {
                                $tipoLabel = ucwords($tipoEnsino);
                            }
                        }
                        $label = $tipoLabel ? ($tipoLabel . ' - ' . $turma['nome']) : $turma['nome'];
                        $sel = ((int)$turma['id'] === $turma_id_preselect) ? ' selected' : '';
                        ?>
                        <option value="<?= (int)$turma['id'] ?>"<?= $sel ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <input type="hidden" name="turma_id" value="0">
            <?php endif; ?>

            <?php if ($tem_matches): ?>
                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                    <p class="text-sm text-gray-600 mb-3">Encontramos mais de um aluno com esse nome. Selecione o correto:</p>
                    <div class="space-y-2">
                        <?php foreach ($matches as $m): ?>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="aluno_id" value="<?= (int)$m['id'] ?>" required>
                                <span><?= htmlspecialchars($m['nome']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <input type="hidden" name="aluno_nome" value="<?= htmlspecialchars($aluno_nome_preselect) ?>">
            <?php else: ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nome completo do aluno</label>
                    <input name="aluno_nome" type="text" value="<?= htmlspecialchars($aluno_nome_preselect) ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg" placeholder="Digite seu nome completo">
                </div>
                <input type="hidden" name="aluno_id" value="">
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg">
                Continuar
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="<?= URL ?>/" class="text-sm text-blue-600 hover:text-blue-500">Voltar para login</a>
        </div>
    </div>
</body>
</html>
