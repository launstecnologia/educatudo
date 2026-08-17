<!-- Etapas Sequenciais da Jornada -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-6">Etapas da Jornada</h2>
    
    <?php if (empty($modulos)): ?>
        <div class="text-center py-12 text-gray-500">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            <p class="text-lg font-medium">Nenhuma etapa disponível</p>
            <p class="text-sm mt-2">O professor ainda não configurou as etapas desta jornada.</p>
        </div>
    <?php else: ?>
        <?php 
        $totalEtapas = count($modulos);
        $etapasConcluidas = 0;
        foreach ($modulos as $m) {
            if ($m['progresso_status'] === 'concluido') {
                $etapasConcluidas++;
            }
        }
        $percentualConcluido = $totalEtapas > 0 ? round(($etapasConcluidas / $totalEtapas) * 100) : 0;
        
        // Determina qual etapa está expandida (se houver)
        $etapaExpandida = isset($_GET['etapa']) ? (int)$_GET['etapa'] : null;
        if ($etapaExpandida !== null && ($etapaExpandida < 0 || $etapaExpandida >= $totalEtapas)) {
            $etapaExpandida = null;
        }
        ?>
        
        <!-- Barra de Progresso Geral -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700">Progresso Geral</span>
                <span class="text-sm text-gray-600"><?= $etapasConcluidas ?> de <?= $totalEtapas ?> concluídas</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="bg-green-500 h-3 rounded-full transition-all duration-300" style="width: <?= $percentualConcluido ?>%"></div>
            </div>
            <p class="text-xs text-gray-500 mt-1"><?= $percentualConcluido ?>% concluído</p>
        </div>
        
        <!-- Lista de Blocos -->
        <div class="space-y-4">
            <?php foreach ($modulos as $index => $modulo): ?>
                <?php 
                $numeroEtapa = $index + 1;
                $estaConcluida = $modulo['progresso_status'] === 'concluido';
                $estaExpandida = $etapaExpandida === $index;
                $podeAcessar = true;
                
                // Verifica se pode acessar (se todas as anteriores estão concluídas ou se é a primeira)
                // Em preview (professor), todas as etapas ficam acessíveis para poder avançar e visualizar qualquer uma
                if (empty($preview)) {
                    if ($index > 0) {
                        $todasAnterioresConcluidas = true;
                        for ($i = 0; $i < $index; $i++) {
                            if ($modulos[$i]['progresso_status'] !== 'concluido') {
                                $todasAnterioresConcluidas = false;
                                break;
                            }
                        }
                        $podeAcessar = $todasAnterioresConcluidas;
                    }
                }
                
                // Determina o status visual
                $statusClass = '';
                $statusIcon = '';
                $statusText = '';
                
                if ($estaConcluida) {
                    $statusClass = 'bg-green-50 border-green-300';
                    $statusIcon = '✓';
                    $statusText = 'Concluída';
                } elseif ($podeAcessar) {
                    $statusClass = 'bg-blue-50 border-blue-300';
                    $statusIcon = '→';
                    $statusText = 'Disponível';
                } else {
                    $statusClass = 'bg-gray-50 border-gray-200';
                    $statusIcon = '🔒';
                    $statusText = 'Bloqueada';
                }
                
                // Tipo do módulo
                $tiposNomes = [
                    'video' => '📚 Conteúdo',
                    'conteudo' => '📚 Conteúdo',
                    'resumo_aluno' => '📝 Resumo',
                    'exercicios' => '✅ Exercícios',
                    'exercicio' => '✅ Exercícios',
                    'dica_professor' => '💡 Dica do Professor'
                ];
                $tipoNome = $tiposNomes[$modulo['tipo_modulo']] ?? 'Módulo';
                ?>
                
                <!-- Card do Bloco -->
                <div class="bloco-card border-2 rounded-xl transition-all <?= $statusClass ?> <?= $estaExpandida ? 'shadow-lg' : 'hover:shadow-md' ?>" 
                     data-etapa="<?= $index ?>" data-modulo-id="<?= $modulo['id'] ?>">
                    <!-- Cabeçalho do Bloco (sempre visível) -->
                    <div class="p-4 cursor-pointer" onclick="toggleEtapa(<?= $index ?>)">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4 flex-1">
                                <!-- Número e Status -->
                                <div class="flex-shrink-0">
                                    <?php if ($estaConcluida): ?>
                                        <div class="w-12 h-12 rounded-full bg-green-500 flex items-center justify-center shadow-md">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                    <?php elseif ($podeAcessar): ?>
                                        <div class="w-12 h-12 rounded-full bg-blue-500 flex items-center justify-center shadow-md">
                                            <span class="text-white font-bold text-lg"><?= $numeroEtapa ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-12 h-12 rounded-full bg-gray-400 flex items-center justify-center shadow-md">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Informações do Bloco -->
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-1">
                                        <h3 class="text-lg font-bold text-gray-900"><?= htmlspecialchars($modulo['titulo']) ?></h3>
                                        <?php if ($modulo['obrigatorio']): ?>
                                            <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">Obrigatório</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center space-x-3 text-sm text-gray-600">
                                        <span><?= $tipoNome ?></span>
                                        <span>•</span>
                                        <span class="font-medium <?= $estaConcluida ? 'text-green-700' : ($podeAcessar ? 'text-blue-700' : 'text-gray-500') ?>">
                                            <?= $statusText ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ícone de Expandir/Recolher -->
                            <div class="ml-4">
                                <svg class="w-6 h-6 text-gray-400 transition-transform <?= $estaExpandida ? 'transform rotate-180' : '' ?>" 
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conteúdo Expandido do Bloco -->
                    <div class="etapa-conteudo <?= $estaExpandida ? '' : 'hidden' ?> px-4 pb-4">
                        <div class="pt-4 border-t border-gray-200">
                            <?php if (!$podeAcessar): ?>
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                                    <p class="text-sm text-yellow-800">
                                        <strong>⚠️ Bloqueado:</strong> Complete as etapas anteriores para desbloquear este bloco.
                                    </p>
                                </div>
                            <?php else: ?>
                                <?php 
                                try {
                                    // Variáveis disponíveis para o arquivo incluído
                                    $moduloAtual = $modulo;
                                    $estaConcluidaAtual = $estaConcluida;
                                    // Garantir que $jornada está disponível (já deve estar no escopo, mas vamos garantir)
                                    if (!isset($jornada)) {
                                        // Tentar buscar do escopo global
                                        global $jornada;
                                    }
                                    include 'etapa-conteudo.php'; 
                                } catch (Exception $e) {
                                    error_log("Erro ao renderizar módulo {$modulo['id']}: " . $e->getMessage());
                                    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4">';
                                    echo '<p class="text-red-800 text-sm">Erro ao carregar conteúdo deste módulo. Por favor, tente novamente.</p>';
                                    echo '</div>';
                                } catch (Error $e) {
                                    error_log("Erro fatal ao renderizar módulo {$modulo['id']}: " . $e->getMessage());
                                    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4">';
                                    echo '<p class="text-red-800 text-sm">Erro ao carregar conteúdo deste módulo. Por favor, tente novamente.</p>';
                                    echo '</div>';
                                }
                                ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Overlay de loading para envios da jornada (resumo, finalizar etapa, etc.) -->
