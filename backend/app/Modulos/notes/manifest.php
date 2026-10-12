<?php
return [
    'chave' => 'notes',
    'label' => 'Notes',
    'namespace' => 'App\Modulos\Notes',
    'feature_gate' => true,
    'feature_keys' => ['aluno' => 'notes'],
    'master_form_key' => 'geral_notes',
    'rotas' => [
        '/notes' => 'notes',
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
];
