<?php

include_once('config.php');
include_once('includes/functions.inc.php');
include_once('includes/db.inc.php');

use fkooman\Json\Json;
use fkooman\Json\JsonException;
use \Firebase\JWT\JWT;

header('Content-Type: application/json');

$klein = new \Klein\Klein();
$klein->respond('GET', '/', function () {
	$res = array(
	            "Name" => "Playlist-Converter API",
	            "Version" => "V2",
	            "Author" => "Stormix - Anas Mazouni",
	            "App Version" => "2.0.1"
	        );
    return Json::encode($res) . PHP_EOL;
});
$klein->with("/v2", "controllers/ApiControllerV2.php");
$klein->with("/download", "controllers/DownloadController.php");
$klein->dispatch();
?>
