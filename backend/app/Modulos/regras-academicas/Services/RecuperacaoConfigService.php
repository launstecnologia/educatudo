<?php

namespace App\Modulos\RegrasAcademicas\Services;

use App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig;
use App\Modulos\RegrasAcademicas\Models\RegraAcademica;

class RecuperacaoConfigService
{
    private RecuperacaoConfig $model;

    public function __construct()
    {
        $this->model = new RecuperacaoConfig();
    }

    public function model(): RecuperacaoConfig
    {
        return $this->model;
    }

    /**
     * @param array<string,mixed> $post
     * @return array{success:bool,id?:int,error?:string}
     */
    public function salvar(array $post, int $id = 0): array
    {
        if (!$this->model->tabelaPronta()) {
            return ['success' => false, 'error' => 'Rode a migration 2026_10_27_recuperacoes.sql no painel Master.'];
        }
        $dados = $this->normalizar($post);
        if (isset($dados['error'])) {
            return ['success' => false, 'error' => $dados['error']];
        }
        if (!$this->model->opcoesProntas() && $this->usaOpcaoNova($dados)) {
            return ['success' => false, 'error' => 'Rode a migration 2026_10_28_recuperacao_escolha.sql no painel Master antes de usar estas opções.'];
        }
        if ($this->model->colunaRegraPronta() && (int) ($dados['regra_id'] ?? 0) <= 0) {
            return ['success' => false, 'error' => 'Salve a regra antes de cadastrar a recuperação.'];
        }
        if (!$this->model->colunaRegraPronta() && $this->model->regrasNoAno((int) $dados['ano_letivo']) > 1) {
            return ['success' => false, 'error' => 'Rode a migration 2026_10_28_recuperacao_por_regra.sql no painel Master antes de cadastrar a recuperação desta regra.'];
        }
        if ($id > 0) {
            $atual = $this->model->findById($id);
            if ($atual === null) {
                return ['success' => false, 'error' => 'Recuperação não encontrada.'];
            }
            $regraAtual = (int) ($atual['regra_id'] ?? 0);
            $regraPost = (int) ($dados['regra_id'] ?? 0);
            if ($regraAtual > 0 && $regraPost > 0 && $regraAtual !== $regraPost) {
                return ['success' => false, 'error' => 'Esta recuperação pertence a outra regra.'];
            }
            $this->model->atualizar($id, $dados);
            return ['success' => true, 'id' => $id];
        }
        return ['success' => true, 'id' => $this->model->criar($dados)];
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>|array{error:string}
     */
    private function normalizar(array $post): array
    {
        $ano = (int) ($post['ano_letivo'] ?? 0);
        $regraId = (int) ($post['regra_id'] ?? 0);
        if ($regraId > 0) {
            $regra = (new RegraAcademica())->findById($regraId);
            if ($regra === null) {
                return ['error' => 'Regra de aprovação não encontrada.'];
            }
            $anoRegra = (int) ($regra['ano_letivo'] ?? 0);
            if ($anoRegra >= 2000 && $anoRegra <= 2100) {
                $ano = $anoRegra;
            }
        }
        if ($ano < 2000 || $ano > 2100) {
            return ['error' => 'Informe o ano letivo.'];
        }
        $nome = trim((string) ($post['nome'] ?? ''));
        if ($nome === '') {
            return ['error' => 'Informe o nome da recuperação.'];
        }
        if (mb_strlen($nome) > 120) {
            return ['error' => 'O nome pode ter no máximo 120 caracteres.'];
        }
        $quando = (string) ($post['quando'] ?? '');
        $textos = RecuperacaoConfig::textosDoAno($ano);
        if (!isset(RecuperacaoConfig::QUANDOS[$quando]) || !isset($textos['quandos'][$quando])) {
            return ['error' => 'Escolha quando essa recuperação acontece neste ano letivo.'];
        }
        $efeito = (string) ($post['efeito'] ?? '');
        if (!isset(RecuperacaoConfig::EFEITOS[$efeito])) {
            return ['error' => 'Escolha o que a nota da recuperação faz.'];
        }
        $minima = $this->nota($post['nota_minima'] ?? null);
        $maxima = $this->nota($post['nota_maxima'] ?? null);
        if ($minima === false || $maxima === false) {
            return ['error' => 'Informe a nota mínima e a nota máxima com um número válido.'];
        }
        if ($maxima <= 0) {
            return ['error' => 'A nota máxima da REC precisa ser maior que zero.'];
        }
        $aplicaEm = null;
        if ($quando === 'par') {
            $aplicaEm = (string) ($post['aplica_em'] ?? '');
            if (!isset(RecuperacaoConfig::APLICA_EM[$aplicaEm])) {
                return ['error' => (string) $textos['erro_aplica']];
            }
        }
        $formula = null;
        if ($efeito === 'formula') {
            $formula = trim((string) ($post['formula'] ?? ''));
            if ($formula === '') {
                return ['error' => 'Escreva a fórmula da recuperação.'];
            }
            if (mb_strlen($formula) > 255) {
                return ['error' => 'A fórmula pode ter no máximo 255 caracteres.'];
            }
            if (!preg_match('/^[0-9a-zA-Z_+*\/().,\s-]+$/', $formula)) {
                return ['error' => 'A fórmula só pode usar media, rec, max, min e os sinais + - * / ( ).'];
            }
        }

        return [
            'ano_letivo' => $ano,
            'regra_id' => $regraId,
            'nome' => $nome,
            'quando' => $quando,
            'nota_minima' => $minima,
            'nota_maxima' => $maxima,
            'efeito' => $efeito,
            'aplica_em' => $aplicaEm,
            'formula' => $formula,
            'permite_aumentar' => (int) ($post['permite_aumentar'] ?? 0) === 1 ? 1 : 0,
        ];
    }

    /**
     * @param array<string,mixed> $dados
     */
    private function usaOpcaoNova(array $dados): bool
    {
        return ($dados['quando'] ?? '') === 'par'
            || ($dados['efeito'] ?? '') === 'formula'
            || (int) ($dados['permite_aumentar'] ?? 0) === 1;
    }

    /**
     * @param mixed $valor
     * @return float|false
     */
    private function nota($valor)
    {
        if ($valor === null || $valor === '') {
            return false;
        }
        $texto = str_replace(',', '.', trim((string) $valor));
        if (!is_numeric($texto)) {
            return false;
        }
        $numero = round((float) $texto, 2);
        if ($numero < 0 || $numero > 1000) {
            return false;
        }
        return $numero;
    }
}
