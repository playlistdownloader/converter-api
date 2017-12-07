<?php

require('includes/Video.php');
require('includes/Validator.php');
require('includes/Hash.php');
require('includes/API.php');

use fkooman\Json\Json;
use fkooman\Json\JsonException;
use Converter\Video;
use Tools\Validator;
use Tools\API;

$this->respond('GET', '/', function ($request, $response) {
    return http_response_code(200);
});
$this->respond('POST','/fetch',function ($request, $response) {
        global $pdo;
        # Fetches the download infos
        //$url = "https://www.youtube.com/watch?v=qfDeJr2NChM";
        //$url = "https://www.youtube.com/playlist?list=PLaEWv3z-sYnPAn9CivcMF5wLkSRWmqJon";
        $body = $request->body();
        $bodyArray = Json::decode($body);
        $APIkey = $bodyArray['key'];
        // Check if the API key is valid!
        $url = $bodyArray['url']; //TODO Get this from POST
        if (!API::validateAPI($APIkey,$pdo)) {
            return Json::encode(generate_response([], "fail", "xxx", "Invalid API key !")) . PHP_EOL;
            exit();
        }
        try {
            #Validate Link
            if (!Validator::isValidLink($url)) {
                return Json::encode(generate_response([], "fail", "001", "Invalid Link")) . PHP_EOL;
            } else {
                # Check if youtube video
                if (Validator::isValidYoutube($url)) {
                    if (Validator::isYoutubeVideo($url)) {
                        # Get the special Hash
                        $hash = \Tools\Hash::encrypt(getYoutubeVideoID($url));
                        # Check in cache first
                        # Now in database
                        if (getDBVideoInfo($hash)) {
                            $data = getDBVideoInfo($hash);
                            // Cache the response
                            return $data['response'];
                        } else {
                            // Get response
                            $video = new Video($url);
                            $videoInfo = $video->getVideoInfo();
                            $response = Json::encode(generate_response($videoInfo, "success")) . PHP_EOL;
                            try {
                                // Add response to database
                                $stmt = $pdo->prepare("INSERT INTO videos(response_id, response, timestamp)
                                VALUES(:hash, :response, :time)");
                                $stmt->execute(array(
                                "hash" => $hash,
                                "response" => $response,
                                "time" => time()
                            ));
                            } catch (PDOException $ex) {
                                // Re-throw exception if it wasn't a constraint violation.
                                if ($ex->getCode() != 23000) {
                                    return Json::encode(generate_response([], "error", "PDO", "Something went wrong, please contact the administrator.")) . PHP_EOL;
                                }
                            }
                            //TODO CACHE THE RESPONSE
                            return $response;
                        }
                    } elseif (Validator::isYoutubePlaylist($url)) {
                        $videoIDS = getPlaylistVideos($url);
                        $responses = [];
                        foreach ($videoIDS as $videoID) {
                            $url = "https://www.youtube.com/watch?v=".$videoID;
                            # Get the special Hash
                            $hash = \Tools\Hash::encrypt($videoID);
                            # Check in cache first
                            # Now in database
                            if (getDBVideoInfo($hash)) {
                                $data = getDBVideoInfo($hash);
                                //TODO Cache the response
                                $responses[] = json_decode($data['response'], true)['data'];
                            } else {
                                // Get response
                                $video = new Video($url);
                                $videoInfo= $video->getVideoInfo();
                                $response = Json::encode(generate_response($videoInfo, "success")) . PHP_EOL;
                                try {
                                    // Add response to database
                                    $stmt = $pdo->prepare("INSERT INTO videos(response_id, response, timestamp)
                                    VALUES(:hash, :response, :time)");
                                    $stmt->execute(array(
                                    "hash" => $hash,
                                    "response" => $response,
                                    "time" => time()
                                ));
                                } catch (PDOException $ex) {
                                    // Re-throw exception if it wasn't a constraint violation.
                                    if ($ex->getCode() != 23000) {
                                        return Json::encode(generate_response([], "error", "PDO", "Something went wrong, please contact the administrator.")) . PHP_EOL;
                                    }
                                }
                                //TODO CACHE THE RESPONSE
                                $responses[] = $videoInfo;
                            }
                        }
                        return Json::encode(generate_response($responses, "success")) . PHP_EOL;
                    }
                } elseif (Validator::isValidDeezer($url)) {
                    $config = array(
                    "app_id" => $_ENV['DEEZER_APP_KEY'],
                    "app_secret" =>$_ENV['DEEZER_APP_SECRET'],
                    "my_url" =>$_ENV['DOMAIN']
                );
                    include_once('includes/DeezerAPI.inc.php');
                    $dz  = new DeezerAPI($config);
                    $response = $dz->getPlaylist(parseDeezerID($url,Validator::$deezer_playlist_reg));
                    $playlist = $response->tracks;
                    $titles = [];
                    foreach ($playlist->data as $track) {
                        $titles[] = $track->title ." - ".$track->artist->name;
                    }
                    $videoIDS = searchVideo($titles);
                    $responses = [];
                    foreach ($videoIDS as $videoID) {
                        $url = "https://www.youtube.com/watch?v=".$videoID;
                        # Get the special Hash
                        $hash = \Tools\Hash::encrypt($videoID);
                        # Check in cache first
                        # Now in database
                        if (getDBVideoInfo($hash)) {
                            $data = getDBVideoInfo($hash);
                            //TODO Cache the response
                            $responses[] = json_decode($data['response'], true)['data'];
                        } else {
                            // Get response
                            $video = new Video($url);
                            $videoInfo= $video->getVideoInfo();
                            $response = Json::encode(generate_response($videoInfo, "success")) . PHP_EOL;
                            try {
                                // Add response to database
                                $stmt = $pdo->prepare("INSERT INTO videos(response_id, response, timestamp)
                                VALUES(:hash, :response, :time)");
                                $stmt->execute(array(
                                    "hash" => $hash,
                                    "response" => $response,
                                    "time" => time()
                                ));
                            } catch (PDOException $ex) {
                                // Re-throw exception if it wasn't a constraint violation.
                                if ($ex->getCode() != 23000) {
                                    return Json::encode(generate_response([], "error", "PDO", "Something went wrong, please contact the administrator.")) . PHP_EOL;
                                }
                            }
                            //TODO CACHE THE RESPONSE
                            $responses[] = $videoInfo;
                        }
                    }
                    return Json::encode(generate_response($responses, "success")) . PHP_EOL;
                } elseif (Validator::isValidSoundcloud($url)) {
                    // It's a souncloud song
                    if(Validator::isSoundcloudSet($url)){
                        // We'll reuse the video class getInfo function for now
                        $info = new Video($url);
                        $setInfo= $info->getYDL();
                        $entries = $setInfo->entries;
                        $responses= [];
                        foreach($entries as $song){
                            # Get the special Hash
                            $hash = \Tools\Hash::encrypt($song['id']);
                            $url = $song['url'];
                            #TODO Check in cache first
                            # Now in database
                            if (getDBVideoInfo($hash)) {
                                $data = getDBVideoInfo($hash);
                                //TODO Cache the response
                                $responses[] = json_decode($data['response'], true)['data'];
                            } else {
                                // Get response
                                $video = new Video($url);
                                $videoInfo= $video->getVideoInfo();
                                $response = Json::encode(generate_response($videoInfo, "success")) . PHP_EOL;
                            try {
                                // Add response to database
                                $stmt = $pdo->prepare("INSERT INTO videos(response_id, response, timestamp)
                                VALUES(:hash, :response, :time)");
                                $stmt->execute(array(
                                    "hash" => $hash,
                                    "response" => $response,
                                    "time" => time()
                                ));
                            } catch (PDOException $ex) {
                                // Re-throw exception if it wasn't a constraint violation.
                                if ($ex->getCode() != 23000) {
                                    return Json::encode(generate_response([], "error", "PDO", "Something went wrong, please contact the administrator.")) . PHP_EOL;
                                }
                            }
                                //TODO CACHE THE RESPONSE
                                $responses[] = $videoInfo;
                            }
                        }
                    }else{
                        $linkInfo = new Video($url);
                        $songInfo= $linkInfo->getYDL();
                        # Get the special Hash
                        $hash = \Tools\Hash::encrypt($songInfo['id']);
                        # Check in cache first
                        # Now in database
                        if (getDBVideoInfo($hash)) {
                            $data = getDBVideoInfo($hash);
                            // Cache the response
                            return $data['response'];
                        } else {
                            // Get response
                            $video = new Video($url);
                            $videoInfo = $video->getVideoInfo();
                            $response = Json::encode(generate_response($videoInfo, "success")) . PHP_EOL;
                            try {
                                // Add response to database
                                $stmt = $pdo->prepare("INSERT INTO videos(response_id, response, timestamp)
                                VALUES(:hash, :response, :time)");
                                $stmt->execute(array(
                                "hash" => $hash,
                                "response" => $response,
                                "time" => time()
                            ));
                            } catch (PDOException $ex) {
                                // Re-throw exception if it wasn't a constraint violation.
                                if ($ex->getCode() != 23000) {
                                    return Json::encode(generate_response([], "error", "PDO", "Something went wrong, please contact the administrator.")) . PHP_EOL;
                                }
                            }
                            //TODO CACHE THE RESPONSE
                            return $response;
                        }
                    }
                    #TODO Add more services
                } else {
                    # Check for youtube-dl support ! - CHECK NEXT COMMIT
                    $response = Validator::isSupportedByYTDL($url);
                    if($response!== False){
                        #TODO Cache & Cleanup the response
                        $res = Json::encode(generate_response(json_decode($response,True), "success")) . PHP_EOL
                        try {
                            // Add response to database
                            $stmt = $pdo->prepare("INSERT INTO videos(response_id, response, timestamp)
                            VALUES(:hash, :response, :time)");
                            $stmt->execute(array(
                            "hash" => $hash,
                            "response" => $res,
                            "time" => time()
                        ));
                        } catch (PDOException $ex) {
                            // Re-throw exception if it wasn't a constraint violation.
                            if ($ex->getCode() != 23000) {
                                return Json::encode(generate_response([], "error", "PDO", "Something went wrong, please contact the administrator.")) . PHP_EOL;
                            }
                    }else{
                        return Json::encode(generate_response([], "fail", "002", "Unsupported Service")) . PHP_EOL;
                    }
                }
            }
        } catch (Exception $e) {
            // Log the exception
            // Return a response to API users
            // TODO Remove the $e from the response !!
            /* ."/n Something went wrong, please contact the administrator."*/
            print_r($e);
            return Json::encode(generate_response([], "error", "000", $e)) . PHP_EOL;
        }
    }
);
$this->respond('GET','/supported', function ($request, $response) {
    $supported = file_get_contents("helpers/supported.json");
    return $supported;
});
