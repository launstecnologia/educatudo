<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/ComplianceService.php';
require_once __DIR__ . '/PendencyService.php';

/**
 * EducaTudo - ComplianceAIService (IA Auditora)
 *
 * Assistente que responde perguntas em linguagem natural sobre a conformidade
 * da escola, sempre ancorado nos dados reais dos indicadores e pendências.
 * Funciona de forma determinística (sem dependência externa); a resposta é
 * gerada a partir do roteamento por intenção da pergunta.
 */
class ComplianceAIService
{
    /** @var Database */
    private $db;
    /** @var ComplianceService */
    private $compliance;
    /** @var PendencyService */
    private $pendencias;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->compliance = new ComplianceService($this->db);
        $this->pendencias = new PendencyService($this->db);
    }

    /** Perguntas sugeridas para a interface. */
    public function sugestoes(): array
    {
        return [
            'Qual a conformidade geral da escola?',
            'Quantos alunos estão com documentação pendente?',
            'Como está o preenchimento do Censo/INEP?',
            'A frequência está dentro do mínimo legal?',
            'Quais habilidades da BNCC ainda não foram trabalhadas?',
            'Estamos cumprindo os dias letivos previstos?',
            'Quais são as principais pendências?',
        ];
    }

    /**
     * @return array{resposta:string,topico:string,itens:list<string>}
     */
    public function responder(string $pergunta): array
    {
        $q = $this->normalizar($pergunta);
        $painel = $this->compliance->dashboard();
        $ind = $painel['indicadores'];

        if ($this->contem($q, ['document'])) {
            return $this->respostaIndicador('documentacao', $ind['documentacao'], 'documentacao');
        }
        if ($this->contem($q, ['censo', 'inep', 'educacenso'])) {
            return $this->respostaIndicador('censo', $ind['censo'], 'censo');
        }
        if ($this->contem($q, ['frequenc', 'falta', 'presenc'])) {
            return $this->respostaIndicador('frequencia', $ind['frequencia'], 'frequencia');
        }
        if ($this->contem($q, ['diario', 'aula', 'registro de classe'])) {
            return $this->respostaIndicador('diario', $ind['diario'], 'diario');
        }
        if ($this->contem($q, ['bncc', 'habilidad', 'cobertura', 'curricul'])) {
            return $this->respostaIndicador('bncc', $ind['bncc'], '');
        }
        if ($this->contem($q, ['calend', 'dia letivo', 'dias letivos', 'carga hor'])) {
            return $this->respostaIndicador('calendario', $ind['calendario'], '');
        }
        if ($this->contem($q, ['pendenc', 'pendente', 'falta corrigir', 'o que falta'])) {
            return $this->respostaPendencias();
        }

        return $this->respostaGeral($painel);
    }

    /**
     * @param array<string,mixed> $indicador
     * @return array{resposta:string,topico:string,itens:list<string>}
     */
    private function respostaIndicador(string $topico, array $indicador, string $areaPendencia): array
    {
        $label = (string) ($indicador['label'] ?? $topico);
        $pct = $indicador['percentual'];
        if ($pct === null) {
            return [
                'topico' => $topico,
                'resposta' => "Ainda não há dados suficientes para avaliar \"$label\". Verifique se o módulo está configurado e com lançamentos.",
                'itens' => [],
            ];
        }
        $situacao = $pct > 95 ? 'em conformidade' : ($pct >= 80 ? 'em atenção' : 'crítico');
        $resposta = "O indicador de \"$label\" está em " . number_format((float) $pct, 1, ',', '') . "% ($situacao). " . (string) ($indicador['detalhe'] ?? '');
        $itens = [];
        if ($areaPendencia !== '') {
            $lista = $this->pendencias->listar($areaPendencia, 8);
            foreach ($lista as $p) {
                $itens[] = (string) $p['titulo'] . ' — ' . (string) $p['descricao'];
            }
        }
        return ['topico' => $topico, 'resposta' => $resposta, 'itens' => $itens];
    }

    /** @return array{resposta:string,topico:string,itens:list<string>} */
    private function respostaPendencias(): array
    {
        $lista = $this->pendencias->listar('', 200);
        $porArea = [];
        foreach ($lista as $p) {
            $area = (string) $p['area_label'];
            $porArea[$area] = ($porArea[$area] ?? 0) + 1;
        }
        $itens = [];
        foreach ($porArea as $area => $n) {
            $itens[] = "$area: $n pendência(s)";
        }
        $total = count($lista);
        $resposta = $total === 0
            ? 'Não há pendências registradas no momento. A escola está em dia.'
            : "Foram encontradas $total pendência(s) no total, distribuídas por área.";
        return ['topico' => 'pendencias', 'resposta' => $resposta, 'itens' => $itens];
    }

    /**
     * @param array<string,mixed> $painel
     * @return array{resposta:string,topico:string,itens:list<string>}
     */
    private function respostaGeral(array $painel): array
    {
        $geral = $painel['conformidade_geral'];
        $resposta = $geral === null
            ? 'Ainda não há indicadores suficientes para calcular a conformidade geral.'
            : 'A conformidade geral da escola está em ' . number_format((float) $geral, 1, ',', '') . '%.';
        $itens = [];
        foreach ($painel['indicadores'] as $ind) {
            $pct = $ind['percentual'];
            $itens[] = (string) $ind['label'] . ': ' . ($pct !== null ? number_format((float) $pct, 1, ',', '') . '%' : 'indisponível');
        }
        return ['topico' => 'geral', 'resposta' => $resposta, 'itens' => $itens];
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $acentos = ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç'];
        $limpos =  ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c'];
        return str_replace($acentos, $limpos, $texto);
    }

    private function contem(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if (strpos($haystack, $this->normalizar($n)) !== false) {
                return true;
            }
        }
        return false;
    }
}
