<?php for ($i = 0; $i < 6; $i++): ?>
<div class="jornada-card-skeleton flex h-full min-h-[18rem] flex-col rounded-xl border border-slate-100 bg-white p-6 shadow-lg animate-pulse" aria-hidden="true">
    <div class="mb-4 flex min-h-[7.5rem] items-start justify-between">
        <div class="min-w-0 flex-1 space-y-3 pr-3">
            <div class="h-6 rounded-md bg-slate-200 w-[88%]"></div>
            <div class="h-4 rounded-md bg-slate-100 w-[62%]"></div>
            <div class="h-3 rounded-md bg-slate-100 w-[48%]"></div>
        </div>
        <div class="h-6 w-24 shrink-0 rounded-full bg-slate-200"></div>
    </div>
    <div class="mb-4 space-y-2">
        <div class="flex justify-between gap-2">
            <div class="h-3 w-20 rounded bg-slate-100"></div>
            <div class="h-3 w-12 rounded bg-slate-100"></div>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
            <div class="h-full w-2/5 rounded-full bg-slate-200"></div>
        </div>
    </div>
    <div class="mt-auto space-y-2">
        <div class="h-4 rounded bg-slate-100 w-full"></div>
        <div class="h-11 rounded-lg bg-slate-200"></div>
    </div>
</div>
<?php endfor; ?>
