# EducaTudo — módulos da plataforma

Este documento organiza os módulos do EducaTudo por perfil de uso, com foco em facilitar apresentação, planejamento comercial e entendimento funcional da plataforma.

Também registra como funcionam os módulos que são implementados como aplicações externas.

---

## Módulos para o Aluno

### 1. Tudinha

- Chat dentro da própria plataforma.
- Implementado no sistema principal em PHP.
- Usa integração com OpenAI para conversas e recursos de IA.
- Funciona como assistente educacional para tirar dúvidas e apoiar os estudos.

### 2. Tudinha 2.0

- Aplicação externa.
- Desenvolvida no Lovable.
- Acesso por autenticação via token.
- Abre em página separada.

### 3. Exercícios gerados pela Tudinha

- Exercícios criados por IA a partir de temas, assuntos ou solicitações do aluno.
- Faz parte da proposta de estudo personalizado dentro da plataforma.

### 4. Simulados

- Simulados para treino e preparação do aluno.
- Voltado especialmente para prática avaliativa e acompanhamento de desempenho.

### 5. FlashCards

- Cartões de estudo para memorização e revisão.
- Podem ser usados como reforço rápido de conteúdo.

### 6. Mini Cursos

- Conteúdos organizados em formato de minicurso.
- Pensados para estudo complementar e trilhas curtas de aprendizado.

### 7. Notes

- Aplicação externa.
- Desenvolvida no Lovable.
- Acesso por autenticação via token.
- Abre em página separada.

### 8. Drive

- Espaço para armazenar arquivos do aluno.
- Serve como apoio para organização de materiais e documentos.

### 9. Forum

- Ambiente de interação e discussão.
- Pode ser usado para troca entre alunos e participação em tópicos.

### 10. Games

- Aplicação externa.
- Desenvolvida no Lovable.
- Acesso por autenticação via token.
- Abre em página separada.

### 11. EducaHits - Músicas Estudantis

- Aplicação externa.
- Desenvolvida no Lovable.
- Acesso por autenticação via token.
- Abre em página separada.

---

## Como funcionam as aplicações externas

No EducaTudo, uma aplicação externa é um sistema fora da aplicação principal, mas integrado ao ecossistema da escola.

Na prática, o fluxo funciona assim:

1. O aluno ou professor clica no módulo dentro do EducaTudo.
2. O EducaTudo gera um token temporário de acesso.
3. O usuário é redirecionado para a URL da aplicação externa.
4. A aplicação externa recebe esse token.
5. A aplicação externa chama um endpoint do EducaTudo para validar o token.
6. Se o token for válido, o app externo identifica o usuário e libera a experiência sem exigir novo login.

### O que o EducaTudo envia

Na validação, o app externo pode receber dados como:

- `userId`
- `userName`
- `nickname`
- `slug` da escola
- `app`
- `inst`

Em alguns casos, o EducaTudo também pode enviar um contexto mais rico do aluno, se isso estiver habilitado para o app externo configurado.

Esse contexto pode incluir:

- dados básicos do aluno
- turma
- provas realizadas
- desempenho
- jornadas
- planos de aula

### Onde isso é configurado

Os apps externos são cadastrados na configuração da escola, normalmente na estrutura de `external_apps_links`.

Cada item pode definir:

- nome
- URL
- se aparece para aluno
- se aparece para professor
- se abre em nova guia
- grupo do menu
- ícone
- se substitui um módulo nativo
- se envia contexto do aluno

### Observações importantes

- O token é temporário.
- O fluxo evita que o usuário precise fazer login novamente no app externo.
- O app externo precisa implementar a chamada de validação do token.
- Existe compatibilidade com estruturas antigas de tokens em alguns módulos legados.
- O Games pode ter regra de horário para acesso do aluno.

---

## Base técnica desta documentação

Esta organização foi consolidada a partir de:

- `PLATAFORMA.md`
- `docs/educatudo-leigo.md`
- `docs/educatudo-tecnico.md`
- `src/docs/apps-externos-guia-simples.md`
- `src/docs/apps-externos-tecnico.md`
- `src/app/Controllers/ExternalApps/ExternalAppsController.php`
- `src/app/Controllers/Notes/NotesTokenController.php`
