<?php
/* Copyright (C) 2011 by iRail vzw/asbl
 *
 * Author: Quentin Kaiser <kaiserquentin@gmail.com>
 * License: AGPLv3
 *
 * This method of IWay will get the traffic events of Belgian traffic jams, accidents and works
 */
ini_set('display_errors', 'On');
include_once "Geocoder.php";
include_once "Tools.class.php";
class IWayTrafficEvent extends AResource{

	private $lang;
	private $region;
	private $from;
	private $area;
	private $max;

	public static function getParameters(){
		return array(
			"lang" => "Language in which the newsfeed should be returned", 
			"region" => "Region that you want data from",
			"max" => "Maximum of events that you want to retrieve",
			"from" => "Geographic coordinates you want data around (format : lat,lng)",
			"area" => "Area around <from> where you want to retrieve events"
		);
    }

    
    public static function getRequiredParameters(){
    	return array("lang","region");
	}


	public function setParameter($key,$val){
		if($key == "lang"){
			$this->lang = $val;
		}
		else if($key == "region"){
			$this->region = $val;
		}
		else if($key == "max"){
			$this->max = $val;
		}
		else if($key == "from"){
			$this->from = explode(",",$val);
		}
		else if($key == "area"){
			$this->area = $val;
		}
	}

    
    public function call(){
		
    	$c = Cache::getInstance();
		$element = new stdClass();
		$element->item = array();	
		if($this->region == 'all'){
			$regions = array("federal", "brussels", "flanders", "wallonia");
			foreach($regions as $region){
				$this->region = $region;
				$element_all = $c->get("traffic" . $this->region . $this->lang);
				if(is_null($element_all)){
					$data = $this->getData();
					$element_all = $this->parseData($data);
					$element_all = $this->geocodeData($element_all);
					$c->set("traffic" . $this->region . $this->lang, $element_all, 900);
				}else{
					if(count($element_all->item)>0){ 
						$data = $this->getData();
						$current_element = $c->get("traffic" . $this->region . $this->lang);
						$new_element = $this->parseData($data);
						$merge_element = new stdClass();
						$merge_element->item = array();
	
						//we search element that needs to be merge into cache
						foreach($new_element->item as $new_item){
							$to_merge = true;
							foreach($current_element->item as $current_item){
								if(strcmp($new_item->location, $current_item->location)==0)
									$to_merge = false;
								}
								if($to_merge)
								array_push($merge_element->item, $new_item);
			                }
							$merge_element = $this->geocodeData($merge_element);
							$current_element->item = array_merge($current_element->item, $merge_element->item);
							$element_all = $current_element;
							$c->set("traffic" . $this->region . $this->lang, $current_element, 900);
					}
				}
				$element->item = array_merge($element->item, $element_all->item);
			}	
		}else{
			$element = $c->get("traffic" . $this->region . $this->lang);
			if(is_null($element)){
				$data = $this->getData();
				$element = $this->parseData($data);
				$element = $this->geocodeData($element);
				$c->set("traffic" . $this->region . $this->lang, $element, 900);
			}else{
				$data = $this->getData();
				$current_element = $c->get("traffic" . $this->region . $this->lang);
				$new_element = $this->parseData($data);
				$merge_element = new stdClass();
				$merge_element->item = array();
				
				//we search element that needs to be merge into cache
				foreach($new_element->item as $new_item){
					$to_merge = true;
					foreach($current_element->item as $current_item){
						if($new_item->location == $current_item->location)
		                	$to_merge = false;
						}
						if($to_merge)
							array_push($merge_element, $new_item);
						}
						$merge_element = $this->geocodeData($merge_element);
						$current_element->item = array_merge($current_element->item, $merge_element->item);
						$element = $current_element;
						$c->set("traffic" . $this->region . $this->lang, $current_element, 900);
			}
		}

		//distance computing 
		if($this->from != ""){

			//workaround to return distance even if there is no area
			if(!isset($this->area)){
				$this->area = 500;
			}
    		
			$distance_items = array();
		 	foreach($element->item as $item){
				$distance = Geocoder::distance(array("latitude"=>$this->from[0], "longitude"=>$this->from[1]),array("latitude"=>$item->lat, "longitude"=>$item->lng));				 
				if($distance < $this->area){
					$item->distance = $distance;
					array_push($distance_items, $item);
				}
			}
			usort($distance_items, 'Geocoder::cmpDistances'); 
			$element->item = $distance_items;
		}
		 
		/* Max parameter */
		//As elements are stored in cache, if a user request items with max parameter there will be missing items for next requests
		// so I use array_slice, that's NOT lazy :)
		if($this->max > 0 && $this->max < count($element->item))
			$element->item = array_slice($element->item, 0, $this->max);
		//numerotation
		$i = 0;		
		foreach($element->item as $item){
			$item->id = $i++;
		}
		return $element;
	}
    

