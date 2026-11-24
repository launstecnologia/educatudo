<?php
$dashVisivel = static function (string $chave): bool {
    return \App\Dominio\Escola\Services\MontagemDashboardService::visivel('professor', $chave);
};
$dashOrdemCustom = \App\Dominio\Escola\Services\MontagemDashboardService::ordemPersonalizada('professor');
$abrirBlocoDash = static function (string $chave) use ($dashOrdemCustom): void {
    if ($dashOrdemCustom) {
        $ordem = \App\Dominio\Escola\Services\MontagemDashboardService::posicao('professor', $chave);
        echo '<div style="order:' . (int) $ordem . '">';
    }
};
$fecharBlocoDash = static function () use ($dashOrdemCustom): void {
    if ($dashOrdemCustom) {
        echo '</div>';
    }
};
?>
<?php if (!empty($grupo_seb_ativo)): ?>
<?php $primaryHex = (string) \App\Core\LayoutHelper::get('primary_color', '#3b82f6'); ?>
<div class="mb-6 md:mb-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-sm sm:text-base text-gray-600 mt-1">Calendário escolar, aulas de hoje e desempenho das turmas.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 sm:justify-end">
            <a href="<?= URL ?>/professor/grade-horaria" class="text-sm font-semibold hover:underline whitespace-nowrap" style="color:<?= htmlspecialchars($primaryHex) ?>">Minha grade →</a>
            <div class="bg-white border border-gray-200 rounded-lg px-3 py-2 shadow-sm text-right">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Código do professor</p>
                <p class="text-base font-semibold" style="color:<?= htmlspecialchars($primaryHex) ?>"><?= htmlspecialchars((string) ($professor['codigo_prof'] ?? '')) ?></p>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="mb-6 md:mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex-1">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Painel do Professor</h1>
            <p class="text-sm md:text-base text-gray-600 mt-2">Bem-vindo, <?= htmlspecialchars($professor['nome']) ?>! Gerencie suas turmas e acompanhe o progresso dos alunos.</p>
        </div>
        <div class="flex items-center gap-4">
            <a href="<?= URL ?>/professor/grade-horaria"
               class="inline-flex items-center px-4 py-2.5 rounded-lg bg-primary text-primary text-sm font-semibold hover:opacity-90 whitespace-nowrap">
                <i class="fa-regular fa-calendar-days mr-2"></i>
                Minha grade
            </a>
            <div class="text-right">
                <p class="text-xs md:text-sm text-gray-500">Código do Professor</p>
                <p class="text-base md:text-lg font-semibold text-blue-600"><?= htmlspecialchars($professor['codigo_prof']) ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($dashOrdemCustom): ?><div class="flex flex-col"><?php endif; ?>
<?php if (!empty($grupo_seb_ativo)): ?>
<?php
$aulasHoje = is_array($aulas_do_dia_professor ?? null) ? $aulas_do_dia_professor : [];
$desempenho = is_array($desempenho_turmas ?? null) ? $desempenho_turmas : ['media_geral' => null, 'turmas' => []];
$linhasDesempenho = is_array($desempenho['turmas'] ?? null) ? $desempenho['turmas'] : [];
$mediaGeral = isset($desempenho['media_geral']) && $desempenho['media_geral'] !== null
    ? (float) $desempenho['media_geral']
    : null;
