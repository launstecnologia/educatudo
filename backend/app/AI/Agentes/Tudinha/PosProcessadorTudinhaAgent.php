<?php

namespace App\AI\Agentes\Tudinha;

use App\AI\AgenteIAInterface;
use App\AI\ContextoExecucao;
use Exception;

require_once __DIR__ . '/../../../Services/TudinhaService.php';

/**
 * EducaTudo - valida HTML, extrai memórias e persiste fatos do aluno.
 * Determinístico após a chamada de IA.
 *
 * Contexto esperado (entrada):
 *   - resposta_bruta (string, obrigatório)
 *   - aluno_id (int, opcional — se presente, salva memórias extraídas)
 * Contexto produzido (saída):
 *   - resposta_html (string)
 *   - memorias_extraidas (array)
 */
class PosProcessadorTudinhaAgent implements AgenteIAInterface
{
    public function nome(): string
    {
        return 'PosProcessadorTudinhaAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        $respostaBruta = trim((string) $contexto->get('resposta_bruta', ''));
        if ($respostaBruta === '') {
            throw new Exception('PosProcessadorTudinhaAgent: resposta_bruta vazia');
        }

        $tudinhaService = new \App\Services\TudinhaService();
        $respostaHtml = $tudinhaService->validarEConverterHTML($respostaBruta);

        $extraido = \App\Services\TudinhaService::extrairMemorias($respostaHtml);
        $respostaHtml = $extraido['html'];
        $memorias = $extraido['memorias'];

        $alunoId = (int) $contexto->get('aluno_id', 0);
        if ($alunoId > 0 && !empty($memorias)) {
            $tudinhaService->salvarMemorias($alunoId, $memorias);
        }

        return $contexto
            ->set('resposta_html', $respostaHtml)
            ->set('memorias_extraidas', $memorias);
    }
}