    private function geocodeData($element){
		if($this->region != "brussels"){
			foreach($element->item as $item){
				if($this->region == "wallonia")
					$coordinates = Geocoder::geocodeData($item->message,$this->region, $this->lang);
				else if($this->region == "flanders")
					$coordinates = Geocoder::geocodeData($item->location, $this->region, $this->lang);
				else if($this->region == "federal")
					$coordinates = Geocoder::geocodeData($item->location, $this->region, $this->lang);
				
				$item->lat = $coordinates['latitude'];
				$item->lng = $coordinates['longitude'];
			}
		}
		return $element;
	}
    
    
    private function getData(){

		$scrapeUrl = "";
		switch($this->region){
			case "wallonia" : 
				$scrapeUrl = 'http://trafiroutes.wallonie.be/trafiroutes/Evenements_'.strtoupper($this->lang).'.rss';
				break;
			case "flanders" : 
				$scrapeUrl = 'http://www.verkeerscentrum.be/verkeersinfo/tekstoverzicht_actueel?lastFunction=info&sortCriterionString=TYPE&sortAscending=true&autoUpdate=&cbxFILE=CHECKED&cbxINC=CHECKED&cbxRMT=CHECKED&cbxINF=CHECKED&cbxVlaanderen=CHECKED&cbxWallonie=CHECKED&cbxBrussel=CHECKED&searchString=&searchStringExactMatch=true';
				break;
			case "brussels" : 
				if($this->lang == "fr")
					$scrapeUrl = 'http://www.bruxellesmobilite.irisnet.be/static/mobiris_files/'.$this->lang.'/alerts.json';	
				else
					$scrapeUrl = 'http://www.bruxellesmobilite.irisnet.be/static/mobiris_files/nl/alerts.json';		
				break;
			case "federal" : 
				if($this->lang == "fr")
					$scrapeUrl = 'http://www.inforoutes.be';
				else
					$scrapeUrl = 'http://www.wegeninfo.be/';
				break;
		}
		return utf8_encode(TDT::HttpRequest($scrapeUrl)->data);	 
	}


	private function parseData($data){
		
		$result = new stdClass();
		$result->item = array();
		$i = 0;
		
		switch($this->region){
			case "wallonia" : 
				try{			
					$xml = new SimpleXMLElement($data);
					foreach($xml->channel->item as $event){
						$result->item[$i] = new StdClass();					
						$result->item[$i]->category =  $this->extractTypeFromDescription($event->description);
						$result->item[$i]->source = 'Trafiroutes';
						$result->item[$i]->time = $this->parseTime(utf8_decode($xml->channel->pubdate));
						$result->item[$i]->message = utf8_decode($event->description);
						$result->item[$i]->location = utf8_decode($event->title);
						$i++;
					}
					return $result;
				}catch(Exception $e){
					$result = new stdClass();
					$result->item = array();
					return $result;
				}
				break;
			case "flanders" :
				try{ 
					preg_match_all('/<tr>.*?<td width="2" bgcolor="#EAF0BF"><\/td>.*?<td width="68" height="31" style="width:68px; height=31px" bgcolor="#EAF0BF" align="center" valign="middle"><img border="0" src="images\/(.*?).gif" alt="" width="31" height="31" \/>.*?<\/td>.*? class="Tekst_bericht">(.*?)<\/span>.*?class="Tekst_bericht">(.*?)\s*<\/span>.*?class="Tekst_bericht">(.*?)<\/span>/smi', $data, $matches, PREG_SET_ORDER);
					//1 = soort
					//2 = location
					//3 = message
					//4 = time
					$i = 0;
					foreach($matches as $match){
						$cat = $match[1];
						$cat = str_ireplace("ongeval_driehoek","accident",$cat);
						$cat = str_ireplace("file_driehoek","traffic jam",$cat);
						$cat = str_ireplace("i_bol","info",$cat);
						$cat = str_ireplace("werkman","works",$cat);
						$location = trim(utf8_decode(str_replace("\s\s+"," ",strip_tags($match[2]))));
						$pattern = '/(\w)(\d+)/i';
						$replacement = '$1$2 ';
						$location =  preg_replace($pattern, $replacement, $location);
						$result->item[$i] = new StdClass();
						$result->item[$i]->category = trim(str_replace("\s\s+"," ",strip_tags($cat)));
						$result->item[$i]->location = $location;
						$result->item[$i]->message = utf8_decode(trim(str_replace('meer informatie', '', str_replace("\s\s+"," ",strip_tags($match[3])))));
						$result->item[$i]->time = Time($this->parseTime(trim(str_replace("\s\s+"," ",strip_tags($match[4])))));
						$result->item[$i]->source = "Verkeerscentrum";
						$i++;
					}
					return $result;
				}catch(Exception $e){
					$result = new stdClass();
					$result->item = array();
					return $result;
				}
				break;
			case "brussels" : 
				try{
					$json_tab = json_decode($data);
					foreach($json_tab->{'features'} as $element) {
						$result->item[$i] = new StdClass();
						$result->item[$i]->category = strtolower($element->{'properties'}->{'category'});
						$result->item[$i]->source = 'Mobiris';
						//$result->item[$i]->time = date('Y-m-j H:i:s');
						$result->item[$i]->time = time();
						$result->item[$i]->message = $element->{'properties'}->{'cause'};
						$result->item[$i]->location = $element->{'properties'}->{'street_name'};
						$coords = $element->{'geometry'}->{'coordinates'};
						$coordinates = Tools::LambertToWGS84($coords[0], $coords[1]);
						$result->item[$i]->lat = $coordinates[0];
						$result->item[$i]->lng = $coordinates[1];
						$i++;
					}
					return $result;
				}catch(Exception $e){
					$result = new stdClass();
					$result->item = array();
					return $result;
				}
				break;

			case "federal" : 
				try{
					include_once 'simple_html_dom.php';
					$html = str_get_html($data);
					$tab = $html->find('TD[class=textehome]');
					$messages = $html->find('font[class=textehome]');
					$location_counter = 8;
					$time_counter = 9;

					while($location_counter < count($tab)-12){
						//processing
						$message = utf8_decode(strip_tags($messages[$i]->innertext));
						$source = explode(":", $message);
						$message = html_entity_decode(preg_replace('/\s\s+/', ' ',str_replace($source[0].":".$source[1].":", "", $message)));
						$source = utf8_encode(html_entity_decode(str_replace(" meldt","", str_replace(" signale", "", $source[1]))));
						
						$result->item[$i] = new stdClass();
						$result->item[$i]->message = utf8_encode($message);
						$result->item[$i]->location = utf8_encode(html_entity_decode(utf8_decode(strip_tags($tab[$location_counter]->innertext))));
						$result->item[$i]->source = $source;
						$result->item[$i]->time = time();
						$result->item[$i]->category =  $this->extractTypeFromDescription($this->region, $result->item[$i]->message); 
						$i++;
						$location_counter += 4;
						$time_counter += 4; 
					}
					return $result;
				}catch(Exception $e){
					$result = new stdClass();
					$result->item = array();
					return $result;
				}
				break;
		}
	}


