<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
  <div><h2 class="text-2xl font-bold text-slate-900">Comunicação Escolar</h2><p class="text-slate-500">Mensagens para responsáveis, separadas do mural dos alunos.</p></div>
  <div class="flex gap-2"><a href="<?= URL ?>/admin/calendario-escolar" class="px-4 py-2 rounded-lg border border-slate-300">Calendário</a><a href="<?= URL ?>/admin/comunicacao-escolar/nova" class="btn-primary-custom px-4 py-2 rounded-lg">Nova comunicação</a></div>
</div>
<div class="bg-white rounded-xl shadow overflow-hidden">
  <?php if(empty($items)): ?><p class="p-8 text-center text-slate-500">Nenhuma comunicação publicada.</p><?php else: ?>
  <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="text-left p-4">Comunicação</th><th class="text-left p-4">Público</th><th class="text-left p-4">Prioridade</th><th class="text-center p-4">Leituras</th><th class="text-center p-4">Respostas</th><th class="p-4"></th></tr></thead><tbody class="divide-y">
  <?php foreach($items as $item): ?><tr><td class="p-4"><strong><?= htmlspecialchars($item['titulo']) ?></strong><div class="text-xs text-slate-500"><?= date('d/m/Y H:i',strtotime($item['created_at'])) ?> · <?= (int)$item['attachment_count'] ?> anexo(s)</div></td><td class="p-4 capitalize"><?= htmlspecialchars($item['publico']) ?></td><td class="p-4 capitalize"><?= htmlspecialchars($item['prioridade']) ?></td><td class="p-4 text-center"><?= (int)$item['read_count'] ?></td><td class="p-4 text-center"><?= (int)$item['reply_count'] ?></td><td class="p-4 text-right"><a class="text-blue-600 font-medium" href="<?= URL ?>/admin/comunicacao-escolar/<?= (int)$item['id'] ?>">Abrir</a></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>
