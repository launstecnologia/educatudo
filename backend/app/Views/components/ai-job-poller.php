<?php
/**
 * Componente de polling para jobs de IA assíncronos.
 */
?>
<script>
(function (global) {
    var APP_BASE_URL = <?= json_encode(defined('URL') ? rtrim((string) URL, '/') : '') ?>;

    function absUrl(path) {
        path = String(path || '');
        if (/^https?:\/\//i.test(path)) {
            return path;
        }
        if (path.charAt(0) !== '/') {
            path = '/' + path;
        }
        return APP_BASE_URL ? (APP_BASE_URL + path) : path;
    }

    function AIJobPoller(jobId, opts) {
        opts = opts || {};
        var interval = opts.interval || 2000;
        var maxFailures = opts.maxFailures || 8;
        var timer    = null;
        var stopped  = false;
        var failures = 0;
        var debugLog = [];

        function logDebug(entry) {
            debugLog.push(entry);
            if (opts.debug) {
                console.log('[AIJobPoller]', entry);
            }
            if (typeof opts.onDebug === 'function') {
                opts.onDebug(entry, debugLog.slice());
            }
        }

        function fail(message) {
            if (stopped) return;
            stopped = true;
            clearInterval(timer);
            if (opts.debug) {
                console.error('[AIJobPoller] falhou', { message: message, attempts: debugLog });
            }
            if (typeof opts.onFailed === 'function') {
                if (opts.debug && debugLog.length) {
                    opts.onFailed(message, debugLog.slice());
                } else {
                    opts.onFailed(message);
                }
            }
        }

        function buildUrl(template, id) {
            return String(template || '').replace(/\{id\}/g, String(id));
        }

        function resolveStatusUrls(id) {
            var urls = [];
            if (opts.statusUrl) {
                urls.push(buildUrl(opts.statusUrl, id));
            }
            if (Array.isArray(opts.statusUrls)) {
                opts.statusUrls.forEach(function (tpl) {
                    urls.push(buildUrl(tpl, id));
                });
            }

            var path = window.location && window.location.pathname ? window.location.pathname : '';
            if (path.indexOf('/professor') !== -1) {
                urls.push(absUrl('/professor/jornadas/corrigir-redacao-ia/status/' + id));
                urls.push(absUrl('/professor/jornadas/ai-job/' + id + '/status'));
                urls.push(absUrl('/professor/ai-job/' + id + '/status'));
            } else if (path.indexOf('/admin') !== -1) {
                urls.push(absUrl('/admin/jornadas/ai-job/' + id + '/status'));
                urls.push(absUrl('/admin/ai-job/' + id + '/status'));
            }
            urls.push(absUrl('/ai-job/' + id + '/status'));

            var seen = {};
            return urls.filter(function (url) {
                if (!url || seen[url]) return false;
                seen[url] = true;
                return true;
            });
        }

        function handlePollResponse(result, url) {
            if (stopped) return;

            logDebug({
                type: 'response',
                url: url,
                http: result.status,
                ok: result.ok,
                data: result.data
            });

            var data = result.data || {};
            failures = 0;

            if (!result.ok && !data.status) {
                if (result.status === 401 || result.status === 403) {
                    fail((data && data.error) ? data.error : 'Sessão expirada. Faça login novamente.');
                    return;
                }
                throw new Error((data && data.error) ? data.error : ('Erro HTTP ' + result.status + ' em ' + url));
            }

            if (data.status === 'done') {
                stopped = true;
                clearInterval(timer);
                opts.onDone && opts.onDone(data.result || {}, debugLog.slice());
                return;
            }

            if (data.status === 'failed' || data.status === 'error' || data.status === 'invalid') {
                var failMsg = data.error || 'Falha ao processar. Tente novamente.';
                if (data.debug) {
                    failMsg += ' [debug: ' + JSON.stringify(data.debug) + ']';
                }
                fail(failMsg);
                return;
            }

            if (data.status === 'forbidden') {
                fail(data.error || 'Sessão expirada ou sem permissão para acompanhar este processamento.');
                return;
            }

            if (data.status === 'not_found') {
                fail(data.error || 'Processamento não encontrado (job_id=' + jobId + '). Tente iniciar novamente.');
                return;
            }

            opts.onProgress && opts.onProgress(data.status || 'processing', data);
        }

        function fetchStatus(urls, index) {
            if (stopped) return;
            if (!urls.length) {
                fail('Nenhuma URL de status configurada.');
                return;
            }
            if (index >= urls.length) {
                var tried = urls.join('\n• ');
                fail('Nenhum endpoint de status respondeu.\n\nURLs testadas:\n• ' + tried);
                return;
            }

            var url = urls[index];
            if (url.indexOf('?') === -1 && opts.debug) {
                url += '?debug=1';
            } else if (opts.debug && url.indexOf('debug=1') === -1) {
                url += (url.indexOf('?') === -1 ? '?' : '&') + 'debug=1';
            }

            logDebug({ type: 'fetch', url: url, attempt: index + 1, total: urls.length });

            fetch(url, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (r) {
                return r.text().then(function (text) {
                    if (r.status === 404 && index + 1 < urls.length) {
                        logDebug({ type: '404_fallback', url: url, next: urls[index + 1] });
                        return fetchStatus(urls, index + 1);
                    }

                    var data = null;
                    if (text) {
                        try {
                            data = JSON.parse(text);
                        } catch (parseError) {
                            logDebug({
                                type: 'parse_error',
                                url: url,
                                http: r.status,
                                snippet: text.substring(0, 200)
                            });
                            if (r.status === 404 && index + 1 < urls.length) {
                                return fetchStatus(urls, index + 1);
                            }
                            if (r.status === 404) {
                                throw new Error('HTTP 404 em ' + url + ' (resposta não-JSON)');
                            }
                            throw new Error('Resposta inválida de ' + url + ' (HTTP ' + r.status + ')');
                        }
                    }

                    return handlePollResponse({ ok: r.ok, status: r.status, data: data }, url);
                });
            })
            .catch(function (err) {
                if (stopped) return;
                logDebug({ type: 'error', url: url, message: err && err.message ? err.message : String(err) });
                failures++;
                if (failures >= maxFailures) {
                    var msg = (err && err.message) ? err.message : 'Erro de conexão ao verificar status.';
                    if (debugLog.length) {
                        msg += '\n\nDiagnóstico (últimas tentativas no console).';
                    }
                    fail(msg);
                }
            });
        }

        function poll() {
            var urls = resolveStatusUrls(jobId);
            logDebug({ type: 'poll_start', jobId: jobId, urls: urls });
            fetchStatus(urls, 0);
        }

        poll();
        timer = setInterval(poll, interval);

        return {
            stop: function () {
                stopped = true;
                clearInterval(timer);
            },
            getDebugLog: function () {
                return debugLog.slice();
            }
        };
    }

    global.AIJobPoller = AIJobPoller;
}(window));
</script>
