<?php

namespace pxls;

class User {

    protected $db;

    private static $sql_select_userinfo = "SELECT *, ban_expiry = to_timestamp(0) AS \"is_ban_permanent\", (SELECT is_shadow_banned OR ban_expiry = to_timestamp(0) OR (now() < ban_expiry)) AS \"banned\", (perma_chat_banned OR now() < chat_ban_expiry) AS \"chat_banned\" FROM users";

    public function __construct($db) {
        $this->db = $db;
    }

    public function checkToken($token, $bypassToken) {
        if($bypassToken !== false && $token == $bypassToken) return true;
        $queryToken = $this->db->prepare("SELECT * FROM sessions WHERE token = :token LIMIT 1");
        $queryToken->bindParam(":token",$token,\PDO::PARAM_STR);
        $queryToken->execute();
        if($queryToken->rowCount() > 0) {
            $token = $queryToken->fetch(\PDO::FETCH_OBJ);
            $user = $this->checkRole($token->who);
            if($user) {
                return $user;
            } else {
                throw new UnauthorizedException('User scope not permitted. Your role is not in the permitted scope. Please talk to your supervisor about this.');
            }
        } else {
            throw new UnauthorizedException('Token not found in database. Seems like your session is invalid.');
        }
        $queryToken->closeCursor();
    }

    protected function checkRole($uid) {
        // TODO (Flying)
        $allowRoles = ["staff", "trialmod", "moderator", "administrator"];

        $getRole = $this->db->prepare("SELECT role FROM roles WHERE id = :uid");
        $getRole->bindParam(":uid",$uid,\PDO::PARAM_INT);
        $getRole->execute();
        while($row = $getRole->fetchAll(\PDO::FETCH_COLUMN, 0)) {
            if(!empty(array_intersect($row, $allowRoles))) {
                return $this->getUserById($uid);
            }
        }
        return false;
    }

    private function populateUserData($usr) {
        $usr["signup_ip"] = $usr["signup_ip"];
        $usr["last_ip"] = $usr["last_ip"];
        $usr["logins"] = $this->getUserLoginsById($usr["id"]);
        $getRoles = $this->db->prepare("SELECT role FROM roles WHERE id = :uid");
        $getRoles->bindParam(":uid",$usr["id"],\PDO::PARAM_INT);
        $getRoles->execute();
        $usr["roles"] = $getRoles->fetchAll(\PDO::FETCH_COLUMN, 0);
        return $usr;
    }

    private $userBufferId = [];
    public function getUserById($uid) {
        if (isset($userBufferId[$uid])) {
            return $userBufferId[$uid];
        }
        $getUser = $this->db->prepare("{$this::$sql_select_userinfo} WHERE id = :uid LIMIT 1");
        $getUser->bindParam(":uid",$uid,\PDO::PARAM_INT);
        $getUser->execute();
        if($getUser->rowCount() == 1) {
            $usr = $getUser->fetch(\PDO::FETCH_ASSOC);
            $usr = $this->populateUserData($usr);
            $userBufferId[$uid] = $usr;
            return $usr;
        } else {
            $userBufferId[$uid] = false;
            return false;
        }
    }

    private $userBufferName = [];
    public function getUserByName($uname) {
        if (isset($userBufferName[$uname])) {
            return $userBufferName[$uname];
        }
        $getUser = $this->db->prepare("{$this::$sql_select_userinfo} WHERE username = :uname");
        $getUser->bindParam(":uname",$uname,\PDO::PARAM_STR);
        $getUser->execute();
        if($getUser->rowCount() == 1) {
            $usr = $getUser->fetch(\PDO::FETCH_ASSOC);
            $usr = $this->populateUserData($usr);
            $userBufferName[$uname] = $usr;
            return $usr;
        } else {
            $userBufferName[$uname] = false;
            return false;
        }
    }

