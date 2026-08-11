<?php
/**
 * Visualização somente leitura do Excalidraw - Meu Caderno (aluno).
 * Exibe o conteúdo salvo sem permitir edição. Usado no show ou em iframe.
 */
$caderno_id = (int)($caderno_id ?? 0);
$initial_data_json = $initial_data_json ?? '{}';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anotação - Meu Caderno</title>
    <link rel="stylesheet" href="https://esm.sh/@excalidraw/excalidraw@0.18.0/dist/dev/index.css">
    <script>
        window.EXCALIDRAW_ASSET_PATH = "https://esm.sh/@excalidraw/excalidraw@0.18.0/dist/prod/";
        window.CADERNO_EXCALIDRAW_VIEW_INITIAL = <?= $initial_data_json ?>;
    </script>
    <script type="importmap">
    {
        "imports": {
            "react": "https://esm.sh/react@18.2.0",
            "react/jsx-runtime": "https://esm.sh/react@18.2.0/jsx-runtime",
            "react-dom": "https://esm.sh/react-dom@18.2.0",
            "react-dom/client": "https://esm.sh/react-dom@18.2.0/client"
        }
    }
    </script>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f5f5f5; }
        #excalidraw-root { height: 100vh; width: 100%; }
    </style>
</head>
<body>
    <div id="excalidraw-root"></div>
    <script type="module">
    (function() {
        const initial = window.CADERNO_EXCALIDRAW_VIEW_INITIAL || {};
        const initialData = {
            elements: initial.elements || [],
            appState: initial.appState || {},
            files: initial.files || {}
        };

        const React = await import('react');
        const ReactDOM = await import('react-dom/client');
        const ExcalidrawModule = await import('https://esm.sh/@excalidraw/excalidraw@0.18.0?external=react,react-dom');
        const Excalidraw = ExcalidrawModule.Excalidraw;

        const root = document.getElementById('excalidraw-root');
        if (root) {
            const clientRoot = ReactDOM.createRoot(root);
            clientRoot.render(
                React.createElement('div', { style: { height: '100%', width: '100%' } },
                    React.createElement(Excalidraw, {
                        initialData: initialData,
                        readOnly: true,
                        viewModeEnabled: true,
                        theme: 'light'
                    })
                )
            );
        }
    })();
    </script>
</body>
</html>
