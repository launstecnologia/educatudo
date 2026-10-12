<?php
$page_title = $page_title ?? 'Remanejamento';
$page_subtitle = $page_subtitle ?? 'Selecione turma de origem, destino e os alunos.';
$form_action = URL . '/admin/students/remanejamento';
$submit_label = 'Remanejar alunos';
$show_destino = true;
$show_sem_turma = false;
require __DIR__ . '/_movimentacao_form.php';
