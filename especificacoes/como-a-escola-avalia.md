# Como a escola avalia

Manual de cada tela do menu **Como a escola avalia**: o que cada botão faz e para que serve cada campo.

A regra de aprovação é o combinado da escola para aquele ano e aquele curso. Ela diz a média mínima, a frequência, a escala da nota, quem pode ver o documento e se existe recuperação. Evento, demonstrativo e boletim não pedem esses números de novo. Eles apontam para a regra.

A nota entra no **evento avaliativo**. O **demonstrativo** é a conta detalhada do período. O **boletim** é o documento oficial. O **relatório** só imprime o que já foi calculado.

Menu do admin:

| Item | Caminho |
|---|---|
| Regras de Aprovação | `/admin/regras-academicas` |
| Eventos Avaliativos | `/admin/eventos-avaliativos` |
| Demonstrativo de notas | `/admin/boletins` |
| Boletim | `/admin/eventos-avaliativos/boletim` |
| Relatórios | `/admin/reports/boletim-coordenacao` |

O guia sem login, com os prints, fica em `/guia/implantar-academico`.

A divisão do ano (bimestre, trimestre, semestre ou etapa única) nasce no Ano Letivo. Os cards de avaliação da regra e os períodos dos eventos usam essa divisão. Não existe outra tela para cadastrar o 1º trimestre.

---

## 1. Regras de Aprovação

Lista em `/admin/regras-academicas`.

### Botões da lista

| Botão | Para que serve |
|---|---|
| Filtros | Abre o painel Filtrar regras. |
| Nova regra de aprovação | Abre o cadastro de uma regra nova. |
| Ações, em cada linha | Abre editar e as outras ações daquela regra. |

Painel Filtrar regras:

| Campo ou botão | Para que serve |
|---|---|
| Ano letivo | Todos, ou um ano. |
| Ciclo/Etapa/Curso | Todos, ou um curso. |
| Série / Ano Escolar | Todas as Séries / Anos, ou uma série. |
| Fechar | Fecha o painel sem aplicar. |
| Limpar | Tira os filtros e volta à lista completa. |
| Aplicar filtros | Recarrega a lista com o que foi escolhido. |

Colunas da tabela: nome, vigência (ano, curso), média e frequência, como a recuperação está configurada, versão, situação (ativa ou não).

A regra fica versionada por ano. Alterar 2026 não reescreve 2025. Cada ano tem a sua.

### Identificação e vigência

| Campo | Para que serve |
|---|---|
| Regra ativa | Marcado, a regra vale. Desmarcado, ela existe mas não é a regra em uso. |
| Nome | O nome que a coordenação reconhece. Exemplo: RP Ensino Fundamental - Anos Finais. |
| Ano letivo | O ano em que a regra vale. Em branco, vale para qualquer ano. |
| Ciclo/Etapa/Curso | O curso. Em branco, vale para todos. Fundamental e Médio podem ter regras diferentes no mesmo ano. |
| Série / Ano Escolar | As séries. Em branco, vale para todas. |

### Cards de avaliação

Não se cadastram à mão. Eles nascem da divisão do ano letivo. Cada trimestre ou bimestre vira um card com o rótulo Avaliação. Esses cards são os períodos que se abrem em Eventos Avaliativos.

### Critérios de Aprovação

| Campo | Para que serve | O que tem na lista |
|---|---|---|
| Escala de Avaliação | Como a nota é registrada. | Numérica (0 a 10). Numérica (0 a 100). Conceitual (A, B, C, D). Conceitual (A+, A, B, C...). Conceitual (Descritiva). |
| Média Mínima para Aprovação | A nota a partir da qual o aluno passa. Só aparece na escala numérica. Precisa caber na escala: escala de 0 a 10 com nota em outra faixa quebra a média. | Número. Exemplo: 6,00. |
| Conceito mínimo para aprovar | Substitui a média quando a escala é conceitual. Aprova quem ficar neste conceito ou em um melhor. | A, B, C, D na escala ABCD. A+, A, B, C, D na escala com A+. Excelente, Bom, Regular na descritiva. |
| Frequência Mínima Exigida (%) | O percentual de presença exigido. | Número de 0 a 100. Exemplo: 75,0. |
| Regra de Arredondamento | Como a nota é arredondada. | Arredondamento convencional. Faixa .00 / .50 / próximo inteiro, que encaixa a nota em ,00, ,50 ou no inteiro seguinte. |
| Casas Decimais da Nota | Como a nota aparece depois de calculada. | Nenhuma (6). 1 casa (6,5). 2 casas (6,50). |
| Exigir Frequência Mínima para Aprovação | Marcado, o aluno precisa da média e da frequência. Desmarcado, a frequência não entra na aprovação. | Caixa de marcar. |
| Avaliação Baseada Apenas na Frequência | Marcado, aprova só pela presença, sem exigir média. | Caixa de marcar. |

### Demonstrativo e boletim

