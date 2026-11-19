<?php

namespace App\Modulos\EducaSync\Services;

use App\Core\Database;
use App\Modulos\EducaSync\Models\Cenario;
use App\Modulos\EducaSync\Models\ComandoIa;
use App\Modulos\EducaSync\Models\Disponibilidade;
use App\Modulos\EducaSync\Models\Restricao;
use App\Modulos\EducaSync\Models\Slot;
use App\Modulos\EducaSync\Models\Versao;

/**
 * EducaSync — propostas tipadas do assistente: normalizar, validar, gravar rascunho e aplicar.
 *
 * Nada aqui roda sem confirmação explícita do usuário (`confirmar`) e nada publica a grade.
 *
 * O que cada tipo faz ao ser confirmado:
 *
 * - bloquear_professor_dia  (aplicável)
 *     Marca livre = 0 em educasync_disponibilidades para TODOS os slots do dia informado,
 *     para o professor. Soma aos bloqueios existentes (não apaga os outros dias).
 *     Travado se o cenário já tem versões (mesma regra dos horários/demandas).
 *
 * - bloquear_professor_slots (aplicável)
 *     Igual ao dia, mas só nos slots informados (dia+ordem ou slot_id). Para bloqueios
 *     parciais (“Helena na 2ª e 3ª de terça”). Travado com versões, como o dia inteiro.
 *
 * - minimizar_janelas       (aplicável)
 *     Atualiza pesos_json.janelas do cenário. O EducaSyncContextoService envia esse peso
 *     como soft `minimizar_janelas`. Peso 0 desliga a preferência. Vale para as PRÓXIMAS gerações;
 *     não mexe em versões já geradas.
 *
 * - evitar_disciplina_slot  (aplicável)
 *     Grava em educasync_restricoes uma preferência soft (hard = 0, ativa = 1, origem = 'assistente')
 *     com parametros_json {materia_id, slot_id, dia, ordem} e o peso informado. Não altera
 *     disponibilidades. O EducaSyncContextoService envia a restrição "achatada" ao solver
 *     (tipo, peso, materia_id, slot_id, dia, ordem) nas PRÓXIMAS gerações; versões existentes
 *     não mudam. Não é bloqueio: é penalidade no objetivo. Repetir o mesmo item só atualiza o peso.
 *
 * - concentrar_disciplina   (aplicável)
 *     Grava em educasync_restricoes uma preferência soft (hard = 0, ativa = 1, origem = 'assistente')
 *     com parametros_json {materia_id[, turma_id]} e o peso informado: a matéria deve ocupar
 *     poucos dias da semana (na turma informada ou em todas). Mesmo envio achatado ao solver,
 *     também só nas próximas gerações. Repetir o mesmo item só atualiza o peso.
 *
 * ATENÇÃO: gravar e enviar não basta — o efeito na grade depende de o solver (services/educasync-solver)
 * implementar esses tipos de soft; enquanto não implementar, ele os ignora com aviso.
 */
class EducaSyncPropostaService
{
    public const TIPOS = [
        'bloquear_professor_dia' => ['label' => 'Bloquear professor em um dia', 'aplicavel' => true, 'trava_com_versoes' => true],
        'bloquear_professor_slots' => ['label' => 'Bloquear professor em horários', 'aplicavel' => true, 'trava_com_versoes' => true],
        'evitar_disciplina_slot' => ['label' => 'Evitar disciplina em um horário', 'aplicavel' => true, 'trava_com_versoes' => false],
        'minimizar_janelas' => ['label' => 'Minimizar janelas dos professores', 'aplicavel' => true, 'trava_com_versoes' => false],
        'concentrar_disciplina' => ['label' => 'Concentrar disciplina em poucos dias', 'aplicavel' => true, 'trava_com_versoes' => false],
    ];

    private const MAX_ALTERACOES = 40;
    private const PESO_MAXIMO = 1000;

