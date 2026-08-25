<?php
/**
 * Catálogo único de módulos comerciais do Master.
 * Um nome na tela; ao salvar, grava todas as chaves (aluno, professor, pais).
 * Rotas alimentam o FeatureGate — desligar some do menu e bloqueia a URL.
 */

require_once __DIR__ . '/ModuloRegistry.php';

class ModuloCatalogo
{
    /** @var list<array>|null */
    private static $cache = null;

    /**
     * @return list<array{
     *   chave: string,
     *   nome: string,
     *   feature_keys: list<string>,
     *   rotas: array<string, string>,
     *   rotas_pais: list<array{pattern: string, feature_key: string}>
     * }>
     */
    public static function todos(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $lista = self::modulosNativos();
        $vistos = [];
        foreach ($lista as $mod) {
            $vistos[$mod['chave']] = true;
            foreach ($mod['feature_keys'] as $key) {
                $vistos[(string) $key] = true;
            }
        }

        foreach (ModuloRegistry::all() as $manifest) {
            $formKey = (string) ($manifest['master_form_key'] ?? '');
            if ($formKey !== '' && empty($vistos[$formKey])) {
                if (!empty($manifest['master_feature_keys']) && is_array($manifest['master_feature_keys'])) {
                    $keys = array_values(array_map('strval', $manifest['master_feature_keys']));
                } else {
                    $fk = is_array($manifest['feature_keys'] ?? null) ? $manifest['feature_keys'] : [];
                    $keys = array_values(array_filter([
                        isset($fk['aluno']) ? (string) $fk['aluno'] : null,
                        isset($fk['professor']) ? (string) $fk['professor'] : null,
                        isset($fk['admin']) ? (string) $fk['admin'] : null,
                    ]));
                }
                if ($keys !== []) {
                    $lista[] = self::normalizar([
                        'chave' => $formKey,
                        'nome' => (string) ($manifest['label'] ?? $formKey),
                        'feature_keys' => $keys,
                        'rotas' => is_array($manifest['rotas'] ?? null) ? $manifest['rotas'] : [],
                        'rotas_pais' => self::rotasPaisDoManifest($manifest, $keys),
                    ]);
                    $vistos[$formKey] = true;
                    foreach ($keys as $key) {
                        $vistos[$key] = true;
                    }
                }
            }

            $extras = $manifest['master_aluno'] ?? null;
            if (!is_array($extras)) {
                continue;
            }
            foreach ($extras as $key => $label) {
                $key = (string) $key;
                if ($key === '' || !empty($vistos[$key])) {
                    continue;
                }
                $rotas = [];
                if (!empty($manifest['rotas']) && is_array($manifest['rotas'])) {
                    foreach ($manifest['rotas'] as $prefix => $featureKey) {
                        if ((string) $featureKey === $key) {
                            $rotas[(string) $prefix] = $key;
                        }
                    }
                }
                $lista[] = self::normalizar([
                    'chave' => $key,
                    'nome' => (string) $label,
                    'feature_keys' => [$key],
                    'rotas' => $rotas,
                    'rotas_pais' => self::rotasPaisDoManifest($manifest, [$key]),
                ]);
                $vistos[$key] = true;
            }
        }

        usort($lista, static function (array $a, array $b): int {
            return strcasecmp($a['nome'], $b['nome']);
        });

        self::$cache = $lista;
        return $lista;
    }

    /**
     * Prefixo de rota => feature key (aluno, professor, admin).
     *
     * @return array<string, string>
     */
    public static function rotasPrefixo(): array
    {
        $map = [];
        foreach (self::todos() as $mod) {
            foreach ($mod['rotas'] as $prefix => $featureKey) {
                $prefix = (string) $prefix;
                $featureKey = (string) $featureKey;
                if ($prefix === '' || $featureKey === '') {
                    continue;
                }
                $map[$prefix] = $featureKey;
            }
        }
        return $map;
    }

