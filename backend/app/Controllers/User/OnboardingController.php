<?php
/**
 * EducaTudo - Controller de Onboarding
 * Gerencia perfil de onboarding dos alunos
 */

require_once __DIR__ . '/../../Core/BaseController.php';

if (!class_exists('OnboardingController')) {
class OnboardingController extends BaseController
{
    private $authManager;
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
    }

    /**
     * Verifica se aluno completou onboarding
     */
    public function verificarOnboarding()
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                $this->json(['completado' => true]);
                return;
            }

            $onboarding = $this->db->fetch(
                "SELECT * FROM alunos_onboarding WHERE aluno_id = :aluno_id",
                ['aluno_id' => $user['id']]
            );

            $completado = $onboarding && $onboarding['completado'] == 1;

            $this->json([
                'completado' => $completado,
                'dados' => $onboarding ?: null
            ]);

        } catch (Exception $e) {
            error_log("Erro ao verificar onboarding: " . $e->getMessage());
            $this->json(['completado' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Salva dados de onboarding
     */
    public function salvar()
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }

            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
                return;
            }

            // Validar dados
            $dados = [
                'aluno_id' => $user['id'],
                'meu_sonho' => trim($_POST['meu_sonho'] ?? ''),
                'objetivo_principal' => trim($_POST['objetivo_principal'] ?? ''),
                'nivel_comprometimento' => trim($_POST['nivel_comprometimento'] ?? ''),
                'pontos_dificuldade' => trim($_POST['pontos_dificuldade'] ?? ''),
                'tempo_estudo_dia' => trim($_POST['tempo_estudo_dia'] ?? ''),
                'pontos_fortes' => trim($_POST['pontos_fortes'] ?? ''),
                'estilo_aprendizado' => trim($_POST['estilo_aprendizado'] ?? ''),
                'completado' => 1
            ];

            // Verificar se já existe
            $existente = $this->db->fetch(
                "SELECT id FROM alunos_onboarding WHERE aluno_id = :aluno_id",
                ['aluno_id' => $user['id']]
            );

            if ($existente) {
                // Atualizar
                $this->db->update(
                    "UPDATE alunos_onboarding SET 
                     meu_sonho = ?,
                     objetivo_principal = ?,
                     nivel_comprometimento = ?,
                     pontos_dificuldade = ?,
                     tempo_estudo_dia = ?,
                     pontos_fortes = ?,
                     estilo_aprendizado = ?,
                     completado = ?,
                     updated_at = NOW()
                     WHERE id = ?",
                    [
                        $dados['meu_sonho'],
                        $dados['objetivo_principal'],
                        $dados['nivel_comprometimento'],
                        $dados['pontos_dificuldade'],
                        $dados['tempo_estudo_dia'],
                        $dados['pontos_fortes'],
                        $dados['estilo_aprendizado'],
                        $dados['completado'],
                        $existente['id']
                    ]
                );
            } else {
                // Criar novo
                $this->db->insert(
                    "INSERT INTO alunos_onboarding 
                     (aluno_id, meu_sonho, objetivo_principal, nivel_comprometimento, 
                      pontos_dificuldade, tempo_estudo_dia, pontos_fortes, estilo_aprendizado, completado) 
                     VALUES 
                     (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $dados['aluno_id'],
                        $dados['meu_sonho'],
                        $dados['objetivo_principal'],
                        $dados['nivel_comprometimento'],
                        $dados['pontos_dificuldade'],
                        $dados['tempo_estudo_dia'],
                        $dados['pontos_fortes'],
                        $dados['estilo_aprendizado'],
                        $dados['completado']
                    ]
                );
            }

            $this->json(['success' => true, 'message' => 'Perfil de onboarding salvo com sucesso!']);

        } catch (Exception $e) {
            error_log("Erro ao salvar onboarding: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Busca dados de onboarding do aluno
     */
    public function buscar()
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }

            $onboarding = $this->db->fetch(
                "SELECT * FROM alunos_onboarding WHERE aluno_id = :aluno_id",
                ['aluno_id' => $user['id']]
            );

            $this->json([
                'success' => true,
                'dados' => $onboarding ?: null
            ]);

        } catch (Exception $e) {
            error_log("Erro ao buscar onboarding: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
}
