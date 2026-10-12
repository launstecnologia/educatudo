<?php

namespace App\API\Controllers;
/**
 * Heartbeat de presença do aluno (contexto de atividade para monitor)
 */


use App\Core\AuthManager;
use App\Core\BaseController;
if (!class_exists(__NAMESPACE__ . '\\StudentPresenceController', false)) {
    class StudentPresenceController extends BaseController
    {
        public function heartbeat()
        {
            $auth = new AuthManager();
            $user = $auth->getUser();

            if (!$user || $user['tipo'] !== 'aluno') {
                $this->json(['error' => 'Não autorizado'], 403);
                return;
            }

            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
                return;
            }

            $contextoTipo = trim((string) ($_POST['contexto_tipo'] ?? ''));
            $contextoId = (int) ($_POST['contexto_id'] ?? 0);
            $contextoLabel = trim((string) ($_POST['contexto_label'] ?? ''));

            if ($contextoTipo === '' || $contextoId <= 0) {
                $inferred = $this->inferirContextoDaUrl($_POST['url'] ?? ($_SERVER['HTTP_REFERER'] ?? ''));
                if ($contextoTipo === '') {
                    $contextoTipo = $inferred['contexto_tipo'];
                }
                if ($contextoId <= 0) {
                    $contextoId = $inferred['contexto_id'];
                }
                if ($contextoLabel === '') {
                    $contextoLabel = $inferred['contexto_label'];
                }
            }

            $auth->atualizarPresencaAluno((int) $user['id'], [
                'contexto_tipo' => $contextoTipo,
                'contexto_id' => $contextoId,
                'contexto_label' => $contextoLabel,
            ]);

            $this->json(['success' => true]);
        }

        private function inferirContextoDaUrl(string $url): array
        {
            $path = parse_url($url, PHP_URL_PATH) ?: '';
            $result = [
                'contexto_tipo' => 'navegacao',
                'contexto_id' => 0,
                'contexto_label' => '',
            ];

            if (preg_match('#/aluno/provas/(\d+)/realizar#', $path, $m)) {
                return [
                    'contexto_tipo' => 'prova',
                    'contexto_id' => (int) $m[1],
                    'contexto_label' => 'Prova em andamento',
                ];
            }
            if (preg_match('#/aluno/jornadas/(\d+)#', $path, $m)) {
                return [
                    'contexto_tipo' => 'jornada',
                    'contexto_id' => (int) $m[1],
                    'contexto_label' => 'Jornada',
                ];
            }
            if (preg_match('#/aluno/redacoes#', $path)) {
                return ['contexto_tipo' => 'redacao', 'contexto_id' => 0, 'contexto_label' => 'Redação'];
            }
            if (preg_match('#/dashboard#', $path)) {
                return ['contexto_tipo' => 'home', 'contexto_id' => 0, 'contexto_label' => 'Início'];
            }

            return $result;
        }
    }
}

if (!class_exists('StudentPresenceController', false) && class_exists(__NAMESPACE__ . '\\StudentPresenceController', false)) {
    class_alias(__NAMESPACE__ . '\\StudentPresenceController', 'StudentPresenceController');
}
