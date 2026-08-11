<?php
/**
 * Converte sequências literais "u00XX" (Unicode sem barra) em caracteres UTF-8.
 * Ex: "primu00e1rios" -> "primários"
 */
function decodeUnicodeEscapesExercicio($str) {
    if ($str === '' || $str === null || !is_string($str)) return $str;
    return preg_replace_callback('/u([0-9a-fA-F]{4})/', function ($m) {
        $char = json_decode('"\\u' . $m[1] . '"');
        return $char !== null ? $char : $m[0];
    }, $str);
}

/**
 * Remove apenas script/style e normaliza LaTeX para exibir com MathJax de forma segura.
 * Garante que delimitadores \( e \) estejam no HTML para o MathJax reconhecer.
 */
function prepararTextoParaMathJax($str) {
    if ($str === '' || $str === null || !is_string($str)) return $str;
    $str = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $str);
    $str = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $str);
    // Normaliza delimitadores que possam ter vindo escapados (ex.: \\ ( no banco vira \( no HTML )
    $str = str_replace(['\\\\(', '\\\\)', '\\\\[', '\\\\]'], ['\\(', '\\)', '\\[', '\\]'], $str);
    return $str;
}

function enunciadoPermiteRemoverTituloDuplicado(string $titulo, string $enunciado): bool
{
    if ($titulo === '' || $enunciado === '') {
        return false;
    }
    if (stripos($enunciado, '<img') !== false) {
        return false;
    }
    $tituloPlain = trim(html_entity_decode(strip_tags($titulo), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $enunciadoPlain = trim(html_entity_decode(strip_tags($enunciado), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($tituloPlain === '' || $enunciadoPlain === '') {
        return false;
    }
    if (strncmp($enunciadoPlain, $tituloPlain, strlen($tituloPlain)) !== 0) {
        return false;
    }
    if (strlen($tituloPlain) < strlen($enunciadoPlain)) {
        $ultimo = mb_substr($tituloPlain, -1, 1, 'UTF-8');
        $proximo = mb_substr($enunciadoPlain, strlen($tituloPlain), 1, 'UTF-8');
        if ($ultimo !== '' && $proximo !== '' && preg_match('/\p{L}/u', $ultimo) && preg_match('/\p{L}/u', $proximo)) {
            return false;
        }
    }
    return true;
}

/**
 * Normaliza src de imagens dentro do enunciado para evitar ícone de imagem quebrada no aluno.
 * Corrige caminhos legados (/public/uploads), relativos e entidades (&amp;).
 */
function normalizarImagensEnunciadoAluno($html) {
    if ($html === '' || $html === null || !is_string($html)) return $html;
    $baseUrl = rtrim((string)(defined('URL') ? URL : ''), '/');
    $tenantSlug = defined('TENANT_SLUG') ? trim((string)TENANT_SLUG) : '';
    return preg_replace_callback('/<img\b([^>]*)\bsrc=["\']([^"\']+)["\']([^>]*)>/i', function ($m) use ($baseUrl, $tenantSlug) {
        $before = $m[1] ?? '';
        $srcRaw = $m[2] ?? '';
        $after = $m[3] ?? '';
        $src = html_entity_decode(trim((string)$srcRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($src !== '' && stripos($src, 'data:image/') !== 0) {
            $parsedUrl = @parse_url($src);
            $queryParams = [];
            if (!empty($parsedUrl['query'])) {
                parse_str((string) $parsedUrl['query'], $queryParams);
            }
            if (($queryParams['type'] ?? '') === 'jornadas_exercicios' && !empty($queryParams['key'])) {
                $src = '/media/serve?type=jornadas_exercicios&key=' . rawurlencode((string) $queryParams['key']);
            } elseif (($queryParams['type'] ?? '') === 'provas_questoes' && !empty($queryParams['key'])) {
                $src = '/media/serve?type=provas_questoes&key=' . rawurlencode((string) $queryParams['key']);
            } elseif (!empty($parsedUrl['path']) && preg_match('#(?:^|/)(?:' . preg_quote($tenantSlug, '#') . '/)?jornadas/exercicios/(.+)$#i', (string) $parsedUrl['path'], $pm)) {
                $src = '/media/serve?type=jornadas_exercicios&key=' . rawurlencode(ltrim((string) $pm[1], '/'));
            }

            $src = str_replace('/public/uploads/', '/uploads/', $src);
            if (strpos($src, 'public/uploads/') === 0) {
                $src = '/uploads/' . ltrim(substr($src, strlen('public/uploads/')), '/');
            } elseif (strpos($src, 'uploads/') === 0) {
                $src = '/uploads/' . ltrim(substr($src, strlen('uploads/')), '/');
            } elseif (strpos($src, 'media/serve?') === 0) {
                $src = '/media/serve?' . substr($src, strlen('media/serve?'));
            }

            // Se vier relativo, torna absoluto no domínio da aplicação.
            if ($baseUrl !== '' && !preg_match('#^(https?:)?//#i', $src) && stripos($src, 'data:image/') !== 0) {
                $src = $baseUrl . '/' . ltrim($src, '/');
            }

            // Garante tenant no media/serve em ambiente multi-tenant.
            if ($tenantSlug !== '' && stripos($src, '/media/serve?') !== false && stripos($src, 'tenant=') === false) {
                $src .= (strpos($src, '?') !== false ? '&' : '?') . 'tenant=' . rawurlencode($tenantSlug);
            }
        }

        $srcEsc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
        return '<img' . $before . 'src="' . $srcEsc . '"' . $after
            . ' onerror="if(!this.dataset.errfix){this.dataset.errfix=\'1\';var s=this.getAttribute(\'src\')||\'\';if(s.indexOf(\'/public/uploads/\')>=0){this.src=s.replace(\'/public/uploads/\',\'/uploads/\');return;}if(s.indexOf(\'/uploads/\')>=0&&s.indexOf(\'/media/serve?\')<0){this.src=s.replace(\'/uploads/\',\'/public/uploads/\');return;}if(s.indexOf(\'&amp;\')>=0){this.src=s.replace(/&amp;/g,\'&\');return;}}this.style.display=\'none\';"'
            . '>';
    }, $html);
}
require_once __DIR__ . '/../../../Core/TenantRelease.php';
$ocultarTituloJornada = TenantRelease::shouldUse('jornadas_ocultar_titulo_v1', true);
$ocultarTituloExercicioJornada = TenantRelease::shouldUse('jornadas_ocultar_titulo_exercicio_v1', true);

// CORREÇÃO CRÍTICA: Garante que $questoes seja sempre um array
// Isso deve ser feito ANTES de qualquer uso da variável
if (isset($questoes)) {
    if (is_string($questoes)) {
        $questoes = json_decode($questoes, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($questoes)) {
            $questoes = null;
        }
    } elseif (!is_array($questoes)) {
        $questoes = null;
    }
} else {
    $questoes = null;
}

        // Se ainda não temos questoes, tenta decodificar do exercicio_atual
if (empty($questoes) && !empty($exercicio_atual['questoes_json'])) {
    if (is_array($exercicio_atual['questoes_json'])) {
        $questoes = $exercicio_atual['questoes_json'];
    } else {
        $jsonString = trim($exercicio_atual['questoes_json']);
        if (!mb_check_encoding($jsonString, 'UTF-8')) {
            $jsonString = mb_convert_encoding($jsonString, 'UTF-8', 'auto');
        }
        // 1ª tentativa: decode direto (evita stripslashes que quebra \u e LaTeX)
        $questoes = json_decode($jsonString, true, 512, JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($questoes)) {
            $questoes = null;
            if (preg_match('/^"(.*)"$/s', $jsonString, $matches)) {
                $inner = $matches[1];
                $inner = str_replace('\"', '"', $inner);
                $inner = str_replace('\\\\', '\\', $inner);
                $questoes = json_decode($inner, true, 512, JSON_UNESCAPED_UNICODE);
            }
        }
        if ($questoes && is_array($questoes)) {
            // Normaliza encoding das opções após decodificar
            if (isset($questoes['opcoes']) && is_array($questoes['opcoes'])) {
                foreach ($questoes['opcoes'] as &$opcao) {
                    if (isset($opcao['texto']) && !mb_check_encoding($opcao['texto'], 'UTF-8')) {
                        $opcao['texto'] = mb_convert_encoding($opcao['texto'], 'UTF-8', 'auto');
                    }
                    if (isset($opcao['text']) && !mb_check_encoding($opcao['text'], 'UTF-8')) {
                        $opcao['text'] = mb_convert_encoding($opcao['text'], 'UTF-8', 'auto');
                    }
                    // Remove caracteres de controle mas preserva UTF-8
                    if (isset($opcao['texto'])) {
                        $opcao['texto'] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $opcao['texto']);
                    }
                    if (isset($opcao['text'])) {
                        $opcao['text'] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $opcao['text']);
                    }
                }
                unset($opcao);
            }
        }
    }
}
?>
<?php if (!empty($preview)): ?>
<div class="mb-4 p-3 bg-amber-100 border border-amber-300 rounded-lg text-amber-800 text-sm flex items-center gap-2">
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
    <span><strong>Preview:</strong> nenhum progresso é salvo.</span>
</div>
<?php endif; ?>
<!-- Header -->
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-2">
        Exercícios - <?= htmlspecialchars($modulo['titulo']) ?>
    </h2>
    <?php if (!$ocultarTituloJornada): ?>
    <p class="text-gray-600">
        Jornada: <?= htmlspecialchars($jornada['titulo']) ?>
    </p>
    <?php endif; ?>
</div>

<!-- Navegação de Questões (Numeração) -->
<div class="bg-white rounded-xl shadow-lg p-4 mb-6 border border-blue-200">
    <div class="flex flex-wrap gap-2 justify-center">
        <?php foreach ($exercicios as $index => $ex): ?>
            <button onclick="irParaQuestao(<?= $index ?>)" 
                    class="questao-btn w-10 h-10 rounded-lg font-semibold transition-all <?= $index === $exercicio_atual_index ? 'bg-blue-600 text-white' : (!empty($ex['resposta_salva']) ? 'bg-blue-100 text-blue-800 border-2 border-blue-300' : 'bg-gray-100 text-gray-700 hover:bg-gray-200') ?>"
                    data-questao="<?= $index ?>">
                <?= $index + 1 ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- Exercício Atual (wrapper para MathJax: enunciado + alternativas) -->
<div id="conteudo-exercicio-jornada-mathjax" class="bg-white rounded-xl shadow-lg p-6 border border-blue-200">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <?php if (!empty($exercicio_atual['resposta_salva'])): ?>
                <span class="ml-3 px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                    Respondido
                </span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="mb-6">
        <?php
        $tipoAtual = strtolower(trim((string)($exercicio_atual['tipo'] ?? '')));
        $ocultarTituloDissertativa = ($tipoAtual === 'dissertativa');
        $mostrarTituloExercicio = !$ocultarTituloExercicioJornada && !$ocultarTituloDissertativa;

        $tituloExercicio = $exercicio_atual['titulo'] ?? '';
        $tituloExercicio = decodeUnicodeEscapesExercicio($tituloExercicio);
        if (!mb_check_encoding($tituloExercicio, 'UTF-8')) {
            $tituloExercicio = mb_convert_encoding($tituloExercicio, 'UTF-8', 'auto');
        }
        $tituloExercicio = trim((string)$tituloExercicio);
        ?>
        <?php if ($mostrarTituloExercicio): ?>
        <h3 class="text-xl font-bold text-gray-900 mb-3">
            <?php
            $tituloRender = $tituloExercicio !== '' ? $tituloExercicio : 'Exercício sem título';
            echo htmlspecialchars($tituloRender, ENT_QUOTES, 'UTF-8');
            ?>
        </h3>
        <?php endif; ?>
        <div class="text-gray-700 mb-4 prose prose-sm max-w-none break-words <?= ($tipoAtual === 'preencher_lacuna') ? 'hidden' : '' ?>" id="enunciado-exercicio-mathjax">
            <?php 
            $enunciado = $exercicio_atual['enunciado'] ?? 'Sem enunciado';
            $enunciado = decodeUnicodeEscapesExercicio($enunciado);
            if (!mb_check_encoding($enunciado, 'UTF-8')) {
                $enunciado = mb_convert_encoding($enunciado, 'UTF-8', 'auto');
            }
            // Evita enunciado duplicado quando o professor colou o título no começo (negrito).
            // Não aplica em exercícios gerados por IA (título = primeiros 60 chars do enunciado).
            $geradoIa = !empty($exercicio_atual['gerado_ia']);
            if (!$geradoIa && $tituloExercicio !== '' && is_string($enunciado) && $enunciado !== ''
                && enunciadoPermiteRemoverTituloDuplicado($tituloExercicio, $enunciado)) {
                $tituloQuoted = preg_quote($tituloExercicio, '~');
                $enunciado = preg_replace(
                    '~^\s*(?:<p[^>]*>\s*)?(?:(?:<strong>|<b>)\s*)?' . $tituloQuoted . '\s*(?:(?:</strong>|</b>)\s*)?(?:</p>\s*)?(?:[:\\-–—]\\s*)?~iu',
                    '',
                    $enunciado,
                    1
                );
            }
            $enunciadoOriginal = (string)$enunciado;
            $enunciadoSanitizado = \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($enunciadoOriginal);
            $originalTemMidiaOuTabela = (stripos($enunciadoOriginal, '<img') !== false || stripos($enunciadoOriginal, '<table') !== false);
            $sanitizadoTemMidiaOuTabela = (stripos($enunciadoSanitizado, '<img') !== false || stripos($enunciadoSanitizado, '<table') !== false);

            if ($originalTemMidiaOuTabela && !$sanitizadoTemMidiaOuTabela) {
                // Fallback: preserva mídia/tabela quando o purifier remove conteúdo válido do professor.
                // Mantém segurança removendo scripts/iframes e atributos on*.
                $enunciadoFallbackSeguro = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $enunciadoOriginal);
                $enunciadoFallbackSeguro = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $enunciadoFallbackSeguro);
                $enunciadoFallbackSeguro = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $enunciadoFallbackSeguro);
                $enunciadoFallbackSeguro = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $enunciadoFallbackSeguro);
                $enunciadoFallbackSeguro = str_replace('/public/uploads/', '/uploads/', $enunciadoFallbackSeguro);
                $enunciadoSanitizado = trim((string)$enunciadoFallbackSeguro);
            }

            if ($enunciadoSanitizado !== '') {
                $enunciadoSanitizado = normalizarImagensEnunciadoAluno($enunciadoSanitizado);
                echo $enunciadoSanitizado;
            } else {
                $enunciadoFallback = prepararTextoParaMathJax($exercicio_atual['enunciado'] ?? '');
                $enunciadoFallback = normalizarImagensEnunciadoAluno($enunciadoFallback);
                echo $enunciadoFallback;
            }
            ?>
        </div>
        <?php if (!empty($exercicio_atual['imagem_url'])): ?>
        <?php
            $img_url = $exercicio_atual['imagem_url'];
            $img_url = preg_replace('#/public/uploads/#', '/uploads/', $img_url);
        ?>
        <div class="mb-4">
            <img src="<?= htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8') ?>" alt="Imagem do enunciado" class="max-w-full h-auto rounded-lg border border-gray-200 shadow-sm">
        </div>
        <?php endif; ?>
        
        <?php 
        // Verifica se há questões e opções disponíveis
        // NOTA: $questoes já foi normalizado no início do arquivo (linha 1-27)
        $temOpcoes = false;
        $opcoes = [];
        
        // Verifica se o tipo é alternativas e se temos questoes válidas
        if ($exercicio_atual['tipo'] === 'alternativas' && $questoes && is_array($questoes)) {
            // Verifica se tem opcoes no formato esperado
            if (isset($questoes['opcoes']) && is_array($questoes['opcoes']) && !empty($questoes['opcoes'])) {
                $temOpcoes = true;
                $opcoes = $questoes['opcoes'];
            } elseif (isset($questoes[0]) && isset($questoes[0]['letra'])) {
                // Formato alternativo: array direto de opções
                $temOpcoes = true;
                $opcoes = $questoes;
            }
        }
        $ehDissertativa = ($exercicio_atual['tipo'] === 'dissertativa');
        $ehLacuna = ($exercicio_atual['tipo'] === 'preencher_lacuna');
        ?>
        
        <?php if ($temOpcoes): ?>
            <?php $modoGabarito = !empty($liberar_gabarito); ?>
            <div class="space-y-3 mt-4">
                <?php foreach ($opcoes as $idxOpcao => $opcao): ?>
                    <?php 
                    $textoOpcao = $opcao['texto'] ?? $opcao['text'] ?? '';
                    $textoOpcao = decodeUnicodeEscapesExercicio($textoOpcao);
                    $letraOpcao = trim((string)($opcao['letra'] ?? ''));
                    if ($letraOpcao === '') {
                        $letraOpcao = chr(65 + ((int)$idxOpcao % 26));
                    }
                    $respostaSalva = $exercicio_atual['resposta_salva'] ?? '';
                    $marcado = ($respostaSalva !== '' && strtoupper(trim($respostaSalva)) === strtoupper(trim($letraOpcao)));
                    $isCorreta = !empty($opcao['correta']);
                    if (!$isCorreta && !empty($exercicio_atual['resposta_correta'])) {
                        $isCorreta = strtoupper(trim($letraOpcao)) === strtoupper(trim($exercicio_atual['resposta_correta']));
                    }
                    $isRespostaAluno = $marcado;
                    if (!mb_check_encoding($textoOpcao, 'UTF-8')) {
                        $textoOpcao = mb_convert_encoding($textoOpcao, 'UTF-8', 'auto');
                    }
                    $textoOpcao = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $textoOpcao);
                    $bgColor = '';
                    if ($modoGabarito) {
                        if ($isCorreta) {
                            $bgColor = 'bg-green-50 border-l-4 border-green-500';
                        } elseif ($isRespostaAluno && !$isCorreta) {
                            $bgColor = 'bg-red-50 border-l-4 border-red-500';
                        }
                    }
                    ?>
                    <?php if ($modoGabarito): ?>
                        <div class="flex items-center space-x-2 p-3 rounded border border-gray-200 <?= $bgColor ?>">
                            <span class="font-medium <?= $isCorreta ? 'text-green-700' : ($isRespostaAluno ? 'text-red-700' : 'text-gray-700') ?>">
                                <?= htmlspecialchars($letraOpcao, ENT_QUOTES, 'UTF-8') ?>.
                            </span>
                            <span class="flex-1 opcao-texto-mathjax prose prose-sm max-w-none inline <?= $isCorreta ? 'text-green-700 font-semibold' : ($isRespostaAluno ? 'text-red-700 font-semibold' : 'text-gray-700') ?>">
                                <?php
                                $textoOpcaoSafe = \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($textoOpcao);
                                echo $textoOpcaoSafe !== '' ? $textoOpcaoSafe : prepararTextoParaMathJax($textoOpcao);
                                ?>
                            </span>
                            <?php if ($isCorreta): ?>
                                <span class="ml-auto px-2 py-1 bg-green-200 text-green-800 text-xs rounded font-medium">Correta</span>
                            <?php elseif ($isRespostaAluno): ?>
                                <span class="ml-auto px-2 py-1 bg-red-200 text-red-800 text-xs rounded font-medium">Sua resposta</span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer transition-all border-gray-200 hover:bg-gray-50">
                            <input type="radio"
                                   name="resposta_<?= $exercicio_atual['id'] ?>"
                                   value="<?= htmlspecialchars($letraOpcao, ENT_QUOTES, 'UTF-8') ?>"
                                   class="mt-1 mr-3 w-5 h-5 text-blue-600"
                                   <?= $marcado ? 'checked' : '' ?>
                                   onchange="salvarRespostaComAuditoria(<?= $exercicio_atual['id'] ?>, '<?= htmlspecialchars($letraOpcao, ENT_QUOTES, 'UTF-8') ?>')">
                            <div class="flex-1">
                                <span class="font-semibold text-gray-900 mr-2"><?= htmlspecialchars($letraOpcao, ENT_QUOTES, 'UTF-8') ?>.</span>
                                <span class="text-gray-700 opcao-texto-mathjax prose prose-sm max-w-none inline"><?php
                                $textoOpcaoSafe = \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($textoOpcao);
                                echo $textoOpcaoSafe !== '' ? $textoOpcaoSafe : prepararTextoParaMathJax($textoOpcao);
                                ?></span>
                            </div>
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php elseif ($ehLacuna): ?>
            <?php
            $opcoesLacuna = [];
            if (is_array($questoes) && isset($questoes['opcoes_lacuna']) && is_array($questoes['opcoes_lacuna'])) {
                $opcoesLacuna = $questoes['opcoes_lacuna'];
            }
            $respostaLacunaSalva = trim((string)($exercicio_atual['resposta_salva'] ?? ''));
            $respostasLacunaSalvas = [];
            if ($respostaLacunaSalva !== '') {
                $respostasLacunaSalvas = array_values(array_filter(array_map('trim', explode('|', $respostaLacunaSalva)), function($v){ return $v !== ''; }));
            }
            $enunciadoComLacuna = $exercicio_atual['enunciado'] ?? '';
            $enunciadoComLacuna = decodeUnicodeEscapesExercicio($enunciadoComLacuna);
            $enunciadoComLacuna = \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($enunciadoComLacuna);
            if (strpos($enunciadoComLacuna, '___') === false) {
                $enunciadoComLacuna .= ' ___';
            }
            $partesLacuna = explode('___', $enunciadoComLacuna);
            ?>
            <div class="space-y-4 mt-4" id="lacuna-wrapper-<?= (int)$exercicio_atual['id'] ?>" data-exercicio-id="<?= (int)$exercicio_atual['id'] ?>">
                <div class="p-4 rounded-lg border border-gray-200 bg-gray-50">
                    <div class="text-gray-800 leading-relaxed">
                        <?php
                        $totalLacunas = count($partesLacuna) - 1;
                        for ($i = 0; $i < count($partesLacuna); $i++) {
                            echo $partesLacuna[$i];
                            if ($i < $totalLacunas) {
                                $valorSalvo = $respostasLacunaSalvas[$i] ?? '';
                                $dropValor = $valorSalvo !== '' ? htmlspecialchars($valorSalvo, ENT_QUOTES, 'UTF-8') : '______';
                                echo '<span class="lacuna-drop inline-flex items-center justify-center min-w-[120px] px-3 py-1.5 mx-1 rounded-md border-2 border-dashed border-blue-400 bg-white text-blue-700 font-semibold cursor-pointer" data-lacuna-drop="' . $i . '">' . $dropValor . '</span>';
                            }
                        }
                        ?>
                    </div>
                </div>
                <div class="p-3 rounded-lg border border-gray-200 bg-white">
                    <p class="text-sm text-gray-600 mb-2">Arraste uma palavra para cada lacuna (ou clique na lacuna e depois clique na palavra):</p>
                    <div class="flex flex-wrap gap-2" data-lacuna-opcoes>
                        <?php foreach ($opcoesLacuna as $op): ?>
                            <?php $opTxt = trim((string)$op); if ($opTxt === '') continue; ?>
                            <button type="button"
                                    class="lacuna-chip px-3 py-1.5 rounded-full bg-blue-100 text-blue-800 text-sm font-medium hover:bg-blue-200 transition-colors cursor-grab active:cursor-grabbing"
                                    draggable="true"
                                    data-lacuna-opcao="<?= htmlspecialchars($opTxt, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($opTxt, ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php elseif ($ehDissertativa): ?>
            <div class="space-y-3 mt-4">
                <label for="resposta_dissertativa_<?= $exercicio_atual['id'] ?>" class="block text-sm font-medium text-gray-700">Sua resposta</label>
                <textarea id="resposta_dissertativa_<?= $exercicio_atual['id'] ?>"
                          name="resposta_dissertativa_<?= $exercicio_atual['id'] ?>"
                          class="w-full min-h-[180px] px-4 py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Escreva sua resposta aqui..."
                          data-exercicio-id="<?= $exercicio_atual['id'] ?>"
                          onblur="salvarRespostaDissertativa(<?= $exercicio_atual['id'] ?>)"><?= htmlspecialchars($exercicio_atual['resposta_salva'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <p class="text-sm text-gray-500">Sua resposta é salva automaticamente ao sair do campo. Você também pode clicar em "Salvar resposta" antes de ir para a próxima questão.</p>
                <button type="button" onclick="salvarRespostaDissertativa(<?= $exercicio_atual['id'] ?>)"
                        data-dissertativa-salvar="<?= $exercicio_atual['id'] ?>"
                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors text-sm">
                    Salvar resposta
                </button>
            </div>
        <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mt-4">
                <p class="text-yellow-800">
                    <strong>Atenção:</strong> Este exercício não possui questões configuradas ou o formato está incorreto.
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($tudinha_explica_exercicio_modal)): ?>
    <div class="mt-6 p-4 bg-gradient-to-r from-pink-50 to-purple-50 border border-pink-200 rounded-xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-900">Parabéns por concluir esta jornada!</p>
            <p class="text-sm text-gray-600 mt-0.5">Quer revisar os conceitos com a <?= htmlspecialchars($ia_nome_tudinha ?? 'Tudinha', ENT_QUOTES, 'UTF-8') ?>?</p>
        </div>
        <button type="button" onclick="abrirModalTudinhaExercicioJornada()"
            class="shrink-0 border-2 border-pink-500 text-pink-700 bg-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-pink-50 transition-colors whitespace-nowrap">
            Tudinha explica
        </button>
    </div>
    <?php endif; ?>

    <?php if (!empty($liberar_gabarito) && (!empty($exercicio_atual['resposta_correta']) || !empty($exercicio_atual['gabarito']))): ?>
    <div class="mt-6 p-4 bg-emerald-50 border border-emerald-200 rounded-xl">
        <h4 class="font-semibold text-emerald-800 mb-2 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Gabarito
        </h4>
        <p class="text-sm text-emerald-700 mb-3">Você concluiu esta jornada; o gabarito dos exercícios está disponível para consulta.</p>
        <?php if (!empty($exercicio_atual['resposta_correta'])): ?>
        <p class="text-gray-800"><strong>Resposta correta:</strong> <?= htmlspecialchars($exercicio_atual['resposta_correta'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if (!empty($exercicio_atual['gabarito'])): ?>
        <div class="text-gray-800 mt-2 prose prose-sm max-w-none"><?= nl2br(htmlspecialchars($exercicio_atual['gabarito'], ENT_QUOTES, 'UTF-8')) ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Botões de Navegação -->
    <div class="flex justify-between items-center mt-6 pt-6 border-t border-gray-200">
        <button onclick="questaoAnterior()" 
                class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors flex items-center <?= $exercicio_atual_index === 0 ? 'opacity-50 cursor-not-allowed' : '' ?>"
                <?= $exercicio_atual_index === 0 ? 'disabled' : '' ?>>
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Anterior
        </button>
        
        <?php if ($exercicio_atual_index >= $total_exercicios - 1): ?>
            <!-- Última questão: em modo gabarito não mostra Finalizar, só navegação -->
            <?php if (empty($liberar_gabarito)): ?>
            <button id="btn-finalizar-exercicios" onclick="finalizarExercicios()" 
                    class="px-6 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Finalizar Exercícios
            </button>
            <?php endif; ?>
        <?php else: ?>
            <!-- Não é a última questão - Botão Próxima -->
            <button id="btn-proxima-questao" onclick="questaoProxima()" 
                    class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors flex items-center">
                Próxima
                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </button>
        <?php endif; ?>
    </div>
    
    <!-- Botão Finalizar e Voltar: só aparece quando todos estão concluídos e NÃO está no modo consulta ao gabarito -->
    <?php 
    $todosConcluidos = true;
    foreach ($exercicios as $ex) {
        if ($ex['progresso_status'] !== 'concluido') {
            $todosConcluidos = false;
            break;
        }
    }
    ?>
    <?php if ($todosConcluidos && empty($liberar_gabarito)): ?>
        <div class="mt-6 flex justify-center">
            <a href="<?= URL ?>/jornadas/<?= $jornada['id'] ?>" 
               class="px-8 py-3 bg-green-500 text-white rounded-lg font-semibold hover:bg-green-600 transition-colors flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Finalizar e Voltar
            </a>
        </div>
    <?php endif; ?>
    <?php if (!empty($liberar_gabarito)): ?>
        <div class="mt-6 flex justify-center">
            <a href="<?= URL ?>/jornadas/<?= $jornada['id'] ?>" 
               class="px-6 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700 transition-colors flex items-center text-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar à jornada
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Overlay de loading para exercícios (salvando resposta / finalizando) -->
<div id="exercicios-enviando-overlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[100] flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full mx-4 p-8 text-center">
        <div class="w-16 h-16 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
        <p id="exercicios-enviando-texto" class="text-lg font-semibold text-gray-800">Enviando...</p>
        <p class="text-sm text-gray-500 mt-1">Não feche a página</p>
    </div>
</div>

<?php if (empty($preview) && !empty($chat_habilitado) && !empty($tudinha_explica_exercicio_modal)): ?>
<div id="modal-tudinha-exercicio-jornada" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/50" onclick="if(event.target===this)fecharModalTudinhaExercicioJornada()">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[88vh] flex flex-col overflow-hidden border border-purple-100" role="dialog" aria-modal="true" aria-labelledby="modal-tudinha-exercicio-titulo" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-pink-50 to-purple-50 shrink-0">
            <h3 id="modal-tudinha-exercicio-titulo" class="text-lg font-bold text-gray-900 truncate"><?= htmlspecialchars($ia_nome_tudinha ?? 'Tudinha', ENT_QUOTES, 'UTF-8') ?> explica</h3>
            <button type="button" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 shrink-0" onclick="fecharModalTudinhaExercicioJornada()" title="Fechar" aria-label="Fechar">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div id="modal-tudinha-exercicio-loading" class="hidden p-10 text-center text-gray-600 shrink-0">
            <div class="w-12 h-12 border-4 border-pink-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
            <p class="text-sm font-medium">Gerando explicação…</p>
        </div>
        <div id="modal-tudinha-exercicio-erro" class="hidden px-5 py-3 text-sm text-red-700 bg-red-50 border-b border-red-100 shrink-0"></div>
        <div id="modal-tudinha-exercicio-corpo" class="flex-1 min-h-0 overflow-y-auto p-5 prose prose-sm max-w-none text-gray-800"></div>
    </div>
</div>
<?php endif; ?>

<script>
var EXERCICIO_ID_TUDINHA_MODAL = <?= (int)($exercicio_atual['id'] ?? 0) ?>;
var CSRF_TOKEN_TUDINHA_EXERCICIO = <?= json_encode($csrf_token ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

function fecharModalTudinhaExercicioJornada() {
    var m = document.getElementById('modal-tudinha-exercicio-jornada');
    if (m) m.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function abrirModalTudinhaExercicioJornada() {
    var modal = document.getElementById('modal-tudinha-exercicio-jornada');
    if (!modal) return;
    var loading = document.getElementById('modal-tudinha-exercicio-loading');
    var erro = document.getElementById('modal-tudinha-exercicio-erro');
    var corpo = document.getElementById('modal-tudinha-exercicio-corpo');
    if (!loading || !erro || !corpo) return;
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    loading.classList.remove('hidden');
    erro.classList.add('hidden');
    erro.textContent = '';
    corpo.innerHTML = '';

    var fd = new FormData();
    fd.append('_token', CSRF_TOKEN_TUDINHA_EXERCICIO || '');
    fd.append('exercicio_id', String(EXERCICIO_ID_TUDINHA_MODAL));

    fetch('<?= URL ?>/jornadas/explicar-exercicio-tudinha', { method: 'POST', body: fd })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, data: j }; }); })
        .then(function (res) {
            loading.classList.add('hidden');
            if (res.data && res.data.success && res.data.html) {
                corpo.innerHTML = res.data.html;
                return;
            }
            var msg = (res.data && res.data.error) ? res.data.error : 'Não foi possível carregar a explicação.';
            erro.textContent = msg;
            erro.classList.remove('hidden');
        })
        .catch(function () {
            loading.classList.add('hidden');
            erro.textContent = 'Erro de conexão. Tente novamente.';
            erro.classList.remove('hidden');
        });
}

