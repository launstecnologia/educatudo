# Convenção de nomenclatura de tabelas – EducaTudo

Este documento analisa o padrão atual das tabelas do banco e propõe uma convenção única para **novas tabelas** e, se desejar, para refatoração futura.

---

## 1. Estado atual (análise)

### O que já é consistente
- **Separador:** quase todas usam **underscore** (`_`).
- **Vários módulos** seguem um prefixo claro:
  - `jornadas_*`, `provas_*`, `forum_*`, `notificacoes_*`, `minicursos_*`, `flashcard_*`, `enem_*`, `essay_*`, `educalabs_*`, `caderno_aluno_*`, `chat_professor_aluno_*`, `provas_blocos_*`.

### Inconsistências

| Aspecto | Exemplos atuais | Problema |
|--------|------------------|----------|
| **Idioma** | `essay_boards`, `audit_logs`, `user_consents`, `student_status_history` vs `provas`, `alunos`, `redacoes` | Mistura de inglês e português. |
| **Singular vs plural** | `alunos`, `turmas`, `provas` (plural) vs `usuario` em `usuarios`, `materia` em `materias` | Nome da entidade às vezes singular, às vezes plural. |
| **Aluno** | `alunos_seguranca`, `aluno_acoes_diarias` | Prefixo às vezes `alunos_`, às vezes `aluno_`. |
| **Lista** | `listas_personalizadas`, `listas_exercicios_personalizados` vs `lista_exercicios` | `lista` vs `listas`. |
| **Jogos** | `games_tokens`, `game_sessions`, `game_actions` vs `partidas_jogo_milhao`, `perguntas_jogo_milhao` | `game(s)_` em inglês vs `jogo_milhao` em português. |
| **Notificação** | `push_notificacoes`, `push_notificacao_envios` | Singular vs plural no mesmo prefixo. |
| **Nome com data** | `backup_enem_questions_20251028` | Uso de data no nome (evitar em tabelas de aplicação). |

---

## 2. Proposta de padrão único

Recomendação: **um único conjunto de regras** para todas as tabelas novas (e, quando fizer sentido, para renomear antigas em migrações).

### Regra 1 – Forma das palavras
- **Nome da tabela:** sempre **plural** (a tabela guarda “vários” registros).
- **Exemplos:** `alunos`, `turmas`, `provas`, `mensagens_chat`, `partidas_jogo_milhao`.

### Regra 2 – Idioma
- **Um idioma só:** preferir **português** em todo o banco (projeto é BR).
- **Exceção aceitável:** módulos que já nasceram em inglês (ex.: `essay_*`, integrações) podem manter inglês para evitar quebra de código, mas **novas tabelas** em PT.

### Regra 3 – Estrutura do nome (snake_case)
- Apenas **letras minúsculas** e **underscore** (`_`).
- Padrão geral:  
  `[módulo_][entidade_][tipo]`  
  - **módulo** (opcional): contexto (ex.: `jornadas`, `forum`, `enem`).  
  - **entidade**: substantivo principal em plural.  
  - **tipo** (opcional): subtipo ou relação (ex.: `anexos`, `mensagens`, `historico`).

### Regra 4 – Tabelas relacionadas (prefixo)
- Tabelas que “pertencem” a uma entidade usam o **mesmo prefixo**.
- Padrão: `entidade_plural_subtipo`.
- **Exemplos:**
  - `caderno_aluno` → `cadernos_aluno` (ou manter nome atual).
  - Filhas: `cadernos_aluno_anexos`, `cadernos_aluno_pastas`.
  - `provas` → filhas: `provas_blocos`, `provas_questoes`, `provas_respostas`.

### Regra 5 – Tabelas de vínculo (N:N)
- Nome que deixe claro as duas entidades.
- Padrão: `entidade1_entidade2` (ambas no plural quando fizer sentido).
- **Exemplos:** `provas_blocos_turmas`, `forum_topicos_turmas`, `jornadas_materias`.

### Regra 6 – Tabelas de configuração / sistema
- Prefixo que indique finalidade: `config_*`, `log_*`, `audit_*`, `dev_*`.
- **Exemplos:** `config_layout`, `config_simulados`, `log_auditoria`, `log_llm_uso`.

### Regra 7 – Evitar no nome
- Datas ou versões: não usar `_20251028` em nome de tabela de aplicação (usar só em backups/scripts).
- Siglas obscuras: preferir nome legível (ex.: `mensagens_chat` em vez de só `msg_chat` se for o padrão do projeto).

---

## 3. Resumo do padrão sugerido

| Regra | Padrão |
|-------|--------|
| Forma | **Plural** para a entidade principal |
| Idioma | **Português** (novas tabelas); exceção para módulos já em inglês |
| Separador | **Underscore** (`_`) |
| Maiúsculas | **Nenhuma** (tudo minúsculo) |
| Relacionadas | **Mesmo prefixo**: `entidade_subtipo` |
| Vínculo N:N | **entidade1_entidade2** |
| Config / log | **config_***, **log_***, **audit_*** |

**Formato geral:**  
`[modulo_]entidade_plural[_subtipo]`  
Ex.: `jornadas_modulos_textos`, `notificacoes_destinatarios`, `provas_respostas`.

---

## 4. Exemplos aplicados ao que você já tem

Só como referência (não é obrigatório renomear tudo de uma vez):

| Nome atual | Observação / possível padrão futuro |
|------------|-------------------------------------|
| `aluno_acoes_diarias` | Padrão: `alunos_acoes_diarias` (prefixo plural). |
| `lista_exercicios` | Padrão: `listas_exercicios` (plural). |
| `push_notificacao_envios` | Padrão: `push_notificacoes_envios` (plural no prefixo). |
| `game_sessions` | Se padronizar em PT: `sessoes_jogo` ou manter `game_sessions` por histórico. |
| `games_tokens` | Se padronizar: `tokens_jogos_externo` ou manter por uso em integração. |
| `student_status_history` | Se padronizar: `historico_status_aluno`. |
| `user_consents` | Se padronizar: `consentimentos_usuario`. |

Para **novas tabelas**, seguir desde já o padrão (plural, português, prefixo do módulo/entidade).

---

## 5. Checklist para nova tabela

- [ ] Nome em **minúsculas** e **snake_case**.
- [ ] Entidade principal no **plural**.
- [ ] **Português** (exceto módulos já em inglês).
- [ ] Tabelas do mesmo módulo com **mesmo prefixo** (ex.: `modulo_x_*`).
- [ ] Sem data/versão no nome (reservar para backups/scripts).
- [ ] Nome legível e previsível para quem for dar manutenção.

Se quiser, no próximo passo podemos aplicar esse padrão só a um módulo (ex.: “jogos” ou “notificações”) e escrever os nomes exatos das tabelas desse módulo.
