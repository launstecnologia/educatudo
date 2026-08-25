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
            <img src="<?= htmlspecialchars($logoEducatudo) ?>" alt="Educatudo" class="plataforma-footer-logo-educatudo" width="92" height="20" loading="lazy">
            <span>© <?= $anoFooter ?> Educatudo. Todos os direitos reservados.</span>
        </div>

        <nav class="plataforma-footer-centro" aria-label="Documentos legais">
            <a href="<?= htmlspecialchars($baseUrl) ?>/termos-de-uso">Termos de Uso</a>
            <a href="<?= htmlspecialchars($baseUrl) ?>/politica-privacidade">Política de Privacidade</a>
            <a href="<?= htmlspecialchars($baseUrl) ?>/politica-retencao">Política de Retenção de Dados</a>
        </nav>

        <div class="plataforma-footer-direita">
            <span>Desenvolvido por</span>
            <a href="https://www.launs.com.br" target="_blank" rel="noopener noreferrer" class="plataforma-footer-launs-link" title="Launs">
                <img src="<?= htmlspecialchars($logoLauns) ?>" alt="Launs" class="plataforma-footer-logo-launs" width="72" height="16" loading="lazy">
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
    background: #F8FAFC;
    border-top: 1px solid #E2E8F0;
    min-height: 64px;
    padding: 12px 24px;
    color: #64748B;
    font-size: 12.5px;
    line-height: 1.4;
}

.plataforma-footer-grid {
    width: 100%;
    min-height: 40px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: center;
    gap: 12px 24px;
}

.plataforma-footer-esquerda,
.plataforma-footer-direita {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.plataforma-footer-esquerda {
    justify-self: start;
}

.plataforma-footer-direita {
    justify-self: end;
}

.plataforma-footer-centro {
    justify-self: center;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 8px 18px;
}

.plataforma-footer-logo-educatudo,
.plataforma-footer-logo-launs {
    display: block;
    width: auto;
    max-width: 110px;
    object-fit: contain;
    flex-shrink: 0;
}

.plataforma-footer-logo-educatudo {
    height: 20px;
}

.plataforma-footer-logo-launs {
    height: 16px;
}

.plataforma-footer a {
    color: #64748B;
    text-decoration: none;
    transition: color 0.15s ease;
}

.plataforma-footer a:hover,
.plataforma-footer a:focus-visible {
    color: #0b5ed7;
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
        flex-wrap: wrap;
        order: 0;
        grid-column: auto;
    }
}
</style>
