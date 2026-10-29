<?php
/** Instruções públicas de modelos, boletim e emissão; imagens estáticas sem dados de alunos. */
$diretorioPrintsDocumentos = \App\Dominio\Escola\Services\ImplantacaoAcademicaService::diretorioPrintsGuia();
$mostrarPrintDocumento = static function (string $arquivo, string $legenda, bool $compacto = false) use ($diretorioPrintsDocumentos, $e): void {
    if ($diretorioPrintsDocumentos === null || !is_file($diretorioPrintsDocumentos . '/' . $arquivo)) {
        return;
    }
    $dimensoesDocumento = getimagesize($diretorioPrintsDocumentos . '/' . $arquivo);
    $srcDocumento = URL . '/assets/guia-onboarding/' . rawurlencode($arquivo) . '?v=' . filemtime($diretorioPrintsDocumentos . '/' . $arquivo);
    ?>
    <figure class="gda-print<?= $compacto ? ' gda-print-compacto' : '' ?>">
        <button type="button" class="guia-foto" data-print-src="<?= $e($srcDocumento) ?>" data-print-legenda="<?= $e($legenda) ?>" aria-label="Ampliar: <?= $e($legenda) ?>" aria-haspopup="dialog"><img src="<?= $e($srcDocumento) ?>" alt="<?= $e($legenda) ?>" width="<?= (int) ($dimensoesDocumento[0] ?? 0) ?>" height="<?= (int) ($dimensoesDocumento[1] ?? 0) ?>" loading="lazy"></button>
        <figcaption><?= $e($legenda) ?> <span>· Clique para ampliar</span></figcaption>
    </figure>
    <?php
};
?>
<style>
.guia-documentos{font-size:14px;line-height:1.75}.guia-documentos p{margin:0 0 12px}.guia-documentos strong{font-weight:650}.guia-documentos .gda-resumo{font-size:15px;color:#465e6c;margin:-8px 0 20px}.guia-documentos .gda-bloco{padding:26px 0;border-bottom:1px solid #e4ecf0}.guia-documentos .gda-bloco:last-child{border:0;padding-bottom:0}.guia-documentos h3{display:flex;gap:12px;align-items:flex-start;font-size:18px;line-height:1.4;margin:0 0 16px;font-weight:700;color:#20323e}.guia-documentos .gda-numero{display:inline-grid;place-items:center;flex-shrink:0;min-width:28px;height:28px;border-radius:50%;background:#e6f4f7;color:#00728a;font-size:12px}.guia-documentos h4{font-size:14px;font-weight:700;margin:20px 0 8px}.guia-documentos ol,.guia-documentos ul{margin:10px 0 16px;padding-left:22px;list-style:decimal}.guia-documentos ul{list-style:disc}.guia-documentos li{padding-left:3px;margin:7px 0}.guia-documentos .gda-caminho{font-size:13px;color:#49616e}.guia-documentos .gda-nota{background:#f2f7fa;color:#415b6a;border-radius:9px;padding:14px 16px;margin:16px 0;font-size:13px}.guia-documentos .gda-nota p:last-child{margin-bottom:0}.guia-documentos .gda-print{margin:16px 0 22px}.guia-documentos .gda-print .guia-foto{margin:0}.guia-documentos figcaption{font-size:12px;color:#49616e;line-height:1.6;margin-top:8px}.guia-documentos figcaption span{color:#61717e}.guia-documentos .gda-print-compacto{max-width:300px}.guia-documentos .gda-conferencia{padding:16px 18px;background:#edf8f2;color:#255c43;border-radius:10px;margin:20px 0 0}.guia-documentos .gda-conferencia p{font-weight:650}.guia-documentos .gda-conferencia ul:last-child{margin-bottom:0}.guia-documentos code{overflow-wrap:anywhere;font-size:13px;background:#edf2f5;padding:3px 6px;border-radius:4px}.guia-documentos details{margin:16px 0;border-top:1px solid #e4ecf0;border-bottom:1px solid #e4ecf0}.guia-documentos summary{padding:13px 0;min-height:46px;cursor:pointer;color:#00728a;font-size:13px;font-weight:650}.guia-documentos details>div{padding-bottom:16px;font-size:13px}@media(max-width:800px){.guia-documentos h3{font-size:17px}.guia-documentos .gda-bloco{padding:22px 0}}
</style>
<div class="guia-documentos">
<?php if (($etapaGuia['chave'] ?? '') === 'demonstrativo'): ?>
    <p class="gda-resumo">Monte o demonstrativo de cada trimestre ou bimestre: vincule o modelo à regra de aprovação, escolha os eventos e subeventos que entram na nota, agrupe as matérias quando necessário e configure as colunas e fórmulas. Depois, revise a conta antes de gerar as notas.</p>
    <div class="gda-nota"><p><strong>O modelo reúne os períodos do ano.</strong> Cada período tem sua própria configuração de eventos, agrupamentos e cálculos. Os prints usam exemplos de trimestre e bimestre; siga a divisão do ano letivo da sua escola.</p></div>

    <section class="gda-bloco" aria-labelledby="gda-dem-modelo">
        <h3 id="gda-dem-modelo"><span class="gda-numero" aria-hidden="true">1</span>Cadastre o modelo com a regra de aprovação</h3>
        <p class="gda-caminho">Acadêmico → Como a escola avalia → <strong>Demonstrativo de notas → Modelos</strong></p>
        <p>Confira se o modelo já existe. Para criar um, clique em <strong>+ Novo modelo</strong>, selecione a <strong>Regra de aprovação</strong> do curso e ano desejados e clique em <strong>Cadastrar</strong>.</p>
        <?php $mostrarPrintDocumento('160-demonstrativo-cadastrar-regra.png', 'No cadastro do modelo, selecione a Regra de aprovação e clique em Cadastrar.'); ?>
        <p>A finalidade oficial ou extra, o público, a situação ativa e as disciplinas vêm da regra escolhida. Confira esses dados na lista após cadastrar. O formulário ainda usa o título <strong>Novo modelo de boletim</strong>, como aparece no print.</p>
        <div class="gda-nota"><p><strong>Cadastrar o modelo é o começo.</strong> A seleção das provas, os agrupamentos e as fórmulas são configurados depois, dentro de cada período desse modelo.</p></div>
    </section>

    <section class="gda-bloco" aria-labelledby="gda-dem-periodo">
        <h3 id="gda-dem-periodo"><span class="gda-numero" aria-hidden="true">2</span>Abra o trimestre ou bimestre que vai configurar</h3>
        <ol>
            <li>Na linha do modelo, confira <strong>Ano</strong>, <strong>Regra de aprovação</strong> e <strong>Períodos configurados</strong>.</li>
            <li>Abra <strong>Ações → Períodos do boletim</strong>.</li>
            <li>Clique no período desejado, por exemplo <strong>1º Trimestre — Configurar notas</strong>.</li>
            <li>No assistente, confira a <strong>Identidade</strong>: modelo, ano e período. Avance para escolher os eventos daquele período.</li>
        </ol>
        <?php $mostrarPrintDocumento('161-demonstrativo-escolher-periodo.png', 'Ações → Períodos do boletim lista as configurações de cada trimestre. Abra Configurar notas no período desejado.'); ?>
        <p>O nome <strong>Períodos do boletim</strong> aparece no menu do Demonstrativo. É por ele que você entra na configuração das notas de cada trimestre ou bimestre.</p>
        <div class="gda-nota"><p><strong>Se o modelo ainda não tiver períodos:</strong> use <strong>Configurar primeiro período</strong> em Ações. Depois de configurar o primeiro, <strong>Adicionar períodos</strong> permite preparar os demais aproveitando uma configuração de origem. Abra e confira cada período depois da criação.</p></div>
    </section>

    <section class="gda-bloco" aria-labelledby="gda-dem-eventos">
        <h3 id="gda-dem-eventos"><span class="gda-numero" aria-hidden="true">3</span>Escolha os eventos e os subeventos que entram na nota</h3>
        <p>Na etapa <strong>Eventos</strong>, a pergunta <strong>Quais eventos avaliativos entram neste boletim?</strong> mostra os eventos do ano e período definidos na Identidade. Confira também as turmas indicadas em cada card.</p>
        <ol>
            <li>Marque o evento que participa do demonstrativo, como <strong>Prova Bimestral</strong> ou <strong>Prova Semanal</strong>.</li>
            <li>No card marcado, confira <strong>Sub-eventos que entram na nota</strong>.</li>
            <li>Marque somente as ocorrências que devem compor o cálculo. Na Prova Semanal do exemplo, são <strong>S1, S2, S3 e S4</strong>, cada uma com sua data.</li>
            <li>Revise a seleção antes de clicar em <strong>Continuar</strong>. O cálculo e a organização das colunas vêm na etapa Fórmula.</li>
        </ol>
        <?php $mostrarPrintDocumento('162-demonstrativo-eventos-subeventos.png', 'Marque o evento e confira as caixas dos subeventos. No exemplo, entram uma Prova Bimestral e quatro provas semanais.'); ?>
        <p>As notas lançadas nos subeventos selecionados são a fonte do demonstrativo. Um subevento desmarcado não deve participar dessa seleção. Se não aparecer nenhum evento, confira o ano e o período e cadastre a avaliação em <strong>Eventos Avaliativos</strong>.</p>
        <div class="gda-nota"><p><strong>Exemplo:</strong> se apenas S1 e S2 devem contar, deixe essas duas marcadas e retire S3 e S4. Depois confira se a fórmula usa as notas e a quantidade de avaliações que você realmente selecionou.</p></div>
    </section>

    <section class="gda-bloco" aria-labelledby="gda-dem-agrupar">
        <h3 id="gda-dem-agrupar"><span class="gda-numero" aria-hidden="true">4</span>Defina como as matérias serão agrupadas</h3>
        <p>Na etapa <strong>Agrupar</strong>, use <strong>Linha única no boletim</strong> quando a escola quiser reunir desdobramentos em uma área. Por exemplo: Literatura, Interpretação de texto e Gramática formando <strong>Língua Portuguesa</strong>.</p>
        <ol>
            <li>Marque <strong>Linha única no boletim</strong> e selecione a <strong>Área</strong>. A relação entre área e matérias filhas já deve estar cadastrada em <strong>Componentes Curriculares</strong>.</li>
            <li>Em <strong>Matérias nesta área (neste evento)</strong>, marque quais filhas entram no agrupamento. Essa escolha altera este período; os outros períodos precisam ser conferidos separadamente.</li>
            <li>Em <strong>Como juntar as notas dessas matérias</strong>, escolha <strong>Média</strong> ou <strong>Soma</strong> como padrão.</li>
            <li>Em <strong>Ajuste por peça</strong>, defina exceções ao padrão. A Prova Semanal pode usar Média e a Prova Bimestral pode usar Soma, se esse for o critério da escola. O ajuste da semanal vale para as semanas e para a média semanal.</li>
            <li>Confira se quer marcar <strong>Exibir só Língua Portuguesa, sem as matérias filhas</strong>. Essa opção também vale no detalhe do aluno, no PDF, nas notas geradas e no acesso de pais e alunos.</li>
        </ol>
        <?php $mostrarPrintDocumento('163-demonstrativo-agrupar-materias.png', 'Escolha a área, as matérias filhas e o critério Média ou Soma. Ajuste por peça permite critérios diferentes para cada tipo de avaliação.'); ?>
        <div class="gda-nota"><p><strong>Exemplo de agrupamento:</strong> notas 6, 7 e 8 resultam em média 7 ou soma 21, antes dos limites e demais regras do cálculo. Escolha o critério e a escala que a escola usa; Média e Soma produzem resultados diferentes.</p></div>
        <h4>Confira também as opções abaixo do agrupamento</h4>
        <ul>
            <li><strong>Arredondamento da área:</strong> usa o arredondamento configurado no evento. Escolha <strong>Todos</strong> para filhas e linha-mãe, <strong>Só filhas</strong> para manter a mãe com a conta exata, ou <strong>Só mãe</strong> para aplicar o arredondamento apenas à linha consolidada.</li>
            <li><strong>Onde essa linha única aparece:</strong> quando disponível, escolha <strong>Só no boletim</strong> para manter as matérias separadas nas notas, ou <strong>No boletim e nas notas</strong> para agrupá-las nos dois lugares.</li>
            <li><strong>Mesma disciplina, professores diferentes:</strong> o critério de reunião dos professores vem dos eventos marcados. Confira essa informação no topo; ela é diferente do agrupamento de matérias filhas em uma área.</li>
        </ul>
        <p>Se a escola não usa uma linha consolidada para a área, mantenha <strong>Linha única no boletim</strong> desmarcada. Depois, clique em <strong>Continuar</strong>.</p>
    </section>

    <section class="gda-bloco" aria-labelledby="gda-dem-formula">
        <h3 id="gda-dem-formula"><span class="gda-numero" aria-hidden="true">5</span>Organize as colunas e monte as fórmulas</h3>
        <p>Na etapa <strong>Fórmula</strong>, confira as colunas disponíveis para os eventos e subeventos escolhidos. No exemplo, aparecem <strong>S1 a S4</strong>, <strong>Prova Semanal</strong>, <strong>Prova Bimestral</strong> e uma coluna de cálculo chamada <strong>Média final</strong>.</p>
        <h4>Ordem das colunas e cabeçalhos</h4>
        <p>Arraste as colunas para definir a ordem de exibição. O <strong>×</strong> tira a coluna da composição. Para reunir colunas sob um cabeçalho, selecione pelo menos duas, escreva o nome e clique em <strong>Criar linha</strong>. Isso organiza a apresentação; não calcula a média das colunas.</p>
        <h4>Crie ou edite uma coluna de cálculo</h4>
        <ol>
            <li>Clique em <strong>Adicionar bloco de cálculo</strong> para uma nova coluna, ou abra o bloco existente que deseja ajustar.</li>
            <li>Preencha <strong>Título da coluna</strong>, por exemplo Média final. Aqui, esse nome identifica a coluna deste demonstrativo; confira a fórmula para saber quais notas ela reúne.</li>
            <li>Defina <strong>Valor máximo</strong>, o teto permitido para o resultado dessa coluna.</li>
            <li>Em <strong>Usar no cálculo</strong>, clique nas peças disponíveis. No exemplo, <strong>PB</strong> representa Prova Bimestral, <strong>PS</strong> representa Prova Semanal e <strong>S1–S4</strong> representam as semanas. Confira os nomes da sua configuração.</li>
            <li>Acrescente os <strong>Sinais</strong> na ordem correta. Para uma constante, preencha <strong>Número</strong> e clique em <strong>Inserir</strong>.</li>
            <li>Leia a expressão abaixo de <strong>Fórmula desta coluna</strong> e clique em <strong>Salvar bloco</strong>. Repita para cada coluna de cálculo.</li>
            <li>Ao terminar todos os blocos, clique em <strong>Salvar e continuar</strong> para ir à revisão.</li>
        </ol>
        <?php $mostrarPrintDocumento('164-demonstrativo-formulas-colunas.png', 'A coluna Média final usa (PB + PS) ÷ 2. Confira Título, Valor máximo e a expressão antes de clicar em Salvar bloco.'); ?>
        <div class="gda-nota"><p><strong>Faça a soma antes da divisão.</strong> Para dar o mesmo peso a PB e PS, use <code>(PB + PS) / 2</code>. Com PB = 8 e PS = 6, o resultado é 7. Sem parênteses, <code>PB + PS / 2</code> resulta em 11, antes de aplicar o teto da coluna.</p></div>
        <h4>Valor máximo não é o peso da nota</h4>
        <p>O valor máximo limita o resultado calculado. Use 10 para uma coluna cuja escala termina em 10. Se a escola configurar uma coluna de resultado multiplicado por 2 e precisar permitir valores até 20, ajuste o máximo para <strong>20</strong>; mantê-lo em 10 corta o resultado.</p>
        <p>Os atalhos <strong>Média das peças</strong> e <strong>Maior entre as duas primeiras</strong> ajudam a montar expressões. Confira as peças usadas e a conta resultante antes de salvar. Use <strong>Limpar</strong> para recomeçar a expressão do bloco aberto.</p>
        <details><summary>Quando uma matéria precisa de outra fórmula</summary><div><p>Em <strong>Exceção só nesta matéria</strong>, selecione a matéria e clique em <strong>Usar outra fórmula nesta matéria</strong>. Por exemplo, uma matéria sem prova semanal pode precisar usar apenas PB. Monte e confira a exceção; a fórmula geral continua nas outras matérias. Revise essa matéria separadamente na simulação.</p></div></details>
    </section>

    <section class="gda-bloco" aria-labelledby="gda-dem-revisao">
        <h3 id="gda-dem-revisao"><span class="gda-numero" aria-hidden="true">6</span>Revise o demonstrativo e simule as notas</h3>
        <p>Na etapa <strong>Revisão</strong>, confira o resumo dos eventos selecionados e a quantidade de ocorrências de cada um. Depois, examine o quadro: matérias, agrupamentos, semanas, colunas de cálculo, valores máximos e ordem de exibição.</p>
        <?php $mostrarPrintDocumento('165-demonstrativo-revisao-simulacao.png', 'Na Revisão, confira o quadro e o aviso sobre a origem dos números. Simular notas com aluno permite usar os lançamentos de um aluno.'); ?>
        <ol>
            <li>Leia o aviso da prévia. Com <strong>Exemplo com dados fictícios — não são notas reais</strong>, os números servem apenas para demonstrar o cálculo.</li>
            <li>Para conferir os lançamentos, use <strong>Simular notas com aluno</strong>: clique no campo e escolha um aluno, ou pesquise pelo nome.</li>
            <li>Compare as notas usadas na simulação com os lançamentos dos subeventos e refaça pelo menos uma conta manualmente.</li>
            <li>Confira uma matéria agrupada e outra com exceção, se houver. Verifique também as colunas de recuperação que participam do seu modelo.</li>
            <li>Use <strong>Sem aluno</strong> para retirar a seleção. Se encontrar erro de coluna, ordem ou cálculo, volte à etapa <strong>Fórmula</strong>; para seleção de provas ou agrupamento, volte à etapa correspondente.</li>
        </ol>
        <div class="gda-nota"><p><strong>Confira a fonte antes de confiar no número.</strong> Sem aluno selecionado, a prévia pode usar dados fictícios. Com aluno, confira se as notas necessárias já foram lançadas nos subeventos escolhidos. A revisão não substitui o lançamento das notas.</p></div>
    </section>

    <section class="gda-bloco" aria-labelledby="gda-dem-concluir">
        <h3 id="gda-dem-concluir"><span class="gda-numero" aria-hidden="true">7</span>Escolha como salvar e confira os demais períodos</h3>
        <p>No final da Revisão, escolha a ação conforme o que você pretende fazer:</p>
        <ul>
            <li><strong>Salvar sem gerar notas:</strong> guarda a configuração. Os alunos e o boletim oficial continuam com as notas que já estão vigentes.</li>
            <li><strong>Concluir e aplicar:</strong> guarda a configuração e inicia a geração das notas dos alunos do evento. Aguarde a conclusão; o boletim oficial só muda quando a geração terminar.</li>
            <li><strong>Gerar só este aluno:</strong> aparece quando há um aluno selecionado. Guarda a configuração e publica as notas e o boletim oficial apenas dele; os demais continuam com as notas vigentes.</li>
        </ul>
        <p>Se a geração não concluir, a configuração salva permanece e a geração pode ser retomada na página do evento. Depois de concluir este período, volte a <strong>Modelos → Ações → Períodos do boletim</strong> e abra o próximo trimestre ou bimestre.</p>
        <p>Repita a conferência de <strong>eventos e subeventos → agrupamento → fórmulas → revisão</strong> em todos os períodos. Mesmo ao aproveitar uma configuração anterior, confira as provas, as datas e os vínculos do período de destino.</p>
        <div class="gda-conferencia"><p>Antes de seguir para o Boletim</p><ul><li>O modelo está vinculado à regra e ao ano corretos.</li><li>Todos os trimestres ou bimestres necessários estão configurados.</li><li>Entram somente os eventos e subeventos previstos pela escola.</li><li>Os agrupamentos, as escalas e as fórmulas de cada coluna foram conferidos.</li><li>A prévia foi revisada e a simulação com notas lançadas foi conferida.</li><li>Se você escolheu gerar as notas, a geração terminou e o resultado foi conferido.</li></ul></div>
    </section>
<?php elseif (($etapaGuia['chave'] ?? '') === 'boletim'): ?>
    <p class="gda-resumo">Cadastre o boletim e monte o quadro que aparecerá no documento: organize as colunas de cada trimestre, confira a média final e revise o resultado antes de salvar.</p>
    <section class="gda-bloco" aria-labelledby="gda-bol-cadastro">
        <h3 id="gda-bol-cadastro"><span class="gda-numero" aria-hidden="true">1</span>Escolha o ano e cadastre o boletim</h3>
        <p class="gda-caminho">Acadêmico → Como a escola avalia → <strong>Boletim</strong></p>
        <p>Selecione o <strong>Ano</strong> e confira os boletins existentes. Para criar um, clique em <strong>+ Novo boletim</strong>.</p>
        <?php $mostrarPrintDocumento('142-boletim-lista.jpg', 'Confira o Ano e a regra na lista. O botão + Novo boletim inicia o cadastro.'); ?>
        <p>Selecione a <strong>Regra de aprovação</strong> do curso e ano desejados e clique em <strong>Cadastrar</strong>. Nome, finalidade, público e disciplinas vêm dessa regra.</p>
        <?php $mostrarPrintDocumento('143-boletim-cadastro.jpg', 'Escolha a Regra de aprovação e clique em Cadastrar.'); ?>
    </section>
    <section class="gda-bloco" aria-labelledby="gda-bol-layout">
        <h3 id="gda-bol-layout"><span class="gda-numero" aria-hidden="true">2</span>Abra o layout do boletim</h3>
        <p>Na linha do boletim, abra <strong>Ações → Layout do boletim</strong>. O editor tem três etapas: <strong>Linhas</strong>, <strong>Fórmula</strong> e <strong>Revisão</strong>.</p>
        <?php $mostrarPrintDocumento('144-boletim-acoes.jpg', 'No menu Ações, clique em Layout do boletim.'); ?>
    </section>
    <section class="gda-bloco" aria-labelledby="gda-bol-linhas">
        <h3 id="gda-bol-linhas"><span class="gda-numero" aria-hidden="true">3</span>Organize cada trimestre na etapa Linhas</h3>
        <p>Cada faixa à direita reúne as colunas de um trecho do quadro. No exemplo, há uma faixa para cada trimestre e outra para o resultado final.</p>
        <?php $mostrarPrintDocumento('152-boletim-criar-linha.jpg', 'Na etapa Linhas, selecione uma faixa do quadro. + Criar linha adiciona outra faixa.'); ?>
        <ol><li>Clique na faixa <strong>1º Trimestre</strong> para selecioná-la. Ela fica contornada.</li><li>Na lista <strong>Colunas</strong>, à esquerda, clique nas opções daquele trimestre que deseja incluir, como <strong>Média</strong>, <strong>Faltas</strong>, <strong>Recuperação</strong> e <strong>Situação</strong>. As opções já usadas ficam na faixa.</li><li>Selecione <strong>2º Trimestre</strong> e repita a montagem com as colunas correspondentes. Faça o mesmo com o <strong>3º Trimestre</strong> e a faixa <strong>Final</strong>.</li><li>Confira os nomes das faixas e clique em <strong>Continuar</strong>.</li></ol>
        <?php $mostrarPrintDocumento('145-boletim-linhas.jpg', 'A faixa 2º Trimestre está selecionada. Clique em uma opção da lista Colunas para adicioná-la à faixa marcada.'); ?>
        <div class="gda-nota"><p>Para tirar uma coluna, clique no <strong>×</strong> ao lado dela; a opção volta à lista. Use <strong>+ Criar linha</strong> quando precisar de outra faixa e preencha seu nome. O ícone de lixeira remove a faixa.</p></div>
    </section>
    <section class="gda-bloco" aria-labelledby="gda-bol-formula">
        <h3 id="gda-bol-formula"><span class="gda-numero" aria-hidden="true">4</span>Confira o cálculo da média final</h3>
        <p>Na etapa <strong>Fórmula</strong>, as variáveis representam as médias dos períodos: <strong>m1</strong> é o 1º trimestre, <strong>m2</strong> o 2º e <strong>m3</strong> o 3º, no exemplo abaixo.</p>
        <p>Para a média simples dos três trimestres, o cálculo é <code>(m1 + m2 + m3) / 3</code>. Os parênteses fazem a soma acontecer antes da divisão.</p>
        <ol><li>Para montar outro cálculo, use <strong>Limpar</strong> e clique ou arraste as médias e os sinais na ordem desejada.</li><li>Para incluir um valor, preencha <strong>Número</strong> e clique em <strong>Colocar número</strong>.</li><li>Confira a expressão exibida abaixo das peças e clique em <strong>Continuar</strong>.</li></ol>
        <?php $mostrarPrintDocumento('146-boletim-formula.jpg', 'Exemplo montado com os três trimestres: (m1 + m2 + m3) / 3. Use os critérios da sua escola.'); ?>
        <div class="gda-nota"><p>Se deixar a fórmula em branco, o sistema usa a média simples dos períodos. Esse campo configura a <strong>média final</strong>; as notas que compõem cada trimestre vêm dos eventos e das configurações acadêmicas.</p></div>
    </section>
    <section class="gda-bloco" aria-labelledby="gda-bol-revisao">
        <h3 id="gda-bol-revisao"><span class="gda-numero" aria-hidden="true">5</span>Revise o quadro e salve o layout</h3>
        <p>Confira o nome e a ordem das faixas, as colunas de cada trimestre e o bloco Final. Se precisar corrigir, clique em <strong>Voltar</strong>. Quando estiver certo, clique em <strong>Salvar layout</strong>.</p>
        <?php $mostrarPrintDocumento('147-boletim-revisao.jpg', 'A etapa Revisão mostra o quadro montado. Confira as colunas antes de clicar em Salvar layout.'); ?>
        <div class="gda-nota"><p><strong>Os números da prévia são exemplos.</strong> Essa tela ajuda a conferir a organização do documento; a conferência das notas dos alunos acontece no relatório.</p></div>
        <div class="gda-conferencia"><p>Antes de emitir</p><ul><li>Todos os trimestres estão representados com as colunas corretas.</li><li>O cálculo da média final segue o critério da escola.</li><li>O layout foi salvo após a revisão.</li></ul></div>
    </section>
<?php elseif (($etapaGuia['chave'] ?? '') === 'relatorio'): ?>
    <p class="gda-resumo">Escolha o documento, delimite o grupo de alunos e gere o relatório para conferir as notas. Depois, use a exportação adequada para a escola.</p>
    <section class="gda-bloco" aria-labelledby="gda-rel-documento">
        <h3 id="gda-rel-documento"><span class="gda-numero" aria-hidden="true">1</span>Escolha o que deseja consultar</h3>
        <p class="gda-caminho">Acadêmico → Como a escola avalia → <strong>Relatórios</strong></p>
        <p>No campo <strong>Exibir</strong>, escolha <strong>Boletim</strong> para consultar o documento da vida escolar ou <strong>Demonstrativo de notas</strong> para consultar o demonstrativo. Confira também o <strong>Ano letivo</strong>.</p>
        <?php $mostrarPrintDocumento('149-relatorio-documento.jpg', 'Abra Exibir e escolha Boletim ou Demonstrativo de notas.'); ?>
    </section>
    <section class="gda-bloco" aria-labelledby="gda-rel-filtros">
        <h3 id="gda-rel-filtros"><span class="gda-numero" aria-hidden="true">2</span>Defina o período e os alunos</h3>
        <ol><li>Em <strong>Trimestre</strong>, escolha o período desejado ou <strong>Todos</strong> para consultar o conjunto. O nome desse campo acompanha a organização do ano da escola.</li><li>Selecione <strong>Curso/Ciclo</strong> e <strong>Turma</strong> para restringir a consulta. Deixe Todos/Todas quando quiser abranger o grupo inteiro.</li><li>Para consultar uma pessoa, use <strong>Aluno</strong> e pesquise por nome, RA ou código.</li><li>Se precisar de espaço para coleta de assinatura, marque <strong>Incluir campo de assinatura ao lado do nome</strong>.</li><li>Clique em <strong>Gerar relatório</strong>. Se alterar os filtros depois, gere novamente.</li></ol>
        <?php $mostrarPrintDocumento('148-relatorio-filtros.jpg', 'Preencha os filtros e clique em Gerar relatório. Limpar permite recomeçar a consulta.'); ?>
        <details><summary>Como consultar notas abaixo de um corte</summary><div><p>Preencha <strong>Nota abaixo de</strong>, por exemplo 6, e escolha em <strong>Matérias</strong> se quer ver todas ou <strong>Só as que estão abaixo do corte</strong>. Gere novamente para aplicar a consulta. Deixe o corte vazio e escolha Todas as matérias para a conferência geral.</p></div></details>
    </section>
    <section class="gda-bloco" aria-labelledby="gda-rel-exportar">
        <h3 id="gda-rel-exportar"><span class="gda-numero" aria-hidden="true">3</span>Confira e exporte o resultado</h3>
        <p>Na lista gerada, confira aluno, turma, matérias, notas e faltas dos períodos escolhidos. Antes de distribuir, confira uma amostra e verifique se os dados correspondem ao ano e ao grupo selecionados.</p>
        <p>Os ícones no alto do resultado permitem <strong>Baixar boletins (ZIP)</strong>, <strong>Exportar Excel</strong>, <strong>Exportar JSON</strong> e <strong>Exportar TXT</strong>, conforme o documento e os dados disponíveis. Passe o mouse sobre o ícone para identificar a ação.</p>
        <?php $mostrarPrintDocumento('150-relatorio-exportacoes.jpg', 'Ações no resultado de Boletim: ZIP, Excel, JSON e TXT, da esquerda para a direita.', true); ?>
        <p>Para um único boletim, use o ícone vermelho <strong>PDF deste boletim</strong> no cabeçalho do aluno. Para o grupo, <strong>Baixar boletins (ZIP)</strong> reúne um PDF por aluno; acompanhe o processamento e clique em <strong>Baixar ZIP</strong> quando estiver pronto.</p>
        <?php $mostrarPrintDocumento('151-relatorio-pdf-individual.jpg', 'O botão PDF no cabeçalho de cada aluno abre a emissão individual.', true); ?>
        <div class="gda-nota"><p>Se o ZIP estiver indisponível, confira se os alunos têm ficha na <strong>Vida Escolar</strong>. Se o demonstrativo indicar ausência de versão vigente, a escola precisa concluir a geração dos resultados antes dessa consulta.</p></div>
        <div class="gda-conferencia"><p>Conferência final</p><ul><li>Documento, ano, período e turma estão corretos.</li><li>Notas e faltas foram conferidas com os lançamentos da escola.</li><li>O arquivo exportado foi aberto e revisado antes da entrega.</li></ul></div>
    </section>
<?php endif; ?>
</div>
