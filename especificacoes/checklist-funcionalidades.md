# Checklist de Funcionalidades do EducaTudo

## Descricao curta

O **EducaTudo** e um ecossistema educacional SaaS multi-tenant para escolas brasileiras, unindo gestao escolar, conteudo pedagogico, comunicacao, app dos pais e inteligencia artificial no dia a dia da sala de aula.

Cada escola tem banco de dados, identidade visual, dominio, modulos e limites proprios, com recursos como Tudinha, provas online, jornadas de aprendizagem, redacao ENEM, TudiCoins e painel Master para operacao do SaaS.

---

## Legenda

| Sigla | Perfil | Papel tipico |
|---|---|---|
| **Aluno** | Aluno | Usa / consome |
| **Professor** | Professor | Cria / corrige / acompanha turma |
| **Coordenacao** | Coordenador / Admin pedagogico | Gere, relatorios, regras |
| **Secretaria** | Secretaria | Cadastros, matriculas, documentos |
| **Diretor** | Diretor / Admin geral | Visao ampla da escola |
| **Financeiro** | Financeiro | Mensalidades, pagantes, creditos |
| **Monitor** | Monitor de sala | Supervisao em tempo real (leitura) |
| **Pais** | Pais / Responsavel | Acompanhamento (somente leitura) |
| **Dev** | Desenvolvedor / Admin tecnico | Configuracoes avancadas |
| **Master** | Operacao SaaS EducaTudo | Escolas, planos, migrations |

Em cada item:
- **Principal** = quem mais usa no dia a dia
- **Tambem** = outros perfis com acesso relevante
- Itens sem modulo no EducaTudo ficam marcados como **(fora da plataforma / operacional da escola)**

---

## Indice por departamento

