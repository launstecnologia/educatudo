<?php
/**
 * EducaTudo - Registry de módulos físicos (app/Modulos/<chave>/manifest.php).
 * Fonte única para FeatureGate (rotas) dos módulos migrados.
 */

class ModuloRegistry
{
    /** @var array<string, array>|null */
    private static $manifests = null;

    /**
     * @return array<string, array> chave => manifest
     */
    public static function all(): array
    {
        if (self::$manifests !== null) {
            return self::$manifests;
        }

        self::$manifests = [];
        $base = dirname(__DIR__) . '/Modulos';
        if (!is_dir($base)) {
            return self::$manifests;
        }

        foreach (scandir($base) ?: [] as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            $manifestPath = $base . '/' . $dir . '/manifest.php';
            if (!is_file($manifestPath)) {
                continue;
            }
            $manifest = require $manifestPath;
            if (!is_array($manifest) || empty($manifest['chave'])) {
                continue;
            }
            self::$manifests[(string) $manifest['chave']] = $manifest;
        }

        return self::$manifests;
    }

    public static function get(string $chave): ?array
    {
        $all = self::all();
        return $all[$chave] ?? null;
    }

    /**
     * Mapa prefixo de rota => feature key (ex.: /aluno/arquivos => aluno_arquivos).
     *
     * @return array<string, string>
     */
    public static function routeToFeatureKey(): array
    {
        $map = [];
        foreach (self::all() as $manifest) {
            if (empty($manifest['feature_gate']) || empty($manifest['rotas']) || !is_array($manifest['rotas'])) {
                continue;
            }
            foreach ($manifest['rotas'] as $prefix => $featureKey) {
                $map[(string) $prefix] = (string) $featureKey;
            }
        }
        return $map;
    }

    /**
     * Resolve caminho absoluto do controller em um módulo.
     * Ex.: Modulos/arquivos/ArquivosAlunoController → .../Modulos/arquivos/Controllers/ArquivosAlunoController.php
     */
    public static function resolveControllerPath(string $handlerController): ?string
    {
        // Modulos/<chave>/<ClassName>
        if (!preg_match('#^Modulos/([^/]+)/([^/]+)$#', $handlerController, $m)) {
            return null;
        }
        $chave = $m[1];
        $classFile = $m[2];
        $path = dirname(__DIR__) . '/Modulos/' . $chave . '/Controllers/' . $classFile . '.php';
        return is_file($path) ? $path : null;
    }

    /**
     * Entradas Master "geral_*" => feature keys (ex.: geral_arquivos => [aluno_arquivos, professor_arquivos]).
     * Usa master_feature_keys quando existir; senão só aluno+professor de feature_keys.
     *
     * @return array<string, list<string>>
     */
    public static function masterGeralMap(): array
    {
        $map = [];
        foreach (self::all() as $manifest) {
            $formKey = (string) ($manifest['master_form_key'] ?? '');
            if ($formKey === '') {
                continue;
            }
            if (!empty($manifest['master_feature_keys']) && is_array($manifest['master_feature_keys'])) {
                $keys = array_values(array_map('strval', $manifest['master_feature_keys']));
            } else {
                $fk = is_array($manifest['feature_keys'] ?? null) ? $manifest['feature_keys'] : [];
                $keys = array_values(array_filter([
                    isset($fk['aluno']) ? (string) $fk['aluno'] : null,
                    isset($fk['professor']) ? (string) $fk['professor'] : null,
                ]));
            }
            if ($keys === []) {
                continue;
            }
            $map[$formKey] = $keys;
        }
        return $map;
    }

    /**
     * Toggles avulsos no bloco Aluno do Master (ex.: aluno_recuperacao).
     *
     * @return array<string, string> feature_key => label
     */
    public static function masterAlunoExtras(): array
    {
        $out = [];
        foreach (self::all() as $manifest) {
            $extra = $manifest['master_aluno'] ?? null;
            if (!is_array($extra)) {
                continue;
            }
            foreach ($extra as $key => $label) {
                $out[(string) $key] = (string) $label;
            }
        }
        return $out;
    }

    /**
     * Default de feature key quando não há config_layout (ex.: aluno_recuperacao => 0).
     * Sem entrada no registry, default da plataforma é '1'.
     */
    public static function featureDefault(string $featureKey): string
    {
        foreach (self::all() as $manifest) {
            $defaults = $manifest['feature_defaults'] ?? null;
            if (!is_array($defaults) || !array_key_exists($featureKey, $defaults)) {
                continue;
            }
            $v = (string) $defaults[$featureKey];
            return ($v === '0' || $v === '2') ? $v : '1';
        }
        return '1';
    }

    /**
     * Labels Master para chaves geral_* dos módulos migrados.
     *
     * @return array<string, string>
     */
    public static function masterGeralLabels(): array
    {
        $labels = [];
        foreach (self::all() as $manifest) {
            $formKey = (string) ($manifest['master_form_key'] ?? '');
            if ($formKey === '') {
                continue;
            }
            $labels[$formKey] = (string) ($manifest['label'] ?? $formKey);
        }
        return $labels;
    }

    /**
     * Itens de menu declarados no manifest, por perfil (aluno|professor|admin).
     *
     * @return list<array{label: string, path: string, feature_key: ?string}>
     */
    public static function menuItems(string $perfil): array
    {
        $items = [];
        foreach (self::all() as $manifest) {
            $menu = $manifest['menu'][$perfil] ?? null;
            if (!is_array($menu)) {
                continue;
            }
            foreach ($menu as $item) {
                if (!is_array($item) || empty($item['path'])) {
                    continue;
                }
                $items[] = [
                    'label' => (string) ($item['label'] ?? $manifest['label'] ?? ''),
                    'path' => (string) $item['path'],
                    'feature_key' => isset($item['feature_key']) ? ($item['feature_key'] !== null ? (string) $item['feature_key'] : null) : null,
                    'modulo' => (string) $manifest['chave'],
                ];
            }
        }
        return $items;
    }
}
