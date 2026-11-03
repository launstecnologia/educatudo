<?php
$list = $list ?? [];
$schema_ready = $schema_ready ?? false;
$seriesPorCurso = is_array($series_por_curso ?? null) ? $series_por_curso : [];
$cursos = $cursos ?? [];
$csrf_token = $csrf_token ?? '';
$has_tipo_possui_serie = (bool) ($has_tipo_possui_serie ?? false);
$status = (string) ($status ?? '');
$message = (string) ($message ?? '');

$page_header_title = 'Cursos e Séries / Anos Escolares';
$page_header_subtitle = 'Séries e anos escolares de cada curso. Arraste para mudar a ordem.';
$trilhaItens = \App\Helpers\TrilhaAdmin::montar('/admin/cursos-series', 'Cursos e Séries / Anos Escolares');
?>
<div class="mb-6">
    <?php include __DIR__ . '/../../../../../Views/layouts/components/trilha.php'; ?>
    <div class="mt-4 flex flex-wrap justify-between items-center gap-4">
        <div class="flex items-center gap-3 min-w-0">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary text-white">
                <i class="fa-solid fa-graduation-cap text-lg" aria-hidden="true"></i>
            </span>
            <div class="min-w-0">
                <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($page_header_title) ?></h2>
                <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($page_header_subtitle) ?></p>
            </div>
        </div>
        <?php
        $podeVerLogCursosSeries = (($user['perfil_admin'] ?? '') === 'dev');
        if ($schema_ready || $podeVerLogCursosSeries):
        ?>
        <div class="flex items-center justify-end gap-3 flex-shrink-0 ml-auto">
            <?php if ($podeVerLogCursosSeries): ?>
            <?php
            $ui_btn_variant = 'complementar';
            $ui_btn_label = 'Log';
            $ui_btn_icon = 'fa-solid fa-clock-rotate-left';
            $ui_btn_onclick = 'abrirTrilhaCursosSeries()';
            include \App\Core\ViewPaths::adminPartial('ui/btn.php');
            ?>
            <?php endif; ?>
            <?php if ($schema_ready): ?>
            <button type="button" onclick="openSerieDrawer()"
               class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                <i class="fa-solid fa-plus mr-2"></i>
                Nova Série / Ano Escolar
            </button>
            <button type="button" onclick="openCursoDrawer()"
               class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                <i class="fa-solid fa-plus mr-2"></i>
                Novo Curso
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php

$flash_status = $status;
$flash_message = $message;
include \App\Core\ViewPaths::adminPartial('flash_message.php');
?>

<?php if (!$schema_ready): ?>
<div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg text-sm">
    Estrutura ainda não disponível. Execute as migrations 022 a 026 no banco do tenant.
