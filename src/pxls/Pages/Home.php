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
        $data['webroots'] = $app->getContainer()->get("settings")["webroots"];

        //region UserData
        $user = new \pxls\User($this->database);
        $data['userdata'] = $user->getUserById($_SESSION['user_id']);
        //endregion

        //region Statistics
        $stats = new \pxls\Statistics($this->database);
        $data['stats'] = [
            'total_users'           => number_format($stats->getUserCount()),
            'total_sessions'        => number_format($stats->activeSessions()),
            'total_pixels'          => number_format($stats->pixelsPlaced(0)),
            'total_pixels_hour'     => number_format($stats->pixelsPlaced("-1 hour")),
            'spu_ratio'             => number_format(($stats->activeSessions() / $stats->getUserCount()),2),
            'ppu_ratio'             => number_format(($stats->pixelsPlaced() / $stats->getUserCount()),2),
        ];
        //endregion

        //region activityreport
        $data['activity']['total_users'] = []; $data['activity']['total_pixels'] = []; $data['activity']['hourly_pixels'] = []; $data['activity']['timestamps'] = [];
        $getActivity = $this->database->query("SELECT * FROM stats ORDER BY id DESC");
        while($row = $getActivity->fetch(\PDO::FETCH_OBJ)) {
            if(!in_array($row->timestamp,$data['activity']['timestamps'])) $data['activity']['timestamps'][] = $row->timestamp;
            $data['activity'][$row->channel][] = $row->value;
        }
        $data['activity']['timestamps'] = array_reverse($data['activity']['timestamps']);
        $data['activity']['total_pixels'] = array_reverse($data['activity']['total_pixels']);
        $data['activity']['total_users'] = array_reverse($data['activity']['total_users']);
        $data['activity']['hourly_pixels'] = array_reverse($data['activity']['hourly_pixels']);
        //endregion

        //region Reports
        $reports = new \pxls\ReportHandler($this->database,$this->discord);
        $data['reports'] = $reports->getReports();

        //endregion
        $this->view->render($response, 'home.html.twig', $data);
        return $response;
    }
}
