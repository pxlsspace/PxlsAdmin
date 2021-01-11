<?php

namespace pxls\Action;

use pxls\DiscordHook;
use Slim\Views\Twig;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

final class NotifyController
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

        //region Meta
        $data['args'] = $args;
        //endregion

        //region UserData
        $user = new \pxls\User($this->database);
        $data['userdata'] = $user->getUserById($_SESSION['user_id']);
        //endregion

        //region Notifications
        $data['notifications'] = $this->getNotifications();
        //endregion

        $this->view->render($response, 'notification.html.twig', $data);
        return $response;
    }

    private function getNotifications() {
        $toReturn = [];

        $query = $this->database->query("SELECT n.id,to_timestamp(n.time) AS \"time\",to_timestamp(n.expiry) AS \"expiry\",n.title,n.content,u.username as \"who_name\",n.who as \"who_id\",CAST(EXTRACT(epoch FROM NOW()) AS INTEGER)>n.expiry AND n.expiry<>0 AS \"_expired\",n.expiry<>0 AS \"_expires\" FROM notifications n LEFT OUTER JOIN users u ON u.id = n.who ORDER BY time DESC");
        if ($query->execute()) {
            $toReturn = $query->fetchAll(\PDO::FETCH_ASSOC);
        }

        return $toReturn;
    }
}