<div id="jornada-enviando-overlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[100] flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full mx-4 p-8 text-center">
        <div class="w-16 h-16 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
        <p id="jornada-enviando-texto" class="text-lg font-semibold text-gray-800">Enviando...</p>
        <p class="text-sm text-gray-500 mt-1">Não feche a página</p>
    </div>
</div>

<script>
function showJornadaEnviando(texto) {
    var el = document.getElementById('jornada-enviando-overlay');
    var txt = document.getElementById('jornada-enviando-texto');
    if (el) el.classList.remove('hidden');
    if (txt && texto) txt.textContent = texto;
}
function hideJornadaEnviando() {
    var el = document.getElementById('jornada-enviando-overlay');
    if (el) el.classList.add('hidden');
}
// Função para expandir/recolher etapa
function toggleEtapa(index) {
    // Se já está expandida, apenas recolhe
    const card = document.querySelector(`.bloco-card[data-etapa="${index}"]`);
    const conteudo = card.querySelector('.etapa-conteudo');
    const icon = card.querySelector('svg');
    
    if (conteudo.classList.contains('hidden')) {
        // Recolhe todas as outras etapas
        document.querySelectorAll('.etapa-conteudo').forEach(el => {
            el.classList.add('hidden');
        });
        document.querySelectorAll('.bloco-card svg').forEach(el => {
            el.classList.remove('transform', 'rotate-180');
        });
        
        // Expande esta etapa
        conteudo.classList.remove('hidden');
        icon.classList.add('transform', 'rotate-180');
        
        // Atualiza URL sem recarregar a página
        const url = new URL(window.location.href);
        url.searchParams.set('etapa', index);
        window.history.pushState({}, '', url);
        
        // Início da etapa é registrado ao clicar em "Iniciar etapa" no conteúdo (não ao expandir)
    } else {
        // Recolhe esta etapa
        conteudo.classList.add('hidden');
        icon.classList.remove('transform', 'rotate-180');
        
        // Remove parâmetro da URL
        const url = new URL(window.location.href);
        url.searchParams.delete('etapa');
        window.history.pushState({}, '', url);
    }
}

