<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-2xl w-full bg-white shadow-lg rounded-lg p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-4">Aceite obrigatório</h1>
        <p class="text-sm text-gray-600 mb-6">Para continuar, é necessário registrar seu aceite.</p>

        <form id="consentForm" class="space-y-6">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="consent_accept" value="1">

            <div id="consent-block" class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-xs text-blue-900 space-y-2">
                <div class="font-semibold" id="consent-title"></div>
                <div id="consent-body"></div>
                <label class="flex items-start gap-2 mt-3">
                    <input type="checkbox" name="consent_accept_checkbox" required class="mt-1">
                    <span id="consent-checkbox-label"></span>
                </label>
            </div>

            <div>
                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200">
                    Confirmar aceite
                </button>
            </div>

            <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"></div>
        </form>

        <footer class="mt-8 pt-4 border-t border-gray-200 text-center text-sm text-gray-600">
            <a href="<?= URL ?>/termos-de-uso" class="hover:underline">Termos de Uso</a> •
            <a href="<?= URL ?>/politica-privacidade" class="hover:underline">Política de Privacidade</a> •
            <a href="<?= URL ?>/politica-retencao" class="hover:underline">Política de Retenção de Dados</a>
        </footer>
    </div>

    <script>
        const userType = "<?= htmlspecialchars($user_type ?? '') ?>";
        const consentTexts = {
            aluno: {
                title: 'Declaração de Ciência do Aluno',
                body: `
                    <p>Declaro que estou ciente de que a plataforma EducaTudo é um ambiente educacional digital utilizado pela minha instituição de ensino para apoio ao aprendizado, realização de atividades, avaliações, acompanhamento pedagógico e comunicação educacional.</p>
                    <p><strong>A plataforma é monitorada por inteligência artificial (IA) para detectar assuntos sensíveis e situações de risco, visando a segurança e o bem-estar de todos.</strong></p>
                    <p>Estou ciente de que:</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Minhas atividades, avaliações, notas, desempenho acadêmico e registros pedagógicos fazem parte da minha vida acadêmica dentro da plataforma;</li>
                        <li>Minhas interações, mensagens e uso do sistema podem ser monitorados e analisados, inclusive por sistemas de inteligência artificial, para fins educacionais, de segurança, prevenção de riscos e melhoria do aprendizado;</li>
                        <li>Esses tratamentos ocorrem conforme autorização do meu responsável legal e de acordo com os Termos de Uso, Política de Privacidade e Política de Retenção de Dados da EducaTudo.</li>
                    </ul>
                    <p>Declaro que li e estou ciente do conteúdo desses documentos, em sua versão vigente.</p>
                `,
                checkbox: 'Li e estou ciente dos Termos de Uso, da Política de Privacidade e da Política de Retenção de Dados.'
            },
            pai: {
                title: 'Consentimento do Responsável Legal',
                body: `
                    <p>Declaro que li, compreendi e ACEITO EXPRESSAMENTE os Termos de Uso, a Política de Privacidade e a Política de Retenção de Dados da plataforma EducaTudo, autorizando o tratamento dos dados pessoais e educacionais do aluno sob minha responsabilidade.</p>
                    <p><strong>A plataforma é monitorada por inteligência artificial (IA) para detectar assuntos sensíveis e situações de risco, visando a segurança e o bem-estar de todos.</strong></p>
                    <p>Estou ciente e autorizo que a plataforma:</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Registre, armazene e trate informações relativas à vida acadêmica do aluno, incluindo atividades, avaliações, notas, ocorrências pedagógicas, relatórios educacionais e histórico acadêmico;</li>
                        <li>Utilize tecnologias de inteligência artificial para apoio pedagógico, análise de desempenho, monitoramento de interações e prevenção de situações de risco;</li>
                        <li>Realize monitoramento automatizado e supervisionado das mensagens, atividades e desempenho do aluno;</li>
                        <li>Mantenha registros educacionais, de segurança e de auditoria pelo período necessário, com posterior anonimização quando aplicável.</li>
                    </ul>
                    <p>Declaro, ainda, estar ciente dos direitos do titular de dados, conforme a LGPD.</p>
                `,
                checkbox: 'Li e ACEITO os Termos de Uso, a Política de Privacidade e a Política de Retenção de Dados.'
            },
            professor: {
                title: 'Termo de Aceite do Usuário Institucional',
                body: `
                    <p>Declaro que li, compreendi e ACEITO os Termos de Uso e a Política de Privacidade da plataforma EducaTudo, comprometendo-me a utilizar o sistema exclusivamente para fins educacionais, institucionais e administrativos.</p>
                    <p><strong>A plataforma é monitorada por inteligência artificial (IA) para detectar assuntos sensíveis e situações de risco, visando a segurança e o bem-estar de todos.</strong></p>
                    <p>Estou ciente de que:</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Minhas ações na plataforma podem ser monitoradas, registradas e auditadas;</li>
                        <li>O acesso a dados acadêmicos e pessoais deve ocorrer estritamente dentro das atribuições do meu perfil;</li>
                        <li>O uso inadequado do sistema poderá resultar em medidas administrativas e legais.</li>
                    </ul>
                `,
                checkbox: 'Li e ACEITO os Termos de Uso e a Política de Privacidade.'
            },
            admin: {
                title: 'Termo de Aceite do Usuário Institucional',
                body: `
                    <p>Declaro que li, compreendi e ACEITO os Termos de Uso e a Política de Privacidade da plataforma EducaTudo, comprometendo-me a utilizar o sistema exclusivamente para fins educacionais, institucionais e administrativos.</p>
                    <p><strong>A plataforma é monitorada por inteligência artificial (IA) para detectar assuntos sensíveis e situações de risco, visando a segurança e o bem-estar de todos.</strong></p>
                    <p>Estou ciente de que:</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Minhas ações na plataforma podem ser monitoradas, registradas e auditadas;</li>
                        <li>O acesso a dados acadêmicos e pessoais deve ocorrer estritamente dentro das atribuições do meu perfil;</li>
                        <li>O uso inadequado do sistema poderá resultar em medidas administrativas e legais.</li>
                    </ul>
                `,
                checkbox: 'Li e ACEITO os Termos de Uso e a Política de Privacidade.'
            },
            admin_escola: {
                title: 'Termo de Aceite do Usuário Institucional',
                body: `
                    <p>Declaro que li, compreendi e ACEITO os Termos de Uso e a Política de Privacidade da plataforma EducaTudo, comprometendo-me a utilizar o sistema exclusivamente para fins educacionais, institucionais e administrativos.</p>
                    <p><strong>A plataforma é monitorada por inteligência artificial (IA) para detectar assuntos sensíveis e situações de risco, visando a segurança e o bem-estar de todos.</strong></p>
                    <p>Estou ciente de que:</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Minhas ações na plataforma podem ser monitoradas, registradas e auditadas;</li>
                        <li>O acesso a dados acadêmicos e pessoais deve ocorrer estritamente dentro das atribuições do meu perfil;</li>
                        <li>O uso inadequado do sistema poderá resultar em medidas administrativas e legais.</li>
                    </ul>
                `,
                checkbox: 'Li e ACEITO os Termos de Uso e a Política de Privacidade.'
            }
        };

        function updateConsentBlock(tipo) {
            const title = document.getElementById('consent-title');
            const body = document.getElementById('consent-body');
            const label = document.getElementById('consent-checkbox-label');
            const text = consentTexts[tipo] || consentTexts.admin;
            title.textContent = text.title;
            body.innerHTML = text.body;
            label.textContent = text.checkbox;
        }

        updateConsentBlock(userType);

        document.getElementById('consentForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const errorDiv = document.getElementById('errorMessage');
            try {
                const response = await fetch('<?= URL ?>/consent/accept', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (response.ok) {
                    window.location.href = result.redirect || '<?= URL ?>/';
                } else {
                    errorDiv.textContent = result.error || 'Erro ao registrar aceite';
                    errorDiv.classList.remove('hidden');
                }
            } catch (error) {
                errorDiv.textContent = 'Erro de conexão. Tente novamente.';
                errorDiv.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>

