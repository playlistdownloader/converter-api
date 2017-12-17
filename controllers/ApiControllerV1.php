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
    $body = $request->body();
    $bodyArray = Json::decode($body);
    $APIkey = $bodyArray['key'];
    // Check if the API key is valid!
    $url = $bodyArray['url']; //TODO Get this from POST
    if (!API::validateAPI($APIkey, $pdo)) {
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
                    $hash = \Tools\Hash::encrypt($url);
                    # Check in cache first
                    # Now in database
                    $data = getDBVideoInfo($hash);
                    if ($data) {
                        // Cache the response
                        return $data['response'];
                    } else {
                        // Get response
                        $video = new Video($url);
                        $videoInfo = $video->getVideoInfo();
                        $response = Json::encode(generate_response($videoInfo, "success", null, null, $hash)) . PHP_EOL;
                        try {
                            // Add response to database
                            $stmt = $pdo->prepare("INSERT INTO downloads(response_id, response, timestamp)
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
                    $playlistHash = \Tools\Hash::encrypt($url);
                    $playlistDBData = fetchPlaylistDownloads($playlistHash);
                    if (!$playlistDBData) {
                        $urls = getPlaylistVideos($url);
                        $hashes = array_map(array('\Tools\Hash','encrypt'), $urls);
                        storePlaylistDownloads($playlistHash, $hashes);
                    } else {
                        $hashes = unserialize($playlistDBData['downloads']);
                    }
                    $responses = [];
                    foreach ($hashes as $hash) {
                        # Check in cache first
                        # Now in database
                        $data = getDBVideoInfo($hash);
                        if ($data){
                            //TODO Cache the response
                            $responses[] = json_decode($data['response'], true)['data']; // Only take the data part of the response cuz we're in a playlist
                        }else{
                            $vurl = \Tools\Hash::decrypt($hash);
                            // Get response
                            $video = new Video($vurl);
                            $videoInfo= $video->getVideoInfo();
                            $response = Json::encode(generate_response($videoInfo, "success", null, null, $hash)) . PHP_EOL;
                            try {
                                // Add response to database
                                $stmt = $pdo->prepare("INSERT INTO downloads(response_id, response, timestamp)
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
                            $responses[] = $videoInfo;// Only take the $videoInfo cuz we're in a playlist
                        }
                    }
                    return Json::encode(generate_response($responses, "success", null, null, "playlist_".$playlistHash)) . PHP_EOL;
                }
            } elseif (Validator::isValidDeezer($url)) {
                $config = array(
                    "app_id" => $_ENV['DEEZER_APP_KEY'],
                    "app_secret" =>$_ENV['DEEZER_APP_SECRET'],
                    "my_url" =>$_ENV['DOMAIN']
                );
                include_once('includes/DeezerAPI.inc.php');
                $dzHash = \Tools\Hash::encrypt($url);
                $playlistDBData = fetchPlaylistDownloads($dzHash);
                if (!$playlistDBData) {
                    $dz  = new DeezerAPI($config);
                    $response = $dz->getPlaylist(parseDeezerID($url, Validator::$deezer_playlist_reg));
                    $playlist = $response->tracks;
                    $titles = [];
                    foreach ($playlist->data as $track) {
                        $titles[] = $track->title ." - ".$track->artist->name;
                    }
                    $urls = searchVideo($titles);
                    $hashes = array_map(array('\Tools\Hash','encrypt'), $urls);
                    storePlaylistDownloads($playlistHash, $videoIDS);
                } else {
                    $hashes = unserialize($playlistDBData['downloads']);
                }
                $responses = [];
                foreach ($hashes as $hash) {
                    $url = \Tools\Hash::decrypt($hash);
                    # Check in cache first
                    # Now in database
                    $data = getDBVideoInfo($hash);
                    if ($data) {
                        //TODO Cache the response
                        $responses[] = json_decode($data['response'], true)['data'];
                    } else {
                        // Get response
                        $video = new Video($url);
                        $videoInfo= $video->getVideoInfo();
                        $response = Json::encode(generate_response($videoInfo, "success", null, null, $hash)) . PHP_EOL;
                        try {
                            // Add response to database
                            $stmt = $pdo->prepare("INSERT INTO downloads(response_id, response, timestamp)
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
                return Json::encode(generate_response($responses, "success", null, null, "playlist_".$dzHash)) . PHP_EOL;
            } elseif (Validator::isValidSoundcloud($url)) {
                // It's a souncloud song
                if (Validator::isSoundcloudSet($url)) {
                    $setHash = \Tools\Hash::encrypt($url);
                    $playlistDBData = fetchPlaylistDownloads($setHash);
                    if (!$playlistDBData) {
                        // We'll reuse the video class getInfo function for now
                        $info = new Video($url);
                        $setInfo= $info->getYDL();
                        $entries = $setInfo['entries'];
                        $responses= [];
                        $hashes = [];
                        foreach ($entries as $song) {
                            # Get the special Hash
                            $hash = \Tools\Hash::encrypt($song['webpage_url']);
                            $hashes[] = $hash;
                            $song['download_id'] = $hash;
                            $url = $song['webpage_url'];
                            #TODO Check in cache first
                            # Now in database
                            $data = getDBVideoInfo($hash);
                            if ($data) {
                                //TODO Cache the response
                                $responses[] = json_decode($data['response'], true)['data'];
                            }else{
                                // Get response
                                $response = Json::encode(generate_response($song, "success", null, null, $hash)) . PHP_EOL;
                                try {
                                    // Add response to database
                                    $stmt = $pdo->prepare("INSERT INTO downloads(response_id, response, timestamp)
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
                                $responses[] = $song;
                            }
                        }
                        storePlaylistDownloads($setHash, $hashes);
                        return Json::encode(generate_response($responses, "success", null, null, "playlist_".$setHash)) . PHP_EOL;
                    } else {
                        $hashes = unserialize($playlistDBData['downloads']);
                        $response = [];
                        foreach ($hashes as $hash) {
                            # Now in database
                            $data = getDBVideoInfo($hash);
                            if ($data) {
                                //TODO Cache the response
                                $responses[] = json_decode($data['response'], true)['data'];
                            } else {
                                $url = \Tools\Hash::decrypt($hash);
                                $info = new Video($url);
                                $song = $info->getYDL();
                                $song['download_id'] = $hash;
                                // Get response
                                $response = Json::encode(generate_response($song, "success", null, null, $hash)) . PHP_EOL;
                                try {
                                    // Add response to database
                                    $stmt = $pdo->prepare("INSERT INTO downloads(response_id, response, timestamp)
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
                                $responses[] = $song;
                            }
                        }
                        return Json::encode(generate_response($responses, "success", null, null, "playlist_".$setHash)) . PHP_EOL;
                    }
                } else {
                    # Get the special Hash
                    $hash = \Tools\Hash::encrypt($url);
                    # Check in cache first
                    # Now in database
                    if (getDBVideoInfo($hash)) {
                        $data = getDBVideoInfo($hash);
                        // Cache the response
                        return $data['response'];
                    } else {
                        // Get response
                        $video = new Video($url);
                        $song = $video->getYDL();
                        $song['download_id'] = $hash;
                        $response = Json::encode(generate_response($song, "success", null, null, $hash)) . PHP_EOL;
                        try {
                            // Add response to database
                            $stmt = $pdo->prepare("INSERT INTO downloads(response_id, response, timestamp)
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
                # Check for youtube-dl support !
                # Get the special Hash
                $hash = \Tools\Hash::encrypt($url);
                $data = getDBVideoInfo($hash);
                if ($data) {
                    // Cache the response
                    return $data['response'];
                } else {
                    $response = Validator::isSupportedByYTDL($url);
                    if ($response!== false) {
                        #TODO Cache & Cleanup the response
                        $res = Json::encode(generate_response(json_decode($response, true), "success", null, null, $hash)) . PHP_EOL;
                        try {
                            // Add response to database
                            $stmt = $pdo->prepare("INSERT INTO downloads(response_id, response, timestamp)
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
                        }
                        return $res;
                    } else {
                        return Json::encode(generate_response([], "fail", "002", $response)) . PHP_EOL;
                    }
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
});

$this->respond('GET', '/supported', function ($request, $response) {
    $supported = file_get_contents("helpers/supported.json");
    return $supported;
});

$this->with("/download", "controllers/DownloadController.php");
