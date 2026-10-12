# Módulo: EducaCursos

> Explicação em linguagem simples do módulo de minicursos da plataforma EducaTudo.
>
> **Nota:** no sistema (menus, código, banco de dados), este módulo ainda aparece com o nome **"Minicursos"**. "EducaCursos" é o novo nome de marca sugerido para este documento — caso a equipe decida renomear oficialmente na interface, é só uma questão de texto, sem mudança de funcionamento.

---

## O que é

É uma plataforma de **cursos curtos** dentro do EducaTudo — pense em algo parecido com um curso online de uma plataforma de ensino a distância, só que dentro do próprio sistema da escola. Cada curso é dividido em módulos e aulas.

---

## Quem usa e onde

| Perfil | Onde acessa | O que faz |
|---|---|---|
| **Admin** | `/admin/minicursos` | Cria e organiza os cursos, módulos e aulas |
| **Aluno** | `/minicursos` | Assiste/lê as aulas e acompanha o próprio progresso |

> Hoje, **só o Admin cria** EducaCursos — não é uma ferramenta de uso do professor.

---

## Estrutura: curso → módulo → aula

1. **Curso** — tem um nome, descrição e imagem de capa.
2. **Módulo** — divide o curso em blocos temáticos.
3. **Aula** — a unidade real de conteúdo dentro de um módulo. Cada aula pode ser:
   - **Vídeo**
   - **Slides**
   - **PDF**
   - **Link externo**
   - **Texto** (conteúdo escrito direto na plataforma)

O admin organiza tudo numa interface onde é possível reordenar módulos e aulas (arrastar e soltar).

---

## Como o aluno usa

1. Abre a lista de cursos disponíveis.
2. Escolhe um curso e vê os módulos/aulas.
3. Assiste/lê cada aula — ao terminar, marca como **"vista"**.
4. O sistema acompanha quantas aulas já foram vistas e mostra o **progresso** do curso.
5. Quando todas as aulas de um curso são marcadas como vistas, o curso é considerado **concluído**.

---

## Para que serve, na prática

É ideal para conteúdos que não fazem parte do currículo obrigatório de uma matéria, mas que a escola quer oferecer como **extra** — por exemplo:
- Curso de preparação para uma prova externa (ENEM, vestibular)
- Curso de uma habilidade específica (oratória, redação técnica, informática básica)
- Trilhas de aprofundamento em um assunto que não cabe dentro da grade normal

---

## Perguntas frequentes

**O professor pode criar um EducaCurso?**
Não no momento — a criação é feita pelo Admin da escola.

**O aluno recebe certificado ao concluir?**
Não há, hoje, emissão automática de certificado — o sistema controla só o progresso/conclusão internamente.

**Posso reordenar as aulas depois de já ter publicado o curso?**
Sim, a reorganização de módulos e aulas pode ser feita a qualquer momento pelo Admin.
