<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <?php include __DIR__ . '/../layouts/components/estilos_plataforma.php'; ?>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8">
        <!-- Header -->
        <div class="text-center">
            <div class="w-16 h-16 bg-primary-600 rounded-lg flex items-center justify-center mx-auto mb-4">
                <span class="text-white font-bold text-2xl">E</span>
            </div>
            <h2 class="text-3xl font-bold text-gray-900">EducaTudo</h2>
            <p class="mt-2 text-sm text-gray-600">Plataforma Educacional Single-Tenant</p>
        </div>

        <!-- Login Form -->
        <div class="bg-white py-8 px-6 shadow-lg rounded-lg">
            <form id="loginForm" class="space-y-6">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                
                <!-- Tipo de Usuário -->
                <div>
                    <label for="tipo" class="block text-sm font-medium text-gray-700 mb-2">
                        Tipo de Usuário
                    </label>
                    <select id="tipo" name="tipo" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Selecione o tipo</option>
                        <option value="admin_escola">Administrador da Escola</option>
                        <option value="professor">Professor</option>
                        <option value="aluno">Aluno</option>
                        <option value="pai">Pai/Responsável</option>
                    </select>
                </div>

                <!-- Login Field -->
                <div>
                    <label for="login" class="block text-sm font-medium text-gray-700 mb-2">
                        <span id="loginLabel">Email</span>
                    </label>
                    <input id="login" name="login" type="text" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           placeholder="Digite seu email">
                </div>

                <!-- Senha -->
                <div>
                    <label for="senha" class="block text-sm font-medium text-gray-700 mb-2">
                        Senha
                    </label>
                    <input id="senha" name="senha" type="password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           placeholder="Digite sua senha">
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="submit" 
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                        Entrar
                    </button>
                </div>

                <!-- Error Message -->
                <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                </div>
            </form>

            <!-- Links -->
            <div class="mt-6 text-center">
                <a href="<?= URL ?>/recuperar-senha" class="text-sm text-primary-600 hover:text-primary-500">
                    Esqueceu sua senha?
                </a>
            </div>
        </div>

        <!-- Footer -->
        <footer class="text-center py-4 border-t border-gray-200 mt-6">
            <p class="text-sm text-gray-900 mb-1">Todos os direitos reservados Educatudo</p>
            <p class="text-sm text-gray-900">
                Feito com carinho por <a href="https://www.launs.com.br" target="_blank" rel="noopener noreferrer" class="text-gray-900 hover:text-gray-700 underline">Launs</a>
            </p>
            <p class="text-sm text-gray-900 mt-2">
                <a href="<?= URL ?>/termos-de-uso" class="hover:underline">Termos de Uso</a> •
                <a href="<?= URL ?>/politica-privacidade" class="hover:underline">Política de Privacidade</a> •
                <a href="<?= URL ?>/politica-retencao" class="hover:underline">Política de Retenção de Dados</a>
            </p>
        </footer>
    </div>

    <script>
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

        function updateConsentModal(tipo) {
            const title = document.getElementById('consent-title');
            const body = document.getElementById('consent-body');
            const label = document.getElementById('consent-checkbox-label');
            const text = consentTexts[tipo];
            if (!text) {
                return;
            }
            title.textContent = text.title;
            body.innerHTML = text.body;
            label.textContent = text.checkbox;
        }

        // Atualiza label baseado no tipo de usuário
        document.getElementById('tipo').addEventListener('change', function() {
            const tipo = this.value;
            const loginLabel = document.getElementById('loginLabel');
            const loginInput = document.getElementById('login');
            
            switch(tipo) {
                case 'aluno':
                    loginLabel.textContent = 'RA (Registro Acadêmico)';
                    loginInput.placeholder = 'Digite seu RA';
                    break;
                case 'professor':
                    loginLabel.textContent = 'Código do Professor';
                    loginInput.placeholder = 'Digite seu código';
                    break;
                default:
                    loginLabel.textContent = 'Email';
                    loginInput.placeholder = 'Digite seu email';
            }
            updateConsentModal(tipo);
        });

        updateConsentModal(document.getElementById('tipo').value);

        // Submit do formulário
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const errorDiv = document.getElementById('errorMessage');
            
            try {
                const response = await fetch('<?= URL ?>/login', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                
                const result = await response.json();
                
                if (response.ok) {
                    // Redireciona para dashboard
                    window.location.href = '<?= URL ?>';
                } else {
                    if (result && (result.need_consent || result.error === 'Aceite obrigatório')) {
                        window.location.href = result.redirect || '<?= URL ?>';
                        return;
                    }
                    errorDiv.textContent = result.error || 'Erro no login';
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
