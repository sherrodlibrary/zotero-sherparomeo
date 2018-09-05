<?php

require_once 'vendor/autoload.php';
require_once('SherpaRomeo.php');

function get_zotero_items($zotero_user, $zotero_key, $type, $limit, $iterate ) {
    $url_base = "https://api.zotero.org/$type/$zotero_user";

    $params = array(
        'key' => $zotero_key,
        'itemType' => 'journalArticle',
        'content' => 'json', 
        'limit' => $limit,
        'sort' => 'dateModified'
    );
    
    if (!$iterate) {
        $response = do_request($url_base, $params);
        return get_items_from_response($response);    
    }

    $items = array();
    $page = 1;
    do {
        $params['start'] = ($page*$limit) + 1;
        $response = do_request($url_base, $params);
        $item_list = get_items_from_response($response);
        $items = array_merge($items, iterator_to_array($item_list));

        $page++;
    } while ($item_list->length == $limit);
    
    return $items;
}

function do_request($url_base, $params) {

    $client = new Zend\Http\Client();
    $request_uri = "$url_base/items?" . http_build_query($params);
    $client->setUri($request_uri);
    $response = $client->send();

    //echo $response->getBody();
    if (200 != $response->getStatusCode()){
        $status_code = $response->getStatusCode();
        throw new Exception("Failed to get items ($status_code).");
    }

    return $response; 
}

function get_items_from_response($response) {
    $items = $response->getBody();
    $items_xml = new DOMDocument();
    $items_xml->loadXML($items);
    $item_list = $items_xml->getElementsByTagName('entry');

    return $item_list;
}

if (( isset($argv[1])) && ( isset($argv[2]))){
  $userid = $argv[1];
  $zotero_key = $argv[2];
  $sherpa_romeo_key = $argv[3];
  $collection_type = $argv[4];
  $limit = isset($argv[5]) && !empty($argv[5]) ? $argv[5] : 25;
  $iterate = isset($argv[6]);

}
else if (isset($_GET['userid'])){
    $userid = $_GET['userid'];
    $zotero_key = $_GET['zotero_key'];
    $sherpa_romeo_key = $_GET['sherpa_romeo_key'];
    $collection_type = $_GET['collection_type']; // 'group' or 'user'
    $limit = isset($_GET['limit']) && !empty($_GET['limit']) ? $_GET['limit'] : 25; // limit to most recent, set to 25 by default.
    $iterate = isset($_GET['iterate']);
  }
else {
    echo "Not enough parameters\n";
    usage();
    exit; 
}

$type ='';
if ($collection_type == 'group'){
  $type ='groups';
}
else{
  $type ='users';
}

$item_list = array();
try {
    $item_list = get_zotero_items($userid, $zotero_key, $type, $limit, $iterate );
}
catch (Exception $e) {
    echo $e->getMessage();
    exit;
}

$sr_responses = array();

foreach ($item_list as $item){

  $content = $item->getElementsByTagName('content')->item(0);
  $content_json = json_decode($content->nodeValue);

  $etag = $content->getAttribute('zapi:etag');

  $item_key = $item->getElementsByTagNameNS('http://zotero.org/ns/api', 'key')->item(0)->nodeValue;
  $issn = $content_json->ISSN;
  $jtitle = $content_json->publicationTitle;
  
  //get "extra" content
  $jextra = $content_json->extra;
 
  if ($issn || $jtitle){
    $sr_key = str_replace(" ", "", $issn.$jtitle);
    if (!isset($sr_responses[$sr_key])){
      $issn_hits = 0;
      if ($issn){
        $sr_data = new SherpaRomeo($issn, 'issn', $sherpa_romeo_key);
        if ($sr_data->getNumHits() > 0){
          $issn_hits = $sr_data->getNumHits();
          $sr_responses[$sr_key] = $sr_data;
        }
      }
     else if ($issn_hits == 0){ // put that else
        if ($jtitle){
          $sr_data = new SherpaRomeo($jtitle, 'jtitle', $sherpa_romeo_key);
          $sr_responses[$sr_key] = $sr_data;
        }
      }
    }else{
      $sr_data = $sr_responses[$sr_key];
      echo "found nothing";
    }

    $pubs = array_values($sr_data->getPublishers());
    if (count($pubs) == 1){
      $pub = $pubs[0];
	  
	  //attempt to set "extra" content
	  
	  if (strpos($content_json->extra , 'SherpaRomeo') !== false){
		//if SherpaRomeo tags already exist in "extra" don't do anything.
	  }
	  else{
		// prepend SherpaRomeo tags to existing content.
		$content_json->extra = ' #SherpaRomeo Pre '.$pub->getPreArchiving() . '; #SherpaRomeo Post ' 
		.$pub->getPreArchiving(). '; #SherpaRomeo PDF '.$pub->getPdfArchiving() . '; ' . $content_json->extra ;  
	  
		
	  }
      $url_base = 'https://api.zotero.org/'.$type.'/'.$userid.'/';
      $client = new Zend\Http\Client();
      $client->setUri($url_base.'items/'.$item_key.'?key='.$zotero_key);
      $client->setHeaders([
      'Content-Type' => 'application/json',
      'If-Match' => $etag,
      ]);
      $client->setRawBody(json_encode($content_json));
      $client->setMethod('PUT');
      $update_response = $client->send();

      if (200 == $update_response->getStatusCode()){
        echo "Nothing to do for ".$item_key."\n";
      }else if (204 == $update_response->getStatusCode()){
        echo "Performed an update (".$update_response->getStatusCode().") for ".$item_key. " ".$content_json->publicationTitle."\n";
      }
    }else{
      echo "Couldn't find single publisher for ".$item_key." ".$content_json->publicationTitle."\n";
    }
  }else{
    echo "No ISSN or publicationTitle for ".$item_key." ".$content_json->publicationTitle."\n";
  }
}

function usage(){
  echo "\n";
  echo "zotero_sherpa.pbp [zotero_user] [zotero_key] [sherpa_romeo_key] [collection_type (user/group)] [limit (update most recent)] [iterate]";
  echo "\n";
}

?>