function showExerciciosEnviando(texto) {
    var el = document.getElementById('exercicios-enviando-overlay');
    var txt = document.getElementById('exercicios-enviando-texto');
    if (el) el.classList.remove('hidden');
    if (txt && texto) txt.textContent = texto;
}
function hideExerciciosEnviando() {
    var el = document.getElementById('exercicios-enviando-overlay');
    if (el) el.classList.add('hidden');
}
function setButtonLoading(btn, loadingText) {
    if (!btn) return;
    if (!btn.dataset.originalHtml) {
        btn.dataset.originalHtml = btn.innerHTML;
    }
    btn.disabled = true;
    btn.classList.add('opacity-70', 'cursor-not-allowed');
    btn.innerHTML = '<span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2 align-middle"></span>' + (loadingText || 'Carregando...');
}
function clearButtonLoading(btn) {
    if (!btn) return;
    btn.disabled = false;
    btn.classList.remove('opacity-70', 'cursor-not-allowed');
    if (btn.dataset.originalHtml) {
        btn.innerHTML = btn.dataset.originalHtml;
    }
}

let exercicios = <?= json_encode($exercicios) ?>;
let exercicioAtualIndex = <?= $exercicio_atual_index ?>;
let totalExercicios = <?= $total_exercicios ?>;
let moduloId = <?= $modulo['id'] ?>;
let jornadaId = <?= $jornada['id'] ?>;
let isPreview = <?= !empty($preview) ? 'true' : 'false' ?>;
let exercicioFinalizado = false;

