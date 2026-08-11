<?php
/**
 * Serviço de ingestão de notícias via RSS (G1 e outros).
 * Extrai título, link, descrição, imagem (do HTML da descrição) e evita duplicados por link.
 */
require_once __DIR__ . '/../Core/Database.php';

class RssService
{
    private $db;
    private $timeout = 15;
    private $feeds = [
        'educacao' => 'https://g1.globo.com/dynamo/educacao/rss2.xml',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Define feeds (útil para testes ou adicionar outros)
     */
    public function setFeeds(array $feeds)
    {
        $this->feeds = $feeds;
    }

    /**
     * Executa a ingestão de todos os feeds configurados.
     * Um feed falhando não interrompe os demais.
     */
    public function importarTodos()
    {
        $totalInseridas = 0;
        $erros = [];

        foreach ($this->feeds as $categoria => $url) {
            try {
                $inseridas = $this->importarFeed($url, $categoria, 'G1');
                $totalInseridas += $inseridas;
            } catch (Exception $e) {
                $erros[$categoria] = $e->getMessage();
                $this->logErro('RSS', "Feed {$categoria}: " . $e->getMessage());
            }
        }

        return [
            'inseridas' => $totalInseridas,
            'erros' => $erros,
        ];
    }

    /**
     * Importa um único feed e retorna quantidade de itens inseridos.
     */
    public function importarFeed($url, $categoria, $fonte = 'G1')
    {
        $xml = $this->carregarFeed($url);
        if ($xml === false || $xml === null) {
            throw new Exception('Não foi possível carregar o feed ou conteúdo inválido.');
        }

        $itens = $xml->channel->item;
        if (empty($itens)) {
            return 0;
        }

        $inseridas = 0;
        foreach ($itens as $item) {
            try {
                if ($this->inserirItem($item, $categoria, $fonte)) {
                    $inseridas++;
                }
            } catch (Exception $e) {
                $this->logErro('RSS', 'Item: ' . $e->getMessage());
            }
        }

        return $inseridas;
    }

    /**
     * Carrega XML do feed com timeout.
     */
    private function carregarFeed($url)
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => $this->timeout,
                'user_agent' => 'EducaTUDO-RSS/1.0',
            ],
        ]);

        $content = @file_get_contents($url, false, $ctx);
        if ($content === false || trim($content) === '') {
            throw new Exception('Falha ao baixar feed ou resposta vazia.');
        }

        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($content);
        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $msg = $errors ? $errors[0]->message : 'XML inválido';
            throw new Exception($msg);
        }

        return $xml;
    }

    /**
     * Processa um item do RSS e insere no banco se o link ainda não existir.
     * Retorna true se inseriu, false se duplicado ou erro silencioso.
     */
    private function inserirItem(SimpleXMLElement $item, $categoria, $fonte)
    {
        $titulo = $this->texto($item->title);
        $link = $this->texto($item->link);
        $description = isset($item->description) ? (string) $item->description : '';
        $pubDate = isset($item->pubDate) ? (string) $item->pubDate : '';

        if ($link === '') {
            return false;
        }

        $linkTruncado = mb_substr($link, 0, 255);
        $existe = $this->db->fetch(
            'SELECT id FROM noticias WHERE link = :link LIMIT 1',
            ['link' => $link]
        );
        if ($existe) {
            return false;
        }

        $imagem = $this->extrairImagem($description);
        $descricaoLimpa = trim(strip_tags($description));
        $dataPublicacao = $this->parsePubDate($pubDate);

        $sql = "INSERT INTO noticias (titulo, link, descricao, imagem, categoria, fonte, data_publicacao)
                VALUES (:titulo, :link, :descricao, :imagem, :categoria, :fonte, :data_publicacao)";
        $params = [
            'titulo' => mb_substr($titulo, 0, 255),
            'link' => $link,
            'descricao' => $descricaoLimpa,
            'imagem' => $imagem !== '' ? $imagem : null,
            'categoria' => mb_substr($categoria, 0, 50),
            'fonte' => mb_substr($fonte, 0, 50),
            'data_publicacao' => $dataPublicacao,
        ];

        try {
            $this->db->query($sql, $params);
            return true;
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), '1062') !== false) {
                return false;
            }
            throw $e;
        }
    }

    private function texto($node)
    {
        if ($node === null) {
            return '';
        }
        return trim((string) $node);
    }

    /**
     * Extrai URL da primeira imagem no HTML da descrição (G1).
     */
    private function extrairImagem($description)
    {
        if ($description === '' || $description === null) {
            return '';
        }
        if (preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"]/', $description, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    private function parsePubDate($pubDate)
    {
        if ($pubDate === '' || $pubDate === null) {
            return null;
        }
        $ts = @strtotime($pubDate);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $ts);
    }

    private function logErro($contexto, $mensagem)
    {
        if (class_exists('Logger')) {
            Logger::error('[RssService] ' . $contexto . ': ' . $mensagem, [], 'rss');
        } else {
            error_log('[RssService] ' . $contexto . ': ' . $mensagem);
        }
    }
}
