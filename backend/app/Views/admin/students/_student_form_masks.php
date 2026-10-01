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

    var cepInput = document.getElementById('cep');
    var cepBtn = document.getElementById('btn-busca-cep');
    var cepStatus = document.getElementById('cep-status');
    if (cepInput && cepBtn) {
        function preencherEnderecoPorCep() {
            var digits = onlyDigits(cepInput.value);
            if (digits.length !== 8) {
                if (cepStatus) cepStatus.textContent = 'Informe um CEP com 8 dígitos.';
                return;
            }
            cepBtn.disabled = true;
            if (cepStatus) cepStatus.textContent = 'Buscando...';
            fetch('https://viacep.com.br/ws/' + digits + '/json/')
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data || data.erro) {
                        if (cepStatus) cepStatus.textContent = 'CEP não encontrado.';
                        return;
                    }
                    var logradouro = document.getElementById('logradouro');
                    var bairro = document.getElementById('bairro');
                    var cidade = document.getElementById('cidade');
                    var uf = document.getElementById('uf');
                    if (logradouro && data.logradouro) logradouro.value = data.logradouro;
                    if (bairro && data.bairro) bairro.value = data.bairro;
                    if (cidade && data.localidade) cidade.value = data.localidade;
                    if (uf && data.uf) uf.value = data.uf;
                    if (cepStatus) cepStatus.textContent = 'Endereço preenchido.';
                    var numero = document.getElementById('numero');
                    if (numero) numero.focus();
                })
                .catch(function () {
                    if (cepStatus) cepStatus.textContent = 'Não foi possível consultar o CEP.';
                })
                .finally(function () {
                    cepBtn.disabled = false;
                });
        }

        cepBtn.addEventListener('click', preencherEnderecoPorCep);
        cepInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                preencherEnderecoPorCep();
            }
        });
    }

    var ufNascimento = document.getElementById('uf_nascimento');
    var naturalidade = document.getElementById('naturalidade');
    if (ufNascimento && naturalidade) {
        var cidadeSalva = naturalidade.getAttribute('data-cidade') || '';
        var pedidoCidades = 0;

        function normalizarNome(valor) {
            return (valor || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
        }

        function opcaoCidade(valor, texto, selecionada) {
            var option = document.createElement('option');
            option.value = valor;
            option.textContent = texto;
            if (selecionada) option.selected = true;
            return option;
        }

        function carregarCidadesNascimento(uf, manter) {
            var pedido = ++pedidoCidades;
            naturalidade.innerHTML = '';
            naturalidade.appendChild(opcaoCidade('', uf ? 'Carregando cidades...' : 'Selecione o estado', !manter));
            if (manter) {
                naturalidade.appendChild(opcaoCidade(manter, manter, true));
            }
            if (!uf) return;

            fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados/' + encodeURIComponent(uf) + '/municipios?orderBy=nome')
                .then(function (response) { return response.json(); })
                .then(function (lista) {
                    if (pedido !== pedidoCidades) return;
                    naturalidade.innerHTML = '';
                    naturalidade.appendChild(opcaoCidade('', 'Selecione', false));
                    var alvo = normalizarNome(manter);
                    var achou = false;
                    (lista || []).forEach(function (municipio) {
                        var nome = (municipio && municipio.nome) ? municipio.nome : '';
                        if (!nome) return;
                        var selecionada = alvo !== '' && normalizarNome(nome) === alvo;
                        if (selecionada) achou = true;
                        naturalidade.appendChild(opcaoCidade(nome, nome, selecionada));
                    });
                    if (manter && !achou) {
                        naturalidade.appendChild(opcaoCidade(manter, manter, true));
                    }
                })
                .catch(function () {
                    if (pedido !== pedidoCidades) return;
                    naturalidade.innerHTML = '';
                    naturalidade.appendChild(opcaoCidade('', 'Não foi possível carregar as cidades', !manter));
                    if (manter) naturalidade.appendChild(opcaoCidade(manter, manter, true));
                });
        }

        ufNascimento.addEventListener('change', function () {
            carregarCidadesNascimento(ufNascimento.value, '');
        });
        if (ufNascimento.value) {
            carregarCidadesNascimento(ufNascimento.value, cidadeSalva);
        }
    }

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
