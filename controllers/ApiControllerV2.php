<?php

require('includes/Video.php');
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
    //$url = "https://www.youtube.com/watch?v=qfDeJr2NChM"; //TODO Get this from POST
    //$url = "https://www.youtube.com/playlist?list=PLaEWv3z-sYnPAn9CivcMF5wLkSRWmqJon";
    $url = "http://www.deezer.com/us/playlist/128972121"; //TODO Get this from POST
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
                }elseif(Validator::isYoutubePlaylist($url)){
                    $videoIDS = getPlaylistVideos($url);
                    $responses = [];
                    foreach($videoIDS as $videoID){
                        $url = "https://www.youtube.com/watch?v=".$videoID;
                        # Get the special Hash
                        $hash = \Tools\Hash::encrypt($videoID);
                        # Check in cache first
                        # Now in database
                        if(getDBVideoInfo($hash)){
                            $data = getDBVideoInfo($hash);
                            //TODO Cache the response
                            $responses[] = json_decode($data['response'],true)['data'];
                        }else{
                            // Get response
                            $video = new Video($url);
                            $videoInfo= $video->getVideoInfo();
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
                            $responses[] = $videoInfo;
                        }
                    }
                    return Json::encode(generate_response($responses,"success")) . PHP_EOL;
                }
            }elseif(Validator::isValidDeezer($url)){
                $config = array(
                    "app_id" => $_ENV['DEEZER_APP_KEY'],
                    "app_secret" =>$_ENV['DEEZER_APP_SECRET'],
                    "my_url" =>$_ENV['DOMAIN']
                );
                include_once('includes/DeezerAPI.inc.php');
                $dz  = new DeezerAPI($config);
                $response = $dz->getPlaylist(parseDeezerID($url));
                $playlist = $response->tracks;
                $titles = [];
                foreach ($playlist->data as $track) {
                    $titles[] = $track->title ." - ".$track->artist->name;
                }
                $videoIDS = searchVideo($titles);
                $responses = [];
                foreach($videoIDS as $videoID){
                    $url = "https://www.youtube.com/watch?v=".$videoID;
                    # Get the special Hash
                    $hash = \Tools\Hash::encrypt($videoID);
                    # Check in cache first
                    # Now in database
                    if(getDBVideoInfo($hash)){
                        $data = getDBVideoInfo($hash);
                        //TODO Cache the response
                        $responses[] = json_decode($data['response'],true)['data'];
                    }else{
                        // Get response
                        $video = new Video($url);
                        $videoInfo= $video->getVideoInfo();
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
                        $responses[] = $videoInfo;
                    }
                }
                return Json::encode(generate_response($responses,"success")) . PHP_EOL;
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
        /* ."/n Something went wrong, please contact the administrator."*/
        return Json::encode(generate_response([],"error","000",$e)) . PHP_EOL;
    }

});
?>
