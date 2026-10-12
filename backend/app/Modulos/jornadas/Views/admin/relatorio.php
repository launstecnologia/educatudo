<?php
$reports_filter_form_action = URL . '/admin/jornadas/relatorio';
$reports_filter_clear_url = URL . '/admin/jornadas/relatorio';
$reports_filter_base_url = URL . '/admin/jornadas/relatorio';
?>
<div class="mb-8">
    <div class="flex flex-wrap justify-between items-start gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Relatório de Jornadas</h2>
            <p class="text-gray-600">Acompanhamento pedagógico por aluno × jornada (conclusão, pendências e tempo).</p>
        </div>
        <a href="<?= URL ?>/admin/jornadas"
           class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 text-sm font-medium">
            ← Voltar às jornadas
        </a>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="p-6">
        <?php require __DIR__ . '/../reports/_tab_jornadas.php'; ?>
    </div>
</div>

<style>
.reports-filter-wrap input[type="date"],
.reports-filter-wrap input[type="search"],
.reports-filter-wrap input[type="text"],
.reports-filter-wrap select {
    -webkit-appearance: none;
    appearance: none;
    width: 100%;
    min-height: 44px;
    line-height: 1.25;
    font-size: 16px;
    border-radius: 10px;
    background-color: #fff;
    box-sizing: border-box;
}
.reports-filter-wrap select {
    padding-right: 2.5rem;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 20 20'%3E%3Cpath d='M5.5 7.5 10 12l4.5-4.5' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 14px 14px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var tipo = document.querySelector('.reports-filter-tipo');
    if (!tipo) return;
    var turmaWrap = document.querySelector('.reports-filter-turma-wrap');
    var usuarioWrap = document.querySelector('.reports-filter-usuario-wrap');
    var materiaWrap = document.querySelector('.reports-filter-modo-materia-wrap');
    function syncTipo() {
        var v = tipo.value;
        if (turmaWrap) turmaWrap.style.display = v === 'turma' ? '' : 'none';
        if (usuarioWrap) usuarioWrap.style.display = v === 'usuario' ? '' : 'none';
        if (materiaWrap) materiaWrap.style.display = v === 'usuario' ? '' : 'none';
    }
    tipo.addEventListener('change', syncTipo);
    syncTipo();
});
</script>
