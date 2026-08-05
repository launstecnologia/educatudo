<?php

namespace App\Services;

require_once __DIR__ . '/../Core/CreditosDecimalHelper.php';

/**
 * Vitrine EducaShop — categorias, enriquecimento visual e listagem de pacotes.
 */
class EducaShopService
{
  /** @var array<string, array{slug: string, label: string, descricao: string, gradiente: string, icone: string}> */
    public const CATEGORIAS = [
        'inicio' => [
            'slug' => 'inicio',
            'label' => 'Para começar',
            'descricao' => 'Pacotes ideais para experimentar os módulos com IA.',
            'gradiente' => 'from-sky-400 via-blue-500 to-indigo-600',
            'icone' => 'fa-coins',
        ],
        'intermediario' => [
            'slug' => 'intermediario',
            'label' => 'Mais TudiCoins',
            'descricao' => 'Mais saldo para usar Tudinha, flashcards e exercícios.',
            'gradiente' => 'from-violet-500 via-purple-600 to-fuchsia-600',
            'icone' => 'fa-bolt',
        ],
        'premium' => [
            'slug' => 'premium',
            'label' => 'Pacotes premium',
            'descricao' => 'Melhor custo-benefício para quem usa IA com frequência.',
            'gradiente' => 'from-amber-400 via-orange-500 to-rose-600',
            'icone' => 'fa-crown',
        ],
    ];

    private \Database $db;

    /** @var bool|null */
    private ?bool $colunasVitrineDetectadas = null;

