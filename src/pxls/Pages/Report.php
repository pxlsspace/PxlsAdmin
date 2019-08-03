<?php

namespace pxls\Action;

use pxls\DiscordHook;
use Slim\Views\Twig;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

final class Report
{
    private $view;
    private $logger;
    private $database;
    private $reportInterface;
    private $chatReportInterface;
    private $bypassToken;

    public function __construct(Twig $view, LoggerInterface $logger, \PDO $database, DiscordHook $discord, $bypassToken)
    {
        $this->view = $view;
        $this->logger = $logger;
        $this->database = $database;
        $this->discord = $discord;
        $this->reportInterface = new \pxls\ReportHandler($this->database, $this->discord);
        $this->chatReportInterface = new \pxls\ChatReportHandler($this->database, $this->discord);

        if (!isset($bypassToken) || empty($bypassToken)) {
            $bypassToken = false;
        }
        $this->bypassToken = $bypassToken;
    }

    public function __invoke(Request $request, Response $response, $args)
    {
        $params = explode("/",$request->getAttribute('params'));

        switch($params[0]) {
            case 'claim':
                if(isset($params[1])) {
                    $reportId = intval($params[1]);
                    $this->reportInterface->claim($reportId, 1);
                    $this->logger->info("claimed report $reportId",array('userid'=>$_SESSION['user_id']));
                    return $response->withStatus(200)->withJson(["status"=>"success"]);
                }
                break;
            case 'unclaim':
                if(isset($params[1])) {
                    $reportId = intval($params[1]);
                    $this->reportInterface->claim($reportId, 0);
                    $this->logger->info("unclaimed report $reportId",array('userid'=>$_SESSION['user_id']));
                    return $response->withStatus(200)->withJson(["status"=>"success"]);
                }
                break;
            case 'resolve':
                if(isset($params[1])) {
                    $reportId = intval($params[1]);
                    if ($this->reportInterface->resolve($reportId)) {
                        $this->logger->info("resolved report $reportId",array('userid'=>$_SESSION['user_id']));
                        return $response->withStatus(200)->withJson(["status"=>"success"]);
                    } else {
                        $this->logger->info("failed to resolve a report because they did not own it ($reportId)",array('userid'=>$_SESSION['user_id']));
                        return $response->withStatus(400)->withJson(["status"=>"failed","reason"=>"claimed by someone else"]);
                    }
                }
                break;
            case 'details':
                if(isset($params[1])) {
                    $reportId = intval($params[1]);
                    return $response->withStatus(200)->withJson($this->reportInterface->getReportDetails($reportId));
                }
                break;
            case 'chatClaim':
                if(isset($params[1])) {
                    $reportId = intval($params[1]);
                    $this->chatReportInterface->setClaimed($reportId, true);
                    $this->logger->info("claimed chat report $reportId",array('userid'=>$_SESSION['user_id']));
                    return $response->withStatus(200)->withJson(["status"=>"success"]);
                }
                break;
            case 'chatUnclaim':
                if(isset($params[1])) {
                    $reportId = intval($params[1]);
                    $this->chatReportInterface->setClaimed($reportId, false);
                    $this->logger->info("unclaimed chat report $reportId",array('userid'=>$_SESSION['user_id']));
                    return $response->withStatus(200)->withJson(["status"=>"success"]);
                }
                break;
            case 'chatResolve':
                if(isset($params[1])) {
                    $reportId = intval($params[1]);
                    if ($this->chatReportInterface->setResolved($reportId, true)) {
                        $this->logger->info("resolved chat report $reportId",array('userid'=>$_SESSION['user_id']));
                        return $response->withStatus(200)->withJson(["status"=>"success"]);
                    } else {
                        $this->logger->info("failed to resolve a chat report because they did not own it ($reportId)",array('userid'=>$_SESSION['user_id']));
                        return $response->withStatus(400)->withJson(["status"=>"failed","reason"=>"claimed by someone else"]);
                    }
                }
                break;
            case 'chatDetails':
                if(isset($params[1])) {
                    $reportId = intval($params[1]);
                    return $response->withStatus(200)->withJson($this->chatReportInterface->getReportDetails($reportId));
                }
                break;
            case 'announce':
                $headerCheck = $request->getHeader("HTTP_AUTHORIZATION");
                $headerCheck = sizeof($headerCheck) > 0 ? $headerCheck[0] : false;

                if ($this->bypassToken !== false && $headerCheck == "Bearer ".$this->bypassToken) {
                    if ($this->reportInterface->announce($this->chatReportInterface->getOpenReportsCount())) {
                        return $response->withStatus(200)->withJson(["status" => "success"]);
                    } else {
                        return $response->withStatus(200)->withJson(["status" => "failed", "msg" => "no opened reports"]);
                    }
                } else {
                    return $response->withJson(["access"=>"denied"]);
                }
                break;
            case 'discordinfo':
                    if($this->reportInterface->discordinfo($params[1])) {
                        return $response->withStatus(200)->withJson(["status" => "success"]);
                    } else {
                        return $response->withStatus(200)->withJson(["status" => "failed", "msg"=>"no opened reports"]);
                    }
                break;

            case 'reload':
                $toReturn = [
                    "canvasReports" => $this->reportInterface->getReports(!isset($_REQUEST['all'])),
                    "chatReports" => $this->chatReportInterface->getReports(!isset($_REQUEST['all']))
                ];
                return $response->withStatus(200)->withJson(["data"=>$toReturn]);
                break;

        }

        return $this->error($response);
    }


    protected function error(Response $response) {
        $html = <<<HTML
<pre>
                       (                                                 
            _           ) )                                              
         _,(_)._        ((     I'M A LITTLE TEAPOT SHORT AND STOUT       
    ___,(_______).        )                                              
  ,'__.   /       \    /\_      THIS IS MY (CENSORED) AND THIS IS MY CUNT
 /,' /  |""|       \  /  /                                               
| | |   |__|       |,'  /                                                
 \`.|                  /                                                 
  `. :           :    /                                                  
    `.            :.,'                                                   
Stef  `-.________,-'                                                     
</pre>
HTML;
        $response->getBody()->write($html);
        return $response->withStatus(418);
    }
}
