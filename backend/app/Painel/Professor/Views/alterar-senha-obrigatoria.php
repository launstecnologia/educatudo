<!-- Header Section -->
<div class="mb-8">
    <div class="text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-orange-100 rounded-full mb-4">
            <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">
            Alteração Obrigatória de Senha 🔒
        </h2>
        <p class="text-gray-600">
            Por segurança, você deve alterar sua senha padrão antes de continuar
        </p>
    </div>
</div>

<!-- Password Change Form -->
<div class="max-w-md mx-auto">
    <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-orange-200 p-8">
        <form id="passwordForm" class="space-y-6">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <!-- Current Password Info -->
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-orange-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-sm text-orange-800">
                        <strong>Senha atual:</strong> 123456 (padrão do sistema)
                    </p>
                </div>
            </div>
            
            <!-- New Password -->
            <div>
                <label for="nova_senha" class="block text-sm font-medium text-gray-700 mb-2">
                    Nova Senha
                </label>
                <div class="relative">
                    <input type="password" 
                           id="nova_senha" 
                           name="nova_senha" 
                           class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                           placeholder="Digite sua nova senha"
                           required
                           minlength="8">
                    <button type="button" 
                            onclick="togglePassword('nova_senha')"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none">
                        <svg id="nova_senha-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        <svg id="nova_senha-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                        </svg>
                    </button>
                </div>
                
                <!-- Password Strength Bar -->
                <div class="mt-2">
                    <div class="flex items-center space-x-2 mb-1">
                        <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div id="password-strength-bar" class="h-full transition-all duration-300 rounded-full" style="width: 0%"></div>
                        </div>
                        <span id="password-strength-text" class="text-xs font-medium text-gray-500">Fraca</span>
                    </div>
                </div>
                
                <p class="text-xs text-gray-500 mt-1">Mínimo 8 caracteres com letras maiúsculas, minúsculas e números</p>
            </div>
            
            <!-- Confirm Password -->
            <div>
                <label for="confirmar_senha" class="block text-sm font-medium text-gray-700 mb-2">
                    Confirmar Nova Senha
                </label>
                <div class="relative">
                    <input type="password" 
                           id="confirmar_senha" 
                           name="confirmar_senha" 
                           class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                           placeholder="Confirme sua nova senha"
                           required
                           minlength="8">
                    <button type="button" 
                            onclick="togglePassword('confirmar_senha')"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none">
                        <svg id="confirmar_senha-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        <svg id="confirmar_senha-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                        </svg>
                    </button>
                </div>
                <div id="password-match" class="mt-1 text-xs"></div>
            </div>
            
            <!-- Password Requirements -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="text-sm font-medium text-blue-800 mb-2">Requisitos da senha:</h4>
                <ul class="text-xs text-blue-700 space-y-1">
                    <li id="req-length" class="flex items-center">
                        <svg class="w-3 h-3 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        Mínimo 8 caracteres
                    </li>
                    <li id="req-uppercase" class="flex items-center">
                        <svg class="w-3 h-3 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        Pelo menos uma letra maiúscula
                    </li>
                    <li id="req-lowercase" class="flex items-center">
                        <svg class="w-3 h-3 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        Pelo menos uma letra minúscula
                    </li>
                    <li id="req-number" class="flex items-center">
                        <svg class="w-3 h-3 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        Pelo menos um número
                    </li>
                    <li id="req-default" class="flex items-center">
                        <svg class="w-3 h-3 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        Não pode ser "123456"
                    </li>
                </ul>
            </div>
            
            <!-- Error Message -->
            <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"></div>
            
            <!-- Submit Button -->
            <button type="submit" 
                    class="w-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Alterar Senha
            </button>
        </form>
    </div>
</div>

<script>
// Função para alternar visualização de senha
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const eye = document.getElementById(inputId + '-eye');
    const eyeOff = document.getElementById(inputId + '-eye-off');
    
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

// Função para calcular força da senha
function calculatePasswordStrength(password) {
    let strength = 0;
    let checks = {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[^A-Za-z0-9]/.test(password)
    };
    
    if (checks.length) strength += 20;
    if (checks.uppercase) strength += 20;
    if (checks.lowercase) strength += 20;
    if (checks.number) strength += 20;
    if (checks.special) strength += 20;
    
    return { strength, checks };
}

