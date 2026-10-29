<?php
/** Guia público do fluxo de eventos; capturas reais, sem acesso ao cadastro do tenant. */
$diretorioPrintsEventos = \App\Dominio\Escola\Services\ImplantacaoAcademicaService::diretorioPrintsGuia();
$mostrarPrintEvento = static function (string $arquivo, string $legenda, bool $painel = false) use ($diretorioPrintsEventos, $e): void {
    if ($diretorioPrintsEventos === null || !is_file($diretorioPrintsEventos . '/' . $arquivo)) {
        return;
    }
    $dimensoesEvento = getimagesize($diretorioPrintsEventos . '/' . $arquivo);
    $larguraEvento = (int) ($dimensoesEvento[0] ?? 0);
    $alturaEvento = (int) ($dimensoesEvento[1] ?? 0);
    $srcEvento = URL . '/assets/guia-onboarding/' . rawurlencode($arquivo) . '?v=' . filemtime($diretorioPrintsEventos . '/' . $arquivo);
    ?>
    <figure class="evg-print<?= $painel ? ' evg-print-painel' : '' ?>">
        <button type="button" class="guia-foto" data-print-src="<?= $e($srcEvento) ?>" data-print-legenda="<?= $e($legenda) ?>" aria-label="Ampliar: <?= $e($legenda) ?>" aria-haspopup="dialog"><img src="<?= $e($srcEvento) ?>" alt="<?= $e($legenda) ?>" width="<?= $larguraEvento ?>" height="<?= $alturaEvento ?>" loading="lazy"></button>
        <figcaption><?= $e($legenda) ?> <span>· Clique na imagem para ampliar</span></figcaption>
    </figure>
    <?php
};
?>
<style>
.guia-eventos{font-size:14px;line-height:1.75}.guia-eventos p{margin:0 0 12px}.guia-eventos strong{font-weight:650}.guia-eventos .evg-resumo{font-size:15px;color:#465e6c;margin:-8px 0 20px}.guia-eventos .evg-preparo,.guia-eventos .evg-exemplo{padding:14px 16px;background:#f2f7fa;border-radius:9px;color:#415b6a;font-size:13px;margin:16px 0}.guia-eventos .evg-preparo p,.guia-eventos .evg-exemplo p:last-child{margin:4px 0 0}.guia-eventos .evg-bloco{padding:26px 0;border-bottom:1px solid #e4ecf0}.guia-eventos .evg-bloco:last-child{border:0;padding-bottom:0}.guia-eventos .evg-titulo{display:flex;align-items:flex-start;gap:12px;margin:0 0 16px;font-size:18px;font-weight:700;line-height:1.4;color:#20323e}.guia-eventos .evg-passo{display:inline-grid;place-items:center;flex-shrink:0;min-width:28px;height:28px;background:#e6f4f7;border-radius:50%;font-size:12px;color:#00728a}.guia-eventos h4{font-size:14px;font-weight:700;margin:22px 0 8px;color:#20323e}.guia-eventos ul,.guia-eventos ol{margin:10px 0 14px;padding-left:22px;list-style:disc}.guia-eventos ol{list-style:decimal}.guia-eventos li{padding-left:3px;margin:7px 0}.guia-eventos .evg-caminho{font-size:13px;color:#49616e;overflow-wrap:anywhere}.guia-eventos .evg-alerta{border-left:3px solid #d29b38;padding:2px 0 2px 13px;margin:16px 0;font-size:13px;color:#6b511f}.guia-eventos .evg-print{margin:16px 0 22px}.guia-eventos .evg-print .guia-foto{margin:0}.guia-eventos .evg-print figcaption{font-size:12px;color:#49616e;margin-top:8px;line-height:1.6}.guia-eventos .evg-print figcaption span{color:#61717e}.guia-eventos .evg-print-painel{max-width:430px}.guia-eventos details{margin:16px 0;border-top:1px solid #e4ecf0;border-bottom:1px solid #e4ecf0}.guia-eventos summary{cursor:pointer;font-size:13px;font-weight:650;padding:13px 0;min-height:46px;color:#00728a}.guia-eventos details>div{padding:0 0 16px;font-size:13px}.guia-eventos .evg-opcoes{margin:12px 0}.guia-eventos .evg-opcoes>div{padding:10px 0;border-top:1px solid #e8eef2}.guia-eventos dt{font-weight:650;color:#20323e}.guia-eventos dd{margin:3px 0 0;color:#49616e;font-size:13px}.guia-eventos .evg-revisao{padding:16px 18px;background:#edf8f2;border-radius:10px;color:#255c43;margin:16px 0}.guia-eventos .evg-revisao p{font-weight:650;margin:0}.guia-eventos .evg-revisao ul:last-child{margin-bottom:0}.guia-eventos .evg-legenda{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#61717e}.guia-eventos .evg-bloco>p:last-child{margin-bottom:0}@media(max-width:800px){.guia-eventos .evg-titulo{font-size:17px;gap:10px}.guia-eventos .evg-bloco{padding:22px 0}}
</style>
<div class="guia-eventos">
    <p class="evg-resumo">Organize as avaliações do período: escolha as turmas, as disciplinas, as datas e como as notas serão reunidas. Depois, abra cada subevento para lançar notas ou gerenciar a prova online.</p>
    <div class="evg-preparo"><strong>Antes de começar</strong><p>Tenha a regra de aprovação, o ano letivo e as turmas cadastrados, com alunos matriculados. Os prints mostram exemplos; use os dados e critérios da sua escola.</p></div>

    <section class="evg-bloco" aria-labelledby="evg-periodo">
        <h3 class="evg-titulo" id="evg-periodo"><span class="evg-passo" aria-hidden="true">1</span>Abra a regra e escolha o período</h3>
        <p class="evg-caminho">Como a escola avalia → <strong>Eventos Avaliativos</strong></p>
        <p>Confira o curso e o ano. Clique em <strong>Abrir</strong> na regra que vai receber as avaliações. Se não encontrar a regra, confira os <strong>Filtros</strong> ou volte a Regras de Aprovação.</p>
        <?php $mostrarPrintEvento('110-eventos-regras.jpg', 'Clique em Abrir na linha da regra de nota desejada.'); ?>
        <p>No mapa do ano, clique no <strong>bimestre, trimestre ou semestre</strong> da avaliação. Os cards de recuperação têm identificação própria e dependem do que foi configurado na regra.</p>
        <?php $mostrarPrintEvento('111-eventos-periodos.jpg', 'Abra o card do período. Os cards vermelhos correspondem às recuperações.'); ?>
        <p>Na lista do período, clique em <strong>+ Novo evento</strong>.</p>
        <?php $mostrarPrintEvento('112-eventos-lista.jpg', 'O botão + Novo evento fica acima da lista. Cada evento agrupa seus subeventos.'); ?>
        <div class="evg-exemplo"><strong>Evento e subevento: qual a diferença?</strong><p>“Prova Semanal” pode ser o evento. S1, S2, S3 e S4 são os subeventos, cada um com sua data. Mesmo um evento único tem um subevento. É nele que você acessa o lançamento ou a prova.</p></div>
    </section>

    <section class="evg-bloco" aria-labelledby="evg-identidade">
        <h3 class="evg-titulo" id="evg-identidade"><span class="evg-passo" aria-hidden="true">2</span>Identidade: nome, público e turmas</h3>
        <ul>
            <li>Preencha <strong>Nome</strong> e uma <strong>Sigla</strong> curta, como PS para Prova Semanal. A sigla aparece na fórmula das notas.</li>
            <li>Confira <strong>Ano letivo</strong> e <strong>Período</strong>. Eles vêm do caminho que você abriu e ficam bloqueados no formulário.</li>
            <li>Marque <strong>Disponibilizar para o aluno ao lançar a nota</strong> somente se quiser liberar a consulta de cada nota assim que ela for lançada.</li>
        </ul>
        <?php $mostrarPrintEvento('113-eventos-identidade.jpg', 'Na etapa Identidade, preencha Nome e Sigla e confira a opção de disponibilizar as notas.'); ?>
        <p>Em <strong>Turmas</strong>, escolha o ensino, se aparecer essa opção, e marque as turmas participantes. Use <strong>Marcar todas</strong> apenas se o evento atender a todas as turmas exibidas.</p>
        <p>Se quiser mostrar as datas no calendário, marque <strong>Disponibilizar no calendário letivo</strong>. O curso das turmas selecionadas precisa ter calendário cadastrado naquele ano.</p>
        <?php $mostrarPrintEvento('114-eventos-turmas.jpg', 'Selecione as turmas, confira a opção de calendário e clique em Continuar.'); ?>
        <p class="evg-alerta">Liberar cada nota para o aluno e mostrar uma prova online no portal são opções diferentes. A média do período depende da configuração e geração do boletim.</p>
    </section>

    <section class="evg-bloco" aria-labelledby="evg-estrutura">
        <h3 class="evg-titulo" id="evg-estrutura"><span class="evg-passo" aria-hidden="true">3</span>Estrutura: onde acontece e quem participa</h3>
        <h4>A avaliação acontece dentro ou fora da plataforma?</h4>
        <dl class="evg-opcoes"><div><dt>Avaliações offline</dt><dd>Para provas presenciais, trabalhos ou outras atividades cuja nota será informada pela escola ou pelo professor.</dd></div><div><dt>Avaliação online</dt><dd>Para o aluno responder à prova dentro da plataforma. Você também precisará gerenciar a elaboração e a liberação da prova.</dd></div></dl>
        <?php $mostrarPrintEvento('115-eventos-tipo.jpg', 'Escolha o Tipo de Avaliação. Em Disciplina, use + Adicionar disciplina ou abra uma linha para ajustar.'); ?>
        <h4>Adicione as disciplinas e confira as turmas de cada uma</h4>
        <p>Clique em <strong>+ Adicionar disciplina</strong> ou na linha existente. No painel lateral, escolha a <strong>Disciplina</strong>, o <strong>Professor</strong>, quando exigido, e as <strong>Turmas</strong> daquela linha. Clique em <strong>Concluir</strong> para voltar ao formulário.</p>
        <?php $mostrarPrintEvento('116-eventos-disciplina.jpg', 'No painel da disciplina, selecione os vínculos e clique em Concluir. O professor é opcional quando a escola ou a coordenação assume a atividade.', true); ?>
        <h4>Quem lança a nota?</h4>
        <p>No offline, escolha <strong>Atribuir ao professor</strong> ou <strong>Escola lança a nota</strong>. Se atribuir ao professor, informe o professor nas disciplinas e o <strong>Prazo para o professor lançar as notas</strong>.</p>
        <?php $mostrarPrintEvento('117-eventos-responsavel.jpg', 'Em Quem lança a nota, escolha o responsável. Ao atribuir ao professor, preencha o prazo.'); ?>
        <details><summary>Se a avaliação for online</summary><div>
            <p>Escolha <strong>Atribuir professor</strong> para ele elaborar as questões, ou <strong>Coordenação faz a prova</strong>. Na atribuição ao professor, confira o prazo e a quantidade de questões nos vínculos da disciplina.</p>
            <p>Marque <strong>Mostrar este evento no portal do aluno</strong> quando quiser disponibilizá-lo em Minhas provas. Desmarcado, o aluno não vê o evento nem acessa por link.</p>
            <?php $mostrarPrintEvento('128-eventos-online.jpg', 'No online, confira a opção Mostrar este evento no portal do aluno.'); ?>
            <p>Depois de criar o evento, abra <strong>Ações → Gerenciar prova</strong> no subevento para continuar a preparação. Criar o evento, mostrar no portal e liberar a realização da prova são partes distintas.</p>
        </div></details>
    </section>

    <section class="evg-bloco" aria-labelledby="evg-datas">
        <h3 class="evg-titulo" id="evg-datas"><span class="evg-passo" aria-hidden="true">4</span>Defina as datas e como a nota será calculada</h3>
        <h4>Acontece uma vez ou em várias datas?</h4>
        <p>Em <strong>Periodicidade</strong>, escolha <strong>Evento único</strong> para uma avaliação ou <strong>Eventos múltiplos</strong> para várias ocorrências. A escolha vale para online e offline.</p>
        <ul>
            <li>No único, preencha a <strong>Data da avaliação</strong> e os horários de início e término.</li>
            <li>Nos múltiplos, informe a <strong>Quantidade de subeventos</strong> e confira nome, data e horários de cada um. Use <strong>Ajustar</strong> se um subevento precisar de vínculos próprios.</li>
            <li>Use <strong>Mesma data para todas</strong>, <strong>Por série / ano</strong> ou <strong>Por turma</strong> conforme a agenda da escola. Clique no campo de data para abrir o calendário.</li>
        </ul>
        <?php $mostrarPrintEvento('118-eventos-datas.jpg', 'Em Periodicidade, escolha o formato e preencha a agenda. As abas permitem diferenciar série ou turma.'); ?>
        <h4>Você pode definir uma agenda diferente para cada subevento</h4>
        <p>Em <strong>Eventos múltiplos</strong>, abra a agenda de cada subevento e escolha como distribuir suas datas e horários. S1 pode acontecer em um dia e S2 em outro; dentro de S1, turmas ou séries também podem fazer a avaliação em dias ou horários diferentes.</p>
        <dl class="evg-opcoes">
            <div><dt>Mesma data para todas</dt><dd>Defina uma data, um início e um término. Todas as turmas selecionadas usam essa agenda naquele subevento.</dd></div>
            <div><dt>Por série / ano</dt><dd>Preencha a data, o início e o término de cada série. As turmas da mesma série compartilham essa agenda; outras séries podem ter outra data ou outro horário.</dd></div>
        </dl>
        <?php $mostrarPrintEvento('129-eventos-agenda-por-serie.png', 'Clique em Por série / ano e preencha Data, Início e Término em cada linha.'); ?>
        <h4>Para separar turmas, use Por turma</h4>
        <ol>
            <li>Clique em <strong>Por turma</strong>. No primeiro bloco, marque as turmas que farão a avaliação juntas. Você também pode marcar uma série inteira.</li>
            <li>Preencha <strong>Data</strong>, <strong>Início</strong> e <strong>Término</strong> para esse grupo.</li>
            <li>Clique em <strong>+ Adicionar horário</strong> para criar outro bloco. Marque as demais turmas e informe a data e os horários delas.</li>
        </ol>
        <?php $mostrarPrintEvento('130-eventos-agenda-por-turma.png', 'Em Por turma, agrupe as turmas de cada agenda e use + Adicionar horário para separar os demais grupos.'); ?>
        <p class="evg-alerta">Em cada subevento, todas as turmas participantes precisam estar distribuídas nos horários. A mesma turma deve entrar uma única vez nessa distribuição.</p>
        <div class="evg-exemplo"><strong>Exemplo ilustrativo</strong><p>Em S1, 1A e 1B podem fazer a avaliação em 02/12, das 8h às 9h, e 2A e 2B em 03/12, das 10h às 11h. Em S2, você define outra agenda, independentemente da usada em S1.</p></div>
        <p>Essas três opções também estão disponíveis no <strong>Evento único</strong>. Nesse caso, você distribui as turmas na agenda da única avaliação.</p>
        <h4>Como as notas dos subeventos viram uma nota do evento?</h4>
        <p>Na seção <strong>Como esta nota é calculada</strong>, confira o <strong>Tipo de nota do evento avaliativo</strong> e a <strong>Escala (Nota Máxima)</strong>. Essa é a consolidação do evento; ela será usada depois no demonstrativo e no boletim.</p>
        <?php $mostrarPrintEvento('119-eventos-calculo.jpg', 'Escolha o Tipo de nota, confira a escala e, quando se aplicar, a combinação entre professores.'); ?>
        <details><summary>Entender os tipos de cálculo</summary><div>
            <dl class="evg-opcoes"><div><dt>Última nota lançada</dt><dd>Usa a última nota conforme o critério do evento.</dd></div><div><dt>Maior nota</dt><dd>Usa a maior nota entre os lançamentos.</dd></div><div><dt>Média das notas</dt><dd>Calcula a média dos lançamentos.</dd></div><div><dt>Soma (depois limita na escala)</dt><dd>Soma as notas e aplica o teto da escala.</dd></div><div><dt>Aproveitamento</dt><dd>Soma os acertos, divide pelo total de questões e multiplica pela escala. Não é a média simples das notas de cada prova.</dd></div></dl>
            <p>Quando aparecer <strong>O que cada evento registra</strong>, confira se o registro é de nota ou de acertos e total de questões.</p>
        </div></details>
        <details><summary>Mesma nota em várias matérias ou professores dividindo disciplina</summary><div>
            <p><strong>Mesma nota em todas as matérias do evento</strong>, quando disponível, repete nas outras matérias a nota lançada pela escola. Deixe desmarcada se cada matéria tiver sua própria nota.</p>
            <p>Em <strong>Professores da mesma disciplina (que dividem frente)</strong>, escolha se os lançamentos entram separados, pela média dos professores ou pela soma. Exemplo: notas 4 e 6 resultam em média 5 ou soma 10, limitada à escala.</p>
        </div></details>
        <p>Depois de conferir disciplinas, responsável, datas e cálculo, clique em <strong>Continuar</strong>.</p>
    </section>

    <section class="evg-bloco" aria-labelledby="evg-revisao">
        <h3 class="evg-titulo" id="evg-revisao"><span class="evg-passo" aria-hidden="true">5</span>Revise antes de concluir o cadastro</h3>
        <p>Na etapa <strong>Revisão</strong>, confira o evento, as turmas, o tipo de avaliação, quem lança a nota e a opção de calendário.</p>
        <?php $mostrarPrintEvento('120-eventos-revisao.jpg', 'A Revisão reúne as escolhas feitas em Identidade e Estrutura. Use Voltar para corrigir.'); ?>
        <p>Confira também a escala, os subeventos, as disciplinas e a prévia <strong>Como a nota aparece no quadro</strong>. Os números dessa prévia são exemplos para demonstrar o cálculo.</p>
        <?php $mostrarPrintEvento('121-eventos-concluir.jpg', 'Confira a prévia e clique em Concluir para gravar. Até essa ação, o novo evento ainda não foi criado.'); ?>
        <details><summary>O que significam rascunho, publicação e cores do período?</summary><div>
            <p>Um evento novo é criado ao clicar em <strong>Concluir</strong>. Eventos copiados do período anterior entram como <strong>rascunho</strong>, quando a opção de duplicar estiver disponível.</p>
            <p>Para um rascunho, use <strong>Ações → Editar</strong> e conclua a revisão para salvar e publicar, ou <strong>Ações → Publicar</strong> quando estiver pronto.</p>
            <p>No mapa: borda <strong>amarela</strong> indica que há rascunho no período; <strong>verde</strong> indica que há evento e nenhum rascunho; borda neutra indica período sem evento. Os cards de recuperação têm identificação vermelha própria.</p>
            <p>Publicar o evento não substitui as opções de consulta das notas nem os controles de realização de uma prova online.</p>
        </div></details>
    </section>

    <section class="evg-bloco" aria-labelledby="evg-notas">
        <h3 class="evg-titulo" id="evg-notas"><span class="evg-passo" aria-hidden="true">6</span>Abra o subevento para lançar as notas</h3>
        <p>Na lista do período, expanda o evento. Na linha do <strong>subevento</strong>, abra <strong>Ações → Lançar notas</strong> para avaliações offline. No online, a ação é <strong>Gerenciar prova</strong>.</p>
        <?php $mostrarPrintEvento('122-eventos-acoes.jpg', 'Use Ações na linha do subevento, abaixo do evento principal.'); ?>
        <p>No lançamento, selecione a <strong>aba da disciplina</strong>. Confira as turmas e os alunos; use <strong>Filtros</strong> quando necessário. A primeira disciplina já vem selecionada.</p>
        <?php $mostrarPrintEvento('123-eventos-notas.jpg', 'Escolha a disciplina na aba. Importar planilha e Desbloquear edição ficam no topo.'); ?>
        <p>Preencha <strong>Nota</strong> e, se necessário, <strong>Observação</strong>. No lançamento direto e na importação, as notas são de <strong>0 a 10</strong>. Clique em <strong>Salvar notas</strong> no fim da tela.</p>
        <?php $mostrarPrintEvento('124-eventos-campo-nota.jpg', 'A nota é preenchida na coluna Nota. O cadeado indica que esse lançamento já salvo está bloqueado.'); ?>
        <details><summary>Como alterar uma nota já salva</summary><div>
            <p>Notas novas podem ser lançadas normalmente. Para corrigir uma nota já salva, clique em <strong>Desbloquear edição</strong> e confirme com a senha da sua conta. Depois, faça a correção e clique em <strong>Salvar notas</strong>.</p>
            <?php $mostrarPrintEvento('126-eventos-desbloqueio.jpg', 'Desbloquear edição pede a confirmação da senha de acesso.', true); ?>
        </div></details>
        <details><summary>Como importar várias notas por planilha</summary><div>
            <ol><li>Clique em <strong>Importar planilha</strong> e em <strong>Baixar modelo preenchido com os alunos</strong>.</li><li>Preencha a coluna <strong>nota</strong>, de 0 a 10. Preserve as colunas <strong>codigo</strong> e <strong>disciplina</strong>; código é o código do aluno ou o RA.</li><li>Salve em <strong>CSV</strong> ou <strong>Excel (.xlsx)</strong>. Em <strong>Escolher Arquivo</strong>, selecione a planilha e clique em <strong>Importar</strong>.</li><li>Confira o resumo da importação e os lançamentos nas abas das disciplinas.</li></ol>
            <?php $mostrarPrintEvento('125-eventos-planilha.jpg', 'Baixe o modelo, escolha o arquivo e importe. A opção Substituir exige edição desbloqueada.', true); ?>
            <p>Notas vazias são ignoradas. Códigos e disciplinas precisam corresponder ao cadastro. Para substituir notas existentes, desbloqueie a edição com a senha e marque <strong>Substituir notas já lançadas</strong>.</p>
        </div></details>
        <details><summary>Onde conferir o resultado do subevento</summary><div><p>No mesmo menu <strong>Ações</strong> da linha do subevento, abra <strong>Ver resultado</strong>. O demonstrativo e o boletim vão consumir os lançamentos conforme as configurações das próximas etapas.</p></div></details>
    </section>

    <section class="evg-bloco" aria-labelledby="evg-recuperacao">
        <h3 class="evg-titulo" id="evg-recuperacao"><span class="evg-passo" aria-hidden="true">7</span>Recuperação: selecione os alunos e lance a nota</h3>
        <p>A recuperação precisa estar cadastrada em <strong>Regras de Aprovação</strong>. No mapa do ano, abra o <strong>card da recuperação correspondente ao período</strong>. Para a recuperação final, use o card próprio do fim do ano.</p>
        <?php $mostrarPrintEvento('131-eventos-card-recuperacao.png', 'Abra o card de Recuperação do período, em vez do card da avaliação regular.', true); ?>

        <h4>Escolha quem faz a recuperação em cada disciplina</h4>
        <ol>
            <li>Na lista da recuperação, clique em <strong>+ Novo evento</strong>. Para ajustar um evento existente, use <strong>Ações → Editar</strong> no evento principal.</li>
            <li>Na etapa <strong>Identidade</strong>, preencha nome e sigla e marque as <strong>turmas</strong>. A seção <strong>Quem faz a recuperação</strong> carrega os alunos separados por disciplina.</li>
            <li>Confira a média de referência exibida. Clique em <strong>Puxar alunos abaixo da média</strong> para marcar os casos identificados pelo sistema nas disciplinas carregadas.</li>
            <li>Percorra as abas, como <strong>Geografia</strong>, <strong>História</strong> e <strong>Matemática</strong>. Confira as turmas e as caixas de seleção de cada aluno em cada matéria.</li>
        </ol>
        <?php $mostrarPrintEvento('132-eventos-alunos-recuperacao.png', 'Em Quem faz a recuperação, use Puxar alunos abaixo da média e confira a seleção em todas as abas de disciplina.'); ?>
        <p><strong>Linha vermelha:</strong> indica que o aluno ficou abaixo da média de referência naquela matéria. <strong>Caixa marcada:</strong> indica que ele está selecionado para participar da recuperação nessa matéria. Ao editar o evento, a seleção já salva pode aparecer marcada.</p>
        <p class="evg-alerta">A nota mostrada ao lado do aluno é uma referência já registrada no período. Ela ajuda a conferir quem ficou abaixo; não é a nota nova da recuperação.</p>
        <div class="evg-exemplo"><strong>Exemplo ilustrativo</strong><p>Um aluno pode estar abaixo da média em Geografia e acima em Matemática. Confira a seleção em Geografia; isso não significa que ele precise fazer recuperação também em Matemática.</p></div>
        <p>Você pode revisar as marcações antes de salvar. Para incluir quem já atingiu a média e quer tentar aumentar a nota, confira se essa possibilidade faz parte da recuperação configurada em <strong>Regras de Aprovação</strong>.</p>
        <details><summary>Não apareceu ninguém abaixo da média?</summary><div><p>Confira as turmas selecionadas, o ano, o período e as notas já lançadas. Sem notas do período nessas turmas, a tela pode não ter dados para separar os alunos por matéria. Não ter aluno destacado não significa, sozinho, que os lançamentos já foram concluídos.</p></div></details>

        <h4>Salve os participantes antes de lançar as notas</h4>
        <p>Siga por <strong>Estrutura → Revisão → Concluir</strong>, conferindo disciplinas, datas e responsáveis. É ao concluir o cadastro ou a edição que a seleção dos participantes fica gravada.</p>
        <p class="evg-alerta">Confira a seleção em todas as matérias. Quando há participantes salvos por disciplina, o lançamento usa essa seleção. Sem uma seleção gravada, o evento pode abranger a turma inteira.</p>

        <h4>Onde lançar a nota da recuperação</h4>
        <ol>
            <li>Volte à lista aberta pelo <strong>card de recuperação</strong> e expanda o evento.</li>
            <li>Na linha do <strong>subevento</strong>, abra <strong>Ações → Lançar notas</strong> para uma avaliação offline.</li>
            <li>Selecione a <strong>aba da disciplina</strong> e confira os participantes. Digite a <strong>nota obtida na recuperação</strong>, de 0 a 10 no lançamento direto, e clique em <strong>Salvar notas</strong>.</li>
            <li>Se preferir lançar em lote, use <strong>Importar planilha</strong> e baixe o modelo desse subevento de recuperação. Para corrigir uma nota já salva, use <strong>Desbloquear edição</strong>.</li>
        </ol>
        <p>É a mesma tela de lançamento explicada no bloco anterior. A diferença é que você a abriu pelo subevento da <strong>recuperação</strong>. No online, abra <strong>Gerenciar prova</strong> para continuar o fluxo da prova.</p>
        <p><strong>Como essa nota entra no resultado?</strong> O efeito vem de <strong>Regras de Aprovação</strong>: pode substituir uma nota, compor uma média ou seguir outro critério configurado. Lance a nota da recuperação no evento próprio e confira o resultado no demonstrativo e no boletim.</p>
        <div class="evg-revisao"><p>Confira antes de seguir</p><ul><li>O evento está no ano, na regra e no período corretos?</li><li>Turmas, disciplinas, responsáveis e datas estão conferidos?</li><li>Cada subevento aparece na lista e abre o lançamento ou a prova correspondente?</li><li>Se já lançou ou importou notas, conferiu uma disciplina e um aluno?</li></ul></div>
        <p>O próximo passo é configurar o <strong>Demonstrativo de Notas</strong>, que mostra a conta do período a partir dos eventos.</p>
    </section>
</div>
