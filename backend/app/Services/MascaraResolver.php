<?php

require_once __DIR__ . '/../Helpers/CatalogoRegraMascara.php';
require_once __DIR__ . '/../Models/EducaInclui/MascaraAluno.php';
require_once __DIR__ . '/../Models/EducaInclui/RegraMascara.php';

if (!class_exists('MascaraResolver')) {
/**
 * EducaInclui — resolve a Máscara de Acessibilidade efetiva de um aluno.
 *
 * A "aprovação humana" das adaptações de ACESSO é a aprovação da máscara pela
 * coordenação/AEE (status = 'ativa'). Só máscaras ativas e vigentes são aplicadas.
 */
class MascaraResolver
{
    /**
     * @return array{active:bool, mascara_id:int, rules:array<string,string>}
     */
    public static function resolveForAluno(int $alunoId): array
    {
        $empty = ['active' => false, 'mascara_id' => 0, 'rules' => []];
        if ($alunoId <= 0) {
            return $empty;
        }

        try {
            $accommodation = (new MascaraAluno())->getActiveByAluno($alunoId);
            if (!$accommodation) {
                return $empty;
            }
            $rules = (new RegraMascara())->getMapaPorMascara((int) $accommodation['id']);
            // Mantém só chaves conhecidas (Fase 1) para não aplicar lixo.
            $rules = array_intersect_key($rules, array_flip(CatalogoRegraMascara::validKeys()));
            if ($rules === []) {
                return $empty;
            }
            return [
                'active' => true,
                'mascara_id' => (int) $accommodation['id'],
                'rules' => $rules,
            ];
        } catch (Throwable $e) {
            // Falha de schema/tabela ausente não pode quebrar a prova.
            error_log('MascaraResolver: ' . $e->getMessage());
            return $empty;
        }
    }

    public static function isOn(array $rules, string $key): bool
    {
        if (!array_key_exists($key, $rules)) {
            return false;
        }
        $v = strtolower(trim((string) $rules[$key]));
        return in_array($v, ['1', 'true', 'on', 'sim', 'yes'], true);
    }

    public static function extraTimePct(array $rules): float
    {
        if (!isset($rules['extra_time_pct'])) {
            return 0.0;
        }
        $pct = (float) str_replace(',', '.', (string) $rules['extra_time_pct']);
        if ($pct < 0) {
            $pct = 0.0;
        }
        if ($pct > 300) {
            $pct = 300.0; // teto de segurança
        }
        return $pct;
    }

    public static function fontSize(array $rules): string
    {
        return (string) ($rules['font_size'] ?? CatalogoRegraMascara::FONT_NORMAL);
    }

    public static function fontFamily(array $rules): string
    {
        return (string) ($rules['font_family'] ?? CatalogoRegraMascara::FAMILY_PADRAO);
    }

    /**
     * Total de alternativas a manter (incluindo a correta). 0 = sem redução.
     */
    public static function reduceOptionsKeep(array $rules): int
    {
        if (!isset($rules['reduce_options'])) {
            return 0;
        }
        $keep = (int) $rules['reduce_options'];
        return $keep >= 2 ? $keep : 0;
    }

    /**
     * Nº de questões da versão adaptada (clone). 0 = sem redução de questões.
     */
    public static function reduceQuestionCount(array $rules): int
    {
        if (!isset($rules['reduce_question_count'])) {
            return 0;
        }
        $n = (int) $rules['reduce_question_count'];
        return $n >= 1 ? $n : 0;
    }

    /**
     * A máscara exige gerar uma prova clonada (adaptação significativa que altera
     * o instrumento): redução de questões e/ou reescrita de enunciado por IA.
     */
    public static function requiresClone(array $rules): bool
    {
        return self::reduceQuestionCount($rules) > 0
            || self::reduceOptionsKeep($rules) > 0
            || CatalogoRegraMascara::hasAiRewriteRule($rules);
    }

    /**
     * Se a máscara exige leitor de tela / navegação por teclado, os bloqueios do
     * modo seguro (anti-cola) devem ser relaxados.
     */
    public static function requiresSecureRelax(array $rules): bool
    {
        foreach (CatalogoRegraMascara::rules() as $key => $def) {
            if (!empty($def['relax_secure']) && self::isOn($rules, $key)) {
                return true;
            }
        }
        return false;
    }
}
}
