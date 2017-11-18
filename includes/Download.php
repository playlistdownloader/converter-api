<?php

namespace Converter;

class Video
{
    // property declaration
	public $id;
	public $format;
	public $type;
	public $source;
	public $link;
	public $title;
	public $description;
	public $filename;
	public $artist;
	public $track;
	public $uploader;
	public $resolution;
	public $download_link;
	public $thumbnail;
	public $formats;

	function __construct($url) {
        $this->link = $url;
    }

	public function getYDL(){
	    $content = shell_exec('youtube-dl -J '.$this->link);
	    return json_decode($content,true);
	}
	public function setVideoInfo(){
		global $_ENV;
		$videoInfo = $this->getYDL();
		$this->id = \Tools\Hash::encrypt($videoInfo['display_id']);
		$this->source = $videoInfo['extractor_key'];
		$this->link = $videoInfo['webpage_url'];
		$this->title = $videoInfo['title'];
		$this->description = $videoInfo['description'];
		$this->filename = $this->getFilename($this->title);
		$this->uploader = $videoInfo["uploader"];
		$artistInfo = explode ("-", $videoInfo['title']);
		if(count($artistInfo) > 1 ){
			$this->artist = trim($artistInfo[0]);
			$this->track = trim($artistInfo[1]);
		}else{
			$this->artist = null;
			$this->track = null;
		}
		$this->download_link = $_ENV['APP_URL']."download/".$this->id;
		$this->thumbnail = is_array($videoInfo['thumbnail']) ? $videoInfo['thumbnail'][0] : $videoInfo['thumbnail'];
		$this->formats = $videoInfo['formats'];
	}
	public function getVideoInfo(){
		if(!$this->id){
			$this->setVideoInfo();
		}
		return [
		            "id" => $this->id,
	                "source" => $this->source,
	                "link" => $this->link,
	                "title" => $this->title,
	                "description" => $this->description,
	                "filename" => $this->filename,
	                "artist" => $this->artist,
	                "track" => $this->track,
	                "uploader" => $this->uploader,
	                "download_link" => $this->download_link,
		            "thumbnail" => $this->thumbnail,
	                "formats" => $this->formats
		        ];
	}
	/**
	 * Returns a safe filename, for a given platform (OS), by replacing all
	 * dangerous characters with an underscore.
	 *
	 * @param string $dangerous_filename The source filename to be "sanitized"
	 * @param string $platform The target OS
	 *
	 * @return Boolean string A safe version of the input filename
	 */
	public static function getFilename($string) {
	    $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "=", "+", "[", "{", "]",
	                   "}", "\\", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
	                   "â€”", "â€“", ",", "<", ">", "/", "?");
	    $clean = trim(str_replace($strip, "", strip_tags($string)));
	    return $clean;
	}
}
?>