// Modo preview (professor vendo jornada como aluno) — envia preview=1 para não persistir no banco
var jornadaPreview = <?= isset($preview) && $preview ? 'true' : 'false' ?>;

// Salva o início da etapa no servidor
function salvarInicioEtapa(moduloId) {
    const formData = new FormData();
    formData.append('modulo_id', moduloId);
    formData.append('acao', 'iniciar');
    if (jornadaPreview) formData.append('preview', '1');
    
    fetch('<?= URL ?>/jornadas/salvar-tempo-etapa', {
        method: 'POST',
        body: formData
    })
    .then(async function(response) {
        const textResponse = await response.text();
        let data = null;
        try {
            data = textResponse ? JSON.parse(textResponse) : null;
        } catch (error) {
            throw new Error('O servidor retornou uma resposta inválida. Recarregue a página e tente novamente.');
        }
        if (!response.ok) {
            throw new Error(data && data.error ? data.error : 'Não foi possível iniciar a etapa.');
        }
        return data;
    })
    .then(data => {
        if (data && data.success) {
            console.log('Início da etapa registrado');
        } else {
            alert(data && data.error ? data.error : 'Não foi possível iniciar a etapa.');
        }
    })
    .catch(error => {
        console.error('Erro ao salvar início da etapa:', error);
        alert(error && error.message ? error.message : 'Erro ao iniciar etapa. Tente novamente.');
    });
}

// Tempo de início por módulo (para contabilizar tempo ao finalizar)
window.tempoEtapaPorModulo = window.tempoEtapaPorModulo || {};
window.tempoEtapaIntervalPorModulo = window.tempoEtapaIntervalPorModulo || {};

function iniciarEtapa(moduloId) {
    var wrap = document.getElementById('iniciar-etapa-wrap-' + moduloId);
    var btn = wrap ? wrap.querySelector('button') : null;
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="inline-block w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2 align-middle"></span> Iniciando...';
    }
    showJornadaEnviando('Iniciando etapa...');
    const formData = new FormData();
    formData.append('modulo_id', moduloId);
    formData.append('acao', 'iniciar');
    if (jornadaPreview) formData.append('preview', '1');
    fetch('<?= URL ?>/jornadas/salvar-tempo-etapa', { method: 'POST', body: formData })
        .then(async function(response) {
            const textResponse = await response.text();
            let data = null;
            try {
                data = textResponse ? JSON.parse(textResponse) : null;
            } catch (error) {
                throw new Error('O servidor retornou uma resposta inválida. Recarregue a página e tente novamente.');
            }
            if (!response.ok) {
                throw new Error(data && data.error ? data.error : 'Não foi possível iniciar a etapa.');
            }
            return data;
        })
        .then(data => {
            hideJornadaEnviando();
            if (data && data.success) {
                if (wrap) wrap.classList.add('hidden');
                var conteudo = document.getElementById('conteudo-etapa-real-' + moduloId);
                if (conteudo) conteudo.classList.remove('hidden');
                window.tempoEtapaPorModulo[moduloId] = Date.now();
                const elTimer = document.getElementById('timer-etapa-' + moduloId);
                if (elTimer) {
                    window.tempoEtapaIntervalPorModulo[moduloId] = setInterval(function() {
                        var seg = Math.floor((Date.now() - window.tempoEtapaPorModulo[moduloId]) / 1000);
                        var m = Math.floor(seg / 60);
                        var s = seg % 60;
                        elTimer.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                    }, 1000);
                }
            } else if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Iniciar etapa';
            }
            if (!(data && data.success)) {
                alert(data && data.error ? data.error : 'Não foi possível iniciar a etapa.');
            }
        })
        .catch(function(err) {
            console.error(err);
            hideJornadaEnviando();
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Iniciar etapa';
            }
            alert(err && err.message ? err.message : 'Erro ao iniciar etapa. Tente novamente.');
        });
}

