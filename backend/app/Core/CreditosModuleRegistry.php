<?php
/**
 * Catálogo de módulos/ações que podem consumir TudiCoins (créditos).
 * Usado pelo Master (precificação), CreditosService (tenant) e FeatureGate (UI).
 *
 * escopo_ui:
 *   - modulo_inteiro → some o módulo do menu/rota se TudiCoins off
 *   - acao           → some só o botão/ação; o módulo pai continua
 * pagador:
 *   - usuario → debita carteira do aluno/professor (ou pool da escola se modo pool)
 *   - escola  → debita sempre a carteira da escola (ex.: EducaInclui)
 */

class CreditosModuleRegistry
{
    /**
     * @var array<string, array{
     *   label: string,
     *   grupo: string,
     *   escopo_ui: 'modulo_inteiro'|'acao',
     *   feature_modules: string[],
     *   pagador: 'usuario'|'escola'
     * }>
     */
    private static $MODULES = [
        // Motor único de geração de questão por IA (app/AI/) — cobre Prova
        // Online + Jornada do Aluno, pago sempre por quem aciona (professor).
        'gerar_exercicio_ia_professor' => [
            'label' => 'Exercício/Questão via IA (Professor)',
            'grupo' => 'Exercícios',
            'escopo_ui' => 'acao',
            'feature_modules' => ['aluno_provas', 'professor_provas', 'jornadas', 'professor_jornadas'],
            'pagador' => 'usuario',
        ],
        // Mesmo motor, acionado pelo aluno (Exercício IA personalizado).
        'gerar_exercicio_ia_aluno' => [
            'label' => 'Exercício via IA (Aluno)',
            'grupo' => 'Exercícios',
            'escopo_ui' => 'modulo_inteiro',
            'feature_modules' => ['exercicios_ia', 'exercicios'],
            'pagador' => 'usuario',
        ],
        // Legado — consolidados acima em 2026-07-12. Mantidos só pra
        // isValid()/getLabel() continuarem resolvendo o nome bonito no
        // extrato de créditos (carteira_movimentacoes) de consumos antigos;
        // não recebem mais débito novo e somem da tela Master de Módulos
        // (getModuleKeys()/getModuleLabels() filtram 'legado').
        'gerar_exercicios_jornada' => [
            'label' => 'Exercício (IA na jornada)',
            'grupo' => 'Exercícios',
            'escopo_ui' => 'acao',
            'feature_modules' => ['jornadas', 'professor_jornadas'],
            'pagador' => 'usuario',
            'legado' => true,
        ],
        'gerar_questoes_prova' => [
            'label' => 'Questões de prova (IA)',
            'grupo' => 'Exercícios',
            'escopo_ui' => 'acao',
            'feature_modules' => ['aluno_provas', 'professor_provas'],
            'pagador' => 'usuario',
            'legado' => true,
        ],
        'exercicios_ia_personalizado' => [
            'label' => 'Lista de exercícios (IA)',
            'grupo' => 'Exercícios',
            'escopo_ui' => 'modulo_inteiro',
            'feature_modules' => ['exercicios_ia'],
            'pagador' => 'usuario',
            'legado' => true,
        ],
        'exercicio_ia_aluno' => [
            'label' => 'Exercício com imagem',
            'grupo' => 'Exercícios',
            'escopo_ui' => 'acao',
            'feature_modules' => ['exercicios_ia', 'exercicios'],
            'pagador' => 'usuario',
            'legado' => true,
        ],
        'tudinha_mensagem' => [
            'label' => 'Chat',
            'grupo' => 'Chat',
            'escopo_ui' => 'modulo_inteiro',
            'feature_modules' => ['chat'],
            'pagador' => 'usuario',
        ],
        'tudinha_chat_imagem' => [
            'label' => 'Chat com imagem',
            'grupo' => 'Chat',
            'escopo_ui' => 'modulo_inteiro',
            'feature_modules' => ['chat'],
            'pagador' => 'usuario',
        ],
        'tudinha_voz_realtime' => [
            'label' => 'Chat por voz (IA)',
            'grupo' => 'Chat',
            'escopo_ui' => 'modulo_inteiro',
            'feature_modules' => ['chat'],
            'pagador' => 'usuario',
        ],
        'flashcard' => [
            'label' => 'FlashCard',
            'grupo' => 'Flashcards',
            'escopo_ui' => 'modulo_inteiro',
            'feature_modules' => ['aluno_flashcards'],
            'pagador' => 'usuario',
        ],
        'flashcard_nao_entendi' => [
            'label' => 'FlashCard (explicação)',
            'grupo' => 'Flashcards',
            'escopo_ui' => 'modulo_inteiro',
            'feature_modules' => ['aluno_flashcards'],
            'pagador' => 'usuario',
        ],
        'redacao_correcao_ia' => [
            'label' => 'Correção de redação',
            'grupo' => 'Redação',
            'escopo_ui' => 'acao',
            'feature_modules' => ['redacoes', 'redacao_configuravel'],
            'pagador' => 'usuario',
        ],
        'redacao_gerar_tema_aluno' => [
            'label' => 'Geração de tema (redação)',
            'grupo' => 'Redação',
            'escopo_ui' => 'acao',
            'feature_modules' => ['redacoes'],
            'pagador' => 'usuario',
        ],
        'gerar_slides' => [
            'label' => 'Slides (professor)',
            'grupo' => 'Professor',
            'escopo_ui' => 'modulo_inteiro',
            'feature_modules' => ['professor_gerar_slides'],
            'pagador' => 'usuario',
        ],
        'ai_agents' => [
            'label' => 'Agentes de IA (TudinhaProf)',
            'grupo' => 'Professor',
            'escopo_ui' => 'modulo_inteiro',
            'feature_modules' => ['professor_ai_agents'],
            'pagador' => 'usuario',
        ],
        'app_externo_tudinha2' => [
            'label' => 'Tudinha 2.0 (app externo)',
            'grupo' => 'Apps externos',
            'escopo_ui' => 'acao',
            'feature_modules' => [],
            'pagador' => 'usuario',
        ],
        'app_externo_educaprof_prova_bimestral' => [
            'label' => 'Educa Prof - Prova bimestral',
            'grupo' => 'Apps externos',
            'escopo_ui' => 'acao',
            'feature_modules' => [],
            'pagador' => 'usuario',
        ],
        'app_externo_tudinha2_mensagem' => [
            'label' => 'Tudinha 2.0 - Mensagem (legado)',
            'grupo' => 'Apps externos',
            'escopo_ui' => 'acao',
            'feature_modules' => [],
            'pagador' => 'usuario',
        ],
        'app_externo_tudinha2_imagem' => [
            'label' => 'Tudinha 2.0 - Mensagem com imagem (legado)',
            'grupo' => 'Apps externos',
            'escopo_ui' => 'acao',
            'feature_modules' => [],
            'pagador' => 'usuario',
        ],
        'app_externo_notes_ia' => [
            'label' => 'Notes - Ação com IA',
            'grupo' => 'Apps externos',
            'escopo_ui' => 'acao',
            'feature_modules' => ['aluno_caderno_novo'],
            'pagador' => 'usuario',
        ],
        'app_externo_games_dica_ia' => [
            'label' => 'Games - Dica com IA',
            'grupo' => 'Apps externos',
            'escopo_ui' => 'acao',
            'feature_modules' => ['jogos'],
            'pagador' => 'usuario',
        ],
        'app_externo_educahits_gerar_musica' => [
            'label' => 'EducaHits - Gerar música',
            'grupo' => 'Apps externos',
            'escopo_ui' => 'acao',
            'feature_modules' => ['educa_hits'],
            'pagador' => 'usuario',
        ],
        'educainclui_analisar_laudo' => [
            'label' => 'EducaInclui — analisar laudo (OCR + IA)',
            'grupo' => 'EducaInclui',
            'escopo_ui' => 'acao',
            'feature_modules' => ['inclusao'],
            'pagador' => 'escola',
        ],
        'educainclui_gerar_prova' => [
            'label' => 'EducaInclui — gerar prova adaptada',
            'grupo' => 'EducaInclui',
            'escopo_ui' => 'acao',
            'feature_modules' => ['inclusao'],
            'pagador' => 'escola',
        ],
        'boletim_assistente_mensagem' => [
            'label' => 'Assistente de boletim (IA)',
            'grupo' => 'Boletim',
            'escopo_ui' => 'acao',
            'feature_modules' => [],
            'pagador' => 'escola',
        ],
        'provas_aluno_assistente_mensagem' => [
            'label' => 'Assistente de provas do aluno (IA)',
            'grupo' => 'Avaliações',
            'escopo_ui' => 'acao',
            'feature_modules' => [],
            'pagador' => 'escola',
        ],
    ];

