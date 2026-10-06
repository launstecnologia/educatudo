<?php
/**
 * Mesma ficha do boletim oficial do detalhe do aluno, só para consulta.
 *
 * @var array{quadro?:?array,fichas?:list<array<string,mixed>>,ficha_id?:int,observacao?:array<string,mixed>}|null $boletim_oficial
 * @var string|null $boletim_url_ficha
 */
$dadosBoletimOficial = is_array($boletim_oficial ?? null) ? $boletim_oficial : [];
$quadro = is_array($dadosBoletimOficial['quadro'] ?? null) ? $dadosBoletimOficial['quadro'] : null;
$fichas = is_array($dadosBoletimOficial['fichas'] ?? null) ? $dadosBoletimOficial['fichas'] : [];
$ficha_id = (int) ($dadosBoletimOficial['ficha_id'] ?? 0);
if (is_array($dadosBoletimOficial['observacao'] ?? null)) {
    $boletim_observacao = $dadosBoletimOficial['observacao'];
}
$boletim_somente_leitura = true;
$aluno_id = 0;
if (is_array($aluno ?? null)) {
    $aluno_id = (int) ($aluno['id'] ?? 0);
}
if ($aluno_id <= 0 && is_array($filho ?? null)) {
    $aluno_id = (int) ($filho['id'] ?? 0);
}
$urlFicha = trim((string) ($boletim_url_ficha ?? ''));
$hrefAba = null;
if ($urlFicha !== '') {
    $hrefAba = static function (string $aba, array $params = []) use ($urlFicha): string {
        unset($aba);
        $separador = str_contains($urlFicha, '?') ? '&' : '?';

        return $urlFicha . $separador . 'ficha_id=' . (int) ($params['ficha_id'] ?? 0) . '#boletim';
    };
}
$partialBoletimOficial = dirname(__DIR__, 2) . '/Modulos/vida-escolar/Views/admin/_aba_boletim.php';
?>
<?php if ($quadro === null && $fichas === []): ?>
    <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
        <p class="text-gray-500">Nenhum boletim disponível.</p>
    </div>
<?php elseif (is_file($partialBoletimOficial)): ?>
    <?php require $partialBoletimOficial; ?>
<?php else: ?>
    <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
        <p class="text-gray-500">Nenhum boletim disponível.</p>
    </div>
<?php endif; ?>
<?php
unset($hrefAba, $quadro, $fichas, $dadosBoletimOficial, $urlFicha, $partialBoletimOficial);
