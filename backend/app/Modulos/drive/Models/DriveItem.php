<?php
/**
 * Drive EducaTudo - Item (pasta ou arquivo)
 */
require_once __DIR__ . '/../../../Core/Database.php';

class DriveItem
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function createFolder($ownerId, $ownerType, $parentId, $name)
    {
        $this->db->query(
            'INSERT INTO drive_itens (owner_id, owner_type, parent_id, name, type) VALUES (:oid, :otype, :pid, :name, \'folder\')',
            [
                'oid' => (int) $ownerId,
                'otype' => $ownerType === 'aluno' ? 'student' : 'teacher',
                'pid' => $parentId ? (int) $parentId : null,
                'name' => trim($name),
            ]
        );
        return (int) $this->db->getPdo()->lastInsertId();
    }

    public function createFile($ownerId, $ownerType, $parentId, $name, $filePath, $fileSize, $mimeType = null)
    {
        $this->db->query(
            'INSERT INTO drive_itens (owner_id, owner_type, parent_id, name, type, file_path, file_size, mime_type) VALUES (:oid, :otype, :pid, :name, \'file\', :fp, :fs, :mime)',
            [
                'oid' => (int) $ownerId,
                'otype' => $ownerType === 'aluno' ? 'student' : 'teacher',
                'pid' => $parentId ? (int) $parentId : null,
                'name' => trim($name),
                'fp' => $filePath,
                'fs' => (int) $fileSize,
                'mime' => $mimeType,
            ]
        );
        return (int) $this->db->getPdo()->lastInsertId();
    }

    public function getById($id)
    {
        return $this->db->fetch('SELECT * FROM drive_itens WHERE id = :id LIMIT 1', ['id' => (int) $id]);
    }

    /** Lista filhos diretos que eu sou dono (mesmo owner_id/owner_type e parent_id) */
    public function listChildren($ownerId, $ownerType, $parentId)
    {
        $otype = $ownerType === 'aluno' ? 'student' : 'teacher';
        $params = ['oid' => (int) $ownerId, 'otype' => $otype];
        $parentCond = $parentId === null || $parentId === '' ? 'parent_id IS NULL' : 'parent_id = :pid';
        if ($parentId !== null && $parentId !== '') {
            $params['pid'] = (int) $parentId;
        }
        return $this->db->fetchAll(
            "SELECT * FROM drive_itens WHERE owner_id = :oid AND owner_type = :otype AND {$parentCond} ORDER BY type ASC, name ASC",
            $params
        );
    }

    /** Lista filhos diretos de uma pasta (por id do item) - para navegar em pasta própria ou compartilhada */
    public function listChildrenByParentId($parentId)
    {
        return $this->db->fetchAll(
            'SELECT * FROM drive_itens WHERE parent_id = :pid ORDER BY type ASC, name ASC',
            ['pid' => (int) $parentId]
        );
    }

    public function updateName($id, $name)
    {
        $this->db->query('UPDATE drive_itens SET name = :name, updated_at = NOW() WHERE id = :id', [
            'id' => (int) $id,
            'name' => trim($name),
        ]);
    }

    public function delete($id)
    {
        $this->db->query('DELETE FROM drive_itens WHERE id = :id', ['id' => (int) $id]);
    }

    /** Breadcrumb: do root até o item (array de [id => ..., name => ...]) */
    public function getBreadcrumb($itemId)
    {
        $item = $this->getById($itemId);
        if (!$item) {
            return [];
        }
        $path = [['id' => $item['id'], 'name' => $item['name']]];
        $current = $item;
        while (!empty($current['parent_id'])) {
            $current = $this->getById($current['parent_id']);
            if (!$current) {
                break;
            }
            array_unshift($path, ['id' => $current['id'], 'name' => $current['name']]);
        }
        return $path;
    }

    /** Verifica se nome já existe no mesmo nível (mesmo parent e owner) */
    public function nameExistsInFolder($ownerId, $ownerType, $parentId, $name, $excludeId = null)
    {
        $otype = $ownerType === 'aluno' ? 'student' : 'teacher';
        $params = ['oid' => (int) $ownerId, 'otype' => $otype, 'name' => trim($name)];
        $parentCond = $parentId === null || $parentId === '' ? 'parent_id IS NULL' : 'parent_id = :pid';
        if ($parentId !== null && $parentId !== '') {
            $params['pid'] = (int) $parentId;
        }
        $sql = "SELECT 1 FROM drive_itens WHERE owner_id = :oid AND owner_type = :otype AND {$parentCond} AND name = :name";
        if ($excludeId) {
            $sql .= ' AND id != :eid';
            $params['eid'] = (int) $excludeId;
        }
        $row = $this->db->fetch($sql . ' LIMIT 1', $params);
        return (bool) $row;
    }

    /** Retorna todos os file_path dos descendentes (para exclusão física). Inclui o próprio item se for arquivo. */
    public function getDescendantFilePaths($itemId, $baseDir)
    {
        $item = $this->getById($itemId);
        if (!$item) {
            return [];
        }
        $paths = [];
        if ($item['type'] === 'file' && !empty($item['file_path'])) {
            $paths[] = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $item['file_path']);
        }
        $children = $this->listChildrenByParentId($itemId);
        foreach ($children as $c) {
            $paths = array_merge($paths, $this->getDescendantFilePaths($c['id'], $baseDir));
        }
        return $paths;
    }

    /** Retorna as chaves (file_path) dos arquivos descendentes para exclusão (S3 ou local). */
    public function getDescendantFileKeys($itemId)
    {
        $item = $this->getById($itemId);
        if (!$item) {
            return [];
        }
        $keys = [];
        if ($item['type'] === 'file' && !empty($item['file_path'])) {
            $keys[] = $item['file_path'];
        }
        $children = $this->listChildrenByParentId($itemId);
        foreach ($children as $c) {
            $keys = array_merge($keys, $this->getDescendantFileKeys($c['id']));
        }
        return $keys;
    }
}