$tzPainel = new DateTimeZone('America/Sao_Paulo');
$agoraPainel = new DateTimeImmutable('now', $tzPainel);
$diasSemanaPainel = [1 => 'segunda-feira', 2 => 'terça-feira', 3 => 'quarta-feira', 4 => 'quinta-feira', 5 => 'sexta-feira', 6 => 'sábado', 7 => 'domingo'];
$rotuloHoje = ($diasSemanaPainel[(int) $agoraPainel->format('N')] ?? '') . ', ' . $agoraPainel->format('d/m');
$corMedia = static function ($media): string {
    if ($media === null) {
        return 'text-gray-400';
    }
    $nota = (float) $media;
    $bom = $nota > 10 ? 70 : 7;
    $regular = $nota > 10 ? 50 : 5;
    if ($nota >= $bom) {
        return 'text-green-600';
    }
    if ($nota >= $regular) {
        return 'text-amber-600';
    }
    return 'text-red-600';
};
$mostrarAulasHoje = $dashVisivel('aulas_hoje');
$mostrarDesempenhoProf = $dashVisivel('desempenho');
$gradeSeb = !$dashOrdemCustom && ($mostrarAulasHoje || $mostrarDesempenhoProf);
?>
<?php if ($gradeSeb): ?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 md:gap-6 mb-6 md:mb-8">
<?php endif; ?>
<?php if ($dashVisivel('calendario')): ?>
<?php
$calPainel = is_array($calendario_painel ?? null) ? $calendario_painel : [];
$calAno = (int) ($calPainel['ano'] ?? date('Y'));
$calMes = (int) ($calPainel['mes'] ?? date('n'));
$calEventos = is_array($calPainel['eventos'] ?? null) ? $calPainel['eventos'] : [];
$calLabels = is_array($calPainel['labels'] ?? null) ? $calPainel['labels'] : [];
$calBg = is_array($calPainel['bg'] ?? null) ? $calPainel['bg'] : [];
$calText = is_array($calPainel['text'] ?? null) ? $calPainel['text'] : [];
$mesesPainel = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$eventosPorDia = [];
$eventosDoMes = [];
$tiposUsadosCal = [];
foreach ($calEventos as $evCal) {
    $inicioCal = strtotime((string) ($evCal['data_inicio'] ?? ''));
    $fimCal = strtotime((string) ($evCal['data_fim'] ?? ''));
    if (!$inicioCal) {
        continue;
    }
    if (!$fimCal || $fimCal < $inicioCal) {
        $fimCal = $inicioCal;
    }
    $tipoCal = (string) ($evCal['tipo'] ?? '');
    if ($tipoCal !== '') {
        $tiposUsadosCal[$tipoCal] = true;
    }
    $tituloCal = trim((string) ($evCal['descricao'] ?? $evCal['titulo'] ?? ''));
    if ($tituloCal === '') {
        $tituloCal = (string) ($calLabels[$tipoCal] ?? 'Evento');
    }
    $itemCal = [
        'tipo' => $tipoCal,
        'titulo' => $tituloCal,
        'local' => trim((string) ($evCal['local_evento'] ?? '')),
        'link' => trim((string) ($evCal['link_reuniao'] ?? '')),
        'minha' => !empty($evCal['minha_informacao']),
        'inicio' => date('Y-m-d', $inicioCal),
        'fim' => date('Y-m-d', $fimCal),
    ];
    $entraNoMes = false;
    for ($diaCal = $inicioCal; $diaCal <= $fimCal; $diaCal += 86400) {
        $eventosPorDia[date('Y-m-d', $diaCal)][] = $itemCal;
        if ((int) date('Y', $diaCal) === $calAno && (int) date('n', $diaCal) === $calMes) {
            $entraNoMes = true;
        }
    }
    if ($entraNoMes) {
        $eventosDoMes[] = $itemCal;
    }
}
$legendaCal = $tiposUsadosCal === [] ? $calLabels : array_intersect_key($calLabels, $tiposUsadosCal);
$primeiroDiaCal = mktime(0, 0, 0, $calMes, 1, $calAno);
$diasNoMesCal = (int) date('t', $primeiroDiaCal);
$offsetCal = (int) date('w', $primeiroDiaCal);
$hojeCal = date('Y-m-d');
$abrirBlocoDash('calendario');
?>
<section id="pf-cal" class="<?= $gradeSeb ? 'xl:col-span-2 ' : 'mb-6 md:mb-8 ' ?>bg-white rounded-2xl shadow-md overflow-hidden border border-gray-100"
         data-ano="<?= (int) $calAno ?>" data-mes="<?= (int) $calMes ?>" data-hoje="<?= htmlspecialchars($hojeCal) ?>" data-primary="<?= htmlspecialchars($primaryHex) ?>">
    <div class="px-5 sm:px-6 py-5 sm:py-6 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0 flex items-center gap-3">
            <span class="w-1.5 h-10 rounded-full flex-shrink-0" style="background:<?= htmlspecialchars($primaryHex) ?>"></span>
            <div>
                <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Calendário escolar</h2>
                <p class="text-sm text-gray-500 mt-1">Feriados, recessos e os seus lembretes</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= URL ?>/professor/calendario-letivo?evento=1" class="text-sm font-medium hover:underline whitespace-nowrap" style="color:<?= htmlspecialchars($primaryHex) ?>">Meu evento</a>
            <a href="<?= URL ?>/professor/calendario-letivo" class="text-sm font-medium hover:underline whitespace-nowrap" style="color:<?= htmlspecialchars($primaryHex) ?>">Ver completo →</a>
        </div>
    </div>
    <div class="px-5 sm:px-6 py-4 flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-1">
            <button type="button" id="pf-cal-prev" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-100" aria-label="Mês anterior">
                <svg class="w-4 h-4" fill="none" stroke="#6b7280" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
            </button>
            <span id="pf-cal-mes-label" class="text-sm font-semibold text-gray-800 min-w-[140px] text-center"><?= htmlspecialchars(($mesesPainel[$calMes - 1] ?? '') . ' de ' . $calAno) ?></span>
            <button type="button" id="pf-cal-next" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-100" aria-label="Próximo mês">
                <svg class="w-4 h-4" fill="none" stroke="#6b7280" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path></svg>
            </button>
        </div>
        <button type="button" id="pf-cal-hoje" class="px-2.5 py-1 text-xs font-medium rounded-md border border-gray-200 text-gray-600 bg-white hover:bg-gray-50">Hoje</button>
    </div>
    <?php if ($legendaCal !== []): ?>
    <div class="px-4 sm:px-5 pb-2 flex flex-wrap gap-1.5">
        <?php foreach ($legendaCal as $k => $v): ?>
        <span class="inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded-full" style="background:<?= htmlspecialchars((string) ($calBg[$k] ?? '#f3f4f6')) ?>;color:<?= htmlspecialchars((string) ($calText[$k] ?? '#374151')) ?>">
            <span class="w-1.5 h-1.5 rounded-full" style="background:<?= htmlspecialchars((string) ($calText[$k] ?? '#6b7280')) ?>"></span>
            <?= htmlspecialchars((string) $v) ?>
        </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="px-3 sm:px-4 pb-3">
        <div class="grid grid-cols-7 border-b border-gray-100">
            <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $siglaDia): ?>
            <div class="py-1.5 text-center text-[10px] font-semibold text-gray-400 uppercase"><?= $siglaDia ?></div>
            <?php endforeach; ?>
        </div>
        <div id="pf-cal-grade" class="grid grid-cols-7 gap-0.5 pt-0.5">
            <?php for ($vazio = 0; $vazio < $offsetCal; $vazio++): ?>
            <div class="h-9 sm:h-10"></div>
            <?php endfor; ?>
            <?php for ($diaNum = 1; $diaNum <= $diasNoMesCal; $diaNum++):
                $chaveDia = sprintf('%04d-%02d-%02d', $calAno, $calMes, $diaNum);
                $evsDia = $eventosPorDia[$chaveDia] ?? [];
                $tiposDia = array_values(array_unique(array_column($evsDia, 'tipo')));
                $primeiroTipo = (string) ($tiposDia[0] ?? '');
                $ehHoje = $chaveDia === $hojeCal;
                $fimSemana = (int) date('N', strtotime($chaveDia)) >= 6;
                if ($primeiroTipo !== '') {
                    $cellBg = (string) ($calBg[$primeiroTipo] ?? '#eef2ff');
                    $cellText = (string) ($calText[$primeiroTipo] ?? '#1e3a8a');
                } else {
                    $cellBg = 'transparent';
                    $cellText = $fimSemana ? '#9ca3af' : '#4b5563';
                }
            ?>
            <button type="button" class="pf-cal-dia relative h-9 sm:h-10 rounded-lg flex flex-col items-center justify-center gap-0.5 hover:bg-gray-50 focus:outline-none"
                    style="background:<?= htmlspecialchars($cellBg) ?>;<?= $ehHoje ? 'box-shadow:inset 0 0 0 1.5px ' . htmlspecialchars($primaryHex) . ';' : '' ?>"
                    data-data="<?= htmlspecialchars($chaveDia) ?>" aria-label="Dia <?= $diaNum ?>">
                <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-semibold rounded-full <?= $ehHoje ? 'text-white' : '' ?>"
                      style="color:<?= $ehHoje ? '#ffffff' : htmlspecialchars($cellText) ?>;<?= $ehHoje ? 'background-color:' . htmlspecialchars($primaryHex) : '' ?>"><?= $diaNum ?></span>
                <?php if ($tiposDia !== []): ?>
                <span class="flex gap-0.5 leading-none">
                    <?php foreach (array_slice($tiposDia, 0, 3) as $tipoDot): ?>
                    <span class="w-1 h-1 rounded-full" style="background:<?= htmlspecialchars((string) ($calText[$tipoDot] ?? '#6b7280')) ?>"></span>
                    <?php endforeach; ?>
                </span>
                <?php endif; ?>
            </button>
            <?php endfor; ?>
        </div>
    </div>
    <div class="px-4 sm:px-6 pb-5 border-t border-gray-100 bg-gray-50/60">
        <div class="pt-4 flex items-center justify-between gap-2 mb-3">
            <h3 class="text-sm font-semibold text-gray-800" id="pf-cal-dia-titulo">Eventos de <?= date('d/m/Y', strtotime($hojeCal)) ?></h3>
            <span class="text-[10px] sm:text-xs text-gray-400" id="pf-cal-dia-count"></span>
        </div>
        <div id="pf-cal-dia-lista" class="space-y-2 min-h-[3rem]">
            <?php $eventosHojeCal = $eventosPorDia[$hojeCal] ?? []; ?>
            <?php if ($eventosHojeCal === []): ?>
            <p class="text-sm text-gray-500">Nenhum evento escolar neste dia.</p>
            <?php else: ?>
                <?php foreach ($eventosHojeCal as $evHoje):
                    $tipoHoje = (string) ($evHoje['tipo'] ?? '');
                ?>
                <div class="flex items-start gap-3 p-3 bg-white rounded-xl border border-gray-100 shadow-sm">
                    <span class="w-2.5 h-2.5 rounded-full mt-1.5 flex-shrink-0" style="background:<?= htmlspecialchars((string) ($calText[$tipoHoje] ?? '#374151')) ?>"></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full" style="background:<?= htmlspecialchars((string) ($calBg[$tipoHoje] ?? '#f3f4f6')) ?>;color:<?= htmlspecialchars((string) ($calText[$tipoHoje] ?? '#374151')) ?>"><?= htmlspecialchars((string) ($calLabels[$tipoHoje] ?? $tipoHoje)) ?></span>
                            <?php if (!empty($evHoje['minha'])): ?><span class="text-[10px] text-gray-400">Meu lembrete</span><?php endif; ?>
                        </div>
                        <p class="text-sm font-semibold text-gray-900 mt-1"><?= htmlspecialchars((string) ($evHoje['titulo'] ?? '')) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="mt-5 pt-4 border-t border-gray-200">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Neste mês</h3>
            <ul id="pf-cal-mes-lista" class="space-y-2 max-h-48 overflow-y-auto pr-1">
                <?php if ($eventosDoMes === []): ?>
                <li class="text-sm text-gray-500"><?= $calEventos === [] ? 'Nenhum evento cadastrado no calendário letivo ainda.' : 'Nenhum evento neste mês.' ?></li>
                <?php else: ?>
                    <?php foreach (array_slice($eventosDoMes, 0, 8) as $evMes):
                        $tipoMes = (string) ($evMes['tipo'] ?? '');
                        $iniBr = date('d/m', strtotime((string) $evMes['inicio']));
                        $fimBr = date('d/m', strtotime((string) $evMes['fim']));
                        $periodoMes = $iniBr === $fimBr ? $iniBr : $iniBr . ' – ' . $fimBr;
                    ?>
                    <li class="flex items-center gap-3 text-sm">
                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:<?= htmlspecialchars((string) ($calText[$tipoMes] ?? '#6b7280')) ?>"></span>
                        <span class="text-xs font-medium text-gray-500 w-20 flex-shrink-0"><?= htmlspecialchars($periodoMes) ?></span>
                        <span class="text-gray-900 truncate font-medium"><?= htmlspecialchars((string) $evMes['titulo']) ?></span>
                    </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</section>