</div>
<?php elseif (empty($list)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-500">
    <i class="fa-solid fa-graduation-cap text-4xl text-gray-300 mb-4"></i>
    <p>Nenhum curso cadastrado</p>
    <button type="button" onclick="openCursoDrawer()"
       class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
        <i class="fa-solid fa-plus mr-2"></i>
        Cadastrar o primeiro curso
    </button>
</div>
<?php else: ?>
<div class="space-y-4" data-curso-lista>
    <?php foreach ($list as $row):
        $cid = (int) ($row['id'] ?? 0);
        $tipo = $row['tipo'] ?? 'regular';
        $tipoLabel = $tipo === 'extra' ? 'Extra' : 'Regular';
        $tipoBadge = $tipo === 'extra' ? 'bg-indigo-100 text-indigo-800' : 'bg-blue-100 text-blue-800';
        $seriesDoCurso = $seriesPorCurso[$cid] ?? [];
        $importLabel = $tipo === 'extra' ? 'Importar / vincular' : 'Importar alunos';
        $corCurso = strtolower(trim((string) ($row['cor'] ?? '')));
        if (!preg_match('/^#[0-9a-f]{6}$/', $corCurso)) {
            $corCurso = '';
        }
        ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200" data-curso-id="<?= $cid ?>">
        <div class="px-4 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-gray-100">
            <div class="flex items-start gap-2 min-w-0">
                <span data-curso-arraste draggable="true" role="button" tabindex="0"
                      class="mt-0.5 inline-flex h-8 w-8 shrink-0 cursor-grab items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600 active:cursor-grabbing"
                      aria-label="Arrastar <?= htmlspecialchars((string) ($row['nome'] ?? 'curso'), ENT_QUOTES, 'UTF-8') ?> para reordenar"
                      title="Arraste para reordenar">
                    <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
                </span>
                <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <?php if ($corCurso !== ''): ?>
                    <span class="inline-block h-3 w-3 rounded-full border border-black/10" style="background:<?= htmlspecialchars($corCurso, ENT_QUOTES, 'UTF-8') ?>"></span>
                    <?php endif; ?>
                    <h3 class="text-base font-semibold text-gray-900"><?= htmlspecialchars((string) ($row['nome'] ?? '')) ?></h3>
                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full <?= $tipoBadge ?>"><?= $tipoLabel ?></span>
                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full <?= !empty($row['ativo']) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                        <?= !empty($row['ativo']) ? 'Ativo' : 'Inativo' ?>
                    </span>
                </div>
                <?php $totalSeries = (int) ($row['total_series'] ?? count($seriesDoCurso)); ?>
                <p class="text-sm text-gray-500 mt-1"><?= $totalSeries === 1 ? '1 série / ano escolar' : ($totalSeries . ' séries / anos escolares') ?></p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <?php if ($tipo !== 'extra'): ?>
                <button type="button" onclick="openSerieDrawer(null, <?= $cid ?>)"
                        class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-plus mr-2"></i> Nova série / ano escolar
                </button>
                <?php endif; ?>
                <?php ob_start(); ?>
                <button type="button" onclick="openCursoDrawer(<?= $cid ?>)"
                   class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar curso
                </button>
                <a href="<?= URL ?>/admin/curso/<?= $cid ?>/importar-alunos"
                   class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-file-import text-gray-400 w-4 text-center"></i> <?= htmlspecialchars($importLabel) ?>
                </a>
                <?php
                $row_actions_dropdown_items = ob_get_clean();
                $row_actions_dropdown_id = 'row-actions-curso-' . $cid;
                include \App\Core\ViewPaths::adminPartial('row_actions_dropdown.php');
                ?>
            </div>
        </div>
        <?php if ($seriesDoCurso === []): ?>
        <p class="px-6 py-4 text-sm text-gray-500">Nenhuma série / ano escolar neste curso.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-12 px-3 py-3" scope="col"><span class="sr-only">Reordenar</span></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Série / Ano Escolar</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" data-serie-lista data-curso-id="<?= $cid ?>">
                    <?php foreach ($seriesDoCurso as $serie): ?>
                    <tr class="hover:bg-gray-50" data-serie-id="<?= (int) $serie['id'] ?>">
                        <td class="px-3 py-3">
                            <span data-serie-arraste draggable="true" role="button" tabindex="0"
                                  class="inline-flex h-8 w-8 cursor-grab items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600 active:cursor-grabbing"
                                  aria-label="Arrastar <?= htmlspecialchars((string) ($serie['nome'] ?? 'série'), ENT_QUOTES, 'UTF-8') ?> para reordenar"
                                  title="Arraste para reordenar">
                                <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
                            </span>
                        </td>
                        <td class="px-6 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($serie['nome'] ?? '')) ?></td>
                        <td class="px-6 py-3">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= !empty($serie['ativo']) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= !empty($serie['ativo']) ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td class="px-6 py-3">
                            <?php ob_start(); ?>
                            <button type="button" onclick="openSerieDrawer(<?= (int) $serie['id'] ?>)"
                               class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                            </button>
                            <?php
                            $row_actions_dropdown_items = ob_get_clean();
                            $row_actions_dropdown_id = 'row-actions-serie-' . (int) $serie['id'];
                            include \App\Core\ViewPaths::adminPartial('row_actions_dropdown.php');
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$somente_drawers = true;
include __DIR__ . '/../curso/index.php';
include \App\Core\ViewPaths::painel('Admin', 'Views/serie/index.php');
?>
<script>
(function () {
    var tokenEl = document.querySelector('#serie-form input[name="_token"]');
    var token = tokenEl ? tokenEl.value : '';
    var url = <?= json_encode(URL . '/admin/serie/reordenar') ?>;
    var arrastando = null;
    var ordemAntes = '';
    var salvando = false;
    var pendente = null;

    function idsDaLista(tbody) {
        return Array.prototype.map.call(tbody.querySelectorAll('tr[data-serie-id]'), function (tr) {
            return tr.getAttribute('data-serie-id');
        });
    }

    function salvar(tbody) {
        var ids = idsDaLista(tbody);
        if (salvando) {
            pendente = tbody;
            return;
        }
        salvando = true;
        tbody.setAttribute('aria-busy', 'true');
        var body = new FormData();
        body.append('_token', token);
        body.append('curso_id', tbody.getAttribute('data-curso-id') || '');
        ids.forEach(function (id) { body.append('ids[]', id); });
        fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    window.alert(data.error || 'Não foi possível salvar a ordem.');
                    window.location.reload();
                }
            })
            .catch(function () {
                window.alert('Erro de conexão. A ordem não foi salva.');
                window.location.reload();
            })
            .finally(function () {
                salvando = false;
                tbody.removeAttribute('aria-busy');
                if (pendente) {
                    var proxima = pendente;
                    pendente = null;
                    salvar(proxima);
                }
            });
    }

    document.querySelectorAll('[data-serie-lista]').forEach(function (tbody) {
        tbody.addEventListener('dragstart', function (e) {
            var alca = e.target.closest('[data-serie-arraste]');
            if (!alca || !tbody.contains(alca)) {
                e.preventDefault();
                return;
            }
            arrastando = alca.closest('tr');
            if (!arrastando) return;
            ordemAntes = idsDaLista(tbody).join(',');
            arrastando.classList.add('opacity-50');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', arrastando.getAttribute('data-serie-id') || '');
            }
        });

        tbody.addEventListener('dragover', function (e) {
            if (!arrastando || arrastando.parentNode !== tbody) return;
            e.preventDefault();
            var alvo = e.target.closest('tr');
            if (!alvo || alvo === arrastando || alvo.parentNode !== tbody) return;
            var rect = alvo.getBoundingClientRect();
            var depois = (e.clientY - rect.top) > (rect.height / 2);
            if (depois) {
                if (alvo.nextSibling !== arrastando) tbody.insertBefore(arrastando, alvo.nextSibling);
            } else if (alvo !== arrastando.nextSibling) {
                tbody.insertBefore(arrastando, alvo);
            }
        });

        tbody.addEventListener('drop', function (e) {
            e.preventDefault();
        });

        tbody.addEventListener('dragend', function () {
            if (!arrastando) return;
            var lista = arrastando.parentNode;
            arrastando.classList.remove('opacity-50');
            var mudou = lista && idsDaLista(lista).join(',') !== ordemAntes;
            arrastando = null;
            ordemAntes = '';
            if (mudou) salvar(lista);
        });

        tbody.querySelectorAll('[data-serie-arraste]').forEach(function (alca) {
            alca.addEventListener('keydown', function (e) {
                if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
                var linha = alca.closest('tr');
                if (!linha) return;
                var vizinha = e.key === 'ArrowUp' ? linha.previousElementSibling : linha.nextElementSibling;
                if (!vizinha) return;
                e.preventDefault();
                if (e.key === 'ArrowUp') tbody.insertBefore(linha, vizinha);
                else tbody.insertBefore(vizinha, linha);
                alca.focus();
                salvar(tbody);
            });
        });
    });
})();

