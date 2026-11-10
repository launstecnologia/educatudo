(function () {
  'use strict';
  var campoModelo = document.getElementById('campo-modelo-boletim-documento');
  var fonte = document.getElementById('fonte-boletim-coord');
  if (campoModelo && fonte) {
    fonte.addEventListener('change', function () { campoModelo.hidden = fonte.value !== 'vida_escolar'; campoModelo.style.display = campoModelo.hidden ? 'none' : ''; });
  }
  var arquivos = [];
  document.querySelectorAll('[data-boletim-previa]').forEach(function (bloco) {
    var carregando = false;
    var pronto = false;
    bloco.addEventListener('toggle', function () {
      if (!bloco.open || carregando || pronto) return;
      carregando = true;
      var mensagem = bloco.querySelector('[data-boletim-mensagem]');
      var frame = bloco.querySelector('iframe');
      mensagem.textContent = 'Gerando o boletim deste aluno…';
      fetch(bloco.getAttribute('data-pdf-url'), { credentials: 'same-origin', headers: { Accept: 'application/pdf' } })
        .then(function (resposta) {
          if (!resposta.ok || (resposta.headers.get('Content-Type') || '').toLowerCase().indexOf('application/pdf') !== 0) {
            throw new Error('PDF indisponível');
          }
          return resposta.blob();
        }).then(function (pdf) {
          return pdf.slice(0, 5).text().then(function (assinatura) {
            if (assinatura !== '%PDF-') throw new Error('PDF inválido');
            var url = URL.createObjectURL(pdf);
            arquivos.push(url);
            frame.src = url;
            frame.hidden = false;
            mensagem.hidden = true;
            pronto = true;
          });
        }).catch(function () {
          mensagem.textContent = 'Não foi possível gerar o boletim com este modelo. Confira o boletim vinculado e o ano do aluno. Feche e abra esta seção para tentar novamente.';
        }).finally(function () { carregando = false; });
    });
  });
  window.addEventListener('pagehide', function () { arquivos.forEach(function (url) { URL.revokeObjectURL(url); }); });
})();
