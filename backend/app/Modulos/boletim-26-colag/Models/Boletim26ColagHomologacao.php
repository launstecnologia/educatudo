<?php

namespace App\Modulos\Boletim26Colag\Models;

use App\Core\Database;

/**
 * Grava e lê a homologação do demonstrativo do Colag. Uma linha nova a cada confirmação.
 */
class Boletim26ColagHomologacao
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tabelaPronta(): bool
    {
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'boletim_colag_homologacoes'");

            return $row !== false && $row !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array{ano:int,serie:int,turma_id:int,aluno_id:int,bimestre:int,nota_campo:string,nota_minima:?float} $filtro
     * @return array<string,mixed>|null
     */
    public function ultima(array $filtro): ?array
    {
        if (!$this->tabelaPronta()) {
            return null;
        }
        $sql = "SELECT id, resumo_json, usuario_nome, criado_em
                  FROM boletim_colag_homologacoes
                 WHERE ano = :ano
                   AND serie = :serie
                   AND turma_id = :turma_id
                   AND aluno_id = :aluno_id
                   AND bimestre = :bimestre
                   AND nota_campo = :nota_campo";
        $params = [
            'ano' => (int) $filtro['ano'],
            'serie' => (int) $filtro['serie'],
            'turma_id' => (int) $filtro['turma_id'],
            'aluno_id' => (int) $filtro['aluno_id'],
            'bimestre' => (int) $filtro['bimestre'],
            'nota_campo' => (string) $filtro['nota_campo'],
        ];
        if ($filtro['nota_minima'] === null) {
            $sql .= ' AND nota_minima IS NULL';
        } else {
            $sql .= ' AND nota_minima = :nota_minima';
            $params['nota_minima'] = $filtro['nota_minima'];
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        try {
            $row = $this->db->fetch($sql, $params);
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{ano:int,serie:int,turma_id:int,aluno_id:int,bimestre:int,nota_campo:string,nota_minima:?float} $filtro
     * @param list<array<string,mixed>> $linhas
     */
    public function registrar(array $filtro, array $linhas, int $usuarioId, string $usuarioNome): void
    {
        $this->db->insert(
            "INSERT INTO boletim_colag_homologacoes
                (ano, serie, turma_id, aluno_id, bimestre, nota_campo, nota_minima, resumo_json, usuario_id, usuario_nome)
             VALUES
                (:ano, :serie, :turma_id, :aluno_id, :bimestre, :nota_campo, :nota_minima, :resumo_json, :usuario_id, :usuario_nome)",
            [
                'ano' => (int) $filtro['ano'],
                'serie' => (int) $filtro['serie'],
                'turma_id' => (int) $filtro['turma_id'],
                'aluno_id' => (int) $filtro['aluno_id'],
                'bimestre' => (int) $filtro['bimestre'],
                'nota_campo' => (string) $filtro['nota_campo'],
                'nota_minima' => $filtro['nota_minima'],
                'resumo_json' => json_encode($linhas, JSON_UNESCAPED_UNICODE),
                'usuario_id' => $usuarioId,
                'usuario_nome' => mb_substr(trim($usuarioNome), 0, 160),
            ]
        );
    }
}