function getRespostaLacuna(exercicioId) {
    var wrap = document.getElementById('lacuna-wrapper-' + exercicioId);
    if (!wrap) return '';
    var drops = wrap.querySelectorAll('[data-lacuna-drop]');
    if (!drops.length) return '';
    var valores = [];
    var incompleto = false;
    drops.forEach(function(drop) {
        var valor = (drop.textContent || '').trim();
        if (!valor || valor === '______') {
            incompleto = true;
        } else {
            valores.push(valor);
        }
    });
    if (incompleto || !valores.length) return '';
    return valores.join('|');
}
let reloadEmAndamento = false;
let navegacaoIntencional = false;
var respostasEmEnvio = {};
var auditoriaPendencias = [];
var auditoriaFlushTimer = null;
var auditoriaFlushEmAndamento = false;
var AUDITORIA_INTERVALO_MS = 2500;
var RESPOSTA_MAX_RETRIES = 2;
var FINALIZAR_MAX_RETRIES = 2;
var PENDENCIAS_STORAGE_KEY = 'jornada_respostas_pendentes_' + jornadaId + '_' + moduloId;

function isErroTransienteConexao(msg) {
    var texto = (msg || '').toString().toLowerCase();
    return texto.indexOf('connection timed out') >= 0 ||
        texto.indexOf('sqlstate[hy000] [2002]') >= 0 ||
        texto.indexOf('erro de conexão') >= 0 ||
        texto.indexOf('instabilidade tempor') >= 0 ||
        texto.indexOf('failed to fetch') >= 0 ||
        texto.indexOf('networkerror') >= 0;
}

