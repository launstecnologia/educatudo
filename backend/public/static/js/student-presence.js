(function () {
    if (typeof window.__educaPresenceInit !== 'undefined') return;
    window.__educaPresenceInit = true;

    var intervalMs = 45000;
    var endpoint = (window.EDUCATUDO_URL || '') + '/api/aluno/presenca';

    function getCsrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content') || '';
        return window.__csrfToken || '';
    }

    function sendPresence() {
        var csrf = getCsrf();
        if (!csrf || !endpoint) return;

        var ctx = window.__educaContexto || {};
        var body = new URLSearchParams();
        body.append('_token', csrf);
        body.append('url', window.location.pathname + window.location.search);
        if (ctx.tipo) body.append('contexto_tipo', ctx.tipo);
        if (ctx.id) body.append('contexto_id', String(ctx.id));
        if (ctx.label) body.append('contexto_label', ctx.label);

        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin',
            keepalive: true
        }).catch(function () {});
    }

    window.educaSendPresence = sendPresence;

    sendPresence();
    setInterval(sendPresence, intervalMs);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) sendPresence();
    });
})();
