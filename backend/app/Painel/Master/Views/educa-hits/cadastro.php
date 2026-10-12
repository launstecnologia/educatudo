<?php
$escolas = $escolas ?? [];
$flash = $flash ?? [];
$csrf_token = $csrf_token ?? '';
$flash_meta = (isset($flash['meta']) && is_array($flash['meta'])) ? $flash['meta'] : null;
$inputClass = 'w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500';
$labelClass = 'block text-sm font-medium text-slate-700 mb-2';
?>
<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg <?= (isset($flash['type']) && $flash['type'] === 'error') ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>
<?php if (!empty($flash_meta) && ($flash_meta['source'] ?? '') === 'educahits_deliver'): ?>
<script>
(function(){ window.__EDUCAHITS_DELIVER_DEBUG = <?= json_encode($flash_meta, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; })();
</script>
<?php endif; ?>

<?php require __DIR__ . '/_nav.php'; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 w-full overflow-hidden">
    <form method="post" action="<?= URL ?>/master/educa-hits/deliver" enctype="multipart/form-data" class="divide-y divide-slate-200">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Destino</h3>
                <p class="text-sm text-slate-500 mt-0.5">Escola que receberá a música entregue.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="escola_id" class="<?= $labelClass ?>">
                        Escola (tenant) <span class="text-red-500">*</span>
                    </label>
                    <select id="escola_id" name="escola_id" required class="<?= $inputClass ?>">
                        <option value="">Selecione…</option>
                        <option value="all">Todas as escolas ativas</option>
                        <?php foreach ($escolas as $e): ?>
                        <option value="<?= (int) ($e['id'] ?? 0) ?>"><?= htmlspecialchars(($e['nome'] ?? '') . ' (' . ($e['slug'] ?? '') . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1.5 text-xs text-slate-500">Em “Todas as escolas ativas”, o sistema tenta envio único global; se a API exigir slug, usa fallback por escola.</p>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Dados da música</h3>
                <p class="text-sm text-slate-500 mt-0.5">Informações que identificam a faixa no catálogo.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="title" class="<?= $labelClass ?>">
                        Título <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="title" name="title" required class="<?= $inputClass ?>" placeholder="Nome da música">
                </div>
                <div>
                    <label for="artist" class="<?= $labelClass ?>">
                        Artista <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="artist" name="artist" value="EducaHits" required class="<?= $inputClass ?>">
                </div>
                <div>
                    <label for="album" class="<?= $labelClass ?>">Álbum</label>
                    <input type="text" id="album" name="album" class="<?= $inputClass ?>" placeholder="Opcional">
                </div>
                <div>
                    <label for="duration" class="<?= $labelClass ?>">Duração (segundos)</label>
                    <input type="number" id="duration" name="duration" min="0" value="0" class="<?= $inputClass ?>">
                </div>
                <div>
                    <label for="subject" class="<?= $labelClass ?>">Matéria</label>
                    <input type="text" id="subject" name="subject" class="<?= $inputClass ?>" placeholder="Ex.: Matemática">
                </div>
                <div>
                    <label for="topic" class="<?= $labelClass ?>">Tema</label>
                    <input type="text" id="topic" name="topic" class="<?= $inputClass ?>" placeholder="Ex.: Frações">
                </div>
                <div class="md:col-span-2">
                    <label for="notes" class="<?= $labelClass ?>">Observações / notas</label>
                    <textarea id="notes" name="notes" rows="2" class="<?= $inputClass ?>" placeholder="Notas internas ou contexto da entrega"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label for="lyrics" class="<?= $labelClass ?>">Letra</label>
                    <textarea id="lyrics" name="lyrics" rows="4" class="<?= $inputClass ?>" placeholder="Opcional"></textarea>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Arquivos</h3>
                <p class="text-sm text-slate-500 mt-0.5">Áudio obrigatório; capa opcional.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="<?= $labelClass ?>">
                        Áudio <span class="text-red-500">*</span>
                    </label>
                    <div id="eh-drop-audio"
                         class="eh-dropzone relative flex flex-col items-center justify-center gap-2 px-4 py-8 border-2 border-dashed border-slate-300 rounded-xl bg-slate-50/50 hover:border-blue-400 hover:bg-blue-50/40 transition-colors cursor-pointer"
                         data-input="audio"
                         data-accept="audio"
                         tabindex="0"
                         role="button"
                         aria-label="Selecionar ou soltar arquivo de áudio">
                        <input id="audio" name="audio" type="file" accept=".mp3,.m4a,.wav,.ogg,audio/*" required class="sr-only">
                        <div class="eh-drop-idle text-center pointer-events-none">
                            <i class="fa-solid fa-cloud-arrow-up text-2xl text-slate-400 mb-2" aria-hidden="true"></i>
                            <p class="text-sm text-slate-600">
                                <span class="font-medium text-blue-600">Clique para selecionar</span>
                                <span class="text-slate-500"> ou arraste e solte</span>
                            </p>
                            <p class="mt-1 text-xs text-slate-500">MP3, M4A, WAV, OGG</p>
                        </div>
                        <div class="eh-drop-file hidden w-full text-center pointer-events-none">
                            <i class="fa-solid fa-file-audio text-2xl text-blue-600 mb-2" aria-hidden="true"></i>
                            <p class="eh-drop-name text-sm font-medium text-slate-800 truncate px-2"></p>
                            <p class="eh-drop-size text-xs text-slate-500 mt-0.5"></p>
                        </div>
                    </div>
                    <button type="button" class="eh-drop-clear hidden mt-2 text-xs text-red-600 hover:text-red-700" data-clear="audio">Remover arquivo</button>
                </div>
                <div>
                    <label class="<?= $labelClass ?>">Capa</label>
                    <div id="eh-drop-cover"
                         class="eh-dropzone relative flex flex-col items-center justify-center gap-2 px-4 py-8 border-2 border-dashed border-slate-300 rounded-xl bg-slate-50/50 hover:border-blue-400 hover:bg-blue-50/40 transition-colors cursor-pointer"
                         data-input="cover"
                         data-accept="image"
                         tabindex="0"
                         role="button"
                         aria-label="Selecionar ou soltar imagem de capa">
                        <input id="cover" name="cover" type="file" accept=".jpg,.jpeg,.png,.webp,image/*" class="sr-only">
                        <div class="eh-drop-idle text-center pointer-events-none">
                            <i class="fa-solid fa-image text-2xl text-slate-400 mb-2" aria-hidden="true"></i>
                            <p class="text-sm text-slate-600">
                                <span class="font-medium text-blue-600">Clique para selecionar</span>
                                <span class="text-slate-500"> ou arraste e solte</span>
                            </p>
                            <p class="mt-1 text-xs text-slate-500">JPG, PNG, WebP</p>
                        </div>
                        <div class="eh-drop-file hidden w-full text-center pointer-events-none">
                            <img class="eh-drop-preview mx-auto mb-2 h-16 w-16 rounded-lg object-cover border border-slate-200 hidden" alt="Preview da capa">
                            <i class="fa-solid fa-file-image eh-drop-icon text-2xl text-blue-600 mb-2" aria-hidden="true"></i>
                            <p class="eh-drop-name text-sm font-medium text-slate-800 truncate px-2"></p>
                            <p class="eh-drop-size text-xs text-slate-500 mt-0.5"></p>
                        </div>
                    </div>
                    <button type="button" class="eh-drop-clear hidden mt-2 text-xs text-red-600 hover:text-red-700" data-clear="cover">Remover arquivo</button>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-slate-50/80 border-t border-slate-200">
            <div class="flex flex-wrap justify-end gap-3">
                <a href="<?= URL ?>/master/educa-hits/musicas"
                   class="inline-flex items-center justify-center px-6 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors">
                    Cancelar
                </a>
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-sm">
                    <i class="fa-solid fa-paper-plane text-xs" aria-hidden="true"></i>
                    Enviar para o EducaHits
                </button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    function formatBytes(n) {
        if (!n && n !== 0) return '';
        if (n < 1024) return n + ' B';
        if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
        return (n / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function isAccepted(file, kind) {
        if (!file) return false;
        var name = (file.name || '').toLowerCase();
        var type = (file.type || '').toLowerCase();
        if (kind === 'audio') {
            return type.indexOf('audio/') === 0
                || /\.(mp3|m4a|wav|ogg)$/.test(name);
        }
        return type.indexOf('image/') === 0
            || /\.(jpe?g|png|webp)$/.test(name);
    }

    function setFile(zone, file) {
        var inputId = zone.getAttribute('data-input');
        var input = document.getElementById(inputId);
        if (!input) return;
        var kind = zone.getAttribute('data-accept');
        if (file && !isAccepted(file, kind)) {
            alert(kind === 'audio'
                ? 'Selecione um áudio (MP3, M4A, WAV ou OGG).'
                : 'Selecione uma imagem (JPG, PNG ou WebP).');
            return;
        }
        if (file) {
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
        } else {
            input.value = '';
        }
        updateUI(zone, file || null);
    }

    function updateUI(zone, file) {
        var idle = zone.querySelector('.eh-drop-idle');
        var selected = zone.querySelector('.eh-drop-file');
        var nameEl = zone.querySelector('.eh-drop-name');
        var sizeEl = zone.querySelector('.eh-drop-size');
        var clearBtn = document.querySelector('.eh-drop-clear[data-clear="' + zone.getAttribute('data-input') + '"]');
        var preview = zone.querySelector('.eh-drop-preview');
        var icon = zone.querySelector('.eh-drop-icon');

        if (file) {
            if (idle) idle.classList.add('hidden');
            if (selected) selected.classList.remove('hidden');
            if (nameEl) nameEl.textContent = file.name;
            if (sizeEl) sizeEl.textContent = formatBytes(file.size);
            if (clearBtn) clearBtn.classList.remove('hidden');
            zone.classList.add('border-blue-500', 'bg-blue-50/50');
            zone.classList.remove('border-slate-300');
            if (preview && file.type && file.type.indexOf('image/') === 0) {
                var url = URL.createObjectURL(file);
                preview.src = url;
                preview.classList.remove('hidden');
                if (icon) icon.classList.add('hidden');
            } else if (preview) {
                preview.classList.add('hidden');
                preview.removeAttribute('src');
                if (icon) icon.classList.remove('hidden');
            }
        } else {
            if (idle) idle.classList.remove('hidden');
            if (selected) selected.classList.add('hidden');
            if (clearBtn) clearBtn.classList.add('hidden');
            zone.classList.remove('border-blue-500', 'bg-blue-50/50');
            zone.classList.add('border-slate-300');
            if (preview) {
                preview.classList.add('hidden');
                preview.removeAttribute('src');
            }
            if (icon) icon.classList.remove('hidden');
        }
    }

    document.querySelectorAll('.eh-dropzone').forEach(function (zone) {
        var inputId = zone.getAttribute('data-input');
        var input = document.getElementById(inputId);

        zone.addEventListener('click', function (e) {
            if (e.target.closest('input')) return;
            if (input) input.click();
        });
        zone.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (input) input.click();
            }
        });
        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.add('border-blue-500', 'bg-blue-50');
        });
        zone.addEventListener('dragleave', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!input || !input.files || !input.files.length) {
                zone.classList.remove('border-blue-500', 'bg-blue-50');
            }
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('bg-blue-50');
            var files = e.dataTransfer && e.dataTransfer.files;
            if (files && files.length) setFile(zone, files[0]);
        });
        if (input) {
            input.addEventListener('change', function () {
                setFile(zone, input.files && input.files[0] ? input.files[0] : null);
            });
        }
    });

    document.querySelectorAll('.eh-drop-clear').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-clear');
            var zone = document.getElementById('eh-drop-' + id);
            if (zone) setFile(zone, null);
        });
    });
})();
</script>
