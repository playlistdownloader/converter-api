<?php
/**
  * Link Validation class, Validator.php
  * Check fields and data validity
  * @category classes
  *
  * @author Stormix
  * @copyright Stormix
  * @license None
  * @version 1.0
  *
  */
namespace Tools;
class Validator
{
    static protected $youtube_vid_reg = "~(?:http|https|)(?::\/\/|)(?:www.|)(?:youtu\.be\/|youtube\.com(?:\/embed\/|\/v\/|\/watch\?v=|\/ytscreeningroom\?v=|\/feeds\/api\/videos\/|\/user\S*[^\w\-\s]|\S*[^\w\-\s]))([\w\-]{11,})[a-z0-9;:@#?&%=+\/\$_.-]*~i";
    static protected $youtube_playlist_reg = '~(?:http|https|)(?::\/\/|)(?:www.|)(?:youtu\.be\/|youtube\.com(?:\/embed\/|\/v\/|\/watch\?v=|\/ytscreeningroom\?v=|\/feeds\/api\/videos\/|\/user\S*[^\w\-\s]|\S*[^\w\-\s]))([\w\-]{12,})[a-z0-9;:@#?&%=+\/\$_.-]*~i';
    static  $deezer_playlist_reg = '/https?:\/\/(?:www\.)?deezer\.com(?:\/[A-Za-z][A-Za-z])?\/playlist\/([0-9]+)/';
    static function isValidLink($url){
        if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
            return False;
        }else{
            return True;
        }
    }
    static function isValidYoutube($url){
        if(preg_match(self::$youtube_vid_reg, $url, $match) || preg_match(self::$youtube_playlist_reg, $url, $match) ){
            return True;
        }else{
            return False;
        }
    }
    static function isValidDeezer($url){
        if(preg_match(self::$deezer_playlist_reg, $url, $match)){
            return True;
        }else{
            return False;
        }
    }
    static function isYoutubeVideo($url){
        if(self::isValidYoutube($url) && !strpos($url, 'list=')){
            return True;
        }else{
            return False;
        }
    }
    static function isYoutubePlaylist($url){
        if(self::isValidYoutube($url) && strpos($url, 'list=')){
            return True;
        }else{
            return False;
        }
    }
	static function isValidSoundcloud($url){
	    $pattern = "/^https?:\/\/(soundcloud\.com|snd\.sc)\/(.*)$/";
	    if(preg_match($pattern, $url, $matches)){
	        return True;
	    }else{
	        return False;
	    }
	}
	static function isSoundcloudSet($url){
	    if (self::isValidSoundcloud($url) && strpos($url, 'sets') !== false) {
	        return True;
	    }else{
	        return False;
	    }
	}
    static function isSupportedByYTDL($url){
        // Test the given $url
        if(self::isValidLink($url)){
            $response = shell_exec('youtube-dl -J '.$url);
            if($response != "" && strpos($response, "ERROR") === false){
                return $response;
            }else{
                return False;
            }
        }else{
            return False;
        }
    }
}
