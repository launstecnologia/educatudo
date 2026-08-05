<?php
/**
 * EducaTudo - FeatureGate
 * Verifica habilitação de módulos por rota
 */

require_once __DIR__ . '/LayoutHelper.php';
require_once __DIR__ . '/ModuloRegistry.php';

class FeatureGate
{
    /**
     * Mapa simples de prefixos de rota para módulos
     */
    private static $routeToModule = [
        // Chat Tudinha
        '/chat' => 'chat',

        // Educa Livros, Inglês, EducaLabs (aluno)
        '/livros' => 'educa_livros',
        '/ingles' => 'ingles',
        '/educalabs' => 'educalabs',

        // Jornadas (aluno, professor, admin)
        '/jornadas' => 'jornadas',
        '/professor/jornadas' => 'jornadas',
        '/admin/jornadas' => 'jornadas',
        '/admin/journeys' => 'jornadas',

        // Exercícios (rotas diretas; subrotas de /professor/jornadas/ são "jornadas", não "exercicios")
        // "-personalizados" é o fluxo de geração por IA (módulo separado exercicios_ia) —
        // precisa vir na lista pra não cair no prefixo genérico "/exercicios" (Banco de
        // Dados); a ordenação por tamanho em matchModuleByUri() já garante a prioridade.
        '/exercicios-personalizados' => 'exercicios_ia',
        '/exercicios' => 'exercicios',
        '/admin/exercises' => 'exercicios',

        // Simulados
        '/simulados' => 'simulados',

        // Redações
        '/redacoes' => 'redacoes',

        // Jogos
        '/jogos' => 'jogos',
        '/jogo-milhao' => 'jogos',

        // Flashcards (aluno)
        '/flashcards' => 'aluno_flashcards',

        // Fórum, Drive, Meu Caderno novo (aluno)
        '/forum' => 'forum',
        '/drive' => 'drive',
        '/notes' => 'aluno_caderno_novo',

        // Aulas Online (aluno e admin)
        '/aluno/aulas-online' => 'aulas_online',
        '/admin/aulas-online' => 'aulas_online',

        // EducaInclui (provas adaptativas por laudo)
        '/admin/inclusao' => 'inclusao',

        // AVA / EAD (cursos do aluno, área do professor e administração)
        '/cursos' => 'ead',
        '/professor/ava' => 'ead',
        '/admin/ava' => 'ead',
    ];

    /**
     * Rotas públicas de API (validate-token, logout) chamadas por apps externos (games, notes, educalabs).
     * Sempre permitidas: devem retornar JSON, nunca redirecionar para login/dashboard.
     */
    private static $publicApiPaths = [
        '/educalabs/validate-token',
        '/educalabs/logout',
        '/games/validate-token',
        '/games/logout',
        '/notes/validate-token',
        '/notes/logout',
    ];

    /**
     * Verifica se a URI requisitada pertence a um módulo desabilitado
     * Retorna null se permitido; caso contrário, retorna uma URL de redirecionamento segura
     */
    public static function getRedirectIfBlocked(?string $uri): ?string
    {
        $uri = $uri ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = ($path === null || $path === '') ? '/' : $path;
        if (defined('FOLDER') && FOLDER !== '' && strpos($path, FOLDER) === 0) {
            $path = substr($path, strlen(FOLDER)) ?: '/';
        }
        $path = '/' . trim($path, '/');
        foreach (self::$publicApiPaths as $apiPath) {
            if ($path === $apiPath || strpos($path, $apiPath . '?') === 0) {
                return null; // endpoint público: sempre permitir (retorna JSON)
            }
        }

        $module = self::matchModuleByUri($uri);
        if ($module === null) {
            return null; // rota não mapeada a módulos
        }

        // Inclui gate TudiCoins (módulos 100% IA somem se créditos off).
        if (LayoutHelper::isModuleEnabled($module)) {
            return null; // habilitado: permite acesso
        }
        // desabilitado, inativo ou bloqueado por TudiCoins: redireciona

        // Redireciono para dashboard apropriado por prefixo
        if (strpos($uri, '/admin') === 0) {
            return URL . '/admin/dashboard';
        }
        if (strpos($uri, '/professor') === 0) {
            return URL . '/professor/dashboard';
        }
        if (strpos($uri, '/pais') === 0) {
            return URL . '/pais/dashboard';
        }
        return URL . '/dashboard';
    }

    public static function isModuleEnabled(string $module): bool
    {
        return LayoutHelper::isModuleEnabled($module);
    }

    /**
     * Verifica se o módulo deve aparecer no menu (habilitado ou desabilitado com mensagem).
     * Inativo (2) não aparece.
     */
    public static function isModuleVisible(string $module): bool
    {
        return LayoutHelper::isModuleVisible($module);
    }
    
    /**
     * Verifica se um módulo do professor está habilitado
     */
    public static function isProfessorModuleEnabled(string $module): bool
    {
        // Rotas passam 'ai_agents' / 'gerar_slides'; layout Master usa professor_* .
        $layoutKey = strpos($module, 'professor_') === 0 ? $module : ('professor_' . $module);
        if (LayoutHelper::moduloBloqueadoPorTudiCoins($layoutKey)) {
            return false;
        }
        $key = 'module_professor_' . (strpos($module, 'professor_') === 0
            ? substr($module, strlen('professor_'))
            : $module);
        $val = LayoutHelper::get($key, null);
        if ($val === null) {
            $val = LayoutHelper::get('module_' . $layoutKey, '1');
        }
        return $val === '1' || $val === 1 || $val === true;
    }
    
    /**
     * Verifica se uma rota do professor está bloqueada por módulo desabilitado
     * Retorna o nome do módulo se bloqueado, null se permitido
     */
    public static function getBlockedProfessorModule(string $uri): ?string
    {
        // Mapeamento de rotas para módulos do professor
        $routeToModule = [
            '/professor/student' => ['module' => 'alunos', 'name' => 'Alunos'],
            '/professor/planos-aula' => ['module' => 'planos_aula', 'name' => 'Planos de Aula'],
            '/professor/jornadas' => ['module' => 'jornadas', 'name' => 'Jornada do Aluno'],
            '/professor/provas' => ['module' => 'provas', 'name' => 'Provas Online'],
            '/professor/gerar-slides' => ['module' => 'gerar_slides', 'name' => 'Gerar Slides'],
            '/professor/ai-agents' => ['module' => 'ai_agents', 'name' => 'TudinhaProf'],
            '/professor/notifications' => ['module' => 'notifications', 'name' => 'Notificações'],
            '/professor/arquivos' => ['module' => 'arquivos', 'name' => 'Arquivos'],
        ];
        
        // Verificar cada rota (ordem por especificidade)
        foreach ($routeToModule as $route => $config) {
            if (strpos($uri, $route) === 0) {
                if (!self::isProfessorModuleEnabled($config['module'])) {
                    return $config['name'];
                }
            }
        }
        
        return null;
    }

    private static function matchModuleByUri(string $uri): ?string
    {
        $map = array_merge(self::$routeToModule, ModuloRegistry::routeToFeatureKey());

        // Ordena prefixos por tamanho desc para casar o mais específico primeiro
        $prefixes = array_keys($map);
        usort($prefixes, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        foreach ($prefixes as $prefix) {
            if (strpos($uri, $prefix) === 0) {
                return $map[$prefix];
            }
        }
        return null;
    }
}


