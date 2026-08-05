<?php
$latest_songs = $latest_songs ?? [];
$csrf_token = $csrf_token ?? '';
$educahits_delete_song_url = trim((string) ($educahits_delete_song_url ?? ''));
$eh_format_data = static function (?string $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '' || $raw === '—') {
        return '—';
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return $raw;
    }
    return date('d/m/Y H:i', $ts);
};
require __DIR__ . '/_nav.php';
?>
<div class="mb-8 bg-white rounded-xl shadow border border-slate-200 p-6">
    <h2 class="text-lg font-semibold text-slate-900 mb-2">Catálogo / últimas músicas</h2>
    <p class="text-sm text-slate-600">Lista opcional via <code class="text-xs bg-slate-100 px-1 rounded">EDUCAHITS_MASTER_SONGS_API</code>.</p>
</div>

<div class="mb-8 bg-white rounded-xl shadow border border-slate-200 overflow-hidden">
    <?php if (empty($latest_songs)): ?>
    <p class="px-6 py-8 text-sm text-slate-600">Sem dados no momento. Configure <code class="text-xs bg-slate-100 px-1 rounded">EDUCAHITS_MASTER_SONGS_API</code> para carregar as últimas músicas.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Título</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Artista</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Álbum</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Escola</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Data</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Detalhes</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($latest_songs as $songIndex => $song): ?>
                <?php
                $songId = trim((string) ($song['id'] ?? $song['song_id'] ?? $song['songId'] ?? ''));
                $title = (string) ($song['title'] ?? $song['name'] ?? '—');
                $artist = (string) ($song['artist'] ?? $song['author'] ?? '—');
                $album = (string) ($song['album'] ?? '—');
                $school = (string) ($song['school_slug'] ?? $song['school'] ?? '—');
                $created = (string) ($song['created_at'] ?? $song['createdAt'] ?? $song['date'] ?? '—');
                $audioUrl = trim((string) ($song['audio_url'] ?? $song['audioUrl'] ?? $song['url'] ?? ''));
                $coverUrl = trim((string) ($song['cover_url'] ?? $song['coverUrl'] ?? ''));
                $songJson = json_encode($song, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                ?>
                <tr class="align-top">
                    <td class="px-4 py-2 text-slate-800 max-w-[12rem]"><span class="line-clamp-2" title="<?= htmlspecialchars($title) ?>"><?= htmlspecialchars($title) ?></span></td>
                    <td class="px-4 py-2 text-slate-700 max-w-[9rem]"><span class="line-clamp-2" title="<?= htmlspecialchars($artist) ?>"><?= htmlspecialchars($artist) ?></span></td>
                    <td class="px-4 py-2 text-slate-700 max-w-[9rem]"><span class="line-clamp-2" title="<?= htmlspecialchars($album) ?>"><?= htmlspecialchars($album) ?></span></td>
                    <td class="px-4 py-2 text-slate-700 max-w-[7rem]"><span class="line-clamp-2" title="<?= htmlspecialchars($school) ?>"><?= htmlspecialchars($school) ?></span></td>
                    <td class="px-4 py-2 text-slate-500 whitespace-nowrap tabular-nums"><?= htmlspecialchars($eh_format_data($created)) ?></td>
                    <td class="px-4 py-2 text-xs text-slate-700 min-w-[16rem]">
                        <details>
                            <summary class="cursor-pointer text-blue-700 hover:text-slate-900">Ver JSON</summary>
                            <pre class="mt-2 p-2 bg-slate-50 border rounded text-[11px] leading-relaxed max-h-40 overflow-auto whitespace-pre-wrap break-all"><?= htmlspecialchars((string) ($songJson ?? '{}')) ?></pre>
                        </details>
                    </td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        <?php
                        ob_start();
                        if ($audioUrl !== ''):
                        ?>
                        <a href="<?= htmlspecialchars($audioUrl) ?>" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <i class="fa-solid fa-music text-gray-400 w-4 text-center"></i> Áudio
                        </a>
                        <?php endif; ?>
                        <?php if ($coverUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($coverUrl) ?>" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <i class="fa-solid fa-image text-gray-400 w-4 text-center"></i> Capa
                        </a>
                        <?php endif; ?>
                        <?php if ($educahits_delete_song_url !== '' && $songId !== ''): ?>
                        <?php if ($audioUrl !== '' || $coverUrl !== ''): ?><div class="border-t border-slate-100 my-1"></div><?php endif; ?>
                        <form method="post" action="<?= URL ?>/master/educa-hits/delete-song" onsubmit="return confirm('Tem certeza que deseja apagar esta música?');">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="song_id" value="<?= htmlspecialchars($songId) ?>">
                            <input type="hidden" name="title" value="<?= htmlspecialchars($title) ?>">
                            <input type="hidden" name="artist" value="<?= htmlspecialchars($artist) ?>">
                            <input type="hidden" name="album" value="<?= htmlspecialchars($album) ?>">
                            <input type="hidden" name="school_slug" value="<?= htmlspecialchars($school) ?>">
                            <input type="hidden" name="audio_url" value="<?= htmlspecialchars($audioUrl) ?>">
                            <input type="hidden" name="cover_url" value="<?= htmlspecialchars($coverUrl) ?>">
                            <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Apagar
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        if (trim($row_actions_dropdown_items) === ''):
                        ?>
                        <span class="text-slate-400">—</span>
                        <?php else: ?>
                        <?php $row_actions_dropdown_id = 'row-actions-musica-' . (int) $songIndex; ?>
                        <?php include __DIR__ . '/../../admin/_partials/row_actions_dropdown.php'; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
