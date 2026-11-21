# Plano de extração — Redações com IA (legado `/redacoes`)

**Data:** 2026-11-21 (pós Etapa 6)  
**Status:** **Etapa 6 concluída** — fluxos IA/créditos no módulo `redacoes`.

---

## Migrado

| Fatia | Service / Controller |
|---|---|
| Hub, historico, ver, rascunhos, ocultar | `RedacaoAlunoLegadoService` + `AlunoRedacaoLegadoController` |
| `criar`, `gerarTema`, `transcreverImagem`, `corrigir` | `RedacaoAlunoIaService` + mesmo controller |

## Comportamento de créditos congelado (não alterar sem decisão)

- Tema: debita `redacao_gerar_tema_aluno` antes da IA; estorna em exception; JSON inválido = fallback **sem** estorno.
- Correção legada: **não** debita `redacao_correcao_ia`; não registra `alunos_acoes_diarias`.
- Transcrição: sem TudiCoins.

## Ainda no StudentController (compartilhado Tudinha)

`getLimiteDiario` / `contarUsoDiario` / `podeExecutarAcao` — usados pelo chat. Extração unificada → Etapa 7.

## Fora de escopo (permanece)

Alterar prompts, modelos, preços, tornar correção assíncrona, passar a cobrar `redacao_correcao_ia` no legado.
