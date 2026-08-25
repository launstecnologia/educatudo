<?php

namespace App\Modulos\CensoEscolar\Services;

/**
 * Carrega o leiaute versionado da edição. Nunca inventa posições/códigos oficiais.
 * O TXT só é gerado quando o arquivo de layout marcar oficial=true e tiver registros.
 */
class CensoLayoutService
{
    public function carregar(int $ano, string $etapaColeta = 'matricula_inicial'): array
    {
        $dir = dirname(__DIR__) . '/Config/Layouts/' . $ano;
        $arquivo = $dir . '/layout.php';
        $usandoPendente = false;
        if (!is_file($arquivo)) {
            $arquivo = dirname(__DIR__) . '/Config/Layouts/pendente/layout.php';
            $usandoPendente = true;
            if (!is_file($arquivo)) {
                return $this->vazio($ano, $etapaColeta, 'Arquivo de leiaute não encontrado para o ano ' . $ano . '.');
            }
        }
        $layout = require $arquivo;
        if (!is_array($layout)) {
            return $this->vazio($ano, $etapaColeta, 'Leiaute inválido.');
        }
        $layout['ano'] = $ano;
        if ($usandoPendente) {
            $layout['oficial'] = false;
            $layout['versao'] = 'pendente-oficial';
            $layout['motivo'] = 'O leiaute oficial do INEP da edição ' . $ano . ' ainda não foi importado.';
        }
        if ((empty($layout['oficial']) || empty($layout['registros'])) && $etapaColeta === 'matricula_inicial') {
            $oficial2026 = dirname(__DIR__) . '/Config/Layouts/2026/layout.php';
            if (is_file($oficial2026) && realpath($oficial2026) !== realpath($arquivo)) {
                $base = require $oficial2026;
                if (is_array($base) && !empty($base['oficial']) && !empty($base['registros'])) {
                    $layout = $base;
                    $layout['ano'] = $ano;
                    $layout['aplicado_de'] = 2026;
                    $arquivo = $oficial2026;
                    $usandoPendente = false;
                }
            }
        }
        $layout['etapa_coleta'] = $etapaColeta;
        $layout['oficial'] = !empty($layout['oficial']);
        $layout['registros'] = is_array($layout['registros'] ?? null) ? $layout['registros'] : [];
        $layout['dominios'] = is_array($layout['dominios'] ?? null) ? $layout['dominios'] : [];
        $layout['regras'] = is_array($layout['regras'] ?? null) ? $layout['regras'] : [];
        $layout['pronto_para_txt'] = $layout['oficial'] && $layout['registros'] !== [];
        $hashFonte = is_file($arquivo) ? hash_file('sha256', $arquivo) : '';
        $layout['hash_arquivo'] = $hashFonte;
        return $layout;
    }

    public function anosDisponiveis(): array
    {
        $base = dirname(__DIR__) . '/Config/Layouts';
        if (!is_dir($base)) {
            return [];
        }
        $anos = [];
        foreach (scandir($base) ?: [] as $item) {
            if (preg_match('/^\d{4}$/', $item) && is_file($base . '/' . $item . '/layout.php')) {
                $anos[] = (int) $item;
            }
        }
        rsort($anos);
        return $anos;
    }

    public function dominio(array $layout, string $tabela): array
    {
        $itens = $layout['dominios'][$tabela] ?? [];
        return is_array($itens) ? $itens : [];
    }

    /**
     * Serializa um registro somente a partir das definições oficiais do leiaute.
     */
    public function serializarRegistro(array $layout, string $tipoRegistro, array $dados): string
    {
        $def = $layout['registros'][$tipoRegistro] ?? null;
        if (!is_array($def) || empty($def['campos']) || !is_array($def['campos'])) {
            throw new \RuntimeException('Tipo de registro ausente no leiaute oficial selecionado.');
        }
        $campos = $def['campos'];
        usort($campos, static fn ($a, $b) => ((int) ($a['posicao'] ?? 0)) <=> ((int) ($b['posicao'] ?? 0)));
        $esperado = (int) ($def['total_campos'] ?? count($campos));
        $sep = (string) ($layout['separador'] ?? '|');
        $vazio = (string) ($layout['campo_vazio'] ?? '');
        $partes = [];
        foreach ($campos as $campo) {
            $chave = (string) ($campo['chave'] ?? '');
            $pos = (int) ($campo['posicao'] ?? 0);
            $valor = $dados[$chave] ?? $dados['c' . $pos] ?? $vazio;
            if ($valor === null || $valor === '') {
                $valor = $vazio;
            }
            $max = (int) ($campo['tamanho'] ?? 0);
            $texto = $this->sanitizarCampo((string) $valor, $sep, $layout);
            if ($max > 0) {
                $texto = mb_substr($texto, 0, $max, 'UTF-8');
            }
            $partes[] = $texto;
        }
        while (count($partes) < $esperado) {
            $partes[] = $vazio;
        }
        return implode($sep, array_slice($partes, 0, $esperado));
    }

    private function sanitizarCampo(string $valor, string $sep, array $layout): string
    {
        $escape = (string) ($layout['escape'] ?? '');
        $valor = str_replace(["\r", "\n"], ' ', $valor);
        if ($escape !== '') {
            $valor = str_replace($sep, $escape . $sep, $valor);
        } else {
            $valor = str_replace($sep, ' ', $valor);
        }
        return $valor;
    }

    private function vazio(int $ano, string $etapa, string $motivo): array
    {
        return [
            'ano' => $ano,
            'versao' => 'ausente',
            'etapa_coleta' => $etapa,
            'oficial' => false,
            'pronto_para_txt' => false,
            'motivo' => $motivo,
            'registros' => [],
            'dominios' => [],
            'regras' => [],
            'fonte_oficial' => 'https://www.gov.br/inep/pt-br/areas-de-atuacao/pesquisas-estatisticas-e-indicadores/censo-escolar',
        ];
    }
}
