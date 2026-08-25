<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Política de Retenção de Dados - EducaTudo') ?></title>
    <?php include __DIR__ . '/../layouts/components/estilos_plataforma.php'; ?>
    <?php require_once __DIR__ . '/../../Core/LayoutHelper.php'; ?>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 text-gray-900">
    <main class="max-w-4xl mx-auto px-6 py-10 space-y-6">
        <header class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <?php if (LayoutHelper::getContextualLogo('login', 'h-10 w-auto', 'Logo EducaTudo')): ?>
                        <?= LayoutHelper::getContextualLogo('login', 'h-10 w-auto', 'Logo EducaTudo') ?>
                    <?php else: ?>
                        <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold">E</div>
                    <?php endif; ?>
                    <div>
                        <h1 class="text-2xl font-bold">Política de Retenção de Dados — EducaTudo</h1>
                        <p class="text-sm text-gray-600">Versão: 1.0</p>
                    </div>
                </div>
                <div class="text-xs text-gray-600 text-right">
                    <div>Última atualização: 27/01/2026</div>
                    <div>Vigência: a partir de 27/01/2026</div>
                </div>
            </div>
        </header>

        <section class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
            <p><strong>Resumo:</strong> A EducaTudo mantém dados pelo tempo necessário às finalidades legais, pedagógicas e de segurança, com posterior anonimização.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">1. Princípios</h2>
            <p>A EducaTudo mantém dados pessoais e educacionais apenas pelo tempo necessário ao cumprimento de suas finalidades legais, pedagógicas e institucionais.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">2. Prazos de Retenção</h2>
            <ul class="list-disc pl-6 space-y-1">
                <li>Chats educacionais: até 5 anos após o encerramento do vínculo educacional, com posterior anonimização;</li>
                <li>Dados acadêmicos (notas, atividades, ocorrências): até 5 anos após a saída do aluno;</li>
                <li>Análises da Tudinha e alertas sensíveis: até 20 anos, com posterior anonimização;</li>
                <li>Logs de auditoria e segurança: mínimo de 5 anos.</li>
            </ul>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">3. Alertas Sensíveis</h2>
            <p>A plataforma poderá gerar alertas sensíveis relacionados à integridade física, emocional ou psicológica dos usuários.</p>
            <ul class="list-disc pl-6 space-y-1">
                <li>São tratados com confidencialidade reforçada;</li>
                <li>São acessados apenas por usuários autorizados;</li>
                <li>São mantidos pelo período necessário à proteção da vida e às obrigações legais;</li>
                <li>Poderão ser anonimizados posteriormente.</li>
            </ul>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">4. Anonimização</h2>
            <p>A anonimização é realizada de forma a impedir a reidentificação do titular, preservando apenas informações estatísticas e históricas de interesse educacional.</p>
        </section>

        <footer class="pt-6 border-t border-gray-200 text-sm text-gray-600 flex flex-wrap gap-3 justify-center">
            <a href="<?= URL ?>/termos-de-uso" class="hover:underline">Termos de Uso</a>
            <a href="<?= URL ?>/politica-privacidade" class="hover:underline">Política de Privacidade</a>
            <a href="<?= URL ?>/politica-retencao" class="hover:underline">Política de Retenção de Dados</a>
        </footer>
    </main>
</body>
</html>

