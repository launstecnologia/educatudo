<?php

namespace App\AI\Agentes\Questoes;

use App\AI\AgenteIAInterface;
use App\AI\ContextoExecucao;
use Exception;

/**
 * EducaTudo - decide qual tipo de recurso visual (se algum) faz sentido pro
 * par disciplina+assunto, quando o Blueprint pede decisão automática
 * (com_recurso_visual = 'auto'). Determinístico por regra fixa — sem
 * chamada de IA, deliberadamente, pra não somar latência/custo a cada
 * questão gerada (requisito de escala: muitos usuários simultâneos).
 * Se a regra fixa não cobrir o caso, decide "sem recurso visual" em vez de
 * arriscar uma imagem sem sentido.
 *
 * Contexto esperado (entrada):
 *   - disciplina (string, obrigatório)
 *   - assunto (string, obrigatório)
 *   - com_recurso_visual (bool|string 'auto', obrigatório)
 *   - tipo_recurso_visual (string|null, opcional — override manual do professor)
 * Contexto produzido (saída):
 *   - tipo_recurso_visual_decidido (string|null) — grafico|infografico|geometria|diagrama|ilustracao|mapa|null
 */
class PlanejadorQuestaoAgent implements AgenteIAInterface
{
    /** Palavras-chave (case-insensitive) → tipo de recurso visual mais comum na disciplina. */
    private const REGRAS = [
        'grafico' => ['interpretação de dados', 'interpretacao de dados', 'estatística', 'estatistica', 'porcentagem', 'proporção', 'proporcao'],
        'geometria' => ['geometria', 'área', 'area', 'perímetro', 'perimetro', 'ângulo', 'angulo', 'triângulo', 'triangulo'],
        'mapa' => ['geografia', 'relevo', 'clima', 'continente', 'território', 'territorio'],
        'diagrama' => ['biologia', 'química', 'quimica', 'física', 'fisica', 'anatomia', 'célula', 'celula', 'molécula', 'molecula'],
        'ilustracao' => ['história', 'historia', 'linha do tempo', 'período histórico', 'periodo historico'],
    ];

    public function nome(): string
    {
        return 'PlanejadorQuestaoAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        $disciplina = trim((string) $contexto->get('disciplina', ''));
        if ($disciplina === '') {
            throw new Exception('PlanejadorQuestaoAgent: disciplina vazia');
        }
        $assunto = trim((string) $contexto->get('assunto', ''));
        $comRecursoVisual = $contexto->get('com_recurso_visual', false);

        if ($comRecursoVisual === false || $comRecursoVisual === null) {
            return $contexto->set('tipo_recurso_visual_decidido', null);
        }

        $override = trim((string) $contexto->get('tipo_recurso_visual', ''));
        if ($override !== '') {
            return $contexto->set('tipo_recurso_visual_decidido', $override);
        }

        if ($comRecursoVisual === 'auto' || $comRecursoVisual === true) {
            $textoBusca = mb_strtolower($disciplina . ' ' . $assunto, 'UTF-8');
            foreach (self::REGRAS as $tipo => $palavrasChave) {
                foreach ($palavrasChave as $palavra) {
                    if (mb_strpos($textoBusca, $palavra) !== false) {
                        return $contexto->set('tipo_recurso_visual_decidido', $tipo);
                    }
                }
            }
        }

        return $contexto->set('tipo_recurso_visual_decidido', null);
    }
}
