# Domínio (sempre ligado)

Negócio essencial da escola — **não** é feature opcional (Opção A).

```
Dominio/
  Pessoas/   Controllers, Models, Services  (aluno, professor, responsável, monitor, lista chamada…)
  Escola/    Controllers, Models, Services  (ano letivo, curso, série, turma, sala, BNCC, matriz…)
```

Regra: **sem código duplicado**. Uma classe vive num único arquivo dono.
Caminhos antigos (`Controllers/Admin`, `Painel/Admin/Controllers`, `Services/`, `Models/*`)
são só **shims** (`require` + `class_alias`) para autoload legado.

- Feature opcional → `Modulos/{slug}/`
- Shell de perfil (dashboard/layout) → `Painel/`
