<?php
/**
 * Páginas-modelo do design system admin (só visualização).
 * Acessível apenas com perfil_admin = dev.
 */

require_once __DIR__ . '/AdminBaseController.php';

if (!class_exists('UiModelosController')) {
class UiModelosController extends AdminBaseController
{
    private function exigirDev(): ?array
    {
        $user = $this->auth->getUser();
        if (!$user || ($user['perfil_admin'] ?? '') !== 'dev') {
            $this->redirect('/admin/dashboard');
            return null;
        }
        return $user;
    }

    private function dadosBase(array $user, string $title): array
    {
        $flash = $this->getFlashMessage();
        return [
            'title' => $title,
            'user' => $user,
            'current_page' => 'ui_modelos',
            'csrf_token' => $this->generateCsrfToken(),
            'flash_message' => $flash['message'] ?? '',
            'flash_type' => $flash['type'] ?? '',
        ];
    }

    public function index(): void
    {
        $user = $this->exigirDev();
        if ($user === null) {
            return;
        }

        $this->viewWithLayout(
            'admin',
            'admin/ui-modelos/index',
            $this->dadosBase($user, 'UI Modelos - EducaTudo')
        );
    }

    public function botoes(): void
    {
        $user = $this->exigirDev();
        if ($user === null) {
            return;
        }

        $this->viewWithLayout(
            'admin',
            'admin/ui-modelos/botoes',
            $this->dadosBase($user, 'UI Modelos — Botões')
        );
    }

    public function tabela(): void
    {
        $user = $this->exigirDev();
        if ($user === null) {
            return;
        }

        $this->viewWithLayout(
            'admin',
            'admin/ui-modelos/tabela',
            $this->dadosBase($user, 'UI Modelos — Tabela')
        );
    }

    public function formulario(): void
    {
        $user = $this->exigirDev();
        if ($user === null) {
            return;
        }

        $this->viewWithLayout(
            'admin',
            'admin/ui-modelos/formulario',
            $this->dadosBase($user, 'UI Modelos — Formulário')
        );
    }

    public function formularioEnviar(): void
    {
        $user = $this->exigirDev();
        if ($user === null) {
            return;
        }

        if (!$this->validateCsrf($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token CSRF inválido.', 'error');
            $this->redirect('/admin/configuracao/ui-modelos/formulario');
            return;
        }

        $this->setFlashMessage('Formulário demo enviado (nenhum dado foi salvo).', 'success');
        $this->redirect('/admin/configuracao/ui-modelos/formulario');
    }

    public function offcanvas(): void
    {
        $user = $this->exigirDev();
        if ($user === null) {
            return;
        }

        $this->viewWithLayout(
            'admin',
            'admin/ui-modelos/offcanvas',
            $this->dadosBase($user, 'UI Modelos — Offcanvas')
        );
    }

    public function badges(): void
    {
        $user = $this->exigirDev();
        if ($user === null) {
            return;
        }

        $this->viewWithLayout(
            'admin',
            'admin/ui-modelos/badges',
            $this->dadosBase($user, 'UI Modelos — Badges')
        );
    }

    public function wizard(): void
    {
        $user = $this->exigirDev();
        if ($user === null) {
            return;
        }

        $this->viewWithLayout(
            'admin',
            'admin/ui-modelos/wizard',
            $this->dadosBase($user, 'UI Modelos — Wizard')
        );
    }

    public function wizardEnviar(): void
    {
        $user = $this->exigirDev();
        if ($user === null) {
            return;
        }

        if (!$this->validateCsrf($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token CSRF inválido.', 'error');
            $this->redirect('/admin/configuracao/ui-modelos/wizard');
            return;
        }

        $this->setFlashMessage('Wizard demo concluído (nenhum dado foi salvo).', 'success');
        $this->redirect('/admin/configuracao/ui-modelos/wizard');
    }
}
}
