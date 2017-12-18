<?php

/**
 * 
 *   ____                          _                 _    ____ ___ 
 *  / ___|___  _ ____   _____ _ __| |_ ___ _ __     / \  |  _ \_ _|
 * | |   / _ \| '_ \ \ / / _ \ '__| __/ _ \ '__|   / _ \ | |_) | | 
 * | |__| (_) | | | \ V /  __/ |  | ||  __/ |     / ___ \|  __/| | 
 *  \____\___/|_| |_|\_/ \___|_|   \__\___|_|    /_/   \_\_|  |___|
 * 
 * Project: Stormiix\Converter-API
 * Author: Anas Mazouni - Stormix
 * Website: Stormix.co
 * License: MIT - LICENSE.md
 * -----
 * Copyright (c) 2017 Stormix.
 */

require 'vendor/autoload.php';


use Monolog\Logger;
use Monolog\Handler\StreamHandler;

# Load ENV variables
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

# Create & set this month's log file - 2017-12_V1.log
$stream = new StreamHandler('logs/'.date('Y').'-'.date('m').'_'.$_ENV['VERSION'].'.log', Logger::DEBUG);

// Create a logger for the debugging-related stuff
$logger = new Logger('debug');
$logger->pushHandler($stream);

// Sentry Integration - Error reporting.
$client = new Raven_Client('https://9d23a62e33d241fab771a4588556542e:7fa1583b5ecf4671b25911a8f5301d46@sentry.io/260530');
$client->install();

// Whoops error handling
$whoops = new Whoops\Run();
// Set Whoops as the default error and exception handler used by PHP:
$whoops->register();
$whoops->pushHandler(new Whoops\Handler\JsonResponseHandler());


// Check if demo mode
$devMode = $_ENV['ENV'] === 'DEV'? true: false;

// Need this to change the file serving header !
$usingNginx = $_ENV['STACK'] === 'LEMP'? true: false;

if($devMode){
    // Dev mode only 
    error_reporting(E_ALL);
}

/*
 *  START REAL CONFIGURATION HERE
 *      EDIT ONLY IF IT'S
 *          NECESSARY
 */

$config = [
            "api" => [
                "keyLength" => 32
            ]
        ];
