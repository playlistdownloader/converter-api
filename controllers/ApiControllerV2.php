<?php

require('includes/Download.php');
require('includes/Validator.php');
require('includes/Hash.php');

use fkooman\Json\Json;
use fkooman\Json\JsonException;
use Converter\Video;
use Tools\Validator;

$this->respond('GET', '/', function ($request, $response) {
    return http_response_code(200);
});
$this->respond('GET', '/auth', function ($request, $response) {
	return http_response_code(200);
});
$this->respond('GET', '/fetch', function ($request, $response) {
    global $pdo;
    # Fetches the download infos
    $url = "https://www.youtube.com/watch?v=qfDeJr2NChM"; //TODO Get this from POST
    try {
        #Validate Link
        if(!Validator::isValidLink($url)){
            return Json::encode(generate_response([],"fail","001","Invalid Link")) . PHP_EOL;
        }else{
            # Check if youtube video
            if(Validator::isValidYoutube($url)){
                if(Validator::isYoutubeVideo($url)){
                    # Get the special Hash
                    $hash = \Tools\Hash::encrypt(getYoutubeVideoID($url));
                    # Check in cache first
                    # Now in database
                    if(getDBVideoInfo($hash)){
                        $data = getDBVideoInfo($hash);
                        // Cache the response
                        return $data['response'];
                    }else{
                        // Get response
                        $video = new Video($url);
                        $videoInfo = $video->getVideoInfo();
                        $response = Json::encode(generate_response($videoInfo,"success")) . PHP_EOL;
                        try {
                            // Add response to database
                            $stmt = $pdo->prepare("INSERT INTO videos(response_id, response, timestamp)
                                VALUES(:hash, :response, :time)");
                            $stmt->execute(array(
                                "hash" => $hash,
                                "response" => $response,
                                "time" => time()
                            ));
                        }catch (PDOException $ex) {
                            // Re-throw exception if it wasn't a constraint violation.
                            if ($ex->getCode() != 23000)
                                return Json::encode(generate_response([],"error","PDO","Something went wrong, please contact the administrator.")) . PHP_EOL;
                        }
                        //TODO CACHE THE RESPONSE
                        return $response;
                    }
                }
            }elseif(Validator::isValidDeezer($url)){
                echo "hey";
            }elseif(Validator::isValidSoundcloud($url)){
                echo "hey";
            }else{
                return Json::encode(generate_response([],"fail","002","Unsupported Service")) . PHP_EOL;
            }
        }
    } catch (Exception $e) {
        // Log the exception
        // Return a response to API users
        // TODO Remove the $e from the response !!
        return Json::encode(generate_response([],"error","000",$e."/n Something went wrong, please contact the administrator.")) . PHP_EOL;
    }

});
?>
