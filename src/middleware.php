<?php
// Application middleware

// e.g: $app->add(new \Slim\Csrf\Guard);

use \Slim\Middleware\TokenAuthentication as TokenAuthentication;

$authenticator = function($request, TokenAuthentication $tokenAuth){
    global $app;
    $bypassToken = $app->getContainer()->get("settings")["tokens"]["bypass"];
    if (!isset($bypassToken) || empty($bypassToken)) {
        $bypassToken = false;
    }
    $token = $tokenAuth->findToken($request);
    $user = new pxls\User($app->getContainer()->get('database'));
    $user = $user->checkToken($token, $bypassToken);
    $_SESSION['user_id'] = $user['id'];
};

$error = function(\Slim\Http\Request $request, \Slim\Http\Response $response, TokenAuthentication $tokenAuth) {
    $output = [];
    $output['error'] = [
        'msg' => $tokenAuth->getResponseMessage(),
        'token' => $tokenAuth->getResponseToken(),
        'status' => 401,
        'error' => true
    ];
    return $response->withJson($output, 401);
};

$app->add(new TokenAuthentication([
    'path' => '/',
    'authenticator' => $authenticator,
    'cookie' => 'pxls-token',
    'secure' =>  $app->getContainer()->get("settings")["authentication"]["secure"] === true,
    'passthrough' => ['/api/public','/api/report/announce'],
    'error' => $error,
]));


$app->add(function($request,$response,$next) {
    $userdata = new pxls\User($this->database);
    $userdata = $userdata->getUserById($_SESSION['user_id']);
    $request = $request->withAttribute('userdata', $userdata);
    $response = $next($request, $response);
    return $response;
});

$app->add(function($request,$response,$next) {
    $supportedTypes = ['text/html', 'application/json'];
    $accept = $request->getHeaderLine('Accept');
    $bestMatch = new Negotiation\Negotiator()->getBest($accept, $supportedTypes);
    $contentType = $bestMatch
        ? $bestMatch->getBasePart() . '/' . $bestMatch->getSubPart()
        : $supportedTypes[0];
    $request = $request->withAttribute('negotiated_type', $contentType);
    $response = $next($request, $response);
    return $response;
});
