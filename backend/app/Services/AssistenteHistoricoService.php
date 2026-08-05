<?php
/**
 * Persistência de conversas do Assistente (admin).
 */

class AssistenteHistoricoService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tabelasProntas(): bool
    {
        try {
            if (method_exists($this->db, 'tableExists')) {
                return $this->db->tableExists('assistente_conversas')
                    && $this->db->tableExists('assistente_mensagens');
            }
            $a = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1',
                ['t' => 'assistente_conversas']
            );
            $b = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1',
                ['t' => 'assistente_mensagens']
            );
            return !empty($a) && !empty($b);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<array{id:int,titulo:string,aluno_id:?int,aluno_nome:?string,updated_at:?string,preview:?string}>
     */
    public function listarConversas(int $usuarioId, int $limit = 50): array
    {
        if ($usuarioId <= 0 || !$this->tabelasProntas()) {
            return [];
        }
        $limit = max(1, min($limit, 100));
        $rows = $this->db->fetchAll(
            "SELECT c.id, c.titulo, c.aluno_id, c.aluno_nome, c.updated_at,
                    (SELECT m.conteudo FROM assistente_mensagens m
                      WHERE m.conversa_id = c.id
                      ORDER BY m.id DESC LIMIT 1) AS preview
             FROM assistente_conversas c
             WHERE c.usuario_id = :uid AND c.deleted_at IS NULL
             ORDER BY c.updated_at DESC, c.id DESC
             LIMIT {$limit}",
            ['uid' => $usuarioId]
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $preview = trim((string) ($r['preview'] ?? ''));
            if (mb_strlen($preview) > 80) {
                $preview = mb_substr($preview, 0, 77) . '…';
            }
            $out[] = [
                'id' => (int) $r['id'],
                'titulo' => trim((string) ($r['titulo'] ?? 'Nova conversa')),
                'aluno_id' => isset($r['aluno_id']) ? (int) $r['aluno_id'] : null,
                'aluno_nome' => isset($r['aluno_nome']) ? trim((string) $r['aluno_nome']) : null,
                'updated_at' => isset($r['updated_at']) ? (string) $r['updated_at'] : null,
                'preview' => $preview !== '' ? $preview : null,
            ];
        }
        return $out;
    }

    /** @return array{id:int,titulo:string,aluno_id:?int,aluno_nome:?string}|null */
    public function criarConversa(int $usuarioId, string $titulo = 'Nova conversa'): ?array
    {
        if ($usuarioId <= 0 || !$this->tabelasProntas()) {
            return null;
        }
        $titulo = trim($titulo);
        if ($titulo === '') {
            $titulo = 'Nova conversa';
        }
        $titulo = mb_substr($titulo, 0, 200);
        $this->db->query(
            'INSERT INTO assistente_conversas (usuario_id, titulo) VALUES (:uid, :titulo)',
            ['uid' => $usuarioId, 'titulo' => $titulo]
        );
        $id = (int) $this->db->lastInsertId();
        if ($id <= 0) {
            return null;
        }
        return [
            'id' => $id,
            'titulo' => $titulo,
            'aluno_id' => null,
            'aluno_nome' => null,
        ];
    }

    /** @return array{id:int,titulo:string,aluno_id:?int,aluno_nome:?string,mensagens:list}|null */
    public function obterConversa(int $usuarioId, int $conversaId): ?array
    {
        $conv = $this->obterCabecalho($usuarioId, $conversaId);
        if ($conv === null) {
            return null;
        }
        $msgs = $this->db->fetchAll(
            'SELECT id, role, conteudo, painel_json, created_at
             FROM assistente_mensagens
             WHERE conversa_id = :cid
             ORDER BY id ASC
             LIMIT 200',
            ['cid' => $conversaId]
        ) ?: [];

        $lista = [];
        foreach ($msgs as $m) {
            $painel = null;
            if (!empty($m['painel_json'])) {
                $dec = json_decode((string) $m['painel_json'], true);
                if (is_array($dec)) {
                    $painel = $dec;
                }
            }
            $lista[] = [
                'id' => (int) $m['id'],
                'role' => (string) $m['role'],
                'conteudo' => (string) ($m['conteudo'] ?? ''),
                'painel' => $painel,
                'created_at' => isset($m['created_at']) ? (string) $m['created_at'] : null,
            ];
        }
        $conv['mensagens'] = $lista;
        return $conv;
    }

    public function renomear(int $usuarioId, int $conversaId, string $titulo): bool
    {
        if ($this->obterCabecalho($usuarioId, $conversaId) === null) {
            return false;
        }
        $titulo = mb_substr(trim($titulo), 0, 200);
        if ($titulo === '') {
            return false;
        }
        $this->db->query(
            'UPDATE assistente_conversas SET titulo = :t, updated_at = NOW()
             WHERE id = :id AND usuario_id = :uid AND deleted_at IS NULL',
            ['t' => $titulo, 'id' => $conversaId, 'uid' => $usuarioId]
        );
        return true;
    }

    public function excluir(int $usuarioId, int $conversaId): bool
    {
        if ($this->obterCabecalho($usuarioId, $conversaId) === null) {
            return false;
        }
        $this->db->query(
            'UPDATE assistente_conversas SET deleted_at = NOW()
             WHERE id = :id AND usuario_id = :uid AND deleted_at IS NULL',
            ['id' => $conversaId, 'uid' => $usuarioId]
        );
        return true;
    }

    /**
     * @param array<string,mixed>|null $painel
     */
    public function adicionarMensagem(
        int $usuarioId,
        int $conversaId,
        string $role,
        string $conteudo,
        ?array $painel = null
    ): bool {
        if ($this->obterCabecalho($usuarioId, $conversaId) === null) {
            return false;
        }
        if (!in_array($role, ['user', 'assistant'], true)) {
            return false;
        }
        $conteudo = trim($conteudo);
        if ($conteudo === '' && $painel === null) {
            return false;
        }
        $painelJson = $painel !== null
            ? json_encode($painel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $this->db->query(
            'INSERT INTO assistente_mensagens (conversa_id, role, conteudo, painel_json)
             VALUES (:cid, :role, :conteudo, :painel)',
            [
                'cid' => $conversaId,
                'role' => $role,
                'conteudo' => $conteudo !== '' ? $conteudo : ' ',
                'painel' => $painelJson,
            ]
        );
        $this->db->query(
            'UPDATE assistente_conversas SET updated_at = NOW()
             WHERE id = :id AND usuario_id = :uid',
            ['id' => $conversaId, 'uid' => $usuarioId]
        );
        return true;
    }

    /**
     * Atualiza título automático e vínculo com aluno a partir do painel.
     *
     * @param array<string,mixed>|null $painel
     */
    public function atualizarMetaAposResposta(
        int $usuarioId,
        int $conversaId,
        string $mensagemUsuario,
        ?array $painel
    ): void {
        $conv = $this->obterCabecalho($usuarioId, $conversaId);
        if ($conv === null) {
            return;
        }

        $alunoId = null;
        $alunoNome = null;
        if (is_array($painel) && !empty($painel['aluno']) && is_array($painel['aluno'])) {
            $alunoId = (int) ($painel['aluno']['id'] ?? 0) ?: null;
            $alunoNome = trim((string) ($painel['aluno']['nome'] ?? '')) ?: null;
        }

        $tituloAtual = trim((string) ($conv['titulo'] ?? ''));
        $tituloNovo = $tituloAtual;
        if ($tituloAtual === '' || $tituloAtual === 'Nova conversa') {
            if ($alunoNome !== null) {
                $tituloNovo = 'Aluno: ' . mb_substr($alunoNome, 0, 160);
            } else {
                $tituloNovo = mb_substr(trim($mensagemUsuario), 0, 80);
                if ($tituloNovo === '') {
                    $tituloNovo = 'Nova conversa';
                }
            }
        } elseif ($alunoNome !== null && str_starts_with($tituloAtual, 'Aluno:') === false) {
            // Mantém título custom; só preenche aluno_id/nome
        }

        $this->db->query(
            'UPDATE assistente_conversas
             SET titulo = :titulo,
                 aluno_id = COALESCE(:aluno_id, aluno_id),
                 aluno_nome = COALESCE(:aluno_nome, aluno_nome),
                 updated_at = NOW()
             WHERE id = :id AND usuario_id = :uid AND deleted_at IS NULL',
            [
                'titulo' => mb_substr($tituloNovo, 0, 200),
                'aluno_id' => $alunoId,
                'aluno_nome' => $alunoNome,
                'id' => $conversaId,
                'uid' => $usuarioId,
            ]
        );
    }

    /**
     * Histórico no formato do LLM (últimas N mensagens).
     * Inclui resumo do painel (prova_id, jornada_id, aluno_id) para follow-ups.
     *
     * @return list<array{role:string,content:string}>
     */
    public function historicoParaLlm(int $usuarioId, int $conversaId, int $limite = 12): array
    {
        $conv = $this->obterConversa($usuarioId, $conversaId);
        if ($conv === null) {
            return [];
        }
        $out = [];
        $alunoIdConv = isset($conv['aluno_id']) ? (int) $conv['aluno_id'] : 0;
        $alunoNomeConv = trim((string) ($conv['aluno_nome'] ?? ''));
        if ($alunoIdConv > 0) {
            $out[] = [
                'role' => 'user',
                'content' => '[contexto_conversa] aluno_id=' . $alunoIdConv
                    . ($alunoNomeConv !== '' ? ' aluno_nome="' . $alunoNomeConv . '"' : '')
                    . ' — use este aluno_id nas tools sem pedir de novo.',
            ];
            $out[] = [
                'role' => 'assistant',
                'content' => 'Entendido. Vou consultar com aluno_id=' . $alunoIdConv . '.',
            ];
        }

        $msgs = array_slice($conv['mensagens'] ?? [], -$limite);
        foreach ($msgs as $m) {
            $role = (string) ($m['role'] ?? '');
            $content = trim((string) ($m['conteudo'] ?? ''));
            if ($role !== 'user' && $role !== 'assistant') {
                continue;
            }
            if ($role === 'assistant' && !empty($m['painel']) && is_array($m['painel'])) {
                $resumo = $this->resumoPainelParaLlm($m['painel']);
                if ($resumo !== '') {
                    $content = ($content !== '' ? $content . "\n\n" : '') . $resumo;
                }
            }
            if ($content === '') {
                continue;
            }
            $out[] = ['role' => $role, 'content' => mb_substr($content, 0, 4000)];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $painel
     */
    private function resumoPainelParaLlm(array $painel): string
    {
        $linhas = ['[contexto_estruturado]'];
        $aluno = is_array($painel['aluno'] ?? null) ? $painel['aluno'] : null;
        if ($aluno && !empty($aluno['id'])) {
            $linhas[] = 'aluno_id=' . (int) $aluno['id']
                . ' aluno_nome="' . trim((string) ($aluno['nome'] ?? '')) . '"';
        }

        $tipo = (string) ($painel['tipo'] ?? '');
        if ($tipo === 'lista' && !empty($painel['provas']) && is_array($painel['provas'])) {
            $linhas[] = 'provas_listadas:';
            foreach (array_slice($painel['provas'], 0, 20) as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $pid = (int) ($p['prova_id'] ?? 0);
                $erros = $p['realizacao']['erros'] ?? '—';
                $linhas[] = sprintf(
                    '- prova_id=%d titulo="%s" materia="%s" erros=%s',
                    $pid,
                    trim((string) ($p['titulo'] ?? '')),
                    trim((string) ($p['materia']['nome'] ?? '')),
                    (string) $erros
                );
            }
            $linhas[] = 'Para ver questões erradas: detalhar_prova_aluno com prova_id acima e somente_erros=true.';
        }

        if ($tipo === 'detalhe_prova' && !empty($painel['detalhe']) && is_array($painel['detalhe'])) {
            $d = $painel['detalhe'];
            $linhas[] = 'detalhe_prova prova_id=' . (int) ($d['prova_id'] ?? 0)
                . ' titulo="' . trim((string) ($d['titulo'] ?? '')) . '"'
                . ' questoes_no_painel=' . (int) ($d['total_questoes_detalhe'] ?? count($d['questoes'] ?? []));
        }

        if ($tipo === 'lista_jornadas' && !empty($painel['jornadas']) && is_array($painel['jornadas'])) {
            $linhas[] = 'jornadas_listadas:';
            foreach (array_slice($painel['jornadas'], 0, 20) as $j) {
                if (!is_array($j)) {
                    continue;
                }
                $linhas[] = sprintf(
                    '- jornada_id=%d titulo="%s"',
                    (int) ($j['jornada_id'] ?? 0),
                    trim((string) ($j['titulo'] ?? ''))
                );
            }
        }

        if ($tipo === 'detalhe_jornada' && !empty($painel['detalhe']) && is_array($painel['detalhe'])) {
            $d = $painel['detalhe'];
            $linhas[] = 'detalhe_jornada jornada_id=' . (int) ($d['jornada_id'] ?? 0)
                . ' titulo="' . trim((string) ($d['titulo'] ?? '')) . '"';
        }

        if ($tipo === 'resumo_professor' || $tipo === 'lista_provas_professor' || $tipo === 'saude_professor'
            || $tipo === 'detalhe_prova_professor' || $tipo === 'ranking_erros_professor') {
            $prof = is_array($painel['professor'] ?? null) ? $painel['professor'] : null;
            if ($prof && !empty($prof['id'])) {
                $linhas[] = 'professor_id=' . (int) $prof['id']
                    . ' professor_nome="' . trim((string) ($prof['nome'] ?? '')) . '"';
            }
        }
        if ($tipo === 'lista_provas_professor' && !empty($painel['provas']) && is_array($painel['provas'])) {
            $linhas[] = 'provas_do_professor:';
            foreach (array_slice($painel['provas'], 0, 15) as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $linhas[] = sprintf(
                    '- prova_id=%d titulo="%s" acertos=%s erros=%s',
                    (int) ($p['prova_id'] ?? 0),
                    trim((string) ($p['titulo'] ?? '')),
                    (string) ($p['acertos'] ?? '—'),
                    (string) ($p['erros'] ?? '—')
                );
            }
        }

        if (count($linhas) <= 1) {
            return '';
        }
        return implode("\n", $linhas);
    }

    public function pertenceAoUsuario(int $usuarioId, int $conversaId): bool
    {
        return $this->obterCabecalho($usuarioId, $conversaId) !== null;
    }

    /** @return array{id:int,titulo:string,aluno_id:?int,aluno_nome:?string}|null */
    private function obterCabecalho(int $usuarioId, int $conversaId): ?array
    {
        if ($usuarioId <= 0 || $conversaId <= 0 || !$this->tabelasProntas()) {
            return null;
        }
        $r = $this->db->fetch(
            'SELECT id, titulo, aluno_id, aluno_nome
             FROM assistente_conversas
             WHERE id = :id AND usuario_id = :uid AND deleted_at IS NULL
             LIMIT 1',
            ['id' => $conversaId, 'uid' => $usuarioId]
        );
        if (!$r) {
            return null;
        }
        return [
            'id' => (int) $r['id'],
            'titulo' => trim((string) ($r['titulo'] ?? 'Nova conversa')),
            'aluno_id' => isset($r['aluno_id']) ? (int) $r['aluno_id'] : null,
            'aluno_nome' => isset($r['aluno_nome']) ? trim((string) $r['aluno_nome']) : null,
        ];
    }
}
