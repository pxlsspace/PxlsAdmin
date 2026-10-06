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
                    $scope = isset($params[1]) ? $params[1] : null;
                    $offset = isset($_GET['start']) ? intval($_GET['start']) : 0;
                    $limit = isset($_GET['length']) ? intval($_GET['length']) : 100;
                    $search = isset($_GET['search']) ? $_GET['search'] : [];
                    $search = isset($search['value']) ? $search['value'] : '';
    
                    $totalResults = $this->getLogItemCount($scope);
                    $filteredResults = $this->getLogItemCount($scope, $search);

                    $data = [
                        'draw' => $_GET['draw'],
                        'recordsTotal' => $totalResults,
                        'recordsFiltered' => $totalResults,
                        'data' => $this->lastActionLog($scope, $offset, $limit, $search)
                    ];
                    return $response->withStatus(200)->withJson($data);
                case 'userinfo':
                    if(!isset($params[1])) { $user = null; } else { $user = $params[1]; }
                    return $response->withStatus(200)->withJson(["data"=>$this->getUserInfo($user)]);
                case "lastSignups":
                    return $response->withStatus(200)->withJson(["data"=>$this->lastSignups()]);
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
        global $app;

        $toRet = [];
        $qSignups = $this->database->query("SELECT id,username,signup_time,ban_reason,(is_shadow_banned OR ban_expiry = '1970-01-01 01:00:00' OR (now() < ban_expiry)) AS \"banned\",signup_ip,last_ip,pixel_count FROM users ORDER BY signup_time DESC LIMIT 50");
        $qSignups->execute();

        $user = new \pxls\User($this->database);

        while ($signup = $qSignups->fetch(\PDO::FETCH_ASSOC)) {
            $parsedTime = $signup["signup_time"];

            $logins = $user->getUserLoginsById($signup["id"]);
            $signup["logins"] = array_map(function (array $login) {
                $loginURL = Utils::MakeUserLoginURL($login);
                if ($login["service"] == "discord") {
                    return '<button class="btn btn-link" style="padding: 0; margin: 0;" data-discord-id="'.$login["service_uid"].'" onclick="askDiscord(\''.$login["service_uid"].'\');" target="_blank">'.$login["service"].':'.$login["service_uid"].'</a>';;
                } else {
                    return '<a href="'.$loginURL.'" target="_blank">'.$login["service"].':'.$login["service_uid"].'</a>';
                }
            }, $logins);

            $signup["signup_ip"] = $signup["signup_ip"];
            $signup["last_ip"] = $signup["last_ip"];

            $username = $signup["username"];
            $signup["username"] = '<a href="'.$app->getContainer()->router->pathFor('profileId', ['id' => $signup['id']]).'" target="_blank">'.$username.'</a>';

            $toRet[] = $signup;
        }

        return $toRet;
    }

    protected function getLogItemCount($scope,$search='') {
        $search = "%$search%";
        switch($scope) {
            case 'adminlog':
                $query = $this->database->prepare("SELECT COUNT(*) AS total FROM admin_log WHERE channel = 'pxlsAdmin' AND message NOT ILIKE '%api%' AND message LIKE :search");
                break;
            case 'canvaslog':
                $query = $this->database->prepare("SELECT COUNT(*) AS total FROM admin_log WHERE channel = 'pxlsCanvas' AND message ILIKE :search");
                break;
            case 'consolelog':
                $query = $this->database->prepare("SELECT COUNT(*) AS total FROM admin_log WHERE channel = 'pxlsConsole' AND message ILIKE :search");
                break;
            case 'apilog':
                $query = $this->database->prepare("SELECT COUNT(*) AS total FROM admin_log WHERE message LIKE '%public api%' AND message ILIKE :search");
                break;
            default:
                $query = $this->database->prepare("SELECT COUNT(*) AS total FROM admin_log WHERE message NOT LIKE '%public api%' AND message ILIKE :search");
                break;
        }
        
        $query->bindParam(":search", $search, \PDO::PARAM_STR);
        $query->execute();
        $report = $query->fetch(\PDO::FETCH_ASSOC);
        return $report['total'];
    }
    
    protected function lastActionLog($scope,$offset,$limit,$search) {
        global $app;

        $search = "%$search%";
        $usernameField = "COALESCE(u.username, CASE WHEN l.userid IS NULL THEN 'Server Console' ELSE 'Unknown User' END) as username";
        $fields = "l.id, l.channel, l.level, l.message, l.time, l.userid, $usernameField";
        $logs = [];
        switch($scope) {
            case 'adminlog':
                $qLogs = $this->database->prepare(
                    "SELECT $fields FROM admin_log l " .
                    "LEFT OUTER JOIN users u on u.id = l.userid " .
                    "WHERE channel = 'pxlsAdmin' " .
                    "AND message NOT LIKE '%api%' " .
                    "AND message ILIKE :search " .
                    "ORDER BY id DESC " .
                    "OFFSET :offset " .
                    "LIMIT :limit"
                );
                break;
            case 'canvaslog':
                $qLogs = $this->database->prepare(
                    "SELECT $fields FROM admin_log l " .
                    "LEFT OUTER JOIN users u on u.id = l.userid " .
                    "WHERE channel = 'pxlsCanvas' " .
                    "AND message ILIKE :search " .
                    "ORDER BY id DESC " .
                    "OFFSET :offset " .
                    "LIMIT :limit"
                 );
                break;
            case 'consolelog':
                $qLogs = $this->database->prepare(
                    "SELECT $fields FROM admin_log l " .
                    "LEFT OUTER JOIN users u on u.id = l.userid " .
                    "WHERE channel = 'pxlsConsole' " .
                    "AND message ILIKE :search " .
                    "ORDER BY id DESC " .
                    "OFFSET :offset " .
                    "LIMIT :limit"
                );
                break;
            case 'apilog':
                $qLogs = $this->database->prepare(
                    "SELECT $fields FROM admin_log l " .
                    "LEFT OUTER JOIN users u on u.id = l.userid " .
                    "WHERE message LIKE '%public api%' " .
                    "AND message LIKE :search " .
                    "ORDER BY id DESC " .
                    "OFFSET :offset " .
                    "LIMIT :limit"
                );
                break;
            default:
                $qLogs = $this->database->prepare(
                    "SELECT $fields FROM admin_log l " .
                    "LEFT OUTER JOIN users u on u.id = l.userid " .
                    "WHERE message NOT LIKE '%public api%' " .
                    "AND message LIKE :search " .
                    "ORDER BY id DESC " .
                    "OFFSET :offset " .
                    "LIMIT :limit"
                );
                break;
        }
        $qLogs->bindParam(":offset", $offset, \PDO::PARAM_INT);
        $qLogs->bindParam(":limit", $limit, \PDO::PARAM_INT);
        $qLogs->bindParam(":search", $search, \PDO::PARAM_STR);
        $qLogs->execute();

        $logParser = new \pxls\LogParser();
        $qLogs->execute();
        while($log = $qLogs->fetch(\PDO::FETCH_ASSOC)) {
            $log["message"] = $logParser->humanLogMessage($logParser->parse($log["message"]),$log["username"],$log["message"]);
            $log["time"] = date("d.m.Y H:i:s",$log["time"]);
            if ($log["username"] !== "Server Console") {
                $log["username"] = '<a href="'.$app->getContainer()->router->pathFor('profileId', ['id' => $log['userid']]).'" target="_blank">'.$log["username"].'</a>';
            }
            $logs[] = $log;
        }
        return $logs;
    }

}
