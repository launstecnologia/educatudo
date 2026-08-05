<?php

namespace App\AI\Suporte;

/**
 * EducaTudo - carrega prompts da Tudinha de app/AI/Prompts/*.md,
 * com override opcional por escola em config_layout (prompt_tudinha_chat).
 */
class CarregadorPromptTudinha
{
    private const CHAVE_OVERRIDE_CHAT = 'prompt_tudinha_chat';

    public static function carregarChat(): string
    {
        $padrao = self::lerArquivo(__DIR__ . '/../Prompts/tudinha-chat.md');
        $override = self::buscarOverrideBanco(self::CHAVE_OVERRIDE_CHAT);
        if ($override !== '') {
            return $override;
        }
        return $padrao;
    }

    public static function carregarVoz(): string
    {
        return self::lerArquivo(__DIR__ . '/../Prompts/tudinha-voz.md');
    }

    private static function lerArquivo(string $caminho): string
    {
        $conteudo = file_get_contents($caminho);
        if ($conteudo === false || trim($conteudo) === '') {
            throw new \Exception("CarregadorPromptTudinha: arquivo não encontrado ou vazio ({$caminho})");
        }
        return trim($conteudo);
    }

    private static function buscarOverrideBanco(string $chave): string
    {
        try {
            if (!class_exists('Database', false)) {
                require_once __DIR__ . '/../../Core/Database.php';
            }
            $db = \Database::getInstance();
            $config = $db->fetch(
                'SELECT config_value FROM config_layout WHERE config_key = ?',
                [$chave]
            );
            if (!empty($config['config_value']) && trim($config['config_value']) !== '') {
                return trim($config['config_value']);
            }
        } catch (\Throwable $e) {
            error_log('CarregadorPromptTudinha: falha ao buscar override do banco: ' . $e->getMessage());
        }
        return '';
    }
}
