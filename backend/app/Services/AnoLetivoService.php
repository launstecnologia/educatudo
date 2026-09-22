<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/PeriodoLetivo.php';

/**
 * Cadastro de ano letivo e trava da divisão quando já há lançamentos no período.
 */
class AnoLetivoService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function tabelaExiste(): bool
    {
        return $this->db->tableExists('ano_letivo');
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buscarPorId(int $id): ?array
    {
        if ($id <= 0 || !$this->tabelaExiste()) {
            return null;
        }
        $item = $this->db->fetch('SELECT * FROM ano_letivo WHERE id = :id', ['id' => $id]);
        if (!is_array($item)) {
            return null;
        }
        $item['periodo_tipo'] = PeriodoLetivo::normalizarTipo((string) ($item['periodo_tipo'] ?? PeriodoLetivo::tipoPadrao()));
        return $item;
    }

    /**
     * @param array<string,mixed> $dados
     * @return array{id:int,ano:int,periodo_tipo:string}
     */
    public function cadastrar(array $dados): array
    {
        $payload = $this->validarPayload($dados);
        $existe = $this->db->fetch('SELECT id FROM ano_letivo WHERE ano = :ano', ['ano' => $payload['ano']]);
        if ($existe) {
            throw new InvalidArgumentException('Já existe ano letivo para este ano.');
        }
        $this->exigirColunaSeNaoPadrao($payload['periodo_tipo']);
        $this->db->beginTransaction();
        try {
            if (PeriodoLetivo::temColunaPeriodoTipo()) {
                $id = (int) $this->db->insert(
                    'INSERT INTO ano_letivo (ano, data_inicio, data_fim, periodo_tipo, ativo)
                     VALUES (:ano, :data_inicio, :data_fim, :periodo_tipo, :ativo)',
                    $payload
                );
            } else {
                $id = (int) $this->db->insert(
                    'INSERT INTO ano_letivo (ano, data_inicio, data_fim, ativo)
                     VALUES (:ano, :data_inicio, :data_fim, :ativo)',
                    [
                        'ano' => $payload['ano'],
                        'data_inicio' => $payload['data_inicio'],
                        'data_fim' => $payload['data_fim'],
                        'ativo' => $payload['ativo'],
                    ]
                );
            }
            $this->conferirPeriodoGravado($id, $payload['periodo_tipo']);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
        PeriodoLetivo::invalidarCache($payload['ano']);
        return ['id' => $id, 'ano' => $payload['ano'], 'periodo_tipo' => $payload['periodo_tipo']];
    }

    /**
     * @param array<string,mixed> $dados
     * @return array{id:int,ano:int,periodo_tipo:string}
     */
    public function atualizar(int $id, array $dados): array
    {
        $atual = $this->buscarPorId($id);
        if ($atual === null) {
            throw new InvalidArgumentException('Ano letivo não encontrado.');
        }
        $payload = $this->validarPayload($dados);
        $outro = $this->db->fetch(
            'SELECT id FROM ano_letivo WHERE ano = :ano AND id != :id',
            ['ano' => $payload['ano'], 'id' => $id]
        );
        if ($outro) {
            throw new InvalidArgumentException('Já existe outro ano letivo com este ano.');
        }

        $tipoAtual = PeriodoLetivo::normalizarTipo((string) ($atual['periodo_tipo'] ?? PeriodoLetivo::tipoPadrao()));
        if ($payload['periodo_tipo'] !== $tipoAtual) {
            $uso = $this->usoDaDivisao((int) $atual['ano']);
            if (!empty($uso['bloqueada'])) {
                throw new InvalidArgumentException((string) $uso['mensagem']);
            }
        }

        $this->exigirColunaSeNaoPadrao($payload['periodo_tipo']);
        $params = $payload;
        $params['id'] = $id;
        $this->db->beginTransaction();
        try {
            if (PeriodoLetivo::temColunaPeriodoTipo()) {
                $this->db->update(
                    'UPDATE ano_letivo
                     SET ano = :ano, data_inicio = :data_inicio, data_fim = :data_fim,
                         periodo_tipo = :periodo_tipo, ativo = :ativo
                     WHERE id = :id',
                    $params
                );
            } else {
                $this->db->update(
                    'UPDATE ano_letivo
                     SET ano = :ano, data_inicio = :data_inicio, data_fim = :data_fim, ativo = :ativo
                     WHERE id = :id',
                    [
                        'ano' => $payload['ano'],
                        'data_inicio' => $payload['data_inicio'],
                        'data_fim' => $payload['data_fim'],
                        'ativo' => $payload['ativo'],
                        'id' => $id,
                    ]
                );
            }
            $this->conferirPeriodoGravado($id, $payload['periodo_tipo']);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
        PeriodoLetivo::invalidarCache((int) $atual['ano']);
        PeriodoLetivo::invalidarCache($payload['ano']);
        return ['id' => $id, 'ano' => $payload['ano'], 'periodo_tipo' => $payload['periodo_tipo']];
    }

    /**
     * @return array{bloqueada:bool,total:int,rotulos:list<string>,mensagem:string}
     */
    public function usoDaDivisao(int $ano): array
    {
        $vazio = [
            'bloqueada' => false,
            'total' => 0,
            'rotulos' => [],
            'mensagem' => '',
        ];
        if ($ano < 2000 || $ano > 2100) {
            return $vazio;
        }

        $fontes = [
            ['tabela' => 'provas_blocos', 'rotulo' => 'provas', 'extra' => 'deleted_at'],
            ['tabela' => 'jornadas', 'rotulo' => 'jornadas', 'extra' => 'deleted_at'],
            ['tabela' => 'boletim_regras', 'rotulo' => 'boletim'],
            ['tabela' => 'faltas_eventos', 'rotulo' => 'faltas'],
            ['tabela' => 'conselho_sessoes', 'rotulo' => 'conselho de classe'],
            ['tabela' => 'diario_fechamentos', 'rotulo' => 'diário'],
            ['tabela' => 'regras_academicas', 'rotulo' => 'regras acadêmicas'],
            ['tabela' => 'resultado_academico', 'rotulo' => 'resultados finais'],
            ['tabela' => 'fechamento_periodo', 'rotulo' => 'fechamento'],
        ];

        $total = 0;
        $rotulos = [];
        foreach ($fontes as $fonte) {
            try {
                $n = $this->contarPorAno($fonte['tabela'], $ano, $fonte['extra'] ?? null);
            } catch (Throwable $e) {
                error_log('AnoLetivo usoDaDivisao ' . $fonte['tabela'] . ': ' . $e->getMessage());
                $n = 1;
            }
            if ($n <= 0) {
                continue;
            }
            $total += $n;
            $rotulos[] = $fonte['rotulo'];
        }

        if ($total <= 0) {
            return $vazio;
        }

        $lista = implode(', ', $rotulos);
        return [
            'bloqueada' => true,
            'total' => $total,
            'rotulos' => $rotulos,
            'mensagem' => 'Não é possível alterar a divisão do ano: já existem cadastros usando esses períodos (' . $lista . ').',
        ];
    }

    /**
     * @param array<string,mixed> $dados
     * @return array{ano:int,data_inicio:?string,data_fim:?string,periodo_tipo:string,ativo:int}
     */
    private function validarPayload(array $dados): array
    {
        $ano = (int) ($dados['ano'] ?? 0);
        if ($ano < 2000 || $ano > 2100) {
            throw new InvalidArgumentException('Ano inválido.');
        }
        $dataInicio = trim((string) ($dados['data_inicio'] ?? ''));
        $dataFim = trim((string) ($dados['data_fim'] ?? ''));
        $dataInicio = $dataInicio !== '' ? $dataInicio : null;
        $dataFim = $dataFim !== '' ? $dataFim : null;
        if ($dataInicio !== null && $dataFim !== null && $dataFim < $dataInicio) {
            throw new InvalidArgumentException('A data fim não pode ser anterior à data início.');
        }

        $bruto = trim((string) ($dados['periodo_tipo'] ?? ''));
        if ($bruto === '') {
            $periodoTipo = PeriodoLetivo::tipoPadrao();
        } elseif (!PeriodoLetivo::tipoValido($bruto)) {
            throw new InvalidArgumentException('Divisão do ano inválida.');
        } else {
            $periodoTipo = PeriodoLetivo::normalizarTipo($bruto);
        }

        return [
            'ano' => $ano,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'periodo_tipo' => $periodoTipo,
            'ativo' => !empty($dados['ativo']) ? 1 : 0,
        ];
    }

    private function exigirColunaSeNaoPadrao(string $periodoTipo): void
    {
        if ($periodoTipo === PeriodoLetivo::tipoPadrao() || PeriodoLetivo::temColunaPeriodoTipo()) {
            return;
        }
        throw new InvalidArgumentException(
            'A divisão do ano ainda não está disponível neste banco. Execute a migration de período do ano letivo.'
        );
    }

    private function conferirPeriodoGravado(int $id, string $esperado): void
    {
        if ($id <= 0 || !PeriodoLetivo::temColunaPeriodoTipo()) {
            return;
        }
        $row = $this->db->fetch('SELECT periodo_tipo FROM ano_letivo WHERE id = :id', ['id' => $id]);
        $gravado = PeriodoLetivo::normalizarTipo((string) ($row['periodo_tipo'] ?? ''));
        if ($gravado !== $esperado) {
            throw new RuntimeException(
                'A divisão do ano não foi gravada. Execute a migration de período do ano letivo e tente novamente.'
            );
        }
    }

    private function contarPorAno(string $tabela, int $ano, ?string $extra = null): int
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabela) || !$this->db->tableExists($tabela) || !$this->colunaExiste($tabela, 'ano_letivo')) {
            return 0;
        }
        $sql = "SELECT COUNT(*) AS n FROM `{$tabela}` WHERE ano_letivo = :ano";
        if ($extra === 'deleted_at' && $this->colunaExiste($tabela, 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $row = $this->db->fetch($sql, ['ano' => $ano]);
        return (int) ($row['n'] ?? 0);
    }

    private function colunaExiste(string $tabela, string $coluna): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabela) || !preg_match('/^[a-zA-Z0-9_]+$/', $coluna)) {
            return false;
        }
        try {
            $row = $this->db->fetch("SHOW COLUMNS FROM `{$tabela}` LIKE '{$coluna}'");
            return is_array($row) && !empty($row);
        } catch (Throwable $e) {
            return false;
        }
    }
}