    public function getUserLoginsById($uid) {
        $getLogins = $this->db->prepare("SELECT service, service_uid FROM user_logins WHERE uid = :uid");
        $getLogins->bindParam(":uid", $uid, \PDO::PARAM_INT);
        $getLogins->execute();
        return $getLogins->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getUserNotesById($uid) {
        $uid = intval($uid); $notes = []; $reply = 0;
        $getNotes = $this->db->prepare("SELECT id, user_id, target_id, message, timestamp FROM admin_notes WHERE target_id = :uid AND reply_to IS NULL");
        $getNotes->bindParam(":uid", $uid, \PDO::PARAM_INT);
        $getNotes->execute();
        if($getNotes->rowCount() > 0) {
            while($row = $getNotes->fetch(\PDO::FETCH_ASSOC)) {
                $youBetterWorkNow = $this->getUserById($row["user_id"]);
                $row["user_name"] = $youBetterWorkNow["username"];
                $row["replys"] = $this->getNoteReplysById($row["id"]);
                $notes[] = $row;
            }
            return $notes;
        }
        return false;
    }

    public function getBanlogFromDB($uid) {
        $uid = intval($uid);
        $toRet = [];
        $user = $this->getUserById($uid);
        if ($user) {
            $getBansQuery = $this->db->prepare('SELECT b.id, b.when as "when_timestamp", to_timestamp(b.when) as "when", b.banner as "banner_id", COALESCE(u.username, \'console\') as "banner", b.banned as "banned_id", u1.username as "banned", b.ban_expiry as "ban_expiry_timestamp", (CASE WHEN b.ban_expiry != NULL THEN b.ban_expiry ELSE 0 END) as "ban_expiry_date", (CASE WHEN b.ban_expiry - b.when > 0 THEN b.ban_expiry - b.when ELSE -1 END) as "length", b.action, b.ban_reason FROM banlogs b LEFT OUTER JOIN users u ON u.id = b.banner INNER JOIN users u1 ON u1.id = b.banned WHERE b.banned = :id ORDER BY b.when ASC');
            $getBansQuery->bindParam(':id', $uid);
            $getBansQuery->execute();
            if ($getBansQuery->rowCount() > 0) {
                $toRet = $getBansQuery->fetchAll(\PDO::FETCH_ASSOC);
            }
        }
        return $toRet;
    }

    public function getBanlogFromAdminLog($uid) {
        $uid = intval($uid);
        $_user = $this->getUserById($uid);
        $toRet = [];
        if ($_user) {
            $uname = $_user["username"]; //not sure if necessary, but forcing a UID here since we can't bindParam to a string for 'LIKE'. forcing a UID means that we do a user lookup before attempting to pull from database. flimsy and probably unnecessary attempt at sanitzation. -Socc
            $uname = str_replace("_", "\\_", $uname);
            $uname = str_replace("%", "\\%", $uname);

            $getBansQuery = $this->db->prepare('SELECT a.*,COALESCE(u.username, \'console\') AS "who",LEFT(message, 2) = \'un\' AS "is_unban" FROM admin_log a LEFT OUTER JOIN users u ON u.id = a.userid WHERE a.message LIKE \'%ban '.$uname.'\' OR a.message LIKE \'%ban '.$uname.' %\';');
            $getBansQuery->execute();
            if($getBansQuery->rowCount() > 0) {
                $toRet = $getBansQuery->fetchAll(\PDO::FETCH_ASSOC);
            }
        }
        return $toRet;
    }

    public function getChatbanlogFromDB($uid) {
        $uid = intval($uid);
        $toRet = [];
        $user = $this->getUserById($uid);
        if ($user) {
            $getBansQuery = $this->db->prepare('SELECT b.id, b.when as "when_timestamp", to_timestamp(b.when) as "when", b.initiator as "banner_id", COALESCE(u.username, \'console\') as "banner", b.target as "banned_id", u1.username as "banned", b.expiry as "ban_expiry_timestamp", (CASE WHEN b.expiry != NULL THEN b.expiry ELSE 0 END) as "ban_expiry_date", (CASE WHEN b.expiry - b.when > 0 THEN b.expiry - b.when ELSE -1 END) as "length", b.type, b.purged, b.reason as ban_reason FROM chatbans b LEFT OUTER JOIN users u ON u.id = b.initiator INNER JOIN users u1 ON u1.id = b.target WHERE b.target = :id ORDER BY b.when ASC');
            $getBansQuery->bindParam(':id', $uid);
            $getBansQuery->execute();
            if ($getBansQuery->rowCount() > 0) {
                $toRet = $getBansQuery->fetchAll(\PDO::FETCH_ASSOC);
            }
        }
        return $toRet;
    }

    public function getNoteReplysById($nid) {
        $nid = intval($nid); $replys = [];
        $getReplys = $this->db->prepare("SELECT id,user_id,target_id,reply_to,message,timestamp FROM admin_notes WHERE reply_to = :nid");
        $getReplys->bindParam(":nid",$nid,\PDO::PARAM_INT);
        $getReplys->execute();
        while($reply = $getReplys->fetch(\PDO::FETCH_ASSOC)) {
            $creator = $this->getUserById($reply["user_id"]);
            $reply["user_name"] = $creator["username"];
            $replys[] = $reply;
        }
        return $replys;
    }

    public function addNoteToUser($target, $message, $reply=null) {
        $target = intval($target); $time = time();
        $addNote = $this->db->prepare("INSERT INTO admin_notes(user_id,target_id,reply_to,message,timestamp) VALUES (:userid,:targetid, :replyto, :message, :timestamp)");

        $addNote->bindParam(":userid", $_SESSION['user_id'], \PDO::PARAM_INT);
        $addNote->bindParam(":targetid", $target, \PDO::PARAM_INT);
        if(!is_null($reply)) {
            $reply = intval($reply);
            $addNote->bindValue(":replyto", $reply, \PDO::PARAM_INT);
        } else {
            $addNote->bindValue(":replyto", $reply, \PDO::PARAM_NULL);
        }
        $addNote->bindParam(":message",$message, \PDO::PARAM_STR);
        $addNote->bindParam(":timestamp",$time, \PDO::PARAM_INT);
        if($addNote->execute()) {
            return true;
        } else {
            return false;
        }
    }

    public function deleteNote($noteid) {
        $noteid = intval($noteid);
        $delNote = $this->db->prepare("DELETE FROM admin_notes WHERE id = :nid");
        $delNote->bindParam(":nid", $noteid, \PDO::PARAM_INT);
        if($delNote->execute()) {
            return true;
        } else {
            return false;
        }
    }

    public function getIPLogForUser($uid) {
        $uid = intval($uid);
        $_user = $this->getUserById($uid);
        $toRet = [];

        if ($_user) { // $usr["last_ip"] = inet_ntop($usr["last_ip"]);
            $query = $this->db->prepare('SELECT * FROM ip_log WHERE "user"=:user ORDER BY last_used DESC;');
            $query->bindParam(':user', $uid);
            $query->execute();
            if ($query->rowCount() > 0) {
                while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
                    $toRet[] = $row;
                }
            }
        }

        return $toRet;
    }

}
