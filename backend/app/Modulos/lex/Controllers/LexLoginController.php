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

        $_SESSION['lex_oauth'] = [
            'state' => $inicio['state'],
            'verifier' => $inicio['verifier'],
            'nonce' => $inicio['nonce'],
            'perfil' => $perfil,
            'expira' => time() + 600,
        ];
        header('Location: ' . $inicio['url'], true, 302);
        exit;
    }

    public function retorno(): void
    {
        $sessao = $_SESSION['lex_oauth'] ?? null;
        $perfil = is_array($sessao) ? (string) ($sessao['perfil'] ?? '') : '';
        $state = trim((string) ($_GET['state'] ?? ''));
        $stateSessao = is_array($sessao) ? (string) ($sessao['state'] ?? '') : '';
        if (!is_array($sessao) || $state === '' || $stateSessao === '' || !hash_equals($stateSessao, $state)) {
            $this->setFlashMessage('O retorno da LEX não confere com o início do login.', 'error');
            $this->redirect($this->rotaLogin($perfil) . '?t=' . time());
            return;
        }

        unset($_SESSION['lex_oauth']);
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

    private function redirectUri(): string
    {
        // Endereço cadastrado na LEX para o teste. Tem de ser idêntico, com a porta.
        return 'http://localhost:8080/lex/retorno';
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
