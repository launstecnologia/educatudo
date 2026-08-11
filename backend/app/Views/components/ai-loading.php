<?php
/**
 * Padrão de loading / botão de IA — usa a cor da escola (navbar / primary_color).
 *
 * Uso do modal (overlay):
 *   $aiLoadingTitle = 'Gerando Exercícios';
 *   $aiLoadingMessage = 'A IA está criando exercícios personalizados para você...';
 *   $aiLoadingStatus = 'Aguarde enquanto processamos sua solicitação...';
 *   include __DIR__ . '/../../components/ai-loading.php';
 *
 * Uso inline (página dedicada, sem overlay):
 *   $aiLoadingVariant = 'inline';
 *   $aiLoadingTitle = 'Gerando seus flashcards...';
 *   include __DIR__ . '/../../components/ai-loading.php';
 *
 * API JS: EducaAiLoading.show(), .hide(), .setProgress(n), .setStatus(t),
 *         .startFakeProgress(), .stopFakeProgress(), .setButtonLoading(btn, bool)
 *
 * Botão: inclua também components/ai-btn-primary.php
 */
$aiLoadingVariant = $aiLoadingVariant ?? 'modal';
$aiLoadingId = $aiLoadingId ?? 'aiLoadingModal';
$aiLoadingTitle = $aiLoadingTitle ?? 'Gerando...';
$aiLoadingMessage = $aiLoadingMessage ?? 'A IA está processando sua solicitação...';
$aiLoadingStatus = $aiLoadingStatus ?? 'Aguarde...';
$aiLoadingClosable = array_key_exists('aiLoadingClosable', get_defined_vars())
    ? (bool) $aiLoadingClosable
    : ($aiLoadingVariant === 'modal');
