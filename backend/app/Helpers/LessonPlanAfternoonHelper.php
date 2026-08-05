<?php

/**
 * Estrutura e validação do bloco "Aulas da Tarde (Oficinas)" em planos de aula.
 */
class LessonPlanAfternoonHelper
{
    public const TIPO_NENHUM = 'nenhum';
    public const TIPO_EXERCICIOS = 'exercicios_adicionais';
    public const TIPO_JORNADAS = 'jornadas';
    public const TIPO_OUTROS = 'outros';

    public static function tipos(): array
    {
        return [
            self::TIPO_NENHUM => 'Nenhum',
            self::TIPO_EXERCICIOS => 'Exercícios adicionais',
            self::TIPO_JORNADAS => 'Jornadas',
            self::TIPO_OUTROS => 'Outros',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function parseList(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [[
                'tipo' => self::TIPO_OUTROS,
                'descricao' => trim(strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                'legacy' => true,
            ]];
        }

        if (!empty($decoded['atividades']) && is_array($decoded['atividades'])) {
            return array_values($decoded['atividades']);
        }

        if (!empty($decoded['tipo'])) {
            return [$decoded];
        }

        return [];
    }

    /** @deprecated Use parseList() */
    public static function parse(?string $raw): array
    {
        $list = self::parseList($raw);
        return $list[0] ?? ['tipo' => ''];
    }

    /**
     * @return array{errors: array<string, string>, value: ?string}
     */
    public static function validateAndBuild(array $post): array
    {
        $errors = [];
        $atividades = $post['aulas_tarde_atividades'] ?? null;

        if (!is_array($atividades) || $atividades === []) {
            $atividades = self::legacyPostToActivities($post);
        }

        if ($atividades === []) {
            $errors['aulas_tarde_atividades'] = 'Adicione pelo menos uma atividade';
            return ['errors' => $errors, 'value' => null];
        }

        $validated = [];
        foreach ($atividades as $index => $item) {
            if (!is_array($item)) {
                $errors['aulas_tarde_atividades_' . $index] = 'Atividade inválida';
                continue;
            }

            $result = self::validateActivity($item, (int) $index);
            if (!empty($result['errors'])) {
                $errors = array_merge($errors, $result['errors']);
            } else {
                $validated[] = $result['activity'];
            }
        }

        if ($validated === []) {
            $errors['aulas_tarde_atividades'] = 'Adicione pelo menos uma atividade válida';
        }

        return [
            'errors' => $errors,
            'value' => empty($errors) ? json_encode(['atividades' => $validated], JSON_UNESCAPED_UNICODE) : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function legacyPostToActivities(array $post): array
    {
        $tipo = trim((string) ($post['aulas_tarde_tipo'] ?? ''));
        if ($tipo === '') {
            return [];
        }

        return [[
            'tipo' => $tipo,
            'pagina' => $post['aulas_tarde_pagina'] ?? '',
            'apostila' => $post['aulas_tarde_apostila'] ?? '',
            'exercicios' => $post['aulas_tarde_exercicios'] ?? '',
            'jornada_nome' => $post['aulas_tarde_jornada_nome'] ?? '',
            'descricao' => $post['aulas_tarde_descricao'] ?? '',
        ]];
    }

    /**
     * @return array{errors: array<string, string>, activity: array<string, string>}
     */
    private static function validateActivity(array $item, int $index): array
    {
        $errors = [];
        $prefix = 'aulas_tarde_atividades_' . $index . '_';
        $tipo = trim((string) ($item['tipo'] ?? ''));

        if ($tipo === '') {
            $errors[$prefix . 'tipo'] = 'Atividade ' . ($index + 1) . ': selecione o tipo';
            return ['errors' => $errors, 'activity' => []];
        }

        $activity = ['tipo' => $tipo];

        switch ($tipo) {
            case self::TIPO_NENHUM:
                break;

            case self::TIPO_EXERCICIOS:
                foreach (['pagina' => 'Página', 'apostila' => 'Apostila', 'exercicios' => 'Exercícios'] as $field => $label) {
                    $value = trim((string) ($item[$field] ?? ''));
                    if ($value === '') {
                        $errors[$prefix . $field] = 'Atividade ' . ($index + 1) . ': ' . $label . ' é obrigatório';
                    }
                    $activity[$field] = $value;
                }
                break;

            case self::TIPO_JORNADAS:
                $nome = trim((string) ($item['jornada_nome'] ?? ''));
                if ($nome === '') {
                    $errors[$prefix . 'jornada_nome'] = 'Atividade ' . ($index + 1) . ': nome da jornada é obrigatório';
                }
                $activity['jornada_nome'] = $nome;
                break;

            case self::TIPO_OUTROS:
                $descricao = trim((string) ($item['descricao'] ?? ''));
                if ($descricao === '') {
                    $errors[$prefix . 'descricao'] = 'Atividade ' . ($index + 1) . ': descrição é obrigatória';
                }
                $activity['descricao'] = $descricao;
                break;

            default:
                $errors[$prefix . 'tipo'] = 'Atividade ' . ($index + 1) . ': tipo inválido';
        }

        return ['errors' => $errors, 'activity' => $activity];
    }

    public static function tipoLabel(string $tipo): string
    {
        return self::tipos()[$tipo] ?? $tipo;
    }

    public static function renderHtml(?string $raw): string
    {
        $atividades = self::parseList($raw);
        if ($atividades === []) {
            return '';
        }

        $html = '';
        foreach ($atividades as $index => $data) {
            $numero = $index + 1;
            $tipo = (string) ($data['tipo'] ?? '');
            if ($tipo === '') {
                continue;
            }

            $html .= '<div class="mb-4' . ($index > 0 ? ' pt-4 border-t border-gray-200' : '') . '">';
            $html .= '<p class="mb-2 font-semibold text-gray-800">Atividade ' . $numero . '</p>';
            $html .= '<p class="mb-2 text-sm"><span class="font-medium text-gray-600">Tipo:</span> '
                . htmlspecialchars(self::tipoLabel($tipo)) . '</p>';

            if ($tipo === self::TIPO_EXERCICIOS) {
                $html .= '<dl class="space-y-1 text-sm text-gray-800">';
                $html .= '<div><dt class="font-medium text-gray-600 inline">Página:</dt> <dd class="inline">' . htmlspecialchars((string) ($data['pagina'] ?? '')) . '</dd></div>';
                $html .= '<div><dt class="font-medium text-gray-600 inline">Apostila:</dt> <dd class="inline">' . htmlspecialchars((string) ($data['apostila'] ?? '')) . '</dd></div>';
                $html .= '<div><dt class="font-medium text-gray-600">Exercícios:</dt><dd class="whitespace-pre-wrap">' . htmlspecialchars((string) ($data['exercicios'] ?? '')) . '</dd></div>';
                $html .= '</dl>';
            } elseif ($tipo === self::TIPO_JORNADAS) {
                $html .= '<p class="text-sm text-gray-800"><span class="font-medium text-gray-600">Nome da jornada:</span> '
                    . htmlspecialchars((string) ($data['jornada_nome'] ?? '')) . '</p>';
            } elseif ($tipo === self::TIPO_OUTROS) {
                $html .= '<p class="text-sm text-gray-800 whitespace-pre-wrap">' . htmlspecialchars((string) ($data['descricao'] ?? '')) . '</p>';
            }

            $html .= '</div>';
        }

        return $html;
    }

    public static function renderPlainText(?string $raw): string
    {
        $atividades = self::parseList($raw);
        if ($atividades === []) {
            return '';
        }

        $blocos = [];
        foreach ($atividades as $index => $data) {
            $tipo = (string) ($data['tipo'] ?? '');
            if ($tipo === '') {
                continue;
            }

            $lines = ['Atividade ' . ($index + 1) . ' - Tipo: ' . self::tipoLabel($tipo)];

            if ($tipo === self::TIPO_EXERCICIOS) {
                $lines[] = 'Página: ' . ($data['pagina'] ?? '');
                $lines[] = 'Apostila: ' . ($data['apostila'] ?? '');
                $lines[] = 'Exercícios: ' . ($data['exercicios'] ?? '');
            } elseif ($tipo === self::TIPO_JORNADAS) {
                $lines[] = 'Nome da jornada: ' . ($data['jornada_nome'] ?? '');
            } elseif ($tipo === self::TIPO_OUTROS) {
                $lines[] = (string) ($data['descricao'] ?? '');
            }

            $blocos[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocos);
    }
}
