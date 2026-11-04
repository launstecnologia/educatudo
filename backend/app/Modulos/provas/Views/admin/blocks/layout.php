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
$escolhido = static function (string $chave, string $valor) use ($layout): string {
    return (string) ($layout[$chave] ?? '') === $valor ? ' selected' : '';
};
$flashTipo = (string) ($flash_type ?? '');
$flashMsg = (string) ($flash_message ?? '');
?>
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h2 class="text-2xl font-bold text-gray-900 mb-1">Layout da prova</h2>
        <p class="text-gray-600">A folha é o documento. Clique no texto para escrever e use a coluna ao lado para logo, espaço e páginas.</p>
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

<style>
    .editor-layout { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 16px; align-items: start; }
    .editor-lateral { position: sticky; top: 12px; max-height: calc(100vh - 24px); overflow: auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,.04); padding: 14px; }
    .faixa-layout { display: flex; flex-direction: column; gap: 14px; }
    .faixa-grupo { display: flex; flex-direction: column; align-items: stretch; gap: 10px; padding-bottom: 14px; border-bottom: 1px solid #e5e7eb; }
    .faixa-grupo:last-child { border-bottom: 0; padding-bottom: 0; }
    .faixa-grupo .w-24, .faixa-grupo .w-28 { width: 100%; }
    .faixa-checks { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 10px; }
    .editor-acoes { display: flex; flex-direction: column; gap: 8px; }
    .editor-acoes button { width: 100%; }
    @media (max-width: 1100px) {
        .editor-layout { grid-template-columns: 1fr; }
        .editor-lateral { position: static; max-height: none; }
    }
    .folha-prova.modo-editor.sem-instrucoes .instrucoes,
    .folha-prova.modo-editor.sem-subtitulo .subtitulo,
    .folha-prova.modo-editor.sem-rodape .rodape-tela { display: block !important; }
    .folha-prova.modo-editor [contenteditable="true"] { min-height: 1.2em; outline: 1px dashed transparent; cursor: text; border-radius: 2px; }
    .folha-prova.modo-editor [contenteditable="true"]:hover,
    .folha-prova.modo-editor [contenteditable="true"]:focus { outline-color: #94a3b8; background: #f8fafc; }
    .folha-prova.modo-editor [contenteditable="true"]:empty::before { content: attr(data-placeholder); color: #94a3b8; font-style: italic; }
    .folha-prova.modo-editor .questao.fora-da-prova { opacity: .45; }
    .folha-prova.modo-editor .questao { position: relative; }
    .barra-questao { position: absolute; z-index: 2; top: 0; right: 0; display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 4px; max-width: 70%; margin: 0; padding: 2px; background: rgba(255,255,255,.94); border: 1px solid #e5e7eb; border-radius: 8px; font-family: system-ui, sans-serif; }
    .barra-questao button { border: 1px solid #d1d5db; background: #fff; color: #111827; border-radius: 6px; padding: 3px 8px; font-size: 12px; line-height: 1.3; cursor: pointer; }
    .barra-questao button.ativa { border-color: var(--primary-color, #111); font-weight: 700; }
</style>
<form method="post" action="<?= $baseUrl ?>/admin/provas/blocos/<?= $blocoId ?>/layout" id="form-layout">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <div class="editor-layout">
    <div>
    <div class="rounded-xl bg-slate-200 px-3 py-8 md:px-6">
        <?php include __DIR__ . '/_folha_layout.php'; ?>
    </div>
    </div>
    <aside class="editor-lateral">
        <div class="editor-acoes mb-4">
            <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium">Salvar</button>
            <button type="submit" name="depois" value="imprimir" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium">Salvar e imprimir</button>
        </div>
        <div class="faixa-layout">
            <div class="faixa-grupo">
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-800 mb-1">
                    <input type="hidden" name="mostrar_logo" value="0">
                    <input type="checkbox" name="mostrar_logo" value="1" class="js-visual"<?= $marcado('mostrar_logo') ?><?= $logo_url === '' ? ' disabled' : '' ?>>
                    Logo
                </label>
                <label class="flex flex-col gap-1 text-[11px] text-gray-500 w-24">Altura <span class="js-px text-gray-800" data-for="altura_logo"><?= (int) ($layout['altura_logo'] ?? 56) ?> px</span>
                    <input type="range" id="altura_logo" name="altura_logo" min="28" max="140" value="<?= (int) ($layout['altura_logo'] ?? 56) ?>" class="w-full js-visual">
                </label>
                <label class="flex flex-col gap-1 text-[11px] text-gray-500">Posição
                    <select name="posicao_logo" class="js-visual px-2 py-1 border border-gray-300 rounded-lg bg-white text-sm text-gray-900">
                        <option value="esquerda"<?= $escolhido('posicao_logo', 'esquerda') ?>>Esquerda</option>
                        <option value="centro"<?= $escolhido('posicao_logo', 'centro') || (string) ($layout['posicao_logo'] ?? '') === '' ? ' selected' : '' ?>>Centro</option>
                        <option value="direita"<?= $escolhido('posicao_logo', 'direita') ?>>Direita</option>
                    </select>
                </label>
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-800 mb-1">
                    <input type="hidden" name="mostrar_nome_escola" value="0">
                    <input type="checkbox" name="mostrar_nome_escola" value="1" class="js-visual"<?= $marcado('mostrar_nome_escola') ?>>
                    Escola
                </label>
            </div>
            <div class="faixa-grupo">
                <label class="flex flex-col gap-1 text-[11px] text-gray-500 w-24">Texto <span class="js-px text-gray-800" data-for="tamanho_fonte"><?= (int) ($layout['tamanho_fonte'] ?? 12) ?> px</span>
                    <input type="range" id="tamanho_fonte" name="tamanho_fonte" min="10" max="16" value="<?= (int) ($layout['tamanho_fonte'] ?? 12) ?>" class="w-full js-visual">
                </label>
                <label class="flex flex-col gap-1 text-[11px] text-gray-500 w-28">Questões <span class="js-px text-gray-800" data-for="espaco_questao"><?= (int) ($layout['espaco_questao'] ?? 12) ?> px</span>
                    <input type="range" id="espaco_questao" name="espaco_questao" min="4" max="48" value="<?= (int) ($layout['espaco_questao'] ?? 12) ?>" class="w-full js-visual">
                </label>
                <label class="flex flex-col gap-1 text-[11px] text-gray-500 w-28">Alternativas <span class="js-px text-gray-800" data-for="espaco_alternativa"><?= (int) ($layout['espaco_alternativa'] ?? 4) ?> px</span>
                    <input type="range" id="espaco_alternativa" name="espaco_alternativa" min="0" max="24" value="<?= (int) ($layout['espaco_alternativa'] ?? 4) ?>" class="w-full js-visual">
                </label>
                <label class="flex flex-col gap-1 text-[11px] text-gray-500 w-24">Margem <span class="js-px text-gray-800" data-for="margem"><?= (int) ($layout['margem'] ?? 14) ?> mm</span>
                    <input type="range" id="margem" name="margem" min="8" max="24" value="<?= (int) ($layout['margem'] ?? 14) ?>" class="w-full js-visual">
                </label>
                <label class="flex flex-col gap-1 text-[11px] text-gray-500 w-24">Imagem <span class="js-px text-gray-800" data-for="altura_imagem"><?= (int) ($layout['altura_imagem'] ?? 180) ?> px</span>
                    <input type="range" id="altura_imagem" name="altura_imagem" min="60" max="480" value="<?= (int) ($layout['altura_imagem'] ?? 180) ?>" class="w-full js-visual">
                </label>
            </div>
            <div class="faixa-grupo faixa-checks">
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-800"><input type="hidden" name="mostrar_data" value="0"><input type="checkbox" name="mostrar_data" value="1" class="js-visual"<?= $marcado('mostrar_data') ?>> Data</label>
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-800"><input type="hidden" name="mostrar_horario" value="0"><input type="checkbox" name="mostrar_horario" value="1" class="js-visual"<?= $marcado('mostrar_horario') ?>> Horário</label>
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-800"><input type="hidden" name="campo_nome" value="0"><input type="checkbox" name="campo_nome" value="1" class="js-visual"<?= $marcado('campo_nome') ?>> Nome</label>
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-800"><input type="hidden" name="campo_turma" value="0"><input type="checkbox" name="campo_turma" value="1" class="js-visual"<?= $marcado('campo_turma') ?>> Turma</label>
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-800"><input type="hidden" name="campo_numero" value="0"><input type="checkbox" name="campo_numero" value="1" class="js-visual"<?= $marcado('campo_numero') ?>> Número</label>
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-800"><input type="hidden" name="mostrar_pontos" value="0"><input type="checkbox" name="mostrar_pontos" value="1" class="js-visual"<?= $marcado('mostrar_pontos') ?>> Pontos</label>
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-800"><input type="hidden" name="mostrar_professor" value="0"><input type="checkbox" name="mostrar_professor" value="1" class="js-visual"<?= $marcado('mostrar_professor') ?>> Professor</label>
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-800"><input type="hidden" name="numero_pagina" value="0"><input type="checkbox" name="numero_pagina" value="1" class="js-visual"<?= $marcado('numero_pagina') ?>> Nº da página</label>
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-800 col-span-2"><input type="hidden" name="quebrar_disciplina" value="0"><input type="checkbox" name="quebrar_disciplina" value="1" id="quebrar_disciplina" class="js-visual"<?= $marcado('quebrar_disciplina') ?>> Página por disciplina</label>
                <label class="flex flex-col gap-1 text-[11px] text-gray-500 col-span-2">Texto extra
                    <select name="texto_extra_lugar" class="js-visual px-2 py-1 border border-gray-300 rounded-lg bg-white text-sm text-gray-900">
                        <option value="cabecalho"<?= $escolhido('texto_extra_lugar', 'cabecalho') || (string) ($layout['texto_extra_lugar'] ?? '') === '' ? ' selected' : '' ?>>No cabeçalho</option>
                        <option value="final"<?= $escolhido('texto_extra_lugar', 'final') ?>>No final</option>
                    </select>
                </label>
            </div>
            <?php if ($materias !== []): ?>
            <div class="faixa-grupo">
                <?php foreach ($materias as $materiaLink): ?>
                    <?php if ((int) ($materiaLink['prova_id'] ?? 0) > 0): ?>
                    <a href="<?= $baseUrl ?>/admin/provas/editar/<?= (int) $materiaLink['prova_id'] ?>" class="text-sm text-gray-700 hover:text-gray-900 hover:underline"><?= htmlspecialchars((string) ($materiaLink['materia_nome'] ?? 'Questões'), ENT_QUOTES, 'UTF-8') ?> — editar questões</a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </aside>
    </div>

    <div class="hidden">
        <input type="text" name="titulo" maxlength="180" value="<?= htmlspecialchars((string) ($layout['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="js-visual">
        <input type="text" name="subtitulo" maxlength="180" value="<?= htmlspecialchars((string) ($layout['subtitulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="js-visual">
        <textarea name="instrucoes" maxlength="800" class="js-visual"><?= htmlspecialchars((string) ($layout['instrucoes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        <textarea name="texto_extra" maxlength="800" class="js-visual"><?= htmlspecialchars((string) ($layout['texto_extra'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        <input type="text" name="rodape" maxlength="120" value="<?= htmlspecialchars((string) ($layout['rodape'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="js-visual">
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
                                    Começar na próxima página
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
</form>
<script>
(function () {
    var folha = document.getElementById('folha-prova');
    var form = document.getElementById('form-layout');
    if (!folha || !form) return;
    folha.classList.add('modo-editor');

    function campo(nome) {
        var marcado = form.querySelector('input[type="checkbox"][name="' + nome + '"]');
        if (marcado) return marcado.checked;
        var texto = form.querySelector('[name="' + nome + '"]');
        return texto ? texto.value : '';
    }

    function escrever(el, valor) {
        if (!el || document.activeElement === el) return;
        if (el.textContent !== valor) el.textContent = valor;
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

        escrever(document.getElementById('cab-titulo'), campo('titulo'));
        escrever(document.getElementById('cab-subtitulo'), campo('subtitulo'));
        escrever(document.getElementById('cab-instrucoes'), campo('instrucoes'));
        escrever(document.getElementById('cab-rodape'), String(campo('rodape')).trim());
        var meta = document.getElementById('cab-meta');
        if (meta) {
            var partes = [];
            if (campo('mostrar_data') && folha.dataset.data) partes.push('Data: ' + folha.dataset.data);
            if (campo('mostrar_horario') && folha.dataset.hora) partes.push(folha.dataset.hora);
            meta.textContent = partes.join(' — ');
        }

        folha.style.setProperty('--logo', campo('altura_logo') + 'px');
        folha.style.setProperty('--img', campo('altura_imagem') + 'px');
        folha.style.setProperty('--fonte', campo('tamanho_fonte') + 'px');
        folha.style.setProperty('--margem', campo('margem') + 'mm');
        folha.style.setProperty('--espaco-questao', (campo('espaco_questao') || 12) + 'px');
        folha.style.setProperty('--espaco-alt', (campo('espaco_alternativa') === '' ? 4 : campo('espaco_alternativa')) + 'px');
        folha.classList.remove('logo-esquerda', 'logo-centro', 'logo-direita');
        var posicaoLogo = campo('posicao_logo') || 'centro';
        if (posicaoLogo !== 'esquerda' && posicaoLogo !== 'direita') posicaoLogo = 'centro';
        folha.classList.add('logo-' + posicaoLogo);
        var textoExtra = String(campo('texto_extra')).trim();
        var noFinal = campo('texto_extra_lugar') === 'final';
        var extraCab = document.getElementById('cab-texto-extra');
        var extraFim = document.getElementById('fim-texto-extra');
        if (extraCab) {
            escrever(extraCab, noFinal ? '' : textoExtra);
            extraCab.classList.toggle('oculta', noFinal);
        }
        if (extraFim) {
            escrever(extraFim, noFinal ? textoExtra : '');
            extraFim.classList.toggle('oculta', !noFinal);
        }
        document.querySelectorAll('.js-px').forEach(function (el) {
            var input = document.getElementById(el.getAttribute('data-for'));
            if (!input) return;
            var unidade = input.id === 'margem' ? ' mm' : ' px';
            el.textContent = input.value + unidade;
        });

        var alturaGlobal = parseInt(campo('altura_imagem'), 10) || 180;
        var primeiraMateria = true;
        var primeiraQuestaoProva = true;
        folha.querySelectorAll('.materia').forEach(function (sec) {
            var visiveis = 0;
            sec.querySelectorAll('.questao').forEach(function (q) {
                var id = q.getAttribute('data-qid');
                var box = form.querySelector('.js-incluir[data-qid="' + id + '"]');
                var ligada = !box || box.checked;
                q.classList.remove('oculta');
                q.classList.toggle('fora-da-prova', !ligada);
                if (ligada) visiveis++;
                var img = form.querySelector('.js-img[data-qid="' + id + '"]');
                var altura = img ? parseInt(img.value, 10) : alturaGlobal;
                if (!altura || altura < 60) altura = alturaGlobal;
                q.style.setProperty('--qimg', altura + 'px');
                var quebra = form.querySelector('.js-quebra[value="' + id + '"]');
                var quebraAtiva = !!(quebra && quebra.checked && ligada && !primeiraQuestaoProva);
                q.classList.toggle('quebra-questao', quebraAtiva);
                if (ligada) primeiraQuestaoProva = false;
                var botaoPagina = q.querySelector('[data-acao="pagina"]');
                var botaoFora = q.querySelector('[data-acao="fora"]');
                if (botaoPagina) botaoPagina.classList.toggle('ativa', !!(quebra && quebra.checked));
                if (botaoFora) botaoFora.textContent = ligada ? 'Tirar da prova' : 'Incluir na prova';
            });
            sec.classList.toggle('oculta', visiveis === 0 && sec.querySelectorAll('.questao').length === 0);
            var quebrar = document.getElementById('quebrar_disciplina');
            sec.classList.toggle('quebra-materia', !!(quebrar && quebrar.checked && !primeiraMateria && visiveis > 0));
            if (visiveis > 0) primeiraMateria = false;
        });
        if (!(document.activeElement && document.activeElement.isContentEditable) && window.paginarFolha) {
            window.paginarFolha();
        }
    }

    function ligarTexto(el, nome, max) {
        if (!el) return;
        el.contentEditable = 'true';
        el.addEventListener('input', function () {
            var valor = (el.innerText || '').replace(/\u00a0/g, ' ').replace(/\n+$/, '');
            if (valor.length > max) valor = valor.slice(0, max);
            var input = form.querySelector('[name="' + nome + '"]');
            if (input && input.value !== valor) input.value = valor;
            aplicar();
        });
        el.addEventListener('blur', function () {
            if (window.paginarFolha) window.paginarFolha();
        });
    }

    var titulo = document.getElementById('cab-titulo');
    var sub = document.getElementById('cab-subtitulo');
    var inst = document.getElementById('cab-instrucoes');
    var extraCab = document.getElementById('cab-texto-extra');
    var extraFim = document.getElementById('fim-texto-extra');
    var rodape = document.getElementById('cab-rodape');
    if (titulo) titulo.dataset.placeholder = 'Título da prova';
    if (sub) sub.dataset.placeholder = 'Subtítulo';
    if (inst) inst.dataset.placeholder = 'Instruções para o aluno';
    if (extraCab) extraCab.dataset.placeholder = 'Texto adicional';
    if (extraFim) extraFim.dataset.placeholder = 'Texto adicional';
    if (rodape) rodape.dataset.placeholder = 'Rodapé';
    ligarTexto(titulo, 'titulo', 180);
    ligarTexto(sub, 'subtitulo', 180);
    ligarTexto(inst, 'instrucoes', 800);
    ligarTexto(extraCab, 'texto_extra', 800);
    ligarTexto(extraFim, 'texto_extra', 800);
    ligarTexto(rodape, 'rodape', 120);

    folha.querySelectorAll('.questao').forEach(function (q) {
        var id = q.getAttribute('data-qid');
        if (!id || q.querySelector('.barra-questao')) return;
        var barra = document.createElement('div');
        barra.className = 'barra-questao';
        barra.innerHTML = '<button type="button" data-dir="-1">Subir</button><button type="button" data-dir="1">Descer</button><button type="button" data-acao="pagina">Próxima página</button><button type="button" data-acao="fora">Tirar da prova</button>';
        q.insertBefore(barra, q.firstChild);
        barra.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            e.preventDefault();
            var dir = btn.getAttribute('data-dir');
            if (dir) {
                var mover = form.querySelector('li[data-qid="' + id + '"] .js-mover[data-dir="' + dir + '"]');
                if (mover) mover.click();
                return;
            }
            if (btn.getAttribute('data-acao') === 'pagina') {
                var quebra = form.querySelector('.js-quebra[value="' + id + '"]');
                if (quebra) {
                    quebra.checked = !quebra.checked;
                    quebra.dispatchEvent(new Event('change', { bubbles: true }));
                }
                return;
            }
            var box = form.querySelector('.js-incluir[data-qid="' + id + '"]');
            if (box) {
                box.checked = !box.checked;
                box.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });

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
