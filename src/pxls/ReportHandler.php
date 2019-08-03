<?php

namespace pxls;

error_reporting(E_ERROR);
class ReportHandler {
    private $db;
    private $discord;

    public function __construct($db, $discord) {
        $this->db = $db;
        $this->discord = $discord;
        global $app;
        $this->settings = $app->getContainer()->get("settings");
    }

    public function announce($openChatReports = 0) {
        $reports = $this->getReports(1);
        $claimedCount = 0;
        foreach($reports as $report) {
            if(!$report['claimed_by'] == 0) $claimedCount++;
        }
        $reportCount = count($reports) - $claimedCount;
        if($reportCount > 0) {
            $normalParts = [
                'oWo h-hewwo moderwators... pwease answewr',
                ' '.($reportCount == 0 ? '' : ($reportCount == 1 ? 'this ' : 'these ').$reportCount. " canvas weport".($reportCount == 1 ? '' : 's')),
                ($openChatReports == 0 ? '' : (($reportCount > 0 ? ' and ' : ($openChatReports == 1 ? 'this ' : 'these ')).$openChatReports.' chat weport'.($openChatReports == 1 ? '' : 's'))),
                ' pwease >.< <'.$this->settings['webroots']['panel'].'/>'
            ];
            $panicParts = [
                '┻━┻彡 ヽ(ಠДಠ)ノ彡┻━┻﻿ wake up, there are',
                ' '.($reportCount == 0 ? '' : $reportCount.' canvas report'.($reportCount == 1 ? '' : 's')),
                ''.($openChatReports == 0 ? ' ' : ($reportCount == 0 ? '' : ' and ').$openChatReports.' chat reports '),
                'to handle. <'.$this->settings['webroots']['panel'].'/>'
            ];

            $this->discord->setName("Pxls Admin");
            $this->discord->setMessage(implode(($reportCount >= 10 || $openChatReports >= 10) ? $panicParts : $normalParts));
            $this->discord->execute();
            return true;
        } else {
            return false;
        }
    }

    public function discordinfo($uid) {
        $execUser = $this->getUserdataById($_SESSION['user_id'])->username;
        $this->discord->setName($this->settings["discord"]["whois"]["name"]);
        $this->discord->setUrl($this->settings["discord"]["whois"]["url"]);
        $this->discord->setMessage("!whois $uid $execUser");
        $this->discord->execute();
        return true;
    }

    public function getReports($onlyOpen=1) {
        global $app;
        $reports = [];
        if($onlyOpen==1) {
            $qReports = $this->db->query("SELECT r.id,r.who,u.username as reported_name,r.x,r.y,r.claimed_by,r.time,r.reported FROM reports r LEFT OUTER JOIN users u ON u.id=r.reported WHERE closed = 0 AND reported IS NOT NULL");
        } else {
            $qReports = $this->db->query("SELECT r.id,r.who,u.username as reported_name,r.x,r.y,r.claimed_by,r.time,r.reported,r.closed FROM reports r LEFT OUTER JOIN users u ON u.id=r.reported WHERE reported IS NOT NULL");
        }

        while($report = $qReports->fetch(\PDO::FETCH_ASSOC)) {
            $report['who_name'] = $report['who'] ? $this->getUserdataById($report['who'])->username : 'Server';
            $report['claimed_name'] = ($report['claimed_by']==0)?'':$this->getUserdataById($report['claimed_by'])->username;
            $report['position_url'] = $report['who'] ? '<a href="'.$this->formatCoordsLink($report['x'], $report['y']).'" target="_blank">X:'.$report['x'].'; Y:'.$report['y'].'</a>' : 'N/A';
            $report['who_url'] = $report['who'] ? '<a href="/userinfo/'.$report['who_name'].'" target="_blank">'.$report['who_name'].'</a>' : 'Server';
            $report['reported_url'] = $report['reported'] ? '<a href="/userinfo/'.$report['reported_name'].'" target="_blank">'.$report['reported_name'].'</a>' : 'Server';
            $report['human_time'] = date("d.m.Y - H:i:s",$report['time']);
            if($report['claimed_by'] == 0) {
                $report['action'] = '<button type="button" class="btn btn-warning btn-xs" data-toggle="modal" data-reportid="'.$report['id'].'" data-target="#report_info">Details</button>';
            } else if($report['claimed_by'] == $_SESSION['user_id']) {
                $report['action'] = '<button type="button" class="btn btn-success btn-xs" data-toggle="modal" data-reportid="' . $report['id'] . '" data-target="#report_info">Details</button>';
            } else {
                $report['action'] = '<button type="button" class="btn btn-default btn-xs" data-toggle="modal" data-target="#report_info" disabled>claimed by '.$report['claimed_name'].'</button>';
            }
            $reports[] = $report;
        }

        return $reports;
    }

