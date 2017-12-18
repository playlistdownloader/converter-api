<?php
namespace Tools;

class MP3
{
    public $file;
    public $title;
    public $artist;
    public $album;
    public $year;
    public $genre;
    
	function __construct($file) {
        $getID3 = new \getID3;
        $info = $getID3->analyze($file);
        \getid3_lib::CopyTagsToComments($info);
        $this->file = $file;
        $this->title = !empty($info['comments_html']['title']) ? $info['comments_html']['title'][0] : '';
        $this->artist = !empty($info['comments_html']['artist']) ? $info['comments_html']['artist'][0] : '';
        $this->album = !empty($info['comments_html']['album']) ? $info['comments_html']['album'][0] : '';
        $this->year =!empty($info['comments_html']['year']) ? $info['comments_html']['year'][0] : '';
        $this->genre = !empty($info['comments_html']['genreid']) ? getGenreFromID($info['comments_html']['genreid'][0]) : !empty($info['comments_html']['genre']) ? $info['comments_html']['genre'] : 'Unknown';
        
    }
    static function getGenreFromID($id){
        $genreArray = \getid3_id3v1::ArrayOfGenres();
        return $genreArray[$id];
    }
    static function convertGenreToID($genre){
        $GenreID = \getid3_id3v1::LookupGenreID($genre);
        return $GenreID;
    }

    public function writeTags($tags){
        if($tags == []){
            return False;
        }
        $tagwriter = new \getid3_writetags;
        $tagwriter->filename = $this->file;
        $tagwriter->tagformats = array('id3v2.3');
        $tagwriter->remove_other_tags = true;
        $tagData = array_map(array($this, 'textToArray'), $tags);
        $tagwriter->tag_data = $tagData;
        // write tags
        if ($tagwriter->WriteTags()) {
            self::__construct($this->file);
            return True;
        } else {
            return False;
        }
    }

    public function textToArray($string){
        return array($string);
    }
    
    public function fetchInternetTags(){
        $title = $this->title;
        $artist = $this->artist;
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET','http://ws.audioscrobbler.com/2.0/?method=track.getInfo&api_key='.$_ENV['LAST_FM_API_KEY'].'&artist='.$artist.'&track='.$title.'&format=json');
        $response = json_decode($res->getBody(), True);
        if(!empty($response)){
            $response = $response['track'];
            $tags = [
                'title'                  => !empty($response['name']) ? $response['name'] : $this->title,
                'artist'                 => !empty($response['artist']['name']) ? $response['artist']['name'] : $this->artist,
                'album'                  => !empty($response['album']['title']) ? $response['album']['title'] : $this->album,
                'genre'                  => !empty($response['genre']) ? $response['genre']: $this->genre,
                'year'                   => !empty($response['year']) ? $response['year']: $this->year,
                'comment'                => ''
            ];
            #TODO fetch the image & converter it to binary data + APIC !
            return $tags;
        }else{
            #TODO use another API
            return [];
        }
    }
    public function fixTags(){
        $tags = $this->fetchInternetTags();
        return $this->writeTags($tags);

    }
}
