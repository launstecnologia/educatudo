<?php

require_once __DIR__ . '/../../../Core/Database.php';

class PresencaIntegracao
{
    public const PROVEDORES = ['generico', 'intelbras', 'control_id', 'henry', 'facial_educatudo'];
    public const MODOS = ['webhook', 'polling'];
    public const MAPEAMENTOS = ['ra', 'codigo_aluno', 'aluno_id', 'cartao'];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tabelasProntas(): bool
    {
        return $this->db->tableExists('presenca_integracoes');
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(): array
    {
        if (!$this->tabelasProntas()) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT id, nome, provedor, modo, mapeamento_identificador, ativo, ultimo_erro, created_at
             FROM presenca_integracoes ORDER BY nome ASC'
        ) ?: [];
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0 || !$this->tabelasProntas()) {
            return null;
        }
        $row = $this->db->fetch('SELECT * FROM presenca_integracoes WHERE id = :id LIMIT 1', ['id' => $id]);
        return $row ?: null;
    }

    public function findByTokenHash(string $hash): ?array
    {
        if ($hash === '' || !$this->tabelasProntas()) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT * FROM presenca_integracoes WHERE token_hash = :hash AND ativo = 1 LIMIT 1',
            ['hash' => $hash]
        );
        return $row ?: null;
    }

    /**
     * @return array{id:int, token:string}
     */
    public function criar(array $dados): array
    {
        if (!$this->tabelasProntas()) {
            throw new RuntimeException('Rode a migration 2026_08_22_gestao_presenca.sql no Master.');
        }
        $nome = trim((string) ($dados['nome'] ?? ''));
        if ($nome === '') {
            throw new InvalidArgumentException('Informe o nome da integração.');
        }
        $provedor = (string) ($dados['provedor'] ?? 'generico');
        if (!in_array($provedor, self::PROVEDORES, true)) {
            $provedor = 'generico';
        }
        $modo = (string) ($dados['modo'] ?? 'webhook');
        if (!in_array($modo, self::MODOS, true)) {
            $modo = 'webhook';
        }
        $mapa = (string) ($dados['mapeamento_identificador'] ?? 'ra');
        if (!in_array($mapa, self::MAPEAMENTOS, true)) {
            $mapa = 'ra';
        }
        $token = bin2hex(random_bytes(32));
        $id = (int) $this->db->insert(
            'INSERT INTO presenca_integracoes
                (nome, provedor, modo, mapeamento_identificador, token_hash, ativo)
             VALUES (:nome, :provedor, :modo, :mapa, :hash, 1)',
            [
                'nome' => $nome,
                'provedor' => $provedor,
                'modo' => $modo,
                'mapa' => $mapa,
                'hash' => hash('sha256', $token),
            ]
        );
        return ['id' => $id, 'token' => $token];
    }

    public function setAtivo(int $id, bool $ativo): void
    {
        if ($id <= 0) {
            return;
        }
        $this->db->update(
            'UPDATE presenca_integracoes SET ativo = :ativo WHERE id = :id',
            ['ativo' => $ativo ? 1 : 0, 'id' => $id]
        );
    }

    public function registrarErro(int $id, string $erro): void
    {
        if ($id <= 0) {
            return;
        }
        $this->db->update(
            'UPDATE presenca_integracoes SET ultimo_erro = :erro WHERE id = :id',
            ['erro' => mb_substr($erro, 0, 255), 'id' => $id]
        );
    }
}
