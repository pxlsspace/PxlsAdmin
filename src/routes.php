<?php
// Routes

$app->get('/', \pxls\Action\Home::class)->setName('home');
$app->get('/logs', \pxls\Action\LogPage::class)->setName('logs');

$app->map(['GET', 'POST'], '/search', \pxls\Action\Search::class)->setName('search');
$app->map(['GET', 'POST'], '/userinfo/{identifier}', \pxls\Action\Profile::class)->setName('profile');

$app->map(['GET','POST'], '/api/private[/{params:.*}]', \pxls\Action\PrivateAPI::class)->setName('prapi');
$app->get('/api/public[/{params:.*}]', \pxls\Action\PublicAPI::class)->setName('papi');

$app->post('/api/log', \pxls\Action\Logger::class)->setName('logger');
$app->get('/api/report[/{params:.*}]', \pxls\Action\Report::class)->setName('report');