<?php
namespace YBoard\Models;

use YBoard\Abstracts\AbstractUserModel;
use YFW\Library\Database;

class UserThreadFollow extends AbstractUserModel
{
    protected $threads = [];
    public $unreadCount = 0;

    public function add(int $threadId) : bool
    {
        $q = $this->db->prepare("INSERT IGNORE INTO user_thread_follow (user_id, thread_id) VALUES (:user_id, :thread_id)");
        $q->bindValue('user_id', $this->userId, Database::PARAM_INT);
        $q->bindValue('thread_id', $threadId, Database::PARAM_INT);
        $q->execute();

        return true;
    }

    public function exists(int $threadId) : bool
    {
        return array_key_exists($threadId, $this->threads);
    }

    public function getFollowers(int $threadId) : array
    {
        $q = $this->db->prepare("SELECT user_id FROM user_thread_follow WHERE thread_id = :thread_id");
        $q->bindValue('thread_id', $threadId, Database::PARAM_INT);
        $q->execute();

        return $q->fetchAll(Database::FETCH_COLUMN);
    }

    public function getAll()
    {
        return $this->threads;
    }

    public function get(int $threadId)
    {
        if (empty($this->threads[$threadId])) {
            return false;
        }

        return $this->threads[$threadId];
    }

    public function getThreadUnreadCount(int $threadId)
    {
        if (empty($this->threads[$threadId])) {
            return false;
        }

        return $this->threads[$threadId]->unreadCount;
    }

    public function getThreadLastSeenReply(int $threadId)
    {
        if (empty($this->threads[$threadId]) || empty($this->threads[$threadId]->lastSeenReply)) {
            return false;
        }

        return $this->threads[$threadId]->lastSeenReply;
    }

    public function incrementUnreadCount(int $threadId, int $userNot = 0) : bool
    {
        $q = $this->db->prepare("UPDATE user_thread_follow SET unread_count = unread_count+1
            WHERE thread_id = :thread_id AND user_id != :user_id");
        $q->bindValue('thread_id', $threadId, Database::PARAM_INT);
        $q->bindValue('user_id', $userNot, Database::PARAM_INT);
        $q->execute();

        return true;
    }

    public function markAllRead() : bool
    {
        $q = $this->db->prepare("UPDATE user_thread_follow SET unread_count = 0 WHERE user_id = :user_id");
        $q->bindValue('user_id', $this->userId, Database::PARAM_INT);
        $q->execute();

        return true;
    }

    protected function load() : bool
    {
        $q = $this->db->prepare("SELECT id, thread_id, last_seen_reply, unread_count
            FROM user_thread_follow WHERE user_id = :user_id ORDER BY unread_count DESC");
        $q->bindValue('user_id', $this->userId, Database::PARAM_INT);
        $q->execute();

        $this->threads = [];
        while ($data = $q->fetch()) {
            $thread = new FollowedThread($this->db);
            $thread->id = $data->id;
            $thread->threadId = $data->thread_id;
            $thread->lastSeenReply = $data->last_seen_reply;
            $thread->unreadCount = $data->unread_count;

            $this->threads[$data->thread_id] = $thread;
        }

        if (!empty($this->threads)) {
            $q = $this->db->prepare("SELECT SUM(unread_count) AS unread_count FROM user_thread_follow
            WHERE user_id = :user_id LIMIT 1");
            $q->bindValue('user_id', $this->userId, Database::PARAM_INT);
            $q->execute();

            $this->unreadCount = $q->fetch(Database::FETCH_COLUMN);
        }

        return true;
    }
}
