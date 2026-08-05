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

    for (let i = 0; i < items.length; i++) {
        const item = items[i];
        const numero = i + 1;
        const tipo = item.querySelector('.aulas-tarde-tipo')?.value || '';
        if (!tipo) {
            alert('Atividade ' + numero + ': selecione o tipo.');
            return false;
        }

        if (tipo === 'exercicios_adicionais') {
            const pagina = item.querySelector('.aulas-tarde-pagina')?.value.trim() || '';
            const apostila = item.querySelector('.aulas-tarde-apostila')?.value.trim() || '';
            const exercicios = item.querySelector('.aulas-tarde-exercicios')?.value.trim() || '';
            if (!pagina || !apostila || !exercicios) {
                alert('Atividade ' + numero + ': preencha página, apostila e exercícios.');
                return false;
            }
        }

        if (tipo === 'jornadas') {
            const jornada = item.querySelector('.aulas-tarde-jornada-nome')?.value.trim() || '';
            if (!jornada) {
                alert('Atividade ' + numero + ': informe o nome da jornada.');
                return false;
            }
        }

        if (tipo === 'outros') {
            const descricao = item.querySelector('.aulas-tarde-descricao')?.value.trim() || '';
            if (!descricao) {
                alert('Atividade ' + numero + ': descreva a atividade.');
                return false;
            }
        }
    }

    return true;
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#aulas-tarde-atividades-list .aulas-tarde-tipo').forEach(function (select) {
        toggleAulasTardeCamposItem(select);
    });
    atualizarNumeracaoAulasTarde();
});
</script>
