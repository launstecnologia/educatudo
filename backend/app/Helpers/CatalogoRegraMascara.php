<?php

/**
 * EducaInclui — Catálogo de rule keys da Máscara de Acessibilidade.
 *
 * Fonte única de verdade para UI (admin/AEE), resolução da máscara e auditoria.
 * Fase 1 expõe apenas as regras já aplicadas na realização da prova.
 */
class CatalogoRegraMascara
{
    public const VISUAL_FONT_SMALL = 'pequena';
    public const VISUAL_FONT_NORMAL = 'normal';
    public const VISUAL_FONT_MEDIUM = 'media';
    public const VISUAL_FONT_LARGE = 'grande';
    public const VISUAL_FONT_XLARGE = 'extra_grande';

    public const VISUAL_CONTRAST_DEFAULT = 'padrao';
    public const VISUAL_CONTRAST_HIGH = 'alto_contraste';
    public const VISUAL_CONTRAST_INVERTED = 'invertido';
    public const VISUAL_CONTRAST_GRAYSCALE = 'cinza';

    public const VISUAL_FAMILY_DEFAULT = 'padrao';
    public const VISUAL_FAMILY_LEXEND = 'lexend';
    public const VISUAL_FAMILY_OPENDYSLEXIC = 'opendyslexic';

    public const VISUAL_SPACING_NORMAL = 'normal';
    public const VISUAL_SPACING_MEDIUM = 'medio';
    public const VISUAL_SPACING_LARGE = 'grande';

    public const VISUAL_BUTTON_NORMAL = 'normal';
    public const VISUAL_BUTTON_LARGE = 'grande';
    public const VISUAL_BUTTON_XLARGE = 'extra_grande';

    public const VISUAL_READ_SLOW = 'lenta';
    public const VISUAL_READ_NORMAL = 'normal';
    public const VISUAL_READ_FAST = 'rapida';

    public const FONT_NORMAL = 'normal';
    public const FONT_LARGE = 'grande';
    public const FONT_XLARGE = 'extra-grande';

    public const FAMILY_PADRAO = 'padrao';
    public const FAMILY_LEGIVEL = 'legivel';
    public const FAMILY_DISLEXIA = 'dislexia';

