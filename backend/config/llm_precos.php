<?php
/**
 * Preços de referência LLM (USD por 1 milhão de tokens).
 * Atualize este arquivo quando a OpenAI mudar a tabela oficial.
 * Usado pelo Master → Custo LLM para estimativa e exibição.
 *
 * @return array{atualizado_em: string, modelos: array<string, array{input: float, output: float}>}
 */
return [
    'atualizado_em' => '2026-07-09',
    'modelos' => [
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-4.1' => ['input' => 2.00, 'output' => 8.00],
        'gpt-4.1-mini' => ['input' => 0.40, 'output' => 1.60],
        'gpt-4.1-nano' => ['input' => 0.10, 'output' => 0.40],
        'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
        'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
        'gpt-5' => ['input' => 1.25, 'output' => 10.00],
        'gpt-5-mini' => ['input' => 0.25, 'output' => 2.00],
        'gpt-5-nano' => ['input' => 0.05, 'output' => 0.40],
        'o3' => ['input' => 2.00, 'output' => 8.00],
        'o4-mini' => ['input' => 1.10, 'output' => 4.40],
    ],
];
