<?php

namespace pxls;

class Statistics {

    protected $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function getUserCount() {
        $users = $this->db->query("SELECT COUNT(id) AS total FROM users");
        return $users->fetch(\PDO::FETCH_OBJ)->total;
    }

    public function activeSessions() {
        return $this->db->query("SELECT COUNT(id) AS total FROM sessions")->fetch(\PDO::FETCH_OBJ)->total;
    }

    public function pixelsPlaced($time=0) {
        if($time == 0) {
            return $this->db->query("SELECT COUNT(id) AS total FROM pixels WHERE NOT rollback_action AND NOT mod_action AND NOT undo_action AND NOT undone")->fetch(\PDO::FETCH_OBJ)->total;
        } else {
            $time = date("Y-m-d H:i:s",strtotime($time));
            $pixels = $this->db->prepare("SELECT COUNT(id) AS total FROM pixels WHERE NOT rollback_action AND NOT mod_action AND NOT undo_action AND NOT undone AND time > :time");
            $pixels->bindParam(":time",$time,\PDO::PARAM_STR);
            $pixels->execute();
            return $pixels->fetch(\PDO::FETCH_OBJ)->total;
        }
    }

    public function topUser() {
        $topuser = $this->db->query("SELECT username, pixel_count FROM users ORDER BY pixel_count DESC LIMIT 10");
        $top10 = [];
        while($row = $topuser->fetch(\PDO::FETCH_OBJ)) {
            $top10[] = ["username"=>$row->username,"pixel_count"=>$row->pixel_count];
        }
        return $top10;
    }
}