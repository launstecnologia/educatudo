<?php

namespace App\Painel\Master\Services;

/**
 * Gera o SQL de estrutura de um banco de escola, sem linhas.
 * É o mesmo conteúdo gravado em database/educa_core.sql para a próxima escola.
 */
class SchemaTenantService
{
    /**
     * @throws \RuntimeException
     */
    public static function gerar(\PDO $pdo, string $origem = ''): string
    {
        $tabelas = $pdo->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
             ORDER BY TABLE_NAME"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $views = $pdo->query(
            "SELECT TABLE_NAME FROM information_schema.VIEWS
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $tabelas = array_values(array_filter($tabelas, static function ($nome) {
            return !self::ignorado((string) $nome);
        }));
        $locais = [];
        foreach ($tabelas as $tabela) {
            $locais[(string) $tabela] = true;
        }
        foreach ($views as $view) {
            $locais[(string) $view] = true;
        }

        $bancoAtual = self::identificador((string) $pdo->query('SELECT DATABASE()')->fetchColumn());

        $origem = trim(str_replace(["\r", "\n"], ' ', $origem));
        $linhas = [];
        $linhas[] = '-- EducaTudo — schema base do tenant (somente estrutura, sem dados)';
        $linhas[] = '-- Gerado em ' . date('Y-m-d H:i:s');
        if ($origem !== '') {
            $linhas[] = '-- Origem: ' . $origem;
        }
        $linhas[] = '-- Usado ao criar escola no Master (MysqlProvisioningService::aplicarSchemaBaseTenant).';
        $linhas[] = '';
        $linhas[] = 'SET NAMES utf8mb4;';
        $linhas[] = "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';";
        $linhas[] = 'SET FOREIGN_KEY_CHECKS = 0;';
        $linhas[] = 'SET UNIQUE_CHECKS = 0;';
        $linhas[] = '';

        foreach ($tabelas as $tabela) {
            $nome = self::identificador((string) $tabela);
            $row = $pdo->query('SHOW CREATE TABLE ' . self::quote($nome))->fetch(\PDO::FETCH_ASSOC);
            $ddl = (string) ($row['Create Table'] ?? '');
            if ($ddl === '') {
                throw new \RuntimeException('Sem DDL para a tabela ' . $nome . '.');
            }
            $ddl = preg_replace('/AUTO_INCREMENT=\d+\s*/i', '', $ddl) ?? $ddl;
            $ddl = str_replace('`' . $bancoAtual . '`.', '', $ddl);
            self::recusarOutroBanco($ddl, $locais, $nome);
            $ddl = preg_replace('/^CREATE TABLE /i', 'CREATE TABLE IF NOT EXISTS ', $ddl) ?? $ddl;
            $linhas[] = '--';
            $linhas[] = '-- ' . $nome;
            $linhas[] = '--';
            $linhas[] = $ddl . ';';
            $linhas[] = '';
        }

        foreach ($views as $view) {
            $nome = self::identificador((string) $view);
            $row = $pdo->query('SHOW CREATE VIEW ' . self::quote($nome))->fetch(\PDO::FETCH_ASSOC);
            $ddl = (string) ($row['Create View'] ?? '');
            if ($ddl === '') {
                continue;
            }
            $ddl = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/i', '', $ddl) ?? $ddl;
            $ddl = str_replace('`' . $bancoAtual . '`.', '', $ddl);
            self::recusarOutroBanco($ddl, $locais, $nome);
            $ddl = preg_replace('/^CREATE /i', 'CREATE OR REPLACE ', $ddl) ?? $ddl;
            $linhas[] = '-- view ' . $nome;
            $linhas[] = $ddl . ';';
            $linhas[] = '';
        }

        $triggers = $pdo->query('SHOW TRIGGERS')->fetchAll(\PDO::FETCH_ASSOC);
        if ($triggers) {
            $linhas[] = 'DELIMITER $$';
            foreach ($triggers as $trg) {
                $tabela = self::identificador((string) ($trg['Table'] ?? ''));
                if (self::ignorado($tabela)) {
                    continue;
                }
                $nome = self::identificador((string) ($trg['Trigger'] ?? ''));
                $timing = (string) ($trg['Timing'] ?? '');
                $event = (string) ($trg['Event'] ?? '');
                $stmt = (string) ($trg['Statement'] ?? '');
                if (!in_array($timing, ['BEFORE', 'AFTER'], true) || !in_array($event, ['INSERT', 'UPDATE', 'DELETE'], true)) {
                    throw new \RuntimeException('Trigger com timing ou evento inesperado: ' . $nome . '.');
                }
                if (strpos($stmt, '$$') !== false) {
                    throw new \RuntimeException('Trigger ' . $nome . ' usa o delimitador $$. O schema não foi gerado.');
                }
                if ($stmt === '') {
                    continue;
                }
                $linhas[] = 'DROP TRIGGER IF EXISTS ' . self::quote($nome) . '$$';
                $linhas[] = 'CREATE TRIGGER ' . self::quote($nome) . " {$timing} {$event} ON " . self::quote($tabela)
                    . ' FOR EACH ROW ' . rtrim($stmt, '; ') . '$$';
                $linhas[] = '';
            }
            $linhas[] = 'DELIMITER ;';
            $linhas[] = '';
        }

        $linhas[] = 'SET UNIQUE_CHECKS = 1;';
        $linhas[] = 'SET FOREIGN_KEY_CHECKS = 1;';
        $linhas[] = '';

        return implode("\n", $linhas);
    }

    private static function ignorado(string $nome): bool
    {
        return $nome === 'migrations_escolas' || strpos($nome, 'backup_') === 0;
    }

    /**
     * Depois de tirar o banco atual, `tabela`.`coluna` permanece.
     * Qualquer prefixo que não seja tabela ou view deste banco é outro schema.
     *
     * @param array<string,bool> $locais
     */
    private static function recusarOutroBanco(string $ddl, array $locais, string $view): void
    {
        if (preg_match_all('/`([A-Za-z0-9_]+)`\s*\.\s*`([A-Za-z0-9_]+)`/', $ddl, $achados, PREG_SET_ORDER) !== false) {
            foreach ($achados as $achado) {
                if (!isset($locais[$achado[1]])) {
                    throw new \RuntimeException('A view ' . $view . ' referencia outro banco (' . $achado[1] . '). O schema não foi gerado.');
                }
            }
        }
    }

    private static function identificador(string $nome): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $nome) !== 1) {
            throw new \RuntimeException('Nome de objeto SQL recusado: ' . $nome . '.');
        }
        return $nome;
    }

    private static function quote(string $nome): string
    {
        return '`' . $nome . '`';
    }
}
