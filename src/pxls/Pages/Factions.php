<?php

namespace pxls\Action;

use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

final class Factions
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
        global $app;
        switch(strtolower(trim($request->getAttribute('negotiation')->getMediaType()))) {
            case 'application/json':
                $qFactions = $this->database->query("SELECT f.id,f.name,f.tag,f.color,u.username as \"owner\", u.id as \"ownerId\",f.created,f.\"canvasCode\" FROM faction f INNER JOIN users u ON u.id = f.owner ORDER BY f.id;");
                $factions = array();

                try {
                    $factions = $qFactions->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Exception $e) {}

                $response = $response->withStatus(200)->withJson(['status' => 'success', 'data' => $factions]);
                break;
            case 'text/html':
                $this->view->render($response, 'factions.html.twig', ['args' => $args, 'userdata' => (new \pxls\User($this->database))->getUserById($_SESSION['user_id'])]);
                break;
        }

        return $response;
    }

}
