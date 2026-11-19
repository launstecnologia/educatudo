# EducaTudo — Como a plataforma é feita

Visão simples da estrutura, para quem está conhecendo o produto. Várias escolas usam o mesmo sistema. Os dados de uma não se misturam com os da outra.

Novembro de 2026

---

## Onde roda

**Aplicação na Oracle.** O site, a API e os serviços de apoio rodam em servidores na Oracle Cloud. A entrada pública passa pela Cloudflare, com o domínio de cada escola.

**Banco gerenciado pela Oracle.** Os dados ficam em MySQL gerenciado pela Oracle, fora do servidor da aplicação. Cada escola tem o próprio banco. Um banco central guarda só o cadastro das escolas.

Junto da aplicação há um Redis, usado para sessão e para lembrar rapidamente qual escola está acessando. Arquivos de aula e materiais ficam em armazenamento separado do banco.

---

## Em que linguagem

| Parte | Linguagem | O que faz |
|---|---|---|
| Plataforma web e API | PHP | Painéis do aluno, do professor, da escola e da operação do EducaTudo. Também atende o aplicativo. |
| Telas no navegador | HTML, CSS e JavaScript | O que a pessoa vê e usa no computador. |
| Aplicativo dos pais | Flutter | Acompanhamento do filho no celular. Não acessa o banco: conversa com a API. |
| Dados | MySQL | Tudo que a escola grava: pessoas, turmas e notas. |

---

## Como o sistema é organizado

A plataforma web é um monólito modular com MVC próprio. Um único sistema atende todas as escolas. Por dentro, cada função opcional é um módulo que a escola liga ou desliga. O núcleo da escola permanece em todas.

**MVC próprio.** A tela pede, o controlador coordena, a regra de negócio fica no serviço e o modelo grava e lê o MySQL. A página só monta o que a pessoa vê. Esse caminho foi feito no EducaTudo, para o produto escolar.

**Módulos em volta do núcleo.** Prova, redação, jornada e os demais recursos nascem como módulo. Dá para entregar um conjunto diferente por escola sem separar o sistema em vários produtos.

---

## Estrutura da escola

O núcleo descreve a escola de verdade, e o resto se apoia nele.

- **Pessoas.** Alunos, professores e monitores, cada um com o seu acesso.
- **Organização.** Ano letivo, curso, série e turma. A turma é onde a aula, a prova e o acompanhamento acontecem.
- **O que a escola liga.** Os módulos usam essas pessoas e essas turmas. Sem esse núcleo, o módulo não tem onde se apoiar.

---

## Como a escola fica separada

A pessoa entra pelo endereço da escola. A plataforma identifica qual escola é e abre somente o banco dela. O banco central não guarda nota, aluno nem conversa: guarda o cadastro da escola, o domínio e o que está ligado para ela.

Os módulos ligados mudam de uma escola para outra. Pessoas, turmas e ano letivo existem em todas, cada uma no seu banco.

---

## Quem usa

| Quem | Para quê |
|---|---|
| Aluno | Estudar, fazer prova e redação, tirar dúvida com a Tudinha |
| Professor | Montar conteúdo, prova e acompanhar a turma |
| Escola | Cadastrar pessoas, organizar o ano e acompanhar a gestão |
| Monitor | Acompanhar a sala |
| Pais | Ver o filho pelo site ou pelo aplicativo |
