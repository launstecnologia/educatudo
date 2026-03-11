# Modelo: overlay de loading ao enviar formulário

Quando precisar exibir um **loading no centro da tela** ao clicar em Salvar/Enviar/Atualizar (enquanto faz upload ou processa e a página recarrega), use este padrão.

---

## 1. CSS (adicionar no `<style>` ou no layout da página)

```css
#form-loading-overlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(255, 255, 255, 0.9);
  align-items: center;
  justify-content: center;
  flex-direction: column;
}
#form-loading-overlay.show {
  display: flex;
}
.form-loading-spinner {
  width: 56px;
  height: 56px;
  border: 4px solid #e5e7eb;
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: form-spin 0.9s linear infinite;
}
@keyframes form-spin {
  to { transform: rotate(360deg); }
}
```

- Troque o id `#form-loading-overlay` e a classe `.form-loading-spinner` se quiser nomes únicos por página (ex.: `#arquivos-loading-overlay`).

---

## 2. HTML do overlay (colocar após o `</form>`)

```html
<div id="form-loading-overlay" aria-hidden="true">
  <div class="form-loading-spinner"></div>
  <p class="mt-4 text-gray-700 font-medium">Salvando... Enviando arquivos.</p>
  <p class="mt-1 text-sm text-gray-500">Aguarde até a página recarregar.</p>
</div>
```

- Ajuste os textos conforme o contexto (ex.: "Atualizando...", "Enviando...", "Processando...").

---

## 3. JavaScript (no submit do formulário)

- Interceptar o `submit`, mostrar o overlay e em seguida enviar o formulário (para o overlay aparecer antes da navegação).

```javascript
var form = document.querySelector('form'); // ou seletor específico, ex: form[action*="salvar"]
var overlay = document.getElementById('form-loading-overlay');

if (form && overlay) {
  form.addEventListener('submit', function(e) {
    if (!form.dataset.submitting) {
      e.preventDefault();
      form.dataset.submitting = '1';
      overlay.classList.add('show');
      setTimeout(function() {
        form.submit();
      }, 80);
    }
  });
}
```

- Se houver campos que precisam ser atualizados antes do submit (ex.: editor Quill → input hidden), faça isso **antes** de `overlay.classList.add('show')` (ex.: `descricaoHidden.value = quill.root.innerHTML`).

---

## 4. Comportamento

- Ao clicar em **Salvar/Atualizar**, o overlay aparece no centro da tela com o spinner e as mensagens.
- O formulário é enviado logo em seguida (POST normal); o overlay some quando a nova página carrega (redirect do servidor).
- O `data-submitting` evita que o handler rode mais de uma vez se o submit for disparado de outro lugar.

---

## 5. Referência no projeto

- **Exemplo implementado:** `src/app/Views/teacher/arquivos/create.php` e `edit.php` (overlay ao Salvar/Atualizar publicação de arquivos).

Quando precisar em outra página, solicite: *"Coloca o loading overlay (modelo do prompt) na página X ao clicar em Salvar/Enviar"* e use este .md como referência.