function getPendenciasSalvas() {
    try {
        var raw = localStorage.getItem(PENDENCIAS_STORAGE_KEY);
        var data = raw ? JSON.parse(raw) : {};
        return (data && typeof data === 'object') ? data : {};
    } catch (e) {
        return {};
    }
}

function setPendenciasSalvas(data) {
    try {
        localStorage.setItem(PENDENCIAS_STORAGE_KEY, JSON.stringify(data || {}));
    } catch (e) {}
}

function salvarPendenciaResposta(exercicioId, resposta) {
    var pendencias = getPendenciasSalvas();
    pendencias[String(exercicioId)] = {
        resposta: (resposta === null || resposta === undefined) ? '' : String(resposta),
        ts: Date.now()
    };
    setPendenciasSalvas(pendencias);
}

function removerPendenciaResposta(exercicioId) {
    var pendencias = getPendenciasSalvas();
    delete pendencias[String(exercicioId)];
    setPendenciasSalvas(pendencias);
}

function delay(ms) {
    return new Promise(function(resolve) { setTimeout(resolve, ms); });
}

// Bloqueia ações até finalizar
function bloquearAcoes() {
    // Bloqueia F5 e Ctrl+R
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F5' || (e.ctrlKey && (e.key === 'r' || e.key === 'R')) || (e.ctrlKey && e.key === 'F5')) {
            e.preventDefault();
            e.stopPropagation();
            alert('Você não pode atualizar a página durante a realização dos exercícios!');
            return false;
        }
        // Bloqueia Ctrl+W (fechar aba)
        if (e.ctrlKey && (e.key === 'w' || e.key === 'W')) {
            e.preventDefault();
            e.stopPropagation();
            alert('Você não pode fechar a página durante a realização dos exercícios!');
            return false;
        }
        // Bloqueia Alt+F4 (fechar janela)
        if (e.altKey && e.key === 'F4') {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    }, true);
    
    // Bloqueia sair da página
    window.addEventListener('beforeunload', function(e) {
        if (!exercicioFinalizado && !navegacaoIntencional) {
            e.preventDefault();
            e.returnValue = 'Você não pode sair da página durante a realização dos exercícios! Finalize todos os exercícios antes de sair.';
            return e.returnValue;
        }
    });
    
    // Bloqueia botão voltar do navegador
    history.pushState(null, null, location.href);
    window.onpopstate = function(event) {
        if (!exercicioFinalizado) {
            history.pushState(null, null, location.href);
            alert('Você não pode voltar durante a realização dos exercícios!');
        }
    };
    
    // Bloqueia menu de contexto (botão direito)
    document.addEventListener('contextmenu', function(e) {
        if (!exercicioFinalizado) {
            e.preventDefault();
            return false;
        }
    });
    
    // Bloqueia seleção de texto (opcional, pode remover se necessário)
    document.addEventListener('selectstart', function(e) {
        // Permite seleção apenas em inputs e textareas
        if (!e.target.closest('input, textarea, label')) {
            // e.preventDefault();
        }
    });
    
    // Bloqueia arrastar elementos
    document.addEventListener('dragstart', function(e) {
        if (!exercicioFinalizado) {
            e.preventDefault();
            return false;
        }
    });
}

