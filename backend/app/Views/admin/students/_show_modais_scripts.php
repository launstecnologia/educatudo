<?php
/** Extraído de show.php — conteúdo existente, sem alteração de regra de negócio. */
?>
<!-- Modal inativação -->
<div id="modalInativarAluno" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Inativar aluno</h3>
        <p class="text-sm text-gray-600 mb-4">Registra motivo, encerra matrículas e preserva histórico. Use <strong>TRANSFERENCIA</strong> para marcar TR na lista de chamada.</p>
        <form id="formInativarAluno" class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                <select name="reason" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="TRANSFERENCIA">Transferência (TR)</option>
                    <option value="EVASAO">Evasão</option>
                    <option value="CONCLUSAO">Conclusão</option>
                    <option value="ADMINISTRATIVO">Administrativo</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Observação</label>
                <textarea name="observation" rows="2" required class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sua senha</label>
                <input type="password" name="password" required class="w-full border border-gray-300 rounded-lg px-3 py-2" autocomplete="current-password">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Digite CONFIRMAR</label>
                <input type="text" name="confirm_text" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="CONFIRMAR">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="fecharModalInativarAluno()" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-orange-600 text-white hover:bg-orange-700">Confirmar inativação</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal exclusão (soft-delete: só oculta da visualização) -->
<div id="modalExcluirAluno" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Excluir aluno</h3>
        <p class="text-sm text-gray-600 mb-4">O aluno será <strong>ocultado da visualização</strong>. Os dados <strong>não são apagados</strong> do banco e podem ser recuperados depois.</p>
        <form id="formExcluirAluno" class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Observação (opcional)</label>
                <textarea name="observation" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Motivo da exclusão"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sua senha</label>
                <input type="password" name="password" required class="w-full border border-gray-300 rounded-lg px-3 py-2" autocomplete="current-password">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Digite CONFIRMAR</label>
                <input type="text" name="confirm_text" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="CONFIRMAR">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="fecharModalExcluirAluno()" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">Confirmar exclusão</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal ativação -->
<div id="modalAtivarAluno" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Reativar aluno</h3>
        <form id="formAtivarAluno" class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Observação (opcional)</label>
                <textarea name="observation" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2">Reativação administrativa</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Digite CONFIRMAR</label>
                <input type="text" name="confirm_text" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="CONFIRMAR">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="fecharModalAtivarAluno()" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700">Cancelar</button>
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<script>
const alunoStatusId = <?= (int) ($student['id'] ?? 0) ?>;

function abrirModalInativarAluno() {
    document.getElementById('modalInativarAluno')?.classList.remove('hidden');
}
function fecharModalInativarAluno() {
    document.getElementById('modalInativarAluno')?.classList.add('hidden');
}
function abrirModalExcluirAluno() {
    document.getElementById('modalExcluirAluno')?.classList.remove('hidden');
}
function fecharModalExcluirAluno() {
    document.getElementById('modalExcluirAluno')?.classList.add('hidden');
}
function abrirModalAtivarAluno() {
    document.getElementById('modalAtivarAluno')?.classList.remove('hidden');
}
function fecharModalAtivarAluno() {
    document.getElementById('modalAtivarAluno')?.classList.add('hidden');
}
function abrirModalMatricula() {
    var form = document.getElementById('formAddMatricula');
    var msg = document.getElementById('matriculaMsg');
    if (form) form.reset();
    var dataEntrada = document.getElementById('mat_data_entrada');
    if (dataEntrada) dataEntrada.value = '<?= date('Y-m-d') ?>';
    if (msg) msg.classList.add('hidden');
    if (typeof atualizarCheckboxTurmaPrincipalMatricula === 'function') {
        atualizarCheckboxTurmaPrincipalMatricula();
    }
    document.getElementById('modalAddMatricula')?.classList.remove('hidden');
}
function fecharModalMatricula() {
    document.getElementById('modalAddMatricula')?.classList.add('hidden');
}

function extrairMensagemErroServidor(raw, httpStatus) {
    const texto = (raw || '').toString().trim();
    if (texto === '') {
        return 'Resposta vazia do servidor (HTTP ' + httpStatus + ')';
    }
    if (texto.startsWith('{') || texto.startsWith('[')) {
        return texto.slice(0, 300);
    }
    const titleMatch = texto.match(/<title>([^<]+)<\/title>/i);
    if (titleMatch && titleMatch[1]) {
        return 'Erro no servidor: ' + titleMatch[1].trim() + ' (HTTP ' + httpStatus + ')';
    }
    const bodyMatch = texto.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
    if (bodyMatch && bodyMatch[1]) {
        const limpo = bodyMatch[1].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
        if (limpo !== '') {
            return limpo.slice(0, 220);
        }
    }
    return 'Erro inesperado do servidor (HTTP ' + httpStatus + ')';
}

async function parseRespostaJsonFetch(response) {
    const raw = await response.text();
    let data = {};
    try {
        data = raw ? JSON.parse(raw) : {};
    } catch (err) {
        throw new Error(extrairMensagemErroServidor(raw, response.status));
    }
    if (!response.ok && !data.error) {
        throw new Error(data.message || ('Falha na requisição (HTTP ' + response.status + ')'));
    }
    return data;
}

document.getElementById('formInativarAluno')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('_token', document.getElementById('csrf_token').value);
    fd.append('confirm', '1');
    fetch(`<?= URL ?>/admin/students/${alunoStatusId}/inactivate`, { method: 'POST', body: fd })
        .then(parseRespostaJsonFetch)
        .then(data => {
            if (data.success) location.reload();
            else alert('Erro: ' + (data.error || 'Falha ao inativar'));
        })
        .catch((err) => alert(err.message || 'Erro de conexão'));
});

document.getElementById('formAtivarAluno')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('_token', document.getElementById('csrf_token').value);
    fd.append('confirm', '1');
    fetch(`<?= URL ?>/admin/students/${alunoStatusId}/activate`, { method: 'POST', body: fd })
        .then(parseRespostaJsonFetch)
        .then(data => {
            if (data.success) location.reload();
            else alert('Erro: ' + (data.error || 'Falha ao ativar'));
        })
        .catch((err) => alert(err.message || 'Erro de conexão'));
});

