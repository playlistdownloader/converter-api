<?php

require_once('includes/Validator.php');
require_once('includes/Hash.php');
require_once('includes/API.php');
require_once('includes/MP3.php');

use fkooman\Json\Json;
use fkooman\Json\JsonException;
use Tools\API;
use Tools\MP3;

$this->respond('GET','/[:id]', function ($request, $response, $service) {
    return Json::encode(generate_response([], "fail", "005  ", "A format must be specified.")) . PHP_EOL;
});
$this->respond('GET', '/[:id]/formats', function ($request, $response, $service) {
    #Check if ID exists
    $id = $request->id;
    $downloadInfo = getDownloadInfo($id);
    #Check if playlist
    if(substr($id, 0, 9) === "playlist_" or !$downloadInfo){
        //$id = substr($id,9);
        global $logger;
        $logger->info("An unknown download was requested.",[
            "Request Details"=>[
               "id"=> $id
            ]
        ]);
        return Json::encode(generate_response([], "fail", "003", "Unknown ID or not yet supported!")) . PHP_EOL;
    }else{
        #It's not a playlist
        $downloadInfoData = $downloadInfo['data'];
        //$rawFormats = shell_exec('youtube-dl -F '.$downloadInfoData['webpage_url']);
        //$formatList = array_values(array_filter(preg_split('/$\R?^/m', explode('note',$rawFormats)[1])));
        $formatList = $downloadInfoData['formats'];
        $response = [];
        foreach($formatList as $format){
            $parts = preg_split('/\s+/', $format['format']);
            $formatID = $format['format_id'];
            $formatExt = $format['ext'];
            $formatDesc = join(' ',array_slice($parts,2));
            $response[] = [
                "format_id" => $formatID,
                "extention" => $formatExt,
                "description" => $formatDesc,
                "download_link" => $_ENV['APP_URL'].$_ENV['VERSION']."/download/".$id."/".$formatID
            ];
        }
        $response[] = [
            "format_id" => "999",
            "extention" => "mp3",
            "description" => "MP3 audio",
            "download_link" => $_ENV['APP_URL'].$_ENV['VERSION']."/download/".$id."/999"
        ];
        return Json::encode($response);
    }
});

$this->respond('GET', '/[:id]/[i:format_id]', function ($request, $response, $service) {
    global $usingNginx;
    #Check if ID exists
    $id = $request->id;
    $downloadInfo = getDownloadInfo($id);
    #Check if playlist
    if(substr($id, 0, 9) === "playlist_" || !$downloadInfo){
        //$id = substr($id,9);
        global $logger;
        $logger->info("An unknown download was requested.",[
            "Request Details"=>[
               "id"=> $id
            ]
        ]);
        return Json::encode(generate_response([], "fail", "003", "Unknown ID or not yet supported!")) . PHP_EOL;
    }else{
        #It's not a playlist
        $downloadInfoData = $downloadInfo['data'];
        // For faster treatement, let's fetch the formats from the database:
        if($request->format_id != "999"){
            foreach($downloadInfoData['formats'] as $format){
                $formatID = $format['format_id'];
                $formatExt = $format['ext'];
                $formats[$formatID] = $formatExt;
            }
        }else{
            $formats['999'] = "mp3";
        }
        
        if(array_key_exists($request->format_id,$formats) || $request->format_id == "999"){
            $ext = $formats[$request->format_id];
            $audio = ($request->format_id == "999" ? true : false);
            $download_link = downloadFile($downloadInfoData['webpage_url'],$request->format_id,$ext,$audio);
            $filename = $downloadInfoData['title'];
            $size = filesize($_SERVER["DOCUMENT_ROOT"].$download_link);
            // Count finished downloads ;)
            incrementFinished();
            decrementUnfinished();
            updateUsage($size);
            // If MP3 - Fix ID3 tags before downloading 
            //if($request->format_id == "999"){
            //    $mp3 = new MP3($download_link);
            //    $mp3->fixTags();
            //}
            if($usingNginx){
                header('X-Accel-Redirect: /'.$download_link);
            }else{
                header('X-Sendfile: '.realpath($download_link));
            }
            header('Content-Type: '.mime_content_type($download_link));
            header('Content-length: ' . $size);
            header('Content-Disposition: attachment; filename="'.$filename.".".$ext.'"');
            header('X-Pad: avoid browser bug');
            header('Cache-Control: no-cache');
        }else{
            return Json::encode(generate_response([], "fail", "004", "Unsupported or Unknown format!")) . PHP_EOL;
        }
    }
});