    private Cenario $cenarios;
    private Slot $slots;
    private Versao $versoes;
    private Disponibilidade $disponibilidades;
    private ComandoIa $comandos;
    private EducaSyncContextoService $contexto;
    private Restricao $restricoes;

    public function __construct(
        ?Cenario $cenarios = null,
        ?Slot $slots = null,
        ?Versao $versoes = null,
        ?Disponibilidade $disponibilidades = null,
        ?ComandoIa $comandos = null,
        ?EducaSyncContextoService $contexto = null,
        ?Restricao $restricoes = null
    ) {
        $this->restricoes = $restricoes ?? new Restricao();
        $this->cenarios = $cenarios ?? new Cenario();
        $this->slots = $slots ?? new Slot();
        $this->versoes = $versoes ?? new Versao();
        $this->disponibilidades = $disponibilidades ?? new Disponibilidade();
        $this->comandos = $comandos ?? new ComandoIa();
        $this->contexto = $contexto ?? new EducaSyncContextoService();
    }

    /**
     * Valida a estrutura: tipos conhecidos, entidades existentes, faixas de valor.
     *
     * @param mixed $alteracoes lista de itens vindos do LLM/UI
     * @return array{valida:bool, erros:list<string>, avisos:list<string>, alteracoes:list<array<string,mixed>>}
     */
    public function validar(int $cenarioId, $alteracoes): array
    {
        $erros = [];
        $avisos = [];
        $normalizadas = [];

        if (!is_array($alteracoes) || $alteracoes === []) {
            return ['valida' => false, 'erros' => ['Nenhuma alteração informada.'], 'avisos' => [], 'alteracoes' => []];
        }
        if (count($alteracoes) > self::MAX_ALTERACOES) {
            return ['valida' => false, 'erros' => ['No máximo ' . self::MAX_ALTERACOES . ' alterações por proposta.'], 'avisos' => [], 'alteracoes' => []];
        }

        $cenario = $this->cenarios->findById($cenarioId);
        if (!$cenario) {
            return ['valida' => false, 'erros' => ['Cenário não encontrado.'], 'avisos' => [], 'alteracoes' => []];
        }
        if ((string) ($cenario['status'] ?? '') === 'arquivado') {
            return ['valida' => false, 'erros' => ['Cenário arquivado não aceita propostas.'], 'avisos' => [], 'alteracoes' => []];
        }

        $temVersoes = $this->versoes->listarPorCenario($cenarioId) !== [];

        foreach (array_values($alteracoes) as $i => $bruto) {
            $rotulo = 'Alteração ' . ($i + 1);
            if (!is_array($bruto)) {
                $erros[] = "{$rotulo}: formato inválido.";
                continue;
            }
            $tipo = (string) ($bruto['tipo'] ?? '');
            if (!isset(self::TIPOS[$tipo])) {
                $erros[] = "{$rotulo}: tipo '{$tipo}' desconhecido. Use: " . implode(', ', array_keys(self::TIPOS)) . '.';
                continue;
            }

            [$item, $errosItem] = $this->normalizarItem($cenario, $tipo, $bruto);
            foreach ($errosItem as $e) {
                $erros[] = "{$rotulo} ({$tipo}): {$e}";
            }
            if ($item === null) {
                continue;
            }
            if (!self::TIPOS[$tipo]['aplicavel']) {
                $avisos[] = "{$rotulo}: '{$tipo}' ainda não é aplicado pelo solver; ao confirmar, ficará como não aplicado.";
            }
            if (($tipo === 'bloquear_professor_dia' || $tipo === 'bloquear_professor_slots') && $temVersoes) {
                $avisos[] = "{$rotulo}: o cenário já tem versões — bloquear dia do professor não será aplicado na confirmação (crie um novo cenário).";
            }
            $normalizadas[] = $item;
        }

        return ['valida' => $erros === [], 'erros' => $erros, 'avisos' => $avisos, 'alteracoes' => $erros === [] ? $normalizadas : []];
    }

