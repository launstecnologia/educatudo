<?php
/** Editor do layout da prova impressa. */
$pacote = is_array($pacote ?? null) ? $pacote : [];
$layout = is_array($pacote['layout'] ?? null) ? $pacote['layout'] : [];
$materias = is_array($pacote['materias'] ?? null) ? $pacote['materias'] : [];
$logo_url = (string) ($pacote['logo_url'] ?? '');
$nome_escola = (string) ($pacote['nome_escola'] ?? '');
$data_texto = (string) ($pacote['data_texto'] ?? '');
$horario_texto = (string) ($pacote['horario_texto'] ?? '');
$bloco = is_array($pacote['bloco'] ?? null) ? $pacote['bloco'] : [];
$blocoId = (int) ($bloco['id'] ?? 0);
$baseUrl = defined('URL') ? rtrim((string) URL, '/') : '';
$marcado = static function (string $chave) use ($layout): string {
    return !empty($layout[$chave]) ? ' checked' : '';
};
$flashTipo = (string) ($flash_type ?? '');
$flashMsg = (string) ($flash_message ?? '');
?>
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h2 class="text-2xl font-bold text-gray-900 mb-1">Layout da prova</h2>
        <p class="text-gray-600">Cabeçalho, logo, questões do professor ou da coordenação, tamanho das imagens e paginação.</p>
    </div>
    <a href="<?= $baseUrl ?>/admin/provas/blocos/<?= $blocoId ?>/gerenciar" class="text-gray-600 hover:text-gray-900">← Voltar</a>
</div>

<?php if ($flashMsg !== ''): ?>
<div class="mb-4 rounded-lg border px-4 py-3 text-sm <?= $flashTipo === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800' ?>">
    <?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<?php if (empty($pacote['coluna_pronta'])): ?>
<div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
    A prévia funciona agora. Para gravar o layout, rode a migration <span class="font-medium">2026_11_03_layout_prova_impressa</span> no Master.
</div>
<?php endif; ?>