$aiLoadingIncludeAssets = $aiLoadingIncludeAssets ?? true;
$aiLoadingSkipMarkup = $aiLoadingSkipMarkup ?? false;
?>
<?php if ($aiLoadingIncludeAssets && !defined('EDUCATUDO_AI_LOADING_CSS')): ?>
<?php define('EDUCATUDO_AI_LOADING_CSS', true); ?>
<style>
    .btn-ai-primary {
        /* Cor base também no LayoutHelper; estados de loading ficam aqui */
        background-color: var(--button-primary-color, var(--primary-color, #a855f7));
        color: var(--primary-text-color, #ffffff);
        transition: filter 0.2s ease, transform 0.2s ease, opacity 0.2s ease;
    }
    .btn-ai-primary:hover:not(:disabled) {
        filter: brightness(0.92);
    }
    .btn-ai-primary:disabled {
        opacity: 0.75;
        cursor: not-allowed;
        transform: none !important;
    }
    .btn-ai-primary .btn-ai-loading {
        display: none;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .btn-ai-primary.is-loading .btn-ai-label {
        display: none;
    }
    .btn-ai-primary.is-loading .btn-ai-loading {
        display: inline-flex;
    }

    .ai-loading-spinner {
        position: relative;
        width: 5rem;
        height: 5rem;
    }
    .ai-loading-spinner::before,
    .ai-loading-spinner::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 9999px;
        border: 4px solid transparent;
    }
    .ai-loading-spinner::before {
        border-color: color-mix(in srgb, var(--primary-color, #a855f7) 22%, white);
    }
    .ai-loading-spinner::after {
        border-color: var(--primary-color, #a855f7);
        border-top-color: transparent;
        animation: ai-loading-spin 0.85s linear infinite;
    }
    @keyframes ai-loading-spin {
        to { transform: rotate(360deg); }
    }

    .ai-loading-progress-track {
        width: 100%;
        background: #e5e7eb;
        border-radius: 9999px;
        height: 0.75rem;
        overflow: hidden;
    }
    .ai-loading-progress-bar {
        height: 100%;
        width: 0%;
        border-radius: 9999px;
        background: linear-gradient(
            90deg,
            var(--primary-color, #a855f7),
            color-mix(in srgb, var(--primary-color, #a855f7) 65%, white)
        );
        transition: width 0.45s ease;
    }
</style>
<?php endif; ?>

<?php if (!$aiLoadingSkipMarkup && $aiLoadingVariant === 'inline'): ?>
<div id="<?= htmlspecialchars($aiLoadingId) ?>" class="ai-loading-inline text-center" data-ai-loading="inline" role="status" aria-live="polite">
    <div class="flex justify-center mb-6">
        <div class="ai-loading-spinner" aria-hidden="true"></div>
    </div>
    <h1 class="ai-loading-title text-2xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($aiLoadingTitle) ?></h1>
    <p class="ai-loading-message text-gray-500 text-sm mb-6"><?= htmlspecialchars($aiLoadingMessage) ?></p>
    <div class="ai-loading-progress-track mb-4 max-w-sm mx-auto">
        <div class="ai-loading-progress-bar" data-ai-progress style="width: 0%"></div>
    </div>
    <p class="ai-loading-status text-sm text-gray-500" data-ai-status><?= htmlspecialchars($aiLoadingStatus) ?></p>
</div>
<?php elseif (!$aiLoadingSkipMarkup): ?>
<div id="<?= htmlspecialchars($aiLoadingId) ?>" class="ai-loading-modal hidden fixed inset-0 z-50 overflow-y-auto" data-ai-loading="modal" role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars($aiLoadingId) ?>-title">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50" data-ai-loading-backdrop<?= $aiLoadingClosable ? '' : ' data-no-close="1"' ?>></div>
        <div class="relative bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full transform transition-all">
            <?php if ($aiLoadingClosable): ?>
            <button type="button" data-ai-loading-close class="absolute top-4 right-4 text-gray-400 hover:text-gray-600" aria-label="Fechar">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            <?php endif; ?>
            <div class="text-center">
                <div class="flex justify-center mb-6">
                    <div class="ai-loading-spinner" aria-hidden="true"></div>
                </div>
                <h3 id="<?= htmlspecialchars($aiLoadingId) ?>-title" class="ai-loading-title text-2xl font-bold text-gray-900 mb-4"><?= htmlspecialchars($aiLoadingTitle) ?></h3>
                <p class="ai-loading-message text-gray-600 mb-6"><?= htmlspecialchars($aiLoadingMessage) ?></p>
                <div class="ai-loading-progress-track mb-4">
                    <div class="ai-loading-progress-bar" data-ai-progress style="width: 0%"></div>
                </div>
                <p class="ai-loading-status text-sm text-gray-500" data-ai-status><?= htmlspecialchars($aiLoadingStatus) ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($aiLoadingIncludeAssets && !defined('EDUCATUDO_AI_LOADING_JS')): ?>
<?php define('EDUCATUDO_AI_LOADING_JS', true); ?>
<script>
(function (global) {
    if (global.EducaAiLoading) {
        return;
    }

    var progressTimer = null;
    var activeRoot = null;

    function resolveRoot(id) {
        if (id) {
            return document.getElementById(id);
        }
        if (activeRoot && document.body.contains(activeRoot)) {
            return activeRoot;
        }
        return document.querySelector('[data-ai-loading]');
    }

    function qs(root, sel) {
        return root ? root.querySelector(sel) : null;
    }

    function EducaAiLoading() {}

    EducaAiLoading.show = function (opts) {
        opts = opts || {};
        var root = resolveRoot(opts.id);
        if (!root) return null;
        activeRoot = root;

        if (opts.title) {
            EducaAiLoading.setTitle(opts.title, root);
        }
        if (opts.message) {
            EducaAiLoading.setMessage(opts.message, root);
        }
        if (opts.status) {
            EducaAiLoading.setStatus(opts.status, root);
        }

        EducaAiLoading.setProgress(opts.progress != null ? opts.progress : 0, root);

        if (root.getAttribute('data-ai-loading') === 'modal') {
            root.classList.remove('hidden');
        }

        if (opts.fakeProgress !== false && opts.fakeProgress !== 0) {
            EducaAiLoading.startFakeProgress(opts.fakeProgress === true ? undefined : opts.fakeProgress);
        }

        return root;
    };

    EducaAiLoading.hide = function (id) {
        var root = resolveRoot(id);
        EducaAiLoading.stopFakeProgress();
        if (!root) return;
        if (root.getAttribute('data-ai-loading') === 'modal') {
            root.classList.add('hidden');
        }
        EducaAiLoading.setProgress(0, root);
    };

    EducaAiLoading.setTitle = function (text, rootOrId) {
        var root = typeof rootOrId === 'string' || rootOrId == null ? resolveRoot(rootOrId) : rootOrId;
        var el = qs(root, '.ai-loading-title');
        if (el) el.textContent = text;
    };

    EducaAiLoading.setMessage = function (text, rootOrId) {
        var root = typeof rootOrId === 'string' || rootOrId == null ? resolveRoot(rootOrId) : rootOrId;
        var el = qs(root, '.ai-loading-message');
        if (el) el.textContent = text;
    };

    EducaAiLoading.setStatus = function (text, rootOrId) {
        var root = typeof rootOrId === 'string' || rootOrId == null ? resolveRoot(rootOrId) : rootOrId;
        var el = qs(root, '[data-ai-status]');
        if (el) el.textContent = text;
    };

    EducaAiLoading.setProgress = function (pct, rootOrId) {
        var root = typeof rootOrId === 'string' || rootOrId == null ? resolveRoot(rootOrId) : rootOrId;
        var bar = qs(root, '[data-ai-progress]');
        if (!bar) return;
        var n = Math.max(0, Math.min(100, Number(pct) || 0));
        bar.style.width = n + '%';
    };

    EducaAiLoading.startFakeProgress = function (opts) {
        opts = opts || {};
        EducaAiLoading.stopFakeProgress();
        var max = opts.max != null ? opts.max : 95;
        var interval = opts.interval != null ? opts.interval : 300;
        var current = 0;
        var root = resolveRoot(opts.id);

        progressTimer = setInterval(function () {
            if (current < max) {
                current += Math.random() * 10;
                if (current > max) current = max;
                EducaAiLoading.setProgress(current, root);
            }
        }, interval);
    };

    EducaAiLoading.stopFakeProgress = function () {
        if (progressTimer) {
            clearInterval(progressTimer);
            progressTimer = null;
        }
    };

    EducaAiLoading.setButtonLoading = function (btn, loading) {
        if (!btn) return;
        if (loading) {
            btn.classList.add('is-loading');
            btn.disabled = true;
        } else {
            btn.classList.remove('is-loading');
            btn.disabled = false;
        }
    };

    EducaAiLoading.bind = function (rootOrId) {
        var root = typeof rootOrId === 'string' || rootOrId == null ? resolveRoot(rootOrId) : rootOrId;
        if (!root || root.__aiLoadingBound) return;
        root.__aiLoadingBound = true;

        var closeBtn = qs(root, '[data-ai-loading-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                EducaAiLoading.hide(root.id);
            });
        }

        var backdrop = qs(root, '[data-ai-loading-backdrop]');
        if (backdrop && !backdrop.getAttribute('data-no-close')) {
            backdrop.addEventListener('click', function () {
                EducaAiLoading.hide(root.id);
            });
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-ai-loading]').forEach(function (el) {
            EducaAiLoading.bind(el);
        });
    });

    // Bind imediato caso o script rode após o DOMContentLoaded
    if (document.readyState !== 'loading') {
        document.querySelectorAll('[data-ai-loading]').forEach(function (el) {
            EducaAiLoading.bind(el);
        });
    }

    global.EducaAiLoading = EducaAiLoading;
}(window));
</script>
<?php endif; ?>
<?php
unset(
    $aiLoadingVariant,
    $aiLoadingId,
    $aiLoadingTitle,
    $aiLoadingMessage,
    $aiLoadingStatus,
    $aiLoadingClosable,
    $aiLoadingIncludeAssets,
    $aiLoadingSkipMarkup
);
?>
