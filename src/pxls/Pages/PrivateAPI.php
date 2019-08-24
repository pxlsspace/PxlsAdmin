<?php

namespace pxls\Action;

use pxls\DiscordHook;
use pxls\Utils;
use Slim\Views\Twig;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

final class PrivateAPI
{
    private $view;
    private $logger;
    private $database;
    private $user;

    public function __construct(Twig $view, LoggerInterface $logger, \PDO $database, DiscordHook $discord)
    {
        $this->view = $view;
        $this->logger = $logger;
        $this->database = $database;
        $this->discord = $discord;
        $this->user = new \pxls\User($this->database);
    }

    public function __invoke(Request $request, Response $response, $args)
    {
        $params = explode("/", $request->getAttribute('params'));

        if($request->isMethod("POST")) {
            switch($params[0]) {
                case 'user':
                    return $this->userhandler($request,$response,$args);
                    break;
            }
        } elseif($request->isMethod("GET")) {
            switch($params[0]) {
                case 'activitylog':
                    if(!isset($params[1])) { $scope = null; } else { $scope = $params[1]; }
                    return $response->withStatus(200)->withJson(["data"=>$this->lastActionLog($scope)]);
                    break;
                case 'userinfo':
                    if(!isset($params[1])) { $user = null; } else { $user = $params[1]; }
                    return $response->withStatus(200)->withJson(["data"=>$this->getUserInfo($user)]);
                    break;
                case "lastSignups":
                    return $response->withStatus(200)->withJson(["data"=>$this->lastSignups()]);
                    break;
            }
        }
        return $response;
    }

    protected function getUserInfo($username) {
        return $this->user->getUserByName($username);
    }

    protected function userHandler(Request $request, Response $response, $args) {
        $params = explode("/", $request->getAttribute('params'));
        $postData = $request->getParsedBody();
        switch($params[1]) {
            case 'note':
                switch($params[2]) {
                    case 'add':
                        if(!empty($postData["message"])) {
                            $this->user->addNoteToUser($postData["targetid"], $postData["message"], null);
                            return $response->withJson(["success" => true]);
                        }
                        break;
                    case 'delete':
                        if(!empty($postData["targetid"])) {
                            $this->user->deleteNote($postData["targetid"]);
                            return $response->withJson(["success" => true]);
                        }
                        break;
                    case 'comment':
                        if(!empty($postData["message"]) && $postData["targetid"] != 0) {
                            $this->user->addNoteToUser($postData["targetid"],$postData["message"],$postData["noteid"]);
                            return $response->withJson(["success"=>true]);
                        }
                        break;
                }
        }
        return false;
    }

    protected function lastSignups() {
        $toRet = [];
        $qSignups = $this->database->query("SELECT id,username,signup_time,login,ban_reason,(role='BANNED' OR role='SHADOWBANNED' OR (now() < ban_expiry)) AS 'banned',signup_ip,last_ip,pixel_count FROM users WHERE 1 ORDER BY signup_time DESC LIMIT 100");
        $qSignups->execute();

        while ($signup = $qSignups->fetch(\PDO::FETCH_ASSOC)) {
            $parsedTime = $signup["signup_time"];

            $loginData = Utils::MakeUserLoginURL($signup["login"], true);
            if ($loginData["service"] == "discord") {
                $signup["login"] = '<button class="btn btn-link" style="padding: 0; margin: 0;" data-discord-id="'.$loginData["id"].'" onclick="askDiscord(\''.$loginData["ID"].'\');" target="_blank">'.$loginData["service"].':'.$loginData["ID"].'</a>';;
            } else {
                $signup["login"] = '<a href="'.$loginData["URL"].'" target="_blank">'.$loginData["service"].':'.$loginData["ID"].'</a>';
            }

            $signup["signup_ip"] = inet_ntop($signup["signup_ip"]);
            $signup["last_ip"] = inet_ntop($signup["last_ip"]);

            $username = $signup["username"];
            $signup["username"] = '<a href="userinfo/'.$username.'" target="_blank">'.$username.'</a>';

            $toRet[] = $signup;
        }

        return $toRet;
    }

    protected function lastActionLog($scope=null) {
        $logs = [];
        switch($scope) {
            case 'adminlog':
                $qLogs = $this->database->query("SELECT * FROM admin_log WHERE channel = 'pxlsAdmin' AND message NOT LIKE '%api%' ORDER BY id DESC LIMIT 1000;");
                break;
            case 'canvaslog':
                $qLogs = $this->database->query("SELECT * FROM admin_log WHERE channel = 'pxlsCanvas' ORDER BY id DESC LIMIT 1000;");
                break;
            case 'consolelog':
                $qLogs = $this->database->query("SELECT * FROM admin_log WHERE channel = 'pxlsConsole' ORDER BY id DESC LIMIT 1000;");
                break;
            case 'apilog':
                $qLogs = $this->database->query("SELECT * FROM admin_log WHERE message LIKE '%public api%' ORDER BY id DESC LIMIT 1000;");
                break;
            default:
                $qLogs = $this->database->query("SELECT * FROM admin_log WHERE message NOT LIKE '%public api%' ORDER BY id DESC LIMIT 1000;");
                break;
        }
        $logParser = new \pxls\LogParser();
        $qLogs->execute();
        while($log = $qLogs->fetch(\PDO::FETCH_ASSOC)) {
            if ($log["channel"] == "pxlsConsole") {
                $log["username"] = "Server Console";
            } else {
                $user = $this->user->getUserById($log["userid"]);
                $log["username"] = $user["username"];
            }
            $log["message"] = $logParser->humanLogMessage($logParser->parse($log["message"]),$log["username"],$log["message"]);
            $log["time"] = date("d.m.Y H:i:s",$log["time"]);
            $log["username"] = '<a href="/userinfo/'.$log["username"].'" target="_blank">'.$log["username"].'</a>';
            $logs[] = $log;
        }
        return $logs;
    }

}
