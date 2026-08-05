<?php
$notes_url = $notes_url ?? '';
$base_url  = $base_url ?? (defined('URL') ? URL : '');
?>
<div class="notes-modal-overlay" id="notes-modal-overlay">
    <div class="notes-modal-container">
        <div class="notes-modal-header">
            <span class="notes-modal-title">Meu Caderno (Novo)</span>
            <a href="<?= htmlspecialchars($base_url) ?>/caderno" class="notes-modal-close" id="notes-modal-close">Fechar</a>
        </div>
        <iframe
            src="<?= htmlspecialchars($notes_url) ?>"
            class="notes-modal-iframe"
            title="Meu Caderno (Novo)"
            allow="fullscreen"
        ></iframe>
    </div>
</div>
<style>
.notes-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 100;
    background: #0f172a;
    display: flex;
    flex-direction: column;
}
.notes-modal-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
}
.notes-modal-header {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: #1e293b;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.notes-modal-title {
    font-weight: 600;
    color: #fff;
}
.notes-modal-close {
    padding: 0.5rem 1rem;
    background: #334155;
    color: #e2e8f0;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
}
.notes-modal-close:hover {
    background: #475569;
    color: #fff;
}
.notes-modal-iframe {
    flex: 1;
    width: 100%;
    border: none;
    min-height: 0;
}
</style>
