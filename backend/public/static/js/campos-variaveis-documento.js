(function () {
  'use strict';

  function digitos(valor) {
    return String(valor || '').replace(/\D/g, '');
  }

  function mascaraCpf(valor) {
    var d = digitos(valor).slice(0, 11);
    var out = d.slice(0, 3);
    if (d.length > 3) out += '.' + d.slice(3, 6);
    if (d.length > 6) out += '.' + d.slice(6, 9);
    if (d.length > 9) out += '-' + d.slice(9, 11);
    return out;
  }

  function mascaraTelefone(valor) {
    var d = digitos(valor).slice(0, 11);
    if (!d) return '';
    if (d.length <= 2) return '(' + d;
    if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
    if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
    return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
  }

  function mascaraValor(valor) {
    var d = digitos(valor).replace(/^0+/, '');
    if (!d) return '';
    while (d.length < 3) d = '0' + d;
    var centavos = d.slice(-2);
    var inteiro = d.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return inteiro + ',' + centavos;
  }

  function mascaraNumero(valor) {
    var d = digitos(valor).replace(/^0+(?=\d)/, '');
    if (!d) return '';
    return d.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function formatar(tipo, valor) {
    if (tipo === 'cpf') return mascaraCpf(valor);
    if (tipo === 'telefone') return mascaraTelefone(valor);
    if (tipo === 'valor') return mascaraValor(valor);
    if (tipo === 'numero') return mascaraNumero(valor);
    return valor;
  }

  function ligar(raiz) {
    var base = raiz || document;
    base.querySelectorAll('[data-mascara]').forEach(function (input) {
      if (input._cvMascara) return;
      var tipo = input.getAttribute('data-mascara') || '';
      if (tipo === 'data' || tipo === 'hora' || tipo === 'texto') return;
      input._cvMascara = true;
      input.addEventListener('input', function () {
        var proximo = formatar(tipo, input.value);
        if (proximo !== input.value) input.value = proximo;
      });
    });
  }

  window.CamposVariaveisDocumento = { ligar: ligar };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { ligar(document); });
  } else {
    ligar(document);
  }
})();