<form method="post" action="<?= $baseUrl ?>/admin/provas/blocos/<?= $blocoId ?>/layout" id="form-layout" class="grid grid-cols-1 xl:grid-cols-[420px_minmax(0,1fr)] gap-6 items-start">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">1</span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Cabeçalho</p>
                    <p class="text-sm text-gray-500 mt-0.5">Logo, nome da escola e os campos que o aluno preenche.</p>
                </div>
            </div>
            <div class="p-5 space-y-4">
                <label class="flex items-center gap-2 text-sm text-gray-800">
                    <input type="hidden" name="mostrar_logo" value="0">
                    <input type="checkbox" name="mostrar_logo" value="1" class="js-visual"<?= $marcado('mostrar_logo') ?><?= $logo_url === '' ? ' disabled' : '' ?>>
                    Mostrar logo<?= $logo_url === '' ? ' (a escola ainda não tem logo)' : '' ?>
                </label>
                <label class="block text-sm text-gray-700">Altura da logo <span class="js-px" data-for="altura_logo"><?= (int) ($layout['altura_logo'] ?? 56) ?> px</span>
                    <input type="range" id="altura_logo" name="altura_logo" min="28" max="140" value="<?= (int) ($layout['altura_logo'] ?? 56) ?>" class="w-full js-visual">
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-800">
                    <input type="hidden" name="mostrar_nome_escola" value="0">
                    <input type="checkbox" name="mostrar_nome_escola" value="1" class="js-visual"<?= $marcado('mostrar_nome_escola') ?>>
                    Mostrar nome da escola
                </label>
                <label class="block text-sm text-gray-700">Título
                    <input type="text" name="titulo" maxlength="180" value="<?= htmlspecialchars((string) ($layout['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="js-visual mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg">
                </label>
                <label class="block text-sm text-gray-700">Subtítulo
                    <input type="text" name="subtitulo" maxlength="180" value="<?= htmlspecialchars((string) ($layout['subtitulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="js-visual mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg">
                </label>
                <div class="flex flex-wrap gap-4 text-sm text-gray-800">
                    <label class="flex items-center gap-2"><input type="hidden" name="mostrar_data" value="0"><input type="checkbox" name="mostrar_data" value="1" class="js-visual"<?= $marcado('mostrar_data') ?>> Data</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="mostrar_horario" value="0"><input type="checkbox" name="mostrar_horario" value="1" class="js-visual"<?= $marcado('mostrar_horario') ?>> Horário</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="campo_nome" value="0"><input type="checkbox" name="campo_nome" value="1" class="js-visual"<?= $marcado('campo_nome') ?>> Nome</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="campo_turma" value="0"><input type="checkbox" name="campo_turma" value="1" class="js-visual"<?= $marcado('campo_turma') ?>> Turma</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="campo_numero" value="0"><input type="checkbox" name="campo_numero" value="1" class="js-visual"<?= $marcado('campo_numero') ?>> Número</label>
                </div>
                <label class="block text-sm text-gray-700">Instruções
                    <textarea name="instrucoes" maxlength="800" rows="3" class="js-visual mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg"><?= htmlspecialchars((string) ($layout['instrucoes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </label>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">2</span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Questões</p>
                    <p class="text-sm text-gray-500 mt-0.5">As que o professor ou a coordenação já lançou. O número é o do gabarito.</p>
                </div>
            </div>
            <div class="p-5 space-y-5">
                <?php if ($materias === []): ?>
                <p class="text-sm text-gray-600">Ainda não há questões. O professor elabora a prova, ou a escola elabora em Gerenciar prova.</p>
                <?php endif; ?>
                <?php foreach ($materias as $materia): ?>
                <?php $mid = (int) ($materia['prova_id'] ?? 0); ?>
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars((string) ($materia['materia_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <span class="flex gap-2">
                            <?php if ((int) ($materia['prova_id'] ?? 0) > 0): ?>
                            <a href="<?= $baseUrl ?>/admin/provas/editar/<?= (int) $materia['prova_id'] ?>" class="text-xs text-teal-700 hover:underline">Abrir questões</a>
                            <?php endif; ?>
                            <button type="button" class="text-xs text-gray-600 hover:underline js-todas" data-mid="<?= $mid ?>" data-on="1">Marcar todas</button>
                        </span>
                    </div>
                    <?php if (($materia['questoes'] ?? []) === []): ?>
                    <p class="text-sm text-gray-500">Nenhuma questão nesta disciplina.</p>
                    <?php else: ?>
                    <ul class="space-y-2" data-lista="<?= $mid ?>">
                        <?php foreach ($materia['questoes'] as $questao): ?>
                        <?php $qid = (int) ($questao['id'] ?? 0); ?>
                        <li class="rounded-lg border border-gray-200 p-3" data-qid="<?= $qid ?>">
                            <input type="hidden" name="materias[<?= $mid ?>][ordem][]" value="<?= $qid ?>">
                            <label class="flex items-start gap-2 text-sm text-gray-800">
                                <input type="checkbox" class="mt-1 js-incluir js-visual" data-qid="<?= $qid ?>" name="materias[<?= $mid ?>][incluir][]" value="<?= $qid ?>"<?= !empty($questao['incluida']) ? ' checked' : '' ?>>
                                <span>
                                    <span class="font-medium"><?= !empty($questao['numero']) ? 'Questão ' . (int) $questao['numero'] : 'Discursiva' ?></span>
                                    <?= htmlspecialchars((string) ($questao['resumo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </label>
                            <div class="mt-2 flex flex-wrap items-center gap-3 pl-6 text-xs text-gray-600">
                                <button type="button" class="px-2 py-1 rounded border border-gray-300 js-mover" data-dir="-1">Subir</button>
                                <button type="button" class="px-2 py-1 rounded border border-gray-300 js-mover" data-dir="1">Descer</button>
                                <label class="flex items-center gap-1">
                                    <input type="checkbox" class="js-quebra js-visual" name="materias[<?= $mid ?>][quebra][]" value="<?= $qid ?>"<?= !empty($questao['quebra']) ? ' checked' : '' ?>>
                                    Quebrar página antes
                                </label>
                                <?php if (!empty($questao['tem_imagem'])): ?>
                                <label class="flex items-center gap-1">Imagem
                                    <input type="number" class="js-img js-visual w-16 px-2 py-1 border border-gray-300 rounded" data-qid="<?= $qid ?>"<?= (int) ($questao['altura_imagem'] ?? 180) !== (int) ($layout['altura_imagem'] ?? 180) ? ' data-manual="1"' : '' ?> name="materias[<?= $mid ?>][imagem][<?= $qid ?>]" min="60" max="480" value="<?= (int) ($questao['altura_imagem'] ?? 180) ?>">
                                    px
                                </label>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">3</span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Página</p>
                    <p class="text-sm text-gray-500 mt-0.5">Fonte, margem, imagens e número da página.</p>
                </div>
            </div>
            <div class="p-5 space-y-4">
                <label class="block text-sm text-gray-700">Tamanho da imagem <span class="js-px" data-for="altura_imagem"><?= (int) ($layout['altura_imagem'] ?? 180) ?> px</span>
                    <input type="range" id="altura_imagem" name="altura_imagem" min="60" max="480" value="<?= (int) ($layout['altura_imagem'] ?? 180) ?>" class="w-full js-visual">
                </label>
                <label class="block text-sm text-gray-700">Tamanho do texto <span class="js-px" data-for="tamanho_fonte"><?= (int) ($layout['tamanho_fonte'] ?? 12) ?> px</span>
                    <input type="range" id="tamanho_fonte" name="tamanho_fonte" min="10" max="16" value="<?= (int) ($layout['tamanho_fonte'] ?? 12) ?>" class="w-full js-visual">
                </label>
                <label class="block text-sm text-gray-700">Margem <span class="js-px" data-for="margem"><?= (int) ($layout['margem'] ?? 14) ?> mm</span>
                    <input type="range" id="margem" name="margem" min="8" max="24" value="<?= (int) ($layout['margem'] ?? 14) ?>" class="w-full js-visual">
                </label>
                <div class="flex flex-col gap-2 text-sm text-gray-800">
                    <label class="flex items-center gap-2"><input type="hidden" name="quebrar_disciplina" value="0"><input type="checkbox" name="quebrar_disciplina" value="1" id="quebrar_disciplina" class="js-visual"<?= $marcado('quebrar_disciplina') ?>> Nova página a cada disciplina</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="numero_pagina" value="0"><input type="checkbox" name="numero_pagina" value="1" class="js-visual"<?= $marcado('numero_pagina') ?>> Número da página</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="mostrar_pontos" value="0"><input type="checkbox" name="mostrar_pontos" value="1" class="js-visual"<?= $marcado('mostrar_pontos') ?>> Mostrar pontuação</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="mostrar_professor" value="0"><input type="checkbox" name="mostrar_professor" value="1" class="js-visual"<?= $marcado('mostrar_professor') ?>> Mostrar professor</label>
                </div>
                <label class="block text-sm text-gray-700">Rodapé
                    <input type="text" name="rodape" maxlength="120" value="<?= htmlspecialchars((string) ($layout['rodape'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="js-visual mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg">
                </label>
            </div>
        </section>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg font-medium">Salvar layout</button>
            <button type="submit" name="depois" value="imprimir" class="px-4 py-2 rounded-lg border border-gray-300 bg-white font-medium">Salvar e imprimir</button>
        </div>
    </div>

    <div class="xl:sticky xl:top-4">
        <p class="text-xs text-gray-500 mb-2">Prévia. A folha impressa usa este desenho.</p>
        <div class="rounded-xl bg-gray-100 p-4 overflow-auto">
            <?php include __DIR__ . '/_folha_layout.php'; ?>
        </div>
    </div>
</form>
<script>
(function () {
    var folha = document.getElementById('folha-prova');
    var form = document.getElementById('form-layout');
    if (!folha || !form) return;

    function campo(nome) {
        var marcado = form.querySelector('input[type="checkbox"][name="' + nome + '"]');
        if (marcado) return marcado.checked;
        var texto = form.querySelector('[name="' + nome + '"]');
        return texto ? texto.value : '';
    }

    function aplicar() {
        folha.classList.toggle('sem-logo', !campo('mostrar_logo'));
        folha.classList.toggle('sem-escola', !campo('mostrar_nome_escola'));
        folha.classList.toggle('sem-data', !campo('mostrar_data'));
        folha.classList.toggle('sem-horario', !campo('mostrar_horario'));
        folha.classList.toggle('sem-campo-nome', !campo('campo_nome'));
        folha.classList.toggle('sem-campo-turma', !campo('campo_turma'));
        folha.classList.toggle('sem-campo-numero', !campo('campo_numero'));
        folha.classList.toggle('sem-pontos', !campo('mostrar_pontos'));
        folha.classList.toggle('sem-professor', !campo('mostrar_professor'));
        folha.classList.toggle('sem-numero', !campo('numero_pagina'));
        folha.classList.toggle('sem-subtitulo', String(campo('subtitulo')).trim() === '');
        folha.classList.toggle('sem-instrucoes', String(campo('instrucoes')).trim() === '');
        folha.classList.toggle('sem-rodape', String(campo('rodape')).trim() === '');

        var titulo = document.getElementById('cab-titulo');
        var sub = document.getElementById('cab-subtitulo');
        var inst = document.getElementById('cab-instrucoes');
        var meta = document.getElementById('cab-meta');
        var rodape = document.getElementById('cab-rodape');
        if (titulo) titulo.textContent = campo('titulo');
        if (sub) sub.textContent = campo('subtitulo');
        if (inst) inst.textContent = campo('instrucoes');
        if (meta) {
            var partes = [];
            if (campo('mostrar_data') && folha.dataset.data) partes.push('Data: ' + folha.dataset.data);
            if (campo('mostrar_horario') && folha.dataset.hora) partes.push(folha.dataset.hora);
            meta.textContent = partes.join(' — ');
        }
        if (rodape) rodape.textContent = String(campo('rodape')).trim();

        folha.style.setProperty('--logo', campo('altura_logo') + 'px');
        folha.style.setProperty('--img', campo('altura_imagem') + 'px');
        folha.style.setProperty('--fonte', campo('tamanho_fonte') + 'px');
        folha.style.setProperty('--margem', campo('margem') + 'mm');
        document.querySelectorAll('.js-px').forEach(function (el) {
            var input = document.getElementById(el.getAttribute('data-for'));
            if (!input) return;
            var unidade = input.id === 'margem' ? ' mm' : ' px';
            el.textContent = input.value + unidade;
        });

        var alturaGlobal = parseInt(campo('altura_imagem'), 10) || 180;
        var primeiraMateria = true;
        folha.querySelectorAll('.materia').forEach(function (sec) {
            var visiveis = 0;
            var primeiraQuestao = true;
            sec.querySelectorAll('.questao').forEach(function (q) {
                var id = q.getAttribute('data-qid');
                var box = form.querySelector('.js-incluir[data-qid="' + id + '"]');
                var ligada = !box || box.checked;
                q.classList.toggle('oculta', !ligada);
                if (ligada) visiveis++;
                var img = form.querySelector('.js-img[data-qid="' + id + '"]');
                var altura = img ? parseInt(img.value, 10) : alturaGlobal;
                if (!altura || altura < 60) altura = alturaGlobal;
                q.style.setProperty('--qimg', altura + 'px');
                var quebra = form.querySelector('.js-quebra[value="' + id + '"]');
                q.classList.toggle('quebra-questao', !!(quebra && quebra.checked && ligada && !primeiraQuestao));
                if (ligada) primeiraQuestao = false;
            });
            sec.classList.toggle('oculta', visiveis === 0);
            var quebrar = document.getElementById('quebrar_disciplina');
            sec.classList.toggle('quebra-materia', !!(quebrar && quebrar.checked && !primeiraMateria && visiveis > 0));
            if (visiveis > 0) primeiraMateria = false;
        });
    }

    form.addEventListener('input', aplicar);
    form.addEventListener('change', aplicar);

    form.querySelectorAll('.js-mover').forEach(function (botao) {
        botao.addEventListener('click', function () {
            var item = botao.closest('li');
            var lista = item && item.parentElement;
            if (!item || !lista) return;
            var dir = parseInt(botao.getAttribute('data-dir'), 10);
            var irmao = dir < 0 ? item.previousElementSibling : item.nextElementSibling;
            if (!irmao) return;
            if (dir < 0) lista.insertBefore(item, irmao);
            else lista.insertBefore(irmao, item);
            var id = item.getAttribute('data-qid');
            var folhaQ = document.getElementById('q-folha-' + id);
            var sec = folhaQ && folhaQ.parentElement;
            if (folhaQ && sec) {
                var outroId = irmao.getAttribute('data-qid');
                var outro = document.getElementById('q-folha-' + outroId);
                if (outro) {
                    if (dir < 0) sec.insertBefore(folhaQ, outro);
                    else sec.insertBefore(outro, folhaQ);
                }
            }
            aplicar();
        });
    });

    form.querySelectorAll('.js-todas').forEach(function (botao) {
        botao.addEventListener('click', function () {
            var lista = form.querySelector('[data-lista="' + botao.getAttribute('data-mid') + '"]');
            if (!lista) return;
            lista.querySelectorAll('.js-incluir').forEach(function (box) { box.checked = true; });
            aplicar();
        });
    });

    var globalImg = document.getElementById('altura_imagem');
    if (globalImg) {
        globalImg.addEventListener('input', function () {
            form.querySelectorAll('.js-img').forEach(function (input) {
                if (input.dataset.manual === '1') return;
                input.value = globalImg.value;
            });
        });
    }
    form.querySelectorAll('.js-img').forEach(function (input) {
        input.addEventListener('input', function () { input.dataset.manual = '1'; });
    });

    aplicar();
})();
</script>
