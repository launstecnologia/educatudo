<?php
$bloco = $bloco ?? null;
$provas = $provas ?? [];
$todas_finalizadas = $todas_finalizadas ?? false;
$blocoId = $bloco ? (int)$bloco['id'] : 0;
$acessibilidade_hide_timer = $acessibilidade_hide_timer ?? false;
$acessibilidade_relax_secure = $acessibilidade_relax_secure ?? false;
$blocoTerminoIso = null;
if ($bloco && !empty($bloco['data_prova']) && !empty($bloco['hora_fim'])) {
    $blocoTerminoIso = $bloco['data_prova'] . ' ' . $bloco['hora_fim'];
}
?>
<!-- Modal de aviso (evita alert() e sair do fullscreen) -->
<div id="modal-aviso" class="fixed inset-0 z-[10000] hidden items-center justify-center bg-black/60 p-4" style="display: none;">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 text-center">
        <p id="modal-aviso-titulo" class="text-lg font-semibold text-gray-900 mb-2"></p>
        <p id="modal-aviso-msg" class="text-gray-600 mb-6"></p>
        <button type="button" id="modal-aviso-ok" class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700">OK</button>
    </div>
</div>

<!-- Cronômetro fixo: término do bloco (não zera ao trocar de matéria) -->
<?php if ($blocoTerminoIso): ?>
<div id="cronometro-bloco" class="fixed top-4 right-4 z-[8500] bg-indigo-700 text-white px-4 py-2 rounded-xl shadow-lg font-mono text-lg font-bold" style="display: none;">
    <span id="cronometro-texto">--:--</span>
    <p class="text-xs font-normal opacity-90 mt-0.5">Término às <?= date('H:i', strtotime($bloco['hora_fim'])) ?></p>
</div>
<?php endif; ?>

<div id="overlay-tela-cheia" class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-gray-900 text-white p-8 text-center">
    <?php if ($acessibilidade_relax_secure): ?>
    <p class="text-xl font-semibold mb-2">Prova com recursos de acessibilidade</p>
    <p class="text-gray-300 mb-6 max-w-md">Para uma melhor experiência de leitura, entre em tela cheia. Você pode sair quando quiser pressionando a tecla <strong>Esc</strong> — sua prova não será cancelada.</p>
    <?php else: ?>
    <p class="text-xl font-semibold mb-2">Modo prova segura</p>
    <p class="text-gray-300 mb-4 max-w-md">Para continuar, entre em tela cheia. Você só poderá sair ao finalizar todas as provas e clicar em &quot;Sair do modo prova&quot;.</p>
    <p class="text-amber-300 text-sm max-w-lg mb-6 font-medium">Atenção: ao clicar em "Entrar em tela cheia", você só poderá sair ao <strong>finalizar todas as provas</strong> e clicar em <strong>"Sair do modo prova"</strong>. A tecla Esc e outras formas de sair serão ignoradas até lá.</p>
    <?php endif; ?>
    <div class="flex flex-col sm:flex-row gap-3 items-center justify-center">
        <button type="button" id="btn-entrar-tela-cheia" class="px-8 py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-lg">
            Entrar em tela cheia
        </button>
        <a href="<?= URL ?>/aluno/provas" id="link-sair-antes-iniciar" class="px-6 py-3 bg-gray-600 hover:bg-gray-500 text-white font-semibold rounded-xl text-base border border-gray-500">
            Sair da prova
        </a>
    </div>
