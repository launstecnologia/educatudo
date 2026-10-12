<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Dispositivos Faciais</h1>
        <p class="mt-1 text-slate-600">Gere um código temporário para conectar o tablet da portaria ao Colag.</p>
    </div>

    <?php if (empty($schema_ready)): ?>
        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-900">
            Execute a migration <strong>067_facial_devices.sql</strong> para habilitar o pareamento.
        </div>
    <?php else: ?>
        <div class="grid gap-6 lg:grid-cols-[.8fr_1.2fr]">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-100 text-blue-700"><i class="fa-solid fa-link"></i></div>
                    <div><h2 class="font-bold text-slate-900">Código de pareamento</h2><p class="text-sm text-slate-500">Uso único, válido por 10 minutos.</p></div>
                </div>
                <?php if (!empty($pairing_code)): ?>
                    <div class="my-6 rounded-2xl border-2 border-dashed border-blue-300 bg-blue-50 px-5 py-7 text-center">
                        <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Informe este código no tablet</p>
                        <p id="pairing-code" class="mt-2 font-mono text-5xl font-black tracking-[.18em] text-blue-950"><?= htmlspecialchars((string) $pairing_code) ?></p>
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('pairing-code').textContent.trim())" class="mt-4 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm">Copiar código</button>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= URL ?>/admin/settings/facial-devices/pairing-code" class="mt-6">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <button class="btn-primary-custom w-full rounded-xl px-5 py-3 font-bold hover:opacity-90"><i class="fa-solid fa-key mr-2"></i>Gerar novo código</button>
                </form>
                <p class="mt-4 text-xs text-slate-500">Ao gerar um novo código, qualquer código anterior ainda não utilizado será invalidado.</p>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5"><h2 class="font-bold text-slate-900">Tablets pareados</h2><p class="text-sm text-slate-500"><?= count($devices) ?> dispositivo(s)</p></div>
                <?php if (empty($devices)): ?>
                    <div class="px-6 py-12 text-center text-slate-500"><i class="fa-solid fa-tablet-screen-button mb-3 text-4xl text-slate-300"></i><p>Nenhum tablet pareado.</p></div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($devices as $device): ?>
                            <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                                <div><p class="font-semibold text-slate-900"><?= htmlspecialchars($device['name']) ?></p><p class="text-xs text-slate-500"><?= htmlspecialchars($device['device_uid']) ?> · Pareado em <?= date('d/m/Y H:i', strtotime($device['paired_at'])) ?></p><p class="mt-1 text-xs text-slate-500">Último acesso: <?= !empty($device['last_seen_at']) ? date('d/m/Y H:i', strtotime($device['last_seen_at'])) : 'nunca' ?></p></div>
                                <?php if (!empty($device['enabled'])): ?>
                                    <form method="post" action="<?= URL ?>/admin/settings/facial-devices/<?= (int) $device['id'] ?>/disable" onsubmit="return confirm('Desvincular este dispositivo?')"><input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>"><button class="rounded-xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Desvincular</button></form>
                                <?php else: ?><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">Desvinculado</span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
</div>
