<?php

namespace App\Modulos\Matricula\Services;

require_once __DIR__ . '/MatriculaProcessoService.php';
require_once __DIR__ . '/../Models/MatriculaProcesso.php';

use App\Modulos\Matricula\Models\MatriculaProcesso;
use Database;

class MatriculaCampanhaService
{
    private $db;
    private MatriculaProcessoService $processoService;
    private MatriculaProcesso $model;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->processoService = new MatriculaProcessoService($this->db);
        $this->model = $this->processoService->getModel();
    }

    public function schemaReady(): bool
    {
        try {
            return (bool) $this->db->fetch('SHOW TABLES LIKE ?', ['matricula_campanhas']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT c.*, ao.ano AS ano_origem, ad.ano AS ano_destino,
                    (SELECT COUNT(*) FROM matricula_processos p WHERE p.campanha_id = c.id) AS total_processos
             FROM matricula_campanhas c
             LEFT JOIN ano_letivo ao ON ao.id = c.ano_origem_id
             LEFT JOIN ano_letivo ad ON ad.id = c.ano_destino_id
             ORDER BY c.created_at DESC"
        ) ?: [];
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0 || !$this->schemaReady()) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT c.*, ao.ano AS ano_origem, ad.ano AS ano_destino
             FROM matricula_campanhas c
             LEFT JOIN ano_letivo ao ON ao.id = c.ano_origem_id
             LEFT JOIN ano_letivo ad ON ad.id = c.ano_destino_id
             WHERE c.id = ?",
            [$id]
        );
        return $row ?: null;
    }

    public function campanhaAbertaParaAluno(int $alunoId): ?array
    {
        if ($alunoId <= 0 || !$this->schemaReady()) {
            return null;
        }
        $hoje = date('Y-m-d');
        $campanhas = $this->db->fetchAll(
            "SELECT * FROM matricula_campanhas
             WHERE status = 'aberta' AND inicio <= ? AND fim >= ?
             ORDER BY id DESC",
            [$hoje, $hoje]
        ) ?: [];
        foreach ($campanhas as $c) {
            $proc = $this->processoDoAluno((int) $c['id'], $alunoId);
            if ($proc && !in_array($proc['status'] ?? '', ['cancelada', 'abandonada'], true)) {
                $c['processo'] = $proc;
                return $c;
            }
        }
        return null;
    }

    public function processoDoAluno(int $campanhaId, int $alunoId): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM matricula_processos WHERE campanha_id = ? AND aluno_id = ? ORDER BY id DESC LIMIT 1',
            [$campanhaId, $alunoId]
        );
        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function criar(array $data, ?int $usuarioId = null): int
    {
        $nome = mb_substr(trim((string) ($data['nome'] ?? '')), 0, 160);
        $anoOrigem = (int) ($data['ano_origem_id'] ?? 0);
        $anoDestino = (int) ($data['ano_destino_id'] ?? 0);
        $inicio = trim((string) ($data['inicio'] ?? ''));
        $fim = trim((string) ($data['fim'] ?? ''));
        if ($nome === '' || $anoOrigem <= 0 || $anoDestino <= 0 || $inicio === '' || $fim === '') {
            throw new \InvalidArgumentException('Preencha nome, anos letivos e o prazo da campanha.');
        }
        if ($anoOrigem === $anoDestino) {
            throw new \InvalidArgumentException('Ano de origem e destino devem ser diferentes.');
        }
        if ($inicio > $fim) {
            throw new \InvalidArgumentException('A data inicial não pode ser depois da data final.');
        }

        $this->db->insert(
            'INSERT INTO matricula_campanhas
             (nome, ano_origem_id, ano_destino_id, inicio, fim, status, plano_padrao_id, reajuste_pct,
              fila_auto_oferecer, exige_censo, criado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $nome,
                $anoOrigem,
                $anoDestino,
                $inicio,
                $fim,
                'rascunho',
                (int) ($data['plano_padrao_id'] ?? 0) ?: null,
                isset($data['reajuste_pct']) && $data['reajuste_pct'] !== '' ? (float) $data['reajuste_pct'] : null,
                !empty($data['fila_auto_oferecer']) ? 1 : 0,
                !empty($data['exige_censo']) ? 1 : 0,
                $usuarioId,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function atualizar(int $id, array $data): void
    {
        $campanha = $this->findById($id);
        if (!$campanha) {
            throw new \InvalidArgumentException('Campanha não encontrada.');
        }
        $nome = mb_substr(trim((string) ($data['nome'] ?? $campanha['nome'])), 0, 160);
        $inicio = trim((string) ($data['inicio'] ?? $campanha['inicio']));
        $fim = trim((string) ($data['fim'] ?? $campanha['fim']));
        $this->db->update(
            'UPDATE matricula_campanhas
             SET nome = ?, inicio = ?, fim = ?, plano_padrao_id = ?, reajuste_pct = ?,
                 fila_auto_oferecer = ?, exige_censo = ?
             WHERE id = ?',
            [
                $nome,
                $inicio,
                $fim,
                (int) ($data['plano_padrao_id'] ?? 0) ?: null,
                isset($data['reajuste_pct']) && $data['reajuste_pct'] !== '' ? (float) $data['reajuste_pct'] : null,
                !empty($data['fila_auto_oferecer']) ? 1 : 0,
                !empty($data['exige_censo']) ? 1 : 0,
                $id,
            ]
        );
    }

    public function alterarStatus(int $id, string $status): void
    {
        $ok = ['rascunho', 'aberta', 'encerrada'];
        if (!in_array($status, $ok, true)) {
            throw new \InvalidArgumentException('Status de campanha inválido.');
        }
        $this->db->update('UPDATE matricula_campanhas SET status = ? WHERE id = ?', [$status, $id]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarMapaPlanos(int $campanhaId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT m.*, po.nome AS plano_origem_nome, pd.nome AS plano_destino_nome, s.nome AS serie_nome
             FROM matricula_campanha_planos m
             LEFT JOIN finance_plans po ON po.id = m.plano_origem_id
             LEFT JOIN finance_plans pd ON pd.id = m.plano_destino_id
             LEFT JOIN serie s ON s.id = m.serie_id
             WHERE m.campanha_id = ?
             ORDER BY m.id ASC",
            [$campanhaId]
        ) ?: [];
    }

    /**
     * @param list<array<string,mixed>> $linhas
     */
    public function salvarMapaPlanos(int $campanhaId, array $linhas): void
    {
        $this->db->query('DELETE FROM matricula_campanha_planos WHERE campanha_id = ?', [$campanhaId]);
        foreach ($linhas as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $destino = (int) ($linha['plano_destino_id'] ?? 0);
            if ($destino <= 0) {
                continue;
            }
            $this->db->insert(
                'INSERT INTO matricula_campanha_planos
                 (campanha_id, plano_origem_id, serie_id, turma_origem_id, plano_destino_id)
                 VALUES (?,?,?,?,?)',
                [
                    $campanhaId,
                    (int) ($linha['plano_origem_id'] ?? 0) ?: null,
                    (int) ($linha['serie_id'] ?? 0) ?: null,
                    (int) ($linha['turma_origem_id'] ?? 0) ?: null,
                    $destino,
                ]
            );
        }
    }

    public function resolverPlanoDestino(array $campanha, int $alunoId, ?int $serieDestinoId = null, ?int $turmaOrigemId = null): ?int
    {
        $mapa = $this->listarMapaPlanos((int) $campanha['id']);
        $planoAtual = $this->planoAtualDoAluno($alunoId, (int) $campanha['ano_origem_id']);

        foreach ($mapa as $linha) {
            $origem = (int) ($linha['plano_origem_id'] ?? 0);
            if ($origem > 0 && $planoAtual === $origem) {
                return (int) $linha['plano_destino_id'];
            }
        }
        if ($serieDestinoId && $serieDestinoId > 0) {
            foreach ($mapa as $linha) {
                if ((int) ($linha['serie_id'] ?? 0) === $serieDestinoId && (int) ($linha['plano_origem_id'] ?? 0) <= 0) {
                    return (int) $linha['plano_destino_id'];
                }
            }
        }
        if ($turmaOrigemId && $turmaOrigemId > 0) {
            foreach ($mapa as $linha) {
                if ((int) ($linha['turma_origem_id'] ?? 0) === $turmaOrigemId) {
                    return (int) $linha['plano_destino_id'];
                }
            }
        }
        $padrao = (int) ($campanha['plano_padrao_id'] ?? 0);
        return $padrao > 0 ? $padrao : null;
    }

    /**
     * Gera processos de rematrícula para alunos ativos do ano de origem.
     *
     * @return array{criados:int,pulados:int}
     */
    public function gerarProcessos(int $campanhaId, ?array $user = null): array
    {
        $campanha = $this->findById($campanhaId);
        if (!$campanha) {
            throw new \InvalidArgumentException('Campanha não encontrada.');
        }

        $alunos = $this->alunosDoAnoOrigem((int) $campanha['ano_origem_id']);
        $criados = 0;
        $pulados = 0;
        $userId = (int) ($user['id'] ?? 0) ?: null;

        foreach ($alunos as $aluno) {
            $alunoId = (int) $aluno['id'];
            $existe = $this->processoDoAluno($campanhaId, $alunoId);
            if ($existe && !in_array($existe['status'] ?? '', ['cancelada', 'abandonada'], true)) {
                $pulados++;
                continue;
            }

            $prefill = $this->processoService->prefillFromAluno($alunoId);
            if (empty($prefill['aluno_nome'])) {
                $pulados++;
                continue;
            }

            $turmaDestinoId = $this->sugerirTurmaDestino(
                (int) ($aluno['turma_id'] ?? 0),
                (int) $campanha['ano_destino_id'],
                (string) ($aluno['resultado_ano'] ?? 'nao_lancado')
            );
            if ($turmaDestinoId === null && $this->serieConclui((int) ($aluno['turma_id'] ?? 0))) {
                $pulados++;
                continue;
            }
            $serieDestinoId = null;
            if ($turmaDestinoId) {
                $t = $this->db->fetch('SELECT serie_id FROM turmas WHERE id = ?', [$turmaDestinoId]);
                $serieDestinoId = $t ? ((int) ($t['serie_id'] ?? 0) ?: null) : null;
            }

            $planoId = $this->resolverPlanoDestino(
                $campanha,
                $alunoId,
                $serieDestinoId,
                (int) ($aluno['turma_id'] ?? 0) ?: null
            );

            $payload = [
                'tipo' => 'rematricula',
                'status' => 'rascunho',
                'origem' => 'interno',
                'aluno_id' => $alunoId,
                'ano_letivo_id' => (int) $campanha['ano_destino_id'],
                'turma_id' => $turmaDestinoId,
                'serie_id' => $serieDestinoId,
                'campanha_id' => $campanhaId,
                'aluno_nome' => $prefill['aluno_nome'],
                'aluno_cpf' => $prefill['aluno_cpf'] ?? null,
                'aluno_data_nasc' => $prefill['aluno_data_nasc'] ?? null,
                'aluno_email' => $prefill['aluno_email'] ?? null,
                'aluno_telefone' => $prefill['aluno_telefone'] ?? null,
                'aluno_nome_mae' => $aluno['nome_mae'] ?? null,
                'aluno_nome_pai' => $aluno['nome_pai'] ?? null,
                'aluno_codigo_inep' => $aluno['codigo_inep'] ?? null,
                'aluno_cor_raca' => $aluno['cor_raca'] ?? null,
                'aluno_nacionalidade' => $aluno['nacionalidade'] ?? null,
                'resp_nome' => $prefill['resp_nome'] ?? '',
                'resp_cpf' => $prefill['resp_cpf'] ?? null,
                'resp_email' => $prefill['resp_email'] ?? null,
                'resp_telefone' => $prefill['resp_telefone'] ?? null,
                'resp_parentesco' => $prefill['resp_parentesco'] ?? null,
                'resp_endereco' => $prefill['resp_endereco'] ?? null,
                'expira_em' => ($campanha['fim'] ?? '') . ' 23:59:59',
                'criado_por' => $userId,
            ];
            if ($planoId) {
                $payload['finance_plan_id'] = $planoId;
                $payload['finance_cobrancas'] = json_encode([
                    ['tipo' => 'mensalidade', 'plan_id' => $planoId, 'desconto_rule_ids' => []],
                ], JSON_UNESCAPED_UNICODE);
            }

            $newId = $this->model->create($payload);
            $this->model->transition($newId, 'rascunho', $user, 'campanha_gerar');
            if ($planoId) {
                $this->processoService->sincronizarResponsaveisEProdutos($newId, [
                    'finance_plan_id' => $planoId,
                    'resp_nome' => $payload['resp_nome'],
                    'resp_cpf' => $payload['resp_cpf'],
                    'resp_email' => $payload['resp_email'],
                    'resp_telefone' => $payload['resp_telefone'],
                    'resp_parentesco' => $payload['resp_parentesco'],
                    'resp_endereco' => $payload['resp_endereco'],
                ]);
            }
            $criados++;
        }

        return ['criados' => $criados, 'pulados' => $pulados];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarProcessos(int $campanhaId): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, t.nome AS turma_nome, t.serie AS turma_serie
             FROM matricula_processos p
             LEFT JOIN turmas t ON t.id = p.turma_id
             WHERE p.campanha_id = ?
             ORDER BY p.aluno_nome ASC",
            [$campanhaId]
        ) ?: [];
    }

    public function aplicarPlanoNoProcesso(int $enrollmentId, int $planoId, ?int $campanhaId = null): void
    {
        if ($planoId <= 0) {
            return;
        }
        $proc = $this->model->findById($enrollmentId);
        if (!$proc) {
            throw new \InvalidArgumentException('Processo não encontrado.');
        }
        if ($campanhaId !== null && (int) ($proc['campanha_id'] ?? 0) !== $campanhaId) {
            throw new \InvalidArgumentException('Processo não pertence a esta campanha.');
        }
        if (in_array($proc['status'] ?? '', ['enturmada', 'cancelada'], true)) {
            throw new \InvalidArgumentException('Não é possível alterar o plano neste status.');
        }
        $this->processoService->sincronizarResponsaveisEProdutos($enrollmentId, [
            'finance_plan_id' => $planoId,
            'resp_nome' => $proc['resp_nome'] ?? '',
            'resp_cpf' => $proc['resp_cpf'] ?? null,
            'resp_email' => $proc['resp_email'] ?? null,
            'resp_telefone' => $proc['resp_telefone'] ?? null,
            'resp_parentesco' => $proc['resp_parentesco'] ?? null,
            'resp_endereco' => $proc['resp_endereco'] ?? null,
        ]);
    }

    public function campanhaNoPrazo(array $campanha): bool
    {
        if (($campanha['status'] ?? '') !== 'aberta') {
            return false;
        }
        $hoje = date('Y-m-d');
        return $hoje >= ($campanha['inicio'] ?? '') && $hoje <= ($campanha['fim'] ?? '');
    }

    private function planoAtualDoAluno(int $alunoId, int $anoOrigemId): ?int
    {
        try {
            $row = $this->db->fetch(
                "SELECT finance_plan_id FROM matricula_processos
                 WHERE aluno_id = ? AND ano_letivo_id = ? AND finance_plan_id IS NOT NULL
                 ORDER BY id DESC LIMIT 1",
                [$alunoId, $anoOrigemId]
            );
            if ($row && (int) ($row['finance_plan_id'] ?? 0) > 0) {
                return (int) $row['finance_plan_id'];
            }
        } catch (\Throwable $e) {
            // ok
        }
        try {
            $row = $this->db->fetch(
                "SELECT plan_id FROM finance_contracts
                 WHERE aluno_id = ? AND ano_letivo_id = ? AND status = 'ativo'
                 ORDER BY id DESC LIMIT 1",
                [$alunoId, $anoOrigemId]
            );
            if ($row && (int) ($row['plan_id'] ?? 0) > 0) {
                return (int) $row['plan_id'];
            }
        } catch (\Throwable $e) {
            // ok
        }
        return null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function alunosDoAnoOrigem(int $anoOrigemId): array
    {
        $temResultado = $this->temColuna('matricula', 'resultado_ano');
        $selResultado = $temResultado ? 'm.resultado_ano' : "'nao_lancado' AS resultado_ano";
        $colsAluno = 'a.id, a.nome, a.turma_id, a.cpf, a.email, a.telefone, a.data_nasc';
        foreach (['nome_mae', 'nome_pai', 'codigo_inep', 'cor_raca', 'nacionalidade'] as $c) {
            if ($this->temColuna('alunos', $c)) {
                $colsAluno .= ', a.' . $c;
            }
        }

        return $this->db->fetchAll(
            "SELECT DISTINCT $colsAluno, $selResultado
             FROM alunos a
             INNER JOIN matricula m ON m.aluno_id = a.id
             WHERE a.ativo = 1
               AND m.ano_letivo_id = ?
               AND m.status = 'ativa'
               AND m.data_saida IS NULL
             ORDER BY a.nome",
            [$anoOrigemId]
        ) ?: [];
    }

    private function sugerirTurmaDestino(int $turmaOrigemId, int $anoDestinoId, string $resultado): ?int
    {
        if ($turmaOrigemId <= 0 || $anoDestinoId <= 0) {
            return null;
        }
        if ($this->temColuna('turmas', 'turma_origem_id')) {
            $sucessora = $this->db->fetch(
                'SELECT id FROM turmas WHERE turma_origem_id = ? AND ano_letivo_id = ? AND ativo = 1 LIMIT 1',
                [$turmaOrigemId, $anoDestinoId]
            );
            if ($sucessora && $resultado !== 'reprovado') {
                if ($this->serieConclui($turmaOrigemId)) {
                    return null;
                }
                return (int) $sucessora['id'];
            }
        }

        $origem = $this->db->fetch(
            'SELECT id, nome, serie_id, curso_novo_id FROM turmas WHERE id = ?',
            [$turmaOrigemId]
        );
        if (!$origem) {
            return null;
        }

        if ($resultado === 'reprovado' && !empty($origem['serie_id'])) {
            $mesma = $this->db->fetch(
                'SELECT id FROM turmas
                 WHERE ano_letivo_id = ? AND serie_id = ? AND ativo = 1
                 ORDER BY id ASC LIMIT 1',
                [$anoDestinoId, (int) $origem['serie_id']]
            );
            return $mesma ? (int) $mesma['id'] : null;
        }

        $proximaSerie = $this->proximaSerieId((int) ($origem['serie_id'] ?? 0));
        if ($proximaSerie) {
            $dest = $this->db->fetch(
                'SELECT id FROM turmas
                 WHERE ano_letivo_id = ? AND serie_id = ? AND ativo = 1
                 ORDER BY id ASC LIMIT 1',
                [$anoDestinoId, $proximaSerie]
            );
            if ($dest) {
                return (int) $dest['id'];
            }
        }
        return null;
    }

    private function proximaSerieId(int $serieId): ?int
    {
        if ($serieId <= 0) {
            return null;
        }
        $atual = $this->db->fetch('SELECT id, curso_id, ordem FROM serie WHERE id = ?', [$serieId]);
        if (!$atual) {
            return null;
        }
        $prox = $this->db->fetch(
            'SELECT id FROM serie
             WHERE curso_id = ? AND ativo = 1 AND ordem > ?
             ORDER BY ordem ASC LIMIT 1',
            [(int) $atual['curso_id'], (int) $atual['ordem']]
        );
        return $prox ? (int) $prox['id'] : null;
    }

    private function serieConclui(int $turmaId): bool
    {
        if ($turmaId <= 0) {
            return false;
        }
        $t = $this->db->fetch('SELECT serie_id FROM turmas WHERE id = ?', [$turmaId]);
        $serieId = (int) ($t['serie_id'] ?? 0);
        if ($serieId <= 0) {
            return false;
        }
        return $this->proximaSerieId($serieId) === null;
    }

    private function temColuna(string $tabela, string $coluna): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/i', $tabela) || !preg_match('/^[a-z0-9_]+$/i', $coluna)) {
            return false;
        }
        try {
            return (bool) $this->db->fetch('SHOW COLUMNS FROM `' . $tabela . '` LIKE ?', [$coluna]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
