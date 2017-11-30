<?php

/*
 * Config File
 */

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$stream = new StreamHandler('logs/main.log', Logger::DEBUG);

// Create a logger for the debugging-related stuff
$logger = new Logger('debug');
$logger->pushHandler($stream);

//Sentry !
//$client = new Raven_Client('');
//$client->install();

$demo = $_ENV['DEMO'] === 'true'? true: false;

// Whoops error handling
$whoops = new Whoops\Run();
// Set Whoops as the default error and exception handler used by PHP:
$whoops->register();
$whoops->pushHandler(new Whoops\Handler\JsonResponseHandler());
error_reporting(E_ALL);

/*
 *  START REAL CONFIGURATION HERE
 *      EDIT ONLY IF IT'S
 *          NECESSARY
 */

$config = [
            "title" => "",
            "version" => "1.".rand(0,9).".".rand(0,9),
            "demo" => $_ENV['DEMO'] === 'true'? true: false,
            "menu"=> [
                        ['/','Home'],
                    ],
            ];
?>