<script <?= $csp_nonce_attr ?? \App\Core\CspPolitica::attrNonce() ?>>
(function () {
    var root = document.getElementById('pf-cal');
    if (!root) return;
    var eventMap = <?= json_encode($eventosPorDia, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var eventosAno = <?= json_encode(array_values($eventosPorDia === [] ? [] : array_reduce($calEventos, static function ($acc, $ev) use ($calLabels) {
        $ini = strtotime((string) ($ev['data_inicio'] ?? ''));
        if (!$ini) {
            return $acc;
        }
        $fim = strtotime((string) ($ev['data_fim'] ?? ''));
        if (!$fim || $fim < $ini) {
            $fim = $ini;
        }
        $tipo = (string) ($ev['tipo'] ?? '');
        $titulo = trim((string) ($ev['descricao'] ?? $ev['titulo'] ?? ''));
        if ($titulo === '') {
            $titulo = (string) ($calLabels[$tipo] ?? 'Evento');
        }
        $acc[] = ['tipo' => $tipo, 'titulo' => $titulo, 'inicio' => date('Y-m-d', $ini), 'fim' => date('Y-m-d', $fim)];
        return $acc;
    }, [])), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var tipoLabels = <?= json_encode($calLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var tipoBg = <?= json_encode($calBg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var tipoText = <?= json_encode($calText, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var mesesNomes = <?= json_encode($mesesPainel, JSON_UNESCAPED_UNICODE) ?>;
    var primaryHex = root.dataset.primary || '#3b82f6';
    var hoje = root.dataset.hoje;
    var ano = parseInt(root.dataset.ano, 10);
    var mes = parseInt(root.dataset.mes, 10);
    var diaSelecionado = hoje;
    var elGrade = document.getElementById('pf-cal-grade');
    var elLabel = document.getElementById('pf-cal-mes-label');
    var elTitulo = document.getElementById('pf-cal-dia-titulo');
    var elLista = document.getElementById('pf-cal-dia-lista');
    var elCount = document.getElementById('pf-cal-dia-count');
    var elMesLista = document.getElementById('pf-cal-mes-lista');
    function pad(n) { return n < 10 ? '0' + n : String(n); }
    function iso(y, m, d) { return y + '-' + pad(m) + '-' + pad(d); }
    function br(isoDate) { var p = String(isoDate).split('-'); return p[2] + '/' + p[1] + '/' + p[0]; }
    function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function renderLista(dataIso) {
        diaSelecionado = dataIso;
        elTitulo.textContent = 'Eventos de ' + br(dataIso);
        var evs = eventMap[dataIso] || [];
        elCount.textContent = evs.length ? (evs.length === 1 ? '1 evento' : evs.length + ' eventos') : '';
        if (!evs.length) {
            elLista.innerHTML = '<p class="text-sm text-gray-500">Nenhum evento escolar neste dia.</p>';
            return;
        }
        elLista.innerHTML = evs.map(function (ev) {
            var tipo = ev.tipo || '';
            var bg = tipoBg[tipo] || '#f3f4f6';
            var tx = tipoText[tipo] || '#374151';
            var minha = ev.minha ? '<span class="text-[10px] text-gray-400">Meu lembrete</span>' : '';
            return '<div class="flex items-start gap-3 p-3 bg-white rounded-xl border border-gray-100 shadow-sm">' +
                '<span class="w-2.5 h-2.5 rounded-full mt-1.5 flex-shrink-0" style="background:' + esc(tx) + '"></span>' +
                '<div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2">' +
                '<span class="text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full" style="background:' + esc(bg) + ';color:' + esc(tx) + '">' + esc(tipoLabels[tipo] || tipo || 'Evento') + '</span>' +
                minha + '</div><p class="text-sm font-semibold text-gray-900 mt-1">' + esc(ev.titulo) + '</p></div></div>';
        }).join('');
    }
    function renderMesLista() {
        var lista = eventosAno.filter(function (ev) {
            if (!ev.inicio) return false;
            var ini = ev.inicio.split('-');
            var fim = (ev.fim || ev.inicio).split('-');
            var yi = parseInt(ini[0], 10), mi = parseInt(ini[1], 10);
            var yf = parseInt(fim[0], 10), mf = parseInt(fim[1], 10);
            return (yi < ano || (yi === ano && mi <= mes)) && (yf > ano || (yf === ano && mf >= mes));
        }).slice(0, 8);
        if (!lista.length) {
            elMesLista.innerHTML = '<li class="text-sm text-gray-500">' + (eventosAno.length ? 'Nenhum evento neste mês.' : 'Nenhum evento cadastrado no calendário letivo ainda.') + '</li>';
            return;
        }
        elMesLista.innerHTML = lista.map(function (ev) {
            var tx = tipoText[ev.tipo] || '#6b7280';
            var iniP = String(ev.inicio).split('-');
            var fimP = String(ev.fim || ev.inicio).split('-');
            var iniBr = iniP[2] + '/' + iniP[1];
            var fimBr = fimP[2] + '/' + fimP[1];
            var periodo = iniBr === fimBr ? iniBr : (iniBr + ' – ' + fimBr);
            return '<li class="flex items-center gap-3 text-sm"><span class="w-2 h-2 rounded-full flex-shrink-0" style="background:' + esc(tx) + '"></span><span class="text-xs font-medium text-gray-500 w-20 flex-shrink-0">' + esc(periodo) + '</span><span class="text-gray-900 truncate font-medium">' + esc(ev.titulo) + '</span></li>';
        }).join('');
    }
    function bindDias() {
        elGrade.querySelectorAll('.pf-cal-dia').forEach(function (btn) {
            btn.addEventListener('click', function () { renderLista(btn.dataset.data); marcarSelecao(); });
        });
    }
    function marcarSelecao() {
        elGrade.querySelectorAll('.pf-cal-dia').forEach(function (b) {
            b.style.boxShadow = (b.dataset.data === diaSelecionado || b.dataset.data === hoje) ? ('inset 0 0 0 1.5px ' + primaryHex) : '';
        });
    }
    function renderGrade() {
        var primeiro = new Date(ano, mes - 1, 1);
        var diasNoMes = new Date(ano, mes, 0).getDate();
        var offset = primeiro.getDay();
        elLabel.textContent = mesesNomes[mes - 1] + ' de ' + ano;
        renderMesLista();
        var html = '';
        for (var i = 0; i < offset; i++) html += '<div class="h-9 sm:h-10"></div>';
        for (var dia = 1; dia <= diasNoMes; dia++) {
            var dataStr = iso(ano, mes, dia);
            var evs = eventMap[dataStr] || [];
            var tipos = [];
            evs.forEach(function (e) { if (e.tipo && tipos.indexOf(e.tipo) === -1) tipos.push(e.tipo); });
            var prim = tipos[0] || null;
            var dow = new Date(ano, mes - 1, dia).getDay();
            var ehHoje = dataStr === hoje;
            var cellBg = prim ? (tipoBg[prim] || '#eef2ff') : 'transparent';
            var cellText = prim ? (tipoText[prim] || '#1e3a8a') : ((dow === 0 || dow === 6) ? '#9ca3af' : '#4b5563');
            var dots = tipos.slice(0, 3).map(function (t) {
                return '<span class="w-1 h-1 rounded-full" style="background:' + esc(tipoText[t] || '#6b7280') + '"></span>';
            }).join('');
            html += '<button type="button" class="pf-cal-dia relative h-9 sm:h-10 rounded-lg flex flex-col items-center justify-center gap-0.5 hover:bg-gray-50 focus:outline-none" style="background:' + esc(cellBg) + ';' + ((ehHoje || dataStr === diaSelecionado) ? 'box-shadow:inset 0 0 0 1.5px ' + primaryHex + ';' : '') + '" data-data="' + dataStr + '">' +
                '<span class="inline-flex items-center justify-center w-6 h-6 text-xs font-semibold rounded-full' + (ehHoje ? ' text-white' : '') + '" style="color:' + (ehHoje ? '#ffffff' : esc(cellText)) + ';' + (ehHoje ? 'background-color:' + primaryHex + ';' : '') + '">' + dia + '</span>' +
                (dots ? '<span class="flex gap-0.5 leading-none">' + dots + '</span>' : '') + '</button>';
        }
        elGrade.innerHTML = html;
        bindDias();
    }
    document.getElementById('pf-cal-prev').addEventListener('click', function () { mes -= 1; if (mes < 1) { mes = 12; ano -= 1; } renderGrade(); });
    document.getElementById('pf-cal-next').addEventListener('click', function () { mes += 1; if (mes > 12) { mes = 1; ano += 1; } renderGrade(); });
    document.getElementById('pf-cal-hoje').addEventListener('click', function () { var p = hoje.split('-'); ano = parseInt(p[0], 10); mes = parseInt(p[1], 10); renderGrade(); renderLista(hoje); });
    bindDias();
    var evsHoje = eventMap[hoje] || [];
    elCount.textContent = evsHoje.length ? (evsHoje.length === 1 ? '1 evento' : evsHoje.length + ' eventos') : '';
})();
</script>
<?php $fecharBlocoDash(); ?>
<?php endif; ?>
<?php if ($gradeSeb): ?>
<div class="space-y-4 md:space-y-6">
<?php endif; ?>
<?php if ($mostrarAulasHoje): ?>
<?php $abrirBlocoDash('aulas_hoje'); ?>
    <section class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-gray-100 flex items-center justify-between gap-2">
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-gray-900">Aulas de hoje</h2>
                <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($rotuloHoje) ?></p>
            </div>
            <a href="<?= URL ?>/professor/grade-horaria" class="text-sm font-semibold hover:underline whitespace-nowrap" style="color:<?= htmlspecialchars($primaryHex) ?>">Grade →</a>
        </div>
        <div class="p-4 sm:p-5">
            <?php if ($aulasHoje === []): ?>
                <p class="text-sm text-gray-500">Nenhuma aula prevista para hoje.</p>
            <?php else: ?>
                <ul class="space-y-3">
                    <?php foreach ($aulasHoje as $aula):
                        $de = substr((string) ($aula['horario_de'] ?? ''), 0, 5);
                        $ate = substr((string) ($aula['horario_ate'] ?? ''), 0, 5);
                        $turmaAula = trim((string) ($aula['turma_nome'] ?? ''));
                        $materiaAula = trim((string) ($aula['materia_nome'] ?? ''));
                        $salaAula = trim((string) ($aula['sala_nome'] ?? ''));
                    ?>
                    <li class="flex gap-3 rounded-xl border border-gray-100 p-3">
                        <div class="w-16 flex-shrink-0 text-center">
                            <p class="text-xs font-bold text-gray-900"><?= htmlspecialchars($de) ?></p>
                            <p class="text-[10px] text-gray-400"><?= htmlspecialchars($ate) ?></p>
                        </div>
                        <div class="min-w-0 flex-1 border-l-4 pl-3" style="border-left-color:<?= htmlspecialchars($primaryHex) ?>">
                            <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($turmaAula !== '' ? $turmaAula : 'Turma') ?></p>
                            <?php if ($materiaAula !== ''): ?>
                            <p class="text-xs text-gray-600 truncate"><?= htmlspecialchars($materiaAula) ?></p>
                            <?php endif; ?>
                            <?php if ($salaAula !== ''): ?>
                            <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($salaAula) ?></p>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
<?php $fecharBlocoDash(); ?>
<?php endif; ?>

<?php if ($mostrarDesempenhoProf): ?>
<?php $abrirBlocoDash('desempenho'); ?>
    <section class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-gray-100">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-gray-900">Desempenho das turmas</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Média das avaliações nas turmas em que você leciona</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500">Média geral</p>
                    <p class="text-2xl font-bold <?= $corMedia($mediaGeral) ?>">
                        <?= $mediaGeral === null ? '—' : htmlspecialchars(number_format($mediaGeral, 1, ',', '.')) ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="p-4 sm:p-5 max-h-96 overflow-y-auto">
            <?php if ($linhasDesempenho === []): ?>
                <p class="text-sm text-gray-500">Nenhuma turma vinculada à sua grade.</p>
            <?php else: ?>
                <ul class="space-y-3">
                    <?php foreach ($linhasDesempenho as $linha):
                        $mediaLinha = isset($linha['media']) && $linha['media'] !== null ? (float) $linha['media'] : null;
                        $alunosLinha = (int) ($linha['alunos'] ?? 0);
                    ?>
                    <li class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 p-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars((string) ($linha['turma_nome'] ?? 'Turma')) ?></p>
                            <p class="text-xs text-gray-500 truncate">
                                <?= htmlspecialchars((string) ($linha['materia_nome'] ?? '')) ?>
                                <?php if ($alunosLinha > 0): ?>
                                    · <?= $alunosLinha ?> <?= $alunosLinha === 1 ? 'aluno' : 'alunos' ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <p class="text-lg font-bold <?= $corMedia($mediaLinha) ?> whitespace-nowrap">
                            <?= $mediaLinha === null ? 'Sem notas' : htmlspecialchars(number_format($mediaLinha, 1, ',', '.')) ?>
                        </p>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
<?php $fecharBlocoDash(); ?>
<?php endif; ?>
<?php if ($gradeSeb): ?>
</div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (empty($grupo_seb_ativo) && $dashVisivel('resumo')): ?>
<?php $abrirBlocoDash('resumo'); ?>
<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 mb-6 md:mb-8">
    <!-- Card Jornadas -->
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Total de Jornadas</p>
                <p class="text-3xl font-bold text-gray-900"><?= $stats['total_jornadas'] ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
            </div>
        </div>
        <div class="space-y-2 pt-4 border-t border-gray-200">
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-600">Aguardando:</span>
                <span class="text-sm font-semibold text-yellow-600"><?= $stats['jornadas_aguardando'] ?? 0 ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-600">Em Andamento:</span>
                <span class="text-sm font-semibold text-blue-600"><?= $stats['jornadas_em_andamento'] ?? 0 ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-600">Concluído:</span>
                <span class="text-sm font-semibold text-green-600"><?= $stats['jornadas_concluidas'] ?? 0 ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-600">Realizou Jornada:</span>
                <span class="text-sm font-semibold text-green-600"><?= $stats['total_realizou_jornada'] ?? 0 ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-600">Não realizou Jornada:</span>
                <span class="text-sm font-semibold text-red-600"><?= $stats['total_nao_realizou_jornada'] ?? 0 ?></span>
            </div>
        </div>
    </div>

    <!-- Card Mensagens -->
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Mensagens de Alunos</p>
                <p class="text-3xl font-bold text-gray-900"><?= $total_mensagens_nao_lidas ?></p>
                <p class="text-xs text-gray-500 mt-1"><?= $total_mensagens_nao_lidas > 0 ? 'não lida(s)' : 'nenhuma pendente' ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
            </div>
        </div>
        <?php if ($total_mensagens_nao_lidas > 0): ?>
            <div class="mt-4 pt-4 border-t border-gray-200">
                <a href="<?= URL ?>/professor/chat" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                    Ver conversas →
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Card Turmas -->
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Turmas Ativas</p>
                <p class="text-3xl font-bold text-gray-900"><?= $stats['total_turmas'] ?></p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
        </div>
        <?php if (!empty($stats['pontos_atencao'])): ?>
            <div class="pt-4 border-t border-gray-200">
                <div class="flex items-center mb-2">
                    <svg class="w-5 h-5 text-yellow-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <p class="text-xs font-semibold text-gray-700">Pontos de Atenção:</p>
                </div>
                <?php foreach ($stats['pontos_atencao'] as $ponto): ?>
                    <?php if ($ponto['tipo'] === 'alunos_pendentes'): ?>
                        <div class="space-y-1">
                            <?php foreach (array_slice($ponto['dados'], 0, 3) as $aluno): ?>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-gray-600 truncate"><?= htmlspecialchars($aluno['nome']) ?></span>
                                    <span class="text-red-600 font-semibold ml-2"><?= $aluno['total_pendentes'] ?> pend.</span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($ponto['dados']) > 3): ?>
                                <p class="text-xs text-gray-500 mt-1">+<?= count($ponto['dados']) - 3 ?> mais...</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="pt-4 border-t border-gray-200">
                <p class="text-xs text-gray-500">Nenhum ponto de atenção</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $fecharBlocoDash(); ?>
<?php endif; ?>

<?php
$mostrarJornadasRecentes = empty($grupo_seb_ativo) && $dashVisivel('jornadas_recentes');
$mostrarProvasProf = $dashVisivel('provas');
$gradeProf = !$dashOrdemCustom && ($mostrarJornadasRecentes || $mostrarProvasProf);
?>
<?php if ($gradeProf): ?>
<div class="grid grid-cols-1 <?= ($mostrarJornadasRecentes && $mostrarProvasProf) ? 'lg:grid-cols-2' : '' ?> gap-8">
<?php endif; ?>
    <?php if ($mostrarJornadasRecentes): ?>
    <?php $abrirBlocoDash('jornadas_recentes'); ?>
    <div class="bg-white rounded-xl shadow-lg">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">Jornadas Recentes</h2>
                <a href="<?= URL ?>/professor/jornadas" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                    Ver todas
                </a>
            </div>
        </div>
        <div class="p-6">
            <?php if (empty($jornadas_recentes)): ?>
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <p class="text-gray-500">Nenhuma jornada criada ainda</p>
                    <p class="text-sm text-gray-400">Crie sua primeira jornada para começar</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($jornadas_recentes as $jornada): ?>
                        <?php 
                        // Determina status da jornada (prioriza status_jornada calculado, senão usa status)
                        if ($jornada['status'] === 'pausada') {
                            $statusExibir = 'pausada';
                        } else {
                            $statusExibir = $jornada['status_jornada'] ?? $jornada['status'] ?? 'ativa';
                        }
                        
                        // Define cores e labels do status
                        $statusLabels = [
                            'aguardando' => 'Aguardando',
                            'em_andamento' => 'Em Andamento',
                            'concluido' => 'Concluído',
                            'ativa' => 'Ativa',
                            'pausada' => 'Pausada',
                            'finalizada' => 'Finalizada'
                        ];
                        
                        $statusColors = [
                            'aguardando' => 'bg-yellow-100 text-yellow-800',
                            'em_andamento' => 'bg-blue-100 text-blue-800',
                            'concluido' => 'bg-green-100 text-green-800',
                            'ativa' => 'bg-green-100 text-green-800',
                            'pausada' => 'bg-amber-700 text-white',
                            'finalizada' => 'bg-gray-100 text-gray-800'
                        ];
                        
                        $status_label = $statusLabels[$statusExibir] ?? ucfirst($statusExibir);
                        $status_color = $statusColors[$statusExibir] ?? 'bg-blue-100 text-blue-800';
                        
                        // Mesmo formato do detalhe da jornada: X turmas (1°A, ...) • Y alunos
                        $turmasNomes = $jornada['turmas_selecionadas_nomes'] ?? [$jornada['turma_nome'] ?? ''];
                        $totalTurmas = count($turmasNomes);
                                        $totalAlunos = (int)($jornada['visualizado'] ?? 0) + (int)($jornada['nao_visualizado'] ?? 0) + (int)($jornada['concluido'] ?? 0);
                        $turmasTexto = $totalTurmas > 0 ? $totalTurmas . ' ' . ($totalTurmas == 1 ? 'turma' : 'turmas') . ' (' . implode(', ', array_map('htmlspecialchars', $turmasNomes)) . ')' : 'Sem turmas';
                        $realizouJornada = (int)($jornada['visualizado'] ?? 0) + (int)($jornada['concluido'] ?? 0);
                        $naoRealizouJornada = (int)($jornada['nao_visualizado'] ?? 0);
                        ?>
                        <div class="block p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors border border-transparent hover:border-gray-300">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-900 mb-1"><?= htmlspecialchars($jornada['titulo']) ?></h3>
                                    <p class="text-sm text-gray-600 mb-1">
                                        <?= htmlspecialchars($jornada['materia_nome'] ?? 'N/A') ?> • <?= $turmasTexto ?> • <?= $totalAlunos ?> <?= $totalAlunos == 1 ? 'aluno' : 'alunos' ?>
                                    </p>
                                    <?php if ($jornada['data_inicio'] && $jornada['data_fim']): ?>
                                        <p class="text-xs text-gray-500">
                                            <?= date('d/m/Y', strtotime($jornada['data_inicio'])) ?><?= !empty($jornada['hora_inicio']) ? ' às ' . htmlspecialchars($jornada['hora_inicio']) : '' ?> - 
                                            <?= date('d/m/Y', strtotime($jornada['data_fim'])) ?><?= !empty($jornada['hora_fim']) ? ' às ' . htmlspecialchars($jornada['hora_fim']) : '' ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <span class="px-2 py-1 <?= $status_color ?> text-xs rounded-full font-medium ml-2">
                                    <?= $status_label ?>
                                </span>
                            </div>
                            <div class="flex items-center space-x-4 text-xs text-gray-600 mt-2">
                                <span class="flex items-center">
                                    <span class="w-2 h-2 bg-green-500 rounded-full mr-1"></span>
                                    Realizou Jornada: <?= $realizouJornada ?> aluno(s)
                                </span>
                                <span class="flex items-center">
                                    <span class="w-2 h-2 bg-red-500 rounded-full mr-1"></span>
                                    Não realizou Jornada: <?= $naoRealizouJornada ?> aluno(s)
                                </span>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>" 
                                   class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    Ver Detalhes
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php $fecharBlocoDash(); ?>
    <?php endif; ?>

    <?php if ($mostrarProvasProf): ?>
    <?php $abrirBlocoDash('provas'); ?>
    <div class="bg-white rounded-xl shadow-lg">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900"><?= !empty($grupo_seb_ativo) ? 'Avaliações' : 'Prova Online' ?></h2>
                <a href="<?= URL ?>/professor/provas" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                    Ver todas
                </a>
            </div>
        </div>
        <div class="p-6">
            <?php if (empty($eventos_prova_recentes)): ?>
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="text-gray-500">Nenhum evento de prova ainda</p>
                    <p class="text-sm text-gray-400">Aguardando eventos criados pela coordenação</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($eventos_prova_recentes as $evento):
                        $isLancamentoNota = (($evento['formato_evento'] ?? 'online_questoes') === 'lancamento_nota');
                        $escolaElaboraImpressa = (($evento['formato_evento'] ?? '') === 'prova_impressa')
                            && (($evento['configuracao_nota'] ?? '') === 'coordenacao_calcula');
                        if ($escolaElaboraImpressa) {
                            $urlProva = URL . '/professor/provas';
                        } elseif ($isLancamentoNota) {
                            $urlProva = URL . '/professor/provas/evento-lancar-notas/' . (int)$evento['id'] . '?materia_id=' . (int)$evento['materia_id'];
                        } else {
                            $urlProva = !empty($evento['prova_existente_id'])
                                ? URL . '/professor/provas/editar/' . (int)$evento['prova_existente_id']
                                : URL . '/professor/provas/criar/evento/' . (int)$evento['id'] . '?materia_id=' . (int)$evento['materia_id'];
                        }
                    ?>
                        <a href="<?= $urlProva ?>" 
                           class="block p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 mb-2">
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-medium text-gray-900 mb-1"><?= htmlspecialchars($evento['titulo']) ?></h3>
                                    <p class="text-sm text-gray-600 mb-1">
                                        <span class="font-semibold">Matéria:</span> <?= htmlspecialchars($evento['materia_nome'] ?? 'N/A') ?>
                                    </p>
                                    <?php if ($evento['data_prova']): ?>
                                        <p class="text-xs text-gray-500">
                                            <?= date('d/m/Y', strtotime($evento['data_prova'])) ?> - 
                                            <?= date('H:i', strtotime($evento['hora_inicio'])) ?> às <?= date('H:i', strtotime($evento['hora_fim'])) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="sm:ml-4 sm:text-right shrink-0">
                                    <?php if ($isLancamentoNota): ?>
                                        <span class="px-2 py-1 bg-violet-100 text-violet-800 text-xs rounded-full font-medium">
                                            Lançamento de notas
                                        </span>
                                    <?php elseif (($evento['provas_criadas_professor'] ?? 0) > 0): ?>
                                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full font-medium">
                                            Prova criada
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full font-medium">
                                            Pendente
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!$isLancamentoNota): ?>
                            <div class="flex items-center mt-2">
                                <span class="text-xs text-gray-600">
                                    Provas criadas: <strong><?= $evento['provas_criadas_professor'] ?? 0 ?></strong>
                                </span>
                            </div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php $fecharBlocoDash(); ?>
    <?php endif; ?>
<?php if ($gradeProf): ?>
</div>
<?php endif; ?>
<?php if ($dashOrdemCustom): ?></div><?php endif; ?>