Esta parte da regra define o documento e quem o vê. O demonstrativo e o boletim não pedem isso de novo.

| Campo | Para que serve |
|---|---|
| Modelo oficial | Matriz regular. Entra no histórico e na vida escolar. |
| Modelo extra | Curso complementar, como música ou robótica. Fica fora do histórico oficial. |
| Disponível para aluno | Marcado, o estudante vê este demonstrativo e este boletim. Desmarcado, não vê. |
| Disponível para famílias | Marcado, pais veem. Desmarcado, não veem. |
| Disciplinas | Áreas oficiais que entram nesta regra. O que ficar desmarcado não aparece no demonstrativo nem no boletim. Os desdobramentos de cada área vêm do cadastro de Componentes Curriculares. Não se cadastram aqui. |

### Recuperação desta regra

A lista guarda as recuperações desta regra. Os cards de cada bimestre ou trimestre já nascem com a regra.

| Botão ou coluna | Para que serve |
|---|---|
| Nova recuperação | Abre o painel de cadastro. |
| Nome | Como a coordenação chama a prova. |
| Quando | Em que momento ela acontece, e o card que ela gera. |
| Quem faz | Quem ficou abaixo da nota, e se quem já passou também pode fazer para aumentar. |
| Nota máxima | O teto da prova de recuperação. |
| Efeito | O que a nota da recuperação faz na média. |
| Lápis | Edita aquela recuperação. |
| Lixeira | Exclui aquela recuperação. |

Painel Nova recuperação / Editar recuperação:

| Campo | Para que serve | O que tem na lista |
|---|---|---|
| Nome | O nome da prova. Exemplo: Rec Trimestral. | Texto. |
| Quando | Em que momento a prova acontece. | A cada Período Letivo: o período ganha o card da avaliação e, ao lado, o card da recuperação. A cada dois ciclos: uma prova vale para um par de períodos. Rec Final: uma prova só, no fim do ano, com card próprio em Eventos Avaliativos. |
| A nota substitui | Só aparece quando Quando é A cada dois ciclos. Diz em qual período do par a nota entra. | Os dois ciclos do par. Cada ciclo do par. Só o primeiro ciclo. Só o último ciclo. |
| O que a nota faz | Como a recuperação entra na média. | Substitui se for maior. Substitui a menor nota. (Média + REC) / 2. Soma na pontuação. Fórmula própria. |
| Fórmula | Só aparece em Fórmula própria. Monta a conta com Média, REC e os sinais. | Blocos arrastáveis. |
| Faz quem ficou abaixo de | O corte. Quem ficou sob essa nota fica de recuperação. | Número. Exemplo: 6,00. |
| Nota máxima da REC | O teto da prova. | Número. Exemplo: 10,00. |
| Quem não ficou de recuperação também pode fazer | Marcado, quem já passou pode fazer se quiser, para aumentar a nota. | Caixa de marcar. |
| Cancelar | Fecha o painel sem gravar. | |
| Salvar / Salvar alterações | Grava a recuperação nesta regra. | |

A nota da recuperação não entra sozinha na média do período. Quem define o efeito é este painel. O fechamento continua dono da substituição na hora de fechar o ano.

### Rodapé da regra

| Botão | Para que serve |
|---|---|
| Cancelar | Volta sem gravar esta versão. |
| Salvar nova versão | Grava uma versão nova da regra. Não reescreve o ano anterior. |
| Histórico de versões | Mostra versão, data e quem salvou. |

---

## 2. Eventos Avaliativos

A lista inicial mostra a regra de nota, o curso e o ano. **Filtros** restringe o ano. **Abrir** entra no mapa daquela regra.

### Mapa do ano

Um card por período (1º trimestre, 2º, 3º, ou bimestres) e, embaixo, os cards de recuperação.

| Borda | Significado |
|---|---|
| Amarela | O período tem evento em rascunho. |
| Verde | O período tem evento publicado. |
| Neutra | O período está vazio. |
| Vermelha | Card de recuperação. Não é uma prova do período. |

Salvar um rascunho publica o evento. Clicar no período abre a lista de eventos daquele período.

### Lista do período

| Botão | Para que serve |
|---|---|
| Períodos | Volta ao mapa. |
| Período | Troca o trimestre ou bimestre sem sair da lista. |
| Busca | Filtra por nome, referência ou sigla. |
| Duplicar de … | Copia os eventos do período anterior. |
| Novo evento | Abre o cadastro. |
| Expandir todos / Recolher todos | Abre ou fecha os subeventos de todos os eventos. |
| Seta do card | Mostra ou esconde os subeventos daquele evento. |

No evento principal (o card mãe), o menu Ações tem:

| Ação | Para que serve |
|---|---|
| Editar | Volta ao cadastro do evento. |
| Publicar | Só aparece em rascunho. Publica o evento. |
| Excluir | Apaga o evento, depois de confirmar. |

O evento principal não lança nota. A nota entra no subevento.

