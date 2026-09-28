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
        $mapa = [];
        if ($payload['periodo_tipo'] !== $tipoAtual) {
            $usados = $this->periodosEmUso((int) $atual['ano']);
            if ($usados !== []) {
                $mapa = $this->validarMapeamento($usados, $dados['mapeamento'] ?? [], $payload['periodo_tipo']);
            }
        }

        $this->exigirColunaSeNaoPadrao($payload['periodo_tipo']);
        $params = $payload;
        $params['id'] = $id;
        $this->db->beginTransaction();
        try {
            if ($payload['periodo_tipo'] !== $tipoAtual) {
                $this->reclassificarDivisao((int) $atual['ano'], $tipoAtual, $payload['periodo_tipo'], $mapa);
            }
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
     * Períodos que já têm cadastro neste ano (só os números que existem de fato).
     *
     * @return list<array{numero:int,rotulo:string}>
     */
    public function periodosEmUso(int $ano): array
    {
        if ($ano < 2000 || $ano > 2100) {
            return [];
        }
        $tipo = PeriodoLetivo::normalizarTipo((string) (PeriodoLetivo::doAno($ano)['tipo'] ?? PeriodoLetivo::tipoPadrao()));
        $numeros = [];
        foreach ($this->coletarNumerosPeriodo($ano, $tipo) as $n) {
            $numeros[$n] = $n;
        }
        ksort($numeros);
        $rotulos = PeriodoLetivo::rotulosDoTipo($tipo);
        $lista = [];
        foreach ($numeros as $n) {
            $lista[] = [
                'numero' => $n,
                'rotulo' => (string) ($rotulos[$n] ?? ($n . 'º período')),
            ];
        }
        return $lista;
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

    /**
     * @param list<array{numero:int,rotulo:string}> $usados
     * @param mixed $bruto
     * @return array<int,int>
     */
    private function validarMapeamento(array $usados, mixed $bruto, string $tipoNovo): array
    {
        if (!is_array($bruto)) {
            $bruto = [];
        }
        $qtd = PeriodoLetivo::quantidade($tipoNovo);
        $mapa = [];
        $faltando = [];
        foreach ($usados as $item) {
            $origem = (int) ($item['numero'] ?? 0);
            if ($origem < 1) {
                continue;
            }
            $destBruto = $bruto[$origem] ?? $bruto[(string) $origem] ?? null;
            $destino = (int) $destBruto;
            if ($destBruto === null || $destBruto === '' || $destino < 1 || $destino > $qtd) {
                $faltando[] = (string) ($item['rotulo'] ?? ($origem . 'º período'));
                continue;
            }
            $mapa[$origem] = $destino;
        }
        if ($faltando !== []) {
            throw new InvalidArgumentException(
                'Indique para onde vai cada período que já tem cadastro: ' . implode(', ', $faltando) . '.'
            );
        }
        return $mapa;
    }

    /**
     * @param array<int,int> $mapa
     */
    private function reclassificarDivisao(int $ano, string $tipoAtual, string $tipoNovo, array $mapa): void
    {
        if ($tipoAtual === $tipoNovo) {
            return;
        }
        $this->exigirTipoAceito('resultado_academico', 'resultados finais', $ano, $tipoAtual, $tipoNovo);
        $this->exigirTipoAceito('fechamento_periodo', 'fechamentos', $ano, $tipoAtual, $tipoNovo);

        foreach ($this->fontesBimestre() as $fonte) {
            $this->remapearColunaInteira($fonte['tabela'], $fonte['coluna'], $ano, $mapa);
        }
        $this->remapearFaltas($ano, $tipoNovo, $mapa);
        $this->remapearFichas($ano, $mapa);
        $this->remapearJsonBoletim($ano, $mapa);
        $this->remapearRegrasAcademicas($ano, $tipoAtual, $tipoNovo, $mapa);
        $this->remapearResultadoAcademico($ano, $tipoAtual, $tipoNovo, $mapa);
        $this->remapearFechamento($ano, $tipoAtual, $tipoNovo, $mapa);
    }

    /**
     * @return list<array{tabela:string,coluna:string}>
     */
    private function fontesBimestre(): array
    {
        return [
            ['tabela' => 'provas_blocos', 'coluna' => 'bimestre'],
            ['tabela' => 'jornadas', 'coluna' => 'bimestre'],
            ['tabela' => 'boletim_regras', 'coluna' => 'bimestre'],
            ['tabela' => 'conselho_sessoes', 'coluna' => 'bimestre'],
            ['tabela' => 'diario_fechamentos', 'coluna' => 'bimestre'],
            ['tabela' => 'notas_tipo_finais', 'coluna' => 'periodo'],
        ];
    }

    /**
     * @return list<int>
     */
    private function coletarNumerosPeriodo(int $ano, string $tipo): array
    {
        $numeros = [];
        foreach ($this->fontesBimestre() as $fonte) {
            foreach ($this->numerosDaColuna($fonte['tabela'], $fonte['coluna'], $ano) as $n) {
                $numeros[$n] = $n;
            }
        }
        foreach (['regras_academicas', 'resultado_academico', 'fechamento_periodo'] as $tabela) {
            foreach ($this->numerosDaColuna($tabela, 'periodo_numero', $ano, 'periodo_tipo', $tipo) as $n) {
                $numeros[$n] = $n;
            }
        }
        foreach ($this->numerosDasFaltas($ano) as $n) {
            $numeros[$n] = $n;
        }
        foreach ($this->numerosDasFichas($ano) as $n) {
            $numeros[$n] = $n;
        }
        foreach ($this->numerosDoJsonBoletim($ano) as $n) {
            $numeros[$n] = $n;
        }
        $lista = array_values($numeros);
        sort($lista);
        return $lista;
    }

    /**
     * @return list<int>
     */
    private function numerosDaColuna(string $tabela, string $coluna, int $ano, ?string $tipoColuna = null, ?string $tipo = null): array
    {
        if (!$this->identificadorSql($tabela) || !$this->identificadorSql($coluna)) {
            return [];
        }
        if (!$this->db->tableExists($tabela) || !$this->colunaExiste($tabela, $coluna) || !$this->colunaExiste($tabela, 'ano_letivo')) {
            return [];
        }
        $sql = "SELECT DISTINCT `{$coluna}` AS n FROM `{$tabela}` WHERE ano_letivo = :ano AND `{$coluna}` IS NOT NULL";
        $params = ['ano' => $ano];
        if ($tipoColuna !== null && $tipo !== null && $this->identificadorSql($tipoColuna) && $this->colunaExiste($tabela, $tipoColuna)) {
            $sql .= " AND `{$tipoColuna}` = :tipo";
            $params['tipo'] = $tipo;
        }
        try {
            $rows = $this->db->fetchAll($sql, $params);
        } catch (Throwable $e) {
            error_log('AnoLetivo periodos ' . $tabela . ': ' . $e->getMessage());
            return [];
        }
        return $this->numerosDasLinhas(is_array($rows) ? $rows : []);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int>
     */
    private function numerosDasLinhas(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $n = $this->numeroDeValor($row['n'] ?? '');
            if ($n >= 1) {
                $out[$n] = $n;
            }
        }
        return array_values($out);
    }

    /**
     * @return list<int>
     */
    private function numerosDasFaltas(int $ano): array
    {
        if (!$this->db->tableExists('faltas_eventos') || !$this->colunaExiste('faltas_eventos', 'bimestre')) {
            return [];
        }
        try {
            $rows = $this->db->fetchAll(
                'SELECT DISTINCT bimestre AS n FROM faltas_eventos WHERE ano_letivo = :ano',
                ['ano' => $ano]
            );
        } catch (Throwable $e) {
            error_log('AnoLetivo periodos faltas: ' . $e->getMessage());
            return [];
        }
        return $this->numerosDasLinhas(is_array($rows) ? $rows : []);
    }

    /**
     * @return list<int>
     */
    private function numerosDasFichas(int $ano): array
    {
        if (!$this->db->tableExists('boletim_ficha_celulas') || !$this->db->tableExists('boletim_ficha_linhas') || !$this->db->tableExists('boletim_fichas')) {
            return [];
        }
        try {
            $rows = $this->db->fetchAll(
                'SELECT DISTINCT c.periodo_numero AS n
                 FROM boletim_ficha_celulas c
                 INNER JOIN boletim_ficha_linhas l ON l.id = c.linha_id
                 INNER JOIN boletim_fichas f ON f.id = l.ficha_id
                 WHERE f.ano_letivo = :ano AND c.periodo_numero BETWEEN 1 AND 12',
                ['ano' => $ano]
            );
        } catch (Throwable $e) {
            error_log('AnoLetivo periodos fichas: ' . $e->getMessage());
            return [];
        }
        return $this->numerosDasLinhas(is_array($rows) ? $rows : []);
    }

    /**
     * @return list<int>
     */
    private function numerosDoJsonBoletim(int $ano): array
    {
        $numeros = [];
        foreach ($this->jsonsBoletimDoAno($ano) as $json) {
            foreach ($this->numerosNoJson($json) as $n) {
                $numeros[$n] = $n;
            }
        }
        return array_values($numeros);
    }

    /**
     * @return list<string>
     */
    private function jsonsBoletimDoAno(int $ano): array
    {
        $jsons = [];
        if ($this->db->tableExists('boletim_componentes') && $this->db->tableExists('boletim_regras') && $this->colunaExiste('boletim_componentes', 'config_json')) {
            try {
                $rows = $this->db->fetchAll(
                    'SELECT c.config_json AS json
                     FROM boletim_componentes c
                     INNER JOIN boletim_regras r ON r.id = c.regra_id
                     WHERE r.ano_letivo = :ano AND c.config_json IS NOT NULL AND c.config_json != \'\'',
                    ['ano' => $ano]
                );
                foreach (is_array($rows) ? $rows : [] as $row) {
                    $jsons[] = (string) ($row['json'] ?? '');
                }
            } catch (Throwable $e) {
                error_log('AnoLetivo json componentes: ' . $e->getMessage());
            }
        }
        if ($this->db->tableExists('boletim_regras')) {
            $cols = [];
            foreach (['extras_json', 'formula_materias_json'] as $col) {
                if ($this->colunaExiste('boletim_regras', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $select = implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $cols));
                try {
                    $rows = $this->db->fetchAll(
                        "SELECT {$select} FROM boletim_regras WHERE ano_letivo = :ano",
                        ['ano' => $ano]
                    );
                    foreach (is_array($rows) ? $rows : [] as $row) {
                        foreach ($cols as $col) {
                            $jsons[] = (string) ($row[$col] ?? '');
                        }
                    }
                } catch (Throwable $e) {
                    error_log('AnoLetivo json regras: ' . $e->getMessage());
                }
            }
        }
        return $jsons;
    }

    /**
     * @return list<int>
     */
    private function numerosNoJson(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }
        $node = json_decode($json, true);
        if (!is_array($node)) {
            return [];
        }
        $numeros = [];
        $this->colherNumerosJson($node, $numeros);
        return array_values($numeros);
    }

    /**
     * @param array<mixed> $node
     * @param array<int,int> $numeros
     */
    private function colherNumerosJson(array $node, array &$numeros): void
    {
        foreach ($node as $k => $v) {
            $chave = is_string($k) ? $k : '';
            if (in_array($chave, ['prova_bimestres', 'jornada_bimestres'], true) && is_array($v)) {
                foreach ($v as $item) {
                    $n = $this->numeroDeValor($item);
                    if ($n >= 1) {
                        $numeros[$n] = $n;
                    }
                }
                continue;
            }
            if (in_array($chave, ['fontes_bimestres', 'fontes_faltas'], true) && is_array($v)) {
                foreach ($v as $pk => $_pv) {
                    $n = $this->numeroDeValor($pk);
                    if ($n >= 1) {
                        $numeros[$n] = $n;
                    }
                }
                continue;
            }
            if ($chave === 'bimestre') {
                $n = $this->numeroDeValor($v);
                if ($n >= 1) {
                    $numeros[$n] = $n;
                }
                continue;
            }
            if (is_array($v)) {
                $this->colherNumerosJson($v, $numeros);
            }
        }
    }

    private function numeroDeValor(mixed $valor): int
    {
        if (is_int($valor) || (is_string($valor) && preg_match('/^\d+$/', $valor))) {
            $n = (int) $valor;
            return ($n >= 1 && $n <= 12) ? $n : 0;
        }
        $texto = trim((string) $valor);
        if ($texto === '') {
            return 0;
        }
        if (preg_match('/etapa/i', $texto)) {
            return 1;
        }
        if (preg_match('/^(\d{1,2})/u', $texto, $m)) {
            $n = (int) $m[1];
            return ($n >= 1 && $n <= 12) ? $n : 0;
        }
        return 0;
    }

    /**
     * @param array<int,int> $mapa
     */
    private function remapearColunaInteira(string $tabela, string $coluna, int $ano, array $mapa): void
    {
        $pares = $this->paresQueMudamNumero($mapa);
        if ($pares === [] || !$this->identificadorSql($tabela) || !$this->identificadorSql($coluna)) {
            return;
        }
        if (!$this->db->tableExists($tabela) || !$this->colunaExiste($tabela, $coluna) || !$this->colunaExiste($tabela, 'ano_letivo')) {
            return;
        }
        foreach ($pares as $de => $para) {
            $this->executarAtualizacao(
                "UPDATE `{$tabela}` SET `{$coluna}` = :temp WHERE ano_letivo = :ano AND `{$coluna}` = :origem",
                ['temp' => 100 + $de, 'ano' => $ano, 'origem' => $de]
            );
        }
        foreach ($pares as $de => $para) {
            $this->executarAtualizacao(
                "UPDATE `{$tabela}` SET `{$coluna}` = :destino WHERE ano_letivo = :ano AND `{$coluna}` = :temp",
                ['destino' => $para, 'ano' => $ano, 'temp' => 100 + $de]
            );
        }
    }

    /**
     * @param array<int,int> $mapa
     */
    private function remapearFaltas(int $ano, string $tipoNovo, array $mapa): void
    {
        if ($mapa === [] || !$this->db->tableExists('faltas_eventos') || !$this->colunaExiste('faltas_eventos', 'bimestre')) {
            return;
        }
        $rotulos = PeriodoLetivo::rotulosDoTipo($tipoNovo);
        $rows = $this->db->fetchAll(
            'SELECT id, bimestre FROM faltas_eventos WHERE ano_letivo = :ano',
            ['ano' => $ano]
        );
        foreach (is_array($rows) ? $rows : [] as $row) {
            $origem = $this->numeroDeValor($row['bimestre'] ?? '');
            if ($origem < 1 || !isset($mapa[$origem])) {
                continue;
            }
            $destino = (int) $mapa[$origem];
            $atual = trim((string) ($row['bimestre'] ?? ''));
            $novo = preg_match('/^\d+$/', $atual) === 1
                ? (string) $destino
                : (string) ($rotulos[$destino] ?? (string) $destino);
            if ($novo === $atual) {
                continue;
            }
            $this->executarAtualizacao(
                'UPDATE faltas_eventos SET bimestre = :bimestre WHERE id = :id',
                ['bimestre' => $novo, 'id' => (int) ($row['id'] ?? 0)]
            );
        }
    }

    /**
     * @param array<int,int> $mapa
     */
    private function remapearFichas(int $ano, array $mapa): void
    {
        $pares = $this->paresQueMudamNumero($mapa);
        if ($pares === [] || !$this->db->tableExists('boletim_ficha_celulas') || !$this->db->tableExists('boletim_ficha_linhas') || !$this->db->tableExists('boletim_fichas')) {
            return;
        }
        foreach ($pares as $de => $para) {
            $this->executarAtualizacao(
                'UPDATE boletim_ficha_celulas c
                 INNER JOIN boletim_ficha_linhas l ON l.id = c.linha_id
                 INNER JOIN boletim_fichas f ON f.id = l.ficha_id
                 SET c.periodo_numero = :temp
                 WHERE f.ano_letivo = :ano AND c.periodo_numero = :origem',
                ['temp' => 100 + $de, 'ano' => $ano, 'origem' => $de]
            );
        }
        foreach ($pares as $de => $para) {
            $this->executarAtualizacao(
                'UPDATE boletim_ficha_celulas c
                 INNER JOIN boletim_ficha_linhas l ON l.id = c.linha_id
                 INNER JOIN boletim_fichas f ON f.id = l.ficha_id
                 SET c.periodo_numero = :destino
                 WHERE f.ano_letivo = :ano AND c.periodo_numero = :temp',
                ['destino' => $para, 'ano' => $ano, 'temp' => 100 + $de]
            );
        }
    }

    /**
     * @param array<int,int> $mapa
     */
    private function remapearJsonBoletim(int $ano, array $mapa): void
    {
        if ($this->paresQueMudamNumero($mapa) === []) {
            return;
        }
        if ($this->db->tableExists('boletim_componentes') && $this->db->tableExists('boletim_regras') && $this->colunaExiste('boletim_componentes', 'config_json')) {
            $rows = $this->db->fetchAll(
                'SELECT c.id, c.config_json
                 FROM boletim_componentes c
                 INNER JOIN boletim_regras r ON r.id = c.regra_id
                 WHERE r.ano_letivo = :ano AND c.config_json IS NOT NULL AND c.config_json != \'\'',
                ['ano' => $ano]
            );
            foreach (is_array($rows) ? $rows : [] as $row) {
                $novo = $this->jsonRemapeado((string) ($row['config_json'] ?? ''), $mapa);
                if ($novo === null) {
                    continue;
                }
                $this->executarAtualizacao(
                    'UPDATE boletim_componentes SET config_json = :json WHERE id = :id',
                    ['json' => $novo, 'id' => (int) ($row['id'] ?? 0)]
                );
            }
        }
        if (!$this->db->tableExists('boletim_regras')) {
            return;
        }
        foreach (['extras_json', 'formula_materias_json'] as $col) {
            if (!$this->identificadorSql($col) || !$this->colunaExiste('boletim_regras', $col)) {
                continue;
            }
            $rows = $this->db->fetchAll(
                "SELECT id, `{$col}` AS json FROM boletim_regras WHERE ano_letivo = :ano AND `{$col}` IS NOT NULL AND `{$col}` != ''",
                ['ano' => $ano]
            );
            foreach (is_array($rows) ? $rows : [] as $row) {
                $novo = $this->jsonRemapeado((string) ($row['json'] ?? ''), $mapa);
                if ($novo === null) {
                    continue;
                }
                $this->executarAtualizacao(
                    "UPDATE boletim_regras SET `{$col}` = :json WHERE id = :id",
                    ['json' => $novo, 'id' => (int) ($row['id'] ?? 0)]
                );
            }
        }
    }

    /**
     * @param array<int,int> $mapa
     */
    private function jsonRemapeado(string $json, array $mapa): ?string
    {
        $node = json_decode($json, true);
        if (!is_array($node)) {
            return null;
        }
        $novo = $this->remapearNoJson($node, $mapa);
        $encoded = json_encode($novo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || $encoded === $json) {
            return null;
        }
        return $encoded;
    }

    /**
     * @param array<mixed> $node
     * @param array<int,int> $mapa
     * @return array<mixed>
     */
    private function remapearNoJson(array $node, array $mapa): array
    {
        $out = [];
        foreach ($node as $k => $v) {
            $chave = is_string($k) ? $k : '';
            if (in_array($chave, ['prova_bimestres', 'jornada_bimestres'], true) && is_array($v)) {
                $nums = [];
                foreach ($v as $item) {
                    $n = $this->numeroDeValor($item);
                    if ($n >= 1) {
                        $nums[] = $mapa[$n] ?? $n;
                    }
                }
                $out[$k] = array_values(array_unique($nums));
                continue;
            }
            if (in_array($chave, ['fontes_bimestres', 'fontes_faltas'], true) && is_array($v)) {
                $novoMapa = [];
                foreach ($v as $pk => $pv) {
                    $n = $this->numeroDeValor($pk);
                    $dest = ($n >= 1 && isset($mapa[$n])) ? $mapa[$n] : $pk;
                    $atual = $novoMapa[$dest] ?? null;
                    if ($atual !== null && (int) $atual > 0 && (int) $pv > 0 && (int) $atual !== (int) $pv) {
                        throw new InvalidArgumentException(
                            'O boletim tem uma fonte diferente em períodos que foram para o mesmo destino. Escolha um período novo para cada um.'
                        );
                    }
                    if ($atual === null || (int) $atual === 0) {
                        $novoMapa[$dest] = $pv;
                    }
                }
                $out[$k] = $novoMapa;
                continue;
            }
            if ($chave === 'bimestre') {
                $n = $this->numeroDeValor($v);
                $out[$k] = ($n >= 1 && isset($mapa[$n])) ? $mapa[$n] : $v;
                continue;
            }
            $out[$k] = is_array($v) ? $this->remapearNoJson($v, $mapa) : $v;
        }
        return $out;
    }

    /**
     * @param array<int,int> $mapa
     */
    private function remapearRegrasAcademicas(int $ano, string $tipoAtual, string $tipoNovo, array $mapa): void
    {
        if (!$this->db->tableExists('regras_academicas') || !$this->colunaExiste('regras_academicas', 'periodo_tipo')) {
            return;
        }
        if (!$this->valorCabeNaColuna('regras_academicas', 'periodo_tipo', $tipoNovo)) {
            return;
        }
        $this->exigirTipoAceito('regras_academicas', 'regras acadêmicas', $ano, $tipoAtual, $tipoNovo);
        foreach ($mapa as $origem => $destino) {
            $this->executarAtualizacao(
                'UPDATE regras_academicas
                 SET periodo_tipo = :novo, periodo_numero = :destino
                 WHERE ano_letivo = :ano AND periodo_tipo = :velho AND periodo_numero = :origem',
                [
                    'novo' => $tipoNovo,
                    'destino' => (int) $destino,
                    'ano' => $ano,
                    'velho' => $tipoAtual,
                    'origem' => (int) $origem,
                ]
            );
        }
        $this->executarAtualizacao(
            'UPDATE regras_academicas
             SET periodo_tipo = :novo
             WHERE ano_letivo = :ano AND periodo_tipo = :velho
               AND (periodo_numero IS NULL OR periodo_numero = 0)',
            ['novo' => $tipoNovo, 'ano' => $ano, 'velho' => $tipoAtual]
        );
    }

    /**
     * @param array<int,int> $mapa
     */
    private function remapearResultadoAcademico(int $ano, string $tipoAtual, string $tipoNovo, array $mapa): void
    {
        if (!$this->db->tableExists('resultado_academico') || !$this->colunaExiste('resultado_academico', 'periodo_tipo')) {
            return;
        }
        if (!$this->valorCabeNaColuna('resultado_academico', 'periodo_tipo', $tipoNovo)) {
            return;
        }
        $snapshots = [];
        if ($this->colunaExiste('resultado_academico', 'snapshot_json')) {
            $snapshots = $this->db->fetchAll(
                'SELECT id, periodo_numero, snapshot_json
                 FROM resultado_academico
                 WHERE ano_letivo = :ano AND periodo_tipo = :velho
                   AND snapshot_json IS NOT NULL AND snapshot_json != \'\'',
                ['ano' => $ano, 'velho' => $tipoAtual]
            );
            $snapshots = is_array($snapshots) ? $snapshots : [];
        }
        foreach ($mapa as $origem => $destino) {
            $this->executarAtualizacao(
                'UPDATE resultado_academico
                 SET periodo_tipo = :novo, periodo_numero = :destino
                 WHERE ano_letivo = :ano AND periodo_tipo = :velho AND periodo_numero = :origem',
                [
                    'novo' => $tipoNovo,
                    'destino' => (int) $destino,
                    'ano' => $ano,
                    'velho' => $tipoAtual,
                    'origem' => (int) $origem,
                ]
            );
        }
        $this->executarAtualizacao(
            'UPDATE resultado_academico
             SET periodo_tipo = :novo
             WHERE ano_letivo = :ano AND periodo_tipo = :velho AND periodo_numero = 0',
            ['novo' => $tipoNovo, 'ano' => $ano, 'velho' => $tipoAtual]
        );
        foreach ($snapshots as $row) {
            $origem = (int) ($row['periodo_numero'] ?? 0);
            $destino = $origem >= 1 ? (int) ($mapa[$origem] ?? $origem) : 0;
            if ($origem >= 1 && !isset($mapa[$origem])) {
                continue;
            }
            $novo = $this->snapshotReclassificado((string) ($row['snapshot_json'] ?? ''), $tipoNovo, $destino);
            if ($novo === null) {
                continue;
            }
            $this->executarAtualizacao(
                'UPDATE resultado_academico SET snapshot_json = :json WHERE id = :id',
                ['json' => $novo, 'id' => (int) ($row['id'] ?? 0)]
            );
        }
    }

    private function snapshotReclassificado(string $json, string $tipoNovo, int $destino): ?string
    {
        $node = json_decode($json, true);
        if (!is_array($node) || !isset($node['periodo']) || !is_array($node['periodo'])) {
            return null;
        }
        $node['periodo']['tipo'] = $tipoNovo;
        $node['periodo']['numero'] = $destino;
        $node['periodo']['label'] = $destino <= 0
            ? 'Ano letivo'
            : (string) (PeriodoLetivo::rotulosDoTipo($tipoNovo)[$destino] ?? ($destino . 'º período'));
        $encoded = json_encode($node, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || $encoded === $json) {
            return null;
        }
        return $encoded;
    }

    /**
     * @param array<int,int> $mapa
     */
    private function remapearFechamento(int $ano, string $tipoAtual, string $tipoNovo, array $mapa): void
    {
        if (!$this->db->tableExists('fechamento_periodo') || !$this->colunaExiste('fechamento_periodo', 'periodo_tipo')) {
            return;
        }
        $rows = $this->db->fetchAll(
            'SELECT id, turma_id, periodo_numero, vigente
             FROM fechamento_periodo
             WHERE ano_letivo = :ano AND periodo_tipo = :tipo AND periodo_numero >= 1',
            ['ano' => $ano, 'tipo' => $tipoAtual]
        );
        $rows = is_array($rows) ? $rows : [];
        if ($rows === []) {
            return;
        }
        $this->executarAtualizacao(
            'UPDATE fechamento_periodo SET vigente_chave = NULL
             WHERE ano_letivo = :ano AND periodo_tipo = :tipo AND periodo_numero >= 1',
            ['ano' => $ano, 'tipo' => $tipoAtual]
        );
        foreach ($rows as $row) {
            $origem = (int) ($row['periodo_numero'] ?? 0);
            if ($origem < 1 || !isset($mapa[$origem])) {
                throw new InvalidArgumentException('Indique para onde vai o período ' . $origem . ' dos fechamentos deste ano.');
            }
            $destino = (int) $mapa[$origem];
            $vigente = (int) ($row['vigente'] ?? 0) === 1;
            $chave = $vigente ? ((int) $row['turma_id'] . ':' . $ano . ':' . $tipoNovo . ':' . $destino) : null;
            $this->executarAtualizacao(
                'UPDATE fechamento_periodo
                 SET periodo_tipo = :tipo, periodo_numero = :num, periodo_ref = :ref, vigente_chave = :chave
                 WHERE id = :id',
                [
                    'tipo' => $tipoNovo,
                    'num' => $destino,
                    'ref' => $this->referenciaPeriodo($ano, $tipoNovo, $destino),
                    'chave' => $chave,
                    'id' => (int) ($row['id'] ?? 0),
                ]
            );
        }
    }

    private function referenciaPeriodo(int $ano, string $tipo, int $numero): string
    {
        $prefixo = match (PeriodoLetivo::normalizarTipo($tipo)) {
            'trimestre' => 'T',
            'semestre' => 'S',
            'etapa_unica' => 'E',
            default => 'B',
        };
        if ($numero >= 1) {
            return $ano . '-' . $prefixo . $numero;
        }
        return $ano . '-ANO';
    }

    private function exigirTipoAceito(string $tabela, string $rotulo, int $ano, string $tipoAtual, string $tipoNovo): void
    {
        if (!$this->identificadorSql($tabela) || !$this->db->tableExists($tabela) || !$this->colunaExiste($tabela, 'periodo_tipo')) {
            return;
        }
        if ($this->valorCabeNaColuna($tabela, 'periodo_tipo', $tipoNovo)) {
            return;
        }
        if ($this->temLinhasDoTipo($tabela, $ano, $tipoAtual)) {
            throw new InvalidArgumentException(
                'Não é possível usar esta divisão porque já existem ' . $rotulo . ' gravados. Escolha bimestre, trimestre ou semestre.'
            );
        }
    }

    private function temLinhasDoTipo(string $tabela, int $ano, string $tipo): bool
    {
        if (!$this->identificadorSql($tabela) || !$this->db->tableExists($tabela) || !$this->colunaExiste($tabela, 'periodo_tipo') || !$this->colunaExiste($tabela, 'ano_letivo')) {
            return false;
        }
        $row = $this->db->fetch(
            "SELECT 1 AS ok FROM `{$tabela}` WHERE ano_letivo = :ano AND periodo_tipo = :tipo LIMIT 1",
            ['ano' => $ano, 'tipo' => $tipo]
        );
        return is_array($row) && !empty($row['ok']);
    }

    private function valorCabeNaColuna(string $tabela, string $coluna, string $valor): bool
    {
        if (!$this->identificadorSql($tabela) || !$this->identificadorSql($coluna)) {
            return false;
        }
        $row = $this->db->fetch("SHOW COLUMNS FROM `{$tabela}` LIKE '{$coluna}'");
        $type = strtolower((string) ($row['Type'] ?? ''));
        if ($type === '' || !str_starts_with($type, 'enum(')) {
            return true;
        }
        preg_match_all("/'([^']*)'/", $type, $m);
        return in_array($valor, $m[1] ?? [], true);
    }

    /**
     * @param array<int,int> $mapa
     * @return array<int,int>
     */
    private function paresQueMudamNumero(array $mapa): array
    {
        $pares = [];
        foreach ($mapa as $de => $para) {
            $de = (int) $de;
            $para = (int) $para;
            if ($de >= 1 && $para >= 1 && $de !== $para) {
                $pares[$de] = $para;
            }
        }
        return $pares;
    }

    /**
     * @param array<string,mixed> $params
     */
    private function executarAtualizacao(string $sql, array $params): void
    {
        try {
            $this->db->update($sql, $params);
        } catch (Throwable $e) {
            if ($this->ehConflitoUnico($e)) {
                throw new InvalidArgumentException(
                    'Não dá para juntar esses períodos: já existe o mesmo cadastro nos dois (por exemplo, a mesma turma ou o mesmo aluno). Escolha um período de destino diferente para cada um.'
                );
            }
            throw $e;
        }
    }

    private function ehConflitoUnico(Throwable $e): bool
    {
        $msg = $e->getMessage();
        return str_contains($msg, '1062') || stripos($msg, 'Duplicate') !== false;
    }

    private function identificadorSql(string $nome): bool
    {
        return preg_match('/^[a-zA-Z0-9_]+$/', $nome) === 1;
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
