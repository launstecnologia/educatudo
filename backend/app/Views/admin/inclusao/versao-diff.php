<?php
require_once __DIR__ . '/../../../Helpers/CatalogoRegraMascara.php';

$resumo = static function (string $html, int $len = 180): string {
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
    $t = trim(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $t)));
    return mb_strlen($t) > $len ? (mb_substr($t, 0, $len) . '…') : $t;
};
$textoEnunciado = static function (string $valor): string {
    $texto = trim($valor);
    if ($texto === '') {
        return '';
    }
    // Remove cercas de código markdown (```json ... ```)
    $texto = trim(preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $texto));

    $json = json_decode($texto, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json) && isset($json['enunciado'])) {
        return trim((string) $json['enunciado']);
    }
    // JSON bem-formado: extrai o valor de "enunciado" (com escapes corretos)
    if (preg_match('/"enunciado"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/us', $texto, $matches)) {
        $decoded = json_decode('"' . $matches[1] . '"');
        return trim((string) ($decoded ?? $matches[1]));
    }
    // JSON malformado (quebras de linha/aspas internas não escapadas): captura tudo
    // entre "enunciado":" e o fechamento "} e desfaz os escapes mais comuns.
    if (preg_match('/"enunciado"\s*:\s*"([\s\S]*)"\s*\}\s*$/u', $texto, $matches)
        || preg_match('/"enunciado"\s*:\s*"([\s\S]*)$/u', $texto, $matches)) {
        $candidate = preg_replace('/"\s*\}?\s*$/u', '', $matches[1]);
        $candidate = strtr($candidate, ['\\"' => '"', '\\n' => "\n", '\\t' => "\t", '\\/' => '/', '\\\\' => '\\']);
        return trim($candidate);
    }
    return $valor;
};
$imageUrl = static function (?string $url): string {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^(https?://|data:)#i', $url) || str_starts_with($url, '/')) {
        return $url;
    }
    return URL . '/' . ltrim($url, '/');
};
$plainText = static function (string $html): string {
    return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
};
$questaoModal = static function (array $questao, string $enunciado) use ($textoEnunciado, $imageUrl, $plainText): array {
    $alternativas = [];
    foreach (($questao['alternativas'] ?? []) as $a) {
        if (!is_array($a)) {
            continue;
        }
        $textoAlternativa = trim((string) ($a['texto'] ?? ''));
        $alternativas[] = [
            'html' => $textoAlternativa,
            'texto' => $plainText($textoAlternativa),
            'correta' => (int) ($a['correta'] ?? 0),
        ];
    }
    $htmlEnunciado = $textoEnunciado($enunciado);
    return [
        'id' => (int) ($questao['id'] ?? 0),
        'enunciado' => $htmlEnunciado,
        'texto' => $plainText($htmlEnunciado),
        'imagem_url' => $imageUrl($questao['imagem_url'] ?? ''),
        'alternativas' => $alternativas,
    ];
};
$fmt = static function (?float $v): string {
    return $v === null ? '—' : number_format($v, 2, ',', '.');
};
$catalog = CatalogoRegraMascara::rules();
$isTruthyRule = static function ($v): bool {
    return in_array(strtolower(trim((string) $v)), ['1', 'true', 'on', 'sim', 'yes'], true);
};
$ruleLabel = static function (string $key) use ($catalog): string {
    return (string) ($catalog[$key]['label'] ?? $key);
};
$ruleValueLabel = static function (string $key, $value) use ($catalog): string {
    if ($value === '1' || $value === 1 || $value === true) {
        return '';
    }
    $options = $catalog[$key]['options'] ?? [];
    if (isset($options[(string) $value])) {
        return (string) $options[(string) $value];
    }
    return (string) $value;
};
$hasAiRewriteRule = false;
foreach (CatalogoRegraMascara::aiRewriteKeys() as $aiRuleKey) {
    if (array_key_exists($aiRuleKey, $rules_snapshot ?? []) && $isTruthyRule(($rules_snapshot ?? [])[$aiRuleKey])) {
        $hasAiRewriteRule = true;
        break;
    }
}
$mantidas = 0;
foreach (($linhas ?? []) as $l) {
    if (!empty($l['mantida'])) { $mantidas++; }
}
?>
<div class="space-y-6 max-w-5xl">
    <div class="flex items-center justify-between">
        <div>
            <a href="<?= URL ?>/admin/inclusao/versoes" class="text-sm text-gray-500 hover:text-gray-700">&larr; Voltar para a fila</a>
            <h1 class="text-2xl font-bold text-gray-900 mt-1">Diff — Original × Adaptada</h1>
            <p class="text-sm text-gray-500">
                Aluno: <strong><?= htmlspecialchars((string) ($aluno_nome ?? '')) ?></strong>
                · Prova: <?= htmlspecialchars((string) ($prova_original['titulo'] ?? ('#' . (int) ($versao['prova_id'] ?? 0)))) ?>
            </p>
        </div>
        <div class="flex gap-2">
            <form method="post" action="<?= URL ?>/admin/inclusao/versoes/gerar" onsubmit="return educaIncluiGerarDiffsLoading(this, 'Gerar agora todos os diffs/versões adaptadas pendentes?');">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90 text-sm font-medium">
                    Gerar todos os diffs
                </button>
            </form>
            <form method="post" action="<?= URL ?>/admin/inclusao/versoes/aprovar" onsubmit="return confirm('Aprovar esta versão?');">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                <input type="hidden" name="version_id" value="<?= (int) ($versao['id'] ?? 0) ?>">
                <input type="hidden" name="acao" value="aprovar">
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90 text-sm font-medium">Aprovar</button>
            </form>
            <form method="post" action="<?= URL ?>/admin/inclusao/versoes/aprovar" onsubmit="return confirm('Reprovar esta versão?');">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                <input type="hidden" name="version_id" value="<?= (int) ($versao['id'] ?? 0) ?>">
                <input type="hidden" name="acao" value="reprovar">
                <button type="submit" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-sm font-medium">Reprovar</button>
            </form>
        </div>
    </div>

    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-lg px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars((string) $flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-500 uppercase">Questões (original)</p>
            <p class="text-2xl font-bold text-gray-900"><?= (int) ($total_original ?? 0) ?></p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-500 uppercase">Questões (adaptada)</p>
            <p class="text-2xl font-bold text-emerald-700"><?= (int) ($total_adaptada ?? 0) ?></p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-500 uppercase">Valor total</p>
            <p class="text-2xl font-bold text-gray-900">
                <?= $fmt((float) ($prova_original['valor_total'] ?? 0)) ?>
            </p>
            <p class="text-[11px] text-gray-500">mesma escala nas duas (nota normalizada)</p>
        </div>
    </div>

    <?php if (!empty($rules_snapshot)): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Regras aplicadas nesta versão</p>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($rules_snapshot as $k => $v): ?>
                <?php $valueLabel = $ruleValueLabel((string) $k, $v); ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-indigo-50 text-indigo-700 border border-indigo-200">
                    <?= htmlspecialchars($ruleLabel((string) $k)) ?><?= $valueLabel !== '' ? (': ' . htmlspecialchars($valueLabel)) : '' ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($hasAiRewriteRule && empty($reescrita_mapa)): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <strong>Reescrita por IA ainda não aplicada.</strong>
        Esta versão tem regra de simplificação/literalidade, mas não há registro de reescrita processada.
        Por isso os enunciados podem aparecer iguais aos originais até o job assíncrono finalizar e registrar o comparativo.
    </div>
    <?php endif; ?>

    <?php if (empty($reescrita_mapa)): ?>
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Comparação por questão</h2>
            <span class="text-sm text-gray-500"><?= $mantidas ?> mantida(s) · <?= (int) ($total_original ?? 0) - $mantidas ?> removida(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase">
                        <th class="px-4 py-3 w-10">#</th>
                        <th class="px-4 py-3">Enunciado (original)</th>
                        <th class="px-4 py-3 w-28">Valor orig.</th>
                        <th class="px-4 py-3 w-28">Valor adapt.</th>
                        <th class="px-4 py-3 w-28">Situação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach (($linhas ?? []) as $l): ?>
                        <tr class="<?= empty($l['mantida']) ? 'bg-red-50/40' : '' ?>">
                            <td class="px-4 py-3 text-gray-500"><?= (int) $l['numero'] ?></td>
                            <td class="px-4 py-3 text-gray-800"><?= htmlspecialchars($resumo((string) $l['enunciado'])) ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= $fmt((float) $l['valor_original']) ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= $fmt($l['valor_adaptado']) ?></td>
                            <td class="px-4 py-3">
                                <?php if (!empty($l['mantida'])): ?>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Mantida</span>
                                <?php else: ?>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Removida</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Esta versão teve enunciados <strong>reescritos por IA</strong>; veja o comparativo antes/depois abaixo. (Removidas por redução de questões: <?= max(0, (int) ($total_original ?? 0) - (int) ($total_adaptada ?? 0)) ?>.)
    </div>
    <?php endif; ?>

    <?php if (!empty($reescrita_mapa)): ?>
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">Reescrita por IA (antes × depois)</h2>
            <p class="text-xs text-gray-500">Enunciados reescritos para linguagem simplificada/literal. Revise antes de aprovar.</p>
        </div>
        <div class="divide-y divide-gray-100">
            <?php foreach ($reescrita_mapa as $i => $r): ?>
                <?php
                $antesCompleto = $textoEnunciado((string) ($r['antes'] ?? ''));
                $depoisCompleto = $textoEnunciado((string) ($r['depois'] ?? ''));
                $originalQuestao = is_array($r['original_questao'] ?? null) ? $r['original_questao'] : [];
                $adaptadaQuestao = is_array($r['adaptada_questao'] ?? null) ? $r['adaptada_questao'] : ['id' => (int) ($r['clone_questao_id'] ?? 0)];
                $originalModal = $questaoModal($originalQuestao, $antesCompleto);
                $adaptadaModal = $questaoModal($adaptadaQuestao, $depoisCompleto);
                ?>
                <div class="px-6 py-4 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-gray-900">Questão #<?= (int) $i + 1 ?></p>
                        <button type="button"
                                class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-semibold border border-indigo-100"
                                onclick='educaIncluiAbrirDiffCompleto(<?= json_encode($originalModal, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($adaptadaModal, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>, <?= (int) $i + 1 ?>)'>
                            Ver diferença completa
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-[11px] font-semibold text-gray-400 uppercase mb-1">Original</p>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($resumo($antesCompleto, 320)) ?></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold text-emerald-600 uppercase mb-1">Reescrito</p>
                            <p class="text-sm text-gray-800"><?= htmlspecialchars($resumo($depoisCompleto, 320)) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <p class="text-xs text-gray-400">As questões mantidas têm o valor escalonado para que a soma da prova adaptada seja igual à da original. A correção usa o gabarito da versão entregue; a nota final fica na mesma escala.</p>
</div>

<div id="educaIncluiDiffModal" class="fixed inset-0 z-[10000] hidden items-center justify-center bg-gray-900/60 px-4">
    <div class="bg-white rounded-xl shadow-2xl border border-gray-200 max-w-5xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
            <div>
                <h3 id="educaIncluiDiffModalTitle" class="text-lg font-semibold text-gray-900">Diferença completa</h3>
                <p class="text-xs text-gray-500">Compare o enunciado original com a versão reescrita por IA.</p>
            </div>
            <button type="button" onclick="educaIncluiFecharDiffCompleto()" class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:text-gray-800 hover:bg-gray-100" aria-label="Fechar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <p class="text-xs font-semibold uppercase text-gray-500 mb-2">Original</p>
                    <div id="educaIncluiDiffOriginal" class="space-y-3"></div>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                    <p class="text-xs font-semibold uppercase text-emerald-700 mb-2">Reescrito</p>
                    <div id="educaIncluiDiffReescrito" class="space-y-3"></div>
                </div>
            </div>
            <form id="educaIncluiEditForm" method="post" action="<?= URL ?>/admin/inclusao/versoes/questao/editar" class="mt-5 rounded-lg border border-gray-200 bg-white p-4">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                <input type="hidden" name="version_id" value="<?= (int) ($versao['id'] ?? 0) ?>">
                <input type="hidden" name="clone_questao_id" value="">
                <label for="educaIncluiEditTextarea" class="block text-xs font-semibold uppercase text-gray-500 mb-2">Editar enunciado reescrito</label>
                <textarea id="educaIncluiEditTextarea" name="enunciado" rows="6" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-800 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                <p class="mt-2 text-xs text-gray-500">A edição altera somente o enunciado da questão adaptada. Imagem e alternativas permanecem preservadas.</p>
            </form>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex flex-wrap justify-end gap-2">
            <form id="educaIncluiRewriteOneForm" method="post" action="<?= URL ?>/admin/inclusao/versoes/questao/refazer" onsubmit="return confirm('Refazer somente esta questão por IA?');">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                <input type="hidden" name="version_id" value="<?= (int) ($versao['id'] ?? 0) ?>">
                <input type="hidden" name="clone_questao_id" value="">
                <input type="hidden" name="original_enunciado" value="">
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90 text-sm font-semibold">
                    Refazer por IA
                </button>
            </form>
            <button type="submit" form="educaIncluiEditForm" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90 text-sm font-semibold">Salvar edição</button>
            <button type="button" onclick="educaIncluiFecharDiffCompleto()" class="px-4 py-2 rounded-lg bg-gray-800 hover:bg-gray-900 text-white text-sm font-semibold">Fechar</button>
        </div>
    </div>
</div>

<div id="educaIncluiDiffLoading" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-gray-900/50 px-4">
    <div class="bg-white rounded-xl shadow-xl border border-gray-200 p-6 max-w-sm w-full text-center">
        <div class="mx-auto mb-4 h-10 w-10 rounded-full border-4 border-indigo-100 border-t-indigo-600 animate-spin"></div>
        <p class="text-base font-semibold text-gray-900">Gerando diffs...</p>
        <p class="mt-1 text-sm text-gray-500">Aguarde enquanto as versões adaptadas e reescritas por IA são processadas.</p>
    </div>
</div>
<script>
function educaIncluiGerarDiffsLoading(form, message) {
    if (!confirm(message)) {
        return false;
    }
    var button = form.querySelector('button[type="submit"]');
    if (button) {
        button.disabled = true;
        button.classList.add('opacity-70', 'cursor-wait');
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Gerando...';
    }
    var overlay = document.getElementById('educaIncluiDiffLoading');
    if (overlay) {
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
    }
    return true;
}
function educaIncluiTextoEnunciado(valor) {
    var texto = String((valor && typeof valor === 'object' ? (valor.enunciado || valor.texto) : valor) || '').trim();
    if (!texto) {
        return '';
    }
    texto = texto.replace(/^```[a-zA-Z]*\s*/, '').replace(/\s*```$/, '').trim();
    try {
        var json = JSON.parse(texto);
        if (json && typeof json === 'object' && json.enunciado) {
            return String(json.enunciado).trim();
        }
    } catch (e) {}
    var match = texto.match(/"enunciado"\s*:\s*"((?:\\.|[^"\\])*)"/s);
    if (match) {
        try {
            return JSON.parse('"' + match[1] + '"').trim();
        } catch (e) {
            return match[1].trim();
        }
    }
    // JSON malformado: captura tudo entre "enunciado":" e o fechamento "}
    var loose = texto.match(/"enunciado"\s*:\s*"([\s\S]*)"\s*\}\s*$/) || texto.match(/"enunciado"\s*:\s*"([\s\S]*)$/);
    if (loose) {
        return loose[1]
            .replace(/"\s*\}?\s*$/, '')
            .replace(/\\"/g, '"').replace(/\\n/g, '\n').replace(/\\t/g, '\t').replace(/\\\//g, '/')
            .trim();
    }
    return texto;
}
function educaIncluiPlainText(valor) {
    var texto = String((valor && typeof valor === 'object' ? (valor.texto || valor.enunciado) : valor) || '');
    var temp = document.createElement('div');
    temp.innerHTML = texto;
    return (temp.textContent || temp.innerText || '').replace(/\s+/g, ' ').trim();
}
function educaIncluiRenderQuestao(container, questao) {
    if (!container) {
        return;
    }
    var data = questao && typeof questao === 'object' ? questao : { enunciado: educaIncluiTextoEnunciado(questao) };
    container.innerHTML = '';

    var enunciado = document.createElement('div');
    enunciado.className = 'text-sm text-gray-800 leading-relaxed whitespace-pre-wrap prose prose-sm max-w-none';
    enunciado.innerHTML = educaIncluiTextoEnunciado(data);
    container.appendChild(enunciado);

    if (data.imagem_url) {
        var wrapper = document.createElement('div');
        wrapper.className = 'rounded-lg border border-gray-200 bg-white p-2';
        var img = document.createElement('img');
        img.src = data.imagem_url;
        img.alt = 'Imagem da questão';
        img.className = 'max-h-72 w-auto max-w-full rounded-md object-contain mx-auto';
        wrapper.appendChild(img);
        container.appendChild(wrapper);
    }

    if (Array.isArray(data.alternativas) && data.alternativas.length > 0) {
        var list = document.createElement('div');
        list.className = 'space-y-2';
        var title = document.createElement('p');
        title.className = 'text-xs font-semibold uppercase text-gray-500';
        title.textContent = 'Alternativas';
        list.appendChild(title);
        var correctLabels = [];
        data.alternativas.forEach(function(alt, index) {
            var isCorrect = !!(alt && parseInt(alt.correta, 10) === 1);
            var letter = String.fromCharCode(65 + index);
            if (isCorrect) {
                correctLabels.push(letter);
            }
            var item = document.createElement('div');
            item.className = isCorrect
                ? 'rounded-lg border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-900'
                : 'rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700';
            var prefix = document.createElement('span');
            prefix.className = isCorrect ? 'font-semibold text-green-700 mr-1' : 'font-semibold text-gray-500 mr-1';
            prefix.textContent = letter + '.';
            item.appendChild(prefix);
            var content = document.createElement('span');
            content.innerHTML = String((alt && (alt.html || alt.texto)) || '');
            item.appendChild(content);
            if (isCorrect) {
                var badge = document.createElement('span');
                badge.className = 'ml-2 inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-800';
                badge.textContent = 'Gabarito';
                item.appendChild(badge);
            }
            list.appendChild(item);
        });
        if (correctLabels.length > 0) {
            var answer = document.createElement('p');
            answer.className = 'rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs font-semibold text-green-800';
            answer.textContent = 'Gabarito: ' + correctLabels.join(', ');
            list.appendChild(answer);
        }
        container.appendChild(list);
    }
}
function educaIncluiPreencherAcoes(original, reescrito) {
    var cloneId = reescrito && reescrito.id ? String(reescrito.id) : '';
    var textoOriginal = educaIncluiTextoEnunciado(original);
    var textoReescrito = educaIncluiPlainText(reescrito);
    var editForm = document.getElementById('educaIncluiEditForm');
    var rewriteForm = document.getElementById('educaIncluiRewriteOneForm');
    var textarea = document.getElementById('educaIncluiEditTextarea');
    if (textarea) textarea.value = textoReescrito;
    [editForm, rewriteForm].forEach(function(form) {
        if (!form) return;
        var cloneInput = form.querySelector('[name="clone_questao_id"]');
        if (cloneInput) cloneInput.value = cloneId;
    });
    if (rewriteForm) {
        var originalInput = rewriteForm.querySelector('[name="original_enunciado"]');
        if (originalInput) originalInput.value = textoOriginal;
    }
}
function educaIncluiAbrirDiffCompleto(original, reescrito, numero) {
    var modal = document.getElementById('educaIncluiDiffModal');
    var title = document.getElementById('educaIncluiDiffModalTitle');
    var originalBox = document.getElementById('educaIncluiDiffOriginal');
    var reescritoBox = document.getElementById('educaIncluiDiffReescrito');
    if (title) title.textContent = 'Diferença completa - questão #' + numero;
    educaIncluiRenderQuestao(originalBox, original);
    educaIncluiRenderQuestao(reescritoBox, reescrito);
    educaIncluiPreencherAcoes(original, reescrito);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}
function educaIncluiFecharDiffCompleto() {
    var modal = document.getElementById('educaIncluiDiffModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        educaIncluiFecharDiffCompleto();
    }
});
</script>
