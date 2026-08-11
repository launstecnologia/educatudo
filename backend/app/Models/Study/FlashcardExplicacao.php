<?php
/**
 * Explicações da Tudinha para cartões que o aluno marcou como "não entendi".
 * Permite ao aluno acessar as explicações a qualquer momento.
 */
require_once __DIR__ . '/../../Core/Database.php';

class FlashcardExplicacao
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Salva uma nova explicação (uma tentativa por vez).
     */
    public function salvar($alunoId, $deckId, $cardId, $explicacao)
    {
        $numero = $this->proximoNumeroTentativa($alunoId, $deckId, $cardId);
        $this->db->query(
            'INSERT INTO flashcard_explicacoes (aluno_id, deck_id, card_id, explicacao, origem, numero_tentativa) VALUES (:aid, :did, :cid, :exp, :origem, :num)',
            [
                'aid' => (int) $alunoId,
                'did' => (int) $deckId,
                'cid' => (int) $cardId,
                'exp' => $explicacao,
                'origem' => 'ia',
                'num' => $numero,
            ]
        );
    }

    /**
     * Retorna o próximo número de tentativa para (aluno, deck, card).
     */
    public function proximoNumeroTentativa($alunoId, $deckId, $cardId)
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(MAX(numero_tentativa), 0) + 1 AS next_num FROM flashcard_explicacoes WHERE aluno_id = :aid AND deck_id = :did AND card_id = :cid',
            [
                'aid' => (int) $alunoId,
                'did' => (int) $deckId,
                'cid' => (int) $cardId,
            ]
        );
        return (int) ($row['next_num'] ?? 1);
    }

    /**
     * Lista explicações salvas por cartão para um aluno em um deck.
     * Retorna [ card_id => [ texto1, texto2, ... ] ] ordenado por numero_tentativa.
     */
    public function listarPorAlunoDeckCards($alunoId, $deckId, array $cardIds)
    {
        if (empty($cardIds)) {
            return [];
        }
        $ids = array_map('intval', $cardIds);
        $placeholders = implode(',', $ids);
        $rows = $this->db->fetchAll(
            "SELECT card_id, explicacao, numero_tentativa FROM flashcard_explicacoes 
             WHERE aluno_id = :aid AND deck_id = :did AND card_id IN ($placeholders) 
             ORDER BY card_id, numero_tentativa ASC",
            [
                'aid' => (int) $alunoId,
                'did' => (int) $deckId,
            ]
        );
        $porCard = [];
        foreach ($rows as $r) {
            $cid = (int) $r['card_id'];
            if (!isset($porCard[$cid])) {
                $porCard[$cid] = [];
            }
            $porCard[$cid][] = $r['explicacao'];
        }
        return $porCard;
    }
}
