<style>
.admin-filter-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px 16px;
    align-items: end;
}
@media (min-width: 640px) {
    .admin-filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .admin-filter-actions {
        grid-column: 1 / -1;
        justify-content: flex-end;
    }
}
@media (min-width: 1024px) {
    .admin-filter-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        row-gap: 16px;
        column-gap: 16px;
    }
    .admin-filter-actions {
        grid-column: 3 / -1;
        justify-content: flex-end;
        align-self: end;
    }
}
.admin-filter-field label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.375rem;
    white-space: nowrap;
}
.admin-filter-control {
    display: block;
    width: 100%;
    min-height: 42px;
    padding: 0.5625rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    color: #111827;
    background-color: #fff;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    box-sizing: border-box;
    -webkit-appearance: none;
    appearance: none;
    margin: 0;
}
.admin-filter-control:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
}
.admin-filter-select {
    padding-right: 2.5rem;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.65rem center;
    background-size: 1rem 1rem;
    cursor: pointer;
}
.admin-filter-date {
    min-width: 0;
    padding-right: 0.5rem;
}
.admin-filter-date::-webkit-date-and-time-value {
    text-align: left;
    min-height: 1.25rem;
    line-height: 1.25rem;
}
.admin-filter-date::-webkit-datetime-edit {
    padding: 0;
}
.admin-filter-date::-webkit-calendar-picker-indicator {
    opacity: 0.65;
    cursor: pointer;
    margin-left: 0.25rem;
}
.admin-filter-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}
.admin-filter-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    min-width: 5.5rem;
    padding: 0.5625rem 1.125rem;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.25rem;
    border-radius: 0.5rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
    white-space: nowrap;
    box-sizing: border-box;
    -webkit-appearance: none;
    appearance: none;
}
.admin-filter-btn-primary {
    background-color: #4f46e5;
    color: #fff;
}
.admin-filter-btn-primary:hover {
    background-color: #4338ca;
}
.admin-filter-btn-blue {
    background-color: #2563eb;
}
.admin-filter-btn-blue:hover {
    background-color: #1d4ed8;
}
.admin-filter-btn-secondary {
    background-color: #e5e7eb;
    color: #374151;
}
.admin-filter-btn-secondary:hover {
    background-color: #d1d5db;
}
</style>