// Função para atualizar barra de força
function updatePasswordStrength(password) {
    const { strength, checks } = calculatePasswordStrength(password);
    const bar = document.getElementById('password-strength-bar');
    const text = document.getElementById('password-strength-text');
    
    bar.style.width = strength + '%';
    
    // Atualizar cor e texto
    if (strength < 40) {
        bar.className = 'h-full transition-all duration-300 rounded-full bg-red-500';
        text.textContent = 'Fraca';
        text.className = 'text-xs font-medium text-red-600';
    } else if (strength < 60) {
        bar.className = 'h-full transition-all duration-300 rounded-full bg-orange-500';
        text.textContent = 'Média';
        text.className = 'text-xs font-medium text-orange-600';
    } else if (strength < 80) {
        bar.className = 'h-full transition-all duration-300 rounded-full bg-yellow-500';
        text.textContent = 'Boa';
        text.className = 'text-xs font-medium text-yellow-600';
    } else {
        bar.className = 'h-full transition-all duration-300 rounded-full bg-green-500';
        text.textContent = 'Forte';
        text.className = 'text-xs font-medium text-green-600';
    }
    
    // Atualizar ícones de requisitos
    updateRequirementIcon('req-length', checks.length);
    updateRequirementIcon('req-uppercase', checks.uppercase);
    updateRequirementIcon('req-lowercase', checks.lowercase);
    updateRequirementIcon('req-number', checks.number);
    updateRequirementIcon('req-default', password !== '123456');
}

function updateRequirementIcon(id, isValid) {
    const element = document.getElementById(id);
    const svg = element.querySelector('svg');
    
    if (isValid) {
        svg.innerHTML = '<path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>';
        svg.className.baseVal = 'w-3 h-3 mr-2 text-green-600';
        element.classList.remove('text-blue-700');
        element.classList.add('text-green-700');
    } else {
        svg.innerHTML = '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>';
        svg.className.baseVal = 'w-3 h-3 mr-2 text-red-600';
        element.classList.remove('text-green-700');
        element.classList.add('text-blue-700');
    }
}

// Event listeners
document.getElementById('nova_senha').addEventListener('input', function() {
    const password = this.value;
    updatePasswordStrength(password);
    
    // Verificar correspondência
    const confirmarSenha = document.getElementById('confirmar_senha').value;
    if (confirmarSenha) {
        checkPasswordMatch();
    }
});

document.getElementById('confirmar_senha').addEventListener('input', function() {
    checkPasswordMatch();
});

function checkPasswordMatch() {
    const novaSenha = document.getElementById('nova_senha').value;
    const confirmarSenha = document.getElementById('confirmar_senha').value;
    const matchDiv = document.getElementById('password-match');
    
    if (confirmarSenha) {
        if (novaSenha === confirmarSenha) {
            matchDiv.textContent = '✓ Senhas coincidem';
            matchDiv.className = 'mt-1 text-xs text-green-600';
            document.getElementById('confirmar_senha').classList.remove('border-red-300');
            document.getElementById('confirmar_senha').classList.add('border-green-300');
        } else {
            matchDiv.textContent = '✗ Senhas não coincidem';
            matchDiv.className = 'mt-1 text-xs text-red-600';
            document.getElementById('confirmar_senha').classList.remove('border-green-300');
            document.getElementById('confirmar_senha').classList.add('border-red-300');
        }
    } else {
        matchDiv.textContent = '';
        document.getElementById('confirmar_senha').classList.remove('border-red-300', 'border-green-300');
    }
}

// Form submission
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const novaSenha = formData.get('nova_senha');
    const confirmarSenha = formData.get('confirmar_senha');
    const errorDiv = document.getElementById('errorMessage');
    
    // Limpar erro anterior
    errorDiv.classList.add('hidden');
    
    // Validações client-side
    if (novaSenha.length < 8) {
        errorDiv.textContent = 'A senha deve ter pelo menos 8 caracteres';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    if (novaSenha === '123456') {
        errorDiv.textContent = 'A nova senha não pode ser a senha padrão';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    if (novaSenha !== confirmarSenha) {
        errorDiv.textContent = 'As senhas não coincidem';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    // Verificar requisitos
    const { checks } = calculatePasswordStrength(novaSenha);
    if (!checks.length || !checks.uppercase || !checks.lowercase || !checks.number) {
        errorDiv.textContent = 'A senha não atende aos requisitos mínimos';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    // Desabilitar botão
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Alterando...';
    
    // Submit form
    fetch('<?= URL ?>/professor/alterar-senha-obrigatoria', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Senha alterada com sucesso!');
            window.location.href = data.redirect || '<?= URL ?>/professor/dashboard';
        } else {
            errorDiv.textContent = data.error || 'Erro ao alterar senha';
            errorDiv.classList.remove('hidden');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Alterar Senha';
        }
    })
    .catch(error => {
        errorDiv.textContent = 'Erro de conexão. Tente novamente.';
        errorDiv.classList.remove('hidden');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Alterar Senha';
    });
});
</script>

