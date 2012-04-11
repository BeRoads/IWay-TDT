<?php
/* Copyright (C) 2011 by iRail vzw/asbl
 *
 * Author: Quentin Kaiser <kaiserquentin@gmail.com>
 * License: AGPLv3
 *
 * This method of IWay will get all the radars of belgium territory
 */
include_once 'Geocoder.php';
class IWayRadar extends AResource{

     
     private $from;
     private $area;
     private $max;

     public static function getParameters(){
	  return array("max" => "Maximum of radars you want to retrieve",
		       "from" => "Geographic coordinates you want data around (format : latitude,longitude)",
		       "area" => "Area around from parameter where you want to retrieve radars",
                        "_dc" => "Custom param for sencha touch paging",
                        "page" =>   "Custom param for sencha touch paging",
                        "start" => "Custom param for sencha touch paging",
                        "limit" => "Custom param for sencha touch paging",
                        "group" => "Custom param for sencha touch paging",
                        "filters" => "Custom param for sencha touch paging",
                        "sorters" => "Custom param for sencha touch paging"
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
     }

	private function getData(){
		
	 	R::setup(Config::$DB, Config::$DB_USER, Config::$DB_PASSWORD);
		
		$radars = R::find("radars", "name LIKE '%'");
		$result = new stdClass();
		for($i=0; $i < count($radars); $i++){

			$result->item[$i] = new stdClass();
			$result->item[$i]->name = $radars[$i]->name;
			$result->item[$i]->lat = $radars[$i]->lat;
			$result->item[$i]->lng = $radars[$i]->lon;
			$result->item[$i]->speedLimit = $radars[$i]->speedLimit;
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
	  if($this->from != "" && $this->area > 0){
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
	  
	  /* Max parameter */
	  //As elements are stored in cache, if a user request items with max parameter there will be missing items for next requests
	  // so I use array_slice, that's NOT lazy :)
	  if($this->max > 0 && $this->max < count($element->item))
			$element->item = array_slice($element->item, 0, $this->max);

		
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
