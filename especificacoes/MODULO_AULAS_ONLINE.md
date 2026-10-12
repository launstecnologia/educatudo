# Módulo: Aulas Online

> Explicação em linguagem simples do módulo de Aulas Online da plataforma EducaTudo.

---

## O que é

É o sistema de **aulas em vídeo ao vivo** da escola — como um Google Meet ou Zoom, mas integrado à plataforma, com chat e gravação automática.

---

## Quem cria e organiza

Hoje, **só o Admin/Coordenação** cria e agenda aulas online (em `/admin/aulas-online`). O professor ainda não tem uma tela própria para criar aula online por conta própria — quem organiza é a coordenação da escola.

Ao criar uma aula, o admin define:
- Título e descrição
- Data e horário de início (e, opcionalmente, fim)
- Para quais turmas a aula é destinada (pode marcar "disponível para todas as turmas")
- Se a aula deve se repetir semanalmente (e por quantas semanas — de 2 a 52)

---

## Como o aluno participa

O aluno vê a lista de aulas agendadas/em andamento na sua tela (`/aluno/aulas-online`) — só aparecem aulas da turma dele (ou marcadas como "para todos"). Ao abrir uma aula, ele vê:

- Título, descrição e horário
- O vídeo da aula (ao vivo, ou a gravação depois que terminar)
- Um **chat** ao lado, para conversar durante a aula

---

## Tecnologia de vídeo (duas opções)

A escola pode usar uma das duas:

1. **Jitsi (videoconferência)** — parecido com Google Meet. O admin entra como "moderador" (pode gravar a aula), o aluno entra como participante. Pode ser auto-hospedado pela própria EducaTudo ou usar o serviço externo JaaS (8x8).
2. **Panda Video Live (streaming)** — mais parecido com uma transmissão (live), pensado para aulas "estilo palestra" com muitos espectadores. Quem dá a aula transmite usando um programa de transmissão (ex.: OBS), e os alunos assistem pela plataforma.

A escolha de qual usar é feita pela coordenação ao configurar o sistema.

---

## Gravação automática

- **Pelo Jitsi**: o admin aciona "gravar" durante a aula. Quando termina, o sistema baixa o vídeo automaticamente e ele fica disponível para os alunos da turma assistirem depois, em `/aluno/aulas-online/gravacao/{id}`.
- **Pelo Panda Video**: a gravação acontece automaticamente, sem precisar apertar nada — assim que a transmissão termina, o vídeo gravado já fica disponível para assistir.

Em ambos os casos, só os alunos da turma autorizada conseguem acessar a gravação — não é um link público.

---

## Chat durante a aula

Tem um **chat em tempo real** rodando ao lado do vídeo, tanto pra quem está dando a aula (admin/coordenação) quanto pra quem está assistindo (aluno). É útil para tirar dúvidas rápidas sem interromper a fala.

---

## Pontos de atenção

- **Aula online não está dentro da Jornada do Aluno** — são dois módulos separados. Uma aula online não aparece como um módulo dentro de uma trilha de estudo; ela tem sua própria tela.
- **Sem integração com calendário** — a aula aparece na lista dentro da plataforma, mas não manda convite para o Google Agenda/Outlook do aluno.
- **Sem lembrete automático** — não existe (ainda) um aviso por notificação avisando "sua aula começa em 10 minutos".

---

## Perguntas frequentes

**Um professor pode criar a própria aula online?**
Hoje não — só a coordenação/admin da escola consegue criar e agendar.

**A gravação fica salva para sempre?**
Sim, fica disponível normalmente, salva nos servidores da plataforma (ou na nuvem do Panda Video, dependendo da tecnologia usada).

**Posso usar a mesma aula configurada toda semana?**
Sim — ao marcar "repetir semanalmente", o sistema cria automaticamente as próximas aulas com a data ajustada.
