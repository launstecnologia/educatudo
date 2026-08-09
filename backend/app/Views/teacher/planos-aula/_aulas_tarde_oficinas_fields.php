<?php
require_once __DIR__ . '/../../../Helpers/LessonPlanAfternoonHelper.php';

$aulasTardeAtividades = $aulas_tarde_atividades ?? LessonPlanAfternoonHelper::parseList($aulas_tarde_raw ?? null);
if (!is_array($aulasTardeAtividades) || $aulasTardeAtividades === []) {
    $aulasTardeAtividades = [['tipo' => '']];
}
$aulasTardeTipos = LessonPlanAfternoonHelper::tipos();
?>

<div class="border-b border-gray-200 pb-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Aulas da Tarde (Oficinas de Aprendizagem / Salas de Estudo)</h3>
        <button type="button"
                onclick="adicionarAulasTardeAtividade()"
                class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors">
            + Adicionar atividade
        </button>
    </div>

    <div id="aulas-tarde-atividades-list" class="space-y-4">
        <?php foreach ($aulasTardeAtividades as $index => $atividade): ?>
            <?php
            $tipo = (string) ($atividade['tipo'] ?? '');
            include __DIR__ . '/_aulas_tarde_atividade_item.php';
            ?>
        <?php endforeach; ?>
    </div>
</div>

<template id="aulas-tarde-atividade-template">
    <?php
    $index = '__INDEX__';
    $atividade = ['tipo' => ''];
    $tipo = '';
    ob_start();
    include __DIR__ . '/_aulas_tarde_atividade_item.php';
    echo ob_get_clean();
    ?>
</template>

<script>
const AULAS_TARDE_TIPOS = <?= json_encode($aulasTardeTipos, JSON_UNESCAPED_UNICODE) ?>;

function limparErroCampoObrigatorio(el) {
    if (!el) return;
    el.classList.remove('border-red-500', 'bg-red-50', 'focus:ring-red-500', 'focus:border-red-500');
    el.classList.add('border-gray-300', 'focus:ring-blue-500');
    el.removeAttribute('aria-invalid');
    const wrapper = el.closest('div');
    const msg = wrapper?.querySelector('[data-field-error]');
    if (msg) {
        msg.remove();
    }
}

function marcarErroCampoObrigatorio(el, mensagem) {
    if (!el) return;
    el.classList.remove('border-gray-300', 'focus:ring-blue-500');
    el.classList.add('border-red-500', 'bg-red-50', 'focus:ring-red-500', 'focus:border-red-500');
    el.setAttribute('aria-invalid', 'true');

    const wrapper = el.closest('div');
    if (wrapper && !wrapper.querySelector('[data-field-error]')) {
        const msg = document.createElement('p');
        msg.setAttribute('data-field-error', '1');
        msg.className = 'mt-1 text-xs font-medium text-red-600';
        msg.textContent = mensagem || 'Campo obrigatório.';
        wrapper.appendChild(msg);
    }
}

function focarPrimeiroCampoComErro(scope) {
    const campo = (scope || document).querySelector('[aria-invalid="true"]');
    if (!campo) return;
    campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(function () {
        campo.focus({ preventScroll: true });
    }, 250);
}

function atualizarNumeracaoAulasTarde() {
    document.querySelectorAll('#aulas-tarde-atividades-list .aulas-tarde-atividade-item').forEach(function (item, idx) {
        const titulo = item.querySelector('.aulas-tarde-atividade-titulo');
        if (titulo) {
            titulo.textContent = 'Atividade ' + (idx + 1);
        }
        const removerBtn = item.querySelector('.aulas-tarde-remover-btn');
        if (removerBtn) {
            removerBtn.classList.toggle('hidden', idx === 0 && document.querySelectorAll('#aulas-tarde-atividades-list .aulas-tarde-atividade-item').length === 1);
        }
    });
}

function toggleAulasTardeCamposItem(selectEl) {
    const item = selectEl.closest('.aulas-tarde-atividade-item');
    if (!item) return;

    const tipo = selectEl.value || '';
    limparErroCampoObrigatorio(selectEl);
    const blocoExercicios = item.querySelector('.aulas-tarde-campos-exercicios');
    const blocoJornadas = item.querySelector('.aulas-tarde-campos-jornadas');
    const blocoOutros = item.querySelector('.aulas-tarde-campos-outros');

    item.querySelectorAll('[data-aulas-tarde-required]').forEach(function (el) {
        el.removeAttribute('required');
    });

    blocoExercicios?.classList.add('hidden');
    blocoJornadas?.classList.add('hidden');
    blocoOutros?.classList.add('hidden');

    if (tipo === 'exercicios_adicionais') {
        blocoExercicios?.classList.remove('hidden');
        blocoExercicios?.querySelectorAll('[data-aulas-tarde-required]').forEach(function (el) {
            el.setAttribute('required', 'required');
        });
    } else if (tipo === 'jornadas') {
        blocoJornadas?.classList.remove('hidden');
        blocoJornadas?.querySelectorAll('[data-aulas-tarde-required]').forEach(function (el) {
            el.setAttribute('required', 'required');
        });
    } else if (tipo === 'outros') {
        blocoOutros?.classList.remove('hidden');
        blocoOutros?.querySelectorAll('[data-aulas-tarde-required]').forEach(function (el) {
            el.setAttribute('required', 'required');
        });
    }
}

