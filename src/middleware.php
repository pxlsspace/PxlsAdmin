<?php

$app->add(function($request,$response,$next) use($app) {
    function isPassthrough($path): bool {
        if (str_starts_with($path, '/api/public')) { return true; }
        if ($path == '/api/report/announce') { return true; }
        if ($path == '/login') { return true; }
        if ($path == '/logout') { return true; }
        
        return false;
    }
    
    if (isPassthrough($request->getUri()->getPath())) {
        return $next($request, $response);
    }

    if (isset($_SESSION['user_id'])) {
        $user_db = new pxls\User($app->getContainer()->get('database'));
        $user = $user_db->getUserById($_SESSION['user_id']);
        if ($user_db->checkRole($user)) {
            $request = $request->withAttribute('userdata', $user);
            return $next($request, $response);
        }  else {
            $view = $app->getContainer()->get('renderer');
            $data = ['userdata' => $user];
            return $view->render($response, 'error/403.html.twig', $data);
        }
    } else {
        $view = $app->getContainer()->get('renderer');
        return $view->render($response, 'error/401.html.twig', []);
    }
});

$settings = $app->getContainer()->get('settings');

$app->add(function($request,$response,$next) {
    $supportedTypes = ['text/html', 'application/json'];
    $accept = $request->getHeaderLine('Accept');
    $negotiator = new Negotiation\Negotiator();
    $bestMatch = $negotiator->getBest($accept, $supportedTypes);
    $contentType = $bestMatch
        ? $bestMatch->getBasePart() . '/' . $bestMatch->getSubPart()
        : $supportedTypes[0];
    $request = $request->withAttribute('negotiated_type', $contentType);
    $response = $next($request, $response);
    return $response;
});