    /**
     * @return list<array{pattern: string, feature_key: string}>
     */
    public static function rotasPais(): array
    {
        $out = [];
        foreach (self::todos() as $mod) {
            foreach ($mod['rotas_pais'] as $row) {
                $pattern = (string) ($row['pattern'] ?? '');
                $featureKey = (string) ($row['feature_key'] ?? '');
                if ($pattern === '' || $featureKey === '') {
                    continue;
                }
                $out[] = ['pattern' => $pattern, 'feature_key' => $featureKey];
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $modules POST modules[chave]
     * @param list<string> $validValues
     * @return array<string, string> config_layout module_* => 1|0|2
     */
    public static function configFromPost(array $modules, array $validValues): array
    {
        $config = [];
        foreach (self::todos() as $mod) {
            $fallback = self::valorPadrao($mod);
            $raw = isset($modules[$mod['chave']]) ? (string) $modules[$mod['chave']] : $fallback;
            $value = in_array($raw, $validValues, true) ? $raw : $fallback;
            foreach ($mod['feature_keys'] as $backendKey) {
                $config['module_' . $backendKey] = $value;
            }
        }
        return $config;
    }

    /**
     * @param array{feature_keys: list<string>} $mod
     */
    public static function valorPadrao(array $mod): string
    {
        $first = (string) ($mod['feature_keys'][0] ?? '');
        if ($first === '') {
            return '1';
        }
        return ModuloRegistry::featureDefault($first);
    }

    /**
     * @return list<array{
     *   chave: string,
     *   nome: string,
     *   feature_keys: list<string>,
     *   rotas: array<string, string>,
     *   rotas_pais: list<array{pattern: string, feature_key: string}>
     * }>
     */
    private static function modulosNativos(): array
    {
        $itens = [
            [
                'chave' => 'professor_ai_agents',
                'nome' => 'Agente de IA',
                'feature_keys' => ['professor_ai_agents'],
                'rotas' => [
                    '/professor/ai-agents' => 'professor_ai_agents',
                ],
            ],
            [
                'chave' => 'geral_aulas_online',
                'nome' => 'Aulas Online',
                'feature_keys' => ['aulas_online'],
                'rotas' => [
                    '/aluno/aulas-online' => 'aulas_online',
                    '/admin/aulas-online' => 'aulas_online',
                ],
            ],
            [
                'chave' => 'geral_cadastro_aluno_simples',
                'nome' => 'Cadastro simples de aluno',
                'feature_keys' => ['cadastro_aluno_simples'],
                'rotas' => [],
            ],
            [
                'chave' => 'geral_chat_professor',
                'nome' => 'Chat com Professor',
                'feature_keys' => ['chat_professor'],
                'rotas' => [
                    '/chat-professor' => 'chat_professor',
                    '/professor/chat' => 'chat_professor',
                ],
            ],
            [
                'chave' => 'geral_ead',
                'nome' => 'EAD / AVA',
                'feature_keys' => ['ead'],
                'rotas' => [
                    '/cursos' => 'ead',
                    '/professor/ava' => 'ead',
                    '/admin/ava' => 'ead',
                ],
            ],
            [
                'chave' => 'educa_hits',
                'nome' => 'EducaHits',
                'feature_keys' => ['educa_hits'],
                'rotas' => [
                    '/hits' => 'educa_hits',
                ],
            ],
            [
                'chave' => 'educalabs',
                'nome' => 'EducaLabs',
                'feature_keys' => ['educalabs'],
                'rotas' => [
                    '/educalabs' => 'educalabs',
                ],
            ],
            [
                'chave' => 'educa_livros',
                'nome' => 'EducaLivro',
                'feature_keys' => ['educa_livros'],
                'rotas' => [
                    '/livros' => 'educa_livros',
                ],
            ],
            [
                'chave' => 'geral_inclusao',
                'nome' => 'EducaInclui',
                'feature_keys' => ['inclusao'],
                'rotas' => [
                    '/admin/inclusao' => 'inclusao',
                ],
            ],
            [
                'chave' => 'exercicios',
                'nome' => 'Exercícios por Banco de Dados',
                'feature_keys' => ['exercicios'],
                'rotas' => [
                    '/exercicios' => 'exercicios',
                    '/admin/exercises' => 'exercicios',
                ],
            ],
            [
                'chave' => 'exercicios_ia',
                'nome' => 'Exercícios por IA',
                'feature_keys' => ['exercicios_ia'],
                'rotas' => [
                    '/exercicios-personalizados' => 'exercicios_ia',
                ],
            ],
            [
                'chave' => 'aluno_flashcards',
                'nome' => 'FlashCard',
                'feature_keys' => ['aluno_flashcards'],
                'rotas' => [
                    '/flashcards' => 'aluno_flashcards',
                ],
            ],
            [
                'chave' => 'forum',
                'nome' => 'Fórum',
                'feature_keys' => ['forum'],
                'rotas' => [
                    '/forum' => 'forum',
                ],
            ],
            [
                'chave' => 'jogos',
                'nome' => 'Games',
                'feature_keys' => ['jogos'],
                'rotas' => [
                    '/jogos' => 'jogos',
                    '/jogo-milhao' => 'jogos',
                ],
            ],
            [
                'chave' => 'ingles',
                'nome' => 'Inglês',
                'feature_keys' => ['ingles'],
                'rotas' => [
                    '/ingles' => 'ingles',
                ],
            ],
            [
                'chave' => 'geral_jornada',
                'nome' => 'Jornada do Aluno',
                'feature_keys' => ['jornadas', 'professor_jornadas'],
                'rotas' => [
                    '/jornadas' => 'jornadas',
                    '/professor/jornadas' => 'jornadas',
                    '/admin/jornadas' => 'jornadas',
                    '/admin/journeys' => 'jornadas',
                ],
                'rotas_pais' => [
                    ['pattern' => '#/pais/filhos/\d+/jornadas(?:/|$)#', 'feature_key' => 'jornadas'],
                ],
            ],
            [
                'chave' => 'geral_redacao_orientada',
                'nome' => 'Jornada da Redação',
                'feature_keys' => ['redacao_configuravel', 'aluno_redacao_configuravel', 'professor_redacao_configuravel'],
                'rotas' => [
                    '/jornada-redacao' => 'redacao_configuravel',
                    '/professor/redacao-configuravel' => 'professor_redacao_configuravel',
                    '/admin/redacao-configuravel' => 'redacao_configuravel',
                ],
                'rotas_pais' => [
                    ['pattern' => '#/pais/filhos/\d+/redacoes(?:/|$)#', 'feature_key' => 'redacao_configuravel'],
                ],
            ],
            [
                'chave' => 'geral_links_uteis',
                'nome' => 'Links Úteis',
                'feature_keys' => ['aluno_links_uteis', 'professor_links_uteis'],
                'rotas' => [],
            ],
            [
                'chave' => 'aluno_caderno_novo',
                'nome' => 'Meu Caderno',
                'feature_keys' => ['aluno_caderno_novo'],
                'rotas' => [
                    '/notes' => 'aluno_caderno_novo',
                    '/caderno' => 'aluno_caderno_novo',
                ],
            ],
            [
                'chave' => 'aluno_minicursos',
                'nome' => 'Mini Cursos',
                'feature_keys' => ['aluno_minicursos'],
                'rotas' => [
                    '/minicursos' => 'aluno_minicursos',
                ],
            ],
            [
                'chave' => 'geral_apostilas',
                'nome' => 'Minha Apostila',
                'feature_keys' => ['aluno_apostilas', 'professor_apostilas'],
                'rotas' => [
                    '/aluno/apostilas' => 'aluno_apostilas',
                    '/aluno/apostilas-ia' => 'aluno_apostilas',
                    '/professor/apostilas' => 'professor_apostilas',
                    '/professor/apostilas-ia' => 'professor_apostilas',
                ],
            ],
            [
                'chave' => 'geral_mural_recados',
                'nome' => 'Mural de Recados',
                'feature_keys' => ['mural_recados'],
                'rotas' => [
                    '/mural-recados' => 'mural_recados',
                    '/professor/mural-recados' => 'mural_recados',
                    '/admin/mural-recados' => 'mural_recados',
                ],
                'rotas_pais' => [
                    ['pattern' => '#/pais/filhos/\d+/mural-recados(?:/|$)#', 'feature_key' => 'mural_recados'],
                ],
            ],
            [
                'chave' => 'professor_notifications',
                'nome' => 'Notificações',
                'feature_keys' => ['professor_notifications'],
                'rotas' => [
                    '/professor/notifications' => 'professor_notifications',
                ],
            ],
            [
                'chave' => 'geral_planos_aula',
                'nome' => 'Plano de Aula',
                'feature_keys' => ['aluno_planos_aula', 'professor_planos_aula'],
                'rotas' => [
                    '/aluno/planos-aula' => 'aluno_planos_aula',
                    '/professor/planos-aula' => 'professor_planos_aula',
                    '/admin/planos-aula' => 'professor_planos_aula',
                ],
                'rotas_pais' => [
                    ['pattern' => '#/pais/filhos/\d+/plano-aula(?:/|$)#', 'feature_key' => 'aluno_planos_aula'],
                ],
            ],
            [
                'chave' => 'geral_provas',
                'nome' => 'Provas',
                'feature_keys' => ['aluno_provas', 'professor_provas'],
                'rotas' => [
                    '/aluno/provas' => 'aluno_provas',
                    '/professor/provas' => 'professor_provas',
                    '/professor/provas-bimestral' => 'professor_provas',
                    '/professor/provas-professor' => 'professor_provas',
                    '/admin/provas' => 'professor_provas',
                    '/admin/provas-professor' => 'professor_provas',
                ],
            ],
            [
                'chave' => 'redacoes',
                'nome' => 'Redação',
                'feature_keys' => ['redacoes'],
                'rotas' => [
                    '/redacoes' => 'redacoes',
                ],
            ],
            [
                'chave' => 'professor_redacao_livre',
                'nome' => 'Redação Livre',
                'feature_keys' => ['professor_redacao_livre'],
                'rotas' => [
                    '/professor/redacao-livre' => 'professor_redacao_livre',
                ],
            ],
            [
                'chave' => 'simulados',
                'nome' => 'Simulados',
                'feature_keys' => ['simulados'],
                'rotas' => [
                    '/simulados' => 'simulados',
                ],
            ],
            [
                'chave' => 'professor_gerar_slides',
                'nome' => 'Slides',
                'feature_keys' => ['professor_gerar_slides'],
                'rotas' => [
                    '/professor/gerar-slides' => 'professor_gerar_slides',
                ],
            ],
            [
                'chave' => 'chat',
                'nome' => 'Tudinha (chat)',
                'feature_keys' => ['chat'],
                'rotas' => [
                    '/chat' => 'chat',
                ],
            ],
            [
                'chave' => 'vlibras',
                'nome' => 'VLibras (Libras)',
                'feature_keys' => ['vlibras'],
                'rotas' => [],
            ],
        ];

        $out = [];
        foreach ($itens as $item) {
            $out[] = self::normalizar($item);
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $mod
     * @return array{
     *   chave: string,
     *   nome: string,
     *   feature_keys: list<string>,
     *   rotas: array<string, string>,
     *   rotas_pais: list<array{pattern: string, feature_key: string}>
     * }
     */
    private static function normalizar(array $mod): array
    {
        $rotas = [];
        foreach (($mod['rotas'] ?? []) as $prefix => $featureKey) {
            if ($featureKey === null || $featureKey === '') {
                continue;
            }
            $rotas[(string) $prefix] = (string) $featureKey;
        }
        $rotasPais = [];
        foreach (($mod['rotas_pais'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $pattern = (string) ($row['pattern'] ?? '');
            $featureKey = (string) ($row['feature_key'] ?? '');
            if ($pattern === '' || $featureKey === '') {
                continue;
            }
            $rotasPais[] = ['pattern' => $pattern, 'feature_key' => $featureKey];
        }
        $keys = [];
        foreach (($mod['feature_keys'] ?? []) as $key) {
            $key = (string) $key;
            if ($key !== '') {
                $keys[] = $key;
            }
        }
        return [
            'chave' => (string) ($mod['chave'] ?? ''),
            'nome' => (string) ($mod['nome'] ?? ''),
            'feature_keys' => $keys,
            'rotas' => $rotas,
            'rotas_pais' => $rotasPais,
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @param list<string> $keys
     * @return list<array{pattern: string, feature_key: string}>
     */
    private static function rotasPaisDoManifest(array $manifest, array $keys): array
    {
        $out = [];
        $permitidas = array_fill_keys($keys, true);
        $menuPais = $manifest['menu']['pais'] ?? [];
        if (!is_array($menuPais)) {
            return $out;
        }
        foreach ($menuPais as $item) {
            if (!is_array($item) || empty($item['path'])) {
                continue;
            }
            $featureKey = isset($item['feature_key']) ? (string) $item['feature_key'] : '';
            if ($featureKey === '' || empty($permitidas[$featureKey])) {
                continue;
            }
            $pattern = self::pathPaisParaRegex((string) $item['path']);
            if ($pattern === null) {
                continue;
            }
            $out[] = ['pattern' => $pattern, 'feature_key' => $featureKey];
        }

        $chave = (string) ($manifest['chave'] ?? '');
        if ($chave === 'drive' && isset($permitidas['drive'])) {
            $out[] = [
                'pattern' => '#/pais/filhos/\d+/drive(?:/|$)#',
                'feature_key' => 'drive',
            ];
        }

        return $out;
    }

    private static function pathPaisParaRegex(string $path): ?string
    {
        $path = '/' . trim($path, '/');
        if (strpos($path, '/pais/') !== 0) {
            return null;
        }
        $quoted = preg_quote($path, '#');
        $quoted = str_replace('\\{id\\}', '\\d+', $quoted);
        return '#^' . $quoted . '(?:/|$)#';
    }
}
