<?php

function generate_response($payload,$status,$code = NULL,$message = Null,$id=NULL){

	$code = $code ? $code : 000;
	$message = $message ? $message : "No message provided";
	switch($status){
		case "success":
			return [
		        "status" => "success",
				"download_id" => $id,
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
    /*
     * Because I'm so lazy, I'm add the cache here.
     */
    
    global $pdo;
    global $redis;

    if($_ENV['CACHE'] == "TRUE" && $redis->exists($hash)){
        return [
            'response' => $redis->get($hash)
        ];
    }else{
        $sql = 'SELECT * FROM downloads WHERE response_id = ? LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if(!$row){
            return False;
        }else{
            return $row;
        }
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
                    $videos[] = "https://www.youtube.com/watch?v=".$videoID;
                }
                    return $videos;
            }else{
                //$mem->set($playlistID, $PlaylistVideos,$_ENV['CACHE_TIME']*60) or die("Couldn't save anything to memcached...");
                return "https://www.youtube.com/watch?v=".$PlaylistVideos;
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
                $videoIds[] = "https://www.youtube.com/watch?v=".$searchResult['id']['videoId'];
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
        $videoId = "https://www.youtube.com/watch?v=".$searchResult['id']['videoId'];
        return [$videoId];
    }
}

function parseDeezerID($url,$deezer_playlist_reg){
    preg_match($deezer_playlist_reg, $url, $match);
    return $match[1];
}

function storePlaylistDownloads($id,$downloads){
    global $pdo;
    global $logger;
	$data = serialize($downloads);
	try {
		// Add response to database
		$stmt = $pdo->prepare("INSERT INTO playlists(playlist_id, downloads, timestamp)
		VALUES(:hash, :downloads, :time)");
		$stmt->execute(array(
		"hash" => $id,
		"downloads" => $data,
		"time" => time()
	));
	} catch (PDOException $ex) {
		// Re-throw exception if it wasn't a constraint violation.
		if ($ex->getCode() != 23000) {
            $logger->critical("A PDO Exception was raised inside a function storePlaylistDownloads(id,downloads).",[
                "Error" =>$e->getMessage(),
            ]);
			return \fkooman\Json\Json::encode(generate_response([], "error", "PDO", "Something went wrong, please contact the administrator.")) . PHP_EOL;
		}
	}
}
function fetchPlaylistDownloads($id){
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
function getDownloadInfo($id){
    global $pdo;
    $sql = 'SELECT * FROM downloads WHERE response_id = ? LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if(!$row){
        return False;
    }else{
        incrementUnfinished();
        return json_decode($row['response'],true);
    }
}


function downloadFile($link,$format,$ext,$audio){
    $id = time();
    $downloadFolder = $_ENV['DOWNLOAD_FOLDER'];
    if(!file_exists($downloadFolder)){
        mkdir($downloadFolder);
    }
    $output = $downloadFolder."/".$id."-delete.".$ext;
    if (file_exists($output)) {
		return $output;
	}else {
        if($audio){
            incrementConversions();
            $cmd = 'youtube-dl --add-metadata --extract-audio --audio-format mp3  --output '.$downloadFolder.'/"'.$id.'-delete.%(ext)s" '.$link;
        }else{
            if($format == "9999"){
                incrementConversions();
                $cmd = 'youtube-dl -f "bestvideo+bestaudio[ext=m4a]/bestvideo+bestaudio/best"  --merge-output-format mp4 --output '.$downloadFolder.'/"'.$id.'-delete.%(ext)s" '.$link;
            }else{
                incrementDownloads();
                $cmd = 'youtube-dl -f '.$format.' --output '.$downloadFolder.'/"'.$id.'-delete.%(ext)s" '.$link;
            }}
		$excecute = shell_exec($cmd);
		return $output;
	}
}
function incrementDownloads(){
    global $pdo;
    $sql = "INSERT INTO stats
            (`downloads`, `conversions`, `unfinished`, `finished`, `period`)
          VALUES
            (1, 0, 0, 0, :period)
          ON DUPLICATE KEY
            UPDATE `downloads` = `downloads` + 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':period' => date('Y-m-d')));
}
function incrementUnfinished(){
    global $pdo;
    $sql = "INSERT INTO stats
            (`downloads`, `conversions`, `unfinished`, `finished`, `period`)
            VALUES
              (0, 0, 1, 0, :period)
          ON DUPLICATE KEY
            UPDATE `unfinished` = `unfinished` + 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':period' => date('Y-m-d')));
}
function decrementUnfinished(){
    global $pdo;
    $sql = "INSERT INTO stats
            (`downloads`, `conversions`, `unfinished`, `finished`, `period`)
            VALUES
              (0, 0, 1, 0, :period)
          ON DUPLICATE KEY
            UPDATE `unfinished` = `unfinished` - 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':period' => date('Y-m-d')));
}
function incrementFinished(){
    global $pdo;
    $sql = "INSERT INTO stats
            (`downloads`, `conversions`, `unfinished`, `finished`, `period`)
            VALUES
              (0, 0, 0, 1, :period)
          ON DUPLICATE KEY
            UPDATE `finished` = `finished` + 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':period' => date('Y-m-d')));
}
function incrementConversions(){
    global $pdo;
    $sql = "INSERT INTO stats
            (`downloads`, `conversions`, `unfinished`, `finished`, `period`)
            VALUES
              (0, 1, 0, 0, :period)
            ON DUPLICATE KEY
                UPDATE `conversions` = `conversions` + 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':period' => date('Y-m-d')));
}
function updateUsage($size){
    global $pdo;
    $sql = "UPDATE stats set `usage` = `usage` + :size WHERE `period` = :period";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':size' => $size,':period' => date('Y-m-d')));
}
function incrementYoutubeSource(){
    global $pdo;
    $sql = "INSERT INTO stats_sources
            (`youtube`, `deezer`, `soundcloud`, `period`)
          VALUES
            (1, 0, 0, :period)
          ON DUPLICATE KEY
            UPDATE `youtube` = `youtube` + 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':period' => date('Y-m-d')));
}

function incrementSoundcloudSource(){
    global $pdo;
    $sql = "INSERT INTO stats_sources
            (`youtube`, `deezer`, `soundcloud`, `period`)
          VALUES
            (0, 0, 1, :period)
          ON DUPLICATE KEY
            UPDATE `deezer` = `deezer` + 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':period' => date('Y-m-d')));
}
function incrementDeezerSource(){
    global $pdo;
    $sql = "INSERT INTO stats_sources
            (`youtube`, `deezer`, `soundcloud`, `period`)
          VALUES
            (0, 1, 0, :period)
          ON DUPLICATE KEY
            UPDATE `soundcloud` = `soundcloud` + 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':period' => date('Y-m-d')));
}
function incrementOtherSource(){
    global $pdo;
    $sql = "INSERT INTO stats_sources
            (`other`, `period`)
          VALUES
            (1, :period)
          ON DUPLICATE KEY
            UPDATE `other` = `other` + 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':period' => date('Y-m-d')));
}
function incrementUnsupportedSource(){
    global $pdo;
    $sql = "INSERT INTO stats_sources
            (`unsupported`, `period`)
          VALUES
            (1, :period)
          ON DUPLICATE KEY
            UPDATE `unsupported` = `unsupported` + 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':period' => date('Y-m-d')));
}

?>