No subevento, o menu Ações tem:

| Ação | Para que serve |
|---|---|
| Lançar notas | Abre o lançamento da coordenação. Em prova online, o rótulo é Gerenciar prova. |
| Ver resultado | Abre o resultado daquele bloco. |

### Cadastro do evento

Três etapas no topo: **Identidade**, **Estrutura**, **Revisão**. O último botão da revisão é **Concluir**. **Voltar** recua uma etapa. **Cancelar** sai sem gravar. **Continuar** avança.

Ano letivo e período já vêm do card que foi aberto. Ficam travados. Não se escolhem de novo aqui.

#### Identidade

| Campo | Para que serve |
|---|---|
| Nome | O nome da prova. Exemplo: Prova Bimestral. |
| Sigla | Apelido curto, até 12 letras. Exemplo: PB ou PS. Aparece na fórmula das notas. |
| Ano letivo | O ano do card. Não edita. |
| Período | O trimestre ou bimestre do card. Não edita. |
| Disponibilizar para o aluno ao lançar a nota | Marcado, o aluno vê cada nota deste evento assim que ela é lançada. A média do período só aparece quando o boletim for gerado. |

#### Turmas

| Campo ou botão | Para que serve |
|---|---|
| Ensino (rádio) | Filtra as turmas daquele curso. Mostra quantas turmas o curso tem. |
| Marcar todas | Marca todas as turmas visíveis. |
| Caixa da turma | Inclui aquela turma no evento. Sem turma, não há quem receber nota. |
| Disponibilizar no calendário letivo | Cada data entra no calendário do curso das turmas marcadas. Curso sem calendário neste ano impede de salvar com esta opção. |
| Quem faz a recuperação | Só no card de recuperação. Cada matéria fica separada. A linha vermelha é a matéria em que o aluno ficou abaixo. Nas outras, marca só quem quiser subir a nota. |
| Puxar alunos abaixo da média | Preenche a lista com quem ficou abaixo do corte da regra. |

#### Estrutura — tipo

| Opção | Para que serve |
|---|---|
| Avaliação online | A prova acontece na plataforma. O aluno faz no sistema. |
| Mostrar este evento no portal do aluno | Só na online. Desmarcado, o aluno não vê em Minhas provas nem acessa por link. A coordenação pode marcar depois. |
| Avaliações offline | Acontece fora da plataforma. A escola informa a nota. |

#### Estrutura — disciplina

| Campo ou botão | Para que serve |
|---|---|
| Adicionar disciplina | Inclui uma matéria no evento. Sem disciplina, não há aba para lançar a nota. |
| Professor | Quem lança, quando a escola atribui ao professor. No painel, professor e disciplina se filtram um pelo outro. |
| Disciplina | A área da nota. |
| Número de questões | Quantas questões a linha tem, quando a prova pede. |
| Marcar todas | Marca as turmas daquela linha de disciplina. |
| Remover | Tira a disciplina do evento. |
| Concluir, no painel da disciplina | Fecha o painel e guarda a linha. |

#### Estrutura — quem lança

Offline:

| Opção | Para que serve |
|---|---|
| Atribuir ao professor | O professor lança a nota. |
| Escola lança a nota | A coordenação lança. O professor é opcional. |
| Prazo para o professor lançar as notas | Data e hora limite. Depois do prazo, o que não foi enviado fica como Não Enviado e trava. |

Online:

| Opção | Para que serve |
|---|---|
| Atribuir professor | O professor monta ou conduz a prova. |
| Coordenação faz a prova | A coordenação conduz a prova na plataforma. |

#### Estrutura — periodicidade

| Opção | Para que serve |
|---|---|
| Evento único | Uma vez no período. Exemplo: prova bimestral, trabalho. |
| Eventos múltiplos | Várias vezes no período. Exemplo: lista, recitais, registros. |
| Quantidade de subeventos | Só nos múltiplos. De 1 a 20. Cada um vira uma linha com a própria data. |
| Data da avaliação | Abre o calendário. Clique no dia. |
| Horário de início e término | A janela da prova. Na online, a janela de realização usa a data e o horário do bloco. |
| Nome do subevento | O nome daquela peça. Exemplo: S1, S2. |
| Só esta data | A data vale para todas as turmas daquele subevento. |
| Ajustar | Abre horário diferente por série ou turma. |
| Remover, na agenda | Tira aquela data extra. |
| Adicionar, na agenda | Inclui outra data ou outro horário. |
| Mês anterior / Próximo mês | Navega o calendário do campo de data. |
| Concluir, no calendário | Fecha o calendário e preenche a data. |

00:00–23:59 no calendário letivo aparece como Dia todo.

#### Estrutura — como a nota é calculada

As provas deste evento viram uma nota só. O boletim mostra essa nota, sem somar as provas de novo.

