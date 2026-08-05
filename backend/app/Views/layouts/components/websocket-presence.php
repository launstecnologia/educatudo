<?php
/**
 * Componente: WebSocket presença online por escola (subdomínio).
 * Só renderiza em contexto multi-tenant com usuário aluno ou professor logado.
 */
$user = $user ?? null;
$wsEscola = defined('TENANT_SLUG') ? (string) TENANT_SLUG : '';
$wsUserId = (int) ($user['id'] ?? 0);
$wsUserName = (string) ($user['nome'] ?? '');
$wsUserTipo = (string) ($user['tipo'] ?? '');
$wsEnabled = ($wsEscola !== '' && $wsUserId > 0 && in_array($wsUserTipo, ['aluno', 'professor', 'admin'], true));
?>
<script>
(function() {
    window.EDUCATUDO_WS = {
        url: "wss://ws.educatudo.com",
        escola: <?= json_encode($wsEscola) ?>,
        usuario_id: <?= (int) $wsUserId ?>,
        nome: <?= json_encode($wsUserName) ?>,
        tipo: <?= json_encode($wsUserTipo) ?>,
        enabled: <?= $wsEnabled ? 'true' : 'false' ?>,
        lastUpdate: null
    };

    if (!window.EDUCATUDO_WS.enabled) return;

    var wsUrl = window.EDUCATUDO_WS.url;
    var escola = window.EDUCATUDO_WS.escola;
    var usuario_id = window.EDUCATUDO_WS.usuario_id;
    var nome = window.EDUCATUDO_WS.nome;
    var tipo = window.EDUCATUDO_WS.tipo;
    var ws = null;
    var reconnectDelay = 2000;
    var maxReconnectDelay = 30000;
    var reconnectTimer = null;

    function connect() {
        try {
            ws = new WebSocket(wsUrl);
        } catch (e) {
            scheduleReconnect();
            return;
        }

        ws.onopen = function() {
            reconnectDelay = 2000;
            var tipoEnvio = (tipo === 'admin') ? 'professor' : tipo;
            ws.send(JSON.stringify({
                type: 'login',
                escola: escola,
                usuario_id: usuario_id,
                nome: nome,
                tipo: tipoEnvio
            }));
            if (tipo === 'aluno' && typeof window.educaSendPresence === 'function') {
                window.educaSendPresence();
            }
        };

        ws.onmessage = function(event) {
            try {
                var msg = JSON.parse(event.data);
                if (msg.type === 'dashboard' && msg.data) {
                    window.EDUCATUDO_WS.lastUpdate = msg.data;
                    if (typeof window.EDUCATUDO_WS.onMasterUpdate === 'function') {
                        window.EDUCATUDO_WS.onMasterUpdate(msg.data);
                    }
                }
            } catch (e) {}
        };

        ws.onclose = function() {
            ws = null;
            scheduleReconnect();
        };

        ws.onerror = function() {
            if (ws) ws.close();
        };
    }

    function scheduleReconnect() {
        if (reconnectTimer) return;
        reconnectTimer = setTimeout(function() {
            reconnectTimer = null;
            connect();
            if (reconnectDelay < maxReconnectDelay) {
                reconnectDelay = Math.min(reconnectDelay * 1.5, maxReconnectDelay);
            }
        }, reconnectDelay);
    }

    connect();

    window.EDUCATUDO_WS.onMasterUpdate = function(data) {
        var el = document.getElementById('badge-online-count');
        if (el && window.EDUCATUDO_WS && window.EDUCATUDO_WS.escola && data[window.EDUCATUDO_WS.escola]) {
            var d = data[window.EDUCATUDO_WS.escola];
            // O badge no menu é "Alunos Online", então exibimos apenas alunos.
            el.textContent = (d.alunos || 0);
        }
    };
})();
</script>
