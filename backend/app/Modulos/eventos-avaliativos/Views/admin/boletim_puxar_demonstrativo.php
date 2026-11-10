<?php
$ano = (int) ($ano ?? date('Y'));
$boletimId = (int) ($boletim_id ?? 0);
$boletimNome = (string) ($boletim_nome ?? 'Boletim');
$demonstrativos = is_array($demonstrativos ?? null) ? $demonstrativos : [];
$csrf_token = (string) ($csrf_token ?? '');

$page_header_back_url = URL . '/admin/eventos-avaliativos/boletim?ano=' . $ano;
$page_header_back_label = 'Voltar ao boletim';
$page_header_title = 'Puxar do Demonstrativo';
$page_header_subtitle = $boletimNome . ' · ' . $ano;
$page_header_icon = 'fa-solid fa-file-circle-check';
include \App\Core\ViewPaths::adminPartial('page_header_form.php');
include \App\Core\ViewPaths::adminPartial('flash_message.php');
?>

<div class="bg-white rounded-xl shadow-lg p-6 w-full">
    <p class="text-sm text-gray-600 mb-6 max-w-3xl">
        Copia as colunas e os cálculos de um Demonstrativo de notas para a visão de impressão deste boletim.
        Depois você pode remover colunas, deixar só o resultado final ou acrescentar novos blocos.
    </p>

    <?php if ($demonstrativos === []): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 mb-6">
        Nenhum Demonstrativo de notas encontrado para <?= (int) $ano ?>.
        Cadastre um em <a class="underline font-semibold" href="<?= URL ?>/admin/boletins">Demonstrativo de notas</a>
        ou comece o quadro do zero.
    </div>
    <a href="<?= URL ?>/admin/eventos-avaliativos/boletim/<?= $boletimId ?>/montar-quadro"
       class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold">
        Montar quadro do zero
    </a>
    <?php else: ?>
    <form method="POST"
          action="<?= URL ?>/admin/eventos-avaliativos/boletim/<?= $boletimId ?>/puxar-demonstrativo"
          class="space-y-5">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2" for="regra-notas-id">Demonstrativo de notas</label>
            <select name="regra_notas_id" id="regra-notas-id" required
                    class="w-full max-w-xl pl-4 py-2.5 border border-gray-300 rounded-lg bg-white text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Selecione…</option>
                <?php foreach ($demonstrativos as $demo): ?>
                    <?php
                    $demoId = (int) ($demo['id'] ?? 0);
                    $demoNome = (string) ($demo['nome'] ?? '');
                    $bim = (int) ($demo['bimestre'] ?? 0);
                    $extra = $bim > 0 ? (' · ' . $bim . 'º período') : '';
                    ?>
                <option value="<?= $demoId ?>"><?= htmlspecialchars($demoNome . $extra, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button type="submit"
                    class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold shadow-sm hover:opacity-90">
                <i class="fa-solid fa-file-import mr-2" aria-hidden="true"></i>
                Puxar colunas e abrir o quadro
            </button>
            <a href="<?= URL ?>/admin/eventos-avaliativos/boletim/<?= $boletimId ?>/montar-quadro"
               class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Preferir começar do zero
            </a>
        </div>
    </form>
    <?php endif; ?>
</div>
