<?php

namespace pxls\Action;

use pxls\DiscordHook;
use Slim\Views\Twig;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

final class PublicAPI
{
    private $view;
    private $logger;
    private $database;
    private $discord;
    private $remoteip;
    private $norate = ["62.143.52.112","212.114.51.106"];

    public function __construct(Twig $view, LoggerInterface $logger, \PDO $database, DiscordHook $discord)
    {
        $this->view = $view;
        $this->logger = $logger;
        $this->database = $database;
        $this->discord = $discord;
    }

    public function __invoke(Request $request, Response $response, $args)
    {
        $params = explode("/",$request->getAttribute('params'));
        $stats = new \pxls\Statistics($this->database);

        $this->remoteip = (isset($_SERVER['HTTP_X_EX_CONNECTING_IP']))?$_SERVER['HTTP_X_EX_CONNECTING_IP']:$_SERVER['REMOTE_ADDR'];

        $rateLimit = $this->checkRate();

        if($rateLimit === false) {
            $this->newRate();
            if($params[0] != "rate") $this->addHit();
        } elseif($rateLimit->accesses >= 15 && $params[0] != "rate") {
            $this->logger->info("ratelimited ".$this->remoteip);
            return $response->withStatus(509, "rate limit exceeded")->withJson(["success"=>false,"msg"=>"rate limit exceeded"]);
        } else {
            if($params[0] != "rate") $this->addHit();
        }

        if($params[0] != "rate") $this->logger->info("public api invoked by ".$this->remoteip);
        $return0 = [];

        switch($params[0]) {
            case 'users':
                if ($params[1] == 'total') {
                    $return0['success'] = $stats->getUserCount();
                    return $response->withStatus(200)->withJson($return0);
                }
                break;
            case 'pixels':
                if ($params[1] == 'total') {
                    $return0['success'] = $stats->pixelsPlaced();
                    return $response->withStatus(200)->withJson($return0);
                } elseif($params[1] == 'last') {
                    switch($params[2]) {
                        case 'minute': $time='-1 minute'; break;
                        case 'day': $time='-1 day'; break;
                        case 'week': $time='-1 week'; break;
                        case 'month': $time='-1 month'; break;
                        default: $time='-1 minute'; break;
                    }
                    $return0['success'] = $stats->pixelsPlaced($time);
                    return $response->withStatus(200)->withJson($return0);
                } elseif($params[1] == 'top') {
                    $return0['success'] = $stats->topUser();
                    return $response->withStatus(200)->withJson($return0);
                } elseif($params[1] == 'color') {
                    $return0['success'] = $stats->topColor();
                    return $response->withStatus(200)->withJson($return0);
                }
                break;
            case 'rate':
                $return0['success'] = ["remote"=>$this->remoteip,"rate"=>$rateLimit->accesses,"rate_reset"=>strtotime("+15 minutes",$rateLimit->time_created)];
                return $response->withStatus(200)->withJson($return0);
                break;
        }

        return $response->withStatus(420,"missing parameters")->withJson($this->determineLocations());
    }

    protected function determineLocations() {
        $locations = [];
        $locations["success"] = false;
        $locations["endpoints"]['/api/public/users/total'] = [
            "method"    =>  "GET",
            "success_response"     =>  '{\"success\":1234}',
        ];
        $locations["endpoints"]['/api/public/pixels/total'] = [
            "method"    =>  "GET",
            "success_response"     =>  '{\"success\":1234}',
        ];
        $locations["endpoints"]['/api/public/pixels/top'] = [
            "method"    =>  "GET",
            "success_response"     =>  '{"success":[{"username":"canvas_user","pixel_count":"123"}, [...]}',
        ];
        $locations["endpoints"]['/api/public/pixels/color'] = [
            "method"    =>  "GET",
            "success_response"     =>  '{"success":[{"uses":"58130","color":"0","hex":"#FFFFFF"},...]}',
        ];
        $locations["endpoints"]['/api/public/pixels/last/[$timespan]'] = [
            "method"    =>  "GET",
            "params"    =>  ["no-param-set"=>"hour","timespan"=>"minute,hour,day,week,month"],
            "success_response"     =>  '{\"success\":1234}',
        ];

        return $locations;
    }

    protected function checkRate() {
        $existent = $this->database->prepare("SELECT * FROM api_access WHERE remote_addr = :remote AND time_created > :time ORDER BY id DESC LIMIT 1");
        $existent->bindParam(":remote", $this->remoteip);
        $existent->bindParam(":time",   strtotime("-15 minutes"));
        $existent->execute();
        if ($existent->rowCount() == 1) {
            $exFetch = $existent->fetch(\PDO::FETCH_OBJ);

            if(in_array($exFetch->remote_addr,$this->norate)) {
                $exFetch->accesses = 0;
            }

            return $exFetch;
        }
        return false;
    }

    protected function newRate() {
        $new = $this->database->prepare("INSERT INTO api_access(remote_addr,time_created) VALUES (:remote,:time)");
        $new->bindParam(":remote", $this->remoteip);
        $new->bindParam(":time",   time());
        $new->execute();
    }

    protected function addHit() {
        $existent = $this->database->prepare("UPDATE api_access SET accesses = accesses+1 WHERE remote_addr = :remote AND time_created < :time ORDER BY id DESC LIMIT 1");
        $existent->bindParam(":remote", $this->remoteip);
        $existent->bindParam(":time",   strtotime("+15 minutes"));
        $existent->execute();
    }
}