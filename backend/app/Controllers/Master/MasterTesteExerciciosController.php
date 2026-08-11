<?php
/**
 * EducaTudo - Página de teste de geração de exercícios (somente admin master)
 * Usa o mesmo prompt da jornada; permite gerar imagem de contexto via API.
 * Protegido por sessão master (requireMaster) — evita abuso/custo da API de IA.
 */

if (!class_exists('MasterTesteExerciciosController')) {

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/Database.php';

class MasterTesteExerciciosController extends BaseController
{
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = \Database::getInstance();
    }

    /**
     * Exige sessão de admin master. Sem isso, esta página consumiria a API de IA
     * (custo/DoS) sem autenticação. $json=true responde 403 JSON (para o endpoint /gerar).
     */
    private function requireMaster(bool $json = false): void
    {
        if (!empty($_SESSION['master_user_id'])) {
            return;
        }
        if ($json) {
            $this->json(['success' => false, 'error' => 'Não autorizado.'], 403);
        } else {
            header('Location: ' . (defined('URL') ? URL : '') . '/master');
        }
        exit;
    }

    /**
     * GET /master/teste-exercicios - Página (somente admin master).
     */
    public function index()
    {
        $this->requireMaster();
        $prompt_default = $this->getPromptJornada();
        $data = [
            'title' => 'Teste de geração de exercícios (jornada)',
            'prompt_default' => $prompt_default,
            'url' => defined('URL') ? URL : '',
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->view('master/teste-exercicios', $data);
    }

    /**
     * POST /master/teste-exercicios/gerar - Gera exercícios (preview JSON, não persiste). Somente admin master.
     */
    public function gerar()
    {
        header('Content-Type: application/json; charset=utf-8');

        $this->requireMaster(true);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'error' => 'Método não permitido'], 405);
            return;
        }

        $input = $this->getPostOrJsonInput();

        if (!$this->verifyCsrfToken((string) ($input['_token'] ?? ''))) {
            $this->json(['success' => false, 'error' => 'Token CSRF inválido. Recarregue a página.'], 403);
            return;
        }
        $contexto = isset($input['contexto']) ? trim((string) $input['contexto']) : 'Contexto de teste';
        $tipoGrafico = isset($input['tipo_grafico']) ? trim((string) $input['tipo_grafico']) : '';
        $tipo = isset($input['tipo']) ? trim((string) $input['tipo']) : 'alternativas';
        $quantidade = isset($input['quantidade']) ? (int) $input['quantidade'] : 3;

        if ($quantidade < 1 || $quantidade > 10) {
            $quantidade = 3;
        }

        $contextoCompleto = "Matéria: Geral\n";
        $contextoCompleto .= "Tipo de exercício: {$tipo}\n";
        $contextoCompleto .= "Quantidade: {$quantidade} exercícios\n";
        $contextoCompleto .= "Contexto adicional: {$contexto}\n";
        if ($tipoGrafico !== '') {
            $contextoCompleto .= "Tipo de gráfico ou diagrama que o professor quer: {$tipoGrafico}\n";
        }

        try {
            require_once __DIR__ . '/../../Services/OpenAIService.php';
            $openaiService = new \App\Services\OpenAIService();
            // Padrão ENEM: enunciado + imagem (imagem_prompt) + pergunta + alternativas A-E
            $exerciciosGerados = $openaiService->gerarExerciciosEstiloENEM($contextoCompleto, $quantidade);
            $this->json([
                'success' => true,
                'exercicios' => $exerciciosGerados,
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function getPromptJornada(): string
    {
        try {
            $row = $this->db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                ['prompt_gerar_exercicios_jornada']
            );
            $v = trim($row['config_value'] ?? '');
            if ($v !== '') {
                return $v;
            }
        } catch (\Exception $e) {
            // fallback para default
        }
        return $this->getPromptJornadaDefault();
    }

    private function getPromptJornadaDefault(): string
    {
        return 'Você é um professor especialista em criar exercícios educacionais para uma plataforma que utiliza MathJax para renderização matemática.

TAREFA: Crie EXATAMENTE {quantidade} exercícios do tipo {tipoDescricao}.

CONTEXTO EDUCACIONAL:
{contextoCompleto}

REGRAS OBRIGATÓRIAS PARA FÓRMULAS MATEMÁTICAS (MathJax):
- Toda expressão matemática deve estar dentro de \\( ... \\) (inline) ou \\[ ... \\] (display).
- É proibido utilizar $...$.
- Nunca escreva LaTeX fora dos delimitadores.
- Utilize apenas comandos simples: \\frac, \\sqrt e expoentes.
- Não utilize ambientes complexos (align, cases, array).

FORMATO DE RESPOSTA (JSON OBRIGATÓRIO):
{
    "questoes": [
        {
            "titulo": "Título do exercício",
            "enunciado": "Pergunta ou problema (use \\( \\) ou \\[ \\] para fórmulas)",
            "alternativas": { "a": "...", "b": "...", "c": "...", "d": "...", "e": "..." },
            "resposta_correta": "a",
            "explicacao": "Explicação detalhada da resposta correta",
            "dificuldade": "fácil|médio|difícil"
        }
    ]
}

REGRAS OBRIGATÓRIAS:
1. Crie EXATAMENTE {quantidade} questões no array "questoes"
2. Cada questão deve ser única e diferente das outras
3. Varie a dificuldade entre as questões
4. Use linguagem clara e adequada ao nível educacional
5. As alternativas devem ser plausíveis e bem elaboradas
6. Para exercícios de múltipla escolha, inclua pelo menos 4 alternativas
7. Retorne APENAS o JSON válido, sem texto adicional antes ou depois
8. NÃO inclua markdown code blocks (```json), apenas o JSON puro
9. Em enunciado e alternativas, use \\( \\) ou \\[ \\] para fórmulas; nunca use $

IMPORTANTE: O array "questoes" deve conter EXATAMENTE {quantidade} objetos.';
    }

    private function getPostOrJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST;
    }
}

}
