<?php
require_once __DIR__ . '/AdminPermissionMatrix.php';
require_once __DIR__ . '/AdminSecretariaAccess.php';
require_once __DIR__ . '/LayoutHelper.php';
require_once __DIR__ . '/Logger.php';

/**
 * Diagnóstico consultável: item de menu × FeatureGate × permissão × perfil secretaria.
 */
class AdminMenuDiagnostico
{
    /**
     * Catálogo alinhado às sidebars principal e secretaria (ciclo de vida).
     *
     * @return list<array{grupo:string,label:string,path:string,permissao:string,feature:?string,secretaria:bool}>
     */
    public static function catalogo(): array
    {
        return [
            ['grupo' => 'Acadêmico · Estrutura', 'label' => 'Ano Letivo', 'path' => '/admin/ano-letivo', 'permissao' => 'ano_letivo', 'feature' => null, 'secretaria' => true],
            ['grupo' => 'Acadêmico · Estrutura', 'label' => 'Cursos e Séries', 'path' => '/admin/cursos-series', 'permissao' => 'curso', 'feature' => null, 'secretaria' => true],
            ['grupo' => 'Acadêmico · Estrutura', 'label' => 'Matriz Curricular', 'path' => '/admin/matrizes-curriculares', 'permissao' => 'matriz_curricular', 'feature' => 'matriz_curricular', 'secretaria' => true],
            ['grupo' => 'Acadêmico · Pessoas', 'label' => 'Professores', 'path' => '/admin/teachers', 'permissao' => 'professores', 'feature' => null, 'secretaria' => true],
            ['grupo' => 'Acadêmico · Pessoas', 'label' => 'Alunos', 'path' => '/admin/students', 'permissao' => 'alunos', 'feature' => null, 'secretaria' => true],
            ['grupo' => 'Acadêmico · Pessoas', 'label' => 'Turmas', 'path' => '/admin/turmas', 'permissao' => 'turmas', 'feature' => null, 'secretaria' => true],
            ['grupo' => 'Acadêmico · Como avalia', 'label' => 'Regras de Aprovação', 'path' => '/admin/regras-academicas', 'permissao' => 'regras_academicas', 'feature' => 'regras_academicas', 'secretaria' => true],
            ['grupo' => 'Acadêmico · Como avalia', 'label' => 'Tipos de Nota', 'path' => '/admin/provas/tipos-avaliacao', 'permissao' => 'provas_online', 'feature' => 'professor_provas', 'secretaria' => true],
            ['grupo' => 'Acadêmico · Como avalia', 'label' => 'Quadro de Notas', 'path' => '/admin/quadros-notas', 'permissao' => 'grupos_regras_notas', 'feature' => 'grupos_regras_notas', 'secretaria' => true],
            ['grupo' => 'Acadêmico · Como avalia', 'label' => 'Lançamento de Notas', 'path' => '/admin/provas', 'permissao' => 'provas_online', 'feature' => 'professor_provas', 'secretaria' => true],
            ['grupo' => 'Acadêmico · Como avalia', 'label' => 'Modelo de Boletim', 'path' => '/admin/boletins', 'permissao' => 'configuracao_boletim', 'feature' => 'boletim', 'secretaria' => true],
            ['grupo' => 'Rotina', 'label' => 'Diário de Classe', 'path' => '/admin/diario', 'permissao' => 'diario_classe', 'feature' => 'diario_classe', 'secretaria' => true],
            ['grupo' => 'Rotina', 'label' => 'Frequência', 'path' => '/admin/frequencia', 'permissao' => 'faltas', 'feature' => 'faltas', 'secretaria' => true],
            ['grupo' => 'Fechamento', 'label' => 'Painel de Fechamento', 'path' => '/admin/fechamento', 'permissao' => 'resultados_finais', 'feature' => 'resultados_finais', 'secretaria' => true],
            ['grupo' => 'Fechamento', 'label' => 'Conselho de Classe', 'path' => '/admin/conselhos', 'permissao' => 'conselho_classe', 'feature' => 'conselho_classe', 'secretaria' => true],
            ['grupo' => 'Fechamento', 'label' => 'Resultados Finais', 'path' => '/admin/resultados-finais', 'permissao' => 'resultados_finais', 'feature' => 'resultados_finais', 'secretaria' => true],
            ['grupo' => 'Secretaria', 'label' => 'Layout de Documentos', 'path' => '/admin/modelos-documentos', 'permissao' => 'modelos_documentos', 'feature' => null, 'secretaria' => true],
            ['grupo' => 'Painéis', 'label' => 'Relatórios', 'path' => '/admin/relatorios', 'permissao' => 'relatorios_gerais', 'feature' => null, 'secretaria' => true],
            ['grupo' => 'Acadêmico', 'label' => 'Implantar acadêmico', 'path' => '/admin/implantar-academico', 'permissao' => 'configuracao_boletim', 'feature' => 'boletim', 'secretaria' => true],
        ];
    }

    /**
     * @param array<string,mixed> $user
     * @return list<array<string,mixed>>
     */
    public static function avaliar(array $user): array
    {
        $db = Database::getInstance();
        $perms = AdminPermissionMatrix::effectivePermissionsForUser($db, $user);
        $isSecretaria = AdminSecretariaAccess::isSecretaria($user);
        $out = [];
        foreach (self::catalogo() as $item) {
            $motivos = [];
            $featureOn = true;
            if (!empty($item['feature']) && class_exists('LayoutHelper') && !LayoutHelper::isModuleEnabled((string) $item['feature'])) {
                $featureOn = false;
                $motivos[] = 'FeatureGate `' . $item['feature'] . '` desligado';
            }
            $permOk = !empty($perms[$item['permissao']]['visualizar']);
            if (!$permOk) {
                $motivos[] = 'sem permissão visualizar `' . $item['permissao'] . '`';
            }
            $secOk = true;
            if ($isSecretaria) {
                $secOk = AdminSecretariaAccess::requestPathIsAllowed($item['path']);
                if (!$secOk) {
                    $motivos[] = 'AdminSecretariaAccess bloqueia o prefixo';
                }
            }
            $visivel = $featureOn && $permOk && $secOk;
            $out[] = [
                'grupo' => $item['grupo'],
                'label' => $item['label'],
                'path' => $item['path'],
                'permissao' => $item['permissao'],
                'feature' => $item['feature'],
                'feature_on' => $featureOn,
                'permissao_ok' => $permOk,
                'secretaria_ok' => $secOk,
                'visivel' => $visivel,
                'motivos' => $motivos,
            ];
        }
        return $out;
    }

    /**
     * Em desenvolvimento, registra por que cada item ficou oculto.
     *
     * @param array<string,mixed> $user
     */
    public static function registrarOcultos(array $user): void
    {
        if (!defined('DEBUG') || !DEBUG) {
            return;
        }
        static $jaRegistrou = false;
        if ($jaRegistrou) {
            return;
        }
        $jaRegistrou = true;
        foreach (self::avaliar($user) as $item) {
            if (!empty($item['visivel']) || empty($item['motivos'])) {
                continue;
            }
            Logger::debug(
                'Menu oculto: ' . $item['label'] . ' (' . $item['path'] . ') — ' . implode('; ', $item['motivos']),
                [
                    'perfil' => $user['perfil_admin'] ?? '',
                    'path' => $item['path'],
                    'permissao' => $item['permissao'],
                    'feature' => $item['feature'],
                ],
                'navbar'
            );
        }
    }
}
