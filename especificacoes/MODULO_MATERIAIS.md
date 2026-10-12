# Módulo: Materiais

> Explicação em linguagem simples sobre "Materiais" na plataforma EducaTudo.

---

## Atenção: "Materiais" não é uma tela única

Diferente dos outros módulos deste mapeamento, **"Materiais" não é uma funcionalidade própria, com tela e botão dedicados**. É o **nome da categoria** que organiza, no menu do Admin, os módulos relacionados a conteúdo de apoio:

- **Apostilas**
- **Arquivos**
- **Educa Livros**
- *(além de Minicursos e Planos de Aula, que também ficam agrupados na mesma categoria "Conteúdo" do menu)*

Ou seja: se você está procurando "onde fica o módulo de Materiais", a resposta é: **não existe um módulo único** — existem três módulos distintos, cada um com sua própria lógica, listados abaixo.

---

## Apostilas

Biblioteca simples de PDFs e imagens, organizada por turma. O admin sobe o arquivo e escolhe quem pode ver (aluno, professor, ou ambos). Sem inteligência artificial — é só consulta/leitura.

> Existe também a **"IA da Apostila"**, um módulo bem mais avançado (com chat, geração de provas e slides), [documentado separadamente aqui](IA_DA_APOSTILA_GUIA_DO_USUARIO.md). Não confundir os dois.

**Quem cria:** Admin · **Quem vê:** Aluno e/ou professor (conforme escolhido) · **Onde:** `/admin/apostilas`, `/aluno/apostilas`, `/professor/apostilas`

---

## Arquivos

Parecido com Apostilas, mas com uma diferença importante: **quem cria pode ser o professor**, não só o admin, e é possível direcionar um arquivo **para um aluno específico** (não só para a turma toda). Cada arquivo pode ter vários anexos (PDF, imagem, vídeo, áudio).

**Quem cria:** Admin ou Professor · **Quem vê:** Alunos da turma (ou aluno específico, se direcionado) · **Onde:** `/admin/arquivos`, `/professor/arquivos`, `/aluno/arquivos`

*(Veja o [documento específico de Arquivos](MODULO_ARQUIVOS.md) para mais detalhes.)*

---

## Educa Livros

Não é upload de arquivo — é uma **busca de livros** (usando o catálogo do Google Books). O aluno pesquisa por palavra-chave e vê informações do livro (autor, capa, descrição, link de pré-visualização). Não fica nada salvo na plataforma; é uma janela para o catálogo do Google.

**Quem usa:** Aluno · **Onde:** `/livros`

---

## Por que separar assim, e não num só lugar?

Cada um tem uma finalidade diferente:

| Módulo | Quem cria | Pode direcionar a 1 aluno? | Tem busca externa? |
|---|---|---|---|
| **Apostilas** | Admin | Não (por turma) | Não |
| **Arquivos** | Admin ou Professor | Sim | Não |
| **Educa Livros** | — (catálogo externo) | — | Sim (Google Books) |

Se no futuro a escola sentir falta de uma tela única que junte os três, isso seria uma melhoria de **navegação** (criar um "hub" que linka para os três) — hoje, tecnicamente, são sistemas independentes.

---

## Veja também

- [Arquivos](MODULO_ARQUIVOS.md)
- [Guia da IA da Apostila](IA_DA_APOSTILA_GUIA_DO_USUARIO.md)
