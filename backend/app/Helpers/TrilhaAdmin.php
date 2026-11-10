<?php

namespace App\Helpers;

/**
 * Trilha do painel admin, alinhada ao menu lateral.
 *
 * A view pode substituir a trilha automática com:
 * $trilha = [
 *     ['rotulo' => 'Alunos', 'url' => '/admin/students'],
 *     ['rotulo' => 'Novo aluno'],
 * ];
 * O último item é a página atual (sem link). "Início" é acrescentado se faltar.
 */
class TrilhaAdmin
{
    private static bool $renderizada = false;

    public static function marcarRenderizada(): void
    {
        self::$renderizada = true;
    }

    public static function jaRenderizada(): bool
    {
        return self::$renderizada;
    }

    /**
     * Rotina, fechamento, comunicação, secretaria e o detalhe do aluno.
     */
    public static function pertenceAoEscopo(string $uri): bool
    {
        $caminho = self::caminho($uri);
        if (preg_match('#/(pdf|download)(/|$)#', $caminho) === 1) {
            return false;
        }

        foreach (self::prefixosEscopo() as $prefixo) {
            if ($caminho === $prefixo || str_starts_with($caminho, $prefixo . '/')) {
                return true;
            }
        }

        return false;
    }
    /**
     * @param array<int, array<string, mixed>>|null $trilha
     * @return list<array{rotulo: string, url: ?string}>
     */
    public static function montar(string $uri, string $tituloPagina = '', ?array $trilha = null): array
    {
        if (is_array($trilha) && $trilha !== []) {
            return self::normalizarInformada($trilha);
        }

        $caminho = self::caminho($uri);
        if ($caminho === '/admin' || $caminho === '/admin/dashboard' || str_starts_with($caminho, '/admin/dashboard/')) {
            return [];
        }

        $item = self::correspondencia($caminho);
        if ($item === null) {
            $titulo = self::tituloUtil($tituloPagina, '');
            if ($titulo === null) {
                return [];
            }

            return [
                ['rotulo' => 'Início', 'url' => self::absoluta('/admin/dashboard')],
                ['rotulo' => $titulo, 'url' => null],
            ];
        }

        $itens = [
            ['rotulo' => 'Início', 'url' => self::absoluta('/admin/dashboard')],
        ];

        $grupo = $item['grupo'];
        if ($grupo !== null && $grupo !== '' && strcasecmp($grupo, $item['rotulo']) !== 0) {
            $grupoUrl = $item['grupo_url'];
            if ($grupoUrl !== null && $grupoUrl === $item['prefixo']) {
                $grupoUrl = null;
            }
            $itens[] = [
                'rotulo' => $grupo,
                'url' => $grupoUrl !== null ? self::absoluta($grupoUrl) : null,
            ];
        }

        $extra = null;
        if ($caminho !== $item['prefixo']) {
            $extra = self::rotuloExtra($tituloPagina, $item['rotulo'], $caminho, $item['prefixo']);
        }

        $itens[] = [
            'rotulo' => $item['rotulo'],
            'url' => $extra !== null ? self::absoluta($item['prefixo']) : null,
        ];

        if ($extra !== null) {
            $itens[] = ['rotulo' => $extra, 'url' => null];
        }

        return $itens;
    }

    /**
     * @param array<int, array<string, mixed>> $trilha
     * @return list<array{rotulo: string, url: ?string}>
     */
    private static function normalizarInformada(array $trilha): array
    {
        $itens = [];
        foreach ($trilha as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rotulo = trim((string) ($item['rotulo'] ?? $item['label'] ?? ''));
            if ($rotulo === '') {
                continue;
            }
            $url = isset($item['url']) ? trim((string) $item['url']) : '';
            $itens[] = [
                'rotulo' => $rotulo,
                'url' => self::urlSegura($url),
            ];
        }

        if ($itens === []) {
            return [];
        }

        if (strcasecmp($itens[0]['rotulo'], 'Início') !== 0) {
            array_unshift($itens, [
                'rotulo' => 'Início',
                'url' => self::absoluta('/admin/dashboard'),
            ]);
        }

        $ultimo = count($itens) - 1;
        $itens[$ultimo]['url'] = null;

        return $itens;
    }

    private static function rotuloExtra(string $tituloPagina, string $rotuloItem, string $caminho, string $prefixo): ?string
    {
        $titulo = self::tituloUtil($tituloPagina, $rotuloItem);
        if ($titulo !== null) {
            return $titulo;
        }

        $resto = trim(substr($caminho, strlen($prefixo)), '/');
        if ($resto === '') {
            return null;
        }

        $ultimo = strtolower((string) basename($resto));
        $acoes = [
            'create' => 'Novo',
            'novo' => 'Novo',
            'criar' => 'Novo',
            'new' => 'Novo',
            'edit' => 'Editar',
            'editar' => 'Editar',
            'update' => 'Editar',
        ];

        return $acoes[$ultimo] ?? null;
    }

