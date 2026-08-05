# MathLive - Fontes

O arquivo `css/mathlive-static.css` referencia fontes em `fonts/` (relativo ao CSS).

Para ter as fontes localmente, execute no diretório `src/`:

```bash
npm install
cp -r node_modules/mathlive/fonts public/static/css/fonts
```

Ou baixe o pacote mathlive e copie a pasta `fonts` para `public/static/css/fonts/`.

Sem as fontes, o MathLive ainda funciona, mas pode usar fallback do sistema para exibir fórmulas.