document.getElementById('formExcluirAluno')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('_token', document.getElementById('csrf_token').value);
    fetch(`<?= URL ?>/admin/students/${alunoStatusId}/excluir`, { method: 'POST', body: fd })
        .then(parseRespostaJsonFetch)
        .then(data => {
            if (data.success) {
                window.location.href = '<?= URL ?>/admin/students';
            } else {
                alert('Erro ao excluir: ' + (data.error || 'Falha ao excluir'));
            }
        })
        .catch((err) => alert(err.message || 'Erro de conexão'));
});

// Função para alterar senha para padrão
function alterarSenhaPadrao(alunoId, alunoNome) {
    console.log('Função alterarSenhaPadrao chamada:', alunoId, alunoNome);
    
    if (!confirm(`Tem certeza que deseja alterar a senha do aluno "${alunoNome}" para a senha padrão (123456)?`)) {
        console.log('Usuário cancelou a operação');
        return;
    }
    
    console.log('Usuário confirmou a operação');
    
    // Criar form data
    const formData = new FormData();
    const tokenElement = document.getElementById('csrf_token');
    if (tokenElement) {
        formData.append('_token', tokenElement.value);
        console.log('Token CSRF:', tokenElement.value);
    } else {
        console.error('Token CSRF não encontrado!');
        alert('Erro: Token de segurança não encontrado');
        return;
    }
    
    // Mostrar loading no botão
    const botao = event.target;
    const textoOriginal = botao.innerHTML;
    botao.innerHTML = '⏳ Alterando...';
    botao.disabled = true;
    
    const url = `<?= URL ?>/admin/students/${alunoId}/password`;
    console.log('Fazendo requisição para:', url);
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Resposta recebida:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Dados recebidos:', data);
        botao.innerHTML = textoOriginal;
        botao.disabled = false;
        
        if (data.success) {
            // Mostrar card de sucesso
            document.getElementById('alunoNomeConfirmacao').textContent = alunoNome;
            document.getElementById('successCard').classList.remove('hidden');
            
            // Scroll para o card de sucesso
            document.getElementById('successCard').scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            alert(`Senha alterada com sucesso! Nova senha: 123456`);
        } else {
            alert('Erro ao alterar senha: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        botao.innerHTML = textoOriginal;
        botao.disabled = false;
        alert('Erro de conexão ao alterar senha. Tente novamente.');
    });
}

// Função para copiar senha
function copiarSenha() {
    const senha = '123456';
    navigator.clipboard.writeText(senha).then(() => {
        alert('Senha copiada para a área de transferência!');
    }).catch(err => {
        console.error('Erro ao copiar:', err);
        alert('Erro ao copiar senha. Tente selecionar e copiar manualmente: 123456');
    });
}

// Função para fechar card de sucesso
function fecharCardSucesso() {
    document.getElementById('successCard').classList.add('hidden');
}

// Função para controlar tabs
const STUDENT_ID_FOR_TABS = <?= (int) ($student['id'] ?? 0) ?>;

function showTab(tabName) {
    // Esconder todos os conteúdos
    const contents = document.querySelectorAll('.tab-content');
    contents.forEach(content => {
        content.classList.add('hidden');
    });

    // Remover classe active de todos os botões
    const buttons = document.querySelectorAll('.tab-button');
    buttons.forEach(button => {
        button.classList.remove('active', 'border-blue-500', 'text-blue-600');
        button.classList.add('border-transparent', 'text-gray-500');
    });

    // Mostrar conteúdo da tab selecionada
    const content = document.getElementById('content-' + tabName);
    if (content) {
        content.classList.remove('hidden');
        carregarAbaSobDemanda(content);
    }

    // Ativar botão da tab selecionada
    const button = document.getElementById('tab-' + tabName);
    if (button) {
        button.classList.add('active', 'border-blue-500', 'text-blue-600');
        button.classList.remove('border-transparent', 'text-gray-500');
    }
}

// Abas marcadas com data-lazy-tab carregam o conteúdo via AJAX só no primeiro
// clique (em vez de virem prontas na carga inicial da página, que era o que
// tornava o Detalhe do Aluno lento — muita informação calculada de uma vez só).
function carregarAbaSobDemanda(content) {
    const lazyTab = content.getAttribute('data-lazy-tab');
    if (!lazyTab || content.getAttribute('data-lazy-loaded') === '1') {
        return;
    }
    content.setAttribute('data-lazy-loaded', '1');
    fetch(<?= json_encode(URL . '/admin/students', JSON_UNESCAPED_SLASHES) ?> + '/' + STUDENT_ID_FOR_TABS + '/tab/' + lazyTab, { credentials: 'same-origin' })
        .then(function (r) {
            if (!r.ok) { throw new Error('HTTP ' + r.status); }
            return r.text();
        })
        .then(function (html) {
            content.innerHTML = html;
        })
        .catch(function () {
            content.setAttribute('data-lazy-loaded', '0');
            content.innerHTML = '<div class="text-center py-12 text-red-600">Erro ao carregar esta aba. <button type="button" class="underline" onclick="carregarAbaSobDemanda(document.getElementById(\'' + content.id + '\'))">Tentar novamente</button></div>';
        });
}

