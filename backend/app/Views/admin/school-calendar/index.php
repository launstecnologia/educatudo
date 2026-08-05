<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
  <div><h2 class="text-2xl font-bold">Calendário escolar</h2><p class="text-slate-500">Eventos exibidos aos responsáveis no app.</p></div>
  <div class="flex gap-2"><a href="<?= URL ?>/admin/comunicacao-escolar" class="px-4 py-2 border rounded-lg">Comunicações</a><a href="<?= URL ?>/admin/calendario-escolar/novo" class="btn-primary-custom px-4 py-2 rounded-lg">Novo evento</a></div>
</div>
<div class="bg-white rounded-xl shadow overflow-hidden">
<?php if(!$events): ?><p class="p-8 text-center text-slate-500">Nenhum evento cadastrado.</p><?php else: ?><div class="divide-y">
<?php foreach($events as $e): ?><div class="p-5 flex flex-wrap justify-between gap-4">
  <div><div class="text-xs font-bold uppercase text-blue-600"><?= htmlspecialchars($e['categoria']) ?> · <?= htmlspecialchars($e['prioridade']) ?></div><h3 class="font-bold text-lg"><?= htmlspecialchars($e['titulo']) ?></h3><p class="text-sm text-slate-500"><?= date('d/m/Y H:i',strtotime($e['inicio_em'])) ?><?= $e['local']?' · '.htmlspecialchars($e['local']):'' ?> · <?= htmlspecialchars($e['publico']) ?></p></div>
  <div><span class="px-3 py-1 rounded-full text-sm <?= $e['status']==='cancelado'?'bg-red-100 text-red-700':'bg-green-100 text-green-700' ?>"><?= htmlspecialchars($e['status']) ?></span>
  <?php if($e['status']==='publicado'): ?><a class="ml-2 text-blue-600" href="<?= URL ?>/admin/calendario-escolar/<?= (int)$e['id'] ?>/editar">Editar</a><form method="post" action="<?= URL ?>/admin/calendario-escolar/<?= (int)$e['id'] ?>/cancelar" class="inline ml-2" onsubmit="return confirm('Cancelar evento e notificar os responsáveis?')"><input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>"><button class="text-red-600">Cancelar</button></form><?php endif; ?></div>
</div><?php endforeach; ?></div><?php endif; ?>
</div>