    /**
     * Valida e grava o rascunho no comando da mensagem atual (confirmado = 0). Não aplica nada.
     *
     * @param mixed $alteracoes
     * @return array<string,mixed>
     */
    public function propor(int $cenarioId, int $comandoId, $alteracoes, string $resumo = ''): array
    {
        $validacao = $this->validar($cenarioId, $alteracoes);
        if (!$validacao['valida']) {
            return ['ok' => false, 'error' => 'Proposta inválida.', 'erros' => $validacao['erros']];
        }
        if ($comandoId <= 0) {
            return ['ok' => false, 'error' => 'Não há comando de auditoria para gravar o rascunho.'];
        }

        $proposta = [
            'resumo' => mb_substr(trim($resumo), 0, 500),
            'alteracoes' => $validacao['alteracoes'],
        ];
        if (!$this->comandos->gravarProposta($comandoId, $cenarioId, $proposta)) {
            return ['ok' => false, 'error' => 'Não foi possível gravar o rascunho da proposta.'];
        }

        return [
            'ok' => true,
            'comando_ia_id' => $comandoId,
            'proposta' => $proposta,
            'avisos' => $validacao['avisos'],
            'situacao' => 'RASCUNHO — nada foi aplicado. O usuário precisa confirmar na tela.',
        ];
    }

