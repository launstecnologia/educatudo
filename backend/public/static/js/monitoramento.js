(function () {
    'use strict';

    var pathPrefix = '';
    if (typeof window.location !== 'undefined' && window.location.pathname) {
        var idx = window.location.pathname.indexOf('/monitoramento');
        pathPrefix = idx >= 0 ? window.location.pathname.substring(0, idx) : '';
    }
    var baseUrl = (typeof window.location !== 'undefined' ? window.location.origin : '') + pathPrefix;
    var streamUrl = baseUrl + '/api/monitoramento/stream';
    var liveEl = document.getElementById('mon-live');
    var offlineEl = document.getElementById('mon-offline');
    var errorEl = document.getElementById('mon-error-msg');
    var eventSource = null;

    function statusClass(percent) {
        if (percent < 60) return 'status-ok';
        if (percent <= 80) return 'status-warn';
        return 'status-critical';
    }

    function setLive(connected) {
        if (connected) {
            liveEl.classList.remove('hidden');
            offlineEl.classList.add('hidden');
        } else {
            liveEl.classList.add('hidden');
            offlineEl.classList.remove('hidden');
        }
    }

    function updateGauge(id, percent) {
        var valEl = document.getElementById(id);
        var needleEl = document.getElementById(id + '-needle');
        if (!valEl || !needleEl) return;
        var pct = percent != null ? Math.min(100, Math.max(0, percent)) : 0;
        valEl.textContent = pct + '%';
        var deg = -90 + (pct / 100) * 180;
        needleEl.style.transform = 'rotate(' + deg + 'deg)';
        needleEl.className = 'mon-gauge-needle ' + (pct <= 80 ? (pct <= 60 ? '' : 'status-warn') : 'status-critical');
    }

    function updateInfra(data) {
        if (!data) return;
        updateGauge('infra-cpu', data.cpu_percent);
        updateGauge('infra-ram', data.ram_percent);
        updateGauge('infra-disk', data.disk_percent);
    }

    function formatNum(n) {
        if (n == null || isNaN(n)) return '--';
        return Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 0 });
    }

    function updateUsers(data) {
        if (!data) return;
        var el = document.getElementById('acessos-hoje-total');
        if (el) el.textContent = formatNum(data.acessos_hoje_total);
    }

    function updateDb(data) {
        if (!data) return;
        var el = document.getElementById('db-queries');
        if (el) el.textContent = formatNum(data.db_queries);
        el = document.getElementById('db-slow');
        if (el) el.textContent = formatNum(data.slow_queries);
        el = document.getElementById('db-errors');
        if (el) el.textContent = formatNum(data.db_errors);
        el = document.getElementById('db-avg-ms');
        if (el) el.textContent = (data.avg_db_time_ms != null && !isNaN(data.avg_db_time_ms)) ? Number(data.avg_db_time_ms).toFixed(1) + ' ms' : '--';
    }

    function applyPayload(payload) {
        if (payload.infra) updateInfra(payload.infra);
        if (payload.users) updateUsers(payload.users);
        if (payload.db) updateDb(payload.db);
    }

    function apiFetch(path) {
        var url = baseUrl + path;
        return fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) {
            if (!r.ok) throw new Error(String(r.status));
            return r.json();
        });
    }

    function showError(msg) {
        if (errorEl) {
            errorEl.textContent = msg;
            errorEl.classList.remove('hidden');
        }
    }

    function clearError() {
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
        }
    }

    function fetchOnce() {
        clearError();
        Promise.all([apiFetch('/api/infra'), apiFetch('/api/users-stats'), apiFetch('/api/db-stats')]).then(function (results) {
            applyPayload({ infra: results[0], users: results[1], db: results[2] });
            setLive(true);
            clearError();
        }).catch(function (err) {
            setLive(false);
            if (!window.MONITORAMENTO_INITIAL_DATA) {
                var msg = 'Não foi possível atualizar. ';
                if (err && err.message && /^\d+$/.test(err.message)) {
                    msg += 'HTTP ' + err.message + '.';
                } else {
                    msg += 'Dados iniciais exibidos.';
                }
                showError(msg);
            }
        });
    }

    var pollingInterval = null;

    function startPollingFallback() {
        if (pollingInterval) return;
        pollingInterval = setInterval(function () {
            Promise.all([apiFetch('/api/infra'), apiFetch('/api/users-stats'), apiFetch('/api/db-stats')]).then(function (results) {
                applyPayload({ infra: results[0], users: results[1], db: results[2] });
            }).catch(function () {});
        }, 2000);
    }

    function stopPollingFallback() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }

    function connectStream() {
        if (eventSource) {
            try { eventSource.close(); } catch (e) {}
        }
        eventSource = new EventSource(streamUrl);
        eventSource.onmessage = function (e) {
            try {
                var payload = JSON.parse(e.data);
                applyPayload(payload);
                setLive(true);
                stopPollingFallback();
            } catch (err) {}
        };
        eventSource.onerror = function () {
            setLive(false);
            eventSource.close();
            eventSource = null;
            startPollingFallback();
            setTimeout(connectStream, 5000);
        };
    }

    function init() {
        if (window.MONITORAMENTO_INITIAL_DATA) {
            applyPayload(window.MONITORAMENTO_INITIAL_DATA);
            setLive(true);
        }
        fetchOnce();
        connectStream();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
