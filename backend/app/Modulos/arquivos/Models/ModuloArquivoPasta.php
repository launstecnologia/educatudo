<?php
/**
 * Model — pastas do módulo Arquivos (tabela modulos_arquivos_pastas).
 */

if (!class_exists('ModuloArquivoPasta')) {
class ModuloArquivoPasta
{
    private $db;
    private ?bool $temColunaArquivoAtivo = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function sqlArquivoAtivo(string $alias = 'ma'): string
    {
        if ($this->temColunaArquivoAtivo === null) {
            $this->temColunaArquivoAtivo = (bool) $this->db->fetch(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'modulos_arquivos'
                   AND COLUMN_NAME = 'ativo'"
            );
        }
        if (!$this->temColunaArquivoAtivo) {
            return '1=1';
        }
        $coluna = $alias !== '' ? $alias . '.ativo' : 'ativo';
        return "({$coluna} = 1 OR {$coluna} IS NULL)";
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM modulos_arquivos_pastas WHERE id = :id',
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function temColunaParent(): bool
    {
        return (bool) $this->db->fetch(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'modulos_arquivos_pastas'
               AND COLUMN_NAME = 'parent_id'"
        );
    }

    public function findByIdDoProfessor(int $id, int $professorId): ?array
    {
        $row = $this->db->fetch(
            'SELECT id FROM modulos_arquivos_pastas WHERE id = :id AND professor_id = :prof_id',
            ['id' => $id, 'prof_id' => $professorId]
        );
        return $row ?: null;
    }

    public function findByIdAdmin(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT id FROM modulos_arquivos_pastas WHERE id = :id AND criado_por_tipo = \'admin\'',
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function createProfessor(string $nome, string $cor, int $professorId): int
    {
        return (int) $this->db->insert(
            'INSERT INTO modulos_arquivos_pastas (nome, cor, professor_id, criado_por_tipo)
             VALUES (:nome, :cor, :prof_id, \'professor\')',
            ['nome' => $nome, 'cor' => $cor, 'prof_id' => $professorId]
        );
    }

    public function createAdmin(string $nome, string $cor, ?int $parentId = null): int
    {
        if ($parentId !== null) {
            return (int) $this->db->insert(
                'INSERT INTO modulos_arquivos_pastas (nome, cor, professor_id, criado_por_tipo, parent_id)
                 VALUES (:nome, :cor, NULL, \'admin\', :parent_id)',
                ['nome' => $nome, 'cor' => $cor, 'parent_id' => $parentId]
            );
        }
        return (int) $this->db->insert(
            'INSERT INTO modulos_arquivos_pastas (nome, cor, professor_id, criado_por_tipo)
             VALUES (:nome, :cor, NULL, \'admin\')',
            ['nome' => $nome, 'cor' => $cor]
        );
    }

    public function rename(int $id, string $nome): void
    {
        $this->db->query(
            'UPDATE modulos_arquivos_pastas SET nome = :nome WHERE id = :id',
            ['nome' => $nome, 'id' => $id]
        );
    }

    public function delete(int $id): void
    {
        $this->db->query('DELETE FROM modulos_arquivos_pastas WHERE id = :id', ['id' => $id]);
    }

    public function tabelaExiste(): bool
    {
        return (bool) $this->db->fetch("SHOW TABLES LIKE 'modulos_arquivos_pastas'");
    }

    public function listProfessor(int $professorId): array
    {
        return $this->db->fetchAll(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM modulos_arquivos ma
                     WHERE ma.pasta_id = p.id AND ma.professor_id = :prof_id2 AND {$this->sqlArquivoAtivo('ma')}) AS total_arquivos
             FROM modulos_arquivos_pastas p
             WHERE p.professor_id = :prof_id AND p.criado_por_tipo = 'professor'
             ORDER BY p.ordem ASC, p.nome ASC",
            ['prof_id' => $professorId, 'prof_id2' => $professorId]
        ) ?: [];
    }

    public function listAdmin(?int $parentId): array
    {
        if ($this->temColunaParent()) {
            $parentSql = $parentId === null ? 'p.parent_id IS NULL' : 'p.parent_id = :parent_id';
            $params = $parentId === null ? [] : ['parent_id' => $parentId];
            $pastas = $this->db->fetchAll(
                "SELECT p.*,
                        (SELECT COUNT(*) FROM modulos_arquivos ma WHERE ma.pasta_id = p.id AND {$this->sqlArquivoAtivo('ma')}) AS total_arquivos,
                        (SELECT COUNT(*) FROM modulos_arquivos_pastas sub WHERE sub.parent_id = p.id) AS total_subpastas
                 FROM modulos_arquivos_pastas p
                 WHERE p.criado_por_tipo = 'admin' AND {$parentSql}
                 ORDER BY p.ordem ASC, p.nome ASC",
                $params
            ) ?: [];
            return $this->aplicarContagemRecursivaAdmin($pastas);
        }

        return $this->db->fetchAll(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM modulos_arquivos ma WHERE ma.pasta_id = p.id AND {$this->sqlArquivoAtivo('ma')}) AS total_arquivos,
                    0 AS total_subpastas
             FROM modulos_arquivos_pastas p
             WHERE p.criado_por_tipo = 'admin'
             ORDER BY p.ordem ASC, p.nome ASC"
        ) ?: [];
    }

    /**
     * Troca total_arquivos (só da pasta) pela soma desta pasta + subpastas.
     *
     * @param array<int, array<string, mixed>> $pastas
     * @return array<int, array<string, mixed>>
     */
    private function aplicarContagemRecursivaAdmin(array $pastas): array
    {
        if ($pastas === []) {
            return $pastas;
        }
        $arvore = $this->db->fetchAll(
            "SELECT id, parent_id FROM modulos_arquivos_pastas WHERE criado_por_tipo = 'admin'"
        ) ?: [];
        $totais = $this->somarArquivosNaArvore($arvore, $this->contarArquivosDiretos());
        foreach ($pastas as &$pasta) {
            $pasta['total_arquivos'] = $totais[(int) $pasta['id']] ?? 0;
        }
        unset($pasta);
        return $pastas;
    }

    /**
     * @return array<int, int> pasta_id => quantidade
     */
    public function contarArquivosDiretos(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT pasta_id, COUNT(*) AS c FROM modulos_arquivos WHERE pasta_id IS NOT NULL AND ' . $this->sqlArquivoAtivo('') . ' GROUP BY pasta_id'
        ) ?: [];
        $mapa = [];
        foreach ($rows as $row) {
            $mapa[(int) $row['pasta_id']] = (int) $row['c'];
        }
        return $mapa;
    }

    /**
     * @param array<int, array<string, mixed>> $pastas
     * @param array<int, int> $contagemDireta
     * @return array<int, int> pasta_id => quantidade incluindo descendentes
     */
    public function somarArquivosNaArvore(array $pastas, array $contagemDireta): array
    {
        $filhos = [];
        foreach ($pastas as $pasta) {
            $id = (int) $pasta['id'];
            $parentId = isset($pasta['parent_id']) && $pasta['parent_id'] !== null && $pasta['parent_id'] !== ''
                ? (int) $pasta['parent_id']
                : 0;
            $filhos[$parentId][] = $id;
        }
        $memo = [];
        $somar = function (int $id, array $pilha) use (&$somar, &$memo, $contagemDireta, $filhos): int {
            if (isset($memo[$id])) {
                return $memo[$id];
            }
            if (isset($pilha[$id])) {
                return $contagemDireta[$id] ?? 0;
            }
            $pilha[$id] = true;
            $total = $contagemDireta[$id] ?? 0;
            foreach ($filhos[$id] ?? [] as $filhoId) {
                $total += $somar($filhoId, $pilha);
            }
            return $memo[$id] = $total;
        };
        $totais = [];
        foreach ($pastas as $pasta) {
            $id = (int) $pasta['id'];
            $totais[$id] = $somar($id, []);
        }
        return $totais;
    }

    public function listAllAdmin(): array
    {
        if ($this->temColunaParent()) {
            return $this->db->fetchAll(
                "SELECT p.id, p.nome, p.parent_id
                 FROM modulos_arquivos_pastas p
                 WHERE p.criado_por_tipo = 'admin'
                 ORDER BY p.nome ASC"
            ) ?: [];
        }
        return $this->db->fetchAll(
            "SELECT id, nome, NULL AS parent_id FROM modulos_arquivos_pastas
             WHERE criado_por_tipo = 'admin'
             ORDER BY nome ASC"
        ) ?: [];
    }

    public function findAdminCompleta(int $id): ?array
    {
        $row = $this->db->fetch(
            "SELECT * FROM modulos_arquivos_pastas WHERE id = :id AND criado_por_tipo = 'admin'",
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function breadcrumb(array $pastaAtual): array
    {
        if (!$this->temColunaParent()) {
            return [];
        }
        $breadcrumb = [];
        $cur = $pastaAtual;
        while (!empty($cur['parent_id'])) {
            $cur = $this->findById((int) $cur['parent_id']);
            if (!$cur) {
                break;
            }
            array_unshift($breadcrumb, $cur);
        }
        return $breadcrumb;
    }

    public function breadcrumbAdmin(array $pastaAtual): array
    {
        return $this->breadcrumb($pastaAtual);
    }
}
}
