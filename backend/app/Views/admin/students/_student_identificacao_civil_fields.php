<?php
require_once __DIR__ . '/../../../Helpers/StudentFormHelper.php';
$student = is_array($student ?? null) ? $student : [];
$coresRaca = StudentFormHelper::corRacaOpcoes();
$ufNascAtual = strtoupper(trim((string) ($student['uf_nascimento'] ?? '')));
$naturalidadeAtual = trim((string) ($student['naturalidade'] ?? ''));
$ufsPorNome = StudentFormHelper::ufsBrasilPorNome();
$corRacaAtual = trim((string) ($student['cor_raca'] ?? ''));
$zonaAtual = trim((string) ($student['zona'] ?? ''));
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$parteCivil = $parteCivil ?? 'tudo';
$mostraFiliacao = $parteCivil === 'tudo' || $parteCivil === 'filiacao';
$mostraCivil = $parteCivil === 'tudo' || $parteCivil === 'civil';
$mostraCertidao = $parteCivil === 'tudo' || $parteCivil === 'certidao';
$mostraEstrangeiros = $parteCivil === 'tudo' || $parteCivil === 'estrangeiros';
$temEstrangeiro = trim((string) ($student['pais'] ?? '')) !== ''
    || trim((string) ($student['passaporte'] ?? '')) !== ''
    || trim((string) ($student['rne'] ?? '')) !== '';
