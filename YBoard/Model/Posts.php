<?php
namespace YBoard\Model;

use YBoard\Data\File;
use YBoard\Data\Post;
use YBoard\Data\Reply;
use YBoard\Data\Thread;
use YBoard\Data\ThreadStatistics;
use YBoard\Library\BbCode;
use YBoard\Library\Text;
use YBoard\Model;

class Posts extends Model
{
    public function getThreadMeta(int $id)
    {
        $q = $this->db->prepare("SELECT id, board_id, user_id, ip, country_code, time, locked, sticky
            FROM posts WHERE id = :id AND thread_id IS NULL LIMIT 1");
        $q->bindValue('id', (int)$id);
        $q->execute();

        if ($q->rowCount() == 0) {
            return false;
        }

        $row = $q->fetch();

        // Assign values to a class to return
        $thread = new Thread();
        $thread->id = $row->id;
        $thread->locked = (bool)$row->locked;
        $thread->boardId = $row->board_id;
        $thread->userId = $row->user_id;
        $thread->ip = inet_ntop($row->ip);
        $thread->countryCode = $row->country_code;
        $thread->time = date('c', strtotime($row->time));
        $thread->sticky = $row->sticky;

        return $thread;
    }

    public function getThread(int $id)
    {
        $q = $this->db->prepare($this->getPostsQuery("WHERE a.id = :id AND a.thread_id IS NULL LIMIT 1"));
        $q->bindValue('id', (int)$id);
        $q->execute();

        if ($q->rowCount() == 0) {
            return false;
        }

        $row = $q->fetch();

        if (empty($row->subject) && $row->subject != '0') {
            $row->subject = $this->createSubject($row->message);
        }

        // Assign values to a class to return
        $thread = new Thread();
        $thread->id = $row->id;
        $thread->locked = (bool)$row->locked;
        $thread->boardId = $row->board_id;
        $thread->userId = $row->user_id;
        $thread->ip = inet_ntop($row->ip);
        $thread->countryCode = $row->country_code;
        $thread->time = date('c', strtotime($row->time));
        $thread->sticky = $row->sticky;
        $thread->username = Text::formatUsername($row->username);
        $thread->subject = $row->subject;
        $thread->message = $row->message;
        $thread->messageFormatted = Text::formatMessage($row->message);
        $thread->threadReplies = $this->getReplies($row->id);
        $thread->postReplies = !empty($row->post_replies) ? explode(',', $row->post_replies) : false;

        $thread->statistics = new ThreadStatistics();
        $thread->statistics->readCount = $row->read_count;
        $thread->statistics->replyCount = $row->reply_count;
        $thread->statistics->distinctReplyCount = $row->distinct_reply_count;

        if (!empty($row->file_id)) {
            $thread->file = $this->createFileClass($row);
        }

        return $thread;
    }

    public function getBoardThreads(int $boardId, int $page, int $count, int $replyCount) : array
    {
        $limitStart = ($page - 1) * $count;

        $q = $this->db->prepare($this->getPostsQuery("WHERE a.board_id = :board_id AND a.thread_id IS NULL
            ORDER BY sticky DESC, bump_time DESC LIMIT " . (int)$limitStart . ', ' . (int)$count));
        $q->bindValue('board_id', $boardId);
        $q->execute();

        if ($q->rowCount() == 0) {
            return [];
        }

        $threads = [];

        while ($row = $q->fetch()) {
            if (empty($row->subject) && $row->subject != '0') {
                $row->subject = $this->createSubject($row->message);
            }

            // Assign values to a class to return
            $thread = new Thread();
            $thread->id = $row->id;
            $thread->locked = (bool)$row->locked;
            $thread->boardId = $row->board_id;
            $thread->userId = $row->user_id;
            $thread->ip = inet_ntop($row->ip);
            $thread->countryCode = $row->country_code;
            $thread->time = date('c', strtotime($row->time));
            $thread->locked = $row->locked;
            $thread->sticky = $row->sticky;
            $thread->username = Text::formatUsername($row->username);
            $thread->subject = $row->subject;
            $thread->message = $row->message;
            $thread->messageFormatted = Text::formatMessage($row->message);
            if ($replyCount != 0) {
                $thread->threadReplies = $this->getReplies($row->id, $replyCount, true);
            } else {
                $thread->threadReplies = false;
            }
            $thread->postReplies = !empty($row->post_replies) ? explode(',', $row->post_replies) : false;

            $thread->statistics = new ThreadStatistics();
            $thread->statistics->readCount = $row->read_count;
            $thread->statistics->replyCount = $row->reply_count;
            $thread->statistics->distinctReplyCount = $row->distinct_reply_count;

            if (!empty($row->file_id)) {
                $thread->file = $this->createFileClass($row);
            }

            $threads[] = $thread;
        }

        return $threads;
    }

    public function getReplies(int $threadId, int $count = null, bool $newest = false, int $fromId = null) : array
    {
        $from = '';
        if ($newest) {
            $order = 'DESC';
            if ($fromId) {
                $from = ' AND a.id > :from';
            }
        } else {
            $order = 'ASC';
            if ($fromId) {
                $from = ' AND a.id < :from';
            }
        }

        if ($count) {
            $limit = ' LIMIT ' . (int)$count;
        } else {
            $limit = '';
        }

        $q = $this->db->prepare($this->getPostsQuery('WHERE a.thread_id = :thread_id' . $from . ' ORDER BY a.id ' . $order . $limit));
        $q->bindValue('thread_id', $threadId);
        if ($from) {
            $q->bindValue('from', $fromId);
        }
        $q->execute();
        
        $replies = [];
        while ($row = $q->fetch()) {
            $tmp = new Reply();
            $tmp->id = $row->id;
            $tmp->threadId = $threadId;
            $tmp->userId = $row->user_id;
            $tmp->ip = inet_ntop($row->ip);
            $tmp->countryCode = $row->country_code;
            $tmp->username = Text::formatUsername($row->username);
            $tmp->time = date('c', strtotime($row->time));
            $tmp->message = $row->message;
            $tmp->messageFormatted = Text::formatMessage($row->message);
            $tmp->postReplies = !empty($row->post_replies) ? explode(',', $row->post_replies) : false;

            if (!empty($row->file_id)) {
                $tmp->file = $this->createFileClass($row);
            }
            $replies[] = $tmp;
        }

        if ($newest) {
            $replies = array_reverse($replies);
        }

        return $replies;
    }

    protected function createSubject(string $message) : string
    {
        $subject = Text::stripFormatting($message);
        $subject = Text::truncate($subject, 40);
        $subject = trim($subject);

        return $subject;
    }

    public function createThread(
        int $userId,
        int $boardId,
        string $subject,
        string $message,
        $username,
        string $ip,
        string $countryCode
    ) : int
    {
        $q = $this->db->prepare("INSERT INTO posts
            (user_id, board_id, ip, country_code, username, subject, message, bump_time, locked, sticky)
            VALUES (:user_id, :board_id, :ip, :country_code, :username, :subject, :message, NOW(), 0, 0)
        ");

        $q->bindValue('user_id', $userId);
        $q->bindValue('board_id', $boardId);
        $q->bindValue('ip', inet_pton($ip));
        $q->bindValue('country_code', $countryCode);
        $q->bindValue('username', $username);
        $q->bindValue('subject', empty($subject) ? null : $subject);
        $q->bindValue('message', $message);

        $q->execute();

        return $this->db->lastInsertId();
    }

    public function addReply(
        int $userId,
        int $threadId,
        string $message,
        $username,
        string $ip,
        string $countryCode
    ) : int
    {
        $q = $this->db->prepare("INSERT INTO posts
            (user_id, thread_id, ip, country_code, username, message)
            VALUES (:user_id, :thread_id, :ip, :country_code, :username, :message)
        ");

        $q->bindValue('user_id', $userId);
        $q->bindValue('thread_id', $threadId);
        $q->bindValue('ip', inet_pton($ip));
        $q->bindValue('country_code', $countryCode);
        $q->bindValue('username', $username);
        $q->bindValue('message', $message);

        $q->execute();

        return $this->db->lastInsertId();
    }

    public function bumpThread(int $threadId) : bool
    {
        $q = $this->db->prepare("UPDATE posts SET bump_time = NOW() WHERE id = :thread_id LIMIT 1");
        $q->bindValue('thread_id', $threadId);
        $q->execute();

        return true;
    }

    public function addFile(int $postId, int $fileId, string $fileName) : bool
    {
        $q = $this->db->prepare("INSERT INTO posts_files (post_id, file_id, file_name) VALUES (:post_id, :file_id, :file_name)");
        $q->bindValue('post_id', $postId);
        $q->bindValue('file_id', $fileId);
        $q->bindValue('file_name', $fileName);
        $q->execute();

        return true;
    }

    public function getMeta(int $postId)
    {
        $q = $this->db->prepare("SELECT id, board_id, thread_id, user_id, ip, country_code, time, username
            FROM posts WHERE id = :post_id LIMIT 1");
        $q->bindValue('post_id', $postId);
        $q->execute();

        if ($q->rowCount() == 0) {
            return false;
        }

        $row = $q->fetch();
        $post = new Post();
        $post->id = $row->id;
        $post->boardId = $row->board_id;
        $post->threadId = $row->thread_id;
        $post->userId = $row->user_id;
        $post->ip = inet_ntop($row->ip);
        $post->countryCode = $row->country_code;
        $post->time = $row->time;

        return $post;
    }

    public function get(int $postId)
    {
        $q = $this->db->prepare($this->getPostsQuery('WHERE a.id = :post_id LIMIT 1'));
        $q->bindValue('post_id', $postId);
        $q->execute();

        if ($q->rowCount() == 0) {
            return false;
        }

        $row = $q->fetch();

        $post = new Reply();
        $post->id = $row->id;
        $post->boardId = $row->board_id;
        $post->threadId = $row->thread_id;
        $post->userId = $row->user_id;
        $post->ip = inet_ntop($row->ip);
        $post->countryCode = $row->country_code;
        $post->username = Text::formatUsername($row->username);
        $post->time = date('c', strtotime($row->time));
        $post->message = $row->message;
        $post->messageFormatted = Text::formatMessage($row->message);
        $post->postReplies = !empty($row->post_replies) ? explode(',', $row->post_replies) : false;

        if (!empty($row->file_id)) {
            $post->file = $this->createFileClass($row);
        }

        return $post;
    }

    public function delete(int $postId) : bool
    {
        $q = $this->db->prepare("INSERT INTO posts_deleted (id, user_id, board_id, thread_id, ip, time, subject, message, time_deleted)
            SELECT id, user_id, board_id, thread_id, ip, time, subject, message, NOW() FROM posts
            WHERE id = :post_id OR thread_id = :post_id_2");
        $q->bindValue('post_id', $postId);
        $q->bindValue('post_id_2', $postId);
        $q->execute();

        $q = $this->db->prepare("DELETE FROM posts WHERE id = :post_id LIMIT 1");
        $q->bindValue('post_id', $postId);
        $q->execute();

        return $q->rowCount() != 0;
    }

    public function deleteByUser(int $userId, int $intervalHours = 1000000) : bool
    {
        $q = $this->db->prepare("INSERT INTO posts_deleted (id, user_id, board_id, thread_id, ip, time, subject, message, time_deleted)
            SELECT id, user_id, board_id, thread_id, ip, time, subject, message, NOW() FROM posts
            WHERE user_id = :user_id AND time >= DATE_SUB(NOW(), INTERVAL :interval_hours HOUR)");
        $q->bindValue('user_id', $userId);
        $q->bindValue('interval_hours', $intervalHours);
        $q->execute();

        $q = $this->db->prepare("DELETE FROM posts
            WHERE user_id = :user_id AND time >= DATE_SUB(NOW(), INTERVAL :interval_hours HOUR)");
        $q->bindValue('user_id', $userId);
        $q->bindValue('interval_hours', $intervalHours);
        $q->execute();

        return true;
    }

    public function updateThreadStats(int $threadId, string $key, int $val = 1) : bool
    {
        switch ($key) {
            case "replyCount":
                $column = 'reply_count';
                break;
            case "readCount":
                $column = 'read_count';
                break;
            default:
                return false;
        }

        $q = $this->db->prepare("INSERT INTO thread_statistics (thread_id, " . $column . ") VALUES (:thread_id, :val)
            ON DUPLICATE KEY UPDATE " . $column . " =  " . $column . "+:val_2");

        $q->bindValue('thread_id', $threadId);
        $q->bindValue('val', $val);
        $q->bindValue('val_2', $val);
        $q->execute();

        return true;
    }

    public function setPostReplies(int $postId, array $replies, bool $clearOld = false) : bool
    {
        if (count($replies) == 0) {
            return true;
        }

        $query = str_repeat('(?,?),', count($replies));
        $query = substr($query, 0, -1);

        $queryVars = [];
        foreach ($replies as $repliedId) {
            $queryVars[] = $postId;
            $queryVars[] = $repliedId;
        }

        if ($clearOld) {
            $q = $this->db->prepare("DELETE FROM posts_replies WHERE post_id = :post_id");
            $q->bindValue('post_id', $postId);
            $q->execute($queryVars);
        }

        $q = $this->db->prepare("INSERT IGNORE INTO posts_replies (post_id, post_id_replied) VALUES " . $query);
        $q->execute($queryVars);

        return true;
    }

    protected function getPostsQuery(string $append = '') : string
    {
        return "SELECT
            a.id, a.board_id, a.thread_id, user_id, ip, country_code, time, locked, sticky, username, subject, message,
            b.file_name AS file_display_name, c.id AS file_id, c.folder AS file_folder, c.name AS file_name,
            c.extension AS file_extension, c.size AS file_size, c.width AS file_width, c.height AS file_height,
            d.read_count, d.reply_count, d.distinct_reply_count,
            (SELECT GROUP_CONCAT(post_id) FROM posts_replies WHERE post_id_replied = a.id) AS post_replies
            FROM posts a
            LEFT JOIN posts_files b ON a.id = b.post_id
            LEFT JOIN files c ON b.file_id = c.id
            LEFT JOIN thread_statistics d ON a.id = d.thread_id " . $append;
    }

    protected function createFileClass($data) : File
    {
        $file = new File();
        $file->id = $data->file_id;
        $file->folder = $data->file_folder;
        $file->name = $data->file_name;
        $file->extension = $data->file_extension;
        $file->size = $data->file_size;
        $file->width = $data->file_width;
        $file->height = $data->file_height;
        $file->displayName = $data->file_display_name;

        return $file;
    }
}
