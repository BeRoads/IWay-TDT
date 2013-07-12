<?php
/* Copyright (C) 2011 by iRail vzw/asbl
 *
 * Author: Quentin Kaiser <kaiserquentin@gmail.com>
 * License: AGPLv3
 *
 * This method of IWay will get all the radars of belgium territory
 */
include_once 'Geocoder.php';
include_once 'simple_html_dom.php';

class IWayRadar extends AResource{

     
    private $from;
    private $area;
    private $max;

    
    public static function getParameters(){
		return array(
			"max" => "Maximum of radars you want to retrieve",
		    "from" => "Geographic coordinates that you want data around (format : latitude,longitude)",
		    "offset" => "Offset let you request radars with pagination"
		);
	}

    
    public static function getRequiredParameters(){
		return array();
    }

    
    public function setParameter($key,$val){
		if($key == "max"){
			$this->max = $val;
	  	}
	  	else if($key == "from"){
			$this->from = explode(",",$val);
		}
	  	else if($key == "area"){
			$this->area = $val;
	  	}
	  	else if($key == "offset"){
			$this->offset = $val;
	  	}
    }

	
	private function getData(){
		
		R::setup(Config::$DB, Config::$DB_USER, Config::$DB_PASSWORD);


		$radars = R::find("radars", " name LIKE '%'");
		$result = new stdClass();
		
		for($i=0; $i < count($radars); $i++){

			$result->item[$i] = new stdClass();
			$result->item[$i]->name = $radars[$i]->name;
			$result->item[$i]->lat = $radars[$i]->lat;
			$result->item[$i]->lng = $radars[$i]->lon;
			$result->item[$i]->date = date('d-m-Y');
			$result->item[$i]->type = "fixed";
			$result->item[$i]->speedLimit = $radars[$i]->speedLimit;
		
		}

		$url = "www.polfed-fedpol.be/verkeer/verkeer_radar_fr.php";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);

		$html = str_get_html($response);
		
		$today = date('d');
		$found = false;
		$i = 0;
		foreach($html->find('TABLE[width=600]') as $table){
			foreach($table->find('span[class=textehome]') as $span){
		
				foreach($span->find('a') as $a){
					$date = $a->name."-".date('m-Y');
					if($a->name == $today){
						$found = true;		 	
					}
				}
			}
			if($found){
				foreach($table->find('TR') as $tr){
					$name = "";
			
					foreach($tr->find('TD[class=textehome]') as $td){
						if($td->width == "143"){
							$name = $td->plaintext;
						}
						else if($td->width!=25 && $td->width!=40 && $td->bgcolor == "#F5F5FC" && !strstr($td, 'center')){
							$name .= " ".$td->plaintext;
						}
					}
					if($name != ""){
						$result->item[++$i] = new stdClass();			
						$result->item[$i]->date = $date;
						$result->item[$i]->type = "mobile";
						$result->item[$i]->speedLimit = 0;
						$result->item[$i]->name = utf8_encode($name);
						$coordinates = Geocoder::geocode("Belgium, ".$result->item[$i]->name);
						$result->item[$i]->lat = $coordinates["latitude"];
						$result->item[$i]->lng = $coordinates["longitude"];
					}
				}
			}
		}	
		return $result;
     }
     

    public function call(){
     
    	$c = Cache::getInstance();
		$element = $c->get("radars");
		if(is_null($element)){
			$element = $this->getData();
			$c->set("radars", $element, 600);
		}
	  
      	/* From, area and proximity */
	  	if($this->from != ""){
	    	
			//workaround to return distance even if there is no area
			if(!isset($this->area)){
				$this->area = 500;
			}

	    	$items = array();
	   	  	
	   	  	for($i = 0; $i < count($element->item); $i++){
		  		
		  		$distance = Geocoder::distance(array("latitude"=>$this->from[0], "longitude"=>$this->from[1]),array("latitude"=>$element->item[$i]->lat, "longitude"=>$element->item[$i]->lng));
				if($distance < $this->area){
					$element->item[$i]->distance = $distance;				
					array_push($items, $element->item[$i]);
				}
			}
			usort($items, 'Geocoder::cmpDistances');
			$element->item = $items;
		}
	  
	  	//numerotation
		$i = 0;		
		foreach($element->item as $item){
			$item->id = $i++;
		}
	  	/* Max parameter */
	  	//As elements are stored in cache, if a user request items with max parameter there will be missing items for next requests
	  	// so I use array_slice, that's NOT lazy :)
	  	$element->item = array_slice($element->item, (isset($this->offset) ? $this->offset : 0), (isset($this->max) ? $this->max : count($element->item))+1);
		return $element;
     }

    public static function getAllowedPrintMethods(){
		return array("json","xml", "jsonp", "php", "html", "kml", "map");
    }

    
    public static function getDoc(){
		return "Radar return a list of all known radars"; 
    }
}
?>