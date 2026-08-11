<?php
/**
 * Manifest do módulo Expo Colag (feira de projetos — exclusivo COLAG).
 * Default off — ligar só no tenant COLAG pelo Painel Master.
 */
return [
    'chave' => 'expo-colag',
    'label' => 'Expo Colag',
    'grupo' => 'colag',
    'feature_keys' => [
        'aluno' => 'expo_colag',
        'professor' => 'expo_colag',
    ],
    'master_feature_keys' => ['expo_colag'],
    'master_form_key' => 'geral_expo_colag',
    'feature_defaults' => [
        'expo_colag' => '0',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/expo-colag' => 'expo_colag',
        '/expo-colag/s' => 'expo_colag',
        '/professor/expo-colag' => 'expo_colag',
        '/admin/expo-colag' => 'expo_colag',
        '/pais/expo-colag' => 'expo_colag',
    ],
    'menu' => [
        'aluno' => [
            ['label' => 'Expo Colag', 'path' => '/expo-colag', 'feature_key' => 'expo_colag'],
        ],
        'professor' => [
            ['label' => 'Expo Colag', 'path' => '/professor/expo-colag', 'feature_key' => 'expo_colag'],
        ],
        'admin' => [
            ['label' => 'Expo Colag', 'path' => '/admin/expo-colag', 'feature_key' => 'expo_colag'],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
