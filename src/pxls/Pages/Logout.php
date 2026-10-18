<?php

namespace pxls\Action;

use Slim\Http\Request;
use Slim\Http\Response;

final class Logout
{
    public function __invoke(Request $request, Response $response, $args): Response
    {
        unset($_SESSION['user_id']);
        return $response->withStatus(307)->withHeader('Location', '/');
    }
}
