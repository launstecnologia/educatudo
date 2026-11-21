# Etapa 4.3 — Auditoria de ownership

**Data:** 2026-11-20  
**Escopo:** mapear acesso por ID; corrigir só falhas com regra clara.

---

## Resumo

| Área | Perfil | Ownership | Tenant | Lacunas |
|---|---|---|---|---|
| Planos aluno | aluno | turma / série+ano + **status=aprovado** (4.1) | PDO | Anexos dedicados: N/A |
| Planos pais/API | pai | filho vinculado + turma + aprovado | PDO | Aplicado filtro 4.1 |
| Mural aluno | aluno | `alunoPodeVer` / destino | PDO | OK pós-migração |
| Redação legado | aluno | `aluno_id` em SELECT/UPDATE/DELETE | PDO | OK pós-4.3 service |
| Redação configurável | aluno | proposal/submission models | PDO | Revisar envios alheios em fatia futura |
| Jornadas aluno | aluno | turma da jornada | PDO | Alta superfície — fatia futura |
| Chat Tudinha | aluno | `conversa.aluno_id` em vários pontos | PDO | Ver plano Tudinha |
| Chat professor | aluno/prof | SEC-10 + PoliticaAcesso | PDO | Já tratado Etapa 1 |
| Ocorrências | admin/prof | módulo próprio | PDO | Inventário superficial — sem correção |
| Arquivos privados | vários | path + sessão | PDO | Testes em Seguranca |
| Compras carteira | aluno | `user_id` + `user_type=aluno` | PDO | Parece OK |

## Correções nesta etapa

1. **Planos rascunho** (4.1) — regra de produto aplicada no service + pais/API.  
2. **Ocultar redação** (Etapa 3) — UPDATE com `aluno_id` (já feito).

## Não corrigido (sem regra inequívoca ou alto risco)

- Assimetria listagem×detalhe de planos (turma vs série) — comportamento legado preservado.  
- Fluxos IA/créditos de redação e Tudinha — só inventário (4.5/4.6).  
- Jornadas: superfície grande; migrar ownership em fatia dedicada.

## Testes negativos existentes / novos

- `PlanoAulaAlunoVisibilidadeStatusTest` — rascunho/rejeitado/ID  
- `RedacaoAlunoLegadoCaracterizacaoTest` — ownership  
- `MuralRecadoAlunoServiceTest` — marcar visto sem acesso  
- `PoliticaAcessoProfessorAlunoTest` / reset senha  
- `AcessoArquivoPrivadoTest`
