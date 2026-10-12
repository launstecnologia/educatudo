<?php
return [
    'chave' => 'educalabs',
    'label' => 'EducaLabs',
    'namespace' => 'App\Modulos\EducaLabs',
    'feature_gate' => true,
    'feature_keys' => ['aluno' => 'educalabs'],
    'master_form_key' => 'geral_educalabs',
    'rotas' => ['/educalabs' => 'educalabs'],
    'controllers_dir' => __DIR__ . '/Controllers',
];
