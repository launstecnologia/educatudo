<?php

namespace App\Modulos\Lex\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Modulos\Lex\Services\LexExcecaoHttp;
use App\Modulos\Lex\Services\LexLoginService;

class LexLoginController extends BaseController
{
    public function entrar(): void
    {
        $perfil = trim((string) ($_GET['perfil'] ?? ''));
        if (!LexLoginService::perfilValido($perfil) || !LexLoginService::disponivel()) {
            $this->setFlashMessage('A integração LEX não está ativa nesta escola.', 'error');
            $this->redirect($this->rotaLogin($perfil) . '?t=' . time());
            return;
        }

        try {
            $inicio = LexLoginService::iniciar($perfil, $this->redirectUri());
        } catch (\Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect($this->rotaLogin($perfil) . '?t=' . time());
            return;
        }

        $inicioLocal = [
            'state' => $inicio['state'],
            'verifier' => $inicio['verifier'],
            'nonce' => $inicio['nonce'],
            'perfil' => $perfil,
            'expira' => time() + 600,
        ];
        $_SESSION['lex_oauth'] = $inicioLocal;
        $this->guardarInicio($inicioLocal);
        header('Location: ' . $inicio['url'], true, 302);
        exit;
    }

    public function retorno(): void
    {
        $sessao = $_SESSION['lex_oauth'] ?? null;
        if (!is_array($sessao)) {
            $sessao = $this->lerInicio();
        }
        $perfil = is_array($sessao) ? (string) ($sessao['perfil'] ?? '') : '';
        $state = trim((string) ($_GET['state'] ?? ''));
        $stateSessao = is_array($sessao) ? (string) ($sessao['state'] ?? '') : '';
        if (!is_array($sessao) || $state === '' || $stateSessao === '' || !hash_equals($stateSessao, $state)) {
            error_log('[lex] retorno sem o inicio do clique');
            $this->limparInicio();
            $this->setFlashMessage('O retorno da LEX não confere com o início do login.', 'error');
            $this->redirect($this->rotaLogin($perfil) . '?t=' . time());
            return;
        }

        unset($_SESSION['lex_oauth']);
        $this->limparInicio();
        $erroLex = trim((string) ($_GET['error'] ?? ''));
        if ($erroLex !== '') {
            $this->setFlashMessage('A LEX não autorizou a entrada.', 'error');
            $this->redirect($this->rotaLogin($perfil) . '?t=' . time());
            return;
        }

        $code = trim((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            $this->setFlashMessage('O retorno da LEX não confere com o início do login.', 'error');
            $this->redirect($this->rotaLogin($perfil) . '?t=' . time());
            return;
        }

        try {
            $vinculo = LexLoginService::concluir($sessao, $code, $state, $this->redirectUri());
            (new AuthManager())->entrarPorLex((string) $vinculo['tipo'], (int) $vinculo['id_local']);
            $idToken = trim((string) ($vinculo['id_token'] ?? ''));
            if ($idToken !== '' && defined('TENANT_ID') && (int) TENANT_ID > 0) {
                $_SESSION['lex_id_token'] = $idToken;
                $_SESSION['lex_tenant_id'] = (int) TENANT_ID;
            }
        } catch (LexExcecaoHttp $e) {
            error_log('[lex] login recusado status=' . $e->httpStatus);
            $this->recusar($perfil);
            return;
        } catch (\Throwable $e) {
            error_log('[lex] login recusado ' . $e::class);
            $this->recusar($perfil, $e->getMessage());
            return;
        }

        $destinos = [
            'aluno' => '/dashboard',
            'admin' => '/admin/dashboard',
            'professor' => '/professor/dashboard',
            'pai' => '/pais/dashboard',
        ];
        $this->redirect($destinos[(string) $vinculo['tipo']] ?? '/');
    }

    public function saida(): void
    {
        $perfil = trim((string) ($_COOKIE['lex_portal'] ?? ''));
        setcookie('lex_portal', '', [
            'expires' => time() - 3600,
            'path' => '/lex',
            'secure' => function_exists('educatudoDetectHttps') && educatudoDetectHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $destinos = [
            'aluno' => '/',
            'admin' => '/admin',
            'professor' => '/professor',
            'pai' => '/pais',
        ];
        $this->redirect(($destinos[$perfil] ?? '/') . '?t=' . time());
    }

    private function recusar(string $perfil, string $mensagem = ''): void
    {
        $conhecidas = [
            'O retorno da LEX não confere com o início do login.',
            'O login da LEX expirou. Tente de novo.',
            'A integração LEX não está ativa nesta escola.',
            'Esta conta LEX ainda não foi sincronizada neste painel.',
            'A LEX devolveu um identificador inválido.',
            'Painel de login inválido.',
        ];
        $texto = in_array($mensagem, $conhecidas, true) ? $mensagem : 'Não foi possível entrar com a LEX.';
        $this->setFlashMessage($texto, 'error');
        $this->redirect($this->rotaLogin($perfil) . '?t=' . time());
    }

    /**
     * @param array{state:string,verifier:string,nonce:string,perfil:string,expira:int} $inicio
     */
    private function guardarInicio(array $inicio): void
    {
        $json = json_encode($inicio);
        if (!is_string($json)) {
            return;
        }
        setcookie('lex_oauth', $json, $this->opcoesCookie(time() + 600));
    }

    /**
     * @return array{state:string,verifier:string,nonce:string,perfil:string,expira:int}|null
     */
    private function lerInicio(): ?array
    {
        $json = (string) ($_COOKIE['lex_oauth'] ?? '');
        $dados = $json !== '' ? json_decode($json, true) : null;
        if (!is_array($dados) || !isset($dados['state'], $dados['verifier'], $dados['nonce'], $dados['perfil'], $dados['expira'])) {
            return null;
        }
        return [
            'state' => (string) $dados['state'],
            'verifier' => (string) $dados['verifier'],
            'nonce' => (string) $dados['nonce'],
            'perfil' => (string) $dados['perfil'],
            'expira' => (int) $dados['expira'],
        ];
    }

    private function limparInicio(): void
    {
        setcookie('lex_oauth', '', $this->opcoesCookie(time() - 3600));
    }

    /**
     * @return array{expires:int,path:string,secure:bool,httponly:bool,samesite:string}
     */
    private function opcoesCookie(int $expira): array
    {
        return [
            'expires' => $expira,
            'path' => '/lex',
            'secure' => function_exists('educatudoDetectHttps') && educatudoDetectHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    private function redirectUri(): string
    {
        return rtrim((string) URL, '/') . '/lex/retorno';
    }

    private function rotaLogin(string $perfil): string
    {
        return [
            'aluno' => '/login',
            'admin' => '/admin',
            'professor' => '/professor',
            'pais' => '/pais',
        ][$perfil] ?? '/login';
    }
}
