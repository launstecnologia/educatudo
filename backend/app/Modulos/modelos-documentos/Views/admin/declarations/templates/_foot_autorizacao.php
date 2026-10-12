<?php
/**
 * Rodapé das autorizações: cidade/data, assinatura do responsável e da direção.
 * Variáveis: $cidade_data, $dados['unidade'], $gerado_em, $assinaturaResponsavel (opcional)
 */
$unidade = is_array($dados['unidade'] ?? null) ? $dados['unidade'] : [];
$diretor = trim((string) ($unidade['diretor_nome'] ?? ''));
$assinaturaResponsavel = isset($assinaturaResponsavel) ? trim((string) $assinaturaResponsavel) : '';
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
    </div><!-- /.corpo -->

    <div class="fecho"><?= $esc($cidade_data ?? '') ?>.</div>

    <div class="assinaturas">
        <div class="sig">
            <div class="line"></div>
            <div class="nome"><?= $assinaturaResponsavel !== '' ? $esc($assinaturaResponsavel) : '&nbsp;' ?></div>
            <div class="cargo">Responsável legal</div>
        </div>
        <div class="sig">
            <div class="line"></div>
            <div class="nome"><?= $diretor !== '' ? $esc($diretor) : '&nbsp;' ?></div>
            <div class="cargo">Direção</div>
        </div>
    </div>

    <div class="footer">
        Documento emitido eletronicamente em <?= $esc($gerado_em ?? date('d/m/Y')) ?> pela plataforma EducaTudo.
    </div>
</body>
</html>
