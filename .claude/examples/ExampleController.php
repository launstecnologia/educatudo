<?php
/**
 * EducaTudo - Example Controller (arquivo de referência de estilo — não é carregado pela aplicação)
 *
 * Padrões demonstrados: controller magro (coordena, não executa), validação de
 * ownership antes de retornar recurso, lógica de negócio delegada ao Service.
 */

class StudentExampleController
{
    private $exampleService;
    private $exampleItem;

    public function __construct()
    {
        $this->exampleService = new ExampleService();
        $this->exampleItem = new ExampleItem();
    }

    public function index()
    {
        $studentId = Auth::user()['id'];
        $items = $this->exampleItem->findByStudent($studentId);

        View::render('student/example/index', ['items' => $items]);
    }

    public function show($id)
    {
        $item = $this->exampleItem->findById($id);

        // Ownership: recurso de aluno só é servido ao próprio aluno
        if (!$item || (int) $item['student_id'] !== (int) Auth::user()['id']) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        View::render('student/example/show', ['item' => $item]);
    }

    public function store()
    {
        Csrf::validate();

        $result = $this->exampleService->createForStudent(Auth::user()['id'], $_POST);

        if (!$result['success']) {
            View::render('student/example/create', ['error' => $result['error']]);
            return;
        }

        header('Location: /example/' . $result['id']);
    }
}