    /** ID fixo da carteira da escola no tenant (user_type=escola, user_id=1). */
    public const ESCOLA_CARTEIRA_USER_ID = 1;

    /**
     * Chaves ativas (exclui módulos legados — ver comentário no array
     * $MODULES). Usado pela tela Master de Módulos e pela sincronização de
     * catálogo (construirPatchLayoutPorTabela()); módulos legados continuam
     * resolvendo normalmente via isValid()/getLabel() (usados no extrato de
     * créditos, que precisa exibir o nome de consumos antigos).
     *
     * @return string[]
     */
    public static function getModuleKeys(): array
    {
        return array_keys(array_filter(self::$MODULES, function ($meta) {
            return empty($meta['legado']);
        }));
    }

    /**
     * @return array<string, string>
     */
    public static function getModuleLabels(): array
    {
        $out = [];
        foreach (self::$MODULES as $key => $meta) {
            if (!empty($meta['legado'])) {
                continue;
            }
            $out[$key] = $meta['label'];
        }
        return $out;
    }

    public static function isValid(string $moduloKey): bool
    {
        return isset(self::$MODULES[$moduloKey]);
    }

    public static function getLabel(string $moduloKey): string
    {
        return self::$MODULES[$moduloKey]['label'] ?? $moduloKey;
    }

