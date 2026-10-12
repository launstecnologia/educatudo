<?php

namespace App\Core;

/**
 * Resolve handlers de rota (ex.: Admin/StudentAdminController) sem app/Controllers.
 * Procura em Modulos/, Dominio/, Painel/, API/, PWA/ e Publico/.
 */
class ControllerLocator
{
    /** @var array<string, array{path:string, fqcn:?string}>|null */
    private static $cache = null;

    /** Renomes de handler legado → chave no mapa */
    private const HANDLER_ALIASES = [
        'Admin/EnrollmentAdminController' => 'Modulos/matricula/MatriculaAdminController',
        'Public/EnrollmentPublicController' => 'Modulos/matricula/MatriculaPublicoController',
        'Admin/ConselhoAdminController' => 'Modulos/conselho-classe/ConselhoAdminController',
        'Teacher/ConselhoProfessorController' => 'Modulos/conselho-classe/ConselhoProfessorController',
        'Admin/ComplianceController' => 'Modulos/conformidade/ComplianceController',
        'Admin/RegraAcademicaAdminController' => 'Modulos/regras-academicas/RegraAcademicaAdminController',
        'Admin/FinanceController' => 'Modulos/financeiro/FinanceController',
        'Admin/FinanceAdminController' => 'Modulos/financeiro/FinanceAdminController',
        'Admin/FinanceReportController' => 'Modulos/financeiro/FinanceReportController',
        'Admin/PatrimonyAdminController' => 'Modulos/patrimonio/PatrimonyAdminController',
        'Admin/InventoryAdminController' => 'Modulos/patrimonio/InventoryAdminController',
        'EducaHits/EducaHitsPortalController' => 'Modulos/educahits/EducaHitsPortalController',
        'EducaLabs/EducaLabsController' => 'Modulos/educalabs/EducaLabsController',
        'ExternalApps/ExternalAppsController' => 'Modulos/external-apps/ExternalAppsController',
        'Games/GameController' => 'Modulos/jogos/GameController',
        'Games/MillionGameController' => 'Modulos/jogos/MillionGameController',
        'Notes/NotesController' => 'Modulos/notes/NotesController',
        'Notes/NotesTokenController' => 'Modulos/notes/NotesTokenController',
    ];

