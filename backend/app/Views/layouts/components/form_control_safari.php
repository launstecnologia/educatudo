<?php
/**
 * Normalização cross-browser de inputs date/select.
 * Safari renderiza maior/diferente por padrão (seta dupla nativa no select,
 * fonte maior em date) — quebra a consistência visual de filtros/formulários.
 *
 * Incluir em todo layout que renderiza <select> ou <input type="date|time|datetime-local">.
 * Não repetir CSS por página: só usar classes Tailwind padrão nos campos.
 */
?>
<style>
/* Normalização Safari — date/time/select (global por layout) */
input[type="date"],
input[type="time"],
input[type="datetime-local"] {
    -webkit-appearance: none;
    appearance: none;
    font-family: inherit;
    font-size: 0.875rem;
    line-height: 1.25rem;
    min-height: 2.5rem;
}
input[type="date"]::-webkit-date-and-time-value {
    text-align: left;
}
select {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236b7280'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z' clip-rule='evenodd'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.6rem center;
    background-size: 1.1em;
    padding-right: 2.25rem;
}
</style>
