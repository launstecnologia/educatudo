<?php

require_once __DIR__ . '/../Core/Database.php';

/**
 * Agenda mensal do aluno — provas, jornadas, redação, eventos escolares,
 * aulas online e itens pessoais. Usado no dashboard e na página /agenda.
 */
class StudentAgendaService
{
    /** @var Database */
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMonthEvents(int $alunoId, int $turmaId, int $ano, int $mes): array
    {
        $mes = max(1, min(12, $mes));
        $ano = max(2000, min(2100, $ano));
        $inicio = sprintf('%04d-%02d-01', $ano, $mes);
        $fim = date('Y-m-t', strtotime($inicio));

        $eventos = array_merge(
            $this->eventosProvas($alunoId, $turmaId, $inicio, $fim),
            $this->eventosJornadas($turmaId, $inicio, $fim),
            $this->eventosRedacao($alunoId, $turmaId, $inicio, $fim),
            $this->eventosEscola($ano, $inicio, $fim),
            $this->eventosAulasOnline($alunoId, $turmaId, $inicio, $fim),
            $this->eventosPessoais($alunoId, $inicio, $fim)
        );

        usort($eventos, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['data'] ?? ''), (string) ($b['data'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string) ($a['hora'] ?? ''), (string) ($b['hora'] ?? ''));
        });

        return $eventos;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizarEvento(
        string $tipo,
        int $id,
        string $titulo,
        string $data,
        ?string $hora,
        ?string $descricao,
        ?string $inicioEm,
        ?string $fimEm,
        ?string $link,
        string $cor,
        string $icone
    ): array {
        return [
            'id' => $id,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'descricao' => $descricao ?? '',
            'inicio_em' => $inicioEm,
            'fim_em' => $fimEm,
            'data' => $data,
            'hora' => $hora !== null && $hora !== '' ? substr($hora, 0, 5) : '',
            'link' => $link,
            'url' => $link,
            'cor' => $cor,
            'icone' => $icone,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eventosProvas(int $alunoId, int $turmaId, string $inicio, string $fim): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT p.id, p.titulo,
                        COALESCE(p.data_prova, DATE(p.data_inicio)) AS data_evento,
                        TIME(p.data_inicio) AS hora_evento
                 FROM provas p
                 LEFT JOIN provas_turmas pt ON p.id = pt.prova_id
                 WHERE p.liberada = 1
                   AND p.ativo = 1
                   AND p.deleted_at IS NULL
                   AND COALESCE(p.data_prova, DATE(p.data_inicio)) BETWEEN :inicio AND :fim
                   AND (
                       p.turma_id = :turma_id_where
                       OR pt.turma_id = :turma_id_pt
                       OR (p.turma_id IS NULL AND NOT EXISTS (
                           SELECT 1 FROM provas_turmas pt2 WHERE pt2.prova_id = p.id
                       ))
                   )
                 ORDER BY data_evento ASC",
                [
                    'inicio' => $inicio,
                    'fim' => $fim,
                    'turma_id_where' => $turmaId,
                    'turma_id_pt' => $turmaId,
                ]
            );
        } catch (Throwable $e) {
            error_log('StudentAgendaService::eventosProvas: ' . $e->getMessage());
            return [];
        }

        $eventos = [];
        foreach ($rows as $r) {
            $data = (string) ($r['data_evento'] ?? '');
            $hora = (string) ($r['hora_evento'] ?? '');
            $eventos[] = $this->normalizarEvento(
                'prova',
                (int) ($r['id'] ?? 0),
                (string) ($r['titulo'] ?? 'Prova'),
                $data,
                $hora,
                null,
                $data . ($hora !== '' && $hora !== '00:00:00' ? ' ' . $hora : ' 00:00:00'),
                null,
                // Não existe rota de "detalhe" de uma prova específica pro aluno (era
                // /prova/{id}, que nunca existiu — sempre deu 404). A lista já mostra
                // cada prova no estado certo (disponível, aguardando, resultado).
                URL . '/aluno/provas',
                'red',
                'clipboard'
            );
        }
        return $eventos;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eventosJornadas(int $turmaId, string $inicio, string $fim): array
    {
        if ($turmaId <= 0) {
            return [];
        }
        try {
            // jornadas não tem coluna data_fim (o prazo mora dentro do JSON de `estrutura`,
            // extraído logo abaixo) — essa query pedia a coluna direto e sempre quebrava
            // (SQLSTATE 42S22 Column not found), caindo no catch: eventos de Jornada nunca
            // apareciam na Agenda, silenciosamente.
            $rows = $this->db->fetchAll(
                "SELECT id, titulo, turma_id, estrutura
                 FROM jornadas
                 WHERE (turma_id = :turma_id OR (estrutura IS NOT NULL AND estrutura != ''))
                   AND (ativo = 1 OR ativo IS NULL)",
                ['turma_id' => $turmaId]
            );
        } catch (Throwable $e) {
            error_log('StudentAgendaService::eventosJornadas: ' . $e->getMessage());
            return [];
        }

        $eventos = [];
        foreach ($rows as $r) {
            if ((int) ($r['turma_id'] ?? 0) !== $turmaId) {
                $estruturaCheck = json_decode((string) ($r['estrutura'] ?? ''), true);
                $turmasSel = is_array($estruturaCheck) ? ($estruturaCheck['turmas_selecionadas'] ?? []) : [];
                if (!is_array($turmasSel) || !in_array($turmaId, array_map('intval', $turmasSel), true)) {
                    continue;
                }
            }
            $estrutura = json_decode((string) ($r['estrutura'] ?? ''), true);
            $dataFim = is_array($estrutura) ? ($estrutura['data_fim'] ?? null) : null;
            if (empty($dataFim) || $dataFim < $inicio || $dataFim > $fim) {
                continue;
            }
            $horaFim = is_array($estrutura) ? trim((string) ($estrutura['hora_fim'] ?? '')) : '';
            $eventos[] = $this->normalizarEvento(
                'jornada',
                (int) ($r['id'] ?? 0),
                (string) ($r['titulo'] ?? 'Jornada'),
                (string) $dataFim,
                $horaFim !== '' ? $horaFim : null,
                'Prazo da jornada',
                null,
                $dataFim . ($horaFim !== '' ? ' ' . $horaFim : ' 23:59:59'),
                URL . '/jornadas/' . (int) ($r['id'] ?? 0),
                'blue',
                'book'
            );
        }
        return $eventos;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eventosRedacao(int $alunoId, int $turmaId, string $inicio, string $fim): array
    {
        try {
            if (!class_exists('EssayProposal')) {
                require_once __DIR__ . '/../Models/Essays/EssayProposal.php';
            }
            // Coluna ativo só existe após a migration 2026_07_14_redacoes_orientadas_propostas_ativo.
            $filtroAtivo = (new EssayProposal())->sqlFiltroPropostaAtiva('p');
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT p.id, p.title, DATE(p.ends_at) AS data_evento, p.ends_at
                 FROM redacoes_orientadas_propostas p
                 WHERE p.status = 'published'
                   {$filtroAtivo}
                   AND p.ends_at IS NOT NULL
                   AND DATE(p.ends_at) BETWEEN :inicio AND :fim
                   AND (
                       EXISTS (
                           SELECT 1 FROM redacoes_orientadas_propostas_alunos ps
                           WHERE ps.proposal_id = p.id AND ps.student_id = :aluno_id
                       )
                       OR (
                           NOT EXISTS (SELECT 1 FROM redacoes_orientadas_propostas_alunos ps2 WHERE ps2.proposal_id = p.id)
                           AND (
                               EXISTS (
                                   SELECT 1 FROM redacoes_orientadas_propostas_turmas pt
                                   WHERE pt.proposal_id = p.id AND pt.turma_id = :turma_id
                               )
                               OR EXISTS (
                                   SELECT 1 FROM redacoes_orientadas_propostas_turmas pt2
                                   JOIN matricula m ON m.turma_id = pt2.turma_id AND m.status = 'ativa'
                                   WHERE pt2.proposal_id = p.id AND m.aluno_id = :aluno_id2
                               )
                           )
                       )
                   )
                 ORDER BY data_evento ASC",
                [
                    'inicio' => $inicio,
                    'fim' => $fim,
                    'aluno_id' => $alunoId,
                    'turma_id' => $turmaId,
                    'aluno_id2' => $alunoId,
                ]
            );
        } catch (Throwable $e) {
            error_log('StudentAgendaService::eventosRedacao: ' . $e->getMessage());
            return [];
        }

        $eventos = [];
        foreach ($rows as $r) {
            $data = (string) ($r['data_evento'] ?? '');
            $endsAt = (string) ($r['ends_at'] ?? '');
            $eventos[] = $this->normalizarEvento(
                'redacao',
                (int) ($r['id'] ?? 0),
                (string) ($r['title'] ?? 'Redação'),
                $data,
                strlen($endsAt) >= 16 ? substr($endsAt, 11, 5) : null,
                'Prazo de entrega',
                $endsAt !== '' ? $endsAt : null,
                null,
                // /redacao/proposta/{id} nunca existiu como rota (sempre 404) — a proposta
                // é da Jornada da Redação (tabela redacoes_orientadas_propostas), cuja rota
                // real é /jornada-redacao/{id} (StudentEssayController@show).
                URL . '/jornada-redacao/' . (int) ($r['id'] ?? 0),
                'purple',
                'pencil'
            );
        }
        return $eventos;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eventosEscola(int $ano, string $inicio, string $fim): array
    {
        try {
            require_once __DIR__ . '/SchoolCalendarService.php';
            $service = new SchoolCalendarService($this->db);
            $cfg = $service->getAno($ano);
            if (!$cfg) {
                return [];
            }
            $todos = $service->eventos((int) $cfg['id']);
        } catch (Throwable $e) {
            error_log('StudentAgendaService::eventosEscola: ' . $e->getMessage());
            return [];
        }

        $eventos = [];
        foreach ($todos as $ev) {
            if ((int) ($ev['visivel_aluno'] ?? 0) !== 1) {
                continue;
            }
            $data = (string) ($ev['data_inicio'] ?? '');
            if ($data === '' || $data < $inicio || $data > $fim) {
                continue;
            }
            $eventos[] = $this->normalizarEvento(
                'escola',
                (int) ($ev['id'] ?? 0),
                (string) ($ev['descricao'] ?? 'Evento escolar'),
                $data,
                null,
                (string) ($ev['observacao'] ?? ''),
                $data . ' 00:00:00',
                null,
                null,
                'green',
                'calendar'
            );
        }
        return $eventos;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eventosAulasOnline(int $alunoId, int $turmaId, string $inicio, string $fim): array
    {
        if ($alunoId <= 0 || $turmaId <= 0) {
            return [];
        }
        try {
            $rows = $this->db->fetchAll(
                "SELECT ao.id, ao.titulo, ao.inicio_em, ao.fim_em
                 FROM aulas_online ao
                 WHERE ao.ativo = 1
                   AND ao.publicado = 1
                   AND DATE(ao.inicio_em) BETWEEN :inicio AND :fim
                   AND (
                       ao.enviar_para_todos = 1
                       OR EXISTS (
                           SELECT 1 FROM aulas_online_turmas aot
                           WHERE aot.aula_online_id = ao.id AND aot.turma_id = :turma_id
                       )
                   )
                 ORDER BY ao.inicio_em ASC",
                ['inicio' => $inicio, 'fim' => $fim, 'turma_id' => $turmaId]
            );
        } catch (Throwable $e) {
            error_log('StudentAgendaService::eventosAulasOnline: ' . $e->getMessage());
            return [];
        }

        $eventos = [];
        foreach ($rows as $r) {
            $inicioEm = (string) ($r['inicio_em'] ?? '');
            $data = $inicioEm !== '' ? substr($inicioEm, 0, 10) : '';
            if ($data === '') {
                continue;
            }
            $eventos[] = $this->normalizarEvento(
                'aula_online',
                (int) ($r['id'] ?? 0),
                (string) ($r['titulo'] ?? 'Aula online'),
                $data,
                substr($inicioEm, 11, 5),
                null,
                $inicioEm,
                !empty($r['fim_em']) ? (string) $r['fim_em'] : null,
                URL . '/aluno/aulas-online',
                'teal',
                'video'
            );
        }
        return $eventos;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eventosPessoais(int $alunoId, string $inicio, string $fim): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT id, titulo, tipo, descricao, data, hora, banca, local, link
                 FROM aluno_agenda_itens
                 WHERE aluno_id = :aluno_id AND data BETWEEN :inicio AND :fim
                 ORDER BY data ASC, hora ASC",
                ['aluno_id' => $alunoId, 'inicio' => $inicio, 'fim' => $fim]
            );
        } catch (Throwable $e) {
            error_log('StudentAgendaService::eventosPessoais: ' . $e->getMessage());
            return [];
        }

        if (!class_exists('AgendaController')) {
            require_once __DIR__ . '/../Controllers/Student/AgendaController.php';
        }
        $tiposPessoal = \AgendaController::tiposItemPessoal();

        $eventos = [];
        foreach ($rows as $r) {
            $tipo = (string) ($r['tipo'] ?? 'pessoal');
            if (!array_key_exists($tipo, $tiposPessoal)) {
                $tipo = 'pessoal';
            }
            $data = (string) ($r['data'] ?? '');
            $hora = (string) ($r['hora'] ?? '');
            $link = trim((string) ($r['link'] ?? ''));
            $eventos[] = $this->normalizarEvento(
                'pessoal',
                (int) ($r['id'] ?? 0),
                (string) ($r['titulo'] ?? 'Compromisso'),
                $data,
                $hora !== '' ? $hora : null,
                (string) ($r['descricao'] ?? ''),
                $data . ($hora !== '' ? ' ' . $hora : ' 00:00:00'),
                null,
                $link !== '' ? $link : null,
                $tiposPessoal[$tipo]['cor'] ?? '#eab308',
                'star'
            );
            $ultimo = array_key_last($eventos);
            $eventos[$ultimo]['tipo_pessoal'] = $tipo;
            $eventos[$ultimo]['tipo_pessoal_label'] = $tiposPessoal[$tipo]['label'] ?? 'Pessoal';
            $eventos[$ultimo]['banca'] = (string) ($r['banca'] ?? '');
            $eventos[$ultimo]['local'] = (string) ($r['local'] ?? '');
        }
        return $eventos;
    }
}
