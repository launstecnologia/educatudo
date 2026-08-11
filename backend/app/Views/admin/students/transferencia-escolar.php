<?php
$page_title = $page_title ?? 'Saída da escola (TR)';
$page_subtitle = $page_subtitle ?? 'Selecione a turma de origem e os alunos que deixam a escola.';
$form_action = URL . '/admin/students/transferencia-escolar';
$submit_label = 'Registrar transferência escolar (TR)';
$show_destino = false;
$show_sem_turma = false;
$show_tr_fields = true;
require __DIR__ . '/_movimentacao_form.php';