function adicionarAulasTardeAtividade() {
    const template = document.getElementById('aulas-tarde-atividade-template');
    const list = document.getElementById('aulas-tarde-atividades-list');
    if (!template || !list) return;

    const index = list.querySelectorAll('.aulas-tarde-atividade-item').length;
    const html = template.innerHTML.replace(/__INDEX__/g, String(index));
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html.trim();
    const item = wrapper.firstElementChild;
    if (!item) return;

    list.appendChild(item);
    const select = item.querySelector('.aulas-tarde-tipo');
    if (select) {
        toggleAulasTardeCamposItem(select);
    }
    atualizarNumeracaoAulasTarde();
}

function removerAulasTardeAtividade(btn) {
    const list = document.getElementById('aulas-tarde-atividades-list');
    const items = list?.querySelectorAll('.aulas-tarde-atividade-item') || [];
    if (items.length <= 1) {
        alert('É necessário ter pelo menos uma atividade.');
        return;
    }
    btn.closest('.aulas-tarde-atividade-item')?.remove();
    atualizarNumeracaoAulasTarde();
}

function coletarAulasTardeAtividades() {
    const atividades = [];
    document.querySelectorAll('#aulas-tarde-atividades-list .aulas-tarde-atividade-item').forEach(function (item) {
        const tipo = item.querySelector('.aulas-tarde-tipo')?.value || '';
        if (!tipo) return;

        const payload = { tipo: tipo };
        if (tipo === 'exercicios_adicionais') {
            payload.pagina = item.querySelector('.aulas-tarde-pagina')?.value.trim() || '';
            payload.apostila = item.querySelector('.aulas-tarde-apostila')?.value.trim() || '';
            payload.exercicios = item.querySelector('.aulas-tarde-exercicios')?.value.trim() || '';
        } else if (tipo === 'jornadas') {
            payload.jornada_nome = item.querySelector('.aulas-tarde-jornada-nome')?.value.trim() || '';
        } else if (tipo === 'outros') {
            payload.descricao = item.querySelector('.aulas-tarde-descricao')?.value.trim() || '';
        }
        atividades.push(payload);
    });
    return atividades;
}

function validarAulasTardeOficinas() {
    const items = document.querySelectorAll('#aulas-tarde-atividades-list .aulas-tarde-atividade-item');
    if (!items.length) {
        alert('Adicione pelo menos uma atividade.');
        return false;
    }

    items.forEach(function (item) {
        item.querySelectorAll('.aulas-tarde-tipo, [data-aulas-tarde-required]').forEach(limparErroCampoObrigatorio);
    });
    let primeiroErro = null;

    function marcar(el, mensagem) {
        if (!primeiroErro) {
            primeiroErro = el;
        }
        marcarErroCampoObrigatorio(el, mensagem);
    }

    for (let i = 0; i < items.length; i++) {
        const item = items[i];
        const numero = i + 1;
        const tipoEl = item.querySelector('.aulas-tarde-tipo');
        const tipo = tipoEl?.value || '';
        if (!tipo) {
            marcar(tipoEl, 'Selecione o tipo da atividade ' + numero + '.');
            continue;
        }

        if (tipo === 'exercicios_adicionais') {
            const paginaEl = item.querySelector('.aulas-tarde-pagina');
            const apostilaEl = item.querySelector('.aulas-tarde-apostila');
            const exerciciosEl = item.querySelector('.aulas-tarde-exercicios');
            if (!(paginaEl?.value || '').trim()) {
                marcar(paginaEl, 'Informe a página.');
            }
            if (!(apostilaEl?.value || '').trim()) {
                marcar(apostilaEl, 'Informe a apostila.');
            }
            if (!(exerciciosEl?.value || '').trim()) {
                marcar(exerciciosEl, 'Informe quais exercícios.');
            }
        }

        if (tipo === 'jornadas') {
            const jornadaEl = item.querySelector('.aulas-tarde-jornada-nome');
            if (!(jornadaEl?.value || '').trim()) {
                marcar(jornadaEl, 'Informe o nome da jornada.');
            }
        }

        if (tipo === 'outros') {
            const descricaoEl = item.querySelector('.aulas-tarde-descricao');
            if (!(descricaoEl?.value || '').trim()) {
                marcar(descricaoEl, 'Descreva a atividade.');
            }
        }
    }

    if (primeiroErro) {
        focarPrimeiroCampoComErro(document.getElementById('aulas-tarde-atividades-list'));
        return false;
    }

    return true;
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#aulas-tarde-atividades-list .aulas-tarde-tipo').forEach(function (select) {
        toggleAulasTardeCamposItem(select);
    });
    atualizarNumeracaoAulasTarde();
    document.getElementById('aulas-tarde-atividades-list')?.addEventListener('input', function (e) {
        if (e.target.matches('.aulas-tarde-tipo, [data-aulas-tarde-required]')) {
            limparErroCampoObrigatorio(e.target);
        }
    });
    document.getElementById('aulas-tarde-atividades-list')?.addEventListener('change', function (e) {
        if (e.target.matches('.aulas-tarde-tipo, [data-aulas-tarde-required]')) {
            limparErroCampoObrigatorio(e.target);
        }
    });
});
</script>