    /**
     * Aplica a proposta do comando. Só com confirmação do próprio autor, uma única vez.
     *
     * @return array{success:bool, error?:string, mensagem?:string, aplicadas?:list<array<string,string>>, nao_aplicadas?:list<array<string,string>>}
     */
    public function confirmar(int $cenarioId, int $comandoId, int $usuarioId): array
    {
        $cenario = $this->cenarios->findById($cenarioId);
        $comando = $this->comandos->findDoCenario($comandoId, $cenarioId);
        if (!$cenario || !$comando) {
            return ['success' => false, 'error' => 'Proposta não encontrada neste cenário.'];
        }
        // usuario_id NULL/0: legado do bug de session_write_close; admin com permissão pode confirmar.
        $donoId = (int) ($comando['usuario_id'] ?? 0);
        if ($donoId > 0 && $donoId !== $usuarioId) {
            return ['success' => false, 'error' => 'Esta proposta foi gerada por outro usuário.'];
        }
        if ((int) $comando['confirmado'] === 1) {
            return ['success' => false, 'error' => 'Esta proposta já foi confirmada.'];
        }
        $proposta = json_decode((string) ($comando['proposta_json'] ?? ''), true);
        if (!is_array($proposta) || empty($proposta['alteracoes'])) {
            return ['success' => false, 'error' => 'Esta mensagem não tem proposta para confirmar.'];
        }

        // Revalida: o cenário pode ter mudado desde o rascunho.
        $validacao = $this->validar($cenarioId, $proposta['alteracoes']);
        if (!$validacao['valida']) {
            return ['success' => false, 'error' => 'A proposta não vale mais: ' . implode(' ', $validacao['erros'])];
        }

        $temVersoes = $this->versoes->listarPorCenario($cenarioId) !== [];
        $aplicar = [];
        $naoAplicadas = [];
        foreach ($validacao['alteracoes'] as $item) {
            $meta = self::TIPOS[$item['tipo']];
            if (!$meta['aplicavel']) {
                $naoAplicadas[] = $this->resumoItem($item, 'O solver ainda não suporta este tipo de preferência.');
            } elseif ($meta['trava_com_versoes'] && $temVersoes) {
                $naoAplicadas[] = $this->resumoItem($item, 'O cenário já tem versões geradas; crie um novo cenário para alterar disponibilidade.');
            } else {
                $aplicar[] = $item;
            }
        }
        if ($aplicar === []) {
            $motivos = array_map(static fn(array $n): string => $n['descricao'] . ' — ' . $n['motivo'], $naoAplicadas);
            return ['success' => false, 'error' => 'Nada a aplicar. ' . implode(' ', $motivos)];
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            if (!$this->comandos->marcarConfirmado($comandoId, $cenarioId)) {
                $db->rollback();
                return ['success' => false, 'error' => 'Esta proposta já foi confirmada.'];
            }
            $aplicadas = [];
            foreach ($aplicar as $item) {
                $this->aplicarItem($cenario, $item);
                $aplicadas[] = $this->resumoItem($item);
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            error_log('EducaSyncPropostaService::confirmar: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Não foi possível aplicar a proposta. Nada foi alterado.'];
        }

        return [
            'success' => true,
            'mensagem' => count($aplicadas) . ' alteração(ões) aplicada(s). A grade NÃO foi gerada nem publicada; use "Gerar grade" para uma nova versão.',
            'aplicadas' => $aplicadas,
            'nao_aplicadas' => $naoAplicadas,
        ];
    }

    /**
     * @param array<string,mixed> $cenario
     * @param array<string,mixed> $item item já normalizado
     */
    private function aplicarItem(array $cenario, array $item): void
    {
        $cenarioId = (int) $cenario['id'];
        switch ($item['tipo']) {
            case 'bloquear_professor_dia':
                $slotIds = [];
                foreach ($this->slots->listarPorCenario($cenarioId) as $s) {
                    if ((int) $s['dia_semana'] === (int) $item['dia_semana']) {
                        $slotIds[] = (int) $s['id'];
                    }
                }
                $this->disponibilidades->adicionarBloqueios($cenarioId, 'professor', (int) $item['professor_id'], $slotIds);
                return;

            case 'bloquear_professor_slots':
                $this->disponibilidades->adicionarBloqueios(
                    $cenarioId,
                    'professor',
                    (int) $item['professor_id'],
                    array_map('intval', (array) ($item['slot_ids'] ?? []))
                );
                return;

            case 'minimizar_janelas':
                $pesos = $this->contexto->pesosDoCenario($cenario);
                $pesos['janelas'] = (int) $item['peso'];
                $this->cenarios->atualizarPesos($cenarioId, $pesos);
                return;

            case 'evitar_disciplina_slot':
                // `dia` = mesmo nome que o solver usa em slots; `slot_id` pode envelhecer se os
                // horários forem regerados, por isso dia+ordem acompanham (o contexto reresolve).
                $this->restricoes->gravarSoftDoAssistente(
                    $cenarioId,
                    'evitar_disciplina_slot',
                    'materia',
                    [
                        'materia_id' => (int) $item['materia_id'],
                        'slot_id' => (int) $item['slot_id'],
                        'dia' => (int) $item['dia_semana'],
                        'ordem' => (int) $item['ordem'],
                    ],
                    (int) $item['peso']
                );
                return;

            case 'concentrar_disciplina':
                $parametros = ['materia_id' => (int) $item['materia_id']];
                if (!empty($item['turma_id'])) {
                    $parametros['turma_id'] = (int) $item['turma_id'];
                }
                $this->restricoes->gravarSoftDoAssistente(
                    $cenarioId,
                    'concentrar_disciplina',
                    isset($parametros['turma_id']) ? 'turma' : 'materia',
                    $parametros,
                    (int) $item['peso']
                );
                return;

            default:
                throw new \LogicException('Tipo sem aplicação: ' . (string) $item['tipo']);
        }
    }

    /**
     * @param array<string,mixed> $cenario
     * @param array<string,mixed> $bruto
     * @return array{0:?array<string,mixed>, 1:list<string>} [item normalizado|null, erros]
     */
    private function normalizarItem(array $cenario, string $tipo, array $bruto): array
    {
        $erros = [];
        $cenarioId = (int) $cenario['id'];
        $item = ['tipo' => $tipo];

        switch ($tipo) {
            case 'bloquear_professor_dia':
                [$professorId, $professorNome, $erro] = $this->resolverEntidade('professores', $bruto, 'professor_id', 'professor_nome', null);
                if ($erro !== null) {
                    $erros[] = $erro;
                }
                $dia = $this->resolverDia($bruto['dia_semana'] ?? ($bruto['dia'] ?? null));
                if ($dia === null) {
                    $erros[] = 'dia_semana inválido (use 1=Segunda … 7=Domingo ou o nome do dia).';
                } elseif (!$this->diaTemSlots($cenarioId, $dia)) {
                    $erros[] = 'O cenário não tem horários (slots) em ' . Slot::DIAS[$dia] . '.';
                }
                if ($erros === []) {
                    $item += [
                        'professor_id' => $professorId,
                        'professor_nome' => $professorNome,
                        'dia_semana' => $dia,
                        'dia_label' => Slot::DIAS[$dia],
                    ];
                }
                break;

            case 'bloquear_professor_slots':
                [$professorId, $professorNome, $erro] = $this->resolverEntidade('professores', $bruto, 'professor_id', 'professor_nome', null);
                if ($erro !== null) {
                    $erros[] = $erro;
                }
                [$slotIds, $rotulos, $erroSlots] = $this->resolverListaSlots($cenarioId, $bruto);
                if ($erroSlots !== null) {
                    $erros[] = $erroSlots;
                }
                if ($erros === []) {
                    $item += [
                        'professor_id' => $professorId,
                        'professor_nome' => $professorNome,
                        'slot_ids' => $slotIds,
                        'slots_label' => implode(', ', $rotulos),
                    ];
                }
                break;

            case 'evitar_disciplina_slot':
                [$materiaId, $materiaNome, $erro] = $this->resolverEntidade('materias', $bruto, 'materia_id', 'materia_nome', null);
                if ($erro !== null) {
                    $erros[] = $erro;
                }
                $slot = $this->resolverSlot($cenarioId, $bruto);
                if ($slot === null) {
                    $erros[] = 'Informe slot_id existente no cenário, ou dia_semana + ordem.';
                }
                $peso = $this->resolverPeso($bruto['peso'] ?? 50, $erros);
                if ($erros === []) {
                    $item += [
                        'materia_id' => $materiaId,
                        'materia_nome' => $materiaNome,
                        'slot_id' => (int) $slot['id'],
                        'dia_semana' => (int) $slot['dia_semana'],
                        'ordem' => (int) $slot['ordem'],
                        'slot_label' => Slot::rotulo($slot),
                        'peso' => $peso,
                    ];
                }
                break;

            case 'minimizar_janelas':
                $peso = $this->resolverPeso($bruto['peso'] ?? null, $erros);
                if ($erros === []) {
                    $item['peso'] = $peso;
                }
                break;

            case 'concentrar_disciplina':
                [$materiaId, $materiaNome, $erro] = $this->resolverEntidade('materias', $bruto, 'materia_id', 'materia_nome', null);
                if ($erro !== null) {
                    $erros[] = $erro;
                }
                $turmaId = null;
                $turmaNome = null;
                if (!empty($bruto['turma_id']) || !empty($bruto['turma_nome'])) {
                    [$turmaId, $turmaNome, $erroTurma] = $this->resolverEntidade('turmas', $bruto, 'turma_id', 'turma_nome', (int) $cenario['ano_letivo']);
                    if ($erroTurma !== null) {
                        $erros[] = $erroTurma;
                    }
                }
                $peso = $this->resolverPeso($bruto['peso'] ?? 50, $erros);
                if ($erros === []) {
                    $item += [
                        'materia_id' => $materiaId,
                        'materia_nome' => $materiaNome,
                        'turma_id' => $turmaId,
                        'turma_nome' => $turmaNome,
                        'peso' => $peso,
                    ];
                }
                break;
        }

        return [$erros === [] ? $item : null, $erros];
    }

    /**
     * Resolve por id (precisa existir e estar ativo) ou por nome (precisa ser único).
     *
     * @param 'professores'|'materias'|'turmas' $tabela
     * @param array<string,mixed> $bruto
     * @return array{0:?int, 1:?string, 2:?string} [id, nome, erro]
     */
    public function resolverEntidade(string $tabela, array $bruto, string $chaveId, string $chaveNome, ?int $anoLetivo): array
    {
        $singular = ['professores' => 'professor', 'materias' => 'matéria', 'turmas' => 'turma'][$tabela] ?? $tabela;

        $id = (int) ($bruto[$chaveId] ?? 0);
        if ($id > 0) {
            $nome = $this->cenarios->nomeDoAtivo($tabela, $id);
            return $nome === null
                ? [null, null, "{$singular} {$id} não existe ou está inativo(a)."]
                : [$id, $nome, null];
        }

        $termo = trim((string) ($bruto[$chaveNome] ?? ''));
        if ($termo === '') {
            return [null, null, "Informe {$chaveId} ou {$chaveNome}."];
        }
        $achados = $this->cenarios->buscarPorNome($tabela, $termo, $anoLetivo, 6);
        if ($achados === []) {
            return [null, null, "Nenhum(a) {$singular} encontrado(a) para \"{$termo}\"."];
        }
        if (count($achados) > 1) {
            $opcoes = array_map(static fn(array $a): string => $a['nome'] . ' (id ' . $a['id'] . ')', $achados);
            return [null, null, "Mais de um(a) {$singular} para \"{$termo}\": " . implode('; ', $opcoes) . '. Informe o id.'];
        }
        return [$achados[0]['id'], $achados[0]['nome'], null];
    }

    /** @param mixed $valor */
    public function resolverDia($valor): ?int
    {
        if (is_int($valor) || (is_string($valor) && ctype_digit(trim($valor)))) {
            $dia = (int) $valor;
            return isset(Slot::DIAS[$dia]) ? $dia : null;
        }
        if (!is_string($valor)) {
            return null;
        }
        $texto = mb_strtolower(trim($valor));
        $texto = strtr($texto, ['ç' => 'c', 'á' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u']);
        $texto = preg_replace('/[-\s]?feira$/', '', $texto) ?? $texto;
        $mapa = ['segunda' => 1, 'terca' => 2, 'quarta' => 3, 'quinta' => 4, 'sexta' => 5, 'sabado' => 6, 'domingo' => 7];
        return $mapa[$texto] ?? null;
    }

    private function diaTemSlots(int $cenarioId, int $dia): bool
    {
        foreach ($this->slots->listarPorCenario($cenarioId) as $s) {
            if ((int) $s['dia_semana'] === $dia) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $bruto
     * @return array<string,mixed>|null linha do slot
     */
    private function resolverSlot(int $cenarioId, array $bruto): ?array
    {
        $slots = $this->slots->listarPorCenario($cenarioId);
        $slotId = (int) ($bruto['slot_id'] ?? 0);
        $dia = $this->resolverDia($bruto['dia_semana'] ?? ($bruto['dia'] ?? null));
        $ordem = (int) ($bruto['ordem'] ?? 0);

        foreach ($slots as $s) {
            if ($slotId > 0 && (int) $s['id'] === $slotId) {
                return $s;
            }
            if ($slotId <= 0 && $dia !== null && $ordem > 0 && (int) $s['dia_semana'] === $dia && (int) $s['ordem'] === $ordem) {
                return $s;
            }
        }
        return null;
    }

    /**
     * Aceita slots: [{dia_semana|dia, ordem}|{slot_id}, ...] ou slot_ids: [1,2]
     * ou dia_semana + ordens: [1,2,3].
     *
     * @param array<string,mixed> $bruto
     * @return array{0:list<int>, 1:list<string>, 2:?string}
     */
    private function resolverListaSlots(int $cenarioId, array $bruto): array
    {
        $candidatos = [];
        if (isset($bruto['slots']) && is_array($bruto['slots'])) {
            $candidatos = $bruto['slots'];
        } elseif (isset($bruto['slot_ids']) && is_array($bruto['slot_ids'])) {
            foreach ($bruto['slot_ids'] as $sid) {
                $candidatos[] = ['slot_id' => (int) $sid];
            }
        } elseif (isset($bruto['ordens']) && is_array($bruto['ordens'])) {
            $diaBase = $bruto['dia_semana'] ?? ($bruto['dia'] ?? null);
            foreach ($bruto['ordens'] as $ordem) {
                $candidatos[] = ['dia_semana' => $diaBase, 'ordem' => (int) $ordem];
            }
        }

        if ($candidatos === []) {
            return [[], [], 'Informe slots (lista de dia+ordem ou slot_id), slot_ids ou dia_semana+ordens.'];
        }
        if (count($candidatos) > 40) {
            return [[], [], 'No máximo 40 horários por bloqueio parcial.'];
        }

        $ids = [];
        $rotulos = [];
        foreach ($candidatos as $i => $cand) {
            if (!is_array($cand)) {
                return [[], [], 'Item de slot #' . ($i + 1) . ' inválido.'];
            }
            $slot = $this->resolverSlot($cenarioId, $cand);
            if ($slot === null) {
                return [[], [], 'Horário #' . ($i + 1) . ' não encontrado no cenário (use dia_semana + ordem ou slot_id).'];
            }
            $sid = (int) $slot['id'];
            if (isset($ids[$sid])) {
                continue;
            }
            $ids[$sid] = true;
            $rotulos[] = Slot::rotulo($slot);
        }

        return [array_map('intval', array_keys($ids)), $rotulos, null];
    }

    /**
     * @param mixed $valor
     * @param list<string> $erros
     */
    private function resolverPeso($valor, array &$erros): int
    {
        if (!is_numeric($valor) || (int) $valor < 0 || (int) $valor > self::PESO_MAXIMO) {
            $erros[] = 'peso deve ser um número entre 0 e ' . self::PESO_MAXIMO . '.';
            return 0;
        }
        return (int) $valor;
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,string>
     */
    private function resumoItem(array $item, ?string $motivo = null): array
    {
        $resumo = ['tipo' => (string) $item['tipo'], 'descricao' => $this->descrever($item)];
        if ($motivo !== null) {
            $resumo['motivo'] = $motivo;
        }
        return $resumo;
    }

    /**
     * @param array<string,mixed> $item
     */
    public function descrever(array $item): string
    {
        switch ($item['tipo'] ?? '') {
            case 'bloquear_professor_dia':
                return ($item['professor_nome'] ?? '?') . ' — ' . ($item['dia_label'] ?? '?') . ' (dia inteiro)';
            case 'bloquear_professor_slots':
                return ($item['professor_nome'] ?? '?') . ' — ' . ($item['slots_label'] ?? '?');
            case 'evitar_disciplina_slot':
                return 'Evitar ' . ($item['materia_nome'] ?? '?') . ' em ' . ($item['slot_label'] ?? '?') . ' (peso ' . (int) ($item['peso'] ?? 0) . ')';
            case 'minimizar_janelas':
                return 'Peso de janelas dos professores = ' . (int) ($item['peso'] ?? 0);
            case 'concentrar_disciplina':
                $escopo = !empty($item['turma_nome']) ? ' na turma ' . $item['turma_nome'] : ' em todas as turmas';
                return 'Concentrar ' . ($item['materia_nome'] ?? '?') . $escopo . ' (peso ' . (int) ($item['peso'] ?? 0) . ')';
            default:
                return (string) ($item['tipo'] ?? '?');
        }
    }
}
