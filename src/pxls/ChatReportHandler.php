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
        $query = $this->db->query("SELECT r.id,r.time,r.chat_message,r.target,r.initiator,r.claimed_by,m.purged_by,u.username as \"target_name\",u1.username as \"initiator_name\",u2.username as \"claimed_name\",u3.username as \"purged_name\" FROM chat_reports r INNER JOIN chat_messages m ON m.nonce=r.chat_message LEFT OUTER JOIN users u ON u.id=r.target LEFT OUTER JOIN users u1 ON u1.id=r.initiator LEFT OUTER JOIN users u2 ON u2.id=r.claimed_by LEFT OUTER JOIN users u3 ON u3.id=m.purged_by WHERE ".($open !== false ? 'r.closed = false;' : 'true;'));

        try {
            return $query->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Error $e) {
            return [];
        }
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
            $selfDataQuery->closeCursor();
        }

        $queryReport = $this->db->prepare("SELECT r.*,m.*,u.username AS \"claimed_by_name\" FROM chat_reports r INNER JOIN chat_messages m ON m.nonce=r.chat_message LEFT OUTER JOIN users u ON u.id=r.claimed_by WHERE r.id = :id;");
        $queryReport->bindParam(":id", $rid, \PDO::PARAM_INT);
        if ($queryReport->execute()) {
            $toReturn["report"] = $queryReport->fetch(\PDO::FETCH_ASSOC);
            $toReturn["report"]["claimed_by_you"] = intval($toReturn["report"]["claimed_by"]) === intval($toReturn["self"]["id"]);
        }

        $queryReporter = $this->db->prepare("SELECT id,username,role,(role='BANNED' OR role='SHADOWBANNED' OR (now() < ban_expiry)) AS \"banned\",(perma_chat_banned OR (now() < chat_ban_expiry)) AS \"chatbanned\" FROM users WHERE id=:id;");
        $queryReporter->bindParam(":id", $toReturn["report"]["initiator"], \PDO::PARAM_INT);
        if ($queryReporter->execute()) {
            $toReturn["reporter"] = $queryReporter->fetch(\PDO::FETCH_ASSOC);
        }

        $queryReported = $this->db->prepare("SELECT id,username,role,(role='BANNED' OR role='SHADOWBANNED' OR (now() < ban_expiry)) AS \"banned\",(perma_chat_banned OR (now() < chat_ban_expiry)) AS \"chatbanned\" FROM users WHERE id=:id;");
        $queryReported->bindParam(":id", $toReturn["report"]["target"], \PDO::PARAM_INT);
        if ($queryReported->execute()) {
            $toReturn["reported"] = $queryReported->fetch(\PDO::FETCH_ASSOC);
        }

       $toReturn["context"] = $this->getContextAroundNonce($toReturn["report"]["chat_message"]);

        return $toReturn;
    }

    public function getContextAroundNonce($nonce, $amount = 10) {
        $amount = intval($amount);
        $toReturn = [];

        $contextQuery = $this->db->prepare("(SELECT m.*,u.username AS \"author_name\" FROM chat_messages m LEFT OUTER JOIN users u ON u.id=m.author WHERE m.sent > (SELECT sent FROM chat_messages WHERE nonce = :nonce) ORDER BY sent ASC LIMIT $amount) UNION ALL (SELECT m.*,u.username AS \"author_name\" FROM chat_messages m LEFT OUTER JOIN users u ON u.id=m.author WHERE m.nonce = :nonce) UNION ALL (SELECT m.*,u.username AS \"author_name\" FROM chat_messages m LEFT OUTER JOIN users u ON u.id=m.author WHERE m.sent < (SELECT sent FROM chat_messages WHERE nonce = :nonce) ORDER BY m.sent DESC LIMIT $amount) ORDER BY sent ASC;");
        $contextQuery->bindParam(":nonce", $nonce, \PDO::PARAM_STR);
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