function getRespostaDissertativa(exercicioId) {
    var el = document.getElementById('resposta_dissertativa_' + exercicioId);
    return el ? (el.value || '').trim() : '';
}

function salvarRespostaDissertativa(exercicioId) {
    var btnSalvar = document.querySelector('button[data-dissertativa-salvar="' + exercicioId + '"]');
    if (btnSalvar && btnSalvar.disabled) return;
    setButtonLoading(btnSalvar, 'Salvando...');
    var resposta = getRespostaDissertativa(exercicioId);
    // Para dissertativa permitimos salvar mesmo vazio (rascunho)
    salvarResposta(exercicioId, resposta || ' ', function() {}, function() {
        clearButtonLoading(btnSalvar);
    });
}

function enviarEventoAuditoria(evento) {
    var fd = new FormData();
    fd.append('exercicio_id', evento.exercicio_id);
    fd.append('modulo_id', evento.modulo_id);
    fd.append('tipo_acao', evento.tipo_acao);
    fd.append('de_valor', evento.de_valor);
    fd.append('para_valor', evento.para_valor);

    return fetch('<?= URL ?>/jornadas/auditoria-exercicio-evento', {
        method: 'POST',
        body: fd
    }).then(function(response) {
        if (!response.ok) {
            throw new Error('Falha no envio individual de auditoria');
        }
        return response.json().catch(function() { return { success: false }; });
    }).then(function(data) {
        if (!data || data.success !== true) {
            throw new Error('Envio individual retornou erro');
        }
        return data;
    });
}

