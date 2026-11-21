# Padrão arquitetural de módulos (EducaTudo)

**Data:** 2026-11-20  
**Uso:** novos módulos e fatias de migração do StudentController.

---

## Camadas

```
Controller (magro) → Service (regras + SQL) → Model (opcional, CRUD simples)
                 ↘ Views do módulo / Painel
```

| Camada | Faz | Não faz |
|---|---|---|
| Controller | Auth de perfil, CSRF POST, montar view/JSON, cast de ids | SQL complexo, regras de cobrança, loops de negócio |
| Service | Ownership, visibilidade, orquestração, prepared statements | HTTP, sessão direta (exceto se injetada), UI |
| Model | CRUD de uma tabela quando já existe padrão | Autorização cross-recurso |
| Policy (futuro) | `podeVer(recurso, user)` reutilizável | Persistência |

## Regras obrigatórias

1. Prepared statements (`:id`) + cast explícito de ids.  
2. Sem `WHERE escola_id` em tenant (isolamento = PDO).  
3. Recurso de aluno só após `aluno_id = sessão`.  
4. CSRF em POST (exceto `/api/*` JWT).  
5. IA > 2s → job assíncrono.  
6. Nomenclatura PT em código novo (ver `.claude/docs/nomenclatura.md`).  
7. Rotas em `Modulos/<chave>/routes.php`; URLs estáveis.  
8. Feature opcional → `FeatureGate` / LayoutHelper.

## Anti-padrões

- Controller com 200+ linhas de SQL.  
- Duplicar SQL de listagem e contagem (preferir um Service).  
- Depender de outro módulo via Controller (preferir Service ou Dominio).  
- Interfaces sem segundo implementador.  
- Migrar IA/créditos sem testes de caracterização + mocks.

## Checklist de fatia de migração

1. Inventário + caracterização  
2. Service com SQL do legado  
3. Controller no módulo  
4. Rotas (mesmas URLs)  
5. Remover do StudentController após consumidores zero  
6. PHPUnit no gate CI de módulos  
7. Rollback documentado