</div>
<!-- Tela única: seleção de matéria (fica em tela cheia; prova abre no iframe abaixo) -->
<div id="conteudo-prova-segura" class="min-h-screen flex flex-col items-center justify-center p-6">
    <div class="w-full max-w-2xl">
        <h1 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($bloco['titulo'] ?? 'Bloco de Provas') ?></h1>
        <p class="text-gray-600 mb-6">Modo prova segura: escolha a matéria abaixo. A prova abre na mesma tela; ao finalizar, você volta aqui para escolher outra.</p>

        <div id="lista-materias-container">
        <?php if ($todas_finalizadas): ?>
            <?php $bloco_terminou = $bloco_terminou ?? false; ?>
            <div class="bg-green-50 border border-green-200 rounded-xl p-6 mb-6">
                <p class="text-green-800 font-medium mb-4">Todas as provas deste bloco foram finalizadas.</p>
                <?php if (!$bloco_terminou && !empty($bloco['hora_fim'])): ?>
                    <p class="text-amber-800 text-sm mb-4">Os resultados estarão disponíveis após o término do horário do bloco (<?= date('H:i', strtotime($bloco['hora_fim'])) ?>). Até lá, você pode sair do modo prova.</p>
                <?php endif; ?>
                <div class="flex flex-col sm:flex-row gap-3">
                    <?php if ($bloco_terminou): ?>
                    <a href="<?= URL ?>/aluno/provas/bloco/<?= $blocoId ?>/resultados" 
                       class="flex-1 text-center bg-green-600 text-white font-semibold py-3 px-6 rounded-lg hover:bg-green-700 link-saida-permitida">
                        Ver Resultados do Bloco
                    </a>
                    <?php endif; ?>
                    <button type="button" id="btn-sair-prova-segura" 
                            class="flex-1 bg-gray-600 text-white font-semibold py-3 px-6 rounded-lg hover:bg-gray-700">
                        Sair do modo prova
                    </button>
                </div>
            </div>
        <?php else: ?>
            <p class="text-sm font-semibold text-gray-700 mb-4">Escolha a matéria para iniciar:</p>
            <div class="space-y-3">
                <?php foreach ($provas as $prova): ?>
                    <?php 
                    $finalizada = isset($prova['realizacao_status']) && $prova['realizacao_status'] === 'finalizado'; 
                    $cancelada = isset($prova['realizacao_status']) && $prova['realizacao_status'] === 'cancelada';
                    ?>
                    <div class="bg-white rounded-xl border-2 border-gray-200 p-4 flex items-center justify-between">
                        <div>
                            <span class="font-medium text-gray-900"><?= htmlspecialchars($prova['materia_nome'] ?? $prova['titulo']) ?></span>
                            <p class="text-sm text-gray-500 mt-0.5">Professor: <?= !empty($prova['professor_nome']) ? htmlspecialchars($prova['professor_nome']) : '—' ?></p>
                            <?php if ($finalizada): ?>
                                <span class="ml-2 text-green-600 text-sm font-semibold">✓ Finalizada</span>
                            <?php elseif ($cancelada): ?>
                                <span class="ml-2 text-amber-600 text-sm font-semibold">Cancelada – aguarde liberação do coordenador</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($finalizada): ?>
                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-lg text-sm">Concluída</span>
                        <?php elseif ($cancelada): ?>
                            <span class="px-3 py-1 bg-amber-100 text-amber-800 rounded-lg text-sm">Cancelada</span>
                        <?php else: ?>
                            <button type="button" class="btn-iniciar-materia px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700" data-prova-id="<?= (int)$prova['id'] ?>">
                                <?= (isset($prova['realizacao_status']) && $prova['realizacao_status'] === 'iniciado') ? 'Continuar' : 'Iniciar' ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<!-- Iframe: prova abre aqui na mesma tela (sem sair da tela cheia) -->
<div id="tela-iframe-prova" class="fixed inset-0 z-[8000] bg-white" style="display: none;">
    <iframe id="iframe-prova" src="about:blank" class="w-full h-full border-0" title="Prova"></iframe>
    <div id="overlay-carregando-materia" class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-white" style="display: none;" aria-live="polite">
        <div class="animate-spin rounded-full h-12 w-12 border-4 border-indigo-200 border-t-indigo-600 mb-4"></div>
        <p class="text-gray-700 font-medium">Carregando matéria...</p>
        <p class="text-sm text-gray-500 mt-1">Aguarde um momento.</p>
    </div>
</div>

