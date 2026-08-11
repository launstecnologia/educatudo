<script>
(function () {
    function onlyDigits(value) {
        return (value || '').toString().replace(/\D/g, '');
    }

    function maskCpf(value) {
        var d = onlyDigits(value).slice(0, 11);
        if (d.length <= 3) return d;
        if (d.length <= 6) return d.slice(0, 3) + '.' + d.slice(3);
        if (d.length <= 9) return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6);
        return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6, 9) + '-' + d.slice(9);
    }

    function maskCep(value) {
        var d = onlyDigits(value).slice(0, 8);
        if (d.length <= 5) return d;
        return d.slice(0, 5) + '-' + d.slice(5);
    }

    function maskRg(value) {
        var raw = (value || '').toString().toUpperCase().replace(/[^0-9X]/g, '').slice(0, 12);
        if (raw.length <= 2) return raw;
        if (raw.length <= 5) return raw.slice(0, 2) + '.' + raw.slice(2);
        if (raw.length <= 8) return raw.slice(0, 2) + '.' + raw.slice(2, 5) + '.' + raw.slice(5);
        return raw.slice(0, 2) + '.' + raw.slice(2, 5) + '.' + raw.slice(5, 8) + '-' + raw.slice(8);
    }

    function maskTelefone(value) {
        var d = onlyDigits(value).slice(0, 10);
        if (d.length <= 2) return d.length ? '(' + d : d;
        if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
        return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
    }

    function maskCelular(value) {
        var d = onlyDigits(value).slice(0, 11);
        if (d.length <= 2) return d.length ? '(' + d : d;
        if (d.length <= 7) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
        return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
    }

    function bindMask(selector, maskFn) {
        document.querySelectorAll(selector).forEach(function (el) {
            el.addEventListener('input', function () {
                var start = el.selectionStart;
                var before = el.value;
                el.value = maskFn(el.value);
                var diff = el.value.length - before.length;
                el.setSelectionRange(Math.max(0, (start || 0) + diff), Math.max(0, (start || 0) + diff));
            });
        });
    }

    bindMask('.js-mask-cpf', maskCpf);
    bindMask('.js-mask-cep', maskCep);
    bindMask('.js-mask-rg', maskRg);
    bindMask('.js-mask-telefone', maskTelefone);
    bindMask('.js-mask-celular', maskCelular);

    window.studentFormNormalizeDocumentoEndereco = function (formData) {
        formData.set('cpf', onlyDigits(formData.get('cpf') || ''));
        formData.set('cep', onlyDigits(formData.get('cep') || ''));
        var rgRaw = (formData.get('rg') || '').toString().toUpperCase().replace(/[^0-9X]/g, '');
        formData.set('rg', rgRaw);
        formData.set('telefone', onlyDigits(formData.get('telefone') || ''));
        formData.set('celular', onlyDigits(formData.get('celular') || ''));
        var dataNasc = (formData.get('data_nasc') || '').toString().trim();
        if (dataNasc === '') {
            formData.delete('data_nasc');
        }
    };
})();
</script>
