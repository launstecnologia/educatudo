<?php
/**
 * Drive EducaTudo - Compartilhamentos
 */
require_once __DIR__ . '/../../Core/Database.php';

class DriveShare
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function add($itemId, $sharedWithId, $sharedWithType, $permission = 'view')
    {
        $swType = $sharedWithType === 'aluno' ? 'student' : 'teacher';
        $this->db->query(
            'INSERT INTO drive_compartilhamentos (item_id, shared_with_id, shared_with_type, permission) VALUES (:iid, :wid, :wtype, :perm)
             ON DUPLICATE KEY UPDATE permission = :perm2',
            [
                'iid' => (int) $itemId,
                'wid' => (int) $sharedWithId,
                'wtype' => $swType,
                'perm' => $permission === 'edit' ? 'edit' : 'view',
                'perm2' => $permission === 'edit' ? 'edit' : 'view',
            ]
        );
    }

    public function remove($itemId, $sharedWithId, $sharedWithType)
    {
        $swType = $sharedWithType === 'aluno' ? 'student' : 'teacher';
        $this->db->query(
            'DELETE FROM drive_compartilhamentos WHERE item_id = :iid AND shared_with_id = :wid AND shared_with_type = :wtype',
            ['iid' => (int) $itemId, 'wid' => (int) $sharedWithId, 'wtype' => $swType]
        );
    }

    public function listByItem($itemId)
    {
        return $this->db->fetchAll('SELECT * FROM drive_compartilhamentos WHERE item_id = :iid ORDER BY created_at DESC', ['iid' => (int) $itemId]);
    }

    /** Itens compartilhados com este usuário (apenas o item raiz de cada compartilhamento) */
    public function listSharedWithMe($userId, $userType)
    {
        $swType = $userType === 'aluno' ? 'student' : 'teacher';
        return $this->db->fetchAll(
            'SELECT s.*, i.name, i.type, i.parent_id, i.owner_id, i.owner_type
             FROM drive_compartilhamentos s
             INNER JOIN drive_itens i ON i.id = s.item_id
             WHERE s.shared_with_id = :wid AND s.shared_with_type = :wtype
             ORDER BY i.name ASC',
            ['wid' => (int) $userId, 'wtype' => $swType]
        );
    }

    /** Retorna a permissão do usuário sobre o item: 'owner' | 'edit' | 'view' | null (sem acesso) */
    public function getPermission($itemId, $userId, $userType)
    {
        $item = $this->db->fetch('SELECT owner_id, owner_type FROM drive_itens WHERE id = :id', ['id' => (int) $itemId]);
        if (!$item) {
            return null;
        }
        $otype = $item['owner_type'] === 'student' ? 'aluno' : 'professor';
        if ((int) $item['owner_id'] === (int) $userId && $otype === $userType) {
            return 'owner';
        }
        $swType = $userType === 'aluno' ? 'student' : 'teacher';
        $share = $this->db->fetch(
            'SELECT permission FROM drive_compartilhamentos WHERE item_id = :iid AND shared_with_id = :wid AND shared_with_type = :wtype',
            ['iid' => (int) $itemId, 'wid' => (int) $userId, 'wtype' => $swType]
        );
        if ($share) {
            return $share['permission'];
        }
        return null;
    }

    /** Verifica se o usuário tem pelo menos acesso de visualização (dono ou compartilhado) */
    public function canAccess($itemId, $userId, $userType)
    {
        return $this->getPermission($itemId, $userId, $userType) !== null;
    }

    /** Verifica se pode editar (renomear, excluir, compartilhar) ou adicionar arquivos em pasta */
    public function canEdit($itemId, $userId, $userType)
    {
        $p = $this->getPermission($itemId, $userId, $userType);
        return $p === 'owner' || $p === 'edit';
    }
}
