<?php

namespace App\Modulos\Provas\Services\Assistente;

/**
 * Caminho único para descobrir QUAL aluno o usuário pediu.
 *
 * Ordem: RA válido (tem dígito) → nome explícito → aluno em foco na conversa.
 * Nome explícito nunca é trocado pelo aluno da conversa; vários resultados
 * viram candidatos para o usuário escolher.
 */
class AssistenteResolvedorAluno
{
    private const PRONOMES = [
        'ele', 'ela', 'dele', 'dela', 'esse', 'essa', 'este', 'esta', 'esse aluno', 'essa aluna',
        'este aluno', 'esta aluna', 'o aluno', 'a aluna', 'aluno', 'aluna', 'mesmo', 'mesma',
        'o mesmo', 'a mesma', 'ele mesmo', 'ela mesma',
    ];

    /** @var callable(string,?string):list<array<string,mixed>> */
    private $buscar;

    /**
     * @param callable(string,?string):list<array<string,mixed>> $buscar termo (ou "ra X") + turma opcional
     */
    public function __construct(callable $buscar)
    {
        $this->buscar = $buscar;
    }

    /** RA/matrícula precisa ter ao menos um dígito; nome nunca vira RA. */
    public static function raValido(string $ra): bool
    {
        $ra = trim($ra);
        return preg_match('/^[A-Za-z0-9]{3,20}$/', $ra) === 1 && preg_match('/\d/', $ra) === 1;
    }

    public static function normalizar(string $texto): string
    {
        $t = mb_strtolower(trim($texto));
        $t = strtr($t, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
            'í' => 'i', 'î' => 'i', 'ì' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ö' => 'o',
            'ú' => 'u', 'û' => 'u', 'ù' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);
        return trim((string) preg_replace('/\s+/', ' ', $t));
    }

    public static function ehPronome(string $nome): bool
    {
        return in_array(self::normalizar($nome), self::PRONOMES, true);
    }

    /**
     * @param array<string,mixed> $args aluno_nome, ra, aluno_da_conversa, turma
     * @param array<string,mixed>|null $alunoAtivo aluno em foco no estado da conversa
     * @return array{status:string,aluno?:array<string,mixed>,candidatos?:list<array<string,mixed>>,mensagem?:string,origem?:string}
     */
    public function resolver(array $args, ?array $alunoAtivo): array
    {
        $ra = trim((string) ($args['ra'] ?? ''));
        $nome = $this->limparNome((string) ($args['aluno_nome'] ?? ''));
        $turma = trim((string) ($args['turma'] ?? $args['turma_nome'] ?? ''));
        $daConversa = !empty($args['aluno_da_conversa']);

        if ($ra !== '' && !self::raValido($ra)) {
            if ($nome === '') {
                $nome = $this->limparNome($ra);
            }
            $ra = '';
        }
        if ($ra === '' && $nome !== '' && self::raValido($nome)) {
            $ra = $nome;
            $nome = '';
        }
        if ($nome !== '' && self::ehPronome($nome)) {
            $nome = '';
            $daConversa = true;
        }

        if ($ra !== '') {
            return $this->porRa($ra, $turma);
        }
        if ($nome !== '') {
            return $this->porNome($nome, $turma, $alunoAtivo);
        }
        if (is_array($alunoAtivo) && (int) ($alunoAtivo['id'] ?? 0) > 0) {
            return ['status' => 'ok', 'aluno' => $alunoAtivo, 'origem' => 'conversa'];
        }
        return [
            'status' => 'sem_aluno',
            'mensagem' => $daConversa
                ? 'Ainda não sei de qual aluno você está falando. Informe o nome ou o RA.'
                : 'De qual aluno? Informe o nome ou o RA.',
        ];
    }

    /** @return array<string,mixed> */
    private function porRa(string $ra, string $turma): array
    {
        $lista = ($this->buscar)('ra ' . $ra, $turma !== '' ? $turma : null);
        if ($lista === [] && $turma !== '') {
            $lista = ($this->buscar)('ra ' . $ra, null);
        }
        $exatos = array_values(array_filter(
            $lista,
            static fn(array $a) => strcasecmp(trim((string) ($a['ra'] ?? '')), $ra) === 0
        ));
        if ($exatos !== []) {
            $lista = $exatos;
        }
        if ($lista === []) {
            return ['status' => 'nenhum', 'mensagem' => 'Não encontrei aluno com RA ' . $ra . ' nesta escola.'];
        }
        if (count($lista) === 1) {
            return ['status' => 'ok', 'aluno' => $lista[0], 'origem' => 'ra'];
        }
        return [
            'status' => 'candidatos',
            'candidatos' => $lista,
            'mensagem' => 'Há mais de um aluno com RA ' . $ra . '. Escolha um:',
        ];
    }

    /**
     * @param array<string,mixed>|null $alunoAtivo
     * @return array<string,mixed>
     */
    private function porNome(string $nome, string $turma, ?array $alunoAtivo): array
    {
        $lista = ($this->buscar)($nome, $turma !== '' ? $turma : null);
        if ($lista === [] && $turma !== '') {
            $lista = ($this->buscar)($nome, null);
        }
        if ($lista === []) {
            return [
                'status' => 'nenhum',
                'mensagem' => 'Não encontrei aluno chamado "' . $nome . '"'
                    . ($turma !== '' ? ' na turma ' . $turma : '')
                    . '. Confira o nome ou informe o RA.',
            ];
        }
        if (count($lista) === 1) {
            return ['status' => 'ok', 'aluno' => $lista[0], 'origem' => 'nome'];
        }

        $alvo = self::normalizar($nome);
        $exatos = array_values(array_filter(
            $lista,
            static fn(array $a) => self::normalizar((string) ($a['nome'] ?? '')) === $alvo
        ));
        if (count($exatos) === 1) {
            return ['status' => 'ok', 'aluno' => $exatos[0], 'origem' => 'nome'];
        }

        // Nome parcial que bate com o aluno já em foco ("Maria" depois de falar de "Maria Clara").
        $idAtivo = is_array($alunoAtivo) ? (int) ($alunoAtivo['id'] ?? 0) : 0;
        if ($idAtivo > 0) {
            foreach ($lista as $a) {
                if ((int) ($a['id'] ?? 0) === $idAtivo) {
                    return ['status' => 'ok', 'aluno' => $a, 'origem' => 'nome_conversa'];
                }
            }
        }

        return [
            'status' => 'candidatos',
            'candidatos' => $lista,
            'mensagem' => 'Encontrei ' . count($lista) . ' alunos com "' . $nome . '". Qual deles?',
        ];
    }

    private function limparNome(string $nome): string
    {
        $n = trim($nome);
        $n = (string) preg_replace('/^(?:d[oa]s?\s+|(?:o|a)\s+)?(?:alun[oa]|estudante)\s+/iu', '', $n);
        $n = (string) preg_replace('/^(?:d[oa]|de)\s+/iu', '', $n);
        $n = trim($n, " \t\n\r\0\x0B.,;:!?\"'");
        return $n;
    }
}