(function () {
    var lista = document.querySelector('[data-curso-lista]');
    if (!lista) return;
    var tokenEl = document.querySelector('#curso-form input[name="_token"]');
    var token = tokenEl ? tokenEl.value : '';
    var url = <?= json_encode(URL . '/admin/curso/reordenar') ?>;
    var arrastando = null;
    var ordemAntes = '';
    var salvando = false;
    var pendente = false;

    function idsDaLista() {
        return Array.prototype.map.call(lista.querySelectorAll(':scope > [data-curso-id]'), function (card) {
            return card.getAttribute('data-curso-id');
        });
    }

    function salvar() {
        var ids = idsDaLista();
        if (salvando) {
            pendente = true;
            return;
        }
        salvando = true;
        lista.setAttribute('aria-busy', 'true');
        var body = new FormData();
        body.append('_token', token);
        ids.forEach(function (id) { body.append('ids[]', id); });
        fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    window.alert(data.error || 'Não foi possível salvar a ordem.');
                    window.location.reload();
                }
            })
            .catch(function () {
                window.alert('Erro de conexão. A ordem não foi salva.');
                window.location.reload();
            })
            .finally(function () {
                salvando = false;
                lista.removeAttribute('aria-busy');
                if (pendente) {
                    pendente = false;
                    salvar();
                }
            });
    }

    lista.addEventListener('dragstart', function (e) {
        var alca = e.target.closest('[data-curso-arraste]');
        if (!alca || !lista.contains(alca)) return;
        arrastando = alca.closest('[data-curso-id]');
        if (!arrastando || arrastando.parentNode !== lista) return;
        ordemAntes = idsDaLista().join(',');
        arrastando.classList.add('opacity-60');
        if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', arrastando.getAttribute('data-curso-id') || '');
        }
    });

    lista.addEventListener('dragover', function (e) {
        if (!arrastando || arrastando.parentNode !== lista) return;
        e.preventDefault();
        var alvo = e.target.closest('[data-curso-id]');
        if (!alvo || alvo === arrastando || alvo.parentNode !== lista) return;
        var rect = alvo.getBoundingClientRect();
        var depois = (e.clientY - rect.top) > (rect.height / 2);
        if (depois) {
            if (alvo.nextElementSibling !== arrastando) lista.insertBefore(arrastando, alvo.nextElementSibling);
        } else if (alvo !== arrastando.nextElementSibling) {
            lista.insertBefore(arrastando, alvo);
        }
    });

    lista.addEventListener('drop', function (e) {
        if (arrastando) e.preventDefault();
    });

    lista.addEventListener('dragend', function () {
        if (!arrastando) return;
        arrastando.classList.remove('opacity-60');
        var mudou = idsDaLista().join(',') !== ordemAntes;
        arrastando = null;
        ordemAntes = '';
        if (mudou) salvar();
    });

    lista.querySelectorAll('[data-curso-arraste]').forEach(function (alca) {
        alca.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
            var card = alca.closest('[data-curso-id]');
            if (!card || card.parentNode !== lista) return;
            var vizinho = e.key === 'ArrowUp' ? card.previousElementSibling : card.nextElementSibling;
            if (!vizinho) return;
            e.preventDefault();
            if (e.key === 'ArrowUp') lista.insertBefore(card, vizinho);
            else lista.insertBefore(vizinho, card);
            alca.focus();
            salvar();
        });
    });
})();
</script>
<?php if (($user['perfil_admin'] ?? '') === 'dev'): ?>
<?php
$trilha_log_id = 'trilhaCursosSeries';
$trilha_log_titulo = 'Log de cursos e séries';
$trilha_log_url = URL . '/admin/cursos-series/logs';
$trilha_log_vazio = 'Nenhum cadastro, alteração ou exclusão de curso ou série registrado.';
include \App\Core\ViewPaths::adminPartial('ui/trilha_log.php');
?>
<?php endif; ?>
