<?php
/**
 * Manifest do módulo Grupo Seb (skin do portal do aluno).
 * Default off — ligar só nas escolas do Grupo Seb pelo Painel Master.
 */
return [
    'chave' => 'grupo-seb',
    'namespace' => 'App\\Modulos\\GrupoSeb',
    'label' => 'Grupo Seb',
    'grupo' => 'geral',
    'feature_keys' => [
        'aluno' => 'grupo_seb',
    ],
    'master_feature_keys' => ['grupo_seb'],
    'master_form_key' => 'geral_grupo_seb',
    'feature_defaults' => [
        'grupo_seb' => '0',
    ],
    'feature_gate' => true,
    // Skin de UI: reutiliza rotas já existentes (agenda, boletim, grade, drive, mural…).
    'rotas' => [],
    'menu' => [],
    'views_dir' => __DIR__ . '/Views',
];
