<?php

namespace App\Modulos\ModelosDocumentos\Services;

use App\Core\Database;

/**
 * Monta o quadro do boletim no formato do print Santa Maria
 * (PP/PU/CH/PE/MU/FAL + semestres + final), a partir do demonstrativo.
 */
class QuadroBoletimSantaMariaService
{
    public const CODIGO_MODELO = 'boletim_santa_maria_2024';

    /** @var list<string> */
    private const AREAS_SOMA = ['História', 'Matemática', 'Física', 'Química', 'Biologia'];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function codigoEhSantaMaria(string $codigo): bool
    {
        $codigo = strtolower(trim($codigo));
        return $codigo === self::CODIGO_MODELO || str_contains($codigo, 'santa_maria');
    }

    /**
     * @return array{html:string,linhas:int}
     */
    public function montarHtml(int $alunoId, int $anoLetivo): array
    {
        $linhas = $this->linhasDoAluno($alunoId, $anoLetivo);
        return [
            'html' => $this->renderTabela($linhas),
            'linhas' => count($linhas),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function linhasDoAluno(int $alunoId, int $anoLetivo): array
    {
        if ($alunoId <= 0 || $anoLetivo < 2000) {
            return [];
        }

        // Prefere visão de impressão (exibir_em=boletim) quando já houver resultados gerados.
        $rows = $this->buscarResultadosQuadroAluno($alunoId, $anoLetivo, 'boletim');
        if ($rows === []) {
            $rows = $this->buscarResultadosQuadroAluno($alunoId, $anoLetivo, 'notas');
        }

        /** @var array<int, array{nome:string,pai_id:?int,bims:array<int,array{pp:?float,pu:?float,pe:?float,mu:?float,fal:int,rec:?float}>}> */
        $porMateria = [];
        $vistos = [];
        foreach ($rows as $row) {
            $mid = (int) ($row['materia_id'] ?? 0);
            $bim = (int) ($row['bimestre'] ?? 0);
            if ($mid <= 0 || $bim < 1 || $bim > 4) {
                continue;
            }
            $chave = $mid . ':' . $bim;
            if (isset($vistos[$chave])) {
                continue;
            }
            $vistos[$chave] = true;

            $notas = json_decode((string) ($row['notas_json'] ?? ''), true);
            if (!is_array($notas)) {
                $notas = [];
            }
            $pp = $this->numOuNull($notas['PP'] ?? null);
            $pu = $this->numOuNull($notas['PU'] ?? null);
            // PE ausente = traço; PE 0,00 só se o professor lançou zero de propósito.
            $pe = $this->numOuNull($notas['PE'] ?? null);
            $mu = $this->numOuNull($row['media_final'] ?? null);
            if ($mu === null) {
                $mu = $this->calcularMu($pp, $pu, $pe);
            }
            $rec = $this->numOuNull($notas['REC'] ?? null);

            if (!isset($porMateria[$mid])) {
                $porMateria[$mid] = [
                    'nome' => trim((string) ($row['materia_nome'] ?? 'Componente')),
                    'pai_id' => null,
                    'bims' => [],
                ];
            }
            $porMateria[$mid]['bims'][$bim] = [
                'pp' => $pp,
                'pu' => $pu,
                'pe' => $pe,
                'mu' => $mu,
                'fal' => 0,
                'rec' => $rec,
            ];
        }

        if ($porMateria === []) {
            return [];
        }

        $ids = array_keys($porMateria);
        $ph = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $k = 'm' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        $meta = $this->db->fetchAll(
            'SELECT id, nome, pai_id FROM materias WHERE id IN (' . implode(',', $ph) . ')',
            $params
        ) ?: [];
        $paiPorId = [];
        foreach ($meta as $m) {
            $id = (int) ($m['id'] ?? 0);
            if ($id <= 0 || !isset($porMateria[$id])) {
                continue;
            }
            $porMateria[$id]['nome'] = trim((string) ($m['nome'] ?? $porMateria[$id]['nome']));
            $pai = isset($m['pai_id']) && $m['pai_id'] !== null ? (int) $m['pai_id'] : null;
            $porMateria[$id]['pai_id'] = $pai > 0 ? $pai : null;
            if ($pai > 0) {
                $paiPorId[$pai] = true;
            }
        }

        // Faltas da ficha oficial (boletim_fichas / vida escolar).
        try {
            $faltas = $this->db->fetchAll(
                "SELECT l.materia_id, c.periodo_numero, c.faltas
                   FROM boletim_ficha_celulas c
                   INNER JOIN boletim_ficha_linhas l ON l.id = c.linha_id
                   INNER JOIN boletim_fichas f ON f.id = l.ficha_id
                  WHERE f.aluno_id = :aluno AND f.ano_letivo = :ano
                    AND c.periodo_numero BETWEEN 1 AND 4",
                ['aluno' => $alunoId, 'ano' => $anoLetivo]
            ) ?: [];
            foreach ($faltas as $f) {
                $mid = (int) ($f['materia_id'] ?? 0);
                $bim = (int) ($f['periodo_numero'] ?? 0);
                if (!isset($porMateria[$mid]['bims'][$bim])) {
                    continue;
                }
                $porMateria[$mid]['bims'][$bim]['fal'] = (int) ($f['faltas'] ?? 0);
            }
        } catch (\Throwable $e) {
            // schema legado / tabela ausente neste tenant
        }

        // Áreas-mãe: soma das MUs dos filhos.
        $paisNomes = [];
        foreach ($porMateria as $mid => $dados) {
            $paiId = $dados['pai_id'];
            if ($paiId === null || $paiId <= 0) {
                continue;
            }
            if (!isset($paisNomes[$paiId])) {
                $paiRow = $this->db->fetch('SELECT id, nome FROM materias WHERE id = :id LIMIT 1', ['id' => $paiId]);
                $paisNomes[$paiId] = trim((string) ($paiRow['nome'] ?? 'Área'));
            }
        }

        $ordemPreferida = [
            'Língua Portuguesa', 'Redação', 'Literatura', 'Arte',
            'História', 'História 1', 'História 2',
            'Geografia', 'Sociologia', 'Filosofia',
            'Matemática', 'Matemática 1', 'Matemática 2',
            'Física', 'Física 1', 'Física 2',
            'Química', 'Química 1', 'Química 2',
            'Biologia', 'Biologia 1', 'Biologia 2',
            'Língua Inglesa', 'Educação Física',
        ];

        $saida = [];
        $ja = [];

        $pushLinha = static function (string $nome, array $bims, bool $area = false, bool $indent = false) use (&$saida, &$ja): void {
            $chave = ($indent ? '..' : '') . $nome;
            if (isset($ja[$chave])) {
                return;
            }
            $ja[$chave] = true;
            $saida[] = [
                'nome' => $indent ? ('..' . $nome) : $nome,
                'area' => $area,
                'bims' => $bims,
            ];
        };

        // Pré-monta áreas.
        $filhosPorPai = [];
        foreach ($porMateria as $mid => $dados) {
            $paiId = $dados['pai_id'];
            if ($paiId !== null && $paiId > 0) {
                $filhosPorPai[$paiId][$mid] = $dados;
            }
        }
        $areasMontadas = [];
        foreach ($filhosPorPai as $paiId => $filhos) {
            $nomePai = $paisNomes[$paiId] ?? 'Área';
            $bims = [];
            foreach ([1, 2, 3, 4] as $b) {
                $soma = 0.0;
                $tem = false;
                $fal = 0;
                foreach ($filhos as $filho) {
                    $cel = $filho['bims'][$b] ?? null;
                    if (!is_array($cel)) {
                        continue;
                    }
                    if ($cel['mu'] !== null) {
                        $soma += (float) $cel['mu'];
                        $tem = true;
                    }
                    $fal += (int) ($cel['fal'] ?? 0);
                }
                $bims[$b] = [
                    'pp' => null,
                    'pu' => null,
                    'pe' => null,
                    'mu' => $tem ? round($soma, 1) : null,
                    'fal' => $fal,
                    'rec' => null,
                ];
            }
            $areasMontadas[$nomePai] = [
                'bims' => $bims,
                'filhos' => $filhos,
            ];
        }

        foreach ($ordemPreferida as $rotulo) {
            if (isset($areasMontadas[$rotulo])) {
                $pushLinha($rotulo, $areasMontadas[$rotulo]['bims'], true, false);
                foreach ($areasMontadas[$rotulo]['filhos'] as $filho) {
                    $pushLinha((string) $filho['nome'], $filho['bims'], false, true);
                }
                unset($areasMontadas[$rotulo]);
                continue;
            }
            foreach ($porMateria as $mid => $dados) {
                if (($dados['pai_id'] ?? null) !== null) {
                    continue;
                }
                if ($dados['nome'] !== $rotulo) {
                    continue;
                }
                $pushLinha($dados['nome'], $dados['bims'], in_array($dados['nome'], self::AREAS_SOMA, true), false);
                unset($porMateria[$mid]);
            }
        }

        foreach ($areasMontadas as $nomePai => $pack) {
            $pushLinha($nomePai, $pack['bims'], true, false);
            foreach ($pack['filhos'] as $filho) {
                $pushLinha((string) $filho['nome'], $filho['bims'], false, true);
            }
        }
        foreach ($porMateria as $dados) {
            if (($dados['pai_id'] ?? null) !== null) {
                continue;
            }
            $pushLinha($dados['nome'], $dados['bims'], false, false);
        }

        return $saida;
    }

    /**
     * @param list<array<string,mixed>> $linhas
     */
    private function renderTabela(array $linhas): string
    {
        $thBim = static function (string $rotulo): string {
            return '<th colspan="6" style="border:1px solid #111;background:#6b7280;color:#fff;font-size:7pt;padding:2px;">'
                . htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') . '</th>';
        };
        $thSem = static function (string $rotulo): string {
            return '<th colspan="2" style="border:1px solid #111;background:#6b7280;color:#fff;font-size:7pt;padding:2px;">'
                . htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') . '</th>';
        };
        $thSub = static function (string $t): string {
            return '<th style="border:1px solid #111;background:#9ca3af;color:#111;font-size:6.5pt;padding:1px 2px;">'
                . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</th>';
        };

        $html = '<table class="sm24-boletim dados" width="100%" style="width:100%;border-collapse:collapse;table-layout:fixed;font-size:7pt;">'
            . '<thead><tr>'
            . '<th rowspan="2" style="border:1px solid #111;background:#9ca3af;color:#111;font-size:7pt;width:14%;">COMPONENTES CURRICULARES</th>'
            . $thBim('I BIMESTRE') . $thBim('II BIMESTRE') . $thSem('1º SEMESTRE')
            . $thBim('III BIMESTRE') . $thBim('IV BIMESTRE') . $thSem('2º SEMESTRE')
            . '<th colspan="4" style="border:1px solid #111;background:#6b7280;color:#fff;font-size:7pt;">RESULTADO FINAL</th>'
            . '</tr><tr>';
        foreach ([1, 2] as $_) {
            foreach (['PP', 'PU', 'CH', 'PE', 'MU', 'FAL'] as $c) {
                $html .= $thSub($c);
            }
        }
        $html .= $thSub('REC') . $thSub('MS');
        foreach ([3, 4] as $_) {
            foreach (['PP', 'PU', 'CH', 'PE', 'MU', 'FAL'] as $c) {
                $html .= $thSub($c);
            }
        }
        $html .= $thSub('REC') . $thSub('MS');
        foreach (['MA', 'PF', 'MF', 'Situação'] as $c) {
            $html .= $thSub($c);
        }
        $html .= '</tr></thead><tbody>';

        if ($linhas === []) {
            $html .= '<tr><td colspan="33" style="border:1px solid #111;padding:8px;text-align:center;">Sem notas no demonstrativo para montar este boletim.</td></tr>';
            return $html . '</tbody></table>'
                . $this->legendaHtml();
        }

        foreach ($linhas as $row) {
            $nome = (string) ($row['nome'] ?? '');
            $area = !empty($row['area']);
            $bims = is_array($row['bims'] ?? null) ? $row['bims'] : [];
            $styleNome = $area
                ? 'font-weight:bold;border:1px solid #111;padding:1px 3px;background:#fafafa;'
                : 'border:1px solid #111;padding:1px 3px;';
            $html .= '<tr><td style="' . $styleNome . '">' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</td>';

            $mus = [];
            $recS1 = null;
            foreach ([1, 2, 3, 4] as $b) {
                $cel = is_array($bims[$b] ?? null) ? $bims[$b] : [];
                $pp = $area ? null : ($cel['pp'] ?? null);
                $pu = $area ? null : ($cel['pu'] ?? null);
                $pe = $area ? null : ($cel['pe'] ?? null);
                $mu = $cel['mu'] ?? null;
                $fal = (int) ($cel['fal'] ?? 0);
                $mus[$b] = is_numeric($mu) ? (float) $mu : null;
                if ($b <= 2 && isset($cel['rec']) && is_numeric($cel['rec'])) {
                    $recS1 = (float) $cel['rec'];
                }

                $html .= '<td style="border:1px solid #111;text-align:center;">' . $this->fmt($pp) . '</td>'
                    . '<td style="border:1px solid #111;text-align:center;">' . $this->fmt($pu) . '</td>'
                    . '<td style="border:1px solid #111;text-align:center;">—</td>'
                    . '<td style="border:1px solid #111;text-align:center;">' . $this->fmt($pe) . '</td>'
                    . '<td style="border:1px solid #111;text-align:center;">' . $this->fmt($mu, true) . '</td>'
                    . '<td style="border:1px solid #111;text-align:center;">' . ($fal > 0 ? (string) $fal : '0') . '</td>';

                if ($b === 2) {
                    $ms = $this->mediaLista([$mus[1] ?? null, $mus[2] ?? null]);
                    if ($recS1 !== null && $ms !== null) {
                        $ms = max($ms, $recS1);
                    }
                    $html .= '<td style="border:1px solid #111;text-align:center;">' . $this->fmt($recS1) . '</td>'
                        . '<td style="border:1px solid #111;text-align:center;">' . $this->fmt($ms, true) . '</td>';
                }
                if ($b === 4) {
                    $recS2 = null;
                    $ms = $this->mediaLista([$mus[3] ?? null, $mus[4] ?? null]);
                    $html .= '<td style="border:1px solid #111;text-align:center;">' . $this->fmt($recS2) . '</td>'
                        . '<td style="border:1px solid #111;text-align:center;">' . $this->fmt($ms, true) . '</td>';
                }
            }

            $html .= '<td style="border:1px solid #111;text-align:center;">—</td>'
                . '<td style="border:1px solid #111;text-align:center;">—</td>'
                . '<td style="border:1px solid #111;text-align:center;">—</td>'
                . '<td style="border:1px solid #111;text-align:center;font-size:6.5pt;">Cursando</td></tr>';
        }

        return $html . '</tbody></table>' . $this->legendaHtml();
    }

    private function legendaHtml(): string
    {
        return '<p style="font-size:6.5pt;margin:6px 0 2px;line-height:1.35;">'
            . '<b>Legenda:</b> PP — Prova Parcial | PU — Prova de Unidade | PR — Projetos | CH — 2ª Chamada | '
            . 'PE — Ponto Extra | MU — Média da Unidade | FAL — Faltas | REC — Recuperação | '
            . 'MS — Média do Semestre | MA — Média Anual | PF — Prova Final | MF — Média Final'
            . '</p>'
            . '<p style="font-size:7pt;margin:0;"><b>Observação:</b> O estudante que não atingir média semestral 7,0 (sete) '
            . 'deverá realizar a recuperação semestral. MU = ((PP + PU) / 2) + PE.</p>';
    }

    private function calcularMu(?float $pp, ?float $pu, ?float $pe): ?float
    {
        $base = [];
        if ($pp !== null) {
            $base[] = $pp;
        }
        if ($pu !== null) {
            $base[] = $pu;
        }
        if ($base === []) {
            return $pe;
        }
        $m = array_sum($base) / count($base);
        if ($pe !== null) {
            $m += $pe;
        }
        return round($m, 1);
    }

    /**
     * @param list<?float> $vals
     */
    private function mediaLista(array $vals): ?float
    {
        $ok = [];
        foreach ($vals as $v) {
            if ($v !== null) {
                $ok[] = $v;
            }
        }
        if ($ok === []) {
            return null;
        }
        return round(array_sum($ok) / count($ok), 1);
    }

    private function numOuNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_numeric($v)) {
            return null;
        }
        return (float) $v;
    }