| Campo | Para que serve | O que tem na lista |
|---|---|---|
| O que cada evento registra | Só em eventos múltiplos. O que cada peça guarda. | Nota 0–10 (ou escala). Acertos e total de questões. |
| Tipo de nota do evento avaliativo | Como várias notas viram uma. | Última nota lançada. Maior nota. Média das notas. Soma (depois limita na escala). Aproveitamento: (soma acertos ÷ soma questões) × escala. |
| Mesma nota em todas as matérias do evento | Marcado, a nota lançada em uma matéria vale para as outras do mesmo evento. | Caixa de marcar. |
| Escala (Nota Máxima) | O teto da nota deste evento. | Número de 1 a 100. |
| Professores da mesma disciplina | Quando dois professores dividem a frente. | Não junta: cada lançamento entra no critério acima. Média das notas dos professores (4 e 6 viram 5). Soma das notas dos professores (4 e 6 viram 10, limitada à escala). |

#### Revisão

Mostra o resumo antes de gravar. **Concluir** salva. Um rascunho, ao salvar, sai do rascunho e fica publicado.

### Lançar notas

Uma aba por disciplina. A primeira já vem selecionada. A nota é de 0 a 10, salvo a escala do evento.

| Botão | Para que serve |
|---|---|
| Filtros | Abre série, turma, ordenação e quantidade por página. |
| Histórico | Abre o que já foi alterado naquela nota. |
| Importar planilha | Abre o painel de CSV ou Excel. |
| Desbloquear edição | Pede a senha de acesso. Nota já salva fica bloqueada até desbloquear. |
| Salvar notas | Grava o que foi digitado. |
| Voltar | Sai do lançamento. |
| Cancelar | Descarta o que ainda não foi salvo. |

Filtros do lançamento:

| Campo | Opções |
|---|---|
| Série | Todas as séries, ou uma série do evento. |
| Turma | Todas as turmas, ou uma turma. |
| Ordenar por | Nome (A–Z), nome (Z–A), número da chamada, chamada decrescente, sexo. |
| Por página | 20, 40, 60 ou 100. |
| Limpar | Tira os filtros. |
| Aplicar | Aplica e recarrega a lista. |

Colunas da grade: turma, número, código do aluno, aluno, nota e observação.

Importar planilha:

| Campo ou botão | Para que serve |
|---|---|
| Baixar modelo preenchido com os alunos | Baixa a planilha já com código, disciplina e turma. A coluna nota vem vazia para preencher. |
| Arquivo | O CSV ou Excel (.xlsx). Colunas obrigatórias: codigo, disciplina, nota. O código é o código do aluno ou o RA. Célula vazia é ignorada. Código ou disciplina que não existem são pulados. |
| Substituir notas já lançadas | Só vale depois de desbloquear a edição com a senha. Sem isso, a nota que já está na tela permanece. |
| Importar | Lê o arquivo e grava. |
| Fechar | Fecha o painel. |

Um código numérico com decimal de zeros (100,0 ou 100.0) vira 100. 100, 10 e 1000 continuam códigos diferentes.

---

## 3. Demonstrativo de notas

`/admin/boletins`. É a conta detalhada do período. Não é o documento oficial da vida escolar.

| Botão | Para que serve |
|---|---|
| Novo modelo | Abre o cadastro. |
| Períodos do boletim, no menu da linha | Abre os períodos e as fórmulas daquele demonstrativo. |
| Ações | Editar, abrir períodos ou excluir. Excluir pede a senha da conta. |

No cadastro atual, o demonstrativo só vincula uma regra de aprovação. Modelo oficial ou extra, aluno, famílias, ativo e disciplinas saem da regra. Não se marcam de novo aqui.

| Campo | Para que serve |
|---|---|
| Regra de aprovação | A regra que esta conta usa. Sem regra cadastrada, o demonstrativo não abre. |

### Como montar o demonstrativo e a fórmula

A fórmula do período não se escreve na regra nem no boletim. Ela se monta em **Configurar notas**, um período de cada vez. A média do ano, que junta os períodos, fica no layout do boletim.

Antes disso, a regra precisa estar ativa e o evento do período precisa estar publicado, com a nota lançada no subevento. Sem nota lançada, a coluna do demonstrativo fica vazia.

1. Em Demonstrativo de notas, clique em **Novo modelo**.
2. Escolha a regra de aprovação e salve. Oficial ou extra, quem vê e quais disciplinas já vêm da regra.
3. Na linha desse modelo, abra **Ações** e clique em **Períodos do boletim**.
4. Abra o período (1º trimestre, 2º, ou o bimestre). A frase da tela é: as fórmulas ficam em Configurar Notas. Depois gere o período em lote.
5. Clique em **Configurar notas**. Abrir essa tela não grava. O caminho no topo é Boletins / Períodos e fórmulas / Evento / Configurar notas.
6. Percorra as seis etapas abaixo. A conta em si é a etapa 5.
7. Na revisão, **Salvar sem gerar notas** só guarda a fórmula. **Concluir e aplicar** guarda e calcula as notas de todos os alunos daquele evento.
8. Volte ao período. **Simular boletim** confere um aluno sem publicar a turma. **Gerar boletins** calcula o período em lote. Aluno travado não entra. O histórico fica guardado.
9. **Adicionar períodos** (Gerar avaliações do ano) copia essa configuração para os outros períodos do ano. Marque os períodos e clique em **Criar períodos selecionados**. Período que já tem configuração vigente pede substituição: a vigente sai e o histórico deixa de ficar vigente. Esta etapa só cria a configuração. O boletim do aluno é gerado depois, com as notas lançadas.