1. [Departamento Pedagogico](#1-departamento-pedagogico)
2. [Departamento de Secretaria e Registro Academico](#2-departamento-de-secretaria-e-registro-academico)
3. [Departamento Administrativo e Financeiro](#3-departamento-administrativo-e-financeiro)
4. [Departamento de Operacoes e Infraestrutura](#4-departamento-de-operacoes-e-infraestrutura)
5. [Departamento de Tecnologia da Informacao (TI)](#5-departamento-de-tecnologia-da-informacao-ti)
6. [Departamento de Apoio ao Estudante e Servicos Gerais](#6-departamento-de-apoio-ao-estudante-e-servicos-gerais)
7. [Departamento de Comunicacao e Marketing](#7-departamento-de-comunicacao-e-marketing)
8. [Perfis de acesso na plataforma](#8-perfis-de-acesso-na-plataforma)

---

## 1. Departamento Pedagogico

> Coracao da escola: ensino, aprendizagem e suporte aos alunos.

### 1.1 Direcao Pedagogica

> Define diretrizes educacionais e metas da instituicao.

- [ ] **Plano de curso**
  - **Principal:** Coordenacao · **Tambem:** Diretor, Professor
  - Organiza conteudos, objetivos e estrutura curricular.
  - Serve como base para acompanhamento pedagogico e planejamento da escola.

- [ ] **BNCC / alinhamento curricular**
  - **Principal:** Coordenacao · **Tambem:** Diretor
  - Apoio ao alinhamento com a Base Nacional Comum Curricular.
  - Orienta planejamento pedagogico e organizacao de conteudos.

- [ ] **Relatorios pedagogicos**
  - **Principal:** Coordenacao · **Tambem:** Diretor, Professor
  - Relatorios de desempenho, uso e acompanhamento institucional.
  - Apoiam metas, reunioes e tomada de decisao.

- [ ] **Configuracao de boletim e regras de media**
  - **Principal:** Coordenacao · **Tambem:** Diretor
  - Define como notas e medias sao compostas.
  - Padroniza a avaliacao da escola.

### 1.2 Coordenacao Pedagogica

> Orienta professores e acompanha o cumprimento do curriculo.

- [ ] **Ano letivo**
  - **Principal:** Coordenacao · **Tambem:** Secretaria, Diretor
  - Configuracao do periodo escolar.
  - Base para turmas, calendarios, avaliacoes e relatorios.

- [ ] **Cursos, series e turmas**
  - **Principal:** Coordenacao · **Tambem:** Secretaria
  - Estrutura academica da escola (curso → serie → turma).
  - Base para provas, jornadas, comunicados e frequencia.

- [ ] **Materias**
  - **Principal:** Coordenacao · **Tambem:** Secretaria
  - Cadastro e organizacao das disciplinas.
  - Relaciona professores, turmas, avaliacoes e boletim.

- [ ] **Grade horaria**
  - **Principal:** Coordenacao · **Tambem:** Secretaria, Professor
  - Organizacao de horarios, aulas, professores e turmas.
  - Pode ser importada ou auxiliada por leitura de imagem com IA.

- [ ] **Calendario letivo**
  - **Principal:** Coordenacao · **Tambem:** Secretaria, Diretor
  - Organizacao academica do ano letivo.
  - Ajuda no planejamento institucional e acompanhamento do periodo.

- [ ] **Planos de aula**
  - **Principal:** Professor · **Tambem:** Coordenacao, Aluno, Pais
  - Professor registra objetivos, conteudos, recursos e planejamento.
  - Coordenacao consulta e acompanha o cumprimento do plano.

- [ ] **Jornada do Aluno**
  - **Principal:** Professor · **Tambem:** Aluno, Coordenacao
  - Trilhas de aprendizagem com videos, documentos, exercicios, resumos e redacoes.
  - Professor cria; aluno avanca; coordenacao acompanha relatorios.

- [ ] **Apostilas e materiais**
  - **Principal:** Professor · **Tambem:** Aluno, Coordenacao
  - Materiais didaticos, arquivos e conteudos de apoio.
  - Podem ser manuais ou gerados com apoio de IA.

- [ ] **Apostilas com IA**
  - **Principal:** Professor · **Tambem:** Aluno, Coordenacao
  - Gera material didatico personalizado.
  - Transforma temas e referencias em material estruturado.

- [ ] **Minicursos / AVA**
  - **Principal:** Coordenacao / Admin · **Tambem:** Aluno, Professor
  - Cursos complementares, aulas, avaliacoes e certificados.
  - Apoia reforco, eletivas e conteudos institucionais.

- [ ] **Gerador de slides**
  - **Principal:** Professor
  - Cria apresentacoes a partir de tema ou planejamento.
  - Apoia preparacao de aulas.

- [ ] **TudinhaProf / Agentes de IA**
  - **Principal:** Professor
  - Professor cria agentes personalizados com materiais proprios.
  - Apoio didatico e automacoes pedagogicas.

### 1.3 Orientacao Educacional

> Apoio emocional, social e de carreira aos estudantes.

- [ ] **Ocorrencias disciplinares**
  - **Principal:** Coordenacao · **Tambem:** Secretaria, Professor
  - Registro de ocorrencias por aluno.
  - Pode usar transcicao de audio por IA para agilizar registros.

- [ ] **Saude academica**
  - **Principal:** Coordenacao · **Tambem:** Diretor
  - Indicadores consolidados de risco e desempenho.
  - Ajuda a identificar alunos que precisam de acompanhamento.

- [ ] **Alertas sensiveis (Tudinha)**
  - **Principal:** Coordenacao · **Tambem:** Diretor
  - Detecta sinais de risco nas interacoes com IA.
  - Envia alertas para a area responsavel.

- [ ] **Acompanhamento do aluno (abas / detalhe)**
  - **Principal:** Coordenacao · **Tambem:** Secretaria, Professor
  - Visao consolidada de notas, provas, jornadas, redacoes e ocorrencias.
  - Apoia orientacao e intervencoes pedagogicas.

### 1.4 Supervisao de Ensino

> Avalia qualidade das aulas e desempenho do corpo docente.

- [ ] **Diario de classe**
  - **Principal:** Professor · **Tambem:** Coordenacao
  - Registro de rotina pedagogica, aulas, conteudos e observacoes.
  - Permite supervisao do que foi trabalhado em sala.

- [ ] **Monitoramento de alunos online**
  - **Principal:** Monitor · **Tambem:** Coordenacao
  - Presenca em tempo real durante aula ou avaliacao.
  - Apoia supervisao de uso da plataforma.

- [ ] **Frequencia e faltas**
  - **Principal:** Professor · **Tambem:** Coordenacao, Secretaria, Pais
  - Controle de presenca por aluno, turma e materia.
  - Alimenta boletim, relatorios e acompanhamento familiar.

- [ ] **Cadastro e vinculo de professores**
  - **Principal:** Coordenacao · **Tambem:** Diretor
  - Gestao de professores, turmas e materias.
  - Base para supervisao do corpo docente.

### 1.5 Inclusao e Acessibilidade

> Adapta conteudos para alunos com necessidades especiais.

- [ ] **EducaInclui**
  - **Principal:** Coordenacao / AEE · **Tambem:** Professor
  - Mascaras de acessibilidade, laudos e versoes adaptadas de provas.
  - Tempo extra, fonte, linguagem simplificada e outras adaptacoes.

### 1.6 Avaliacoes e aprendizagem do aluno

> Nucleo de ensino e aprendizagem na plataforma.

- [ ] **Provas online**
  - **Principal:** Professor · **Tambem:** Aluno, Coordenacao
  - Multipla escolha, V/F, dissertativa, cronometro e modo anti-cola.
  - Correcao manual ou automatizada; resultados consolidados.

- [ ] **Banco de questoes**
  - **Principal:** Professor · **Tambem:** Coordenacao
  - Repositorio reutilizavel de questoes da escola.
  - Usado em provas, exercicios e geracao com IA.

- [ ] **Jornada da Redacao**
  - **Principal:** Professor · **Tambem:** Aluno, Coordenacao
  - Propostas com criterios ENEM, textos motivadores e correcao.
  - Correcao por IA, professor ou modelo hibrido.

- [ ] **Redacao livre**
  - **Principal:** Aluno · **Tambem:** Professor, Coordenacao
  - Tema livre ou gerado com IA.
  - Feedback por competencias e historico de desempenho.

- [ ] **Redacao manuscrita**
  - **Principal:** Aluno · **Tambem:** Professor
  - Foto da redacao → OCR → texto → correcao.
  - Reduz trabalho manual do professor.

- [ ] **Exercicios**
  - **Principal:** Aluno · **Tambem:** Professor, Coordenacao
  - Listas por materia, assunto e dificuldade.
  - Feedback e gabarito comentado.

- [ ] **Exercicios personalizados com IA**
  - **Principal:** Aluno · **Tambem:** Professor
  - Aluno escolhe tema, quantidade e dificuldade.
  - Geracao, correcao e explicacao automaticas.

- [ ] **Simulados**
  - **Principal:** Aluno · **Tambem:** Professor, Coordenacao
  - Provas preparatorias formato ENEM ou modelo da escola.
  - Acompanhamento de desempenho.

- [ ] **Flashcards**
  - **Principal:** Aluno
  - Decks gerados por IA para revisao.
  - Explicacoes detalhadas sob demanda.

- [ ] **Notas e boletim (lancamento / consulta)**
  - **Principal:** Professor / Coordenacao · **Tambem:** Aluno, Pais
  - Lancamento, consolidacao e visualizacao de notas.
  - Consulta por aluno, materia e periodo.

- [ ] **Chat Tudinha**
  - **Principal:** Aluno · **Tambem:** Professor
  - Tutora de IA para duvidas em linguagem natural.
  - Contexto, imagem/OCR e resposta por voz quando habilitado.

- [ ] **Aulas online**
  - **Principal:** Aluno · **Tambem:** Professor, Coordenacao
  - Aulas ao vivo com videoconferencia e gravacao.
  - Integra o AVA e a rotina pedagogica.

---

## 2. Departamento de Secretaria e Registro Academico

> Documentacao legal e historico dos alunos.

### 2.1 Matriculas e Transferencias

> Processa ingresso e saida de estudantes.

- [ ] **Cadastro de alunos**
  - **Principal:** Secretaria · **Tambem:** Coordenacao, Diretor
  - Dados pessoais, responsaveis, status e vinculos.
  - Importacao Excel/CSV e acompanhamento por turma.

- [ ] **Responsaveis vinculados**
  - **Principal:** Secretaria · **Tambem:** Coordenacao
  - Cadastro e vinculo de pais/responsaveis.
  - Base para painel dos pais e app mobile.

- [ ] **Matricula**
  - **Principal:** Secretaria · **Tambem:** Coordenacao, Financeiro
  - Vinculo do aluno com turma, serie e periodo.
  - Apoia movimentacao, historico e organizacao academica.

- [ ] **Matricula online / enrollment**
  - **Principal:** Secretaria · **Tambem:** Financeiro, Pais
  - Fluxo digital de matricula ou pre-matricula.
  - Pode integrar com financeiro e contratos.

- [ ] **Movimentacao / transferencia de alunos**
  - **Principal:** Secretaria · **Tambem:** Coordenacao
  - Transferencia entre turmas, status e alteracoes academicas.
  - Mantem rastreabilidade da vida escolar.

- [ ] **Lista de chamada**
  - **Principal:** Secretaria · **Tambem:** Professor, Coordenacao
  - Apoio a chamada e acompanhamento de turma.
  - Usado em rotinas escolares do dia a dia.

### 2.2 Arquivo Academico

> Guarda historicos escolares, atas de notas e diplomas.

- [ ] **Historico escolar**
  - **Principal:** Secretaria · **Tambem:** Coordenacao
  - Registro e emissao de informacoes historicas do aluno.
  - Apoia documentacao oficial.

- [ ] **Documentos do aluno**
  - **Principal:** Secretaria · **Tambem:** Coordenacao
  - Checklist e documentos vinculados ao aluno.
  - Acompanhamento de pendencias.

- [ ] **Documentos institucionais**
  - **Principal:** Secretaria · **Tambem:** Diretor, Coordenacao
  - Gestao de documentos oficiais da escola.
  - Centraliza arquivos importantes.

- [ ] **Atas, diplomas e arquivo fisico digitalizado**
  - **Principal:** Secretaria
  - **(parcial / fora da plataforma)** — depende do processo da escola.
  - Pode usar documentos institucionais e anexos como apoio.

### 2.3 Emissao de Documentos

> Emite declaracoes, boletins e certificados.

- [ ] **Declaracoes**
  - **Principal:** Secretaria
  - Emissao de documentos escolares sob demanda.
  - Atendimento a alunos e responsaveis.

- [ ] **Boletim (emissao / consulta)**
  - **Principal:** Secretaria / Coordenacao · **Tambem:** Aluno, Pais
  - Visualizacao e emissao do desempenho por periodo.
  - Regras de media configuraveis.

- [ ] **Certificados (minicursos / AVA)**
  - **Principal:** Coordenacao / Admin · **Tambem:** Aluno
  - Certificados de cursos e trilhas quando habilitados.
  - Complementa a documentacao academica.

---

## 3. Departamento Administrativo e Financeiro

> Sustentabilidade financeira e gestao de recursos.

### 3.1 Tesouraria e Caixa

> Recebe mensalidades e gerencia pagamentos.

- [ ] **Financeiro da escola (dashboard)**
  - **Principal:** Financeiro · **Tambem:** Diretor
  - Visao de pagantes, mensalidades e indicadores.
  - Apoia o dia a dia do setor financeiro.

- [ ] **Recarga e compra de TudiCoins (escola)**
  - **Principal:** Financeiro · **Tambem:** Diretor
  - Compra de creditos para recursos de IA.
  - PIX / Asaas e saldo institucional.

### 3.2 Faturamento e Cobranca

> Controla inadimplencia e negocia debitos.

- [ ] **Relatorio de pagantes**
  - **Principal:** Financeiro · **Tambem:** Diretor
  - Controle de alunos/professores pagantes.
  - Apoia cobranca e acompanhamento.

- [ ] **Contratos e mensalidades**
  - **Principal:** Financeiro · **Tambem:** Secretaria
  - Gestao financeira vinculada a matriculas (quando habilitado).
  - Pode coexistir com modulos financeiros legados e novos.

- [ ] **Negociacao de debitos / CRM financeiro**
  - **Principal:** Financeiro
  - **(fora da plataforma / operacional da escola)** — processo interno da escola.
  - EducaTudo cobre indicadores e cobranças conforme integracao Asaas.

### 3.3 Recursos Humanos (RH)

> Contrata, treina e gerencia folha de professores e funcionarios.

- [ ] **Cadastro de professores**
  - **Principal:** Coordenacao / RH · **Tambem:** Diretor, Secretaria
  - Dados, acesso e vinculos pedagogicos.
  - Nao substitui folha de pagamento completa.

- [ ] **Cadastro de monitores**
  - **Principal:** Coordenacao · **Tambem:** Diretor
  - Usuarios de supervisao em sala.
  - Perfil de leitura e acompanhamento.

- [ ] **Cadastro de usuarios admin**
  - **Principal:** Diretor · **Tambem:** Dev
  - Contas com papeis: diretor, coordenacao, secretaria, financeiro, desenvolvedor.
  - Controla quem acessa o que no painel.

- [ ] **Perfis de permissao**
  - **Principal:** Diretor · **Tambem:** Dev
  - Matriz de permissoes por papel.
  - Adapta o painel admin as funcoes da escola.

- [ ] **Folha de pagamento e ponto**
  - **Principal:** RH
  - **(fora da plataforma / operacional da escola)**
  - EducaTudo nao substitui sistema de RH/folha.

### 3.4 Compras e Almoxarifado

> Adquire e controla estoque de materiais.

- [ ] **Almoxarifado**
  - **Principal:** Secretaria · **Tambem:** Diretor
  - Controle de estoque e materiais.
  - Apoia gestao de recursos fisicos.

- [ ] **Patrimonio**
  - **Principal:** Secretaria · **Tambem:** Diretor
  - Inventario de bens da instituicao.
  - Controle administrativo e organizacao patrimonial.

- [ ] **EducaShop / carteira de creditos**
  - **Principal:** Professor / Aluno · **Tambem:** Financeiro
  - Compra de recursos com creditos (TudiCoins).
  - Monetizacao de funcionalidades digitais, nao de material fisico.

---

## 4. Departamento de Operacoes e Infraestrutura

> Ambiente fisico seguro, limpo e funcionando.

### 4.1 Manutencao e Conservacao

- [ ] **Manutencao predial / eletrica / hidraulica**
  - **Principal:** Operacoes
  - **(fora da plataforma / operacional da escola)**
  - Nao ha modulo especifico no EducaTudo.

### 4.2 Limpeza e Zeladoria

- [ ] **Limpeza e zeladoria**
  - **Principal:** Operacoes
  - **(fora da plataforma / operacional da escola)**
  - Nao ha modulo especifico no EducaTudo.

### 4.3 Seguranca e Portaria

> Controla acesso para proteger alunos.

- [ ] **Reconhecimento facial (entrada/saida)**
  - **Principal:** Coordenacao / Admin · **Tambem:** Secretaria, Pais
  - Registro de entrada e saida por reconhecimento facial.
  - Historico visivel para gestao e responsaveis.

- [ ] **Dispositivos de portaria**
  - **Principal:** Coordenacao / Admin · **Tambem:** Secretaria
  - Pareamento e gestao dos equipamentos de acesso.
  - Apoia a operacao de portaria.

- [ ] **Unidades / sedes**
  - **Principal:** Diretor · **Tambem:** Secretaria
  - Cadastro de unidades ou filiais.
  - Apoia escolas com mais de uma sede.

### 4.4 Logistica e Transporte

- [ ] **Transporte escolar / frota**
  - **Principal:** Operacoes
  - **(fora da plataforma / operacional da escola)**
  - Nao ha modulo especifico no EducaTudo.

---

## 5. Departamento de Tecnologia da Informacao (TI)

> Ferramentas digitais e infraestrutura tecnologica.

### 5.1 Suporte Tecnico

- [ ] **Tickets de suporte**
  - **Principal:** Aluno / Escola · **Tambem:** Master, Dev
  - Chamados de ajuda tecnica ou duvidas.
  - Podem ser centralizados no Master conforme fluxo.




---

## 6. Departamento de Apoio ao Estudante e Servicos Gerais

> Servicos que complementam a rotina escolar.

### 6.1 Biblioteca ou Centro de Midia


- [ ] **Drive do aluno**
  - **Principal:** Aluno · **Tambem:** Professor
  - Arquivos pessoais e compartilhamento.
  - Espaco digital de materiais do estudante.

- [ ] **Caderno digital**
  - **Principal:** Aluno
  - Anotacoes pessoais, editor e quadro branco.
  - Apoio ao estudo diario.

- [ ] **EducaLabs**
  - **Principal:** Aluno
  - Projetos e laboratorio com apoio de IA.
  - Experiencias digitais complementares.

- [ ] **EducaHits**
  - **Principal:** Aluno
  - Conteudos e experiencias digitais (ex.: musica educativa).
  - Engajamento e aprendizagem complementar.

- [ ] **Jogos educativos**
  - **Principal:** Aluno
  - Xadrez, quiz com IA, Jogo do Milhao etc.
  - Aprendizagem ludica com controle de acesso.

### 6.2 Nutricao e Cantina

- [ ] **Merenda / cantina / cardapio**
  - **Principal:** Servicos Gerais
  - **(fora da plataforma / operacional da escola)**
  - Nao ha modulo especifico no EducaTudo.

### 6.3 Enfermaria ou Primeiros Socorros

- [ ] **Enfermaria / atendimento de saude**
  - **Principal:** Servicos Gerais
  - **(fora da plataforma / operacional da escola)**
  - Nao ha modulo especifico no EducaTudo.
  - Ocorrencias e saude academica cobrem o lado pedagogico/comportamental, nao clinico.

### 6.4 Acompanhamento familiar (apoio ao estudante)

- [ ] **Painel dos pais**
  - **Principal:** Pais
  - Acompanhamento web do filho (somente leitura).
  - Desempenho, comunicados, calendario e entrada/saida.

- [ ] **App mobile dos pais**
  - **Principal:** Pais
  - Aplicativo para acompanhar a vida escolar.
  - Push de comunicados, eventos e acesso.

---

## 7. Departamento de Comunicacao e Marketing

> Imagem da instituicao e relacionamento com a comunidade.

### 7.1 Atendimento e Recepcao

- [ ] **Atendimento inicial / recepcao**
  - **Principal:** Secretaria / Recepcao
  - **(parcial)** — cadastros, matricula e comunicacao apoiam o fluxo.
  - Processo presencial continua operacional da escola.

- [ ] **Tickets / suporte ao usuario**
  - **Principal:** Aluno · **Tambem:** Admin, Master
  - Canal digital de atendimento.
  - Complementa a recepcao presencial.

### 7.2 Comunicacao Interna

> Comunicados e avisos para pais e responsaveis.

- [ ] **Mural de recados**
  - **Principal:** Professor · **Tambem:** Coordenacao, Aluno
  - Avisos rapidos para turmas e alunos.
  - Canal simples de comunicacao pedagogica.

- [ ] **Comunicacao escolar**
  - **Principal:** Coordenacao / Admin · **Tambem:** Professor, Aluno, Pais
  - Comunicados individuais ou coletivos com anexos.
  - Respostas e confirmacao de leitura.

- [ ] **Calendario escolar**
  - **Principal:** Coordenacao / Admin · **Tambem:** Aluno, Pais, Professor
  - Provas, reunioes, feriados e eventos.
  - Visivel para a comunidade escolar.

- [ ] **Notificacoes (e-mail, WhatsApp, in-app)**
  - **Principal:** Coordenacao / Admin · **Tambem:** Todos
  - Canais de aviso automatico ou manual.
  - Lembretes, eventos, entrada/saida e alertas.

- [ ] **Push notifications**
  - **Principal:** Coordenacao / Admin · **Tambem:** Aluno, Pais, Professor
  - Notificacoes web e mobile.
  - Reforca a comunicacao com a familia.

- [ ] **Chat aluno-professor**
  - **Principal:** Aluno · **Tambem:** Professor
  - Conversa dentro da plataforma.
  - Comunicacao rastreavel escola-aluno.

- [ ] **Forum**
  - **Principal:** Aluno · **Tambem:** Coordenacao (moderacao)
  - Discussao entre alunos.
  - Moderacao e denuncias.

- [ ] **Reunioes**
  - **Principal:** Coordenacao · **Tambem:** Professor, Pais, Secretaria
  - Organizacao de encontros com familia e equipe.
  - Pode se relacionar ao calendario.

### 7.3 Marketing e Captacao

> Campanhas para atrair novos alunos.

- [ ] **Matricula online (captacao)**
  - **Principal:** Secretaria / Marketing · **Tambem:** Financeiro, Pais
  - Fluxo digital de ingresso.
  - Apoia captacao e conversao de interessados.

- [ ] **Identidade visual / PWA da escola**
  - **Principal:** Marketing / Diretor · **Tambem:** Dev
  - Logo, cores e experiencia white-label.
  - Reforca a marca da instituicao no digital.

- [ ] **Campanhas de midia paga / CRM de leads**
  - **Principal:** Marketing
  - **(fora da plataforma / operacional da escola)**
  - EducaTudo nao substitui ferramenta de marketing externo.

---

## 8. Perfis de acesso na plataforma

> Como a estrutura de departamentos se traduz nos logins do EducaTudo.

- [ ] **Aluno** (`/`)
  - Consome conteudo pedagogico, avaliacoes, Tudinha e recursos de apoio.

- [ ] **Professor** (`/professor`)
  - Atua no departamento pedagogico: cria, corrige e acompanha turmas.

- [ ] **Admin / Coordenacao / Secretaria / Financeiro / Dev** (`/admin`)
  - Painel unico com permissoes por papel, cobrindo os departamentos acima.

- [ ] **Monitor** (`/monitor`)
  - Supervisao de sala em tempo real (leitura).

- [ ] **Pais / Responsavel** (`/pais` + app)
  - Acompanhamento familiar; interface do apoio ao estudante e comunicacao.

- [ ] **Master** (`/master`)
  - Operacao SaaS do EducaTudo (nao e departamento da escola).