    public function __construct(?\Database $db = null)
    {
        $this->db = $db ?? \Database::getInstance();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarPacotesVitrine(): array
    {
        $cols = $this->colunasSelectPacotes();
        $sql = "SELECT {$cols}
                FROM pacotes_creditos
                WHERE ativo = 1
                ORDER BY COALESCE(ordem, 9999) ASC, creditos ASC";
        try {
            $rows = $this->db->fetchAll($sql);
        } catch (\Throwable $e) {
            $rows = $this->db->fetchAll(
                'SELECT id, creditos, valor_centavos, nome, ativo
                 FROM pacotes_creditos WHERE ativo = 1 ORDER BY creditos ASC'
            );
        }

        $enriquecidos = [];
        foreach ($rows as $row) {
            $enriquecidos[] = $this->enriquecerPacote($row);
        }

        $melhorValorId = $this->resolverMelhorValorId($enriquecidos);
        foreach ($enriquecidos as &$pacote) {
            if ((int) ($pacote['id'] ?? 0) === $melhorValorId && empty($pacote['destaque'])) {
                $pacote['badge'] = 'Melhor custo-benefício';
            }
        }
        unset($pacote);

        return $enriquecidos;
    }

    /**
     * @param array<int, array<string, mixed>> $pacotes
     * @return array<string, array{label: string, descricao: string, pacotes: array<int, array<string, mixed>>}>
     */
    public function agruparPorCategoria(array $pacotes): array
    {
        $grupos = [];
        foreach (self::CATEGORIAS as $slug => $meta) {
            $grupos[$slug] = [
                'label' => $meta['label'],
                'descricao' => $meta['descricao'],
                'pacotes' => [],
            ];
        }
        foreach ($pacotes as $pacote) {
            $cat = (string) ($pacote['categoria_slug'] ?? 'inicio');
            if (!isset($grupos[$cat])) {
                $cat = 'inicio';
            }
            $grupos[$cat]['pacotes'][] = $pacote;
        }

        return array_filter($grupos, static fn (array $g): bool => !empty($g['pacotes']));
    }

    public function resolverCategoriaSlug(float $creditos): string
    {
        if ($creditos <= 30.0) {
            return 'inicio';
        }
        if ($creditos <= 100.0) {
            return 'intermediario';
        }

        return 'premium';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function enriquecerPacote(array $row): array
    {
        $creditos = \CreditosDecimalHelper::fromScalar($row['creditos'] ?? 0, 0.0);
        $valorCentavos = max(0, (int) ($row['valor_centavos'] ?? 0));
        $valorReais = $valorCentavos / 100;
        $categoriaSlug = trim((string) ($row['categoria'] ?? ''));
        if ($categoriaSlug === '' || !isset(self::CATEGORIAS[$categoriaSlug])) {
            $categoriaSlug = $this->resolverCategoriaSlug($creditos);
        }
        $catMeta = self::CATEGORIAS[$categoriaSlug];
        $precoPorCoin = $creditos > 0 ? round($valorReais / $creditos, 4) : 0.0;
        $descricao = trim((string) ($row['descricao'] ?? ''));
        if ($descricao === '') {
            $descricao = $this->descricaoPadrao($categoriaSlug, $creditos);
        }
        $nome = trim((string) ($row['nome'] ?? ''));
        if ($nome === '') {
            $nome = 'Pacote ' . \CreditosDecimalHelper::formatDisplay($creditos, false) . ' TudiCoins';
        }

        $destaque = !empty($row['destaque']);
        $badge = null;
        if ($destaque) {
            $badge = 'Destaque';
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'nome' => $nome,
            'descricao' => $descricao,
            'creditos' => $creditos,
            'creditos_display' => \CreditosDecimalHelper::formatDisplay($creditos),
            'valor_centavos' => $valorCentavos,
            'valor_reais' => $valorReais,
            'valor_reais_display' => 'R$ ' . number_format($valorReais, 2, ',', '.'),
            'preco_por_coin' => $precoPorCoin,
            'preco_por_coin_display' => 'R$ ' . number_format($precoPorCoin, 2, ',', '.') . ' / TudiCoin',
            'categoria_slug' => $categoriaSlug,
            'categoria_label' => $catMeta['label'],
            'gradiente' => $catMeta['gradiente'],
            'icone' => $catMeta['icone'],
            'imagem_url' => self::sanitizarImagemUrl(trim((string) ($row['imagem_url'] ?? ''))),
            'destaque' => $destaque,
            'badge' => $badge,
            'ordem' => (int) ($row['ordem'] ?? 0),
        ];
    }

    private function descricaoPadrao(string $categoriaSlug, float $creditos): string
    {
        $qtd = \CreditosDecimalHelper::formatDisplay($creditos, false);
        return match ($categoriaSlug) {
            'premium' => "{$qtd} TudiCoins com o melhor valor para uso intenso de IA na escola.",
            'intermediario' => "{$qtd} TudiCoins para acompanhar as aulas com mais tranquilidade.",
            default => "{$qtd} TudiCoins para começar a explorar os recursos com IA.",
        };
    }

    /**
     * @param array<int, array<string, mixed>> $pacotes
     */
    private function resolverMelhorValorId(array $pacotes): int
    {
        $melhorId = 0;
        $melhorPreco = null;
        foreach ($pacotes as $pacote) {
            $preco = (float) ($pacote['preco_por_coin'] ?? 0);
            if ($preco <= 0) {
                continue;
            }
            if ($melhorPreco === null || $preco < $melhorPreco) {
                $melhorPreco = $preco;
                $melhorId = (int) ($pacote['id'] ?? 0);
            }
        }

        return $melhorId;
    }

    private function colunasSelectPacotes(): string
    {
        if (!$this->temColunasVitrine()) {
            return 'id, creditos, valor_centavos, nome, ativo';
        }

        return 'id, creditos, valor_centavos, nome, ativo, categoria, descricao, imagem_url, destaque, ordem';
    }

    private function temColunasVitrine(): bool
    {
        if ($this->colunasVitrineDetectadas !== null) {
            return $this->colunasVitrineDetectadas;
        }
        try {
            $row = $this->db->fetch(
                "SELECT COUNT(*) AS c
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'pacotes_creditos'
                   AND COLUMN_NAME = 'categoria'"
            );
            $this->colunasVitrineDetectadas = ((int) ($row['c'] ?? 0)) > 0;
        } catch (\Throwable $e) {
            $this->colunasVitrineDetectadas = false;
        }

        return $this->colunasVitrineDetectadas;
    }

    public static function sanitizarImagemUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '/')) {
            return $url;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return '';
    }
}
