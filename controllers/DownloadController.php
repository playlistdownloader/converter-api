<?php

require_once('includes/Validator.php');
require_once('includes/Hash.php');
require_once('includes/API.php');

use fkooman\Json\Json;
use fkooman\Json\JsonException;
use Tools\API;

$this->respond('GET', '/', function ($request, $response) {
    return "wassup";
});