    /**
     * Regras expostas/aplicadas na Fase 1.
     *
     * @return array<string, array{
     *   label:string, group:string, input:string, help:string,
     *   options?:array<string,string>, relax_secure?:bool
     * }>
     */
    public static function rules(): array
    {
        return [
            'extra_time_pct' => [
                'label' => 'Tempo extra (%)',
                'group' => 'Tempo',
                'input' => 'number',
                'help' => 'Acréscimo no tempo da prova. Ex.: 50 = +50% de tempo.',
            ],
            'font_size' => [
                'label' => 'Tamanho da fonte',
                'group' => 'Apresentação',
                'input' => 'select',
                'options' => [
                    self::FONT_NORMAL => 'Normal',
                    self::FONT_LARGE => 'Grande',
                    self::FONT_XLARGE => 'Extra grande',
                ],
                'help' => 'Amplia o texto do enunciado e das alternativas.',
            ],
            'font_family' => [
                'label' => 'Tipo de letra',
                'group' => 'Apresentação',
                'input' => 'select',
                'options' => [
                    self::FAMILY_PADRAO => 'Padrão',
                    self::FAMILY_LEGIVEL => 'Mais legível (Atkinson Hyperlegible)',
                    self::FAMILY_DISLEXIA => 'Dislexia (OpenDyslexic)',
                ],
                'help' => 'Troca a fonte do enunciado e das alternativas por uma mais legível.',
            ],
            'high_contrast' => [
                'label' => 'Alto contraste',
                'group' => 'Apresentação',
                'input' => 'toggle',
                'help' => 'Tema escuro de alto contraste para a prova.',
            ],
            'progress_indicator' => [
                'label' => 'Barra de progresso',
                'group' => 'Apresentação',
                'input' => 'toggle',
                'help' => 'Mostra uma barra com o quanto da prova já foi respondido.',
            ],
            'hide_timer' => [
                'label' => 'Ocultar cronômetro',
                'group' => 'Tempo',
                'input' => 'toggle',
                'help' => 'Esconde o cronômetro para reduzir ansiedade (o tempo continua valendo).',
            ],
            'allow_pause_timer' => [
                'label' => 'Permitir pausar o cronômetro',
                'group' => 'Tempo',
                'input' => 'toggle',
                'help' => 'Mostra um botão para o aluno pausar/retomar o tempo (provas avulsas; não vale em blocos com término compartilhado).',
            ],
            'visual_font_size' => [
                'label' => 'Tamanho da fonte',
                'group' => 'Configurações visuais',
                'input' => 'select',
                'default' => self::VISUAL_FONT_NORMAL,
                'options' => [
                    self::VISUAL_FONT_SMALL => 'Pequena',
                    self::VISUAL_FONT_NORMAL => 'Normal',
                    self::VISUAL_FONT_MEDIUM => 'Média',
                    self::VISUAL_FONT_LARGE => 'Grande',
                    self::VISUAL_FONT_XLARGE => 'Extra grande',
                ],
                'help' => 'Define o tamanho padrão dos textos no portal do aluno e nas provas.',
            ],
            'visual_contrast' => [
                'label' => 'Alto contraste',
                'group' => 'Configurações visuais',
                'input' => 'select',
                'default' => self::VISUAL_CONTRAST_DEFAULT,
                'options' => [
                    self::VISUAL_CONTRAST_DEFAULT => 'Padrão',
                    self::VISUAL_CONTRAST_HIGH => 'Alto contraste',
                    self::VISUAL_CONTRAST_INVERTED => 'Contraste invertido',
                    self::VISUAL_CONTRAST_GRAYSCALE => 'Escala de cinza',
                ],
                'help' => 'Ajusta o contraste dos conteúdos exibidos ao aluno.',
            ],
            'visual_font_family' => [
                'label' => 'Fonte de leitura',
                'group' => 'Configurações visuais',
                'input' => 'select',
                'default' => self::VISUAL_FAMILY_DEFAULT,
                'options' => [
                    self::VISUAL_FAMILY_DEFAULT => 'Padrão do sistema',
                    self::VISUAL_FAMILY_LEXEND => 'Lexend',
                    self::VISUAL_FAMILY_OPENDYSLEXIC => 'OpenDyslexic',
                ],
                'help' => 'Permite usar uma fonte de leitura mais confortável.',
            ],
            'visual_text_spacing' => [
                'label' => 'Espaçamento de texto',
                'group' => 'Configurações visuais',
                'input' => 'select',
                'default' => self::VISUAL_SPACING_NORMAL,
                'options' => [
                    self::VISUAL_SPACING_NORMAL => 'Normal',
                    self::VISUAL_SPACING_MEDIUM => 'Médio',
                    self::VISUAL_SPACING_LARGE => 'Grande',
                ],
                'help' => 'Aumenta a distância entre linhas e letras.',
            ],
            'visual_element_spacing' => [
                'label' => 'Espaçamento entre elementos',
                'group' => 'Configurações visuais',
                'input' => 'select',
                'default' => self::VISUAL_SPACING_NORMAL,
                'options' => [
                    self::VISUAL_SPACING_NORMAL => 'Normal',
                    self::VISUAL_SPACING_MEDIUM => 'Médio',
                    self::VISUAL_SPACING_LARGE => 'Grande',
                ],
                'help' => 'Aumenta o espaço entre questões, alternativas, campos e botões.',
            ],
            'visual_button_size' => [
                'label' => 'Tamanho dos botões',
                'group' => 'Configurações visuais',
                'input' => 'select',
                'default' => self::VISUAL_BUTTON_NORMAL,
                'options' => [
                    self::VISUAL_BUTTON_NORMAL => 'Normal',
                    self::VISUAL_BUTTON_LARGE => 'Grande',
                    self::VISUAL_BUTTON_XLARGE => 'Extra grande',
                ],
                'help' => 'Aumenta a área de toque e leitura dos botões.',
            ],
            'visual_highlight_buttons' => [
                'label' => 'Destacar botões',
                'group' => 'Configurações visuais',
                'input' => 'toggle',
                'help' => 'Aplica bordas mais fortes e maior contraste em ações clicáveis.',
            ],
            'visual_highlight_focus' => [
                'label' => 'Destacar campo ativo',
                'group' => 'Configurações visuais',
                'input' => 'toggle',
                'help' => 'Destaca visualmente o campo ou botão em foco.',
            ],
            'visual_read_aloud' => [
                'label' => 'Leitura por voz',
                'group' => 'Configurações visuais',
                'input' => 'toggle',
                'help' => 'Permite leitura dos textos usando síntese de voz no navegador.',
                'relax_secure' => true,
            ],
            'visual_auto_read_page' => [
                'label' => 'Ler página automaticamente',
                'group' => 'Configurações visuais',
                'input' => 'toggle',
                'help' => 'Inicia a leitura da questão/página automaticamente ao abrir.',
                'relax_secure' => true,
            ],
            'visual_read_speed' => [
                'label' => 'Velocidade da leitura',
                'group' => 'Configurações visuais',
                'input' => 'select',
                'default' => self::VISUAL_READ_NORMAL,
                'options' => [
                    self::VISUAL_READ_SLOW => 'Lenta',
                    self::VISUAL_READ_NORMAL => 'Normal',
                    self::VISUAL_READ_FAST => 'Rápida',
                ],
                'help' => 'Define a velocidade da leitura por voz.',
            ],
            'visual_focus_mode' => [
                'label' => 'Modo foco',
                'group' => 'Configurações visuais',
                'input' => 'toggle',
                'help' => 'Reduz distrações visuais e prioriza o conteúdo principal.',
            ],
            'visual_reduce_motion' => [
                'label' => 'Reduzir animações',
                'group' => 'Configurações visuais',
                'input' => 'toggle',
                'help' => 'Desabilita transições, efeitos e movimentos automáticos.',
            ],
            'visual_highlight_cursor' => [
                'label' => 'Cursor destacado',
                'group' => 'Configurações visuais',
                'input' => 'toggle',
                'help' => 'Aumenta a visibilidade do ponteiro do mouse no portal web.',
            ],
            'screen_reader' => [
                'label' => 'Leitor de tela / navegação por teclado',
                'group' => 'Acesso',
                'input' => 'toggle',
                'help' => 'Relaxa os bloqueios do modo seguro que impedem NVDA/JAWS e atalhos de teclado.',
                'relax_secure' => true,
            ],
            'enable_tts' => [
                'label' => 'Leitura em voz alta (TTS)',
                'group' => 'Acesso',
                'input' => 'toggle',
                'help' => 'Adiciona um botão "Ouvir" em cada questão para ler o enunciado e as alternativas.',
            ],
            'highlight_keywords' => [
                'label' => 'Destacar comandos da questão',
                'group' => 'Apresentação',
                'input' => 'toggle',
                'help' => 'Realça palavras de comando (assinale, calcule, exceto, etc.) no enunciado.',
            ],
            'glossary_enabled' => [
                'label' => 'Glossário (clique na palavra)',
                'group' => 'Acesso',
                'input' => 'toggle',
                'help' => 'Permite o aluno clicar/selecionar uma palavra do enunciado para ver o significado.',
            ],
            'allow_audio_answer' => [
                'label' => 'Resposta por áudio (dissertativa)',
                'group' => 'Acesso',
                'input' => 'toggle',
                'help' => 'Adiciona um gravador nas questões dissertativas; o áudio é transcrito automaticamente para o campo de resposta.',
                'relax_secure' => true,
            ],
            'no_spelling_penalty' => [
                'label' => 'Não penalizar ortografia',
                'group' => 'Correção',
                'input' => 'toggle',
                'help' => 'Sinaliza ao corretor para não descontar nota por ortografia em questões dissertativas.',
            ],
            'reduce_options' => [
                'label' => 'Reduzir nº de alternativas',
                'group' => 'Conteúdo (significativa)',
                'input' => 'number',
                'help' => 'Gera uma versão adaptada mantendo a alternativa correta + distratores até este total (mín. 2). Exige aprovação reforçada. Não altera a pontuação da questão.',
                'significativa' => true,
                'clona_prova' => true,
            ],
            'reduce_question_count' => [
                'label' => 'Reduzir nº de questões',
                'group' => 'Conteúdo (significativa)',
                'input' => 'number',
                'help' => 'Entrega uma prova adaptada com este nº de questões (clonada). Exige aprovação reforçada (máscara) + aprovação da versão gerada. A nota é normalizada proporcionalmente para a mesma escala da prova original.',
                'significativa' => true,
                'clona_prova' => true,
            ],
            'simplified_instructions' => [
                'label' => 'Simplificar enunciados (IA)',
                'group' => 'Conteúdo (significativa)',
                'input' => 'toggle',
                'help' => 'Reescreve os enunciados com linguagem mais simples por IA, preservando o sentido. Gera prova clonada e exige aprovação reforçada + revisão da versão.',
                'significativa' => true,
                'clona_prova' => true,
            ],
            'shorten_statement' => [
                'label' => 'Encurtar enunciado (IA)',
                'group' => 'Conteúdo (significativa)',
                'input' => 'toggle',
                'help' => 'Reduz enunciados longos por IA, removendo redundâncias sem alterar dados, sentido ou resposta esperada. Gera prova clonada e exige revisão da versão.',
                'significativa' => true,
                'clona_prova' => true,
            ],
            'literal_language' => [
                'label' => 'Linguagem literal (IA)',
                'group' => 'Conteúdo (significativa)',
                'input' => 'toggle',
                'help' => 'Reescreve enunciados removendo figuras de linguagem/ambiguidade (útil para TEA). Gera prova clonada e exige aprovação reforçada + revisão da versão.',
                'significativa' => true,
                'clona_prova' => true,
            ],
        ];
    }