<script>
(function() {
    var permitirSair = false;
    var saiuFullscreenContagem = 0;
    var cancelamentoIniciado = false;
    var cancelamentoServidorEnviado = false;
    var entrouFullscreenUmaVez = false;
    var ignorarVisibilityAte = 0;
    var transicaoMateria = false;
    var aguardandoProximaMateria = false;
    var cargaIframeSeq = 0;
    var blocoIdCancelar = <?= (int)$blocoId ?>;
    // EducaInclui: acomodações do aluno (resolvidas no controller)
    var ocultarCronometro = <?= !empty($acessibilidade_hide_timer) ? 'true' : 'false' ?>;
    var modoSeguroAtivo = <?= !empty($acessibilidade_relax_secure) ? 'false' : 'true' ?>;
    var ultimaAcaoSeguranca = { motivo: '', em: 0 };

    function registrarAcaoSeguranca(motivo) {
        if (!motivo) return;
        ultimaAcaoSeguranca = { motivo: String(motivo), em: Date.now() };
    }
    // O iframe da prova chama isso de forma síncrona (mesma origem) para o Esc
    // chegar ao pai antes do evento fullscreenchange.
    window.registrarAcaoSeguranca = registrarAcaoSeguranca;

    function motivoSaidaRecente(fallback) {
        if (ultimaAcaoSeguranca.motivo && (Date.now() - ultimaAcaoSeguranca.em) < 2000) {
            return ultimaAcaoSeguranca.motivo;
        }
        return fallback || 'desconhecido';
    }

    function rotuloMotivoSaida(motivo) {
        var mapa = {
            tecla_esc: 'pressionou a tecla Esc',
            tecla_f11: 'pressionou a tecla F11',
            atalho_fechar_aba: 'usou o atalho para fechar a aba (Ctrl/Cmd+W)',
            atalho_nova_aba: 'usou o atalho para abrir nova aba (Ctrl/Cmd+T)',
            aba_trocada: 'trocou de aba ou minimizou a janela',
            aba_fechada: 'fechou a aba ou saiu da página',
            janela_perdeu_foco: 'a janela perdeu o foco (outro aplicativo na frente)',
            saiu_tela_cheia: 'saiu da tela cheia (Esc, botão do navegador ou gesto)',
            timeout_aviso_tela_cheia: 'não voltou à tela cheia após o aviso de 10 segundos',
            segunda_saida_tela_cheia: 'saiu da tela cheia pela segunda vez',
            botao_voltar: 'usou o botão Voltar do navegador',
            desconhecido: 'motivo não identificado pelo navegador'
        };
        return mapa[motivo] || mapa.desconhecido;
    }

    function registrarLogSeguranca(tipoEvento, detalhe) {
        try {
            var payload = JSON.stringify({
                tipo_evento: tipoEvento,
                bloco_id: blocoIdCancelar,
                detalhe: detalhe || ''
            });
            var urlLog = '<?= URL ?>/aluno/provas/log-evento';
            if (navigator.sendBeacon) {
                navigator.sendBeacon(urlLog, new Blob([payload], { type: 'application/json' }));
            } else {
                fetch(urlLog, {
                    method: 'POST',
                    body: payload,
                    headers: { 'Content-Type': 'application/json' },
                    keepalive: true,
                    credentials: 'same-origin'
                }).catch(function() {});
            }
        } catch (e) {}
    }

    function capturarAcaoTeclado(e) {
        var mod = e.ctrlKey || e.metaKey;
        if (e.key === 'Escape') registrarAcaoSeguranca('tecla_esc');
        else if (e.key === 'F11') registrarAcaoSeguranca('tecla_f11');
        else if (mod && (e.key === 'w' || e.key === 'W')) registrarAcaoSeguranca('atalho_fechar_aba');
        else if (mod && (e.key === 't' || e.key === 'T')) registrarAcaoSeguranca('atalho_nova_aba');
    }
    document.addEventListener('keydown', capturarAcaoTeclado, true);

    function fecharIframeProva() {
        try {
            var iframeProva = document.getElementById('iframe-prova');
            if (iframeProva && iframeProva.contentWindow) {
                iframeProva.contentWindow.postMessage({ tipo: 'prova_cancelada_bloco' }, '*');
            }
        } catch (e) {}
        var iframe = document.getElementById('iframe-prova');
        var tela = document.getElementById('tela-iframe-prova');
        if (iframe) iframe.src = 'about:blank';
        if (tela) {
            tela.style.display = 'none';
            tela.style.pointerEvents = 'none';
        }
        esconderOverlayCarregandoMateria();
        var conteudo = document.getElementById('conteudo-prova-segura');
        if (conteudo) conteudo.style.display = 'flex';
    }

    function cancelarBlocoSeguroAgora(motivo, origem) {
        motivo = motivo || motivoSaidaRecente('desconhecido');
        registrarAcaoSeguranca(motivo);
        fecharIframeProva();
        var url = '<?= URL ?>/aluno/provas/bloco/' + blocoIdCancelar + '/cancelar-seguro';
        if (cancelamentoServidorEnviado) {
            return Promise.resolve(true);
        }
        cancelamentoServidorEnviado = true;
        origem = origem || '';
        var qs = 'motivo=' + encodeURIComponent(motivo) + '&_=' + Date.now();
        if (origem) {
            qs += '&origem=' + encodeURIComponent(origem);
        }
        var corpoObj = { motivo: motivo };
        if (origem) {
            corpoObj.origem = origem;
        }
        var corpo = JSON.stringify(corpoObj);

        try {
            var img = new Image();
            img.src = url + '?' + qs;
        } catch (e) {}

        try {
            if (navigator.sendBeacon) {
                navigator.sendBeacon(url, new Blob([corpo], { type: 'application/json' }));
            }
        } catch (e) {}

        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: corpo
        }).then(function(r) {
            if (r.ok) return true;
            return fetch(url + '?' + qs, {
                method: 'GET',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function(r2) { return r2.ok; });
        }).catch(function() {
            return fetch(url + '?' + qs, {
                method: 'GET',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function(r) { return r.ok; }).catch(function() { return false; });
        });
    }

    window.mostrarModal = function(titulo, msg) {
        var el = document.getElementById('modal-aviso');
        if (!el) return;
        document.getElementById('modal-aviso-titulo').textContent = titulo || 'Aviso';
        document.getElementById('modal-aviso-msg').textContent = msg || '';
        document.getElementById('modal-aviso-ok').textContent = 'OK';
        el.style.display = 'flex';
        el.classList.remove('hidden');
        document.getElementById('modal-aviso-ok').onclick = function() {
            el.style.display = 'none';
            el.classList.add('hidden');
        };
    };

    var timerPrimeiraSaida = null;
    function mostrarModalPrimeiraSaida() {
        var el = document.getElementById('modal-aviso');
        if (!el) return;
        if (timerPrimeiraSaida) {
            clearInterval(timerPrimeiraSaida);
            timerPrimeiraSaida = null;
        }
        document.getElementById('modal-aviso-titulo').textContent = 'Atenção';
        document.getElementById('modal-aviso-msg').innerHTML = 'Volte para tela cheia em <strong id="countdown-segundos">10</strong> segundos ou sua prova será cancelada.';
        document.getElementById('modal-aviso-ok').textContent = 'Entrar em tela cheia agora';
        el.style.display = 'flex';
        el.classList.remove('hidden');
        var segundos = 10;
        function cancelarPorTimeout() {
            if (timerPrimeiraSaida) {
                clearInterval(timerPrimeiraSaida);
                timerPrimeiraSaida = null;
            }
            cancelamentoIniciado = true;
            fecharIframeProva();
            cancelarBlocoSeguroAgora('timeout_aviso_tela_cheia', window._motivoPrimeiraSaidaSeguro || '').finally(function() {
                permitirSair = true;
                window.location.href = '<?= URL ?>/aluno/provas?cancelar_bloco=' + blocoIdCancelar;
            });
        }
        document.getElementById('modal-aviso-ok').onclick = function() {
            if (timerPrimeiraSaida) {
                clearInterval(timerPrimeiraSaida);
                timerPrimeiraSaida = null;
            }
            el.style.display = 'none';
            el.classList.add('hidden');
            entrarFullscreen();
        };
        timerPrimeiraSaida = setInterval(function() {
            segundos--;
            var span = document.getElementById('countdown-segundos');
            if (span) span.textContent = segundos;
            if (segundos <= 0) {
                cancelarPorTimeout();
            }
        }, 1000);
    }

    function mostrarModalCancelarProva() {
        var motivo = motivoSaidaRecente('segunda_saida_tela_cheia');
        cancelamentoIniciado = true;
        fecharIframeProva();
        cancelarBlocoSeguroAgora(motivo);
        var el = document.getElementById('modal-aviso');
        if (!el) return;
        document.getElementById('modal-aviso-titulo').textContent = 'Prova cancelada';
        document.getElementById('modal-aviso-msg').textContent = 'Sua prova foi cancelada por saída do modo seguro. Apenas o coordenador pode liberar nova tentativa. Você será redirecionado para Minhas Provas.';
        document.getElementById('modal-aviso-ok').textContent = 'OK';
        el.style.display = 'flex';
        el.classList.remove('hidden');
        document.getElementById('modal-aviso-ok').onclick = function() {
            permitirSair = true;
            window.location.href = '<?= URL ?>/aluno/provas?cancelar_bloco=' + blocoIdCancelar;
        };
    }

    <?php if ($blocoTerminoIso): ?>
    var blocoTermino = new Date('<?= date('Y-m-d\TH:i:s', strtotime($blocoTerminoIso)) ?>'.replace(/-(\d{2})-(\d{2})T/, '-$1-$2T'));
    var blocoTerminoRefreshFeito = false;
    function atualizarCronometro() {
        var el = document.getElementById('cronometro-bloco');
        if (!el) return;
        var agora = new Date();
        if (agora >= blocoTermino) {
            el.querySelector('#cronometro-texto').textContent = '00:00';
            if (!blocoTerminoRefreshFeito && document.getElementById('lista-materias-container')) {
                blocoTerminoRefreshFeito = true;
                fetch('<?= URL ?>/aluno/provas/bloco/<?= (int)$blocoId ?>/iniciar-seguro?partial=1')
                    .then(function(r) { return r.text(); })
                    .then(function(html) {
                        var c = document.getElementById('lista-materias-container');
                        if (c) c.innerHTML = html;
                    })
                    .catch(function() {});
            }
            return;
        }
        var seg = Math.floor((blocoTermino - agora) / 1000);
        var m = Math.floor(seg / 60);
        var s = seg % 60;
        el.querySelector('#cronometro-texto').textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }
    function mostrarCronometro() {
        var el = document.getElementById('cronometro-bloco');
        if (!el) return;
        // hide_timer: mantém o elemento no DOM (auto-refresh no fim do bloco) porém oculto
        atualizarCronometro();
        if (ocultarCronometro) { el.style.display = 'none'; return; }
        el.style.display = 'block';
    }
    setInterval(atualizarCronometro, 1000);
    <?php endif; ?>

    function entrarFullscreen() {
        var el = document.documentElement;
        if (el.requestFullscreen) el.requestFullscreen();
        else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
        else if (el.msRequestFullscreen) el.msRequestFullscreen();
    }

    function sairFullscreen() {
        if (document.exitFullscreen) document.exitFullscreen();
        else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
        else if (document.msExitFullscreen) document.msExitFullscreen();
    }

    function antiCola() {
        var mod = function(e) { return e.ctrlKey || e.metaKey; };
        document.addEventListener('contextmenu', function(e) { e.preventDefault(); return false; });
        document.addEventListener('copy', function(e) { e.preventDefault(); });
        document.addEventListener('cut', function(e) { e.preventDefault(); });
        document.addEventListener('paste', function(e) { e.preventDefault(); });
        document.addEventListener('keydown', function(e) {
            capturarAcaoTeclado(e);
            if (modoSeguroAtivo && estaEmFullscreen() && !permitirSair) {
                var el = document.activeElement;
                var tag = el && el.tagName ? el.tagName.toLowerCase() : '';
                var allowKey = false;
                if (el && (tag === 'input' || tag === 'textarea' || tag === 'select')) {
                    allowKey = true;
                }
                if (!allowKey && el && (tag === 'button' || tag === 'a')) {
                    var id = el.id || '';
                    var cls = el.className || '';
                    if (id === 'btn-entrar-tela-cheia' || id === 'btn-sair-prova-segura' || id === 'modal-aviso-ok' || (cls && cls.indexOf('btn-iniciar-materia') !== -1)) {
                        if (e.key === 'Enter' || e.key === ' ') allowKey = true;
                    }
                }
                if (!allowKey && el && el.closest && el.closest('#modal-aviso')) allowKey = true;
                if (!allowKey) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }
            if (e.key === 'F12') { e.preventDefault(); return false; }
            if (mod(e) && (e.key === 't' || e.key === 'T')) { e.preventDefault(); return false; }
            if (mod(e) && (e.key === 'n' || e.key === 'N')) { e.preventDefault(); return false; }
            if (mod(e) && (e.key === 'w' || e.key === 'W')) { e.preventDefault(); return false; }
            if (mod(e) && e.shiftKey && (e.key === 'n' || e.key === 'N')) { e.preventDefault(); return false; }
            if (mod(e) && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C')) { e.preventDefault(); return false; }
            if (mod(e) && (e.key === 'u' || e.key === 'U')) { e.preventDefault(); return false; }
            if (mod(e) && (e.key === 'c' || e.key === 'C' || e.key === 'v' || e.key === 'V' || e.key === 'x' || e.key === 'X')) { e.preventDefault(); return false; }
        }, true);
    }

    function estaEmFullscreen() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
    }

    function iniciarTransicaoMateria(ms) {
        transicaoMateria = true;
        ignorarVisibilityAte = Date.now() + (ms || 2000);
        setTimeout(function() {
            if (aguardandoProximaMateria) return;
            transicaoMateria = false;
            if (!permitirSair && modoSeguroAtivo && !estaEmFullscreen()) {
                mostrarOverlayTelaCheia();
            }
        }, ms || 2000);
    }

    function mostrarOverlayCarregandoMateria() {
        var el = document.getElementById('overlay-carregando-materia');
        if (el) el.style.display = 'flex';
        var iframe = document.getElementById('iframe-prova');
        if (iframe) iframe.style.visibility = 'hidden';
    }
    function esconderOverlayCarregandoMateria() {
        var iframe = document.getElementById('iframe-prova');
        if (iframe) iframe.style.visibility = 'visible';
        var el = document.getElementById('overlay-carregando-materia');
        if (el) el.style.display = 'none';
    }

    function mostrarListaSobreIframe() {
        aguardandoProximaMateria = true;
        transicaoMateria = true;
        ignorarVisibilityAte = Date.now() + 120000;
        var conteudo = document.getElementById('conteudo-prova-segura');
        var tela = document.getElementById('tela-iframe-prova');
        if (conteudo) {
            conteudo.style.display = 'flex';
            conteudo.style.position = 'fixed';
            conteudo.style.inset = '0';
            conteudo.style.zIndex = '9000';
            conteudo.style.background = '#f3f4f6';
            conteudo.style.overflow = 'auto';
        }
        if (tela) {
            tela.style.pointerEvents = 'none';
            tela.style.display = 'block';
        }
        mostrarOverlayCarregandoMateria();
        try { window.focus(); } catch (e) {}
        if (!estaEmFullscreen()) {
            mostrarOverlayTelaCheia();
        }
    }

    function abrirIframeProva(url) {
        aguardandoProximaMateria = false;
        transicaoMateria = true;
        ignorarVisibilityAte = Date.now() + 2000;
        var seq = ++cargaIframeSeq;
        var conteudo = document.getElementById('conteudo-prova-segura');
        var tela = document.getElementById('tela-iframe-prova');
        var iframe = document.getElementById('iframe-prova');
        mostrarOverlayCarregandoMateria();
        if (tela) {
            tela.style.display = 'block';
            tela.style.visibility = 'visible';
            tela.style.pointerEvents = 'auto';
        }
        if (conteudo) {
            conteudo.style.display = 'none';
            conteudo.style.position = '';
            conteudo.style.inset = '';
            conteudo.style.zIndex = '';
            conteudo.style.background = '';
            conteudo.style.overflow = '';
        }
        if (!estaEmFullscreen()) {
            entrarFullscreen();
        }
        if (iframe) {
            iframe.onload = function() {
                if (seq !== cargaIframeSeq) return;
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        if (seq !== cargaIframeSeq) return;
                        esconderOverlayCarregandoMateria();
                    });
                });
            };
            iframe.src = url;
        }
        setTimeout(function() {
            if (!aguardandoProximaMateria) transicaoMateria = false;
        }, 2000);
        setTimeout(function() {
            if (seq === cargaIframeSeq) {
                esconderOverlayCarregandoMateria();
            }
        }, 8000);
    }

    function tratarSaidaFullscreen() {
        if (estaEmFullscreen()) {
            entrouFullscreenUmaVez = true;
            ignorarVisibilityAte = Date.now() + 800;
            if (timerPrimeiraSaida) {
                clearInterval(timerPrimeiraSaida);
                timerPrimeiraSaida = null;
            }
            var modalEl = document.getElementById('modal-aviso');
            if (modalEl && modalEl.style.display === 'flex') {
                modalEl.style.display = 'none';
                modalEl.classList.add('hidden');
            }
            esconderOverlayTelaCheia();
            <?php if ($blocoTerminoIso): ?>mostrarCronometro();<?php endif; ?>
            return;
        }
        if (permitirSair || !modoSeguroAtivo) return;
        if (transicaoMateria || aguardandoProximaMateria || Date.now() < ignorarVisibilityAte) {
            mostrarOverlayTelaCheia();
            return;
        }
        var motivoFullscreen = motivoSaidaRecente('saiu_tela_cheia');
        registrarAcaoSeguranca(motivoFullscreen);
        if (saiuFullscreenContagem === 0) {
            saiuFullscreenContagem = 1;
            window._motivoPrimeiraSaidaSeguro = motivoFullscreen;
            registrarLogSeguranca(
                'tentativa_sair_tela_cheia',
                'Primeira saída da tela cheia: ' + rotuloMotivoSaida(motivoFullscreen) + '.'
            );
            mostrarModalPrimeiraSaida();
        } else {
            cancelamentoIniciado = true;
            mostrarModalCancelarProva();
        }
    }

    function mostrarOverlayTelaCheia() {
        var el = document.getElementById('overlay-tela-cheia');
        if (el) el.style.display = 'flex';
    }
    function esconderOverlayTelaCheia() {
        var el = document.getElementById('overlay-tela-cheia');
        if (el) el.style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
        antiCola();
        var params = new URLSearchParams(window.location.search);
        if (params.get('encerrado') === '1') {
            cancelarBlocoSeguroAgora();
            mostrarModal('Prova cancelada', 'A prova foi cancelada por saída do modo seguro (tela cheia fechada, aba fechada ou navegação). Apenas o coordenador pode liberar nova tentativa para você realizar novamente.');
            if (window.history && window.history.replaceState) {
                params.delete('encerrado');
                var novaUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                window.history.replaceState({}, '', novaUrl);
            }
        }
        if (params.get('materia_ok') === '1') {
            aguardandoProximaMateria = true;
            transicaoMateria = true;
            if (window.history && window.history.replaceState) {
                params.delete('materia_ok');
                var urlOk = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                window.history.replaceState({}, '', urlOk);
            }
            if (!estaEmFullscreen()) {
                mostrarOverlayTelaCheia();
            }
        }
        // Tela cheia em ambos os modos (melhora foco/leitura). No modo relaxado (EducaInclui)
        // o overlay é opcional: o aluno pode sair com Esc sem cancelar (ver fullscreenchange).
        if (!estaEmFullscreen()) {
            mostrarOverlayTelaCheia();
        }
        if (!modoSeguroAtivo) {
            <?php if ($blocoTerminoIso): ?>mostrarCronometro();<?php endif; ?>
        }

        document.getElementById('btn-entrar-tela-cheia') && document.getElementById('btn-entrar-tela-cheia').addEventListener('click', function() {
            entrarFullscreen();
        });
        var linkSairAntes = document.getElementById('link-sair-antes-iniciar');
        if (linkSairAntes) {
            linkSairAntes.addEventListener('click', function() {
                permitirSair = true;
            });
        }

        document.addEventListener('fullscreenchange', tratarSaidaFullscreen);
        document.addEventListener('webkitfullscreenchange', tratarSaidaFullscreen);

        document.addEventListener('click', function(e) {
            var a = e.target && e.target.closest('a');
            if (a && a.classList && a.classList.contains('link-saida-permitida')) {
                permitirSair = true;
            }
            var recarregar = e.target && e.target.closest('.btn-recarregar-materias');
            if (recarregar) {
                e.preventDefault();
                iniciarTransicaoMateria(2000);
                var lista = document.getElementById('lista-materias-container');
                if (lista) {
                    fetch('<?= URL ?>/aluno/provas/bloco/<?= (int)$blocoId ?>/iniciar-seguro?partial=1')
                        .then(function(r) { return r.text(); })
                        .then(function(html) { lista.innerHTML = html; })
                        .catch(function() {});
                }
                return;
            }
            var btn = e.target && e.target.closest('.btn-iniciar-materia');
            if (btn && btn.getAttribute('data-prova-id')) {
                e.preventDefault();
                var provaId = btn.getAttribute('data-prova-id');
                var blocoId = <?= (int)$blocoId ?>;
                var url = '<?= URL ?>/aluno/provas/realizar/' + provaId + '?bloco_id=' + blocoId + '&modo_bloco=1&modo_seguro=<?= $acessibilidade_relax_secure ? '0' : '1' ?>&embed=1';
                abrirIframeProva(url);
            }
        }, true);

        document.addEventListener('visibilitychange', function() {
            if (!modoSeguroAtivo) return;
            if (!entrouFullscreenUmaVez) return;
            if (transicaoMateria || aguardandoProximaMateria || Date.now() < ignorarVisibilityAte) return;
            if (document.visibilityState === 'hidden' && !permitirSair && !cancelamentoIniciado) {
                cancelamentoIniciado = true;
                fecharIframeProva();
                cancelarBlocoSeguroAgora(motivoSaidaRecente('aba_trocada'));
            }
        });
        window.addEventListener('pagehide', function() {
            if (!modoSeguroAtivo) return;
            if (!entrouFullscreenUmaVez) return;
            if (transicaoMateria || aguardandoProximaMateria) return;
            if (permitirSair || cancelamentoIniciado) return;
            cancelamentoIniciado = true;
            fecharIframeProva();
            cancelarBlocoSeguroAgora(motivoSaidaRecente('aba_fechada'));
        });
        window.addEventListener('beforeunload', function(e) {
            if (!modoSeguroAtivo) return;
            if (!entrouFullscreenUmaVez) return;
            if (transicaoMateria || aguardandoProximaMateria) return;
            if (permitirSair || cancelamentoIniciado) return;
            cancelamentoIniciado = true;
            fecharIframeProva();
            cancelarBlocoSeguroAgora(motivoSaidaRecente('aba_fechada'));
            e.preventDefault();
            e.returnValue = '';
            return '';
        });
        window.addEventListener('blur', function() {
            if (!modoSeguroAtivo || !entrouFullscreenUmaVez || permitirSair || cancelamentoIniciado) return;
            if (document.visibilityState === 'hidden') return;
            registrarAcaoSeguranca('janela_perdeu_foco');
        });

        var loadingHtml = '<div class="flex flex-col items-center justify-center py-12 px-6"><div class="animate-spin rounded-full h-12 w-12 border-4 border-indigo-200 border-t-indigo-600 mb-4"></div><p class="text-gray-600 font-medium">Atualizando lista...</p><p class="text-sm text-gray-500 mt-1">Aguarde um momento.</p></div>';
        window.addEventListener('message', function(e) {
            if (!e.data || !e.data.tipo) return;
            if (e.data.tipo === 'acao_seguranca' && e.data.motivo) {
                registrarAcaoSeguranca(e.data.motivo);
                return;
            }
            if (e.data.tipo === 'finalizando_materia') {
                transicaoMateria = true;
                aguardandoProximaMateria = true;
                ignorarVisibilityAte = Date.now() + 120000;
                // Não chamar window.focus() aqui: no Edge/Chromium isso tira o foco
                // do iframe e aborta o POST de finalizar (Failed to fetch).
                return;
            }
            if (e.data.tipo === 'prova_finalizada' && e.data.bloco_id) {
                mostrarListaSobreIframe();
                var container = document.getElementById('lista-materias-container');
                if (container) container.innerHTML = loadingHtml;
                var blocoId = e.data.bloco_id;
                fetch('<?= URL ?>/aluno/provas/bloco/' + blocoId + '/iniciar-seguro?partial=1')
                    .then(function(r) { return r.text(); })
                    .then(function(html) {
                        if (container) container.innerHTML = html;
                    })
                    .catch(function() {
                        if (container) container.innerHTML = '<p class="text-red-600 py-4">Erro ao atualizar. <button type="button" class="underline btn-recarregar-materias">Recarregar</button></p>';
                    });
            }
        });

        if (modoSeguroAtivo) {
            history.pushState(null, '', location.href);
            window.addEventListener('popstate', function() {
                if (!modoSeguroAtivo) return;
                history.pushState(null, '', location.href);
                registrarAcaoSeguranca('botao_voltar');
                registrarLogSeguranca('tentativa_voltar_navegador', 'Aluno tentou usar o botão Voltar do navegador durante o modo seguro.');
                mostrarModal('Atenção', 'Você só pode sair após finalizar todas as provas ou clicar em "Sair do modo prova".');
            });
        }
    });

    document.addEventListener('click', function(e) {
        if (e.target.id === 'btn-sair-prova-segura' || (e.target.closest && e.target.closest('#btn-sair-prova-segura'))) {
            e.preventDefault();
            e.stopPropagation();
            permitirSair = true;
            sairFullscreen();
            window.location.href = '<?= URL ?>/aluno/provas';
        }
    }, true);

})();
</script>
