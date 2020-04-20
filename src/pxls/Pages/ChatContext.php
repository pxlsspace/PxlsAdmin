<?php
namespace pxls\Action;

use pxls\ChatReportHandler;
use pxls\DiscordHook;
use Slim\Views\Twig;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

final class ChatContext
{
    private $view;
    private $logger;
    private $database;
    private $discord;
    private $chatReportHandler;

    public function __construct(Twig $view, LoggerInterface $logger, \PDO $database, DiscordHook $discord) {
        $this->view = $view;
        $this->logger = $logger;
        $this->database = $database;
        $this->discord = $discord;
        $this->chatReportHandler = new ChatReportHandler($this->database, $this->discord);
    }

    public function __invoke(Request $request, Response $response, $args)
    {
        if ($request->getMethod() === "POST") {
            $toRet = [];
            $cmid = filter_input(INPUT_POST, 'cmid', FILTER_SANITIZE_STRING);
            if ($cmid !== FALSE) {
                $toRet = $this->chatReportHandler->getContextAroundID($cmid, 100);
            }
            return $response->withJson(["success" => $cmid !== FALSE, "data" => $toRet]);
        } else {
            $this->view->render($response, 'ChatContext.html.twig', ['args' => $args, 'userdata' => (new \pxls\User($this->database))->getUserById($_SESSION['user_id'])]);
        }
        return $response;
    }
}