    /**
     * @return array{path:string, fqcn:?string}|null
     */
    public static function resolve(string $handlerController): ?array
    {
        $handlerController = str_replace('\\', '/', $handlerController);
        if (isset(self::HANDLER_ALIASES[$handlerController])) {
            $handlerController = self::HANDLER_ALIASES[$handlerController];
        }
        $map = self::map();
        if (isset($map[$handlerController])) {
            return $map[$handlerController];
        }

        // Fallback: só o nome do arquivo
        $name = basename($handlerController);
        foreach ($map as $key => $info) {
            if (basename($key) === $name || basename($info['path'], '.php') === $name) {
                return $info;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{path:string, fqcn:?string}>
     */
    public static function map(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = [];
        $app = dirname(__DIR__);

        // Modulos/<chave>/Controllers[/...]/Class.php
        $modulos = $app . '/Modulos';
        if (is_dir($modulos)) {
            foreach (scandir($modulos) ?: [] as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                $ctrlDir = $modulos . '/' . $dir . '/Controllers';
                if (!is_dir($ctrlDir)) {
                    continue;
                }
                self::indexDir($ctrlDir, 'Modulos/' . $dir, self::$cache);
            }
        }

        // Dominio/{Pessoas,Escola}/Controllers
        $dominio = $app . '/Dominio';
        if (is_dir($dominio)) {
            foreach (scandir($dominio) ?: [] as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                $ctrlDir = $dominio . '/' . $dir . '/Controllers';
                if (!is_dir($ctrlDir)) {
                    continue;
                }
                self::indexDir($ctrlDir, 'Dominio/' . $dir, self::$cache);
            }
            // aliases legados de rota Admin/* e Education/*
            foreach (self::$cache as $key => $info) {
                if (strpos($key, 'Dominio/') !== 0) {
                    continue;
                }
                $base = basename($key);
                if (!isset(self::$cache['Admin/' . $base])) {
                    self::$cache['Admin/' . $base] = $info;
                }
                if (!isset(self::$cache['Education/' . $base])) {
                    self::$cache['Education/' . $base] = $info;
                }
            }
        }

        // Painel/<perfil>/Controllers[/...]
        $painel = $app . '/Painel';
        if (is_dir($painel)) {
            foreach (scandir($painel) ?: [] as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                $ctrlDir = $painel . '/' . $dir . '/Controllers';
                if (!is_dir($ctrlDir)) {
                    continue;
                }
                self::indexDir($ctrlDir, 'Painel/' . $dir, self::$cache);
            }
            // Aliases legados de perfil no Painel
            $legacy = [
                'Auth' => 'Auth',
                'Master' => 'Master',
                'User' => 'User',
                'Teacher' => 'Professor',
                'Student' => 'Aluno',
                'Media' => 'Media',
                'Integrations' => 'Integrations',
                'Webhooks' => 'Webhooks',
                'Admin' => 'Admin',
            ];
            foreach ($legacy as $old => $painelDir) {
                $prefix = 'Painel/' . $painelDir . '/';
                foreach (self::$cache as $key => $info) {
                    if (strpos($key, $prefix) !== 0) {
                        continue;
                    }
                    $rest = substr($key, strlen($prefix));
                    $alias = $old . '/' . $rest;
                    // Não sobrescreve alias já definido pelo Domínio (ex.: Admin/StudentAdminController)
                    if (!isset(self::$cache[$alias])) {
                        self::$cache[$alias] = $info;
                    }
                }
            }
        }

        // Fora do Painel: API, PWA, Publico (rotas legadas Api/*, Pwa/*, Public/*)
        foreach (['API' => 'Api', 'PWA' => 'Pwa', 'Publico' => 'Public'] as $dir => $routePrefix) {
            $ctrlDir = $app . '/' . $dir . '/Controllers';
            if (!is_dir($ctrlDir)) {
                continue;
            }
            self::indexDir($ctrlDir, $dir, self::$cache);
            $prefix = $dir . '/';
            foreach (self::$cache as $key => $info) {
                if (strpos($key, $prefix) !== 0) {
                    continue;
                }
                $rest = substr($key, strlen($prefix));
                $alias = $routePrefix . '/' . $rest;
                if (!isset(self::$cache[$alias])) {
                    self::$cache[$alias] = $info;
                }
            }
        }

        return self::$cache;
    }

    /**
     * @param array<string, array{path:string, fqcn:?string}> $into
     */
    private static function indexDir(string $ctrlDir, string $keyPrefix, array &$into): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($ctrlDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || substr($file->getFilename(), -4) !== '.php') {
                continue;
            }
            $full = $file->getPathname();
            $rel = substr($full, strlen($ctrlDir) + 1);
            $rel = str_replace('\\', '/', $rel);
            $key = $keyPrefix . '/' . preg_replace('/\.php$/', '', $rel);
            $fqcn = self::readFqcn($full);
            $into[$key] = ['path' => $full, 'fqcn' => $fqcn];
            // também indexa só pelo nome da classe
            $base = preg_replace('/\.php$/', '', $file->getFilename());
            if (!isset($into[$base])) {
                $into[$base] = ['path' => $full, 'fqcn' => $fqcn];
            }
        }
    }

    private static function readFqcn(string $file): ?string
    {
        $src = @file_get_contents($file);
        if ($src === false) {
            return null;
        }
        if (!preg_match('/namespace\s+([\w\\\\]+)\s*;/', $src, $nm)) {
            return null;
        }
        if (!preg_match('/\bclass\s+(\w+)/', $src, $cm)) {
            return null;
        }
        return $nm[1] . '\\' . $cm[1];
    }
}
