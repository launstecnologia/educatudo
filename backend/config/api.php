<?php
/**
 * Configuração da API externa (notícias / notificações manuais).
 * X-API-KEY: obrigatório para POST /api/notificacoes (envio manual).
 */
return [
    'api_key' => env('API_KEY', ''),
    'api_key_header' => 'X-API-KEY',
];
