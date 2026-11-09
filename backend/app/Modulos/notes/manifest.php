<?php
return [
    'chave' => 'notes',
    'label' => 'Notes',
    'namespace' => 'App\Modulos\Notes',
    'feature_gate' => true,
    'feature_keys' => ['aluno' => 'aluno_caderno_novo'],
    'rotas' => [
        '/notes' => 'aluno_caderno_novo',
        '/caderno' => 'aluno_caderno_novo',
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
];
