<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Termos de Uso - EducaTudo') ?></title>
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
                        <h1 class="text-2xl font-bold">Termos de Uso — Plataforma EducaTudo</h1>
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
            <p><strong>Resumo:</strong> A EducaTudo é uma plataforma educacional que utiliza tecnologia, inteligência artificial e monitoramento responsável para apoiar o aprendizado, garantir segurança e promover um ambiente educacional saudável, sempre em conformidade com a LGPD.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">1. Objeto</h2>
            <p>Os presentes Termos de Uso regulam o acesso e a utilização da plataforma educacional EducaTudo, disponibilizada às instituições de ensino privadas e seus usuários autorizados, incluindo alunos, responsáveis legais, professores, coordenadores, administradores e demais colaboradores.</p>
            <p>A plataforma tem finalidade exclusivamente educacional e institucional, sendo utilizada como ambiente digital de apoio ao ensino, aprendizagem, acompanhamento acadêmico, comunicação educacional, avaliações e uso de tecnologias de inteligência artificial para fins pedagógicos e de segurança.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">2. Aceitação dos Termos</h2>
            <p>Ao acessar ou utilizar a plataforma EducaTudo, o usuário declara que leu, compreendeu e concorda integralmente com estes Termos de Uso, bem como com a Política de Privacidade e a Política de Retenção de Dados.</p>
            <p>No caso de usuários menores de 18 (dezoito) anos, o acesso à plataforma depende de consentimento expresso do responsável legal, sendo o aceite do aluno considerado apenas como ciência.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">3. Elegibilidade e Acesso</h2>
            <p>3.1. O acesso à plataforma é restrito a usuários devidamente cadastrados e autorizados pela instituição de ensino.</p>
            <p>3.2. Cada usuário é responsável por manter a confidencialidade de suas credenciais de acesso, não sendo permitido o compartilhamento de login e senha.</p>
            <p>3.3. O uso indevido da plataforma poderá resultar em suspensão ou encerramento do acesso.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">4. Registro da Vida Acadêmica do Aluno</h2>
            <p>A plataforma EducaTudo realiza o registro, armazenamento e tratamento de informações relacionadas à vida acadêmica do aluno, incluindo, mas não se limitando a:</p>
            <ul class="list-disc pl-6 space-y-1">
                <li>Atividades educacionais realizadas;</li>
                <li>Exercícios, provas e avaliações;</li>
                <li>Notas, conceitos e desempenho acadêmico;</li>
                <li>Ocorrências e registros pedagógicos;</li>
                <li>Relatórios educacionais e históricos acadêmicos;</li>
                <li>Interações educacionais realizadas na plataforma.</li>
            </ul>
            <p>Esses dados são tratados exclusivamente para fins educacionais, acompanhamento pedagógico, cumprimento de obrigações institucionais e melhoria do processo de ensino-aprendizagem.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">5. Uso de Inteligência Artificial e Monitoramento</h2>
            <p>5.1. A plataforma utiliza tecnologias de inteligência artificial como ferramenta de apoio ao processo educacional.</p>
            <p>5.2. O usuário declara estar ciente de que suas interações, atividades, mensagens e desempenho podem ser monitorados, analisados e processados, inclusive por mecanismos automatizados.</p>
            <p>5.3. As análises automatizadas não substituem a avaliação humana, servindo como apoio pedagógico e preventivo.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">6. Comunicações e Chat Educacional</h2>
            <p>As comunicações realizadas por meio da plataforma possuem caráter educacional e institucional.</p>
            <p>As mensagens poderão ser registradas, monitoradas e analisadas para fins de garantia de um ambiente seguro, prevenção de riscos, cumprimento de obrigações legais e apoio pedagógico.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">7. Responsabilidades dos Usuários</h2>
            <ul class="list-disc pl-6 space-y-1">
                <li>Utilizar a plataforma de forma ética, respeitosa e compatível com sua finalidade educacional;</li>
                <li>Não praticar atos ilícitos, ofensivos, discriminatórios ou que violem direitos de terceiros;</li>
                <li>Respeitar as normas institucionais da escola contratante;</li>
                <li>Utilizar os dados acessados apenas dentro de suas atribuições.</li>
            </ul>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">8. Suspensão e Encerramento de Acesso</h2>
            <p>O acesso à plataforma poderá ser suspenso ou encerrado em caso de descumprimento destes Termos ou das normas institucionais, sem prejuízo da manutenção de registros necessários ao cumprimento legal e institucional.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">9. Foro</h2>
            <p>Fica eleito o foro da comarca da sede da instituição de ensino contratante para dirimir quaisquer controvérsias oriundas destes Termos.</p>
        </section>

        <footer class="pt-6 border-t border-gray-200 text-sm text-gray-600 flex flex-wrap gap-3 justify-center">
            <a href="<?= URL ?>/termos-de-uso" class="hover:underline">Termos de Uso</a>
            <a href="<?= URL ?>/politica-privacidade" class="hover:underline">Política de Privacidade</a>
            <a href="<?= URL ?>/politica-retencao" class="hover:underline">Política de Retenção de Dados</a>
        </footer>
    </main>
</body>
</html>

