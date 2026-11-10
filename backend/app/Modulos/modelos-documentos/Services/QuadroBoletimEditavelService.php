<?php

namespace App\Modulos\ModelosDocumentos\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;

/** Expõe a estrutura do quadro sem gravar notas de um aluno no modelo. */
class QuadroBoletimEditavelService
{
    public const PREFIXO = 'boletim_celula_';

    /** @return array{html:string,variaveis:array<string,string>,celulas:int} */
    public static function expor(string $html): array
    {
        $vazio = ['html' => '', 'variaveis' => [], 'celulas' => 0];
        if (stripos($html, '<table') === false) {
            return $vazio;
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $anterior = libxml_use_internal_errors(true);
        try {
            $doc->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="edoc-fonte-quadro">' . $html . '</div></body></html>', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
        $raiz = $doc->getElementById('edoc-fonte-quadro');
        if (!$raiz) {
            return $vazio;
        }
        $xpath = new DOMXPath($doc);
        $variaveis = [];
        $numeroTabela = 0;
        foreach ($xpath->query('.//table[not(ancestor::table)]', $raiz) as $tabela) {
            $numeroTabela++;
            $cabecalhos = self::cabecalhos($xpath, $tabela);
            $ocorrencias = [];
            foreach ($xpath->query('./tbody/tr | ./tr', $tabela) as $linha) {
                $celulas = $xpath->query('./td | ./th', $linha);
                if ($celulas->length < 2) {
                    continue;
                }
                $primeira = $celulas->item(0);
                $materiaId = (int) $linha->getAttribute('data-boletim-materia');
                $materia = $materiaId > 0 ? 'm' . $materiaId : self::chave($primeira->textContent);
                $ocorrencias[$materia] = ($ocorrencias[$materia] ?? 0) + 1;
                $sufixo = $ocorrencias[$materia] > 1 ? '_' . $ocorrencias[$materia] : '';
                $coluna = max(1, (int) $primeira->getAttribute('colspan'));
                for ($i = 1; $i < $celulas->length; $i++) {
                    $celula = $celulas->item($i);
                    $codigo = trim($celula->getAttribute('data-boletim-coluna'));
                    $codigo = $codigo !== '' ? self::chave($codigo) : ($cabecalhos[$coluna] ?? ('coluna_' . $coluna));
                    $chave = self::PREFIXO . 't' . $numeroTabela . '_' . $materia . $sufixo . '_' . $codigo;
                    $texto = trim(preg_replace('/\s+/u', ' ', $celula->textContent) ?? '');
                    $variaveis[$chave] = htmlspecialchars($texto !== '' ? $texto : '—', ENT_QUOTES, 'UTF-8');
                    $alvo = $xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " boletim-cell-valor ")]', $celula)->item(0) ?? $celula;
                    while ($alvo->firstChild) {
                        $alvo->removeChild($alvo->firstChild);
                    }
                    $alvo->appendChild($doc->createTextNode('{{' . $chave . '}}'));
                    $coluna += max(1, (int) $celula->getAttribute('colspan'));
                }
            }
        }
        $modelo = '';
        foreach ($raiz->childNodes as $no) {
            $modelo .= $doc->saveHTML($no);
        }
        return ['html' => $modelo, 'variaveis' => $variaveis, 'celulas' => count($variaveis)];
    }

    /** @return array<string,string> */
    public static function variaveis(string $html): array
    {
        return self::expor($html)['variaveis'];
    }

    /** @return array<int,string> */
    private static function cabecalhos(DOMXPath $xpath, DOMElement $tabela): array
    {
        $grade = [];
        foreach ($xpath->query('./thead/tr', $tabela) as $r => $linha) {
            $c = 0;
            foreach ($xpath->query('./th | ./td', $linha) as $celula) {
                while (isset($grade[$r][$c])) {
                    $c++;
                }
                $nome = trim(preg_replace('/\s+/u', ' ', $celula->textContent) ?? '');
                $altura = min(20, max(1, (int) $celula->getAttribute('rowspan')));
                $largura = min(200, max(1, (int) $celula->getAttribute('colspan')));
                for ($rr = $r; $rr < $r + $altura; $rr++) {
                    for ($cc = $c; $cc < $c + $largura; $cc++) {
                        $grade[$rr][$cc] = $nome;
                    }
                }
                $c += $largura;
            }
        }
        $partes = [];
        foreach ($grade as $linha) {
            foreach ($linha as $c => $nome) {
                if ($nome !== '' && !in_array($nome, $partes[$c] ?? [], true)) {
                    $partes[$c][] = $nome;
                }
            }
        }
        return array_map(static fn (array $nomes): string => self::chave(implode('_', $nomes)), $partes);
    }

    private static function chave(string $valor): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);
        $chave = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($ascii !== false ? $ascii : $valor)) ?? '', '_');
        if ($chave === '') {
            return 'sem_nome';
        }
        return strlen($chave) > 90 ? substr($chave, 0, 70) . '_' . substr(sha1($valor), 0, 8) : $chave;
    }
}
