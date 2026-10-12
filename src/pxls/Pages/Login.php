<?php

namespace pxls\Action;

use Slim\Http\Request;
use Slim\Http\Response;

final class Login
{
    private \Slim\Views\Twig $renderer;
    private \OidcProvider $provider;
    private \PDO $db;

    public function __construct(
        \Slim\Views\Twig $renderer,
        \OidcProvider $provider,
        \PDO $db
    ) {
        $this->renderer = $renderer;
        $this->provider = $provider;
        $this->db = $db;
    }

    public function __invoke(Request $request, Response $response, $args)
    {   
        if (isset($_GET['code'])) {
            return $this->login($_GET['code'], $_GET['state'], $response);
        }

        if (isset($_GET['error'])) {
            $data = [
                'error_code' => $_GET['error'],
                'error_description' => $_GET['error_description']
            ];
            return $this->renderer->render($response, 'error/login.html.twig', $data);
        }

        if (empty($_SESSION['user_id'])) {
            return $response->withStatus(307)->withHeader('Location', $this->redirect());
        }
        
        return $response->withStatus(307)->withHeader('Location', '/');
    }

    
    private function checkState(): void {
        // TODO: proper errors
        if (empty($_GET['state']) || empty($_SESSION['authstate'])) {
            exit("missing state");
        } 
        if ($_GET['state'] !== $_SESSION['authstate']) {
            exit("invalid state");
        }
        unset($_SESSION['authstate']);
    }
    
    private function login($code, $state, Response $response): Response {
        $this->checkState();
        $token = $this->provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);
        $id = $this->provider->decodeId($token);
        $user = new \pxls\User($this->db);
        $uid = $user->subToId($id->sub);
        $_SESSION['user_id'] = $uid;
        return $response->withStatus(307)->withHeader('Location', '/');
    }

    private function redirect(): string {
        $auth_url = $this->provider->getAuthorizationUrl();
        $_SESSION['authstate'] = $this->provider->getState();
        return $auth_url;
    }
}
