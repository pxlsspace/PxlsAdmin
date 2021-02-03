<?php
// Routes

use pxls\Action\NotifyController;

$app->get('/', \pxls\Action\Home::class)->setName('home');
$app->get('/logs', \pxls\Action\LogPage::class)->setName('logs');
$app->get('/reports', \pxls\Action\ReportList::class)->setName('reportList');
$app->get('/pixels', \pxls\Action\Pixels::class)->setName('pixels');
$app->map(['GET', 'POST'], '/chatContext', \pxls\Action\ChatContext::class)->setName('ChatContext');

$app->map(['GET', 'POST'], '/search', \pxls\Action\Search::class)->setName('search');
$app->map(['GET', 'POST'], '/userinfo/{username}', \pxls\Action\Profile::class)->setName('profileUsername');
$app->map(['GET', 'POST'], '/userinfo/id/{id}', \pxls\Action\Profile::class)->setName('profileId');
$app->map(['GET', 'POST'], '/profile/{path:.*}', function(Slim\Http\Request $request, Slim\Http\Response $response, $args) use ($app) {
	$path = $args["path"];
	return $response->withRedirect("/userinfo/$path", 307);
})->setName('profileRedirect');

$app->map(['GET','POST'], '/api/private[/{params:.*}]', \pxls\Action\PrivateAPI::class)->setName('prapi');
$app->get('/api/public[/{params:.*}]', \pxls\Action\PublicAPI::class)->setName('papi');

$app->post('/api/log', \pxls\Action\Logger::class)->setName('logger');
$app->get('/api/report[/{params:.*}]', \pxls\Action\Report::class)->setName('report');

$app->map(['GET', 'POST'], '/notifications', NotifyController::class)->setName('notifications');

$app->get('/factions', \pxls\Action\Factions::class)->setName('factions');
