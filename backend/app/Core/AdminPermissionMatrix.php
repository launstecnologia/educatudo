<?php

class AdminPermissionMatrix
{
    /**
     * Indica se a tabela admin_perfis_permissao existe no SCHEMA atual (evita 42S02 antes da migration).
     *
     * Cache estático, mas chaveado por tenant: `static` em PHP-FPM persiste por
     * toda a vida do worker (não só pelo request), e o mesmo worker atende
     * requests de escolas diferentes. Sem a chave por tenant, o resultado de
     * UMA escola vazava pra próxima escola atendida pelo mesmo worker.
     *
     * @param Database $db
     */
    public static function adminPerfisPermissaoTableExists($db): bool
    {
        static $cache = [];
        $tenantKey = defined('TENANT_ID') ? ('t' . TENANT_ID) : 'no_tenant';
        if (array_key_exists($tenantKey, $cache)) {
            return $cache[$tenantKey];
        }
        try {
            $row = $db->fetch(
                "SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'admin_perfis_permissao'
                 LIMIT 1"
            );
            $cache[$tenantKey] = !empty($row['ok']);
        } catch (\Throwable $e) {
            $cache[$tenantKey] = false;
        }

        return $cache[$tenantKey];
    }

    /**
     * @return array<string, array{label: string, prefixes: list<string>}>
     */
    public static function moduleCatalog(): array
    {
        return [
            'dashboard' => ['label' => 'Dashboard', 'prefixes' => ['/admin/dashboard']],

            'alunos' => ['label' => 'Alunos', 'prefixes' => ['/admin/students']],

            'ano_letivo' => ['label' => 'Ano Letivo', 'prefixes' => ['/admin/ano-letivo']],
            'curso' => ['label' => 'Curso', 'prefixes' => ['/admin/curso', '/admin/cursos']],
            'series' => ['label' => 'Série', 'prefixes' => ['/admin/serie']],
            'turmas' => ['label' => 'Turmas', 'prefixes' => ['/admin/turmas']],

            'exercicios' => ['label' => 'Exercícios', 'prefixes' => ['/admin/exercises']],
            'jornadas_aluno' => ['label' => 'Jornada do Aluno', 'prefixes' => ['/admin/consulta-jornadas-aluno', '/admin/jornadas']],
            'provas_online' => ['label' => 'Avaliações/Notas', 'prefixes' => ['/admin/consulta-provas-aluno', '/admin/consulta-provas-professor', '/admin/provas', '/admin/provas-professor', '/admin/blocos-modelo']],
            'assistente' => ['label' => 'Assistente', 'prefixes' => ['/admin/assistente', '/admin/assistente-provas', '/admin/consulta-assistente', '/admin/doc-sistema']],
            'redacao_professor' => ['label' => 'Jornada da Redação', 'prefixes' => ['/admin/redacao-professor']],
            'inclusao' => ['label' => 'EducaInclui (Acessibilidade)', 'prefixes' => ['/admin/inclusao']],
            'listagem_avaliacoes' => ['label' => 'Listagem', 'prefixes' => ['/admin/students/exercicio-ia']],
            'relatorio_avaliacoes' => ['label' => 'Relatório', 'prefixes' => ['/admin/reports']],

            'denuncias_forum' => ['label' => 'Denúncias Fórum', 'prefixes' => ['/forum/moderation']],
            'forum' => ['label' => 'Fórum', 'prefixes' => ['/forum']],
            'mural_recados' => ['label' => 'Mural de Recados', 'prefixes' => ['/admin/mural-recados']],
            'comunicacao_escolar' => ['label' => 'Comunicação Escolar', 'prefixes' => ['/admin/comunicacao-escolar']],
            'calendario_escolar' => ['label' => 'Calendário Escolar', 'prefixes' => ['/admin/calendario-escolar']],
            'notificacoes' => ['label' => 'Notificações', 'prefixes' => ['/admin/notifications']],
            'notificacoes_push' => ['label' => 'Notificações Push', 'prefixes' => ['/admin/push-notifications']],

            'configuracao_boletim' => ['label' => 'Configuração Boletim', 'prefixes' => ['/admin/boletim', '/admin/boletim-configuracao']],
            'notas_semanais' => ['label' => 'Quadro de Notas Semanais', 'prefixes' => ['/admin/notas-semanais']],
            'guia_boletim' => ['label' => 'Guia do Boletim', 'prefixes' => ['/admin/boletim-guia', '/admin/boletim_guia']],
            'modo_manutencao' => ['label' => 'Modo Manutenção', 'prefixes' => ['/admin/maintenance']],
            'slider_dashboard' => ['label' => 'Slider Dashboard', 'prefixes' => ['/admin/settings']],
            'dev_settings' => ['label' => 'Dev Settings (dev)', 'prefixes' => ['/admin/dev']],
            'tickets_dev' => ['label' => 'Tickets (dev)', 'prefixes' => ['/admin/dev/tickets', '/admin/tickets']],

            'apostilas_ia' => ['label' => 'IA da Apostila', 'prefixes' => ['/admin/apostilas-ia']],
            'apostilas' => ['label' => 'Minha Apostila', 'prefixes' => ['/admin/apostilas']],
            'arquivos' => ['label' => 'Arquivos', 'prefixes' => ['/admin/arquivos']],
            'expo_colag' => ['label' => 'Expo Colag', 'prefixes' => ['/admin/expo-colag']],
            'educahits_portal' => ['label' => 'EducaHits (portal)', 'prefixes' => ['/admin/educa-hits']],
            'minicursos' => ['label' => 'Minicursos', 'prefixes' => ['/admin/minicursos']],
            'planos_aula' => ['label' => 'Planos de Aula', 'prefixes' => ['/admin/planos-aula']],
            'ava' => ['label' => 'AVA / EAD', 'prefixes' => ['/admin/ava']],

            'financeiro_dashboard' => ['label' => 'Dashboard', 'prefixes' => ['/admin/financeiro']],
            'financeiro_relatorio_pagantes' => ['label' => 'Relatório Pagantes', 'prefixes' => ['/admin/financeiro/relatorio-pagantes']],

            'faltas' => ['label' => 'Faltas', 'prefixes' => ['/admin/faltas', '/admin/faltas/lancar']],
            'diario_classe' => ['label' => 'Diário de Classe', 'prefixes' => ['/admin/diario']],
            'conformidade' => ['label' => 'Painel de Conformidade', 'prefixes' => ['/admin/conformidade']],
            'calendario_letivo' => ['label' => 'Calendário Letivo', 'prefixes' => ['/admin/calendario-letivo']],
            'bncc' => ['label' => 'BNCC e Plano de Curso', 'prefixes' => ['/admin/bncc', '/admin/plano-curso']],
            'documentos_institucionais' => ['label' => 'Documentos Institucionais', 'prefixes' => ['/admin/documentos-institucionais']],
            'modelos_documentos' => ['label' => 'Modelos de Documentos', 'prefixes' => ['/admin/modelos-documentos']],
            'documentos_professor' => ['label' => 'Documentos do Professor', 'prefixes' => ['/admin/teachers-documentos']],
            'saude_academica' => ['label' => 'Saúde Acadêmica', 'prefixes' => ['/admin/saude-academica']],
            'grade_horaria' => ['label' => 'Grade Horária', 'prefixes' => ['/admin/grade-horaria']],
            'materias' => ['label' => 'Matérias', 'prefixes' => ['/admin/subjects']],
            'unidades' => ['label' => 'Unidades da Escola', 'prefixes' => ['/admin/unidades']],
            'almoxarifado' => ['label' => 'Almoxarifado', 'prefixes' => ['/admin/almoxarifado']],
            'patrimonio' => ['label' => 'Patrimônio', 'prefixes' => ['/admin/patrimonio']],
            'ocorrencias' => ['label' => 'Ocorrências', 'prefixes' => ['/admin/ocorrencias']],
            'pacotes_creditos' => ['label' => 'Pacotes de Créditos', 'prefixes' => ['/admin/creditos/pacotes']],

            'alertas_sensiveis' => ['label' => 'Alertas Sensíveis', 'prefixes' => ['/admin/monitoramento/alertas-sensiveis']],
            'alunos_online' => ['label' => 'Alunos Online', 'prefixes' => ['/admin/monitoramento']],
            'reconhecimento_facial' => ['label' => 'Reconhecimento Facial', 'prefixes' => ['/admin/reconhecimento-facial']],

            'relatorios_gerais' => ['label' => 'Gerais', 'prefixes' => ['/admin/reports']],

            'configuracao_prompt' => ['label' => 'Configuração de Prompt', 'prefixes' => ['/admin/dev/prompts-redacao']],
            'tentativas_login' => ['label' => 'Tentativas de login', 'prefixes' => ['/admin/tentativas-login', '/admin/dev/logins']],

            'administradores' => ['label' => 'Administradores', 'prefixes' => ['/admin/usuarios', '/admin/permissoes-perfis']],
            'professores' => ['label' => 'Professores', 'prefixes' => ['/admin/teachers']],
            'transferencia' => ['label' => 'Movimentação de alunos', 'prefixes' => [
                '/admin/students/transfer',
                '/admin/students/remanejamento',
                '/admin/students/transferencia-escolar',
            ]],
            'lista_chamada' => ['label' => 'Lista de Chamada', 'prefixes' => [
                '/admin/turmas/%id%/lista-chamada',
            ]],

            'acao_rapida_editar_aluno' => ['label' => 'Ação Rápida: Editar Aluno', 'prefixes' => ['/admin/students/%id%/edit']],
            'acao_rapida_acessar_aluno' => ['label' => 'Ação Rápida: Acessar como Aluno', 'prefixes' => ['/admin/students/%id%/acessar-como']],
            'acao_rapida_acessar_pai' => ['label' => 'Ação Rápida: Acessar como Pai', 'prefixes' => ['/admin/students/%id%/acessar-como-pai']],
            'acao_rapida_ativar_desativar' => ['label' => 'Ação Rápida: Ativar/Desativar Aluno', 'prefixes' => [
                '/admin/students/%id%/toggle-status',
                '/admin/students/%id%/inactivate',
                '/admin/students/%id%/activate',
            ]],
            'acao_rapida_resetar_senha' => ['label' => 'Ação Rápida: Resetar Senha', 'prefixes' => ['/admin/students/%id%/password']],
            'acao_rapida_cadastrar_responsavel' => ['label' => 'Ação Rápida: Cadastrar Responsável', 'prefixes' => ['/admin/students/cadastrar-pai']],
            'acao_rapida_analise_tudinha' => ['label' => 'Ação Rápida: Análise da Tudinha', 'prefixes' => ['/admin/students/%id%/analise-tudinha']],
            'acao_rapida_excluir_aluno' => ['label' => 'Ação Rápida: Excluir Aluno', 'prefixes' => [
                '/admin/students/%id%/excluir',
            ]],

            'responsaveis_vinculados' => ['label' => 'Responsáveis Vinculados', 'prefixes' => ['/admin/students/responsavel/atualizar', '/admin/students/cadastrar-pai']],
            'matriculas_aluno' => ['label' => 'Matrículas do Aluno', 'prefixes' => ['/admin/students/%id%/matricula', '/admin/students/%id%/matricula-sincronizar-cadastro', '/admin/students/%id%/matricula/%matricula_id%/encerrar', '/admin/turmas/%id%/vincular-aluno']],
            'processos_matricula' => ['label' => 'Processos de Matrícula', 'prefixes' => ['/admin/enrollment', '/admin/matricula', '/admin/configuracao/matricula', '/admin/configuracao/assinatura-digital']],
            'declaracoes_aluno' => ['label' => 'Documentação e Autorização do Aluno', 'prefixes' => [
                '/admin/students/%id%/declaracoes',
                '/admin/students/%id%/historico-escolar',
            ]],
            'documentos_aluno' => ['label' => 'Documentos / Checklist do Aluno', 'prefixes' => ['/admin/students/%id%/documentos']],

            'tab_relatorio' => ['label' => 'Aba Relatório', 'prefixes' => []],
            'tab_exercicios_bd' => ['label' => 'Aba Exercícios BD', 'prefixes' => []],
            'tab_exercicios_ia' => ['label' => 'Aba Exercícios IA', 'prefixes' => []],
            'tab_redacao' => ['label' => 'Aba Redação', 'prefixes' => []],
            'tab_ocorrencias' => ['label' => 'Aba Ocorrências', 'prefixes' => []],
            'tab_jornadas' => ['label' => 'Aba Jornadas', 'prefixes' => []],
            'tab_provas' => ['label' => 'Aba Provas', 'prefixes' => []],
            'tab_notas' => ['label' => 'Aba Notas', 'prefixes' => []],
            'tab_boletim' => ['label' => 'Aba Boletim', 'prefixes' => []],
            'tab_acessos' => ['label' => 'Aba Acessos', 'prefixes' => []],
        ];
    }

