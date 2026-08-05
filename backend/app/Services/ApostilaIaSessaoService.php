<?php
/**
 * Sessões de chat da IA da Apostila (Fase B).
 * Cada usuário (professor/aluno) pode ter várias threads por apostila.
 */

class ApostilaIaSessaoService
{
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(int $apostilaId, int $usuarioId, string $usuarioTipo): array
    {
        return $this->db->fetchAll(
            "SELECT id, titulo, resumo, created_at, updated_at
             FROM apostila_ia_sessoes
             WHERE apostila_id = :apostila_id
               AND usuario_id = :usuario_id
               AND usuario_tipo = :usuario_tipo
             ORDER BY updated_at DESC, id DESC
             LIMIT 50",
            [
                'apostila_id' => $apostilaId,
                'usuario_id' => $usuarioId,
                'usuario_tipo' => $usuarioTipo,
            ]
        );
    }

    public function criar(int $apostilaId, int $usuarioId, string $usuarioTipo, string $titulo = 'Nova conversa'): int
    {
        return (int) $this->db->insert(
            "INSERT INTO apostila_ia_sessoes (apostila_id, usuario_id, usuario_tipo, titulo)
             VALUES (:apostila_id, :usuario_id, :usuario_tipo, :titulo)",
            [
                'apostila_id' => $apostilaId,
                'usuario_id' => $usuarioId,
                'usuario_tipo' => $usuarioTipo,
                'titulo' => mb_substr(trim($titulo), 0, 255),
            ]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buscarValida(int $sessaoId, int $apostilaId, int $usuarioId, string $usuarioTipo): ?array
    {
        if ($sessaoId <= 0) {
            return null;
        }

        return $this->db->fetch(
            "SELECT id, titulo, resumo, created_at, updated_at
             FROM apostila_ia_sessoes
             WHERE id = :id
               AND apostila_id = :apostila_id
               AND usuario_id = :usuario_id
               AND usuario_tipo = :usuario_tipo
             LIMIT 1",
            [
                'id' => $sessaoId,
                'apostila_id' => $apostilaId,
                'usuario_id' => $usuarioId,
                'usuario_tipo' => $usuarioTipo,
            ]
        ) ?: null;
    }

    /**
     * Resolve a sessão ativa: usa $sessaoId se válido; senão a mais recente;
     * se não existir nenhuma, cria "Nova conversa".
     *
     * @return array<string,mixed>
     */
    public function resolverAtiva(int $apostilaId, int $usuarioId, string $usuarioTipo, ?int $sessaoId): array
    {
        $this->migrarConversasLegadas($apostilaId, $usuarioId, $usuarioTipo);

        if ($sessaoId !== null && $sessaoId > 0) {
            $sessao = $this->buscarValida($sessaoId, $apostilaId, $usuarioId, $usuarioTipo);
            if ($sessao !== null) {
                return $sessao;
            }
        }

        $sessoes = $this->listar($apostilaId, $usuarioId, $usuarioTipo);
        if (!empty($sessoes)) {
            return $sessoes[0];
        }

        $novoId = $this->criar($apostilaId, $usuarioId, $usuarioTipo);
        $criada = $this->buscarValida($novoId, $apostilaId, $usuarioId, $usuarioTipo);
        if ($criada === null) {
            throw new RuntimeException('Falha ao criar sessão de chat da apostila.');
        }

        return $criada;
    }

    /**
     * Conversas antigas (sem sessao_id) viram uma sessão "Conversa anterior".
     */
    public function migrarConversasLegadas(int $apostilaId, int $usuarioId, string $usuarioTipo): void
    {
        $legadoIdCol = $usuarioTipo === 'aluno' ? 'aluno_id' : 'professor_id';
        $params = ['apostila_id' => $apostilaId, $legadoIdCol => $usuarioId];

        $pendentesRow = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM apostila_ia_conversas
             WHERE apostila_id = :apostila_id
               AND professor_id = :{$legadoIdCol}
               AND sessao_id IS NULL",
            $params
        );
        $pendentes = (int)($pendentesRow['total'] ?? 0);

        if ($pendentes <= 0) {
            return;
        }

        $sessaoId = $this->criar($apostilaId, $usuarioId, $usuarioTipo, 'Conversa anterior');

        $this->db->query(
            "UPDATE apostila_ia_conversas
             SET sessao_id = :sessao_id
             WHERE apostila_id = :apostila_id
               AND professor_id = :{$legadoIdCol}
               AND sessao_id IS NULL",
            array_merge($params, ['sessao_id' => $sessaoId])
        );
    }

    public function garantirTituloPrimeiraMensagem(int $sessaoId, string $pergunta): void
    {
        $sessao = $this->db->fetch(
            "SELECT id, titulo FROM apostila_ia_sessoes WHERE id = :id LIMIT 1",
            ['id' => $sessaoId]
        );
        if (!$sessao || ($sessao['titulo'] ?? '') !== 'Nova conversa') {
            return;
        }

        $pergunta = trim($pergunta);
        if ($pergunta === '') {
            return;
        }

        $titulo = mb_strlen($pergunta) > 60 ? mb_substr($pergunta, 0, 57) . '...' : $pergunta;
        $this->db->query(
            "UPDATE apostila_ia_sessoes SET titulo = :titulo WHERE id = :id",
            ['titulo' => $titulo, 'id' => $sessaoId]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function historicoDaSessao(int $sessaoId, int $limite = 100): array
    {
        return $this->db->fetchAll(
            "SELECT id, pergunta, resposta, paginas_usadas, created_at
             FROM apostila_ia_conversas
             WHERE sessao_id = :sessao_id
             ORDER BY created_at ASC, id ASC
             LIMIT " . (int) $limite,
            ['sessao_id' => $sessaoId]
        );
    }

    /**
     * @return list<string>
     */
    public function parseSugestoesChat(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $sugestoes = [];
        foreach ($decoded as $item) {
            if (is_string($item) && trim($item) !== '') {
                $sugestoes[] = trim($item);
            }
        }
        return array_slice(array_values(array_unique($sugestoes)), 0, 8);
    }

    /**
     * @return list<string>
     */
    public function sugestoesPadrao(string $usuarioTipo): array
    {
        if ($usuarioTipo === 'aluno') {
            return [
                'Resuma este material',
                'Liste os exercícios',
                'Explique este conteúdo de forma simples',
                'Quais páginas falam sobre este tema?',
            ];
        }

        return [
            'Resuma esta apostila',
            'Liste os exercícios',
            'Quais páginas falam sobre este tema?',
            'Explique este conteúdo de forma simples',
        ];
    }
}
