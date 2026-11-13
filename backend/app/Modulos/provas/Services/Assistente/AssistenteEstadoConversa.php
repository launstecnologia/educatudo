<?php

namespace App\Modulos\Provas\Services\Assistente;

/**
 * Estado estruturado da conversa do Assistente (motor v2), gravado em
 * assistente_conversas.estado_json. Funções puras: recebem e devolvem o array.
 */
final class AssistenteEstadoConversa
{
    public const FLUXO_OCORRENCIA = 'criar_ocorrencia';

    /** Ordem em que a coleta pergunta os campos da ocorrência. */
    public const CAMPOS_OCORRENCIA = ['categoria', 'detalhe', 'nivel_gravidade', 'mais_envolvidos', 'envolvidos', 'enviar_pais'];

    /**
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    public static function normalizar(array $estado): array
    {
        $aluno = is_array($estado['aluno_ativo'] ?? null) && (int) ($estado['aluno_ativo']['id'] ?? 0) > 0
            ? self::resumoAluno($estado['aluno_ativo'])
            : null;
        $fluxo = ($estado['fluxo'] ?? null) === self::FLUXO_OCORRENCIA ? self::FLUXO_OCORRENCIA : null;
        $pendente = null;
        if (is_array($estado['pendente'] ?? null) && is_string($estado['pendente']['tool'] ?? null)) {
            $toolPendente = (string) $estado['pendente']['tool'];
            if (in_array($toolPendente, AssistenteFerramentas::nomes(), true)) {
                $pendente = [
                    'tool' => $toolPendente,
                    'args' => is_array($estado['pendente']['args'] ?? null) ? $estado['pendente']['args'] : [],
                ];
            }
        }

        return [
            'versao' => 1,
            'aluno_ativo' => $aluno,
            'turma_ativa' => self::textoOuNull($estado['turma_ativa'] ?? null),
            'ano_letivo' => (int) ($estado['ano_letivo'] ?? 0) > 0 ? (int) $estado['ano_letivo'] : null,
            'fluxo' => $fluxo,
            'rascunho' => $fluxo !== null && is_array($estado['rascunho'] ?? null) ? $estado['rascunho'] : [],
            'pendente' => $pendente,
            'referencias' => is_array($estado['referencias'] ?? null) ? $estado['referencias'] : [],
            'ultima_tool' => self::textoOuNull($estado['ultima_tool'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $estado
     * @param array<string,mixed> $aluno
     * @return array<string,mixed>
     */
    public static function definirAlunoAtivo(array $estado, array $aluno): array
    {
        if ((int) ($aluno['id'] ?? 0) <= 0) {
            return $estado;
        }
        $novo = self::resumoAluno($aluno);
        if ((int) ($estado['aluno_ativo']['id'] ?? 0) !== $novo['id']) {
            $estado['referencias'] = [];
        }
        $estado['aluno_ativo'] = $novo;
        return $estado;
    }

    /**
     * Junta no rascunho só o que veio preenchido; o que já estava fica.
     *
     * @param array<string,mixed> $estado
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function atualizarRascunhoOcorrencia(array $estado, array $args): array
    {
        $r = $estado['fluxo'] === self::FLUXO_OCORRENCIA && is_array($estado['rascunho'] ?? null)
            ? $estado['rascunho']
            : [];

        foreach (['categoria', 'titulo', 'detalhe'] as $campo) {
            $v = self::textoOuNull($args[$campo] ?? null);
            if ($v !== null && !self::ehPlaceholder($v)) {
                $r[$campo] = mb_substr($v, 0, $campo === 'detalhe' ? 2000 : 120);
            }
        }
        $nivel = self::normalizarGravidade((string) ($args['nivel_gravidade'] ?? ''));
        if ($nivel !== null) {
            $r['nivel_gravidade'] = $nivel;
        }
        $resolvidos = (array) ($r['envolvidos_resolvidos'] ?? []);
        $envolvidos = [];
        foreach ((array) ($args['envolvidos'] ?? []) as $nome) {
            $nome = trim((string) $nome);
            if ($nome !== '' && !isset($resolvidos[AssistenteResolvedorAluno::normalizar($nome)])) {
                $envolvidos[] = mb_substr($nome, 0, 120);
            }
        }
        if ($envolvidos !== []) {
            $r['envolvidos'] = array_values(array_unique(array_merge((array) ($r['envolvidos'] ?? []), $envolvidos)));
        }
        if ($envolvidos !== [] || $resolvidos !== []) {
            $r['mais_envolvidos'] = true;
        }
        if (($args['mais_envolvidos'] ?? null) === false && $envolvidos === []) {
            $r['mais_envolvidos'] = false;
            $r['envolvidos'] = [];
            $r['envolvidos_resolvidos'] = [];
        } elseif (($args['mais_envolvidos'] ?? null) === true) {
            $r['mais_envolvidos'] = true;
        }
        if (is_bool($args['enviar_pais'] ?? null)) {
            $r['enviar_pais'] = $args['enviar_pais'];
        }

        $estado['fluxo'] = self::FLUXO_OCORRENCIA;
        $estado['rascunho'] = $r;
        return $estado;
    }

    /**
     * @param array<string,mixed> $rascunho
     * @return list<string>
     */
    public static function camposFaltando(array $rascunho): array
    {
        $faltando = [];
        foreach (['categoria', 'detalhe', 'nivel_gravidade'] as $campo) {
            if (self::textoOuNull($rascunho[$campo] ?? null) === null) {
                $faltando[] = $campo;
            }
        }
        if (!is_bool($rascunho['mais_envolvidos'] ?? null)) {
            $faltando[] = 'mais_envolvidos';
        } elseif ($rascunho['mais_envolvidos'] === true && empty($rascunho['envolvidos']) && empty($rascunho['envolvidos_resolvidos'])) {
            $faltando[] = 'envolvidos';
        }
        if (!is_bool($rascunho['enviar_pais'] ?? null)) {
            $faltando[] = 'enviar_pais';
        }
        return $faltando;
    }

