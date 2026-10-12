<?php
/**
 * Editor Excalidraw - Meu Caderno (aluno).
 * Página mínima para uso em iframe: carrega React + Excalidraw, autosave e exportação PNG/PDF.
 * Não usa layout do site (full HTML).
 */
$caderno_id = (int)($caderno_id ?? 0);
$caderno_titulo = $caderno_titulo ?? '';
$url_base = rtrim($url_base ?? '', '/');
$csrf_token = $csrf_token ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anotações - <?= htmlspecialchars($caderno_titulo) ?> - Meu Caderno</title>
    <link rel="stylesheet" href="https://esm.sh/@excalidraw/excalidraw@0.18.0/dist/dev/index.css">
    <script>
        window.EXCALIDRAW_ASSET_PATH = "https://esm.sh/@excalidraw/excalidraw@0.18.0/dist/prod/";
        window.CADERNO_EXCALIDRAW_CONFIG = {
            cadernoId: <?= (int)$caderno_id ?>,
            urlBase: <?= json_encode($url_base) ?>,
            csrfToken: <?= json_encode($csrf_token) ?>
        };
    </script>
    <script type="importmap">
    {"imports":{"react":"https://esm.sh/react@19.0.0","react/jsx-runtime":"https://esm.sh/react@19.0.0/jsx-runtime","react-dom":"https://esm.sh/react-dom@19.0.0","react-dom/client":"https://esm.sh/react-dom@19.0.0/client"}}
    </script>
    <style>
        html, body { margin: 0; padding: 0; height: 100%; font-family: system-ui, sans-serif; background: #f5f5f5; overflow: hidden; }
        .toolbar-caderno { flex-shrink: 0; display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #fff; border-bottom: 1px solid #e5e7eb; flex-wrap: wrap; }
        .toolbar-caderno .titulo-editor { font-weight: 600; color: #374151; margin-right: auto; }
        .toolbar-caderno .status { font-size: 12px; color: #6b7280; }
        .toolbar-caderno button { padding: 6px 12px; border-radius: 6px; border: 1px solid #d1d5db; background: #fff; cursor: pointer; font-size: 13px; }
        .toolbar-caderno button:hover { background: #f3f4f6; }
        .toolbar-caderno button.export-png { background: #059669; color: #fff; border-color: #059669; }
        .toolbar-caderno button.export-png:hover { background: #047857; }
        .toolbar-caderno a.voltar { color: #059669; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; margin-right: 12px; }
        .toolbar-caderno a.voltar:hover { text-decoration: underline; }
        /* Altura fixa para o container do Excalidraw (requer dimensões definidas para a barra de ferramentas aparecer) */
        #excalidraw-root { width: 100%; height: 560px; min-height: 560px; position: relative; }
        #excalidraw-wrapper { display: flex; flex-direction: column; height: 100%; min-height: 100vh; }
    </style>
</head>
<body>
    <div id="excalidraw-wrapper">
        <div class="toolbar-caderno">
            <a href="<?= htmlspecialchars($url_base . '/caderno/' . $caderno_id) ?>" class="voltar">← Voltar à anotação</a>
            <span class="titulo-editor"><?= htmlspecialchars($caderno_titulo) ?></span>
            <span class="status" id="status-save">Salvo automaticamente</span>
            <button type="button" id="btn-export-png">Exportar PNG</button>
            <button type="button" id="btn-export-pdf">Imprimir / PDF</button>
        </div>
        <div id="excalidraw-root"></div>
    </div>

    <script type="module">
    (function() {
        const CONFIG = window.CADERNO_EXCALIDRAW_CONFIG;
        if (!CONFIG || !CONFIG.cadernoId) return;

        const loadUrl = CONFIG.urlBase + '/caderno/' + CONFIG.cadernoId + '/excalidraw-carregar';
        const saveUrl = CONFIG.urlBase + '/caderno/' + CONFIG.cadernoId + '/excalidraw-salvar';

        let saveTimeout = null;
        const DEBOUNCE_MS = 2000;

        function setStatus(text, isError) {
            const el = document.getElementById('status-save');
            if (el) {
                el.textContent = text;
                el.style.color = isError ? '#dc2626' : '#6b7280';
            }
        }

        function autosave(elements, appState, files) {
            if (saveTimeout) clearTimeout(saveTimeout);
            setStatus('Salvando...', false);
            saveTimeout = setTimeout(function() {
                const content = JSON.stringify({ elements: elements || [], appState: appState || {}, files: files || {} });
                const form = new FormData();
                form.append('_token', CONFIG.csrfToken);
                form.append('content', content);
                fetch(saveUrl, { method: 'POST', body: form })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) setStatus('Salvo automaticamente', false);
                        else setStatus('Erro ao salvar', true);
                    })
                    .catch(function() { setStatus('Erro de conexão', true); });
            }, DEBOUNCE_MS);
        }

        // Carrega React e Excalidraw via ESM (path e React 19 conforme docs oficiais)
        const React = await import('react');
        const ReactDOM = await import('react-dom/client');
        const ExcalidrawModule = await import('https://esm.sh/@excalidraw/excalidraw@0.18.0/dist/prod/index.js?external=react,react-dom');
        const Excalidraw = ExcalidrawModule.Excalidraw;
        const exportToBlob = ExcalidrawModule.exportToBlob || (() => Promise.resolve(null));

        let currentElements = [];
        let currentAppState = {};
        let currentFiles = {};

        function App() {
            const [initialData, setInitialData] = React.useState(null);
            const [loaded, setLoaded] = React.useState(false);

            React.useEffect(function() {
                fetch(loadUrl)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success && data.content) {
                            setInitialData({
                                elements: data.content.elements || [],
                                appState: data.content.appState || {},
                                files: data.content.files || {}
                            });
                        } else {
                            setInitialData({ elements: [], appState: {}, files: {} });
                        }
                        setLoaded(true);
                    })
                    .catch(function() {
                        setInitialData({ elements: [], appState: {}, files: {} });
                        setLoaded(true);
                    });
            }, []);

            const onChange = React.useCallback(function(elements, appState, files) {
                currentElements = elements;
                currentAppState = appState;
                currentFiles = files || {};
                autosave(elements, appState, currentFiles);
            }, []);

            if (!loaded) {
                return React.createElement('div', { style: { padding: 20, textAlign: 'center' } }, 'Carregando...');
            }

            return React.createElement(
                'div',
                { style: { height: '560px', width: '100%' } },
                React.createElement(Excalidraw, {
                    initialData: initialData,
                    onChange: onChange,
                    theme: 'light'
                })
            );
        }

        const root = document.getElementById('excalidraw-root');
        if (root) {
            const clientRoot = ReactDOM.createRoot(root);
            clientRoot.render(React.createElement(App));
        }

        document.getElementById('btn-export-png').addEventListener('click', function() {
            if (currentElements.length === 0) {
                alert('Não há nada para exportar. Desenhe algo primeiro.');
                return;
            }
            const blobPromise = exportToBlob({
                elements: currentElements,
                appState: currentAppState,
                files: currentFiles,
                exportPadding: 10,
                maxWidthOrHeight: 2048
            });
            if (blobPromise && typeof blobPromise.then === 'function') {
                blobPromise.then(function(blob) {
                    if (!blob) return;
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = 'anotacao-' + CONFIG.cadernoId + '.png';
                    a.click();
                    URL.revokeObjectURL(a.href);
                }).catch(function() { alert('Erro ao exportar PNG.'); });
            } else {
                alert('Exportação PNG não disponível nesta versão.');
            }
        });

        document.getElementById('btn-export-pdf').addEventListener('click', function() {
            window.print();
        });
    })();
    </script>
</body>
</html>
