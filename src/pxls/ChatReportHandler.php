<?php namespace pxls;
error_reporting(E_ERROR);

class ChatReportHandler {
    /**
     * @var \PDO
     */
    private $db;
    private $discord;
    private $settings;

    public function __construct($db, $discord) {
        $this->db = $db;
        $this->discord = $discord;
        global $app;
        $this->settings = $app->getContainer()->get("settings");
    }

    public function getOpenReportsCount() {
        $qReports = $this->db->query("SELECT count(id) AS \"total\" FROM chat_reports WHERE NOT closed AND claimed_by = 0 AND target IS NOT NULL");

        if ($qReports->execute()) {
            return intval($qReports->fetch()->total);
        }
        return false;
    }

    public function getReports($open=true) {
        $query = $this->db->query("SELECT r.id,r.time,r.cmid,r.target,r.initiator,r.claimed_by,m.purged_by,u.username as \"target_name\",u1.username as \"initiator_name\",u2.username as \"claimed_name\",u3.username as \"purged_name\" FROM chat_reports r INNER JOIN chat_messages m ON m.id=r.cmid LEFT OUTER JOIN users u ON u.id=r.target LEFT OUTER JOIN users u1 ON u1.id=r.initiator LEFT OUTER JOIN users u2 ON u2.id=r.claimed_by LEFT OUTER JOIN users u3 ON u3.id=m.purged_by WHERE ".($open !== false ? 'r.closed = false;' : 'true;'));

        try {
            return $query->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Error $e) {
            return [];
        }
    }

