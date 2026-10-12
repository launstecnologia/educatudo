<?php
return [
    'chave' => 'educahits',
    'label' => 'EducaHits',
    'namespace' => 'App\Modulos\EducaHits',
    'feature_gate' => true,
    'feature_keys' => ['aluno' => 'educa_hits'],
    'master_form_key' => 'geral_educa_hits',
    'rotas' => ['/hits' => 'educa_hits'],
    'controllers_dir' => __DIR__ . '/Controllers',
];
