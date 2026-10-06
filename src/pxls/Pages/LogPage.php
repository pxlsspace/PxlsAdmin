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

        //region UserData
        $user = new \pxls\User($this->database);
        $data['userdata'] = $user->getUserById($_SESSION['user_id']);
        //endregion

        if(!in_array('administrator', $data['userdata']['roles'])) {
            return $response->withStatus(403)->getBody()->write("lol, nope. you don't belong here.");
        }

        $this->view->render($response, 'logpage.html.twig', $data);
        return $response;
    }
}