    public static function perguntaDoCampo(string $campo): string
    {
        return match ($campo) {
            'aluno' => 'De qual aluno é a ocorrência? Informe o nome ou o RA.',
            'categoria' => 'Qual a **categoria** da ocorrência? Pode digitar ou escolher uma sugestão.',
            'detalhe' => 'Descreva **o que aconteceu**.',
            'nivel_gravidade' => 'Qual o **nível**: leve, moderado ou grave?',
            'mais_envolvidos' => 'Há **mais alunos envolvidos**?',
            'envolvidos' => 'Quais os **nomes** dos outros alunos envolvidos?',
            'enviar_pais' => 'Deseja **notificar os responsáveis**?',
            default => 'Complete os dados da ocorrência.',
        };
    }

    /**
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    public static function limparFluxo(array $estado): array
    {
        $estado['fluxo'] = null;
        $estado['rascunho'] = [];
        return $estado;
    }

    /**
     * Guarda a chamada que parou em candidatos para retomar no clique.
     *
     * @param array<string,mixed> $estado
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function definirPendente(array $estado, string $tool, array $args): array
    {
        $estado['pendente'] = ['tool' => $tool, 'args' => $args];
        return $estado;
    }

    /**
     * Ids que o modelo pode reutilizar em follow-ups ("detalhe dessa prova").
     *
     * @param array<string,mixed> $estado
     * @param array<string,mixed> $resultado
     * @return array<string,mixed>
     */
    public static function registrarReferencias(array $estado, string $tool, array $resultado): array
    {
        $mapa = [
            'listar_provas_aluno' => ['provas', 'prova_id', 'provas'],
            'listar_jornadas_aluno' => ['jornadas', 'jornada_id', 'jornadas'],
            'listar_provas_professor' => ['provas', 'prova_id', 'provas_professor'],
            'buscar_blocos' => ['blocos', 'bloco_id', 'blocos'],
        ];
        if (!isset($mapa[$tool]) || empty($resultado['ok'])) {
            return $estado;
        }
        [$chaveLista, $chaveId, $destino] = $mapa[$tool];
        $itens = [];
        foreach (array_slice((array) ($resultado[$chaveLista] ?? []), 0, 15) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = (int) ($item[$chaveId] ?? $item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $itens[] = [
                $chaveId => $id,
                'titulo' => mb_substr(trim((string) ($item['titulo'] ?? $item['nome'] ?? '')), 0, 100),
                'data' => self::textoOuNull($item['data'] ?? $item['data_prova'] ?? $item['finalizado_em'] ?? null),
            ];
        }
        $estado['referencias'][$destino] = $itens;
        return $estado;
    }

    /** @param array<string,mixed> $estado */
    public static function paraPrompt(array $estado): string
    {
        $visivel = [
            'aluno_em_foco' => $estado['aluno_ativo'] ?? null,
            'turma_em_foco' => $estado['turma_ativa'] ?? null,
            'ano_letivo' => $estado['ano_letivo'] ?? null,
        ];
        if (($estado['fluxo'] ?? null) === self::FLUXO_OCORRENCIA) {
            $rascunho = (array) ($estado['rascunho'] ?? []);
            $falta = self::camposFaltando($rascunho);
            if (empty($rascunho['aluno'])) {
                array_unshift($falta, 'aluno');
            }
            $visivel['coleta_em_andamento'] = [
                'acao' => 'criar ocorrência (chame propor_criar_ocorrencia com a resposta do usuário)',
                'rascunho' => $rascunho,
                'falta' => $falta,
            ];
        }
        if (!empty($estado['referencias'])) {
            $visivel['referencias'] = $estado['referencias'];
        }
        return "Estado atual da conversa (fonte do servidor, use para pronomes e follow-ups):\n"
            . json_encode($visivel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function normalizarGravidade(string $valor): ?string
    {
        $v = AssistenteResolvedorAluno::normalizar($valor);
        return match ($v) {
            'leve' => 'leve',
            'moderado', 'moderada', 'medio', 'media' => 'moderado',
            'grave', 'gravissimo', 'gravissima' => 'grave',
            default => null,
        };
    }

    /**
     * @param array<string,mixed> $aluno
     * @return array{id:int,nome:string,ra:?string,turma_nome:?string,turma_id:?int}
     */
    public static function resumoAluno(array $aluno): array
    {
        return [
            'id' => (int) $aluno['id'],
            'nome' => trim((string) ($aluno['nome'] ?? '')),
            'ra' => self::textoOuNull($aluno['ra'] ?? null),
            'turma_nome' => self::textoOuNull($aluno['turma_nome'] ?? null),
            'turma_id' => (int) ($aluno['turma_id'] ?? 0) > 0 ? (int) $aluno['turma_id'] : null,
        ];
    }

    private static function textoOuNull($v): ?string
    {
        if (!is_scalar($v)) {
            return null;
        }
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }

    private static function ehPlaceholder(string $v): bool
    {
        return preg_match('/^(?:\.{2,}|…|null|n\/a|-+|\?+)$/iu', trim($v)) === 1;
    }
}
