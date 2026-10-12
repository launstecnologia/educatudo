<?php
return [
    'chave' => 'jogos',
    'label' => 'Jogos',
    'namespace' => 'App\Modulos\Jogos',
    'feature_gate' => true,
    'feature_keys' => ['aluno' => 'jogos'],
    'master_form_key' => 'geral_jogos',
    'rotas' => [
        '/jogos' => 'jogos',
        '/jogo-milhao' => 'jogos',
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
];
