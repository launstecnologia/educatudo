<?php
/**
 * EducaTudo - Log de anomalias durante a realização de provas online
 *
 * Registra só EVENTO/ANOMALIA (erro de sessão/resposta/finalização, tentativa
 * de burlar o modo seguro) — nunca o fluxo normal da prova. Consultado pelo
 * Master em /master/log-provas (ver MasterLogProvasController).
 *
 * registrar() nunca lança exceção pra fora: uma falha ao gravar o log não pode
 * derrubar a prova do aluno nem mascarar o erro real que estava sendo logado.
 */
class ProvaLogEvento
{
    /** Tipos de evento aceitos — mantido em lista simples (não ENUM no banco) para permitir novos tipos sem migration. */
    public const TIPOS_VALIDOS = [
        'erro_sessao',
        'erro_salvar_resposta',
        'erro_finalizar',
        'saida_modo_seguro',
        'tentativa_sair_tela_cheia',
        'tentativa_atualizar_pagina',
        'tentativa_voltar_navegador',
        'outro',
    ];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @param array<string,mixed> $dados tipo_evento (obrigatório), aluno_id, prova_id, bloco_id, detalhe, ip, user_agent
     */
    public function registrar(array $dados): bool
    {
        try {
            $tipoEvento = (string) ($dados['tipo_evento'] ?? '');
            if ($tipoEvento === '' || !in_array($tipoEvento, self::TIPOS_VALIDOS, true)) {
                $tipoEvento = 'outro';
            }

            $detalhe = (string) ($dados['detalhe'] ?? '');
            if (mb_strlen($detalhe) > 2000) {
                $detalhe = mb_substr($detalhe, 0, 2000);
            }

            $userAgent = (string) ($dados['user_agent'] ?? '');
            if (mb_strlen($userAgent) > 255) {
                $userAgent = mb_substr($userAgent, 0, 255);
            }

            $this->db->insert(
                "INSERT INTO provas_log_eventos (tipo_evento, aluno_id, prova_id, bloco_id, detalhe, ip, user_agent)
                 VALUES (:tipo_evento, :aluno_id, :prova_id, :bloco_id, :detalhe, :ip, :user_agent)",
                [
                    'tipo_evento' => $tipoEvento,
                    'aluno_id' => !empty($dados['aluno_id']) ? (int) $dados['aluno_id'] : null,
                    'prova_id' => !empty($dados['prova_id']) ? (int) $dados['prova_id'] : null,
                    'bloco_id' => !empty($dados['bloco_id']) ? (int) $dados['bloco_id'] : null,
                    'detalhe' => $detalhe !== '' ? $detalhe : null,
                    'ip' => (string) ($dados['ip'] ?? '') !== '' ? (string) $dados['ip'] : null,
                    'user_agent' => $userAgent !== '' ? $userAgent : null,
                ]
            );
            return true;
        } catch (\Throwable $e) {
            error_log('ProvaLogEvento::registrar falhou: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param array<string,mixed> $filtros tipo_evento, aluno_nome (LIKE), data_inicio, data_fim
     * @return array<int, array<string,mixed>>
     */
    public function listar(array $filtros, int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT e.id, e.tipo_evento, e.aluno_id, a.nome AS aluno_nome, a.turma_id,
                       e.prova_id, p.titulo AS prova_titulo, e.bloco_id, pb.titulo AS bloco_titulo,
                       e.detalhe, e.ip, e.user_agent, e.created_at
                FROM provas_log_eventos e
                LEFT JOIN alunos a ON a.id = e.aluno_id
                LEFT JOIN provas p ON p.id = e.prova_id
                LEFT JOIN provas_blocos pb ON pb.id = e.bloco_id
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['tipo_evento'])) {
            $sql .= " AND e.tipo_evento = :tipo_evento";
            $params['tipo_evento'] = (string) $filtros['tipo_evento'];
        }
        if (!empty($filtros['aluno_nome'])) {
            $sql .= " AND a.nome LIKE :aluno_nome";
            $params['aluno_nome'] = '%' . $filtros['aluno_nome'] . '%';
        }
        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND e.created_at >= :data_inicio";
            $params['data_inicio'] = (string) $filtros['data_inicio'] . ' 00:00:00';
        }
        if (!empty($filtros['data_fim'])) {
            $sql .= " AND e.created_at <= :data_fim";
            $params['data_fim'] = (string) $filtros['data_fim'] . ' 23:59:59';
        }

        $sql .= " ORDER BY e.created_at DESC LIMIT " . (int) $limit . " OFFSET " . (int) $offset;

        return $this->db->fetchAll($sql, $params);
    }
}