function enviarEventosAuditoriaLote(eventos) {
    return fetch('<?= URL ?>/jornadas/auditoria-exercicio-evento-lote', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ eventos: eventos })
    }).then(function(response) {
        if (!response.ok) {
            throw new Error('Falha no lote de auditoria');
        }
        return response.json().catch(function() { return { success: false }; });
    }).then(function(data) {
        if (!data || data.success !== true) {
            throw new Error('Lote retornou erro');
        }
        return data;
    });
}

function enviarEventoAuditoriaComRetry(evento, tentativa) {
    var tentativaAtual = tentativa || 0;
    return enviarEventoAuditoria(evento).catch(function() {
        if (tentativaAtual >= 1) return;
        return enviarEventoAuditoriaComRetry(evento, tentativaAtual + 1);
    });
}

function flushAuditoriaFila() {
    if (auditoriaFlushEmAndamento) return;
    if (!auditoriaPendencias.length) return;
    auditoriaFlushEmAndamento = true;

    var eventos = auditoriaPendencias.splice(0, auditoriaPendencias.length);
    enviarEventosAuditoriaLote(eventos).catch(function() {
        return Promise.all(eventos.map(function(evento) {
            return enviarEventoAuditoriaComRetry(evento, 0);
        }));
    }).finally(function() {
        auditoriaFlushEmAndamento = false;
        if (auditoriaPendencias.length) {
            auditoriaFlushTimer = setTimeout(flushAuditoriaFila, AUDITORIA_INTERVALO_MS);
        } else {
            auditoriaFlushTimer = null;
        }
    });
}

function agendarFlushAuditoria() {
    if (auditoriaFlushTimer || auditoriaFlushEmAndamento) return;
    auditoriaFlushTimer = setTimeout(flushAuditoriaFila, AUDITORIA_INTERVALO_MS);
}