function finalizarEtapa(moduloId, tipo) {
    if (!confirm('Tem certeza que finalizou esta etapa? Você poderá continuar para a próxima.')) {
        return;
    }
    showJornadaEnviando('Finalizando etapa...');
    document.querySelectorAll('.js-btn-finalizar').forEach(function(b) { b.disabled = true; });
    if (window.tempoEtapaIntervalPorModulo[moduloId]) {
        clearInterval(window.tempoEtapaIntervalPorModulo[moduloId]);
        delete window.tempoEtapaIntervalPorModulo[moduloId];
    }
    var tempoGasto = 0;
    if (window.tempoEtapaPorModulo[moduloId]) {
        tempoGasto = Math.floor((Date.now() - window.tempoEtapaPorModulo[moduloId]) / 1000);
    }
    const formData = new FormData();
    formData.append('modulo_id', moduloId);
    formData.append('tipo', tipo);
    formData.append('tempo_gasto', tempoGasto);
    if (jornadaPreview) formData.append('preview', '1');
    fetch('<?= URL ?>/jornadas/finalizar-etapa', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Etapa finalizada com sucesso!');
            location.reload();
        } else {
            hideJornadaEnviando();
            document.querySelectorAll('.js-btn-finalizar').forEach(function(b) { b.disabled = false; });
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(function(error) {
        hideJornadaEnviando();
        document.querySelectorAll('.js-btn-finalizar').forEach(function(b) { b.disabled = false; });
        alert('Erro de conexão');
        console.error(error);
    });
}

// Enter no resumo: apenas pula linha (não envia o formulário)
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.js-resumo-textarea').forEach(function(ta) {
        ta.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var start = this.selectionStart, end = this.selectionEnd, val = this.value;
                this.value = val.slice(0, start) + '\n' + val.slice(end);
                this.selectionStart = this.selectionEnd = start + 1;
            }
        });
    });
});

function getResumoConteudo(moduloId) {
    var ta = document.getElementById('textarea-resumo-' + moduloId);
    return ta ? (ta.value || '') : '';
}

// Função para enviar resumo
function enviarResumo(moduloId, tipoModulo) {
    var conteudo = getResumoConteudo(moduloId);
    if (!conteudo.trim()) {
        alert('Por favor, escreva um resumo antes de continuar.');
        return;
    }
    var btn = document.getElementById('btn-enviar-resumo-' + moduloId);
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="inline-block w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin mr-2 align-middle"></span> Enviando resumo...';
    }
    showJornadaEnviando('Enviando resumo...');
    
    const formData = new FormData();
    formData.append('modulo_id', moduloId);
    formData.append('jornada_id', '<?= $jornada['id'] ?>');
    formData.append('resumo', conteudo);
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        formData.append('_token', csrfToken.getAttribute('content'));
    }
    
    fetch('<?= URL ?>/jornadas/enviar-resumo', {
        method: 'POST',
        body: formData,
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
    .then(async function(response) {
        const textResponse = await response.clone().text();
        if (textResponse.trim().indexOf('<') === 0) {
            var msg = 'O servidor retornou HTML em vez de JSON.';
            if (response.status === 401) msg += ' Sessão pode ter expirado.';
            throw new Error(msg);
        }
        var ct = response.headers.get('content-type') || '';
        if (ct.indexOf('application/json') === -1) {
            if (response.status === 401) {
                throw new Error('Sessão expirada. Faça login novamente.');
            }
            throw new Error('Content-Type não é JSON');
        }
        var data = JSON.parse(textResponse);
        return { data: data, status: response.status };
    })
    .then(function(result) {
        var data = result.data;
        if (result.status === 401) {
            hideJornadaEnviando();
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Enviar Resumo';
            }
            alert(data && data.error ? data.error : 'Sessão expirada. Faça login novamente.');
            window.location.href = '<?= URL ?>/';
            return;
        }
        if (data && data.success) {
            alert('Resumo enviado com sucesso!');
            location.reload();
        } else {
            hideJornadaEnviando();
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Enviar Resumo';
            }
            alert('Erro: ' + (data && data.error ? data.error : 'Erro desconhecido'));
        }
    })
    .catch(function(error) {
        console.error('Erro ao enviar resumo:', error);
        hideJornadaEnviando();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Enviar Resumo';
        }
        alert(error && error.message ? error.message : 'Erro ao enviar resumo. Tente novamente.');
    });
}
</script>
