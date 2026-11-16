<?php

namespace pxls\Action;

use pxls\DiscordHook;
use Slim\Views\Twig;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

final class Home
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

        //region UserData
        $user = new \pxls\User($this->database);
        $data['userdata'] = $user->getUserById($_SESSION['user_id']);
        //endregion

        //region Statistics
        $stats = new \pxls\Statistics($this->database);
        $data['stats'] = [
            'total_users'       => number_format($stats->getUserCount()),
            'active_users'      => number_format($stats->getActiveUserCount()),
            'total_lookups'     => number_format($stats->getLookupCount()),
            'total_sessions'    => number_format($stats->activeSessions()),
            'total_pixels'      => number_format($stats->pixelsPlaced(0)),
            'total_pixels_hour' => number_format($stats->pixelsPlaced("-1 hour")),
        ];
        //endregion

        $this->view->render($response, 'home.html.twig', $data);
        return $response;
    }
}
