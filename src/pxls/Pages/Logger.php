<?php

namespace pxls\Action;

use Slim\Views\Twig;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

final class Logger
{
    private $view;
    private $logger;
    private $database;

    public function __construct(Twig $view, LoggerInterface $logger, \PDO $database)
    {
        $this->view = $view;
        $this->logger = $logger;
        $this->database = $database;
    }

    public function __invoke(Request $request, Response $response, $args)
    {
        $action = $request->getParam("action");
        $target = $request->getParam("target");

        $logLine = "$action $target";

        switch($action) {
            case "ban":
            case "permaban":
            case "shadowban":
                $reason = $request->getParam("reason");
                if (!$reason) $reason = "";
                $logLine = "$action $target with reason: $reason";
                break;
        }
        $this->logger->info($logLine,array('userid'=>$_SESSION['user_id']));
    }

}
?>