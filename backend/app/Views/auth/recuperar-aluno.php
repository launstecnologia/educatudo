<?php
require_once __DIR__ . '/../../Core/LayoutHelper.php';
$logo_horizontal_url = LayoutHelper::getLoginLogoUrl();
$system_title = LayoutHelper::getSystemTitle();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha - <?= htmlspecialchars($system_title) ?></title>
    <?php include __DIR__ . '/../layouts/components/estilos_plataforma.php'; ?>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-6">
    <div class="w-full max-w-xl bg-white rounded-xl shadow-lg p-8">
        <div class="text-center mb-6">
            <?php if (!empty($logo_horizontal_url)): ?>
                <img src="<?= htmlspecialchars($logo_horizontal_url) ?>" alt="Logo" class="h-12 mx-auto mb-3">
            <?php endif; ?>
            <h1 class="text-2xl font-bold text-gray-900">Recuperar senha</h1>
            <p class="text-gray-600">Use seu nickname para recuperar</p>
        </div>

        <form id="recuperarForm" class="space-y-5">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nickname</label>
                <input name="nickname" type="text" required class="w-full px-4 py-3 border border-gray-300 rounded-lg">
            </div>

            <div id="perguntaBox" class="hidden">
                <p class="text-sm text-gray-600 mb-2">Pergunta de segurança</p>
                <p id="perguntaTexto" class="font-medium text-gray-900 mb-3"></p>
                <input type="text" name="resposta" id="resposta" autocomplete="off" class="w-full px-4 py-3 border border-gray-300 rounded-lg mb-4" placeholder="Digite sua resposta">
                <label class="block text-sm font-medium text-gray-700 mb-2">Nova senha</label>
                <div class="relative mb-2">
                    <input id="senha" name="senha" type="password" autocomplete="new-password" class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg">
                    <button type="button"
                            onclick="togglePassword('senha')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                        <svg id="senha-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        <svg id="senha-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                        </svg>
                    </button>
                </div>
                <div class="mb-3">
                    <div class="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
                        <div id="senhaStrengthBar" class="h-2 bg-red-500 w-0 transition-all duration-200"></div>
                    </div>
                    <p id="senhaStrengthText" class="text-xs text-gray-500 mt-1">Força da senha: fraca</p>
                    <ul class="mt-2 text-xs text-gray-500 space-y-1">
                        <li id="req-length">• Mínimo 6 caracteres</li>
                        <li id="req-upper">• 1 letra maiúscula</li>
                        <li id="req-lower">• 1 letra minúscula</li>
                        <li id="req-number">• 1 número</li>
                        <li id="req-special">• 1 símbolo</li>
                    </ul>
                </div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Confirmar nova senha</label>
                <div class="relative">
                    <input id="confirmar_senha" name="confirmar_senha" type="password" autocomplete="new-password" class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg">
                    <button type="button"
                            onclick="togglePassword('confirmar_senha')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                        <svg id="confirmar_senha-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        <svg id="confirmar_senha-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 0 01-4.132 5.411m0 0L21 21"></path>
                        </svg>
                    </button>
                </div>
                <div id="confirmarSenhaErro" class="hidden text-xs text-red-600 mt-2">Senhas não conferem</div>
            </div>

            <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"></div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg">
                Continuar
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="<?= URL ?>/" class="text-sm text-blue-600 hover:text-blue-500">Voltar para login</a>
        </div>
    </div>

    <script>
        const form = document.getElementById('recuperarForm');
        const perguntaBox = document.getElementById('perguntaBox');
        const perguntaTexto = document.getElementById('perguntaTexto');
        const errorDiv = document.getElementById('errorMessage');
        let etapaPergunta = false;

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const eye = document.getElementById(inputId + '-eye');
            const eyeOff = document.getElementById(inputId + '-eye-off');
            if (!input || !eye || !eyeOff) return;
            if (input.type === 'password') {
                input.type = 'text';
                eye.classList.add('hidden');
                eyeOff.classList.remove('hidden');
            } else {
                input.type = 'password';
                eye.classList.remove('hidden');
                eyeOff.classList.add('hidden');
            }
        }

        function updatePasswordStrength(value) {
            const bar = document.getElementById('senhaStrengthBar');
            const text = document.getElementById('senhaStrengthText');
            const reqLength = document.getElementById('req-length');
            const reqUpper = document.getElementById('req-upper');
            const reqLower = document.getElementById('req-lower');
            const reqNumber = document.getElementById('req-number');
            const reqSpecial = document.getElementById('req-special');

            const hasLength = value.length >= 6;
            const hasUpper = /[A-Z]/.test(value);
            const hasLower = /[a-z]/.test(value);
            const hasNumber = /[0-9]/.test(value);
            const hasSpecial = /[^A-Za-z0-9]/.test(value);

            reqLength.classList.toggle('text-green-600', hasLength);
            reqUpper.classList.toggle('text-green-600', hasUpper);
            reqLower.classList.toggle('text-green-600', hasLower);
            reqNumber.classList.toggle('text-green-600', hasNumber);
            reqSpecial.classList.toggle('text-green-600', hasSpecial);

            const score = [hasLength, hasUpper, hasLower, hasNumber, hasSpecial].filter(Boolean).length;
            let percent = (score / 5) * 100;
            bar.style.width = percent + '%';

            if (score <= 2) {
                bar.className = 'h-2 bg-red-500 w-0 transition-all duration-200';
                text.textContent = 'Força da senha: fraca';
            } else if (score === 3 || score === 4) {
                bar.className = 'h-2 bg-yellow-500 w-0 transition-all duration-200';
                text.textContent = 'Força da senha: média';
            } else {
                bar.className = 'h-2 bg-green-500 w-0 transition-all duration-200';
                text.textContent = 'Força da senha: forte';
            }
            bar.style.width = percent + '%';
        }

        function validarConfirmacaoSenha() {
            const inlineError = document.getElementById('confirmarSenhaErro');
            const senha = document.getElementById('senha')?.value || '';
            const confirmar = document.getElementById('confirmar_senha')?.value || '';
            if (confirmar !== '' && senha !== confirmar) {
                inlineError.classList.remove('hidden');
                return false;
            }
            inlineError.classList.add('hidden');
            return true;
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorDiv.classList.add('hidden');
            const formData = new FormData(form);
            if (etapaPergunta) {
                const senha = formData.get('senha') || '';
                const confirmar = formData.get('confirmar_senha') || '';
                const forte = senha.length >= 6 &&
                    /[A-Z]/.test(senha) &&
                    /[a-z]/.test(senha) &&
                    /[0-9]/.test(senha) &&
                    /[^A-Za-z0-9]/.test(senha);
                if (!forte) {
                    errorDiv.textContent = 'A senha deve ter no mínimo 6 caracteres, com letra maiúscula, minúscula, número e símbolo.';
                    errorDiv.classList.remove('hidden');
                    return;
                }
                if (senha !== confirmar) {
                    document.getElementById('confirmarSenhaErro').classList.remove('hidden');
                    return;
                }
            }
            const url = etapaPergunta ? '<?= URL ?>/aluno/recuperar-senha/reset' : '<?= URL ?>/aluno/recuperar-senha/pergunta';
            const resp = await fetch(url, { method: 'POST', body: formData });
            const data = await resp.json();
            if (!resp.ok) {
                errorDiv.textContent = data.error || 'Erro na recuperação';
                errorDiv.classList.remove('hidden');
                return;
            }
            if (!etapaPergunta) {
                // V-04: Backend retorna mensagem genérica ou pergunta. Nunca revela se nickname existe.
                if (data.pergunta) {
                    perguntaTexto.textContent = data.pergunta;
                    perguntaBox.classList.remove('hidden');
                    etapaPergunta = true;
                    form.querySelector('button[type="submit"]').textContent = 'Redefinir senha';
                    const senhaInput = document.getElementById('senha');
                    if (senhaInput) {
                        senhaInput.addEventListener('input', (evt) => {
                            updatePasswordStrength(evt.target.value || '');
                        });
                    }
                    const confirmarInput = document.getElementById('confirmar_senha');
                    if (confirmarInput) {
                        confirmarInput.addEventListener('blur', validarConfirmacaoSenha);
                    }
                } else {
                    // Mensagem genérica (nickname inexistente ou sem pergunta) - não indicar qual caso
                    errorDiv.textContent = data.message || 'Se o nickname informado estiver correto, o processo de recuperação continuará.';
                    errorDiv.classList.remove('hidden');
                    errorDiv.classList.remove('bg-red-100', 'border-red-400', 'text-red-700');
                    errorDiv.classList.add('bg-blue-50', 'border-blue-200', 'text-blue-800', 'border');
                }
                return;
            }
            if (data.redirect) {
                window.location.href = data.redirect;
            }
        });
    </script>
</body>
</html>

