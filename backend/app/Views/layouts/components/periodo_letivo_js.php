<?php
if (!class_exists('PeriodoLetivo')) {
    require_once dirname(__DIR__, 3) . '/Core/PeriodoLetivo.php';
}
$periodoLetivoMapa = PeriodoLetivo::mapaPorAno();
$periodoLetivoPadrao = PeriodoLetivo::doAno((int) date('Y'));
?>
<script>
window.PeriodoLetivoUI = {
    mapa: <?= json_encode($periodoLetivoMapa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    padrao: <?= json_encode($periodoLetivoPadrao, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    doAno: function (ano) {
        var n = parseInt(ano, 10) || 0;
        if (n > 0 && this.mapa && this.mapa[n]) {
            return this.mapa[n];
        }
        if (n > 0 && this.mapa && this.mapa[String(n)]) {
            return this.mapa[String(n)];
        }
        return this.padrao || { tipo: 'bimestre', quantidade: 4, rotulo_campo: 'Bimestre', rotulos: {1:'1º Bimestre',2:'2º Bimestre',3:'3º Bimestre',4:'4º Bimestre'} };
    },
    preencherSelect: function (select, ano, selecionado) {
        if (!select) return;
        var info = this.doAno(ano);
        var valorRotulo = select.getAttribute('data-periodo-valor') === 'rotulo';
        var keep = selecionado !== undefined && selecionado !== null
            ? String(selecionado)
            : String(select.value || '');
        var html = '';
        if (select.getAttribute('data-periodo-vazio') === '1') {
            html += '<option value="">' + (select.getAttribute('data-periodo-vazio-label') || 'Selecione') + '</option>';
        }
        if (select.getAttribute('data-periodo-todos') === '1') {
            html += '<option value="">' + (select.getAttribute('data-periodo-todos-label') || 'Todos') + '</option>';
        }
        var rotulos = info.rotulos || {};
        Object.keys(rotulos).forEach(function (k) {
            var lab = rotulos[k];
            var val = valorRotulo ? lab : String(k);
            html += '<option value="' + String(val).replace(/"/g, '&quot;') + '"' + (keep === String(val) ? ' selected' : '') + '>' + lab + '</option>';
        });
        select.innerHTML = html;
        if (keep && !Array.prototype.some.call(select.options, function (o) { return o.value === keep; })) {
            select.value = '';
        }
    },
    atualizarLabels: function (root, ano) {
        var info = this.doAno(ano);
        var campo = info.rotulo_campo || 'Bimestre';
        (root || document).querySelectorAll('[data-periodo-label]').forEach(function (el) {
            var sufixo = el.getAttribute('data-periodo-label-suffix') || '';
            var required = el.querySelector('span.text-red-500');
            el.childNodes.forEach(function (n) {
                if (n.nodeType === 3) {
                    n.textContent = campo + (sufixo ? ' ' + sufixo : ' ');
                }
            });
            if (!Array.prototype.some.call(el.childNodes, function (n) { return n.nodeType === 3 && String(n.textContent).trim() !== ''; })) {
                el.insertBefore(document.createTextNode(campo + (sufixo ? ' ' + sufixo : ' ')), el.firstChild);
            }
            if (required && required.parentNode !== el) {
                el.appendChild(required);
            }
        });
    },
    ligar: function (anoInput, select) {
        if (!anoInput || !select) return;
        var self = this;
        var sync = function () {
            self.preencherSelect(select, anoInput.value, select.value);
            var root = select.closest('form') || document;
            self.atualizarLabels(root, anoInput.value);
        };
        anoInput.addEventListener('change', sync);
        anoInput.addEventListener('input', sync);
    }
};

document.addEventListener('DOMContentLoaded', function () {
    var ui = window.PeriodoLetivoUI;
    if (!ui) return;
    document.querySelectorAll('[data-periodo-letivo-select]').forEach(function (select) {
        var form = select.closest('form') || document;
        var anoSel = form.querySelector('[data-periodo-ano], #ano_letivo, [name="ano_letivo"]');
        if (anoSel) {
            ui.ligar(anoSel, select);
            ui.preencherSelect(select, anoSel.value, select.getAttribute('data-periodo-selecionado') || select.value);
            ui.atualizarLabels(form, anoSel.value);
        }
    });
});
</script>
