<?php
namespace YBoard\Models;

use YFW\Library\Database;
use YFW\Model;

class User extends Model
{
    const PASSWORD_HASH_COST = 12;
    const PASSWORD_HASH_TYPE = PASSWORD_BCRYPT;

    public $id = null;
    public $session;
    public $accountCreated;
    public $username;
    public $class = 0;
    public $goldLevel = 0;
    public $lastActive;
    public $lastIp;
    public $isRegistered = false;
    public $loggedIn = false;
    public $ban = false;
    public $isMod = false;
    public $isAdmin = false;
    public $requireCaptcha = true;

    public $preferences;
    public $statistics;
    public $threadHide;
    public $threadFollow;
    public $notifications;

    protected $password;

    public function __construct(Database $db, $data = [], $skipDbLoad = false)
    {
        parent::__construct($db);

        foreach ($data as $key => $val) {
            switch ($key) {
                case 'id':
                    $this->id = (int)$val;
                    break;
                case 'account_created':
                    $this->accountCreated = $val;
                    break;
                case 'username':
                    $this->username = $val;
                    break;
                case 'password':
                    $this->password = $val;
                    break;
                case 'class':
                    $this->class = (int)$val;
                    break;
                case 'gold_level':
                    $this->goldLevel = (int)$val;
                    break;
                case 'last_active':
                    $this->lastActive = $val;
                    break;
                case 'last_ip':
                    $this->lastIp = inet_ntop($val);
                    break;
            }
        }

        $this->loadSubclasses($skipDbLoad);

        $this->isRegistered = $this->loggedIn = !empty($data->username); // Doubled just for clarity
        $this->requireCaptcha = $this->statistics->totalPosts < 1;
        $this->ban = $this->getBan();

        if ($this->class == 1) {
            $this->isMod = true;
            $this->isAdmin = true;
        } elseif ($this->class == 2) {
            $this->isMod = true;
        }

        // No functions for nongolds
        if ($this->goldLevel == 0) {
            $this->resetGoldSettings();
        } else {
            // Override settings for golds
            // TODO: add file maxsize
        }
    }

    protected function resetGoldSettings()
    {
        if ($this->goldLevel >= 1) {
            return true;
        }

        // TODO: Add rest of them
        $this->preferences->useName = false;
        $this->preferences->hideAds = false;
        $this->preferences->tinfoilMode = false;
    }

    public function delete() : bool
    {
        // Relations will handle the deletion of rest of the data, so we don't have to care.
        // Thank you relations!
        $q = $this->db->prepare("DELETE FROM users WHERE id = :id LIMIT 1");
        $q->bindValue('id', $this->id, Database::PARAM_INT);
        $q->execute();

        return true;
    }

    public function validatePassword($password) : bool
    {
        if ($this->password === null) {
            return false;
        }

        if (password_verify($password, $this->password)) {
            return true;
        }

        return false;
    }

    public function setPassword($newPassword) : bool
    {
        // Do note that this function does not verify old password!
        $newPassword = password_hash($newPassword, static::PASSWORD_HASH_TYPE, ['cost' => static::PASSWORD_HASH_COST]);

        $q = $this->db->prepare("UPDATE users SET password = :new_password WHERE id = :id LIMIT 1");
        $q->bindValue('new_password', $newPassword);
        $q->bindValue('id', $this->id, Database::PARAM_INT);
        $q->execute();

        return true;
    }

    public function setUsername($newUsername) : bool
    {
        $q = $this->db->prepare("UPDATE users SET username = :new_username WHERE id = :id LIMIT 1");
        $q->bindValue('new_username', $newUsername);
        $q->bindValue('id', $this->id);
        $q->execute();

        return true;
    }

    public function updateLastActive() : bool
    {
        $q = $this->db->prepare("UPDATE users SET last_active = NOW(), last_ip = :last_ip WHERE id = :id LIMIT 1");
        $q->bindValue('id', (int)$this->id);
        $q->bindValue('last_ip', inet_pton($_SERVER['REMOTE_ADDR']));
        $q->execute();

        return true;
    }

    public function getBan()
    {
        if ($this->id === null) {
            return false;
        }

        $bans = new Bans($this->db);
        return $bans->get($_SERVER['REMOTE_ADDR'], $this->id);
    }

    protected function loadSubclasses(bool $skipDbLoad = false) : bool
    {
        $this->preferences = new UserPreferences($this->db, $this->id, $skipDbLoad);
        $this->statistics = new UserStatistics($this->db, $this->id, $skipDbLoad);
        $this->threadHide = new UserThreadHide($this->db, $this->id, $skipDbLoad);
        $this->threadFollow = new UserThreadFollow($this->db, $this->id, $skipDbLoad);
        $this->notifications = new Notifications($this->db, $this->id, $this->preferences->hiddenNotificationTypes,
            $skipDbLoad);

        return true;
    }

    protected function load()
    {

    }
}