    /** Chaves de regra consideradas adaptações significativas (exigem aprovação reforçada). @return list<string> */
    public static function significativaKeys(): array
    {
        $keys = [];
        foreach (self::rules() as $key => $def) {
            if (!empty($def['significativa'])) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /** Há alguma regra significativa no conjunto informado? @param array<string,string> $rules */
    public static function hasSignificativaRule(array $rules): bool
    {
        foreach (self::significativaKeys() as $key) {
            if (array_key_exists($key, $rules) && trim((string) $rules[$key]) !== '') {
                return true;
            }
        }
        return false;
    }

    /** Chaves de regra válidas (Fase 1). @return list<string> */
    public static function validKeys(): array
    {
        return array_keys(self::rules());
    }

    public static function isValidKey(string $key): bool
    {
        return isset(self::rules()[$key]);
    }

    /** Regras booleanas (toggle). @return list<string> */
    public static function toggleKeys(): array
    {
        $keys = [];
        foreach (self::rules() as $key => $def) {
            if (($def['input'] ?? '') === 'toggle') {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * Mapa de fonte → fator de escala aplicado no CSS da prova.
     */
    public static function fontScaleFactor(string $value): float
    {
        switch ($value) {
            case self::VISUAL_FONT_SMALL:
                return 0.92;
            case self::VISUAL_FONT_MEDIUM:
                return 1.12;
            case self::FONT_LARGE:
            case self::VISUAL_FONT_LARGE:
                return 1.25;
            case self::FONT_XLARGE:
            case self::VISUAL_FONT_XLARGE:
                return 1.6;
            default:
                return 1.0;
        }
    }

    /**
     * Mapa do tipo de letra → família CSS (vazio = padrão do tema).
     */
    public static function fontFamilyCss(string $value): string
    {
        switch ($value) {
            case self::VISUAL_FAMILY_LEXEND:
                return "'Lexend', 'Atkinson Hyperlegible', Verdana, Arial, sans-serif";
            case self::VISUAL_FAMILY_OPENDYSLEXIC:
            case self::FAMILY_DISLEXIA:
                return "'OpenDyslexic', 'Comic Sans MS', Verdana, sans-serif";
            case self::FAMILY_LEGIVEL:
                return "'Atkinson Hyperlegible', Verdana, Arial, sans-serif";
            default:
                return '';
        }
    }

    /** Chaves que disparam reescrita de enunciado por IA (geram clone significativo). @return list<string> */
    public static function aiRewriteKeys(): array
    {
        return ['simplified_instructions', 'shorten_statement', 'literal_language'];
    }

    /** Há alguma regra de reescrita por IA ativa? @param array<string,string> $rules */
    public static function hasAiRewriteRule(array $rules): bool
    {
        foreach (self::aiRewriteKeys() as $key) {
            $v = strtolower(trim((string) ($rules[$key] ?? '')));
            if (in_array($v, ['1', 'true', 'on', 'sim', 'yes'], true)) {
                return true;
            }
        }
        return false;
    }
}