function registrarAuditoriaAlternativa(exercicioId, respostaNova) {
    if (!exercicioId || isPreview) return;
    var exAtual = exercicios[exercicioAtualIndex] || {};
    var respostaAnterior = (exAtual.resposta_salva || '').toString().trim();
    var nova = (respostaNova || '').toString().trim();
    if (respostaAnterior === nova) return;

    var evento = {
        exercicio_id: exercicioId,
        modulo_id: moduloId,
        tipo_acao: respostaAnterior ? 'alternativa_alterada' : 'alternativa_marcada',
        de_valor: respostaAnterior,
        para_valor: nova
    };
    var idxExistente = auditoriaPendencias.findIndex(function(item) {
        return item.exercicio_id === exercicioId;
    });
    if (idxExistente >= 0) {
        auditoriaPendencias[idxExistente] = evento;
    } else {
        auditoriaPendencias.push(evento);
    }
    agendarFlushAuditoria();
}

function salvarRespostaComAuditoria(exercicioId, resposta) {
    registrarAuditoriaAlternativa(exercicioId, resposta);
    salvarResposta(exercicioId, resposta);
}

function salvarResposta(exercicioId, resposta, callbackSucesso, callbackFinalizado) {
    if (!exercicioId) {
        console.error('Dados inválidos: exercicioId obrigatório', { exercicioId, moduloId });
        alert('Erro: Dados inválidos para salvar resposta');
        return;
    }
    // Resposta pode ser vazia apenas para dissertativa (enviar como string)
    if (resposta === undefined || resposta === null) {
        resposta = '';
    }
    resposta = String(resposta);
    salvarPendenciaResposta(exercicioId, resposta);
    if (respostasEmEnvio[exercicioId]) {
        return;
    }
    respostasEmEnvio[exercicioId] = true;
    
    var ehFinalizar = window.exerciciosFinalizando;
    showExerciciosEnviando('Salvando sua resposta..');
    
    const formData = new FormData();
    formData.append('exercicio_id', exercicioId);
    formData.append('modulo_id', moduloId);
    formData.append('resposta', resposta);
    
    console.log('Enviando resposta:', { exercicioId, moduloId, resposta });
    
    function enviarComRetry(tentativa) {
        return fetch('<?= URL ?>/jornadas/responder-exercicio-modulo', {
            method: 'POST',
            body: formData
        })
        .then(async function(response) {
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                const data = await response.json();
                if (!response.ok && isErroTransienteConexao(data && data.error ? data.error : '')) {
                    throw new Error(data.error || 'Instabilidade temporária de conexão');
                }
                return data;
            }
            const text = await response.text();
            throw new Error('Erro ' + response.status + ': ' + text.substring(0, 200));
        })
        .catch(function(error) {
            if (tentativa < RESPOSTA_MAX_RETRIES && isErroTransienteConexao(error && error.message ? error.message : '')) {
                showExerciciosEnviando('Salvando sua resposta..');
                return delay(800 * (tentativa + 1)).then(function() {
                    return enviarComRetry(tentativa + 1);
                });
            }
            throw error;
        });
    }

    enviarComRetry(0)
    .then(function(data) {
        console.log('Resposta recebida:', data);
        delete respostasEmEnvio[exercicioId];
        if (typeof callbackFinalizado === 'function') callbackFinalizado();
        if (!ehFinalizar) hideExerciciosEnviando();
        if (data.success) {
            removerPendenciaResposta(exercicioId);
            exercicios[exercicioAtualIndex].progresso_status = 'concluido';
            exercicios[exercicioAtualIndex].resposta_salva = resposta.trim ? resposta.trim() : resposta;
            atualizarInterface();
            if (data.modulo_concluido) {
                exercicioFinalizado = true;
            }
            if (typeof callbackSucesso === 'function') callbackSucesso();
        } else {
            console.error('Erro na resposta:', data);
            alert('Erro ao salvar resposta: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(function(error) {
        console.error('Erro ao salvar resposta:', error);
        delete respostasEmEnvio[exercicioId];
        if (typeof callbackFinalizado === 'function') callbackFinalizado();
        if (!ehFinalizar) hideExerciciosEnviando();
        alert('Instabilidade temporária. Sua resposta ficou salva localmente e será reenviada. Detalhe: ' + error.message);
    });
}

function atualizarInterface() {
    // Atualiza botão da questão atual
    const btnAtual = document.querySelector(`[data-questao="${exercicioAtualIndex}"]`);
    if (btnAtual && exercicios[exercicioAtualIndex].progresso_status === 'concluido') {
        btnAtual.classList.remove('bg-gray-100', 'text-gray-700');
        btnAtual.classList.add('bg-green-100', 'text-green-800', 'border-2', 'border-green-300');
    }
    
    // Atualiza botão de número pela resposta salva (não pelo gabarito); dissertativa em branco não conta
    const todosComResposta = exercicios.every(ex => ex.resposta_salva && String(ex.resposta_salva).trim() !== '');
    if (todosComResposta) {
        exercicioFinalizado = true;
    }
}

function irParaQuestao(index) {
    if (index >= 0 && index < totalExercicios) {
        navegacaoIntencional = true;
        var url = '<?= URL ?>/jornadas/' + jornadaId + '/modulos/' + moduloId + '/exercicios/' + index;
        if (isPreview) url += '?preview=1';
        window.location.href = url;
    }
}

function antesDeNavegar(callback) {
    var ex = exercicios[exercicioAtualIndex];
    if (!ex || ex.tipo !== 'dissertativa') {
        callback();
        return;
    }
    var texto = getRespostaDissertativa(ex.id);
    salvarResposta(ex.id, texto || ' ', function() { callback(); });
}

function questaoAnterior() {
    if (exercicioAtualIndex > 0) {
        var btnAnterior = document.querySelector('button[onclick="questaoAnterior()"]');
        setButtonLoading(btnAnterior, 'Indo...');
        antesDeNavegar(function() { irParaQuestao(exercicioAtualIndex - 1); });
    }
}

function questaoProxima() {
    if (exercicioAtualIndex < totalExercicios - 1) {
        var btnProxima = document.getElementById('btn-proxima-questao');
        setButtonLoading(btnProxima, 'Indo...');
        antesDeNavegar(function() { irParaQuestao(exercicioAtualIndex + 1); });
    }
}

function finalizarEtapaERedirecionar() {
    var urlVoltar = isPreview ? '<?= URL ?>/professor/jornadas/<?= (int)$jornada['id'] ?>' : '<?= URL ?>/jornadas/<?= $jornada['id'] ?>';
    if (isPreview) {
        exercicioFinalizado = true;
        navegacaoIntencional = true;
        window.location.href = urlVoltar;
        return;
    }
    showExerciciosEnviando('Salvando sua resposta..');
    var formData = new FormData();
    formData.append('modulo_id', moduloId);
    formData.append('tipo', 'exercicios');

    function enviarFinalizacaoComRetry(tentativa) {
        return fetch('<?= URL ?>/jornadas/finalizar-etapa', {
            method: 'POST',
            body: formData
        }).then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success && tentativa < FINALIZAR_MAX_RETRIES && isErroTransienteConexao(data.error || '')) {
                return delay(1000 * (tentativa + 1)).then(function() {
                    return enviarFinalizacaoComRetry(tentativa + 1);
                });
            }
            return data;
        }).catch(function(err) {
            if (tentativa < FINALIZAR_MAX_RETRIES && isErroTransienteConexao(err && err.message ? err.message : '')) {
                return delay(1000 * (tentativa + 1)).then(function() {
                    return enviarFinalizacaoComRetry(tentativa + 1);
                });
            }
            throw err;
        });
    }

    enviarFinalizacaoComRetry(0)
    .then(function(data) {
        if (data.success) {
            exercicioFinalizado = true;
            navegacaoIntencional = true;
            window.location.href = urlVoltar;
        } else {
            window.exerciciosFinalizando = false;
            hideExerciciosEnviando();
            var btn = document.getElementById('btn-finalizar-exercicios');
            if (btn) { btn.disabled = false; btn.innerHTML = '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Finalizar Exercícios'; }
            alert('Erro ao finalizar etapa: ' + (data.error || 'Tente novamente.'));
        }
    })
    .catch(function(err) {
        console.error(err);
        window.exerciciosFinalizando = false;
        hideExerciciosEnviando();
        var btn = document.getElementById('btn-finalizar-exercicios');
        if (btn) { btn.disabled = false; btn.innerHTML = '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Finalizar Exercícios'; }
        alert('Erro de conexão ao finalizar. Tente novamente.');
    });
}

