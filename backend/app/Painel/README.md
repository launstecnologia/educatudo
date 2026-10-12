# Painel (shell por perfil)

Casca de cada tipo de acesso — layout/dashboard/home do perfil.
**Não** colocar CRUD de domínio nem features de módulo aqui.

```
Painel/
  Admin/ Professor/ Aluno/ Pais/ Master/ Monitor/ Auth/
    Controllers/   # só shell (dashboard, hubs de menu)
    Views/         # opcional; layouts globais ficam em Views/layouts/
```

- Negócio essencial → `Dominio/`
- Feature opcional → `Modulos/` (EducaHits, EducaLabs, Apps Externos, Jogos, Notes…)
- API HTTP (JWT/mobile) → `Api/` (fora do Painel)
- Manifests PWA → `PWA/` (fora do Painel)
- Endpoints públicos sem login → `Publico/` (ex.: validar histórico)
- Layout HTML chrome → `Views/layouts/`
