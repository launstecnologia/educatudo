/**
 * Integração do Launs Editor nos exercícios da jornada (professor).
 * Upload devolve a URL pública (S3/media) gravada no src da imagem/vídeo.
 */
(function (global) {
    'use strict';

    var config = {
        uploadUrl: '',
        csrfToken: ''
    };

    function configurar(opts) {
        opts = opts || {};
        if (opts.uploadUrl) {
            config.uploadUrl = String(opts.uploadUrl);
        }
        if (opts.csrfToken != null) {
            config.csrfToken = String(opts.csrfToken);
        }
    }

    function tokenCsrf() {
        if (config.csrfToken) {
            return config.csrfToken;
        }
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.getAttribute('content') || '') : '';
    }

    function escaparAttr(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function htmlParaEditor(html) {
        var s = String(html || '').trim();
        if (!s) {
            return '<p></p>';
        }

        s = s.replace(/<span[^>]*class="[^"]*eq-chip[^"]*"[^>]*data-latex="([^"]*)"[^>]*>[\s\S]*?<\/span>/gi, function (_, latex) {
            return '<span data-type="inline-math" data-latex="' + latex + '"></span>';
        });
        s = s.replace(/<span[^>]*data-latex="([^"]*)"[^>]*class="[^"]*eq-chip[^"]*"[^>]*>[\s\S]*?<\/span>/gi, function (_, latex) {
            return '<span data-type="inline-math" data-latex="' + latex + '"></span>';
        });

        if (s.indexOf('data-type="inline-math"') === -1 && s.indexOf("data-type='inline-math'") === -1) {
            s = s.replace(/\\\(([\s\S]*?)\\\)/g, function (_, latex) {
                return '<span data-type="inline-math" data-latex="' + escaparAttr(latex.trim()) + '"></span>';
            });
        }
        if (s.indexOf('data-type="block-math"') === -1 && s.indexOf("data-type='block-math'") === -1) {
            s = s.replace(/\$\$([\s\S]*?)\$\$/g, function (_, latex) {
                return '<div data-type="block-math" data-latex="' + escaparAttr(latex.trim()) + '"></div>';
            });
            s = s.replace(/\\\[([\s\S]*?)\\\]/g, function (_, latex) {
                return '<div data-type="block-math" data-latex="' + escaparAttr(latex.trim()) + '"></div>';
            });
        }

        if (!/<[a-z][\s\S]*>/i.test(s)) {
            s = '<p>' + s.replace(/\n/g, '<br>') + '</p>';
        }
        return s;
    }

    function htmlDoEditor(editor) {
        if (!editor || typeof editor.getHTML !== 'function') {
            return '';
        }
        var tmp = document.createElement('div');
        tmp.innerHTML = editor.getHTML() || '';
        tmp.querySelectorAll('[data-type="inline-math"]').forEach(function (el) {
            el.replaceWith(document.createTextNode('\\(' + (el.getAttribute('data-latex') || '') + '\\)'));
        });
        tmp.querySelectorAll('[data-type="block-math"]').forEach(function (el) {
            el.replaceWith(document.createTextNode('$$' + (el.getAttribute('data-latex') || '') + '$$'));
        });
        if (tmp.querySelector('img')) {
            return tmp.innerHTML;
        }
        if ((tmp.textContent || '').replace(/\s/g, '') === '') {
            return '';
        }
        return tmp.innerHTML;
    }

    function htmlDeElemento(el) {
        if (!el) {
            return '';
        }
        if (el._launsEditor) {
            return htmlDoEditor(el._launsEditor);
        }
        return (el.innerHTML || '').trim();
    }

    function enviarParaS3(file) {
        if (!file || !file.type) {
            return Promise.reject(new Error('Arquivo inválido'));
        }
        var ehImagem = file.type.indexOf('image/') === 0;
        var ehVideo = file.type.indexOf('video/') === 0;
        if (!ehImagem && !ehVideo) {
            return Promise.reject(new Error('Envie uma imagem ou um vídeo.'));
        }
        if (!config.uploadUrl) {
            return Promise.reject(new Error('URL de upload não configurada'));
        }
        var fd = new FormData();
        fd.append('imagem', file);
        var token = tokenCsrf();
        if (token) {
            fd.append('_token', token);
        }
        return fetch(config.uploadUrl, { method: 'POST', body: fd }).then(function (r) {
            return r.json().then(function (data) {
                var url = data && (data.url || data.image_url);
                if (!r.ok || !data || !data.success || !url) {
                    throw new Error((data && data.error) ? data.error : 'Falha no upload para o S3.');
                }
                return url;
            });
        });
    }

    function destruir(el) {
        if (!el) {
            return;
        }
        if (el._launsEditor) {
            try {
                el._launsEditor.destroy();
            } catch (e) {}
            el._launsEditor = null;
        }
        el.innerHTML = '';
    }

    function criar(element, opts) {
        opts = opts || {};
        var el = typeof element === 'string' ? document.querySelector(element) : element;
        if (!el) {
            return null;
        }
        if (typeof LaunsEditor === 'undefined') {
            console.error('LaunsEditor não carregou.');
            return null;
        }

        var compact = !!opts.compact;
        var editor;
        try {
            destruir(el);
            editor = new LaunsEditor({
                element: el,
                content: htmlParaEditor(opts.content || ''),
                placeholder: opts.placeholder || 'Comece a escrever…',
                toolbar: true,
                menus: compact ? { slash: false } : undefined,
                upload: enviarParaS3,
                media: {
                    maxImageSizeMB: 10,
                    maxVideoSizeMB: 100,
                    maxImageDimension: 1600,
                    imageFormat: 'image/webp',
                    imageQuality: 0.82
                }
            });
        } catch (err) {
            console.error('Falha ao criar LaunsEditor', err);
            return null;
        }

        el._launsEditor = editor;

        editor.on('error', function (e) {
            var msg = e && e.error && e.error.message ? e.error.message : 'Erro no editor.';
            alert(msg);
        });

        function sincronizar() {
            if (!opts.hiddenInput) {
                return;
            }
            var hidden = typeof opts.hiddenInput === 'string'
                ? document.querySelector(opts.hiddenInput)
                : opts.hiddenInput;
            if (hidden) {
                hidden.value = htmlDoEditor(editor);
            }
        }

        editor.on('change', sincronizar);
        sincronizar();
        return editor;
    }

    function setarConteudo(el, html) {
        if (!el) {
            return;
        }
        if (el._launsEditor && typeof el._launsEditor.setHTML === 'function') {
            el._launsEditor.setHTML(htmlParaEditor(html));
            return;
        }
        el.innerHTML = htmlParaEditor(html);
    }

    global.LaunsJornadaEditor = {
        configurar: configurar,
        criar: criar,
        destruir: destruir,
        setarConteudo: setarConteudo,
        htmlDeElemento: htmlDeElemento,
        htmlDoEditor: htmlDoEditor,
        htmlParaEditor: htmlParaEditor,
        uploadImagem: enviarParaS3,
        enviarParaS3: enviarParaS3
    };
})(window);
