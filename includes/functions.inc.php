<?php

function generate_response($payload,$status,$code = NULL,$message = Null){
	/*
	 * Jsend API Standards :
	 *  {
	 *      status : "fail",
	 *      data : {
	 *          code : 123,
	 *          message : "Something is required!"
	 *      }
	 *  }
	 *  {
	 *      status : "error",
	 *      code : 123,
	 *      message : "An error occured!"
	 *
	 *  }
	 */
	$code = $code ? $code : 000;
	$message = $message ? $message : "No message provided";
	switch($status){
		case "success":
			return [
		        "status" => "success",
		        "data" => $payload
		    ];
			break;
		case "fail":
			return [
		        "status" => "fail",
		        "data" => [
					"code" => $code,
					"message" => $message
				]
		    ];
			break;
		case "error":
			return [
				"status" => "error",
				"code" => $code,
				"message" => $message
			];
			break;
	}
}
/**
 * get youtube video ID from URL
 *
 * @param string $url
 * @return string Youtube video id or FALSE if none found.
 */
function getYoutubeVideoID($url) {
    $pattern =
        '%^# Match any youtube URL
        (?:https?://)?  # Optional scheme. Either http or https
        (?:www\.)?      # Optional www subdomain
        (?:             # Group host alternatives
          youtu\.be/    # Either youtu.be,
        | youtube\.com  # or youtube.com
          (?:           # Group path alternatives
            /embed/     # Either /embed/
          | /v/         # or /v/
          | /watch\?v=  # or /watch\?v=
          )             # End path alternatives.
        )               # End host alternatives.
        ([\w-]{10,12})  # Allow 10-12 for 11 char youtube id.
        $%x'
        ;
    $result = preg_match($pattern, $url, $matches);
    if ($result) {
        return $matches[1];
    }
    return false;
}
function getPlaylistID($url){
    parse_str( parse_url( $url, PHP_URL_QUERY ), $params );
    return $params['list'];
}
function getDBVideoInfo($hash){
    global $pdo;
    $sql = 'SELECT * FROM videos WHERE response_id = ? LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if(!$row){
        return False;
    }else{
        return $row;
    }
}
function getDBPlaylistInfo($id){
    global $pdo;
    $sql = 'SELECT * FROM playlists WHERE playlist_id = ? LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if(!$row){
        return False;
    }else{
        return $row;
    }
}
function getPlaylistInfo($url,$max=0){
    global $_ENV;
    $API_KEY = $_ENV['GOOGLE_API_KEY'];
    $client = new \Google_Client();
    $client->setDeveloperKey($API_KEY);
    $youtube = new \Google_Service_YouTube($client);
    $playlistID = getPlaylistID($url);
    $nextPageToken = '';
    $yt_response = array();
    if($max == 0){
        do {
            $playlistItemsResponse = $youtube->playlistItems->listPlaylistItems('snippet,id', array(
               'playlistId' => $playlistID,
               'maxResults' => 50,
               'pageToken' => $nextPageToken));
               foreach ($playlistItemsResponse['items'] as $playlistItem) {
                  $yt_response[]= $playlistItem['snippet']['resourceId']['videoId'];
               }
               $nextPageToken = $playlistItemsResponse['nextPageToken'];
       } while ($nextPageToken <> '');
   }else{
       $i = 0;
       do {
           $playlistItemsResponse = $youtube->playlistItems->listPlaylistItems('snippet,id', array(
              'playlistId' => $playlistID,
              'maxResults' => 50,
              'pageToken' => $nextPageToken));
              foreach ($playlistItemsResponse['items'] as $playlistItem) {
                 $yt_response[]= $playlistItem['snippet']['resourceId']['videoId'];
              }
              $nextPageToken = $playlistItemsResponse['nextPageToken'];
              $i++;
      } while ($nextPageToken <> '' && $i <= $max);
   }
   return $yt_response;
}
function getPlaylistVideos($url){
    //global $mem;
    global $pdo;
    global $_ENV;
    global $config;

	# Get the special Hash
	$playlistID = \Tools\Hash::encrypt(getPlaylistID($url));
	# Check in cache first
    //$cached = $mem->get($playlistID);
    //if($cached){
    //    return $cached;
    //} else {
	# Now in database
        if(getDBPlaylistInfo($playlistID)){
            $data = getDBPlaylistInfo($playlistID);
            //$mem->set($playlistID, unserialize($data['videos']),$_ENV['CACHE_TIME']*60) or die("Couldn't save anything to memcached...");
            return unserialize($data['videos']);
        }else{
            // Get response
            $PlaylistVideos = getPlaylistInfo($url);
            $videos = [];
            if(count($PlaylistVideos > 0)){
                foreach($PlaylistVideos as $videoID){
                    $videos[] = $videoID;
                }
                // Add playlist videos to database
                $stmt = $pdo->prepare("INSERT INTO playlists(playlist_id, videos, timestamp)
                    VALUES(:playlist_id, :videos, :time)");
                $stmt->execute(array(
                    "playlist_id" => $playlistID,
                    "videos" => serialize($videos),
                    "time" => time()
                ));
                    //$mem->set($playlistID, $videos,$_ENV['CACHE_TIME']*60) or die("Couldn't save anything to memcached...");
                    return $videos;
                }else{
                    //$mem->set($playlistID, $PlaylistVideos,$_ENV['CACHE_TIME']*60) or die("Couldn't save anything to memcached...");
                    return $PlaylistVideos;
                }

        }
    //}
}
function searchVideo($title,$max=0){
    global $_ENV;
    $API_KEY = $_ENV['GOOGLE_API_KEY'];
    $client = new \Google_Client();
    $client->setDeveloperKey($API_KEY);
    $youtube = new \Google_Service_YouTube($client);
    $videos = '';
    if(is_array($title) && count($title) > 1){
        if($max != 0 && $max < count($title)){
            $title = array_slice($title, 0, $max);
        }
        $videoIds = [];
        foreach($title as $t){
            // query title.
            $searchResponse = $youtube->search->listSearch('id', array(
                'type' => 'video',
                'q' => $t,
                'maxResults' => "1",
            ));
            if(!is_null($searchResponse['items']) && array_key_exists(0,$searchResponse['items'])){
                $searchResult = $searchResponse['items'][0];
                $videoIds[] = encrypt($searchResult['id']['videoId'],$_ENV['ENCRYPT_KEY']);
            }
        }
        return $videoIds;
    }else{
        if(is_array($title)){
            $title = $title[0];
        }
        // query title.
        $searchResponse = $youtube->search->listSearch('id', array(
            'type' => 'video',
            'q' => $title,
            'maxResults' => "1",
        ));
        $searchResult = $searchResponse['items'][0];
        $videoId = encrypt($searchResult['id']['videoId'],$_ENV['ENCRYPT_KEY']);
        return [$videoId];
    }
}
function parseDeezerID($url){
    global $deezer_playlist_reg;
    preg_match($deezer_playlist_reg, $url, $match);
    return $match[2];
}
?>
