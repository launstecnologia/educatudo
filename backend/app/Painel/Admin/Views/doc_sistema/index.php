<?php
/**
 * Alias admin → view compartilhada da wiki.
 */
$wiki_url_base = $wiki_url_base ?? (URL . '/admin/doc-sistema');
$wiki_voltar_href = $wiki_voltar_href ?? (URL . '/admin/assistente');
$wiki_voltar_label = $wiki_voltar_label ?? 'Assistente';
$wiki_titulo = $wiki_titulo ?? 'Doc do sistema';
$wiki_subtitulo = $wiki_subtitulo ?? 'Documentação viva em doc_sistema/. Edite o .md — a página atualiza sozinha.';
include __DIR__ . '/../../shared/doc_sistema/wiki.php';
