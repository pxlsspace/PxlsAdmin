<?php

namespace pxls\Action;

use pxls\DiscordHook;
use Slim\Views\Twig;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

final class ReportList
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

        //region Reports
        $reports = new \pxls\ReportHandler($this->database,$this->discord);
        $data['reports'] = $reports->getReports(false);
        //endregion

        $this->view->render($response, 'reportList.html.twig', $data);
        return $response;
    }
}