/**
 * SEC-02 — config MathJax v3 sem script inline (layouts aluno/professor/admin).
 * Carregar ANTES de tex-svg.js.
 */
window.MathJax = {
  tex: {
    inlineMath: [['$', '$'], ['\\(', '\\)']],
    displayMath: [['$$', '$$'], ['\\[', '\\]']],
    processEscapes: true
  },
  svg: {
    fontCache: 'global'
  }
};
