<?php

namespace App\Modulos\RegrasAcademicas\Models;

use App\Core\Database;
use App\Core\PeriodoLetivo;
use App\Modulos\EventosAvaliativos\Models\EventoAvaliativo;

class RecuperacaoConfig
{
    public const CODIGO_FINAL = 90;

    public const CODIGO_PAR_BASE = 80;

    /** Recuperação de um ciclo: 61 = 1º período, 62 = 2º, e assim por diante. */
    public const CODIGO_CICLO_BASE = 60;

    public const QUANDOS = [
        'bimestre' => 'A cada Período Letivo',
        'par' => 'A cada dois ciclos',
        'final' => 'Rec Final',
    ];

    public const EFEITOS = [
        'maior_nota' => 'Substitui se for maior',
        'substitui_menor' => 'Substitui a menor nota',
        'composicao' => '(Média + REC) / 2',
        'soma' => 'Soma na pontuação',
        'formula' => 'Fórmula própria',
    ];

    public const APLICA_EM = [
        'par' => 'Os dois ciclos do par',
        'cada' => 'Cada ciclo do par',
        'primeiro' => 'Só o primeiro ciclo',
        'ultimo' => 'Só o último ciclo',
    ];

    /**
     * Rótulos da recuperação conforme a divisão cadastrada no ano letivo.
     *
     * @return array{
     *   quandos:array<string,string>,
     *   rotulos:array<string,string>,
     *   onde:array<string,string>,
     *   aplica:array<string,string>,
     *   aplica_ajuda:string,
     *   ajuda:string,
     *   erro_aplica:string
     * }
     */
    public static function textosDoAno(int $ano): array
    {
        $info = PeriodoLetivo::doAno($ano > 0 ? $ano : (int) date('Y'));
        $tipo = (string) ($info['tipo'] ?? 'bimestre');
        $bimestral = $tipo === 'bimestre';
        $um = mb_strtolower(PeriodoLetivo::rotuloCampo($tipo));
        $varios = mb_strtolower(PeriodoLetivo::rotuloCampoPlural($tipo));
        $rotulos = [
            'bimestre' => 'A cada Período Letivo',
            'par' => 'A cada dois ciclos',
            'final' => 'Rec Final',
        ];
        $quandos = [
            'bimestre' => $rotulos['bimestre'],
            'final' => $rotulos['final'],
        ];
        if ($bimestral) {
            $quandos = [
                'bimestre' => $rotulos['bimestre'],
                'par' => $rotulos['par'],
                'final' => $rotulos['final'],
            ];
        }
        $frases = ['A cada Período Letivo: o período ganha o card da avaliação e, ao lado, o card da recuperação.'];
        if ($bimestral) {
            $frases[] = 'A cada dois ciclos: um card junta o 1º com o 2º e outro junta o 3º com o 4º.';
        }
        $frases[] = 'Rec Final: uma prova só, no fim do ano, com card próprio em Eventos Avaliativos.';

        return [
            'quandos' => $quandos,
            'rotulos' => $rotulos,
            'onde' => [
                'bimestre' => 'Card ao lado de cada período',
                'par' => 'Um card a cada dois períodos',
                'final' => 'Card da Rec Final',
            ],
            'aplica' => [
                'par' => 'Os dois ' . $varios,
                'cada' => 'Cada ' . $um,
                'primeiro' => 'Só o primeiro ' . $um,
                'ultimo' => 'Só o último ' . $um,
            ],
            'aplica_ajuda' => 'Os dois ' . $varios . ': a mesma nota vale para o par. Cada ' . $um . ': entra separado em cada um. Só o primeiro: substitui o período que abre o par. Só o último: substitui o que fecha.',
            'ajuda' => implode(' ', $frases),
            'erro_aplica' => 'Escolha se a nota substitui os dois ' . $varios . ', cada ' . $um . ', só o primeiro ou só o último.',
        ];
    }

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tabelaPronta(): bool
    {
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1',
                ['t' => 'recuperacoes']
            );
            return !empty($row['ok']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(int $ano): array
    {
        if ($ano <= 0 || !$this->tabelaPronta()) {
            return [];
        }
        return $this->mapear($this->db->fetchAll(
            'SELECT ' . $this->colunas() . '
               FROM recuperacoes
              WHERE ano_letivo = :ano AND deleted_at IS NULL
              ORDER BY ordem ASC, id ASC',
            ['ano' => $ano]
        ) ?: []);
    }

    /**
     * Recuperações de uma regra. Sem o id da regra, a lista fica vazia.
     *
     * @return list<array<string,mixed>>
     */
    public function listarDaRegra(int $regraId): array
    {
        if ($regraId <= 0 || !$this->tabelaPronta()) {
            return [];
        }
        if (!$this->colunaRegraPronta()) {
            $regra = (new RegraAcademica())->findById($regraId);
            $ano = (int) ($regra['ano_letivo'] ?? 0);
            if ($ano < 2000 || $ano > 2100 || $this->regrasNoAno($ano) !== 1) {
                return [];
            }
            return $this->listar($ano);
        }
        return $this->mapear($this->db->fetchAll(
            'SELECT ' . $this->colunas() . '
               FROM recuperacoes
              WHERE regra_id = :regra AND deleted_at IS NULL
              ORDER BY ordem ASC, id ASC',
            ['regra' => $regraId]
        ) ?: []);
    }

    public function alinharAno(int $regraId, int $ano): void
    {
        if ($regraId <= 0 || $ano < 2000 || $ano > 2100 || !$this->colunaRegraPronta()) {
            return;
        }
        $this->db->query(
            'UPDATE recuperacoes
                SET ano_letivo = :ano
              WHERE regra_id = :regra AND deleted_at IS NULL',
            ['ano' => $ano, 'regra' => $regraId]
        );
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0 || !$this->tabelaPronta()) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT ' . $this->colunas() . '
               FROM recuperacoes
              WHERE id = :id AND deleted_at IS NULL
              LIMIT 1',
            ['id' => $id]
        );
        return $row ? $this->completar($row) : null;
    }

    public function opcoesProntas(): bool
    {
        return $this->temColuna('aplica_em');
    }

    public function colunaRegraPronta(): bool
    {
        return $this->temColuna('regra_id');
    }

    /**
     * @param array<string,mixed> $dados
     */
    public function criar(array $dados): int
    {
        $regraId = (int) ($dados['regra_id'] ?? 0);
        $porRegra = $this->colunaRegraPronta() && $regraId > 0;
        if ($porRegra) {
            $ordem = $this->db->fetch(
                'SELECT COALESCE(MAX(ordem), 0) + 1 AS proxima
                   FROM recuperacoes
                  WHERE regra_id = :regra AND deleted_at IS NULL',
                ['regra' => $regraId]
            );
        } else {
            $ordem = $this->db->fetch(
                'SELECT COALESCE(MAX(ordem), 0) + 1 AS proxima
                   FROM recuperacoes
                  WHERE ano_letivo = :ano AND deleted_at IS NULL',
                ['ano' => (int) $dados['ano_letivo']]
            );
        }
        $params = [
            'ano_letivo' => (int) $dados['ano_letivo'],
            'nome' => (string) $dados['nome'],
            'quando' => (string) $dados['quando'],
            'nota_minima' => $dados['nota_minima'],
            'nota_maxima' => $dados['nota_maxima'],
            'efeito' => (string) $dados['efeito'],
            'ordem' => (int) ($ordem['proxima'] ?? 1),
        ];
        if ($porRegra) {
            $params['regra_id'] = $regraId;
        }
        $colunas = 'ano_letivo, nome, quando, nota_minima, nota_maxima, efeito, ordem';
        $valores = ':ano_letivo, :nome, :quando, :nota_minima, :nota_maxima, :efeito, :ordem';
        if ($porRegra) {
            $colunas = 'ano_letivo, regra_id, nome, quando, nota_minima, nota_maxima, efeito, ordem';
            $valores = ':ano_letivo, :regra_id, :nome, :quando, :nota_minima, :nota_maxima, :efeito, :ordem';
        }
        if ($this->opcoesProntas()) {
            $params['aplica_em'] = $dados['aplica_em'];
            $params['formula'] = $dados['formula'];
            $params['permite_aumentar'] = (int) $dados['permite_aumentar'];
            $colunas .= ', aplica_em, formula, permite_aumentar';
            $valores .= ', :aplica_em, :formula, :permite_aumentar';
        }
        return (int) $this->db->insert(
            'INSERT INTO recuperacoes (' . $colunas . ') VALUES (' . $valores . ')',
            $params
        );
    }

    /**
     * @param array<string,mixed> $dados
     */
    public function atualizar(int $id, array $dados): void
    {
        $params = [
            'id' => $id,
            'nome' => (string) $dados['nome'],
            'quando' => (string) $dados['quando'],
            'nota_minima' => $dados['nota_minima'],
            'nota_maxima' => $dados['nota_maxima'],
            'efeito' => (string) $dados['efeito'],
        ];
        if (!$this->opcoesProntas()) {
            $this->db->query(
                'UPDATE recuperacoes
                    SET nome = :nome,
                        quando = :quando,
                        nota_minima = :nota_minima,
                        nota_maxima = :nota_maxima,
                        efeito = :efeito
                  WHERE id = :id AND deleted_at IS NULL',
                $params
            );
            return;
        }
        $params['aplica_em'] = $dados['aplica_em'];
        $params['formula'] = $dados['formula'];
        $params['permite_aumentar'] = (int) $dados['permite_aumentar'];
        $this->db->query(
            'UPDATE recuperacoes
                SET nome = :nome,
                    quando = :quando,
                    nota_minima = :nota_minima,
                    nota_maxima = :nota_maxima,
                    efeito = :efeito,
                    aplica_em = :aplica_em,
                    formula = :formula,
                    permite_aumentar = :permite_aumentar
              WHERE id = :id AND deleted_at IS NULL',
            $params
        );
    }

    public function excluir(int $id, int $regraId = 0): void
    {
        if ($regraId > 0 && $this->colunaRegraPronta()) {
            $this->db->query(
                'UPDATE recuperacoes
                    SET deleted_at = NOW()
                  WHERE id = :id AND regra_id = :regra AND deleted_at IS NULL',
                ['id' => $id, 'regra' => $regraId]
            );
            return;
        }
        $this->db->query(
            'UPDATE recuperacoes SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /**
     * Cards extras da tela de eventos. Sem a tabela, mantém o card da final.
     *
     * @param array<int,int> $contagens
     * @return list<array{codigo:int,rotulo:string,grupo:string}>
     */
    public function slotsParaTela(int $ano, array $contagens = [], int $regraId = 0): array
    {
        if (!$this->tabelaPronta()) {
            return [[
                'codigo' => self::CODIGO_FINAL,
                'rotulo' => EventoAvaliativo::ROTULO_RECUPERACAO_FINAL,
                'grupo' => 'Ano',
            ]];
        }

        $linhas = $regraId > 0 ? $this->listarDaRegra($regraId) : $this->listar($ano);
        $slots = $this->slotsDasLinhas($ano, $linhas);
        if ($regraId > 0) {
            return $slots;
        }
        $codigos = [];
        foreach ($slots as $slot) {
            $codigos[(int) $slot['codigo']] = true;
        }
        foreach ($contagens as $codigo => $total) {
            $codigo = (int) $codigo;
            if ($codigo < self::CODIGO_CICLO_BASE || (int) $total <= 0 || isset($codigos[$codigo])) {
                continue;
            }
            $slots[] = [
                'codigo' => $codigo,
                'rotulo' => $codigo === self::CODIGO_FINAL ? EventoAvaliativo::ROTULO_RECUPERACAO_FINAL : 'Recuperação',
                'grupo' => 'Ano',
            ];
            $codigos[$codigo] = true;
        }
        return $slots;
    }

    public function codigoAceito(int $ano, int $codigo, int $regraId = 0): bool
    {
        if ($codigo < self::CODIGO_CICLO_BASE) {
            return false;
        }
        if (!$this->tabelaPronta()) {
            return $codigo === self::CODIGO_FINAL;
        }
        foreach ($this->slotsParaTela($ano, [], $regraId) as $slot) {
            if ((int) $slot['codigo'] === $codigo) {
                return true;
            }
        }
        if ($regraId > 0) {
            return false;
        }
        try {
            $totais = (new EventoAvaliativo())->contarPorAno($ano);
        } catch (\Throwable $e) {
            return false;
        }
        return (int) ($totais[$codigo] ?? 0) > 0;
    }

    /**
     * @return list<array{codigo:int,rotulo:string,grupo:string}>
     */
    public function opcoesFormulario(int $ano, int $selecionado = 0, int $regraId = 0): array
    {
        $opcoes = $this->slotsParaTela($ano, [], $regraId);
        $codigos = [];
        foreach ($opcoes as $opcao) {
            $codigos[(int) $opcao['codigo']] = true;
        }
        if ($selecionado >= self::CODIGO_CICLO_BASE && !isset($codigos[$selecionado])) {
            $rotulo = $this->rotuloDoCodigo($ano, $selecionado, $regraId);
            if ($rotulo !== null) {
                $opcoes[] = [
                    'codigo' => $selecionado,
                    'rotulo' => $rotulo,
                    'grupo' => 'Ano',
                ];
            }
        }
        return $opcoes;
    }

    public function rotuloDoCodigo(int $ano, int $codigo, int $regraId = 0): ?string
    {
        if ($codigo < self::CODIGO_CICLO_BASE) {
            return null;
        }
        foreach ($this->slotsParaTela($ano, [], $regraId) as $slot) {
            if ((int) $slot['codigo'] === $codigo) {
                return (string) $slot['rotulo'];
            }
        }
        if ($regraId > 0) {
            return null;
        }
        if ($codigo === self::CODIGO_FINAL) {
            return EventoAvaliativo::ROTULO_RECUPERACAO_FINAL;
        }
        if ($codigo > self::CODIGO_PAR_BASE || $codigo > self::CODIGO_CICLO_BASE) {
            return 'Recuperação';
        }
        return null;
    }

    /**
     * @param list<array<string,mixed>> $linhas
     * @return list<array{codigo:int,rotulo:string,grupo:string}>
     */
    private function slotsDasLinhas(int $ano, array $linhas): array
    {
        $info = PeriodoLetivo::doAno($ano);
        $quantidade = max(1, (int) ($info['quantidade'] ?? 4));
        $rotulos = is_array($info['rotulos'] ?? null) ? $info['rotulos'] : [];
        $slots = [];
        $vistos = [];
        foreach ($linhas as $linha) {
            $quando = (string) ($linha['quando'] ?? '');
            $nome = trim((string) ($linha['nome'] ?? ''));
            if ($nome === '') {
                $nome = 'Recuperação';
            }
            if ($quando === 'final') {
                $this->guardarSlot($slots, $vistos, self::CODIGO_FINAL, $nome, 'Ano');
                continue;
            }
            if ($quando === 'bimestre') {
                for ($i = 1; $i <= $quantidade; $i++) {
                    $rotuloPeriodo = (string) ($rotulos[$i] ?? ($i . 'º'));
                    $this->guardarSlot(
                        $slots,
                        $vistos,
                        self::CODIGO_CICLO_BASE + $i,
                        $nome . ' · ' . $rotuloPeriodo,
                        'Ciclo'
                    );
                }
                continue;
            }
            if ($quando !== 'par') {
                continue;
            }
            $pares = (int) ceil($quantidade / 2);
            for ($i = 1; $i <= $pares; $i++) {
                $inicio = (($i - 1) * 2) + 1;
                $fim = min($quantidade, $inicio + 1);
                $rotuloInicio = (string) ($rotulos[$inicio] ?? ($inicio . 'º'));
                $rotuloFim = (string) ($rotulos[$fim] ?? ($fim . 'º'));
                $rotulo = $inicio === $fim ? $nome . ' · ' . $rotuloInicio : $nome . ' · ' . $rotuloInicio . ' e ' . $rotuloFim;
                $this->guardarSlot($slots, $vistos, self::CODIGO_PAR_BASE + $i, $rotulo, 'Etapas');
            }
        }
        return $slots;
    }

    /**
     * @param list<array{codigo:int,rotulo:string,grupo:string}> $slots
     * @param array<int,bool> $vistos
     */
    private function guardarSlot(array &$slots, array &$vistos, int $codigo, string $rotulo, string $grupo): void
    {
        if (isset($vistos[$codigo])) {
            return;
        }
        $vistos[$codigo] = true;
        $slots[] = [
            'codigo' => $codigo,
            'rotulo' => $rotulo,
            'grupo' => $grupo,
        ];
    }

    private function colunas(): string
    {
        $colunas = 'id, ano_letivo, nome, quando, nota_minima, nota_maxima, efeito, ordem';
        if ($this->colunaRegraPronta()) {
            $colunas .= ', regra_id';
        }
        if ($this->opcoesProntas()) {
            $colunas .= ', aplica_em, formula, permite_aumentar';
        }
        return $colunas;
    }

    public function regrasNoAno(int $ano): int
    {
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS total
               FROM regras_academicas
              WHERE ano_letivo = :ano',
            ['ano' => $ano]
        );
        return (int) ($row['total'] ?? 0);
    }

    private function temColuna(string $coluna): bool
    {
        if (!$this->tabelaPronta()) {
            return false;
        }
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
                 LIMIT 1',
                ['t' => 'recuperacoes', 'c' => $coluna]
            );
            return !empty($row['ok']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function mapear(array $rows): array
    {
        $saida = [];
        foreach ($rows as $row) {
            $saida[] = $this->completar($row);
        }
        return $saida;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function completar(array $row): array
    {
        $aplica = (string) ($row['aplica_em'] ?? '');
        $row['aplica_em'] = isset(self::APLICA_EM[$aplica]) ? $aplica : null;
        $formula = trim((string) ($row['formula'] ?? ''));
        $row['formula'] = $formula !== '' ? $formula : null;
        $row['permite_aumentar'] = (int) ($row['permite_aumentar'] ?? 0) === 1 ? 1 : 0;
        return $row;
    }
}