    private function fmt(?float $v, bool $bold = false): string
    {
        if ($v === null) {
            return '—';
        }
        $t = number_format($v, 1, ',', '');
        return $bold ? '<b>' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</b>' : htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buscarResultadosQuadroAluno(int $alunoId, int $anoLetivo, string $exibirEm): array
    {
        $exibirEm = strtolower(trim($exibirEm));
        if (!in_array($exibirEm, ['notas', 'boletim'], true)) {
            return [];
        }
        $filtroBim = $exibirEm === 'notas'
            ? 'AND r.bimestre BETWEEN 1 AND 4'
            : 'AND (r.bimestre IS NULL OR r.bimestre BETWEEN 1 AND 4)';
        return $this->db->fetchAll(
            "SELECT g.materia_id, g.materia_nome, g.media_final, g.notas_json, g.id AS gid,
                    r.bimestre, r.id AS regra_id
               FROM boletim_resultados_gerados g
               INNER JOIN boletim_regras r ON r.id = g.regra_id
              WHERE g.aluno_id = :aluno
                AND r.ano_letivo = :ano
                AND r.exibir_em = :exibir
                AND r.ativo = 1
                {$filtroBim}
              ORDER BY g.materia_nome ASC, r.bimestre ASC, g.id DESC",
            ['aluno' => $alunoId, 'ano' => $anoLetivo, 'exibir' => $exibirEm]
        ) ?: [];
    }
}
