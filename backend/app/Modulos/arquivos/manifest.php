<?php
/**
 * Manifest do módulo Arquivos (materiais da turma).
 * Consumido por ModuloRegistry → FeatureGate / menus / Master.
 *
 * aluno_recuperacao: sub-feature (menu/rota Recuperação). Default off — ligar só no COLAG.
 */
return [
    'chave' => 'arquivos',
    'label' => 'Arquivos',
    'grupo' => 'geral',
    'feature_keys' => [
        'aluno' => 'aluno_arquivos',
        'professor' => 'professor_arquivos',
        'recuperacao' => 'aluno_recuperacao',
    ],
    // Só estas entram no toggle Master "Arquivos" (geral_arquivos)
    'master_feature_keys' => ['aluno_arquivos', 'professor_arquivos'],
    'master_form_key' => 'geral_arquivos',
    // Toggle avulso no bloco Aluno do Master
    'master_aluno' => [
        'aluno_recuperacao' => 'Recuperação (menu Arquivos)',
    ],
    'feature_defaults' => [
        'aluno_recuperacao' => '0',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/aluno/arquivos' => 'aluno_arquivos',
        '/aluno/recuperacao' => 'aluno_recuperacao',
        '/professor/arquivos' => 'professor_arquivos',
        '/admin/arquivos' => 'aluno_arquivos',
    ],
    'menu' => [
        'aluno' => [
            ['label' => 'Arquivos', 'path' => '/aluno/arquivos', 'feature_key' => 'aluno_arquivos'],
            ['label' => 'Recuperação', 'path' => '/aluno/recuperacao', 'feature_key' => 'aluno_recuperacao'],
        ],
        'professor' => [
            ['label' => 'Arquivos', 'path' => '/professor/arquivos', 'feature_key' => 'professor_arquivos'],
        ],
        'admin' => [
            ['label' => 'Arquivos', 'path' => '/admin/arquivos', 'feature_key' => 'aluno_arquivos'],
        ],
        'pais' => [
            ['label' => 'Arquivos', 'path' => '/pais/filhos/{id}/arquivos', 'feature_key' => 'aluno_arquivos'],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
