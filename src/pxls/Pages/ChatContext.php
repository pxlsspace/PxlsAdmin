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
            $nonce = filter_input(INPUT_POST, 'nonce', FILTER_SANITIZE_STRING);
            if ($nonce !== FALSE) {
                $toRet = $this->chatReportHandler->getContextAroundNonce($nonce, 50);
            }
            return $response->withJson(["success" => $nonce !== FALSE, "data" => $toRet]);
        } else {
            $this->view->render($response, 'ChatContext.html.twig', ['args' => $args, 'userdata' => (new \pxls\User($this->database))->getUserById($_SESSION['user_id'])]);
        }
        return $response;
    }
}