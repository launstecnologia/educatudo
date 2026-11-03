<?php

namespace App\Dominio\Escola\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Core\Database;
use App\Dominio\Escola\Services\GradeHorariaLeitura;

if (!class_exists(__NAMESPACE__ . '\\GradeHorariaConsultaController', false)) {
class GradeHorariaConsultaController extends BaseController
{
    private $auth;
    private $db;
    private GradeHorariaLeitura $leitura;

    /** @var array<int,string> */
    private static $DIAS_SEMANA = [
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $this->leitura = new GradeHorariaLeitura();
    }

    public function professor(): void
    {
        $usuario = $this->exigirProfessor();
        $professorId = (int) ($usuario['id'] ?? 0);
        $professor = $this->db->fetch(
            'SELECT id, nome FROM professores WHERE id = :id AND ativo = 1',
            ['id' => $professorId]
        );
        $filtros = $this->filtrosDeVisao();
        $filtros['professor_id'] = $professorId;
        $itens = $professor ? $this->leitura->listar($filtros) : [];

        $this->viewWithLayout('professor', 'admin/grade-horaria/consulta', $this->dadosConsulta(
            'professor',
            'Minha grade',
            'Horário, turma e sala de cada aula.',
            $filtros,
            $itens,
            $professor ? [$professor] : [],
            URL . '/professor/grade-horaria',
            $usuario
        ));
    }

    public function pdfProfessor(): void
    {
        $usuario = $this->exigirProfessor();
        $professorId = (int) ($usuario['id'] ?? 0);
        $professor = $this->db->fetch(
            'SELECT id, nome FROM professores WHERE id = :id AND ativo = 1',
            ['id' => $professorId]
        );
        $filtros = $this->filtrosDeVisao();
        $filtros['professor_id'] = $professorId;
        $filtros['visao'] = 'semana';
        $filtros['dia'] = 0;
        $itens = $professor ? $this->leitura->listar($filtros) : [];
        $nome = trim((string) ($professor['nome'] ?? 'Professor'));

        $html = $this->renderizarHtml('admin/grade-horaria/pdf-professores', [
            'grades_professores' => [[
                'id' => $professorId,
                'nome' => $nome,
                'itens' => $itens,
            ]],
            'filtros' => $filtros,
            'dias_semana' => self::$DIAS_SEMANA,
            'turmas' => [],
            'turmas_filtro' => [],
            'professores' => $professor ? [$professor] : [],
            'materias' => [],
            'gerado_em' => date('d/m/Y H:i'),
        ]);
        $this->outputPdf($html, 'minha-grade.pdf', 'landscape');
    }

    public function aluno(): void
    {
        $usuario = $this->exigirAluno();
        $aluno = $this->alunoLogado((int) ($usuario['id'] ?? 0));
        $turmaId = (int) ($aluno['turma_id'] ?? 0);
        $filtros = $this->filtrosDeVisao();
        $filtros['turma_id'] = $turmaId;
        $itens = ($aluno && $turmaId > 0) ? $this->leitura->listar($filtros) : [];
        $turmaNome = trim((string) ($aluno['turma_nome'] ?? ''));

        $this->viewWithLayout('student', 'admin/grade-horaria/consulta', $this->dadosConsulta(
            'aluno',
            $turmaNome !== '' ? $turmaNome : 'Grade horária',
            'Horário de aulas da sua turma.',
            $filtros,
            $itens,
            [],
            URL . '/grade-horaria',
            $usuario,
            $aluno ? [$aluno] : []
        ));
    }

    public function pdfAluno(): void
    {
        $usuario = $this->exigirAluno();
        $aluno = $this->alunoLogado((int) ($usuario['id'] ?? 0));
        $turmaId = (int) ($aluno['turma_id'] ?? 0);
        $filtros = $this->filtrosDeVisao();
        $filtros['turma_id'] = $turmaId;
        $itens = ($aluno && $turmaId > 0) ? $this->leitura->listar($filtros) : [];
        $turmas = $turmaId > 0 ? [[
            'id' => $turmaId,
            'nome' => (string) ($aluno['turma_nome'] ?? ''),
            'serie' => '',
            'tipo_ensino' => '',
        ]] : [];

        $html = $this->renderizarHtml('admin/grade-horaria/pdf', [
            'itens' => $itens,
            'filtros' => $filtros,
            'dias_semana' => self::$DIAS_SEMANA,
            'turmas' => $turmas,
            'turmas_filtro' => $turmas,
            'professores' => [],
            'materias' => [],
            'gerado_em' => date('d/m/Y H:i'),
            'pdfPaisagem' => ($filtros['visao'] ?? 'semana') !== 'dia',
        ]);
        $orientacao = (($filtros['visao'] ?? 'semana') === 'dia') ? 'portrait' : 'landscape';
        $this->outputPdf($html, 'grade-horaria.pdf', $orientacao);
    }

    /**
     * @return array<string,mixed>
     */
    private function exigirProfessor(): array
    {
        $usuario = $this->auth->getUser();
        if (!$usuario) {
            $this->redirect('/professor');
        }
        if (($usuario['tipo'] ?? '') !== 'professor') {
            $this->redirecionarParaPainel($usuario['tipo'] ?? null);
        }

        return $usuario;
    }

    /**
     * @return array<string,mixed>
     */
    private function exigirAluno(): array
    {
        $usuario = $this->auth->getUser();
        if (!$usuario) {
            $this->redirect('/');
        }
        if (($usuario['tipo'] ?? '') !== 'aluno') {
            $this->redirecionarParaPainel($usuario['tipo'] ?? null);
        }

        return $usuario;
    }

    private function redirecionarParaPainel(?string $tipo): void
    {
        switch ($tipo) {
            case 'aluno':
                $this->redirect('/dashboard');
                break;
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'monitor':
                $this->redirect('/monitor/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            case 'admin':
            case 'admin_escola':
                $this->redirect('/admin/dashboard');
                break;
            default:
                $this->redirect('/');
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function alunoLogado(int $alunoId): ?array
    {
        if ($alunoId <= 0) {
            return null;
        }
        $aluno = $this->db->fetch(
            'SELECT a.id, a.nome, a.turma_id, t.nome AS turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = :id',
            ['id' => $alunoId]
        );

        return $aluno ?: null;
    }

    /**
     * Visão e dia vêm da URL. Turma e professor são fixados pelo login.
     *
     * @return array{tipo_ensino:string,serie:string,turma_id:int,periodo:string,professor_id:int,materia_id:int,visao:string,dia:int}
     */
    private function filtrosDeVisao(): array
    {
        $visao = strtolower(trim((string) ($_GET['visao'] ?? 'semana')));
        $dia = (int) ($_GET['dia'] ?? 0);

        return [
            'tipo_ensino' => '',
            'serie' => '',
            'turma_id' => 0,
            'periodo' => '',
            'professor_id' => 0,
            'materia_id' => 0,
            'visao' => in_array($visao, ['semana', 'dia', 'lista'], true) ? $visao : 'semana',
            'dia' => ($dia >= 1 && $dia <= 7) ? $dia : 0,
        ];
    }

    /**
     * @param list<array<string,mixed>> $itens
     * @param list<array<string,mixed>> $professores
     * @param list<array<string,mixed>> $turmas
     * @param array<string,mixed> $usuario
     * @return array<string,mixed>
     */
    private function dadosConsulta(
        string $perfil,
        string $titulo,
        string $descricao,
        array $filtros,
        array $itens,
        array $professores,
        string $baseUrl,
        array $usuario,
        array $turmas = []
    ): array {
        return [
            'title' => $titulo . ' - EducaTudo',
            'user' => $usuario,
            'current_page' => 'grade_horaria',
            'perfil_consulta' => $perfil,
            'titulo_pagina' => $titulo,
            'descricao_pagina' => $descricao,
            'itens' => $itens,
            'filtros' => $filtros,
            'dias_semana' => self::$DIAS_SEMANA,
            'turmas' => $turmas,
            'turmas_filtro' => $turmas,
            'professores' => $professores,
            'materias' => [],
            'gradeBaseUrl' => $baseUrl,
            'csrf_token' => '',
        ];
    }

    private function renderizarHtml(string $view, array $viewData): string
    {
        $arquivo = $this->resolveViewPath($view);
        if ($arquivo === null) {
            throw new \Exception('View não encontrada: ' . $view);
        }
        ob_start();
        extract($viewData, EXTR_SKIP);
        require $arquivo;
        return (string) ob_get_clean();
    }

    private function outputPdf(string $html, string $filename, string $orientation = 'landscape'): void
    {
        $orientation = $orientation === 'portrait' ? 'portrait' : 'landscape';
        $old = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', $orientation);
            $dompdf->render();
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            echo $dompdf->output();
            exit;
        } finally {
            ini_set('display_errors', (string) $old);
        }
    }
}
}