    private static function tituloUtil(string $titulo, string $rotuloItem): ?string
    {
        $titulo = trim($titulo);
        if ($titulo === '') {
            return null;
        }

        $titulo = (string) preg_replace('/\s+-\s+EducaTudo\s*$/u', '', $titulo);
        $titulo = trim($titulo);
        if ($titulo === '' || strcasecmp($titulo, 'EducaTudo') === 0) {
            return null;
        }

        $genericos = [
            'painel administrativo',
            'educatudo admin',
            'dashboard admin',
        ];
        if (in_array(strtolower($titulo), $genericos, true)) {
            return null;
        }

        if ($rotuloItem !== '' && strcasecmp($titulo, $rotuloItem) === 0) {
            return null;
        }

        if (mb_strlen($titulo) > 80) {
            return null;
        }

        return $titulo;
    }

    /**
     * @return array{prefixo: string, grupo: ?string, grupo_url: ?string, rotulo: string}|null
     */
    private static function correspondencia(string $caminho): ?array
    {
        $melhor = null;
        $tamanho = -1;
        foreach (self::catalogo() as $item) {
            $prefixo = $item['prefixo'];
            $casa = $caminho === $prefixo || str_starts_with($caminho, $prefixo . '/');
            if (!$casa) {
                continue;
            }
            $len = strlen($prefixo);
            if ($len > $tamanho) {
                $tamanho = $len;
                $melhor = $item;
            }
        }

        return $melhor;
    }

    private static function caminho(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : $uri;
        $base = '';
        if (defined('URL')) {
            $basePath = parse_url((string) URL, PHP_URL_PATH);
            $base = is_string($basePath) ? rtrim($basePath, '/') : '';
        }
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : $path;
    }

