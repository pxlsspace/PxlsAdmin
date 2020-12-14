<?php

namespace pxls\Action;

use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

final class Pixels
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

    private function isAllNumeric(array $arr) {
        return !in_array(false, array_map(fn($v) => is_numeric($v), $arr), true);
    }
    private function minMax($a, $b) {
        return ["min" => min($a, $b), "max" => max($a, $b)];
    }

    public function __invoke(Request $request, Response $response, $args)
    {
        global $app;
        switch(strtolower(trim($request->getAttribute('negotiation')->getMediaType()))) {
            case 'application/json':
                $where = array();
                $params = $request->getQueryParams();
                $input = array();
                $this->logger->info("params:", $params); // TODO(netux): remove debug log
                if (is_numeric($params['pixelId'])) {
                    array_push($where, "p.id = :id");
                    $input['id'] = $params['pixelId'];
                } else {
                    $rawCoords = ['x' => $params['coordsX'], 'y' => $params['coordsY']];
                    if ($this->isAllNumeric($rawCoords)) {
                        array_push($where, "p.x = :x AND p.y = :y");
                        $input['x'] = $rawCoords['x'];
                        $input['y'] = $rawCoords['y'];
                    } else {
                        $rawCoordsFrom = ['x' => $params['coordsFromX'], 'y' => $params['coordsFromY']];
                        $rawCoordsTo = ['x' => $params['coordsToX'], 'y' => $params['coordsToY']];
                        if ($this->isAllNumeric($rawCoordsFrom) && $this->isAllNumeric($rawCoordsTo)) {
                            // Fix cases where the "from" is bigger than the "to".
                            $fromToX = $this->minMax(intval($rawCoordsFrom['x']), intval($rawCoordsTo['x']));
                            $fromToY = $this->minMax(intval($rawCoordsFrom['y']), intval($rawCoordsTo['y']));

                            array_push($where, "p.x >= :fromX AND p.x <= :toX AND p.y >= :fromY AND p.y <= :toY");
                            $input['fromX'] = $fromToX['min'];
                            $input['fromY'] = $fromToY['min'];
                            $input['toX'] = $fromToX['max'];
                            $input['toY'] = $fromToY['max'];
                        }
                    }

                    $rawBefore = $params['before'];
                    if (is_numeric($rawBefore)) {
                        array_push($where, "EXTRACT(EPOCH FROM TIMEZONE('UTC', p.time)) <= :before");
                        $input['before'] = intval($rawBefore) / 1000;
                    }
                    $rawAfter = $params['after'];
                    if (is_numeric($rawAfter)) {
                        array_push($where, "EXTRACT(EPOCH FROM TIMEZONE('UTC', p.time)) >= :after");
                        $input['after'] = intval($rawAfter) / 1000;
                    }

                    $rawPlacers = $params['placers'];
                    if (is_array($rawPlacers) && sizeof($rawPlacers) > 0) {
                        $username_list = array_map(fn($name) => $this->database->quote($name), $rawPlacers);
                        array_push($where, "p.who IN (SELECT id FROM users WHERE username IN (" . join(", ", $username_list) . "))");
                    }

                    $rawColors = $params['colors'];
                    if (is_array($rawColors) && sizeof($rawColors) > 0 && $this->isAllNumeric($rawColors)) {
                        $coloridx_list = array_map(fn($cIdx) => intval($cIdx), $rawColors);
                        array_push($where, "p.color IN (" . join(", ", $coloridx_list) . ")");
                    }
                }

                try {
                    $q = "SELECT p.id as \"pixel_id\", p.x, p.y, p.time, p.color, p.undone, p.undo_action, p.mod_action, p.rollback_action, p.most_recent, u.id as \"user_id\", u.username as \"user_name\" FROM pixels p LEFT JOIN users u ON u.id = p.who WHERE " . (sizeof($where) > 0 ? join(" AND ", $where) : "true");
                    $this->logger->info("query: " . $q, $input); // TODO(netux): remove debug log
                    $query = $this->database->prepare($q);
                    $query->execute($input);
                    $pixels = $query->fetchAll(\PDO::FETCH_ASSOC);
                    $response = $response->withStatus(200)->withJson(['status' => 'success', 'data' => $pixels]);
                } catch (\Exception $e) {
                    $this->logger->error("Failed to fetch pixels from database", ["exception" => $e]);
                    $response = $response->withStatus(500)->withJson(['status' => 'error', 'error' => 'Unknown error while executing query']);
                }

                break;
            case 'text/html':
                $response = $this->view->render($response, 'pixels.html.twig', ['args' => $args, 'userdata' => (new \pxls\User($this->database))->getUserById($_SESSION['user_id']), 'webroots' => $app->getContainer()->get("settings")["webroots"]]);
                break;
        }

        return $response;
    }

}