?>
<?php if ($mostraFiliacao): ?>
<div class="<?= $parteCivil === 'tudo' ? 'md:col-span-2 pt-2' : '' ?>">
    <?php if ($parteCivil === 'tudo'): ?>
    <h4 class="text-base font-semibold text-gray-900 mb-4">Filiação e Censo</h4>
    <?php endif; ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="nome_mae" class="block text-sm font-medium text-gray-700 mb-2">Nome da mãe</label>
            <input type="text" id="nome_mae" name="nome_mae" maxlength="255"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                   value="<?= $esc($student['nome_mae'] ?? '') ?>" placeholder="Filiação 1">
        </div>
        <div>
            <label for="nome_pai" class="block text-sm font-medium text-gray-700 mb-2">Nome do pai</label>
            <input type="text" id="nome_pai" name="nome_pai" maxlength="255"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                   value="<?= $esc($student['nome_pai'] ?? '') ?>" placeholder="Filiação 2">
        </div>
        <div>
            <label for="codigo_inep" class="block text-sm font-medium text-gray-700 mb-2">Código INEP do aluno (Censo)</label>
            <input type="text" id="codigo_inep" name="codigo_inep" maxlength="20" inputmode="numeric"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                   value="<?= $esc($student['codigo_inep'] ?? '') ?>" placeholder="ID do aluno no Censo Escolar">
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($mostraCivil): ?>
<div class="<?= $parteCivil === 'tudo' ? 'md:col-span-2 pt-2' : '' ?>">
    <?php if ($parteCivil === 'tudo'): ?>
    <h4 class="text-base font-semibold text-gray-900 mb-4">Identificação civil</h4>
    <?php endif; ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="nacionalidade" class="block text-sm font-medium text-gray-700 mb-2">Nacionalidade</label>
            <input type="text" id="nacionalidade" name="nacionalidade"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                   value="<?= $esc($student['nacionalidade'] ?? '') ?>"
                   placeholder="Ex: Brasileira">
        </div>
        <div>
            <label for="cor_raca" class="block text-sm font-medium text-gray-700 mb-2">Cor / Raça (IBGE)</label>
            <select id="cor_raca" name="cor_raca"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <option value="">Não informada</option>
                <?php foreach ($coresRaca as $cr): ?>
                <option value="<?= $esc($cr) ?>" <?= $corRacaAtual === $cr ? 'selected' : '' ?>><?= $esc($cr) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="uf_nascimento" class="block text-sm font-medium text-gray-700 mb-2">UF de nascimento</label>
            <select id="uf_nascimento" name="uf_nascimento"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <option value="">Selecione</option>
                <?php foreach ($ufsPorNome as $sigla => $nomeUf): ?>
                <option value="<?= $esc($sigla) ?>" <?= $ufNascAtual === $sigla ? 'selected' : '' ?>><?= $esc($nomeUf) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="naturalidade" class="block text-sm font-medium text-gray-700 mb-2">Naturalidade (município de nascimento)</label>
            <select id="naturalidade" name="naturalidade" data-cidade="<?= $esc($naturalidadeAtual) ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <option value=""><?= $ufNascAtual === '' ? 'Selecione o estado' : 'Carregando cidades...' ?></option>
                <?php if ($naturalidadeAtual !== ''): ?>
                <option value="<?= $esc($naturalidadeAtual) ?>" selected><?= $esc($naturalidadeAtual) ?></option>
                <?php endif; ?>
            </select>
        </div>
        <div>
            <label for="nis" class="block text-sm font-medium text-gray-700 mb-2">NIS (PIS/PASEP)</label>
            <input type="text" id="nis" name="nis" inputmode="numeric" maxlength="20"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                   value="<?= $esc($student['nis'] ?? '') ?>"
                   placeholder="Número do NIS">
        </div>
        <div>
            <label for="zona" class="block text-sm font-medium text-gray-700 mb-2">Zona de residência</label>
            <select id="zona" name="zona"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <option value="">Não informada</option>
                <option value="urbana" <?= $zonaAtual === 'urbana' ? 'selected' : '' ?>>Urbana</option>
                <option value="rural" <?= $zonaAtual === 'rural' ? 'selected' : '' ?>>Rural</option>
            </select>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($mostraCertidao): ?>
    <div class="<?= $parteCivil === 'tudo' ? 'mt-6' : '' ?>">
        <?php if ($parteCivil === 'tudo'): ?>
        <h4 class="text-base font-semibold text-gray-900 mb-4">Certidão de nascimento</h4>
        <?php endif; ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label for="certidao_nascimento" class="block text-sm font-medium text-gray-700 mb-2">Matrícula / número da certidão</label>
                <input type="text" id="certidao_nascimento" name="certidao_nascimento" maxlength="80"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       value="<?= $esc($student['certidao_nascimento'] ?? '') ?>"
                       placeholder="Número da matrícula da certidão (modelo novo)">
            </div>
            <div>
                <label for="certidao_livro" class="block text-sm font-medium text-gray-700 mb-2">Livro</label>
                <input type="text" id="certidao_livro" name="certidao_livro" maxlength="20"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       value="<?= $esc($student['certidao_livro'] ?? '') ?>">
            </div>
            <div>
                <label for="certidao_folha" class="block text-sm font-medium text-gray-700 mb-2">Folha</label>
                <input type="text" id="certidao_folha" name="certidao_folha" maxlength="20"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       value="<?= $esc($student['certidao_folha'] ?? '') ?>">
            </div>
            <div>
                <label for="certidao_termo" class="block text-sm font-medium text-gray-700 mb-2">Termo</label>
                <input type="text" id="certidao_termo" name="certidao_termo" maxlength="20"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       value="<?= $esc($student['certidao_termo'] ?? '') ?>">
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($mostraEstrangeiros): ?>
    <div class="<?= $parteCivil === 'estrangeiros' ? '' : 'mt-6' ?>">
        <?php if ($parteCivil === 'estrangeiros'): ?>
        <details <?= $temEstrangeiro ? 'open' : '' ?>>
            <summary class="cursor-pointer text-base font-semibold text-gray-900">Estrangeiros (opcional)</summary>
            <p class="mt-1 text-sm text-gray-500">Use só quando o aluno mora no exterior ou tem documento de estrangeiro.</p>
            <div class="mt-4">
        <?php else: ?>
        <h4 class="text-base font-semibold text-gray-900 mb-4">Estrangeiros (opcional)</h4>
        <?php endif; ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="pais" class="block text-sm font-medium text-gray-700 mb-2">País de residência</label>
                <input type="text" id="pais" name="pais" maxlength="60"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       value="<?= $esc($student['pais'] ?? '') ?>"
                       placeholder="Brasil">
            </div>
            <div>
                <label for="passaporte" class="block text-sm font-medium text-gray-700 mb-2">Passaporte</label>
                <input type="text" id="passaporte" name="passaporte" maxlength="30"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       value="<?= $esc($student['passaporte'] ?? '') ?>">
            </div>
            <div>
                <label for="rne" class="block text-sm font-medium text-gray-700 mb-2">RNE / RNM</label>
                <input type="text" id="rne" name="rne" maxlength="30"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       value="<?= $esc($student['rne'] ?? '') ?>">
            </div>
        </div>
        <?php if ($parteCivil === 'estrangeiros'): ?>
            </div>
        </details>
        <?php endif; ?>
    </div>
<?php endif; ?>
