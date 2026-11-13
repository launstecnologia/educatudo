<?php

namespace App\Painel\Admin\Services;
/**
 * Persistência de conversas do Assistente (admin).
 */


use App\Core\Database;
class AssistenteHistoricoService
{
    private $db;
    private ?bool $estadoPronto = null;

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
        } catch (\Throwable $e) {
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
     * @return list<array{role:string,content:string,proposta_id?:int}>
     */
    public function historicoParaLlm(int $usuarioId, int $conversaId, int $limite = 12, bool $somenteTexto = false): array
    {
        $conv = $this->obterConversa($usuarioId, $conversaId);
        if ($conv === null) {
            return [];
        }
        $out = [];
        $alunoIdConv = isset($conv['aluno_id']) ? (int) $conv['aluno_id'] : 0;
        $alunoNomeConv = trim((string) ($conv['aluno_nome'] ?? ''));
        if ($alunoIdConv > 0 && !$somenteTexto) {
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
            if ($role === 'assistant' && $somenteTexto && !empty($m['painel']['tipo'])) {
                $alunoPainel = trim((string) ($m['painel']['aluno']['nome'] ?? ''));
                $content = trim($content . ' (painel exibido: ' . (string) $m['painel']['tipo']
                    . ($alunoPainel !== '' ? ' — ' . $alunoPainel : '') . ')');
            }
            if ($role === 'assistant' && !$somenteTexto && !empty($m['painel']) && is_array($m['painel'])) {
                $resumo = $this->resumoPainelParaLlm($m['painel']);
                if ($resumo !== '') {
                    $content = ($content !== '' ? $content . "\n\n" : '') . $resumo;
                }
            }
            if ($content === '') {
                continue;
            }
            $out[] = ['role' => $role, 'content' => mb_substr($content, 0, 4000)];
            if ($role === 'assistant' && ($m['painel']['tipo'] ?? '') === 'proposta') {
                $out[array_key_last($out)]['proposta_id'] = (int) ($m['painel']['proposta_id'] ?? 0);
            }
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
                . ' aluno_nome="' . trim((string) ($aluno['nome'] ?? '')) . '"'
                . (!empty($aluno['ra']) ? ' ra="' . trim((string) $aluno['ra']) . '"' : '')
                . (!empty($aluno['turma_nome']) ? ' turma_nome="' . trim((string) $aluno['turma_nome']) . '"' : '');
        }

        $tipo = (string) ($painel['tipo'] ?? '');

        // Contexto de consultas (bimestre/ano/turma/coluna/matéria) para follow-ups.
        if (!empty($painel['bimestre'])) {
            $linhas[] = 'bimestre=' . (int) $painel['bimestre'];
        }
        if (!empty($painel['ano_letivo'])) {
            $linhas[] = 'ano_letivo=' . (int) $painel['ano_letivo'];
        }
        if (!empty($painel['coluna'])) {
            $linhas[] = 'coluna="' . trim((string) $painel['coluna']) . '"';
        }
        if (!empty($painel['materia'])) {
            $linhas[] = 'materia="' . trim((string) $painel['materia']) . '"';
        }
        if ($tipo === 'boletim_aluno' || $tipo === 'notas_evento_aluno' || !empty($painel['bimestre'])) {
            $linhas[] = 'ultima_consulta=nota_boletim';
        }
        if ($tipo === 'grade_horaria_consulta') {
            $escopo = is_array($painel['escopo'] ?? null) ? $painel['escopo'] : [];
            $turmaGrade = trim((string) ($escopo['turma_nome'] ?? $escopo['nome'] ?? $painel['turma_nome'] ?? ''));
            if ($turmaGrade !== '') {
                $linhas[] = 'turma_nome="' . $turmaGrade . '"';
            }
            $dataGrade = trim((string) ($painel['data'] ?? $escopo['data'] ?? ''));
            if ($dataGrade !== '') {
                $linhas[] = 'data_grade="' . $dataGrade . '"';
            }
            $linhas[] = 'ultima_consulta=grade_horaria';
        }
        if ($tipo === 'proposta') {
            $payload = is_array($painel['payload'] ?? null) ? $painel['payload'] : [];
            $tipoProp = trim((string) ($painel['proposta_tipo'] ?? $painel['acao'] ?? $payload['tipo'] ?? $painel['tipo_acao'] ?? ''));
            $linhas[] = 'proposta_pendente=1'
                . ($tipoProp !== '' ? ' proposta_tipo="' . $tipoProp . '"' : '');
            if (!empty($payload['titulo'])) {
                $linhas[] = 'ocorrencia_titulo="' . $this->escaparValorContexto((string) $payload['titulo'], 120) . '"';
            }
            if (!empty($payload['detalhe'])) {
                $linhas[] = 'ocorrencia_detalhe="' . $this->escaparValorContexto((string) $payload['detalhe'], 500) . '"';
            }
            if (!empty($payload['turma'])) {
                $linhas[] = 'turma_nome="' . trim((string) $payload['turma']) . '"';
            }
            $alunosProp = is_array($payload['alunos'] ?? null) ? $payload['alunos'] : [];
            if ($alunosProp !== [] && empty($aluno['id'])) {
                $a0 = $alunosProp[0];
                if (is_array($a0) && !empty($a0['id'])) {
                    $linhas[] = 'aluno_id=' . (int) $a0['id']
                        . ' aluno_nome="' . trim((string) ($a0['nome'] ?? '')) . '"'
                        . (!empty($a0['ra']) ? ' ra="' . trim((string) $a0['ra']) . '"' : '')
                        . (!empty($a0['turma_nome']) ? ' turma_nome="' . trim((string) $a0['turma_nome']) . '"' : '');
                }
            }
        }
        if ($tipo === 'candidatos_categorias' || $tipo === 'coleta_ocorrencia') {
            $linhas[] = 'coleta_ocorrencia=1 aguardando_categoria=1 proposta_pendente=1 proposta_tipo="criar_ocorrencia"';
            $payloadColeta = is_array($painel['payload'] ?? null) ? $painel['payload'] : [];
            if (!empty($payloadColeta['categoria'])) {
                $linhas[] = 'ocorrencia_categoria="' . $this->escaparValorContexto((string) $payloadColeta['categoria'], 80) . '"';
            }
            if (!empty($payloadColeta['titulo'])) {
                $linhas[] = 'ocorrencia_titulo="' . $this->escaparValorContexto((string) $payloadColeta['titulo'], 120) . '"';
            }
            if (!empty($payloadColeta['detalhe'])) {
                $linhas[] = 'ocorrencia_detalhe="' . $this->escaparValorContexto((string) $payloadColeta['detalhe'], 500) . '"';
            }
            if (!empty($payloadColeta['alunos']) && is_array($payloadColeta['alunos'])) {
                $nomesExtra = [];
                foreach ($payloadColeta['alunos'] as $aExtra) {
                    if (is_string($aExtra) && trim($aExtra) !== '') {
                        $nomesExtra[] = trim($aExtra);
                    } elseif (is_array($aExtra) && !empty($aExtra['nome'])) {
                        $nomesExtra[] = trim((string) $aExtra['nome']);
                    }
                }
                if ($nomesExtra !== []) {
                    $linhas[] = 'ocorrencia_envolvidos="' . $this->escaparValorContexto(implode(' | ', $nomesExtra), 200) . '"';
                }
            }
            if (!empty($payloadColeta['nivel_gravidade_explicito']) && !empty($payloadColeta['nivel_gravidade'])) {
                $linhas[] = 'ocorrencia_nivel=' . trim((string) $payloadColeta['nivel_gravidade']);
            }
            if (!empty($payloadColeta['enviar_pais_explicito'])) {
                $linhas[] = 'ocorrencia_pais=' . (!empty($payloadColeta['enviar_pais']) ? '1' : '0');
            }
            if (!empty($payloadColeta['envolvidos_ok'])) {
                $linhas[] = 'ocorrencia_envolvidos_ok=1';
            }
            if (!empty($painel['aguardando_campo'])) {
                $linhas[] = 'aguardando_campo="' . trim((string) $painel['aguardando_campo']) . '"';
            }
            $alunoColeta = is_array($painel['aluno'] ?? null) ? $painel['aluno'] : null;
            if ($alunoColeta && !empty($alunoColeta['id']) && empty($aluno['id'])) {
                $linhas[] = 'aluno_id=' . (int) $alunoColeta['id']
                    . ' aluno_nome="' . trim((string) ($alunoColeta['nome'] ?? '')) . '"'
                    . (!empty($alunoColeta['ra']) ? ' ra="' . trim((string) $alunoColeta['ra']) . '"' : '')
                    . (!empty($alunoColeta['turma_nome']) ? ' turma_nome="' . trim((string) $alunoColeta['turma_nome']) . '"' : '');
            }
        }

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

    /** Coluna estado_json (migration 2026_11_13_assistente_estado_conversa). */
    public function estadoPronto(): bool
    {
        if ($this->estadoPronto !== null) {
            return $this->estadoPronto;
        }
        try {
            $r = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1',
                ['t' => 'assistente_conversas', 'c' => 'estado_json']
            );
            $this->estadoPronto = !empty($r);
        } catch (\Throwable $e) {
            $this->estadoPronto = false;
        }
        return $this->estadoPronto;
    }

    /** @return array<string,mixed> */
    public function obterEstado(int $usuarioId, int $conversaId): array
    {
        if ($usuarioId <= 0 || $conversaId <= 0 || !$this->tabelasProntas() || !$this->estadoPronto()) {
            return [];
        }
        $r = $this->db->fetch(
            'SELECT estado_json FROM assistente_conversas
             WHERE id = :id AND usuario_id = :uid AND deleted_at IS NULL
             LIMIT 1',
            ['id' => $conversaId, 'uid' => $usuarioId]
        );
        if (!$r || empty($r['estado_json'])) {
            return [];
        }
        $dec = json_decode((string) $r['estado_json'], true);
        return is_array($dec) ? $dec : [];
    }

    /** @param array<string,mixed> $estado */
    public function salvarEstado(int $usuarioId, int $conversaId, array $estado): bool
    {
        if ($usuarioId <= 0 || $conversaId <= 0 || !$this->tabelasProntas() || !$this->estadoPronto()) {
            return false;
        }
        $json = json_encode($estado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || strlen($json) > 60000) {
            return false;
        }
        $this->db->query(
            'UPDATE assistente_conversas SET estado_json = :estado
             WHERE id = :id AND usuario_id = :uid AND deleted_at IS NULL',
            ['estado' => $json, 'id' => $conversaId, 'uid' => $usuarioId]
        );
        return true;
    }

    /** Escapa aspas/quebras para tokens de contexto no histórico (regex "([^"]+)"). */
    private function escaparValorContexto(string $valor, int $maxLen = 200): string
    {
        $v = trim($valor);
        $v = str_replace(["\r", "\n", '"'], [' ', ' ', "'"], $v);
        $v = preg_replace('/\s+/u', ' ', $v) ?? $v;
        if (mb_strlen($v) > $maxLen) {
            $v = mb_substr($v, 0, $maxLen);
        }
        return $v;
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

if (!class_exists('AssistenteHistoricoService', false) && class_exists(__NAMESPACE__ . '\\AssistenteHistoricoService', false)) {
    class_alias(__NAMESPACE__ . '\\AssistenteHistoricoService', 'AssistenteHistoricoService');
}