    public function getRolesById($uid) {
        $query = $this->db->prepare("SELECT role FROM roles WHERE id = :uid");
        $query->bindParam(":uid", $uid);
        $query->execute();
        return $query->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    public function getReportDetails($rid) {
        $toReturn = [
            "self" => [],
            "report" => [],
            "reporter" => [],
            "reported" => [],
            "context" => []
        ];

        $selfDataQuery = $this->db->prepare("select id,username,role from users where id = :id");
        $selfDataQuery->bindParam(":id", $_SESSION['user_id'], \PDO::PARAM_INT);
        if ($selfDataQuery->execute()) {
            $toReturn['self'] = $selfDataQuery->fetch(\PDO::FETCH_ASSOC);
            $toReturn['self']['roles'] = $this->getRolesById($_SESSION['user_id']);
            $selfDataQuery->closeCursor();
        }

        $queryReportDetails = $this->db->prepare("SELECT r.*,u.username AS \"claimed_by_name\" FROM chat_reports r LEFT OUTER JOIN users u ON u.id=r.claimed_by WHERE r.id=:id");
        $queryReportDetails->bindParam(":id", $rid, \PDO::PARAM_INT);
        if ($queryReportDetails->execute()) {
            $toReturn['report']['details'] = $queryReportDetails->fetch(\PDO::FETCH_ASSOC);
            $queryReportMessage = $this->db->prepare("SELECT * FROM chat_messages WHERE id = :id");
            $queryReportMessage->bindParam(':id', $toReturn['report']['details']['cmid']);
            if ($queryReportMessage->execute()) {
                $toReturn['report']['message'] = $queryReportMessage->fetch(\PDO::FETCH_ASSOC);
            }
            $toReturn['report']['claimed_by_you'] = intval($toReturn['report']['details']['claimed_by']) === intval($toReturn['self']['id']);
        }

        $queryReporter = $this->db->prepare("SELECT id,username,(is_shadow_banned OR CAST(EXTRACT(epoch FROM ban_expiry) AS INTEGER) = 0 OR (now() < ban_expiry)) AS \"banned\",(perma_chat_banned OR (now() < chat_ban_expiry)) AS \"chatbanned\" FROM users WHERE id=:id;");
        $queryReporter->bindParam(":id", $toReturn["report"]["details"]["initiator"], \PDO::PARAM_INT);
        if ($queryReporter->execute()) {
            $toReturn["reporter"] = $queryReporter->fetch(\PDO::FETCH_ASSOC);
            $toReturn['reporter']['roles'] = $this->getRolesById($toReturn["report"]["details"]["initiator"]);
        }

        $queryReported = $this->db->prepare("SELECT id,username,(is_shadow_banned OR CAST(EXTRACT(epoch FROM ban_expiry) AS INTEGER) = 0 OR (now() < ban_expiry)) AS \"banned\",(perma_chat_banned OR (now() < chat_ban_expiry)) AS \"chatbanned\" FROM users WHERE id=:id;");
        $queryReported->bindParam(":id", $toReturn["report"]["details"]["target"], \PDO::PARAM_INT);
        if ($queryReported->execute()) {
            $toReturn["reported"] = $queryReported->fetch(\PDO::FETCH_ASSOC);
            $toReturn['reported']['roles'] = $this->getRolesById($toReturn["report"]["details"]["target"]);
        }

       $toReturn["context"] = $this->getContextAroundID($toReturn["report"]["details"]["cmid"]);

        return $toReturn;
    }

    public function getContextAroundID($id, $amount = 12) {
        $id = intval($id);
        $amount = intval($amount);
        $toReturn = [];

        $contextQuery = $this->db->prepare("(select m.*,u.username as \"author_name\" from chat_messages m inner join users u on u.id=m.author where m.id > :id order by id asc limit $amount) union all (select m.*,u.username as \"author_name\" from chat_messages m inner join users u on u.id=m.author where m.id = :id) union all (select m.*,u.username as \"author_name\" from chat_messages m inner join users u on u.id=m.author where m.id < :id order by m.id desc limit $amount) order by id desc;");
        $contextQuery->bindParam(":id", $id, \PDO::PARAM_INT);
        if ($contextQuery->execute()) {
            $toReturn = $contextQuery->fetchAll(\PDO::FETCH_ASSOC);
        }

        return $toReturn;
    }

    public function setClaimed($rid, $isClaimed) {
        $rid = intval($rid);
        $isClaimed = ($isClaimed == true ? 1 : 0);
        $uid = $isClaimed == 1 ? $_SESSION['user_id'] : 0;

        $queryUpdate = $this->db->prepare("UPDATE chat_reports SET claimed_by = :uid WHERE id = :rid");
        $queryUpdate->bindParam(':uid', $uid, \PDO::PARAM_INT);
        $queryUpdate->bindParam(':rid', $rid, \PDO::PARAM_INT);

        return $queryUpdate->execute();
    }

    public function setResolved($rid, $isResolved) {
        $rid = intval($rid);
        $isResolved = ($isResolved == true ? 1 : 0);

        $queryWhoami = $this->db->prepare('SELECT id,role FROM users WHERE id = :id');
        $queryWhoami->bindParam(':id', $_SESSION['user_id'], \PDO::PARAM_INT);
        if (!$queryWhoami->execute()) return false;
        $whoami = $queryWhoami->fetch(\PDO::FETCH_ASSOC);

        $reportQuery = $this->db->prepare('SELECT claimed_by FROM chat_reports WHERE id = :rid');
        $reportQuery->bindParam(':rid', $rid, \PDO::PARAM_INT);
        if (!$reportQuery->execute()) return false;
        $reportClaimedBy = intval($reportQuery->fetch(\PDO::FETCH_ASSOC)['claimed_by']);

        if (($whoami->role != "ADMIN" && $whoami->role != "DEVELOPER") && $reportClaimedBy != intval($_SESSION['user_id'])) {
            return false;
        }

        $resolveQuery = $this->db->prepare('UPDATE chat_reports SET closed=:isResolved WHERE id = :rid');
        $resolveQuery->bindParam(':isResolved', $isResolved, \PDO::PARAM_INT);
        $resolveQuery->bindParam(':rid', $rid, \PDO::PARAM_INT);

        return $resolveQuery->execute();
    }

}