    /**
     * @return array<string, array{label: string, items: list<string>}>
     */
    public static function permissionSections(): array
    {
        return [
            'dashboard' => ['label' => 'Dashboard', 'items' => ['dashboard']],
            'alunos' => ['label' => 'Alunos', 'items' => ['alunos', 'assistente']],
            'academico' => ['label' => 'Acadêmico', 'items' => ['ano_letivo', 'curso', 'series', 'turmas', 'transferencia']],
            'avaliacoes' => ['label' => 'Avaliações', 'items' => ['exercicios', 'jornadas_aluno', 'provas_online', 'redacao_professor', 'inclusao', 'listagem_avaliacoes', 'relatorio_avaliacoes']],
            'comunicacao' => ['label' => 'Comunicação', 'items' => ['denuncias_forum', 'forum', 'mural_recados', 'comunicacao_escolar', 'calendario_escolar', 'notificacoes', 'notificacoes_push']],
            'configuracoes' => ['label' => 'Configurações', 'items' => ['configuracao_boletim', 'notas_semanais', 'guia_boletim', 'modo_manutencao', 'slider_dashboard', 'dev_settings', 'tickets_dev']],
            'conteudo' => ['label' => 'Conteúdo', 'items' => ['apostilas_ia', 'apostilas', 'arquivos', 'expo_colag', 'educahits_portal', 'minicursos', 'planos_aula', 'ava']],
            'financeiro' => ['label' => 'Financeiro', 'items' => ['financeiro_dashboard', 'financeiro_relatorio_pagantes']],
            'gestao_escolar' => ['label' => 'Gestão Escolar', 'items' => ['faltas', 'diario_classe', 'conformidade', 'calendario_letivo', 'bncc', 'documentos_institucionais', 'modelos_documentos', 'documentos_professor', 'saude_academica', 'grade_horaria', 'materias', 'unidades', 'almoxarifado', 'patrimonio', 'ocorrencias', 'pacotes_creditos']],
            'monitoramento' => ['label' => 'Monitoramento', 'items' => ['alertas_sensiveis', 'alunos_online', 'reconhecimento_facial']],
            'relatorios' => ['label' => 'Relatórios', 'items' => ['relatorios_gerais']],
            'sistema' => ['label' => 'Sistema', 'items' => ['configuracao_prompt', 'tentativas_login']],
            'usuarios' => ['label' => 'Usuários', 'items' => ['administradores', 'professores', 'lista_chamada']],
            'acoes_rapidas_aluno' => ['label' => 'Aluno • Ações Rápidas', 'items' => ['acao_rapida_editar_aluno', 'acao_rapida_acessar_aluno', 'acao_rapida_acessar_pai', 'acao_rapida_ativar_desativar', 'acao_rapida_resetar_senha', 'acao_rapida_cadastrar_responsavel', 'acao_rapida_analise_tudinha', 'acao_rapida_excluir_aluno']],
            'aluno_complementares' => ['label' => 'Aluno • Responsáveis e Matrículas', 'items' => ['responsaveis_vinculados', 'matriculas_aluno', 'processos_matricula', 'declaracoes_aluno', 'documentos_aluno']],
            'aluno_tabs' => ['label' => 'Aluno • Abas Internas', 'items' => ['tab_relatorio', 'tab_exercicios_bd', 'tab_exercicios_ia', 'tab_redacao', 'tab_ocorrencias', 'tab_jornadas', 'tab_provas', 'tab_notas', 'tab_boletim', 'tab_acessos']],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return [
            'visualizar' => 'Visualizar',
            'cadastrar' => 'Cadastrar',
            'alterar' => 'Alterar',
            'excluir' => 'Excluir',
        ];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public static function defaultPermissionsByProfile(string $perfil): array
    {
        $all = self::allowAll();

        switch ($perfil) {
            case 'dev':
                return $all;
            case 'diretor':
                return self::denyModules($all, ['dev_settings', 'tickets_dev', 'configuracao_prompt']);
            case 'coordenador':
                return self::onlyModules($all, [
                    'dashboard', 'alunos', 'assistente', 'ano_letivo', 'curso', 'series', 'turmas',
                    'exercicios', 'jornadas_aluno', 'provas_online', 'redacao_professor', 'inclusao', 'listagem_avaliacoes', 'relatorio_avaliacoes',
                    'denuncias_forum', 'forum', 'mural_recados', 'comunicacao_escolar', 'calendario_escolar', 'notificacoes', 'notificacoes_push',
                    'configuracao_boletim', 'notas_semanais', 'guia_boletim', 'modo_manutencao', 'slider_dashboard',
                    'apostilas_ia', 'apostilas', 'arquivos', 'expo_colag', 'minicursos', 'planos_aula', 'ava',
                    'faltas', 'diario_classe', 'conformidade', 'calendario_letivo', 'bncc', 'documentos_institucionais', 'modelos_documentos', 'documentos_professor', 'saude_academica', 'grade_horaria', 'materias', 'unidades', 'almoxarifado', 'patrimonio', 'ocorrencias', 'pacotes_creditos',
                    'alertas_sensiveis', 'alunos_online', 'reconhecimento_facial',
                    'relatorios_gerais',
                    'administradores', 'professores', 'transferencia', 'lista_chamada',
                    'acao_rapida_editar_aluno', 'acao_rapida_acessar_aluno', 'acao_rapida_acessar_pai', 'acao_rapida_ativar_desativar',
                    'acao_rapida_resetar_senha', 'acao_rapida_cadastrar_responsavel', 'acao_rapida_analise_tudinha', 'acao_rapida_excluir_aluno',
                    'responsaveis_vinculados', 'matriculas_aluno', 'processos_matricula', 'declaracoes_aluno', 'documentos_aluno',
                    'tab_relatorio', 'tab_exercicios_bd', 'tab_exercicios_ia', 'tab_redacao', 'tab_ocorrencias', 'tab_jornadas', 'tab_provas', 'tab_notas', 'tab_boletim', 'tab_acessos'
                ]);
            case 'aee':
                // Atendimento Educacional Especializado: foco no EducaInclui (vê laudo),
                // com acesso de leitura ao aluno e aos resultados das avaliações.
                return self::onlyModules($all, [
                    'dashboard', 'alunos', 'inclusao', 'provas_online',
                    'relatorio_avaliacoes', 'tab_relatorio', 'tab_provas', 'tab_notas',
                ]);
            case 'financeiro':
                return self::onlyModules($all, ['dashboard', 'financeiro_dashboard', 'financeiro_relatorio_pagantes', 'pacotes_creditos', 'tab_relatorio']);
            case 'secretaria':
                return self::onlyModules($all, [
                    'dashboard', 'alunos', 'ano_letivo', 'curso', 'series', 'turmas',
                    'exercicios', 'jornadas_aluno', 'provas_online', 'redacao_professor',
                    'faltas', 'diario_classe', 'conformidade', 'calendario_letivo', 'bncc', 'documentos_institucionais', 'modelos_documentos', 'documentos_professor', 'saude_academica', 'grade_horaria', 'materias', 'unidades', 'almoxarifado', 'patrimonio', 'ocorrencias',
                    'professores', 'transferencia', 'lista_chamada', 'reconhecimento_facial',
                    'acao_rapida_ativar_desativar',
                    'responsaveis_vinculados', 'matriculas_aluno', 'processos_matricula', 'declaracoes_aluno', 'documentos_aluno',
                    'tab_relatorio', 'tab_ocorrencias', 'tab_jornadas', 'tab_provas', 'tab_notas', 'tab_boletim', 'tab_acessos'
                ]);
            default:
                return [];
        }
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public static function sanitizePermissions($raw): array
    {
        $catalog = self::moduleCatalog();
        $actions = array_keys(self::actionLabels());
        $sanitized = [];
        if (!is_array($raw)) {
            return $sanitized;
        }

        foreach ($catalog as $moduleKey => $_moduleDef) {
            $row = $raw[$moduleKey] ?? null;
            if (!is_array($row)) {
                continue;
            }
            $sanitized[$moduleKey] = [];
            foreach ($actions as $action) {
                $sanitized[$moduleKey][$action] = !empty($row[$action]);
            }
        }

        return $sanitized;
    }

    /**
     * @return array{module_key:?string, action_key:?string}
     */
    public static function resolveModuleAndAction(string $requestUri, string $method): array
    {
        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $moduleKey = null;
        foreach (self::moduleCatalog() as $key => $def) {
            foreach ($def['prefixes'] as $prefix) {
                if ($prefix === '' || str_contains($prefix, '%')) {
                    continue;
                }
                if (strncmp($path, $prefix, strlen($prefix)) === 0) {
                    $moduleKey = $key;
                    break 2;
                }
            }
        }

        return ['module_key' => $moduleKey, 'action_key' => self::resolveAction($path, $method)];
    }

    public static function can(array $permissions, string $moduleKey, string $actionKey = 'visualizar'): bool
    {
        return !empty($permissions[$moduleKey][$actionKey]);
    }

    private static function resolveAction(string $path, string $method): string
    {
        $m = strtoupper(trim($method));
        if ($m === 'GET' || $m === 'HEAD' || $m === 'OPTIONS') {
            return 'visualizar';
        }

        // Endpoints MCP / tools JSON / assistente de consulta são leitura (POST com CSRF).
        // Endpoints MCP / assistente (POST com CSRF) são consulta/chat, não alteração de cadastro.
        if (preg_match('#/mcp(/|$)#i', $path)
            || preg_match('#/(assistente|assistente-provas)(/|$)#i', $path)
            || preg_match('#/consulta-(provas|jornadas)-aluno#i', $path)
            || preg_match('#/consulta-provas-professor#i', $path)
            || preg_match('#/consulta-assistente#i', $path)
            || preg_match('#/doc-sistema#i', $path)) {
            return 'visualizar';
        }

        if (preg_match('#/(delete|destroy|remove|excluir)(/|$)#i', $path)) {
            return 'excluir';
        }
        if (preg_match('#/(edit|update|editar|atualizar|senha|avatar|toggle|inactivate|encerrar|sincronizar)(/|$)#i', $path)) {
            return 'alterar';
        }
        if (preg_match('#/(create|store|new|novo|criar|salvar|cadastrar)(/|$)#i', $path)) {
            return 'cadastrar';
        }

        return 'alterar';
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public static function effectivePermissionsForUser(Database $db, array $user): array
    {
        $perfil = (string) ($user['perfil_admin'] ?? '');
        $base = self::defaultPermissionsByProfile($perfil);

        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return $base;
        }

        try {
            if (self::adminPerfisPermissaoTableExists($db)) {
                $row = $db->fetch(
                    'SELECT u.permissoes_admin_json, p.permissoes_json AS perfil_permissoes_json
                     FROM usuarios u
                     LEFT JOIN admin_perfis_permissao p ON p.id = u.perfil_permissao_id AND p.ativo = 1
                     WHERE u.id = ? LIMIT 1',
                    [$userId]
                );
            } else {
                $row = $db->fetch(
                    'SELECT u.permissoes_admin_json, NULL AS perfil_permissoes_json
                     FROM usuarios u
                     WHERE u.id = ? LIMIT 1',
                    [$userId]
                );
            }
        } catch (\Throwable $e) {
            return $base;
        }

        $raw = trim((string) ($row['permissoes_admin_json'] ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($row['perfil_permissoes_json'] ?? ''));
        }
        if ($raw === '') {
            return $base;
        }

        $decoded = json_decode($raw, true);
        $sanitized = self::sanitizePermissions($decoded);
        return $sanitized === [] ? $base : $sanitized;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private static function allowAll(): array
    {
        $result = [];
        foreach (array_keys(self::moduleCatalog()) as $moduleKey) {
            $result[$moduleKey] = [
                'visualizar' => true,
                'cadastrar' => true,
                'alterar' => true,
                'excluir' => true,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, array<string, bool>> $base
     * @param list<string> $keys
     * @return array<string, array<string, bool>>
     */
    private static function onlyModules(array $base, array $keys): array
    {
        $allowed = array_fill_keys($keys, true);
        foreach ($base as $module => $_actions) {
            if (!isset($allowed[$module])) {
                $base[$module] = [
                    'visualizar' => false,
                    'cadastrar' => false,
                    'alterar' => false,
                    'excluir' => false,
                ];
            }
        }

        return $base;
    }

    /**
     * @param array<string, array<string, bool>> $base
     * @param list<string> $keys
     * @return array<string, array<string, bool>>
     */
    private static function denyModules(array $base, array $keys): array
    {
        foreach ($keys as $module) {
            if (isset($base[$module])) {
                $base[$module] = [
                    'visualizar' => false,
                    'cadastrar' => false,
                    'alterar' => false,
                    'excluir' => false,
                ];
            }
        }

        return $base;
    }
}