const notasPrintLogoUrl = <?= json_encode($logoHorizontalPrintUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

function abrirModalNotasEvento(contentId, buttonEl) {
    if (!contentId) return;
    const content = document.getElementById(contentId);
    if (!content) return;
    const title = (buttonEl && buttonEl.getAttribute('data-notas-title')) || 'Notas do evento';
    const modal = document.getElementById('modal-notas-evento');
    const body = document.getElementById('modal-notas-evento-body');
    const titleEl = document.getElementById('modal-notas-evento-title');
    if (!modal || !body || !titleEl) return;
    titleEl.textContent = title;
    body.innerHTML = content.innerHTML;
    modal.classList.remove('hidden');
}

function fecharModalNotasEvento() {
    const modal = document.getElementById('modal-notas-evento');
    const body = document.getElementById('modal-notas-evento-body');
    if (modal) modal.classList.add('hidden');
    if (body) body.innerHTML = '';
}

function imprimirNotasEvento(contentId, buttonEl) {
    if (!contentId) return;
    const content = document.getElementById(contentId);
    if (!content) return;
    const title = (buttonEl && buttonEl.getAttribute('data-notas-title')) || 'Notas do evento';
    imprimirConteudoNotas(title, content.innerHTML);
}

function imprimirNotasModalAtual() {
    const titleEl = document.getElementById('modal-notas-evento-title');
    const body = document.getElementById('modal-notas-evento-body');
    if (!body) return;
    const title = titleEl ? titleEl.textContent : 'Notas do evento';
    imprimirConteudoNotas(title, body.innerHTML);
}

function imprimirConteudoNotas(title, bodyHtml) {
    const win = window.open('', '_blank', 'width=1024,height=768');
    if (!win) {
        alert('Não foi possível abrir a janela de impressão. Verifique o bloqueio de pop-up.');
        return;
    }
    const safeTitle = (title || 'Notas do evento').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const headerLogo = notasPrintLogoUrl
        ? '<img src="' + notasPrintLogoUrl.replace(/"/g, '&quot;') + '" alt="Logo" style="max-height:52px; max-width:260px; object-fit:contain;">'
        : '';
    win.document.write(
        '<!DOCTYPE html><html><head><meta charset="UTF-8">' +
        '<title>' + safeTitle + '</title>' +
        '<style>' +
        '@page{size:A4 landscape;margin:12mm;}' +
        'body{font-family:Arial,sans-serif;margin:0;color:#111827;}' +
        '.sheet{padding:2mm 0;}' +
        '.header{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:8px 0 14px;border-bottom:2px solid #0f766e;margin-bottom:14px;}' +
        '.header-meta{text-align:right;}' +
        'h1{font-size:18px;margin:0 0 4px;line-height:1.2;}' +
        '.sub{font-size:12px;color:#6b7280;margin:0;}' +
        '.print-table-wrap{border:1px solid #d1d5db;border-radius:8px;overflow:hidden;}' +
        'table{width:100%;border-collapse:collapse;font-size:13px;}' +
        'th,td{border:1px solid #d1d5db;padding:7px 8px;text-align:center;}' +
        'th:first-child,td:first-child{text-align:left;}' +
        'thead th{background:#eef2ff;color:#1f2937;font-weight:700;}' +
        'tbody tr:nth-child(even){background:#f9fafb;}' +
        '@media print{.no-print{display:none !important;}}' +
        '</style></head><body>' +
        '<div class="sheet">' +
        '<div class="header">' +
        '<div>' + headerLogo + '</div>' +
        '<div class="header-meta">' +
        '<h1>' + safeTitle + '</h1>' +
        '<p class="sub">Formato boletim · Impresso em ' + new Date().toLocaleString('pt-BR') + '</p>' +
        '</div>' +
        '</div>' +
        '<div class="print-table-wrap">' + bodyHtml + '</div>' +
        '</div>' +
        '<script>window.onload=function(){window.print();};<\/script>' +
        '</body></html>'
    );
    win.document.close();
}

// Função para mostrar/ocultar detalhes de conversas
function toggleConversaDetalhes(conversaId) {
    const detalhes = document.getElementById('conversa-detalhes-' + conversaId);
    const toggleText = document.getElementById('toggle-text-' + conversaId);
    
    if (detalhes && toggleText) {
        if (detalhes.classList.contains('hidden')) {
            detalhes.classList.remove('hidden');
            toggleText.textContent = 'Ocultar detalhes';
        } else {
            detalhes.classList.add('hidden');
            toggleText.textContent = 'Ver detalhes';
        }
    }
}

function irParaAbaRelatorio(tabName) {
    showTab(tabName);
    const sec = document.getElementById('section-relatorio-detalhado');
    if (sec) {
        sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Função para mostrar/ocultar detalhes de redações
function toggleRedacaoDetails(redacaoId) {
    const detalhes = document.getElementById('redacao-detalhes-' + redacaoId);
    const arrow = document.getElementById('arrow-' + redacaoId);
    
    if (detalhes && arrow) {
        if (detalhes.classList.contains('hidden')) {
            detalhes.classList.remove('hidden');
            arrow.classList.add('rotate-180');
        } else {
            detalhes.classList.add('hidden');
            arrow.classList.remove('rotate-180');
        }
    }
}

// Função para abrir modal de análise
function abrirModalAnalise(alunoId) {
    document.getElementById('modalAnalise').classList.remove('hidden');
    document.getElementById('alunoIdAnalise').value = alunoId;
    // Data padrão: hoje
    const hoje = new Date().toISOString().split('T')[0];
    document.getElementById('dataAte').value = hoje;
}

// Função para fechar modal
function fecharModalAnalise() {
    document.getElementById('modalAnalise').classList.add('hidden');
    document.getElementById('resultadoAnalise').classList.add('hidden');
    document.getElementById('loadingAnalise').classList.add('hidden');
}

// Função para gerar análise
function gerarAnalise() {
    const alunoId = document.getElementById('alunoIdAnalise').value;
    const dataAte = document.getElementById('dataAte').value;
    
    if (!dataAte) {
        alert('Por favor, selecione uma data limite');
        return;
    }
    
    // Mostrar loading
    document.getElementById('loadingAnalise').classList.remove('hidden');
    document.getElementById('resultadoAnalise').classList.add('hidden');
    document.getElementById('btnGerarAnalise').disabled = true;
    
    const formData = new FormData();
    formData.append('_token', document.getElementById('csrf_token').value);
    formData.append('data_ate', dataAte);
    
    fetch(`<?= URL ?>/admin/students/${alunoId}/analise-tudinha`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.job_id) {
            document.getElementById('loadingAnalise').classList.add('hidden');
            document.getElementById('btnGerarAnalise').disabled = false;
            alert('Erro ao gerar análise: ' + (data.error || 'Erro desconhecido'));
            return;
        }

        new AIJobPoller(data.job_id, {
            onDone: function(result) {
                document.getElementById('loadingAnalise').classList.add('hidden');
                document.getElementById('btnGerarAnalise').disabled = false;
                document.getElementById('resultadoAnalise').classList.remove('hidden');
                document.getElementById('conteudoAnalise').innerHTML = formatarAnalise(result.analise);
                document.getElementById('dataAnalise').textContent = 'Análise de até ' + new Date(dataAte).toLocaleDateString('pt-BR');
            },
            onFailed: function(err) {
                document.getElementById('loadingAnalise').classList.add('hidden');
                document.getElementById('btnGerarAnalise').disabled = false;
                alert('Erro ao gerar análise: ' + err);
            }
        });
    })
    .catch(error => {
        console.error('Erro:', error);
        document.getElementById('loadingAnalise').classList.add('hidden');
        document.getElementById('btnGerarAnalise').disabled = false;
        alert('Erro de conexão ao gerar análise');
    });
}

// Ocorrências - formulário e IA

// Função para formatar análise
function formatarAnalise(analise) {
    // Se for string, retornar diretamente
    if (typeof analise === 'string') {
        // Tentar fazer parse se for JSON string
        try {
            analise = JSON.parse(analise);
        } catch(e) {
            return '<div class="whitespace-pre-wrap text-gray-700">' + analise.replace(/\n/g, '<br>') + '</div>';
        }
    }
    
    // Se ainda for objeto, extrair campos
    if (typeof analise === 'object' && analise !== null) {
        let html = '';
        
        // Função auxiliar para escapar HTML
        const escapeHtml = (text) => {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };
        
        // Função auxiliar para escapar HTML e quebrar linhas
        const formatarTexto = (texto) => {
            if (!texto) return '';
            
            // Se for objeto, formatar como lista
            if (typeof texto === 'object' && texto !== null) {
                let textoFormatado = '';
                for (const [key, value] of Object.entries(texto)) {
                    if (value) {
                        const valorFormatado = typeof value === 'object' ? JSON.stringify(value) : String(value);
                        textoFormatado += '<div class="mb-3"><strong class="text-gray-800">' + escapeHtml(String(key)) + ':</strong> <span class="text-gray-700">' + escapeHtml(valorFormatado) + '</span></div>';
                    }
                }
                return textoFormatado || '<pre>' + JSON.stringify(texto, null, 2) + '</pre>';
            }
            
            // Converter para string
            texto = String(texto);
            
            // Tentar fazer parse se for JSON string
            try {
                const parsed = JSON.parse(texto);
                if (typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)) {
                    // Se for objeto, formatar como lista
                    let textoFormatado = '';
                    for (const [key, value] of Object.entries(parsed)) {
                        if (value) {
                            const valorFormatado = typeof value === 'object' ? JSON.stringify(value) : String(value);
                            textoFormatado += '<div class="mb-3"><strong class="text-gray-800">' + escapeHtml(String(key)) + ':</strong> <span class="text-gray-700">' + escapeHtml(valorFormatado) + '</span></div>';
                        }
                    }
                    return textoFormatado || escapeHtml(texto);
                }
            } catch(e) {
                // Não é JSON, continuar normalmente
            }
            
            // Escapar HTML e converter quebras de linha
            return escapeHtml(texto).replace(/\n/g, '<br>');
        };
        
        // Dificuldades
        const dificuldades = analise.dificuldades || analise.Dificuldades || analise.dificuldades_identificadas;
        if (dificuldades) {
            html += '<div class="mb-6"><h4 class="font-bold text-red-700 mb-2 text-lg">🔴 Dificuldades Identificadas:</h4><div class="text-gray-700 leading-relaxed">' + formatarTexto(dificuldades) + '</div></div>';
        }
        
        // Facilidades
        const facilidades = analise.facilidades || analise.Facilidades || analise.facilidades_identificadas;
        if (facilidades) {
            html += '<div class="mb-6"><h4 class="font-bold text-green-700 mb-2 text-lg">🟢 Facilidades Identificadas:</h4><div class="text-gray-700 leading-relaxed">' + formatarTexto(facilidades) + '</div></div>';
        }
        
        // Observações
        const observacoes = analise.observacoes || analise.Observacoes || analise.observacoes_gerais;
        if (observacoes) {
            html += '<div class="mb-6"><h4 class="font-bold text-blue-700 mb-2 text-lg">📊 Observações Gerais:</h4><div class="text-gray-700 leading-relaxed">' + formatarTexto(observacoes) + '</div></div>';
        }
        
        // Recomendações
        const recomendacoes = analise.recomendacoes || analise.Recomendacoes;
        if (recomendacoes) {
            html += '<div class="mb-6"><h4 class="font-bold text-purple-700 mb-2 text-lg">💡 Recomendações:</h4><div class="text-gray-700 leading-relaxed">' + formatarTexto(recomendacoes) + '</div></div>';
        }
        
        // Se tiver análise_completa mas não os campos específicos
        if (!html && analise.analise_completa) {
            html = '<div class="whitespace-pre-wrap text-gray-700">' + formatarTexto(analise.analise_completa) + '</div>';
        }
        
        return html || '<div class="text-gray-700"><pre>' + JSON.stringify(analise, null, 2) + '</pre></div>';
    }
    
    return '<div class="text-gray-700">Dados inválidos recebidos</div>';
}

        // Função para abrir modal de cadastrar pai
        function abrirModalCadastrarPai(alunoId) {
            document.getElementById('modalCadastrarPai').classList.remove('hidden');
            document.getElementById('aluno_id_pai').value = alunoId;
            document.getElementById('formCadastrarPai').reset();
            document.getElementById('errorMessagePai').classList.add('hidden');
            document.getElementById('successMessagePai').classList.add('hidden');
        }

        // Função para fechar modal de cadastrar pai
        function fecharModalCadastrarPai() {
            document.getElementById('modalCadastrarPai').classList.add('hidden');
            document.getElementById('formCadastrarPai').reset();
            document.getElementById('errorMessagePai').classList.add('hidden');
            document.getElementById('successMessagePai').classList.add('hidden');
        }

        function abrirModalEditarResponsavel(data) {
            document.getElementById('modalEditarResponsavel').classList.remove('hidden');
            document.getElementById('resp_edit_aluno_id').value = data.aluno_id || '';
            document.getElementById('resp_edit_responsavel_id').value = data.responsavel_id || '';
            document.getElementById('resp_edit_nome').value = data.nome || '';
            document.getElementById('resp_edit_email').value = data.email || '';
            document.getElementById('resp_edit_telefone').value = data.telefone || '';
            document.getElementById('resp_edit_cpf').value = data.cpf || '';
            document.getElementById('resp_edit_rg').value = data.rg || '';
            document.getElementById('resp_edit_celular').value = data.celular || '';
            document.getElementById('resp_edit_data_nascimento').value = data.data_nascimento || '';
            document.getElementById('resp_edit_endereco').value = data.endereco || '';
            document.getElementById('resp_edit_numero').value = data.numero || '';
            document.getElementById('resp_edit_complemento').value = data.complemento || '';
            document.getElementById('resp_edit_bairro').value = data.bairro || '';
            document.getElementById('resp_edit_cidade').value = data.cidade || '';
            document.getElementById('resp_edit_uf').value = data.uf || '';
            document.getElementById('resp_edit_cep').value = data.cep || '';
            document.getElementById('resp_edit_observacoes').value = data.observacoes || '';
            document.getElementById('resp_edit_senha').value = '';
            document.getElementById('resp_edit_financeiro').checked = Number(data.is_financeiro || 0) === 1;
            document.getElementById('resp_edit_ativo').checked = Number(data.ativo || 0) === 1;
            var setVal = function (id, val) { var el = document.getElementById(id); if (el) { el.value = val || ''; } };
            var setChk = function (id, val) { var el = document.getElementById(id); if (el) { el.checked = Number(val || 0) === 1; } };
            setVal('resp_edit_parentesco', data.parentesco);
            setVal('resp_edit_profissao', data.profissao);
            setVal('resp_edit_empresa', data.empresa);
            setChk('resp_edit_pode_retirar', data.pode_retirar);
            setChk('resp_edit_recebe_boletos', data.recebe_boletos);
            setChk('resp_edit_recebe_boletim', data.recebe_boletim);
            setChk('resp_edit_recebe_notificacoes', data.recebe_notificacoes);
            setChk('resp_edit_responsavel_pedagogico', data.responsavel_pedagogico);
            setChk('resp_edit_guarda_judicial', data.guarda_judicial);
            setChk('resp_edit_assina_documentos', data.assina_documentos);
            document.getElementById('respEditError').classList.add('hidden');
            document.getElementById('respEditSuccess').classList.add('hidden');
        }

        function fecharModalEditarResponsavel() {
            document.getElementById('modalEditarResponsavel').classList.add('hidden');
            document.getElementById('formEditarResponsavel').reset();
            document.getElementById('respEditError').classList.add('hidden');
            document.getElementById('respEditSuccess').classList.add('hidden');
        }

        var STUDENT_ID_DOC = <?= (int) ($student['id'] ?? 0) ?>;

        function docToggleTitulo() {
            var tipo = document.getElementById('doc_tipo').value;
            document.getElementById('doc_titulo_wrap').classList.toggle('hidden', tipo !== 'outros');
        }

        function abrirModalDocumento(data) {
            var modal = document.getElementById('modalDocumentoAluno');
            document.getElementById('formDocumentoAluno').reset();
            document.getElementById('docError').classList.add('hidden');
            document.getElementById('docSuccess').classList.add('hidden');
            data = data || {};
            document.getElementById('doc_doc_id').value = data.doc_id || '';
            document.getElementById('doc_tipo').value = data.tipo || 'rg';
            document.getElementById('doc_titulo').value = data.titulo || '';
            document.getElementById('doc_status').value = data.status || 'pendente';
            document.getElementById('doc_observacao').value = data.observacao || '';
            docToggleTitulo();
            modal.classList.remove('hidden');
        }

        function fecharModalDocumento() {
            document.getElementById('modalDocumentoAluno').classList.add('hidden');
        }

        async function salvarDocumento(event) {
            event.preventDefault();
            var form = event.target;
            var formData = new FormData(form);
            var errorDiv = document.getElementById('docError');
            var successDiv = document.getElementById('docSuccess');
            errorDiv.classList.add('hidden');
            successDiv.classList.add('hidden');
            try {
                var response = await fetch('<?= URL ?>/admin/students/' + STUDENT_ID_DOC + '/documentos/salvar', {
                    method: 'POST',
                    body: formData
                });
                var result = await response.json();
                if (response.ok && result.success) {
                    successDiv.textContent = result.message || 'Documento salvo';
                    successDiv.classList.remove('hidden');
                    setTimeout(function () { location.reload(); }, 1000);
                } else {
                    errorDiv.textContent = result.error || 'Erro ao salvar documento';
                    errorDiv.classList.remove('hidden');
                }
            } catch (e) {
                errorDiv.textContent = 'Erro de conexão. Tente novamente.';
                errorDiv.classList.remove('hidden');
            }
        }

        async function removerDocumento(docId) {
            if (!confirm('Remover este documento?')) { return; }
            var formData = new FormData(document.getElementById('formRemoverDocumento'));
            try {
                var response = await fetch('<?= URL ?>/admin/students/' + STUDENT_ID_DOC + '/documentos/' + docId + '/remover', {
                    method: 'POST',
                    body: formData
                });
                var result = await response.json();
                if (response.ok && result.success) {
                    location.reload();
                } else {
                    alert(result.error || 'Erro ao remover documento');
                }
            } catch (e) {
                alert('Erro de conexão. Tente novamente.');
            }
        }

        function abrirModalAcessarComoPai() {
            document.getElementById('modalAcessarComoPai').classList.remove('hidden');
        }

        function fecharModalAcessarComoPai() {
            document.getElementById('modalAcessarComoPai').classList.add('hidden');
        }

        async function salvarEdicaoResponsavel(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const errorDiv = document.getElementById('respEditError');
            const successDiv = document.getElementById('respEditSuccess');
            errorDiv.classList.add('hidden');
            successDiv.classList.add('hidden');

            try {
                const response = await fetch('<?= URL ?>/admin/students/responsavel/atualizar', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (response.ok && result.success) {
                    successDiv.textContent = result.message || 'Responsável atualizado.';
                    successDiv.classList.remove('hidden');
                    setTimeout(() => location.reload(), 900);
                } else {
                    errorDiv.textContent = result.error || 'Erro ao atualizar responsável.';
                    errorDiv.classList.remove('hidden');
                }
            } catch (error) {
                errorDiv.textContent = 'Erro de conexão. Tente novamente.';
                errorDiv.classList.remove('hidden');
            }
        }

        // Função para cadastrar pai
        async function cadastrarPai(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            const errorDiv = document.getElementById('errorMessagePai');
            const successDiv = document.getElementById('successMessagePai');
            
            // Hide previous messages
            errorDiv.classList.add('hidden');
            successDiv.classList.add('hidden');
            
            try {
                const response = await fetch('<?= URL ?>/admin/students/cadastrar-pai', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    successDiv.textContent = result.message || 'Responsável cadastrado e vinculado com sucesso!';
                    successDiv.classList.remove('hidden');
                    
                    // Redirect after 2 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    errorDiv.textContent = result.error || 'Erro ao cadastrar responsável';
                    errorDiv.classList.remove('hidden');
                }
            } catch (error) {
                errorDiv.textContent = 'Erro de conexão. Tente novamente.';
                errorDiv.classList.remove('hidden');
            }
        }

        const ADMIN_PERMISSIONS = <?= json_encode($admin_permissions, JSON_UNESCAPED_UNICODE) ?>;

        function hasAdminPermission(key, action = 'visualizar') {
            return !!(ADMIN_PERMISSIONS[key] && ADMIN_PERMISSIONS[key][action]);
        }

        function applyStudentPermissionVisibility() {
            const responsaveisSection = document.getElementById('section-responsaveis-vinculados');
            if (responsaveisSection && !hasAdminPermission('responsaveis_vinculados', 'visualizar')) {
                responsaveisSection.classList.add('hidden');
            }

            const matriculasSection = document.getElementById('section-matriculas-aluno');
            if (matriculasSection && !hasAdminPermission('matriculas_aluno', 'visualizar')) {
                matriculasSection.classList.add('hidden');
            }

            document.querySelectorAll('[data-perm-key]').forEach((el) => {
                const key = el.getAttribute('data-perm-key');
                const action = el.getAttribute('data-perm-action') || 'visualizar';
                if (!key || hasAdminPermission(key, action)) return;
                el.classList.add('hidden');
                if ('disabled' in el) {
                    el.disabled = true;
                }
            });

            const tabMap = {
                'tab-relatorio': 'content-relatorio',
                'tab-redacoes': 'content-redacoes',
                'tab-ocorrencias': 'content-ocorrencias',
                'tab-jornadas': 'content-jornadas',
                'tab-provas': 'content-provas',
                'tab-notas-eventos': 'content-notas-eventos',
                'tab-boletim-eventos': 'content-boletim-eventos',
                'tab-acessos': 'content-acessos'
            };

            let firstAllowedTab = null;
            document.querySelectorAll('[data-tab-perm-key]').forEach((btn) => {
                const key = btn.getAttribute('data-tab-perm-key');
                const allowed = !!key && hasAdminPermission(key, 'visualizar');
                const contentId = tabMap[btn.id] || '';
                const content = contentId ? document.getElementById(contentId) : null;
                if (!allowed) {
                    btn.classList.add('hidden');
                    if (content) content.classList.add('hidden');
                    return;
                }
                if (!firstAllowedTab) {
                    firstAllowedTab = btn.id.replace('tab-', '');
                }
            });

            showTab(firstAllowedTab || 'relatorio');
        }

        // Inicializar tab padrão ao carregar
        document.addEventListener('DOMContentLoaded', function() {
            applyStudentPermissionVisibility();
            if (typeof initAbasAluno === 'function') {
                initAbasAluno();
            }
            
            // Fechar modal ao clicar fora
            document.getElementById('modalAnalise').addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModalAnalise();
                }
            });
            
            // Fechar modal de cadastrar pai ao clicar fora
            document.getElementById('modalCadastrarPai').addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModalCadastrarPai();
                }
            });

            document.getElementById('modalEditarResponsavel')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModalEditarResponsavel();
                }
            });

            document.getElementById('modalAcessarComoPai')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModalAcessarComoPai();
                }
            });

            document.getElementById('modalAddMatricula')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModalMatricula();
                }
            });
        });
