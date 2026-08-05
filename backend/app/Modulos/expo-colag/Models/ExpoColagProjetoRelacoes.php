<?php
/**
 * Relações N:N / 1:N do projeto Expo Colag (wizard S2).
 */

class ExpoColagProjetoRelacoes
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function carregarTudo(int $projetoId): array
    {
        return [
            'materias' => $this->db->fetchAll(
                'SELECT materia_id FROM expo_colag_projeto_materias WHERE projeto_id = :id',
                ['id' => $projetoId]
            ) ?: [],
            'professores' => $this->db->fetchAll(
                'SELECT professor_id FROM expo_colag_projeto_professores WHERE projeto_id = :id',
                ['id' => $projetoId]
            ) ?: [],
            'objetivos' => $this->db->fetchAll(
                'SELECT id, ordem, texto FROM expo_colag_projeto_objetivos WHERE projeto_id = :id ORDER BY ordem ASC',
                ['id' => $projetoId]
            ) ?: [],
            'tipos_trabalho' => $this->db->fetchAll(
                'SELECT id, tipo FROM expo_colag_projeto_tipos_trabalho WHERE projeto_id = :id',
                ['id' => $projetoId]
            ) ?: [],
            'papeis' => $this->db->fetchAll(
                'SELECT id, nome, descricao, vagas FROM expo_colag_projeto_papeis WHERE projeto_id = :id',
                ['id' => $projetoId]
            ) ?: [],
            'habilidades' => $this->db->fetchAll(
                'SELECT id, codigo_habilidade, habilidade_id FROM expo_colag_projeto_habilidades WHERE projeto_id = :id',
                ['id' => $projetoId]
            ) ?: [],
            'visibilidade' => $this->db->fetchAll(
                'SELECT id, escopo, referencia_id FROM expo_colag_projeto_visibilidade WHERE projeto_id = :id',
                ['id' => $projetoId]
            ) ?: [],
            'etapas' => $this->db->fetchAll(
                'SELECT id, ordem, titulo, descricao, data_limite, entregavel_esperado, status
                 FROM expo_colag_projeto_etapas WHERE projeto_id = :id ORDER BY ordem ASC',
                ['id' => $projetoId]
            ) ?: [],
            'encontros' => $this->db->fetchAll(
                'SELECT id, rotulo, data_hora, link, sala_id FROM expo_colag_projeto_encontros
                 WHERE projeto_id = :id ORDER BY data_hora ASC',
                ['id' => $projetoId]
            ) ?: [],
            'rubrica' => $this->db->fetchAll(
                'SELECT id, criterio, peso, descricao FROM expo_colag_projeto_rubrica WHERE projeto_id = :id',
                ['id' => $projetoId]
            ) ?: [],
            'materiais' => $this->db->fetchAll(
                'SELECT id, titulo, tipo, arquivo_url, link_externo, versao
                 FROM expo_colag_projeto_materiais WHERE projeto_id = :id ORDER BY id ASC',
                ['id' => $projetoId]
            ) ?: [],
        ];
    }

    public function sincronizarMaterias(int $projetoId, array $materiaIds): void
    {
        $this->db->query('DELETE FROM expo_colag_projeto_materias WHERE projeto_id = :id', ['id' => $projetoId]);
        foreach ($materiaIds as $mid) {
            $mid = (int) $mid;
            if ($mid <= 0) {
                continue;
            }
            $this->db->insert(
                'INSERT INTO expo_colag_projeto_materias (projeto_id, materia_id) VALUES (:pid, :mid)',
                ['pid' => $projetoId, 'mid' => $mid]
            );
        }
    }

    public function sincronizarProfessores(int $projetoId, array $professorIds): void
    {
        $this->db->query('DELETE FROM expo_colag_projeto_professores WHERE projeto_id = :id', ['id' => $projetoId]);
        foreach ($professorIds as $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) {
                continue;
            }
            $this->db->insert(
                'INSERT INTO expo_colag_projeto_professores (projeto_id, professor_id) VALUES (:proj, :prof)',
                ['proj' => $projetoId, 'prof' => $pid]
            );
        }
    }

    public function sincronizarObjetivos(int $projetoId, array $textos): void
    {
        $this->db->query('DELETE FROM expo_colag_projeto_objetivos WHERE projeto_id = :id', ['id' => $projetoId]);
        $ordem = 1;
        foreach ($textos as $texto) {
            $texto = trim((string) $texto);
            if ($texto === '') {
                continue;
            }
            $this->db->insert(
                'INSERT INTO expo_colag_projeto_objetivos (projeto_id, ordem, texto) VALUES (:pid, :ordem, :texto)',
                ['pid' => $projetoId, 'ordem' => $ordem++, 'texto' => $texto]
            );
        }
    }

    public function sincronizarTiposTrabalho(int $projetoId, array $tipos): void
    {
        $this->db->query('DELETE FROM expo_colag_projeto_tipos_trabalho WHERE projeto_id = :id', ['id' => $projetoId]);
        foreach ($tipos as $tipo) {
            $tipo = trim((string) $tipo);
            if ($tipo === '') {
                continue;
            }
            $this->db->insert(
                'INSERT INTO expo_colag_projeto_tipos_trabalho (projeto_id, tipo) VALUES (:pid, :tipo)',
                ['pid' => $projetoId, 'tipo' => mb_substr($tipo, 0, 120)]
            );
        }
    }

    /** @param list<array{nome:string,descricao?:string,vagas?:int}> $papeis */
    public function sincronizarPapeis(int $projetoId, array $papeis): void
    {
        $this->db->query('DELETE FROM expo_colag_projeto_papeis WHERE projeto_id = :id', ['id' => $projetoId]);
        foreach ($papeis as $papel) {
            $nome = trim((string) ($papel['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $this->db->insert(
                'INSERT INTO expo_colag_projeto_papeis (projeto_id, nome, descricao, vagas)
                 VALUES (:pid, :nome, :descricao, :vagas)',
                [
                    'pid' => $projetoId,
                    'nome' => mb_substr($nome, 0, 120),
                    'descricao' => trim((string) ($papel['descricao'] ?? '')) ?: null,
                    'vagas' => max(1, (int) ($papel['vagas'] ?? 1)),
                ]
            );
        }
    }

    /** @param list<array{codigo:string,habilidade_id?:int}> $habilidades */
    public function sincronizarHabilidades(int $projetoId, array $habilidades): void
    {
        $this->db->query('DELETE FROM expo_colag_projeto_habilidades WHERE projeto_id = :id', ['id' => $projetoId]);
        foreach ($habilidades as $h) {
            $codigo = trim((string) ($h['codigo'] ?? $h['codigo_habilidade'] ?? ''));
            if ($codigo === '') {
                continue;
            }
            $this->db->insert(
                'INSERT INTO expo_colag_projeto_habilidades (projeto_id, codigo_habilidade, habilidade_id)
                 VALUES (:pid, :codigo, :hid)',
                [
                    'pid' => $projetoId,
                    'codigo' => mb_substr($codigo, 0, 40),
                    'hid' => !empty($h['habilidade_id']) ? (int) $h['habilidade_id'] : null,
                ]
            );
        }
    }

    /** @param list<array{escopo:string,referencia_id:int}> $itens */
    public function sincronizarVisibilidade(int $projetoId, array $itens): void
    {
        $this->db->query('DELETE FROM expo_colag_projeto_visibilidade WHERE projeto_id = :id', ['id' => $projetoId]);
        $escopos = ['Serie', 'Turma', 'Aluno'];
        foreach ($itens as $item) {
            $escopo = (string) ($item['escopo'] ?? '');
            $ref = (int) ($item['referencia_id'] ?? 0);
            if (!in_array($escopo, $escopos, true) || $ref <= 0) {
                continue;
            }
            $this->db->insert(
                'INSERT INTO expo_colag_projeto_visibilidade (projeto_id, escopo, referencia_id)
                 VALUES (:pid, :escopo, :ref)',
                ['pid' => $projetoId, 'escopo' => $escopo, 'ref' => $ref]
            );
        }
    }

    /** @param list<array{ordem?:int,titulo:string,descricao?:string,data_limite?:string,entregavel_esperado?:string}> $etapas */
    public function sincronizarEtapas(int $projetoId, array $etapas): void
    {
        $this->db->query('DELETE FROM expo_colag_projeto_etapas WHERE projeto_id = :id', ['id' => $projetoId]);
        $ordem = 1;
        foreach ($etapas as $e) {
            $titulo = trim((string) ($e['titulo'] ?? ''));
            if ($titulo === '') {
                continue;
            }
            $this->db->insert(
                'INSERT INTO expo_colag_projeto_etapas
                    (projeto_id, ordem, titulo, descricao, data_limite, entregavel_esperado, status)
                 VALUES
                    (:pid, :ordem, :titulo, :descricao, :data_limite, :entregavel, \'Pendente\')',
                [
                    'pid' => $projetoId,
                    'ordem' => (int) ($e['ordem'] ?? $ordem),
                    'titulo' => mb_substr($titulo, 0, 255),
                    'descricao' => trim((string) ($e['descricao'] ?? '')) ?: null,
                    'data_limite' => $this->dataOuNull($e['data_limite'] ?? null),
                    'entregavel' => trim((string) ($e['entregavel_esperado'] ?? '')) ?: null,
                ]
            );
            $ordem++;
        }
    }

    /** @param list<array{rotulo:string,data_hora:string,link?:string}> $encontros */
    public function sincronizarEncontros(int $projetoId, array $encontros): void
    {
        $this->db->query('DELETE FROM expo_colag_projeto_encontros WHERE projeto_id = :id', ['id' => $projetoId]);
        foreach ($encontros as $e) {
            $rotulo = trim((string) ($e['rotulo'] ?? ''));
            $dataHora = trim((string) ($e['data_hora'] ?? ''));
            if ($rotulo === '' || $dataHora === '') {
                continue;
            }
            $dataHora = str_replace('T', ' ', $dataHora);
            if (strlen($dataHora) === 16) {
                $dataHora .= ':00';
            }
            $this->db->insert(
                'INSERT INTO expo_colag_projeto_encontros (projeto_id, rotulo, data_hora, link)
                 VALUES (:pid, :rotulo, :data_hora, :link)',
                [
                    'pid' => $projetoId,
                    'rotulo' => mb_substr($rotulo, 0, 180),
                    'data_hora' => $dataHora,
                    'link' => trim((string) ($e['link'] ?? '')) ?: null,
                ]
            );
        }
    }

    /** @param list<array{criterio:string,peso?:float,descricao?:string}> $itens */
    public function sincronizarRubrica(int $projetoId, array $itens): void
    {
        $this->db->query('DELETE FROM expo_colag_projeto_rubrica WHERE projeto_id = :id', ['id' => $projetoId]);
        foreach ($itens as $r) {
            $criterio = trim((string) ($r['criterio'] ?? ''));
            if ($criterio === '') {
                continue;
            }
            $this->db->insert(
                'INSERT INTO expo_colag_projeto_rubrica (projeto_id, criterio, peso, descricao)
                 VALUES (:pid, :criterio, :peso, :descricao)',
                [
                    'pid' => $projetoId,
                    'criterio' => mb_substr($criterio, 0, 180),
                    'peso' => (float) ($r['peso'] ?? 0),
                    'descricao' => trim((string) ($r['descricao'] ?? '')) ?: null,
                ]
            );
        }
    }

    /** @param list<array{titulo:string,tipo?:string,link_externo?:string,arquivo_url?:string}> $materiais */
    public function sincronizarMateriais(int $projetoId, array $materiais, ?int $enviadoPor = null): void
    {
        // Não apaga materiais adicionados na execução (origem=Execucao)
        $this->db->query(
            "DELETE FROM expo_colag_projeto_materiais WHERE projeto_id = :id AND origem = 'Wizard'",
            ['id' => $projetoId]
        );
        foreach ($materiais as $m) {
            $titulo = trim((string) ($m['titulo'] ?? ''));
            if ($titulo === '') {
                continue;
            }
            $this->db->insert(
                'INSERT INTO expo_colag_projeto_materiais
                    (projeto_id, titulo, tipo, arquivo_url, link_externo, enviado_por, versao, origem)
                 VALUES
                    (:pid, :titulo, :tipo, :arquivo_url, :link_externo, :enviado_por, 1, \'Wizard\')',
                [
                    'pid' => $projetoId,
                    'titulo' => mb_substr($titulo, 0, 255),
                    'tipo' => mb_substr(trim((string) ($m['tipo'] ?? 'link')), 0, 60) ?: 'link',
                    'arquivo_url' => trim((string) ($m['arquivo_url'] ?? '')) ?: null,
                    'link_externo' => trim((string) ($m['link_externo'] ?? '')) ?: null,
                    'enviado_por' => $enviadoPor,
                ]
            );
        }
    }

    private function dataOuNull($value): ?string
    {
        $v = trim((string) $value);
        if ($v === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }
        return $v;
    }
}
