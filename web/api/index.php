<?php

namespace WpGet;


ini_set('display_errors', 'On');
ini_set('html_errors', 0);
error_reporting(-1);

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \WpGet\Controllers\UserController as UserController;
use \WpGet\Controllers\DynamicController as DynamicController;
use \WpGet\Controllers\EntityController as EntityController;
use \WpGet\Controllers\RepositoryController as RepositoryController;
use \WpGet\Controllers\PublishTokenController as PublishTokenController;
use \WpGet\db\TableBase as TableBase;
use \WpGet\db\RepositoryTable as RepositoryTable;
use \WpGet\db\UsersTable as UsersTable;
use \WpGet\db\PublishTokenTable as PublishTokenTable;
use \WpGet\handler\ErrorHandler as ErrorHandler;
use \WpGet\utils\DependencyManager as DependencyManager;
use \WpGet\Controllers\AuthenticationController as AuthenticationController;

use \Monolog\Logger as Logger;
use \Monolog\Handler\RotatingFileHandler as RotatingFileHandler;
use \Bairwell\MiddlewareCors as MiddlewareCors;
use \WpGet\Models\PublishToken;
use WpGet\Controllers\PackageController;
use WpGet\Controllers\InstallerController;

require '../../vendor/autoload.php';
require '../../src/utils/DependencyManager.php';

$appPath=realpath("../../");

$dm= new DependencyManager($appPath);

$dm->requireOnceFolders( array(
  'src/models',
  'src/controllers/base',
  'src/controllers',
  'src/db/base',
  'src/db',
  'src/handlers',
  'src/utils',
));

$configPath=$dm->resolvePath('config/settings.php');
$config =  include($configPath);



// Slim configuration

$app = new \Slim\App(['settings'=> $config]);


$container = $app->getContainer();


$container['logger'] = function($c)
{
	$logger = new Logger('api');

	$rotating = new RotatingFileHandler(__DIR__ . "/../../logs/api.log", 0, Logger::DEBUG);
	$logger->pushHandler($rotating);
	return $logger;
};

 
$container['db'] = function ($container) {
	$capsule = new \Illuminate\Database\Capsule\Manager;
	$capsule->addConnection($container['settings']['db']);
	$capsule->setAsGlobal();
	$capsule->bootEloquent();

	$capsule->getContainer()->singleton(
		Illuminate\Contracts\Debug\ExceptionHandler::class,
		ErrorHandler::class
	);

	return $capsule;
};


$capsule = $container['db'];

$app->add(new MiddlewareCors( $container['settings']['cors'])); 
  

  

//Routing configuration 

$app->any('/repository/{action}[/{id}]', RepositoryController::class);
$app->any('/user/{action}[/{id}]', UserController::class);

$app->any('/publishtoken/{action}[/{id}]', PublishTokenController::class);
$app->any('/package/{action}[/{id}]', PackageController::class);

$configPath=$dm->resolvePath('web/ui/assets/settings.json');

if(!file_exists($configPath))
{
	$container['dm']=$dm;
	$app->get('/install',InstallerController::class );
}
else
{
	$app->get('/install',function($request, $response, $args)
		{
			$response->getBody()->write("Already installed. Try to navigate main url.");
		} 
	);
}

// Run app
$app->run();