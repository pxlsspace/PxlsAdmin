<?php

namespace pxls\Action;

use Slim\Exception\SlimException;
use Slim\Views\Twig;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

final class Search
{
    private $view;
    private $logger;
    private $database;
    protected $result = [];
    private $qs = "id, username, login, signup_time, cooldown_expiry, role, ban_expiry, ban_reason, INET6_NTOA(signup_ip) as signup_ip, INET6_NTOA(last_ip) as last_ip, pixel_count";

    public function __construct(Twig $view, LoggerInterface $logger, \PDO $database)
    {
        $this->view = $view;
        $this->logger = $logger;
        $this->database = $database;
    }

    public function __invoke(Request $request, Response $response, $args)
    {
        global $app;
        $data['webroots'] = $app->getContainer()->get("settings")["webroots"];
        $user = new \pxls\User($this->database);
        $data['userdata'] = $user->getUserById($_SESSION['user_id']);
        if(empty($request->getParam('q'))) {
            return $response->withStatus(302)->withHeader('Location','/');
        }
        $data['result'] = $this->doSearch($request->getParam('q'));
        $this->view->render($response, 'search.html.twig', $data);
        return $response;
    }

    protected function addResult(array $newUser, $reason) {
        $resultExists = false;
        foreach($this->result as $updateUser) {
            if ($updateUser['id'] == $newUser['id']) {
                $resultExists = true;
                $updateUser['reason'][] = $reason;
            }
        }
        if(!$resultExists) {
            $replace = [
                "reddit:"=>"https://reddit.com/u/",
                "google:"=>"https://plus.google.com/",
                "discord:"=>'#" onclick="askDiscord(\'',
            ];
            $newUser['login_url'] = strtr($newUser['login'],$replace);
            if(strpos($newUser['login'],"discord") !== false) $newUser['login_url'] .= "');";
            //$newUser['last_ip'] = inet_ntop($newUser['last_ip']);
            //$newUser['signup_ip'] = inet_ntop($newUser['signup_ip']);
            $newUser['reason'] = [$reason];
            $this->result[] = $newUser;
        }
    }

    protected function doSearch($needle) {
        $this->searchByUser($needle.'%');
        return $this->result;
    }

    protected function searchByUser($needle) {
        $search = $this->database->prepare("SELECT ".$this->qs." FROM users WHERE username LIKE :search LIMIT 30");
        $needle = str_replace("_","\_",$needle);
        $search->bindParam(":search", $needle, \PDO::PARAM_STR);
        $search->execute();
        if($search->rowCount() > 0) {
            while($row = $search->fetch(\PDO::FETCH_ASSOC)) {
		$this->addResult($row, "username");
    		$this->searchForIp($row['signup_ip'], "signup_ip");
                $this->searchForIp($row['last_ip'], "last_ip");
            }
        }
    }

    protected function searchForIp($needle,$why) {
        $search = $this->database->prepare("SELECT ".$this->qs." FROM users WHERE $why = INET6_ATON(:search)");
        $search->bindParam(":search", $needle, \PDO::PARAM_STR);
        $search->execute();
        if($search->rowCount() > 0) {
            while($row = $search->fetch(\PDO::FETCH_ASSOC)) {
                $this->addResult($row, $why);
            }
        }
    }

}