	/**
	  * Parses the time according to Het Verkeerscentrum
	  */
	private function parseTime($str){
		
		$months = array("janv"=>1,"fevr"=>2,"mar"=>3,"avr"=>4,"mai"=>5,"juin"=>6,"juil"=>7,"aout"=>8,"sept"=>9,"oct"=>10,"nov"=>11,"dec"=> 12);
     	switch($this->region){
     		case "wallonia" : 
     	  		//sam., 10 sept. 2011 23:57:43 +0200
     	  		//throw new Exception($str);
				preg_match("/(\w+)., (\d+) ([a-zA-Zéû]+). (\d+) ((\d\d):(\d\d):(\d\d)) +(\d+)?/",$str,$match);
				if(sizeof($match)==7){
					$h = $match[6];
					$i = $match[7];
					$d = date("d");
					$m = date("m");
					$y = date("y");
					$search = explode(",","\E7,\E6,.,\E1,\E9,\ED,\F3,\FA,\E0,\E8,\EC,\F2,\F9,\E4,\EB,\EF,\F6,\FC,\FF,\E2,\EA,\EE,\F4,\FB,\E5,e,i,\F8,u");
					$replace = explode(",","c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u");
					if(isset($match[3])){
						$d = $match[2];
						$m = $months[$match[3]];
						$y = $match[4];
					}
					return mktime($h,$i,0,$m,$d,$y);
				}
				break;

				case "flanders" : 
					preg_match("/([0-2][0-9]):([0-5][0-9])( (\d\d)-(\d\d)-(\d\d))?/",$str,$match);
					$h = $match[1];
					$i = $match[2];
					$d = date("d");
					$m = date("m");
					$y = date("y");
					if(isset($match[3])){
						$d = $match[4];
						$m = $match[5];
						$y = $match[6];
					}

					$y = "20".$y;
					return mktime($h,$i,0,$m,$d,$y);
					break;
		}
	}


	private function extractTypeFromDescription($description){

		$type = "others";
		switch($this->region){
			case "wallonia" :
				if(!(stripos($description,"travaux")===false) || !(stripos($description,"chantier")===false)) {
					$type = "works";
				}else if(!(stripos($description,"accident")===false) || !(stripos($description,"incident")===false) || !(stripos($description,"Perte")===false) 
					|| !(stripos($description,"Parking ferm\E9")===false) || !(stripos($description,"Degradation")===false)) {
					$type = "events";
				}
				break;
			case "flanders" :
				if(!(stripos($description,"Ongeval")===false) || !(stripos($description,"File")===false)) {
					$type = "events";
				}
				elseif(!(stripos($description, "rijstrook afgesloten")==false) || !(stripos($description, "rijstroken afgesloten")==false) || 
					!(stripos($description, "Mobiele onderhoudsvoertuigen")==false)) {
					$type = "works";
				}
				break;
			case "federal" :
				if(!(stripos($description,"travaux")===false) || !(stripos($description,"chantier")===false)) {
					$type = "works";
				}
				else if(!(stripos($description,"accident")===false) || !(stripos($description,"incident")===false)) {
					$type = "events";
				}
				break;
		}
		return $type;
	}


	public static function getAllowedPrintMethods(){
		return array("json","xml", "jsonp", "php", "html", "kml", "map");
	}


	public static function getDoc(){
		return "TrafficEvent return the latest trafic events by region or around geographic coordinate.";
	}
}
?>