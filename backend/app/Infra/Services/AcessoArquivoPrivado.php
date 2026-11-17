<?php

namespace App\Infra\Services;

use App\Core\Database;

/**
 * Decide se a sessão atual pode ler um arquivo privado.
 * A permissão sai do registro no banco. Sem registro, só o aluno dono do caminho
 * (arquivo recém-enviado) ou o responsável vinculado a ele.
 */
class AcessoArquivoPrivado
{
    private const TIPOS_DO_ALUNO = ['chat', 'redacoes', 'tickets', 'arquivos', 'mensagens'];

    public static function podeLer(string $type, string $key): bool
    {
        $tipo = (string) ($_SESSION['user_type'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if (!empty($_SESSION['master_user_id']) || in_array($tipo, ['admin', 'admin_escola'], true)) {
            return true;
        }

        if (!in_array($type, self::TIPOS_DO_ALUNO, true)) {
            return $userId > 0 && $tipo !== '';
        }

        $registro = self::registro($type, $key);
        if ($registro !== null) {
            return self::sessaoPodeRegistro($type, $tipo, $userId, $registro);
        }

        $dono = self::donoAluno($key);
        if ($dono === null) {
            return false;
        }

        return self::ehDonoOuPai($tipo, $userId, $dono);
    }

    /**
     * Pedido direto a /uploads/ ou /public/uploads/.
     * rota = o controller da rota entrega (pode estar no S3).
     * publico / privado = este processo pode ler o arquivo do disco.
     * negado = não entrega.
     */
    public static function pastaDireta(string $relativo): string
    {
        if (strpos($relativo, 'uploads/jornadas/exercicios/') === 0
            || strpos($relativo, 'uploads/provas/questoes/') === 0) {
            return 'rota';
        }
        if (strpos($relativo, 'uploads/cadernos_aluno/') === 0) {
            return 'negado';
        }
        if (strpos($relativo, 'uploads/redacoes/') === 0 && strpos($relativo, 'uploads/redacoes_temas/') !== 0) {
            $key = substr($relativo, strlen('uploads/redacoes/'));

            return self::podeLer('redacoes', $key) ? 'privado' : 'negado';
        }
        if (strpos($relativo, 'uploads/arquivos/') === 0) {
            $key = substr($relativo, strlen('uploads/arquivos/'));

            return self::podeLer('arquivos', $key) ? 'privado' : 'negado';
        }
        if (strpos($relativo, 'uploads/mensagens/') === 0) {
            $key = substr($relativo, strlen('uploads/mensagens/'));

            return self::podeLer('mensagens', $key) ? 'privado' : 'negado';
        }
        if (strpos($relativo, 'uploads/essays/submissions/') === 0 || strpos($relativo, 'uploads/essays/feedback_audio/') === 0) {
            $key = substr($relativo, strlen('uploads/'));

            return self::podeLer('redacoes', $key) ? 'privado' : 'negado';
        }
        if (strpos($relativo, 'uploads/tickets/') === 0) {
            $key = substr($relativo, strlen('uploads/tickets/'));

            return self::podeLer('tickets', $key) ? 'privado' : 'negado';
        }

        return 'publico';
    }

    /**
     * @param array{aluno_id:int,turma_id:int,professor_ids:array<int,int>,so_dono:bool} $registro
     */
    private static function sessaoPodeRegistro(string $type, string $tipo, int $userId, array $registro): bool
    {
        $alunoId = (int) $registro['aluno_id'];
        $turmaId = (int) $registro['turma_id'];

        if ($tipo === 'aluno' && $userId > 0) {
            if ($alunoId > 0 && $userId === $alunoId) {
                return true;
            }
            if ($alunoId === 0 && $turmaId > 0 && self::turmaDoAluno($userId) === $turmaId) {
                return true;
            }

            return false;
        }

        if ($tipo === 'pai' && $userId > 0) {
            if ($alunoId > 0) {
                return self::paiPodeAluno($userId, $alunoId);
            }
            if ($turmaId > 0) {
                return self::paiTemFilhoNaTurma($userId, $turmaId);
            }

            return false;
        }

        if (!empty($registro['so_dono']) || in_array($type, ['chat', 'tickets'], true)) {
            return false;
        }

        if ($tipo === 'professor' && $userId > 0) {
            if (in_array($userId, $registro['professor_ids'], true)) {
                return true;
            }

            return $turmaId > 0 && self::professorDaTurma($userId, $turmaId);
        }

        if ($tipo === 'monitor' && $userId > 0) {
            return $turmaId > 0 && self::monitorDaTurma($userId, $turmaId);
        }

        return false;
    }

    private static function ehDonoOuPai(string $tipo, int $userId, int $dono): bool
    {
        if ($userId <= 0 || $dono <= 0) {
            return false;
        }
        if ($tipo === 'aluno' && $userId === $dono) {
            return true;
        }
        if ($tipo === 'pai') {
            return self::paiPodeAluno($userId, $dono);
        }

        return false;
    }

    /**
     * @return array{aluno_id:int,turma_id:int,professor_ids:array<int,int>,so_dono:bool}|null
     */
    private static function registro(string $type, string $key): ?array
    {
        $nome = basename(str_replace('\\', '/', $key));
        if ($nome === '' || $nome === '.' || $nome === '..' || strlen($nome) > 255 || strpbrk($nome, "%\\\0") !== false) {
            return null;
        }
        $dono = self::donoAluno($key) ?? 0;

        try {
            if ($type === 'chat') {
                return self::registroChat($nome, $dono);
            }
            if ($type === 'redacoes') {
                return self::registroRedacao($nome, $dono);
            }
            if ($type === 'tickets') {
                return self::registroTicket($nome, $dono);
            }
            if ($type === 'arquivos') {
                return self::registroArquivo($nome, $dono);
            }
            if ($type === 'mensagens') {
                return self::registroMensagem($nome, $dono);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * @return array{aluno_id:int,turma_id:int,professor_ids:array<int,int>,so_dono:bool}|null
     */
    private static function registroChat(string $nome, int $dono): ?array
    {
        $row = Database::getInstance()->fetch(
            'SELECT c.aluno_id, a.turma_id
             FROM tudinha_mensagens m
             INNER JOIN tudinha_conversas c ON c.id = m.conversa_id
             INNER JOIN alunos a ON a.id = c.aluno_id
             WHERE c.excluida = 0
               AND LOCATE(:nome, COALESCE(m.image_url, \'\')) > 0
               AND (:dono = 0 OR c.aluno_id = :dono)
             LIMIT 1',
            ['nome' => $nome, 'dono' => $dono]
        );

        return $row ? self::montar((int) $row['aluno_id'], (int) ($row['turma_id'] ?? 0), [], true) : null;
    }

    /**
     * @return array{aluno_id:int,turma_id:int,professor_ids:array<int,int>,so_dono:bool}|null
     */
    private static function registroRedacao(string $nome, int $dono): ?array
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            'SELECT r.aluno_id, a.turma_id, r.corrigida_por_professor, j.professor_id AS professor_jornada
             FROM redacoes r
             INNER JOIN alunos a ON a.id = r.aluno_id
             LEFT JOIN jornadas j ON j.id = r.jornada_id
             WHERE (LOCATE(:nome, COALESCE(r.imagem_url, \'\')) > 0
                 OR LOCATE(:nome, COALESCE(r.imagem_path, \'\')) > 0)
               AND (:dono = 0 OR r.aluno_id = :dono)
             LIMIT 1',
            ['nome' => $nome, 'dono' => $dono]
        );
        if ($row) {
            return self::montar(
                (int) $row['aluno_id'],
                (int) ($row['turma_id'] ?? 0),
                self::idsValidos([$row['corrigida_por_professor'] ?? 0, $row['professor_jornada'] ?? 0]),
                false
            );
        }

        $entrega = $db->fetch(
            'SELECT e.student_id AS aluno_id, a.turma_id, p.teacher_id
             FROM redacoes_orientadas_entregas e
             INNER JOIN alunos a ON a.id = e.student_id
             INNER JOIN redacoes_orientadas_propostas p ON p.id = e.proposal_id
             WHERE LOCATE(:nome, COALESCE(e.content_image_path, \'\')) > 0
               AND (:dono = 0 OR e.student_id = :dono)
             LIMIT 1',
            ['nome' => $nome, 'dono' => $dono]
        );
        if (!$entrega) {
            return null;
        }

        return self::montar(
            (int) $entrega['aluno_id'],
            (int) ($entrega['turma_id'] ?? 0),
            self::idsValidos([$entrega['teacher_id'] ?? 0]),
            false
        );
    }

    /**
     * @return array{aluno_id:int,turma_id:int,professor_ids:array<int,int>,so_dono:bool}|null
     */
    private static function registroTicket(string $nome, int $dono): ?array
    {
        $row = Database::getInstance()->fetch(
            'SELECT t.aluno_id, a.turma_id
             FROM suporte_tickets_mensagens m
             INNER JOIN suporte_tickets t ON t.id = m.ticket_id
             INNER JOIN alunos a ON a.id = t.aluno_id
             WHERE (LOCATE(:nome, COALESCE(m.anexo, \'\')) > 0
                 OR LOCATE(:nome, COALESCE(m.mensagem, \'\')) > 0)
               AND (:dono = 0 OR t.aluno_id = :dono)
             LIMIT 1',
            ['nome' => $nome, 'dono' => $dono]
        );

        return $row ? self::montar((int) $row['aluno_id'], (int) ($row['turma_id'] ?? 0), [], true) : null;
    }

    /**
     * @return array{aluno_id:int,turma_id:int,professor_ids:array<int,int>,so_dono:bool}|null
     */
    private static function registroArquivo(string $nome, int $dono): ?array
    {
        $row = Database::getInstance()->fetch(
            'SELECT COALESCE(ma.aluno_id, 0) AS aluno_id, ma.turma_id, ma.professor_id
             FROM modulos_arquivos_anexos anx
             INNER JOIN modulos_arquivos ma ON ma.id = anx.modulo_arquivo_id
             WHERE LOCATE(:nome, anx.caminho) > 0
               AND (:dono = 0 OR ma.aluno_id = :dono OR ma.aluno_id IS NULL)
             ORDER BY (ma.aluno_id = :dono) DESC, ma.aluno_id DESC
             LIMIT 1',
            ['nome' => $nome, 'dono' => $dono]
        );

        return $row
            ? self::montar((int) $row['aluno_id'], (int) ($row['turma_id'] ?? 0), self::idsValidos([$row['professor_id'] ?? 0]), false)
            : null;
    }

    /**
     * @return array{aluno_id:int,turma_id:int,professor_ids:array<int,int>,so_dono:bool}|null
     */
    private static function registroMensagem(string $nome, int $dono): ?array
    {
        $row = Database::getInstance()->fetch(
            'SELECT msg.aluno_id, a.turma_id, msg.professor_id
             FROM jornadas_mensagens_anexos anx
             INNER JOIN jornadas_mensagens msg ON msg.id = anx.mensagem_id
             INNER JOIN alunos a ON a.id = msg.aluno_id
             WHERE (LOCATE(:nome, COALESCE(anx.caminho_arquivo, \'\')) > 0
                 OR LOCATE(:nome, COALESCE(anx.nome_arquivo, \'\')) > 0)
               AND (:dono = 0 OR msg.aluno_id = :dono)
             LIMIT 1',
            ['nome' => $nome, 'dono' => $dono]
        );

        return $row
            ? self::montar((int) $row['aluno_id'], (int) ($row['turma_id'] ?? 0), self::idsValidos([$row['professor_id'] ?? 0]), false)
            : null;
    }

    /**
     * @param array<int,int> $professorIds
     * @return array{aluno_id:int,turma_id:int,professor_ids:array<int,int>,so_dono:bool}
     */
    private static function montar(int $alunoId, int $turmaId, array $professorIds, bool $soDono): array
    {
        return [
            'aluno_id' => $alunoId,
            'turma_id' => $turmaId,
            'professor_ids' => $professorIds,
            'so_dono' => $soDono,
        ];
    }

    /**
     * @param array<int,mixed> $ids
     * @return array<int,int>
     */
    private static function idsValidos(array $ids): array
    {
        $validos = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $validos[] = $id;
            }
        }

        return $validos;
    }

    private static function donoAluno(string $key): ?int
    {
        if (preg_match('#(?:^|/)(?:student|aluno)/(\d+)(?:/|$)#', $key, $m)) {
            return (int) $m[1];
        }
        if (preg_match('#(?:^|/)(?:file|chat|ticket|redacao)_(\d+)_#', $key, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private static function paiPodeAluno(int $paiId, int $alunoId): bool
    {
        require_once dirname(__DIR__, 2) . '/Policies/ParentStudentAccessPolicy.php';

        return (new \ParentStudentAccessPolicy())->canAccess($paiId, $alunoId);
    }

    private static function turmaDoAluno(int $alunoId): int
    {
        $row = Database::getInstance()->fetch(
            'SELECT turma_id FROM alunos WHERE id = :id',
            ['id' => $alunoId]
        );

        return (int) ($row['turma_id'] ?? 0);
    }

    private static function paiTemFilhoNaTurma(int $paiId, int $turmaId): bool
    {
        $row = Database::getInstance()->fetch(
            'SELECT a.id
             FROM alunos a
             WHERE a.turma_id = :turma
               AND (
                    a.responsavel_id = :pai
                    OR EXISTS (
                        SELECT 1 FROM alunos_responsaveis ar
                        WHERE ar.aluno_id = a.id AND ar.responsavel_id = :pai AND ar.ativo = 1
                    )
               )
             LIMIT 1',
            ['turma' => $turmaId, 'pai' => $paiId]
        );

        return (bool) $row;
    }

    private static function professorDaTurma(int $professorId, int $turmaId): bool
    {
        try {
            $row = Database::getInstance()->fetch(
                'SELECT turmas FROM professores WHERE id = :id',
                ['id' => $professorId]
            );
        } catch (\Throwable $e) {
            return false;
        }

        return self::turmaEstaNaLista($row['turmas'] ?? null, $turmaId);
    }

    private static function monitorDaTurma(int $monitorId, int $turmaId): bool
    {
        try {
            $row = Database::getInstance()->fetch(
                'SELECT turmas FROM monitores WHERE id = :id',
                ['id' => $monitorId]
            );
        } catch (\Throwable $e) {
            return false;
        }

        return self::turmaEstaNaLista($row['turmas'] ?? null, $turmaId);
    }

    private static function turmaEstaNaLista($turmas, int $turmaId): bool
    {
        $lista = json_decode((string) $turmas, true);
        if (!is_array($lista)) {
            return false;
        }
        foreach ($lista as $turma) {
            if ((int) $turma === $turmaId) {
                return true;
            }
        }

        return false;
    }
}