    public function getUserdataById($s) {
        $getUser = $this->db->prepare("SELECT * FROM users WHERE id = :uid LIMIT 1");
        $getUser->bindParam(":uid",$s,\PDO::PARAM_INT);
        $getUser->execute();
        return $getUser->fetch(\PDO::FETCH_OBJ);
    }

    public function getUserdataByPixel($s) {
        $getPixel = $this->db->prepare("SELECT who FROM pixels WHERE id = :uid LIMIT 1");
        $getPixel->bindParam(":uid",$s,\PDO::PARAM_INT);
        $getPixel->execute();
        $pixel = $getPixel->fetch(\PDO::FETCH_OBJ);
        return $this->getUserdataById($pixel->who);
    }

    public function claim($rId,$claim) {
        $rid = intval($rId);
        $uid = ($claim==1)?$_SESSION['user_id']:0;
        $updateReport = $this->db->prepare("UPDATE reports SET claimed_by = :uid WHERE id = :rid");
        $updateReport->bindParam(":uid",$uid,\PDO::PARAM_INT);
        $updateReport->bindParam(":rid",$rid,\PDO::PARAM_INT);
        $updateReport->execute();
    }
    private function _resolve($rId) {
        $rid = intval($rId);
        $updateReport = $this->db->prepare("UPDATE reports SET closed = 1 WHERE id = :rid");
        $updateReport->bindParam(":rid",$rid,\PDO::PARAM_INT);
        $updateReport->execute();
        return true;
    }
    public function resolve($rId) {
        $execUser = $this->getUserdataById($_SESSION['user_id']);
        if ($execUser->role == "ADMIN") return $this->_resolve($rId);
        if ($this->whoClaimedReport($rId) != $_SESSION['user_id']) return false;
        return $this->_resolve($rId);
    }

    private function whoClaimedReport($rId) {
        $reportQuery = $this->db->prepare("SELECT claimed_by FROm reports WHERE id = :id LIMIT 1");
        $reportQuery->bindParam(":id", $rId, \PDO::PARAM_INT);
        $reportQuery->execute();

        $reportResult = $reportQuery->fetch(\PDO::FETCH_OBJ);
        return $reportResult ? $reportResult->claimed_by : false;
    }

    public function getReportDetails($reportid) {
        $report = [];
        $self = $this->getUserdataById($_SESSION['user_id']);

        $qR = $this->db->prepare("SELECT id,who,x,y,claimed_by,time,pixel_id,message,reported FROM reports WHERE id = :id LIMIT 1");
        $qR->bindParam(":id",$reportid,\PDO::PARAM_INT);
        $qR->execute();
        while($gData = $qR->fetch(\PDO::FETCH_OBJ)) {
            $report['self'] = [
                'id' => $self->id,
                'username' => $self->username,
                'role' => $self->role
            ];
            $report['general']['id'] = $gData->id;
            $report['general']['pixel'] = $gData->pixel_id;
            $report['general']['claimed'] = ($gData->claimed_by == 0)?'no one':$this->getUserdataById($gData->claimed_by)->username;
            $report['general']['claimed_by_you']=$gData->claimed_by == $self->id;
            $report['general']['position'] = '<a href="'.$this->formatCoordsLink($gData->x, $gData->y).'" target="_blank">X: ' . $gData->x . ' &mdash; Y: ' . $gData->y . '</a>';
            $report['general']['message'] = htmlentities($gData->message);
            $report['general']['time'] = date("d.m.Y - H:i:s", $gData->time);

            $reporterData = $this->getUserdataById($gData->who);
            $report['reporter']['username']         = $reporterData->username;
            $report['reporter']['login']            = $reporterData->login;
            $report['reporter']['signup']           = $reporterData->signup_time;
            $report['reporter']['role']             = $reporterData->role;
            $report['reporter']['pixelcount']       = $reporterData->pixel_count;
            $report['reporter']['ip']               = ["last"=>inet_ntop($reporterData->last_ip),"signup"=>inet_ntop($reporterData->signup_ip)];
            $report['reporter']['ban']              = ["expiry"=>$reporterData->ban_expiry,"reason"=>$reporterData->ban_reason];

            $reportedData = $this->getUserdataById($gData->reported);
            $report['reported']['username']         = $reportedData->username;
            $report['reported']['login']            = $reportedData->login;
            $report['reported']['signup']           = $reportedData->signup_time;
            $report['reported']['role']             = $reportedData->role;
            $report['reported']['pixelcount']       = $reportedData->pixel_count;
            $report['reported']['ip']               = ["last"=>inet_ntop($reportedData->last_ip),"signup"=>inet_ntop($reportedData->signup_ip)];
            $report['reported']['ban']              = ["expiry"=>$reportedData->ban_expiry,"reason"=>$reportedData->ban_reason];
        }
        return $report;
    }

    private function formatCoordsLink($x, $y, $scale=32) {
        return $this->settings["webroots"]["game"]."/#x=".$x."&y=".$y."&scale=".$scale;
    }
}