    private static function urlSegura(string $url): ?string
    {
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '/')) {
            return self::absoluta($url);
        }
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return null;
    }

    private static function absoluta(string $caminho): string
    {
        $base = defined('URL') ? rtrim((string) URL, '/') : '';
        if ($caminho === '' || $caminho[0] !== '/') {
            return $base . '/' . ltrim($caminho, '/');
        }

        return $base . $caminho;
    }

    /**
     * @return list<string>
     */
    private static function prefixosEscopo(): array
    {
        return [
            '/admin/rotina',
            '/admin/diario',
            '/admin/frequencia',
            '/admin/faltas',
            '/admin/ocorrencias',
            '/admin/reconhecimento-facial',
            '/admin/fechamento',
            '/admin/conselhos',
            '/admin/resultados-finais',
            '/admin/comunicacao',
            '/admin/comunicacao-escolar',
            '/admin/calendario-escolar',
            '/admin/mural-recados',
            '/admin/reunioes',
            '/admin/notificacoes-push',
            '/admin/notifications',
            '/forum',
            '/admin/gestao-escolar',
            '/admin/censo',
            '/admin/enrollment',
            '/admin/paineis/documentos',
            '/admin/vida-escolar',
            '/admin/conformidade',
            '/admin/saude-academica',
            '/admin/students',
        ];
    }

    /**
     * @return list<array{prefixo: string, grupo: ?string, grupo_url: ?string, rotulo: string}>
     */
    private static function catalogo(): array
    {
        static $itens = null;
        if ($itens !== null) {
            return $itens;
        }

        $grupos = [
            'Acadêmico' => '/admin/academico',
            'Rotina' => '/admin/rotina',
            'Fechamento' => '/admin/fechamento',
            'Comunicação' => '/admin/comunicacao',
            'Conteúdo' => '/admin/conteudo',
            'Financeiro' => '/admin/financeiro-escolar',
            'Secretaria' => '/admin/gestao-escolar',
            'Recursos' => '/admin/almoxarifado',
            'Monitoramento' => '/admin/monitoramento-escolar',
            'Pedagógico' => '/admin/pedagogico',
            'Sistema' => '/admin/sistema',
            'Usuários' => '/admin/gestao-usuarios',
            'Configuração' => '/admin/z-configuracao',
        ];

        $linhas = [
            ['/admin/academico', null, 'Acadêmico'],
            ['/admin/assistente', null, 'Assistente'],
            ['/admin/ano-letivo', 'Acadêmico', 'Anos Letivos'],
            ['/admin/cursos-series', 'Acadêmico', 'Cursos e Séries / Anos Escolares'],
            ['/admin/cursos', 'Acadêmico', 'Cursos e Séries / Anos Escolares'],
            ['/admin/curso', 'Acadêmico', 'Cursos e Séries / Anos Escolares'],
            ['/admin/serie', 'Acadêmico', 'Cursos e Séries / Anos Escolares'],
            ['/admin/componentes-curriculares', 'Acadêmico', 'Componentes Curriculares'],
            ['/admin/matrizes-curriculares', 'Acadêmico', 'Matriz Curricular'],
            ['/admin/calendario-letivo', 'Acadêmico', 'Calendário Letivo'],
            ['/admin/teachers', 'Acadêmico', 'Professores'],
            ['/admin/salas', 'Acadêmico', 'Salas / Ambientes'],
            ['/admin/turmas', 'Acadêmico', 'Turmas'],
            ['/admin/students/remanejamento', 'Secretaria', 'Movimentação de alunos'],
            ['/admin/students/transferencia-escolar', 'Secretaria', 'Movimentação de alunos'],
            ['/admin/students/transfer', 'Secretaria', 'Movimentação de alunos'],
            ['/admin/vida-escolar', 'Secretaria', 'Vida escolar'],
            ['/admin/conformidade', 'Secretaria', 'Conformidade'],
            ['/admin/saude-academica', 'Secretaria', 'Saúde Acadêmica'],
            ['/admin/students', 'Acadêmico', 'Alunos'],
            ['/admin/grade-horaria', 'Acadêmico', 'Grade Horária'],
            ['/admin/regras-academicas', 'Acadêmico', 'Regras de Aprovação'],
            ['/admin/eventos-avaliativos/boletim', 'Acadêmico', 'Boletim'],
            ['/admin/eventos-avaliativos', 'Acadêmico', 'Eventos Avaliativos'],
            ['/admin/provas/tipos-avaliacao', 'Acadêmico', 'Tipos de Nota'],
            ['/admin/quadros-notas', 'Acadêmico', 'Quadro de Notas'],
            ['/admin/grupos-regras-notas', 'Acadêmico', 'Quadro de Notas'],
            ['/admin/provas', 'Acadêmico', 'Lançamento de Notas'],
            ['/admin/reports/boletim-coordenacao', 'Acadêmico', 'Relatórios'],
            ['/admin/boletim-configuracao', 'Acadêmico', 'Boletim'],
            ['/admin/boletim', 'Acadêmico', 'Boletim'],
            ['/admin/boletins', 'Acadêmico', 'Demonstrativo de notas'],
            ['/admin/rotina', null, 'Rotina'],
            ['/admin/diario', 'Rotina', 'Diário de Classe'],
            ['/admin/frequencia', 'Rotina', 'Frequência'],
            ['/admin/ocorrencias', 'Rotina', 'Ocorrências'],
            ['/admin/fechamento', null, 'Fechamento'],
            ['/admin/resultados-finais', 'Fechamento', 'Resultados Finais'],
            ['/admin/conselhos', 'Fechamento', 'Conselho de Classe'],
            ['/admin/expo-colag', null, 'Expo Colag'],
            ['/admin/comunicacao', null, 'Comunicação'],
            ['/admin/comunicacao-escolar', 'Comunicação', 'Comunicação Escolar'],
            ['/admin/calendario-escolar', 'Comunicação', 'Avisos à família'],
            ['/forum/moderation', 'Comunicação', 'Denúncias Fórum'],
            ['/forum', 'Comunicação', 'Fórum'],
            ['/admin/mural-recados', 'Comunicação', 'Mural de Recados'],
            ['/admin/notificacoes-push', 'Comunicação', 'Notificações Push'],
            ['/admin/notifications', 'Comunicação', 'Notificações'],
            ['/admin/reunioes', 'Comunicação', 'Reuniões'],
            ['/admin/conteudo', null, 'Conteúdo'],
            ['/admin/arquivos', 'Conteúdo', 'Arquivos'],
            ['/admin/apostilas-ia', 'Conteúdo', 'Meu Material'],
            ['/admin/financeiro-escolar', null, 'Financeiro'],
            ['/admin/finance/charges/batch', 'Financeiro', 'Nova em Lote'],
            ['/admin/finance/charges', 'Financeiro', 'Cobranças Avulsas'],
            ['/admin/finance/settings', 'Financeiro', 'Configurações'],
            ['/admin/finance/bills', 'Financeiro', 'Contas a Pagar'],
            ['/admin/finance/contracts', 'Financeiro', 'Contratos'],
            ['/admin/finance/discount-rules', 'Financeiro', 'Descontos'],
            ['/admin/finance/reports/dfc', 'Financeiro', 'DFC'],
            ['/admin/finance/reports/dlpa', 'Financeiro', 'DLPA'],
            ['/admin/finance/reports/dmpl', 'Financeiro', 'DMPL'],
            ['/admin/finance/reports/dre', 'Financeiro', 'DRE'],
            ['/admin/finance/reports/balanco', 'Financeiro', 'Balanço Patrimonial'],
            ['/admin/finance/cashflow', 'Financeiro', 'Fluxo de Caixa'],
            ['/admin/finance/report/inadimplencia', 'Financeiro', 'Inadimplência'],
            ['/admin/finance/plans', 'Financeiro', 'Planos e Preços'],
            ['/admin/finance', 'Financeiro', 'Dashboard'],
            ['/admin/gestao-escolar', null, 'Secretaria'],
            ['/admin/censo', 'Secretaria', 'Censo Escolar'],
            ['/admin/enrollment/config', 'Secretaria', 'Configuração de Matrícula'],
            ['/admin/enrollment', 'Secretaria', 'Matrículas'],
            ['/admin/paineis/documentos', 'Secretaria', 'Documentos'],
            ['/admin/almoxarifado', 'Recursos', 'Almoxarifado'],
            ['/admin/patrimonio', 'Recursos', 'Patrimônio'],
            ['/admin/monitoramento-escolar', null, 'Monitoramento'],
            ['/admin/monitoramento/alertas', 'Monitoramento', 'Alertas Sensíveis'],
            ['/admin/tentativas-login', 'Monitoramento', 'Tentativas de login'],
            ['/admin/pedagogico', null, 'Pedagógico'],
            ['/admin/aulas-online', 'Pedagógico', 'Aulas Online'],
            ['/admin/ava', 'Pedagógico', 'AVA / EAD'],
            ['/admin/bncc', 'Pedagógico', 'BNCC / Plano de Curso'],
            ['/admin/minicursos', 'Pedagógico', 'EducaCursos'],
            ['/admin/planos-aula', 'Pedagógico', 'Plano de Aula'],
            ['/admin/inclusao', 'Pedagógico', 'Avaliação Adaptativa'],
            ['/admin/redacao-professor', 'Pedagógico', 'Jornada da Redação'],
            ['/admin/jornadas', 'Pedagógico', 'Jornada do Aluno'],
            ['/admin/relatorios', 'Pedagógico', 'Relatórios'],
            ['/admin/sistema', null, 'Sistema'],
            ['/admin/avatares-alunos', 'Sistema', 'Avatares dos Alunos'],
            ['/admin/redacao-configuravel', 'Sistema', 'Configuração de Prompt'],
            ['/admin/dev/tickets', 'Sistema', 'Tickets'],
            ['/admin/gestao-usuarios', null, 'Usuários'],
            ['/admin/usuarios', 'Usuários', 'Administradores'],
            ['/admin/monitors', 'Usuários', 'Monitores'],
            ['/admin/permissoes-perfis', 'Usuários', 'Perfis de Permissão'],
            ['/admin/z-configuracao/zerar-popular', 'Configuração', 'Zerar e popular EM 2025'],
            ['/admin/z-configuracao', null, 'Configuração'],
            ['/admin/documentos-institucionais', 'Configuração', 'Documentos Institucionais'],
            ['/admin/configuracao/assinatura-digital', 'Configuração', 'Assinatura Digital'],
            ['/admin/modelos-documentos', 'Configuração', 'Layout de documentos'],
            ['/admin/tudicoins', 'Configuração', 'TudiCoins da Escola'],
            ['/admin/creditos/pacotes', 'Configuração', 'Pacotes de TudiCoins'],
            ['/admin/dev', 'Configuração', 'Dev Settings'],
            ['/admin/unidades', 'Configuração', 'Instituição'],
            ['/admin/maintenance', 'Configuração', 'Modo Manutenção'],
            ['/admin/settings', 'Configuração', 'Slider Dashboard'],
            ['/admin/resultados-finais/layouts', 'Configuração', 'Layouts'],
            ['/admin/configuracao/ui-modelos', 'Configuração', 'UI Modelos'],
            ['/admin/reconhecimento-facial', 'Rotina', 'Reconhecimento facial'],
        ];

        $itens = [];
        foreach ($linhas as $linha) {
            $grupo = $linha[1];
            $itens[] = [
                'prefixo' => $linha[0],
                'grupo' => $grupo,
                'grupo_url' => $grupo !== null ? ($grupos[$grupo] ?? null) : null,
                'rotulo' => $linha[2],
            ];
        }

        return $itens;
    }
}
