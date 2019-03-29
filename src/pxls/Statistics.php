<?php

namespace pxls;

class Statistics {

    protected $db;
    private $palette = ["#FFFFFF", "#CDCDCD", "#888888", "#222222", "#000000", "#FFA7D1", "#E50000", "#800000", "#FFDDCA", "#E59500", "#A06A42", "#E5D900", "#94E044", "#02BE01", "#00D3DD", "#0083C7", "#0000EA", "#CF6EE4", "#FF00FF", "#820080"];

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

    public function mostPixels() {
        return $this->db->query("SELECT username, pixel_count FROM users ORDER BY pixel_count DESC LIMIT 1")->fetch(\PDO::FETCH_OBJ);
    }

    public function topUser() {
        $topuser = $this->db->query("SELECT username, pixel_count FROM users ORDER BY pixel_count DESC LIMIT 10");
        $top10 = [];
        while($row = $topuser->fetch(\PDO::FETCH_OBJ)) {
            $top10[] = ["username"=>$row->username,"pixel_count"=>$row->pixel_count];
        }
        return $top10;
    }

    public function topColor() {
        $topColor = $this->db->query("SELECT COUNT(*) as uses, color FROM pixels GROUP BY color ORDER BY uses DESC");
        $topColors = $topColor->fetchAll(\PDO::FETCH_OBJ);
        foreach($topColors as $top) {
            $top->hex = $this->palette[$top->color];
        }
        return $topColors;
    }

}