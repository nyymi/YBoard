<?php
namespace YBoard\Models;

use YFW\Library\Database;
use YBoard\BaseModel;

class Notification extends BaseModel
{
    public $id;
    public $time;
    public $type;
    public $postId;
    public $customData;
    public $count;
    public $isRead;
    public $text;

    public function remove() : bool
    {
        $q = $this->db->prepare("DELETE FROM user_notifications WHERE id = :id LIMIT 1");
        $q->bindValue('id', $this->id, Database::PARAM_INT);
        $q->execute();

        return true;
    }

    public function markRead() : bool
    {
        $q = $this->db->prepare("UPDATE user_notifications SET count = 0, is_read = 1
            WHERE id = :id AND is_read = 0 LIMIT 1");
        $q->bindValue('id', $this->id, Database::PARAM_INT);
        $q->execute();

        return true;
    }
}
