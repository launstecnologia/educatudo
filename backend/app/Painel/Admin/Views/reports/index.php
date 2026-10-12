<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Relatórios
            </h2>
            <p class="text-gray-600">
                Preencha os filtros e clique em aplicar para carregar os resultados de Jornadas.
            </p>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="p-6">
        <?php require __DIR__ . '/_tab_jornadas.php'; ?>
    </div>
</div>

<style>
/* Ajustes de ergonomia para Safari/iOS (evita zoom automático e melhora alvo de toque) */
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

.reports-filter-wrap input[type="date"] {
    padding-right: 0.75rem;
}

.reports-filter-wrap input[type="date"]::-webkit-calendar-picker-indicator {
    opacity: 1;
    cursor: pointer;
}

.reports-filter-wrap input[type="checkbox"] {
    -webkit-appearance: none;
    appearance: none;
    width: 18px;
    height: 18px;
    border: 1.5px solid #9ca3af;
    border-radius: 4px;
    background: #fff;
    position: relative;
    vertical-align: middle;
}

.reports-filter-wrap input[type="checkbox"]:checked {
    background: #7c3aed;
    border-color: #7c3aed;
}

.reports-filter-wrap input[type="checkbox"]:checked::after {
    content: "";
    position: absolute;
    left: 5px;
    top: 1px;
    width: 5px;
    height: 10px;
    border: solid #fff;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.reports-filter-wrap input:focus,
.reports-filter-wrap select:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.18);
    border-color: #8b5cf6 !important;
}

@supports (-webkit-touch-callout: none) {
    .reports-filter-wrap input[type="date"],
    .reports-filter-wrap input[type="text"],
    .reports-filter-wrap select {
        height: 44px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.reports-filter-wrap').forEach(function (wrap) {
        const tipoSelect = wrap.querySelector('.reports-filter-tipo');
        const turmaFilter = wrap.querySelector('.reports-filter-turma-wrap');
        const usuarioFilter = wrap.querySelector('.reports-filter-usuario-wrap');
        const modoMateriaFilter = wrap.querySelector('.reports-filter-modo-materia-wrap');
        if (!tipoSelect || !turmaFilter || !usuarioFilter) {
            return;
        }
        function updateFilterVisibility() {
            const tipo = tipoSelect.value;
            if (tipo === 'turma') {
                turmaFilter.style.display = '';
                usuarioFilter.style.display = 'none';
                if (modoMateriaFilter) modoMateriaFilter.style.display = 'none';
            } else if (tipo === 'usuario') {
                turmaFilter.style.display = 'none';
                usuarioFilter.style.display = '';
                if (modoMateriaFilter) modoMateriaFilter.style.display = '';
            } else {
                turmaFilter.style.display = 'none';
                usuarioFilter.style.display = 'none';
                if (modoMateriaFilter) modoMateriaFilter.style.display = 'none';
            }
        }
        tipoSelect.addEventListener('change', updateFilterVisibility);
        updateFilterVisibility();
    });
});
</script>
