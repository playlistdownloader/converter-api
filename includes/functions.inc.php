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
?>