</script>

<?php include __DIR__ . '/../../components/ai-job-poller.php'; ?>

<div id="modalAcessarComoPai" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full">
        <div class="sticky top-0 bg-white border-b border-gray-200 p-6 flex items-center justify-between">
            <div>
                <h3 class="text-2xl font-bold text-gray-900">Acessar como Pai</h3>
                <p class="text-sm text-gray-500 mt-1">Selecione qual responsável deseja acessar no portal.</p>
            </div>
            <button onclick="fecharModalAcessarComoPai()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="p-6">
            <?php if (empty($responsaveis_aluno)): ?>
                <p class="text-sm text-gray-500">Nenhum responsável ativo vinculado a este aluno.</p>
            <?php else: ?>
                <form method="GET" action="<?= URL ?>/admin/students/<?= (int)($student['id'] ?? 0) ?>/acessar-como-pai" class="space-y-4">
                    <div>
                        <label for="responsavel_id_acesso" class="block text-sm font-medium text-gray-700 mb-2">Responsável</label>
                        <select id="responsavel_id_acesso" name="responsavel_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500">
                            <option value="">Selecione um responsável</option>
                            <?php foreach ($responsaveis_aluno as $resp): ?>
                                <option value="<?= (int)($resp['id'] ?? 0) ?>">
                                    <?= safe_htmlspecialchars($resp['nome'] ?? '', 'Responsável') ?><?= !empty($resp['email']) ? ' - ' . safe_htmlspecialchars($resp['email']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="fecharModalAcessarComoPai()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                            Cancelar
                        </button>
                        <button type="submit" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                            Entrar como pai
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Editar Responsável -->
<div id="modalEditarResponsavel" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[92vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 p-6 flex items-center justify-between">
            <h3 class="text-2xl font-bold text-gray-900">Editar Responsável</h3>
            <button onclick="fecharModalEditarResponsavel()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="p-6">
            <form id="formEditarResponsavel" onsubmit="salvarEdicaoResponsavel(event)">
                <input type="hidden" id="resp_edit_aluno_id" name="aluno_id" value="">
                <input type="hidden" id="resp_edit_responsavel_id" name="responsavel_id" value="">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="space-y-5">
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Identificação</h4>
                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <label for="resp_edit_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo *</label>
                                <input type="text" id="resp_edit_nome" name="nome" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Nome completo do responsável">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                            <div>
                                <label for="resp_edit_cpf" class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                                <input type="text" id="resp_edit_cpf" name="cpf" maxlength="14"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="000.000.000-00">
                            </div>
                            <div>
                                <label for="resp_edit_rg" class="block text-sm font-medium text-gray-700 mb-1">RG</label>
                                <input type="text" id="resp_edit_rg" name="rg" maxlength="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="00.000.000-0">
                            </div>
                            <div>
                                <label for="resp_edit_data_nascimento" class="block text-sm font-medium text-gray-700 mb-1">Data de Nascimento</label>
                                <input type="date" id="resp_edit_data_nascimento" name="data_nascimento"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="resp_edit_email" class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                                <input type="email" id="resp_edit_email" name="email"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="email@exemplo.com">
                            </div>
                            <div>
                                <label for="resp_edit_telefone" class="block text-sm font-medium text-gray-700 mb-1">Telefone fixo</label>
                                <input type="text" id="resp_edit_telefone" name="telefone" maxlength="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="(00) 0000-0000">
                            </div>
                            <div>
                                <label for="resp_edit_celular" class="block text-sm font-medium text-gray-700 mb-1">Celular / WhatsApp</label>
                                <input type="text" id="resp_edit_celular" name="celular" maxlength="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="(00) 00000-0000">
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Endereço</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="md:col-span-2">
                                <label for="resp_edit_endereco" class="block text-sm font-medium text-gray-700 mb-1">Logradouro</label>
                                <input type="text" id="resp_edit_endereco" name="endereco" maxlength="255"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Rua, Avenida...">
                            </div>
                            <div>
                                <label for="resp_edit_numero" class="block text-sm font-medium text-gray-700 mb-1">Número</label>
                                <input type="text" id="resp_edit_numero" name="numero" maxlength="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="123">
                            </div>
                            <div>
                                <label for="resp_edit_complemento" class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                                <input type="text" id="resp_edit_complemento" name="complemento" maxlength="100"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Apto, Casa...">
                            </div>
                            <div>
                                <label for="resp_edit_bairro" class="block text-sm font-medium text-gray-700 mb-1">Bairro</label>
                                <input type="text" id="resp_edit_bairro" name="bairro" maxlength="120"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Bairro">
                            </div>
                            <div>
                                <label for="resp_edit_cep" class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                                <input type="text" id="resp_edit_cep" name="cep" maxlength="9"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="00000-000">
                            </div>
                            <div class="md:col-span-2">
                                <label for="resp_edit_cidade" class="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                                <input type="text" id="resp_edit_cidade" name="cidade" maxlength="120"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Cidade">
                            </div>
                            <div>
                                <label for="resp_edit_uf" class="block text-sm font-medium text-gray-700 mb-1">UF</label>
                                <select id="resp_edit_uf" name="uf" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                    <option value="">--</option>
                                    <?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                                    <option value="<?= $uf ?>"><?= $uf ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Acesso ao portal</h4>
                        <div>
                            <label for="resp_edit_senha" class="block text-sm font-medium text-gray-700 mb-1">
                                Nova senha <span class="text-gray-400 font-normal">(deixe em branco para manter a atual)</span>
                            </label>
                            <input type="password" id="resp_edit_senha" name="senha"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                   placeholder="Mínimo 6 caracteres">
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-4">
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" id="resp_edit_financeiro" name="is_financeiro" value="1" class="rounded border-gray-300 text-indigo-600">
                                <span class="text-sm text-gray-700">Responsável financeiro</span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" id="resp_edit_ativo" name="ativo" value="1" class="rounded border-gray-300 text-indigo-600" checked>
                                <span class="text-sm text-gray-700">Ativo</span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-gray-100">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Dados do vínculo</h4>
                        <?php $prefix = 'resp_edit_'; include __DIR__ . '/_responsavel_vinculo_fields.php'; ?>
                    </div>

                    <div>
                        <label for="resp_edit_observacoes" class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                        <textarea id="resp_edit_observacoes" name="observacoes" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 resize-none"
                                  placeholder="Informações adicionais..."></textarea>
                    </div>
                </div>
                <div id="respEditError" class="hidden mt-4 bg-red-100 border border-red-300 text-red-700 px-3 py-2 rounded-lg text-sm"></div>
                <div id="respEditSuccess" class="hidden mt-4 bg-green-100 border border-green-300 text-green-700 px-3 py-2 rounded-lg text-sm"></div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="fecharModalEditarResponsavel()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90">Salvar alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Análise da Tudinha -->
<div id="modalAnalise" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 p-6 flex items-center justify-between">
            <h3 class="text-2xl font-bold text-gray-900">Análise da Tudinha</h3>
            <button onclick="fecharModalAnalise()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div class="p-6">
            <input type="hidden" id="alunoIdAnalise" value="">
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Data limite para análise:
                </label>
                <input type="date" id="dataAte" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500">
                <p class="text-xs text-gray-500 mt-1">A análise considerará todas as atividades até esta data</p>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button onclick="fecharModalAnalise()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancelar
                </button>
                <button id="btnGerarAnalise" onclick="gerarAnalise()" class="px-4 py-2 bg-pink-600 text-white rounded-lg hover:bg-pink-700 transition-colors">
                    Gerar Análise
                </button>
            </div>
            
            <!-- Loading -->
            <div id="loadingAnalise" class="hidden mt-6 text-center py-12">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-pink-600 mb-4"></div>
                <p class="text-gray-600">Gerando análise completa do aluno...</p>
                <p class="text-sm text-gray-500 mt-2">Isso pode levar alguns minutos</p>
            </div>
            
            <!-- Resultado -->
            <div id="resultadoAnalise" class="hidden mt-6">
                <div class="bg-gradient-to-r from-pink-50 to-purple-50 border border-pink-200 rounded-lg p-6">
                    <div class="mb-4">
                        <span class="text-sm text-gray-500" id="dataAnalise"></span>
                    </div>
                    <div id="conteudoAnalise" class="prose max-w-none">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Documento do Aluno -->
<div id="modalDocumentoAluno" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 p-6 flex items-center justify-between">
            <h3 class="text-2xl font-bold text-gray-900">Documento do aluno</h3>
            <button onclick="fecharModalDocumento()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="p-6">
            <form id="formDocumentoAluno" onsubmit="salvarDocumento(event)" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" id="doc_doc_id" name="doc_id" value="">
                <div class="space-y-4">
                    <div>
                        <label for="doc_tipo" class="block text-sm font-medium text-gray-700 mb-2">Tipo de documento</label>
                        <select id="doc_tipo" name="tipo" onchange="docToggleTitulo()" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                            <?php foreach ($docChecklist as $ckTipo => $ckLabel): ?>
                            <option value="<?= htmlspecialchars($ckTipo, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ckLabel, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="doc_titulo_wrap" class="hidden">
                        <label for="doc_titulo" class="block text-sm font-medium text-gray-700 mb-2">Título do documento</label>
                        <input type="text" id="doc_titulo" name="titulo" maxlength="160" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Ex: Declaração de vacinação">
                    </div>
                    <div>
                        <label for="doc_status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select id="doc_status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                            <option value="pendente">Pendente</option>
                            <option value="entregue">Entregue</option>
                            <option value="dispensado">Dispensado</option>
                        </select>
                    </div>
                    <div>
                        <label for="doc_arquivo" class="block text-sm font-medium text-gray-700 mb-2">Arquivo (opcional)</label>
                        <input type="file" id="doc_arquivo" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                               class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="text-xs text-gray-500 mt-1">PDF, imagem ou documento (até 10MB). Anexar marca como entregue.</p>
                    </div>
                    <div>
                        <label for="doc_observacao" class="block text-sm font-medium text-gray-700 mb-2">Observação</label>
                        <input type="text" id="doc_observacao" name="observacao" maxlength="255" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div id="docError" class="hidden mt-4 bg-red-100 border border-red-300 text-red-700 px-3 py-2 rounded-lg text-sm"></div>
                <div id="docSuccess" class="hidden mt-4 bg-green-100 border border-green-300 text-green-700 px-3 py-2 rounded-lg text-sm"></div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="fecharModalDocumento()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="formRemoverDocumento" class="hidden">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
</form>

<!-- Modal de Cadastrar Responsável -->
<div id="modalCadastrarPai" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[92vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900">Cadastrar / Vincular Responsável</h3>
            <button onclick="fecharModalCadastrarPai()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="px-6 py-3 bg-blue-50 border-b border-blue-100 text-sm text-blue-700">
            <i class="fa-solid fa-circle-info mr-1"></i>
            Se o responsável <strong>já tem cadastro</strong> (ex: outro filho na escola), informe o CPF — ele será vinculado automaticamente sem criar novo registro.
        </div>

        <div class="p-6">
            <form id="formCadastrarPai" onsubmit="cadastrarPai(event)">
                <input type="hidden" id="aluno_id_pai" name="aluno_id" value="">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="space-y-5">

                    <!-- Identificação -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Identificação</h4>
                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <label for="pai_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo *</label>
                                <input type="text" id="pai_nome" name="nome" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Nome completo do responsável">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 mt-3">
                            <div>
                                <label for="pai_cpf" class="block text-sm font-medium text-gray-700 mb-1">CPF *</label>
                                <input type="text" id="pai_cpf" name="cpf" required maxlength="14"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="000.000.000-00">
                            </div>
                            <div>
                                <label for="pai_rg" class="block text-sm font-medium text-gray-700 mb-1">RG</label>
                                <input type="text" id="pai_rg" name="rg" maxlength="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="00.000.000-0">
                            </div>
                            <div>
                                <label for="pai_data_nascimento" class="block text-sm font-medium text-gray-700 mb-1">Data de Nascimento</label>
                                <input type="date" id="pai_data_nascimento" name="data_nascimento"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="pai_email" class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                                <input type="email" id="pai_email" name="email"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="email@exemplo.com">
                            </div>
                            <div>
                                <label for="pai_telefone" class="block text-sm font-medium text-gray-700 mb-1">Telefone fixo</label>
                                <input type="text" id="pai_telefone" name="telefone" maxlength="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="(00) 0000-0000">
                            </div>
                            <div>
                                <label for="pai_celular" class="block text-sm font-medium text-gray-700 mb-1">Celular / WhatsApp</label>
                                <input type="text" id="pai_celular" name="celular" maxlength="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="(00) 00000-0000">
                            </div>
                        </div>
                    </div>

                    <!-- Endereço -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Endereço</h4>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2">
                                <label for="pai_endereco" class="block text-sm font-medium text-gray-700 mb-1">Logradouro</label>
                                <input type="text" id="pai_endereco" name="endereco" maxlength="255"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Rua, Avenida...">
                            </div>
                            <div>
                                <label for="pai_numero" class="block text-sm font-medium text-gray-700 mb-1">Número</label>
                                <input type="text" id="pai_numero" name="numero" maxlength="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="123">
                            </div>
                            <div>
                                <label for="pai_complemento" class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                                <input type="text" id="pai_complemento" name="complemento" maxlength="100"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Apto, Casa...">
                            </div>
                            <div>
                                <label for="pai_bairro" class="block text-sm font-medium text-gray-700 mb-1">Bairro</label>
                                <input type="text" id="pai_bairro" name="bairro" maxlength="120"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Bairro">
                            </div>
                            <div>
                                <label for="pai_cep" class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                                <input type="text" id="pai_cep" name="cep" maxlength="9"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="00000-000">
                            </div>
                            <div class="col-span-2">
                                <label for="pai_cidade" class="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                                <input type="text" id="pai_cidade" name="cidade" maxlength="120"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Cidade">
                            </div>
                            <div>
                                <label for="pai_uf" class="block text-sm font-medium text-gray-700 mb-1">UF</label>
                                <select id="pai_uf" name="uf" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="">--</option>
                                    <?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                                    <option value="<?= $uf ?>"><?= $uf ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Responsável financeiro + Senha -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Acesso ao portal</h4>
                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <label for="pai_senha" class="block text-sm font-medium text-gray-700 mb-1">
                                    Senha <span class="text-gray-400 font-normal">(deixe em branco se já tem cadastro)</span>
                                </label>
                                <input type="password" id="pai_senha" name="senha"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Mínimo 6 caracteres">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="is_financeiro" value="1" class="rounded border-gray-300 text-indigo-600">
                                <span class="text-sm text-gray-700 font-medium">Responsável financeiro (recebe cobranças e assina contratos)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Vínculo -->
                    <div class="pt-3 border-t border-gray-100">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Dados do vínculo</h4>
                        <?php $prefix = 'pai_'; include __DIR__ . '/_responsavel_vinculo_fields.php'; ?>
                    </div>

                    <!-- Observações -->
                    <div>
                        <label for="pai_observacoes" class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                        <textarea id="pai_observacoes" name="observacoes" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                                  placeholder="Informações adicionais..."></textarea>
                    </div>
                </div>

                <div id="errorMessagePai" class="hidden mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-sm"></div>
                <div id="successMessagePai" class="hidden mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg text-sm"></div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="fecharModalCadastrarPai()"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="btn-primary-custom px-4 py-2 rounded-lg transition-colors hover:opacity-90">
                        Salvar Responsável
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
