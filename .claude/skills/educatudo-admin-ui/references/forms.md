# Offcanvas — Cadastro/Edição (form único) e Filtro

Pré-requisito: ler `prompts/admin_ui_sistema/cores-e-botoes.md` (cor, botões)
e `prompts/admin_ui_sistema/formularios-e-filtros.md` (inputs, select-reset),
além do `SKILL.md` desta skill (critério de quando usar offcanvas). Aqui só o
que é específico do padrão offcanvas.

## 1. Offcanvas de Cadastro/Edição — form único reaproveitado

Diferente do padrão de página própria do documento principal, aqui **um único
`<form>`** serve pra criar e editar. Painel desliza da direita, largura
generosa (`max-w-3xl`), fundo da página visível e escurecido atrás. Conteúdo
em **seções com título + divisória**, campos em **grid de 2 colunas**.

```html
<div id="xDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeXDrawer()"></div>
<aside id="xDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
  <!-- Header -->
  <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
    <h2 id="xDrawerTitle" class="text-xl font-bold text-gray-900">Novo Registro</h2>
    <button type="button" onclick="closeXDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
      <i class="fa-solid fa-xmark text-xl"></i>
    </button>
  </div>

  <form id="x-form" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" id="x_id" value="">
    <!-- só se o módulo usa PUT/DELETE via override — ver "armadilha do _method vazio" no SKILL.md -->
    <input type="hidden" name="_method" id="x_method" value="" disabled>

    <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
      <section>
        <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Dados Principais</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
          <div>
            <label for="x_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
            <input type="text" id="x_nome" name="nome" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
          </div>
          <!-- demais campos -->
        </div>
      </section>

      <!-- Seção só de um dos modos: id próprio, escondida/mostrada via JS -->
      <section id="x-edit-only-section" class="hidden border-t border-gray-200 pt-6">
        <h4 class="text-sm font-medium text-gray-900 mb-3">Informações do Sistema</h4>
        <!-- created_at / updated_at etc, só em modo editar -->
      </section>
    </div>

    <!-- Footer fixo -->
    <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
      <button type="button" onclick="closeXDrawer()"
              class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
        Cancelar
      </button>
      <button type="submit"
              class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
        <span id="x-form-submit-label">Salvar</span>
      </button>
    </div>
  </form>
</aside>
```

**Regras fixas:**
- Header com título + X de fechar à direita, sempre com borda inferior.
- Cada seção lógica = `<h3>` + linha divisória + grid de 2 colunas (`grid-cols-1 sm:grid-cols-2`, 1 coluna no mobile).
- Campo obrigatório: asterisco vermelho no label + `required`.
- Footer de ação **sempre fixo**, botões empilham no mobile (`flex-col-reverse sm:flex-row` — primário em cima).
- Focus ring dos campos de cadastro: `ring-green-500`/`border-green-500` (mesmo padrão do resto do sistema, ver documento principal §6).
- Campos `<select>` sempre com o reset cross-browser (ver documento principal §6, "Compatibilidade Safari").

### JS — alternar entre criar e editar no mesmo form

```js
function openXDrawer(id) {
    const form = document.getElementById('x-form');
    form.reset();
    document.getElementById('x_id').value = '';

    if (!id) {
        form.dataset.mode = 'create';
        document.getElementById('xDrawerTitle').textContent = 'Novo Registro';
        document.getElementById('x-form-submit-label').textContent = 'Salvar';
        document.getElementById('x-edit-only-section').classList.add('hidden');
        showXDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    document.getElementById('xDrawerTitle').textContent = 'Editar Registro';
    document.getElementById('x-form-submit-label').textContent = 'Salvar Alterações';
    document.getElementById('x-edit-only-section').classList.remove('hidden');
    showXDrawer();

    // Modo editar SEMPRE popula via fetch — nunca via data-* no botão nem página server-rendered
    fetch(`${URL_BASE}/admin/x/${id}/dados`)
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) { alert('Erro: ' + (data.error || '')); closeXDrawer(); return; }
            document.getElementById('x_id').value = data.item.id;
            document.getElementById('x_nome').value = data.item.nome;
            // ... demais campos
        })
        .catch(() => { alert('Erro de conexão.'); closeXDrawer(); });
}

document.getElementById('x-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const mode = this.dataset.mode;
    const id = document.getElementById('x_id').value;
    const url = mode === 'create' ? `${URL_BASE}/admin/x` : `${URL_BASE}/admin/x/${id}`;
    fetch(url, { method: 'POST', body: new FormData(this) })
        .then((r) => r.json())
        .then((result) => {
            if (result.success) { window.location.reload(); }
            else { alert('Erro: ' + result.error); }
        })
        .catch(() => alert('Erro de conexão. Tente novamente.'));
});
```

O endpoint `.../{id}/dados` no controller: mesma checagem de permissão/
ownership que a página de edição antiga fazia, retorna JSON (nunca
`viewWithLayout`). Referência real: `UsuarioController::dados()` em
`src/app/Controllers/User/UserController.php`.

## 2. Offcanvas de Filtro

Mais estreito que o de cadastro (`max-w-md`), campos empilhados (1 coluna),
botões de ação **sempre no rodapé fixo**: "Limpar" (outline) à esquerda,
"Aplicar filtros" (azul) à direita. Esse padrão é o mesmo do documento
principal §7 — só repetido aqui porque frequentemente aparece na mesma tela
que o offcanvas de cadastro.

```html
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
  <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
    <h3 class="text-lg font-semibold text-gray-900">Filtrar Registros</h3>
    <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
      <i class="fa-solid fa-xmark text-xl"></i>
    </button>
  </div>
  <form method="GET" class="flex flex-col flex-1 overflow-hidden">
    <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
      <div>
        <label for="filtro_nome" class="block text-sm font-medium text-gray-700 mb-1.5">Nome</label>
        <input type="text" id="filtro_nome" name="nome"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
      </div>
    </div>
    <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
      <button type="button" onclick="clearFilters()" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Limpar</button>
      <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">Aplicar filtros</button>
    </div>
  </form>
</aside>
```

Ao aplicar, os valores refletem na query string da listagem e **persistem ao
paginar** (documento principal §8). Ao reabrir, os campos vêm pré-preenchidos
com `htmlspecialchars($filtro_atual)`/`selected`.

## 3. Escape fecha os drawers

```js
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeFilterDrawer();
        closeXDrawer();
    }
});
```
