<?php

namespace App\Modulos\LeitorGabarito\Controllers;

use App\Modulos\LeitorGabarito\Services\CodigoCapturaCelular;
use App\Modulos\LeitorGabarito\Services\LeitorGabaritoService;

/**
 * Página aberta pelo QR no celular. Sem sessão: o token só autoriza a foto desta prova.
 */
if (!class_exists(__NAMESPACE__ . '\\CapturaGabaritoController', false)) {
class CapturaGabaritoController extends \App\Core\BaseController
{
    public function abrir(string $token): void
    {
        $acesso = CodigoCapturaCelular::abrir($token);
        if ($acesso === null) {
            $this->pagina($token, 'Este QR Code expirou ou não é desta escola. Gere outro na prova.', '');
            return;
        }
        $this->pagina($token, '', $this->titulo($acesso['prova_id'], $acesso['professor_id'], (int) ($acesso['conjunto'] ?? 0)));
    }

    public function enviar(string $token): void
    {
        @set_time_limit(90);
        $acesso = CodigoCapturaCelular::abrir($token);
        if ($acesso === null) {
            $this->pagina($token, 'Este QR Code expirou ou não é desta escola. Gere outro na prova.', '');
            return;
        }
        $arquivo = $_FILES['foto'] ?? null;
        if (!is_array($arquivo)) {
            $this->pagina($token, 'Tire a foto do gabarito inteiro.', $this->titulo($acesso['prova_id'], $acesso['professor_id'], (int) ($acesso['conjunto'] ?? 0)));
            return;
        }
        try {
            $professorId = $acesso['professor_id'] > 0 ? $acesso['professor_id'] : null;
            $resultado = (new LeitorGabaritoService())->receberScans(
                $acesso['prova_id'],
                $professorId,
                0,
                [[
                    'name' => (string) ($arquivo['name'] ?? 'foto.jpg'),
                    'tmp_name' => (string) ($arquivo['tmp_name'] ?? ''),
                    'error' => (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int) ($arquivo['size'] ?? 0),
                ]],
                (int) ($acesso['conjunto'] ?? 0)
            );
            $mensagem = $this->resumo($resultado);
        } catch (\PDOException $e) {
            error_log('captura gabarito: ' . $e->getMessage());
            $mensagem = 'Não foi possível ler esta foto. Tente de novo.';
        } catch (\RuntimeException $e) {
            $mensagem = $e->getMessage();
        } catch (\Throwable $e) {
            error_log('captura gabarito: ' . $e->getMessage());
            $mensagem = 'Não foi possível ler esta foto. Tente de novo.';
        }
        $this->pagina($token, $mensagem, $this->titulo($acesso['prova_id'], $acesso['professor_id'], (int) ($acesso['conjunto'] ?? 0)));
    }

    private function titulo(int $provaId, int $professorId, int $conjunto = 0): string
    {
        $professor = $professorId > 0 ? $professorId : null;
        $prova = (new LeitorGabaritoService())->detalharProva($provaId, $professor, 1);
        if ($prova === null) {
            return 'Gabarito';
        }
        $nome = trim((string) ($prova['prova']['titulo'] ?? 'Gabarito'));
        if ($conjunto === 1) {
            return $nome . ' · todas as matérias';
        }
        $materia = trim((string) ($prova['prova']['materia_nome'] ?? ''));
        return $materia !== '' ? $nome . ' · ' . $materia : $nome;
    }

    /**
     * @param array{lidas:int,revisao:int,erros:int} $resultado
     */
    private function resumo(array $resultado): string
    {
        if (($resultado['lidas'] ?? 0) > 0 && ($resultado['revisao'] ?? 0) === 0 && ($resultado['erros'] ?? 0) === 0) {
            return 'Foto lida. A nota foi lançada. Pode fotografar outro gabarito.';
        }
        if (($resultado['revisao'] ?? 0) > 0) {
            return 'Foto recebida. A marcação ficou para conferência no computador.';
        }
        return 'Não consegui ler esta foto. Enquadre a folha inteira, com as quatro marcas pretas dos cantos.';
    }

    private function pagina(string $token, string $mensagem, string $titulo): void
    {
        $valido = CodigoCapturaCelular::abrir($token) !== null;
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex');
        include dirname(__DIR__) . '/Views/captura.php';
    }
}
}
