<?php

namespace App\Modulos\Mural\Services;

use App\Core\Database;

/**
 * Publica o recado do mural no sino e registra a confirmação de leitura.
 */
class MuralRecadoService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function publicarNoSino(int $recadoId): void
    {
        if ($recadoId <= 0) {
            return;
        }
        try {
            $recado = $this->db->fetch('SELECT * FROM mural_recados WHERE id = :id', ['id' => $recadoId]);
            if (!$recado) {
                return;
            }
            $destinatarios = $this->destinatarios($recado);
            $conteudo = trim((string) ($recado['conteudo'] ?? ''));
            if ($conteudo === '') {
                $conteudo = (string) $recado['titulo'];
            }
            $expira = ((string) ($recado['data_sai_mural'] ?? '')) . ' 23:59:59';
            $perfil = ($recado['autor_tipo'] ?? '') === 'professor' ? 'professor' : 'coordenador';
            $tipoEnviador = ($recado['autor_tipo'] ?? '') === 'professor' ? 'professor' : 'admin';

            $existente = $this->db->fetch(
                'SELECT id FROM notificacoes WHERE mural_recado_id = :id LIMIT 1',
                ['id' => $recadoId]
            );
            if ($existente) {
                $notificacaoId = (int) $existente['id'];
                $this->db->query(
                    'UPDATE notificacoes
                     SET titulo = :titulo, conteudo = :conteudo, data_expiracao = :expira, ativo = 1
                     WHERE id = :id',
                    [
                        'titulo' => $recado['titulo'],
                        'conteudo' => $conteudo,
                        'expira' => $expira,
                        'id' => $notificacaoId,
                    ]
                );
                $this->db->query('DELETE FROM notificacoes_destinatarios WHERE notificacao_id = :id', ['id' => $notificacaoId]);
            } else {
                $notificacaoId = (int) $this->db->insert(
                    "INSERT INTO notificacoes
                        (tipo_conteudo, tipos_conteudo, titulo, conteudo, enviado_por, tipo_enviador, perfil_enviador, prioridade, data_envio, data_expiracao, ativo, mural_recado_id)
                     VALUES
                        ('texto', 'texto', :titulo, :conteudo, :enviado, :tipo, :perfil, 'normal', NOW(), :expira, 1, :mural)",
                    [
                        'titulo' => $recado['titulo'],
                        'conteudo' => $conteudo,
                        'enviado' => (int) $recado['autor_id'],
                        'tipo' => $tipoEnviador,
                        'perfil' => $perfil,
                        'expira' => $expira,
                        'mural' => $recadoId,
                    ]
                );
            }
            foreach ($destinatarios as $destino) {
                $this->db->insert(
                    'INSERT INTO notificacoes_destinatarios (notificacao_id, tipo_destinatario, destinatario_id) VALUES (:nid, :tipo, :did)',
                    ['nid' => $notificacaoId, 'tipo' => $destino['tipo'], 'did' => $destino['id']]
                );
            }
        } catch (\Throwable $e) {
            error_log('Mural sino: ' . $e->getMessage());
        }
    }

    public function marcarVistoAluno(int $recadoId, int $alunoId): void
    {
        if ($recadoId <= 0 || $alunoId <= 0 || !$this->alunoPodeVer($recadoId, $alunoId)) {
            return;
        }
        $this->db->query(
            'INSERT IGNORE INTO mural_recados_vistos (mural_recado_id, aluno_id) VALUES (:rid, :aid)',
            ['rid' => $recadoId, 'aid' => $alunoId]
        );
        $this->marcarSinoLido($recadoId, $alunoId, 'alunos');
    }

    public function marcarVistoProfessor(int $recadoId, int $professorId): void
    {
        if ($recadoId <= 0 || $professorId <= 0 || !$this->professorPodeVer($recadoId, $professorId)) {
            return;
        }
        $this->db->query(
            'INSERT IGNORE INTO mural_recados_vistos (mural_recado_id, professor_id) VALUES (:rid, :pid)',
            ['rid' => $recadoId, 'pid' => $professorId]
        );
        $this->marcarSinoLido($recadoId, $professorId, 'professores');
    }

    /**
     * @param array<int, int> $turmaIds
     * @return array<int, array<string, mixed>>
     */
    public function listarRecebidosProfessor(int $professorId, array $turmaIds): array
    {
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', $turmaIds))));
        $turmaSql = '';
        $params = ['pid' => $professorId, 'pid_visto' => $professorId];
        if ($turmaIds !== []) {
            $marcas = [];
            foreach ($turmaIds as $i => $tid) {
                $chave = 't' . $i;
                $marcas[] = ':' . $chave;
                $params[$chave] = $tid;
            }
            $turmaSql = ' OR (r.professor_id IS NULL AND r.enviar_para_todos = 0 AND EXISTS (
                SELECT 1 FROM mural_recados_turmas rt
                WHERE rt.mural_recado_id = r.id AND rt.turma_id IN (' . implode(',', $marcas) . ')
            ))';
        }
        return $this->db->fetchAll(
            "SELECT r.id, r.titulo, r.conteudo, r.data_publicacao, r.enviar_para_todos,
                    CASE WHEN v.id IS NULL THEN 0 ELSE 1 END AS ja_visto
             FROM mural_recados r
             LEFT JOIN mural_recados_vistos v
               ON v.mural_recado_id = r.id AND v.professor_id = :pid_visto
             WHERE r.publico = 'professores'
               AND CURDATE() <= r.data_sai_mural
               AND (
                    r.professor_id = :pid
                    OR (r.professor_id IS NULL AND r.enviar_para_todos = 1)
                    {$turmaSql}
               )
             ORDER BY r.data_publicacao DESC
             LIMIT 50",
            $params
        );
    }

    private function alunoPodeVer(int $recadoId, int $alunoId): bool
    {
        $row = $this->db->fetch(
            "SELECT r.id
             FROM mural_recados r
             INNER JOIN alunos a ON a.id = :aluno AND a.ativo = 1
             WHERE r.id = :rid
               AND COALESCE(r.publico, 'alunos') = 'alunos'
               AND CURDATE() <= r.data_sai_mural
               AND (
                    r.aluno_id = a.id
                    OR (
                        r.aluno_id IS NULL
                        AND (
                            r.enviar_para_todos = 1
                            OR EXISTS (
                                SELECT 1 FROM mural_recados_turmas rt
                                WHERE rt.mural_recado_id = r.id AND rt.turma_id = a.turma_id
                            )
                        )
                    )
               )
             LIMIT 1",
            ['aluno' => $alunoId, 'rid' => $recadoId]
        );
        return !empty($row);
    }

    private function professorPodeVer(int $recadoId, int $professorId): bool
    {
        $recado = $this->db->fetch(
            "SELECT id, professor_id, enviar_para_todos
             FROM mural_recados
             WHERE id = :id AND publico = 'professores' AND CURDATE() <= data_sai_mural",
            ['id' => $recadoId]
        );
        if (!$recado) {
            return false;
        }
        if ((int) ($recado['professor_id'] ?? 0) === $professorId) {
            return true;
        }
        if (!empty($recado['enviar_para_todos']) && empty($recado['professor_id'])) {
            $ativo = $this->db->fetch('SELECT id FROM professores WHERE id = :id AND ativo = 1', ['id' => $professorId]);
            return !empty($ativo);
        }
        $professor = $this->db->fetch('SELECT turmas FROM professores WHERE id = :id AND ativo = 1', ['id' => $professorId]);
        if (!$professor) {
            return false;
        }
        $turmasProfessor = json_decode((string) ($professor['turmas'] ?? '[]'), true);
        if (!is_array($turmasProfessor)) {
            return false;
        }
        $alvo = array_flip(array_map('intval', $turmasProfessor));
        foreach ($this->turmasDoRecado($recadoId) as $turmaId) {
            if (isset($alvo[$turmaId])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $recado
     * @return array<int, array{tipo: string, id: int}>
     */
    private function destinatarios(array $recado): array
    {
        $publico = ($recado['publico'] ?? 'alunos') === 'professores' ? 'professores' : 'alunos';
        $recadoId = (int) $recado['id'];
        if ($publico === 'professores') {
            if (!empty($recado['professor_id'])) {
                return [['tipo' => 'professores', 'id' => (int) $recado['professor_id']]];
            }
            if (!empty($recado['enviar_para_todos'])) {
                $rows = $this->db->fetchAll('SELECT id FROM professores WHERE ativo = 1');
                return array_map(static fn ($row) => ['tipo' => 'professores', 'id' => (int) $row['id']], $rows);
            }
            $turmas = $this->turmasDoRecado($recadoId);
            return array_map(
                static fn ($id) => ['tipo' => 'professores', 'id' => $id],
                $this->professoresDasTurmas($turmas)
            );
        }
        if (!empty($recado['aluno_id'])) {
            return [['tipo' => 'alunos', 'id' => (int) $recado['aluno_id']]];
        }
        if (!empty($recado['enviar_para_todos'])) {
            $rows = $this->db->fetchAll('SELECT id FROM alunos WHERE ativo = 1');
            return array_map(static fn ($row) => ['tipo' => 'alunos', 'id' => (int) $row['id']], $rows);
        }
        $turmas = $this->turmasDoRecado($recadoId);
        if ($turmas === []) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($turmas), '?'));
        $rows = $this->db->fetchAll(
            "SELECT id FROM alunos WHERE ativo = 1 AND turma_id IN ({$marcas})",
            $turmas
        );
        return array_map(static fn ($row) => ['tipo' => 'alunos', 'id' => (int) $row['id']], $rows);
    }

    /**
     * @return array<int, int>
     */
    private function turmasDoRecado(int $recadoId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT turma_id FROM mural_recados_turmas WHERE mural_recado_id = :id',
            ['id' => $recadoId]
        );
        return array_values(array_unique(array_map('intval', array_column($rows, 'turma_id'))));
    }

    /**
     * @param array<int, int> $turmaIds
     * @return array<int, int>
     */
    private function professoresDasTurmas(array $turmaIds): array
    {
        if ($turmaIds === []) {
            return [];
        }
        $alvo = array_flip($turmaIds);
        $rows = $this->db->fetchAll('SELECT id, turmas FROM professores WHERE ativo = 1');
        $ids = [];
        foreach ($rows as $row) {
            $turmas = json_decode((string) ($row['turmas'] ?? '[]'), true);
            if (!is_array($turmas)) {
                continue;
            }
            foreach ($turmas as $tid) {
                if (isset($alvo[(int) $tid])) {
                    $ids[] = (int) $row['id'];
                    break;
                }
            }
        }
        return array_values(array_unique($ids));
    }

    private function marcarSinoLido(int $recadoId, int $pessoaId, string $tipo): void
    {
        try {
            $this->db->query(
                "UPDATE notificacoes_destinatarios nd
                 INNER JOIN notificacoes n ON n.id = nd.notificacao_id
                 SET nd.lida = 1, nd.lida_em = COALESCE(nd.lida_em, NOW())
                 WHERE n.mural_recado_id = :rid
                   AND nd.tipo_destinatario = :tipo
                   AND nd.destinatario_id = :pid",
                ['rid' => $recadoId, 'tipo' => $tipo, 'pid' => $pessoaId]
            );
        } catch (\Throwable $e) {
            error_log('Mural leitura sino: ' . $e->getMessage());
        }
    }
}
