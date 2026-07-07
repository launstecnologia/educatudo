<?php
/**
 * EducaTudo - Example Service (arquivo de referência de estilo — não é carregado pela aplicação)
 *
 * Padrões demonstrados: lógica de negócio fora do controller, retorno padronizado
 * ['success' => bool, ...], trabalho de IA delegado a job assíncrono.
 */

class ExampleService
{
    private $exampleItem;

    public function __construct()
    {
        $this->exampleItem = new ExampleItem();
    }

    public function createForStudent($studentId, array $input)
    {
        $title = trim($input['title'] ?? '');
        if ($title === '') {
            return ['success' => false, 'error' => 'Título é obrigatório.'];
        }

        $id = $this->exampleItem->create([
            'student_id' => $studentId,
            'title' => $title,
        ]);

        // Chamada de IA nunca é síncrona na request: enfileira job
        AIJobService::enqueue('example_analysis', ['item_id' => $id]);

        Logger::info('example_item_created', ['item_id' => $id, 'student_id' => $studentId]);

        return ['success' => true, 'id' => $id];
    }
}