    public static function getGrupo(string $moduloKey): string
    {
        return self::$MODULES[$moduloKey]['grupo'] ?? 'Outros';
    }

    /** Consumo via app externo (Tudinha 2.0, Notes, Games…) vs uso dentro da plataforma. */
    public static function isAppExterno(string $moduloKey): bool
    {
        if ($moduloKey === '') {
            return false;
        }
        if (str_starts_with($moduloKey, 'app_externo_')) {
            return true;
        }
        return (self::$MODULES[$moduloKey]['grupo'] ?? '') === 'Apps externos';
    }

    public static function getTipoOrigemLabel(string $moduloKey): string
    {
        return self::isAppExterno($moduloKey) ? 'App externo' : 'Plataforma';
    }

    /**
     * @return 'modulo_inteiro'|'acao'
     */
    public static function getEscopoUi(string $moduloKey): string
    {
        return self::$MODULES[$moduloKey]['escopo_ui'] ?? 'acao';
    }

    /**
     * @return 'usuario'|'escola'
     */
    public static function getPagador(string $moduloKey): string
    {
        return self::$MODULES[$moduloKey]['pagador'] ?? 'usuario';
    }

    /**
     * @return string[]
     */
    public static function getFeatureModules(string $moduloKey): array
    {
        return self::$MODULES[$moduloKey]['feature_modules'] ?? [];
    }

    /**
     * Chaves de FeatureGate que devem sumir quando TudiCoins está desligado
     * (módulos 100% IA).
     *
     * @return string[]
     */
    public static function getFeatureModulesQueExigemTudiCoins(): array
    {
        $out = [];
        foreach (self::$MODULES as $meta) {
            if (($meta['escopo_ui'] ?? '') !== 'modulo_inteiro') {
                continue;
            }
            foreach ($meta['feature_modules'] as $fm) {
                $out[$fm] = true;
            }
        }
        return array_keys($out);
    }

    /**
     * Ação de IA disponível na UI?
     * Requer TudiCoins ligado na escola. O checkbox "Cobra" no catálogo só
     * controla débito (CreditosService::moduloCobraCredito) — desmarcado =
     * libera sem cobrar, não esconde a ação.
     */
    public static function acaoIaDisponivel(string $moduloKey): bool
    {
        if (!self::isValid($moduloKey)) {
            return false;
        }
        if (!class_exists('LayoutHelper', false)) {
            require_once __DIR__ . '/LayoutHelper.php';
        }
        return \LayoutHelper::get('creditos_habilitado', '0') === '1';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getMeta(string $moduloKey): ?array
    {
        return self::$MODULES[$moduloKey] ?? null;
    }

    /**
     * Rótulo amigável e código de transação para exibir no extrato do aluno/professor.
     *
     * @return array{label: string, codigo: ?string}
     */
    public static function formatMovimentacaoExibicao(array $mov): array
    {
        $moduloKey = trim((string) ($mov['modulo_key'] ?? ''));
        $observacao = trim((string) ($mov['observacao'] ?? ''));
        $referenciaId = trim((string) ($mov['referencia_id'] ?? ''));
        $tipo = (string) ($mov['tipo'] ?? '');

        if ($observacao !== '') {
            $label = $observacao;
        } elseif ($moduloKey !== '' && self::isValid($moduloKey)) {
            $label = self::getLabel($moduloKey);
        } else {
            $tipoLabels = [
                'recarga_mensal' => 'Recarga mensal',
                'recarga_inicial' => 'TudiCoins iniciais',
                'cortesia' => 'Cortesia',
                'compra' => 'Compra de TudiCoins',
                'consumo' => 'Consumo',
                'estorno' => 'Estorno',
                'recarga_plano' => 'Recarga do plano',
            ];
            $label = $tipoLabels[$tipo] ?? ($moduloKey !== '' ? $moduloKey : '—');
        }

        return [
            'label' => $label,
            'codigo' => $referenciaId !== '' ? $referenciaId : null,
        ];
    }
}
