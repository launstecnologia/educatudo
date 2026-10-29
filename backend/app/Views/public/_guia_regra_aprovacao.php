<?php
/** Conteúdo editorial da etapa pública; não lê nem altera o cadastro da escola. */
$printsRegra = [];
foreach (($etapaGuia['como_fazer'] ?? []) as $itemRegra) {
    if (is_array($itemRegra) && !empty($itemRegra['print']['existe'])) {
        $printsRegra[(string) ($itemRegra['print']['arquivo'] ?? '')] = $itemRegra['print'];
    }
}
// Recorte existente com as opções de frequência, próximo à explicação de como marcá-las.
$diretorioPrintsRegra = \App\Dominio\Escola\Services\ImplantacaoAcademicaService::diretorioPrintsGuia();
$arquivoFrequenciaRegra = '086-regras-arredondamento.jpg';
if ($diretorioPrintsRegra !== null && is_file($diretorioPrintsRegra . '/' . $arquivoFrequenciaRegra)) {
    $printsRegra[$arquivoFrequenciaRegra] = [
        'url' => '/assets/guia-onboarding/' . rawurlencode($arquivoFrequenciaRegra) . '?v=' . filemtime($diretorioPrintsRegra . '/' . $arquivoFrequenciaRegra),
    ];
}
$mostrarPrintRegra = static function (string $arquivo, string $legenda) use ($printsRegra, $e): void {
    $printRegra = $printsRegra[$arquivo] ?? null;
    if ($printRegra === null) {
        return;
    }
    $srcRegra = URL . (string) $printRegra['url'];
    ?>
    <figure class="regra-print">
        <button type="button" class="guia-foto" data-print-src="<?= $e($srcRegra) ?>" data-print-legenda="<?= $e($legenda) ?>" aria-label="Ampliar: <?= $e($legenda) ?>" aria-haspopup="dialog">
            <img src="<?= $e($srcRegra) ?>" alt="<?= $e($legenda) ?>" loading="lazy">
        </button>
        <figcaption><?= $e($legenda) ?> <span>· Clique na imagem para ampliar</span></figcaption>
    </figure>
    <?php
};
?>
<style>
.guia-regra .regra-print{margin:16px 0 20px}.guia-regra .regra-print .guia-foto{margin:0}.guia-regra .regra-print figcaption{font-size:12px;color:#49616e;margin-top:8px;line-height:1.6}.guia-regra .regra-print figcaption span{color:#61717e}.guia-regra{font-size:14px;line-height:1.7}.guia-regra p{margin:0 0 12px}.guia-regra strong{font-weight:650}.guia-regra .regra-resumo{font-size:15px;color:#465e6c;margin:-8px 0 20px}.guia-regra .regra-preparo{background:#f2f7fa;border-radius:9px;padding:14px 16px;margin-bottom:8px;color:#415b6a;font-size:13px}.guia-regra .regra-preparo p{margin:4px 0 0}.guia-regra .regra-bloco{padding:26px 0;border-bottom:1px solid #e4ecf0}.guia-regra .regra-bloco:last-child{border:0;padding-bottom:0}.guia-regra .regra-titulo{display:flex;align-items:flex-start;gap:12px;margin:0 0 16px;font-size:18px;font-weight:700;line-height:1.4;color:#20323e}.guia-regra .regra-passo{display:inline-grid;place-items:center;flex-shrink:0;min-width:28px;height:28px;background:#e6f4f7;border-radius:50%;font-size:12px;color:#00728a}.guia-regra h4{font-size:14px;font-weight:700;margin:20px 0 8px;color:#20323e}.guia-regra ul,.guia-regra ol{margin:10px 0 0;padding-left:22px;list-style:disc}.guia-regra ol{list-style:decimal}.guia-regra li{padding-left:3px;margin:7px 0}.guia-regra .regra-caminho{font-size:13px;color:#49616e;overflow-wrap:anywhere}.guia-regra .regra-alerta{border-left:3px solid #d29b38;padding:2px 0 2px 13px;margin:16px 0 0;font-size:13px;color:#6b511f}.guia-regra .regra-exemplo{background:#f1f7fa;padding:14px 16px;border-radius:9px;margin-top:16px;font-size:13px;color:#405967}.guia-regra .regra-exemplo p:last-child{margin:0}.guia-regra .regra-exemplo .regra-legenda{font-size:11px;letter-spacing:.7px;text-transform:uppercase;font-weight:650;color:#61717e;margin:0 0 8px}.guia-regra .regra-opcoes{margin:12px 0 0}.guia-regra .regra-opcoes>div{padding:12px 0;border-top:1px solid #e8eef2}.guia-regra dt{font-weight:650;color:#20323e}.guia-regra dd{margin:4px 0 0;color:#49616e;font-size:13px}.guia-regra details{margin:16px 0 0;border-top:1px solid #e4ecf0;border-bottom:1px solid #e4ecf0}.guia-regra summary{cursor:pointer;font-size:13px;font-weight:650;padding:13px 0;min-height:46px;color:#00728a}.guia-regra details>div{padding:0 0 16px;font-size:13px}.guia-regra details h4{font-size:13px}.guia-regra .regra-opcional{font-size:11px;letter-spacing:.4px;text-transform:uppercase;color:#61717e;margin:-8px 0 12px}.guia-regra .regra-revisao{padding:16px 18px;background:#edf8f2;border-radius:10px;margin:16px 0;color:#255c43}.guia-regra .regra-revisao p{font-weight:650;margin:0}.guia-regra .regra-conexao{font-size:13px;color:#61717e;margin-top:16px}.guia-regra .regra-conexao p{margin:0}.guia-regra .regra-bloco>p:last-child{margin-bottom:0}@media(max-width:800px){.guia-regra .regra-titulo{font-size:17px;gap:10px}.guia-regra .regra-bloco{padding:22px 0}}
</style>
<div class="guia-regra">
    <p class="regra-resumo">Defina os critérios que a escola usa para aprovar os alunos. Você vai escolher para quem a regra vale, as notas, a frequência e, se houver, a recuperação.</p>
    <div class="regra-preparo"><strong>Antes de começar</strong><p>Tenha os critérios definidos pela coordenação e o ano letivo cadastrado. Use os valores da sua escola; os números abaixo são apenas exemplos.</p></div>

    <section class="regra-bloco" aria-labelledby="regra-abrangencia">
        <h3 class="regra-titulo" id="regra-abrangencia"><span class="regra-passo" aria-hidden="true">1</span>Defina para quem vale a regra</h3>
        <p class="regra-caminho">Como a escola avalia → Regras de Aprovação → <strong>Nova regra de aprovação</strong></p>
        <?php $mostrarPrintRegra('081-regras-nova.jpg', 'Clique em Nova regra de aprovação para abrir o cadastro.'); ?>
        <ul>
            <li>Em <strong>Nome</strong>, use uma identificação fácil de reconhecer, como “Ensino Médio 2026”. Deixe <strong>Regra ativa</strong> marcado.</li>
            <li>Em <strong>Vale para</strong>, escolha o <strong>Ano letivo</strong>, o <strong>Ciclo/Etapa/Curso</strong> e as séries atendidas por esta regra.</li>
        </ul>
        <?php $mostrarPrintRegra('082-regras-identificacao.jpg', 'Preencha Nome e Vale para; mantenha Regra ativa marcada.'); ?>
        <p class="regra-alerta">Deixar curso ou séries sem seleção amplia a regra para todos. Se as etapas da escola têm critérios diferentes, cadastre regras separadas.</p>
        <details><summary>De onde vêm os bimestres ou trimestres?</summary><div>Os cards de avaliação seguem a divisão cadastrada no ano letivo. Você usará esses períodos em Eventos Avaliativos.</div></details>
    </section>

    <section class="regra-bloco" aria-labelledby="regra-criterios">
        <h3 class="regra-titulo" id="regra-criterios"><span class="regra-passo" aria-hidden="true">2</span>Defina como o aluno é aprovado</h3>
        <p>Preencha a seção <strong>Critérios de Aprovação</strong> em três partes.</p>
        <h4>Como a nota é registrada?</h4>
        <p>Em <strong>Escala de Avaliação</strong>, escolha a escala usada pelos professores. Para notas de 0 a 10, escolha <strong>Numérica (0 a 10)</strong>; para notas de 0 a 100, escolha a opção correspondente.</p>
        <p>Depois, informe a <strong>Média Mínima para Aprovação</strong> dentro dessa escala.</p>
        <?php $mostrarPrintRegra('085-regras-criterios.jpg', 'Localize Escala de Avaliação e Média Mínima para Aprovação na seção Critérios de Aprovação.'); ?>
        <details><summary>A escola usa conceitos em vez de notas?</summary><div><p>Escolha a escala conceitual usada pela escola. Quando aparecer <strong>Conceito mínimo para aprovar</strong>, selecione o conceito exigido: o aluno precisa atingir esse conceito ou um melhor.</p><p>As opções de escala também incluem avaliação descritiva. Use a modalidade definida pela coordenação.</p></div></details>

        <h4>A frequência também é necessária?</h4>
        <p>Preencha <strong>Frequência Mínima Exigida (%)</strong>. Em seguida, confira as duas opções abaixo desse campo:</p>
        <dl class="regra-opcoes">
            <div><dt>Nota e frequência</dt><dd>Marque <strong>Exigir Frequência Mínima para Aprovação</strong> e deixe <strong>Avaliação Baseada Apenas na Frequência</strong> desmarcada.</dd></div>
            <div><dt>Apenas nota</dt><dd>Deixe as duas opções desmarcadas. O percentual preenchido, sozinho, não faz a frequência entrar na aprovação.</dd></div>
            <div><dt>Apenas frequência</dt><dd>Marque <strong>Avaliação Baseada Apenas na Frequência</strong>. Nessa modalidade, a aprovação considera a presença, sem exigir média.</dd></div>
        </dl>
        <?php $mostrarPrintRegra('086-regras-arredondamento.jpg', 'Neste exemplo, Exigir Frequência Mínima para Aprovação está marcada e a opção de apenas frequência está desmarcada.'); ?>
        <div class="regra-exemplo"><p class="regra-legenda">Exemplo ilustrativo · nota e frequência</p><p>Uma escola exige média <strong>6</strong> e frequência de <strong>75%</strong>. Um aluno com média <strong>7</strong> e frequência de <strong>70%</strong> atende à nota, mas não ao critério de frequência.</p></div>

        <h4>Como a nota será arredondada e exibida?</h4>
        <p>Confira <strong>Regra de Arredondamento</strong> e <strong>Casas Decimais da Nota</strong> conforme o padrão da escola.</p>
        <?php $mostrarPrintRegra('085-regras-criterios.jpg', 'Os campos Regra de Arredondamento e Casas Decimais da Nota ficam lado a lado, acima das opções de frequência.'); ?>
        <details><summary>Entender as opções de arredondamento e casas decimais</summary><div><p><strong>Arredondamento convencional</strong> e <strong>Faixa .00 / .50 / próximo inteiro</strong> são formas diferentes de ajustar o resultado. A segunda trabalha com faixas que terminam em ,00, ,50 ou no inteiro seguinte.</p><p><strong>Casas Decimais da Nota</strong> define a apresentação: nenhuma (6), uma casa (6,5) ou duas casas (6,50). Confira o resultado com a coordenação antes de usar a regra.</p></div></details>
    </section>

    <section class="regra-bloco" aria-labelledby="regra-documentos">
        <h3 class="regra-titulo" id="regra-documentos"><span class="regra-passo" aria-hidden="true">3</span>Escolha as disciplinas e quem poderá consultar</h3>
        <p>Na seção <strong>Demonstrativo e boletim</strong>:</p>
        <ul>
            <li>Em <strong>Modelo</strong>, escolha <strong>Modelo oficial</strong> para a vida escolar regular ou <strong>Modelo extra</strong> para um curso complementar, que fica fora do histórico oficial.</li>
            <li>Marque as <strong>Disciplinas</strong> que participam desta regra. As desmarcadas ficam fora do demonstrativo e do boletim.</li>
            <li>Em <strong>Disponibilidade</strong>, marque <strong>Disponível para aluno</strong> e/ou <strong>Disponível para famílias</strong> conforme o público que poderá consultar os documentos.</li>
        </ul>
        <?php $mostrarPrintRegra('087-regras-modelo.jpg', 'Em Demonstrativo e boletim, escolha Modelo, Disponibilidade e Disciplinas.'); ?>
        <p class="regra-conexao">Os desdobramentos das disciplinas vêm de Componentes Curriculares.</p>
    </section>

    <section class="regra-bloco" aria-labelledby="regra-recuperacao">
        <h3 class="regra-titulo" id="regra-recuperacao"><span class="regra-passo" aria-hidden="true">4</span>A escola tem recuperação?</h3>
        <p class="regra-opcional">Somente se fizer parte dos critérios da escola</p>
        <p>Se a escola não utiliza recuperação, siga para a revisão. Se utiliza, abra as orientações abaixo.</p>
        <details><summary>Como cadastrar a recuperação</summary><div>
            <p><strong>Salve a regra primeiro.</strong> Depois, abra a edição e, em <strong>Recuperação desta regra</strong>, clique em <strong>Nova recuperação</strong>. O cadastro precisa da regra salva e de um ano letivo selecionado.</p>
            <?php $mostrarPrintRegra('089-regras-recuperacao-lista.jpg', 'Na regra já salva, clique em Nova recuperação.'); ?>
            <ol>
                <li>Preencha o <strong>Nome</strong>, como “Recuperação trimestral”.</li>
                <li>Em <strong>Quando</strong>, escolha em que momento ela acontece: por período ou no fim do ano. No ano bimestral, também pode aparecer a opção a cada dois ciclos.</li>
                <li>Em <strong>O que a nota faz</strong>, escolha o efeito previsto pela escola: substituir se for maior, substituir a menor nota, calcular <strong>(Média + REC) / 2</strong>, somar na pontuação ou usar fórmula própria.</li>
                <li>Em <strong>Faz quem ficou abaixo de</strong>, informe a nota de corte. Em <strong>Nota máxima da REC</strong>, informe o teto da prova.</li>
                <li>Se quem já atingiu a média puder tentar aumentar a nota, marque <strong>Quem não ficou de recuperação também pode fazer</strong>.</li>
                <li>Salve a recuperação e confira se ela aparece na lista desta regra.</li>
            </ol>
            <?php $mostrarPrintRegra('089-regras-recuperacao.jpg', 'No painel Nova recuperação, preencha os campos e clique em Salvar.'); ?>
            <h4>Se a recuperação acontece a cada dois ciclos</h4>
            <p>Confira também <strong>A nota substitui</strong>: ela pode valer para os dois ciclos do par, para cada ciclo separadamente, só para o primeiro ou só para o último.</p>
            <h4>Se a escola usa fórmula própria</h4>
            <p>Monte a conta com <strong>Média</strong> e <strong>REC</strong> na área exibida. Confira o resultado de um exemplo com a coordenação antes de liberar o lançamento.</p>
        </div></details>
    </section>

    <section class="regra-bloco" aria-labelledby="regra-revisao">
        <h3 class="regra-titulo" id="regra-revisao"><span class="regra-passo" aria-hidden="true">5</span>Revise e salve</h3>
        <div class="regra-revisao"><p>Confira antes de seguir</p><ul>
            <li>O ano, o curso e as séries são os corretos?</li>
            <li>A escala e a média mínima ou o conceito mínimo correspondem aos critérios da escola?</li>
            <li>As opções de frequência representam a forma de aprovação escolhida?</li>
            <li>As disciplinas, o modelo e o público estão corretos?</li>
            <li>Se houver recuperação, ela está cadastrada com o corte e o efeito esperados?</li>
        </ul></div>
        <p>Clique em <strong>Salvar</strong>. Se já salvou a regra para cadastrar a recuperação, salve também as alterações que fizer depois. Confira na listagem se a regra aparece ativa para o ano e o curso escolhidos.</p>
        <?php $mostrarPrintRegra('090-regras-salvar.jpg', 'Após conferir os dados, clique em Salvar no fim do formulário.'); ?>
        <div class="regra-conexao"><p><strong>O que vem depois:</strong> Eventos Avaliativos organiza onde lançar as notas. A média mínima vem desta regra; as fórmulas de cálculo são tratadas nas etapas de Demonstrativo e Boletim.</p></div>
    </section>
</div>
