<?php

namespace App\Infra\Services;

use App\Core\Database;

/**
 * Decide se a sessão atual pode ler um arquivo privado.
 * Arquivo de aluno (student/{id}/ ou file_{id}_ / chat_{id}_) não sai para outra conta.
 */
class AcessoArquivoPrivado
{
    public static function podeLer(string $type, string $key): bool
    {
        $tipo = (string) ($_SESSION['user_type'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if (!empty($_SESSION['master_user_id']) || in_array($tipo, ['admin', 'admin_escola'], true)) {
            return true;
        }

        $dono = self::donoAluno($key);
        if ($dono !== null) {
            if ($tipo === 'aluno' && $userId === $dono) {
                return true;
            }
            if ($tipo === 'pai' && $userId > 0) {
                require_once dirname(__DIR__, 2) . '/Policies/ParentStudentAccessPolicy.php';
                if ((new \ParentStudentAccessPolicy())->canAccess($userId, $dono)) {
                    return true;
                }
            }
            if ($type !== 'chat' && in_array($tipo, ['professor', 'monitor'], true)) {
                return true;
            }

            return false;
        }

        if ($type === 'chat') {
            return $tipo === 'aluno' && self::mensagemDoAluno($userId, $key);
        }

        return $userId > 0 && $tipo !== '';
    }

    private static function donoAluno(string $key): ?int
    {
        if (preg_match('#(?:^|/)(?:student|aluno)/(\d+)(?:/|$)#', $key, $m)) {
            return (int) $m[1];
        }
        if (preg_match('#(?:^|/)(?:file|chat)_(\d+)_#', $key, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private static function mensagemDoAluno(int $alunoId, string $key): bool
    {
        $nome = basename(str_replace('\\', '/', $key));
        if ($alunoId <= 0 || $nome === '' || $nome === '.' || $nome === '..') {
            return false;
        }
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $nome)) {
            return false;
        }

        $row = Database::getInstance()->fetch(
            'SELECT m.id
             FROM tudinha_mensagens m
             INNER JOIN tudinha_conversas c ON c.id = m.conversa_id
             WHERE c.aluno_id = :aluno
               AND c.excluida = 0
               AND m.image_url LIKE :arquivo
             LIMIT 1',
            ['aluno' => $alunoId, 'arquivo' => '%' . $nome]
        );

        return (bool) $row;
    }
}
