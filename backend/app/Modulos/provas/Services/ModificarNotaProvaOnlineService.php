<?php

namespace App\Modulos\Provas\Services;

use App\Core\Database;
use App\Modulos\Provas\Models\Exam;

/**
 * Coordenação altera acerto/erro de prova online e recalcula a nota oficial.
 * Cada gravação exige motivo e fica no histórico.
 */
class ModificarNotaProvaOnlineService
{
    private Database $db;
    private Exam $provas;
    private ?bool $temColunaInvalidada = null;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->provas = new Exam();
    }

    /**
     * @return array{prova: array, questoes: list<array>, alunos: list<array>, historico: list<array>}
     */
    public function montar(int $blocoId, int $provaId): array
    {
        $this->garantirTabela();
        $prova = $this->buscarProvaNoBloco($blocoId, $provaId);
        if ($prova === null) {
            throw new \InvalidArgumentException('Prova não encontrada neste bloco.');
        }

        $questoes = $this->buscarQuestoes($provaId);
        $letras = $this->mapaLetras($questoes);
        foreach ($questoes as &$questao) {
            $questao['gabarito'] = $letras['gabarito'][(int) $questao['id']] ?? null;
        }
        unset($questao);

        $realizacoes = $this->mapaRealizacoes($provaId);
        $respostas = $this->mapaRespostas($provaId);
        $alunos = $this->listarAlunos($blocoId, $provaId, $realizacoes);

        $linhas = [];
        foreach ($alunos as $aluno) {
            $alunoId = (int) $aluno['id'];
            $realizacao = $realizacoes[$alunoId] ?? null;
            $status = (string) ($realizacao['status'] ?? 'nao_realizou');
            if (!isset($this->rotulosStatus()[$status])) {
                $status = 'nao_realizou';
            }
            $mapa = [];
            foreach ($questoes as $questao) {
                $qid = (int) $questao['id'];
                $resp = $respostas[$alunoId][$qid] ?? null;
                $correta = null;
                $pontuacao = 0.0;
                $letra = null;
                if (is_array($resp)) {
                    if ($resp['correta'] !== null) {
                        $correta = (int) $resp['correta'] === 1 ? 1 : 0;
                    }
                    $pontuacao = (float) $resp['pontuacao'];
                    $altId = (int) ($resp['alternativa_id'] ?? 0);
                    $letra = $altId > 0 ? ($letras['por_alternativa'][$altId] ?? null) : null;
                }
                $mapa[$qid] = [
                    'correta' => $correta,
                    'pontuacao' => $pontuacao,
                    'letra' => $letra,
                ];
            }
            $nota = null;
            if ($realizacao && $realizacao['nota'] !== null && $realizacao['nota'] !== '') {
                $nota = (float) $realizacao['nota'];
            }
            $linhas[] = [
                'id' => $alunoId,
                'nome' => (string) $aluno['nome'],
                'ra' => trim((string) ($aluno['ra'] ?? '')),
                'turma_nome' => (string) ($aluno['turma_nome'] ?? ''),
                'status' => $status,
                'status_label' => $this->rotulosStatus()[$status],
                'editavel' => $status === 'finalizado',
                'nota' => $nota,
                'respostas' => $mapa,
            ];
        }

        return [
            'prova' => $prova,
            'questoes' => $questoes,
            'alunos' => $linhas,
            'historico' => $this->historico($provaId, $questoes),
        ];
    }

    /**
     * @param list<array> $alteracoes
     * @param array<string,mixed> $usuario
     * @return array{mensagem: string, alunos: int, questoes: int}
     */
    public function aplicar(
        int $blocoId,
        int $provaId,
        array $alteracoes,
        array $usuario,
        string $observacao,
        string $escopo,
        string $ip
    ): array {
        $this->garantirTabela();
        $prova = $this->buscarProvaNoBloco($blocoId, $provaId);
        if ($prova === null) {
            throw new \InvalidArgumentException('Prova não encontrada neste bloco.');
        }

        $questoes = [];
        foreach ($this->buscarQuestoes($provaId) as $questao) {
            $questoes[(int) $questao['id']] = $questao;
        }
        $pedidos = $this->normalizarAlteracoes($alteracoes, $questoes);
        $observacao = mb_substr(trim($observacao), 0, 2000);
        if (mb_strlen($observacao) < 10) {
            throw new \InvalidArgumentException('Descreva o motivo da alteração (mínimo de 10 caracteres).');
        }
        $escopos = ['todos' => true, 'aluno' => true, 'questao' => true, 'manual' => true, 'zerar' => true, 'zerar_aluno' => true];
        if (!isset($escopos[$escopo])) {
            $escopo = 'manual';
        }
        $usuarioId = (int) ($usuario['id'] ?? 0);
        if ($usuarioId <= 0) {
            throw new \InvalidArgumentException('Usuário não identificado.');
        }
        $usuarioNome = trim((string) ($usuario['nome'] ?? ''));
        if ($usuarioNome === '') {
            $usuarioNome = 'Coordenação';
        }
        $usuarioNome = mb_substr($usuarioNome, 0, 255);
        $ip = substr(trim($ip), 0, 45);
        $lote = bin2hex(random_bytes(8));

        $porAluno = [];
        foreach ($pedidos as $pedido) {
            $porAluno[$pedido['aluno_id']][] = $pedido;
        }

        $this->db->beginTransaction();
        try {
            $realizacoes = $this->mapaRealizacoes($provaId, array_keys($porAluno));
            $respostas = $this->mapaRespostas($provaId, array_keys($porAluno));
            $nomes = $this->nomesAlunos(array_keys($porAluno));
            $alunosAlterados = 0;
            $questoesAlteradas = 0;

            foreach ($porAluno as $alunoId => $itens) {
                $realizacao = $realizacoes[$alunoId] ?? null;
                if (($realizacao['status'] ?? '') !== 'finalizado') {
                    $nome = $nomes[$alunoId] ?? ('aluno ' . $alunoId);
                    throw new \InvalidArgumentException('Só é possível alterar a nota de quem já concluiu a prova. ' . $nome . ' ainda não concluiu.');
                }

                $notaAntes = null;
                if ($realizacao['nota'] !== null && $realizacao['nota'] !== '') {
                    $notaAntes = round((float) $realizacao['nota'], 2);
                }
                $detalhes = [];
                foreach ($itens as $item) {
                    $qid = (int) $item['questao_id'];
                    $questao = $questoes[$qid];
                    if (!empty($questao['invalidada'])) {
                        throw new \InvalidArgumentException('A questão ' . (int) $questao['numero'] . ' está inválida e não entra na nota.');
                    }
                    $existente = $respostas[$alunoId][$qid] ?? null;
                    $antes = null;
                    if (is_array($existente) && $existente['correta'] !== null) {
                        $antes = (int) $existente['correta'] === 1 ? 1 : 0;
                    }
                    $depois = (int) $item['correta'] === 1 ? 1 : 0;
                    if ($antes === $depois) {
                        continue;
                    }
                    $pontuacao = $depois === 1 ? (float) $questao['valor'] : 0.0;
                    if (is_array($existente)) {
                        $this->db->query(
                            'UPDATE provas_respostas
                             SET correta = :correta, pontuacao = :pontuacao
                             WHERE prova_id = :prova_id AND aluno_id = :aluno_id AND questao_id = :questao_id',
                            [
                                'correta' => $depois,
                                'pontuacao' => $pontuacao,
                                'prova_id' => $provaId,
                                'aluno_id' => $alunoId,
                                'questao_id' => $qid,
                            ]
                        );
                    } else {
                        $this->db->query(
                            'INSERT INTO provas_respostas
                                (prova_id, aluno_id, questao_id, alternativa_id, resposta_texto, correta, pontuacao)
                             VALUES
                                (:prova_id, :aluno_id, :questao_id, NULL, NULL, :correta, :pontuacao)',
                            [
                                'prova_id' => $provaId,
                                'aluno_id' => $alunoId,
                                'questao_id' => $qid,
                                'correta' => $depois,
                                'pontuacao' => $pontuacao,
                            ]
                        );
                    }
                    $detalhes[] = [
                        'questao_id' => $qid,
                        'numero' => (int) $questao['numero'],
                        'antes' => $antes,
                        'depois' => $depois,
                    ];
                }

                if ($detalhes === []) {
                    continue;
                }

                $notaNova = round($this->provas->recalcularNotaAluno($provaId, $alunoId), 2);
                $this->db->query(
                    'INSERT INTO provas_notas_alteracoes_log
                        (lote, bloco_id, prova_id, aluno_id, usuario_id, usuario_nome, observacao, ciente, escopo,
                         nota_anterior, nota_nova, detalhes_json, ip)
                     VALUES
                        (:lote, :bloco_id, :prova_id, :aluno_id, :usuario_id, :usuario_nome, :observacao, 1, :escopo,
                         :nota_anterior, :nota_nova, :detalhes_json, :ip)',
                    [
                        'lote' => $lote,
                        'bloco_id' => $blocoId,
                        'prova_id' => $provaId,
                        'aluno_id' => $alunoId,
                        'usuario_id' => $usuarioId,
                        'usuario_nome' => $usuarioNome,
                        'observacao' => $observacao,
                        'escopo' => $escopo,
                        'nota_anterior' => $notaAntes,
                        'nota_nova' => $notaNova,
                        'detalhes_json' => json_encode($detalhes, JSON_UNESCAPED_UNICODE),
                        'ip' => $ip !== '' ? $ip : null,
                    ]
                );
                $alunosAlterados++;
                $questoesAlteradas += count($detalhes);
            }

            if ($alunosAlterados === 0) {
                throw new \InvalidArgumentException('Nenhuma nota precisou ser alterada. Os acertos já estavam como informado.');
            }

            $this->db->commit();
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                try {
                    $this->db->rollback();
                } catch (\Exception $rollbackError) {
                    error_log('ModificarNotaProvaOnlineService rollback: ' . $rollbackError->getMessage());
                }
            }
            throw $e;
        }

        $mensagem = $alunosAlterados === 1
            ? 'Nota atualizada de 1 aluno. A alteração ficou registrada no histórico.'
            : 'Notas atualizadas de ' . $alunosAlterados . ' alunos. A alteração ficou registrada no histórico.';

        return [
            'mensagem' => $mensagem,
            'alunos' => $alunosAlterados,
            'questoes' => $questoesAlteradas,
        ];
    }

    /**
     * @param array<int,array> $questoes
     * @param list<array> $alteracoes
     * @return list<array{aluno_id:int,questao_id:int,correta:int}>
     */
    private function normalizarAlteracoes(array $alteracoes, array $questoes): array
    {
        if (count($alteracoes) > 5000) {
            throw new \InvalidArgumentException('Muitas alterações de uma vez.');
        }
        $map = [];
        foreach ($alteracoes as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Alteração inválida.');
            }
            $alunoId = (int) ($item['aluno_id'] ?? 0);
            $questaoId = (int) ($item['questao_id'] ?? 0);
            if (!array_key_exists('correta', $item)) {
                throw new \InvalidArgumentException('Alteração inválida.');
            }
            $correta = (int) $item['correta'];
            if ($alunoId <= 0 || $questaoId <= 0 || ($correta !== 0 && $correta !== 1)) {
                throw new \InvalidArgumentException('Alteração inválida.');
            }
            if (!isset($questoes[$questaoId])) {
                throw new \InvalidArgumentException('Questão não pertence a esta prova.');
            }
            $map[$alunoId . ':' . $questaoId] = [
                'aluno_id' => $alunoId,
                'questao_id' => $questaoId,
                'correta' => $correta,
            ];
        }
        if ($map === []) {
            throw new \InvalidArgumentException('Nenhuma alteração para salvar.');
        }

        return array_values($map);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buscarProvaNoBloco(int $blocoId, int $provaId): ?array
    {
        if ($blocoId <= 0 || $provaId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT p.id, p.titulo, p.professor_id, p.materia_id, p.valor_total,
                    pr.nome AS professor_nome, m.nome AS materia_nome
             FROM provas p
             INNER JOIN provas_blocos_vinculo v ON v.prova_id = p.id AND v.bloco_id = :bloco_id
             LEFT JOIN professores pr ON pr.id = p.professor_id
             LEFT JOIN materias m ON m.id = p.materia_id
             WHERE p.id = :prova_id AND p.deleted_at IS NULL
             LIMIT 1',
            ['bloco_id' => $blocoId, 'prova_id' => $provaId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array>
     */
    private function buscarQuestoes(int $provaId): array
    {
        $sqlCom = 'SELECT id, ordem, enunciado, tipo, valor, COALESCE(invalidada, 0) AS invalidada
                   FROM provas_questoes
                   WHERE prova_id = :prova_id
                   ORDER BY ordem ASC, id ASC';
        $sqlSem = 'SELECT id, ordem, enunciado, tipo, valor, 0 AS invalidada
                   FROM provas_questoes
                   WHERE prova_id = :prova_id
                   ORDER BY ordem ASC, id ASC';
        try {
            $rows = $this->temInvalidada()
                ? $this->db->fetchAll($sqlCom, ['prova_id' => $provaId])
                : $this->db->fetchAll($sqlSem, ['prova_id' => $provaId]);
        } catch (\Exception $e) {
            $rows = $this->db->fetchAll($sqlSem, ['prova_id' => $provaId]);
        }

        $lista = [];
        $n = 0;
        foreach ($rows ?: [] as $row) {
            $n++;
            $enunciado = $this->textoPlano((string) ($row['enunciado'] ?? ''));
            $lista[] = [
                'id' => (int) $row['id'],
                'numero' => $n,
                'enunciado' => $enunciado,
                'enunciado_curto' => $this->cortar($enunciado, 90),
                'tipo' => (string) ($row['tipo'] ?? ''),
                'valor' => (float) ($row['valor'] ?? 0),
                'invalidada' => (int) ($row['invalidada'] ?? 0) === 1 ? 1 : 0,
            ];
        }

        return $lista;
    }

    /**
     * @param list<array> $questoes
     * @return array{por_alternativa: array<int,string>, gabarito: array<int,string>}
     */
    private function mapaLetras(array $questoes): array
    {
        $ids = [];
        foreach ($questoes as $questao) {
            $ids[] = (int) $questao['id'];
        }
        $porAlternativa = [];
        $gabarito = [];
        if ($ids === []) {
            return ['por_alternativa' => [], 'gabarito' => []];
        }
        $params = [];
        $ph = [];
        foreach ($ids as $i => $id) {
            $k = 'q' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        $rows = $this->db->fetchAll(
            'SELECT id, questao_id, COALESCE(correta, 0) AS correta
             FROM provas_alternativas
             WHERE questao_id IN (' . implode(',', $ph) . ')
             ORDER BY questao_id ASC, ordem ASC, id ASC',
            $params
        ) ?: [];
        $seq = [];
        $alfabeto = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        foreach ($rows as $row) {
            $qid = (int) $row['questao_id'];
            $indice = $seq[$qid] ?? 0;
            $seq[$qid] = $indice + 1;
            $letra = $alfabeto[$indice] ?? (string) ($indice + 1);
            $porAlternativa[(int) $row['id']] = $letra;
            if ((int) $row['correta'] === 1 && !isset($gabarito[$qid])) {
                $gabarito[$qid] = $letra;
            }
        }

        return ['por_alternativa' => $porAlternativa, 'gabarito' => $gabarito];
    }

    /**
     * @param list<int> $alunoIds
     * @return array<int,array>
     */
    private function mapaRealizacoes(int $provaId, array $alunoIds = []): array
    {
        $sql = 'SELECT aluno_id, status, nota FROM provas_realizacoes WHERE prova_id = :prova_id';
        $params = ['prova_id' => $provaId];
        if ($alunoIds !== []) {
            $ph = [];
            $i = 0;
            foreach ($alunoIds as $alunoId) {
                $alunoId = (int) $alunoId;
                if ($alunoId <= 0) {
                    continue;
                }
                $k = 'a' . $i;
                $ph[] = ':' . $k;
                $params[$k] = $alunoId;
                $i++;
            }
            if ($ph === []) {
                return [];
            }
            $sql .= ' AND aluno_id IN (' . implode(',', $ph) . ')';
        }
        $rows = $this->db->fetchAll($sql, $params) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['aluno_id']] = $row;
        }

        return $map;
    }

    /**
     * @param list<int> $alunoIds
     * @return array<int,array<int,array>>
     */
    private function mapaRespostas(int $provaId, array $alunoIds = []): array
    {
        $sql = 'SELECT aluno_id, questao_id, correta, pontuacao, alternativa_id
                FROM provas_respostas
                WHERE prova_id = :prova_id';
        $params = ['prova_id' => $provaId];
        if ($alunoIds !== []) {
            $ph = [];
            $i = 0;
            foreach ($alunoIds as $alunoId) {
                $alunoId = (int) $alunoId;
                if ($alunoId <= 0) {
                    continue;
                }
                $k = 'r' . $i;
                $ph[] = ':' . $k;
                $params[$k] = $alunoId;
                $i++;
            }
            if ($ph === []) {
                return [];
            }
            $sql .= ' AND aluno_id IN (' . implode(',', $ph) . ')';
        }
        $rows = $this->db->fetchAll($sql, $params) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['aluno_id']][(int) $row['questao_id']] = $row;
        }

        return $map;
    }

    /**
     * @param array<int,array> $realizacoes
     * @return list<array>
     */
    private function listarAlunos(int $blocoId, int $provaId, array $realizacoes): array
    {
        $porId = [];
        foreach ($this->alunosDasTurmas($this->idsTurmas($blocoId, $provaId)) as $aluno) {
            $porId[(int) $aluno['id']] = $aluno;
        }
        $faltando = [];
        foreach (array_keys($realizacoes) as $alunoId) {
            if (!isset($porId[$alunoId])) {
                $faltando[] = (int) $alunoId;
            }
        }
        foreach ($this->alunosPorIds($faltando) as $aluno) {
            $porId[(int) $aluno['id']] = $aluno;
        }
        $lista = array_values($porId);
        usort($lista, static function (array $a, array $b): int {
            $cmp = strcasecmp((string) ($a['turma_nome'] ?? ''), (string) ($b['turma_nome'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcasecmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? ''));
        });

        return $lista;
    }

    /**
     * @return list<int>
     */
    private function idsTurmas(int $blocoId, int $provaId): array
    {
        $ids = [];
        $rows = $this->db->fetchAll(
            'SELECT turma_id FROM provas_turmas WHERE prova_id = :prova_id',
            ['prova_id' => $provaId]
        ) ?: [];
        foreach ($rows as $row) {
            $id = (int) ($row['turma_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids !== []) {
            return array_values($ids);
        }
        try {
            $rows = $this->db->fetchAll(
                'SELECT turma_id FROM provas_blocos_turmas WHERE bloco_id = :bloco_id',
                ['bloco_id' => $blocoId]
            ) ?: [];
            foreach ($rows as $row) {
                $id = (int) ($row['turma_id'] ?? 0);
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        } catch (\Exception $e) {
            error_log('ModificarNotaProvaOnlineService idsTurmas: ' . $e->getMessage());
        }

        return array_values($ids);
    }

    /**
     * @param list<int> $turmaIds
     * @return list<array>
     */
    private function alunosDasTurmas(array $turmaIds): array
    {
        $params = [];
        $ph = [];
        foreach (array_values($turmaIds) as $i => $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $k = 't' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        if ($ph === []) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT a.id, a.nome, a.ra, t.nome AS turma_nome
             FROM alunos a
             INNER JOIN turmas t ON t.id = a.turma_id
             WHERE a.ativo = 1 AND a.turma_id IN (' . implode(',', $ph) . ')
             ORDER BY t.nome ASC, a.nome ASC',
            $params
        ) ?: [];
    }

    /**
     * @param list<int> $ids
     * @return list<array>
     */
    private function alunosPorIds(array $ids): array
    {
        $params = [];
        $ph = [];
        foreach (array_values($ids) as $i => $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $k = 'id' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        if ($ph === []) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT a.id, a.nome, a.ra, COALESCE(t.nome, \'\') AS turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id IN (' . implode(',', $ph) . ')',
            $params
        ) ?: [];
    }

    /**
     * @param list<int> $ids
     * @return array<int,string>
     */
    private function nomesAlunos(array $ids): array
    {
        $map = [];
        foreach ($this->alunosPorIds($ids) as $aluno) {
            $map[(int) $aluno['id']] = (string) $aluno['nome'];
        }

        return $map;
    }

    /**
     * @param list<array> $questoes
     * @return list<array>
     */
    private function historico(int $provaId, array $questoes): array
    {
        $rows = $this->db->fetchAll(
            'SELECT l.lote, l.usuario_nome, l.observacao, l.escopo, l.nota_anterior, l.nota_nova,
                    l.detalhes_json, l.created_at, a.nome AS aluno_nome
             FROM provas_notas_alteracoes_log l
             LEFT JOIN alunos a ON a.id = l.aluno_id
             WHERE l.prova_id = :prova_id
             ORDER BY l.id DESC
             LIMIT 200',
            ['prova_id' => $provaId]
        ) ?: [];

        $numeros = [];
        foreach ($questoes as $questao) {
            $numeros[(int) $questao['id']] = (int) $questao['numero'];
        }
        $rotulos = [
            'todos' => 'Todos os alunos',
            'aluno' => 'Todas as questões de um aluno',
            'questao' => 'Uma questão para todos',
            'manual' => 'Questão a questão',
            'zerar' => 'Nota zerada de todos os alunos',
            'zerar_aluno' => 'Nota zerada de um aluno',
        ];
        $grupos = [];
        foreach ($rows as $row) {
            $lote = (string) ($row['lote'] ?? '');
            if ($lote === '') {
                $lote = 'avulso-' . (string) ($row['created_at'] ?? '') . '-' . (string) ($row['usuario_nome'] ?? '');
            }
            if (!isset($grupos[$lote])) {
                $escopo = (string) ($row['escopo'] ?? 'manual');
                $quando = (string) ($row['created_at'] ?? '');
                $grupos[$lote] = [
                    'quando' => $quando !== '' ? date('d/m/Y H:i', strtotime($quando)) : '',
                    'usuario_nome' => (string) ($row['usuario_nome'] ?? ''),
                    'observacao' => (string) ($row['observacao'] ?? ''),
                    'escopo_label' => $rotulos[$escopo] ?? 'Alteração',
                    'alunos' => [],
                ];
            }
            $detalhes = json_decode((string) ($row['detalhes_json'] ?? ''), true);
            $itens = [];
            if (is_array($detalhes)) {
                foreach ($detalhes as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $qid = (int) ($item['questao_id'] ?? 0);
                    $numero = (int) ($item['numero'] ?? ($numeros[$qid] ?? 0));
                    $itens[] = [
                        'numero' => $numero,
                        'antes' => $this->rotuloAcerto($item['antes'] ?? null),
                        'depois' => $this->rotuloAcerto($item['depois'] ?? null),
                    ];
                }
            }
            $grupos[$lote]['alunos'][] = [
                'nome' => trim((string) ($row['aluno_nome'] ?? '')) !== '' ? (string) $row['aluno_nome'] : 'Aluno removido',
                'nota_anterior' => $this->formatarNota($row['nota_anterior'] ?? null),
                'nota_nova' => $this->formatarNota($row['nota_nova'] ?? null),
                'detalhes' => $itens,
            ];
        }

        return array_values($grupos);
    }

    private function rotuloAcerto($valor): string
    {
        if ($valor === null || $valor === '') {
            return 'Sem resposta';
        }

        return (int) $valor === 1 ? 'Acertou' : 'Errou';
    }

    private function formatarNota($valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return number_format((float) $valor, 2, ',', '.');
    }

    /**
     * @return array<string,string>
     */
    private function rotulosStatus(): array
    {
        return [
            'finalizado' => 'Concluída',
            'iniciado' => 'Em andamento',
            'cancelada' => 'Cancelada',
            'nao_realizou' => 'Não realizou',
        ];
    }

    private function temInvalidada(): bool
    {
        if ($this->temColunaInvalidada !== null) {
            return $this->temColunaInvalidada;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'provas_questoes'
                   AND COLUMN_NAME = 'invalidada'
                 LIMIT 1"
            );
            $this->temColunaInvalidada = !empty($row);
        } catch (\Exception $e) {
            $this->temColunaInvalidada = false;
        }

        return $this->temColunaInvalidada;
    }

    private function garantirTabela(): void
    {
        static $feito = false;
        if ($feito) {
            return;
        }
        $feito = true;
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS provas_notas_alteracoes_log (
                id INT NOT NULL AUTO_INCREMENT,
                lote VARCHAR(32) NOT NULL,
                bloco_id INT NULL,
                prova_id INT NOT NULL,
                aluno_id INT NULL,
                usuario_id INT NOT NULL,
                usuario_nome VARCHAR(255) NOT NULL,
                observacao TEXT NOT NULL,
                ciente TINYINT(1) NOT NULL DEFAULT 1,
                escopo VARCHAR(20) NOT NULL,
                nota_anterior DECIMAL(10,2) NULL,
                nota_nova DECIMAL(10,2) NULL,
                detalhes_json MEDIUMTEXT NULL,
                ip VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_prova_nota_alt (prova_id, id),
                KEY idx_bloco_nota_alt (bloco_id),
                KEY idx_lote_nota_alt (lote)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function textoPlano(string $html): string
    {
        $texto = trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    private function cortar(string $texto, int $limite): string
    {
        if (mb_strlen($texto) <= $limite) {
            return $texto;
        }

        return mb_substr($texto, 0, $limite - 1) . '…';
    }
}
