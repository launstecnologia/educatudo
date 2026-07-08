# Mensagens Flash, Abas e Ficha Detalhada

> Parte de `prompts/admin_ui_sistema.md`. Ler o índice lá antes se ainda não leu.

## 9. Mensagens flash e alertas

```html
<!-- Sucesso -->
<div class="mb-6 p-4 rounded-lg bg-green-100 text-green-800 border border-green-200">...</div>

<!-- Erro -->
<div class="mb-6 p-4 rounded-lg bg-red-100 text-red-800 border border-red-200">...</div>

<!-- Info contextual (inline) -->
<p class="text-sm text-blue-800 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2">...</p>

<!-- Aviso -->
<p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">...</p>
```

---

## 10. Abas / pills (hub multi-fluxo)

Ex.: Movimentação de alunos (`_movimentacao_form.php`):

```html
<div class="flex flex-wrap gap-2 text-sm">
    <a href="..." class="px-3 py-1 rounded-full bg-blue-600 text-white">Aba ativa</a>
    <a href="..." class="px-3 py-1 rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200">Aba inativa</a>
</div>
```

---

## 11. Ficha detalhada (opcional — aluno)

Para telas de **detalhe** ricas, o módulo aluno usa classes locais em `show.php`:

- `.student-card` — card com `border-radius: 16px`, borda `#e2e8f0`
- `.student-field-label` — uppercase pequeno `#64748b`
- `.student-field-value` — valor em negrito `#0f172a`

Use esse padrão só em fichas complexas; listagens e CRUD simples usam Tailwind puro como acima.
