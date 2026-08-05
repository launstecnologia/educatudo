<?php

require_once __DIR__ . '/../Models/Exams/Exam.php';

if (!class_exists('AssessmentHasher')) {
/**
 * EducaInclui — calcula um hash determinístico do conteúdo de uma prova
 * (questões + alternativas + gabarito + pontuação) para detecção de "drift".
 *
 * Se o professor editar a prova original depois de uma versão adaptada ter sido
 * aprovada, o hash muda e a versão é invalidada (não pode ser entregue) até nova
 * geração/aprovação — evita entregar adaptação dessincronizada do original.
 */
class AssessmentHasher
{
    private $exam;

    public function __construct(?Exam $exam = null)
    {
        $this->exam = $exam ?: new Exam();
    }

    public function hashProva(int $provaId): string
    {
        if ($provaId <= 0) {
            return '';
        }

        $questoes = $this->exam->getQuestoes($provaId);
        $canonico = [];

        foreach ($questoes as $q) {
            $alts = [];
            if (($q['tipo'] ?? '') === 'multipla_escolha') {
                foreach ($this->exam->getAlternativas((int) $q['id']) as $a) {
                    $alts[] = [
                        'id' => (int) $a['id'],
                        'texto' => trim((string) ($a['texto'] ?? '')),
                        'correta' => (int) ($a['correta'] ?? 0),
                        'ordem' => (int) ($a['ordem'] ?? 0),
                    ];
                }
                usort($alts, static fn($x, $y) => $x['id'] <=> $y['id']);
            }
            $canonico[] = [
                'id' => (int) $q['id'],
                'enunciado' => trim((string) ($q['enunciado'] ?? '')),
                'tipo' => (string) ($q['tipo'] ?? ''),
                'valor' => (string) ($q['valor'] ?? ''),
                'alternativas' => $alts,
            ];
        }

        usort($canonico, static fn($x, $y) => $x['id'] <=> $y['id']);

        return hash('sha256', json_encode($canonico, JSON_UNESCAPED_UNICODE));
    }
}
}
