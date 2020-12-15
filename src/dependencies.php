<?php
require_once('PDOHandler.php');

//region DIC configuration

$container = $app->getContainer();

// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    $view = new Slim\Views\Twig($settings['template_path'], [
        'cache' => $settings['cache_path'],
        'debug' => true,
    ]);
    $basePath = rtrim(str_ireplace('index.php', '', $c['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new Slim\Views\TwigExtension($c['router'], $basePath));
    $view->addExtension(new Twig_Extension_Debug());
    return $view;
};

$container['database'] = function ($c) {
    $db = $c->get('settings')['db'];
    $pdo = new PDO("pgsql:host=" . $db['host'] . ";port=" . $db['port'] . ";dbname=" . $db['dbname'], $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
    return $pdo;
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new PgSQLHandler($c->get('database'), "admin_log", array('userid'), $settings['level']));
    if (isset($settings['path'])) {
        $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    }
    return $logger;
};

$container['discord'] = function($c) {
    $settings = $c->get('settings')['discord'];
    $discord = new pxls\DiscordHook($settings['general']['url']);
    $discord->setName($settings['general']['name']);
    return $discord;
};

//endregion

//region site config
$container[\pxls\Action\Home::class] = function ($c) {
    return new \pxls\Action\Home($c->get('renderer'), $c->get('logger'), $c->get('database'),$c->get('discord'));
};
$container[\pxls\Action\Search::class] = function ($c) {
    return new \pxls\Action\Search($c->get('renderer'), $c->get('logger'), $c->get('database'));
};
$container[\pxls\Action\Logger::class] = function ($c) {
    return new \pxls\Action\Logger($c->get('renderer'), $c->get('logger'), $c->get('database'));
};
$container[\pxls\Action\Report::class] = function ($c) {
    return new \pxls\Action\Report($c->get('renderer'), $c->get('logger'), $c->get('database'),$c->get('discord'), $c->get('settings')['tokens']['bypass']);
};
$container[\pxls\Action\LogPage::class] = function ($c) {
    return new \pxls\Action\LogPage($c->get('renderer'), $c->get('logger'), $c->get('database'),$c->get('discord'));
};
$container[\pxls\Action\PublicAPI::class] = function ($c) {
    return new \pxls\Action\PublicAPI($c->get('renderer'), $c->get('logger'), $c->get('database'),$c->get('discord'));
};
$container[\pxls\Action\PrivateAPI::class] = function ($c) {
    return new \pxls\Action\PrivateAPI($c->get('renderer'), $c->get('logger'), $c->get('database'),$c->get('discord'));
};
$container[\pxls\Action\Profile::class] = function ($c) {
    return new \pxls\Action\Profile($c->get('renderer'), $c->get('logger'), $c->get('database'),$c->get('discord'));
};
$container[\pxls\Action\ReportList::class] = function ($c) {
    return new \pxls\Action\ReportList($c->get('renderer'), $c->get('logger'), $c->get('database'),$c->get('discord'));
};
$container[\pxls\Action\Pixels::class] = function ($c) {
    return new \pxls\Action\Pixels($c->get('renderer'), $c->get('logger'), $c->get('database'));
};
$container[\pxls\Action\ChatContext::class] = function ($c) {
    return new \pxls\Action\ChatContext($c->get('renderer'), $c->get('logger'), $c->get('database'),$c->get('discord'));
};
$container[\pxls\Action\NotifyController::class] = function ($c) {
    return new \pxls\Action\NotifyController($c->get('renderer'), $c->get('logger'), $c->get('database'),$c->get('discord'));
};
$container[\pxls\Action\Factions::class] = function ($c) {
    return new \pxls\Action\Factions($c->get('renderer'), $c->get('logger'), $c->get('database'));
};

//endregion
