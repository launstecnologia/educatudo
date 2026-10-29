<?php
/** Fragmentos locais revisados; nenhuma consulta remota ou leitura do tenant. */
$arquivoReferenciaGuia = (string) ($indiceReferenciaGuia[(string) ($etapaGuia['chave'] ?? '')]['arquivo'] ?? '');
if (preg_match('/\A[a-z_]+\.html\z/D', $arquivoReferenciaGuia) === 1) {
    $caminhoReferenciaGuia = __DIR__ . '/conteudo_guia/' . $arquivoReferenciaGuia;
    if (is_file($caminhoReferenciaGuia)) {
        readfile($caminhoReferenciaGuia);
    }
}