function finalizarExercicios() {
    var exercicioAtual = exercicios[exercicioAtualIndex];
    var exercicioId = exercicioAtual && exercicioAtual.id;
    var radio = exercicioId ? document.querySelector('input[name="resposta_' + exercicioId + '"]:checked') : null;
    var textareaDissertativa = exercicioId ? document.getElementById('resposta_dissertativa_' + exercicioId) : null;
    var respostaLacuna = exercicioId ? getRespostaLacuna(exercicioId) : '';
    var resposta = radio ? radio.value : (textareaDissertativa ? textareaDissertativa.value.trim() : (respostaLacuna || ''));

    // Atualiza resposta da questão atual no array (para checagem de não respondidas)
    if (exercicioId && resposta) {
        exercicioAtual.resposta_salva = resposta;
    }

    var semResposta = exercicios.filter(function(ex) { return !ex.resposta_salva || String(ex.resposta_salva).trim() === ''; });
    if (semResposta.length > 0) {
        if (!confirm('Há ' + semResposta.length + ' questão(ões) sem resposta. Deseja realmente finalizar?')) {
            return;
        }
    }

    window.exerciciosFinalizando = true;
    showExerciciosEnviando('Salvando sua resposta..');
    var btn = document.getElementById('btn-finalizar-exercicios');
    setButtonLoading(btn, 'Salvando sua resposta..');

    if (exercicioId && (resposta || textareaDissertativa)) {
        var textoFinal = resposta || (textareaDissertativa ? textareaDissertativa.value.trim() : '') || ' ';
        salvarResposta(exercicioId, textoFinal, function() {
            finalizarEtapaERedirecionar();
        });
    } else {
        finalizarEtapaERedirecionar();
    }
}

// Inicializa bloqueios
document.addEventListener('DOMContentLoaded', function() {
    bloquearAcoes();
    
    // Atualiza interface inicial
    atualizarInterface();

    // Reenvio automático de resposta pendente da questão atual (quando houve instabilidade de conexão)
    var exAtual = exercicios[exercicioAtualIndex];
    if (exAtual && exAtual.id) {
        var pendencias = getPendenciasSalvas();
        var pend = pendencias[String(exAtual.id)];
        if (pend && typeof pend.resposta === 'string' && pend.resposta !== '') {
            salvarResposta(exAtual.id, pend.resposta);
        }
    }
    
    // Garantir renderização LaTeX (enunciado e alternativas) na jornada do aluno
    var containerMathJax = document.getElementById('conteudo-exercicio-jornada-mathjax');
    function runMathJaxTypeset() {
        if (!window.MathJax || !window.MathJax.typesetPromise) return;
        var el = containerMathJax || document.body;
        MathJax.typesetPromise([el]).catch(function(err) { console.warn('MathJax typeset:', err); });
    }
    if (containerMathJax && window.MathJax && window.MathJax.typesetPromise) {
        runMathJaxTypeset();
    } else if (containerMathJax) {
        var checkMathJax = setInterval(function() {
            if (window.MathJax && window.MathJax.typesetPromise) {
                clearInterval(checkMathJax);
                runMathJaxTypeset();
            }
        }, 100);
        setTimeout(function() { clearInterval(checkMathJax); }, 10000);
    }
    // Retentativas: script MathJax é async e pode carregar depois do DOMContentLoaded
    setTimeout(runMathJaxTypeset, 800);
    setTimeout(runMathJaxTypeset, 2000);

    // Drag and drop - preencha lacuna
    document.querySelectorAll('[data-lacuna-opcao]').forEach(function(btn) {
        btn.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/plain', this.getAttribute('data-lacuna-opcao') || '');
        });
        btn.addEventListener('click', function() {
            var wrap = this.closest('[id^="lacuna-wrapper-"]');
            if (!wrap) return;
            var drop = wrap.querySelector('[data-lacuna-drop].is-active') || wrap.querySelector('[data-lacuna-drop]');
            if (!drop) return;
            var valor = this.getAttribute('data-lacuna-opcao') || '';
            drop.textContent = valor;
            drop.classList.remove('text-blue-700');
            drop.classList.add('text-gray-900');
            var exercicioId = wrap.getAttribute('data-exercicio-id');
            if (exercicioId) {
                var respostaCompleta = getRespostaLacuna(parseInt(exercicioId, 10));
                if (respostaCompleta) salvarRespostaComAuditoria(parseInt(exercicioId, 10), respostaCompleta);
            }
        });
    });

    document.querySelectorAll('[data-lacuna-drop]').forEach(function(drop) {
        drop.addEventListener('click', function() {
            document.querySelectorAll('[data-lacuna-drop]').forEach(function(d) { d.classList.remove('is-active', 'ring-2', 'ring-blue-300'); });
            this.classList.add('is-active', 'ring-2', 'ring-blue-300');
        });
        drop.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('ring-2', 'ring-blue-300');
        });
        drop.addEventListener('dragleave', function() {
            this.classList.remove('ring-2', 'ring-blue-300');
        });
        drop.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('ring-2', 'ring-blue-300');
            var valor = e.dataTransfer.getData('text/plain');
            if (!valor) return;
            this.textContent = valor;
            this.classList.remove('text-blue-700');
            this.classList.add('text-gray-900');
            var wrap = this.closest('[id^="lacuna-wrapper-"]');
            if (!wrap) return;
            var exercicioId = wrap.getAttribute('data-exercicio-id');
            if (exercicioId) {
                var respostaCompleta = getRespostaLacuna(parseInt(exercicioId, 10));
                if (respostaCompleta) salvarRespostaComAuditoria(parseInt(exercicioId, 10), respostaCompleta);
            }
        });
    });
});
</script>
