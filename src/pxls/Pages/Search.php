<?php

namespace pxls\Action;

use Slim\Exception\SlimException;
use Slim\Views\Twig;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use pxls\Utils;

final class Search
{
    private $view;
    private $logger;
    private $database;
    protected $result = [];
    // TODO (Flying): missing role
    private $qs = "id, username, login, signup_time, cooldown_expiry, ban_expiry, ban_reason, signup_ip as signup_ip, last_ip as last_ip, pixel_count";

    public function __construct(Twig $view, LoggerInterface $logger, \PDO $database)
    {
        $this->view = $view;
        $this->logger = $logger;
        $this->database = $database;
    }

    public function __invoke(Request $request, Response $response, $args)
    {
        global $app;
        $data['webroots'] = $app->getContainer()->get("settings")["webroots"];
        $user = new \pxls\User($this->database);
        $data['userdata'] = $user->getUserById($_SESSION['user_id']);
        if(empty($request->getParam('q'))) {
            return $response->withStatus(302)->withHeader('Location','/');
        }
        $data['result'] = $this->doSearch(trim($request->getParam('q')), $request->getParam('type', 'user'));
        $this->view->render($response, 'search.html.twig', $data);
        return $response;
    }

    protected function addResult(array $newUser, $reason) {
        $resultExists = false;
        foreach($this->result as $updateUser) {
            if ($updateUser['id'] == $newUser['id']) {
                $resultExists = true;
                $updateUser['reason'][] = $reason;
            }
        }
        if(!$resultExists) {
            $newUser["login_url"] = Utils::MakeUserLoginURL($newUser["login"]);

            $newUser['reason'] = [$reason];
            $this->result[] = $newUser;
        }
    }

    protected function doSearch($needle, $type) {
        switch($type) {
            case 'user':
                $this->searchByUser($needle.'%');
                break;
            case 'ip':
                $this->searchByIP($needle);
                break;
            case 'login':
                $this->searchByLogin($needle);
                break;
        }
        return $this->result;
    }

    protected function searchByUser($needle) {
        $rows = $this->_performSearch(sprintf("SELECT %s FROM users WHERE UPPER(username) LIKE UPPER(:search) LIMIT 30", $this->qs), [
            ":search" => [str_replace('_', '\_', $needle), \PDO::PARAM_STR]
        ]);

        //Search for accounts with same IPs
        foreach($rows as $row) {
            $this->_performSearch(sprintf("SELECT %s FROM users WHERE last_ip = :ip", $this->qs), [
                ':ip' => [$row['last_ip'], \PDO::PARAM_STR]
            ], 'same last_ip');
            $this->_performSearch(sprintf("SELECT %s FROM users WHERE signup_ip = :ip", $this->qs), [
                ':ip' => [$row['signup_ip'], \PDO::PARAM_STR]
            ], 'same signup_ip');
        }
    }

    protected function searchByIP($needle) {
        $this->_performSearch(sprintf("SELECT %s FROM users WHERE last_ip=:ip OR signup_ip=:ip LIMIT 30", $this->qs), [
            ":ip" => [$needle, \PDO::PARAM_STR]
        ]);
        $this->_performSearch("SELECT u.id, u.username, u.login, u.signup_time, u.cooldown_expiry, u.ban_expiry, u.ban_reason, u.signup_ip as signup_ip, u.last_ip as last_ip, u.pixel_count, l.ip AS log_ip FROM ip_log l LEFT OUTER JOIN users u ON u.id = l.user WHERE :ip = l.ip", [
            ':ip' => [$needle, \PDO::PARAM_STR]
        ], 'ip_log match');
    }

    protected function searchByLogin($needle) {
        $this->_performSearch(sprintf("SELECT %s FROM users WHERE UPPER(login) LIKE UPPER(:login) LIMIT 30", $this->qs), [
            ':login' => [sprintf("%%%s%%", str_replace('_', '\_', $needle)), \PDO::PARAM_STR]
        ]);
    }

    /**
     * @param $queryString "select * from table where param=:param"
     * @param $toBind [":param" => ["param_value", \PDO::PARAM_TYPE]]
     * @param $reason "associated last_ip" (this is for UX only, shows in the 'reason' column on the front-end to explain why this row exists, e.g. 'last_ip' signals that this row was matched because the user shares the same last_ip)
     * @return mixed Returns a copy of the rows that have been added to the local results
     */
    private function _performSearch($queryString, $toBind, $reason = "original query") {
        $toRet = [];
        $query = $this->database->prepare($queryString);
        foreach($toBind as $key=>$value) {
            $query->bindParam($key, $value[0], $value[1]);
        }
        if ($query->execute()) {
            while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
                $this->addResult($row, $reason);
                $toRet[] = $row;
            }
        }
        return $toRet;
    }

}
