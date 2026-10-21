<?php

namespace App\Painel\Master\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Esvazia o banco de uma única escola, preservando os administradores.
 *
 * O nome do banco permitido está fixo nesta classe. A requisição não escolhe
 * o alvo: se a conexão ativa não for exatamente esse banco, nada é apagado.
 */
class LimparDadosEscolaEducaService
{
    public const BANCO_PERMITIDO = 'educa_007_escolaeduca';

    public const FRASE_CONFIRMACAO = 'EXCLUIR DADOS';

    /** Carga que a ação "zerar e popular" executa depois da limpeza. Nome fixo. */
    public const ARQUIVO_CARGA = '2026_10_06_importar_dados_escola_teste_em_2025.sql';

    /**
     * O seed não recria estes catálogos. Entram só na ação zerar e popular.
     *
     * @var list<string>
     */
    public const PRESERVAR_NA_CARGA = [
        'materias',
        'ocorrencias_categorias',
    ];

    /**
     * Cadastro de acesso e controle de schema. O restante das tabelas é esvaziado.
     *
     * @var list<string>
     */
    private const PRESERVAR = [
        'usuarios',
        'admin_perfis_permissao',
        'migrations_executadas',
        'migrations_log',
        'migrations',
        'schema_migrations',
    ];

    public static function bancoPermitido(string $nomeBanco): bool
    {
        return $nomeBanco === self::BANCO_PERMITIDO;
    }

    /**
     * O seed EM 2025 recusa o banco se estes componentes não existirem.
     * Devolve a mensagem de recusa, ou null quando pode seguir.
     */
    public static function catalogoProntoParaCarga(\PDO $pdo): ?string
    {
        $trava = self::travarConexao($pdo);
        if ($trava !== null) {
            return $trava . ' Nada foi alterado.';
        }
        if (!self::tabelaExiste($pdo, 'materias')) {
            return 'A tabela materias não existe. Nada foi alterado.';
        }

        $nomes = [
            'Língua Portuguesa',
            'Matemática',
            'História',
            'Geografia',
            'Física',
            'Química',
            'Biologia',
            'Sociologia',
        ];
        $params = [];
        $placeholders = [];
        foreach ($nomes as $i => $nome) {
            $chave = 'n' . $i;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $nome;
        }
        $stmt = $pdo->prepare(
            'SELECT COUNT(DISTINCT nome) FROM materias WHERE nome IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        if ($total < count($nomes)) {
            return 'Catálogo de componentes incompleto (' . $total . ' de ' . count($nomes) . '). Nada foi alterado.';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function tabelasPreservadas(): array
    {
        return self::PRESERVAR;
    }

    /**
     * @return array{ok:bool,erro?:string,usuarios?:int,tabelas?:list<string>,preservadas?:list<string>}
     */
    public static function inspecionar(\PDO $pdo, array $preservarTambem = []): array
    {
        $trava = self::travarConexao($pdo);
        if ($trava !== null) {
            return ['ok' => false, 'erro' => $trava];
        }

        $extras = self::extrasSeguros($preservarTambem);
        $preservadas = [];
        $tabelas = [];
        $temUsuarios = false;
        foreach (self::listarTabelasBase($pdo) as $tabela) {
            if (self::preservar($tabela, $extras)) {
                $preservadas[] = $tabela;
                if (strtolower($tabela) === 'usuarios') {
                    $temUsuarios = true;
                }
            } else {
                $tabelas[] = $tabela;
            }
        }

        if (!$temUsuarios) {
            return ['ok' => false, 'erro' => 'A tabela usuarios não existe neste banco. Nada foi alterado.'];
        }

        return [
            'ok' => true,
            'usuarios' => self::contarUsuarios($pdo),
            'tabelas' => $tabelas,
            'preservadas' => $preservadas,
        ];
    }

    /**
     * @return array{ok:bool,erro?:string,usuarios?:int,tabelas_limpas?:int,falhas?:list<string>}
     */
    public static function executar(\PDO $pdo, string $nomeBancoConfigurado, array $preservarTambem = []): array
    {
        if (!self::bancoPermitido($nomeBancoConfigurado)) {
            return ['ok' => false, 'erro' => 'Recusado: esta escola não usa o banco permitido.'];
        }

        $extras = self::extrasSeguros($preservarTambem);
        $trava = self::travarConexao($pdo);
        if ($trava !== null) {
            return ['ok' => false, 'erro' => $trava . ' Nada foi alterado.'];
        }

        $antes = self::inspecionar($pdo, $extras);
        if (empty($antes['ok'])) {
            return ['ok' => false, 'erro' => (string) ($antes['erro'] ?? 'Inspeção recusou a limpeza.')];
        }

        $usuariosAntes = (int) $antes['usuarios'];
        $tabelas = $antes['tabelas'] ?? [];

        Logger::warning('Limpeza de dados iniciada no banco permitido', [
            'banco' => self::BANCO_PERMITIDO,
            'tabelas' => count($tabelas),
            'usuarios' => $usuariosAntes,
        ], 'master');

        $limpas = 0;
        $falhas = [];
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tabelas as $tabela) {
                if (self::preservar($tabela, $extras) || !self::identificadorSeguro($tabela)) {
                    continue;
                }
                $confirma = self::travarConexao($pdo);
                if ($confirma !== null) {
                    $falhas[] = $tabela . ': ' . $confirma . ' A limpeza parou aqui; tabelas anteriores deste banco já foram esvaziadas.';
                    break;
                }
                $erro = self::esvaziar($pdo, $tabela, $extras);
                if ($erro !== null) {
                    $falhas[] = $tabela . ': ' . $erro;
                    continue;
                }
                $limpas++;
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        $usuariosDepois = self::contarUsuarios($pdo);
        if ($usuariosDepois !== $usuariosAntes) {
            Logger::error('Contagem de usuarios mudou durante a limpeza', [
                'banco' => self::BANCO_PERMITIDO,
                'antes' => $usuariosAntes,
                'depois' => $usuariosDepois,
            ], 'master');
            return [
                'ok' => false,
                'erro' => 'A limpeza foi interrompida: a quantidade de usuários mudou. Confira o banco antes de tentar de novo.',
                'usuarios' => $usuariosDepois,
                'tabelas_limpas' => $limpas,
                'falhas' => $falhas,
            ];
        }

        if ($falhas !== []) {
            Logger::error('Limpeza terminou com falhas', [
                'banco' => self::BANCO_PERMITIDO,
                'limpas' => $limpas,
                'falhas' => $falhas,
            ], 'master');
            return [
                'ok' => false,
                'erro' => 'Parte das tabelas não foi esvaziada. Usuários preservados: ' . $usuariosDepois . '.',
                'usuarios' => $usuariosDepois,
                'tabelas_limpas' => $limpas,
                'falhas' => $falhas,
            ];
        }

        Logger::info('Limpeza de dados concluída', [
            'banco' => self::BANCO_PERMITIDO,
            'limpas' => $limpas,
            'usuarios' => $usuariosDepois,
        ], 'master');

        return [
            'ok' => true,
            'usuarios' => $usuariosDepois,
            'tabelas_limpas' => $limpas,
            'falhas' => [],
        ];
    }

    private static function travarConexao(\PDO $pdo): ?string
    {
        $atual = self::bancoDaConexao($pdo);
        if ($atual !== self::BANCO_PERMITIDO) {
            return 'Recusado: a conexão ativa é "' . $atual . '", não ' . self::BANCO_PERMITIDO . '.';
        }

        $master = self::bancoMasterAtual();
        if ($master === '') {
            return 'Recusado: não foi possível identificar o banco master.';
        }
        if ($master === self::BANCO_PERMITIDO || $master === $atual) {
            return 'Recusado: a conexão do master aponta para o mesmo banco.';
        }

        return null;
    }

    private static function bancoDaConexao(\PDO $pdo): string
    {
        $nome = $pdo->query('SELECT DATABASE()')->fetchColumn();
        return is_string($nome) ? $nome : '';
    }

    private static function bancoMasterAtual(): string
    {
        $master = Database::getInstance();
        $pdo = $master->getPdo();
        if (!$pdo instanceof \PDO) {
            return '';
        }
        $nome = $pdo->query('SELECT DATABASE()')->fetchColumn();
        return is_string($nome) ? $nome : '';
    }

    /**
     * @return list<string>
     */
    private static function listarTabelasBase(\PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_TYPE = 'BASE TABLE'"
        );
        $nomes = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $nome = (string) ($row['TABLE_NAME'] ?? '');
            if (self::identificadorSeguro($nome)) {
                $nomes[] = $nome;
            }
        }
        sort($nomes);
        return $nomes;
    }

