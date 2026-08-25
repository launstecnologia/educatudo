<?php

namespace App\Modulos\Matricula\Services;

use Database;

/**
 * Virada de ano: clona turmas e sugere sucessão por ordem da série.
 */
class MatriculaViradaService
{
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @return array{clonadas:int,ligadas:int,erros:list<string>}
     */
    public function clonarTurmas(int $anoOrigemId, int $anoDestinoId): array
    {
        if ($anoOrigemId <= 0 || $anoDestinoId <= 0 || $anoOrigemId === $anoDestinoId) {
            throw new \InvalidArgumentException('Informe anos letivos de origem e destino diferentes.');
        }
        $origem = $this->db->fetch('SELECT id, ano FROM ano_letivo WHERE id = ?', [$anoOrigemId]);
        $destino = $this->db->fetch('SELECT id, ano FROM ano_letivo WHERE id = ?', [$anoDestinoId]);
        if (!$origem || !$destino) {
            throw new \InvalidArgumentException('Ano letivo inválido.');
        }

        $turmas = $this->db->fetchAll(
            'SELECT * FROM turmas WHERE ano_letivo_id = ? AND ativo = 1 ORDER BY serie, nome',
            [$anoOrigemId]
        ) ?: [];

        $clonadas = 0;
        $ligadas = 0;
        $erros = [];
        $temVagas = $this->temColuna('turmas', 'vagas');
        $temOrigem = $this->temColuna('turmas', 'turma_origem_id');

        foreach ($turmas as $turma) {
            $turmaId = (int) $turma['id'];
            try {
                if ($temOrigem) {
                    $ja = $this->db->fetch(
                        'SELECT id FROM turmas WHERE turma_origem_id = ? AND ano_letivo_id = ? LIMIT 1',
                        [$turmaId, $anoDestinoId]
                    );
                    if ($ja) {
                        $ligadas++;
                        continue;
                    }
                }

                $serieDestinoId = $this->proximaSerieId((int) ($turma['serie_id'] ?? 0));
                if (!empty($turma['serie_id']) && $serieDestinoId === null) {
                    continue;
                }
                $serieNome = (string) ($turma['serie'] ?? '');
                if ($serieDestinoId) {
                    $s = $this->db->fetch('SELECT nome FROM serie WHERE id = ?', [$serieDestinoId]);
                    if ($s) {
                        $serieNome = (string) $s['nome'];
                    }
                } else {
                    $serieDestinoId = (int) ($turma['serie_id'] ?? 0) ?: null;
                }

                $nomeNovo = $this->nomeTurmaDestino((string) $turma['nome'], $serieNome);
                $cols = ['nome', 'ano_letivo', 'serie', 'ativo'];
                $vals = [$nomeNovo, (int) $destino['ano'], $serieNome, 1];

                if ($this->temColuna('turmas', 'curso_novo_id')) {
                    $cols[] = 'curso_novo_id';
                    $vals[] = (int) ($turma['curso_novo_id'] ?? 0) ?: null;
                }
                if ($this->temColuna('turmas', 'serie_id')) {
                    $cols[] = 'serie_id';
                    $vals[] = $serieDestinoId;
                }
                if ($this->temColuna('turmas', 'ano_letivo_id')) {
                    $cols[] = 'ano_letivo_id';
                    $vals[] = $anoDestinoId;
                }
                if ($temVagas) {
                    $cols[] = 'vagas';
                    $vals[] = isset($turma['vagas']) && $turma['vagas'] !== null ? (int) $turma['vagas'] : null;
                }
                if ($temOrigem) {
                    $cols[] = 'turma_origem_id';
                    $vals[] = $turmaId;
                }

                $ph = implode(',', array_fill(0, count($cols), '?'));
                $this->db->insert(
                    'INSERT INTO turmas (' . implode(', ', $cols) . ') VALUES (' . $ph . ')',
                    $vals
                );
                $clonadas++;
            } catch (\Throwable $e) {
                $erros[] = ($turma['nome'] ?? ('#' . $turmaId)) . ': ' . $e->getMessage();
            }
        }

        return ['clonadas' => $clonadas, 'ligadas' => $ligadas, 'erros' => $erros];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function mapaSucessao(int $anoOrigemId, int $anoDestinoId): array
    {
        $cols = 'id, nome, serie, serie_id';
        if ($this->temColuna('turmas', 'vagas')) {
            $cols .= ', vagas';
        }
        $turmas = $this->db->fetchAll(
            'SELECT ' . $cols . ' FROM turmas WHERE ano_letivo_id = ? AND ativo = 1 ORDER BY serie, nome',
            [$anoOrigemId]
        ) ?: [];
        $out = [];
        foreach ($turmas as $t) {
            $sucessora = null;
            if ($this->temColuna('turmas', 'turma_origem_id')) {
                $sucessora = $this->db->fetch(
                    'SELECT id, nome, serie FROM turmas WHERE turma_origem_id = ? AND ano_letivo_id = ? LIMIT 1',
                    [(int) $t['id'], $anoDestinoId]
                ) ?: null;
            }
            $proxSerie = $this->proximaSerieId((int) ($t['serie_id'] ?? 0));
            $proxNome = null;
            if ($proxSerie) {
                $s = $this->db->fetch('SELECT nome FROM serie WHERE id = ?', [$proxSerie]);
                $proxNome = $s['nome'] ?? null;
            }
            $out[] = [
                'origem' => $t,
                'sucessora' => $sucessora,
                'proxima_serie' => $proxNome,
                'conclui' => $proxSerie === null && !empty($t['serie_id']),
            ];
        }
        return $out;
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
            'SELECT id FROM serie WHERE curso_id = ? AND ativo = 1 AND ordem > ? ORDER BY ordem ASC LIMIT 1',
            [(int) $atual['curso_id'], (int) $atual['ordem']]
        );
        return $prox ? (int) $prox['id'] : null;
    }

    private function nomeTurmaDestino(string $nomeOrigem, string $serieNova): string
    {
        $letra = '';
        if (preg_match('/([A-Za-z])\s*$/', $nomeOrigem, $m)) {
            $letra = strtoupper($m[1]);
        }
        $base = trim($serieNova);
        if ($base === '') {
            return $nomeOrigem;
        }
        return $letra !== '' ? ($base . ' ' . $letra) : $base;
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
