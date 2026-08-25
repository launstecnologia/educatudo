<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Política de Privacidade - EducaTudo') ?></title>
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
                        <h1 class="text-2xl font-bold">Política de Privacidade — EducaTudo</h1>
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
            <p><strong>Resumo:</strong> A EducaTudo trata dados pessoais com segurança, transparência e conformidade com a LGPD.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">1. Compromisso com a Privacidade</h2>
            <p>A EducaTudo realiza o tratamento de dados pessoais em conformidade com a Lei nº 13.709/2018 – LGPD, adotando medidas técnicas, administrativas e organizacionais adequadas à proteção dos dados pessoais.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">2. Dados Pessoais Tratados</h2>
            <ul class="list-disc pl-6 space-y-1">
                <li>Dados cadastrais;</li>
                <li>Dados educacionais e acadêmicos;</li>
                <li>Registros de acesso e uso;</li>
                <li>Comunicações educacionais;</li>
                <li>Dados de desempenho e comportamento educacional;</li>
                <li>Análises pedagógicas automatizadas;</li>
                <li>Logs de segurança e auditoria.</li>
            </ul>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">3. Dados de Crianças e Adolescentes</h2>
            <p>O tratamento de dados de menores de 18 anos ocorre exclusivamente para fins educacionais, mediante consentimento do responsável legal, com adoção de medidas reforçadas de segurança e governança.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">4. Monitoramento e Uso de Inteligência Artificial</h2>
            <p>A plataforma utiliza inteligência artificial para apoio ao aprendizado, análise de padrões educacionais, monitoramento de interações, detecção preventiva de situações de risco e produção de análises pedagógicas.</p>
            <p>Sempre que possível, os dados são utilizados de forma anonimizada ou agregada.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">5. Retenção e Anonimização de Dados</h2>
            <p>Os dados pessoais são mantidos durante o vínculo educacional e após o encerramento pelo período necessário para fins pedagógicos, legais, de auditoria e proteção da vida, com posterior anonimização.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">6. Direitos do Titular</h2>
            <p>O titular dos dados ou seu responsável legal poderá solicitar confirmação da existência de tratamento, acesso aos dados, correção de dados incompletos ou desatualizados e informações sobre o tratamento realizado.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">7. Segurança da Informação</h2>
            <p>A EducaTudo adota medidas de segurança, controle de acesso, monitoramento contínuo e auditoria para proteção dos dados pessoais contra acessos não autorizados, vazamentos ou usos indevidos.</p>
        </section>

        <footer class="pt-6 border-t border-gray-200 text-sm text-gray-600 flex flex-wrap gap-3 justify-center">
            <a href="<?= URL ?>/termos-de-uso" class="hover:underline">Termos de Uso</a>
            <a href="<?= URL ?>/politica-privacidade" class="hover:underline">Política de Privacidade</a>
            <a href="<?= URL ?>/politica-retencao" class="hover:underline">Política de Retenção de Dados</a>
        </footer>
    </main>
</body>
</html>