    /**
     * @param list<string> $extras
     */
    private static function preservar(string $tabela, array $extras = []): bool
    {
        $nome = strtolower($tabela);
        if (in_array($nome, self::PRESERVAR, true)) {
            return true;
        }
        return in_array($nome, $extras, true);
    }

    /**
     * @param list<string> $extras
     * @return list<string>
     */
    private static function extrasSeguros(array $extras): array
    {
        $out = [];
        foreach ($extras as $extra) {
            $nome = strtolower((string) $extra);
            if (preg_match('/^[a-z0-9_]+$/', $nome) === 1) {
                $out[] = $nome;
            }
        }
        return $out;
    }

    private static function identificadorSeguro(string $nome): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $nome) === 1;
    }

    private static function contarUsuarios(\PDO $pdo): int
    {
        if (!self::tabelaExiste($pdo, 'usuarios')) {
            return 0;
        }
        return (int) $pdo->query('SELECT COUNT(*) FROM `usuarios`')->fetchColumn();
    }

    private static function tabelaExiste(\PDO $pdo, string $tabela): bool
    {
        if (!self::identificadorSeguro($tabela)) {
            return false;
        }
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :tabela
               AND TABLE_TYPE = 'BASE TABLE'
             LIMIT 1"
        );
        $stmt->execute(['tabela' => $tabela]);
        return (bool) $stmt->fetchColumn();
    }

    private static function esvaziar(\PDO $pdo, string $tabela, array $extras = []): ?string
    {
        if (self::preservar($tabela, $extras) || !self::identificadorSeguro($tabela)) {
            return 'recusado: tabela protegida';
        }

        $sqlTabela = '`' . $tabela . '`';
        try {
            $pdo->exec('TRUNCATE TABLE ' . $sqlTabela);
            return null;
        } catch (\Throwable) {
            try {
                $pdo->exec('DELETE FROM ' . $sqlTabela);
            } catch (\Throwable $e2) {
                return $e2->getMessage();
            }
        }

        try {
            $pdo->exec('ALTER TABLE ' . $sqlTabela . ' AUTO_INCREMENT = 1');
        } catch (\Throwable) {
            // Tabela sem auto incremento continua esvaziada.
        }

        return null;
    }
}