Na página do período também existem **Ver resultados e versões**, **Voltar ao modelo** e **Voltar aos períodos**.

#### Exemplo de fórmula do período

A escola tem, no 1º trimestre, Prova Semanal com três subeventos (S1, S2, S3) e Prova Bimestral (PB). A média do trimestre é a soma das quatro notas, dividida por 4, no teto 10.

1. Em Começar, escolha **Do zero**.
2. Em Identidade, dê o nome (1º trimestre), confira ano e período, e escolha o boletim oficial. A média mínima aparece da regra.
3. Em Tipo de Notas, marque Prova Semanal e Prova Bimestral. Em Prova Semanal, deixe S1, S2 e S3 marcados. Desmarcar um subevento tira aquela nota da conta.
4. Em Agrupar, deixe como está se cada disciplina continua na própria linha. Marque **Linha única no boletim** só se Gramática, Interpretação e Literatura devem aparecer como uma linha só, Língua Portuguesa.
5. Em Fórmula, as colunas S1, S2, S3 e PB já aparecem.
6. Para agrupar as semanais debaixo de um título: clique em S1, S2 e S3 (a borda fica azul), escreva Prova Semanal no nome da linha e clique em **Criar linha**. Isso só organiza a aparência. A fórmula não muda.
7. Clique em **Adicionar bloco de cálculo**.
8. Título da coluna: Média. Valor máximo: 10, ou o atalho 10. Esse teto corta o resultado. Se a conta for nota vezes 2, o teto precisa ser 20, senão o 20 vira 10.
9. Em Usar no cálculo, clique em S1, depois em +, S2, +, S3, +, PB. Em Sinais, clique em ( antes do S1 e em ) depois do PB. Depois clique em ÷ e, em Número, digite 4 e **Inserir**. A fórmula fica `(S1 + S2 + S3 + PB) ÷ 4`.
10. O botão **Média das peças** monta essa mesma conta sozinho: soma entre parênteses e divide pela quantidade de peças. Use-o quando todas as peças pesam igual.
11. **Maior entre as duas primeiras** monta `maior(primeira, segunda)`. Serve quando a recuperação substitui a média se for maior. A vírgula separa os dois valores dentro de maior ( e menor (.
12. Clique em **Salvar bloco**. A tela mostra Salvo. Sem esse clique, a coluna Média não existe.
13. **Salvar e continuar** vai para a revisão.
14. Em Simular notas com aluno, busque um aluno que já tem nota lançada. A coluna vazia significa que aquele subevento ainda não foi lançado. **Sem aluno** volta aos números de exemplo.
15. Confira as duas vistas: **Demonstrativo de Notas** (o quadro com as semanas) e **Boletim** (o documento).
16. Se uma matéria não tem semanal, em Matéria escolha essa disciplina e clique em **Usar outra fórmula nesta matéria**. Monte só o que ela tem, por exemplo `(PB) ÷ 1`. **Voltar à fórmula geral** retorna à conta das outras. **Remover** apaga a exceção.
17. Sem parêntese, `S1 + S2 + S3 + PB ÷ 4` divide só a PB. A média sai errada.
18. Na revisão, **Salvar sem gerar notas** guarda a fórmula e não publica. **Concluir e aplicar** publica para todos. **Gerar só este aluno** publica só o aluno que está no simulador.

O × no bloco tira um pedaço da fórmula. **Limpar** esvazia o bloco. Arrastar a coluna muda a ordem na tela e não muda a conta.

Essa fórmula é a do período. A média do ano, que junta 1º, 2º e 3º, se monta no layout do boletim, na etapa Fórmula, com as médias dos períodos. Em branco, o boletim usa a média simples dos períodos.

### Períodos e fórmulas

Na linha do modelo, **Períodos do boletim** abre a lista dos períodos do ano. Cada período é um evento de notas. Abrir a tela não grava nada. O caminho no topo é Boletins / Períodos e fórmulas / Evento / Configurar notas.

Quem vê o demonstrativo foi marcado na regra, em Disponível para aluno e Disponível para famílias.

A coluna lê a nota lançada no evento avaliativo. Sem lançamento, a coluna fica vazia. O demonstrativo não inventa média.

A fórmula precisa de parênteses na divisão. `(Prova + Trabalho + Participação) ÷ 3` divide a soma. Sem o parêntese, só o último número é dividido e a média sai errada.

#### Botões que ficam em todas as etapas

| Botão | Para que serve |
|---|---|
| Voltar | Recua uma etapa. Na primeira, fica desabilitado. |
| Continuar | Avança. Na etapa Fórmula o rótulo vira Salvar e continuar. Na revisão vira Concluir e aplicar. |
| Aplicar na configuração | Grava o que já foi montado sem sair da etapa. Na revisão o rótulo vira Salvar sem gerar notas. |
| Gerar só este aluno | Só aparece na revisão, quando há um aluno escolhido no demonstrativo. Grava e publica notas e boletim oficial só dele. O restante da turma fica com as notas vigentes. |
| Copiar receita | Copia a montagem para reaproveitar. |
| Fechar | Sai do assistente. |
| Concluir e aplicar | Grava a configuração e inicia a geração das notas de todos os alunos do evento. O boletim oficial só muda quando essa geração terminar. Se ela não concluir, a configuração salva permanece e a geração pode ser retomada na página do evento. |
| Salvar sem gerar notas | Grava a versão da configuração. O detalhe do aluno e o boletim oficial continuam com as notas que já estão vigentes. |

As seis etapas, na ordem da tela: Começar, Identidade, Tipo de Notas, Agrupar, Fórmula, Revisão.

#### 1. Começar

Pergunta como a escola quer começar.

| Opção | Para que serve |
|---|---|
| Do zero | A escola escolhe as peças e como cada uma entra na média. |
| Modelo pronto | Começa com uma combinação comum da escola. Abre a lista de modelos, cada um com título e descrição. |
| Clonar outro evento | Copia a regra de um bimestre ou evento já existente e só ajusta o período. |

Em Clonar outro evento:

| Campo | Para que serve |
|---|---|
| Evento de origem | A lista dos eventos já salvos. Cada opção mostra nome, período e ano. |
| Novo período | O número do período de destino, de 1 até o máximo do ano. |
| Ano letivo | O ano do evento copiado. |

No modo clonar, a ordem e as fórmulas do evento de origem são mantidas. A etapa Fórmula só pede para avançar.

#### 2. Identidade

| Campo | Para que serve |
|---|---|
| Nome do evento | O nome desta conta do período. |
| Ano letivo | O ano. |
| Período | O trimestre, bimestre ou etapa. O rótulo acompanha a divisão do ano. No boletim composto, o campo mostra o ano todo, do 1º ao último, sem select. |
| Boletim | O documento oficial em que esta conta entra. Cada opção mostra nome, Oficial ou Extra, e o ano. Nenhum deixa o evento fora do boletim oficial. Sem boletim cadastrado, a mínima sugerida fica 6 e o público é o que for marcado nesta identidade. |
| Quadro de Notas | Opcional. Só aparece quando o módulo de eventos avaliativos não está conduzindo a nota. Liga este evento a um quadro já cadastrado. |
| Média mínima | Vem da regra do boletim escolhido. A tela mostra a regra e o valor. |

#### 3. Tipo de Notas

Com eventos avaliativos ligados, a pergunta é quais eventos do período da identidade entram neste boletim.

| Controle | Para que serve |
|---|---|
| Caixa do evento | Inclui aquele evento. Mostra o nome, a quantidade de subeventos e as turmas. |
| Sub-eventos que entram na nota | Cada subevento, com a data. Desmarcado, a nota daquela peça não entra. As notas lançadas nos marcados entram neste boletim. |
| Jornada | Inclui a jornada como peça, quando o catálogo tem essa peça. |

Com a jornada marcada:

| Campo | Para que serve | Opções |
|---|---|---|
| Jornadas vinculadas | Mostra quantas jornadas o período tem. | Texto. |
| Jornadas que entram | Desmarque a que não deve contar. | Uma caixa por jornada, com nome, matéria e período. |
| Como lançar a nota | De onde sai a nota da jornada. | Por matéria: usa a jornada de cada disciplina. Média única: calcula o percentual geral de conclusão e repete a mesma nota nas matérias marcadas, mesmo nas que não tiveram jornada. |
| Matérias que recebem a nota | Só na média única. | Marcar todas. Desmarcar todas. Uma caixa por matéria. |
| Pontuação por conclusão | Como o percentual vira nota. | Linear (100% = 10). Tabela por faixas: cada faixa tem o percentual mínimo e o campo da nota. |

Sem o módulo de eventos avaliativos, a mesma etapa lista as peças do catálogo (prova semanal, prova bimestral, trabalho, jornada). Marcar a peça abre o período dela e os eventos que entram. O fechamento da peça vem do tipo de nota. Para mudar, edita em Avaliações → Tipos de nota.

Papel de cada peça, quando a tela pede:

| Papel | Para que serve |
|---|---|
| Entra na média | Compõe a nota base com as outras peças. |
| Entra depois (média final) | Entra numa segunda média, depois da média parcial. |
| Só melhora | Se for menor que a média, a média permanece. |
| Substitui se for maior | Usa esta nota quando ela for maior que a média. É o papel da recuperação. |
| Só mostra | Aparece no boletim e não entra no cálculo. |

#### 4. Agrupar

O grupo, pai e desdobramentos, já vem de Componentes Curriculares. O padrão vale para todas as peças. Em Ajuste por peça dá para a semanal e a jornada fazerem média e a prova bimestral somar.

Com eventos avaliativos, a frase **Mesma disciplina, professores diferentes** só informa o que o evento já definiu: média, soma, ou não junta.

Sem eventos avaliativos:

| Campo | Para que serve |
|---|---|
| Juntar matérias iguais | Mesma matéria, professores diferentes (dois Português). Não junta Português com Literatura. |
| Como juntar as notas dos professores | Somar: cada professor contribui (3+4+3 = 10). Média: 10+10+10 ÷ 3 = 10. Independente do tipo de nota. |
| Linha única no boletim | Junta os desdobramentos numa linha só. Exemplo: Gramática + Interpretação + Literatura viram Língua Portuguesa. |
| Exibir só a área, sem as matérias filhas | Mostra só a linha-mãe no detalhe do aluno, no PDF, nas notas geradas e no acesso de pais e alunos. |
| Área | As áreas já cadastradas em Componentes, com as filhas listadas. Uma área por vez. |
| Matérias nesta área | Altera só este evento de notas, este período. Os outros períodos não mudam. |
| Como juntar as notas dessas matérias | Média ou soma. É o padrão das peças que não tiverem ajuste próprio. |
| Ajuste por peça | Média ou soma para cada peça. Prova semanal inclui as semanas e a média semanal. |
| Arredondamento da área | Usa o arredondamento do evento (faixa ,00 / ,50). Todos: filhas e linha-mãe. Só filhas: a mãe fica com a média ou soma exata. Só mãe: só a linha-mãe arredonda. |
| Onde essa linha única aparece | Só no boletim: nas notas, as matérias continuam em linhas separadas. No boletim e nas notas: junta nos dois lugares. Só quando o evento é de notas. |
| Nome da linha | Avançado, dentro de um detalhe fechado. Use se o rótulo for diferente do nome da área em Componentes. |

#### 5. Fórmula

Em cima, a lista **Eventos usados por peça** resume o que foi marcado.

| Botão ou campo | Para que serve |
|---|---|
| Adicionar bloco de cálculo | Cria uma coluna calculada, como a média do período. |
| Ordem das colunas | Arraste para mudar a ordem. O × tira a coluna da linha. A fórmula não muda. |
| Criar linha | Com eventos avaliativos: clique em pelo menos duas colunas (a borda fica azul), escreva o nome e crie a faixa. O nome sobe para cima delas. |
| Tirar da linha | Devolve as colunas marcadas para fora da faixa. |
| Título da coluna | O nome do bloco. Exemplo: Média Bim. Até 60 caracteres. |
| Valor máximo | O teto do resultado. Atalhos 10, 20 e 100. Em Resultado Final × 2, use 20. Sem isso, o teto fica 10 e corta a nota. |
| Salvar bloco | Grava o bloco. A tela confirma com Salvo. |
| Usar no cálculo | As peças marcadas, como blocos arrastáveis. |
| Sinais | +, −, ×, ÷, parênteses, vírgula, maior ( e menor (. |
| Número e Inserir | Coloca um número fixo na fórmula. |
| Média das peças | Monta sozinha a soma entre parênteses dividida pela quantidade. |
| Maior entre as duas primeiras | Monta a função maior entre as duas primeiras peças. |
| Limpar | Esvazia a fórmula do bloco. |
| × no bloco | Tira aquele pedaço da fórmula. |
| Matéria | A matéria que terá uma conta diferente. |
| Usar outra fórmula nesta matéria | Abre a exceção. A fórmula geral continua nas outras. Editar volta à conta daquela matéria. Remover apaga a exceção. |
| Voltar à fórmula geral | Sai da exceção e volta à conta do bloco. |
| Simular notas com aluno | Busca um aluno e usa as notas lançadas no exemplo. |
| Sem aluno | Tira o aluno e volta aos números de exemplo. |
| Demonstrativo de Notas | Mostra o quadro com blocos e semanas. |
| Boletim | Mostra o documento oficial de exemplo. |

#### 6. Revisão

Confere o demonstrativo, com blocos e semanas, ou o exemplo do boletim. Escolha um aluno para usar as notas lançadas. Para mudar ordem ou fórmula, volte em Fórmula.

Sem boletim vinculado, os números são exemplo e o público ainda não é o de uma turma específica.

No boletim composto, o exemplo mostra 1º ao último período (Média e Faltas) e a faixa final (Média, Rec., Faltas, Resultado), com dados fictícios.

A caixa explica de novo Salvar sem gerar notas, Concluir e aplicar e Gerar só este aluno.

Versões da configuração lista cada versão salva, com identificador, número, data e quem salvou. **Recuperar** pede confirmação e traz aquela versão. A configuração atual continua no histórico.

Quando a tela foi aberta a partir de adicionar períodos, o link **Voltar e configurar os demais períodos** retorna para os outros períodos do ano.

---

## 4. Boletim

`/admin/eventos-avaliativos/boletim`. É o documento oficial. O demonstrativo continua ao lado, com a conta do período.

| Botão | Para que serve |
|---|---|
| Novo boletim | Abre o cadastro. |
| Editar o boletim | Troca a regra vinculada. |
| Layout do boletim | Abre as três etapas do desenho. |
| Excluir | Apaga o boletim, no menu de ações. |

Cada turma do ano usa um documento. Dois boletins oficiais para a mesma turma no mesmo ano se atropelam.

### Cadastro

| Campo | Para que serve |
|---|---|
| Regra de aprovação | A regra deste boletim. Modelo, público, ativo e disciplinas saem dela. O desenho das colunas fica no layout. |
| Voltar | Volta à lista, sem gravar. |
| Cadastrar / Salvar | Grava o vínculo. |

### Layout

Três etapas: **Linhas**, **Fórmula**, **Revisão**.

| Botão | Para que serve |
|---|---|
| Voltar | Recua uma etapa. Na primeira, fica desabilitado. |
| Continuar | Avança. |
| Salvar layout | Na revisão, grava o desenho. |

#### Linhas

Cada faixa é uma linha do quadro: 1º trimestre, 2º, 3º e Final, ou os bimestres do ano.

| Controle | Para que serve |
|---|---|
| Nome da faixa | O rótulo da linha. Exemplo: 1º Trimestre, Final. |
| Nova linha | Inclui outra faixa. |
| Média, Faltas, Recuperação, Situação | Clique coloca a coluna na faixa marcada. |
| × na coluna | Devolve a coluna para a lista. |
| Remover faixa | Apaga aquela linha. |

Faixa sem a média daquele período imprime traço.

#### Fórmula

Monta a média final arrastando as médias dos períodos e os sinais. Em branco, o sistema usa a média simples dos períodos. Use parênteses na divisão, do mesmo jeito que no demonstrativo.

| Botão | Para que serve |
|---|---|
| Colocar número | Inclui um número fixo na fórmula. |
| Limpar | Esvazia a fórmula. |

#### Revisão

O quadro mostra números de exemplo. A faixa de cima é a linha que foi nomeada. Para mudar ordem ou fórmula, volta no passo. Confira se cada período tem a coluna de média e se a faixa Final está completa. Depois, Salvar layout.

A ficha do aluno, o portal e o relatório do boletim leem este layout. Sem ele, o documento oficial não tem onde se desenhar.

---

## 5. Relatório

`/admin/reports/boletim-coordenacao`. É a mesma área, na aba Relatórios. Esta tela não cadastra nota. Ela imprime o que o evento, o demonstrativo e o boletim já calcularam.

| Campo | Para que serve | O que tem |
|---|---|---|
| Exibir | Qual documento imprimir. | Boletim, o oficial. Demonstrativo de notas, a conta detalhada do período, na versão vigente. |
| Ano letivo | O ano do documento. | Os anos cadastrados. |
| Período | O trimestre, bimestre ou o ano inteiro. O rótulo acompanha a divisão do ano. | Todos. Cada período do ano. |
| Curso/Ciclo | Restringe o curso. | Todos, ou um curso. |
| Turma | Restringe a turma. A lista respeita o curso escolhido. | Todas as turmas, ou uma turma. |
| Aluno | Um aluno. Aceita nome, RA ou código, com sugestões. | Texto. |
| Nota abaixo de | Só quem ficou sob o corte. | Número. Exemplo: 7 ou 6,5. |
| Matérias | Quais colunas saem. | Todas as matérias. Só as que estão abaixo do corte. |
| Incluir campo de assinatura ao lado do nome | Reserva um espaço de assinatura na entrega. | Caixa de marcar. |
| Limpar | Tira os filtros. | |
| Gerar relatório | Monta o documento com o que foi escolhido. | |

Período sem nota lançada sai em branco. O relatório não inventa média. Turma sem layout de boletim não tem documento oficial para emitir. Sem versão vigente naquele ano e período, a tela avisa.

Quando a escola inteira entra no arquivo, o ZIP é gerado em segundo plano: um PDF por aluno, depois o pacote. A página precisa ficar aberta.

---

## O que conferir antes de entregar

1. A regra do ano e do curso está ativa, com a escala certa e as disciplinas marcadas.
2. O evento do período está publicado, com disciplina, e a nota foi lançada no subevento.
3. A fórmula do demonstrativo tem parênteses na divisão.
4. O layout do boletim tem a média de cada período e a faixa final.
5. O relatório foi gerado uma vez para um aluno conhecido, no boletim e no demonstrativo.
