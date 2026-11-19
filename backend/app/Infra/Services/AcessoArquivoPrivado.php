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

    /** Conteúdo da escola: sessão não basta; o registro precisa ligar o usuário ao recurso. */
    private const TIPOS_DA_ESCOLA = [
        'provas_questoes',
        'jornadas_exercicios',
        'jornadas_documentos',
        'jornadas_videos',
        'apostilas',
        'notifications',
    ];

    public static function podeLer(string $type, string $key): bool
    {
        $tipo = (string) ($_SESSION['user_type'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if (!empty($_SESSION['master_user_id']) || in_array($tipo, ['admin', 'admin_escola'], true)) {
            return true;
        }

        if (!in_array($type, self::TIPOS_DO_ALUNO, true)) {
            if (!in_array($type, self::TIPOS_DA_ESCOLA, true)) {
                return false;
            }

            return self::podeLerConteudoEscola($type, $key, $tipo, $userId);
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

            return self::decidirPrivado('redacoes', $key);
        }
        if (strpos($relativo, 'uploads/arquivos/') === 0) {
            $key = substr($relativo, strlen('uploads/arquivos/'));

            return self::decidirPrivado('arquivos', $key);
        }
        if (strpos($relativo, 'uploads/mensagens/') === 0) {
            $key = substr($relativo, strlen('uploads/mensagens/'));

            return self::decidirPrivado('mensagens', $key);
        }
        if (strpos($relativo, 'uploads/essays/submissions/') === 0 || strpos($relativo, 'uploads/essays/feedback_audio/') === 0) {
            $key = substr($relativo, strlen('uploads/'));

            return self::decidirPrivado('redacoes', $key);
        }
        if (strpos($relativo, 'uploads/tickets/') === 0) {
            $key = substr($relativo, strlen('uploads/tickets/'));

            return self::decidirPrivado('tickets', $key);
        }
        if (strpos($relativo, 'uploads/layout/') === 0
            || strpos($relativo, 'uploads/essays/proposals/') === 0
            || strpos($relativo, 'uploads/redacoes_temas/') === 0) {
            return 'publico';
        }
        if (strpos($relativo, 'uploads/documentos/') === 0) {
            return self::decidirPrivado('jornadas_documentos', substr($relativo, strlen('uploads/documentos/')));
        }
        if (strpos($relativo, 'uploads/videos/') === 0) {
            return self::decidirPrivado('jornadas_videos', substr($relativo, strlen('uploads/videos/')));
        }
        if (strpos($relativo, 'uploads/apostilas/') === 0) {
            return self::decidirPrivado('apostilas', substr($relativo, strlen('uploads/apostilas/')));
        }
        if (strpos($relativo, 'uploads/notifications/') === 0) {
            return self::decidirPrivado('notifications', substr($relativo, strlen('uploads/notifications/')));
        }
        if (strpos($relativo, 'uploads/minicursos/') === 0
            || strpos($relativo, 'uploads/expo-colag/') === 0
            || strpos($relativo, 'uploads/ingles/') === 0) {
            return self::decidirSessao();
        }

        return 'negado';
    }

    private static function temSessao(): bool
    {
        if (!empty($_SESSION['master_user_id'])) {
            return true;
        }

        return !empty($_SESSION['user_id']) && !empty($_SESSION['user_type']);
    }

    private static function decidirPrivado(string $type, string $key): string
    {
        if (!self::temSessao()) {
            return 'sem_sessao';
        }

        return self::podeLer($type, $key) ? 'privado' : 'negado';
    }

    /** Pasta da escola sem dono por arquivo: anônimo não lê; sessão da escola lê. */
    private static function decidirSessao(): string
    {
        return self::temSessao() ? 'privado' : 'sem_sessao';
    }

    /**
     * @param array{aluno_id:int,turma_id:int,professor_ids:array<int,int>,so_dono:bool} $registro
     */
    private static function sessaoPodeRegistro(string $type, string $tipo, int $userId, array $registro): bool
    {
        $alunoId = (int) $registro['aluno_id'];
        $turmas = self::turmasDoRegistro($registro);
        $alunosIds = isset($registro['alunos_ids']) && is_array($registro['alunos_ids']) ? $registro['alunos_ids'] : [];

        if (!empty($registro['oculto_aluno']) && in_array($tipo, ['aluno', 'pai', 'monitor'], true)) {
            return false;
        }

        if ($tipo === 'aluno' && $userId > 0) {
            if ($alunosIds !== [] && !in_array($userId, $alunosIds, true)) {
                return false;
            }
            if ($alunoId > 0 && $userId === $alunoId) {
                return true;
            }
            if ($alunoId === 0 && !empty($registro['qualquer_turma'])) {
                return true;
            }
            if ($alunoId === 0 && $turmas !== []) {
                return self::alunoEmAlgumaTurma($userId, $turmas);
            }

            return false;
        }

        if ($tipo === 'pai' && $userId > 0) {
            if (!empty($registro['qualquer_turma'])) {
                return true;
            }
            if ($alunosIds !== []) {
                return self::paiPodeAlgumAluno($userId, $alunosIds);
            }
            if ($alunoId > 0) {
                return self::paiPodeAluno($userId, $alunoId);
            }
            foreach ($turmas as $turmaId) {
                if (self::paiTemFilhoNaTurma($userId, $turmaId)) {
                    return true;
                }
            }

            return false;
        }

        if (!empty($registro['so_dono']) || in_array($type, ['chat', 'tickets'], true)) {
            return false;
        }

        if ($tipo === 'professor' && $userId > 0) {
            if (!empty($registro['qualquer_turma'])) {
                return true;
            }
            if (in_array($userId, $registro['professor_ids'], true)) {
                return true;
            }
            foreach ($turmas as $turmaId) {
                if (self::professorDaTurma($userId, $turmaId)) {
                    return true;
                }
            }

            return false;
        }

        if ($tipo === 'monitor' && $userId > 0) {
            if (!empty($registro['qualquer_turma'])) {
                return true;
            }
            foreach ($turmas as $turmaId) {
                if (self::monitorDaTurma($userId, $turmaId)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    /**
     * @param array{aluno_id:int,turma_id:int,professor_ids:array<int,int>,so_dono:bool,turmas?:array<int,int>} $registro
     * @return array<int,int>
     */
    private static function turmasDoRegistro(array $registro): array
    {
        $turmas = [];
        $principal = (int) ($registro['turma_id'] ?? 0);
        if ($principal > 0) {
            $turmas[] = $principal;
        }
        foreach ($registro['turmas'] ?? [] as $turmaId) {
            $turmaId = (int) $turmaId;
            if ($turmaId > 0) {
                $turmas[] = $turmaId;
            }
        }

        return array_values(array_unique($turmas));
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

    private static function podeLerConteudoEscola(string $type, string $key, string $tipo, int $userId): bool
    {
        if ($userId <= 0 || $tipo === '') {
            return false;
        }
        if ($type === 'notifications') {
            return self::podeLerNotificacao($key, $tipo, $userId);
        }
        if (self::arquivoDoProfessorDaSessao($key, $tipo, $userId)) {
            return true;
        }

        $nome = self::nomeArquivoSeguro($key);
        if ($nome === null) {
            return false;
        }

        try {
            $linhas = self::linhasDoConteudo($type, $nome);
        } catch (\Throwable $e) {
            return false;
        }
        foreach ($linhas as $linha) {
            if (self::sessaoPodeRegistro($type, $tipo, $userId, $linha)) {
                return true;
            }
        }

        return false;
    }

    private static function arquivoDoProfessorDaSessao(string $key, string $tipo, int $userId): bool
    {
        if ($tipo !== 'professor' || $userId <= 0) {
            return false;
        }
        $caminho = str_replace('\\', '/', $key);

        return preg_match('#(?:^|/)teacher/' . $userId . '(?:/|$)#', $caminho) === 1;
    }

    private static function nomeArquivoSeguro(string $key): ?string
    {
        $nome = basename(str_replace('\\', '/', $key));
        if ($nome === '' || $nome === '.' || $nome === '..' || strlen($nome) > 255 || strpbrk($nome, "%\\\0") !== false) {
            return null;
        }

        return $nome;
    }

    /**
     * @return list<array{aluno_id:int,turma_id:int,professor_ids:array<int,int>,so_dono:bool,turmas?:array<int,int>,alunos_ids?:array<int,int>,oculto_aluno?:bool}>
     */
    private static function linhasDoConteudo(string $type, string $nome): array
    {
        $db = Database::getInstance();
        if ($type === 'jornadas_exercicios') {
            return self::linhasJornada(
                array_merge(
                    self::buscarLinhas(
                        $db,
                        'SELECT j.turma_id, j.professor_id, j.estrutura
                         FROM jornadas_modulos_exercicios me
                         INNER JOIN jornadas_modulos m ON m.id = me.modulo_id
                         INNER JOIN jornadas j ON j.id = m.jornada_id
                         WHERE LOCATE(:nome, COALESCE(me.enunciado, \'\')) > 0
                            OR LOCATE(:nome, COALESCE(me.questoes_json, \'\')) > 0',
                        $nome
                    ),
                    self::buscarLinhas(
                        $db,
                        'SELECT j.turma_id, j.professor_id, j.estrutura
                         FROM jornadas_modulos_exercicios me
                         INNER JOIN jornadas_modulos m ON m.id = me.modulo_id
                         INNER JOIN jornadas j ON j.id = m.jornada_id
                         WHERE LOCATE(:nome, COALESCE(me.imagem_url, \'\')) > 0',
                        $nome
                    )
                ),
                self::buscarLinhas(
                    $db,
                    'SELECT j.turma_id, j.professor_id, j.estrutura
                     FROM jornadas_exercicios je
                     INNER JOIN jornadas j ON j.id = je.jornada_id
                     WHERE LOCATE(:nome, COALESCE(je.descricao, \'\')) > 0
                        OR LOCATE(:nome, COALESCE(je.questoes_json, \'\')) > 0',
                    $nome
                )
            );
        }
        if ($type === 'jornadas_documentos') {
            return self::linhasJornada(self::buscarLinhas(
                $db,
                'SELECT j.turma_id, j.professor_id, j.estrutura
                 FROM jornadas_modulos_documentos d
                 INNER JOIN jornadas_modulos m ON m.id = d.modulo_id
                 INNER JOIN jornadas j ON j.id = m.jornada_id
                 WHERE LOCATE(:nome, COALESCE(d.arquivo, \'\')) > 0',
                $nome
            ), []);
        }
        if ($type === 'jornadas_videos') {
            return self::linhasJornada(self::buscarLinhas(
                $db,
                'SELECT j.turma_id, j.professor_id, j.estrutura
                 FROM jornadas_modulos_videos v
                 INNER JOIN jornadas_modulos m ON m.id = v.modulo_id
                 INNER JOIN jornadas j ON j.id = m.jornada_id
                 WHERE LOCATE(:nome, COALESCE(v.arquivo_video, \'\')) > 0',
                $nome
            ), []);
        }
        if ($type === 'provas_questoes') {
            return self::linhasProva(array_merge(
                self::buscarLinhas(
                    $db,
                    'SELECT p.id, p.turma_id, p.professor_id
                     FROM provas_questoes q
                     INNER JOIN provas p ON p.id = q.prova_id
                     WHERE LOCATE(:nome, COALESCE(q.imagem_url, \'\')) > 0
                        OR LOCATE(:nome, COALESCE(q.enunciado, \'\')) > 0',
                    $nome
                ),
                self::buscarLinhas(
                    $db,
                    'SELECT p.id, p.turma_id, p.professor_id
                     FROM provas_alternativas a
                     INNER JOIN provas_questoes q ON q.id = a.questao_id
                     INNER JOIN provas p ON p.id = q.prova_id
                     WHERE LOCATE(:nome, COALESCE(a.texto, \'\')) > 0',
                    $nome
                )
            ));
        }
        if ($type === 'apostilas') {
            return self::linhasApostila($db, $nome);
        }

        return [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function buscarLinhas(Database $db, string $sql, string $nome): array
    {
        try {
            $rows = $db->fetchAll($sql, ['nome' => $nome]);

            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<int,array<string,mixed>>|false $principal
     * @param array<int,array<string,mixed>>|false $extra
     * @return list<array{aluno_id:int,turma_id:int,professor_ids:array<int,int>,so_dono:bool,turmas?:array<int,int>,alunos_ids?:array<int,int>}>
     */
    private static function linhasJornada($principal, $extra): array
    {
        $saida = [];
        foreach (array_merge(is_array($principal) ? $principal : [], is_array($extra) ? $extra : []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $turmas = self::turmasDaJornada($row);
            $saida[] = self::montar(0, $turmas[0] ?? 0, self::idsValidos([$row['professor_id'] ?? 0]), false) + [
                'turmas' => $turmas,
                'alunos_ids' => self::alunosSelecionadosDaJornada($row),
            ];
        }

        return $saida;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return list<array{aluno_id:int,turma_id:int,professor_ids:array<int,int>,so_dono:bool}>
     */
    private static function linhasProva(array $rows): array
    {
        $porId = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || !is_array($row)) {
                continue;
            }
            $porId[$id] = $row;
        }
        if ($porId === []) {
            return [];
        }

        $ids = array_keys($porId);
        $db = Database::getInstance();
        $turmasPorProva = [];
        foreach (self::buscarPorIds($db, 'SELECT prova_id, turma_id FROM provas_turmas WHERE prova_id IN (%s)', $ids) as $extra) {
            $turmasPorProva[(int) ($extra['prova_id'] ?? 0)][] = (int) ($extra['turma_id'] ?? 0);
        }

        $blocosPorProva = [];
        $blocoIds = [];
        foreach (self::buscarPorIds(
            $db,
            'SELECT pbv.prova_id, pb.id AS bloco_id, pb.turma_id
             FROM provas_blocos_vinculo pbv
             INNER JOIN provas_blocos pb ON pb.id = pbv.bloco_id AND pb.deleted_at IS NULL
             WHERE pbv.prova_id IN (%s)',
            $ids
        ) as $bloco) {
            $provaId = (int) ($bloco['prova_id'] ?? 0);
            $blocoId = (int) ($bloco['bloco_id'] ?? 0);
            if ($provaId <= 0 || $blocoId <= 0) {
                continue;
            }
            $blocosPorProva[$provaId][$blocoId] = [
                'turma_id' => (int) ($bloco['turma_id'] ?? 0),
                'extras' => [],
            ];
            $blocoIds[$blocoId] = $blocoId;
        }
        if ($blocoIds !== []) {
            foreach (self::buscarPorIds(
                $db,
                'SELECT bloco_id, turma_id FROM provas_blocos_turmas WHERE bloco_id IN (%s)',
                array_values($blocoIds)
            ) as $extraBloco) {
                $blocoId = (int) ($extraBloco['bloco_id'] ?? 0);
                $turmaId = (int) ($extraBloco['turma_id'] ?? 0);
                foreach ($blocosPorProva as &$blocos) {
                    if ($turmaId > 0 && isset($blocos[$blocoId])) {
                        $blocos[$blocoId]['extras'][] = $turmaId;
                    }
                }
                unset($blocos);
            }
        }

        $saida = [];
        foreach ($porId as $id => $row) {
            $turmas = [];
            $principal = (int) ($row['turma_id'] ?? 0);
            if ($principal > 0) {
                $turmas[] = $principal;
            }
            foreach ($turmasPorProva[$id] ?? [] as $turmaId) {
                if ($turmaId > 0) {
                    $turmas[] = $turmaId;
                }
            }
            $provaSemTurma = $turmas === [];
            $temBloco = !empty($blocosPorProva[$id]);
            $blocoAberto = false;
            foreach ($blocosPorProva[$id] ?? [] as $bloco) {
                $doBloco = [];
                if ($bloco['turma_id'] > 0) {
                    $doBloco[] = $bloco['turma_id'];
                }
                foreach ($bloco['extras'] as $turmaId) {
                    if ($turmaId > 0) {
                        $doBloco[] = $turmaId;
                    }
                }
                if ($doBloco === []) {
                    $blocoAberto = true;
                } else {
                    $turmas = array_merge($turmas, $doBloco);
                }
            }
            $turmas = array_values(array_unique($turmas));
            $qualquerTurma = ($provaSemTurma && (!$temBloco || $blocoAberto)) || $blocoAberto;
            $saida[] = self::montar(0, $turmas[0] ?? 0, self::idsValidos([$row['professor_id'] ?? 0]), false) + [
                'turmas' => $turmas,
                'qualquer_turma' => $qualquerTurma,
            ];
        }

        return $saida;
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,array<string,mixed>>
     */
    private static function buscarPorIds(Database $db, string $sql, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $partes = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $chave = 'id' . $i;
            $partes[] = ':' . $chave;
            $params[$chave] = $id;
        }
        try {
            $rows = $db->fetchAll(sprintf($sql, implode(', ', $partes)), $params);

            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<array{aluno_id:int,turma_id:int,professor_ids:array<int,int>,so_dono:bool,turmas?:array<int,int>,oculto_aluno?:bool}>
     */
    private static function linhasApostila(Database $db, string $nome): array
    {
        try {
            $rows = $db->fetchAll(
                'SELECT ma.id, ma.turma_id, ma.professor_id, ma.visibilidade
                 FROM modulos_apostilas_anexos aa
                 INNER JOIN modulos_apostilas ma ON ma.id = aa.modulo_apostila_id
                 WHERE LOCATE(:nome, COALESCE(aa.caminho, \'\')) > 0',
                ['nome' => $nome]
            );
        } catch (\Throwable $e) {
            return [];
        }
        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $saida = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $turmas = [];
            $principal = (int) ($row['turma_id'] ?? 0);
            if ($principal > 0) {
                $turmas[] = $principal;
            }
            try {
                $extras = $db->fetchAll(
                    'SELECT turma_id FROM modulos_apostilas_turmas WHERE modulo_apostila_id = :id',
                    ['id' => (int) $row['id']]
                );
                foreach (is_array($extras) ? $extras : [] as $extra) {
                    $turmaId = (int) ($extra['turma_id'] ?? 0);
                    if ($turmaId > 0) {
                        $turmas[] = $turmaId;
                    }
                }
            } catch (\Throwable $e) {
                // A turma principal já está na lista.
            }
            $turmas = array_values(array_unique($turmas));
            $visivelAoAluno = in_array((string) ($row['visibilidade'] ?? ''), ['aluno', 'ambos'], true);
            $saida[] = self::montar(0, $turmas[0] ?? 0, self::idsValidos([$row['professor_id'] ?? 0]), false) + [
                'turmas' => $turmas,
                'oculto_aluno' => !$visivelAoAluno,
            ];
        }

        return $saida;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<int,int>
     */
    private static function turmasDaJornada(array $row): array
    {
        $turmas = [];
        $principal = (int) ($row['turma_id'] ?? 0);
        if ($principal > 0) {
            $turmas[] = $principal;
        }
        $estrutura = json_decode((string) ($row['estrutura'] ?? ''), true);
        if (is_array($estrutura) && !empty($estrutura['turmas_selecionadas']) && is_array($estrutura['turmas_selecionadas'])) {
            foreach ($estrutura['turmas_selecionadas'] as $turmaId) {
                $turmaId = (int) $turmaId;
                if ($turmaId > 0) {
                    $turmas[] = $turmaId;
                }
            }
        }

        return array_values(array_unique($turmas));
    }

    /**
     * @param array<string,mixed> $row
     * @return array<int,int>
     */
    private static function alunosSelecionadosDaJornada(array $row): array
    {
        $estrutura = json_decode((string) ($row['estrutura'] ?? ''), true);
        if (!is_array($estrutura)) {
            return [];
        }
        $tipo = strtolower(trim((string) ($estrutura['tipo_selecao_alunos'] ?? 'todos')));
        if ($tipo !== 'selecionados') {
            return [];
        }
        $ids = [];
        foreach ((array) ($estrutura['alunos_selecionados'] ?? []) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private static function podeLerNotificacao(string $key, string $tipo, int $userId): bool
    {
        $nome = self::nomeArquivoSeguro($key);
        if ($nome === null) {
            return false;
        }
        $mapa = [
            'aluno' => 'alunos',
            'professor' => 'professores',
            'pai' => 'pais',
            'admin' => 'usuarios',
            'admin_escola' => 'usuarios',
            'monitor' => 'monitores',
        ];
        $tipoDestino = $mapa[$tipo] ?? '';
        if ($tipoDestino === '') {
            return false;
        }
        try {
            $db = Database::getInstance();
            $aviso = $db->fetch(
                'SELECT id, enviado_por, tipo_enviador
                 FROM notificacoes
                 WHERE LOCATE(:nome, COALESCE(arquivo_url, \'\')) > 0
                 LIMIT 1',
                ['nome' => $nome]
            );
            if (!$aviso) {
                return false;
            }
            $enviadoPor = (int) ($aviso['enviado_por'] ?? 0);
            $tipoEnviador = (string) ($aviso['tipo_enviador'] ?? '');
            if ($enviadoPor === $userId && (
                ($tipoEnviador === 'professor' && $tipo === 'professor')
                || ($tipoEnviador === 'admin' && in_array($tipo, ['admin', 'admin_escola'], true))
            )) {
                return true;
            }
            $paramsDestino = [
                'id' => (int) $aviso['id'],
                'tipo' => $tipoDestino,
                'uid' => $userId,
                'tipo_usuario' => $tipo,
            ];
            $filtroTurma = '1 = 0';
            if ($tipo === 'aluno') {
                $partesTurma = [];
                foreach (self::turmasDoAluno($userId) as $i => $turmaId) {
                    $chave = 'turma' . $i;
                    $partesTurma[] = ':' . $chave;
                    $paramsDestino[$chave] = $turmaId;
                }
                if ($partesTurma !== []) {
                    $filtroTurma = 'nd.turma_id IN (' . implode(', ', $partesTurma) . ')';
                }
            }
            $row = $db->fetch(
                'SELECT nd.id
                 FROM notificacoes_destinatarios nd
                 WHERE nd.notificacao_id = :id
                   AND (
                        nd.tipo_destinatario = \'todos\'
                        OR (nd.tipo_destinatario = :tipo AND nd.destinatario_id = :uid)
                        OR (nd.tipo_destinatario = \'turma\' AND :tipo_usuario = \'aluno\' AND ' . $filtroTurma . ')
                   )
                 LIMIT 1',
                $paramsDestino
            );
            if ($row) {
                return true;
            }
            if ($tipo !== 'pai' && $tipo !== 'professor' && $tipo !== 'monitor') {
                return false;
            }
            $turmas = $db->fetchAll(
                'SELECT turma_id FROM notificacoes_destinatarios
                 WHERE notificacao_id = :id AND tipo_destinatario = \'turma\' AND turma_id > 0',
                ['id' => (int) $aviso['id']]
            );
            foreach (is_array($turmas) ? $turmas : [] as $turma) {
                $turmaId = (int) ($turma['turma_id'] ?? 0);
                if ($turmaId <= 0) {
                    continue;
                }
                if ($tipo === 'pai' && self::paiTemFilhoNaTurma($userId, $turmaId)) {
                    return true;
                }
                if ($tipo === 'professor' && self::professorDaTurma($userId, $turmaId)) {
                    return true;
                }
                if ($tipo === 'monitor' && self::monitorDaTurma($userId, $turmaId)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * @param array<int,int> $alunosIds
     */
    private static function paiPodeAlgumAluno(int $paiId, array $alunosIds): bool
    {
        foreach ($alunosIds as $alunoId) {
            if (self::paiPodeAluno($paiId, (int) $alunoId)) {
                return true;
            }
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

        if ($row) {
            return true;
        }
        try {
            $extra = Database::getInstance()->fetch(
                'SELECT a.id
                 FROM alunos a
                 INNER JOIN matricula m ON m.aluno_id = a.id
                 WHERE m.turma_id = :turma
                   AND m.status = \'ativa\'
                   AND m.data_saida IS NULL
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

            return (bool) $extra;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<int,int>
     */
    private static function turmasDoAluno(int $alunoId): array
    {
        if ($alunoId <= 0) {
            return [];
        }
        if (!class_exists(\App\Core\AlunoTurmaHelper::class)) {
        }
        $ids = \App\Core\AlunoTurmaHelper::getTurmaIds(Database::getInstance(), $alunoId);

        return array_values(array_unique(array_map('intval', is_array($ids) ? $ids : [])));
    }

    /**
     * @param array<int,int> $turmas
     */
    private static function alunoEmAlgumaTurma(int $alunoId, array $turmas): bool
    {
        foreach (self::turmasDoAluno($alunoId) as $turmaId) {
            if (in_array($turmaId, $turmas, true)) {
                return true;
            }
        }

        return false;
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
