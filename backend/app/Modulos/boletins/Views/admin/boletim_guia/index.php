<div class="max-w-4xl mx-auto space-y-8 pb-12">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Guia do Boletim</h1>
        <p class="text-gray-600 mt-2">
            Fluxo modular para a <strong>coordenação</strong> (ou diretor): primeiro as notas que vêm de uso do sistema (jornadas, provas), depois a <strong>regra de cálculo</strong> que junta tudo na média final.
        </p>
    </div>

    <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-5">
        <h2 class="text-sm font-semibold text-indigo-900 uppercase tracking-wide">Atalhos</h2>
        <div class="mt-3 flex flex-wrap gap-3">
            <a href="<?= URL ?>/admin/boletim-configuracao" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                Configuração do boletim
            </a>
        </div>
        <p class="text-xs text-indigo-800 mt-3">Modelos prontos ficam na seção abaixo — ao clicar, você será levado ao editor com os blocos já sugeridos (pode editar antes de salvar).</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-900">Modelos modulares (clique para carregar no editor)</h2>
            <p class="text-sm text-gray-500 mt-1">Substituem apenas o rascunho na tela; use <strong>Salvar regra</strong> depois de revisar blocos, filtros e blocos de prova.</p>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="border border-gray-200 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900">Média de 3 (semanal + bimestral + jornadas)</h3>
                <p class="text-sm text-gray-600 mt-1">Três componentes e fórmula <code class="text-xs bg-gray-100 px-1 rounded">(semanal + bimestral + jornadas) / 3</code>. Ajuste os filtros por título das provas (ex.: contém &quot;semanal&quot;).</p>
                <button type="button" data-modelo="media3" class="mt-3 px-3 py-2 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700">Aplicar modelo</button>
            </div>
            <div class="border border-gray-200 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900">Média de 3 + ENAC (só melhora)</h3>
                <p class="text-sm text-gray-600 mt-1">Inclui nota manual ENAC. Fórmula usa <code class="text-xs bg-gray-100 px-1 rounded">max</code>: se ENAC for menor que a média dos três, mantém a média; se for maior, faz a média entre a base e o ENAC.</p>
                <button type="button" data-modelo="media3_enac" class="mt-3 px-3 py-2 bg-violet-600 text-white text-sm rounded-lg hover:bg-violet-700">Aplicar modelo</button>
            </div>
            <div class="border border-teal-200 rounded-lg p-4 bg-teal-50/40">
                <h3 class="font-semibold text-gray-900">Quadro semanal (S1–S8)</h3>
                <p class="text-sm text-gray-600 mt-1">Monta o boletim no layout do quadro: semanas com <strong>N</strong> e <strong>Q</strong>, média semanal (acertos÷questões), prova bimestral (nota inteira), ENAC/trabalho se existirem e recuperação na média final. Junta a mesma matéria de professores diferentes. Ajuste tipos e pesos no editor ou peça no assistente.</p>
                <button type="button" data-modelo="quadro_semanal" class="mt-3 px-3 py-2 bg-teal-700 text-white text-sm rounded-lg hover:bg-teal-800">Aplicar modelo</button>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">Tutorial passo a passo</h2>
        </div>
        <div class="divide-y divide-gray-100 text-sm text-gray-700">
            <details class="group" open>
                <summary class="px-5 py-4 cursor-pointer font-medium text-gray-900 hover:bg-gray-50 list-none flex justify-between items-center">
                    <span>Passo 0 — O que é cada peça</span>
                    <span class="text-gray-400 group-open:rotate-180">▼</span>
                </summary>
                <div class="px-5 pb-4 space-y-2 text-gray-600">
                    <p><strong>Componente:</strong> um “bloco” de nota (ex.: provas semanais do período, bloco <strong>Jornadas (automático)</strong> por conclusão das jornadas, ou nota manual).</p>
                    <p><strong>Código:</strong> nome curto usado na fórmula final (ex.: <code>semanal</code>, <code>jornadas</code>).</p>
                    <p><strong>Período referência:</strong> na simulação e no lançamento manual, algo como <code>2026-B1</code> (bimestre) para filtrar provas por data de finalização.</p>
                    <p><strong>Fórmula final (opcional):</strong> combina os códigos com <code>+ - * / ( )</code> e também <code>max(a,b)</code> e <code>min(a,b)</code> quando precisar de regras do tipo “só melhora com ENAC”.</p>
                </div>
            </details>
            <details class="group">
                <summary class="px-5 py-4 cursor-pointer font-medium text-gray-900 hover:bg-gray-50 list-none flex justify-between items-center">
                    <span>Passo 1 — Jornadas no boletim (só por conclusão)</span>
                    <span class="text-gray-400">▼</span>
                </summary>
                <div class="px-5 pb-4 space-y-2 text-gray-600">
                    <p>Na <a class="text-indigo-600 underline" href="<?= URL ?>/admin/boletim-configuracao">Configuração do Boletim</a>, adicione um bloco com origem <strong>Jornadas (automático)</strong>. A pontuação considera se o aluno <strong>concluiu</strong> cada jornada (módulos ou marcação de concluída), <strong>não</strong> o acerto em questões.</p>
                    <ol class="list-decimal pl-5 space-y-2">
                        <li>Selecione as jornadas no escopo (ou use <strong>Marcar todas</strong> no modal) e o intervalo de datas, se quiser fixar além do <code>periodo_ref</code> da simulação.</li>
                        <li>Marque <strong>Nota proporcional ao % de jornadas concluídas</strong> quando quiser nota linear em função de quantas jornadas do escopo foram concluídas; desmarcado aplica a tabela por faixas ao percentual.</li>
                        <li>Salve a regra e use a <strong>Simulação real</strong> com o mesmo <code>periodo_ref</code> do bimestre.</li>
                    </ol>
                </div>
            </details>
            <details class="group">
                <summary class="px-5 py-4 cursor-pointer font-medium text-gray-900 hover:bg-gray-50 list-none flex justify-between items-center">
                    <span>Passo 2 — Provas semanais e bimestrais</span>
                    <span class="text-gray-400">▼</span>
                </summary>
                <div class="px-5 pb-4 space-y-2 text-gray-600">
                    <p>Na <a class="text-indigo-600 underline" href="<?= URL ?>/admin/boletim-configuracao">Configuração do Boletim</a>, adicione blocos do tipo <strong>Provas do sistema</strong>:</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Marque <strong>Calcular por acertos/questões</strong> para transformar desempenho em nota 0–10 (acertos ÷ total de questões × escala).</li>
                        <li>Use <strong>filtro por título</strong> para separar o que é “semanal” do que é “bimestral” (o título da prova no sistema deve conter essas palavras).</li>
                        <li>Em <strong>Blocos de prova</strong>, use Ctrl/Cmd + clique para escolher <strong>vários</strong> blocos. Com blocos marcados, o <strong>filtro por título não é aplicado</strong> (o título da prova no cadastro costuma não ser igual ao nome do bloco). Para filtrar só por palavra no título, não marque blocos.</li>
                        <li>Opcional: restrinja também a uma <strong>matéria</strong>.</li>
                    </ul>
                </div>
            </details>
            <details class="group">
                <summary class="px-5 py-4 cursor-pointer font-medium text-gray-900 hover:bg-gray-50 list-none flex justify-between items-center">
                    <span>Passo 3 — ENAC (manual)</span>
                    <span class="text-gray-400">▼</span>
                </summary>
                <div class="px-5 pb-4 space-y-2 text-gray-600">
                    <p>Adicione um componente <strong>manual</strong> com código <code>enac</code>. A nota pode ser digitada na própria tela de boletim (simulação / notas manuais) por aluno e período, ou importada por processo próprio da escola.</p>
                </div>
            </details>
            <details class="group">
                <summary class="px-5 py-4 cursor-pointer font-medium text-gray-900 hover:bg-gray-50 list-none flex justify-between items-center">
                    <span>Passo 4 — Fórmula final (ex.: média 3 + regra ENAC)</span>
                    <span class="text-gray-400">▼</span>
                </summary>
                <div class="px-5 pb-4 space-y-2 text-gray-600">
                    <p><strong>Média só dos três:</strong></p>
                    <pre class="text-xs bg-gray-900 text-green-100 p-3 rounded-lg overflow-x-auto">(semanal + bimestral + jornadas) / 3</pre>
                    <p class="pt-2"><strong>Com ENAC (quando ENAC é maior que a média dos três, faz média com ENAC; senão mantém a média dos três):</strong></p>
                    <pre class="text-xs bg-gray-900 text-green-100 p-3 rounded-lg overflow-x-auto whitespace-pre-wrap">max((semanal + bimestral + jornadas) / 3, ((semanal + bimestral + jornadas) / 3 + enac) / 2)</pre>
                    <p class="text-xs text-gray-500">O modelo “Média de 3 + ENAC” já preenche isso ao clicar em Aplicar modelo.</p>
                </div>
            </details>
            <details class="group">
                <summary class="px-5 py-4 cursor-pointer font-medium text-gray-900 hover:bg-gray-50 list-none flex justify-between items-center">
                    <span>Passo 5 — Salvar e testar</span>
                    <span class="text-gray-400">▼</span>
                </summary>
                <div class="px-5 pb-4 space-y-2 text-gray-600">
                    <ol class="list-decimal pl-5 space-y-2">
                        <li>Clique em <strong>Salvar regra</strong> na configuração do boletim.</li>
                        <li>Use <strong>Simulação real</strong> com um aluno e o <code>periodo_ref</code> do bimestre (o sistema avisa se existir nota manual em outro período).</li>
                        <li>Confira cada linha da tabela e a nota final; ajuste filtros ou fórmula se algo não bater com a política da escola.</li>
                    </ol>
                </div>
            </details>
        </div>
    </div>
</div>

<script>
(function() {
    function irComModelo(chave) {
        try {
            sessionStorage.setItem('boletim_aplicar_modelo', chave);
        } catch (e) {}
        window.location.href = <?= json_encode(URL . '/admin/boletim-configuracao', JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    }
    document.querySelectorAll('[data-modelo]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var k = btn.getAttribute('data-modelo');
            if (confirm('Ir para a configuração do boletim e carregar o modelo "' + k + '"? O rascunho atual na tela será substituído (não salva até você clicar em Salvar regra).')) {
                irComModelo(k);
            }
        });
    });
})();
</script>

<style>
details > summary::-webkit-details-marker { display: none; }
</style>
