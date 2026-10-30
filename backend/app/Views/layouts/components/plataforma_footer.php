<?php
/**
 * Rodapé da plataforma (área principal).
 * Fica no fluxo do conteúdo — nunca position:fixed.
 *
 * Variáveis opcionais:
 * - $plataforma_footer_oculto (bool)
 */
$baseUrl = defined('URL') ? rtrim((string) URL, '/') : '';
$logoEducatudo = $baseUrl . '/assets/logos/logo-educatudo-black.png';
$logoLauns = $baseUrl . '/assets/logos/logo-launs-black.png';
$anoFooter = (int) date('Y');
$ocultarFooter = !empty($plataforma_footer_oculto);
?>
<?php if (!$ocultarFooter): ?>
<footer class="plataforma-footer" role="contentinfo">
    <div class="plataforma-footer-grid">
        <div class="plataforma-footer-esquerda">
            <img src="<?= htmlspecialchars($logoEducatudo) ?>" alt="Educatudo" class="plataforma-footer-logo-educatudo" width="104" height="22" loading="lazy">
            <span class="plataforma-footer-copy">© <?= $anoFooter ?> Educatudo. Todos os direitos reservados.</span>
        </div>

        <nav class="plataforma-footer-centro" aria-label="Documentos legais">
            <a href="<?= htmlspecialchars($baseUrl) ?>/termos-de-uso">Termos de Uso</a>
            <a href="<?= htmlspecialchars($baseUrl) ?>/politica-privacidade">Política de Privacidade</a>
            <a href="<?= htmlspecialchars($baseUrl) ?>/politica-retencao">Política de Retenção de Dados</a>
        </nav>

        <div class="plataforma-footer-direita">
            <span class="plataforma-footer-credito">Desenvolvido por</span>
            <a href="https://www.launs.com.br" target="_blank" rel="noopener noreferrer" class="plataforma-footer-launs-link" title="Launs">
                <img src="<?= htmlspecialchars($logoLauns) ?>" alt="Launs" class="plataforma-footer-logo-launs" width="78" height="18" loading="lazy">
            </a>
        </div>
    </div>
</footer>
<?php endif; ?>
<style>
.plataforma-pagina {
    display: flex;
    flex-direction: column;
    flex: 1 0 auto;
}

.plataforma-pagina-preenche {
    min-height: 100%;
}

.plataforma-pagina-tela {
    min-height: 100dvh;
}

.plataforma-pagina.plataforma-pagina-chat {
    flex: 1 1 0;
    min-height: 0;
    height: 100%;
    overflow: hidden;
}

.plataforma-conteudo {
    flex: 1 0 auto;
}

.plataforma-footer {
    flex-shrink: 0;
    margin-top: auto;
    background: #fff;
    border-top: 1px solid #e2e8f0;
    padding: 14px 28px;
    color: #64748b;
    font-size: 13px;
    line-height: 1.35;
}

.plataforma-footer-grid {
    width: 100%;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: center;
    gap: 16px 28px;
}

.plataforma-footer-esquerda,
.plataforma-footer-direita {
    display: flex;
    min-width: 0;
}

.plataforma-footer-esquerda {
    flex-direction: column;
    align-items: flex-start;
    justify-self: start;
    gap: 6px;
}

.plataforma-footer-copy {
    color: #94a3b8;
    font-size: 12px;
}

.plataforma-footer-direita {
    flex-direction: column;
    align-items: flex-end;
    justify-self: end;
    gap: 4px;
}

.plataforma-footer-credito {
    color: #94a3b8;
    font-size: 11px;
    letter-spacing: 0.01em;
}

.plataforma-footer-centro {
    justify-self: center;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 6px 0;
}

.plataforma-footer-centro a:not(:last-child)::after {
    content: "";
    display: inline-block;
    width: 3px;
    height: 3px;
    margin: 0 12px;
    border-radius: 999px;
    background: #cbd5e1;
    vertical-align: middle;
}

.plataforma-footer-logo-educatudo,
.plataforma-footer-logo-launs {
    display: block;
    width: auto;
    object-fit: contain;
    flex-shrink: 0;
}

.plataforma-footer-logo-educatudo {
    height: 22px;
    max-width: 120px;
}

.plataforma-footer-logo-launs {
    height: 18px;
    max-width: 88px;
}

.plataforma-footer a {
    color: #475569;
    text-decoration: none;
    transition: color 0.15s ease;
}

.plataforma-footer a:hover,
.plataforma-footer a:focus-visible {
    color: #0f172a;
}

.plataforma-footer a:focus-visible {
    outline: 2px solid #0b5ed7;
    outline-offset: 2px;
    border-radius: 2px;
}

.plataforma-footer-launs-link {
    display: inline-flex;
    align-items: center;
}

@media (max-width: 1023px) {
    .plataforma-footer-grid {
        grid-template-columns: 1fr 1fr;
    }

    .plataforma-footer-centro {
        grid-column: 1 / -1;
        order: 3;
    }
}

@media (max-width: 639px) {
    .plataforma-footer {
        padding: 16px 24px;
        min-height: 0;
    }

    .plataforma-footer-grid {
        grid-template-columns: 1fr;
        justify-items: center;
        text-align: center;
        gap: 12px;
    }

    .plataforma-footer-esquerda,
    .plataforma-footer-direita,
    .plataforma-footer-centro {
        justify-self: center;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
        order: 0;
        grid-column: auto;
    }
}
</style>
