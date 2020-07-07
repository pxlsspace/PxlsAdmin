<?php

namespace pxls\Action;

use pxls\DiscordHook;
use Slim\Views\Twig;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

final class LogPage
{
    private $view;
    private $logger;
    private $database;
    private $discord;

    public function __construct(Twig $view, LoggerInterface $logger, \PDO $database, DiscordHook $discord)
    {
        $this->view = $view;
        $this->logger = $logger;
        $this->database = $database;
        $this->discord = $discord;
    }

    public function __invoke(Request $request, Response $response, $args)
    {
        global $app;
        $data = [];
        $data['args'] = $args;
        $data['webroots'] = $app->getContainer()->get("settings")["webroots"];

        //region UserData
        $user = new \pxls\User($this->database);
        $data['userdata'] = $user->getUserById($_SESSION['user_id']);
        //endregion

        if(in_array('administrator', $data['userdata']['roles'])) return $response->withStatus(403)->getBody()->write("lol, nope. you don't belong here.");

        $data['logs'] = [];
        $data['logs']['total']      = $this->getTotal();
        $data['logs']['admin']      = $this->getLogs("pxlsAdmin");
        $data['logs']['canvas']     = $this->getLogs("pxlsCanvas");
        $data['logs']['console']    = $this->getLogs("pxlsConsole");

        //$pixelsLog = new \pxls\PixelsLogParser();
        //var_dump($pixelsLog);

        $this->view->render($response, 'logpage.html.twig', $data);
        return $response;
    }

    public function getTotal() {
        return $this->database->query("SELECT COUNT(id) as total FROM admin_log")->fetch(\PDO::FETCH_OBJ)->total;
    }

    public function getLogs($channel) {
        $logParser = new \pxls\LogParser(); $rt = [];

        $logs = $this->database->prepare("SELECT * FROM admin_log WHERE channel = :channel;");
        $logs->bindParam(":channel",$channel,\PDO::PARAM_STR);
        $logs->execute();
        while($row = $logs->fetch(\PDO::FETCH_ASSOC)) {
            $qUser = $this->database->prepare("SELECT username FROM users WHERE id = :uid");
            $qUser->bindParam(":uid", $row['userid']);
            $qUser->execute();
            $username = $qUser->fetch(\PDO::FETCH_ASSOC)['username'];

            $row["message"] = $logParser->parse($row["message"]);
            $row['user_name'] = $username;
            $rt[] = $row;
        }
        return $rt;
    }
}
